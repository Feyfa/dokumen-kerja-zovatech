<?php

$user = User::select(['users.*', 'companies.name as company_name'])
            ->join('companies', 'companies.id', '=', 'users.company_id')
            ->where('companies.name', 'LIKE', '%'.request('keyword').'%')
            ->orWhere('users.name', 'LIKE', '%'.request('keyword').'%')
            ->first();

var_dump(strlen("emaillloremipsumdolorsitametssaasdasdasjdnjkasndajksdnasjkdn10000@gmail.com"));

// $APP_KEY = "base64:mFZb5ZQi4VUXEZXv+GY8GSjA74zqfaBBaIV01KMAijY=";
// $key1 = md5($APP_KEY);
// $key2 = substr(hash('sha256', $APP_KEY), 0, 16);
// var_dump($key1);
// var_dump($key2);

// APP_KEY yang benar
// $key = "F6nf+ubRyo50AVJUOOPr/ZNqAT2Yfmk9BFGT/psAKS4=";
// $decoded = base64_decode($key);

// Hasil decode = 32 bytes binary data
// var_dump(strlen($decoded)); // int(32)
// var_dump(bin2hex($decoded)); // Hex representation (lebih readable)

// $animals = array('a' => 'dog', 'b' => 'cat', 'c' => 'cow');

// // Check if 'cat' exists as a value
// if (in_array('cat', $animals)) {
//     echo "Value 'cat' found.\n";
// }

// // Get the key for 'dog'
// $key = array_search('dog', $animals);
// if ($key !== false) {
//     echo "Value 'dog' found with key: " . $key . "\n"; // Output: a
// }

// $invoice_number_array = array_filter(["", "1234"]);
// $result = implode("-", $invoice_number_array);
// var_dump([
//     'result' => $result,
// ]);

// $parts = explode("|", "");
// var_dump($parts);

// $_leadspeek_api_id = "";
// $leadspeek_invoices_id_buffer = "123456";
// $leadspeek_api_id_data_wallet = implode("|", array_filter([$_leadspeek_api_id, $leadspeek_invoices_id_buffer]));
// var_dump($leadspeek_api_id_data_wallet);

// public function getTransactionHistoryDirectPayment(Request $request)
// {
//     $company_id = (isset($request->company_id)) ? $request->company_id : '';
//     $date = (isset($request->date)) ? $request->date : '';

//     /* VALIDATION AGENCY ONLY EMM */
//     $systemid = config('services.application.systemid');
//     $userExists = User::where('company_id','=',$company_id)
//                         ->where('company_parent','=',$systemid)
//                         ->where('active','=','T')
//                         ->where('user_type','=','userdownline')
//                         ->exists();
//     if(!$userExists)
//         return response()->json([
//             'result' => 'failed',
//             'message' => 'Agency Not Found'
//         ], 404);
//     /* VALIDATION AGENCY ONLY EMM */
//     /* VALIDATION AGENCY ONLY EMM */
//     $systemid = config('services.application.systemid');
//     $userExists = User::where('company_id','=',$company_id)
//                         ->where('company_parent','=',$systemid)
//                         ->where('active','=','T')
//                         ->where('user_type','=','userdownline')
//                         ->exists();
//     if(!$userExists)
//         return response()->json([
//             'result' => 'failed',
//             'message' => 'Agency Not Found'
//         ], 404);
//     /* VALIDATION AGENCY ONLY EMM */

//     $topupHistoryIds = TopupAgency::where('company_id','=',$company_id)
//                                 //   ->when(!empty($date) && trim($date) !== '', function ($query) use ($date) {
//                                 //         return $query->whereRaw("DATE_FORMAT(created_at, '%Y-%m') = ?", [$date]);
//                                 //   })
//                                     ->pluck('id')
//                                     ->toArray();
//     $topupHistoryIds = TopupAgency::where('company_id','=',$company_id)
//                                 //   ->when(!empty($date) && trim($date) !== '', function ($query) use ($date) {
//                                 //         return $query->whereRaw("DATE_FORMAT(created_at, '%Y-%m') = ?", [$date]);
//                                 //   })
//                                     ->pluck('id')
//                                     ->toArray();

