<?php

namespace App\Http\Controllers;

use App\Exports\LeadsExport;
use App\Mail\Gmail;
use App\Models\campaignInformation;
use App\Models\Company;
use App\Models\CompanySetting;
use App\Models\JobProgress;
use App\Models\LeadsListsQueue;
use App\Models\LeadspeekBusiness;
use App\Models\LeadspeekInvoice;
use App\Models\LeadspeekReport;
use App\Models\LeadspeekPredictReport;
use App\Models\LeadspeekUser;
use App\Models\LeadspeekConversionCampaign;
use App\Models\ModuleSetting;
use App\Models\State;
use App\Models\SuppressionList;
use App\Models\Topup;
use App\Models\User;
use App\Services\GoogleSheet;
use App\Services\PredictService;
use App\Services\SimpliFiService;
use DateTime;
use Exception;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Stripe\Exception\ApiConnectionException;
use Stripe\Exception\ApiErrorException;
use Stripe\Exception\AuthenticationException as ExceptionAuthenticationException;
use Stripe\Exception\OAuth\InvalidRequestException;
use Stripe\Exception\RateLimitException;
use Stripe\StripeClient;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use ZipArchive;
use Carbon\Carbon;
use App\Models\IntegrationSettings;
use App\Models\MasterFeature;
use App\Models\ServicesAgreement;
use App\Models\TopupAgency;
use App\Models\UserLog;
use App\Services\Leadspeek\LeadspeekGetCampaignService;
use App\Services\OpenApi\OpenApiWebhookService;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Cache;
use App\Models\CleanIDExport;
use App\Jobs\CleanIDExportJob;
use App\Models\CleanIDFile;
use GuzzleHttp\Client;

use function PHPUnit\Framework\isNull;

class LeadspeekController extends Controller
{
    private $_moduleID = "3";
    private $_settingTokenName = "leadsToken";


    private $BigDBMController;
    private $ConfigurationController;
    private $SimpliFiService;
    private $SimplifiController;
    private $OpenApiWebhookService;

    public function __construct(BigDBMController $BigDBMController, ConfigurationController $ConfigurationController, SimpliFiService $SimpliFiService, SimplifiController $SimplifiController, OpenApiWebhookService $OpenApiWebhookService)
    {
        $this->BigDBMController = $BigDBMController;
        $this->ConfigurationController = $ConfigurationController;
        $this->SimpliFiService = $SimpliFiService;
        $this->SimplifiController = $SimplifiController;
        $this->OpenApiWebhookService = $OpenApiWebhookService;
    }

    /* REFUND TOPUP */
    public function estimate_refund_topup(Request $request)
    {
        /* VALIDATION LEADSPEEK API ID EMPTY */
        $systemid = config('services.application.systemid');
        $leadspeek_api_id = (isset($request->leadspeek_api_id))?$request->leadspeek_api_id:'';
        if(empty($leadspeek_api_id) || trim($leadspeek_api_id) == ''){
            return response()->json(['result' => 'error', 'message' => 'Campaign id cannot empty'], 400);
        }
        /* VALIDATION LEADSPEEK API ID EMPTY */

        /* VALIDATION CAMPAIGN EXISTS AND STATUS CAMPAIGN */
        $campaign = LeadspeekUser::select('leadspeek_users.active','leadspeek_users.disabled','leadspeek_users.active_user','leadspeek_users.paymentterm','leadspeek_users.company_id','users.company_root_id')
            ->join('users', 'users.company_id', '=', 'leadspeek_users.company_id')
            ->where('leadspeek_users.leadspeek_api_id', $leadspeek_api_id)
            ->where('leadspeek_users.archived', 'F')
            ->where('users.active', 'T')
            ->first();
        if(empty($campaign)){
            return response()->json(['result' => 'error', 'message' => 'Campaign not found'], 404);
        }
        if(!($campaign->active == 'F' && $campaign->disabled == 'T' && $campaign->active_user == 'F')){
            return response()->json(['result' => 'error', 'message' => 'Please stop the campaign before refund campaign.'], 400);
        }
        if($campaign->paymentterm != 'Prepaid'){
            return response()->json(['result' => 'error', 'message' => 'Payment type campaign must be a prepaid.'], 400);
        }
        if($campaign->company_root_id != $systemid){
            return response()->json(['result' => 'error', 'message' => 'This Feature Is Not Available'], 400);
        }
        /* VALIDATION CAMPAIGN EXISTS STATUS CAMPAIGN */

        /* CHECK AGENCY MANUAL BILL OR NOT */
        $agencyManualBill = Company::where('id', $campaign->company_id)->value('manual_bill');
        /* CHECK AGENCY MANUAL BILL OR NOT */

        /* VALIDATION REMAINING BALANCE 0 */
        $idTopups = Topup::where('leadspeek_api_id','=', $leadspeek_api_id)
            ->where('topup_status','<>','done')
            ->pluck('id')
            ->toArray(); // info(['idTopups' => $idTopups]);
        $remainingBalanceLeads = Topup::where('leadspeek_api_id','=', $leadspeek_api_id)
            ->where('topup_status','<>','done')
            ->sum('total_leads') - DB::table('leadspeek_reports')
            ->where('leadspeek_api_id','=',$leadspeek_api_id)
            ->whereIn('topup_id', $idTopups)
            ->count(); // info(['remainingBalanceLeads' => $remainingBalanceLeads]);
        if($remainingBalanceLeads <= 0){
            return response()->json(['result' => 'error', 'message' => 'You cannot a refund because your remaining balance is 0 contact.'], 400);
        }
        /* VALIDATION REMAINING BALANCE 0 */

        /* ESTIMATE PRICE */
        $amountRefundAgency = 0;
        $amountRefundClientAuto = 0; // amount yang refund ke client secara otomatis langsung ke stripe
        $amountRefundClientManual = 0; // amount yang refund ke client secara manual, dikarenakan customer_payment_id belum ke record di database 

        $getInvoiceTopupCampaignNotDone = $this->getInvoiceTopupCampaignNotDone($leadspeek_api_id);
        $invoicesFinal = $getInvoiceTopupCampaignNotDone['invoicesFinal'] ?? collect([]);
        foreach($invoicesFinal as $invoice){
            $costPerleadClient = max(0, $invoice->cost_perlead);
            $costPerleadAgency = max(0, $invoice->platform_cost_leads);
            $customerPaymentId = $invoice->customer_payment_id ?? "";
            $remainingLeads = $invoice->remaining_leads ?? 0;
                
            $amountRefundAgency += ($remainingLeads * $costPerleadAgency);
            $refundClientAmount = ($remainingLeads * $costPerleadClient);
            if(!empty($customerPaymentId)){ // jika tidak kosong, maka masuk refund auto
                $amountRefundClientAuto += $refundClientAmount;
            }else{ // jika kosong maka masuk ke refund manual
                $amountRefundClientManual += $refundClientAmount;
            }
        }

        $amountRefundAgency = (float) number_format($amountRefundAgency,2,'.','');
        $amountRefundClientAuto = (float) number_format($amountRefundClientAuto,2,'.','');
        $amountRefundClientManual = (float) number_format($amountRefundClientManual,2,'.','');
        /* ESTIMATE PRICE */

        /* RETURN RESPONSE */
        $response = ['result' => 'success', 'amountRefundAgency' => $amountRefundAgency, 'amountRefundClientAuto' => $amountRefundClientAuto, 'amountRefundClientManual' => $amountRefundClientManual, 'remainingBalanceLeads' => $remainingBalanceLeads, 'agencyManualBill' => $agencyManualBill];
        // info('estimate_refund_topup', ['response' => $response]);
        return response()->json($response);
        /* RETURN RESPONSE */
    }

    public function refund_topup(Request $request)
    {
        date_default_timezone_set('America/Chicago');

        /* LOCK REFUND TOPUP CAMPAIGN */
        $leadspeek_api_id = (isset($request->leadspeek_api_id)) ? $request->leadspeek_api_id : '';
        $lockKey = "refund_topup_$leadspeek_api_id";
        $lock = Cache::lock($lockKey, 10);
        if(!$lock->get()){ // coba ambil lock → kalau gagal berarti lock sudah ada, sebisa mungkin process refund per leadspeek_api_id hanya satu
            return response()->json(['result' => 'error', 'message' => 'Another process is running. Try again later.'], 429);
        }
        /* LOCK REFUND TOPUP CAMPAIGN */

        /* GET ESTIMATE AMOUNT REFUND */
        $response = $this->estimate_refund_topup($request);
        $statusCode = $response->getStatusCode();
        $data = $response->getData();
        // info('GET ESTIMATE AMOUNT REFUND', ['statusCode' => $statusCode, 'data' => $data]);
        // return response()->json(['message' => 'ini hanya testing']);

        $result = isset($data->result) ? $data->result : '';
        $message = isset($data->message) ? $data->message : '';
        $amountRefundAgency = isset($data->amountRefundAgency) ? $data->amountRefundAgency : '0';
        $amountRefundClientAuto = isset($data->amountRefundClientAuto) ? $data->amountRefundClientAuto : '0';
        $amountRefundClientManual = isset($data->amountRefundClientManual) ? $data->amountRefundClientManual : '0';
        $agencyManualBill = isset($data->agencyManualBill) ? $data->agencyManualBill : 'F';
        $remainingBalanceLeads = isset($data->remainingBalanceLeads) ? $data->remainingBalanceLeads : '0';
        
        if($result == 'error'){
            $lock->release();
            return response()->json(['result' => 'error', 'message' => $message], $statusCode);
        }
        /* GET ESTIMATE AMOUNT REFUND */

        /* IF MANUAL BILLING IS F AND CLIENT REFUND AMOUNT > 0.5, CHECK WHETHER THE CONNECTED ACCOUNT AGENCY ACCOUNT BALANCE IS SUFFICIENT. */
        $company_id = isset($request->company_id) ? $request->company_id : '';
        if($agencyManualBill == 'F' && $amountRefundClientAuto > 0){
            $response2 = $this->validateConnectedAccountBalanceForRefund($company_id, $amountRefundClientAuto);
            // info('refund_topup RESPONSE 2', ['response2' => $response2]);
            $result2 = $response2['result'] ?? '';
            $message2 = $response2['message'] ?? '';
            if($result2 == 'error'){
                $lock->release();
                return response()->json(['result' => 'error', 'message' => $message2], 400);
            }
        }
        /* IF MANUAL BILLING IS F AND CLIENT REFUND AMOUNT > 0.5, CHECK WHETHER THE CONNECTED ACCOUNT AGENCY ACCOUNT BALANCE IS SUFFICIENT. */

        /* TRANSFER TO PREPAID DIRECT PAYMENT */
        $ip_user =  (isset($request->ip_user)) ? $request->ip_user : ''; 
        $timezone = (isset($request->timezone)) ? $request->timezone : '';
        $param_prepaid_direct_payment = [
            'company_id' => $company_id,
            'amount' => $amountRefundAgency,
            'amount_client_auto' => $amountRefundClientAuto,
            'amount_client_manual' => $amountRefundClientManual,
            'agency_manual_bill' => $agencyManualBill,
            'payment_type' => 'refund_campaign',
            'ip_user' => $ip_user,
            'timezone' => $timezone,
            'leadspeek_api_id' => $leadspeek_api_id,
            'remaining_balance_leads' => $remainingBalanceLeads
        ];
        $response3 = $this->processTransferToPrepaidDirectPayment($param_prepaid_direct_payment);
        // info('refund_topup RESPONSE 3', ['response3' => $response3]);
        $result3 = $response3['result'] ?? '';
        $message3 = $response3['message'] ?? '';
        $list_payment_intent_id = $response3['list_payment_intent_id'] ?? "";
        if($result3 == 'error'){
            $lock->release();
            return response()->json(['result' => 'error', 'message' => $message3], 400);
        }
        /* TRANSFER TO PREPAID DIRECT PAYMENT */

        /* GET CAMPAIGN */
        $campaign = LeadspeekUser::where('leadspeek_api_id', $leadspeek_api_id)->first();
        $stopContinual = $campaign->stopcontinual ?? 'F';
        $client_id = $campaign->user_id ?? null;
        $leadspeek_type = $campaign->leadspeek_type ?? '';
        /* GET CAMPAIGN */

        /* DONE TOPUP CAMPAIGN */
        // info('DONE TOPUP CAMPAIGN');
        Topup::where('leadspeek_api_id','=', $leadspeek_api_id)
            ->where('topup_status','<>','done')
            ->update(['topup_status' => 'done']);
        /* DONE TOPUP CAMPAIGN */

        /* REMAINING BALANCE */
        // info('REMAINING BALANCE');
        $idTopups = Topup::select(['id'])
            ->where('leadspeek_api_id','=', $leadspeek_api_id)
            ->where('topup_status','<>','done')
            ->get();
        $remainingBalanceLeadsCurrent = Topup::where('leadspeek_api_id','=', $leadspeek_api_id)
            ->where('topup_status','<>','done')
            ->sum('total_leads') - DB::table('leadspeek_reports')
            ->where('leadspeek_api_id','=',$leadspeek_api_id)
            ->whereIn('topup_id', $idTopups)
            ->count();
        if($remainingBalanceLeadsCurrent <= 0){
            $campaign->stopcontinual = 'F';
            $stopContinual = isset($campaign->stopcontinual) ? $campaign->stopcontinual : 'F';
            $campaign->save();
        }
        /* REMAINING BALANCE */

        /* USER LOG */
        $login_id = optional(auth()->user())->id;
        $ipAddress = ($request->header('Clientip') !== null)?$request->header('Clientip'):$request->ip();
        $descArray = [
            "agency manual bill: {$agencyManualBill}",
            "campaign id: {$leadspeek_api_id}",
            "campaign type: {$leadspeek_type}",
            "total wholesale agency: {$amountRefundAgency}",
            "total wholesale client auto: {$amountRefundClientAuto}",
            "total wholesale client manual: {$amountRefundClientManual}",
            "list payment intent id: ({$list_payment_intent_id})",
        ];
        $desc = implode(' | ', $descArray);
        $this->logUserAction($login_id, 'Campaign Refund', $desc, $ipAddress, $client_id);
        /* USER LOG */

        return response()->json(['result' => 'success', 'message' => 'Refund Campaign Was Successful.','remainingBalanceLeads' => $remainingBalanceLeadsCurrent, 'stopContinual' => $stopContinual]);
    }
    /* REFUND TOPUP */

    public function current_topup($leadspeek_api_id = "")
    {
        $current_topup = Topup::select('leadspeek_api_id','leadspeek_type','topupoptions','campaign_information_type_local')
                              ->where('leadspeek_api_id', $leadspeek_api_id)
                              ->where('topup_status', 'progress') 
                              ->orderBy('id', 'ASC') 
                              ->first();
                      
        return response()->json(['result'=>'success','current_topup'=>$current_topup]);
    }

    public function get_campaign($campaign_id){
        $_campaign_id = !empty($campaign_id) ? $campaign_id : '' ;

        $campaign = LeadspeekUser::select(
            'leadspeek_users.id',
            'leadspeek_users.campaign_name',
            'leadspeek_users.leadspeek_api_id',
            'leadspeek_users.user_id',
            'leadspeek_users.national_targeting',
            'leadspeek_users.leadspeek_locator_state',
            'leadspeek_users.leadspeek_locator_state_simplifi as leadspeek_locator_state_external',
            'leadspeek_users.leadspeek_locator_city_simplifi as leadspeek_locator_city_external',
            'leadspeek_users.leadspeek_locator_city',
            'leadspeek_users.leadspeek_locator_zip',
            'leadspeek_users.spreadsheet_id',
            'leadspeek_users.leadspeek_locator_keyword',
            'leadspeek_users.leadspeek_locator_keyword_contextual',
            'leadspeek_users.campaign_startdate',
            'leadspeek_users.campaign_enddate',
            'leadspeek_users.ori_campaign_enddate',
            'leadspeek_users.homeaddressenabled',
            'leadspeek_users.phoneenabled',
            'leadspeek_users.require_email',
            'leadspeek_users.applyreidentificationall',
            'leadspeek_users.disabled',
            'leadspeek_users.active_user',
            'leadspeek_users.admin_notify_to',
            'leadspeek_users.reidentification_type',
            'leadspeek_users.report_type',
            'companies.company_name', 
            'users.name', 
            'users.email',
            'users.phonenum'
        )
        ->leftJoin('companies', 'companies.id', '=', 'leadspeek_users.company_id') 
        ->leftJoin('users', 'users.id', '=', 'leadspeek_users.user_id') 
        ->where('leadspeek_users.campaign_link_id', $_campaign_id)
        ->first();
        

        if ($campaign) {
            return response()->json(['result' => 'success', 'data' => $campaign]);
        }else {
            return response()->json(['result' => 'failed', 'message' => 'campaign not found'], 404);
        }
    }
    public function getAllCampaigns(Request $request, $companyID = null, $companyGroupID = null)
    {
        if (empty($companyID) || empty($companyGroupID)) {
            return response()->json([
                'result' => 'error',
                'message' => 'Missing parameters: companyID and companyGroupID are required.'
            ], 400);
        }

        $perPage = $request->query('per_page', 10);
        $page = $request->query('page', 1);

        $query = LeadspeekUser::select([
            'id',
            'module_id',
            'company_id',
            'group_company_id',
            'leadspeek_api_id',
            'campaign_name',
            'leadspeek_type',
             'total_leads'
        ])
        ->where('archived', 'F')
        ->where('total_leads', '>', 0)
        ->where('company_id', $companyID)
        ->where('group_company_id', $companyGroupID)
        ->orderBy('leadspeek_type')
        ->orderBy('created_at', 'desc');

        // $campaigns = $query->paginate($perPage, ['*'], 'page', $page);
           $campaigns = $query->get();
        return response()->json([
            'result' => 'success',
            'campaigns' => $campaigns
        ]);
    }


    public function stop_continual_topup(Request $request)
    {
        $leadspeek_api_id = (isset($request->leadspeek_api_id)) ? $request->leadspeek_api_id : ''; 
        $lp_limit_freq = (isset($request->lp_limit_freq))?$request->lp_limit_freq:'';

        $leadspeekUser = LeadspeekUser::select(
                                        'leadspeek_users.id','leadspeek_users.stopcontinual','leadspeek_users.active','leadspeek_users.disabled','leadspeek_users.active_user','leadspeek_users.is_send_email_prepaid',
                                        'leadspeek_users.campaign_name','leadspeek_users.report_sent_to','users.id as user_id','users.company_parent','users.email','companies.company_name','leadspeek_users.leadspeek_type'
                                    )
                                    ->join('users','leadspeek_users.user_id','=','users.id')
                                    ->join('companies','users.company_id','=','companies.id')
                                    ->where('leadspeek_users.leadspeek_api_id', $leadspeek_api_id)
                                    ->first();

        if(empty($leadspeekUser))
        {
            return response()->json([
                'status' => false,
                'message' => 'your campaign doesnt exist'
            ]); 
        }    

        if($lp_limit_freq == 'day')
        {
            // cari data bedasarkan leadspeek_api_id, payment_type = 'continual top up' , active = 'T'.
            $data = Topup::where('leadspeek_api_id', $leadspeek_api_id)
                        ->where('topupoptions', 'continual')
                        ->where('topup_status', '<>', 'done')
                        ->get();

            if(count($data) === 0) 
            {
                return response()->json([
                    'status' => false,
                    'message' => 'your topup prepaid continual doesnt exist'
                ]);
            }
    
            foreach($data as $d) 
            {
                // ubah stop_continue menjadi 'T' 
                $d->stop_continue = 'T';
                $d->save();
            }

            $leadspeekUser->stopcontinual = 'T';
            $leadspeekUser->save();
        }
        else if($lp_limit_freq == 'month')
        {
            $campaignActive = (isset($leadspeekUser->active))?$leadspeekUser->active:'';
            $campaignDisabled = (isset($leadspeekUser->disabled))?$leadspeekUser->disabled:'';
            $campaignActiveUser = (isset($leadspeekUser->active_user))?$leadspeekUser->active_user:'';

            $idTopups = Topup::select(['id'])
                            ->where('leadspeek_api_id','=', $leadspeek_api_id)
                            ->where('topup_status','<>','done')
                            ->get();

            $remainingBalanceLeads = Topup::where('leadspeek_api_id','=', $leadspeek_api_id)
                                        ->where('topup_status','<>','done')
                                        ->sum('total_leads') - DB::table('leadspeek_reports')
                                        ->where('leadspeek_api_id','=',$leadspeek_api_id)
                                        ->whereIn('topup_id', $idTopups)
                                        ->count();

            if(($remainingBalanceLeads <= 0) && (($campaignActive == 'T' && $campaignDisabled == 'F' && $campaignActiveUser == 'T') || ($campaignActive == 'F' && $campaignDisabled == 'F' && $campaignActiveUser == 'T') || ($campaignActive == 'F' && $campaignDisabled == 'T' && $campaignActiveUser == 'T'))) // (remaining 0) && (status running or paused on run or paused)
            {
                /* UPDATE STOPCONTINUE IN LEADSPEEK USER TO F */
                $leadspeekUser->active = 'F';
                $leadspeekUser->disabled = 'T';
                $leadspeekUser->active_user = 'F';
                $leadspeekUser->is_send_email_prepaid = 'F';
                $leadspeekUser->stopcontinual = 'F';
                $leadspeekUser->save();
                /* UPDATE STOPCONTINUE IN LEADSPEEK USER TO F */

                /** UPDATE USER END DATE */
                $userID = (isset($leadspeekUser->user_id))?$leadspeekUser->user_id:'';
                $updateUser = User::find($userID);
                if(!empty($updateUser))
                {
                    $updateUser->lp_enddate = null;
                    $updateUser->lp_limit_startdate = null;
                    $updateUser->save();
                }
                /** UPDATE USER END DATE */

                /** SENT CLIENT EMAIL NOTIFICATION CAMPAIGN STOP*/
                $clientEmail =  explode(PHP_EOL, $leadspeekUser->report_sent_to);
                $company_name = $leadspeekUser->company_name;
                $campaign_name = $leadspeekUser->campaign_name;
                $company_parent = $leadspeekUser->company_parent;
                $custEmail = $leadspeekUser->email;
                
                $adminEmail = array();
                foreach($clientEmail as $value) 
                {
                    array_push($adminEmail,$value);
                }
                array_push($adminEmail,$custEmail);

                $AdminDefault = $this->get_default_admin($company_parent);
                $AdminDefaultEmail = (isset($AdminDefault[0]['email']))?$AdminDefault[0]['email']:'';
                $defaultdomain = $this->getDefaultDomainEmail($company_parent);

                $attachement = array();
                $details = [
                    'name'  => $company_name,
                    'campaignName' => $campaign_name,
                    'leadspeekapiID' => $leadspeek_api_id,
                    'defaultadmin' => $AdminDefaultEmail,
                ];
                $from = [
                    'address' => 'noreply@' . $defaultdomain,
                    'name' => 'Notification',
                    'replyto' => 'support@' . $defaultdomain,
                ];

                $this->send_email($adminEmail,$from,"Campaign has ended for {$company_name} - {$campaign_name} #{$leadspeek_api_id}",$details,$attachement,'emails.tryseracampaignended',$company_parent);
                /** SENT CLIENT EMAIL NOTIFICATION CAMPAIGN STOP*/
            }
            else // jika remaining balance diatas 0, maka stopcontinual menjadi 'T';
            {
                $data = Topup::where('leadspeek_api_id', $leadspeek_api_id)
                            ->where('topupoptions', 'continual')
                            ->where('topup_status', '<>', 'done')
                            ->get();
        
                if(count($data) === 0) 
                {
                    return response()->json([
                        'status' => false,
                        'message' => 'your topup prepaid continual doesnt exist'
                    ]);
                }

                foreach($data as $d) 
                {
                    // ubah stop_continue menjadi 'T' 
                    $d->stop_continue = 'T';
                    $d->save();
                }

                $leadspeekUser->stopcontinual = 'T';
                $leadspeekUser->save();
            }
        }

        // FOR USER LOGS
        $ipAddress = ($request->header('Clientip') !== null)?$request->header('Clientip'):$request->ip();
        $user_id = auth()->user()->id;
        $desc = "user id: {$user_id} has performed a Stop Continual Top-Up action | status: {$leadspeekUser->stopcontinual} | campaign type: {$leadspeekUser->leadspeek_type} |target user id: {$leadspeekUser->user_id} | leadspeek_api_id: {$leadspeek_api_id} | lp_limit_freq: {$lp_limit_freq}";

        $this->logUserAction($user_id, 'Stop Continual Top Up', $desc, $ipAddress, $leadspeekUser->user_id);
        // FOR USER LOGS

        return response()->json([
            'status' => true,
            'stop_continue' => $leadspeekUser->stopcontinual
        ]);
    }

    public function remaining_balance_leads($leadspeek_api_id)
    {        
        // dapatkan id topup yang topup_status nya tidak done
        $idTopups = Topup::select(['id'])
                         ->where('leadspeek_api_id','=', $leadspeek_api_id)
                         ->where('topup_status','<>','done')
                         ->get();

        // setelah dapatkan id topups, hitung remainingBalanceLeads
        // sum total_leads di tabel topup bedasarkan leadspeek_api_id nya dan topup_status nya bukan done - jumlah row di tabel LeadspeekReport bedasarkan leadspeek_api_id nya dan topup_id nya
        $remainingBalanceLeads = Topup::where('leadspeek_api_id','=', $leadspeek_api_id)
                                      ->where('topup_status','<>','done')
                                      ->sum('total_leads') - DB::table('leadspeek_reports')
                                      ->where('leadspeek_api_id','=',$leadspeek_api_id)
                                      ->whereIn('topup_id', $idTopups)
                                      ->count();

        return response()->json(['remainingBalanceLeads' => $remainingBalanceLeads]);
    }

    function generateRandomNumberString($length = 6) {
        $randomNumber = random_int(0, pow(10, $length) - 1);
        return str_pad($randomNumber, $length, '0', STR_PAD_LEFT);
    }

    public function suppressionprogress(Request $request) {
        $leadspeek_apiID = (isset($request->leadspeekID) && $request->leadspeekID != 'undefined')?$request->leadspeekID:'';
        $companyId = (isset($request->companyId) && $request->companyId != 'undefined')?$request->companyId:'';
        $leadspeekApiId = (isset($request->leadspeekApiId) && $request->leadspeekApiId != 'undefined')?$request->leadspeekApiId:'';
        $campaignType = (isset($request->campaignType) && $request->campaignType !== 'undefined')?$request->campaignType:'';

        $job = JobProgress::select(
                                    'id',
                                    'filename',
                                    'upload_at',
                                    'percentage',
                                    'status',
                                    'total_row',
                                    'current_row',
                                    // DB::raw("
                                    //     CASE 
                                    //         WHEN SUM(CASE WHEN status = 'queue' THEN 1 ELSE 0 END) = COUNT(*) THEN 'queue'
                                    //         WHEN SUM(CASE WHEN status = 'progress' THEN 1 ELSE 0 END) > 0 THEN 'progress'
                                    //         WHEN SUM(CASE WHEN status = 'done' THEN 1 ELSE 0 END) = COUNT(*) THEN 'done'
                                    //     END AS status
                                    // "),
                                    // DB::raw("
                                    //     CASE 
                                    //         WHEN SUM(CASE WHEN status = 'done' THEN 1 ELSE 0 END) = COUNT(*) THEN MAX(done_at) ELSE NULL
                                    //     END AS done_at
                                    // ")
                                )
                                ->where('lead_userid', $leadspeek_apiID)
                                ->where('company_id', $companyId)
                                ->where('leadspeek_api_id', $leadspeekApiId)
                                ->where('suppression_type', $campaignType)
                                //->groupBy('upload_at')
                                // ->havingRaw("
                                //     (status IN ('queue', 'progress')) OR 
                                //     (status = 'done' AND ABS(UNIX_TIMESTAMP() - done_at) < 30)
                                // ")
                                ->orderByDesc('created_at')
                                ->get()
                                ->toArray();
        
        // if (count($jobProgress) > 0) {
        //     if ($jobProgress[0]['status'] == 'done') {
        //         $removeProgress = JobProgress::find($jobProgress[0]['id']);
        //         $removeProgress->delete();
        //     }
        // }

        $jobProgress = array_values(array_filter($job, function ($item) {
            return $item['status'] == 'progress';
        }));
        $jobDone = array_values(array_filter($job, function ($item) {
            return $item['status'] == 'done';
        }));

        return response()->json([
            'campaignType' => $campaignType,
            'leadspeek_apiID' => $leadspeek_apiID,
            'companyId' => $companyId,
            'leadspeekApiId' => $leadspeekApiId,
            'jobProgress' => $jobProgress,
            'jobDone' => $jobDone
        ]);
    }

    public function suppressionupload(Request $request) {
        $leadspeek_apiID = $request->leadspeekID;
        $campaigntype = (isset($request->campaigntype))?$request->campaigntype:'campaign';

        $uploadTmp = 'tmp';
        
        $filenameOri = $request->file('suppressionfile')->getClientOriginalName();
        $filename = pathinfo($filenameOri, PATHINFO_FILENAME);
        $extension = $request->file('suppressionfile')->getClientOriginalExtension();

        $tmpfile = 'leadspeek_' . $leadspeek_apiID . '.' . $extension;
        $path = $request->file('suppressionfile')->storeAs($uploadTmp, $tmpfile,'local');    

        $file_url = storage_path('app/' . $path);

        $fp = fopen($file_url, 'r');

        /** GET LEADSPEEK USER */
        $leaduser = LeadspeekUser::select('leadspeek_users.id','users.company_id','users.company_parent','leadspeek_users.leadspeek_api_id')
                            ->join('users','leadspeek_users.user_id','=','users.id')
                            ->where('leadspeek_users.id','=',$leadspeek_apiID)
                            ->get();
        /** GET LEADSPEEK USER */

        if (count($leaduser) > 0) {
            /** REMOVE EXISTING SUPPRESSION LIST */
            // $removeExisting = SuppressionList::where('lead_userid','=',$leadspeek_apiID)
            //                                 ->where('company_id','=',$leaduser[0]['company_id'])
            //                                 ->where('leadspeek_api_id','=',$leaduser[0]['leadspeek_api_id'])
            //                                 ->where('suppression_type','=',$campaigntype)
            //                                 ->delete();
            /** REMOVE EXISTING SUPPRESSION LIST */
            while (($line = fgetcsv($fp)) !== FALSE) {
                $md5email =  md5($line[0]);
                $existingSuppression = SuppressionList::where('emailmd5', $md5email)
                ->where('leadspeek_api_id', '=',$leaduser[0]['leadspeek_api_id'])
                ->where('suppression_type', '=',$campaigntype)
                ->exists();
                if (!$existingSuppression) {
                    $createSuppression = SuppressionList::create([
                        'lead_userid' => $leadspeek_apiID,
                        'company_id' => $leaduser[0]['company_id'],
                        'leadspeek_api_id' =>  $leaduser[0]['leadspeek_api_id'],
                        'suppression_type' => $campaigntype,
                        'reidentification_date' => date('Y-m-d'),
                        'email' => $line[0],
                        'emailmd5' => $md5email,
                    ]);
                }
            }
            /** UPLOAD TO DO SPACES */
                // $path = Storage::disk('spaces')->putFileAs('suppressionlist',$file_url,$tmpfile);
                // $filedownload_url = Storage::disk('spaces')->url($path);
                // $filedownload_url = str_replace('digitaloceanspaces','cdn.digitaloceanspaces',$filedownload_url);    
            /** UPLOAD TO DO SPACES */

                // $usr = LeadspeekUser::find($leadspeek_apiID);
                // $usr->suppresionlist_filename = $tmpfile;
                // $usr->save();

                @unlink(storage_path('app/' . $uploadTmp . '/' . $tmpfile));

                $filedownload_url = "";

                return response()->json(array('result'=>'success','filename'=>$filedownload_url));
        }else{
            return response()->json(array('result'=>'failed','filename'=>'#'));
        }

    }

    public function suppressionpurge(Request $request){
        $paramID = $request->paramID;
        $campaignType = (isset($request->campaignType))?$request->campaignType:'';
        if ($campaignType == 'campaign') {
            $leaduser = LeadspeekUser::select('leadspeek_users.id','users.company_id','users.company_parent','leadspeek_users.leadspeek_api_id')
            ->join('users','leadspeek_users.user_id','=','users.id')
            ->where('leadspeek_users.id','=',$paramID)
            ->get();
            if (count($leaduser) > 0) {
                try {
                    $removeExisting = SuppressionList::where('lead_userid','=',$paramID)
                    ->where('company_id','=',$leaduser[0]['company_id'])
                    ->where('leadspeek_api_id','=',$leaduser[0]['leadspeek_api_id'])
                    ->where('suppression_type','=',$campaignType)
                    ->delete();            
                    
                    $leadspeek_api_id = $leaduser[0]['leadspeek_api_id'];
                    $removeJobProgress = JobProgress::where('leadspeek_api_id', $leadspeek_api_id)
                                                    ->where('suppression_type','campaign')
                                                    ->where('status', 'done')
                                                    ->delete();
                } catch (\Throwable $th) {
                 return response()->json(['result'=>'failed', 'msg' => $th->getMessage()]);
                }
                if ($removeExisting > 0) {
                    return response()->json(['result'=>'success','title' => 'Success!', 'msg' => 'purge successful!']);
                }else {
                    return response()->json(['result'=>'failed','title' => 'Data is empty', 'msg' => 'nothing to purge in this campaign']);
                }
            }
        }elseif ($campaignType == 'client') {
                try {
                $removeExisting = SuppressionList::where('company_id','=',$paramID)
                    ->where('lead_userid','=', 0)
                    ->where('suppression_type','=',$campaignType)
                    ->delete();            
                    
                    $removeJobProgress = JobProgress::where('company_id', $paramID)
                                                    ->where('suppression_type','client')
                                                    ->where('status', 'done')
                                                    ->delete();
                } catch (\Throwable $th) {
                    return response()->json(['result'=>'failed', 'msg' => $th->getMessage()]);
                }
                if ($removeExisting > 0) {
                    return response()->json(['result'=>'success','title' => 'Success!', 'msg' => 'purge successful!']);
                }else {
                    return response()->json(['result'=>'failed','title' => 'Data is empty', 'msg' => 'nothing to purge in this client']);
                }
        }
        return response()->json(['result'=>'', 'msg' => '']);
    }

    public function suppressionuploadTrysera(Request $request) {
        //$usr = LeadspeekUser::where('id','=',$request->leadspeekID)->get();
        $leadspeek_apiID = $request->leadspeekID;
        
        $uploadTmp = 'tmp';
        //$uploadFolder = 'users/suppressionlist';
        
        $filenameOri = $request->file('suppressionfile')->getClientOriginalName();
        $filename = pathinfo($filenameOri, PATHINFO_FILENAME);
        $extension = $request->file('suppressionfile')->getClientOriginalExtension();

        $tmpfile = 'leadspeek_' . $leadspeek_apiID . '.' . $extension;
        $path = $request->file('suppressionfile')->storeAs($uploadTmp, $tmpfile,'local');    
        //$file_url = Storage::disk('local')->url('app/' . $path);
        $file_url = storage_path('app/' . $path);

        $result_url = public_path() . '/assets/suppressionlist/' . $tmpfile;

        $fp = fopen($file_url, 'r');
        $content = array();
        
        while (($line = fgetcsv($fp)) !== FALSE) {
            $content[] = md5($line[0]);
        }

        fclose($fp);
        
        $fp = fopen($result_url,'w');

        $headerfield = array('md5');
        fputcsv($fp,$headerfield);

        for($i=0;$i<count($content);$i++) {
            fputcsv($fp,array($content[$i]));
        }
        fclose($fp);

        /** UPLOAD TO DO SPACES */
            //$file = Storage::disk('public_access')->path('/suppressionlist/' . $tmpfile);
            $path = Storage::disk('spaces')->putFile('suppressionlist/',$result_url,'public');
            //$path = $file->storeAs('suppressionlist/', $tmpfile,'spaces');    
            $image_url = Storage::disk('spaces')->url($path);
            $image_url = str_replace('digitaloceanspaces','cdn.digitaloceanspaces',$image_url);    
        /** UPLOAD TO DO SPACES */

        //$disk =  Storage::disk('spaces');
        //$path = $disk->putFileAs($uploadFolder, $result_url, $tmpfile);
        //unlink($result_url);
        //unlink($file_url);

        $usr = LeadspeekUser::find($request->leadspeekID);
        $usr->suppresionlist_filename = $tmpfile;
        $usr->save();

        /** UPLOAD TO TRYSERA AS SUPPRESSION LIST */
        $http = new \GuzzleHttp\Client;
        
        $appkey = config('services.trysera.api_id');
        $domain = config('services.trysera.domain');
        $campaignID = config('services.trysera.campaignid');
        
        $apiURL =  config('services.trysera.endpoint') . 'suppressionlist/http';
        
        try {
            $options = [
                'headers' => [
                    'Authorization' => 'Bearer ' . $appkey,
                ],
                'json' => [
                    "SuppressionList" => [
                        "File" => $image_url
                    ],
                    "SubClient" => [
                        "ID" => $leadspeek_apiID,
                    ]       
                ]
            ]; 
           
            $response = $http->post($apiURL,$options);
            
        }catch (\GuzzleHttp\Exception\BadResponseException $e) {
            if ($e->getCode() === 400) {
                return response()->json('Invalid Request. Please enter a username or a password.', $e->getCode());
            } else if ($e->getCode() === 401) {
                return response()->json('Your credentials are incorrect. Please try again', $e->getCode());
            }

            return response()->json('Something went wrong on the server.', $e->getCode());
        }
        
        unlink($result_url);
        unlink($file_url);
        /** UPLOAD TO TRYSERA AS SUPPRESSION LIST */
        
        return response()->json(array('result'=>'success','filename'=>env('APP_URL') . '/assets/suppressionlist/' . $tmpfile));
    }

    /** SIMPLI.FI API */
    
    private function _startPause_campaign($_organizationID,$_campaignsID,$status='') {
        $http = new \GuzzleHttp\Client;

        $appkey = "86bb19a0-43e6-0139-8548-06b4c2516bae";
        $usrkey = "63c52610-87cd-0139-b15f-06a60fe5fe77";
        $organizationID = $_organizationID;
        $campaignsID = explode(PHP_EOL, $_campaignsID);

        for($i=0;$i<count($campaignsID);$i++) {
            
           
            try {
                /** CHECK ACTIONS IF CAMPAIGN ALLOW TO RUN STATUS  */
                $apiURL = "https://app.simpli.fi/api/organizations/" . $organizationID . "/campaigns/" . $campaignsID[$i];
                $options = [
                    'headers' => [
                        'X-App-Key' => $appkey,        
                        'X-User-Key' => $usrkey,
                        'Content-Type' => 'application/json',
                    ],
                ];

                $response = $http->get($apiURL,$options);
                $result =  json_decode($response->getBody());
                
                for($j=0;$j<count($result->campaigns[0]->actions);$j++) {
                    if ($status == 'activate') {
                        if(isset($result->campaigns[0]->actions[$j]->activate)) {
                            //echo "activate";
                            try {
                                /** ACTIVATE THE CAMPAIGN */
                                $ActionApiURL = "https://app.simpli.fi/api/organizations/" . $organizationID . "/campaigns/" . $campaignsID[$i] . "/activate";
                                $ActionResponse = $http->post($ActionApiURL,$options);
                                /** ACTIVATE THE CAMPAIGN */
                            }catch (\GuzzleHttp\Exception\BadResponseException $e) {
                                $details = [
                                    'errormsg'  => 'Error when trying to Activate Campaign Organization ID : ' . $organizationID . ' Campaign ID :' . $campaignsID[$i] . ' (' . $e->getCode() . ')',
                                ];
                                $from = [
                                    'address' => 'noreply@exactmatchmarketing.com',
                                    'name' => 'Support',
                                    'replyto' => 'support@exactmatchmarketing.com',
                                ];
                                $this->send_email(array('serverlogs@sitesettingsapi.com'),$from,'Error Log for Activate Campaign ' . $e->getCode(),$details,array(),'emails.tryseramatcherrorlog','');
                            }
                        }
                    }else if ($status == 'pause') {
                        if(isset($result->campaigns[0]->actions[$j]->pause)) {
                            //echo "Pause";
                            try {
                                /** PAUSE THE CAMPAIGN */
                                $ActionApiURL = "https://app.simpli.fi/api/organizations/" . $organizationID . "/campaigns/" . $campaignsID[$i] . "/pause";
                                $ActionResponse = $http->post($ActionApiURL,$options);
                                /** PAUSE THE CAMPAIGN */
                            }catch (\GuzzleHttp\Exception\BadResponseException $e) {
                                $details = [
                                    'errormsg'  => 'Error when trying to Pause Campaign Organization ID : ' . $organizationID . ' Campaign ID :' . $campaignsID[$i] . ' (' . $e->getCode() . ')',
                                ];
                                $from = [
                                    'address' => 'noreply@exactmatchmarketing.com',
                                    'name' => 'Support',
                                    'replyto' => 'support@exactmatchmarketing.com',
                                ];
                                $this->send_email(array('serverlogs@sitesettingsapi.com'),$from,'Error Log for Pause Campaign ' . $e->getCode(),$details,array(),'emails.tryseramatcherrorlog','');
                            }
                        }
                    }else if ($status == 'stop') {
                        if(isset($result->campaigns[0]->actions[$j]->end)) {
                            //echo "Pause";
                            try {
                                /** PAUSE THE CAMPAIGN */
                                $ActionApiURL = "https://app.simpli.fi/api/organizations/" . $organizationID . "/campaigns/" . $campaignsID[$i] . "/end";
                                $ActionResponse = $http->post($ActionApiURL,$options);
                                /** PAUSE THE CAMPAIGN */
                            }catch (\GuzzleHttp\Exception\BadResponseException $e) {
                                $details = [
                                    'errormsg'  => 'Error when trying to Pause Campaign Organization ID : ' . $organizationID . ' Campaign ID :' . $campaignsID[$i] . ' (' . $e->getCode() . ')',
                                ];
                                $from = [
                                    'address' => 'noreply@exactmatchmarketing.com',
                                    'name' => 'Support',
                                    'replyto' => 'support@exactmatchmarketing.com',
                                ];
                                $this->send_email(array('serverlogs@sitesettingsapi.com'),$from,'Error Log for Pause Campaign ' . $e->getCode(),$details,array(),'emails.tryseramatcherrorlog','');
                            }
                        }
                    }
                    //echo $result->campaigns[0]->actions[$j]->activate[0];
                }
                
                //return response()->json(array("result"=>'success','message'=>'xx','param'=>$result));
                /** CHECK ACTIONS IF CAMPAIGN ALLOW TO RUN STATUS  */
            }catch (\GuzzleHttp\Exception\BadResponseException $e) {
                $details = [
                    'errormsg'  => 'Error when trying to get campaign information Organization ID : ' . $organizationID . ' Campaign ID :' . $campaignsID[$i] . '(' . $e->getCode() . ')',
                ];
                $from = [
                    'address' => 'noreply@exactmatchmarketing.com',
                    'name' => 'Support',
                    'replyto' => 'support@exactmatchmarketing.com',
                ];
                $this->send_email(array('serverlogs@sitesettingsapi.com'),$from,'Error Log for Start / Pause Get Campaign ' . $e->getCode(),$details,array(),'emails.tryseramatcherrorlog','');

                if ($e->getCode() === 400) {
                    return response()->json(array("result"=>'failed','message'=>'Invalid Request. Please enter a username or a password.'), $e->getCode());
                } else if ($e->getCode() === 401) {
                    return response()->json(array("result"=>'failed','message'=>'Your credentials are incorrect. Please try again'), $e->getCode());
                }

                return response()->json(array("result"=>'failed','message'=>'Something went wrong on the server.'), $e->getCode());
            }
            
        }
        
        
    }
    /** SIMPLI.FI API */

    public function removeclient(Request $request) 
    {
        date_default_timezone_set('America/Chicago');
        $leadspeekID = (isset($request->leadspeekID))?$request->leadspeekID:'';
        $companyID = (isset($request->companyID))?$request->companyID:'';
        $_status = (isset($request->status))?$request->status:'';
        $userID = (isset($request->userID))?$request->userID:'';

        if ($leadspeekID != '') 
        {
            $updateclient = $this->getclient($request);
            
            /* FORCE PAYMENT TERM TO PREPAID WHEN MODULE TYPE SIMPLIFI */
            if ($updateclient[0]->leadspeek_type == 'simplifi')
            {
                $updateclient[0]->paymentterm = 'Prepaid';
            }
            /* FORCE PAYMENT TERM TO PREPAID WHEN MODULE TYPE SIMPLIFI */

            $tryseraCustomID = $this->_moduleID . '_' . $companyID . '00' . $userID . '_' . date('His');
            $status = true;
            $campaignStatus = 'activate';

            if ($_status != '') 
            {
                if ($_status == 'F') 
                {
                    $status = false;
                    $campaignStatus = 'stop';
                }

                $leads = LeadspeekUser::find($leadspeekID);

                $organizationid = $leads->leadspeek_organizationid;
                $campaignsid = $leads->leadspeek_campaignsid;
                $start_billing_date = $leads->start_billing_date;

                /* VALIDATION IS CAMPAIGN ALREADY STOPPED */
                if($campaignStatus == 'stop' && $leads->active == 'F' && $leads->disabled == 'T' && $leads->active_user == 'F') 
                {
                    return response()->json(['result'=>'failed_campaign_already_stopped','message'=>'Sorry, you cannot stopped campaign because this campaign already stopped']);
                }
                /* VALIDATION IS CAMPAIGN ALREADY STOPPED */

                /** LOG ACTION */
                // $ipAddress = ($request->header('Clientip') !== null)?$request->header('Clientip'):$request->ip();
                // $loguser = $this->logUserAction($userID,'Campaign Stopped','Campaign Status : ' . $campaignStatus . ' | CampaignID :' . $leads->leadspeek_api_id,$ipAddress);
                /** LOG ACTION */

                /** TRYSERA ACTIVE DEACTIVE */
                if ($leads->trysera == 'T') 
                {
                    $this->update_leadspeek_api_client($updateclient[0]->leadspeek_api_id,$updateclient[0]->company_name,$tryseraCustomID,$status);
                }
                /** TRYSERA ACTIVE DEACTIVE */

                /** ACTIVATE CAMPAIGN SIMPLIFI */
                if ($leads->leadspeek_type == 'locator' && $organizationid != '' && $campaignsid != '') 
                {
                    $camp = $this->startPause_campaign($organizationid,$campaignsid,$campaignStatus);
                }
                /** ACTIVATE CAMPAIGN SIMPLIFI */

                /** STOP CAMPAIGN SIMPLIFI */
                if ($leads->leadspeek_type == 'simplifi' && $organizationid != '' && $campaignsid != '')
                {
                    $response = $this->SimpliFiService->update_status_campaign($organizationid, $campaignsid, $campaignStatus);
                    // info(__FUNCTION__, [
                    //     'response_update_status_campaign 1' => $response
                    // ]);
                }
                /** STOP CAMPAIGN SIMPLIFI */

                $clientEmail = "";
                $clientAdminNotify = "";
                $custEmail = "";
                $invoiceCreated = "";

                /** CHARGE ONE TIME CREATIVE / SET UP FEE WHEN STATUS ACTIVATED (ONE TIME) */
                if ($_status == 'T') 
                {
                    $usrInfo = User::select('customer_payment_id','customer_card_id','email')
                                   ->where('id','=',$userID)
                                   ->where('active','=','T')
                                   ->get();
                    if ($usrInfo[0]['paymentterm'] == 'One Time') 
                    {
                        $usrupdate = User::find($userID);
                        $usrupdate->lp_limit_startdate = date('Y-m-d H:i:s');
                        $usrupdate->save();
                    }
                    if(count($usrInfo) > 0 && $usrInfo[0]['customer_payment_id'] != '' && $usrInfo[0]['customer_card_id'] != '' && ($updateclient[0]->platformfee > 0 || $updateclient[0]->lp_min_cost_month > 0)) 
                    {
                        $totalFirstCharge = $updateclient[0]->platformfee + $updateclient[0]->lp_min_cost_month;
                        //$this->chargeClient($usrInfo[0]['customer_payment_id'],$usrInfo[0]['customer_card_id'],$usrInfo[0]['email'],$totalFirstCharge,$usrInfo[0]['platformfee'],$usrInfo[0]['lp_min_cost_month'],$updateclient);
                    }
                }
                else
                {
                    /** CHECK IF THERE END DATE ON WEEKLY OR MONTHLY PAYMENT TERM */
                    $clientPaymentTerm = $updateclient[0]->paymentterm;
                    $_company_id = $updateclient[0]->company_id;
                    $_user_id = $updateclient[0]->user_id;
                    $_leadspeek_api_id = $updateclient[0]->leadspeek_api_id;
                    $clientPaymentTerm = $updateclient[0]->paymentterm;
                    $minCostLeads = $updateclient[0]->lp_min_cost_month;
                    $_lp_user_id = $updateclient[0]->id;
                    $company_name = $updateclient[0]->company_name;
                    $clientEmail = explode(PHP_EOL, $updateclient[0]->report_sent_to);
                    $clientAdminNotify = explode(',',$updateclient[0]->admin_notify_to);
                    $custEmail = $updateclient[0]->email;    
                    
                    $clientLimitStartDate = ($updateclient[0]->lp_limit_startdate == null || $updateclient[0]->lp_limit_startdate == '0000-00-00 00:00:00')?'':$updateclient[0]->lp_limit_startdate;
                    $clientLimitEndDate = ($updateclient[0]->lp_enddate == null || $updateclient[0]->lp_enddate == '0000-00-00 00:00:00')?'':$updateclient[0]->lp_enddate;

                    $clientMaxperTerm = $updateclient[0]->lp_max_lead_month;
                    $clientCostPerLead = $updateclient[0]->cost_perlead;
                    
                    $custStripeID = $updateclient[0]->customer_payment_id;
                    $custStripeCardID = $updateclient[0]->customer_card_id;

                    if ($leads->leadspeek_type != 'predict' && $clientPaymentTerm != 'One Time' && $clientPaymentTerm != 'Prepaid' && $start_billing_date != '') 
                    {
                        $EndDate = date('YmdHis',strtotime($start_billing_date));
                        $platformFee = 0;
                        
                        if (date('YmdHis') >= $EndDate) 
                        {
                            /** CHECK IF NEED TO BILLED PLATFORM FEE OR NOT */
                            if ($clientPaymentTerm == 'Weekly') 
                            {
                                $date1=date_create(date('Ymd'));
                                $date2=date_create($EndDate);
                                $diff=date_diff($date1,$date2);
                                if ($diff->format("%a") >= 6) {
                                    $platformFee = $minCostLeads;
                                }
                            }
                            else if ($clientPaymentTerm == 'Monthly') 
                            {
                                if(date('m') > date('m',strtotime($start_billing_date))) 
                                {
                                    $platformFee = $minCostLeads;
                                }
                            }
                            /** CHECK IF NEED TO BILLED PLATFORM FEE OR NOT */

                            /** CHECK IF PLATFORM FEE NOT ZERO AND THEN PUT THE FORMULA */
                            if ($platformFee != 0 && $platformFee != '' && $clientPaymentTerm == 'Weekly') 
                            {
                                $clientWeeksContract = 52; //assume will be one year if end date is null or empty
                                $clientMonthRange = 12;

                                /** PUT FORMULA TO DEVIDED HOW MANY TUESDAY FROM PLATFORM FEE COST */
                                if ($platformFee != '' && $platformFee > 0) 
                                {
                                    if ($clientLimitEndDate != '') 
                                    {
                                        $d1 = new DateTime($clientLimitStartDate);
                                        $d2 = new DateTime($clientLimitEndDate);
                                        $interval = $d1->diff($d2);
                                        $clientMonthRange = $interval->m;

                                        $d1 = strtotime($clientLimitStartDate);
                                        $d2 = strtotime($clientLimitEndDate);
                                        $clientWeeksContract = $this->countDays(2, $d1, $d2);

                                        $platformFee = ($minCostLeads * $clientMonthRange) / $clientWeeksContract;
                                    }
                                    else
                                    {
                                        $platformFee = ($minCostLeads * $clientMonthRange) / $clientWeeksContract;
                                    }
                                }
                                /** PUT FORMULA TO DEVIDED HOW MANY TUESDAY FROM PLATFORM FEE COST */
                            }
                            /** CHECK IF PLATFORM FEE NOT ZERO AND THEN PUT THE FORMULA*/

                            /** UPDATE USER END DATE */
                            $updateUser = User::find($userID);
                            $updateUser->lp_enddate = null;
                            $updateUser->lp_limit_startdate = null;
                            $updateUser->save();
                            /** UPDATE USER END DATE */

                            $clientStartBilling = date('YmdHis',strtotime($start_billing_date));
                            $nextBillingDate = date('YmdHis');

                            /** HACKED ENDED CLIENT NO PLATFORM FEE */
                            $platformfee = 0;
                            /** HACKED ENDED CLIENT NO PLATFORM FEE */

                            /** CREATE INVOICE AND SENT IT */
                            $invoiceCreated = $this->createInvoice($_lp_user_id,$_company_id,$_user_id,$_leadspeek_api_id,$clientMaxperTerm,$clientCostPerLead,$platformFee,$clientPaymentTerm,$company_name,$clientEmail,$clientAdminNotify,$clientStartBilling,$nextBillingDate,$custStripeID,$custStripeCardID,$custEmail,$updateclient);
                            /** CREATE INVOICE AND SENT IT */

                        }
                    }
                    /** CHECK IF THERE END DATE ON WEEKLY OR MONTHLY PAYMENT TERM */
                }
                /** CHARGE ONE TIME CREATIVE / SET UP FEE WHEN STATUS ACTIVATED (ONE TIME) */
                
                $leads->active_user = $_status;
                $leads->active = $_status;
                $leads->disabled = ($_status == 'T')?'F':'T';
                $leads->last_balance_leads = null; // reset last_balance_leads
                $leads->save();

                /** SENT CLIENT EMAIL NOTIFICATION CAMPAIGN STOP*/
                $clientEmail =  explode(PHP_EOL, $updateclient[0]->report_sent_to);
                $company_name = $updateclient[0]->company_name;
                $custEmail = $updateclient[0]->email;
                
                $adminEmail = array();
                foreach($clientEmail as $value) 
                {
                    array_push($adminEmail,$value);
                }
                array_push($adminEmail,$custEmail);

                $AdminDefault = $this->get_default_admin($updateclient[0]->company_parent);
                $AdminDefaultEmail = (isset($AdminDefault[0]['email']))?$AdminDefault[0]['email']:'';

                $defaultdomain = $this->getDefaultDomainEmail($updateclient[0]->company_parent);

                $details = [
                    'name'  => $company_name,
                    'campaignName' => $updateclient[0]->campaign_name,
                    'leadspeekapiID' => $_leadspeek_api_id,
                    'defaultadmin' => $AdminDefaultEmail,
                ];
                $attachement = array();
                
                $from = [
                    'address' => 'noreply@' . $defaultdomain,
                    'name' => 'Notification',
                    'replyto' => 'support@' . $defaultdomain,
                ];
                
                $this->send_email($adminEmail,$from,'Campaign has ended for ' . $company_name . ' - ' . $updateclient[0]->campaign_name . ' #' . $_leadspeek_api_id,$details,$attachement,'emails.tryseracampaignended',$updateclient[0]->company_parent,true);                
                /** SENT CLIENT EMAIL NOTIFICATION CAMPAIGN STOP*/

                if ($leads->leadspeek_type != 'predict' && $clientPaymentTerm == 'Prepaid') 
                {
                    /* UPDATE STOPCONTINUE IN TOPUP TO F */
                    $data = Topup::where('lp_user_id', $leadspeekID)
                                 ->where('topupoptions', 'continual')
                                 ->where('topup_status', '<>', 'done')
                                 ->get();

                    foreach($data as $d) 
                    {
                        // ubah stop_continue menjadi 'T' 
                        $d->stop_continue = 'F';
                        $d->save();
                    }
                    /* UPDATE STOPCONTINUE IN TOPUP TO F */

                    /* UPDATE STOPCONTINUE IN LEADSPEEK USER TO F */
                    $leadspeekUser = LeadspeekUser::where('id', $leadspeekID)->first();
                    $leadspeekUser->stopcontinual = 'F';
                    $leadspeekUser->is_send_email_prepaid = 'F';
                    $leadspeekUser->save();
                    /* UPDATE STOPCONTINUE IN LEADSPEEK USER TO F */

                    /** UPDATE USER END DATE */
                    $updateUser = User::find($userID);
                    $updateUser->lp_enddate = null;
                    $updateUser->lp_limit_startdate = null;
                    $updateUser->save();
                    /** UPDATE USER END DATE */
                }

                return response()->json(array("result"=>'success','message'=>'','status_invoice' => $invoiceCreated));
                //$removeLeadsPeek = $this->remove_leadspeek_api_client($usr[0]->leadspeek_api_id);
                //LeadspeekUser::find($LeadspeekID)->delete();
            }
        }
    }

    private function _createInvoice($_lp_user_id,$_company_id,$_user_id,$_leadspeek_api_id,$minLeads,$costLeads,$minCostLeads,$clientPaymentTerm,$companyName,$reportSentTo,$adminnotify,$startBillingDate,$endBillingDate,$custStripeID,$custStripeCardID,$custEmail,$usrInfo) {
        date_default_timezone_set('America/Chicago');
        $todayDate = date('Y-m-d H:i:s');
        $invoiceNum = date('Ymd') . '-' . $_lp_user_id;
        $exceedLeads = 0;
        $totalAmount = 0;
        $costPriceLeads = 0;
        $platform_costPriceLeads = 0;

        /** CHECK IF THIS COMPANY USERDOWNLINE OR CLIENT */
        $accConID = '';
        if ($usrInfo[0]->company_parent != '') {
            $accConID = $this->check_connected_account($usrInfo[0]->company_parent,$usrInfo[0]->company_root_id);
        }
        /** CHECK IF THIS COMPANY USERDOWNLINE OR CLIENT */

        /** CHECK IF USER DATA STILL ON PLATFORM */
        $validuser = true;
        $user[0]['customer_payment_id'] = (isset($usrInfo[0]->customer_payment_id))?$usrInfo[0]->customer_payment_id:'';
        $user[0]['company_id'] = (isset($usrInfo[0]->company_id))?$usrInfo[0]->company_id:'';
        $user[0]['id'] = (isset($usrInfo[0]->user_id))?$usrInfo[0]->user_id:'';
        $user[0]['company_root_id'] = $usrInfo[0]->company_root_id;

        $chkStripeUser = $this->check_stripe_customer_platform_exist($user,$accConID);
        $chkResultUser = json_decode($chkStripeUser);
        if ($chkResultUser->result == 'success') {
            $validuser = true;
            $custStripeID = $chkResultUser->custStripeID;
            $custStripeCardID = $chkResultUser->CardID;
        }else{
            $validuser = false;
        }
        /** CHECK IF USER DATA STILL ON PLATFORM */


        /** FIND IF THERE IS ANY EXCEED LEADS */
        $reportCat = LeadspeekReport::select(DB::raw("COUNT(*) as total"),DB::raw("SUM(price_lead) as costleadprice"),DB::raw("SUM(platform_price_lead) as platform_costleadprice"))
                        ->where('lp_user_id','=',$_lp_user_id)
                        ->where('company_id','=',$_company_id)
                        ->where('user_id','=',$_user_id)
                        ->where('leadspeek_api_id','=',$_leadspeek_api_id)
                        ->where('active','=','T')
                        //->whereBetween(DB::raw('DATE_FORMAT(clickdate,"%Y-%m-%d")'),[$startBillingDate,$endBillingDate])
                        ->where(DB::raw('DATE_FORMAT(clickdate,"%Y%m%d%H%i%s")'),'>=',$startBillingDate)
                        ->where(DB::raw('DATE_FORMAT(clickdate,"%Y%m%d%H%i%s")'),'<=',$endBillingDate)
                        ->get();
        if(count($reportCat) > 0) {
            $ongoingLeads = $reportCat[0]['total'];
            $costPriceLeads = $reportCat[0]['costleadprice'];
            $platform_costPriceLeads = $reportCat[0]['platform_costleadprice'];
        }

        /*if(($ongoingLeads > $minLeads) && $minLeads > 0) {
            $exceedLeads = $ongoingLeads - $minLeads;
        }*/
        /** FIND IF THERE IS ANY EXCEED LEADS */

        //$totalAmount = ($costLeads * $exceedLeads) + $minCostLeads;
        /** IF JUST WILL CHARGE PER LEAD */
        if ($clientPaymentTerm != 'One Time' && ($costLeads != '0' || trim($costLeads) != '')) {
            //$totalAmount =  ($costLeads * $ongoingLeads) + $minCostLeads;
            $totalAmount = $costPriceLeads + $minCostLeads;
        }else if($clientPaymentTerm == 'One Time') {
            $totalAmount = $minCostLeads;
        }
        /** IF JUST WILL CHARGE PER LEAD */

        /** CHARGE WITH STRIPE */
        $paymentintentID = '';
        $errorstripe = '';
        $platform_errorstripe = '';
        $statusPayment = 'pending';
        $cardlast = '';
        $platform_paymentintentID = '';
        $sr_id = 0;
        $ae_id = 0;
        $sales_fee = 0;
        $platformfee_charge = false;

        $totalAmount = number_format($totalAmount,2,'.','');
        $minCostLeads = number_format($minCostLeads,2,'.','');

        if(trim($custStripeID) != '' && trim($custStripeCardID) != '' && $validuser) { 
            /** GET STRIPE KEY */
            $stripeseckey = config('services.stripe.secret');
            $stripepublish = $this->getcompanysetting($usrInfo[0]->company_root_id,'rootstripe');
            if ($stripepublish != '') {
                $stripeseckey = (isset($stripepublish->secretkey))?$stripepublish->secretkey:"";
            }
            /** GET STRIPE KEY */

            $stripe = new StripeClient([
                'api_key' => $stripeseckey,
                'stripe_version' => '2020-08-27'
            ]);

            /** GET PLATFORM MARGIN */
            $platformMargin = $this->getcompanysetting($usrInfo[0]->company_parent,'costagency');
            $platform_LeadspeekCostperlead = 0;
            $platform_LeadspeekMinCostMonth = 0;
            $platform_LeadspeekPlatformFee = 0;
            $platformfee_ori = 0;
            $platformfee = 0;

            $paymentterm = trim($usrInfo[0]->paymentterm);
            $paymentterm = str_replace(' ','',$paymentterm);
            if ($platformMargin != '') {
                if ($usrInfo[0]->leadspeek_type == "local") {
                    $platform_LeadspeekCostperlead = (isset($platformMargin->local->$paymentterm->LeadspeekCostperlead))?$platformMargin->local->$paymentterm->LeadspeekCostperlead:0;
                    $platform_LeadspeekMinCostMonth = (isset($platformMargin->local->$paymentterm->LeadspeekMinCostMonth))?$platformMargin->local->$paymentterm->LeadspeekMinCostMonth:0;
                    $platform_LeadspeekPlatformFee = (isset($platformMargin->local->$paymentterm->LeadspeekPlatformFee))?$platformMargin->local->$paymentterm->LeadspeekPlatformFee:0;
                }else if ($usrInfo[0]->leadspeek_type == "locator") {
                    $platform_LeadspeekCostperlead = (isset($platformMargin->locator->$paymentterm->LocatorCostperlead))?$platformMargin->locator->$paymentterm->LocatorCostperlead:0;
                    $platform_LeadspeekMinCostMonth = (isset($platformMargin->locator->$paymentterm->LocatorMinCostMonth))?$platformMargin->locator->$paymentterm->LocatorMinCostMonth:0;
                    $platform_LeadspeekPlatformFee = (isset($platformMargin->locator->$paymentterm->LocatorPlatformFee))?$platformMargin->locator->$paymentterm->LocatorPlatformFee:0;
                }
                /** HACKED BECAUSE CAMPAIGN ENDED NO NEED TO CHARGE PLATFORM FEE */
                $platform_LeadspeekMinCostMonth = 0;
            }
            /** GET PLATFORM MARGIN */

            if ($clientPaymentTerm != 'One Time' && ($costLeads != '0' || trim($costLeads) != '')) {
                //$platformfee =  ($platform_LeadspeekCostperlead * $ongoingLeads) + $platform_LeadspeekMinCostMonth;
                $platformfee =  $platform_costPriceLeads + $platform_LeadspeekMinCostMonth;
                /** HACKED PLATFORMFEE FOR ONLY CAMPAIGN ID #642466 */
                if ($_leadspeek_api_id == '642466') {
                    $platform_LeadspeekCostperlead = 0.15;
                    $platformfee = (0.15 * $ongoingLeads) + $platform_LeadspeekMinCostMonth;
                }
                /** HACKED PLATFORMFEE FOR ONLY CAMPAIGN ID #642466 */
            }else if($clientPaymentTerm == 'One Time') {
                $platformfee = $platform_LeadspeekMinCostMonth;
            }
            
            $platformfee = number_format($platformfee,2,'.','');
            $platformfee_ori = $platformfee;
            
            $defaultInvoice = '#' . $invoiceNum . '-' . $companyName . ' #' . $_leadspeek_api_id . '(ended)';

            /** CHECK IF TOTAL AMOUNT IS SMALLER THAN PLATFORM FEE */
            //if (($totalAmount < $platformfee) && $platformfee > 0) {
            // if ($platformfee >= 0.5) {
            //     $agencystripe = $this->check_agency_stripeinfo($usrInfo[0]->company_parent,$platformfee,$_leadspeek_api_id,'Agency ' . $defaultInvoice);
            //     $agencystriperesult = json_decode($agencystripe);
            //     $platformfee_charge = true;

            //     if ($agencystriperesult->result == 'success') {
            //         $platform_paymentintentID = $agencystriperesult->payment_intentID;
            //         $sr_id = $agencystriperesult->srID;
            //         $ae_id = $agencystriperesult->aeID;
            //         $sales_fee = $agencystriperesult->salesfee;
            //         $platformfee = 0;
            //         $platform_errorstripe = '';
            //     }else{
            //         $platform_paymentintentID = $agencystriperesult->payment_intentID;
            //         $platform_errorstripe .= $agencystriperesult->error;
            //     }
            // }
            /** CHECK IF TOTAL AMOUNT IS SMALLER THAN PLATFORM FEE */

            /** CREATE ONE TIME PAYMENT USING PAYMENT INTENT */
            if ($totalAmount < 0.5 || $totalAmount <= 0) {
                $paymentintentID = '';
                $statusPayment = 'paid';
                $platformfee_charge = false;
            }else{
                try {
                    $chargeAmount = $totalAmount * 100;
                    $payment_intent =  $stripe->paymentIntents->create([
                        'payment_method_types' => ['card'],
                        'customer' => trim($custStripeID),
                        'amount' => $chargeAmount,
                        'currency' => 'usd',
                        'receipt_email' => $custEmail,
                        'payment_method' => $custStripeCardID,
                        'confirm' => true,
                        'application_fee_amount' => ($platformfee * 100),
                        'description' => $defaultInvoice,
                    ],['stripe_account' => $accConID]);

                    $paymentintentID = $payment_intent->id;
                    $statusPayment = 'paid';
                    $errorstripe = '';
                    $platformfee_charge = true;

                    /** TRANSFER SALES COMMISSION IF ANY */
                    $salesfee = $this->transfer_commission_sales($usrInfo[0]->company_parent,$platformfee,$_leadspeek_api_id,$startBillingDate,$endBillingDate,$stripeseckey);
                    /** TRANSFER SALES COMMISSION IF ANY */
                }catch (RateLimitException $e) {
                    $statusPayment = 'failed';
                    $platformfee_charge = false;
                    // Too many requests made to the API too quickly
                    $errorstripe = $e->getHttpStatus() . '|' . $e->getError()->type . '|' . $e->getError()->code . '|' . $e->getError()->param . '|' . $e->getError()->message;
                } catch (InvalidRequestException $e) {
                    $statusPayment = 'failed';
                    $platformfee_charge = false;
                    // Invalid parameters were supplied to Stripe's API
                    $errorstripe = $e->getHttpStatus() . '|' . $e->getError()->type . '|' . $e->getError()->code . '|' . $e->getError()->param . '|' . $e->getError()->message;
                } catch (ExceptionAuthenticationException $e) {
                    $statusPayment = 'failed';
                    $platformfee_charge = false;
                    // Authentication with Stripe's API failed
                    // (maybe you changed API keys recently)
                    $errorstripe = $e->getHttpStatus() . '|' . $e->getError()->type . '|' . $e->getError()->code . '|' . $e->getError()->param . '|' . $e->getError()->message;
                } catch (ApiConnectionException $e) {
                    $statusPayment = 'failed';
                    $platformfee_charge = false;
                    // Network communication with Stripe failed
                    $errorstripe = $e->getHttpStatus() . '|' . $e->getError()->type . '|' . $e->getError()->code . '|' . $e->getError()->param . '|' . $e->getError()->message;
                } catch (ApiErrorException $e) {
                    $statusPayment = 'failed';
                    $platformfee_charge = false;
                    // Display a very generic error to the user, and maybe send
                    // yourself an email
                    $errorstripe = $e->getHttpStatus() . '|' . $e->getError()->type . '|' . $e->getError()->code . '|' . $e->getError()->param . '|' . $e->getError()->message;
                } catch (Exception $e) {
                    $statusPayment = 'failed';
                    $platformfee_charge = false;
                    // Something else happened, completely unrelated to Stripe
                    $errorstripe = 'error not stripe things :' . $e->getMessage();
                }
            }

            $cardinfo = $stripe->customers->retrieveSource(trim($custStripeID),trim($custStripeCardID),[],['stripe_account' => $accConID]);
            $cardlast = $cardinfo->last4;
            
            /** CHECK IF FAILED CHARGE CLIENT WE STILL CHARGE THE AGENCY */
            //if ($statusPayment == 'failed' && $platformfee_charge == false && $platformfee >= 0.5) {
            if ($platformfee_charge == false && $platformfee >= 0.5) {
                $agencystripe = $this->check_agency_stripeinfo($usrInfo[0]->company_parent,$platformfee,$_leadspeek_api_id,'Agency ' . $defaultInvoice,$startBillingDate,$endBillingDate);
                $agencystriperesult = json_decode($agencystripe);

                if ($agencystriperesult->result == 'success') {
                    $platform_paymentintentID = $agencystriperesult->payment_intentID;
                    $sr_id = $agencystriperesult->srID;
                    $ae_id = $agencystriperesult->aeID;
                    $sales_fee = $agencystriperesult->salesfee;
                    $platformfee = 0;
                    $platform_errorstripe = '';
                }else{
                    $platform_paymentintentID = $agencystriperesult->payment_intentID;
                    $platform_errorstripe .= $agencystriperesult->error;
                }
            }
            /** CHECK IF FAILED CHARGE CLIENT WE STILL CHARGE THE AGENCY */
            
        }
        /** CHARGE WITH STRIPE */

        if (trim($sr_id) == "") {
            $sr_id = 0;
        }
        if (trim($ae_id) == "") {
            $ae_id = 0;
        }

        $statusClientPayment = $statusPayment;
        
        $invoiceCreated = LeadspeekInvoice::create([
            'company_id' => $_company_id,
            'user_id' => $_user_id,
            'leadspeek_api_id' => $_leadspeek_api_id,
            'invoice_number' => '',
            'payment_term' => $clientPaymentTerm,
            'onetimefee' => 0,
            'platform_onetimefee' => $platform_LeadspeekPlatformFee,
            'min_leads' => $minLeads,
            'exceed_leads' => $exceedLeads,
            'total_leads' => $ongoingLeads,
            'min_cost' => $minCostLeads,
            'platform_min_cost' => $platform_LeadspeekMinCostMonth,
            'cost_leads' => $costLeads,
            'platform_cost_leads' => $platform_LeadspeekCostperlead,
            'total_amount' => $totalAmount,
            'platform_total_amount' => $platformfee_ori,
            'status' => $statusPayment,
            'customer_payment_id' => $paymentintentID,
            'platform_customer_payment_id' => $platform_paymentintentID,
            'error_payment' => $errorstripe,
            'platform_error_payment' => $platform_errorstripe,
            'invoice_date' => $todayDate,
            'invoice_start' => date('Y-m-d H:i:s',strtotime($startBillingDate)),
            'invoice_end' => date('Y-m-d H:i:s',strtotime($endBillingDate)),
            'sent_to' => json_encode($reportSentTo),
            'sr_id' => $sr_id,
            'sr_fee' => $sales_fee,
            'ae_id' => $ae_id,
            'ae_fee' => $sales_fee,
            'active' => 'T',
        ]);
        $invoiceID = $invoiceCreated->id;

        $invoice = LeadspeekInvoice::find($invoiceID);
        $invoice->invoice_number = $invoiceNum . '-' . $invoiceID;
        $invoice->save();

        $lpupdate = LeadspeekUser::find($_lp_user_id);
        $lpupdate->ongoing_leads = 0;
        $lpupdate->start_billing_date = $todayDate;
        $lpupdate->lifetime_cost = ($lpupdate->lifetime_cost + $totalAmount);
        $lpupdate->save();
        
        /** FIND ADMIN EMAIL */
        $tmp = User::select('email')->whereIn('id', $adminnotify)->get();
        $adminEmail = array();
        foreach($tmp as $ad) {
            array_push($adminEmail,$ad['email']);
        }
        //array_push($adminEmail,'serverlogs@sitesettingsapi.com');
        /** FIND ADMIN EMAIL */
        
        if ($statusPayment == 'paid') {
            $statusPayment = "Customer's Credit Card Successfully Charged ";
        }else if ($statusPayment == 'failed') {
            $statusPayment = "Customer's Credit Card Failed";
        }else{
            $statusPayment = "Customer's Credit Card Successfully Charged ";
        }

        if ($totalAmount == '0.00' || $totalAmount == '0') {
            $statusPayment = "Customer's Credit Card Successfully Charged";
        }

        if ($platformfee_ori != '0.00' || $platformfee_ori != '0') {
            if ($statusClientPayment == 'failed') {
                $statusPayment .= " and Agency's Card Charged For Overage";
            }
        }

        $platform_LeadspeekCostperlead = number_format($platform_LeadspeekCostperlead,2,'.','');
        
        $agencyNet = "";
        if ($totalAmount > $platformfee_ori) {
            $agencyNet = $totalAmount - $platformfee_ori;
            $agencyNet = number_format($agencyNet,2,'.','');
        }
        
        $AdminDefault = $this->get_default_admin($usrInfo[0]->company_root_id);
        $AdminDefaultEmail = (isset($AdminDefault[0]['email']))?$AdminDefault[0]['email']:'';
        $rootCompanyInfo = $this->getCompanyRootInfo($usrInfo[0]->company_root_id);

        $defaultdomain = $this->getDefaultDomainEmail($usrInfo[0]->company_root_id);

        $details = [
            'name'  => $companyName,
            'invoiceNumber' => $invoiceNum . '-' . $invoiceID,
            'min_leads' => $minLeads,
            //'exceed_leads' => $exceedLeads,
            'total_leads' => $ongoingLeads,
            'min_cost' => $minCostLeads,
            'platform_min_cost' => $platform_LeadspeekMinCostMonth,
            'cost_leads' => $costLeads,
            'platform_cost_leads' => $platform_LeadspeekCostperlead,
            'total_amount' => $totalAmount,
            'platform_total_amount' => $platformfee_ori,
            //'invoiceDate' => date('m-d-Y',strtotime($todayDate)),
            'startBillingDate' => date('m-d-Y H:i:s',strtotime($startBillingDate)),
            'endBillingDate' =>  date('m-d-Y H:i:s',strtotime($endBillingDate)),
            'invoiceStatus' => $statusPayment,
            'cardlast' => trim($cardlast),
            'leadspeekapiid' => $_leadspeek_api_id,
            'paymentterm' => $clientPaymentTerm,
            //'onetimefee' => '0',
            'invoicetype' => 'agency',
            'agencyname' => $rootCompanyInfo['company_name'],
            'defaultadmin' => $AdminDefaultEmail,
            'agencyNet' => $agencyNet,
        ];
        $attachement = array();
        
        $from = [
            'address' => 'noreply@' . $defaultdomain,
            'name' => 'Invoice',
            'replyto' => 'support@' . $defaultdomain,
        ];

        $subjectFailed = "";
        if ($statusClientPayment == 'failed') {
            $subjectFailed = "Failed Payment - ";
        }

        $this->send_email($adminEmail,$from,$subjectFailed . 'Invoice for ' . $companyName . ' #' . $_leadspeek_api_id . ' (' . date('m-d-Y',strtotime($todayDate)) . ' Ended Campaign)',$details,$attachement,'emails.tryseramatchlistinvoice',$usrInfo[0]->company_parent);

        /** UPDATE CLIENT STRIPE PAYMENT DESCRIPTION */
        if ($paymentintentID != "") {
            $updatePaymentClientDesc =  $stripe->paymentIntents->update($paymentintentID,
                [
                    'description' => '#' . $invoiceNum . '-' . $invoiceID .  '-' . $companyName . ' #' . $_leadspeek_api_id . '(ended)',
                ],['stripe_account' => $accConID]);
            /** UPDATE CLIENT STRIPE PAYMENT DESCRIPTION */
        }
        if ($platform_paymentintentID != "") {
            /** UPDATE AGENCY STRIPE PAYMENT DESCRIPTION */
            $updatePaymentClientDesc =  $stripe->paymentIntents->update($platform_paymentintentID,
                [
                    'description' => 'Agency #' . $invoiceNum . '-' . $invoiceID .  '-' . $companyName . ' #' . $_leadspeek_api_id . '(ended)',
                ]);
            /** UPDATE AGENCY STRIPE PAYMENT DESCRIPTION */
        }

    }

    public function chargeCampaignSimplifiByRemainingDays(Request $request)
    {
        // info(__FUNCTION__, ['all' => $request->all()]);
        date_default_timezone_set('America/Chicago');
        
        /* VARIABLE */
        $companyID = $request->companyID ?? null; // variable ini digunakan di function getClient
        $leadspeekID = $request->leadspeekID ?? null; // variable ini digunakan di function getClient
        $userID = $request->userID ?? null;
        $leadspeekMaxBid = $request->leadspeekMaxBid ?? 0;
        $leadspeekMonthlyBudget = $request->leadspeekMonthlyBudget ?? 0;
        $leadspeekDailyBudget = $request->leadspeekDailyBudget ?? 0;
        $leadspeekGoalValue = $request->leadspeekGoalValue ?? 0;
        $leadspeekGoalType = $request->leadspeekGoalType ?? "";
        $leadspeekAgencyMarkup = $request->leadspeekAgencyMarkup ?? 0;
        $leadspeekRemainingDays = $request->leadspeekRemainingDays ?? 0;
        $leadspeekTotalSpend = $request->leadspeekTotalSpend ?? 0;
        $costClientByRemainingDays = $request->costClientByRemainingDays ?? 0;
        $costAgencyByRemainingDays = $request->costAgencyByRemainingDays ?? 0;
        /* VARIABLE */

        /* GET CAMPAIGN */
        $updateclient = $this->getclient($request);
        $leadspeek_active = isset($updateclient[0]->active) ? $updateclient[0]->active : '';
        $leadspeek_disabled = isset($updateclient[0]->disabled) ? $updateclient[0]->disabled : '';
        $leadspeek_active_user = isset($updateclient[0]->active_user) ? $updateclient[0]->active_user : '';
        $isCampaignRunning = ($leadspeek_active == 'T' && $leadspeek_disabled == 'F' && $leadspeek_active_user == 'T') || 
                             ($leadspeek_active == 'F' && $leadspeek_disabled == 'F' && $leadspeek_active_user == 'T');
        /* GET CAMPAIGN */

        /* GET CLIENT */
        $usrInfo = User::select('id','customer_payment_id','customer_card_id','email','company_parent','company_id')
                       ->where('id','=',$userID)
                       ->where('active','=','T')
                       ->get();
        $customer_payment_id = isset($usrInfo[0]['customer_payment_id']) ? $usrInfo[0]['customer_payment_id'] : '';
        $customer_card_id = isset($usrInfo[0]['customer_card_id']) ? $usrInfo[0]['customer_card_id'] : '';
        $email = isset($usrInfo[0]['email']) ? $usrInfo[0]['email'] : '';
        /* GET CLIENT */

        /* GET AGENCY MANUAL BILL  */
        $agencyManualBill = Company::where('id', $updateclient[0]->company_parent)->value('manual_bill');
        $agencyManualBill = ($agencyManualBill == 'T');
        /* GET AGENCY MANUAL BILL  */

        /* PROCESS CHARGE */
        // info(['costClientByRemainingDays' => $costClientByRemainingDays,'costAgencyByRemainingDays' => $costAgencyByRemainingDays,'isCampaignRunning' => $isCampaignRunning,'customer_payment_id' => $customer_payment_id,'customer_card_id' => $customer_card_id,'agencyManualBill' => $agencyManualBill,]);
        if( 
            ($costClientByRemainingDays > 0 && $costAgencyByRemainingDays > 0 && $isCampaignRunning) && // jika costclient dan costagency lebih besar dari 0 dan status campaign running
            ((!empty($customer_payment_id) && !empty($customer_card_id)) || ($agencyManualBill)) // jika (customer stripe client tersedia) || (agency manual bill)
        ) 
        {
            $totalFirstCharge = $costClientByRemainingDays;
            $dataSimplifi = [
                'chargeType' => 'increase_budget',
                'leadspeekMaxBid' => $leadspeekMaxBid,
                'leadspeekMonthlyBudget' => $leadspeekMonthlyBudget,
                'leadspeekDailyBudget' => $leadspeekDailyBudget,
                'leadspeekGoalValue' => $leadspeekGoalValue,
                'leadspeekGoalType' => $leadspeekGoalType,
                'leadspeekAgencyMarkup' => $leadspeekAgencyMarkup,
                'leadspeekRemainingDays' => $leadspeekRemainingDays,
                'leadspeekTotalSpend' => $leadspeekTotalSpend,
                'costAgencyByRemainingDays' => $costAgencyByRemainingDays,
                'costClientByRemainingDays' => $costClientByRemainingDays,
            ];
            // info(__FUNCTION__, ['dataSimplifi' => $dataSimplifi]);

            // if cost client zero
            if ($totalFirstCharge <= 0)
                return response()->json(['result'=>'failed','msg'=>'cost client must be above $0']);
            // if cost client zero

            /*
                Process Yang Akan Dijalankan
                1. charge cost campaign, jika failed maka akan stop campaign, dan berhenti sampai sini, tidak lanjut ke ke step berikutnya 
                2. update budget dan setting lainnya di simplifi
                    - update semua settingan ke db
                    - update_budget_plans_campaign() -> untuk update total budget
                    - update_campaign() -> untuk update cpm, daily budget, goal
            */
            return $this->chargeClient($customer_payment_id,$customer_card_id,$email,$totalFirstCharge,0,0,$updateclient,[],0,false,$dataSimplifi);
        }
        /* PROCESS CHARGE */
    }

    private function chargeClient($custStripeID,$custStripeCardID,$custEmail,$totalAmount,$oneTime,$platformFee,$usrInfo,$topup=[],$rootFee=0,$rootFeeTransfer=false,$dataSimplifi=[]) 
    {
        // info('public function ' . __FUNCTION__, ['get_defined_vars' => get_defined_vars()]);
        date_default_timezone_set('America/Chicago');
        $systemid = config('services.application.systemid');
        $_lp_user_id = $usrInfo[0]->id;
        $invoiceNum = date('Ymd') . '-' . $_lp_user_id;
        $AgencyManualBill = "F";
        
        /** GET COMPANY PARENT NAME / AGENCY */
        $getParentInfo = Company::select('company_name','manual_bill')->where('id','=',$usrInfo[0]->company_parent)->get();
        if(count($getParentInfo) > 0) 
        {
            $companyParentName = $getParentInfo[0]['company_name'];
            $AgencyManualBill = $getParentInfo[0]['manual_bill'];
        }
        /** GET COMPANY PARENT NAME / AGENCY */

        /** CHECK IF THIS COMPANY USERDOWNLINE OR CLIENT */
        $accConID = '';
        if ($usrInfo[0]->company_parent != '') 
        {
            $accConID = $this->check_connected_account($usrInfo[0]->company_parent,$usrInfo[0]->company_root_id);
        }
        /** CHECK IF THIS COMPANY USERDOWNLINE OR CLIENT */

        /** CHECK IF USER DATA STILL ON PLATFORM */
        $validuser = true;
        $user[0]['customer_payment_id'] = (isset($usrInfo[0]->customer_payment_id))?$usrInfo[0]->customer_payment_id:'';
        $user[0]['company_id'] = (isset($usrInfo[0]->company_id))?$usrInfo[0]->company_id:'';
        $user[0]['id'] = (isset($usrInfo[0]->user_id))?$usrInfo[0]->user_id:'';
        $user[0]['company_root_id'] = $usrInfo[0]->company_root_id;
        /** CHECK IF USER DATA STILL ON PLATFORM */

        /** CHECK IF AGENCY MANUAL BILL */
        if ($AgencyManualBill == "T") 
        {
            $validuser = true;
            $custStripeID = "agencyDirectPayment";
            $custStripeCardID = "agencyDirectPayment";
            /** GET STRIPE ACC AND CARD AGENCY */
            $custAgencyStripeID = "";
            $custAgencyStripeCardID = "";

            $chkAgency = User::select('id','customer_payment_id','customer_card_id','email')
                    ->where('company_id','=',$usrInfo[0]->company_parent)
                    ->where('company_parent','<>',$usrInfo[0]->company_parent)
                    ->where('user_type','=','userdownline')
                    ->where('isAdmin','=','T')
                    ->where('active','=','T')
                    ->get();
            if(count($chkAgency) > 0) 
            {
                $custAgencyStripeID = $chkAgency[0]['customer_payment_id'];
                $custAgencyStripeCardID = $chkAgency[0]['customer_card_id'];
            }
            /** GET STRIPE ACC AND CARD AGENCY */
        } 
        else 
        {
            $chkStripeUser = $this->check_stripe_customer_platform_exist($user,$accConID);
            $chkResultUser = json_decode($chkStripeUser);
            if ($chkResultUser->result == 'success') 
            {
                $validuser = true;
                $custStripeID = $chkResultUser->custStripeID;
                $custStripeCardID = $chkResultUser->CardID;
            } 
            else 
            {
                $validuser = false;
            }
        }
        /** CHECK CUSTOMER CARD AND VALID USER */

        /* force simplifi ke prepaid, walaupun di simplifi tidak ada fitur untuk atur weekly,monthly,prepaid. tapi type prepaid lebih masuk akal  */
        if($usrInfo[0]->leadspeek_type == 'simplifi') 
        {
            $usrInfo[0]->paymentterm = 'Prepaid';
        }
        /* force simplifi ke prepaid, walaupun di simplifi tidak ada fitur untuk atur weekly,monthly,prepaid. tapi type prepaid lebih masuk akal  */
        
        $topup_agencies_id = null;
        $leadspeek_invoices_id = null;
        $paymentintentID = '';
        $chargeID = '';
        $platform_errorstripe = '';
        $errorstripe = '';
        $statusPayment = '';
        $platform_paymentintentID = '';
        $sr_id = 0;
        $ae_id = 0;
        $ar_id = 0;
        $sr_fee = 0;
        $ae_fee = 0;
        $ar_fee = 0;
        $sr_transfer_id = '';
        $ae_transfer_id = '';
        $ar_transfer_id = '';
        $sales_fee = 0;
        $platformfee_charge = false;
        $_ongoingleads = "";

        /** CHARGE WITH STRIPE */
        // if(trim($custStripeID) != '' && trim($custStripeCardID) != '' && ($totalAmount > 0 || $totalAmount != '' || $usrInfo[0]->paymentterm == 'One Time' || $usrInfo[0]->paymentterm == 'Prepaid') && $validuser) 
        if(trim($custStripeID) != '' && trim($custStripeCardID) != '' && $validuser)
        { 
            $totalAmount = number_format($totalAmount,2,'.','');

            /** GET STRIPE KEY */
            $stripeseckey = config('services.stripe.secret');
            $stripepublish = $this->getcompanysetting($usrInfo[0]->company_root_id,'rootstripe');
            if ($stripepublish != '') 
            {
                $stripeseckey = (isset($stripepublish->secretkey))?$stripepublish->secretkey:"";
            }
            /** GET STRIPE KEY */

            $stripe = new StripeClient([
                'api_key' => $stripeseckey,
                'stripe_version' => '2020-08-27'
            ]);

            /** GET PLATFORM MARGIN */
            $platformMargin = $this->getcompanysetting($usrInfo[0]->company_parent,'costagency');
            $platform_LeadspeekCostperlead = 0;
            $platform_LeadspeekMinCostMonth = 0;
            $platform_LeadspeekPlatformFee = 0;
            $platformfee_ori = 0;
            
            $paymentterm = trim($usrInfo[0]->paymentterm);
            $paymentterm = str_replace(' ','',$paymentterm);
            if ($usrInfo[0]->leadspeek_type == "local") 
            {
                $rootcostagency = $this->getcompanysetting($usrInfo[0]->company_root_id,'rootcostagency');
                $campaign_information_type_local = !empty($usrInfo[0]->campaign_information_type_local) ? explode(',', $usrInfo[0]->campaign_information_type_local) : [];

                if(in_array('advanced', $campaign_information_type_local)) {
                    $platform_LeadspeekCostperlead = (isset($platformMargin->local->$paymentterm->LeadspeekCostperleadAdvanced))?($platformMargin->local->$paymentterm->LeadspeekCostperleadAdvanced):($rootcostagency->local->$paymentterm->LeadspeekCostperleadAdvanced);
                } else {
                    $platform_LeadspeekCostperlead = (isset($platformMargin->local->$paymentterm->LeadspeekCostperlead))?($platformMargin->local->$paymentterm->LeadspeekCostperlead):0;
                }
                $platform_LeadspeekMinCostMonth = (isset($platformMargin->local->$paymentterm->LeadspeekMinCostMonth))?$platformMargin->local->$paymentterm->LeadspeekMinCostMonth:0;
                $platform_LeadspeekPlatformFee = (isset($platformMargin->local->$paymentterm->LeadspeekPlatformFee))?$platformMargin->local->$paymentterm->LeadspeekPlatformFee:0;
            }
            else if ($usrInfo[0]->leadspeek_type == "locator") 
            {
                $platform_LeadspeekCostperlead = (isset($platformMargin->locator->$paymentterm->LocatorCostperlead))?$platformMargin->locator->$paymentterm->LocatorCostperlead:0;
                $platform_LeadspeekMinCostMonth = (isset($platformMargin->locator->$paymentterm->LocatorMinCostMonth))?$platformMargin->locator->$paymentterm->LocatorMinCostMonth:0;
                $platform_LeadspeekPlatformFee = (isset($platformMargin->locator->$paymentterm->LocatorPlatformFee))?$platformMargin->locator->$paymentterm->LocatorPlatformFee:0;
            }
            else if ($usrInfo[0]->leadspeek_type == "enhance") 
            {
                // $rootcostagency = []; 
                // if(!isset($platformMargin->enhance)) {
                //     $rootcostagency = $this->getcompanysetting($usrInfo[0]->company_root_id,'rootcostagency');
                // }

                $clientTypeLead = $this->getClientCapType($usrInfo[0]->company_root_id);
                $rootcostagency = $this->getcompanysetting($usrInfo[0]->company_root_id,'rootcostagency');
                $costagency = $this->getcompanysetting($usrInfo[0]->company_parent, 'costagency');

                /* VALIDATION COST PER LEAD UNDER COSTAGENCY AND COST PER LEAD IS GREATHER THAN ZERO */
                // info('', [
                //     'action' => 'usrInfo before',
                //     'usrInfo' => $usrInfo
                // ]);
                if($usrInfo[0]->paymentterm == 'Weekly') {
                    if(isset($costagency->enhance->Weekly->EnhanceCostperlead) && ($costagency->enhance->Weekly->EnhanceCostperlead != '') && ($costagency->enhance->Weekly->EnhanceCostperlead != 0) && ($usrInfo[0]->cost_perlead > 0) && ($usrInfo[0]->cost_perlead < $costagency->enhance->Weekly->EnhanceCostperlead)) {
                        // info("VALIDATION COST PER LEAD UNDER COSTAGENCY chargeClient {$usrInfo[0]->paymentterm}");
                        $usrInfo[0]->cost_perlead = (float) $costagency->enhance->Weekly->EnhanceCostperlead;
                    }
                } else if($usrInfo[0]->paymentterm == 'Monthly') {
                    if(isset($costagency->enhance->Monthly->EnhanceCostperlead) && ($costagency->enhance->Monthly->EnhanceCostperlead != '') && ($costagency->enhance->Monthly->EnhanceCostperlead != 0) && ($usrInfo[0]->cost_perlead > 0) && ($usrInfo[0]->cost_perlead < $costagency->enhance->Monthly->EnhanceCostperlead)) {
                        // info("VALIDATION COST PER LEAD UNDER COSTAGENCY chargeClient {$usrInfo[0]->paymentterm}");
                        $usrInfo[0]->cost_perlead = (float) $costagency->enhance->Monthly->EnhanceCostperlead;
                    }
                } else if($usrInfo[0]->paymentterm == 'One Time') {
                    if(isset($costagency->enhance->OneTime->EnhanceCostperlead) && ($costagency->enhance->OneTime->EnhanceCostperlead != '') && ($costagency->enhance->OneTime->EnhanceCostperlead != 0) && ($usrInfo[0]->cost_perlead > 0) && ($usrInfo[0]->cost_perlead < $costagency->enhance->OneTime->EnhanceCostperlead)) {
                        // info("VALIDATION COST PER LEAD UNDER COSTAGENCY chargeClient {$usrInfo[0]->paymentterm}");
                        $usrInfo[0]->cost_perlead = (float) $costagency->enhance->OneTime->EnhanceCostperlead;
                    }
                } else if($usrInfo[0]->paymentterm == 'Prepaid') {
                    if(isset($costagency->enhance->Prepaid->EnhanceCostperlead) && ($costagency->enhance->Prepaid->EnhanceCostperlead != '') && ($costagency->enhance->Prepaid->EnhanceCostperlead != 0) && ($usrInfo[0]->cost_perlead > 0) && ($usrInfo[0]->cost_perlead < $costagency->enhance->Prepaid->EnhanceCostperlead)) {
                        // info("VALIDATION COST PER LEAD UNDER COSTAGENCY chargeClient {$usrInfo[0]->paymentterm}");
                        $usrInfo[0]->cost_perlead = (float) $costagency->enhance->Prepaid->EnhanceCostperlead;
                    }
                }
                // info('', [
                //     'action' => 'usrInfo after',
                //     'usrInfo' => $usrInfo
                // ]);
                /* VALIDATION COST PER LEAD UNDER COSTAGENCY AND COST PER LEAD IS GREATHER THAN ZERO */
                
                if($clientTypeLead['type'] == 'clientcapleadpercentage') {
                    
                    if($usrInfo[0]->paymentterm == 'Weekly') {
                        $m_LeadspeekCostperlead = ($usrInfo[0]->cost_perlead * $clientTypeLead['value']) / 100;
                        // $rootCostPerLeadMin = ($costagency->enhance->Weekly->EnhanceCostperlead > $rootcostagency->enhance->Weekly->EnhanceCostperlead) ? $costagency->enhance->Weekly->EnhanceCostperlead : $rootcostagency->enhance->Weekly->EnhanceCostperlead;
                        $rootCostPerLeadMin = $costagency->enhance->Weekly->EnhanceCostperlead;
                        $rootCostPerLeadMax = ($rootCostPerLeadMin) / ($clientTypeLead['value'] / 100);

                        // jika cost_perlead 0 atau m_LeadspeekCostperlead berada di rentang $rootCostPerLeadMin ~ $rootCostPerLeadMax
                        if(($usrInfo[0]->cost_perlead == 0) || ($usrInfo[0]->cost_perlead <= $rootCostPerLeadMax && $usrInfo[0]->cost_perlead >= $rootCostPerLeadMin)) { 
                            $platform_LeadspeekCostperlead = $rootCostPerLeadMin;
                        }
                        // jika lebih dari $rootCostPerLeadMax, maka dinamis
                        else if($usrInfo[0]->cost_perlead > $rootCostPerLeadMax) {
                            $platform_LeadspeekCostperlead = $m_LeadspeekCostperlead;
                        }
                        else {
                            $platform_LeadspeekCostperlead = $rootCostPerLeadMin;
                        }
                    } else if($usrInfo[0]->paymentterm == 'Monthly') {
                        $m_LeadspeekCostperlead = ($usrInfo[0]->cost_perlead * $clientTypeLead['value']) / 100;
                        // $rootCostPerLeadMin = ($costagency->enhance->Monthly->EnhanceCostperlead > $rootcostagency->enhance->Monthly->EnhanceCostperlead) ? $costagency->enhance->Monthly->EnhanceCostperlead : $rootcostagency->enhance->Monthly->EnhanceCostperlead;
                        $rootCostPerLeadMin = $costagency->enhance->Monthly->EnhanceCostperlead;
                        $rootCostPerLeadMax = ($rootCostPerLeadMin) / ($clientTypeLead['value'] / 100);

                        // jika cost_perlead 0 atau m_LeadspeekCostperlead berada di rentang $rootCostPerLeadMin ~ $rootCostPerLeadMax
                        if(($usrInfo[0]->cost_perlead == 0) || ($usrInfo[0]->cost_perlead <= $rootCostPerLeadMax && $usrInfo[0]->cost_perlead >= $rootCostPerLeadMin)) { 
                            $platform_LeadspeekCostperlead = $rootCostPerLeadMin;
                        }
                        // jika lebih dari $rootCostPerLeadMax, maka dinamis
                        else if($usrInfo[0]->cost_perlead > $rootCostPerLeadMax) {
                            $platform_LeadspeekCostperlead = $m_LeadspeekCostperlead;
                        }
                        else {
                            $platform_LeadspeekCostperlead = $rootCostPerLeadMin;
                        }
                    } else if($usrInfo[0]->paymentterm == 'One Time') {
                        $m_LeadspeekCostperlead = ($usrInfo[0]->cost_perlead * $clientTypeLead['value']) / 100;
                        // $rootCostPerLeadMin = ($costagency->enhance->OneTime->EnhanceCostperlead > $rootcostagency->enhance->OneTime->EnhanceCostperlead) ? $costagency->enhance->OneTime->EnhanceCostperlead : $rootcostagency->enhance->OneTime->EnhanceCostperlead;
                        $rootCostPerLeadMin = $costagency->enhance->OneTime->EnhanceCostperlead;
                        $rootCostPerLeadMax = ($rootCostPerLeadMin) / ($clientTypeLead['value'] / 100);

                        // jika cost_perlead 0 atau m_LeadspeekCostperlead berada di rentang $rootCostPerLeadMin ~ $rootCostPerLeadMax
                        if(($usrInfo[0]->cost_perlead == 0) || ($usrInfo[0]->cost_perlead <= $rootCostPerLeadMax && $usrInfo[0]->cost_perlead >= $rootCostPerLeadMin)) { 
                            $platform_LeadspeekCostperlead = $rootCostPerLeadMin;
                        }
                        // jika lebih dari $rootCostPerLeadMax, maka dinamis
                        else if($usrInfo[0]->cost_perlead > $rootCostPerLeadMax) {
                            $platform_LeadspeekCostperlead = $m_LeadspeekCostperlead;
                        }
                        else {
                            $platform_LeadspeekCostperlead = $rootCostPerLeadMin;
                        }
                    } else if($usrInfo[0]->paymentterm == 'Prepaid') {
                        $m_LeadspeekCostperlead = ($usrInfo[0]->cost_perlead * $clientTypeLead['value']) / 100;
                        // $rootCostPerLeadMin = ($costagency->enhance->Prepaid->EnhanceCostperlead > $rootcostagency->enhance->Prepaid->EnhanceCostperlead) ? $costagency->enhance->Prepaid->EnhanceCostperlead : $rootcostagency->enhance->Prepaid->EnhanceCostperlead;
                        $rootCostPerLeadMin = $costagency->enhance->Prepaid->EnhanceCostperlead;
                        $rootCostPerLeadMax = ($rootCostPerLeadMin) / ($clientTypeLead['value'] / 100);

                        // jika cost_perlead 0 atau m_LeadspeekCostperlead berada di rentang $rootCostPerLeadMin ~ $rootCostPerLeadMax
                        if(($usrInfo[0]->cost_perlead == 0) || ($usrInfo[0]->cost_perlead <= $rootCostPerLeadMax && $usrInfo[0]->cost_perlead >= $rootCostPerLeadMin)) {
                            $platform_LeadspeekCostperlead = $rootCostPerLeadMin;
                        }
                        // jika lebih dari $rootCostPerLeadMax, maka dinamis
                        else if($usrInfo[0]->cost_perlead > $rootCostPerLeadMax) {
                            $platform_LeadspeekCostperlead = $m_LeadspeekCostperlead;
                        }
                        else {
                            $platform_LeadspeekCostperlead = $rootCostPerLeadMin;
                        }
                    }
                } else {
                    $platform_LeadspeekCostperlead = (isset($platformMargin->enhance->$paymentterm->EnhanceCostperlead))?$platformMargin->enhance->$paymentterm->EnhanceCostperlead:$rootcostagency->enhance->$paymentterm->EnhanceCostperlead;
                }

                $platform_LeadspeekMinCostMonth = (isset($platformMargin->enhance->$paymentterm->EnhanceMinCostMonth))?$platformMargin->enhance->$paymentterm->EnhanceMinCostMonth:$rootcostagency->enhance->$paymentterm->EnhanceMinCostMonth;
                $platform_LeadspeekPlatformFee = (isset($platformMargin->enhance->$paymentterm->EnhancePlatformFee))?$platformMargin->enhance->$paymentterm->EnhancePlatformFee:$rootcostagency->enhance->$paymentterm->EnhancePlatformFee;
            }
            else if ($usrInfo[0]->leadspeek_type == "b2b") 
            {
                // $rootcostagency = []; 
                // if(!isset($platformMargin->b2b)) {
                //     $rootcostagency = $this->getcompanysetting($usrInfo[0]->company_root_id,'rootcostagency');
                // }

                $clientTypeLead = $this->getClientCapType($usrInfo[0]->company_root_id);
                $rootcostagency = $this->getcompanysetting($usrInfo[0]->company_root_id,'rootcostagency');
                $costagency = $this->getcompanysetting($usrInfo[0]->company_parent, 'costagency');

                /* VALIDATION COST PER LEAD UNDER COSTAGENCY */
                // info('', [
                //     'action' => 'usrInfo before',
                //     'usrInfo' => $usrInfo
                // ]);
                if($usrInfo[0]->paymentterm == 'Weekly') {
                    $m_B2bCostperlead = (isset($costagency->b2b->Weekly->B2bCostperlead)) ? ($costagency->b2b->Weekly->B2bCostperlead) : ($rootcostagency->b2b->Weekly->B2bCostperlead);
                    if(($m_B2bCostperlead != '') && ($m_B2bCostperlead != 0) && ($usrInfo[0]->cost_perlead > 0) && ($usrInfo[0]->cost_perlead < $m_B2bCostperlead)) {
                        // info("VALIDATION COST PER LEAD UNDER COSTAGENCY chargeClient {$usrInfo[0]->paymentterm}");
                        $usrInfo[0]->cost_perlead = (float) $m_B2bCostperlead;
                    }
                } else if($usrInfo[0]->paymentterm == 'Monthly') {
                    $m_B2bCostperlead = (isset($costagency->b2b->Monthly->B2bCostperlead)) ? ($costagency->b2b->Monthly->B2bCostperlead) : ($rootcostagency->b2b->Monthly->B2bCostperlead);
                    if(($m_B2bCostperlead != '') && ($m_B2bCostperlead != 0) && ($usrInfo[0]->cost_perlead > 0) && ($usrInfo[0]->cost_perlead < $m_B2bCostperlead)) {
                        // info("VALIDATION COST PER LEAD UNDER COSTAGENCY chargeClient {$usrInfo[0]->paymentterm}");
                        $usrInfo[0]->cost_perlead = (float) $m_B2bCostperlead;
                    }
                } else if($usrInfo[0]->paymentterm == 'One Time') {
                    $m_B2bCostperlead = (isset($costagency->b2b->OneTime->B2bCostperlead)) ? ($costagency->b2b->OneTime->B2bCostperlead) : ($rootcostagency->b2b->OneTime->B2bCostperlead);
                    if(($m_B2bCostperlead != '') && ($m_B2bCostperlead != 0) && ($usrInfo[0]->cost_perlead > 0) && ($usrInfo[0]->cost_perlead < $m_B2bCostperlead)) {
                        // info("VALIDATION COST PER LEAD UNDER COSTAGENCY chargeClient {$usrInfo[0]->paymentterm}");
                        $usrInfo[0]->cost_perlead = (float) $m_B2bCostperlead;
                    }
                } else if($usrInfo[0]->paymentterm == 'Prepaid') {
                    $m_B2bCostperlead = (isset($costagency->b2b->Prepaid->B2bCostperlead)) ? ($costagency->b2b->Prepaid->B2bCostperlead) : ($rootcostagency->b2b->Prepaid->B2bCostperlead);
                    if(($m_B2bCostperlead != '') && ($m_B2bCostperlead != 0) && ($usrInfo[0]->cost_perlead > 0) && ($usrInfo[0]->cost_perlead < $m_B2bCostperlead)) {
                        // info("VALIDATION COST PER LEAD UNDER COSTAGENCY chargeClient {$usrInfo[0]->paymentterm}");
                        $usrInfo[0]->cost_perlead = (float) $m_B2bCostperlead;
                    }
                }
                // info('', [
                //     'action' => 'usrInfo after',
                //     'usrInfo' => $usrInfo
                // ]);
                /* VALIDATION COST PER LEAD UNDER COSTAGENCY */

                if($clientTypeLead['type'] == 'clientcapleadpercentage') {
                    
                    if($usrInfo[0]->paymentterm == 'Weekly') {
                        $m_LeadspeekCostperlead = ($usrInfo[0]->cost_perlead * $clientTypeLead['value']) / 100;
                        // $rootCostPerLeadMin = (isset($costagency->b2b->Weekly->B2bCostperlead) && ($costagency->b2b->Weekly->B2bCostperlead > $rootcostagency->b2b->Weekly->B2bCostperlead)) ? $costagency->b2b->Weekly->B2bCostperlead : $rootcostagency->b2b->Weekly->B2bCostperlead;
                        $rootCostPerLeadMin = (isset($costagency->b2b->Weekly->B2bCostperlead)) ? ($costagency->b2b->Weekly->B2bCostperlead) : ($rootcostagency->b2b->Weekly->B2bCostperlead);
                        $rootCostPerLeadMax = ($rootCostPerLeadMin) / ($clientTypeLead['value'] / 100);

                        // Log::info([
                        //     'action' => 'clientcapleadpercentage in chargeclient',
                        //     'rootCostPerLeadMin' => $rootCostPerLeadMin,
                        //     'rootCostPerLeadMax' => $rootCostPerLeadMax
                        // ]);

                        // jika cost_perlead 0 atau m_LeadspeekCostperlead berada di rentang $rootCostPerLeadMin ~ $rootCostPerLeadMax
                        if(($usrInfo[0]->cost_perlead == 0) || ($usrInfo[0]->cost_perlead <= $rootCostPerLeadMax && $usrInfo[0]->cost_perlead >= $rootCostPerLeadMin)) { 
                            $platform_LeadspeekCostperlead = $rootCostPerLeadMin;
                        }
                        // jika lebih dari $rootCostPerLeadMax, maka dinamis
                        else if($usrInfo[0]->cost_perlead > $rootCostPerLeadMax) {
                            $platform_LeadspeekCostperlead = $m_LeadspeekCostperlead;
                        }
                        else {
                            $platform_LeadspeekCostperlead = $rootCostPerLeadMin;
                        }
                    } else if($usrInfo[0]->paymentterm == 'Monthly') {
                        $m_LeadspeekCostperlead = ($usrInfo[0]->cost_perlead * $clientTypeLead['value']) / 100;
                        // $rootCostPerLeadMin = (isset($costagency->b2b->Monthly->B2bCostperlead) && ($costagency->b2b->Monthly->B2bCostperlead > $rootcostagency->b2b->Monthly->B2bCostperlead)) ? $costagency->b2b->Monthly->B2bCostperlead : $rootcostagency->b2b->Monthly->B2bCostperlead;
                        $rootCostPerLeadMin = (isset($costagency->b2b->Monthly->B2bCostperlead)) ? ($costagency->b2b->Monthly->B2bCostperlead) : ($rootcostagency->b2b->Monthly->B2bCostperlead);
                        $rootCostPerLeadMax = ($rootCostPerLeadMin) / ($clientTypeLead['value'] / 100);

                        // Log::info([
                        //     'action' => 'clientcapleadpercentage in chargeclient',
                        //     'rootCostPerLeadMin' => $rootCostPerLeadMin,
                        //     'rootCostPerLeadMax' => $rootCostPerLeadMax
                        // ]);

                        // jika cost_perlead 0 atau m_LeadspeekCostperlead berada di rentang $rootCostPerLeadMin ~ $rootCostPerLeadMax
                        if(($usrInfo[0]->cost_perlead == 0) || ($usrInfo[0]->cost_perlead <= $rootCostPerLeadMax && $usrInfo[0]->cost_perlead >= $rootCostPerLeadMin)) { 
                            $platform_LeadspeekCostperlead = $rootCostPerLeadMin;
                        }
                        // jika lebih dari $rootCostPerLeadMax, maka dinamis
                        else if($usrInfo[0]->cost_perlead > $rootCostPerLeadMax) {
                            $platform_LeadspeekCostperlead = $m_LeadspeekCostperlead;
                        }
                        else {
                            $platform_LeadspeekCostperlead = $rootCostPerLeadMin;
                        }
                    } else if($usrInfo[0]->paymentterm == 'One Time') {
                        $m_LeadspeekCostperlead = ($usrInfo[0]->cost_perlead * $clientTypeLead['value']) / 100;
                        // $rootCostPerLeadMin = (isset($costagency->b2b->OneTime->B2bCostperlead) && ($costagency->b2b->OneTime->B2bCostperlead > $rootcostagency->b2b->OneTime->B2bCostperlead)) ? $costagency->b2b->OneTime->B2bCostperlead : $rootcostagency->b2b->OneTime->B2bCostperlead;
                        $rootCostPerLeadMin = (isset($costagency->b2b->OneTime->B2bCostperlead)) ? ($costagency->b2b->OneTime->B2bCostperlead) : ($rootcostagency->b2b->OneTime->B2bCostperlead);
                        $rootCostPerLeadMax = ($rootCostPerLeadMin) / ($clientTypeLead['value'] / 100);

                        // Log::info([
                        //     'action' => 'clientcapleadpercentage in chargeclient',
                        //     'rootCostPerLeadMin' => $rootCostPerLeadMin,
                        //     'rootCostPerLeadMax' => $rootCostPerLeadMax
                        // ]);


                        // jika cost_perlead 0 atau m_LeadspeekCostperlead berada di rentang $rootCostPerLeadMin ~ $rootCostPerLeadMax
                        if(($usrInfo[0]->cost_perlead == 0) || ($usrInfo[0]->cost_perlead <= $rootCostPerLeadMax && $usrInfo[0]->cost_perlead >= $rootCostPerLeadMin)) { 
                            $platform_LeadspeekCostperlead = $rootCostPerLeadMin;
                        }
                        // jika lebih dari $rootCostPerLeadMax, maka dinamis
                        else if($usrInfo[0]->cost_perlead > $rootCostPerLeadMax) {
                            $platform_LeadspeekCostperlead = $m_LeadspeekCostperlead;
                        }
                        else {
                            $platform_LeadspeekCostperlead = $rootCostPerLeadMin;
                        }
                    } else if($usrInfo[0]->paymentterm == 'Prepaid') {
                        $m_LeadspeekCostperlead = ($usrInfo[0]->cost_perlead * $clientTypeLead['value']) / 100;
                        // $rootCostPerLeadMin = (isset($costagency->b2b->Prepaid->B2bCostperlead) && ($costagency->b2b->Prepaid->B2bCostperlead > $rootcostagency->b2b->Prepaid->B2bCostperlead)) ? $costagency->b2b->Prepaid->B2bCostperlead : $rootcostagency->b2b->Prepaid->B2bCostperlead;
                        $rootCostPerLeadMin = (isset($costagency->b2b->Prepaid->B2bCostperlead)) ? ($costagency->b2b->Prepaid->B2bCostperlead) : ($rootcostagency->b2b->Prepaid->B2bCostperlead);
                        $rootCostPerLeadMax = ($rootCostPerLeadMin) / ($clientTypeLead['value'] / 100);

                        // Log::info([
                        //     'action' => 'clientcapleadpercentage in chargeclient',
                        //     'rootCostPerLeadMin' => $rootCostPerLeadMin,
                        //     'rootCostPerLeadMax' => $rootCostPerLeadMax
                        // ]);

                        // jika cost_perlead 0 atau m_LeadspeekCostperlead berada di rentang $rootCostPerLeadMin ~ $rootCostPerLeadMax
                        if(($usrInfo[0]->cost_perlead == 0) || ($usrInfo[0]->cost_perlead <= $rootCostPerLeadMax && $usrInfo[0]->cost_perlead >= $rootCostPerLeadMin)) {
                            $platform_LeadspeekCostperlead = $rootCostPerLeadMin;
                        }
                        // jika lebih dari $rootCostPerLeadMax, maka dinamis
                        else if($usrInfo[0]->cost_perlead > $rootCostPerLeadMax) {
                            $platform_LeadspeekCostperlead = $m_LeadspeekCostperlead;
                        }
                        else {
                            $platform_LeadspeekCostperlead = $rootCostPerLeadMin;
                        }
                    }
                } else {
                    $platform_LeadspeekCostperlead = (isset($platformMargin->b2b->$paymentterm->B2bCostperlead))?$platformMargin->b2b->$paymentterm->B2bCostperlead:$rootcostagency->b2b->$paymentterm->B2bCostperlead;
                }

                $platform_LeadspeekMinCostMonth = (isset($platformMargin->b2b->$paymentterm->B2bMinCostMonth))?$platformMargin->b2b->$paymentterm->B2bMinCostMonth:$rootcostagency->b2b->$paymentterm->B2bMinCostMonth;
                $platform_LeadspeekPlatformFee = (isset($platformMargin->b2b->$paymentterm->B2bPlatformFee))?$platformMargin->b2b->$paymentterm->B2bPlatformFee:$rootcostagency->b2b->$paymentterm->B2bPlatformFee;
            }
            // info(['cost_perlead' => $usrInfo[0]->cost_perlead,'platform_LeadspeekCostperlead' => $platform_LeadspeekCostperlead,'platform_LeadspeekMinCostMonth' => $platform_LeadspeekMinCostMonth,'platform_LeadspeekPlatformFee' => $platform_LeadspeekPlatformFee,]);
            /** GET PLATFORM MARGIN */

            /* CALCULATE PLATFORMFEE */
            $profitAgency = 0;
            $platformfee = 0;
            if ($usrInfo[0]->paymentterm == 'One Time') 
            {
                $costinclude = ($usrInfo[0]->lp_max_lead_month * $platform_LeadspeekCostperlead);
                $platformfee = (($platform_LeadspeekPlatformFee + $platform_LeadspeekMinCostMonth) - $costinclude);
                if ($platformfee < 0) 
                {
                    $platformfee = $platformfee * -1;
                }
            }
            else if ($usrInfo[0]->paymentterm == 'Prepaid') 
            {
                if ($usrInfo[0]->leadspeek_type == 'simplifi') // untuk module simplifi
                {
                    // info('masuk ke module simplifi');
                    if (($dataSimplifi['chargeType'] ?? "") == "increase_budget") // ini terjadi ketika mengubah daily budget lebih besar dari sebelumnya di tengah jalan 
                    {
                        $platformfee = $dataSimplifi['costAgencyByRemainingDays'] ?? 0; // harga agency
                        $profitAgency = $totalAmount - $platformfee; // hitung profit agency
                        // info(__FUNCTION__ . ' CALCULATE PLATFORMFEE prepaid -> simplifi -> if increase_budget', ['totalAmount' => $totalAmount,'platformfee' => $platformfee,'profitAgency' => $profitAgency]);
                    }
                    else 
                    {
                        $profitAgency = ($usrInfo[0]->monthly_budget * $usrInfo[0]->agency_markup) / 100; // hitung profit agency dulu
                        $platformfee = ($usrInfo[0]->monthly_budget - $profitAgency); // hitung harga agency
                        // info(__FUNCTION__ . ' CALCULATE PLATFORMFEE prepaid -> simplifi -> else', ['monthly_budget' => $usrInfo[0]->monthly_budget,'agency_markup' => $usrInfo[0]->agency_markup,'profitAgency' => $profitAgency,'platformfee' => $platformfee,]);
                    }
                }
                else // untuk module selain simpliifi
                {
                    $platformfee = $platformFee + $platform_LeadspeekPlatformFee;
                    $_ongoingleads = (isset($topup['total_leads']))?$topup['total_leads']:'';
                }
            }
            else
            {
                $platformfee = ($platform_LeadspeekPlatformFee + $platform_LeadspeekMinCostMonth);
            }
            $profitAgency = number_format($profitAgency,2,'.','');
            $platformfee = number_format($platformfee,2,'.','');
            $platformfee_ori = $platformfee;
            // info(['platformfee' => $platformfee,'platformfee_ori' => $platformfee_ori]);
            /* CALCULATE PLATFORMFEE */

            /* VALIDATION INVALID PRICE CAMPAIGN */
            $topup_lp_limit_leads = (isset($topup['lp_limit_leads']))?$topup['lp_limit_leads']:0;
            $topup_total_leads = (isset($topup['total_leads']))?$topup['total_leads']:0;

            $isWeeklyMonthlyPriceInvalid = (
                in_array($usrInfo[0]->paymentterm, ['Weekly', 'Monthly']) &&
                $platform_LeadspeekCostperlead == 0
            );
            $isPrepaidPriceInvalid = (
                $usrInfo[0]->paymentterm == 'Prepaid' &&
                ($platform_LeadspeekCostperlead == 0 || $topup_lp_limit_leads == 0 || $topup_total_leads == 0)
            );

            if($usrInfo[0]->leadspeek_type != 'simplifi' && ($isWeeklyMonthlyPriceInvalid || $isPrepaidPriceInvalid)){
                LeadspeekUser::find($_lp_user_id)->update(['active' => 'F', 'disabled' => 'T', 'active_user' => 'F']);
                $msg = "";
                if($isWeeklyMonthlyPriceInvalid){ // WEEKLY / MONTHLY 
                    $msg = "Sorry, this campaign cannot be started because the agency cost per contact is set to $0. Please update the agency pricing and try again.";
                }elseif($isPrepaidPriceInvalid){ // PREPAID
                    if ($platform_LeadspeekCostperlead == 0){
                        $msg = "Sorry, this campaign cannot be started because the agency cost per contact is set to $0. Please update the agency pricing and try again.";
                    }elseif ($topup_lp_limit_leads == 0){
                        $msg = "Sorry, this campaign cannot be started because the contact per day is set to 0. Please update your prepaid settings and try again.";
                    }elseif ($topup_total_leads == 0){
                        $msg = "Sorry, this campaign cannot be started because the number of contacts to buy is set to 0. Please update your prepaid settings and try again.";
                    }
                }
                return response()->json(array('result'=>'failedPayment','msg'=>$msg));
            }
            /* VALIDATION INVALID PRICE CAMPAIGN */

            /* UPDATE DESCRIPTION */
            $defaultInvoice = '#' . $invoiceNum . '-' . str_replace($usrInfo[0]->leadspeek_api_id,'',$usrInfo[0]->company_name) . ' #' . $usrInfo[0]->leadspeek_api_id;
            $defaultInvoice_ori = $defaultInvoice;
            // if($usrInfo[0]->leadspeek_type == 'enhance' || $usrInfo[0]->leadspeek_type == 'b2b')
            // {
            //     $rootAccConResult = $this->getcompanysetting($usrInfo[0]->company_root_id,'rootfee');
            //     $feepercentagedom = isset($rootAccConResult->feepercentagedom)?$rootAccConResult->feepercentagedom:"";   
            //     $feepercentagemob = isset($rootAccConResult->feepercentagemob)?$rootAccConResult->feepercentagemob:"";   
            //     // jika mobile, feepercentagedom milik mobile dan feepercentagemob milik dom 
            //     if($feepercentagedom != '' && $feepercentagemob != '')
            //     {
            //         $feeMob = number_format((($platformfee * $feepercentagedom) / 100),2,'.','');
            //         $feeDom = number_format((($platformfee * $feepercentagemob) / 100),2,'.','');
            //         $remainingFeeEmm = number_format(($platformfee - $feeMob - $feeDom),2,'.','');
            //         //$defaultInvoice .= ", From a net amount of \$$platformfee for root Emm, $feepercentagedom% (\$$feeMob) is allocated to Mobile and $feepercentagemob% ($feeDom) to Dominator, leaving a remaining balance of \$$remainingFeeEmm.";
            //     }
            //     // jika dominator, feepercentagedom milik dominator 
            //     else if($feepercentagedom != '')
            //     {
            //         $feeDom = number_format((($platformfee * $feepercentagedom) / 100),2,'.','');
            //         $remainingFeeEmm = number_format(($platformfee - $feeDom),2,'.','');
            //         //$defaultInvoice .= ", From a net amount of \$$platformfee for root Emm, $feepercentagedom% (\$$feeDom) is allocated to Dominator, leaving a remaining balance of \$$remainingFeeEmm";
            //     }
            // }
            /* UPDATE DESCRIPTION */

            /** CHECK IF TOTAL AMOUNT IS SMALLER THAN PLATFORM FEE */
            //if (($totalAmount < $platformfee) && $platformfee > 0) {
            // if ($platformfee >= 0.5) {
            //     $agencystripe = $this->check_agency_stripeinfo($usrInfo[0]->company_parent,$platformfee,$usrInfo[0]->leadspeek_api_id,'Agency ' . $defaultInvoice);
            //     $agencystriperesult = json_decode($agencystripe);
            //     $platformfee_charge = true;

            //     if ($agencystriperesult->result == 'success') {
            //         $platform_paymentintentID = $agencystriperesult->payment_intentID;
            //         $sr_id = $agencystriperesult->srID;
            //         $ae_id = $agencystriperesult->aeID;
            //         $sales_fee = $agencystriperesult->salesfee;
            //         $platformfee = 0;
            //         $platform_errorstripe = '';
            //     }else{
            //         $platform_paymentintentID = $agencystriperesult->payment_intentID;
            //         $platform_errorstripe .= $agencystriperesult->error;
            //     }
            // }
            /** CHECK IF TOTAL AMOUNT IS SMALLER THAN PLATFORM FEE */
            
            /* GET BALANCE TOPUP AGENCY */
            $balanceAmount = 0;
            $agencyAgreeDataWallet = 'F';
            if($usrInfo[0]->company_root_id == $systemid)
            {
                // get agency
                $agency = User::where('company_id', $usrInfo[0]->company_parent)
                              ->where('user_type', 'userdownline')
                              ->first();
                $agencyID = $agency->id ?? null;
                // get agency

                // get service agreement data wallet
                $featureDataWalletId = MasterFeature::where('slug', 'data_wallet')
                                                    ->value('id');
                $serviceAgreement = ServicesAgreement::where('user_id','=',$agencyID)
                                                     ->where('feature_id','=',$featureDataWalletId)
                                                     ->first();
                $agencyAgreeDataWallet = (isset($serviceAgreement->status) && $serviceAgreement->status == 'T') ? $serviceAgreement->status : 'F';
                // info(['action' => 'get agreement', 'agencyID' => $agencyID, 'agencyAgreeDataWallet' => $agencyAgreeDataWallet]);
                // get service agreement data wallet

                // get balance
                $balanceAmount = TopupAgency::where('company_id','=',$usrInfo[0]->company_parent)
                                            ->where('topup_status','<>','done')
                                            ->whereNull('expired_at')
                                            ->sum('balance_amount');
                // get balance
            }
            // info(['action' => 'get balance amount in charge client', 'balanceAmount' => $balanceAmount, 'agencyAgreeDataWallet' => $agencyAgreeDataWallet, 'paymentterm' => $paymentterm, 'AgencyManualBill' => $AgencyManualBill]);
            /* GET BALANCE TOPUP AGENCY */

            /** CREATE ONE TIME PAYMENT USING PAYMENT INTENT */
            if ($totalAmount < 0.5 || $totalAmount <= 0) 
            {
                $paymentintentID = '';
                $statusPayment = 'paid';
                $platformfee_charge = false;
            }
            else
            {
                if ($AgencyManualBill == 'F') 
                {
                    try 
                    {
                        $chargeAmount = $totalAmount * 100;
                        $statusPayment = 'paid';
                        $errorstripe = '';

                        if(
                            $agencyAgreeDataWallet != 'T' || // jika agency belum agree data wallet 
                            $balanceAmount == 0 || // balance data wallet agency 0
                            $paymentterm != 'Prepaid' || // type paymentterm campaign bukan prepaid
                            $usrInfo[0]->leadspeek_type == 'simplifi' || // campaign type simplifii
                            $usrInfo[0]->company_root_id != $systemid // bukan dari root emm
                        ) 
                        {
                            $dataPaymentIntent = [
                                'payment_method_types' => ['card'],
                                'customer' => trim($custStripeID),
                                'amount' => $chargeAmount,
                                'currency' => 'usd',
                                'receipt_email' => $custEmail,
                                'payment_method' => $custStripeCardID,
                                'confirm' => true,
                                'description' => $defaultInvoice,
                                'application_fee_amount' => ($platformfee * 100)
                            ];
                            $platformfee_charge = true;
                            // info(['action' => 'prosess charge client with application_fee_amount in chargeclient','dataPaymentIntent' => $dataPaymentIntent,'stripe_account' => $accConID]);
                            $payment_intent = $stripe->paymentIntents->create($dataPaymentIntent, ['stripe_account' => $accConID]);
    
                            /* CHECK STATUS PAYMENT INTENTS */
                            $payment_intent_status = (isset($payment_intent->status))?$payment_intent->status:"";
                            if($payment_intent_status == 'requires_action') 
                            {
                                $statusPayment = 'failed';
                                $platformfee_charge = false;
                            }
                            /* CHECK STATUS PAYMENT INTENTS */
    
                            $paymentintentID = $payment_intent->id;
    
                            if($statusPayment == 'paid' && $platformfee_charge && $usrInfo[0]->leadspeek_type != 'simplifi') 
                            {
                                /** TRANSFER SALES COMMISSION IF ANY */
                                $dataCustomCommissionSales = [];
                                if(count($topup) > 0) // karena chargeClient disini bisa untuk prepaid atau weekly atau monthly, jadi pastikan hanya prepaid dan module nya bukan simplifi 
                                { 
                                    $dataCustomCommissionSales = [
                                        'type' => 'topup',
                                        'platform_price_lead' => $topup['platform_price'],
                                        'total_lead_topup' => $topup['total_leads'],
                                    ];
                                }
                                $_cleanProfit = "";
                                if($rootFee != "0" && $rootFee != "") 
                                {
                                    $_cleanProfit = $platformfee_ori - $rootFee;
                                }
                                $salesfee = $this->transfer_commission_sales($usrInfo[0]->company_parent,$platformfee,$usrInfo[0]->leadspeek_api_id,date('Y-m-d'),date('Y-m-d'),$stripeseckey,$_ongoingleads,$_cleanProfit,$dataCustomCommissionSales);
                                $salesfeeresult = json_decode($salesfee);
                                $platform_paymentintentID = $salesfeeresult->payment_intentID ?? '';
                                $sr_id = $salesfeeresult->srID ?? 0;
                                $ae_id = $salesfeeresult->aeID ?? 0;
                                $ar_id = $salesfeeresult->arID ?? 0;
                                $sr_fee = $salesfeeresult->srFee ?? 0;
                                $ae_fee = $salesfeeresult->aeFee ?? 0;
                                $ar_fee = $salesfeeresult->arFee ?? 0;
                                $sr_transfer_id = $salesfeeresult->srTransferID ?? '';
                                $ae_transfer_id = $salesfeeresult->aeTransferID ?? '';
                                $ar_transfer_id = $salesfeeresult->arTransferID ?? '';
                                $sales_fee = $salesfeeresult->salesfee ?? 0;
                                /** TRANSFER SALES COMMISSION IF ANY */
                            }
                        }
                    }
                    catch (RateLimitException $e) 
                    {
                        // info(__FUNCTION__, ['error1' => $e->getMessage()]);
                        $statusPayment = 'failed';
                        $platformfee_charge = false;
                        // Too many requests made to the API too quickly
                        $errorstripe = $e->getHttpStatus() . '|' . $e->getError()->type . '|' . $e->getError()->code . '|' . $e->getError()->param . '|' . $e->getError()->message;
                    } 
                    catch (InvalidRequestException $e) 
                    {
                        // info(__FUNCTION__, ['error2' => $e->getMessage()]);
                        $statusPayment = 'failed';
                        $platformfee_charge = false;
                        // Invalid parameters were supplied to Stripe's API
                        $errorstripe = $e->getHttpStatus() . '|' . $e->getError()->type . '|' . $e->getError()->code . '|' . $e->getError()->param . '|' . $e->getError()->message;
                    } 
                    catch (ExceptionAuthenticationException $e) 
                    {
                        // info(__FUNCTION__, ['error3' => $e->getMessage()]);
                        $statusPayment = 'failed';
                        $platformfee_charge = false;
                        // Authentication with Stripe's API failed
                        // (maybe you changed API keys recently)
                        $errorstripe = $e->getHttpStatus() . '|' . $e->getError()->type . '|' . $e->getError()->code . '|' . $e->getError()->param . '|' . $e->getError()->message;
                    } 
                    catch (ApiConnectionException $e) 
                    {
                        // info(__FUNCTION__, ['error4' => $e->getMessage()]);
                        $statusPayment = 'failed';
                        $platformfee_charge = false;
                        // Network communication with Stripe failed
                        $errorstripe = $e->getHttpStatus() . '|' . $e->getError()->type . '|' . $e->getError()->code . '|' . $e->getError()->param . '|' . $e->getError()->message;
                    } 
                    catch (ApiErrorException $e) 
                    {
                        // info(__FUNCTION__, ['error5' => $e->getMessage()]);
                        $statusPayment = 'failed';
                        $platformfee_charge = false;
                        // Display a very generic error to the user, and maybe send
                        // yourself an email
                        $errorstripe = $e->getHttpStatus() . '|' . $e->getError()->type . '|' . $e->getError()->code . '|' . $e->getError()->param . '|' . $e->getError()->message;
                    } 
                    catch (Exception $e) 
                    {
                        // info(__FUNCTION__, ['error6' => $e->getMessage()]);
                        $statusPayment = 'failed';
                        $platformfee_charge = false;
                        // Something else happened, completely unrelated to Stripe
                        $errorstripe = 'error not stripe things';
                    }
                }
                else
                {
                    $statusPayment = 'paid';
                    $platformfee_charge = false;
                    $errorstripe = "This direct Agency Bill Method";
                }
            }
            /** CREATE ONE TIME PAYMENT USING PAYMENT INTENT */
                    
            /* GET CARD INFO */
            $cardinfo = "";
            $cardlast = "";
            if ($AgencyManualBill == "T") 
            {
                $custStripeID = $custAgencyStripeID;
                $custStripeCardID = $custAgencyStripeCardID;
                $cardinfo = $stripe->customers->retrieveSource(trim($custStripeID),trim($custStripeCardID),[]);
                $cardlast = $cardinfo->last4;
            }
            else 
            {
                $cardinfo = $stripe->customers->retrieveSource(trim($custStripeID),trim($custStripeCardID),[],['stripe_account' => $accConID]);
                $cardlast = $cardinfo->last4;
            }
            /* GET CARD INFO */

            /** CHECK IF FAILED CHARGE CLIENT WE STILL CHARGE THE AGENCY */
            if (
                ($platformfee_charge == false && $platformfee >= 0.5 && $statusPayment == "paid" && $AgencyManualBill == 'T') || // jika manual bill
                ($totalAmount <= 0 && $platformfee_charge == false && $statusPayment == "paid" && $AgencyManualBill == 'F') || // jika tidak manual bill dan cost client = 0 dan cost agency > 0
                ($platformfee_charge == false && $platformfee > 0 && $statusPayment == "paid" && $balanceAmount > 0 && $agencyAgreeDataWallet == 'T' && $paymentterm == 'Prepaid' && $usrInfo[0]->leadspeek_type != 'simplifi') // jika balance dari data wallet agency > 0 dan sudah agree data wallet dan paymentterm prepaid
            )
            {
                // info('masuk if check_agency_stripeinfo');
                $dataCustomCommissionSales = [];
                if(count($topup) > 0) // karena chargeClient disini bisa untuk prepaid atau weekly atau monthly, jadi pastikan hanya prepaid. di simplifi variable topup tetap jadi array kosong
                {
                    $dataCustomCommissionSales = [
                        'type' => 'topup',
                        'platform_price_lead' => $topup['platform_price'],
                        'total_lead_topup' => $topup['total_leads'],
                    ];
                }

                $_cleanProfit = "";
                if($rootFee != "0" && $rootFee != "") 
                {
                    $_cleanProfit = $platformfee_ori - $rootFee;
                }

                $dataChargeClient = [
                    'stripeseckey' => $stripeseckey,
                    'accConID' => $accConID,
                    'customer' => $custStripeID,
                    'amount' => $totalAmount,
                    'receipt_email' => $custEmail,
                    'payment_method' => $custStripeCardID,
                    'description' => $defaultInvoice,
                ];
                $agencystripe = $this->check_agency_stripeinfo($usrInfo[0]->company_parent,$platformfee,$usrInfo[0]->leadspeek_api_id,"Agency {$defaultInvoice}",date('Y-m-d 00:00:00'),date('Y-m-d 23:59:59'),$_ongoingleads,$_cleanProfit,$dataCustomCommissionSales,$AgencyManualBill,$dataChargeClient);
                $agencystriperesult = json_decode($agencystripe);
                // info(__FUNCTION__, ['agencystriperesult' => $agencystriperesult]);

                if (($agencystriperesult->result ?? '') == 'success') 
                {
                    $platform_paymentintentID = $agencystriperesult->payment_intentID ?? '';
                    $sr_id = $agencystriperesult->srID ?? 0;
                    $ae_id = $agencystriperesult->aeID ?? 0;
                    $ar_id = $agencystriperesult->arID ?? 0;
                    $sr_fee = $agencystriperesult->srFee ?? 0;
                    $ae_fee = $agencystriperesult->aeFee ?? 0;
                    $ar_fee = $agencystriperesult->arFee ?? 0;
                    $sr_transfer_id = $agencystriperesult->srTransferID ?? '';
                    $ae_transfer_id = $agencystriperesult->aeTransferID ?? '';
                    $ar_transfer_id = $agencystriperesult->arTransferID ?? '';
                    $sales_fee = $agencystriperesult->salesfee ?? 0;
                    $topup_agencies_id = $agencystriperesult->topup_agencies_id ?? null;
                    $leadspeek_invoices_id = $agencystriperesult->leadspeek_invoices_id ?? null;
                    $platform_errorstripe = '';
                    $statusPayment = $agencystriperesult->statusPayment ?? '';
                    if(($agencystriperesult->statusPaymentClient ?? '') == 'failed') 
                    {
                        $statusPayment = $agencystriperesult->statusPaymentClient ?? '';
                        $errorstripe = $agencystriperesult->errorstripeClient ?? '';
                    }
                }
                else
                {
                    $platform_paymentintentID = $agencystriperesult->payment_intentID ?? '';
                    $platform_errorstripe .= $agencystriperesult->error ?? '';
                    $statusPayment = 'failed';
                }
            }
            /** CHECK IF FAILED CHARGE CLIENT WE STILL CHARGE THE AGENCY */

            /* WEBHOOK SEND PAYLOAD */
            $webhookSendPayload = [
                'created' => Carbon::now()->timestamp,
                'data' => [
                    'campaign' => [
                        'id' => (int) (isset($usrInfo[0]->leadspeek_api_id)?$usrInfo[0]->leadspeek_api_id:''),
                        'name' => isset($usrInfo[0]->campaign_name)?$usrInfo[0]->campaign_name:'',
                        'payment_term' => isset($usrInfo[0]->paymentterm)?$usrInfo[0]->paymentterm:'',
                    ],
                    'client' => [
                        'id' => (int) $_lp_user_id,
                        'name' => isset($usrInfo[0]->name)?$usrInfo[0]->name:'',
                        'email' => isset($usrInfo[0]->email)?$usrInfo[0]->email:'',
                    ],
                    'pricing' => [
                        'client' => [
                            'setup_fee' => $oneTime,
                            'campaign_fee' => (float) (isset($usrInfo[0]->lp_min_cost_month)?$usrInfo[0]->lp_min_cost_month:0),
                            'cost_per_lead' => (float) (isset($usrInfo[0]->cost_perlead)?$usrInfo[0]->cost_perlead:0),
                            'total_leads' => ($usrInfo[0]->paymentterm == 'Prepaid') ?
                                             ((int) (isset($usrInfo[0]->leadsbuy)?$usrInfo[0]->leadsbuy:0)) : 
                                             ((int) (isset($usrInfo[0]->lp_limit_leads)?$usrInfo[0]->lp_limit_leads:0)),
                            'total_amount' => (float) $totalAmount,
                        ],
                        'agency' => [
                            'setup_fee' => (float) $platform_LeadspeekPlatformFee,
                            'campaign_fee' => (float) $platform_LeadspeekMinCostMonth,
                            'cost_per_lead' => (float) $platform_LeadspeekCostperlead,
                            'total_leads' => ($usrInfo[0]->paymentterm == 'Prepaid') ?
                                             ((int) (isset($usrInfo[0]->leadsbuy)?$usrInfo[0]->leadsbuy:0)) : 
                                             ((int) (isset($usrInfo[0]->lp_limit_leads)?$usrInfo[0]->lp_limit_leads:0)),
                            'total_amount' => (float) $platformfee_ori,
                        ]
                    ]
                ],
            ];
            /* WEBHOOK SEND PAYLOAD */

            /** IF FOR PREPAID AND ATTEMPT TO PAID FAILED THEN WE NEED JUST STOP IT */
            // Log::info(['statusPayment' => $statusPayment,'paymentintentID' => $paymentintentID,'platform_paymentintentID' => $platform_paymentintentID,'AgencyManualBill'=>$AgencyManualBill,'errorstripe' => $errorstripe]);
            $topup_campaigns_id = null;
            if ($statusPayment == 'failed' || (trim($paymentintentID) == "" && trim($platform_paymentintentID) == "")) 
            {   
                /** STOP CAMPAIGN */
                $updateLeadspeekUser = LeadspeekUser::find($_lp_user_id);
                $organizationid = $updateLeadspeekUser->leadspeek_organizationid;
                $campaignsid = $updateLeadspeekUser->leadspeek_campaignsid;
                $updateLeadspeekUser->active = 'F';
                $updateLeadspeekUser->disabled = 'T';
                $updateLeadspeekUser->active_user = 'F';
                $updateLeadspeekUser->save();
                /** STOP CAMPAIGN */
                            
                /** UPDATE USER CLIENT CARD STATUS */
                if($AgencyManualBill == 'F' && !empty($errorstripe)) 
                {
                    $updateUser = User::find($usrInfo[0]->user_id);
                    $updateUser->payment_status = 'failed';
                    $updateUser->save();
                }
                /** UPDATE USER CLIENT CARD STATUS */

                /** ACTIVATE CAMPAIGN SIMPLIFI */
                if ($organizationid != '' && $campaignsid != '' && $usrInfo[0]->leadspeek_type == 'locator') 
                {
                    $camp = $this->startPause_campaign($organizationid,$campaignsid,'stop');
                }
                /** ACTIVATE CAMPAIGN SIMPLIFI */

                /** STOP CAMPAIGN SIMPLIFI */
                if ($organizationid != '' && $campaignsid != '' && $usrInfo[0]->leadspeek_type == 'simplifi')
                {
                    $response = $this->SimpliFiService->update_status_campaign($organizationid, $campaignsid, 'stop');
                    // info(__FUNCTION__, [
                    //     'response_update_status_campaign 2' => $response
                    // ]);
                }
                /** STOP CAMPAIGN SIMPLIFI */

                /* SENDING WEBHOOK AGENCY IF ANY */
                if(in_array($usrInfo[0]->leadspeek_type, ['local','enhance','b2b']))
                {
                    $webhookEvent = 'invoice.payment_failed';
                    $webhookSendPayload = array_merge(['type' => $webhookEvent], $webhookSendPayload);
                    $this->OpenApiWebhookService->sendWebhookEndpoint($usrInfo[0]->company_parent, $webhookEvent, $webhookSendPayload);
                }
                /* SENDING WEBHOOK AGENCY IF ANY */
                
                $paymentUserType = ($AgencyManualBill == 'F' && !empty($errorstripe)) ? "client" : "agency";
                $msg = "Sorry, this campaign cannot be started because your {$paymentUserType}'s credit card charge failed. Please check your {$paymentUserType}'s payment details and try again.";
                return response()->json(array('result'=>'failedPayment','msg'=>$msg));
                exit;die();
            }
            else if ($usrInfo[0]->paymentterm == 'Prepaid' && count($topup) > 0) // di simplifi variable topup tetap jadi array kosong
            {
                $addTopUp = Topup::create($topup);
                $topup_campaigns_id = $addTopUp->id;
            }
            /** IF FOR PREPAID AND ATTEMPT TO PAID FAILED THEN WE NEED JUST STOP IT */

            /* CHECK KEYWORD COUNT */
            // check_keyword_count jika statusnya dari stop ke play di dijalankan di dalam function chargeClient setelah validasi charge
            if (in_array($usrInfo[0]->leadspeek_type, ['enhance','b2b'])) {
                $this->BigDBMController->check_keyword_count($_lp_user_id);
            }
            /* CHECK KEYWORD COUNT */

            $statusClientPayment = $statusPayment;
            $_company_id = $usrInfo[0]->company_id;
            $_user_id = $usrInfo[0]->user_id;
            $_leadspeek_api_id = $usrInfo[0]->leadspeek_api_id;
            $clientPaymentTerm = $usrInfo[0]->paymentterm;
            $minCostLeads = number_format($platformFee,2,'.','');
            $total_leads =  '0';
            $cost_perlead = '0';
            $platform_cost_leads = '0';
            $root_total_amount = '0';
            // variable untuk simplifi
            $budget_plan_id = null;
            $budget_end_date = null;
            $frequency_capping_impressions = '0';
            $frequency_capping_hours = '0';
            $max_bid = '0';
            $monthly_budget = '0';
            $daily_budget = '0';
            $goal_type = null;
            $goal_value = '0';
            $agency_markup = '0';
            // variable untuk simplifi
            // info(__FUNCTION__, ['clientcampaignsid' => $usrInfo[0]->clientcampaignsid]);
            if ($usrInfo[0]->paymentterm == 'Prepaid') 
            {
                if ($usrInfo[0]->leadspeek_type == 'simplifi')
                {   
                    if (($dataSimplifi['chargeType'] ?? "") == "increase_budget") // ini terjadi ketika mengubah daily budget lebih besar dari sebelumnya di tengah jalan 
                    {
                        // info(__FUNCTION__ . 'UPDATE BUDGET prepaid -> simplifi -> if increase_budget');
                        
                        // update in db
                        $updateLeadspeekUser = LeadspeekUser::find($_lp_user_id);
                        $updateLeadspeekUser->max_bid = $dataSimplifi['leadspeekMaxBid'] ?? 0; 
                        $updateLeadspeekUser->monthly_budget = $dataSimplifi['leadspeekMonthlyBudget'] ?? 0; 
                        $updateLeadspeekUser->daily_budget = $dataSimplifi['leadspeekDailyBudget'] ?? 0; 
                        $updateLeadspeekUser->goal_type = $dataSimplifi['leadspeekGoalType'] ?? "none";
                        $updateLeadspeekUser->goal_value = $dataSimplifi['leadspeekGoalValue'] ?? "none";
                        $updateLeadspeekUser->agency_markup = $dataSimplifi['leadspeekAgencyMarkup'] ?? 0;
                        $updateLeadspeekUser->save();
                        // info(__FUNCTION__ . ' update in db', ['dataSimplifi' => $dataSimplifi]);
                        
                        $frequency_capping_impressions = $updateLeadspeekUser->frequency_capping_impressions;
                        $frequency_capping_hours = $updateLeadspeekUser->frequency_capping_hours;
                        $max_bid = $updateLeadspeekUser->max_bid;
                        $monthly_budget = (($dataSimplifi['leadspeekDailyBudget'] ?? 0) * ($dataSimplifi['leadspeekRemainingDays'] ?? 0)) + ($dataSimplifi['leadspeekTotalSpend'] ?? 0);
                        $daily_budget = $updateLeadspeekUser->daily_budget;
                        $goal_type = $updateLeadspeekUser->goal_type;
                        $goal_value = $updateLeadspeekUser->goal_value;
                        $agency_markup = $updateLeadspeekUser->agency_markup;
                        // update in db

                        /* get last budget asc */
                        $now_date = Carbon::now()->toDateString();
                        $organizationid = $updateLeadspeekUser->leadspeek_organizationid;
                        $campaignsid = $updateLeadspeekUser->leadspeek_campaignsid;

                        $budget_plans_all = $this->SimpliFiService->get_budget_plans_campaign($campaignsid);
                        $budget_plan_first = collect($budget_plans_all['budget_plans'] ?? [])
                                             ->filter(function ($item) use ($now_date) {
                                                return $item['end_date'] >= $now_date; // hanya ambil yg end_date >= hari ini
                                             })
                                             ->sortBy('start_date')
                                             ->first(); // ambil budget yang belom expired, dan urutkan bedasarkan start_date asc, dan ambil yang pertama
                        
                        $budget_plan_id = $budget_plan_first['id'] ?? null;
                        $budget_end_date = $budget_plan_first['end_date'] ?? null;
                        $total_budget = $budget_plan_first['total_budget'] ?? null;
                        
                        // info(__FUNCTION__ . ' get last budget asc', ['now_date' => $now_date,'budget_plan_first' => $budget_plan_first,]);
                        /* get last budget asc */

                        // update budget in simplifi
                        $platformfee_ori_half = $platformfee_ori / 2;
                        $budget_plans_data = [
                            'total_budget' => number_format($total_budget + $platformfee_ori_half,2,'.','')
                        ];
                        // info(__FUNCTION__ . ' update budget in simplifi', ['platformfee_ori' => $platformfee_ori,'budget_plans_data' => $budget_plans_data]);
                        $response = $this->SimpliFiService->update_budget_plans_campaign($budget_plan_id, $budget_plans_data);
                        // update budget in simplifi

                        // update campaign setting in simplifi
                        $campaign_goal = null;
                        if(in_array($goal_type, ['cpa', 'cpc', 'ctr']))
                            $campaign_goal  = [
                                'goal_type' => $goal_type,
                                'goal_value' => $goal_value
                            ];
                        $campaign_settings = [
                            'daily_budget' => number_format($daily_budget / 2,2,'.',''),
                            'campaign_goal' => $campaign_goal,
                            'bid' => number_format($max_bid / 2,2,'.',''),
                            'bid_type_id' => 1, // ID for CPM
                        ];
                        // info(__FUNCTION__ . ' update campaign setting in simplifi', ['campaign_settings' => $campaign_settings,]);
                        $this->SimpliFiService->update_campaign($organizationid, $campaignsid, $campaign_settings);
                        // update campaign setting in simplifi
                    }
                    else 
                    {
                        // info(__FUNCTION__ . 'GET BUDGET prepaid -> simplifi -> else');

                        // get lates budget
                        $budget_plan_id = $dataSimplifi['budget_plan_id'] ?? null;
                        $budget_end_date = $dataSimplifi['budget_end_date'] ?? null;
                        // get lates budget
    
                        $frequency_capping_impressions = $usrInfo[0]->frequency_capping_impressions;
                        $frequency_capping_hours = $usrInfo[0]->frequency_capping_hours;
                        $max_bid = $usrInfo[0]->max_bid;
                        $monthly_budget = $usrInfo[0]->monthly_budget;
                        $daily_budget = $usrInfo[0]->daily_budget;
                        $goal_type = $usrInfo[0]->goal_type;
                        $goal_value = $usrInfo[0]->goal_value;
                        $agency_markup = $usrInfo[0]->agency_markup;
                    }
                    $this->UpsertReportAnalytics($usrInfo[0]->leadspeek_api_id, $usrInfo[0]->leadspeek_type);
                }
                else 
                {
                    $minCostLeads = number_format($usrInfo[0]->lp_min_cost_month,2,'.','');
                    $total_leads =  $usrInfo[0]->leadsbuy;
                    $cost_perlead = $usrInfo[0]->cost_perlead;
                    $platform_cost_leads = $platform_LeadspeekCostperlead;
                    $root_total_amount = (isset($usrInfo[0]->root_price))?$usrInfo[0]->root_price:'0';
                    $root_total_amount = $total_leads * $root_total_amount;
                }
            }
            // info(__FUNCTION__, ['budget_plan_id' => $budget_plan_id]);
            $reportSentTo = explode(PHP_EOL, $usrInfo[0]->report_sent_to);
            $todayDate = date('Y-m-d H:i:s');
            $clientMaxperTerm = $usrInfo[0]->lp_max_lead_month;
            $oneTime = number_format($oneTime,2,'.','');

            /** CHECK IF ROOT FEE TRANSFER ENABLED */
            if ($rootFeeTransfer) 
            {
                $this->root_fee_commission($usrInfo[0],$stripeseckey,$usrInfo[0]->company_name,$usrInfo[0]->leadspeek_api_id,$rootFee,$platformfee_ori);
            }
            /** CHECK IF ROOT FEE TRANSFER ENABLED */

            /* GET ID SALES AGENCY */
            if (trim($sr_id) == "") 
            {
                $sr_id = 0;
            }
            if (trim($ae_id) == "") 
            {
                $ae_id = 0;
            }
            if (trim($ar_id) == "") 
            {
                $ar_id = 0;
            }
            /* GET ID SALES AGENCY */

            /** CREATE INVOICE FOR THIS */
            $invoiceID = "";
            if(empty($leadspeek_invoices_id) && trim($leadspeek_invoices_id) == '') 
            {
                // info('buat invoice');
                $invoiceCreated = LeadspeekInvoice::create([
                    'topup_agencies_id' => $topup_agencies_id,
                    'topup_campaigns_id' => $topup_campaigns_id,
                    'budget_plan_id' => $budget_plan_id,
                    'company_id' => $_company_id,
                    'user_id' => $_user_id,
                    'leadspeek_api_id' => $_leadspeek_api_id,
                    'invoice_number' => '',
                    'payment_term' => $clientPaymentTerm,
                    'onetimefee' => $oneTime,
                    'platform_onetimefee' => $platform_LeadspeekPlatformFee,
                    'min_leads' => $clientMaxperTerm,
                    'exceed_leads' => '0',
                    'total_leads' => $total_leads,
                    'min_cost' => $minCostLeads,
                    'platform_min_cost' => $platform_LeadspeekMinCostMonth,
                    'cost_leads' => $cost_perlead,
                    'platform_cost_leads' => $platform_cost_leads,
                    'frequency_capping_impressions' => $frequency_capping_impressions,
                    'frequency_capping_hours' => $frequency_capping_hours,
                    'max_bid' => $max_bid,
                    'monthly_budget' => $monthly_budget,
                    'daily_budget' => $daily_budget,
                    'goal_type' => $goal_type,
                    'goal_value' => $goal_value,
                    'agency_markup' => $agency_markup,
                    'total_amount' => $totalAmount,
                    'platform_total_amount' => $platformfee_ori,
                    'root_total_amount' => $root_total_amount,
                    'status' => $statusPayment,
                    'customer_payment_id' => $paymentintentID,
                    'platform_customer_payment_id' => $platform_paymentintentID,
                    'error_payment' => $errorstripe,
                    'platform_error_payment' => $platform_errorstripe,
                    'invoice_date' => date('Y-m-d'),
                    'invoice_start' => date('Y-m-d'),
                    'invoice_end' => date('Y-m-d'),
                    'sent_to' => json_encode($reportSentTo),
                    'sr_id' => $sr_id,
                    'sr_fee' => $sr_fee,
                    'sr_transfer_id' => $sr_transfer_id,
                    'ae_id' => $ae_id,
                    'ae_fee' => $ae_fee,
                    'ae_transfer_id' => $ae_transfer_id,
                    'ar_id' => $ar_id,
                    'ar_fee' => $ar_fee,
                    'ar_transfer_id' => $ar_transfer_id,
                    'active' => 'T',
                ]);
                $invoiceID = $invoiceCreated->id;
            }
            else 
            {
                // info('update invoice');
                $invoiceID = $leadspeek_invoices_id;
                $dataLeadspeekInvoiceUpdate = [
                    'topup_agencies_id' => $topup_agencies_id,
                    'topup_campaigns_id' => $topup_campaigns_id,
                    'budget_plan_id' => $budget_plan_id,
                    'company_id' => $_company_id,
                    'user_id' => $_user_id,
                    'invoice_number' => '',
                    'payment_term' => $clientPaymentTerm,
                    'onetimefee' => $oneTime,
                    'platform_onetimefee' => $platform_LeadspeekPlatformFee,
                    'min_leads' => $clientMaxperTerm,
                    'exceed_leads' => '0',
                    'total_leads' => $total_leads,
                    'min_cost' => $minCostLeads,
                    'platform_min_cost' => $platform_LeadspeekMinCostMonth,
                    'cost_leads' => $cost_perlead,
                    'platform_cost_leads' => $platform_cost_leads,
                    'frequency_capping_impressions' => $frequency_capping_impressions,
                    'frequency_capping_hours' => $frequency_capping_hours,
                    'max_bid' => $max_bid,
                    'monthly_budget' => $monthly_budget,
                    'daily_budget' => $daily_budget,
                    'goal_type' => $goal_type,
                    'goal_value' => $goal_value,
                    'agency_markup' => $agency_markup,
                    'total_amount' => $totalAmount,
                    'platform_total_amount' => $platformfee_ori,
                    'root_total_amount' => $root_total_amount,
                    'status' => $statusPayment,
                    // 'customer_payment_id' => $paymentintentID,
                    'platform_customer_payment_id' => $platform_paymentintentID,
                    'error_payment' => $errorstripe,
                    'platform_error_payment' => $platform_errorstripe,
                    'invoice_date' => date('Y-m-d'),
                    'invoice_start' => date('Y-m-d'),
                    'invoice_end' => date('Y-m-d'),
                    'sent_to' => json_encode($reportSentTo),
                    'sr_id' => $sr_id,
                    'sr_fee' => $sr_fee,
                    'sr_transfer_id' => $sr_transfer_id,
                    'ae_id' => $ae_id,
                    'ae_fee' => $ae_fee,
                    'ae_transfer_id' => $ae_transfer_id,
                    'ar_id' => $ar_id,
                    'ar_fee' => $ar_fee,
                    'ar_transfer_id' => $ar_transfer_id,
                    'active' => 'T',
                ];
                if(!empty($paymentintentID) && trim($paymentintentID) != ''){
                    $dataLeadspeekInvoiceUpdate['customer_payment_id'] = $paymentintentID;
                }
                LeadspeekInvoice::where('id','=',$invoiceID)->update($dataLeadspeekInvoiceUpdate);
            }
    
            $invoice = LeadspeekInvoice::find($invoiceID);
            if(!empty($invoice)) 
            {
                $invoice->invoice_number = $invoiceNum . '-' . $invoiceID;
                $invoice->save();
            }

            $lpupdate = LeadspeekUser::find($_lp_user_id);
            $lpupdate->ongoing_leads = 0;
            $lpupdate->start_billing_date = $todayDate;
            $lpupdate->lp_invoice_date = $todayDate;
            $lpupdate->lifetime_cost = ($lpupdate->lifetime_cost + $totalAmount);
            $lpupdate->is_send_email_prepaid = 'F';
            if ($lpupdate->leadspeek_type == 'simplifi')
            {
                // info(__FUNCTION__ . " update lp_enddate when simplifi budget_end_date = $budget_end_date");
                $lpupdate->lp_enddate = $budget_end_date;
            }
            $lpupdate->save();
            /** CREATE INVOICE FOR THIS */

            /* SENDING WEBHOOK AGENCY IF ANY */
            if(in_array($usrInfo[0]->leadspeek_type, ['local','enhance','b2b']))
            {
                $webhookEvent = 'invoice.succeeded';
                $webhookSendPayload = array_merge(['type' => $webhookEvent], $webhookSendPayload);
                $this->OpenApiWebhookService->sendWebhookEndpoint($usrInfo[0]->company_parent, $webhookEvent, $webhookSendPayload);
            }
            /* SENDING WEBHOOK AGENCY IF ANY */

            /** GET NAME CAMPAIGN AND COMPANY NAME */
            $campaignName = '';
            if (isset($usrInfo[0]->campaign_name) && trim($usrInfo[0]->campaign_name) != '') 
            {
                $campaignName = ' - ' . str_replace($_leadspeek_api_id,'',$usrInfo[0]->campaign_name);
            }
            $companyName = str_replace($_leadspeek_api_id,'',$usrInfo[0]->company_name) . $campaignName;
            /** GET NAME CAMPAIGN AND COMPANY NAME */

            /** FIND ADMIN EMAIL */
            $adminnotify = explode(',',$usrInfo[0]->admin_notify_to);
            $tmp = User::select('email')->whereIn('id', $adminnotify)->get();
            $adminEmail = array();
            foreach($tmp as $ad) 
            {
                array_push($adminEmail,$ad['email']);
            }
            //array_push($adminEmail,'serverlogs@sitesettingsapi.com');
            if ($statusPayment == 'paid') 
            {
                $statusPayment = "Customer's Credit Card Successfully Charged ";
            }
            else if ($statusPayment == 'failed') 
            {
                $statusPayment = "Customer's Credit Card Failed";
            }
            else
            {
                $statusPayment = "Customer's Credit Card Successfully Charged ";
            }
            if ($totalAmount == '0.00' || $totalAmount == '0') 
            {
                $statusPayment = "Customer's Credit Card Successfully Charged";
            }
            if ($platformfee_ori != '0.00' || $platformfee_ori != '0') 
            {
                if ($statusClientPayment == 'failed' && $clientPaymentTerm != 'Prepaid') 
                {
                    $statusPayment .= " and Agency's Card Charged For Overage";
                }
            }
            if ($AgencyManualBill == "T") 
            {
                $statusPayment = "You must directly bill your client the amount due.";
            }
            /** FIND ADMIN EMAIL */

            /** UPDATE CLIENT STRIPE PAYMENT DESCRIPTION */
            if ($paymentintentID != "") 
            {
                try 
                {
                    $updatePaymentClientDesc =  $stripe->paymentIntents->update(
                        $paymentintentID,
                        ['description' => $defaultInvoice_ori],
                        ['stripe_account' => $accConID]
                    );
                }
                catch (\Throwable $e) 
                {
                    Log::error("Error Update Client Stripe Payment Description In Function Charge Client Message = {$e->getMessage()}");
                }
            }
            /** UPDATE CLIENT STRIPE PAYMENT DESCRIPTION */

            /** SEND EMAIL */
            $AdminDefault = $this->get_default_admin($usrInfo[0]->company_root_id);
            $AdminDefaultEmail = (isset($AdminDefault[0]['email']))?$AdminDefault[0]['email']:'';
            $rootCompanyInfo = $this->getCompanyRootInfo($usrInfo[0]->company_root_id);
            $defaultdomain = $this->getDefaultDomainEmail($usrInfo[0]->company_root_id);
            
            $details = [
                'leadspeek_type' => $lpupdate->leadspeek_type,
                'max_bid' => $lpupdate->max_bid, 
                'monthly_budget' => $lpupdate->monthly_budget,
                'daily_budget' => $lpupdate->daily_budget,
                'goal_type' => $lpupdate->goal_type,
                'goal_value' => $lpupdate->goal_value,
                'agency_markup' => $lpupdate->agency_markup,
                'agency_profit' => $profitAgency,
                'paymentterm' => $clientPaymentTerm,
                'name'  => $companyName,
                'cardlast' => trim($cardlast),
                'leadspeekapiid' =>$_leadspeek_api_id,
                'invoiceNumber' => $invoiceNum . '-' . $invoiceID,
                'invoiceStatus' => $statusPayment,
                'invoiceDate' => date('m-d-Y'),
                'onetimefee' => $oneTime,
                'cost_perlead' => (isset($topup['cost_perlead']))?$topup['cost_perlead']:'0',
                'total_leads' => (isset($topup['total_leads']))?$topup['total_leads']:'0',
                'platform_onetimefee' => $platform_LeadspeekPlatformFee,
                'platform_cost_perlead' => (isset($topup['platform_price']))?$topup['platform_price']:'0',
                'min_cost' => $minCostLeads,
                'platform_min_cost' => $platform_LeadspeekMinCostMonth,
                'min_leads'=> $clientMaxperTerm,
                'total_amount' => $totalAmount,  
                'platform_total_amount' => $platformfee_ori,
                'invoicetype' => 'agency',
                'agencyname' => $rootCompanyInfo['company_name'],
                'defaultadmin' => $AdminDefaultEmail,
            ];
            $attachement = array();
            $from = [
                'address' => 'noreply@' . $defaultdomain,
                'name' => 'Invoice',
                'replyto' => 'support@' . $defaultdomain,
            ];

            $subjectFailed = "";
            if ($statusClientPayment == 'failed') 
            {
                $subjectFailed = "Failed Payment - ";
            }

            $tmp = $this->send_email($adminEmail,$from,$subjectFailed . 'Invoice for ' . $companyName . ' #' . $_leadspeek_api_id . ' (' . date('m-d-Y',strtotime($todayDate)) . ')',$details,$attachement,'emails.tryseramatchlistcharge',$usrInfo[0]->company_parent,true);
            $tmp = $this->send_email(array('serverlogs@sitesettingsapi.com'),$from,$subjectFailed . 'Invoice for ' . $companyName . ' #' . $_leadspeek_api_id . ' (' . date('m-d-Y',strtotime($todayDate)) . ')',$details,$attachement,'emails.tryseramatchlistcharge','',true);
            /** SEND EMAIL */

            /** CHECK IF FAILED PAYMENT THEN PAUSED THE CAMPAIGN AND SENT EMAIL*/
            if ($statusClientPayment == 'failed') 
            {
                $ClientCompanyIDFailed = "";
                $ListFailedCampaign = "";

                $leadsuser = LeadspeekUser::select('leadspeek_users.leadspeek_type','leadspeek_users.active','leadspeek_users.disabled','leadspeek_users.trysera','leadspeek_users.leadspeek_organizationid','leadspeek_users.leadspeek_campaignsid','users.customer_payment_id','users.company_id','leadspeek_users.user_id')
                                    ->join('users','leadspeek_users.user_id','=','users.id')
                                    ->where('leadspeek_users.leadspeek_api_id','=',$_leadspeek_api_id)
                                    ->get();
                if (count($leadsuser) > 0) 
                {
                    foreach($leadsuser as $lds) 
                    {
                        $ClientCompanyIDFailed = $lds['company_id'];

                        if ($lds['active'] == "T" || ($lds['active'] == "F" && $lds['disabled'] == "F")) 
                        {
                            $tryseramethod = (isset($lds['trysera']) && $lds['trysera'] == "T")?true:false;

                            $http = new \GuzzleHttp\Client;
                            $appkey = config('services.trysera.api_id');
                            $organizationid = ($lds['leadspeek_organizationid'] != "")?$lds['leadspeek_organizationid']:"";
                            $campaignsid = ($lds['leadspeek_campaignsid'] != "")?$lds['leadspeek_campaignsid']:"";    
                            $userStripeID = $lds['customer_payment_id'];
                            
                            if ($tryseramethod) 
                            {
                                /** GET COMPANY NAME AND CUSTOM ID */
                                $tryseraCustomID =  '3_' . $_company_id . '00' . $_user_id . '_' . $_lp_user_id . '_' . date('His');
                                /** GET COMPANY NAME AND CUSTOM ID */

                                /** DISABLED THE TRYSERA ALSO MAKE IT IN ACTIVE */
                                $pauseApiURL =  config('services.trysera.endpoint') . 'subclients/' . $_leadspeek_api_id;
                                $pauseoptions = [
                                    'headers' => [
                                        'Authorization' => 'Bearer ' . $appkey,
                                    ],
                                    'json' => [
                                        "SubClient" => [
                                            "ID" => $_leadspeek_api_id,
                                            "Name" => trim($companyName),
                                            "CustomID" => $tryseraCustomID ,
                                            "Active" => false
                                        ]       
                                    ]
                                ]; 
                                $pauseresponse = $http->put($pauseApiURL,$pauseoptions);
                                $result =  json_decode($pauseresponse->getBody());

                            }

                            $updateLeadspeekUser = LeadspeekUser::find($_lp_user_id);
                            $updateLeadspeekUser->active = 'F';
                            $updateLeadspeekUser->disabled = 'T';
                            $updateLeadspeekUser->active_user = 'T';
                            $updateLeadspeekUser->last_lead_pause = date('Y-m-d H:i:s');
                            $updateLeadspeekUser->save();
                            /** DISABLED THE TRYSERA ALSO MAKE IT IN ACTIVE */
                            
                            /** UPDATE USER CARD STATUS */
                            $updateUser = User::find($lds['user_id']);
                            $updateUser->payment_status = 'failed';
                            $updateUser->save();
                            /** UPDATE USER CARD STATUS */

                            /** ACTIVATE CAMPAIGN SIMPLIFI */
                            if ($organizationid != '' && $campaignsid != '' && $lds['leadspeek_type'] == 'locator') 
                            {
                                $camp = $this->startPause_campaign($organizationid,$campaignsid,'pause');
                            }
                            /** ACTIVATE CAMPAIGN SIMPLIFI */

                            $ListFailedCampaign = $ListFailedCampaign . $_leadspeek_api_id . '<br/>';
                        }
                    }

                    /** PAUSED THE OTHER ACTIVE CAMPAIGN FOR THIS CLIENT */
                    $leadsuser = LeadspeekUser::select('leadspeek_users.id','leadspeek_users.leadspeek_type','leadspeek_users.leadspeek_api_id','leadspeek_users.active','leadspeek_users.disabled','leadspeek_users.trysera','leadspeek_users.leadspeek_organizationid','leadspeek_users.leadspeek_campaignsid','users.customer_payment_id','users.company_id')
                                    ->join('users','leadspeek_users.user_id','=','users.id')
                                    ->where('users.company_id','=',$ClientCompanyIDFailed)
                                    ->where('users.user_type','=','client')
                                    ->where('leadspeek_users.leadspeek_api_id','<>',$_leadspeek_api_id)
                                    ->where(function($query){
                                        $query->where(function($query){
                                            $query->where('leadspeek_users.active','=','T')
                                                ->where('leadspeek_users.disabled','=','F')
                                                ->where('leadspeek_users.active_user','=','T');
                                        })
                                        ->orWhere(function($query){
                                            $query->where('leadspeek_users.active','=','F')
                                                ->where('leadspeek_users.disabled','=','F')
                                                ->where('leadspeek_users.active_user','=','T');
                                        });
                                    })->get();

                    if (count($leadsuser) > 0) 
                    {
                        foreach($leadsuser as $lds) 
                        {
                            $http = new \GuzzleHttp\Client;
                            
                            $organizationid = ($lds['leadspeek_organizationid'] != "")?$lds['leadspeek_organizationid']:"";
                            $campaignsid = ($lds['leadspeek_campaignsid'] != "")?$lds['leadspeek_campaignsid']:"";    
                            $userStripeID = $lds['customer_payment_id'];

                            $updateLeadspeekUser = LeadspeekUser::find($lds['id']);
                            $updateLeadspeekUser->active = 'F';
                            $updateLeadspeekUser->disabled = 'T';
                            $updateLeadspeekUser->active_user = 'T';
                            $updateLeadspeekUser->last_lead_pause = date('Y-m-d H:i:s');
                            $updateLeadspeekUser->save();
                            /** DISABLED THE TRYSERA ALSO MAKE IT IN ACTIVE */
                            
                            /** ACTIVATE CAMPAIGN SIMPLIFI */
                            if ($organizationid != '' && $campaignsid != '' && $lds['leadspeek_type'] == 'locator') 
                            {
                                $camp = $this->startPause_campaign($organizationid,$campaignsid,'pause');
                            }
                            /** ACTIVATE CAMPAIGN SIMPLIFI */

                            $ListFailedCampaign = $ListFailedCampaign . $lds['leadspeek_api_id'] . '<br/>';
                        }
                    }
                    /** PAUSED THE OTHER ACTIVE CAMPAIGN FOR THIS CLIENT */

                    /** SEND EMAIL TELL THIS CAMPAIGN HAS BEEN PAUSED BECAUSE FAILED PAYMENT */
                    $from = [
                        'address' => 'noreply@' . $defaultdomain,
                        'name' => 'Invoice',
                        'replyto' => 'support@' . $defaultdomain,
                    ];
                    
                    $details = [
                        'campaignid'  => $_leadspeek_api_id,
                        'stripeid' => $userStripeID,
                        'othercampaigneffected' => $ListFailedCampaign,
                    ];

                    $tmp = $this->send_email($adminEmail,$from,'Campaign ' . $companyName . ' #' . $_leadspeek_api_id . ' (has been pause due the payment failed)',$details,$attachement,'emails.invoicefailed',"",true);
                    return response()->json(array('result'=>'failedPayment','msg'=>'Sorry, this campaign can not be start, due the payment failed we paused the campaign ID : #' . $_leadspeek_api_id . ' (internal 2)'));
                    /** SEND EMAIL TELL THIS CAMPAIGN HAS BEEN PAUSED BECAUSE FAILED PAYMENT */
                }
            }
            /** CHECK IF FAILED PAYMENT THEN PAUSED THE CAMPAIGN AND SENT EMAIL*/

            return response()->json(['result'=>'success','invoiceID'=>$invoiceID]);
        }
        /** CHARGE WITH STRIPE */
    }
    
    public function activepauseclient(Request $request) {
        // info(__FUNCTION__, ['all' => $request->all()]);
        date_default_timezone_set('America/Chicago');
        
        $activeUser = (isset($request->activeuser))?$request->activeuser:'';
        $userID = (isset($request->userID))?$request->userID:'';
        $ip_user = (isset($request->ip_user))?$request->ip_user:'';
        $timezone = (isset($request->timezone))?$request->timezone:'';
        $idSys = (isset($request->idSys))?$request->idSys:'';

        $tryseraCustomID = $this->_moduleID . '_' . $request->companyID . '00' . $request->userID . '_' . date('His');
        $status = true;
        $campaignStatus = 'activate';
        if($request->status == 'T') {
            $status = false;
            $campaignStatus = 'pause';
        }
        
        $leads = LeadspeekUser::find($request->leadspeekID);

        $leadspeek_api_id = $leads->leadspeek_api_id;
        $organizationid = $leads->leadspeek_organizationid;
        $campaignsid = $leads->leadspeek_campaignsid;
        $isCampaignBudgetPaid = false;
        $activateCampaignBudgetPlan = false;
        $activateCampaignBudgetPlanDate = "";

        /* VALIDATION IS CAMPAIGN ALREADY ACTIVE OR ALREADY PAUSED */
        // info(['campaignStatus' => $campaignStatus,'leads->active' => $leads->active,'leads->disabled' => $leads->disabled,'leads->active_user' => $leads->active_user,'check' => ($campaignStatus == 'pause' && $leads->active == 'F' && $leads->disabled == 'T' && $leads->active_user == 'T')]);
        if($campaignStatus == 'activate' && (($leads->active == 'T' && $leads->disabled == 'F' && $leads->active_user == 'T') || ($leads->active == 'F' && $leads->disabled == 'F' && $leads->active_user == 'T'))) {
            return response()->json(['result'=>'failed_campaign_already_active','msg'=>'Sorry, you cannot active campaign because this campaign is already active']);
        } else if($campaignStatus == 'pause' && (($leads->active == 'F' && $leads->disabled == 'T' && $leads->active_user == 'T') || $leads->active == 'F' && $leads->disabled == 'T' && $leads->active_user == 'F')) {
            $currentStatus = ($leads->active == 'F' && $leads->disabled == 'T' && $leads->active_user == 'T') ? 'paused' : 'stopped';
            return response()->json(['result'=>'failed_campaign_already_paused','msg'=>"Sorry, you cannot paused campaign because this campaign is already {$currentStatus}"]);
        }
        /* VALIDATION IS CAMPAIGN ALREADY ACTIVE OR ALREADY PAUSED */

        /** LOG ACTION */
        // $ipAddress = ($request->header('Clientip') !== null)?$request->header('Clientip'):$request->ip();
        // $loguser = $this->logUserAction($userID,'Campaign ' . $campaignStatus,'Campaign Status : ' . $campaignStatus . ' | CampaignID :' . $leads->leadspeek_api_id,$ipAddress);
        /** LOG ACTION */

        /* FORCE LEADSBUY TO LIMIT LEAD WHEN CAMPAIGN TYPE LOCAL AND LIMIT LEAD FREQUENCY IS MONTH */
        if($leads->leadspeek_type == 'local' && $leads->lp_limit_freq == 'month')
        {
            $leads->leadsbuy = $leads->lp_limit_leads;
            $leads->continual_buy_options = 'Monthly';
            $leads->save();
        }
        /* FORCE LEADSBUY TO LIMIT LEAD WHEN CAMPAIGN TYPE LOCAL AND LIMIT LEAD FREQUENCY IS MONTH */

        $updateclient = $this->getclient($request);

        /* ACTIVE CAMPAIGN SIMPLIFI SITE TARGETING */
        $budget_plan_id = null;
        $budget_end_date = null;
        if ($leads->leadspeek_type == 'simplifi' && $campaignStatus == 'activate' && $organizationid != '' && $campaignsid != '') 
        {
            // validation audience status
            if (empty($leads->audience_status) ||  $leads->audience_status == 'progress')
                return response()->json(['result'=>'failed','msg'=>'Cannot play campaign while the audience is still processing.']);
            if ($leads->audience_status == 'error')
                return response()->json(['result'=>'failed','msg'=>'Cannot play campaign because of an audience error. Try updating the campaign again.']);
            // validation audience status

            // get lates budget
            $budget_plans_all = $this->SimpliFiService->get_budget_plans_campaign($campaignsid);
            $budget_plan_first = collect($budget_plans_all['budget_plans'] ?? [])->sortByDesc('start_date')->first(); // ambil yang paling terbaru
            $budget_plan_id = $budget_plan_first['id'] ?? "";
            $budget_start_date = date('Y-m-d',strtotime(($budget_plan_first['start_budget'] ?? "")));
            $budget_end_date = date('Y-m-d',strtotime(($budget_plan_first['end_date'] ?? "")));
            // get lates budget

            // check expired budget ketika budget sudah di beli
            $leadspeek_invoice_plan_ids = LeadspeekInvoice::where('leadspeek_api_id', $leads->leadspeek_api_id)
                                                          ->whereNotNull('budget_plan_id')
                                                          ->where('budget_plan_id', '<>', '')
                                                          ->pluck('budget_plan_id')
                                                          ->toArray();
            if(in_array($budget_plan_id, $leadspeek_invoice_plan_ids))
            {
                $isCampaignBudgetPaid = true;
                if($budget_end_date < date('Y-m-d'))
                {
                    return response()->json(array('result'=>'failed','msg'=>'Sorry, this campaign cannot be started, please update your budget in the financial icon before starting the campaign.'));
                }

                // update enddate campaign
                $leads->lp_enddate = $budget_end_date;
                $leads->save();
                // update enddate campaign
            }
            // check expired budget ketika budget sudah di beli

            // update cost module
            $dataRequest = new Request([
                'ClientID' => $request->leadspeekID,
                'LeadspeekMaxBid' => $leads->max_bid,
                'LeadspeekMonthlyBudget' => $leads->monthly_budget,
                'LeadspeekDailyBudget' => $leads->daily_budget,
                'LeadspeekGoalValue' => $leads->goal_value,
                'LeadspeekGoalType' => $leads->goal_type,
                'LeadspeekAgencyMarkup' => $leads->agency_markup,
            ]);
            $response = $this->SimplifiController->costmodule($dataRequest)->getData();
            // info(__FUNCTION__, ['response_costmodule' => $response]);
            if(($response->result ?? "") == 'error')
            {
                $message = $response->message ?? "Something Went Wrong"; 
                return response()->json(array('result'=>'failed','msg'=>$message));
            }
            // update cost module

            // update status in simplifi to active
            $response = $this->SimpliFiService->update_status_campaign($organizationid, $campaignsid, 'active');
            // info(__FUNCTION__, ['response_update_status_campaign 1' => $response]);
            if(($response['status'] ?? "") == 'error')
            {
                $message = $response['message'] ?? "Something Went Wrong";
                return response()->json(array('result'=>'failed','msg'=>$message));
            }
            // update status in simplifi to active
        }
        /* ACTIVE CAMPAIGN SIMPLIFI SITE TARGETING */

        /** CHECK IF START CAMPAIGN ON SIMPLI.FI STILL NOT EXPIRED */
        if ($leads->leadspeek_type == 'locator' && $campaignStatus == 'activate' && $updateclient[0]->clientcampaignsid != '') {
            $budgetplan = $this->getDefaultBudgetPlan($updateclient[0]->clientcampaignsid);
            if (count($budgetplan->budget_plans) > 0) {
                $count = count($budgetplan->budget_plans) - 1;
                $sifienddate = date('Ymd',strtotime($budgetplan->budget_plans[$count]->end_date));
                $_campaign_enddate = date('Y-m-d',strtotime(trim($updateclient[0]->campaign_enddate)));
                $_campaign_enddate_time = date('H:i:s',strtotime(trim($updateclient[0]->campaign_enddate)));

                if ($sifienddate < date('Ymd')) {
                    return response()->json(array('result'=>'failed','msg'=>'Sorry, this campaign can not be start, please check the campaign start and end date.'));
                }else{
                    if ($_campaign_enddate != trim($budgetplan->budget_plans[$count]->end_date)) {
                        $activateCampaignBudgetPlan = true;
                        $activateCampaignBudgetPlanDate = $budgetplan->budget_plans[$count]->end_date . ' ' . $_campaign_enddate_time;
                    }
                }

            }else{
                return response()->json(array('result'=>'failed','msg'=>'Sorry, this campaign can not be start, please contact your administrator with campaign ID : #' . $updateclient[0]->leadspeek_api_id . ' (internal 1)'));
            }
        }
        /** CHECK IF START CAMPAIGN ON SIMPLI.FI STILL NOT EXPIRED */

        /** ACTIVATE CAMPAIGN SIMPLIFI */
        if ($leads->leadspeek_type == 'locator' && $organizationid != '' && $campaignsid != '') {
            $camp = $this->startPause_campaign($organizationid,$campaignsid,$campaignStatus);
            if (!$camp) {
                $details = [
                    'errormsg'  => 'Error when trying to Start / Pause Campaign Organization ID : ' . $organizationid . ' Campaign ID :' . $campaignsid,
                ];
                $from = [
                    'address' => 'noreply@exactmatchmarketing.com',
                    'name' => 'Support',
                    'replyto' => 'support@exactmatchmarketing.com',
                ];
                $this->send_email(array('serverlogs@sitesettingsapi.com'),$from,'Error Log for ' . $campaignStatus . ' Get Campaign (Apps DATA - activepauseclient - L1462) ',$details,array(),'emails.tryseramatcherrorlog','');

                return response()->json(array('result'=>'failed','msg'=>'We apologize, but the process has failed. Please try again later.'));
                exit;die();
            }
        }
        /** ACTIVATE CAMPAIGN SIMPLIFI */

        /** IF CAMPAIGN SUCCESSFULLY ACTIVATE UPDATE THE BUDGET PLAN DATE */
        if ($activateCampaignBudgetPlan && $activateCampaignBudgetPlanDate != "") {
            $leaduserupdate = LeadspeekUser::find($updateclient[0]->id);
            $leaduserupdate->campaign_enddate = $activateCampaignBudgetPlanDate;
            $leaduserupdate->save();
        }
        /** IF CAMPAIGN SUCCESSFULLY ACTIVATE UPDATE THE BUDGET PLAN DATE */

        if ($updateclient[0]->trysera == 'T') {
            $this->update_leadspeek_api_client($updateclient[0]->leadspeek_api_id,$updateclient[0]->company_name,$tryseraCustomID,$status);
        }
        //$leads = LeadspeekUser::find($request->leadspeekID);

        // $organizationid = $leads->leadspeek_organizationid;
        // $campaignsid = $leads->leadspeek_campaignsid;

        $activeUser = $leads->active_user;

        $CampaignWasOnStopState = false;

        if ($leads->active == 'F' && $leads->disabled == 'T' && $leads->active_user == 'F') {
            $CampaignWasOnStopState = true;
        }

        $leads->active = ($request->status == 'T')?'F':'T';
        $leads->disabled = $request->status;
        if($activeUser != '' && $activeUser == 'F') {
            $leads->active_user = 'T';
        }

        if ($leads->leadspeek_type != 'predict' && $campaignStatus == 'activate' && $CampaignWasOnStopState === true) {
            $leads->start_billing_date = date('Y-m-d H:i:s');
        }

        $leads->save();

        /* IF PREDICT ID STOPS HERE, BECAUSE ITS PURPOSE IS ONLY TO CHANGE ITS STATUS */
        if ($leads->leadspeek_type == 'predict') {
            return response()->json(['result'=>'success','msg'=>'Campaign status updated successfully']);
        }
        /* IF PREDICT ID STOPS HERE, BECAUSE ITS PURPOSE IS ONLY TO CHANGE ITS STATUS */

        /* CHECK KEYWORD COUNT */
        // check_keyword_count berjalan dengan normal jika status nya dari pause ke play, namun jika status nya dari stop ke play di dijalankan di dalam function chargeClient setelah validasi charge
        if (in_array($leads->leadspeek_type, ['enhance','b2b']) && ($request->status == 'F' && $request->activeuser == 'T')) {
            $this->BigDBMController->check_keyword_count($leads->id);
        }
        /* CHECK KEYWORD COUNT */

        /** ACTIVATE CAMPAIGN SIMPLIFI */
        // if ($organizationid != '' && $campaignsid != '') {
        //     $camp = $this->startPause_campaign($organizationid,$campaignsid,$campaignStatus);
        // }
        /** ACTIVATE CAMPAIGN SIMPLIFI */

        //// ketika site id update $embedded_play = 1;
        if ($leads->leadspeek_type == 'local' && $campaignStatus == 'activate') {
            $lpupdate = LeadspeekUser::find($updateclient[0]->id);
            $lpupdate->embeddedcode_crawl = 'T';
            $lpupdate->embedded_play = 1;
            $lpupdate->save();
        }

        // ADD GHL TAG ON AGENCY's CONTACT
        if ((($request->status == 'F' && $request->activeuser == 'T') || ($request->status == 'F' && $request->activeuser == 'F'))) { // UPDATE IF CAMPAIGN PLAY
            $encryptionKey = substr(hash('sha256', env('APP_KEY')), 0, 16);
            $agency_user = User::select(
                        'id',
                        DB::raw("CONVERT(AES_DECRYPT(FROM_BASE64(email), '$encryptionKey') USING utf8mb4) AS user_email"),
                        DB::raw("CONVERT(AES_DECRYPT(FROM_BASE64(name), '$encryptionKey') USING utf8mb4) AS user_name"),
                        DB::raw("CONVERT(AES_DECRYPT(FROM_BASE64(phonenum), '$encryptionKey') USING utf8mb4) AS user_phone"),
                        'company_id',
                        'company_parent',
                        'company_root_id'
                        )
                        ->where('company_id','=',$request->companyID)
                        ->where('user_type','=','userdownline')
                        ->where('active','=','T')
                        ->first();    

            if (!empty($agency_user) && !empty($agency_user->user_email) && !empty($agency_user->company_root_id)) {

                $company_root_id = $agency_user->company_root_id;
                $root_ghl_setting = $this->getcompanysetting($company_root_id,'rootghlapikey');

                
                if (isset($root_ghl_setting->api_key) && !empty($root_ghl_setting->api_key) && $root_ghl_setting->api_key != '') {
                    $api_key = $root_ghl_setting->api_key;
                    try {
                        $param = [
                            'name' => $agency_user->user_name,
                            'email' => $agency_user->user_email,
                            'phone' => $agency_user->user_phone,
                        ];
                        $add_tag = $this->ghl_addContactTagsAgency($api_key,$param,['campaign-started']);                        
                    }catch (\GuzzleHttp\Exception\BadResponseException $e) {
                        $errorBody = (string) $e->getResponse()->getBody();
                        $decodedError = json_decode($errorBody, true);
                        Log::info([
                            'function' => 'activepauseclient',
                            'section' => 'campaign-started',
                            'success' => false,
                            'error' => $decodedError['msg'],
                        ]);
                    } catch (\Exception $e) {
                        Log::info([
                            'function' => 'activepauseclient',
                            'section' => 'campaign-started',
                            'success' => false,
                            'error' => $e->getMessage(),
                        ]);
                    }               
                }
            }
        }
        // ADD GHL TAG ON AGENCY's CONTACT
            
        /** IF THIS FIRST TIME ACTIVE THEN WILL CHARGE CLIENT FOR ONE TIME CREATIVE AND FIRST PLATFORM FEE */
        if($activeUser != '' && $activeUser == 'F') {
             /** CHARGE ONE TIME CREATIVE / SET UP FEE WHEN STATUS ACTIVATED (ONE TIME) */
            
                $usrInfo = User::select('id','customer_payment_id','customer_card_id','email','company_parent','company_id')
                                ->where('id','=',$userID)
                                ->where('active','=','T')
                                ->get();
                //if ($usrInfo[0]['paymentterm'] == 'One Time') {
                    $usrupdate = User::find($userID);
                    $usrupdate->lp_limit_startdate = date('Y-m-d');
                    $usrupdate->save();
                //}

                /** GET COMPANY PARENT NAME / AGENCY */
                $_AgencyManualBill = false;
                $getParentInfo = Company::select('company_name','manual_bill')->where('id','=',$updateclient[0]->company_parent)->get();
                if(count($getParentInfo) > 0) {
                    if ($getParentInfo[0]['manual_bill'] == 'T') {
                        $_AgencyManualBill = true;
                    }
                }
                /** GET COMPANY PARENT NAME / AGENCY */

                // if(count($usrInfo) > 0 && (($usrInfo[0]['customer_payment_id'] != '' && $usrInfo[0]['customer_card_id'] != '') || $_AgencyManualBill === true) && ($updateclient[0]->platformfee > 0 || $updateclient[0]->lp_min_cost_month > 0 || $updateclient[0]->paymentterm == 'One Time' || $updateclient[0]->paymentterm == 'Prepaid' || $updateclient[0]->leadspeek_type == 'simplifi')) {
                if(count($usrInfo) > 0 && (($usrInfo[0]['customer_payment_id'] != '' && $usrInfo[0]['customer_card_id'] != '') || $_AgencyManualBill === true)) {
                    if ($updateclient[0]->leadspeek_type == 'simplifi') { // jika simplifi
                        if (!$isCampaignBudgetPaid) { // jika plan belum di bayar
                            $totalFirstCharge = $updateclient[0]->monthly_budget;
                            $dataSimplifi = [
                                'budget_plan_id' => $budget_plan_id,
                                'budget_end_date' => $budget_end_date, 
                            ];

                            // if cost client zero
                            if ($totalFirstCharge <= 0) {
                                $leads->active = 'F';
                                $leads->disabled = 'T';
                                $leads->active_user = 'F';
                                $leads->save();
                                return response()->json(['result'=>'failed','msg'=>'cost client must be above $0']);
                            }
                            // if cost client zero

                            return $this->chargeClient($usrInfo[0]['customer_payment_id'],$usrInfo[0]['customer_card_id'],$usrInfo[0]['email'],$totalFirstCharge,$updateclient[0]->platformfee,$updateclient[0]->lp_min_cost_month,$updateclient,[],0,false,$dataSimplifi);
                        } else { // jika plan sudah di bayar check lagi apakah ada daily budget
                            try {
                                $dataRequest = new Request([
                                    'campaign_action_source' => 'from_active_campaign',
                                    'leadspeek_api_id' => $leadspeek_api_id,
                                ]);
                                $response = $this->SimplifiController->checkBudgetIncrease($dataRequest)->getData();
                                // info(__FUNCTION__. ' checkBudgetIncrease', ['response' => $response]);
                                $isCampaignBudgetIncreased = $response->is_campaign_budget_increased ?? false;

                                if ($isCampaignBudgetIncreased === true) {
                                    $costClientByRemainingDays = $response->cost_client_by_remaining_days ?? 0;
                                    $totalFirstCharge = $costClientByRemainingDays;       
                                    $dataSimplifi = [
                                        'chargeType' => 'increase_budget',
                                        'leadspeekMaxBid' => $leads->max_bid,
                                        'leadspeekMonthlyBudget' => $leads->monthly_budget,
                                        'leadspeekDailyBudget' => $leads->daily_budget,
                                        'leadspeekGoalValue' => $leads->goal_value,
                                        'leadspeekGoalType' => $leads->goal_type,
                                        'leadspeekAgencyMarkup' => $leads->agency_markup,
                                        'leadspeekRemainingDays' => $response->remaining_days ?? 0,
                                        'leadspeekTotalSpend' => $response->previous_total_spend ?? 0,
                                        'costClientByRemainingDays' => $costClientByRemainingDays,
                                        'costAgencyByRemainingDays' => $response->cost_agency_by_remaining_days ?? 0
                                    ];
                                    
                                    // if cost client zero
                                    if ($totalFirstCharge <= 0) {
                                        $leads->active = 'F';
                                        $leads->disabled = 'T';
                                        $leads->active_user = 'F';
                                        $leads->save();
                                        return response()->json(['result'=>'failed','msg'=>'cost client must be above $0']);
                                    }
                                    // if cost client zero

                                    // info(__FUNCTION__. ' before chargeClient', ['dataSimplifi' => $dataSimplifi]);
                                    return $this->chargeClient($usrInfo[0]['customer_payment_id'],$usrInfo[0]['customer_card_id'],$usrInfo[0]['email'],$totalFirstCharge,$updateclient[0]->platformfee,$updateclient[0]->lp_min_cost_month,$updateclient,[],0,false,$dataSimplifi);
                                } else {
                                    $this->UpsertReportAnalytics($leadspeek_api_id,$leads->leadspeek_type);
                                }
                            } catch (\Exception $e) {
                                return response()->json(array('result'=>'failed','msg'=>$e->getMessage()));
                            }
                        }
                    } else if ($updateclient[0]->paymentterm == 'Prepaid') { // jika prepaid
                        /** CHECK IF LEADSBUY ZERO SHOULD BE HAVE PROBLEM */
                        if ($leads->leadsbuy == '0' || $leads->leadsbuy == '') {
                            return response()->json(array('result'=>'failed','msg'=>'You cannot start the campaign until you have set up the campaign financials. Please click the dollar icon to set them up.'));
                            exit;die();
                        }
                        /** CHECK IF LEADSBUY ZERO SHOULD BE HAVE PROBLEM */

                        /** CHECK REMINING BALANCE TOP UP */
                        $remainingBalanceTotal = Topup::where('leadspeek_api_id','=',$leads->leadspeek_api_id)
                                                      ->sum('balance_leads');

                        $campaign = LeadspeekUser::where('leadspeek_api_id','=',$leads->leadspeek_api_id)->first();
                        $campaign->last_balance_leads = null; // reset last_balance_leads
                        $campaign->save();

                        /** CHECK IF TOP UP EVER BEEN CREATE */
                        $dataContinualNotCreated = Topup::where('leadspeek_api_id', $leads->leadspeek_api_id)
                                                         ->where('topupoptions', 'continual')
                                                         ->whereIn('topup_status', ['progress', 'queue'])
                                                         ->exists();
                        
                        // info(['topupoptions' => $leads->topupoptions,'remainingBalanceTotal' => $remainingBalanceTotal,'lp_limit_leads' => $campaign->lp_limit_leads,'dataContinualNotCreated' => $dataContinualNotCreated,'remainingBalanceTotal < lp_limit_leads' => ($remainingBalanceTotal < $campaign->lp_limit_leads)]);
                        if(($leads->topupoptions === 'continual' && ($remainingBalanceTotal < $campaign->lp_limit_leads || !$dataContinualNotCreated)) || ($leads->topupoptions === 'onetime')) {
                            /** FOR TOP UP PREPAID */
                            $topup_status = 'progress';
                            //check if campaign has any topup
                            $runningTopup = Topup::where('leadspeek_api_id','=',$leads->leadspeek_api_id)
                                                 ->where('topup_status', '=', 'progress')
                                                 ->get(); 
    
                            if (count($runningTopup) > 0 ) {
                                $topup_status = 'queue';
                            }
    
                            $data['user_id'] = $leads->user_id ?? '';
                            $data['lp_user_id'] = $leads->id ?? 0;
                            $data['company_id'] = $leads->company_id ?? 0;
                            $data['leadspeek_api_id'] = $leads->leadspeek_api_id ?? '';
                            $data['leadspeek_type'] = $leads->leadspeek_type ?? '';
                            $data['topupoptions'] = $leads->topupoptions ?? '';
                            $data['platformfee'] = $leads->platformfee ?? 0;
                            $data['cost_perlead'] = $leads->cost_perlead ?? 0;
                            $data['lp_limit_leads'] = $leads->lp_limit_leads ?? 0;
                            $data['lp_min_cost_month'] = $leads->lp_min_cost_month ?? 0;
                            $data['total_leads'] = $leads->leadsbuy ?? 0;
                            $data['balance_leads'] = $leads->leadsbuy ?? 0;
                            $data['treshold'] = $leads->lp_limit_leads ?? 0;
                            $data['payment_amount'] = '0';

                            $data['advance_information'] = '';
                            $data['campaign_information_type_local'] = '';
                            
                            $data['active'] = 'T';
                            $data['stop_continue'] = $leads->stopcontinual ?? 'F';
    
                            $data['last_cost_perlead'] = '0';
                            $data['last_limit_leads_day'] = '0';
                            $data['topup_status'] = $topup_status ?? '';
                            $data['platform_price'] = '0';
                            $data['root_price'] = '0';

                            $data['ip_user'] = $ip_user;
                            $data['timezone'] = $timezone;

                            /* GET COST AGENCY PRICE */
                            $companysetting = "";
                            $settingnameAgency = 'costagency';
                            $companyParent = $leads->company_id;
                            $getcompanysetting = CompanySetting::select('setting_value')
                                                               ->where('company_id', $companyParent)
                                                               ->whereEncrypted('setting_name', $settingnameAgency)
                                                               ->get();
                                                               
                            if (count($getcompanysetting) > 0) {
                                $companysetting = json_decode($getcompanysetting[0]['setting_value']);
                                if ($leads->leadspeek_type == 'local') {
                                    $rootcostagency = $this->getcompanysetting($idSys,'rootcostagency');
                                    $campaign_information_type_local = !empty($campaign->campaign_information_type_local) ? explode(',', $campaign->campaign_information_type_local) : [];
                                    
                                    $data['advance_information'] = $campaign->advance_information;
                                    $data['campaign_information_type_local'] = $campaign->campaign_information_type_local;

                                    if(in_array('advanced', $campaign_information_type_local)) {
                                        $data['platform_price'] = (isset($companysetting->local->Prepaid->LeadspeekCostperleadAdvanced))?($companysetting->local->Prepaid->LeadspeekCostperleadAdvanced):($rootcostagency->local->Prepaid->LeadspeekCostperleadAdvanced);
                                    } else {
                                        $data['platform_price'] = $companysetting->local->Prepaid->LeadspeekCostperlead;
                                    }
                                }else if ($leads->leadspeek_type == 'locator') {
                                    $data['platform_price'] = $companysetting->locator->Prepaid->LocatorCostperlead;
                                } else if($leads->leadspeek_type == 'enhance') {
                                    // $rootcostagency = []; 
                                    // if(!isset($platformMargin->enhance)) {
                                    //     $rootcostagency = $this->getcompanysetting($idSys,'rootcostagency');
                                    // }

                                    $clientTypeLead = $this->getClientCapType($idSys);
                                    $rootcostagency = $this->getcompanysetting($idSys,'rootcostagency');
                                    $costagency = $this->getcompanysetting($leads->company_id, 'costagency');

                                    /* VALIDATION COST PER LEAD UNDER COSTAGENCY AND COST PER LEAD IS GREATHER THAN ZERO */
                                    // info('',[
                                    //     'action' => 'data before',
                                    //     'leads' => $leads,
                                    //     'data' => $data
                                    // ]);
                                    if($leads->paymentterm == 'Weekly') {
                                        if(isset($costagency->enhance->Weekly->EnhanceCostperlead) && ($costagency->enhance->Weekly->EnhanceCostperlead != '') && ($costagency->enhance->Weekly->EnhanceCostperlead != 0) && ($leads->cost_perlead > 0) && ($leads->cost_perlead < $costagency->enhance->Weekly->EnhanceCostperlead)) {
                                            // info("VALIDATION COST PER LEAD UNDER COSTAGENCY activePauseClient {$leads->paymentterm}");
                                            $leads->cost_perlead = (float) $costagency->enhance->Weekly->EnhanceCostperlead;
                                            $data['cost_perlead'] = $leads->cost_perlead;
                                        }
                                    } else if($leads->paymentterm == 'Monthly') {
                                        if(isset($costagency->enhance->Monthly->EnhanceCostperlead) && ($costagency->enhance->Monthly->EnhanceCostperlead != '') && ($costagency->enhance->Monthly->EnhanceCostperlead != 0) && ($leads->cost_perlead > 0) && ($leads->cost_perlead < $costagency->enhance->Monthly->EnhanceCostperlead)) {
                                            // info("VALIDATION COST PER LEAD UNDER COSTAGENCY activePauseClient {$leads->paymentterm}");
                                            $leads->cost_perlead = (float) $costagency->enhance->Monthly->EnhanceCostperlead;
                                            $data['cost_perlead'] = $leads->cost_perlead;
                                        }
                                    } else if($leads->paymentterm == 'One Time') {
                                        if(isset($costagency->enhance->OneTime->EnhanceCostperlead) && ($costagency->enhance->OneTime->EnhanceCostperlead != '') && ($costagency->enhance->OneTime->EnhanceCostperlead != 0) && ($leads->cost_perlead > 0) && ($leads->cost_perlead < $costagency->enhance->OneTime->EnhanceCostperlead)) {
                                            // info("VALIDATION COST PER LEAD UNDER COSTAGENCY activePauseClient {$leads->paymentterm}");
                                            $leads->cost_perlead = (float) $costagency->enhance->OneTime->EnhanceCostperlead;
                                            $data['cost_perlead'] = $leads->cost_perlead;
                                        }
                                    } else if($leads->paymentterm == 'Prepaid') { 
                                        if(isset($costagency->enhance->Prepaid->EnhanceCostperlead) && ($costagency->enhance->Prepaid->EnhanceCostperlead != '') && ($costagency->enhance->Prepaid->EnhanceCostperlead != 0) && ($leads->cost_perlead > 0) && ($leads->cost_perlead < $costagency->enhance->Prepaid->EnhanceCostperlead)) {
                                            // info("VALIDATION COST PER LEAD UNDER COSTAGENCY activePauseClient {$leads->paymentterm}");
                                            $leads->cost_perlead = (float) $costagency->enhance->Prepaid->EnhanceCostperlead;
                                            $data['cost_perlead'] = $leads->cost_perlead;
                                        }
                                    }
                                    // info('',[
                                    //     'action' => 'data after',
                                    //     'leads' => $leads,
                                    //     'data' => $data
                                    // ]);
                                    /* VALIDATION COST PER LEAD UNDER COSTAGENCY AND COST PER LEAD IS GREATHER THAN ZERO */

                                    if($clientTypeLead['type'] == 'clientcapleadpercentage') {

                                        if($leads->paymentterm == 'Weekly') {
                                            // Log::info("activepauseclient block weekly");
                                            $m_LeadspeekCostperlead = ($leads->cost_perlead * $clientTypeLead['value']) / 100;
                                            // $rootCostPerLeadMin = ($costagency->enhance->Weekly->EnhanceCostperlead > $rootcostagency->enhance->Weekly->EnhanceCostperlead) ? $costagency->enhance->Weekly->EnhanceCostperlead : $rootcostagency->enhance->Weekly->EnhanceCostperlead;
                                            $rootCostPerLeadMin = $costagency->enhance->Weekly->EnhanceCostperlead;
                                            $rootCostPerLeadMax = ($rootCostPerLeadMin) / ($clientTypeLead['value'] / 100);

                                            // jika cost_perlead 0 atau m_LeadspeekCostperlead berada di rentang $rootCostPerLeadMin ~ $rootCostPerLeadMax
                                            if(($leads->cost_perlead == 0) || ($leads->cost_perlead <= $rootCostPerLeadMax && $leads->cost_perlead >= $rootCostPerLeadMin)) { 
                                                $data['platform_price'] = $rootCostPerLeadMin;
                                            }
                                            // jika lebih dari $rootCostPerLeadMax, maka dinamis
                                            else if($leads->cost_perlead > $rootCostPerLeadMax) {
                                                $data['platform_price'] = $m_LeadspeekCostperlead;
                                            }
                                            else {
                                                $data['platform_price'] = $rootCostPerLeadMin;
                                            }                                
                                        } else if($leads->paymentterm == 'Monthly') {
                                            // Log::info("activepauseclient block monthly");
                                            $m_LeadspeekCostperlead = ($leads->cost_perlead * $clientTypeLead['value']) / 100;
                                            // $rootCostPerLeadMin = ($costagency->enhance->Monthly->EnhanceCostperlead > $rootcostagency->enhance->Monthly->EnhanceCostperlead) ? $costagency->enhance->Monthly->EnhanceCostperlead : $rootcostagency->enhance->Monthly->EnhanceCostperlead;
                                            $rootCostPerLeadMin = $costagency->enhance->Monthly->EnhanceCostperlead;
                                            $rootCostPerLeadMax = ($rootCostPerLeadMin) / ($clientTypeLead['value'] / 100);

                                            // jika cost_perlead 0 atau m_LeadspeekCostperlead berada di rentang $rootCostPerLeadMin ~ $rootCostPerLeadMax
                                            if(($leads->cost_perlead == 0) || ($leads->cost_perlead <= $rootCostPerLeadMax && $leads->cost_perlead >= $rootCostPerLeadMin)) { 
                                                $data['platform_price'] = $rootCostPerLeadMin;
                                            }
                                            // jika lebih dari $rootCostPerLeadMax, maka dinamis
                                            else if($leads->cost_perlead > $rootCostPerLeadMax) {
                                                $data['platform_price'] = $m_LeadspeekCostperlead;
                                            }
                                            else {
                                                $data['platform_price'] = $rootCostPerLeadMin;
                                            }
                                        } else if($leads->paymentterm == 'One Time') {
                                            // Log::info("activepauseclient block onetime");
                                            $m_LeadspeekCostperlead = ($leads->cost_perlead * $clientTypeLead['value']) / 100;
                                            // $rootCostPerLeadMin = ($costagency->enhance->OneTime->EnhanceCostperlead > $rootcostagency->enhance->OneTime->EnhanceCostperlead) ? $costagency->enhance->OneTime->EnhanceCostperlead : $rootcostagency->enhance->OneTime->EnhanceCostperlead;
                                            $rootCostPerLeadMin = $costagency->enhance->OneTime->EnhanceCostperlead;
                                            $rootCostPerLeadMax = ($rootCostPerLeadMin) / ($clientTypeLead['value'] / 100);

                                            // jika cost_perlead 0 atau m_LeadspeekCostperlead berada di rentang $rootCostPerLeadMin ~ $rootCostPerLeadMax
                                            if(($leads->cost_perlead == 0) || ($leads->cost_perlead <= $rootCostPerLeadMax && $leads->cost_perlead >= $rootCostPerLeadMin)) { 
                                                $data['platform_price'] = $rootCostPerLeadMin;
                                            }
                                            // jika lebih dari $rootCostPerLeadMax, maka dinamis
                                            else if($leads->cost_perlead > $rootCostPerLeadMax) {
                                                $data['platform_price'] = $m_LeadspeekCostperlead;
                                            }
                                            else {
                                                $data['platform_price'] = $rootCostPerLeadMin;
                                            }
                                        } else if($leads->paymentterm == 'Prepaid') {
                                            // Log::info("activepauseclient block prepaid");
                                            $m_LeadspeekCostperlead = ($leads->cost_perlead * $clientTypeLead['value']) / 100;
                                            // $rootCostPerLeadMin = ($costagency->enhance->Prepaid->EnhanceCostperlead > $rootcostagency->enhance->Prepaid->EnhanceCostperlead) ? $costagency->enhance->Prepaid->EnhanceCostperlead : $rootcostagency->enhance->Prepaid->EnhanceCostperlead;
                                            $rootCostPerLeadMin = $costagency->enhance->Prepaid->EnhanceCostperlead;
                                            $rootCostPerLeadMax = ($rootCostPerLeadMin) / ($clientTypeLead['value'] / 100);

                                            // jika cost_perlead 0 atau m_LeadspeekCostperlead berada di rentang $rootCostPerLeadMin ~ $rootCostPerLeadMax
                                            if(($leads->cost_perlead == 0) || ($leads->cost_perlead <= $rootCostPerLeadMax && $leads->cost_perlead >= $rootCostPerLeadMin)) {
                                                $data['platform_price'] = $rootCostPerLeadMin;
                                            }
                                            // jika lebih dari $rootCostPerLeadMax, maka dinamis
                                            else if($leads->cost_perlead > $rootCostPerLeadMax) {
                                                $data['platform_price'] = $m_LeadspeekCostperlead;
                                            }
                                            else {
                                                $data['platform_price'] = $rootCostPerLeadMin;
                                            }
                                        }
                                    } else {
                                        $data['platform_price'] = (isset($companysetting->enhance->Prepaid->EnhanceCostperlead))?$companysetting->enhance->Prepaid->EnhanceCostperlead:$rootcostagency->enhance->Prepaid->EnhanceCostperlead;
                                    }
                                } else if($leads->leadspeek_type == 'b2b') {
                                    // $rootcostagency = []; 
                                    // if(!isset($platformMargin->b2b)) {
                                    //     $rootcostagency = $this->getcompanysetting($idSys,'rootcostagency');
                                    // }

                                    $clientTypeLead = $this->getClientCapType($idSys);
                                    $rootcostagency = $this->getcompanysetting($idSys,'rootcostagency');
                                    $costagency = $this->getcompanysetting($leads->company_id, 'costagency');

                                    /* VALIDATION COST PER LEAD UNDER COSTAGENCY */
                                    // info('',[
                                    //     'action' => 'data before',
                                    //     'leads' => $leads->cost_perlead,
                                    //     'data' => $data['cost_perlead']
                                    // ]);
                                    if($leads->paymentterm == 'Weekly') {              
                                        $m_B2bCostperlead = (isset($costagency->b2b->Weekly->B2bCostperlead)) ? ($costagency->b2b->Weekly->B2bCostperlead) : ($rootcostagency->b2b->Weekly->B2bCostperlead);
                                        if(($m_B2bCostperlead != '') && ($m_B2bCostperlead != 0) && ($leads->cost_perlead > 0) && ($leads->cost_perlead < $m_B2bCostperlead)) {
                                            // info("VALIDATION COST PER LEAD UNDER COSTAGENCY activePauseClient {$leads->paymentterm}");
                                            $leads->cost_perlead = (float) $m_B2bCostperlead;
                                            $data['cost_perlead'] = $leads->cost_perlead;
                                        }
                                    } else if($leads->paymentterm == 'Monthly') {
                                        $m_B2bCostperlead = (isset($costagency->b2b->Monthly->B2bCostperlead)) ? ($costagency->b2b->Monthly->B2bCostperlead) : ($rootcostagency->b2b->Monthly->B2bCostperlead);
                                        if(($m_B2bCostperlead != '') && ($m_B2bCostperlead != 0) && ($leads->cost_perlead > 0) && ($leads->cost_perlead < $m_B2bCostperlead)) {
                                            // info("VALIDATION COST PER LEAD UNDER COSTAGENCY activePauseClient {$leads->paymentterm}");
                                            $leads->cost_perlead = (float) $m_B2bCostperlead;
                                            $data['cost_perlead'] = $leads->cost_perlead;
                                        }
                                    } else if($leads->paymentterm == 'One Time') {
                                        $m_B2bCostperlead = (isset($costagency->b2b->OneTime->B2bCostperlead)) ? ($costagency->b2b->OneTime->B2bCostperlead) : ($rootcostagency->b2b->OneTime->B2bCostperlead);
                                        if(($m_B2bCostperlead != '') && ($m_B2bCostperlead != 0) && ($leads->cost_perlead > 0) && ($leads->cost_perlead < $m_B2bCostperlead)) {
                                            // info("VALIDATION COST PER LEAD UNDER COSTAGENCY activePauseClient {$leads->paymentterm}");
                                            $leads->cost_perlead = (float) $m_B2bCostperlead;
                                            $data['cost_perlead'] = $leads->cost_perlead;
                                        }
                                    } else if($leads->paymentterm == 'Prepaid') { 
                                        $m_B2bCostperlead = (isset($costagency->b2b->Prepaid->B2bCostperlead)) ? ($costagency->b2b->Prepaid->B2bCostperlead) : ($rootcostagency->b2b->Prepaid->B2bCostperlead);
                                        if(($m_B2bCostperlead != '') && ($m_B2bCostperlead != 0) && ($leads->cost_perlead > 0) && ($leads->cost_perlead < $m_B2bCostperlead)) {
                                            // info("VALIDATION COST PER LEAD UNDER COSTAGENCY activePauseClient {$leads->paymentterm}");
                                            $leads->cost_perlead = (float) $m_B2bCostperlead;
                                            $data['cost_perlead'] = $leads->cost_perlead;
                                        }
                                    }
                                    // info('',[
                                    //     'action' => 'data after',
                                    //     'leads' => $leads->cost_perlead,
                                    //     'data' => $data['cost_perlead']
                                    // ]);
                                    /* VALIDATION COST PER LEAD UNDER COSTAGENCY */

                                    if($clientTypeLead['type'] == 'clientcapleadpercentage') {

                                        if($leads->paymentterm == 'Weekly') {
                                            // Log::info("activepauseclient block weekly");
                                            $m_LeadspeekCostperlead = ($leads->cost_perlead * $clientTypeLead['value']) / 100;
                                            // $rootCostPerLeadMin = (isset($costagency->b2b->Weekly->B2bCostperlead) && ($costagency->b2b->Weekly->B2bCostperlead > $rootcostagency->b2b->Weekly->B2bCostperlead)) ? $costagency->b2b->Weekly->B2bCostperlead : $rootcostagency->b2b->Weekly->B2bCostperlead;
                                            $rootCostPerLeadMin = (isset($costagency->b2b->Weekly->B2bCostperlead)) ? ($costagency->b2b->Weekly->B2bCostperlead) : ($rootcostagency->b2b->Weekly->B2bCostperlead);
                                            $rootCostPerLeadMax = ($rootCostPerLeadMin) / ($clientTypeLead['value'] / 100);

                                            // Log::info([
                                            //     'action' => 'clientcapleadpercentage in activepauseclient',
                                            //     'rootCostPerLeadMin' => $rootCostPerLeadMin,
                                            //     'rootCostPerLeadMax' => $rootCostPerLeadMax
                                            // ]);

                                            // jika cost_perlead 0 atau m_LeadspeekCostperlead berada di rentang $rootCostPerLeadMin ~ $rootCostPerLeadMax
                                            if(($leads->cost_perlead == 0) || ($leads->cost_perlead <= $rootCostPerLeadMax && $leads->cost_perlead >= $rootCostPerLeadMin)) { 
                                                $data['platform_price'] = $rootCostPerLeadMin;
                                            }
                                            // jika lebih dari $rootCostPerLeadMax, maka dinamis
                                            else if($leads->cost_perlead > $rootCostPerLeadMax) {
                                                $data['platform_price'] = $m_LeadspeekCostperlead;
                                            }
                                            else {
                                                $data['platform_price'] = $rootCostPerLeadMin;
                                            }                                
                                        } else if($leads->paymentterm == 'Monthly') {
                                            $m_LeadspeekCostperlead = ($leads->cost_perlead * $clientTypeLead['value']) / 100;
                                            // $rootCostPerLeadMin = (isset($costagency->b2b->Monthly->B2bCostperlead) && ($costagency->b2b->Monthly->B2bCostperlead > $rootcostagency->b2b->Monthly->B2bCostperlead)) ? $costagency->b2b->Monthly->B2bCostperlead : $rootcostagency->b2b->Monthly->B2bCostperlead;
                                            $rootCostPerLeadMin = (isset($costagency->b2b->Monthly->B2bCostperlead)) ? ($costagency->b2b->Monthly->B2bCostperlead) : ($rootcostagency->b2b->Monthly->B2bCostperlead);
                                            $rootCostPerLeadMax = ($rootCostPerLeadMin) / ($clientTypeLead['value'] / 100);

                                            // Log::info([
                                            //     'action' => 'clientcapleadpercentage in activepauseclient',
                                            //     'rootCostPerLeadMin' => $rootCostPerLeadMin,
                                            //     'rootCostPerLeadMax' => $rootCostPerLeadMax
                                            // ]);

                                            // jika cost_perlead 0 atau m_LeadspeekCostperlead berada di rentang $rootCostPerLeadMin ~ $rootCostPerLeadMax
                                            if(($leads->cost_perlead == 0) || ($leads->cost_perlead <= $rootCostPerLeadMax && $leads->cost_perlead >= $rootCostPerLeadMin)) { 
                                                $data['platform_price'] = $rootCostPerLeadMin;
                                            }
                                            // jika lebih dari $rootCostPerLeadMax, maka dinamis
                                            else if($leads->cost_perlead > $rootCostPerLeadMax) {
                                                $data['platform_price'] = $m_LeadspeekCostperlead;
                                            }
                                            else {
                                                $data['platform_price'] = $rootCostPerLeadMin;
                                            }
                                        } else if($leads->paymentterm == 'One Time') {
                                            $m_LeadspeekCostperlead = ($leads->cost_perlead * $clientTypeLead['value']) / 100;
                                            // $rootCostPerLeadMin = (isset($costagency->b2b->OneTime->B2bCostperlead) && ($costagency->b2b->OneTime->B2bCostperlead > $rootcostagency->b2b->OneTime->B2bCostperlead)) ? $costagency->b2b->OneTime->B2bCostperlead : $rootcostagency->b2b->OneTime->B2bCostperlead;
                                            $rootCostPerLeadMin = (isset($costagency->b2b->OneTime->B2bCostperlead)) ? ($costagency->b2b->OneTime->B2bCostperlead) : ($rootcostagency->b2b->OneTime->B2bCostperlead);
                                            $rootCostPerLeadMax = ($rootCostPerLeadMin) / ($clientTypeLead['value'] / 100);

                                            // Log::info([
                                            //     'action' => 'clientcapleadpercentage in activepauseclient',
                                            //     'rootCostPerLeadMin' => $rootCostPerLeadMin,
                                            //     'rootCostPerLeadMax' => $rootCostPerLeadMax
                                            // ]);

                                            // jika cost_perlead 0 atau m_LeadspeekCostperlead berada di rentang $rootCostPerLeadMin ~ $rootCostPerLeadMax
                                            if(($leads->cost_perlead == 0) || ($leads->cost_perlead <= $rootCostPerLeadMax && $leads->cost_perlead >= $rootCostPerLeadMin)) { 
                                                $data['platform_price'] = $rootCostPerLeadMin;
                                            }
                                            // jika lebih dari $rootCostPerLeadMax, maka dinamis
                                            else if($leads->cost_perlead > $rootCostPerLeadMax) {
                                                $data['platform_price'] = $m_LeadspeekCostperlead;
                                            }
                                            else {
                                                $data['platform_price'] = $rootCostPerLeadMin;
                                            }
                                        } else if($leads->paymentterm == 'Prepaid') {
                                            // Log::info("activepauseclient block prepaid");
                                            $m_LeadspeekCostperlead = ($leads->cost_perlead * $clientTypeLead['value']) / 100;
                                            // $rootCostPerLeadMin = (isset($costagency->b2b->Prepaid->B2bCostperlead) && ($costagency->b2b->Prepaid->B2bCostperlead > $rootcostagency->b2b->Prepaid->B2bCostperlead)) ? $costagency->b2b->Prepaid->B2bCostperlead : $rootcostagency->b2b->Prepaid->B2bCostperlead;
                                            $rootCostPerLeadMin = (isset($costagency->b2b->Prepaid->B2bCostperlead)) ? ($costagency->b2b->Prepaid->B2bCostperlead) : ($rootcostagency->b2b->Prepaid->B2bCostperlead);
                                            $rootCostPerLeadMax = ($rootCostPerLeadMin) / ($clientTypeLead['value'] / 100);

                                            // Log::info([
                                            //     'action' => 'clientcapleadpercentage in activepauseclient',
                                            //     'rootCostPerLeadMin' => $rootCostPerLeadMin,
                                            //     'rootCostPerLeadMax' => $rootCostPerLeadMax
                                            // ]);

                                            // jika cost_perlead 0 atau m_LeadspeekCostperlead berada di rentang $rootCostPerLeadMin ~ $rootCostPerLeadMax
                                            if(($leads->cost_perlead == 0) || ($leads->cost_perlead <= $rootCostPerLeadMax && $leads->cost_perlead >= $rootCostPerLeadMin)) {
                                                $data['platform_price'] = $rootCostPerLeadMin;
                                            }
                                            // jika lebih dari $rootCostPerLeadMax, maka dinamis
                                            else if($leads->cost_perlead > $rootCostPerLeadMax) {
                                                $data['platform_price'] = $m_LeadspeekCostperlead;
                                            }
                                            else {
                                                $data['platform_price'] = $rootCostPerLeadMin;
                                            }
                                        }
                                    } else {
                                        $data['platform_price'] = (isset($companysetting->b2b->Prepaid->B2bCostperlead))?$companysetting->b2b->Prepaid->B2bCostperlead:$rootcostagency->b2b->Prepaid->B2bCostperlead;
                                    }
                                }
                            }

                            // info([
                            //     'action' => 'platform_price activepauseclient',
                            //     'platform_price' => $data['platform_price']
                            // ]);
                            /* GET COST AGENCY PRICE */
                            
                            /** GET ROOT FEE PER LEADS FROM SUPER ROOT */
                            $masterRootFee = $this->getcompanysetting($updateclient[0]->company_root_id,'rootfee');
                            if ($masterRootFee != '') {
                                if ($leads->leadspeek_type == 'local') {
                                    $data['root_price'] = (isset($masterRootFee->feesiteid))?$masterRootFee->feesiteid:0;
                                }else if ($leads->leadspeek_type == 'locator') {
                                    $data['root_price'] = (isset($masterRootFee->feesearchid))?$masterRootFee->feesearchid:0;
                                }else if ($leads->leadspeek_type == 'enhance') {
                                    $data['root_price'] = (isset($masterRootFee->feeenhance))?$masterRootFee->feeenhance:0;
                                }else if ($leads->leadspeek_type == 'b2b') {
                                    $data['root_price'] = (isset($masterRootFee->feeb2b))?$masterRootFee->feeb2b:0;
                                }
                            }
                            /** GET ROOT FEE PER LEADS FROM SUPER ROOT */

                            //$addTopUp = Topup::create($data);
                            /** FOR TOP UP PREPAID */

                            /** CHARGE CLIENT FIRST TIME PLAY */
                            $totalFirstCharge = ($data['cost_perlead'] * $data['total_leads']) + $data['platformfee'];
                            $_platformFee = ($data['platform_price'] * $data['total_leads']);
                            $_rootPrice = ($data['root_price'] * $data['total_leads']);

                            // Log::info('', [
                            //     'platform_price' => $data['platform_price'],
                            //     'total_leads' => $data['total_leads'],
                            //     '_platformFee' => $_platformFee, 
                            //     '_rootPrice' => $_rootPrice,
                            //     'data' => $data,
                            // ]);

                            return $this->chargeClient($usrInfo[0]['customer_payment_id'],$usrInfo[0]['customer_card_id'],$usrInfo[0]['email'],$totalFirstCharge,$updateclient[0]->platformfee,$_platformFee,$updateclient,$data,$_rootPrice,true);
                            /** CHARGE CLIENT FIRST TIME PLAY */
                        } 

                    } else { // jika weekly or monthly
                        /** PUT FORMULA FOR PLATFORM FEE CALCULATION */
                        $totalFirstCharge = $updateclient[0]->platformfee + $updateclient[0]->lp_min_cost_month;
                        return $this->chargeClient($usrInfo[0]['customer_payment_id'],$usrInfo[0]['customer_card_id'],$usrInfo[0]['email'],$totalFirstCharge,$updateclient[0]->platformfee,$updateclient[0]->lp_min_cost_month,$updateclient);
                    }
                }
            
            /** CHARGE ONE TIME CREATIVE / SET UP FEE WHEN STATUS ACTIVATED (ONE TIME) */
            
        }
        /** IF THIS FIRST TIME ACTIVE THEN WILL CHARGE CLIENT FOR ONE TIME CREATIVE AND FIRST PLATFORM FEE */
    }

    public function root_fee_commission($usrInfo,$stripeseckey,$companyName,$_leadspeek_api_id,$rootFee = 0,$platformfee_ori = 0) 
    {
        /** CHARGE ROOT FEE AGENCY */
        if($rootFee != "0" && $rootFee != "")
        {
            /** GET ROOT CONNECTED ACCOUNT TO BE TRANSFER FOR CLEAN PROFIT AFTER CUT BY ROOT FEE COST */
            $stripe = new StripeClient([
                'api_key' => $stripeseckey,
                'stripe_version' => '2020-08-27'
            ]);

            $todayDate = date('Y-m-d H:i:s');
            $platformfeeEmm = $platformfee_ori;

            $cleanProfit = $platformfee_ori - $rootFee;
            $cleanProfit = number_format($cleanProfit,2,'.','');
            
            $rootAccConResult = $this->getcompanysetting($usrInfo->company_root_id,'rootfee');
            $rootAccCon = "";
            $rootAccConMob = "";
            $rootCommissionSRAcc = "";
            $rootCommissionAEAcc = "";

            $rootCommissionFee = ($usrInfo->leadspeek_type == 'enhance' || $usrInfo->leadspeek_type == 'b2b')?($platformfee_ori * 0.05):($rootFee * 0.05);
            $rootCommissionFee = number_format($rootCommissionFee,2,'.','');
            $rootCommissionSRAccVal = $rootCommissionFee;
            $rootCommissionAEAccVal = $rootCommissionFee;
            /** GET ROOT CONNECTED ACCOUNT TO BE TRANSFER FOR CLEAN PROFIT AFTER CUT BY ROOT FEE COST */

            /** OVERRIDE PLATFORMFEE IF ENHANCE,B2B AND SALES SR AND SALES AE */
            // Log::info(['action' => 'start root_fee_commission','usrInfo->leadspeek_type' => $usrInfo->leadspeek_type,'rootAccConResult' => $rootAccConResult,'isset($rootAccConResult->rootcomfee)' => isset($rootAccConResult->rootcomfee),'isset($rootAccConResult->rootcomfee1)' => isset($rootAccConResult->rootcomfee1),]);
            if ($rootAccConResult != '') 
            {
                $rootAccCon = (isset($rootAccConResult->rootfeeaccid))?$rootAccConResult->rootfeeaccid:"";
                $rootAccConMob = (isset($rootAccConResult->rootfeeaccidmob))?$rootAccConResult->rootfeeaccidmob:"";
                $rootCommissionSRAcc = (isset($rootAccConResult->rootcomsr))?$rootAccConResult->rootcomsr:"";
                $rootCommissionAEAcc = (isset($rootAccConResult->rootcomae))?$rootAccConResult->rootcomae:"";

                if ($usrInfo->leadspeek_type == 'enhance' || $usrInfo->leadspeek_type == 'b2b') 
                {
                    if((isset($rootAccConResult->feepercentagemob) && $rootAccConResult->feepercentagemob != "") || (isset($rootAccConResult->feepercentagedom) && $rootAccConResult->feepercentagedom != "")) 
                    {
                        $feePercentageEmm = (isset($rootAccConResult->feepercentageemm))?$rootAccConResult->feepercentageemm:0;
                        $platformfeeEmm = ($platformfeeEmm * $feePercentageEmm) / 100;
                        $platformfeeEmm = number_format($platformfeeEmm,2,'.','');
                        // Log::info(['action' => "override platformfeeEmm when {$usrInfo->leadspeek_type}",'feePercentageEmm' => "{$feePercentageEmm}%",'platformfeeEmm' => $platformfeeEmm,'platformfee_ori' => $platformfee_ori,]);
                    }
                    if (isset($rootAccConResult->rootcomfee) && $rootAccConResult->rootcomfee != "") 
                    {
                        $rootCommissionSRAcc = $rootAccConResult->rootcomfee;
                        $rootAccConResult->rootcomsrval = $rootAccConResult->rootcomfeeval;
                        // Log::info(['action' => "override rootCommissionSRAcc when {$usrInfo->leadspeek_type}",'rootCommissionSRAcc' => $rootCommissionSRAcc,'rootAccConResult->rootcomsrval' => $rootAccConResult->rootcomsrval]);
                    }
                    if (isset($rootAccConResult->rootcomfee1) && $rootAccConResult->rootcomfee1 != "") 
                    {
                        $rootCommissionAEAcc = $rootAccConResult->rootcomfee1;
                        $rootAccConResult->rootcomaeval = $rootAccConResult->rootcomfeeval1;
                        // Log::info(['action' => "override rootCommissionAEAcc when {$usrInfo->leadspeek_type}",'rootCommissionAEAcc' => $rootCommissionAEAcc,'rootAccConResult->rootcomaeval' => $rootAccConResult->rootcomaeval,]);
                    }
                }
                if (isset($rootAccConResult->rootcomsrval) && $rootAccConResult->rootcomsrval != "") 
                {
                    $_rootFee = ($usrInfo->leadspeek_type == 'enhance' || $usrInfo->leadspeek_type == 'b2b')?$platformfeeEmm:$rootFee;
                    $rootCommissionSRAccVal = ($_rootFee * (float) $rootAccConResult->rootcomsrval);
                    $rootCommissionSRAccVal = number_format($rootCommissionSRAccVal,2,'.','');
                    // Log::info(['action' => 'calculate rootCommissionSRAccVal','_rootFee' => $_rootFee,'platformfeeEmm' => $platformfeeEmm,'rootFee' => $rootFee,'$rootAccConResult->rootcomsrval' => $rootAccConResult->rootcomsrval,'rootCommissionSRAccVal' => $rootCommissionSRAccVal,]);
                }
                if (isset($rootAccConResult->rootcomaeval) && $rootAccConResult->rootcomaeval != "") 
                {
                    $_rootFee = ($usrInfo->leadspeek_type == 'enhance' || $usrInfo->leadspeek_type == 'b2b')?$platformfeeEmm:$rootFee;
                    $rootCommissionAEAccVal = ($_rootFee * (float) $rootAccConResult->rootcomaeval);
                    $rootCommissionAEAccVal = number_format($rootCommissionAEAccVal,2,'.','');
                    // Log::info(['action' => 'calculate rootCommissionAEAccVal','_rootFee' => $_rootFee,'platformfeeEmm' => $platformfeeEmm,'rootFee' => $rootFee,'$rootAccConResult->rootcomaeval' => $rootAccConResult->rootcomaeval,'rootCommissionAEAccVal' => $rootCommissionAEAccVal,]);
                }
            }

            /** TRANSFER FOR ROOT COMMISSION */
            if ($rootAccCon != "") 
            {
                try 
                {
                    if($usrInfo->leadspeek_type == "enhance" || $usrInfo->leadspeek_type == 'b2b') 
                    {
                        // if root mobile
                        if(isset($rootAccConResult->feepercentagemob) && $rootAccConResult->feepercentagemob != "") 
                        {
                            // calculation cleanProfit for mobile
                            $feePercentageMob = (isset($rootAccConResult->feepercentagemob))?$rootAccConResult->feepercentagemob:0;
                            $cleanProfitMob = ($platformfee_ori * $feePercentageMob) / 100;
                            $cleanProfitMob = number_format($cleanProfitMob,2,'.','');
                            
                            if($cleanProfitMob >= 0.5) 
                            {
                                // Log::info(['action' => "TRANSFER FOR ROOT MOBILE WHEN {$usrInfo->leadspeek_type}",'rootAccConMob' => $rootAccConMob,'feePercentageMob' => "{$feePercentageMob}%",'cleanProfitMob' => $cleanProfitMob,]);
                                // send cleanProfit to mobile
                                $transferRootProfit = $stripe->transfers->create([
                                    'amount' => ($cleanProfitMob * 100),
                                    'currency' => 'usd',
                                    'destination' => $rootAccConMob,
                                    'description' => 'Profit Root App from Invoice ' . $companyName . ' #' . $_leadspeek_api_id . ' (' . date('m-d-Y',strtotime($todayDate)) . ')',
                                ]);
                            }
                        }
                        if(isset($rootAccConResult->feepercentagedom) && $rootAccConResult->feepercentagedom != "") 
                        {
                            // calculation cleanProfit for dominator
                            $feePercentageDom = (isset($rootAccConResult->feepercentagedom))?$rootAccConResult->feepercentagedom:0;
                            $cleanProfitDom = ($platformfee_ori * $feePercentageDom) / 100;
                            $cleanProfitDom = number_format($cleanProfitDom,2,'.','');
                            
                            if($cleanProfitDom >= 0.5)
                            {
                                // Log::info(['action' => "TRANSFER FOR ROOT DOMINATOR WHEN {$usrInfo->leadspeek_type}",'rootAccCon' => $rootAccCon,'feePercentageDom' => "{$feePercentageDom}%",'cleanProfitDom' => $cleanProfitDom,]);
                                // send cleanProfit to dominator
                                $transferRootProfit = $stripe->transfers->create([
                                    'amount' => ($cleanProfitDom * 100),
                                    'currency' => 'usd',
                                    'destination' => $rootAccCon,
                                    'description' => 'Profit Root App from Invoice ' . $companyName . ' #' . $_leadspeek_api_id . ' (' . date('m-d-Y',strtotime($todayDate)) . ')',
                                ]);
                            }
                        }
                    }
                    else if($cleanProfit >= 0.5) 
                    {
                        // Log::info(['action' => "TRANSFER FOR ROOT " . (isset($rootAccConResult->feepercentagemob) && $rootAccConResult->feepercentagemob != "" ? "MOBILE" : "DOMINATOR") . " WHEN {$usrInfo->leadspeek_type}",'amount' => ($cleanProfit * 100),'currency' => 'usd','destination' => $rootAccCon,'description' => 'Profit Root App from Invoice ' . $companyName . ' #' . $_leadspeek_api_id . ' (' . date('m-d-Y',strtotime($todayDate)) . ')',]);
                        $transferRootProfit = $stripe->transfers->create([
                            'amount' => ($cleanProfit * 100),
                            'currency' => 'usd',
                            'destination' => $rootAccCon,
                            'description' => 'Profit Root App from Invoice ' . $companyName . ' #' . $_leadspeek_api_id . ' (' . date('m-d-Y',strtotime($todayDate)) . ')',
                        ]);
                    }
                }
                catch (Exception $e) 
                {
                    $this->send_notif_stripeerror('Profit Root Transfer Error','Profit Root Transfer Error to ' . $rootAccCon ,$usrInfo->company_root_id);
                }
            }
            /** TRANSFER FOR ROOT COMMISSION */

            /** TRANSFER FOR ROOT SALES Representative */
            if ($rootCommissionSRAcc != "" && $rootCommissionSRAccVal >= 0.5) 
            {
                try 
                {
                    // Log::info(['action' => 'TRANSFER FOR ROOT SALES Representative','amount' => ($rootCommissionSRAccVal * 100),'currency' => 'usd','destination' => $rootCommissionSRAcc,'description' => 'Commision Root app from  Invoice ' . $companyName . ' #' . $_leadspeek_api_id . ' (' . date('m-d-Y',strtotime($todayDate)) . ' )',]);
                    $transferRootProfitSRAcc = $stripe->transfers->create([
                        'amount' => ($rootCommissionSRAccVal * 100),
                        'currency' => 'usd',
                        'destination' => $rootCommissionSRAcc,
                        'description' => 'Commision Root app from  Invoice ' . $companyName . ' #' . $_leadspeek_api_id . ' (' . date('m-d-Y',strtotime($todayDate)) . ')',
                    ]);
                    //$this->send_email(array($sale['email']),$from,'Commission fee from ' . $sale['company_name'] . ' #' . $_leadspeek_api_id ,$details,$attachement,'emails.salesfee',$companyParentID);
                }
                catch (Exception $e) 
                {
                    $this->send_notif_stripeerror('Commission Root Transfer Error','Profit Root Transfer Error to ' . $rootCommissionSRAcc ,$usrInfo->company_root_id);
                }
            }
            /** TRANSFER FOR ROOT SALES Representative */

            /** TRANSFER FOR ROOT Account Executive */
            if ($rootCommissionAEAcc != "" && $rootCommissionAEAccVal >= 0.5) 
            {
                try 
                {
                    // Log::info(['action' => 'TRANSFER FOR ROOT Account Executive','amount' => ($rootCommissionAEAccVal * 100),'currency' => 'usd','destination' => $rootCommissionAEAcc,'description' => 'Commision Root app from  Invoice ' . $companyName . ' #' . $_leadspeek_api_id . ' (' . date('m-d-Y',strtotime($todayDate)) . ')',]);
                    $transferRootProfitAEAcc = $stripe->transfers->create([
                        'amount' => ($rootCommissionAEAccVal * 100),
                        'currency' => 'usd',
                        'destination' => $rootCommissionAEAcc,
                        'description' => 'Commision Root app from  Invoice ' . $companyName . ' #' . $_leadspeek_api_id . ' (' . date('m-d-Y',strtotime($todayDate)) . ')',
                    ]);
                    //$this->send_email(array($sale['email']),$from,'Commission fee from ' . $sale['company_name'] . ' #' . $_leadspeek_api_id ,$details,$attachement,'emails.salesfee',$companyParentID);
                }
                catch (Exception $e) 
                {
                    $this->send_notif_stripeerror('Commission Root Transfer Error','Profit Root Transfer Error to ' . $rootCommissionAEAcc ,$usrInfo->company_root_id);
                }
            }
            /** TRANSFER FOR ROOT Account Executive */
        }
        /** CHARGE ROOT FEE AGENCY */
    }

    public function updateclientlocal(Request $request, $prevClient) {
        $locatorrequire = (isset($request->locatorrequire))?trim($request->locatorrequire):'';
        $urlCode = (isset($request->urlCode))?$request->urlCode:'';
        $phoneenabled = (isset($request->phoneenabled) && $request->phoneenabled)?'T':'F';
        $homeaddressenabled = (isset($request->homeaddressenabled) && $request->homeaddressenabled)?'T':'F';
        $requireemailaddress = (isset($request->requireemailaddress) && $request->requireemailaddress)?'T':'F';
        $reidentificationtype = (isset($request->reidentificationtype))?$request->reidentificationtype:'never';
        $gtminstalled = (isset($request->gtminstalled))?$request->gtminstalled:'F';
        $applyreidentificationall = (isset($request->applyreidentificationall) && $request->applyreidentificationall)?'T':'F';

        /* FOR LOCAL ADVANCE OR B2B INFORMATION */
        // id save
        $advance_information = "";
        $merge_information = [];
        $listAdvanceInformationId = isset($request->listAdvanceInformationId)?$request->listAdvanceInformationId:[];
        $listB2BInformationId = isset($request->listB2BInformationId)?$request->listB2BInformationId:[];
        if (is_array($listAdvanceInformationId) && !empty($listAdvanceInformationId)) {
            $merge_information['advance'] = $listAdvanceInformationId;
        }
        // if (is_array($listB2BInformationId) && !empty($listB2BInformationId)) {
        //     $merge_information['b2b'] = $listB2BInformationId;
        // }
        if(is_array($merge_information) && !empty($merge_information)) {
            $advance_information = json_encode($merge_information);
        }
        // id save

        // type save
        // $valid_types = ['basic','advanced','b2b'];
        $valid_types = ['basic','advanced'];
        $campaign_information_type_local = isset($request->campaign_information_type_local)?$request->campaign_information_type_local:['basic'];
        $campaign_information_type_local = array_filter($campaign_information_type_local, function($item) use($valid_types){
           return in_array($item, $valid_types);
        });
        $campaign_information_type_local = implode(',',array_unique(array_merge(['basic'], $campaign_information_type_local)));
        // type save
        /* FOR LOCAL ADVANCE OR B2B INFORMATION */

        $updateclient = LeadspeekUser::find($request->leadspeekID);
        $updateclient->leadspeek_locator_require = $locatorrequire;
        $updateclient->phoneenabled = $phoneenabled;
        $updateclient->homeaddressenabled = $homeaddressenabled;
        $updateclient->require_email = $requireemailaddress;
        $updateclient->reidentification_type = $reidentificationtype;
        $updateclient->advance_information = $advance_information;

        /* UPDATE FOR COST PER CONTACT */
        $campaign_information_type_local_copy = explode(',', $updateclient->campaign_information_type_local);
        $campaign_information_type_local_request_copy = explode(',', $campaign_information_type_local);
        sort($campaign_information_type_local_copy);
        sort($campaign_information_type_local_request_copy);
        $is_defference_campaign_information_type_local = ($campaign_information_type_local_copy != $campaign_information_type_local_request_copy);

        $paymentterm = $updateclient->paymentterm;
        $companyID = isset($request->companyID) ? $request->companyID : '';
        $userID = isset($request->userID) ? $request->userID : '';
        $idSys = User::where('company_id', $companyID)
                     ->where('user_type', 'userdownline')
                     ->value('company_root_id');
        $dataRequest = new Request([
            'CompanyID' => $companyID,
            'settingname' => 'costagency',
            'idSys' => $idSys,
            'pk' => $userID
        ]);

        try {
            $generalSetting = $this->ConfigurationController->getgeneralsetting($dataRequest);
            $generalSetting = $generalSetting->getData();

            if($is_defference_campaign_information_type_local) {
                $cost_perlead_basic = 0;
                if($paymentterm == "Weekly") {
                    $cost_perlead_basic = (isset($generalSetting->clientDefaultPrice->local->Weekly->LeadspeekCostperlead)) ? ($generalSetting->clientDefaultPrice->local->Weekly->LeadspeekCostperlead) : 1;
                } else if($paymentterm == 'Monthly') {
                    $cost_perlead_basic = (isset($generalSetting->clientDefaultPrice->local->Monthly->LeadspeekCostperlead)) ? ($generalSetting->clientDefaultPrice->local->Monthly->LeadspeekCostperlead) : 1;
                } else if($paymentterm == "Prepaid") {
                    $cost_perlead_basic = (isset($generalSetting->clientDefaultPrice->local->Prepaid->LeadspeekCostperlead)) ? ($generalSetting->clientDefaultPrice->local->Prepaid->LeadspeekCostperlead) : 1;
                }

                $cost_perlead_advanced = 0;
                if($paymentterm == "Weekly") {
                    $cost_perlead_advanced = (isset($generalSetting->clientDefaultPrice->local->Weekly->LeadspeekCostperleadAdvanced)) ? ($generalSetting->clientDefaultPrice->local->Weekly->LeadspeekCostperleadAdvanced) : 1;
                } else if($paymentterm == 'Monthly') {
                    $cost_perlead_advanced = (isset($generalSetting->clientDefaultPrice->local->Monthly->LeadspeekCostperleadAdvanced)) ? ($generalSetting->clientDefaultPrice->local->Monthly->LeadspeekCostperleadAdvanced) : 1;
                } else if($paymentterm == "Prepaid") {
                    $cost_perlead_advanced = (isset($generalSetting->clientDefaultPrice->local->Prepaid->LeadspeekCostperleadAdvanced)) ? ($generalSetting->clientDefaultPrice->local->Prepaid->LeadspeekCostperleadAdvanced) : 1;
                }

                if(in_array('advanced', $campaign_information_type_local_request_copy)) {
                    $updateclient->cost_perlead = $cost_perlead_advanced;
                } else {
                    $updateclient->cost_perlead = $cost_perlead_basic;
                }
            }
        } catch (\Throwable $e) {
            Log::error("Error Get General Setting = {$e->getMessage()}");
        }
        /* UPDATE FOR COST PER CONTACT */
        
        $updateclient->campaign_information_type_local = $campaign_information_type_local;

        $tryseraMethod = (isset($updateclient->trysera) && $updateclient->trysera == 'T')?true:false;

        $inOpenApi = (isset($request->inOpenApi))?$request->inOpenApi:"";

        /** UPDATE ON SPREADSHEET */
        
        $spreadSheetID = (isset($updateclient->spreadsheet_id))?trim($updateclient->spreadsheet_id):'';
        $companyID = $updateclient->company_id;

        /* PRE FILTER STATE */
        $globalviewmode = (isset($request->globalviewmode))?$request->globalviewmode:'';

        if($globalviewmode === true) {
            $answers = (isset($request->answers))?$request->answers:'';
            $includeExclude = isset($answers['includeExclude'])?$answers['includeExclude']:'0';
            
            if($includeExclude == '0') { // exclude
                $leadspeek_locator_state_exclude = (isset($answers['state']) && is_array($answers['state']) && count($answers['state']) > 0) ? implode(',',$answers['state']) : '';
                $updateclient->leadspeek_locator_state_exclude = $leadspeek_locator_state_exclude;
                $updateclient->leadspeek_locator_state = "";
                $updateclient->leadspeek_locator_state_type = 'exclude';
            } else { // include
                $leadspeek_locator_state = (isset($answers['state']) && is_array($answers['state']) && count($answers['state']) > 0) ? implode(',',$answers['state']) : '';
                $updateclient->leadspeek_locator_state = $leadspeek_locator_state;
                $updateclient->leadspeek_locator_state_exclude = "";
                $updateclient->leadspeek_locator_state_type = 'include';
            }
        }

        /* PRE FILTER STATE */

        /** CHECK IF HAVE GOOGLE CONNECTION */
        $check_connect_google = $this->googlespreadsheet_checkconnect($request, $updateclient->company_id);
        $data = $check_connect_google->getData(true); // true agar jadi array, bukan stdClass
        $isConnected_google = $data['googleSpreadsheetConnected'] ?? false;

        if($isConnected_google && $spreadSheetID != '') {
            /** CHECK IF HAVE GOOGLE CONNECTION */
            $clientGoogle = new GoogleSheet($companyID,$this->_moduleID,$this->_settingTokenName,'',true,$updateclient->leadspeek_api_id ?? "",$updateclient->leadspeek_type ?? "", __FUNCTION__);
            $clientGoogle->setSpreadSheetID($spreadSheetID);
                    
            $sheetID = $clientGoogle->getSheetID('2021');
                    if ($sheetID === '') {
                        $sheetdefault = explode('|',$clientGoogle->getDefaultSheetIDName());
                        $sheetID = $sheetdefault[0];
                        $clientGoogle->setSheetName($sheetdefault[1]);
                    }else{
                        $clientGoogle->setSheetName('2021');
                    }
                    
                    
                        /** NEW OPTIONS FOR ENABLE PHONE AND MAIL ADDRESS */
                        if($phoneenabled == 'T' && $homeaddressenabled == 'F') {
                            if ($tryseraMethod) {
                                $clientGoogle->showhideColumn($sheetID,7,8,'F');
                                $clientGoogle->showhideColumn($sheetID,10,11,'T');
                                $clientGoogle->showhideColumn($sheetID,11,12,'T');
                                $clientGoogle->showhideColumn($sheetID,12,13,'T');
                                $clientGoogle->showhideColumn($sheetID,13,14,'T');
                                $clientGoogle->showhideColumn($sheetID,14,15,'T');
                            }else{
                                $clientGoogle->showhideColumn($sheetID,6,8,'F');
                                $clientGoogle->showhideColumn($sheetID,8,13,'T');
                            }
                        }else if($phoneenabled == 'F' && $homeaddressenabled == 'T') {
                            if ($tryseraMethod) {
                                $clientGoogle->showhideColumn($sheetID,7,8,'T');
                                $clientGoogle->showhideColumn($sheetID,10,11,'F');
                                $clientGoogle->showhideColumn($sheetID,11,12,'F');
                                $clientGoogle->showhideColumn($sheetID,12,13,'F');
                                $clientGoogle->showhideColumn($sheetID,13,14,'F');
                                $clientGoogle->showhideColumn($sheetID,14,15,'F');
                            }else{
                                $clientGoogle->showhideColumn($sheetID,6,8,'T');
                                $clientGoogle->showhideColumn($sheetID,8,13,'F');
                            }
                        }else if($phoneenabled == 'F' && $homeaddressenabled == 'F') {
                            if ($tryseraMethod) {
                                $clientGoogle->showhideColumn($sheetID,7,8,'T');
                                $clientGoogle->showhideColumn($sheetID,10,11,'T');
                                $clientGoogle->showhideColumn($sheetID,11,12,'T');
                                $clientGoogle->showhideColumn($sheetID,12,13,'T');
                                $clientGoogle->showhideColumn($sheetID,13,14,'T');
                                $clientGoogle->showhideColumn($sheetID,14,15,'T');
                            }else{
                                $clientGoogle->showhideColumn($sheetID,6,8,'T');
                                $clientGoogle->showhideColumn($sheetID,8,13,'T');
                            }
                        }else{
                            if ($tryseraMethod) {
                                $clientGoogle->showhideColumn($sheetID,7,8,'F');
                                $clientGoogle->showhideColumn($sheetID,10,11,'F');
                                $clientGoogle->showhideColumn($sheetID,11,12,'F');
                                $clientGoogle->showhideColumn($sheetID,12,13,'F');
                                $clientGoogle->showhideColumn($sheetID,13,14,'F');
                                $clientGoogle->showhideColumn($sheetID,14,15,'F');
                            }else{
                                $clientGoogle->showhideColumn($sheetID,6,8,'F');
                                $clientGoogle->showhideColumn($sheetID,8,13,'F');
                            }
                        }
                        /** NEW OPTIONS FOR ENABLE PHONE AND MAIL ADDRESS */

                        // CREATE OR UPDATE SPREADSHEET
                        $campaign_information_type_local = explode(',',$campaign_information_type_local);
                        if(!empty($campaign_information_type_local) && in_array('advanced',$campaign_information_type_local)) 
                        {
                            $campaignInformation = campaignInformation::where('status', 'active')
                                                                    // ->whereIn('campaign_type', ['local_adv','local_b2b'])
                                                                    ->whereIn('campaign_type', ['local_adv'])
                                                                    ->orderBy('start_index','ASC')
                                                                    ->get();
                            $campaignInformationMerge = [];
                            foreach($campaignInformation as $item)
                            {
                                $descriptionArray = json_decode($item->description, true);
                                $campaignInformationMerge  = array_merge($campaignInformationMerge, $descriptionArray);
                            }

                            $oldHeader = $clientGoogle->getHeader($spreadSheetID);

                            // cek apakah new header belum ada sama sekali
                            $headerAdvanceInformationExists = false;
                            foreach($oldHeader as $item)
                            {
                                if(in_array($item, $campaignInformationMerge)) {
                                    $headerAdvanceInformationExists = true;
                                }
                            }

                            // jika belum ada sama sekali, add header
                            if(!$headerAdvanceInformationExists)
                            {
                                // info('jika belum ada sama sekali, add header');
                                $result = $clientGoogle->addHeader($spreadSheetID, $campaignInformationMerge);
                                
                                // sembunyikan campaign information yang tidak dipilih
                                $advanceInformationId = isset($merge_information['advance']) ? $merge_information['advance'] : [];
                                // $advanceB2BInformationId = isset($merge_information['b2b']) ? $merge_information['b2b'] : [];
                                $advanceB2BInformationId = [];
                                $mergeId = array_merge($advanceInformationId, $advanceB2BInformationId);
                                $campaignInformationNotInId = campaignInformation::whereNotIn('id', $mergeId)
                                                                                ->where('status', 'active')
                                                                                // ->whereIn('campaign_type', ['local_adv','local_b2b'])
                                                                                ->whereIn('campaign_type', ['local_adv'])
                                                                                ->orderBy('start_index','ASC')
                                                                                ->get();
                                foreach($campaignInformationNotInId as $item)
                                {
                                    $startIndex = intval($item->start_index);

                                    $countDescription = count(json_decode($item->description, true));
                                    $endIndex = intval($startIndex + $countDescription);

                                    $clientGoogle->showhideColumn($sheetID, $startIndex, $endIndex, 'T');
                                }
                            }
                            // jika sudah ada maka check untuk synchronized field
                            else
                            {
                                // info('jika sudah ada maka check untuk synchronized field');

                                $existingHeader = [
                                    "ID",
                                    "ClickDate",
                                    "First Name",
                                    "Last Name",
                                    "Email1",
                                    "Email2",
                                    "Phone1",
                                    "Phone2",
                                    "Address1",
                                    "Address2",
                                    "City",
                                    "State",
                                    "Zipcode",
                                    "Landing URL"
                                ];
                                $newHeader = array_merge($existingHeader, $campaignInformationMerge);

                                $different = array_diff($newHeader, $oldHeader);
                                $indexOfDifferent = array_keys($different);


                                if(count($indexOfDifferent) > 0) 
                                {
                                    // info('jika indexOfDifferent > 0, maka ada perbedaan field dan harus di synchronized');
                                    foreach($indexOfDifferent as $item)
                                    {
                                        // info('jalankan function insertColumnInSheet');
                                        $clientGoogle->insertColumnInSheet($spreadSheetID, $sheetID, $item);
                                    }
                                    
                                    // info('jalankan function updateHeader');
                                    $clientGoogle->updateHeader($spreadSheetID, $newHeader);

                                    // sembunyian colum yang tidak di uncheck
                                    $advanceInformationId = isset($merge_information['advance']) ? $merge_information['advance'] : [];
                                    // $advanceB2BInformationId = isset($merge_information['b2b']) ? $merge_information['b2b'] : [];
                                    $advanceB2BInformationId = [];
                                    $mergeId = array_merge($advanceInformationId, $advanceB2BInformationId);
                                    $campaignInformationNotInId = CampaignInformation::whereNotIn('id', $mergeId)
                                                                                    ->where('status', 'active')
                                                                                    // ->whereIn('campaign_type', ['local_adv','local_b2b'])
                                                                                    ->whereIn('campaign_type', ['local_adv'])
                                                                                    ->orderBy('start_index','ASC')
                                                                                    ->get();
                                    
                                    foreach($campaignInformationNotInId as $item)
                                    {
                                        $startIndex = intval($item->start_index);

                                        $countDescription = count(json_decode($item->description, true));
                                        $endIndex = intval($startIndex + $countDescription);

                                        // $endIndex = intval($item->end_index + 1);
                                        // info('jalankan function showhideColumn');
                                        $clientGoogle->showhideColumn($sheetID, $startIndex, $endIndex, 'T');
                                    }
                                    // sembunyian colum yang tidak di uncheck
                                }
                                // jika indexOfDifferent 0, maka field sudah synchronized seperti di db
                                else 
                                {
                                    // info("jika indexOfDifferent =  0, maka field sudah synchronized seperti di db");
                                    $advanceInformationId = isset($merge_information['advance']) ? $merge_information['advance'] : [];
                                    // $advanceB2BInformationId = isset($merge_information['b2b']) ? $merge_information['b2b'] : [];
                                    $advanceB2BInformationId = [];
                                    $mergeId = array_merge($advanceInformationId, $advanceB2BInformationId);
                                    $campaignInformationInId = campaignInformation::whereIn('id', $mergeId)
                                                                                ->where('status', 'active')
                                                                                // ->whereIn('campaign_type', ['local_adv','local_b2b'])
                                                                                ->whereIn('campaign_type', ['local_adv'])
                                                                                ->orderBy('start_index','ASC')
                                                                                ->get();
                                    foreach($campaignInformationInId as $item)
                                    {
                                        $startIndex = intval($item->start_index);

                                        $countDescription = count(json_decode($item->description, true));
                                        $endIndex = intval($startIndex + $countDescription);
                                        // info(['startIndex' => $startIndex, 'endIndex' => $endIndex]);

                                        // $endIndex = intval($item->end_index + 1);
                                        // info("munculkan {$item->type}", ['startIndex' => $startIndex, 'endIndex' => $endIndex]);
                                        $clientGoogle->showhideColumn($sheetID, $startIndex, $endIndex, 'F');
                                    }
                                }
                            }
                        }
                            // CREATE OR UPDATE SPREADSHEET
                    
        }          
        /** UPDATE ON SPREADSHEET */

        $updateclient->save();

        /* UPDATE TOPUP CAMPAIGN FOR ADVANCE INFORMATION */
        $campaign_information_type_local_array = explode(',', $updateclient->campaign_information_type_local);
        if(in_array('advanced', $campaign_information_type_local_array)) {
            $updateTopupIds = [];
            $topups = Topup::where('leadspeek_api_id','=',$updateclient->leadspeek_api_id)
                           ->where('topup_status','!=','done')
                           ->get();
            
            // kenapa id dikumpulin dahulu? untuk menghindari terjadi nya n+1
            foreach($topups as $topup) {
                $campaign_information_type_local_topup_array = explode(',', $topup->campaign_information_type_local);
                if(in_array('advanced', $campaign_information_type_local_topup_array)) {
                    $updateTopupIds[] = $topup->id;
                }
            }
    
            if (!empty($updateTopupIds)) {
                Topup::whereIn('id', $updateTopupIds)
                     ->update(['advance_information' => $updateclient->advance_information]);
            }
        }

        /* UPDATE TOPUP CAMPAIGN FOR ADVANCE INFORMATION */
        
        /** LOG ACTION */
        $descLogs = "Campaign Id: {$updateclient->leadspeek_api_id} | ";

        // Additional fields logging
        $fields = [
            'campaign_name' => 'Campaign Name',
            'admin_notify_to' => 'Admin Notify To',
            'url_code' => 'Url Code',
            'gtminstalled' => 'GTM Installed',
            'phoneenabled' => 'Phone Enabled',
            'homeaddressenabled' => 'Home Address Enabled',
            'require_email' => 'Require Email Address',
            'reidentification_type' => 'Re-identification Type',
            'applyreidentificationall' => 'Apply Reidentification',
            'leadspeek_locator_state_type' => 'States Type',
            'leadspeek_locator_state' => 'States Include',
            'leadspeek_locator_state_exclude' => 'States Exclude',
            'campaign_information_type_local' => 'Campaign Information Type Local',
        ];
        
        foreach ($fields as $field => $label) {
            if ($prevClient->$field !== $updateclient->$field) {
                $prevFieldClient = $prevClient->$field ?: 'empty';
                $newFieldClient = $updateclient->$field ?: 'empty';
                
                $descLogs .= "{$label}: Update values from {$prevFieldClient} to {$newFieldClient} | ";
            } else {
                $prevFieldClient = $prevClient->$field ?: 'empty';
                $descLogs .= "{$label}: No changes, value remains {$prevFieldClient} | ";
            }
        }

        if($descLogs == "Campaign Id: {$updateclient->leadspeek_api_id} | "){
            $descLogs = "Campaign Id: {$updateclient->leadspeek_api_id} | No updates were made as all values remain unchanged.";
        }

        if($inOpenApi === true) {
            $descLogs .= "in open api";
        }

        // Clean up and finalize log
        $descLogs = rtrim($descLogs, ' |');
        $ipAddress = ($inOpenApi === true) ? "inopenapi" : ($request->header('Clientip') ?? $request->ip());

        $user_id = null;
        if($inOpenApi === true) {
            if(isset($request->companyID) && $request->companyID != '') {
                $user_id = User::where('company_id', $request->companyID)->where('user_type', 'userdownline')->where('active', 'T')->value('id');
            }
        } else {
            $user_id = auth()->user()->id;
        }

        $target_userid = $updateclient->user_id;
        $this->logUserAction($user_id, "Edit Campaign Local", $descLogs, $ipAddress, $target_userid);
        // $this->logUserAction($updateclient->user_id, "Edit Campaign Local", $descLogs, $ipAddress);
        /** LOG ACTION */

        return response()->json(array('result'=>'success'));
    }

    public function updateclientlocator(Request $request, $prevClient) {
        $locatorzip = (isset($request->locatorzip))?str_replace(PHP_EOL,',',$request->locatorzip):'';
        $locatordesc = (isset($request->locatordesc))?$request->locatordesc:'';
        $locatorkeyword = (isset($request->locatorkeyword))?implode(",",$request->locatorkeyword):'';
        $locatorkeywordcontextual = (isset($request->locatorkeywordcontextual))?implode(",",$request->locatorkeywordcontextual):'';
        $sifiorganizationID = (isset($request->externalorganizationid))?$request->externalorganizationid:'';
        $sificampaignID = (isset($request->externalcampaign))?$request->externalcampaign:'';
        $companyID = (isset($request->companyID))?$request->companyID:'';

        $locatorrequire = (isset($request->locatorrequire))?trim($request->locatorrequire):'';
        $locatorstate = "";
        $locatorstatesifi = "";
        $locatorcity = "";
        $locatorcitysifi = "";
        $nationaltargeting = (isset($request->nationaltargeting) && $request->nationaltargeting)?'T':'F';

        $phoneenabled = (isset($request->phoneenabled) && $request->phoneenabled)?'T':'F';
        $homeaddressenabled = (isset($request->homeaddressenabled) && $request->homeaddressenabled)?'T':'F';
        $requireemailaddress = (isset($request->requireemailaddress) && $request->requireemailaddress)?'T':'F';

        $reidentificationtype = (isset($request->reidentificationtype))?$request->reidentificationtype:'never';

        $startdatecampaign = (isset($request->startdatecampaign))?$request->startdatecampaign:'';
        $enddatecampaign = (isset($request->enddatecampaign))?$request->enddatecampaign:'';
        $oristartdatecampaign = (isset($request->oristartdatecampaign))?$request->oristartdatecampaign:'';
        $orienddatecampaign = (isset($request->orienddatecampaign))?$request->orienddatecampaign:'';
        $locationtarget = (isset($request->locationtarget))?$request->locationtarget:'Focus';

        $timezone = (isset($request->timezone))?$request->timezone:'America/Chicago';

        $inOpenApi = (isset($request->inOpenApi))?$request->inOpenApi:"";

        if (isset($request->locatorstate) && count($request->locatorstate) > 0) {
            $_tmp = $request->locatorstate;
            $locatorstate = "";
            $locatorstatesifi = "";

            foreach($_tmp as $tmp) {
                $ex = explode('|',$tmp);
                $locatorstate .= $ex[1] . ',';
                $locatorstatesifi .= $ex[0] . ',';
            }
            $locatorstate = rtrim($locatorstate,",");
            $locatorstatesifi = rtrim($locatorstatesifi,",");
        }else{
            $locatorstate = "";
            $locatorstatesifi = "";
        }

        if (isset($request->locatorcity) && count($request->locatorcity) > 0) {
            $_tmp = $request->locatorcity;
            $locatorcity = '';
            $locatorcitysifi = '';
            foreach($_tmp as $tmp) {
                $ex = explode('|',$tmp);
                $locatorcity .= $ex[1] . ',';
                $locatorcitysifi .= $ex[0] . ',';
            }
            $locatorcity = rtrim($locatorcity,",");
            $locatorcitysifi = rtrim($locatorcitysifi,",");
        }else{
            $locatorcity = "";
            $locatorcitysifi = "";
        }

        $hidePhone = 'T';
        $_tmp = explode(",",$locatorrequire);
        if (in_array("Phone",$_tmp)) {
            $hidePhone ='F';
        }

        $updateclient = LeadspeekUser::find($request->leadspeekID);

        $tryseraMethod = (isset($updateclient->trysera) && $updateclient->trysera == 'T')?true:false;
        
        $masterCostAgency = CompanySetting::where('company_id',$companyID)->whereEncrypted('setting_name','agencydefaultprice')->get();
        if (count($masterCostAgency) > 0) {
            $masterCostAgency = json_decode($masterCostAgency[0]['setting_value']);
        }else{
            $masterCostAgency = '';
        }

        $cost_perlead = 2;
       
        if ($locatorrequire == "FirstName,LastName") {
            $cost_perlead = (isset($masterCostAgency->locatorlead->FirstName_LastName))?$masterCostAgency->locatorlead->FirstName_LastName:'1.50';
        }else if ($locatorrequire == "FirstName,LastName,MailingAddress") {
            $cost_perlead = (isset($masterCostAgency->locatorlead->FirstName_LastName_MailingAddress))?$masterCostAgency->locatorlead->FirstName_LastName_MailingAddress:'2';
        }else if ($locatorrequire == "FirstName,LastName,MailingAddress,Phone") {
            $cost_perlead = (isset($masterCostAgency->locatorlead->FirstName_LastName_MailingAddress_Phone))?$masterCostAgency->locatorlead->FirstName_LastName_MailingAddress_Phone:'3';
        }

        /** UPDATE ON SPREADSHEET */
        
        $spreadSheetID = isset($updateclient->spreadsheet_id)?trim($updateclient->spreadsheet_id):'';
        $companyID = $updateclient->company_id;
        $leadspeek_apiID = $updateclient->leadspeek_api_id;

        try {
            /** CHECK IF HAVE GOOGLE CONNECTION */
            $check_connect_google = $this->googlespreadsheet_checkconnect($request, $updateclient->company_id);
            $data = $check_connect_google->getData(true); // true agar jadi array, bukan stdClass
            $isConnected_google = $data['googleSpreadsheetConnected'] ?? false;

            if($isConnected_google && $spreadSheetID != '') {
            /** CHECK IF HAVE GOOGLE CONNECTION */
                $clientGoogle = new GoogleSheet($companyID,$this->_moduleID,$this->_settingTokenName,'',true,$updateclient->leadspeek_api_id ?? "",$updateclient->leadspeek_type ?? "", __FUNCTION__);
                $clientGoogle->setSpreadSheetID($spreadSheetID);
                        
                $sheetID = $clientGoogle->getSheetID('2021');
                        if ($sheetID === '') {
                            $sheetdefault = explode('|',$clientGoogle->getDefaultSheetIDName());
                            $sheetID = $sheetdefault[0];
                            $clientGoogle->setSheetName($sheetdefault[1]);
                        }else{
                            $clientGoogle->setSheetName('2021');
                        }
                        
                        if ($locatorrequire == "FirstName,LastName") {
                            if ($tryseraMethod) {
                                $clientGoogle->showhideColumn($sheetID,7,8,'T');
                                $clientGoogle->showhideColumn($sheetID,10,11,'T');
                                $clientGoogle->showhideColumn($sheetID,11,12,'T');
                            }else{
                                $clientGoogle->showhideColumn($sheetID,6,8,'T');
                                $clientGoogle->showhideColumn($sheetID,8,13,'F');
                            }
                        }else if ($locatorrequire == "FirstName,LastName,MailingAddress") {
                            if ($tryseraMethod) {
                                $clientGoogle->showhideColumn($sheetID,7,8,'T');
                                $clientGoogle->showhideColumn($sheetID,10,11,'F');
                                $clientGoogle->showhideColumn($sheetID,11,12,'F');
                            }else{
                                $clientGoogle->showhideColumn($sheetID,6,8,'T');
                                $clientGoogle->showhideColumn($sheetID,8,13,'F');
                            }
                        }else if ($locatorrequire == "FirstName,LastName,MailingAddress,Phone") {
                            /** NEW OPTIONS FOR ENABLE PHONE AND MAIL ADDRESS */
                            if($phoneenabled == 'T' && $homeaddressenabled == 'F') {
                                if ($tryseraMethod) {
                                    $clientGoogle->showhideColumn($sheetID,7,8,'F');
                                    $clientGoogle->showhideColumn($sheetID,10,11,'T');
                                    $clientGoogle->showhideColumn($sheetID,11,12,'T');
                                    $clientGoogle->showhideColumn($sheetID,12,13,'T');
                                    $clientGoogle->showhideColumn($sheetID,13,14,'T');
                                    $clientGoogle->showhideColumn($sheetID,14,15,'T');
                                }else{
                                    $clientGoogle->showhideColumn($sheetID,6,8,'F');
                                    $clientGoogle->showhideColumn($sheetID,8,13,'T');
                                }
                            }else if($phoneenabled == 'F' && $homeaddressenabled == 'T') {
                                if ($tryseraMethod) {
                                    $clientGoogle->showhideColumn($sheetID,7,8,'T');
                                    $clientGoogle->showhideColumn($sheetID,10,11,'F');
                                    $clientGoogle->showhideColumn($sheetID,11,12,'F');
                                    $clientGoogle->showhideColumn($sheetID,12,13,'F');
                                    $clientGoogle->showhideColumn($sheetID,13,14,'F');
                                    $clientGoogle->showhideColumn($sheetID,14,15,'F');
                                }else{
                                    $clientGoogle->showhideColumn($sheetID,6,8,'T');
                                    $clientGoogle->showhideColumn($sheetID,8,13,'F');
                                }
                            }else if($phoneenabled == 'F' && $homeaddressenabled == 'F') {
                                if ($tryseraMethod) {
                                    $clientGoogle->showhideColumn($sheetID,7,8,'T');
                                    $clientGoogle->showhideColumn($sheetID,10,11,'T');
                                    $clientGoogle->showhideColumn($sheetID,11,12,'T');
                                    $clientGoogle->showhideColumn($sheetID,12,13,'T');
                                    $clientGoogle->showhideColumn($sheetID,13,14,'T');
                                    $clientGoogle->showhideColumn($sheetID,14,15,'T');
                                }else{
                                    $clientGoogle->showhideColumn($sheetID,6,8,'T');
                                    $clientGoogle->showhideColumn($sheetID,8,13,'T');
                                }
                            }else{
                                if ($tryseraMethod) {
                                    $clientGoogle->showhideColumn($sheetID,7,8,'F');
                                    $clientGoogle->showhideColumn($sheetID,10,11,'F');
                                    $clientGoogle->showhideColumn($sheetID,11,12,'F');
                                    $clientGoogle->showhideColumn($sheetID,12,13,'F');
                                    $clientGoogle->showhideColumn($sheetID,13,14,'F');
                                    $clientGoogle->showhideColumn($sheetID,14,15,'F');
                                }else{
                                    $clientGoogle->showhideColumn($sheetID,6,8,'F');
                                    $clientGoogle->showhideColumn($sheetID,8,13,'F');
                                }
                            }
                            /** NEW OPTIONS FOR ENABLE PHONE AND MAIL ADDRESS */

                        }
            } 
        /** UPDATE ON SPREADSHEET */
        }catch(Exception $e) {
            Log::warning("Google Spreadsheet Update Client - UpdateClientLocator Failed (L1949) ErrMsg:" . $e->getMessage() . " CampaignID:" . $updateclient->leadspeek_api_id . ' Campaign Name:' . (isset($request->campaignName))?$request->campaignName:'');
        }
        /** UPDATE ON SIMPLIFI */

        if (trim($sifiorganizationID) != "" && trim($sificampaignID) != "") {

            /** CHECK IF CAMPAIGN IS RUNNING NEED TO PAUSE FIRST */
            if ($updateclient->active == 'T' && $updateclient->disabled == 'F' && $updateclient->active_user == 'T') {
                $camp = $this->startPause_campaign($sifiorganizationID,$sificampaignID,'pause');
            }
            /** CHECK IF CAMPAIGN IS RUNNING NEED TO PAUSE FIRST */

            $_state = explode(",",trim($locatorstatesifi));
            $_cities = explode(",",trim($locatorcitysifi));
            
            $_geotarget = array_merge($_state,$_cities);
            $_postalcode = array(); 
            $tmp_postcode = explode(',',$locatorzip);
            foreach($tmp_postcode as $postcode) {
                if($postcode != '') {
                    array_push($_postalcode,array("postal_code"=>$postcode, "country_code"=>"USA"));
                }
            }

            if ($nationaltargeting == 'T') {
                $_postalcode = array(); 
                $_geotarget = array('8180');
            }
            $this->updateGeoTargetsPostalCode($sifiorganizationID,$sificampaignID,$_geotarget,$_postalcode);

            /** KEYWORDS */
            $_keywords = array(); 
            $tmp_keywords = $request->locatorkeyword;
            foreach($tmp_keywords as $keyword) {
                if(trim($keyword) != "") {
                    array_push($_keywords,array("name"=>trim($keyword), "max_bid"=>""));
                }
            }

            /** FOR CONTEXTUAL */
            if (is_array($request->locatorkeywordcontextual)) {
                $tmp_keywords = $request->locatorkeywordcontextual;
                foreach($tmp_keywords as $keyword) {
                    if(trim($keyword) != "") {
                        array_push($_keywords,array("name"=>"!" . trim($keyword), "max_bid"=>""));
                    }
                }
            }
            /** FOR CONTEXTUAL */

            $this->createKeywords($sificampaignID,$_keywords,$sifiorganizationID);
            /** KEYWORDS */

            /** UPDATE BUDGET PLAN */
            $start_date = $startdatecampaign;
            $end_date = $enddatecampaign;
            
            //$tmp = strtotime($end_date) - strtotime($start_date);
            //$diffdays = abs(round($tmp / 86400));
            //$totalbudget = number_format(1 * $diffdays,1,".","");
           
            $this->updateDefaultBudgetPlan($sifiorganizationID,$sificampaignID,$oristartdatecampaign,$orienddatecampaign);
            /** UPDATE BUDGET PLAN */

            /** UPDATE RECENCY */
            //$this->updateRecencyCampaign($sifiorganizationID,$sificampaignID,"2");
            /** UPDATE RECENCY */

            /** UPDATE DEVICE TYPE */
            //$this->createDeviceType($sificampaignID,36);
            /** UPDATE DEVICE TYPE */

            /** CHECK IF CAMPAIGN IS RUNNING AFTER PAUSE NEED TO START AGAIN */
            if ($updateclient->active == 'T' && $updateclient->disabled == 'F' && $updateclient->active_user == 'T') {
                $camp = $this->startPause_campaign($sifiorganizationID,$sificampaignID,'activate');
            }
            /** CHECK IF CAMPAIGN IS RUNNING AFTER PAUSE NEED TO START AGAIN */
        }
        /** UPDATE ON SIMPLIFI */

        $updateclient = LeadspeekUser::find($request->leadspeekID);
        $updateclient->leadspeek_locator_zip = $locatorzip;
        $updateclient->leadspeek_locator_desc = $locatordesc;
        $updateclient->leadspeek_locator_keyword = $locatorkeyword;
        $updateclient->leadspeek_locator_keyword_contextual = $locatorkeywordcontextual;
        $updateclient->leadspeek_locator_state = $locatorstate;
        $updateclient->leadspeek_locator_state_simplifi = $locatorstatesifi;
        $updateclient->leadspeek_locator_city = $locatorcity;
        $updateclient->leadspeek_locator_city_simplifi = $locatorcitysifi;
        $updateclient->leadspeek_locator_state = $locatorstate;
        $updateclient->leadspeek_locator_require = $locatorrequire;
        if (trim($locatorrequire) != trim($updateclient->leadspeek_locator_require)) {
            $updateclient->cost_perlead = $cost_perlead;
        }
        $updateclient->hide_phone = $hidePhone;
        $updateclient->campaign_startdate = $startdatecampaign;
        $updateclient->campaign_enddate = $enddatecampaign;
        $updateclient->ori_campaign_startdate = $oristartdatecampaign;
        $updateclient->ori_campaign_enddate = $orienddatecampaign;
        $updateclient->lp_enddate = $enddatecampaign;
        $updateclient->timezone = $timezone;
        $updateclient->national_targeting = $nationaltargeting;
        $updateclient->location_target = $locationtarget;
        $updateclient->phoneenabled = $phoneenabled;
        $updateclient->homeaddressenabled = $homeaddressenabled;
        $updateclient->require_email = $requireemailaddress;
        $updateclient->reidentification_type = $reidentificationtype;
        
        $updateclient->save();

        /** NOTIFY ADMIN */
            $adminnotify = explode(',',$updateclient->admin_notify_to);
            /** FIND ADMIN EMAIL */
            $tmp = User::select('email')->whereIn('id', $adminnotify)->get();
            $adminEmail = array();
            foreach($tmp as $ad) {
                array_push($adminEmail,$ad['email']);
            }
            //array_push($adminEmail,'serverlogs@sitesettingsapi.com');
            //array_push($adminEmail,'carrie@uncommonreach.com');
            //array_push($adminEmail,'daniel@exactmatchmarketing.com');
            /** FIND ADMIN EMAIL */

            /** FIND USER COMPANY */
            $userlist = User::select('companies.company_name','users.company_parent','users.company_root_id')
                            ->join('companies','users.company_id','=','companies.id')
                            ->where('users.id','=',$updateclient->user_id)
                            ->where('users.active','=','T')
                            ->get();
            /** FIND USER COMPANY */

            /** FIND PARENT OR AGENCY NAME */
            $agency = Company::select('company_name')->where('id','=',$userlist[0]['company_parent'])->get();
            $agencyName = "";
            if (count($agency) > 0) {
                $agencyName = ' (' . $agency[0]['company_name'] . ')';
            }
            /** FIND PARENT OR AGENCY NAME */

            /** GET PRODUCT NAME */
            $productlocalname = 'Site ID';
            $productlocatorname = 'Search ID';

            $rootcompanysetting = CompanySetting::where('company_id',trim($userlist[0]['company_root_id']))->whereEncrypted('setting_name','rootcustomsidebarleadmenu')->get();
            if (count($rootcompanysetting) > 0) {
                $productname = json_decode($rootcompanysetting[0]['setting_value']);
                $productlocalname = $productname->local->name;
                $productlocatorname = $productname->locator->name;
            }

            if($this->checkwhitelabellingpackage(trim($companyID))) {
                $companysetting = CompanySetting::where('company_id',trim($companyID))->whereEncrypted('setting_name','customsidebarleadmenu')->get();
                if (count($companysetting) > 0) {
                    $productname = json_decode($companysetting[0]['setting_value']);
                    $productlocalname = $productname->local->name;
                    $productlocatorname = $productname->locator->name;
                }
            }
            /** GET PRODUCT NAME */


            $details = [
                'name'  => $userlist[0]['company_name'],
                'locatorzip' => trim($locatorzip),
                'locatordesc' => trim($locatordesc),
                'locatorkeyword' => trim($locatorkeyword),
                'locatorkeywordcontextual' => trim($locatorkeywordcontextual),
                'locatorstate' => trim($locatorstate),
                'locatorcities' => trim($locatorcity),
                'requiredfields' => trim($locatorrequire),
                'productlocatorname' => $productlocatorname,
            ];

            $from = [
                'address' => 'noreply@sitesettingsapi.com',
                'name' => 'Update Notification',
                'replyto' => 'support@sitesettingsapi.com',
            ];

            /** LOG ACTION */
            $descLogs = '';
            $fromTargetLocation = '';

            // Determine initial target location
            if ($prevClient->national_targeting === 'T') {
                $fromTargetLocation .= 'National Targeting,';
            }
            if (!empty($prevClient->leadspeek_locator_state)) {
                $fromTargetLocation .= 'States,';
            }
            if (!empty($prevClient->leadspeek_locator_zip)) {
                $fromTargetLocation .= 'Zip Code,';
            }

            $fromTargetLocation = rtrim($fromTargetLocation, ',');

            // Update target location
            if ($updateclient->national_targeting === 'T') {
                $descLogs .= "Campaign Id: {$updateclient->leadspeek_api_id} | Target Location: Update values from {$fromTargetLocation} to National Targeting | ";
            } else {
                if ($updateclient->leadspeek_locator_state) {
                    $descLogs .= "Campaign Id: {$updateclient->leadspeek_api_id} | Target Location: Update values from {$fromTargetLocation} to States | ";
                    if ($prevClient->leadspeek_locator_state !== $updateclient->leadspeek_locator_state) {
                        $prevState = $prevClient->leadspeek_locator_state ?: 'empty';
                        $newState = $updateclient->leadspeek_locator_state ?: 'empty';

                        $descLogs .= "States: Update values from $prevState to $newState | ";
                    } else {
                        $prevState = $prevClient->leadspeek_locator_state ?: 'empty';
                        $descLogs .= "States: No changes, value remains {$prevState} | ";
                    }

                    if ($prevClient->leadspeek_locator_state_simplifi !== $updateclient->leadspeek_locator_state_simplifi) {
                        $prevStateSimplifi = $prevClient->leadspeek_locator_state_simplifi ?: 'empty';
                        $newStateSimplifi = $updateclient->leadspeek_locator_state_simplifi ?: 'empty';

                        $descLogs .= "States Simplifi: Update values from $prevStateSimplifi to $newStateSimplifi | ";
                    } else {
                        $prevStateSimplifi = $prevClient->leadspeek_locator_state_simplifi ?: 'empty';
                        $descLogs .= "States Simplifi: No changes, value remains {$prevStateSimplifi} | ";
                    }

                    if ($prevClient->leadspeek_locator_city !== $updateclient->leadspeek_locator_city) {
                        $prevCities = $prevClient->leadspeek_locator_city ?: 'empty';
                        $newCities = $updateclient->leadspeek_locator_city ?: 'empty';

                        $descLogs .= "Cities: Update values from $prevCities to $newCities | ";
                    } else {
                        $prevCities = $prevClient->leadspeek_locator_city ?: 'empty';
                        $descLogs .= "Cities: No changes, value remains {$prevCities} | ";
                    }
                    
                    if ($prevClient->leadspeek_locator_city_simplifi !== $updateclient->leadspeek_locator_city_simplifi) {
                        $prevCitiesSimplifi = $prevClient->leadspeek_locator_city_simplifi ?: 'empty';
                        $newCitiesSimplifi = $updateclient->leadspeek_locator_city_simplifi ?: 'empty';

                        $descLogs .= "Cities Simplifi: Update values from $prevCitiesSimplifi to $newCitiesSimplifi | ";
                    } else {
                        $prevCitiesSimplifi = $prevClient->leadspeek_locator_city_simplifi ?: 'empty';
                        $descLogs .= "Cities Simplifi: No changes, value remains {$prevCitiesSimplifi} | ";
                    }
                } 
                
                if ($updateclient->leadspeek_locator_zip) {
                    $descLogs .= "Campaign Id: {$updateclient->leadspeek_api_id} | Target Location: Update values from {$fromTargetLocation} to Zip Code | ";
                    if ($prevClient->leadspeek_locator_zip !== $updateclient->leadspeek_locator_zip) {
                        $prevZipCode = $prevClient->leadspeek_locator_zip ?: 'empty';
                        $newZipCode = $updateclient->leadspeek_locator_zip ?: 'empty';

                        $descLogs .= "Zip Code: Update values from $prevZipCode to $newZipCode | ";
                    } else {
                        $prevZipCode = $prevClient->leadspeek_locator_zip ?: 'empty';
                        $descLogs .= "Zip Code: No changes, value remains {$prevZipCode} | ";
                    }
                }
            }
            

            // Additional fields logging
            $fields = [
                'campaign_name' => 'Campaign Name',
                'admin_notify_to' => 'Admin Notify To',
                'campaign_enddate' => 'Campaign End Date',
                'phoneenabled' => 'Phone Enabled',
                'homeaddressenabled' => 'Home Address Enabled',
                'require_email' => 'Require Email Address',
                'reidentification_type' => 'Re-identification Type',
                'applyreidentificationall' => 'Apply Reidentification',
                'leadspeek_locator_keyword' => 'Keyword',
                'leadspeek_locator_keyword_contextual' => 'Keyword Contextual'
            ];

            foreach ($fields as $field => $label) {
                if ($prevClient->$field !== $updateclient->$field) {
                    $prevFieldClient = $prevClient->$field ?: 'empty';
                    $newFieldClient = $updateclient->$field ?: 'empty';
                    $descLogs .= "{$label}: Update values from {$prevFieldClient} to {$newFieldClient} | ";
                } else {
                    $prevFieldClient = $prevClient->$field ?: 'empty';
                    $descLogs .= "{$label}: No changes, value remains {$prevFieldClient} | ";
                }
            }

            if($descLogs == ''){
                $descLogs = "Campaign Id: {$updateclient->leadspeek_api_id} | No updates were made as all values remain unchanged.";
            }

            if($inOpenApi === true) {
                $descLogs .= "in open api";
            }

            // Clean up and finalize log
            $descLogs = rtrim($descLogs, ' |');

            $ipAddress = ($inOpenApi === true) ? "inopenapi" : ($request->header('Clientip') ?? $request->ip());

            $user_id = null;
            if($inOpenApi === true) {
                if(isset($request->companyID) && $request->companyID != '') {
                    $user_id = User::where('company_id', $request->companyID)->where('user_type', 'userdownline')->where('active', 'T')->value('id');
                }
            } else {
                $user_id = auth()->user()->id;
            }

            $target_userid = $updateclient->user_id;
            $this->logUserAction($user_id, "Edit Campaign Locator", $descLogs, $ipAddress, $target_userid);
            // $this->logUserAction($user_id, "Edit Campaign Locator", $descLogs, $ipAddress);
            /** LOG ACTION */

            //$this->send_email(array('carrie@uncommonreach.com'),$from,$userlist[0]['company_name'] . '-' . $updateclient->campaign_name . ' #' . $leadspeek_apiID . $agencyName . ' updated their ' . $productlocatorname . ' information',$details,array(),'emails.locatorinfoupdate',$companyID);

        /** NOTIFY ADMIN */

        return response()->json(array('result'=>'success', 'company'=>$userlist[0]['company_name'], 'detais'=>$details));
    }

    public function updateclientenhance(Request $request, $prevClient) {
        $locatorzip = (isset($request->locatorzip))?str_replace([PHP_EOL, "\r\n", "\n", "\r"], ',', $request->locatorzip) :'';
        $locatordesc = (isset($request->locatordesc))?$request->locatordesc:'';
        $locatorkeyword = (isset($request->locatorkeyword))?implode(",",$request->locatorkeyword):'';
        $locatorkeywordcontextual = (isset($request->locatorkeywordcontextual))?implode(",",$request->locatorkeywordcontextual):'';
        $sifiorganizationID = (isset($request->externalorganizationid))?$request->externalorganizationid:'';
        $sificampaignID = (isset($request->externalcampaign))?$request->externalcampaign:'';
        $companyID = (isset($request->companyID))?$request->companyID:'';

        $locatorrequire = (isset($request->locatorrequire))?trim($request->locatorrequire):'';
        $locatorstate = "";
        $locatorstatesifi = "";
        $locatorcity = "";
        $locatorcitysifi = "";
        $nationaltargeting = (isset($request->nationaltargeting) && $request->nationaltargeting)?'T':'F';

        $phoneenabled = (isset($request->phoneenabled) && $request->phoneenabled)?'T':'F';
        $homeaddressenabled = (isset($request->homeaddressenabled) && $request->homeaddressenabled)?'T':'F';
        $requireemailaddress = (isset($request->requireemailaddress) && $request->requireemailaddress)?'T':'F';

        $reidentificationtype = (isset($request->reidentificationtype))?$request->reidentificationtype:'never';

        $startdatecampaign = (isset($request->startdatecampaign))?$request->startdatecampaign:'';
        $enddatecampaign = (isset($request->enddatecampaign))?$request->enddatecampaign:'';
        $oristartdatecampaign = (isset($request->oristartdatecampaign))?$request->oristartdatecampaign:'';
        $orienddatecampaign = (isset($request->orienddatecampaign))?$request->orienddatecampaign:'';
        $locationtarget = (isset($request->locationtarget))?$request->locationtarget:'Focus';

        $timezone = (isset($request->timezone))?$request->timezone:'America/Chicago';

        $inOpenApi = (isset($request->inOpenApi))?$request->inOpenApi:"";

        if (isset($request->locatorstate) && count($request->locatorstate) > 0) {
            $locatorstate = rtrim(implode(",", $request->locatorstate), ",");
        }else{
            $locatorstate = "";
        }

        if (isset($request->locatorcity) && count($request->locatorcity) > 0) {
            $locatorcity = rtrim(implode(",", $request->locatorcity), ",");
        }else{
            $locatorcity = "";
        }

        $hidePhone = 'T';
        $_tmp = explode(",",$locatorrequire);
        if (in_array("Phone",$_tmp)) {
            $hidePhone ='F';
        }

        $updateclient = LeadspeekUser::find($request->leadspeekID);

        $tryseraMethod = (isset($updateclient->trysera) && $updateclient->trysera == 'T')?true:false;
        
        $masterCostAgency = CompanySetting::where('company_id',$companyID)->whereEncrypted('setting_name','agencydefaultprice')->get();
        if (count($masterCostAgency) > 0) {
            $masterCostAgency = json_decode($masterCostAgency[0]['setting_value']);
        }else{
            $masterCostAgency = '';
        }

        $cost_perlead = 2;
       
        if ($locatorrequire == "FirstName,LastName") {
            $cost_perlead = (isset($masterCostAgency->locatorlead->FirstName_LastName))?$masterCostAgency->locatorlead->FirstName_LastName:'1.50';
        }else if ($locatorrequire == "FirstName,LastName,MailingAddress") {
            $cost_perlead = (isset($masterCostAgency->locatorlead->FirstName_LastName_MailingAddress))?$masterCostAgency->locatorlead->FirstName_LastName_MailingAddress:'2';
        }else if ($locatorrequire == "FirstName,LastName,MailingAddress,Phone") {
            $cost_perlead = (isset($masterCostAgency->locatorlead->FirstName_LastName_MailingAddress_Phone))?$masterCostAgency->locatorlead->FirstName_LastName_MailingAddress_Phone:'3';
        }

        /** UPDATE ON SPREADSHEET */
        /* ADVANCE_INFORMATION */
        $advance_information = "";
        $campaign_information_type = isset($request->campaign_information_type)?$request->campaign_information_type:'basic';
        if(isset($request->listAdvanceInformationId) && is_array($request->listAdvanceInformationId) && count($request->listAdvanceInformationId) > 0) {
            $advance_information = implode(',', $request->listAdvanceInformationId);
        }

        $valid_types = ['basic', 'advanced'];
        if (!in_array($campaign_information_type, $valid_types)) {
            return response()->json(['result' => 'failed', 'message' => 'Invalid campaign information type'], 400);
        }
        /* ADVANCE_INFORMATION */

        
        $spreadSheetID = isset($updateclient->spreadsheet_id)?trim($updateclient->spreadsheet_id):'';
        $companyID = $updateclient->company_id;
        $leadspeek_apiID = $updateclient->leadspeek_api_id;

        try {
            /** CHECK IF HAVE GOOGLE CONNECTION */
            $check_connect_google = $this->googlespreadsheet_checkconnect($request, $updateclient->company_id);
            $data = $check_connect_google->getData(true); // true agar jadi array, bukan stdClass
            $isConnected_google = $data['googleSpreadsheetConnected'] ?? false;

            if($isConnected_google && $spreadSheetID != '') {
            /** CHECK IF HAVE GOOGLE CONNECTION */
                $clientGoogle = new GoogleSheet($companyID,$this->_moduleID,$this->_settingTokenName,'',true,$updateclient->leadspeek_api_id ?? "",$updateclient->leadspeek_type ?? "", __FUNCTION__);
                $clientGoogle->setSpreadSheetID($spreadSheetID);
                        
                $sheetID = $clientGoogle->getSheetID('2021');
                        if ($sheetID === '') {
                            $sheetdefault = explode('|',$clientGoogle->getDefaultSheetIDName());
                            $sheetID = $sheetdefault[0];
                            $clientGoogle->setSheetName($sheetdefault[1]);
                        }else{
                            $clientGoogle->setSheetName('2021');
                        }
                        
                        if ($locatorrequire == "FirstName,LastName") {
                            if ($tryseraMethod) {
                                $clientGoogle->showhideColumn($sheetID,7,8,'T');
                                $clientGoogle->showhideColumn($sheetID,10,11,'T');
                                $clientGoogle->showhideColumn($sheetID,11,12,'T');
                            }else{
                                $clientGoogle->showhideColumn($sheetID,6,8,'T');
                                $clientGoogle->showhideColumn($sheetID,8,13,'F');
                            }
                        }else if ($locatorrequire == "FirstName,LastName,MailingAddress") {
                            if ($tryseraMethod) {
                                $clientGoogle->showhideColumn($sheetID,7,8,'T');
                                $clientGoogle->showhideColumn($sheetID,10,11,'F');
                                $clientGoogle->showhideColumn($sheetID,11,12,'F');
                            }else{
                                $clientGoogle->showhideColumn($sheetID,6,8,'T');
                                $clientGoogle->showhideColumn($sheetID,8,13,'F');
                            }
                        }else if ($locatorrequire == "FirstName,LastName,MailingAddress,Phone") {
                            /** NEW OPTIONS FOR ENABLE PHONE AND MAIL ADDRESS */
                            if($phoneenabled == 'T' && $homeaddressenabled == 'F') {
                                if ($tryseraMethod) {
                                    $clientGoogle->showhideColumn($sheetID,7,8,'F');
                                    $clientGoogle->showhideColumn($sheetID,10,11,'T');
                                    $clientGoogle->showhideColumn($sheetID,11,12,'T');
                                    $clientGoogle->showhideColumn($sheetID,12,13,'T');
                                    $clientGoogle->showhideColumn($sheetID,13,14,'T');
                                    $clientGoogle->showhideColumn($sheetID,14,15,'T');
                                }else{
                                    $clientGoogle->showhideColumn($sheetID,6,8,'F');
                                    $clientGoogle->showhideColumn($sheetID,8,13,'T');
                                }
                            }else if($phoneenabled == 'F' && $homeaddressenabled == 'T') {
                                if ($tryseraMethod) {
                                    $clientGoogle->showhideColumn($sheetID,7,8,'T');
                                    $clientGoogle->showhideColumn($sheetID,10,11,'F');
                                    $clientGoogle->showhideColumn($sheetID,11,12,'F');
                                    $clientGoogle->showhideColumn($sheetID,12,13,'F');
                                    $clientGoogle->showhideColumn($sheetID,13,14,'F');
                                    $clientGoogle->showhideColumn($sheetID,14,15,'F');
                                }else{
                                    $clientGoogle->showhideColumn($sheetID,6,8,'T');
                                    $clientGoogle->showhideColumn($sheetID,8,13,'F');
                                }
                            }else if($phoneenabled == 'F' && $homeaddressenabled == 'F') {
                                if ($tryseraMethod) {
                                    $clientGoogle->showhideColumn($sheetID,7,8,'T');
                                    $clientGoogle->showhideColumn($sheetID,10,11,'T');
                                    $clientGoogle->showhideColumn($sheetID,11,12,'T');
                                    $clientGoogle->showhideColumn($sheetID,12,13,'T');
                                    $clientGoogle->showhideColumn($sheetID,13,14,'T');
                                    $clientGoogle->showhideColumn($sheetID,14,15,'T');
                                }else{
                                    $clientGoogle->showhideColumn($sheetID,6,8,'T');
                                    $clientGoogle->showhideColumn($sheetID,8,13,'T');
                                }
                            }else{
                                if ($tryseraMethod) {
                                    $clientGoogle->showhideColumn($sheetID,7,8,'F');
                                    $clientGoogle->showhideColumn($sheetID,10,11,'F');
                                    $clientGoogle->showhideColumn($sheetID,11,12,'F');
                                    $clientGoogle->showhideColumn($sheetID,12,13,'F');
                                    $clientGoogle->showhideColumn($sheetID,13,14,'F');
                                    $clientGoogle->showhideColumn($sheetID,14,15,'F');
                                }else{
                                    $clientGoogle->showhideColumn($sheetID,6,8,'F');
                                    $clientGoogle->showhideColumn($sheetID,8,13,'F');
                                }
                            }
                            /** NEW OPTIONS FOR ENABLE PHONE AND MAIL ADDRESS */

                        }

                        if(!empty($advance_information) && trim($advance_information) != '') 
                        {
                            $campaignInformation = campaignInformation::where('status', 'active')
                                                                      ->where('campaign_type','enhance') 
                                                                      ->get();
                            $campaignInformationMerge = [];
                            foreach($campaignInformation as $item)
                            {
                                $descriptionArray = json_decode($item->description, true);
                                $campaignInformationMerge  = array_merge($campaignInformationMerge, $descriptionArray);
                            }

                            $oldHeader = $clientGoogle->getHeader($spreadSheetID);

                            // info('', [
                            //     'oldHeader' => $oldHeader,
                            //     'campaignInformationMerge' => $campaignInformationMerge,
                            // ]);

                            // cek apakah new header belum ada sama sekali
                            $headerAdvanceInformationExists = false;
                            foreach($oldHeader as $item)
                            {
                                if(in_array($item, $campaignInformationMerge)) {
                                    $headerAdvanceInformationExists = true;
                                }
                            }

                            // info('', [
                            //     'headerAdvanceInformationExists' => $headerAdvanceInformationExists
                            // ]);

                            // jika belum ada sama sekali, add header
                            if(!$headerAdvanceInformationExists)
                            {
                                // info('jika belum ada sama sekali, add header');
                                $result = $clientGoogle->addHeader($spreadSheetID, $campaignInformationMerge);
                                
                                // sembunyikan campaign information yang tidak dipilih
                                $advanceInformationId = explode(',', $advance_information);
                                $campaignInformationNotInId = campaignInformation::whereNotIn('id', $advanceInformationId)
                                                                                 ->where('status', 'active')
                                                                                 ->where('campaign_type','enhance')
                                                                                 ->get();
                                foreach($campaignInformationNotInId as $item)
                                {
                                    $startIndex = intval($item->start_index);

                                    $countDescription = count(json_decode($item->description, true));
                                    $endIndex = intval($startIndex + $countDescription);
                                    // info(['startIndex' => $startIndex, 'endIndex' => $endIndex]);

                                    // $endIndex = intval($item->end_index + 1);
                                    // info("sembunyikan {$item->type}", ['startIndex' => $startIndex, 'endIndex' => $endIndex]);
                                    $clientGoogle->showhideColumn($sheetID, $startIndex, $endIndex, 'T');
                                }
                            }
                            // jika sudah ada maka check untuk synchronized field
                            else
                            {
                                // info('jika sudah ada maka check untuk synchronized field');

                                $existingHeader = [
                                    "ID",
                                    "ClickDate",
                                    "First Name",
                                    "Last Name",
                                    "Email1",
                                    "Email2",
                                    "Phone1",
                                    "Phone2",
                                    "Address1",
                                    "Address2",
                                    "City",
                                    "State",
                                    "Zipcode",
                                    "Keyword"
                                ];
                                $newHeader = array_merge($existingHeader, $campaignInformationMerge);

                                $different = array_diff($newHeader, $oldHeader);
                                $indexOfDifferent = array_keys($different);

                                // info('', [
                                //     'indexOfDifferent' => $indexOfDifferent,
                                //     'newHeader' => $newHeader,
                                //     'oldHeader' => $oldHeader
                                // ]);

                                // jika indexOfDifferent lebih dari 0, maka ada perbedaan field dan harus di synchronized
                                if(count($indexOfDifferent) > 0) 
                                {
                                    // info('jika indexOfDifferent > 0, maka ada perbedaan field dan harus di synchronized');
                                    foreach($indexOfDifferent as $item)
                                    {
                                        // info('jalankan function insertColumnInSheet');
                                        $clientGoogle->insertColumnInSheet($spreadSheetID, $sheetID, $item);
                                    }
                                    
                                    // info('jalankan function updateHeader');
                                    $clientGoogle->updateHeader($spreadSheetID, $newHeader);

                                    // sembunyian colum yang tidak di uncheck
                                    $advanceInformationId = explode(',', $advance_information);
                                    $campaignInformationNotInId = CampaignInformation::whereNotIn('id', $advanceInformationId)
                                                                                     ->where('status', 'active')
                                                                                     ->where('campaign_type','enhance')
                                                                                     ->get();
                                    
                                    foreach($campaignInformationNotInId as $item)
                                    {
                                        $startIndex = intval($item->start_index);

                                        $countDescription = count(json_decode($item->description, true));
                                        $endIndex = intval($startIndex + $countDescription);
                                        // info(['startIndex' => $startIndex, 'endIndex' => $endIndex]);

                                        // $endIndex = intval($item->end_index + 1);
                                        // info('jalankan function showhideColumn');
                                        $clientGoogle->showhideColumn($sheetID, $startIndex, $endIndex, 'T');
                                    }
                                    // sembunyian colum yang tidak di uncheck
                                }
                                // jika indexOfDifferent 0, maka field sudah synchronized seperti di db
                                else 
                                {
                                    // info("jika indexOfDifferent =  0, maka field sudah synchronized seperti di db");
                                    $advanceInformationId = explode(',', $advance_information);
                                    $campaignInformationInId = campaignInformation::whereIn('id', $advanceInformationId)
                                                                                  ->where('status', 'active')
                                                                                  ->where('campaign_type','enhance')
                                                                                  ->get();
                                    foreach($campaignInformationInId as $item)
                                    {
                                        $startIndex = intval($item->start_index);

                                        $countDescription = count(json_decode($item->description, true));
                                        $endIndex = intval($startIndex + $countDescription);
                                        // info(['startIndex' => $startIndex, 'endIndex' => $endIndex]);

                                        // $endIndex = intval($item->end_index + 1);
                                        // info("munculkan {$item->type}", ['startIndex' => $startIndex, 'endIndex' => $endIndex]);
                                        $clientGoogle->showhideColumn($sheetID, $startIndex, $endIndex, 'F');
                                    }
                                }
                            }
                        }
            } 
        /** UPDATE ON SPREADSHEET */
        }catch(Exception $e) {
            Log::warning("Google Spreadsheet Update Client - UpdateClientLocator Failed (L1949) ErrMsg:" . $e->getMessage() . " CampaignID:" . $updateclient->leadspeek_api_id . ' Campaign Name:' . (isset($request->campaignName))?$request->campaignName:'');
        }
        // /** UPDATE ON SIMPLIFI, ONLY LOCATOR */

        // if (trim($sifiorganizationID) != "" && trim($sificampaignID) != "") {

        //     /** CHECK IF CAMPAIGN IS RUNNING NEED TO PAUSE FIRST */
        //     if ($updateclient->active == 'T' && $updateclient->disabled == 'F' && $updateclient->active_user == 'T') {
        //         $camp = $this->startPause_campaign($sifiorganizationID,$sificampaignID,'pause');
        //     }
        //     /** CHECK IF CAMPAIGN IS RUNNING NEED TO PAUSE FIRST */

        //     $_state = explode(",",trim($locatorstatesifi));
        //     $_cities = explode(",",trim($locatorcitysifi));
            
        //     $_geotarget = array_merge($_state,$_cities);
        //     $_postalcode = array(); 
        //     $tmp_postcode = explode(',',$locatorzip);
        //     foreach($tmp_postcode as $postcode) {
        //         if($postcode != '') {
        //             array_push($_postalcode,array("postal_code"=>$postcode, "country_code"=>"USA"));
        //         }
        //     }

        //     if ($nationaltargeting == 'T') {
        //         $_postalcode = array(); 
        //         $_geotarget = array('8180');
        //     }
        //     $this->updateGeoTargetsPostalCode($sifiorganizationID,$sificampaignID,$_geotarget,$_postalcode);

        //     /** KEYWORDS */
        //     $_keywords = array(); 
        //     $tmp_keywords = $request->locatorkeyword;
        //     foreach($tmp_keywords as $keyword) {
        //         if(trim($keyword) != "") {
        //             array_push($_keywords,array("name"=>trim($keyword), "max_bid"=>""));
        //         }
        //     }

        //     /** FOR CONTEXTUAL */
        //     if (is_array($request->locatorkeywordcontextual)) {
        //         $tmp_keywords = $request->locatorkeywordcontextual;
        //         foreach($tmp_keywords as $keyword) {
        //             if(trim($keyword) != "") {
        //                 array_push($_keywords,array("name"=>"!" . trim($keyword), "max_bid"=>""));
        //             }
        //         }
        //     }
        //     /** FOR CONTEXTUAL */

        //     $this->createKeywords($sificampaignID,$_keywords,$sifiorganizationID);
        //     /** KEYWORDS */

        //     /** UPDATE BUDGET PLAN */
        //     $start_date = $startdatecampaign;
        //     $end_date = $enddatecampaign;
            
        //     //$tmp = strtotime($end_date) - strtotime($start_date);
        //     //$diffdays = abs(round($tmp / 86400));
        //     //$totalbudget = number_format(1 * $diffdays,1,".","");
           
        //     $this->updateDefaultBudgetPlan($sifiorganizationID,$sificampaignID,$oristartdatecampaign,$orienddatecampaign);
        //     /** UPDATE BUDGET PLAN */

        //     /** UPDATE RECENCY */
        //     //$this->updateRecencyCampaign($sifiorganizationID,$sificampaignID,"2");
        //     /** UPDATE RECENCY */

        //     /** UPDATE DEVICE TYPE */
        //     //$this->createDeviceType($sificampaignID,36);
        //     /** UPDATE DEVICE TYPE */

        //     /** CHECK IF CAMPAIGN IS RUNNING AFTER PAUSE NEED TO START AGAIN */
        //     if ($updateclient->active == 'T' && $updateclient->disabled == 'F' && $updateclient->active_user == 'T') {
        //         $camp = $this->startPause_campaign($sifiorganizationID,$sificampaignID,'activate');
        //     }
        //     /** CHECK IF CAMPAIGN IS RUNNING AFTER PAUSE NEED TO START AGAIN */
        // }
        // /** UPDATE ON SIMPLIFI, ONLY LOCATOR */

        $updateclient = LeadspeekUser::find($request->leadspeekID);
        $updateclient->leadspeek_locator_zip = $locatorzip;
        $updateclient->leadspeek_locator_desc = $locatordesc;
        $updateclient->leadspeek_locator_keyword = $locatorkeyword;
        $updateclient->leadspeek_locator_keyword_contextual = $locatorkeywordcontextual;
        $updateclient->leadspeek_locator_state = $locatorstate;
        $updateclient->leadspeek_locator_state_simplifi = $locatorstatesifi;
        $updateclient->leadspeek_locator_city = $locatorcity;
        $updateclient->leadspeek_locator_city_simplifi = $locatorcitysifi;
        $updateclient->leadspeek_locator_state = $locatorstate;
        $updateclient->leadspeek_locator_require = $locatorrequire;
        // if (trim($locatorrequire) != trim($updateclient->leadspeek_locator_require)) {
        //     $updateclient->cost_perlead = $cost_perlead;
        // }
        $updateclient->hide_phone = $hidePhone;
        $updateclient->campaign_startdate = $startdatecampaign;
        $updateclient->campaign_enddate = $enddatecampaign;
        $updateclient->ori_campaign_startdate = $oristartdatecampaign;
        $updateclient->ori_campaign_enddate = $orienddatecampaign;
        $updateclient->lp_enddate = $enddatecampaign;
        $updateclient->timezone = $timezone;
        $updateclient->national_targeting = $nationaltargeting;
        $updateclient->location_target = $locationtarget;
        $updateclient->phoneenabled = $phoneenabled;
        $updateclient->homeaddressenabled = $homeaddressenabled;
        $updateclient->require_email = $requireemailaddress;
        $updateclient->reidentification_type = $reidentificationtype;
        $updateclient->advance_information = $advance_information;
        $updateclient->campaign_information_type = $campaign_information_type;

        $updateclient->save();

        /** NOTIFY ADMIN */
            $adminnotify = explode(',',$updateclient->admin_notify_to);
            /** FIND ADMIN EMAIL */
            $tmp = User::select('email')->whereIn('id', $adminnotify)->get();
            $adminEmail = array();
            foreach($tmp as $ad) {
                array_push($adminEmail,$ad['email']);
            }
            //array_push($adminEmail,'serverlogs@sitesettingsapi.com');
            //array_push($adminEmail,'carrie@uncommonreach.com');
            //array_push($adminEmail,'daniel@exactmatchmarketing.com');
            /** FIND ADMIN EMAIL */

            /** FIND USER COMPANY */
            $userlist = User::select('companies.company_name','users.company_parent','users.company_root_id')
                            ->join('companies','users.company_id','=','companies.id')
                            ->where('users.id','=',$updateclient->user_id)
                            ->where('users.active','=','T')
                            ->get();
            /** FIND USER COMPANY */

            /** FIND PARENT OR AGENCY NAME */
            $agency = Company::select('company_name')->where('id','=',$userlist[0]['company_parent'])->get();
            $agencyName = "";
            if (count($agency) > 0) {
                $agencyName = ' (' . $agency[0]['company_name'] . ')';
            }
            /** FIND PARENT OR AGENCY NAME */

            /** GET PRODUCT NAME */
            $productlocalname = 'Site ID';
            $productlocatorname = 'Search ID';

            $rootcompanysetting = CompanySetting::where('company_id',trim($userlist[0]['company_root_id']))->whereEncrypted('setting_name','rootcustomsidebarleadmenu')->get();
            if (count($rootcompanysetting) > 0) {
                $productname = json_decode($rootcompanysetting[0]['setting_value']);
                $productlocalname = $productname->local->name;
                $productlocatorname = $productname->locator->name;
            }

            if($this->checkwhitelabellingpackage(trim($companyID))) {
                $companysetting = CompanySetting::where('company_id',trim($companyID))->whereEncrypted('setting_name','customsidebarleadmenu')->get();
                if (count($companysetting) > 0) {
                    $productname = json_decode($companysetting[0]['setting_value']);
                    $productlocalname = $productname->local->name;
                    $productlocatorname = $productname->locator->name;
                }
            }
            /** GET PRODUCT NAME */


            $details = [
                'name'  => $userlist[0]['company_name'],
                'locatorzip' => trim($locatorzip),
                'locatordesc' => trim($locatordesc),
                'locatorkeyword' => trim($locatorkeyword),
                'locatorkeywordcontextual' => trim($locatorkeywordcontextual),
                'locatorstate' => trim($locatorstate),
                'locatorcities' => trim($locatorcity),
                'requiredfields' => trim($locatorrequire),
                'productlocatorname' => $productlocatorname,
            ];

            $from = [
                'address' => 'noreply@sitesettingsapi.com',
                'name' => 'Update Notification',
                'replyto' => 'support@sitesettingsapi.com',
            ];

            /** LOG ACTION */
            $descLogs = "Campaign Id: {$updateclient->leadspeek_api_id} | ";
            $fromTargetLocation = '';

            // Determine initial target location
            if ($prevClient->national_targeting === 'T') {
                $fromTargetLocation = 'National Targeting';
            } elseif ($prevClient->leadspeek_locator_state) {
                $fromTargetLocation = 'States';
            } elseif ($prevClient->leadspeek_locator_zip) {
                $fromTargetLocation = 'Zip Code';
            }

            // Update target location
            if ($updateclient->national_targeting === 'T') {
                if ($fromTargetLocation !== "National Targeting") {
                    $descLogs .= "Target Location: Update values from {$fromTargetLocation} to National Targeting | ";
                } else {
                    $descLogs .= "Target Location: No changes, value remains {$fromTargetLocation} | ";
                }
            } elseif ($updateclient->leadspeek_locator_state) {
                if ($fromTargetLocation !== "States") {
                    $descLogs .= "Target Location: Update values from {$fromTargetLocation} to States | ";
                } else {
                    $descLogs .= "Target Location: No changes, value remains {$fromTargetLocation} | ";
                }

                if ($prevClient->leadspeek_locator_state !== $updateclient->leadspeek_locator_state) {
                    $prevState = $prevClient->leadspeek_locator_state ?: 'empty';
                    $newState = $updateclient->leadspeek_locator_state ?: 'empty';

                    $descLogs .= "States: Update values from {$prevState} to {$newState} | ";
                } else {
                    $prevState = $prevClient->leadspeek_locator_state ?: 'empty';
                    $descLogs .= "States: No changes, value remains {$prevState} | ";
                }

                if ($prevClient->leadspeek_locator_city !== $updateclient->leadspeek_locator_city) {
                    $prevCities = $prevClient->leadspeek_locator_city ?: 'empty';
                    $newCities = $updateclient->leadspeek_locator_city ?: 'empty';

                    $descLogs .= "Cities: Update values from {$prevCities} to {$newCities} | ";
                } else {
                    $prevCities = $prevClient->leadspeek_locator_city ?: 'empty';
                    $descLogs .= "Cities: No changes, value remains {$prevCities} | ";
                }

            } elseif ($updateclient->leadspeek_locator_zip) {
                if ($fromTargetLocation !== "Zip Code") {
                    $descLogs .= "Target Location: Update values from {$fromTargetLocation} to Zip Code | ";
                } else {
                    $descLogs .= "Target Location: No changes, value remains {$fromTargetLocation} | ";
                }

                if ($prevClient->leadspeek_locator_zip !== $updateclient->leadspeek_locator_zip) {
                    $prevZipCode = $prevClient->leadspeek_locator_zip ?: 'empty';
                    $newZipCode = $updateclient->leadspeek_locator_zip ?: 'empty';
                    $descLogs .= "Zip Code: Update values from {$prevZipCode} to {$newZipCode} | ";
                } else {
                    $prevZipCode = $prevClient->leadspeek_locator_zip ?: 'empty';
                    $descLogs .= "Zip Code: No changes, value remains {$prevZipCode} | ";
                }
            }

            // Additional fields logging
            $fields = [
                'campaign_name' => 'Campaign Name',
                'admin_notify_to' => 'Admin Notify To',
                'campaign_information_type' => 'Campaign Information Type',
                'advance_information' => 'List Advanced Information Id',
                'phoneenabled' => 'Phone Enabled',
                'homeaddressenabled' => 'Home Address Enabled',
                'require_email' => 'Require Email Address',
                'reidentification_type' => 'Re-identification Type',
                'applyreidentificationall' => 'Apply Reidentification',
                'leadspeek_locator_keyword' => 'Keyword',
            ];

            foreach ($fields as $field => $label) {
                if ($prevClient->$field !== $updateclient->$field) {
                    $prevFieldClient = $prevClient->$field ?: 'empty';
                    $newFieldClient = $updateclient->$field ?: 'empty';

                    $descLogs .= "{$label}: Update values from {$prevFieldClient} to {$newFieldClient} | ";
                } else {
                    $prevFieldClient = $prevClient->$field ?: 'empty';
                    $descLogs .= "{$label}: No changes, value remains {$prevFieldClient} | ";
                }
            }

            if($descLogs == "Campaign Id: {$updateclient->leadspeek_api_id} | "){
                $descLogs = "Campaign Id: {$updateclient->leadspeek_api_id} | No updates were made as all values remain unchanged.";
            }

            if($inOpenApi === true) {
                $descLogs .= "in open api";
            }

            // Clean up and finalize log
            $descLogs = rtrim($descLogs, ' |');
            $ipAddress = ($inOpenApi === true) ? "inopenapi" : ($request->header('Clientip') ?? $request->ip());
            
            $user_id = null;
            if($inOpenApi === true) {
                if(isset($request->companyID) && $request->companyID != '') {
                    $user_id = User::where('company_id', $request->companyID)->where('user_type', 'userdownline')->where('active', 'T')->value('id');
                }
            } else {
                $user_id = auth()->user()->id;
            }

            $target_userid = $updateclient->user_id;
            $this->logUserAction($user_id, "Edit Campaign Enhance", $descLogs, $ipAddress, $target_userid);
            // $this->logUserAction($user_id, "Edit Campaign Enhance", $descLogs, $ipAddress);
            /** LOG ACTION */


            //$this->send_email(array('carrie@uncommonreach.com'),$from,$userlist[0]['company_name'] . '-' . $updateclient->campaign_name . ' #' . $leadspeek_apiID . $agencyName . ' updated their ' . $productlocatorname . ' information',$details,array(),'emails.locatorinfoupdate',$companyID);

        /** NOTIFY ADMIN */

        return response()->json(array('result'=>'success', 'company'=>$userlist[0]['company_name'], 'detais'=>$details));
    }

    public function updateclientb2b(Request $request, $prevClient) {
        $locatorzip = (isset($request->locatorzip))?str_replace([PHP_EOL, "\r\n", "\n", "\r"], ',', $request->locatorzip) :'';
        $locatordesc = (isset($request->locatordesc))?$request->locatordesc:'';
        $locatorkeyword = (isset($request->locatorkeyword))?implode(",",$request->locatorkeyword):'';
        $locatorkeywordcontextual = (isset($request->locatorkeywordcontextual))?implode(",",$request->locatorkeywordcontextual):'';
        $sifiorganizationID = (isset($request->externalorganizationid))?$request->externalorganizationid:'';
        $sificampaignID = (isset($request->externalcampaign))?$request->externalcampaign:'';
        $companyID = (isset($request->companyID))?$request->companyID:'';

        $locatorrequire = (isset($request->locatorrequire))?trim($request->locatorrequire):'';
        $locatorstate = "";
        $locatorstatesifi = "";
        $locatorcity = "";
        $locatorcitysifi = "";
        $nationaltargeting = (isset($request->nationaltargeting) && $request->nationaltargeting)?'T':'F';

        $phoneenabled = (isset($request->phoneenabled) && $request->phoneenabled)?'T':'F';
        $homeaddressenabled = (isset($request->homeaddressenabled) && $request->homeaddressenabled)?'T':'F';
        $requireemailaddress = (isset($request->requireemailaddress) && $request->requireemailaddress)?'T':'F';

        $reidentificationtype = (isset($request->reidentificationtype))?$request->reidentificationtype:'never';

        $startdatecampaign = (isset($request->startdatecampaign))?$request->startdatecampaign:'';
        $enddatecampaign = (isset($request->enddatecampaign))?$request->enddatecampaign:'';
        $oristartdatecampaign = (isset($request->oristartdatecampaign))?$request->oristartdatecampaign:'';
        $orienddatecampaign = (isset($request->orienddatecampaign))?$request->orienddatecampaign:'';
        $locationtarget = (isset($request->locationtarget))?$request->locationtarget:'Focus';

        $timezone = (isset($request->timezone))?$request->timezone:'America/Chicago';

        $inOpenApi = (isset($request->inOpenApi))?$request->inOpenApi:"";

        if (isset($request->locatorstate) && count($request->locatorstate) > 0) {
            $locatorstate = rtrim(implode(",", $request->locatorstate), ",");
        }else{
            $locatorstate = "";
        }

        if (isset($request->locatorcity) && count($request->locatorcity) > 0) {
            $locatorcity = rtrim(implode(",", $request->locatorcity), ",");
        }else{
            $locatorcity = "";
        }

        $hidePhone = 'T';
        $_tmp = explode(",",$locatorrequire);
        if (in_array("Phone",$_tmp)) {
            $hidePhone ='F';
        }

        $updateclient = LeadspeekUser::find($request->leadspeekID);

        $tryseraMethod = (isset($updateclient->trysera) && $updateclient->trysera == 'T')?true:false;
        
        $masterCostAgency = CompanySetting::where('company_id',$companyID)->whereEncrypted('setting_name','agencydefaultprice')->get();
        if (count($masterCostAgency) > 0) {
            $masterCostAgency = json_decode($masterCostAgency[0]['setting_value']);
        }else{
            $masterCostAgency = '';
        }

        $cost_perlead = 2;
       
        if ($locatorrequire == "FirstName,LastName") {
            $cost_perlead = (isset($masterCostAgency->locatorlead->FirstName_LastName))?$masterCostAgency->locatorlead->FirstName_LastName:'1.50';
        }else if ($locatorrequire == "FirstName,LastName,MailingAddress") {
            $cost_perlead = (isset($masterCostAgency->locatorlead->FirstName_LastName_MailingAddress))?$masterCostAgency->locatorlead->FirstName_LastName_MailingAddress:'2';
        }else if ($locatorrequire == "FirstName,LastName,MailingAddress,Phone") {
            $cost_perlead = (isset($masterCostAgency->locatorlead->FirstName_LastName_MailingAddress_Phone))?$masterCostAgency->locatorlead->FirstName_LastName_MailingAddress_Phone:'3';
        }

        /** UPDATE ON SPREADSHEET */
        $advance_information = "";
        $campaign_information_type = isset($request->campaign_information_type)?$request->campaign_information_type:'basic';
        if(isset($request->listAdvanceInformationId) && is_array($request->listAdvanceInformationId) && count($request->listAdvanceInformationId) > 0) {
            $advance_information = implode(',', $request->listAdvanceInformationId);
        }
        
        $spreadSheetID = isset($updateclient->spreadsheet_id)?trim($updateclient->spreadsheet_id):'';
        $companyID = $updateclient->company_id;
        $leadspeek_apiID = $updateclient->leadspeek_api_id;
        $leadspeekType = $updateclient->leadspeek_type;

        try {
            /** CHECK IF HAVE GOOGLE CONNECTION */
            $check_connect_google = $this->googlespreadsheet_checkconnect($request, $updateclient->company_id);
            $data = $check_connect_google->getData(true); // true agar jadi array, bukan stdClass
            $isConnected_google = $data['googleSpreadsheetConnected'] ?? false;

            if($isConnected_google && $spreadSheetID != '') {
            /** CHECK IF HAVE GOOGLE CONNECTION */
                $clientGoogle = new GoogleSheet($companyID,$this->_moduleID,$this->_settingTokenName,'',true,$updateclient->leadspeek_api_id ?? "",$updateclient->leadspeek_type ?? "", __FUNCTION__);
                $clientGoogle->setSpreadSheetID($spreadSheetID);
                        
                $sheetID = $clientGoogle->getSheetID('2021');
                        if ($sheetID === '') {
                            $sheetdefault = explode('|',$clientGoogle->getDefaultSheetIDName());
                            $sheetID = $sheetdefault[0];
                            $clientGoogle->setSheetName($sheetdefault[1]);
                        }else{
                            $clientGoogle->setSheetName('2021');
                        }

                        $settingJson = DB::table('global_settings')
                                        ->where('setting_name', 'spreadsheet_b2b_ordering')
                                        ->value('setting_value');

                        $dtJson = json_decode($settingJson, true);
                        $startDate_spreadsheet = isset($dtJson[0]['start-date']) ? Carbon::parse($dtJson[0]['start-date']) : null;
                        $userCreatedAt = Carbon::parse($updateclient->created_at);
                        //// cek setingan di global seting.. 
                        // ketika today lebih dari tgl yg di global seting, pakai data dan indexing baru, 
                        // else pakai data dan indexing lama
                        if ($startDate_spreadsheet && $userCreatedAt->greaterThanOrEqualTo($startDate_spreadsheet)) {
                            
                            if ($locatorrequire == "FirstName,LastName") {
                                if ($tryseraMethod) {
                                    $clientGoogle->showhideColumn($sheetID,7,8,'T');
                                    $clientGoogle->showhideColumn($sheetID,10,11,'T');
                                    $clientGoogle->showhideColumn($sheetID,11,12,'T');
                                }else{
                                    $clientGoogle->showhideColumn($sheetID,12,13,'T');
                                    $clientGoogle->showhideColumn($sheetID,13,18,'F');
                                }
                            }else if ($locatorrequire == "FirstName,LastName,MailingAddress") {
                                if ($tryseraMethod) {
                                    $clientGoogle->showhideColumn($sheetID,7,8,'T');
                                    $clientGoogle->showhideColumn($sheetID,10,11,'F');
                                    $clientGoogle->showhideColumn($sheetID,11,12,'F');
                                }else{
                                    $clientGoogle->showhideColumn($sheetID,12,13,'T');
                                    $clientGoogle->showhideColumn($sheetID,13,18,'F');
                                }
                            }else if ($locatorrequire == "FirstName,LastName,MailingAddress,Phone") {
                                /** NEW OPTIONS FOR ENABLE PHONE AND MAIL ADDRESS */
                                if($phoneenabled == 'T' && $homeaddressenabled == 'F') {
                                    if ($tryseraMethod) {
                                        $clientGoogle->showhideColumn($sheetID,7,8,'F');
                                        $clientGoogle->showhideColumn($sheetID,10,11,'T');
                                        $clientGoogle->showhideColumn($sheetID,11,12,'T');
                                        $clientGoogle->showhideColumn($sheetID,12,13,'T');
                                        $clientGoogle->showhideColumn($sheetID,13,14,'T');
                                        $clientGoogle->showhideColumn($sheetID,14,15,'T');
                                    }else{
                                        $clientGoogle->showhideColumn($sheetID,5,6,'F');
                                        $clientGoogle->showhideColumn($sheetID,12,13,'F');
                                        $clientGoogle->showhideColumn($sheetID,13,18,'T');
                                        // if($leadspeekType == 'b2b') {
                                        //     $clientGoogle->showhideColumn($sheetID,16,17,'F');
                                        // }
                                    }
                                }else if($phoneenabled == 'F' && $homeaddressenabled == 'T') {
                                    if ($tryseraMethod) {
                                        $clientGoogle->showhideColumn($sheetID,7,8,'T');
                                        $clientGoogle->showhideColumn($sheetID,10,11,'F');
                                        $clientGoogle->showhideColumn($sheetID,11,12,'F');
                                        $clientGoogle->showhideColumn($sheetID,12,13,'F');
                                        $clientGoogle->showhideColumn($sheetID,13,14,'F');
                                        $clientGoogle->showhideColumn($sheetID,14,15,'F');
                                    }else{
                                        $clientGoogle->showhideColumn($sheetID,5,6,'T');
                                        $clientGoogle->showhideColumn($sheetID,12,13,'T');
                                        $clientGoogle->showhideColumn($sheetID,13,18,'F');
                                        // if($leadspeekType == 'b2b') {
                                        //     $clientGoogle->showhideColumn($sheetID,16,17,'T');
                                        // }
                                    }
                                }else if($phoneenabled == 'F' && $homeaddressenabled == 'F') {
                                    if ($tryseraMethod) {
                                        $clientGoogle->showhideColumn($sheetID,7,8,'T');
                                        $clientGoogle->showhideColumn($sheetID,10,11,'T');
                                        $clientGoogle->showhideColumn($sheetID,11,12,'T');
                                        $clientGoogle->showhideColumn($sheetID,12,13,'T');
                                        $clientGoogle->showhideColumn($sheetID,13,14,'T');
                                        $clientGoogle->showhideColumn($sheetID,14,15,'T');
                                    }else{
                                        $clientGoogle->showhideColumn($sheetID,5,6,'T');
                                        $clientGoogle->showhideColumn($sheetID,12,13,'T');
                                        $clientGoogle->showhideColumn($sheetID,13,18,'T');
                                        // if($leadspeekType == 'b2b') {
                                        //     $clientGoogle->showhideColumn($sheetID,16,17,'T');
                                        // }
                                    }
                                }else{
                                    if ($tryseraMethod) {
                                        $clientGoogle->showhideColumn($sheetID,7,8,'F');
                                        $clientGoogle->showhideColumn($sheetID,10,11,'F');
                                        $clientGoogle->showhideColumn($sheetID,11,12,'F');
                                        $clientGoogle->showhideColumn($sheetID,12,13,'F');
                                        $clientGoogle->showhideColumn($sheetID,13,14,'F');
                                        $clientGoogle->showhideColumn($sheetID,14,15,'F');
                                    }else{
                                        $clientGoogle->showhideColumn($sheetID,5,6,'F');
                                        $clientGoogle->showhideColumn($sheetID,12,13,'F');
                                        $clientGoogle->showhideColumn($sheetID,13,18,'F');
                                        // if($leadspeekType == 'b2b') {
                                        //     $clientGoogle->showhideColumn($sheetID,16,17,'F');
                                        // }
                                    }
                                }
                                /** NEW OPTIONS FOR ENABLE PHONE AND MAIL ADDRESS */

                            }

                            if(!empty($advance_information) && trim($advance_information) != '') 
                            {
                                $campaignInformation = campaignInformation::where('status', 'active')
                                                                        ->where('campaign_type','b2b')
                                                                        ->orderBy('start_index', 'asc')
                                                                        ->get();
                                $campaignInformationMerge = [];
                                foreach($campaignInformation as $item)
                                {
                                    $descriptionArray = json_decode($item->description, true);
                                    $index = $item->start_index;
                                    // $campaignInformationMerge  = array_merge($campaignInformationMerge, $descriptionArray);
                                    $campaignInformationMerge[$index] = $descriptionArray[0];
                                }

                                $oldHeader = $clientGoogle->getHeader($spreadSheetID);

                                // info('', [
                                //     'oldHeader' => $oldHeader,
                                //     'campaignInformationMerge' => $campaignInformationMerge,
                                // ]);

                                // cek apakah new header belum ada sama sekali
                                $headerAdvanceInformationExists = false;
                                foreach($oldHeader as $item)
                                {
                                    if(in_array($item, $campaignInformationMerge)) {
                                        $headerAdvanceInformationExists = true;
                                    }
                                }

                                // info('', [
                                //     'headerAdvanceInformationExists' => $headerAdvanceInformationExists
                                // ]);

                                // jika belum ada sama sekali, add header
                                if(!$headerAdvanceInformationExists)
                                {
                                    // info('jika belum ada sama sekali, add header');
                                    $result = $clientGoogle->addHeader($spreadSheetID, $campaignInformationMerge);
                                    
                                    // sembunyikan campaign information yang tidak dipilih
                                    $advanceInformationId = explode(',', $advance_information);
                                    $campaignInformationNotInId = campaignInformation::whereNotIn('id', $advanceInformationId)
                                                                                    ->where('status', 'active')
                                                                                    ->where('campaign_type','b2b')
                                                                                    ->orderBy('start_index', 'asc')
                                                                                    ->get();
                                    foreach($campaignInformationNotInId as $item)
                                    {
                                        $startIndex = intval($item->start_index);

                                        $countDescription = count(json_decode($item->description, true));
                                        $endIndex = intval($startIndex + $countDescription);
                                        // info(['startIndex' => $startIndex, 'endIndex' => $endIndex]);

                                        // $endIndex = intval($item->end_index + 1);
                                        // info("sembunyikan {$item->type}", ['startIndex' => $startIndex, 'endIndex' => $endIndex]);
                                        $clientGoogle->showhideColumn($sheetID, $startIndex, $endIndex, 'T');
                                    }
                                }
                                // jika sudah ada maka check untuk synchronized field
                                else
                                {
                                    // info('jika sudah ada maka check untuk synchronized field');

                                    $existingHeader = [
                                        0 => "ID",
                                        2 => "First Name",
                                        3 => "Last Name",
                                        4 => "Company Email",
                                        11 => "ClickDate",
                                        12 => "Phone1",
                                        13 => "Address1",
                                        14 => "Address2",
                                        15 => "City",
                                        16 => "State",
                                        17 => "Zipcode",
                                        18 => "Keyword"
                                    ];
                                    // $newHeader = array_merge($existingHeader, $campaignInformationMerge);
                                    $newHeader = $existingHeader + $campaignInformationMerge;
                                    ksort($newHeader);                  //// urutin index arraynya

                                    $different = array_diff($newHeader, $oldHeader);
                                    $indexOfDifferent = array_keys($different);

                                    // info('', [
                                    //     'indexOfDifferent' => $indexOfDifferent,
                                    //     'newHeader' => $newHeader,
                                    //     'oldHeader' => $oldHeader,
                                    //     'different' => $different
                                    // ]);

                                    // jika indexOfDifferent lebih dari 0, maka ada perbedaan field dan harus di synchronized
                                    if(count($indexOfDifferent) > 0) 
                                    {
                                        // info('jika indexOfDifferent > 0, maka ada perbedaan field dan harus di synchronized');
                                        foreach($indexOfDifferent as $item)
                                        {
                                            // info('jalankan function insertColumnInSheet');
                                            $clientGoogle->insertColumnInSheet($spreadSheetID, $sheetID, $item);
                                        }
                                        
                                        // info('jalankan function updateHeader');
                                        $clientGoogle->updateHeader($spreadSheetID, $newHeader);

                                        // sembunyian colum yang tidak di uncheck
                                        $advanceInformationId = explode(',', $advance_information);
                                        $campaignInformationNotInId = CampaignInformation::whereNotIn('id', $advanceInformationId)
                                                                                        ->where('status', 'active')
                                                                                        ->where('campaign_type','b2b')
                                                                                        ->orderBy('start_index', 'asc')
                                                                                        ->get();
                                        
                                        foreach($campaignInformationNotInId as $item)
                                        {
                                            $startIndex = intval($item->start_index);

                                            $countDescription = count(json_decode($item->description, true));
                                            $endIndex = intval($startIndex + $countDescription);
                                            
                                            $clientGoogle->showhideColumn($sheetID, $startIndex, $endIndex, 'T');
                                        }
                                        // sembunyian colum yang tidak di uncheck
                                    }
                                    // jika indexOfDifferent 0, maka field sudah synchronized seperti di db
                                    else 
                                    {
                                        // info("jika indexOfDifferent =  0, maka field sudah synchronized seperti di db");
                                        $advanceInformationId = explode(',', $advance_information);
                                        $campaignInformationInId = campaignInformation::whereIn('id', $advanceInformationId)
                                                                                    ->where('status', 'active')
                                                                                    ->where('campaign_type','b2b')
                                                                                    ->orderBy('start_index', 'asc')
                                                                                    ->get();
                                        foreach($campaignInformationInId as $item)
                                        {
                                            $startIndex = intval($item->start_index);

                                            $countDescription = count(json_decode($item->description, true));
                                            $endIndex = intval($startIndex + $countDescription);
                                            
                                            $clientGoogle->showhideColumn($sheetID, $startIndex, $endIndex, 'F');
                                        }
                                    }
                                }
                            }
                        } else {
                            if ($locatorrequire == "FirstName,LastName") {
                                if ($tryseraMethod) {
                                    $clientGoogle->showhideColumn($sheetID,7,8,'T');
                                    $clientGoogle->showhideColumn($sheetID,10,11,'T');
                                    $clientGoogle->showhideColumn($sheetID,11,12,'T');
                                }else{
                                    $clientGoogle->showhideColumn($sheetID,6,8,'T');
                                    $clientGoogle->showhideColumn($sheetID,8,13,'F');
                                }
                            }else if ($locatorrequire == "FirstName,LastName,MailingAddress") {
                                if ($tryseraMethod) {
                                    $clientGoogle->showhideColumn($sheetID,7,8,'T');
                                    $clientGoogle->showhideColumn($sheetID,10,11,'F');
                                    $clientGoogle->showhideColumn($sheetID,11,12,'F');
                                }else{
                                    $clientGoogle->showhideColumn($sheetID,6,8,'T');
                                    $clientGoogle->showhideColumn($sheetID,8,13,'F');
                                }
                            }else if ($locatorrequire == "FirstName,LastName,MailingAddress,Phone") {
                                /** NEW OPTIONS FOR ENABLE PHONE AND MAIL ADDRESS */
                                if($phoneenabled == 'T' && $homeaddressenabled == 'F') {
                                    if ($tryseraMethod) {
                                        $clientGoogle->showhideColumn($sheetID,7,8,'F');
                                        $clientGoogle->showhideColumn($sheetID,10,11,'T');
                                        $clientGoogle->showhideColumn($sheetID,11,12,'T');
                                        $clientGoogle->showhideColumn($sheetID,12,13,'T');
                                        $clientGoogle->showhideColumn($sheetID,13,14,'T');
                                        $clientGoogle->showhideColumn($sheetID,14,15,'T');
                                    }else{
                                        $clientGoogle->showhideColumn($sheetID,6,8,'F');
                                        $clientGoogle->showhideColumn($sheetID,8,13,'T');
                                        if($leadspeekType == 'b2b') {
                                            $clientGoogle->showhideColumn($sheetID,16,17,'F');
                                        }
                                    }
                                }else if($phoneenabled == 'F' && $homeaddressenabled == 'T') {
                                    if ($tryseraMethod) {
                                        $clientGoogle->showhideColumn($sheetID,7,8,'T');
                                        $clientGoogle->showhideColumn($sheetID,10,11,'F');
                                        $clientGoogle->showhideColumn($sheetID,11,12,'F');
                                        $clientGoogle->showhideColumn($sheetID,12,13,'F');
                                        $clientGoogle->showhideColumn($sheetID,13,14,'F');
                                        $clientGoogle->showhideColumn($sheetID,14,15,'F');
                                    }else{
                                        $clientGoogle->showhideColumn($sheetID,6,8,'T');
                                        $clientGoogle->showhideColumn($sheetID,8,13,'F');
                                        if($leadspeekType == 'b2b') {
                                            $clientGoogle->showhideColumn($sheetID,16,17,'T');
                                        }
                                    }
                                }else if($phoneenabled == 'F' && $homeaddressenabled == 'F') {
                                    if ($tryseraMethod) {
                                        $clientGoogle->showhideColumn($sheetID,7,8,'T');
                                        $clientGoogle->showhideColumn($sheetID,10,11,'T');
                                        $clientGoogle->showhideColumn($sheetID,11,12,'T');
                                        $clientGoogle->showhideColumn($sheetID,12,13,'T');
                                        $clientGoogle->showhideColumn($sheetID,13,14,'T');
                                        $clientGoogle->showhideColumn($sheetID,14,15,'T');
                                    }else{
                                        $clientGoogle->showhideColumn($sheetID,6,8,'T');
                                        $clientGoogle->showhideColumn($sheetID,8,13,'T');
                                        if($leadspeekType == 'b2b') {
                                            $clientGoogle->showhideColumn($sheetID,16,17,'T');
                                        }
                                    }
                                }else{
                                    if ($tryseraMethod) {
                                        $clientGoogle->showhideColumn($sheetID,7,8,'F');
                                        $clientGoogle->showhideColumn($sheetID,10,11,'F');
                                        $clientGoogle->showhideColumn($sheetID,11,12,'F');
                                        $clientGoogle->showhideColumn($sheetID,12,13,'F');
                                        $clientGoogle->showhideColumn($sheetID,13,14,'F');
                                        $clientGoogle->showhideColumn($sheetID,14,15,'F');
                                    }else{
                                        $clientGoogle->showhideColumn($sheetID,6,8,'F');
                                        $clientGoogle->showhideColumn($sheetID,8,13,'F');
                                        if($leadspeekType == 'b2b') {
                                            $clientGoogle->showhideColumn($sheetID,16,17,'F');
                                        }
                                    }
                                }
                                /** NEW OPTIONS FOR ENABLE PHONE AND MAIL ADDRESS */
    
                            }

                            if(!empty($advance_information) && trim($advance_information) != '') 
                            {
                                $oldHeader = $clientGoogle->getHeader($spreadSheetID);
                                $newHeader = [
                                    0 => "ID",
                                    1 => "ClickDate",
                                    2 => "First Name",
                                    3 => "Last Name",
                                    4 => "Email1",
                                    5 => "Email2",
                                    6 => "Phone1",
                                    7 => "Phone2",
                                    8 => "Address1",
                                    9 => "Address2",
                                    10 => "City",
                                    11 => "State",
                                    12 => "Zipcode",
                                    13 => "Keyword",
                                    14 => "Employee ID",
                                    15 => "Company_Name",
                                    16 => "Company_Phone",
                                    17 => "Company_Website",
                                    18 => "Num_Employees",
                                    19 => "Sales_Volume",
                                    20 => "Year_Founded",
                                    21 => "Job_Title",
                                    22 => "Level",
                                    23 => "Job_Function",
                                    24 => "Headquarters_Branch",
                                    25 => "NAICS_CODE",
                                    26 => "LastSeenDate",
                                    27 => "LinkedIn"
                                ];
                            
                                $different = array_diff($newHeader, $oldHeader);
                                $indexOfDifferent = array_keys($different);

                                // info('', [
                                //     'indexOfDifferent' => $indexOfDifferent,
                                //     'newHeader' => $newHeader,
                                //     'oldHeader' => $oldHeader,
                                //     'different' => $different
                                // ]);

                                // jika indexOfDifferent lebih dari 0, maka ada perbedaan field dan harus di synchronized
                                if(count($indexOfDifferent) > 0) 
                                {
                                    // info('jika indexOfDifferent > 0, maka ada perbedaan field dan harus di synchronized');
                                    foreach($indexOfDifferent as $item)
                                    {
                                        // info('jalankan function insertColumnInSheet');
                                        $clientGoogle->insertColumnInSheet($spreadSheetID, $sheetID, $item);
                                    }
                                    
                                    // info('jalankan function updateHeader');
                                    $clientGoogle->updateHeader($spreadSheetID, $newHeader);

                                }
                                
                            }
                        }
                        //// END cek setingan di global seting.. 

            } 
        /** UPDATE ON SPREADSHEET */
        }catch(Exception $e) {
            Log::warning("Google Spreadsheet Update Client - UpdateClientLocator Failed (L1949) ErrMsg:" . $e->getMessage() . " CampaignID:" . $updateclient->leadspeek_api_id . ' Campaign Name:' . (isset($request->campaignName))?$request->campaignName:'');
        }
        // /** UPDATE ON SIMPLIFI, ONLY LOCATOR */

        // if (trim($sifiorganizationID) != "" && trim($sificampaignID) != "") {

        //     /** CHECK IF CAMPAIGN IS RUNNING NEED TO PAUSE FIRST */
        //     if ($updateclient->active == 'T' && $updateclient->disabled == 'F' && $updateclient->active_user == 'T') {
        //         $camp = $this->startPause_campaign($sifiorganizationID,$sificampaignID,'pause');
        //     }
        //     /** CHECK IF CAMPAIGN IS RUNNING NEED TO PAUSE FIRST */

        //     $_state = explode(",",trim($locatorstatesifi));
        //     $_cities = explode(",",trim($locatorcitysifi));
            
        //     $_geotarget = array_merge($_state,$_cities);
        //     $_postalcode = array(); 
        //     $tmp_postcode = explode(',',$locatorzip);
        //     foreach($tmp_postcode as $postcode) {
        //         if($postcode != '') {
        //             array_push($_postalcode,array("postal_code"=>$postcode, "country_code"=>"USA"));
        //         }
        //     }

        //     if ($nationaltargeting == 'T') {
        //         $_postalcode = array(); 
        //         $_geotarget = array('8180');
        //     }
        //     $this->updateGeoTargetsPostalCode($sifiorganizationID,$sificampaignID,$_geotarget,$_postalcode);

        //     /** KEYWORDS */
        //     $_keywords = array(); 
        //     $tmp_keywords = $request->locatorkeyword;
        //     foreach($tmp_keywords as $keyword) {
        //         if(trim($keyword) != "") {
        //             array_push($_keywords,array("name"=>trim($keyword), "max_bid"=>""));
        //         }
        //     }

        //     /** FOR CONTEXTUAL */
        //     if (is_array($request->locatorkeywordcontextual)) {
        //         $tmp_keywords = $request->locatorkeywordcontextual;
        //         foreach($tmp_keywords as $keyword) {
        //             if(trim($keyword) != "") {
        //                 array_push($_keywords,array("name"=>"!" . trim($keyword), "max_bid"=>""));
        //             }
        //         }
        //     }
        //     /** FOR CONTEXTUAL */

        //     $this->createKeywords($sificampaignID,$_keywords,$sifiorganizationID);
        //     /** KEYWORDS */

        //     /** UPDATE BUDGET PLAN */
        //     $start_date = $startdatecampaign;
        //     $end_date = $enddatecampaign;
            
        //     //$tmp = strtotime($end_date) - strtotime($start_date);
        //     //$diffdays = abs(round($tmp / 86400));
        //     //$totalbudget = number_format(1 * $diffdays,1,".","");
           
        //     $this->updateDefaultBudgetPlan($sifiorganizationID,$sificampaignID,$oristartdatecampaign,$orienddatecampaign);
        //     /** UPDATE BUDGET PLAN */

        //     /** UPDATE RECENCY */
        //     //$this->updateRecencyCampaign($sifiorganizationID,$sificampaignID,"2");
        //     /** UPDATE RECENCY */

        //     /** UPDATE DEVICE TYPE */
        //     //$this->createDeviceType($sificampaignID,36);
        //     /** UPDATE DEVICE TYPE */

        //     /** CHECK IF CAMPAIGN IS RUNNING AFTER PAUSE NEED TO START AGAIN */
        //     if ($updateclient->active == 'T' && $updateclient->disabled == 'F' && $updateclient->active_user == 'T') {
        //         $camp = $this->startPause_campaign($sifiorganizationID,$sificampaignID,'activate');
        //     }
        //     /** CHECK IF CAMPAIGN IS RUNNING AFTER PAUSE NEED TO START AGAIN */
        // }
        // /** UPDATE ON SIMPLIFI, ONLY LOCATOR */

        $updateclient = LeadspeekUser::find($request->leadspeekID);
        $updateclient->leadspeek_locator_zip = $locatorzip;
        $updateclient->leadspeek_locator_desc = $locatordesc;
        $updateclient->leadspeek_locator_keyword = $locatorkeyword;
        $updateclient->leadspeek_locator_keyword_contextual = $locatorkeywordcontextual;
        $updateclient->leadspeek_locator_state = $locatorstate;
        $updateclient->leadspeek_locator_state_simplifi = $locatorstatesifi;
        $updateclient->leadspeek_locator_city = $locatorcity;
        $updateclient->leadspeek_locator_city_simplifi = $locatorcitysifi;
        $updateclient->leadspeek_locator_state = $locatorstate;
        $updateclient->leadspeek_locator_require = $locatorrequire;
        // if (trim($locatorrequire) != trim($updateclient->leadspeek_locator_require)) {
        //     $updateclient->cost_perlead = $cost_perlead;
        // }
        $updateclient->hide_phone = $hidePhone;
        $updateclient->campaign_startdate = $startdatecampaign;
        $updateclient->campaign_enddate = $enddatecampaign;
        $updateclient->ori_campaign_startdate = $oristartdatecampaign;
        $updateclient->ori_campaign_enddate = $orienddatecampaign;
        $updateclient->lp_enddate = $enddatecampaign;
        $updateclient->timezone = $timezone;
        $updateclient->national_targeting = $nationaltargeting;
        $updateclient->location_target = $locationtarget;
        $updateclient->phoneenabled = $phoneenabled;
        $updateclient->homeaddressenabled = $homeaddressenabled;
        $updateclient->require_email = $requireemailaddress;
        $updateclient->reidentification_type = $reidentificationtype;
        $updateclient->advance_information = $advance_information;
        $updateclient->campaign_information_type = $campaign_information_type;
        
        $updateclient->save();

        /** NOTIFY ADMIN */
            $adminnotify = explode(',',$updateclient->admin_notify_to);
            /** FIND ADMIN EMAIL */
            $tmp = User::select('email')->whereIn('id', $adminnotify)->get();
            $adminEmail = array();
            foreach($tmp as $ad) {
                array_push($adminEmail,$ad['email']);
            }
            //array_push($adminEmail,'harrison@uncommonreach.com');
            //array_push($adminEmail,'carrie@uncommonreach.com');
            //array_push($adminEmail,'daniel@exactmatchmarketing.com');
            /** FIND ADMIN EMAIL */

            /** FIND USER COMPANY */
            $userlist = User::select('companies.company_name','users.company_parent','users.company_root_id')
                            ->join('companies','users.company_id','=','companies.id')
                            ->where('users.id','=',$updateclient->user_id)
                            ->where('users.active','=','T')
                            ->get();
            /** FIND USER COMPANY */

            /** FIND PARENT OR AGENCY NAME */
            $agency = Company::select('company_name')->where('id','=',$userlist[0]['company_parent'])->get();
            $agencyName = "";
            if (count($agency) > 0) {
                $agencyName = ' (' . $agency[0]['company_name'] . ')';
            }
            /** FIND PARENT OR AGENCY NAME */

            /** GET PRODUCT NAME */
            $productlocalname = 'Site ID';
            $productlocatorname = 'Search ID';

            $rootcompanysetting = CompanySetting::where('company_id',trim($userlist[0]['company_root_id']))->whereEncrypted('setting_name','rootcustomsidebarleadmenu')->get();
            if (count($rootcompanysetting) > 0) {
                $productname = json_decode($rootcompanysetting[0]['setting_value']);
                $productlocalname = $productname->local->name;
                $productlocatorname = $productname->locator->name;
            }

            if($this->checkwhitelabellingpackage(trim($companyID))) {
                $companysetting = CompanySetting::where('company_id',trim($companyID))->whereEncrypted('setting_name','customsidebarleadmenu')->get();
                if (count($companysetting) > 0) {
                    $productname = json_decode($companysetting[0]['setting_value']);
                    $productlocalname = $productname->local->name;
                    $productlocatorname = $productname->locator->name;
                }
            }
            /** GET PRODUCT NAME */


            $details = [
                'name'  => $userlist[0]['company_name'],
                'locatorzip' => trim($locatorzip),
                'locatordesc' => trim($locatordesc),
                'locatorkeyword' => trim($locatorkeyword),
                'locatorkeywordcontextual' => trim($locatorkeywordcontextual),
                'locatorstate' => trim($locatorstate),
                'locatorcities' => trim($locatorcity),
                'requiredfields' => trim($locatorrequire),
                'productlocatorname' => $productlocatorname,
            ];

            $from = [
                'address' => 'noreply@sitesettingsapi.com',
                'name' => 'Update Notification',
                'replyto' => 'support@sitesettingsapi.com',
            ];

            /** LOG ACTION */
            $descLogs = '';
            $fromTargetLocation = '';

            // Determine initial target location
            if ($prevClient->national_targeting === 'T') {
                $fromTargetLocation = 'National Targeting';
            } elseif ($prevClient->leadspeek_locator_state) {
                $fromTargetLocation = 'States';
            } elseif ($prevClient->leadspeek_locator_zip) {
                $fromTargetLocation = 'Zip Code';
            }

            // Update target location
            if ($updateclient->national_targeting === 'T') {
                $descLogs .= "Campaign Id: {$updateclient->leadspeek_api_id} | Target Location: Update values from {$fromTargetLocation} to National Targeting | ";
            } elseif ($updateclient->leadspeek_locator_state) {
                $descLogs .= "Campaign Id: {$updateclient->leadspeek_api_id} | Target Location: Update values from {$fromTargetLocation} to States | ";
                if ($prevClient->leadspeek_locator_state !== $updateclient->leadspeek_locator_state) {
                    $descLogs .= "States: Update values from " . ($prevClient->leadspeek_locator_state ?: 'empty') . " to {$updateclient->leadspeek_locator_state} | ";
                }
                if ($prevClient->leadspeek_locator_city !== $updateclient->leadspeek_locator_city) {
                    $descLogs .= "Cities: Update values from " . ($prevClient->leadspeek_locator_city ?: 'empty') . " to {$updateclient->leadspeek_locator_city} | ";
                }
            } elseif ($updateclient->leadspeek_locator_zip) {
                $descLogs .= "Campaign Id: {$updateclient->leadspeek_api_id} | Target Location: Update values from {$fromTargetLocation} to Zip Code | ";
                if ($prevClient->leadspeek_locator_zip !== $updateclient->leadspeek_locator_zip) {
                    $descLogs .= "Zip Code: Update values from " . ($prevClient->leadspeek_locator_zip ?: 'empty') . " to {$updateclient->leadspeek_locator_zip} | ";
                }
            }

            // Additional fields logging
            $fields = [
                'phoneenabled' => 'Phone Enabled',
                'homeaddressenabled' => 'Home Address Enabled',
                'require_email' => 'Require Email Address',
                'reidentification_type' => 'Re-identification Type',
                'applyreidentificationall' => 'Apply Reidentification',
                'leadspeek_locator_keyword' => 'Keyword'
            ];

            foreach ($fields as $field => $label) {
                if ($prevClient->$field !== $updateclient->$field) {
                    $descLogs .= "{$label}: Update values from " . ($prevClient->$field ?: 'empty') . " to {$updateclient->$field} | ";
                }
            }

            if($descLogs == ''){
                $descLogs = "Campaign Id: {$updateclient->leadspeek_api_id} | No updates were made as all values remain unchanged.";
            }

            if($inOpenApi === true) {
                $descLogs .= "in open api";
            }

            // Clean up and finalize log
            $descLogs = rtrim($descLogs, ' |');
            $ipAddress = ($inOpenApi === true) ? "inopenapi" : ($request->header('Clientip') ?? $request->ip());

            $user_id = null;
            if($inOpenApi === true) {
                if(isset($request->companyID) && $request->companyID != '') {
                    $user_id = User::where('company_id', $request->companyID)->where('user_type', 'userdownline')->where('active', 'T')->value('id');
                }
            } else {
                $user_id = auth()->user()->id;
            }

            $target_userid = $updateclient->user_id;
            $this->logUserAction($user_id, "Edit Campaign B2B", $descLogs, $ipAddress, $target_userid);
            // $this->logUserAction($updateclient->user_id, "Edit Campaign B2B", $descLogs, $ipAddress);
            /** LOG ACTION */


            //$this->send_email(array('carrie@uncommonreach.com'),$from,$userlist[0]['company_name'] . '-' . $updateclient->campaign_name . ' #' . $leadspeek_apiID . $agencyName . ' updated their ' . $productlocatorname . ' information',$details,array(),'emails.locatorinfoupdate',$companyID);

        /** NOTIFY ADMIN */

        return response()->json(array('result'=>'success', 'company'=>$userlist[0]['company_name'], 'detais'=>$details));
    }

    public function updateclient(Request $request) {
        $leadspeekType = (isset($request->leadspeekType))?$request->leadspeekType:'';
        $companyGroupID = (isset($request->companyGroupID))?$request->companyGroupID:'0';
        $clientHidePhone = (isset($request->clientHidePhone))?$request->clientHidePhone:'';
        $gtminstalled = (isset($request->gtminstalled))?$request->gtminstalled:'F';

        if ($companyGroupID == '') {
            $companyGroupID = '0';
        }
        
        $campaignName = (isset($request->campaignName))?$request->campaignName:'';
        $urlCode = (isset($request->urlCode))?$request->urlCode:'';
        $urlCodeThankyou = (isset($request->urlCodeThankyou))?$request->urlCodeThankyou:'';

        $applyreidentificationall = (isset($request->applyreidentificationall) && $request->applyreidentificationall)?'T':'F';

        $updateclient = LeadspeekUser::find($request->leadspeekID);
        $prevClient = clone $updateclient;

        $_admin_report_sent_to = array();
        $_report_sent_to = array();

        $_admin_report_sent_to = explode(",",$updateclient->admin_notify_to);
        $_report_sent_to = explode("\n",$updateclient->report_sent_to);
        if (count($_report_sent_to) == 0) {
            $_report_sent_to = explode(PHP_EOL,$updateclient->report_sent_to);
        }

        /** IF UPDATE JUST PER FIELD */
        if(isset($request->fieldupdate) && $request->fieldupdate != '') {
            if($request->fieldupdate == 'reporttype') {
                $updateclient->report_type =  $request->valuefield;
            }else if($request->fieldupdate == 'reportfrequency') {
                $tmp = explode('_',$request->valuefield);
                $updateclient->report_frequency_id = $request->valuefield;
                $updateclient->report_frequency = $tmp[0];
                $updateclient->report_frequency_unit = $tmp[1];
            }else if($request->fieldupdate == 'reportsentto') {
                $updateclient->report_sent_to = $request->valuefield;
            }
        }else{
        /** IF UPDATE JUST PER FIELD */
            $clientOrganizationID = (isset($request->clientOrganizationID))?$request->clientOrganizationID:'';
            $clientCampaignID = (isset($request->clientCampaignID))?$request->clientCampaignID:'';

            $_adminNotifyTo = "";
            foreach($request->adminNotifyTo as $item) {
                $_adminNotifyTo .= $item . ',';
            }

            $_adminNotifyTo = rtrim($_adminNotifyTo,',');

            //$updateclient = LeadspeekUser::find($request->leadspeekID);
            $updateclient->admin_notify_to = $_adminNotifyTo;
            $updateclient->leads_amount_notification = $request->leadsAmountNotification;
            $updateclient->report_sent_to = $request->reportSentTo;
            $updateclient->report_type = $request->reportType;
            $updateclient->leadspeek_organizationid = $clientOrganizationID;
            $updateclient->leadspeek_campaignsid = $clientCampaignID;
            $updateclient->hide_phone = $clientHidePhone;
            $updateclient->campaign_name = $campaignName;
            $updateclient->url_code = strtolower($urlCode);
            $updateclient->url_code_thankyou = strtolower($urlCodeThankyou);
            $updateclient->gtminstalled = $gtminstalled;
            $updateclient->applyreidentificationall =  $applyreidentificationall;

            if ($leadspeekType != '') {
                $updateclient->leadspeek_type = $leadspeekType;
            }

            if ($companyGroupID != '') {
                $updateclient->group_company_id = $companyGroupID;
            }
            //$updateclient->save();

            /** GOOGLE SPREAD SHEET HIDE SHOW COLUMN */

            $check_connect_google = $this->googlespreadsheet_checkconnect($request, $updateclient->company_id);
            $data = $check_connect_google->getData(true); // true agar jadi array, bukan stdClass
            $isConnected_google = $data['googleSpreadsheetConnected'] ?? false;
            
            if ($isConnected_google && $updateclient->report_type == 'GoogleSheet') {
            //if (false) {
            try {    
                /** CHECK IF PHONE HIDE OR NOT */
                $hidePhone = ($clientHidePhone == 'T')?true:false;
                $spreadSheetID = $updateclient->spreadsheet_id;
                $companyID = $updateclient->company_id;

                $clientGoogle = new GoogleSheet($companyID,$this->_moduleID,$this->_settingTokenName,'',true,$updateclient->leadspeek_api_id ?? "",$updateclient->leadspeek_type ?? "", __FUNCTION__);
                $clientGoogle->setSpreadSheetID($spreadSheetID);
                //$sheetID = $clientGoogle->getSheetID(date('Y'));
                $sheetID = $clientGoogle->getSheetID('2021');
                if ($sheetID === '') {
                    //$sheetID = $clientGoogle->createSheet(date('Y'));
                    //$clientGoogle->setSheetName(date('Y'));
                    /** INTIAL GOOGLE SPREADSHEET HEADER */
                    //$contentHeader[] = array('ID','Email','IP','Source','OptInDate','ClickDate','Referer','Phone','First Name','Last Name','Address1','Address2','City','State','Zipcode');
                    /** INTIAL GOOGLE SPREADSHEET HEADER */
                    //$savedData = $clientGoogle->saveDataHeaderToSheet($contentHeader);
                    $sheetdefault = explode('|',$clientGoogle->getDefaultSheetIDName());
                    $sheetID = $sheetdefault[0];
                    $clientGoogle->setSheetName($sheetdefault[1]);
                }else{
                    //$clientGoogle->setSheetName(date('Y'));
                    $clientGoogle->setSheetName('2021');
                }

                //$clientGoogle->showhideColumn($sheetID,7,8,$hidePhone);
                /** CHECK IF PHONE HIDE OR NOT */

                /** FOR PERMISSION SET */
                /** GET ORIGINAL VALUE FOR REPORT SENT TO */

                $_admin_update_report_sent_to = $request->adminNotifyTo;
               
                $_update_report_sent_to =  explode("\n",$request->reportSentTo);
                $removedUser = array();
                $addUser = array();

                $AdminremovedUser = array();
                $AdminaddUser = array();
                
                $fileUserPermission = array();
                $finalAddUser = array();
                $AdminfinalAddUser = array();

                if ($spreadSheetID != "") {
                    // Find removed user
                    $removedUser = array_diff($_report_sent_to, $_update_report_sent_to);
                    // Find added user
                    $addUser = array_diff($_update_report_sent_to, $_report_sent_to);

                    // Find Admin removed user
                    $AdminremovedUser = array_diff($_admin_report_sent_to, $_admin_update_report_sent_to);
                     /** FIND ADMIN EMAIL */
                    $tmp = User::select('email')->whereIn('id', $AdminremovedUser)->get();
                    $AdminremovedUser = array();
                    foreach($tmp as $ad) {
                        array_push($AdminremovedUser,$ad['email']);
                    }
                    // Find Admin added user
                    $AdminaddUser = array_diff($_admin_update_report_sent_to, $_admin_report_sent_to);
                    $tmp = User::select('email')->whereIn('id', $AdminaddUser)->get();
                    $AdminaddUser = array();
                    foreach($tmp as $ad) {
                        array_push($AdminaddUser,$ad['email']);
                    }

                    $tmp = User::select('email')->whereIn('id', $_admin_update_report_sent_to)->get();
                    $_admin_update_report_sent_to = array();
                    foreach($tmp as $ad) {
                        array_push($_admin_update_report_sent_to,$ad['email']);
                    }


                    $getPermissionList = json_encode($clientGoogle->getPermissionList($spreadSheetID));
                    $permissionList = json_decode($getPermissionList,true);

                    foreach($permissionList['permissions'] as $permission) {
                        $filePermissionEmail = strtolower($permission['emailAddress']);
                        $filePermissionID = trim($permission['id']);
                        $fileUserPermission[] = $filePermissionEmail ;
                        /** FOR REMOVED USER */
                        if (count($removedUser) > 0 && in_array($filePermissionEmail,$removedUser,true)) {
                            $removepermission = $clientGoogle->deletePermission($spreadSheetID,$filePermissionID);
                        }
                        /** FOR REMOVED USER */

                        /** FOR REMOVED USER */
                        if (count($AdminremovedUser) > 0 && in_array($filePermissionEmail,$AdminremovedUser,true)) {
                            $removepermission = $clientGoogle->deletePermission($spreadSheetID,$filePermissionID);
                        }
                        /** FOR REMOVED USER */
                    }

                    $finalAddUser = array_diff($_update_report_sent_to,$fileUserPermission);
                    foreach($finalAddUser as $usrEmail) {
                        $createpermission = $clientGoogle->createPermission($spreadSheetID,$usrEmail,'user','reader',false);
                    }

                    $AdminfinalAddUser = array_diff($_admin_update_report_sent_to,$fileUserPermission);
                    foreach($AdminfinalAddUser as $usrEmail) {
                        $createpermission = $clientGoogle->createPermission($spreadSheetID,$usrEmail,'user','writer',false);
                    }

                }
                // return response()->json(array("result"=>'success','addUser'=>$addUser,'removedUser'=>$removedUser,"finalAddUser"=>$finalAddUser,"fileUserPermission"=>$fileUserPermission,"permissionList"=>$permissionList['permissions'],"report_sent_to"=>$_report_sent_to,"update_report_sent_to"=>$_update_report_sent_to));
                // exit;die();
                /** FIND ADMIN EMAIL */
                //     $tmp = User::select('email')->whereIn('id', $request->adminNotifyTo)->get();
                //     foreach($tmp as $ad) {
                //         /** MAKE SURE THEY ADDED ON SHARED PERMISSION */
                //         $permission = $clientGoogle->createPermission($spreadSheetID,$ad['email'],'user','writer',false);
                //         /** MAKE SURE THEY ADDED ON SHARED PERMISSION */
                //     }

                //     /** SENT ALSO TO EMAIL CLIENT */
                //     $tmp = explode(PHP_EOL, $request->reportSentTo);
                //     foreach($tmp as $ad) {
                //         /** MAKE SURE THEY ADDED ON SHARED PERMISSION */
                //         $permission = $clientGoogle->createPermission($spreadSheetID,$ad,'user','reader',false);
                //         /** MAKE SURE THEY ADDED ON SHARED PERMISSION */
                //     }
                //     /** SENT ALSO TO EMAIL CLIENT */
                    
                // /** FIND ADMIN EMAIL */

                /** FOR PERMISSION SET */
            }catch(\Throwable $e) {
                Log::warning("Google Spreadsheet Update Client Failed (L2336) ErrMsg:" . $e->getMessage() . " CampaignID:" . $updateclient->leadspeek_api_id . ' Campaign Name:' . $campaignName);
            }

            }
            /** GOOGLE SPREAD SHEET HIDE SHOW COLUMN */

        }

        $updateclient->save();

        /** UPDATE LOCATOR IF EXIST */
        if (isset($request->locatorkeyword) && $leadspeekType == 'locator') {
            $locatorupdate = $this->updateclientlocator($request, $prevClient);
        } else if(isset($request->locatorkeyword) && $leadspeekType == 'enhance') {
            $enhanceUpdate = $this->updateclientenhance($request, $prevClient);
        } else if(isset($request->locatorkeyword) && $leadspeekType == 'b2b') {
            $enhanceUpdate = $this->updateclientb2b($request, $prevClient);
        } else if ($leadspeekType == 'local') {
            $localupdate = $this->updateclientlocal($request, $prevClient);
        }
        /** UPDATE LOCATOR IF EXIST */
        return $this->getclient($request,$request->companyID,$request->leadspeekID);

    }

    public function setupcomplete(Request $request) {
        $usrID = (isset($request->usrID))?$request->usrID:'';
        $statusComplete = (isset($request->statuscomplete))?$request->statuscomplete:'';
        $answers = (isset($request->answers))?$request->answers:'';
        $companyGroupID = (isset($request->companyGroupID))?$request->companyGroupID:'0';
        $leadspeekType = 'local';
        $questinnaireCode = date('YmdHi');
        $campaign_keywords = "";
        $timezone = (isset($request->timezone))?$request->timezone:'America/Chicago';
        $clientOwnerEmail = "";

        $userlist = User::select('companies.company_name','companies.simplifi_organizationid','users.company_id','users.company_parent','users.name','users.email','users.phonenum','users.user_type','users.company_root_id')
                            ->join('companies','users.company_id','=','companies.id')
                            ->where('users.id','=',$usrID)
                            ->where('users.active','=','T')
                            ->get();
        
        /** IF JUST FINISH USER SETUP PROFILE */
        if ($usrID != "" && $statusComplete == "T" && $answers == "") {
            /** SAVE THE QUESTIONNAIRE ANSWER AND UPDATE SETUP COMPLETED */
            $usr = User::find($usrID);
            $usr->profile_setup_completed = $statusComplete;
            $usr->save();
            return response()->json(array('result'=>'success','params'=>array()));
            exit;die();
        /** SAVE THE QUESTIONNAIRE ANSWER AND UPDATE SETUP COMPLETED */
        }
        /** IF JUST FINISH USER SETUP PROFILE */

        $defaultParentOrganization = config('services.sifidefaultorganization.organizationid');
        $clientOwnerEmail = $userlist[0]['email'];

        $companyUserID =  ($userlist[0]['company_id'] == null || $userlist[0]['company_id'] == '')?'':$userlist[0]['company_id'];
        $companyID =  ($userlist[0]['company_parent'] == null || $userlist[0]['company_parent'] == '')?'':$userlist[0]['company_parent'];
        $companyName = $userlist[0]['company_name'];
        $companyOrganizationID = ($userlist[0]['simplifi_organizationid'] == null || $userlist[0]['simplifi_organizationid'] == '')?'':trim($userlist[0]['simplifi_organizationid']);
        $newCampaignID = '';
        $companyParentName = '';

        if ($companyID != '') {
            $companyParent = Company::select('simplifi_organizationid','company_name')
                                ->where('id','=',$companyID)
                                ->get();
            if(count($companyParent) > 0) {
                if ($companyParent[0]['simplifi_organizationid'] != '') {
                    $defaultParentOrganization = $companyParent[0]['simplifi_organizationid'];
                }
                if ($companyParent[0]['company_name'] != '') {
                    $companyParentName = trim($companyParent[0]['company_name']) . '-';
                }
            }
        }

        $reportType = trim($answers['asec6_1']);
        $tmp = explode('_',trim($answers['asec6_2']));
        $report_frequency_id = trim($answers['asec6_2']);
        $report_frequency = $tmp[0];
        $report_frequency_unit = $tmp[1];
        $reportSentTo = trim($answers['asec6_3']);
        $leadsAmountNotification = 500;
        $clientHidePhone = 'T';
        $_adminNotifyTo = '';
        $_filelocatorurl = $answers['filenames'];
        $newclientID = '';
        $nationalTargeting = 'F';

        $phoneenabled = (isset($request->phoneenabled) && $request->phoneenabled)?'T':'F';
        $homeaddressenabled = (isset($request->homeaddressenabled) && $request->homeaddressenabled)?'T':'F';
        $requireemailaddress = (isset($request->requireemailaddress) && $request->requireemailaddress)?'T':'F';

        $phoneenabledsiteid = (isset($request->phoneenabledsiteid) && $request->phoneenabledsiteid)?'T':'F';
        $homeaddressenabledsiteid = (isset($request->homeaddressenabledsiteid) && $request->homeaddressenabledsiteid)?'T':'F';
        $requireemailaddresssiteid = (isset($request->requireemailaddresssiteid) && $request->requireemailaddresssiteid)?'T':'F';
        
        $reidentificationtype = (isset($request->reidentificationtype))?$request->reidentificationtype:'never';
        $locationtarget = (isset($request->locationtarget))?$request->locationtarget:'Focus';
        
        $reportSentTo = str_replace(',',PHP_EOL,$reportSentTo);

        $leadspeek_locator_require = (trim($answers['asec6_5']) == 'none')?'':trim($answers['asec6_5']);
        
        $defaultPriceCompanyID = '';
        if ($userlist[0]['user_type'] == 'userdownline' || $userlist[0]['user_type'] == 'user') {
            $defaultPriceCompanyID = $userlist[0]['company_id'];
        }else if ($userlist[0]['user_type'] == 'client') {
            $defaultPriceCompanyID = $userlist[0]['company_parent'];
        }
        
        $clientdefaultprice = false;
        $masterCostAgency = CompanySetting::where('company_id',$companyUserID)->whereEncrypted('setting_name','clientdefaultprice')->get();
        if (count($masterCostAgency) > 0) {
            $masterCostAgency = json_decode($masterCostAgency[0]['setting_value']);
            $clientdefaultprice = false;
        }else{
             /** CHECK IF CLIENT HAVE THEIR OWN DEFAULT PRICE SETUP */
             $masterCostClient = CompanySetting::where('company_id',$defaultPriceCompanyID)->whereEncrypted('setting_name','agencydefaultprice')->get();
             if (count($masterCostClient) > 0) {
                 $masterCostAgency = json_decode($masterCostClient[0]['setting_value']);    
             }else{
                 $masterCostAgency = '';
             }
             /** CHECK IF CLIENT HAVE THEIR OWN DEFAULT PRICE SETUP */
        }

        /** CHECK DEFAULT PAYMENT TERM */
        $paymenttermDefault = "Weekly";
        $getRootSetting = $this->getcompanysetting($userlist[0]['company_root_id'],'rootsetting');
        if ($getRootSetting != '') {
            if (isset($getRootSetting->defaultpaymentterm) && $getRootSetting->defaultpaymentterm != '') {
                $paymenttermDefault = trim($getRootSetting->defaultpaymentterm);
            }
        }
        $defpaymentterm = Company::select('paymentterm_default')->where('id','=',$companyUserID)->get();
        if (count($defpaymentterm) > 0 && $clientdefaultprice === true) {
            $paymenttermDefault = $defpaymentterm[0]['paymentterm_default'];
        }else{
            $defpaymentterm = Company::select('paymentterm_default')->where('id','=',$defaultPriceCompanyID)->get();
            if (count($defpaymentterm) > 0) {
                $paymenttermDefault = $defpaymentterm[0]['paymentterm_default'];
            }
        }
        /** CHECK DEFAULT PAYMENT TERM */

        $cdnurl = 'https://emmspaces.nyc3.cdn.digitaloceanspaces.com/users/questionnaire/';
        if (config('services.appconf.devmode') === true) {
            $cdnurl = 'https://emmbetaspaces.nyc3.cdn.digitaloceanspaces.com/users/questionnaire/';
        }

        if (isset($answers['asec5_4']) && count($answers['asec5_4']) > 0 && $answers['asec5_4_0_1'] === true) {
            $_tmp = $answers['asec5_4'];
            $answers['asec5_4'] = '';
            $answers['asec5_4_simplifi'] = '';
            foreach($_tmp as $tmp) {
                $ex = explode('|',$tmp);
                $answers['asec5_4'] .= $ex[1] . ',';
                $answers['asec5_4_simplifi'] .= $ex[0] . ',';
            }
            $answers['asec5_4'] = rtrim($answers['asec5_4'],",");
            $answers['asec5_4_simplifi'] = rtrim($answers['asec5_4_simplifi'],",");
        }else{
            $answers['asec5_4'] = "";
            $answers['asec5_4_simplifi'] = "";
        }

        if (isset($answers['asec5_4_1']) && count($answers['asec5_4_1']) > 0) {
            $answers['asec5_4_1'] = implode(",",$answers['asec5_4_1']);
        }else{
            $answers['asec5_4_1'] = "";
        }

        if (isset($answers['asec5_4_2']) && count($answers['asec5_4_2']) > 0 && $answers['asec5_4_0_2'] === true) {
            $_tmp = $answers['asec5_4_2'];
            $answers['asec5_4_2'] = '';
            $answers['asec5_4_2_simplifi'] = '';
            foreach($_tmp as $tmp) {
                $ex = explode('|',$tmp);
                $answers['asec5_4_2'] .= $ex[1] . ',';
                $answers['asec5_4_2_simplifi'] .= $ex[0] . ',';
            }
            $answers['asec5_4_2'] = rtrim($answers['asec5_4_2'],",");
            $answers['asec5_4_2_simplifi'] = rtrim($answers['asec5_4_2_simplifi'],",");
        }else{
            $answers['asec5_4_2'] = "";
            $answers['asec5_4_2_simplifi'] = "";
        }
        
        if (isset($answers['asec5_10']) && count($answers['asec5_10']) > 0) {
            $campaign_keywords = $answers['asec5_10'];
            $answers['asec5_10'] = implode(",",$answers['asec5_10']);
        }else{
            $answers['asec5_10'] = "";
        }

        if (isset($answers['asec5_3']) && trim($answers['asec5_3']) != '' && $answers['asec5_4_0_3'] === true) {
            $answers['asec5_3'] = explode(PHP_EOL,$answers['asec5_3']);
            $answers['asec5_3'] = implode(",",$answers['asec5_3']);
        }else{
            $answers['asec5_3'] = "";
        }

        /** IF SETUP FOR NATIONAL TARGETING */
        if ($answers['asec5_4_0_0'] === true && $answers['asec5_4_0_1'] === false && $answers['asec5_4_0_2'] === false && $answers['asec5_4_0_3'] === false) {
            $answers['asec5_4'] = "";
            $answers['asec5_4_simplifi'] = "";

            $answers['asec5_4_2'] = "";
            $answers['asec5_4_2_simplifi'] = "";
            $answers['asec5_4_1'] = "";
            $answers['asec5_3'] = "";
            $nationalTargeting = 'T';
        }
        /** IF SETUP FOR NATIONAL TARGETING */

        //$newCampaignID = "3064039";
        //$tryseraID = "7777";

        //$creativeGroupID = $this->createCreativeGroups($newCampaignID, trim($userlist[0]['company_name']) . ' Uncommon Reach ads #' . $tryseraID);
        //$creativeGroupID = "952470";
        //$companyOrganizationID = "384940";
        //$tmp = $this->createAds($companyName,$tryseraID,$companyOrganizationID,$newCampaignID,$creativeGroupID);
        //return response()->json(array('result'=>$tmp));
        //return response()->json(array('result'=>'success','params'=>$usrID,'newfilenameid'=>$answers['asec5_4_simplifi'],'state'=>trim($answers['asec5_4'])));
        //exit;die();

        /** FIND DEFAULT ADMIN NOTIFY */
        
        $admins =  User::select('id','email')
                        ->where('company_id','=',$companyID)
                        ->where('active','T')
                        ->where('isAdmin','=','T')
                        ->where('defaultadmin','=','T')
                        ->orderByEncrypted('name')
                        ->get();
        
        if (count($admins) > 0) {
            foreach($admins as $ad) {
                $_adminNotifyTo = $_adminNotifyTo . $ad['id'] . ',';
            }
        }else{
            $admins =  User::select('id','email')
                        ->where('company_id','=',$companyID)
                        ->where('active','T')
                        ->where('isAdmin','=','T')
                        ->where('user_type','=','userdownline')
                        ->orderByEncrypted('name')
                        ->get();
            foreach($admins as $ad) {
                $_adminNotifyTo = $_adminNotifyTo . $ad['id'] . ',';
            }
        }

        if (trim($_adminNotifyTo) != '') {
            $_adminNotifyTo = rtrim($_adminNotifyTo,',');
        }
        
        /** FIND DEFAULT ADMIN NOTIFY */

        /** ALGORITHM TO AUTOMATICALLY SETUP CLIENT BASE ON QUESTIONNAIRE */

        /** GET PRODUCT NAME */
        $productlocalname = 'Site ID';
        $productlocatorname = 'Search ID';

        $rootcompanysetting = CompanySetting::where('company_id',trim($userlist[0]['company_root_id']))->whereEncrypted('setting_name','rootcustomsidebarleadmenu')->get();
        if (count($rootcompanysetting) > 0) {
            $productname = json_decode($rootcompanysetting[0]['setting_value']);
            $productlocalname = $productname->local->name;
            $productlocatorname = $productname->locator->name;
        }

        if($this->checkwhitelabellingpackage(trim($companyID))) {
            $companysetting = CompanySetting::where('company_id',trim($companyID))->whereEncrypted('setting_name','customsidebarleadmenu')->get();
            if (count($companysetting) > 0) {
                $productname = json_decode($companysetting[0]['setting_value']);
                $productlocalname = $productname->local->name;
                $productlocatorname = $productname->locator->name;
            }
        }
        /** GET PRODUCT NAME */

        $campaignlocalname = (isset($answers['campaignlocalname']) && $answers['campaignlocalname'] != '')?$answers['campaignlocalname']:'Campaign Local';
        $campaignlocatorname = (isset($answers['campaignlocatorname']) && $answers['campaignlocatorname'] != '')?$answers['campaignlocatorname']:'Campaign Locator';

        $startdatecampaign = (isset($request->startdatecampaign))?$request->startdatecampaign:'';
        $enddatecampaign = (isset($request->enddatecampaign))?$request->enddatecampaign:'';

        /** CHECK IF CLIENT WANT LEADSPEEK LOCAL */
            if($answers['asec2_1'] == 'Yes') {
                $leadspeekType = 'local';
                $campaignName = ($answers['campaignlocalname'] != '')?$answers['campaignlocalname']:'Campaign Local';
                $urlCode = str_replace(array('http://','https://'),'',trim($answers['asec3_1']));
                $urlCode = "https://" . strtolower($urlCode);
                $urlCodeThankyou = str_replace(array('http://','https://'),'',trim($answers['asec3_6']));
                $urlCodeThankyou = "https://" . strtolower($urlCodeThankyou);
                //$phoneenabled = 'F';
                //$homeaddressenabled = 'T';
                //$requireemailaddress = 'T';
                
                $lp_limit_leads = (isset($answers['asec5_6_1']))?trim($answers['asec5_6_1']):'0';
                /** BY DEFAULT LOCAL WILL HAVE FIRSTNAME AND LASTNAME AS REQUIRED */
                //$leadspeek_locator_require = "FirstName,LastName";

                /** CREATE TRYSERA ID */
                $tryseraID = "";
                $tryseraCustomID = $this->_moduleID . '_' . $companyID . '00' . $usrID . '_' . date('His');
                /*if (config('services.appconf.devmode') === false) {
                    $tryseraID = $this->create_leadspeek_api_client($companyName . ' - ' . $campaignName,$tryseraCustomID);
                }else{
                    $tryseraID = rand(1000,9999);
                }*/
                $tryseraID = $this->generateLeadSpeekIDUniqueNumber();

                if (empty($tryseraID) || trim($tryseraID) == '') {
                    return response()->json(array('result'=>'failed','message'=>'Sorry, system can not get the leadspeek ID, would you please try again?','data'=>array()));
                    exit;die();
                }
                /** CREATE TRYSERA ID */
                
                $_cost_perlead = (isset($masterCostAgency->local->Weekly->LeadspeekCostperlead))?$masterCostAgency->local->Weekly->LeadspeekCostperlead:'0';
                $_platformfee = (isset($masterCostAgency->local->Weekly->LeadspeekPlatformFee))?$masterCostAgency->local->Weekly->LeadspeekPlatformFee:'0';
                $_lp_min_cost_month = (isset($masterCostAgency->local->Weekly->LeadspeekMinCostMonth))?$masterCostAgency->local->Weekly->LeadspeekMinCostMonth:'0';
                $lp_limit_leads = (isset($masterCostAgency->local->Weekly->LeadspeekLeadsPerday))?$masterCostAgency->local->Weekly->LeadspeekLeadsPerday:'10';
                
                if ($paymenttermDefault == "Monthly") {
                    $_cost_perlead = (isset($masterCostAgency->local->Monthly->LeadspeekCostperlead))?$masterCostAgency->local->Monthly->LeadspeekCostperlead:'0';
                    $_platformfee = (isset($masterCostAgency->local->Monthly->LeadspeekPlatformFee))?$masterCostAgency->local->Monthly->LeadspeekPlatformFee:'0';
                    $_lp_min_cost_month = (isset($masterCostAgency->local->Monthly->LeadspeekMinCostMonth))?$masterCostAgency->local->Monthly->LeadspeekMinCostMonth:'0';
                    $lp_limit_leads = (isset($masterCostAgency->local->Monthly->LeadspeekLeadsPerday))?$masterCostAgency->local->Monthly->LeadspeekLeadsPerday:'10';
                }else if ($paymenttermDefault == "One Time") {
                    $_cost_perlead = (isset($masterCostAgency->local->OneTime->LeadspeekCostperlead))?$masterCostAgency->local->OneTime->LeadspeekCostperlead:'0';
                    $_platformfee = (isset($masterCostAgency->local->OneTime->LeadspeekPlatformFee))?$masterCostAgency->local->OneTime->LeadspeekPlatformFee:'0';
                    $_lp_min_cost_month = (isset($masterCostAgency->local->OneTime->LeadspeekMinCostMonth))?$masterCostAgency->local->OneTime->LeadspeekMinCostMonth:'0';
                    $lp_limit_leads = (isset($masterCostAgency->local->OneTime->LeadspeekLeadsPerday))?$masterCostAgency->local->OneTime->LeadspeekLeadsPerday:'10';
                }

                $newclient = LeadspeekUser::create([
                    'module_id' => $this->_moduleID,
                    'company_id' => $companyID,
                    'user_id' => $usrID,
                    'report_type' => $reportType,
                    'report_sent_to' => $reportSentTo,
                    'admin_notify_to' => $_adminNotifyTo,
                    'leads_amount_notification' => $leadsAmountNotification,
                    'total_leads' => 0,
                    'start_billing_date' => date('Y-m-d H:i:s'),
                    'spreadsheet_id' => '',
                    'filename' => '',
                    'report_frequency_id' => $report_frequency_id,
                    'report_frequency' => $report_frequency,
                    'report_frequency_unit' => $report_frequency_unit,
                    'leadspeek_type' => $leadspeekType,
                    'group_company_id' => $companyGroupID,
                    'campaign_name' => $campaignName,
                    'url_code' => $urlCode,
                    'url_code_thankyou' => $urlCodeThankyou,
                    'leadspeek_organizationid' => '',
                    'leadspeek_campaignsid' => '',
                    'paymentterm' => $paymenttermDefault,
                    'leadspeek_locator_require' => 'FirstName,LastName,MailingAddress,Phone',
                    'hide_phone' => $clientHidePhone,
                    'last_lead_pause' => '0000-00-00 00:00:00',
                    'last_lead_start' => '0000-00-00 00:00:00',
                    'active' => 'F',
                    'disabled' => 'T',
                    'active_user' => 'F',
                    'leadspeek_api_id' => $tryseraID,
                    'embeddedcode_crawl' => 'F',
                    'embedded_status' => 'Waiting for the embedded code to be placed on: ' . $urlCode,
                    'questionnaire_answers' => json_encode($answers),
                    'cost_perlead' => $_cost_perlead,
                    'platformfee' => $_platformfee,
                    'lp_max_lead_month' => '0',
                    'lp_min_cost_month' => $_lp_min_cost_month,
                    'lp_limit_leads' => $lp_limit_leads,
                    'file_url' => '',
                    'phoneenabled' => $phoneenabledsiteid,
                    'homeaddressenabled' => $homeaddressenabledsiteid,
                    'reidentification_type' => $reidentificationtype,
                    'location_target' => 'Focus',
                    'require_email' => $requireemailaddresssiteid,
                    'trysera' => 'F',
                ]);
                
                $newclientID = $newclient->id;

                /** CHECK IF GOOGLE SPREADSHEET */
                if ($reportType == "GoogleSheet") {
                //if (false) {
                /** CHECK IF HAVE GOOGLE CONNECTION */
                $chkGconnection = $this->get_setting($companyID,$this->_moduleID,$this->_settingTokenName);
                if(count($chkGconnection) > 0 && $chkGconnection[0]->setting_value != '') {
                /** CHECK IF HAVE GOOGLE CONNECTION */
                            /** CREATE GOOGLE SHEET ONLINE */
                            $spreadsheetTitle = trim($companyName . ' - ' . $campaignName . ' #' . $tryseraID);
                            $clientGoogle = new GoogleSheet($companyID,$this->_moduleID,$this->_settingTokenName,'',true,$newclient->leadspeek_api_id ?? "",$newclient->leadspeek_type ?? "", __FUNCTION__);
                            $spreadSheetID = $clientGoogle->createSpreadSheet($spreadsheetTitle);
                            $clientGoogle->updateSheetTitle(date('Y'));

                            $clientGoogle->setSheetName(date('Y'));
                            
                            /** INTIAL GOOGLE SPREADSHEET HEADER */
                            //$contentHeader[] = array('ID','Email','IP','Source','OptInDate','ClickDate','Referer','Phone','First Name','Last Name','Address1','Address2','City','State','Zipcode');
                            $contentHeader[] = array('ID','ClickDate','First Name','Last Name','Email1','Email2','Phone1','Phone2','Address1','Address2','City','State','Zipcode','Landing URL');
                            
                            /** INTIAL GOOGLE SPREADSHEET HEADER */
                            $savedData = $clientGoogle->saveDataHeaderToSheet($contentHeader);

                            /** ADD DUMMY DATA ON FIRST ROW FOR ZAPIER TEST */
                            //$content[] = array('00000000','johndoe@example.com','','',date('m/d/Y h:i:s A'),date('m/d/Y h:i:s A'),'','','John','Doe','John Doe Street','','Columbus','OH','43055');
                            $content[] = array('00000000',date('m/d/Y h:i:s A'),'John','Doe','johndoe1-' . $tryseraID . '@example.com','johndoe2-' . $tryseraID . '@example.com','123-123-1234','567-567-5678',$tryseraID . ' John Doe Street','suite 101','Columbus','OH','43055','https://example.com');
                            $savedData = $clientGoogle->saveDataToSheet($content);
                            /** ADD DUMMY DATA ON FIRST ROW FOR ZAPIER TEST */

                            $newclient->spreadsheet_id = $spreadSheetID;
                            $newclient->save();

                            /** CHECK IF PHONE HIDE OR NOT */
                            $hidePhone = ($clientHidePhone == 'T')?true:false;
                            $sheetID = $clientGoogle->getSheetID(date('Y'));
                            /** HIDE EVERYTHING ELSE BY DEFAULT EXCEPT firstname, lastname, email, click date, and address */
                            //$clientGoogle->showhideColumn($sheetID,7,8,$hidePhone);
                            $clientGoogle->showhideColumn($sheetID,0,1,'T');
                            
                            /** NEW OPTIONS FOR ENABLE PHONE AND MAIL ADDRESS */
                            if($phoneenabled == 'T' && $homeaddressenabled == 'F') {
                                $clientGoogle->showhideColumn($sheetID,6,8,'F');
                                $clientGoogle->showhideColumn($sheetID,8,13,'T');
                            }else if($phoneenabled == 'F' && $homeaddressenabled == 'T') {
                                $clientGoogle->showhideColumn($sheetID,6,8,'T');
                                $clientGoogle->showhideColumn($sheetID,8,13,'F');
                            }else if($phoneenabled == 'F' && $homeaddressenabled == 'F') {
                                $clientGoogle->showhideColumn($sheetID,6,8,'T');
                                $clientGoogle->showhideColumn($sheetID,8,13,'T');
                            }else{
                                $clientGoogle->showhideColumn($sheetID,6,8,'F');
                                $clientGoogle->showhideColumn($sheetID,8,13,'F');
                            }
                            /** NEW OPTIONS FOR ENABLE PHONE AND MAIL ADDRESS */

                            /** HIDE EVERYTHING ELSE BY DEFAULT EXCEPT firstname, lastname, email, click date, and address */
                            /** CHECK IF PHONE HIDE OR NOT */

                            /** CREATE GOOGLE SHEET ONLINE */

                            /** SENT TO CLIENT AND ADMIN THE LINK FOR THE PERMISSION */

                            /** FIND ADMIN EMAIL */
                                //$tmp = User::select('email')->whereIn('id', $_adminNotifyTo)->get();
                                $adminEmail = array();
                                foreach($admins as $ad) {
                                    $permission = $clientGoogle->createPermission($spreadSheetID,$ad['email'],'user','writer',false);
                                    array_push($adminEmail,trim($ad['email']));
                                }
                                //array_push($adminEmail,'serverlogs@sitesettingsapi.com');
                                /** SENT ALSO TO EMAIL CLIENT */
                                $tmp = explode(PHP_EOL, $reportSentTo);
                                foreach($tmp as $ad) {
                                    $permission = $clientGoogle->createPermission($spreadSheetID,$ad,'user','reader',false);
                                    array_push($adminEmail,trim($ad));
                                }
                                /** SENT ALSO TO EMAIL CLIENT */
                                
                            /** FIND ADMIN EMAIL */

                            /** FIND USER COMPANY */
                            
                            //$AdminDefault = $this->get_default_admin($companyID);
                            //$AdminDefaultEmail = (isset($AdminDefault[0]['email']))?$AdminDefault[0]['email']:'';

                            /** FIND USER COMPANY */
                                // $emailfname = "";
                                // if ($userlist[0]['name'] != "") {
                                //     $_tmp = explode(" ",$userlist[0]['name']);
                                //     $emailfname = $_tmp[0];
                                // }

                                // $details = [
                                //     'defaultadmin' => $AdminDefaultEmail,
                                //     'companyName' => $userlist[0]['company_name'],
                                //     'name'  => $emailfname,
                                //     'campaignName' => $campaignName,
                                //     'TryseraID' =>  $tryseraID,
                                //     'links' => 'https://docs.google.com/spreadsheets/d/' . $spreadSheetID . '/edit?usp=sharing',
                                // ];

                                /** START NEW METHOD EMAIL */
                                $defaultFromName = $productlocatorname . ' Support';
                                $from = [
                                    'address' => 'noreply@exactmatchmarketing.com',
                                    'name' => $productlocatorname . ' Support',
                                    'replyto' => 'support@exactmatchmarketing.com',
                                ];
                                
                                if (strtolower($leadspeekType) == 'local') {
                                    $defaultFromName = $productlocalname . ' Support';
                                    
                                    $from = [
                                        'address' => 'noreply@exactmatchmarketing.com',
                                        'name' => $productlocalname . ' Support',
                                        'replyto' => 'support@exactmatchmarketing.com',
                                    ];
                                }

                                $smtpusername = $this->set_smtp_email($userlist[0]['company_parent']);
                                $emailtype = 'em_campaigncreated';

                                $customsetting = $this->getcompanysetting($userlist[0]['company_parent'],$emailtype);
                                $chkcustomsetting = $customsetting;

                                if ($customsetting == '') {
                                    $customsetting =  json_decode(json_encode($this->check_email_template($emailtype,$userlist[0]['company_parent'])));
                                }
                                
                                $finalcontent = nl2br($this->filterCustomEmail($userlist,$userlist[0]['company_parent'],$customsetting->content,'','','',$tryseraID,'https://docs.google.com/spreadsheets/d/' . $spreadSheetID . '/edit?usp=sharing'));
                                $finalsubject = $this->filterCustomEmail($userlist,$userlist[0]['company_parent'],$customsetting->subject,'','','',$tryseraID);
                                $finalfrom = $this->filterCustomEmail($userlist,$userlist[0]['company_parent'],$customsetting->fromName,'','','',$tryseraID);

                                $details = [
                                    'title' => ucwords($finalsubject),
                                    'content' => $finalcontent,
                                ];

                                $from = [
                                    'address' => (isset($customsetting->fromAddress) && $customsetting->fromAddress != '')?$customsetting->fromAddress:'noreply@sitesettingsapi.com',
                                    'name' => (isset($finalfrom) && $finalfrom != '')?$finalfrom:$defaultFromName,
                                    'replyto' => (isset($customsetting->fromReplyto) && $customsetting->fromReplyto != '')?$customsetting->fromReplyto:'support@sitesettingsapi.com',
                                ];
                    
                                if ($smtpusername != "" && $chkcustomsetting == "") {
                                    $from = [
                                        'address' => $smtpusername,
                                        'name' => (isset($finalfrom) && $finalfrom != '')?$finalfrom:$defaultFromName,
                                        'replyto' => $smtpusername,
                                    ];
                                }

                                //$this->send_email($adminEmail,$from,ucwords($finalsubject),$details,array(),'emails.customemail',$userlist[0]['company_parent']);
                                
                                /** START NEW METHOD EMAIL */

                                //$this->send_email($adminEmail,$from,$userlist[0]['company_name'] . ' - ' . $campaignName . ' #' . $tryseraID . ' Google Spreadsheet Link',$details,array(),'emails.spreadsheetlink',$companyID);

                            /** NOTIFY ADMIN */

                            /** SENT TO CLIENT AND ADMIN THE LINK FOR THE PERMISSION */

                    }
                }
                /** CHECK IF GOOGLE SPREADSHEET */

                /** SENT TO CLIENT EMBEDDED CODE */
                
                $AdminDefault = $this->get_default_admin($companyID);
                $AdminDefaultEmail = (isset($AdminDefault[0]['email']))?$AdminDefault[0]['email']:'';

                /** GET DOMAIN OR SUBDOMAIN FOR EMBEDDED CODE */
                $datacompany = Company::select('domain','subdomain','status_domain')
                                    ->where('id','=',$companyID)
                                    ->get();

                $jsdomain = 'px.0o0o.io/px.min.js';
                if (config('services.appconf.devmode') === true) {
                    $jsdomain = 'api.emmsandbox.com/px.min.js';
                }

                if (count($datacompany) > 0) {
                    $jsdomain = trim($datacompany[0]['subdomain']);
                    if ($datacompany[0]['domain'] != '' && $datacompany[0]['status_domain'] == 'ssl_acquired') {
                        $jsdomain = trim($datacompany[0]['domain']);
                    }
                    if (config('services.appconf.devmode') === true) {
                        $jsdomain = $jsdomain . '/px-sandbox.min.js';
                    }else{
                        $jsdomain = $jsdomain . '/px.min.js';
                    }
                }
                /** GET DOMAIN OR SUBDOMAIN FOR EMBEDDED CODE */

                $defaultdomain = $this->getDefaultDomainEmail($userlist[0]['company_parent']);
                
                $details = [
                    'defaultadmin' => $AdminDefaultEmail,
                    'leadspeek_api_id' => $tryseraID,
                    'jsdomain' => $jsdomain,
                ];

                $from = [
                    'address' => 'noreply@' . $defaultdomain,
                    'name' => $productlocalname . ' Support',
                    'replyto' => 'support@' . $defaultdomain,
                ];

                if (trim($clientOwnerEmail) != "") {
                    $tmp = array();
                    array_push($tmp,$clientOwnerEmail);
                }else{
                    $tmp = explode(PHP_EOL, $reportSentTo);
                }
                
                $this->send_email($tmp,$from,$campaignName . ' #' . $tryseraID . ' lead generation code for your website',$details,array(),'emails.embeddedcode',$companyID);
               
                /** SENT TO CLIENT EMBEDDED CODE */

            }
        /** CHECK IF CLIENT WANT LEADSPEEK LOCAL */
        
        /** CHECK IF CLIENT WANT LEADSPEEK LOCATOR */
            if($answers['asec4_1'] == 'Yes') {

                $leadspeekType = 'locator';
                $campaignName = ($answers['campaignlocatorname'] != '')?$answers['campaignlocatorname']:'Campaign Locator';
                $urlCodeAds = trim($answers['asec5_2']);
                $leadspeek_locator_zip = trim($answers['asec5_3']);
                $leadspeek_locator_state = trim($answers['asec5_4']);
                $leadspeek_locator_state_simplifi = trim($answers['asec5_4_simplifi']);
                $leadspeek_locator_city = trim($answers['asec5_4_2']);
                $leadspeek_locator_city_simplifi = trim($answers['asec5_4_2_simplifi']);
                $leadspeek_locator_country = trim($answers['asec5_4_1']);
                $leadspeek_locator_keyword = trim($answers['asec5_10']);
                $lp_limit_leads = trim($answers['asec5_6']);
                //$lp_enddate = date('Y-m-d',strtotime(trim($answers['asec5_5_2'])));
                $startdatecampaign = (isset($request->startdatecampaign))?$request->startdatecampaign:'';
                $enddatecampaign = (isset($request->enddatecampaign))?$request->enddatecampaign:'';

                $oristartdatecampaign = (isset($request->oristartdatecampaign))?$request->oristartdatecampaign:'';
                $orienddatecampaign = (isset($request->orienddatecampaign))?$request->orienddatecampaign:'';
                

                /** CREATE TRYSERA ID */
                $tryseraID = "";
                $tryseraCustomID = $this->_moduleID . '_' . $companyID . '00' . $usrID . '_' . date('His');
                /*if (config('services.appconf.devmode') === false) {
                    $tryseraID = $this->create_leadspeek_api_client($companyName . ' - ' . $campaignName,$tryseraCustomID);
                }else{
                    $tryseraID = rand(1000,9999);
                }*/

                $tryseraID = $this->generateLeadSpeekIDUniqueNumber();

                if (empty($tryseraID) || trim($tryseraID) == '') {
                    return response()->json(array('result'=>'failed','message'=>'Sorry, system can not get the leadspeek ID, would you please try again?','data'=>array()));
                    exit;die();
                }
                /** CREATE TRYSERA ID */

                $_costperlead = (isset($masterCostAgency->locator->Weekly->LocatorCostperlead))?$masterCostAgency->locator->Weekly->LocatorCostperlead:'0';
                $_platformfee = (isset($masterCostAgency->locator->Weekly->LocatorPlatformFee))?$masterCostAgency->locator->Weekly->LocatorPlatformFee:'0';
                $_lp_min_cost_month = (isset($masterCostAgency->locator->Weekly->LocatorMinCostMonth))?$masterCostAgency->locator->Weekly->LocatorMinCostMonth:'0';
                $lp_limit_leads = (isset($masterCostAgency->locator->Weekly->LocatorLeadsPerday))?$masterCostAgency->locator->Weekly->LocatorLeadsPerday:'0';

                if ($paymenttermDefault == "Monthly") {
                    $_costperlead = (isset($masterCostAgency->locator->Monthly->LocatorCostperlead))?$masterCostAgency->locator->Monthly->LocatorCostperlead:'0';
                    $_platformfee = (isset($masterCostAgency->locator->Monthly->LocatorPlatformFee))?$masterCostAgency->locator->Monthly->LocatorPlatformFee:'0';
                    $_lp_min_cost_month = (isset($masterCostAgency->locator->Monthly->LocatorMinCostMonth))?$masterCostAgency->locator->Monthly->LocatorMinCostMonth:'0';
                    $lp_limit_leads = (isset($masterCostAgency->locator->Monthly->LocatorLeadsPerday))?$masterCostAgency->locator->Monthly->LocatorLeadsPerday:'0';
                }else if ($paymenttermDefault == "One Time") {
                    $_costperlead = (isset($masterCostAgency->locator->OneTime->LocatorCostperlead))?$masterCostAgency->locator->OneTime->LocatorCostperlead:'0';
                    $_platformfee = (isset($masterCostAgency->locator->OneTime->LocatorPlatformFee))?$masterCostAgency->locator->OneTime->LocatorPlatformFee:'0';
                    $_lp_min_cost_month = (isset($masterCostAgency->locator->OneTime->LocatorMinCostMonth))?$masterCostAgency->locator->OneTime->LocatorMinCostMonth:'0';
                    $lp_limit_leads = (isset($masterCostAgency->locator->OneTime->LocatorLeadsPerday))?$masterCostAgency->locator->OneTime->LocatorLeadsPerday:'0';
                }

                if ($clientdefaultprice == false) {
                    /** Cost per leads */
                    $_costperlead = '1.50';
                    if (trim($leadspeek_locator_require) == 'FirstName,LastName') {
                        $_costperlead = (isset($masterCostAgency->locatorlead->FirstName_LastName))?$masterCostAgency->locatorlead->FirstName_LastName:'1.50';
                    }else if (trim($leadspeek_locator_require) == 'FirstName,LastName,MailingAddress') {
                        $_costperlead = (isset($masterCostAgency->locatorlead->FirstName_LastName_MailingAddress))?$masterCostAgency->locatorlead->FirstName_LastName_MailingAddress:'2';
                    }else if (trim($leadspeek_locator_require) == 'FirstName,LastName,MailingAddress,Phone') {
                        $_costperlead = (isset($masterCostAgency->locatorlead->FirstName_LastName_MailingAddress_Phone))?$masterCostAgency->locatorlead->FirstName_LastName_MailingAddress_Phone:'3';
                    }
                    /** Cost per leads */
                }
                
                $newclient = LeadspeekUser::create([
                    'module_id' => $this->_moduleID,
                    'company_id' => $companyID,
                    'user_id' => $usrID,
                    'report_type' => $reportType,
                    'report_sent_to' => $reportSentTo,
                    'admin_notify_to' => $_adminNotifyTo,
                    'leads_amount_notification' => $leadsAmountNotification,
                    'total_leads' => 0,
                    'start_billing_date' => date('Y-m-d H:i:s'),
                    'spreadsheet_id' => '',
                    'filename' => '',
                    'report_frequency_id' => $report_frequency_id,
                    'report_frequency' => $report_frequency,
                    'report_frequency_unit' => $report_frequency_unit,
                    'leadspeek_type' => $leadspeekType,
                    'group_company_id' => $companyGroupID,
                    'campaign_name' => $campaignName,
                    'campaign_startdate' => $startdatecampaign,
                    'campaign_enddate' => $enddatecampaign,
                    'ori_campaign_startdate' => $oristartdatecampaign,
                    'ori_campaign_enddate' => $orienddatecampaign,
                    'timezone' => $timezone,
                    'url_code' => '',
                    'url_code_thankyou' => '',
                    'url_code_ads' => $urlCodeAds,
                    'leadspeek_organizationid' => '',
                    'leadspeek_campaignsid' => '',
                    'paymentterm' => $paymenttermDefault,
                    'leadspeek_locator_require' => $leadspeek_locator_require,
                    'leadspeek_locator_zip' => $leadspeek_locator_zip,
                    'leadspeek_locator_state' => $leadspeek_locator_state,
                    'leadspeek_locator_state_simplifi' => $leadspeek_locator_state_simplifi,
                    'leadspeek_locator_city' => $leadspeek_locator_city,
                    'leadspeek_locator_city_simplifi' => $leadspeek_locator_city_simplifi,
                    'leadspeek_locator_keyword' => $leadspeek_locator_keyword,
                    'lp_limit_leads' => $lp_limit_leads,
                    'lp_enddate' => $enddatecampaign,
                    'lp_limit_startdate' => $startdatecampaign,
                    'hide_phone' => $clientHidePhone,
                    'last_lead_pause' => '0000-00-00 00:00:00',
                    'last_lead_start' => '0000-00-00 00:00:00',
                    'active' => 'F',
                    'disabled' => 'T',
                    'active_user' => 'F',
                    'leadspeek_api_id' => $tryseraID,
                    'embeddedcode_crawl' => 'T',
                    'embedded_status' => 'We are still building the magic!',
                    'questionnaire_answers' => json_encode($answers),
                    'cost_perlead' => $_costperlead,
                    'platformfee' => $_platformfee,
                    'lp_max_lead_month' => '0',
                    'lp_min_cost_month' => $_lp_min_cost_month,
                    'file_url' => '',
                    'national_targeting' => $nationalTargeting,
                    'phoneenabled' => $phoneenabled,
                    'homeaddressenabled' => $homeaddressenabled,
                    'require_email' => $requireemailaddress,
                    'reidentification_type' => $reidentificationtype,
                    'location_target' => $locationtarget,
                    'trysera' => 'F',
                ]);

                $newclientID = $newclient->id;

                /** CHECK IF GOOGLE SPREADSHEET */
                if ($reportType == "GoogleSheet") {
                //if (false) {
                /** CHECK IF HAVE GOOGLE CONNECTION */
                $chkGconnection = $this->get_setting($companyID,$this->_moduleID,$this->_settingTokenName);
                if(count($chkGconnection) > 0 && $chkGconnection[0]->setting_value != '') {
                /** CHECK IF HAVE GOOGLE CONNECTION */
                        /** CREATE GOOGLE SHEET ONLINE */
                        $spreadsheetTitle = trim($companyName . ' - ' . $campaignName . ' #' . $tryseraID);
                        $clientGoogle = new GoogleSheet($companyID,$this->_moduleID,$this->_settingTokenName,'',true,$newclient->leadspeek_api_id ?? "",$newclient->leadspeek_type ?? "", __FUNCTION__);
                        $spreadSheetID = $clientGoogle->createSpreadSheet($spreadsheetTitle);
                        $clientGoogle->updateSheetTitle(date('Y'));

                        $clientGoogle->setSheetName(date('Y'));
                        
                        $contentHeader = array();
                        $content = array();
                        
                        /** INTIAL GOOGLE SPREADSHEET HEADER */
                        //$contentHeader[] = array('ID','Email','IP','Source','OptInDate','ClickDate','Referer','Phone','First Name','Last Name','Address1','Address2','City','State','Zipcode');
                        $contentHeader[] = array('ID','ClickDate','First Name','Last Name','Email1','Email2','Phone1','Phone2','Address1','Address2','City','State','Zipcode','Keyword');
                        /** INTIAL GOOGLE SPREADSHEET HEADER */
                        $savedData = $clientGoogle->saveDataHeaderToSheet($contentHeader);

                        /** ADD DUMMY DATA ON FIRST ROW FOR ZAPIER TEST */
                        //$content[] = array('00000000','johndoe@example.com','','',date('m/d/Y h:i:s A'),date('m/d/Y h:i:s A'),'','','John','Doe','John Doe Street','','Columbus','OH','43055');
                        $content[] = array('00000000',date('m/d/Y h:i:s A'),'John','Doe','johndoe1-' . $tryseraID . '@example.com','johndoe2-' . $tryseraID . '@example.com','123-123-1234','567-567-5678',$tryseraID . ' John Doe Street','suite 101','Columbus','OH','43055','keyword');
                        $savedData = $clientGoogle->saveDataToSheet($content);
                        /** ADD DUMMY DATA ON FIRST ROW FOR ZAPIER TEST */

                        $newclient->spreadsheet_id = $spreadSheetID;
                        $newclient->save();

                        /** CHECK IF PHONE HIDE OR NOT */
                        $hidePhone = ($clientHidePhone == 'T')?true:false;
                        $sheetID = $clientGoogle->getSheetID(date('Y'));
                        /** HIDE EVERYTHING ELSE BY DEFAULT EXCEPT firstname, lastname, email, click date, and address */
                        //$clientGoogle->showhideColumn($sheetID,7,8,$hidePhone);
                        $clientGoogle->showhideColumn($sheetID,0,1,'T');
                        //$clientGoogle->showhideColumn($sheetID,2,5,'T');
                        //$clientGoogle->showhideColumn($sheetID,6,7,'T');
                        
                        if (trim($leadspeek_locator_require) == "FirstName,LastName") {
                                $clientGoogle->showhideColumn($sheetID,6,8,'T');
                                $clientGoogle->showhideColumn($sheetID,8,13,'F');
                                //$clientGoogle->showhideColumn($sheetID,11,12,'T');
                        }else if (trim($leadspeek_locator_require) == 'FirstName,LastName,MailingAddress') {
                                $clientGoogle->showhideColumn($sheetID,6,8,'T');
                                $clientGoogle->showhideColumn($sheetID,8,13,'F');
                                //$clientGoogle->showhideColumn($sheetID,11,12,'F');
                        }else if (trim($leadspeek_locator_require) == 'FirstName,LastName,MailingAddress,Phone') {
                                
                                /** NEW OPTIONS FOR ENABLE PHONE AND MAIL ADDRESS */
                                if($phoneenabled == 'T' && $homeaddressenabled == 'F') {
                                    $clientGoogle->showhideColumn($sheetID,6,8,'F');
                                    $clientGoogle->showhideColumn($sheetID,8,13,'T');
                                    //$clientGoogle->showhideColumn($sheetID,11,12,'T');
                                    //$clientGoogle->showhideColumn($sheetID,12,13,'T');
                                    //$clientGoogle->showhideColumn($sheetID,13,14,'T');
                                    //$clientGoogle->showhideColumn($sheetID,14,15,'T');
                                }else if($phoneenabled == 'F' && $homeaddressenabled == 'T') {
                                    $clientGoogle->showhideColumn($sheetID,6,8,'T');
                                    $clientGoogle->showhideColumn($sheetID,8,13,'F');
                                    //$clientGoogle->showhideColumn($sheetID,11,12,'F');
                                    //$clientGoogle->showhideColumn($sheetID,12,13,'F');
                                    //$clientGoogle->showhideColumn($sheetID,13,14,'F');
                                    //$clientGoogle->showhideColumn($sheetID,14,15,'F');
                                }else if($phoneenabled == 'F' && $homeaddressenabled == 'F') {
                                    $clientGoogle->showhideColumn($sheetID,6,8,'T');
                                    $clientGoogle->showhideColumn($sheetID,8,13,'T');
                                    //$clientGoogle->showhideColumn($sheetID,11,12,'T');
                                    //$clientGoogle->showhideColumn($sheetID,12,13,'T');
                                    //$clientGoogle->showhideColumn($sheetID,13,14,'T');
                                    //$clientGoogle->showhideColumn($sheetID,14,15,'T');
                                }else{
                                    $clientGoogle->showhideColumn($sheetID,6,8,'F');
                                    $clientGoogle->showhideColumn($sheetID,8,13,'F');
                                    //$clientGoogle->showhideColumn($sheetID,11,12,'F');
                                    //$clientGoogle->showhideColumn($sheetID,12,13,'F');
                                    //$clientGoogle->showhideColumn($sheetID,13,14,'F');
                                    //$clientGoogle->showhideColumn($sheetID,14,15,'F');
                                }
                                /** NEW OPTIONS FOR ENABLE PHONE AND MAIL ADDRESS */
                        }
                    
                        /** HIDE EVERYTHING ELSE BY DEFAULT EXCEPT firstname, lastname, email, click date, and address */
                        /** CHECK IF PHONE HIDE OR NOT */

                        /** CREATE GOOGLE SHEET ONLINE */

                        /** SENT TO CLIENT AND ADMIN THE LINK FOR THE PERMISSION */

                        /** FIND ADMIN EMAIL */
                            //$tmp = User::select('email')->whereIn('id', $_adminNotifyTo)->get();
                            $adminEmail = array();
                            foreach($admins as $ad) {
                                $permission = $clientGoogle->createPermission($spreadSheetID,$ad['email'],'user','writer',false);
                                array_push($adminEmail,trim($ad['email']));
                            }
                            /** SENT ALSO TO EMAIL CLIENT */
                            $tmp = explode(PHP_EOL, $reportSentTo);
                            foreach($tmp as $ad) {
                                $permission = $clientGoogle->createPermission($spreadSheetID,$ad,'user','reader',false);
                                array_push($adminEmail,trim($ad));
                            }
                            /** SENT ALSO TO EMAIL CLIENT */
                            
                        /** FIND ADMIN EMAIL */

                        /** FIND USER COMPANY */
                        
                        /** FIND USER COMPANY */
                            // $AdminDefault = $this->get_default_admin($companyID);
                            // $AdminDefaultEmail = (isset($AdminDefault[0]['email']))?$AdminDefault[0]['email']:'';

                            // $emailfname = "";
                            // if ($userlist[0]['name'] != "") {
                            //     $_tmp = explode(" ",$userlist[0]['name']);
                            //     $emailfname = $_tmp[0];
                            // }
                            
                            // $details = [
                            //     'defaultadmin' => $AdminDefaultEmail,
                            //     'companyName' => $userlist[0]['company_name'],
                            //     'name'  => $emailfname,
                            //     'campaignName' => $campaignName,
                            //     'TryseraID' =>  $tryseraID,
                            //     'links' => 'https://docs.google.com/spreadsheets/d/' . $spreadSheetID . '/edit?usp=sharing',
                            // ];
                            
                            /** START NEW METHOD EMAIL */
                            $defaultFromName = $productlocatorname . ' Support';
                            $from = [
                                'address' => 'noreply@exactmatchmarketing.com',
                                'name' => $productlocatorname . ' Support',
                                'replyto' => 'support@exactmatchmarketing.com',
                            ];
                            
                            if (strtolower($leadspeekType) == 'local') {
                                $defaultFromName = $productlocalname . ' Support';
                                
                                $from = [
                                    'address' => 'noreply@exactmatchmarketing.com',
                                    'name' => $productlocalname . ' Support',
                                    'replyto' => 'support@exactmatchmarketing.com',
                                ];
                            }

                            $smtpusername = $this->set_smtp_email($userlist[0]['company_parent']);
                            $emailtype = 'em_campaigncreated';

                            $customsetting = $this->getcompanysetting($userlist[0]['company_parent'],$emailtype);
                            $chkcustomsetting = $customsetting;

                            if ($customsetting == '') {
                                $customsetting =  json_decode(json_encode($this->check_email_template($emailtype,$userlist[0]['company_parent'])));
                            }
                            
                            $finalcontent = nl2br($this->filterCustomEmail($userlist,$userlist[0]['company_parent'],$customsetting->content,'','','',$tryseraID,'https://docs.google.com/spreadsheets/d/' . $spreadSheetID . '/edit?usp=sharing'));
                            $finalsubject = $this->filterCustomEmail($userlist,$userlist[0]['company_parent'],$customsetting->subject,'','','',$tryseraID);
                            $finalfrom = $this->filterCustomEmail($userlist,$userlist[0]['company_parent'],$customsetting->fromName,'','','',$tryseraID);

                            $details = [
                                'title' => ucwords($finalsubject),
                                'content' => $finalcontent,
                            ];

                            $from = [
                                'address' => (isset($customsetting->fromAddress) && $customsetting->fromAddress != '')?$customsetting->fromAddress:'noreply@sitesettingsapi.com',
                                'name' => (isset($finalfrom) && $finalfrom != '')?$finalfrom:$defaultFromName,
                                'replyto' => (isset($customsetting->fromReplyto) && $customsetting->fromReplyto != '')?$customsetting->fromReplyto:'support@sitesettingsapi.com',
                            ];
                
                            if ($smtpusername != "" && $chkcustomsetting == "") {
                                $from = [
                                    'address' => $smtpusername,
                                    'name' => (isset($finalfrom) && $finalfrom != '')?$finalfrom:$defaultFromName,
                                    'replyto' => $smtpusername,
                                ];
                            }

                            //$this->send_email($adminEmail,$from,ucwords($finalsubject),$details,array(),'emails.customemail',$userlist[0]['company_parent']);
                            
                            /** START NEW METHOD EMAIL */

                            //$this->send_email($adminEmail,$from,$userlist[0]['company_name'] . ' - ' . $campaignName . ' #' . $tryseraID . ' Google Spreadsheet Link',$details,array(),'emails.spreadsheetlink',$companyID);
                        
                        /** NOTIFY ADMIN */

                        /** SENT TO CLIENT AND ADMIN THE LINK FOR THE PERMISSION */
                    }

                }
                /** CHECK IF GOOGLE SPREADSHEET */  
                
                /** SET FILE LOCATOR URL */
                $usrFiles = "";
                $fileurlemail = array();
                
                foreach($_filelocatorurl as $fl) {
                    $usrFiles = $usrFiles . $tryseraID . '_' . $fl . '|';
                    array_push($fileurlemail,$tryseraID . '_' . $fl);
                }
                $usrFiles = rtrim($usrFiles,'|');
                //$_filelocatorurl = str_replace(" ","_",$userlist[0]['company_name']) . "_" . str_replace(" ","_",$campaignName) . '_' . $tryseraID . "." . $_locatorext;
                $newclient->file_url = $usrFiles;
                $newclient->save();
                /** SET FILE LOCATOR URL */

                /** SETUP SIMPLI.FI CAMPAIGN */
                    if ($companyOrganizationID == '') {
                    //if (false) {
                        /** CREATE ORGANIZATION */
                        $sifiEMMStatus = "[CLIENT]";
                        if (config('services.appconf.devmode') === true) {
                            $sifiEMMStatus = "[CLIENT BETA]";
                        }

                        $companyOrganizationID = $this->createOrganization(trim($userlist[0]['company_name']) . ' ' . $sifiEMMStatus,$defaultParentOrganization);
                        
                        $updateOrg = Company::find($companyUserID);
                        $updateOrg->simplifi_organizationid = $companyOrganizationID;
                        $updateOrg->save();
                        /** CREATE ORGANIZATION */
                    } 
                   
                    /** CREATE A CAMPAIGN */
                    if ($companyOrganizationID != '') {
                    //if (false) {
                        $_state = explode(",",trim($answers['asec5_4_simplifi']));
                        $_cities = explode(",",trim($answers['asec5_4_2_simplifi']));
                        
                        $_geotarget = array_merge($_state,$_cities);
                        $_postalcode = array(); //asec5_3
                        $tmp_postcode = explode(',',$answers['asec5_3']);
                        foreach($tmp_postcode as $postcode) {
                            if($postcode != '') {
                                array_push($_postalcode,array("postal_code"=>$postcode, "country_code"=>"USA"));
                            }
                        }
                        
                        if (trim($answers['asec5_4']) != "") {
                            if (trim($answers['asec5_4_2']) != "") {
                                $answers['asec5_4_2'] = "," . $answers['asec5_4_2'];
                            }
                        }
                        //$sifi_campaignname = trim($userlist[0]['company_name']) . ' - ' . trim($answers['asec5_4']) .  trim($answers['asec5_4_2']) . ' - Keyword Locator #' . $tryseraID; 
                        $sifi_campaignname = trim($companyParentName . $userlist[0]['company_name'] . '-' . $campaignName . '-KL #' . $tryseraID);

                        $_keywords = array(); 
                        $tmp_keywords = $campaign_keywords;
                        foreach($tmp_keywords as $keyword) {
                            if(trim($keyword) != "") {
                                array_push($_keywords,array("name"=>$keyword, "max_bid"=>""));
                            }
                        }
                        
                        /** IF NATIONAL TARGETING */
                        $_nationaltargeting = 'F';
                        if ($answers['asec5_4_0_0'] === true && $answers['asec5_4_0_1'] === false && $answers['asec5_4_0_2'] === false && $answers['asec5_4_0_3'] === false) {
                            $_geotarget = array('8180');
                            $_postalcode = array();
                            $sifi_campaignname = trim($companyParentName . $userlist[0]['company_name']) . '-' . $campaignName .'-NT-KL #' . $tryseraID; 
                            $_nationaltargeting = 'T';
                        }
                        /** IF NATIONAL TARGETING */
                        $newCampaignID = $this->createCampaign($companyOrganizationID,$sifi_campaignname,$_geotarget,$_postalcode,$oristartdatecampaign,$orienddatecampaign);
                        
                        $this->createBudgetPlans($newCampaignID,$oristartdatecampaign,$orienddatecampaign);
                        $this->createKeywords($newCampaignID,$_keywords);
                        $this->createDeviceType($newCampaignID,36);

                        $creativeGroupID = $this->createCreativeGroups($newCampaignID, trim($userlist[0]['company_name']) . ' Uncommon Reach ads #' . $tryseraID);
                        
                        $this->createAds($companyName,$tryseraID,$companyOrganizationID,$newCampaignID,$creativeGroupID);

                        /** UPDATE COMPANY ORGANIZATION ID AND CAMPAIGN ID ON LEADSPEEK USER */
                        $newclient->leadspeek_organizationid = $companyOrganizationID;
                        $newclient->leadspeek_campaignsid = $newCampaignID;
                        $newclient->national_targeting = $_nationaltargeting;
                        $newclient->save();
                        /** UPDATE COMPANY ORGANIZATION ID AND CAMPAIGN ID ON LEADSPEEK USER */
                    }    
                /** CREATE A CAMPAIGN */
                
                /** SETUP SIMPLI.FI CAMPAIGN */

                /** TEMPORARY ADD EMM ADMIN TO NOTIFY */
                // $from = [
                //     'address' => 'noreply@exactmatchmarketing.com',
                //     'name' => $productlocatorname . ' Support',
                //     'replyto' => 'support@exactmatchmarketing.com',
                // ];
                
                // if (strtolower($leadspeekType) == 'local') {
                //     $from = [
                //         'address' => 'noreply@exactmatchmarketing.com',
                //         'name' => $productlocalname . ' Support',
                //         'replyto' => 'support@exactmatchmarketing.com',
                //     ];
                // }
                
                $emmEmail = array();
                $details = [
                    'title' => 'Admin Notification',
                    'content' => $companyParentName . $userlist[0]['company_name'] . ' added a new campaign<br/><br/>Campaign Name: ' . $campaignName . '<br/><br/>Campaign ID: #' . $tryseraID,
                ];
                
                //array_push($emmEmail,'serverlogs@sitesettingsapi.com');
                array_push($emmEmail,'carrie@uncommonreach.com');
                //array_push($emmEmail,'daniel@exactmatchmarketing.com');
                
                //$this->send_email($emmEmail,$from,$companyParentName . $userlist[0]['company_name'] . ' added a new campaign - ' . $campaignName . ' #' . $tryseraID . ' (Client Setup Complete)',$details,array(),'emails.customemail');
                /** TEMPORARY ADD EMM ADMIN TO NOTUFY */

            }else{ 
                /** EVEN THEY ARE NOT CHOOSING SEARCH ID THEN WE STILL NEED TO CREATE THE SIMPLIFI */
                if ($companyOrganizationID == '') {
                    /** CREATE ORGANIZATION */
                    $sifiEMMStatus = "[CLIENT]";
                    if (config('services.appconf.devmode') === true) {
                        $sifiEMMStatus = "[CLIENT BETA]";
                    }

                    $companyOrganizationID = $this->createOrganization(trim($userlist[0]['company_name']) . ' ' . $sifiEMMStatus,$defaultParentOrganization);
                    
                    /** CREATE ORGANIZATION */
                } 
                $update = Company::find($companyUserID);
                $update->simplifi_organizationid = $companyOrganizationID;
                $update->save();
                /** EVEN THEY ARE NOT CHOOSING SEARCH ID THEN WE STILL NEED TO CREATE THE SIMPLIFI */
            }
        /** CHECK IF CLIENT WANT LEADSPEEK LCOATOR */


        /** ALGORITHM TO AUTOMATICALLY SETUP CLIENT BASE ON QUESTIONNAIRE */

        /** SAVE THE QUESTIONNAIRE ANSWER AND UPDATE SETUP COMPLETED */
            $usr = User::find($usrID);
            //$usr->questionnaire_answers = $answers;
            $usr->profile_setup_completed = $statusComplete;
            $usr->questionnaire_setup_completed = 'T';
            $usr->save();
        /** SAVE THE QUESTIONNAIRE ANSWER AND UPDATE SETUP COMPLETED */
        
        // /** SENT TO ADMIN QUESTIONNAIRE FORM */
        // $details = [
        //     'answers'  => $answers,
        //     //'filelocator' => $fileurlemail,
        //     'cdnurl' => $cdnurl,
        //     'companyname' => $userlist[0]['company_name'],
        //     'clientname' => $userlist[0]['name'],
        //     'clientemail' => $userlist[0]['email'],
        //     'clientphone' => $userlist[0]['phonenum'],
        //     'localname' => $productlocalname,
        //     'locatorname' => $productlocatorname,
        //     'campaignlocalname' => $campaignlocalname,
        //     'campaignlocatorname' => $campaignlocatorname,
        //     'locatorstartcampaign' => $startdatecampaign,
        //     'locatorendcampaign' => $enddatecampaign,
        //     'defaultadmin' => $AdminDefaultEmail,
        // ];

        // $from = [
        //     'address' => 'noreply@exactmatchmarketing.com',
        //     'name' => 'Questionnaire Result Support',
        //     'replyto' => 'support@exactmatchmarketing.com',
        // ];
        
        // $this->send_email($admins,$from,'Questionnaire result for ' . $userlist[0]['company_name'] . ' - ' . $campaignName . ' #' . $tryseraID,$details,array(),'emails.questionnaire',$companyID);
        
        // $tmp = explode(PHP_EOL, $reportSentTo);

        // $this->send_email($tmp,$from,'Questionnaire result for ' . $userlist[0]['company_name'] . ' - ' . $campaignName . ' #' . $tryseraID,$details,array(),'emails.questionnaire',$companyID);
        
        // /** SENT TO ADMIN QUESTIONNAIRE FORM */

        /*$usr = User::find($usrID);
        $usr->profile_setup_completed = $statusComplete;
        $usr->save();
        
        return response()->json(array('result'=>'success','params'=>$usr));
        */
        return response()->json(array('result'=>'success','params'=>$usr,'newfilenameid'=>$tryseraID));
    }

    /** SIMPLI.FI CREATE CAMPAIGN FUNCTIONS */
    private function createAds($companyName,$leadspeekID,$companyOrganizationID,$campaignID,$creativeGroupID) {
        /** COPY Master Ads file to temporary folder, read and update the ads ID */
            $folderName = $leadspeekID;
            $fileName = $companyName . ' Locator ' . $leadspeekID . '.zip';
            $destinationPath= '/uploads/zip';

            $ads_des= storage_path("ads_des/tmp/" . $folderName);
            $ads_src_fld = array('ads_300x250_ur','ads_970x250_ur','ads_728x90_ur','ads_300x600_ur','ads_250x250_ur','ads_200x200_ur');
            $adsizelist = array(1,40,2,19,17,13);
            //$ads_src_fld = array('ads_300x250_ur');
            //$adsizelist = array(1);
            $i=0;

            if (!File::exists( $ads_des)) {
                File::makeDirectory($ads_des, 0755, true);
            }

            foreach($ads_src_fld as $srcfld) {
                $srcfiles = Storage::disk('root')->files("ads_src/" . $srcfld);
                if (!File::exists($ads_des . '/' . $srcfld)) {
                    File::makeDirectory($ads_des . '/' . $srcfld, 0755, true);
                }
                
                foreach($srcfiles as $fl) {
                    $ads_despath = str_replace('ads_src','ads_des/tmp/' . $folderName,$fl);
                    //echo  $ads_despath . '<br>';
                    if (!File::exists( storage_path($ads_despath))) {
                        Storage::disk('root')->copy($fl,$ads_despath);
                    }
                }
                /** READ CONTENT THE FILE */
                if (File::exists(storage_path('ads_des/tmp/' . $folderName . '/' . $srcfld . '/index.html'))) {
                    $tmpread = File::get(storage_path('ads_des/tmp/' . $folderName . '/' . $srcfld . '/index.html'));
                    //$tmpread = str_replace('/s/XXXX/','/s/' . $leadspeekID . '/', $tmpread);
                    $tmpread = str_replace('XXXX|',$leadspeekID . '|', $tmpread);
                    $pxurl = 'px.0o0o.io';
                    if (config('services.appconf.devmode') === true) {
                        $pxurl = 'api.emmsandbox.com';
                    }
                    $tmpread = str_replace('{pixeldomain}',$pxurl, $tmpread);
                    File::replace(storage_path('ads_des/tmp/' . $folderName . '/' . $srcfld . '/index.html'),$tmpread);
                }
                /** READ CONTENT THE FILE */

                /** ZIP THE FILES */
                $adssize = str_replace('ads_','',$srcfld);
                $adssize = str_replace('_ur','',$adssize);

                $fileName = $companyName . ' Locator ' . $leadspeekID . ' ' . $adssize . '.zip';
                $fileName = $this->makeSafeFileName($fileName);

                $zip = new ZipArchive();
                $isOpened =  $zip->open(storage_path('ads_des/tmp/' . $folderName) . "/" . $fileName, ZipArchive::CREATE | \ZipArchive::OVERWRITE);
                if($isOpened === TRUE){
                    
                    foreach($srcfiles as $fl) {
                        $ads_despath = str_replace('ads_src','ads_des/tmp/' . $folderName,$fl);
                        $folder = str_replace('/' . basename($ads_despath),'',$ads_despath);
                        //echo $ads_despath . ' | ' . basename($ads_despath) . '<br>';
                       
                        $relativeNameInZipFile = basename($ads_despath);
                        $zip->addFile(storage_path($ads_despath),$relativeNameInZipFile);
                        //echo $ads_despath . ' | ' . $relativeNameInZipFile . '<br/>';
                    }
                    $zip->close();
                    File::deleteDirectory(storage_path($folder));

                    /** UPLOAD THE ADS */
                    $this->uploadAds($companyName,$leadspeekID,$companyOrganizationID,$campaignID,$fileName,'ads_des/tmp/' . $folderName,$adsizelist[$i],$creativeGroupID);
                    /** UPLOAD THE ADS */
                    //echo $folder . '<br/>';
                    
                }else{
                    return response()->html("Unbale to create/open zip");
                } 
                /** ZIP THE FILES */
                $i = $i + 1;
            }

            /** REMOVE ENTIRE FOLDER */
            File::deleteDirectory($ads_des);
            /** REMOVE ENTIRE FOLDER */
    }

    private function uploadAds($companyName,$leadspeekID,$organizationID,$campaignID,$adsName,$zipPath,$adsizeID,$creativeGroupID) {
        $http = new \GuzzleHttp\Client;

        $appkey = config('services.simplifi.app_key');
        $usrkey = config('services.simplifi.usr_key');
        $apiURL = config('services.simplifi.endpoint');

        
        $options = [
            'headers' => [
                'X-App-Key' => $appkey,        
                'X-User-Key' => $usrkey,
                'Content-Type' => 'multipart/form-data',
            ],
            'form_params' => [
                "ad" =>[
                    'name' => $adsName,
                    'pacing' => 100,
                    'ad_file_type_id' => 9,
                    'ad_size_id' => $adsizeID,
                    'creative_group_id' => $creativeGroupID,
                    //'target_url' => 'https://uncommonreach.com/?utm_source=EMM&utm_medium=UR&utm_campaign=' . urlencode(str_replace(" ","", $companyName . 'locator' . $leadspeekID)),
                    'target_url' => 'https://uncommonreach.com/?utm_source=' . $leadspeekID . '&utm_campaign={{keyword}}',
                ],
            ],
        ]; 

        $response = $http->post($apiURL . 'organizations/' . $organizationID . '/campaigns/' . $campaignID . '/ads',$options);
        $result =  json_decode($response->getBody());

        $newAdsID = $result->ads[0]->id;
        //return filesize(storage_path($zipPath) . "/" . $adsName);
        //exit;die();
        /** UPLOAD ZIP FILE USING CURL */

        $apiURL2 = $apiURL . "organizations/" . $organizationID . "/campaigns/" . $campaignID . "/ads/" . $newAdsID;

        $filename = storage_path($zipPath) . "/" . $adsName; // The name of the file
        $url = $apiURL2; // The URL to upload to
        $fieldname = 'ad[primary_creative]';
        $namefile = $adsName;
      
        // Manually create the body
        $requestBody = '';
        $separator = '-----'.md5(microtime()).'-----';
        $file = fopen($filename, 'r');
        $size = filesize($filename);
        $filecontent = fread($file, $size);

        $requestBody .= "--$separator\r\n"
                      . "Content-Disposition: form-data; name=\"$fieldname\"; filename=\"$namefile\"\r\n"
                      . "Content-Length: ".strlen($filecontent)."\r\n"
                      . "Content-Type: application/zip\r\n"
                      . "Content-Transfer-Encoding: binary\r\n"
                      . "\r\n"
                      . "$filecontent\r\n";
      
        // Terminate the body
        $requestBody .= "--$separator--";
      
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch,CURLOPT_HTTP_VERSION,CURL_HTTP_VERSION_1_1);
        curl_setopt($ch, CURLOPT_ENCODING, "");
        curl_setopt($ch, CURLOPT_MAXREDIRS, 10);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);

        // This is necessary as cURL will ignore the CURLOUT_POSTFIELDS if we use the build-in PUT method
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
        curl_setopt($ch, CURLOPT_POSTFIELDS, $requestBody);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
          'Content-Type: multipart/form-data; boundary="'.$separator.'"',
          'X-App-Key: ' . $appkey,
          'X-User-Key: ' . $usrkey,
        ));
        $response = curl_exec($ch);
    }

    private function createCreativeGroups($campaignID,$groupName) {
        $http = new \GuzzleHttp\Client;

        $appkey = config('services.simplifi.app_key');
        $usrkey = config('services.simplifi.usr_key');
        $apiURL = config('services.simplifi.endpoint');

        /** OVERWRITE START DATE CAMPAIGN FOR SYNC TO SIFI */
        date_default_timezone_set('America/New_York');
        /** OVERWRITE START DATE CAMPAIGN FOR SYNC TO SIFI */

        $datenow = date("Y-m-d");

        $options = [
            'headers' => [
                'X-App-Key' => $appkey,        
                'X-User-Key' => $usrkey,
                'Content-Type' => 'application/json',
            ],
            'json' => [
                'name' => $groupName,
                'flights' => [
                    [
                    'start_at' => $datenow,
                    'end_at' => null,
                    ]
                ]
            ]
        ]; 
        
        $response = $http->post($apiURL . 'campaigns/' . $campaignID . '/creative_groups',$options);
        $result =  json_decode($response->getBody());

        return $result->creative_groups[0]->id;
    }

    private function createDeviceType($campaignID,$deviceTypeID=36) {
        $http = new \GuzzleHttp\Client;

        $appkey = config('services.simplifi.app_key');
        $usrkey = config('services.simplifi.usr_key');
        $apiURL = config('services.simplifi.endpoint');

        $options = [
            'headers' => [
                'X-App-Key' => $appkey,        
                'X-User-Key' => $usrkey,
                'Content-Type' => 'application/json',
            ],
            'json' => [
                'device_type_ids' => [35,$deviceTypeID,37],
               
            ]
        ]; 

        $response = $http->put($apiURL . 'campaigns/' . $campaignID . '/device_types',$options);
        $result =  json_decode($response->getBody());
    }

    private function createKeywords($campaignID,$keywords,$organizationID = "") {
        $http = new \GuzzleHttp\Client;

        $appkey = config('services.simplifi.app_key');
        $usrkey = config('services.simplifi.usr_key');
        $apiURL = config('services.simplifi.endpoint');

        /** REMOVE EXISTING KEYWORD FIRST */
        if ($organizationID != "") {
            $options = [
                'headers' => [
                    'X-App-Key' => $appkey,        
                    'X-User-Key' => $usrkey,
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    
                ]
            ]; 

            $response = $http->delete($apiURL . 'organizations/' . $organizationID . '/campaigns/' . $campaignID . '/keywords',$options);
        }
        /** REMOVE EXISTING KEYWORD FIRST */

        $options = [
            'headers' => [
                'X-App-Key' => $appkey,        
                'X-User-Key' => $usrkey,
                'Content-Type' => 'application/json',
            ],
            'json' => [
                'campaign_ids' => [$campaignID],
                'keywords' => $keywords,
                
            ]
        ]; 

        $response = $http->post($apiURL . 'campaigns/bulk/keywords',$options);
        $result =  json_decode($response->getBody());
    }

    private function createBudgetPlans($campaignID,$startdatecampaign='',$enddatecampaign='') {
        $http = new \GuzzleHttp\Client;

        $appkey = config('services.simplifi.app_key');
        $usrkey = config('services.simplifi.usr_key');
        $apiURL = config('services.simplifi.endpoint');

        /** OVERWRITE START DATE CAMPAIGN FOR SYNC TO SIFI */
        date_default_timezone_set('America/New_York');
        /** OVERWRITE START DATE CAMPAIGN FOR SYNC TO SIFI */

        //$datenow = date("Y-m-d");
        //$end_date = date("Y-m-d", strtotime("+1 month",strtotime($datenow)));
        //$start_date = $startdatecampaign;
        $start_date = date('Y-m-d 00:00:00');
        $end_date = $enddatecampaign;

        $tmp = strtotime($end_date) - strtotime($start_date);
        $diffdays = abs(round($tmp / 86400));
        $totalbudget = number_format(6 * $diffdays,1,".","");

        $options = [
            'headers' => [
                'X-App-Key' => $appkey,        
                'X-User-Key' => $usrkey,
                'Content-Type' => 'application/json',
            ],
            'json' => [
                'start_date' => $start_date,
                'end_date' => $end_date,
                'total_budget' => $totalbudget,
                'adjusted_budget' => $totalbudget,
                'spent_budget' => 0.0,
                'available_rollover' => false,
            ]
        ]; 

        $response = $http->post($apiURL . 'campaigns/' . $campaignID . '/budget_plans',$options);
        $result =  json_decode($response->getBody());

    }

    private function getCampaignSifi($organizationID,$campaignID,$module="") {
        $http = new \GuzzleHttp\Client;

        $appkey = config('services.simplifi.app_key');
        $usrkey = config('services.simplifi.usr_key');
        $apiURL = config('services.simplifi.endpoint');

        $_module = "";
        $result = "";
        if (trim($module) != "") {
            $_module = "/" . trim($module);
        }

        if ($module != 'keywords/download') {

            $options = [
                'headers' => [
                    'X-App-Key' => $appkey,        
                    'X-User-Key' => $usrkey,
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    
                ]
            ]; 

            $response = $http->get($apiURL . 'organizations/' . $organizationID . '/campaigns/' . $campaignID . $_module,$options);
            if ($module == 'geo_targets') {
                $tmp = json_decode($response->getBody());
                for($i=0;$i<count($tmp->geo_targets);$i++) {
                    $statelist = State::select('state','state_code','sifi_state_id')
                                ->where('sifi_state_id','=',$tmp->geo_targets[$i]->id)
                                ->orderBy('state')
                                ->get();
                    if (count($statelist) > 0) {
                        $tmp->geo_targets[$i]->name = $statelist[0]['state_code'];
                    }
                }
                $result = $tmp;
            }else{
                $result =  json_decode($response->getBody());
            }
        }else if ($module == 'keywords/download') {
            $options = [
                'headers' => [
                    'X-App-Key' => $appkey,        
                    'X-User-Key' => $usrkey,
                    'Content-Type' => 'application/json',
                    'accept' => '*/*',
                ],
                'json' => [
                    
                ]
            ]; 

            $response = $http->get($apiURL . 'organizations/' . $organizationID . '/campaigns/' . $campaignID . $_module,$options);

            $result = explode(PHP_EOL,$response->getBody());


        }
        
        return $result;
    }

    private function getDefaultBudgetPlan($campaignID) {
        $http = new \GuzzleHttp\Client;

        $appkey = config('services.simplifi.app_key');
        $usrkey = config('services.simplifi.usr_key');
        $apiURL = config('services.simplifi.endpoint');
        
        $options = [
            'headers' => [
                'X-App-Key' => $appkey,        
                'X-User-Key' => $usrkey,
                'Content-Type' => 'application/json',
            ],
        ]; 

        $response = $http->get($apiURL . 'campaigns/' . $campaignID . '/budget_plans',$options);
        return  json_decode($response->getBody());


    }

    private function updateDefaultBudgetPlan($organizationID,$campaignID,$startDate='',$endDate='',$totalbudget='',$budget_planID='') {
        date_default_timezone_set('America/Chicago');
        
        $http = new \GuzzleHttp\Client;

        $appkey = config('services.simplifi.app_key');
        $usrkey = config('services.simplifi.usr_key');
        $apiURL = config('services.simplifi.endpoint');

        $budgetPlanID = $budget_planID;

        if ($budget_planID == "") {
            /** FIND BUDGET PLANS */
            $options = [
                'headers' => [
                    'X-App-Key' => $appkey,        
                    'X-User-Key' => $usrkey,
                    'Content-Type' => 'application/json',
                ],
            ]; 

            $response = $http->get($apiURL . 'campaigns/' . $campaignID . '/budget_plans',$options);
            $result =  json_decode($response->getBody());

            $count = count($result->budget_plans) - 1;

            $startdateupdate = true;

            /** CHECK IF UPDATE OR CREATE NEW ONE */
            $budget_startdate = $result->budget_plans[$count]->start_date;
            $budget_enddate = $result->budget_plans[$count]->end_date;

            if (date('Ymd',strtotime($budget_enddate)) < date('Ymd')) { /** CHECK IF LAST BUDGET PLAN ALREADY EXPIRED */
                $budgetPlanID = "";
                if (date('Ymd',strtotime($startDate)) < date('Ymd')) {
                    $startDate = date('Y-m-d');
                }

                if (date('Ymd',strtotime($endDate)) < date('Ymd')) {
                    return response()->json(array('result'=>'failed','message'=>'Campaign end date must be on or after today date.'));
                    exit;die();
                }else if (date('Ymd',strtotime($endDate)) < date('Ymd',strtotime($startDate))) {
                    return response()->json(array('result'=>'failed','message'=>'Campaign end date must be on or after start date'));
                    exit;die();
                }
            }else{
                $budgetPlanID = $result->budget_plans[$count]->id;
                $startDate = date('Y-m-d');

                if (date('Ymd',strtotime($endDate)) < date('Ymd')) {
                    return response()->json(array('result'=>'failed','message'=>'Campaign end date must be on or after today date.'));
                    exit;die();
                }else if (date('Ymd',strtotime($endDate)) < date('Ymd',strtotime($startDate))) {
                    return response()->json(array('result'=>'failed','message'=>'Campaign end date must be on or after today date'));
                    exit;die();
                }

                if (date('Ymd',strtotime($budget_startdate)) < date('Ymd')) {
                    $options = [
                        'headers' => [
                            'X-App-Key' => $appkey,        
                            'X-User-Key' => $usrkey,
                            'Content-Type' => 'application/json',
                        ],
                        'json' => [
                           
                        ]
                    ]; 

                    $campaignStatus = $http->get($apiURL . 'organizations/' . $organizationID . '/campaigns/' . $campaignID ,$options);
                    $resultStatus =  json_decode($campaignStatus->getBody());

                    if($resultStatus->campaigns[0]->status == 'Paused' || $resultStatus->campaigns[0]->status == 'Active' || $resultStatus->campaigns[0]->status == 'Ended') {
                        $startdateupdate = false;
                        $startDate = $budget_startdate;
                    }

                }

            }
            /** CHECK IF UPDATE OR CREATE NEW ONE */
        }

        /** SET BUDGET PLAN */
        $tmp = strtotime($endDate) - strtotime($startDate);
        $diffdays = abs(round($tmp / 86400));
        $totalbudget = number_format(6 * $diffdays,1,".","");
        /** SET BUDGET PLAN */

        $options = [
            'headers' => [
                'X-App-Key' => $appkey,        
                'X-User-Key' => $usrkey,
                'Content-Type' => 'application/json',
            ],
            'json' => [
                "end_date" => $endDate,
            ]
        ]; 

        if ($startDate != '' && $startdateupdate == true) {
            $options = [
                'headers' => [
                    'X-App-Key' => $appkey,        
                    'X-User-Key' => $usrkey,
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    "start_date" => $startDate,
                    "end_date" => $endDate,
                    "total_budget" => $totalbudget,
                    "adjusted_budget" => $totalbudget,
                    'spent_budget' => 0.0,
                ]
            ]; 
        }else{
            $options = [
                'headers' => [
                    'X-App-Key' => $appkey,        
                    'X-User-Key' => $usrkey,
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    "end_date" => $endDate,
                    //"total_budget" => $totalbudget,
                    //"adjusted_budget" => $totalbudget,
                    //'spent_budget' => 0.0,
                ]
            ]; 
        }

        if ($budgetPlanID == "") {
            $response = $http->post($apiURL . 'campaigns/' . $campaignID . '/budget_plans' ,$options);
        }else{
            $response = $http->put($apiURL . 'budget_plans/' . $budgetPlanID ,$options);
        }

        /** UPDATE DAILY BUDGET ON CAMPAIGN */
        // $options = [
        //     'headers' => [
        //         'X-App-Key' => $appkey,        
        //         'X-User-Key' => $usrkey,
        //         'Content-Type' => 'application/json',
        //     ],
        //     'json' => [
        //         "campaign" => [
        //             "daily_budget" => 6.0,
        //             "bid" => 6.0,
        //             "ad_placement_id" => "3"
        //         ]
        //     ]
        // ]; 

        // $responseOrganization = $http->put($apiURL . 'organizations/' . $organizationID . '/campaigns/' . $campaignID ,$options);
        /** UPDATE DAILY BUDGET ON CAMPAIGN */

        $result =  json_decode($response->getBody());

    }

    private function updateGeoTargetsPostalCode($organizationID,$campaignID,$geotargets = '',$postalcodes = '',$nationaltarget = false) {
        $http = new \GuzzleHttp\Client;

        $appkey = config('services.simplifi.app_key');
        $usrkey = config('services.simplifi.usr_key');
        $apiURL = config('services.simplifi.endpoint');


        /** CREATE CAMPAIGN FIRST */
        $options = [
            'headers' => [
                'X-App-Key' => $appkey,        
                'X-User-Key' => $usrkey,
                'Content-Type' => 'application/json',
            ],
            'json' => [
                "campaign" => [
                    "geo_target_ids" => $geotargets,
                    "postal_codes" => [
                        "values" => $postalcodes
                    ]
                ]
            ]
        ]; 

        if ($nationaltarget == true) {
            $options = [
                'headers' => [
                    'X-App-Key' => $appkey,        
                    'X-User-Key' => $usrkey,
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    "campaign" => [
                        "geo_target_ids" => $geotargets,
                    ]
                ]
            ]; 
        }

        $response = $http->put($apiURL . 'organizations/' . $organizationID . '/campaigns/' . $campaignID,$options);
        $result =  json_decode($response->getBody());


    }

    private function updateRecencyCampaign($organizationID,$campaignID,$recencyID = "2") {
        $http = new \GuzzleHttp\Client;

        $appkey = config('services.simplifi.app_key');
        $usrkey = config('services.simplifi.usr_key');
        $apiURL = config('services.simplifi.endpoint');

        /** CREATE CAMPAIGN FIRST */
        $options = [
            'headers' => [
                'X-App-Key' => $appkey,        
                'X-User-Key' => $usrkey,
                'Content-Type' => 'application/json',
            ],
            'json' => [
                "campaign" => [
                    "recency_id" => $recencyID,
                ]
            ]
        ]; 

        $response = $http->put($apiURL. 'organizations/' . $organizationID . '/campaigns/' . $campaignID,$options);
        $result =  json_decode($response->getBody());

    }

    private function createCampaign($organizationID,$campaignName,$geotargets,$postalcodes,$startdatecampaign='',$enddatecampaign='') {
        $http = new \GuzzleHttp\Client;

        $appkey = config('services.simplifi.app_key');
        $usrkey = config('services.simplifi.usr_key');
        $apiURL = config('services.simplifi.endpoint');

        /** OVERWRITE START DATE CAMPAIGN FOR SYNC TO SIFI */
        date_default_timezone_set('America/New_York');
        /** OVERWRITE START DATE CAMPAIGN FOR SYNC TO SIFI */

        /** CREATE CAMPAIGN FIRST */
        $options = [
            'headers' => [
                'X-App-Key' => $appkey,        
                'X-User-Key' => $usrkey,
                'Content-Type' => 'application/json',
            ],
            'json' => [
                
            ]
        ]; 

        $response = $http->post($apiURL . 'organizations/' . $organizationID . '/campaigns',$options);
        $result =  json_decode($response->getBody());
        
        $newCampaignID = $result->campaigns[0]->id;
        
        $campaignName = str_replace(",","",$campaignName);
        $campaignName = $this->makeSafeTitleName($campaignName);
        //$newCampaignID = "3062934";
        /** CREATE CAMPAIGN FIRST */

        /** UPDATE CAMPAIGN */
        //$datenow = date("Y-m-d");
        //$end_date = date("Y-m-d", strtotime("+1 month",strtotime($datenow)));
        //$start_date = $startdatecampaign;
        $start_date = date('Y-m-d 00:00:00');
        $end_date = $enddatecampaign;

        $tmp = strtotime($end_date) - strtotime($start_date);
        $diffdays = abs(round($tmp / 86400));
        $totalbudget = number_format(6 * $diffdays,1,".","");

        $options = [
            'headers' => [
                'X-App-Key' => $appkey,        
                'X-User-Key' => $usrkey,
                'Content-Type' => 'application/json',
            ],
            'json' => [
                "campaign" => [
                    "name" => $campaignName,
                    "daily_budget" => 6.0,
                    "media_type_id" => 1,
                    "auto_adjust_daily_budget" =>false,
		            "total_budget" => $totalbudget,
                    "bid" => 6.0,
                    "start_date" => $start_date,
                    "end_date" => $end_date,
                    "bid_type_id" => "1",
                    "campaign_type_id" => "1",
                    "ad_placement_id" => "3",
                    "recency_id" => "2",
                    "frequency_capping" => [
                        "how_many_times" => 1,
                        "hours" => 24,
                    ],
                    "geo_target_ids" => $geotargets,
                    "postal_codes" => [
                        "values" => $postalcodes
                    ]

                ]
            ]
        ]; 

        //return $options;
        //exit;die();
        $response = $http->put($apiURL. 'organizations/' . $organizationID . '/campaigns/' . $newCampaignID,$options);
        $result =  json_decode($response->getBody());

        return $newCampaignID;
        /** UPDATE CAMPAIGN */
    }

    private function createOrganization($organizationName,$parentOrganization = "",$customID="") {
        $http = new \GuzzleHttp\Client;

        $appkey = config('services.simplifi.app_key');
        $usrkey = config('services.simplifi.usr_key');
        $apiURL = config('services.simplifi.endpoint') . "organizations";
        
        $parentID = (trim($parentOrganization) == "")?config('services.sifidefaultorganization.organizationid'):trim($parentOrganization);
        $sifiEMMStatus = '';

        $organizationName = $this->makeSafeTitleName($organizationName);
        
        if (!str_contains($organizationName,'[CLIENT') && !str_contains($organizationName,'[AGENCY')) {
            $sifiEMMStatus = "[EMM]";
            if (config('services.appconf.devmode') === true) {
                $sifiEMMStatus = "[EMM BETA]";
            }
        }

        try {
            $options = [
                'headers' => [
                    'X-App-Key' => $appkey,        
                    'X-User-Key' => $usrkey,
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    "organization" =>[
                        "name" => $organizationName . ' - ' . date('His'),
                        "parent_id" => $parentID,
                        "custom_id" => $customID
                    ]
                ]
            ]; 
            
           
            $response = $http->post($apiURL,$options);
            $result =  json_decode($response->getBody());
            
            return $result->organizations[0]->id;

        }catch (\GuzzleHttp\Exception\BadResponseException $e) {
            // if ($e->getCode() === 400) {
            //     return "";
            // } else if ($e->getCode() === 401) {
            //     return "";
            // }
            $details = [
                'errormsg'  => 'Error when trying to create SIFI Organization : ' . $organizationName . ' parent ID :' . $parentID . ' (' . $e->getMessage() . ')',
            ];

            $from = [
                'address' => 'noreply@exactmatchmarketing.com',
                'name' => 'Support',
                'replyto' => 'support@exactmatchmarketing.com',
            ];
            $this->send_email(array('serverlogs@sitesettingsapi.com'),$from,'Error Log SIFI Create Organization :' . $organizationName . ' parent ID:' . $parentID . '(Apps DATA - createOrganization - LeadspeekCont - L4075) ',$details,array(),'emails.tryseramatcherrorlog','');


            return "";
        }

    }

    public function getcampaignresource(Request $request) {
        $organizationID = (isset($request->organizatonID))?$request->organizatonID:'';
        $campaignID =  (isset($request->campaignID))?$request->campaignID:'';
       
        $ziplist = [];
        $geotargets = [];
        $keywords = [];

        /** GET ZIP CODE */
            $ziplist = $this->getCampaignSifi($organizationID,$campaignID,'postal_codes');
            $ziplist = $ziplist->postal_codes;
        /** GET ZIP CODE */

        /** GET GEO TARGETS */
            $geotargets = $this->getCampaignSifi($organizationID,$campaignID,'geo_targets');
            $geotargets = $geotargets->geo_targets;
        /** GET GEO TARGETS */

        /** GET KEYWORDS */
            $keywords = $this->getCampaignSifi($organizationID,$campaignID,'keywords/download');
        /** GET KEYWORDS */

        return response()->json(array("result"=>'success','ziplist'=>$ziplist,'geotargets'=>$geotargets,'keywords'=>$keywords));
    }
    /** SIMPLI.FI CREATE CAMPAIGN FUNCTIONS */

    public function createclient(Request $request) {
        $_adminNotifyTo = "";
        foreach($request->adminNotifyTo as $item) {
            $_adminNotifyTo .= $item . ',';
        }

        $_adminNotifyTo = rtrim($_adminNotifyTo,',');
        $idSys = (isset($request->idSys))?$request->idSys:'';
        $leadspeekType = (isset($request->leadspeekType))?$request->leadspeekType:'';

        $companyGroupID = (isset($request->companyGroupID))?$request->companyGroupID:'0';

        $campaignName = (isset($request->campaignName))?$request->campaignName:'';
        $urlCode = (isset($request->urlCode))?strtolower($request->urlCode):'';
        $urlCodeThankyou = (isset($request->urlCodeThankyou))?strtolower($request->urlCodeThankyou):'';

        if ($companyGroupID == '') {
            $companyGroupID = '0';
        }

        $clientOrganizationID = (isset($request->clientOrganizationID))?trim($request->clientOrganizationID):'';
        $clientCampaignID = (isset($request->clientCampaignID))?$request->clientCampaignID:'';
        $clientHidePhone = (isset($request->clientHidePhone))?$request->clientHidePhone:'';
        $answers = (isset($request->answers))?$request->answers:'';
        $gtminstalled = (isset($request->gtminstalled))?$request->gtminstalled:'F';

        $phoneenabled = (isset($request->phoneenabled) && $request->phoneenabled)?'T':'F';
        $homeaddressenabled = (isset($request->homeaddressenabled) && $request->homeaddressenabled)?'T':'F';
        $requireemailaddress = (isset($request->requireemailaddress) && $request->requireemailaddress)?'T':'F';
        $reidentificationtype = (isset($request->reidentificationtype))?$request->reidentificationtype:'never';
        $locationtarget = (isset($request->locationtarget))?$request->locationtarget:'Focus';
        $timezone = (isset($request->timezone))?$request->timezone:'America/Chicago';
        
        $applyreidentificationall = (isset($request->applyreidentificationall) && $request->applyreidentificationall)?'T':'F';

        $enableMinimumLimitLeads = (isset($request->enableMinimumLimitLeads) && $request->enableMinimumLimitLeads)?'T':'F';
        $minimumLimitLeads = (isset($request->minimumLimitLeads))?$request->minimumLimitLeads:1;

        $_campaignName = '';
        if (trim($campaignName) != '') {
            $_campaignName = ' - ' . trim($campaignName);
        }

        $_embeddedcode_crawl = 'T';
        $_embedded_status = 'We are still building the magic!';
        $_cost_perlead = '0';
        $_platformfee = '0';
        $_lp_max_lead_month = '0';
        $_lp_min_cost_month = '0';
        $_lp_limit_leads = '0';
        $leadspeek_locator_require = "";
        $campaign_keywords = "";
        $campaign_keywords_contextual = "";
        $companyParentName = "";
        $clientOwnerEmail = "";

        $inOpenApi = (isset($request->inOpenApi))?$request->inOpenApi:"";

        /** GET COMPANY AGENCY / PARENT NAME */
        if (isset($request->companyID) && $request->companyID != "") {
            $getCompanyParent = Company::select('company_name')
                                            ->where('id','=',$request->companyID)
                                            ->get();
            if (count($getCompanyParent) > 0) {
                $companyParentName = $getCompanyParent[0]['company_name'] . '-';
            }
        }
        /** GET COMPANY AGENCY / PARENT NAME */

        /** FOR LOCATOR AND QUESTIONNAIRE */
       
        $defaultParentOrganization = config('services.sifidefaultorganization.organizationid');

        $userlist = User::select('companies.company_name','companies.simplifi_organizationid','users.company_id','users.company_parent','users.name','users.email','users.phonenum','users.user_type','users.company_root_id')
                            ->join('companies','users.company_id','=','companies.id')
                            ->where('users.id','=',$request->userID)
                            ->where('users.active','=','T')
                            ->get();
        
        $companyUserID =  ($userlist[0]['company_id'] == null || $userlist[0]['company_id'] == '')?'':$userlist[0]['company_id'];
        
        $clientOwnerEmail = $userlist[0]['email'];

        if ($clientOrganizationID == null || $clientOrganizationID == "") {
            $clientOrganizationID =  ($userlist[0]['simplifi_organizationid'] == null || $userlist[0]['simplifi_organizationid'] == '')?'':trim($userlist[0]['simplifi_organizationid']);
        }

        $defaultPriceCompanyID = '';
        if ($userlist[0]['user_type'] == 'userdownline' || $userlist[0]['user_type'] == 'user') {
            $defaultPriceCompanyID = $userlist[0]['company_id'];
        }else if ($userlist[0]['user_type'] == 'client') {
            $defaultPriceCompanyID = $userlist[0]['company_parent'];
        }

        $clientdefaultprice = false;
        $masterCostAgency = CompanySetting::where('company_id',$companyUserID)->whereEncrypted('setting_name','clientdefaultprice')->get();
        if (count($masterCostAgency) > 0) {
            $masterCostAgency = json_decode($masterCostAgency[0]['setting_value']);
            $clientdefaultprice = true;
        }else{
            /** CHECK IF CLIENT HAVE THEIR OWN DEFAULT PRICE SETUP */
            $masterCostClient = CompanySetting::where('company_id',$defaultPriceCompanyID)->whereEncrypted('setting_name','agencydefaultprice')->get();
            if (count($masterCostClient) > 0) {
                $masterCostAgency = json_decode($masterCostClient[0]['setting_value']);    
            }else{
                $masterCostAgency = '';
            }
            /** CHECK IF CLIENT HAVE THEIR OWN DEFAULT PRICE SETUP */
            
        }
        
        /** CHECK DEFAULT PAYMENT TERM */
        $paymenttermDefault = "Weekly";
        $getRootSetting = $this->getcompanysetting($userlist[0]['company_root_id'],'rootsetting');
        if ($getRootSetting != '') {
            if (isset($getRootSetting->defaultpaymentterm) && $getRootSetting->defaultpaymentterm != '') {
                $paymenttermDefault = trim($getRootSetting->defaultpaymentterm);
            }
        }
        $defpaymentterm = Company::select('paymentterm_default')->where('id','=',$companyUserID)->get();
        if (count($defpaymentterm) > 0 && $clientdefaultprice === true) {
            $paymenttermDefault = $defpaymentterm[0]['paymentterm_default'];
        }else{
            $defpaymentterm = Company::select('paymentterm_default')->where('id','=',$defaultPriceCompanyID)->get();
            if (count($defpaymentterm) > 0) {
                $paymenttermDefault = $defpaymentterm[0]['paymentterm_default'];
            }
        }
        /** CHECK DEFAULT PAYMENT TERM */
        
        /** CHECK PERMISSION AGENCY PAYMENTTERM */
        $agencypaymentterm = $this->getcompanysetting($defaultPriceCompanyID, 'agencypaymentterm');
        if($agencypaymentterm != '' && isset($agencypaymentterm->SelectedPaymentTerm) && is_array($agencypaymentterm->SelectedPaymentTerm)) 
        {
            $agencySelectPaymentTerm = $agencypaymentterm->SelectedPaymentTerm;
            $filterAgencySelectPaymentTerm = array_filter($agencySelectPaymentTerm, function ($item) use ($paymenttermDefault) {
                return $item->term == $paymenttermDefault;
            });
            $filterAgencySelectPaymentTerm = array_values($filterAgencySelectPaymentTerm);

            $status_term = isset($filterAgencySelectPaymentTerm[0]->status)?$filterAgencySelectPaymentTerm[0]->status:false;
            if($status_term == false) 
            {
                foreach($agencySelectPaymentTerm as $item) 
                {
                    if($item->status == true) 
                    {
                        $paymenttermDefault = $item->term;
                        break;
                    }
                }
            }
        }
        /** CHECK PERMISSION AGENCY PAYMENTTERM */
        
        if ((strtolower($leadspeekType) == 'locator' || strtolower($leadspeekType) == 'enhance' || strtolower($leadspeekType) == 'b2b') && $answers != "") {
            if (strtolower($leadspeekType) == 'locator') {
                if (isset($answers['asec5_4']) && count($answers['asec5_4']) > 0 && $answers['asec5_4_0_1'] === true) {// state
                    $_tmp = $answers['asec5_4'];
                    $answers['asec5_4'] = '';
                    $answers['asec5_4_simplifi'] = '';
                    foreach($_tmp as $tmp) {
                        $ex = explode('|',$tmp);
                        $answers['asec5_4'] .= $ex[1] . ',';
                        $answers['asec5_4_simplifi'] .= $ex[0] . ',';
                    }
                    $answers['asec5_4'] = rtrim($answers['asec5_4'],",");
                    $answers['asec5_4_simplifi'] = rtrim($answers['asec5_4_simplifi'],",");
                }else{
                    $answers['asec5_4'] = "";
                    $answers['asec5_4_simplifi'] = "";
                }
            }elseif (strtolower($leadspeekType) == 'enhance' || strtolower($leadspeekType) == 'b2b') {
                if (isset($answers['asec5_4']) && count($answers['asec5_4']) > 0 && $answers['asec5_4_0_1'] === true) {// state
                    $answers['asec5_4'] = rtrim(implode(",",$answers['asec5_4']),",");
                }else{
                    $answers['asec5_4'] = "";
                }
            }

            if (isset($answers['asec5_4_1']) && count($answers['asec5_4_1']) > 0) {
                $answers['asec5_4_1'] = implode(",",$answers['asec5_4_1']);
            }else{
                $answers['asec5_4_1'] = "";
            }
            info(['asec5_4_2 ke 1'=> $answers['asec5_4_2']]);

            if (strtolower($leadspeekType) == 'locator') {
                if (isset($answers['asec5_4_2']) && count($answers['asec5_4_2']) > 0 && $answers['asec5_4_0_2'] === true) {//city
                    $_tmp = $answers['asec5_4_2'];
                    $answers['asec5_4_2'] = '';
                    $answers['asec5_4_2_simplifi'] = '';
                    foreach($_tmp as $tmp) {
                        $ex = explode('|',$tmp);
                        $answers['asec5_4_2'] .= $ex[1] . ',';
                        $answers['asec5_4_2_simplifi'] .= $ex[0] . ',';
                    }
                    $answers['asec5_4_2'] = rtrim($answers['asec5_4_2'],",");
                    $answers['asec5_4_2_simplifi'] = rtrim($answers['asec5_4_2_simplifi'],",");
                }else{
                    $answers['asec5_4_2'] = "";
                    $answers['asec5_4_2_simplifi'] = "";
                }
            }elseif (strtolower($leadspeekType) == 'enhance' || strtolower($leadspeekType) == 'b2b') {
                if (isset($answers['asec5_4_2']) && count($answers['asec5_4_2']) > 0 && $answers['asec5_4_0_2'] === true) {//city
                    $answers['asec5_4_2'] = rtrim(implode(",",$answers['asec5_4_2']),",");
                }else{
                    $answers['asec5_4_2'] = "";
                }
            }

            if (isset($answers['asec5_10']) && count($answers['asec5_10']) > 0) {
                $campaign_keywords = $answers['asec5_10'];
                $answers['asec5_10'] = implode(",",$answers['asec5_10']);
            }else{
                $answers['asec5_10'] = "";
            }

            if (isset($answers['asec5_10_1']) && count($answers['asec5_10_1']) > 0) {
                $campaign_keywords_contextual = $answers['asec5_10_1'];
                $answers['asec5_10_1'] = implode(",",$answers['asec5_10_1']);
            }else{
                $answers['asec5_10_1'] = "";
            }
    
            if (strtolower($leadspeekType) == 'locator') {
                if (isset($answers['asec5_3']) && trim($answers['asec5_3']) != '' && $answers['asec5_4_0_3'] === true) {//zip code
                    $answers['asec5_3'] = explode(PHP_EOL,$answers['asec5_3']);
                    $answers['asec5_3'] = implode(",",$answers['asec5_3']);
                }else{
                    $answers['asec5_3'] = "";
                }
            }elseif (strtolower($leadspeekType) == 'enhance' || strtolower($leadspeekType) == 'b2b') {
                if (isset($answers['asec5_3']) && trim($answers['asec5_3']) != '' && $answers['asec5_4_0_3'] === true) {//zip code
                    $answers['asec5_3'] = str_replace([PHP_EOL, "\r\n", "\n", "\r"], ',', $answers['asec5_3']);
                }else{
                    $answers['asec5_3'] = "";
                }
            }

            $leadspeek_locator_require = (trim($answers['asec6_5']) == 'none')?'':trim($answers['asec6_5']);

            /** START SET CAMPAIGN */
            
            $leadspeek_locator_zip = trim($answers['asec5_3']);
            $leadspeek_locator_state = trim($answers['asec5_4']);
            if (strtolower($leadspeekType) == 'locator') {
                $leadspeek_locator_state_simplifi = trim($answers['asec5_4_simplifi']);
            }
            $leadspeek_locator_city = trim($answers['asec5_4_2']);
            if (strtolower($leadspeekType) == 'locator') {
            $leadspeek_locator_city_simplifi = trim($answers['asec5_4_2_simplifi']);
            }
            $leadspeek_locator_country = trim($answers['asec5_4_1']);
            $leadspeek_locator_keyword = trim($answers['asec5_10']);
            $leadspeek_locator_keyword_contextual = (isset($answers['asec5_10_1']))?trim($answers['asec5_10_1']):"";
            $_lp_limit_leads = trim($answers['asec5_6']);

            if(strtolower($leadspeekType) == 'locator') {
                /* GET AGENCY DEFAULTPRICE */
                $agencydefaultprice = $this->getcompanysetting($defaultPriceCompanyID, 'agencydefaultprice');
                /* GET AGENCY DEFAULTPRICE */

                /* GET ROOT AGENCY DEFAULTPRICE */
                $rootagencydefaultprice = $this->getcompanysetting($idSys, 'rootagencydefaultprice');
                /* GET ROOT AGENCY DEFAULTPRICE */

                if($paymenttermDefault == "Weekly") {
                    $_cost_perlead = (isset($masterCostAgency->locator->Weekly->LocatorCostperlead)) ? ($masterCostAgency->locator->Weekly->LocatorCostperlead) : (isset($agencydefaultprice->locator->Weekly->LocatorCostperlead) ? ($agencydefaultprice->locator->Weekly->LocatorCostperlead) : ($rootagencydefaultprice->locator->Weekly->LocatorCostperlead));
                    $_platformfee = (isset($masterCostAgency->locator->Weekly->LocatorPlatformFee)) ? ($masterCostAgency->locator->Weekly->LocatorPlatformFee) : (isset($agencydefaultprice->locator->Weekly->LocatorPlatformFee) ? ($agencydefaultprice->locator->Weekly->LocatorPlatformFee) : ($rootagencydefaultprice->locator->Weekly->LocatorPlatformFee));
                    $_lp_min_cost_month = (isset($masterCostAgency->locator->Weekly->LocatorMinCostMonth)) ? ($masterCostAgency->locator->Weekly->LocatorMinCostMonth) : (isset($agencydefaultprice->locator->Weekly->LocatorMinCostMonth) ? ($agencydefaultprice->locator->Weekly->LocatorMinCostMonth) : ($rootagencydefaultprice->locator->Weekly->LocatorMinCostMonth));
                    //$_lp_limit_leads = (isset($masterCostAgency->locator->Weekly->LocatorLeadsPerday))?$masterCostAgency->locator->Weekly->LocatorLeadsPerday:'10';
                }else if ($paymenttermDefault == "Monthly") {
                    $_cost_perlead = (isset($masterCostAgency->locator->Monthly->LocatorCostperlead)) ? ($masterCostAgency->locator->Monthly->LocatorCostperlead) : (isset($agencydefaultprice->locator->Monthly->LocatorCostperlead) ? ($agencydefaultprice->locator->Monthly->LocatorCostperlead) : ($rootagencydefaultprice->locator->Monthly->LocatorCostperlead));
                    $_platformfee = (isset($masterCostAgency->locator->Monthly->LocatorPlatformFee)) ? ($masterCostAgency->locator->Monthly->LocatorPlatformFee) : (isset($agencydefaultprice->locator->Monthly->LocatorPlatformFee) ? ($agencydefaultprice->locator->Monthly->LocatorPlatformFee) : ($rootagencydefaultprice->locator->Monthly->LocatorPlatformFee));
                    $_lp_min_cost_month = (isset($masterCostAgency->locator->Monthly->LocatorMinCostMonth)) ? ($masterCostAgency->locator->Monthly->LocatorMinCostMonth) : (isset($agencydefaultprice->locator->Monthly->LocatorMinCostMonth) ? ($agencydefaultprice->locator->Monthly->LocatorMinCostMonth) : ($rootagencydefaultprice->locator->Monthly->LocatorMinCostMonth));
                    //$_lp_limit_leads = (isset($masterCostAgency->locator->Monthly->LocatorLeadsPerday))?$masterCostAgency->locator->Monthly->LocatorLeadsPerday:'10';
                }else if ($paymenttermDefault == "One Time") {
                    $_cost_perlead = (isset($masterCostAgency->locator->OneTime->LocatorCostperlead)) ? ($masterCostAgency->locator->OneTime->LocatorCostperlead) : (isset($agencydefaultprice->locator->OneTime->LocatorCostperlead) ? ($agencydefaultprice->locator->OneTime->LocatorCostperlead) : ($rootagencydefaultprice->locator->OneTime->LocatorCostperlead));
                    $_platformfee = (isset($masterCostAgency->locator->OneTime->LocatorPlatformFee)) ? ($masterCostAgency->locator->OneTime->LocatorPlatformFee) : (isset($agencydefaultprice->locator->OneTime->LocatorPlatformFee) ? ($agencydefaultprice->locator->OneTime->LocatorPlatformFee) : ($rootagencydefaultprice->locator->OneTime->LocatorPlatformFee));
                    $_lp_min_cost_month = (isset($masterCostAgency->locator->OneTime->LocatorMinCostMonth)) ? ($masterCostAgency->locator->OneTime->LocatorMinCostMonth) : (isset($agencydefaultprice->locator->OneTime->LocatorMinCostMonth) ? ($agencydefaultprice->locator->OneTime->LocatorMinCostMonth) : ($rootagencydefaultprice->locator->OneTime->LocatorMinCostMonth));
                    //$_lp_limit_leads = (isset($masterCostAgency->locator->OneTime->LocatorLeadsPerday))?$masterCostAgency->locator->OneTime->LocatorLeadsPerday:'10';
                }else if ($paymenttermDefault == "Prepaid") {
                    $_cost_perlead = (isset($masterCostAgency->locator->Prepaid->LocatorCostperlead)) ? ($masterCostAgency->locator->Prepaid->LocatorCostperlead) : (isset($agencydefaultprice->locator->Prepaid->LocatorCostperlead) ? ($agencydefaultprice->locator->Prepaid->LocatorCostperlead) : ($rootagencydefaultprice->locator->Prepaid->LocatorCostperlead));
                    $_platformfee = (isset($masterCostAgency->locator->Prepaid->LocatorPlatformFee)) ? ($masterCostAgency->locator->Prepaid->LocatorPlatformFee) : (isset($agencydefaultprice->locator->Prepaid->LocatorPlatformFee) ? ($agencydefaultprice->locator->Prepaid->LocatorPlatformFee) : ($rootagencydefaultprice->locator->Prepaid->LocatorPlatformFee));
                    $_lp_min_cost_month = (isset($masterCostAgency->locator->Prepaid->LocatorMinCostMonth)) ? ($masterCostAgency->locator->Prepaid->LocatorMinCostMonth) : (isset($agencydefaultprice->locator->Prepaid->LocatorMinCostMonth) ? ($agencydefaultprice->locator->Prepaid->LocatorMinCostMonth) : ($rootagencydefaultprice->locator->Prepaid->LocatorMinCostMonth));
                    //$_lp_limit_leads = (isset($masterCostAgency->locator->Prepaid->LocatorLeadsPerday))?$masterCostAgency->locator->Prepaid->LocatorLeadsPerday:'10';
                }
            }

            // if (strtolower($leadspeekType) == 'locator' && $clientdefaultprice == false) {
            //     /** Cost per leads */
            //     $_cost_perlead = '1.50';
            //     if (trim($leadspeek_locator_require) == 'FirstName,LastName') {
            //         $_cost_perlead = (isset($masterCostAgency->locatorlead->FirstName_LastName))?$masterCostAgency->locatorlead->FirstName_LastName:'1.50';
            //     }else if (trim($leadspeek_locator_require) == 'FirstName,LastName,MailingAddress') {
            //         $_cost_perlead = (isset($masterCostAgency->locatorlead->FirstName_LastName_MailingAddress))?$masterCostAgency->locatorlead->FirstName_LastName_MailingAddress:'2';
            //     }else if (trim($leadspeek_locator_require) == 'FirstName,LastName,MailingAddress,Phone') {
            //         $_cost_perlead = (isset($masterCostAgency->locatorlead->FirstName_LastName_MailingAddress_Phone))?$masterCostAgency->locatorlead->FirstName_LastName_MailingAddress_Phone:'3';
            //     }
            //     /** Cost per leads */
            // }
            /** START SET CAMPAIGN */
        }
        /** FOR LOCATOR AND QUESTIONNAIRE */

        /* FOR ENHANCE ADVANCE INFORMATION */
        $advanceInformation = "";
        $listAdvanceInformationId = isset($request->listAdvanceInformationId)?$request->listAdvanceInformationId:'';
        $campaign_information_type = isset($request->campaign_information_type)?$request->campaign_information_type:'basic';
        
        if(is_array($listAdvanceInformationId) && count($listAdvanceInformationId) > 0) {
            $advanceInformation = implode(',', $listAdvanceInformationId);
        }

        $valid_types = ['basic', 'advanced'];
        if (!in_array($campaign_information_type, $valid_types)) {
            return response()->json(['result' => 'failed', 'message' => 'Invalid campaign information type'], 400);
        }
        /* FOR ENHANCE ADVANCE INFORMATION */
        
        /** CREATE TRYSERA ID */
        $tryseraID = "";
        $tryseraCustomID = $this->_moduleID . '_' . $request->companyID . '00' . $request->userID . '_' . date('His');
        /*if (config('services.appconf.devmode') === false) {
            $tryseraID = $this->create_leadspeek_api_client($request->companyName . $_campaignName,$tryseraCustomID);
        }else{
            $tryseraID = rand(1000,9999);
        }*/
        $tryseraID = $this->generateLeadSpeekIDUniqueNumber();
        
        if (empty($tryseraID) || trim($tryseraID) == '') {
            return response()->json(array('result'=>'failed','message'=>'Sorry, system can not get the leadspeek ID, would you please try again?','data'=>array()));
            exit;die();
        }
        /** CREATE TRYSERA ID */

        /** TRY TO CREATE SIFI ORGANIZATION FIRST BEFORE CONTINUE OTHER ELSE */
        if ($clientOrganizationID == '' && strtolower($leadspeekType) == 'locator') {
            //if (false){
                /** CREATE ORGANIZATION */
                $sifiEMMStatus = "[CLIENT]";
                if (config('services.appconf.devmode') === true) {
                    $sifiEMMStatus = "[CLIENT BETA]";
                }

                if ($request->companyID != '') {
                    $companyParent = Company::select('simplifi_organizationid')
                                        ->where('id','=',$request->companyID)
                                        ->get();
                    if(count($companyParent) > 0) {
                        if ($companyParent[0]['simplifi_organizationid'] != '') {
                            $defaultParentOrganization = $companyParent[0]['simplifi_organizationid'];
                        }
                    }
                }

                $clientOrganizationID = $this->createOrganization(trim($request->companyName) . ' ' . $sifiEMMStatus,$defaultParentOrganization);
                
                if (trim($clientOrganizationID) == "") {
                    $clientOrganizationID = $this->createOrganization(trim($request->companyName) . ' ' . $sifiEMMStatus,$defaultParentOrganization);
                    /** LOG ACTION */
                    $ipAddress = ($request->header('Clientip') !== null)?$request->header('Clientip'):$request->ip();
                    $user_id = auth()->user()->id;
                    $target_userid = $request->userID <> $user_id ? $request->userID : $user_id;
                    $loguser = $this->logUserAction($user_id, 'Create SIFI ORGANIZATION ATTEMPT 2 - FAILED (L4565)', 'Organization ID : ' . $clientOrganizationID . ' | CampaignID :' . $tryseraID, $ipAddress, $target_userid);

                    // $loguser = $this->logUserAction($request->userID,'Create SIFI ORGANIZATION ATTEMPT 2 - FAILED (L4565)','Organization ID : ' . $clientOrganizationID . ' | CampaignID :' . $tryseraID,$ipAddress);
                    /** LOG ACTION */

                }

                if (trim($clientOrganizationID) == "") {
                    return response()->json(array('result'=>'failed','message'=>'Sorry, system can not create campaign, please make sure all data valid and try again later.','data'=>array()));
                    exit;die();
                }
                /** CREATE ORGANIZATION */
        }
        /** TRY TO CREATE SIFI ORGANIZAtION FIRST BEFORE CONTINUE OTHER ELSE */

        $globalviewmode = (isset($request->globalviewmode))?$request->globalviewmode:'';
        if(strtolower($leadspeekType) == 'local' && $answers != '' && $globalviewmode === true) {
            $includeExclude = isset($answers['includeExclude'])?$answers['includeExclude']:'0';
            if($includeExclude == '0') { // exclude
                $leadspeek_locator_state_type = 'exclude';
                $leadspeek_locator_state_exclude = (isset($answers['state']) && is_array($answers['state']) && count($answers['state']) > 0) ? implode(',',$answers['state']) : '';
            } else { // include
                $leadspeek_locator_state_type = 'include';
                $leadspeek_locator_state = (isset($answers['state']) && is_array($answers['state']) && count($answers['state']) > 0) ? implode(',',$answers['state']) : '';
            }
        }

        // return response()->json(['result' => 'success']);

        if (strtolower($leadspeekType) == 'enhance') {
            /* GET CLIENT MIN LEAD DAYS */
            $rootSetting = $this->getcompanysetting($idSys, 'rootsetting');
            $clientMinLeadDayEnhance = (isset($rootSetting->clientminleadday))?$rootSetting->clientminleadday:"";
            /* GET CLIENT MIN LEAD DAYS */

            /* GET AGENCY DEFAULTPRICE */
            $agencydefaultprice = $this->getcompanysetting($defaultPriceCompanyID, 'agencydefaultprice');
            /* GET AGENCY DEFAULTPRICE */

            /* GET ROOT COST AGENCY */
            // $rootcostagency = $this->getcompanysetting($idSys, 'rootcostagency');
            $rootagencydefaultprice = $this->getcompanysetting($idSys, 'rootagencydefaultprice');
            /* GET ROOT COST AGENCY */

            /* GET costagency */
            $costagency = $this->getcompanysetting($userlist[0]['company_parent'], 'costagency');
            /* GET costagency */

            // if($rootSetting->clientcapleadpercentage != '') {}

            if ($paymenttermDefault == "Weekly") {
                // if($rootSetting->clientcapleadpercentage != '') {
                //     $_cost_perlead = (isset($costagency->enhance->Weekly->EnhanceCostperlead)) ? $costagency->enhance->Weekly->EnhanceCostperlead : "0";
                // } else {
                    $_cost_perlead = (isset($masterCostAgency->enhance->Weekly->EnhanceCostperlead)) ? ($masterCostAgency->enhance->Weekly->EnhanceCostperlead) : (isset($agencydefaultprice->enhance->Weekly->EnhanceCostperlead) ? ($agencydefaultprice->enhance->Weekly->EnhanceCostperlead) : ($rootagencydefaultprice->enhance->Weekly->EnhanceCostperlead)); // pakai clientdefaultprice jika ada, pakai agencydefaultprice jika clientfdefaultpricetidak tidak ada, pakai rootagencydefaultprice jika agencydefaultprice tidak ada
                    // info([
                    //     'paymenttermDefault' => $paymenttermDefault,
                    //     '_cost_perlead' => $_cost_perlead,
                    //     'costagency_enhance_weekly_costperlead' => isset($costagency->enhance->Weekly->EnhanceCostperlead) ? $costagency->enhance->Weekly->EnhanceCostperlead : 'empty',
                    // ]);
                    if(isset($costagency->enhance->Weekly->EnhanceCostperlead) && ($costagency->enhance->Weekly->EnhanceCostperlead != '') && ($_cost_perlead > 0) && ($_cost_perlead < $costagency->enhance->Weekly->EnhanceCostperlead)) {
                        $_cost_perlead = $costagency->enhance->Weekly->EnhanceCostperlead;
                    }
                //}
                                 
                $_platformfee = (isset($masterCostAgency->enhance->Weekly->EnhancePlatformFee)) ? $masterCostAgency->enhance->Weekly->EnhancePlatformFee : (isset($agencydefaultprice->enhance->Weekly->EnhancePlatformFee) ? ($agencydefaultprice->enhance->Weekly->EnhancePlatformFee) : ($rootagencydefaultprice->enhance->Weekly->EnhancePlatformFee));
                $_lp_min_cost_month = (isset($masterCostAgency->enhance->Weekly->EnhanceMinCostMonth)) ? $masterCostAgency->enhance->Weekly->EnhanceMinCostMonth : (isset($agencydefaultprice->enhance->Weekly->EnhanceMinCostMonth) ? ($agencydefaultprice->enhance->Weekly->EnhanceMinCostMonth) : ($rootagencydefaultprice->enhance->Weekly->EnhanceMinCostMonth));

                // if($clientMinLeadDayEnhance != "") {
                //     $_lp_limit_leads = (isset($masterCostAgency->enhance->Weekly->EnhanceLeadsPerday)) ? (($masterCostAgency->enhance->Weekly->EnhanceLeadsPerday < $clientMinLeadDayEnhance) ? $clientMinLeadDayEnhance : $masterCostAgency->enhance->Weekly->EnhanceLeadsPerday) : $clientMinLeadDayEnhance;
                // } else {
                //     $_lp_limit_leads = (isset($masterCostAgency->enhance->Weekly->EnhanceLeadsPerday)) ? $masterCostAgency->enhance->Weekly->EnhanceLeadsPerday : "10";
                // }
            }else if ($paymenttermDefault == "Monthly") {
                // if($rootSetting->clientcapleadpercentage != '') {
                //     $_cost_perlead = (isset($costagency->enhance->Monthly->EnhanceCostperlead)) ? $costagency->enhance->Monthly->EnhanceCostperlead : "0";
                // } else {
                    $_cost_perlead = (isset($masterCostAgency->enhance->Monthly->EnhanceCostperlead)) ? ($masterCostAgency->enhance->Monthly->EnhanceCostperlead) : (isset($agencydefaultprice->enhance->Monthly->EnhanceCostperlead) ? ($agencydefaultprice->enhance->Monthly->EnhanceCostperlead) : ($rootagencydefaultprice->enhance->Monthly->EnhanceCostperlead)); // pakai clientdefaultprice jika ada, pakai agencydefaultprice jika clientfdefaultpricetidak tidak ada, pakai rootagencydefaultprice jika agencydefaultprice tidak ada
                    // info([
                    //     'paymenttermDefault' => $paymenttermDefault,
                    //     '_cost_perlead' => $_cost_perlead,
                    //     'costagency_enhance_monthly_costperlead' => isset($costagency->enhance->Monthly->EnhanceCostperlead) ? $costagency->enhance->Monthly->EnhanceCostperlead : 'empty',
                    // ]);
                    if(isset($costagency->enhance->Monthly->EnhanceCostperlead) && ($costagency->enhance->Monthly->EnhanceCostperlead != '') && ($_cost_perlead > 0) && ($_cost_perlead < $costagency->enhance->Monthly->EnhanceCostperlead)) {
                        $_cost_perlead = $costagency->enhance->Monthly->EnhanceCostperlead;
                    }
                //}

                $_platformfee = (isset($masterCostAgency->enhance->Monthly->EnhancePlatformFee)) ? $masterCostAgency->enhance->Monthly->EnhancePlatformFee : (isset($agencydefaultprice->enhance->Monthly->EnhancePlatformFee) ? ($agencydefaultprice->enhance->Monthly->EnhancePlatformFee) : ($rootagencydefaultprice->enhance->Monthly->EnhancePlatformFee));
                $_lp_min_cost_month = (isset($masterCostAgency->enhance->Monthly->EnhanceMinCostMonth)) ? $masterCostAgency->enhance->Monthly->EnhanceMinCostMonth : (isset($agencydefaultprice->enhance->Monthly->EnhanceMinCostMonth) ? ($agencydefaultprice->enhance->Monthly->EnhanceMinCostMonth) : ($rootagencydefaultprice->enhance->Monthly->EnhanceMinCostMonth));

                // if($clientMinLeadDayEnhance != "") {
                //     $_lp_limit_leads = (isset($masterCostAgency->enhance->Monthly->EnhanceLeadsPerday)) ? (($masterCostAgency->enhance->Monthly->EnhanceLeadsPerday < $clientMinLeadDayEnhance) ? $clientMinLeadDayEnhance : $masterCostAgency->enhance->Monthly->EnhanceLeadsPerday) : $clientMinLeadDayEnhance;
                // } else {
                //     $_lp_limit_leads = (isset($masterCostAgency->enhance->Monthly->EnhanceLeadsPerday)) ? $masterCostAgency->enhance->Monthly->EnhanceLeadsPerday : "10";
                // }
            }else if ($paymenttermDefault == "One Time") {
                // if($rootSetting->clientcapleadpercentage != '') {
                //     $_cost_perlead = (isset($costagency->enhance->OneTime->EnhanceCostperlead)) ? $costagency->enhance->OneTime->EnhanceCostperlead : "0";
                // } else {
                    $_cost_perlead = (isset($masterCostAgency->enhance->OneTime->EnhanceCostperlead)) ? ($masterCostAgency->enhance->OneTime->EnhanceCostperlead) : (isset($agencydefaultprice->enhance->OneTime->EnhanceCostperlead) ? ($agencydefaultprice->enhance->OneTime->EnhanceCostperlead) : ($rootagencydefaultprice->enhance->OneTime->EnhanceCostperlead)); // pakai clientdefaultprice jika ada, pakai agencydefaultprice jika clientfdefaultpricetidak tidak ada, pakai rootagencydefaultprice jika agencydefaultprice tidak ada
                    // info([
                    //     'paymenttermDefault' => $paymenttermDefault,
                    //     '_cost_perlead' => $_cost_perlead,
                    //     'costagency_enhance_onetime_costperlead' => isset($costagency->enhance->OneTime->EnhanceCostperlead) ? $costagency->enhance->OneTime->EnhanceCostperlead : 'empty',
                    // ]);
                    if(isset($costagency->enhance->OneTime->EnhanceCostperlead) && ($costagency->enhance->OneTime->EnhanceCostperlead) && ($_cost_perlead > 0) && ($_cost_perlead < $costagency->enhance->OneTime->EnhanceCostperlead)) {
                        $_cost_perlead = $costagency->enhance->OneTime->EnhanceCostperlead;
                    }
                //}

                $_platformfee = (isset($masterCostAgency->enhance->OneTime->EnhancePlatformFee)) ? $masterCostAgency->enhance->OneTime->EnhancePlatformFee : (isset($agencydefaultprice->enhance->OneTime->EnhancePlatformFee) ? ($agencydefaultprice->enhance->OneTime->EnhancePlatformFee) : ($rootagencydefaultprice->enhance->OneTime->EnhancePlatformFee));
                $_lp_min_cost_month = (isset($masterCostAgency->enhance->OneTime->EnhanceMinCostMonth)) ? $masterCostAgency->enhance->OneTime->EnhanceMinCostMonth : (isset($agencydefaultprice->enhance->OneTime->EnhanceMinCostMonth) ? ($agencydefaultprice->enhance->OneTime->EnhanceMinCostMonth) : ($rootagencydefaultprice->enhance->OneTime->EnhanceMinCostMonth));

                // if($clientMinLeadDayEnhance != "") {
                //     $_lp_limit_leads = (isset($masterCostAgency->enhance->OneTime->EnhanceLeadsPerday)) ? (($masterCostAgency->enhance->OneTime->EnhanceLeadsPerday < $clientMinLeadDayEnhance) ? $clientMinLeadDayEnhance : $masterCostAgency->enhance->OneTime->EnhanceLeadsPerday) : $clientMinLeadDayEnhance;
                // } else {
                //     $_lp_limit_leads = (isset($masterCostAgency->enhance->OneTime->EnhanceLeadsPerday)) ? $masterCostAgency->enhance->OneTime->EnhanceLeadsPerday : "10";
                // }
            }else if ($paymenttermDefault == "Prepaid") {
                // if($rootSetting->clientcapleadpercentage != '') {
                //     $_cost_perlead = (isset($costagency->enhance->Prepaid->EnhanceCostperlead)) ? $costagency->enhance->Prepaid->EnhanceCostperlead : "0";
                // } else {
                    $_cost_perlead = (isset($masterCostAgency->enhance->Prepaid->EnhanceCostperlead)) ? ($masterCostAgency->enhance->Prepaid->EnhanceCostperlead) : (isset($agencydefaultprice->enhance->Prepaid->EnhanceCostperlead) ? ($agencydefaultprice->enhance->Prepaid->EnhanceCostperlead) : ($rootagencydefaultprice->enhance->Prepaid->EnhanceCostperlead)); // pakai clientdefaultprice jika ada, pakai agencydefaultprice jika clientfdefaultpricetidak tidak ada, pakai rootagencydefaultprice jika agencydefaultprice tidak ada
                    // info([
                    //     'paymenttermDefault' => $paymenttermDefault,
                    //     '_cost_perlead' => $_cost_perlead,
                    //     'costagency_enhance_prepaid_costperlead' => isset($costagency->enhance->Prepaid->EnhanceCostperlead) ? $costagency->enhance->Prepaid->EnhanceCostperlead : 'empty',
                    // ]);
                    if(isset($costagency->enhance->Prepaid->EnhanceCostperlead) && ($costagency->enhance->Prepaid->EnhanceCostperlead != '') && ($_cost_perlead > 0) && ($_cost_perlead < $costagency->enhance->Prepaid->EnhanceCostperlead)) {
                        $_cost_perlead = $costagency->enhance->Prepaid->EnhanceCostperlead;
                    }
                //}

                $_platformfee = (isset($masterCostAgency->enhance->Prepaid->EnhancePlatformFee)) ? $masterCostAgency->enhance->Prepaid->EnhancePlatformFee : (isset($agencydefaultprice->enhance->Prepaid->EnhancePlatformFee) ? ($agencydefaultprice->enhance->Prepaid->EnhancePlatformFee) : ($rootagencydefaultprice->enhance->Prepaid->EnhancePlatformFee));
                $_lp_min_cost_month = (isset($masterCostAgency->enhance->Prepaid->EnhanceMinCostMonth)) ? $masterCostAgency->enhance->Prepaid->EnhanceMinCostMonth : (isset($agencydefaultprice->enhance->Prepaid->EnhanceMinCostMonth) ? ($agencydefaultprice->enhance->Prepaid->EnhanceMinCostMonth) : ($rootagencydefaultprice->enhance->Prepaid->EnhanceMinCostMonth));
                
                // if($clientMinLeadDayEnhance != "") {
                //     $_lp_limit_leads = (isset($masterCostAgency->enhance->Prepaid->EnhanceLeadsPerday)) ? (($masterCostAgency->enhance->Prepaid->EnhanceLeadsPerday < $clientMinLeadDayEnhance) ? $clientMinLeadDayEnhance : $masterCostAgency->enhance->Prepaid->EnhanceLeadsPerday) : $clientMinLeadDayEnhance;
                // } else {
                //     $_lp_limit_leads = (isset($masterCostAgency->enhance->Prepaid->EnhanceLeadsPerday)) ? $masterCostAgency->enhance->Prepaid->EnhanceLeadsPerday : "10";
                // }
            }
        }

        if (strtolower($leadspeekType) == 'b2b') {
            /* GET CLIENT MIN LEAD DAYS */
            $rootSetting = $this->getcompanysetting($idSys, 'rootsetting');
            $clientMinLeadDayB2b = (isset($rootSetting->clientminleadday))?$rootSetting->clientminleadday:"";
            /* GET CLIENT MIN LEAD DAYS */

            // if($rootSetting->clientcapleadpercentage != '') {
            //     /* GET costagency */
            //     $costagency = $this->getcompanysetting($userlist[0]['company_parent'], 'costagency');
            //     /* GET costagency */
            // }

            /* GET COSTAGENCY IF B2B EXIST OR GET ROOTCOSTAGENCY */
            // $masterCost = "";
            // $costagency = $this->getcompanysetting($userlist[0]['company_parent'], 'costagency');
            // if($costagency != '' && isset($costagency->b2b)) {
            //     $masterCost = $costagency;
            // } else {
            //     $masterCost = $this->getcompanysetting($idSys, 'rootcostagency');
            // }
            $costagency = $this->getcompanysetting($userlist[0]['company_parent'], 'costagency');
            if($costagency == '' || !isset($costagency->b2b)) {
                $costagency = $this->getcompanysetting($idSys, 'rootcostagency');
            }
            /* GET COSTAGENCY IF B2B EXIST OR GET ROOTCOSTAGENCY */

            /* GET AGENCY DEFAULTPRICE */
            $agencydefaultprice = $this->getcompanysetting($defaultPriceCompanyID, 'agencydefaultprice');
            /* GET AGENCY DEFAULTPRICE */
            
            /* GET ROOT AGENCY DEFAULTPRICE */
            $rootagencydefaultprice = $this->getcompanysetting($idSys, 'rootagencydefaultprice');
            /* GET ROOT AGENCY DEFAULTPRICE */
            
            if ($paymenttermDefault == "Weekly") {
                // if($rootSetting->clientcapleadpercentage != '') {
                //     $_cost_perlead = (isset($costagency->b2b->Weekly->B2bCostperlead)) ? $costagency->b2b->Weekly->B2bCostperlead : "0";
                // } else {
                    $_cost_perlead = (isset($masterCostAgency->b2b->Weekly->B2bCostperlead)) ? ($masterCostAgency->b2b->Weekly->B2bCostperlead) : (isset($agencydefaultprice->b2b->Weekly->B2bCostperlead) ? ($agencydefaultprice->b2b->Weekly->B2bCostperlead) : ($rootagencydefaultprice->b2b->Weekly->B2bCostperlead));
                
                    if(isset($costagency->b2b->Weekly->B2bCostperlead) && ($costagency->b2b->Weekly->B2bCostperlead != '') && ($_cost_perlead > 0) && ($_cost_perlead < $costagency->b2b->Weekly->B2bCostperlead)) {
                        $_cost_perlead = $costagency->b2b->Weekly->B2bCostperlead;
                    }
                //}
                                 
                $_platformfee = (isset($masterCostAgency->b2b->Weekly->B2bPlatformFee)) ? $masterCostAgency->b2b->Weekly->B2bPlatformFee : (isset($masterCost->b2b->Weekly->B2bPlatformFee) ? ($masterCost->b2b->Weekly->B2bPlatformFee) : "0");
                $_lp_min_cost_month = (isset($masterCostAgency->b2b->Weekly->B2bMinCostMonth)) ? $masterCostAgency->b2b->Weekly->B2bMinCostMonth : (isset($masterCost->b2b->Weekly->B2bMinCostMonth) ? ($masterCost->b2b->Weekly->B2bMinCostMonth) : "0");

                // if($clientMinLeadDayB2b != "") {
                //     $_lp_limit_leads = (isset($masterCostAgency->b2b->Weekly->B2bLeadsPerday)) ? (($masterCostAgency->b2b->Weekly->B2bLeadsPerday < $clientMinLeadDayB2b) ? $clientMinLeadDayB2b : $masterCostAgency->b2b->Weekly->B2bLeadsPerday) : $clientMinLeadDayB2b;
                // } else {
                //     $_lp_limit_leads = (isset($masterCostAgency->b2b->Weekly->B2bLeadsPerday)) ? $masterCostAgency->b2b->Weekly->B2bLeadsPerday : "10";
                // }
            }else if ($paymenttermDefault == "Monthly") {
                // if($rootSetting->clientcapleadpercentage != '') {
                //     $_cost_perlead = (isset($costagency->b2b->Monthly->B2bCostperlead)) ? $costagency->b2b->Monthly->B2bCostperlead : "0";
                // } else {
                    $_cost_perlead = (isset($masterCostAgency->b2b->Monthly->B2bCostperlead)) ? ($masterCostAgency->b2b->Monthly->B2bCostperlead) : (isset($agencydefaultprice->b2b->Monthly->B2bCostperlead) ? ($agencydefaultprice->b2b->Monthly->B2bCostperlead) : ($rootagencydefaultprice->b2b->Monthly->B2bCostperlead));

                    if(isset($costagency->b2b->Monthly->B2bCostperlead) && ($costagency->b2b->Monthly->B2bCostperlead != '') && ($_cost_perlead > 0) && ($_cost_perlead < $costagency->b2b->Monthly->B2bCostperlead)) {
                        $_cost_perlead = $costagency->b2b->Monthly->B2bCostperlead;
                    }
                //}

                $_platformfee = (isset($masterCostAgency->b2b->Monthly->B2bPlatformFee)) ? $masterCostAgency->b2b->Monthly->B2bPlatformFee : (isset($masterCost->b2b->Monthly->B2bPlatformFee) ? ($masterCost->b2b->Monthly->B2bPlatformFee) : "0");
                $_lp_min_cost_month = (isset($masterCostAgency->b2b->Monthly->B2bMinCostMonth)) ? $masterCostAgency->b2b->Monthly->B2bMinCostMonth : (isset($masterCost->b2b->Monthly->B2bMinCostMonth) ? ($masterCost->b2b->Monthly->B2bMinCostMonth) : "0");

                // if($clientMinLeadDayB2b != "") {
                //     $_lp_limit_leads = (isset($masterCostAgency->b2b->Monthly->B2bLeadsPerday)) ? (($masterCostAgency->b2b->Monthly->B2bLeadsPerday < $clientMinLeadDayB2b) ? $clientMinLeadDayB2b : $masterCostAgency->b2b->Monthly->B2bLeadsPerday) : $clientMinLeadDayB2b;
                // } else {
                //     $_lp_limit_leads = (isset($masterCostAgency->b2b->Monthly->B2bLeadsPerday)) ? $masterCostAgency->b2b->Monthly->B2bLeadsPerday : "10";
                // }
            }else if ($paymenttermDefault == "One Time") {
                // if($rootSetting->clientcapleadpercentage != '') {
                //     $_cost_perlead = (isset($costagency->b2b->OneTime->B2bCostperlead)) ? $costagency->b2b->OneTime->B2bCostperlead : "0";
                // } else {
                    $_cost_perlead = (isset($masterCostAgency->b2b->OneTime->B2bCostperlead)) ? ($masterCostAgency->b2b->OneTime->B2bCostperlead) : (isset($agencydefaultprice->b2b->OneTime->B2bCostperlead) ? ($agencydefaultprice->b2b->OneTime->B2bCostperlead) : ($rootagencydefaultprice->b2b->OneTime->B2bCostperlead));

                    if(isset($costagency->b2b->OneTime->B2bCostperlead) && ($costagency->b2b->OneTime->B2bCostperlead != '') && ($_cost_perlead > 0) && ($_cost_perlead < $costagency->b2b->OneTime->B2bCostperlead)) {
                        $_cost_perlead = $costagency->b2b->OneTime->B2bCostperlead;
                    }
                //}

                $_platformfee = (isset($masterCostAgency->b2b->OneTime->B2bPlatformFee)) ? $masterCostAgency->b2b->OneTime->B2bPlatformFee : (isset($masterCost->b2b->OneTime->B2bPlatformFee) ? ($masterCost->b2b->OneTime->B2bPlatformFee) : "0");
                $_lp_min_cost_month = (isset($masterCostAgency->b2b->OneTime->B2bMinCostMonth)) ? $masterCostAgency->b2b->OneTime->B2bMinCostMonth : (isset($masterCost->b2b->OneTime->B2bMinCostMonth) ? ($masterCost->b2b->OneTime->B2bMinCostMonth) : "0");

                // if($clientMinLeadDayB2b != "") {
                //     $_lp_limit_leads = (isset($masterCostAgency->b2b->OneTime->B2bLeadsPerday)) ? (($masterCostAgency->b2b->OneTime->B2bLeadsPerday < $clientMinLeadDayB2b) ? $clientMinLeadDayB2b : $masterCostAgency->b2b->OneTime->B2bLeadsPerday) : $clientMinLeadDayB2b;
                // } else {
                //     $_lp_limit_leads = (isset($masterCostAgency->b2b->OneTime->B2bLeadsPerday)) ? $masterCostAgency->b2b->OneTime->B2bLeadsPerday : "10";
                // }
            }else if ($paymenttermDefault == "Prepaid") {
                // if($rootSetting->clientcapleadpercentage != '') {
                //     $_cost_perlead = (isset($costagency->b2b->Prepaid->B2bCostperlead)) ? $costagency->b2b->Prepaid->B2bCostperlead : "0";
                // } else {
                    $_cost_perlead = (isset($masterCostAgency->b2b->Prepaid->B2bCostperlead)) ? ($masterCostAgency->b2b->Prepaid->B2bCostperlead) : (isset($agencydefaultprice->b2b->Prepaid->B2bCostperlead) ? ($agencydefaultprice->b2b->Prepaid->B2bCostperlead) : ($rootagencydefaultprice->b2b->Prepaid->B2bCostperlead));

                    if(isset($costagency->b2b->Prepaid->B2bCostperlead) && ($costagency->b2b->Prepaid->B2bCostperlead != '') && ($_cost_perlead > 0) && ($_cost_perlead < $costagency->b2b->Prepaid->B2bCostperlead)) {
                        $_cost_perlead = $costagency->b2b->Prepaid->B2bCostperlead;
                    }
                //}

                $_platformfee = (isset($masterCostAgency->b2b->Prepaid->B2bPlatformFee)) ? $masterCostAgency->b2b->Prepaid->B2bPlatformFee : (isset($masterCost->b2b->Prepaid->B2bPlatformFee) ? ($masterCost->b2b->Prepaid->B2bPlatformFee) : "0");
                $_lp_min_cost_month = (isset($masterCostAgency->b2b->Prepaid->B2bMinCostMonth)) ? $masterCostAgency->b2b->Prepaid->B2bMinCostMonth : (isset($masterCost->b2b->Prepaid->B2bMinCostMonth) ? ($masterCost->b2b->Prepaid->B2bMinCostMonth) : "0");
                
                // if($clientMinLeadDayB2b != "") {
                //     $_lp_limit_leads = (isset($masterCostAgency->b2b->Prepaid->B2bLeadsPerday)) ? (($masterCostAgency->b2b->Prepaid->B2bLeadsPerday < $clientMinLeadDayB2b) ? $clientMinLeadDayB2b : $masterCostAgency->b2b->Prepaid->B2bLeadsPerday) : $clientMinLeadDayB2b;
                // } else {
                //     $_lp_limit_leads = (isset($masterCostAgency->b2b->Prepaid->B2bLeadsPerday)) ? $masterCostAgency->b2b->Prepaid->B2bLeadsPerday : "10";
                // }
            }
        }

        if($_lp_limit_leads < 1) {
            $_lp_limit_leads = 1;
        }

        //return response()->json(array('result'=>'failed','message'=>strtolower($leadspeekType),'costlead'=>$_cost_perlead,'platform'=>$_platformfee,'mincost'=>$_lp_min_cost_month));
        //exit;die();
        // info(['leadspeek_api_id' => $tryseraID,]);

        //set campaign's integration based on client integration settings
        $integrations_setting = IntegrationSettings::select('integration_slug', 'enable_default_campaign')
        ->where('company_id', $companyUserID)
        ->whereIn('integration_slug', [
            'sendgrid',
            'mailboxpower',
            'gohighlevel',
            'kartra',
            'zapier',
            'clickfunnels',
            'agencyzoom',
            'sendjim'
        ])
        ->get()
        ->keyBy('integration_slug');

        $sendgrid_is_active     = isset($integrations_setting['sendgrid'])     && $integrations_setting['sendgrid']->enable_default_campaign ? 1 : 0;
        $mbp_is_active          = isset($integrations_setting['mailboxpower']) && $integrations_setting['mailboxpower']->enable_default_campaign ? 1 : 0;
        $ghl_is_active          = isset($integrations_setting['gohighlevel'])  && $integrations_setting['gohighlevel']->enable_default_campaign ? 1 : 0;
        $kartra_is_active       = isset($integrations_setting['kartra'])       && $integrations_setting['kartra']->enable_default_campaign ? 1 : 0;
        $zap_is_active          = isset($integrations_setting['zapier'])       && $integrations_setting['zapier']->enable_default_campaign ? 1 : 0;
        $clickfunnels_is_active = isset($integrations_setting['clickfunnels']) && $integrations_setting['clickfunnels']->enable_default_campaign ? 1 : 0;
        $agencyzoom_is_active   = isset($integrations_setting['agencyzoom'])   && $integrations_setting['agencyzoom']->enable_default_campaign ? 1 : 0;
        $sendjim_is_active      = isset($integrations_setting['sendjim'])      && $integrations_setting['sendjim']->enable_default_campaign ? 1 : 0;
        //set campaign's integration based on client integration settings

        /* FOR LOCAL ADVANCE OR B2B INFORMATION */
        $campaign_information_type_local = 'basic';
        if (strtolower($leadspeekType) == 'local') {
            // id save
            $advanceInformation = '';
            $merge_information = [];
            $listAdvanceInformationId = isset($request->listAdvanceInformationId)?$request->listAdvanceInformationId:[];
            $listB2BInformationId = isset($request->listB2BInformationId)?$request->listB2BInformationId:[];
            if (is_array($listAdvanceInformationId) && !empty($listAdvanceInformationId)) {
                $merge_information['advance'] = $listAdvanceInformationId;
            }
            // if (is_array($listB2BInformationId) && !empty($listB2BInformationId)) {
            //     $merge_information['b2b'] = $listB2BInformationId;
            // }
            if(is_array($merge_information) && !empty($merge_information)) {
                $advanceInformation = json_encode($merge_information);
            }
            // id save

            // type save
            // $valid_types = ['basic','advanced','b2b'];
            $valid_types = ['basic','advanced'];
            $campaign_information_type_local = isset($request->campaign_information_type_local)?$request->campaign_information_type_local:['basic'];
            $campaign_information_type_local = array_filter($campaign_information_type_local, function($item) use($valid_types){
                return in_array($item, $valid_types);
            });
            $campaign_information_type_local = implode(',',array_unique(array_merge(['basic'], $campaign_information_type_local)));
            // type save
        }

        if (strtolower($leadspeekType) == 'local') {
            $_embeddedcode_crawl = 'F';
            $_embedded_status = 'Waiting for the embedded code to be placed on: ' . $urlCode;
            $_cost_perlead = (isset($masterCostAgency->local->Weekly->LeadspeekCostperlead))?$masterCostAgency->local->Weekly->LeadspeekCostperlead:'0';
            $_platformfee = (isset($masterCostAgency->local->Weekly->LeadspeekPlatformFee))?$masterCostAgency->local->Weekly->LeadspeekPlatformFee:'0';
            $_lp_max_lead_month = '0';
            $_lp_min_cost_month = (isset($masterCostAgency->local->Weekly->LeadspeekMinCostMonth))?$masterCostAgency->local->Weekly->LeadspeekMinCostMonth:'0';
            $_lp_limit_leads = (isset($masterCostAgency->local->Weekly->LeadspeekLeadsPerday))?$masterCostAgency->local->Weekly->LeadspeekLeadsPerday:'10';
            $leadspeek_locator_require = "FirstName,LastName,MailingAddress,Phone";

            /* GET AGENCY DEFAULTPRICE */
            $agencydefaultprice = $this->getcompanysetting($defaultPriceCompanyID, 'agencydefaultprice');
            /* GET AGENCY DEFAULTPRICE */

            /* GET ROOT AGENCY DEFAULTPRICE */
            $rootagencydefaultprice = $this->getcompanysetting($idSys, 'rootagencydefaultprice');
            /* GET ROOT AGENCY DEFAULTPRICE */

            /* COPY campaign_information_type_local */
            $campaign_information_type_local_copy = explode(',', $campaign_information_type_local);
            /* COPY campaign_information_type_local */

            if ($paymenttermDefault == "Weekly") {
                if(in_array('advanced', $campaign_information_type_local_copy)) {
                    $_cost_perlead = (isset($masterCostAgency->local->Weekly->LeadspeekCostperleadAdvanced)) ? ($masterCostAgency->local->Weekly->LeadspeekCostperleadAdvanced) : (isset($agencydefaultprice->local->Weekly->LeadspeekCostperleadAdvanced) ? ($agencydefaultprice->local->Weekly->LeadspeekCostperleadAdvanced) : ($rootagencydefaultprice->local->Weekly->LeadspeekCostperleadAdvanced));
                } else {
                    $_cost_perlead = (isset($masterCostAgency->local->Weekly->LeadspeekCostperlead)) ? ($masterCostAgency->local->Weekly->LeadspeekCostperlead) : (isset($agencydefaultprice->local->Weekly->LeadspeekCostperlead) ? ($agencydefaultprice->local->Weekly->LeadspeekCostperlead) : ($rootagencydefaultprice->local->Weekly->LeadspeekCostperlead));
                }
                $_platformfee = (isset($masterCostAgency->local->Weekly->LeadspeekPlatformFee)) ? ($masterCostAgency->local->Weekly->LeadspeekPlatformFee) : (isset($agencydefaultprice->local->Weekly->LeadspeekPlatformFee) ? ($agencydefaultprice->local->Weekly->LeadspeekPlatformFee) : ($rootagencydefaultprice->local->Weekly->LeadspeekPlatformFee));
                $_lp_min_cost_month = (isset($masterCostAgency->local->Weekly->LeadspeekMinCostMonth)) ? ($masterCostAgency->local->Weekly->LeadspeekMinCostMonth) : (isset($agencydefaultprice->local->Weekly->LeadspeekMinCostMonth) ? ($agencydefaultprice->local->Weekly->LeadspeekMinCostMonth) : ($rootagencydefaultprice->local->Weekly->LeadspeekMinCostMonth));
                $_lp_limit_leads = (isset($masterCostAgency->local->Weekly->LeadspeekLeadsPerday)) ? $masterCostAgency->local->Weekly->LeadspeekLeadsPerday : '10';
            }else if ($paymenttermDefault == "Monthly") {
                if(in_array('advanced', $campaign_information_type_local_copy)) {
                    $_cost_perlead = (isset($masterCostAgency->local->Monthly->LeadspeekCostperleadAdvanced)) ? ($masterCostAgency->local->Monthly->LeadspeekCostperleadAdvanced) : (isset($agencydefaultprice->local->Monthly->LeadspeekCostperleadAdvanced) ? ($agencydefaultprice->local->Monthly->LeadspeekCostperleadAdvanced) : ($rootagencydefaultprice->local->Monthly->LeadspeekCostperleadAdvanced));
                } else {
                    $_cost_perlead = (isset($masterCostAgency->local->Monthly->LeadspeekCostperlead)) ? ($masterCostAgency->local->Monthly->LeadspeekCostperlead) : (isset($agencydefaultprice->local->Monthly->LeadspeekCostperlead) ? ($agencydefaultprice->local->Monthly->LeadspeekCostperlead) : ($rootagencydefaultprice->local->Monthly->LeadspeekCostperlead));
                }
                $_platformfee = (isset($masterCostAgency->local->Monthly->LeadspeekPlatformFee)) ? ($masterCostAgency->local->Monthly->LeadspeekPlatformFee) : (isset($agencydefaultprice->local->Monthly->LeadspeekPlatformFee) ? ($agencydefaultprice->local->Monthly->LeadspeekPlatformFee) : ($rootagencydefaultprice->local->Monthly->LeadspeekPlatformFee));
                $_lp_min_cost_month = (isset($masterCostAgency->local->Monthly->LeadspeekMinCostMonth)) ? ($masterCostAgency->local->Monthly->LeadspeekMinCostMonth) : (isset($agencydefaultprice->local->Monthly->LeadspeekMinCostMonth) ? ($agencydefaultprice->local->Monthly->LeadspeekMinCostMonth) : ($rootagencydefaultprice->local->Monthly->LeadspeekMinCostMonth));
                $_lp_limit_leads = (isset($masterCostAgency->local->Monthly->LeadspeekLeadsPerday)) ? $masterCostAgency->local->Monthly->LeadspeekLeadsPerday : '10';
            }else if ($paymenttermDefault == "One Time") {
                if(in_array('advanced', $campaign_information_type_local_copy)) {
                    $_cost_perlead = (isset($masterCostAgency->local->OneTime->LeadspeekCostperleadAdvanced)) ? ($masterCostAgency->local->OneTime->LeadspeekCostperleadAdvanced) : (isset($agencydefaultprice->local->OneTime->LeadspeekCostperleadAdvanced) ? ($agencydefaultprice->local->OneTime->LeadspeekCostperleadAdvanced) : ($rootagencydefaultprice->local->OneTime->LeadspeekCostperleadAdvanced));
                } else {
                    $_cost_perlead = (isset($masterCostAgency->local->OneTime->LeadspeekCostperlead)) ? ($masterCostAgency->local->OneTime->LeadspeekCostperlead) : (isset($agencydefaultprice->local->OneTime->LeadspeekCostperlead) ? ($agencydefaultprice->local->OneTime->LeadspeekCostperlead) : ($rootagencydefaultprice->local->OneTime->LeadspeekCostperlead));
                }
                $_platformfee = (isset($masterCostAgency->local->OneTime->LeadspeekPlatformFee)) ? ($masterCostAgency->local->OneTime->LeadspeekPlatformFee) : (isset($agencydefaultprice->local->OneTime->LeadspeekPlatformFee) ? ($agencydefaultprice->local->OneTime->LeadspeekPlatformFee) : ($rootagencydefaultprice->local->OneTime->LeadspeekPlatformFee));
                $_lp_min_cost_month = (isset($masterCostAgency->local->OneTime->LeadspeekMinCostMonth)) ? ($masterCostAgency->local->OneTime->LeadspeekMinCostMonth) : (isset($agencydefaultprice->local->OneTime->LeadspeekMinCostMonth) ? ($agencydefaultprice->local->OneTime->LeadspeekMinCostMonth) : ($rootagencydefaultprice->local->OneTime->LeadspeekMinCostMonth));
                $_lp_limit_leads = (isset($masterCostAgency->local->OneTime->LeadspeekLeadsPerday)) ? $masterCostAgency->local->OneTime->LeadspeekLeadsPerday : '10';
            }else if ($paymenttermDefault == "Prepaid") {
                if(in_array('advanced', $campaign_information_type_local_copy)) {
                    $_cost_perlead = (isset($masterCostAgency->local->Prepaid->LeadspeekCostperleadAdvanced)) ? ($masterCostAgency->local->Prepaid->LeadspeekCostperleadAdvanced) : (isset($agencydefaultprice->local->Prepaid->LeadspeekCostperleadAdvanced) ? ($agencydefaultprice->local->Prepaid->LeadspeekCostperleadAdvanced) : ($rootagencydefaultprice->local->Prepaid->LeadspeekCostperleadAdvanced));
                } else {
                    $_cost_perlead = (isset($masterCostAgency->local->Prepaid->LeadspeekCostperlead)) ? ($masterCostAgency->local->Prepaid->LeadspeekCostperlead) : (isset($agencydefaultprice->local->Prepaid->LeadspeekCostperlead) ? ($agencydefaultprice->local->Prepaid->LeadspeekCostperlead) : ($rootagencydefaultprice->local->Prepaid->LeadspeekCostperlead));
                }
                $_platformfee = (isset($masterCostAgency->local->Prepaid->LeadspeekPlatformFee)) ? ($masterCostAgency->local->Prepaid->LeadspeekPlatformFee) : (isset($agencydefaultprice->local->Prepaid->LeadspeekPlatformFee) ? ($agencydefaultprice->local->Prepaid->LeadspeekPlatformFee) : ($rootagencydefaultprice->local->Prepaid->LeadspeekPlatformFee));
                $_lp_min_cost_month = (isset($masterCostAgency->local->Prepaid->LeadspeekMinCostMonth)) ? ($masterCostAgency->local->Prepaid->LeadspeekMinCostMonth) : (isset($agencydefaultprice->local->Prepaid->LeadspeekMinCostMonth) ? ($agencydefaultprice->local->Prepaid->LeadspeekMinCostMonth) : ($rootagencydefaultprice->local->Prepaid->LeadspeekMinCostMonth));
                $_lp_limit_leads = (isset($masterCostAgency->local->Prepaid->LeadspeekLeadsPerday)) ? $masterCostAgency->local->Prepaid->LeadspeekLeadsPerday : '10';
            }

            //$phoneenabled = 'F';
            //$homeaddressenabled = 'T';
            //$requireemailaddress = 'T';
            $locationtarget = 'Focus';
        }
        /* FOR LOCAL ADVANCE OR B2B INFORMATION */

        $newclient = LeadspeekUser::create([
            'module_id' => $this->_moduleID,
            'company_id' => $request->companyID,
            'user_id' => $request->userID,
            'report_type' => $request->reportType,
            'report_sent_to' => $request->reportSentTo,
            'admin_notify_to' => $_adminNotifyTo,
            'leads_amount_notification' => $request->leadsAmountNotification,
            'total_leads' => 0,
            'start_billing_date' => date('Y-m-d H:i:s'),
            'spreadsheet_id' => '',
            'filename' => '',
            'leadspeek_type' => $leadspeekType,
            'group_company_id' => $companyGroupID,
            'campaign_name' => $campaignName,
            'url_code' => $urlCode,
            'url_code_thankyou' => $urlCodeThankyou,
            'leadspeek_organizationid' => $clientOrganizationID,
            'leadspeek_campaignsid' => $clientCampaignID,
            'paymentterm' => $paymenttermDefault,
            'location_target' => $locationtarget,
            'timezone' => $timezone,
            
            'leadspeek_locator_require' => (isset($leadspeek_locator_require))?$leadspeek_locator_require:'',
            'leadspeek_locator_zip' => (isset($leadspeek_locator_zip))?$leadspeek_locator_zip:'',
            'leadspeek_locator_state' => (isset($leadspeek_locator_state))?$leadspeek_locator_state:'',
            'leadspeek_locator_state_exclude' => (isset($leadspeek_locator_state_exclude))?$leadspeek_locator_state_exclude:'',
            'leadspeek_locator_state_type' => (isset($leadspeek_locator_state_type))?$leadspeek_locator_state_type:'',
            'leadspeek_locator_state_simplifi' => (isset($leadspeek_locator_state_simplifi))?$leadspeek_locator_state_simplifi:'',
            'leadspeek_locator_city' => (isset($leadspeek_locator_city))?$leadspeek_locator_city:'',
            'leadspeek_locator_city_simplifi' => (isset($leadspeek_locator_city_simplifi))?$leadspeek_locator_city_simplifi:'',
            'leadspeek_locator_keyword' => (isset($leadspeek_locator_keyword))?$leadspeek_locator_keyword:'',
            'leadspeek_locator_keyword_contextual' => (isset($leadspeek_locator_keyword_contextual))?$leadspeek_locator_keyword_contextual:'',

            'hide_phone' => $clientHidePhone,
            'last_lead_pause' => '0000-00-00 00:00:00',
            'last_lead_start' => '0000-00-00 00:00:00',
            'active' => 'F',
            'disabled' => 'T',
            'active_user' => 'F',
            'leadspeek_api_id' => $tryseraID,
            'embeddedcode_crawl' => $_embeddedcode_crawl,
            'embedded_status' => $_embedded_status,
            'questionnaire_answers' => json_encode($answers),
            'cost_perlead' => $_cost_perlead,
            'platformfee' => $_platformfee,
            'lp_max_lead_month' => $_lp_max_lead_month,
            'lp_min_cost_month' => $_lp_min_cost_month,
            'lp_limit_leads' => $_lp_limit_leads,
            'enable_minimum_limit_leads' => $enableMinimumLimitLeads,
            'minimum_limit_leads' => $minimumLimitLeads,
            'file_url' => '',
            'gtminstalled' => $gtminstalled,
            'phoneenabled' => $phoneenabled,
            'homeaddressenabled' => $homeaddressenabled,
            'reidentification_type' => $reidentificationtype,
            'applyreidentificationall' => $applyreidentificationall,
            'require_email' => $requireemailaddress,
            'advance_information' => $advanceInformation,
            'campaign_information_type' => $campaign_information_type,
            'campaign_information_type_local' => $campaign_information_type_local,
            'trysera' => 'F',
            'is_send_email_prepaid' => 'F',

            'sendgrid_is_active' => $sendgrid_is_active,
            'mbp_is_active' => $mbp_is_active,
            'ghl_is_active' => $ghl_is_active,
            'kartra_is_active' => $kartra_is_active,
            'zap_is_active' => $zap_is_active,
            'clickfunnels_is_active' => $clickfunnels_is_active,
            'agencyzoom_is_active' => $agencyzoom_is_active,
            'sendjim_is_active' => $sendjim_is_active,
        ]);

        $newclientID = $newclient->id;

        /* UPDATE FREQ TO MONTH IF LOCAL AND PREPAID */
        if (strtolower($leadspeekType) == 'local' && $paymenttermDefault == "Prepaid") { 
            $newclient->continual_buy_options = 'Monthly';
            $newclient->lp_limit_freq = 'month';
            $newclient->lp_limit_leads = 50;
            $newclient->save();
        }
        /* UPDATE FREQ TO MONTH IF LOCAL AND PREPAID */
        
        /** GET PRODUCT NAME */
        $productlocalname = 'Site ID';
        $productlocatorname = 'Search ID';

        $rootcompanysetting = CompanySetting::where('company_id',trim($userlist[0]['company_root_id']))->whereEncrypted('setting_name','rootcustomsidebarleadmenu')->get();
        if (count($rootcompanysetting) > 0) {
            $productname = json_decode($rootcompanysetting[0]['setting_value']);
            $productlocalname = $productname->local->name;
            $productlocatorname = $productname->locator->name;
        }

        if($this->checkwhitelabellingpackage(trim($userlist[0]['company_parent']))) {
            $companysetting = CompanySetting::where('company_id',trim($userlist[0]['company_parent']))->whereEncrypted('setting_name','customsidebarleadmenu')->get();
            if (count($companysetting) > 0) {
                $productname = json_decode($companysetting[0]['setting_value']);
                $productlocalname = $productname->local->name;
                $productlocatorname = $productname->locator->name;
            }
        }
        /** GET PRODUCT NAME */

        /** UPDATE TRYSERA ID */
        
        /*if($tryseraID != "") {
            $newclient->leadspeek_api_id = $tryseraID;
            $newclient->save();
        }*/
        /** UPDATE TRYSERA ID */
        if ($request->reportType == "GoogleSheet") {
        //if (false) {
        /** CHECK IF HAVE GOOGLE CONNECTION */
        $chkGconnection = $this->get_setting($request->companyID,$this->_moduleID,$this->_settingTokenName);
        if(count($chkGconnection) > 0 && $chkGconnection[0]->setting_value != '') {
        /** CHECK IF HAVE GOOGLE CONNECTION */
            $spreadSheetID = "";
            $ownerSpreadsheet = "";
            try {
                $campaignInformation = campaignInformation::where('status', 'active')->where('campaign_type','enhance')->get();

                /** CREATE GOOGLE SHEET ONLINE */
                $spreadsheetTitle = trim($request->companyName . ' - ' . $campaignName . ' #' . $tryseraID);
                $clientGoogle = new GoogleSheet($request->companyID,$this->_moduleID,$this->_settingTokenName,'',true,$newclient->leadspeek_api_id ?? "",$newclient->leadspeek_type ?? "", __FUNCTION__);
                $spreadSheetID = $clientGoogle->createSpreadSheet($spreadsheetTitle);
                $ownerSpreadsheet = $clientGoogle->getOwner($spreadSheetID);
                $clientGoogle->updateSheetTitle(date('Y'));

                $clientGoogle->setSheetName(date('Y'));
                
                /** INTIAL GOOGLE SPREADSHEET HEADER */
                //$contentHeader[] = array('ID','Email','IP','Source','OptInDate','ClickDate','Referer','Phone','First Name','Last Name','Address1','Address2','City','State','Zipcode');
                $additonalCol = "";
                $content_b2b = array();
                if (strtolower($leadspeekType) == 'local') {
                    $contentHeader[] = array('ID','ClickDate','First Name','Last Name','Email1','Email2','Phone1','Phone2','Address1','Address2','City','State','Zipcode','Landing URL');

                    $campaign_information_type_local = explode(',',$campaign_information_type_local);
                    if(!empty($campaign_information_type_local) && in_array('advanced',$campaign_information_type_local)) 
                    {   
                        $campaignInformation = campaignInformation::where('status', 'active')
                                                                    // ->whereIn('campaign_type', ['local_adv','local_b2b'])
                                                                    ->whereIn('campaign_type', ['local_adv'])
                                                                    ->orderBy('start_index','ASC')
                                                                    ->get();
                        $campaignInformationArray = [];
                        foreach($campaignInformation as $item)
                        {
                            $description = json_decode($item->description, true);
                            $campaignInformationArray  = array_merge($campaignInformationArray, $description);
                        }
                        $contentHeader[0] = array_merge($contentHeader[0], $campaignInformationArray);
                    }
                    $additonalCol = "https://example.com";
                }else if(strtolower($leadspeekType) == 'locator' || strtolower($leadspeekType) == 'enhance'){
                    $contentHeader[] = array('ID','ClickDate','First Name','Last Name','Email1','Email2','Phone1','Phone2','Address1','Address2','City','State','Zipcode','Keyword');

                    if(strtolower($leadspeekType) == 'enhance' && !empty($advanceInformation) && trim($advanceInformation) != '') {
                        $campaignInformationArray = [];
                        foreach($campaignInformation as $item)
                        {
                            $description = json_decode($item->description, true);
                            $campaignInformationArray = array_merge($campaignInformationArray, $description);
                        }
                        $contentHeader[0] = array_merge($contentHeader[0], $campaignInformationArray);
                    }

                    $additonalCol = "keyword";
                }else if(strtolower($leadspeekType) == 'b2b') {
                    $additonalCol = "keyword";

                    $settingJson = DB::table('global_settings')
                                        ->where('setting_name', 'spreadsheet_b2b_ordering')
                                        ->value('setting_value');

                    $dtJson = json_decode($settingJson, true);
                    $startDate_spreadsheet = isset($dtJson[0]['start-date']) ? Carbon::parse($dtJson[0]['start-date']) : null;
                    //// cek setingan di global seting.. 
                    // ketika today lebih dari tgl yg di global seting, pakai data dan indexing baru, 
                    // else pakai data dan indexing lama
                    if ($startDate_spreadsheet && Carbon::today()->greaterThanOrEqualTo($startDate_spreadsheet)) {
                        if(!empty($advanceInformation) && trim($advanceInformation) != '') {
                            $contentHeader[] = array('ID','Company Name','First Name','Last Name','Company Email','Company Phone','Company Website','Job Title','Level','Job Function','LinkedIn','ClickDate','Phone1','Address1','Address2','City','State','Zipcode','Keyword','Num Employees','Sales Volume','Year Founded');
                            $content_b2b = ['00000000','Ai Company','John','Doe','johndoe1-' . $tryseraID . '@example.com','123-123-1234','aicompany.com','Sales Staff','Staff','Sales','https://www.linkedin.com/in/jhon-doe',date('m/d/Y h:i:s A'),'123-123-1234',$tryseraID . ' John Doe Street','suite 101','Columbus','OH','43055',$additonalCol,'1 TO 49','Less Than $500.000','1999'];
    
                        } else {
                            $contentHeader[] = array('ID', 'First Name', 'Last Name', 'Company Email', 'ClickDate', 'Phone1', 'Address1', 'Address2', 'City', 'State', 'Zipcode', 'Keyword');
                            $content_b2b =  ['00000000','John','Doe','johndoe1-' . $tryseraID . '@example.com',date('m/d/Y h:i:s A'),'123-123-1234',$tryseraID . ' John Doe Street','suite 101','Columbus','OH','43055',$additonalCol];
                        }
                    } else {
                        $contentHeader[] = array('ID','ClickDate','First Name','Last Name','Email1','Email2','Phone1','Phone2','Address1','Address2','City','State','Zipcode','Keyword','Employee ID','Company_Name','Company_Phone','Company_Website','Num_Employees','Sales_Volume','Year_Founded','Job_Title','Level','Job_Function','Headquarters_Branch','NAICS_CODE','LastSeenDate','LinkedIn');
                        $content_b2b = ['123456789','AI COMPANY','123-123-1234','AICOMPANY.COM','1 TO 49','LESS THAN $500.000','1999','SALES STAFF','STAFF','SALES','0','441310','09/01/2023','https://www.linkedin.com/in/jhon-doe'];
                    }

                    
                    
                    
                    // $contentHeader[] = array('ID','ClickDate','First Name','Last Name','Email1','Email2','Phone1','Phone2','Address1','Address2','City','State','Zipcode','Keyword');
                }  
                else {
                    $contentHeader[] = array('ID','ClickDate','First Name','Last Name','Email1','Email2','Phone1','Phone2','Address1','Address2','City','State','Zipcode','Keyword','Employee ID','Company_Name','Company_Phone','Company_Website','Num_Employees','Sales_Volume','Year_Founded','Job_Title','Level','Job_Function','Headquarters_Branch','NAICS_CODE','LastSeenDate','LinkedIn');
                    $additonalCol = "keyword";
                }
                /** INTIAL GOOGLE SPREADSHEET HEADER */
                $savedData = $clientGoogle->saveDataHeaderToSheet($contentHeader);

                /** ADD DUMMY DATA ON FIRST ROW FOR ZAPIER TEST */
                //$content[] = array('00000000','johndoe@example.com','','',date('m/d/Y h:i:s A'),date('m/d/Y h:i:s A'),'','','John','Doe','John Doe Street','','Columbus','OH','43055');
                $content[] = array('00000000',date('m/d/Y h:i:s A'),'John','Doe','johndoe1-' . $tryseraID . '@example.com','johndoe2-' . $tryseraID . '@example.com','123-123-1234','567-567-5678',$tryseraID . ' John Doe Street','suite 101','Columbus','OH','43055',$additonalCol);
                
                if(strtolower($leadspeekType) == 'enhance' && !empty($advanceInformation) && trim($advanceInformation) != '') {
                    $campaignInformationArray = [];
                    foreach($campaignInformation as $item)
                    {
                        $description = json_decode($item->description, true);
                        $campaignInformationArray = array_merge($campaignInformationArray, $description);
                    }
                    $content[0] = array_merge($content[0], $campaignInformationArray);
                } else if(strtolower($leadspeekType) == 'b2b') {
                    // $contentB2b = ['123456789','AI COMPANY','123-123-1234','AICOMPANY.COM','1 TO 49','LESS THAN $500.000','1999','SALES STAFF','STAFF','SALES','0','441310','09/01/2023','https://www.linkedin.com/in/jhon-doe'];
                    // $content[0] = array_merge($content[0],$contentB2b);
                    $content[0] = $content_b2b;
                }

                $savedData = $clientGoogle->saveDataToSheet($content);
                /** ADD DUMMY DATA ON FIRST ROW FOR ZAPIER TEST */

                $newclient->spreadsheet_id = $spreadSheetID;
                $newclient->spreadsheet_owner = $ownerSpreadsheet;
                $newclient->save();

                /** CHECK IF PHONE HIDE OR NOT */
                $hidePhone = ($clientHidePhone == 'T')?true:false;
                $sheetID = $clientGoogle->getSheetID(date('Y'));
                /** HIDE EVERYTHING ELSE BY DEFAULT EXCEPT firstname, lastname, email, click date, and address */
                $clientGoogle->showhideColumn($sheetID,0,1,'T');
                //$clientGoogle->showhideColumn($sheetID,2,5,'T');
                //$clientGoogle->showhideColumn($sheetID,6,7,'T');
                //$clientGoogle->showhideColumn($sheetID,7,8,$hidePhone);

                /*if (strtolower($leadspeekType) == 'local') {
                    $clientGoogle->showhideColumn($sheetID,7,8,'T');
                }else{
                */
                
                    if (trim($leadspeek_locator_require) == "FirstName,LastName") {
                        if($leadspeekType == 'b2b') {
                            $clientGoogle->showhideColumn($sheetID,12,13,'T');
                            $clientGoogle->showhideColumn($sheetID,13,18,'F');
                        } else {
                        $clientGoogle->showhideColumn($sheetID,6,8,'T');
                        $clientGoogle->showhideColumn($sheetID,8,13,'F');
                        //$clientGoogle->showhideColumn($sheetID,11,12,'T');
                        }
                    }else if (trim($leadspeek_locator_require) == 'FirstName,LastName,MailingAddress') {
                        if($leadspeekType == 'b2b') {
                            $clientGoogle->showhideColumn($sheetID,12,13,'T');
                            $clientGoogle->showhideColumn($sheetID,13,18,'F');
                        } else {
                            $clientGoogle->showhideColumn($sheetID,6,8,'T');
                            $clientGoogle->showhideColumn($sheetID,8,13,'F');
                            //$clientGoogle->showhideColumn($sheetID,11,12,'F');
                        }
                    }else if (trim($leadspeek_locator_require) == 'FirstName,LastName,MailingAddress,Phone') {

                            /** NEW OPTIONS FOR ENABLE PHONE AND MAIL ADDRESS */
                            if($phoneenabled == 'T' && $homeaddressenabled == 'F') {
                                if($leadspeekType == 'b2b') {
                                    $clientGoogle->showhideColumn($sheetID,5,6,'F');
                                    $clientGoogle->showhideColumn($sheetID,12,13,'F');
                                    $clientGoogle->showhideColumn($sheetID,13,18,'T');
                                } else {
                                    $clientGoogle->showhideColumn($sheetID,6,8,'F');
                                    $clientGoogle->showhideColumn($sheetID,8,13,'T');
                                    // if($leadspeekType == 'b2b') {
                                    //     $clientGoogle->showhideColumn($sheetID,16,17,'F');
                                    // }
                                }
                                //$clientGoogle->showhideColumn($sheetID,11,12,'T');
                                //$clientGoogle->showhideColumn($sheetID,12,13,'T');
                                //$clientGoogle->showhideColumn($sheetID,13,14,'T');
                                //$clientGoogle->showhideColumn($sheetID,14,15,'T');
                            }else if($phoneenabled == 'F' && $homeaddressenabled == 'T') {
                                if($leadspeekType == 'b2b') {
                                    $clientGoogle->showhideColumn($sheetID,5,6,'T');
                                    $clientGoogle->showhideColumn($sheetID,12,13,'T');
                                    $clientGoogle->showhideColumn($sheetID,13,18,'F');
                                } else {
                                    $clientGoogle->showhideColumn($sheetID,6,8,'T');
                                    $clientGoogle->showhideColumn($sheetID,8,13,'F');
                                    // if($leadspeekType == 'b2b') {
                                    //     $clientGoogle->showhideColumn($sheetID,16,17,'T');
                                    // }
                                }
                                //$clientGoogle->showhideColumn($sheetID,11,12,'F');
                                //$clientGoogle->showhideColumn($sheetID,12,13,'F');
                                //$clientGoogle->showhideColumn($sheetID,13,14,'F');
                                //$clientGoogle->showhideColumn($sheetID,14,15,'F');
                            }else if($phoneenabled == 'F' && $homeaddressenabled == 'F') {
                                if($leadspeekType == 'b2b') {
                                    $clientGoogle->showhideColumn($sheetID,5,6,'T');
                                    $clientGoogle->showhideColumn($sheetID,12,13,'T');
                                    $clientGoogle->showhideColumn($sheetID,13,18,'T');
                                } else {
                                    $clientGoogle->showhideColumn($sheetID,6,8,'T');
                                    $clientGoogle->showhideColumn($sheetID,8,13,'T');
                                }
                                //$clientGoogle->showhideColumn($sheetID,11,12,'T');
                                //$clientGoogle->showhideColumn($sheetID,12,13,'T');
                                //$clientGoogle->showhideColumn($sheetID,13,14,'T');
                                //$clientGoogle->showhideColumn($sheetID,14,15,'T');
                            }else{
                                if($leadspeekType == 'b2b') {
                                    $clientGoogle->showhideColumn($sheetID,5,6,'F');
                                    $clientGoogle->showhideColumn($sheetID,12,13,'F');
                                    $clientGoogle->showhideColumn($sheetID,13,18,'F');
                                } else {
                                    $clientGoogle->showhideColumn($sheetID,6,8,'F');
                                    $clientGoogle->showhideColumn($sheetID,8,13,'F');
                                }
                                //$clientGoogle->showhideColumn($sheetID,11,12,'F');
                                //$clientGoogle->showhideColumn($sheetID,12,13,'F');
                                //$clientGoogle->showhideColumn($sheetID,13,14,'F');
                                //$clientGoogle->showhideColumn($sheetID,14,15,'F');
                            }
                            /** NEW OPTIONS FOR ENABLE PHONE AND MAIL ADDRESS */
                    }

                    if(strtolower($leadspeekType) == 'enhance' && !empty($advanceInformation) && trim($advanceInformation) != '') {
                        /* SEMBUNYIKAN CAMPAIGN INFORMATION YANG TIDAK DIPILIH */
                        $advanceInformationId = explode(',', $advanceInformation);
                        $campaignInformationNotInId = campaignInformation::whereNotIn('id', $advanceInformationId)
                                                                         ->where('status', 'active')
                                                                         ->where('campaign_type','enhance')
                                                                         ->get();
                        foreach($campaignInformationNotInId as $item)
                        {
                            $startIndex = intval($item->start_index);
                            
                            $countDescription = count(json_decode($item->description, true));
                            $endIndex = intval($startIndex + $countDescription);
                            // info(['startIndex' => $startIndex, 'endIndex' => $endIndex]);

                            // $endIndex = intval($item->end_index + 1);
                            // info("sembunyikan {$item->type}", ['startIndex' => $startIndex, 'endIndex' => $endIndex]);
                            $clientGoogle->showhideColumn($sheetID, $startIndex, $endIndex, 'T');
                        }
                        /* SEMBUNYIKAN CAMPAIGN INFORMATION YANG TIDAK DIPILIH */
                    }

                    if(strtolower($leadspeekType) == 'b2b' && !empty($advanceInformation) && trim($advanceInformation) != '') {
                        /* SEMBUNYIKAN CAMPAIGN INFORMATION YANG TIDAK DIPILIH */
                        $advanceInformationId = explode(',', $advanceInformation);
                        $campaignInformationNotInId = campaignInformation::whereNotIn('id', $advanceInformationId)
                                                                         ->where('status', 'active')
                                                                         ->where('campaign_type','b2b')
                                                                         ->orderBy('id','asc')
                                                                         ->get();
                        foreach($campaignInformationNotInId as $item)
                        {
                            $startIndex = intval($item->start_index);
                            
                            $countDescription = count(json_decode($item->description, true));
                            $endIndex = intval($startIndex + $countDescription);

                            $clientGoogle->showhideColumn($sheetID, $startIndex, $endIndex, 'T');
                        }
                        /* SEMBUNYIKAN CAMPAIGN INFORMATION YANG TIDAK DIPILIH */
                    }
                //}
                //$clientGoogle->showhideColumn($sheetID,11,12,'T');
                /** HIDE EVERYTHING ELSE BY DEFAULT EXCEPT firstname, lastname, email, click date, and address */
                /** CHECK IF PHONE HIDE OR NOT */

                /** CREATE GOOGLE SHEET ONLINE */

                /** SHARE SPREADSHEET WITH ROLE AS VIEWER */
                /*$tmpSentTo = explode(PHP_EOL, $request->reportSentTo);
                foreach($tmpSentTo as $sto) {
                    $permission = $clientGoogle->createPermission($spreadSheetID,$sto,'user','reader',false);
                }*/
                /** SHARE SPREADSHEET WITH ROLE AS VIEWER */

                /** SENT TO CLIENT AND ADMIN THE LINK FOR THE PERMISSION */
        
                /** FIND ADMIN EMAIL */
                    $tmp = User::select('email')->whereIn('id', $request->adminNotifyTo)->get();
                    $adminEmail = array();
                    foreach($tmp as $ad) {
                        $permission = $clientGoogle->createPermission($spreadSheetID,$ad['email'],'user','writer',false);
                        array_push($adminEmail,$ad['email']);
                    }
                
                    /** SENT ALSO TO EMAIL CLIENT */
                    // cek setting di client management apakah client aktif sebagai editor??
                    $cek_editor_client = User::where('id',$request->userID)
                                                ->where('editor_spreadsheet','T')
                                                ->where('active','T')
                                                ->exists();
                    if ($cek_editor_client) {
                        $permission_client = 'writer';
                    } else {
                        $permission_client = 'reader';
                    }

                    $tmp = explode(PHP_EOL, $request->reportSentTo);
                    foreach($tmp as $ad) {
                        // $permission = $clientGoogle->createPermission($spreadSheetID,$ad,'user','reader',false);
                        $permission = $clientGoogle->createPermission($spreadSheetID,$ad,'user',$permission_client,false);
                        array_push($adminEmail,$ad);
                    }
                    /** SENT ALSO TO EMAIL CLIENT */
                    
                /** FIND ADMIN EMAIL */
            }catch(Exception $e) {
                LeadspeekUser::where('id','=',$newclientID)->delete();

                Log::warning("Google Spreadsheet Create Client Failed (L472) ErrMsg:" . $e->getMessage() . " CampaignID:" . $tryseraID . ' Campaign Name:' . $campaignName . ' Email:' . $clientOwnerEmail . ' CompanyName:' .  $companyParentName);
                return response()->json(array('result'=>'failed','message'=>'Sorry, system can not create Spreadsheet, please make sure all data valid and try again later. (' . $e->getMessage() . ')','data'=>array()));
                exit;die();
            }
                /** FIND USER COMPANY */
                    // $userlist = User::select('companies.company_name','users.company_id','users.company_parent','users.name','users.email','users.phonenum')
                    //                 ->join('companies','users.company_id','=','companies.id')
                    //                 ->where('users.id','=',$request->userID)
                    //                 ->where('users.active','=','T')
                    //                 ->get();
                                    
                /** FIND USER COMPANY */
                    
                    // $AdminDefault = $this->get_default_admin($userlist[0]['company_parent']);
                    // $AdminDefaultEmail = (isset($AdminDefault[0]['email']))?$AdminDefault[0]['email']:'';
                    
                    // $emailfname = "";
                    // if ($userlist[0]['name'] != "") {
                    //     $_tmp = explode(" ",$userlist[0]['name']);
                    //     $emailfname = $_tmp[0];
                    // }

                    // $details = [
                    //     'defaultadmin' => $AdminDefaultEmail,
                    //     'companyName' => $userlist[0]['company_name'],
                    //     'name'  => $emailfname,
                    //     'campaignName' => $campaignName,
                    //     'TryseraID' =>  $tryseraID,
                    //     'links' => 'https://docs.google.com/spreadsheets/d/' . $spreadSheetID . '/edit?usp=sharing',
                    // ];

                    /** START NEW METHOD EMAIL */
                    $defaultFromName = $productlocatorname . ' Support';
                    $from = [
                        'address' => 'noreply@exactmatchmarketing.com',
                        'name' => $productlocatorname . ' Support',
                        'replyto' => 'support@exactmatchmarketing.com',
                    ];
                    
                    if (strtolower($leadspeekType) == 'local') {
                        $defaultFromName = $productlocalname . ' Support';
                        
                        $from = [
                            'address' => 'noreply@exactmatchmarketing.com',
                            'name' => $productlocalname . ' Support',
                            'replyto' => 'support@exactmatchmarketing.com',
                        ];
                    }

                    $smtpusername = $this->set_smtp_email($userlist[0]['company_parent']);
                    $emailtype = 'em_campaigncreated';

                    $customsetting = $this->getcompanysetting($userlist[0]['company_parent'],$emailtype);
                    $chkcustomsetting = $customsetting;

                    if ($customsetting == '') {
                        $customsetting =  json_decode(json_encode($this->check_email_template($emailtype,$userlist[0]['company_parent'])));
                    }
                    
                    $spreadsheetLink = 'https://docs.google.com/spreadsheets/d/' . $spreadSheetID . '/edit?usp=sharing';
                    if ($spreadSheetID == "") {
                        $spreadsheetLink = "(Failed to create spreadsheet, please check google connection)";
                    }

                    $finalcontent = nl2br($this->filterCustomEmail($userlist,$userlist[0]['company_parent'],$customsetting->content,'','','',$tryseraID,$spreadsheetLink));
                    $finalsubject = $this->filterCustomEmail($userlist,$userlist[0]['company_parent'],$customsetting->subject,'','','',$tryseraID);
                    $finalfrom = $this->filterCustomEmail($userlist,$userlist[0]['company_parent'],$customsetting->fromName,'','','',$tryseraID);

                    $details = [
                        'title' => ucwords($finalsubject),
                        'content' => $finalcontent,
                    ];

                    $from = [
                        'address' => (isset($customsetting->fromAddress) && $customsetting->fromAddress != '')?$customsetting->fromAddress:'noreply@sitesettingsapi.com',
                        'name' => (isset($finalfrom) && $finalfrom != '')?$finalfrom:$defaultFromName,
                        'replyto' => (isset($customsetting->fromReplyto) && $customsetting->fromReplyto != '')?$customsetting->fromReplyto:'support@sitesettingsapi.com',
                    ];
        
                    if ($smtpusername != "" && $chkcustomsetting == "") {
                        $from = [
                            'address' => $smtpusername,
                            'name' => (isset($finalfrom) && $finalfrom != '')?$finalfrom:$defaultFromName,
                            'replyto' => $smtpusername,
                        ];
                    }

                    //$this->send_email($adminEmail,$from,ucwords($finalsubject),$details,array(),'emails.customemail',$userlist[0]['company_parent']);
                    
                    /** START NEW METHOD EMAIL */

                    //$this->send_email($adminEmail,$from,$userlist[0]['company_name'] . ' - ' . $campaignName . ' #' . $tryseraID . ' Google Spreadsheet Link',$details,array(),'emails.spreadsheetlink',$userlist[0]['company_parent']);
                
                /** NOTIFY ADMIN */

                /** SENT TO CLIENT AND ADMIN THE LINK FOR THE PERMISSION */
                
                /** TEMPORARY ADD EMM ADMIN TO NOTIFY */
                $emmEmail = array();
                $details = [
                    'title' => 'Admin Notification',
                    'content' => $companyParentName . $userlist[0]['company_name'] . ' added a new campaign<br/><br/>Campaign Name: ' . $campaignName . '<br/><br/>Campaign ID: #' . $tryseraID,
                ];
                
                //array_push($emmEmail,'serverlogs@sitesettingsapi.com');
                array_push($emmEmail,'carrie@uncommonreach.com');
                //array_push($emmEmail,'daniel@exactmatchmarketing.com');
                
                //$this->send_email($emmEmail,$from,$companyParentName . $userlist[0]['company_name'] . ' added a new campaign - ' . $campaignName . ' #' . $tryseraID,$details,array(),'emails.customemail');
                /** TEMPORARY ADD EMM ADMIN TO NOTUFY */

            }   
        }

        /** CHECK IF THERE IS ALREADY SIMPLIFI ON COMPANY CLIENT */
       
        /** SETUP SIMPLI.FI CAMPAIGN */
            // if ($clientOrganizationID == '' && strtolower($leadspeekType) == 'locator') {
            // //if (false){
            //     /** CREATE ORGANIZATION */
            //     $sifiEMMStatus = "[CLIENT]";
            //     if (config('services.appconf.devmode') === true) {
            //         $sifiEMMStatus = "[CLIENT BETA]";
            //     }

            //     if ($request->companyID != '') {
            //         $companyParent = Company::select('simplifi_organizationid')
            //                             ->where('id','=',$request->companyID)
            //                             ->get();
            //         if(count($companyParent) > 0) {
            //             if ($companyParent[0]['simplifi_organizationid'] != '') {
            //                 $defaultParentOrganization = $companyParent[0]['simplifi_organizationid'];
            //             }
            //         }
            //     }

            //     $clientOrganizationID = $this->createOrganization(trim($request->companyName) . ' ' . $sifiEMMStatus,$defaultParentOrganization);
                
            //     if (trim($clientOrganizationID) == "") {
            //         $clientOrganizationID = $this->createOrganization(trim($request->companyName) . ' ' . $sifiEMMStatus,$defaultParentOrganization);
            //         /** LOG ACTION */
            //         $ipAddress = ($request->header('Clientip') !== null)?$request->header('Clientip'):$request->ip();
            //         $loguser = $this->logUserAction($request->userID,'Create SIFI ORGANIZATION ATTEMPT 2','Organization ID : ' . $clientOrganizationID . ' | CampaignID :' . $tryseraID,$ipAddress);
            //         /** LOG ACTION */

            //     }
            //     /** CREATE ORGANIZATION */
            // } 
        
            /** CREATE A CAMPAIGN */
            if ($clientOrganizationID != '' && strtolower($leadspeekType) == 'locator') {
            //if (false) {
                $_state = explode(",",trim($answers['asec5_4_simplifi']));
                $_cities = explode(",",trim($answers['asec5_4_2_simplifi']));

                $startdatecampaign = (isset($request->startdatecampaign))?$request->startdatecampaign:'';
                $enddatecampaign = (isset($request->enddatecampaign))?$request->enddatecampaign:'';

                $oristartdatecampaign = (isset($request->oristartdatecampaign))?$request->oristartdatecampaign:'';
                $orienddatecampaign = (isset($request->orienddatecampaign))?$request->orienddatecampaign:'';
                
                $_geotarget = array_merge($_state,$_cities);
                $_postalcode = array(); //asec5_3
                $tmp_postcode = explode(',',$answers['asec5_3']);
                foreach($tmp_postcode as $postcode) {
                    if($postcode != '') {
                        array_push($_postalcode,array("postal_code"=>$postcode, "country_code"=>"USA"));
                    }
                }
                
                if (trim($answers['asec5_4']) != "") {
                    if (trim($answers['asec5_4_2']) != "") {
                        $answers['asec5_4_2'] = "," . $answers['asec5_4_2'];
                    }
                }
                //$sifi_campaignname = trim($request->companyName) . ' - ' . trim($answers['asec5_4']) .  trim($answers['asec5_4_2']) . ' - Keyword Locator #' . $tryseraID; 
                $sifi_campaignname =  trim($companyParentName . $request->companyName . '-' . $campaignName . '-KL #' . $tryseraID);

                $_keywords = array(); 
                $tmp_keywords = $campaign_keywords;
                foreach($tmp_keywords as $keyword) {
                    if(trim($keyword) != "") {
                        array_push($_keywords,array("name"=>$keyword, "max_bid"=>""));
                    }
                }

                /** FOR CONTEXTUAL */
                if (is_array($campaign_keywords_contextual)) {
                    $tmp_keywords = $campaign_keywords_contextual;
                    foreach($tmp_keywords as $keyword) {
                        if(trim($keyword) != "") {
                            array_push($_keywords,array("name"=>"!" . trim($keyword), "max_bid"=>""));
                        }
                    }
                }
                /** FOR CONTEXTUAL */

                /** IF NATIONAL TARGETING */
                $_nationaltargeting = 'F';
                if ($answers['asec5_4_0_0'] === true && $answers['asec5_4_0_1'] === false && $answers['asec5_4_0_2'] === false && $answers['asec5_4_0_3'] === false) {
                    $_geotarget = array('8180');
                    $_postalcode = array();
                    $sifi_campaignname = trim($companyParentName . $request->companyName) . '-' . $campaignName . '-NT-KL #' . $tryseraID; 
                    $_nationaltargeting = 'T';
                }
                /** IF NATIONAL TARGETING */

                $newCampaignID = $this->createCampaign($clientOrganizationID,$sifi_campaignname,$_geotarget,$_postalcode,$oristartdatecampaign,$orienddatecampaign);
                
                $this->createBudgetPlans($newCampaignID,$oristartdatecampaign,$orienddatecampaign);
                $this->createKeywords($newCampaignID,$_keywords);
                $this->createDeviceType($newCampaignID,36);

                $creativeGroupID = $this->createCreativeGroups($newCampaignID, trim($userlist[0]['company_name']) . ' Uncommon Reach ads #' . $tryseraID);
                
                $this->createAds(trim($request->companyName),$tryseraID,$clientOrganizationID,$newCampaignID,$creativeGroupID);

                /** UPDATE COMPANY ORGANIZATION ID AND CAMPAIGN ID ON LEADSPEEK USER */
                $newclient->leadspeek_organizationid = $clientOrganizationID;
                $newclient->leadspeek_campaignsid = $newCampaignID;
                $newclient->campaign_startdate = $startdatecampaign;
                $newclient->campaign_enddate = $enddatecampaign;
                $newclient->ori_campaign_startdate = $oristartdatecampaign;
                $newclient->ori_campaign_enddate = $orienddatecampaign;
                $newclient->lp_enddate = $enddatecampaign;
                $newclient->lp_limit_startdate = $startdatecampaign;
                $newclient->national_targeting = $_nationaltargeting;
                $newclient->save();

                $update = Company::find($companyUserID);
                $update->simplifi_organizationid = $clientOrganizationID;
                $update->save();
                /** UPDATE COMPANY ORGANIZATION ID AND CAMPAIGN ID ON LEADSPEEK USER */
            }
            /** CREATE A CAMPAIGN */
        
        /** SETUP SIMPLI.FI CAMPAIGN */

        if(strtolower($leadspeekType) == 'enhance' || strtolower($leadspeekType) == 'b2b') {
            $startdatecampaign = (isset($request->startdatecampaign))?$request->startdatecampaign:'';
            $enddatecampaign = (isset($request->enddatecampaign))?$request->enddatecampaign:'';

            $oristartdatecampaign = (isset($request->oristartdatecampaign))?$request->oristartdatecampaign:'';
            $orienddatecampaign = (isset($request->orienddatecampaign))?$request->orienddatecampaign:'';

            /** IF NATIONAL TARGETING */
            $_nationaltargeting = 'F';
            if ($answers['asec5_4_0_0'] === true && $answers['asec5_4_0_1'] === false && $answers['asec5_4_0_2'] === false && $answers['asec5_4_0_3'] === false) {
                $_nationaltargeting = 'T';
            }
            /** IF NATIONAL TARGETING */

            $newclient->campaign_startdate = $startdatecampaign;
            $newclient->campaign_enddate = $enddatecampaign;
            $newclient->ori_campaign_startdate = $oristartdatecampaign;
            $newclient->ori_campaign_enddate = $orienddatecampaign;
            $newclient->national_targeting = $_nationaltargeting;

            $newclient->save();
        }
       

        if (strtolower($leadspeekType) == 'local') {

                /** SENT TO CLIENT EMBEDDED CODE */
                
                $AdminDefault = $this->get_default_admin($userlist[0]['company_parent']);
                $AdminDefaultEmail = (isset($AdminDefault[0]['email']))?$AdminDefault[0]['email']:'';

                /** GET DOMAIN OR SUBDOMAIN FOR EMBEDDED CODE */
                $datacompany = Company::select('domain','subdomain','status_domain')
                                    ->where('id','=',$request->companyID)
                                    ->get();

                $jsdomain = 'px.0o0o.io/px.min.js';
                if (config('services.appconf.devmode') === true) {
                    $jsdomain = 'api.emmsandbox.com/px.min.js';
                }

                if (count($datacompany) > 0) {
                    $jsdomain = trim($datacompany[0]['subdomain']);
                    if ($datacompany[0]['domain'] != '' && $datacompany[0]['status_domain'] == 'ssl_acquired') {
                        $jsdomain = trim($datacompany[0]['domain']);
                    }
                    if (config('services.appconf.devmode') === true) {
                        $jsdomain = $jsdomain . '/px-sandbox.min.js';
                    }else{
                        $jsdomain = $jsdomain . '/px.min.js';
                    }
                }
                /** GET DOMAIN OR SUBDOMAIN FOR EMBEDDED CODE */

                $defaultdomain = $this->getDefaultDomainEmail($userlist[0]['company_parent']);
                
                $details = [
                    'defaultadmin' => $AdminDefaultEmail,
                    'leadspeek_api_id' => $tryseraID,
                    'jsdomain' => $jsdomain,
                ];

                $from = [
                    'address' => 'noreply@' . $defaultdomain,
                    'name' => $productlocalname . ' Support',
                    'replyto' => 'support@' . $defaultdomain,
                ];

                if (trim($clientOwnerEmail) != "") {
                    $tmp = array();
                    array_push($tmp,$clientOwnerEmail);
                }else{
                    $tmp = explode(PHP_EOL, $request->reportSentTo);
                }

                //$this->send_email($tmp,$from,$campaignName . ' #' . $tryseraID . ' lead generation code for your website',$details,array(),'emails.embeddedcode',$userlist[0]['company_parent']);
                
                /** SENT TO CLIENT EMBEDDED CODE */
        }
        
        /*if($request->reportType == "CSV") {

        }else if ($request->reportType == "Excel") {

        }else if ($request->reportType == "GoogleSheet") {
        }*/
        if ($companyGroupID == '0') {
            $companyGroupID = '';
        }

        $request->CompanyID = $request->companyID;
        //$request->clientID = $request->userID;
        $request->clientID = $newclientID;

        $request->groupCompanyID = $companyGroupID;
        
        /** LOG ACTION */
        $descLogs = '';
        $descArray = [
            "Campaign User Id: {$request->userID}",
            "Campaign Id: {$tryseraID}",
            "Campaign Name: {$campaignName}",
            "Campaign Type: {$leadspeekType}",
            "Admin Notify To: {$_adminNotifyTo}",
            "Phone Enabled: {$phoneenabled}",
            "Home Address Enabled: {$homeaddressenabled}",
            "Re-identification Type: {$reidentificationtype}",
            "Apply Reidentification: {$applyreidentificationall}",
            "Require Email Address: {$requireemailaddress}"
        ];

        if(strtolower($leadspeekType) == 'local'){
            array_push($descArray, "Url Code: " . ($urlCode ?? 'empty'));
            array_push($descArray, "GTM Installed: " . ($gtminstalled ?? 'empty'));
            if($globalviewmode){
                array_push($descArray, "States Type: " . ($leadspeek_locator_state_type ?? 'empty'));
                array_push($descArray, "States Include: " . ($leadspeek_locator_state ?? 'empty'));
                array_push($descArray, "States Exclude: " . ($leadspeek_locator_state_exclude ?? 'empty'));
            }
            array_push($descArray, "Campaign Information Type Local: " . ($newclient->campaign_information_type_local ?? "empty"));            
            
            $advanceInformationDecode = json_decode($advanceInformation, true);
            $listIdadvanceInformation = (is_array($advanceInformationDecode) && isset($advanceInformationDecode['advance']) && is_array($advanceInformationDecode['advance'])) ?
                                        $advanceInformationDecode['advance'] : 
                                        [];
            $listIdadvanceInformation = implode(',', $listIdadvanceInformation);
            array_push($descArray, "List Advanced Information Id: " . ($listIdadvanceInformation));
        } else if (strtolower($leadspeekType) == 'locator'){
            if ($leadspeek_locator_state){
                array_push($descArray, "States: " . ($leadspeek_locator_state ?? 'empty'));
                array_push($descArray, "States Simplifi: " . ($leadspeek_locator_state_simplifi ?? 'empty'));
                array_push($descArray, "Cities: " . ($leadspeek_locator_city ?? 'empty'));
                array_push($descArray, "Cities Simplifi: " . ($leadspeek_locator_city_simplifi ?? 'empty'));
            }

            if($leadspeek_locator_zip){
                array_push($descArray, "Zip Code: " . ($leadspeek_locator_zip ?? 'empty'));
            }

            if($leadspeek_locator_state && $leadspeek_locator_zip){
                array_push($descArray, "Target Location: States, Zip Code");
            } else if ($leadspeek_locator_state){
                array_push($descArray, "Target Location: States");
            } else if ($leadspeek_locator_zip){
                array_push($descArray, "Target Location: Zip Code");
            } else {
                array_push($descArray, "Target Location: National Targeting");
            }

            array_push($descArray, "Campaign End Date: " . ($enddatecampaign ?? 'empty'));
            array_push($descArray, "Keyword: " . ($leadspeek_locator_keyword ?? 'empty'));
            array_push($descArray, "Keyword Contextual: " . ($leadspeek_locator_keyword_contextual ?? 'empty'));
        } else if (strtolower($leadspeekType) == 'enhance'){
            if ($leadspeek_locator_state){
                array_push($descArray, "States: " . ($leadspeek_locator_state ?? 'empty'));
                array_push($descArray, "Cities: " . ($leadspeek_locator_city ?? 'empty'));
            }

            if($leadspeek_locator_zip){
                array_push($descArray, "Zip Code: " . ($leadspeek_locator_zip ?? 'empty'));
            }

            if ($leadspeek_locator_state){
                array_push($descArray, "Target Location: States");
            } else if ($leadspeek_locator_zip){
                array_push($descArray, "Target Location: Zip Code");
            } else {
                array_push($descArray, "Target Location: National Targeting");
            }

            array_push($descArray, "Campaign Information Type: " . ($campaign_information_type ?? 'empty'));
            array_push($descArray, "List Advanced Information Id: " . ($advanceInformation ?? 'empty'));
            array_push($descArray, "Keyword: " . ($leadspeek_locator_keyword ?? 'empty'));
        } else if (strtolower($leadspeekType) == 'b2b') {
            if ($leadspeek_locator_state){
                array_push($descArray, "States: " . ($leadspeek_locator_state ?? 'empty'));
                array_push($descArray, "Cities: " . ($leadspeek_locator_city ?? 'empty'));
            }

            if($leadspeek_locator_zip){
                array_push($descArray, "Zip Code: " . ($leadspeek_locator_zip ?? 'empty'));
            }

            if ($leadspeek_locator_state){
                array_push($descArray, "Target Location: States");
            } else if ($leadspeek_locator_zip){
                array_push($descArray, "Target Location: Zip Code");
            } else {
                array_push($descArray, "Target Location: National Targeting");
            }

            array_push($descArray, "Campaign Information Type: " . ($campaign_information_type ?? 'empty'));
            array_push($descArray, "List Advanced Information Id: " . ($advanceInformation ?? 'empty'));
            array_push($descArray, "Keyword: " . ($leadspeek_locator_keyword ?? 'empty'));
        }

        if($inOpenApi === true) {
            array_push($descArray, "In Open Api");
        }

        $descLogs = implode(" | ", $descArray);
        $ipAddress = ($inOpenApi === true) ? "inopenapi" : ($request->header('Clientip') ?? $request->ip());
        
        $user_id = null;
        if($inOpenApi === true) {
            if(isset($request->companyID) && $request->companyID != '') {
                $user_id = User::where('company_id', $request->companyID)->where('user_type', 'userdownline')->where('active', 'T')->value('id');
            }
        } else {
            $user_id = auth()->user()->id;
        }
        
        $target_userid = isset($request->userID) ? $request->userID : '';
        $this->logUserAction($user_id, "Create campaign", $descLogs, $ipAddress, $target_userid);
        /** LOG ACTION */

        if($inOpenApi === true) {
            return response()->json([
                'leadspeek_api_id' => $tryseraID
            ]);
        } else {
            return $this->getclient($request);
        }
    }

    public function resentspreadsheetlink(Request $request) {
        $clientGoogle = new GoogleSheet($request->companyID,$this->_moduleID,$this->_settingTokenName,'',true);

        /** SENT TO CLIENT AND ADMIN THE LINK FOR THE PERMISSION */
        /** FIND SPREADSHEET ID */
        $spreadSheetID = "";
        $tryseraID = "";
        $campaignName = "";
        $leadspeekType = "";
        $usrID = "";
            $leadsuser = LeadspeekUser::select('spreadsheet_id','leadspeek_api_id','leadspeek_type','campaign_name','user_id')
                                    ->where('id','=',$request->leadspeekID)
                                    ->get();
            if (count($leadsuser) > 0) {
                $spreadSheetID = $leadsuser[0]['spreadsheet_id'];
                $tryseraID = $leadsuser[0]['leadspeek_api_id'];
                $campaignName = $leadsuser[0]['campaign_name'];
                $leadspeekType = $leadsuser[0]['leadspeek_type'];
                $usrID = $leadsuser[0]['user_id'];
            }
        /** FIND SPREADSHEET ID */

        /** FIND ADMIN EMAIL */
            $tmp = User::select('email')->whereIn('id', $request->adminNotifyTo)->get();
            $adminEmail = array();
            foreach($tmp as $ad) {
                /** MAKE SURE THEY ADDED ON SHARED PERMISSION */
                $permission = $clientGoogle->createPermission($spreadSheetID,$ad['email'],'user','writer',false);
                /** MAKE SURE THEY ADDED ON SHARED PERMISSION */
                array_push($adminEmail,$ad['email']);
            }

            /** SENT ALSO TO EMAIL CLIENT */
            $tmp = explode(PHP_EOL, $request->reportSentTo);
            foreach($tmp as $ad) {
                /** MAKE SURE THEY ADDED ON SHARED PERMISSION */
                $permission = $clientGoogle->createPermission($spreadSheetID,$ad,'user','reader',false);
                /** MAKE SURE THEY ADDED ON SHARED PERMISSION */
                array_push($adminEmail,$ad);
            }
            /** SENT ALSO TO EMAIL CLIENT */
            
        /** FIND ADMIN EMAIL */

         /** FIND USER COMPANY */
        $userlist = User::select('companies.company_name','users.company_id','users.company_parent','users.name','users.email','users.phonenum','users.company_root_id')
                ->join('companies','users.company_id','=','companies.id')
                ->where('users.id','=',$usrID)
                ->where('users.active','=','T')
                ->get();  
        /** FIND USER COMPANY */
        
        /** GET PRODUCT NAME */
        $productlocalname = 'Site ID';
        $productlocatorname = 'Search ID';

        $rootcompanysetting = CompanySetting::where('company_id',trim($userlist[0]['company_root_id']))->whereEncrypted('setting_name','rootcustomsidebarleadmenu')->get();
        if (count($rootcompanysetting) > 0) {
            $productname = json_decode($rootcompanysetting[0]['setting_value']);
            $productlocalname = $productname->local->name;
            $productlocatorname = $productname->locator->name;
        }

        if($this->checkwhitelabellingpackage(trim($userlist[0]['company_parent']))) {
            $companysetting = CompanySetting::where('company_id',trim($userlist[0]['company_parent']))->whereEncrypted('setting_name','customsidebarleadmenu')->get();
            if (count($companysetting) > 0) {
                $productname = json_decode($companysetting[0]['setting_value']);
                $productlocalname = $productname->local->name;
                $productlocatorname = $productname->locator->name;
            }
        }
        /** GET PRODUCT NAME */

        // $AdminDefault = $this->get_default_admin($userlist[0]['company_parent']);
        // $AdminDefaultEmail = (isset($AdminDefault[0]['email']))?$AdminDefault[0]['email']:'';
        
        // $emailfname = "";
        // if ($userlist[0]['name'] != "") {
        //     $_tmp = explode(" ",$userlist[0]['name']);
        //     $emailfname = $_tmp[0];
        // }

        // $details = [
        //     'defaultadmin' => $AdminDefaultEmail,
        //     'companyName' => $userlist[0]['company_name'],
        //     'name'  => $emailfname,
        //     'campaignName' => $campaignName,
        //     'TryseraID' =>  $tryseraID,
        //     'links' => 'https://docs.google.com/spreadsheets/d/' . $spreadSheetID . '/edit?usp=sharing',
        // ];

           /** START NEW METHOD EMAIL */
           $defaultFromName = $productlocatorname . ' Support';
           $from = [
               'address' => 'noreply@exactmatchmarketing.com',
               'name' => $productlocatorname . ' Support',
               'replyto' => 'support@exactmatchmarketing.com',
           ];
           
           if (strtolower($leadspeekType) == 'local') {
               $defaultFromName = $productlocalname . ' Support';
               
               $from = [
                   'address' => 'noreply@exactmatchmarketing.com',
                   'name' => $productlocalname . ' Support',
                   'replyto' => 'support@exactmatchmarketing.com',
               ];
           }

           $smtpusername = $this->set_smtp_email($userlist[0]['company_parent']);
           $emailtype = 'em_campaigncreated';

           $customsetting = $this->getcompanysetting($userlist[0]['company_parent'],$emailtype);
           $chkcustomsetting = $customsetting;

           if ($customsetting == '') {
               $customsetting =  json_decode(json_encode($this->check_email_template($emailtype,$userlist[0]['company_parent'])));
           }
           
           $finalcontent = nl2br($this->filterCustomEmail($userlist,$userlist[0]['company_parent'],$customsetting->content,'','','',$tryseraID,'https://docs.google.com/spreadsheets/d/' . $spreadSheetID . '/edit?usp=sharing'));
           $finalsubject = $this->filterCustomEmail($userlist,$userlist[0]['company_parent'],$customsetting->subject,'','','',$tryseraID);
           $finalfrom = $this->filterCustomEmail($userlist,$userlist[0]['company_parent'],$customsetting->fromName,'','','',$tryseraID);

           $details = [
               'title' => ucwords($finalsubject),
               'content' => $finalcontent,
           ];

           $from = [
               'address' => (isset($customsetting->fromAddress) && $customsetting->fromAddress != '')?$customsetting->fromAddress:'noreply@sitesettingsapi.com',
               'name' => (isset($finalfrom) && $finalfrom != '')?$finalfrom:$defaultFromName,
               'replyto' => (isset($customsetting->fromReplyto) && $customsetting->fromReplyto != '')?$customsetting->fromReplyto:'support@sitesettingsapi.com',
           ];

           if ($smtpusername != "" && $chkcustomsetting == "") {
               $from = [
                   'address' => $smtpusername,
                   'name' => (isset($finalfrom) && $finalfrom != '')?$finalfrom:$defaultFromName,
                   'replyto' => $smtpusername,
               ];
           }

           $this->send_email($adminEmail,$from,ucwords($finalsubject),$details,array(),'emails.customemail',$userlist[0]['company_parent']);
           /** START NEW METHOD EMAIL */

            //$this->send_email($adminEmail,$from,$userlist[0]['company_name'] . ' - ' . $campaignName . ' #' . $tryseraID . ' Google Spreadsheet Link',$details,array(),'emails.spreadsheetlink',$userlist[0]['company_parent']);
            
        /** NOTIFY ADMIN */

        /** SENT TO CLIENT AND ADMIN THE LINK FOR THE PERMISSION */

        return response()->json(array("result"=>'success','message'=>'Google Sheet Link has been sent','param'=>array()));
    }

    public function getclient(Request $request) {
        $leadspeekGetCampaignService = App::make(LeadspeekGetCampaignService::class);
        $salt = substr(hash('sha256', env('APP_KEY')), 0, 16);    
        $view = (isset($request->view))?$request->view:'';
        $CompanyID = (isset($request->CompanyID))?$request->CompanyID:'';
        if ($CompanyID == '') {
            $CompanyID = (isset($request->companyID))?$request->companyID:'';
        }
        $PerPage = (isset($request->PerPage)) ? (int) $request->PerPage : 10; 
        $Page = (isset($request->Page))?$request->Page:'';
        $leadspeekID = (isset($request->leadspeekID))?$request->leadspeekID:'';
        $leadspeekType = (isset($request->leadspeekType))?$request->leadspeekType:'';
        $groupCompanyID = (isset($request->groupCompanyID))?$request->groupCompanyID:'';
        $sortby = (isset($request->SortBy) && $request->SortBy != '0')?$request->SortBy:'';
        $order = (isset($request->OrderBy) && $request->OrderBy != '0')?$request->OrderBy:'';
        $searchKey = (isset($request->searchKey))?$request->searchKey:'';
        $CampaignStatus = (isset($request->CampaignStatus))?$request->CampaignStatus:'';
        $clientID = (isset($request->clientID))?$request->clientID:'';
        $isDashboardViewAllCampaign = ($view == 'dashboard' && $clientID == 'view_all') ? true : false;
        // info('getclient', ['view' => $view,'CompanyID' => $CompanyID,'PerPage' => $PerPage,'Page' => $Page,'leadspeekID' => $leadspeekID,'leadspeekType' => $leadspeekType,'groupCompanyID' => $groupCompanyID,'sortby' => $sortby,'order' => $order,'searchKey' => $searchKey,'CampaignStatus' => $CampaignStatus,'clientID' => $clientID]);
        
        if($isDashboardViewAllCampaign) // jika dashboard view all
        {
            // 1. Bangun dummy record
            $defaults = ['id' => 'view_all', 'campaign_name' => 'View All', 'phoneenabled' => 'T', 'homeaddressenabled' => 'T', 'leadspeek_locator_require' => 'FirstName,LastName,MailingAddress,Phone'];
            $columns = $leadspeekGetCampaignService->baseCampaignSelect();
            $dummy = $leadspeekGetCampaignService->buildDummyRow($columns, $defaults);
            // info('getclient if 1.1', ['dummy' => $dummy]);

            // 2. Hitung lifetime total leads dan Hitung last billing total leads
            $lifeTime = $leadspeekGetCampaignService->getLifeTimeTotalLeads(true,'',$leadspeekType,$CompanyID,$groupCompanyID);
            $lastBilling = $leadspeekGetCampaignService->getLastBillingTotalLeads(true,'',$leadspeekType,$CompanyID,$groupCompanyID);
            // info('getclient if 1.2', ['lifeTime' => $lifeTime]);
            // info('getclient if 1.3', ['lastBilling' => $lastBilling]);

            // 3. Set ke dummy row
            $dummy['lifetime_total_leads'] = $lifeTime['lifetime_total_leads'] ?? 0;
            $dummy['agency_lifetime_total_leads_cost'] = $lifeTime['agency_lifetime_total_leads_cost'] ?? 0;
            $dummy['client_lifetime_total_leads_cost'] = $lifeTime['client_lifetime_total_leads_cost'] ?? 0;
            // info('getclient if 1.4', ['lifetime_total_leads' => $dummy['lifetime_total_leads'],'agency_lifetime_total_leads_cost' => $dummy['agency_lifetime_total_leads_cost'],'client_lifetime_total_leads_cost' => $dummy['client_lifetime_total_leads_cost'],]);
            $dummy['total_leads_last_billing'] = $lastBilling['total_leads_last_billing'] ?? 0;
            $dummy['agency_total_cost_since_last_billing'] = $lastBilling['agency_total_cost_since_last_billing'] ?? 0;
            $dummy['client_total_cost_since_last_billing'] = $lastBilling['client_total_cost_since_last_billing'] ?? 0;
            // info('getclient if 1.5', ['total_leads_last_billing' => $dummy['total_leads_last_billing'],'agency_total_cost_since_last_billing' => $dummy['agency_total_cost_since_last_billing'],'client_total_cost_since_last_billing' => $dummy['client_total_cost_since_last_billing'],]);

            // 4. hitung ulang total campaigns
            $getTotalCampaignsDashboardWhenClickCampaign = $leadspeekGetCampaignService->getTotalCampaignsDashboardWhenClickCampaign($CompanyID, $groupCompanyID, $leadspeekType);
            $campaignTotal = $getTotalCampaignsDashboardWhenClickCampaign['campaign_total'] ?? 0;

            // 5. Jadikan paginator manual
            $items = collect([(object) $dummy]);
            $client = new LengthAwarePaginator(
                $items, // items
                $campaignTotal, // total
                1, // per page
                1, // current page
                ['path' => request()->url(), 'query' => request()->query()] // options
            );
            // info('getclient if 1.6', ['client' => $client]);
            return $client;
        }
        
        //get list queue id for enhance
        $yesterday = Carbon::yesterday()->toDateString();

        $latestListQueueSubquery = DB::table('lead_list_queue')
            ->select('leadspeek_api_id', 'list_queue_id')
            ->whereDate('created_at', '=', $yesterday)
            ->latest('created_at')
            ->groupBy('leadspeek_api_id');

        $client = LeadspeekUser::select(
                'leadspeek_users.id','leadspeek_users.report_type','leadspeek_users.leadspeek_type','leadspeek_users.leadspeek_locator_zip','leadspeek_users.leadspeek_locator_desc','leadspeek_users.leadspeek_locator_keyword','leadspeek_locator_keyword_contextual','leadspeek_users.leadspeek_locator_state','leadspeek_users.leadspeek_locator_state_exclude','leadspeek_users.leadspeek_locator_state_type','leadspeek_users.leadspeek_locator_state_simplifi as leadspeek_locator_state_external','leadspeek_users.gtminstalled',
                'leadspeek_users.leadspeek_locator_city','leadspeek_users.leadspeek_locator_city_simplifi as leadspeek_locator_city_external','leadspeek_users.leadspeek_locator_require','leadspeek_users.hide_phone','leadspeek_users.national_targeting','leadspeek_users.location_target','leadspeek_users.start_billing_date','leadspeek_users.lp_invoice_date','leadspeek_users.phoneenabled','leadspeek_users.homeaddressenabled','leadspeek_users.reidentification_type','leadspeek_users.require_email','leadspeek_users.advance_information','leadspeek_users.campaign_information_type','leadspeek_users.campaign_information_type_local',
                'leadspeek_users.file_url','leadspeek_users.report_sent_to','leadspeek_users.admin_notify_to','leadspeek_users.leadspeek_api_id','leadspeek_users.active','leadspeek_users.disabled','leadspeek_users.active_user','leadspeek_users.leadspeek_organizationid as clientorganizationid','leadspeek_users.leadspeek_campaignsid as clientcampaignsid',
                'leadspeek_users.leads_amount_notification','leadspeek_users.total_leads',DB::raw('DATE_FORMAT(leadspeek_users.last_lead_added,"%m-%d-%Y") as last_lead_added'),'leadspeek_users.spreadsheet_id','leadspeek_users.filename','users.customer_payment_id','users.customer_card_id','users.company_parent','users.company_root_id',
                'leadspeek_users.ongoing_leads',DB::raw('DATE_FORMAT(leadspeek_users.last_lead_check,"%d-%m-%Y") as last_lead_check'),'leadspeek_users.lifetime_cost','leadspeek_users.cost_perlead','leadspeek_users.lp_max_lead_month','leadspeek_users.lp_min_cost_month','leadspeek_users.paymentterm','leadspeek_users.continual_buy_options','leadspeek_users.topupoptions','leadspeek_users.leadsbuy','leadspeek_users.stopcontinual','leadspeek_users.platformfee','leadspeek_users.lp_limit_startdate','leadspeek_users.lp_enddate',
                'leadspeek_users.report_frequency_id','leadspeek_users.lp_limit_leads','leadspeek_users.enable_minimum_limit_leads','leadspeek_users.minimum_limit_leads','leadspeek_users.lp_limit_freq','users.id as user_id','users.name','users.email','users.phonenum','companies.company_name','companies.id as company_id','leadspeek_users.group_company_id','leadspeek_users.campaign_name','leadspeek_users.campaign_startdate','leadspeek_users.campaign_enddate','leadspeek_users.ori_campaign_startdate','leadspeek_users.ori_campaign_enddate','leadspeek_users.url_code','leadspeek_users.url_code_thankyou','leadspeek_users.url_code_ads',
                'leadspeek_users.embeddedcode_crawl','leadspeek_users.embedded_status','leadspeek_users.questionnaire_answers','leadspeek_users.trysera','leadspeek_users.trysera','leadspeek_users.archived','leadspeek_users.timezone','leadspeek_users.applyreidentificationall',
                'users.paymentterm as m_paymentterm','users.platformfee as m_platformfee','users.lp_max_lead_month as m_lp_max_lead_month','users.lp_min_cost_month as m_lp_min_cost_month','users.cost_perlead as m_cost_perlead','users.lp_limit_leads as m_lp_limit_leads','users.lp_limit_freq as m_lp_limit_freq','users.lp_limit_startdate as m_lp_limit_startdate','users.lp_enddate as m_lp_enddate','leadspeek_users.sendgrid_is_active as sendgrid_is_active', 'leadspeek_users.googlesheet_is_active as googlesheet_is_active','leadspeek_users.sendgrid_action as sendgrid_action','leadspeek_users.sendgrid_list as sendgrid_list',
                'leadspeek_users.ghl_is_active','leadspeek_users.mbp_is_active','leadspeek_users.clickfunnels_is_active','users.payment_status', 'leadspeek_users.created_at',
                'leadspeek_users.sendjim_is_active as sendjim_is_active','leadspeek_users.sendjim_tags as sendjim_tags',
                'leadspeek_users.sendjim_quicksend_is_active as sendjim_quicksend_is_active','leadspeek_users.sendjim_quicksend_templates as sendjim_quicksend_templates',
                'leadspeek_users.zap_is_active as zap_is_active', 'leadspeek_users.agencyzoom_is_active as agencyzoom_is_active',
                'users.payment_status', 'leadspeek_users.created_at',
                'latest_list_queue.list_queue_id','leadspeek_users.frequency_capping_impressions','leadspeek_users.frequency_capping_hours','leadspeek_users.max_bid','leadspeek_users.monthly_budget','leadspeek_users.daily_budget','leadspeek_users.goal_type','leadspeek_users.goal_value','leadspeek_users.cpa_view_thru_per','leadspeek_users.cpa_click_thru_per','leadspeek_users.view_attribution_window','leadspeek_users.click_attribution_window','leadspeek_users.device_type','leadspeek_users.simplifi_selected_campaign','leadspeek_users.simplifi_selected_audience','leadspeek_users.agency_markup','leadspeek_users.simplifi_selected_media','leadspeek_users.audience_status','leadspeek_users.ads_upload_status','leadspeek_users.media_type','leadspeek_users.destination_url',
                'leadspeek_business.business_type_id','leadspeek_business.fetch_report_status',DB::raw("CONVERT(AES_DECRYPT(FROM_bASE64(leadspeek_business.business_name), '$salt') USING utf8mb4) as business_name"),DB::raw("CONVERT(AES_DECRYPT(FROM_bASE64(leadspeek_business.website), '$salt') USING utf8mb4) as website"),'leadspeek_business.business_industry_id',DB::raw("CONVERT(AES_DECRYPT(FROM_bASE64(leadspeek_business.business_description), '$salt') USING utf8mb4) as business_description"),DB::raw("CONVERT(AES_DECRYPT(FROM_bASE64(leadspeek_business.business_competitors), '$salt') USING utf8mb4) as business_competitors"),'leadspeek_business.final_analyze_customers','leadspeek_business.final_expression','leadspeek_users.leadspeek_latitude','leadspeek_users.leadspeek_latitude','leadspeek_users.leadspeek_longitude','leadspeek_users.leadspeek_radius'
            )
            ->join('users','leadspeek_users.user_id','=','users.id')
            ->join('companies','users.company_id','=','companies.id')
            ->leftjoin('companies_integration_settings','leadspeek_users.company_id','=','companies_integration_settings.company_id')
            ->leftJoin('list_and_tags', function ($join) {
                        $join->on('leadspeek_users.company_id', '=', 'list_and_tags.company_id')
                            ->on('leadspeek_users.id', '=', 'list_and_tags.campaign_id');
                    })
                    ->addSelect('list_and_tags.kartra_is_active')
            ->leftJoinSub(
                $latestListQueueSubquery,
                'latest_list_queue',
                'leadspeek_users.leadspeek_api_id',
                '=',
                'latest_list_queue.leadspeek_api_id'
            )
            ->leftJoin('leadspeek_business', 'leadspeek_business.leadspeek_api_id', '=', 'leadspeek_users.leadspeek_api_id')
            //->leftjoin('company_groups','leadspeek_users.group_company_id','=','company_groups.id')
            ->where('leadspeek_users.module_id','=', $this->_moduleID)
            ->where('leadspeek_users.company_id','=',$CompanyID)
            ->where('leadspeek_users.archived','=','F')
            ->where('users.active','=','T');
            //->where('companies_integration_settings.integration_slug','=','sendgrid');
        
        if($clientID != '' && $clientID != '0'){
            $client->where('leadspeek_users.id','=',$clientID);
        }elseif($leadspeekID != ''){
            $client->where('leadspeek_users.id','=',$leadspeekID);
        }

        if(trim($leadspeekType) != '' && trim($leadspeekType) != 'all'){
            $client->where('leadspeek_users.leadspeek_type','=',trim($leadspeekType));
        }

        if(trim($groupCompanyID) != '' && trim($groupCompanyID) != 'all'){
            // $client->where('leadspeek_users.group_company_id','=',trim($groupCompanyID));
            $client->where('leadspeek_users.user_id','=',trim($groupCompanyID));
        }

        if (trim($searchKey) != ''){
            $client->where(function($query) use ($searchKey,$salt){
                $query->orWhere(DB::raw("CONVERT(AES_DECRYPT(FROM_bASE64(companies.company_name), '" . $salt . "') USING utf8mb4)"),'like','%' . $searchKey . '%')
                    ->orWhere(DB::raw("CONVERT(AES_DECRYPT(FROM_bASE64(leadspeek_users.campaign_name), '" . $salt . "') USING utf8mb4)"),'like','%' . $searchKey . '%')
                    ->orWhere('leadspeek_users.leadspeek_api_id','like','%' . $searchKey . '%')
                    ->orWhere('latest_list_queue.list_queue_id', 'like', '%' . $searchKey . '%'); 
            });
        }

        
        if(trim($order) != ''){
            if (trim($order) == 'descending') {
                $order = "DESC";
            }else{
                $order = "ASC";
            }
        }

        if($CampaignStatus == 'all'){
            $client->where(function($query){
                $query->where(function($query) {
                            $query->where('leadspeek_users.active_user', 'T')
                            ->orWhere('leadspeek_users.active_user', 'F');
                        })
                        ->where(function($query) {
                            $query->where('leadspeek_users.active', 'T')
                                ->orWhere('leadspeek_users.active', 'F');
                        })
                        ->where(function($query) {
                            $query->where('leadspeek_users.disabled', 'T')
                                ->orWhere('leadspeek_users.disabled', 'F');
                        });
            });
        }elseif($CampaignStatus == 'play'){
            $client->where(function($query){
                $query->where(function($subquery){
                    $subquery->where('leadspeek_users.active_user', 'T')
                        ->where('leadspeek_users.disabled', 'F')
                        ->where('leadspeek_users.active', 'T');
                })->orWhere(function($subquery){
                    $subquery->where('leadspeek_users.active_user', 'T')
                        ->where('leadspeek_users.disabled', 'F')
                        ->where('leadspeek_users.active', 'F');
                });
            });
        }elseif($CampaignStatus == 'paused'){
            $client->where(function($query){
                $query->where('leadspeek_users.active', 'F')
                        ->where('leadspeek_users.active_user', 'T')
                        ->where('leadspeek_users.disabled', 'T');
            });
        }elseif($CampaignStatus == 'stop'){
            $client->where(function($query){
                $query->where('leadspeek_users.active', 'F')
                        ->where('leadspeek_users.active_user', 'F')
                        ->where('leadspeek_users.disabled', 'T');
            });
        }
        
        if(trim($sortby) != ''){
            if(trim($sortby) == "company_name"){
                $client->orderByEncrypted('companies.company_name',$order);
            }elseif(trim($sortby) == "campaign_name"){
                $client->orderByEncrypted('leadspeek_users.campaign_name',$order);
            }elseif(trim($sortby) == "leadspeek_api_id"){
                $client->orderBy(DB::raw('CAST(leadspeek_users.leadspeek_api_id AS DECIMAL)'),$order);
            }elseif(trim($sortby) == "total_leads"){
                $client->orderBy(DB::raw('CAST(leadspeek_users.total_leads AS DECIMAL)'),$order);
            }elseif(trim($sortby) == "last_lead_added"){
                $client->orderBy(DB::raw('CAST(leadspeek_users.last_lead_added AS DATETIME)'),$order);
            }elseif(trim($sortby) == "created_at"){
                $client->orderBy(DB::raw('CAST(leadspeek_users.created_at AS DATETIME)'),$order);
            }
        }else{
            $client->orderBy(DB::raw('CAST(leadspeek_users.last_lead_added AS DATETIME)'),'DESC');
        }

        if($Page == ''){
            // info('getclient if aa');
            $client = $client->get();
        }else{
            // info('getclient if bb');
            $client = $client->paginate($PerPage, ['*'], 'page', $Page);
        }

        $client->map(function ($item){
            if(!empty($item->sendgrid_action)){
                $item->sendgrid_action = explode(',', $item->sendgrid_action);
                return $item;
            }
            return array();
        });    
        $client->map(function ($item1){
            if(!empty($item1->sendgrid_list)){
                $item1->sendgrid_list = explode(',', $item1->sendgrid_list);
                return $item1;
            }
            return array();
        });

        if(($clientID != '' && $clientID != '0') && (trim($leadspeekType) != '' && trim($leadspeekType) == 'locator')){
            /** CHECK FOR START DATE AND END DATE IF NOT FILLED WILL FOLLOW THE LAST BUDGET PLAN*/
                if($client[0]['campaign_startdate'] == '0000-00-00' || $client[0]['campaign_enddate'] == '0000-00-00'){
                    $_campaignID = $client[0]['clientcampaignsid'];
                    if(trim($_campaignID) != ''){
                        $budgetplan = $this->getDefaultBudgetPlan($_campaignID);
                        if(count($budgetplan->budget_plans) > 0){
                            $count = count($budgetplan->budget_plans) - 1;
                            $client[0]['campaign_startdate'] = $budgetplan->budget_plans[$count]->start_date;
                            $client[0]['campaign_enddate'] = $budgetplan->budget_plans[$count]->end_date;
                        }
                    }
                }
            /** CHECK FOR START DATE AND END DATE IF NOT FILLED WILL FOLLOW THE LAST BUDGET PLAN*/
        }

        /* SETUP DEVICE TYPE AND DEVICE NAMES */
        $available_device_type = [
            'desktop' => 36,
            'tablet' => 37,
            'mobile' => 35,
            'connected tv' => 1
        ];
        $device_names = [
            'desktop' => 'Desktop',
            'tablet' => 'Tablet',
            'mobile' => 'Mobile',
            'connected tv' => 'Connected TV',
        ];
        /* SETUP DEVICE TYPE AND DEVICE NAMES */

        /** SYNC TOTAL LEADS WITH THE REPORT TABLE */
        foreach($client as $a => $cl) 
        {
            // /** GET TOTAL LEADS SINCE BILLING */
            // $reportotal = LeadspeekReport::select(DB::raw("COUNT(*) as total"),DB::raw("SUM(price_lead) as pricetotal"))
            //                             ->where('leadspeek_api_id','=',$cl['leadspeek_api_id'])
            //                             ->where('active','=','T')
            //                             ->where(DB::raw('DATE_FORMAT(clickdate,"%Y%m%d")'),'>=',date('Ymd',strtotime($cl['start_billing_date'])))
            //                             ->get();
            // if(count($reportotal) > 0) {
            //     $client[$a]['total_leads_sincebilling'] = $reportotal[0]['total'];
            //     $client[$a]['total_cost_sincebilling'] = $reportotal[0]['pricetotal'];
            // }else{
            //     $client[$a]['total_leads_sincebilling'] = '0';
            //     $client[$a]['total_cost_sincebilling'] = '0';
            // }
            // /** GET TOTAL LEADS SINCE BILLING */

            /* ADD 1 MONTH LP_INVOICE_DATE */
            $client[$a]['lp_invoice_date_next_month'] = "";
            if($client[$a]['leadspeek_type'] == 'local'){
                if($client[$a]['active'] == 'F' && $client[$a]['disabled'] == 'T' && $client[$a]['active_user'] == 'F'){ // jika status stop pakai waktu sekarang
                    $client[$a]['lp_invoice_date_next_month'] = Carbon::now()->addMonthsNoOverflow()->setTimezone('America/Chicago')->format('Y-m-d');
                }elseif(empty($client[$a]['lp_invoice_date'])){ // jika status play, paused on play, paused dan lp_invoice_date null, maka check invoice terakhir
                    $invoice = LeadspeekInvoice::where('leadspeek_api_id', $client[$a]['leadspeek_api_id'])->where('invoice_type', 'campaign')->orderBy('id', 'desc')->first();
                    if($invoice){
                        $lp_invoice_date = $invoice->invoice_date;
                        $client[$a]['lp_invoice_date_next_month'] = Carbon::parse($lp_invoice_date)->addMonthNoOverflow()->setTimezone('America/Chicago')->format('Y-m-d');
                    }
                }else{ // jika status play, paused on play, paused dan lp_invoice_date tidak null, maka pakai lp_invoice_date
                    $client[$a]['lp_invoice_date_next_month'] = Carbon::parse($client[$a]['lp_invoice_date'])->addMonthsNoOverflow()->setTimezone('America/Chicago')->format('Y-m-d');
                }
            }

            /* ADD 1 MONTH LP_INVOICE_DATE */

            /** SET FOR TARGET LOCATION*/
            $client[$a]['target_nt'] = false;
            $client[$a]['target_state'] = false;
            $client[$a]['target_city'] = false;
            $client[$a]['target_zip'] = false;
            $client[$a]['selects_state'] = array();
            $client[$a]['selects_citylist'] = array();
            $client[$a]['selects_city'] = array();
            $client[$a]['selects_citystate'] = array();
            $client[$a]['selects_citystatelist'] = array();
            $client[$a]['leadspeek_locator_keyword_bulk'] = $cl['leadspeek_locator_keyword']; 
            $client[$a]['continual_buy_options'] = $cl['continual_buy_options'] === 'Monthly' ? true : false;
            /** SET FOR TARGET LOCATION*/

            /** CHECK IF ORI CAMPAIGN START AND END DATE IS STILL EMPTY THEN MAKE IT SAME WITH EXISTING */
            if($cl['ori_campaign_startdate'] == "0000-00-00 00:00:00"){
                $client[$a]['ori_campaign_startdate'] = $cl['campaign_startdate'];
            }
            if($cl['ori_campaign_enddate'] == "0000-00-00 00:00:00"){
                $client[$a]['ori_campaign_enddate'] = $cl['campaign_enddate'];
            }
            if($cl['lp_enddate'] == 'null' || $cl['lp_enddate'] == "" || is_null($cl['lp_enddate'])){
                $client[$a]['lp_enddate'] = ($cl['ori_campaign_enddate'] == "0000-00-00 00:00:00")?$cl['campaign_enddate']:$cl['ori_campaign_enddate'];
            }
            if(trim($cl['lp_enddate']) == '0000-00-00 23:59:59' || trim($cl['lp_enddate']) == '0000-00-00 00:00:00'){
                $client[$a]['lp_enddate'] = '';
            }
            if($client[$a]['ori_campaign_enddate'] == "0000-00-00 00:00:00"){
                $client[$a]['ori_campaign_enddate'] = "";
            }
            if($client[$a]['campaign_enddate'] == "0000-00-00 00:00:00"){
                $client[$a]['campaign_enddate'] = "";
            }
            /** CHECK IF ORI CAMPAIGN START AND END DATE IS STILL EMPTY THEN MAKE IT SAME WITH EXISTING */

            /* IF VIEW IN DASHBOARD */
            if($view === 'dashboard') 
            {
                // info('getclient foreach dashboard 3.1');
                /* GET agency_lifetime_total_leads, GET agency_lifetime_total_leads_cost, GET agency_lifetime_total_leads_profit */
                $getLifeTimeTotalLeads = $leadspeekGetCampaignService->getLifeTimeTotalLeads(false, $cl['leadspeek_api_id']);
                $client[$a]['lifetime_total_leads'] = $getLifeTimeTotalLeads['lifetime_total_leads'] ?? 0;
                $client[$a]['agency_lifetime_total_leads_cost'] = $getLifeTimeTotalLeads['agency_lifetime_total_leads_cost'] ?? 0;
                $client[$a]['client_lifetime_total_leads_cost'] = $getLifeTimeTotalLeads['client_lifetime_total_leads_cost'] ?? 0;
                // info('getclient foreach dashboard 3.2', ['lifetime_total_leads' => $client[$a]['lifetime_total_leads'],'agency_lifetime_total_leads_cost' => $client[$a]['agency_lifetime_total_leads_cost'],'client_lifetime_total_leads_cost' => $client[$a]['client_lifetime_total_leads_cost'],]);
                /* GET agency_lifetime_total_leads, GET agency_lifetime_total_leads_cost, GET agency_lifetime_total_leads_profit */            

                /* GET agency_total_leads_last_billing, GET agency_total_cost_since_last_billing, GET agency_total_cost_since_last_billing_profit */
                $getLastBillingTotalLeads = $leadspeekGetCampaignService->getLastBillingTotalLeads(false, $cl['leadspeek_api_id']);
                $client[$a]['total_leads_last_billing'] = $getLastBillingTotalLeads['total_leads_last_billing'] ?? 0;
                $client[$a]['agency_total_cost_since_last_billing'] = $getLastBillingTotalLeads['agency_total_cost_since_last_billing'] ?? 0;
                $client[$a]['client_total_cost_since_last_billing'] = $getLastBillingTotalLeads['client_total_cost_since_last_billing'] ?? 0;
                // info('getclient foreach dashboard 3.3', ['total_leads_last_billing' => $client[$a]['total_leads_last_billing'],'agency_total_cost_since_last_billing' => $client[$a]['agency_total_cost_since_last_billing'],'client_total_cost_since_last_billing' => $client[$a]['client_total_cost_since_last_billing'],]);
                /* GET agency_total_leads_last_billing, GET agency_total_cost_since_last_billing, GET agency_total_cost_since_last_billing_profit */
            }
            /* IF VIEW IN DASHBOARD */

            /* IF VIEW IN CAMPAIGN MANAGEMENT */
            elseif($view === 'campaign') 
            {
                // info('getclient foreach campaign 4.1');

                /* FORCE ENABLE MINIMUM LIMIT LEADS TO 'F' */
                $client[$a]['enable_minimum_limit_leads'] = 'F';
                /* FORCE ENABLE MINIMUM LIMIT LEADS TO 'F' */

                /* GET TOTAL LEADS AND COST */
                $reportotal = LeadspeekReport::select(DB::raw("COUNT(*) as total"),DB::raw("SUM(price_lead) as pricetotal"))
                                            ->where('leadspeek_api_id','=',$cl['leadspeek_api_id'])
                                            ->where('active','=','T')
                                            ->get();
                if(count($reportotal) > 0) {
                    $client[$a]['total_leads'] = $reportotal[0]['total'];
                    $client[$a]['total_cost'] = $reportotal[0]['pricetotal'];
                }else{
                    $client[$a]['total_leads'] = '0';
                    $client[$a]['total_cost'] = '0';
                }

                if($client[$a]['leadspeek_type'] == 'predict') {
                    $totalLeadsPredict = LeadspeekPredictReport::where('leadspeek_api_id', $cl['leadspeek_api_id'])->sum('total_leads');
                    $client[$a]['total_leads'] = $totalLeadsPredict ?? 0;
                }
                /* GET TOTAL LEADS AND COST */

                /** GET YESTERDAY LEADS */
                $yesterday = date("Ymd", strtotime( '-1 days' ) );
                $yesterdaytotal = LeadspeekReport::select(DB::raw("COUNT(*) as total"))
                                            ->where(DB::raw('DATE_FORMAT(clickdate,"%Y%m%d")'),'=',$yesterday)
                                            ->where('leadspeek_api_id','=',$cl['leadspeek_api_id'])
                                            ->where('active','=','T')
                                            ->get();
                if(count($yesterdaytotal) > 0) {
                    $client[$a]['yerterday_leads'] = $yesterdaytotal[0]['total'];
                }else{
                    $client[$a]['yerterday_leads'] = 0;
                }
                /** GET YESTERDAY LEADS */

                /** GET YESTERDAY PREVIOUS LEADS */
                $yesterdaypreviousday = date("Ymd", strtotime( '-2 days' ) );
                $yesterdayprevioustotal = LeadspeekReport::select(DB::raw("COUNT(*) as total"))
                                            ->where(DB::raw('DATE_FORMAT(clickdate,"%Y%m%d")'),'=',$yesterdaypreviousday)
                                            ->where('leadspeek_api_id','=',$cl['leadspeek_api_id'])
                                            ->where('active','=','T')
                                            ->get();
                if(count($yesterdayprevioustotal) > 0) {
                    $client[$a]['yerterday_previous_leads'] = $yesterdayprevioustotal[0]['total'];
                }else{
                    $client[$a]['yerterday_previous_leads'] = 0;
                }
                /** GET YESTERDAY PREVIOUS LEADS */

                /** GET LIST QUEUE ID FOR ENHANCE */
                if (!empty($cl['list_queue_id']) && isset($cl['list_queue_id'])) {
                    $client[$a]['list_queue_id'] = $cl['list_queue_id'];

                }else {
                    $client[$a]['list_queue_id'] = '';
                }
                /** GET LIST QUEUE ID FOR ENHANCE */

                /* CHANGE ADVANCE INFORMATION TO ARRAY */
                if ($client[$a]['leadspeek_type'] == 'enhance' || $client[$a]['leadspeek_type'] == 'b2b') {
                    if(!empty($client[$a]['advance_information']) && trim($client[$a]['advance_information']) != '') {
                        $client[$a]['advance_information'] = explode(',', $client[$a]['advance_information']);
                    } else {
                        $client[$a]['advance_information'] = [];
                        $client[$a]['b2b_information'] = [];
                    }
                }

                if ($client[$a]['leadspeek_type'] == 'local') {
                   if(is_string($client[$a]['advance_information']) && !empty($client[$a]['advance_information']) && trim($client[$a]['advance_information']) != '') {
                        $information = json_decode($client[$a]['advance_information']);
                        if(isset($information->advance)){
                            $client[$a]['advance_information'] = $information->advance;
                        }else {
                            $client[$a]['advance_information'] = [];
                        }
                        
                        if(isset($information->b2b)){
                            $client[$a]['b2b_information'] = $information->b2b;
                        }else {
                            $client[$a]['b2b_information'] = [];
                        }
                    } else {
                        $client[$a]['advance_information'] = [];
                        $client[$a]['b2b_information'] = [];
                        
                        /* IF PREPAID WHEN advance_information IN leadspeek_users EMPTY, CHECK AGAIN  IN topup_campaigns */
                        if($client[$a]['paymentterm'] == "Prepaid")  
                        {
                            $topupProgress = Topup::where('leadspeek_api_id', $client[$a]['leadspeek_api_id'])
                                                  ->where('topup_status', 'progress')                                          
                                                  ->orderBy('id', 'ASC')
                                                  ->first();
                            // info("IF PREPAID CHECK AGAIN ADVANCE_INFORMATION IN TOPUP PROGRESS", ['topupProgress' => $topupProgress, 'leadspeek_api_id' => $client[$a]['leadspeek_api_id']]);
                            if(!empty($topupProgress)) 
                            {
                                $campaign_information_type_local = !empty($topupProgress->campaign_information_type_local) ? explode(',', $topupProgress->campaign_information_type_local) : [];
                                $information = !empty($topupProgress->advance_information) ? json_decode($topupProgress->advance_information) : '';
                                // info('topup available 1', ['campaign_information_type_local' => $topupProgress->campaign_information_type_local, 'campaign_information_type_local_array' => $campaign_information_type_local,'information' => $information,'in_array_advanced' => in_array('advanced', $campaign_information_type_local),'in_array_b2b' => in_array('b2b', $campaign_information_type_local),'isset_advance' => isset($information->advance),'isset_b2b' => isset($information->b2b)]);

                                if(in_array('advanced', $campaign_information_type_local) && isset($information->advance)) 
                                {
                                    $client[$a]['advance_information'] = $information->advance;
                                    // info('topup advanced 1', ['advance_information' => $client[$a]['advance_information']]);
                                } 
                                else 
                                {
                                    $topupQueue = Topup::where('leadspeek_api_id', $client[$a]['leadspeek_api_id'])
                                                       ->where('topup_status', 'queue')
                                                       ->orderBy('id', 'ASC')
                                                       ->first();
                                    // info("CHECK AGAIN ADVANCE_INFORMATION IN TOPUP QUEUE", ['topupQueue' => $topupQueue, 'leadspeek_api_id' => $client[$a]['leadspeek_api_id']]);
                                    if(!empty($topupQueue))
                                    {
                                        $campaign_information_type_local = !empty($topupQueue->campaign_information_type_local) ? explode(',', $topupQueue->campaign_information_type_local) : [];
                                        $information = !empty($topupQueue->advance_information) ? json_decode($topupQueue->advance_information) : '';
                                        // info('topup available 2', ['campaign_information_type_local' => $topupProgress->campaign_information_type_local, 'campaign_information_type_local_array' => $campaign_information_type_local,'information' => $information,'in_array_advanced' => in_array('advanced', $campaign_information_type_local),'in_array_b2b' => in_array('b2b', $campaign_information_type_local),'isset_advance' => isset($information->advance),'isset_b2b' => isset($information->b2b)]);
                                    
                                        if(in_array('advanced', $campaign_information_type_local) && isset($information->advance)) 
                                        {
                                            $client[$a]['advance_information'] = $information->advance;
                                            // info('topup advanced 2', ['advance_information' => $client[$a]['advance_information']]);
                                        }
                                    }
                                }
                            }
                        }
                        /* IF PREPAID WHEN advance_information IN leadspeek_users EMPTY, CHECK AGAIN  IN topup_campaigns */
                    }
                }

                if (is_string($client[$a]['campaign_information_type_local']) && !empty($client[$a]['campaign_information_type_local']) && $client[$a]['campaign_information_type_local'] !== '') {
                    $client[$a]['campaign_information_type_local'] = explode(',',$client[$a]['campaign_information_type_local']);
                }else {
                    $client[$a]['campaign_information_type_local'] = ['basic'];
                }
                /* CHANGE ADVANCE INFORMATION TO ARRAY */

                /** CHECK IF CAMPAIGN EVER STARTED */
                $client[$a]['campaign_never_started'] = 'F';

                $checkUserLog = UserLog::where('action', 'Campaign activate')
                                        ->where('description','like','campaign id:%' . $cl['leadspeek_api_id'] . '%')
                                        ->first();
                if (!$checkUserLog && $client[$a]['total_leads'] == '0') {
                    if (date('YmdHi',strtotime($client[$a]['start_billing_date'])) == date('YmdHi',strtotime($client[$a]['created_at']))) {
                        $client[$a]['campaign_never_started'] = 'T';
                    }
                }
                /** CHECK IF CAMPAIGN EVER STARTED */

                /* FILTER CAMPAIGN ONLY DRAFT FOR LEADSPEEK_TYPE SIMPLIFI */
                if (
                    ($leadspeekType == 'simplifi') &&
                    (
                        ($CampaignStatus == 'draft' && $client[$a]['campaign_never_started'] == "F") || 
                        ($CampaignStatus == 'stop' && $client[$a]['campaign_never_started'] == "T")
                    )
                ) {
                    unset($client[$a]);
                    continue;
                }
                /* FILTER CAMPAIGN ONLY DRAFT FOR LEADSPEEK_TYPE SIMPLIFI */

                // SIMPLIFI CAMPAIGN ATTRIBUTE
                $client[$a]['simplifi_selected_campaign'] = !empty($client[$a]['simplifi_selected_campaign']) && is_string($client[$a]['simplifi_selected_campaign'])
                    ? array_map('intval', explode(',', $client[$a]['simplifi_selected_campaign']))
                    : [];

                $client[$a]['simplifi_selected_audience'] = !empty($client[$a]['simplifi_selected_audience']) && is_string($client[$a]['simplifi_selected_audience'])
                    ? array_map('intval', explode(',', $client[$a]['simplifi_selected_audience']))
                    : [];

                $client[$a]['simplifi_selected_media'] = !empty($client[$a]['simplifi_selected_media']) && is_string($client[$a]['simplifi_selected_media'])
                    ? array_map('intval', explode(',', $client[$a]['simplifi_selected_media']))
                    : [];

                // Get conversion data from local database (following audience pattern)
                $conversion_data = LeadspeekConversionCampaign::where('leadspeek_api_id', $client[$a]['leadspeek_api_id'])
                    ->where('conversion_type', 'site')
                    ->first();
                
                if ($conversion_data) {
                    $client[$a]['conversion_type_ids'] = !empty($conversion_data->conversion_type_ids)
                        ? array_values(array_filter(array_map('intval', explode(',', $conversion_data->conversion_type_ids))))
                        : [];
                    // Infer conversion_enabled from conversion_type_ids (not empty = enabled)
                    $client[$a]['conversion_enabled'] = !empty($client[$a]['conversion_type_ids']);
                    $client[$a]['conversion_segment_id'] = $conversion_data->simplifi_segment_id;
                } else {
                    $client[$a]['conversion_enabled'] = false;
                    $client[$a]['conversion_segment_id'] = null;
                    $client[$a]['conversion_type_ids'] = [];
                }

                // Get geo conversion data from local database
                $geo_conversion_data = LeadspeekConversionCampaign::where('leadspeek_api_id', $client[$a]['leadspeek_api_id'])
                    ->where('conversion_type', 'geo')
                    ->first();
                
                if ($geo_conversion_data) {
                    $client[$a]['geo_conversion_fence_ids'] = !empty($geo_conversion_data->simplifi_geo_fence_id)
                        ? array_values(array_filter(array_map('intval', explode(',', $geo_conversion_data->simplifi_geo_fence_id))))
                        : [];
                    // Infer geo_conversion_enabled from geo_fence_ids (not empty = enabled)
                    $client[$a]['geo_conversion_enabled'] = !empty($client[$a]['geo_conversion_fence_ids']);
                } else {
                    $client[$a]['geo_conversion_enabled'] = false;
                    $client[$a]['geo_conversion_fence_ids'] = [];
                }

                $selected_array = [];
                if (!empty($client[$a]['device_type']) && is_string($client[$a]['device_type'])) {
                    $selected_array = json_decode($client[$a]['device_type'], true);
                }

                $selected_array = array_map('strtolower', $selected_array ?? []);

                $result = [];
                foreach ($available_device_type as $name => $id) {
                    $result[] = [
                        'name' => $device_names[$name] ? $device_names[$name] : ucwords($name),
                        'val'  => in_array($name, $selected_array)
                    ];
                }
                $client[$a]['device_types'] = $result;
                // SIMPLIFI CAMPAIGN ATTRIBUTE

                // PREDICT CUSTOMER
                if ($client[$a]['leadspeek_type'] == 'predict') {
                    $predictService = App::make(PredictService::class);
                    $response = $predictService->getCustomersById($client[$a]['business_industry_id']);
                    $customers = $response['customers'] ?? [];
                    $client[$a]['predict_customers'] = $customers;
                }
                // PREDICT CUSTOMER

                // PREDICT DECODE ANALYZE CUSTOMER
                if (!empty($client[$a]['final_analyze_customers']) && is_string($client[$a]['final_analyze_customers'])) {
                    $client[$a]['final_analyze_customers'] = json_decode($client[$a]['final_analyze_customers'], true);
                } else {
                    $client[$a]['final_analyze_customers'] = null;
                }       
                // PREDICT DECODE ANALYZE CUSTOMER
            }
            /* IF VIEW IN CAMPAIGN MANAGEMENT */
        }
            
        /** SYNC TOTAL LEADS WITH THE REPORT TABLE */
        if(trim($sortby) == "total_leads"){
            $client = $client->toArray();

            $total_leads = array();
            foreach ($client['data'] as $key => $row){
                $total_leads[$key] = $row['total_leads'];
            }

            if ($order == "DESC") {
                array_multisort($total_leads,SORT_DESC,$client['data']);
            }else{
                array_multisort($total_leads,SORT_ASC,$client['data']);
            }
        }

        // fix total in pagination 1, when click specifict campaign
        // info('getclient 5.0', ['view' => $view,'clientID' => $clientID, 'CompanyID' => $CompanyID, 'PerPage' => $PerPage, 'groupCompanyID' => $groupCompanyID,'leadspeekType'=>$leadspeekType]);
        if($view == 'dashboard' && !empty($clientID)){
            // info('getclient 5.1');
            $getTotalCampaignsDashboardWhenClickCampaign = $leadspeekGetCampaignService->getTotalCampaignsDashboardWhenClickCampaign($CompanyID, $groupCompanyID, $leadspeekType);
            $campaignTotal = $getTotalCampaignsDashboardWhenClickCampaign['campaign_total'] ?? 0;
            $client = new LengthAwarePaginator(
                $client->items(), // items
                $campaignTotal, // total
                $client->perPage(), // per page
                $client->currentPage(), // current page
                ['path' => request()->url(), 'query' => request()->query()] // options
            );
        }

        // info('getclient return', ['client' => $client]);
        return $client;
    }
    
    public function googlespreadsheet_checkconnect(Request $request, $CompanyID) {
        $chk = $this->get_setting($CompanyID,$this->_moduleID,$this->_settingTokenName);
        
        if(count($chk) > 0 && $chk[0]->setting_value != '') {
            return response()->json(array("googleSpreadsheetConnected"=>true));
        }else{
            return response()->json(array("googleSpreadsheetConnected"=>false));
        }
    }

    private function get_setting($CompanyID,$ModuleID,$settingName) {
        return ModuleSetting::select('setting_value')
                ->where('company_id','=',$CompanyID)
                ->where('module_id','=',$ModuleID)
                ->where('setting_name','=',$settingName)
                ->get();
    }

    public function googlespreadsheet_connect(Request $request,$CompanyID) {
        $client = new GoogleSheet($CompanyID,$this->_moduleID,$this->_settingTokenName);
        $acctoken = "success";
        return view('googlepopup',compact('acctoken'));
    }

    public function googlespreadsheet_revoke(Request $request,$CompanyID) {
        /** CHECK IF THERE IS ANY ACTIVE CAMPAIGN */
        // $chkinvalidusr = LeadspeekUser::select('leadspeek_users.id','leadspeek_users.user_id')
        //                                 ->where('leadspeek_users.company_id','=',$CompanyID)
        //                                 ->where(function($query){
        //                                     $query->where(function($query){
        //                                         $query->where('leadspeek_users.active','=','T')
        //                                             ->where('leadspeek_users.disabled','=','F')
        //                                             ->where('leadspeek_users.active_user','=','T');
        //                                     })
        //                                     ->orWhere(function($query){
        //                                         $query->where('leadspeek_users.active','=','F')
        //                                             ->where('leadspeek_users.disabled','=','F')
        //                                             ->where('leadspeek_users.active_user','=','T');
        //                                     })
        //                                     ->orWhere(function($query){
        //                                         $query->where('leadspeek_users.active','=','F')
        //                                             ->where('leadspeek_users.disabled','=','T')
        //                                             ->where('leadspeek_users.active_user','=','T');
        //                                     });
        //                                 })->get();
        //     if (count($chkinvalidusr) > 0) {
        //         return response()->json(array('result'=>'failed','message'=>"There are active campaigns still running, Please stop all campaigns prior to disconnect google connection."));
        //         exit;die();
        //     }else{
        /** CHECK IF THERE IS ANY ACTIVE CAMPAIGN */
                try {
                    $client = new GoogleSheet($CompanyID,$this->_moduleID,$this->_settingTokenName);
                    $client->revoke();
                } catch (Exception $e) {
                    $details = [
                        'errormsg'  => 'Error when trying to revoke google connect (googlespreadsheet_revoke - L5245) Company ID :' . $CompanyID,
                    ];
                    $from = [
                        'address' => 'noreply@exactmatchmarketing.com',
                        'name' => 'Support',
                        'replyto' => 'support@exactmatchmarketing.com',
                    ];
                    $this->send_email(array('serverlogs@sitesettingsapi.com'),$from,'Error when trying to revoke google connect (googlespreadsheet_revoke - L5245) Company ID :' . $CompanyID . '(' . $e->getCode() . ')',$details,array(),'emails.tryseramatcherrorlog','');
                }
                /** REMOVE FROM MODULE SETTING */
                $removeModuleSetting = ModuleSetting::where('company_id','=',$CompanyID)->where('module_id','=',$this->_moduleID)->delete();
                /** REMOVE FROM MODULE SETTING */
                return response()->json(array("result"=>"success","googleSpreadsheetConnected"=>false));
            //}
    }

    public function googlespreadsheet_callback(Request $request) {  
        $client = new GoogleSheet($request->state,$this->_moduleID,$this->_settingTokenName,$request->code);   
        $acctoken = "success";
        return view('googlepopup',compact('acctoken'));
    }

    private function update_leadspeek_api_client($leadspeekID,$name,$customID,$active=true) {
        $http = new \GuzzleHttp\Client;
        
        $appkey = config('services.trysera.api_id');
        $domain = config('services.trysera.domain');
        $campaignID = config('services.trysera.campaignid');
        
        $apiURL =  config('services.trysera.endpoint') . 'subclients/' . $leadspeekID;
        
        try {
            $options = [
                'headers' => [
                    'Authorization' => 'Bearer ' . $appkey,
                ],
                'json' => [
                    "SubClient" => [
                        "ID" => $leadspeekID,
                        "Name" => $name,
                        "CustomID" => $customID,
                        "Active" => $active
                    ]       
                ]
            ]; 
           
           
            $response = $http->put($apiURL,$options);
            $result =  json_decode($response->getBody());
            //$result = json_encode($result);
            //echo $result->SubClients . '<br>';
            //echo $result->SubClients['Message'];
            /*if(isset($result->SubClients[0]->ID)) {
                return $result->SubClients[0]->ID;
            }else{
                return '';
            }*/
            
            //print_r($result);
            //echo $result->Message;
            //return response()->json($result,200);

        }catch (\GuzzleHttp\Exception\BadResponseException $e) {
            if ($e->getCode() === 400) {
                return response()->json('Invalid Request. Please enter a username or a password.', $e->getCode());
            } else if ($e->getCode() === 401) {
                return response()->json('Your credentials are incorrect. Please try again', $e->getCode());
            }

            return response()->json('Something went wrong on the server.', $e->getCode());
        }
    }

    private function remove_leadspeek_api_client($leadspeekID) {
        $http = new \GuzzleHttp\Client;
        
        $appkey = config('services.trysera.api_id');
        $domain = config('services.trysera.domain');
        $campaignID = config('services.trysera.campaignid');
        
        $apiURL =  config('services.trysera.endpoint') . 'subclients/' . $leadspeekID;
        
        try {
            $options = [
                'headers' => [
                    'Authorization' => 'Bearer ' . $appkey,
                ],
            ]; 
            
            $response = $http->delete($apiURL,$options);
            $result =  json_decode($response->getBody());
            //$result = json_encode($result);
            //echo $result->SubClients . '<br>';
            //echo $result->SubClients['Message'];
            /*if(isset($result->SubClients[0]->ID)) {
                return $result->SubClients[0]->ID;
            }else{
                return '';
            }
            */
            //print_r($result);
            //echo $result->Message;
            //return response()->json($result,200);

        }catch (\GuzzleHttp\Exception\BadResponseException $e) {
            if ($e->getCode() === 400) {
                return response()->json('Invalid Request. Please enter a username or a password.', $e->getCode());
            } else if ($e->getCode() === 401) {
                return response()->json('Your credentials are incorrect. Please try again', $e->getCode());
            }

            return response()->json('Something went wrong on the server.', $e->getCode());
        }
    }

    private function create_leadspeek_api_client($clientName,$customID='') {
        $http = new \GuzzleHttp\Client;
        
        $appkey = config('services.trysera.api_id');
        $domain = config('services.trysera.domain');
        $campaignID = config('services.trysera.campaignid');
        
        $apiURL =  config('services.trysera.endpoint') . 'subclients';
        
        try {
            $options = [
                'headers' => [
                    'Authorization' => 'Bearer ' . $appkey,
                ],
                'json' => [
                    "SubClient" => [
                        "Name" => $clientName,
                        "CustomID" => $customID,
                        "Active" => false
                    ]       
                ]
            ]; 
            /*$headers = [
                'X-App-Key' => $appkey,        
                'X-User-Key' => $usrkey,
                'X-Api-Version' => '',
                'Content-Type' => 'application/json',
            ];*/
            //$http->setHeader('X-App-Key',$appkey);
            //$http->setHeader('X-User-Key',$usrkey);
            //$http->setHeader('Content-Type','application/json');
           
            $response = $http->post($apiURL,$options);
            $result =  json_decode($response->getBody());
            //$result = json_encode($result);
            //echo $result->SubClients . '<br>';
            //echo $result->SubClients['Message'];
            if(isset($result->SubClients[0]->ID)) {
                return $result->SubClients[0]->ID;
            }else{
                return '';
            }
            
            //print_r($result);
            //echo $result->Message;
            //return response()->json($result,200);

        }catch (\GuzzleHttp\Exception\BadResponseException $e) {
            if ($e->getCode() === 400) {
                return response()->json('Invalid Request. Please enter a username or a password.', $e->getCode());
            } else if ($e->getCode() === 401) {
                return response()->json('Your credentials are incorrect. Please try again', $e->getCode());
            }

            return response()->json('Something went wrong on the server.', $e->getCode());
        }
    }

    /** REPORTING */
    /** EXPORT REPORTING */

    public function getreportleadexport(Request $request) {
        // info('getreportleadexport', ['all' => $request->all()]);
        return (new LeadsExport)->betweenDate($request->clientID,$request->startDate,$request->endDate,$request->searchQuery,$request->companyID,$request->groupCompanyID,$request->moduleType)->download('leads.xlsx');
    }
    /** EXPORT REPORTING */

    public function getreportlead(Request $request) {
        $userID = $request->clientID;
        $companyID = $request->CompanyID;
        $moduleType = isset($request->moduleType)?$request->moduleType:'';
        $searchQuery = isset($request->searchQuery)?$request->searchQuery:'';
        //$startDate = date('Y-m-d',strtotime($request->startDate));
        //$endDate = date('Y-m-d',strtotime($request->endDate));
        $startDate = date('YmdHis',strtotime($request->startDate));
        $endDate = date('YmdHis',strtotime($request->endDate));
        $PerPage = $PerPage ?? $request->input('PerPage', 10);
        $Page = (isset($request->Page))?$request->Page:'';
        $sortby = (isset($request->sortby) && $request->sortby != '0')?$request->sortby:'';
        $order = (isset($request->order) && $request->order != '0')?$request->order:'';
        $groupCompanyID = (isset($request->groupCompanyID))?$request->groupCompanyID:'';
        // info('getreportlead 1.1', ['userID' => $userID,'companyID' => $companyID,'moduleType' => $moduleType,'searchQuery' => $searchQuery,'startDate' => $startDate,'endDate' => $endDate,'PerPage' => $PerPage,'Page' => $Page,'sortby' => $sortby,'order' => $order,'groupCompanyID' => $groupCompanyID]);

        $leadspeekIds = [];
        if($userID == 'view_all' && !empty($moduleType)){
            $leadspeekIds = LeadspeekUser::join('users','leadspeek_users.user_id','=','users.id')
                ->join('companies','users.company_id','=','companies.id')
                ->where('leadspeek_users.company_id','=',$companyID)
                ->where('leadspeek_users.leadspeek_type','=',$moduleType);
            if(!empty($groupCompanyID)){
                $leadspeekIds->where('leadspeek_users.user_id','=',$groupCompanyID);
            }
            $leadspeekIds = $leadspeekIds->where('leadspeek_users.archived','=','F')
                ->where('users.active','=','T')
                ->pluck('leadspeek_users.id')
                ->toArray();
            // info('getreportlead 1.2', ['leadspeekIds' => $leadspeekIds]);
        }

        //$leads = LeadspeekReport::where('user_id','=',$userID)
        $leads = [];
        if($moduleType == 'enhance' || $moduleType == 'local') {
            $leads = LeadspeekReport::leftJoin('person_advance_1','person_advance_1.person_id','=','leadspeek_reports.person_id')
                ->where('leadspeek_reports.active','=','T')
                //->whereBetween(DB::raw('DATE_FORMAT(clickdate,"%Y-%m-%d")'),[$startDate,$endDate])
                ->where(DB::raw('DATE_FORMAT(leadspeek_reports.clickdate,"%Y%m%d%H%i%s")'),'>=',$startDate)
                ->where(DB::raw('DATE_FORMAT(leadspeek_reports.clickdate,"%Y%m%d%H%i%s")'),'<=',$endDate)
                ->where(function ($query) use ($searchQuery, $userID, $moduleType) {
                    $query->where(DB::raw('CONCAT(CONVERT(AES_DECRYPT(FROM_BASE64(leadspeek_reports.firstname), "8e651522e38256f2") USING utf8mb4)," ",CONVERT(AES_DECRYPT(FROM_BASE64(leadspeek_reports.lastname), "8e651522e38256f2") USING utf8mb4))'), 'like', "%$searchQuery%")
                            ->orWhere(DB::raw('CONVERT(AES_DECRYPT(FROM_BASE64(leadspeek_reports.email), "8e651522e38256f2") USING utf8mb4)'), 'like', "%$searchQuery%")
                            ->orWhere(DB::raw('CONVERT(AES_DECRYPT(FROM_BASE64(leadspeek_reports.phone), "8e651522e38256f2") USING utf8mb4)'), 'like', "%$searchQuery%")
                            ->orWhere(DB::raw('CONVERT(AES_DECRYPT(FROM_BASE64(leadspeek_reports.address1), "8e651522e38256f2") USING utf8mb4)'), 'like', "%$searchQuery%")
                            ->orWhere(DB::raw('CONVERT(AES_DECRYPT(FROM_BASE64(leadspeek_reports.city), "8e651522e38256f2") USING utf8mb4)'), 'like', "%$searchQuery%")
                            ->orWhere(DB::raw('CONVERT(AES_DECRYPT(FROM_BASE64(leadspeek_reports.state), "8e651522e38256f2") USING utf8mb4)'), 'like', "%$searchQuery%")
                            ->orWhere(DB::raw('CONVERT(AES_DECRYPT(FROM_BASE64(leadspeek_reports.zipcode), "8e651522e38256f2") USING utf8mb4)'), 'like', "%$searchQuery%")
                            ->orWhere('leadspeek_reports.keyword', 'like', "%$searchQuery%");
                    if($userID == 'view_all' && !empty($moduleType)){
                        $query->orWhere('leadspeek_reports.leadspeek_api_id', 'like', "%$searchQuery%");
                    }
                });
                // ->where(DB::raw('CONCAT(CONVERT(AES_DECRYPT(FROM_BASE64(leadspeek_reports.firstname), "8e651522e38256f2") USING utf8mb4)," ",CONVERT(AES_DECRYPT(FROM_BASE64(leadspeek_reports.lastname), "8e651522e38256f2") USING utf8mb4)," ",CONVERT(AES_DECRYPT(FROM_BASE64(leadspeek_reports.email), "8e651522e38256f2") USING utf8mb4))'), 'like', "%$searchQuery%");
                // ->orderByDesc('clickdate');
            
            if($userID == 'view_all' && !empty($moduleType)){
                // info('getreportlead 2.1');
                $leads->whereIn('leadspeek_reports.lp_user_id',$leadspeekIds);
            }else{
                // info('getreportlead 2.2');
                $leads->where('leadspeek_reports.lp_user_id','=',$userID);
            }
        } else if($moduleType == 'b2b') {
            $leads = LeadspeekReport::leftJoin('person_b2b','person_b2b.person_id','=','leadspeek_reports.person_id')
                ->where('leadspeek_reports.active','=','T')
                //->whereBetween(DB::raw('DATE_FORMAT(clickdate,"%Y-%m-%d")'),[$startDate,$endDate])
                ->where(DB::raw('DATE_FORMAT(leadspeek_reports.clickdate,"%Y%m%d%H%i%s")'),'>=',$startDate)
                ->where(DB::raw('DATE_FORMAT(leadspeek_reports.clickdate,"%Y%m%d%H%i%s")'),'<=',$endDate)
                ->where(function ($query) use ($searchQuery, $userID, $moduleType) {
                    $query->where(DB::raw('CONCAT(CONVERT(AES_DECRYPT(FROM_BASE64(leadspeek_reports.firstname), "8e651522e38256f2") USING utf8mb4)," ",CONVERT(AES_DECRYPT(FROM_BASE64(leadspeek_reports.lastname), "8e651522e38256f2") USING utf8mb4))'), 'like', "%$searchQuery%")
                            ->orWhere(DB::raw('CONVERT(AES_DECRYPT(FROM_BASE64(leadspeek_reports.email), "8e651522e38256f2") USING utf8mb4)'), 'like', "%$searchQuery%")
                            ->orWhere(DB::raw('CONVERT(AES_DECRYPT(FROM_BASE64(leadspeek_reports.phone), "8e651522e38256f2") USING utf8mb4)'), 'like', "%$searchQuery%")
                            ->orWhere(DB::raw('CONVERT(AES_DECRYPT(FROM_BASE64(leadspeek_reports.address1), "8e651522e38256f2") USING utf8mb4)'), 'like', "%$searchQuery%")
                            ->orWhere(DB::raw('CONVERT(AES_DECRYPT(FROM_BASE64(leadspeek_reports.city), "8e651522e38256f2") USING utf8mb4)'), 'like', "%$searchQuery%")
                            ->orWhere(DB::raw('CONVERT(AES_DECRYPT(FROM_BASE64(leadspeek_reports.state), "8e651522e38256f2") USING utf8mb4)'), 'like', "%$searchQuery%")
                            ->orWhere(DB::raw('CONVERT(AES_DECRYPT(FROM_BASE64(leadspeek_reports.zipcode), "8e651522e38256f2") USING utf8mb4)'), 'like', "%$searchQuery%")
                            ->orWhere('leadspeek_reports.keyword', 'like', "%$searchQuery%");
                    if($userID == 'view_all' && !empty($moduleType)){
                        $query->orWhere('leadspeek_reports.leadspeek_api_id', 'like', "%$searchQuery%");
                    }
                });
                // ->where(DB::raw('CONCAT(CONVERT(AES_DECRYPT(FROM_BASE64(leadspeek_reports.firstname), "8e651522e38256f2") USING utf8mb4)," ",CONVERT(AES_DECRYPT(FROM_BASE64(leadspeek_reports.lastname), "8e651522e38256f2") USING utf8mb4)," ",CONVERT(AES_DECRYPT(FROM_BASE64(leadspeek_reports.email), "8e651522e38256f2") USING utf8mb4))'), 'like', "%$searchQuery%");
                // ->orderByDesc('clickdate');
            
            if($userID == 'view_all' && !empty($moduleType)){
                // info('getreportlead 3.1');
                $leads->whereIn('leadspeek_reports.lp_user_id',$leadspeekIds);
            }else{
                // info('getreportlead 3.2');
                $leads->where('leadspeek_reports.lp_user_id','=',$userID);
            }
        } else {
            $leads = LeadspeekReport::where('leadspeek_reports.active','=','T')
                //->whereBetween(DB::raw('DATE_FORMAT(clickdate,"%Y-%m-%d")'),[$startDate,$endDate])
                ->where(DB::raw('DATE_FORMAT(leadspeek_reports.clickdate,"%Y%m%d%H%i%s")'),'>=',$startDate)
                ->where(DB::raw('DATE_FORMAT(leadspeek_reports.clickdate,"%Y%m%d%H%i%s")'),'<=',$endDate)
                ->where(function ($query) use ($searchQuery, $userID, $moduleType) {
                    $query->where(DB::raw('CONCAT(CONVERT(AES_DECRYPT(FROM_BASE64(leadspeek_reports.firstname), "8e651522e38256f2") USING utf8mb4)," ",CONVERT(AES_DECRYPT(FROM_BASE64(leadspeek_reports.lastname), "8e651522e38256f2") USING utf8mb4))'), 'like', "%$searchQuery%")
                            ->orWhere(DB::raw('CONVERT(AES_DECRYPT(FROM_BASE64(leadspeek_reports.email), "8e651522e38256f2") USING utf8mb4)'), 'like', "%$searchQuery%")
                            ->orWhere(DB::raw('CONVERT(AES_DECRYPT(FROM_BASE64(leadspeek_reports.phone), "8e651522e38256f2") USING utf8mb4)'), 'like', "%$searchQuery%")
                            ->orWhere(DB::raw('CONVERT(AES_DECRYPT(FROM_BASE64(leadspeek_reports.address1), "8e651522e38256f2") USING utf8mb4)'), 'like', "%$searchQuery%")
                            ->orWhere(DB::raw('CONVERT(AES_DECRYPT(FROM_BASE64(leadspeek_reports.city), "8e651522e38256f2") USING utf8mb4)'), 'like', "%$searchQuery%")
                            ->orWhere(DB::raw('CONVERT(AES_DECRYPT(FROM_BASE64(leadspeek_reports.state), "8e651522e38256f2") USING utf8mb4)'), 'like', "%$searchQuery%")
                            ->orWhere(DB::raw('CONVERT(AES_DECRYPT(FROM_BASE64(leadspeek_reports.zipcode), "8e651522e38256f2") USING utf8mb4)'), 'like', "%$searchQuery%")
                            ->orWhere('leadspeek_reports.keyword', 'like', "%$searchQuery%");
                    if($userID == 'view_all' && !empty($moduleType)){
                        $query->orWhere('leadspeek_reports.leadspeek_api_id', 'like', "%$searchQuery%");
                    }
                });
                // ->where(DB::raw('CONCAT(CONVERT(AES_DECRYPT(FROM_BASE64(leadspeek_reports.firstname), "8e651522e38256f2") USING utf8mb4)," ",CONVERT(AES_DECRYPT(FROM_BASE64(leadspeek_reports.lastname), "8e651522e38256f2") USING utf8mb4)," ",CONVERT(AES_DECRYPT(FROM_BASE64(leadspeek_reports.email), "8e651522e38256f2") USING utf8mb4))'), 'like', "%$searchQuery%");
                // ->orderByDesc('clickdate');'
            
            if($userID == 'view_all' && !empty($moduleType)){
                // info('getreportlead 4.1');
                $leads->whereIn('leadspeek_reports.lp_user_id',$leadspeekIds);
            }else{
                // info('getreportlead 4.2');
                $leads->where('leadspeek_reports.lp_user_id','=',$userID);
            }
        }

        if (trim($order) != '') {
            if (trim($order) == 'descending') {
                $order = "DESC";
            }else{
                $order = "ASC";
            }
        }

        if (trim($sortby) != '') {
            if (trim($sortby) == "full_name"){
                $leads->orderBy(DB::raw('CONVERT(AES_DECRYPT(FROM_BASE64(leadspeek_reports.firstname), "8e651522e38256f2") USING utf8mb4)'), $order)
                      ->orderBy(DB::raw('CONVERT(AES_DECRYPT(FROM_BASE64(leadspeek_reports.lastname), "8e651522e38256f2") USING utf8mb4)'), $order);
            } else if (trim($sortby) == "clickdate"){
                $leads->orderBy('leadspeek_reports.clickdate', $order);
            } else if (trim($sortby) == "leadspeek_api_id"){
                $leads->orderBy('leadspeek_reports.leadspeek_api_id', $order);
            }
        }

        if ($Page == '') { 
            $leads = $leads->orderByDesc('leadspeek_reports.clickdate')->get();
        }else{
            $leads = $leads->paginate($PerPage, ['*'], 'page', $Page);
        }

        return response()->json(array('result'=>'success','leads'=>$leads));
    }

    public function getreportinvoice(Request $request) {
        $userID = $request->clientID;
        $companyID = $request->CompanyID;
        //$startDate = date('Y-m-d',strtotime($request->startDate));
        //$endDate = date('Y-m-d',strtotime($request->endDate));
        $startDate = date('YmdHis',strtotime($request->startDate));
        $endDate = date('YmdHis',strtotime($request->endDate));

        $leads = array();

        $leaduser = LeadspeekUser::select('leadspeek_api_id')
                                    ->where('id','=',$userID)
                                    ->get();
        if (count($leaduser) > 0) {
            $userID = $leaduser[0]['leadspeek_api_id'];

            //$leads = LeadspeekInvoice::where('user_id','=',$userID)
            $leads = LeadspeekInvoice::where('leadspeek_api_id','=',$userID)
                                    ->where('active','=','T')
                                    //->whereBetween(DB::raw('DATE_FORMAT(invoice_date,"%Y-%m-%d")'),[$startDate,$endDate])
                                    ->where(DB::raw('DATE_FORMAT(invoice_date,"%Y%m%d%H%i%s")'),'>=',$startDate)
                                    ->where(DB::raw('DATE_FORMAT(invoice_date,"%Y%m%d%H%i%s")'),'<=',$endDate)
                                    ->get();
        }

        return response()->json(array('result'=>'success','invoices'=>$leads));
    }

    public function getinitdatechart(Request $request) 
    {
        $userID = $request->clientID;
        $companyID = $request->CompanyID;
        $moduleType = $request->moduleType ?? '';
        $groupCompanyID = $request->groupCompanyID ?? '';

        $leadspeekIds = [];
        if($userID == 'view_all' && !empty($moduleType)){
            $leadspeekIds = LeadspeekUser::join('users','leadspeek_users.user_id','=','users.id')
                ->join('companies','users.company_id','=','companies.id')
                ->where('leadspeek_users.company_id','=',$companyID)
                ->where('leadspeek_users.leadspeek_type','=',$moduleType);
            if(!empty($groupCompanyID)){
                $leadspeekIds->where('leadspeek_users.user_id','=',$groupCompanyID);
            }
            $leadspeekIds = $leadspeekIds->where('leadspeek_users.archived','=','F')
                ->where('users.active','=','T')
                ->pluck('leadspeek_users.id')
                ->toArray();
            // info('getinitdatechart 1.1', ['leadspeekIds' => $leadspeekIds]);
        }

        $initDate = LeadspeekReport::select(
                DB::raw("(DATE_FORMAT(clickdate,'%c')) as month"),
                DB::raw("(DATE_FORMAT(clickdate,'%Y')) as year")
            )
            ->where('active','=','T')
            ->where('clickdate','>=',DB::raw("last_day(now()) + interval 1 day - interval 6 month"));
        if($userID == 'view_all' && !empty($moduleType)){
            $initDate->whereIn('lp_user_id',$leadspeekIds);
        }else{
            $initDate->where('lp_user_id','=',$userID);
        }
        $initDate = $initDate->orderBy(DB::raw("DATE_FORMAT(clickdate,'%Y%c')"),'ASC')
            ->groupBy(DB::raw("DATE_FORMAT(clickdate,'%Y%c')"))
            ->get();

        // info('getinitdatechart', ['userID'=>$userID, 'companyID'=>$companyID, 'initDate'=>$initDate]);
        return response()->json(array('result'=>'success','initdate'=>$initDate));
    }

    public function getreportchart(Request $request) 
    {
        $typeReport = $request->type;
        $userID = $request->clientID;
        $companyID = $request->CompanyID;
        $chartdataMonthly = array();
        $chartdataWeekly = array();
        $moduleType = $request->moduleType ?? '';
        $groupCompanyID = $request->groupCompanyID ?? '';

        $startDate = date('YmdHis',strtotime($request->startDate));
        $endDate = date('YmdHis',strtotime($request->endDate));

        $leadspeekIds = [];
        if($userID == 'view_all' && !empty($moduleType)){
            $leadspeekIds = LeadspeekUser::join('users','leadspeek_users.user_id','=','users.id')
                ->join('companies','users.company_id','=','companies.id')
                ->where('leadspeek_users.company_id','=',$companyID)
                ->where('leadspeek_users.leadspeek_type','=',$moduleType);
            if(!empty($groupCompanyID)){
                $leadspeekIds->where('leadspeek_users.user_id','=',$groupCompanyID);
            }
            $leadspeekIds = $leadspeekIds->where('leadspeek_users.archived','=','F')
                ->where('users.active','=','T')
                ->pluck('leadspeek_users.id')
                ->toArray();
            // info('getreportchart 1.1', ['leadspeekIds' => $leadspeekIds]);
        }
        
        //if($typeReport == 'monthly') {
        $chart = LeadspeekReport::select(
                DB::raw("(COUNT(*)) as total"),
                DB::raw("(DATE_FORMAT(clickdate,'%c')) as month"),
                DB::raw("(DATE_FORMAT(clickdate,'%Y')) as year")
            )
            //->where(DB::raw("DATE_FORMAT(clickdate,'%Y')"),'=',date('Y'))
            //->where('user_id','=',$userID)
            ->where(DB::raw('DATE_FORMAT(clickdate,"%Y%m%d%H%i%s")'),'>=',$startDate)
            ->where(DB::raw('DATE_FORMAT(clickdate,"%Y%m%d%H%i%s")'),'<=',$endDate)
            ->where('active','=','T');
                        
        if($userID == 'view_all' && !empty($moduleType)){
            $chart->whereIn('lp_user_id',$leadspeekIds);
        }else{
            $chart->where('lp_user_id','=',$userID);
        }

        $chart = $chart->orderBy(DB::raw("DATE_FORMAT(clickdate,'%Y%m')"))
            ->groupBy(DB::raw("DATE_FORMAT(clickdate,'%Y%m')"))
            ->get();
        
        $chartdataMonthly = $chart;
            
        // -------------------------------------
            
        //}else if($typeReport == 'weekly') {
        $dateRange = $this->get_first_and_last_day_of_week(date('Y'),date('W'));
        $startday = $dateRange->first_day;
        //$startday = $startday->format('Y-m-d');
        $startday = $startday->format('Ymd000000');

        $endday = $dateRange->last_day;
        //$endday = $endday->format('Y-m-d');
        $endday = $endday->format('Ymd235959');

        $chart = LeadspeekReport::select(
                DB::raw("(COUNT(*)) as total"),
                DB::raw("(DATE_FORMAT(clickdate,'%w')) as day"),
            )
            //->whereBetween(DB::raw('DATE_FORMAT(clickdate,"%Y-%m-%d")'),[$startday,$endday])
            ->where(DB::raw('DATE_FORMAT(clickdate,"%Y%m%d%H%i%s")'),'>=',$startday)
            ->where(DB::raw('DATE_FORMAT(clickdate,"%Y%m%d%H%i%s")'),'<=',$endday)
            ->where('active','=','T');
            //->where('user_id','=',$userID)
        
        if($userID == 'view_all' && !empty($moduleType)){
            $chart->whereIn('lp_user_id',$leadspeekIds);
        }else{
            $chart->where('lp_user_id','=',$userID);
        }
            
        $chart = $chart->orderBy(DB::raw("DATE_FORMAT(clickdate,'%w')"))
            ->groupBy(DB::raw("DATE_FORMAT(clickdate,'%w')"))
            ->get();

        for($i=0;$i<7;$i++) {
            $match = false;
            /** CHECK ON CHART RESULT */
            foreach($chart as $ch) {
                if (($i) == $ch['day']) {
                    $match = true;
                    break;
                }
            }
            /** CHECK ON CHART RESULT */
            if($match) {
                $chartdataWeekly[] = $ch['total'];
            }else{
                $chartdataWeekly[] = 0;
            }
        }

        //}

        // -------------------------------------


        // info('getreportchart', ['typeReport' => $typeReport, 'userID'=>$userID, 'companyID'=>$companyID, 'chartdataMonthly'=>$chartdataMonthly,'chartdataWeekly'=>$chartdataWeekly]);
        return response()->json(array('result'=>'success','chartdataMonthly'=>$chartdataMonthly,'chartdataWeekly'=>$chartdataWeekly));

    }

    private function countDays($day, $start, $end)
    {        
        //get the day of the week for start and end dates (0-6)
        $w = array(date('w', $start), date('w', $end));
    
        //get partial week day count
        if ($w[0] < $w[1])
        {            
            $partialWeekCount = ($day >= $w[0] && $day <= $w[1]);
        }else if ($w[0] == $w[1])
        {
            $partialWeekCount = $w[0] == $day;
        }else
        {
            $partialWeekCount = ($day >= $w[0] || $day <= $w[1]);
        }
    
        //first count the number of complete weeks, then add 1 if $day falls in a partial week.
        return floor( ( $end-$start )/60/60/24/7) + $partialWeekCount;
    }

    private function get_first_and_last_day_of_week( $year_number, $week_number ) {
        // we need to specify 'today' otherwise datetime constructor uses 'now' which includes current time
        $today = new DateTime( 'today' );
    
        return (object) [
            'first_day' => clone $today->setISODate( $year_number, $week_number, 0 ),
            'last_day'  => clone $today->setISODate( $year_number, $week_number, 6 )
        ];
    }
    /** REPORTING */

    /** ARCHIVE CAMPAIGN */
    public function archivecampaign(Request $request) {
        $lpuserID = isset($request->lpuserid)?$request->lpuserid:'';
        $status = isset($request->status)?$request->status:'T';

        $leadspeekuserUpdate = LeadspeekUser::find($lpuserID);
        $leadspeekuserUpdate->archived = $status;
        $leadspeekuserUpdate->save();

        /** SEND NOTIFICATION TO CLIENT */
        /** START NEW METHOD EMAIL */
        
        $userlist = User::select('companies.company_name','companies.simplifi_organizationid','users.company_id','users.company_parent','users.name','users.email','users.phonenum','users.user_type','users.company_root_id')
                            ->join('companies','users.company_id','=','companies.id')
                            ->where('users.id','=',$leadspeekuserUpdate->user_id)
                            ->where('users.active','=','T')
                            ->get();

        //$smtpusername = $this->set_smtp_email($userlist[0]['company_parent']);
        $clientEmail = explode(PHP_EOL, $leadspeekuserUpdate->report_sent_to);
        $emailtype = 'em_archivecampaign';

        $customsetting = $this->getcompanysetting($leadspeekuserUpdate->company_id,$emailtype);
        $chkcustomsetting = $customsetting;

        if ($customsetting == '') {
            $customsetting =  json_decode(json_encode($this->check_email_template($emailtype,$leadspeekuserUpdate->company_id)));
        }
        
        $finalcontent = nl2br($this->filterCustomEmail($userlist,$userlist[0]['company_parent'],$customsetting->content,'','','',$leadspeekuserUpdate->leadspeek_api_id));
        $finalsubject = $this->filterCustomEmail($userlist,$userlist[0]['company_parent'],$customsetting->subject,'','','',$leadspeekuserUpdate->leadspeek_api_id);
        $finalfrom = $this->filterCustomEmail($userlist,$userlist[0]['company_parent'],$customsetting->fromName,'','','',$leadspeekuserUpdate->leadspeek_api_id);

        $details = [
            'title' => ucwords($finalsubject),
            'content' => $finalcontent,
        ];

        $from = [
            'address' => (isset($customsetting->fromAddress) && $customsetting->fromAddress != '')?$customsetting->fromAddress:'noreply@sitesettingsapi.com',
            'name' => (isset($finalfrom) && $finalfrom != '')?$finalfrom:'Campaign Archived',
            'replyto' => (isset($customsetting->fromReplyto) && $customsetting->fromReplyto != '')?$customsetting->fromReplyto:'support@sitesettingsapi.com',
        ];


        $this->send_email($clientEmail,$from,ucwords($finalsubject),$details,array(),'emails.customemail',$userlist[0]['company_parent'],true);
        /** START NEW METHOD EMAIL */
        /** SEND NOTIFICATION TO CLIENT */

        //SIMPLIFI DELETE CAMPAIGN
        // if ($leadspeekuserUpdate->leadspeek_type == 'simplifi') {
        //     try {
        //         $this->SimpliFiService->delete_campaign((int) $leadspeekuserUpdate->leadspeek_organizationid, (int) $leadspeekuserUpdate->leadspeek_campaignsid);
        //     } catch (\Throwable $th) {
        //         Log::info([
        //             'message' => 'Error deleting Simplifi campaign',
        //             'organization_id' => $leadspeekuserUpdate->leadspeek_organizationid,
        //             'campaign_id' => $leadspeekuserUpdate->leadspeek_campaignsid,
        //             'error' => $th->getMessage()
        //         ]);
        //     }
        //     try {
        //         $this->SimpliFiService->delete_addressable_audience((int) $leadspeekuserUpdate->addressable_audience_simplifi_id);
        //     } catch (\Throwable $th) {
        //         Log::info([
        //             'message' => 'Error deleting Simplifi Addressable Audience',
        //             'organization_id' => $leadspeekuserUpdate->leadspeek_organizationid,
        //             'campaign_id' => $leadspeekuserUpdate->leadspeek_campaignsid,
        //             'error' => $th->getMessage()
        //         ]);
        //     }
        //     try {
        //         $this->SimpliFiService->delete_first_party_segment((int) $leadspeekuserUpdate->retargeting_audience_simplifi_id);
        //     } catch (\Throwable $th) {
        //         Log::info([
        //             'message' => 'Error deleting Simplifi Addressable Audience',
        //             'organization_id' => $leadspeekuserUpdate->leadspeek_organizationid,
        //             'campaign_id' => $leadspeekuserUpdate->leadspeek_campaignsid,
        //             'error' => $th->getMessage()
        //         ]);
        //     }
        // }
        //SIMPLIFI DELETE CAMPAIGN

        return response()->json(array('result'=>'success'));
    }
    /** ARCHIVE CAMPAIGN */
    
    public function checkCampaignActive(Request $request)
    {
        $company_id = isset($request->company_id) ? $request->company_id : '';
        $leadspeek_type = isset($request->leadspeek_type) ? $request->leadspeek_type : '';
        $user_type = isset($request->user_type) ? $request->user_type : '';
        $user_id = isset($request->user_id) ? $request->user_id : '';

        try {
            $query = LeadspeekUser::where('company_id', $company_id)
            ->where('leadspeek_type', $leadspeek_type)
            ->where('active_user', 'T')
            ->where('disabled', 'F')
            ->where(function ($query) {
                $query->where('active', 'T')
                    ->orWhere('active', 'F');
            });

            if ($user_type == 'client') {
                $query->where('user_id', $user_id);
            }

            $activeCampaignIds = $query->pluck('leadspeek_api_id');
            $totalActiveCampaign = $activeCampaignIds->count();

            return response()->json(['result' => 'success', 'active_campaign_id' => $activeCampaignIds, 'count' => $totalActiveCampaign, 'leadspeek_type' => $leadspeek_type]);
        } catch (\Throwable $th) {
            return response()->json(['result' => 'failed', 'message' => $th->getMessage()]);
        }
    }

    public function checkver(){
        $settingJson = DB::table('global_settings')
                        ->where('setting_name', 'spreadsheet_b2b_ordering')
                        ->value('setting_value');

        $dtJson = json_decode($settingJson, true);
        $startDate_spreadsheet = isset($dtJson[0]['start-date']) ? Carbon::parse($dtJson[0]['start-date']) : null;
        return $startDate_spreadsheet;
    }

    public function onSendTestSpreadsheet(Request $request) {
        $campaign_id = (isset($request->campaignID)) ? $request->campaignID : ''; 
        $leadspeekUser = LeadspeekUser::where('id', $campaign_id)->where('archived','F')->first();

        if($leadspeekUser) {
            $clientGoogle = new GoogleSheet($leadspeekUser->company_id,$this->_moduleID,$this->_settingTokenName,'',true,$leadspeekUser->leadspeek_api_id ?? "",$leadspeekUser->leadspeek_type ?? "", __FUNCTION__);

            $cekSpreadsheet = $clientGoogle->checkSpreadsheet($leadspeekUser->spreadsheet_id);

            $errorObj = [];
            $status = 'SUCCESS';

            // info('SpreadSheet', [
            //     'cekSpreadsheet' => $cekSpreadsheet
            // ]);

            if (isset($cekSpreadsheet['error'])) {
                $decodedError = json_decode($cekSpreadsheet['error'], true);
                $errorObj = $decodedError;
                $status = $decodedError['error']['status'] ?? 'UNKNOWN_STATUS';
                $status = str_replace('_', ' ', $status);
            }

            if (!$cekSpreadsheet['success']) {
                return response()->json([
                    'result' => 'error',
                    'message' => 'Cannot access the spreadsheet. Please check your Google Spreadsheet Setting.',
                    'errorDetail' => $errorObj,
                    'status' => $status,
                ]);
            } else {
                return response()->json([
                    'result' => 'success',
                    'message' => 'Spreadsheet connection was successful.',
                    'errorDetail' => $errorObj,
                    'status' => $status,
                    'spreadsheed_id' => $leadspeekUser->spreadsheet_id,
                ], 200);
            }
        }
    }

    public function getHistorySpreadsheet($leadspeek_api_id) {
        $history = DB::table('spreadsheet_history')
                        ->where('leadspeek_api_id', $leadspeek_api_id)
                        ->orderBy('created_at','desc')
                        ->get()
                        ->map(function($item) {
                            $item->created_at = \Carbon\Carbon::parse($item->created_at)->format('Y-m-d');
                            return $item;
                        });
        return response()->json($history);
    }

    public function resetSpreadsheet(Request $request) {
        $user_login = (isset($request->userLogin)) ? $request->userLogin : '';
        $campaign_id = (isset($request->campaignID)) ? $request->campaignID : '';

        $leadspeekUser = LeadspeekUser::where('leadspeek_users.id', $campaign_id)
                                        ->where('leadspeek_users.archived','F')
                                        ->join('users', 'users.id', '=', 'leadspeek_users.user_id')
                                        ->join('companies', 'companies.id', '=', 'users.company_id')
                                        ->select('leadspeek_users.*', 'companies.company_name as company_name', 'companies.id as company_id_user')
                                        ->first();

        if($leadspeekUser) {
            $leadspeek_api_id = $leadspeekUser->leadspeek_api_id;
            $spreadsheet_id_old = $leadspeekUser->spreadsheet_id;
            $leadspeekType = $leadspeekUser->leadspeek_type;
            $campaignName = $leadspeekUser->campaign_name;
            $clientHidePhone = $leadspeekUser->hide_phone;
            $phoneenabled = $leadspeekUser->phoneenabled;
            $homeaddressenabled = $leadspeekUser->homeaddressenabled;
            $leadspeek_locator_require = $leadspeekUser->leadspeek_locator_require;

            $tryseraID = $leadspeek_api_id;
            $company_name = $leadspeekUser->company_name;
            $company_id = $leadspeekUser->company_id;
            $company_id_user = $leadspeekUser->company_id_user;
            
            $user_id = $leadspeekUser->user_id;
            $admin_notify_to = $leadspeekUser->admin_notify_to;
            $adminNotifyTo = explode(',', $admin_notify_to);
            $reportSentTo = $leadspeekUser->report_sent_to;

            $advanceInformation = isset($leadspeekUser->advance_information) ? $leadspeekUser->advance_information : '';
            
            //// insert history AND create new google sheet 
            try {
                DB::beginTransaction();

                DB::table('spreadsheet_history')->insert([
                                                    'leadspeek_api_id' => $leadspeek_api_id,
                                                    'spreadsheet_id' => $spreadsheet_id_old,
                                                    'created_at' => now(),
                                                ]);


                $campaignInformation = campaignInformation::where('status', 'active')->where('campaign_type','enhance')->get();

                /** CREATE GOOGLE SHEET ONLINE */
                $spreadsheetTitle = trim($company_name . ' - ' . $campaignName . ' #' . $tryseraID);
                $clientGoogle = new GoogleSheet($company_id, $this->_moduleID, $this->_settingTokenName, '', true, $leadspeekUser->leadspeek_api_id ?? "", $leadspeekUser->leadspeek_type ?? "", __FUNCTION__);
                $spreadSheetID = $clientGoogle->createSpreadSheet($spreadsheetTitle);
                $clientGoogle->updateSheetTitle(date('Y'));

                $clientGoogle->setSheetName(date('Y'));
                
                /** INTIAL GOOGLE SPREADSHEET HEADER */
                //$contentHeader[] = array('ID','Email','IP','Source','OptInDate','ClickDate','Referer','Phone','First Name','Last Name','Address1','Address2','City','State','Zipcode');
                $additonalCol = "";
                $content_b2b = array();
                if (strtolower($leadspeekType) == 'local') {
                    $contentHeader[] = array('ID','ClickDate','First Name','Last Name','Email1','Email2','Phone1','Phone2','Address1','Address2','City','State','Zipcode','Landing URL');
                    $additonalCol = "https://example.com";
                }else if(strtolower($leadspeekType) == 'locator' || strtolower($leadspeekType) == 'enhance'){
                    $contentHeader[] = array('ID','ClickDate','First Name','Last Name','Email1','Email2','Phone1','Phone2','Address1','Address2','City','State','Zipcode','Keyword');

                    if(strtolower($leadspeekType) == 'enhance' && !empty($advanceInformation) && trim($advanceInformation) != '') {
                        $campaignInformationArray = [];
                        foreach($campaignInformation as $item)
                        {
                            $description = json_decode($item->description, true);
                            $campaignInformationArray = array_merge($campaignInformationArray, $description);
                        }
                        $contentHeader[0] = array_merge($contentHeader[0], $campaignInformationArray);
                    }

                    $additonalCol = "keyword";
                }else if(strtolower($leadspeekType) == 'b2b') {
                    $additonalCol = "keyword";

                    $settingJson = DB::table('global_settings')
                                        ->where('setting_name', 'spreadsheet_b2b_ordering')
                                        ->value('setting_value');

                    $dtJson = json_decode($settingJson, true);
                    $startDate_spreadsheet = isset($dtJson[0]['start-date']) ? Carbon::parse($dtJson[0]['start-date']) : null;
                    // info('',['today'=>Carbon::today(),
                    //         'startDate_spreadsheet'=>$startDate_spreadsheet,
                    //         'dtJson'=>$dtJson,
                    //         'settingJson'=>$settingJson
                    // ]);
                    //// cek setingan di global seting.. 
                    // ketika today lebih dari tgl yg di global seting, pakai data dan indexing baru, 
                    // else pakai data dan indexing lama
                    if ($startDate_spreadsheet && Carbon::today()->greaterThanOrEqualTo($startDate_spreadsheet)) {
                        if(!empty($advanceInformation) && trim($advanceInformation) != '') {
                            $contentHeader[] = array('ID','Company Name','First Name','Last Name','Company Email','Company Phone','Company Website','Job Title','Level','Job Function','LinkedIn','ClickDate','Phone1','Address1','Address2','City','State','Zipcode','Keyword','Num Employees','Sales Volume','Year Founded');
                            $content_b2b = ['00000000','Ai Company','John','Doe','johndoe1-' . $tryseraID . '@example.com','123-123-1234','aicompany.com','Sales Staff','Staff','Sales','https://www.linkedin.com/in/jhon-doe',date('m/d/Y h:i:s A'),'123-123-1234',$tryseraID . ' John Doe Street','suite 101','Columbus','OH','43055',$additonalCol,'1 TO 49','Less Than $500.000','1999'];
    
                        } else {
                            $contentHeader[] = array('ID', 'First Name', 'Last Name', 'Company Email', 'ClickDate', 'Phone1', 'Address1', 'Address2', 'City', 'State', 'Zipcode', 'Keyword');
                            $content_b2b =  ['00000000','John','Doe','johndoe1-' . $tryseraID . '@example.com',date('m/d/Y h:i:s A'),'123-123-1234',$tryseraID . ' John Doe Street','suite 101','Columbus','OH','43055',$additonalCol];
                        }
                    } else {
                        $contentHeader[] = array('ID','ClickDate','First Name','Last Name','Email1','Email2','Phone1','Phone2','Address1','Address2','City','State','Zipcode','Keyword','Employee ID','Company_Name','Company_Phone','Company_Website','Num_Employees','Sales_Volume','Year_Founded','Job_Title','Level','Job_Function','Headquarters_Branch','NAICS_CODE','LastSeenDate','LinkedIn');
                        $content_b2b = ['123456789','AI COMPANY','123-123-1234','AICOMPANY.COM','1 TO 49','LESS THAN $500.000','1999','SALES STAFF','STAFF','SALES','0','441310','09/01/2023','https://www.linkedin.com/in/jhon-doe'];
                    }

                    
                    
                    
                    // $contentHeader[] = array('ID','ClickDate','First Name','Last Name','Email1','Email2','Phone1','Phone2','Address1','Address2','City','State','Zipcode','Keyword');
                }  
                else {
                    $contentHeader[] = array('ID','ClickDate','First Name','Last Name','Email1','Email2','Phone1','Phone2','Address1','Address2','City','State','Zipcode','Keyword','Employee ID','Company_Name','Company_Phone','Company_Website','Num_Employees','Sales_Volume','Year_Founded','Job_Title','Level','Job_Function','Headquarters_Branch','NAICS_CODE','LastSeenDate','LinkedIn');
                    $additonalCol = "keyword";
                }
                /** INTIAL GOOGLE SPREADSHEET HEADER */
                $savedData = $clientGoogle->saveDataHeaderToSheet($contentHeader);

                /** ADD DUMMY DATA ON FIRST ROW FOR ZAPIER TEST */
                //$content[] = array('00000000','johndoe@example.com','','',date('m/d/Y h:i:s A'),date('m/d/Y h:i:s A'),'','','John','Doe','John Doe Street','','Columbus','OH','43055');
                $content[] = array('00000000',date('m/d/Y h:i:s A'),'John','Doe','johndoe1-' . $tryseraID . '@example.com','johndoe2-' . $tryseraID . '@example.com','123-123-1234','567-567-5678',$tryseraID . ' John Doe Street','suite 101','Columbus','OH','43055',$additonalCol);
                
                if(strtolower($leadspeekType) == 'enhance' && !empty($advanceInformation) && trim($advanceInformation) != '') {
                    $campaignInformationArray = [];
                    foreach($campaignInformation as $item)
                    {
                        $description = json_decode($item->description, true);
                        $campaignInformationArray = array_merge($campaignInformationArray, $description);
                    }
                    $content[0] = array_merge($content[0], $campaignInformationArray);
                } else if(strtolower($leadspeekType) == 'b2b') {
                    // $contentB2b = ['123456789','AI COMPANY','123-123-1234','AICOMPANY.COM','1 TO 49','LESS THAN $500.000','1999','SALES STAFF','STAFF','SALES','0','441310','09/01/2023','https://www.linkedin.com/in/jhon-doe'];
                    // $content[0] = array_merge($content[0],$contentB2b);
                    $content[0] = $content_b2b;
                }

                $savedData = $clientGoogle->saveDataToSheet($content);
                /** ADD DUMMY DATA ON FIRST ROW FOR ZAPIER TEST */

                $leadspeekUser->spreadsheet_id = $spreadSheetID;
                $leadspeekUser->save();

                DB::commit();

                /** CHECK IF PHONE HIDE OR NOT */
                $hidePhone = ($clientHidePhone == 'T')?true:false;
                $sheetID = $clientGoogle->getSheetID(date('Y'));
                /** HIDE EVERYTHING ELSE BY DEFAULT EXCEPT firstname, lastname, email, click date, and address */
                $clientGoogle->showhideColumn($sheetID,0,1,'T');
                //$clientGoogle->showhideColumn($sheetID,2,5,'T');
                //$clientGoogle->showhideColumn($sheetID,6,7,'T');
                //$clientGoogle->showhideColumn($sheetID,7,8,$hidePhone);

                /*if (strtolower($leadspeekType) == 'local') {
                    $clientGoogle->showhideColumn($sheetID,7,8,'T');
                }else{
                */
                
                    if (trim($leadspeek_locator_require) == "FirstName,LastName") {
                        if($leadspeekType == 'b2b') {
                            $clientGoogle->showhideColumn($sheetID,12,13,'T');
                            $clientGoogle->showhideColumn($sheetID,13,18,'F');
                        } else {
                        $clientGoogle->showhideColumn($sheetID,6,8,'T');
                        $clientGoogle->showhideColumn($sheetID,8,13,'F');
                        //$clientGoogle->showhideColumn($sheetID,11,12,'T');
                        }
                    }else if (trim($leadspeek_locator_require) == 'FirstName,LastName,MailingAddress') {
                        if($leadspeekType == 'b2b') {
                            $clientGoogle->showhideColumn($sheetID,12,13,'T');
                            $clientGoogle->showhideColumn($sheetID,13,18,'F');
                        } else {
                            $clientGoogle->showhideColumn($sheetID,6,8,'T');
                            $clientGoogle->showhideColumn($sheetID,8,13,'F');
                            //$clientGoogle->showhideColumn($sheetID,11,12,'F');
                        }
                    }else if (trim($leadspeek_locator_require) == 'FirstName,LastName,MailingAddress,Phone') {

                            /** NEW OPTIONS FOR ENABLE PHONE AND MAIL ADDRESS */
                            if($phoneenabled == 'T' && $homeaddressenabled == 'F') {
                                if($leadspeekType == 'b2b') {
                                    $clientGoogle->showhideColumn($sheetID,5,6,'F');
                                    $clientGoogle->showhideColumn($sheetID,12,13,'F');
                                    $clientGoogle->showhideColumn($sheetID,13,18,'T');
                                } else {
                                    $clientGoogle->showhideColumn($sheetID,6,8,'F');
                                    $clientGoogle->showhideColumn($sheetID,8,13,'T');
                                    // if($leadspeekType == 'b2b') {
                                    //     $clientGoogle->showhideColumn($sheetID,16,17,'F');
                                    // }
                                }
                                //$clientGoogle->showhideColumn($sheetID,11,12,'T');
                                //$clientGoogle->showhideColumn($sheetID,12,13,'T');
                                //$clientGoogle->showhideColumn($sheetID,13,14,'T');
                                //$clientGoogle->showhideColumn($sheetID,14,15,'T');
                            }else if($phoneenabled == 'F' && $homeaddressenabled == 'T') {
                                if($leadspeekType == 'b2b') {
                                    $clientGoogle->showhideColumn($sheetID,5,6,'T');
                                    $clientGoogle->showhideColumn($sheetID,12,13,'T');
                                    $clientGoogle->showhideColumn($sheetID,13,18,'F');
                                } else {
                                    $clientGoogle->showhideColumn($sheetID,6,8,'T');
                                    $clientGoogle->showhideColumn($sheetID,8,13,'F');
                                    // if($leadspeekType == 'b2b') {
                                    //     $clientGoogle->showhideColumn($sheetID,16,17,'T');
                                    // }
                                }
                                //$clientGoogle->showhideColumn($sheetID,11,12,'F');
                                //$clientGoogle->showhideColumn($sheetID,12,13,'F');
                                //$clientGoogle->showhideColumn($sheetID,13,14,'F');
                                //$clientGoogle->showhideColumn($sheetID,14,15,'F');
                            }else if($phoneenabled == 'F' && $homeaddressenabled == 'F') {
                                if($leadspeekType == 'b2b') {
                                    $clientGoogle->showhideColumn($sheetID,5,6,'T');
                                    $clientGoogle->showhideColumn($sheetID,12,13,'T');
                                    $clientGoogle->showhideColumn($sheetID,13,18,'T');
                                } else {
                                    $clientGoogle->showhideColumn($sheetID,6,8,'T');
                                    $clientGoogle->showhideColumn($sheetID,8,13,'T');
                                }
                                //$clientGoogle->showhideColumn($sheetID,11,12,'T');
                                //$clientGoogle->showhideColumn($sheetID,12,13,'T');
                                //$clientGoogle->showhideColumn($sheetID,13,14,'T');
                                //$clientGoogle->showhideColumn($sheetID,14,15,'T');
                            }else{
                                if($leadspeekType == 'b2b') {
                                    $clientGoogle->showhideColumn($sheetID,5,6,'F');
                                    $clientGoogle->showhideColumn($sheetID,12,13,'F');
                                    $clientGoogle->showhideColumn($sheetID,13,18,'F');
                                } else {
                                    $clientGoogle->showhideColumn($sheetID,6,8,'F');
                                    $clientGoogle->showhideColumn($sheetID,8,13,'F');
                                }
                                //$clientGoogle->showhideColumn($sheetID,11,12,'F');
                                //$clientGoogle->showhideColumn($sheetID,12,13,'F');
                                //$clientGoogle->showhideColumn($sheetID,13,14,'F');
                                //$clientGoogle->showhideColumn($sheetID,14,15,'F');
                            }
                            /** NEW OPTIONS FOR ENABLE PHONE AND MAIL ADDRESS */
                    }

                    if(strtolower($leadspeekType) == 'enhance' && !empty($advanceInformation) && trim($advanceInformation) != '') {
                        /* SEMBUNYIKAN CAMPAIGN INFORMATION YANG TIDAK DIPILIH */
                        $advanceInformationId = explode(',', $advanceInformation);
                        $campaignInformationNotInId = campaignInformation::whereNotIn('id', $advanceInformationId)
                                                                         ->where('status', 'active')
                                                                         ->where('campaign_type','enhance')
                                                                         ->get();
                        foreach($campaignInformationNotInId as $item)
                        {
                            $startIndex = intval($item->start_index);
                            
                            $countDescription = count(json_decode($item->description, true));
                            $endIndex = intval($startIndex + $countDescription);
                            // info(['startIndex' => $startIndex, 'endIndex' => $endIndex]);

                            // $endIndex = intval($item->end_index + 1);
                            // info("sembunyikan {$item->type}", ['startIndex' => $startIndex, 'endIndex' => $endIndex]);
                            $clientGoogle->showhideColumn($sheetID, $startIndex, $endIndex, 'T');
                        }
                        /* SEMBUNYIKAN CAMPAIGN INFORMATION YANG TIDAK DIPILIH */
                    }

                    if(strtolower($leadspeekType) == 'b2b' && !empty($advanceInformation) && trim($advanceInformation) != '') {
                        /* SEMBUNYIKAN CAMPAIGN INFORMATION YANG TIDAK DIPILIH */
                        $advanceInformationId = explode(',', $advanceInformation);
                        $campaignInformationNotInId = campaignInformation::whereNotIn('id', $advanceInformationId)
                                                                         ->where('status', 'active')
                                                                         ->where('campaign_type','b2b')
                                                                         ->orderBy('id','asc')
                                                                         ->get();
                        foreach($campaignInformationNotInId as $item)
                        {
                            $startIndex = intval($item->start_index);
                            
                            $countDescription = count(json_decode($item->description, true));
                            $endIndex = intval($startIndex + $countDescription);

                            $clientGoogle->showhideColumn($sheetID, $startIndex, $endIndex, 'T');
                        }
                        /* SEMBUNYIKAN CAMPAIGN INFORMATION YANG TIDAK DIPILIH */
                    }
                //}
                //$clientGoogle->showhideColumn($sheetID,11,12,'T');
                /** HIDE EVERYTHING ELSE BY DEFAULT EXCEPT firstname, lastname, email, click date, and address */
                /** CHECK IF PHONE HIDE OR NOT */

                /** CREATE GOOGLE SHEET ONLINE */

                /** SHARE SPREADSHEET WITH ROLE AS VIEWER */
                /*$tmpSentTo = explode(PHP_EOL, $request->reportSentTo);
                foreach($tmpSentTo as $sto) {
                    $permission = $clientGoogle->createPermission($spreadSheetID,$sto,'user','reader',false);
                }*/
                /** SHARE SPREADSHEET WITH ROLE AS VIEWER */

                /** SENT TO CLIENT AND ADMIN THE LINK FOR THE PERMISSION */
        
                /** FIND ADMIN EMAIL */
                    $tmp = User::select('email')->whereIn('id', $adminNotifyTo)->get();
                    $adminEmail = array();
                    foreach($tmp as $ad) {
                        $permission = $clientGoogle->createPermission($spreadSheetID,$ad['email'],'user','writer',false);
                        array_push($adminEmail,$ad['email']);
                    }
                
                    /** SENT ALSO TO EMAIL CLIENT */
                    // cek setting di client management apakah client aktif sebagai editor??
                    $cek_editor_client = User::where('id',$user_id)
                                                ->where('editor_spreadsheet','T')
                                                ->where('active','T')
                                                ->exists();
                    if ($cek_editor_client) {
                        $permission_client = 'writer';
                    } else {
                        $permission_client = 'reader';
                    }

                    $tmp = explode(PHP_EOL, $reportSentTo);
                    foreach($tmp as $ad) {
                        // $permission = $clientGoogle->createPermission($spreadSheetID,$ad,'user','reader',false);
                        $permission = $clientGoogle->createPermission($spreadSheetID,$ad,'user',$permission_client,false);
                        array_push($adminEmail,$ad);
                    }
                    /** SENT ALSO TO EMAIL CLIENT */


                    //// insert user logs
                    $userID = (isset($user_login))?$user_login:'';
                    $action = 'Reset Spreadsheet';
                    $desc = 'Reset Google Spreadsheet CampaignID : '.$tryseraID.' | Campaign Name : '.$campaignName.' | spreadsheet_id from : '.$spreadsheet_id_old. ' to : '. $spreadSheetID;
                    $target = $user_id;
                    $ipAddress = ($request->header('Clientip') !== null)?$request->header('Clientip'):$request->ip();

                    $loguser = $this->logUserAction($userID,$action,$desc,$ipAddress,$target);
                    //// END insert user logs

                    return response()->json([
                        'result' => 'success',
                        'message' => 'Reset Google Spreadsheet Client Success.',
                    ]);
                    
                /** FIND ADMIN EMAIL */
            } catch(Exception $e) {
                DB::rollBack();

                Log::warning("Reset Google Spreadsheet Client Failed (L472) ErrMsg:" . $e->getMessage() . " CampaignID:" . $tryseraID . ' Campaign Name:' . $campaignName );
                return response()->json(array('result'=>'failed','message'=>'Sorry, system can not create Spreadsheet, please make sure all data valid and try again later. (' . $e->getMessage() . ')','data'=>array()));
            }


        } else {
            return response()->json([
                'result' => 'error',
                'message' => 'Campaign Not Found.',
            ]);
        }

    }

    public function getBalanceWallet(Request $request)
    {
        /* VARIABLE */
        $company_id = $request->company_id ?? '';
        /* VARIABLE */
        
        /* CHECK AGENCY EXISTS */
        $systemid = config('services.application.systemid');
        $user = User::select('id','amount','last_balance_amount','custom_amount','stopcontinual')
            ->where('company_id','=',$company_id)
            ->where('company_parent','=',$systemid)
            ->where('active','=','T')
            ->where('user_type','=','userdownline')
            ->first();
        if(!$user){
            return response()->json(['result' => 'error', 'message' => 'agency not found'], 404);
        }
        /* CHECK AGENCY EXISTS */

        /* GET SERVICE AGREEMENT DATA WALLET */
        $user_id = $user->id ?? null;
        $featureDataWalletId = MasterFeature::where('slug', 'data_wallet')->value('id');
        $serviceAgreement = ServicesAgreement::where('user_id','=',$user_id)
            ->where('feature_id','=',$featureDataWalletId)
            ->first();
        $agencyAgreeDataWallet = (isset($serviceAgreement->status) && $serviceAgreement->status == 'T') ? 
                                 ($serviceAgreement->status) :
                                 "F";
        // info(['agencyAgreeDataWallet' => $agencyAgreeDataWallet]);
        if($agencyAgreeDataWallet != 'T'){
            return response()->json(['result' => 'success', 'agencyAgreeDataWallet' => false, 'balance_amount' => 0]);
        }
        /* GET SERVICE AGREEMENT DATA WALLET */

        /* GET BALANCE WALLET AGENCY */
        $balanceAmount = TopupAgency::where('company_id','=',$company_id)
            ->where('topup_status','<>','done')
            ->whereNull('expired_at')
            ->orderBy('id','asc')
            ->sum('balance_amount');
        /* GET BALANCE WALLET AGENCY */

        return response()->json(['result' => 'success', 'agencyAgreeDataWallet' => true, 'balance_amount' => $balanceAmount]);
    }

    public function submitCleanID(Request $request)
    {
        // info('submitCleanID');
        // info(__FUNCTION__, ['all' => $request->all]);

        /* VARIABLE */
        $app_url = env('APP_URL');
        $userIDLogin = optional($request->user())->id;
        $ipAddress = ($request->header('Clientip') !== null)?$request->header('Clientip'):$request->ip();
        $companyID = isset($request->companyID) ? $request->companyID : '';
        $module = isset($request->module) ? $request->module : '';
        $phone_number = $request->phone_number;
        $home_address = $request->home_address;
        $require_email = $request->require_email;
        $advanced_information = isset($request->advanced_information) ? $request->advanced_information : [];
        $source_provider = isset($request->source_provider) ? $request->source_provider : '';

        $md5 = isset($request->md5) ? $request->md5 : '';
        $md5Array = array_filter(
            array_map('trim', explode("\n", $md5)),
            fn($val) => $val !== ''
        );

        $cleanid_name = isset($request->file_name) ? $request->file_name : '';
        // info(__FUNCTION__, ['companyID' => $companyID,'module' => $module,'phone_number' => $phone_number,'home_address' => $home_address,'require_email' => $require_email,'installed_gtm' => $installed_gtm,'advanced_information' => $advanced_information,'md5' => $md5,'md5Array' => $md5Array,'cleanid_name' => $cleanid_name,]);
        /* VARIABLE */

        /* VALIDATION */
        if(empty($companyID) || trim($companyID) == '')
            return response()->json(['result' => 'error', 'message' => 'company id cannot be empty'], 400);
        if(empty($module) || trim($module) == '')
            return response()->json(['result' => 'error', 'message' => 'module cannot be empty'], 400);
        if(!is_array($advanced_information))
            return response()->json(['result' => 'error', 'message' => 'advanced information must be array'], 400);
        
        // info('', ['check1' => explode(',', $module),'check2' => in_array('advanced', explode(',', $module)),'advanced_information' => $advanced_information]);
        // if(in_array('advanced', explode(',', $module)) && count($advanced_information) == 0)
        //     return response()->json(['result' => 'error', 'message' => 'advanced information must be selected at least 1'], 400);
        if(count($md5Array) == 0)
            return response()->json(['result' => 'error', 'message' => 'md5 must be filled at least 1'], 400);
        if(empty($cleanid_name) || trim($cleanid_name) == '')
            return response()->json(['result' => 'error', 'message' => 'name cannot be empty'], 400);

        foreach($md5Array as $index =>  $md5) 
        {
            $isValidEmail = (bool) filter_var($md5, FILTER_VALIDATE_EMAIL);
            $isValidMd5 = (bool) preg_match('/^[a-f0-9]{32}$/i', $md5);
            $isValidSha1 = ($source_provider == 'wattdata') ? (bool) preg_match('/^[a-f0-9]{40}$/i', $md5) : false;
            $isValidSha256 = ($source_provider == 'wattdata') ? (bool) preg_match('/^[a-f0-9]{64}$/i', $md5) : false;
            // info('', ['isValidEmail' => $isValidEmail, 'isValidMd5' => $isValidMd5, 'isValidSha1' => $isValidSha1, 'isValidSha256' => $isValidSha256]);

            if($source_provider == 'wattdata' && !$isValidEmail && !$isValidMd5 && !$isValidSha1 && !$isValidSha256)
            {
                return response()->json(['result' => 'error', 'message' => "item '$md5' is not a valid md5 or email or sha1 or sha256"], 400);
            }
            elseif($source_provider == 'bigdbm' && !$isValidEmail && !$isValidMd5)
            {
                return response()->json(['result' => 'error', 'message' => "item '$md5' is not a valid md5 or email"], 400);
            }
        }
        /* VALIDATION */

        /* GET AGENCY */
        $agency = User::where('company_id', $companyID)
                      ->where('user_type', 'userdownline')
                      ->whereNotNull('company_parent')
                      ->where('active', 'T')
                      ->first();
        if(empty($agency))
            return response()->json(['result' => 'error', 'message' => 'Agency Not Found'], 404);
        $userID = $agency->id;
        $advanced_information_string = (is_array($advanced_information)) ? implode(',', $advanced_information) : "";
        /* GET AGENCY */

        try
        {
            /* HIT EMM API */
            // info('masuk try');
            $form_params = [
                'userid' => $userID,
                'module' => 'basic',
                'phone_number' => true,
                'home_address' => true,
                'require_email' => true,
                // 'advance_information' => $advanced_information,
                'raw_text' => implode(',', $md5Array),
                'file_name' => $cleanid_name,
                'file_type' => 'Manual',
                'source_type' => 'ui',
                'app_url' => $app_url,
                'source_provider' => $source_provider,
            ];
            $client = new Client;
            $response = $client->post(env('APISERVER_URL').'/api/tools/clean-id/upload', [
                'form_params' => $form_params
            ]);
            $responseData = json_decode($response->getBody()->getContents(), true);
            // info('', ['responseData' => $responseData]);
            $statusCode = $response->getStatusCode();
            /* HIT EMM API */

            /* GET BALANCE WALLET */
            $dataRequest = new Request(['company_id' => $companyID]);
            $getBalanceWallet = $this->getBalanceWallet($dataRequest)->getData();
            $balance_amount = isset($getBalanceWallet->balance_amount) ? $getBalanceWallet->balance_amount : 0;
            /* GET BALANCE WALLET */

            /* LOG USER DATA */
            $file_name  = $responseData['file_name'] ?? "";
            $clean_api_id = $responseData['clean_api_id'] ?? "";
            $total_entries = $responseData['total_entries'] ?? "";
            $cost_cleanid = $responseData['cost_cleanid'] ?? "";
            $descriptionLogs = "source type : ui | file type : manual | file name : {$file_name} | company id : {$companyID} | advanced information : {$advanced_information_string} | cost per contact : {$cost_cleanid} | total entries : {$total_entries} | clean api id : {$clean_api_id}";
            $this->logUserAction($userIDLogin, "Clean ID Submit Manual From UI", $descriptionLogs, $ipAddress, $userID);
            /* LOG USER DATA */

            return response()->json(['result' => 'success','message' => 'Upload Clean ID MD5 Successfully', 'balance_amount' => $balance_amount]); 
        } 
        catch (\GuzzleHttp\Exception\ClientException $e) 
        {
            // info('', ['error1' => $e->getMessage()]);
            $resCode = $e->getCode();
            $resSts = 'error';

            $responseBody = $e->getResponse()->getBody()->getContents();
            $json = json_decode($responseBody, true);
            $resMsg = $json['error'] ?? 'Unexpected error';
            $errorType = $json['error_type'] ?? "";
            // info(['resMsg1' => $resMsg]);

            /* log user */
            $descriptionLogs = "source type : ui | file type : manual | company id : {$companyID} | advanced information : {$advanced_information_string}";
            $this->logUserAction($userIDLogin, "Clean ID Submit Manual From UI Failed", $descriptionLogs, $ipAddress, $userID);
            /* log user */

            return response()->json(['result' => 'error', 'error_type' => $errorType, 'message' => $resMsg], 400);
        }
        catch(\Exception $e)
        {
            // info('', ['error2' => $e->getMessage()]);

            /* log user */
            $descriptionLogs = "source type : ui | file type : manual | company id : {$companyID} | advanced information : {$advanced_information_string}";
            $this->logUserAction($userIDLogin, "Clean ID Submit Manual From UI Failed", $descriptionLogs, $ipAddress, $userID);
            /* log user */

            return response()->json(['result' => 'error', 'message' => 'Something wrong, please try again'], 400);
        }
    }

    public function getDataFiles(string $company_id = '', Request $request)
    {
        /* GET AGENCY */
        $agency = User::where('company_id', $company_id)
                      ->where('user_type', 'userdownline')
                      ->where('active', 'T')
                      ->first();
        if(empty($agency)){
            return response()->json(['result' => 'error', 'message' => 'agency not found'], 404);
        }
        $user_id = $agency->id;
        /* GET AGENCY */

        /* VARIABLE */
        $filterStatus = ['pending' => 'pending', 'processed' => 'process', 'done' => 'done'][strtolower($request->filterStatus ?? '')] ?? '';
        $searchkey = $request->searchkey ?? '';
        $Page = $request->Page ?? '';
        $PerPage = $request->PerPage ?? 10;
        $sortby = (isset($request->sortby) && $request->sortby != '0' && !empty($request->sortby)) ? (trim($request->sortby)) : ('date');
        $order = (isset($request->order) && $request->order != '0' && !empty($request->order)) ? (['descending' => 'DESC'][trim($request->order)] ?? 'ASC') : ('DESC');
        // info(['searchkey' => $searchkey,'filterStatus' => $filterStatus,'page' => $Page,'perPage' => $PerPage,'sortby' => $sortby,'order' => $order]);
        /* VARIABLE */

        /* GET DATA */
        $cleanIdFiles = CleanIDFile::select([
                'id',
                'clean_api_id',
                'source_provider as provider',
                'file_name as name',
                'file_type as file_type',
                'source_type as source_type',
                'created_at as date',
                'status',
                'entries',
                'module',
                DB::raw("(SELECT COUNT(*) FROM clean_id_md5 WHERE clean_id_md5.file_id = clean_id_file.id AND clean_id_md5.status = 'found') AS found"),
                DB::raw("(SELECT COUNT(*) FROM clean_id_md5 WHERE clean_id_md5.file_id = clean_id_file.id AND clean_id_md5.status = 'not_found') AS not_found"),
                DB::raw("(SELECT COUNT(*) FROM clean_id_md5 WHERE clean_id_md5.file_id = clean_id_file.id AND clean_id_md5.status = 'duplicate') AS duplicate"),
            ])
            ->where('user_id', $user_id);

        if(!empty($searchkey) && trim($searchkey) != ''){
            // info('masuk searchkey');
            $cleanIdFiles->where(function ($query) use ($searchkey) {
                $query->where('file_name','like',"%{$searchkey}%")
                      ->orWhere('clean_api_id','like',"%{$searchkey}%");
            });
        }
        if(!empty($filterStatus) && in_array($filterStatus, ['pending', 'process', 'done'])){
            // info('masuk filter');
            $cleanIdFiles->where('status', $filterStatus);
        }
        if($sortby == 'date'){
            $cleanIdFiles->orderBy('date', $order);
        }
        
        $cleanIdFiles = $cleanIdFiles->paginate($PerPage, ['*'], 'page', $Page)
            ->through(function ($item) {
                if($item->status != 'done'){
                    $item->found = "-";
                    $item->not_found = "-";
                    $item->duplicate = "-";
                }

                $item->provider = strtolower($item->provider ?? "") == 'wattdata' ? "WattData" : "BigDBM";
                $source_type = strtolower($item->source_type ?? "")  == 'api' ? "API / " : "";
                $file_type = strtolower($item->file_type ?? "") == 'upload' ? "Upload" : "Manual";
                $item->type = "{$source_type}{$file_type}";

                $item->date = Carbon::parse($item->date)->format('d-m-Y');
                $item->status = ucfirst($item->status); // Field Status jadi huruf kapital depan 
                $item->module = array_map('trim', explode(',', $item->module)); // field module jadi array []
                return $item;
            });
        /* GET DATA */

        /* GET BALANCE WALLET */
        $dataRequest = new Request(['company_id' => $company_id]);
        $getBalanceWallet = $this->getBalanceWallet($dataRequest)->getData();
        $balance_amount = isset($getBalanceWallet->balance_amount) ? $getBalanceWallet->balance_amount : 0;
        /* GET BALANCE WALLET */

        return response()->json(['result' => 'success', 'clean_id_files' => $cleanIdFiles, 'balance_amount' => $balance_amount]);
    }

    public function getMd5Result(string $file_id = '')
    {

        $dt_file = DB::table('clean_id_result')
                        ->where('file_id', $file_id)
                        ->selectRaw("id, CONCAT(first_name, ' ', last_name) as name,
                                    phone as phone1, phone2, address as address1, address2,
                                    city, state, zip as zipcode")
                        ->get();

        return response()->json($dt_file);
    }

    public function downloadResultCleanID(string $file_id = '', string $client_id = '')
    {

       $export = CleanIDExport::create([
            'user_id' => auth()->id(),
            'client_id' => $client_id,
            'file_id' => $file_id,
            'status' => 'processing'
        ]);

        CleanIDExportJob::dispatch($export->id, $file_id)->onQueue('cleanID_export');

        return response()->json(['export_id' => $export->id]);
    }

    public function exportStatus(CleanIDExport $export)
    {
        // Cek authorization user, agar gak sembarangan akses file orang lain
        if ($export->user_id !== auth()->id()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        return response()->json([
            'status' => $export->status,
            'download_url' => $export->file_path ? asset('storage/' . $export->file_path) : null,
        ]);
    }

    public function deleteFile(CleanIDExport $export)
    {
        // Cek authorization user, agar gak sembarangan akses file orang lain
        if ($export->user_id !== auth()->id()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        if ($export->file_path && Storage::disk('public')->exists($export->file_path)) {
            Storage::disk('public')->delete($export->file_path);
        }

        return response()->json(['status' => 'success', 'message' => 'File deleted.']);
    }

}
