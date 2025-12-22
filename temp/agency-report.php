<?php
namespace App\Exports;

use Carbon\Carbon;
use App\Models\User;
use App\Models\LeadspeekUser;
use App\Models\CompanySetting;
use App\Models\ReportAnalytic;
use App\Models\LeadspeekReport;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;


// class AgenciesExport implements FromArray,WithHeadings
class AgenciesExport implements FromQuery, WithHeadings, WithMapping, ShouldAutoSize
{
    use Exportable;
    protected $headercol;
    protected $company_root_id;
    protected $sort_by;
    protected $order_by;
    protected $search_key;
    protected $agency_status;
    protected $campaign_status;
    protected $selected_date;
    protected $open_api_status;
    protected $idsys;

    public function __construct()
    {
        $this->headercol = array(
            "Agency Name",
            "Client Name", 
            "Email", 
            "Phone", 
            "Clients Created", 
            "Under performing campaigns", 
            "Status Credit Card", 
            "Status Stripe", 
            "Status Google", 
            "Status Manual BIll", 
            "Status Domain", 
            "Status Logo", 
            "Status SMTP", 
            "Status Default Price",
            "Status Open Api",
            "Clients with Active Credit Card", 
            "Active Campaigns", 
            "Site Campaigns Active", 
            "Search Campaigns Active", 
            "Enhance Campaigns Active", 
            "Stop Campaigns", 
            "Total Weekly Revenue", 
            "Min Spend Start", 
            "Account Executive", 
            "Referral Agent",
            "Sales Agent",
            "Created On",
            "Selected Report Date",
        );
    }

    public function chunkSize(): int
    {
        return 10; 
    }


    public function headings(): array
    {
        return $this->headercol;
    }


    public function param($company_root_id,$sort_by,$order_by,$search_key,$agency_status,$campaign_status,$selected_date = '',$open_api_status, $idsys)
    {
        $this->company_root_id = !empty($company_root_id) ? $company_root_id : '';
        $this->sort_by = !empty($sort_by) ? $sort_by : '';
        $this->order_by = !empty($order_by) ? $order_by : '';
        $this->search_key = !empty($search_key) ? $search_key : '';
        $this->agency_status = !empty($agency_status) ? $agency_status : [];
        $this->campaign_status = !empty($campaign_status) ? $campaign_status : [];
        $this->open_api_status = !empty($open_api_status) ? $open_api_status : [];
        $this->selected_date = !empty($selected_date) ? $selected_date : '';
        $this->idsys = !empty($idsys) ? $idsys : '';
        return $this;
    }

    public function query(){
        date_default_timezone_set('America/Chicago');

        $encryptionKey = substr(hash('sha256', env('APP_KEY')), 0, 16);
        $root_company_ids = User::select('company_id')
        ->whereNull('company_parent')
        ->where('user_type','userdownline')
        ->pluck('company_id');

        $selected_date = !empty($this->selected_date) ? $this->selected_date : '';

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
                'companies.created_at',
            )
            ->leftJoin('companies', 'companies.id', '=', 'users.company_id')
            ->leftJoin('company_stripes AS cs', 'cs.company_id', '=', 'users.company_id')
            ->leftJoin('open_api_users', 'open_api_users.company_id', '=', 'users.company_id');
            
            if ($this->company_root_id !=  '' && $this->company_root_id != 'all') {
                $agencies->where('users.company_parent', $this->company_root_id);
            }else {
                $agencies->whereIn('users.company_parent', $root_company_ids);
            }

            $agencies->where('users.user_type', 'userdownline')
            ->where('users.active', 'T');

            if (!empty($selected_date)) {
                $agencies->whereDate('companies.created_at',' <=', $selected_date);
            }

            $search_key = $this->search_key;
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
            $AgencyStatus = $this->agency_status;
            $agencyStatus = (object) array_merge([
                'active' => false,
                'failed' => false,
                'inactive' => false,
            ], $AgencyStatus);

            $stripeNotConnected = $agencyStatus->active;
            $manualBill = $agencyStatus->failed;
            $inactiveCardStatus = $agencyStatus->inactive;

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
            $CampaignStatus = $this->campaign_status;
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
            $OpenApiStatus = $this->open_api_status;
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

