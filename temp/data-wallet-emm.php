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