//     $transactionHistory = LeadspeekInvoice::select(
//                                             'leadspeek_invoices.id','leadspeek_invoices.invoice_type','leadspeek_invoices.leadspeek_api_id','leadspeek_invoices.platform_total_amount',
//                                             'leadspeek_invoices.payment_term','leadspeek_invoices.payment_type','leadspeek_invoices.created_at','topup_agencies.expired_at',
//                                             'leadspeek_users.campaign_name','leadspeek_users.leadspeek_type',
//                                             'clean_id_file.id as upload_id','clean_id_file.file_name as file_name'
//                                             )
//                                             ->leftJoin('topup_agencies','topup_agencies.id','=','leadspeek_invoices.topup_agencies_id')
//                                             ->leftJoin('leadspeek_users','leadspeek_users.leadspeek_api_id','=','leadspeek_invoices.leadspeek_api_id')
//                                             ->leftJoin('clean_id_file','clean_id_file.id','=','leadspeek_invoices.clean_file_id')
//                                             ->where(function ($query) use ($topupHistoryIds, $company_id) {
//                                                 $query->where(function ($query) use ($topupHistoryIds) {
//                                                     $query->where('leadspeek_invoices.invoice_type','=','campaign')
//                                                             ->whereIn('leadspeek_invoices.topup_agencies_id',$topupHistoryIds);
//                                                 })
//                                                 ->orWhere(function ($query) use ($topupHistoryIds) {
//                                                     $query->where('leadspeek_invoices.invoice_type','=','agency')
//                                                             ->whereIn('leadspeek_invoices.topup_agencies_id',$topupHistoryIds);
//                                                 })
//                                                 ->orWhere(function ($query) use ($topupHistoryIds) { // ini terjadi jika charge clean id dengan harga berapapun, dan balance wallet nya ada
//                                                     $query->where('leadspeek_invoices.invoice_type','=','clean_id')
//                                                             ->whereIn('leadspeek_invoices.topup_agencies_id',$topupHistoryIds);
//                                                 })
//                                                 ->orWhere(function ($query) use ($company_id) { // ini terjadi jika charge clean id dengan harga 0, dan balance wallet nya 0
//                                                     $query->where('leadspeek_invoices.invoice_type','=','clean_id')
//                                                             ->whereNull('leadspeek_invoices.topup_agencies_id')
//                                                             ->where('leadspeek_invoices.company_id',$company_id);
//                                                 });
//                                             })
//                                             ->where('leadspeek_invoices.active','=','T')
//                                             ->when(!empty($date) && trim($date) !== '', function ($query) use ($date) {
//                                                 return $query->whereRaw("DATE_FORMAT(leadspeek_invoices.created_at, '%Y-%m') = ?", [$date]);
//                                             })
//                                             ->orderBy('id','desc')
//                                             ->get()
//                                             ->map(function ($item, $index) {
//                                             $payment_term = $item->payment_term;
//                                             $transaction_type = ($item->invoice_type == 'campaign' || $item->invoice_type == 'clean_id') ? 'charge' : 'topup';
//     $transactionHistory = LeadspeekInvoice::select(
//                                             'leadspeek_invoices.id','leadspeek_invoices.invoice_type','leadspeek_invoices.leadspeek_api_id','leadspeek_invoices.platform_total_amount',
//                                             'leadspeek_invoices.payment_term','leadspeek_invoices.payment_type','leadspeek_invoices.created_at','topup_agencies.expired_at',
//                                             'leadspeek_users.campaign_name','leadspeek_users.leadspeek_type',
//                                             'clean_id_file.id as upload_id','clean_id_file.file_name as file_name'
//                                             )
//                                             ->leftJoin('topup_agencies','topup_agencies.id','=','leadspeek_invoices.topup_agencies_id')
//                                             ->leftJoin('leadspeek_users','leadspeek_users.leadspeek_api_id','=','leadspeek_invoices.leadspeek_api_id')
//                                             ->leftJoin('clean_id_file','clean_id_file.id','=','leadspeek_invoices.clean_file_id')
//                                             ->where(function ($query) use ($topupHistoryIds, $company_id) {
//                                                 $query->where(function ($query) use ($topupHistoryIds) {
//                                                     $query->where('leadspeek_invoices.invoice_type','=','campaign')
//                                                             ->whereIn('leadspeek_invoices.topup_agencies_id',$topupHistoryIds);
//                                                 })
//                                                 ->orWhere(function ($query) use ($topupHistoryIds) {
//                                                     $query->where('leadspeek_invoices.invoice_type','=','agency')
//                                                             ->whereIn('leadspeek_invoices.topup_agencies_id',$topupHistoryIds);
//                                                 })
//                                                 ->orWhere(function ($query) use ($topupHistoryIds) { // ini terjadi jika charge clean id dengan harga berapapun, dan balance wallet nya ada
//                                                     $query->where('leadspeek_invoices.invoice_type','=','clean_id')
//                                                             ->whereIn('leadspeek_invoices.topup_agencies_id',$topupHistoryIds);
//                                                 })
//                                                 ->orWhere(function ($query) use ($company_id) { // ini terjadi jika charge clean id dengan harga 0, dan balance wallet nya 0
//                                                     $query->where('leadspeek_invoices.invoice_type','=','clean_id')
//                                                             ->whereNull('leadspeek_invoices.topup_agencies_id')
//                                                             ->where('leadspeek_invoices.company_id',$company_id);
//                                                 });
//                                             })
//                                             ->where('leadspeek_invoices.active','=','T')
//                                             ->when(!empty($date) && trim($date) !== '', function ($query) use ($date) {
//                                                 return $query->whereRaw("DATE_FORMAT(leadspeek_invoices.created_at, '%Y-%m') = ?", [$date]);
//                                             })
//                                             ->orderBy('id','desc')
//                                             ->get()
//                                             ->map(function ($item, $index) {
//                                             $payment_term = $item->payment_term;
//                                             $transaction_type = ($item->invoice_type == 'campaign' || $item->invoice_type == 'clean_id') ? 'charge' : 'topup';

