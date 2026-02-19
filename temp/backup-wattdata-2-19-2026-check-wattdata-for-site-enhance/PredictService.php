<?php

namespace App\Services;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\LeadspeekInvoice;
use App\Models\LeadspeekPredictReport;
use App\Models\LeadspeekWattFieldCampaign;
use App\Models\LeadspeekWattFieldCompany;
use App\Models\LeadspeekWattFieldInternal;
use App\Models\LeadspeekWattFieldMaster;
use App\Models\LeadspeekUser;
use App\Models\User;
use Carbon\Carbon;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use ZipArchive;
use Stripe\Exception\RateLimitException;
use Stripe\Exception\OAuth\InvalidRequestException;
use Stripe\Exception\AuthenticationException;
use Stripe\Exception\ApiConnectionException;
use Stripe\Exception\ApiErrorException;
use Exception;
use Illuminate\Support\Facades\DB;
use Stripe\StripeClient;

class PredictService
{
    private Controller $controller;

    public function __construct(Controller $controller) 
    {
        $this->controller = $controller;
    }

    // ================================== PUBLIC  FUNCTION ==================================
    /**
     * untuk mendapat person dari mcp mastra
     * @param array $json
     * @return array
     * format json:
     *  {
            "expression": "(900000002) AND (900000023) AND (1800000051) AND (900000021)",
            "export_format": "csv",
            "domains": ["name", "email", "demographic", "address"],
            "audience_limit": 50,
            "location": {
                "latitude": 40,
                "longitude": -83,
                "radius": 100,
                "unit": "km"
            }
        }
     */
    public function findPerson_mcpMastra(array $json)
    {
        // Log::info('findPerson_mcpMastra', ['json' => $json]);
        /* CONFIG */
        $baseUrl = config('services.predict_service.base_url', 'localhost:4111');
        $timeout = (int) config('services.predict_service.timeout', 120);
        $token = config('services.predict_service.token', '');
        $url = "{$baseUrl}/find-persons";
        /* CONFIG */

        /* REQUEST */
        try{
            $client = new Client([
                'timeout' => $timeout,
                'http_errors' => false,
            ]);
            
            $response = $client->post($url, [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                    'x-internal-token' => $token,
                ],
                'json' => $json,
            ]);
            $responseBody = $response->getBody()->getContents();
            $responseData = json_decode($responseBody, true);
            // Log::info('findPerson_mcpMastra response', ['responseBody' => $responseBody]);
            
            // Handle multiple export files (array) atau single file
            $exportFiles = [];
            if(isset($responseData['export'])){
                if(isset($responseData['export'][0])){
                    // Multiple files (array)
                    $exportFiles = $responseData['export'];
                }elseif(isset($responseData['export']['url'])){
                    // Single file (legacy format)
                    $exportFiles = [$responseData['export']];
                }
            }
            
            // Calculate total leads
            $total_leads = 0;
            foreach($exportFiles as $file){
                $total_leads += isset($file['rows']) ? (int) $file['rows'] : 0;
            }
            
            // Extract pagination info
            $workflow_id = $responseData['workflow_id'] ?? null;
            $has_more = $responseData['has_more'] ?? false;
            $next_offset = $responseData['next_offset'] ?? null;
            $total_matches = $responseData['total_matches'] ?? null;
            
            return [
                'status' => 'success', 
                'export' => $exportFiles, 
                'total_leads' => $total_leads, 
                'workflow_id' => $workflow_id,
                'has_more' => $has_more,
                'next_offset' => $next_offset,
                'total_matches' => $total_matches,
                'response_data' => $responseData
            ];
        }catch(\Exception $e){
            Log::info('findPerson_mcpMastra error', ['message' => $e->getMessage()]);
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
        /* REQUEST */
    }

