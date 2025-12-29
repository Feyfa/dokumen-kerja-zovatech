<?php

private function getNextTimeBillingCampaign(string $campaignId)
{
    // date_default_timezone_set('America/Chicago');
    
    /* GET CAMPAIGN */
    $lp_invoice_date_next_month = null;
    $campaign = LeadspeekUser::where('leadspeek_api_id', $campaignId)->where('archived', 'F')->first();
    if(!$campaign){
        return ['status' => 'error', 'status_code' => 404, 'message' => 'Campaign Not Found', 'lp_invoice_date_next_month' => $lp_invoice_date_next_month];
    }
    // info('getNextTimeBillingCampaign', ['campaign' => $campaign]);
    /* GET CAMPAIGN */

    /* PROCESS GET NEXT TIME BILLING CAMPAIGN */
    if($campaign->paymentterm == 'Weekly')
    {
        $lp_invoice_date_next_month = Carbon::parse($campaign->start_billing_date)
            ->startOfDay()   // ⬅️ KUNCI
            ->addWeek()
            ->addDay()
            ->format('Y-m-d');
        info('', ['lp_invoice_date_next_month' => $lp_invoice_date_next_month]);
    }
    elseif($campaign->paymentterm == 'Monthly')
    {

    }
    /* PROCESS GET NEXT TIME BILLING CAMPAIGN */

    return ['status' => 'success','status_code' => 200,'message' => 'Success Get Next Time Billing Campaign', 'lp_invoice_date_next_month' => $lp_invoice_date_next_month];
}

public function processinvoicemonthly(Request $request) 
{
    /* CARA QUEUE REDIS */
    // dapatkan tanggal beserta jam nya hari ini, format "Y-m-d H:i:s"
    $nowDate = Carbon::now();
    // 1 hari sebelum $nowDate 
    $previousDate = $nowDate->copy()->subDay();
    // atur waktunya menjadi akhir jam 29:59:59
    $endBillingDate = $previousDate->copy()->endOfDay();

    $clientList = LeadspeekUser::select('leadspeek_users.id','leadspeek_users.start_billing_date','companies.id as company_id','leadspeek_users.user_id','leadspeek_users.leadspeek_type',
                                        'leadspeek_users.leadspeek_api_id','companies.company_name','leadspeek_users.report_sent_to','leadspeek_users.admin_notify_to',
                                        'leadspeek_users.paymentterm','leadspeek_users.lp_enddate','leadspeek_users.lp_limit_startdate','leadspeek_users.campaign_name','leadspeek_users.campaign_information_type_local','leadspeek_users.advance_information',
                                        'leadspeek_users.cost_perlead','leadspeek_users.lp_max_lead_month','leadspeek_users.lp_min_cost_month','users.customer_payment_id','users.customer_card_id','users.email','leadspeek_users.company_id as company_parent','users.active','users.company_root_id')
                    ->join('users','leadspeek_users.user_id','=','users.id')
                    ->join('companies','users.company_id','=','companies.id')
                    ->where(function($query){
                        $query->where(function($query){
                            $query->where('leadspeek_users.active','=','T')
                                ->where('leadspeek_users.disabled','=','F')
                                ->where('leadspeek_users.active_user','=','T');
                        })
                        ->orWhere(function($query){
                            $query->where('leadspeek_users.active','=','F')
                                ->where('leadspeek_users.disabled','=','T')
                                ->where('leadspeek_users.active_user','=','T');
                        })
                        ->orWhere(function($query){
                            $query->where('leadspeek_users.active','=','F')
                                ->where('leadspeek_users.disabled','=','F')
                                ->where('leadspeek_users.active_user','=','T');
                        });
                    })
                    ->where('leadspeek_users.paymentterm','=','Monthly')
                    ->where('leadspeek_users.archived','=','F')
                    ->where('users.user_type','=','client')
                    // ->where(DB::raw('DATE_FORMAT(DATE_ADD(leadspeek_users.start_billing_date,INTERVAL 1 MONTH),"%Y%m%d%H%i%s")'),'<=',date("YmdHis"))
                    ->whereRaw("TIMESTAMPDIFF(MONTH, leadspeek_users.start_billing_date, ?) >= 1", [$endBillingDate])
                    /*->where(function($query) {
                        $query->where('leadspeek_users.cost_perlead','>',0)
                                ->orWhere('leadspeek_users.lp_max_lead_month','>',0)
                                ->orWhere('leadspeek_users.lp_min_cost_month','>',0);
                    })*/
                    ->orderBy(DB::raw("DATE_FORMAT(leadspeek_users.start_billing_date,'%Y%m%d')"),'ASC')
                    ->get()
                    ->toArray(); // jangan lupa toArray() karena di dispatch job, jangan pake collection
}