//                                             $payment_type = 'Credit Card';
//                                             if($item->payment_type == 'bank_account') 
//                                                 $payment_type = 'Bank Account';
//                                             else if($item->payment_type == 'refund_campaign') 
//                                                 $payment_type = "Refund Campaign #{$item->leadspeek_api_id}";
//                                             else if($item->payment_type == 'minimum_spend')
//                                                 $payment_type = "Minimum Spend";
//                                             $payment_type = 'Credit Card';
//                                             if($item->payment_type == 'bank_account') 
//                                                 $payment_type = 'Bank Account';
//                                             else if($item->payment_type == 'refund_campaign') 
//                                                 $payment_type = "Refund Campaign #{$item->leadspeek_api_id}";
//                                             else if($item->payment_type == 'minimum_spend')
//                                                 $payment_type = "Minimum Spend";

//                                             $title = "Charge {$payment_term} Payment For Campaign #{$item->leadspeek_api_id} With Invoice #{$item->id}";
//                                             if($item->invoice_type == 'agency')
//                                             {
//                                                 if(empty($item->leadspeek_api_id)) // topup manual
//                                                 {
//                                                     $title = "Top Up Via {$payment_type}";
//                                                 }
//                                                 else // auto topup
//                                                 {
//                                                     $leadspeek_api_id_data_wallet_array = explode("|", $item->leadspeek_api_id);
//                                                     $title = "Auto Top Up Via {$payment_type}";
//                                                     if(!in_array($item->payment_type, ['refund_campaign','minimum_spend']))
//                                                     {
//                                                         if(!empty($leadspeek_api_id_data_wallet_array[0] ?? "")){ // leadspeek_api_id
//                                                             // check apakah leadspeek_api_id ini punya campaign atau clean id
//                                                             $isLeadspeekApiIdCampaign = LeadspeekUser::where('leadspeek_api_id',$leadspeek_api_id_data_wallet_array[0])->exists();
//                                                             $isLeadspeekApiIdCleanId = CleanIDFile::where('clean_api_id',$leadspeek_api_id_data_wallet_array[0])->exists();
//                                                             if($isLeadspeekApiIdCampaign){
//                                                                 $title .= " For Campaign #{$leadspeek_api_id_data_wallet_array[0]}";
//                                                             }elseif($isLeadspeekApiIdCleanId){
//                                                                 $title .= " For Clean ID #{$leadspeek_api_id_data_wallet_array[0]}";
//                                                             }
//                                                         }
//                                                         if(!empty($leadspeek_api_id_data_wallet_array[1] ?? "")){ // leadspeek_invoice_id
//                                                             $title .= " With Invoice #{$leadspeek_api_id_data_wallet_array[1]}";
//                                                         }
//                                                     }
//                                                 }
//                                             }
//                                             elseif($item->invoice_type == 'clean_id')
//                                             {
//                                                 $title = "Charge For Clean ID #{$item->leadspeek_api_id} With Invoice #{$item->id}";
//                                             }
//                                             $title = "Charge {$payment_term} Payment For Campaign #{$item->leadspeek_api_id} With Invoice #{$item->id}";
//                                             if($item->invoice_type == 'agency')
//                                             {
//                                                 if(empty($item->leadspeek_api_id)) // topup manual
//                                                 {
//                                                     $title = "Top Up Via {$payment_type}";
//                                                 }
//                                                 else // auto topup
//                                                 {
//                                                     $leadspeek_api_id_data_wallet_array = explode("|", $item->leadspeek_api_id);
//                                                     $title = "Auto Top Up Via {$payment_type}";
//                                                     if(!in_array($item->payment_type, ['refund_campaign','minimum_spend']))
//                                                     {
//                                                         if(!empty($leadspeek_api_id_data_wallet_array[0] ?? "")){ // leadspeek_api_id
//                                                             // check apakah leadspeek_api_id ini punya campaign atau clean id
//                                                             $isLeadspeekApiIdCampaign = LeadspeekUser::where('leadspeek_api_id',$leadspeek_api_id_data_wallet_array[0])->exists();
//                                                             $isLeadspeekApiIdCleanId = CleanIDFile::where('clean_api_id',$leadspeek_api_id_data_wallet_array[0])->exists();
//                                                             if($isLeadspeekApiIdCampaign){
//                                                                 $title .= " For Campaign #{$leadspeek_api_id_data_wallet_array[0]}";
//                                                             }elseif($isLeadspeekApiIdCleanId){
//                                                                 $title .= " For Clean ID #{$leadspeek_api_id_data_wallet_array[0]}";
//                                                             }
//                                                         }
//                                                         if(!empty($leadspeek_api_id_data_wallet_array[1] ?? "")){ // leadspeek_invoice_id
//                                                             $title .= " With Invoice #{$leadspeek_api_id_data_wallet_array[1]}";
//                                                         }
//                                                     }
//                                                 }
//                                             }
//                                             elseif($item->invoice_type == 'clean_id')
//                                             {
//                                                 $title = "Charge For Clean ID #{$item->leadspeek_api_id} With Invoice #{$item->id}";
//                                             }