    /**
     * Upload ZIP lokal ke DigitalOcean Spaces untuk Predict, dan kembalikan URL CDN.
     *
     * @param string $localZipPath Path absolut file ZIP di local (storage_path)
     * @param string $zipFileName Nama file ZIP (misal: predict_report_campaign_xxx.zip)
     * @return array ['status' => 'success', 'file_download' => string] atau ['status' => 'error', 'message' => string]
     */
    public function uploadPredictZipToSpaces(string $localZipPath, string $zipFileName): array
    {
        try{
            if(!file_exists($localZipPath)){
                return ['status' => 'error', 'message' => "ZIP file not found at {$localZipPath}"];
            }

            $zipPathInSpaces = "users/predict_merges/{$zipFileName}";

            $readStream = fopen($localZipPath, 'r');
            if(!$readStream){
                return ['status' => 'error', 'message' => "Failed to open ZIP file stream at {$localZipPath}"];
            }

            Storage::disk('spaces')->put($zipPathInSpaces, $readStream, 'public');
            fclose($readStream);

            $file_download = Storage::disk('spaces')->url($zipPathInSpaces);
            $file_download = str_replace('digitaloceanspaces.com', 'cdn.digitaloceanspaces.com', $file_download);

            if(file_exists($localZipPath)){
                @unlink($localZipPath);
            }

            return ['status' => 'success', 'file_download' => $file_download];
        }catch(\Exception $e){
            Log::info('uploadPredictZipToSpaces error', ['message' => $e->getMessage()]);
            if(file_exists($localZipPath)){
                @unlink($localZipPath);
            }
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    /**
     * Check custom fields config untuk campaign. Cek table leadspeek_watt_field_campaigns.
     * Jika ada config (field_id dari general setting atau field_order campaign-specific), return custom_name dan ordered field_slugs.
     *
     * @param int $leadspeek_api_id
     * @return array|null ['custom_name' => string, 'ordered_field_slugs' => array] atau null jika tidak ada custom fields
     */
    public function checkCampaignCustomFieldsConfig(int $leadspeek_api_id): ?array
    {
        /* CHECK APAKAH CUSTOM FIELD CAMPAIGN PREDICT ADA */
        $campaignConfig = LeadspeekWattFieldCampaign::where('leadspeek_api_id', $leadspeek_api_id)
            ->where('leadspeek_type', 'predict')
            ->first();
        if(empty($campaignConfig)){
            return null;
        }
        /* CHECK APAKAH CUSTOM FIELD CAMPAIGN PREDICT ADA */

        /* FILTER APAKAH FIELD INI DIAMBIL DARI GENERAT SETTING ATAU CAMPAIGN SPECIFIC */
        $fieldOrderIds = null;
        $customName = null;
        if(!empty($campaignConfig->field_id)){
            $companyConfig = LeadspeekWattFieldCompany::where('id', $campaignConfig->field_id)
                ->where('leadspeek_type', 'predict')
                ->first();
            if(empty($companyConfig)){
                return null;
            }

            $rawFieldOrder = $companyConfig->field_order;
            $fieldOrderIds = is_string($rawFieldOrder) ? json_decode($rawFieldOrder, true) : $rawFieldOrder;
            if(empty($fieldOrderIds) || !is_array($fieldOrderIds)){
                return null;
            }

            $customName = $companyConfig->field_name ?? 'custom';
        }else{
            $rawFieldOrder = $campaignConfig->field_order;
            $fieldOrderIds = is_string($rawFieldOrder) ? json_decode($rawFieldOrder, true) : $rawFieldOrder;
            if(empty($fieldOrderIds) || !is_array($fieldOrderIds)){
                return null;
            }

            $customName = $campaignConfig->field_name ?? 'custom';
        }
        /* FILTER APAKAH FIELD INI DIAMBIL DARI GENERAT SETTING ATAU CAMPAIGN SPECIFIC */

        /* JIKA ORDER ID KOSONG */
        if(empty($fieldOrderIds)){
            return null;
        }
        /* JIKA ORDER ID KOSONG */

        /* AMBIL FIELD SLUG DAN FIELD WATT DARI ID YANG DIHASILKAN */
        $masters = LeadspeekWattFieldMaster::whereIn('id', $fieldOrderIds)
            ->where('leadspeek_type', 'predict')
            ->get()
            ->keyBy('id');
        $orderedFieldSlugs = [];
        $orderedFieldWatts = [];
        foreach($fieldOrderIds as $id){
            $m = $masters->get($id);
            if($m && !empty($m->field_slug)){
                $orderedFieldSlugs[] = $m->field_slug;
                $orderedFieldWatts[] = $m->field_watt ?? '';
            }
        }
        /* AMBIL FIELD SLUG DAN FIELD WATT DARI ID YANG DIHASILKAN */

        /* JIKA ORDERED FIELD SLUGS KOSONG */
        if(empty($orderedFieldSlugs)){
            return null;
        }
        /* JIKA ORDERED FIELD SLUGS KOSONG */

        /* RETURN CUSTOM NAME, ORDERED FIELD MASTER IDS, SLUGS (UNTUK HEADER), DAN WATTS (UNTUK AMBIL NILAI DARI RAW) */
        return [
            'custom_name' => $customName,
            'ordered_field_master_ids' => $fieldOrderIds,
            'ordered_field_slugs' => $orderedFieldSlugs,
            'ordered_field_watts' => $orderedFieldWatts,
        ];
        /* RETURN CUSTOM NAME, ORDERED FIELD MASTER IDS, SLUGS (UNTUK HEADER), DAN WATTS (UNTUK AMBIL NILAI DARI RAW) */
    }

    /**
     * Get headers untuk CUSTOM CSV (sesuai field order).
     *
     * @param array $orderedFieldSlugs
     * @return array
     */
    public function getCustomCsvHeaders(array $orderedFieldSlugs): array
    {
        return $orderedFieldSlugs;
    }

    /**
     * Ambil config internal CSV types dari table leadspeek_watt_field_internals (active saja).
     * Resolve field_order ke ordered_field_slugs dan ordered_field_watts.
     *
     * @return array Keyed by csv_type, value = ['csv_type'=>..., 'name'=>..., 'ordered_field_master_ids'=>..., 'ordered_field_slugs'=>..., 'ordered_field_watts'=>..., 'row_mode'=>...]
     */
    public function getActiveInternalConfigs(): array
    {
        $internals = LeadspeekWattFieldInternal::where('active', 'T')
            ->where('leadspeek_type', 'predict')
            ->orderBy('id')
            ->get();
        $configs = [];
        foreach($internals as $internal){
            $rawFieldOrder = $internal->field_order;
            $fieldOrderIds = is_string($rawFieldOrder) ? json_decode($rawFieldOrder, true) : $rawFieldOrder;
            if(empty($fieldOrderIds) || !is_array($fieldOrderIds)){
                continue;
            }

            $masters = LeadspeekWattFieldMaster::whereIn('id', $fieldOrderIds)
                ->where('leadspeek_type', 'predict')
                ->get()
                ->keyBy('id');
            $orderedFieldSlugs = [];
            $orderedFieldWatts = [];
            foreach($fieldOrderIds as $id){
                $m = $masters->get($id);
                if($m && !empty($m->field_slug)){
                    $orderedFieldSlugs[] = $m->field_slug;
                    $orderedFieldWatts[] = $m->field_watt ?? '';
                }
            }
            if(empty($orderedFieldSlugs)){
                continue;
            }

            $configs[$internal->csv_type] = [
                'csv_type' => $internal->csv_type,
                'name' => $internal->name,
                'ordered_field_master_ids' => $fieldOrderIds,
                'ordered_field_slugs' => $orderedFieldSlugs,
                'ordered_field_watts' => $orderedFieldWatts,
                'row_mode' => $internal->row_mode ?? 'single',
            ];
        }
        return $configs;
    }

    /**
     * Hitung full_address dari raw row (gabungan address_primary, address_secondary, city, state, zip).
     * Dipakai ketika field_watt = 'full_address' (kolom computed, tidak ada di API).
     *
     * @param array $rawRow
     * @param array $rawHeaders
     * @return string
     */
    private function computeFullAddressFromRawRow(array $rawRow, array $rawHeaders): string
    {
        $getValue = function ($headerName) use ($rawRow, $rawHeaders){
            $index = array_search($headerName, $rawHeaders);
            return ($index !== false && isset($rawRow[$index])) ? trim((string) $rawRow[$index]) : '';
        };
        $addressLine1 = $getValue('domains_addresses_0_address_primary');
        $addressLine2 = $getValue('domains_addresses_0_address_secondary');
        $city = $getValue('domains_addresses_0_city');
        $state = $getValue('domains_addresses_0_state');
        $zip = $getValue('domains_addresses_0_zip');
        $parts = array_filter([$addressLine1, $addressLine2, $city, $state, $zip]);
        $partString = implode(', ', $parts);
        // Log::info("PredictService::computeFullAddressFromRawRow", ['addressLine1' => $addressLine1, 'addressLine2' => $addressLine2, 'city' => $city, 'state' => $state, 'zip' => $zip, 'parts' => $parts, 'partString' => $partString]);
        return $partString;
    }

    /**
     * Transform row ke format internal dengan row_mode per_email: 1 raw row → banyak baris (satu per email).
     * Kolom pertama = email (dari domains_emails_0..9), kolom sisanya dari orderedFieldWatts.
     *
     * @param array $rawRow
     * @param array $rawHeaders
     * @param array $orderedFieldWatts
     * @return array Array of rows
     */
    public function transformToInternalPerEmail(array $rawRow, array $rawHeaders, array $orderedFieldWatts): array
    {
        /* JIKA FIELD WATTS KOSONG */
        if(empty($orderedFieldWatts)){
            return [];
        }
        /* JIKA FIELD WATTS KOSONG */

        /* BUAT HELPER UNTUK GET DATA DARI RAW ROW BERDASARKAN WATT HEADER */
        $getValue = function ($wattHeader) use ($rawRow, $rawHeaders){
            // Special case: country selalu return 'US'
            if($wattHeader === 'country'){
                return 'US';
            }
            if(!is_string($wattHeader) || $wattHeader === ''){
                return '';
            }
            $index = array_search($wattHeader, $rawHeaders);
            return ($index !== false && isset($rawRow[$index])) ? trim((string) $rawRow[$index]) : '';
        };
        /* BUAT HELPER UNTUK GET DATA DARI RAW ROW BERDASARKAN WATT HEADER */

        /* AMBIL LIST EMAIL(0-9) DALAM 1 ROW */
        $emails = [];
        for($i = 0; $i <= 9; $i++){
            $h = "domains_emails_{$i}_email_address";
            $v = $getValue($h);
            if($v !== ''){
                $emails[] = $v;
            }
        }
        /* AMBIL LIST EMAIL(0-9) DALAM 1 ROW */

        /* JIKA DARI LIST EMAIL(0-9) KOSONG */
        if(empty($emails)){
            $row = [];
            for($i = 0; $i < count($orderedFieldWatts); $i++){
                $w = $orderedFieldWatts[$i] ?? '';
                if($w === 'full_address'){
                    $row[] = $this->computeFullAddressFromRawRow($rawRow, $rawHeaders);
                }elseif($w === 'country'){
                    $row[] = 'US';
                }else{
                    $row[] = $getValue($orderedFieldWatts[$i]);
                }
            }
            return [$row];
        }
        /* JIKA DARI LIST EMAIL(0-9) KOSONG */

        /* UNTUK leadspeek_watt_field_internals row_mode per_email, CHECK 1 ID FIELD WATT YANG MENGANDUNG 'domains_emails_x_email_address' */
        $emailColumnIndex = null;
        for($i = 0; $i < count($orderedFieldWatts); $i++){
            $w = $orderedFieldWatts[$i] ?? '';
            if(is_string($w) && preg_match('/^domains_emails_\d+_email_address$/', $w)){
                $emailColumnIndex = $i;
                break;
            }
        }
        /* UNTUK leadspeek_watt_field_internals row_mode per_email, CHECK 1 ID FIELD WATT YANG MENGANDUNG 'domains_emails_x_email_address' */

        /* KUMPULKAN SEMUA LIST EMAIL YANG DI DAPAT, UNTUK 1 ORANG, JADI EMAIL BANYAK UNTUK 1 ORANG YANG SAMA */
        $fullAddressComputed = $this->computeFullAddressFromRawRow($rawRow, $rawHeaders);
        $rows = [];
        foreach($emails as $email){
            $row = [];
            for($i = 0; $i < count($orderedFieldWatts); $i++){
                $w = $orderedFieldWatts[$i] ?? '';
                if($w === 'full_address'){
                    $row[] = $fullAddressComputed;
                }elseif($w === 'country'){
                    $row[] = 'US';
                }else{
                    $row[] = ($emailColumnIndex !== null && $i === $emailColumnIndex) ? $email : $getValue($orderedFieldWatts[$i]);
                }
            }
            $rows[] = $row;
        }
        /* KUMPULKAN SEMUA LIST EMAIL YANG DI DAPAT, UNTUK 1 ORANG, JADI EMAIL BANYAK UNTUK 1 ORANG YANG SAMA */

        return $rows;
    }

    /**
     * Transform row dari CSV kotor ke CUSTOM CSV format (1 baris, kolom sesuai ordered_field_watts).
     * Mengambil nilai langsung dari raw row berdasarkan nama kolom Watt (field_watt).
     *
     * @param array $rawRow
     * @param array $rawHeaders
     * @param array $orderedFieldWatts Nama kolom Watt/API untuk tiap field (urutan = urutan kolom custom)
     * @return array
     */
    public function transformToCustom(array $rawRow, array $rawHeaders, array $orderedFieldWatts): array
    {
        /* JIKA FIELD WATTS KOSONG */
        if(empty($orderedFieldWatts)){
            return [];
        }
        /* JIKA FIELD WATTS KOSONG */

        /* SUSUN ROW BERDASARKAN ORDERED FIELD WATTS */
        $row = [];
        foreach($orderedFieldWatts as $wattHeader){
            if($wattHeader === 'full_address'){
                $row[] = $this->computeFullAddressFromRawRow($rawRow, $rawHeaders);
                continue;
            }
            if($wattHeader === 'country'){
                $row[] = 'US';
                continue;
            }
            $index = is_string($wattHeader) && $wattHeader !== '' ? array_search($wattHeader, $rawHeaders) : false;
            $row[] = ($index !== false && isset($rawRow[$index])) ? trim((string) $rawRow[$index]) : '';
        }
        /* SUSUN ROW BERDASARKAN ORDERED FIELD WATTS */

        return $row;
    }

    public function chargeCampaign(LeadspeekUser $campaign)
    {
        try{
            /* CHARGE CAMPAIGN FOR SELECTED CAMPAIGN */
            $campaign_id = $campaign->id ?? null;
            $campaign_name = $campaign->campaign_name ?? "";
            $campaign_type = $campaign->leadspeek_type ?? null;
            $campaign_leadspeek_api_id = $campaign->leadspeek_api_id ?? null;
            $handlers = [
                'predict' => function () use ($campaign) {
                    return $this->chargeCampaignPredict($campaign);
                },
                'audience' => function () use ($campaign) {
                    return $this->chargeCampaignAudience($campaign);
                },
            ];
    
            if(!isset($handlers[$campaign_type])){
                Log::info("PredictService::chargeCampaign Invalid module type", [
                    'campaign_id' => $campaign_id,
                    'campaign_name' => $campaign_name,
                    'campaign_type' => $campaign_type,
                    'campaign_leadspeek_api_id' => $campaign_leadspeek_api_id
                ]);
                return ['result' => 'failed', 'message' => "{$campaign_type} Invalid type For campaign name {$campaign_name}, campaign leadspeek api id {$campaign_leadspeek_api_id}"];
            }
    
            return $handlers[$campaign_type]();
            /* CHARGE CAMPAIGN FOR SELECTED CAMPAIGN */
        }catch(\Throwable $e){
            Log::info("PredictService::chargeCampaign Error", [
                'message' => $e->getMessage(),
                'campaign_id' => $campaign->id ?? null,
                'campaign_name' => $campaign->campaign_name ?? "",
                'campaign_type' => $campaign->leadspeek_type ?? null,
                'campaign_leadspeek_api_id' => $campaign->leadspeek_api_id ?? null
            ]);
            return ['result' => 'failed', 'message' => $e->getMessage()];
        }
    }
    // ================================== PUBLIC  FUNCTION ==================================

    // ================================== PRIVATE  FUNCTION ==================================
    private function chargeCampaignPredict(LeadspeekUser $campaign)
    {
        try{
            /* GET ACC CONNECTED ACCOUNT */
            $agency_company_id = $campaign->clientowner ?? null;
            $company_root_id = $campaign->company_root_id ?? null;
            $accConID = $this->controller->check_connected_account($agency_company_id, $company_root_id);
            if(empty($accConID)){
                return ['result' => 'error', 'message' => 'Agency connected account not found'];
            }
            /* GET ACC CONNECTED ACCOUNT */

            /* GET MANUAL BILL */
            $salt = substr(hash('sha256', env('APP_KEY')), 0, 16);
            $company_parent = $campaign->company_parent ?? null;
            $agency = User::from('users as u')
                ->select(
                    'u.*',
                    DB::raw("CONVERT(AES_DECRYPT(FROM_bASE64(c.email), '" . $salt . "') USING utf8mb4) AS company_email"),
                    'c.manual_bill as manual_bill'
                )
                ->join('companies as c', 'u.company_id', '=', 'c.id')
                ->where('u.user_type', 'userdownline')
                ->where('u.company_id', $company_parent)
                ->where('u.active', 'T')
                ->first();
            $agency_manual_bill = $agency->manual_bill ?? '';
            $agency_company_email = $agency->company_email ?? '';
            /* GET MANUAL BILL */

            /* GET CLIENT DATA */
            $client_company_name = $campaign->company_name ?? "";
            $leadspeek_api_id = $campaign->leadspeek_api_id ?? "";
            $client_user_id = $campaign->user_id ?? null;
            $client_company_id = $campaign->company_id ?? null;
            $client_email = $campaign->email ?? "";
            /* GET CLIENT DATA */

            /* SETUP PAYMENT AMOUNT */
            $customer_payment_id = trim($campaign->customer_payment_id ?? "");
            $customer_card_id = trim($campaign->customer_card_id ?? "");
            if(empty($customer_payment_id) || empty($customer_card_id)){
                return ['result' => 'error', 'message' => 'Client payment method not found'];
            }

            $platformFee = (float) number_format(($campaign->platformfee ?? 0), 2, '.', '');
            $minCostMonth =  (float) number_format(($campaign->lp_min_cost_month ?? 0), 2, '.', '');
            $totalAmount = (float) number_format(($platformFee + $minCostMonth), 2, '.', '');
            $chargeAmount = (float) number_format(($totalAmount * 100), 0, '.', ''); // in cents
            /* SETUP PAYMENT AMOUNT */

            /* BUILD START DATE AND NEXT BILLING DATE */
            $now = Carbon::now();
            // $now = Carbon::parse('2026-03-03 01:21:17');
            $start_date = $now->format('Y-m-d');
            $start_datetime = $now->format('Y-m-d H:i:s');
            $next_billing_date = $now->copy()->addMonthNoOverflow()->format('Y-m-d');
            $next_billing_datetime = $now->copy()->addMonthNoOverflow()->format('Y-m-d H:i:s');
            /* BUILD START DATE AND NEXT BILLING DATE */

            /** GET STRIPE KEY */
            $stripeseckey = config('services.stripe.secret');
            $stripepublish = $this->controller->getcompanysetting($company_root_id,'rootstripe');
            if ($stripepublish != '') {
                $stripeseckey = $stripepublish->secretkey ?? "";
            }
            Log::info("PredictService::chargeCampaign - Using Stripe Key", [
                'company_root_id' => $company_root_id,
                'stripeseckey' => $stripeseckey,
                'stripepublish' => $stripepublish,
            ]);
            /** GET STRIPE KEY */

            /* CHARGE WITH STRIPE */
            $paymentintentID = "";
            $message = "";
            $statusPayment = 'paid';
            $nowFormat = $now->format('YmdHis');
            $defaultInvoice = "#{$nowFormat}-{$client_company_name} #{$leadspeek_api_id}";
            if($totalAmount >= 0.5 && $agency_manual_bill == "F"){
                try{
                    $stripe = new StripeClient([
                        'api_key' => $stripeseckey,
                        'stripe_version' => '2020-08-27'
                    ]);
        
                    Log::info("PredictService::chargeCampaign - Initiating charge stripe", [
                        'agency_company_id' => $agency_company_id,
                        'agency_company_email' => $agency_company_email,
                        'client_user_id' => $client_user_id,
                        'client_email' => $client_email,
                        'amount' => $totalAmount,
                        'description' => $defaultInvoice,
                        'connected_account_id' => $accConID
                    ]);
                    $payment_intent =  $stripe->paymentIntents->create([
                        'payment_method_types' => ['card'],
                        'customer' => $customer_payment_id,
                        'amount' => $chargeAmount,
                        'currency' => 'usd',
                        'receipt_email' => $agency_company_email,
                        'payment_method' => $customer_card_id,
                        'confirm' => true,
                        'description' => $defaultInvoice,
                    ],['stripe_account' => $accConID]);
        
                    // check status payment intents
                    $paymentintentID = isset($payment_intent->id) ? $payment_intent->id : '';
                    $payment_intent_status = (isset($payment_intent->status))?$payment_intent->status:"";
                    if($payment_intent_status == 'requires_action'){
                        $statusPayment = 'failed';
                        $message = "Payment for create this campaign was unsuccessful: Stripe status '$payment_intent_status' indicates further user action is needed.";
                    }
                }catch(RateLimitException $e) {
                    // Too many requests made to the API too quickly
                    $statusPayment = 'failed';
                    $message = $e->getHttpStatus() . '|' . $e->getError()->type . '|' . $e->getError()->code . '|' . $e->getError()->param . '|' . $e->getError()->message;
                    Log::error("PredictService::chargeCampaign - Catch Rate limit exception - {$message}");
                }catch(InvalidRequestException $e){
                    // Invalid parameters were supplied to Stripe's API
                    $statusPayment = 'failed';
                    $message = $e->getHttpStatus() . '|' . $e->getError()->type . '|' . $e->getError()->code . '|' . $e->getError()->param . '|' . $e->getError()->message;
                    Log::error("PredictService::chargeCampaign - Catch Invalid request exception - {$message}");
                }catch(AuthenticationException $e){
                    // Authentication with Stripe's API failed
                    // (maybe you changed API keys recently)
                    $statusPayment = 'failed';
                    $message = $e->getHttpStatus() . '|' . $e->getError()->type . '|' . $e->getError()->code . '|' . $e->getError()->param . '|' . $e->getError()->message;
                    Log::error("PredictService::chargeCampaign - Catch Authentication exception - {$message}");
                }catch(ApiConnectionException $e){
                    // Network communication with Stripe failed
                    $statusPayment = 'failed';
                    $message = $e->getHttpStatus() . '|' . $e->getError()->type . '|' . $e->getError()->code . '|' . $e->getError()->param . '|' . $e->getError()->message;
                    Log::error("PredictService::chargeCampaign - Catch API connection exception - {$message}");
                }catch(ApiErrorException $e){
                    // Display a very generic error to the user, and maybe send
                    // yourself an email
                    $statusPayment = 'failed';
                    $message = $e->getHttpStatus() . '|' . $e->getError()->type . '|' . $e->getError()->code . '|' . $e->getError()->param . '|' . $e->getError()->message;
                    Log::error("PredictService::chargeCampaign - Catch API error exception - {$message}");
                }catch(Exception $e){
                    // Something else happened, completely unrelated to Stripe
                    $statusPayment = 'failed';
                    $message = $e->getMessage();
                    Log::error("PredictService::chargeCampaign -  Catch General exception - {$message}");
                }
            }
            /* CHARGE WITH STRIPE */

            /* PROCESS BASE ON PAYMENT STATUS */
            $invoiceNum = "";
            if($statusPayment != "failed"){
                /* LEADSPEEK INVOICE */
                $invoieCreated = LeadspeekInvoice::create([
                    'company_id' => $client_company_id,
                    'user_id' => $client_user_id,
                    'leadspeek_api_id' => $leadspeek_api_id,
                    'invoice_number' => '',
                    'payment_term' => 'Prepaid',
                    'onetimefee' => $platformFee,
                    'platform_onetimefee' => 0,
                    'min_leads' => 0,
                    'exceed_leads' => 0,
                    'total_leads' => 0,
                    'min_cost' => $minCostMonth,
                    'platform_min_cost' => 0,
                    'cost_leads' => 0,
                    'platform_cost_leads' => 0,
                    'total_amount' => $totalAmount,
                    'platform_total_amount' => 0,
                    'root_total_amount' => 0,
                    'status' => $statusPayment,
                    'customer_payment_id' => $paymentintentID,
                    'customer_stripe_id' => $customer_payment_id,
                    'customer_card_id' => $customer_card_id,
                    'platform_customer_payment_id' => '',
                    'error_payment' => '',
                    'platform_error_payment' => '',
                    'invoice_date' => $start_date,
                    'invoice_start' => $start_date,
                    'invoice_end' => $next_billing_date,
                    'sent_to' => $agency_company_email,
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
                $invoiceID = $invoieCreated->id ?? ''; 
                $invoiceNum = "{$nowFormat}-{$leadspeek_api_id}-{$invoiceID}";
                if(!empty($invoiceID)){
                    LeadspeekInvoice::where('id', $invoiceID)->update(['invoice_number' => $invoiceNum]);
                }
                /* LEADSPEEK INVOICE */

                /* UPDATE LEADSPEEKUSER */
                LeadspeekUser::where('leadspeek_api_id', $leadspeek_api_id)
                    ->update([
                        'lp_invoice_date' => $start_datetime,
                    ]);
                /* UPDATE LEADSPEEKUSER */
            }else{
                /* UPDATE STATUS CAMPAIGN TO STOP */
                LeadspeekUser::where('leadspeek_api_id', $leadspeek_api_id)
                    ->update([
                        'active' => 'F',
                        'disabled' => 'T',
                        'active_user' => 'F',
                    ]);
                /* UPDATE STATUS CAMPAIGN TO STOP */
            }
            /* PROCESS BASE ON PAYMENT STATUS */

             /* UPDATE PAYMENT STATUS CLIENT */
            User::where('id', $client_user_id)
                ->update(['payment_status' => ($statusPayment == "failed" ? "failed" : '')]);
            /* UPDATE PAYMENT STATUS CLIENT */

            /* SEND EMAIL */
            $root_company_name = Company::where('id', $company_root_id)->value('company_name');
            
            $AdminDefault = $this->controller->get_default_admin($company_root_id);
            $AdminDefaultEmail = (isset($AdminDefault[0]['email']))?$AdminDefault[0]['email']:'';
            $defaultdomain = $this->controller->getDefaultDomainEmail($company_root_id);

            $campaign_name = $campaign->campaign_name ?? '';
            $title = "Invoice for {$client_company_name} - {$campaign_name} #{$leadspeek_api_id} ({$start_date})";
            if($statusPayment == "failed"){
                $title = "Failed Payment - {$title}";
            }

            $campaignFind = LeadspeekUser::where('leadspeek_api_id', $leadspeek_api_id)->first();
            $active = $campaignFind->active ?? '';
            $disabled = $campaignFind->disabled ?? '';
            $active_user = $campaignFind->active_user ?? '';
            $campaign_status = ($active == 'T' && $disabled == 'F' && $active_user == 'T') || ($active == 'F' && $disabled == 'F' && $active_user == 'T') ? "Running" : "Stopped";

            $details = [
                'root_company_name' => $root_company_name,
                'campaign_name' => $campaign_name,
                'campaign_id' => $leadspeek_api_id,
                'campaign_status' => $campaign_status,
                'leadspeek_type' => 'Predict',
                'invoice_status' => $statusPayment,
                'invoice_number' => $invoiceNum,
                'start_date' => $start_date,
                'next_billing_date' => $next_billing_date,
                'defaultadmin' => $AdminDefaultEmail,
                'customer_total_invoice' => $totalAmount,
                'agency_net_proceeds' => $totalAmount,
            ];
            $from = [
                'address' => "noreply@{$defaultdomain}",
                'name' => 'Invoice',
                'replyto' => "support@{$defaultdomain}",
            ];
            $attachment = [];

            $this->controller->send_email([$agency_company_email],$title,$details,$attachment,'emails.campaignpredictcharge',$from,$company_root_id,true);
            $this->controller->send_email(['serverlogs@sitesettingsapi.com'],$title,$details,$attachment,'emails.campaignpredictcharge',$from,'',true);
            /* SEND EMAIL */
        }catch(\Throwable $e){
            Log::info("PredictService::chargeCampaignPredict Error", [
                'message' => $e->getMessage(),
                'campaign_id' => $campaign->id ?? null,
                'campaign_name' => $campaign->campaign_name ?? "",
                'campaign_leadspeek_api_id' => $campaign->leadspeek_api_id ?? null
            ]);
            return ['result' => 'failed', 'message' => $e->getMessage()];
        }
    }
    
    private function chargeCampaignAudience(LeadspeekUser $campaign)
    {

    }

    /**
     * Send email notification ketika Predict report selesai di-generate
     * 
     * @param int $predict_report_id
     * @param int $total_leads
     * @return void
     */
    public function sendPredictReportReadyEmail(int $predict_report_id, int $total_leads = 0): void
    {
        try{
            /* GET PREDICT REPORT */
            $predictReport = LeadspeekPredictReport::find($predict_report_id);
            if(empty($predictReport)){
                Log::warning('PredictService::sendPredictReportReadyEmail: PredictReport not found', ['predict_report_id' => $predict_report_id]);
                return;
            }
            /* GET PREDICT REPORT */

            /* GET CAMPAIGN */
            $leadspeek_api_id = $predictReport->leadspeek_api_id;
            $campaign = LeadspeekUser::where('leadspeek_api_id', $leadspeek_api_id)
                ->where('leadspeek_type', 'predict')
                ->where('archived', 'F')
                ->first();
            if(empty($campaign)){
                Log::warning('PredictService::sendPredictReportReadyEmail: Campaign not found', ['leadspeek_api_id' => $leadspeek_api_id]);
                return;
            }
            /* GET CAMPAIGN */

            /* GET AGENCY EMAIL */
            $company_id = $campaign->company_id ?? null;
            $company_root_id = $campaign->company_root_id ?? null;
            if(empty($company_id)){
                Log::warning('PredictService::sendPredictReportReadyEmail: Company ID not found', ['leadspeek_api_id' => $leadspeek_api_id]);
                return;
            }

            $salt = substr(hash('sha256', env('APP_KEY')), 0, 16);
            $agency = User::from('users as u')
                ->select(
                    'u.*',
                    DB::raw("CONVERT(AES_DECRYPT(FROM_bASE64(c.email), '" . $salt . "') USING utf8mb4) AS company_email")
                )
                ->join('companies as c', 'u.company_id', '=', 'c.id')
                ->where('u.user_type', 'userdownline')
                ->where('u.company_id', $company_id)
                ->where('u.active', 'T')
                ->first();
            
            $agency_company_email = $agency->company_email ?? '';
            if(empty($agency_company_email)){
                Log::warning('PredictService::sendPredictReportReadyEmail: Agency email not found', ['company_parent' => $company_id]);
                return;
            }
            /* GET AGENCY EMAIL */

            /* PREPARE EMAIL DETAILS */
            $campaign_name = $campaign->campaign_name ?? '';
            $report_start_date = $predictReport->start_date ? Carbon::parse($predictReport->start_date)->format('Y-m-d') : '';
            $report_end_date = $predictReport->end_date ? Carbon::parse($predictReport->end_date)->format('Y-m-d') : '';
            $generated_at = $predictReport->updated_at ? Carbon::parse($predictReport->updated_at)->format('Y-m-d H:i:s') : Carbon::now()->format('Y-m-d H:i:s');
            
            // Check apakah ada custom fields
            $hasCustomFields = LeadspeekWattFieldCampaign::where('leadspeek_api_id', $leadspeek_api_id)
                ->where('leadspeek_type', 'predict')
                ->whereNotNull('field_order')
                ->exists();

            $root_company_name = Company::where('id', $company_root_id)->value('company_name') ?? 'Exact Match Marketing';
            $AdminDefault = $this->controller->get_default_admin($company_root_id);
            $AdminDefaultEmail = (isset($AdminDefault[0]['email'])) ? $AdminDefault[0]['email'] : 'fisikamodern00@gmail.com';
            $defaultdomain = $this->controller->getDefaultDomainEmail($company_root_id);

            $title = "Your Predict Campaign Report Is Ready to Download";
            $details = [
                'root_company_name' => $root_company_name,
                'campaign_name' => $campaign_name,
                'campaign_id' => $leadspeek_api_id,
                'report_start_date' => $report_start_date,
                'report_end_date' => $report_end_date,
                'total_leads' => $total_leads,
                'generated_at' => $generated_at,
                'has_custom_fields' => $hasCustomFields,
                'contact_email' => $AdminDefaultEmail,
            ];
            $from = [
                'address' => "noreply@{$defaultdomain}",
                'name' => 'Predict Report Completed',
                'replyto' => "support@{$defaultdomain}",
            ];
            $attachment = [];
            /* PREPARE EMAIL DETAILS */

            /* SEND EMAIL */
            $this->controller->send_email([$agency_company_email], $title, $details, $attachment, 'emails.campaignpredictreportready', $from, $company_root_id, true);
            $this->controller->send_email(['serverlogs@sitesettingsapi.com'], $title, $details, $attachment, 'emails.campaignpredictreportready', $from, '', true);
            /* SEND EMAIL */

            Log::info('PredictService::sendPredictReportReadyEmail: Email sent', [
                'predict_report_id' => $predict_report_id,
                'agency_email' => $agency_company_email,
                'total_leads' => $total_leads
            ]);
        }catch(\Throwable $e){
            Log::error('PredictService::sendPredictReportReadyEmail Error', [
                'message' => $e->getMessage(),
                'predict_report_id' => $predict_report_id,
                'trace' => $e->getTraceAsString()
            ]);
        }
    }
    // ================================== PRIVATE  FUNCTION ==================================
}