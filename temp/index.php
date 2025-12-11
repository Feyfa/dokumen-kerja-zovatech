public function processRefundToClient($leadspeek_api_id, $company_id, $company_root_id)
{
    try
    {
        // info('processRefundToClient 1.1', ['get_defined_vars' => get_defined_vars()]);

        /* AMBIL TOPUP CAMPAIGN YANG BELUM DONE */
        $topupCampaigns = Topup::where('leadspeek_api_id', $leadspeek_api_id)
            ->where('topup_status', '<>', 'done')
            ->orderBy('id', 'desc')
            ->get();
        $topupCampaigns_ids = $topupCampaigns->pluck('id')->toArray();
        $topupCampaigns_count = $topupCampaigns->count();
        info('processRefundToClient 1.2', ['topupCampaigns' => $topupCampaigns]);
        if($topupCampaigns_count == 0)
        {
            return ['result' => 'success', 'message' => 'refund to client successfully'];
        }
        /* AMBIL TOPUP CAMPAIGN YANG BELUM DONE */


        /* AMBIL INVOICE BY TOPUP CAMPAIGN ID */
        $invoicesWithTopupCampaign = LeadspeekInvoice::whereIn('topup_campaign_id', $topupCampaigns_ids)
            ->where('invoice_type', 'agency')
            ->where('leadspeek_api_id', $leadspeek_api_id)
            ->orderBy('id', 'desc')
            ->get();
        info('processRefundToClient 1.3', ['invoicesWithTopupCampaign' => $invoicesWithTopupCampaign]);
        $invoicesWithTopupCampaign_ids = $invoicesWithTopupCampaign->pluck('id')->toArray();
        $invoicesWithTopupCampaign_topupCampaignsIds = $invoicesWithTopupCampaign->pluck('topup_campaign_id')->toArray();
        $invoicesWithTopupCampaign_count = $invoicesWithTopupCampaign->count();
        /* AMBIL INVOICE BY TOPUP CAMPAIGN ID */


        $invoicesFinal = collect([]);
        info('processRefundToClient 1.4', ['invoicesWithTopupCampaign_count' => $invoicesWithTopupCampaign_count, 'topupCampaigns_count' => $topupCampaigns_count]);
        if($invoicesWithTopupCampaign_count < $topupCampaigns_count) // jika leadspeek_invoices sebagian ada topup_campaign_id, dan masih ada sebagian leadspeek_invoices yang belum ada topup_campaign_id, maka ambil yang sebagian yang belum ada itu
        {
            // ------
            // ambil list topup_campaigns yang belum ada, yang di leadspeek_invoices nya topup_campaign_id nya NULL
            $topupCampaignsFilterNull = $topupCampaigns->whereNotIn('id', $invoicesWithTopupCampaign_topupCampaignsIds)->values();
            // ambil list id invoice yang sudah ada topup_campaign_id nya
            $invoicesBufferId = $invoicesWithTopupCampaign_ids;
            info('processRefundToClient 2.1', ['topupCampaignsFilterNull' => $topupCampaignsFilterNull, 'invoicesBufferId' => $invoicesBufferId]);
            // ------


            // ------
            foreach($invoicesWithTopupCampaign as $invoice)
            {
                $topup_campaign_id = $invoice->topup_campaign_id;
                $cost_leads = $invoice->cost_leads;
                $total_leads = $invoice->total_leads;
                $invoicesFinal->push((object) [
                    'topup_campaign_id' => $topup_campaign_id,
                    'cost_perlead' => $cost_leads,
                    'total_leads' => $total_leads,
                    'customer_payment_id' => $invoice->customer_payment_id,
                ]);
            }
            // ------


            // ------insert ke invoices final------
            foreach($topupCampaignsFilterNull as $topup)
            {
                $invoices = LeadspeekInvoice::whereNotIn('id', $invoicesBufferId)
                    ->whereNull('topup_campaign_id')
                    ->where('invoice_type', 'campaign')
                    ->where('leadspeek_api_id', $leadspeek_api_id)
                    ->where('cost_leads', $topup->cost_perlead)
                    ->where('total_leads', $topup->total_leads)
                    ->orderBy('id', 'desc')
                    ->first();
                info('processRefundToClient 2.2', ['topup->cost_perlead' => $topup->cost_perlead, 'topup->total_leads' => $topup->total_leads, 'invoices' => $invoices]);
                if(!empty($invoices))
                {
                    $invoicesFinal->push((object) [
                        'topup_campaign_id' => $topup->id,
                        'cost_perlead' => $invoices->cost_leads,
                        'total_leads' => $invoices->total_leads,
                        'customer_payment_id' => $invoices->customer_payment_id,
                    ]);
                    $invoicesBufferId[] = $invoices->id;
                }
            }
            // ------insert ke invoices final------

            info('processRefundToClient 2.3', ['invoicesFinal' => $invoicesFinal]);
        }
        else // jika ternyata sama, maka query dengan cara leftJoin biasa
        {
            $invoicesFinal = Topup::select(
                    'topup_campaigns.id as topup_campaign_id',
                    'topup_campaigns.cost_perlead as cost_perlead',
                    'topup_campaigns.total_leads as total_leads',
                    'leadspeek_invoices.id as customer_payment_id',
                )
                ->leftJoin('leadspeek_invoices', 'leadspeek_invoices.topup_campaign_id', '=', 'topup_campaigns.id')
                ->where('topup_campaigns.leadspeek_api_id', $leadspeek_api_id)
                ->where('topup_campaigns.topup_status', '<>', 'done')
                ->orderBy('topup_campaigns.id', 'desc')
                ->get();
            // info('processRefundToClient 3.1', ['invoicesFinal' => $invoicesFinal]);
        }


        /* AMBIL CONNECTED ACCOUNT AGENCY */
        $accConID = $this->check_connected_account($company_id,$company_root_id);
        $stripepublish = $this->getcompanysetting($company_root_id,'rootstripe');
        $stripeseckey = (isset($stripepublish->secretkey) && !empty($stripepublish->secretkey)) ? $stripepublish->secretkey : config('services.stripe.secret');
        $stripe = new StripeClient(['api_key' => $stripeseckey, 'stripe_version' => '2020-08-27']);
        /* AMBIL CONNECTED ACCOUNT AGENCY */
    }
    catch(\Throwable $e)
    {
        Log::error("Error In Function processRefundToClient = {$e->getMessage()}");
        return ['result' => 'error', 'message' => $e->getMessage()];
    }
}