//                                             $sub_title = '';
//                                             $campaign_name = Encrypter::decrypt($item->campaign_name ?? '');
//                                             $leadspeek_type = $item->leadspeek_type ?? '';
//                                             $leadspeek_type = ($leadspeek_type == 'b2b') ? 'B2B' : ucfirst($leadspeek_type);
//                                             if($item->invoice_type == 'campaign')
//                                             {
//                                                 $sub_title = "Campaign {$campaign_name} ({$leadspeek_type})";
//                                             }
//                                             $sub_title = '';
//                                             $campaign_name = Encrypter::decrypt($item->campaign_name ?? '');
//                                             $leadspeek_type = $item->leadspeek_type ?? '';
//                                             $leadspeek_type = ($leadspeek_type == 'b2b') ? 'B2B' : ucfirst($leadspeek_type);
//                                             if($item->invoice_type == 'campaign')
//                                             {
//                                                 $sub_title = "Campaign {$campaign_name} ({$leadspeek_type})";
//                                             }
                                            
//                                             $created_at = Carbon::parse($item->created_at)->format('Y F d H:i:s');
//                                             $expired_at = !empty($item->expired_at) ? Carbon::parse($item->expired_at)->format('Y F d H:i:s') : null;
//                                             $created_at = Carbon::parse($item->created_at)->format('Y F d H:i:s');
//                                             $expired_at = !empty($item->expired_at) ? Carbon::parse($item->expired_at)->format('Y F d H:i:s') : null;

