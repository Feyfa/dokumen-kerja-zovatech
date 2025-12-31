<?php

public function getListUserClient(string $usertype, string $search_keyword, Request $request)
{
    $openApiToken = $request->attributes->get('openApiToken');
    
    /* GET USER TYPE */
    $usertype = ($usertype === 'client') ? 'client' : 'userdownline';
    /* GET USER TYPE */

    /* CHECK DOMAIN AND SUBDOMAIN HAVE USER */
    $companyIDRoot = User::where('company_parent',null)
                            ->pluck('company_id')
                            ->toArray();
    
    $company = Company::where('id', $openApiToken->company_id)
                        ->first();
    $companyID = $company->id ?? "";
    
    $userType = 'client'; // awalnya saat create user itu create client
    if(in_array($companyID, $companyIDRoot))
        $userType = 'userdownline'; // namun ketika companyID ada di companyIDRoot, maka dia root atau admin root. maka dia hanya bisa create agency
    
    // ketika token ini hanya mempunyai kemampuan untuk create client, namun dia menggunakan url yang usertype agency example /openapi/create/user/agency. maka tidak diperbolehkan. dan sebaliknya
    if(trim($userType) !== trim($usertype))
        return response()->json([
            'status' => 'error', 
            'message' => 'Your Token Does Not Have The Ability To Get List ' . ($usertype === 'client' ? 'Client' : 'Agency'),
            'status_code' => 422
        ], 422);
    /* CHECK DOMAIN AND SUBDOMAIN HAVE USER */

    /* VALIDATOR */
    $rules = [];
    
    if($request->has('page')) 
        $rules['page'] = ['required','integer','min:1'];
    if($request->has('limit'))
        $rules['limit'] = ['required','integer','min:1'];

    if(count($rules) > 0)
    {
        $validator = Validator::make($request->all(), $rules);

        if($validator->fails())
        {
            $dtMsg = json_decode($validator->messages(), true);
            $tx_validator = $this->get_tx_validator($dtMsg);
            
            $resCode = 422;
            $resSts  = 'error';
            $resMsg  = $tx_validator;

            return response()->json([
                'status' => $resSts, 
                'message' => $resMsg,
                'status_code' => $resCode
            ], $resCode);
        }
    }
    /* VALIDATOR */

    /* GET LIST */
    $salt = substr(hash('sha256', env('APP_KEY')), 0, 16);
    $page = 1;
    $limit = 5;
    $search = "";
    $response = [];

    if($userType == 'client')
    {
        $users = User::select(
                        "users.id",
                        "users.email",
                        "users.name",
                        "companies.company_name",
                        "users.phonenum",
                        "users.phone_country_code",
                        "users.phone_country_calling_code",
                        DB::raw("DATE_FORMAT(users.created_at, '%m-%d-%Y') as create_at"),
                    )
                    ->leftJoin('companies','companies.id','=','users.company_id')
                    ->where('users.company_parent','=',$companyID)
                    ->where('users.active','=','T')
                    ->where('users.user_type','=','client');

        // if($request->has('search') && !empty($request->search) && trim($request->search) != '')
        // {
        //     $search = $request->search;
        if(!empty($search_keyword) && trim($search_keyword) != '') {
            $search = $search_keyword;
            $users = $users->where(function ($query) use ($salt, $search) {
                $query->where(DB::raw("CONVERT(AES_DECRYPT(FROM_bASE64(users.email), '" . $salt . "') USING utf8mb4)"),'LIKE',"%{$search}%")
                        ->orWhere(DB::raw("CONVERT(AES_DECRYPT(FROM_bASE64(users.name), '" . $salt . "') USING utf8mb4)"),'LIKE',"%{$search}%")
                        ->orWhere(DB::raw("CONVERT(AES_DECRYPT(FROM_bASE64(companies.company_name), '" . $salt . "') USING utf8mb4)"),'LIKE',"%{$search}%")
                        ->orWhere(DB::raw("DATE_FORMAT(users.created_at, '%m-%d-%Y')"),'LIKE',"%{$search}%");;
            });
        }

        $users = $users->orderBy('users.id', 'desc');

        if($request->has('page') || $request->has('limit')) // page or limit
        {
            if($request->has('page'))
                $page = (int) $request->page;
            if($request->has('limit'))
                $limit = (int) $request->limit;

            $users = $users->paginate($limit, ['*'], 'page', $page);

            $response['status'] = 'success';
            $response['message'] = '';
            $response['status_code'] = 200;
            $response['current_page'] = $users->currentPage();
            $response['last_page'] = $users->lastPage();
            $response['per_page'] = $users->perPage();
            $response['total'] = $users->total();
            $response['data'] = $users->items();
            
            return response()->json($response);
        }
        else // all
        {
            $users = $users->get();

            $response['status'] = 'success';
            $response['message'] = '';
            $response['status_code'] = 200;
            $response['current_page'] = null;
            $response['last_page'] = null;
            $response['per_page'] = null;
            $response['total'] = $users->count();
            $response['data'] = $users;

            return response($response);
        }
    }
    else if($userType == 'userdownline')
    {
        $users = User::select(
                    "users.id",
                    "users.email",
                    "users.name",
                    "companies.company_name",
                    "companies.subdomain",
                    "users.phonenum",
                    "users.phone_country_code",
                    "users.phone_country_calling_code",
                    DB::raw("DATE_FORMAT(users.created_at, '%m-%d-%Y') as create_at"),
                )
                ->leftJoin('companies','companies.id','=','users.company_id')
                ->where('users.company_parent','=',$companyID)
                ->where('users.active','=','T')
                ->where('users.user_type','=','userdownline');

        // if($request->has('search') && !empty($request->search) && trim($request->search) != '')
        // {
        //     $search = $request->search;
        if(!empty($search_keyword) && trim($search_keyword) != '') {
            $search = $search_keyword;
            $users = $users->where(function ($query) use ($salt, $search) {
                $query->where(DB::raw("CONVERT(AES_DECRYPT(FROM_bASE64(users.email), '" . $salt . "') USING utf8mb4)"),'LIKE',"%{$search}%")
                        ->orWhere(DB::raw("CONVERT(AES_DECRYPT(FROM_bASE64(users.name), '" . $salt . "') USING utf8mb4)"),'LIKE',"%{$search}%")
                        ->orWhere(DB::raw("CONVERT(AES_DECRYPT(FROM_bASE64(companies.company_name), '" . $salt . "') USING utf8mb4)"),'LIKE',"%{$search}%")
                        ->orWhere('companies.subdomain','LIKE',"%{$search}%")
                        ->orWhere(DB::raw("DATE_FORMAT(users.created_at, '%m-%d-%Y')"),'LIKE',"%{$search}%");;
            });
        }

        $users = $users->orderBy('users.id', 'desc');

        if($request->has('page') || $request->has('limit')) // page or limit
        {
            if($request->has('page'))
                $page = (int) $request->page;
            if($request->has('limit'))
                $limit = (int) $request->limit;

            $users = $users->paginate($limit, ['*'], 'page', $page);

            $response['status'] = 'success';
            $response['message'] = '';
            $response['status_code'] = 200;
            $response['current_page'] = $users->currentPage();
            $response['last_page'] = $users->lastPage();
            $response['per_page'] = $users->perPage();
            $response['total'] = $users->total();
            $response['data'] = $users->items();
            
            return response()->json($response);
        }
        else // all
        {
            $users = $users->get();

            $response['status'] = 'success';
            $response['message'] = '';
            $response['status_code'] = 200;
            $response['current_page'] = null;
            $response['last_page'] = null;
            $response['per_page'] = null;
            $response['total'] = $users->count();
            $response['data'] = $users;

            return response($response);
        }
    }
    /* GET LIST */
}