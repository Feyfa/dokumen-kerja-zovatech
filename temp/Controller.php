<?php

namespace App\Http\Controllers;

use App\Jobs\SendEmailJob;
use App\Mail\Gmail;
use App\Models\Company;
use App\Models\CompanySale;
use App\Models\CompanySetting;
use App\Models\CompanyStripe;
use App\Models\EmailNotification;
use App\Models\FailedLeadRecord;
use App\Models\FailedRecord;
use App\Models\IntegrationSettings;
use App\Models\LeadspeekReport;
use App\Models\LeadspeekUser;
use App\Models\ReportAnalytic;
use App\Models\User;
use App\Services\BigDBM;
use Illuminate\Support\Str;
use Exception;
use GuzzleHttp\Client;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Http\Client\RequestException;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Stripe\Exception\ApiConnectionException;
use Stripe\Exception\ApiErrorException;
use Stripe\Exception\AuthenticationException as ExceptionAuthenticationException;
use Stripe\Exception\OAuth\InvalidRequestException;
use Stripe\Exception\RateLimitException;
use Stripe\StripeClient;
use Swift_Mailer;
use Swift_SmtpTransport;
use Swift_TransportException;
use Throwable;
use App\Models\GlobalSettings;
use App\Http\Controllers\WebhookController;
use App\Models\CleanIDFile;
use App\Models\LeadspeekInvoice;
use App\Models\MasterFeature;
use App\Models\PixelLeadRecord;
use App\Models\ServicesAgreement;
use App\Models\TopupAgency;
use App\Models\TopupCleanId;
use App\Models\UserLog;
use Carbon\Carbon;
use Illuminate\Support\Facades\App;
use Illuminate\Http\Request;
use Closure;

class Controller extends BaseController
{
    use AuthorizesRequests, DispatchesJobs, ValidatesRequests;

    /* FOR GETLEADWEBHOOK */
    private function rememberIsCampaignLocal(string $cacheKey, int $ttlIfTrue, int $ttlIfFalse, Closure $resolver)
    {
        try 
        {
            // coba check di redis apakah ada keynya jika ada return
            $cached = Cache::store('redis')->get($cacheKey);
            // info('rememberIsCampaignLocal 1.1', ['cacheKey' => $cacheKey, 'cached' => $cached]);
            if(!is_null($cached)){
                // info('rememberIsCampaignLocal 1.2');
                return (bool) $cached;
            }

            // jika tidak ada maka jalankan callback resolver, dan put hasilnya ke redis
            $value = (bool) $resolver();
            $ttl = $value ? $ttlIfTrue : $ttlIfFalse;
            Cache::store('redis')->put($cacheKey, $value, $ttl);
            // info('rememberIsCampaignLocal 1.3', ['cacheKey' => $cacheKey, 'value' => $value, 'ttl' => $ttl]);

            return $value;
        }
        catch(\Throwable $e)
        {
            info("rememberIsCampaignLocal error : {$e->getMessage()}");
            return false;
        }
    }

    private function extractLeadspeekApiId($label)
    {
        try
        {
            if(empty($label)){
                return null;
            }

            // cari delimiter terdekat (| atau -)
            $posPipe = strpos($label, '|');
            $posDash = strpos($label, '-');

            if($posPipe === false && $posDash === false){
                return null;
            }

            if($posPipe === false){
                return substr($label, 0, $posDash);
            }

            if($posDash === false){
                return substr($label, 0, $posPipe);
            }

            return substr($label, 0, min($posPipe, $posDash));
        }
        catch(\Throwable $e)
        {
            info("extractLeadspeekApiId error : {$e->getMessage()}");
            return null;
        }
    }

    public function buildLegacyLabelArray($label)
    {
        try
        {
            /*
                Contoh Case
                * INPUT : "{campaignid}-{pixelLeadRecordID}|{keyword}" OUTPUT : "{campaignid}|{keyword}|{pixelLeadRecordID}"
                * INPUT : "{campaignid}-{pixelLeadRecordID}-{customParams}|{keyword}" OUTPUT : "{campaignid}|{keyword}|{pixelLeadRecordID}|{customParams}"
            */



            /* =====VALIDATION LABEL===== */
            // validation is campaign local or not
            // info('buildLegacyLabelArray 1.1');
            $leadspeek_api_id = $this->extractLeadspeekApiId($label);
            $appEnvironment = env('DEVELOPEMODE', false) ? 'sandbox' : 'production';
            $cacheKey = "{$appEnvironment}_buildLegacyLabelArray_iscampaignlocal_{$leadspeek_api_id}";
            $isCampaignLocal = $this->rememberIsCampaignLocal($cacheKey,86400,600,function () use ($leadspeek_api_id) {
                return LeadspeekUser::where('leadspeek_api_id', $leadspeek_api_id)->where('leadspeek_type', 'local')->exists();
            });
            if(!$isCampaignLocal || empty($label)){
                // info('buildLegacyLabelArray 1.2');
                return explode('|', $label);
            }

            // validation apakah format label masih pakai yang lama "{campaignid}|{keyword}|{pixelleadrecordid}" atau "{campaignid}|{keyword}"
            $pipePosFirst = strpos($label, '|');
            // info('buildLegacyLabelArray 1.3', ['label' => $label, 'pipePosFirst' => $pipePosFirst]);
            if($pipePosFirst === false){
                // info('buildLegacyLabelArray 1.4');
                return explode('|', $label);
            }
            $headFirst = substr($label, 0, $pipePosFirst);
            $isLabelOldFormat = strpos($headFirst, '-') === false;
            // info('buildLegacyLabelArray 1.5', ['headFirst' => $headFirst, 'isLabelOldFormat' => $isLabelOldFormat]);
            if($isLabelOldFormat){
                // info('buildLegacyLabelArray 1.6');
                return explode('|', $label);
            }
            /* =====VALIDATION LABEL===== */



            /* =====START LOGIC NEW LABEL===== */
            // siapkan format array nya
            // info('buildLegacyLabelArray 1.7');
            $data = [
                0 => null, // leadspeek_api_id
                1 => null, // keyword
                2 => null, // pixelLeadRecordID
                3 => null, // customParams
            ];

            // Pisahkan keyword → selalu ambil PIPE TERAKHIR
            $pipePosLast = strrpos($label, '|');
            // info('buildLegacyLabelArray 1.8', ['pipePosLast' => $pipePosLast]);
            if($pipePosLast === false){
                // info('buildLegacyLabelArray 1.9');
                return $data;
            }
            $head = substr($label, 0, $pipePosLast);
            $keyword = substr($label, $pipePosLast + 1);
            // info('buildLegacyLabelArray 1.10', ['head' => $head, 'keyword' => $keyword]);

            // Parse bagian depan (ID, pixel, custom) dan masukan keyword
            if(strpos($head, '-') !== false){
                // Ambil maksimal 3 part: id - pixel - custom
                $parts = explode('-', $head, 3);
                $data[0] = !empty($parts[0] ?? null) ? trim($parts[0] ?? null) : null; // leadspeek_api_id
                $data[2] = !empty($parts[1] ?? null) ? trim($parts[1] ?? null) : null; // pixelLeadRecordID
                $data[3] = !empty($parts[2] ?? null) ? trim($parts[2] ?? null) : null; // customParams
                // info('buildLegacyLabelArray 1.11', ['parts' => $parts]);
            }
            $data[1] = trim($keyword ?? "");
            // info('buildLegacyLabelArray 1.12', ['data' => $data]);

            return $data;
            /* =====START LOGIC NEW LABEL===== */
        }
        catch(\Throwable $e)
        {
            info("buildLegacyLabelArray error : {$e->getMessage()}");
            return [];
        }
    }
    /* FOR GETLEADWEBHOOK */

    public function logUserAction($userID,$action,$desc,$userIP = "",$target = null) 
    {
        date_default_timezone_set('America/Chicago');
        try {
            /** INSERT INTO USER LOG TABLE */
            $queryUserLog = UserLog::create([
                'user_id' => $userID,
                'user_ip' => $userIP,
                'action' => $action,
                'description' => $desc,
                'target_user_id' => $target,
            ]);
            /** INSERT INTO USER LOG TABLE */
        } catch (\Throwable $th) {
            Log::info('Error logUserAction: ' . $th->getMessage());
        }
    }

    public function createFailedLeadRecord($email_encrypt,$leadspeek_api_id,$function,$url,$type,$description)
    {
        FailedLeadRecord::create([
            'email_encrypt' => $email_encrypt,
            'leadspeek_api_id' => $leadspeek_api_id,
            'function' => $function,
            'url' => $url,
            'type' => $type,
            'description' => $description,
        ]);
    }

    public function getvariableVisitorClientBrowser(Request $request)
    {
        // info(__FUNCTION__, ['request' => $request->all()]);
        $visitorIdClientBrowser = $request->visitorId ?? null;
        $urlClientBrowser = $request->url ?? null;
        $ipClientBrowser = $request->header('X-Forwarded-For') ?: ($request->ipClient ?? null);
        $dateTimeClientBrowser = $request->date ?? null;
        $timeZoneClientBrowser = $request->timeZone ?? null;
        $screenWidthClientBrowser = $request->screenWidth ?? null;
        $screenHeightClientBrowser = $request->screenHeight ?? null;
        $viewportWidthClientBrowser = $request->viewportWidth ?? null;
        $viewportHeightClientBrowser = $request->viewportHeight ?? null;
        $pixelRatioClientBrowser = $request->pixelRatio ?? null;
        $deviceTypeClientBrowser = $request->deviceType ?? null;
        $customParams = (isset($request->customParams) && is_string($request->customParams))?trim($request->customParams):'';
        $canvasFingerprintClientBrowser = $request->canvasFingerprint ?? null;
        $webGLFingerprintClientBrowser = $request->webGLFingerprint ?? null;
        $botDetectedClientBrowser = $request->botDetected ?? null;
        $botIndicatorsClientBrowser = $request->botIndicators ?? null;
        $browserInfoClientBrowser = $request->browserInfo ?? null;
        $extendedInfoClientBrowser = $request->extendedInfo ?? null;
        $geolocationClientBrowser = $request->geolocation ?? null;
        $geolocationLatitudeClientBrowser = $request->geolatitude ?? null;
        $geolocationLongitudeClientBrowser = $request->geolongitude ?? null;
        $geolocationAccuracyClientBrowser = $request->geolocationaccuracy ?? null;
        $geolocationTimestampClientBrowser = $request->geolocationtimestamp ?? null;
        $incognitoClientBrowser = $request->incognito ?? null;
        $incognitoDetectedClientBrowser = $request->incognitoDetected ?? null;
        $incognitoIndicatorsClientBrowser = $request->incognitoIndicators ?? null;
        $incognitoDetailsClientBrowser = $request->incognitoDetails ?? null;
        $vpnClientBrowser = $request->vpn ?? null;
        $vpnDetectedClientBrowser = $request->vpnDetected ?? null;
        $vpnIndicatorsClientBrowser = $request->vpnIndicators ?? null;
        $vpnDetailsClientBrowser = $request->vpnDetails ?? null;
        $compositeFingerprintClientBrowser = $request->compositeFingerprint ?? null;
        $fingerprintIdClientBrowser = $request->fingerprintId ?? null;
        $fingerprintConfidenceClientBrowser = $request->fingerprintConfidence ?? null;
        $fingerprintLayersClientBrowser = $request->fingerprintLayers ?? null;
        $deviceIdClientBrowser = $request->deviceId ?? null;
        // $emailClientBrowser = $request->email ?? null;
        // $firstnameClientBrowser = $request->firstname ?? null;
        // $lastnameClientBrowser = $request->lastname ?? null;
        // $nameClientBrowser = $request->name ?? null;
        
        $dataVisitorClientBrowser = [
            'headers' => $request->headers->all(),
            'url' => $urlClientBrowser,
            'ip' => $ipClientBrowser,
            'date' => $dateTimeClientBrowser,
            'timezone' => $timeZoneClientBrowser,
            'screenWidth' => $screenWidthClientBrowser,
            'screenHeight' => $screenHeightClientBrowser,
            'viewportWidth' => $viewportWidthClientBrowser,
            'viewportHeight' => $viewportHeightClientBrowser,
            'pixelRatio' => $pixelRatioClientBrowser,
            'deviceType' => $deviceTypeClientBrowser,
            'custom_params' => $customParams,
            'canvasFingerprint' => $canvasFingerprintClientBrowser,
            'webGLFingerprint' => $webGLFingerprintClientBrowser,
            'botDetected' => $botDetectedClientBrowser,
            'botIndicators' => $botIndicatorsClientBrowser,
            'browserInfo' => $browserInfoClientBrowser,
            'extendedInfo' => $extendedInfoClientBrowser,
            'geolocation' => $geolocationClientBrowser,
            'incognito' => $incognitoClientBrowser,
            'vpn' => $vpnClientBrowser,
            'fingerprint' => $compositeFingerprintClientBrowser,
            // 'email' => $emailClientBrowser,
            // 'firstname' => $firstnameClientBrowser,
            // 'lastname' => $lastnameClientBrowser,
            // 'name' => $nameClientBrowser,
        ];
        $dataPixelLeadRecord = [
            'pixel_status' => 'pending',
            'leadspeek_api_id' => null,
            'custom_params' => $customParams,
            'visitor_id' => $visitorIdClientBrowser,
            'visitor_data' => json_encode($dataVisitorClientBrowser, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'function' => 'renderingPixel',
            'url' => $urlClientBrowser,
            'ip_address' => $ipClientBrowser,
            'date' => $dateTimeClientBrowser,
            'timezone' => $timeZoneClientBrowser,
            'bot_detected' => ($botDetectedClientBrowser == 'true') ? 'T' : 'F',
            'bot_indicators' => $botIndicatorsClientBrowser,
            'geo_latitude' => $geolocationLatitudeClientBrowser,
            'geo_longitude' => $geolocationLongitudeClientBrowser,
            'geo_location_accuracy' => $geolocationAccuracyClientBrowser,
            'geo_location_timestamp' => $geolocationTimestampClientBrowser,
            'incognito_detected' => ($incognitoDetectedClientBrowser == 'true') ? 'T' : 'F',
            'incognito_indicators' => $incognitoIndicatorsClientBrowser,
            'incognito_details' => $incognitoDetailsClientBrowser,
            'vpn_detected' => ($vpnDetectedClientBrowser == 'true') ? 'T' : 'F',
            'vpn_indicators' => $vpnIndicatorsClientBrowser,
            'vpn_details' => $vpnDetailsClientBrowser,
            'fingerprint_id' => $fingerprintIdClientBrowser,
            'fingerprint_confidence' => $fingerprintConfidenceClientBrowser,
            'fingerprint_layers' => $fingerprintLayersClientBrowser,
            'device_id' => $deviceIdClientBrowser,
            'screen_width' => $screenWidthClientBrowser,
            'screen_height' => $screenHeightClientBrowser,
            'viewport_width' => $viewportWidthClientBrowser,
            'viewport_height' => $viewportHeightClientBrowser,
            'pixel_ratio' => $pixelRatioClientBrowser,
            'device_type' => $deviceTypeClientBrowser,
            'campaign_status' => null,
        ];
        return ['dataPixelLeadRecord' => $dataPixelLeadRecord, 'visitorIdClientBrowser' => $visitorIdClientBrowser];
    }
    
    public function UpdateFeedbackPixelLeadRecord($pixelLeadRecordID, $leadspeekApiId, $md5param, $labelOriginal)
    {
        // info('UpdateFeedbackPixelLeadRecord', ['get_defined_vars' => get_defined_vars()]);
        try
        { 
            $labelOriginalArray = explode('|', ($labelOriginal ?? ""));
            if(!empty($pixelLeadRecordID)) // jika pixel lead record id ada
            {
                // info('UpdateFeedbackPixelLeadRecord if');
                $pixelLeadRecord = PixelLeadRecord::where('id','=',$pixelLeadRecordID)->where('leadspeek_api_id','=',$leadspeekApiId)->first();
                if(!empty($pixelLeadRecord))
                {
                    $md5_list = json_decode($pixelLeadRecord->md5_list, true);
                    $md5_list = is_array($md5_list) ? $md5_list : [];
                    $md5_list[] = $md5param;
                    $pixelLeadRecord->md5_list = json_encode($md5_list, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

                    $pixelLeadRecord->pixel_status = 'valid_label';
                    $pixelLeadRecord->lead_fire = ((int) ($pixelLeadRecord->lead_fire ?? 0)) + 1;
                    $pixelLeadRecord->date_fire = date('Y-m-d');
                    $pixelLeadRecord->label = json_encode($labelOriginalArray, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                    $pixelLeadRecord->save();
                }
            }
            else // jika pixel lead record id tidak ada
            {
                // info('UpdateFeedbackPixelLeadRecord else');
                $md5_list = [$md5param];
                // PixelLeadRecord::create([
                //     'md5_list' => json_encode($md5_list, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                //     'campaign_status' => 'running',
                //     'lead_fire' => null,
                //     'pixel_status' => 'invalid_label',
                //     'leadspeek_api_id' => $leadspeekApiId,
                //     'date_fire' => date('Y-m-d'),
                //     'label' => json_encode($labelOriginalArray, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                // ]);
            }
        }
        catch(\Exception $e)
        {
            Log::error('Error UpdateFeedbackPixelLeadRecord: ' . $e->getMessage());
        }
    }

    public function UpsertPixelLeadRecord(array $data)
    {
        try 
        {
            // Validasi dan normalisasi format datetime
            if(isset($data['date'])){
                $data['date'] = (Carbon::hasFormat($data['date'], 'Y-m-d H:i:s')) ? 
                                (Carbon::createFromFormat('Y-m-d H:i:s', $data['date'])->format('Y-m-d H:i:s')) : 
                                (null) ;
            }
            // $pixelLeadRecord = PixelLeadRecord::create([
            //     'pixel_status' => $data['pixel_status'] ?? null,
            //     'leadspeek_api_id' => $data['leadspeek_api_id'] ?? null,
            //     'campaign_status' => $data['campaign_status'] ?? null,
            //     'custom_params' => $data['custom_params'] ?? null,
            //     'visitor_id' => $data['visitor_id'] ?? null,
            //     'visitor_data' => $data['visitor_data'] ?? null,
            //     'function' => $data['function'] ?? null,
            //     'url' => $data['url'] ?? null,
            //     'ip_address' => $data['ip_address'] ?? null,
            //     'date' => $data['date'] ?? null,
            //     'timezone' => $data['timezone'] ?? null,
            //     'screen_width' => $data['screen_width'] ?? null,
            //     'screen_height' => $data['screen_height'] ?? null,
            //     'viewport_width' => $data['viewport_width'] ?? null,
            //     'viewport_height' => $data['viewport_height'] ?? null,
            //     'pixel_ratio' => $data['pixel_ratio'] ?? null,
            //     'device_type' => $data['device_type'] ?? null,
            //     'bot_detected' => $data['bot_detected'] ?? null,
            //     'bot_indicators' => $data['bot_indicators'] ?? null,
            //     'geo_latitude' => $data['geo_latitude'] ?? null,
            //     'geo_longitude' => $data['geo_longitude'] ?? null,
            //     'geo_location_accuracy' => $data['geo_location_accuracy'] ?? null,
            //     'geo_location_timestamp' => $data['geo_location_timestamp'] ?? null,
            //     'incognito_detected' => $data['incognito_detected'] ?? null,
            //     'incognito_indicators' => $data['incognito_indicators'] ?? null,
            //     'incognito_details' => $data['incognito_details'] ?? null,
            //     'vpn_detected' => $data['vpn_detected'] ?? null,
            //     'vpn_indicators' => $data['vpn_indicators'] ?? null,
            //     'vpn_details' => $data['vpn_details'] ?? null,
            //     'fingerprint_id' => $data['fingerprint_id'] ?? null,
            //     'fingerprint_confidence' => $data['fingerprint_confidence'] ?? null,
            //     'fingerprint_layers' => $data['fingerprint_layers'] ?? null,
            //     'device_id' => $data['device_id'] ?? null,
            // ]);
            // return $pixelLeadRecord->id;
            return "upsertpixelleadrecord";
        } 
        catch (\Exception $e) 
        {
            Log::error('Error UpsertPixelLeadRecord: ' . $e->getMessage());
            return null;
        }
    }

    public function UpsertFailedLeadRecord(array $data, $id = '') 
    {
        info(__FUNCTION__);
        // create field
        if(empty($id))
        {
            $failedLeadRecord = FailedLeadRecord::create([
                /* GLOBAL */
                'function' => (isset($data['function']))?$data['function']:null,
                'type' => (isset($data['type']))?$data['type']:null,
                'blocked_type' => (isset($data['blocked_type']))?$data['blocked_type']:null,
                'description' => (isset($data['description']))?$data['description']:null,
                'leadspeek_api_id' => (isset($data['leadspeek_api_id']))?$data['leadspeek_api_id']:null,
                'clean_file_id' => (isset($data['clean_file_id']))?$data['clean_file_id']:null,
                /* GLOBAL */

                /* LEAD */
                'email_encrypt' => (isset($data['email_encrypt']))?$data['email_encrypt']:null,
                'url' => (isset($data['url']))?$data['url']:null,
                'leadspeek_type' => isset($data['leadspeek_type'])?$data['leadspeek_type']:null,
                'data_lead' => isset($data['data_lead'])?$data['data_lead']:null,
                /* LEAD */

                /* TRUELIST */
                'email' => (isset($data['email']))?$data['email']:null,
                'status' => (isset($data['status']))?$data['status']:null,
                /* TRUELIST */

                /* PROCESS INVOICE */
                // 'invoice_id' => (isset($data['invoice_id']))?$data['invoice_id']:null,
                // 'total_amount' => (isset($data['total_amount']))?$data['total_amount']:null,
                // 'customer_payment_id' => (isset($data['customer_payment_id']))?$data['customer_payment_id']:null,
                // 'payment_method' => (isset($data['payment_method']))?$data['payment_method']:null,
                // 'acc_connect_id' => (isset($data['acc_connect_id']))?$data['acc_connect_id']:null, 
                /* PROCESS INVOICE */
            ]);

            return $failedLeadRecord->id;
        }
        // update field
        else
        {
            $failedLeadRecord = FailedLeadRecord::where('id', $id)
                                                ->first();

            if($failedLeadRecord) {
                // get fillable in model FailedLeadRecord
                $fillable = $failedLeadRecord->getFillable();

                foreach($data as $key => $value) {
                    if(in_array($key, $fillable)) {
                        $failedLeadRecord->$key = $value;
                    }
                }

                $failedLeadRecord->save();

                return $failedLeadRecord->id;
            }

            return null;
        }
    }

    public function UpsertReportAnalytics($leadspeek_api_id,$leadspeekType = "",$typeReport,$value = '', $date = '') {

        $chkExist = ReportAnalytic::select('id');
        
        if($leadspeekType == 'clean_id') {
            $chkExist->where('clean_file_id','=',$leadspeek_api_id);
        } else {
            $chkExist->where('leadspeek_api_id','=',$leadspeek_api_id);
        }

        if ($typeReport == 'simplifi_impressions') {
            $chkExist->where('date','=',$date);
        } else {
            $chkExist->where('date','=',date('Y-m-d'));
        }

        $chkExist = $chkExist->get();

        if (count($chkExist) > 0) {
            $reportAnalytic = ReportAnalytic::find($chkExist[0]['id']);
            if ($typeReport == "pixelfire") {
                $reportAnalytic->pixelfire = $reportAnalytic->pixelfire + 1;
            }else if ($typeReport == 'renderingpixelfire') {
                $reportAnalytic->renderingpixelfire = $reportAnalytic->renderingpixelfire + 1;
            }else if ($typeReport == "towerpostal") {
                $reportAnalytic->towerpostal = $reportAnalytic->towerpostal + 1;
            }else if ($typeReport == "endatoenrichment") {
                $reportAnalytic->endatoenrichment = $reportAnalytic->endatoenrichment + 1;
            }else if ($typeReport == "toweremail") {
                $reportAnalytic->toweremail = $reportAnalytic->toweremail + 1;
            }else if ($typeReport == "zerobounce") {
                $reportAnalytic->zerobounce = $reportAnalytic->zerobounce + 1;
            }else if ($typeReport == "zerobouncefailed") {
                $reportAnalytic->zerobouncefailed = $reportAnalytic->zerobouncefailed + 1;
            }else if ($typeReport == "zerobounce_details" && !empty($value)) {
                if (empty($reportAnalytic->zerobounce_details)) {
                    $zerobounce_details = (object)[
                        'valid' => 0,
                        'invalid' => 0,
                        'catch_all' => 0,
                        'unknown' => 0,
                        'spamtrap' => 0,
                        'abuse' => 0,
                        'do_not_mail' => 0,
                        'total' => 0
                    ];
                } else {
                    $zerobounce_details = json_decode($reportAnalytic->zerobounce_details);

                    foreach (['valid','invalid', 'catch_all', 'unknown', 'spamtrap', 'abuse', 'do_not_mail','total'] as $field) {
                        if (!isset($zerobounce_details->$field)) {
                            $zerobounce_details->$field = 0;
                        }
                    }
                }

                if ($value == 'valid') {
                    $zerobounce_details->valid = $zerobounce_details->valid + 1; 
                }elseif ($value == 'invalid') {
                    $zerobounce_details->invalid = $zerobounce_details->invalid + 1; 
                }elseif ($value == 'catch-all') {
                    $zerobounce_details->catch_all = $zerobounce_details->catch_all + 1; 
                }elseif ($value == 'unknown') {
                    $zerobounce_details->unknown = $zerobounce_details->unknown + 1; 
                }elseif ($value == 'spamtrap') {
                    $zerobounce_details->spamtrap = $zerobounce_details->spamtrap + 1; 
                }elseif ($value == 'abuse') {
                    $zerobounce_details->abuse = $zerobounce_details->abuse + 1; 
                }elseif ($value == 'do_not_mail') {
                    $zerobounce_details->do_not_mail = $zerobounce_details->do_not_mail + 1; 
                }
                $zerobounce_details->total = $zerobounce_details->total + 1;
                $reportAnalytic->zerobounce_details = json_encode($zerobounce_details);
            }else if ($typeReport == "truelist_details" && !empty($value)) {

                if (empty($reportAnalytic->truelist_details)) {
                    $truelist_details = (object)[
                        $value => 0,
                        'total' => 0,
                    ];
                } else {
                    $truelist_details = json_decode($reportAnalytic->truelist_details);
                    if (!isset($truelist_details->$value)) {
                        $truelist_details->$value = 0;
                    }
                    if (!isset($truelist_details->total)) {
                        $truelist_details->total = 0;
                    }
                }
                
                $truelist_details->$value = $truelist_details->$value + 1; 
                $truelist_details->total = $truelist_details->total + 1;
                $reportAnalytic->truelist_details = json_encode($truelist_details);
            }else if ($typeReport == "locationlock") {
                $reportAnalytic->locationlock = $reportAnalytic->locationlock + 1;
            }else if ($typeReport == "locationlockfailed") {
                $reportAnalytic->locationlockfailed = $reportAnalytic->locationlockfailed + 1;
            }else if ($typeReport == "serveclient") {
                $reportAnalytic->serveclient = $reportAnalytic->serveclient + 1;
            }else if ($typeReport == "notserve") {
                $reportAnalytic->notserve = $reportAnalytic->notserve + 1;
            }else if ($typeReport == "bigbdmemail") {
                $reportAnalytic->bigbdmemail = $reportAnalytic->bigbdmemail + 1;
            }else if ($typeReport == "bigbdmpii") {
                $reportAnalytic->bigbdmpii = $reportAnalytic->bigbdmpii + 1;
            }else if($typeReport == 'bigbdmhems') {
                $reportAnalytic->bigbdmhems = $value;
            }else if($typeReport == 'bigbdmtotalleads') {
                $reportAnalytic->bigbdmtotalleads = $value;
            }else if($typeReport == 'bigbdmremainingleads') {
                $reportAnalytic->bigbdmremainingleads = $value;
            }else if($typeReport == 'getleadfailed') {
                $reportAnalytic->getleadfailed = $reportAnalytic->getleadfailed + 1;
            }else if($typeReport == 'getleadfailed_bigbdmmd5') {
                $reportAnalytic->getleadfailed_bigbdmmd5 = $reportAnalytic->getleadfailed_bigbdmmd5 + 1;
            }else if($typeReport == 'getleadfailed_gettowerdata') {
                $reportAnalytic->getleadfailed_gettowerdata = $reportAnalytic->getleadfailed_gettowerdata + 1;
            }else if($typeReport == 'getleadfailed_bigbdmpii') {
                $reportAnalytic->getleadfailed_bigbdmpii = $reportAnalytic->getleadfailed_bigbdmpii + 1;
            }elseif ($typeReport == 'simplifi_impressions') {
                $reportAnalytic->simplifi_impressions = $reportAnalytic->simplifi_impressions + $value; 
            }

            $reportAnalytic->save();

        }else{
            $pixelfire = ($typeReport == "pixelfire")?1:0;
            $renderingpixelfire = ($typeReport == 'renderingpixelfire')?1:0;
            $towerpostal = ($typeReport == "towerpostal")?1:0;
            $endatoenrichment =($typeReport == "endatoenrichment")?1:0;
            $toweremail = ($typeReport == "toweremail")?1:0;
            $zerobounce = ($typeReport == "zerobounce")?1:0;
            $zerobouncefailed = ($typeReport == "zerobouncefailed")?1:0;
            if ($typeReport == "zerobounce_details") {
                $zerobounce_details = (object)[
                    'valid' => ($value == 'valid') ? 1 : 0,
                    'invalid' => ($value == 'invalid') ? 1 : 0,
                    'catch_all' => ($value == 'catch-all') ? 1 : 0,
                    'unknown' => ($value == 'unknown') ? 1 : 0,
                    'spamtrap' => ($value == 'spamtrap') ? 1 : 0,
                    'abuse' => ($value == 'abuse') ? 1 : 0,
                    'do_not_mail' => ($value == 'do_not_mail') ? 1 : 0,
                    'total' => ($value == 'total') ? 1 : 0
                ];
            }else {
                $zerobounce_details = (object)[
                    'valid' => 0,
                    'invalid' => 0,
                    'catch_all' => 0,
                    'unknown' => 0,
                    'spamtrap' => 0,
                    'abuse' => 0,
                    'do_not_mail' => 0,
                    'total' => 0
                ];
            }

            if ($typeReport == "truelist_details") {
                if (!empty($value)) {
                    $truelist_details = (object)[
                        $value => 1,
                        'total' => 1,
                    ];
                }else {
                    $truelist_details = (object)[
                        'total' => 1,
                    ];
                }
            }else {
                $truelist_details = (object)[
                    'total' => 0,
                ];
            }
            $locationlock = ($typeReport == "locationlock")?1:0;
            $locationlockfailed = ($typeReport == "locationlockfailed")?1:0;
            $leadspeek_type = ($leadspeekType != "")?$leadspeekType:"local";
            $serveclient = ($typeReport == "serveclient")?1:0;
            $notserve = ($typeReport == "notserve")?1:0;
            $bigbdmhems = ($typeReport == 'bigbdmhems')?$value:0;
            $bigbdmtotalleads = ($typeReport == 'bigbdmtotalleads')?$value:0;
            $bigbdmremainingleads = ($typeReport == 'bigbdmremainingleads')?$value:0;
            $getleadfailed = ($typeReport == 'getleadfailed')?1:0;
            $getleadfailed_bigbdmmd5 = ($typeReport == 'getleadfailed_bigbdmmd5')?1:0;
            $getleadfailed_gettowerdata = ($typeReport == 'getleadfailed_gettowerdata')?1:0;
            $getleadfailed_bigbdmpii = ($typeReport == 'getleadfailed_bigbdmpii')?1:0;
            $simplifi_impressions = ($typeReport == 'simplifi_impressions')?$value:0;

            $reportAnalytic = ReportAnalytic::create([
                'date' => ($typeReport == 'simplifi_impressions') ? $date : date('Y-m-d'),
                'leadspeek_api_id'=>($leadspeekType != 'clean_id')?$leadspeek_api_id:'',
                'clean_file_id'=>($leadspeekType == 'clean_id')?$leadspeek_api_id:null,
                'pixelfire' => $pixelfire,
                'renderingpixelfire' => $renderingpixelfire,
                'towerpostal' => $towerpostal,
                'endatoenrichment' => $endatoenrichment,
                'toweremail' => $toweremail,
                'zerobounce' => $zerobounce,
                'zerobouncefailed' => $zerobouncefailed,
                'zerobounce_details' => json_encode($zerobounce_details),
                'truelist_details' => json_encode($truelist_details),
                'locationlock' => $locationlock,
                'locationlockfailed' => $locationlockfailed,
                'leadspeek_type' => $leadspeek_type,
                'serveclient' => $serveclient,
                'notserve' => $notserve,
                'bigbdmhems' => $bigbdmhems,
                'bigbdmtotalleads' => $bigbdmtotalleads,
                'bigbdmremainingleads' => $bigbdmremainingleads,
                'getleadfailed' => $getleadfailed,
                'getleadfailed_bigbdmmd5' => $getleadfailed_bigbdmmd5,
                'getleadfailed_gettowerdata' => $getleadfailed_gettowerdata,
                'getleadfailed_bigbdmpii' => $getleadfailed_bigbdmpii,
                'simplifi_impressions' => $simplifi_impressions,
            ]);
        }
    }
    
    public function getClientCapType($company_root_id)
    {
        $clientTypeLead = [
            'type' => '',
            'value' => ''
        ];

        $rootsetting = $this->getcompanysetting($company_root_id, 'rootsetting');

        if(!empty($rootsetting->clientcaplead)) {
            $clientTypeLead['type'] = 'clientcaplead';
            $clientTypeLead['value'] = $rootsetting->clientcaplead;
        } 
        if(!empty($rootsetting->clientcapleadpercentage)) {
            $clientTypeLead['type'] = 'clientcapleadpercentage';
            $clientTypeLead['value'] = $rootsetting->clientcapleadpercentage;
        }

        return $clientTypeLead;
    }

    Public function __construct()
    {
        date_default_timezone_set('America/Chicago');
    }

    public function check_connected_account($companyParentID,$idsys = "") {
        $accConID = '';
        $confAppSysID = config('services.application.systemid');
        if ($idsys != "") {
            $confAppSysID = $idsys;
        }

        if ($companyParentID != '' && $companyParentID != $confAppSysID) {
            $usrchk = User::select('user_type')
                            ->where('company_id','=',$companyParentID)
                            ->where('company_parent','=',$confAppSysID)
                            ->where('isAdmin','=','T')
                            ->where('active','=','T')
                            ->where('user_type','=','userdownline')
                            ->get();

            if (count($usrchk) > 0) {
                if ($usrchk[0]['user_type'] == 'userdownline') {
                    $companyStripe = CompanyStripe::select('acc_connect_id')
                                            ->where('company_id','=',$companyParentID)
                                            ->where('status_acc','=','completed')
                                            ->get();

                    if (count($companyStripe) > 0) {
                        $accConID = $companyStripe[0]['acc_connect_id'];
                    }
                }
            }

        }
        return $accConID;
}

    public function send_notif_stripeerror($title,$content,$idsys = "", $isQueue = false) {
        $details = [
            'title' => $title,
            'content'  => $content,
        ];
    
        $attachement = array();
    
        $from = [
            'address' => 'noreply@exactmatchmarketing.com',
            'name' => 'Charge Error',
            'replyto' => 'support@exactmatchmarketing.com',
        ];
    
        //$CompanyID = config('services.application.systemid');
        $confAppSysID = config('services.application.systemid');
        if ($idsys != "") {
            $confAppSysID = $idsys;
        }
    
        $rootAdmin = User::select('name','email')->where('company_id','=',$confAppSysID)->where('active','T')->whereRaw("user_type IN ('user','userdownline')")->where('isAdmin','=','T')->get();
    
        $adminEmail = array();
        foreach($rootAdmin as $ad) {
            array_push($adminEmail,$ad['email']);
        }
    
        //$this->send_email($adminEmail,$title,$details,$attachement,'emails.customemail',$from,'');
        $this->send_email(array('serverlogs@sitesettingsapi.com'),$title,$details,$attachement,'emails.customemail',$from,'',$isQueue);
    }
    
    /** FOR STRIPE THINGS */
    public function transfer_commission_sales($companyParentID,$platformfee,$_leadspeek_api_id = "",$startdate = "0000-00-00 00:00:00",$enddate = "0000-00-00 00:00:00",$stripeseckey = "",$ongoingleads = "",$cleanProfit = "",$dataCustomCommissionSales = [],$transfer_group = "",$source_transaction = "") 
    {
        // info("public function " . __FUNCTION__, ['companyParentID' => $companyParentID, 'platformfee' => $platformfee, '_leadspeek_api_id' => $_leadspeek_api_id, 'startdate' => $startdate, 'enddate' => $enddate, 'stripeseckey' => $stripeseckey, 'ongoingleads' => $ongoingleads, 'cleanProfit' => $cleanProfit, 'dataCustomCommissionSales' => $dataCustomCommissionSales, 'transfer_group' => $transfer_group, 'source_transaction' => $source_transaction]);
        $stripe = new StripeClient([
            'api_key' => $stripeseckey,
            'stripe_version' => '2020-08-27'
        ]);

        /* VARIABLES */
        $srID = 0;
        $aeID = 0;
        $arID = 0;
        $srFee = 0;
        $aeFee = 0;
        $arFee = 0;
        $srTransferID = '';
        $aeTransferID = '';
        $arTransferID = '';
        /* VARIABLES */

        /* CHECK TYPE CHARGE IN COMMISSION SALES */
        // info(['dataCustomCommissionSales' => $dataCustomCommissionSales]);
        $chargeType = isset($dataCustomCommissionSales['type'])?$dataCustomCommissionSales['type']:''; // untuk mengetahui transfer commission sale itu dari invoice atau topup
        $chargeFrom = (isset($dataCustomCommissionSales['from']) && !empty($dataCustomCommissionSales['from']))?$dataCustomCommissionSales['from']:'topup'; // untuk mengetahui transfer commission sale itu dari minimum_spend atau wallet
        $platformPriceArray = []; // jika createInvoice, data ini untuk menampung seluruh platform_price_lead
        $platformPriceTopup = 0; // jika topup, data ini untuk menampung platformPrice topup
        $totalLeadTopup = 0; // jika topup, data ini untuk menampung totalLead topup

        if($chargeType == 'invoice') 
        {
            $_lp_user_id = isset($dataCustomCommissionSales['_lp_user_id'])?$dataCustomCommissionSales['_lp_user_id']:'';
            $_company_id = isset($dataCustomCommissionSales['_company_id'])?$dataCustomCommissionSales['_company_id']:'';
            $_user_id = isset($dataCustomCommissionSales['_user_id'])?$dataCustomCommissionSales['_user_id']:'';
            $_leadspeek_api_id = isset($dataCustomCommissionSales['_leadspeek_api_id'])?$dataCustomCommissionSales['_leadspeek_api_id']:'';
            $startBillingDate = isset($dataCustomCommissionSales['startBillingDate'])?$dataCustomCommissionSales['startBillingDate']:'';
            $endBillingDate = isset($dataCustomCommissionSales['endBillingDate'])?$dataCustomCommissionSales['endBillingDate']:'';
            
            $platformPriceArray = LeadspeekReport::where('lp_user_id','=',$_lp_user_id)
                                                ->where('company_id','=',$_company_id)
                                                ->where('user_id','=',$_user_id)
                                                ->where('leadspeek_api_id','=',$_leadspeek_api_id)
                                                ->where('active','=','T')
                                                //->whereBetween(DB::raw('DATE_FORMAT(clickdate,"%Y-%m-%d")'),[$startBillingDate,$endBillingDate])
                                                ->where(DB::raw('DATE_FORMAT(clickdate,"%Y%m%d%H%i%s")'),'>=',$startBillingDate)
                                                ->where(DB::raw('DATE_FORMAT(clickdate,"%Y%m%d%H%i%s")'),'<=',$endBillingDate)
                                                ->pluck('platform_price_lead');
            // info('persiapan data untuk custom_commission_type, type pembayaran createInvoice', ['lp_user_id' => $_lp_user_id,'_company_id' => $_company_id,'_user_id' => $_user_id,'_leadspeek_api_id' => $_leadspeek_api_id,'lp_user_id' => $_lp_user_id,'platformPriceArray' => $platformPriceArray]);
        } 
        else if($chargeType == 'topup') 
        {
            $platformPriceTopup = isset($dataCustomCommissionSales['platform_price_lead'])?$dataCustomCommissionSales['platform_price_lead']:0;
            $totalLeadTopup = isset($dataCustomCommissionSales['total_lead_topup'])?$dataCustomCommissionSales['total_lead_topup']:0;
            // info('persiapan data untuk custom_commission_type, type pembayaran topup', ['platformPriceTopup' => $platformPriceTopup,'totalLeadTopup' => $totalLeadTopup,]);
        }
        /* CHECK TYPE CHARGE IN COMMISSION SALES */

        /** CHECK IF THERE ARE SALES AND ACCOUNT EXECUTIVE */
        $salt = substr(hash('sha256', env('APP_KEY')), 0, 16);
        $saleslist = CompanySale::select(
                                    DB::raw("CONVERT(AES_DECRYPT(FROM_bASE64(users.custom_commission), '" . $salt . "') USING utf8mb4) as `custom_commission`"),
                                    DB::raw("CONVERT(AES_DECRYPT(FROM_bASE64(users.custom_commission_fixed), '" . $salt . "') USING utf8mb4) as `custom_commission_fixed`"),
                                    'users.custom_commission_enabled','users.custom_commission_type','company_sales.id','company_sales.sales_id','company_sales.sales_title','users.company_root_id',
                                    DB::raw("CONVERT(AES_DECRYPT(FROM_bASE64(`company_name`), '" . $salt . "') USING utf8mb4) as `company_name`"),
                                    DB::raw("CONVERT(AES_DECRYPT(FROM_bASE64(users.acc_connect_id), '" . $salt . "') USING utf8mb4) as `accconnectid`"),
                                    DB::raw("CONVERT(AES_DECRYPT(FROM_bASE64(users.name), '" . $salt . "') USING utf8mb4) as `name`"),
                                    DB::raw("CONVERT(AES_DECRYPT(FROM_bASE64(users.email), '" . $salt . "') USING utf8mb4) as `email`"))
                                 ->join('users','company_sales.sales_id','=','users.id')
                                 ->join('companies','company_sales.company_id','=','companies.id')
                                 ->where('company_sales.company_id','=',$companyParentID)
                                 ->where('users.active','=','T')
                                 ->where('users.user_type','=','sales')
                                 ->where('users.status_acc','=','completed')
                                 ->get();
        
        /** CHECK DEFAULT SALES COMMISSION */
        $AgencyPercentageCommission = 0.05;
        if (count($saleslist) > 0)
        {
            $rootAgencyPercentageCommission = $this->getcompanysetting($saleslist[0]['company_root_id'],'rootsetting');
            if ($rootAgencyPercentageCommission != '') 
            {
                if(isset($rootAgencyPercentageCommission->defaultagencypercentagecommission) && $rootAgencyPercentageCommission->defaultagencypercentagecommission != "") 
                {
                    $AgencyPercentageCommission = $rootAgencyPercentageCommission->defaultagencypercentagecommission;
                }
            }
        }
        // info(['AgencyPercentageCommission' => $AgencyPercentageCommission]);
        /** CHECK DEFAULT SALES COMMISSION */

        $salesfee = ($platformfee * (float) $AgencyPercentageCommission);
        //$salesfee = ($cleanProfit != "")?($cleanProfit * (float) $AgencyPercentageCommission):$salesfee;
        $salesfee = number_format($salesfee,2,'.','');

        // info('hitung salesfee pertama kali', ['platformfee' => $platformfee,'AgencyPercentageCommission' => $AgencyPercentageCommission,'salesfee' => $salesfee]);
        if (count($saleslist) > 0 && $platformfee > 0 && $salesfee > 0) 
        {
            /** GET OTHER DETAILS */
            $_campaign_name = "";
            $_client_name = "";
            $_leadspeek_type = "";

            $campaigndetails = LeadspeekUser::select('leadspeek_users.campaign_name','companies.company_name','leadspeek_users.leadspeek_type')
                                            ->join('users','leadspeek_users.user_id','=','users.id')
                                            ->join('companies','users.company_id','=','companies.id')
                                            ->where('leadspeek_users.leadspeek_api_id','=',$_leadspeek_api_id)
                                            ->get();
            if (count($campaigndetails) > 0) 
            {
                $_campaign_name = $campaigndetails[0]['campaign_name'];
                $_client_name = $campaigndetails[0]['company_name'];
                $_leadspeek_type = $campaigndetails[0]['leadspeek_type'];
            }
            if (in_array($chargeType, ['wallet', 'clean_id']))
            {
                $_leadspeek_type = $chargeType;
            }
            // info('masuk ke if untuk transfer sales', ['_leadspeek_type' => $_leadspeek_type]);
            /** GET OTHER DETAILS */

            foreach($saleslist as $sale) 
            {
                $overrideCommission = array();
                $salesfee = ($platformfee * (float) $AgencyPercentageCommission);
                $salesfee = number_format($salesfee,2,'.','');

                /** OVERRIDE THE COMMISSION IF ENABLED */
                if ($sale['custom_commission_enabled'] == 'T' && $ongoingleads != "") 
                {
                    if($sale['custom_commission_type'] == 'fixed') 
                    {
                        $chkCommissionOverride = json_decode($sale['custom_commission_fixed']);
                        // info('pakai cara custom_commission_fixed', ['chkCommissionOverride' => $chkCommissionOverride]);
                        if($_leadspeek_type == 'local') 
                        {
                            // sales representative siteid
                            if(isset($chkCommissionOverride->sr->siteid) && $chkCommissionOverride->sr->siteid > 0 && $chkCommissionOverride->sr->siteid != '') 
                            {
                                $calculateCommission_srSiteID = 0;
                                if($chargeType == 'invoice') 
                                {
                                    foreach($platformPriceArray as $item) 
                                    {
                                        if($item > $chkCommissionOverride->sr->siteid) 
                                        {
                                            $calculateCommission_srSiteID += ($item - $chkCommissionOverride->sr->siteid);    
                                        }
                                    }
                                } 
                                else if($chargeType == 'topup' && $platformPriceTopup > $chkCommissionOverride->sr->siteid) 
                                {
                                    $calculateCommission_srSiteID = ($platformPriceTopup - $chkCommissionOverride->sr->siteid) * $totalLeadTopup;
                                }
                                $overrideCommission['srSiteID'] = $calculateCommission_srSiteID;
                                // info("overrideCommission srSiteID if", ['chargeType' => $chargeType,'srSiteID' => $overrideCommission['srSiteID'] ?? "masih kosong",'chkCommissionOverride->sr->siteid' => $chkCommissionOverride->sr->siteid,'platformPriceTopup' => $platformPriceTopup,'totalLeadTopup' => $totalLeadTopup]);
                            } 
                            else 
                            {
                                $overrideCommission['srSiteID'] = $salesfee;
                                // info("overrideCommission srSiteID else", ['srSiteID' => $overrideCommission['srSiteID'] ?? "masih kosong",'salesfee' => $salesfee]);
                            }
                            // sales representative siteid

                            // account executive siteid
                            if(isset($chkCommissionOverride->ae->siteid) && $chkCommissionOverride->ae->siteid > 0 && $chkCommissionOverride->ae->siteid != '') 
                            {
                                $calculateCommission_aeSiteID = 0;
                                if($chargeType == 'invoice') 
                                {
                                    foreach($platformPriceArray as $item) 
                                    {
                                        if($item > $chkCommissionOverride->ae->siteid) 
                                        {
                                            $calculateCommission_aeSiteID += ($item - $chkCommissionOverride->ae->siteid);    
                                        }
                                    }
                                } 
                                else if($chargeType == 'topup' && $platformPriceTopup > $chkCommissionOverride->ae->siteid) 
                                {
                                    $calculateCommission_aeSiteID = ($platformPriceTopup - $chkCommissionOverride->ae->siteid) * $totalLeadTopup;
                                }
                                $overrideCommission['aeSiteID'] = $calculateCommission_aeSiteID;
                                // info("overrideCommission aeSiteID if", ['chargeType' => $chargeType,'aeSiteID' => $overrideCommission['srSiteID'] ?? "masih kosong",'chkCommissionOverride->ae->siteid' => $chkCommissionOverride->ae->siteid,'platformPriceTopup' => $platformPriceTopup,'totalLeadTopup' => $totalLeadTopup,]);
                            } 
                            else 
                            {
                                $overrideCommission['aeSiteID'] = $salesfee;
                                // info("overrideCommission aeSiteID else", ['aeSiteID' => $overrideCommission['aeSiteID'] ?? "masih kosong",'salesfee' => $salesfee]);
                            }
                            // account executive siteid

                            // account referral siteid, ini yang akan dipakai
                            if(isset($chkCommissionOverride->ar->siteid) && $chkCommissionOverride->ar->siteid > 0 && $chkCommissionOverride->ar->siteid != '') 
                            {
                                $calculateCommission_arSiteID  = 0;
                                if($chargeType == 'invoice') 
                                {
                                    foreach($platformPriceArray as $item) 
                                    {
                                        if($item > $chkCommissionOverride->ar->siteid) 
                                        {
                                            $calculateCommission_arSiteID += ($item - $chkCommissionOverride->ar->siteid);
                                        }
                                    }  
                                } 
                                else if($chargeType == 'topup' && $platformPriceTopup > $chkCommissionOverride->ar->siteid) 
                                {
                                    $calculateCommission_arSiteID = ($platformPriceTopup - $chkCommissionOverride->ar->siteid) * $totalLeadTopup;
                                }
                                $overrideCommission['arSiteID'] = $calculateCommission_arSiteID;
                                // info("overrideCommission arSiteID if", ['chargeType' => $chargeType,'arSiteID' => isset($overrideCommission['arSiteID'])?$overrideCommission['arSiteID']:'','chkCommissionOverride->ar->siteid' => $chkCommissionOverride->ar->siteid,'platformPriceTopup' => $platformPriceTopup,'totalLeadTopup' => $totalLeadTopup,]);
                            } 
                            else 
                            {
                                $overrideCommission['arSiteID'] = $salesfee;
                                // info("overrideCommission arSiteID else", ['arSiteID' => $overrideCommission['arSiteID'] ?? "masih kosong",'salesfee' => $salesfee]);
                            }
                            // account referral siteid, ini yang akan dipakai
                        } 
                        else if($_leadspeek_type == 'locator') 
                        {
                            // sales representative searchid
                            if(isset($chkCommissionOverride->sr->searchid) && $chkCommissionOverride->sr->searchid > 0 && $chkCommissionOverride->sr->searchid != '') 
                            {
                                $calculateCommission_srSearchID = 0;
                                if($chargeType == 'invoice') 
                                {
                                    foreach($platformPriceArray as $item) 
                                    {
                                        if($item > $chkCommissionOverride->sr->searchid) 
                                        {
                                            $calculateCommission_srSearchID += ($item - $chkCommissionOverride->sr->searchid);
                                        }
                                    }
                                } 
                                else if($chargeType == 'topup' && $platformPriceTopup > $chkCommissionOverride->sr->searchid) 
                                {
                                    $calculateCommission_srSearchID = ($platformPriceTopup - $chkCommissionOverride->sr->searchid) * $totalLeadTopup;
                                }
                                $overrideCommission['srSearchID'] = $calculateCommission_srSearchID;
                                // info("overrideCommission srSearchID if", ['chargeType' => $chargeType,'srSearchID' => $overrideCommission['srSearchID'] ?? "masih kosong",'chkCommissionOverride->sr->searchid' => $chkCommissionOverride->sr->searchid,'platformPriceTopup' => $platformPriceTopup,'totalLeadTopup' => $totalLeadTopup,]);
                            } 
                            else 
                            {
                                $overrideCommission['srSearchID'] = $salesfee;
                                // info("overrideCommission srSearchID else", ['srSearchID' => $overrideCommission['srSearchID'] ?? "masih kosong",'salesfee' => $salesfee]);
                            }
                            // sales representative searchid

                            // account executive searchid
                            if(isset($chkCommissionOverride->ae->searchid) && $chkCommissionOverride->ae->searchid > 0 && $chkCommissionOverride->ae->searchid != '') 
                            {
                                $calculateCommission_aeSearchID = 0;
                                if($chargeType == 'invoice') 
                                {
                                    foreach($platformPriceArray as $item) 
                                    {
                                        if($item > $chkCommissionOverride->ae->searchid) 
                                        {
                                            $calculateCommission_aeSearchID += ($item - $chkCommissionOverride->ae->searchid);
                                        }
                                    }
                                } 
                                else if($chargeType == 'topup' && $platformPriceTopup > $chkCommissionOverride->ae->searchid) 
                                {
                                    $calculateCommission_aeSearchID = ($platformPriceTopup - $chkCommissionOverride->ae->searchid) * $totalLeadTopup;
                                }
                                $overrideCommission['aeSearchID'] = $calculateCommission_aeSearchID;
                                // info("overrideCommission aeSearchID if", ['chargeType' => $chargeType,'aeSearchID' => $overrideCommission['aeSearchID'] ?? "masih kosong",'chkCommissionOverride->ae->searchid' => $chkCommissionOverride->ae->searchid,'platformPriceTopup' => $platformPriceTopup,'totalLeadTopup' => $totalLeadTopup,]);
                            } 
                            else 
                            {
                                $overrideCommission['aeSearchID'] = $salesfee;
                                // info("overrideCommission aeSearchID else", ['aeSearchID' => $overrideCommission['aeSearchID'] ?? "masih kosong",'salesfee' => $salesfee]);
                            }
                            // account executive searchid

                            // account reveral searchid, ini yang akan dipakai
                            if(isset($chkCommissionOverride->ar->searchid) && $chkCommissionOverride->ar->searchid > 0 && $chkCommissionOverride->ar->searchid != '') 
                            {
                                $calculateCommission_arSearchID = 0;
                                if($chargeType == 'invoice') 
                                {
                                    foreach($platformPriceArray as $item) 
                                    {
                                        if($item > $chkCommissionOverride->ar->searchid) 
                                        {
                                            $calculateCommission_arSearchID += ($item - $chkCommissionOverride->ar->searchid);
                                        }
                                    }
                                } 
                                else if($chargeType == 'topup' && $platformPriceTopup > $chkCommissionOverride->ar->searchid) 
                                {
                                    $calculateCommission_arSearchID = ($platformPriceTopup - $chkCommissionOverride->ar->searchid) * $totalLeadTopup;
                                }
                                $overrideCommission['arSearchID'] = $calculateCommission_arSearchID;
                                // info("overrideCommission arSearchID if", ['chargeType' => $chargeType,'arSearchID' => $overrideCommission['arSearchID'] ?? "masih kosong",'chkCommissionOverride->ar->searchid' => $chkCommissionOverride->ar->searchid,'platformPriceTopup' => $platformPriceTopup,'totalLeadTopup' => $totalLeadTopup,]);
                            } 
                            else 
                            {
                                $overrideCommission['arSearchID'] = $salesfee;
                                // info("overrideCommission arSearchID else", ['arSearchID' => $overrideCommission['arSearchID'] ?? "masih kosong",'salesfee' => $salesfee]);
                            }
                            // account executive searchid, ini yang akan dipakai
                        } 
                        else if($_leadspeek_type == 'enhance') 
                        {
                            // sales representative enhance
                            if(isset($chkCommissionOverride->sr->enhanceid) && $chkCommissionOverride->sr->enhanceid > 0 && $chkCommissionOverride->sr->enhanceid != '') 
                            {
                                $calculateCommission_srEnhanceID = 0;
                                if($chargeType == 'invoice') 
                                {
                                    foreach($platformPriceArray as $item) 
                                    {
                                        if($item > $chkCommissionOverride->sr->enhanceid) 
                                        {
                                            $calculateCommission_srEnhanceID += ($item - $chkCommissionOverride->sr->enhanceid);
                                        }
                                    }
                                } 
                                else if($chargeType == 'topup' && $platformPriceTopup > $chkCommissionOverride->sr->enhanceid) 
                                {
                                    $calculateCommission_srEnhanceID = ($platformPriceTopup - $chkCommissionOverride->sr->enhanceid) * $totalLeadTopup;
                                } 
                                $overrideCommission['srEnhanceID'] = $calculateCommission_srEnhanceID;
                                // info("overrideCommission srEnhanceID if", ['chargeType' => $chargeType,'srEnhanceID' => $overrideCommission['srEnhanceID'] ?? "masih kosong",'chkCommissionOverride->sr->enhanceid' => $chkCommissionOverride->sr->enhanceid,'platformPriceTopup' => $platformPriceTopup,'totalLeadTopup' => $totalLeadTopup,]);
                            } 
                            else 
                            {
                                $overrideCommission['srEnhanceID'] = $salesfee;
                                // info("overrideCommission srEnhanceID else", ['arSearchID' => $overrideCommission['srEnhanceID'] ?? "masih kosong",'salesfee' => $salesfee]);
                            }
                            // sales representative enhance

                            // account executive enhance
                            if(isset($chkCommissionOverride->ae->enhanceid) && $chkCommissionOverride->ae->enhanceid > 0 && $chkCommissionOverride->ae->enhanceid != '') 
                            {
                                $calculateCommission_aeEnhanceID = 0;
                                if($chargeType == 'invoice') 
                                {
                                    foreach($platformPriceArray as $item) 
                                    {
                                        if($item > $chkCommissionOverride->ae->enhanceid) 
                                        {
                                            $calculateCommission_aeEnhanceID += ($item - $chkCommissionOverride->ae->enhanceid);
                                        }
                                    }
                                } 
                                else if($chargeType == 'topup' && $platformPriceTopup > $chkCommissionOverride->ae->enhanceid) 
                                {
                                    $calculateCommission_aeEnhanceID = ($platformPriceTopup - $chkCommissionOverride->ae->enhanceid) * $totalLeadTopup;
                                }
                                $overrideCommission['aeEnhanceID'] = $calculateCommission_aeEnhanceID;
                                // info("overrideCommission aeEnhanceID if", ['chargeType' => $chargeType,'aeEnhanceID' => $overrideCommission['aeEnhanceID'] ?? "masih kosong",'chkCommissionOverride->ae->enhanceid' => $chkCommissionOverride->ae->enhanceid,'platformPriceTopup' => $platformPriceTopup,'totalLeadTopup' => $totalLeadTopup,]);
                            } 
                            else 
                            {
                                $overrideCommission['aeEnhanceID'] = $salesfee;
                                // info("overrideCommission aeEnhanceID else", ['aeEnhanceID' => $overrideCommission['aeEnhanceID'] ?? "masih kosong",'salesfee' => $salesfee]);
                            }
                            // account executive enhance

                            // account reveral enhance
                            if(isset($chkCommissionOverride->ar->enhanceid) && $chkCommissionOverride->ar->enhanceid > 0 && $chkCommissionOverride->ar->enhanceid != '') 
                            {
                                $calculateCommission_arEnhanceID = 0;
                                if($chargeType == 'invoice') 
                                {
                                    foreach($platformPriceArray as $item) 
                                    {
                                        if($item > $chkCommissionOverride->ar->enhanceid) 
                                        {
                                            $calculateCommission_arEnhanceID += ($item - $chkCommissionOverride->ar->enhanceid);
                                        }
                                    }
                                } 
                                else if($chargeType == 'topup' && $platformPriceTopup > $chkCommissionOverride->ar->enhanceid) 
                                {
                                    $calculateCommission_arEnhanceID = ($platformPriceTopup - $chkCommissionOverride->ar->enhanceid) * $totalLeadTopup;;
                                }
                                $overrideCommission['arEnhanceID'] = $calculateCommission_arEnhanceID;
                                // info("overrideCommission arEnhanceID if", ['chargeType' => $chargeType,'arEnhanceID' => $overrideCommission['arEnhanceID'] ?? "masih kosong",'chkCommissionOverride->ar->enhanceid' => $chkCommissionOverride->ar->enhanceid,'platformPriceTopup' => $platformPriceTopup,'totalLeadTopup' => $totalLeadTopup,]);
                            } 
                            else 
                            {
                                $overrideCommission['arEnhanceID'] = $salesfee;
                                // info("overrideCommission arEnhanceID else", ['arEnhanceID' => $overrideCommission['arEnhanceID'] ?? "masih kosong",'salesfee' => $salesfee]);
                            }
                            // account executive enhance
                        }
                        else if($_leadspeek_type == 'b2b') 
                        {
                            // sales representative b2b
                            if(isset($chkCommissionOverride->sr->b2bid) && $chkCommissionOverride->sr->b2bid > 0 && $chkCommissionOverride->sr->b2bid != '') 
                            {
                                $calculateCommission_srB2bID = 0;
                                if($chargeType == 'invoice') 
                                {
                                    foreach($platformPriceArray as $item) 
                                    {
                                        if($item > $chkCommissionOverride->sr->b2bid) 
                                        {
                                            $calculateCommission_srB2bID += ($item - $chkCommissionOverride->sr->b2bid);
                                        }
                                    }
                                } 
                                else if($chargeType == 'topup' && $platformPriceTopup > $chkCommissionOverride->sr->b2bid) 
                                {
                                    $calculateCommission_srB2bID = ($platformPriceTopup - $chkCommissionOverride->sr->b2bid) * $totalLeadTopup;
                                } 
                                $overrideCommission['srB2bID'] = $calculateCommission_srB2bID;
                                // info("overrideCommission srB2bID if end", ['chargeType' => $chargeType,'srB2bID' => $overrideCommission['srB2bID'] ?? "masih kosong",'chkCommissionOverride->sr->b2bid' => $chkCommissionOverride->sr->b2bid,'platformPriceTopup' => $platformPriceTopup,'totalLeadTopup' => $totalLeadTopup,]);
                            } 
                            else 
                            {
                                $overrideCommission['srB2bID'] = $salesfee;
                                // info("overrideCommission srEnhanceID else", ['srB2bID' => $overrideCommission['srB2bID'] ?? "masih kosong",'salesfee' => $salesfee]);
                            }
                            // sales representative b2b

                            // account executive b2b
                            if(isset($chkCommissionOverride->ae->b2bid) && $chkCommissionOverride->ae->b2bid > 0 && $chkCommissionOverride->ae->b2bid != '') 
                            {
                                $calculateCommission_aeB2bID = 0;
                                if($chargeType == 'invoice') 
                                {
                                    foreach($platformPriceArray as $item) 
                                    {
                                        if($item > $chkCommissionOverride->ae->b2bid) 
                                        {
                                            $calculateCommission_aeB2bID += ($item - $chkCommissionOverride->ae->b2bid);
                                        }
                                    }
                                } 
                                else if($chargeType == 'topup' && $platformPriceTopup > $chkCommissionOverride->ae->b2bid) 
                                {
                                    $calculateCommission_aeB2bID = ($platformPriceTopup - $chkCommissionOverride->ae->b2bid) * $totalLeadTopup;
                                }
                                $overrideCommission['aeB2bID'] = $calculateCommission_aeB2bID;
                                // info("overrideCommission aeB2bID if end", ['chargeType' => $chargeType,'aeB2bID' => $overrideCommission['aeB2bID'] ?? "masih kosong",'chkCommissionOverride->ae->b2bid' => $chkCommissionOverride->ae->b2bid,'platformPriceTopup' => $platformPriceTopup,'totalLeadTopup' => $totalLeadTopup,]);
                            } 
                            else 
                            {
                                $overrideCommission['aeB2bID'] = $salesfee;
                                // info("overrideCommission aeB2bID else", ['aeB2bID' => $overrideCommission['aeB2bID'] ?? "masih kosong",'salesfee' => $salesfee]);
                            }
                            // account executive b2b

                            // account reveral b2b
                            if(isset($chkCommissionOverride->ar->b2bid) && $chkCommissionOverride->ar->b2bid > 0 && $chkCommissionOverride->ar->b2bid != '') 
                            {
                                $calculateCommission_arB2bID = 0;
                                if($chargeType == 'invoice') 
                                {
                                    foreach($platformPriceArray as $item) 
                                    {
                                        if($item > $chkCommissionOverride->ar->b2bid) 
                                        {
                                            $calculateCommission_arB2bID += ($item - $chkCommissionOverride->ar->b2bid);
                                        }
                                    }
                                } 
                                else if($chargeType == 'topup' && $platformPriceTopup > $chkCommissionOverride->ar->b2bid) 
                                {
                                    $calculateCommission_arB2bID = ($platformPriceTopup - $chkCommissionOverride->ar->b2bid) * $totalLeadTopup;;
                                }
                                $overrideCommission['arB2bID'] = $calculateCommission_arB2bID;
                                // info("overrideCommission arB2bID if end", ['chargeType' => $chargeType,'arB2bID' => $overrideCommission['arB2bID'] ?? "masih kosong",'chkCommissionOverride->ar->b2bid' => $chkCommissionOverride->ar->b2bid,'platformPriceTopup' => $platformPriceTopup,'totalLeadTopup' => $totalLeadTopup,]);
                            } 
                            else
                            {
                                $overrideCommission['arB2bID'] = $salesfee;
                                // info("overrideCommission arB2bID else", ['arB2bID' => $overrideCommission['arB2bID'] ?? "masih kosong",'salesfee' => $salesfee]);
                            }
                            // account executive b2b
                        }
                        else if ($_leadspeek_type == 'wallet') // for data wallet, 5%
                        {
                            $overrideCommission['srWalletID'] = $platformfee * (float) 0.05;
                            $overrideCommission['aeWalletID'] = $platformfee * (float) 0.05;
                            $overrideCommission['arWalletID'] = $platformfee * (float) 0.05;
                            // info('fixed', ['_leadspeek_type' => $_leadspeek_type,'srWalletID' => $overrideCommission['srWalletID'],'aeWalletID' => $overrideCommission['aeWalletID'],'arWalletID' => $overrideCommission['arWalletID']]);
                        }
                    } 
                    else 
                    {
                        $chkCommissionOverride = json_decode($sale['custom_commission']);
                        // info('pakai cara custom_commission', ['chkCommissionOverride' => $chkCommissionOverride]);
                        if ($_leadspeek_type == 'local') 
                        {
                            $overrideCommission['srSiteID'] = (isset($chkCommissionOverride->sr->siteid) && $chkCommissionOverride->sr->siteid > 0)?$platformfee * (float) $chkCommissionOverride->sr->siteid:$salesfee;
                            $overrideCommission['aeSiteID'] = (isset($chkCommissionOverride->ae->siteid) && $chkCommissionOverride->ae->siteid > 0)?$platformfee * (float) $chkCommissionOverride->ae->siteid:$salesfee;
                            $overrideCommission['arSiteID'] = (isset($chkCommissionOverride->ar->siteid) && $chkCommissionOverride->ar->siteid > 0)?$platformfee * (float) $chkCommissionOverride->ar->siteid:$salesfee;
                            // info('', ['_leadspeek_type' => $_leadspeek_type,'srSiteID' => $overrideCommission['srSiteID'],'aeSiteID' => $overrideCommission['aeSiteID'],'arSiteID' => $overrideCommission['arSiteID'],]);
                        }
                        else if ($_leadspeek_type == 'locator') 
                        {
                            $overrideCommission['srSearchID'] = (isset($chkCommissionOverride->sr->searchid) && $chkCommissionOverride->sr->searchid > 0)?$platformfee * (float) $chkCommissionOverride->sr->searchid:$salesfee;
                            $overrideCommission['aeSearchID'] = (isset($chkCommissionOverride->ae->searchid) && $chkCommissionOverride->ae->searchid > 0)?$platformfee * (float) $chkCommissionOverride->ae->searchid:$salesfee;
                            $overrideCommission['arSearchID'] = (isset($chkCommissionOverride->ar->searchid) && $chkCommissionOverride->ar->searchid > 0)?$platformfee * (float) $chkCommissionOverride->ar->searchid:$salesfee;
                            // info('', ['_leadspeek_type' => $_leadspeek_type,'srSearchID' => $overrideCommission['srSearchID'],'aeSearchID' => $overrideCommission['aeSearchID'],'arSearchID' => $overrideCommission['arSearchID'],]);
                        }
                        else if ($_leadspeek_type == 'enhance') 
                        {
                            $overrideCommission['srEnhanceID'] = (isset($chkCommissionOverride->sr->enhanceid) && $chkCommissionOverride->sr->enhanceid > 0)?$platformfee * (float) $chkCommissionOverride->sr->enhanceid:$salesfee;
                            $overrideCommission['aeEnhanceID'] = (isset($chkCommissionOverride->ae->enhanceid) && $chkCommissionOverride->ae->enhanceid > 0)?$platformfee * (float) $chkCommissionOverride->ae->enhanceid:$salesfee;
                            $overrideCommission['arEnhanceID'] = (isset($chkCommissionOverride->ar->enhanceid) && $chkCommissionOverride->ar->enhanceid > 0)?$platformfee * (float) $chkCommissionOverride->ar->enhanceid:$salesfee;
                            // info('', ['_leadspeek_type' => $_leadspeek_type,'srEnhanceID' => $overrideCommission['srEnhanceID'],'aeEnhanceID' => $overrideCommission['aeEnhanceID'],'aeEnhanceID' => $overrideCommission['aeEnhanceID'],]);
                        }
                        else if ($_leadspeek_type == 'b2b') 
                        {
                            $overrideCommission['srB2bID'] = (isset($chkCommissionOverride->sr->b2bid) && $chkCommissionOverride->sr->b2bid > 0)?$platformfee * (float) $chkCommissionOverride->sr->b2bid:$salesfee;
                            $overrideCommission['aeB2bID'] = (isset($chkCommissionOverride->ae->b2bid) && $chkCommissionOverride->ae->b2bid > 0)?$platformfee * (float) $chkCommissionOverride->ae->b2bid:$salesfee;
                            $overrideCommission['arB2bID'] = (isset($chkCommissionOverride->ar->b2bid) && $chkCommissionOverride->ar->b2bid > 0)?$platformfee * (float) $chkCommissionOverride->ar->b2bid:$salesfee;
                            // info('', ['_leadspeek_type' => $_leadspeek_type,'srB2bID' => $overrideCommission['srB2bID'],'aeB2bID' => $overrideCommission['aeB2bID'],'arB2bID' => $overrideCommission['arB2bID'],]);
                        }
                        else if ($_leadspeek_type == 'wallet') // for data wallet, 5%
                        {
                            $overrideCommission['srWalletID'] = $platformfee * (float) 0.05;
                            $overrideCommission['aeWalletID'] = $platformfee * (float) 0.05;
                            $overrideCommission['arWalletID'] = $platformfee * (float) 0.05;
                            // info('percentage', ['_leadspeek_type' => $_leadspeek_type,'srWalletID' => $overrideCommission['srWalletID'],'aeWalletID' => $overrideCommission['aeWalletID'],'arWalletID' => $overrideCommission['arWalletID']]);
                        }
                    }
                }
                /** OVERRIDE THE COMMISSION IF ENABLED */

                /** RETRIVE BALANCE */
                $balance = $stripe->balance->retrieve([]);
                $currbalance = $balance->available[0]->amount / 100;
                $currbalance = number_format($currbalance,2,'.','');
                /** RETRIVE BALANCE */

                if ($currbalance >= $salesfee) 
                {
                    $tmp = explode(" ",$sale['name']);
                    $details = [
                        'firstname' => $tmp[0],
                        'salesfee'  => $salesfee,
                        'companyname' =>  $sale['company_name'],
                        'clientname' => $_client_name,
                        'campaignname' =>  $_campaign_name,
                        'campaignid' =>$_leadspeek_api_id,
                        'start' => date('Y-m-d',strtotime($startdate)),
                        'end' => date('Y-m-d',strtotime($enddate)),
                    ];
                    $attachement = array();
                    $from = [
                        'address' => 'noreply@exactmatchmarketing.com',
                        'name' => 'Commission Fee',
                        'replyto' => 'support@exactmatchmarketing.com',
                    ];

                    $description = "Commision from {$sale['company_name']} for campaign #{$_leadspeek_api_id}";
                    if($chargeType == 'wallet')
                    {
                        $description .= "Commision from {$sale['company_name']} for data wallet {$chargeFrom}";
                    }
                    elseif($chargeType == 'clean_id')
                    {
                        $description = "Commision from {$sale['company_name']} for cleanid #{$_leadspeek_api_id}";
                    }

                    if ($sale['sales_title'] == "Sales Representative") 
                    {
                        try 
                        {
                            /** CHECK IF OVERRIDE COMMISSION ENABLED */
                            if ($sale['custom_commission_enabled'] == 'T' && $ongoingleads != "") 
                            {
                                if ($_leadspeek_type == 'local') 
                                {
                                    $salesfee = (isset($overrideCommission['srSiteID']))?number_format($overrideCommission['srSiteID'],2,'.',''):$salesfee;
                                    $details['salesfee'] = $salesfee;
                                }
                                else if ($_leadspeek_type == 'locator') 
                                {
                                    $salesfee = (isset($overrideCommission['srSearchID']))?number_format( $overrideCommission['srSearchID'],2,'.',''):$salesfee;
                                    $details['salesfee'] = $salesfee;
                                }
                                else if ($_leadspeek_type == 'enhance') 
                                {
                                    $salesfee = (isset($overrideCommission['srEnhanceID']))?number_format( $overrideCommission['srEnhanceID'],2,'.',''):$salesfee;
                                    $details['salesfee'] = $salesfee;
                                }
                                else if ($_leadspeek_type == 'b2b') 
                                {
                                    $salesfee = (isset($overrideCommission['srB2bID']))?number_format( $overrideCommission['srB2bID'],2,'.',''):$salesfee;
                                    $details['salesfee'] = $salesfee;
                                }
                                else if ($_leadspeek_type == 'wallet')
                                {
                                    $salesfee = (isset($overrideCommission['srWalletID']))?number_format( $overrideCommission['srWalletID'],2,'.',''):$salesfee;
                                    $details['salesfee'] = $salesfee;
                                }
                            }
                            /** CHECK IF OVERRIDE COMMISSION ENABLED */

                            // info('Fee Sales Representative', ['_leadspeek_type' => $_leadspeek_type,'salesfee' => $salesfee]);
                            if ($salesfee > 0) 
                            {
                                //'description' => 'Commision from ' . $sale['company_name'] . '-' . $_client_name  . '-' . $_campaign_name . ' #' . $_leadspeek_api_id,
                                $transferData = [
                                    'amount' => ($salesfee * 100),
                                    'currency' => 'usd',
                                    'destination' => $sale['accconnectid'],
                                    'description' => $description
                                ];
                                if(!empty($transfer_group) && trim($transfer_group) != '')
                                    $transferData['transfer_group'] = $transfer_group;
                                if(!empty($source_transaction) && trim($source_transaction) != '')
                                    $transferData['source_transaction'] = $source_transaction;

                                $transferSales = $stripe->transfers->create($transferData);
                                // info('process transfer Sales Representative', ['transferData' => $transferData]);

                                if (isset($transferSales->destination_payment)) 
                                {
                                    $despay = $transferSales->destination_payment;
                                    $transferSalesDesc =  $stripe->charges->update(
                                        $despay,
                                        ['description' => $description],
                                        ['stripe_account' => $sale['accconnectid']]
                                    );
                                }

                                $srID = $sale['sales_id'];
                                $srFee = $salesfee;
                                $srTransferID = $transferSales->id ?? '';
                                $this->send_email(array($sale['email']),'Commission fee from ' . $sale['company_name'] . ' #' . $_leadspeek_api_id . '(SR)',$details,$attachement,'emails.salesfee',$from,$sale['company_root_id'],true);
                            }

                        }
                        catch (Exception $e) 
                        {
                            //return json_encode(array('result'=>'failed','payment_intentID'=>'','srID'=>'','aeID'=>'','salesfee'=>'','error'=>'SE error transfer'));
                            $this->send_notif_stripeerror('SE error transfer','SE error transfer for ' . $sale['company_name'] . ' #' . $_leadspeek_api_id,$sale['company_root_id']);
                        }
                    }
                    else if ($sale['sales_title'] == "Account Executive") 
                    {
                        try 
                        {
                            /** CHECK IF OVERRIDE COMMISSION ENABLED */
                            if ($sale['custom_commission_enabled'] == 'T' && $ongoingleads != "") 
                            {
                                if ($_leadspeek_type == 'local') 
                                {
                                    $salesfee = (isset($overrideCommission['aeSiteID']))?number_format( $overrideCommission['aeSiteID'],2,'.',''):$salesfee;
                                    $details['salesfee'] = $salesfee;
                                }
                                else if ($_leadspeek_type == 'locator') 
                                {
                                    $salesfee = (isset($overrideCommission['aeSearchID']))?number_format( $overrideCommission['aeSearchID'],2,'.',''):$salesfee;
                                    $details['salesfee'] = $salesfee;
                                }
                                else if ($_leadspeek_type == 'enhance') 
                                {
                                    $salesfee = (isset($overrideCommission['aeEnhanceID']))?number_format( $overrideCommission['aeEnhanceID'],2,'.',''):$salesfee;
                                    $details['salesfee'] = $salesfee;
                                }
                                else if ($_leadspeek_type == 'b2b') 
                                {
                                    $salesfee = (isset($overrideCommission['aeB2bID']))?number_format( $overrideCommission['aeB2bID'],2,'.',''):$salesfee;
                                    $details['salesfee'] = $salesfee;
                                }
                                else if ($_leadspeek_type == 'wallet') 
                                {
                                    $salesfee = (isset($overrideCommission['aeWalletID']))?number_format( $overrideCommission['aeWalletID'],2,'.',''):$salesfee;
                                    $details['salesfee'] = $salesfee;
                                }
                            }
                            /** CHECK IF OVERRIDE COMMISSION ENABLED */

                            // info('Fee Account Executive', ['_leadspeek_type' => $_leadspeek_type,'salesfee' => $salesfee]);
                            if ($salesfee > 0) 
                            { 
                                $transferData = [
                                    'amount' => ($salesfee * 100),
                                    'currency' => 'usd',
                                    'destination' => $sale['accconnectid'],
                                    'description' => $description,
                                ];
                                if(!empty($transfer_group) && trim($transfer_group) != '')
                                    $transferData['transfer_group'] = $transfer_group;
                                if(!empty($source_transaction) && trim($source_transaction) != '')
                                    $transferData['source_transaction'] = $source_transaction;

                                $transferSales = $stripe->transfers->create($transferData);
                                // info('process transfer Account Executive', ['transferData' => $transferData]);
                                
                                if (isset($transferSales->destination_payment)) 
                                {
                                    $despay = $transferSales->destination_payment;
                                    $transferSalesDesc =  $stripe->charges->update(
                                        $despay,
                                        ['description' => $description,],
                                        ['stripe_account' => $sale['accconnectid']]
                                    );
                                }

                                $aeID = $sale['sales_id'];
                                $aeFee = $salesfee;
                                $aeTransferID = $transferSales->id ?? '';
                                $this->send_email(array($sale['email']),'Commission fee from ' . $sale['company_name'] . ' #' . $_leadspeek_api_id . '(AE)',$details,$attachement,'emails.salesfee',$from,$sale['company_root_id'],true);
                            }
                        }
                        catch (Exception $e) 
                        {
                            //return json_encode(array('result'=>'failed','payment_intentID'=>'','srID'=>'','aeID'=>'','salesfee'=>'','error'=>'AE error transfer'));
                            $this->send_notif_stripeerror('AE error transfer','AE error transfer for ' . $sale['company_name'] . ' #' . $_leadspeek_api_id,$sale['company_root_id']);
                        }
                    }
                    else if ($sale['sales_title'] == "Account Referral") 
                    {
                        try 
                        {
                            /** CHECK IF OVERRIDE COMMISSION ENABLED */
                            if ($sale['custom_commission_enabled'] == 'T' && $ongoingleads != "") 
                            {
                                if ($_leadspeek_type == 'local') 
                                {
                                    $salesfee = (isset($overrideCommission['arSiteID']))?number_format( $overrideCommission['arSiteID'],2,'.',''):$salesfee;
                                    $details['salesfee'] = $salesfee;
                                }
                                else if ($_leadspeek_type == 'locator') 
                                {
                                    $salesfee = (isset($overrideCommission['arSearchID']))?number_format( $overrideCommission['arSearchID'],2,'.',''):$salesfee;
                                    $details['salesfee'] = $salesfee;
                                }
                                else if ($_leadspeek_type == 'enhance') 
                                {
                                    $salesfee = (isset($overrideCommission['arEnhanceID']))?number_format( $overrideCommission['arEnhanceID'],2,'.',''):$salesfee;
                                    $details['salesfee'] = $salesfee;
                                }
                                else if ($_leadspeek_type == 'b2b') 
                                {
                                    $salesfee = (isset($overrideCommission['arB2bID']))?number_format( $overrideCommission['arB2bID'],2,'.',''):$salesfee;
                                    $details['salesfee'] = $salesfee;
                                }
                                else if ($_leadspeek_type == 'wallet') 
                                {
                                    $salesfee = (isset($overrideCommission['arWalletID']))?number_format( $overrideCommission['arWalletID'],2,'.',''):$salesfee;
                                    $details['salesfee'] = $salesfee;
                                }
                            }
                            /** CHECK IF OVERRIDE COMMISSION ENABLED */

                            // info('Fee Account Referral', ['_leadspeek_type' => $_leadspeek_type,'salesfee' => $salesfee]);
                            if ($salesfee > 0) 
                            { 
                                $transferData = [
                                    'amount' => ($salesfee * 100),
                                    'currency' => 'usd',
                                    'destination' => $sale['accconnectid'],
                                    'description' => $description,
                                ];
                                if(!empty($transfer_group) && trim($transfer_group) != '')
                                    $transferData['transfer_group'] = $transfer_group;
                                if(!empty($source_transaction) && trim($source_transaction) != '')
                                    $transferData['source_transaction'] = $source_transaction;

                                $transferSales = $stripe->transfers->create($transferData);
                                // info('process transfer Account Referral', ['transferData' => $transferData]);
                                
                                if (isset($transferSales->destination_payment)) 
                                {
                                    $despay = $transferSales->destination_payment;
                                    $transferSalesDesc =  $stripe->charges->update(
                                        $despay,
                                        ['description' => $description,],
                                        ['stripe_account' => $sale['accconnectid']]
                                    );
                                }

                                $arID = $sale['sales_id'];
                                $arFee = $salesfee;
                                $arTransferID = $transferSales->id ?? '';
                                $this->send_email(array($sale['email']),'Commission fee from ' . $sale['company_name'] . ' #' . $_leadspeek_api_id . '(AR)',$details,$attachement,'emails.salesfee',$from,$sale['company_root_id'],true);
                            }
                        }
                        catch (Exception $e)
                        {
                            //return json_encode(array('result'=>'failed','payment_intentID'=>'','srID'=>'','aeID'=>'','salesfee'=>'','error'=>'AE error transfer'));
                            $this->send_notif_stripeerror('ACREF error transfer','ACREF error transfer for ' . $sale['company_name'] . ' #' . $_leadspeek_api_id,$sale['company_root_id']);
                        }
                    }
                }
                else
                {
                    $tmp = explode(" ",$sale['name']);
                    $this->send_notif_stripeerror('insufficient balance Commision for ' . $tmp[0],'Insufficient balance to transfer for ' . $sale['company_name'] . ' #' . $_leadspeek_api_id,$sale['company_root_id']);
                }
            }
        }
        
        return json_encode([
            'result'=>'success',
            'payment_intentID'=>'',
            'srID'=>$srID,
            'aeID'=>$aeID,
            'arID'=>$arID,
            'srFee'=>$srFee,
            'aeFee'=>$aeFee,
            'arFee'=>$arFee,
            'srTransferID'=>$srTransferID,
            'aeTransferID'=>$aeTransferID,
            'arTransferID'=>$arTransferID,
            'salesfee'=>$salesfee,
            'error'=>''
        ]);
    }

    public function getCampaignDetails($leadspeek_api_id) 
    {
        $campaigndetails = LeadspeekUser::select('leadspeek_users.campaign_name','leadspeek_users.paymentterm','leadspeek_users.leadspeek_type','companies.company_name')
                                        ->join('users','leadspeek_users.user_id','=','users.id')
                                        ->join('companies','users.company_id','=','companies.id')
                                        ->where('leadspeek_users.leadspeek_api_id','=',$leadspeek_api_id)
                                        ->first();
        return $campaigndetails;
    }

    public function chargePrepaidDirectPayment($_company_id = "",$_amount = 0,$_stop_continual = "",$_custom_amount = "",$_ip_user = "",$_timezone = "",$_payment_type = "credit_card", $is_from_auto_topup = true, $leadspeek_api_id_data_wallet = "")
    {
        date_default_timezone_set('America/Chicago');

        $company_id = $_company_id;
        $amount = $_amount;
        $stop_continual = ($_stop_continual == true ? 'T' : 'F');
        $custom_amount = ($_custom_amount == true ? 'T' : 'F');
        $ip_user =  $_ip_user; 
        $timezone = $_timezone; 
        $payment_type = $_payment_type; 

        // info(['company_id' => $company_id, 'amount' => $amount, 'stop_continual' => $stop_continual, 'ip_user' => $ip_user, 'timezone' => $timezone, 'payment_type' => $payment_type]);

        $user = User::select(
                        'users.id','users.last_balance_amount','users.email','users.company_root_id','users.customer_payment_id','users.customer_card_id','users.payment_status','users.amount','users.is_marketing_services_agreement_developer',
                        'companies.company_name','companies.manual_bill')
                    ->join('companies','companies.id','=','users.company_id')
                    ->where('users.company_id','=',$company_id)
                    ->where('users.active','=','T')
                    ->where('users.user_type','=','userdownline')
                    ->first();
        $user_id = (isset($user->id)) ? trim($user->id) : '';
        $email = (isset($user->email)) ? trim($user->email) : '';
        $company_root_id = (isset($user->company_root_id)) ? trim($user->company_root_id) : '';
        $company_name = (isset($user->company_name)) ? trim($user->company_name) : '';
        $customer_payment_id = (isset($user->customer_payment_id)) ? trim($user->customer_payment_id) : '';
        $customer_card_id = (isset($user->customer_card_id)) ? trim($user->customer_card_id) : '';

        /* VALIDATE */
        if(empty($company_id) || trim($company_id) == '')
            return [
                'result' => 'failed',
                'message' => 'The company ID is empty.',
            ]; 

        if(($amount == '' || $amount < 1)) 
            return [
                'result' => 'failed',
                'message' => 'The minimum amount is $1',
            ];
        
        if(empty($customer_payment_id) || trim($customer_payment_id) == '' || empty($customer_card_id) || trim($customer_card_id) == '')
            return [
                'result' => 'failed',
                'message' => 'Your credit card has never been set up.'
            ];

        $agencyManualBill = isset($user->manual_bill) ? $user->manual_bill : '';
        $agencyAgreeDeveloper = isset($user->is_marketing_services_agreement_developer) ? $user->is_marketing_services_agreement_developer : '';
        $isEnableTopupPrepaidDirectPayment = (($agencyManualBill == 'T') || ($agencyAgreeDeveloper == 'T')); 
        if(!$isEnableTopupPrepaidDirectPayment)
            return [
                'result' => 'failed',
                'message' => 'Feature to charge data wallet is not available.'
            ];
        /* VALIDATE */        

        /** GET STRIPE KEY */
        $stripeseckey = config('services.stripe.secret');
        $stripepublish = $this->getcompanysetting($company_root_id,'rootstripe');
        if ($stripepublish != '') 
            $stripeseckey = (isset($stripepublish->secretkey))?$stripepublish->secretkey:"";
        /** GET STRIPE KEY */
        
        /* PROCESS CHARGE AGENCIES */
        $statusPayment = "";
        $payment_intent_status = "";
        $payment_intent_id = "";
        $errorstripe = "";

        $now = date('Ymd');
        $defaultInvoice = "#{$now}-{$company_id}-{$company_name} prepaid-direct-payment";

        $amount = number_format($amount,2,'.','');
        $amountStripe = $amount * 100;
        // info(__FUNCTION__, ['is_from_auto_topup' => $is_from_auto_topup]);
        if($is_from_auto_topup !== true) // jika bukan berasal dari auto topup campaign, maka update amount user
            $user->amount = $amount;
        $user->custom_amount = $custom_amount;
        $user->save();

        $runningTopupExists = TopupAgency::where('company_id','=',$company_id)
                                         ->where('topup_status','=','progress')
                                         ->whereNull('expired_at')
                                         ->exists();
        $topup_status = $runningTopupExists ? "queue" : "progress";
        $topup = [
            'user_id' => $user_id,
            'company_id' => $company_id,
            'stop_continue' => $stop_continual,
            'total_amount' => $amount,
            'balance_amount' => $amount,
            'topup_status' => $topup_status,
            'ip_user' => $ip_user,
            'timezone' => $timezone,
            'payment_type' => $payment_type,
            'expired_at' => null,
        ];

        try
        {
            $stripe = new StripeClient([
                'api_key' => $stripeseckey,
                'stripe_version' => '2020-08-27'
            ]);

            // info(['payment_method_types' => ['card'],'customer' => $customer_payment_id,'amount' => $amountStripe,'currency' => 'usd','receipt_email' => $email,'payment_method' => $customer_card_id,'confirm' => true,'description' => $defaultInvoice,]);
            $payment_intent =  $stripe->paymentIntents->create([
                'payment_method_types' => ['card'],
                'customer' => $customer_payment_id,
                'amount' => $amountStripe,
                'currency' => 'usd',
                'receipt_email' => $email,
                'payment_method' => $customer_card_id,
                'confirm' => true,
                'description' => $defaultInvoice,
            ]);

            $statusPayment = "paid";
            $payment_intent_id = (isset($payment_intent->id)) ? $payment_intent->id : '';

            /* CHECK STATUS PAYMENT INTENTS */
            $payment_intent_status = (isset($payment_intent->status)) ? $payment_intent->status : "";
            if($payment_intent_status == 'requires_action')
                $statusPayment = 'failed';
            /* CHECK STATUS PAYMENT INTENTS */
        }
        catch (RateLimitException $e) 
        {
            // Too many requests made to the API too quickly
            $statusPayment = 'failed';
            $errorstripe = $e->getHttpStatus() . '|' . $e->getError()->type . '|' . $e->getError()->code . '|' . $e->getError()->param . '|' . $e->getError()->message;
        } 
        catch (InvalidRequestException $e) 
        {
            // Invalid parameters were supplied to Stripe's API
            $statusPayment = 'failed';
            $errorstripe = $e->getHttpStatus() . '|' . $e->getError()->type . '|' . $e->getError()->code . '|' . $e->getError()->param . '|' . $e->getError()->message;
        } 
        catch (ExceptionAuthenticationException $e) 
        {
            // Authentication with Stripe's API failed, (maybe you changed API keys recently)
            $statusPayment = 'failed';
            $errorstripe = $e->getHttpStatus() . '|' . $e->getError()->type . '|' . $e->getError()->code . '|' . $e->getError()->param . '|' . $e->getError()->message;
        } 
        catch (ApiConnectionException $e) 
        {
            // Network communication with Stripe failed
            $statusPayment = 'failed';
            $errorstripe = $e->getHttpStatus() . '|' . $e->getError()->type . '|' . $e->getError()->code . '|' . $e->getError()->param . '|' . $e->getError()->message;
        } 
        catch (ApiErrorException $e) 
        {
            // Display a very generic error to the user, and maybe send, yourself an email
            $statusPayment = 'failed';
            $errorstripe = $e->getHttpStatus() . '|' . $e->getError()->type . '|' . $e->getError()->code . '|' . $e->getError()->param . '|' . $e->getError()->message;
        } 
        catch (Exception $e) 
        {
            // Something else happened, completely unrelated to Stripe
            $statusPayment = 'failed';
            $errorstripe = 'error not stripe things';
        }

        $cardlast = "";
        try
        {
            $cardinfo = $stripe->customers->retrieveSource($customer_payment_id,$customer_card_id,[]);
            $cardlast = $cardinfo->last4;
        }
        catch(Exception $e) 
        {
            $cardlast = "";
        }
        /* PROCESS CHARGE AGENCIES */

        /* USER LOGS */
        $userIDLogin = $user_id;
        $action = ($is_from_auto_topup === true ? "Auto " : "") . "Topup Agency Wallet";
        $descriptionData = [
            'user id' => $user_id,
            'company id' => $company_id,
            'agency name' => $company_name,
            'agency email' => $email,
            'stop continual topup' => $stop_continual,
            'total amount' => $amount,
        ];
        /* USER LOGS */
    
        /* VALIDATE RESULT CHARGE AGENCY */
        if($statusPayment == 'failed') 
        {
            $user->payment_status = 'failed';
            $user->save();

            $description = collect($descriptionData)->map(fn($v, $k) => "$k: $v")->implode(' | ');
            $action .= " Failed";
            $this->logUserAction($userIDLogin, $action, $description, $ip_user, $user_id);

            return [
                'result' => 'failed',
                'message' => 'Sorry, this prepaid direct amount cannot be started because your credit card charge failed. Please check your payment details and try again.'
            ];
        }
        /* VALIDATE RESULT CHARGE AGENCY */

        /* CREATE TOPUP */
        $topupCreate = TopupAgency::create($topup);
        $topupAgencyID = (isset($topupCreate->id)) ? $topupCreate->id : null;

        $total_balance = TopupAgency::where('company_id','=',$company_id)
                                    ->where('topup_status','<>','done')
                                    ->whereNull('expired_at')
                                    ->sum('balance_amount');
        $user->last_balance_amount = $total_balance;
        $user->save();
        /* CREATE TOPUP */
        
        /* CREATE INVOICE */
        $invoiceCreated = LeadspeekInvoice::create([
            'invoice_type' => 'agency',
            'topup_agencies_id' => $topupAgencyID,
            'payment_type' => $payment_type,
            'company_id' => $company_id,
            'user_id' => $user_id,
            'leadspeek_api_id' => $leadspeek_api_id_data_wallet,
            'invoice_number' => '',
            'payment_term' => '',
            'onetimefee' => 0,
            'platform_onetimefee' => 0,
            'min_leads' => 0,
            'exceed_leads' => 0,
            'total_leads' => 0,
            'min_cost' => 0,
            'platform_min_cost' => 0,
            'cost_leads' => 0,
            'platform_cost_leads' => 0,
            'total_amount' => 0,
            'platform_total_amount' => $amount,
            'root_total_amount' => 0,
            'status' => $statusPayment,
            'customer_payment_id' => '',
            'customer_stripe_id' => $customer_payment_id,
            'customer_card_id' => $customer_card_id,
            'platform_customer_payment_id' => $payment_intent_id,
            'error_payment' => '',
            'platform_error_payment' => $errorstripe,
            'invoice_date' => date('Y-m-d'),
            'invoice_start' => date('Y-m-d'),
            'invoice_end' => date('Y-m-d'),
            'sent_to' => $email,
            'sr_id' => 0,
            'sr_fee' => 0,
            'sr_transfer_id' => '',
            'ae_id' => 0,
            'ae_fee' => 0,
            'ae_transfer_id' => '',
            'ar_id' => 0,
            'ar_fee' => 0,
            'ar_transfer_id' => '',
            'active' => 'T',
        ]);
        $invoiceID = $invoiceCreated->id;
        $invoiceCreated->invoice_number = "{$now} - {$invoiceID}";
        $invoiceCreated->save();
        /* CREATE INVOICE */

        /* SEND EMAIL */
        $AdminDefault = $this->get_default_admin($company_root_id);
        $AdminDefaultEmail = (isset($AdminDefault[0]['email']))?$AdminDefault[0]['email']:'';
        $defaultdomain = $this->getDefaultDomainEmail($company_root_id);

        $todayDate = date('m-d-Y');
        $title = "Invoice for {$company_name} #({$todayDate})";

        $invoiceStatus = "";
        if($statusPayment == 'paid') 
            $invoiceStatus = "Customer's Credit Card Successfully Charged";
        else if($statusPayment == 'failed')
            $invoiceStatus = "Customer's Credit Card Failed";
        else
            $invoiceStatus = "Customer's Credit Card Successfully Charged";

        $details = [
            'invoicetype' => 'agency',
            'payment_type' => ($payment_type == 'credit_card') ? 'credit card' : 'bank account',
            'company_name' => $company_name,
            'cardlast' => $cardlast,
            'invoice_number' => "{$now} - {$invoiceID}",
            'invoice_status' => $statusPayment,
            'invoice_date' => date('m-d-Y'),
            'platform_total_amount' => $amount,
            'defaultadmin' => $AdminDefaultEmail,
        ];

        $from = [
            'address' => 'noreply@' . $defaultdomain,
            'name' => 'Invoice',
            'replyto' => 'support@' . $defaultdomain,
        ];

        $attachement = [];

        $tmp = $this->send_email([$email],$title,$details,$attachement,'emails.agencyprepaiddirectpayment',$from,$company_root_id,true);
        $tmp = $this->send_email(['serverlogs@sitesettingsapi.com'],$title,$details,$attachement,'emails.agencyprepaiddirectpayment',$from,'',true);     
        /* SEND EMAIL */

        /* USER LOGS */
        $descriptionData['balance amount'] = $total_balance;
        $descriptionData['invoice id'] = $invoiceID;
        $description = collect($descriptionData)->map(fn($v, $k) => "$k: $v")->implode(' | ');
        $this->logUserAction($userIDLogin, $action, $description, $ip_user, $user_id);
        /* USER LOGS */

        /* TRASFER COMMISSION AGENCY */
        $dataCustomCommissionSales = ['type' => 'wallet', 'from' => 'topup'];
        $transferCommissionSales = $this->transfer_commission_sales($company_id,$amount,"", date('Y-m-d'),date('Y-m-d'),$stripeseckey,$amount,"",$dataCustomCommissionSales);
        $transferCommissionSales = json_decode($transferCommissionSales);
        $invoiceCreated->sr_id = $transferCommissionSales->srID ?? 0;
        $invoiceCreated->sr_fee = $transferCommissionSales->srFee ?? 0;
        $invoiceCreated->sr_transfer_id = $transferCommissionSales->srTransferID ?? '';
        $invoiceCreated->ae_id = $transferCommissionSales->aeID ?? 0;
        $invoiceCreated->ae_fee = $transferCommissionSales->aeFee ?? 0;
        $invoiceCreated->ae_transfer_id = $transferCommissionSales->aeTransferID ?? '';
        $invoiceCreated->ar_id = $transferCommissionSales->arID ?? 0;
        $invoiceCreated->ar_fee = $transferCommissionSales->arFee ?? 0;
        $invoiceCreated->ar_transfer_id = $transferCommissionSales->arTransferID ?? '';
        $invoiceCreated->save();
        /* TRASFER COMMISSION AGENCY */
    
        return [
            'result' => "success",
            'message' => "Topup direct payment was successful.",
            'invoiceID' => $invoiceID,
        ];
    }

    public function process_charge_agency_stripeinfo($stripeseckey = '',$customer_payment_id = '',$platformfee = 0, $email = '',$customer_card_id = '',$defaultInvoice = '',$transferGroup = '',$_leadspeek_api_id = '',$_agency_name = '',$_client_name = '',$_campaign_name = '',$company_root_id = '') 
    {
        $payment_intent = "";
        $statusPayment = "";
        $errorstripe = "";

        if($platformfee < 0.5 || $platformfee == '')
        {
            $statusPayment = 'paid';
            return [
                'payment_intent' => $payment_intent,
                'statusPayment' => $statusPayment,
                'errorstripe' => $errorstripe
            ];
        }

        $stripe = new StripeClient([
            'api_key' => $stripeseckey,
            'stripe_version' => '2020-08-27'
        ]);

        try
        {
            $timestampKey = Carbon::now()->format('YmdHi'); // formatnya {namafungsi}_{leadspeekapiid}_{waktusampaimenit}
            $idempotencyKey = "processchargeagencystripeinfo_{$_leadspeek_api_id}_{$timestampKey}";
            
            // info(['action' => 'process_charge_agency_stripeinfo','idempotency_key' => $idempotencyKey,'payment_method_types' => ['card'],'customer' => trim($customer_payment_id),'amount' => ($platformfee * 100),'currency' => 'usd','receipt_email' => $email,'payment_method' => trim($customer_card_id),'confirm' => true,'description' => $defaultInvoice,'transfer_group' => $transferGroup,]);
            $payment_intent =  $stripe->paymentIntents->create([
                'payment_method_types' => ['card'],
                'customer' => trim($customer_payment_id),
                'amount' => ($platformfee * 100),
                'currency' => 'usd',
                'receipt_email' => $email,
                'payment_method' => trim($customer_card_id),
                'confirm' => true,
                'description' => $defaultInvoice,
                'transfer_group' => $transferGroup,
            ],[
                'idempotency_key' => $idempotencyKey
            ]);
            $statusPayment = 'paid';

            /* CHECK STATUS PAYMENT INTENTS */
            $payment_intent_status = (isset($payment_intent->status))?$payment_intent->status:"";
            if($payment_intent_status == 'requires_action') 
            {
                $statusPayment = 'failed';
                $errorstripe = "Payment for campaign $_leadspeek_api_id was unsuccessful: Stripe status '$payment_intent_status' indicates further user action is needed.";
                $this->send_notif_stripeerror("Agency Billing Failure Notice","<p>Dear Admin,</p><p>The following agency's card failed to process a payment.</p><p>Agency: " . $_agency_name . "</p><p>Client: " . $_client_name . "</p>Campaign: " . $_campaign_name . "#" . $_leadspeek_api_id . "</p><p>&nbsp;</p><p>&nbsp;</p><p>Stripe Error : " . $errorstripe . "</p>",$company_root_id);
            }
            /* CHECK STATUS PAYMENT INTENTS */

        }
        catch (RateLimitException $e) 
        {
            $statusPayment = 'failed';
            // Too many requests made to the API too quickly
            $errorstripe = $e->getHttpStatus() . '|' . $e->getError()->type . '|' . $e->getError()->code . '|' . $e->getError()->param . '|' . $e->getError()->message;
            $this->send_notif_stripeerror("Agency Billing Failure Notice","<p>Dear Admin,</p><p>The following agency's card failed to process a payment.</p><p>Agency: " . $_agency_name . "</p><p>Client: " . $_client_name . "</p>Campaign: " . $_campaign_name . "#" . $_leadspeek_api_id . "</p><p>&nbsp;</p><p>&nbsp;</p><p>Stripe Error : " . $errorstripe . "</p>",$company_root_id);
            //return json_encode(array('result'=>'failed','payment_intentID'=>'','srID'=>'','aeID'=>'','salesfee'=>'','error'=>$errorstripe));
        } 
        catch (InvalidRequestException $e) 
        {
            $statusPayment = 'failed';
            // Invalid parameters were supplied to Stripe's API
            $errorstripe = $e->getHttpStatus() . '|' . $e->getError()->type . '|' . $e->getError()->code . '|' . $e->getError()->param . '|' . $e->getError()->message;
            $this->send_notif_stripeerror("Agency Billing Failure Notice","<p>Dear Admin,</p><p>The following agency's card failed to process a payment.</p><p>Agency: " . $_agency_name . "</p><p>Client: " . $_client_name . "</p>Campaign: " . $_campaign_name . "#" . $_leadspeek_api_id . "</p><p>&nbsp;</p><p>&nbsp;</p><p>Stripe Error : " . $errorstripe . "</p>",$company_root_id);
            //return json_encode(array('result'=>'failed','payment_intentID'=>'','srID'=>'','aeID'=>'','salesfee'=>'','error'=>$errorstripe));
        } 
        catch (ExceptionAuthenticationException $e) 
        {
            $statusPayment = 'failed';
            // Authentication with Stripe's API failed
            // (maybe you changed API keys recently)
            $errorstripe = $e->getHttpStatus() . '|' . $e->getError()->type . '|' . $e->getError()->code . '|' . $e->getError()->param . '|' . $e->getError()->message;
            $this->send_notif_stripeerror("Agency Billing Failure Notice","<p>Dear Admin,</p><p>The following agency's card failed to process a payment.</p><p>Agency: " . $_agency_name . "</p><p>Client: " . $_client_name . "</p>Campaign: " . $_campaign_name . "#" . $_leadspeek_api_id . "</p><p>&nbsp;</p><p>&nbsp;</p><p>Stripe Error : " . $errorstripe . "</p>",$company_root_id);
            //return json_encode(array('result'=>'failed','payment_intentID'=>'','srID'=>'','aeID'=>'','salesfee'=>'','error'=>$errorstripe));
        } 
        catch (ApiConnectionException $e) 
        {
            $statusPayment = 'failed';
            // Network communication with Stripe failed
            $errorstripe = $e->getHttpStatus() . '|' . $e->getError()->type . '|' . $e->getError()->code . '|' . $e->getError()->param . '|' . $e->getError()->message;
            $this->send_notif_stripeerror("Agency Billing Failure Notice","<p>Dear Admin,</p><p>The following agency's card failed to process a payment.</p><p>Agency: " . $_agency_name . "</p><p>Client: " . $_client_name . "</p>Campaign: " . $_campaign_name . "#" . $_leadspeek_api_id . "</p><p>&nbsp;</p><p>&nbsp;</p><p>Stripe Error : " . $errorstripe . "</p>",$company_root_id);
            //return json_encode(array('result'=>'failed','payment_intentID'=>'','srID'=>'','aeID'=>'','salesfee'=>'','error'=>$errorstripe));
        } 
        catch (ApiErrorException $e) 
        {
            $statusPayment = 'failed';
            // Display a very generic error to the user, and maybe send
            // yourself an email
            $errorstripe = $e->getHttpStatus() . '|' . $e->getError()->type . '|' . $e->getError()->code . '|' . $e->getError()->param . '|' . $e->getError()->message;
            $this->send_notif_stripeerror("Agency Billing Failure Notice","<p>Dear Admin,</p><p>The following agency's card failed to process a payment.</p><p>Agency: " . $_agency_name . "</p><p>Client: " . $_client_name . "</p>Campaign: " . $_campaign_name . "#" . $_leadspeek_api_id . "</p><p>&nbsp;</p><p>&nbsp;</p><p>Stripe Error : " . $errorstripe . "</p>",$company_root_id);
            //return json_encode(array('result'=>'failed','payment_intentID'=>'','srID'=>'','aeID'=>'','salesfee'=>'','error'=>$errorstripe));
        } 
        catch (Exception $e) 
        {
            $statusPayment = 'failed';
            // Something else happened, completely unrelated to Stripe
            $errorstripe = 'error not stripe things';
            $this->send_notif_stripeerror("Agency Billing Failure Notice","<p>Dear Admin,</p><p>The following agency's card failed to process a payment.</p><p>Agency: " . $_agency_name . "</p><p>Client: " . $_client_name . "</p>Campaign: " . $_campaign_name . "#" . $_leadspeek_api_id . "</p><p>&nbsp;</p><p>&nbsp;</p><p>Stripe Error : " . $errorstripe . "</p>",$company_root_id);
            //return json_encode(array('result'=>'failed','payment_intentID'=>'','srID'=>'','aeID'=>'','salesfee'=>'','error'=>$errorstripe));
        }

        return [
            'payment_intent' => $payment_intent,
            'statusPayment' => $statusPayment,
            'errorstripe' => $errorstripe
        ];
    }
    
    public function check_agency_stripeinfo($companyParentID = "",$platformfee = "",$_leadspeek_api_id = "",$defaultInvoice = "",$startdate = "0000-00-00 00:00:00",$enddate = "0000-00-00 00:00:00",$ongoingleads = "",$cleanProfit = "",$dataCustomCommissionSales = [],$agencyManualBill = 'F', $dataChargeClient = [])
    {
        date_default_timezone_set('America/Chicago');
        
        $chkUser = User::select('users.id','users.customer_payment_id','users.customer_card_id','users.email','users.company_root_id','users.amount','users.custom_amount','users.last_balance_amount','users.stopcontinual','users.payment_type','users.ip_login','companies.company_name')
                       ->leftjoin('companies','companies.id','=','users.company_id')
                       ->where('users.company_id','=',$companyParentID)
                       ->where('users.company_parent','<>',$companyParentID)
                       ->where('users.user_type','=','userdownline')
                       ->where('users.isAdmin','=','T')
                       ->where('users.active','=','T')
                       ->get();
        // info('start function check_agency_stripeinfo', ['companyParentID' => $companyParentID, 'chkUser' => $chkUser]);
        if(count($chkUser) > 0) 
        {
            /** GET STRIPE KEY */
            $company_root_id = $chkUser[0]['company_root_id'];
            $stripeseckey = config('services.stripe.secret');
            $stripepublish = $this->getcompanysetting($company_root_id,'rootstripe');
            if ($stripepublish != '') 
            {
                $stripeseckey = (isset($stripepublish->secretkey))?$stripepublish->secretkey:"";
            }
            /** GET STRIPE KEY */

            /** GET OTHER DETAILS */
            $systemid = config('services.application.systemid');
            $transferGroup = 'AI_' . $chkUser[0]['id'] . '_' . $_leadspeek_api_id . uniqid();
            $srID = "";
            $aeID = "";
            $arID = "";
            $srFee = 0;
            $aeFee = 0;
            $arFee = 0;
            $srTransferID = '';
            $aeTransferID = '';
            $arTransferID = '';
            $salesfee = 0;
            $topup_agencies_id = null;
            $leadspeek_invoices_id = null;

            $payment_intent = "";
            $statusPayment = "";
            $statusPaymentClient = "";
            $errorstripeClient = "";
            $chargeStatusWithPrepaid = "";
            $errorstripe = "";

            $_campaign_name = "";
            $_campaign_paymentterm = "";
            $_client_name = "";
            $_campaign_type = "";  
            $_agency_name = (isset($chkUser[0]['company_name'])) ? $chkUser[0]['company_name'] : '';
            $_agency_stop_continual = (isset($chkUser[0]['stopcontinual'])) ? $chkUser[0]['stopcontinual'] : 'F';
            $_agency_amount = (isset($chkUser[0]['amount'])) ? $chkUser[0]['amount'] : 0;
            $_agency_custom_amount = (isset($chkUser[0]['custom_amount'])) ? $chkUser[0]['custom_amount'] : 'F';
            $_agency_payment_type = (isset($chkUser[0]['payment_type'])) ? $chkUser[0]['payment_type'] : 'payment_type';

            $_agency_ip_login = (isset($chkUser[0]['ip_login'])) ? explode('|', $chkUser[0]['ip_login']) : [];
            $_agency_ip_user = (isset($_agency_ip_login[0])) ? $_agency_ip_login[0] : '';
            $_agency_timezone = (isset($_agency_ip_login[1])) ? $_agency_ip_login[1] : '';

            // $_agency_last_balance_amount = (isset($chkUser[0]['last_balance_amount'])) ? $chkUser[0]['last_balance_amount'] : 0; // dulu 10% auto topup data wallet itu acuan dari last_balance_amount
            $_agency_last_balance_amount = $_agency_amount; // sekarang 10% auto topup data wallet acuan nya dari amount yang di setup
            $_agency_balance_threshold = ($_agency_last_balance_amount * 10 / 100);
            $_agency_balance_threshold = (float) number_format($_agency_balance_threshold,2,'.','');

            // info(['_agency_stop_continual' => $_agency_stop_continual,'_agency_amount' => $_agency_amount,'_agency_custom_amount' => $_agency_custom_amount,'_agency_payment_type' => $_agency_payment_type,'_agency_ip_login' => $_agency_ip_login,'_agency_ip_user' => $_agency_ip_user,'_agency_timezone' => $_agency_timezone,'_agency_last_balance_amount' => $_agency_last_balance_amount,'_agency_balance_threshold' => $_agency_balance_threshold,]);
            $campaigndetails = $this->getCampaignDetails($_leadspeek_api_id);
            if(!empty($campaigndetails)) 
            {
                $_campaign_name = isset($campaigndetails->campaign_name) ? $campaigndetails->campaign_name : '';
                $_campaign_paymentterm = isset($campaigndetails->paymentterm) ? $campaigndetails->paymentterm : ''; 
                $_client_name = isset($campaigndetails->company_name) ? $campaigndetails->company_name : '';
                $_campaign_type = isset($campaigndetails->leadspeek_type) ? $campaigndetails->leadspeek_type : '';
                // info(['_campaign_name' => $_campaign_name,'_campaign_paymentterm' => $_campaign_paymentterm,'_client_name' => $_client_name,]);
            }

            /** GET OTHER DETAILS */

            /** GET AGENCY BALANCE AMOUNT IF MANUAL BILL */
            $topupAgencyExists = false;
            $remainingPlatformfee = 0;
            $applicationFeeAmount = 0;
            if($_campaign_paymentterm == 'Prepaid' && $company_root_id == $systemid && $_campaign_type != 'simplifi')
            {
                // hati-hati menggunakan function ini, karena parameter nya menggunakan reference
                // (&$topupAgencyExists, &$remainingPlatformfee, $statusPaymentClient, $errorstripeClient, &$statusPayment, &$topup_agencies_id, &$leadspeek_invoices_id, &$chargeStatusWithPrepaid)
                // info('charge pakai data wallet', ['dataChargeClient' => $dataChargeClient]);
                $this->process_charge_agency_wallet($companyParentID, $platformfee, $_campaign_paymentterm, $topupAgencyExists, $remainingPlatformfee, $statusPayment, $statusPaymentClient, $errorstripeClient, $topup_agencies_id, $leadspeek_invoices_id, $chargeStatusWithPrepaid, $applicationFeeAmount, $_agency_stop_continual, $_agency_amount, $_agency_custom_amount, $_agency_payment_type, $_agency_ip_user, $_agency_timezone, $_agency_balance_threshold, $stripeseckey, $chkUser, $defaultInvoice, $transferGroup, $_leadspeek_api_id, $_agency_name, $_client_name, $_campaign_name, $dataChargeClient);
            }
            /** GET AGENCY BALANCE AMOUNT IF MANUAL BILL */

            /** CHARGE WITH STRIPE */
            // info(['remainingPlatformfee_final' => $remainingPlatformfee, 'topupAgencyExists' => $topupAgencyExists, 'applicationFeeAmount' => $applicationFeeAmount]);
            if(!$topupAgencyExists)
            {
                $resultCharge = $this->process_charge_agency_stripeinfo($stripeseckey,$chkUser[0]['customer_payment_id'],$platformfee,$chkUser[0]['email'],$chkUser[0]['customer_card_id'],$defaultInvoice,$transferGroup,$_leadspeek_api_id,$_agency_name,$_client_name,$_campaign_name,$chkUser[0]['company_root_id']);
                $payment_intent = (isset($resultCharge['payment_intent'])) ? $resultCharge['payment_intent'] : '';
                $statusPayment = (isset($resultCharge['statusPayment'])) ? $resultCharge['statusPayment'] : '';
                $errorstripe = (isset($resultCharge['errorstripe'])) ? $resultCharge['errorstripe'] : '';
                // info('charge pakai stripe karena bukan prepaid atau balance 0', ['resultCharge' => $resultCharge,]);
            }
            /** CHARGE WITH STRIPE */

            /* PROCESS TRANSFER COMMISSION SALES */
            // info('PROCESS TRANSFER COMMISSION SALES', ['statusPayment' => $statusPayment, 'chargeStatusWithPrepaid' => $chargeStatusWithPrepaid, 'topupAgencyExists' => $topupAgencyExists, 'applicationFeeAmount' => $applicationFeeAmount]);
            if(($statusPayment != 'failed' && $chargeStatusWithPrepaid != 'failed') && (!$topupAgencyExists || $applicationFeeAmount > 0) && ($_campaign_type != 'simplifi'))
            {
                $amount = $platformfee;
                // info('amount before', ['amount' => $amount, 'platformfee' => $platformfee, 'applicationFeeAmount' => $applicationFeeAmount]);
                if($topupAgencyExists && $applicationFeeAmount > 0)
                    $amount = $applicationFeeAmount;
                // info('amount after', ['amount' => $amount, 'platformfee' => $platformfee, 'applicationFeeAmount' => $applicationFeeAmount]);

                $sourceTransaction = ""; 
                if(is_object($payment_intent) && isset($payment_intent->charges->data[0]->id) && $payment_intent->charges->data[0]->id != '') 
                    $sourceTransaction = $payment_intent->charges->data[0]->id;
                // info('', ['sourceTransaction' => $sourceTransaction]);

                $transferCommissionSales = $this->transfer_commission_sales($companyParentID,$amount,$_leadspeek_api_id,$startdate,$enddate,$stripeseckey,$amount,"",$dataCustomCommissionSales,$transferGroup,$sourceTransaction);
                $transferCommissionSales = json_decode($transferCommissionSales);
                $srID = $transferCommissionSales->srID ?? 0;
                $aeID = $transferCommissionSales->aeID ?? 0;
                $arID = $transferCommissionSales->arID ?? 0;
                $srFee = $transferCommissionSales->srFee ?? 0;
                $aeFee = $transferCommissionSales->aeFee ?? 0;
                $arFee = $transferCommissionSales->arFee ?? 0;
                $srTransferID = $transferCommissionSales->srTransferID ?? '';
                $aeTransferID = $transferCommissionSales->aeTransferID ?? '';
                $arTransferID = $transferCommissionSales->arTransferID ?? '';
            }
            /* PROCESS TRANSFER COMMISSION SALES */

            /* CHECK IF AGENCY PAYMENT FAILED OR NOT FAILED */
            // Log::info(['payment_status _agency' => $statusPayment,'failed_total_amount' => $platformfee]);
            if($statusPayment == 'failed') 
            {
                $idUsr = $chkUser[0]['id'];

                $leadsuser = LeadspeekUser::select('leadspeek_users.leadspeek_type','leadspeek_users.active','leadspeek_users.disabled','leadspeek_users.trysera','leadspeek_users.leadspeek_organizationid','leadspeek_users.leadspeek_campaignsid','users.customer_payment_id','leadspeek_users.user_id','users.company_id')
                                          ->join('users','leadspeek_users.user_id','=','users.id')
                                          ->where('leadspeek_users.leadspeek_api_id','=',$_leadspeek_api_id)
                                          ->get();
                if(count($leadsuser) > 0)
                {
                    foreach($leadsuser as $lds) 
                    {

                        if(!($lds['active'] == "F" && $lds['disabled'] == "T" && $lds['active_user'] == "F")) 
                        {
                            /** UPDATE USER CARD STATUS */
                            $updateUser = User::find($idUsr);

                            $failedTotalAmount = $platformfee;
                            if($_campaign_paymentterm != 'Prepaid' && $topupAgencyExists && $remainingPlatformfee > 0)
                            {
                                $failedTotalAmount = $remainingPlatformfee;
                            }

                            $failedCampaignID = $_leadspeek_api_id;
                            
                            if(trim($updateUser->failed_total_amount) != '') 
                            {
                                $failedTotalAmount = $updateUser->failed_total_amount . '|' . $failedTotalAmount;
                            }
                            if(trim($updateUser->failed_campaignid) != '') 
                            {
                                $failedCampaignID = $updateUser->failed_campaignid . '|' . $failedCampaignID;
                            }

                            // info('failed 1.1', ['topupAgencyExists' => $topupAgencyExists, 'remainingPlatformfee' => $remainingPlatformfee, '_campaign_paymentterm' => $_campaign_paymentterm]);
                            // (jika tidak pakai topup agency) || (pakai topup agency && masih ada sisa platformfee && paymentterm selain prepaid)
                            if(($_campaign_paymentterm != 'Prepaid') && ((!$topupAgencyExists) || ($topupAgencyExists && $remainingPlatformfee > 0)))
                            {
                                // change status to pause
                                LeadspeekUser::where('leadspeek_api_id','=',$_leadspeek_api_id)
                                             ->update([
                                                'active' => 'F',
                                                'disabled' => 'T',
                                                'active_user' => 'T',
                                                'last_lead_pause' => date('Y-m-d H:i:s')
                                             ]);

                                $organizationid = ($lds['leadspeek_organizationid'] != "") ? $lds['leadspeek_organizationid'] : "";
                                $campaignsid = ($lds['leadspeek_campaignsid'] != "") ? $lds['leadspeek_campaignsid'] : "";
                                if($organizationid != '' && $campaignsid != '' && $lds['leadspeek_type'] == 'locator') 
                                {
                                    $marketingController = App::make(MarketingController::class);
                                    $camp = $marketingController->startPause_campaign($organizationid,$campaignsid,'pause');
                                    if($camp != true)
                                    {
                                        /** SEND EMAIL TO ME */
                                        $details = [
                                            'errormsg'  => 'Simpli.Fi Error Leadspeek ID :' . $_leadspeek_api_id. '<br/>',
                                        ];
                                        $from = [
                                            'address' => 'noreply@sitesettingsapi.com',
                                            'name' => 'support',
                                            'replyto' => 'noreply@sitesettingsapi.com',
                                        ];
                                        $this->send_email(array('serverlogs@sitesettingsapi.com'),'Start Pause Campaign Failed (INTERNAL - CronAPI-due the payment failed - L2197) #' .$_leadspeek_api_id,$details,array(),'emails.tryseramatcherrorlog',$from,'');
                                        /** SEND EMAIL TO ME */
                                    }
                                }
                                // change status to pause

                                $updateUser->failed_total_amount = $failedTotalAmount;
                                $updateUser->failed_campaignid = $failedCampaignID; 
                            }
                            $updateUser->payment_status = 'failed';
                            $updateUser->save();
                            /** UPDATE USER CARD STATUS */
                        }
                    }
                }

                // pakai agency prepaid && chargeCampaignWithPrepaid = 'paid'
                if($topupAgencyExists && $chargeStatusWithPrepaid == 'paid')
                {
                    $statusPayment = 'paid';
                }
                // info('check statusPayment final', ['statusPayment' => $statusPayment, 'agencyManualBill' => $agencyManualBill,'topupAgencyExists' => $topupAgencyExists,'chargeStatusWithPrepaid' => $chargeStatusWithPrepaid]);
            }
            else 
            {
                // clear payment status client
                $updateUser = User::where('id', $chkUser[0]['id'])->first();
                if($updateUser && empty($updateUser->failed_campaignid) && empty($updateUser->failed_total_amount))
                {
                    $updateUser->payment_status = '';
                    $updateUser->save();
                }
                // clear payment status client
            }
            /* CHECK IF AGENCY PAYMENT FAILED  OR NOT FAILED */

            $_paymentID = ($topupAgencyExists === true) ? "topup_agency" : ((isset($payment_intent->id)) ? $payment_intent->id : '');
            return json_encode([
                'result'=>'success',
                'payment_intentID'=>$_paymentID,
                'srID'=>$srID,
                'aeID'=>$aeID,
                'arID'=>$arID,
                'srFee'=>$srFee,
                'aeFee'=>$aeFee,
                'arFee'=>$arFee,
                'srTransferID'=>$srTransferID,
                'aeTransferID'=>$aeTransferID,
                'arTransferID'=>$arTransferID,
                'salesfee'=>$salesfee,
                'error'=>$errorstripe,
                'topup_agencies_id'=>$topup_agencies_id,
                'leadspeek_invoices_id'=>$leadspeek_invoices_id,
                'statusPayment'=>$statusPayment,
                'statusPaymentClient'=>$statusPaymentClient,
                'errorstripeClient'=>$errorstripeClient
            ]);
        }

        return json_encode([
            'result'=>'failed',
            'payment_intentID'=>'',
            'srID'=>0,
            'aeID'=>0,
            'arID'=>0,
            'srFee'=>0,
            'aeFee'=>0,
            'arFee'=>0,
            'srTransferID'=>0,
            'aeTransferID'=>0,
            'arTransferID'=>0,
            'salesfee'=>0,
            'error'=>'',
            'topup_agencies_id'=>null,
            'leadspeek_invoices_id'=>null,
            'statusPayment'=>'',
            'statusPaymentClient'=>'',
            'errorstripeClient'=>''
        ]);
    }

    public function process_charge_agency_wallet($companyParentID, $platformfee, $_campaign_paymentterm, &$topupAgencyExists, &$remainingPlatformfee, &$statusPayment, &$statusPaymentClient, &$errorstripeClient, &$topup_agencies_id, &$leadspeek_invoices_id, &$chargeStatusWithPrepaid, &$applicationFeeAmount, $_agency_stop_continual, $_agency_amount, $_agency_custom_amount, $_agency_payment_type, $_agency_ip_user, $_agency_timezone, $_agency_balance_threshold, $stripeseckey, $chkUser, $defaultInvoice, $transferGroup, $_leadspeek_api_id, $_agency_name, $_client_name, $_campaign_name, $dataChargeClient)
    {
        // info(__FUNCTION__);
        // info('start db::transaction');
        DB::transaction(function () use ($companyParentID, $platformfee, $_campaign_paymentterm, &$topupAgencyExists, &$remainingPlatformfee, &$statusPayment, &$statusPaymentClient, &$errorstripeClient, &$topup_agencies_id, &$leadspeek_invoices_id, &$chargeStatusWithPrepaid, &$applicationFeeAmount, $_agency_stop_continual, $_agency_amount, $_agency_custom_amount, $_agency_payment_type, $_agency_ip_user, $_agency_timezone, $_agency_balance_threshold, $stripeseckey, $chkUser, $defaultInvoice, $transferGroup, $_leadspeek_api_id, $_agency_name, $_client_name, $_campaign_name, $dataChargeClient) {
            /* GET BALANCE AMOUNT */
            $topupAgency = TopupAgency::select('balance_amount','topup_status')
                                      ->where('company_id','=',$companyParentID)
                                      ->where('topup_status','<>','done')
                                      ->whereNull('expired_at')
                                      ->lockForUpdate()
                                      ->orderBy('id','asc')
                                      ->get();
            $balanceAmount = $topupAgency->sum('balance_amount');
            $autoTopupWalletinvoiceID = "";
            $autoTopupWalletinvoiceSecondID = "";
            $leadspeek_invoices_id_buffer = "";
            $chargeType = isset($dataChargeClient['charge_type']) ? $dataChargeClient['charge_type'] : '';
            // info(['balanceAmount' => $balanceAmount]);
            // info(['_leadspeek_api_id' => $_leadspeek_api_id]);
            /* GET BALANCE AMOUNT */

            /* CHECK FOR AVAILABLE FEATURE STOPCONTINUAL OR NOT */
            $agencyManualBill = Company::where('id', $companyParentID)
                                       ->value('manual_bill');
            $agency = User::where('company_id', $companyParentID)
                          ->where('user_type', 'userdownline')
                          ->where('active', 'T')
                          ->first();
            $agencyID = isset($agency->id) ? $agency->id : '';
            $agencyAgreeDeveloper = isset($agency->is_marketing_services_agreement_developer) ? $agency->is_marketing_services_agreement_developer : '';
            $isEnableTopupPrepaidDirectPayment = (($agencyManualBill == 'T') || ($agencyAgreeDeveloper == 'T'));
            /* CHECK FOR AVAILABLE FEATURE STOPCONTINUAL OR NOT */

            /* GET SERVICE AGREEMENT DATA WALLET */
            $featureDataWalletId = MasterFeature::where('slug', 'data_wallet')
                                                ->value('id');
            $serviceAgreement = ServicesAgreement::where('user_id','=',$agencyID)
                                                 ->where('feature_id','=',$featureDataWalletId)
                                                 ->first();
            $agencyAgreeDataWallet = (isset($serviceAgreement->status) && $serviceAgreement->status == 'T') ? $serviceAgreement->status : 'F';
            // info(['balanceAmount' => $balanceAmount, 'agencyAgreeDataWallet' => $agencyAgreeDataWallet]);
            /* GET SERVICE AGREEMENT DATA WALLET */

            /* PROCESS CHARGE */
            // if($balanceAmount > 0 && $agencyAgreeDataWallet == 'T' && ($chargeType != 'cleanid' || ($chargeType == 'cleanid' && $balanceAmount >= $platformfee))) // balance harus di atas 0 dan harus agree data wallet
            if($balanceAmount > 0 && $agencyAgreeDataWallet == 'T') // balance harus di atas 0 dan harus agree data wallet
            {
                // info('pakai agency topup'); 

                // If the agency manual bill 'F' and wallet are less, then take all the wallet data and calculate the remainder
                $amountClient = isset($dataChargeClient['amount']) ? $dataChargeClient['amount'] : 0;
                // info('block 0.1', ['applicationFeeAmount' => $applicationFeeAmount, 'agencyManualBill' => $agencyManualBill, '_agency_stop_continual' => $_agency_stop_continual, 'balanceAmount' => $balanceAmount, 'platformfee' => $platformfee, 'amountClient' => $amountClient, 'dataChargeClient' => $dataChargeClient]);
                if($agencyManualBill == 'F' && ($_agency_stop_continual == 'T' || !$isEnableTopupPrepaidDirectPayment || $_agency_amount <= 0) && $balanceAmount < $platformfee && $amountClient >= 0.5 && $chargeType != 'cleanid')
                {
                    $applicationFeeAmount = $platformfee - $balanceAmount;
                    $platformfee = $balanceAmount;
                    // info('block 0.2', ['platformfee' => $platformfee, 'applicationFeeAmount' => $applicationFeeAmount]);
                }
                // If the agency manual bill 'F' and wallet are less, then take all the wallet data and calculate the remainder


                // Process Charge With Data Wallet
                // info('block 1.1');
                $topupAgencyExists = true;
                $topupAgencyProgressBuffer = [];
                $topupAgencyQueueBuffer = [];
                if($balanceAmount >= $platformfee) // jika balance amount lebih dari platformfee
                {
                    // update balance amount
                    $topupAgencyProgress = TopupAgency::where('company_id','=',$companyParentID)
                                                      ->where('topup_status','=','progress')
                                                      ->whereNull('expired_at')
                                                      ->first();
                    $topupAgencyProgressBuffer = clone $topupAgencyProgress;
                    // info('block 2.1', ['topupAgencyProgress' => $topupAgencyProgress]);
                    if(!empty($topupAgencyProgress)) 
                    {
                        // info('block 2.2');
                        if($topupAgencyProgress->balance_amount >= $platformfee) // jika balance_amount lebih atau sama dengan platformfee, maka kurangi dengan normal
                        {
                            // info('block 2.3');
                            // if($chargeType == 'cleanid')
                            // {
                            //     info(['topupAgencyProgress->id' => $topupAgencyProgress->id]);
                            //     $this->process_booking_wallet_cleanid($file_id, $topupAgencyProgress->id, $cost_cleanid, $platformfee);
                            // }
                            $topupAgencyProgress->balance_amount -= $platformfee;
                            $topupAgencyProgress->balance_amount = (float) number_format($topupAgencyProgress->balance_amount,2,'.','');
                        }
                        else // jika balance amount lebih kecil dari platformfee, tetapi ada topup yang queue, maka ubah balance_amount menjadi 0 karena pasti minus, lalu kurangi lagi dengan yang masih queue
                        {
                            // info('block 2.4');
                            // if($chargeType == 'cleanid')
                            // {
                            //     info(['topupAgencyProgress->id' => $topupAgencyProgress->id]);
                            //     $this->process_booking_wallet_cleanid($file_id, $topupAgencyProgress->id, $cost_cleanid, $topupAgencyProgress->balance_amount);
                            // }
                            $remainingPlatformfee = $platformfee - $topupAgencyProgress->balance_amount;
                            $remainingPlatformfee = (float) number_format($remainingPlatformfee,2,'.','');
                            $topupAgencyProgress->balance_amount = 0;
                        }

                        if($topupAgencyProgress->balance_amount <= 0) 
                        {
                            // info('block 2.5');
                            $topupAgencyProgress->topup_status = 'done';
                        }

                        $topupAgencyProgress->save();
                        $topup_agencies_id = $topupAgencyProgress->id;
                    }

                    // info(['remainingPlatformfee luar' => $remainingPlatformfee]);
                    if($remainingPlatformfee > 0) 
                    {
                        $topupAgencyQueue = TopupAgency::where('company_id','=',$companyParentID)
                                                       ->where('topup_status','=','queue')
                                                       ->whereNull('expired_at')
                                                       ->orderBy('id','asc')
                                                       ->get();
                        $topupAgencyQueueBuffer = $topupAgencyQueue->map(fn($item) => clone $item);
                        // info('block 2.6', ['topupAgencyQueue' => $topupAgencyQueue]);
                        foreach($topupAgencyQueue as $item)
                        {
                            // info('block 2.7', ['balanceAmount dalam' => $item->balance_amount, 'remainingPlatformfee dalam' => $remainingPlatformfee]);
                            $topup_agencies_id = $item->id;
                            $item->topup_status = 'progress';
                            $item->save();

                            if($item->balance_amount >= $remainingPlatformfee)
                            {
                                // info('block 2.8');
                                // if($chargeType == 'cleanid')
                                // {
                                //     $this->process_booking_wallet_cleanid($file_id, $topup_agencies_id, $cost_cleanid, $remainingPlatformfee);
                                // }

                                $diffBalanceAmount = $item->balance_amount - $remainingPlatformfee;  
                                $diffBalanceAmount = (float) number_format($diffBalanceAmount,2,'.','');
                                $remainingPlatformfee = 0;
                                
                                if($diffBalanceAmount <= 0) 
                                {
                                    // info('block 2.9');
                                    $item->topup_status = 'done';
                                }
                                $item->balance_amount = $diffBalanceAmount;
                                $item->save();
                                break;
                            }
                            else
                            {
                                // info('block 2.10');
                                // if($chargeType == 'cleanid')
                                // {
                                //     $this->process_booking_wallet_cleanid($file_id, $topup_agencies_id, $cost_cleanid, $item->balance_amount);
                                // }

                                $remainingPlatformfee -= $item->balance_amount;
                                $remainingPlatformfee = (float) number_format($remainingPlatformfee,2,'.','');
                                $item->balance_amount = 0;
                                $item->topup_status = 'done';
                                $item->save();
                            }
                        }
                    }
                    // update balance amount

                    // create leadspeek invoice
                    // info('buat invoice in if');
                    $invoiceCreated = LeadspeekInvoice::create([
                        'topup_agencies_id' => $topup_agencies_id,
                        'company_id' => 0,
                        'user_id' => 0,
                        'leadspeek_api_id' => $_leadspeek_api_id,
                        'invoice_number' => '',
                        'payment_term' => '',
                        'onetimefee' => 0,
                        'platform_onetimefee' => 0,
                        'min_leads' => 0,
                        'exceed_leads' => 0,
                        'total_leads' => 0,
                        'min_cost' => 0,
                        'platform_min_cost' => 0,
                        'cost_leads' => 0,
                        'platform_cost_leads' => 0,
                        'total_amount' => 0,
                        'platform_total_amount' => $platformfee,
                        'root_total_amount' => 0,
                        'status' => 'pending',
                        'customer_payment_id' => '',
                        'platform_customer_payment_id' => '',
                        'error_payment' => '',
                        'platform_error_payment' => '',
                        'invoice_date' => date('Y-m-d'),
                        'invoice_start' => date('Y-m-d'),
                        'invoice_end' => date('Y-m-d'),
                        'sent_to' => '',
                        'sr_id' => 0,
                        'sr_fee' => 0,
                        'ae_id' => 0,
                        'ae_fee' => 0,
                        'ar_id' => 0,
                        'ar_fee' => 0,
                        'active' => 'T',
                    ]);
                    $leadspeek_invoices_id = $invoiceCreated->id;
                    $leadspeek_invoices_id_buffer = $leadspeek_invoices_id;
                    // create leadspeek invoice

                    // check auto topup if stopcontinual 'F'
                    $chargeStatusWithPrepaid = "paid";
                    $statusPayment = 'paid';
                    if($_agency_stop_continual == 'F' && $isEnableTopupPrepaidDirectPayment && $_agency_amount > 0)
                    {
                        $currentBalanceAmount = TopupAgency::where('company_id','=',$companyParentID)
                                                           ->where('topup_status','<>','done')
                                                           ->whereNull('expired_at')
                                                           ->orderBy('id','asc')
                                                           ->sum('balance_amount');
                        // info('block 2.13', ['currentBalanceAmount' => $currentBalanceAmount,'_agency_balance_threshold' => $_agency_balance_threshold]);
                        if($currentBalanceAmount < $_agency_balance_threshold)
                        {
                            // info('block 2.14');
                            $companyIDParam = $companyParentID;
                            $amountParam = $_agency_amount;
                            $stopContinualParam = ($_agency_stop_continual == 'F') ? false : true;
                            $customAmountParam = ($_agency_custom_amount == 'F') ? false : true;
                            $ipUserParam = $_agency_ip_user;
                            $timezoneParam = $_agency_timezone;
                            $paymentTypeParam = $_agency_payment_type; 
                            $is_from_auto_topup = true; // parameter ini untuk memberitahu bahwa charge ini untuk auto topup data wallet
                            $leadspeek_api_id_data_wallet = implode("|", array_filter([$_leadspeek_api_id, $leadspeek_invoices_id_buffer])); // format nya jika invoice id ada "{$leadspeek_api_id}|{$leadspeek_invoices_id}" atau jika invoice id tidak ada "{$leadspeek_api_id}", // parameter ini yang menentukan data wallet ini di charge manual atau dari auto topup campaign

                            try 
                            {
                                $charge = $this->chargePrepaidDirectPayment($companyIDParam,$amountParam,$stopContinualParam,$customAmountParam,$ipUserParam,$timezoneParam,$paymentTypeParam,$is_from_auto_topup,$leadspeek_api_id_data_wallet);
                                $statusPayment = (isset($charge['result']) && $charge['result'] == 'failed') ? 'failed' : 'paid';
                                $autoTopupWalletinvoiceID = (isset($charge['invoiceID'])) ? $charge['invoiceID'] : '';
                                // info('block 2.15', ['statusPayment' => $statusPayment, 'charge' => $charge, 'autoTopupWalletinvoiceID' => $autoTopupWalletinvoiceID]);
                            }
                            catch (\Exception $e)
                            {
                                // info('block 2.16');
                                $statusPayment = 'failed';
                            }
                        }
                    }
                    // check auto topup if stopcontinual 'F'
                }
                else // jika balance amount kurang dari platformfee
                {
                    // info('block 3.1', ['_agency_stop_continual' => $_agency_stop_continual]);
                    $statusPayment = 'paid';

                    if($_agency_stop_continual == 'F' && $isEnableTopupPrepaidDirectPayment && $_agency_amount > 0)
                    {
                        // jika balance amount kurang dari platformfee, maka hitung selisihnya dan hitung berapa kali auto topup diperlukan, dan jika berapa kali auto topup ketemu maka langsung dikali select amount data wallet nya  
                        // example stop continual : false | balance data wallet : $100 | select amount : $250 | charge campaign : $1000 | karena butuh charge $250 sebanyak 4 kali, jadi dijadikan 1 kali charge -> ($100) + ($250 x 4) = $1100
                        $shortage = $platformfee - $balanceAmount; // hitung selisih yang kurang
                        $autoTopupCount = ceil($shortage / $_agency_amount);  // berapa kali auto topup yang diperlukan
                        $totalTopupAmount = $autoTopupCount * $_agency_amount; // jumlah yang akan di-charge tunggal
                        // jika balance amount kurang dari platformfee, maka hitung selisihnya dan hitung berapa kali auto topup diperlukan, dan jika berapa kali auto topup ketemu maka langsung dikali select amount data wallet nya  

                        for($i = 0; $i < 20; $i++) // max 20x, cara lama bakal looping, cara yang baru pasti bakal 1 kali saja
                        {
                            // info('block 3.2', ['platformfee' => $platformfee,'balanceAmount' => $balanceAmount,'_agency_amount' => $_agency_amount,'shortage' => $shortage,'autoTopupCount' => $autoTopupCount,'totalTopupAmount' => $totalTopupAmount,]);

                            $companyIDParam = $companyParentID;
                            $amountParam = $totalTopupAmount; // $_agency_amount;
                            $stopContinualParam = ($_agency_stop_continual == 'F') ? false : true;
                            $customAmountParam = ($_agency_custom_amount == 'F') ? false : true;
                            $ipUserParam = $_agency_ip_user;
                            $timezoneParam = $_agency_timezone;
                            $paymentTypeParam = $_agency_payment_type;
                            $is_from_auto_topup = true; // parameter ini untuk memberitahu bahwa charge ini untuk auto topup data wallet
                            $leadspeek_api_id_data_wallet = implode("|", array_filter([$_leadspeek_api_id])); // format nya "{$leadspeek_api_id}", // parameter ini yang menentukan data wallet ini di charge manual atau dari auto topup campaign

                            try 
                            {
                                $charge = $this->chargePrepaidDirectPayment($companyIDParam,$amountParam,$stopContinualParam,$customAmountParam,$ipUserParam,$timezoneParam,$paymentTypeParam,$is_from_auto_topup,$leadspeek_api_id_data_wallet);
                                $statusPayment = (isset($charge['result']) && $charge['result'] == 'failed') ? 'failed' : 'paid';
                                $autoTopupWalletinvoiceID = (isset($charge['invoiceID'])) ? $charge['invoiceID'] : '';
                                // info('block 3.3', ['statusPayment' => $statusPayment, 'charge' => $charge, 'autoTopupWalletinvoiceID' => $autoTopupWalletinvoiceID]);
                            }
                            catch (\Exception $e)
                            {
                                // info('block 3.4');
                                $statusPayment = 'failed';
                            }
                            
                            if($statusPayment == 'failed') 
                                break;

                            $currentBalanceAmount = TopupAgency::where('company_id','=',$companyParentID)
                                                               ->where('topup_status','<>','done')
                                                               ->whereNull('expired_at')
                                                               ->orderBy('id','asc')
                                                               ->sum('balance_amount');
                            if($currentBalanceAmount < $platformfee)
                                continue;

                            break;
                        }
                    }

                    if($_campaign_paymentterm != 'Prepaid' || ($_campaign_paymentterm == 'Prepaid' && $statusPayment == 'paid'))
                    {
                        $topupAgencyProgress = TopupAgency::where('company_id','=',$companyParentID)
                                                          ->where('topup_status','=','progress')
                                                          ->whereNull('expired_at')
                                                          ->first();
                        $topupAgencyProgressBuffer = clone $topupAgencyProgress;
                        // info('block 3.5', ['topupAgencyProgress' => $topupAgencyProgress]);
                        if(!empty($topupAgencyProgress)) 
                        {
                            // info('block 3.6');
                            $remainingPlatformfee = $platformfee - $topupAgencyProgress->balance_amount;
                            $remainingPlatformfee = (float) number_format($remainingPlatformfee,2,'.','');
                            
                            $topupAgencyProgress->balance_amount = 0;
                            $topupAgencyProgress->topup_status = 'done';
                            $topupAgencyProgress->save();
                            $topup_agencies_id = $topupAgencyProgress->id;
                        }

                        if($remainingPlatformfee > 0) 
                        {
                            // info('block 3.7');
                            $topupAgencyQueue = TopupAgency::where('company_id','=',$companyParentID)
                                                           ->where('topup_status','=','queue')
                                                           ->whereNull('expired_at')
                                                           ->orderBy('id','asc')
                                                           ->get();
                            $topupAgencyQueueBuffer = $topupAgencyQueue->map(fn($item) => clone $item);
                            
                            foreach($topupAgencyQueue as $item)
                            {
                                // info('block 3.8', ['balanceAmount dalam' => $item->balance_amount, 'remainingPlatformfee luar' => $remainingPlatformfee]);
                                $topup_agencies_id = $item->id;
                                $item->topup_status = 'progress';
                                $item->save();

                                if($item->balance_amount >= $remainingPlatformfee)
                                {
                                    // info('block 3.9');
                                    $diffBalanceAmount = $item->balance_amount - $remainingPlatformfee;  
                                    $diffBalanceAmount = (float) number_format($diffBalanceAmount,2,'.','');
                                    $remainingPlatformfee = 0;
                                    
                                    if($diffBalanceAmount <= 0) 
                                    {
                                        // info('block 3.10');
                                        $item->topup_status = 'done';
                                    }
                                    $item->balance_amount = $diffBalanceAmount;
                                    $item->save();
                                    break;
                                }
                                else
                                {
                                    // info('block 3.11');
                                    $remainingPlatformfee -= $item->balance_amount;
                                    $remainingPlatformfee = (float) number_format($remainingPlatformfee,2,'.','');
                                    $item->balance_amount = 0;
                                    $item->topup_status = 'done';
                                    $item->save();
                                }
                            }
                        }

                        if($statusPayment == 'paid') 
                        {
                            // info('block 3.12');
                            $chargeStatusWithPrepaid = "paid";
                        }

                        // info('buat invoice in else');
                        $invoiceCreated = LeadspeekInvoice::create([
                            'topup_agencies_id' => $topup_agencies_id,
                            'company_id' => 0,
                            'user_id' => 0,
                            'leadspeek_api_id' => $_leadspeek_api_id,
                            'invoice_number' => '',
                            'payment_term' => '',
                            'onetimefee' => 0,
                            'platform_onetimefee' => 0,
                            'min_leads' => 0,
                            'exceed_leads' => 0,
                            'total_leads' => 0,
                            'min_cost' => 0,
                            'platform_min_cost' => 0,
                            'cost_leads' => 0,
                            'platform_cost_leads' => 0,
                            'total_amount' => 0,
                            'platform_total_amount' => $platformfee,
                            'root_total_amount' => 0,
                            'status' => 'pending',
                            'customer_payment_id' => '',
                            'platform_customer_payment_id' => '',
                            'error_payment' => '',
                            'platform_error_payment' => '',
                            'invoice_date' => date('Y-m-d'),
                            'invoice_start' => date('Y-m-d'),
                            'invoice_end' => date('Y-m-d'),
                            'sent_to' => '',
                            'sr_id' => 0,
                            'sr_fee' => 0,
                            'ae_id' => 0,
                            'ae_fee' => 0,
                            'ar_id' => 0,
                            'ar_fee' => 0,
                            'active' => 'T',
                        ]);
                        $leadspeek_invoices_id = $invoiceCreated->id;
                        $leadspeek_invoices_id_buffer = $leadspeek_invoices_id;

                        // info('update invoice auto topup wallet agency');
                        $this->appendInvoiceIdToLeadspeekApiIdForRecordDataWallet($autoTopupWalletinvoiceID,$leadspeek_invoices_id_buffer); // yang awalnya format nya hanya "{$leadspeek_api_id}", tambahkan invoice id nya "{$leadspeek_api_id}|{$leadspeek_invoices_id}"
                    }

                    // info(['remainingPlatformfee' => $remainingPlatformfee]);
                    if(($_agency_stop_continual == 'T' || !$isEnableTopupPrepaidDirectPayment || $_agency_amount <= 0) && $remainingPlatformfee > 0) // kondisi ini ketika stop continual 'T' dan agency manual bill 'T', karena jika agency manual bill 'F' akan masuk di wilayah 'Charge client and the remaining agent fees using the application fee amount'
                    {
                        // coba charge sisanya
                        $applicationFeeAmount = $remainingPlatformfee;
                        $resultCharge = $this->process_charge_agency_stripeinfo($stripeseckey,$chkUser[0]['customer_payment_id'],$remainingPlatformfee,$chkUser[0]['email'],$chkUser[0]['customer_card_id'],$defaultInvoice,$transferGroup,$_leadspeek_api_id,$_agency_name,$_client_name,$_campaign_name,$chkUser[0]['company_root_id']);
                        $statusPayment = (isset($resultCharge['statusPayment'])) ? $resultCharge['statusPayment'] : '';
                        $errorstripe = (isset($resultCharge['errorstripe'])) ? $resultCharge['errorstripe'] : '';
                        // info('block 3.13', ['stripeseckey' => $stripeseckey,'customer_payment_id' => $chkUser[0]['customer_payment_id'],'remainingPlatformfee' => $remainingPlatformfee,'email' => $chkUser[0]['email'],'customer_card_id' => $chkUser[0]['customer_card_id'],'defaultInvoice' => $defaultInvoice,'transferGroup' => $transferGroup,'leadspeek_api_id' => $_leadspeek_api_id,'agency_name' => $_agency_name,'client_name' => $_client_name,'campaign_name' => $_campaign_name,'company_root_id'=>$chkUser[0]['company_root_id'],'statusPayment'=>$statusPayment,'errorstripe'=>$errorstripe]);
                        
                        // jika error revert ulang topupnya untuk Prepaid
                        if($statusPayment == 'failed')
                        {
                            // info('block 3.14');
                            $chargeStatusWithPrepaid = "failed";

                            if($_campaign_paymentterm == 'Prepaid')
                            {
                                // info('block 3.15', ['topupAgencyProgressBuffer'=>$topupAgencyProgressBuffer]);
                                if(!empty($topupAgencyProgressBuffer)) 
                                {
                                    // info('block 3.16');
                                    $topup_id = $topupAgencyProgressBuffer->id;
                                    $topup_status = $topupAgencyProgressBuffer->topup_status;
                                    $topup_balance_amount = $topupAgencyProgressBuffer->balance_amount;
                                    TopupAgency::where('id','=',$topup_id)
                                               ->update([
                                                    'topup_status' => $topup_status,
                                                    'balance_amount' => $topup_balance_amount
                                               ]);
                                }

                                // info('block 3.17', ['topupAgencyQueueBuffer' => $topupAgencyQueueBuffer]);
                                if(!empty($topupAgencyQueueBuffer))
                                {
                                    foreach($topupAgencyQueueBuffer as $item)
                                    {
                                        // info('block 3.18');
                                        $topup_id = $item->id;
                                        $topup_status = $item->topup_status;
                                        $topup_balance_amount = $item->balance_amount;
                                        TopupAgency::where('id','=',$topup_id)
                                                   ->update([
                                                        'topup_status' => $topup_status,
                                                        'balance_amount' => $topup_balance_amount
                                                   ]);
                                    }
                                }

                                // remove leadspeekinvoice
                                LeadspeekInvoice::where('id','=',$leadspeek_invoices_id)->delete();
                                $leadspeek_invoices_id = null;
                                // remove leadspeekinvoice
                            }
                        }
                    }

                    if($_agency_stop_continual == 'F' && $isEnableTopupPrepaidDirectPayment && $_agency_amount > 0 && $statusPayment == 'paid')
                    {
                        $currentBalanceAmount = TopupAgency::where('company_id','=',$companyParentID)
                                                           ->where('topup_status','<>','done')
                                                           ->whereNull('expired_at')
                                                           ->orderBy('id','asc')
                                                           ->sum('balance_amount');

                        $agency = User::select('last_balance_amount','amount')
                                      ->where('company_id','=',$companyParentID)
                                      ->where('active','=','T')
                                      ->where('user_type','=','userdownline')
                                      ->first();

                        // $_agency_last_balance_amount = (isset($agency->last_balance_amount)) ? $agency->last_balance_amount : 0; // dulu 10% auto topup data wallet itu acuan dari last_balance_amount
                        $_agency_last_balance_amount = (isset($agency->amount)) ? $agency->amount : 0; // sekarang 10% auto topup data wallet acuan nya dari amount yang di setup
                        $_agency_balance_threshold = ($_agency_last_balance_amount * 10 / 100);
                        $_agency_balance_threshold = (float) number_format($_agency_balance_threshold,2,'.','');
                        // info('block 3.19', ['currentBalanceAmount' => $currentBalanceAmount,'_agency_balance_threshold' => $_agency_balance_threshold]);
                        
                        if($currentBalanceAmount < $_agency_balance_threshold)
                        {
                            // info('block 3.20');

                            $companyIDParam = $companyParentID;
                            $amountParam = $_agency_amount;
                            $stopContinualParam = ($_agency_stop_continual == 'F') ? false : true;
                            $customAmountParam = ($_agency_custom_amount == 'F') ? false : true;
                            $ipUserParam = $_agency_ip_user;
                            $timezoneParam = $_agency_timezone;
                            $paymentTypeParam = $_agency_payment_type;
                            $is_from_auto_topup = true; // parameter ini untuk memberitahu bahwa charge ini untuk auto topup data wallet
                            $leadspeek_api_id_data_wallet = implode("|", array_filter([$_leadspeek_api_id, $leadspeek_invoices_id_buffer])); // format nya jika invoice id ada "{$leadspeek_api_id}|{$leadspeek_invoices_id}" atau jika invoice id tidak ada "{$leadspeek_api_id}", parameter ini yang menentukan data wallet ini di charge manual atau dari auto topup campaign

                            try 
                            {
                                $charge = $this->chargePrepaidDirectPayment($companyIDParam,$amountParam,$stopContinualParam,$customAmountParam,$ipUserParam,$timezoneParam,$paymentTypeParam,$is_from_auto_topup,$leadspeek_api_id_data_wallet);
                                $statusPayment = (isset($charge['result']) && $charge['result'] == 'failed') ? 'failed' : 'paid';
                                $autoTopupWalletinvoiceSecondID = (isset($charge['invoiceID'])) ? $charge['invoiceID'] : '';
                                // info('block 3.21', ['statusPayment' => $statusPayment, 'charge' => $charge, 'autoTopupWalletinvoiceID' => $autoTopupWalletinvoiceID]);
                            }
                            catch (\Exception $e)
                            {
                                // info('block 3.22');
                                $statusPayment = 'failed';
                            }
                        }
                    }
                }
                // Process Charge With Data Wallet


                // Charge client and the remaining agent fees using the application fee amount
                // info(['agencyManualBill' => $agencyManualBill, 'amountClient' => $amountClient, 'chargeStatusWithPrepaid' => $chargeStatusWithPrepaid]);
                if($agencyManualBill == 'F' && $amountClient >= 0.5 && $chargeStatusWithPrepaid == 'paid' && $chargeType != 'cleanid')
                {
                    // info('block 4.1', ['dataChargeClient' => $dataChargeClient]);
                    $stripeseckey = isset($dataChargeClient['stripeseckey'])?$dataChargeClient['stripeseckey']:'';
                    $accConID = isset($dataChargeClient['accConID'])?$dataChargeClient['accConID']:'';
                    $customer = isset($dataChargeClient['customer'])?$dataChargeClient['customer']:'';
                    $amount = isset($dataChargeClient['amount'])?$dataChargeClient['amount']:'';
                    $receipt_email = isset($dataChargeClient['receipt_email'])?$dataChargeClient['receipt_email']:'';
                    $payment_method = isset($dataChargeClient['payment_method'])?$dataChargeClient['payment_method']:'';
                    $description = isset($dataChargeClient['description'])?$dataChargeClient['description']:'';
                    $charge_client = $this->charge_client_with_app_fee_amount($stripeseckey, $accConID, $_leadspeek_api_id, $customer, $amount, $receipt_email, $payment_method, $description, $applicationFeeAmount);
                    // info('block 4.2', ['charge_client' => $charge_client]);

                    // info(['status' => $charge_client['status']]);
                    if($charge_client['status'] == 'failed') // revert topup data wallet
                    {
                        // info('block 4.3 failed');
                        $statusPaymentClient = "failed";
                        $errorstripeClient = $charge_client['message'];
                        $chargeStatusWithPrepaid = 'failed';

                        // info('block 4.4', ['topupAgencyProgressBuffer'=>$topupAgencyProgressBuffer]);
                        if(!empty($topupAgencyProgressBuffer)) 
                        {
                            // info('block 4.5');
                            $topup_id = $topupAgencyProgressBuffer->id;
                            $topup_status = $topupAgencyProgressBuffer->topup_status;
                            $topup_balance_amount = $topupAgencyProgressBuffer->balance_amount;
                            TopupAgency::where('id','=',$topup_id)
                                       ->update([
                                            'topup_status' => $topup_status,
                                            'balance_amount' => $topup_balance_amount
                                       ]);
                        }

                        // info('block 4.6', ['topupAgencyQueueBuffer' => $topupAgencyQueueBuffer]);
                        if(!empty($topupAgencyQueueBuffer))
                        {
                            foreach($topupAgencyQueueBuffer as $item)
                            {
                                // info('block 4.7');
                                $topup_id = $item->id;
                                $topup_status = $item->topup_status;
                                $topup_balance_amount = $item->balance_amount;
                                TopupAgency::where('id','=',$topup_id)
                                           ->update([
                                                'topup_status' => $topup_status,
                                                'balance_amount' => $topup_balance_amount
                                           ]);
                            }
                        }

                        // remove leadspeekinvoice
                        LeadspeekInvoice::where('id','=',$leadspeek_invoices_id)->delete();
                        $leadspeek_invoices_id = null;
                        // remove leadspeekinvoice

                        // remove leadspeekinvoiceid untuk record auto topup dari campaign dan invoice campaign mana
                        $this->cleanLeadspeekInvoiceIdForRecordDataWallet($autoTopupWalletinvoiceID);
                        $this->cleanLeadspeekInvoiceIdForRecordDataWallet($autoTopupWalletinvoiceSecondID);
                        // remove leadspeekinvoiceid untuk record auto topup dari campaign dan invoice campaign mana
                    }
                    else // update customer_payment_id to leadspeek_invoice
                    {
                        $payment_intent_id = isset($charge_client['payment_intent_id'])?$charge_client['payment_intent_id']:'';
                        LeadspeekInvoice::where('id', $leadspeek_invoices_id)->update(['customer_payment_id' => $payment_intent_id]);
                    }
                }
                // Charge client and the remaining agent fees using the application fee amount



                // If there is no progress, check again whether there is any queue
                $topupAgencyProgressExists = TopupAgency::where('company_id','=',$companyParentID)
                                                        ->where('topup_status','=','progress')
                                                        ->whereNull('expired_at')
                                                        ->exists();
                if(!$topupAgencyProgressExists)
                {
                    // info('block 5.1');
                    $topupAgencyQueue = TopupAgency::where('company_id','=',$companyParentID)
                                                   ->where('topup_status','=','queue')
                                                   ->whereNull('expired_at')
                                                   ->orderBy('created_at','asc')
                                                   ->first();
                    if(!empty($topupAgencyQueue)) 
                    {
                        // info('block 5.2');
                        $topupAgencyQueue->topup_status = 'progress';
                        $topupAgencyQueue->save();
                    }
                }
                // If there is no progress, check again whether there is any queue
            }
            /* PROCESS CHARGE */
        });
    }

    // function ini untuk menambahkan invoice id ke leadspeek_api_id_data_wallet, awalnya "{$leadspeek_api_id}" menjadi "{$leadspeek_api_id}|{$leadspeek_invoices_id}"
    public function appendInvoiceIdToLeadspeekApiIdForRecordDataWallet($invoiceId, $leadspeekInvoiceId)
    {
        // info(__FUNCTION__, ['invoiceId' => $invoiceId, 'leadspeekInvoiceId' => $leadspeekInvoiceId]);
        try
        {
            if (empty($invoiceId)) return;
        
            $invoice = LeadspeekInvoice::find($invoiceId);
            if (!$invoice) return;
        
            // pecah string menjadi array
            $parts = explode("|", (string) $invoice->leadspeek_api_id);
        
            // tambahkan invoice id ke belakang array
            $parts[] = $leadspeekInvoiceId;
        
            // satukan kembali
            $invoice->leadspeek_api_id = implode("|", $parts);
            $invoice->save();
        }
        catch(\Exception $e)
        {
            Log::error('appendInvoiceIdToLeadspeekApiIdForRecordDataWallet error: ' . $e->getMessage());
        }
    }

    // function ini untuk menghapus invoice id dari leadspeek_api_id_data_wallet, awalnya "{$leadspeek_api_id}|{$leadspeek_invoices_id}" menjadi "{$leadspeek_api_id}"
    public function cleanLeadspeekInvoiceIdForRecordDataWallet($invoiceId)
    {
        // info(__FUNCTION__, ['invoiceId' => $invoiceId]);
        try
        {
            if (!$invoiceId) return;
        
            $invoice = LeadspeekInvoice::find($invoiceId);
            if (!$invoice) return;
        
            $parts = explode("|", (string) $invoice->leadspeek_api_id);
        
            // Ambil hanya API ID bagian pertama
            $invoice->leadspeek_api_id = $parts[0] ?? '';
            $invoice->save();
        }
        catch(\Exception $e)
        {
            Log::error('cleanLeadspeekInvoiceIdForRecordDataWallet error: ' . $e->getMessage());
        }
    }

    public function charge_client_with_app_fee_amount($stripeseckey = "", $accConID = "", $leadspeek_api_id = "", $customer = "", $amount = 0, $receipt_email = "", $payment_method = "", $description = "", $application_fee_amount = 0)
    {
        try 
        {
            $stripe = new StripeClient([
                'api_key' => $stripeseckey,
                'stripe_version' => '2020-08-27'
            ]);

            $amount = number_format((float) $amount,2,'.','');
            $application_fee_amount = number_format((float) $application_fee_amount,2,'.','');
            $dataPaymentIntent = [
                'payment_method_types' => ['card'],
                'customer' => trim($customer),
                'amount' => ($amount * 100),
                'currency' => 'usd',
                'receipt_email' => $receipt_email,
                'payment_method' => $payment_method,
                'confirm' => true,
                'description' => $description,
                'application_fee_amount' => ($application_fee_amount * 100)
            ];
            // info('charge_client_with_app_fee_amount 1.1', ['dataPaymentIntent' => $dataPaymentIntent]);
            $payment_intent = $stripe->paymentIntents->create($dataPaymentIntent, ['stripe_account' => $accConID]);

            /* CHECK STATUS PAYMENT INTENTS */
            $payment_intent_status = (isset($payment_intent->status))?$payment_intent->status:"";
            $payment_intent_id = isset($payment_intent->id)?$payment_intent->id:'';
            if($payment_intent_status == 'requires_action') 
            {
                $errorstripe = "Payment for campaign $leadspeek_api_id was unsuccessful: Stripe status '$payment_intent_status' indicates further user action is needed.";
                // info('charge_client_with_app_fee_amount 1.2', ['errorStripe' => $errorstripe]);
                return ['status' => 'failed', 'message' => $errorstripe, 'payment_intent_id' => $payment_intent_id];
            }
            /* CHECK STATUS PAYMENT INTENTS */

            return ['status' => 'success', 'message' => 'Success Charge Client', 'payment_intent_id' => $payment_intent_id];
        }
        catch (RateLimitException $e) 
        {
            // Too many requests made to the API too quickly
            $errorstripe = $e->getHttpStatus() . '|' . $e->getError()->type . '|' . $e->getError()->code . '|' . $e->getError()->param . '|' . $e->getError()->message;
            // info('charge_client_with_app_fee_amount 1.3', ['errorStripe' => $errorstripe, 'message' => $e->getMessage()]);
            return ['status' => 'failed', 'message' => $errorstripe];
        } 
        catch (InvalidRequestException $e) 
        {
            // Invalid parameters were supplied to Stripe's API
            $errorstripe = $e->getHttpStatus() . '|' . $e->getError()->type . '|' . $e->getError()->code . '|' . $e->getError()->param . '|' . $e->getError()->message;
            // info('charge_client_with_app_fee_amount 1.4', ['errorStripe' => $errorstripe, 'message' => $e->getMessage()]);
            return ['status' => 'failed', 'message' => $errorstripe];
        } 
        catch (ExceptionAuthenticationException $e) 
        {
            // Authentication with Stripe's API failed
            // (maybe you changed API keys recently)
            $errorstripe = $e->getHttpStatus() . '|' . $e->getError()->type . '|' . $e->getError()->code . '|' . $e->getError()->param . '|' . $e->getError()->message;
            // info('charge_client_with_app_fee_amount 1.5', ['errorStripe' => $errorstripe, 'message' => $e->getMessage()]);
            return ['status' => 'failed', 'message' => $errorstripe];
        } 
        catch (ApiConnectionException $e) 
        {
            // Network communication with Stripe failed
            $errorstripe = $e->getHttpStatus() . '|' . $e->getError()->type . '|' . $e->getError()->code . '|' . $e->getError()->param . '|' . $e->getError()->message;
            // info('charge_client_with_app_fee_amount 1.6', ['errorStripe' => $errorstripe, 'message' => $e->getMessage()]);
            return ['status' => 'failed', 'message' => $errorstripe];
        } 
        catch (ApiErrorException $e) 
        {
            // Display a very generic error to the user, and maybe send
            // yourself an email
            $errorstripe = $e->getHttpStatus() . '|' . $e->getError()->type . '|' . $e->getError()->code . '|' . $e->getError()->param . '|' . $e->getError()->message;
            // info('charge_client_with_app_fee_amount 1.7', ['errorStripe' => $errorstripe, 'message' => $e->getMessage()]);
            return ['status' => 'failed', 'message' => $errorstripe];
        } 
        catch (Exception $e) 
        {
            // Something else happened, completely unrelated to Stripe
            $errorstripe = 'error not stripe things';
            // info('charge_client_with_app_fee_amount 1.8', ['errorStripe' => $errorstripe, 'message' => $e->getMessage()]);
            return ['status' => 'failed', 'message' => $errorstripe];
        }
    }

    public function process_booking_wallet_cleanid($file_id = "", $topup_agencies_id = "", $cost_cleanid = 0, $total_amount = 0)
    {
        // id	file_id	topup_agencies_id	total_amount	balance_amount	created_at	updated_at
        // info(__FUNCTION__, ['file_id' => $file_id, 'topup_agencies_id' => $topup_agencies_id, 'cost_cleanid' => $cost_cleanid, 'total_amount' => $total_amount]);
        $topupCleanId = TopupCleanId::create([
            'file_id' => $file_id,
            'topup_agencies_id' => $topup_agencies_id,
            'cost_cleanid' => $cost_cleanid,
            'total_amount' => $total_amount,
            'balance_amount' => $total_amount,
            'topup_status' => 'queue',
        ]);
        // info(__FUNCTION__, ['topupCleanId' => $topupCleanId]);
    }

    public function searchInJSON($json, $searchKey) {
        foreach ($json as $key => $value) {
            if ($key === $searchKey) {
                return $value;
            }
            if (is_array($value) || is_object($value)) {
                $result = $this->searchInJSON($value, $searchKey);
                if ($result !== null) {
                    return $result;
                }
            }
        }
        return null;
    }
    
    public function send_email($sentTo = array(),$title='',$details = array(),$attachment = array() ,$emailtemplate = '',$from = array(),$companyID = '',$isQueue = false,$isHtmlTemplate = false) 
    {
        /* IF DISABLED EMAIL */
        $emailDisabled = config('services.email.disabled');
        if($emailDisabled === true) 
        {
            return;
        }
        /* IF DISABLED EMAIL */

        if($isQueue === true) 
        {
            SendEmailJob::dispatch(
                $sentTo,
                $title,
                $details,
                $attachment,
                $emailtemplate,
                $from,
                $companyID,
                $isHtmlTemplate,
            )->onQueue('send_email');
            return;
        }

        $companysetting = "";
        $smtpusername = "";
        $AdminDefaultSMTP = "";
        $AdminDefaultSMTPEmail = "";
        
        if ($companyID != "") 
        {
            $getcompanysetting = CompanySetting::where('company_id',$companyID)->whereEncrypted('setting_name','customsmtpmenu')->get();
            $AdminDefaultSMTP = $this->get_default_admin($companyID);
            $AdminDefaultSMTPEmail = (isset($AdminDefaultSMTP[0]['email']))?$AdminDefaultSMTP[0]['email']:'';
        }

        if (count($from) == 0) 
        {
            $from = [
                'address' => 'noreply@sitesettingsapi.com',
                'name' => 'newleads',
                'replyto' => 'noreply@sitesettingsapi.com',
            ];
        }

        $chktemplate = false;
        // $templatecheck = array("emails.salesfee","emails.embeddedcodemissing","emails.tryseracrawlfailed","emails.tryseracrawlsuccess","emails.tryseraembeddedreminder","emails.tryseramatchlist","emails.tryseramatchlistcharge","emails.tryseramatchlistclient","emails.tryseramatchlistclientattach","emails.tryseramatchlistinvoice","emails.tryserastartstop");
        // if (in_array($emailtemplate,$templatecheck)) {
        //     $chktemplate = true;
        // }

        /** HACKED DANIEL SAID ALL EMAIL */
        $chktemplate = true;
        
        foreach($sentTo as $to) 
        {
            if(trim($to) != '' && strpos($to, "@") !== false) 
            {
                $smtpusername = "";

                /* CHECK USER INACTIVE */
                $user = "";
                if($companyID != '')
                {
                    $user = User::select('active')
                                ->whereEncrypted('email','=',trim($to))
                                ->where(function ($query) use ($companyID) {
                                    $query->where('company_id','=',$companyID)
                                          ->orWhere('company_parent','=',$companyID);
                                })
                                ->orderBy('created_at', 'desc')
                                ->first();
                }
                else
                {
                    $user = User::select('active')
                                ->whereEncrypted('email','=',trim($to))
                                ->orderBy('created_at', 'desc')
                                ->first();
                }

                // validasi hanya untuk user yang terdaftar di db, jika seperti serverlog tidak perlu divalidasi
                if(!empty($user)) 
                {
                    $userActive = isset($user->active) ? $user->active : 'F';
                    if($userActive == 'F')
                    {
                        continue;
                    }
                }
                /* CHECK USER INACTIVE */

                /** CHECK IF USER EMAIL IS DISABLED TO RECEIVED EMAIL */
                if ($emailtemplate != "" && $chktemplate) 
                {    
                    $chkdisabledemail = User::select('id')
                                            ->whereEncrypted('email','=',trim($to))
                                            ->where('active','=','T')
                                            ->where('disabled_receive_email','=','T')
                                            ->get();
                    if(count($chkdisabledemail) > 0) 
                    {
                        continue;
                    }
                }
                /** CHECK IF USER EMAIL IS DISABLED TO RECEIVED EMAIL */
                
                $statusSender = "";

                /** CHECK RECEPIENT IS CLIENT OR NOT */
                $isRecepientClient = false;
                $chkRecipient = User::select('id','user_type')
                                    ->whereEncrypted('email','=',trim($to))
                                    ->where('active','=','T')
                                    //->where('user_type','=','client')
                                    ->first();
                if ($chkRecipient) 
                {
                    if ($chkRecipient->user_type == "client") 
                    {
                        $isRecepientClient = true;
                    }
                }
                else
                {
                    $isRecepientClient = true;
                }
                /** CHECK RECEPIENT IS CLIENT OR NOT */
                try 
                {
                    try 
                    {
                        /** SET SMTP EMAIL */
                        if ($companyID != '') 
                        {
                            if (count($getcompanysetting) > 0) 
                            {
                                $companysetting = json_decode($getcompanysetting[0]['setting_value']);
                                if (!isset($companysetting->default)) 
                                {
                                    $companysetting->default = false;
                                }
                                if (!$companysetting->default) 
                                {
                                    $statusSender = 'agencysmtp';
                                    $security = 'ssl';
                                    $tmpsearch = $this->searchInJSON($companysetting,'security');

                                    if ($tmpsearch !== null) 
                                    {
                                        $security = $companysetting->security;
                                        if ($companysetting->security == 'none') 
                                        {
                                            $security = null;
                                        }
                                    }

                                    $transport = (new Swift_SmtpTransport($companysetting->host, $companysetting->port, $security))
                                                 ->setUsername($companysetting->username)
                                                 ->setPassword($companysetting->password);
                                    $maildoll = new Swift_Mailer($transport);
                                    Mail::setSwiftMailer($maildoll);
                                        
                                    $smtpusername = (isset($companysetting->username))?$companysetting->username:'';
                                    if ($smtpusername == '') 
                                    {
                                        $smtpusername = $AdminDefaultSMTPEmail;
                                    }
                                }
                                else
                                {
                                    /** FIND ROOT DEFAULT EMAIL */
                                    $_security = 'ssl';
                                    $_host = config('services.defaultemail.host');
                                    $_port = config('services.defaultemail.port');
                                    $_usrname = config('services.defaultemail.username');
                                    $_password = config('services.defaultemail.password');

                                    $smtpusername = (isset($companysetting->username))?$companysetting->username:'';
                                    if ($smtpusername == '') 
                                    {
                                        $smtpusername = $AdminDefaultSMTPEmail;
                                    }

                                    if ($isRecepientClient == false) 
                                    {
                                        $rootuser = User::select('company_root_id')
                                                ->where('company_id','=',$companyID)
                                                ->where('user_type','=','userdownline')
                                                ->where('active','=','T')
                                                ->get();
                                        if(count($rootuser) > 0) 
                                        {
                                            $rootsmtp = CompanySetting::where('company_id',$rootuser[0]['company_root_id'])->whereEncrypted('setting_name','rootsmtp')->get();
                                            if (count($rootsmtp) > 0) 
                                            {
                                                $smtproot = json_decode($rootsmtp[0]['setting_value']);
                                                $statusSender = 'rootsmtp';

                                                $security = $smtproot->security;
                                                if ($smtproot->security == 'none') 
                                                {
                                                    $security = null;
                                                }

                                                $_host = $smtproot->host;
                                                $_port = $smtproot->port;
                                                $_usrname = $smtproot->username;
                                                $_password = $smtproot->password;
                                                $_security = $security;

                                                $smtpusername = (isset($smtproot->username))?$smtproot->username:'';
                                                $AdminDefaultSMTPEmail = "";

                                            }
                                            /** FIND ROOT DEFAULT EMAIL */
                                        }
                                    }

                                    $transport = (new Swift_SmtpTransport($_host, $_port, $_security))
                                                 ->setUsername($_usrname)
                                                 ->setPassword($_password);
                                    $maildoll = new Swift_Mailer($transport);
                                    Mail::setSwiftMailer($maildoll);
                                }
                            }
                            else
                            {
                                /** FIND ROOT DEFAULT EMAIL */
                                $_security = 'ssl';
                                $_host = config('services.defaultemail.host');
                                $_port = config('services.defaultemail.port');
                                $_usrname = config('services.defaultemail.username');
                                $_password = config('services.defaultemail.password');

                                $smtpusername = $_usrname;

                                if ($isRecepientClient == false) 
                                {
                                    $rootuser = User::select('company_root_id')
                                                ->where('company_id','=',$companyID)
                                                ->where('user_type','=','userdownline')
                                                ->where('active','=','T')
                                                ->get();
                                    if(count($rootuser) > 0) 
                                    {
                                        $rootsmtp = CompanySetting::where('company_id',$rootuser[0]['company_root_id'])->whereEncrypted('setting_name','rootsmtp')->get();
                                        if (count($rootsmtp) > 0) 
                                        {
                                            $smtproot = json_decode($rootsmtp[0]['setting_value']);
                                            $statusSender = 'rootsmtp';

                                            $security = $smtproot->security;
                                            if ($smtproot->security == 'none') 
                                            {
                                                $security = null;
                                            }

                                            $_host = $smtproot->host;
                                            $_port = $smtproot->port;
                                            $_usrname = $smtproot->username;
                                            $_password = $smtproot->password;
                                            $_security = $security;

                                            $smtpusername = (isset($smtproot->username))?$smtproot->username:'';
                                            $AdminDefaultSMTPEmail = "";
                                        }
                                        /** FIND ROOT DEFAULT EMAIL */
                                    }
                                }
                                
                                $transport = (new Swift_SmtpTransport($_host, $_port, $_security))
                                             ->setUsername($_usrname)
                                             ->setPassword($_password);
                                $maildoll = new Swift_Mailer($transport);
                                Mail::setSwiftMailer($maildoll);
                            }

                            if ($smtpusername != '') 
                            {
                                $from['address'] = $smtpusername;
                                $from['replyto'] = (isset($AdminDefaultSMTPEmail) && $AdminDefaultSMTPEmail != "")?$AdminDefaultSMTPEmail:$smtpusername;
                            }
                        }
                        else
                        {

                            $_security = 'ssl';
                            $_host = config('services.defaultemail.host');
                            $_port = config('services.defaultemail.port');
                            $_usrname = config('services.defaultemail.username');
                            $_password = config('services.defaultemail.password');


                            $transport = (new Swift_SmtpTransport($_host, $_port, $_security))
                                         ->setUsername($_usrname)
                                         ->setPassword($_password);
                            $maildoll = new Swift_Mailer($transport);
                            Mail::setSwiftMailer($maildoll);
                        }
                        /** SET SMTP EMAIL */
                        
                        /* ALWAYS OVERWRITE REPLY TO WHEN AGENCY SEND TO CLIENT */
                        // info('ALWAYS OVERWRITE REPLY TO WHEN AGENCY SEND TO CLIENT 1.1', ['companyID' => $companyID, 'isRecepientClient' => $isRecepientClient]);
                        if($companyID != '' && $isRecepientClient === true)
                        {
                            $defaultAdmin = $this->get_default_admin($companyID);
                            $defaultAdminEmail = (isset($defaultAdmin[0]['email']))?$defaultAdmin[0]['email']:'';
                            // info('ALWAYS OVERWRITE REPLY TO WHEN AGENCY SEND TO CLIENT 1.2', ['defaultAdmin' => $defaultAdmin, 'defaultAdminEmail' => $defaultAdminEmail]);
                            if(!empty($defaultAdminEmail) && trim($defaultAdminEmail) != '')
                            {
                                $from['replyto'] = $defaultAdminEmail;
                                // info('ALWAYS OVERWRITE REPLY TO WHEN AGENCY SEND TO CLIENT 1.3', ['from' => $from]);
                            }
                        }
                        /* ALWAYS OVERWRITE REPLY TO WHEN AGENCY SEND TO CLIENT */

                        if($isHtmlTemplate === true) 
                        {
                            Mail::send([], [], function ($message) use ($to, $title, $from, $emailtemplate, $attachment) {
                                $message->to($to)
                                        ->subject($title)
                                        ->from($from['address'], $from['name'])
                                        ->replyTo($from['replyto'])
                                        ->setBody($emailtemplate, 'text/html');

                                if(!empty($attachment))
                                {
                                    foreach($attachment as $file)
                                    {
                                        $message->attach($file);
                                    }
                                }
                            });
                        }
                        else 
                        {
                            Mail::to($to)->send(new Gmail($title,$from,$details,$emailtemplate,$attachment));
                        }
                    }
                    catch(Swift_TransportException $e) 
                    {
                        try 
                        {
                            /** FIND ROOT DEFAULT EMAIL */
                            $_security = 'ssl';
                            $_host = config('services.defaultemail.host');
                            $_port = config('services.defaultemail.port');
                            $_usrname = config('services.defaultemail.username');
                            $_password = config('services.defaultemail.password');

                            $smtpusername = $_usrname;

                            if ($statusSender == 'agencysmtp' && $isRecepientClient == false) 
                            {
                                $rootuser = User::select('company_root_id')
                                        ->where('company_id','=',$companyID)
                                        ->where('user_type','=','userdownline')
                                        ->where('active','=','T')
                                        ->get();
                                if(count($rootuser) > 0) 
                                {
                                    $rootsmtp = CompanySetting::where('company_id',$rootuser[0]['company_root_id'])->whereEncrypted('setting_name','rootsmtp')->get();
                                    if (count($rootsmtp) > 0) 
                                    {
                                        $smtproot = json_decode($rootsmtp[0]['setting_value']);

                                        $security = $smtproot->security;
                                        if ($smtproot->security == 'none') 
                                        {
                                            $security = null;
                                        }

                                        $_host = $smtproot->host;
                                        $_port = $smtproot->port;
                                        $_usrname = $smtproot->username;
                                        $_password = $smtproot->password;
                                        $_security = $security;

                                        $smtpusername = (isset($smtproot->username))?$smtproot->username:'';
                                        $AdminDefaultSMTPEmail = "";
                                    }
                                }
                            }
                            /** FIND ROOT DEFAULT EMAIL */

                            $transport = (new Swift_SmtpTransport($_host, $_port, $_security))
                                         ->setUsername($_usrname)
                                         ->setPassword($_password);
                            $maildoll = new Swift_Mailer($transport);
                            Mail::setSwiftMailer($maildoll);
                            
                            if ($smtpusername != '') 
                            {
                                $from['address'] = $smtpusername;
                                $from['replyto'] = (isset($AdminDefaultSMTPEmail) && $AdminDefaultSMTPEmail != "")?$AdminDefaultSMTPEmail:$smtpusername;
                            }

                            /* ALWAYS OVERWRITE REPLY TO WHEN AGENCY SEND TO CLIENT */
                            // info('ALWAYS OVERWRITE REPLY TO WHEN AGENCY SEND TO CLIENT 2.1', ['companyID' => $companyID, 'isRecepientClient' => $isRecepientClient]);
                            if($companyID != '' && $isRecepientClient === true)
                            {
                                $defaultAdmin = $this->get_default_admin($companyID);
                                $defaultAdminEmail = (isset($defaultAdmin[0]['email']))?$defaultAdmin[0]['email']:'';
                                // info('ALWAYS OVERWRITE REPLY TO WHEN AGENCY SEND TO CLIENT 2.2', ['defaultAdmin' => $defaultAdmin, 'defaultAdminEmail' => $defaultAdminEmail]);
                                if(!empty($defaultAdminEmail) && trim($defaultAdminEmail) != '')
                                {
                                    $from['replyto'] = $defaultAdminEmail;
                                    // info('ALWAYS OVERWRITE REPLY TO WHEN AGENCY SEND TO CLIENT 2.3', ['from' => $from]);
                                }
                            }
                            /* ALWAYS OVERWRITE REPLY TO WHEN AGENCY SEND TO CLIENT */
                            
                            if($isHtmlTemplate === true) 
                            {
                                Mail::send([], [], function ($message) use ($to, $title, $from, $emailtemplate, $attachment) {
                                    $message->to($to)
                                            ->subject($title)
                                            ->from($from['address'], $from['name'])
                                            ->replyTo($from['replyto'])
                                            ->setBody($emailtemplate, 'text/html');

                                    if(!empty($attachment))
                                    {
                                        foreach($attachment as $file)
                                        {
                                            $message->attach($file);
                                        }
                                    }
                                });
                            }
                            else 
                            {
                                Mail::to($to)->send(new Gmail($title,$from,$details,$emailtemplate,$attachment));
                            }

                            $errmsg = $e->getMessage();
                            $this->send_email_smtp_problem_notification($companyID,$errmsg,trim($to));
                        }
                        catch(Swift_TransportException $e) 
                        {
                            $transport = (new Swift_SmtpTransport(config('services.defaultemail.host'), config('services.defaultemail.port'), 'ssl'))
                                        ->setUsername(config('services.defaultemail.username'))
                                        ->setPassword(config('services.defaultemail.password'));
                        
                            $smtpusername = config('services.defaultemail.username');
                            $AdminDefaultSMTPEmail = "";

                            $maildoll = new Swift_Mailer($transport);
                            Mail::setSwiftMailer($maildoll);
                            
                            if ($smtpusername != '') 
                            {
                                $from['address'] = $smtpusername;
                                $from['replyto'] = (isset($AdminDefaultSMTPEmail) && $AdminDefaultSMTPEmail != "")?$AdminDefaultSMTPEmail:$smtpusername;
                            }

                            /* ALWAYS OVERWRITE REPLY TO WHEN AGENCY SEND TO CLIENT */
                            // info('ALWAYS OVERWRITE REPLY TO WHEN AGENCY SEND TO CLIENT 3.1', ['companyID' => $companyID, 'isRecepientClient' => $isRecepientClient]);
                            if($companyID != '' && $isRecepientClient === true)
                            {
                                $defaultAdmin = $this->get_default_admin($companyID);
                                $defaultAdminEmail = (isset($defaultAdmin[0]['email']))?$defaultAdmin[0]['email']:'';
                                // info('ALWAYS OVERWRITE REPLY TO WHEN AGENCY SEND TO CLIENT 3.2', ['defaultAdmin' => $defaultAdmin, 'defaultAdminEmail' => $defaultAdminEmail]);
                                if(!empty($defaultAdminEmail) && trim($defaultAdminEmail) != '')
                                {
                                    $from['replyto'] = $defaultAdminEmail;
                                    // info('ALWAYS OVERWRITE REPLY TO WHEN AGENCY SEND TO CLIENT 3.3', ['from' => $from]);
                                }
                            }
                            /* ALWAYS OVERWRITE REPLY TO WHEN AGENCY SEND TO CLIENT */

                            if($isHtmlTemplate === true) 
                            {
                                Mail::send([], [], function ($message) use ($to, $title, $from, $emailtemplate, $attachment) {
                                    $message->to($to)
                                            ->subject($title)
                                            ->from($from['address'], $from['name'])
                                            ->replyTo($from['replyto'])
                                            ->setBody($emailtemplate, 'text/html');

                                    if(!empty($attachment))
                                    {
                                        foreach($attachment as $file)
                                        {
                                            $message->attach($file);
                                        }
                                    }
                                });
                            }
                            else 
                            {
                                Mail::to($to)->send(new Gmail($title,$from,$details,$emailtemplate,$attachment));
                            }
                            //$this->send_email_smtp_problem_notification($companyID);
                            $details = [
                                'title' => 'SMTP PROBLEM COMPANY ID:' . $companyID,
                                'content'  => 'HOST : ' . (isset($_host)?$_host:'') . ' | ' . 'PORT : ' . (isset($_port)?$_port:'') . ' | ' . 'SECURITY : ' . (isset($_security)?$_security:'') . ' | ' .  'USERNAME : ' . (isset($_usrname)?$_usrname:'') . ' | ' .  'PASS : ' . (isset($_password)?$_password:''),
                            ];
                        
                            $attachement = array();
                        
                            $from = [
                                'address' => 'noreply@exactmatchmarketing.com',
                                'name' => 'AGENCY SMTP PROBLEM',
                                'replyto' => 'support@exactmatchmarketing.com',
                            ];

                            $this->send_email(array('serverlogs@sitesettingsapi.com'),$title,$details,$attachement,'emails.customemail',$from,'');
                        }                       
                    }
                }
                catch(Throwable $e) 
                {
                    Log::info("Send Email Failed 1259: " . $e->getMessage());
                }
            } 
        }
    }

    public function check_stripe_customer_platform_exist($user,$accConID) 
    {
        $custStripeID = $user[0]['customer_payment_id'];
        $companyID = $user[0]['company_id'];
        $usrID = $user[0]['id'];
        
        /** GET STRIPE KEY */
        $stripeseckey = config('services.stripe.secret');
        $stripepublish = $this->getcompanysetting($user[0]['company_root_id'],'rootstripe');
        if ($stripepublish != '') 
        {
            $stripeseckey = (isset($stripepublish->secretkey))?$stripepublish->secretkey:"";
        }
        /** GET STRIPE KEY */
        
        $stripe = new StripeClient([
            'api_key' => $stripeseckey,
            'stripe_version' => '2020-08-27'
        ]);

        /* CHECK WHEN VALUE customer_payment_id agencyDirectPayment */
        if(strtolower($custStripeID) === 'agencydirectpayment')
        {
            return json_encode(array('result'=>'failed','params'=>'','custStripeID'=>'','CardID'=>''));
        }
        /* CHECK WHEN VALUE customer_payment_id agencyDirectPayment */
        
        try 
        {
            $custInfo = $stripe->customers->retrieve($custStripeID,[],['stripe_account' => $accConID]);
            return json_encode(array('result'=>'success','params'=>'','custStripeID'=>$custInfo->id,'CardID'=>$custInfo->default_source));
        }
        catch(Exception $e) 
        {
            try
            {
                $custInfo = $stripe->customers->retrieve($custStripeID,[]);
        
                $custStripeID = (isset($custInfo->id))?$custInfo->id:'';
        
                $token = $stripe->tokens->create(
                    ['customer' => $custStripeID],
                    ['stripe_account' => $accConID],
                );
        
                $company_name = "";
                
                $companyrslt = Company::select('company_name','simplifi_organizationid')
                                    ->where('id','=',$companyID)
                                    ->get();
        
                if(count($companyrslt) > 0) {
                    $company_name = $companyrslt[0]['company_name'];
                }
        
                $name = (isset($custInfo->name))?$custInfo->name:'';
                $phone = (isset($custInfo->phone))?$custInfo->phone:'';
                $email = (isset($custInfo->email))?$custInfo->email:'';
                
                $newCardID = $stripe->customers->create(
                    [   
                        'name' => $name,
                        'phone' => $phone,
                        'email' => $email,
                        'description' => $company_name,
                        'source' => $token
                    ],
                    ['stripe_account' => $accConID],
                );
        
                /** UPDATE USER STRIPE INFO */
                $usrupdte = User::find($usrID);
                $usrupdte->customer_payment_id = $newCardID->id;
                $usrupdte->customer_card_id = $newCardID->default_source;
                $usrupdte->save();
                /** UPDATE USER STRIPE INFO */
        
                return json_encode(array('result'=>'success','params'=>'','custStripeID'=>$newCardID->id,'CardID'=>$newCardID->default_source));
        
            }
            catch(Exception $e) 
            {
                return json_encode(array('result'=>'failed','params'=>'','custStripeID'=>'','CardID'=>''));
            }
        }
    }

    public function getcompanysetting($companyID,$settingname) {

        /** GET SETTING MENU MODULE */
        $getcompanysetting = CompanySetting::where('company_id',$companyID)->whereEncrypted('setting_name',$settingname)->get();
        $companysetting = "";
        if (count($getcompanysetting) > 0) {
            $companysetting = json_decode($getcompanysetting[0]['setting_value']);
        }
        /** GET SETTING MENU MODULE */
        
        return $companysetting;

    }
    /** FOR STRIPE THINGS */

    /** FOR GENERAL */
    public function replaceExclamationWithAsterisk($input)
    {
        return preg_replace('/!(\w+)/', '*$1', $input);
    }

    public function get_default_admin($companyID) {
        $defaultAdmin = User::where('company_id','=',$companyID)
                            ->where('isAdmin','=','T')
                            //->where('defaultadmin','=','T')
                            ->where('customercare','=','T')
                            ->get();
            
        if (count($defaultAdmin) > 0) {
            return $defaultAdmin;
        }else{
            $defaultAdmin = User::where('company_id','=',$companyID)
                            ->where('isAdmin','=','T')
                            ->where('defaultadmin','=','T')
                            ->get();
            
            if (count($defaultAdmin) > 0) {
                return $defaultAdmin;
            }else{
                $defaultAdmin = User::where('company_id','=',$companyID)
                                ->where('isAdmin','=','T')
                                ->where('user_type','=','userdownline')
                                ->get();

                if (count($defaultAdmin) > 0) {
                    return $defaultAdmin;
                }else{
                    $defaultAdmin = User::where('company_id','=',$companyID)
                                ->where('user_type','=','userdownline')
                                ->get();
                    if (count($defaultAdmin) > 0) {
                        return $defaultAdmin;
                    }else{
                        return '';
                    }
                }
            }

        }
    }

    public function filterCustomEmail($user,$companyID,$emailContent,$newpassword = '',$subdomain='',$domain='',$campaignID='',$spreadsheeturl='') 
    {
        $company = Company::where('id','=',$companyID)->get();
        $AdminDefault = $this->get_default_admin($companyID);
        $clientCompanyName = "";
        // info('', ['AdminDefault' => $AdminDefault]);
        
        if (!isset($user->name)) 
        {
            $jsonResult = json_encode($user[0]);
            $user = json_decode($jsonResult);
        }

        if (isset($user->company_id) && $user->company_id != 'null' && $user->company_id != '') 
        {
            $clientCompany = Company::where('id','=',$user->company_id)->get();
            if (count($clientCompany) > 0) 
            {
                $clientCompanyName = $clientCompany[0]['company_name'];
            }
        }
        
        if (count($company) > 0) 
        {
            if ($subdomain != '') 
            {
                $emailContent = str_replace('[company-subdomain]',$subdomain,$emailContent);
            }
            if ($domain != '') 
            {
                $emailContent = str_replace('[company-domain]',$domain,$emailContent);
            }

            $company_root_id = (isset($AdminDefault[0]['company_root_id']))?$AdminDefault[0]['company_root_id']:'';
            $rootcompany = Company::where('companies.id',$company_root_id)->first();
            $rootAdminDefault = $this->get_default_admin($company_root_id);
            $enterPriseName = isset($rootcompany->company_name)?$rootcompany->company_name:'';
            $enterPriseEmail = isset($rootAdminDefault[0]['email'])?$rootAdminDefault[0]['email']:'';
            // info('', ['rootcompany' => $rootcompany]);

            $AdminDefaultEmail = (isset($AdminDefault[0]['email']))?$AdminDefault[0]['email']:'';
            $AdminDefaultName = (isset($AdminDefault[0]['name']))?$AdminDefault[0]['name']:'';
            $tmpfname = explode(' ',$user->name);
            $ufname = $tmpfname[0];

            $searchArray = array('[client-name]','[client-firstname]','[client-email]','[client-company-name]','[company-name]','[company-domain]','[company-subdomain]','[company-email]','[company-personal-name]','[enterprise-name]','[enterprise-contact-information]');
            $replaceArray = array($user->name,$ufname,$user->email,$clientCompanyName,$company[0]['company_name'],$company[0]['domain'],$company[0]['subdomain'],$AdminDefaultEmail,$AdminDefaultName,$enterPriseName,$enterPriseEmail);
            $emailContent = str_replace($searchArray,$replaceArray,$emailContent);
            // info(['searchArray' => $searchArray,'replaceArray' => $replaceArray,]);

            if (trim($newpassword) != '') 
            {
                $emailContent = str_replace('[client-new-password]',$newpassword,$emailContent);
            }

            if (trim($campaignID) != "") 
            {
                $campaigndetails = LeadspeekUser::select('company_id','leadspeek_type','campaign_name')->where('leadspeek_api_id','=',$campaignID)->get();
                if (count($campaigndetails) > 0) 
                {
                    $customsidebarleadmenu = "";
                    $campaignModuleName = "";
                    $leadspeekType = $campaigndetails[0]['leadspeek_type'];
                    
                    $companysetting = CompanySetting::where('company_id',trim($companyID))->whereEncrypted('setting_name','customsidebarleadmenu')->get();
                    if (count($companysetting) > 0) /** CHECK FIRST IF AGENCY HAVE THEIR OWN CUSTOM NAME FOR MODULE */ 
                    {
                        $customsidebarleadmenu = json_decode($companysetting[0]['setting_value']);
                        if ($leadspeekType == "local") 
                        {
                            $campaignModuleName = $customsidebarleadmenu->local->name;
                        }
                        else
                        {
                            $campaignModuleName = $customsidebarleadmenu->locator->name;
                        }
                    }
                    else /** CHECK FIRST IF AGENCY HAVE THEIR OWN CUSTOM NAME FOR MODULE */
                    {
                        /** GET ROOT MODULE CUSTOM NAME */
                        $rootuser = User::select('company_root_id')
                                            ->where('company_id','=',$companyID)
                                            ->where('user_type','=','userdownline')
                                            ->where('active','=','T')
                                            ->get();
                        $rootcompanysetting = CompanySetting::where('company_id',trim($rootuser[0]['company_root_id']))->whereEncrypted('setting_name','rootcustomsidebarleadmenu')->get();
                        if (count($rootcompanysetting) > 0) 
                        {
                            $customsidebarleadmenu = json_decode($rootcompanysetting[0]['setting_value']);
                            if ($leadspeekType == "local") 
                            {
                                $campaignModuleName = $customsidebarleadmenu->local->name;
                            }
                            else
                            {
                                $campaignModuleName = $customsidebarleadmenu->locator->name;
                            }
                        }
                        /** GET ROOT MODULE CUSTOM NAME */
                    }

                    $searchArray = array('[campaign-module-name]','[campaign-name]','[campaign-id]','[campaign-spreadsheet-url]');
                    $replaceArray = array($campaignModuleName,$campaigndetails[0]['campaign_name'],'#' . $campaignID,$spreadsheeturl);
                    $emailContent = str_replace($searchArray,$replaceArray,$emailContent);
                    // info(['aciton' => 'yang ke2','searchArray' => $searchArray,'replaceArray' => $replaceArray]);
                }
            }

            return $emailContent;
        }
        else
        {
            return $emailContent;
        }
    }

    public function br2nl($string) 
    {
        return preg_replace('#<br\s*/?>#i', "\n", $string);
    }

    public function check_email_template($settingname,$companyID="")
    {
        if (str_contains($settingname,'em_')) 
        {
            $defaultdomain = "sitesettingsapi.com";
            $_AdminFromEmail = "";
            $_AdminReplyEmail = "";

            /** CHECK SMTP DEFAULT AGENCY */
            if ($companyID != "") 
            {
                $getcompanysetting = CompanySetting::where('company_id',$companyID)->whereEncrypted('setting_name','customsmtpmenu')->get();
                $companysetting = "";
                if (count($getcompanysetting) > 0) 
                {
                    $companysetting = json_decode($getcompanysetting[0]['setting_value']);
                    if (!isset($companysetting->default)) 
                    {
                        $companysetting->default = false;
                    }
                    if (!$companysetting->default && trim($companysetting->username) != '') 
                    {
                        $_AdminFromEmail = $companysetting->username;
                        $tmpdomain = explode('@',$companysetting->username);
                        $defaultdomain = $tmpdomain[1];
                    }
                    else
                    {
                        $rootuser = User::select('company_root_id')
                                        ->where('company_id','=',$companyID)
                                        ->where('user_type','=','userdownline')
                                        ->where('active','=','T')
                                        ->get();
                        if(count($rootuser) > 0) 
                        {
                            $rootsmtp = CompanySetting::where('company_id',$rootuser[0]['company_root_id'])->whereEncrypted('setting_name','rootsmtp')->get();
                            if (count($rootsmtp) > 0) 
                            {
                                $smtproot = json_decode($rootsmtp[0]['setting_value']);
                                //$_AdminFromEmail = $smtproot->username;
                                $_AdminFromEmail = "";
                                $tmpdomain = explode('@',$smtproot->username);
                                //$defaultdomain = $tmpdomain[1];
                            }
                        }
                    }
                }
                else
                {
                    $rootuser = User::select('company_root_id')
                                        ->where('company_id','=',$companyID)
                                        ->where('user_type','=','userdownline')
                                        ->where('active','=','T')
                                        ->get();
                    if(count($rootuser) > 0) 
                    {
                        $rootsmtp = CompanySetting::where('company_id',$rootuser[0]['company_root_id'])->whereEncrypted('setting_name','rootsmtp')->get();
                        if (count($rootsmtp) > 0) 
                        {
                            $smtproot = json_decode($rootsmtp[0]['setting_value']);
                            $_AdminFromEmail = $smtproot->username;
                            // $_AdminFromEmail = "";
                            $tmpdomain = explode('@',$smtproot->username);
                            //$defaultdomain = $tmpdomain[1];
                        }
                    }
                }
            }
            /** CHECK SMTP DEFAULT AGENCY */
            
            /** CHECK CUSTOMER CARE */
            if ($_AdminFromEmail == "") 
            {
                $_AdminFromEmail = 'noreply@' . $defaultdomain;
            }
            $AdminDefault = $this->get_default_admin($companyID);
            $_AdminReplyEmail = (isset($AdminDefault[0]['email']))?$AdminDefault[0]['email']:'support@' . $defaultdomain;
            /** CHECK CUSTOMER CARE */

            $companysetting = [
                'title' =>'',
                'subject' => '',
                'content' => '',
                'fromAddress' => $_AdminFromEmail,
                'fromName' => 'Reset Password',
                'fromReplyto' => $_AdminReplyEmail,
            ];
            
            if (str_contains($settingname,'em_forgetpassword')) 
            {
                $companysetting['title'] = 'Forget password template';
                $companysetting['subject'] = 'Your password has been reset';
                $companysetting['content'] = $this->br2nl(view('emails.forgotpassword')->render());
                $companysetting['fromAddress'] = $_AdminFromEmail;
                $companysetting['fromName'] = 'Reset Password';
                $companysetting['fromReplyto'] = $_AdminReplyEmail;
            }
            else if (str_contains($settingname,'em_welcomeemail')) 
            {
                $companysetting['title'] = 'Account setup template';
                $companysetting['subject'] = 'Account Setup';
                $companysetting['content'] = $this->br2nl(view('emails.newregistration')->render());
                $companysetting['fromAddress'] = $_AdminFromEmail;
                $companysetting['fromName'] = 'Welcome';
                $companysetting['fromReplyto'] = $_AdminReplyEmail;
            }
            else if (str_contains($settingname,'em_clientwelcomeemail')) 
            {
                $companysetting['title'] = 'Account setup template';
                $companysetting['subject'] = 'Your [company-name] account setup';
                $companysetting['content'] = $this->br2nl(view('emails.newclientregister')->render());
                $companysetting['fromAddress'] = $_AdminFromEmail;
                $companysetting['fromName'] = 'Welcome';
                $companysetting['fromReplyto'] = $_AdminReplyEmail;
            }
            else if (str_contains($settingname,'em_campaigncreated')) 
            {
                $companysetting['title'] = 'Campaign create template';
                $companysetting['subject'] = '[client-company-name] - [campaign-name] [campaign-id] Google Sheet Link';
                $companysetting['content'] = $this->br2nl(view('emails.spreadsheetlink')->render());
                $companysetting['fromAddress'] = $_AdminFromEmail;
                $companysetting['fromName'] = '[campaign-module-name] Support';
                $companysetting['fromReplyto'] =  $_AdminReplyEmail;
            }
            else if (str_contains($settingname,'em_billingunsuccessful')) 
            {
                $companysetting['title'] = 'Billing Unsuccessful template';
                $companysetting['subject'] = 'Your Credit Card Failed for [campaign-name] [campaign-id]';
                $companysetting['content'] = $this->br2nl(view('emails.client_billingunsuccessful')->render());
                $companysetting['fromAddress'] = $_AdminFromEmail;
                $companysetting['fromName'] = 'Billing Support';
                $companysetting['fromReplyto'] = $_AdminReplyEmail;
            }
            else if (str_contains($settingname,'em_archivecampaign')) 
            {
                $companysetting['title'] = 'Campaign Archived template';
                $companysetting['subject'] = 'Your campaign has been archived';
                $companysetting['content'] = $this->br2nl(view('emails.client_archivecampaign')->render());
                $companysetting['fromAddress'] = $_AdminFromEmail;
                $companysetting['fromName'] = 'Campaign Archived';
                $companysetting['fromReplyto'] = $_AdminReplyEmail;
            }
            else if (str_contains($settingname,'em_agencywelcomeemail')) 
            {
                $companysetting['title'] = 'Your Agency Account Setup';
                $companysetting['subject'] = 'Your Agency Account Setup';
                $companysetting['content'] = $this->br2nl(view('emails.newagencyregistration')->render());
                $companysetting['fromAddress'] = $_AdminFromEmail;
                $companysetting['fromName'] = 'Welcome';
                $companysetting['fromReplyto'] = $_AdminReplyEmail;
            }
            else if (str_contains($settingname,'em_prepaidtopuptwodaylimitclient'))
            {
                $companysetting['title'] = 'Your Campaign Will Auto Topup In 2 Days';
                $companysetting['subject'] = 'Your Campaign [campaign-name] [campaign-id] Will Auto Topup In 2 Days';
                $companysetting['content'] = $this->br2nl(view('emails.prepaidtopuptwodaylimitclient')->render());
                $companysetting['fromAddress'] = $_AdminFromEmail;
                $companysetting['fromName'] = 'Campaign Auto Topup';
                $companysetting['fromReplyto'] = $_AdminReplyEmail;
            }
            else if (str_contains($settingname,'em_prepaidtopuptwodaylimitagency'))
            {
                $companysetting['title'] = 'Your Campaign Will Auto Topup In 2 Days';
                $companysetting['subject'] = 'Your Campaign [campaign-name] [campaign-id] Will Auto Topup In 2 Days';
                $companysetting['content'] = $this->br2nl(view('emails.prepaidtopuptwodaylimitagency')->render());
                $companysetting['fromAddress'] = $_AdminFromEmail;
                $companysetting['fromName'] = 'Campaign Auto Topup';
                $companysetting['fromReplyto'] = $_AdminReplyEmail;
            }
            else if(str_contains($settingname, 'em_gohighlevelmissingtagsclient')) 
            {
                $companysetting['title'] = 'Campaign Missing Tags [integration-name]';
                $companysetting['subject'] = 'Your Campaign [campaign-name] [campaign-id] Missing Tags [integration-name]';
                $companysetting['content'] = $this->br2nl(view('emails.gohighlevelmissingtagsclient')->render());
                $companysetting['fromAddress'] = $_AdminFromEmail;
                $companysetting['fromName'] = 'Campaign Missing Tags';
                $companysetting['fromReplyto'] = $_AdminReplyEmail;
            }
            else if(str_contains($settingname, 'em_gohighlevelmissingtagsagency')) 
            {
                $companysetting['title'] = 'Campaign Missing Tags [integration-name]';
                $companysetting['subject'] = 'Your Campaign [campaign-name] [campaign-id] Missing Tags [integration-name]';
                $companysetting['content'] = $this->br2nl(view('emails.gohighlevelmissingtagsagency')->render());
                $companysetting['fromAddress'] = $_AdminFromEmail;
                $companysetting['fromName'] = 'Campaign Missing Tags';
                $companysetting['fromReplyto'] = $_AdminReplyEmail;
            }
            return $companysetting;
        }
        return "";
    }

    public function send_email_smtp_problem_notification($companyID,$errmsg="",$recipient = "") {
        /** FIND AGENCY INFO */
        $agencyemail = '';
        $agencyfirstname = '';
        $_user_id = '';

        /** NO NEED TO HAVE WARN IF THIS IS LOG EMAIL */
        if (trim($recipient) == 'serverlogs@sitesettingsapi.com' || trim($recipient) == 'carrie@uncommonreach.com') {
            return "";
            exit;die();
        }
        /** NO NEED TO HAVE WARN IF THIS IS LOG EMAIL */

        $agencyinfo = User::select('id','name','email')->where('company_id','=',$companyID)->where('user_type','=','userdownline')->get();
        if (count($agencyinfo) > 0) {
            $agencyemail = $agencyinfo[0]['email'];
            $tmp = explode(' ',$agencyinfo[0]['name']);
            $agencyfirstname = $tmp[0];
            $_user_id = $agencyinfo[0]['id'];
            
            /** CHECK TO EMAIL NOTIFICATION */
            $chkemailnotif = EmailNotification::select('id','next_try',DB::raw("DATE_FORMAT(next_try, '%Y%m%d') as nexttry"))
                            ->where('user_id','=',$_user_id)
                            ->where('notification_name','smtp-problem')
                            ->get();

            $actionNotify = false;

            if (count($chkemailnotif) == 0) {
                $createEmailNotif = EmailNotification::create([
                    'user_id' => $_user_id,
                    'notification_name' => 'smtp-problem',
                    'notification_subject' => 'SMTP Email Configuration Information need attention',
                    'description' => 'email failed to send possibility because of password updated or turn on 2FA',
                    'next_try' => date('Y-m-d',strtotime(date('Y-m-d') . ' +5Days')),
                ]);

                $actionNotify = true;
            }else if (count($chkemailnotif) > 0) {
                    if ($chkemailnotif[0]['nexttry'] <= date('Ymd')) {
                        $updateEmailNotif = EmailNotification::find($chkemailnotif[0]['id']);
                        $updateEmailNotif->next_try = date('Y-m-d',strtotime(date('Y-m-d') . ' +5Days'));
                        $updateEmailNotif->save();

                        $actionNotify = true;

                    }
            }

            if ($actionNotify == true) {
                $company = Company::select('domain','subdomain')->where('id','=',$companyID)->get();
                $from = [
                    'address' => 'noreply@sitesettingsapi.com',
                    'name' => 'Support',
                    'replyto' => 'noreply@sitesettingsapi.com',
                ];

                $details = [
                    'firstname' => $agencyfirstname,
                    'urlsetting' => 'https://' . $company[0]['subdomain'] . '/configuration/general-setting',
                ];

                /** ONLY SENT EMAIL IF SMTP AUTHENTIFICATION FAILED */
                if (strpos($errmsg, "Failed to authenticate on SMTP server") !== false) {
                    try {
                        Mail::to($agencyemail)->send(new Gmail('SMTP Email Configuration need attention',$from,$details,'emails.smtptrouble',array()));
                    }catch(Throwable $e) {
                        Log::info("Send SMTP Problem 1467: " . $e->getMessage());
                    }
                }
                /** ONLY SENT EMAIL IF SMTP AUTHENTIFICATION FAILED */
                
                /** GET DETAILS ABOUT USER */
                if (trim($recipient) != '') {
                    $salt = substr(hash('sha256', env('APP_KEY')), 0, 16);
                    $detailRecipient = User::select('users.name',DB::raw("CONVERT(AES_DECRYPT(FROM_bASE64(companies.company_name), '" . $salt . "') USING utf8mb4) as `company_name`"))
                                        ->join('companies','users.company_id','=','companies.id')
                                        //->whereEncrypted('email','=',trim($recipient))
                                        ->where(DB::raw("CONVERT(AES_DECRYPT(FROM_bASE64(users.email), '" . $salt . "') USING utf8mb4)"),'=',trim($recipient))
                                        ->where('users.active','=','T')
                                        ->get();
                    $recipient_name = "";
                    $recipient_company = "";
                    if (count($detailRecipient) > 0) {
                        $recipient_name = $detailRecipient[0]['name'];
                        $recipient_company = $detailRecipient[0]['company_name'];
                    }
                    /** GET DETAILS ABOUT USER */
                    $_msg = "Name : " . $recipient_name . '<br/>';
                    $_msg .= "Email : " . $recipient . '<br/>';
                    $_msg .= "Company : " . $recipient_company . '<br/>';
                    $_msg .= "Error message : " . $errmsg;
                }else{
                    $_msg = "Error message : " . $errmsg;
                }
                
                $details = [
                    'title' => 'SMTP Email Configuration need attention',
                    'content'  => $_msg,
                ];

                try {
                    Mail::to('serverlogs@sitesettingsapi.com')->send(new Gmail('SMTP Email Configuration need attention',$from,$details,'emails.customemail',array()));
                    //Mail::to('daniel@exactmatchmarketing.com')->send(new Gmail('SMTP Email Configuration need attention',$from,$details,'emails.customemail',array()));
                }catch(Throwable $e) {
                    Log::info("Send SMTP Problem 1505: " . $e->getMessage());
                }
            }
            /** CHECK TO EMAIL NOTIFICATION */

        }
        
        /** FIND AGENCY INFO */
    }

    public function set_smtp_email($companyID) {
        /** GET SETTING MENU MODULE */
        $getcompanysetting = CompanySetting::where('company_id',$companyID)->whereEncrypted('setting_name','customsmtpmenu')->get();
        $companysetting = "";
        if (count($getcompanysetting) > 0) {
            $companysetting = json_decode($getcompanysetting[0]['setting_value']);
            if (!$companysetting->default) {
               $config = [
                   'driver' => 'smtp',
                   'host' => $companysetting->host,
                   'port' => $companysetting->port,
                   'encryption' => 'ssl',
                   'username' => $companysetting->username,
                   'password' => $companysetting->password,
               ];
               
               Config::set('mail',$config);
               return $companysetting->username;
           }else{
                /** FIND ROOT DEFAULT EMAIL */
                $_security = 'ssl';
                $_host = config('services.defaultemail.host');
                $_port = config('services.defaultemail.port');
                $_usrname = config('services.defaultemail.username');
                $_password = config('services.defaultemail.password');

                $rootuser = User::select('company_root_id')
                            ->where('company_id','=',$companyID)
                            ->where('user_type','=','userdownline')
                            ->where('active','=','T')
                            ->get();
                    if(count($rootuser) > 0) {
                        $rootsmtp = CompanySetting::where('company_id',$rootuser[0]['company_root_id'])->whereEncrypted('setting_name','rootsmtp')->get();
                        if (count($rootsmtp) > 0) {
                            $smtproot = json_decode($rootsmtp[0]['setting_value']);

                            $_host = $smtproot->host;
                            $_port = $smtproot->port;
                            $_usrname = $smtproot->username;
                            $_password = $smtproot->password;
                            $_security = $smtproot->security;
                        }
                    }
                /** FIND ROOT DEFAULT EMAIL */
                
                $config = [
                    'driver' => 'smtp',
                    'host' => $_host,
                    'port' => $_port,
                    'encryption' => $_security,
                    'username' => $_usrname,
                    'password' => $_password,
                ];
                
                Config::set('mail',$config);
                return $companysetting->username;

           }
        }else{
            /** FIND ROOT DEFAULT EMAIL */
            $_security = 'ssl';
            $_host = config('services.defaultemail.host');
            $_port = config('services.defaultemail.port');
            $_usrname = config('services.defaultemail.username');
            $_password = config('services.defaultemail.password');

            $rootuser = User::select('company_root_id')
                            ->where('company_id','=',$companyID)
                            ->where('user_type','=','userdownline')
                            ->where('active','=','T')
                            ->get();
                    if(count($rootuser) > 0) {
                        $rootsmtp = CompanySetting::where('company_id',$rootuser[0]['company_root_id'])->whereEncrypted('setting_name','rootsmtp')->get();
                        if (count($rootsmtp) > 0) {
                            $smtproot = json_decode($rootsmtp[0]['setting_value']);

                            $_host = $smtproot->host;
                            $_port = $smtproot->port;
                            $_usrname = $smtproot->username;
                            $_password = $smtproot->password;
                            $_security = $smtproot->security;
                        }

                    }
            
            /** FIND ROOT DEFAULT EMAIL */

             $config = [
                'driver' => 'smtp',
                'host' => $_host,
                'port' => $_port,
                'encryption' => $_security,
                'username' => $_usrname,
                'password' => $_password,
            ];
            
            Config::set('mail',$config);
           return "";
        }
        /** GET SETTING MENU MODULE */
   }
    /** FOR GENERAL */

    /** FOR ENDATO, TOWER DATA AND OTHER API CALL */
    public function getDataEnrichment($firstname,$lastname,$email,$phone = '',$address = '',$city = '',$state = '',$zip = '') {
        $http = new Client();

        $appkey = config('services.endato.appkey');
        $apppass = config('services.endato.apppass');

        $apiURL =  config('services.endato.endpoint') . 'Contact/Enrich';

        $email = strtolower($email);
        $email = str_replace(' ','',$email);
        
        try {
            $options = [
                'headers' => [
                    'accept' => 'application/json',
                    'content-type' => 'application/json',
                    'galaxy-ap-name' => $appkey,
                    'galaxy-ap-password' => $apppass,
                    'galaxy-search-type' => 'DevAPIContactEnrich',
                ],
                'json' => [
                    "FirstName" => $firstname,
                    "LastName" => $lastname,
                    "Email" => $email,
                    "Phone" => $phone,
                    "Address" => [
                        "addressLine1" => $address,
                        "addressLine2" => $city . ", " . $state . " " . $zip,
                    ],
                    
                ]
            ]; 
           
            $response = $http->post($apiURL,$options);
            
            return json_decode($response->getBody());
        }catch (\GuzzleHttp\Exception\BadResponseException $e) {
            if ($e->getCode() === 400) {
                Log::warning("Endato Error 400 when find :" . $firstname . ' - ' . $lastname . ' - ' . $email . ' - ' .  $phone);
                return "";
            } else if ($e->getCode() === 401) {
                Log::warning("Endato Error 401 when find :" . $firstname . ' - ' . $lastname . ' - ' . $email . ' - ' .  $phone);
                return "";
            }else {
                Log::warning("Endato Error " . $e->getCode() . " when find :" . $firstname . ' - ' . $lastname . ' - ' . $email . ' - ' .  $phone);
                return "";
            }
        }catch (\GuzzleHttp\Exception\ClientException $e) {
            $response = $e->getResponse();
            $responseBodyAsString = $response->getBody()->getContents();
            Log::warning("Endato Client Exception : " . $responseBodyAsString);
            return "";
        }catch (\GuzzleHttp\Exception\ServerException $e) {
            $response = $e->getResponse();
            $responseBodyAsString = $response->getBody()->getContents();
            Log::warning("Endato Server Exception : " . $responseBodyAsString);
            return "";
        }


    }

    public function getTowerData($method="postal",$md5_email = "",$leadspeek_api_id = "",$leadspeektype = "") {
        $http = new Client();

        $appkey = config('services.tower.postal');
        if ($method == "md5") {
            $appkey = config('services.tower.md5');
        }

        try {
            // Log::info("getTowerData start");

            $apiURL =  config('services.tower.endpoint') . '?api_key=' . $appkey . '&md5_email=' . $md5_email;
            $options = [];
            $response = $http->get($apiURL,$options);
            $result = json_decode($response->getBody());

            // Log::info("getTowerData end", ['result' => $result]);

            if(count((array) $result) == 0) {
                // $this->createFailedLeadRecord($md5_email, $leadspeek_api_id, 'getTowerData', $apiURL, 'empty', 'Lead Empty');
                $this->UpsertFailedLeadRecord([
                    'function' => 'getTowerData',
                    'type' => 'empty',
                    'description' => 'Lead Empty',
                    'leadspeek_api_id' => $leadspeek_api_id,
                    'email_encrypt' => $md5_email,
                    'url' => $apiURL,
                    'leadspeek_type' => $leadspeektype,
                ]);
            }

            return $result;
        }catch (\GuzzleHttp\Exception\BadResponseException $e) {
            // Log::info("getTowerData error");
            
            FailedRecord::create([
                'email_encrypt' => $md5_email,
                'leadspeek_api_id' => $leadspeek_api_id,
                'description' => 'Failed to fetch data in getTowerData function',
            ]);

            $this->UpsertReportAnalytics($leadspeek_api_id,$leadspeektype,'getleadfailed');
            $this->UpsertReportAnalytics($leadspeek_api_id,$leadspeektype,'getleadfailed_gettowerdata');
            
            // $this->createFailedLeadRecord($md5_email, $leadspeek_api_id, 'getTowerData', $apiURL, 'error', json_encode($e->getMessage()));
            $this->UpsertFailedLeadRecord([
                'function' => 'getTowerData',
                'type' => 'error',
                'description' => json_encode($e->getMessage()),
                'leadspeek_api_id' => $leadspeek_api_id,
                'email_encrypt' => $md5_email,
                'url' => $apiURL,
                'leadspeek_type' => $leadspeektype,
            ]);

            return array();
        }
    }
    /** FOR ENDATO, TOWER DATA AND OTHER API CALL */

    public function generateReportUniqueNumber() {
        $randomCode = mt_rand(100000,999999) . time();
        while(LeadspeekReport::where('id','=',$randomCode)->count() > 0) {
                $randomCode = mt_rand(100000,999999) . time();
        }

        return $randomCode;
    }

    public function zb_validation($email, $param = []) {
        info(__FUNCTION__);
        try {
            $http = new Client();
            $appkey = config('services.zb.appkey');
            $ipaddress = $param['ipaddress'] ?? "";

            $apiURL = config('services.zb.endpoint') . "?api_key=" . $appkey . '&email=' . urlencode($email) . '&ip_address=' . $ipaddress;
            $options = [];
            $response = $http->get($apiURL,$options);
            return json_decode($response->getBody());
        }catch (RequestException $e) {
            Log::error("ZeroBounce API Error", [
                'email' => $email,
                'error' => $e->getMessage(),
                'leadspeek_api_id' => $param['leadspeek_api_id'] ?? null
            ]);
            
            // Record ZB validation failure
            if (isset($param['leadspeek_api_id']) && isset($param['leadspeek_type']) && isset($param['md5param'])) {
                $this->UpsertFailedLeadRecord([
                    'function' => 'zb_validation',
                    'type' => 'error',
                    'blocked_type' => 'zerobounce',
                    'description' => json_encode([
                        'message' => $e->getMessage(),
                        'fallback_from' => $param['fallback_from'] ?? null
                    ]),
                    'leadspeek_api_id' => $param['leadspeek_api_id'],
                    'email_encrypt' => $param['md5param'],
                    'leadspeek_type' => $param['leadspeek_type'],
                    'email' => $email,
                    'clean_file_id' => $param['clean_file_id'] ?? null, // ini hanya ada di clean_id
                ]);
            }
            
            return "";
        }catch (Exception $e) {
            Log::error("ZeroBounce API Unexpected Error", [
                'email' => $email,
                'error' => $e->getMessage(),
                'leadspeek_api_id' => $param['leadspeek_api_id'] ?? null
            ]);
            
            // Record ZB validation failure
            if (isset($param['leadspeek_api_id']) && isset($param['leadspeek_type']) && isset($param['md5param'])) {
                $this->UpsertFailedLeadRecord([
                    'function' => 'zb_validation',
                    'type' => 'error',
                    'blocked_type' => 'zerobounce',
                    'description' => json_encode([
                        'message' => $e->getMessage(),
                        'fallback_from' => $param['fallback_from'] ?? null
                    ]),
                    'leadspeek_api_id' => $param['leadspeek_api_id'],
                    'email_encrypt' => $param['md5param'],
                    'leadspeek_type' => $param['leadspeek_type'],
                    'email' => $email,
                    'clean_file_id' => $param['clean_file_id'] ?? null, // ini hanya ada di clean_id
                ]);
            }
            
            return "";
        }
    }

    /**
     * Get the current rate limit from cache or config
     * Supports dynamic rate limit adjustment based on API responses
     * 
     * @return int Current rate limit per second
     */
    private function getTrueListRateLimit() {
        info(__FUNCTION__);
        // Check if we have a dynamically discovered rate limit in cache
        $dynamicRateLimit = Cache::store('redis')->get('truelist_dynamic_rate_limit');
        
        if ($dynamicRateLimit !== null) {
            Log::info("TrueList: Using dynamically discovered rate limit", [
                'rate_limit' => $dynamicRateLimit,
                'source' => 'cache'
            ]);
            return $dynamicRateLimit;
        }
        
        // Fall back to configuration
        $configRateLimit = config('services.truelist.rate_limit_per_second', 10);
        Log::info("TrueList: Using configured rate limit", [
            'rate_limit' => $configRateLimit,
            'source' => 'config'
        ]);
        
        return $configRateLimit;
    }
    
    /**
     * Update rate limit dynamically based on API response headers
     * 
     * @param \Psr\Http\Message\ResponseInterface $response API response
     * @return void
     */
    private function updateTrueListRateLimitFromHeaders($response) {
        // Check for common rate limit headers
        $headers = $response->getHeaders();
        info(__FUNCTION__, ['headers' => $headers]);
        
        // X-RateLimit-Limit: Total requests allowed in the time window
        if (isset($headers['X-RateLimit-Limit'])) {
            $limit = (int) $headers['X-RateLimit-Limit'][0];
            
            // Assume the limit is per second (or adjust based on X-RateLimit-Reset if available)
            if ($limit > 0) {
                Cache::store('redis')->put('truelist_dynamic_rate_limit', $limit, 3600); // Store for 1 hour
                
                Log::info("TrueList: Rate limit discovered from API headers", [
                    'rate_limit' => $limit,
                    'header' => 'X-RateLimit-Limit'
                ]);
            }
        }
        
        // Log remaining requests for monitoring
        if (isset($headers['X-RateLimit-Remaining'])) {
            $remaining = (int) $headers['X-RateLimit-Remaining'][0];
            Log::info("TrueList: Rate limit remaining", [
                'remaining' => $remaining
            ]);
        }
    }
    
    /**
     * Wait for rate limit availability using cache-based token bucket algorithm
     * This ensures we don't exceed TrueList API rate limits across all webhook instances
     * 
     * @param int $maxRequestsPerSecond Maximum requests allowed per second
     * @return void
     */
    private function waitForTrueListRateLimit($maxRequestsPerSecond = 10) {
        info(__FUNCTION__);
        $cacheKey = 'truelist_rate_limit_bucket';
        $currentTime = microtime(true);
        
        // Try to get or initialize the rate limit data
        $rateLimitData = Cache::store('redis')->get($cacheKey, [
            'tokens' => $maxRequestsPerSecond,
            'last_update' => $currentTime
        ]);
        
        $timePassed = $currentTime - $rateLimitData['last_update'];
        
        // Refill tokens based on time passed
        $tokensToAdd = $timePassed * $maxRequestsPerSecond;
        $rateLimitData['tokens'] = min($maxRequestsPerSecond, $rateLimitData['tokens'] + $tokensToAdd);
        $rateLimitData['last_update'] = $currentTime;
        
        // If we don't have a token available, wait
        if ($rateLimitData['tokens'] < 1) {
            $waitTime = (1 - $rateLimitData['tokens']) / $maxRequestsPerSecond;
            $waitMicroseconds = (int)($waitTime * 1000000);
            
            Log::info("TrueList Rate Limiter: Waiting before API call", [
                'wait_seconds' => $waitTime,
                'current_tokens' => $rateLimitData['tokens'],
                'rate_limit_per_second' => $maxRequestsPerSecond,
                'delay_detik' => round($waitMicroseconds / 1000000, 2)
            ]);
            
            usleep($waitMicroseconds);
            
            // Update tokens after waiting
            $rateLimitData['tokens'] = 1;
            $rateLimitData['last_update'] = microtime(true);
        }
        
        // Consume one token
        $rateLimitData['tokens'] -= 1;
        
        // Store back to cache (expires in 60 seconds as a safety measure)
        Cache::store('redis')->put($cacheKey, $rateLimitData, 60);
    }

    public function true_list_validation($email,$param = []) {
        info('true_list_validation start', ['email' => $email, 'param' => $param]);
        $maxRetries = config('services.truelist.max_retries', 5); // Get from config or default to 5
        $attempt = 0;
        $baseDelay = 1; // Base delay in seconds
        $_leadspeek_type = isset($param['leadspeek_type']) ? $param['leadspeek_type'] : '';
        
        while ($attempt < $maxRetries) {
            try {
                info('true_list_validation try', ['attempt' => $attempt]);
                // Get current rate limit (dynamic or from config)
                $rateLimit = $this->getTrueListRateLimit();
                
                // Wait for rate limit availability before making the API call
                // Rate limit can be set via TRUE_LIST_RATE_LIMIT_PER_SECOND env variable
                // or will be dynamically discovered from API response headers
                $this->waitForTrueListRateLimit($rateLimit);
                
                $http = new Client([
                    'timeout' => 30, // Set a reasonable timeout
                    'connect_timeout' => 10,
                ]);
                $appkey = config('services.truelist.appkey');
                $apiURL = config('services.truelist.endpoint') . '/v1/verify_inline?email=' . urlencode($email);
                $options = [
                    'headers' => [
                        'Authorization' =>  $appkey
                    ]
                ];
                $response = $http->post($apiURL,$options);
                // Try to extract and store rate limit info from response headers
                $this->updateTrueListRateLimitFromHeaders($response);
                
                $response_decoded = json_decode($response->getBody());
                info(__FUNCTION__ . ' response', ['response_decoded' => $response_decoded]);
                if(isset($response_decoded->emails[0]->email_state) && ($response_decoded->emails[0]->email_state != "ok") && ($response_decoded->emails[0]->email_state != "email_invalid")){
                    if (isset($param['leadspeek_api_id']) && isset($param['leadspeek_type']) && isset($param['md5param'])) {
                        $this->UpsertFailedLeadRecord([
                            'function' => __FUNCTION__,
                            'type' => 'blocked',
                            'blocked_type' => 'truelist',
                            'description' => 'Truelist Status: ' . $response_decoded->emails[0]->email_state . '|Email',
                            'leadspeek_api_id' => $param['leadspeek_api_id'],
                            'email_encrypt' => $param['md5param'],
                            'leadspeek_type' => $param['leadspeek_type'],
                            'email' => $email,
                            'status' => $response_decoded->emails[0]->email_state,
                            'clean_file_id' => $param['clean_file_id'] ?? null, // ini hanya ada di clean_id
                        ]);
                    }

                    if (isset($param['leadspeek_api_id']) && $_leadspeek_type != 'clean_id') {
                        /** REPORT ANALYTIC */
                        $this->UpsertReportAnalytics($param['leadspeek_api_id'], 'enhance', 'truelist_details', $response_decoded->emails[0]->email_state);
                        /** REPORT ANALYTIC */
                    }

                    // Try ZB validation as fallback
                    Log::info("TrueList not OK, trying ZB validation as fallback", [
                        'email' => $email,
                        'leadspeek_api_id' => $param['leadspeek_api_id'] ?? null
                    ]);
                    
                    // Add fallback_from parameter to track this is a fallback call
                    $zbParam = $param;
                    $zbParam['fallback_from'] = 'truelist';
                    
                    $zbResult = $this->zb_validation($email, $zbParam);
                    if ($zbResult !== "") {
                        Log::info("ZB validation successful as fallback", [
                            'email' => $email,
                            'leadspeek_api_id' => $param['leadspeek_api_id'] ?? null
                        ]);
                        return $zbResult;
                    }
                    
                    return "";
                }
                return $response_decoded;
                
            } catch (RequestException $e) {
                info(__FUNCTION__ . ' masuk catch 1', ['error' => $e->getMessage()]);
                if (isset($param['leadspeek_api_id']) && isset($param['leadspeek_type']) && isset($param['md5param'])) {
                    $this->UpsertFailedLeadRecord([
                        'function' => 'true_list_validation',
                        'type' => 'error',
                        'blocked_type' => 'truelist',
                        'description' => json_encode($e->getMessage()),
                        'leadspeek_api_id' => $param['leadspeek_api_id'],
                        'email_encrypt' => $param['md5param'],
                        'leadspeek_type' => $param['leadspeek_type'],
                        'email' => $email,
                        'clean_file_id' => $param['clean_file_id'] ?? null,
                    ]);
                }
                return "";
            } catch (Exception $e) {
                info(__FUNCTION__ .' masuk catch 2', ['error' => $e->getMessage()]);
                $attempt++;
                
                // Try to get status code from any exception
                $statusCode = 0;
                $retryAfter = null;
                
                // Check if exception has a response (Guzzle exceptions)
                try {
                    // Check if this is a Guzzle exception with response
                    if ($e instanceof \GuzzleHttp\Exception\RequestException) {
                        if ($e->hasResponse()) {
                            $response = $e->getResponse();
                            $statusCode = $response->getStatusCode();
                            
                            // Try to extract rate limit info from error response headers
                            $this->updateTrueListRateLimitFromHeaders($response);
                            
                            // Check for Retry-After header
                            $headers = $response->getHeaders();
                            if (isset($headers['Retry-After'])) {
                                $retryAfter = (int) $headers['Retry-After'][0];
                            }
                        }
                    }
                } catch (\Exception $ex) {
                    // If we can't get status code, continue with statusCode = 0
                }
                
                // ONLY retry if status code is 429 (Rate Limit)
                if ($statusCode !== 429) {
                    // Log TrueList error
                    Log::error("TrueList API Error: Not retrying, trying ZB fallback", [
                        'email' => $email,
                        'status_code' => $statusCode,
                        'error' => $e->getMessage(),
                        'leadspeek_api_id' => $param['leadspeek_api_id'] ?? null
                    ]);
                    
                    if (isset($param['leadspeek_api_id']) && isset($param['leadspeek_type']) && isset($param['md5param'])) {
                        $this->UpsertFailedLeadRecord([
                            'function' => 'true_list_validation',
                            'type' => 'error',
                            'blocked_type' => 'truelist',
                            'description' => json_encode([
                                'status_code' => $statusCode,
                                'message' => $e->getMessage()
                            ]),
                            'leadspeek_api_id' => $param['leadspeek_api_id'],
                            'email_encrypt' => $param['md5param'],
                            'leadspeek_type' => $param['leadspeek_type'],
                            'email' => $email,
                            'clean_file_id' => $param['clean_file_id'] ?? null,
                        ]);
                    }

                    // Try ZB validation as fallback
                    Log::info("TrueList failed, trying ZB validation as fallback", [
                        'email' => $email,
                        'leadspeek_api_id' => $param['leadspeek_api_id'] ?? null
                    ]);
                    
                    // Add fallback_from parameter to track this is a fallback call
                    $zbParam = $param;
                    $zbParam['fallback_from'] = 'truelist';
                    
                    $zbResult = $this->zb_validation($email, $zbParam);
                    if ($zbResult !== "") {
                        Log::info("ZB validation successful as fallback", [
                            'email' => $email,
                            'leadspeek_api_id' => $param['leadspeek_api_id'] ?? null
                        ]);
                        return $zbResult;
                    }
                    
                    return "";
                }
                
                // Check if max retries reached (only for 429 errors)
                if ($attempt >= $maxRetries) {
                    Log::warning("TrueList API Error: Max retries reached", [
                        'email' => $email,
                        'status_code' => $statusCode,
                        'error' => $e->getMessage(),
                        'leadspeek_api_id' => $param['leadspeek_api_id'] ?? null,
                        'attempts' => $attempt
                    ]);
                    
                    if (isset($param['leadspeek_api_id']) && isset($param['leadspeek_type']) && isset($param['md5param'])) {
                        $this->UpsertFailedLeadRecord([
                            'function' => 'true_list_validation',
                            'type' => 'rate_limit_exceeded',
                            'blocked_type' => 'truelist',
                            'description' => json_encode([
                                'status_code' => $statusCode,
                                'message' => $e->getMessage(),
                                'attempts' => $attempt
                            ]),
                            'leadspeek_api_id' => $param['leadspeek_api_id'],
                            'email_encrypt' => $param['md5param'],
                            'leadspeek_type' => $param['leadspeek_type'],
                            'email' => $email,
                        ]);
                    }
                    return "";
                }
                
                // Calculate delay: use Retry-After if available, otherwise exponential backoff
                if ($retryAfter !== null && $retryAfter > 0) {
                    $delay = $retryAfter;
                    $jitter = 0;
                    
                    Log::info("TrueList API: Using Retry-After header", [
                        'email' => $email,
                        'status_code' => $statusCode,
                        'attempt' => $attempt,
                        'retry_after_seconds' => $retryAfter,
                        'leadspeek_api_id' => $param['leadspeek_api_id'] ?? null
                    ]);
                } else {
                    // Exponential backoff with jitter
                    $delay = $baseDelay * pow(2, $attempt - 1);
                    $jitter = rand(0, 1000) / 1000;
                    
                    Log::info("TrueList API: Retrying with exponential backoff", [
                        'email' => $email,
                        'status_code' => $statusCode,
                        'error' => $e->getMessage(),
                        'attempt' => $attempt,
                        'delay_seconds' => ($delay + $jitter),
                        'leadspeek_api_id' => $param['leadspeek_api_id'] ?? null
                    ]);
                }
                
                $totalDelay = ($delay + $jitter) * 1000000;
                usleep($totalDelay);
                continue;
            }
        }
        return "";
    }


    public function bigBDM_getToken() {
        $webhookController = App::make(WebhookController::class);
        $_cacheKey = 'bigdbm_token';
        $acces_token = '';
        $check_cache = $webhookController->cacheHasResult($_cacheKey);
        if ($check_cache) {
            $acces_token = $webhookController->cacheGetResult($_cacheKey);
            return $acces_token;
        }else {
            $global_setting = GlobalSettings::where('setting_name','bigdbm_token')->first();
            if (isset($global_setting->setting_value) && !is_null($global_setting->setting_value) && $global_setting != '') {
                $acces_token = $global_setting->setting_value;
                return $acces_token;
            }else {

                $http = new \GuzzleHttp\Client;
                $bigbdm_clientID = config('services.bigbdm.clientid');
                $bigbdm_secretKey = config('services.bigbdm.clientsecret');
                $bigbdm_url_token = config('services.bigbdm.endpoint_token');
        
                try {

                    $formoptions = [
                        'form_params' => [
                            'grant_type' => 'client_credentials',
                            'scope' => '',
                            'client_id' => $bigbdm_clientID,
                            'client_secret' => $bigbdm_secretKey,
                        ],
                    ]; 
                    $tokenresponse = $http->post($bigbdm_url_token,$formoptions);
                    $result =  json_decode($tokenresponse->getBody());

                    $access_token = $result->access_token;

                    //update token in global settings
                    $global_setting = GlobalSettings::where('setting_name','bigdbm_token')->first();
                    if (!empty($global_setting) && isset($global_setting->setting_value)) {
                        $global_setting->setting_value = $access_token;
                        $global_setting->save();
                    }else {
                        $save = GlobalSettings::create([
                            'setting_name' => 'bigdbm_token',
                            'setting_value' => $access_token,
                        ]);
                    }
                    //update token in global settings

                    $check_cache = $webhookController->cacheHasResult($_cacheKey);
                    if ($check_cache) {
                        $delete_cache = $webhookController->cacheForget($_cacheKey);
                        $cache_token = $webhookController->cacheQueryResult($_cacheKey,36000,function () use ($access_token) {
                            return $access_token;
                        });
                    }else {
                        $cache_token = $webhookController->cacheQueryResult($_cacheKey,36000,function () use ($access_token) {
                            return $access_token;
                        });
                    }

                    return $access_token;
                }catch (\GuzzleHttp\Exception\BadResponseException $e) {
                    Log::info("BigDBM Create Token failed");
                    Log::info("BigDBM Create Token error msg: " . $e->getMessage());
                    $this->UpsertFailedLeadRecord([
                        'function' => 'bigBDM_getToken',
                        'type' => 'error',
                        'description' => 'Failed Get Token Webhook Search OR Site ID with errmsg: ' . $e->getMessage(),
                        'leadspeek_api_id' => '',
                        'email_encrypt' => '',
                        'url' => '',
                    ]);
                    return "";
                }
            }
        }
    }

    public function bigDBM_B2B($md5, $leadspeek_api_id, $leadspeek_type){
        $bigdbm_url_b2b = config('services.bigbdm.endpoint_b2b');

        try {
            $bigdbm = app(BigDBM::class);

            $token = $bigdbm->getAccessTokenB2B();  
            $result = $bigdbm->getResultListB2B([
                'RequestId' => uniqid(),
                'ObjectList' => [$md5],
                "OutputId" => 8
    
            ],$token);

            if(count((array)$result['returnData']) == 0) {
                $this->UpsertFailedLeadRecord([
                    'function' => 'bigBDM_B2B',
                    'type' => 'empty',
                    'description' => 'Lead Empty',
                    'leadspeek_api_id' => $leadspeek_api_id,
                    'email_encrypt' => $md5,
                    'url' => $bigdbm_url_b2b,
                    'leadspeek_type' => $leadspeek_type,
                ]);
            }
    
            return $result['returnData'];
        } catch (\Throwable $e) {
            
            FailedRecord::create([
                'email_encrypt' => $md5,
                'leadspeek_api_id' => $leadspeek_api_id,
                'description' => 'Failed to fetch data in bigDBM_B2B function',
            ]);
            
            $this->UpsertReportAnalytics($leadspeek_api_id,$leadspeek_type,'getleadfailed');
            $this->UpsertReportAnalytics($leadspeek_api_id,$leadspeek_type,'getleadfailed_bigbdmmd5');
            
            $this->UpsertFailedLeadRecord([
                'function' => 'bigDBM_B2B',
                'type' => 'error',
                'description' => json_encode($e->getMessage()),
                'leadspeek_api_id' => $leadspeek_api_id,
                'email_encrypt' => $md5,
                'url' => $bigdbm_url_b2b,
                'leadspeek_type' => $leadspeek_type,
            ]);
            
            return array();

        }
    }

    public function bigBDM_MD5($md5email,$leadspeek_api_id="",$leadspeektype = "",$is_advance = false) {
        $http = new \GuzzleHttp\Client;
        $bigbdm_url_md5 = config('services.bigbdm.endpoint_md5');

        $accessToken = $this->bigBDM_getToken();
        
        try {
            // Log::info("bigBDM_MD5 start");
            // $output_id = $is_advance ? 10038 : 2;
            $output_id = $is_advance ? 10045 : 10044;
            $md5options = [
                'headers' => [
                    'Authorization' => 'Bearer ' . $accessToken,
                ],
                'json' => [
                    'RequestId' => uniqid(),
                    'ObjectList' => [$md5email],
                    'OutputId' => $output_id,
                ]
            ]; 
        
                $tokenresponse = $http->post($bigbdm_url_md5,$md5options);
                $result =  json_decode($tokenresponse->getBody());
                // Log::info("bigBDM_MD5 end", ['result' => $result]);

                if(count((array)$result->returnData) == 0) {
                    // $this->createFailedLeadRecord($md5email, $leadspeek_api_id, 'bigBDM_MD5', $bigbdm_url_md5, 'empty', 'Lead Empty');
                    $this->UpsertFailedLeadRecord([
                        'function' => 'bigBDM_MD5',
                        'type' => 'empty',
                        'description' => 'Lead Empty',
                        'leadspeek_api_id' => $leadspeek_api_id,
                        'email_encrypt' => $md5email,
                        'url' => $bigbdm_url_md5,
                        'leadspeek_type' => $leadspeektype,
                    ]);
                }

                return $result->returnData;
            }catch (\GuzzleHttp\Exception\BadResponseException $e) {
                // Log::info("bigBDM_MD5 error");

                FailedRecord::create([
                    'email_encrypt' => $md5email,
                    'leadspeek_api_id' => $leadspeek_api_id,
                    'description' => 'Failed to fetch data in bigBDM_MD5 function',
                ]);
                
                $this->UpsertReportAnalytics($leadspeek_api_id,$leadspeektype,'getleadfailed');
                $this->UpsertReportAnalytics($leadspeek_api_id,$leadspeektype,'getleadfailed_bigbdmmd5');
                
                // $this->createFailedLeadRecord($md5email, $leadspeek_api_id, 'bigBDM_MD5', $bigbdm_url_md5, 'error', json_encode($e->getMessage()));
                $this->UpsertFailedLeadRecord([
                    'function' => 'bigBDM_MD5',
                    'type' => 'error',
                    'description' => json_encode($e->getMessage()),
                    'leadspeek_api_id' => $leadspeek_api_id,
                    'email_encrypt' => $md5email,
                    'url' => $bigbdm_url_md5,
                    'leadspeek_type' => $leadspeektype,
                ]);
                
                return array();
            }
    }

    public function bigBDM_PII($_fname,$_lname,$_address,$_zip,$md5param = "",$leadspeek_api_id = "", $leadspeektype = "",$is_advance = false) {
        $http = new \GuzzleHttp\Client;
        $bigbdm_url_pii = config('services.bigbdm.endpoint_pii');

        $accessToken = $this->bigBDM_getToken();

        try {
            // Log::info("bigBDM_PII start");
            // $output_id = $is_advance ? 10038 : 2;
            $output_id = $is_advance ? 10045 : 10044;

            $piioptions = [
                'headers' => [
                    'Authorization' => 'Bearer ' . $accessToken,
                ],
                'json' => [
                    'RequestId' => uniqid(),
                    'ObjectList' => [
                        [
                            'FirstName' => $_fname,
                            'LastName' => $_lname,
                            'Address' => $_address,
                            'Zip' => $_zip,
                            'Sequence' => uniqid()
                        ]
                    ],
                    'OutputId' => $output_id
                ]
            ]; 

            $tokenresponse = $http->post($bigbdm_url_pii,$piioptions);
            $result =  json_decode($tokenresponse->getBody());

            // Log::info("bigBDM_PII end", ['result' => $result]);

            if(count((array)$result->returnData) == 0) {
                // $this->createFailedLeadRecord($md5param, $leadspeek_api_id, 'bigBDM_PII', $bigbdm_url_pii, 'empty', 'Lead Empty');
                $this->UpsertFailedLeadRecord([
                    'function' => 'bigBDM_PII',
                    'type' => 'empty',
                    'blocked_type' => 'empty',
                    'description' => 'Lead Empty',
                    'leadspeek_api_id' => $leadspeek_api_id,
                    'email_encrypt' => $md5param,
                    'url' => $bigbdm_url_pii,
                    'leadspeek_type' => $leadspeektype,
                ]);
            }

            return $result->returnData;
        }catch (\GuzzleHttp\Exception\BadResponseException $e) {
            // Log::info("bigBDM_PII error");

            FailedRecord::create([
                'email_encrypt' => $md5param,
                'leadspeek_api_id' => $leadspeek_api_id,
                'description' => 'Failed to fetch data in bigBDM_PII function',
            ]);

            $this->UpsertReportAnalytics($leadspeek_api_id,$leadspeektype,'getleadfailed');
            $this->UpsertReportAnalytics($leadspeek_api_id,$leadspeektype,'getleadfailed_bigbdmpii');
            
            // $this->createFailedLeadRecord($md5param, $leadspeek_api_id, 'bigBDM_PII', $bigbdm_url_pii, 'error', json_encode($e->getMessage()));
            $this->UpsertFailedLeadRecord([
                'function' => 'bigBDM_PII',
                'type' => 'error',
                'blocked_type' => 'empty',
                'description' => json_encode($e->getMessage()),
                'leadspeek_api_id' => $leadspeek_api_id,
                'email_encrypt' => $md5param,
                'url' => $bigbdm_url_pii,
                'leadspeek_type' => $leadspeektype,
            ]);

            return array();
        }
    }

    public function getCompanyRootInfo($companyID) {
        $company = Company::select('id','logo','simplifi_organizationid','domain','subdomain','template_bgcolor','box_bgcolor','font_theme','login_image','client_register_image','agency_register_image','company_name','phone','company_address','company_city','company_zip','company_state_name')
                            ->where('id','=',$companyID)
                            ->where('approved','=','T')
                            ->get();
        return $company[0];
    }

    public function getDefaultDomainEmail($companyID) {
        $defaultdomain = "sitesettingsapi.com";
        $customsmtp = CompanySetting::where('company_id',trim($companyID))->whereEncrypted('setting_name','customsmtpmenu')->get();
        if (count($customsmtp) > 0) {
            $csmtp = json_decode($customsmtp[0]['setting_value']);
            if (!$csmtp->default) {
                $tmpdomain = explode('@',$csmtp->username);
                $defaultdomain = $tmpdomain[1];
            }else{
                $rootsmtp = CompanySetting::where('company_id',$companyID)->whereEncrypted('setting_name','rootsmtp')->get();
                if (count($rootsmtp) > 0) {
                    $smtproot = json_decode($rootsmtp[0]['setting_value']);
                    $tmpdomain = explode('@',$smtproot->username);
                    $defaultdomain = $tmpdomain[1];
                }
            }
        }else{
            $rootsmtp = CompanySetting::where('company_id',$companyID)->whereEncrypted('setting_name','rootsmtp')->get();
            if (count($rootsmtp) > 0) {
                $smtproot = json_decode($rootsmtp[0]['setting_value']);
                $tmpdomain = explode('@',$smtproot->username);
                $defaultdomain = $tmpdomain[1];
            }
        }

        return $defaultdomain;
    }

    /* MAILBOXPOWER FUNCTIONS */
    public function mbp_createContact($apikey = "", $groupid = "", $companyname = "", $id ="", $leadspeek_api_id = "", $clickdate = "", $firstname = "", $lastname = "", $email = "", $email2 = "", $phone = "", $phone2 = "", $address1 = "", $address2 = "", $city = "", $state = "", $zipcode = "", $keyword = "", $errMsg = "",$leadspeek_type = "",$md5param = "", $additional_fields = [])
    {
        if(!empty($apikey) && trim($apikey) != '')
        {
            $http = new \GuzzleHttp\Client;

            /* DATA REQUIRED */
            $firstname = (!empty($firstname) && trim($firstname) != '') ? $firstname : " "; // data ini required di mailboxpower. jika string kosong, null maka akan menyebabkan bad request. minimal 2 karakter spasi
            $lastname = (!empty($lastname) && trim($lastname) != '') ? $lastname : " "; // data ini required di mailboxpower. jika string kosong, null maka akan menyebabkan bad request. minimal 2 karakter spasi
            $address1 = (!empty($address1) && trim($address1) != '') ? $address1 : " "; // data ini required di mailboxpower. jika string kosong, null maka akan menyebabkan bad request. minimal 2 karakter spasi
            $city = (!empty($city) && trim($city) != '') ? $city : " "; // data ini required di mailboxpower. jika string kosong, null maka akan menyebabkan bad request. minimal 2 karakter spasi
            $state = (!empty($state) && trim($state) != '') ? $state : " "; // data ini required di mailboxpower. jika string kosong, null maka akan menyebabkan bad request. minimal 2 karakter spasi
            // Log::info(['firstname' => $firstname, 'lastname' => $lastname, 'address1' => $address1, 'city' => $city, 'state' => $state]);
            /* DATA REQUIRED */

            /* ATTRIBUTE FOR REQUEST */
            $url = "https://www.mailboxpower.com/api/v3/contacts";
            $headers = [
                'APIKEY' => $apikey
            ];
            $multipart = [
                [
                    'name' => 'groupId',
                    'contents' =>  $groupid // jika group id tidak ditemukan, maka akan ditetapkan secara global
                ],
                [
                    // required
                    'name' => 'firstname',
                    'contents' => $firstname, // jika tidak ingin diisi maka berikan karakter spasi minimal 2, kalau string kosong atau null bad request
                ],
                [
                    // required
                    'name' => 'lastname',
                    'contents' => $lastname, // jika tidak ingin diisi maka berikan karakter spasi minimal 2, kalau string kosong atau null bad request
                ],
                [
                    'name' => 'company',
                    'contents' => '',
                    // 'contents' => $companyname,
                ],
                [
                    'name' => 'email',
                    'contents' => $email,
                ],
                [
                    'name' => 'phone',
                    'contents' => $phone
                ],
                [
                    // required
                    'name' => 'street',
                    'contents' => $address1 // jika tidak ingin diisi maka berikan karakter spasi minimal 2, kalau string kosong atau null bad request
                ],
                [
                    
                    'name' => 'street2',
                    'contents' => $address2
                ],
                [
                    // required
                    'name' => 'city',
                    'contents' => $city // jika tidak ingin diisi maka berikan karakter spasi minimal 2, kalau string kosong atau null bad request
                ],
                [
                    // required
                    'name' => 'state',
                    'contents' => $state // jika tidak ingin diisi maka berikan karakter spasi minimal 2, kalau string kosong atau null bad request
                ],
                [
                    'name' => 'postalcode',
                    'contents' => $zipcode
                ],
                [
                    'name' => 'country',
                    'contents' => 'US'
                ],
                [
                    'name' => 'forcenew',
                    'contents' => 'true' // jika ada data yang sebelumnya di mailboxpower, maka data yang sebelumnya tidak di replace. dan data yang baru akan di create
                ],
                [
                    'name' => 'merge1',
                    'contents' => "[Contact ID] : $id",
                ],
                [
                    'name' => 'merge2',
                    'contents' => "[Campaign ID] : $leadspeek_api_id",
                ],
                [
                    'name' => 'merge3',
                    'contents' => "[Click Date] : $clickdate",
                ],
                [
                    'name' => 'merge4',
                    'contents' => "[Email 2] : $email2",
                ],
                [
                    'name' => 'merge5',
                    'contents' => "[Phone 2] : $phone2",
                ],
                [
                    'name' => 'merge6',
                    'contents' => ($leadspeek_type != "local") ? "[Keyword] : $keyword" : "",
                ],
            ];

            if (!empty($additional_fields)) 
            {            
                $key = 7;
                foreach ($additional_fields as $field) 
                {
                    $multipart[] = [
                        'name'     => "merge{$key}",
                        'contents' => "[{$field['text']}] : {$field['value']}"
                    ];
                    $key++;
                }
            }

            $options = [
                'headers' => $headers,
                'multipart' => $multipart
            ];
            /* ATTRIBUTE FOR REQUEST */

            try
            {
                // Log::info(['options' => $options]);
                $response = $http->post($url, $options);
                $response = json_decode($response->getBody(), true);
                // Log::info('', ['response' => $response]);
            }
            catch (\Exception $e)
            {
                $message = $e->getMessage();
                Log::warning("MBP Create Contact ErrMsg: {$message} $errMsg");
            
                /* WRITE UPSER FAILED LEAD RECORD */
                $contactData = [
                    'groupId' => $groupid,
                    'firstname' => $firstname,
                    'lastname' => $lastname,
                    'company' => '',
                    'email' => $email,
                    'phone' => $phone,
                    'street' => $address1,
                    'street2' => $address2,
                    'city' => $city,
                    'state' => $state,
                    'postalcode' => $zipcode,
                    'country' => 'US',
                    'merge1' => "[Contact ID] : $id",
                    'merge2' => "[Campaign ID] : $leadspeek_api_id",
                    'merge3' => "[Click Date] : $clickdate",
                    'merge4' => "[Email 2] : $email2",
                    'merge5' => "[Phone 2] : $phone2",
                    'merge6' => "[Keyword] : $keyword",
                ];
                $this->UpsertFailedLeadRecord([
                    'function' => 'mbp_createContact',
                    'type' => 'blocked',
                    'blocked_type' => 'mailboxpower',
                    'description' => $message,
                    'leadspeek_api_id' => $leadspeek_api_id,
                    'leadspeek_type' => $leadspeek_type,
                    'email_encrypt' => $md5param,
                    'data_lead' => json_encode($contactData)
                ]);
            /* WRITE UPSER FAILED LEAD RECORD */
            }

        }
    }
    /* MAILBOXPOWER FUNCTIONS */

    /* CLICKFUNNELS FUNCTION */
    public function clickfunnels_GetListTags($api_key = '', $subdomain = '', $workspace_id = '', $data = [], $leadspeek_type = '', $md5param = '')
    {
        $http = new \GuzzleHttp\Client;
        $url = "https://{$subdomain}.myclickfunnels.com/api/v2/workspaces/{$workspace_id}/contacts/tags";

        $leadspeek_api_id = isset($data['custom_attributes']['id_campaign']) ? $data['custom_attributes']['id_campaign'] : '';
        $contact = [
            'id' => isset($data['custom_attributes']['id_contact']) ? $data['custom_attributes']['id_contact'] : '',
            'leadspeek_api_id' => $leadspeek_api_id,
            'clickdate' => isset($data['custom_attributes']['click_date']) ? $data['custom_attributes']['click_date'] : '',
            'firstname' => isset($data['first_name']) ? $data['first_name'] : '',
            'lastname' => isset($data['last_name']) ? $data['last_name'] : '',
            'email' => isset($data['email_address']) ? $data['email_address'] : '',
            'email2' => isset($data['custom_attributes']['email_2']) ? $data['custom_attributes']['email_2'] : '',
            'phone' => isset($data['phone_number']) ? $data['phone_number'] : '',
            'phone2' => isset($data['custom_attributes']['phone_2']) ? $data['custom_attributes']['phone_2'] : '',
            'address1' => isset($data['custom_attributes']['address_1']) ? $data['custom_attributes']['address_1'] : '',
            'address2' => isset($data['custom_attributes']['address_2']) ? $data['custom_attributes']['address_2'] : '',
            'city' => isset($data['custom_attributes']['city']) ? $data['custom_attributes']['city'] : '',
            'state' => isset($data['custom_attributes']['state']) ? $data['custom_attributes']['state'] : '',
            'zipcode' => isset($data['custom_attributes']['postal_code']) ? $data['custom_attributes']['postal_code'] : '',
            'keyword' => isset($data['custom_attributes']['keyword']) ? $data['custom_attributes']['keyword'] : '',
        ];
        
        if (trim($api_key) === '' || trim($subdomain) === '' || trim($workspace_id) === '') {
            return ['result' => 'failed', 'message' => 'please check required fields', 'status_code' => 400, 'data' => []];
        }

        try 
        {
            $response = $http->request('GET', $url, [
                'headers' => [
                    'accept' => 'application/json',
                    'authorization' => "Bearer {$api_key}",
                ],
            ]);

            $body = $response->getBody();
            $resData = json_decode($body, true);

            // info('clickfunnels_GetListTags', ['resData' => $resData]);

            return [
                'result' => 'success',
                'message' => 'Successfully create contact',
                'status_code' => 201,
                'data' => $resData,
            ];
        }
        catch (\GuzzleHttp\Exception\RequestException $e) {
            $errorMessage = "";

            if ($e->hasResponse()) {
                $statusCode = $e->getResponse()->getStatusCode();

                if($statusCode == 400){
                    $errorMessage = 'invalid subdomain';
                }

                if($statusCode == 401){
                    $errorMessage = 'API key missing or invalid';
                }

                if($statusCode == 404){
                    $errorMessage = 'invalid workspace';
                }

                if(in_array($statusCode, [400,401,402])){
                    /* WRITE UPSER FAILED LEAD RECORD */
                    $this->UpsertFailedLeadRecord([
                        'function' => 'clickfunnels_GetListTags',
                        'type' => 'blocked',
                        'blocked_type' => 'clickfunnels',
                        'description' => $errorMessage,
                        'leadspeek_api_id' => $leadspeek_api_id,
                        'leadspeek_type' => $leadspeek_type,
                        'email_encrypt' => $md5param,
                        'data_lead' => json_encode($contact)
                    ]);
                    /* WRITE UPSER FAILED LEAD RECORD */

                    return [
                        'result' => 'failed',
                        'message' => $errorMessage,
                        'status_code' => $statusCode,
                        'data' => []
                    ];
                }

                $errorMessage = $e->getResponse()->getBody()->getContents();
                $errorData = json_decode($errorMessage, true);
                $error = isset($errorData['error']) ? $errorData['error'] : 'Unknown error';
                
                /* WRITE UPSER FAILED LEAD RECORD */
                $this->UpsertFailedLeadRecord([
                    'function' => 'clickfunnels_GetListTags',
                    'type' => 'blocked',
                    'blocked_type' => 'clickfunnels',
                    'description' => $errorMessage,
                    'leadspeek_api_id' => $leadspeek_api_id,
                    'leadspeek_type' => $leadspeek_type,
                    'email_encrypt' => $md5param,
                    'data_lead' => json_encode($contact)
                ]);
                /* WRITE UPSER FAILED LEAD RECORD */

                return [
                    'result' => 'failed',
                    'message' => $error,
                    'status_code' => $statusCode,
                    'data' => []
                ];

            } else {
                $errorMessage = 'No response from server';

                /* WRITE UPSER FAILED LEAD RECORD */
                $this->UpsertFailedLeadRecord([
                    'function' => 'clickfunnels_GetListTags',
                    'type' => 'blocked',
                    'blocked_type' => 'clickfunnels',
                    'description' => $errorMessage,
                    'leadspeek_api_id' => $leadspeek_api_id,
                    'leadspeek_type' => $leadspeek_type,
                    'email_encrypt' => $md5param,
                    'data_lead' => json_encode($contact)
                ]);
                /* WRITE UPSER FAILED LEAD RECORD */

                return [
                    'result' => 'failed',
                    'message' => $errorMessage,
                    'status_code' => 500,
                    'data' => []
                ];
            }
        } catch (\Exception $e){
            $errorMessage = $e->getMessage();

            /* WRITE UPSER FAILED LEAD RECORD */
            $this->UpsertFailedLeadRecord([
                'function' => 'clickfunnels_GetListTags',
                'type' => 'blocked',
                'blocked_type' => 'clickfunnels',
                'description' => $errorMessage,
                'leadspeek_api_id' => $leadspeek_api_id,
                'leadspeek_type' => $leadspeek_type,
                'email_encrypt' => $md5param,
                'data_lead' => json_encode($contact)
            ]);
            /* WRITE UPSER FAILED LEAD RECORD */

            if(strpos($errorMessage, 'cURL error') === 0){
                return [
                    'result' => 'failed',
                    'message' => 'invalid subdomain',
                    'status_code' => 500,
                    'data' => []
                ];
            }

            return [
                'result' => 'failed',
                'message' => $errorMessage,
                'status_code' => 500,
                'data' => []
            ];
        }

    }

    public function clickfunnels_CreateContact($api_key = '', $subdomain = '', $workspace_id = '', $data = [], $leadspeek_type = '', $md5param = ''){
        $http = new \GuzzleHttp\Client;
        $url = "https://{$subdomain}.myclickfunnels.com/api/v2/workspaces/{$workspace_id}/contacts/upsert";

        $leadspeek_api_id = isset($data['custom_attributes']['id_campaign']) ? $data['custom_attributes']['id_campaign'] : '';
        $contact = [
            'id' => isset($data['custom_attributes']['id_contact']) ? $data['custom_attributes']['id_contact'] : '',
            'leadspeek_api_id' => $leadspeek_api_id,
            'clickdate' => isset($data['custom_attributes']['click_date']) ? $data['custom_attributes']['click_date'] : '',
            'firstname' => isset($data['first_name']) ? $data['first_name'] : '',
            'lastname' => isset($data['last_name']) ? $data['last_name'] : '',
            'email' => isset($data['email_address']) ? $data['email_address'] : '',
            'email2' => isset($data['custom_attributes']['email_2']) ? $data['custom_attributes']['email_2'] : '',
            'phone' => isset($data['phone_number']) ? $data['phone_number'] : '',
            'phone2' => isset($data['custom_attributes']['phone_2']) ? $data['custom_attributes']['phone_2'] : '',
            'address1' => isset($data['custom_attributes']['address_1']) ? $data['custom_attributes']['address_1'] : '',
            'address2' => isset($data['custom_attributes']['address_2']) ? $data['custom_attributes']['address_2'] : '',
            'city' => isset($data['custom_attributes']['city']) ? $data['custom_attributes']['city'] : '',
            'state' => isset($data['custom_attributes']['state']) ? $data['custom_attributes']['state'] : '',
            'zipcode' => isset($data['custom_attributes']['postal_code']) ? $data['custom_attributes']['postal_code'] : '',
            'keyword' => isset($data['custom_attributes']['keyword']) ? $data['custom_attributes']['keyword'] : '',
        ];

        if (trim($api_key) === '' || trim($subdomain) === '' || trim($workspace_id) === '') {
            return ['result' => 'failed', 'message' => 'please check required fields', 'status_code' => 400, 'data' => []];
        }

        try {
            $response = $http->request('POST', $url, [
                'headers' => [
                    'accept' => 'application/json',
                    'authorization' => "Bearer {$api_key}",
                    'content-type' => 'application/json',
                ],
                'json' => [
                    'contact' => $data
                ]
            ]);

            $body = $response->getBody();
            $resData = json_decode($body, true);

            // info('clickfunnels_CreateContact', ['resData' => $resData]);

            return [
                'result' => 'success',
                'message' => 'Successfully create contact',
                'status_code' => 201,
                'data' => $resData,
            ];
        } catch (\GuzzleHttp\Exception\RequestException $e) {
            $errorMessage = "";

            if ($e->hasResponse()) {
                $statusCode = $e->getResponse()->getStatusCode();

                if($statusCode == 400){
                    $errorMessage = 'invalid subdomain';
                }
                
                if($statusCode == 401){
                    $errorMessage = 'API key missing or invalid';
                }
                
                if($statusCode == 404){
                    $errorMessage = 'invalid workspace';
                }

                if(in_array($statusCode, [400,401,402])){
                    /* WRITE UPSER FAILED LEAD RECORD */
                    $this->UpsertFailedLeadRecord([
                        'function' => 'clickfunnels_CreateContact',
                        'type' => 'blocked',
                        'blocked_type' => 'clickfunnels',
                        'description' => $errorMessage,
                        'leadspeek_api_id' => $leadspeek_api_id,
                        'leadspeek_type' => $leadspeek_type,
                        'email_encrypt' => $md5param,
                        'data_lead' => json_encode($contact)
                    ]);
                    /* WRITE UPSER FAILED LEAD RECORD */

                    return [
                        'result' => 'failed',
                        'message' => $errorMessage,
                        'status_code' => $statusCode,
                        'data' => []
                    ];
                }

                $errorMessage = $e->getResponse()->getBody()->getContents();
                $errorData = json_decode($errorMessage, true);
                $error = isset($errorData['error']) ? $errorData['error'] : 'Unknown error';
                
                /* WRITE UPSER FAILED LEAD RECORD */
                $this->UpsertFailedLeadRecord([
                    'function' => 'clickfunnels_CreateContact',
                    'type' => 'blocked',
                    'blocked_type' => 'clickfunnels',
                    'description' => $errorMessage,
                    'leadspeek_api_id' => $leadspeek_api_id,
                    'leadspeek_type' => $leadspeek_type,
                    'email_encrypt' => $md5param,
                    'data_lead' => json_encode($contact)
                ]);
                /* WRITE UPSER FAILED LEAD RECORD */

                return [
                    'result' => 'failed',
                    'message' => $error,
                    'status_code' => $statusCode,
                    'data' => []
                ];

            } else {
                $errorMessage = 'No response from server';

                /* WRITE UPSER FAILED LEAD RECORD */
                $this->UpsertFailedLeadRecord([
                    'function' => 'clickfunnels_CreateContact',
                    'type' => 'blocked',
                    'blocked_type' => 'clickfunnels',
                    'description' => $errorMessage,
                    'leadspeek_api_id' => $leadspeek_api_id,
                    'leadspeek_type' => $leadspeek_type,
                    'email_encrypt' => $md5param,
                    'data_lead' => json_encode($contact)
                ]);
                /* WRITE UPSER FAILED LEAD RECORD */

                return [
                    'result' => 'failed',
                    'message' => $errorMessage,
                    'status_code' => 500,
                    'data' => []
                ];
            }
        } catch (\Exception $e){
            $errorMessage = $e->getMessage();

            /* WRITE UPSER FAILED LEAD RECORD */
            $this->UpsertFailedLeadRecord([
                'function' => 'clickfunnels_CreateContact',
                'type' => 'blocked',
                'blocked_type' => 'clickfunnels',
                'description' => $errorMessage,
                'leadspeek_api_id' => $leadspeek_api_id,
                'leadspeek_type' => $leadspeek_type,
                'email_encrypt' => $md5param,
                'data_lead' => json_encode($contact)
            ]);
            /* WRITE UPSER FAILED LEAD RECORD */

            if(strpos($errorMessage, 'cURL error') === 0){
                return [
                    'result' => 'failed',
                    'message' => 'invalid subdomain',
                    'status_code' => 500,
                    'data' => []
                ];
            }

            return [
                'result' => 'failed',
                'message' => $errorMessage,
                'status_code' => 500,
                'data' => []
            ];
        }
    }

    public function clickfunnels_GetWorkSpaceId($api_key = '', $subdomain = '', $workspace_id = '', $data = [], $leadspeek_type = '', $md5param = ''){
        $http = new \GuzzleHttp\Client;
        $url = "https://{$subdomain}.myclickfunnels.com/api/v2/workspaces/{$workspace_id}";

        $leadspeek_api_id = isset($data['custom_attributes']['id_campaign']) ? $data['custom_attributes']['id_campaign'] : '';
        $contact = [
            'id' => isset($data['custom_attributes']['id_contact']) ? $data['custom_attributes']['id_contact'] : '',
            'leadspeek_api_id' => $leadspeek_api_id,
            'clickdate' => isset($data['custom_attributes']['click_date']) ? $data['custom_attributes']['click_date'] : '',
            'firstname' => isset($data['first_name']) ? $data['first_name'] : '',
            'lastname' => isset($data['last_name']) ? $data['last_name'] : '',
            'email' => isset($data['email_address']) ? $data['email_address'] : '',
            'email2' => isset($data['custom_attributes']['email_2']) ? $data['custom_attributes']['email_2'] : '',
            'phone' => isset($data['phone_number']) ? $data['phone_number'] : '',
            'phone2' => isset($data['custom_attributes']['phone_2']) ? $data['custom_attributes']['phone_2'] : '',
            'address1' => isset($data['custom_attributes']['address_1']) ? $data['custom_attributes']['address_1'] : '',
            'address2' => isset($data['custom_attributes']['address_2']) ? $data['custom_attributes']['address_2'] : '',
            'city' => isset($data['custom_attributes']['city']) ? $data['custom_attributes']['city'] : '',
            'state' => isset($data['custom_attributes']['state']) ? $data['custom_attributes']['state'] : '',
            'zipcode' => isset($data['custom_attributes']['postal_code']) ? $data['custom_attributes']['postal_code'] : '',
            'keyword' => isset($data['custom_attributes']['keyword']) ? $data['custom_attributes']['keyword'] : '',
        ];

        if (trim($api_key) === '' || trim($subdomain) === '' || trim($workspace_id) === '') {
            return response()->json(['result' => 'failed', 'message' => 'please check required fields', 'status_code' => 400, 'id' => null], 400);
        }

        try {
            $response = $http->request('GET', $url, [
                'headers' => [
                    'accept' => 'application/json',
                    'authorization' => "Bearer {$api_key}",
                ]
            ]);

            $body = $response->getBody();
            $resData = json_decode($body, true);

            // info('clickfunnels_GetWorkSpaceId', ['resData' => $resData]);

            return [
                'result' => 'success',
                'message' => 'Successfully get workspace id',
                'status_code' => 200,
                'id' => $resData['id']
            ];
        } catch (\GuzzleHttp\Exception\RequestException $e) {
            $errorMessage = "";

            if ($e->hasResponse()) {
                $statusCode = $e->getResponse()->getStatusCode();

                if($statusCode == 400){
                    $errorMessage = 'invalid subdomain';
                }
                
                if($statusCode == 401){
                    $errorMessage = 'API key missing or invalid';
                }
                
                if($statusCode == 404){
                    $errorMessage = 'invalid workspace';
                }

                if(in_array($statusCode, [400,401,402])){
                    /* WRITE UPSER FAILED LEAD RECORD */
                    $this->UpsertFailedLeadRecord([
                        'function' => 'clickfunnels_GetWorkSpaceId',
                        'type' => 'blocked',
                        'blocked_type' => 'clickfunnels',
                        'description' => $errorMessage,
                        'leadspeek_api_id' => $leadspeek_api_id,
                        'leadspeek_type' => $leadspeek_type,
                        'email_encrypt' => $md5param,
                        'data_lead' => json_encode($contact)
                    ]);
                    /* WRITE UPSER FAILED LEAD RECORD */

                    return [
                        'result' => 'failed',
                        'message' => $errorMessage,
                        'status_code' => $statusCode,
                        'data' => []
                    ];
                }

                $errorMessage = $e->getResponse()->getBody()->getContents();
                $errorData = json_decode($errorMessage, true);
                $error = isset($errorData['error']) ? $errorData['error'] : 'Unknown error';

                /* WRITE UPSER FAILED LEAD RECORD */
                $this->UpsertFailedLeadRecord([
                    'function' => 'clickfunnels_GetWorkSpaceId',
                    'type' => 'blocked',
                    'blocked_type' => 'clickfunnels',
                    'description' => $errorMessage,
                    'leadspeek_api_id' => $leadspeek_api_id,
                    'leadspeek_type' => $leadspeek_type,
                    'email_encrypt' => $md5param,
                    'data_lead' => json_encode($contact)
                ]);
                /* WRITE UPSER FAILED LEAD RECORD */

                return [
                    'result' => 'failed',
                    'message' => $error,
                    'status_code' => $statusCode,
                    'id' => null
                ];
            } else {
                $errorMessage = 'No response from server';

                /* WRITE UPSER FAILED LEAD RECORD */
                $this->UpsertFailedLeadRecord([
                    'function' => 'clickfunnels_GetWorkSpaceId',
                    'type' => 'blocked',
                    'blocked_type' => 'clickfunnels',
                    'description' => $errorMessage,
                    'leadspeek_api_id' => $leadspeek_api_id,
                    'leadspeek_type' => $leadspeek_type,
                    'email_encrypt' => $md5param,
                    'data_lead' => json_encode($contact)
                ]);
                /* WRITE UPSER FAILED LEAD RECORD */

                return [
                    'result' => 'failed',
                    'message' => $errorMessage,
                    'status_code' => 500,
                    'id' => null
                ];
            }
        } catch (\Exception $e){
            $errorMessage = $e->getMessage();

            /* WRITE UPSER FAILED LEAD RECORD */
            $this->UpsertFailedLeadRecord([
                'function' => 'clickfunnels_GetWorkSpaceId',
                'type' => 'blocked',
                'blocked_type' => 'clickfunnels',
                'description' => $errorMessage,
                'leadspeek_api_id' => $leadspeek_api_id,
                'leadspeek_type' => $leadspeek_type,
                'email_encrypt' => $md5param,
                'data_lead' => json_encode($contact)
            ]);
            /* WRITE UPSER FAILED LEAD RECORD */

            if(strpos($errorMessage, 'cURL error') === 0){
                return [
                    'result' => 'failed',
                    'message' => 'invalid subdomain',
                    'status_code' => 500,
                    'id' => null
                ];
            }

            return [
                'result' => 'failed',
                'message' => $errorMessage ,
                'status_code' => 500,
                'id' => null
            ];
        }
    }
    /* CLICKFUNNELS FUNCTION */

    /** GOHIGHLEVELV2 FUNCTIONS */
    public function gohighlevelv2GetTokensDB($company_id = "")
    {
        // info('start function gohighlevelv2GetTokensDB');
        $integration = IntegrationSettings::where('company_id','=',$company_id)
                                          ->where('integration_slug','=','gohighlevel')
                                          ->first();
                                          
        $tokens = isset($integration->tokens) ? json_decode($integration->tokens, true) : [];
        $access_token = isset($tokens['access_token']) ? $tokens['access_token'] : '';
        $refresh_token = isset($tokens['refresh_token']) ? $tokens['refresh_token'] : '';
        $location_id = isset($tokens['locationId']) ? $tokens['locationId'] : '';

        return [
            'access_token' => $access_token,
            'refresh_token' => $refresh_token,
            'location_id' => $location_id
        ];
    }

    public function gohighlevelv2RefreshToken($company_id = "", $refresh_token = "")
    {
        // info('start function gohighlevelv2RefreshToken');
        $http = new \GuzzleHttp\Client;

        $client_id = config('services.gohighlevelv2.client_id');
        $client_secret = config('services.gohighlevelv2.client_secret');

        try 
        {
            $url = "https://services.leadconnectorhq.com/oauth/token";
            $form_params = [
                'client_id' => $client_id,
                'client_secret' => $client_secret,
                'grant_type' => 'refresh_token',
                'refresh_token' => $refresh_token,
            ];
            $headers = [
                'Accept' => 'application/json',
                'Content-Type' => 'application/x-www-form-urlencoded',
            ];
            $parameter = [
                'form_params' => $form_params,
                'headers' => $headers
            ];

            $response = $http->post($url, $parameter);
            $response = json_decode($response->getBody(), true);

            $access_token = isset($response['access_token']) ? $response['access_token'] : '';
            $refresh_token = isset($response['refresh_token']) ? $response['refresh_token'] : '';
            $tokens_encode = json_encode($response, JSON_UNESCAPED_SLASHES);

            IntegrationSettings::where('company_id','=',$company_id)
                               ->where('integration_slug','=','gohighlevel')
                               ->update([
                                    'tokens' => $tokens_encode
                               ]);
            // info('start function gohighlevelv2RefreshToken try 1.1', [
            //     'status' => 'success',
            //     'access_token' => $access_token,
            //     'refresh_token' => $refresh_token
            // ]);
            return [
                'status' => 'success',
                'access_token' => $access_token,
                'refresh_token' => $refresh_token
            ];
        }
        catch (\GuzzleHttp\Exception\BadResponseException $e)
        {
            // info('start function gohighlevelv2RefreshToken catch 1.1', [
            //     'status' => 'error',
            //     'message' => $e->getMessage()
            // ]);
            return [
                'status' => 'error',
                'message' => $e->getMessage()
            ];
        }
    }

    public function gohighlevelv2GetContact($company_id = "", &$access_token = "", &$refresh_token = "", $location_id = "", $email = "")
    {
        $http = new \GuzzleHttp\Client;
        $retry = false;
        
        do 
        {
            try 
            {
                $url = "https://services.leadconnectorhq.com/contacts";
                $headers = [
                    'Accept' => 'application/json',
                    'Authorization' => "Bearer {$access_token}",
                    'Version' => '2021-07-28',
                ];
                $query = [
                    'query' => $email,
                    'locationId' => $location_id
                ];
                $parameter = [
                    'headers' => $headers,
                    'query' => $query,
                ];
    
                $response = $http->get($url, $parameter);
                $response = json_decode($response->getBody(), true);
                // info('gohighlevelv2GetContact_try 1.1', [
                //     // 'response' => $response
                // ]);
    
                return [
                    'status' => 'success',
                    'data' => $response
                ];
            }
            catch (\GuzzleHttp\Exception\BadResponseException $e)
            {
                /* REFRESH TOKEN */
                $code = $e->getResponse()->getStatusCode();
                // info('gohighlevelv2GetContact_catch 2.1', ['code' => $code, 'retry' => $retry, 'error' => $e->getMessage()]);
                if($code == 401 && !$retry) 
                {
                    $retry = true;
                    // info('gohighlevelv2GetContact_catch 2.2', [
                    //     'company_id' => $company_id,
                    //     'refresh_token' => $refresh_token,
                    // ]);
                    $refresh_result = $this->gohighlevelv2RefreshToken($company_id, $refresh_token);
                    // info('gohighlevelv2GetContact_catch 2.3', ['refresh_result' => $refresh_result]);
                    if (isset($refresh_result['status']) && $refresh_result['status'] === 'success')
                    {
                        // info('gohighlevelv2GetContact_catch 2.4');
                        $access_token = $refresh_result['access_token'];
                        $refresh_token = $refresh_result['refresh_token'];
                        continue; // ulangi process
                    }
                    else
                    {
                        // info('gohighlevelv2GetContact_catch 2.5');
                        return [
                            'status' => 'error',
                            'message' => $e->getMessage(),
                        ];
                    }
                }
                /* REFRESH TOKEN */

                return [
                    'status' => 'error',
                    'message' => $e->getMessage(),
                ];
            }
        } while ($retry);
    }

    public function gohighlevelv2CreateContact($company_id = "", &$access_token = "", &$refresh_token = "", $location_id = "", $id = "", $click_date = "", $first_name = "", $last_name = "", $email = "", $email2 = "", $phone = "", $phone2 = "", $address = "", $address2, $city = "", $state = "", $zipcode = "", $keyword = "", $tags = [], $campaign_id = "", $err_msg = "", $leadspeek_type = "", $md5_param = "", $additional_fields = [])
    {
        // info('start function gohighlevelv2CreateContact',[
        //     'company_id' => $company_id,
        //     'access_token' => $access_token,
        //     'refresh_token' => $refresh_token,
        //     'location_id' => $location_id,
        //     'id' => $id,
        //     'click_date' => $click_date,
        //     'first_name' => $first_name,
        //     'last_name' => $last_name,
        //     'email' => $email,
        //     'email2' => $email2,
        //     'phone' => $phone,
        //     'phone2' => $phone2,
        //     'address' => $address,
        //     'address2' => $address2,
        //     'city' => $city,
        //     'state' => $state,
        //     'zipcode' => $zipcode,
        //     'keyword' => $keyword,
        //     'tags' => $tags,
        //     'campaign_id' => $campaign_id,
        //     'err_msg' => $err_msg,
        //     'leadspeek_type' => $leadspeek_type,
        //     'md5_param' => $md5_param,
        //     'additional_fields' => $additional_fields,
        // ]);

        $http = new \GuzzleHttp\Client;
        $retry = false;
        $comset_name = "gohlcustomfields";

        /** GET IF CUSTOM FIELD ALREADY EXIST */
        $company_name = "";
        $email2Id = "";
        $phone2Id = "";
        $address2Id = "";
        $keywordId = "";
        //$urlId = "";
        $contactId = "";
        $clickDateId = "";
        
        $customfields = CompanySetting::where('company_id','=',$company_id)->whereEncrypted('setting_name',$comset_name)->get();
        if (count($customfields) > 0) 
        {
            $_customfields = json_decode($customfields[0]['setting_value']);
            $email2Id = (isset($_customfields->email2Id))?$_customfields->email2Id:'';
            $phone2Id = (isset($_customfields->phone2Id))?$_customfields->phone2Id:'';
            $address2Id = (isset($_customfields->address2Id))?$_customfields->address2Id:'';
            $keywordId = (isset($_customfields->keywordId))?$_customfields->keywordId:'';
            //$urlId = (isset($_customfields->urlId))?$_customfields->urlId:'';
            $contactId = (isset($_customfields->contactId))?$_customfields->contactId:'';
            $clickDateId = (isset($_customfields->clickDateId))?$_customfields->clickDateId:'';
            if (!empty($additional_fields)) 
            {
                foreach ($additional_fields as &$field) 
                {
                    $field['ghl_id'] = (isset($_customfields->{$field['db_id']})) ? $_customfields->{$field['db_id']} : '' ;
                }
                unset($field);
            }
        }

        $custom_fields = [
            [
                'id' => $contactId,
                'field_value' => $id
            ],
            [
                'id' => $clickDateId,
                'field_value' => $click_date
            ],
            [
                'id' => $email2Id,
                'field_value' => $email2
            ],
            [
                'id' => $phone2Id,
                'field_value' => $phone2
            ],
            [
                'id' => $address2Id,
                'field_value' => $address2
            ],
            [
                'id' => $keywordId,
                'field_value' => $keyword
            ],
            // [
            //     'id' => $urlId,
            //     'field_value' => $Url
            // ]
        ];

        if (is_array($additional_fields) && !empty($additional_fields)) 
        {
            foreach ($additional_fields as $fields) 
            {      
                if (isset($fields['ghl_id']) && $fields['value']) 
                {
                    $custom_fields[] = [
                        'id' => $fields['ghl_id'],
                        'field_value' => $fields['value']
                    ];
                }
                if (isset($fields['db_id']) && strtolower($fields['db_id']) == 'companynameid') 
                {
                    $company_name = $fields['value'];
                }
            }
        }
        // info('gohighlevelv2CreateContact', ['custom_fields' => $custom_fields]);
        /** GET IF CUSTOM FIELD ALREADY EXIST */

        do 
        {
            try
            {
                // process create contact
                // info('gohighlevelv2CreateContact_try 1.1');
                $url = "https://services.leadconnectorhq.com/contacts/upsert";
                $json = [
                    'locationId' => $location_id,
                    'firstName' => $first_name,
                    'lastName' => $last_name,
                    'name' => "{$first_name} {$last_name}",
                    'email' => $email,
                    'phone' => $phone,
                    'address1' => $address,
                    'country' => 'US',
                    'city' => $city,
                    'state' => $state,
                    'postalCode' => $zipcode,
                    'customFields' => $custom_fields,
                    'source' => "Campaign ID : #{$campaign_id}",
                    // 'tags' => $tags, // saat pertama kali create contact, tags tidak perlu dikirim
                    'companyName' => $company_name
                ];
                $headers = [
                    'Accept' => 'application/json',
                    'Authorization' => "Bearer {$access_token}",
                    'Content-Type' => 'application/json',
                    'Version' => '2021-07-28',
                ];
                $parameter = [
                    'json' => $json,
                    'headers' => $headers
                ];
                $createContact = $http->post($url, $parameter);
                $createContactResponse = json_decode($createContact->getBody()->getContents(), true);
                // info('gohighlevelv2CreateContact_try 1.2', ['createContactResponse' => $createContactResponse]);

                // process update contact, untuk menambahkan tag
                $contactIdExisting = isset($createContactResponse['contact']['id']) ? $createContactResponse['contact']['id'] : '';
                $tagsExisting = (isset($createContactResponse['contact']['tags']) && is_array($createContactResponse['contact']['tags'])) ? $createContactResponse['contact']['tags'] : [];
                $tags = array_values(array_unique(array_merge($tags, $tagsExisting)));
                // info('gohighlevelv2CreateContact_try 1.3', ['contactIdExisting' => $contactIdExisting, 'tagsExisting' => $tagsExisting, 'tags' => $tags]);
                if(!empty($contactIdExisting) && !empty($tags)){
                    $url = "https://services.leadconnectorhq.com/contacts/{$contactIdExisting}";
                    $json = [
                        'tags' => $tags, // saat update contact, tags perlu dikirim
                    ];
                    $parameter = [
                        'json' => $json,
                        'headers' => $headers
                    ];
                    $updateContact = $http->put($url, $parameter);
                    $updateContactResponse = json_decode($updateContact->getBody()->getContents(), true);
                    // info('gohighlevelv2CreateContact_try 1.4', ['updateContactResponse' => $updateContactResponse]);
                }

                return;
            }
            catch (\GuzzleHttp\Exception\BadResponseException $e)
            {
                /* REFRESH TOKEN */
                $code = $e->getResponse()->getStatusCode();
                // info('gohighlevelv2CreateContact_catch 2.1', ['code' => $code, 'retry' => $retry, 'error' => $e->getMessage()]);
                if($code == 401 && !$retry) 
                {
                    $retry = true;
                    // info('gohighlevelv2CreateContact_catch 2.2', [
                    //     'company_id' => $company_id,
                    //     'refresh_token' => $refresh_token,
                    // ]);
                    $refresh_result = $this->gohighlevelv2RefreshToken($company_id, $refresh_token);
                    // info('gohighlevelv2CreateContact_catch 2.3', ['refresh_result' => $refresh_result]);
                    if (isset($refresh_result['status']) && $refresh_result['status'] === 'success')
                    {
                        // info('gohighlevelv2CreateContact_catch 2.4');
                        $access_token = $refresh_result['access_token'];
                        $refresh_token = $refresh_result['refresh_token'];
                        continue; // ulangi process
                    }
                }
                /* REFRESH TOKEN */

                $message = $e->getMessage();
                if ($err_msg != "") 
                    Log::warning("GHL Create Contact (L941) ErrMsg:{$err_msg}");
                else
                    Log::warning("GHL Failed Create Contact : {$message} CampaignID : #{$campaign_id}");

                /* WRITE UPSER FAILED LEAD RECORD */
                $this->UpsertFailedLeadRecord([
                    'function' => 'gohighlevelv2CreateContact',
                    'type' => 'blocked',
                    'blocked_type' => 'gohighlevel',
                    'description' => $message,
                    'leadspeek_api_id' => $campaign_id,
                    'leadspeek_type' => $leadspeek_type,
                    'email_encrypt' => $md5_param,
                    'data_lead' => json_encode($json)
                ]);
                /* WRITE UPSER FAILED LEAD RECORD */

                return;
            }
        } while ($retry);
    }

    public function gohighlevelv2CreateCustomField($company_id = "", &$access_token = "", &$refresh_token = "", $location_id = "", $name = "", $placeholder = "", $data_type = "TEXT")
    {
        // info('start function gohighlevelv2CreateCustomField');
        $http = new \GuzzleHttp\Client;
        $retry = false;

        do
        {
            try 
            {
                // info('gohighlevelv2CreateCustomField_try 1.1');
                $url = "https://services.leadconnectorhq.com/locations/{$location_id}/customFields";        
                $json = [
                    'name' => $name,
                    'placeholder' => $placeholder,
                    'dataType' => $data_type,
                    'position' => 0,
                    'model' => 'contact'
                ];
                $headers = [
                    'Accept' => 'application/json',
                    'Authorization' => "Bearer $access_token",
                    'Content-Type' => 'application/json',
                    'Version' => '2021-07-28',
                ];
                $parameter = [
                    'json' => $json,
                    'headers' => $headers
                ];
    
                $response = $http->post($url, $parameter);
                $response = json_decode($response->getBody(), true);
                // info('gohighlevelv2CreateCustomField_try 1.2', ['id' => $response['customField']['id']]);
                return isset($response['customField']['id']) ? $response['customField']['id'] : '';
            }
            catch (\GuzzleHttp\Exception\BadResponseException $e) 
            {
                /* REFRESH TOKEN */
                $code = $e->getResponse()->getStatusCode();
                // info('gohighlevelv2CreateCustomField_catch 2.1', ['code' => $code, 'retry' => $retry]);
                if($code == 401 && !$retry) 
                {
                    $retry = true;
                    // info('gohighlevelv2CreateCustomField_catch 2.2', ['company_id' => $company_id,'refresh_token' => $refresh_token,]);
                    $refresh_result = $this->gohighlevelv2RefreshToken($company_id, $refresh_token);
                    // info('gohighlevelv2CreateCustomField_catch 2.3', ['refresh_result' => $refresh_result]);
                    if (isset($refresh_result['status']) && $refresh_result['status'] === 'success')
                    {
                        // info('gohighlevelv2CreateCustomField_catch 2.4');
                        $access_token = $refresh_result['access_token'];
                        $refresh_token = $refresh_result['refresh_token'];
                        continue; // ulangi process
                    }
                    else
                    {
                        // info('gohighlevelv2CreateCustomField_catch 2.5');
                        return "";
                    }
                }
                /* REFRESH TOKEN */

                $id = $this->gohighlevelv2GetCustomFieldID($company_id, $access_token, $refresh_token, $location_id, $name);
                // info('gohighlevelv2CreateCustomField_catch 2.6', ['error' => $e->getMessage(), 'id' => $id]);
                return $id;
            }
        } while ($retry);
    }

    public function gohighlevelv2GetCustomFieldID($company_id = "", &$access_token = "", &$refresh_token = "", $location_id = "", $name = "")
    {
        // info('start function gohighlevelv2GetCustomFieldID');
        $http = new \GuzzleHttp\Client;
        $retry = false;

        do 
        {
            try
            {
                // info('gohighlevelv2GetCustomFieldID_try 1.1');
                $url = "https://services.leadconnectorhq.com/locations/{$location_id}/customFields";
                $headers = [
                    'Accept' => 'application/json',
                    'Authorization' => "Bearer {$access_token}",
                    'Version' => '2021-07-28',
                ];
                $parameter = [
                    'headers' => $headers
                ];

                $response = $http->get($url, $parameter);
                $response = json_decode($response->getBody(), true);
                
                // info('gohighlevelv2GetCustomFieldID_try 1.2');
                $customFields = $response['customFields'];
                foreach($customFields as $item)
                {
                    if (trim($name) == trim($item['name'])) 
                    {
                        // info('gohighlevelv2GetCustomFieldID_try_if', ['id' => $item['id']]);
                        return $item['id'];
                        break;
                    }
                }
            }
            catch (\GuzzleHttp\Exception\BadResponseException $e)
            {   
                /* REFRESH TOKEN */
                $code = $e->getResponse()->getStatusCode();
                // info('gohighlevelv2GetCustomFieldID_catch 2.1', ['code' => $code, 'retry' => $retry, 'error' => $e->getMessage()]);
                if($code == 401 && !$retry) 
                {
                    $retry = true;
                    // info('gohighlevelv2GetCustomFieldID_catch 2.2', [
                    //     'company_id' => $company_id,
                    //     'refresh_token' => $refresh_token,
                    // ]);
                    $refresh_result = $this->gohighlevelv2RefreshToken($company_id, $refresh_token);
                    // info('gohighlevelv2GetCustomFieldID_catch 2.3', ['refresh_result' => $refresh_result]);
                    if (isset($refresh_result['status']) && $refresh_result['status'] === 'success')
                    {
                        // info('gohighlevelv2GetCustomFieldID_catch 2.4');
                        $access_token = $refresh_result['access_token'];
                        $refresh_token = $refresh_result['refresh_token'];
                        continue; // ulangi process
                    }
                    else
                    {
                        // info('gohighlevelv2GetCustomFieldID_catch 2.5');
                        return "";
                    }
                }
                /* REFRESH TOKEN */

                return "";
            }
        } while ($retry);
    }

    public function gohighlevelv2GetTag($company_id = "", &$access_token = "", &$refresh_token = "", $location_id = "", $tag_id = "")
    {
        $http = new \GuzzleHttp\Client;
        $retry = false;

        do 
        {
            try 
            {
                $url = "https://services.leadconnectorhq.com/locations/{$location_id}/tags/{$tag_id}";
                $headers = [
                    'Accept' => 'application/json',
                    'Authorization' => "Bearer $access_token",
                    'Version' => '2021-07-28',
                ];
                $parameter = [
                    'headers' => $headers
                ];

                $response = $http->get($url, $parameter);
                $response = json_decode($response->getBody(), true);
                // info('gohighlevelv2GetTag_try 1.1', [
                //     // 'response' => $response
                // ]);
                return isset($response['tag']['name']) ? $response['tag']['name'] : "sys_removed";
            }
            catch (\GuzzleHttp\Exception\BadResponseException $e)
            {
                /* REFRESH TOKEN */
                $code = $e->getResponse()->getStatusCode();
                // info('gohighlevelv2GetTag_catch 2.1', ['code' => $code, 'retry' => $retry, 'error' => $e->getMessage()]);
                if($code == 401 && !$retry) 
                {
                    $retry = true;
                    // info('gohighlevelv2GetTag_catch 2.2', [
                    //     'company_id' => $company_id,
                    //     'refresh_token' => $refresh_token,
                    // ]);
                    $refresh_result = $this->gohighlevelv2RefreshToken($company_id, $refresh_token);
                    // info('gohighlevelv2GetTag_catch 2.3', ['refresh_result' => $refresh_result]);
                    if (isset($refresh_result['status']) && $refresh_result['status'] === 'success')
                    {
                        // info('gohighlevelv2GetTag_catch 2.4');
                        $access_token = $refresh_result['access_token'];
                        $refresh_token = $refresh_result['refresh_token'];
                        continue; // ulangi process
                    }
                    else
                    {
                        // info('gohighlevelv2GetTag_catch 2.5');
                        return "sys_removed";
                    }
                }
                /* REFRESH TOKEN */

                return "sys_removed";
            }
        } while ($retry);
    }

    public function gohighlevelv2GetTags($company_id = "", &$access_token = "", &$refresh_token = "", $location_id = "")
    {
        $http = new \GuzzleHttp\Client;
        $retry = false;

        do
        {
            try
            {
                $url = "https://services.leadconnectorhq.com/locations/{$location_id}/tags";
                $headers = [
                    'Accept' => 'application/json',
                    'Authorization' => "Bearer {$access_token}",
                    'Version' => '2021-07-28',
                ];
                $parameter = [
                    'headers' => $headers
                ];

                $response = $http->get($url, $parameter);
                $response = json_decode($response->getBody());
                // info('gohighlevelv2GetTags_try 1.1', [
                //     // 'response' => $response
                // ]);
                return $response;
            }
            catch (\GuzzleHttp\Exception\BadResponseException $e)
            {
                /* REFRESH TOKEN */
                $code = $e->getResponse()->getStatusCode();
                // info('gohighlevelv2GetTags_catch 2.1', ['code' => $code, 'retry' => $retry, 'error' => $e->getMessage()]);
                if($code == 401 && !$retry) 
                {
                    $retry = true;
                    // info('gohighlevelv2GetTags_catch 2.2', [
                    //     'company_id' => $company_id,
                    //     'refresh_token' => $refresh_token,
                    // ]);
                    $refresh_result = $this->gohighlevelv2RefreshToken($company_id, $refresh_token);
                    // info('gohighlevelv2GetTags_catch 2.3', ['refresh_result' => $refresh_result]);
                    if (isset($refresh_result['status']) && $refresh_result['status'] === 'success')
                    {
                        // info('gohighlevelv2GetTags_catch 2.4');
                        $access_token = $refresh_result['access_token'];
                        $refresh_token = $refresh_result['refresh_token'];
                        continue; // ulangi process
                    }
                    else
                    {
                        // info('gohighlevelv2GetTags_catch 2.5');
                        return 'sys_removed';
                    }
                }
                /* REFRESH TOKEN */

                return 'sys_removed';
            }
        } while ($retry);
    }
    /** GOHIGHLEVELV2 FUNCTIONS */

    /** GOHIGHLEVEL FUNCTIONS */
    public function ghl_GetContact($api_key = "", $email = "")  
    {
        $http = new \GuzzleHttp\Client;
        
        if(empty($api_key) || trim($api_key) == '' || empty($email) || trim($email) == '') 
        {
            return [
                'status' => 'error',
                'message' => 'apikey or email empty'
            ];
        }

        try
        {
            $apiEndpoint = "https://rest.gohighlevel.com/v1/contacts/lookup";
            $dataOptions = [
                'headers' => [
                    'Authorization' => 'Bearer ' . $api_key,
                    'Content-Type' => 'application/json'
                ],
                'query' => ['email' => $email]
            ];

            $response = $http->get($apiEndpoint, $dataOptions);
            $data = json_decode($response->getBody(), true);

            return [
                'status' => 'success',
                'data' => $data,
            ];
        }
        catch (\GuzzleHttp\Exception\BadResponseException $e) 
        {
            return [
                'status' => 'error',
                'message' => $e->getMessage(),
            ];
        }

    }

    public function ghl_createContact($company_id = "",$api_key = "",$ID = "",$ClickDate = "",$FirstName = "",$LastName = "",$Email = "",$Email2 = "",$Phone = "",$Phone2 = "",$Address = "",$Address2 = "",$City = "",$State = "",$Zipcode = "",$Keyword = "",$tags = array(),$campaignID="",$errMsg="",$leadspeek_type = "",$md5param = "", $additional_fields = []) {
        if($api_key != '') {
            $http = new \GuzzleHttp\Client;

            $comset_name = 'gohlcustomfields';
            /** GET IF CUSTOM FIELD ALREADY EXIST */
            $company_name = "";
            $email2Id = "";
            $phone2Id = "";
            $address2Id = "";
            $keywordId = "";
            //$urlId = "";
            $contactId = "";
            $clickDateId = "";
            
            $customfields = CompanySetting::where('company_id','=',$company_id)->whereEncrypted('setting_name',$comset_name)->get();
            if (count($customfields) > 0) {
                $_customfields = json_decode($customfields[0]['setting_value']);
                $email2Id = (isset($_customfields->email2Id))?$_customfields->email2Id:'';
                $phone2Id = (isset($_customfields->phone2Id))?$_customfields->phone2Id:'';
                $address2Id = (isset($_customfields->address2Id))?$_customfields->address2Id:'';
                $keywordId = (isset($_customfields->keywordId))?$_customfields->keywordId:'';
                //$urlId = (isset($_customfields->urlId))?$_customfields->urlId:'';
                $contactId = (isset($_customfields->contactId))?$_customfields->contactId:'';
                $clickDateId = (isset($_customfields->clickDateId))?$_customfields->clickDateId:'';
 
                if (!empty($additional_fields)) {
                    foreach ($additional_fields as &$field) {
                        $field['ghl_id'] = (isset($_customfields->{$field['db_id']})) ? $_customfields->{$field['db_id']} : '' ;
                    }
                    unset($field);
                }
 
            }
            /** GET IF CUSTOM FIELD ALREADY EXIST */

            $custom_fields = [
                $contactId => $ID,
                $clickDateId => $ClickDate,
                $email2Id => $Email2,
                $phone2Id => $Phone2,
                $address2Id => $Address2,
                $keywordId => $Keyword,
                //$urlId => $Url
            ];

            if (is_array($additional_fields) && !empty($additional_fields)) {
                foreach ($additional_fields as $fields) {      
                    if (isset($fields['ghl_id']) && isset($fields['value'])) {
                        $custom_fields[$fields['ghl_id']] = $fields['value'];
                    }  
                    if (isset($fields['db_id']) && strtolower($fields['db_id']) == 'companynameid') {
                        $company_name = $fields['value'];
                    }
               }
            }
            
            //$custom_fields = json_decode($custom_fields);
            //$tags = json_encode($tags);

            $contactData = [
                "firstName" => $FirstName,
                "lastName" => $LastName,
                "name" => $FirstName . ' ' . $LastName,
                "email" => $Email,
                "phone" => $Phone,
                "address1" => $Address,
                "country" => "US",
                "city" => $City,
                "state" => $State,
                "postalCode" => $Zipcode,
                "customField" => $custom_fields,
                "source" => "Campaign ID : #" . $campaignID,
                // "tags" => $tags, // saat pertama kali create contact, tags tidak perlu dikirim
                "companyName" => $company_name,
            ];

            try {
                // process create contact, tanpa tag
                $apiEndpoint =  "https://rest.gohighlevel.com/v1/contacts/";
                $dataOptions = [
                    'headers' => [
                        'Authorization' => 'Bearer ' . $api_key,
                        'Content-Type' => 'application/json'
                    ],
                    'json' => $contactData
                ];
                $createContact = $http->post($apiEndpoint,$dataOptions);
                $createContactResponse = json_decode($createContact->getBody()->getContents(), true);
                // info('ghl_createContact', ['createContactResponse' => $createContactResponse]);

                // process update contact, untuk menambahkan tag
                $emailExisting = isset($createContactResponse['contact']['email']) ? $createContactResponse['contact']['email'] : '';
                $contactIdExisting = isset($createContactResponse['contact']['id']) ? $createContactResponse['contact']['id'] : '';
                $tagsExisting = (isset($createContactResponse['contact']['tags']) && is_array($createContactResponse['contact']['tags'])) ? $createContactResponse['contact']['tags'] : [];
                $tags = array_values(array_unique(array_merge($tags, $tagsExisting)));
                // info('ghl_createContact', ['contactIdExisting' => $contactIdExisting, 'tagsExisting' => $tagsExisting, 'tags' => $tags, 'emailExisting' => $emailExisting]);
                if(!empty($contactIdExisting) && !empty($tags) && !empty($emailExisting)){
                    $apiEndpoint =  "https://rest.gohighlevel.com/v1/contacts/{$contactIdExisting}";
                    $dataOptions = [
                        'headers' => [
                            'Authorization' => 'Bearer ' . $api_key,
                            'Content-Type' => 'application/json'
                        ],
                        'json' => [
                            "email" => $emailExisting, // ini required dari ghl nya
                            "tags" => $tags, // saat update contact, tags perlu dikirim
                        ]
                    ];
                    $updateContact = $http->put($apiEndpoint,$dataOptions);
                    $updateContactResponse = json_decode($updateContact->getBody()->getContents(), true);
                    // info('ghl_createContact', ['updateContactResponse' => $updateContactResponse]);
                }
                
                // echo "<pre>";
                // print_r($dataOptions);
                // echo "</pre>";
            }catch (\GuzzleHttp\Exception\BadResponseException $e){
                $message = $e->getMessage();

                if ($errMsg != "") {
                    Log::warning("GHL Create Contact (L941) ErrMsg:" . $message . $errMsg);
                }else{
                    log::warning('GHL Failed Create Contact : ' . $message . ' CampaignID : #' . $campaignID);
                }

                /* WRITE UPSER FAILED LEAD RECORD */
                $contactData['tags'] = $tags;
                $this->UpsertFailedLeadRecord([
                    'function' => 'ghl_createContact',
                    'type' => 'blocked',
                    'blocked_type' => 'gohighlevel',
                    'description' => $message,
                    'leadspeek_api_id' => $campaignID,
                    'leadspeek_type' => $leadspeek_type,
                    'email_encrypt' => $md5param,
                    'data_lead' => json_encode($contactData)
                ]);
                /* WRITE UPSER FAILED LEAD RECORD */
            }
        }
    }

    public function ghl_CreateCustomField($api_key,$parentId = '',$name = '', $placeholder='',$dataType = 'TEXT',$showInForms = true){
        $http = new \GuzzleHttp\Client;
        try {
            $apiEndpoint =  "https://rest.gohighlevel.com/v1/custom-fields/";
            $dataOptions = [
                'headers' => [
                    'Authorization' => 'Bearer ' . $api_key,
                    'Content-Type' => 'application/json'
                ],
                'json' => [
                    "parentId" => $parentId,
                    "name" => $name,
                    "dataType" => $dataType,
                    "placeholder" => ($placeholder != '')?$placeholder:$name,
                    "position" => 0,
                    "model" => "contact",
                    "showInForms" => $showInForms
                ]
            ];

            $createfield = $http->post($apiEndpoint,$dataOptions);
            $result =  json_decode($createfield->getBody());
            return $result->customField->id;
        }catch (\GuzzleHttp\Exception\BadResponseException $e) {
            return $this->ghl_searchCustomFieldID($api_key,$name);
        }
    }

    public function ghl_searchCustomFieldID($api_key,$name) {
        $http = new \GuzzleHttp\Client;
        try {
            $apiEndpoint =  "https://rest.gohighlevel.com/v1/custom-fields";
            $dataOptions = [
                'headers' => [
                    'Authorization' => 'Bearer ' . $api_key,
                    'Content-Type' => 'application/json'
                ],
                'json' => [
                   
                ]
            ];

            $listfield = $http->get($apiEndpoint,$dataOptions);
            $result =  json_decode($listfield->getBody());
            $listResult = $result->customFields;
            foreach($listResult as $lr) {
                if (trim($name) == trim($lr->name)) {
                    return $lr->id;
                    break;
                }
            }

           
        }catch (\GuzzleHttp\Exception\BadResponseException $e) {
            return "error : " . $e->getMessage();
        }
    }

    public function ghl_getTag($tagID,$api_key = '') {
        // $apiSetting = IntegrationSettings::where('company_id','=',$company_id)
        //                 ->where('integration_slug','=','gohighlevel')
        //                 ->first();
        
        if($api_key != '') {
            $http = new \GuzzleHttp\Client;
            try {

                $apiEndpoint =  "https://rest.gohighlevel.com/v1/tags/" . $tagID;
                $dataOptions = [
                    'headers' => [
                        'Authorization' => 'Bearer ' . $api_key,
                        'Content-Type' => 'application/json'
                    ],
                    'json' => [
                        
                    ]
                ];

                $getTags = $http->get($apiEndpoint,$dataOptions);
                $result =  json_decode($getTags->getBody());
                return $result->name;
            }catch (\GuzzleHttp\Exception\BadResponseException $e) {
                return "sys_removed";
            }

        }else{
            return "";
        }
    }

    public function ghl_getTags($api_key = "") 
    {
        if(empty($api_key) || trim($api_key) == "")
        {
            return 'apikey_empty';
        }

        try 
        {
            $http = new \GuzzleHttp\Client;
            $apiEndpoint =  "https://rest.gohighlevel.com/v1/tags/";
            $dataOptions = [
                'headers' => [
                    'Authorization' => 'Bearer ' . $api_key,
                    'Content-Type' => 'application/json'
                ],
                'json' => [
                    
                ]
            ];

            $getTags = $http->get($apiEndpoint,$dataOptions);
            $result =  json_decode($getTags->getBody());
        
            return $result;
        } 
        catch (\Exception $e) 
        {
            return 'sys_removed';
        }
    }

    public function ghl_updateContact($api_key,$contact_id, $param = []) {
        if(!empty($api_key)) {
            $http = new \GuzzleHttp\Client;

            $json = [
                'country' => 'US'
            ];
            //required
            if (isset($param['email'])) {
                $json['email'] = $param['email'];
            }
            //required
            
            if (isset($param['first_name'])) {
                $json['firstName'] = $param['first_name'];
            }
            if (isset($param['last_name'])) {
                $json['lastName'] = $param['last_name'];
            }
            if (isset($param['name'])) {
                $json['name'] = $param['name'];
            }
            if (isset($param['phone'])) {
                $json['phone'] = $param['phone'];
            }
            if (isset($param['address1'])) {
                $json['address1'] = $param['address1'];
            }
            if (isset($param['city'])) {
                $json['city'] = $param['city'];
            }
            if (isset($param['state'])) {
                $json['state'] = $param['state'];
            }
            if (isset($param['zip_code'])) {
                $json['postalCode'] = $param['zip_code'];
            }
            if (isset($param['source'])) {
                $json['source'] = $param['source'];
            }
            if (isset($param['tags'])) {
                $json['tags'] = $param['tags'];
            }
            
            try {
                $apiEndpoint =  "https://rest.gohighlevel.com/v1/contacts/" . $contact_id;
                $dataOptions = [
                    'headers' => [
                        'Authorization' => 'Bearer ' . $api_key,
                        'Content-Type' => 'application/json'
                    ],
                    'json' => $json
                ];
                
                
                $updateContact = $http->put($apiEndpoint,$dataOptions);
                
                if ($updateContact->getStatusCode() == 200) {
                    return [
                        'success' => true,
                        'error' => false,
                    ];
                } else {
                    return [
                        'success' => false,
                        'error' => 'Something went wrong, please try again later.',
                    ];
                }

            }catch (\GuzzleHttp\Exception\BadResponseException $e) {
                $errorBody = (string) $e->getResponse()->getBody();
                $decodedError = json_decode($errorBody, true);
                Log::warning('ghl_updateContact (' . $param['email'] . ') error add msg :' . $e);
                return [
                    'success' => false,
                    'error' => $decodedError['msg'],
                ];
            }
        }else {
            Log::warning('ghl_updateContact api key empty');
        }
    }


    public function ghl_addContactTags($api_key,$email, $tagsToAdd = []) {
        if(!empty($api_key)) {
            try {
                $get_contact = [];
                $get_contact = $this->ghl_GetContact($api_key, $email);

                $get_contact_status = isset($get_contact['status']) ? $get_contact['status'] : '';
                $get_contact_id = isset($get_contact['data']['contacts'][0]['id']) ? $get_contact['data']['contacts'][0]['id'] : '';
                $get_contact_tags = isset($get_contact['data']['contacts'][0]['tags']) ? $get_contact['data']['contacts'][0]['tags'] : [];

                if($get_contact_status == 'success' && is_array($get_contact_tags)) {
                    $tagsToAdd = array_map('strtolower', $tagsToAdd);
                    $newTags = array_diff($tagsToAdd, $get_contact_tags);

                    if (count($newTags) > 0) {
                        $mergedTags = array_merge($get_contact_tags, $newTags);
                        $mergedTags = array_unique($mergedTags);
                        $mergedTags = array_values($mergedTags);
    
                        $param = [
                            'email' => $email,
                            'tags' => $mergedTags,
                        ];
                        $update_contact = $this->ghl_updateContact($api_key,$get_contact_id,$param);
                    }
                }

            }catch (\GuzzleHttp\Exception\BadResponseException $e) {
                $errorBody = (string) $e->getResponse()->getBody();
                $decodedError = json_decode($errorBody, true);
                Log::warning('ghl_addContactTags (' . strtolower($email) . ') error add msg :' . $e);

                Log::info([
                    'success' => false,
                    'error' => $decodedError['msg'],
                ]);
            }
        }
    }

    public function ghl_addContactTagsAgency($api_key,$user,$tagsToAdd = []) {
        if(!empty($api_key)) {
            try {
                $name = isset($user['name']) ? $user['name'] : '';
                $email = isset($user['email']) ? $user['email'] : '';
                $phone = isset($user['phone']) ? $user['phone'] : '';

                //get contact from GHL
                $get_contact = [];
                $get_contact = $this->ghl_GetContact($api_key, $email);
                $get_contact_status = isset($get_contact['status']) ? $get_contact['status'] : '';
                $get_contact_message = isset($get_contact['message']) ? $get_contact['message'] : '';
                $get_contact_id = isset($get_contact['data']['contacts'][0]['id']) ? $get_contact['data']['contacts'][0]['id'] : '';
                $get_contact_tags = isset($get_contact['data']['contacts'][0]['tags']) ? $get_contact['data']['contacts'][0]['tags'] : [];
                //get contact from GHL

                $tagsToAdd = array_map('strtolower', $tagsToAdd);
                $newTags = array_diff($tagsToAdd, $get_contact_tags);

                $param = [];
                $mergedTags = [];
                if (count($newTags) > 0) {
                    $mergedTags = array_merge($get_contact_tags, $newTags);
                    $mergedTags = array_unique($mergedTags);
                    $mergedTags = array_values($mergedTags);

                    $param = [
                        'email' => $email,
                        'tags' => $mergedTags,
                    ];
                }
                
                if($get_contact_status == 'success' && is_array($get_contact_tags) && count($newTags) > 0) {// UPDATE CONTACT TAGS
                    $update_contact = $this->ghl_updateContact($api_key,$get_contact_id,$param);
                }elseif ($get_contact_status == 'error') { // CREATE CONTACT IF NOT EXIST
                    if (strpos($get_contact_message, 'The email address is invalid.') !== false) {
                        try {
                            $http = new \GuzzleHttp\Client;
                            $fullName = trim($name);
                            $nameParts = preg_split('/\s+/', $fullName);

                            if (count($nameParts) === 1) {
                                $FirstName = $nameParts[0];
                                $LastName = '';
                            } else {
                                $LastName = array_pop($nameParts);
                                $FirstName = implode(' ', $nameParts);
                            }

                            $apiEndpoint =  "https://rest.gohighlevel.com/v1/contacts/";
                            $dataOptions = [
                                'headers' => [
                                    'Authorization' => 'Bearer ' . $api_key,
                                    'Content-Type' => 'application/json'
                                ],
                                'json' => [
                                    "firstName" => $FirstName,
                                    "lastName" => $LastName,
                                    "name" => $name,
                                    "email" => strtolower($email),
                                    "phone" => $phone,
                                    "country" => "US",
                                    "customField" => [],
                                    "source" => "Existing Agency",
                                    "tags" => array_merge(['account-created'],$mergedTags),
                                ]
                            ];

                            $createContact = $http->post($apiEndpoint,$dataOptions);

                            if ($createContact->getStatusCode() == 200) {
                                Log::info([
                                    'success' => true,
                                    'error' => false,
                                ]);
                            } else {
                                Log::info([
                                    'success' => false,
                                    'error' => 'Something went wrong, please try again later.',
                                ]);
                            }

                        }catch (\GuzzleHttp\Exception\BadResponseException $e) {
                            $errorBody = (string) $e->getResponse()->getBody();
                            $decodedError = json_decode($errorBody, true);
                            Log::warning('ghl_createContact (' . strtolower($user->user_email) . ') error add msg :' . $e);

                            Log::info([
                                'success' => false,
                                'error' => $decodedError['msg'],
                            ]);
                        }
                    }
                }
            }catch (\GuzzleHttp\Exception\BadResponseException $e) {
                $errorBody = (string) $e->getResponse()->getBody();
                $decodedError = json_decode($errorBody, true);
                Log::info('ghl_addContactTagsAgency (' . strtolower($user->user_email) . ') error add msg :' . $e);

                Log::info([
                    'success' => false,
                    'error' => $decodedError['msg'],
                ]);
            }
        }
    }

    public function ghl_removeContactTags($api_key, $email, $tagsToRemove = []) {
        if (!empty($api_key)) {
            try {
                $get_contact = $this->ghl_GetContact($api_key, $email);

                $get_contact_status = isset($get_contact['status']) ? $get_contact['status'] : '';
                $get_contact_id = isset($get_contact['data']['contacts'][0]['id']) ? $get_contact['data']['contacts'][0]['id'] : '';
                $get_contact_tags = isset($get_contact['data']['contacts'][0]['tags']) ? $get_contact['data']['contacts'][0]['tags'] : [];

                if ($get_contact_status == 'success' && is_array($get_contact_tags)) {
                    $tagsToRemove = array_map('strtolower', $tagsToRemove);
                    $existingTagsToRemove = array_intersect($get_contact_tags, $tagsToRemove);//  pick any tags that need to remove from $get_contact_tags 
                    if (count($existingTagsToRemove) > 0) {
                        $updated_tags = array_values(array_diff($get_contact_tags, $existingTagsToRemove));//remove tags from $existingTagsToRemove
    
                        $param = [
                            'email' => $email,
                            'tags' => $updated_tags,
                        ];
    
                        $update_contact = $this->ghl_updateContact($api_key, $get_contact_id, $param);
                    }

                }

            } catch (\GuzzleHttp\Exception\BadResponseException $e) {
                $errorBody = (string) $e->getResponse()->getBody();
                $decodedError = json_decode($errorBody, true);
                Log::warning('ghl_updateContact (' . strtolower($email) . ') error remove msg :' . $e);

                Log::info([
                    'success' => false,
                    'error' => $decodedError['msg'] ?? 'Unknown error',
                ]);
            }
        }
    }    
    /** GOHIGHLEVEL FUNCTIONS */

    // AGENCYZOOM FUNCTIONS
    public function agencyzoom_sendrecord($webhook = "",$FirstName = "",$LastName = "",$BusinessName = "",$Email = "",$Email2 = "",$Phone = "",$Phone2 = "",$Address = "",$Address2 = "",$City = "",$State = "",$Zipcode = "",$Keyword = "",$campaignID = "", $leadspeek_type = "", $md5param = "")
    {
        // info(__FUNCTION__, ['get_defined_vars' => get_defined_vars()]);
        if(empty($webhook))
            return;

        $http = new \GuzzleHttp\Client;

        $contact_data = [
            "firstname" => $FirstName,
            "lastname" => $LastName,
            "businessname" => $BusinessName,
            "contactname" => "$FirstName $LastName",
            "email" => $Email,
            "phone" => $Phone,
            "otherEmail" => $Email2 ,
            "otherPhone" => $Phone2,
            "streetAddress" => $Address,
            "streetAddressLine2" => $Address2,
            "city" => $City,
            "state" => $State,
            "country" => "USA",
            "zip" => $Zipcode,
            "notes" => $Keyword,
        ];

        try 
        {
            $dataOptions = [
                'json' => $contact_data
            ];
            // info(__FUNCTION__, ['webhook' => $webhook,'dataOptions' => $dataOptions]);
            $send_record = $http->post($webhook,$dataOptions);
            $result =  json_decode($send_record->getBody());
            // info(__FUNCTION__, ['result' => $result]);
        }
        catch (\GuzzleHttp\Exception\BadResponseException $e) 
        {
            /* WRITE UPSER FAILED LEAD RECORD */
            $message = $e->getMessage();
            $this->UpsertFailedLeadRecord([
                'function' => 'agencyzoom_sendrecord',
                'type' => 'blocked',
                'blocked_type' => 'agencyzoom',
                'description' => $message,
                'leadspeek_api_id' => $campaignID,
                'leadspeek_type' => $leadspeek_type,
                'email_encrypt' => $md5param,
                'data_lead' => json_encode($contact_data)
            ]);
            /* WRITE UPSER FAILED LEAD RECORD */
        }
    }
    // AGENCYZOOM FUNCTIONS

    // ZAPIER FUNCTIONS 
    public function zap_sendrecord($webhook = "",$ClickDate = "",$FirstName = "",$LastName = "",$Email = "",$Email2 = "",$Phone = "",$Phone2 = "",$Address = "",$Address2 = "",$City = "",$State = "",$Zipcode = "",$Keyword = "", $url = "",$tags = array(),$campaignID="", $campaign_type = "", $contactID = "", $leadspeek_type = "", $md5param = "", $additional_fields = []) {
        if($webhook != '') {
            $http = new \GuzzleHttp\Client;
            // $newTags = [];
            // if (!empty($tags)) {
            //     foreach ($tags as $index => $tag) {
            //         $newTags[$index + 1] = $tag; 
            //     }
            // }

            $contactData = [
                "clickdate" => $ClickDate,
                "firstName" => $FirstName,
                "lastName" => $LastName,
                "name" => $FirstName . ' ' . $LastName,
                "email1" => $Email,
                "email2" => $Email2,
                "phone1" => $Phone,
                "phone2" => $Phone2,
                "address1" => $Address,
                "address2" => $Address2,    
                "city" => $City,
                "state" => $State,
                "postalCode" => $Zipcode,
                'keyword' => $Keyword,
                'url' => $url,
                "campaignID" => $campaignID,
                "tags" => $tags,
                "campaignType" => $campaign_type,
                "contactID" => $contactID,
            ];

            if (!empty($additional_fields)) {
                foreach ($additional_fields as $key => $value) {
                    $contactData[$key] = $value;
                }
            }

            try {
                $dataOptions = [
                    'json' => $contactData
                ];
                $send_record = $http->post($webhook,$dataOptions);
                $result =  json_decode($send_record->getBody());
                //    echo "<pre>";
                //    print_r($dataOptions);
                //    echo "</pre>";
            } catch (\GuzzleHttp\Exception\BadResponseException $e) {
                //log::warning('GHL Failed Create Contact : ' . $e->getMessage());
                $message = $e->getMessage();
                // echo $message;

                /* WRITE UPSER FAILED LEAD RECORD */
                $this->UpsertFailedLeadRecord([
                    'function' => 'zap_sendrecord',
                    'type' => 'blocked',
                    'blocked_type' => 'zapier',
                    'description' => $message,
                    'leadspeek_api_id' => $campaignID,
                    'leadspeek_type' => $leadspeek_type,
                    'email_encrypt' => $md5param,
                    'data_lead' => json_encode($contactData)
                ]);
                /* WRITE UPSER FAILED LEAD RECORD */
            }
        }
    }
    // ZAPIER FUNCTIONS 

    /** GENERAL FUNCTION */
    public function getLimitedKeywords($keywords, $limit = 500, $shuffle = true) {
        // Check if the keyword string exceeds the limit
        if (strlen($keywords) <= $limit) {
            return $keywords; // Return as-is if within the limit
        }
        
        // Explode the keywords into an array
        $keywordArray = explode(',', $keywords);
        
        if ($shuffle) {
            // Shuffle the keyword array
            shuffle($keywordArray);
        }
        
        // Initialize an empty array to store selected keywords
        $selectedKeywords = [];
        
        // Initialize a counter to track the current character length
        $currentLength = 0;
        
        // Loop through each keyword
        foreach ($keywordArray as $keyword) {
            // Trim whitespace and calculate the length of the keyword with a comma separator
            $keyword = trim($keyword);
            $keywordLength = strlen($keyword) + 1; // +1 for the comma
            
            // Check if adding this keyword would exceed the limit
            if ($currentLength + $keywordLength <= $limit) {
                // Add the keyword to the selected array
                $selectedKeywords[] = $keyword;
                // Update the current length
                $currentLength += $keywordLength;
            } else {
                // Stop the loop if the limit is reached
                break;
            }
        }
        
        // Implode the selected keywords back into a comma-separated string
        return implode(',', $selectedKeywords);
    }

    public function paused_campaign_create_list($leadspeek_api_id){
        $campaign = LeadspeekUser::where('leadspeek_api_id', $leadspeek_api_id)->first();
        if (!empty($campaign)) {
            $campaign->active = 'F';
            $campaign->disabled = 'T';
            $campaign->active_user = 'T';
            $campaign->save();

            //prepare campaign link
            $url_setting = $this->getcompanysetting($campaign->company_id,'customsidebarleadmenu');
            $url = '';
            if (isset($url_setting->enhance) && $campaign->leadspeek_type == 'enhance') {
                $url = $url_setting->enhance->url;
            }else if (isset($url_setting->b2b) && $campaign->leadspeek_type == 'b2b') {
                $url = $url_setting->b2b->url;
            }else{
                $get_root_company_id = User::select('id','company_root_id')->where('id',$campaign->user_id)->first();
                if (isset($get_root_company_id->company_root_id)) {
                    $url_setting = $this->getcompanysetting($get_root_company_id->company_root_id,'rootcustomsidebarleadmenu');
                    if (isset($url_setting->enhance->url) && $campaign->leadspeek_type == 'enhance') {
                        $url = $url_setting->enhance->url;
                    }else if (isset($url_setting->b2b->url) && $campaign->leadspeek_type == 'b2b') {
                        $url = $url_setting->b2b->url;
                    }
                }
            }

            $domain_setting  = Company::select('id','domain','subdomain','status_domain')->where('id',$campaign->company_id)->first();
            $domain = '';

            if (!empty($domain_setting->domain) && $domain_setting->status_domain === 'ssl_acquired') {
                $domain = $domain_setting->domain;
            } elseif (!empty($domain_setting->subdomain)) {
                $domain = $domain_setting->subdomain;
            }

            $link_type = 'play';

            $campaign_link_id = '';
            if (!empty($campaign->campaign_link_id)) {
                $campaign_link_id = $campaign->campaign_link_id; 
            }else {
                $campaign_link_id = Str::uuid(); 
                $campaign->campaign_link_id = $campaign_link_id;
                $campaign->save();
            }

            $campaign_link = 'https://' . $domain . '/' . $url . '/campaign-management?campaign_id=' . $campaign_link_id . '&type=' . $link_type;

            //get campaign user_id
            $user = User::select('email')->where('company_id',$campaign->company_id)->where('user_type','userdownline')->where('active','T')->first();

            //send email
            $ownedcompanyid = "";
            $ownedcompanyid = $campaign->company_id;

            $subject = '';
            $subject = 'Your Campaign '. $campaign->campaign_name .' is Paused - keywords missing';

            $details = [
                'campaign_name' => $campaign->campaign_name,
                'leadspeek_api_id' => $campaign->leadspeek_api_id,
                'campaign_link' => $campaign_link,
            ];

            $defaultdomain = $this->getDefaultDomainEmail($ownedcompanyid);

            $from = [
                'address' => 'noreply@' . $defaultdomain,
                'name' => 'noreply@' . $defaultdomain,
                'replyto' => 'noreply@' . $defaultdomain,
            ];        

            if (isset($user->email) && !empty($user->email)) {
                $this->send_email(array($user->email),$subject,$details,array(),'emails.bigdbmcreatelisterror',$from,$ownedcompanyid);
            }
        }
    }

    /** GENERAL FUNCTION */
}