//                                             return [
//                                                 'id' => $item->id,
//                                                 'transaction_type' => $transaction_type,
//                                                 'title' => $title,
//                                                 'sub_title' => $sub_title,
//                                                 'amount' => number_format($item->platform_total_amount ?? 0,2,'.',''),
//                                                 'created_at' => $created_at,
//                                                 'expired_at' => $expired_at, 
//                                             ];
//                                         });
//                                             return [
//                                                 'id' => $item->id,
//                                                 'transaction_type' => $transaction_type,
//                                                 'title' => $title,
//                                                 'sub_title' => $sub_title,
//                                                 'amount' => number_format($item->platform_total_amount ?? 0,2,'.',''),
//                                                 'created_at' => $created_at,
//                                                 'expired_at' => $expired_at, 
//                                             ];
//                                         });

//     // info(['topup_history_ids' => $topupHistoryIds,'transaction_history' => $transactionHistory]);
//     return response()->json([
//         'transaction_history' => $transactionHistory,
//     ]);
// }
//     // info(['topup_history_ids' => $topupHistoryIds,'transaction_history' => $transactionHistory]);
//     return response()->json([
//         'transaction_history' => $transactionHistory,
//     ]);
// }


// // payment term and transaction type
// $payment_term = $item->payment_term;
// $transaction_type = (in_array($item->invoice_type, ['campaign','clean_id','agency_subscription'])) ? 'charge' : 'topup';
// // payment term and transaction type
// // payment term and transaction type
// $payment_term = $item->payment_term;
// $transaction_type = (in_array($item->invoice_type, ['campaign','clean_id','agency_subscription'])) ? 'charge' : 'topup';
// // payment term and transaction type

// // payment type
// $payment_type = [
//     'credit_card' => 'Credit Card',
//     'bank_account' => 'Bank Account', 
//     'refund_campaign' => "Refund Campaign #{$item->leadspeek_api_id}",
//     'minimum_spend' => "Minimum Spend",
// ][$item->payment_type ?? 'credit_card'] ?? 'Credit Card';
// // payment type
// // payment type
// $payment_type = [
//     'credit_card' => 'Credit Card',
//     'bank_account' => 'Bank Account', 
//     'refund_campaign' => "Refund Campaign #{$item->leadspeek_api_id}",
//     'minimum_spend' => "Minimum Spend",
// ][$item->payment_type ?? 'credit_card'] ?? 'Credit Card';
// // payment type

// // title
// $title = "Charge {$payment_term} Payment For Campaign #{$item->leadspeek_api_id} With Invoice #{$item->id}";
// $subscription_type = ucfirst($item->subscription_type ?? "Initial");
// $subscription_details = json_decode($item->subscription_details ?? '{}', true) ?? [];
// if($item->invoice_type == 'agency'){
//     if(empty($item->leadspeek_api_id)){ // topup manual
//         $title = "Top Up Via {$payment_type}";
//     }elseif(!empty($subscription_details) && !empty($item->leadspeek_api_id)){ // auto topup agency karena subscription
//         $modules = [];
//         foreach ($subscription_details as $detail) {
//             if (!empty($detail['module'])) {
//                 $modules[] = ucfirst($detail['module']);
//             }
//         }
//         $moduleList = implode(',', $modules);
//         $invoiceId = $item->leadspeek_api_id ?? "";
//         $title = "Auto Top Up Via {$payment_type} For {$subscription_type} Subscription ({$moduleList}) With Invoice #{$invoiceId}";
//     }else{ // auto topup
//         $leadspeek_api_id_data_wallet_array = explode("|", $item->leadspeek_api_id);
//         $title = "Auto Top Up Via {$payment_type}";
// // title
// $title = "Charge {$payment_term} Payment For Campaign #{$item->leadspeek_api_id} With Invoice #{$item->id}";
// $subscription_type = ucfirst($item->subscription_type ?? "Initial");
// $subscription_details = json_decode($item->subscription_details ?? '{}', true) ?? [];
// if($item->invoice_type == 'agency'){
//     if(empty($item->leadspeek_api_id)){ // topup manual
//         $title = "Top Up Via {$payment_type}";
//     }elseif(!empty($subscription_details) && !empty($item->leadspeek_api_id)){ // auto topup agency karena subscription
//         $modules = [];
//         foreach ($subscription_details as $detail) {
//             if (!empty($detail['module'])) {
//                 $modules[] = ucfirst($detail['module']);
//             }
//         }
//         $moduleList = implode(',', $modules);
//         $invoiceId = $item->leadspeek_api_id ?? "";
//         $title = "Auto Top Up Via {$payment_type} For {$subscription_type} Subscription ({$moduleList}) With Invoice #{$invoiceId}";
//     }else{ // auto topup
//         $leadspeek_api_id_data_wallet_array = explode("|", $item->leadspeek_api_id);
//         $title = "Auto Top Up Via {$payment_type}";

