<?php

class DataWalletEmm
{
    // ===========TESTING===========
    public function toDollar($cents, $decimal = 2)
    {
        if ($cents === null) return 0;

        return (float) number_format($cents / 100, $decimal, '.', '');
    }
    public function jidantest1()
    {
        /*SETUP */
        $leadspeek_api_id = "23024637";
        $campaign = LeadspeekUser::from('leadspeek_users as lu')->select('u.company_root_id','u.company_parent')->join('users as u', 'u.id', '=', 'lu.user_id')->where('lu.leadspeek_api_id', '=', $leadspeek_api_id)->where('u.user_type', '=', 'client')->first();
        // return info('', ['campaign' => $campaign]);
        if(!$campaign){
            return response()->json(['result'=>'failed','msg'=>'campaign not found']);
        }
        $accConID = $this->check_connected_account($campaign->company_parent,$campaign->company_root_id);
        $stripepublish = $this->getcompanysetting($campaign->company_root_id,'rootstripe');
        $stripeseckey = (isset($stripepublish->secretkey) && !empty($stripepublish->secretkey)) ? $stripepublish->secretkey : config('services.stripe.secret');
        $stripe = new StripeClient(['api_key' => $stripeseckey, 'stripe_version' => '2020-08-27']);
        // info('', ['accConID' => $accConID,'stripepublish' => $stripepublish,'stripeseckey' => $stripeseckey,]);
        /*SETUP */

        try
        {
            /* BALANCE CONNECTED ACCOUNT */
            // $balanceRoot = $stripe->balance->retrieve([]);
            // $balanceAgency = $stripe->balance->retrieve([], [
            //     'stripe_account' => $accConID,
            // ]);
            // return response()->json([
            //     'balanceRoot' => $balanceRoot,
            //     'balanceAgency' => $balanceAgency,
            //     'pendingRoot' => $this->toDollar($balanceRoot->pending[0]->amount),
            //     'availableRoot' => $this->toDollar($balanceRoot->available[0]->amount),
            //     'pendingAgency' => $this->toDollar($balanceAgency->pending[0]->amount),
            //     'availableAgency' => $this->toDollar($balanceAgency->available[0]->amount),
            // ]);
            /* BALANCE CONNECTED ACCOUNT */

            /* REFUND CAMPAIGN */
            $payment_intent_id = "pi_3SclaPRrOfJImWE21hQIzxeY";
            $amount_refund = (float) number_format(4.63 * 100,2,'.','');
            $refund = $stripe->refunds->create([
                'payment_intent' => $payment_intent_id,
                'amount' => $amount_refund, // dalam cent
            ], [
                'stripe_account' => $accConID
            ]);
            return response()->json([
                'refund' => $refund,
            ]);
            /* REFUND CAMPAIGN */   
        }
        catch(\Throwable $th)
        {
            return response()->json(['result'=>'failed','msg'=>$th->getMessage()], 400);
        }
    }
    // ===========TESTING===========
}

hai saya punya data misalnya begini

$datas = [
    [
        'id' => 1,
        'cost_perlead' => 0.1,
        'total_leads' => 10,
    ],
    [
        'id' => 2,
        'cost_perlead' => 0.1,
        'total_leads' => 10,
    ],
    [
        'id' => 3,
        'cost_perlead' => 0.1,
        'total_leads' => 10,
    ],
    [
        'id' => 4,
        'cost_perlead' => 0.1,
        'total_leads' => 10,
    ],
];
$idsExclude = [1,2];



public function processRefundToClient($leadspeek_api_id)
{
    try
    {
        /* GET TOPUPS CAMPAIGNS */
        // ambil topup campaign yang belum done
        $topupCampaigns = Topup::where('leadspeek_api_id', $leadspeek_api_id)
            ->where('topup_status', '<>', 'done')
            ->orderBy('id', 'desc')
            ->get()
            ->toArray();
        $topupCampaigns_ids = array_column($topupCampaigns, 'id');
        $topupCampaigns_count = count($topupCampaigns);
        /* GET TOPUPS CAMPAIGNS */

        /* GET INVOICES TOPUP CAMPAIGNS */
        // buat variable untuk invoice final
        $invoicesFinal = [];

        // ambil invoice yang sudah ada topup_campaign_id
        $invoicesWithTopupCampaign = LeadspeekInvoice::whereIn('topup_campaign_id', $topupCampaigns_ids)
            ->where('leadspeek_api_id', $leadspeek_api_id)
            ->where('invoice_type', 'campaign')
            ->orderBy('id', 'desc')
            ->get()
            ->toArray();
        $invoicesWithTopupCampaign_ids = array_column($invoicesWithTopupCampaign, 'id');
        $invoicesWithTopupCampaign_topupCampaignsIds = array_column($invoicesWithTopupCampaign, 'topup_campaign_id');
        $invoicesWithTopupCampaign_count = count($invoicesWithTopupCampaign);

        if($invoicesWithTopupCampaign_count == 0) // jika leadspeek_invoices sama sekali ngga ada topup_campaign_id
        {
            $invoicesBufferId = [];
            foreach($topupCampaigns as $topup)
            {
                $cost_perlead = $topup['cost_perlead'];
                $total_leads = $topup['total_leads'];
                $invoices = LeadspeekInvoice::whereNotIn('id', $invoicesBufferId)
                    ->where('leadspeek_api_id', $leadspeek_api_id)
                    ->where('invoice_type', 'campaign')
                    ->where('cost_leads', $cost_perlead)
                    ->where('total_leads', $total_leads)
                    ->orderBy('id', 'desc')
                    ->first()
                    ->toArray();
                if(!empty($invoices))
                {
                    $invoicesFinal[] = $invoices;
                    $invoicesBufferId[] = $invoices['id'];
                }
            }
        }
        elseif($invoicesWithTopupCampaign_count < $topupCampaigns_count) // jika leadspeek_invoices sebagian ada topup_campaign_id, dan masih ada sebagian leadspeek_invoices yang belum ada topup_campaign_id, maka ambil yang sebagian yang belum ada itu
        {
            // ambil topup campaign yang belum ada invoice nya
            $topupCampaignsFilter = array_filter($topupCampaigns, function ($item) use ($invoicesWithTopupCampaign_topupCampaignsIds) {
                return !in_array($item['id'], $invoicesWithTopupCampaign_topupCampaignsIds);
            });

            $invoicesBufferId = $invoicesWithTopupCampaign_ids;
            foreach($topupCampaignsFilter as $topup)
            {
                $cost_perlead = $topup['cost_perlead'];
                $total_leads = $topup['total_leads'];
                $invoices = LeadspeekInvoice::whereNotIn('id', $invoicesBufferId)
                    ->whereNull('topup_campaign_id')
                    ->whereNotIn('topup_campaign_id', $invoicesWithTopupCampaign_topupCampaignsIds)
                    ->where('leadspeek_api_id', $leadspeek_api_id)
                    ->where('invoice_type', 'campaign')
                    ->where('cost_leads', $cost_perlead)
                    ->where('total_leads', $total_leads)
                    ->orderBy('id', 'desc')
                    ->first()
                    ->toArray();
                if(!empty($invoices))
                {
                    $invoicesFinal[] = $invoices;
                    $invoicesBufferId[] = $invoices['id'];
                }
            }
        }
        /* GET INVOICES TOPUP CAMPAIGNS */
    }
    catch(\Throwable $e)
    {
        Log::error("Error In Function processRefundToClient = {$e->getMessage()}");
        return ['result' => 'error', 'message' => $e->getMessage()];
    }
}