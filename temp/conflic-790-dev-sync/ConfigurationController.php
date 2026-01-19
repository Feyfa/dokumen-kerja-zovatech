<?php

namespace App\Http\Controllers;

use App\Exports\AnalyticExport;
use App\Exports\FailedLeadRecordExport;
use App\Exports\AgenciesExport;
use App\Exports\ClientsExport;
use App\Exports\TransactionHistoryDirectPayment;
use App\Exports\UserLogsExport;
use App\Jobs\AdminToCampaignJob;
use App\Exports\CleanIDResultExport;
use App\Mail\Gmail;
use App\Models\campaignInformation;
use App\Models\CleanIDFile;
use App\Models\Company;
use App\Models\CompanyAgreementFile;
use App\Models\CompanySale;
use App\Models\PackagePlan;
use App\Models\CompanySetting;
use App\Models\CompanyStripe;
use App\Models\Coupon;
use App\Models\DomainRemove;
use App\Models\FeatureUser;
use App\Models\GlobalSettings;
use App\Models\LeadspeekInvoice;
use App\Models\LeadspeekReport;
use App\Models\LeadspeekUser;
use App\Models\MasterFeature;
use App\Models\MinimumSpendList;
use App\Models\Module;
use App\Models\PixelLeadRecord;
use App\Models\ReportAnalytic;
use App\Models\Role;
use App\Models\RoleModule;
use App\Models\User;
use App\Models\UserLog;
use App\Models\Topup;
use App\Models\Site;
use App\Models\TopupAgency;
use App\Services\Configuration\PixelLeadRecordService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx\Rels;
use ESolution\DBEncryption\Encrypter;
use Exception;
use Illuminate\Pagination\LengthAwarePaginator;
use Carbon\Carbon;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use stdClass;
use Stripe\Exception\ApiConnectionException;
use Stripe\Exception\ApiErrorException;
use Stripe\Exception\AuthenticationException as ExceptionAuthenticationException;
use Stripe\Exception\OAuth\InvalidRequestException;
use Stripe\Exception\RateLimitException;
use Stripe\StripeClient;
use Swift_Mailer;
use Swift_SmtpTransport;
use Swift_TransportException;

class ConfigurationController extends Controller
{
    private function getCurrentMinimumSpend($company_id)
    {
        date_default_timezone_set('America/Chicago');

        /* VARIABLE */
        $start_date_minimum_spend = '';
        $end_date_minimum_spend = '';
        /* VARIABLE */

        /* GET AGENCY */
        $systemid = config('services.application.systemid');
        $user = User::where('company_id', $company_id)
                    ->where('company_parent','=',$systemid)
                    ->where('active','=','T')
                    ->where('user_type','=','userdownline')
                    ->first();
        if(empty($user))
            return ['agencyMinimumSpendStartDate' => '', 'agencyMinimumSpendEndDate' => '', 'agencyTotalWholesaleSpend' => 0, 'agencyMinimumPlatformSpend' => 0, 'agencyMinimumSpendBilling' => 0];
        $company_root_id = $user->company_root_id;
        /* GET AGENCY */

        /* GET THE DATE TO DETERMINE THE MINIMUM EXPENDITURE OF THE AGENCY NOW IN WHAT MONTH */    
        // $now_date = Carbon::parse('2026-01-31')->format('Y-m-d'); 
        $now_date = Carbon::now()->format('Y-m-d');
        
        // last payment update
        $last_payment_update = $user->last_payment_update;
        $last_payment_update_date = Carbon::parse($last_payment_update)->format('Y-m-d');
        
        // month 1
        $thirtyDaysAfterDate = Carbon::parse($last_payment_update)->copy()->addMonth(1);
        $chkThirtyDayFwd = ($thirtyDaysAfterDate->day >= 29) ? ($thirtyDaysAfterDate->copy()->addMonth(1)->startOfMonth()->format('Y-m-d')) : ($thirtyDaysAfterDate->format('Y-m-d')) ;

        // month 2
        $sixtyDaysAfterDate = Carbon::parse($last_payment_update)->copy()->addMonth(2);
        $chkSixtyDayFwd = ($sixtyDaysAfterDate->day >= 29) ? ($sixtyDaysAfterDate->copy()->addMonth(1)->startOfMonth()->format('Y-m-d')) : ($sixtyDaysAfterDate->format('Y-m-d')) ;

        // last payment invoice / flat month
        $last_invoice_minspend = $user->last_invoice_minspend;
        $last_invoice_minspend_carbon = Carbon::parse($last_invoice_minspend);
        $last_invoice_minspend_day = $last_invoice_minspend_carbon->day;
        $last_invoice_minspend_date = $last_invoice_minspend_carbon->format('Y-m-d');

        // lats_payment_update - now_date - chkThirtyDayFwd
        // info('', ['last_payment_update_date' => $last_payment_update_date, 'now_date' => $now_date, 'chkThirtyDayFwd' => $chkThirtyDayFwd, 'chkSixtyDayFwd' => $chkSixtyDayFwd]);
        if($last_payment_update_date <= $now_date && $chkThirtyDayFwd > $now_date)
        {
            // info('check date masuk if');
            $start_date_minimum_spend = $last_payment_update_date;
            $end_date_minimum_spend = $chkThirtyDayFwd;
        }
        // chkThirtyDayFwd - now_date - chkSixtyDayFwd
        elseif($chkThirtyDayFwd <= $now_date && $chkSixtyDayFwd > $now_date)
        {
            // info('check date masuk elseif');
            $start_date_minimum_spend = $chkThirtyDayFwd;
            $end_date_minimum_spend = $chkSixtyDayFwd;
        }
        else 
        {
            // info('check date masuk else');
            $start_date_minimum_spend = $last_invoice_minspend_date;
            $end_date_minimum_spend = Carbon::parse($last_invoice_minspend)->addMonth(); 
            if($last_invoice_minspend_day >= 29) 
            {
                // info('check date masuk else ke if');
                $newlastInvoiceDate = Carbon::parse($last_invoice_minspend)->addMonthNoOverflow()->startOfMonth();
                $end_date_minimum_spend = $newlastInvoiceDate->copy()->addMonth();
            }
        }
        /* GET THE DATE TO DETERMINE THE MINIMUM EXPENDITURE OF THE AGENCY NOW IN WHAT MONTH */

        /* GET MINIMUM SPEND */
        $minSpendValue = 0;
        $plan_minspend_id = $user->plan_minspend_id;
        $api_mode = $user->api_mode;
        $is_marketing_services_agreement_developer = $user->is_marketing_services_agreement_developer;
        $month_developer_minspend = $user->month_developer_minspend;
        $use_developer = ($api_mode == 'T' && $is_marketing_services_agreement_developer == 'T' && $month_developer_minspend > 0);

        if($use_developer) // minspend developer api
        {
            // info('masuk ke minspend developer api');
            $getglobalsetting = GlobalSettings::where('company_id',$systemid)->where('setting_name','root_min_spend_developer_api')->first();
            $globalsetting = !empty($getglobalsetting) ? json_decode($getglobalsetting->setting_value) : (object) [];
            if(isset($globalsetting->minimum_spend) && count($globalsetting->minimum_spend) > 0)
            {
                foreach ($globalsetting->minimum_spend as $entry)
                {
                    if ($entry->month == $month_developer_minspend)
                    {
                        $minSpendValue = (float) $entry->amount;
                        break;
                    }
                }
            }
        }
        else // minspend biasa
        {
            // info('masuk ke minspend biasa');
            $last_payment_update_start_of_day = Carbon::parse($last_payment_update)->startOfDay();
            $end_date_minimum_spend_start_of_day = Carbon::parse($end_date_minimum_spend)->startOfDay();
            $minspend_month = (int) $last_payment_update_start_of_day->diffInMonths($end_date_minimum_spend_start_of_day);

            $getcompanysetting = CompanySetting::where('company_id',$systemid)->whereEncrypted('setting_name','rootminspend')->get();
            $companysetting = (count($getcompanysetting) > 0) ? json_decode($getcompanysetting[0]['setting_value']) : (object) [];
            $minspend_first_month = isset($companysetting->minspend_first_month) ? (float) $companysetting->minspend_first_month : 0;
            $minspend_second_month = isset($companysetting->minspend_second_month) ? (float) $companysetting->minspend_second_month : 0;
            $minSpend = isset($companysetting->minspend) ? (float) $companysetting->minspend : 0;
            
            if($minspend_month > 0)
            {
                if(is_numeric($plan_minspend_id))
                {
                    // info('masuk ke custom minimum spend');
                    $minimum_spend_plan = MinimumSpendList::where('active', 'T')->where('id', $plan_minspend_id)->first();
                    $plan_months = (isset($minimum_spend_plan->months) && !empty($minimum_spend_plan->months)) ? json_decode($minimum_spend_plan->months, true) : [];
                    if(!empty($plan_months))
                    {
                        // ambil flat dan bulan => value
                        foreach ($plan_months as $item) 
                        {
                            if(($item['month'] ?? null) == 'flat')
                            {
                                $flatMonth = (float) ($item['value'] ?? 0);
                            }
                            else
                            {
                                $planByMonth[(int) ($item['month'] ?? 0)] = (float) ($item['value'] ?? 0);
                            }
                        }
                        // info('masuk ke custom minimum spend', ['flatMonth' => $flatMonth,'planByMonth' => $planByMonth]);
                        // ambil flat dan bulan => value
                        $minSpendValue = $planByMonth[$minspend_month] ?? $flatMonth;
                    }
                }
                else 
                {
                    // info('masuk ke root minimum spend');
                    $rootminspendlist = [1 => $minspend_first_month, 2 => $minspend_second_month];
                    $minSpendValue = $rootminspendlist[$minspend_month] ?? $minSpend;
                }
            }
        }
        /* GET MINIMUM SPEND */

        /* GET TOTAL MINIMUM SPEND */
        $totalSpend = LeadspeekReport::from('leadspeek_reports as lr')
                                     ->join('users as u', 'lr.company_id', '=', 'u.company_id')
                                     ->where(function ($query) {
                                        $query->whereNull('lr.topup_id')
                                              ->orWhere('lr.topup_id', 0)
                                              ->orWhere('lr.topup_id', '');
                                     })
                                     ->where('lr.clickdate', '>=', $start_date_minimum_spend)
                                     ->where('lr.clickdate', '<', $end_date_minimum_spend)
                                     ->where('u.company_parent', $company_id)
                                     ->where('u.company_root_id', $company_root_id)
                                     ->where('u.active', 'T')
                                     ->where('u.user_type', '=', 'client')
                                     ->sum('lr.platform_price_lead') ?: 0;
        
        $totalSpendPrepaid = Topup::from('topup_campaigns as tc')
                                  ->join('users as u', 'tc.user_id', '=', 'u.id')
                                  ->where('tc.created_at', '>=', $start_date_minimum_spend)
                                  ->where('tc.created_at', '<', $end_date_minimum_spend)
                                  ->where('u.company_parent', $company_id)
                                  ->where('u.company_root_id', $company_root_id)
                                  ->where('u.active', 'T')
                                  ->where('u.user_type', '=', 'client')
                                  ->selectRaw('SUM(platform_price * total_leads) as total_spend')
                                  ->value('total_spend') ?: 0;
        
        $totalSpendFinal = $totalSpend + $totalSpendPrepaid;
        /* GET TOTAL MINIMUM SPEND */

        /* SAVE VARIABLE */
        $agencyMinimumSpendStartDate = Carbon::parse($start_date_minimum_spend)->format('F j, Y');
        $agencyMinimumSpendEndDate = Carbon::parse($end_date_minimum_spend)->format('F j, Y');
        $agencyTotalWholesaleSpend = number_format($totalSpendFinal,2,'.','');
        $agencyMinimumPlatformSpend = number_format($minSpendValue,2,'.','');
        $agencyMinimumSpendBilling = ($agencyTotalWholesaleSpend < $agencyMinimumPlatformSpend) ? number_format($agencyMinimumPlatformSpend - $agencyTotalWholesaleSpend,2,'.','') : 0;
        /* SAVE VARIABLE */

        // info(['start_date_minimum_spend' => Carbon::parse($start_date_minimum_spend)->format('Y-m-d'),'end_date_minimum_spend' => Carbon::parse($end_date_minimum_spend)->format('Y-m-d'),'agencyMinimumSpendStartDate' => $agencyMinimumSpendStartDate,'agencyMinimumSpendEndDate' => $agencyMinimumSpendEndDate,'agencyTotalWholesaleSpend' => $agencyTotalWholesaleSpend,'agencyMinimumPlatformSpend' => $agencyMinimumPlatformSpend,'agencyMinimumSpendBilling' => $agencyMinimumSpendBilling,]);

        return ['agencyMinimumSpendStartDate' => $agencyMinimumSpendStartDate, 'agencyMinimumSpendEndDate' => $agencyMinimumSpendEndDate, 'agencyTotalWholesaleSpend' => $agencyTotalWholesaleSpend, 'agencyMinimumPlatformSpend' => $agencyMinimumPlatformSpend, 'agencyMinimumSpendBilling' => $agencyMinimumSpendBilling];
    }

    public function getPrepaidDirectPayment(Request $request)
    {
        $company_id = (isset($request->company_id)) ? $request->company_id : '';
        $date = (isset($request->date)) ? $request->date : '';
        // info(['company_id' => $company_id, 'date' => $date]);

        /* GET AMOUNT, STOPCONTINUAL */
        $systemid = config('services.application.systemid');
        $user = User::select('id','amount','last_balance_amount','custom_amount','stopcontinual')
                    ->where('company_id','=',$company_id)
                    ->where('company_parent','=',$systemid)
                    ->where('active','=','T')
                    ->where('user_type','=','userdownline')
                    ->first();
        if(empty($user))
            return response()->json([
                'result' => 'failed',
                'message' => 'Agency Not Found'
            ], 404);
        $amount = (isset($user->amount) && !empty($user->amount)) ? $user->amount : 0;
        $last_balance_amount = (isset($user->last_balance_amount) && !empty($user->last_balance_amount)) ? $user->last_balance_amount : 0;
        $custom_amount = (isset($user->custom_amount) && !empty($user->custom_amount) && trim($user->custom_amount) != '') ? $user->custom_amount : 'F'; 
        $stop_continual = (isset($user->stopcontinual) && !empty($user->stopcontinual) && trim($user->stopcontinual) != '') ? $user->stopcontinual : 'F'; 
        /* GET AMOUNT, STOPCONTINUAL */

        /* GET BALANCE AND HISTORY */
        $total_amount = TopupAgency::where('company_id','=',$company_id)
                                   ->where('topup_status','<>','done')
                                   ->whereNull('expired_at')
                                   ->sum('total_amount');
        $balance_amount = TopupAgency::where('company_id','=',$company_id)
                                     ->where('topup_status','<>','done')
                                     ->whereNull('expired_at')
                                     ->sum('balance_amount');
        $balance_amount = (float) number_format($balance_amount,2,'.','');
        // info(['total_amount' => $total_amount, 'balance_amount' => $balance_amount]);
        $getTransactionHistory = $this->getTransactionHistoryDirectPayment($request)->getData();
        $transactionHistory = (isset($getTransactionHistory->transaction_history)) ? $getTransactionHistory->transaction_history : [];
        // info('', ['topupHistory' => $topupHistory]);
        /* GET BALANCE AND HISTORY */

        /* CHECK FOR SHOWING FEATURE STOPCONTINUAL OR NOT */
        $agencyManualBill = Company::where('id', $company_id)
                                   ->value('manual_bill');
        $agencyAgreeDeveloper = User::where('company_id', $company_id)
                                    ->where('user_type', 'userdownline')
                                    ->where('active', 'T')
                                    ->value('is_marketing_services_agreement_developer');
        $isEnableTopupPrepaidDirectPayment = (($agencyManualBill == 'T') || ($agencyAgreeDeveloper == 'T')); 
        // info(['agencyManualBill' => $agencyManualBill, 'agencyAgreeDeveloper' => $agencyAgreeDeveloper, 'isEnableTopupPrepaidDirectPayment' => $isEnableTopupPrepaidDirectPayment]);
        /* CHECK FOR SHOWING FEATURE STOPCONTINUAL OR NOT */

        /* GET CURRENT MINIMUM SPEND */
        $getCurrentMinimumSpend = $this->getCurrentMinimumSpend($company_id);
        $agencyMinimumSpendStartDate = $getCurrentMinimumSpend['agencyMinimumSpendStartDate'] ?? '';
        $agencyMinimumSpendEndDate = $getCurrentMinimumSpend['agencyMinimumSpendEndDate'] ?? '';
        $agencyTotalWholesaleSpend = $getCurrentMinimumSpend['agencyTotalWholesaleSpend'] ?? 0;
        $agencyMinimumPlatformSpend = $getCurrentMinimumSpend['agencyMinimumPlatformSpend'] ?? 0;
        $agencyMinimumSpendBilling = $getCurrentMinimumSpend['agencyMinimumSpendBilling'] ?? 0;
        /* GET CURRENT MINIMUM SPEND */

        /* VALIDATION WHEN THE USER FIRST OPENS WALLET DATA AND CUSTOM AMOUNT 0 */
        // if($amount == 0) 
        // {
        //     $user->amount = 250;
        //     $user->custom_amount = 'F';
        //     $user->save();

        //     $amount = (isset($user->amount) && !empty($user->amount)) ? $user->amount : 0;
        //     $custom_amount = (isset($user->custom_amount) && !empty($user->custom_amount) && trim($user->custom_amount) != '') ? $user->custom_amount : 'F'; 
        // }
        /* VALIDATION WHEN THE USER FIRST OPENS WALLET DATA AND CUSTOM AMOUNT 0 */

        return response()->json([
            'amount' => $amount,
            'stop_continual' => $stop_continual,
            'total_amount' => $total_amount,
            'balance_amount' => $balance_amount,
            'last_balance_amount' => $last_balance_amount,
            'custom_amount' => $custom_amount,
            'transaction_history' => $transactionHistory,
            'is_enable_topup_prepaid_direct_payment' => $isEnableTopupPrepaidDirectPayment,
            'agencyMinimumSpendStartDate' => $agencyMinimumSpendStartDate,
            'agencyMinimumSpendEndDate' => $agencyMinimumSpendEndDate,
            'agencyTotalWholesaleSpend' => $agencyTotalWholesaleSpend,
            'agencyMinimumPlatformSpend' => $agencyMinimumPlatformSpend,
            'agencyMinimumSpendBilling' => $agencyMinimumSpendBilling,
        ]);
    }

    public function getTransactionHistoryDirectPayment(Request $request)
    {
        $company_id = (isset($request->company_id)) ? $request->company_id : '';
        $date = (isset($request->date)) ? $request->date : '';

        /* VALIDATION AGENCY ONLY EMM */
        $systemid = config('services.application.systemid');
        $userExists = User::where('company_id','=',$company_id)
                          ->where('company_parent','=',$systemid)
                          ->where('active','=','T')
                          ->where('user_type','=','userdownline')
                          ->exists();
        if(!$userExists)
            return response()->json([
                'result' => 'failed',
                'message' => 'Agency Not Found'
            ], 404);
        /* VALIDATION AGENCY ONLY EMM */

        $topupHistoryIds = TopupAgency::where('company_id','=',$company_id)
                                    //   ->when(!empty($date) && trim($date) !== '', function ($query) use ($date) {
                                    //         return $query->whereRaw("DATE_FORMAT(created_at, '%Y-%m') = ?", [$date]);
                                    //   })
                                      ->pluck('id')
                                      ->toArray();

        $transactionHistory = LeadspeekInvoice::select(
                                                'leadspeek_invoices.id','leadspeek_invoices.invoice_type','leadspeek_invoices.leadspeek_api_id','leadspeek_invoices.platform_total_amount',
                                                'leadspeek_invoices.payment_term','leadspeek_invoices.payment_type','leadspeek_invoices.created_at','topup_agencies.expired_at',
                                                'leadspeek_users.campaign_name','leadspeek_users.leadspeek_type',
                                                'clean_id_file.id as upload_id','clean_id_file.file_name as file_name'
                                              )
                                              ->leftJoin('topup_agencies','topup_agencies.id','=','leadspeek_invoices.topup_agencies_id')
                                              ->leftJoin('leadspeek_users','leadspeek_users.leadspeek_api_id','=','leadspeek_invoices.leadspeek_api_id')
                                              ->leftJoin('clean_id_file','clean_id_file.id','=','leadspeek_invoices.clean_file_id')
                                              ->where(function ($query) use ($topupHistoryIds, $company_id) {
                                                    $query->where(function ($query) use ($topupHistoryIds) {
                                                        $query->where('leadspeek_invoices.invoice_type','=','campaign')
                                                              ->whereIn('leadspeek_invoices.topup_agencies_id',$topupHistoryIds);
                                                    })
                                                    ->orWhere(function ($query) use ($topupHistoryIds) {
                                                        $query->where('leadspeek_invoices.invoice_type','=','agency')
                                                              ->whereIn('leadspeek_invoices.topup_agencies_id',$topupHistoryIds);
                                                    })
                                                    ->orWhere(function ($query) use ($topupHistoryIds) { // ini terjadi jika charge clean id dengan harga berapapun, dan balance wallet nya ada
                                                        $query->where('leadspeek_invoices.invoice_type','=','clean_id')
                                                              ->whereIn('leadspeek_invoices.topup_agencies_id',$topupHistoryIds);
                                                    })
                                                    ->orWhere(function ($query) use ($company_id) { // ini terjadi jika charge clean id dengan harga 0, dan balance wallet nya 0
                                                        $query->where('leadspeek_invoices.invoice_type','=','clean_id')
                                                              ->whereNull('leadspeek_invoices.topup_agencies_id')
                                                              ->where('leadspeek_invoices.company_id',$company_id);
                                                    });
                                              })
                                              ->where('leadspeek_invoices.active','=','T')
                                              ->when(!empty($date) && trim($date) !== '', function ($query) use ($date) {
                                                    return $query->whereRaw("DATE_FORMAT(leadspeek_invoices.created_at, '%Y-%m') = ?", [$date]);
                                              })
                                              ->orderBy('id','desc')
                                              ->get()
                                              ->map(function ($item, $index) {
                                                $payment_term = $item->payment_term;
                                                $transaction_type = ($item->invoice_type == 'campaign' || $item->invoice_type == 'clean_id') ? 'charge' : 'topup';

                                                $payment_type = 'Credit Card';
                                                if($item->payment_type == 'bank_account') 
                                                    $payment_type = 'Bank Account';
                                                else if($item->payment_type == 'refund_campaign') 
                                                    $payment_type = "Refund Campaign #{$item->leadspeek_api_id}";
                                                else if($item->payment_type == 'minimum_spend')
                                                    $payment_type = "Minimum Spend";

                                                $title = "Charge {$payment_term} Payment For Campaign #{$item->leadspeek_api_id} With Invoice #{$item->id}";
                                                if($item->invoice_type == 'agency')
                                                {
                                                    if(empty($item->leadspeek_api_id)) // topup manual
                                                    {
                                                        $title = "Top Up Via {$payment_type}";
                                                    }
                                                    else // auto topup
                                                    {
                                                        $leadspeek_api_id_data_wallet_array = explode("|", $item->leadspeek_api_id);
                                                        $title = "Auto Top Up Via {$payment_type}";
                                                        if(!in_array($item->payment_type, ['refund_campaign','minimum_spend']))
                                                        {
                                                            if(!empty($leadspeek_api_id_data_wallet_array[0] ?? "")){ // leadspeek_api_id
                                                                // check apakah leadspeek_api_id ini punya campaign atau clean id
                                                                $isLeadspeekApiIdCampaign = LeadspeekUser::where('leadspeek_api_id',$leadspeek_api_id_data_wallet_array[0])->exists();
                                                                $isLeadspeekApiIdCleanId = CleanIDFile::where('clean_api_id',$leadspeek_api_id_data_wallet_array[0])->exists();
                                                                if($isLeadspeekApiIdCampaign){
                                                                    $title .= " For Campaign #{$leadspeek_api_id_data_wallet_array[0]}";
                                                                }elseif($isLeadspeekApiIdCleanId){
                                                                    $title .= " For Clean ID #{$leadspeek_api_id_data_wallet_array[0]}";
                                                                }
                                                            }
                                                            if(!empty($leadspeek_api_id_data_wallet_array[1] ?? "")){ // leadspeek_invoice_id
                                                                $title .= " With Invoice #{$leadspeek_api_id_data_wallet_array[1]}";
                                                            }
                                                        }
                                                    }
                                                }
                                                elseif($item->invoice_type == 'clean_id')
                                                {
                                                    $title = "Charge For Clean ID #{$item->leadspeek_api_id} With Invoice #{$item->id}";
                                                }

                                                $sub_title = '';
                                                $campaign_name = Encrypter::decrypt($item->campaign_name ?? '');
                                                $leadspeek_type = $item->leadspeek_type ?? '';
                                                $leadspeek_type = ($leadspeek_type == 'b2b') ? 'B2B' : ucfirst($leadspeek_type);
                                                if($item->invoice_type == 'campaign')
                                                {
                                                    $sub_title = "Campaign {$campaign_name} ({$leadspeek_type})";
                                                }
                                                
                                                $created_at = Carbon::parse($item->created_at)->format('Y F d H:i:s');
                                                $expired_at = !empty($item->expired_at) ? Carbon::parse($item->expired_at)->format('Y F d H:i:s') : null;

                                                return [
                                                    'id' => $item->id,
                                                    'transaction_type' => $transaction_type,
                                                    'title' => $title,
                                                    'sub_title' => $sub_title,
                                                    'amount' => number_format($item->platform_total_amount ?? 0,2,'.',''),
                                                    'created_at' => $created_at,
                                                    'expired_at' => $expired_at, 
                                                ];
                                          });

        // info(['topup_history_ids' => $topupHistoryIds,'transaction_history' => $transactionHistory]);
        return response()->json([
            'transaction_history' => $transactionHistory,
        ]);
    }

    public function stopContinualTopupDirectPayment(Request $request)
    {
        $company_id = (isset($request->company_id)) ? $request->company_id : '';
        $stop_continual = (isset($request->stop_continual)) ? $request->stop_continual : false;
        $toggle_stop_continual = ($stop_continual == true) ? 'F' : 'T';

        /* STOP CONTINUAL IN USERDOWNLINE LEVEL */
        $systemid = config('services.application.systemid');
        $user = User::where('company_id','=',$company_id)
                    ->where('company_parent','=',$systemid)
                    ->where('active','=','T')
                    ->where('user_type','=','userdownline')
                    ->first();
        if(empty($user))
            return response()->json([
                'result' => 'failed',
                'message' => 'Agency Not Found'
            ], 404);
        $user->stopcontinual = $toggle_stop_continual;
        $user->save();
        /* STOP CONTINUAL IN USERDOWNLINE LEVEL */

        /* STOP CONTINUAL IN TOPUP AGENCY LEVEL */
        $topup = TopupAgency::where('company_id','=',$company_id)
                            ->where('topup_status','<>','done')
                            ->update([
                                'stop_continue' => $toggle_stop_continual
                            ]);
        /* STOP CONTINUAL IN TOPUP AGENCY LEVEL */

        /* CUSTOM MESSAGE */
        $message = ($toggle_stop_continual == 'T') ? "Stop Continual Topup Success" : "Continual Topup Success";
        /* CUSTOM MESSAGE */

        return response()->json([
            'stop_continual' => $toggle_stop_continual,
            'message' => $message
        ]);
    }

    public function savePrepaidDirectPayment(Request $request)
    {
        $company_id = (isset($request->company_id)) ? $request->company_id : '';
        $amount = (isset($request->amount)) ? $request->amount : '';
        $custom_amount = (isset($request->custom_amount)) ? ($request->custom_amount == true ? 'T' : 'F') : 'F';
        $stop_continual = (isset($request->stop_continual) && !empty($request->stop_continual)) ? ($request->stop_continual == true ? 'T' : 'F') : 'F';
        
        /* UPDATE TO USERDOWNLINE LEVEL */
        $systemid = config('services.application.systemid');
        $user = User::where('company_id','=',$company_id)
                    ->where('company_parent','=',$systemid)
                    ->where('active','=','T')
                    ->where('user_type','=','userdownline')
                    ->first();
        if(empty($user))
            return response()->json([
                'result' => 'failed',
                'message' => 'Agency Not Found'
            ], 404);
        $user->amount = $amount;
        $user->custom_amount = $custom_amount;
        $user->stopcontinual = $stop_continual;
        $user->save();
        /* UPDATE TO USERDOWNLINE LEVEL */

        return response()->json([
            'message' => 'save direct payment successfully'
        ]);
    }

    public function chargePrepaidDirectPayment(Request $request)
    {
        date_default_timezone_set('America/Chicago');

        $company_id = (isset($request->company_id)) ? $request->company_id : '';
        $amount = (isset($request->amount)) ? $request->amount : 0;
        $stop_continual = (isset($request->stop_continual) && !empty($request->stop_continual)) ? ($request->stop_continual == true ? 'T' : 'F') : 'F';
        $custom_amount = (isset($request->custom_amount) && !empty($request->custom_amount)) ? ($request->custom_amount == true ? 'T' : 'F') : 'F';
        $ip_user =  (isset($request->ip_user)) ? $request->ip_user : ''; 
        $timezone = (isset($request->timezone)) ? $request->timezone : ''; 
        $payment_type = (isset($request->payment_type)) ? $request->payment_type : 'credit_card'; 
        $leadspeek_api_id_data_wallet = (isset($request->leadspeek_api_id_data_wallet)) ? $request->leadspeek_api_id_data_wallet : '';
        $is_from_auto_topup = (isset($request->is_from_auto_topup)) ? $request->is_from_auto_topup : false;

        // info(__FUNCTION__, [ 'company_id' => $company_id, 'amount' => $amount, 'stop_continual' => $stop_continual, 'ip_user' => $ip_user, 'timezone' => $timezone, 'payment_type' => $payment_type]);

        $systemid = config('services.application.systemid');
        $user = User::select(
                        'users.id','users.last_balance_amount','users.email','users.company_root_id','users.customer_payment_id','users.customer_card_id','users.payment_status','users.amount','users.is_marketing_services_agreement_developer',
                        'companies.company_name','companies.manual_bill')
                    ->join('companies','companies.id','=','users.company_id')
                    ->where('users.company_id','=',$company_id)
                    ->where('users.company_parent','=',$systemid)
                    ->where('users.active','=','T')
                    ->where('users.user_type','=','userdownline')
                    ->first();
        if(empty($user))
            return response()->json([
                'result' => 'failed',
                'message' => 'Agency Not Found'
            ], 404);
        $user_id = (isset($user->id)) ? trim($user->id) : '';
        $email = (isset($user->email)) ? trim($user->email) : '';
        $company_root_id = (isset($user->company_root_id)) ? trim($user->company_root_id) : '';
        $company_name = (isset($user->company_name)) ? trim($user->company_name) : '';
        $customer_payment_id = (isset($user->customer_payment_id)) ? trim($user->customer_payment_id) : '';
        $customer_card_id = (isset($user->customer_card_id)) ? trim($user->customer_card_id) : '';

        /* VALIDATE */
        if(empty($company_id) || trim($company_id) == '')
            return response()->json([
                'result' => 'failed',
                'message' => 'The company ID is empty.',
            ]); 

        if(($amount == '' || $amount < 1)) 
            return response()->json([
                'result' => 'failed',
                'message' => 'The minimum amount is $1',
            ], 400);
        
        if(empty($customer_payment_id) || trim($customer_payment_id) == '' || empty($customer_card_id) || trim($customer_card_id) == '')
            return response()->json([
                'result' => 'failed',
                'message' => 'Your credit card has never been set up.'
            ], 400);

        $agencyManualBill = isset($user->manual_bill) ? $user->manual_bill : '';
        $agencyAgreeDeveloper = isset($user->is_marketing_services_agreement_developer) ? $user->is_marketing_services_agreement_developer : '';
        $isEnableTopupPrepaidDirectPayment = (($agencyManualBill == 'T') || ($agencyAgreeDeveloper == 'T')); 
        if(!$isEnableTopupPrepaidDirectPayment)
            return response()->json([
                'result' => 'failed',
                'message' => 'Feature to charge data wallet is not available.'
            ], 400);
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
            // info(__FUNCTION__, ['error1' => $e->getMessage()]);
            // Too many requests made to the API too quickly
            $statusPayment = 'failed';
            $errorstripe = $e->getHttpStatus() . '|' . $e->getError()->type . '|' . $e->getError()->code . '|' . $e->getError()->param . '|' . $e->getError()->message;
        } 
        catch (InvalidRequestException $e) 
        {
            // info(__FUNCTION__, ['error2' => $e->getMessage()]);
            // Invalid parameters were supplied to Stripe's API
            $statusPayment = 'failed';
            $errorstripe = $e->getHttpStatus() . '|' . $e->getError()->type . '|' . $e->getError()->code . '|' . $e->getError()->param . '|' . $e->getError()->message;
        } 
        catch (ExceptionAuthenticationException $e) 
        {
            // info(__FUNCTION__, ['error3' => $e->getMessage()]);
            // Authentication with Stripe's API failed, (maybe you changed API keys recently)
            $statusPayment = 'failed';
            $errorstripe = $e->getHttpStatus() . '|' . $e->getError()->type . '|' . $e->getError()->code . '|' . $e->getError()->param . '|' . $e->getError()->message;
        } 
        catch (ApiConnectionException $e) 
        {
            // info(__FUNCTION__, ['error4' => $e->getMessage()]);
            // Network communication with Stripe failed
            $statusPayment = 'failed';
            $errorstripe = $e->getHttpStatus() . '|' . $e->getError()->type . '|' . $e->getError()->code . '|' . $e->getError()->param . '|' . $e->getError()->message;
        } 
        catch (ApiErrorException $e) 
        {
            // info(__FUNCTION__, ['error5' => $e->getMessage()]);
            // Display a very generic error to the user, and maybe send, yourself an email
            $statusPayment = 'failed';
            $errorstripe = $e->getHttpStatus() . '|' . $e->getError()->type . '|' . $e->getError()->code . '|' . $e->getError()->param . '|' . $e->getError()->message;
        } 
        catch (Exception $e) 
        {
            // info(__FUNCTION__, ['error6' => $e->getMessage()]);
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
            // info(__FUNCTION__, ['error7' => $e->getMessage()]);
            $cardlast = "";
        }
        /* PROCESS CHARGE AGENCIES */

        /* USER LOGS */
        $userIDLogin = optional(auth()->user())->id ?? $user_id;
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

            return response()->json([
                'result' => 'failed',
                'message' => 'Sorry, this prepaid direct amount cannot be started because your credit card charge failed. Please check your payment details and try again.'
            ], 400);
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
        $user->stopcontinual = $stop_continual;
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

        $tmp = $this->send_email([$email],$from,$title,$details,$attachement,'emails.agencyprepaiddirectpayment',$company_root_id,true);
        $tmp = $this->send_email(['serverlogs@sitesettingsapi.com'],$from,$title,$details,$attachement,'emails.agencyprepaiddirectpayment','',true);
        /* SEND EMAIL */

        /* USER LOGS */
        $descriptionData['balance amount'] = $total_balance;
        $descriptionData['invoice id'] = $invoiceID;
        $description = collect($descriptionData)->map(fn($v, $k) => "$k: $v")->implode(' | ');
        $this->logUserAction($userIDLogin, $action, $description, $ip_user, $user_id);
        /* USER LOGS */

        /* TRASFER COMMISSION SALES AGENCY */
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
        /* TRASFER COMMISSION SALES AGENCY */
    
        return response()->json([
            'result' => "success",
            'message' => "Topup direct payment was successful.",
            'invoiceID' => $invoiceID,
            'balance_amount' => $total_balance,
        ]);
    }

    public function downloadTransactionHistoryDirectPayment(Request $request)
    {
        date_default_timezone_set('America/Chicago');

        $company_id = (isset($request->company_id)) ? $request->company_id : '';
        $date = (isset($request->date)) ? $request->date : '';

        return (new TransactionHistoryDirectPayment)->betweenDate($company_id,$date)->download("transaction_history_direct_payment_{$company_id}.csv");
    }

    public function getListAdvanceInformation()
    {
        $campaignInformation = campaignInformation::where('status', 'active')
                                                    ->where('campaign_type','enhance')
                                                    ->get();

        $listAdvanceInformation = [];
        foreach($campaignInformation as $item)
        {
            $listAdvanceInformation[] = [
                'id' => $item->id,
                'name' => $item->name,
                'type' => $item->type,
                'description' => json_decode($item->description, true)
            ];
        }

        return response()->json(['listAdvanceInformation' => $listAdvanceInformation]);
    }

    public function getListAdvanceInformationB2b()
    {
        $campaignInformation = campaignInformation::where('status', 'active')
                                                    ->where('campaign_type','b2b')
                                                    ->orderBy('id', 'asc')
                                                    ->get();
        $listAdvanceInformation = [];
        foreach($campaignInformation as $item)
        {
            $listAdvanceInformation[] = [
                'id' => $item->id,
                'name' => $item->name,
                'type' => $item->type,
                'description' => json_decode($item->description, true)
            ];
        }

        return response()->json(['listAdvanceInformation' => $listAdvanceInformation]);
    }

    public function getListAdvanceInformationByType(Request $request)
    {
        $type = isset($request->type) ? $request->type : '';
        $campaignInformation = campaignInformation::where('status', 'active')
                                                    ->where('campaign_type',$type)
                                                    ->orderBy('id', 'asc')
                                                    ->get();
        $listAdvanceInformation = [];
        foreach($campaignInformation as $item)
        {
            $listAdvanceInformation[] = [
                'id' => $item->id,
                'name' => $item->name,
                'type' => $item->type,
                'description' => json_decode($item->description, true)
            ];
        }

        return response()->json(['listAdvanceInformation' => $listAdvanceInformation]);
    }

    public function getminleaddayenhance(Request $request)
    {
        $idSys = (isset($request->idSys))?$request->idSys:"";

        /* GET CLIENT MIN LEAD DAYS */
        $rootSetting = $this->getcompanysetting($idSys, 'rootsetting');
        $clientMinLeadDayEnhance = (isset($rootSetting->clientminleadday))?$rootSetting->clientminleadday:"";
        /* GET CLIENT MIN LEAD DAYS */
    
        return response()->json(['clientMinLeadDayEnhance'=>$clientMinLeadDayEnhance]);
    }

    public function getminvaluesimplifi(Request $request) 
    {
        $idsys = $request->idsys ?? "";

        /* GET MINIMUM VALUE SIMPLIFI */
        $rootSetting = $this->getcompanysetting($idsys, 'rootsetting');
        $data = [
            'maxBid' => [
                'minimum' => (isset($rootSetting->maxBid->minimum))?$rootSetting->maxBid->minimum:"0"
            ],
            'dailyBudget' => [
                'minimum' => (isset($rootSetting->dailyBudget->minimum))?$rootSetting->dailyBudget->minimum:"0"
            ]
        ];
        /* GET MINIMUM VALUE SIMPLIFI */
        
        return response()->json(['data' => $data]);
    }

    public function manual_pagination($downline,$Page=0,$PerPage=0) {
        $total = $downline->count(); // Total number of items
        $offset = ($Page - 1) * $PerPage; // Calculate the offset
            
        // Get a subset of items for the current page
        $currentPageItems = $downline->slice($offset, $PerPage);
        
        // Create a paginator instance
        $paginator = new LengthAwarePaginator(
            $currentPageItems, // Items for the current page
            $total, // Total number of items
            $PerPage, // Items per page
            $Page, // Current page
            ['path' => LengthAwarePaginator::resolveCurrentPath()] // Path for generating URLs
        );
        
        // Convert the paginator to an array with pagination data
        return $paginator->toArray();
    }

    public function usermodule_show(Request $request) {
        
    }

    public function getDomainDNSRecord($domainName)
    {
        $http = new \GuzzleHttp\Client;
        //$apiURL = "https://networkcalc.com/api/dns/lookup/" . $domainName;
        $apiURL = "https://dns.google/resolve?name=" . $domainName . "&type=A";
        try {
            $options = [
                
            ]; 
           
            $response = $http->get($apiURL,$options);
            $result =  json_decode($response->getBody()->getContents(),true);

            return $result;
            
        }catch (\GuzzleHttp\Exception\BadResponseException $e) {
            Log::info("Got Error :" . $e->getMessage());
            return "";
        }
    }
    public function retrydomainssl(Request $request) {
        $companyID = (isset($request->companyID))?$request->companyID:'';
        $domain = (isset($request->domain))?$request->domain:'';

        $sslnblbipd = '157.230.213.72';
        $chkdomain = escapeshellarg($domain);
        
        $dns_a = dns_get_record($chkdomain,DNS_A);
        
        /** CHECK IF DNS HAVE VALUE IF NOT USING ANOTHER METHOD */
        if (is_array($dns_a) && count($dns_a) == 0) {
            $getRecord = $this->getDomainDNSRecord(trim($chkdomain));
            //Log::info($getRecord);
            if ($getRecord != "") {
                //$dns_a[0]['ip'] = (isset($getRecord["records"]["A"][0]['address']))?$getRecord["records"]["A"][0]['address']:"0.0.0.0";
                $dns_a[0]['ip'] = (isset($getRecord["Answer"][0]['data']))?$getRecord["Answer"][0]['data']:"0.0.0.0";
            }
        }
        /** CHECK IF DNS HAVE VALUE IF NOT USING ANOTHER METHOD */

        if (isset($dns_a[0]['ip']) && $dns_a[0]['ip'] == $sslnblbipd) {

            $company = Company::find($companyID);
            $statdomain = $company->status_domain;
            $currDomain = $company->domain;

            if (trim($currDomain) != trim($domain)) {
                $company->domain = $domain;
                $company->status_domain = '';
                $company->save();
                $statdomain = '';
            }else{

                if ($company && $company->status_domain != 'ssl_acquired') {
                    $company->status_domain = '';
                    $company->save();
                    $statdomain = '';
                }
            }
            
            return response()->json(array('result'=>'success','stdom'=>$statdomain,'message'=>'Domain SSL certificate reconfiguration initiated. This may take a few minutes. Please check your domain periodically to confirm the update has completed.'));
        }else{
            return response()->json(array('result'=>'failed','message'=>'We couldn\'t verify that your domain\'s A record is pointing to 157.230.213.72. Please make sure your DNS settings include this IP and that there are no multiple A records configured for your domain.'));
        }
    }

    public function updatesubdomain(Request $request) {
        $companyID = (isset($request->companyID))?$request->companyID:'';
        $subdomain = (isset($request->subdomain))?$request->subdomain:'';
        $idsys = (isset($request->idsys))?$request->idsys:'';

        /** GET ROOT SYS CONF */
        $confAppDomain =  config('services.application.domain');
        if ($idsys != "") {
            $conf = $this->getCompanyRootInfo($idsys);
            $confAppDomain = $conf['domain'];
        }
        /** GET ROOT SYS CONF */

        if ($companyID != '' && $subdomain != '') {
            $subdomain = str_replace('http://','',$subdomain);
            $subdomain = trim(str_replace('https://','',$subdomain));
            $subdomain = $subdomain . '.' . $confAppDomain;
            if ($this->check_subordomain_exist($subdomain)) {
                return response()->json(array('result'=>'failed','message'=>'This subdomain already exists'));
            }else{
                $subdomainupdate = Company::find($companyID);
                $subdomainupdate->subdomain = $subdomain;
                $subdomainupdate->save();

                return response()->json(array('result'=>'success','message'=>'This subdomain has been update','domain'=>$subdomainupdate->domain,'subdomain'=>$subdomainupdate->subdomain));
            }


        }
    }

    public function costmodule(Request $request) {
        date_default_timezone_set('America/Chicago');

        //$user = User::find($request->ClientID);
        $user = LeadspeekUser::find($request->ClientID);

        /* VALIDATION IS CAMPAIGN HAS ALREADY ACTIVE OR PAUSED */
        if(
            ($user->active == 'T' && $user->disabled == 'F' && $user->active_user == 'T') || 
            ($user->active == 'F' && $user->disabled == 'F' && $user->active_user == 'T') || 
            ($user->active == 'F' && $user->disabled == 'T' && $user->active_user == 'T')
        ) {
            $campaignStatus = "";
            if(($user->active == 'T' && $user->disabled == 'F' && $user->active_user == 'T') || ($user->active == 'F' && $user->disabled == 'F' && $user->active_user == 'T')) {
                $campaignStatus = "active";
            } else if ($user->active == 'F' && $user->disabled == 'T' && $user->active_user == 'T') {
                $campaignStatus = 'paused';
            }

            // info(['user->paymentterm' => $user->paymentterm,'request->paymentterm' => $request->paymentterm,'user->topupoptions' => $user->topupoptions,'request->topupoptions' => $request->topupoptions,'user->lp_limit_freq' => $user->lp_limit_freq,'request->LimitLeadFreq' => $request->LimitLeadFreq]);
            if($user->paymentterm == 'Prepaid' && $user->paymentterm == $request->PaymentTerm && ($user->topupoptions != $request->topupoptions || $user->lp_limit_freq != $request->LimitLeadFreq)) {
                return response()->json(['result'=>"failed_campaign_already_{$campaignStatus}",'message'=>"Sorry, you cannot change the prepaid type because the campaign is already {$campaignStatus}."]);
            } else if($user->paymentterm != 'Prepaid' && $user->paymentterm == $request->PaymentTerm && ($user->platformfee != $request->PlatformFee || $user->lp_min_cost_month != $request->CostMonth || $user->cost_perlead != $request->CostSet)) {
                return response()->json(['result'=>"failed_campaign_already_{$campaignStatus}",'message'=>"Sorry, you cannot change the campaign price because the campaign is already {$campaignStatus}."]); 
            } else if ($user->paymentterm != $request->PaymentTerm) {
                return response()->json(['result'=>"failed_campaign_already_{$campaignStatus}",'message'=>"Sorry, you cannot change the payment term because the campaign is already {$campaignStatus}."]); 
            }
        }
        /* VALIDATION IS CAMPAIGN HAS ALREADY ACTIVE OR PAUSED */

        if ($request->ModuleName == 'LeadsPeek') {
            $user->cost_perlead = $request->CostSet;
            $user->lp_max_lead_month = $request->CostMaxLead;
            $user->lp_min_cost_month = $request->CostMonth;
            $user->lp_limit_leads = $request->LimitLead;
            $user->enable_minimum_limit_leads = (isset($request->enableMinimumLimitLeads) && $request->enableMinimumLimitLeads == true) ? "T" : "F";
            $user->minimum_limit_leads = (isset($request->minimumLimitLeads) && $request->minimumLimitLeads >= 1) ? $request->minimumLimitLeads : 1;
            $user->lp_limit_freq = $request->LimitLeadFreq;
            $user->paymentterm = $request->PaymentTerm;
            $user->continual_buy_options = $request->contiDurationSelection ? 'Monthly' : 'Weekly';
            $user->topupoptions = $request->topupoptions;
            $user->leadsbuy = $request->leadsbuy;
            $user->platformfee = $request->PlatformFee;
            if (isset($request->LimitLeadStart) || $request->LimitLeadStart != '') {
                if ($request->PaymentTerm == 'One Time') {
                    $user->lp_limit_startdate = date('Y-m-d');
                }else{
                    $user->lp_limit_startdate = $request->LimitLeadStart;
                }
            }
            if (isset($request->LimitLeadEnd) && $request->LimitLeadEnd != '' && $request->LimitLeadEnd != 'Invalid date') {
                $user->lp_enddate = $request->LimitLeadEnd;
            }else{
                $user->lp_enddate = null;
            }
            if($request->PaymentTerm === 'Prepaid') {
                $user->is_send_email_prepaid = 'F';
            }
        }
        $user->save();

        /** CHECK IF PREPAID ALREADY ON COSTAGENCY */
        $getcompanysetting = CompanySetting::where('company_id',$user->company_id)->whereEncrypted('setting_name','costagency')->get();
        $companysetting = "";
        if (count($getcompanysetting) > 0) {
            $companysetting = json_decode($getcompanysetting[0]['setting_value']);
        }
        if ($companysetting != "") {
            $comset_val = $this->getcompanysetting($request->idSys,'rootcostagency');

            if(!isset($companysetting->enhance)) {
                $companysetting->enhance = new stdClass();
                $companysetting->enhance->Weekly = new stdClass();
                $companysetting->enhance->Monthly = new stdClass();
                $companysetting->enhance->OneTime = new stdClass();
                $companysetting->enhance->Prepaid = new stdClass();
        
                /* WEEKLY */
                $companysetting->enhance->Weekly->EnhanceCostperlead = $comset_val->enhance->Weekly->EnhanceCostperlead;
                $companysetting->enhance->Weekly->EnhanceMinCostMonth = $comset_val->enhance->Weekly->EnhanceMinCostMonth;
                $companysetting->enhance->Weekly->EnhancePlatformFee = $comset_val->enhance->Weekly->EnhancePlatformFee;
                /* WEEKLY */
                
                /* MONTHLY */
                $companysetting->enhance->Monthly->EnhanceCostperlead = $comset_val->enhance->Monthly->EnhanceCostperlead;
                $companysetting->enhance->Monthly->EnhanceMinCostMonth = $comset_val->enhance->Monthly->EnhanceMinCostMonth;
                $companysetting->enhance->Monthly->EnhancePlatformFee = $comset_val->enhance->Monthly->EnhancePlatformFee;
                /* MONTHLY */
                
                /* ONETIME */
                $companysetting->enhance->OneTime->EnhanceCostperlead = $comset_val->enhance->OneTime->EnhanceCostperlead;
                $companysetting->enhance->OneTime->EnhanceMinCostMonth = $comset_val->enhance->OneTime->EnhanceMinCostMonth;
                $companysetting->enhance->OneTime->EnhancePlatformFee = $comset_val->enhance->OneTime->EnhancePlatformFee;
                /* ONETIME */
        
                /* PREPAID */
                $companysetting->enhance->Prepaid->EnhanceCostperlead = $comset_val->enhance->Prepaid->EnhanceCostperlead;
                $companysetting->enhance->Prepaid->EnhanceMinCostMonth = $comset_val->enhance->Prepaid->EnhanceMinCostMonth;
                $companysetting->enhance->Prepaid->EnhancePlatformFee = $comset_val->enhance->Prepaid->EnhancePlatformFee;
                /* PREPAID */
            }

            if (!isset($companysetting->local->Prepaid)) {
                $newPrepaidLocal = [
                    "LeadspeekCostperlead" => $companysetting->local->Weekly->LeadspeekCostperlead,
                    "LeadspeekMinCostMonth" => $companysetting->local->Weekly->LeadspeekMinCostMonth,
                    "LeadspeekPlatformFee" => $companysetting->local->Weekly->LeadspeekPlatformFee
                ];

                $newPrepaidLocator = [
                    "LocatorCostperlead" => $companysetting->locator->Weekly->LocatorCostperlead,
                    "LocatorMinCostMonth" => $companysetting->locator->Weekly->LocatorMinCostMonth,
                    "LocatorPlatformFee" => $companysetting->locator->Weekly->LocatorPlatformFee
                ];
                
                $newPrepaidEnhance = [
                    "EnhanceCostperlead" => $comset_val->enhance->Prepaid->EnhanceCostperlead,
                    "EnhanceMinCostMonth" => $comset_val->enhance->Prepaid->EnhanceMinCostMonth,
                    "EnhancePlatformFee" => $comset_val->enhance->Prepaid->EnhancePlatformFee
                ];

                $companysetting->local->Prepaid = $newPrepaidLocal;
                $companysetting->locator->Prepaid = $newPrepaidLocator;
                $companysetting->enhance->Prepaid = $newPrepaidEnhance;
            }
            
            /** UPDATE COMPANY SETTING VALUE (COSTAGENCY) */
            $updatesetting = CompanySetting::find($getcompanysetting[0]['id']);
            $updatesetting->setting_value = json_encode($companysetting);
            $updatesetting->save();
            /** UPDATE COMPANY SETTING VALUE (COSTAGENCY) */
        }
        /** CHECK IF PREPAID ALREADY ON COSTAGENCY */
        
        if($request->PaymentTerm === 'Prepaid') {
            $topupCampaignExist = Topup::where('leadspeek_api_id', $request->LeadspeekApiId)
                                       ->where('topup_status', '<>', 'done')
                                       ->exists();
    
            if($topupCampaignExist) {
                Topup::where('leadspeek_api_id', $request->LeadspeekApiId)
                     ->where('topup_status', '<>', 'done')
                     ->update([
                        'treshold' => $request->LimitLead
                     ]);
            }
        }

        /** LOG ACTION */
        // $idUser = (isset($request->idUser))?$request->idUser:''; 
        // $ipAddress = (isset($request->ipAddress))?$request->ipAddress:'';
        // $description = "CampaignID : {$request->LeadspeekApiId} | Billing Frequency : {$request->PaymentTerm} | ";

        // jika prepaid 
        // if($request->PaymentTerm === 'Prepaid') {
        //     $description .= "Topup Options : {$request->topupoptions} | ";
        // }

        // $description .= "Setup Fee : {$request->PlatformFee} | Campaign Fee : {$request->CostMonth} | Cost per lead : {$request->CostSet} | Leads Per Day : {$request->LimitLead}";
        // $user_id = auth()->user()->id;
        // $this->logUserAction($user_id, "Setup Campaigns Financial", $description, $ipAddress);
        /** LOG ACTION */

        return response()->json(array('result'=>'success'));
    }

    public function removerolemodule(Request $request,$CompanyID,$RoleID) {
        RoleModule::where('role_id','=',$RoleID)->delete();
        $usr = User::where('role_id','=',$RoleID)->get();
        if (count($usr) > 0) {
            $usr->role_id = 0;
            $usr->save();
        }
        Role::find($RoleID)->delete();
    }

    public function rolemoduleaddupdate(Request $request) {
        $RoleID = '';
        if($request->roleID == '') {
            $Role = Role::create([
                'role_name' => $request->roleName,
                'role_icon' => $request->roleIcon,
                'company_id' => $request->companyID,
            ]);
    
            $RoleID = $Role->id;

            foreach($request->roledata as $item) {
                $rolemodule = RoleModule::create([
                    'role_id' => $RoleID,
                    'module_id' => $item['id'],
                    'create_permission' => ($item['create_permission'])?'T':'F',
                    'read_permission' => ($item['read_only'])?'T':'F',
                    'update_permission' => ($item['update_permission'])?'T':'F',
                    'delete_permission' => ($item['delete_permission'])?'T':'F',
                    'enable_permission' => ($item['enable_permission'])?'T':'F',
                ]);
            }

        }else{
            $RoleID = $request->roleID;

            $Role = Role::find($RoleID);
            $Role->role_name = $request->roleName;
            $Role->role_icon = $request->roleIcon;
            $Role->save();

            $del_rolemodule = RoleModule::where('role_id','=',$request->roleID)->delete();
            foreach($request->roledata as $item) {
                $rolemodule = RoleModule::create([
                    'role_id' => $request->roleID,
                    'module_id' => $item['id'],
                    'create_permission' => ($item['create_permission'])?'T':'F',
                    'read_permission' => ($item['read_only'])?'T':'F',
                    'update_permission' => ($item['update_permission'])?'T':'F',
                    'delete_permission' => ($item['delete_permission'])?'T':'F',
                    'enable_permission' => ($item['enable_permission'])?'T':'F',
                ]);
            }
        }
        
        return array('roleID'=>$RoleID);
        //return $request->roledata[0]['module_name'];
    }

    public function rolemodule_show(Request $request,$GetType='',$CompanyID='',$ID='') {
        if($GetType == 'getrole') {
            if ($ID != '') {
                return Role::find($ID);
            }else {
                return Role::where('company_id','=',$CompanyID)->get();
            }
        }else if ($GetType == 'getmodule') {
            if ($ID != '') {
                return Module::find($ID);
            }else{
                return Module::get();
            }
        }else if ($GetType == 'getrolemodule') {
            /** CHECK IF USER SETUP COMPLETED */
            $systemid = config('services.application.systemid');
            $usrCompleteProfileSetup = 'F';
            $paymentStatusFailed = false;
            $clientPaymentFailed = false;
            $failed_campaignid = array();
            $failed_total_amount = array();
            $apiMode = false;
            $is_marketing_services_agreement_developer = false;
            $enabledClientDeletedAccount = 'F'; // Initialize enabled_client_deleted_account

            if(isset($request->usrID) && $request->usrID != '') {
                $usrSetup = User::select('profile_setup_completed','user_type','status_acc','payment_status','failed_total_amount','failed_campaignid','company_id','api_mode','is_marketing_services_agreement_developer')
                            ->where('active','=','T')
                            ->where('id','=',$request->usrID)
                            ->get();
                if(count($usrSetup) > 0) {
                    $comp_id = $usrSetup[0]['company_id'];
                    $usrCompleteProfileSetup = $usrSetup[0]['profile_setup_completed'];
                    $paymentStatusFailed = ($usrSetup[0]['payment_status'] == 'failed')?true:false;
                    $clientPaymentFailed = ($usrSetup[0]['payment_status'] == 'failed')?true:false;
                    $failed_campaignid = explode('|',$usrSetup[0]['failed_campaignid']);
                    $failed_total_amount = explode('|',$usrSetup[0]['failed_total_amount']);
                    $apiMode = ($usrSetup[0]['api_mode'] == 'T')?true:false;
                    $is_marketing_services_agreement_developer = ($usrSetup[0]['is_marketing_services_agreement_developer'] == 'T')?true:false;

                    if($usrSetup[0]['user_type'] != 'userdownline' && $usrSetup[0]['user_type'] != 'client') {
                        $usrSetup2 = User::select('payment_status','failed_campaignid','failed_total_amount','api_mode','is_marketing_services_agreement_developer')
                                        ->where('active','=','T')
                                        ->where('company_id','=',$comp_id)
                                        ->where('user_type','=','userdownline')
                                        ->first();

                        $paymentStatusFailed = ($usrSetup2->payment_status == 'failed')?true:false;
                        $clientPaymentFailed = ($usrSetup2->payment_status == 'failed')?true:false;
                        $failed_campaignid = explode('|',$usrSetup2->failed_campaignid);
                        $failed_total_amount = explode('|',$usrSetup2->failed_total_amount);
                        $apiMode = ($usrSetup2->api_mode == 'T')?true:false;
                        $is_marketing_services_agreement_developer = ($usrSetup2->is_marketing_services_agreement_developer == 'T')?true:false;
                    }

                    // ubah format jika cleanid
                    // info('before', ['failed_campaignid' => $failed_campaignid]);
                    $clean_api_ids = CleanIdFile::whereIn('clean_api_id', array_filter($failed_campaignid, 'is_numeric'))->pluck('clean_api_id')->toArray();
                    $failed_campaignid = array_map(function ($item) use ($clean_api_ids) {
                        return is_numeric($item) && in_array($item, $clean_api_ids) ? "cleanid_$item" : $item;
                    }, $failed_campaignid);
                    // info('after', ['failed_campaignid' => $failed_campaignid]);
                    // ubah format jika cleanid
                }
            }
            /** CHECK IF USER SETUP COMPLETED */
            
            // Get enabled_client_deleted_account from agency owner (userdownline)
            $agencyOwner = User::select('enabled_client_deleted_account')
                            ->where('active','=','T')
                            ->where('company_id','=',$CompanyID)
                            ->where('user_type','=','userdownline')
                            ->first();
            if($agencyOwner && $agencyOwner->enabled_client_deleted_account) {
                $enabledClientDeletedAccount = $agencyOwner->enabled_client_deleted_account;
            }

            /** CHECK STRIPE CONNECTED ACCOUNT */
            $companyConnectStripe = CompanyStripe::select('status_acc','acc_connect_id','package_id')->where('company_id','=',$CompanyID)
                                    ->get();

            $checkPaymentGateway = Company::select('paymentgateway')
                                        ->where('id','=',$CompanyID)
                                        ->get();


            $accountConnected = '';
            $package_id = '';
            $paymentgateway = 'stripe';

            if (count($companyConnectStripe) > 0) {
                $accountConnected = $companyConnectStripe[0]['status_acc'];
                $package_id = ($companyConnectStripe[0]['package_id'] != '')?$companyConnectStripe[0]['package_id']:"";
                if ($accountConnected == "" && count($checkPaymentGateway) > 0) {
                    $paymentgateway = $checkPaymentGateway[0]['paymentgateway'];
                }
            }
            
            if (isset($usrSetup[0]['user_type']) && $usrSetup[0]['user_type'] == "sales") {
                $accountConnected = $usrSetup[0]['status_acc'];
                $package_id = "";
            }
            /** CHECK STRIPE CONNECTED ACCOUNT */

            // Check is_whitelabeling exists
            $companyStripe = CompanyStripe::where('company_id','=',$CompanyID)
                        ->get();
            $getCurrentCompany = Company::where('id', '=', $CompanyID)->get();
            $getUserCurrentCompany = User::where('company_id', '=', $CompanyID)->get();
            $getColorsParentCompany = Company::select('sidebar_bgcolor', 'text_color')->where('id', '=', $getUserCurrentCompany[0]['company_root_id'])->first();

            $whitelabellingpackage = 'F';

            if(count($companyStripe) > 0){
                if(trim($companyStripe[0]->package_id) != ''){
                    $chkPackage = PackagePlan::select('whitelabelling')
                                        ->where('package_id','=',trim($companyStripe[0]->package_id))
                                        ->get();
                    foreach($chkPackage as $chkpak) {
                        $whitelabellingpackage = $chkpak['whitelabelling'];
                    }
                }
            }

            $is_whitelabeling = $getCurrentCompany[0]['is_whitelabeling'] ? $getCurrentCompany[0]['is_whitelabeling'] : $whitelabellingpackage;
            // Check is_whitelabeling exists

            // agency payment term setting 
            // $getUserCurrentCompany = User::where('company_id', '=', $CompanyID)->get();
            try {
                $paymenttermcontrol = CompanySetting::where('company_id', $CompanyID)
                    ->whereEncrypted('setting_name', 'agencypaymentterm')
                    ->get();
        
                } catch (\Throwable $th) {
                        return response()->json(['result' => 'failed', 'msG' => $th->getMessage(), 'ID' => $companyStripe->acc_connect_id ]);
                }
                if (count($paymenttermcontrol) > 0) {
                    /** GET PAYMENT TERM ROOT FILTERED BY PAYMENTTERMCONTROL */
                    try {
                        $root_paymenttermlist = "";
                        $paymenttermlist = CompanySetting::where('company_id', $getUserCurrentCompany[0]['company_root_id'])
                            ->whereEncrypted('setting_name', 'rootpaymentterm')
                            ->get();
                
                        if (count($paymenttermlist) > 0) {
                            $root_paymenttermlist = json_decode($paymenttermlist[0]['setting_value']);
                        }
        
                        $_paymenttermcontrol = "";
                        if (count($paymenttermcontrol) > 0) {
                            $_paymenttermcontrol = json_decode($paymenttermcontrol[0]['setting_value']);
                        }
                
                
                        // Filter rootpaymentterm based on paymenttermcontrol
                        $filteredPaymentTerms = [];
                        if ($root_paymenttermlist && $_paymenttermcontrol) {
                            // Create a map of terms and their statuses
                            $termStatus = [];
                            foreach ($_paymenttermcontrol->SelectedPaymentTerm as $control) {
                                $termStatus[$control->term] = $control->status;
                            }
                
                            // Filter rootpaymentterm
                            foreach ($root_paymenttermlist->PaymentTerm as $term) {
                                if (isset($termStatus[$term->value]) && $termStatus[$term->value]) {
                                    $filteredPaymentTerms[] = $term;
                                }
                            }
                        }
                    } catch (\Throwable $th) {
                        // return response()->json(['filteredPaymentTerms'=> $filteredPaymentTerms, '_paymenttermcontrol' => $_paymenttermcontrol, 'errmsg' => $th->getMessage()]);
                    }
                    /** GET PAYMENT TERM ROOT FILTERED BY PAYMENTTERMCONTROL */
                        $paymentTerms = $filteredPaymentTerms;
                    }else {
        
                                // /** GET PAYMENT TERM ROOT */
                            $_paymenttermlist = "";
                            $paymenttermlist = CompanySetting::where('company_id',$getUserCurrentCompany[0]['company_root_id'])->whereEncrypted('setting_name','rootpaymentterm')->get();
                            if (count($paymenttermlist) > 0) {
                                $_paymenttermlist = json_decode($paymenttermlist[0]['setting_value']);
                            }
                            /** GET PAYMENT TERM ROOT */
        
                        $paymentTerms = $_paymenttermlist->PaymentTerm ?? [];
                }
            // agency payment term setting 

            // root default payment term for new agencies
            $settingPaymentTermsNewAgencies = $this->getcompanysetting($CompanyID,'rootPaymentTermsNewAgencies');
            $rootPaymentTermsNewAgencies = $settingPaymentTermsNewAgencies ?? [];
            // root default payment term for new agencies

            //ROOT OR AGENCY DEFAULT MODULES VALUE
            $setting_name = 'agencydefaultmodules';
            if ($CompanyID == $getUserCurrentCompany[0]['company_root_id']) {
                $setting_name = 'rootdefaultmodules';
            }
            $agencyDefaultModules = [];
            $agencyDefaultModules_setting = $this->getcompanysetting($CompanyID,$setting_name);
            if (!empty($agencyDefaultModules_setting) && isset($agencyDefaultModules_setting->DefaultModules)) {
                    $agencyDefaultModules = $agencyDefaultModules_setting->DefaultModules;

                    $rootcustomsidebarleadmenu = CompanySetting::where('company_id',trim($getUserCurrentCompany[0]['company_root_id']))->whereEncrypted('setting_name','rootcustomsidebarleadmenu')->get();
                    if (count($rootcustomsidebarleadmenu) > 0) {
                        $rootcustomsidebarleadmenu = json_decode($rootcustomsidebarleadmenu[0]['setting_value']);
                    }

                    // check jika agency default modules belum punya b2b namun root sudah siap -jidan-
                    $agency_default_modules_b2b_exists = !empty(array_filter($agencyDefaultModules, function ($item) {
                        return $item->type === 'b2b';
                    }));

                    // info('ROOT OR AGENCY DEFAULT MODULES VALUE 1', [
                    //     'agency_default_modules_b2b_exists' => $agency_default_modules_b2b_exists,
                    //     'agencyDefaultModules' => $agencyDefaultModules,
                    //     'rootcustomsidebarleadmenu' => $rootcustomsidebarleadmenu
                    // ]);

                    if (isset($rootcustomsidebarleadmenu->b2b) && !$agency_default_modules_b2b_exists) {
                        $agencyDefaultModules[] = (object) [
                            'type' => 'b2b',
                            'status' => true
                        ];
                    }

                    // info('ROOT OR AGENCY DEFAULT MODULES VALUE 2', [
                    //     'agencyDefaultModules' => $agencyDefaultModules
                    // ]);
                    // check jika agency default modules belum punya b2b namun root sudah siap -jidan-
                    
                    // check jika agency default modules belum punya simplifi namun root sudah siap -jidan-
                    $agency_default_modules_simplifi_exists = !empty(array_filter($agencyDefaultModules, function ($item) {
                        return $item->type === 'simplifi';
                    }));

                    // info('ROOT OR AGENCY DEFAULT MODULES VALUE 3', [
                    //     'agency_default_modules_simplifi_exists' => $agency_default_modules_simplifi_exists,
                    //     'agencyDefaultModules' => $agencyDefaultModules,
                    //     'rootcustomsidebarleadmenu' => $rootcustomsidebarleadmenu
                    // ]);

                    if (isset($rootcustomsidebarleadmenu->simplifi) && !$agency_default_modules_simplifi_exists) {
                        $agencyDefaultModules[] = (object) [
                            'type' => 'simplifi',
                            'status' => true
                        ];
                    }

                    // info('ROOT OR AGENCY DEFAULT MODULES VALUE 4', [
                    //     'agencyDefaultModules' => $agencyDefaultModules
                    // ]);
                    // check jika agency default modules belum punya simplifi namun root sudah siap -jidan-
            }else {
                $root_clientsidebar = $this->getcompanysetting($getUserCurrentCompany[0]['company_root_id'], 'rootcustomsidebarleadmenu');
                if (!empty($root_clientsidebar)) {
                    foreach($root_clientsidebar as $key => $value){
                            $agencyDefaultModules[] = [
                                'type' => $key,
                                'status' => true
                            ];
                    }
                }
            }
            //ROOT OR AGENCY DEFAULT MODULES VALUE

            //Modules for general setting, downlinelist and client management
            $agencyFilteredModules = "";
            $root_modules = CompanySetting::where('company_id',trim($getUserCurrentCompany[0]['company_root_id']))->whereEncrypted('setting_name','rootcustomsidebarleadmenu')->get();
            if (count($root_modules) > 0) {
                $root_modules = json_decode($root_modules[0]['setting_value']);

                if ($getUserCurrentCompany[0]['company_id'] != $getUserCurrentCompany[0]['company_root_id']) { // jika selain root
                    $agencysidebar = $this->getcompanysetting($getUserCurrentCompany[0]['company_id'], 'agencysidebar');
                    $exist_setting = $this->getcompanysetting($getUserCurrentCompany[0]['company_root_id'], 'rootexistagencymoduleselect');
                    if (!empty($agencysidebar) && isset($agencysidebar->SelectedModules)) { // filter acuannya dari agencysidebar, jika true dibiarkan, jika false maka dihapus key root_modules tersebut
                        foreach ($agencysidebar->SelectedModules as $key => $value) {
                            foreach ($root_modules as $key1 => $value1) {
                                if ($key1 == $value->type && $value->status == false) {
                                    unset($root_modules->$key1);
                                }
                            }
                        }
                    } elseif (!empty($exist_setting) && isset($exist_setting->SelectedModules)) {
                        foreach ($exist_setting->SelectedModules as $key => $value) {
                            foreach ($root_modules as $key2 => $value2) {
                                if ($key2 == $value->type && $value->status == false) {
                                    unset($root_modules->$key2);
                                }
                            }
                        }
                    }
                }
                $agencyFilteredModules = $root_modules;
            }
            //Modules for general setting, downlinelist and client management

            /* SIDEBARMENU */
            $rootsidebarleadmenu = "";
            $customsidebarleadmenu = "";
            $rootcompanysetting = CompanySetting::where('company_id',trim($getUserCurrentCompany[0]['company_root_id']))->whereEncrypted('setting_name','rootcustomsidebarleadmenu')->get();
            if (count($rootcompanysetting) > 0) {
                $rootsidebarleadmenu = json_decode($rootcompanysetting[0]['setting_value']);
            }
            
            $companysetting = CompanySetting::where('company_id',trim($CompanyID))->whereEncrypted('setting_name','customsidebarleadmenu')->get();
            if (count($companysetting) > 0) {
                $customsidebarleadmenu = json_decode($companysetting[0]['setting_value']);
            }
            // Log::info('rootsidebarleadmenu1', ['rootsidebarleadmenu' => $rootsidebarleadmenu]);
            /* SIDEBARMENU */

            // SELECTED MODULES FOR AGENCIES
            $agencysidebar = $this->getcompanysetting($getUserCurrentCompany[0]['company_id'], 'agencysidebar');

            $agency_side_menu = [];
            if (!empty($agencysidebar) && isset($agencysidebar->SelectedModules)) {
                $agency_side_menu = $agencysidebar->SelectedModules;

                /* CHECK AGENCYSIDEMENU ENHANCE EXISTS -jidan- */
                $agency_side_menu_enhance_exists = !empty(array_filter($agency_side_menu, function ($item) {
                    return $item->type === 'enhance';
                }));

                // Log::info('SELECTED MODULES FOR AGENCIES 1', [
                //     'agency_side_menu' => $agency_side_menu,
                //     'agency_side_menu_enhance_exists' => $agency_side_menu_enhance_exists
                // ]);

                // jika root sudah siap dengan enhance, namun agencysidebar belum ada enhance 
                if (isset($rootsidebarleadmenu->enhance) && !$agency_side_menu_enhance_exists) {
                    // Log::info('rootsidebarleadmenu enhance block1');
                    $agency_side_menu[] = (object) [
                        'type' => 'enhance',
                        'status' => true
                    ];
                }
                // jika root belum siap dengan enhance, namun agencysidebar sudah ada enhance
                else if(!isset($rootsidebarleadmenu->enhance) && $agency_side_menu_enhance_exists) {
                    // Log::info('rootsidebarleadmenu enhance block2');
                    // Log::info('agency_side_menu', ['agency_side_menu' => $agency_side_menu]);
                    foreach($agency_side_menu as $index => $item) {
                        if($item->type == 'enhance') {
                            $item->status = false;
                        }
                    }
                    // Log::info('rootsidebarleadmenu enhance agency_side_menu', ['agency_side_menu' => $agency_side_menu]);
                }

                // Log::info('SELECTED MODULES FOR AGENCIES 2', [
                //     'agency_side_menu' => $agency_side_menu
                // ]);
                /* CHECK AGENCYSIDEMENU ENHANCE EXISTS -jidan- */

                /* CHECK AGENCYSIDEMENU B2B EXISTS -jidan- */
                $agency_side_menu_b2b_exists = !empty(array_filter($agency_side_menu, function ($item) {
                    return $item->type === 'b2b';
                }));

                // Log::info('SELECTED MODULES FOR AGENCIES 1', [
                //     'agency_side_menu' => $agency_side_menu,
                //     'agency_side_menu_b2b_exists' => $agency_side_menu_b2b_exists
                // ]);

                // jika root sudah siap dengan b2b, namun agencysidebar belum ada b2b 
                if (isset($rootsidebarleadmenu->b2b) && !$agency_side_menu_b2b_exists) {
                    // Log::info('rootsidebarleadmenu b2b block1');
                    $agency_side_menu[] = (object) [
                        'type' => 'b2b',
                        'status' => true
                    ];
                }
                // jika root belum siap dengan b2b, namun agencysidebar sudah ada b2b
                else if(!isset($rootsidebarleadmenu->b2b) && $agency_side_menu_b2b_exists) {
                    // Log::info('rootsidebarleadmenu b2b block2');
                    // Log::info('agency_side_menu', ['agency_side_menu' => $agency_side_menu]);
                    foreach($agency_side_menu as $index => $item) {
                        if($item->type == 'b2b') {
                            $item->status = false;
                        }
                    }
                    // Log::info('rootsidebarleadmenu b2b agency_side_menu', ['agency_side_menu' => $agency_side_menu]);
                }

                // Log::info('SELECTED MODULES FOR AGENCIES 2', [
                //     'agency_side_menu' => $agency_side_menu
                // ]);
                /* CHECK AGENCYSIDEMENU B2B EXISTS -jidan- */

                /* CHECK AGENCYSIDEMENU SIMPLIFI EXISTS -jidan- */
                $agency_side_menu_simplifi_exists = !empty(array_filter($agency_side_menu, function ($item) {
                    return $item->type === 'simplifi';
                }));

                // Log::info('SELECTED MODULES FOR AGENCIES 1', [
                //     'agency_side_menu' => $agency_side_menu,
                //     'agency_side_menu_simplifi_exists' => $agency_side_menu_simplifi_exists
                // ]);

                // jika root sudah siap dengan simplifi, namun agencysidebar belum ada simplifi 
                if (isset($rootsidebarleadmenu->simplifi) && !$agency_side_menu_simplifi_exists) {
                    // Log::info('rootsidebarleadmenu simplifi block1');
                    $agency_side_menu[] = (object) [
                        'type' => 'simplifi',
                        'status' => true
                    ];
                }
                // jika root belum siap dengan simplifi, namun agencysidebar sudah ada simplifi
                else if(!isset($rootsidebarleadmenu->simplifi) && $agency_side_menu_simplifi_exists) {
                    // Log::info('rootsidebarleadmenu simplifi block2');
                    // Log::info('agency_side_menu', ['agency_side_menu' => $agency_side_menu]);
                    foreach($agency_side_menu as $index => $item) {
                        if($item->type == 'simplifi') {
                            $item->status = false;
                        }
                    }
                    // Log::info('rootsidebarleadmenu simplifi agency_side_menu', ['agency_side_menu' => $agency_side_menu]);
                }

                // Log::info('SELECTED MODULES FOR AGENCIES 2', [
                //     'agency_side_menu' => $agency_side_menu
                // ]);
                /* CHECK AGENCYSIDEMENU SIMPLIFI EXISTS -jidan- */

                /* CHECK AGENCYSIDEMENU PREDICT EXISTS -jidan- */
                $agency_side_menu_predict_exists = !empty(array_filter($agency_side_menu, function ($item) {
                    return $item->type === 'predict';
                }));

                // Log::info('SELECTED MODULES FOR AGENCIES 1', [
                //     'agency_side_menu' => $agency_side_menu,
                //     'agency_side_menu_predict_exists' => $agency_side_menu_predict_exists
                // ]);

                // jika root sudah siap dengan predict, namun agencysidebar belum ada predict 
                if (isset($rootsidebarleadmenu->predict) && !$agency_side_menu_predict_exists) {
                    // Log::info('rootsidebarleadmenu predict block1');
                    $agency_side_menu[] = (object) [
                        'type' => 'predict',
                        'status' => false
                    ];
                }
                // jika root belum siap dengan predict, namun agencysidebar sudah ada predict
                else if(!isset($rootsidebarleadmenu->predict) && $agency_side_menu_predict_exists) {
                    // Log::info('rootsidebarleadmenu predict block2');
                    // Log::info('agency_side_menu', ['agency_side_menu' => $agency_side_menu]);
                    foreach($agency_side_menu as $index => $item) {
                        if($item->type == 'predict') {
                            $item->status = false;
                        }
                    }
                    // Log::info('rootsidebarleadmenu predict agency_side_menu', ['agency_side_menu' => $agency_side_menu]);
                }

                // Log::info('SELECTED MODULES FOR AGENCIES 2', [
                //     'agency_side_menu' => $agency_side_menu
                // ]);
                /* CHECK AGENCYSIDEMENU PREDICT EXISTS -jidan- */
            }else {
                    $root_agencysidebar = $this->getcompanysetting($getUserCurrentCompany[0]['company_root_id'], 'rootcustomsidebarleadmenu');
                        if (!empty($root_agencysidebar)) {
                            foreach ($root_agencysidebar as $key => $value) {
                                    $agency_side_menu[] = [
                                        'type' => $key,
                                        'status' => true
                                    ];
                            }
                        }
            }
            // SELECTED MODULES FOR AGENCIES

            /** IF PAYMENT FAILED THEN DISABLED ACCES AND REDIRECT TO UPDATE CC */
            $accessmodule = "";
            if ($clientPaymentFailed && isset($failed_campaignid[0]) && $failed_campaignid[0] != '' && isset($failed_total_amount[0]) && $failed_total_amount[0] != '') {
                $accessmodule = 'paymentfailed';
                $agency_side_menu = [];

                $spendIndex = array_search("minspend", $failed_campaignid);

                if ($spendIndex !== false) {
                    // Ambil nilai amount yang sesuai dengan "spend"
                    $minspendValue = $failed_total_amount[$spendIndex];

                    // Hapus "spend" dan nilainya dari array
                    unset($failed_campaignid[$spendIndex]);
                    unset($failed_total_amount[$spendIndex]);

                    // Tambahkan "spend" dan nilainya di akhir array
                    $failed_campaignid[] = "minspend";
                    $failed_total_amount[] = $minspendValue;
                }

                // Ubah kembali ke string
                // $failed_campaignid = implode("|", $failed_campaignid);
                // $failed_total_amount = implode("|", $failed_total_amount);
                $failed_campaignid = array_values($failed_campaignid);
                $failed_total_amount = array_values($failed_total_amount);
            }

            // info(['failed_campaignid' => $failed_campaignid, 'failed_total_amount' => $failed_total_amount ]);

            /* GET AND VALIDATE FEATURE */
            $user = User::select('company_parent','user_type')
                        ->where('active','=','T')
                        ->where('id','=',$request->usrID)
                        ->first();
            $userType = isset($user->user_type) ? $user->user_type : '';
            $companyParent = isset($user->company_parent) ? $user->company_parent : '';
            $agencyCompanyID = ($userType != 'client') ? $CompanyID : $companyParent;
            $betaFeature = [
                'data_wallet' => [
                    'id' => 1,
                    'slug' => 'data_wallet',
                    'name' => 'Data Wallet',
                    'is_beta' => false,
                    'apply_to_all_agency' => false,
                ],
                'b2b_module' => [
                    'id' => 2,
                    'slug' => 'b2b_module',
                    'name' => 'B2B Module',
                    'is_beta' => false,
                    'apply_to_all_agency' => false,
                ],
                'simplifi_module' => [
                    'id' => 3,
                    'slug' => 'simplifi_module',
                    'name' => 'Simplifi Module',
                    'is_beta' => false,
                    'apply_to_all_agency' => false,
                ],
                'clean_module' => [
                    'id' => 4,
                    'slug' => 'clean_module',
                    'name' => 'Clean Module',
                    'is_beta' => false,
                    'apply_to_all_agency' => false,
                ],
                'predict_module' => [
                    'id' => 5,
                    'slug' => 'predict_module',
                    'name' => 'Predict Module',
                    'is_beta' => false,
                    'apply_to_all_agency' => false,
                ]
            ];
            // info(['userType' => $userType, 'companyParent' => $companyParent]);

            // ambil semua feature
            $featureBeta = MasterFeature::all();
            $featureUser = FeatureUser::where('company_id','=',$agencyCompanyID)->first();
            $isBeta = (isset($featureUser->is_beta) && $featureUser->is_beta == 'T') ? true : false;
            // info('', ['agencyCompanyID' => $agencyCompanyID, 'featureBeta' => $featureBeta, 'featureUser' => $featureUser]);
            
            $slugBetaFeature = ['data_wallet','b2b_module','simplifi_module','clean_module','predict_module'];
            foreach($featureBeta as $item)
            {
                if(in_array($item->slug, $slugBetaFeature))
                {
                    $betaFeature[$item->slug]['id'] = $item->id;
                    $betaFeature[$item->slug]['slug'] = $item->slug;
                    $betaFeature[$item->slug]['name'] = $item->name;
                    $betaFeature[$item->slug]['is_beta'] = ($item->is_beta == 'T') ? true : false;
                    $betaFeature[$item->slug]['apply_to_all_agency'] = ($item->apply_to_all_agency == 'T') ? true : false;
                }
            }
            // info('', ['isBeta' => $isBeta, 'betaFeature' => $betaFeature]);
            /* GET AND VALIDATE FEATURE */
    
            //AGENCY ONBOARD SETTING
            $rootonboardingagency = $this->getcompanysetting($getUserCurrentCompany[0]['company_root_id'], 'rootonboardingagency');

            $agency_onboarding_status = false;
            $agency_onboarding_price = 0;
            $agency_enable_coupon = true;
            if (!empty($rootonboardingagency) && isset($rootonboardingagency->status) && $rootonboardingagency->status == true) {
                
                $price = isset($rootonboardingagency->price) ? $rootonboardingagency->price : 0;
                $enable_coupon = true;
                
                $customonboardingagency = $this->getcompanysetting($getUserCurrentCompany[0]['company_id'],'agencyonboardingcustom');
                if (isset($customonboardingagency->amount)) {
                    $price = $customonboardingagency->amount;
                }
                if (isset($customonboardingagency->enable_coupon)) {
                    $enable_coupon = $customonboardingagency->enable_coupon;
                }

                $agency_onboarding_status = true;
                $agency_onboarding_price = $price;
                $agency_enable_coupon = $enable_coupon;
            }
            //AGENCY ONBOARD SETTING


            //ROOT MINSPEND
            $rootminspend = $this->getcompanysetting($getUserCurrentCompany[0]['company_root_id'], 'rootminspend');
            $minspend_setting = null; 
            if (!empty($rootminspend) && isset($rootminspend->enabled) && $rootminspend->enabled == 'T') {
                $minspend_setting['status'] = isset($rootminspend->enabled) && $rootminspend->enabled == 'T' ? true : false;
                $minspend_setting['first_month'] = isset($rootminspend->minspend_first_month) ? $rootminspend->minspend_first_month : 0;
                $minspend_setting['second_month'] = isset($rootminspend->minspend_second_month) ? $rootminspend->minspend_second_month : 0;
                $minspend_setting['monthly'] = isset($rootminspend->minspend) ? $rootminspend->minspend : 0;
            }
            //ROOT MINSPEND

            // DATA WALLET AGGREEMENT
            $isDataWalletAgree = false;
            $isDataWalletBeta = isset($betaFeature['data_wallet']['is_beta']) ? $betaFeature['data_wallet']['is_beta'] : false;
            $isDataWalletApplyToAll = isset($betaFeature['data_wallet']['apply_to_all_agency']) ? $betaFeature['data_wallet']['apply_to_all_agency'] : false;
            $dataWalletBetaId = isset($betaFeature['data_wallet']['id']) ? $betaFeature['data_wallet']['id'] : '';
            // info(['userType' => $userType,'companyRootID' => $getUserCurrentCompany[0]['company_root_id'],'systemid' => $systemid,'isDataWalletBeta' => $isDataWalletBeta,'isDataWalletApplyToAll' => $isDataWalletApplyToAll,'isBeta' => $isBeta,]);
            if (
                in_array($userType, ['user', 'userdownline']) && // jika agency
                $getUserCurrentCompany[0]['company_root_id'] == $systemid && // jika di root emm
                $isDataWalletBeta && // jika data wallet beta
                ($isDataWalletApplyToAll || $isBeta) // jika data wallet apply to all atau jika user ini beta 
            )
            {
                try 
                {
                    $dataRequest = new Request([
                        'user_id' => $request->usrID,
                        'feature_id' => $dataWalletBetaId,
                    ]);
                    $generalController = App::make(GeneralController::class);
                    $response = $generalController->getMarketingServicesAgreementFeature($dataRequest)->getData();
                    // info(['function' => __FUNCTION__,'action' => 'getMarketingServicesAgreementFeature','response' => $isDataWalletAgree,]);
                    $result = $response->result ?? "";
                    $status = $response->status ?? "";
                    if($result == 'success') 
                    {
                        $isDataWalletAgree = ($status == "T");
                    }
                } 
                catch (\Exception $e) 
                {
                    $isDataWalletAgree = false;
                }
            } 
            // DATA WALLET AGGREEMENT        

            //$modules = Module::get();
            $modules = Module::where('active','=','T')->get();
            $is_master_company = false;
            $loggedInCompanyID = auth()->user()->company_id;
            $is_master_company = ($loggedInCompanyID == $systemid);
            if ($ID !== '') {
               
                foreach($modules as $mdl) {
                    $mdl->create_permission = false;
                    $mdl->update_permission = false;
                    $mdl->delete_permission = false;
                    $mdl->enable_permission = false;
                    $mdl->entry_only = false;
                    $mdl->read_only = false;
                    $mdl->role_id = $ID;
                    $mdl->company_id = $CompanyID;

                    $permission = Module::find($mdl->id)->rolesmodules()->where('role_id','=',$ID)->where('company_id','=',$CompanyID)->get();
                    if (count($permission) > 0 ){
                        $mdl->create_permission = ($permission[0]->create_permission == 'T')?true:false;
                        $mdl->update_permission = ($permission[0]->update_permission == 'T')?true:false;
                        $mdl->delete_permission = ($permission[0]->delete_permission == 'T')?true:false;
                        $mdl->enable_permission = ($permission[0]->enable_permission == 'T')?true:false;   
                        
                        if ($permission[0]->create_permission == 'F' && $permission[0]->update_permission == 'F' 
                        && $permission[0]->delete_permission == 'F' && $permission[0]->enable_permission == 'T') {
                            $mdl->read_only = true;
                        }

                        if ($permission[0]->create_permission == 'T' && $permission[0]->update_permission == 'T' 
                        && $permission[0]->delete_permission == 'F' && $mdl->read_only == false && $permission[0]->enable_permission == 'T') {
                            $mdl->entry_only = true;
                        }



                    }
                    
                }

                // Log::info('rootsidebarleadmenu2', ['rootsidebarleadmenu' => $rootsidebarleadmenu]);
                
                return response()->json(array('result'=>'success','setupcomplete'=>$usrCompleteProfileSetup,'accountconnected'=>$accountConnected,'modules'=>$modules,'package_id'=>$package_id,'paymentgateway'=>$paymentgateway, 'is_whitelabeling' => $is_whitelabeling,'paymenttermlist' => $paymentTerms,'rootPaymentTermsNewAgencies' => $rootPaymentTermsNewAgencies, 'colors_parent' => $getColorsParentCompany,'rootsidemenu' => $rootsidebarleadmenu,'sidemenu' => $customsidebarleadmenu,'agencyDefaultModules' => $agencyDefaultModules, 'agencyFilteredModules' => $agencyFilteredModules, 'agency_side_menu' => $agency_side_menu, 'accessmodule_agency' => $accessmodule, 'fcampid'=>$failed_campaignid, 'finamt'=>$failed_total_amount, 'agency_onboarding_status' => $agency_onboarding_status,'agency_onboarding_price' => $agency_onboarding_price,'agency_enable_coupon' => $agency_enable_coupon, 'paymentStatusFailed'=>$paymentStatusFailed,'isBeta'=>$isBeta,'betaFeature'=>$betaFeature, 'minspend_setting' => $minspend_setting, 'api_mode'=>$apiMode, 'isDataWalletAgree' => $isDataWalletAgree, 'is_marketing_services_agreement_developer' => $is_marketing_services_agreement_developer, 'is_master_company' => $is_master_company, 'enabled_client_deleted_account' => $enabledClientDeletedAccount));
                //return $modules;
            }else {
                foreach($modules as $mdl) {
                    $mdl->create_permission = false;
                    $mdl->update_permission = false;
                    $mdl->delete_permission = false;
                    $mdl->enable_permission = false;
                    $mdl->entry_only = false;
                    $mdl->read_only = false;
                    $mdl->role_id = $ID;
                    $mdl->company_id = $CompanyID;
                }
                return response()->json(array('result'=>'success','setupcomplete'=>$usrCompleteProfileSetup,'accountconnected'=>$accountConnected,'modules'=>$modules,'package_id'=>$package_id,'paymentgateway'=>$paymentgateway,'rootsidemenu'=>$rootsidebarleadmenu,'sidemenu'=>$customsidebarleadmenu, 'accessmodule_agency' => $accessmodule, 'fcampid'=>$failed_campaignid, 'finamt'=>$failed_total_amount, 'agency_onboarding_status' => $agency_onboarding_status,'paymentStatusFailed'=>$paymentStatusFailed,'isBeta'=>$isBeta,'betaFeature'=>$betaFeature, 'minspend_setting' => $minspend_setting, 'api_mode'=>$apiMode, 'isDataWalletAgree' => $isDataWalletAgree, 'is_marketing_services_agreement_developer' => $is_marketing_services_agreement_developer, 'is_master_company' => $is_master_company, 'enabled_client_deleted_account' => $enabledClientDeletedAccount));
                //return $modules;
            }
        }
      
    }

    public function set_onboarding_agency(Request $request) {
        $company_id = (isset($request->CompanyID))?$request->CompanyID:'';
        $amount = (isset($request->amount))?$request->amount:'';
        $enable_coupon = (isset($request->enable_coupon))?$request->enable_coupon:'';
        $inOpenApi = (isset($request->inOpenApi))?$request->inOpenApi:false;
        $agency = User::where('company_id',$company_id)->where('user_type','userdownline')->where('active','T')->first();
        
        if (!empty($agency)) {
            try {
                if ($amount > 0.5) {
                    $agency->exclude_onboard_charge = 0;
                }else {
                    $agency->exclude_onboard_charge = 1;
                    $enable_coupon = false;
                }
                $agency->save();

                $company_setting = CompanySetting::where('company_id',$company_id)->whereEncrypted('setting_name','agencyonboardingcustom')->first();
                $comset_val = json_encode([
                    'amount' => $amount,
                    'enable_coupon' => $enable_coupon
                ]);
                
                if (!empty($company_setting)) {
                    $company_setting->setting_value = $comset_val;
                    $company_setting->save();
                }else {
                    CompanySetting::create([
                        'company_id' => $company_id,
                        'setting_name' => 'agencyonboardingcustom',
                        'setting_value' => $comset_val,
                    ]);
                }

                $status = $agency->exclude_onboard_charge == 0 ? 'Active' : 'Inactive';
                $ip_address = ($request->header('Clientip') !== null)?$request->header('Clientip'):$request->ip();
                $updated_by_user_id = ($inOpenApi === true) ? $agency->id : auth()->user()->id;
                $target_user_id = $agency->id;
                $description = "Agency Onboarding Charge Updated, Status Updated to : $status | Amount Updated to : $amount | CompanyID : $company_id | userID : $target_user_id";
                if($inOpenApi === true)
                    $description .= " | In Open Api : yes";
                $loguser = $this->logUserAction($updated_by_user_id,'Agency Onboarding Charge Updated',$description,$ip_address,$target_user_id);

            return response()->json([
                'result' => 'success',
                'message' => 'Agency status updated.',
                'status' => $agency->exclude_onboard_charge
            ]);
            } catch (\Throwable $th) {
            return response()->json([
                'result' => 'error',
                'message' => "Something went wrong, please try again later.",
                'status' => null,
                'error' => $th->getMessage() // optional: for debugging
            ]);
            }
        } 

        return response()->json([
            'result' => 'failed',
            'message' => "Agency not found.",
            'status' => null
        ]);
    }

    public function get_onboarding_agency(Request $request){
        $company_id = (isset($request->companyID))?$request->companyID:'';
        $company_root_id = (isset($request->companyRootID))?$request->companyRootID:'';
        $settingname = (isset($request->settingname))?$request->settingname:'';

        try {
            $settings = null;
            $default_onboarding_setting = $this->getcompanysetting($company_root_id,'rootonboardingagency');

            if (!empty($default_onboarding_setting) && isset($default_onboarding_setting->status) && $default_onboarding_setting->status == true  && isset($default_onboarding_setting->price)) {
                
                $settings['amount'] = $default_onboarding_setting->price;
                $settings['enable_coupon'] = true;
                                
                $agency_onboarding_setting = $this->getcompanysetting($company_id,$settingname);
    
                if (!empty($agency_onboarding_setting) && isset($agency_onboarding_setting->amount)) {
    
                    if (isset($agency_onboarding_setting->amount)) {
                        $settings['amount'] =  $agency_onboarding_setting->amount;
                    }
                    if (isset($agency_onboarding_setting->enable_coupon)) {
                        $settings['enable_coupon'] =  $agency_onboarding_setting->enable_coupon;
                    }
    
                }
            }
    
            return response()->json(['result' => 'success', 'data' => $settings]);
        } catch (\Throwable $th) {
            return response()->json(['result' => 'failed', 'data' => null]);
        }

    }

    public function salesdownline(Request $request) {
        $userID = (isset($request->usrID))?$request->usrID:'';
        $CompanyID = (isset($request->CompanyID))?$request->CompanyID:'';
        $idsys = (isset($request->idsys))?$request->idsys:'';
        $PerPage = $PerPage ?? $request->input('PerPage', 10);
        $Page = (isset($request->Page))?$request->Page:'';
        $searchKey = (isset($request->searchKey))?$request->searchKey:'';
        $sortby = (isset($request->SortBy))?$request->SortBy:'';
        $order = (isset($request->OrderBy))?$request->OrderBy:'';
        
        /** GET ROOT SYS CONF */
        $confAppDomain =  config('services.application.domain');
        if ($idsys != "") {
            $conf = $this->getCompanyRootInfo($idsys);
            $confAppDomain = $conf['domain'];
        }
        /** GET ROOT SYS CONF */

        $downline = User::select('users.*',DB::raw('"" as sales'),DB::raw('"" as salesrep'),DB::raw('"" as accountexecutive'),'companies.company_name','companies.simplifi_organizationid','companies.domain', 'companies.subdomain as orisubdomain', DB::raw('REPLACE(companies.subdomain,".' . $confAppDomain . '","") as subdomain'))
                    ->distinct()
                    ->join('companies','companies.id','=','users.company_id')
                    ->join('company_sales','users.company_id','=','company_sales.company_id')
                    ->where('company_parent','=',$CompanyID)
                    ->where('company_sales.sales_id','=',$userID)
                    ->where('company_sales.sales_title','=','Account Executive')
                    ->where('active','T')->where('user_type','=','userdownline')
                    ->with('children');
                    // ->orderBy('sort')
                    // ->orderByEncrypted('companies.company_name');
                    //->get();

                    if (trim($searchKey) != '') {
                        $salt = substr(hash('sha256', env('APP_KEY')), 0, 16);
                        
                        $downline->where(function($query) use ($searchKey,$salt) {
                            $query->orWhere(DB::raw("CONVERT(AES_DECRYPT(FROM_bASE64(companies.company_name), '" . $salt . "') USING utf8mb4)"),'like','%' . $searchKey . '%')
                            ->orWhere(DB::raw("CONVERT(AES_DECRYPT(FROM_bASE64(users.name), '" . $salt . "') USING utf8mb4)"),'like','%' . $searchKey . '%')
                            ->orWhere(DB::raw("CONVERT(AES_DECRYPT(FROM_bASE64(users.email), '" . $salt . "') USING utf8mb4)"),'like','%' . $searchKey . '%')
                            ->orWhere(DB::raw("DATE_FORMAT(users.created_at,'%m-%d-%Y')"),'like','%' . $searchKey . '%');
                        });
                    }

                    if (trim($order) != '') {
                        if (trim($order) == 'descending') {
                            $order = "DESC";
                        }else{
                            $order = "ASC";
                        }
                    }

                    
                    if (trim($sortby) != '') {
                        if (trim($sortby) == "company_name") {
                            $downline = $downline->orderByEncrypted('companies.company_name',$order);
                        }else if (trim($sortby) == "full_name") {
                            $downline = $downline->orderByEncrypted('users.name',$order);
                        }else if (trim($sortby) == "email") {
                            $downline = $downline->orderByEncrypted('users.email',$order);
                        }else if (trim($sortby) == "phone") {
                            $downline = $downline->orderByEncrypted('users.phonenum',$order);
                        }else if (trim($sortby) == "created_at") {
                            $downline = $downline->orderBy(DB::raw('CAST(users.created_at AS DATETIME)'),$order);
                        }
                        $downline = $downline->paginate($PerPage, ['*'], 'page', $Page);
                    } else {
                        $downline = $downline->orderByEncrypted('companies.company_name')->paginate($PerPage, ['*'], 'page', $Page);
                    }

                    
                    foreach($downline as $dl) {
                        /** CHECK STRIPE CONNECTED ACCOUNT */
                        $companyConnectStripe = CompanyStripe::select('status_acc','acc_connect_id','package_id')->where('company_id','=',$dl['company_id'])
                                                ->get();
        
                        $accountConnected = '';
                        $package_id = '';
        
                        if (count($companyConnectStripe) > 0) {
                            $accountConnected = $companyConnectStripe[0]['status_acc'];
                            $package_id = ($companyConnectStripe[0]['package_id'] != '')?$companyConnectStripe[0]['package_id']:"";
                        }
                        /** CHECK STRIPE CONNECTED ACCOUNT */
        
                        $dl['status_acc'] = $accountConnected;
                        $dl['package_id'] = $package_id;
        
                        /** CHECK SALES  */
                        $chksales = User::select('users.id','users.name','company_sales.sales_title')
                                            ->join('company_sales','users.id','=','company_sales.sales_id')
                                            ->where('company_sales.company_id','=',$dl['company_id'])
                                            ->where('users.active','=','T')
                                            ->get();
        
                        $compsalesrepID = "";
                        $compsalesrep = "";
                        $compaccountexecutive = "";
                        $compaccountexecutiveID = "";
                        $compaccountref = "";
                        $compaccountrefID = "";
        
                        foreach($chksales as $sl) {
                            /*$tmpsales = [
                                'name' => $sl['name'],
                                'title' => $sl['sales_title'],
                            ];*/
                            //$compsales = $compsales .  $sl['name'] . '-' . $sl['sales_title'] . '|';
                            //array_push($compsales,$tmpsales);
                            if ($sl['sales_title'] == "Sales Representative") {
                                $compsalesrepID = $sl['id'];
                                $compsalesrep = $sl['name'];
                            }
                            if ($sl['sales_title'] == "Account Executive") {
                                $compaccountexecutiveID = $sl['id'];
                                $compaccountexecutive = $sl['name'];
                            }
                            if ($sl['sales_title'] == "Account Referral") {
                                $compaccountrefID = $sl['id'];
                                $compaccountref = $sl['name'];
                            }
                        }
                        //$compsales = rtrim($compsales,"|");
                        $dl['salesrepid'] = $compsalesrepID;
                        $dl['salesrep'] = $compsalesrep;
                        $dl['accountexecutiveid'] = $compaccountexecutiveID;
                        $dl['accountexecutive'] = $compaccountexecutive;
                        $dl['accountrefid'] = $compaccountrefID;
                        $dl['accountref'] = $compaccountref;
                        /** CHECK SALES */

                         // ROOT PAYMENT TERM
                        $root_payment_term = $this->getcompanysetting($dl['company_root_id'], 'rootpaymentterm');
                        if ($root_payment_term && isset($root_payment_term->PaymentTerm)) {
                            $dl['rootpaymentterm'] = $root_payment_term->PaymentTerm;
                        }
                        // ROOT PAYMENT TERM

                        // SELECTED PAYMENT TERM
                        $selected_payment_term = $this->getcompanysetting($dl['company_id'], 'agencypaymentterm');
                        $root_term = $this->getcompanysetting($dl['company_root_id'], 'rootpaymentterm');
                        if ($selected_payment_term) {
                            $dl['selected_payment_term'] = $selected_payment_term->SelectedPaymentTerm;
                        }else {
                            // set value to true for agency that doesn't have agencypaymentterm
                            try {
                                foreach ($root_term->PaymentTerm as $term) {
                                    $term->term = $term->value;
                                    unset($term->label);
                                    $term->status = true;
                                }
                                $dl['selected_payment_term'] = $root_term->PaymentTerm;
                            } catch (\Throwable $th) {
                            return response()->json(['selected_payment_term'=> $dl['selected_payment_term'], 'errmsg' => $th->getMessage()]);
                            }
                        }
                        // SELECTED PAYMENT TERM

                        //ROOT MODULES FOR AGENCY-LIST
                        $root_modules = [];
                        $root_modules_setting = $this->getcompanysetting($dl['company_root_id'], 'rootcustomsidebarleadmenu');
                        if (!empty($root_modules_setting)) {
                            foreach ($root_modules_setting as $key => $value) {
                                $root_modules[] = [
                                    'value' => $key,
                                    'label' => $value->name,
                                ];
                            }
                        }
                        $dl['rootmodules'] = $root_modules;
                        //ROOT MODULES FOR AGENCY-LIST

                        // SELECTED MODULES FOR AGENCY-LIST
                        $selected_modules = $this->getcompanysetting($dl['company_id'], 'agencysidebar');
                        $root_module = $this->getcompanysetting($dl['company_root_id'], 'rootcustomsidebarleadmenu');
                        if ($selected_modules) {
                            // if there is agency sidebar setting, it will use it. but it will check to rootcustomsidebarleadmenu first to match the modules.
                            $agencysidebar = $selected_modules->SelectedModules;
                            $root_modules = array_keys((array)$root_module);
                            // Ambil tipe yang ada di selected_modules
                            $existing_terms = [];
                            foreach ($agencysidebar as $module) {
                                $existing_terms[] = $module->type;
                            }
                            // Cek setiap item di root_term
                            foreach ($root_modules as $mod) {
                                // Jika tipe dari root_term belum ada di selected_modules
                                if (!in_array($mod, $existing_terms)) {
                                    $agencysidebar[] = (object)[
                                        'type' => $mod,
                                        'status' => true,
                                    ];
                                }
                            }
                            $dl['selected_modules'] = $agencysidebar;
                            // $dl['selected_modules'] = $selected_modules->SelectedModules;
                        }else {
                            $exist_setting = $this->getcompanysetting($dl['company_root_id'], 'rootexistagencymoduleselect');
                            if (!empty($exist_setting) && isset($exist_setting->SelectedModules)) {
                                $dl['selected_modules'] = $exist_setting->SelectedModules;
                            }else {
                                $agencysidebar = [];
                                    foreach ($root_modules_setting as $key => $value) {
                                        $agencysidebar[] = [
                                            'type' => $key,
                                            'status' => true,
                                        ];
                                    }
                                $dl['selected_modules'] = $agencysidebar;
                            }
                        }
                        // SELECTED MODULES FOR AGENCY-LIST
                    }
        
                    return $downline;

    }

    public function show(Request $request) 
    {
        $UserType = (isset($request->UserType))?$request->UserType:'';
        $PerPage = $PerPage ?? $request->input('PerPage', 10);
        $Page = (isset($request->Page))?$request->Page:'';
        $CardStatus = json_decode($request->input('CardStatus', '{}'), true);
        $CampaignStatus = json_decode($request->input('CampaignStatus', '{}'), true);
        $Filters = json_decode($request->input('Filters', '{}'), true);
        $Exclude = json_decode($request->input('Exclude', '{}'), true);
        $inAgencyList = $request->input('inAgencyList', '');

        // info($Filters['directPayment']['active']);
        
        $CompanyID = (isset($request->CompanyID))?$request->CompanyID:'';
        $sortby = (isset($request->SortBy))?$request->SortBy:'';
        $order = (isset($request->OrderBy))?$request->OrderBy:'';
        $idsys = (isset($request->idsys))?$request->idsys:'';
        $searchKey = (isset($request->searchKey))?$request->searchKey:'';

        /** GET ROOT SYS CONF */
        $confAppDomain =  config('services.application.domain');
        if (trim($idsys) != "") {
            $conf = $this->getCompanyRootInfo(trim($idsys));
            if (isset($conf['domain'])) {
                $confAppDomain = $conf['domain'];
            }
        }
        /** GET ROOT SYS CONF */
        
        /*$givelist->where(function($query) use ($searchText)
                {
                    $query->orWhere('givers.name','LIKE','%' . $searchText . '%')
                            ->orWhere('funds.fund_name','LIKE','%' . $searchText . '%')
                            ->orWhere('givers.status','LIKE','%' . $searchText . '%')
                            ->orWhere('givers.gift_type','LIKE','%' . $searchText . '%')
                            ->orWhere('givers.schedule_type','LIKE','%' . $searchText . '%')
                            ->orWhere('givers.amount','LIKE', floatval($searchText))
                            ->orWhere('givers.fee','LIKE',floatval($searchText))
                            ->orWhere(DB::raw('DATE_FORMAT(givers.transaction_date,"%Y-%m-%d")'),'LIKE','%' . $searchText . '%');
                }
            );
        */
        if($UserType == 'client') {
            $UserModule = (isset($request->UserModule))?$request->UserModule:'';
            $groupCompanyID = (isset($request->groupCompanyID))?$request->groupCompanyID:'';
            $SortBy = (isset($request->SortBy))?$request->SortBy:'';

            /** GET IF AGENCY HAVE MANUAL BILL */
            $agencyManualBill = 'F';
            $agency = Company::select('manual_bill')->where('id','=',$CompanyID)->get();
            if (count($agency) > 0) 
            {
                $agencyManualBill = $agency[0]['manual_bill'];
            }
            /** GET IF AGENCY HAVE MANUAL BILL */

            //return  User::select('users.*','companies.company_name')->join('companies','companies.id','=','users.company_id')->where('company_parent',$CompanyID)->where('active','T')->where('user_type','=','client')->get();
            //$user =  User::select('users.*','companies.company_name','sites.domain',DB::raw('"" as newpassword'),'leadspeek_users.active_user','leadspeek_users.leadspeek_type','leadspeek_users.leadspeek_api_id')
            //$user =  User::select('users.*','companies.company_name','sites.domain',DB::raw('"" as newpassword'))
            $user =  User::select(
                                'users.*',
                                'companies.manual_bill',
                                'companies.paymentterm_default',
                                'companies.company_name', 
                                'companies.simplifi_organizationid as external_organizationid',
                                'companies.optoutfile',
                                'sites.domain',
                                'companies.subdomain as orisubdomain',
                                DB::raw('REPLACE(companies.subdomain,".' . $confAppDomain . '","") as subdomain'),
                                DB::raw('"" as newpassword')
                            )
                            ->leftjoin('companies','companies.id','=','users.company_id')
                            ->leftjoin('sites','users.site_id','=','sites.id')
                            ->leftjoin('leadspeek_users','users.id','=','leadspeek_users.user_id'); // search bedasarkan leadspeek_api_id hanya untuk di agency list

            if ($groupCompanyID != 'all') 
            {
                $user->where('users.id',$groupCompanyID);
            }
            else
            {
                $user->where('users.company_parent',$CompanyID);
            }

            $user = $user->where('users.active','T')
                         ->where('users.user_type','=','client');

            if ($SortBy == "LeadsPeek") 
            {
                if ($agencyManualBill == 'F') 
                {
                    $user->where('users.customer_payment_id','<>','')
                         ->where('users.customer_card_id','<>','');
                }
            }
            /*if($UserModule != '' && $UserModule == 'LeadsPeek') {
                $user->whereNotIn('users.id',function($query) {

                    $query->select('user_id')->from('leadspeek_users');
                 
                 });
            }*/

            /*if(trim($groupCompanyID) != '' && trim($groupCompanyID) != 'all') {
                $user->where('leadspeek_users.group_company_id','=',trim($groupCompanyID));
            }*/
            if (trim($searchKey) != '') 
            {
                $salt = substr(hash('sha256', env('APP_KEY')), 0, 16);
                
                $user->where(function($query) use ($searchKey,$salt,$inAgencyList) {
                    $query->orWhere(DB::raw("CONVERT(AES_DECRYPT(FROM_bASE64(companies.company_name), '" . $salt . "') USING utf8mb4)"),'like','%' . $searchKey . '%')
                          ->orWhere(DB::raw("CONVERT(AES_DECRYPT(FROM_bASE64(users.name), '" . $salt . "') USING utf8mb4)"),'like','%' . $searchKey . '%')
                          ->orWhere(DB::raw("CONVERT(AES_DECRYPT(FROM_bASE64(users.email), '" . $salt . "') USING utf8mb4)"),'like','%' . $searchKey . '%')
                          ->orWhere(DB::raw("CONVERT(AES_DECRYPT(FROM_bASE64(users.phonenum), '" . $salt . "') USING utf8mb4)"),'like','%' . $searchKey . '%')
                          ->orWhere(DB::raw("DATE_FORMAT(users.created_at,'%m-%d-%Y')"),'like','%' . $searchKey . '%');

                    if($inAgencyList == true)
                    {
                        $query->orWhere('leadspeek_users.leadspeek_api_id','like',"%$searchKey%");
                    }
                });
            }

            if (trim($order) != '') 
            {
                if (trim($order) == 'descending') 
                {
                    $order = "DESC";
                }
                else
                {
                    $order = "ASC";
                }
            }

            // Filter card status
            $cardStatus = (object) array_merge([
                'active' => false,
                'failed' => false,
                'inactive' => false,
            ], $CardStatus);

            $activeCardStatus = $cardStatus->active;
            $failedCardStatus = $cardStatus->failed;
            $inactiveCardStatus = $cardStatus->inactive;

            if($activeCardStatus && $inactiveCardStatus &&  $failedCardStatus)
            {
                $user->where(function($query) {
                    $query->orWhere('users.customer_payment_id', '')
                          ->orWhere('users.customer_card_id', '')
                          ->orWhere('users.customer_payment_id', 'LIKE', '%cus%')
                          ->orWhere('users.customer_card_id', 'LIKE', '%card%')
                          ->orWhere('users.customer_card_id', 'LIKE', '%agency%')
                          ->orWhere('users.customer_payment_id', 'LIKE', '%agency%');
                });
            } 
            else if($activeCardStatus && $inactiveCardStatus)
            {
                $user->where(function($query) {
                    $query->orWhere('users.customer_payment_id', '')
                          ->orWhere('users.customer_card_id', '')
                          ->orWhere('users.customer_payment_id', 'LIKE', '%cus%')
                          ->orWhere('users.customer_card_id', 'LIKE', '%card%')
                          ->orWhere('users.customer_card_id', 'LIKE', '%agency%')
                          ->orWhere('users.customer_payment_id', 'LIKE', '%agency%');
                })->where(function($query) {
                    $query->where('users.payment_status', '!=', 'failed')
                          ->orWhereNull('users.payment_status')
                          ->orWhere('users.payment_status', '=', '');
                });
            } 
            else if($activeCardStatus &&  $failedCardStatus)
            {
                $user->where(function($query) {
                    $query->orWhere('users.customer_payment_id', 'LIKE', '%cus%')
                          ->orWhere('users.customer_card_id', 'LIKE', '%card%')
                          ->orWhere('users.customer_card_id', 'LIKE', '%agency%')
                          ->orWhere('users.customer_payment_id', 'LIKE', '%agency%')
                          ->orWhere('users.payment_status', '=', 'failed');
                });
            } 
            else if ($inactiveCardStatus &&  $failedCardStatus) 
            {
                $user->where(function($query) {
                    $query->where('users.customer_payment_id', '')
                          ->orWhere('users.customer_card_id', '')
                          ->orWhere('users.payment_status', '=', 'failed');
                });
            } 
            else if ($inactiveCardStatus) 
            {
                $user->where(function($query) {
                    $query->where('users.customer_payment_id', '')
                          ->orWhere('users.customer_card_id', '');
                });
            } 
            elseif ($activeCardStatus) 
            {
                $user->where(function($query) {
                    $query->where('users.customer_payment_id', 'LIKE', '%cus%')
                          ->orWhere('users.customer_card_id', 'LIKE', '%card%')
                          ->orWhere('users.customer_card_id', 'LIKE', '%agency%')
                          ->orWhere('users.customer_payment_id', 'LIKE', '%agency%');
                })->where(function($query) {
                    $query->where('users.payment_status', '!=', 'failed')
                          ->orWhereNull('users.payment_status')
                          ->orWhere('users.payment_status', '=', '');
                });
            } 
            elseif ($failedCardStatus) 
            {
                $user->where(function($query) {
                    $query->where('users.payment_status', 'failed');
                });
            }
            // Filter card status

            // Filter campaign status
            $campaignStatus = (object) array_merge([
                'active' => false,
                'inactive' => false,
            ], $CampaignStatus);

            $activeCampaignStatus = $campaignStatus->active;
            $inactiveCampaignStatus = $campaignStatus->inactive;

            if($activeCampaignStatus && $inactiveCampaignStatus)
            {
                $user->whereIn('users.id', function($query) {
                    $query->select('user_id')
                          ->from('leadspeek_users')
                          ->where('archived', '=', 'F')
                          ->groupBy('user_id')
                          ->havingRaw('SUM(CASE WHEN (active = "T" OR active = "F") AND disabled = "F" AND active_user = "T" THEN 1 ELSE 0 END) > 0')
                          ->havingRaw('SUM(CASE WHEN active = "F" AND disabled = "T" AND (active_user = "T" OR active_user = "F") THEN 1 ELSE 0 END) > 0');
                });
            } 
            else if ($activeCampaignStatus)
            {
                $user->whereIn('users.id', function($query) {
                    $query->select('user_id')
                          ->from('leadspeek_users')
                          ->where('archived', '=', 'F')
                          ->groupBy('user_id')
                          ->havingRaw('SUM(CASE WHEN (active = "T" OR active = "F") AND disabled = "F" AND active_user = "T" THEN 1 ELSE 0 END) > 0');
                });
            } 
            else if ($inactiveCampaignStatus)
            {
                $user->whereIn('users.id', function($query) {
                    $query->select('user_id')
                          ->from('leadspeek_users')
                          ->where('archived', '=', 'F')
                          ->groupBy('user_id')
                          ->havingRaw('SUM(CASE WHEN active = "F" AND disabled = "T" AND (active_user = "T" OR active_user = "F") THEN 1 ELSE 0 END) > 0');
                });
            }
            // Filter campaign status

            $user->groupBy('users.id'); // ini penting karena sekarang lef join dengan leadspeek_users

            if (trim($sortby) != '') 
            {
                if (trim($sortby) == "company_name") 
                {
                    $user = $user->orderByEncrypted('companies.company_name',$order);
                }
                else if (trim($sortby) == "full_name") 
                {
                    $user = $user->orderByEncrypted('users.name',$order);
                }
                else if (trim($sortby) == "email") 
                {
                    $user = $user->orderByEncrypted('users.email',$order);
                }
                else if (trim($sortby) == "phone") 
                {
                    $user = $user->orderByEncrypted('users.phonenum',$order);
                }
                else if (trim($sortby) == "created_at") 
                {
                    $user = $user->orderBy(DB::raw('CAST(users.created_at AS DATETIME)'),$order);
                }

                if ($Page == '') 
                { 
                    $user = $user->get();
                }
                else
                {
                    $user = $user->paginate($PerPage, ['*'], 'page', $Page);
                }
            }
            else
            {
                if ($Page == '') 
                { 
                    $user = $user->orderByEncrypted('companies.company_name')->get();
                }
                else
                {
                    $user = $user->orderByEncrypted('companies.company_name')->paginate($PerPage, ['*'], 'page', $Page);
                }
            }

            foreach($user as $a => $us) 
            {    
                $user[$a]['manual_bill'] = $agencyManualBill;
                
                /** GET ACTIVE CAMPAIGN*/
                $activeCampaign = LeadspeekUser::select(DB::raw('COUNT(*) as activecampaign'))
                                               ->where(function($query){
                                                    $query->where(function($query){
                                                        $query->where('active','=','T')
                                                              ->where('disabled','=','F')
                                                              ->where('active_user','=','T');
                                                    })
                                                    ->orWhere(function($query){
                                                        $query->where('active','=','F')
                                                              ->where('disabled','=','F')
                                                              ->where('active_user','=','T');
                                                    });
                                                })
                                                ->where('archived','=','F')
                                                ->where('user_id','=',$us['id'])
                                                ->get();
                if (count($activeCampaign) > 0) 
                {
                    $user[$a]['campaign_active'] = $activeCampaign[0]['activecampaign'];
                }
                else
                {
                    $user[$a]['campaign_active'] = 0;
                }
                /** GET ACTIVE CAMPAIGN */

                /** GET PAUSE / STOP CAMPAIGN*/
                $notActiveCampaign = LeadspeekUser::select(DB::raw('COUNT(*) as notactivecampaign'))
                                                  ->where(function($query){
                                                        $query->where(function($query){
                                                            $query->where('active','=','F')
                                                                  ->where('disabled','=','T')
                                                                  ->where('active_user','=','T');
                                                        })
                                                        ->orWhere(function($query){
                                                            $query->where('active','=','F')
                                                                ->where('disabled','=','T')
                                                                ->where('active_user','=','F');
                                                        });
                                                  })
                                                  ->where('archived','=','F')
                                                  ->where('user_id','=',$us['id'])
                                                  ->get();
                if (count($notActiveCampaign) > 0) 
                {
                    $user[$a]['campaign_not_active'] = $notActiveCampaign[0]['notactivecampaign'];
                }
                else
                {
                    $user[$a]['campaign_not_active'] = 0;
                }
                /** GET PAUSE / STOP CAMPAIGN*/

                // SELECTED MODULES FOR CLIENT MANAGEMENT IN AGENCY

                // filter root_module with agency setting before client use it
                $root_module = CompanySetting::where('company_id',trim($user[$a]['company_root_id']))->whereEncrypted('setting_name','rootcustomsidebarleadmenu')->get();
                if (count($root_module) > 0) 
                {
                    $rootsidebarleadmenu = json_decode($root_module[0]['setting_value']);
                    $agencysidebar = $this->getcompanysetting($user[$a]['company_parent'], 'agencysidebar');
                    $exist_setting_agency = $this->getcompanysetting($user[$a]['company_root_id'], 'rootexistagencymoduleselect');
                    if (!empty($agencysidebar) && isset($agencysidebar->SelectedModules)) 
                    {
                        foreach ($agencysidebar->SelectedModules as $key => $value) 
                        {
                            foreach ($rootsidebarleadmenu as $key1 => $value1) 
                            {
                                if ($key1 == $value->type && $value->status == false) 
                                {
                                    unset($rootsidebarleadmenu->$key1);
                                }
                            }
                        }
                    } 
                    elseif (!empty($exist_setting_agency) && isset($exist_setting_agency->SelectedModules)) 
                    {
                        foreach ($exist_setting_agency->SelectedModules as $key => $value) 
                        {
                            foreach ($rootsidebarleadmenu as $key2 => $value2) 
                            {
                                if ($key2 == $value->type && $value->status == false) 
                                {
                                    unset($rootsidebarleadmenu->$key2);
                                }
                            }
                        }
                    }

                    $root_module = $rootsidebarleadmenu;
                }
                // filter root_module with agency setting before client use it

                $selected_modules = $this->getcompanysetting($user[$a]['company_id'], 'clientsidebar');
                $client_side_menu = [];
                if (!empty($selected_modules) && isset($selected_modules->SelectedModules) || !empty($selected_modules) && isset($selected_modules->SelectedSideBar)) //IF CLIENT HAS CLIENTSIDEBAR SETTING
                {
                    // if there is clientsidebar sidebar setting, it will use it. but it will check to rootcustomsidebarleadmenu first to match the modules.
                    if (isset($selected_modules->SelectedModules)) 
                    {
                        $clientsidebar = $selected_modules->SelectedModules;
                    }
                    elseif (isset($selected_modules->SelectedSideBar)) //it's for the latest setting that use SelectedSideBar for variable 
                    {  
                        $clientsidebar = $selected_modules->SelectedSideBar;
                    }
                    $root_modules = array_keys((array)$root_module);

                    // Ambil tipe yang ada di selected_modules
                    $existing_modules = [];
                    foreach ($clientsidebar as $module) 
                    {
                        $existing_modules[] = $module->type;
                    }
                    
                    // Cek setiap item di root_modules
                    $exist_setting_client = $this->getcompanysetting($user[$a]['company_root_id'], 'rootexistclientmoduleselect');
                    if (!empty($exist_setting_client) && isset($exist_setting_client->SelectedModules)) // for handle when root add new modules after clientsidebar created 
                    { 
                        foreach ($root_modules as $mod) 
                        {
                            // Jika tipe dari root_modules belum ada di selected_modules
                            if (!in_array($mod, $existing_modules)) 
                            {
                                foreach ($exist_setting_client->SelectedModules as $key => $value) 
                                {
                                    if ($mod == $value->type) 
                                    {
                                        $clientsidebar[] = (object)[
                                            'type' => $mod,
                                            'status' => $value->status,//take the status in rootexistclientmoduleselect
                                        ];
                                    }
    
                                }
                            }
                        }
                    }
                    else 
                    {
                        foreach ($root_modules as $mod) 
                        {
                            // Jika tipe dari root_modules belum ada di selected_modules
                            if (!in_array($mod, $existing_modules)) 
                            {
                                $clientsidebar[] = (object)[
                                    'type' => $mod,
                                    'status' => true //make the status to be true
                                ];
                            }
                        }
                    }
                    $client_side_menu = $clientsidebar;
                }
                else //IF CLIENT DIDN'T HAVE CLIENTSIDEBAR SETTING 
                {
                    $root_clientsidebar = $this->getcompanysetting($user[$a]['company_root_id'], 'rootcustomsidebarleadmenu');
                    if (!empty($root_clientsidebar)) 
                    {
                        $exist_setting = $this->getcompanysetting($user[$a]['company_root_id'], 'rootexistclientmoduleselect');
                        if (!empty($exist_setting && isset($exist_setting->SelectedModules))) 
                        {
                            foreach ($exist_setting->SelectedModules as $key => $value) 
                            {
                                foreach ($root_clientsidebar as $key2 => $value2) 
                                {
                                    if ($key2 == $value->type && $value->status == false) 
                                    {
                                        unset($root_clientsidebar->$key2);
                                    }
                                }
                            }
                        }
                        foreach($root_clientsidebar as $key => $value)
                        {
                            $client_side_menu[] = [
                                'type' => $key,
                                'status' => true
                            ];    
                        }
                    }
                }

                $user[$a]['selected_side_bar'] = $client_side_menu;
                // SELECTED MODULES FOR CLIENT MANAGEMENT IN AGENCY
                
                // GET CLIENT AGREEMENT FILES 
                $agreement_file = CompanyAgreementFile::where('company_id', $us['company_id'])->first();
                // if (!empty($agreement_file)) {
                if (false) {
                    $base_url = env('APP_URL');
                    $download_url = $base_url . '/download-agreement?agreement_id=' . urlencode($agreement_file->uuid);
                    $user[$a]['company_agreement_file'] = $download_url;
                }else {
                    $user_agree = UserLog::where('user_id', $us['id'])
                                        ->where('target_user_id', $us['id'])
                                        ->whereIn('action', ['Service and Billing Agreement', 'Payment Direct Setup services'])
                                        ->orderBy('created_at', 'desc')
                                        ->exists();
                    if ($user_agree) {
                        $user[$a]['company_agreement_file'] = 'download';
                    }else {
                        $user[$a]['company_agreement_file'] = null;
                    }
                }
                // GET CLIENT AGREEMENT FILES 
                
            }

            return $user;
        }else if($UserType == 'userdownline') {
            //return User::select('users.*','companies.company_name')->join('companies','companies.id','=','users.company_id')->where('company_parent','=',$CompanyID)->where('user_type','=','userdownline')->with('child')->get();
            //return User::select('users.*','companies.company_name','companies.simplifi_organizationid','companies.domain',DB::raw('REPLACE(companies.subdomain,".' . config('services.application.domain') . '","") as subdomain'))->join('companies','companies.id','=','users.company_id')->where('company_parent','=',$CompanyID)->where('active','T')->where('user_type','=','userdownline')->with('children')->orderBy('sort')->orderByEncrypted('companies.company_name')->get();
            $childrensearch = false;

            // CARI BY AGENCY
            $downline = User::select(
                                'users.*',
                                DB::raw('"" as sales'),
                                DB::raw('"" as salesrep'),
                                DB::raw('"" as accountexecutive'),
                                'companies.manual_bill',
                                'companies.company_name',
                                'companies.simplifi_organizationid as external_organizationid',
                                'companies.domain',
                                'companies.is_whitelabeling',
                                'companies.subdomain as orisubdomain',
                                DB::raw('REPLACE(companies.subdomain,".' . $confAppDomain . '","") as subdomain'), 
                                DB::raw('CASE WHEN open_api_users.client_id IS NOT NULL AND open_api_users.secret_key IS NOT NULL THEN true ELSE false END as is_user_generate_api')
                            )
                            ->join('companies','companies.id','=','users.company_id')
                            ->leftJoin('open_api_users', 'open_api_users.company_id', '=', 'users.company_id')
                            ->where('company_parent','=',$CompanyID)
                            ->where('active','T')
                            ->where('user_type','=','userdownline');
                            // ->with('children');
            // CARI BY AGENCY

            if (trim($searchKey) != '') 
            {
                // info("$UserType search key");
                $salt = substr(hash('sha256', env('APP_KEY')), 0, 16);

                $downline->where(function($query) use ($searchKey,$salt) {
                    $query->orWhere(DB::raw("CONVERT(AES_DECRYPT(FROM_bASE64(companies.company_name), '" . $salt . "') USING utf8mb4)"),'like','%' . $searchKey . '%')
                          ->orWhere(DB::raw("CONVERT(AES_DECRYPT(FROM_bASE64(users.email), '" . $salt . "') USING utf8mb4)"),'like','%' . $searchKey . '%')
                          ->orWhere(DB::raw("CONVERT(AES_DECRYPT(FROM_bASE64(users.name), '" . $salt . "') USING utf8mb4)"),'like','%' . $searchKey . '%');
                });
            }

            //// filter direct payment
            if ($Filters['directPayment']['active']) 
            {
                $downline->where('companies.manual_bill','=','T');
            }
            //// filter payment status
            if ($Filters['paymentFailed']['active']) 
            {
                $downline->where('users.payment_status','=','failed');
            }

            // FILTER USER GENERATE API
            if (isset($Filters['isUserGenerateApi']) && $Filters['isUserGenerateApi']['active']) 
            {
                $downline->whereNotNull('open_api_users.client_id')
                         ->whereNotNull('open_api_users.secret_key');
            }
            // FILTER USER GENERATE API

            // FILTER ENABLED DEVELOPER MODE
            if (isset($Filters['isOpenApiMode']) && $Filters['isOpenApiMode']['active']) 
            {
                $downline->where('users.api_mode','=','T');
            }
            // FILTER ENABLED DEVELOPER MODE

            if (trim($order) != '') 
            {
                if (trim($order) == 'descending') 
                {
                    $order = "DESC";
                }
                else
                {
                    $order = "ASC";
                }
            }

            if(trim($sortby) != '')
            {
                if (trim($sortby) == "full_name") 
                {
                    $downline = $downline->orderByEncrypted('users.name',$order);
                }
                else if (trim($sortby) == "company_name") 
                {
                    $downline = $downline->orderByEncrypted('companies.company_name',$order);
                }
                else if (trim($sortby) == "email") 
                {
                    $downline = $downline->orderByEncrypted('users.email',$order);
                }
                else if (trim($sortby) == "created_at") 
                {
                    $downline = $downline->orderBy(DB::raw('CAST(users.created_at AS DATETIME)'),$order);
                }
                // else if (trim($sortby) == "payment_status") {
                //     $downline = $downline->orderBy('users.payment_status',$order);
                // }

                if ($Page == '') 
                {
                    $downline = $downline->orderBy('sort')->orderByEncrypted('companies.company_name')->get();
                }
                else
                {
                    $downline = $downline->orderBy('sort')->orderByEncrypted('companies.company_name')->paginate($PerPage, ['*'], 'page', $Page);
                }
            } 
            else 
            {
                if ($Page == '') 
                { 
                    $downline = $downline->orderBy('sort')->orderByEncrypted('companies.company_name')->get();
                }
                else
                {
                    $downline = $downline->orderBy('sort')->orderByEncrypted('companies.company_name')->paginate($PerPage, ['*'], 'page', $Page);
                }
            }

            /** IF SEARCH ON AGENCY EMPTY RESULT, JIKA DARI AGENCY TIDAK DITEMUKAN CARI BEDASARKAN DATA CLIENT ATAU DATA CAMPAIGN ATAU DATA CLEAN ID */
            if (count($downline) == 0 && trim($searchKey) != '') 
            {
                $childrensearch = true;

                /* NEW */
                $downline = User::from('users as agency')
                                ->select(
                                    'agency.*',
                                    DB::raw('"" as sales'),
                                    DB::raw('"" as salesrep'),
                                    DB::raw('"" as accountexecutive'),
                                    'company_agency.manual_bill',
                                    'company_agency.company_name',
                                    'company_agency.simplifi_organizationid as external_organizationid',
                                    'company_agency.domain',
                                    'company_agency.subdomain as orisubdomain',
                                    DB::raw('REPLACE(company_agency.subdomain,".' . $confAppDomain . '","") as subdomain'),
                                    DB::raw('CASE WHEN open_api_users.client_id IS NOT NULL AND open_api_users.secret_key IS NOT NULL THEN true ELSE false END as is_user_generate_api')
                                )
                                ->join('companies as company_agency', 'company_agency.id', '=', 'agency.company_id')
                                ->join('users as client', 'client.company_parent', '=', 'agency.company_id')
                                ->join('companies as company_client', 'company_client.id', '=', 'client.company_id')
                                ->leftJoin('leadspeek_users', 'leadspeek_users.user_id', '=', 'client.id')
                                ->leftJoin('open_api_users', 'open_api_users.company_id', '=', 'agency.company_id')
                                ->leftJoin('clean_id_file', 'clean_id_file.user_id', '=', 'agency.id')
                                ->where('agency.company_parent', $CompanyID)
                                ->where('agency.active', 'T')
                                ->where('agency.user_type', 'userdownline')
                                ->where(function ($query) use ($searchKey, $salt) {
                                    $query->where(DB::raw("CONVERT(AES_DECRYPT(FROM_bASE64(company_client.company_name), '$salt') USING utf8mb4)"), 'like', "%$searchKey%")
                                          ->orWhere(DB::raw("CONVERT(AES_DECRYPT(FROM_bASE64(client.email), '$salt') USING utf8mb4)"), 'like', "%$searchKey%")
                                          ->orWhere(DB::raw("CONVERT(AES_DECRYPT(FROM_bASE64(client.name), '$salt') USING utf8mb4)"), 'like', "%$searchKey%")
                                          ->orWhere('leadspeek_users.leadspeek_api_id', 'like', "%$searchKey%")
                                          ->orWhere(DB::raw("CONVERT(AES_DECRYPT(FROM_bASE64(leadspeek_users.campaign_name), '$salt') USING utf8mb4)"), 'like', "%$searchKey%")
                                          ->orWhere('clean_id_file.clean_api_id', 'like', "%$searchKey%");
                                })
                                ->groupBy('agency.id')
                                ->orderBy('agency.sort')
                                ->orderBy(DB::raw("CONVERT(AES_DECRYPT(FROM_bASE64(company_agency.company_name), '$salt') USING utf8mb4)"))
                                ->get();
                /* NEW */
                
                /* OLD */
                // $downline = User::select(
                //                     'users.*',
                //                     DB::raw('"" as sales'),
                //                     DB::raw('"" as salesrep'),
                //                     DB::raw('"" as accountexecutive'),
                //                     'companies.manual_bill',
                //                     'companies.company_name',
                //                     'companies.simplifi_organizationid as external_organizationid',
                //                     'companies.domain',
                //                     'companies.subdomain as orisubdomain',
                //                     DB::raw('REPLACE(companies.subdomain,".' . $confAppDomain . '","") as subdomain')
                //                 )
                //                 ->join('companies','companies.id','=','users.company_id')
                //                 ->where('company_parent','=',$CompanyID)
                //                 ->where('active','T')
                //                 ->where('user_type','=','userdownline')
                //                 ->with(['children' => function ($query) use ($searchKey, $salt) {
                //                     if ($searchKey !== "") {
                //                         $query->where(function($subQuery) use ($searchKey, $salt) {
                //                             $subQuery->where(DB::raw("CONVERT(AES_DECRYPT(FROM_bASE64(companies.company_name), '" . $salt . "') USING utf8mb4)"),'like','%' . $searchKey . '%')
                //                                      ->orWhere(DB::raw("CONVERT(AES_DECRYPT(FROM_bASE64(users.email), '" . $salt . "') USING utf8mb4)"),'like','%' . $searchKey . '%')
                //                                      ->orWhere(DB::raw("CONVERT(AES_DECRYPT(FROM_bASE64(users.name), '" . $salt . "') USING utf8mb4)"),'like','%' . $searchKey . '%')
                //                                      ->orWhere('leadspeek_users.leadspeek_api_id','like','%' . $searchKey . '%')
                //                                      ->orWhere(DB::raw("CONVERT(AES_DECRYPT(FROM_bASE64(leadspeek_users.campaign_name), '" . $salt . "') USING utf8mb4)"),'like','%' . $searchKey . '%');
                //                         });
                //                     }
                //                 }]);
                // $downline = $downline->orderBy('sort')->orderByEncrypted('companies.company_name')->get();              
                // if (count($downline) > 0) 
                // {
                //     $downline = $downline->filter(function ($user) {
                //         return $user->children->isNotEmpty();
                //     });
                // }
                /* OLD */
            }
            /** IF SEARCH ON AGENCY EMPTY RESULT, JIKA DARI AGENCY TIDAK DITEMUKAN CARI BEDASARKAN DATA CLIENT ATAU DATA CAMPAIGN ATAU DATA CLEAN ID */

            foreach($downline as $i => $dl) 
            {
                /** CHECK STRIPE CONNECTED ACCOUNT */
                $companyConnectStripe = CompanyStripe::select('status_acc','acc_connect_id','package_id')
                                                     ->where('company_id','=',$dl['company_id'])
                                                     ->get();

                $accountConnected = '';
                $package_id = '';

                if (count($companyConnectStripe) > 0) 
                {
                    $accountConnected = $companyConnectStripe[0]['status_acc'];
                    $package_id = ($companyConnectStripe[0]['package_id'] != '')?$companyConnectStripe[0]['package_id']:"";
                }
                /** CHECK STRIPE CONNECTED ACCOUNT */

                $dl['status_acc'] = $accountConnected;
                $dl['package_id'] = $package_id;

                // Check white labeling
                $whitelabelingByPackageplans = PackagePlan::select('whitelabelling')
                                                          ->where('package_id', $dl['package_id'])
                                                          ->where('company_root_id', $CompanyID)
                                                          ->first();
                $getIsWhitelabelingByCompany = Company::select('is_whitelabeling')
                                                      ->where('id', '=', $dl['company_id'])
                                                      ->first();
                
                $whitelabelling = 'F';
                if($whitelabelingByPackageplans)
                {
                    $whitelabelling = $whitelabelingByPackageplans->whitelabelling;
                } 
                else 
                {
                    $whitelabelling = 'F';
                }
            
                $is_whitelabeling = $getIsWhitelabelingByCompany->is_whitelabeling ? $getIsWhitelabelingByCompany->is_whitelabeling : $whitelabelling;
                $dl['is_whitelabeling'] = $is_whitelabeling;

                /** CHECK SALES  */
                $chksales = User::select('users.id','users.name','company_sales.sales_title')
                                ->join('company_sales','users.id','=','company_sales.sales_id')
                                ->where('company_sales.company_id','=',$dl['company_id'])
                                ->where('users.active','=','T')
                                ->get();

                $compsalesrepID = "";
                $compsalesrep = "";
                $compaccountexecutive = "";
                $compaccountexecutiveID = "";
                $compaccountref = "";
                $compaccountrefID = "";

                foreach($chksales as $sl) 
                {
                    /*$tmpsales = [
                        'name' => $sl['name'],
                        'title' => $sl['sales_title'],
                    ];*/
                    //$compsales = $compsales .  $sl['name'] . '-' . $sl['sales_title'] . '|';
                    //array_push($compsales,$tmpsales);
                    if ($sl['sales_title'] == "Sales Representative") 
                    {
                        $compsalesrepID = $sl['id'];
                        $compsalesrep = $sl['name'];
                    }
                    if ($sl['sales_title'] == "Account Executive") 
                    {
                        $compaccountexecutiveID = $sl['id'];
                        $compaccountexecutive = $sl['name'];
                    }

                    if ($sl['sales_title'] == "Account Referral") 
                    {
                        $compaccountrefID = $sl['id'];
                        $compaccountref = $sl['name'];
                    }
                }
                //$compsales = rtrim($compsales,"|");
                $dl['salesrepid'] = $compsalesrepID;
                $dl['salesrep'] = $compsalesrep;
                $dl['accountexecutiveid'] = $compaccountexecutiveID;
                $dl['accountexecutive'] = $compaccountexecutive;
                $dl['accountrefid'] = $compaccountrefID;
                $dl['accountref'] = $compaccountref;
                /** CHECK SALES */
                
                // ROOT PAYMENT TERM
                $root_payment_term = $this->getcompanysetting($dl['company_root_id'], 'rootpaymentterm');
                if ($root_payment_term && isset($root_payment_term->PaymentTerm)) 
                {
                    $dl['rootpaymentterm'] = $root_payment_term->PaymentTerm;
                }
                // ROOT PAYMENT TERM

                // SELECTED PAYMENT TERM
                $selected_payment_term = $this->getcompanysetting($dl['company_id'], 'agencypaymentterm');

                $root_term = $this->getcompanysetting($dl['company_root_id'], 'rootpaymentterm');
                if ($selected_payment_term) 
                {
                    $dl['selected_payment_term'] = $selected_payment_term->SelectedPaymentTerm;
                }
                else 
                {
                    // set value to true for agency that doesn't have agencypaymentterm
                    try 
                    {
                        foreach ($root_term->PaymentTerm as $term) 
                        {
                            $term->term = $term->value;
                            unset($term->label);
                            $term->status = true;
                        }
                        $dl['selected_payment_term'] = $root_term->PaymentTerm;
                    } 
                    catch (\Throwable $th) 
                    {
                        return response()->json(['selected_payment_term'=> $dl['selected_payment_term'], 'errmsg' => $th->getMessage()]);
                    }
                }
                // SELECTED PAYMENT TERM

                //ROOT MODULES FOR AGENCY-LIST
                $root_modules = [];
                $root_modules_setting = $this->getcompanysetting($dl['company_root_id'], 'rootcustomsidebarleadmenu');
                if (!empty($root_modules_setting)) 
                {
                    foreach ($root_modules_setting as $key => $value) 
                    {
                        $root_modules[] = [
                            'value' => $key,
                            'label' => $value->name,
                        ];
                    }
                }
                $dl['rootmodules'] = $root_modules;
                //ROOT MODULES FOR AGENCY-LIST

                // SELECTED MODULES FOR AGENCY-LIST
                $selected_modules = $this->getcompanysetting($dl['company_id'], 'agencysidebar');
                $root_module = $this->getcompanysetting($dl['company_root_id'], 'rootcustomsidebarleadmenu');
                if ($selected_modules) 
                {
                    // if there is agency sidebar setting, it will use it. but it will check to rootcustomsidebarleadmenu first to match the modules.
                    $agencysidebar = $selected_modules->SelectedModules;
                    $root_modules = array_keys((array)$root_module);
                    // Ambil tipe yang ada di selected_modules
                    $existing_terms = [];
                    foreach ($agencysidebar as $module) 
                    {
                        $existing_terms[] = $module->type;
                    }
                    // Cek setiap item di root_term
                    foreach ($root_modules as $mod) 
                    {
                        // Jika tipe dari root_term belum ada di selected_modules
                        if (!in_array($mod, $existing_terms)) 
                        {
                            $agencysidebar[] = (object)[
                                'type' => $mod,
                                'status' => ($mod == 'predict') ? false : true,
                            ];
                        }
                    }
                    $dl['selected_modules'] = $agencysidebar;
                    // $dl['selected_modules'] = $selected_modules->SelectedModules;
                }
                else 
                {
                    $exist_setting = $this->getcompanysetting($dl['company_root_id'], 'rootexistagencymoduleselect');
                    if (!empty($exist_setting) && isset($exist_setting->SelectedModules)) 
                    {
                        $dl['selected_modules'] = $exist_setting->SelectedModules;
                    }
                    else 
                    {
                        $agencysidebar = [];
                        foreach ($root_modules_setting as $key => $value) 
                        {
                            $agencysidebar[] = [
                                'type' => $key,
                                'status' => ($key == 'predict') ? false : true,
                            ];
                        }
                        $dl['selected_modules'] = $agencysidebar;
                    }
                }
                // SELECTED MODULES FOR AGENCY-LIST

                // EXCLUDE MINIMUM SPEND
                $downline[$i]['exclude_minimum_spend'] = !empty($downline[$i]['exclude_minimum_spend']) ? true : false;
                // EXCLUDE MINIMUM SPEND

                // CHECK USER GENERATE API
                $dl['is_user_generate_api'] = (bool) $dl['is_user_generate_api'];
                // CHECK USER GENERATE API

                // GET AGENCY AGREEMENT FILES 
                $agreement_file = CompanyAgreementFile::where('company_id', $dl['company_id'])->first();
                // if (!empty($agreement_file)) {
                if (false) {
                    $base_url = env('APP_URL');
                    $download_url = $base_url . '/download-agreement?agreement_id=' . urlencode($agreement_file->uuid);
                    $downline[$i]['company_agreement_file'] = $download_url;
                }else {
                    $user_agree = UserLog::where('user_id', $dl['id'])
                                    ->where('target_user_id', $dl['id'])
                                    ->where('action', 'Service and Billing Agreement')
                                    ->orderBy('created_at', 'desc')
                                    ->exists();
                    if ($user_agree) {
                        $downline[$i]['company_agreement_file'] = 'download';
                    }else {
                        $downline[$i]['company_agreement_file'] = null;
                    }
                }
                // GET AGENCY AGREEMENT FILES 

                // change if plan_minspend_id has not been set force to system
                if(is_null($downline[$i]['plan_minspend_id'])) {
                    $downline[$i]['plan_minspend_id'] = 'system';
                }
                // change if plan_minspend_id has not been set force to system
            }

            if ($childrensearch) 
            {
                $downline = $this->manual_pagination($downline,$Page,$PerPage);
                $downline['childrensearch'] = true;
                return $downline;
            }

            return $downline;

        }else if($UserType == 'user') {
            $user = User::where('company_id','=',$CompanyID)->where('active','T')->whereRaw("user_type IN ('user','userdownline')")->where('isAdmin','=','T');

            if(isset($Exclude['exclude_id']) && !empty($Exclude)){
                $user->whereNotIn('id', [$Exclude]);
            }

            if (trim($searchKey) != '') {
                $salt = substr(hash('sha256', env('APP_KEY')), 0, 16);
                
                $user->where(function($query) use ($searchKey,$salt) {
                    $query->orWhere(DB::raw("CONVERT(AES_DECRYPT(FROM_bASE64(name), '" . $salt . "') USING utf8mb4)"),'like','%' . $searchKey . '%')
                    ->orWhere(DB::raw("CONVERT(AES_DECRYPT(FROM_bASE64(email), '" . $salt . "') USING utf8mb4)"),'like','%' . $searchKey . '%')
                    ->orWhere(DB::raw("CONVERT(AES_DECRYPT(FROM_bASE64(phonenum), '" . $salt . "') USING utf8mb4)"),'like','%' . $searchKey . '%')
                    ->orWhere(DB::raw("DATE_FORMAT(created_at,'%m-%d-%Y')"),'like','%' . $searchKey . '%');
                });
            }

            if (trim($order) != '') {
                if (trim($order) == 'descending') {
                    $order = "DESC";
                }else{
                    $order = "ASC";
                }
            }

            if (trim($sortby) != '') {
                if (trim($sortby) == "full_name") {
                    $user = $user->orderByEncrypted('name',$order);
                }else if (trim($sortby) == "email") {
                    $user = $user->orderByEncrypted('email',$order);
                }else if (trim($sortby) == "phone") {
                    $user = $user->orderByEncrypted('phonenum',$order);
                }else if (trim($sortby) == "created_at") {
                    $user = $user->orderBy(DB::raw('CAST(created_at AS DATETIME)'),$order);
                }

                if ($Page == '') { 
                    $user = $user->orderByEncrypted('name')->get();
                }else{
                    $user = $user->paginate($PerPage, ['*'], 'page', $Page);
                }
            }else{
                if ($Page == '') { 
                    $user = $user->orderByEncrypted('name')->get();
                }else{
                    $user = $user->orderByEncrypted('name')->paginate($PerPage, ['*'], 'page', $Page);
                }
            }
            
            return $user;
        }else if($UserType == 'sales') {
            $user = User::where('active','T')->whereRaw("user_type IN ('sales')")->where('isAdmin','=','T')->where('company_id','=',$CompanyID);

            if (trim($searchKey) != '') {
                $salt = substr(hash('sha256', env('APP_KEY')), 0, 16);
                
                $user->where(function($query) use ($searchKey,$salt) {
                    $query->orWhere(DB::raw("CONVERT(AES_DECRYPT(FROM_bASE64(name), '" . $salt . "') USING utf8mb4)"),'like','%' . $searchKey . '%')
                    ->orWhere(DB::raw("CONVERT(AES_DECRYPT(FROM_bASE64(email), '" . $salt . "') USING utf8mb4)"),'like','%' . $searchKey . '%')
                    ->orWhere(DB::raw("CONVERT(AES_DECRYPT(FROM_bASE64(phonenum), '" . $salt . "') USING utf8mb4)"),'like','%' . $searchKey . '%')
                    ->orWhere(DB::raw("DATE_FORMAT(created_at,'%m-%d-%Y')"),'like','%' . $searchKey . '%');
                });
            }

            if (trim($order) != '') {
                if (trim($order) == 'descending') {
                    $order = "DESC";
                }else{
                    $order = "ASC";
                }
            }

            if (trim($sortby) != '') {
                if (trim($sortby) == "full_name") {
                    $user = $user->orderByEncrypted('name',$order);
                }else if (trim($sortby) == "email") {
                    $user = $user->orderByEncrypted('email',$order);
                }else if (trim($sortby) == "phone") {
                    $user = $user->orderByEncrypted('phonenum',$order);
                }else if (trim($sortby) == "created_at") {
                    $user = $user->orderBy(DB::raw('CAST(created_at AS DATETIME)'),$order);
                }

                if ($Page == '') { 
                    $user = $user->orderByEncrypted('name')->get();
                }else{
                    $user = $user->paginate($PerPage, ['*'], 'page', $Page);
                }
            }else{
                if ($Page == '') { 
                    $user = $user->orderByEncrypted('name')->get();
                }else{
                    $user = $user->orderByEncrypted('name')->paginate($PerPage, ['*'], 'page', $Page);
                }
            }
            
            return $user;
            
        }
    }

    public function sorting(Request $request) {
        $parentCompany = $request->ParentCompanyID;
        $tmp = $request->dataSort;
        parse_str($tmp,$rowList);
        
        $dataSort = $rowList['rowList'];
       
        foreach ($dataSort as $id => $parentID) {
            if (!isset($position[$parentID])) {
				$position[$parentID] = 0;
			}

            $companyParentID = $parentID;
            if(!isset($parentID) || $parentID === "null") {
                $companyParentID = $parentCompany;
                //echo "IN : " . $companyParentID . '<br>';
               
            }

           //echo $id . ' | ' . $parentID . ' : ' . $position[$parentID] . '<br>';

            /** UPDATE USER SORT */
            $user = User::where("company_id","=",$id)
                            ->where("user_type","=","userdownline")
                            ->update(["company_parent" => $companyParentID,"sort" => $position[$parentID]]);
            //$user->company_parent = $parentID;
            //$user->sort = $position[$parentID];
            //$user->save();
            /** UPDATE USER SORT */

            $count = $position[$parentID];
			$position[$parentID] = $count + 1;

        }
        
    }

    public function updatecustomdomain(Request $request) {
        $companyID = (isset($request->companyID) && $request->companyID != '')?$request->companyID:'';
        $DownlineDomain = (isset($request->DownlineDomain) && $request->DownlineDomain != '')?$request->DownlineDomain:'';
        $whitelabelling = ($request->whitelabelling === true)?'T':'F';

        $company = Company::find($companyID);

        $DownlineDomain = str_replace('http://','',$DownlineDomain);
        $DownlineDomain = trim(str_replace('https://','',$DownlineDomain));
        $DownlineDomain = strtolower($DownlineDomain);

        $msg = '';

        if ($DownlineDomain != "" && $whitelabelling == 'T') {
            $msg = 'White Labelling has been enabled.';

            /** GET CURRENT DOMAIN BEFORE UPDATE **/
                $getcurrdomain = Company::select('domain','status_domain','status_domain_error')->where('id','=',$request->companyID)->get();
                $currdomain = trim((isset($getcurrdomain[0]['domain']) && $getcurrdomain[0]['domain'] != '')?$getcurrdomain[0]['domain']:'');
                $statusdomain = trim((isset($getcurrdomain[0]['status_domain']) && $getcurrdomain[0]['status_domain'] != '')?$getcurrdomain[0]['status_domain']:'');
                $statusdomainerror = trim((isset($getcurrdomain[0]['status_domain_error']) && $getcurrdomain[0]['status_domain_error'] != '')?$getcurrdomain[0]['status_domain_error']:'');
                
                $currdomain = strtolower($currdomain);

                if ($currdomain != $DownlineDomain) {
                    if ($this->check_subordomain_exist($DownlineDomain)) {
                        return response()->json(array('result'=>'failed','message'=>'Domain name already exist'));
                    }else{
                        $statusdomain = "";
                        $statusdomainerror = "";
                    }

                    /** PUT CURRENT DOMAIN TO TABLE DOMAIN THAT NEED TO BE REMOVE */
                    if ($currdomain != '') {
                        date_default_timezone_set('America/Chicago');

                        $datenow = date('Y-m-d');
                        $date5more = date('Y-m-d',strtotime('+5 day',strtotime($datenow)));
                        
                        $chkdomainremoved = DomainRemove::select('id')->where('domain','=',$currdomain)->get();
                        if (count($chkdomainremoved) == 0 && $currdomain != '') {
                            $domainremoved = DomainRemove::create([
                                'company_id' => $companyID,
                                'domain' => $currdomain,
                                'date_removed' => $date5more,
                            ]);
                        }
                    }
                    /** PUT CURRENT DOMAIN TO TABLE DOMAIN THAT NEED TO BE REMOVE */
                }

                $chkdomainremoved = DomainRemove::select('id')->where('domain','=',$DownlineDomain)->get();
                if (count($chkdomainremoved) > 0) {
                    $removedomain = DomainRemove::where('domain','=',$DownlineDomain)->delete();
                }

                $company->domain = $DownlineDomain;
                $company->status_domain = $statusdomain;
                $company->status_domain_error = $statusdomainerror;
                
                if ($currdomain == $DownlineDomain && $statusdomain == 'action_check_manually') {
                    $company->status_domain = '';
                    $company->status_domain_error = '';
                }
                
                $company->whitelabelling = 'T';
                $company->save();

            /** GET CURRENT DOMAIN BEFORE UPDATE **/
        }else if($whitelabelling == 'F'){
            $msg = 'White Labelling has been disabled.';

            $getcurrdomain = Company::select('domain','status_domain','status_domain_error')->where('id','=',$request->companyID)->get();
            $currdomain = trim((isset($getcurrdomain[0]['domain']) && $getcurrdomain[0]['domain'] != '')?$getcurrdomain[0]['domain']:'');
            
            $currdomain = strtolower($currdomain);
            
            $company->whitelabelling = 'F';
            $company->save();

            $DownlineDomain = $currdomain;

            /** PUT CURRENT DOMAIN TO TABLE DOMAIN THAT NEED TO BE REMOVE */
            if ($currdomain != '') {
                date_default_timezone_set('America/Chicago');

                $datenow = date('Y-m-d');
                $date5more = date('Y-m-d',strtotime('+5 day',strtotime($datenow)));
                
                $chkdomainremoved = DomainRemove::select('id')->where('domain','=',$currdomain)->get();
                if (count($chkdomainremoved) == 0 && $currdomain != '') {
                    $domainremoved = DomainRemove::create([
                        'company_id' => $companyID,
                        'domain' => $currdomain,
                        'date_removed' => $date5more,
                    ]);
                }
            }
            /** PUT CURRENT DOMAIN TO TABLE DOMAIN THAT NEED TO BE REMOVE */
        }

       
        return response()->json(array('result'=>'success','message'=>$msg,'activated'=>$whitelabelling,'domain'=>$DownlineDomain));
        
    }

    public function update(Request $request) {

            $confAppSysID = config('services.application.systemid');
            $defaultAdmin = 'F';
            $customercare = 'F';
            $adminGetNotification = "F";
            $disabledrecieveemail = (isset($request->disabledreceivedemail))?$request->disabledreceivedemail:'F';
            $enabledDeletedAccountClient = (isset($request->enabledDeletedAccountClient))?$request->enabledDeletedAccountClient:'F';
            $disabledaddcampaign = (isset($request->disabledaddcampaign))?$request->disabledaddcampaign:'F';
            $editorspreadsheet = (isset($request->editorspreadsheet))?$request->editorspreadsheet:'F';
            $enablephone = (isset($request->enablephonenumber))?$request->enablephonenumber:'F';
            $is_apimode = (isset($request->ApiMode))?$request->ApiMode:'F';
            $planMinSpendId = (isset($request->planMinSpendId))?$request->planMinSpendId:"";
            $inOpenApi = (isset($request->inOpenApi))?$request->inOpenApi:"";

            if(isset($request->defaultAdmin) && $request->defaultAdmin == 'T') {
                $defaultAdmin = 'T';
            }

            if(isset($request->customercare) && $request->customercare == 'T') {
                $customercare = 'T';
               
                /** UPDATE CUSTOMER CARE ONLY CAN BE ONLY ONE */
                $updcustcare = User::where('company_id','=',$request->companyID)
                                    ->where(function ($query) {
                                        $query->where('user_type','=','user')
                                        ->orWhere('user_type','=','userdownline');
                                    })
                                    ->update(['customercare' => 'F']);
                /** UPDATE CUSTOMER CARE ONLY CAN BE ONLY ONE */
            }

            if(isset($request->adminGetNotification) && $request->adminGetNotification == 'T') {
                $adminGetNotification = 'T';
            }

            $action = (isset($request->action) && $request->action != '')?$request->action:'';

            $DownlineDomain = (isset($request->DownlineDomain) && $request->DownlineDomain != '')?$request->DownlineDomain:'';
            $DownlineSubDomain = (isset($request->DownlineSubDomain) && $request->DownlineSubDomain != '')?$request->DownlineSubDomain:'';
            $statusdomain = "";
            $statusdomainerror = "";

            $idsys = (isset($request->idsys))?$request->idsys:'';

            /** GET ROOT SYS CONF */
            $confAppDomain =  config('services.application.domain');
            if ($idsys != "") {
                $conf = $this->getCompanyRootInfo($idsys);
                $confAppDomain = $conf['domain'];
            }
            /** GET ROOT SYS CONF */

            $getcurrdomain = Company::select('domain','status_domain','status_domain_error','subdomain')->where('id','=',$request->companyID)->get();
            $currdomain = (isset($getcurrdomain[0]['domain']))?trim($getcurrdomain[0]['domain']):'';
            $statusdomain = (isset($getcurrdomain[0]['status_domain']))?trim($getcurrdomain[0]['status_domain']):'';
            $statusdomainerror = (isset($getcurrdomain[0]['status_domain_error']))?trim($getcurrdomain[0]['status_domain_error']):'';
            $currsubdomain = (isset($getcurrdomain[0]['subdomain']))?trim($getcurrdomain[0]['subdomain']):'';

            if ($DownlineDomain != "" && isset($request->DownlineDomain)) {
                $DownlineDomain = str_replace('http://','',$DownlineDomain);
                $DownlineDomain = trim(str_replace('https://','',$DownlineDomain));
                /** GET CURRENT DOMAIN BEFORE UPDATE **/                    
                    if ($currdomain != $DownlineDomain) {
                        if ($this->check_subordomain_exist($DownlineDomain)) {
                            return response()->json(array('result'=>'failed','message'=>'Domain name already exist'));
                        }else{
                            $statusdomain = "";
                            $statusdomainerror = "";
                        }
                    }
                /** GET CURRENT DOMAIN BEFORE UPDATE **/
            }

            if ($DownlineSubDomain != "" && isset($request->DownlineSubDomain)) {
                $DownlineSubDomain = str_replace('http://','',$DownlineSubDomain);
                $DownlineSubDomain = trim(str_replace('https://','',$DownlineSubDomain));
                $DownlineSubDomain = $DownlineSubDomain . '.' . $confAppDomain;

                if($currsubdomain != $DownlineSubDomain){
                    if ($this->check_subordomain_exist($DownlineSubDomain)) {
                        return response()->json(array('result'=>'failed','message'=>'This subdomain already exists'));
                    }
                }
                
            }

            $DownlineOrganizationID = (isset($request->DownlineOrganizationID) && $request->DownlineOrganizationID != '')?$request->DownlineOrganizationID:'';

            $NewcompanyID = "";

            $company = Company::find($request->companyID);
            $prevCompany = $company ? $company->toArray() : [];

            if ($company) {
                if (isset($request->ClientCompanyName)) {
                    $company->company_name = $request->ClientCompanyName;
                }
                if (trim($DownlineDomain) != ''&& isset($request->DownlineDomain) ) {
                    $company->domain = $DownlineDomain;
                }
                if (trim($DownlineSubDomain) != ''  && isset($request->DownlineSubDomain)) {
                    $company->subdomain = $DownlineSubDomain;
                }

                if (trim($DownlineOrganizationID != '') && isset($request->DownlineOrganizationID)) {
                    $company->simplifi_organizationid = $DownlineOrganizationID;
                }

                if (trim($DownlineDomain) != ''&& isset($request->DownlineDomain) ) {
                    $company->status_domain = $statusdomain;
                    $company->status_domain_error = $statusdomainerror;
                }
                if(isset($request->ClientWhiteLabeling)) {
                    $company->is_whitelabeling = $request->ClientWhiteLabeling;
                }
                $company->save();
            }else{
                $newCompany = Company::create([
                    'company_name' => $request->ClientCompanyName,
                    'company_address' => '',
                    'company_city' => '',
                    'company_zip' => '',
                    'company_country_code' => '',
                    'company_state_code' => '',
                    'company_state_name' => '',
                    'phone_country_code' => '',
                    'phone_country_calling_code' => '',
                    'phone' => '',
                    'email' => '',
                    'logo' => '',
                    'sidebar_bgcolor' => '',
                    'template_bgcolor' => '',
                    'box_bgcolor' => '',
                    'font_theme' => '',
                    'login_image' => '',
                    'client_register_image' => '',
                    'agency_register_image' => '',
                    'subdomain' => '',
                    'approved' => 'T',
                    'user_create_id' => $request->ClientID,
                    
                ]);
    
                $NewcompanyID = $newCompany->id;
            }

            // Admin Permissions Only Root EMM
            $permission_active = (isset($request->permission_active))?$request->permission_active:null;
            $user_permissions = (isset($request->user_permissions))?$request->user_permissions:null;

            if($user_permissions){
                if (!is_array($user_permissions) && !is_object($user_permissions)) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'user permissions should be an object or an array.'
                    ], 400);
                }
                $allowed_keys = ['external_sf', 'report_analytics'];
        
                foreach ($user_permissions as $key => $value) {
                    if (!in_array($key, $allowed_keys)) {
                        return response()->json([
                            'status' => 'error',
                            'message' => "The key '$key' is not allowed."
                        ], 400);
                    }
            
                    if (!is_bool($value)) {
                        return response()->json([
                            'status' => 'error',
                            'message' => "The value for '$key' must be a boolean."
                        ], 400);
                    }
                }

                $user_permissions = json_encode($user_permissions);
            }
            // Admin Permissions Only Root EMM

            $user = User::find($request->ClientID);
            $prevUser = $user ? $user->toArray() : [];
            
            /** CHECK IF EMAIL ALREADY EXIST */
            $chkusrname = strtolower($request->ClientEmail);
            if($user->email != $request->ClientEmail){
                $chkEmailExist = null;
                $ownedcompanyid = null;
                if ($user->user_type == 'client') {
                    $companyRootID = $user->company_root_id;
                    $ownedcompanyid = $user->company_parent;
                    $chkEmailExist = User::where('email',Encrypter::encrypt($chkusrname))
                                         ->where('active','T')
                                         ->where('id','<>',$user->id)
                                         ->where(function ($query) use ($ownedcompanyid, $companyRootID) {
                                            $query->where(function ($query) use ($ownedcompanyid) { // check email di platform/domain itu sendiri, sudah dipakai oleh agency, admin agency, client belum
                                                $query->whereIn('user_type',['userdownline','user','client'])
                                                      ->where(function ($query) use ($ownedcompanyid) {
                                                            $query->where('company_id',$ownedcompanyid)
                                                                  ->orWhere('company_parent',$ownedcompanyid);
                                                      });
                                            })->orWhere(function ($query) use ($companyRootID) { // check email di root, admin root, sales sudah dipakai atau belum
                                                $query->whereIn('user_type',['userdownline','user','sales'])
                                                      ->where('company_id',$companyRootID);
                                            });
                                         })
                                         ->first();
                } else {
                    $ownedcompanyid = $user->company_id;
                    $chkEmailExist = User::where('company_root_id',$idsys)
                                         ->where('email',Encrypter::encrypt($chkusrname))
                                         ->where('active','T')
                                         ->where('id', '!=', $user->id)
                                         ->orderByRaw( // order by priority company_id, company_parent, id
                                            "
                                                CASE 
                                                    WHEN company_id = ? THEN 0 
                                                    WHEN company_parent = ? THEN 1 
                                                    ELSE 2 
                                                END, id ASC
                                            ", 
                                            [$ownedcompanyid, $ownedcompanyid]
                                         )
                                         ->first();
                }

                if ($chkEmailExist) {
                    $messageError = "This email address is already registered on another platform. Please use a different email address. Thank you!";
                    $userTypeExists = $chkEmailExist->user_type ?? null;
                    $companyIDExists = ($userTypeExists == 'client') ?
                                       ($chkEmailExist->company_parent ?? null) :
                                       ($chkEmailExist->company_id ?? null) ;
                    $companyIDRoot = User::where('company_parent',null)->pluck('company_id')->toArray();
                    $suffix = "";
                    // info(['userTypeExists' => $userTypeExists, 'companyIDExists' => $companyIDExists, 'ownedcompanyid' => $ownedcompanyid]);
                    if (in_array($companyIDExists, $companyIDRoot)) { // jika di platform root
                        if ($companyIDExists != $ownedcompanyid) { // jika yang login tidak berasal dari root
                            $suffix = ['userdownline' => '(1)', 'user' => '(2)', 'sales' => '(3)'][$userTypeExists] ?? ''; // 1 = root , 2 = admin root , 3 = sales
                        } elseif ($companyIDExists == $ownedcompanyid) { // jika yang login berasal dari root
                            $roleLabels = ['userdownline' => 'as a root', 'user' => 'as a admin root', 'client' => 'as a client', 'sales' => 'as a sales'][$userTypeExists] ?? '';
                            $messageError = "This email address is already registered on your platform $roleLabels. Please use a different email address. Thank you!";
                        }
                    } elseif ($companyIDExists != $ownedcompanyid) { // jika beda platform agency
                        $suffix = ['userdownline' => '(4)', 'user' => '(5)', 'client' => '(6)'][$userTypeExists] ?? ''; // 4 = agency , 5 = admin agency , 6 = client 
                    } elseif ($companyIDExists == $ownedcompanyid) { // jika sama di dalam platform
                        $roleLabels = ['userdownline' => 'as a agency', 'user' => 'as a admin agency', 'client' => 'as a client', 'sales' => 'as a sales'][$userTypeExists] ?? '';
                        $messageError = "This email address is already registered on your platform $roleLabels. Please use a different email address. Thank you!";
                    }
                    $messageError .= " $suffix";
                    return response()->json(array('result'=>'error','message'=>$messageError,'error'=>''),422);
                }
            }
            /** CHECK IF EMAIL ALREADY EXIST */
            
            $user->name = $request->ClientFullName;
            $user->email = strtolower($request->ClientEmail);
            $user->phonenum = $request->ClientPhone;
            $user->phone_country_code = $request->ClientPhoneCountryCode;
            $user->phone_country_calling_code = $request->ClientPhoneCountryCallingCode;
            $user->defaultadmin = $defaultAdmin;
            $user->customercare = $customercare;
            $user->admin_get_notification = $adminGetNotification;
            $user->disabled_receive_email = $disabledrecieveemail;
            $user->enabled_client_deleted_account = $enabledDeletedAccountClient;
            $user->disable_client_add_campaign = $disabledaddcampaign;
            $user->editor_spreadsheet = $editorspreadsheet;
            $user->enabled_phone_number = $enablephone;
            $user->permission_active = $permission_active;
            $user->user_permissions = $user_permissions;
            
            if($request->has('ApiMode') && $user->user_type == 'userdownline' && $user->company_root_id == $confAppSysID) {
                /* UPDATE SERVICE AGREEMENT AND MONTH DEVELOPER MINSPEND WHEN API MODE F */
                if($is_apimode == 'F') {
                    $user->is_marketing_services_agreement_developer = 'F';
                    $user->month_developer_minspend = 1;
                }
                else if($is_apimode == 'T' && $user->api_mode == 'F') {
                    $user->is_marketing_services_agreement_developer = 'F';
                    $user->month_developer_minspend = 1;
                }
                $user->api_mode = $is_apimode;
                /* UPDATE SERVICE AGREEMENT AND MONTH DEVELOPER MINSPEND WHEN API MODE F */

                /* ADD GHL TAG ON AGENCY's CONTACT */
                if (!empty($user) && !empty($user->email) && !empty($user->company_root_id)) {
                    $company_root_id = $user->company_root_id;
                    $root_ghl_setting = $this->getcompanysetting($company_root_id,'rootghlapikey');

                    if (isset($root_ghl_setting->api_key) && !empty($root_ghl_setting->api_key) && $root_ghl_setting->api_key != '') {
                        $api_key = $root_ghl_setting->api_key;
                        try {
                            $param = [
                                'name' => $user->name,
                                'email' => $user->email,
                                'phone' => $user->phonenum,
                            ];
                            if ($is_apimode == 'T') {
                                $add_tag = $this->ghl_addContactTagsAgency($api_key,$param,['api-developer-enabled']);
                            } elseif ($is_apimode == 'F') {
                                $add_tag = $this->ghl_addContactTagsAgency($api_key,$param,[]);
                                $remove_tag = $this->ghl_removeContactTags($api_key,$user->email,['api-developer-enabled']);
                            }
                        } catch (\GuzzleHttp\Exception\BadResponseException $e) {
                            $errorBody = (string) $e->getResponse()->getBody();
                            $decodedError = json_decode($errorBody, true);
                            Log::info([
                                'function' => 'update',
                                'section' => 'api-developer-enabled',
                                'success' => false,
                                'error' => $decodedError['msg'],
                            ]);
                        } catch (\Exception $e) {
                            Log::info([
                                'function' => 'update',
                                'section' => 'api-developer-enabled',
                                'success' => false,
                                'error' => $e->getMessage(),
                            ]);
                        }
                    }
                }
                /* ADD GHL TAG ON AGENCY's CONTACT */
            }

            /* FORCE TO F IF OTHER THAN EMM */
            if($user->user_type == 'userdownline' && $user->company_root_id != $confAppSysID) {
                $user->api_mode = 'F';
            }
            /* FORCE TO F IF OTHER THAN EMM */

            /* PLAN MINIMUM SPEND ID */
            if($request->has('planMinSpendId') && $user->user_type == 'userdownline' && $user->company_root_id == $confAppSysID) {
                $user->plan_minspend_id = $planMinSpendId;
            }
            /* PLAN MINIMUM SPEND ID */

            if ($NewcompanyID != '') {
                $user->company_id = $NewcompanyID;
            }
            
            if (isset($request->ClientRole) && $request->ClientRole != '') {
                $user->role_id = $request->ClientRole;
            }

            /** SITE DOMAIN NAME */
            if(isset($request->ClientDomain) && $request->ClientDomain != '') {
                $userdetail = User::select('site_id')
                                ->where('id','=',$request->ClientID)
                                ->get();
                if(count($userdetail) > 0) {
                    $newdomain = str_replace('http://','',$request->ClientDomain);
                    $newdomain = trim(str_replace('https://','',$newdomain));

                    if($userdetail[0]['site_id'] == '' || $userdetail[0]['site_id'] == null) {
                        /** CHECK FIRST IF DOMAIN EXIST */
                        $siteExist = Site::select('id')
                                        ->where('domain','=',$newdomain)
                                        ->get();
                        if (count($siteExist) == 0) {                
                            $newSite = Site::create([
                                'company_name' => $request->ClientCompanyName,
                                'domain' => $newdomain,
                            ]);

                            $newsiteID = $newSite->id;
                            $user->site_id = $newsiteID;
                        }else{
                            $siteupdate = Site::find($siteExist[0]['id']);
                            $siteupdate->domain = $newdomain;
                            $siteupdate->company_name = $request->ClientCompanyName;
                            $siteupdate->save();

                            $user->site_id = $siteExist[0]['id'];
                        }
                    }else{
                        $siteupdate = Site::find($userdetail[0]['site_id']);
                        $siteupdate->domain = $newdomain;
                        $siteupdate->save();
                    }
                }
            }
            /** SITE DOMAIN NAME */
            
            if(isset($request->ClientPass) && $request->ClientPass != '') {
                $user->password = Hash::make($request->ClientPass);
            }
            
            /** CHECK IF USER TYPE IS SALES AND REFCODE IS EMPTY */
            if ($user->user_type == 'sales' &&  trim($user->referralcode) == '') {
                $user->referralcode = $this->generateReferralCode('salesref' . $request->ClientID);
            }
            /** CHECK IF USER TYPE IS SALES AND REFCODE IS EMPTY */

            // SAVE PAYMENTTERMCONTROL
            $paymentterm_setting = CompanySetting::where('company_id', $request->companyID)
                    ->whereEncrypted('setting_name', 'agencypaymentterm')
                    ->first();

            $prev_paymentterm_setting = [];
            if ($paymentterm_setting) {
                $value = json_decode($paymentterm_setting->setting_value, true);
                $prev_paymentterm_setting = $value['SelectedPaymentTerm'] ?? [];
            }

                $paymentterm = [];
                // return response()->json(['data', $request->selectedterms]);
            if (!empty($request->selectedterms)) {
                $paymentterm = [
                    "SelectedPaymentTerm" => $request->selectedterms,
                ];
            }

            if ($paymentterm_setting && $request->selectedterms != []) {
            // Update data yang ada
                $paymentterm_setting->setting_value = json_encode($paymentterm);
                $paymentterm_setting->save();
            } else {
                if ($paymentterm != []) {
                    $createsetting = CompanySetting::create([
                    'company_id' => $request->companyID,
                    'setting_name' => 'agencypaymentterm',
                    'setting_value' => json_encode($paymentterm),
                    ]);
                }
            }

            if($user->user_type == 'userdownline') {
                $agencyCompany = Company::find($request->companyID);
                $paymentTermDefault = isset($agencyCompany->paymentterm_default) ? $agencyCompany->paymentterm_default : '';

                if(isset($paymentterm['SelectedPaymentTerm']) && !empty($paymentterm['SelectedPaymentTerm']) && !empty($paymentterm)) {
                    $isUpdatePaymentTerm = false;
                    $weeklyStatus = $monthlyStatus = $prepaidStatus = false;
                    
                    foreach($paymentterm['SelectedPaymentTerm'] as $item) {
                        $term = isset($item['term']) ? $item['term'] : '';
                        $status = isset($item['status']) ? $item['status'] : false;

                        if($term == 'Weekly') {
                            $weeklyStatus = $status;
                        } else if($term == 'Monthly') {
                            $monthlyStatus = $status;
                        } else if($term == 'Prepaid') {
                            $prepaidStatus = $status;
                        }
                        
                        if($paymentTermDefault == $item['term'] && $item['status'] == false) {
                            $isUpdatePaymentTerm = true;
                        }
                    }   
                    
                    if($isUpdatePaymentTerm == true) {
                        if($prepaidStatus == true) {
                            $agencyCompany->paymentterm_default = 'Prepaid';
                        } else if($weeklyStatus == true) {
                            $agencyCompany->paymentterm_default = 'Weekly';
                        } else if($monthlyStatus == true) {
                            $agencyCompany->paymentterm_default = 'Monthly';
                        }
                        $agencyCompany->save();
                    }
                }
            }
            // SAVE PAYMENTTERMCONTROL

            // SAVE MODULES
            $prev_modules_setting = [];
            $set_name = '';
            if ($user->user_type == 'userdownline' || $user->user_type == 'user') {
                $set_name = 'agencysidebar';
            }elseif ($user->user_type == 'client') {
                $set_name = 'clientsidebar';
            }
            if (!empty($set_name)) {
                $modules_setting = CompanySetting::where('company_id', $request->companyID)
                        ->whereEncrypted('setting_name', $set_name)
                        ->first();

                 if ($modules_setting) {
                    $value = json_decode($modules_setting->setting_value, true);
                    $prev_modules_setting = $value['SelectedModules'] ?? [];
                }

                $modules = [];
                if (!empty($request->selectedmodules) && isset($request->selectedmodules)) {
                    $selectedModules = $request->selectedmodules;

                    // Force predict to always be true for clients only
                    if ($set_name == 'clientsidebar') {
                        $predictExists = false;
                        foreach ($selectedModules as $key => $module) {
                            if (isset($module['type']) && $module['type'] === 'predict') {
                                $selectedModules[$key]['status'] = true; // Force status to true
                                $predictExists = true;
                                break;
                            }
                        }
                        
                        // If predict doesn't exist, add it with status true
                        if (!$predictExists) {
                            $selectedModules[] = [
                                'type' => 'predict',
                                'status' => true
                            ];
                        }
                    }

                    $modules = [
                        "SelectedModules" => $selectedModules,
                    ];
                }

                if (($modules_setting && $request->selectedmodules != [])) {
                    $modules_setting->setting_value = json_encode($modules);
                    $modules_setting->save();
                } else {
                    if ($modules != []) {
                        $createsetting = CompanySetting::create([
                        'company_id' => (isset($request->companyID) && $request->companyID != '') ? $request->companyID : $NewcompanyID,
                        'setting_name' => $set_name,
                        'setting_value' => json_encode($modules),
                        ]);
                    }
                }
            }
            // SAVE MODULES

            //SAVE LOGS
            $isRoot = isset($request->companyID, $request->idsys) 
                            && $request->companyID == $request->idsys;
            $login_id = null;
            if ($inOpenApi === true) {
                $company_parent = $user->company_parent ?? null;
                $login_id = User::where('company_id', $company_parent)->where('user_type', 'userdownline')->where('active', 'T')->value('id');
            } else {
                $login_id = auth()->user()->id;
            }

            $ipAddress = ($request->header('Clientip') !== null)?$request->header('Clientip'):$request->ip();
            $actionType = isset($request->action) ? $request->action : '';

            if($prevUser['user_type'] == 'userdownline'){
                $subdomainFull = $prevCompany['subdomain'] ?? null;
                $subdomainOnly = $subdomainFull ? explode('.', $subdomainFull)[0] : null;

                $previous = [
                    'companyID' => $request->companyID ?? "",
                    'ClientID' => $request->ClientID ?? "",
                    'ClientCompanyName' => $prevCompany['company_name'] ?? "",
                    'ClientFullName' => $prevUser['name'] ?? "",
                    'ClientEmail' => $prevUser['email'] ?? "",
                    'ClientPhone' => $prevUser['phonenum'] ?? "",
                    'ClientPhoneCountryCode' => $prevUser['phone_country_code'] ?? "",
                    'ClientPhoneCountryCallingCode' => $prevUser['phone_country_calling_code'] ?? "",
                    'ClientWhiteLabeling' => $prevCompany['is_whitelabeling'] ?? "",  
                    'ApiMode' => $prevUser['api_mode'] ?? "",
                    'DownlineDomain' => $prevCompany['domain'] ?? "",
                    'DownlineSubDomain' => $subdomainOnly,
                    'DownlineOrganizationID' => $prevCompany['simplifi_organizationid'] ?? "",
                    'idsys' => $request->idsys ?? "",
                    'selectedterms' => $prev_paymentterm_setting,
                    'selectedmodules' => $prev_modules_setting,
                ];

                $logs = [
                    'prev' => $previous,
                    'after' => $request->all(),
                ];

                if($actionType == 'administrator'){
                    $logs['after'] = $request->except(['permission_active', 'user_permissions', 'ClientPass']);

                    if (!empty($request->ClientPass)) {
                        $logs['password_changed'] = true;
                    }
                }

                $title = $isRoot ? 'Edit Root' : 'Edit Agency';

                $this->logUserAction($login_id, $title, json_encode($logs), $ipAddress, $prevUser['id']);

            } elseif($prevUser['user_type'] == 'user'){
                $previous = [
                    'companyID' => $request->companyID ?? "",
                    'ClientID' => $request->ClientID ?? "",
                    'ClientFullName' => $prevUser['name'] ?? "",
                    'ClientEmail' => $prevUser['email'] ?? "",
                    'ClientPhone' => $prevUser['phonenum'] ?? "",
                    'ClientPhoneCountryCode' => $prevUser['phone_country_code'] ?? "",
                    'ClientPhoneCountryCallingCode' => $prevUser['phone_country_calling_code'] ?? "",
                    'ClientRole' => $prevUser['role_id'] ?? "",
                    'idsys' => $request->idsys ?? "",
                    'defaultAdmin' => $prevUser['defaultadmin'] ?? "",
                    'customercare' => $prevUser['customercare'] ?? "",
                    'adminGetNotification' => $prevUser['admin_get_notification'] ?? "",
                    'permission_active' => $prevUser['permission_active'] ?? "",
                    'user_permissions' => $prevUser['user_permissions'] ?? "",
                ];

                $logs = [
                    'prev' => $previous,
                    'after' => $request->except(['ClientPass']),
                ];

                if (!empty($request->ClientPass)) {
                    $logs['password_changed'] = true;
                }
                
                $title = $isRoot ? 'Edit Admin Root' : 'Edit Admin Agency';
                $this->logUserAction($login_id, $title, json_encode($logs), $ipAddress, $prevUser['id']);
            } elseif($prevUser['user_type'] == 'client'){
                $previous = [
                    'companyID' => $request->companyID ?? "",
                    'ClientID' => $request->ClientID ?? "",
                    'ClientCompanyName' => $prevCompany['company_name'] ?? "",
                    'ClientFullName' => $prevUser['name'] ?? "",
                    'ClientEmail' => $prevUser['email'] ?? "",
                    'ClientPhone' => $prevUser['phonenum'] ?? "",
                    'ClientPhoneCountryCode' => $prevUser['phone_country_code'] ?? "",
                    'ClientPhoneCountryCallingCode' => $prevUser['phone_country_calling_code'] ?? "",
                    'ClientDomain' => $prevCompany['domain'] ?? "",
                    'idsys' => $request->idsys ?? "",
                    'selectedmodules' => $prev_modules_setting,
                    'disabledreceivedemail' => $prevUser['disabled_receive_email'] ?? "",
                    'disabledaddcampaign' => $prevUser['disable_client_add_campaign'] ?? "",
                    'enablephonenumber' => $prevUser['enabled_phone_number'] ?? "",
                    'editorspreadsheet' => $prevUser['editor_spreadsheet'] ?? "",
                ];

                $logs = [
                    'prev' => $previous,
                    'after' => $request->except(['ClientPass']),
                ];

                if (!empty($request->ClientPass)) {
                    $logs['password_changed'] = true;
                }
                
                $this->logUserAction($login_id, 'Edit Client', json_encode($logs), $ipAddress, $prevUser['id']);
            } elseif($prevUser['user_type'] == 'sales'){
                $previous = [
                    'companyID' => $request->companyID ?? "",
                    'ClientID' => $request->ClientID ?? "",
                    'ClientFullName' => $prevUser['name'] ?? "",
                    'ClientEmail' => $prevUser['email'] ?? "",
                    'ClientPhone' => $prevUser['phonenum'] ?? "",
                    'ClientPhoneCountryCode' => $prevUser['phone_country_code'] ?? "",
                    'ClientPhoneCountryCallingCode' => $prevUser['phone_country_calling_code'] ?? "",
                    'ClientRole' => $prevUser['role_id'] ?? "",
                    'idsys' => $request->idsys ?? "",
                    'defaultAdmin' => $prevUser['defaultadmin'] ?? "",
                    'customercare' => $prevUser['customercare'] ?? "",
                ];

                $logs = [
                    'prev' => $previous,
                    'after' => $request->except(['ClientPass']),
                ];

                if (!empty($request->ClientPass)) {
                    $logs['password_changed'] = true;
                }
                
                $this->logUserAction($login_id, 'Edit Sales', json_encode($logs), $ipAddress, $prevUser['id']);
            } else {
                $logs = [
                    'prev' => $prevUser,
                    'after' => $request->except(['ClientPass']),
                ];

                if (!empty($request->ClientPass)) {
                    $logs['password_changed'] = true;
                }

                $this->logUserAction($login_id, 'Edit User', json_encode($logs), $ipAddress, $prevUser['id']);
            }
            //SAVE LOGS

            $user->save();

    }

    public function testsmtp(Request $request) {
        $companyID = (isset($request->companyID) && $request->companyID != '')?$request->companyID:'';
        $emailsent = (isset($request->emailsent) && $request->emailsent != '')?$request->emailsent:'';

        if ($companyID != '' && $emailsent != '') {
            $getcompanysetting = CompanySetting::where('company_id',$companyID)
                                                ->where(function($query){
                                                    $query->whereEncrypted('setting_name','customsmtpmenu')
                                                          ->orWhereEncrypted('setting_name','rootsmtp');
                                                })
                                                ->get();
            // $getcompanysetting = CompanySetting::where('company_id',$companyID)->whereEncrypted('setting_name','customsmtpmenu')->get();
            $companysetting = "";
            $smtpusername = "";

            if (count($getcompanysetting) > 0) {
                $companysetting = json_decode($getcompanysetting[0]['setting_value']);
                
                $security = 'ssl';
                $tmpsearch = $this->searchInJSON($companysetting,'security');

                if ($tmpsearch !== null) {
                    $security = $companysetting->security;
                    if ($companysetting->security == 'none') {
                        $security = null;
                    }
                }
                
                try {
                    $transport = (new Swift_SmtpTransport(
                        $companysetting->host, 
                        $companysetting->port, 
                        $security))
                        ->setUsername($companysetting->username)
                        ->setPassword($companysetting->password);
        
            
                        $maildoll = new Swift_Mailer($transport);
                        Mail::setSwiftMailer($maildoll);
                        $smtpusername = $companysetting->username;

                        $from = [
                            'address' => $companysetting->username,
                            'name' => 'SMTP Test',
                            'replyto' => $companysetting->username,
                        ];
                        $details = [
                            'title' => "Test Email SMTP",
                            'content' => "Test Email template",
                        ];

                        Mail::to($emailsent)->send(new Gmail("Test Email SMTP",$from,$details,'emails.customemail',array()));

                        return response()->json(array('result'=>'success','msg'=>'SMTP configuration successfully sent the email. Please check the spam folder if you did not receive it in your inbox.'));
                }catch(Swift_TransportException $e) {
                    return response()->json(array('result'=>'failed','msg'=>'Oops! We couldn’t send your email at the moment. Please check your SMTP settings and try again later.', 'error' => $e->getMessage()));
                }
            }else{
                return response()->json(array('result'=>'failed','msg'=>'Before proceeding, kindly save your SMTP configuration. Thank you!'));
            }

        }else{
            return response()->json(array('result'=>'failed','msg'=>'no data'));
        }

    }

    public function resendInvitation(Request $request) {
        $clientID = (isset($request->ClientID) && $request->ClientID != '')?$request->ClientID:'';
        if ($clientID != '') {
            $user = User::find($clientID);

            $_usertype = $user->user_type;

            $parent_company = $user->company_parent;
            $companyID = $user->company_id;

            $clientEmail = $user->email;
            $clientFullName = $user->name;
            $genpass = Str::random(10);
            $newpassword = Hash::make($genpass);
            $user->password = $newpassword;
            $user->save();

            /** EMAIL SENT TO CLIENTS */
            
            $agencycompany = Company::select('company_name','domain','subdomain','status_domain')
                                        ->where('id','=',$parent_company)
                                        ->get();
            $agencyname = "";
            $agencyurl = "";

            if($_usertype == 'client') {
                if (count($agencycompany) > 0) {
                    $agencyname = $agencycompany[0]['company_name'];
                    $agencyurl = $agencycompany[0]['subdomain'];
                    if ($agencycompany[0]['domain'] != '' && $agencycompany[0]['status_domain'] == 'ssl_acquired') {
                        $agencyurl = $agencycompany[0]['domain'];
                        /** CHECK IF DOMAIN ALREADY POINTED TO OUR IP */
                        $ip = gethostbyname(trim($agencycompany[0]['domain']));
                        if ($ip == '157.230.213.72') {
                            $agencyurl = $agencycompany[0]['domain'];
                        }else{
                            $agencyurl = $agencycompany[0]['subdomain'];
                        }
                        /** CHECK IF DOMAIN ALREADY POINTED TO OUR IP */
                    }
                }
    
                /** START NEW METHOD EMAIL */
                $from = [
                    'address' => 'noreply@sitesettingsapi.com',
                    'name' => 'Welcome',
                    'replyto' => 'noreply@sitesettingsapi.com',
                ];
    
                $smtpusername = $this->set_smtp_email($parent_company);
                $emailtype = 'em_clientwelcomeemail';
    
                $customsetting = $this->getcompanysetting($parent_company,$emailtype);
                $chkcustomsetting = $customsetting;
    
                if ($customsetting == '') {
                    $customsetting =  json_decode(json_encode($this->check_email_template($emailtype,$parent_company)));
                }
    
                $finalcontent = nl2br($this->filterCustomEmail($user,$parent_company,$customsetting->content,$genpass,$agencyurl));
                $finalsubject = $this->filterCustomEmail($user,$parent_company,$customsetting->subject,$genpass,$agencyurl);
                $finalfrom = $this->filterCustomEmail($user,$parent_company,$customsetting->fromName,$genpass,$agencyurl);
    
                $details = [
                    'title' => ucwords($finalsubject),
                    'content' => $finalcontent,
                ];
                
                $from = [
                    'address' => (isset($customsetting->fromAddress) && $customsetting->fromAddress != '')?$customsetting->fromAddress:'noreply@sitesettingsapi.com',
                    'name' => (isset($finalfrom) && $finalfrom != '')?$finalfrom:'Welcome',
                    'replyto' => (isset($customsetting->fromReplyto) && $customsetting->fromReplyto != '')?$customsetting->fromReplyto:'support@sitesettingsapi.com',
                ];
    
                if ($smtpusername != "" && $chkcustomsetting == "") {
                    $from = [
                        'address' => $smtpusername,
                        'name' => (isset($finalfrom) && $finalfrom != '')?$finalfrom:'Welcome',
                        'replyto' => $smtpusername,
                    ];
                }
                
                $this->send_email(array($clientEmail),$from,ucwords($finalsubject),$details,array(),'emails.customemail',$parent_company);
                /** START NEW METHOD EMAIL */
                //$this->send_email(array($request->ClientEmail),$from,ucwords($agencyname) . ' Account Setup',$details,array(),'emails.userinvitation',$request->companyID);
            }else if ($_usertype == 'userdownline' || $_usertype == 'user') {

                /** START NEW EMAIL METHOD */
                if (count($agencycompany) > 0) {
                    $agencyname = $agencycompany[0]['company_name'];
                    $agencyurl = $agencycompany[0]['subdomain'];
                    if ($agencycompany[0]['domain'] != '') {
                        $agencyurl = $agencycompany[0]['domain'];
                        /** CHECK IF DOMAIN ALREADY POINTED TO OUR IP */
                        $ip = gethostbyname(trim($agencycompany[0]['domain']));
                        if ($ip == '157.230.213.72' || $ip == '146.190.186.110' || $ip == '143.244.212.205') {
                            $agencyurl = $agencycompany[0]['domain'];
                        }else{
                            $agencyurl = $agencycompany[0]['subdomain'];
                        }
                        /** CHECK IF DOMAIN ALREADY POINTED TO OUR IP */
                    }
                }

                $AdminDefault = $this->get_default_admin($parent_company);
                $AdminDefaultEmail = (isset($AdminDefault[0]['email']))?$AdminDefault[0]['email']:'';

                $defaultdomain = $this->getDefaultDomainEmail($parent_company);
    
                $details = [
                    'defaultadmin' => $AdminDefaultEmail,
                    'agencyname' => ucwords($agencyname),
                    'agencyurl' => 'https://' . $agencyurl,
                    'username' => $clientEmail,
                    'name'  => $clientFullName,
                    'newpass' => $genpass,
                ];
                    
                $from = [
                    'address' => 'noreply@' . $defaultdomain,
                        'name' => 'Welcome',
                    'replyto' => 'support@' . $defaultdomain,
                ];
                                
                $this->send_email(array($user->email),$from,'Your Agency Admin Account Setup',$details,array(),'emails.admininvitation',$request->companyID);
                /** START NEW EMAIL METHOD */
                   
            }
            /** EMAIL SENT TO CLIENTS */

            return response()->json(array('result'=>'success','message'=>'Invitation has been sent'));
        }else{
            return response()->json(array('result'=>'failed','message'=>'Invitation failed to sent'));
        }
    }

    public function resendInvitation2(Request $request) {
        $clientID = (isset($request->ClientID) && $request->ClientID != '')?$request->ClientID:'';
        if ($clientID != '') {
            $user = User::find($clientID);

            $_usertype = $user->user_type;

            $parent_company = $user->company_parent;
            $companyID = $user->company_id;

            $clientEmail = $user->email;
            $clientFullName = $user->name;
            $genpass = Str::random(10);
            $newpassword = Hash::make($genpass);
            $user->password = $newpassword;
            $user->save();

            /** EMAIL SENT TO CLIENTS */
            
            $agencycompany = Company::select('company_name','domain','subdomain','status_domain')
                                        ->where('id','=',$parent_company)
                                        ->get();
            $agencyname = "";
            $agencyurl = "";
            if (count($agencycompany) > 0) {
                $agencyname = $agencycompany[0]['company_name'];
                $agencyurl = $agencycompany[0]['subdomain'];
                if ($agencycompany[0]['domain'] != '' && $agencycompany[0]['status_domain'] == 'ssl_acquired') {
                    $agencyurl = $agencycompany[0]['domain'];
                }
            }

            $companyInfo = Company::select('company_name')
                                    ->where('id','=',$companyID)
                                    ->get();

            $companyName = "";
            if (count($companyInfo) > 0) {
                $companyName = $companyInfo[0]['company_name'];
            }

            $AdminDefault = $this->get_default_admin($parent_company);
            $AdminDefaultEmail = (isset($AdminDefault[0]['email']))?$AdminDefault[0]['email']:'';

            $defaultdomain = $this->getDefaultDomainEmail($parent_company);

            if ($_usertype == 'client') {
                $details = [
                    'agencyname' => ucwords($agencyname),
                    'agencyurl' => 'https://' . $agencyurl,
                    'defaultadmin' => $AdminDefaultEmail,
                    'username' => $clientEmail,
                    'name'  => $clientFullName,
                    'newpass' => $genpass,
                ];

                $from = [
                    'address' => 'noreply@' . $defaultdomain,
                    'name' => 'Welcome',
                    'replyto' => 'support@' . $defaultdomain,
                ];


                    $company_id = $user->company_parent;
                    if ($user->user_type == 'userdownline') {
                        $company_id = $user->commpany_id;
                    }
                    
                    $this->send_email(array($user->email),$from,$companyName . ' Account Set Up from ' . $agencyname,$details,array(),'emails.userinvitation',$company_id);
            }else if ($_usertype == 'user' || $_usertype == 'userdownline') {
                $details = [
                    'defaultadmin' => $AdminDefaultEmail,
                    'agencyname' => ucwords($agencyname),
                    'agencyurl' => 'https://' . $agencyurl,
                    'username' => $clientEmail,
                    'name'  => $clientFullName,
                    'newpass' => $genpass,
                ];
    
                $from = [
                    'address' => 'noreply@' . $defaultdomain,
                    'name' => 'Welcome',
                    'replyto' => 'support@' . $defaultdomain,
                ];
                
                $this->send_email(array($user->email),$from,ucwords($agencyname) . ' Administrator Setup',$details,array(),'emails.admininvitation',$request->companyID);
            }
            /** EMAIL SENT TO CLIENTS */

            return response()->json(array('result'=>'success','message'=>'Invitation has been sent'));
        }else{
            return response()->json(array('result'=>'failed','message'=>'Invitation failed to sent'));
        }
    }
    
    private function check_subordomain_exist($subordomain) {
        $chksubordomain = Company::select('id','logo','simplifi_organizationid','domain','subdomain')
                                    ->where(function ($query) use ($subordomain) {
                                        $query->where('domain','=',$subordomain)
                                                ->orWhere('subdomain','=',$subordomain);
                                    })->where('approved','=','T')
                                    ->get();

        if(count($chksubordomain) > 0) {
            return true;
        }else{
            return false;
        }

    }
 
    public function create(Request $request) {
        $confAppSysID = config('services.application.systemid');
        $defaultParentOrganization = config('services.sifidefaultorganization.organizationid');
        $DownlineDomain = (isset($request->DownlineDomain) && $request->DownlineDomain != '')?$request->DownlineDomain:'';
        $DownlineSubDomain = (isset($request->DownlineSubDomain) && $request->DownlineSubDomain != '')?$request->DownlineSubDomain:'';
        $disabledrecieveemail = (isset($request->disabledreceivedemail))?$request->disabledreceivedemail:'F';
        $enabledDeletedAccountClient = (isset($request->enabledDeletedAccountClient))?$request->enabledDeletedAccountClient:'F';
        $disabledaddcampaign = (isset($request->disabledaddcampaign))?$request->disabledaddcampaign:'F';
        $editorspreadsheet = (isset($request->editorspreadsheet))?$request->editorspreadsheet:'F';
        $enablephone = (isset($request->enablephonenumber))?$request->enablephonenumber:'F';
        // info('enable phone number : ' . $enablephone);

        $idsys = (isset($request->idsys))?$request->idsys:'';
        $ownedcompanyid = (isset($request->companyID))?$request->companyID:'';
        $is_whitelabeling = (isset($request->ClientWhiteLabeling))?$request->ClientWhiteLabeling:'F';
        $is_apimode = (isset($request->ApiMode))?$request->ApiMode:'F';

        $salesRep = (isset($request->salesRep))?$request->salesRep:'';
        $salesAE = (isset($request->salesAE))?$request->salesAE:'';
        $salesRef = (isset($request->salesRef))?$request->salesRef:'';

        /* THIS VARIABLE IN OPEN API CONTROLLER */
        $inOpenApi = (isset($request->inOpenApi))?$request->inOpenApi:"";
        $isSendEmail = (isset($request->isSendEmail))?$request->isSendEmail:"";
        /* THIS VARIABLE IN OPEN API CONTROLLER */
        
        //assign admin to all existing campaign
        $assign_admin_to_all_campaign = (isset($request->adminAddToAllCampaign) && $request->adminAddToAllCampaign !== null) ? $request->adminAddToAllCampaign : false;
        //assign admin to all existing campaign

        $permission_active = (isset($request->permission_active))?$request->permission_active:null;
        $user_permissions = (isset($request->user_permissions))?$request->user_permissions:null;
        
        $planMinSpendId = (isset($request->planMinSpendId))?$request->planMinSpendId:null; 

        /** CHECK IF EMAIL ALREADY EXIST */
        $chkusrname = strtolower($request->ClientEmail);
        // $chkEmailExist = User::where(function ($query) use ($ownedcompanyid,$chkusrname,$idSys) {
        //     // $query->where('company_id','=',$ownedcompanyid)
        //     $query->where(function($query) use ($ownedcompanyid,$idSys) {
        //                 $query->where('company_id','=',$ownedcompanyid)
        //                     ->orWhere('company_parent','=',$idSys);
        //             })
        //             ->where('email',Encrypter::encrypt($chkusrname))
        //             ->where('user_type','=','user');
        //     })->orWhere(function ($query) use ($ownedcompanyid,$chkusrname) {
        //         $query->where('email',Encrypter::encrypt($chkusrname))
        //                 ->where('user_type','=','userdownline')
        //                 ->where(function ($subQuery) use ($ownedcompanyid) {
        //                     $subQuery->where('company_id', '=', $ownedcompanyid)
        //                              ->orWhere('company_parent', '=', $ownedcompanyid);
        //                 });
        //     })->orWhere(function ($query) use ($ownedcompanyid,$chkusrname) {
        //         $query->where('company_parent','=',$ownedcompanyid)
        //                 ->where('email',Encrypter::encrypt($chkusrname))
        //                 ->where('user_type','=','client');
        //     })->orWhere(function ($query) use ($ownedcompanyid,$chkusrname,$idSys) {
        //         // $query->where('company_parent','=',$ownedcompanyid)
        //         $query->where(function($query2) use ($ownedcompanyid,$idSys) {
        //                     $query2->where('company_parent','=',$ownedcompanyid)
        //                         ->orWhere('company_parent','=',$idSys);
        //                 })
        //                 ->where('email',Encrypter::encrypt($chkusrname))
        //                 ->where('user_type','=','sales');
        //     })
        //     ->where('active','T')
        //     ->get();

        $chkEmailExist = [];
        if ($request->userType == 'client') {
            $chkEmailExist = User::where('email',Encrypter::encrypt($chkusrname))
                                 ->where('active','T')
                                 ->where(function ($query) use ($ownedcompanyid, $idsys) {
                                    $query->where(function ($query) use ($ownedcompanyid) { // check email di platform/domain itu sendiri, sudah dipakai oleh agency, admin agency, client belum
                                        $query->whereIn('user_type',['userdownline','user','client'])
                                              ->where(function ($query) use ($ownedcompanyid) {
                                                    $query->where('company_id',$ownedcompanyid)
                                                          ->orWhere('company_parent',$ownedcompanyid);
                                              });
                                    })->orWhere(function ($query) use ($idsys) { // check email di root, admin root, sales sudah dipakai atau belum
                                        $query->whereIn('user_type',['userdownline','user','sales'])
                                              ->where('company_id',$idsys);
                                    });
                                 })
                                 ->get();
        } else {
            $chkEmailExist = User::where('company_root_id',$idsys)
                                 ->where('email',Encrypter::encrypt($chkusrname))
                                 ->where('active','T')
                                 ->orderByRaw( // order by priority company_id, company_parent, id
                                    "
                                        CASE 
                                            WHEN company_id = ? THEN 0 
                                            WHEN company_parent = ? THEN 1 
                                            ELSE 2 
                                        END, id ASC
                                    ", 
                                    [$ownedcompanyid, $ownedcompanyid]
                                 )
                                 ->get();
        }

        //$chkEmailExist = User::where('email','=',trim(Encrypter::encrypt($request->email)))->get();
        if (count($chkEmailExist) > 0 || $ownedcompanyid == '') {
            $messageError = "This email address is already registered on another platform. Please use a different email address. Thank you!";
            if (count($chkEmailExist) > 0) { 
                $userTypeExists = $chkEmailExist[0]['user_type'] ?? null;
                $companyIDExists = ($userTypeExists == 'client') ?
                                   ($chkEmailExist[0]['company_parent'] ?? null) :
                                   ($chkEmailExist[0]['company_id'] ?? null) ;
                $companyIDRoot = User::where('company_parent',null)->pluck('company_id')->toArray();
                $suffix = "";
                // info(['companyIDExists' => $companyIDExists, 'ownedcompanyid' => $ownedcompanyid]);
                if (in_array($companyIDExists, $companyIDRoot)) { // jika di platform root
                    if ($companyIDExists != $ownedcompanyid) { // jika yang login tidak berasal dari root
                        $suffix = ['userdownline' => '(1)', 'user' => '(2)', 'sales' => '(3)'][$userTypeExists] ?? ''; // 1 = root , 2 = admin root , 3 = sales
                    } elseif ($companyIDExists == $ownedcompanyid) { // jika yang login berasal dari root
                        $roleLabels = ['userdownline' => 'as a root', 'user' => 'as a admin root', 'client' => 'as a client', 'sales' => 'as a sales'][$userTypeExists] ?? '';
                        $messageError = "This email address is already registered on your platform $roleLabels. Please use a different email address. Thank you!";
                    }
                } elseif ($companyIDExists != $ownedcompanyid) { // jika beda platform agency
                    $suffix = ['userdownline' => '(4)', 'user' => '(5)', 'client' => '(6)'][$userTypeExists] ?? ''; // 4 = agency , 5 = admin agency , 6 = client 
                } elseif ($companyIDExists == $ownedcompanyid) { // jika sama di dalam platform
                    $roleLabels = ['userdownline' => 'as a agency', 'user' => 'as a admin agency', 'client' => 'as a client', 'sales' => 'as a sales'][$userTypeExists] ?? '';
                    $messageError = "This email address is already registered on your platform $roleLabels. Please use a different email address. Thank you!";
                }
                $messageError .= " $suffix";
            }
            return response()->json(array('result'=>'error','message'=>$messageError,'error'=>''));
        }
        /** CHECK IF EMAIL ALREADY EXIST */

        /** GET ROOT SYS CONF */
        $confAppDomain =  config('services.application.domain');
        if ($idsys != "") {
            $conf = $this->getCompanyRootInfo($idsys);
            $confAppDomain = $conf['domain'];
        }
        /** GET ROOT SYS CONF */

        if ($DownlineDomain != "") {
            $DownlineDomain = str_replace('http://','',$DownlineDomain);
            $DownlineDomain = trim(str_replace('https://','',$DownlineDomain));
        }

        if ($DownlineSubDomain != "") {
            $DownlineSubDomain = str_replace('http://','',$DownlineSubDomain);
            $DownlineSubDomain = trim(str_replace('https://','',$DownlineSubDomain));
            $DownlineSubDomain = $DownlineSubDomain . '.' . $confAppDomain;
            if ($this->check_subordomain_exist($DownlineSubDomain)) {
                return response()->json(array('result'=>'failed','message'=>'This subdomain already exists'));
            }
        }

        //CHARGE ONBOARDING NEW AGENCY
        $customer_payment_id = '';
        $customer_card_id = '';
        $amount_onboard_charged = null;
        $is_onboard_charged = 0;
        $last_payment_update = null;
        $registration_method = null;
        if ($request->userType == 'userdownline' && isset($request->TokenId) && $request->TokenId != '' && isset($request->Amount) && $request->Amount != '' ) {

            $rootonboardingagency = $this->getcompanysetting($idsys,'rootonboardingagency');

            if ((isset($rootonboardingagency->status) && $rootonboardingagency->status == true)) {

                $param = [
                    'token_id' => $request->TokenId,
                    'name' => $request->ClientFullName,
                    'phone' => $request->ClientPhone,
                    'company_name' => $request->ClientCompanyName,
                    'email' => $request->ClientEmail,
                    'company_root_id' => $request->idsys,
                    'amount' => $request->Amount
                ];

                $charge_new_agency = $this->charge_new_agency($param);

                if (isset($charge_new_agency['result']) && ($charge_new_agency['result'] == 'failed' || $charge_new_agency['result'] == 'error')) {//if charge failed
                    return response()->json(array('result'=>'error','message'=>$charge_new_agency['message'],'error'=> $charge_new_agency['message']));
                }else {
                    $customer_payment_id = isset($charge_new_agency['customer_id']) ? $charge_new_agency['customer_id'] : '';
                    $customer_card_id = isset($charge_new_agency['customer_card_id']) ? $charge_new_agency['customer_card_id'] : '';
                    $amount_onboard_charged = $request->Amount ?? 0;
                    $is_onboard_charged = 1; 
                    $last_payment_update = now(); 
                    $registration_method = 'save_and_charge';
                }
            }   
        }
        //CHARGE ONBOARDING NEW AGENCY

        $DownlineOrganizationID = (isset($request->DownlineOrganizationID) && $request->DownlineOrganizationID != '')?$request->DownlineOrganizationID:'';

        $sortorder = 0;

        if($request->userType == 'userdownline') {
            /** FIND THE LAST SORT */
           $sortResult = User::select('id','sort')->where('user_type','=','userdownline')->where('company_parent','=',$request->companyID)->orderByDesc('sort')->first();
           if (isset($sortResult)) {
            $sortorder = ($sortResult->sort + 1);
           }
           /** FIND THE LAST SORT */
        }

        $newpassword = Str::random(10);
        $company_id = '';
        $company_parent = $request->companyID;
        $isAdmin = 'T';
        $defaultAdmin = 'F';
        $customercare = 'F';
        $adminGetNotification = "F";
        $usrCompleteProfileSetup = 'F';
        $refcode = '';

        if(isset($request->defaultAdmin) && $request->defaultAdmin == 'T') {
            $defaultAdmin = 'T';
        }

        if(isset($request->adminGetNotification) && $request->adminGetNotification == 'T') {
            $adminGetNotification = 'T';
        }

        //if($request->userType == 'client' || $request->userType == 'userdownline') {
        if($request->userType == 'client') {
            $isAdmin = 'F';
        }

        if($request->userType == 'sales') {
            $isAdmin = 'T';
            $company_id = $request->companyID;
            if(isset($request->ClientPass) && $request->ClientPass != '') {
                $newpassword = $request->ClientPass;
            }
            $usrCompleteProfileSetup = 'T';
        }

        if($request->userType == 'user') {
            if(isset($request->ClientPass) && $request->ClientPass != '') {
                $newpassword = $request->ClientPass;
            }
            //$company_parent = null;
            $company_id = $request->companyID;
            if ($isAdmin == 'T') {
                $usrCompleteProfileSetup = 'T';
            }

            if(isset($request->customercare) && $request->customercare == 'T') {
                $customercare = 'T';
               
                /** UPDATE CUSTOMER CARE ONLY CAN BE ONLY ONE */
                $updcustcare = User::where('company_id','=',$company_id)
                                    ->where(function ($query) {
                                        $query->where('user_type','=','user')
                                        ->orWhere('user_type','=','userdownline');
                                    })
                                    ->update(['customercare' => 'F']);
                /** UPDATE CUSTOMER CARE ONLY CAN BE ONLY ONE */
            }

            // Validation User Permissions
            if($user_permissions){
                if (!is_array($user_permissions) && !is_object($user_permissions)) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'user permissions should be an object or an array.'
                    ], 400);
                }
                $allowed_keys = ['external_sf', 'report_analytics'];
        
                foreach ($user_permissions as $key => $value) {
                    if (!in_array($key, $allowed_keys)) {
                        return response()->json([
                            'status' => 'error',
                            'message' => "The key '$key' is not allowed."
                        ], 400);
                    }
            
                    if (!is_bool($value)) {
                        return response()->json([
                            'status' => 'error',
                            'message' => "The value for '$key' must be a boolean."
                        ], 400);
                    }
                }

                $user_permissions = json_encode($user_permissions);
            }
            // Validation User Permissions
        }

        if($request->userType == 'userdownline') {
            $isAdmin = 'T';
            $defaultAdmin = 'T';
            $customercare = 'T';
            $adminGetNotification = 'T';
        }

        if($inOpenApi === true && isset($request->ClientPass) && $request->ClientPass != '' && ($request->userType == 'client' || $request->userType == 'userdownline' || $request->userType == 'sales')) {
            $newpassword = $request->ClientPass;
        }

        //two factor authenctication
        $tfa_active = 0;
        $tfa_type = null;
        if ($request->userType == 'userdownline' || $request->userType == 'user' && !empty($request->twoFactorAuth)) {
            $tfa_active = 1;
            $tfa_type = $request->twoFactorAuth;
        }
        //two factor authenctication

        /** CHECK IF EMAIL ALREADY EXIST */
        // $chkEmailExist = User::where('email','=',trim($request->ClientEmail))->get();
        // if (count($chkEmailExist) > 0) {
        //     return response()->json(array('result'=>'failed','message'=>'Sorry, email is already exist. Email will be use for your client as the username','data'=>array()));
        // }
        /** CHECK IF EMAIL ALREADY EXIST */

        // Create company is_whitelabeling

        /* FORCE API MODE TO F WHEN USERDOWNLINE OTHEN EMM */
        if($request->userType == 'userdownline' && $idsys != $confAppSysID) {
            $is_apimode = 'F';
        }
        /* FORCE API MODE TO F WHEN USERDOWNLINE OTHEN EMM */
        

        $usr = User::create([
            'name' => $request->ClientFullName,
            'email' => strtolower($request->ClientEmail),
            'phonenum' => $request->ClientPhone,
            'phone_country_code' => $request->ClientPhoneCountryCode,
            'phone_country_calling_code' => $request->ClientPhoneCountryCallingCode,
            'password' => Hash::make($newpassword),
            'role_id' => $request->ClientRole,
            'company_id' => $company_id,
            'company_parent' => $company_parent,
            'company_root_id' => $idsys,
            'user_type' => $request->userType,
            'isAdmin' => $isAdmin,
            'profile_setup_completed' => $usrCompleteProfileSetup,
            'sort' => $sortorder,
            'city' => '',
            'zip' => '',
            'country_code' => '',
            'state_code' => '',
            'state_name' => '',
            'lp_limit_freq' => 'day',
            'defaultadmin' => $defaultAdmin,
            'customercare' => $customercare,
            'admin_get_notification' => $adminGetNotification,
            'acc_connect_id' => '',
            'acc_email' => '',
            'acc_ba_id' => '',
            'status_acc' => '',
            'customer_payment_id' => $customer_payment_id,
            'customer_card_id' => $customer_card_id,
            'amount_onboard_charged' => $amount_onboard_charged,
            'is_onboard_charged' => $is_onboard_charged,
            'last_payment_update' => $last_payment_update,
            'disabled_receive_email' => $disabledrecieveemail,
            'enabled_client_deleted_account' => $enabledDeletedAccountClient,
            'disable_client_add_campaign' => $disabledaddcampaign,
            'editor_spreadsheet' => $editorspreadsheet,
            'enabled_phone_number' => $enablephone,
            'tfa_active' => $tfa_active,
            'tfa_type' => $tfa_type,
            'permission_active' => $permission_active,
            'user_permissions' => $user_permissions,
            'api_mode' => $is_apimode,
            'registration_method' => $registration_method,
        ]);

        $usrID = $usr->id;

        // SAVE LOGS
        $login_id = null;
        if ($inOpenApi === true) {
            $login_id = User::where('company_id', $company_parent)->where('user_type', 'userdownline')->where('active', 'T')->value('id');
        } else {
            $login_id = optional(auth()->user())->id ?? $usrID;
        }
        $isRoot = isset($request->companyID, $request->idsys) 
                        && $request->companyID == $request->idsys;
        $ipAddress = ($request->header('Clientip') !== null)?$request->header('Clientip'):$request->ip();

        $action = 'Add User';
        $except = [];
        $data   = $request->all();

        if ($request->userType === 'userdownline') {
            if (!empty($request->TokenId) && !empty($request->Amount)) {
                $action = 'Add Agency - Save and Charge';
                $except = ['twoFactorAuth'];
            } else {
                $action = 'Add Agency - Save';
                $except = ['twoFactorAuth', 'TokenId', 'Amount'];
            }
        } elseif ($request->userType === 'user') {
            $except = ['selectedmodules', 'twoFactorAuth', 'TokenId', 'Amount', 'ClientCompanyName'];
            $action = $isRoot ? 'Add Admin Root' : 'Add Admin Agency';
        } elseif ($request->userType === 'client') {
            $action = 'Add Client';
            $except = ['TokenId', 'Amount'];
        } elseif ($request->userType === 'sales') {
            $action = 'Add Sales';
            $except = ['selectedmodules', 'TokenId', 'Amount'];
        }

        if (!empty($except)) {
            $data = $request->except($except);
        }

        $requestJson = json_encode($data);
        $this->logUserAction($login_id, $action, $requestJson, $ipAddress, $usrID);
        // SAVE LOGS

        /** CHECK IF USER DOWNLINE AND IS EMM AGENCY, AND UPDATE PLAN MIN SPEND ID */
        if($request->userType == 'userdownline' && $idsys == config('services.application.systemid')) {
            $updateTrialDate = User::where('id',$usrID)
                                    ->where('active','=','T')
                                    ->where('user_type','=','userdownline')
                                    ->update([
                                        'trial_end_date' => DB::raw("DATE_ADD(created_at, INTERVAL 2 MONTH)"),
                                        'last_invoice_minspend' => DB::raw("
                                            CASE
                                                WHEN DAY(created_at) IN (29, 30, 31) THEN
                                                    DATE_ADD(LAST_DAY(created_at) + INTERVAL 1 DAY, INTERVAL 2 MONTH)
                                                ELSE
                                                    DATE_ADD(created_at, INTERVAL 2 MONTH)
                                            END
                                        "),
                                    ]);
            $usr->plan_minspend_id = $planMinSpendId;
            $usr->save();
        }
        /** CHECK IF USER DOWNLINE AND IS EMM AGENCY, AND UPDATE PLAN MIN SPEND ID */
        
        /** CREATE REFERRAL CODE IF SALES */
        if($request->userType == 'sales') {
            $updrefcode = User::find($usrID);
            $updrefcode->referralcode = $this->generateReferralCode('salesref' . $usrID);
            $updrefcode->save();
        }
        /** CREATE REFERRAL CODE IF SALES */

        if(isset($request->ClientCompanyName) && $request->ClientCompanyName != '') {

            /** IF SUBDOMAIN EMPTY AUTO CREATE */
            if($request->userType == 'userdownline' && $DownlineSubDomain == "") {
                $_comname = explode(' ',strtolower($request->ClientCompanyName));
                $subresult = '';

                foreach ($_comname as $w) {
                    $subresult .= mb_substr($w, 0, 1);
                }

                $subresult = preg_replace('/[^a-zA-Z0-9]/', '', $subresult);

                $DownlineSubDomain = $subresult . date('ynjis') . '.' . $confAppDomain;

                while ($this->check_subordomain_exist($DownlineSubDomain)) {
                     $DownlineSubDomain = $subresult . date('ynjis') . '.' . $confAppDomain;
                }
            }
            /** IF SUBDOMAIN EMPTY AUTO CREATE */

            /** CREATE ORGANIZATION ON SIMPLI.FI */
            if (trim($DownlineOrganizationID) == "") {
                $companyParent = Company::select('simplifi_organizationid')
                                    ->where('id','=',$company_parent)
                                    ->get();
                if(count($companyParent) > 0) {
                    if ($companyParent[0]['simplifi_organizationid'] != '') {
                        $defaultParentOrganization = $companyParent[0]['simplifi_organizationid'];
                    }
                }

                /** CREATE ORGANIZATION */
                if ($request->userType == 'userdownline') { 
                    $sifiEMMStatus = "[AGENCY]";
                    if (config('services.appconf.devmode') === true) {
                        $sifiEMMStatus = "[AGENCY BETA]";
                    }
                }else if ($request->userType == 'client') {
                    $sifiEMMStatus = "[CLIENT]";
                    if (config('services.appconf.devmode') === true) {
                        $sifiEMMStatus = "[CLIENT BETA]";
                    }
                }
                $DownlineOrganizationID = $this->createOrganization(trim($request->ClientCompanyName) . ' ' . $sifiEMMStatus,$defaultParentOrganization);
                /** CREATE ORGANIZATION */
            }
            /** CREATE ORGANIZATION ON SIMPLI.FI */

            /** UPDATE DEFAULT PAYMENT IF SETTING EXIST IF NOT THEN USE DEFAULT DB */
            $_paymentterm_default = "Weekly";

            if ($request->userType == 'userdownline') { 
                $getRootSetting = $this->getcompanysetting($idsys,'rootsetting');
                if ($getRootSetting != '') {
                    if (isset($getRootSetting->defaultpaymentterm) && $getRootSetting->defaultpaymentterm != '') {
                        $_paymentterm_default = trim($getRootSetting->defaultpaymentterm);
                    }
                }
            }else if ($request->userType == 'client') {
                $getCompanyParentDefaultPayment = Company::select('paymentterm_default')->where('id',$company_parent)->first();
                if ($getCompanyParentDefaultPayment) {
                    $_paymentterm_default = $getCompanyParentDefaultPayment->paymentterm_default;
                } 
            }
            /** UPDATE DEFAULT PAYMENT IF SETTING EXIST IF NOT THEN USE DEFAULT DB */

            $newCompany = Company::create([
                'company_name' => $request->ClientCompanyName,
                'company_city' => '',
                'company_zip' => '',
                'company_country_code' => '',
                'company_state_code' => '',
                'company_state_name' => '', 
                'simplifi_organizationid' => $DownlineOrganizationID,
                'domain' => $DownlineDomain,
                'subdomain' => $DownlineSubDomain,
                'sidebar_bgcolor' => '',
                'template_bgcolor' => '',
                'box_bgcolor' => '',
                'font_theme' => '',
                'login_image' => '',
                'client_register_image' => '',
                'agency_register_image' => '',
                'approved' => 'T',
                'paymentterm_default' => $_paymentterm_default
            ]);

            $newCompanyID = $newCompany->id;

            $usr->company_id = $newCompanyID;
            $usr->save();

            if ($request->userType == 'userdownline') { 
                /** CREATE FREE PACKAGE FOR ONBOARDING CHARGE*/
                if ($is_onboard_charged == 1) {
                    /** CHECK IF THERE IS ANY OTHER ROOT HAVE FREE PLAN AS DEFAULT */
                    $getRootSetting = $this->getcompanysetting($idsys,'rootsetting');
                    $anotherRootDefaultFreePlan = false;
                    if ($getRootSetting != '') {
                        if (isset($getRootSetting->defaultfreeplan) && $getRootSetting->defaultfreeplan == 'T') {
                            $anotherRootDefaultFreePlan = true;
                        }
                    }

                    if ($anotherRootDefaultFreePlan) {
                        $freeplanID = "";
                        $getfreePlan = $this->getcompanysetting($idsys,'agencyplan');
                        if ($getfreePlan != '') {
                                $freeplanID = (isset($getfreePlan->livemode->free))?$getfreePlan->livemode->free:"";
                            if (config('services.appconf.devmode') === true) {
                                $freeplanID = (isset($getfreePlan->testmode->free))?$getfreePlan->testmode->free:"";
                            }
                        }

                        /** GET STRIPE KEY */
                        $stripeseckey = config('services.stripe.secret');
                        $stripepublish = $this->getcompanysetting($idsys,'rootstripe');
                        if ($stripepublish != '') {
                            $stripeseckey = (isset($stripepublish->secretkey))?$stripepublish->secretkey:"";
                        }
                        /** GET STRIPE KEY */

                        $stripe = new StripeClient([
                            'api_key' => $stripeseckey,
                            'stripe_version' => '2020-08-27'
                        ]);

                        $companycheck = CompanyStripe::where('company_id','=',$newCompanyID)
                                            ->get();
                        if($companycheck->count() == 0) {
                            try{
                                /** CREATE SUBSCRIPTION */
                            $createSub = $stripe->subscriptions->create([
                                "customer" => trim($customer_payment_id),
                                "items" => [
                                    ["price" => $freeplanID],
                                ],
                                "default_source" => $customer_card_id,

                            ]);
                            /** CREATE SUBSCRIPTION */

                            /** CREATE COMPANY STRIPE */
                            $createCompany = CompanyStripe::create([
                                    'company_id' => $newCompanyID,
                                    'acc_connect_id' => '',
                                    'acc_prod_id' => '',
                                    'acc_email' => '',
                                    'acc_ba_id' => '',
                                    'acc_holder_name' => '',
                                    'acc_holder_type' => '',
                                    'ba_name' => '',
                                    'ba_route' => '',
                                    'status_acc' => '',
                                    'ipaddress' => '',
                                    'package_id' => $freeplanID,
                                    'subscription_id' => $createSub->id,
                                    'subscription_item_id' => (isset($createSub->items->data[0]['id']))?$createSub->items->data[0]['id']:'',
                                    'plan_date_created' => date('Y-m-d'),
                                    'plan_next_date' => date('Y-m-d',strtotime(date('Y-m-d') . ' +1 years'))
                                ]);
                            
                            /** CREATE COMPANY STRIPE */
                        }catch (Exception $e) {
                                Log::info("Error Create Customer (L859) : " . $e->getMessage());
                        }
                        }
                    }
                    
                    /** CREATE FREE PACKAGE FOR ONBOARDING CHARGE */
                }

                /** CREATE DEFAULT PRICE FOR COST AGENCY */

                    $comset_val = [
                        "local" => [
                            "Monthly" => [
                                "LeadspeekCostperlead" => '0.10',
                                "LeadspeekMinCostMonth" => '0',
                                "LeadspeekPlatformFee" => '0'
                            ],
                            "OneTime" => [
                                "LeadspeekCostperlead" => "0.10",
                                "LeadspeekMinCostMonth" => "0",
                                "LeadspeekPlatformFee" => "0",
                            ],
                            "Weekly" => [
                                "LeadspeekCostperlead" => "0.10",
                                "LeadspeekMinCostMonth" => "0",
                                "LeadspeekPlatformFee" => "0",
                            ],
                            "Prepaid" => [
                                "LeadspeekCostperlead" => "0.10",
                                "LeadspeekMinCostMonth" => "0",
                                "LeadspeekPlatformFee" => "0",
                            ]
                        ],

                        "locator" => [
                            "Monthly" => [
                                "LocatorCostperlead" => '1.29',
                                "LocatorMinCostMonth" => '0',
                                "LocatorPlatformFee" => '0'
                            ],
                            "OneTime" => [
                                "LocatorCostperlead" => "1.29",
                                "LocatorMinCostMonth" => "0",
                                "LocatorPlatformFee" => "0",
                            ],
                            "Weekly" => [
                                "LocatorCostperlead" => "1.29",
                                "LocatorMinCostMonth" => "0",
                                "LocatorPlatformFee" => "0",
                            ],
                            "Prepaid" => [
                                "LocatorCostperlead" => "1.29",
                                "LocatorMinCostMonth" => "0",
                                "LocatorPlatformFee" => "0",
                            ]
                        ],

                        "enhance" => [
                            "Monthly" => [
                                "EnhanceCostperlead" => '0.10',
                                "EnhanceMinCostMonth" => '0',
                                "EnhancePlatformFee" => '0'
                            ],
                            "OneTime" => [
                                "EnhanceCostperlead" => "0.10",
                                "EnhanceMinCostMonth" => "0",
                                "EnhancePlatformFee" => "0",
                            ],
                            "Weekly" => [
                                "EnhanceCostperlead" => "0.10",
                                "EnhanceMinCostMonth" => "0",
                                "EnhancePlatformFee" => "0",
                            ],
                            "Prepaid" => [
                                "EnhanceCostperlead" => "0.10",
                                "EnhanceMinCostMonth" => "0",
                                "EnhancePlatformFee" => "0",
                            ]
                        ],

                        "b2b" => [
                            "Monthly" => [
                                "B2bCostperlead" => '0.10',
                                "B2bMinCostMonth" => '0',
                                "B2bPlatformFee" => '0'
                            ],
                            "OneTime" => [
                                "B2bCostperlead" => "0.10",
                                "B2bMinCostMonth" => "0",
                                "B2bPlatformFee" => "0",
                            ],
                            "Weekly" => [
                                "B2bCostperlead" => "0.10",
                                "B2bMinCostMonth" => "0",
                                "B2bPlatformFee" => "0",
                            ],
                            "Prepaid" => [
                                "B2bCostperlead" => "0.10",
                                "B2bMinCostMonth" => "0",
                                "B2bPlatformFee" => "0",
                            ],
                        ],

                        "clean" => [
                            "CleanCostperlead" => "0.5",
                            "CleanCostperleadAdvanced" => "1"
                        ]
                    ];

                    /** GET DEFAULT ROOT COST AGENCY */
                    $comset_val = $this->getcompanysetting($idsys,'rootcostagency');
                    /** GET DEFAULT ROOT COST AGENCY */

                    $createsetting = CompanySetting::create([
                        'company_id' => $newCompanyID,
                        'setting_name' => 'costagency',
                        'setting_value' => json_encode($comset_val),
                    ]);
                
                /** CREATE DEFAULT PRICE FOR COST AGENCY */

                /* CREATE DEFAULT PRICE FOR AGENCY DEFAULT PRICE */
                $agency_default_price = [
                    "local" => [
                        "Monthly" => [
                            "LeadspeekCostperlead" => '1',
                            "LeadspeekCostperleadAdvanced" => '1',
                            "LeadspeekMinCostMonth" => '0',
                            "LeadspeekPlatformFee" => '0'
                        ],
                        "OneTime" => [
                            "LeadspeekCostperlead" => "1",
                            "LeadspeekCostperleadAdvanced" => '1',
                            "LeadspeekMinCostMonth" => "0",
                            "LeadspeekPlatformFee" => "0",
                        ],
                        "Weekly" => [
                            "LeadspeekCostperlead" => "1",
                            "LeadspeekCostperleadAdvanced" => '1',
                            "LeadspeekMinCostMonth" => "0",
                            "LeadspeekPlatformFee" => "0",
                        ],
                        "Prepaid" => [
                            "LeadspeekCostperlead" => "1",
                            "LeadspeekCostperleadAdvanced" => '1',
                            "LeadspeekMinCostMonth" => "0",
                            "LeadspeekPlatformFee" => "0",
                        ]
                    ],

                    "locator" => [
                        "Monthly" => [
                            "LocatorCostperlead" => '1',
                            "LocatorMinCostMonth" => '0',
                            "LocatorPlatformFee" => '0'
                        ],
                        "OneTime" => [
                            "LocatorCostperlead" => "1",
                            "LocatorMinCostMonth" => "0",
                            "LocatorPlatformFee" => "0",
                        ],
                        "Weekly" => [
                            "LocatorCostperlead" => "1",
                            "LocatorMinCostMonth" => "0",
                            "LocatorPlatformFee" => "0",
                        ],
                        "Prepaid" => [
                            "LocatorCostperlead" => "1",
                            "LocatorMinCostMonth" => "0",
                            "LocatorPlatformFee" => "0",
                        ],
                    ],

                    "enhance" => [
                        "Monthly" => [
                            "EnhanceCostperlead" => '1',
                            "EnhanceMinCostMonth" => '0',
                            "EnhancePlatformFee" => '0'
                        ],
                        "OneTime" => [
                            "EnhanceCostperlead" => "1",
                            "EnhanceMinCostMonth" => "0",
                            "EnhancePlatformFee" => "0",
                        ],
                        "Weekly" => [
                            "EnhanceCostperlead" => "1",
                            "EnhanceMinCostMonth" => "0",
                            "EnhancePlatformFee" => "0",
                        ],
                        "Prepaid" => [
                            "EnhanceCostperlead" => "1",
                            "EnhanceMinCostMonth" => "0",
                            "EnhancePlatformFee" => "0",
                        ],
                    ],

                    "b2b" => [
                        "Monthly" => [
                            "B2bCostperlead" => '1.49',
                            "B2bMinCostMonth" => '0',
                            "B2bPlatformFee" => '0'
                        ],
                        "OneTime" => [
                            "B2bCostperlead" => "1.49",
                            "B2bMinCostMonth" => "0",
                            "B2bPlatformFee" => "0",
                        ],
                        "Weekly" => [
                            "B2bCostperlead" => "1.49",
                            "B2bMinCostMonth" => "0",
                            "B2bPlatformFee" => "0",
                        ],
                        "Prepaid" => [
                            "B2bCostperlead" => "1.49",
                            "B2bMinCostMonth" => "0",
                            "B2bPlatformFee" => "0",
                        ],
                    ],

                    "simplifi" => [
                        "Prepaid" => [
                            "SimplifiMaxBid" => "12",
                            "SimplifiDailyBudget" => "5",
                            "SimplifiAgencyMarkup" => "0",
                        ]
                    ]
                ];

                $agency_default_price = $this->getcompanysetting($idsys,'rootagencydefaultprice');

                $createsetting = CompanySetting::create([
                    'company_id' => $newCompanyID,
                    'setting_name' => 'agencydefaultprice',
                    'setting_value' => json_encode($agency_default_price),
                ]);
                /* CREATE DEFAULT PRICE FOR AGENCY DEFAULT PRICE */

                // AGENCY PAYMENT TERM

                // Create company is_whitelabeling
                if($is_whitelabeling == 'T'){
                    $company = Company::find($newCompanyID);
                    if ($company) {
                        $company->is_whitelabeling = 'T';
                        $company->save();
                    }
                } else {
                    $company = Company::find($newCompanyID);
                    if ($company) {
                        $company->is_whitelabeling = 'F';
                        $company->save();
                    }
                }

                // SAVE PAYMENTTERMCONTROL
                try {
                    $paymentterm = $request->selectedterms;
                    $allFalse = true;
                    foreach ($paymentterm as $term) {
                        if ($term['status'] === true) {
                            $allFalse = false;
                            break;
                        }
                    }
                    if (!$allFalse) {
                        $paymentterm = [
                            "SelectedPaymentTerm" => $request->selectedterms,
                        ];
                        $createsetting = CompanySetting::create([
                            'company_id' => $newCompanyID,
                            'setting_name' => 'agencypaymentterm',
                            'setting_value' => json_encode($paymentterm),
                        ]);
                    }
                } catch (\Throwable $th) {
                    return response()->json(['result' => 'failed', 'message' => $th->getMessage()]);
                }
                // SAVE PAYMENTTERMCONTROL

                // SAVE AGENCY MODULES
                $modules = [];
                if (!empty($request->selectedmodules) && isset($request->selectedmodules)) {
                    $modules = [
                        "SelectedModules" => $request->selectedmodules,
                    ];
                }

                if ($modules != []) {
                    $createsetting = CompanySetting::create([
                    'company_id' => $newCompanyID,
                    'setting_name' => 'agencysidebar',
                    'setting_value' => json_encode($modules),
                    ]);
                }
                // SAVE AGENCY MODULES

                /** SET SALES, AE OR REFERRAL IF ANY */
                if (trim($salesRep) != '') {
                    /** FOR SALE REPS */
                    $chkSalesCompany = CompanySale::select('id')
                                                    ->where('company_id','=',$newCompanyID)
                                                    ->where('sales_title','=','Sales Representative')
                                                    ->get();
        
                    if (count($chkSalesCompany) == 0) {
                        if (trim($salesRep) != "") {
                            $createSalesRep = CompanySale::create([
                                                    'company_id' => $newCompanyID,
                                                    'sales_id' => $salesRep,
                                                    'sales_title' => 'Sales Representative',
                                                ]);
                        }
                    }else{
                        if (trim($salesRep) != "") {
                            $updateSalesRep = CompanySale::find($chkSalesCompany[0]['id']);
                            $updateSalesRep->sales_id = $salesRep;
                            $updateSalesRep->save();
                        }else{
                            $deleteSalesRep = CompanySale::find($chkSalesCompany[0]['id']);
                            $deleteSalesRep->delete();
                        }
                    }
                    /** FOR SALE REPS */
                }

                if (trim($salesAE) != '') {
                    /** FOR Account Executive */
                    $chkSalesCompany = CompanySale::select('id')
                                                    ->where('company_id','=',$newCompanyID)
                                                    ->where('sales_title','=','Account Executive')
                                                    ->get();
        
                    if (count($chkSalesCompany) == 0) {
                        if (trim($salesAE) != "") {
                            $createSalesAE = CompanySale::create([
                                                    'company_id' => $newCompanyID,
                                                    'sales_id' => $salesAE,
                                                    'sales_title' => 'Account Executive',
                                                ]);
                        }
                    }else{
                        if (trim($salesAE) != "") {
                            $updateSalesAE = CompanySale::find($chkSalesCompany[0]['id']);
                            $updateSalesAE->sales_id = $salesAE;
                            $updateSalesAE->save();
                        }else{
                            $deleteSalesAE = CompanySale::find($chkSalesCompany[0]['id']);
                            $deleteSalesAE->delete();
                        }
                    }
                    /** FOR Account Executive */
                }

                if (trim($salesRef) != '') {
                    
                    /** FOR SALES REFERRAL */
                    $chkSalesCompany = CompanySale::select('id')
                                                    ->where('company_id','=',$newCompanyID)
                                                    ->where('sales_title','=','Account Referral')
                                                    ->get();
        
                    if (count($chkSalesCompany) == 0) {
                        if (trim($salesRef) != "") {
                            
                            $createSalesRef = CompanySale::create([
                                                    'company_id' => $newCompanyID,
                                                    'sales_id' => $salesRef,
                                                    'sales_title' => 'Account Referral',
                                                ]);
                        }
                    }else{
                        if (trim($salesRef) != "") {
                            $updateSalesAE = CompanySale::find($chkSalesCompany[0]['id']);
                            $updateSalesAE->sales_id = $salesRef;
                            $updateSalesAE->save();
                        }else{
                            $deleteSalesAE = CompanySale::find($chkSalesCompany[0]['id']);
                            $deleteSalesAE->delete();
                        }
                    }
                    /** FOR SALES REFERRAL */
                }
                
                /** SET SALES, AE OR REFERRAL IF ANY */
            } elseif ($request->userType == 'client') {
                //SAVE CLIENT SIDEBAR CONTROL
                $clientsidebar_setting = CompanySetting::where('company_id', $newCompanyID)
                    ->whereEncrypted('setting_name', 'clientsidebar')
                    ->first();

                $clientsidebar = [];

                if (!empty($request->selectedmodules) && isset($request->selectedmodules)) {
                    $selectedModules = $request->selectedmodules;

                    // Force predict to always be true for clients
                    $predictExists = false;
                    foreach ($selectedModules as $key => $module) {
                        if (isset($module['type']) && $module['type'] === 'predict') {
                            $selectedModules[$key]['status'] = true; // Force status to true
                            $predictExists = true;
                            break;
                        }
                    }
                    
                    // If predict doesn't exist, add it with status true
                    if (!$predictExists) {
                        $selectedModules[] = [
                            'type' => 'predict',
                            'status' => true
                        ];
                    }
                    
                    $clientsidebar = [
                        "SelectedModules" => $selectedModules,
                    ];
                }

                if ($clientsidebar_setting && !empty($request->selectedmodules) && isset($request->selectedmodules)) {
                    $clientsidebar_setting->setting_value = json_encode($clientsidebar);
                    $clientsidebar_setting->save();
                } else {
                    if ($clientsidebar != []) {
                        $createsetting = CompanySetting::create([
                            'company_id' => $newCompanyID,
                            'setting_name' => 'clientsidebar',
                            'setting_value' => json_encode($clientsidebar),
                        ]);
                    }
                } 
            }
        }

        if(isset($request->ClientDomain) && $request->ClientDomain != '') {
            $newdomain = str_replace('http://','',$request->ClientDomain);
            $newdomain = trim(str_replace('https://','',$newdomain));

            $newSite = Site::create([
                'company_name' => $request->ClientCompanyName,
                'domain' => $newdomain,
            ]);

            $newsiteID = $newSite->id;

            $usr->site_id = $newsiteID;
            $usr->save();
        }

        /** EMAIL SENT TO CLIENTS */
        if($request->userType == 'client') {
            $agencycompany = Company::select('company_name','domain','subdomain','status_domain')
                                        ->where('id','=',$request->companyID)
                                        ->get();
            $agencyname = "";
            $agencyurl = "";
            if (count($agencycompany) > 0) {
                $agencyname = $agencycompany[0]['company_name'];
                $agencyurl = $agencycompany[0]['subdomain'];
                if ($agencycompany[0]['domain'] != '' && $agencycompany[0]['status_domain'] == 'ssl_acquired') {
                    $agencyurl = $agencycompany[0]['domain'];
                    /** CHECK IF DOMAIN ALREADY POINTED TO OUR IP */
                    $ip = gethostbyname(trim($agencycompany[0]['domain']));
                    if ($ip == '157.230.213.72') {
                        $agencyurl = $agencycompany[0]['domain'];
                    }else{
                        $agencyurl = $agencycompany[0]['subdomain'];
                    }
                    /** CHECK IF DOMAIN ALREADY POINTED TO OUR IP */
                }
            }

            // $AdminDefault = $this->get_default_admin($request->companyID);
            // $AdminDefaultEmail = (isset($AdminDefault[0]['email']))?$AdminDefault[0]['email']:'';

            // $details = [
            //     'agencyname' => ucwords($agencyname),
            //     'agencyurl' => 'https://' . $agencyurl,
            //     'defaultadmin' => $AdminDefaultEmail,
            //     'username' => $request->ClientEmail,
            //     'name'  => $request->ClientFullName,
            //     'newpass' => $newpassword,
            // ];
            /** START NEW METHOD EMAIL */
            $from = [
                'address' => 'noreply@sitesettingsapi.com',
                'name' => 'Welcome',
                'replyto' => 'noreply@sitesettingsapi.com',
            ];

            $smtpusername = $this->set_smtp_email($company_parent);
            $emailtype = 'em_clientwelcomeemail';

            $customsetting = $this->getcompanysetting($company_parent,$emailtype);
            $chkcustomsetting = $customsetting;

            if ($customsetting == '') {
                $customsetting =  json_decode(json_encode($this->check_email_template($emailtype,$company_parent)));
            }

            $finalcontent = nl2br($this->filterCustomEmail($usr,$company_parent,$customsetting->content,$newpassword,$agencyurl));
            $finalsubject = $this->filterCustomEmail($usr,$company_parent,$customsetting->subject,$newpassword,$agencyurl);
            $finalfrom = $this->filterCustomEmail($usr,$company_parent,$customsetting->fromName,$newpassword,$agencyurl);

            $details = [
                'title' => ucwords($finalsubject),
                'content' => $finalcontent,
            ];
            
            $from = [
                'address' => (isset($customsetting->fromAddress) && $customsetting->fromAddress != '')?$customsetting->fromAddress:'noreply@sitesettingsapi.com',
                'name' => (isset($finalfrom) && $finalfrom != '')?$finalfrom:'Welcome',
                'replyto' => (isset($customsetting->fromReplyto) && $customsetting->fromReplyto != '')?$customsetting->fromReplyto:'support@sitesettingsapi.com',
            ];

            if ($smtpusername != "" && $chkcustomsetting == "") {
                $from = [
                    'address' => $smtpusername,
                    'name' => (isset($finalfrom) && $finalfrom != '')?$finalfrom:'Welcome',
                    'replyto' => $smtpusername,
                ];
            }
            
            if($inOpenApi === true) {
                if($isSendEmail === true) {
                    $this->send_email(array($request->ClientEmail),$from,ucwords($finalsubject),$details,array(),'emails.customemail',$company_parent,true);
                }
            } else {
                $this->send_email(array($request->ClientEmail),$from,ucwords($finalsubject),$details,array(),'emails.customemail',$company_parent,true);
            }
            /** START NEW METHOD EMAIL */
            //$this->send_email(array($request->ClientEmail),$from,ucwords($agencyname) . ' Account Setup',$details,array(),'emails.userinvitation',$request->companyID);
        }else if ($request->userType == 'userdownline') {
            $agencycompany = Company::select('company_name','domain','subdomain','status_domain')
                                ->where('id','=',$idsys)
                                ->get();

            // $agencyname = " ";
            // $defaultdomain = $this->getDefaultDomainEmail($idsys);
            // if (count($agencycompany) > 0) {
            //     $agencyname = $agencycompany[0]['company_name'] . " ";
            // }

            // $details = [
            //     'username' => $request->ClientEmail,
            //     'name'  => $request->ClientFullName,
            //     'newpass' => $newpassword,
            //     'domain' => $DownlineDomain,
            //     'subdomain' => $DownlineSubDomain,
            // ];

            // $from = [
            //     'address' => 'noreply@' . $defaultdomain,
            //     'name' => 'Welcome',
            //     'replyto' => 'support@' . $defaultdomain,
            // ];
            // $this->send_email(array($request->ClientEmail),$from, $agencyname . 'Agency Setup',$details,array(),'emails.agencysetup','');

            /** START NEW EMAIL METHOD */
            $agencyname = "";
            $agencyurl = "";
            if (count($agencycompany) > 0) {
                $agencyname = $agencycompany[0]['company_name'];
                $agencyurl = $agencycompany[0]['subdomain'];
                if ($agencycompany[0]['domain'] != '') {
                    $agencyurl = $agencycompany[0]['domain'];
                    /** CHECK IF DOMAIN ALREADY POINTED TO OUR IP */
                    $ip = gethostbyname(trim($agencycompany[0]['domain']));
                    if ($ip == '157.230.213.72' || $ip == '146.190.186.110' || $ip == '143.244.212.205') {
                        $agencyurl = $agencycompany[0]['domain'];
                    }else{
                        $agencyurl = $agencycompany[0]['subdomain'];
                    }
                    /** CHECK IF DOMAIN ALREADY POINTED TO OUR IP */
                }
            }

            if($DownlineSubDomain != "") {
                $agencyurl = 'https://' . $DownlineSubDomain;
            }
                

            $from = [
                'address' => 'noreply@sitesettingsapi.com',
                'name' => 'Welcome',
                'replyto' => 'noreply@sitesettingsapi.com',
            ];

            $smtpusername = $this->set_smtp_email($company_parent);
            $emailtype = 'em_agencywelcomeemail';

            $customsetting = $this->getcompanysetting($company_parent,$emailtype);
            $chkcustomsetting = $customsetting;

            if ($customsetting == '') {
                $customsetting =  json_decode(json_encode($this->check_email_template($emailtype,$company_parent)));
            }

            $finalcontent = nl2br($this->filterCustomEmail($usr,$company_parent,$customsetting->content,$newpassword,$agencyurl));
            $finalsubject = $this->filterCustomEmail($usr,$company_parent,$customsetting->subject,$newpassword,$agencyurl);
            $finalfrom = $this->filterCustomEmail($usr,$company_parent,$customsetting->fromName,$newpassword,$agencyurl);

            $details = [
                'title' => ucwords($finalsubject),
                'content' => $finalcontent,
            ];
            
            $from = [
                'address' => (isset($customsetting->fromAddress) && $customsetting->fromAddress != '')?$customsetting->fromAddress:'noreply@sitesettingsapi.com',
                'name' => (isset($finalfrom) && $finalfrom != '')?$finalfrom:'Welcome',
                'replyto' => (isset($customsetting->fromReplyto) && $customsetting->fromReplyto != '')?$customsetting->fromReplyto:'support@sitesettingsapi.com',
            ];

            if ($smtpusername != "" && $chkcustomsetting == "") {
                $from = [
                    'address' => $smtpusername,
                    'name' => (isset($finalfrom) && $finalfrom != '')?$finalfrom:'Welcome',
                    'replyto' => $smtpusername,
                ];
            }
            
            if($inOpenApi === true) {
                if($isSendEmail === true) {
                    $this->send_email(array($request->ClientEmail),$from,ucwords($finalsubject),$details,array(),'emails.customemail',$company_parent,true);
                }
            } else {
                $this->send_email(array($request->ClientEmail),$from,ucwords($finalsubject),$details,array(),'emails.customemail',$company_parent,true);
            }
            /** START NEW EMAIL METHOD */

            /** SEND EMAIL NOTIFICATION NEW REGISTER */
            $AccountType = 'Agency account';
             
            $tmp = User::select('email')->where('company_id','=',$ownedcompanyid)->where('active','T')
                    ->where(function($query) {
                        $query->where('user_type','=','user')
                                ->orWhere('user_type','=','userdownline');
                    })
                    ->where('isAdmin','=','T')
                    ->where('active','=','T')
                    ->orderByEncrypted('name')->get();
            $adminEmail = array();
            foreach($tmp as $ad) {
                array_push($adminEmail,$ad['email']);
            }

            $AdminDefault = $this->get_default_admin($ownedcompanyid);
            $AdminDefaultEmail = (isset($AdminDefault[0]['email']))?$AdminDefault[0]['email']:'';
            $defaultdomain = $this->getDefaultDomainEmail($ownedcompanyid);


            $sales_agent = '';
            if (trim($salesRep) != "") {
            $agent = User::select('name')->where('id', $salesRep)->first();
            $sales_agent = $agent->name;
            }
    
            $referral_agent = '';
            if (trim($salesRef) != "") {
            $agent = User::select('name')->where('id', $salesRef)->first();
            $referral_agent = $agent->name;
            }
    
            $account_executive = '';
            if (trim($salesAE) != "") {
            $agent = User::select('name')->where('id', $salesAE)->first();
            $account_executive = $agent->name;
            }
    
            $fullNameParts = explode(' ', trim($request->ClientFullName));

            $details = [
            'email' => strtolower($request->ClientEmail),
            'name'  => $request->ClientFullName,
            'first_name' => $fullNameParts[0], 
            'last_name' => end($fullNameParts),
            'phone' => $request->ClientPhone,
            'business_name' => $request->ClientCompanyName,
            'sales_agent' => $sales_agent,
            'referral_agent' => $referral_agent,
            'account_executive' => $account_executive,
            'domain' => $DownlineSubDomain,
            'accounttype' => $AccountType,
            'defaultadmin' => $AdminDefaultEmail,
            ];

            $from = [
            'address' => 'noreply@' . $defaultdomain,
            'name' => 'New Account Registered',
            'replyto' => 'support@' . $defaultdomain,
            ];

            if($inOpenApi === true) {
                if($isSendEmail === true) {
                    $this->send_email($adminEmail,$from,'New ' . $AccountType . ' Registered',$details,array(),'emails.adminnewaccountregister',$ownedcompanyid,true);
                }
            } else {
                $this->send_email($adminEmail,$from,'New ' . $AccountType . ' Registered',$details,array(),'emails.adminnewaccountregister',$ownedcompanyid,true);
            }
            /** SEND EMAIL NOTIFICATION NEW REGISTER */

            

        }else if($request->userType == 'user') {
            $agencycompany = Company::select('company_name','domain','subdomain','status_domain')
                                        ->where('id','=',$request->companyID)
                                        ->get();
            $agencyname = "";
            $agencyurl = "";
            if (count($agencycompany) > 0) {
                $agencyname = $agencycompany[0]['company_name'];
                $agencyurl = $agencycompany[0]['subdomain'];
                if ($agencycompany[0]['domain'] != '' && $agencycompany[0]['status_domain'] == 'ssl_acquired') {
                    $agencyurl = $agencycompany[0]['domain'];
                }
            }

            $AdminDefault = $this->get_default_admin($request->companyID);
            $AdminDefaultEmail = (isset($AdminDefault[0]['email']))?$AdminDefault[0]['email']:'';

            $defaultdomain = $this->getDefaultDomainEmail($request->companyID);

            $details = [
                'defaultadmin' => $AdminDefaultEmail,
                'agencyname' => ucwords($agencyname),
                'agencyurl' => 'https://' . $agencyurl,
                'username' => $request->ClientEmail,
                'name'  => $request->ClientFullName,
                'newpass' => $newpassword,
            ];

            $from = [
                'address' => 'noreply@' . $defaultdomain,
                'name' => 'Welcome',
                'replyto' => 'support@' . $defaultdomain,
            ];
            
            $this->send_email(array($request->ClientEmail),$from,ucwords($agencyname) . ' Administrator Setup',$details,array(),'emails.admininvitation',$request->companyID,true);
        }else if($request->userType == 'sales') {
            $agencycompany = Company::select('company_name','domain','subdomain','status_domain')
                                        ->where('id','=',$request->companyID)
                                        ->get();
            $agencyname = "";
            $agencyurl = "";
            if (count($agencycompany) > 0) {
                $agencyname = $agencycompany[0]['company_name'];
                $agencyurl = $agencycompany[0]['subdomain'];
                if ($agencycompany[0]['domain'] != '' && $agencycompany[0]['status_domain'] == 'ssl_acquired') {
                    $agencyurl = $agencycompany[0]['domain'];
                }
            }

            $AdminDefault = $this->get_default_admin($request->companyID);
            $AdminDefaultEmail = (isset($AdminDefault[0]['email']))?$AdminDefault[0]['email']:'';

            $defaultdomain = $this->getDefaultDomainEmail($request->companyID);

            $details = [
                'defaultadmin' => $AdminDefaultEmail,
                'agencyname' => ucwords($agencyname),
                'agencyurl' => 'https://' . $agencyurl,
                'username' => $request->ClientEmail,
                'name'  => $request->ClientFullName,
                'newpass' => $newpassword,
            ];

            $from = [
                'address' => 'noreply@' . $defaultdomain,
                'name' => 'Welcome',
                'replyto' => 'support@' . $defaultdomain,
            ];

            if($inOpenApi === true) {
                if($isSendEmail === true) {
                    $this->send_email(array($request->ClientEmail),$from,ucwords($agencyname) . ' Sales Account Setup',$details,array(),'emails.salesinvitation',$request->companyID,true);
                }
            } else {
                $this->send_email(array($request->ClientEmail),$from,ucwords($agencyname) . ' Sales Account Setup',$details,array(),'emails.salesinvitation',$request->companyID,true);
            }
        }
        
        /** EMAIL SENT TO CLIENTS */

        if($request->userType == 'client') {
            $temp = User::select('users.*','companies.company_name')->join('companies','companies.id','=','users.company_id')->where('users.id',$usrID)->get();
        }else if($request->userType == 'userdownline') {
            $temp =  User::select('users.*','companies.company_name')->join('companies','companies.id','=','users.company_id')->where('users.id',$usrID)->get();
            if (trim($salesRep) != '' || trim($salesAE) != '' || trim($salesRef) != '') {
                /** CHECK SALES  */
                $chksales = User::select('users.id','users.name','company_sales.sales_title')
                                    ->join('company_sales','users.id','=','company_sales.sales_id')
                                    ->where('company_sales.company_id','=',$temp[0]['company_id'])
                                    ->where('users.active','=','T')
                                    ->get();

                $compsalesrepID = "";
                $compsalesrep = "";
                $compaccountexecutive = "";
                $compaccountexecutiveID = "";
                $compaccountref = "";
                $compaccountrefID = "";

                foreach($chksales as $sl) {
                    if ($sl['sales_title'] == "Sales Representative") {
                        $compsalesrepID = $sl['id'];
                        $compsalesrep = $sl['name'];
                    }
                    if ($sl['sales_title'] == "Account Executive") {
                        $compaccountexecutiveID = $sl['id'];
                        $compaccountexecutive = $sl['name'];
                    }

                    if ($sl['sales_title'] == "Account Referral") {
                        $compaccountrefID = $sl['id'];
                        $compaccountref = $sl['name'];
                    }
                }
                foreach($temp as $tmp) {
                    $tmp['salesrepid'] = $compsalesrepID;
                    $tmp['salesrep'] = $compsalesrep;
                    $tmp['accountexecutiveid'] = $compaccountexecutiveID;
                    $tmp['accountexecutive'] = $compaccountexecutive;
                    $tmp['accountrefid'] = $compaccountrefID;
                    $tmp['accountref'] = $compaccountref;
                }
                /** CHECK SALES */
            }
        }else if($request->userType == 'user') {
            $temp = User::where('users.id',$usrID)->where('active','T')->where('user_type','=','user')->get();
        }else if($request->userType == 'sales') {
            $temp = User::where('users.id',$usrID)->where('active','T')->where('user_type','=','sales')->get();
        }

        if ($request->userType == 'user' && $assign_admin_to_all_campaign) {
            $param = [
                'admin_ids' => [$usrID],
                'company_id' => $request->companyID,
                'action' => 'add',
            ];
            //ADD
            
            AdminToCampaignJob::dispatch($param)->onQueue('admin_to_campaign');
            $update_status = User::where('id',$usrID)->update(['admin_assign_status' => 'queue']);
        }

        //  CREATE GHL AGENCY's CONTACT IN ROOT
        if ($request->userType == 'userdownline') {

            $root_ghl_setting = $this->getcompanysetting($idsys,'rootghlapikey');

            if (isset($root_ghl_setting->api_key) && !empty($root_ghl_setting->api_key) ) {

                try {
                    $api_key = $root_ghl_setting->api_key;
                    $http = new \GuzzleHttp\Client;

                    $fullName = trim($request->ClientFullName);
                    $nameParts = preg_split('/\s+/', $fullName);

                    if (count($nameParts) === 1) {
                        $FirstName = $nameParts[0];
                        $LastName = '';
                    } else {
                        $LastName = array_pop($nameParts);
                        $FirstName = implode(' ', $nameParts);
                    }

                    $source = "Create Agency List (reguler save)";
                    $tags = ['account-created'];
                    if ($usr->is_onboard_charged === 1) {
                        $tags[] = 'agreement-signed';
                        $source = "Create Agency List (save & charge)";
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
                            "name" => $request->ClientFullName,
                            "email" => strtolower($request->ClientEmail),
                            "phone" => $request->ClientPhone,
                            "country" => "US",
                            "source" => $source,
                            "tags" => $tags,
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
                    Log::warning('ghl_createContact (' . strtolower($request->email) . ') error add msg :' . $e);

                    Log::info([
                        'success' => false,
                        'error' => $decodedError['msg'],
                    ]);
                }
            }

        }
        //  CREATE GHL AGENCY's CONTACT IN ROOT

        /* CREATE CUSTOM MENU LINK GOHIGHLEVEL FOR CLIENT */
        $isCreateIframeClientGHLV2 = false;
        $isSuccessCreateIframeClientGHLV2 = false;
        if ($request->userType == 'client') {
            // info(__FUNCTION__, ['usr' => $usr]);
            $companyAgencyId = $usr->company_parent ?? null;
            $ghlV2AgencyConnected = Company::join('users', 'users.company_id', '=', 'companies.id')
                                           ->where('users.company_id', $companyAgencyId)
                                           ->where('users.user_type', 'userdownline')
                                           ->where('users.active', 'T')
                                           ->where(function ($query) {
                                                $query->whereNotNull('companies.ghl_company_id')->where('companies.ghl_company_id', '<>', '')
                                                      ->whereNotNull('companies.ghl_tokens')->where('companies.ghl_tokens', '<>', '')
                                                      ->whereNotNull('companies.ghl_custom_menus')->where('companies.ghl_custom_menus', '<>', '')
                                                      ->whereNotNull('companies.ghl_credentials_id')->where('companies.ghl_credentials_id', '<>', '');
                                           })
                                           ->exists();
            
            // info(__FUNCTION__, ['ghlV2AgencyConnected' => $ghlV2AgencyConnected]);
            $location_id = $request->location_id ?? '';
            if($ghlV2AgencyConnected === true && !empty($location_id) && trim($location_id) != '') {
                $companyClientId = $usr->company_id ?? null;
                $custom_menu_name = $request->custom_menu_name ?? $request->ClientCompanyName;
                $ip_login = $request->ip_login ?? "";
                // info(__FUNCTION__, ['companyClientId' => $companyClientId, 'companyAgencyId' => $companyAgencyId, 'custom_menu_name' => $custom_menu_name, 'ip_login' => $ip_login]);

                $dataRequest = new Request([
                    'company_id' => $companyClientId,
                    'company_parent' => $companyAgencyId,
                    'user_ip' => $ip_login,
                    'location_id' => $location_id,
                    'custom_menu_name' => $custom_menu_name,
                ]);
                $integrationController = App::make(IntegrationController::class);
                $response = $integrationController->gohighlevelv2CreateIframeClient($dataRequest)->getData();   
                // info(__FUNCTION__, ['response' => $response]);
                
                $isCreateIframeClientGHLV2 = true;
                if(($response->result ?? '') != 'failed')
                {
                    $isSuccessCreateIframeClientGHLV2 = true;
                }
            }
        }
        /* CREATE CUSTOM MENU LINK GOHIGHLEVEL FOR CLIENT */

        return response()->json(array('result'=>'success','message'=>'','data'=>$temp,'isCreateIframeClientGHLV2'=>$isCreateIframeClientGHLV2,'isSuccessCreateIframeClientGHLV2'=>$isSuccessCreateIframeClientGHLV2));

    }

    public function remove(Request $request) {
        $UserID = (isset($request->UserID))?$request->UserID:'';
        $CompanyID = (isset($request->CompanyID))?$request->CompanyID:'';
        $params = json_decode($request->input('params', '{}'), true);
        $user = User::find($UserID);
        if(empty($user)) {
            return response()->json(array('result'=>'failed','message'=>"User Not Found"));
            exit;die();
        }
        //$user->active = 'F';
        //$user->save();
        $inOpenApi = isset($request->inOpenApi)?$request->inOpenApi:false; 
        $idLogin = "";
        $companyLogin = "";
        $isRoot = "";

        if($inOpenApi !== true) {
            $idLogin = auth()->user()->id;
            $userLogin = User::find($idLogin);
            $companyLogin = $userLogin->company_id;
            $isRoot = $userLogin->company_id == $userLogin->company_root_id;
        }
        
        /** CHECK RUN OR PAUSE CAMPAIGN THAT STILL ACTIVE */
        $http = new \GuzzleHttp\Client;
        $appkey = config('services.trysera.api_id');
        $domain = config('services.trysera.domain');

        if ($user->user_type == "client") {
            $self_delete = ($user->id == optional(auth()->user())->id) ? true : false;
            if ($inOpenApi || $isRoot || ($companyLogin == $user->company_parent) || $self_delete) {
            $chkinvalidusr = LeadspeekUser::select('leadspeek_users.id','leadspeek_users.user_id','leadspeek_users.leadspeek_api_id','leadspeek_users.leadspeek_organizationid','leadspeek_users.leadspeek_campaignsid','leadspeek_users.campaign_name','leadspeek_users.active','leadspeek_users.disabled','leadspeek_users.active_user',
                                            'companies.id as company_id','companies.company_name','leadspeek_users.trysera')
                                        ->join('users','leadspeek_users.user_id','=','users.id')
                                        ->join('companies','users.company_id','=','companies.id')
                                        ->where('users.user_type','=','client')
                                        ->where('leadspeek_users.user_id','=',$UserID)
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
                                            })
                                            ->orWhere(function($query){
                                                $query->where('leadspeek_users.active','=','F')
                                                    ->where('leadspeek_users.disabled','=','T')
                                                    ->where('leadspeek_users.active_user','=','T');
                                            });
                                        })->get();
            if (count($chkinvalidusr) > 0) {
                return response()->json(array('result'=>'failed','message'=>"There are active campaigns still running for this client, Please stop all campaigns prior to deleting."));
                exit;die();

                foreach($chkinvalidusr as $inv) {
                    /** MAKE IT THE CAMPAIGN PAUSED AND STOP THE SIMPLIFI AND TRYSERA */
                    $updateleadusr = LeadspeekUser::find($inv['id']);
                    $updateleadusr->activex = 'F';
                    $updateleadusr->disabled = 'T';
                    $updateleadusr->active_user = 'F';
                    $updateleadusr->save();
                    
                    /** DISABLED THE TRYSERA ALSO MAKE IT IN ACTIVE */
                    $_company_id = $inv['company_id'];
                    $_user_id = $inv['user_id'];
                    $_lp_user_id = $inv['id'];
                    $_leadspeek_api_id = $inv['leadspeek_api_id'];
                    $organizationid = $inv['leadspeek_organizationid'];
                    $campaignsid = $inv['leadspeek_campaignsid'];

                    /** GET COMPANY NAME AND CUSTOM ID */
                    $tryseraCustomID =  '3_' . $_company_id . '00' . $_user_id . '_' . $_lp_user_id . '_' . date('His');
                    /** GET COMPANY NAME AND CUSTOM ID */

                    $campaignName = '';
                    if (isset($inv['campaign_name']) && trim($inv['campaign_name']) != '') {
                        $campaignName = ' - ' . str_replace($_leadspeek_api_id,'',$inv['campaign_name']);
                    }

                    $company_name = str_replace($_leadspeek_api_id,'',$inv['company_name']) . $campaignName;

                    if ($inv['trysera'] == 'T') {
                        $pauseApiURL =  config('services.trysera.endpoint') . 'subclients/' . $inv['leadspeek_api_id'];
                        $pauseoptions = [
                            'headers' => [
                                'Authorization' => 'Bearer ' . $appkey,
                            ],
                            'json' => [
                                "SubClient" => [
                                    "ID" => $inv['leadspeek_api_id'],
                                    "Name" => trim($company_name),
                                    "CustomID" => $tryseraCustomID ,
                                    "Active" => false
                                ]       
                            ]
                        ]; 
                        $pauseresponse = $http->put($pauseApiURL,$pauseoptions);
                        $result =  json_decode($pauseresponse->getBody());
                    }

                    /** DISABLED THE TRYSERA ALSO MAKE IT IN ACTIVE */

                    if ($organizationid != '' && $campaignsid != '') {
                        $this->startPause_campaign($organizationid,$campaignsid,'stop');
                    }
                
                }

            }else{
                $user->active = 'F';
                $user->save();

                /* USER LOG */
                $login_id = optional(auth()->user())->id;
                $ipAddress = ($request->header('Clientip') !== null)?$request->header('Clientip'):$request->ip();
                $company = Company::find($user->company_id);
                $company_name = $company ? $company->company_name : '';
                $description = "Client deleted their own account. Name: {$user->name} | Email: {$user->email} | Company Name: {$company_name} | Company ID: {$user->company_id}";
                $user_id = $user->id;
                $this->logUserAction($login_id,'Client Deleted Account',$description,$ipAddress,$user_id);
                /* USER LOG */
                
                /* SEND EMAIL TO DEFAULT ADMIN */
                if ($user->company_parent && $user->company_parent != '') {
                    $defaultAdmin = $this->get_default_admin($user->company_parent);
                    if ($defaultAdmin && count($defaultAdmin) > 0 && isset($defaultAdmin[0]['email']) && trim($defaultAdmin[0]['email']) != '') {
                        $adminEmail = $defaultAdmin[0]['email'];
                        
                        // Get agency name from company_parent
                        $agencyCompany = Company::find($user->company_parent);
                        $agencyName = $agencyCompany ? $agencyCompany->company_name : 'Agency';
                        
                        // Get default domain email for company_parent
                        $defaultdomain = $this->getDefaultDomainEmail($user->company_parent);
                        
                        $from = [
                            'address' => 'noreply@' . $defaultdomain,
                            'name' => 'System Notification',
                            'replyto' => 'support@' . $defaultdomain,
                        ];
                        
                        $title = 'Client Account Deletion Notification';
                        $dateTime = date('m-d-Y');
                        
                        $details = [
                            'agency_name' => $agencyName,
                            'client_name' => $user->name,
                            'client_email' => $user->email,
                            'date_time' => $dateTime,
                        ];
                        
                        try {
                            $this->send_email([$adminEmail], $from, $title, $details, [], 'emails.clientdeletionnotification', $user->company_parent, true);
                        } catch (\Throwable $e) {
                            // Log error but don't fail the deletion
                            Log::error("Error sending email notification for deleted client: " . $e->getMessage());
                        }
                    }
                }
                /* SEND EMAIL TO DEFAULT ADMIN */
                
                return response()->json(array('result'=>'success'));
            }
            } else {
                return response()->json(array('result'=>'failed','message'=>"You dont have access to remove this User"));
            }

        }else if($user->user_type == "userdownline") {
            if ($inOpenApi || $isRoot || ($companyLogin == $user->company_parent)) {
            $chkNotActiveAgency =  User::select('company_id')
                                        ->where('id','=',$UserID)
                                        ->where('user_type','=','userdownline')
                                        ->get();;

            if(count($chkNotActiveAgency) > 0) {
                foreach($chkNotActiveAgency as $agency) {
                    $chkinvalidusr = LeadspeekUser::select('leadspeek_users.id','leadspeek_users.user_id','leadspeek_users.leadspeek_api_id','leadspeek_users.leadspeek_organizationid','leadspeek_users.leadspeek_campaignsid','leadspeek_users.campaign_name','leadspeek_users.active','leadspeek_users.disabled','leadspeek_users.active_user',
                                        'companies.id as company_id','companies.company_name','leadspeek_users.trysera','leadspeek_users.leadspeek_type')
                                    ->join('users','leadspeek_users.user_id','=','users.id')
                                    ->join('companies','users.company_id','=','companies.id')
                                    ->where('users.user_type','=','client')
                                    ->where('users.active','=','T')
                                    ->where('users.company_parent','=',$agency['company_id'])
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
                                        })
                                        ->orWhere(function($query){
                                            $query->where('leadspeek_users.active','=','F')
                                                ->where('leadspeek_users.disabled','=','T')
                                                ->where('leadspeek_users.active_user','=','T');
                                        });
                                    })->get();

                                    if (count($chkinvalidusr) > 0) {
                                        return response()->json(array('result'=>'failed','message'=>"There are some agency's client still have campaign running, please stop the campaign before remove the client"));
                                        exit;die();

                                        foreach($chkinvalidusr as $inv) {
                                            /** MAKE IT THE CAMPAIGN PAUSED AND STOP THE SIMPLIFI AND TRYSERA */
                                            $updateleadusr = LeadspeekUser::find($inv['id']);
                                            $updateleadusr->activex = 'F';
                                            $updateleadusr->disabled = 'T';
                                            $updateleadusr->active_user = 'F';
                                            $updateleadusr->save();
                            
                                             /** DISABLED THE TRYSERA ALSO MAKE IT IN ACTIVE */
                                             $_company_id = $inv['company_id'];
                                             $_user_id = $inv['user_id'];
                                             $_lp_user_id = $inv['id'];
                                             $_leadspeek_api_id = $inv['leadspeek_api_id'];
                                             $organizationid = $inv['leadspeek_organizationid'];
                                             $campaignsid = $inv['leadspeek_campaignsid'];
                                             $tryseramethod = (isset($inv['trysera']) && $inv['trysera'] == "T")?true:false;

                                             /** DISABLED CLIENT */
                                                $updateUser = User::find($_user_id);
                                                $updateUser->active = "F";
                                                $updateUser->save();
                                             /** DISABLED CLIENT */
                            
                                             /** GET COMPANY NAME AND CUSTOM ID */
                                             $tryseraCustomID =  '3_' . $_company_id . '00' . $_user_id . '_' . $_lp_user_id . '_' . date('His');
                                             /** GET COMPANY NAME AND CUSTOM ID */
                            
                                             $campaignName = '';
                                             if (isset($inv['campaign_name']) && trim($inv['campaign_name']) != '') {
                                                 $campaignName = ' - ' . str_replace($_leadspeek_api_id,'',$inv['campaign_name']);
                                             }
                            
                                             $company_name = str_replace($_leadspeek_api_id,'',$inv['company_name']) . $campaignName;
                            
                                             if ($tryseramethod) {
                                                $pauseApiURL =  config('services.trysera.endpoint') . 'subclients/' . $inv['leadspeek_api_id'];
                                                $pauseoptions = [
                                                    'headers' => [
                                                        'Authorization' => 'Bearer ' . $appkey,
                                                    ],
                                                    'json' => [
                                                        "SubClient" => [
                                                            "ID" => $inv['leadspeek_api_id'],
                                                            "Name" => trim($company_name),
                                                            "CustomID" => $tryseraCustomID ,
                                                            "Active" => false
                                                        ]       
                                                    ]
                                                ]; 
                                                $pauseresponse = $http->put($pauseApiURL,$pauseoptions);
                                                $result =  json_decode($pauseresponse->getBody());
                                             }
                            
                                             /** DISABLED THE TRYSERA ALSO MAKE IT IN ACTIVE */
                                             
                                             /** ACTIVATE CAMPAIGN SIMPLIFI */
                                             if ($organizationid != '' && $campaignsid != '' && $inv['leadspeek_type'] == "locator") {
                                                 $camp = $this->startPause_campaign($organizationid,$campaignsid,'stop');
                                             }
                                             /** ACTIVATE CAMPAIGN SIMPLIFI */
                            
                                            /** MAKE IT THE CAMPAIGN PAUSED AND STOP THE SIMPLIFI AND TRYSERA */
                            
                                        }
                                    }else{
                                        $users_under_agency = User::select('id','active')
                                        ->where('active', 'T')
                                        ->where(function($query) use($user) {
                                            $query->where(function($query) use($user) {
                                                $query->where('company_parent',$user->company_id);
                                            })->orWhere(function($query) use($user){
                                                $query->where('company_id',$user->company_id)
                                                    ->where('user_type', 'user');
                                            });
                                         })->get();

                                        if (!empty($users_under_agency)) {
                                            foreach ($users_under_agency as $value) {
                                            $value->active = 'F';
                                            $value->save();
                                            }
                                        }

                                        $user->active = 'F';
                                        $user->save();
                                        
                                        /* USER LOG */
                                        $login_id = optional(auth()->user())->id;
                                        $ipAddress = ($request->header('Clientip') !== null)?$request->header('Clientip'):$request->ip();
                                        $company = Company::find($user->company_id);
                                        $company_name = $company ? $company->company_name : '';
                                        $description = "Agency account deleted. Name: {$user->name} | Email: {$user->email} | Company Name: {$company_name} | Company ID: {$user->company_id}";
                                        $user_id = $user->id;
                                        $this->logUserAction($login_id,'Agency Account Deleted',$description,$ipAddress,$user_id);
                                        /* USER LOG */
                                        
                                        return response()->json(array('result'=>'success'));
                                    }
                                    

                }
            }
            } else {
                return response()->json(array('result'=>'failed','message'=>"You dont have access to remove this User"));
            }

        }else if($user->user_type == "user" || $user->user_type == "sales") {
            if ($inOpenApi || $isRoot || ($companyLogin == $user->company_id)) {

            $user->active = 'F';
            $user->save();

                        
            /* USER LOG */
            $login_id = optional(auth()->user())->id;
            $ipAddress = ($request->header('Clientip') !== null)?$request->header('Clientip'):$request->ip();
            $company = Company::find($user->company_id);
            $company_name = $company ? $company->company_name : '';
            $action_type = ($user->user_type == "sales") ? "Sales" : "Admin";
            $description = "{$action_type} account deleted. Name: {$user->name} | Email: {$user->email} | Company Name: {$company_name} | Company ID: {$user->company_id}";
            $user_id = $user->id;
            $this->logUserAction($login_id,"{$action_type} Account Deleted",$description,$ipAddress,$user_id);
            /* USER LOG */
            

            if (isset($params['admin_replacement_ids']) && is_array($params['admin_replacement_ids']) && !empty($params['admin_replacement_ids'])) {
                $param = [
                    'admin_ids' => [$user->id],
                    'company_id' => $user->company_id,
                    'action' => 'remove',
                    'admin_replacement_ids' => $params['admin_replacement_ids'],
                ];
                //ADD

                AdminToCampaignJob::dispatch($param)->onQueue('admin_to_campaign');
                $admin_to_update = array_unique(array_merge([$user->id],$params['admin_replacement_ids']));
                $update_status = User::whereIn('id',$admin_to_update)->update(['admin_assign_status' => 'queue']);
            }
            return response()->json(array('result'=>'success'));
            } else {
                return response()->json(array('result'=>'failed','message'=>"You dont have access to remove this User"));
            }
        }
        /** CHECK RUN OR PAUSE CAMPAIGN THAT STILL ACTIVE */
    }

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

    private function createOrganization($organizationName,$parentOrganization = "",$customID="") {
        $http = new \GuzzleHttp\Client;

        $appkey = config('services.simplifi.app_key');
        $usrkey = config('services.simplifi.usr_key');
        $apiURL = config('services.simplifi.endpoint') . "organizations";
        
        $parentID = (trim($parentOrganization) == "")?config('services.sifidefaultorganization.organizationid'):trim($parentOrganization);

        $organizationName = $this->makeSafeTitleName($organizationName);

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
            $this->send_email(array('serverlogs@sitesettingsapi.com'),$from,'Error Log SIFI Create Organization :' . $organizationName . ' parent ID:' . $parentID . '(Apps DATA - createOrganization - ConfigurationCont - L1940) ',$details,array(),'emails.tryseramatcherrorlog','');

            return "";
        }

    }

    public function testemail(Request $request) {

        // Validate the request
        $request->validate([
            'fromAddress' => 'required',
            'fromName' => 'required|string',
            'fromReplyto' => 'required|email',
            'subject' => 'required|string',
            'content' => 'required|string',
            'testEmailAddress' => 'required|email',
            'companyID' => 'required|integer'
        ]);

        // Extract data from request
        $fromAddress = $request->fromAddress;
        $fromName = $request->fromName;
        $fromReplyto = $request->fromReplyto;
        $subject = $request->subject;
        $content = $request->content;
        $testEmailAddress = $request->testEmailAddress;
        $companyID = $request->companyID;
        $userType = $request->userType;

        // Setup SMTP configuration based on companyID
        $smtpusername = "";
        if ($userType == 'userdownline' || $userType == 'user') {
            $smtpusername = $this->set_smtp_email($companyID);
        }
        // Prepare email details
        $details = [
            'title' => ucwords($subject),
            'content' => nl2br($content),
        ];
    
        $from = [
            'address' => $fromAddress,
            'name' => $fromName,
            'replyto' => $fromReplyto,
        ];
            // Override 'from' address if SMTP username is set
        if ($smtpusername != "") {
            $from['address'] = $smtpusername;
            $from['replyto'] = $smtpusername;
        }
    
        // Send the email
        try {
            $this->send_email([$testEmailAddress], $from, ucwords($subject), $details, [], 'emails.customemail');
            return response()->json('Test email sent successfully', 200);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Failed to send test email', 'error' => $e->getMessage()], 500);
        }
    }


    public function updategeneralsetting(Request $request) {
        $companyID = (isset($request->companyID))?$request->companyID:'';
        $actionType = (isset($request->actionType))?$request->actionType:'';

        $sidebarcolor = (isset($request->sidebarcolor))?$request->sidebarcolor:'';
        $templatecolor = (isset($request->templatecolor))?$request->templatecolor:'';
        $boxcolor = (isset($request->boxcolor))?$request->boxcolor:'';
        $textcolor = (isset($request->textcolor))?$request->textcolor:'';
        $linkcolor = (isset($request->linkcolor))?$request->linkcolor:'';

        $fonttheme = (isset($request->fonttheme))?$request->fonttheme:'';

        $paymenttermDefault = (isset($request->paymenttermDefault))?$request->paymenttermDefault:'';

        $comset_name = (isset($request->comsetname))?$request->comsetname:'';
        $comset_val = (isset($request->comsetval))?$request->comsetval:'';

        if ($actionType == 'colortheme') {
            $company = Company::find($companyID);
            $company->sidebar_bgcolor = $sidebarcolor;
            $company->template_bgcolor = $templatecolor;
            $company->box_bgcolor = $boxcolor;
            $company->text_color = $textcolor;
            $company->link_color = $linkcolor;
            $company->save();
        }else if ($actionType == 'agencyClientDeletedAccount') {
            $downline = User::where('company_id', $companyID)->where('user_type', 'userdownline')->where('active','T')->first();
            if ($downline) {
                $downline->enabled_client_deleted_account = ($comset_val == 'true') ? 'T' : 'F';
                $downline->save();
            }
        }else if ($actionType == 'fonttheme') {
            $company = Company::find($companyID);
            $company->font_theme = $fonttheme;
            $company->save();
        }else if ($actionType == 'paymenttermDefault') {
            $company = Company::find($companyID);
            $company->paymentterm_default = $paymenttermDefault;
            $company->save();
        }else if ($actionType == 'custommenumodule' || $actionType == 'customsmtpmodule') {
            $listemailtemplate = ['em_forgetpassword','em_clientwelcomeemail','em_agencywelcomeemail','em_campaigncreated','em_billingunsuccessful','em_archivecampaign','em_prepaidtopuptwodaylimitclient'];
            $companysetting = CompanySetting::where('company_id',$companyID)->whereEncrypted('setting_name',$comset_name)->get();

            if($actionType == 'custommenumodule'){
                $localUrl = $comset_val['local']['url'];
                $locatorUrl = $comset_val['locator']['url'];
                $enhanceUrl = $comset_val['enhance']['url'] ?? null;
                $b2bUrl = $comset_val['b2b']['url'] ?? null;

                if (($localUrl == $locatorUrl) || ($localUrl == $enhanceUrl && $enhanceUrl != null) || ($localUrl == $b2bUrl && $b2bUrl != null) || ($locatorUrl == $enhanceUrl && $enhanceUrl != null) || ($locatorUrl == $b2bUrl && $b2bUrl != null) || ($enhanceUrl == $b2bUrl && $enhanceUrl != null && $b2bUrl != null)){
                    return response()->json(["result" => "failed", "message" => "Each product's module URL must be unique. Please update it with a unique URL."], 400);
                }
            }else if(in_array($comset_name, $listemailtemplate)) {
                $data = [
                    'subject' => isset($comset_val['subject'])?$comset_val['subject']:'',
                    'content' => isset($comset_val['content'])?$comset_val['content']:'',
                    'fromAddress' => isset($comset_val['fromAddress'])?$comset_val['fromAddress']:'',
                    'fromName' => isset($comset_val['fromName'])?$comset_val['fromName']:'',
                    'fromReplyto' => isset($comset_val['fromReplyto'])?$comset_val['fromReplyto']:'',
                ];
                $rule = [
                    'subject' => ['required'],
                    'content' => ['required'],
                    'fromAddress' => ['required','email'],
                    'fromName' => ['required'],
                    'fromReplyto' => ['required','email'],
                ];
                $validator = Validator::make($data,$rule);
                if($validator->fails()) {
                    return response()->json([
                        'result' => 'error', 
                        'message' => $validator->messages(),
                    ], 422);
                }
            }

            if (count($companysetting) > 0) {
                $updatesetting = CompanySetting::find($companysetting[0]['id']);
                $updatesetting->setting_value = json_encode($comset_val);
                $updatesetting->save();
            }else{
                $createsetting = CompanySetting::create([
                    'company_id' => $companyID,
                    'setting_name' => $comset_name,
                    'setting_value' => json_encode($comset_val),
                ]);
            }   
        }else if ($actionType == 'agencydefaultmodules' || $actionType == 'rootdefaultmodules') {
            $clientsidebar_setting = CompanySetting::where('company_id', $request->companyID)
            ->whereEncrypted('setting_name', $actionType)
            ->first();

            $clientsidebar = [];

            if (!empty($request->comsetval)) {
                $clientsidebar = [
                "DefaultModules" => $request->comsetval,
                ];
            }

            if ($clientsidebar_setting && $request->comsetval != []) {
                $clientsidebar_setting->setting_value = json_encode($clientsidebar);
                $clientsidebar_setting->save();
            } else {
                if ($clientsidebar != []) {
                    $createsetting = CompanySetting::create([
                    'company_id' => $request->companyID,
                    'setting_name' => $actionType,
                    'setting_value' => json_encode($clientsidebar),
                    ]);
                }
            }
        }

        //$a = CompanySetting::where('id',1)->get();
        //$jr = json_decode($a[0]['setting_value']);
        return response()->json(array('result'=>'success'));
    }

    public function getDefaultPrice($companyID,$settingname,$idSys) {
            /* GET CLIENT MIN LEAD DAYS */
            $rootSetting = $this->getcompanysetting($idSys, 'rootsetting');
            $clientMinLeadDayEnhance = (isset($rootSetting->clientminleadday))?$rootSetting->clientminleadday:"";
            /* GET CLIENT MIN LEAD DAYS */
            
            $getcompanysetting = CompanySetting::where('company_id',$companyID)->whereEncrypted('setting_name',$settingname)->get();
            $companysetting = "";
            if (count($getcompanysetting) > 0) { // jika clientdefaultprice ada pakai ini
                $companysetting = json_decode($getcompanysetting[0]['setting_value']);
                $clientdefaultprice = true;

                if ($companysetting != "") {
                    $userClient = User::select('company_parent')->where('company_id','=',$companyID)->where('user_type','=','client')->first();
                    $companyParentID = isset($userClient->company_parent)?$userClient->company_parent:'';
                    $agency_default_price = $this->getcompanysetting($companyParentID,'agencydefaultprice');

                    $comset_val = $this->getcompanysetting($idSys,'rootagencydefaultprice');

                    /* ENHANCE */
                    if(!isset($companysetting->enhance)) {
                        $companysetting->enhance = new stdClass();
                        $companysetting->enhance->Weekly = new stdClass();
                        $companysetting->enhance->Monthly = new stdClass();
                        $companysetting->enhance->OneTime = new stdClass();
                        $companysetting->enhance->Prepaid = new stdClass();
    
                        /* WEEKLY */
                        $companysetting->enhance->Weekly->EnhanceCostperlead = isset($agency_default_price->enhance->Weekly->EnhanceCostperlead) ? $agency_default_price->enhance->Weekly->EnhanceCostperlead : $comset_val->enhance->Weekly->EnhanceCostperlead;
                        $companysetting->enhance->Weekly->EnhanceMinCostMonth = isset($agency_default_price->enhance->Weekly->EnhanceMinCostMonth) ? $agency_default_price->enhance->Weekly->EnhanceMinCostMonth : $comset_val->enhance->Weekly->EnhanceMinCostMonth;
                        $companysetting->enhance->Weekly->EnhancePlatformFee = isset($agency_default_price->enhance->Weekly->EnhancePlatformFee) ? $agency_default_price->enhance->Weekly->EnhancePlatformFee : $comset_val->enhance->Weekly->EnhancePlatformFee;
                        /* WEEKLY */
                        
                        /* MONTHLY */
                        $companysetting->enhance->Monthly->EnhanceCostperlead = isset($agency_default_price->enhance->Monthly->EnhanceCostperlead) ? $agency_default_price->enhance->Monthly->EnhanceCostperlead : $comset_val->enhance->Monthly->EnhanceCostperlead;
                        $companysetting->enhance->Monthly->EnhanceMinCostMonth = isset($agency_default_price->enhance->Monthly->EnhanceMinCostMonth) ? $agency_default_price->enhance->Monthly->EnhanceMinCostMonth : $comset_val->enhance->Monthly->EnhanceMinCostMonth;
                        $companysetting->enhance->Monthly->EnhancePlatformFee = isset($agency_default_price->enhance->Monthly->EnhancePlatformFee) ? $agency_default_price->enhance->Monthly->EnhancePlatformFee : $comset_val->enhance->Monthly->EnhancePlatformFee;
                        /* MONTHLY */
                        
                        /* ONETIME */
                        $companysetting->enhance->OneTime->EnhanceCostperlead = isset($agency_default_price->enhance->OneTime->EnhanceCostperlead) ? $agency_default_price->enhance->OneTime->EnhanceCostperlead : $comset_val->enhance->OneTime->EnhanceCostperlead;
                        $companysetting->enhance->OneTime->EnhanceMinCostMonth = isset($agency_default_price->enhance->OneTime->EnhanceMinCostMonth) ? $agency_default_price->enhance->OneTime->EnhanceMinCostMonth : $comset_val->enhance->OneTime->EnhanceMinCostMonth;
                        $companysetting->enhance->OneTime->EnhancePlatformFee = isset($agency_default_price->enhance->OneTime->EnhancePlatformFee) ? $agency_default_price->enhance->OneTime->EnhancePlatformFee : $comset_val->enhance->OneTime->EnhancePlatformFee;
                        /* ONETIME */
    
                        /* PREPAID */
                        $companysetting->enhance->Prepaid->EnhanceCostperlead = isset($agency_default_price->enhance->Prepaid->EnhanceCostperlead) ? $agency_default_price->enhance->Prepaid->EnhanceCostperlead : $comset_val->enhance->Prepaid->EnhanceCostperlead;
                        $companysetting->enhance->Prepaid->EnhanceMinCostMonth = isset($agency_default_price->enhance->Prepaid->EnhanceMinCostMonth) ? $agency_default_price->enhance->Prepaid->EnhanceMinCostMonth : $comset_val->enhance->Prepaid->EnhanceMinCostMonth;
                        $companysetting->enhance->Prepaid->EnhancePlatformFee = isset($agency_default_price->enhance->Prepaid->EnhancePlatformFee) ? $agency_default_price->enhance->Prepaid->EnhancePlatformFee : $comset_val->enhance->Prepaid->EnhancePlatformFee;
                        /* PREPAID */
                    }
                    /* ENHANCE */

                    /* B2B */
                    if(!isset($companysetting->b2b)) {
                        $companysetting->b2b = new stdClass();
                        $companysetting->b2b->Weekly = new stdClass();
                        $companysetting->b2b->Monthly = new stdClass();
                        $companysetting->b2b->OneTime = new stdClass();
                        $companysetting->b2b->Prepaid = new stdClass();
    
                        /* WEEKLY */
                        $companysetting->b2b->Weekly->B2bCostperlead = isset($agency_default_price->b2b->Weekly->B2bCostperlead) ? $agency_default_price->b2b->Weekly->B2bCostperlead : $comset_val->b2b->Weekly->B2bCostperlead;
                        $companysetting->b2b->Weekly->B2bMinCostMonth = isset($agency_default_price->b2b->Weekly->B2bMinCostMonth) ? $agency_default_price->b2b->Weekly->B2bMinCostMonth : $comset_val->b2b->Weekly->B2bMinCostMonth;
                        $companysetting->b2b->Weekly->B2bPlatformFee = isset($agency_default_price->b2b->Weekly->B2bPlatformFee) ? $agency_default_price->b2b->Weekly->B2bPlatformFee : $comset_val->b2b->Weekly->B2bPlatformFee;
                        /* WEEKLY */
                        
                        /* MONTHLY */
                        $companysetting->b2b->Monthly->B2bCostperlead = isset($agency_default_price->b2b->Monthly->B2bCostperlead) ? $agency_default_price->b2b->Monthly->B2bCostperlead : $comset_val->b2b->Monthly->B2bCostperlead;
                        $companysetting->b2b->Monthly->B2bMinCostMonth = isset($agency_default_price->b2b->Monthly->B2bMinCostMonth) ? $agency_default_price->b2b->Monthly->B2bMinCostMonth : $comset_val->b2b->Monthly->B2bMinCostMonth;
                        $companysetting->b2b->Monthly->B2bPlatformFee = isset($agency_default_price->b2b->Monthly->B2bPlatformFee) ? $agency_default_price->b2b->Monthly->B2bPlatformFee : $comset_val->b2b->Monthly->B2bPlatformFee;
                        /* MONTHLY */
                        
                        /* ONETIME */
                        $companysetting->b2b->OneTime->B2bCostperlead = isset($agency_default_price->b2b->OneTime->B2bCostperlead) ? $agency_default_price->b2b->OneTime->B2bCostperlead : $comset_val->b2b->OneTime->B2bCostperlead;
                        $companysetting->b2b->OneTime->B2bMinCostMonth = isset($agency_default_price->b2b->OneTime->B2bMinCostMonth) ? $agency_default_price->b2b->OneTime->B2bMinCostMonth : $comset_val->b2b->OneTime->B2bMinCostMonth;
                        $companysetting->b2b->OneTime->B2bPlatformFee = isset($agency_default_price->b2b->OneTime->B2bPlatformFee) ? $agency_default_price->b2b->OneTime->B2bPlatformFee : $comset_val->b2b->OneTime->B2bPlatformFee;
                        /* ONETIME */
    
                        /* PREPAID */
                        $companysetting->b2b->Prepaid->B2bCostperlead = isset($agency_default_price->b2b->Prepaid->B2bCostperlead) ? $agency_default_price->b2b->Prepaid->B2bCostperlead : $comset_val->b2b->Prepaid->B2bCostperlead;
                        $companysetting->b2b->Prepaid->B2bMinCostMonth = isset($agency_default_price->b2b->Prepaid->B2bMinCostMonth) ? $agency_default_price->b2b->Prepaid->B2bMinCostMonth : $comset_val->b2b->Prepaid->B2bMinCostMonth;
                        $companysetting->b2b->Prepaid->B2bPlatformFee = isset($agency_default_price->b2b->Prepaid->B2bPlatformFee) ? $agency_default_price->b2b->Prepaid->B2bPlatformFee : $comset_val->b2b->Prepaid->B2bPlatformFee;
                        /* PREPAID */
                    }
                    /* B2B */

                    if(!isset($companysetting->local->Prepaid)) {
                        $newPrepaidLocal = (object) [
                            "LeadspeekCostperlead" => $companysetting->local->Weekly->LeadspeekCostperlead,
                            "LeadspeekMinCostMonth" => $companysetting->local->Weekly->LeadspeekMinCostMonth,
                            "LeadspeekPlatformFee" => $companysetting->local->Weekly->LeadspeekPlatformFee,
                            "LeadspeekLeadsPerday" => "10"
                        ];
    
                        $newPrepaidLocator = (object) [
                            "LocatorCostperlead" => $companysetting->locator->Weekly->LocatorCostperlead,
                            "LocatorMinCostMonth" => $companysetting->locator->Weekly->LocatorMinCostMonth,
                            "LocatorPlatformFee" => $companysetting->locator->Weekly->LocatorPlatformFee,
                            "LocatorLeadsPerday" => "10"
                        ];

                        $newPrepaidEnhance = (object) [
                            "EnhanceCostperlead" => isset($agency_default_price->enhance->Prepaid->EnhanceCostperlead) ? $agency_default_price->enhance->Prepaid->EnhanceCostperlead : $comset_val->enhance->Prepaid->EnhanceCostperlead,
                            "EnhanceMinCostMonth" => isset($agency_default_price->enhance->Prepaid->EnhanceMinCostMonth) ? $agency_default_price->enhance->Prepaid->EnhanceMinCostMonth : $comset_val->enhance->Prepaid->EnhanceMinCostMonth,
                            "EnhancePlatformFee" => isset($agency_default_price->enhance->Prepaid->EnhancePlatformFee) ? $agency_default_price->enhance->Prepaid->EnhancePlatformFee : $comset_val->enhance->Prepaid->EnhancePlatformFee,
                            "EnhanceLeadsPerday" => "10"
                        ];

                        $newPrepaidB2b = (object) [
                            "B2bCostperlead" => isset($agency_default_price->b2b->Prepaid->B2bCostperlead) ? $agency_default_price->b2b->Prepaid->B2bCostperlead : $comset_val->b2b->Prepaid->B2bCostperlead,
                            "B2bMinCostMonth" => isset($agency_default_price->b2b->Prepaid->B2bMinCostMonth) ? $agency_default_price->b2b->Prepaid->B2bMinCostMonth : $comset_val->b2b->Prepaid->B2bMinCostMonth,
                            "B2bPlatformFee" => isset($agency_default_price->b2b->Prepaid->B2bPlatformFee) ? $agency_default_price->b2b->Prepaid->B2bPlatformFee : $comset_val->b2b->Prepaid->B2bPlatformFee,
                            "B2bLeadsPerday" => "10"
                        ];

                        $companysetting->local->Prepaid = $newPrepaidLocal;
                        $companysetting->locator->Prepaid = $newPrepaidLocator;
                        $companysetting->enhance->Prepaid = $newPrepaidEnhance;
                        $companysetting->b2b->Prepaid = $newPrepaidB2b;
                    }

                    // LOCAL MARKUP
                    if(!isset($companysetting->local->Weekly->LeadspeekCostperleadAdvanced)) {
                        // info('LOCAL MARKUP rootagencydefaultprice if Weekly 1');
                        $companysetting->local->Weekly->LeadspeekCostperleadAdvanced = isset($agency_default_price->local->Weekly->LeadspeekCostperleadAdvanced) ? $agency_default_price->local->Weekly->LeadspeekCostperleadAdvanced : $comset_val->local->Weekly->LeadspeekCostperleadAdvanced;
                    }
                    if(!isset($companysetting->local->Monthly->LeadspeekCostperleadAdvanced)) {
                        // info('LOCAL MARKUP rootagencydefaultprice if Monthly 1');
                        $companysetting->local->Monthly->LeadspeekCostperleadAdvanced = isset($agency_default_price->local->Monthly->LeadspeekCostperleadAdvanced) ? $agency_default_price->local->Monthly->LeadspeekCostperleadAdvanced : $comset_val->local->Monthly->LeadspeekCostperleadAdvanced;
                    } 
                    if(!isset($companysetting->local->OneTime->LeadspeekCostperleadAdvanced)) {
                        // info('LOCAL MARKUP rootagencydefaultprice if OneTime 1');
                        $companysetting->local->OneTime->LeadspeekCostperleadAdvanced = isset($agency_default_price->local->OneTime->LeadspeekCostperleadAdvanced) ? $agency_default_price->local->OneTime->LeadspeekCostperleadAdvanced : $comset_val->local->OneTime->LeadspeekCostperleadAdvanced;
                    } 
                    if(!isset($companysetting->local->Prepaid->LeadspeekCostperleadAdvanced)) {
                        // info('LOCAL MARKUP rootagencydefaultprice if Prepaid 1');
                        $companysetting->local->Prepaid->LeadspeekCostperleadAdvanced = isset($agency_default_price->local->Prepaid->LeadspeekCostperleadAdvanced) ? $agency_default_price->local->Prepaid->LeadspeekCostperleadAdvanced : $comset_val->local->Prepaid->LeadspeekCostperleadAdvanced;
                    } 
                    // LOCAL MARKUP

                    /* CHECK ENABLE MINIMUM LEADS PER DAY */
                    if(!isset($companysetting->local->EnableMinimumLeadsPerday))
                        $companysetting->local->EnableMinimumLeadsPerday = false;
                    if(!isset($companysetting->locator->EnableMinimumLeadsPerday))
                        $companysetting->locator->EnableMinimumLeadsPerday = false;
                    if(!isset($companysetting->enhance->EnableMinimumLeadsPerday))
                        $companysetting->enhance->EnableMinimumLeadsPerday = false;
                    if(!isset($companysetting->b2b->EnableMinimumLeadsPerday))
                        $companysetting->b2b->EnableMinimumLeadsPerday = false;
                    /* CHECK ENABLE MINIMUM LEADS PER DAY */

                    // SIMPLIFI
                    if(!isset($companysetting->simplifi)) {
                        $companysetting->simplifi = new stdClass();
                        $companysetting->simplifi->Prepaid = new stdClass();

                        /* PREPAID */
                        $companysetting->simplifi->Prepaid->SimplifiMaxBid = isset($agency_default_price->simplifi->Prepaid->SimplifiMaxBid) ? $agency_default_price->simplifi->Prepaid->SimplifiMaxBid : $comset_val->simplifi->Prepaid->SimplifiMaxBid;
                        $companysetting->simplifi->Prepaid->SimplifiDailyBudget = isset($agency_default_price->simplifi->Prepaid->SimplifiDailyBudget) ? $agency_default_price->simplifi->Prepaid->SimplifiDailyBudget : $comset_val->simplifi->Prepaid->SimplifiDailyBudget;
                        $companysetting->simplifi->Prepaid->SimplifiAgencyMarkup = isset($agency_default_price->simplifi->Prepaid->SimplifiAgencyMarkup) ? $agency_default_price->simplifi->Prepaid->SimplifiAgencyMarkup : $comset_val->simplifi->Prepaid->SimplifiAgencyMarkup;
                        /* PREPAID */
                    }
                    // SIMPLIFI

                    // PREDICT
                    if(!isset($companysetting->predict)) {
                        $companysetting->predict = new stdClass();
                    }
                    if(!isset($companysetting->predict->Prepaid)) {
                        $companysetting->predict->Prepaid = new stdClass();
                    }
                    if(!isset($companysetting->predict->Prepaid->PredictPlatformFee)) {
                        $companysetting->predict->Prepaid->PredictPlatformFee = isset($agency_default_price->predict->Prepaid->PredictPlatformFee) ? $agency_default_price->predict->Prepaid->PredictPlatformFee : $comset_val->predict->Prepaid->PredictPlatformFee;
                    }
                    if(!isset($companysetting->predict->Prepaid->PredictMinCostMonth)) {
                        $companysetting->predict->Prepaid->PredictMinCostMonth = isset($agency_default_price->predict->Prepaid->PredictMinCostMonth) ? $agency_default_price->predict->Prepaid->PredictMinCostMonth : $comset_val->predict->Prepaid->PredictMinCostMonth;
                    }
                    // PREDICT

                }
            } else { // jika tidak ada ambil dari agencydefaultprice
                $userClient = User::select('company_parent')->where('company_id','=',$companyID)->where('user_type','=','client')->first();
                $companyParentID = isset($userClient->company_parent)?$userClient->company_parent:'';
                
                $companysetting = $this->getcompanysetting($companyParentID,'agencydefaultprice');

                if($companysetting != '') {
                    $comset_val = $this->getcompanysetting($idSys,'rootagencydefaultprice');

                    /* ENHANCE */
                    if(!isset($companysetting->enhance)) {
    
                        $companysetting->enhance = new stdClass();
                        $companysetting->enhance->Weekly = new stdClass();
                        $companysetting->enhance->Monthly = new stdClass();
                        $companysetting->enhance->OneTime = new stdClass();
                        $companysetting->enhance->Prepaid = new stdClass();
    
                        /* WEEKLY */
                        $companysetting->enhance->Weekly->EnhanceCostperlead = isset($agency_default_price->enhance->Weekly->EnhanceCostperlead) ? $agency_default_price->enhance->Weekly->EnhanceCostperlead : $comset_val->enhance->Weekly->EnhanceCostperlead;
                        $companysetting->enhance->Weekly->EnhanceMinCostMonth = isset($agency_default_price->enhance->Weekly->EnhanceMinCostMonth) ? $agency_default_price->enhance->Weekly->EnhanceMinCostMonth : $comset_val->enhance->Weekly->EnhanceMinCostMonth;
                        $companysetting->enhance->Weekly->EnhancePlatformFee = isset($agency_default_price->enhance->Weekly->EnhancePlatformFee) ? $agency_default_price->enhance->Weekly->EnhancePlatformFee : $comset_val->enhance->Weekly->EnhancePlatformFee;
                        /* WEEKLY */
                        
                        /* MONTHLY */
                        $companysetting->enhance->Monthly->EnhanceCostperlead = isset($agency_default_price->enhance->Monthly->EnhanceCostperlead) ? $agency_default_price->enhance->Monthly->EnhanceCostperlead : $comset_val->enhance->Monthly->EnhanceCostperlead;
                        $companysetting->enhance->Monthly->EnhanceMinCostMonth = isset($agency_default_price->enhance->Monthly->EnhanceMinCostMonth) ? $agency_default_price->enhance->Monthly->EnhanceMinCostMonth : $comset_val->enhance->Monthly->EnhanceMinCostMonth;
                        $companysetting->enhance->Monthly->EnhancePlatformFee = isset($agency_default_price->enhance->Monthly->EnhancePlatformFee) ? $agency_default_price->enhance->Monthly->EnhancePlatformFee : $comset_val->enhance->Monthly->EnhancePlatformFee;
                        /* MONTHLY */
                        
                        /* ONETIME */
                        $companysetting->enhance->OneTime->EnhanceCostperlead = isset($agency_default_price->enhance->OneTime->EnhanceCostperlead) ? $agency_default_price->enhance->OneTime->EnhanceCostperlead : $comset_val->enhance->OneTime->EnhanceCostperlead;
                        $companysetting->enhance->OneTime->EnhanceMinCostMonth = isset($agency_default_price->enhance->OneTime->EnhanceMinCostMonth) ? $agency_default_price->enhance->OneTime->EnhanceMinCostMonth : $comset_val->enhance->OneTime->EnhanceMinCostMonth;
                        $companysetting->enhance->OneTime->EnhancePlatformFee = isset($agency_default_price->enhance->OneTime->EnhancePlatformFee) ? $agency_default_price->enhance->OneTime->EnhancePlatformFee : $comset_val->enhance->OneTime->EnhancePlatformFee;
                        /* ONETIME */
    
                        /* PREPAID */
                        $companysetting->enhance->Prepaid->EnhanceCostperlead = isset($agency_default_price->enhance->Prepaid->EnhanceCostperlead) ? $agency_default_price->enhance->Prepaid->EnhanceCostperlead : $comset_val->enhance->Prepaid->EnhanceCostperlead;
                        $companysetting->enhance->Prepaid->EnhanceMinCostMonth = isset($agency_default_price->enhance->Prepaid->EnhanceMinCostMonth) ? $agency_default_price->enhance->Prepaid->EnhanceMinCostMonth : $comset_val->enhance->Prepaid->EnhanceMinCostMonth;
                        $companysetting->enhance->Prepaid->EnhancePlatformFee = isset($agency_default_price->enhance->Prepaid->EnhancePlatformFee) ? $agency_default_price->enhance->Prepaid->EnhancePlatformFee : $comset_val->enhance->Prepaid->EnhancePlatformFee;
                        /* PREPAID */
                    }
                    /* ENHANCE */

                    /* B2B */
                    if(!isset($companysetting->b2b)) {
                        
                        $companysetting->b2b = new stdClass();
                        $companysetting->b2b->Weekly = new stdClass();
                        $companysetting->b2b->Monthly = new stdClass();
                        $companysetting->b2b->OneTime = new stdClass();
                        $companysetting->b2b->Prepaid = new stdClass();
    
                        /* WEEKLY */
                        $companysetting->b2b->Weekly->B2bCostperlead = isset($agency_default_price->b2b->Weekly->B2bCostperlead) ? $agency_default_price->b2b->Weekly->B2bCostperlead : $comset_val->b2b->Weekly->B2bCostperlead;
                        $companysetting->b2b->Weekly->B2bMinCostMonth = isset($agency_default_price->b2b->Weekly->B2bMinCostMonth) ? $agency_default_price->b2b->Weekly->B2bMinCostMonth : $comset_val->b2b->Weekly->B2bMinCostMonth;
                        $companysetting->b2b->Weekly->B2bPlatformFee = isset($agency_default_price->b2b->Weekly->B2bPlatformFee) ? $agency_default_price->b2b->Weekly->B2bPlatformFee : $comset_val->b2b->Weekly->B2bPlatformFee;
                        /* WEEKLY */
                        
                        /* MONTHLY */
                        $companysetting->b2b->Monthly->B2bCostperlead = isset($agency_default_price->b2b->Monthly->B2bCostperlead) ? $agency_default_price->b2b->Monthly->B2bCostperlead : $comset_val->b2b->Monthly->B2bCostperlead;
                        $companysetting->b2b->Monthly->B2bMinCostMonth = isset($agency_default_price->b2b->Monthly->B2bMinCostMonth) ? $agency_default_price->b2b->Monthly->B2bMinCostMonth : $comset_val->b2b->Monthly->B2bMinCostMonth;
                        $companysetting->b2b->Monthly->B2bPlatformFee = isset($agency_default_price->b2b->Monthly->B2bPlatformFee) ? $agency_default_price->b2b->Monthly->B2bPlatformFee : $comset_val->b2b->Monthly->B2bPlatformFee;
                        /* MONTHLY */
                        
                        /* ONETIME */
                        $companysetting->b2b->OneTime->B2bCostperlead = isset($agency_default_price->b2b->OneTime->B2bCostperlead) ? $agency_default_price->b2b->OneTime->B2bCostperlead : $comset_val->b2b->OneTime->B2bCostperlead;
                        $companysetting->b2b->OneTime->B2bMinCostMonth = isset($agency_default_price->b2b->OneTime->B2bMinCostMonth) ? $agency_default_price->b2b->OneTime->B2bMinCostMonth : $comset_val->b2b->OneTime->B2bMinCostMonth;
                        $companysetting->b2b->OneTime->B2bPlatformFee = isset($agency_default_price->b2b->OneTime->B2bPlatformFee) ? $agency_default_price->b2b->OneTime->B2bPlatformFee : $comset_val->b2b->OneTime->B2bPlatformFee;
                        /* ONETIME */
    
                        /* PREPAID */
                        $companysetting->b2b->Prepaid->B2bCostperlead = isset($agency_default_price->b2b->Prepaid->B2bCostperlead) ? $agency_default_price->b2b->Prepaid->B2bCostperlead : $comset_val->b2b->Prepaid->B2bCostperlead;
                        $companysetting->b2b->Prepaid->B2bMinCostMonth = isset($agency_default_price->b2b->Prepaid->B2bMinCostMonth) ? $agency_default_price->b2b->Prepaid->B2bMinCostMonth : $comset_val->b2b->Prepaid->B2bMinCostMonth;
                        $companysetting->b2b->Prepaid->B2bPlatformFee = isset($agency_default_price->b2b->Prepaid->B2bPlatformFee) ? $agency_default_price->b2b->Prepaid->B2bPlatformFee : $comset_val->b2b->Prepaid->B2bPlatformFee;
                        /* PREPAID */
                    }
                    /* B2B */

                    if (!isset($companysetting->local->Prepaid)) {
                        $newPrepaidLocal = (object) [
                            "LeadspeekCostperlead" => $companysetting->local->Weekly->LeadspeekCostperlead,
                            "LeadspeekMinCostMonth" => $companysetting->local->Weekly->LeadspeekMinCostMonth,
                            "LeadspeekPlatformFee" => $companysetting->local->Weekly->LeadspeekPlatformFee,
                            "LeadspeekLeadsPerday" => "10"
                        ];
    
                        $newPrepaidLocator = (object) [
                            "LocatorCostperlead" => $companysetting->locator->Weekly->LocatorCostperlead,
                            "LocatorMinCostMonth" => $companysetting->locator->Weekly->LocatorMinCostMonth,
                            "LocatorPlatformFee" => $companysetting->locator->Weekly->LocatorPlatformFee,
                            "LocatorLeadsPerday" => "10"
                        ];

                        $newPrepaidEnhance = (object) [
                            "EnhanceCostperlead" => isset($agency_default_price->enhance->Prepaid->EnhanceCostperlead) ? $agency_default_price->enhance->Prepaid->EnhanceCostperlead : $comset_val->enhance->Prepaid->EnhanceCostperlead,
                            "EnhanceMinCostMonth" => isset($agency_default_price->enhance->Prepaid->EnhanceMinCostMonth) ? $agency_default_price->enhance->Prepaid->EnhanceMinCostMonth : $comset_val->enhance->Prepaid->EnhanceMinCostMonth,
                            "EnhancePlatformFee" => isset($agency_default_price->enhance->Prepaid->EnhancePlatformFee) ? $agency_default_price->enhance->Prepaid->EnhancePlatformFee : $comset_val->enhance->Prepaid->EnhancePlatformFee,
                            "EnhanceLeadsPerday" => "10"
                        ];

                        $newPrepaidB2b = (object) [
                            "B2bCostperlead" => isset($agency_default_price->b2b->Prepaid->B2bCostperlead) ? $agency_default_price->b2b->Prepaid->B2bCostperlead : $comset_val->b2b->Prepaid->B2bCostperlead,
                            "B2bMinCostMonth" => isset($agency_default_price->b2b->Prepaid->B2bMinCostMonth) ? $agency_default_price->b2b->Prepaid->B2bMinCostMonth : $comset_val->b2b->Prepaid->B2bMinCostMonth,
                            "B2bPlatformFee" => isset($agency_default_price->b2b->Prepaid->B2bPlatformFee) ? $agency_default_price->b2b->Prepaid->B2bPlatformFee : $comset_val->b2b->Prepaid->B2bPlatformFee,
                            "B2bLeadsPerday" => "10"
                        ];

                        $companysetting->local->Prepaid = $newPrepaidLocal;
                        $companysetting->locator->Prepaid = $newPrepaidLocator;
                        $companysetting->enhance->Prepaid = $newPrepaidEnhance;
                        $companysetting->b2b->Prepaid = $newPrepaidB2b;
                    }

                    // LOCAL MARKUP
                    if(!isset($companysetting->local->Weekly->LeadspeekCostperleadAdvanced)) {
                        // info('LOCAL MARKUP rootagencydefaultprice else Weekly 1');
                        $companysetting->local->Weekly->LeadspeekCostperleadAdvanced = $comset_val->local->Weekly->LeadspeekCostperleadAdvanced;
                    }
                    if(!isset($companysetting->local->Monthly->LeadspeekCostperleadAdvanced)) {
                        // info('LOCAL MARKUP rootagencydefaultprice else Monthly 1');
                        $companysetting->local->Monthly->LeadspeekCostperleadAdvanced = $comset_val->local->Monthly->LeadspeekCostperleadAdvanced;
                    } 
                    if(!isset($companysetting->local->OneTime->LeadspeekCostperleadAdvanced)) {
                        // info('LOCAL MARKUP rootagencydefaultprice else OneTime 1');
                        $companysetting->local->OneTime->LeadspeekCostperleadAdvanced = $comset_val->local->OneTime->LeadspeekCostperleadAdvanced;
                    } 
                    if(!isset($companysetting->local->Prepaid->LeadspeekCostperleadAdvanced)) {
                        // info('LOCAL MARKUP rootagencydefaultprice else Prepaid 1');
                        $companysetting->local->Prepaid->LeadspeekCostperleadAdvanced = $comset_val->local->Prepaid->LeadspeekCostperleadAdvanced;
                    } 
                    // LOCAL MARKUP

                    // SIMPLIFI
                    if(!isset($companysetting->simplifi)) {
                        $companysetting->simplifi = new stdClass();
                        $companysetting->simplifi->Prepaid = new stdClass();

                        /* PREPAID */
                        $companysetting->simplifi->Prepaid->SimplifiMaxBid = $comset_val->simplifi->Prepaid->SimplifiMaxBid;
                        $companysetting->simplifi->Prepaid->SimplifiDailyBudget = $comset_val->simplifi->Prepaid->SimplifiDailyBudget;
                        $companysetting->simplifi->Prepaid->SimplifiAgencyMarkup = $comset_val->simplifi->Prepaid->SimplifiAgencyMarkup;
                        /* PREPAID */
                    }
                    // SIMPLIFI

                    // PREDICT
                    if(!isset($companysetting->predict)) {
                        $companysetting->predict = new stdClass();
                    }
                    if(!isset($companysetting->predict->Prepaid)) {
                        $companysetting->predict->Prepaid = new stdClass();
                    }
                    if(!isset($companysetting->predict->Prepaid->PredictPlatformFee)) {
                        $companysetting->predict->Prepaid->PredictPlatformFee = isset($agency_default_price->predict->Prepaid->PredictPlatformFee) ? $agency_default_price->predict->Prepaid->PredictPlatformFee : $comset_val->predict->Prepaid->PredictPlatformFee;
                    }
                    if(!isset($companysetting->predict->Prepaid->PredictMinCostMonth)) {
                        $companysetting->predict->Prepaid->PredictMinCostMonth = isset($agency_default_price->predict->Prepaid->PredictMinCostMonth) ? $agency_default_price->predict->Prepaid->PredictMinCostMonth : $comset_val->predict->Prepaid->PredictMinCostMonth;
                    }
                    // PREDICT
                } else {
                    $companysetting = $this->getcompanysetting($idSys, 'rootagencydefaultprice');
                }

                $companysetting->local->Monthly->LeadspeekLeadsPerday = '10';
                $companysetting->local->Weekly->LeadspeekLeadsPerday = '10';
                $companysetting->local->OneTime->LeadspeekLeadsPerday = '10';
                $companysetting->local->Prepaid->LeadspeekLeadsPerday = '10';

                $companysetting->locator->Monthly->LocatorLeadsPerday = '10';
                $companysetting->locator->Weekly->LocatorLeadsPerday = '10';
                $companysetting->locator->OneTime->LocatorLeadsPerday = '10';
                $companysetting->locator->Prepaid->LocatorLeadsPerday = '10';

                $companysetting->enhance->Monthly->EnhanceLeadsPerday = ($clientMinLeadDayEnhance !== '') ? $clientMinLeadDayEnhance : "10";
                $companysetting->enhance->Weekly->EnhanceLeadsPerday = ($clientMinLeadDayEnhance !== '') ? $clientMinLeadDayEnhance : "10";
                $companysetting->enhance->OneTime->EnhanceLeadsPerday = ($clientMinLeadDayEnhance !== '') ? $clientMinLeadDayEnhance : "10";
                $companysetting->enhance->Prepaid->EnhanceLeadsPerday = ($clientMinLeadDayEnhance !== '') ? $clientMinLeadDayEnhance : "10";

                $companysetting->b2b->Monthly->B2bLeadsPerday = ($clientMinLeadDayEnhance !== '') ? $clientMinLeadDayEnhance : "10";
                $companysetting->b2b->Weekly->B2bLeadsPerday = ($clientMinLeadDayEnhance !== '') ? $clientMinLeadDayEnhance : "10";
                $companysetting->b2b->OneTime->B2bLeadsPerday = ($clientMinLeadDayEnhance !== '') ? $clientMinLeadDayEnhance : "10";
                $companysetting->b2b->Prepaid->B2bLeadsPerday = ($clientMinLeadDayEnhance !== '') ? $clientMinLeadDayEnhance : "10";

                /* CHECK ENABLE MINIMUM LEADS PER DAY */
                if(!isset($companysetting->local->EnableMinimumLeadsPerday))
                    $companysetting->local->EnableMinimumLeadsPerday = false;
                if(!isset($companysetting->locator->EnableMinimumLeadsPerday))
                    $companysetting->locator->EnableMinimumLeadsPerday = false;
                if(!isset($companysetting->enhance->EnableMinimumLeadsPerday))
                    $companysetting->enhance->EnableMinimumLeadsPerday = false;
                if(!isset($companysetting->b2b->EnableMinimumLeadsPerday))
                    $companysetting->b2b->EnableMinimumLeadsPerday = false;
                /* CHECK ENABLE MINIMUM LEADS PER DAY */
            }

            return $companysetting;
    }

    public function getgeneralsetting(Request $request) {
        $settingname = (isset($request->settingname))?$request->settingname:'';
        $companyID = (isset($request->CompanyID))?$request->CompanyID:'';
        $idSys = (isset($request->idSys))?$request->idSys:'';
        $clientdefaultprice = false;
        $rootcostagency = "";
        $clientTypeLead = [
            'type' => '',
            'value' => ''
        ];
        $clientMinLeadDayEnhance = 0;
        $clientDefaultPrice = "";
        $pk = (isset($request->pk))?$request->pk:'';

        /** IF FOR CLIENT COST AGENCY */
        if ($settingname == "clientdefaultprice") {
            /* GET CLIENT MIN LEAD DAYS */
            $rootSetting = $this->getcompanysetting($idSys, 'rootsetting');
            $clientMinLeadDayEnhance = (isset($rootSetting->clientminleadday))?$rootSetting->clientminleadday:"";
            /* GET CLIENT MIN LEAD DAYS */
            
            $getcompanysetting = CompanySetting::where('company_id',$companyID)->whereEncrypted('setting_name',$settingname)->get();
            $companysetting = "";
            if (count($getcompanysetting) > 0) { // jika clientdefaultprice ada pakai ini
                $companysetting = json_decode($getcompanysetting[0]['setting_value']);
                $clientdefaultprice = true;

                if ($companysetting != "") {
                    $userClient = User::select('company_parent')->where('company_id','=',$companyID)->where('user_type','=','client')->first();
                    $companyParentID = isset($userClient->company_parent)?$userClient->company_parent:'';
                    $agency_default_price = $this->getcompanysetting($companyParentID,'agencydefaultprice');

                    $comset_val = $this->getcompanysetting($idSys,'rootagencydefaultprice');

                    if(!isset($companysetting->enhance)) {
    
                        $companysetting->enhance = new stdClass();
                        $companysetting->enhance->Weekly = new stdClass();
                        $companysetting->enhance->Monthly = new stdClass();
                        $companysetting->enhance->OneTime = new stdClass();
                        $companysetting->enhance->Prepaid = new stdClass();
    
                        /* WEEKLY */
                        $companysetting->enhance->Weekly->EnhanceCostperlead = isset($agency_default_price->enhance->Weekly->EnhanceCostperlead) ? $agency_default_price->enhance->Weekly->EnhanceCostperlead : $comset_val->enhance->Weekly->EnhanceCostperlead;
                        $companysetting->enhance->Weekly->EnhanceMinCostMonth = isset($agency_default_price->enhance->Weekly->EnhanceMinCostMonth) ? $agency_default_price->enhance->Weekly->EnhanceMinCostMonth : $comset_val->enhance->Weekly->EnhanceMinCostMonth;
                        $companysetting->enhance->Weekly->EnhancePlatformFee = isset($agency_default_price->enhance->Weekly->EnhancePlatformFee) ? $agency_default_price->enhance->Weekly->EnhancePlatformFee : $comset_val->enhance->Weekly->EnhancePlatformFee;
                        /* WEEKLY */
                        
                        /* MONTHLY */
                        $companysetting->enhance->Monthly->EnhanceCostperlead = isset($agency_default_price->enhance->Monthly->EnhanceCostperlead) ? $agency_default_price->enhance->Monthly->EnhanceCostperlead : $comset_val->enhance->Monthly->EnhanceCostperlead;
                        $companysetting->enhance->Monthly->EnhanceMinCostMonth = isset($agency_default_price->enhance->Monthly->EnhanceMinCostMonth) ? $agency_default_price->enhance->Monthly->EnhanceMinCostMonth : $comset_val->enhance->Monthly->EnhanceMinCostMonth;
                        $companysetting->enhance->Monthly->EnhancePlatformFee = isset($agency_default_price->enhance->Monthly->EnhancePlatformFee) ? $agency_default_price->enhance->Monthly->EnhancePlatformFee : $comset_val->enhance->Monthly->EnhancePlatformFee;
                        /* MONTHLY */
                        
                        /* ONETIME */
                        $companysetting->enhance->OneTime->EnhanceCostperlead = isset($agency_default_price->enhance->OneTime->EnhanceCostperlead) ? $agency_default_price->enhance->OneTime->EnhanceCostperlead : $comset_val->enhance->OneTime->EnhanceCostperlead;
                        $companysetting->enhance->OneTime->EnhanceMinCostMonth = isset($agency_default_price->enhance->OneTime->EnhanceMinCostMonth) ? $agency_default_price->enhance->OneTime->EnhanceMinCostMonth : $comset_val->enhance->OneTime->EnhanceMinCostMonth;
                        $companysetting->enhance->OneTime->EnhancePlatformFee = isset($agency_default_price->enhance->OneTime->EnhancePlatformFee) ? $agency_default_price->enhance->OneTime->EnhancePlatformFee : $comset_val->enhance->OneTime->EnhancePlatformFee;
                        /* ONETIME */
    
                        /* PREPAID */
                        $companysetting->enhance->Prepaid->EnhanceCostperlead = isset($agency_default_price->enhance->Prepaid->EnhanceCostperlead) ? $agency_default_price->enhance->Prepaid->EnhanceCostperlead : $comset_val->enhance->Prepaid->EnhanceCostperlead;
                        $companysetting->enhance->Prepaid->EnhanceMinCostMonth = isset($agency_default_price->enhance->Prepaid->EnhanceMinCostMonth) ? $agency_default_price->enhance->Prepaid->EnhanceMinCostMonth : $comset_val->enhance->Prepaid->EnhanceMinCostMonth;
                        $companysetting->enhance->Prepaid->EnhancePlatformFee = isset($agency_default_price->enhance->Prepaid->EnhancePlatformFee) ? $agency_default_price->enhance->Prepaid->EnhancePlatformFee : $comset_val->enhance->Prepaid->EnhancePlatformFee;
                        /* PREPAID */

                        /* LEADS PER DAY */
                        $companysetting->enhance->Monthly->EnhanceLeadsPerday = ($clientMinLeadDayEnhance !== '') ? $clientMinLeadDayEnhance : "10";
                        $companysetting->enhance->Weekly->EnhanceLeadsPerday = ($clientMinLeadDayEnhance !== '') ? $clientMinLeadDayEnhance : "10";
                        $companysetting->enhance->OneTime->EnhanceLeadsPerday = ($clientMinLeadDayEnhance !== '') ? $clientMinLeadDayEnhance : "10";
                        $companysetting->enhance->Prepaid->EnhanceLeadsPerday = ($clientMinLeadDayEnhance !== '') ? $clientMinLeadDayEnhance : "10";
                        /* LEADS PER DAY */
                    }

                    if(!isset($companysetting->b2b)) {
    
                        $companysetting->b2b = new stdClass();
                        $companysetting->b2b->Weekly = new stdClass();
                        $companysetting->b2b->Monthly = new stdClass();
                        $companysetting->b2b->OneTime = new stdClass();
                        $companysetting->b2b->Prepaid = new stdClass();
    
                        /* WEEKLY */
                        $companysetting->b2b->Weekly->B2bCostperlead = isset($agency_default_price->b2b->Weekly->B2bCostperlead) ? $agency_default_price->b2b->Weekly->B2bCostperlead : $comset_val->b2b->Weekly->B2bCostperlead;
                        $companysetting->b2b->Weekly->B2bMinCostMonth = isset($agency_default_price->b2b->Weekly->B2bMinCostMonth) ? $agency_default_price->b2b->Weekly->B2bMinCostMonth : $comset_val->b2b->Weekly->B2bMinCostMonth;
                        $companysetting->b2b->Weekly->B2bPlatformFee = isset($agency_default_price->b2b->Weekly->B2bPlatformFee) ? $agency_default_price->b2b->Weekly->B2bPlatformFee : $comset_val->b2b->Weekly->B2bPlatformFee;
                        /* WEEKLY */
                        
                        /* MONTHLY */
                        $companysetting->b2b->Monthly->B2bCostperlead = isset($agency_default_price->b2b->Monthly->B2bCostperlead) ? $agency_default_price->b2b->Monthly->B2bCostperlead : $comset_val->b2b->Monthly->B2bCostperlead;
                        $companysetting->b2b->Monthly->B2bMinCostMonth = isset($agency_default_price->b2b->Monthly->B2bMinCostMonth) ? $agency_default_price->b2b->Monthly->B2bMinCostMonth : $comset_val->b2b->Monthly->B2bMinCostMonth;
                        $companysetting->b2b->Monthly->B2bPlatformFee = isset($agency_default_price->b2b->Monthly->B2bPlatformFee) ? $agency_default_price->b2b->Monthly->B2bPlatformFee : $comset_val->b2b->Monthly->B2bPlatformFee;
                        /* MONTHLY */
                        
                        /* ONETIME */
                        $companysetting->b2b->OneTime->B2bCostperlead = isset($agency_default_price->b2b->OneTime->B2bCostperlead) ? $agency_default_price->b2b->OneTime->B2bCostperlead : $comset_val->b2b->OneTime->B2bCostperlead;
                        $companysetting->b2b->OneTime->B2bMinCostMonth = isset($agency_default_price->b2b->OneTime->B2bMinCostMonth) ? $agency_default_price->b2b->OneTime->B2bMinCostMonth : $comset_val->b2b->OneTime->B2bMinCostMonth;
                        $companysetting->b2b->OneTime->B2bPlatformFee = isset($agency_default_price->b2b->OneTime->B2bPlatformFee) ? $agency_default_price->b2b->OneTime->B2bPlatformFee : $comset_val->b2b->OneTime->B2bPlatformFee;
                        /* ONETIME */
    
                        /* PREPAID */
                        $companysetting->b2b->Prepaid->B2bCostperlead = isset($agency_default_price->b2b->Prepaid->B2bCostperlead) ? $agency_default_price->b2b->Prepaid->B2bCostperlead : $comset_val->b2b->Prepaid->B2bCostperlead;
                        $companysetting->b2b->Prepaid->B2bMinCostMonth = isset($agency_default_price->b2b->Prepaid->B2bMinCostMonth) ? $agency_default_price->b2b->Prepaid->B2bMinCostMonth : $comset_val->b2b->Prepaid->B2bMinCostMonth;
                        $companysetting->b2b->Prepaid->B2bPlatformFee = isset($agency_default_price->b2b->Prepaid->B2bPlatformFee) ? $agency_default_price->b2b->Prepaid->B2bPlatformFee : $comset_val->b2b->Prepaid->B2bPlatformFee;
                        /* PREPAID */

                        /* LEAD PER DAY */
                        $companysetting->b2b->Monthly->B2bLeadsPerday = ($clientMinLeadDayEnhance !== '') ? $clientMinLeadDayEnhance : "10";
                        $companysetting->b2b->Weekly->B2bLeadsPerday = ($clientMinLeadDayEnhance !== '') ? $clientMinLeadDayEnhance : "10";
                        $companysetting->b2b->OneTime->B2bLeadsPerday = ($clientMinLeadDayEnhance !== '') ? $clientMinLeadDayEnhance : "10";
                        $companysetting->b2b->Prepaid->B2bLeadsPerday = ($clientMinLeadDayEnhance !== '') ? $clientMinLeadDayEnhance : "10";
                        /* LEAD PER DAY */
                    }

                    if(!isset($companysetting->local->Prepaid)) {
                        $newPrepaidLocal = (object) [
                            "LeadspeekCostperlead" => $companysetting->local->Weekly->LeadspeekCostperlead,
                            "LeadspeekMinCostMonth" => $companysetting->local->Weekly->LeadspeekMinCostMonth,
                            "LeadspeekPlatformFee" => $companysetting->local->Weekly->LeadspeekPlatformFee,
                            "LeadspeekLeadsPerday" => "10"
                        ];
    
                        $newPrepaidLocator = (object) [
                            "LocatorCostperlead" => $companysetting->locator->Weekly->LocatorCostperlead,
                            "LocatorMinCostMonth" => $companysetting->locator->Weekly->LocatorMinCostMonth,
                            "LocatorPlatformFee" => $companysetting->locator->Weekly->LocatorPlatformFee,
                            "LocatorLeadsPerday" => "10"
                        ];

                        $newPrepaidEnhance = (object) [
                            "EnhanceCostperlead" => isset($agency_default_price->enhance->Prepaid->EnhanceCostperlead) ? $agency_default_price->enhance->Prepaid->EnhanceCostperlead : $comset_val->enhance->Prepaid->EnhanceCostperlead,
                            "EnhanceMinCostMonth" => isset($agency_default_price->enhance->Prepaid->EnhanceMinCostMonth) ? $agency_default_price->enhance->Prepaid->EnhanceMinCostMonth : $comset_val->enhance->Prepaid->EnhanceMinCostMonth,
                            "EnhancePlatformFee" => isset($agency_default_price->enhance->Prepaid->EnhancePlatformFee) ? $agency_default_price->enhance->Prepaid->EnhancePlatformFee : $comset_val->enhance->Prepaid->EnhancePlatformFee,
                            "EnhanceLeadsPerday" => "10"
                        ];

                        $newPrepaidB2b = (object) [
                            "B2bCostperlead" => isset($agency_default_price->b2b->Prepaid->B2bCostperlead) ? $agency_default_price->b2b->Prepaid->B2bCostperlead : $comset_val->b2b->Prepaid->B2bCostperlead,
                            "B2bMinCostMonth" => isset($agency_default_price->b2b->Prepaid->B2bMinCostMonth) ? $agency_default_price->b2b->Prepaid->B2bMinCostMonth : $comset_val->b2b->Prepaid->B2bMinCostMonth,
                            "B2bPlatformFee" => isset($agency_default_price->b2b->Prepaid->B2bPlatformFee) ? $agency_default_price->b2b->Prepaid->B2bPlatformFee : $comset_val->b2b->Prepaid->B2bPlatformFee,
                            "B2bLeadsPerday" => "10"
                        ];

                        $companysetting->local->Prepaid = $newPrepaidLocal;
                        $companysetting->locator->Prepaid = $newPrepaidLocator;
                        $companysetting->enhance->Prepaid = $newPrepaidEnhance;
                        $companysetting->b2b->Prepaid = $newPrepaidB2b;
                    }

                    // LOCAL MARKUP
                    if(!isset($companysetting->local->Weekly->LeadspeekCostperleadAdvanced)) {
                        // info('LOCAL MARKUP rootagencydefaultprice if Weekly 1');
                        $companysetting->local->Weekly->LeadspeekCostperleadAdvanced = isset($agency_default_price->local->Weekly->LeadspeekCostperleadAdvanced) ? $agency_default_price->local->Weekly->LeadspeekCostperleadAdvanced : $comset_val->local->Weekly->LeadspeekCostperleadAdvanced;
                    }
                    if(!isset($companysetting->local->Monthly->LeadspeekCostperleadAdvanced)) {
                        // info('LOCAL MARKUP rootagencydefaultprice if Monthly 1');
                        $companysetting->local->Monthly->LeadspeekCostperleadAdvanced = isset($agency_default_price->local->Monthly->LeadspeekCostperleadAdvanced) ? $agency_default_price->local->Monthly->LeadspeekCostperleadAdvanced : $comset_val->local->Monthly->LeadspeekCostperleadAdvanced;
                    } 
                    if(!isset($companysetting->local->OneTime->LeadspeekCostperleadAdvanced)) {
                        // info('LOCAL MARKUP rootagencydefaultprice if OneTime 1');
                        $companysetting->local->OneTime->LeadspeekCostperleadAdvanced = isset($agency_default_price->local->OneTime->LeadspeekCostperleadAdvanced) ? $agency_default_price->local->OneTime->LeadspeekCostperleadAdvanced : $comset_val->local->OneTime->LeadspeekCostperleadAdvanced;
                    } 
                    if(!isset($companysetting->local->Prepaid->LeadspeekCostperleadAdvanced)) {
                        // info('LOCAL MARKUP rootagencydefaultprice if Prepaid 1');
                        $companysetting->local->Prepaid->LeadspeekCostperleadAdvanced = isset($agency_default_price->local->Prepaid->LeadspeekCostperleadAdvanced) ? $agency_default_price->local->Prepaid->LeadspeekCostperleadAdvanced : $comset_val->local->Prepaid->LeadspeekCostperleadAdvanced;
                    } 
                    // LOCAL MARKUP

                    /* CHECK ENABLE MINIMUM LEADS PER DAY */
                    if(!isset($companysetting->local->EnableMinimumLeadsPerday))
                        $companysetting->local->EnableMinimumLeadsPerday = false;
                    if(!isset($companysetting->locator->EnableMinimumLeadsPerday))
                        $companysetting->locator->EnableMinimumLeadsPerday = false;
                    if(!isset($companysetting->enhance->EnableMinimumLeadsPerday))
                        $companysetting->enhance->EnableMinimumLeadsPerday = false;
                    if(!isset($companysetting->b2b->EnableMinimumLeadsPerday))
                        $companysetting->b2b->EnableMinimumLeadsPerday = false;
                    /* CHECK ENABLE MINIMUM LEADS PER DAY */

                    // SIMPLIFI
                    if(!isset($companysetting->simplifi)) {
                        $companysetting->simplifi = new stdClass();
                        $companysetting->simplifi->Prepaid = new stdClass();

                        /* PREPAID */
                        $companysetting->simplifi->Prepaid->SimplifiMaxBid = isset($agency_default_price->simplifi->Prepaid->SimplifiMaxBid) ? $agency_default_price->simplifi->Prepaid->SimplifiMaxBid : $comset_val->simplifi->Prepaid->SimplifiMaxBid;
                        $companysetting->simplifi->Prepaid->SimplifiDailyBudget = isset($agency_default_price->simplifi->Prepaid->SimplifiDailyBudget) ? $agency_default_price->simplifi->Prepaid->SimplifiDailyBudget : $comset_val->simplifi->Prepaid->SimplifiDailyBudget;
                        $companysetting->simplifi->Prepaid->SimplifiAgencyMarkup = isset($agency_default_price->simplifi->Prepaid->SimplifiAgencyMarkup) ? $agency_default_price->simplifi->Prepaid->SimplifiAgencyMarkup : $comset_val->simplifi->Prepaid->SimplifiAgencyMarkup;
                        /* PREPAID */
                    }
                    // SIMPLIFI

                    // PREDICT
                    if(!isset($companysetting->predict)) {
                        $companysetting->predict = new stdClass();
                    }
                    if(!isset($companysetting->predict->Prepaid)) {
                        $companysetting->predict->Prepaid = new stdClass();
                    }
                    if(!isset($companysetting->predict->Prepaid->PredictPlatformFee)) {
                        $companysetting->predict->Prepaid->PredictPlatformFee = isset($agency_default_price->predict->Prepaid->PredictPlatformFee) ? $agency_default_price->predict->Prepaid->PredictPlatformFee : $comset_val->predict->Prepaid->PredictPlatformFee;
                    }
                    if(!isset($companysetting->predict->Prepaid->PredictMinCostMonth)) {
                        $companysetting->predict->Prepaid->PredictMinCostMonth = isset($agency_default_price->predict->Prepaid->PredictMinCostMonth) ? $agency_default_price->predict->Prepaid->PredictMinCostMonth : $comset_val->predict->Prepaid->PredictMinCostMonth;
                    }
                    // PREDICT

                }
            }else{ // jika tidak ada ambil dari agencydefaultprice
                /** FIND COMPANY PARENT AND FIND COST AGENCY DEFAULT PRICE*/
                $userClient = User::select('company_parent')->where('company_id','=',$companyID)->where('user_type','=','client')->get();
                /** FIND COMPANY PARENT AND FIND COST AGENCY DEFAULT PRICE*/
                if (count($userClient) > 0) {
                    $companyParentID = $userClient[0]['company_parent'];
                    $getcompanysetting = CompanySetting::where('company_id',$companyParentID)->whereEncrypted('setting_name','agencydefaultprice')->get();
                    
                    if (count($getcompanysetting) > 0) {
                        $companysetting = json_decode($getcompanysetting[0]['setting_value']);

                        if ($companysetting != "") {
                            $comset_val = $this->getcompanysetting($idSys,'rootagencydefaultprice');

                            if(!isset($companysetting->enhance)) {
            
                                $companysetting->enhance = new stdClass();
                                $companysetting->enhance->Weekly = new stdClass();
                                $companysetting->enhance->Monthly = new stdClass();
                                $companysetting->enhance->OneTime = new stdClass();
                                $companysetting->enhance->Prepaid = new stdClass();
            
                                /* WEEKLY */
                                $companysetting->enhance->Weekly->EnhanceCostperlead = $comset_val->enhance->Weekly->EnhanceCostperlead;
                                $companysetting->enhance->Weekly->EnhanceMinCostMonth = $comset_val->enhance->Weekly->EnhanceMinCostMonth;
                                $companysetting->enhance->Weekly->EnhancePlatformFee = $comset_val->enhance->Weekly->EnhancePlatformFee;
                                /* WEEKLY */
                                
                                /* MONTHLY */
                                $companysetting->enhance->Monthly->EnhanceCostperlead = $comset_val->enhance->Monthly->EnhanceCostperlead;
                                $companysetting->enhance->Monthly->EnhanceMinCostMonth = $comset_val->enhance->Monthly->EnhanceMinCostMonth;
                                $companysetting->enhance->Monthly->EnhancePlatformFee = $comset_val->enhance->Monthly->EnhancePlatformFee;
                                /* MONTHLY */
                                
                                /* ONETIME */
                                $companysetting->enhance->OneTime->EnhanceCostperlead = $comset_val->enhance->OneTime->EnhanceCostperlead;
                                $companysetting->enhance->OneTime->EnhanceMinCostMonth = $comset_val->enhance->OneTime->EnhanceMinCostMonth;
                                $companysetting->enhance->OneTime->EnhancePlatformFee = $comset_val->enhance->OneTime->EnhancePlatformFee;
                                /* ONETIME */
            
                                /* PREPAID */
                                $companysetting->enhance->Prepaid->EnhanceCostperlead = $comset_val->enhance->Prepaid->EnhanceCostperlead;
                                $companysetting->enhance->Prepaid->EnhanceMinCostMonth = $comset_val->enhance->Prepaid->EnhanceMinCostMonth;
                                $companysetting->enhance->Prepaid->EnhancePlatformFee = $comset_val->enhance->Prepaid->EnhancePlatformFee;
                                /* PREPAID */
                            }

                            if(!isset($companysetting->b2b)) {
            
                                $companysetting->b2b = new stdClass();
                                $companysetting->b2b->Weekly = new stdClass();
                                $companysetting->b2b->Monthly = new stdClass();
                                $companysetting->b2b->OneTime = new stdClass();
                                $companysetting->b2b->Prepaid = new stdClass();
            
                                /* WEEKLY */
                                $companysetting->b2b->Weekly->B2bCostperlead = $comset_val->b2b->Weekly->B2bCostperlead;
                                $companysetting->b2b->Weekly->B2bMinCostMonth = $comset_val->b2b->Weekly->B2bMinCostMonth;
                                $companysetting->b2b->Weekly->B2bPlatformFee = $comset_val->b2b->Weekly->B2bPlatformFee;
                                /* WEEKLY */
                                
                                /* MONTHLY */
                                $companysetting->b2b->Monthly->B2bCostperlead = $comset_val->b2b->Monthly->B2bCostperlead;
                                $companysetting->b2b->Monthly->B2bMinCostMonth = $comset_val->b2b->Monthly->B2bMinCostMonth;
                                $companysetting->b2b->Monthly->B2bPlatformFee = $comset_val->b2b->Monthly->B2bPlatformFee;
                                /* MONTHLY */
                                
                                /* ONETIME */
                                $companysetting->b2b->OneTime->B2bCostperlead = $comset_val->b2b->OneTime->B2bCostperlead;
                                $companysetting->b2b->OneTime->B2bMinCostMonth = $comset_val->b2b->OneTime->B2bMinCostMonth;
                                $companysetting->b2b->OneTime->B2bPlatformFee = $comset_val->b2b->OneTime->B2bPlatformFee;
                                /* ONETIME */
            
                                /* PREPAID */
                                $companysetting->b2b->Prepaid->B2bCostperlead = $comset_val->b2b->Prepaid->B2bCostperlead;
                                $companysetting->b2b->Prepaid->B2bMinCostMonth = $comset_val->b2b->Prepaid->B2bMinCostMonth;
                                $companysetting->b2b->Prepaid->B2bPlatformFee = $comset_val->b2b->Prepaid->B2bPlatformFee;
                                /* PREPAID */
                            }

                            if (!isset($companysetting->local->Prepaid)) {
                                $newPrepaidLocal = (object) [
                                    "LeadspeekCostperlead" => $companysetting->local->Weekly->LeadspeekCostperlead,
                                    "LeadspeekMinCostMonth" => $companysetting->local->Weekly->LeadspeekMinCostMonth,
                                    "LeadspeekPlatformFee" => $companysetting->local->Weekly->LeadspeekPlatformFee,
                                    "LeadspeekLeadsPerday" => "10"
                                ];
            
                                $newPrepaidLocator = (object) [
                                    "LocatorCostperlead" => $companysetting->locator->Weekly->LocatorCostperlead,
                                    "LocatorMinCostMonth" => $companysetting->locator->Weekly->LocatorMinCostMonth,
                                    "LocatorPlatformFee" => $companysetting->locator->Weekly->LocatorPlatformFee,
                                    "LocatorLeadsPerday" => "10"
                                ];

                                $newPrepaidEnhance = (object) [
                                    "EnhanceCostperlead" => $comset_val->enhance->Prepaid->EnhanceCostperlead,
                                    "EnhanceMinCostMonth" => $comset_val->enhance->Prepaid->EnhanceMinCostMonth,
                                    "EnhancePlatformFee" => $comset_val->enhance->Prepaid->EnhancePlatformFee,
                                    "EnhanceLeadsPerday" => "10"
                                ];

                                $newPrepaidB2b = (object) [
                                    "B2bCostperlead" => $comset_val->b2b->Prepaid->B2bCostperlead,
                                    "B2bMinCostMonth" => $comset_val->b2b->Prepaid->B2bMinCostMonth,
                                    "B2bPlatformFee" => $comset_val->b2b->Prepaid->B2bPlatformFee,
                                    "B2bLeadsPerday" => "10"
                                ];

                                $companysetting->local->Prepaid = $newPrepaidLocal;
                                $companysetting->locator->Prepaid = $newPrepaidLocator;
                                $companysetting->enhance->Prepaid = $newPrepaidEnhance;
                                $companysetting->b2b->Prepaid = $newPrepaidB2b;
                            }

                            // LOCAL MARKUP
                            if(!isset($companysetting->local->Weekly->LeadspeekCostperleadAdvanced)) {
                                // info('LOCAL MARKUP rootagencydefaultprice else Weekly 1');
                                $companysetting->local->Weekly->LeadspeekCostperleadAdvanced = $comset_val->local->Weekly->LeadspeekCostperleadAdvanced;
                            }
                            if(!isset($companysetting->local->Monthly->LeadspeekCostperleadAdvanced)) {
                                // info('LOCAL MARKUP rootagencydefaultprice else Monthly 1');
                                $companysetting->local->Monthly->LeadspeekCostperleadAdvanced = $comset_val->local->Monthly->LeadspeekCostperleadAdvanced;
                            } 
                            if(!isset($companysetting->local->OneTime->LeadspeekCostperleadAdvanced)) {
                                // info('LOCAL MARKUP rootagencydefaultprice else OneTime 1');
                                $companysetting->local->OneTime->LeadspeekCostperleadAdvanced = $comset_val->local->OneTime->LeadspeekCostperleadAdvanced;
                            } 
                            if(!isset($companysetting->local->Prepaid->LeadspeekCostperleadAdvanced)) {
                                // info('LOCAL MARKUP rootagencydefaultprice else Prepaid 1');
                                $companysetting->local->Prepaid->LeadspeekCostperleadAdvanced = $comset_val->local->Prepaid->LeadspeekCostperleadAdvanced;
                            } 
                            // LOCAL MARKUP

                            // SIMPLIFI
                            if(!isset($companysetting->simplifi)) {
                                $companysetting->simplifi = new stdClass();
                                $companysetting->simplifi->Prepaid = new stdClass();

                                /* PREPAID */
                                $companysetting->simplifi->Prepaid->SimplifiMaxBid = $comset_val->simplifi->Prepaid->SimplifiMaxBid;
                                $companysetting->simplifi->Prepaid->SimplifiDailyBudget = $comset_val->simplifi->Prepaid->SimplifiDailyBudget;
                                $companysetting->simplifi->Prepaid->SimplifiAgencyMarkup = $comset_val->simplifi->Prepaid->SimplifiAgencyMarkup;
                                /* PREPAID */
                            }
                            // SIMPLIFI

                            // PREDICT
                            if(!isset($companysetting->predict)) {
                                $companysetting->predict = new stdClass();
                            }
                            if(!isset($companysetting->predict->Prepaid)) {
                                $companysetting->predict->Prepaid = new stdClass();
                            }
                            if(!isset($companysetting->predict->Prepaid->PredictPlatformFee)) {
                                $companysetting->predict->Prepaid->PredictPlatformFee = $comset_val->predict->Prepaid->PredictPlatformFee;
                            }
                            if(!isset($companysetting->predict->Prepaid->PredictMinCostMonth)) {
                                $companysetting->predict->Prepaid->PredictMinCostMonth = $comset_val->predict->Prepaid->PredictMinCostMonth;
                            }
                            // PREDICT
                        }

                        $companysetting->local->Monthly->LeadspeekLeadsPerday = '10';
                        $companysetting->local->Weekly->LeadspeekLeadsPerday = '10';
                        $companysetting->local->OneTime->LeadspeekLeadsPerday = '10';
                        $companysetting->local->Prepaid->LeadspeekLeadsPerday = '10';

                        $companysetting->locator->Monthly->LocatorLeadsPerday = '10';
                        $companysetting->locator->Weekly->LocatorLeadsPerday = '10';
                        $companysetting->locator->OneTime->LocatorLeadsPerday = '10';
                        $companysetting->locator->Prepaid->LocatorLeadsPerday = '10';

                        $companysetting->enhance->Monthly->EnhanceLeadsPerday = ($clientMinLeadDayEnhance !== '') ? $clientMinLeadDayEnhance : "10";
                        $companysetting->enhance->Weekly->EnhanceLeadsPerday = ($clientMinLeadDayEnhance !== '') ? $clientMinLeadDayEnhance : "10";
                        $companysetting->enhance->OneTime->EnhanceLeadsPerday = ($clientMinLeadDayEnhance !== '') ? $clientMinLeadDayEnhance : "10";
                        $companysetting->enhance->Prepaid->EnhanceLeadsPerday = ($clientMinLeadDayEnhance !== '') ? $clientMinLeadDayEnhance : "10";

                        $companysetting->b2b->Monthly->B2bLeadsPerday = ($clientMinLeadDayEnhance !== '') ? $clientMinLeadDayEnhance : "10";
                        $companysetting->b2b->Weekly->B2bLeadsPerday = ($clientMinLeadDayEnhance !== '') ? $clientMinLeadDayEnhance : "10";
                        $companysetting->b2b->OneTime->B2bLeadsPerday = ($clientMinLeadDayEnhance !== '') ? $clientMinLeadDayEnhance : "10";
                        $companysetting->b2b->Prepaid->B2bLeadsPerday = ($clientMinLeadDayEnhance !== '') ? $clientMinLeadDayEnhance : "10";

                        /* CHECK ENABLE MINIMUM LEADS PER DAY */
                        if(!isset($companysetting->local->EnableMinimumLeadsPerday))
                            $companysetting->local->EnableMinimumLeadsPerday = false;
                        if(!isset($companysetting->locator->EnableMinimumLeadsPerday))
                            $companysetting->locator->EnableMinimumLeadsPerday = false;
                        if(!isset($companysetting->enhance->EnableMinimumLeadsPerday))
                            $companysetting->enhance->EnableMinimumLeadsPerday = false;
                        if(!isset($companysetting->b2b->EnableMinimumLeadsPerday))
                            $companysetting->b2b->EnableMinimumLeadsPerday = false;
                        /* CHECK ENABLE MINIMUM LEADS PER DAY */

                        // $companysetting->local->Monthly->LeadspeekCostperlead = $companysetting->locatorlead->FirstName_LastName_MailingAddress_Phone;
                        // $companysetting->local->Weekly->LeadspeekCostperlead = $companysetting->locatorlead->FirstName_LastName_MailingAddress_Phone;
                        // $companysetting->local->OneTime->LeadspeekCostperlead = $companysetting->locatorlead->FirstName_LastName_MailingAddress_Phone;

                        // $companysetting->locator->Monthly->LeadspeekCostperlead = $companysetting->locatorlead->FirstName_LastName_MailingAddress_Phone;
                        // $companysetting->locator->Weekly->LeadspeekCostperlead = $companysetting->locatorlead->FirstName_LastName_MailingAddress_Phone;
                        // $companysetting->locator->OneTime->LeadspeekCostperlead = $companysetting->locatorlead->FirstName_LastName_MailingAddress_Phone;

                    } else {
                        $companysetting = $this->getcompanysetting($idSys, 'rootagencydefaultprice');
                    }
                }
            }
        }else{
            /** IF FOR CLIENT COST AGENCY */

            /** GET SETTING MENU MODULE */
            $getcompanysetting = CompanySetting::where('company_id',$companyID)->whereEncrypted('setting_name',$settingname)->get();
            $companysetting = "";
            if (count($getcompanysetting) > 0) {
                $companysetting = json_decode($getcompanysetting[0]['setting_value']);
            }
        }

        if ($companysetting == "") {
            $companysetting = $this->check_email_template($settingname,$companyID);
        }
        /** GET SETTING MENU MODULE */
        
        /** GET DEFAULT PAYMENT TERM */
        $defaultpaymentterm = 'Weekly';
        if($settingname == "agencydefaultprice") {
            // default paymenterm
            $getdefpay = Company::find($companyID);
            if ($getdefpay->count() > 0) {
                $defaultpaymentterm = $getdefpay->paymentterm_default;
                
            }
            // default paymenterm

            // untuk costagency
            $getcompanysetting = CompanySetting::where('company_id',$companyID)->whereEncrypted('setting_name','costagency')->get();
            $rootcostagency = "";
            if (count($getcompanysetting) > 0) {
                $rootcostagency = json_decode($getcompanysetting[0]['setting_value']);
            }
            if ($rootcostagency != "") {
                $comset_val = $this->getcompanysetting($idSys,'rootcostagency');

                if(!isset($rootcostagency->enhance)) {
                    $rootcostagency->enhance = new stdClass();
                    $rootcostagency->enhance->Weekly = new stdClass();
                    $rootcostagency->enhance->Monthly = new stdClass();
                    $rootcostagency->enhance->OneTime = new stdClass();
                    $rootcostagency->enhance->Prepaid = new stdClass();

                    /* WEEKLY */
                    $rootcostagency->enhance->Weekly->EnhanceCostperlead = $comset_val->enhance->Weekly->EnhanceCostperlead;
                    $rootcostagency->enhance->Weekly->EnhanceMinCostMonth = $comset_val->enhance->Weekly->EnhanceMinCostMonth;
                    $rootcostagency->enhance->Weekly->EnhancePlatformFee = $comset_val->enhance->Weekly->EnhancePlatformFee;
                    /* WEEKLY */
                    
                    /* MONTHLY */
                    $rootcostagency->enhance->Monthly->EnhanceCostperlead = $comset_val->enhance->Monthly->EnhanceCostperlead;
                    $rootcostagency->enhance->Monthly->EnhanceMinCostMonth = $comset_val->enhance->Monthly->EnhanceMinCostMonth;
                    $rootcostagency->enhance->Monthly->EnhancePlatformFee = $comset_val->enhance->Monthly->EnhancePlatformFee;
                    /* MONTHLY */
                    
                    /* ONETIME */
                    $rootcostagency->enhance->OneTime->EnhanceCostperlead = $comset_val->enhance->OneTime->EnhanceCostperlead;
                    $rootcostagency->enhance->OneTime->EnhanceMinCostMonth = $comset_val->enhance->OneTime->EnhanceMinCostMonth;
                    $rootcostagency->enhance->OneTime->EnhancePlatformFee = $comset_val->enhance->OneTime->EnhancePlatformFee;
                    /* ONETIME */

                    /* PREPAID */
                    $rootcostagency->enhance->Prepaid->EnhanceCostperlead = $comset_val->enhance->Prepaid->EnhanceCostperlead;
                    $rootcostagency->enhance->Prepaid->EnhanceMinCostMonth = $comset_val->enhance->Prepaid->EnhanceMinCostMonth;
                    $rootcostagency->enhance->Prepaid->EnhancePlatformFee = $comset_val->enhance->Prepaid->EnhancePlatformFee;
                    /* PREPAID */
                }

                if(!isset($rootcostagency->b2b)) {
                    $rootcostagency->b2b = new stdClass();
                    $rootcostagency->b2b->Weekly = new stdClass();
                    $rootcostagency->b2b->Monthly = new stdClass();
                    $rootcostagency->b2b->OneTime = new stdClass();
                    $rootcostagency->b2b->Prepaid = new stdClass();

                    /* WEEKLY */
                    $rootcostagency->b2b->Weekly->B2bCostperlead = $comset_val->b2b->Weekly->B2bCostperlead;
                    $rootcostagency->b2b->Weekly->B2bMinCostMonth = $comset_val->b2b->Weekly->B2bMinCostMonth;
                    $rootcostagency->b2b->Weekly->B2bPlatformFee = $comset_val->b2b->Weekly->B2bPlatformFee;
                    /* WEEKLY */
                    
                    /* MONTHLY */
                    $rootcostagency->b2b->Monthly->B2bCostperlead = $comset_val->b2b->Monthly->B2bCostperlead;
                    $rootcostagency->b2b->Monthly->B2bMinCostMonth = $comset_val->b2b->Monthly->B2bMinCostMonth;
                    $rootcostagency->b2b->Monthly->B2bPlatformFee = $comset_val->b2b->Monthly->B2bPlatformFee;
                    /* MONTHLY */
                    
                    /* ONETIME */
                    $rootcostagency->b2b->OneTime->B2bCostperlead = $comset_val->b2b->OneTime->B2bCostperlead;
                    $rootcostagency->b2b->OneTime->B2bMinCostMonth = $comset_val->b2b->OneTime->B2bMinCostMonth;
                    $rootcostagency->b2b->OneTime->B2bPlatformFee = $comset_val->b2b->OneTime->B2bPlatformFee;
                    /* ONETIME */

                    /* PREPAID */
                    $rootcostagency->b2b->Prepaid->B2bCostperlead = $comset_val->b2b->Prepaid->B2bCostperlead;
                    $rootcostagency->b2b->Prepaid->B2bMinCostMonth = $comset_val->b2b->Prepaid->B2bMinCostMonth;
                    $rootcostagency->b2b->Prepaid->B2bPlatformFee = $comset_val->b2b->Prepaid->B2bPlatformFee;
                    /* PREPAID */
                }

                if (!isset($rootcostagency->local->Prepaid)) {
                    $newPrepaidLocal = (object) [
                        "LeadspeekCostperlead" => $rootcostagency->local->Weekly->LeadspeekCostperlead,
                        "LeadspeekMinCostMonth" => $rootcostagency->local->Weekly->LeadspeekMinCostMonth,
                        "LeadspeekPlatformFee" => $rootcostagency->local->Weekly->LeadspeekPlatformFee
                    ];

                    $newPrepaidLocator = (object) [
                        "LocatorCostperlead" => $rootcostagency->locator->Weekly->LocatorCostperlead,
                        "LocatorMinCostMonth" => $rootcostagency->locator->Weekly->LocatorMinCostMonth,
                        "LocatorPlatformFee" => $rootcostagency->locator->Weekly->LocatorPlatformFee
                    ];
                    
                    $newPrepaidEnhance = (object) [
                        "EnhanceCostperlead" => $comset_val->enhance->Prepaid->EnhanceCostperlead,
                        "EnhanceMinCostMonth" => $comset_val->enhance->Prepaid->EnhanceMinCostMonth,
                        "EnhancePlatformFee" => $comset_val->enhance->Prepaid->EnhancePlatformFee
                    ];

                    $newPrepaidB2b = (object) [
                        "B2bCostperlead" => $comset_val->b2b->Prepaid->B2bCostperlead,
                        "B2bMinCostMonth" => $comset_val->b2b->Prepaid->B2bMinCostMonth,
                        "B2bPlatformFee" => $comset_val->b2b->Prepaid->B2bPlatformFee
                    ];

                    $rootcostagency->local->Prepaid = $newPrepaidLocal;
                    $rootcostagency->locator->Prepaid = $newPrepaidLocator;
                    $rootcostagency->enhance->Prepaid = $newPrepaidEnhance;
                    $rootcostagency->b2b->Prepaid = $newPrepaidB2b;
                }

                // LOCAL MARKUP
                if(!isset($rootcostagency->local->Weekly->LeadspeekCostperleadAdvanced)) {
                    // info('LOCAL MARKUP rootcostagency Weekly 1');
                    $rootcostagency->local->Weekly->LeadspeekCostperleadAdvanced = $comset_val->local->Weekly->LeadspeekCostperleadAdvanced;
                }
                if(!isset($rootcostagency->local->Monthly->LeadspeekCostperleadAdvanced)) {
                    // info('LOCAL MARKUP rootcostagency Monthly 1');
                    $rootcostagency->local->Monthly->LeadspeekCostperleadAdvanced = $comset_val->local->Monthly->LeadspeekCostperleadAdvanced;
                } 
                if(!isset($rootcostagency->local->OneTime->LeadspeekCostperleadAdvanced)) {
                    // info('LOCAL MARKUP rootcostagency OneTime 1');
                    $rootcostagency->local->OneTime->LeadspeekCostperleadAdvanced = $comset_val->local->OneTime->LeadspeekCostperleadAdvanced;
                } 
                if(!isset($rootcostagency->local->Prepaid->LeadspeekCostperleadAdvanced)) {
                    // info('LOCAL MARKUP rootcostagency Prepaid 1');
                    $rootcostagency->local->Prepaid->LeadspeekCostperleadAdvanced = $comset_val->local->Prepaid->LeadspeekCostperleadAdvanced;
                }
                // LOCAL MARKUP

                /* CHECK COSTPERLEAD IN COSTAGENCY UNDER COSTPERLEAD IN ROOTCOSTAGENCY , ONLY ENHANCE */
                // if(isset($rootcostagency->enhance->Weekly->EnhanceCostperlead) && isset($comset_val->enhance->Weekly->EnhanceCostperlead)) {
                //     $rootcostagency->enhance->Weekly->EnhanceCostperlead = ($rootcostagency->enhance->Weekly->EnhanceCostperlead > $comset_val->enhance->Weekly->EnhanceCostperlead) ? $rootcostagency->enhance->Weekly->EnhanceCostperlead : $comset_val->enhance->Weekly->EnhanceCostperlead;
                // }
                // if(isset($rootcostagency->enhance->Monthly->EnhanceCostperlead) && isset($comset_val->enhance->Monthly->EnhanceCostperlead)) {
                //     $rootcostagency->enhance->Monthly->EnhanceCostperlead = ($rootcostagency->enhance->Monthly->EnhanceCostperlead > $comset_val->enhance->Monthly->EnhanceCostperlead) ? $rootcostagency->enhance->Monthly->EnhanceCostperlead : $comset_val->enhance->Monthly->EnhanceCostperlead;
                // }
                // if(isset($rootcostagency->enhance->OneTime->EnhanceCostperlead) && isset($comset_val->enhance->OneTime->EnhanceCostperlead)) {
                //     $rootcostagency->enhance->OneTime->EnhanceCostperlead = ($rootcostagency->enhance->OneTime->EnhanceCostperlead > $comset_val->enhance->OneTime->EnhanceCostperlead) ? $rootcostagency->enhance->OneTime->EnhanceCostperlead : $comset_val->enhance->OneTime->EnhanceCostperlead;
                // }
                // if(isset($rootcostagency->enhance->Prepaid->EnhanceCostperlead) && isset($comset_val->enhance->Prepaid->EnhanceCostperlead)) {
                //     $rootcostagency->enhance->Prepaid->EnhanceCostperlead = ($rootcostagency->enhance->Prepaid->EnhanceCostperlead > $comset_val->enhance->Prepaid->EnhanceCostperlead) ? $rootcostagency->enhance->Prepaid->EnhanceCostperlead : $comset_val->enhance->Prepaid->EnhanceCostperlead;
                // }
                /* CHECK COSTPERLEAD IN COSTAGENCY UNDER COSTPERLEAD IN ROOTCOSTAGENCY , ONLY ENHANCE */
            }
            // untuk costagency

            // untuk agencydefaultprice
            if ($companysetting != "") {
                $comset_val = $this->getcompanysetting($idSys,'rootagencydefaultprice');

                if(!isset($companysetting->enhance)) {
                    $companysetting->enhance = new stdClass();
                    $companysetting->enhance->Weekly = new stdClass();
                    $companysetting->enhance->Monthly = new stdClass();
                    $companysetting->enhance->OneTime = new stdClass();
                    $companysetting->enhance->Prepaid = new stdClass();

                    /* WEEKLY */
                    $companysetting->enhance->Weekly->EnhanceCostperlead = $comset_val->enhance->Weekly->EnhanceCostperlead;
                    $companysetting->enhance->Weekly->EnhanceMinCostMonth = $comset_val->enhance->Weekly->EnhanceMinCostMonth;
                    $companysetting->enhance->Weekly->EnhancePlatformFee = $comset_val->enhance->Weekly->EnhancePlatformFee;
                    /* WEEKLY */
                    
                    /* MONTHLY */
                    $companysetting->enhance->Monthly->EnhanceCostperlead = $comset_val->enhance->Monthly->EnhanceCostperlead;
                    $companysetting->enhance->Monthly->EnhanceMinCostMonth = $comset_val->enhance->Monthly->EnhanceMinCostMonth;
                    $companysetting->enhance->Monthly->EnhancePlatformFee = $comset_val->enhance->Monthly->EnhancePlatformFee;
                    /* MONTHLY */
                    
                    /* ONETIME */
                    $companysetting->enhance->OneTime->EnhanceCostperlead = $comset_val->enhance->OneTime->EnhanceCostperlead;
                    $companysetting->enhance->OneTime->EnhanceMinCostMonth = $comset_val->enhance->OneTime->EnhanceMinCostMonth;
                    $companysetting->enhance->OneTime->EnhancePlatformFee = $comset_val->enhance->OneTime->EnhancePlatformFee;
                    /* ONETIME */

                    /* PREPAID */
                    $companysetting->enhance->Prepaid->EnhanceCostperlead = $comset_val->enhance->Prepaid->EnhanceCostperlead;
                    $companysetting->enhance->Prepaid->EnhanceMinCostMonth = $comset_val->enhance->Prepaid->EnhanceMinCostMonth;
                    $companysetting->enhance->Prepaid->EnhancePlatformFee = $comset_val->enhance->Prepaid->EnhancePlatformFee;
                    /* PREPAID */
                }

                if(!isset($companysetting->b2b)) {
                    $companysetting->b2b = new stdClass();
                    $companysetting->b2b->Weekly = new stdClass();
                    $companysetting->b2b->Monthly = new stdClass();
                    $companysetting->b2b->OneTime = new stdClass();
                    $companysetting->b2b->Prepaid = new stdClass();

                    /* WEEKLY */
                    $companysetting->b2b->Weekly->B2bCostperlead = $comset_val->b2b->Weekly->B2bCostperlead;
                    $companysetting->b2b->Weekly->B2bMinCostMonth = $comset_val->b2b->Weekly->B2bMinCostMonth;
                    $companysetting->b2b->Weekly->B2bPlatformFee = $comset_val->b2b->Weekly->B2bPlatformFee;
                    /* WEEKLY */
                    
                    /* MONTHLY */
                    $companysetting->b2b->Monthly->B2bCostperlead = $comset_val->b2b->Monthly->B2bCostperlead;
                    $companysetting->b2b->Monthly->B2bMinCostMonth = $comset_val->b2b->Monthly->B2bMinCostMonth;
                    $companysetting->b2b->Monthly->B2bPlatformFee = $comset_val->b2b->Monthly->B2bPlatformFee;
                    /* MONTHLY */
                    
                    /* ONETIME */
                    $companysetting->b2b->OneTime->B2bCostperlead = $comset_val->b2b->OneTime->B2bCostperlead;
                    $companysetting->b2b->OneTime->B2bMinCostMonth = $comset_val->b2b->OneTime->B2bMinCostMonth;
                    $companysetting->b2b->OneTime->B2bPlatformFee = $comset_val->b2b->OneTime->B2bPlatformFee;
                    /* ONETIME */

                    /* PREPAID */
                    $companysetting->b2b->Prepaid->B2bCostperlead = $comset_val->b2b->Prepaid->B2bCostperlead;
                    $companysetting->b2b->Prepaid->B2bMinCostMonth = $comset_val->b2b->Prepaid->B2bMinCostMonth;
                    $companysetting->b2b->Prepaid->B2bPlatformFee = $comset_val->b2b->Prepaid->B2bPlatformFee;
                    /* PREPAID */
                }

                if (!isset($companysetting->local->Prepaid)) {
                    $newPrepaidLocal = (object) [
                        "LeadspeekCostperlead" => $companysetting->local->Weekly->LeadspeekCostperlead,
                        "LeadspeekMinCostMonth" => $companysetting->local->Weekly->LeadspeekMinCostMonth,
                        "LeadspeekPlatformFee" => $companysetting->local->Weekly->LeadspeekPlatformFee
                    ];

                    $newPrepaidLocator = (object) [
                        "LocatorCostperlead" => $companysetting->locator->Weekly->LocatorCostperlead,
                        "LocatorMinCostMonth" => $companysetting->locator->Weekly->LocatorMinCostMonth,
                        "LocatorPlatformFee" => $companysetting->locator->Weekly->LocatorPlatformFee
                    ];

                    $newPrepaidEnhance = (object) [
                        "EnhanceCostperlead" => $comset_val->enhance->Prepaid->EnhanceCostperlead,
                        "EnhanceMinCostMonth" => $comset_val->enhance->Prepaid->EnhanceMinCostMonth,
                        "EnhancePlatformFee" => $comset_val->enhance->Prepaid->EnhancePlatformFee
                    ];
                    
                    $newPrepaidB2b = (object) [
                        "B2bCostperlead" => $comset_val->b2b->Prepaid->B2bCostperlead,
                        "B2bMinCostMonth" => $comset_val->b2b->Prepaid->B2bMinCostMonth,
                        "B2bPlatformFee" => $comset_val->b2b->Prepaid->B2bPlatformFee
                    ];

                    $companysetting->local->Prepaid = $newPrepaidLocal;
                    $companysetting->locator->Prepaid = $newPrepaidLocator;
                    $companysetting->enhance->Prepaid = $newPrepaidEnhance;
                    $companysetting->b2b->Prepaid = $newPrepaidB2b;
                }

                // LOCAL MARKUP
                if(!isset($companysetting->local->Weekly->LeadspeekCostperleadAdvanced)) {
                    // info('LOCAL MARKUP rootagencydefaultprice Weekly 1');
                    $companysetting->local->Weekly->LeadspeekCostperleadAdvanced = $comset_val->local->Weekly->LeadspeekCostperleadAdvanced;
                }
                if(!isset($companysetting->local->Monthly->LeadspeekCostperleadAdvanced)) {
                    // info('LOCAL MARKUP rootagencydefaultprice Monthly 1');
                    $companysetting->local->Monthly->LeadspeekCostperleadAdvanced = $comset_val->local->Monthly->LeadspeekCostperleadAdvanced;
                } 
                if(!isset($companysetting->local->OneTime->LeadspeekCostperleadAdvanced)) {
                    // info('LOCAL MARKUP rootagencydefaultprice OneTime 1');
                    $companysetting->local->OneTime->LeadspeekCostperleadAdvanced = $comset_val->local->OneTime->LeadspeekCostperleadAdvanced;
                } 
                if(!isset($companysetting->local->Prepaid->LeadspeekCostperleadAdvanced)) {
                    // info('LOCAL MARKUP rootagencydefaultprice Prepaid 1');
                    $companysetting->local->Prepaid->LeadspeekCostperleadAdvanced = $comset_val->local->Prepaid->LeadspeekCostperleadAdvanced;
                } 
                // LOCAL MARKUP

                // SIMPLIFI
                if(!isset($companysetting->simplifi)) {
                    $companysetting->simplifi = new stdClass();
                    $companysetting->simplifi->Prepaid = new stdClass();

                    /* PREPAID */
                    $companysetting->simplifi->Prepaid->SimplifiMaxBid = $comset_val->simplifi->Prepaid->SimplifiMaxBid;
                    $companysetting->simplifi->Prepaid->SimplifiDailyBudget = $comset_val->simplifi->Prepaid->SimplifiDailyBudget;
                    $companysetting->simplifi->Prepaid->SimplifiAgencyMarkup = $comset_val->simplifi->Prepaid->SimplifiAgencyMarkup;
                    /* PREPAID */
                }
                // SIMPLIFI

                // PREDICT ID
                if(!isset($companysetting->predict)) {
                    $companysetting->predict = new stdClass();
                }
                if(!isset($companysetting->predict->Prepaid)) {
                    $companysetting->predict->Prepaid = new stdClass();
                }
                if(!isset($companysetting->predict->Prepaid->PredictPlatformFee)) {
                    $companysetting->predict->Prepaid->PredictPlatformFee = $comset_val->predict->Prepaid->PredictPlatformFee;
                }
                if(!isset($companysetting->predict->Prepaid->PredictMinCostMonth)) {
                    $companysetting->predict->Prepaid->PredictMinCostMonth = $comset_val->predict->Prepaid->PredictMinCostMonth;
                }
                // PREDICT ID
            } else {
                $companysetting = $this->getcompanysetting($idSys, 'rootagencydefaultprice');
            }
            // untuk agencydefaultprice
        }else if ($settingname == "clientdefaultprice") {
            $getdefpay = Company::find($companyID);
           
            if ($getdefpay->count() > 0 && $clientdefaultprice === true) {
                $defaultpaymentterm = $getdefpay->paymentterm_default;
            }else{
                /** FIND COMPANY PARENT AND FIND COST AGENCY DEFAULT PRICE*/
                $userClient = User::select('company_parent')->where('company_id','=',$companyID)->where('user_type','=','client')->get();
                /** FIND COMPANY PARENT AND FIND COST AGENCY DEFAULT PRICE*/
                if (count($userClient) > 0) {
                    $companyParentID = $userClient[0]['company_parent'];
                    $getdefpay = Company::find($companyParentID);
                    if ($getdefpay->count() > 0) {
                        $defaultpaymentterm = $getdefpay->paymentterm_default;
                    }
                }
            }
        }else if ($settingname == 'costagency'){
            /* GET CLIENT MIN LEAD DAYS */
            $rootSetting = $this->getcompanysetting($idSys, 'rootsetting');
            $clientMinLeadDayEnhance = (isset($rootSetting->clientminleadday))?$rootSetting->clientminleadday:"";
            /* GET CLIENT MIN LEAD DAYS */

            /* GET ROOT COST AGENCY */
            $rootcostagency = $this->getcompanysetting($idSys, 'rootcostagency');
            /* GET ROOT COST AGENCY */
            
            /* GET CLIENT TYPE LEAD */
            // $rootSetting = $this->getcompanysetting($idSys, 'rootsetting');

            if(!empty($rootSetting->clientcaplead)) {
                $clientTypeLead['type'] = 'clientcaplead';
                $clientTypeLead['value'] = $rootSetting->clientcaplead;
            }
            if(!empty($rootSetting->clientcapleadpercentage)) {
                $clientTypeLead['type'] = 'clientcapleadpercentage';
                $clientTypeLead['value'] = $rootSetting->clientcapleadpercentage;
            }
            /* GET CLIENT TYPE LEAD */

            $getdefpay = Company::find($companyID);
            if ($getdefpay->count() > 0) {
                $defaultpaymentterm = $getdefpay->paymentterm_default;
            }

            if ($companysetting != "") {
                $comset_val = $this->getcompanysetting($idSys,'rootcostagency');

                if(!isset($companysetting->enhance)) { // ENHANCE
                    $companysetting->enhance = new stdClass();
                    $companysetting->enhance->Weekly = new stdClass();
                    $companysetting->enhance->Monthly = new stdClass();
                    $companysetting->enhance->OneTime = new stdClass();
                    $companysetting->enhance->Prepaid = new stdClass();

                    /* WEEKLY */
                    $companysetting->enhance->Weekly->EnhanceCostperlead = $comset_val->enhance->Weekly->EnhanceCostperlead;
                    $companysetting->enhance->Weekly->EnhanceMinCostMonth = $comset_val->enhance->Weekly->EnhanceMinCostMonth;
                    $companysetting->enhance->Weekly->EnhancePlatformFee = $comset_val->enhance->Weekly->EnhancePlatformFee;
                    /* WEEKLY */
                    
                    /* MONTHLY */
                    $companysetting->enhance->Monthly->EnhanceCostperlead = $comset_val->enhance->Monthly->EnhanceCostperlead;
                    $companysetting->enhance->Monthly->EnhanceMinCostMonth = $comset_val->enhance->Monthly->EnhanceMinCostMonth;
                    $companysetting->enhance->Monthly->EnhancePlatformFee = $comset_val->enhance->Monthly->EnhancePlatformFee;
                    /* MONTHLY */
                    
                    /* ONETIME */
                    $companysetting->enhance->OneTime->EnhanceCostperlead = $comset_val->enhance->OneTime->EnhanceCostperlead;
                    $companysetting->enhance->OneTime->EnhanceMinCostMonth = $comset_val->enhance->OneTime->EnhanceMinCostMonth;
                    $companysetting->enhance->OneTime->EnhancePlatformFee = $comset_val->enhance->OneTime->EnhancePlatformFee;
                    /* ONETIME */

                    /* PREPAID */
                    $companysetting->enhance->Prepaid->EnhanceCostperlead = $comset_val->enhance->Prepaid->EnhanceCostperlead;
                    $companysetting->enhance->Prepaid->EnhanceMinCostMonth = $comset_val->enhance->Prepaid->EnhanceMinCostMonth;
                    $companysetting->enhance->Prepaid->EnhancePlatformFee = $comset_val->enhance->Prepaid->EnhancePlatformFee;
                    /* PREPAID */
                }

                if(!isset($companysetting->b2b)) { // B2B
                    $companysetting->b2b = new stdClass();
                    $companysetting->b2b->Weekly = new stdClass();
                    $companysetting->b2b->Monthly = new stdClass();
                    $companysetting->b2b->OneTime = new stdClass();
                    $companysetting->b2b->Prepaid = new stdClass();

                    /* WEEKLY */
                    $companysetting->b2b->Weekly->B2bCostperlead = $comset_val->b2b->Weekly->B2bCostperlead;
                    $companysetting->b2b->Weekly->B2bMinCostMonth = $comset_val->b2b->Weekly->B2bMinCostMonth;
                    $companysetting->b2b->Weekly->B2bPlatformFee = $comset_val->b2b->Weekly->B2bPlatformFee;
                    /* WEEKLY */
                    
                    /* MONTHLY */
                    $companysetting->b2b->Monthly->B2bCostperlead = $comset_val->b2b->Monthly->B2bCostperlead;
                    $companysetting->b2b->Monthly->B2bMinCostMonth = $comset_val->b2b->Monthly->B2bMinCostMonth;
                    $companysetting->b2b->Monthly->B2bPlatformFee = $comset_val->b2b->Monthly->B2bPlatformFee;
                    /* MONTHLY */
                    
                    /* ONETIME */
                    $companysetting->b2b->OneTime->B2bCostperlead = $comset_val->b2b->OneTime->B2bCostperlead;
                    $companysetting->b2b->OneTime->B2bMinCostMonth = $comset_val->b2b->OneTime->B2bMinCostMonth;
                    $companysetting->b2b->OneTime->B2bPlatformFee = $comset_val->b2b->OneTime->B2bPlatformFee;
                    /* ONETIME */

                    /* PREPAID */
                    $companysetting->b2b->Prepaid->B2bCostperlead = $comset_val->b2b->Prepaid->B2bCostperlead;
                    $companysetting->b2b->Prepaid->B2bMinCostMonth = $comset_val->b2b->Prepaid->B2bMinCostMonth;
                    $companysetting->b2b->Prepaid->B2bPlatformFee = $comset_val->b2b->Prepaid->B2bPlatformFee;
                    /* PREPAID */
                }

                if (!isset($companysetting->local->Prepaid)) {
                    $newPrepaidLocal = (object) [
                        "LeadspeekCostperlead" => $companysetting->local->Weekly->LeadspeekCostperlead,
                        "LeadspeekMinCostMonth" => $companysetting->local->Weekly->LeadspeekMinCostMonth,
                        "LeadspeekPlatformFee" => $companysetting->local->Weekly->LeadspeekPlatformFee
                    ];

                    $newPrepaidLocator = (object) [
                        "LocatorCostperlead" => $companysetting->locator->Weekly->LocatorCostperlead,
                        "LocatorMinCostMonth" => $companysetting->locator->Weekly->LocatorMinCostMonth,
                        "LocatorPlatformFee" => $companysetting->locator->Weekly->LocatorPlatformFee
                    ];

                    $newPrepaidEnhance = (object) [
                        "EnhanceCostperlead" => $comset_val->enhance->Prepaid->EnhanceCostperlead,
                        "EnhanceMinCostMonth" => $comset_val->enhance->Prepaid->EnhanceMinCostMonth,
                        "EnhancePlatformFee" => $comset_val->enhance->Prepaid->EnhancePlatformFee
                    ];

                    $newPrepaidB2b = (object) [
                        "B2bCostperlead" => $comset_val->b2b->Prepaid->B2bCostperlead,
                        "B2bMinCostMonth" => $comset_val->b2b->Prepaid->B2bMinCostMonth,
                        "B2bPlatformFee" => $comset_val->b2b->Prepaid->B2bPlatformFee
                    ];

                    $companysetting->local->Prepaid = $newPrepaidLocal;
                    $companysetting->locator->Prepaid = $newPrepaidLocator;
                    $companysetting->enhance->Prepaid = $newPrepaidEnhance;
                    $companysetting->b2b->Prepaid = $newPrepaidB2b;
                }

                // LOCAL MARKUP
                if(!isset($companysetting->local->Weekly->LeadspeekCostperleadAdvanced)) {
                    // info('LOCAL MARKUP Weekly 1');
                    $companysetting->local->Weekly->LeadspeekCostperleadAdvanced = $comset_val->local->Weekly->LeadspeekCostperleadAdvanced;
                }
                if(!isset($companysetting->local->Monthly->LeadspeekCostperleadAdvanced)) {
                    // info('LOCAL MARKUP Monthly 1');
                    $companysetting->local->Monthly->LeadspeekCostperleadAdvanced = $comset_val->local->Monthly->LeadspeekCostperleadAdvanced;
                } 
                if(!isset($companysetting->local->OneTime->LeadspeekCostperleadAdvanced)) {
                    // info('LOCAL MARKUP OneTime 1');
                    $companysetting->local->OneTime->LeadspeekCostperleadAdvanced = $comset_val->local->OneTime->LeadspeekCostperleadAdvanced;
                } 
                if(!isset($companysetting->local->Prepaid->LeadspeekCostperleadAdvanced)) {
                    // info('LOCAL MARKUP Prepaid 1');
                    $companysetting->local->Prepaid->LeadspeekCostperleadAdvanced = $comset_val->local->Prepaid->LeadspeekCostperleadAdvanced;
                } 
                // LOCAL MARKUP

                // PREDICT ID
                if(!isset($companysetting->predict)) {
                    $companysetting->predict = new stdClass();
                }
                if(!isset($companysetting->predict->Prepaid)) {
                    $companysetting->predict->Prepaid = new stdClass();
                }
                if(!isset($companysetting->predict->Prepaid->PredictMaxCampaigns)) {
                    $companysetting->predict->Prepaid->PredictMaxCampaigns = $comset_val->predict->Prepaid->PredictMaxCampaigns;
                }
                if(!isset($companysetting->predict->Prepaid->PredictMinCostMonth)) {
                    $companysetting->predict->Prepaid->PredictMinCostMonth = $comset_val->predict->Prepaid->PredictMinCostMonth;
                }
                // PREDICT ID

                // CLEAN ID
                if(!isset($companysetting->clean)) {
                    $companysetting->clean = new stdClass();
                    $companysetting->clean->CleanCostperlead = $comset_val->clean->CleanCostperlead;
                    $companysetting->clean->CleanCostperleadAdvanced = $comset_val->clean->CleanCostperleadAdvanced;
                }
                // CLEAN ID
                
                /* CHECK COSTPERLEAD IN COSTAGENCY UNDER COSTPERLEAD IN ROOTCOSTAGENCY , ONLY ENHANCE */
                // if(isset($companysetting->enhance->Weekly->EnhanceCostperlead) && isset($comset_val->enhance->Weekly->EnhanceCostperlead)) {
                //     $companysetting->enhance->Weekly->EnhanceCostperlead = ($companysetting->enhance->Weekly->EnhanceCostperlead > $comset_val->enhance->Weekly->EnhanceCostperlead) ? $companysetting->enhance->Weekly->EnhanceCostperlead : $comset_val->enhance->Weekly->EnhanceCostperlead;
                // }
                // if(isset($companysetting->enhance->Monthly->EnhanceCostperlead) && isset($comset_val->enhance->Monthly->EnhanceCostperlead)) {
                //     $companysetting->enhance->Monthly->EnhanceCostperlead = ($companysetting->enhance->Monthly->EnhanceCostperlead > $comset_val->enhance->Monthly->EnhanceCostperlead) ? $companysetting->enhance->Monthly->EnhanceCostperlead : $comset_val->enhance->Monthly->EnhanceCostperlead;
                // }
                // if(isset($companysetting->enhance->OneTime->EnhanceCostperlead) && isset($comset_val->enhance->OneTime->EnhanceCostperlead)) {
                //     $companysetting->enhance->OneTime->EnhanceCostperlead = ($companysetting->enhance->OneTime->EnhanceCostperlead > $comset_val->enhance->OneTime->EnhanceCostperlead) ? $companysetting->enhance->OneTime->EnhanceCostperlead : $comset_val->enhance->OneTime->EnhanceCostperlead;
                // }
                // if(isset($companysetting->enhance->Prepaid->EnhanceCostperlead) && isset($comset_val->enhance->Prepaid->EnhanceCostperlead)) {
                //     $companysetting->enhance->Prepaid->EnhanceCostperlead = ($companysetting->enhance->Prepaid->EnhanceCostperlead > $comset_val->enhance->Prepaid->EnhanceCostperlead) ? $companysetting->enhance->Prepaid->EnhanceCostperlead : $comset_val->enhance->Prepaid->EnhanceCostperlead;
                // }
                /* CHECK COSTPERLEAD IN COSTAGENCY UNDER COSTPERLEAD IN ROOTCOSTAGENCY , ONLY ENHANCE */
            }

            /** GET IF THERE IS CLIENT DEFAULT PRICE SET */
            if (isset($pk) && $pk != "") {
                $clientDefaultPrice = $this->getDefaultPrice($pk,'clientdefaultprice',$idSys);
            }
            /** GET IF THERE IS CLIENT DEFAULT PRICE SET */

        }else if ($settingname == 'agencyplan'){
            
            $getRootSetting = $this->getcompanysetting($companyID,'rootsetting');
            if ($getRootSetting != '') {
                if (isset($getRootSetting->defaultpaymentterm) && $getRootSetting->defaultpaymentterm != '') {
                    $defaultpaymentterm = trim($getRootSetting->defaultpaymentterm);
                }
            }
            
        }else if($settingname == 'rootcostagency') {
            $getdefpay = Company::find($companyID);
           
            if ($getdefpay->count() > 0) {
                $defaultpaymentterm = $getdefpay->paymentterm_default;
            }
        }
        /** GET DEFAULT PAYMENT TERM */

        if ($settingname == "rootstripe") {
            $companysetting = $companysetting->publishablekey;
            $defaultpaymentterm = "";
        }
        
        return response()->json(array('result'=>'success','data'=>$companysetting,'dpay'=>$defaultpaymentterm,'rootcostagency'=>$rootcostagency,'clientTypeLead'=>$clientTypeLead,'clientMinLeadDayEnhance'=>$clientMinLeadDayEnhance,'clientDefaultPrice'=>$clientDefaultPrice));

    }

    public function updatepackageplan(Request $request) {
        $companyID = (isset($request->CompanyID))?$request->CompanyID:'';
        $packageID = (isset($request->packageID))?$request->packageID:'';

        return $this->create_subscription($companyID,$packageID);
    }

    public function updateExcludeMinimumSpend(Request $request) {
        $companyID = (isset($request->CompanyID))?$request->CompanyID:'';
        $confAppSysID = config('services.application.systemid');
        
        $user = User::select('id', 'exclude_minimum_spend')
                    ->where('company_id', $companyID)
                    ->where('company_parent', $confAppSysID)
                    ->where('user_type', 'userdownline')
                    ->where('active', 'T')
                    ->first();

        if(empty($user))
            return response()->json(['result' => 'failed', 'message' => 'Sorry User Not Found', 'exclude_minimum_spend' => ''], 400);

        // toggle exclude_minimum_spend 
        $user->exclude_minimum_spend = ($user->exclude_minimum_spend == 1) ? 0 : 1;
        $user->save();

        $exclude_minimum_spend = ($user->exclude_minimum_spend == 1) ? true : false;

        /* USER LOG */
        $login_id = optional(auth()->user())->id;
        $ipAddress = ($request->header('Clientip') !== null)?$request->header('Clientip'):$request->ip();
        $description = ($exclude_minimum_spend) ? 'Update To Exclude Minimum Spend' : 'Update To Include Minimum Spend';
        $user_id = $user->id;
        $this->logUserAction($login_id,'Exclude Include Minimum Spend',$description,$ipAddress,$user_id);
        /* USER LOG */

        return response()->json(['result' => 'success', 'message' => '', 'exclude_minimum_spend' => $exclude_minimum_spend]);
    }

    public function get_under_perform_campaign(Request $request){
        $campaign_ids = !empty($request->campaign_ids) ? $request->campaign_ids : [];
        $selected_date = (isset($request->selected_date))? $request->selected_date : '';
        $weeks_ago = '';
        if (!empty($selected_date)) {
            $weeks_ago = Carbon::parse($selected_date)->subDays(7)->format('Y-m-d');
        }
        $campaigns = [];
        if (!empty($campaign_ids)) {
            $campaigns = LeadspeekUser::select(
                'companies.company_name',
                'leadspeek_users.campaign_name',
                'leadspeek_users.leadspeek_api_id',
                DB::raw('COUNT(leadspeek_reports.id) as total_leads'),
                'leadspeek_users.lp_limit_leads',
                'leadspeek_users.created_at'
            )
            ->leftJoin('users', 'leadspeek_users.user_id', '=', 'users.id')
            ->leftJoin('companies', 'users.company_id', '=', 'companies.id')
            ->leftJoin('leadspeek_reports', function($join) use($selected_date, $weeks_ago) {
                $join->on('leadspeek_users.leadspeek_api_id', '=', 'leadspeek_reports.leadspeek_api_id')
                    ->whereDate('leadspeek_reports.clickdate', '<=', $selected_date)
                    ->whereDate('leadspeek_reports.clickdate', '>=', $weeks_ago);
            })
            ->whereIn('leadspeek_users.id', $campaign_ids)
            ->groupBy(
                'companies.company_name',
                'leadspeek_users.campaign_name',
                'leadspeek_users.leadspeek_api_id',
                'leadspeek_users.lp_limit_leads',
                'leadspeek_users.created_at'
            )
            ->get();

            $campaigns->each(function ($campaign) {
                $campaign->created_on = Carbon::createFromFormat('Y-m-d H:i:s', $campaign->created_at)->format('m-d-Y');
            });
        }
        return response()->json([
            'result' => 'success',
            'campaigns' => $campaigns
        ],200);
    }
    
    public function getReportAgencies(Request $request){
        $company_root = isset($request->CompanyRoot) ? $request->CompanyRoot : '';
        $per_page = $PerPage ?? $request->input('PerPage', 10);
        $page = (isset($request->Page))?$request->Page:'';
        $search_key = (isset($request->searchKey))?$request->searchKey:'';
        $sort_by = (isset($request->SortBy))?$request->SortBy:'';
        $order = (isset($request->OrderBy))?$request->OrderBy: '';


        $CardStatus = json_decode($request->input('CardStatus', '{}'), true);
        $CampaignStatus = json_decode($request->input('CampaignStatus', '{}'), true);
        $OpenApiStatus = json_decode($request->input('OpenApiStatus', '{}'), true);
        $selected_date = (isset($request->SelectedDate))? $request->SelectedDate : '';
        $weeks_ago = '';
        if (!empty($selected_date)) {
            $weeks_ago = Carbon::parse($selected_date)->subDays(7)->format('Y-m-d');
        }

        $encryptionKey = substr(hash('sha256', env('APP_KEY')), 0, 16);
        $root_company_ids = User::select('company_id')
        ->whereNull('company_parent')
        ->where('user_type','userdownline')
        ->pluck('company_id');

        $quoted_selected_date = DB::getPdo()->quote($selected_date);

        $agencies = User::select(
                'users.id AS user_id',
                'users.company_id',
                'users.company_parent',
                DB::raw("CONVERT(AES_DECRYPT(FROM_BASE64(companies.company_name), '$encryptionKey') USING utf8mb4) AS agency_name"),
                DB::raw("CONVERT(AES_DECRYPT(FROM_BASE64(users.name), '$encryptionKey') USING utf8mb4) AS client_name"),
                DB::raw("CONVERT(AES_DECRYPT(FROM_BASE64(companies.email), '$encryptionKey') USING utf8mb4) AS agency_email"),
                DB::raw("CONVERT(AES_DECRYPT(FROM_BASE64(companies.phone), '$encryptionKey') USING utf8mb4) AS agency_phone"),
                //clients_created
                // DB::raw("(SELECT COUNT(*) FROM users AS u 
                //         WHERE u.company_parent = users.company_id 
                //         AND u.user_type = 'client' AND u.active = 'T') AS clients_created"),
                DB::raw("(SELECT COUNT(*) FROM users AS u 
                        WHERE u.company_parent = users.company_id 
                        AND DATE(u.created_at) <= $quoted_selected_date 
                        AND u.user_type = 'client'
                        AND u.active = 'T'
                         ) AS clients_created"),

                //CONNECTION
                    DB::raw("(SELECT customer_card_id FROM users AS u 
                    WHERE u.company_id = users.company_id 
                    AND u.user_type = 'userdownline' AND u.active = 'T') AS conn_cc_customer_card"),
                    DB::raw("(SELECT customer_payment_id FROM users AS u 
                    WHERE u.company_id = users.company_id 
                    AND u.user_type = 'userdownline' AND u.active = 'T') AS conn_cc_customer_payment"),
                    DB::raw("(SELECT payment_status FROM users AS u 
                    WHERE u.company_id = users.company_id 
                    AND u.user_type = 'userdownline' AND u.active = 'T') AS conn_cc_payment_status"),

                    //stripe 
                    'companies.paymentgateway AS conn_stripe_paymentgateway',
                    DB::raw("CONVERT(AES_DECRYPT(FROM_BASE64(cs.acc_ba_id), '$encryptionKey') USING utf8mb4) AS conn_stripe_ba_id"),
                    DB::raw("CONVERT(AES_DECRYPT(FROM_BASE64(cs.acc_connect_id), '$encryptionKey') USING utf8mb4) AS conn_stripe_connect_id"),
                    'cs.status_acc AS conn_stripe_status',

                    //google 
                    DB::raw("(SELECT setting_name FROM module_settings AS ms 
                            WHERE ms.company_id = users.company_id AND ms.setting_name = 'leadsToken' LIMIT 1) AS conn_google"),
                    //URL custom
                    'companies.manual_bill AS conn_manual_bill',
                    //URL custom
                    'companies.domain AS conn_domain',
                    'companies.status_domain AS conn_status_domain',
                    //logo custom
                    'companies.logo AS conn_logo',   
                    'companies.logo_login_register AS conn_logo_login_register',   
                    //SMTP
                    DB::raw("(SELECT CONVERT(AES_DECRYPT(FROM_BASE64(cs.setting_name), '$encryptionKey') USING utf8mb4) FROM company_settings AS cs 
                    WHERE cs.company_id = users.company_id AND CONVERT(AES_DECRYPT(FROM_BASE64(cs.setting_name), '$encryptionKey') USING utf8mb4) = 'customsmtpmenu' LIMIT 1) AS conn_smtp"),
                    //Agency Default Price
                    DB::raw("(SELECT CONVERT(AES_DECRYPT(FROM_BASE64(cs.setting_name), '$encryptionKey') USING utf8mb4) FROM company_settings AS cs 
                    WHERE cs.company_id = users.company_id AND CONVERT(AES_DECRYPT(FROM_BASE64(cs.setting_name), '$encryptionKey') USING utf8mb4) = 'agencydefaultprice' LIMIT 1) AS conn_agency_default_price"),
            
                //CONNECTION
                
                DB::raw("(SELECT COUNT(*) FROM users AS u 
                        WHERE u.company_parent = users.company_id 
                        AND u.user_type = 'client' AND u.active = 'T' 
                        AND (u.payment_status IS NULL OR u.payment_status = '')  
                        AND u.customer_card_id <> '' AND u.customer_payment_id <> '') AS clients_active_credit_card"),
                
                DB::raw("(SELECT COUNT(*) FROM leadspeek_users AS lu 
                        WHERE lu.company_id = users.company_id 
                        AND lu.archived = 'F'
                        AND (lu.active = 'T' AND lu.disabled = 'F' AND lu.active_user = 'T')) AS recently_start_campaign"),

                DB::raw("(SELECT COUNT(*) FROM leadspeek_users AS lu 
                        WHERE lu.company_id = users.company_id 
                        AND lu.archived = 'F'
                        AND lu.leadspeek_type = 'local' 
                        AND lu.active = 'T' AND lu.disabled = 'F' AND lu.active_user = 'T') AS local_active_campaign"),
                
                DB::raw("(SELECT COUNT(*) FROM leadspeek_users AS lu 
                        WHERE lu.company_id = users.company_id 
                        AND lu.archived = 'F'
                        AND lu.leadspeek_type = 'locator' 
                        AND lu.active = 'T' AND lu.disabled = 'F' AND lu.active_user = 'T') AS locator_active_campaign"),
                
                DB::raw("(SELECT COUNT(*) FROM leadspeek_users AS lu 
                        WHERE lu.company_id = users.company_id 
                        AND lu.archived = 'F'
                        AND lu.leadspeek_type = 'enhance' 
                        AND lu.active = 'T' AND lu.disabled = 'F' AND lu.active_user = 'T') AS enhance_active_campaign"),

                DB::raw("(SELECT COUNT(*) FROM leadspeek_users AS lu 
                        WHERE lu.company_id = users.company_id 
                        AND lu.archived = 'F'
                        AND (lu.active = 'F' AND lu.disabled = 'T' AND lu.active_user = 'F')) AS total_stopped_campaign"),

                //account executive
                DB::raw("(SELECT CONVERT(AES_DECRYPT(FROM_BASE64(name), '$encryptionKey') USING utf8mb4) 
                FROM users AS u 
                WHERE u.id = (SELECT sales_id 
                            FROM company_sales AS cs 
                            WHERE cs.company_id = users.company_id 
                            AND cs.sales_title = 'Account Executive')
                ) AS account_executive"),

                //account referral
                DB::raw("(SELECT CONVERT(AES_DECRYPT(FROM_BASE64(name), '$encryptionKey') USING utf8mb4) 
                FROM users AS u 
                WHERE u.id = (SELECT sales_id 
                            FROM company_sales AS cs 
                            WHERE cs.company_id = users.company_id 
                            AND cs.sales_title = 'Account Referral')
                ) AS account_referral"),

                //sales representative
                DB::raw("(SELECT CONVERT(AES_DECRYPT(FROM_BASE64(name), '$encryptionKey') USING utf8mb4) 
                FROM users AS u 
                WHERE u.id = (SELECT sales_id 
                            FROM company_sales AS cs 
                            WHERE cs.company_id = users.company_id 
                            AND cs.sales_title = 'Sales Representative')
                ) AS sales_representative"),

                // user generate open api
                DB::raw('CASE WHEN open_api_users.client_id IS NOT NULL AND open_api_users.secret_key IS NOT NULL THEN true ELSE false END as is_user_generate_api'),

                'users.trial_end_date',
                'users.api_mode',
                'users.is_marketing_services_agreement_developer',
                'companies.created_at',
            )
            ->leftJoin('companies', 'companies.id', '=', 'users.company_id')
            ->leftJoin('company_stripes AS cs', 'cs.company_id', '=', 'users.company_id')
            ->leftJoin('open_api_users', 'open_api_users.company_id', '=', 'users.company_id');
            
            if (!empty($company_root) && $company_root != 'all') {
                $agencies->where('users.company_parent', $company_root);
            }else {
                $agencies->whereIn('users.company_parent', $root_company_ids);
            }

            $agencies->where('users.user_type', 'userdownline')
            ->where('users.active', 'T');

            if (!empty($selected_date)) {
                $agencies->whereDate('companies.created_at',' <=', $selected_date);
            }

            if (trim($search_key) != '') {
                $agencies->where(function($query) use ($search_key,$encryptionKey) {
                    $query->orWhere(DB::raw("CONVERT(AES_DECRYPT(FROM_bASE64(companies.company_name), '" . $encryptionKey . "') USING utf8mb4)"),'like','%' . $search_key . '%')
                    ->orWhere(DB::raw("CONVERT(AES_DECRYPT(FROM_bASE64(users.email), '" . $encryptionKey . "') USING utf8mb4)"),'like','%' . $search_key . '%')
                    ->orWhere(DB::raw("CONVERT(AES_DECRYPT(FROM_bASE64(companies.email), '" . $encryptionKey . "') USING utf8mb4)"),'like','%' . $search_key . '%')
                    ->orWhere(DB::raw("CONVERT(AES_DECRYPT(FROM_bASE64(companies.phone), '" . $encryptionKey . "') USING utf8mb4)"),'like','%' . $search_key . '%')
                    ->orWhere(DB::raw("DATE_FORMAT(companies.created_at,'%m-%d-%Y')"),'like','%' . $search_key . '%');
                });
            }

            // Filter card status
            $cardStatus = (object) array_merge([
                'active' => false,
                'failed' => false,
                'inactive' => false,
            ], $CardStatus);

            $stripeNotConnected = $cardStatus->active;
            $manualBill = $cardStatus->failed;
            $inactiveCardStatus = $cardStatus->inactive;

            if($stripeNotConnected && $inactiveCardStatus &&  $manualBill){
                $agencies->where(function($query) {
                    $query->where('cs.acc_connect_id', '')//stripe not connected
                        ->orWhereNull('cs.acc_connect_id')//stripe not connected
                        ->orWhere('users.customer_payment_id', '')//credit card inactive
                        ->orWhere('users.customer_card_id', '')//credit card inactive
                        ->orWhere('companies.manual_bill', 'F');//manual bill
                });
            } else if($stripeNotConnected && $inactiveCardStatus){
                $agencies->where(function($query) {
                    $query
                        ->orWhere('users.customer_payment_id', '')//credit card inactive
                        ->orWhere('users.customer_card_id', '')//credit card inactive
                        ->orWhere('cs.acc_connect_id', '')//stripe not connected
                        ->orWhereNull('cs.acc_connect_id');//stripe not connected

                });
            } else if($stripeNotConnected &&  $manualBill){
                $agencies->where(function($query) {
                    $query->orWhere('cs.acc_connect_id', '')//stripe not connected
                         ->orWhereNull('cs.acc_connect_id')//stripe not connected
                        ->orWhere('companies.manual_bill', 'F');//manual bill
                });
            } else if ($inactiveCardStatus &&  $manualBill) {
                $agencies->where(function($query) {
                    $query->where('users.customer_payment_id', '')//credit card inactive
                            ->orWhere('users.customer_card_id', '')//credit card inactive
                            ->orWhere('companies.manual_bill', 'F'); //manual bill
                });
            } else if ($inactiveCardStatus) {
                $agencies->where(function($query) {
                    $query->where('users.customer_payment_id', '')
                        ->orWhere('users.customer_card_id', '');
                });//credit card inactive
            } elseif ($stripeNotConnected) {
                $agencies->where(function($query) {
                    $query->where('cs.acc_connect_id', '')//stripe not connected
                            ->orWhereNull('cs.acc_connect_id');//stripe not connected
                });
            } elseif ($manualBill) {
                $agencies->where(function($query) {
                    $query->where('companies.manual_bill', 'F');
                });//manual bill
            }
            // Filter card status

            // Filter campaign status
            $campaignStatus = (object) array_merge([
                'active' => false,
                'inactive' => false,
            ], $CampaignStatus);

            $activeCampaignStatus = $campaignStatus->active;
            $inactiveCampaignStatus = $campaignStatus->inactive;


            if($activeCampaignStatus && $inactiveCampaignStatus){
                $agencies->whereIn('users.company_id', function($query) {
                    $query->select('leadspeek_users.company_id')
                        ->from('leadspeek_users')
                        ->where('leadspeek_users.archived', '=', 'F')
                        ->groupBy('leadspeek_users.company_id')
                        ->havingRaw('SUM(CASE WHEN (leadspeek_users.active = "T" OR leadspeek_users.active = "F") AND leadspeek_users.disabled = "F" AND leadspeek_users.active_user = "T" THEN 1 ELSE 0 END) > 0')
                        ->orHavingRaw('SUM(CASE WHEN leadspeek_users.active = "F" AND leadspeek_users.disabled = "T" AND (leadspeek_users.active_user = "T" OR leadspeek_users.active_user = "F") THEN 1 ELSE 0 END) > 0');
                });
            } else if ($activeCampaignStatus){
                $agencies->whereIn('users.company_id', function($query) {
                    $query->select('leadspeek_users.company_id')
                        ->from('leadspeek_users')
                        ->where('leadspeek_users.archived', '=', 'F')
                        ->groupBy('leadspeek_users.company_id')
                        ->havingRaw('SUM(CASE WHEN (leadspeek_users.active = "T" OR leadspeek_users.active = "F") AND leadspeek_users.disabled = "F" AND leadspeek_users.active_user = "T" THEN 1 ELSE 0 END) > 0');
                });
            } else if ($inactiveCampaignStatus){
                $agencies->whereIn('users.company_id', function($query) {
                    $query->select('leadspeek_users.company_id')
                        ->from('leadspeek_users')
                        ->where('leadspeek_users.archived', '=', 'F')
                        ->groupBy('leadspeek_users.company_id')
                        ->havingRaw('SUM(CASE WHEN (leadspeek_users.active = "T" OR leadspeek_users.active = "F") AND leadspeek_users.disabled = "F" AND leadspeek_users.active_user = "T" THEN 1 ELSE 0 END) < 1')
                        ->havingRaw('SUM(CASE WHEN leadspeek_users.active = "F" AND leadspeek_users.disabled = "T" AND (leadspeek_users.active_user = "T" OR leadspeek_users.active_user = "F") THEN 1 ELSE 0 END) > 0');
                });
            }
            // Filter campaign status

            // Filter open api status
            $openApiStatus = (object) array_merge([
                'apiMode' => false,
                'isGenerateApi' => false,
            ], $OpenApiStatus);

            $activeApiMode = $openApiStatus->apiMode;
            $activeGenerateApi = $openApiStatus->isGenerateApi;

            if($activeGenerateApi){
                $agencies->where(function($query) {
                    $query->whereNotNull('open_api_users.client_id')
                         ->whereNotNull('open_api_users.secret_key');
                });
            }

            if($activeApiMode){
                $agencies->where(function($query) {
                    $query->where('users.api_mode','=','T');
                });
            }
            // Filter open api status

            if (trim($order) != '') {
                if (trim($order) == 'descending') {
                    $order = "DESC";
                }else{
                    $order = "ASC";
                }
            }

            if (trim($sort_by) != '') {
                if (trim($sort_by) == "agency_name") {
                    $agencies = $agencies->orderByEncrypted('companies.company_name',$order);
                }else if (trim($sort_by) == "client_name") {
                    $agencies = $agencies->orderByEncrypted('users.name',$order);
                }else if (trim($sort_by) == "created_on") {
                    $agencies = $agencies->orderBy(DB::raw('CAST(companies.created_at AS DATETIME)'),$order);
                }
                $agencies = $agencies->paginate($per_page, ['*'], 'page', $page);
            } else {
                $agencies = $agencies->orderByEncrypted('companies.company_name')->paginate($per_page, ['*'], 'page', $page);
            }

            // $agencies = $agencies->get();

        if (!empty($agencies)) {
            foreach ($agencies as $index => $agency) {
                //agencies connection
                    //credit card
                    $credit_card = '';
                    if (!empty($agency->conn_cc_customer_card) && !empty($agency->conn_cc_customer_payment) && ($agency->conn_cc_payment_status == '' || $agency->conn_cc_payment_status == null)) {
                        $credit_card = 'active';
                    }elseif (empty($agency->conn_cc_customer_card) || empty($agency->conn_cc_customer_payment)) {
                        $credit_card = 'inactive';
                    }elseif ($agency->conn_cc_payment_status == 'failed') {
                        $credit_card = 'failed';
                    }
                    $agencies[$index]['status_credit_card'] = $credit_card;

                    //stripe
                    // $stripe = '';
                    // $param = new Request([
                    //     'companyID' => !empty($agency->company_id) ? $agency->company_id : '',
                    //     'idsys' => !empty($agency->company_parent) ? $agency->company_parent : '',
                    // ]);
                    // $stripeResponse = $generalController->checkconnectedaccountstatus($param);
                    // $responseData = $stripeResponse->getData(true); 
                    // $stripe = isset($responseData['status']) ? $responseData['status'] : null;
                    $stripe_status = 'not_registered';
                    if (!empty($agency->conn_stripe_connect_id)) {
                        if (!empty($agency->conn_stripe_status) && $agency->conn_stripe_status == 'completed') {
                            $stripe_status = 'completed';
                        }elseif (!empty($agency->conn_stripe_status) && $agency->conn_stripe_status == 'pending') {
                            $stripe_status = 'pending';
                        }elseif (!empty($agency->conn_stripe_status) && $agency->conn_stripe_status == 'inverification') {
                            $stripe_status = 'inverification';
                        }
                    }else {
                        $stripe_status = 'not_registered';
                    }
                    $agencies[$index]['status_stripe'] = $stripe_status;

                    //google
                    $google = !empty($agency->conn_google) ? true :false;
                    $agencies[$index]['status_google'] = $google;

                    //manual_bill
                    $manual_bill = !empty($agency->conn_manual_bill) && $agency->conn_manual_bill == 'T' ? true :false;
                    $agencies[$index]['status_manual_bill'] = $manual_bill;

                    //url
                    $domain = !empty($agency->conn_domain) ? true :false;
                    $status_domain = $agency->conn_status_domain == 'ssl_acquired' ? true :false;
                    $agencies[$index]['status_domain'] = ($domain && $status_domain) ? true : false;

                    //logo
                    $logo = !empty($agency->conn_logo) || !empty($agency->conn_logo_login_register)  ? true :false;
                    $agencies[$index]['status_logo'] = $logo;

                    //SMTP
                    $smtp = $agency->conn_smtp == 'customsmtpmenu' ? true :false;
                    $agencies[$index]['status_smtp'] = $smtp;

                    //Default Price
                    $default_price = $agency->conn_agency_default_price == 'agencydefaultprice' ? true :false;
                    $agencies[$index]['status_default_price'] = $default_price;
                //agencies connection

                //underperform campaigns
                $underperform_campaigns = LeadspeekUser::select('leadspeek_users.id','leadspeek_users.lp_limit_leads')
                ->leftJoin('leadspeek_reports', function($join) use($selected_date, $weeks_ago) {
                    $join->on('leadspeek_users.leadspeek_api_id', '=', 'leadspeek_reports.leadspeek_api_id')
                        ->whereDate('leadspeek_reports.clickdate', '<=', $selected_date)
                        ->whereDate('leadspeek_reports.clickdate', '>=', $weeks_ago);
                })
                ->where('leadspeek_users.company_id', $agency->company_id)
                ->where('leadspeek_users.active', 'T')
                ->where('leadspeek_users.disabled', 'F')
                ->where('leadspeek_users.active_user', 'T')
                ->groupBy('leadspeek_users.id')
                ->havingRaw('COUNT(leadspeek_reports.id) < (0.6 * (leadspeek_users.lp_limit_leads * 7))')
                ->get();

                $details = [];
                if (!empty($underperform_campaigns)) {
                    foreach ($underperform_campaigns as $campaign) {
                        if (isset($campaign->id)) {
                            $details [] = $campaign->id;
                        }
                    }
                }
                $agencies[$index]['under_perform_campaigns_total'] = count($underperform_campaigns);;
                $agencies[$index]['under_perform_campaigns_details'] = $details;

                // //weekly_revenue
                $totalRevenue = DB::table(function ($query) use ($agency, $selected_date, $weeks_ago) {
                    $query->select(DB::raw('SUM(platform_price_lead) AS total_profit'))
                        ->from('leadspeek_reports')
                        ->leftJoin('users', 'leadspeek_reports.user_id', '=', 'users.id')
                        ->where('users.company_parent', $agency->company_id)
                        ->where(function($query) {
                            $query->whereNull('leadspeek_reports.topup_id')
                                  ->orWhere('leadspeek_reports.topup_id', 0)
                                  ->orWhere('leadspeek_reports.topup_id', '');
                        })
                        ->whereDate('leadspeek_reports.clickdate', '<=', $selected_date)
                        ->whereDate('leadspeek_reports.clickdate', '>=', $weeks_ago)
                        // ->whereBetween('leadspeek_reports.clickdate',[$selected_date,$weeks_ago])
                        ->unionAll(
                            DB::table('topup_campaigns')
                                ->select(DB::raw('SUM((platform_price) * total_leads) AS total_profit'))
                                ->where('company_id', $agency->company_id)
                                ->whereDate('created_at', '<=', $selected_date)
                                ->whereDate('created_at', '>=', $weeks_ago)
                                // ->whereBetween('created_at',[$selected_date,$weeks_ago])

                        );
                })->select(DB::raw('SUM(total_profit) AS total_revenue'))->first();
                $agencies[$index]['weekly_revenue'] = !empty($totalRevenue->total_revenue) ? number_format($totalRevenue->total_revenue,2,'.','') : 0;

                $agencies[$index]['minspend_start_date'] = Carbon::parse($agency->trial_end_date)->format('m-d-Y');
                $agencies[$index]['created_on'] = Carbon::parse($agency->created_at)->format('m-d-Y');
                $agencies[$index]['is_user_generate_api'] = (bool) $agency->is_user_generate_api;
            }
        }

        $agencies->makeHidden([
            'conn_cc_customer_card',
            'conn_cc_customer_payment',
            'conn_cc_payment_status',
            'conn_domain',
            'conn_google',
            'conn_logo',
            'conn_logo_login_register',
            'conn_manual_bill',
            'conn_smtp',
            'conn_agency_default_price',
            'conn_status_domain',
            'conn_stripe_ba_id',
            'conn_stripe_connect_id',
            'conn_stripe_paymentgateway',
            'conn_stripe_status',
            'created_at',
        ]);

        return response()->json([
            'result' => 'success',
            'agencies' => $agencies,
        ], 200);
    }

    public function getRootRevenue(Request $request)
    {
        /* GET ROOT REVENEU */
        $startDate = (isset($request->startDate))?date('Ymd',strtotime($request->startDate)):"";
        $endDate = (isset($request->endDate))?date('Ymd',strtotime($request->endDate)):"";
        $confAppSysID = config('services.application.systemid');
        
        // dapatkan list root nya
        $rootRevenueList = [];
        $rootList = User::select('users.company_id as company_id','companies.company_name as company_name')
                        ->join('companies','users.company_id','=','companies.id')
                        ->where('users.active','=','T')
                        ->whereNull('company_parent')
                        ->get();

        foreach($rootList as $item)
        {
            $company_name = isset($item->company_name) ? $item->company_name : '';
            $company_root_id = isset($item->company_id) ? $item->company_id : '';

            $getExcludeAgency = CompanySetting::where('company_id',$company_root_id)->whereEncrypted('setting_name','rootAnalyticsExcludeAgency')->get();
            $excludeAgency = [];
            if (count($getExcludeAgency) > 0) 
            {
                $getExcludeAgencyResult = json_decode($getExcludeAgency[0]['setting_value']);
                $excludeAgency = explode(",",$getExcludeAgencyResult->companyAgencyId);
            }

            // hitung revenue setiap root untuk weekly atau monthly
            $totalRevenueRoot = LeadspeekReport::select(
                                                    DB::raw('SUM(platform_price_lead) as total_platform_price_lead'),
                                                    DB::raw('SUM(root_price_lead) as total_root_price_lead'),
                                               )
                                               ->join('leadspeek_users',DB::raw('TRIM(leadspeek_reports.leadspeek_api_id)'),'=',DB::raw('TRIM(leadspeek_users.leadspeek_api_id)'))
                                               ->join('users','users.id','=','leadspeek_reports.user_id')
                                               ->whereIn('leadspeek_reports.leadspeek_api_id', function($query) use($startDate, $endDate) {
                                                    $query->select('report_analytics.leadspeek_api_id')
                                                          ->from('report_analytics')
                                                          ->where(DB::raw('DATE_FORMAT(report_analytics.date,"%Y%m%d")'),'>=',$startDate)
                                                          ->where(DB::raw('DATE_FORMAT(report_analytics.date,"%Y%m%d")'),'<=',$endDate);
                                                })
                                               ->where(DB::raw('DATE_FORMAT(leadspeek_reports.clickdate,"%Y%m%d")'),'>=',$startDate)
                                               ->where(DB::raw('DATE_FORMAT(leadspeek_reports.clickdate,"%Y%m%d")'),'<=',$endDate)
                                               ->where('users.company_root_id','=',$company_root_id);

            if(count($excludeAgency) > 0) 
            {
                $totalRevenueRoot = $totalRevenueRoot->whereNotIn('leadspeek_users.company_id',$excludeAgency);
            }

            $totalRevenueRoot = $totalRevenueRoot->first();
            $totalRevenueRoot = ($company_root_id == $confAppSysID) ? 
                                (isset($totalRevenueRoot->total_platform_price_lead) ? number_format($totalRevenueRoot->total_platform_price_lead,2,'.','') : 0) :
                                (isset($totalRevenueRoot->total_root_price_lead) ? number_format($totalRevenueRoot->total_root_price_lead,2,'.','') : 0);

            $rootRevenueList[] = [
                'company_name' => $company_name,
                'total_revenue_root' => $totalRevenueRoot,
            ];
        }

        return response()->json(array('result'=>'success','rootRevenueList'=>$rootRevenueList));
        /* GET ROOT REVENEU */
    }

    public function getReportAnalytic(Request $request) {
        date_default_timezone_set('America/Chicago');

        $startDate = (isset($request->startDate))?date('Ymd',strtotime($request->startDate)):"";
        $endDate = (isset($request->endDate))?date('Ymd',strtotime($request->endDate)):"";

        $companyid = (isset($request->companyid))?trim($request->companyid):"";
        $campaignid = (isset($request->campaignid))?trim($request->campaignid):"";

        $companyrootid = (isset($request->companyrootid))?trim($request->companyrootid):"";

        $profitLocal = "";
        $profitLocator = "";
        $profitEnhance = "";
        $profitB2B = "";
        $profitSimplifi = "";
        $confAppSysID = config('services.application.systemid');

        $getExcludeAgency = CompanySetting::where('company_id',$companyrootid)->whereEncrypted('setting_name','rootAnalyticsExcludeAgency')->get();
        $ExcludeAgency = array();
        if (count($getExcludeAgency) > 0) {
            $getExcludeAgencyResult = json_decode($getExcludeAgency[0]['setting_value']);
            $ExcludeAgency = explode(",",$getExcludeAgencyResult->companyAgencyId);
        }

        /** IF VIEW ALL STILL EXCLUDE IF ANY COMANY SETTING TO EXCLUDE AGENCY */
        if ($companyrootid == "" && $companyid == "" && $campaignid == '' && count($getExcludeAgency) == 0) {
            $getExcludeAgency = CompanySetting::whereEncrypted('setting_name','rootAnalyticsExcludeAgency')->get();
            $ExcludeAgency = array();

            foreach($getExcludeAgency as $ea) {
                $getExcludeAgencyResult = json_decode($ea['setting_value']);
                $agencyIds = explode(",", $getExcludeAgencyResult->companyAgencyId);
                $ExcludeAgency = array_merge($ExcludeAgency, $agencyIds);  // Merge each result into the main array
            }

            // Optionally, if you want unique values, you can use array_unique
            $ExcludeAgency = array_unique($ExcludeAgency);
        }
        /** IF VIEW ALL STILL EXCLUDE IF ANY COMANY SETTING TO EXCLUDE AGENCY */
        
        //if ($confAppSysID != $companyrootid && ($companyrootid != "" && $companyid == "0" && $campaignid == '0')) {
        if (false) {
            $rootAccConResult = $this->getcompanysetting($companyrootid,'rootfee');
                    if ($rootAccConResult != '') {
                        $profitLocal = (isset($rootAccConResult->feesiteid))?$rootAccConResult->feesiteid:"";
                        $profitLocator = (isset($rootAccConResult->feesearchid))?$rootAccConResult->feesearchid:"";
                        $profitEnhance = (isset($rootAccConResult->feeenhanceid))?$rootAccConResult->feeenhanceid:"";
                    }
        }

        $rpanalytics = ReportAnalytic::select('report_analytics.leadspeek_type',DB::raw('SUM(pixelfire) as pixelfire'),DB::raw('SUM(towerpostal) as towerpostal'),DB::raw('SUM(bigbdmemail) as bigbdmemail'),DB::raw('SUM(bigbdmpii) as bigbdmpii'),DB::raw('SUM(endatoenrichment) as endatoenrichment'),
                                        DB::raw('SUM(toweremail) as toweremail'),DB::raw('SUM(zerobouncefailed) as zerobouncefailed'),'report_analytics.zerobounce_details',DB::raw('SUM(locationlockfailed) as locationlockfailed'),DB::raw('SUM(serveclient) as serveclient'),
                                        DB::raw('SUM(notserve) as notserve'),DB::raw('"0" as platformfee'),DB::raw('COUNT(report_analytics.leadspeek_api_id) as activecampaign'),
                                        DB::raw('SUM(bigbdmhems) as bigbdmhems'),DB::raw('SUM(bigbdmtotalleads) as bigbdmtotalleads'),DB::raw('SUM(bigbdmremainingleads) as bigbdmremainingleads'),DB::raw('SUM(simplifi_impressions) as simplifi_impressions'),
                                        DB::raw('SUM(getleadfailed) as getleadfailed'),DB::raw('SUM(getleadfailed_bigbdmmd5) as getleadfailed_bigbdmmd5'),DB::raw('SUM(getleadfailed_gettowerdata) as getleadfailed_gettowerdata'),DB::raw('SUM(getleadfailed_bigbdmpii) as getleadfailed_bigbdmpii')
                                    )->whereIn('report_analytics.leadspeek_type', ['local','locator','enhance','b2b']);
            if($startDate != "" && $endDate != "") {
                $rpanalytics->where(function($query) use ($startDate,$endDate) {
                    $query->where(DB::raw('DATE_FORMAT(date,"%Y%m%d")'),'>=',$startDate)
                                ->where(DB::raw('DATE_FORMAT(date,"%Y%m%d")'),'<=',$endDate);
                });
            }else{
                $rpanalytics->where(DB::raw('DATE_FORMAT(date,"%Y%m%d")'),'=',date('Ymd'));
            }
            
            if ($companyid != "" && $companyid != "0") {
                $rpanalytics->join('leadspeek_users','report_analytics.leadspeek_api_id','=','leadspeek_users.leadspeek_api_id')
                            ->where('leadspeek_users.company_id','=',$companyid);
            }

            if ($companyrootid != "" && $companyid == "0" && $campaignid == '0') {
                $rpanalytics->join('leadspeek_users',DB::raw('TRIM(report_analytics.leadspeek_api_id)'),'=',DB::raw('TRIM(leadspeek_users.leadspeek_api_id)'))
                            ->join('users','leadspeek_users.user_id','=','users.id')
                            ->where('users.company_root_id','=',$companyrootid);
                /** FOR EXCLUDE AGENCY FROM REPORT ANALYTICS */
                if (count($ExcludeAgency) > 0) {
                    $rpanalytics = $rpanalytics->whereNotIn('leadspeek_users.company_id',$ExcludeAgency);
                }
                /** FOR EXCLUDE AGENCY FROM REPORT ANALYTICS */
            }else if ($companyrootid == "" && $companyid == "" && $campaignid == '' && count($ExcludeAgency) > 0) {
                $rpanalytics->join('leadspeek_users',DB::raw('TRIM(report_analytics.leadspeek_api_id)'),'=',DB::raw('TRIM(leadspeek_users.leadspeek_api_id)'))
                            ->join('users','leadspeek_users.user_id','=','users.id')
                            ->whereNotIn('leadspeek_users.company_id',$ExcludeAgency);
            }

            if ($campaignid != '' && $campaignid != '0') {
                $rpanalytics->where('report_analytics.leadspeek_api_id','=',$campaignid);
            }

            $rpanalytics = $rpanalytics->groupBy('leadspeek_type')
                                        ->get();

            // ===================================================================
            // NEW QUERY FOR ZEROBOUNCE DETAILS
            // ===================================================================
            $zbQuery = ReportAnalytic::select(
                'report_analytics.leadspeek_type',
                DB::raw("SUM(COALESCE(JSON_UNQUOTE(JSON_EXTRACT(zerobounce_details, '$.valid')), 0)) as valid"),
                DB::raw("SUM(COALESCE(JSON_UNQUOTE(JSON_EXTRACT(zerobounce_details, '$.invalid')), 0)) as invalid"),
                DB::raw("SUM(COALESCE(JSON_UNQUOTE(JSON_EXTRACT(zerobounce_details, '$.catch_all')), 0)) as catch_all"),
                DB::raw("SUM(COALESCE(JSON_UNQUOTE(JSON_EXTRACT(zerobounce_details, '$.unknown')), 0)) as unknown"),
                DB::raw("SUM(COALESCE(JSON_UNQUOTE(JSON_EXTRACT(zerobounce_details, '$.spamtrap')), 0)) as spamtrap"),
                DB::raw("SUM(COALESCE(JSON_UNQUOTE(JSON_EXTRACT(zerobounce_details, '$.abuse')), 0)) as abuse"),
                DB::raw("SUM(COALESCE(JSON_UNQUOTE(JSON_EXTRACT(zerobounce_details, '$.do_not_mail')), 0)) as do_not_mail"),
                DB::raw("SUM(COALESCE(JSON_UNQUOTE(JSON_EXTRACT(zerobounce_details, '$.total')), 0)) as total")
            )->whereIn('report_analytics.leadspeek_type', ['local','locator','enhance','b2b']);

            if ($startDate != "" && $endDate != "") {
                $zbQuery->where(function($query) use ($startDate, $endDate) {
                    $query->where(DB::raw('DATE_FORMAT(date,"%Y%m%d")'), '>=', $startDate)
                        ->where(DB::raw('DATE_FORMAT(date,"%Y%m%d")'), '<=', $endDate);
                });
            } else {
                $zbQuery->where(DB::raw('DATE_FORMAT(date,"%Y%m%d")'), '=', date('Ymd'));
            }

            if ($companyid != "" && $companyid != "0") {
                $zbQuery->join('leadspeek_users', 'report_analytics.leadspeek_api_id', '=', 'leadspeek_users.leadspeek_api_id')
                        ->where('leadspeek_users.company_id', '=', $companyid);
            }

            if ($companyrootid != "" && $companyid == "0" && $campaignid == '0') {
                $zbQuery->join('leadspeek_users', DB::raw('TRIM(report_analytics.leadspeek_api_id)'), '=', DB::raw('TRIM(leadspeek_users.leadspeek_api_id)'))
                        ->join('users', 'leadspeek_users.user_id', '=', 'users.id')
                        ->where('users.company_root_id', '=', $companyrootid);
                /** FOR EXCLUDE AGENCY FROM REPORT ANALYTICS */
                if (count($ExcludeAgency) > 0) {
                    $zbQuery->whereNotIn('leadspeek_users.company_id', $ExcludeAgency);
                }
                /** FOR EXCLUDE AGENCY FROM REPORT ANALYTICS */
            } else if ($companyrootid == "" && $companyid == "" && $campaignid == '' && count($ExcludeAgency) > 0) {
                $zbQuery->join('leadspeek_users', DB::raw('TRIM(report_analytics.leadspeek_api_id)'), '=', DB::raw('TRIM(leadspeek_users.leadspeek_api_id)'))
                        ->join('users', 'leadspeek_users.user_id', '=', 'users.id')
                        ->whereNotIn('leadspeek_users.company_id', $ExcludeAgency);
            }

            if ($campaignid != '' && $campaignid != '0') {
                $zbQuery->where('report_analytics.leadspeek_api_id', '=', $campaignid);
            }

            $zerobounceSummary = $zbQuery->groupBy('leadspeek_type')
                                        ->get()
                                        ->keyBy('leadspeek_type')
                                        ->map(function ($item) {
                                            // remove leadspeek_type
                                            unset($item->leadspeek_type);
                                            return $item;
                                        });
            // ===================================================================
            // NEW QUERY FOR ZEROBOUNCE DETAILS
            // ===================================================================

            // ===================================================================
            // NEW CODE FOR TRUE LIST
            // ===================================================================
            $tlQuery = ReportAnalytic::select('report_analytics.leadspeek_type', 'truelist_details');

            if ($startDate != "" && $endDate != "") {
                $tlQuery->where(function($query) use ($startDate, $endDate) {
                    $query->where(DB::raw('DATE_FORMAT(date,"%Y%m%d")'), '>=', $startDate)
                        ->where(DB::raw('DATE_FORMAT(date,"%Y%m%d")'), '<=', $endDate);
                });
            } else {
                $tlQuery->where(DB::raw('DATE_FORMAT(date,"%Y%m%d")'), '=', date('Ymd'));
            }

            if ($companyid != "" && $companyid != "0") {
                $tlQuery->join('leadspeek_users', 'report_analytics.leadspeek_api_id', '=', 'leadspeek_users.leadspeek_api_id')
                        ->where('leadspeek_users.company_id', '=', $companyid);
            }

            if ($companyrootid != "" && $companyid == "0" && $campaignid == '0') {
                $tlQuery->join('leadspeek_users', DB::raw('TRIM(report_analytics.leadspeek_api_id)'), '=', DB::raw('TRIM(leadspeek_users.leadspeek_api_id)'))
                        ->join('users', 'leadspeek_users.user_id', '=', 'users.id')
                        ->where('users.company_root_id', '=', $companyrootid);
                if (count($ExcludeAgency) > 0) {
                    $tlQuery->whereNotIn('leadspeek_users.company_id', $ExcludeAgency);
                }
            } else if ($companyrootid == "" && $companyid == "" && $campaignid == '' && count($ExcludeAgency) > 0) {
                $tlQuery->join('leadspeek_users', DB::raw('TRIM(report_analytics.leadspeek_api_id)'), '=', DB::raw('TRIM(leadspeek_users.leadspeek_api_id)'))
                        ->join('users', 'leadspeek_users.user_id', '=', 'users.id')
                        ->whereNotIn('leadspeek_users.company_id', $ExcludeAgency);
            }

            if ($campaignid != '' && $campaignid != '0') {
                $tlQuery->where('report_analytics.leadspeek_api_id', '=', $campaignid);
            }

            $truelistResults = $tlQuery->whereNotNull('truelist_details')->get();
            
            $truelistSummary = [];
            foreach ($truelistResults as $result) {
                $type = $result->leadspeek_type;
                $details = json_decode($result->truelist_details, true);

                if (!is_array($details)) {
                    continue;
                }
                if (!isset($truelistSummary[$type])) {
                    $truelistSummary[$type] = [];
                }
                foreach ($details as $key => $value) {
                    if (is_numeric($value)) {
                        $truelistSummary[$type][$key] = ($truelistSummary[$type][$key] ?? 0) + $value;
                    }
                }
            }
            // ===================================================================
            // NEW CODE FOR TRUE LIST
            // ===================================================================

            foreach($rpanalytics as $a => $ra) {
                if ($ra['leadspeek_type'] == 'local') {
                    if ($profitLocal == "") { /** IF SUPER ROOT */
                        $platformlocal = LeadspeekReport::select(DB::raw('SUM(leadspeek_reports.platform_price_lead) as platformfee'),DB::raw('SUM(leadspeek_reports.root_price_lead) as rootplatformfee'))
                                                        ->whereIn('leadspeek_reports.leadspeek_api_id', function($query) use($startDate,$endDate) {
                                                            $query->select('report_analytics.leadspeek_api_id')
                                                                    ->from('report_analytics')
                                                                    ->where('report_analytics.leadspeek_type','=','local')
                                                                    ->where(DB::raw('DATE_FORMAT(report_analytics.date,"%Y%m%d")'),'>=',$startDate)
                                                                    ->where(DB::raw('DATE_FORMAT(report_analytics.date,"%Y%m%d")'),'<=',$endDate);
                                                        });

                                                        if ($companyid != "" && $companyid != "0") {
                                                            $platformlocal->join('leadspeek_users','leadspeek_reports.leadspeek_api_id','=','leadspeek_users.leadspeek_api_id')
                                                                        ->where('leadspeek_users.company_id','=',$companyid);
                                                        }
                                            
                                                        if ($campaignid != '' && $campaignid != '0') {
                                                            $platformlocal->where('leadspeek_reports.leadspeek_api_id','=',$campaignid);
                                                        }

                                                        if ($companyrootid != "" && $companyid == "0" && $campaignid == '0') {
                                                            $platformlocal->join('leadspeek_users',DB::raw('TRIM(leadspeek_reports.leadspeek_api_id)'),'=',DB::raw('TRIM(leadspeek_users.leadspeek_api_id)'))
                                                                            ->join('users','leadspeek_users.user_id','=','users.id')
                                                                            ->where('users.company_root_id','=',$companyrootid);
                                                            /** FOR EXCLUDE AGENCY FROM REPORT ANALYTICS */
                                                            if (count($ExcludeAgency) > 0) {
                                                                $platformlocal = $platformlocal->whereNotIn('leadspeek_users.company_id',$ExcludeAgency);
                                                            }
                                                            /** FOR EXCLUDE AGENCY FROM REPORT ANALYTICS */
                                                        }else if ($companyrootid == "" && $companyid == "" && $campaignid == '' && count($ExcludeAgency) > 0) {
                                                                $platformlocal->join('leadspeek_users',DB::raw('TRIM(leadspeek_reports.leadspeek_api_id)'),'=',DB::raw('TRIM(leadspeek_users.leadspeek_api_id)'))
                                                                              ->join('users','leadspeek_users.user_id','=','users.id')
                                                                              ->whereNotIn('leadspeek_users.company_id',$ExcludeAgency);
                                                        }

                                                        $platformlocal = $platformlocal->where(DB::raw('DATE_FORMAT(leadspeek_reports.clickdate,"%Y%m%d")'),'>=',$startDate)
                                                                            ->where(DB::raw('DATE_FORMAT(leadspeek_reports.clickdate,"%Y%m%d")'),'<=',$endDate)
                                                                            ->get();
                        if (count($platformlocal) > 0) {
                            if ($confAppSysID != $companyrootid && ($companyrootid != "" && $companyid == "0" && $campaignid == '0')) {
                                $rpanalytics[$a]['platformfee'] =  number_format($platformlocal[0]['rootplatformfee'],2,'.','');
                            }else if ($confAppSysID == $companyrootid && ($companyrootid != "" && $companyid == "0" && $campaignid == '0')) {
                                $rpanalytics[$a]['platformfee'] =  number_format($platformlocal[0]['platformfee'],2,'.','');
                            }else if ($companyrootid == "" && ($companyid != "" && $companyid != "0") && ($campaignid != "" && $campaignid != "0")) {
                                /** GET ROOT COMPANY */
                                $_companyrootid = ""; 
                                $getCompanyRoot = User::select('company_root_id')->where('user_type','=','userdownline')->where('company_id','=',$companyid)->get();
                                if (count($getCompanyRoot) > 0) {
                                    $_companyrootid = $getCompanyRoot[0]['company_root_id'];
                                }
                                if ($confAppSysID == $_companyrootid) {
                                    $rpanalytics[$a]['platformfee'] =  number_format($platformlocal[0]['platformfee'],2,'.','');
                                }else{
                                    $rpanalytics[$a]['platformfee'] =  number_format($platformlocal[0]['rootplatformfee'],2,'.','');
                                }
                                /** GET ROOT COMPANY */
                            }else{
                                
                                $adjustPlatformFee = LeadspeekReport::select(DB::raw('SUM(leadspeek_reports.platform_price_lead) as platformfee'))
                                                    ->whereIn('leadspeek_reports.leadspeek_api_id', function($query) use($startDate,$endDate) {
                                                        $query->select('report_analytics.leadspeek_api_id')
                                                                ->from('report_analytics')
                                                                ->where('report_analytics.leadspeek_type','=','local')
                                                                ->where(DB::raw('DATE_FORMAT(report_analytics.date,"%Y%m%d")'),'>=',$startDate)
                                                                ->where(DB::raw('DATE_FORMAT(report_analytics.date,"%Y%m%d")'),'<=',$endDate);
                                                    });
                                $adjustPlatformFee->join('leadspeek_users',DB::raw('TRIM(leadspeek_reports.leadspeek_api_id)'),'=',DB::raw('TRIM(leadspeek_users.leadspeek_api_id)'))
                                                                            ->join('users','leadspeek_users.user_id','=','users.id')
                                                                            ->where('users.company_root_id','<>',$confAppSysID);
                                $adjustPlatformFee = $adjustPlatformFee->where(DB::raw('DATE_FORMAT(leadspeek_reports.clickdate,"%Y%m%d")'),'>=',$startDate)
                                                                            ->where(DB::raw('DATE_FORMAT(leadspeek_reports.clickdate,"%Y%m%d")'),'<=',$endDate)
                                                                            ->get();

                                $_totalFee = ($platformlocal[0]['platformfee'] - $adjustPlatformFee[0]['platformfee']) + $platformlocal[0]['rootplatformfee'];
                                $rpanalytics[$a]['platformfee'] = number_format($_totalFee,2,'.','');
                            }
                            
                        }

                    }else{ /** IF NOT SUPER ROOT */
                        $_rootplatformfee = $ra['serveclient'] * $profitLocal;
                        $rpanalytics[$a]['platformfee'] =  number_format($_rootplatformfee,2,'.','');
                    }
                                                   
                }

                if ($ra['leadspeek_type'] == 'locator') {
                    if ($profitLocator == "") { /** IF SUPER ROOT */
                        $platformlocator = LeadspeekReport::select(DB::raw('SUM(leadspeek_reports.platform_price_lead) as platformfee'),DB::raw('SUM(leadspeek_reports.root_price_lead) as rootplatformfee'))
                                                        ->whereIn('leadspeek_reports.leadspeek_api_id', function($query) use($startDate,$endDate) {
                                                            $query->select('report_analytics.leadspeek_api_id')
                                                                    ->from('report_analytics')
                                                                    ->where('report_analytics.leadspeek_type','=','locator')
                                                                    ->where(DB::raw('DATE_FORMAT(report_analytics.date,"%Y%m%d")'),'>=',$startDate)
                                                                    ->where(DB::raw('DATE_FORMAT(report_analytics.date,"%Y%m%d")'),'<=',$endDate);
                                                        });
                                                        if ($companyid != "" && $companyid != "0") {
                                                            $platformlocator->join('leadspeek_users','leadspeek_reports.leadspeek_api_id','=','leadspeek_users.leadspeek_api_id')
                                                                        ->where('leadspeek_users.company_id','=',$companyid);
                                                        }
                                            
                                                        if ($campaignid != '' && $campaignid != '0') {
                                                            $platformlocator->where('leadspeek_reports.leadspeek_api_id','=',$campaignid);
                                                        }

                                                        if ($companyrootid != "" && $companyid == "0" && $campaignid == '0') {
                                                            $platformlocator->join('leadspeek_users',DB::raw('TRIM(leadspeek_reports.leadspeek_api_id)'),'=',DB::raw('TRIM(leadspeek_users.leadspeek_api_id)'))
                                                                            ->join('users','leadspeek_users.user_id','=','users.id')
                                                                            ->where('users.company_root_id','=',$companyrootid);
                                                                            /** FOR EXCLUDE AGENCY FROM REPORT ANALYTICS */
                                                                            if (count($ExcludeAgency) > 0) {
                                                                                $platformlocator = $platformlocator->whereNotIn('leadspeek_users.company_id',$ExcludeAgency);
                                                                            }
                                                                            /** FOR EXCLUDE AGENCY FROM REPORT ANALYTICS */
                                                        }else if ($companyrootid == "" && $companyid == "" && $campaignid == '' && count($ExcludeAgency) > 0) {
                                                            $platformlocator->join('leadspeek_users',DB::raw('TRIM(leadspeek_reports.leadspeek_api_id)'),'=',DB::raw('TRIM(leadspeek_users.leadspeek_api_id)'))
                                                                            ->join('users','leadspeek_users.user_id','=','users.id')
                                                                            ->whereNotIn('leadspeek_users.company_id',$ExcludeAgency);
                                                        }

                                                        $platformlocator = $platformlocator->where(DB::raw('DATE_FORMAT(leadspeek_reports.clickdate,"%Y%m%d")'),'>=',$startDate)
                                                                            ->where(DB::raw('DATE_FORMAT(leadspeek_reports.clickdate,"%Y%m%d")'),'<=',$endDate)
                                                                            ->get();
                        if (count($platformlocator) > 0) {
                            if ($confAppSysID != $companyrootid && ($companyrootid != "" && $companyid == "0" && $campaignid == '0')) {
                                $rpanalytics[$a]['platformfee'] =  number_format($platformlocator[0]['rootplatformfee'],2,'.','');
                            }else if ($confAppSysID == $companyrootid && ($companyrootid != "" && $companyid == "0" && $campaignid == '0')) {
                                $rpanalytics[$a]['platformfee'] =  number_format($platformlocator[0]['platformfee'],2,'.','');
                            }else if ($companyrootid == "" && ($companyid != "" && $companyid != "0") && ($campaignid != "" && $campaignid != "0")) {
                                /** GET ROOT COMPANY */
                                $_companyrootid = ""; 
                                $getCompanyRoot = User::select('company_root_id')->where('user_type','=','userdownline')->where('company_id','=',$companyid)->get();
                                if (count($getCompanyRoot) > 0) {
                                    $_companyrootid = $getCompanyRoot[0]['company_root_id'];
                                }
                                if ($confAppSysID == $_companyrootid) {
                                    $rpanalytics[$a]['platformfee'] =  number_format($platformlocator[0]['platformfee'],2,'.','');
                                }else{
                                    $rpanalytics[$a]['platformfee'] =  number_format($platformlocator[0]['rootplatformfee'],2,'.','');
                                }
                                /** GET ROOT COMPANY */
                            }else{
                                $adjustPlatformFee = LeadspeekReport::select(DB::raw('SUM(leadspeek_reports.platform_price_lead) as platformfee'))
                                                                ->whereIn('leadspeek_reports.leadspeek_api_id', function($query) use($startDate,$endDate) {
                                                                    $query->select('report_analytics.leadspeek_api_id')
                                                                            ->from('report_analytics')
                                                                            ->where('report_analytics.leadspeek_type','=','locator')
                                                                            ->where(DB::raw('DATE_FORMAT(report_analytics.date,"%Y%m%d")'),'>=',$startDate)
                                                                            ->where(DB::raw('DATE_FORMAT(report_analytics.date,"%Y%m%d")'),'<=',$endDate);
                                                                });
                                $adjustPlatformFee->join('leadspeek_users',DB::raw('TRIM(leadspeek_reports.leadspeek_api_id)'),'=',DB::raw('TRIM(leadspeek_users.leadspeek_api_id)'))
                                                                            ->join('users','leadspeek_users.user_id','=','users.id')
                                                                            ->where('users.company_root_id','<>',$confAppSysID);
                                $adjustPlatformFee = $adjustPlatformFee->where(DB::raw('DATE_FORMAT(leadspeek_reports.clickdate,"%Y%m%d")'),'>=',$startDate)
                                                                            ->where(DB::raw('DATE_FORMAT(leadspeek_reports.clickdate,"%Y%m%d")'),'<=',$endDate)
                                                                            ->get();

                                $_totalFee = ($platformlocator[0]['platformfee'] - $adjustPlatformFee[0]['platformfee']) + $platformlocator[0]['rootplatformfee'];
                                $rpanalytics[$a]['platformfee'] = number_format($_totalFee,2,'.','');
                            }
                        }

                    }else{/** IF NOT SUPER ROOT */
                        $_rootplatformfee = $ra['serveclient'] * $profitLocator;
                        $rpanalytics[$a]['platformfee'] =  number_format($_rootplatformfee,2,'.','');
                    }
                                                   
                }

                if ($ra['leadspeek_type'] == 'enhance') {
                    if ($profitEnhance == "") { /** IF SUPER ROOT */
                        $platformenhance = LeadspeekReport::select(DB::raw('SUM(leadspeek_reports.platform_price_lead) as platformfee'),DB::raw('SUM(leadspeek_reports.root_price_lead) as rootplatformfee'))
                                                        ->whereIn('leadspeek_reports.leadspeek_api_id', function($query) use($startDate,$endDate) {
                                                            $query->select('report_analytics.leadspeek_api_id')
                                                                    ->from('report_analytics')
                                                                    ->where('report_analytics.leadspeek_type','=','enhance')
                                                                    ->where(DB::raw('DATE_FORMAT(report_analytics.date,"%Y%m%d")'),'>=',$startDate)
                                                                    ->where(DB::raw('DATE_FORMAT(report_analytics.date,"%Y%m%d")'),'<=',$endDate);
                                                        });

                                                        if ($companyid != "" && $companyid != "0") {
                                                            $platformenhance->join('leadspeek_users','leadspeek_reports.leadspeek_api_id','=','leadspeek_users.leadspeek_api_id')
                                                                        ->where('leadspeek_users.company_id','=',$companyid);
                                                        }
                                            
                                                        if ($campaignid != '' && $campaignid != '0') {
                                                            $platformenhance->where('leadspeek_reports.leadspeek_api_id','=',$campaignid);
                                                        }

                                                        if ($companyrootid != "" && $companyid == "0" && $campaignid == '0') {
                                                            $platformenhance->join('leadspeek_users',DB::raw('TRIM(leadspeek_reports.leadspeek_api_id)'),'=',DB::raw('TRIM(leadspeek_users.leadspeek_api_id)'))
                                                                            ->join('users','leadspeek_users.user_id','=','users.id')
                                                                            ->where('users.company_root_id','=',$companyrootid);
                                                            /** FOR EXCLUDE AGENCY FROM REPORT ANALYTICS */
                                                            if (count($ExcludeAgency) > 0) {
                                                                $platformenhance = $platformenhance->whereNotIn('leadspeek_users.company_id',$ExcludeAgency);
                                                            }
                                                            /** FOR EXCLUDE AGENCY FROM REPORT ANALYTICS */
                                                        }else if ($companyrootid == "" && $companyid == "" && $campaignid == '' && count($ExcludeAgency) > 0) {
                                                            $platformenhance->join('leadspeek_users',DB::raw('TRIM(leadspeek_reports.leadspeek_api_id)'),'=',DB::raw('TRIM(leadspeek_users.leadspeek_api_id)'))
                                                                            ->join('users','leadspeek_users.user_id','=','users.id')
                                                                            ->whereNotIn('leadspeek_users.company_id',$ExcludeAgency);
                                                        }

                                                        $platformenhance = $platformenhance->where(DB::raw('DATE_FORMAT(leadspeek_reports.clickdate,"%Y%m%d")'),'>=',$startDate)
                                                                            ->where(DB::raw('DATE_FORMAT(leadspeek_reports.clickdate,"%Y%m%d")'),'<=',$endDate)
                                                                            ->get();

                        if (count($platformenhance) > 0) {
                            if ($confAppSysID != $companyrootid && ($companyrootid != "" && $companyid == "0" && $campaignid == '0')) {
                                $rpanalytics[$a]['platformfee'] =  number_format($platformenhance[0]['rootplatformfee'],2,'.','');
                            }else if ($confAppSysID == $companyrootid && ($companyrootid != "" && $companyid == "0" && $campaignid == '0')) {
                                $rpanalytics[$a]['platformfee'] =  number_format($platformenhance[0]['platformfee'],2,'.','');
                            }else if ($companyrootid == "" && ($companyid != "" && $companyid != "0") && ($campaignid != "" && $campaignid != "0")) {
                                /** GET ROOT COMPANY */
                                $_companyrootid = ""; 
                                $getCompanyRoot = User::select('company_root_id')->where('user_type','=','userdownline')->where('company_id','=',$companyid)->get();
                                if (count($getCompanyRoot) > 0) {
                                    $_companyrootid = $getCompanyRoot[0]['company_root_id'];
                                }
                                if ($confAppSysID == $_companyrootid) {
                                    $rpanalytics[$a]['platformfee'] =  number_format($platformenhance[0]['platformfee'],2,'.','');
                                }else{
                                    $rpanalytics[$a]['platformfee'] =  number_format($platformenhance[0]['rootplatformfee'],2,'.','');
                                }
                                /** GET ROOT COMPANY */
                            }else{
                                
                                $adjustPlatformFee = LeadspeekReport::select(DB::raw('SUM(leadspeek_reports.platform_price_lead) as platformfee'))
                                                    ->whereIn('leadspeek_reports.leadspeek_api_id', function($query) use($startDate,$endDate) {
                                                        $query->select('report_analytics.leadspeek_api_id')
                                                                ->from('report_analytics')
                                                                ->where('report_analytics.leadspeek_type','=','enhance')
                                                                ->where(DB::raw('DATE_FORMAT(report_analytics.date,"%Y%m%d")'),'>=',$startDate)
                                                                ->where(DB::raw('DATE_FORMAT(report_analytics.date,"%Y%m%d")'),'<=',$endDate);
                                                    });
                                $adjustPlatformFee->join('leadspeek_users',DB::raw('TRIM(leadspeek_reports.leadspeek_api_id)'),'=',DB::raw('TRIM(leadspeek_users.leadspeek_api_id)'))
                                                                            ->join('users','leadspeek_users.user_id','=','users.id')
                                                                            ->where('users.company_root_id','<>',$confAppSysID);
                                $adjustPlatformFee = $adjustPlatformFee->where(DB::raw('DATE_FORMAT(leadspeek_reports.clickdate,"%Y%m%d")'),'>=',$startDate)
                                                                            ->where(DB::raw('DATE_FORMAT(leadspeek_reports.clickdate,"%Y%m%d")'),'<=',$endDate)
                                                                            ->get();
                                $_totalFee = ($platformenhance[0]['platformfee'] - $adjustPlatformFee[0]['platformfee']) + $platformenhance[0]['rootplatformfee'];
                                $rpanalytics[$a]['platformfee'] = number_format($_totalFee,2,'.','');
                            }
                            
                        }

                    }else{ /** IF NOT SUPER ROOT */
                        $_rootplatformfee = $ra['serveclient'] * $profitEnhance;
                        $rpanalytics[$a]['platformfee'] =  number_format($_rootplatformfee,2,'.','');
                    }
                                                   
                }

                if ($ra['leadspeek_type'] == 'b2b') {
                    if ($profitB2B == "") { /** IF SUPER ROOT */
                        $platformb2b = LeadspeekReport::select(DB::raw('SUM(leadspeek_reports.platform_price_lead) as platformfee'),DB::raw('SUM(leadspeek_reports.root_price_lead) as rootplatformfee'))
                                                        ->whereIn('leadspeek_reports.leadspeek_api_id', function($query) use($startDate,$endDate) {
                                                            $query->select('report_analytics.leadspeek_api_id')
                                                                    ->from('report_analytics')
                                                                    ->where('report_analytics.leadspeek_type','=','b2b')
                                                                    ->where(DB::raw('DATE_FORMAT(report_analytics.date,"%Y%m%d")'),'>=',$startDate)
                                                                    ->where(DB::raw('DATE_FORMAT(report_analytics.date,"%Y%m%d")'),'<=',$endDate);
                                                        });

                                                        if ($companyid != "" && $companyid != "0") {
                                                            $platformb2b->join('leadspeek_users','leadspeek_reports.leadspeek_api_id','=','leadspeek_users.leadspeek_api_id')
                                                                        ->where('leadspeek_users.company_id','=',$companyid);
                                                        }
                                            
                                                        if ($campaignid != '' && $campaignid != '0') {
                                                            $platformb2b->where('leadspeek_reports.leadspeek_api_id','=',$campaignid);
                                                        }

                                                        if ($companyrootid != "" && $companyid == "0" && $campaignid == '0') {
                                                            $platformb2b->join('leadspeek_users',DB::raw('TRIM(leadspeek_reports.leadspeek_api_id)'),'=',DB::raw('TRIM(leadspeek_users.leadspeek_api_id)'))
                                                                            ->join('users','leadspeek_users.user_id','=','users.id')
                                                                            ->where('users.company_root_id','=',$companyrootid);
                                                            /** FOR EXCLUDE AGENCY FROM REPORT ANALYTICS */
                                                            if (count($ExcludeAgency) > 0) {
                                                                $platformb2b = $platformb2b->whereNotIn('leadspeek_users.company_id',$ExcludeAgency);
                                                            }
                                                            /** FOR EXCLUDE AGENCY FROM REPORT ANALYTICS */
                                                        }else if ($companyrootid == "" && $companyid == "" && $campaignid == '' && count($ExcludeAgency) > 0) {
                                                            $platformb2b->join('leadspeek_users',DB::raw('TRIM(leadspeek_reports.leadspeek_api_id)'),'=',DB::raw('TRIM(leadspeek_users.leadspeek_api_id)'))
                                                                            ->join('users','leadspeek_users.user_id','=','users.id')
                                                                            ->whereNotIn('leadspeek_users.company_id',$ExcludeAgency);
                                                        }

                                                        $platformb2b = $platformb2b->where(DB::raw('DATE_FORMAT(leadspeek_reports.clickdate,"%Y%m%d")'),'>=',$startDate)
                                                                            ->where(DB::raw('DATE_FORMAT(leadspeek_reports.clickdate,"%Y%m%d")'),'<=',$endDate)
                                                                            ->get();

                        if (count($platformb2b) > 0) {
                            if ($confAppSysID != $companyrootid && ($companyrootid != "" && $companyid == "0" && $campaignid == '0')) {
                                $rpanalytics[$a]['platformfee'] =  number_format($platformb2b[0]['rootplatformfee'],2,'.','');
                            }else if ($confAppSysID == $companyrootid && ($companyrootid != "" && $companyid == "0" && $campaignid == '0')) {
                                $rpanalytics[$a]['platformfee'] =  number_format($platformb2b[0]['platformfee'],2,'.','');
                            }else if ($companyrootid == "" && ($companyid != "" && $companyid != "0") && ($campaignid != "" && $campaignid != "0")) {
                                /** GET ROOT COMPANY */
                                $_companyrootid = ""; 
                                $getCompanyRoot = User::select('company_root_id')->where('user_type','=','userdownline')->where('company_id','=',$companyid)->get();
                                if (count($getCompanyRoot) > 0) {
                                    $_companyrootid = $getCompanyRoot[0]['company_root_id'];
                                }
                                if ($confAppSysID == $_companyrootid) {
                                    $rpanalytics[$a]['platformfee'] =  number_format($platformb2b[0]['platformfee'],2,'.','');
                                }else{
                                    $rpanalytics[$a]['platformfee'] =  number_format($platformb2b[0]['rootplatformfee'],2,'.','');
                                }
                                /** GET ROOT COMPANY */
                            }else{
                                $adjustPlatformFee = LeadspeekReport::select(DB::raw('SUM(leadspeek_reports.platform_price_lead) as platformfee'))
                                                    ->whereIn('leadspeek_reports.leadspeek_api_id', function($query) use($startDate,$endDate) {
                                                        $query->select('report_analytics.leadspeek_api_id')
                                                                ->from('report_analytics')
                                                                ->where('report_analytics.leadspeek_type','=','b2b')
                                                                ->where(DB::raw('DATE_FORMAT(report_analytics.date,"%Y%m%d")'),'>=',$startDate)
                                                                ->where(DB::raw('DATE_FORMAT(report_analytics.date,"%Y%m%d")'),'<=',$endDate);
                                                    });
                                $adjustPlatformFee->join('leadspeek_users',DB::raw('TRIM(leadspeek_reports.leadspeek_api_id)'),'=',DB::raw('TRIM(leadspeek_users.leadspeek_api_id)'))
                                                                            ->join('users','leadspeek_users.user_id','=','users.id')
                                                                            ->where('users.company_root_id','<>',$confAppSysID);
                                $adjustPlatformFee = $adjustPlatformFee->where(DB::raw('DATE_FORMAT(leadspeek_reports.clickdate,"%Y%m%d")'),'>=',$startDate)
                                                                            ->where(DB::raw('DATE_FORMAT(leadspeek_reports.clickdate,"%Y%m%d")'),'<=',$endDate)
                                                                            ->get();
                                $_totalFee = ($platformb2b[0]['platformfee'] - $adjustPlatformFee[0]['platformfee']) + $platformb2b[0]['rootplatformfee'];
                                $rpanalytics[$a]['platformfee'] = number_format($_totalFee,2,'.','');
                            }
                            
                        }

                    }else{ /** IF NOT SUPER ROOT */
                        $_rootplatformfee = $ra['serveclient'] * $profitB2B;
                        $rpanalytics[$a]['platformfee'] =  number_format($_rootplatformfee,2,'.','');
                    }
                                                   
                }

                if ($ra['leadspeek_type'] == 'simplifi') {
                    if ($profitSimplifi == "") { /** IF SUPER ROOT */
                        $platformsimplifi = LeadspeekInvoice::select(DB::raw('SUM(leadspeek_invoices.platform_total_amount) as platformfee'))
                                                        ->whereIn('leadspeek_invoices.leadspeek_api_id', function($query) use($startDate,$endDate) {
                                                            $query->select('report_analytics.leadspeek_api_id')
                                                                    ->from('report_analytics')
                                                                    ->where('report_analytics.leadspeek_type','=','simplifi')
                                                                    ->where(DB::raw('DATE_FORMAT(report_analytics.date,"%Y%m%d")'),'>=',$startDate)
                                                                    ->where(DB::raw('DATE_FORMAT(report_analytics.date,"%Y%m%d")'),'<=',$endDate);
                                                        });

                                                        if ($companyid != "" && $companyid != "0") {
                                                            $platformsimplifi->join('leadspeek_users','leadspeek_invoices.leadspeek_api_id','=','leadspeek_users.leadspeek_api_id')
                                                                        ->where('leadspeek_users.company_id','=',$companyid);
                                                        }
                                            
                                                        if ($campaignid != '' && $campaignid != '0') { // 
                                                            $platformsimplifi->where('leadspeek_invoices.leadspeek_api_id','=',$campaignid);
                                                        }

                                                        if ($companyrootid != "" && $companyid == "0" && $campaignid == '0') {
                                                            $platformsimplifi->join('leadspeek_users',DB::raw('TRIM(leadspeek_invoices.leadspeek_api_id)'),'=',DB::raw('TRIM(leadspeek_users.leadspeek_api_id)'))
                                                                            ->join('users','leadspeek_users.user_id','=','users.id')
                                                                            ->where('users.company_root_id','=',$companyrootid);
                                                            /** FOR EXCLUDE AGENCY FROM REPORT ANALYTICS */
                                                            if (count($ExcludeAgency) > 0) {
                                                                $platformsimplifi = $platformsimplifi->whereNotIn('leadspeek_users.company_id',$ExcludeAgency);
                                                            }
                                                            /** FOR EXCLUDE AGENCY FROM REPORT ANALYTICS */
                                                        }else if ($companyrootid == "" && $companyid == "" && $campaignid == '' && count($ExcludeAgency) > 0) {
                                                            $platformsimplifi->join('leadspeek_users',DB::raw('TRIM(leadspeek_invoices.leadspeek_api_id)'),'=',DB::raw('TRIM(leadspeek_users.leadspeek_api_id)'))
                                                                            ->join('users','leadspeek_users.user_id','=','users.id')
                                                                            ->whereNotIn('leadspeek_users.company_id',$ExcludeAgency);
                                                        }

                                                        $platformsimplifi = $platformsimplifi->where(DB::raw('DATE_FORMAT(leadspeek_invoices.created_at,"%Y%m%d")'),'>=',$startDate)
                                                                            ->where(DB::raw('DATE_FORMAT(leadspeek_invoices.created_at,"%Y%m%d")'),'<=',$endDate)
                                                                            ->get();

                        if (count($platformsimplifi) > 0) {
                            if ($confAppSysID == $companyrootid && ($companyrootid != "" && $companyid == "0" && $campaignid == '0')) {
                                $rpanalytics[$a]['platformfee'] =  number_format(($platformsimplifi[0]['platformfee'] / 2),2,'.','');
                            }else if ($confAppSysID == $companyrootid && ($companyrootid != "" && $companyid == "0" && $campaignid == '0')) {
                                $rpanalytics[$a]['platformfee'] =  number_format($platformb2b[0]['platformfee'] / 2,2,'.','');
                            }else if ($companyrootid == "" && ($companyid != "" && $companyid != "0") && ($campaignid != "" && $campaignid != "0")) {
                                /** GET ROOT COMPANY */
                                $_companyrootid = ""; 
                                $getCompanyRoot = User::select('company_root_id')->where('user_type','=','userdownline')->where('company_id','=',$companyid)->get();
                                if (count($getCompanyRoot) > 0) {
                                    $_companyrootid = $getCompanyRoot[0]['company_root_id'];
                                }
                                if ($confAppSysID == $_companyrootid) {
                                    $rpanalytics[$a]['platformfee'] =  number_format(($platformsimplifi[0]['platformfee'] / 2),2,'.','');
                                }
                                /** GET ROOT COMPANY */
                            }else{
                                $adjustPlatformFee = LeadspeekInvoice::select(DB::raw('SUM(leadspeek_invoices.platform_total_amount) as platformfee'))
                                                    ->whereIn('leadspeek_invoices.leadspeek_api_id', function($query) use($startDate,$endDate) {
                                                        $query->select('report_analytics.leadspeek_api_id')
                                                                ->from('report_analytics')
                                                                ->where('report_analytics.leadspeek_type','=','simplifi')
                                                                ->where(DB::raw('DATE_FORMAT(report_analytics.date,"%Y%m%d")'),'>=',$startDate)
                                                                ->where(DB::raw('DATE_FORMAT(report_analytics.date,"%Y%m%d")'),'<=',$endDate);
                                                    });
                                $adjustPlatformFee->join('leadspeek_users',DB::raw('TRIM(leadspeek_invoices.leadspeek_api_id)'),'=',DB::raw('TRIM(leadspeek_users.leadspeek_api_id)'))
                                                                            ->join('users','leadspeek_users.user_id','=','users.id')
                                                                            ->where('users.company_root_id','<>',$confAppSysID);
                                $adjustPlatformFee = $adjustPlatformFee->where(DB::raw('DATE_FORMAT(leadspeek_invoices.created_at,"%Y%m%d")'),'>=',$startDate)
                                                                            ->where(DB::raw('DATE_FORMAT(leadspeek_invoices.created_at,"%Y%m%d")'),'<=',$endDate)
                                                                            ->get();
                                $_totalFee = ($platformsimplifi[0]['platformfee'] - $adjustPlatformFee[0]['platformfee']);
                                $rpanalytics[$a]['platformfee'] = number_format($_totalFee / 2 ,2,'.','');
                            }
                            
                        }

                    }else{ /** IF NOT SUPER ROOT */
                        $_rootplatformfee = $ra['serveclient'] * $profitSimplifi;
                        $rpanalytics[$a]['platformfee'] =  number_format($_rootplatformfee,2,'.','');
                    }
                }

                if (isset($rpanalytics[$a]['bigbdmremainingleads']) && $rpanalytics[$a]['bigbdmremainingleads'] < 0 ) {
                    $rpanalytics[$a]['bigbdmremainingleads'] = 0;
                }

                $leadspeekType = $ra['leadspeek_type'];
                if (isset($zerobounceSummary[$leadspeekType])) {
                    $rpanalytics[$a]['zerobounce_details'] = $zerobounceSummary[$leadspeekType]->toArray();
                } else {
                    $rpanalytics[$a]['zerobounce_details'] = [
                        'valid' => 0, 'invalid' => 0, 'catch_all' => 0,
                        'unknown' => 0, 'spamtrap' => 0, 'abuse' => 0, 'do_not_mail' => 0
                    ];
                }

                if (isset($truelistSummary[$leadspeekType])) {
                    $rpanalytics[$a]['truelist_details'] = $truelistSummary[$leadspeekType];
                } else {
                    $rpanalytics[$a]['truelist_details'] = (object)[]; 
                }
            }
                        
            return response()->json(array('result'=>'success','data'=>$rpanalytics));
    }

    public function getPixelReport(Request $request) 
    {
        date_default_timezone_set('America/Chicago');

        /* VARIABLE */
        $pixelLeadRecordService = App::make(PixelLeadRecordService::class);
        $startDate = (isset($request->startDate))?date('Ymd',strtotime($request->startDate)):"";
        $endDate = (isset($request->endDate))?date('Ymd',strtotime($request->endDate)):"";
        $companyrootid = (isset($request->companyrootid))?trim($request->companyrootid):"";
        // info(__FUNCTION__, ['startDate' => $startDate,'endDate' => $endDate,'companyrootid' => $companyrootid]);
        /* VARIABLE */

        /* GET PIXEL LEAD RECORDS ALL VISITOR ID */
        $pixelAllVisitorUs = $pixelLeadRecordService->getPixelLeadRecords('all', $startDate, $endDate, $companyrootid, 'us'); // negara us
        $pixelAllVisitorNonUs = $pixelLeadRecordService->getPixelLeadRecords('all', $startDate, $endDate, $companyrootid, 'non_us'); // bukan negara us
        $pixelAllVisitorAllCountry = [
            'running' => ((int) ($pixelAllVisitorUs['running'] ?? 0)) + ((int) ($pixelAllVisitorNonUs['running'] ?? 0)),
            'stopped' => ((int) ($pixelAllVisitorUs['stopped'] ?? 0)) + ((int) ($pixelAllVisitorNonUs['stopped'] ?? 0)),
            'paused' => ((int) ($pixelAllVisitorUs['paused'] ?? 0)) + ((int) ($pixelAllVisitorNonUs['paused'] ?? 0)),
            'paused_run' => ((int) ($pixelAllVisitorUs['paused_run'] ?? 0)) + ((int) ($pixelAllVisitorNonUs['paused_run'] ?? 0)),
            'total_all' => ((int) ($pixelAllVisitorUs['total_all'] ?? 0)) + ((int) ($pixelAllVisitorNonUs['total_all'] ?? 0)),
        ]; // semua negara
        $pixelAllVisitor = [
            'all_country' => $pixelAllVisitorAllCountry,
            'us_country' => $pixelAllVisitorUs,
            'non_us_country' => $pixelAllVisitorNonUs,
        ]; // format response
        // info(__FUNCTION__, ['pixelAllVisitorUs' => $pixelAllVisitorUs, 'pixelAllVisitorNonUs' => $pixelAllVisitorNonUs, 'pixelAllVisitorAllCountry' => $pixelAllVisitorAllCountry, 'pixelAllVisitor' => $pixelAllVisitor]);
        /* GET PIXEL LEAD RECORDS ALL VISITOR  ID */

        /* GET PIXEL LEAD RECORDS UNIQUE VISITOR ID */
        $pixelUniqueVisitorUs = $pixelLeadRecordService->getPixelLeadRecords('grouping', $startDate, $endDate, $companyrootid, 'us'); // negara us
        $pixelUniqueVisitorNonUs = $pixelLeadRecordService->getPixelLeadRecords('grouping', $startDate, $endDate, $companyrootid, 'non_us'); // bukan negara us
        $pixelUniqueVisitorAllCountry = [
            'running' => ((int) ($pixelUniqueVisitorUs['running'] ?? 0)) + ((int) ($pixelUniqueVisitorNonUs['running'] ?? 0)),
            'stopped' => ((int) ($pixelUniqueVisitorUs['stopped'] ?? 0)) + ((int) ($pixelUniqueVisitorNonUs['stopped'] ?? 0)),
            'paused' => ((int) ($pixelUniqueVisitorUs['paused'] ?? 0)) + ((int) ($pixelUniqueVisitorNonUs['paused'] ?? 0)),
            'paused_run' => ((int) ($pixelUniqueVisitorUs['paused_run'] ?? 0)) + ((int) ($pixelUniqueVisitorNonUs['paused_run'] ?? 0)),
            'total_all' => ((int) ($pixelUniqueVisitorUs['total_all'] ?? 0)) + ((int) ($pixelUniqueVisitorNonUs['total_all'] ?? 0)),
        ]; // semua negara
        $pixelUniqueVisitor = [
            'all_country' => $pixelUniqueVisitorAllCountry,
            'us_country' => $pixelUniqueVisitorUs,
            'non_us_country' => $pixelUniqueVisitorNonUs,
        ]; // format response
        // info(__FUNCTION__, ['pixelUniqueVisitorUs' => $pixelUniqueVisitorUs, 'pixelUniqueVisitorNonUs' => $pixelUniqueVisitorNonUs, 'pixelUniqueVisitorAllCountry' => $pixelUniqueVisitorAllCountry, 'pixelUniqueVisitor' => $pixelUniqueVisitor]);
        /* GET PIXEL LEAD RECORDS UNIQUE VISITOR ID */

        /* GET PIXEL LEAD RECORD EMPTY VISITOR ID */
        $countPixelEmptyVisitorUs = $pixelLeadRecordService->countPixelLeadRecords('empty', $startDate, $endDate, $companyrootid, 'us'); // negara us
        $countPixelEmptyVisitorNonUs = $pixelLeadRecordService->countPixelLeadRecords('empty', $startDate, $endDate, $companyrootid, 'non_us'); // bukan negara us
        $countPixelEmptyVisitorAllCountry = ((int) ($countPixelEmptyVisitorUs)) + ((int) ($countPixelEmptyVisitorNonUs)); // semua negara
        $countPixelEmptyVisitor = [
            'all_country' => [ 'total' => $countPixelEmptyVisitorAllCountry ],
            'us_country' => [ 'total' => $countPixelEmptyVisitorUs ],
            'non_us_country' => [ 'total' => $countPixelEmptyVisitorNonUs ],
        ]; // format response
        // info(__FUNCTION__, ['countPixelEmptyVisitorUs' => $countPixelEmptyVisitorUs, 'countPixelEmptyVisitorNonUs' => $countPixelEmptyVisitorNonUs, 'countPixelEmptyVisitorAllCountry' => $countPixelEmptyVisitorAllCountry, 'countPixelEmptyVisitor' => $countPixelEmptyVisitor]);
        /* GET PIXEL LEAD RECORD EMPTY VISITOR ID */

        /* GET PIXEL LEAD RECORD EMPTY VISITOR ID */
        $countPixelFeedbackVisitorUs = $pixelLeadRecordService->countPixelLeadRecords('feedback', $startDate, $endDate, $companyrootid, 'us'); // negara us
        $countPixelFeedbackVisitorNonUs = $pixelLeadRecordService->countPixelLeadRecords('feedback', $startDate, $endDate, $companyrootid, 'non_us'); // bukan negara us
        $countPixelFeedbackVisitorAllCountry = ((int) ($countPixelFeedbackVisitorUs)) + ((int) ($countPixelFeedbackVisitorNonUs)); // semua negara
        $countPixelFeedbackVisitor = [
            'all_country' => ['total' => $countPixelFeedbackVisitorAllCountry ],
            'us_country' => ['total' => $countPixelFeedbackVisitorUs ],
            'non_us_country' => ['total' => $countPixelFeedbackVisitorNonUs ],
        ]; // format response
        // info(__FUNCTION__, ['countPixelFeedbackVisitorUs' => $countPixelFeedbackVisitorUs, 'countPixelFeedbackVisitorNonUs' => $countPixelFeedbackVisitorNonUs, 'countPixelFeedbackVisitorAllCountry' => $countPixelFeedbackVisitorAllCountry, 'countPixelFeedbackVisitor' => $countPixelFeedbackVisitor]);
        /* GET PIXEL LEAD RECORD EMPTY VISITOR ID */

        return response()->json(['result' => 'success', 'pixelAllVisitor' => $pixelAllVisitor, 'pixelUniqueVisitor' => $pixelUniqueVisitor, 'countPixelEmptyVisitor' => $countPixelEmptyVisitor, 'countPixelFeedbackVisitor' => $countPixelFeedbackVisitor]);
    }

    public function downloadReportAnalytic(Request $request) {
        date_default_timezone_set('America/Chicago');

        $startDate = (isset($request->startDate))?date('Ymd',strtotime($request->startDate)):"";
        $endDate = (isset($request->endDate))?date('Ymd',strtotime($request->endDate)):"";
        $companyid = (isset($request->companyid))?trim($request->companyid):"";
        $campaignid = (isset($request->campaignid))?trim($request->campaignid):"";
        $companyrootid = (isset($request->companyrootid))?trim($request->companyrootid):"";

        return (new AnalyticExport)->betweenDate($startDate,$endDate,$companyid,$campaignid,$companyrootid)->download('reportAnalytics_' . $startDate . '_' . $endDate . '.csv');
    }

    public function downloadFailedLeadRecord(Request $request)
    {
        $startDate = (isset($request->startDate))?date('Ymd',strtotime($request->startDate)):"";
        $endDate = (isset($request->endDate))?date('Ymd',strtotime($request->endDate)):"";

        return (new FailedLeadRecordExport)->betweenDate($startDate, $endDate)->download('failedLeadRecord_' . $startDate . '_' . $endDate . '.csv');
    }

    public function downloadAgenciesReport(Request $request)
    {
        $company_root_id = (isset($request->companyRootID))? $request->companyRootID :"";
        $sort_by = (isset($request->SortBy))? $request->SortBy :"";
        $order_by = (isset($request->OrderBy))? $request->OrderBy :"";
        $search_key = (isset($request->searchKey))? $request->searchKey :"";
        $agency_status = json_decode($request->input('AgencyStatus', '{}'), true);
        $campaign_status = json_decode($request->input('CampaignStatus', '{}'), true);
        $open_api_status = json_decode($request->input('OpenApiStatus', '{}'), true);
        $selected_date = (!empty($request->SelectedDate))? $request->SelectedDate : '';
        $idsys = (isset($request->idsys))? $request->idsys :"";

        return (new AgenciesExport)->param($company_root_id,$sort_by,$order_by,$search_key,$agency_status,$campaign_status,$selected_date,$open_api_status, $idsys)->download('agenciesReport_' . $company_root_id .  '.csv');
    }

    public function downloadClientsReport(Request $request)
    {
        $company_id = (isset($request->companyID))? $request->companyID :"";
        $sort_by = (isset($request->SortBy))? $request->SortBy :"";
        $order_by = (isset($request->OrderBy))? $request->OrderBy :"";
        $search_key = (isset($request->searchKey))? $request->searchKey :"";
        $card_status = json_decode($request->input('CardStatus', '{}'), true);
        $campaign_status = json_decode($request->input('CampaignStatus', '{}'), true);

        return (new ClientsExport)->param($company_id,$sort_by,$order_by,$search_key,$campaign_status, $card_status)->download('clientsReport_' . $company_id .  '.csv');
    }

    public function getRootList(Request $request) {
        $rootList = User::select('users.company_id as id','companies.company_name as name','companies.domain')
                        ->join('companies','users.company_id','=','companies.id')
                        ->where('users.active','=','T')
                        ->whereNull('company_parent')
                        ->get();
        return response()->json(array('result'=>'success','params'=>$rootList));
    }

    public function getSalesList(Request $request) {
        $CompanyID = (isset($request->CompanyID))?$request->CompanyID:'';
        $saleslist = User::select('id','name','status_acc')
                        ->where('user_type','=','sales')
                        ->where('active','=','T')
                        ->where('status_acc','=','completed');
        if ($CompanyID != "") {
            $saleslist->where('company_id','=',$CompanyID);
        }
            $saleslist = $saleslist->get();
        return response()->json(array('result'=>'success','params'=>$saleslist));
    }

    public function setSalesPerson(Request $request) {
        $companyID = (isset($request->companyID))?$request->companyID:'';
        $salesRep = (isset($request->salesRep))?$request->salesRep:'';
        $salesAE = (isset($request->salesAE))?$request->salesAE:'';
        $salesRef = (isset($request->salesRef))?$request->salesRef:'';
       
        
        if ($companyID != "") {
            /** FOR SALE REPS */
            $chkSalesCompany = CompanySale::select('id')
                                            ->where('company_id','=',$companyID)
                                            ->where('sales_title','=','Sales Representative')
                                            ->get();

            if (count($chkSalesCompany) == 0) {
                if (trim($salesRep) != "") {
                    $createSalesRep = CompanySale::create([
                                            'company_id' => $companyID,
                                            'sales_id' => $salesRep,
                                            'sales_title' => 'Sales Representative',
                                        ]);
                }
            }else{
                if (trim($salesRep) != "") {
                    $updateSalesRep = CompanySale::find($chkSalesCompany[0]['id']);
                    $updateSalesRep->sales_id = $salesRep;
                    $updateSalesRep->save();
                }else{
                    $deleteSalesRep = CompanySale::find($chkSalesCompany[0]['id']);
                    $deleteSalesRep->delete();
                }
            }
            /** FOR SALE REPS */

            /** FOR Account Executive */
            $chkSalesCompany = CompanySale::select('id')
                                            ->where('company_id','=',$companyID)
                                            ->where('sales_title','=','Account Executive')
                                            ->get();

            if (count($chkSalesCompany) == 0) {
                if (trim($salesAE) != "") {
                    $createSalesAE = CompanySale::create([
                                            'company_id' => $companyID,
                                            'sales_id' => $salesAE,
                                            'sales_title' => 'Account Executive',
                                        ]);
                }
            }else{
                if (trim($salesAE) != "") {
                    $updateSalesAE = CompanySale::find($chkSalesCompany[0]['id']);
                    $updateSalesAE->sales_id = $salesAE;
                    $updateSalesAE->save();
                }else{
                    $deleteSalesAE = CompanySale::find($chkSalesCompany[0]['id']);
                    $deleteSalesAE->delete();
                }
            }
            /** FOR Account Executive */
            
            /** FOR SALES REFERRAL */
            $chkSalesCompany = CompanySale::select('id')
                                            ->where('company_id','=',$companyID)
                                            ->where('sales_title','=','Account Referral')
                                            ->get();

            if (count($chkSalesCompany) == 0) {
                if (trim($salesRef) != "") {
                    
                    $createSalesRef = CompanySale::create([
                                            'company_id' => $companyID,
                                            'sales_id' => $salesRef,
                                            'sales_title' => 'Account Referral',
                                        ]);
                }
            }else{
                if (trim($salesRef) != "") {
                    $updateSalesAE = CompanySale::find($chkSalesCompany[0]['id']);
                    $updateSalesAE->sales_id = $salesRef;
                    $updateSalesAE->save();
                }else{
                    $deleteSalesAE = CompanySale::find($chkSalesCompany[0]['id']);
                    $deleteSalesAE->delete();
                }
            }
            /** FOR SALES REFERRAL */

        }

        return response()->json(array('result'=>'success'));
    }

    public function cancelsubscription(Request $request) {
        try {
            $companyID = (isset($request->companyID) && $request->companyID != '')?$request->companyID:'';
            $userID = $request->user()->id;
            $ipAddress = ($request->header('Clientip') !== null)?$request->header('Clientip'):$request->ip();
            
            $disabledCampaign = $this->stopAllCampaignAndBill($companyID,$ipAddress,$userID,true);

            /** DEACTIVE ALL THE CLIENTS */
            $deactiveClients = User::where('company_parent','=',$companyID)
                                        ->where('user_type','=','client')
                                        ->update(['active' => 'F']);
            /** DEACTIVE ALL THE CLIENTS */

            /** DEACTIVE AGENCY AND ADMIN */
            $deactiveAgencyAndAdmin = User::where('company_id','=',$companyID)
                                        ->where(function ($query) {
                                            $query->where('user_type','=','user')
                                            ->orWhere('user_type','=','userdownline');
                                        })
                                        ->update(['active' => 'F']);
            /** DEACTIVE AGENCY AND ADMIN */

            /** LOG ACTION */
            $agency_id = User::where('company_id','=',$companyID)->where('user_type','=','userdownline')->first();
            $target_userid = $agency_id ? $agency_id->id : '';

            $loguser = $this->logUserAction($userID,'Agency Cancel Subscription Success','CompanyID : ' . $companyID . ' | userID :' . $userID,$ipAddress,$target_userid);
            /** LOG ACTION */

            return response()->json(array('result'=>'success'));
        }catch(Exception $e) {
            /** LOG ACTION */
            $agency_id = User::where('company_id','=',$companyID)->where('user_type','=','userdownline')->first();
            $target_userid = $agency_id ? $agency_id->id : '';

            $loguser = $this->logUserAction($userID,'Agency Cancel Subscription FAILED','CompanyID : ' . $companyID . ' | userID :' . $userID . ' | ErrMessage: ' . $e->getMessage(),$ipAddress,$target_userid);
            /** LOG ACTION */
            return response()->json(array('result'=>'failed'));
        }
    }

    //// LOG PER KATEGORI (DISTINCT ACTION)
    public function getActionLogs(Request $request)
    {
        // return $request->user_id;
        // $user_id = (isset($request->user_id) && $request->user_id != '')?$request->user_id:'';
        // $companyID = (isset($request->companyID) && $request->companyID != '')?$request->companyID:'';

        $data = UserLog::select(DB::raw("distinct action"))
                                ->orderBy('action', 'asc')->get();

        return response()->json($data);
    }

    //// ALL LOG PER USER 
    public function getUserLogs(Request $request)
    {
        // $user_id = (isset($request->user_id) && $request->user_id != '')?$request->user_id:'';
        $companyID = (isset($request->companyID) && $request->companyID != '')?$request->companyID:'';
        $action = (isset($request->action) && $request->action != '')?$request->action:'';
        $user_type = (isset($request->user_type) && $request->user_type != '')?$request->user_type:'';
        $slc_user_id = (isset($request->user_id) && $request->user_id != '')?$request->user_id:'';
        $page = (isset($request->page) && $request->page != '')?$request->page:1;
        $leadspeek_api_id = (isset($request->leadspeek_api_id) && $request->leadspeek_api_id != '')?$request->leadspeek_api_id:'';
        $log_date_range = (isset($request->log_date_range) && $request->log_date_range != '')?$request->log_date_range: [];
        $log_start_date = '';
        $log_end_date = '';
        if (!empty($log_date_range) && is_array($log_date_range)) {
            $log_start_date = isset($log_date_range[0]) ? $log_date_range[0] : '';
            $log_end_date = isset($log_date_range[1]) ? $log_date_range[1] : '';
        }
        

        $user_company = User::where('company_id','=',$companyID)
                                        ->where('user_type','=','userdownline')
                                        ->where(['active' => 'T'])->get();
        $user_id = $user_company[0]->id;

        $data_agency = UserLog::select(DB::raw("user_logs.id, user_logs.user_id, user_logs.action, SUBSTRING_INDEX(user_logs.user_ip, '|', 1) AS user_ip, case when user_logs.user_ip LIKE '%|%' then SUBSTRING_INDEX(user_logs.user_ip, '|',-1) else '' end as location, 
                                        user_logs.description, u.name, c.company_name,
                                        DATE_FORMAT(user_logs.created_at, '%m-%d-%Y %H:%i:%s') AS create_date, 'agency' as type_user, user_logs.created_at"))
                                ->join('users as u', 'u.id', '=', 'user_logs.user_id')
                                ->join('companies as c', 'c.id', '=', 'u.company_id')
                                // ->where('user_logs.user_id', $user_id)
                                ->where('c.id', $companyID)
                                ->where('u.user_type','=','userdownline')
                                ->where('u.active','=','T')->orderBy('user_logs.created_at', 'desc');
        if($action)
        {
            $data_agency->where('user_logs.action', $action);
        }

        if($slc_user_id)
        {
            $data_agency->where('user_logs.user_id', $slc_user_id);
        }


        if (!empty($log_date_range) && trim($log_start_date) != '' && trim($log_end_date) != '') {
        $data_agency->whereDate('user_logs.created_at','>=',$log_start_date)
                     ->whereDate('user_logs.created_at','<=',$log_end_date);
        }

        if (trim($leadspeek_api_id) != '') {
            $data_agency->where('user_logs.description','like','%' . $leadspeek_api_id . '%');
        }

        $data_admin = UserLog::select(DB::raw("user_logs.id, user_logs.user_id, user_logs.action, SUBSTRING_INDEX(user_logs.user_ip, '|', 1) AS user_ip, case when user_logs.user_ip LIKE '%|%' then SUBSTRING_INDEX(user_logs.user_ip, '|',-1) else '' end as location, 
                                        user_logs.description, u.name, c.company_name,
                                        DATE_FORMAT(user_logs.created_at, '%m-%d-%Y %H:%i:%s') AS create_date, 'admin' as type_user, user_logs.created_at"))
                                ->join('users as u', function ($join) {
                                    $join->on('u.id', '=', 'user_logs.user_id')
                                         ->where('u.user_type', '=', 'user')
                                         ->where('u.active','=','T');
                                })
                                ->join('companies as c', 'c.id', '=', 'u.company_id')
                                ->where('c.id', $companyID)->orderBy('user_logs.created_at', 'desc');
        if($action)
        {
            $data_admin->where('user_logs.action', $action);
        }

        if($slc_user_id)
        {
            $data_admin->where('user_logs.user_id', $slc_user_id);
        }

        if (!empty($log_date_range) && trim($log_start_date) != '' && trim($log_end_date) != '') {
            $data_admin->whereDate('user_logs.created_at','>=',$log_start_date)
                        ->whereDate('user_logs.created_at','<=',$log_end_date);
        }

        if (trim($leadspeek_api_id) != '') {
            $data_admin->where('user_logs.description','like','%' . $leadspeek_api_id . '%');
        }

        $data_client = UserLog::select(DB::raw("user_logs.id, user_logs.user_id, user_logs.action, SUBSTRING_INDEX(user_logs.user_ip, '|', 1) AS user_ip, case when user_logs.user_ip LIKE '%|%' then SUBSTRING_INDEX(user_logs.user_ip, '|',-1) else '' end as location, 
                                        user_logs.description, u.name, c2.company_name,
                                        DATE_FORMAT(user_logs.created_at, '%m-%d-%Y %H:%i:%s') AS create_date, 'client' as type_user, user_logs.created_at"))
                                ->join('users as u', function ($join) {
                                    $join->on('u.id', '=', 'user_logs.user_id')
                                        ->where('u.user_type', '=', 'client')
                                        ->where('u.active','=','T');
                                    })
                                ->join('companies as c', 'c.id', '=', 'u.company_parent')
                                ->join('companies as c2', 'c2.id', '=', 'u.company_id')
                                ->where('c.id', $companyID)->orderBy('user_logs.created_at', 'desc');
        if($action)
        {
            $data_client->where('user_logs.action', $action);
        }

        if($slc_user_id)
        {
            $data_client->where('user_logs.user_id', $slc_user_id);
        }

        if (!empty($log_date_range) && trim($log_start_date) != '' && trim($log_end_date) != '') {
        $data_client->whereDate('user_logs.created_at','>=',$log_start_date)
                    ->whereDate('user_logs.created_at','<=',$log_end_date);
        }

        if (trim($leadspeek_api_id) != '') {
            $data_client->where('user_logs.description','like','%' . $leadspeek_api_id . '%');
        }

        //// root
        $id_root = User::where('company_id','=',$companyID)->first()->company_root_id;

        $data_root = UserLog::select(DB::raw("user_logs.id, user_logs.user_id, user_logs.action, SUBSTRING_INDEX(user_logs.user_ip, '|', 1) AS user_ip, case when user_logs.user_ip LIKE '%|%' then SUBSTRING_INDEX(user_logs.user_ip, '|',-1) else '' end as location, 
                                        user_logs.description, u.name, c.company_name,
                                        DATE_FORMAT(user_logs.created_at, '%m-%d-%Y %H:%i:%s') AS create_date, 'Enterprise' as type_user, user_logs.created_at"))
                                ->join('users as u', function ($join) {
                                    $join->on('u.id', '=', 'user_logs.user_id')
                                        ->whereIn('u.user_type', ['userdownline', 'user'])
                                        ->where('u.active','=','T');
                                    })
                                ->join('companies as c', 'c.id', '=', 'u.company_id')
                                ->where('c.id', $id_root)
                                ->whereIn('target_user_id', function ($query) use ($companyID) {
                                    $query->select('id')
                                          ->distinct()
                                          ->from('users')
                                          ->where(function ($q) use ($companyID) {
                                              $q->where('company_id', $companyID)
                                                ->orWhere('company_parent', $companyID);
                                          });
                                })
                                ->orderBy('user_logs.created_at', 'desc');
        if($action)
        {
            $data_root->where('user_logs.action', $action);
        }

        if($slc_user_id)
        {
            $data_root->where('user_logs.user_id', $slc_user_id);
        }

        if (!empty($log_date_range) && trim($log_start_date) != '' && trim($log_end_date) != '') {
        $data_root->whereDate('user_logs.created_at','>=',$log_start_date)
                    ->whereDate('user_logs.created_at','<=',$log_end_date);
        }

        if (trim($leadspeek_api_id) != '') {
            $data_root->where('user_logs.description','like','%' . $leadspeek_api_id . '%');
        }
        //// root

        // $hasilunion = $data_agency->unionAll($data_admin)->unionAll($data_client)->paginate(5, ['*'], 'page', $page);

        $hasilunion = [];
        if($user_type == 'admin')
        {
            $hasilunion = $data_agency->unionAll($data_admin)->orderBy('created_at', 'desc')->paginate(5, ['*'], 'page', $page);
        } 
        else if ($user_type == 'client')
        {
            $hasilunion = $data_client->paginate(5, ['*'], 'page', $page);
        }
        else if ($user_type == 'root')
        {
            $hasilunion = $data_root->paginate(5, ['*'], 'page', $page);
        }
        else if ($user_type == '9999')  //// SELECT ALL USER 
        {
            $hasilunion = $data_agency->unionAll($data_admin)->unionAll($data_client)->unionAll($data_root)->orderBy('created_at', 'desc')->paginate(5, ['*'], 'page', $page);
        }
        
        return response()->json($hasilunion);
    }

    //// SHOW ALL USER IN AGENCY 
    public function getUsers(Request $request)
    {
        // $user_id = (isset($request->user_id) && $request->user_id != '')?$request->user_id:'';
        $companyID = (isset($request->companyID) && $request->companyID != '')?$request->companyID:'';
        $user_type = (isset($request->user_type) && $request->user_type != '')?$request->user_type:'';

        $data_admin = User::select(DB::raw("users.id, c.company_name, users.name, 'admin' as user_type,
                                            case when users.user_type = 'userdownline' then 'Admin (owner)'
                                            when users.user_type = 'user' then 'Admin' end as type_user_tx"))
                                ->join('companies as c', 'c.id', '=', 'users.company_id')
                                ->where('users.active','=','T')
                                ->whereIn('user_type', ['user', 'userdownline'])
                                ->where('c.id', $companyID);
        

        $data_client = User::select(DB::raw("users.id, c2.company_name, users.name, 'client' as user_type, users.user_type as type_user_tx"))
                                ->join('companies as c', 'c.id', '=', 'users.company_parent')
                                ->join('companies as c2', 'c2.id', '=', 'users.company_id')
                                ->where('users.active','=','T')
                                ->where('user_type', 'client')
                                ->where('c.id', $companyID);

        $id_root = User::where('company_id','=',$companyID)->first()->company_root_id;
        $data_root = User::select(DB::raw("users.id, c.company_name, users.name, 'client' as user_type, 
                                            case when users.user_type = 'userdownline' then 'Admin (owner)'
                                            when users.user_type = 'user' then 'Admin' end as type_user_tx"))
                                ->join('companies as c', 'c.id', '=', 'users.company_id')
                                ->where('users.active','=','T')
                                ->whereIn('user_type', ['user', 'userdownline'])
                                ->where('c.id', $id_root);

        //// cek kiriman dari front
        //// jika user_type = admin tampil $data_admin
        //// jika user_type = client tampil $data_client
        //// jika user_type = 9999 (select all) tampil union all
        $show_data = [];
        if($user_type == 'admin')
        {
            $show_data = $data_admin->get();
        } 
        else if ($user_type == 'client')
        {
            $show_data = $data_client->get();
        }
        else if ($user_type == 'root')
        {
            $show_data = $data_root->get();
        }
        else if ($user_type == '9999')  //// SELECT ALL USER 
        {
            $show_data = $data_admin->unionAll($data_client)->get();
        }

        return response()->json($show_data);
    }

    public function getUserCampaigns(Request $request){
        $company_id = (isset($request->companyID) && !empty($request->companyID)) ? $request->companyID : '';
        $campaigns = LeadspeekUser::select('leadspeek_users.id','leadspeek_users.leadspeek_api_id','leadspeek_users.campaign_name','companies.company_name')
                        ->leftJoin('users','leadspeek_users.user_id','users.id')
                        ->leftJoin('companies','users.company_id','companies.id')
                        ->where('leadspeek_users.company_id', $company_id)
                        ->where('leadspeek_users.archived','F')
                        ->orderBy('leadspeek_users.campaign_name','ASC')
                        ->get();
        return response()->json(['campaigns' => $campaigns]);
    }


    public function downloadUserLogs(Request $request) {
        date_default_timezone_set('America/Chicago');

        $companyID = (isset($request->companyID) && $request->companyID != '')?$request->companyID:'';
        $action = (isset($request->action) && $request->action != '')?$request->action:'';
        $user_id = (isset($request->user_id) && $request->user_id != '')?$request->user_id:'';
        $user_type = (isset($request->user_type) && $request->user_type != '')?$request->user_type:'';
        $date_range = (isset($request->date_range) && $request->date_range != '')?$request->date_range:[];
        $leadspeek_api_id = (isset($request->leadspeek_api_id) && $request->leadspeek_api_id != '')?$request->leadspeek_api_id:'all';

        return (new UserLogsExport)
            ->betweenDate($companyID,$action,$user_id,$user_type,$date_range,$leadspeek_api_id)
            ->download('userLogs_' . $companyID . '_' . $action .'.csv');
    }

    public function get_coupon(Request $request){
        date_default_timezone_set('America/Chicago');
        
        $code = $request->input('code');

        if (!$code) {
            return response()->json(['result' => 'error', 'message' => 'Coupon code required'],400);
        }

        $coupon = Coupon::where('code',$code)->where('status',1)->first();
        if (!empty($coupon)) {

            $now = Carbon::today(); 
            $start_date = Carbon::parse($coupon->start_date)->startOfDay();
            $expired_Date = Carbon::parse($coupon->expired_date)->endOfDay();

            if ($now->lt($start_date)) {
                return response()->json([
                    'result' => 'error',
                    'message' => 'Coupon is not available.',
                ], 400);
            }

            if ($now->gt($expired_Date)) {
                return response()->json([
                    'result' => 'error',
                    'message' => 'Coupon has expired.',
                ], 400);
            }
            
            if (($coupon->usage_limit != null) && ($coupon->usage_count >= $coupon->usage_limit)) {
                return response()->json([
                    'result' => 'error', 'message' => "Coupon code reach it's limit"
                ],400);
            }

            return response()->json(['result' => 'success', 'data' => $coupon->makeHidden(['id','created_at', 'updated_at'])]);
        }else {
            return response()->json(['result' => 'error', 'message' => 'Invalid coupon code.',],404);
        }
    }

    public function downloadCsvCleanID(Request $request) 
    {
        date_default_timezone_set('America/Chicago');

        /* VARIABLE */
        $company_id = $request->company_id ?? '';
        $file_id = $request->file_id ?? '';
        $from_email = $request->from_email ?? '';
        /* VARIABLE */

        /* GET CLEAN ID FILE */
        $dt_file = CleanIDFile::from('clean_id_file as file')
            ->select('file.*','export.file_download')
            ->leftJoin('clean_id_export as export', 'export.file_id', '=', 'file.id')
            ->where('file.id', $file_id)
            ->first();
        $user_id = $dt_file->user_id ?? "";
        if(empty(($dt_file) || empty($user_id)))
            return response()->json(['result' => 'error', 'message' => 'clean id file not found'], 404);
        /* GET CLEAN ID FILE */

        /* VALIDATION BETWEEN USER_ID, COMPANY_ID, FILE_ID MATCH */
        $agencyMatch = User::where('company_id', $company_id)->where('user_type', 'userdownline')->first();
        $agencyIDMatch = $agencyMatch->id ?? '';
        if(empty($agencyMatch) || empty($agencyIDMatch))
            return response()->json(['result' => 'error', 'message' => 'user not match'], 404);
        if($user_id != $agencyIDMatch)
            return response()->json(['result' => 'error', 'message' => 'user not match'], 400);
        /* VALIDATION BETWEEN USER_ID, COMPANY_ID, FILE_ID MATCH */

        /* WHEN CLEAN ID NOT DONE */
        if($dt_file->status != 'done')
            return response()->json(['result' => 'error', 'message' => 'clean id still processing'], 400);
        /* WHEN CLEAN ID NOT DONE */
        
        /* VALIDATION PAYMENT STATUS AGENCY */
        $payment_status = $agencyMatch->payment_status ?? "";
        $failed_campaignid_string = $agencyMatch->failed_campaignid ?? "";
        $clean_api_id = $dt_file->clean_api_id ?? "";
        if($payment_status == 'failed' && !empty($failed_campaignid_string)){
            $failed_campaignid_array = explode('|', $failed_campaignid_string);
            $failed_cleanid_array = CleanIdFile::whereIn('clean_api_id', array_filter($failed_campaignid_array, 'is_numeric'))->pluck('clean_api_id')->toArray();
            if(in_array($clean_api_id, $failed_cleanid_array)){
                return response()->json(['result' => 'error', 'message' => "You can't download these cleaned results because there is an outstanding balance for this Clean ID. Please settle the unpaid amount to continue."], 400);
            }
        }
        /* VALIDATION PAYMENT STATUS AGENCY */

        $file_name = $dt_file->file_name ?? '';
        $filenameOnly = pathinfo($file_name, PATHINFO_FILENAME);
        $file_download = $dt_file->file_download ?? '';
        $file_type = $dt_file->file_type ?? '';
        // info("result_clean_id_$filenameOnly.csv");
        // info('', ['from_email' => $from_email, 'get_type' => gettype($from_email)]);

        if(strtolower($file_type) == 'upload' && !empty($file_download)){
            if($from_email == 'true'){
                return redirect()->away($file_download);
            }else{
                return response()->json(['result' => 'success', 'file_download' => $file_download]);
            }
        }else{
            return (new CleanIDResultExport)->betweenDate($file_id)->download("result_clean_id_$filenameOnly.csv");
        }
    }
}