//         if(in_array($item->payment_type, ['credit_card','bank_account'])){
//             $leadspeek_api_id_data_wallet_array_0 = $leadspeek_api_id_data_wallet_array[0] ?? "";
//             $leadspeek_api_id_data_wallet_array_1 = $leadspeek_api_id_data_wallet_array[1] ?? "";
//         if(in_array($item->payment_type, ['credit_card','bank_account'])){
//             $leadspeek_api_id_data_wallet_array_0 = $leadspeek_api_id_data_wallet_array[0] ?? "";
//             $leadspeek_api_id_data_wallet_array_1 = $leadspeek_api_id_data_wallet_array[1] ?? "";
            
//             if(!empty($leadspeek_api_id_data_wallet_array_0)){ // leadspeek_api_id
//                 // check apakah leadspeek_api_id ini punya campaign atau clean id
//                 $isLeadspeekApiIdCampaign = LeadspeekUser::where('leadspeek_api_id',$leadspeek_api_id_data_wallet_array_0)->exists();
//                 $isLeadspeekApiIdCleanId = CleanIDFile::where('clean_api_id',$leadspeek_api_id_data_wallet_array_0)->exists();
//                 if($isLeadspeekApiIdCampaign){
//                     $title .= " For Campaign #{$leadspeek_api_id_data_wallet_array_0}";
//                 }elseif($isLeadspeekApiIdCleanId){
//                     $title .= " For Clean ID #{$leadspeek_api_id_data_wallet_array_0}";
//                 }
//             }
//             if(!empty($leadspeek_api_id_data_wallet_array_0)){ // leadspeek_api_id
//                 // check apakah leadspeek_api_id ini punya campaign atau clean id
//                 $isLeadspeekApiIdCampaign = LeadspeekUser::where('leadspeek_api_id',$leadspeek_api_id_data_wallet_array_0)->exists();
//                 $isLeadspeekApiIdCleanId = CleanIDFile::where('clean_api_id',$leadspeek_api_id_data_wallet_array_0)->exists();
//                 if($isLeadspeekApiIdCampaign){
//                     $title .= " For Campaign #{$leadspeek_api_id_data_wallet_array_0}";
//                 }elseif($isLeadspeekApiIdCleanId){
//                     $title .= " For Clean ID #{$leadspeek_api_id_data_wallet_array_0}";
//                 }
//             }

//             if(!empty($leadspeek_api_id_data_wallet_array_1)){ // leadspeek_invoice_id
//                 $title .= " With Invoice #{$leadspeek_api_id_data_wallet_array_1}";
//             }
//         }
//     }
// }elseif($item->invoice_type == 'agency_subscription'){
//     $modules = [];
//     foreach ($subscription_details as $detail) {
//         if (!empty($detail['module'])) {
//             $modules[] = ucfirst($detail['module']);
//         }
//     }
//     $moduleList = implode(',', $modules);
//     $invoiceId = $item->id ?? '';
//     $title = "Charge Prepaid Payment For {$subscription_type} Subscription ({$moduleList}) With Invoice #{$invoiceId}";
// }elseif($item->invoice_type == 'clean_id'){
//     $title = "Charge For Clean ID #{$item->leadspeek_api_id} With Invoice #{$item->id}";
// }
// // title
//             if(!empty($leadspeek_api_id_data_wallet_array_1)){ // leadspeek_invoice_id
//                 $title .= " With Invoice #{$leadspeek_api_id_data_wallet_array_1}";
//             }
//         }
//     }
// }elseif($item->invoice_type == 'agency_subscription'){
//     $modules = [];
//     foreach ($subscription_details as $detail) {
//         if (!empty($detail['module'])) {
//             $modules[] = ucfirst($detail['module']);
//         }
//     }
//     $moduleList = implode(',', $modules);
//     $invoiceId = $item->id ?? '';
//     $title = "Charge Prepaid Payment For {$subscription_type} Subscription ({$moduleList}) With Invoice #{$invoiceId}";
// }elseif($item->invoice_type == 'clean_id'){
//     $title = "Charge For Clean ID #{$item->leadspeek_api_id} With Invoice #{$item->id}";
// }
// // title