            $order = $this->order_by;
            if (trim($order) != '') {
                if (trim($order) == 'descending') {
                    $order = "DESC";
                }else{
                    $order = "ASC";
                }
            }

            $sort_by = $this->sort_by;
            if (trim($sort_by) != '') {
                if (trim($sort_by) == "agency_name") {
                    $agencies = $agencies->orderByEncrypted('companies.company_name',$order);
                }else if (trim($sort_by) == "client_name") {
                    $agencies = $agencies->orderByEncrypted('users.name',$order);
                }else if (trim($sort_by) == "created_on") {
                    $agencies = $agencies->orderBy(DB::raw('CAST(companies.created_at AS DATETIME)'),$order);
                }
                $agencies->get();
            } else {
                $agencies->orderByEncrypted('companies.company_name')->get();
            }

        return $agencies;
    }

    public function map($row): array
    {

        $selected_date = '';
        if (!empty($this->selected_date)) {
            $selected_date = !empty($this->selected_date) ? $this->selected_date : '';
        }
        $weeks_ago = '';
        if (!empty($this->selected_date)) {
            $weeks_ago = Carbon::parse($this->selected_date)->subDays(7)->format('Y-m-d');
        }

        $idsys = !empty($this->idsys) ? $this->idsys : '';

        //underperform campaigns
        $underperform_campaigns = LeadspeekUser::select('leadspeek_users.id','leadspeek_users.lp_limit_leads')
            ->leftJoin('leadspeek_reports', function($join) use ($selected_date,$weeks_ago) {
                $join->on('leadspeek_users.leadspeek_api_id', '=', 'leadspeek_reports.leadspeek_api_id')
                ->whereDate('leadspeek_reports.clickdate', '<=', $selected_date)
                ->whereDate('leadspeek_reports.clickdate', '>=', $weeks_ago);

            })
            ->where('leadspeek_users.company_id', $row->company_id)
            ->where('leadspeek_users.active', 'T')
            ->where('leadspeek_users.disabled', 'F')
            ->where('leadspeek_users.active_user', 'T')
            ->groupBy('leadspeek_users.id','leadspeek_users.lp_limit_leads')
            ->havingRaw('COUNT(leadspeek_reports.id) < (0.6 * (leadspeek_users.lp_limit_leads * 7))')
            ->get();

        //credit card
        $status_credit_card = '';
        if (!empty($row->conn_cc_customer_card) && !empty($row->conn_cc_customer_payment) && ($row->conn_cc_payment_status == '' || $row->conn_cc_payment_status == null)) {
            $status_credit_card = 'active';
        }elseif (empty($row->conn_cc_customer_card) || empty($row->conn_cc_customer_payment)) {
            $status_credit_card = 'inactive';
        }elseif ($row->conn_cc_payment_status == 'failed') {
            $status_credit_card = 'failed';
        }

        //stripe
        $status_stripe = 'Not Registered';
        if (!empty($row->conn_stripe_connect_id)) {
            if (!empty($row->conn_stripe_status) && $row->conn_stripe_status == 'completed') {
                $status_stripe = 'Completed';
            }elseif (!empty($row->conn_stripe_status) && $row->conn_stripe_status == 'pending') {
                $status_stripe = 'Pending';
            }elseif (!empty($row->conn_stripe_status) && $row->conn_stripe_status == 'inverification') {
                $status_stripe = 'Inverification';
            }
        }else {
            $status_stripe = 'Not Registered';
        }

        //google
        $status_google = !empty($row->conn_google) ? 'Connected' : 'Not Connected';

        //manual_bill
        $status_manual_bill = !empty($row->conn_manual_bill) && $row->conn_manual_bill == 'T' ? 'true' :'false';
        
        //url
        $domain = !empty($row->conn_domain) ? true :false;
        $status_domain = $row->conn_status_domain == 'ssl_acquired' ? true :false;
        $status_domain_connection = ($domain && $status_domain) ? 'true' : 'false';

        //logo
        $status_logo = !empty($row->conn_logo) || !empty($row->conn_logo_login_register)  ? 'true' :'false';  
        
        //SMTP
        $status_smtp = $row->conn_smtp == 'customsmtpmenu' ? 'true' :'false'; 

        //Default Price
        $status_default_price = $row->conn_agency_default_price == 'agencydefaultprice' ? 'true' :'false';

        // Open Api Status
        $status_api_mode = $row->api_mode;
        $status_generate_api = $row->is_user_generate_api;
        $desc_open_api = '-';
        if($row->company_parent == $idsys){
            if($status_api_mode == 'T' && $status_generate_api){
                $desc_open_api = 'Both the developer menu and API key are active. All API features are available.';
            } else if ($status_api_mode == 'T'){
                $desc_open_api = 'The developer menu is enabled, but no API key has been generated.';
            } else if ($status_generate_api){
                $desc_open_api = 'An API key has been generated, but the developer menu is still disabled.';
            } else {
                $desc_open_api = 'API access is currently inactive. Neither the developer menu nor the API key is enabled.';
            }
        }

        // //weekly_revenue
        $totalRevenue = DB::table(function ($query) use ($row,$selected_date,$weeks_ago) {
            $query->select(DB::raw('SUM(platform_price_lead) AS total_profit'))
                ->from('leadspeek_reports')
                ->leftJoin('users', 'leadspeek_reports.user_id', '=', 'users.id')
                ->where('users.company_parent', $row->company_id)
                ->where(function($query) {
                    $query->whereNull('leadspeek_reports.topup_id')
                          ->orWhere('leadspeek_reports.topup_id', 0)
                          ->orWhere('leadspeek_reports.topup_id', '');
                })
                ->whereDate('leadspeek_reports.clickdate', '<=', $selected_date)
                ->whereDate('leadspeek_reports.clickdate', '>=', $weeks_ago)
                // ->whereDate('leadspeek_reports.clickdate', '>=', Carbon::today()->subDays(7))
                ->unionAll(
                    DB::table('topup_campaigns')
                        ->select(DB::raw('SUM((platform_price) * total_leads) AS total_profit'))
                        ->where('company_id', $row->company_id)
                        ->whereDate('created_at', '<=', $selected_date)
                        ->whereDate('created_at', '>=', $weeks_ago)
                        // ->whereDate('created_at', '>=', Carbon::today()->subDays(7))
                );
        })->select(DB::raw('SUM(total_profit) AS total_revenue'))->first();
        $weekly_revenue = !empty($totalRevenue->total_revenue) ? $totalRevenue->total_revenue : 0;
                        
        return [
            !empty($row->agency_name) ? $row->agency_name : '-',
            !empty($row->client_name) ? $row->client_name : '-',
            !empty($row->agency_email) ? $row->agency_email : '-',
            !empty($row->agency_phone) ? $row->agency_phone : '-',
            $row->clients_created > 0 ? $row->clients_created : '0',
            count($underperform_campaigns) > 0 ? count($underperform_campaigns) : '0',
            $status_credit_card,
            $status_stripe,
            $status_google,
            $status_manual_bill,
            $status_domain_connection,
            $status_logo,
            $status_smtp,
            $status_default_price,
            $desc_open_api,
            $row->clients_active_credit_card > 0 ? $row->clients_active_credit_card : '0',
            $row->recently_start_campaign > 0 ? $row->recently_start_campaign : '0',
            $row->local_active_campaign > 0 ? $row->local_active_campaign : '0',
            $row->locator_active_campaign > 0 ? $row->locator_active_campaign : '0',
            $row->enhance_active_campaign > 0 ? $row->enhance_active_campaign : '0',
            $row->total_stopped_campaign > 0 ? $row->total_stopped_campaign : '0',
            number_format($weekly_revenue,2,'.',''),
            Carbon::parse($row->trial_end_date)->format('m-d-Y'),
            !empty($row->account_executive) ? $row->account_executive : '-',
            !empty($row->account_referral) ? $row->account_referral : '-',
            !empty($row->sales_representative) ? $row->sales_representative : '-',
            Carbon::parse($row->created_at)->format('m-d-Y'),
            Carbon::parse($selected_date)->format('m-d-Y'),
        ];
    }

}