// // sub title
// $sub_title = '';
// $campaign_name = Encrypter::decrypt($item->campaign_name ?? '');
// $leadspeek_type = $item->leadspeek_type ?? '';
// $leadspeek_type = ($leadspeek_type == 'b2b') ? 'B2B' : ucfirst($leadspeek_type);
// if($item->invoice_type == 'campaign'){
//     $sub_title = "Campaign {$campaign_name} ({$leadspeek_type})";
// }elseif($item->invoice_type == 'agency_subscription' && count($subscription_details) > 1){
//     // dari subscription_details, coba bikin string gini Predict ($100, 10 campaigns), Clean ($100, 10 campaigns), Audience ($100, 10 campaigns)
//     $subDetails = [];
//     foreach($subscription_details as $detail){
//         if(!empty($detail['module'])){
//             $module = $detail['module'] ?? "";
//             $price = $detail['price'] ?? 0;
//             $quota = $detail['quota'] ?? 0;
// // sub title
// $sub_title = '';
// $campaign_name = Encrypter::decrypt($item->campaign_name ?? '');
// $leadspeek_type = $item->leadspeek_type ?? '';
// $leadspeek_type = ($leadspeek_type == 'b2b') ? 'B2B' : ucfirst($leadspeek_type);
// if($item->invoice_type == 'campaign'){
//     $sub_title = "Campaign {$campaign_name} ({$leadspeek_type})";
// }elseif($item->invoice_type == 'agency_subscription' && count($subscription_details) > 1){
//     // dari subscription_details, coba bikin string gini Predict ($100, 10 campaigns), Clean ($100, 10 campaigns), Audience ($100, 10 campaigns)
//     $subDetails = [];
//     foreach($subscription_details as $detail){
//         if(!empty($detail['module'])){
//             $module = $detail['module'] ?? "";
//             $price = $detail['price'] ?? 0;
//             $quota = $detail['quota'] ?? 0;
            
//             $moduleName = ucfirst($module);
//             $quotaType = ($module == 'predict') ? 'campaigns' : 'contacts';
//             $subDetails[] = "{$moduleName} (\${$price}, {$quota} {$quotaType})";
//         }
//     }
//     $sub_title = implode(', ', $subDetails);
// }
// // sub title
//             $moduleName = ucfirst($module);
//             $quotaType = ($module == 'predict') ? 'campaigns' : 'contacts';
//             $subDetails[] = "{$moduleName} (\${$price}, {$quota} {$quotaType})";
//         }
//     }
//     $sub_title = implode(', ', $subDetails);
// }
// // sub title

// // return
// $created_at = Carbon::parse($item->created_at)->format('Y F d H:i:s');
// $expired_at = !empty($item->expired_at) ? Carbon::parse($item->expired_at)->format('Y F d H:i:s') : null;
// return [
//     'id' => $item->id,
//     'transaction_type' => $transaction_type,
//     'title' => $title,
//     'sub_title' => $sub_title,
//     'amount' => number_format($item->platform_total_amount ?? 0,2,'.',''),
//     'created_at' => $created_at,
//     'expired_at' => $expired_at,
// ];
// // return 
// // return
// $created_at = Carbon::parse($item->created_at)->format('Y F d H:i:s');
// $expired_at = !empty($item->expired_at) ? Carbon::parse($item->expired_at)->format('Y F d H:i:s') : null;
// return [
//     'id' => $item->id,
//     'transaction_type' => $transaction_type,
//     'title' => $title,
//     'sub_title' => $sub_title,
//     'amount' => number_format($item->platform_total_amount ?? 0,2,'.',''),
//     'created_at' => $created_at,
//     'expired_at' => $expired_at,
// ];
// // return 