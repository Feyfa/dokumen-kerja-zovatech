<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\CompanySale;
use App\Models\LeadspeekUser;
use App\Models\OpenApiToken;
use App\Models\OpenApiUser;
use App\Models\OpenApiLogs;
use App\Models\Role;
use App\Models\SsoAccessToken;
use App\Models\User;
use App\Models\BigDBMCountHistory;
use App\Models\campaignInformation;
use App\Models\IntegrationList;
use App\Models\IntegrationSettings;
use App\Models\State;
use App\Models\CompanySetting;
use App\Services\BigDBM;
use App\Services\OpenApi\OpenApiCampaignGetService;
use App\Services\OpenApi\OpenApiCampaignStatusService;
use App\Services\OpenApi\OpenApiCampaignUpdateService;
use App\Services\OpenApi\OpenApiContactsService;
use App\Services\OpenApi\OpenApiIntegrationService;
use App\Services\OpenApi\OpenApiLeadConnectorService;
use App\Services\OpenApi\OpenApiUserGetService;
use App\Services\OpenApi\OpenApiValidationService;
use App\Services\OpenApi\OpenApiWebhookService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use ESolution\DBEncryption\Encrypter;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use GuzzleHttp\Client;
use Illuminate\Validation\Rule;

Validator::extend('valid_email', function ($attribute, $value, $parameters, $validator) {
    return filter_var($value, FILTER_VALIDATE_EMAIL) && preg_match('/^[a-z0-9._%+-]+@[a-z0-9.-]+\.[a-z]{2,}$/i', $value);
});
Validator::extend('valid_name', function ($attribute, $value, $parameters, $validator) {
    $value = trim($value);
    return preg_match('/^(?=.*[a-zA-Z])[a-zA-Z0-9\s.,&\-\/\'()]+$/', $value); // validasi tidak boleh hanya angka, namun angka boleh dicampur dengan string
});

class OpenApiController extends Controller
{
    protected $configurationController;
    protected $userController;
    protected $leadspeekController;
    protected $bigdbmController;
    protected $toolController;
    protected $integrationController;
    protected $companyController;
    protected $generalController;
    protected $openApiValidationService;
    protected $openApiIntegrationService;
    protected $openApiWebhookService;
    protected $openApiContactsService;
    protected $openApiCampaignUpdateService;
    protected $openApiLeadConnectorService;
    protected $openApiCampaignGetService;
    protected $openApiCampaignStatusService;
    protected $openApiUserGetService;

    public function __construct(ConfigurationController $configurationController, UserController $userController, LeadspeekController $leadspeekController, BigDBMController $bigdbmController, ToolController $toolController, IntegrationController $integrationController, CompanyController $companyController, GeneralController $generalController, OpenApiValidationService $openApiValidationService, OpenApiIntegrationService $openApiIntegrationService, OpenApiWebhookService $openApiWebhookService, OpenApiContactsService $openApiContactsService, OpenApiCampaignUpdateService $openApiCampaignUpdateService, OpenApiLeadConnectorService $openApiLeadConnectorService, OpenApiCampaignGetService $openApiCampaignGetService, OpenApiCampaignStatusService $openApiCampaignStatusService, OpenApiUserGetService $openApiUserGetService)
    {
        $this->configurationController = $configurationController;
        $this->userController = $userController;
        $this->leadspeekController = $leadspeekController;
        $this->bigdbmController = $bigdbmController;
        $this->toolController = $toolController;
        $this->integrationController = $integrationController;
        $this->companyController = $companyController;
        $this->generalController = $generalController;
        $this->openApiValidationService = $openApiValidationService;
        $this->openApiIntegrationService = $openApiIntegrationService;
        $this->openApiWebhookService = $openApiWebhookService;
        $this->openApiContactsService = $openApiContactsService;
        $this->openApiCampaignUpdateService = $openApiCampaignUpdateService;
        $this->openApiLeadConnectorService = $openApiLeadConnectorService;
        $this->openApiCampaignGetService = $openApiCampaignGetService;
        $this->openApiCampaignStatusService = $openApiCampaignStatusService;
        $this->openApiUserGetService = $openApiUserGetService;
    }

    /**
     * Generator For Random Key 
     * usign for generate secret_key and token
     * @return string
     */
    private function generateRandomKey($min, $max)
    {
        // random min 60, max 80
        $randomInt = rand($min, $max);
        // akan menghasilkan karakter acak, 2 kali dari value $randomInt
        $secretKey = bin2hex(random_bytes($randomInt));

        return $secretKey;
    }

    /**
     * Create User For Api, Not User Emm
     * @return \Illuminate\Http\JsonResponse
     */
    public function getUserOpenApi(string $company_id)
    {
        /* VALIDATOR */
        $validator = Validator::make(
            [
                'company_id' => $company_id
            ],
            [
                'company_id' => ['required','filled','integer']
            ]
        );

        if($validator->fails())
        {
            $dtMsg = json_decode($validator->messages(), true);
            $tx_validator = $this->get_tx_validator($dtMsg);
            
            $resCode    = 422;
            $resSts     = 'error';
            $resMsg     = $tx_validator;

            return response()->json([
                'status' => $resSts, 
                'message' => $resMsg,
                'status_code' => $resCode
            ], $resCode);
        }
        /* VALIDATOR */

        /* VALIDATION is_marketing_services_agreement_developer */
        $userAgency = User::select('company_id','is_marketing_services_agreement_developer')
                          ->where('company_id', $company_id)
                          ->where('user_type', 'userdownline')
                          ->where('active', 'T')
                          ->first();

        $company_id = $userAgency->company_id ?? "";
        $is_marketing_services_agreement_developer = $userAgency->is_marketing_services_agreement_developer ?? "F";

        if($is_marketing_services_agreement_developer != 'T') 
        {
            return response()->json([
                'status' => 'error', 
                'message' => 'You Have Not Agreed to the Developer Agreement',
                'status_code' => 404
            ], 404);   
        }
        /* VALIDATION is_marketing_services_agreement_developer */

        /* VALIDATION ENABLE API_MODE */
        $dt_user = User::where('company_id',$company_id)
                       ->where('user_type','userdownline')
                       ->where('active','T')
                       ->where('api_mode','T')
                       ->first();

        if(!$dt_user) 
        {
            return response()->json([
                'status' => 'error', 
                'message' => 'Unauthorized.',
                'status_code' => 404
            ], 404);
        }
        /* VALIDATION ENABLE API_MODE */

        /* CHECK ONLY EMM */
        $confAppSysID = config('services.application.systemid');
        $company_root_id = $dt_user->company_root_id ?? "";

        if($company_root_id != $confAppSysID)
            return response()->json([
                'status' => 'error',
                'message' => 'You do not have access to this resource.',
                'status_code' => 401
            ], 401);
        /* CHECK ONLY EMM */

        /* GET USER OPEN API AND BASE URL */
        $base_url = env('APP_URL', '');
        $openApiUser = OpenApiUser::where('company_id', $company_id)
                                  ->first();

        return response()->json([
            'status' => 'success',
            'client_id' => $openApiUser->client_id ?? "",
            'secret_key' => $openApiUser->secret_key ?? "",
            'base_url' => $base_url
        ]);
        /* GET USER OPEN API AND BASE URL */
    }

    /**
     * Create User For Api, Not User Emm
     * @return \Illuminate\Http\JsonResponse
     */
    public function createUserOpenApi(Request $request)
    {
        /* VALIDATOR */
        $validator = Validator::make($request->all(), [
            'companyID' => ['required'],
            'email' => ['required','valid_email'],
            // 'password' => ['required'],
        ]);

        if($validator->fails())
        {
            $dtMsg = json_decode($validator->messages(), true);
            $tx_validator = $this->get_tx_validator($dtMsg);
            $tx_validator = str_replace("validation.valid_email", "The email must be a valid email address", $tx_validator);
            
            $resCode    = 422;
            $resSts     = 'error';
            $resMsg     = $tx_validator;

            return response()->json([
                'status' => $resSts, 
                'message' => $resMsg,
                'status_code' => $resCode
            ], $resCode);
        }
        /* VALIDATOR */

        /* CHECK PASSWORD AND USERTYPE ONLY USER AND USERDFOWNLINE */
        $salt = substr(hash('sha256', env('APP_KEY')), 0, 16);
        $user = User::select('users.id', 'users.company_id', 'users.user_type','users.is_marketing_services_agreement_developer','users.email', 'users.password', 'companies.subdomain' , 'users.isAdmin')
                    ->join('companies', 'companies.id', '=', 'users.company_id')
                    ->where('companies.id', $request->companyID)
                    ->whereIn('users.user_type', ['user', 'userdownline'])
                    ->where(DB::raw("CONVERT(AES_DECRYPT(FROM_bASE64(users.email), '" . $salt . "') USING utf8mb4)"), strtolower($request->email))
                    ->first();
        
        // $password = $user->password ?? "";
        $companyID = $user->company_id ?? "";
        $subdomain = $user->subdomain ?? "";
        $user_type = $user->user_type ?? "";
        $is_marketing_services_agreement_developer = $user->is_marketing_services_agreement_developer ?? "F";

        if(empty($user))
            return response()->json([
                'status' => 'error', 
                // 'message' => ['user' => ["User Not Found"]],
                'message' => 'User Not Found',
                'status_code' => 422
            ], 422);
        if(empty($companyID) || empty($subdomain))
            return response()->json([
                'status' => 'error', 
                // 'message' => ['user' => ["Subdomain Or Email Incorrect"]],
                'message' => 'User Not Found',
                'status_code' => 422
            ], 422);
        // if(empty($password))
        //     return response()->json([
        //         'status' => 'error', 
        //         // 'message' => ['user' => ["This user's password is empty"]],
        //         'message' => ['user' => ["Subdomain Or Email Or Password Incorrect"]],
        //         'status_code' => 422
        //     ], 422);
        // if(!Hash::check($request->password, $password))
        //     return response()->json([
        //         'status' => 'error', 
        //         // 'message' => ['user' => ["Password Incorrect"]],
        //         'message' => ['user' => ["Subdomain Or Email Or Password Incorrect"]],
        //         'status_code' => 422
        //     ], 422);

        if($user_type == 'user')
        {
            $is_marketing_services_agreement_developer = User::where('company_id', $companyID)
                                                             ->where('user_type', 'userdownline')
                                                             ->value('is_marketing_services_agreement_developer');
        }
        /* CHECK PASSWORD AND USERTYPE ONLY USER AND USERDFOWNLINE */

        /* VALIDATION ENABLE API_MODE */
        $dt_user = User::where('company_id',$companyID)
                       ->where('user_type','userdownline')
                       ->where('active','T')
                       ->where('api_mode','T')
                       ->first();

        if(!$dt_user) 
        {
            return response()->json([
                'status' => 'error', 
                'message' => 'Unauthorized.',
                'status_code' => 404
            ], 404);
        }
        /* VALIDATION ENABLE API_MODE */

        /* VALIDATION is_marketing_services_agreement_developer */
        if($is_marketing_services_agreement_developer != 'T')
        {
            return response()->json([
                'status' => 'error', 
                'message' => 'You Have Not Agreed to the Developer Agreement',
                'status_code' => 404
            ], 404);
        }
        /* VALIDATION is_marketing_services_agreement_developer */

        /* CHECK ONLY EMM */
        $confAppSysID = config('services.application.systemid');
        $company_root_id = $dt_user->company_root_id ?? "";

        if($company_root_id != $confAppSysID)
            return response()->json([
                'status' => 'error',
                'message' => 'You do not have access to this resource.',
                'status_code' => 401
            ], 401);
        /* CHECK ONLY EMM */

        /* CHECK DELETE OPEN API USER IF EXISTS */
        $openApiUserExists = OpenApiUser::where('company_id', $companyID)
                                        ->delete();
        /* CHECK DELETE OPEN API USER IF EXISTS */

        $clientID = "";
        $secretKey = "";

        /* GENERATE SECRET KEY FOR USER */
        while(true)
        {
            $clientID = $this->generateRandomKey(10, 20);
            $secretKey = $this->generateRandomKey(81, 90);


            $openApiUserExists = OpenApiUser::whereEncrypted('client_id', $secretKey)
                                            ->orWhereEncrypted('secret_key', $secretKey)
                                            ->exists();

            if(!$openApiUserExists) 
                break;
        }
        /* GENERATE SECRET KEY FOR USER */
    
        /* CREATE USER API */
        if(!empty($secretKey))
            $openApiUserCreate = OpenApiUser::create([
                'company_id' => $companyID,
                'client_id' => $clientID,
                'secret_key' => $secretKey
            ]);
        /* CREATE USER API */

        return response()->json([
            'status' => 'success',
            'user' => [
                'client_id' => $clientID,
                'secret_key' => $secretKey,
            ],
            'status_code' => 200
        ], 200);
    }

    private function get_tx_validator($dtMsg)
    {
        $result = [];
        foreach ($dtMsg as $key => $value) {
            // $result[] = $key . ":" . implode(" ", $value);
            $result[] = implode(" ", $value);
        }
        $finalString = implode(",", $result);
        $finalString = str_replace('.,', '; ', $finalString);
        return $finalString;
    }

    /**
     * Create Token For Open Api, Not Token Laraval passport
     * @return \Illuminate\Http\JsonResponse
     */
    public function createTokenOpenApi(Request $request)
    {
        $token = "";

        /* GET ATTRIBUTE */
        $openApiUsers = $request->attributes->get('openApiUsers');
        $client_id = $request->attributes->get('client_id');
        $secret_key = $request->attributes->get('secret_key');
        /* GET ATTRIBUTE */

        /* GENERATE SECRET KEY FOR USER */
        while(true)
        {
            $token = $this->generateRandomKey(91, 100);
            $tokenExists = OpenApiToken::whereEncrypted('token', $token)
                                       ->exists();

            if(!$tokenExists)
                break;
        }
        /* GENERATE SECRET KEY FOR USER */

        /* CREATE NEW TOKEN */
        $expired_at = Carbon::now()->addMinutes(30)->timestamp;  

        OpenApiToken::create([
            'company_id' => $openApiUsers->company_id,
            'user_api_id' => $openApiUsers->id,
            'token' => $token,
            'expired_at' => $expired_at
        ]);
        /* CREATE NEW TOKEN */

        return response()->json([
            'status' => 'success',
            'token' => $token,
            'expired_at' => $expired_at,
            'status_code' => 200
        ], 200);
    }

    /**
     * Create Client With Open Api
     * @return \Illuminate\Http\JsonResponse
     */
    public function createClientOpenApi(string $usertype, Request $request)
    {   
        if(!in_array($usertype, ['client', 'agency', 'sales', 'admin']))
            return response()->json([
                'status' => 'error', 
                'message' => "User Type Must Be Client Or Agency Or Admin Or Sales",
                'status_code' => 400
            ], 400);

        //// cek email sudah ada gak???
        // if($request->has('email') && $usertype != 'client') {
        //     $openApiToken = $request->attributes->get('openApiToken');
        //     $companyIDRoot = User::where('company_parent',null)
        //                        ->pluck('company_id')
        //                        ->toArray();

        //     $companyID = $openApiToken->company_id;

        //     if(in_array($companyID, $companyIDRoot)) {
        //         $company_root_id = $companyID;
        //     } else {
        //         $company_root_id = User::where('company_id', '=', $companyID)->first()->company_root_id;
        //     }
            
        //     $chkEmailExist = User::where('company_root_id',$company_root_id)
        //                 ->where('email',Encrypter::encrypt(strtolower($request->email)))
        //                 ->where('active','T')
        //                 ->get();

        //     if (count($chkEmailExist) > 0) {

        //         $resMsg = 'This email address is already associated with an existing account. Please log in or use a different email address.';
        //         $resSts = 'error';

        //         $this->inserLog($request, 400, $resSts, $resMsg, 'createClientOpenApi');

        //         return response()->json([
        //             'status' => $resSts , 
        //             'message' => $resMsg,
        //             'status_code' => 400
        //         ], 400);
                
        //     }
        // }

        if($usertype == 'agency' || $usertype == 'client')
        {
            return $this->createUserAgencyOrClient($usertype, $request);
        }
        else if($usertype == 'sales')
        {
            return $this->createUserSales($usertype, $request);
        }
        else if($usertype == 'admin')
        {
            return $this->createUserAdmin($usertype, $request);
        }
    }

    /**
     * create user agency or client
     * @return \Illuminate\Http\JsonResponse
     */
    private function createUserAgencyOrClient(string $usertype, Request $request)
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
        $domain = $company->domain ?? "";
        $subdomain = $company->subdomain ?? "";

        if(empty($company) || empty(trim($subdomain)))
            return response()->json([
                'status' => 'error', 
                'message' => ['subdomain_not_found' => ["Subdomain Not Found"]],
                'status_code' => 404
            ], 404);
        
        $userType = 'client'; // awalnya saat create user itu create client
        if(in_array($companyID, $companyIDRoot))
            $userType = 'userdownline'; // namun ketika companyID ada di companyIDRoot, maka dia root atau admin root. maka dia hanya bisa create agency
        
        // ketika token ini hanya mempunyai kemampuan untuk create client, namun dia menggunakan url yang usertype agency example /openapi/create/user/agency. maka tidak diperbolehkan. dan sebaliknya
        if(trim($userType) !== trim($usertype))
            return response()->json([
                'status' => 'error', 
                'message' => 'Your Token Only Has The Ability To Create ' . ($userType === 'client' ? 'Client' : 'Agency'),
                'status_code' => 422
            ], 422);
        /* CHECK DOMAIN AND SUBDOMAIN HAVE USER */

        /* VALIDATOR */
        $rules = [
            'email' => ['required', 'valid_email'],
            'full_name' => ['required', 'valid_name'],
            'company_name' => ['required', 'valid_name'],
            'is_send_email' => ['required']
        ];

        if(isset($request->password))
            $rules['password'] = ['required','min:6','string'];

        if($userType == 'userdownline')
        {
            $rules['subdomain'] = [
                'required',
                'valid_name',  // Hanya huruf dan angka
                'max:30' // maximal 30 karakter
            ];

            $rules['api_mode'] = ['in:T,F'];

            if($request->has('sales_sr_id'))
                $rules['sales_sr_id'] = ['required'];
            if($request->has('sales_ae_id'))
                $rules['sales_ae_id'] = ['required'];
            if($request->has('sales_ra_id'))
                $rules['sales_ra_id'] = ['required'];
        }
        elseif($userType == 'client')
        {
            if($request->has('disable_receive_email'))
                $rules['disable_receive_email'] = ['required','boolean'];
            if($request->has('disable_add_campaign'))
                $rules['disable_add_campaign'] = ['required','boolean'];
            if($request->has('enable_phone_number_on_campaign'))
                $rules['enable_phone_number_on_campaign'] = ['required','boolean'];
            if($request->has('enable_google_sheet_editor'))
                $rules['enable_google_sheet_editor'] = ['required','boolean'];
        }

        $messages = [
            'valid_name' => 'The attribute :attribute not valid',
        ];

        $validator = Validator::make($request->all(), $rules, $messages);

        if($validator->fails())
        {
            $dtMsg = json_decode($validator->messages(), true);
            $tx_validator = $this->get_tx_validator($dtMsg);
            $tx_validator = str_replace("validation.valid_email", "The email must be a valid email address", $tx_validator);
            
            $resCode    = 422;
            $resSts     = 'error';
            $resMsg     = $tx_validator;

            return response()->json([
                'status' => $resSts, 
                'message' => $resMsg,
                'status_code' => $resCode
            ], $resCode);
        }
        /* VALIDATOR */

        /* VALIDATION INTEGRATION IF USER_TYPE CLIENT AND USE INTEGRATION */
        if($userType == 'client' && $request->has('integrations'))
        {
            $integrationRequest = new Request(
                (is_array($request->integrations ?? null)) ?
                ($request->integrations) :
                ([])
            );
            $response = $this->openApiIntegrationService->validateRequestIntegrationClient($integrationRequest);
            if($response['status'] == 'error')
                return response()->json([
                    'status' => 'error',
                    'message' => $response['message'] ?? 'Something went wrong',
                    'status_code' => 422
                ], 422);
        }
        /* VALIDATION INTEGRATION IF USER_TYPE CLIENT AND USE INTEGRATION */

        // VALIDATION FOR List Product Client
        $newResult = [];
        if($userType == 'client' && $request->has('modules')) 
        {
            $modules = $request->modules;
            if(!is_array($modules) || (is_array($modules) && count($modules) == 0))
                return response()->json([
                    'status' => 'error',
                    'message' => 'The Field Module Not Valid Format',
                    'status_code' => 422
                ], 422);

            $getCompanyAgency = User::where('company_id', '=', $companyID)->get();
            $companyRootID = trim($getCompanyAgency[0]['company_root_id']);
            $root_modules = CompanySetting::where('company_id',$companyRootID)->whereEncrypted('setting_name','rootcustomsidebarleadmenu')->get();

            $keys = array();
            if (count($root_modules) > 0) {
                $root_modules = json_decode($root_modules[0]['setting_value']);
                $agencysidebar = $this->getcompanysetting($companyID, 'agencysidebar');
                if (!empty($agencysidebar) && isset($agencysidebar->SelectedModules)) {
                    foreach ($agencysidebar->SelectedModules as $key => $value) {
                        foreach ($root_modules as $key1 => $value1) {
                            if ($key1 == $value->type && $value->status == false) {
                                unset($root_modules->$key1);
                                // unset($agencysidebar->key);
                            }
                        }
                    }
                }
                $keys = array_keys((array) $root_modules);
            }

            foreach($modules as $key => $item) 
            {
                if (in_array($key, ['local', 'locator', 'enhance', 'b2b', 'simplifi'])) 
                {
                    //// cek ke agency module list ada gak? 
                    if (!in_array($key, $keys))
                        return response()->json([
                            'status' => 'error',
                            'message' => "The module '{$key}' is not defined in Enterprise.",
                            'status_code' => 422,
                        ], 422);

                    $validator = Validator::make(
                        ['flag' => $item],
                        ['flag' => ['required', 'string', 'in:T,F']]
                    );
                
                    if ($validator->fails())
                        return response()->json([
                            'status' => 'error',
                            'message' => "The attribute '{$key}' in modules is not a valid input",
                            'status_code' => 422,
                        ], 422);
                } 
                else 
                {
                    return response()->json([
                        'status' => 'error',
                        'message' => "Please input module in (local, locator, enhance or b2b or simplifi)",
                        'status_code' => 422,
                    ], 422);
                }
            }

            $agencySettings = $this->getcompanysetting($companyID, 'agencydefaultmodules');
            $agencydefault = isset($agencySettings->DefaultModules) ? $agencySettings->DefaultModules : null;
 
            if(empty($agencydefault)) // ketika ga ada agencydefaultmodules, default true semua
            {
                $agencydefault = [
                    (object)[
                        "type" => "local",
                        "status" => true
                    ],
                    (object)[
                        "type" => "locator",
                        "status" => true
                    ],
                    (object)[
                        "type" => "enhance",
                        "status" => true
                    ],
                    (object)[
                        "type" => "b2b",
                        "status" => true
                    ],
                    (object)[
                        "type" => "simplifi",
                        "status" => true
                    ]
                ];
            } 
            else 
            {
                // jika simplifi tidak ada di agencydefaultmodules, maka tambahkan menjadi true
                $agency_default_modules_simplifi_exists = !empty(array_filter($agencydefault, function ($item) {
                    return $item->type === 'simplifi';
                }));

                if(!$agency_default_modules_simplifi_exists)
                {
                    $agencydefault[] = (object) [
                        'type' => 'simplifi',
                        'status' => true
                    ];
                }
                // jika simplifi tidak ada di agencydefaultmodules, maka tambahkan menjadi true
            }

            foreach ($agencydefault as $item) 
            {
                $type = $item->type;
            
                // Cek apakah module ini ada dalam $modules dan ubah status-nya jika ada
                $newStatus = isset($modules[$type]) ? $modules[$type] === 'T' : $item->status;
            
                $newResult[] = (object) [
                    'type' => $type,
                    'status' => $newStatus
                ];
            }

        }
        // END VALIDATION List Product Client

        $companyID = $openApiToken->company_id;

        /* CHECK EMAIL CLIENT HAS BEN IN AGENCY AND USER */
        $emailInAgencyUserExists = null;

        if($userType == 'client')
        {
            $emailInAgencyUserExists = User::whereEncrypted('email', strtolower($request->email))
                                            ->whereIn('user_type', ['userdownline','user','client'])
                                            ->where(function ($query) use ($companyID) {
                                                $query->where('company_id', $companyID)
                                                      ->orWhere('company_parent', $companyID);
                                            })
                                            ->where('active', 'T')
                                            ->first();
        }
        else if($userType == 'userdownline')
        {
            $urlSubdomain = "{$request->subdomain}.$domain";
            $salt = substr(hash('sha256', env('APP_KEY')), 0, 16);
            $emailInAgencyUserExists = User::join('companies','companies.id','=','users.company_id')
                                           ->where('companies.subdomain', $urlSubdomain)
                                           ->where(DB::raw("CONVERT(AES_DECRYPT(FROM_bASE64(users.email), '" . $salt . "') USING utf8mb4)"), strtolower($request->email))
                                           ->where('active', 'T')
                                           ->first();
        }

        if($emailInAgencyUserExists)
        {
            $userTypeExists = $emailInAgencyUserExists->user_type ?? "";
            $roleLabels = ['userdownline' => 'as a agency', 'user' => 'as a admin agency ', 'client' => 'as a client', 'sales' => 'as a sales'][$userTypeExists] ?? '';
            
            $message = ($userType == 'client') ?
                       ("This email address is already registered on your platform $roleLabels. Please use a different email address. Thank you!") : 
                       ("This email address Or Subdomain is already registered on your platform $roleLabels. Please use a different email address Or Subdomain. Thank you!") ;
            return response()->json([
                'status' => 'error', 
                'message' => $message,
                'status_code' => 409
            ], 409);
        }
        /* CHECK EMAIL CLIENT HAS BEN IN AGENCY AND USER */
        
        /* GET ATTRIBUTES */
        $user = User::where('company_id', $companyID)
                    ->where('user_type', 'userdownline')
                    ->where('active', 'T')
                    ->first();
                    
        $idsys = $user->company_root_id;
        // $userType = "client";
        $ClientCompanyName = $request->company_name;
        $ClientFullName = $request->full_name;
        $ClientEmail = strtolower($request->email);
        $ClientPass = $request->password;
        // $ClientPass = Str::random(10);
        $isSendEmail = filter_var($request->input('is_send_email'), FILTER_VALIDATE_BOOLEAN);
        // $ClientPhone = "";
        // $ClientPhoneCountryCode = "US";
        // $ClientPhoneCountryCallingCode = "+1";
        $ClientPhone = $request->phone_number;
        $ClientPhoneCountryCode = $request->phone_country_code;
        $ClientPhoneCountryCallingCode = $request->phone_country_calling_code;

        $ClientDomain = "";
        $disabledreceivedemail = "F"; // disable_receive_email
        $disabledaddcampaign = "F"; // disable_add_campaign
        $enablephonenumber = "F"; // enable_phone_number_on_campaign
        $editorspreadsheet = "F"; // enable_google_sheet_editor
        if($userType == 'client') {
            $ClientDomain = "";
            $disabledreceivedemail = ($request->has('disable_receive_email') && $request->disable_receive_email === true) ? 'T' : 'F';
            $disabledaddcampaign = ($request->has('disable_add_campaign') && $request->disable_add_campaign === true) ? 'T' : 'F';
            $enablephonenumber = ($request->has('enable_phone_number_on_campaign') && $request->enable_phone_number_on_campaign === true) ? 'T' : 'F';
            $editorspreadsheet = ($request->has('enable_google_sheet_editor') && $request->enable_google_sheet_editor === true) ? 'T' : 'F';
        }
        if($userType == 'userdownline') {
            // $api_mode = $request->api_mode;
            // if($api_mode == 'T') {
            //     $apiMode = true;
            // } else {
            //     $apiMode = false;
            // }
            $apiMode = $request->api_mode === 'T' ? 'T' : 'F';
        }
        
        $selectedmodules = [
            [
                "type" => "local",
                "status" => true
            ],
            [
                "type" => "locator",
                "status" => true
            ],
            [
                "type" => "enhance",
                "status" => true
            ],
            [
                "type" => "b2b",
                "status" => true
            ],
            [
                "type" => "simplifi",
                "status" => true
            ]
        ];
        $inOpenApi = true;

        //ROOT OR AGENCY DEFAULT MODULES VALUE
        $getUserCurrentCompany = User::where('company_id', '=', $companyID)->get();

        $setting_name = 'agencydefaultmodules';
        if ($companyID == $getUserCurrentCompany[0]['company_root_id']) 
        {
            $setting_name = 'rootdefaultmodules';
        }
        
        $agencyDefaultModules_setting = $this->getcompanysetting($companyID,$setting_name);
        
        if (!empty($agencyDefaultModules_setting) && isset($agencyDefaultModules_setting->DefaultModules)) 
        {
            $selectedmodules = $agencyDefaultModules_setting->DefaultModules;

            // jika simplifi tidak ada di agencydefaultmodules, maka tambahkan menjadi true
            $agency_default_modules_simplifi_exists = !empty(array_filter($selectedmodules, function ($item) {
                return $item->type === 'simplifi';
            }));

            if(!$agency_default_modules_simplifi_exists)
            {
                $selectedmodules[] = (object) [
                    'type' => 'simplifi',
                    'status' => true
                ];
            }
            // jika simplifi tidak ada di agencydefaultmodules, maka tambahkan menjadi true
        }
        else 
        {
            $rootsidebar = $this->getcompanysetting($getUserCurrentCompany[0]['company_root_id'], 'rootcustomsidebarleadmenu');
            
            if(!empty($rootsidebar)) 
            {
                $selectedmodules = [];
                foreach($rootsidebar as $key => $value) 
                {
                        $selectedmodules[] = [
                            'type' => $key,
                            'status' => true
                        ];
                }
            }
        }
        //ROOT OR AGENCY DEFAULT MODULES VALUE

        $ClientWhiteLabeling = "";
        $DownlineDomain = "";
        $DownlineSubDomain = "";
        $DownlineOrganizationID = "";
        $salesRep = null;
        $salesAE = null;
        $salesRef = null;
        $twoFactorAuth = "";
        $selectedterms =  [];
        $planMinSpendId = null;
        if($userType == 'userdownline') 
        {
            // get domain have root
            $companyRoot = Company::select('domain')
                                 ->where('id', $idsys)
                                 ->first();

            $domainRoot = $companyRoot->domain ?? "";
            if(empty($domainRoot) || empty(trim($domainRoot)))
                return response()->json([
                    'status' => 'error', 
                    'message' => "Sorry Domain Root Not Found",
                    'status_code' => 404
                ], 404);
            // get domain have root

            $ClientWhiteLabeling = "T";
            $DownlineDomain = null;
            $DownlineSubDomain = $request->subdomain;
            $DownlineOrganizationID = null;
            $twoFactorAuth = "email";
            $selectedterms =  [
                [
                    "term" => "Weekly",
                    "status" => true
                ],
                [
                    "term" => "Monthly",
                    "status" => true
                ],
                [
                    "term" => "Prepaid",
                    "status" => true
                ]
            ];

            // validate payment term agency
            $rootPaymentTermsNewAgencies = $this->getcompanysetting($getUserCurrentCompany[0]['company_root_id'], 'rootPaymentTermsNewAgencies');
            
            if(!empty($rootPaymentTermsNewAgencies))
            {
                $rootPaymentTermsNewAgencies = json_decode(json_encode($rootPaymentTermsNewAgencies), true);
                $selectedterms = $rootPaymentTermsNewAgencies;
            }
            // validate payment term agency

            // validate attach sales to agency
            $salesList = User::select('id','name','status_acc')
                             ->where('user_type' ,'sales')
                             ->where('active' ,'T')
                             ->where('company_id', $idsys)
                             ->get();

            if(isset($request->sales_sr_id) && !empty($request->sales_sr_id) && trim($request->sales_sr_id) != '') // Sales Representative
            {
                $salesRepExists = false;
                foreach($salesList as $sales)
                {
                    if($sales->id == $request->sales_sr_id)
                    {
                        if($sales->status_acc != 'completed')
                            return response()->json([
                                'status' => 'error', 
                                'message' => "Sorry Sales Representative Has Not Completed Connect Stripe",
                                'status_code' => 404
                            ]);
                        
                        $salesRep = $request->sales_sr_id;
                        $salesRepExists = true;
                        break;
                    }
                }

                if(!$salesRepExists)
                    return response()->json([
                        'status' => 'error', 
                        'message' => "Sorry Sales Representative ID Not Found",
                        'status_code' => 404
                    ], 404);
            }
            if(isset($request->sales_ae_id) && !empty($request->sales_ae_id) && trim($request->sales_ae_id) != '') // Account Executive
            {
                $salesAeExists = false;
                foreach($salesList as $sales)
                {
                    if($sales->id == $request->sales_ae_id)
                    {
                        if($sales->status_acc != 'completed')
                            return response()->json([
                                'status' => 'error', 
                                'message' => "Sorry Sales Account Executive Has Not Completed Connect Stripe",
                                'status_code' => 404
                            ]);
                        
                        $salesAE = $request->sales_ae_id;
                        $salesAeExists = true;
                        break;
                    }
                }

                if(!$salesAeExists)
                    return response()->json([
                        'status' => 'error', 
                        'message' => "Sorry Sales Account Executive ID Not Found",
                        'status_code' => 404
                    ], 404);
            }
            if(isset($request->sales_ra_id) && !empty($request->sales_ra_id) && trim($request->sales_ra_id) != '') // Referral Account
            {
                $salesRefExists = false;
                foreach($salesList as $sales)
                {
                    if($sales->id == $request->sales_ra_id)
                    {
                        if($sales->status_acc != 'completed')
                            return response()->json([
                                'status' => 'error', 
                                'message' => "Sorry Sales Referral Account Has Not Completed Connect Stripe",
                                'status_code' => 404
                            ]);

                        $salesRef = $request->sales_ra_id;
                        $salesRefExists = true;
                        break;
                    }
                }

                if(!$salesRefExists)
                    return response()->json([
                        'status' => 'error', 
                        'message' => "Sorry Sales Referral Account ID Not Found",
                        'status_code' => 404
                    ], 404);
            }
            // validate attach sales to agency

            // get minimumspend default
            try 
            {
                $dataRequest = new Request(['page' => 'all']);
                $getMinimumSpendConfig = $this->generalController->getMinimumSpendConfig($dataRequest)->getData();
                $planMinSpendId = isset($getMinimumSpendConfig->minimumSpendListsDefault->id)?$getMinimumSpendConfig->minimumSpendListsDefault->id:null;
            }
            catch (\Throwable $e) 
            {
                Log::error(__FUNCTION__, ['error' => $e->getMessage()]);
            } 
            // get minimumspend default
        }

        if($userType == 'client') 
        {
            $data = new Request([
                'companyID' => $companyID,
                'idsys' => $idsys,
                'userType' => $userType,
                'ClientCompanyName' => $ClientCompanyName,
                'ClientFullName' => $ClientFullName,
                'ClientEmail' => $ClientEmail,
                'ClientPass' => $ClientPass,
                'ClientPhone' => $ClientPhone,
                'ClientPhoneCountryCode' => $ClientPhoneCountryCode,
                'ClientPhoneCountryCallingCode' => $ClientPhoneCountryCallingCode,
                'ClientDomain' => $ClientDomain,
                'disabledreceivedemail' => $disabledreceivedemail,
                'disabledaddcampaign' => $disabledaddcampaign,
                'enablephonenumber' => $enablephonenumber,
                'editorspreadsheet' => $editorspreadsheet,
                // 'selectedmodules' => $selectedmodules,
                'inOpenApi' => $inOpenApi,
                'isSendEmail' => $isSendEmail,
                // 'selectedmodules' => $newResult,
            ]);

            if (!empty($newResult)) 
            {
                $data['selectedmodules'] = $newResult;
            }
            else 
            {
                $data['selectedmodules'] = $selectedmodules;
            }
        }
        else 
        {
            $data = new Request([
                'companyID' => $companyID,
                'idsys' => $idsys,
                'userType' => $userType,
                'ClientCompanyName' => $ClientCompanyName,
                'ClientFullName' => $ClientFullName,
                'ClientEmail' => $ClientEmail,
                'ClientPass' => $ClientPass,
                'ClientPhone' => $ClientPhone,
                'ClientPhoneCountryCode' => $ClientPhoneCountryCode,
                'ClientPhoneCountryCallingCode' => $ClientPhoneCountryCallingCode,
                'ClientWhiteLabeling' => $ClientWhiteLabeling,
                'DownlineDomain' => $DownlineDomain,
                'DownlineSubDomain' => $DownlineSubDomain,
                'DownlineOrganizationID' => $DownlineOrganizationID,
                'salesRep' => $salesRep,
                'salesAE' => $salesAE,
                'salesRef' => $salesRef,
                'selectedterms' => $selectedterms,
                'selectedmodules' => $selectedmodules,
                'twoFactorAuth' => $twoFactorAuth,
                'inOpenApi' => $inOpenApi,
                'isSendEmail' => $isSendEmail,
                'ApiMode' => $apiMode,
                'planMinSpendId' => $planMinSpendId
            ]);
        }
        /* GET ATTRIBUTES */

        /* PROCESS CREATE CLIENT */
        $createClient = ""; 
        $createClientUserID = "";
        
        try 
        {
            $createClient = $this->configurationController->create($data)->getData();
            $createClientUserID = $createClient->data[0]->id ?? "";
        }
        catch(\Exception $e)
        {
            return response()->json([
                'status' => 'error', 
                'message' => $e->getMessage(),
                'status_code' => 500
            ], 500);
        }

        if($createClient->result == 'error' || $createClient->result == 'failed')
            return response()->json([
                'status' => 'error', 
                'message' => $createClient->message,
                'status_code' => 409
            ], 409);
        /* PROCESS CREATE CLIENT */

        /* PROCESS SAVE INTEGRATION CLIENT */
        $messageClient = 'Client Created Successfully';
        if($userType == 'client' && $request->has('integrations'))
        {
            $integrationRequest = new Request(
                (is_array($request->integrations ?? null)) ?
                ($request->integrations) :
                ([])
            );
            $clientCompanyID = $createClient->data[0]->company_id ?? "";
            $response = $this->openApiIntegrationService->saveIntegrationClient($clientCompanyID, $integrationRequest);
            // info('saveIntegrationClient', ['response' => $response]);
            $errorCount = $response['error_count'] ?? 0;
            if($errorCount > 0 && isset($response['message']))
                $messageClient .= ". However {$response['message']}";
        }
        /* PROCESS SAVE INTEGRATION  CLIENT*/

        return response()->json([
            'status' => 200,
            'user_id' => $createClientUserID,
            'message' => ($userType == 'client') ? "{$messageClient}" : 'Agency Created Successfully.',
            'status_code' => 200,
        ]);
    }

    /**
     * create user sales
     * @return \Illuminate\Http\JsonResponse
     */
    private function createUserSales(string $usertype, Request $request)
    {
        $openApiToken = $request->attributes->get('openApiToken');

        /* CHECK ABILITY TOKEN */
        $companyIDRoot = User::where('company_parent',null)
                               ->pluck('company_id')
                               ->toArray();
        
        $company = Company::where('id', $openApiToken->company_id)
                               ->first();

        $companyID = $company->id ?? "";
        
        if(!in_array($companyID, $companyIDRoot))
            return response()->json([
                'status' => 'error', 
                'message' => 'Your Token Only Has The Ability To Create Client',
                'status_code' => 422
            ], 422);
        /* CHECK ABILITY TOKEN */

        /* VALIDATOR */
        $rules = [
            'email' => ['required', 'valid_email'],
            'full_name' => ['required', 'valid_name'],
            'is_send_email' => ['required','in:0,1']
        ];

        if(isset($request->password))
            $rules['password'] = ['required','min:6','string'];

        $messages = [
            'valid_name' => 'The attribute :attribute not valid',
        ];

        $validator = Validator::make($request->all(), $rules, $messages);

        if($validator->fails())
        {
            $dtMsg = json_decode($validator->messages(), true);
            $tx_validator = $this->get_tx_validator($dtMsg);
            $tx_validator = str_replace("validation.valid_email", "The email must be a valid email address", $tx_validator);
            
            $resCode    = 422;
            $resSts     = 'error';
            $resMsg     = $tx_validator;

            return response()->json([
                'status' => $resSts, 
                'message' => $resMsg,
                'status_code' => $resCode
            ], $resCode);
        }
        /* VALIDATOR */

        /* CHECK EMAIL SALES HAS BEN IN ROOT, ADMIN ROOT, SALES */
        $emailSalesExists = User::whereEncrypted('email', strtolower($request->email))
                           ->where('company_id', $companyID)
                           ->where('active', 'T')
                           ->first();

        if($emailSalesExists) 
        {
            $userTypeExists = $emailSalesExists->user_type ?? "";
            $roleLabels = ['userdownline' => 'as a agency', 'user' => 'as a admin agency ', 'client' => 'as a client', 'sales' => 'as a sales'][$userTypeExists] ?? '';
            $message = "This email address is already registered on your platform $roleLabels. Please use a different email address. Thank you!";

            return response()->json([
                'status' => 'error',
                'message' => $message,
                'status_code' => 409
            ], 409);
        }
        /* CHECK EMAIL SALES HAS BEN IN ROOT, ADMIN ROOT, SALES */

        /* GET ATTRIBUTE */
        $userType = $usertype;
        $ClientCompanyName = null;
        $ClientFullName = $request->full_name;
        $ClientEmail = strtolower($request->email);
        $ClientPhone = $request->phone_number;
        $ClientPhoneCountryCode = $request->phone_country_code;
        $ClientPhoneCountryCallingCode = $request->phone_country_calling_code;
        $ClientPass = $request->password;
            
        $role = Role::where('company_id', $companyID)->first();
        $ClientRole = $role->id ?? "";
        
        $defaultAdmin = "F";
        $customercare = "F";
        $idsys = $companyID;
        $selectedmodules =  null;
        $inOpenApi = true;
        $isSendEmail = ($request->is_send_email == 1) ? true : false;

        $data = new Request([
            "companyID" => $companyID,
            "userType" => $userType,
            "ClientCompanyName" => $ClientCompanyName,
            "ClientFullName" => $ClientFullName,
            "ClientEmail" => $ClientEmail,
            "ClientPhone" => $ClientPhone,
            "ClientPhoneCountryCode" => $ClientPhoneCountryCode,
            "ClientPhoneCountryCallingCode" => $ClientPhoneCountryCallingCode,
            "ClientPass" => $ClientPass,
            "ClientRole" => $ClientRole,
            "defaultAdmin" => $defaultAdmin,
            "customercare" => $customercare,
            "idsys" => $idsys,
            "selectedmodules" => $selectedmodules,
            "inOpenApi" => $inOpenApi,
            'isSendEmail' => $isSendEmail
        ]);
        /* GET ATTRIBUTE */

        /* PROCESS CREATE SALES */
        $createClient = ""; 
        $createClientUserID = "";
        
        try
        {
            $createClient = $this->configurationController->create($data)->getData();
            $createClientUserID = $createClient->data[0]->id ?? "";
        }
        catch (\Exception $e)
        {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
                'status_code' => 500
            ], 500);
        }

        if($createClient->result == 'error' || $createClient->result == 'failed')
            return response()->json([
                'status' => 'error', 
                'message' => $createClient->message,
                'status_code' => 409
            ], 409);
        /* PROCESS CREATE SALES */

        return response()->json([
            'status' => 'success',
            'user_id' => $createClientUserID,
            'message' => 'Sales Created Successfully',
            'status_code' => 200,
        ], 200);
    }

    /**
     * update user sales
     * @return \Illuminate\Http\JsonResponse
     */
    private function updateUserSales(string $usertype, string $userid, Request $request)
    {
        $openApiToken = $request->attributes->get('openApiToken');

        /* CHECK ABILITY TOKEN */
        $companyIDRoot = User::where('company_parent',null)
                               ->pluck('company_id')
                               ->toArray();
        
        $company = Company::where('id', $openApiToken->company_id)
                               ->first();

        $companyID = $company->id ?? "";
        
        if(!in_array($companyID, $companyIDRoot))
            return response()->json([
                'status' => 'error', 
                'message' => 'Your Token Only Has The Ability To Update Client',
                'status_code' => 422
            ], 422);
        /* CHECK ABILITY TOKEN */

        /* VALIDATOR */
        $rules = [
            'email' => ['required', 'valid_email'],
            'full_name' => ['required', 'valid_name'],
            // 'is_send_email' => ['required','in:0,1']
        ];

        if(isset($request->password))
            $rules['password'] = ['required','min:6','string'];

        $messages = [
            'valid_name' => 'The attribute :attribute not valid',
        ];

        $validator = Validator::make($request->all(), $rules, $messages);

        if($validator->fails())
        {
            $dtMsg = json_decode($validator->messages(), true);
            $tx_validator = $this->get_tx_validator($dtMsg);
            $tx_validator = str_replace("validation.valid_email", "The email must be a valid email address", $tx_validator);
            
            $resCode    = 422;
            $resSts     = 'error';
            $resMsg     = $tx_validator;

            return response()->json([
                'status' => $resSts, 
                'message' => $resMsg,
                'status_code' => $resCode
            ], $resCode);
        }
        /* VALIDATOR */

        /* CHECK EMAIL SALES HAS BEN IN ROOT, ADMIN ROOT, SALES */
        $emailSalesExists = User::whereEncrypted('email', strtolower($request->email))
                           ->where('company_id', $companyID)
                           ->where('id','<>',$userid)
                           ->first();

        if($emailSalesExists) 
        {
            $userTypeExists = $emailSalesExists->user_type ?? "";
            $roleLabels = ['userdownline' => 'as a agency', 'user' => 'as a admin agency ', 'client' => 'as a client', 'sales' => 'as a sales'][$userTypeExists] ?? '';
            $message = "This email address is already registered on your platform $roleLabels. Please use a different email address. Thank you!";

            return response()->json([
                'status' => 'error',
                'message' => $message,
                'status_code' => 409
            ], 409);
        }
        /* CHECK EMAIL SALES HAS BEN IN ROOT, ADMIN ROOT, SALES */

        /* GET ATTRIBUTE */
        $userType = $usertype;
        $ClientCompanyName = null;
        $ClientFullName = $request->full_name;
        $ClientEmail = strtolower($request->email);
        $ClientPhone = $request->phone_number;
        $ClientPhoneCountryCode = $request->phone_country_code;
        $ClientPhoneCountryCallingCode = $request->phone_country_calling_code;
        $ClientPass = $request->password;
            
        $role = Role::where('company_id', $companyID)->first();
        $ClientRole = $role->id ?? "";
        
        $defaultAdmin = "F";
        $customercare = "F";
        $idsys = $companyID;
        $selectedmodules =  null;
        $inOpenApi = true;
        $isSendEmail = ($request->is_send_email == 1) ? true : false;

        $data = new Request([
            "ClientEmail" => $ClientEmail,
            "ClientFullName" => $ClientFullName,
            "ClientID" => $userid,
            "ClientPhone" => $ClientPhone,
            "ClientPhoneCountryCallingCode" => $ClientPhoneCountryCallingCode,
            "ClientPhoneCountryCode" => $ClientPhoneCountryCode,
            "ClientRole" => $ClientRole,
            "action" => 'administrator',
            "companyID" => $companyID,
            "customercare" => $customercare,
            "defaultAdmin" => $defaultAdmin,
            "idsys" => $idsys,
            "ClientPass" => $ClientPass,
            'inOpenApi' => $inOpenApi,
            // "userType" => $userType,
            // "ClientCompanyName" => $ClientCompanyName,
            // "selectedmodules" => $selectedmodules,
            // "inOpenApi" => $inOpenApi,
            // 'isSendEmail' => $isSendEmail
        ]);
        /* GET ATTRIBUTE */

        /* PROCESS CREATE SALES */
        $createClient = ""; 
        $createClientUserID = "";
        
        try
        {
            $createClient = $this->configurationController->update($data);
        }
        catch (\Exception $e)
        {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
                'status_code' => 500
            ], 500);
        }

        // if($createClient->result == 'error' || $createClient->result == 'failed')
        //     return response()->json([
        //         'status' => 'error', 
        //         'message' => ['create' => [$createClient->message]],
        //         'status_code' => 409
        //     ], 409);
        /* PROCESS CREATE SALES */

        return response()->json([
            'status' => 'success',
            'message' => 'Sales Updated Successfully',
            'status_code' => 200,
        ], 200);
    }

    /**
     * Login With SSO
     * @return \Illuminate\Http\JsonResponse
     */
    public function ssoLogin(string $usertype, Request $request)
    {
        $openApiToken = $request->attributes->get('openApiToken');
        $inGoHighLevel = $request->attributes->get('inGoHighLevel'); // ini hanya true jika login dari gohighlevel custom menu

        /* CHECK TYPE USER */
        if(!in_array($usertype, ['client','agency']))
            return response()->json([
                'status' => 'error', 
                'message' => "User Type Must Be Client Or Agency",
                'status_code' => 400
            ], 400);
        
        $usertype = ($usertype === 'client') ? 'client' : 'userdownline';
        /* CHECK TYPE USER */

        /* CHECK SUBDOMAIN  */ 
        $companyIDRoot = User::where('company_parent',null)
                             ->pluck('company_id')
                             ->toArray();

        $company = Company::where('id', $openApiToken->company_id)
                          ->first();
        
        if(empty($company))
            return response()->json([
                'status' => 'error', 
                'message' => ['subdomain' => ["Agency Subdomain Not Found"]],
                'status_code' => 404
            ], 404);

        $isSetupDomain = false;
        $domain = $company->domain ?? "";
        $statusDomain = $company->status_domain ?? "";
        $subdomain = $company->subdomain ?? "";
        $companyID = $openApiToken->company_id ?? "";

        if(empty($subdomain)) 
            return response()->json([
                'status' => 'error', 
                'message' => ['invalid_request' => ["Invalid Request. Subdomain Or Token Empty."]],
                'status_code' => 400
            ], 400);

        $userType = 'client'; // awalnya dia sebagai agency atau admin_agency. maka punya akses untuk login client
        if(in_array($companyID, $companyIDRoot))
            $userType = 'userdownline'; // namun ketika companyID ada di companyIDRoot, maka dia sebagai root atau admin root. maka punya akses untuk login agency

        // ketika token ini hanya mempunyai kemampuan untuk login client, namun dia menggunakan url yang usertype agency example /openapi/sso/login/agency. maka tidak diperbolehkan. dan sebaliknya
        if(trim($userType) !== trim($usertype))
            return response()->json([
                'status' => 'error', 
                'message' => 'Your Token Only Has The Ability To Login To ' . ($userType === 'client' ? 'Client' : 'Agency'),
                'status_code' => 422
            ], 422);
        /* CHECK SUBDOMAIN */

        /* VALIDATOR */
        $rules = [
            'email' => ['nullable', 'email'],
            'user_id' => ['nullable', 'integer'],
        ];

        if($userType == 'userdonwline') 
        {
            if($request->has('exclude_onboard_charge'))
                $rules['exclude_onboard_charge'] = ['required', Rule::in([true, false])];
            if($request->has('exclude_minimum_spend'))
                $rules['exclude_minimum_spend'] = ['required', Rule::in([true, false])];
        }

        $validator = Validator::make($request->all(), $rules);

        $validator->after(function ($validator) use ($request) {
            // filled digunakan untuk memeriksa apakah suatu field diisi dan memiliki nilai yang bukan null, kosong, atau hanya spasi
            // Jika kedua-duanya ada atau kedua-duanya kosong, maka tambahkan error
            if (($request->filled('email') && $request->filled('user_id')) || (!$request->filled('email') && !$request->filled('user_id'))) {
                $validator->errors()->add('credentials', 'You must provide either an email or user_id, not both or neither.');
            }
        });

        if($validator->fails())
        {
            $dtMsg = json_decode($validator->messages(), true);
            $tx_validator = $this->get_tx_validator($dtMsg);
            
            $resCode    = 422;
            $resSts     = 'error';
            $resMsg     = $tx_validator;

            return response()->json([
                'status' => $resSts, 
                'message' => $resMsg,
                'status_code' => $resCode
            ], $resCode);
        }
        /* VALIDATOR */

        /* CHECK EMAIL MATCH OR NOT */
        $salt = substr(hash('sha256', env('APP_KEY')), 0, 16);
        $user = User::select('users.*','companies.subdomain','companies.domain','companies.status_domain')
                    ->join('companies', 'companies.id', '=', 'users.company_id')
                    ->where('users.company_parent', $companyID)
                    ->where('users.active', 'T');

        if($userType == 'client')
            $user->where('users.user_type', $userType);
        else if($userType == 'userdownline')
            $user->whereIn('users.user_type', [$userType, 'user']);

        if($request->filled('email'))
            $user->where(DB::raw("CONVERT(AES_DECRYPT(FROM_bASE64(users.email), '" . $salt . "') USING utf8mb4)"), strtolower($request->email));
        else if($request->filled('user_id'))
            $user->where('users.id', $request->user_id);
        else 
            return response()->json([
                'status' => 'error',
                'message' => ['credentials' => ["You must provide either an email or user_id, not both or neither."]],
            ]);

        $user = $user->first();

        if(empty($user))
        {
            return response()->json([
                'status' => 'error',
                'message' => 'user not found',
                'status_code' => 404
            ], 404);
        }
        /* CHECK EMAIL MATCH OR NOT */

        /* OVERRIDE WHEN USER TYPE userdownline */
        if($userType == 'userdownline')
        {
            $domain = $user->domain ?? "";
            $statusDomain = $user->status_domain ?? "";
            $subdomain = $user->subdomain ?? "";
        }
        /* OVERRIDE WHEN USER TYPE userdownline */

        /* CHECK AGENCY HAS BEEN SETUP DOMAIN */
        // if(!empty($domain) && $statusDomain === 'ssl_acquired') // local
        if(!empty($domain) && $statusDomain === 'ssl_acquired' && gethostbyname(trim($domain)) == '157.230.213.72') // production
        {
            $isSetupDomain = true;
        }
        // info(['domain' => $domain, 'empty_domain' => !empty($domain), 'statusDomain' => $statusDomain, 'isSetupDomain' => $isSetupDomain]);
        /* CHECK AGENCY HAS BEEN SETUP DOMAIN */
        
        /* GENERATE ONE-TIME SSO ACCESS TOKEN , NOT TOKEN LARAVEL PASSPORT OR SANCTUM */
        $token = "";

        while(true)
        {
            $token = $this->generateRandomKey(101, 110);
            $tokenExists = SsoAccessToken::whereEncrypted('token', $token)
                                         ->exists();

            if(!$tokenExists) 
                break;
        }
        /* GENERATE ONE-TIME SSO ACCESS TOKEN , NOT TOKEN LARAVEL PASSPORT OR SANCTUM */

        /* CREATE URL */
        if(empty($token)) 
        {
            return response()->json([
                'status' => 'error', 
                'message' => "Invalid Request Token Empty",
                'status_code' => 400
            ], 400);
        }

        // $url = "http://$subdomain:8080/sso?token=$token"; // local
        $url = ($isSetupDomain === true) ? "https://$domain/sso?token=$token" : "https://$subdomain/sso?token=$token"; // production
        // $url2 = ($isSetupDomain === true) ? "https://$subdomain/sso?token=$token" : ""; // production
        /* CREATE URL */

        if($userType == 'userdownline')
        {
            /* DISABLE ENABLE FIRST CHARGE */
            try 
            {
                $dataRequest = [
                    'CompanyID' => $user->company_id,
                    'inOpenApi' => true // ini hanya ada di open api
                ];
                if($request->exclude_onboard_charge === true)
                {
                    $dataRequest['amount'] = 0;
                    $dataRequest['enable_coupon'] = false;
                }
                else 
                {
                    $dataRequest['amount'] = 497;
                    $dataRequest['enable_coupon'] = true;
                }
                $dataRequest = new Request($dataRequest);

                $response = $this->configurationController->set_onboarding_agency($dataRequest)->getData();
                $result = $response->result ?? "";
                $message = $response->message ?? "";
                if($result == 'error' || $result == 'failed')
                {
                    return response()->json([
                        'status' => 'error', 
                        'message' => $message,
                        'status_code' => 400
                    ], 400);
                }
            }
            catch (\Exception $e)
            {
                return response()->json([
                    'status' => 'error', 
                    'message' => $e->getMessage(),
                    'status_code' => 500
                ], 500);
            }
            /* DISABLE ENABLE FIRST CHARGE */

            /* DISABLE ENABLE MINSPEND */
            if($request->exclude_minimum_spend === true)
            {
                User::where('company_id', $user->company_id)
                    ->where('user_type', 'userdownline')
                    ->where('active', 'T')
                    ->update(['exclude_minimum_spend' => 1]);
            }
            else 
            {
                User::where('company_id', $user->company_id)
                    ->where('user_type', 'userdownline')
                    ->where('active', 'T')
                    ->update(['exclude_minimum_spend' => 0]);
            }
            /* DISABLE ENABLE MINSPEND */
        }

        /* GENERATE TOKEN */
        SsoAccessToken::create([
            'user_id' => $user->id,
            'token' => $token,
            'not_remove' => ($inGoHighLevel === true) ? 'T' : null
        ]);
        /* GENERATE TOKEN */

        return response()->json([
            'status' => 'success',
            'message' => 'Access Login With SSO Available. Please note that, This link can only be used once and will expire afterward.',
            'url' => $url,
            //'url2' => $url2,
            'status_code' => 200,
        ], 200);
    }

    public function ssoValidation(Request $request)
    {
        /* GET ATTRIBUTE */
        $ssoAccessToken = $request->attributes->get('ssoAccessToken');
        $subdomain = $request->attributes->get('subdomain');
        /* GET ATTRIBUTE */

        /* VALIDATION SUBDOMAIN */
        if(empty($subdomain))
            return response()->json([
                'status' => 'error', 
                'message' => ['subdomain' => ["Agency Subdomain Not Empty"]],
                'status_code' => 404
            ], 404);

        $user_id = $ssoAccessToken->user_id;

        $user = User::where('users.id', $user_id)
                    ->first();

        if(empty($user))
            return response()->json([
                'status' => 'error', 
                'message' => ['user_not_found' => ["User Not Found"]],
                'status_code' => 404
            ], 404);

        $userType = $user->user_type ?? "";
        $company = "";
        $subdomain_agency = "";
        $companyID = "";
        
        if($userType == 'client') 
            $companyID = $user->company_parent;
        else if($userType == 'userdownline' || $userType == 'user')
            $companyID = $user->company_id;
        else 
            return response()->json([
                'status' => 'error', 
                'message' => ['user_type_invalid' => ["Invalid Request. User Type Only Userdownline Or Client"]],
                'status_code' => 404
            ], 404);

        $company = Company::where('id', $companyID)
                          ->first();
        $subdomain_agency = $company->subdomain ?? "";
        $domain_agency = $company->domain ?? "";

        if(empty($subdomain) || empty($subdomain_agency))
            return response()->json([
                'status' => 'error', 
                'message' => ['subdomain_token_empty' => ["Subdomain Or Access Token Empty"]],
                'status_code' => 401
            ], 401);

        // info(['subdomain' => $subdomain, 'subdomain_agency' => $subdomain_agency, 'domain_agency' => $domain_agency]);
        $validSubdomain = (strtolower(trim($subdomain)) == strtolower(trim($subdomain_agency)) || strtolower(trim($subdomain)) == strtolower(trim($domain_agency)));
        if(!$validSubdomain)
            return response()->json([
                'status' => 'error', 
                'message' => ['token_invalid' => ["Invalid Token On This Domain"]],
                'status_code' => 401
            ], 401);
        /* VALIDATION SUBDOMAIN */

        /* LOGOUT USER IF PREV LOGIN */
        $action = "";
        $currentUserID = $request->currentUserID ?? null;
        if(!empty($currentUserID))
        {
            DB::table('oauth_access_tokens')
              ->where('user_id', $currentUserID)
              ->delete();
            
            $action = 'replace_user';
        }
        /* LOGOUT USER IF PREV LOGIN */

        /* GENERATE TOKEN PASSPORT */
        $user = User::find($user_id);

        $access_token = '';

        try
        {
            $access_token = $user->createToken('access_token')->accessToken;
        }
        catch(\Exception $e)
        {
            return response()->json([
                'status' => 'error', 
                'message' => $e->getMessage(),
                'status_code' => 500
            ], 500);
        }
        /* GENERATE TOKEN PASSPORT */

        /* DELETE SSO ACCESS TOKEN */
        $sso = SsoAccessToken::where('id', $ssoAccessToken->id)->first();
        if(!empty($sso) && $sso->not_remove != 'T')
        {
            $sso->delete();
        }
        /* DELETE SSO ACCESS TOKEN */

        return response()->json([
            'status' => 'success',
            'message' => 'Access Token Valid',
            'action' => $action,
            'access_token' => $access_token,
            'status_code' => 200,
        ], 200);
    }

    public function updateAgencyorClient(string $usertype, string $userid, Request $request) 
    {
        $openApiToken = $request->attributes->get('openApiToken');

        /* CHECK USER TYPE */
        if(!in_array($usertype, ['client','agency']))
            return response()->json([
                'status' => 'error', 
                'message' => "User Type Must Be Client Or Agency",
                'status_code' => 400
            ], 400);
    
        $usertype = ($usertype === 'client') ? 'client' : 'userdownline';
        /* CHECK USER TYPE */
        
        /* CHECK DOMAIN AND SUBDOMAIN HAVE USER */
        $companyIDRoot = User::where('company_parent',null)
                            ->pluck('company_id')
                            ->toArray();

        $company = Company::where('id', $openApiToken->company_id)
                        ->first();

        $companyID = $company->id ?? "";
        $domain = $company->domain ?? "";
        $subdomain = $company->subdomain ?? "";

        // return $subdomain;

        if(empty($company) || empty(trim($subdomain)))
            return response()->json([
                'status' => 'error', 
                'message' => "Subdomain Not Found",
                'status_code' => 404
            ], 404);

        $userType = 'client'; // awalnya saat create user itu create client
        if(in_array($companyID, $companyIDRoot))
            $userType = 'userdownline'; // namun ketika companyID ada di companyIDRoot, maka dia root atau admin root. maka dia hanya bisa create agency

        // ketika token ini hanya mempunyai kemampuan untuk create client, namun dia menggunakan url yang usertype agency example /openapi/create/user/agency. maka tidak diperbolehkan. dan sebaliknya
        if(trim($userType) !== trim($usertype))
            return response()->json([
                'status' => 'error', 
                'message' => 'Your Token Only Has The Ability To Update ' . ($userType === 'client' ? 'Client' : 'Agency'),
                'status_code' => 422
            ], 422);
        /* CHECK DOMAIN AND SUBDOMAIN HAVE USER */

        /* VALIDATOR */
        $attributes = [
            'userid' => $userid,
            'email' => $request->email ? strtolower($request->email) : null,
            'full_name' => $request->full_name ?? null,
            'company_name' => $request->company_name ?? null,
            'subdomain' => $request->subdomain ?? null,
            'disable_receive_email' => $request->disable_receive_email ?? null,
            'disable_add_campaign' => $request->disable_add_campaign ?? null,
            'enable_phone_number_on_campaign' => $request->enable_phone_number_on_campaign ?? null,
            'enable_google_sheet_editor' => $request->enable_google_sheet_editor ?? null,
            // 'api_mode' => $request->api_mode ?? null
        ];

        $rules = [
            'userid' => ['required', 'integer'],
            'email' => ['required', 'valid_email'],
            'full_name' => ['required', 'valid_name'],
            'company_name' => ['required', 'valid_name'],
        ];

        if($userType == 'userdownline')
        {
            $rules['subdomain'] = [
                'required',
                'regex:/^[a-zA-Z0-9]+$/',  // Hanya huruf dan angka
                'max:30' // maximal 30 karakter
            ];

            $rules['api_mode'] = ['in:T,F'];
        }
        elseif($userType == 'client')
        {
            if($request->has('disable_receive_email'))
                $rules['disable_receive_email'] = ['required','boolean'];
            if($request->has('disable_add_campaign'))
                $rules['disable_add_campaign'] = ['required','boolean'];
            if($request->has('enable_phone_number_on_campaign'))
                $rules['enable_phone_number_on_campaign'] = ['required','boolean'];
            if($request->has('enable_google_sheet_editor'))
                $rules['enable_google_sheet_editor'] = ['required','boolean'];
        }

        $messages = [
            'valid_name' => 'The attribute :attribute not valid',
        ];

        $validator = Validator::make($attributes, $rules, $messages);

        if($validator->fails())
        {
            $dtMsg = json_decode($validator->messages(), true);
            $tx_validator = $this->get_tx_validator($dtMsg);
            $tx_validator = str_replace("validation.valid_email", "The email must be a valid email address", $tx_validator);
            
            $resCode    = 422;
            $resSts     = 'error';
            $resMsg     = $tx_validator;

            return response()->json([
                'status' => $resSts, 
                'message' => $resMsg,
                'status_code' => $resCode
            ], $resCode);
        }
        /* VALIDATOR */

        /* VALIDATION INTEGRATION IF USER_TYPE CLIENT AND USE INTEGRATION */
        if($userType == 'client' && $request->has('integrations'))
        {
            $integrationRequest = new Request(
                (is_array($request->integrations ?? null)) ?
                ($request->integrations) :
                ([])
            );
            $response = $this->openApiIntegrationService->validateRequestIntegrationClient($integrationRequest);
            if($response['status'] == 'error')
                return response()->json([
                    'status' => 'error',
                    'message' => $response['message'] ?? 'Something went wrong',
                    'status_code' => 422
                ], 422);
        }
        /* VALIDATION INTEGRATION IF USER_TYPE CLIENT AND USE INTEGRATION */

        // VALIDATION FOR List Product Client
        $newResult = [];
        if($userType == 'client' && $request->has('modules')) {
            $modules = $request->modules;
            if(!is_array($modules) || (is_array($modules) && count($modules) == 0))
                return response()->json([
                    'status' => 'error',
                    'message' => 'The Field Module Not Valid Format',
                    'status_code' => 422
                ], 422);

            $getCompanyAgency = User::where('company_id', '=', $companyID)->get();
            $companyRootID = trim($getCompanyAgency[0]['company_root_id']);
            $root_modules = CompanySetting::where('company_id',$companyRootID)->whereEncrypted('setting_name','rootcustomsidebarleadmenu')->get();

            $keys = array();
            if (count($root_modules) > 0) {
                $root_modules = json_decode($root_modules[0]['setting_value']);
                $agencysidebar = $this->getcompanysetting($companyID, 'agencysidebar');
                if (!empty($agencysidebar) && isset($agencysidebar->SelectedModules)) {
                    foreach ($agencysidebar->SelectedModules as $key => $value) {
                        foreach ($root_modules as $key1 => $value1) {
                            if ($key1 == $value->type && $value->status == false) {
                                unset($root_modules->$key1);
                                unset($agencysidebar->key);
                            }
                        }
                    }
                }
                $keys = array_keys((array) $root_modules);
            }

            foreach($modules as $key => $item) {
                if (in_array($key, ['local', 'locator', 'enhance', 'b2b'])) {

                    //// cek ke agency module list ada gak? 
                    if (!in_array($key, $keys)) {
                        return response()->json([
                            'status' => 'error',
                            'message' => "The module '{$key}' is not defined in Enterprise.",
                            'status_code' => 422,
                        ], 422);
                    }

                    $validator = Validator::make(
                        ['flag' => $item],
                        ['flag' => ['required', 'string', 'in:T,F']]
                    );
                
                    if ($validator->fails())
                        return response()->json([
                            'status' => 'error',
                            'message' => "The attribute '{$key}' in modules is not a valid input",
                            'status_code' => 422,
                        ], 422);


                } else {
                    return response()->json([
                        'status' => 'error',
                        'message' => "Please input module in (local, locator, enhance or b2b)",
                        'status_code' => 422,
                    ], 422);
                }
            }

            $company_id_client = User::where('id', $userid)->where('user_type', 'client')->first()->company_id;
            $clientSetting = $this->getcompanysetting($company_id_client, 'clientsidebar');
            $clientdefault = isset($clientSetting->SelectedModules) ? $clientSetting->SelectedModules : null;

            if (empty($clientdefault)) {
                $clientdefault = [
                    (object)[
                        "type" => "local",
                        "status" => true
                    ],
                    (object)[
                        "type" => "locator",
                        "status" => true
                    ],
                    (object)[
                        "type" => "enhance",
                        "status" => true
                    ],
                    (object)[
                        "type" => "b2b",
                        "status" => true
                    ]
                ];
            }

            foreach ($clientdefault as $item) {
                $type = $item->type;
            
                // Cek apakah module ini ada dalam $modules dan ubah status-nya jika ada
                $newStatus = isset($modules[$type]) ? $modules[$type] === 'T' : $item->status;
            
                $newResult[] = (object)[
                    'type' => $type,
                    'status' => $newStatus
                ];
            }

        }
        // END VALIDATION List Product Client

        /* FIND EMAIL */
        $updateUser = "";
        $apiMode = false;
        $planMinSpendId = null;
        $inOpenApi = true;

        if($userType == 'client')
        {
            $updateUser = User::where('id', $userid)
                            ->where('company_parent', $companyID)
                            ->where('user_type', 'client')
                            ->first();
        }
        else if($userType == 'userdownline')
        {
            $updateUser = User::where('id', $userid)
                              ->whereIn('user_type', ['userdownline'])
                              ->first();

            if ($request->has('api_mode')) 
            {
                $apiMode = $request->api_mode;
            } 
            else 
            {
                $apiMode = $updateUser->api_mode;
            }

            $planMinSpendId = $updateUser->plan_minspend_id ?? null;
        }

        if(empty($updateUser))
            return response()->json([
                'status' => 'error',
                'message' => ($userType == 'client') ? 'Client Not Found' : 'Agency Not Found',
                'status_code' => '404'
            ], 404);
        /* FIND EMAIL */

        /* CHECK EMAIL ALREADY EXISTS */
        $emailExists = null;
        $subdomainExist = false;

        if($userType == 'client')
        {
            $emailExists = User::whereEncrypted('email', strtolower($request->email))
                                ->whereIn('user_type', ['userdownline','user','client'])
                                ->where('id','<>',$userid)
                                ->where('active','=','T')
                                ->where(function ($query) use ($companyID) {
                                    $query->where('company_id', $companyID)
                                          ->orWhere('company_parent', $companyID);
                                })
                                ->first();
        }
        else if($userType == 'userdownline') 
        {
            // CHECK COMPANY ID AGENCY IS COMPANY ID ROOT
            $companyAgencyID = $updateUser->company_id ?? "";

            if(in_array($companyAgencyID, $companyIDRoot))
                return response()->json([
                    'status' => 'error', 
                    'message' => 'Agency Not Found',
                    'status_code' => 404
            ], 404);
            // CHECK COMPANY ID AGENCY IS COMPANY ID ROOT

            // CHECK EMAIL ALREADY EXISTS IN THIS COMPANY ID
            $salt = substr(hash('sha256', env('APP_KEY')), 0, 16);
            $emailExists = User::where(DB::raw("CONVERT(AES_DECRYPT(FROM_bASE64(users.email), '" . $salt . "') USING utf8mb4)"), strtolower($request->email))
                                ->where('id','<>',$userid)
                                ->where('active','=','T')
                                ->where(function ($query) use ($companyAgencyID) {
                                    $query->where('company_id', $companyAgencyID)
                                          ->orWhere('company_parent', $companyAgencyID);
                                })
                                ->first();

            $urlSubdomain = "{$request->subdomain}.$domain";
            $subdomainExist = Company::where('companies.subdomain', $urlSubdomain)
                                     ->where('id','<>',$companyAgencyID)
                                     ->first();
            
            // CHECK EMAIL ALREADY EXISTS IN THIS COMPANY ID 
        }

        if($emailExists)
        { 
            $userTypeExists = $emailExists->user_type ?? "";
            $roleLabels = ['userdownline' => 'as a agency', 'user' => 'as a admin agency ', 'client' => 'as a client', 'sales' => 'as a sales'][$userTypeExists] ?? '';
            $message = "This email address is already registered on your platform $roleLabels. Please use a different email address. Thank you!";
            return response()->json([
                'status' => 'error', 
                'message' => $message,
                'status_code' => 409
            ], 409);
        }

        if($subdomainExist)
        { 
            return response()->json([
                'status' => 'error', 
                'message' => 'This Subdomain already registered, Please use another Subdomain Thank you!',
                'status_code' => 409
            ], 409);
        }
        /* CHECK EMAIL ALREADY EXISTS */

        /* VALIDATE ID SALES ATTACH TO AGENCY IF AGENCY */
        $salesRep = null; 
        $salesAE = null; 
        $salesRef = null;

        if($userType == 'userdownline')
        {
            $idsys = $updateUser->company_root_id ?? "";
            $salesList = User::select('id','name','status_acc')
                             ->where('user_type' ,'sales')
                             ->where('active' ,'T')
                             ->where('company_id', $idsys)
                             ->get();

            if($request->has('sales_sr_id')) // Sales Representative, jika parameter sales_sr_id diisi
            {
                if(!empty($request->sales_sr_id) && trim($request->sales_sr_id) != '') // jika bukan string kosong di cek id nya match atau tidak
                {
                    $salesSrExists = false;
                    foreach($salesList as $sales)
                    {
                        if($sales->id == $request->sales_sr_id)
                        {
                            if($sales->status_acc != 'completed')
                                return response()->json([
                                    'status' => 'error', 
                                    'message' => "Sorry Sales Representative Has Not Completed Connect Stripe",
                                    'status_code' => 404
                                ]);
    
                            $salesRep = $request->sales_sr_id;
                            $salesSrExists = true;
                            break;
                        }
                    }
    
                    if(!$salesSrExists)
                        return response()->json([
                            'status' => 'error', 
                            'message' => "Sorry Sales Representative ID Not Found",
                            'status_code' => 404
                        ], 404);
                }
            }
            else // jika parameter sales_sr_id tidak diisi, pakai sales_id yang sebelumnya diisi, kalau tidak ada null
            {
                $companySale = CompanySale::where('company_id', $updateUser->company_id)
                                          ->where('sales_title', 'Sales Representative')
                                          ->first();
                $salesRep = $companySale->sales_id ?? null;
            }

            if($request->has('sales_ae_id')) // Account Executive, jika parameter sales_ae_id diisi
            {
                if(!empty($request->sales_ae_id) && trim($request->sales_ae_id) != '') // jika bukan string kosong di cek id nya match atau tidak
                {
                    $salesAeExists = false;
                    foreach($salesList as $sales)
                    {
                        if($sales->id == $request->sales_ae_id)
                        {
                            if($sales->status_acc != 'completed')
                                return response()->json([
                                    'status' => 'error', 
                                    'message' => "Sorry Sales Account Executive Has Not Completed Connect Stripe",
                                    'status_code' => 404
                                ]);

                            $salesAE = $request->sales_ae_id;
                            $salesAeExists = true;
                            break;
                        }
                    }
    
                    if(!$salesAeExists)
                        return response()->json([
                            'status' => 'error', 
                            'message' => "Sorry Sales Account Executive ID Not Found",
                            'status_code' => 404
                        ], 404);
                }
            }
            else // jika parameter sales_ae_id tidak diisi, pakai sales_id yang sebelumnya diisi, kalau tidak ada null
            {
                $companySale = CompanySale::where('company_id', $updateUser->company_id)
                                          ->where('sales_title', 'Account Executive')
                                          ->first();
                $salesAE = $companySale->sales_id ?? null;
            }


            if($request->has('sales_ra_id')) // Referral Account, jika parameter sales_ra_id diisi
            {
                if(!empty($request->sales_ra_id) && trim($request->sales_ra_id) != '') // jika bukan string kosong di cek id nya match atau tidak
                {
                    $salesRaExists = false;
                    foreach($salesList as $sales)
                    {
                        if($sales->id == $request->sales_ra_id)
                        {
                            if($sales->status_acc != 'completed')
                                return response()->json([
                                    'status' => 'error', 
                                    'message' => "Sorry Sales Referral Account Has Not Completed Connect Stripe",
                                    'status_code' => 404
                                ]);

                            $salesRef = $request->sales_ra_id;
                            $salesRaExists = true;
                            break;
                        }
                    }
    
                    if(!$salesRaExists)
                        return response()->json([
                            'status' => 'error', 
                            'message' => "Sorry Sales Referral Account ID Not Found",
                            'status_code' => 404
                        ], 404);
                }
            }
            else // jika parameter sales_ra_id tidak diisi, pakai sales_id yang sebelumnya diisi, kalau tidak ada null
            {
                $companySale = CompanySale::where('company_id', $updateUser->company_id)
                                          ->where('sales_title', 'Account Referral')
                                          ->first();
                $salesRef = $companySale->sales_id ?? null;
            }
        }
        /* VALIDATE ID SALES ATTACH TO AGENCY IF AGENCY */

        /* ATTRIBUTE */
        $ClientPhone = ($request->has('phone_number')) ? $request->phone_number : $updateUser->phonenum;
        $ClientPhoneCountryCallingCode = ($request->has('phone_country_calling_code')) ? $request->phone_country_calling_code : $updateUser->phone_country_calling_code;
        $ClientPhoneCountryCode = ($request->has('phone_country_code')) ? $request->phone_country_code : $updateUser->phone_country_code;
        
        $disabledreceivedemail = "F"; // disable_receive_email
        $disabledaddcampaign = "F"; // disable_add_campaign
        $enablephonenumber = "F"; // enable_phone_number_on_campaign
        $editorspreadsheet = "F"; // enable_google_sheet_editor
        if($userType == 'client'){
            $disabledreceivedemail = ($updateUser->disabled_receive_email ?? '') == 'T' ? 'T' : 'F';
            if($request->has('disable_receive_email')){
                $disabledreceivedemail = $request->disable_receive_email === true ? 'T' : 'F';
            }

            $disabledaddcampaign = ($updateUser->disable_client_add_campaign ?? '') == 'T' ? 'T' : 'F';
            if($request->has('disable_add_campaign')){
                $disabledaddcampaign = $request->disable_add_campaign === true ? 'T' : 'F';
            }
            
            $enablephonenumber = ($updateUser->enabled_phone_number ?? '') == 'T' ? 'T' : 'F';
            if($request->has('enable_phone_number_on_campaign')){
                $enablephonenumber = ($request->enable_phone_number_on_campaign === true) ? 'T' : 'F';
            }

            $editorspreadsheet = ($updateUser->editor_spreadsheet ?? '') == 'T' ? 'T' : 'F';
            if($request->has('enable_google_sheet_editor')){
                $editorspreadsheet = ($request->enable_google_sheet_editor === true) ? 'T' : 'F';
            }
        }
        /* ATTRIBUTE */

        /* PROCESS UPDATE DATA */
        if($userType == 'userdownline'){
            $xdata = new Request([
                "ClientCompanyName" => $request->company_name,
                "ClientEmail" => strtolower($request->email),
                "ClientFullName" => $request->full_name,
                "ClientID" => $userid,
                "DownlineSubDomain" => $request->subdomain,
                "action" => 'downline',
                "companyID" => $updateUser->company_id,
                "ClientPass" => $request->password,
                "idsys" => $updateUser->company_root_id,
                "ClientPhone" => $ClientPhone,
                "ClientPhoneCountryCode" => $ClientPhoneCountryCode,
                "ClientPhoneCountryCallingCode" => $ClientPhoneCountryCallingCode,
                'ApiMode' => $apiMode,
                'inOpenApi' => $inOpenApi,
                'planMinSpendId' => $planMinSpendId,
                // "ClientPhone" => $ClientPhone,
                // "ClientPhoneCountryCallingCode" => $ClientPhoneCountryCallingCode,
                // "ClientPhoneCountryCode" => $ClientPhoneCountryCode,
    
            ]);
            /* GET ATTRIBUTE */

    
        } else {
            $xdata = new Request([
                "ClientCompanyName" => $request->company_name,
                "ClientEmail" => strtolower($request->email),
                "ClientFullName" => $request->full_name,
                "ClientID" => $userid,
                // "DownlineSubDomain" => $request->subdomain,
                "action" => 'client',
                "companyID" => $updateUser->company_id,
                "ClientPass" => $request->password,
                "idsys" => $updateUser->company_root_id,
                "ClientPhone" => $ClientPhone,
                "ClientPhoneCountryCode" => $ClientPhoneCountryCode,
                "ClientPhoneCountryCallingCode" => $ClientPhoneCountryCallingCode,
                'disabledreceivedemail' => $disabledreceivedemail,
                'disabledaddcampaign' => $disabledaddcampaign,
                'enablephonenumber' => $enablephonenumber,
                'editorspreadsheet' => $editorspreadsheet,
                'inOpenApi' => $inOpenApi,
                // "ClientPhone" => $ClientPhone,
                // "ClientPhoneCountryCallingCode" => $ClientPhoneCountryCallingCode,
                // "ClientPhoneCountryCode" => $ClientPhoneCountryCode,
            ]);
            if (!empty($newResult)) {
                $xdata['selectedmodules'] = $newResult;
            }
        }

        // Log::info(array("ClientCompanyName" => $request->company_name,
        //         "ClientEmail" => strtolower($request->email),
        //         "ClientFullName" => strtolower($request->email),
        //         "ClientID" => $userid,
        //         // "ClientPhone" => $ClientPhone,
        //         // "ClientPhoneCountryCallingCode" => $ClientPhoneCountryCallingCode,
        //         // "ClientPhoneCountryCode" => $ClientPhoneCountryCode,
        //         "DownlineSubDomain" => $request->subdomain,
        //         "action" => 'client',
        //         "companyID" => $updateUser->company_id,
        //         "ClientPass" => $request->password,
        //         "idsys" => $updateUser->company_root_id,));

        try
        {
            $updateClient = $this->configurationController->update($xdata);
            if($updateClient != null) 
            {
                $updateClient = $updateClient->getData();
                $result = $updateClient->result ?? '';
                if(in_array($result, ['failed', 'error']))
                {
                    $errorMessage = $updateClient->message ?? "";
                    return response()->json([
                        'status' => 'error', 
                        'message' => $errorMessage,
                        'status_code' => 409
                    ], 409);
                }
            }
        }
        catch (\Exception $e)
        {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
                'status_code' => 500
            ], 500);
        }

        // $updateUser->email = strtolower($request->email);
        // $updateUser->save();
        /* PROCESS UPDATE DATA */

        /* PROCESS UPDATE ATTACH SALES TO AGENCY */
        if($userType == 'userdownline' && ($request->has('sales_sr_id') || $request->has('sales_ae_id') || $request->has('sales_ra_id')))
        {
            $ydata = new Request([
                'companyID' => $updateUser->company_id,
                'salesRep' => $salesRep,
                'salesAE' => $salesAE,
                'salesRef' => $salesRef
            ]);

            try
            {
                $setSalesAgency = $this->configurationController->setSalesPerson($ydata);
            }
            catch (\Exception $e)
            {
                return response()->json([
                    'status' => 'error',
                    'message' => $e->getMessage(),
                    'status_code' => 500
                ], 500);
            }
        }
        /* PROCESS UPDATE ATTACH SALES TO AGENCY */

        /* PROCESS SAVE INTEGRATION CLIENT */
        $messageClient = 'Client Updated Successfully';
        if($userType == 'client' && $request->has('integrations'))
        {
            $integrationRequest = new Request(
                (is_array($request->integrations ?? null)) ?
                ($request->integrations) :
                ([])
            );
            $clientCompanyID = $updateUser->company_id;
            $response = $this->openApiIntegrationService->saveIntegrationClient($clientCompanyID, $integrationRequest);
            // info('saveIntegrationClient', ['response' => $response]);
            $errorCount = $response['error_count'] ?? 0;
            if($errorCount > 0 && isset($response['message']))
                $messageClient .= ". However {$response['message']}";
        }
        /* PROCESS SAVE INTEGRATION  CLIENT*/

        return response()->json([
            'status' => 200,
            'message' => ($userType == 'client') ? "{$messageClient}" : 'Agency Updated Successfully.',
            'status_code' => 200,
        ]);
    }

    /**
     * Update Client With Open Api
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateClientOpenApi(string $usertype, string $userid, Request $request)
    {
        if(!in_array($usertype, ['client','agency','sales','admin']))
            return response()->json([
                'status' => 'error', 
                'message' => "User Type Must Be Client Or Agency or Admin Or Sales",
                'status_code' => 400
            ], 400);

        //// cek email sudah ada gak???
        // if($request->has('email') && $usertype != 'client') {
        //     $openApiToken = $request->attributes->get('openApiToken');
        //     $companyIDRoot = User::where('company_parent',null)
        //                        ->pluck('company_id')
        //                        ->toArray();

        //     $companyID = $openApiToken->company_id;

        //     if(in_array($companyID, $companyIDRoot)) {
        //         $company_root_id = $companyID;
        //     } else {
        //         $company_root_id = User::where('company_id', '=', $companyID)->first()->company_root_id;
        //     }
            
        //     $chkEmailExist = User::where('company_root_id',$company_root_id)
        //                 ->where('email',Encrypter::encrypt(strtolower($request->email)))
        //                 ->where('active','T')
        //                 ->where('id', '<>', $userid)
        //                 ->get();

        //     if (count($chkEmailExist) > 0) {

        //         $resMsg = 'This email address is already associated with an existing account. Please log in or use a different email address.';
        //         $resSts = 'error';

        //         $this->inserLog($request, 400, $resSts, $resMsg, 'updateClientOpenApi');

        //         return response()->json([
        //             'status' => $resSts , 
        //             'message' => $resMsg,
        //             'status_code' => 400
        //         ], 400);
                
        //     }
        // }

        if($usertype == 'sales') 
        {
            return $this->updateUserSales($usertype, $userid, $request);
        } 
        else if($usertype == 'client' || $usertype == 'agency')
        {
            return $this->updateAgencyorClient($usertype, $userid, $request);
        }
        else if($usertype == 'admin')
        {
            return $this->updateAdmin($userid, $request);
        }
    }

    /**
     * Create Campaign
     * @return \Illuminate\Http\JsonResponse
     */
    public function createCampaignOpenApi(string $campaignType, Request $request)
    {
        /* GET ATTRIBUTE */
        $openApiToken = $request->attributes->get('openApiToken');
        /* GET ATTRIBUTE */

        $func = __FUNCTION__;
        $statecode_arr = [];
        $city_arr = [];
        $zip_arr = [];
        $zip_tx = '';
        $companyID = $openApiToken->company_id; // company id agency

        /* CHECK TYPE CAMPAIGN */
        // hilangkan locator, karena sudah tidak digunakan
        if(!in_array($campaignType, ['local','enhance','b2b'])) 
        {
            $resMsg = 'Campaign Type Must Be local Or Enhance Or B2B';
            $resSts = 'error';

            $this->inserLog($request, 400, $resSts, $resMsg, $func, $companyID);

            return response()->json([
                'status' => $resSts, 
                'message' => $resMsg,
                'status_code' => 400
            ], 400);
        }
        /* CHECK TYPE CAMPAIGN */

        /* VALIDATOR */
        $rules = [
            'campaign_name' => ['required'],
            'email' => ['nullable', 'valid_email'],
            'user_id' => ['nullable', 'integer'],
        ];

        if($request->has('enable_phone')) 
            $rules['enable_phone'] = ['required','in:T,F'];
        if($request->has('enable_home'))
            $rules['enable_home'] = ['required','in:T,F'];
        if($request->has('require_email')) 
            $rules['require_email'] = ['required','in:T,F'];
        if($request->has('reidentified_contact'))
            $rules['reidentified_contact'] = ['required','in:T,F'];
        if($request->has('userid_admin_gsheet')) 
        {
            $rules['userid_admin_gsheet'] = ['required','array'];
            $rules['userid_admin_gsheet.*'] = ['required','integer'];
        }
        if($request->has('reident_time_limit')) 
        {
            if(!in_array($request->reident_time_limit, ['1 year','6 months','3 months','1 month','1 week','never'])) 
            {
                $resMsg = 'Re-identification Time Limit must be 1 year, 6 months, 3 months, 1 month, 1 week, or never';
                $resSts = 'error';
                $this->inserLog($request, 400, $resSts, $resMsg, $func, $companyID);
                return response()->json([
                    'status' => $resSts, 
                    'message' => $resMsg,
                    'status_code' => 400
                ], 400);
            }
        }

        if($request->has('advanced_information') && in_array($campaignType, ['local', 'enhance', 'b2b']))
        {
            $rules['advanced_information'] = ['required', 'array'];
        }

        if($campaignType == 'local')
        {
            // $rules['url'] = ['required','array','max:5'];
            // $rules['url.*'] = ['required','max:255','active_url'];
            $rules['url'] = [
                'required',
                function ($attribute, $value, $fail) {
                    if (is_array($value)) {
                        // Jika array, cek maksimal 5 elemen
                        if (count($value) > 5) {
                            $fail("The $attribute must not have more than 5 items.");
                        }
                        // Cek setiap elemen dalam array harus URL yang valid
                        foreach ($value as $url) {
                            if (!filter_var($url, FILTER_VALIDATE_URL)) {
                                $fail("Each $attribute must be a valid URL.");
                            }
                        }
                    } elseif (!filter_var($value, FILTER_VALIDATE_URL)) {
                        // Jika bukan array, cek apakah itu valid URL
                        $fail("The $attribute must be a valid URL.");
                    }
                }
            ];

            if($request->has('installed_gtm'))
                $rules['installed_gtm'] = ['required','in:T,F'];

            if($request->has('exclude_state'))
                $rules['exclude_state'] = ['required','required_without:selected_state','array'];  
            if($request->has('selected_state')) 
                $rules['selected_state'] = ['required','required_without:exclude_state','array'];   
            
        }
        else if($campaignType == 'locator')
        {
            $rules['search_keyword'] = ['required'];
            $rules['context_keyword'] = ['required'];
            $rules['end_date_campaign'] = ['required'];

            if($request->has('national_targeting'))
                $rules['national_targeting'] = ['required','in:T'];
            if($request->has('state_targeting'))
                $rules['state_targeting'] = ['required','regex:/^[a-zA-Z0-9\s,]+$/'];
            if($request->has('zipcode_targeting'))
                $rules['zipcode_targeting'] = ['required','regex:/^([A-Za-z0-9\- ]{3,12})(,\s*[A-Za-z0-9\- ]{3,12})*$/'];
        }
        else if($campaignType == 'enhance' || $campaignType == 'b2b')
        {
            $rules['search_keyword'] = ['required'];

            if($request->has('national_targeting'))
                $rules['national_targeting'] = ['required','sometimes','required_without:state_targeting,zipcode_targeting','in:T'];
            if($request->has('state_targeting'))
                $rules['state_targeting'] = ['required','sometimes','required_without:national_targeting,zipcode_targeting','regex:/^[a-zA-Z0-9\s,]+$/'];
            if($request->has('city_targeting'))
                $rules['city_targeting'] = ['required','regex:/^[a-zA-Z0-9\s,]+$/'];
            if($request->has('zipcode_targeting'))
                $rules['zipcode_targeting'] = ['required','sometimes','required_without:national_targeting,state_targeting','regex:/^([A-Za-z0-9\- ]{3,12})(,\s*[A-Za-z0-9\- ]{3,12})*$/'];
        }
        
        $validator = Validator::make($request->all(), $rules);

        $validator->after(function ($validator) use ($request) {
            // filled digunakan untuk memeriksa apakah suatu field diisi dan memiliki nilai yang bukan null, kosong, atau hanya spasi
            // Jika kedua-duanya ada atau kedua-duanya kosong, maka tambahkan error
            if (($request->filled('email') && $request->filled('user_id')) || (!$request->filled('email') && !$request->filled('user_id'))) {
                $validator->errors()->add('credentials', 'You must provide either an email or user_id, not both or neither.');
            }
        });

        if($validator->fails())
        {
            $dtMsg = json_decode($validator->messages(), true);
            $tx_validator = $this->get_tx_validator($dtMsg);
            $tx_validator = str_replace("validation.valid_email", "The email must be a valid email address", $tx_validator);
            
            $resCode    = 422;
            $resSts     = 'error';
            $resMsg     = $tx_validator;

            $this->inserLog($request, $resCode, $resSts, $resMsg, $func, $companyID);

            return response()->json([
                'status' => $resSts, 
                'message' => $resMsg,
                'status_code' => $resCode
            ], $resCode);
        }

        // check permission agency sidebar
        $agencySidebar = $this->getcompanysetting($companyID, 'agencysidebar');
        $selectedModules = $agencySidebar->SelectedModules ?? "";

        if(is_array($selectedModules) && count($selectedModules) > 0)
        {
            $filterAgencySidebar = array_filter($selectedModules, function ($item) use ($campaignType) {
                return strtolower($item->type) == $campaignType;
            });

            $type = array_values($filterAgencySidebar)[0]->type ?? "";
            $status = array_values($filterAgencySidebar)[0]->status ?? false;

            // Log::info('', ['filterAgencySidebar' => $filterAgencySidebar,'type' => $type,'status' => $status, ]);

            if($status === false)
            {
                $resCode    = 422;
                $resSts     = 'error';
                $resMsg     = "Create Campaign In Modules $type Is Not Permitted";
                $this->inserLog($request, $resCode, $resSts, $resMsg, $func, $companyID);
                return response()->json(['status' => $resSts, 'message' => $resMsg,'status_code' => $resCode], $resCode);
            }
        }
        // check permission agency sidebar

        if($campaignType == 'local')
        {
            /* VALIDATOR URLS */
            $urlCode = $request->url;
            $domains = [];
            if (is_array($urlCode)) {
                foreach ($urlCode as $url) {
                    if (!preg_match('/^https?:\/\//', $url)) {
                        $url = 'https://' . $url; // default https
                    }
                    $host = parse_url($url, PHP_URL_HOST);
                    $mainDomain = preg_replace('/^www\./', '', $host);
                    if (in_array($mainDomain, $domains)) {
                        $resCode    = 422;
                        $resSts     = 'error';
                        $resMsg     = 'Duplicate url. Please check your Urls input.';

                        $this->inserLog($request, $resCode, $resSts, $resMsg, $func, $companyID);
                        
                        return response()->json([
                            'status' => $resSts, 
                            'message' => $resMsg,
                            'status_code' => $resCode
                        ], $resCode);
                    }
                    $domains[] = $mainDomain;
                }
            } 
            /* END VALIDATOR URLS */
        }

        if($campaignType == 'enhance' || $campaignType == 'b2b')
        {
            if(!$request->has('national_targeting') && !$request->has('state_targeting') && !$request->has('city_targeting') && !$request->has('zipcode_targeting'))
                $request->merge(['national_targeting' => 'T']);
            $national_targeting = $request->national_targeting;
            $state_targeting    = $request->state_targeting;
            $city_targeting     = $request->city_targeting;
            $zipcode_targeting  = $request->zipcode_targeting;

            $filledCount = 0;
            if (!empty($national_targeting)) $filledCount++;
            if (!empty($state_targeting)) $filledCount++;
            if (!empty($zipcode_targeting)) $filledCount++;

            // Cek apakah tidak ada atau lebih dari satu yang diisi
            if ($filledCount === 0) {
                $resCode    = 422;
                $resSts     = 'error';
                $resMsg     = 'Please add target location (national targeting or state or zipcode)';

                $this->inserLog($request, $resCode, $resSts, $resMsg, $func, $companyID);
                
                return response()->json([
                    'status' => $resSts, 
                    'message' => $resMsg,
                    'status_code' => $resCode
                ], $resCode);
            }

            if ($filledCount > 1) {
                $resCode    = 422;
                $resSts     = 'error';
                $resMsg     = 'Make sure only one choice target location (national targeting or state or zipcode)';

                $this->inserLog($request, $resCode, $resSts, $resMsg, $func, $companyID);
                
                return response()->json([
                    'status' => $resSts, 
                    'message' => $resMsg,
                    'status_code' => $resCode
                ], $resCode);
            }
            // Cek apakah tidak ada atau lebih dari satu yang diisi

            // validation zipcode apakah kurang dari 1 atau lebih dari 50
            if (!empty($zipcode_targeting)) {
                $zip_arr = explode(',', $zipcode_targeting); 
                $zip_arr = array_map('trim', $zip_arr);
                $zip_arr = array_unique($zip_arr); 
                $zip_arr = array_filter($zip_arr); 
                $zip_tx = implode("\n", $zip_arr);

                if (count($zip_arr) < 1 || count($zip_arr) > 50) {
                    $resCode    = 422;
                    $resSts     = 'error';
                    $resMsg     = 'Zipcode targeting min 1 and max 50 unique zip codes, duplicates are not allowed';
    
                    $this->inserLog($request, $resCode, $resSts, $resMsg, $func, $companyID);
    
                    return response()->json([
                        'status' => $resSts, 
                        'message' => $resMsg,
                        'status_code' => $resCode
                    ], $resCode);
                }
            }
            // validation zipcode apakah kurang dari 1 atau lebih dari 50
            
            //// untuk states ambil kode states nya saja (dari table states)
            $states_arr = $state_targeting ? array_map('trim', explode(",", $state_targeting)) : [];
            $cities_arr = $city_targeting ? array_map('trim', explode(",", $city_targeting)) : [];
            foreach ($states_arr as $st) {
                $statecode = DB::select("select state_code from bigdbm_locations where state_name = ?", [$st]);
                if($statecode) {
                    array_push($statecode_arr, $statecode[0]->state_code);
                }
                if(empty($statecode_arr)) {
                    $this->inserLog($request, 409, 'error', 'Please enter at least one valid state', $func, $companyID);
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Please enter at least one valid state.',
                        'status_code' => 409
                    ], 409);
                }
            } 
            if (!empty($cities_arr) && !empty($statecode_arr)) {
                $st = $statecode_arr[0];
                foreach ($cities_arr as $ct) {
                    $cities = DB::select("select city from bigdbm_locations where city = ? and state_code = ? ", [$ct,$st]);
                    if($cities) {
                        array_push($city_arr, $cities[0]->city);
                    }
                } 
                if(empty($city_arr)) {
                    $this->inserLog($request, 409, 'error', 'Please enter at least one valid city', $func, $companyID);
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Please enter at least one valid city.',
                        'status_code' => 409
                    ], 409);
                }
            }
            //// untuk states ambil kode states nya saja (dari table states)

            // validation state, city, zipcode
            // log::info('bbbbb',$cities_arr);
            if (count($statecode_arr) > count(array_unique($statecode_arr))) { // jika terdapat state yang duplicate
                $this->inserLog($request, 409, 'error', 'States are duplicated', $func, $companyID);
                return response()->json([
                    'status' => 'error',
                    'message' => 'States are duplicated.',
                    'status_code' => 409
                ], 409);
            }
            if ( count($statecode_arr) > 1 && $city_targeting && !empty($cities_arr) ) { // jika pilih banyak state dan pilih banyak city
                $this->inserLog($request, 509, 'error', 'You can input cities only if exactly one state', $func, $companyID);
                return response()->json([
                    'status' => 'error',
                    'message' => 'You can input cities only if exactly one state.',
                    'status_code' => 509
                ], 509);
            }
            if (count($city_arr) > count(array_unique($city_arr))) { // jika terdapat state yang duplicate
                $this->inserLog($request, 409, 'error', 'Cities are duplicated', $func, $companyID);
                return response()->json([
                    'status' => 'error',
                    'message' => 'Cities are duplicated.',
                    'status_code' => 409
                ], 409);
            }
            if ( count($statecode_arr) > 5) { // jika state lebih dari 5
                $this->inserLog($request, 509, 'error', 'You can input max 5 States', $func, $companyID);
                return response()->json([
                    'status' => 'error',
                    'message' => 'You can input max 5 States.',
                    'status_code' => 509
                ], 509);
            }
            if ( count($city_arr) > 10) { // jika city lebih dari 10
                $this->inserLog($request, 509, 'error', 'You can input max 10 Cities', $func, $companyID);
                return response()->json([
                    'status' => 'error',
                    'message' => 'You can input max 10 Cities.',
                    'status_code' => 509
                ], 509);
            }
            if (!empty($cities_arr) && empty($city_arr)) { // jika pilih city, namun city nya tidak ada di db
                $this->inserLog($request, 509, 'error', 'Your selected cities are not in the specified state', $func, $companyID);
                return response()->json([
                    'status' => 'error',
                    'message' => 'Your selected cities are not in the specified state.',
                    'status_code' => 509
                ], 509);
            }
            // validation state, city, zipcode
        }

        if($campaignType == 'locator')
        {
            /* VALIDATION TARGETING LOCATION */
            if(!$request->has('national_targeting') && !$request->has('state_targeting') && !$request->has('city_targeting') && !$request->has('zipcode_targeting'))
                $request->merge(['national_targeting' => 'T']);
            $national_targeting = $request->national_targeting;
            $state_targeting    = $request->state_targeting;
            $zipcode_targeting  = $request->zipcode_targeting;
            
            $filledCount = 0;
            if (!empty($national_targeting)) $filledCount++;
            if (!empty($state_targeting)) $filledCount++;
            if (!empty($zipcode_targeting)) $filledCount++;

            // Cek apakah tidak ada atau lebih dari satu yang diisi
            if ($filledCount === 0) {
                $resCode    = 422;
                $resSts     = 'error';
                $resMsg     = 'Please add target location (national targeting or state or zipcode)';

                $this->inserLog($request, $resCode, $resSts, $resMsg, $func, $companyID);
                
                return response()->json([
                    'status' => $resSts, 
                    'message' => $resMsg,
                    'status_code' => $resCode
                ], $resCode);
            }

            if ($national_targeting == 'T' && (!empty($state_targeting) || !empty($zipcode_targeting))) {
                $resCode    = 422;
                $resSts     = 'error';
                $resMsg     = 'When use national targeting, you cannot also use state or zip code targeting';

                $this->inserLog($request, $resCode, $resSts, $resMsg, $func, $companyID);

                return response()->json([
                    'status' => $resSts, 
                    'message' => $resMsg,
                    'status_code' => $resCode
                ], $resCode);
            }

            if (!empty($zipcode_targeting)) {
                $zip_arr = explode(',', $zipcode_targeting); 
                $zip_arr = array_map('trim', $zip_arr);
                $zip_arr = array_unique($zip_arr); 
                $zip_arr = array_filter($zip_arr); 
                $zip_tx = implode("\n", $zip_arr);

                if (count($zip_arr) < 15) {
                    $resCode    = 422;
                    $resSts     = 'error';
                    $resMsg     = 'Zipcode targeting must contain at least 15 unique zip codes, duplicates are not allowed';
    
                    $this->inserLog($request, $resCode, $resSts, $resMsg, $func, $companyID);
    
                    return response()->json([
                        'status' => $resSts, 
                        'message' => $resMsg,
                        'status_code' => $resCode
                    ], $resCode);
                }
            }

            // $state_targeting = $request->state_targeting ?? [];
            // if ($state_targeting) {
            //     $state_targeting = State::select(DB::raw("CONCAT(sifi_state_id, '|', state_code) as state_target"))->whereIn('state', $state_targeting)
            //                     ->pluck('state_target')
            //                     ->toArray();
            // }

            if (!empty($state_targeting)) {
                $states_arr = array_map('trim', explode(",", $state_targeting));
            
                foreach ($states_arr as $st) {
                    $statecode = DB::select("select CONCAT(sifi_state_id, '|', state_code) as state_target from states where state = ?", [$st]);
                    if($statecode) {
                        array_push($statecode_arr, $statecode[0]->state_target);
                    }
                    if(empty($statecode_arr)) {
                        $this->inserLog($request, 409, 'error', 'Please enter at least one valid state', $func, $companyID);
                        return response()->json([
                            'status' => 'error',
                            'message' => 'Please enter at least one valid state.',
                            'status_code' => 409
                        ], 409);
                    }
                } 
            }            

            if (count($statecode_arr) > count(array_unique($statecode_arr))) {
                $this->inserLog($request, 409, 'error', 'States are duplicated', $func, $companyID);
                return response()->json([
                    'status' => 'error',
                    'message' => 'States are duplicated.',
                    'status_code' => 409
                ], 409);
            }
            /* END VALIDATION TARGETING LOCATION */

            /* VALIDATION END DATE */
            try
            {
                $enddDate = Carbon::createFromFormat('Y-m-d', $request->end_date_campaign);

                if (empty($enddDate) || $enddDate->format('Y-m-d') != $request->end_date_campaign) {
                    $resCode    = 422;
                    $resSts     = 'error';
                    $resMsg     = 'The End Date Campaign format is invalid. Please use Y-m-d';

                    $this->inserLog($request, $resCode, $resSts, $resMsg, $func, $companyID);
                    
                    return response()->json([
                        'status' => $resSts, 
                        'message' => $resMsg,
                        'status_code' => $resCode
                    ], $resCode);
                }
                
                /* VALIDATION END DATE > TODAY DATE */
                $enddDate = $enddDate->toDateString();
                $todayDate = Carbon::now('America/New_York')->toDateString();

                // Log::info([
                //     'end_date_campaign' => $request->end_date_campaign,
                //     'enddDate' => $enddDate,
                //     'todayDate' => $todayDate,
                // ]);

                if($enddDate <= $todayDate) {
                    $resCode    = 422;
                    $resSts     = 'error';
                    $resMsg     = 'The End Date Campaign Must Be After Today Date';

                    $this->inserLog($request, $resCode, $resSts, $resMsg, $func, $companyID);
                    
                    return response()->json([
                        'status' => $resSts, 
                        'message' => $resMsg,
                        'status_code' => $resCode
                    ], $resCode);
                }
                /* VALIDATION END DATE > TODAY DATE */
            }
            catch(\Exception $e)
            {
                $resCode    = 422;
                $resSts     = 'error';
                $resMsg     = 'The End Date Campaign format is invalid. Please use Y-m-d';

                $this->inserLog($request, $resCode, $resSts, $resMsg, $func, $companyID);
                
                return response()->json([
                    'status' => $resSts, 
                    'message' => $resMsg,
                    'status_code' => $resCode
                ], $resCode);
            }
            /* VALIDATION END DATE */
        }
        /* VALIDATOR */

        /* VARIABLE */
        // CHECK USER TYPE MUST BE AGENCY
        $companyIDRoots = User::where('company_parent',null)
                              ->pluck('company_id')
                              ->toArray();

        if(in_array($companyID, $companyIDRoots)) {
            $resCode    = 422;
            $resSts     = 'error';
            $resMsg     = 'Your Token Does Not Have The Ability To Create Campaigns';

            $this->inserLog($request, $resCode, $resSts, $resMsg, $func, $companyID);

            return response()->json([
                'status' => $resSts, 
                'message' => $resMsg,
                'status_code' => $resCode
            ], $resCode);
            
        }
        // CHECK USER TYPE MUST BE AGENCY
        
        // GET USER AND COMPANIES CLIENT
        $salt = substr(hash('sha256', env('APP_KEY')), 0, 16);
        $user = User::select('users.id','users.email','users.company_id','users.company_root_id','companies.company_name','companies.paymentterm_default','companies.manual_bill','users.customer_payment_id','users.customer_card_id')
                    ->join('companies','companies.id','=','users.company_id')
                    ->where('users.user_type', 'client')
                    ->where('users.company_parent', $companyID);

        if($request->filled('email'))
            $user = $user->where(DB::raw("CONVERT(AES_DECRYPT(FROM_bASE64(users.email), '" . $salt . "') USING utf8mb4)"), strtolower($request->email));
        else if($request->filled('user_id'))
            $user = $user->where('users.id', $request->user_id);
        else {
            $resCode    = 422;
            $resSts     = 'error';
            $resMsg     = 'You must provide either an email or user_id, not both or neither';

            $this->inserLog($request, $resCode, $resSts, $resMsg, $func, $companyID);

            return response()->json([
                'status' => $resSts, 
                'message' => $resMsg,
                'status_code' => $resCode
            ], $resCode);
        }

        $user = $user->first();
                    
        // Log::info('', ['user' => $user]);

        if(empty($user))
        {
            $message = "";

            if($request->filled('email'))
                $message = "Sorry Email Is Incorrect";
            else if($request->filled('user_id'))
                $message = "Sorry User ID Is Incorrect";
            else 
                $message = 'Client Not Found';

            $this->inserLog($request, 404, 'error', $message, $func, $companyID);
            return response()->json([
                'status' => 'error',
                'message' => $message,
                'status_code' => 404,
            ], 404);
        }
        // GET USER AND COMPANIES CLIENT

        // CHECK MANUAL BILL
        $agency = Company::select('manual_bill')
                         ->where('id','=',$companyID)
                         ->first();

        // Log::info('', [
        //     'agency' => $agency,
        //     'manual_bill' => $agency->manual_bill ?? ""
        // ]);

        $manual_bill = $agency->manual_bill ?? "F";
        $customer_card_id = $user->customer_card_id ?? "";
        $customer_payment_id = $user->customer_payment_id ?? "";

        if($manual_bill == 'F' && empty(trim($customer_card_id)) && empty(trim($customer_payment_id)))
        {
            $message = "";

            if($request->filled('email'))
                $message = "Sorry Email Client Has Not Filled Payment";
            else if($request->filled('user_id'))
                $message = "Sorry User ID Client Has Not Filled Payment";
            else 
                $message = 'Client Not Found';

            $this->inserLog($request, 404, 'error', $message, $func, $companyID);
            return response()->json([
                'status' => 'error',
                'message' => $message,
                'status_code' => 404,
            ], 404);
        }
        // CHECK MANUAL BILL
        
        $userID = $user->id ?? "";
        if(empty($userID)) {
            $this->inserLog($request, 400, 'error', 'Id Client Empty', $func, $companyID);
            return response()->json([
                'status' => 'error',
                'message' => "Id Client Empty",
                'status_code' => 400,
            ], 400);
        }

        $companyName = $user->company_name ?? "";
        if(empty($companyName)) {
            $this->inserLog($request, 400, 'error', 'Company Name Client Empty', $func, $companyID);
            return response()->json([
                'status' => 'error',
                'message' => "Company Name Client Empty",
                'status_code' => 400,
            ], 400);
        }

        $reportType = "GoogleSheet";

        $reportSentTo = $user->email ?? "";
        if(empty($reportSentTo)) {
            $this->inserLog($request, 400, 'error', 'Email Client Empty', $func, $companyID);
            return response()->json([
                'status' => 'error',
                'message' => "Email Client Empty",
                'status_code' => 400,
            ], 400);
        }

        $adminNotifyTo = [];
        $leadsAmountNotification = "500";
        $leadspeekType = $campaignType;
        $companyGroupID = $userID;
        $clientOrganizationID = null;
        $clientCampaignID = null;
        $clientHidePhone = "T";
        $campaignName = $request->campaign_name;
        $gtminstalled = "";
        $urlCode = null;
        $answer_local = array();
        if($campaignType == 'local')
        {
            $urlCode = $request->url;
            if (is_array($urlCode)) {
                $urlCode = implode('|', $urlCode);
            } 
            $gtminstalled = $request->installed_gtm ?? "F";

            // EXCLUDE AND SELECTED STATE
            $exclude_state  = $request->exclude_state;
            $selected_state = $request->selected_state;

            if ($exclude_state && $selected_state) {
                $this->inserLog($request, 422, 'error', 'Please provide only one: exclude_state or selected_state..', $func, $companyID);
                return response()->json([
                    'status' => 'error',
                    'message' => "Please provide only one: exclude_state or selected_state..",
                    'status_code' => 422,
                ], 422);
            }

            if ($exclude_state) {
                $exclude_state = State::whereIn('state', $exclude_state)
                                ->pluck('state_code')
                                ->toArray();

                $answer_local = [
                    'includeExclude' => "0",
                    'state' => $exclude_state // Berisi array ['CO', 'CA']
                ];
            }

            if ($selected_state) {
                $selected_state = State::whereIn('state', $selected_state)
                                ->pluck('state_code')
                                ->toArray();
                        
                $answer_local = [
                    'includeExclude' => "1",
                    'state' => $selected_state // Berisi array ['CO', 'CA']
                ];
            }
            // EXCLUDE AND SELECTED STATE
        }

        $urlCodeThankyou = null;
        
        // GET DEFAULT PAYMENT TERM
        $paymentTermDefault = $user->paymentterm_default ?? ""; // paymenttem_default client
        if(empty($paymentTermDefault))
        {
            $company = Company::where('id', $companyID)
                              ->first();

            $paymentTermDefault = $company->paymentterm_default ?? ""; // jika paymenttem_default client kosong, pakai paymenttem_default agency
            if(empty($paymentTermDefault)) 
            {
                $companyIDRoot = $user->company_root_id ?? "";
                if(empty($companyIDRoot)) {
                    $this->inserLog($request, 400, 'error', 'Company ID Root Empty', $func, $companyID);
                    return response()->json([
                        'status' => 'error',
                        'message' => "Company ID Root Empty",
                        'status_code' => 400,
                    ], 400);
                }

                $company = Company::where('id', $companyIDRoot)
                                  ->first();

                $paymentTermDefault = $company->paymentterm_default ?? "Weekly"; // jika paymenttem_default agency kosong, pakai paymenttem_default root
            }
        }
        // GET DEFAULT PAYMENT TERM

        // GET LEAD DAY DEFAULT
        $companyIDClient = $user->company_id ?? "";
        if(empty($companyIDClient)) {
            $this->inserLog($request, 400, 'error', 'Company ID Client Empty', $func, $companyID);
            return response()->json([
                'status' => 'error',
                'message' => "Company ID Client Empty",
                'status_code' => 400,
            ], 400);
        }
        
        $clientSetting = $this->getcompanysetting($companyIDClient, 'clientdefaultprice');
        $leadPerDay = $clientSetting->$campaignType->$paymentTermDefault->EnhanceLeadsPerday ?? "10";
        // GET LEAD DAY DEFAULT

        $answers = [
            "asec5_4_0_0" => false,
            "asec5_4_0_1" => false,
            "asec5_4_0_2" => false,
            "asec5_4_0_3" => false,
            "asec5_4" => [],
            "asec5_4_1" => null,
            "asec5_4_2" => [],
            "asec5_3" => null,
            "asec5_5" => null,
            "asec5_6" => "",
            "asec5_9_1" => false,
            "asec5_10" => [],
            "asec5_10_1" => [],
            "asec6_5" => "",
            "startdatecampaign" => "",
            "enddatecampaign" => ""
        ];
        
        if($campaignType == 'enhance' || $campaignType == 'b2b')
        {
            // FILTER SEARCH KEYWORD
            $filterSearchKeyword = $this->filterKeyword($request->search_keyword, $campaignType);
            if($filterSearchKeyword['status'] == 'error') {
                $this->inserLog($request, 400, 'error', $filterSearchKeyword['message'], $func, $companyID);
                return response()->json([
                    'status' => 'error',
                    'message' => $filterSearchKeyword['message'],
                    'status_code' => 400
                ], 400);
            }
    
            $searchKeywordArray = $filterSearchKeyword['data'];
            // FILTER SEARCH KEYWORD
    
            // DATE CAMPAIGN AND ORI DATE CAMPAIGN
            $oristartdatecampaign = Carbon::now('America/New_York')->startOfDay()->format('Y-m-d H:i:s');
            $orienddatecampaign = Carbon::now('America/New_York')->addMonths(6)->endOfDay()->format('Y-m-d H:i:s');
    
            $startdatecampaign = $this->formatDate($oristartdatecampaign, true, false); // Dari America/New_York ke Asia/Jakarta
            $enddatecampaign = $this->formatDate($orienddatecampaign, true, false); // Dari America/New_York ke Asia/Jakarta
    
            // Log::info([
            //     'oristartdatecampaign' => $oristartdatecampaign,
            //     'orienddatecampaign' => $orienddatecampaign,
            //     'startdatecampaign' => $startdatecampaign,
            //     'enddatecampaign' => $enddatecampaign,
            // ]);
            // DATE CAMPAIGN AND ORI DATE CAMPAIGN

            $national_targeting  = $request->national_targeting;

            $answers['asec5_4_0_0'] = $national_targeting ? $this->trueOrFalse($national_targeting) : false;
            $answers['asec5_4_0_1'] = is_array($statecode_arr) && !empty($statecode_arr) ? true : false;
            $answers['asec5_4_0_2'] = is_array($city_arr) && !empty($city_arr) ? true : false;
            $answers['asec5_4_0_3'] = $zip_tx != '' ? true : false;
            //// zipcode
            $answers['asec5_3'] = $zip_tx;
            //// state
            $answers['asec5_4'] = $statecode_arr;
            //// city
            $answers['asec5_4_2'] = $city_arr;

            $answers['asec5_9_1'] = true;
            $answers['asec5_10'] = $searchKeywordArray;
            $answers['asec5_6'] = $leadPerDay;
            $answers['asec6_5'] = "FirstName,LastName,MailingAddress,Phone";
            $answers['startdatecampaign'] = $startdatecampaign;
            $answers['enddatecampaign'] = $enddatecampaign;
        }
        else if($campaignType == 'locator')
        {
            // FILTER SEARCH KEYWORD
            $filterSearchKeyword = $this->filterKeyword($request->search_keyword, $campaignType);
            if($filterSearchKeyword['status'] == 'error') {
                $this->inserLog($request, 400, 'error', $filterSearchKeyword['message'], $func, $companyID);
                return response()->json([
                    'status' => 'error',
                    'message' => $filterSearchKeyword['message'],
                    'status_code' => 400
                ], 400);
            }
    
            $searchKeywordArray = $filterSearchKeyword['data'];
            // FILTER SEARCH KEYWORD

            // FILTER CONTEXT KEYWORD
            $filterContextKeyword = $this->filterKeyword($request->context_keyword, $campaignType);
            if($filterContextKeyword['status'] == 'error') {
                $this->inserLog($request, 400, 'error', $filterContextKeyword['message'], $func, $companyID);
                return response()->json([
                    'status' => 'error',
                    'message' => $filterContextKeyword['message'],
                    'status_code' => 400
                ], 400);
            }
    
            $contextKeywordArray = $filterContextKeyword['data'];
            // FILTER CONTEXT KEYWORD

            // DATE CAMPAIGN AND ORI DATE CAMPAIGN
            $oristartdatecampaign = Carbon::now('America/New_York')->startOfDay()->format('Y-m-d H:i:s');
            $orienddatecampaign = Carbon::parse($request->end_date_campaign)->endOfDay()->format('Y-m-d H:i:s'); // ini yang diubah manual
    
            $startdatecampaign = $this->formatDate($oristartdatecampaign, true, false); // Dari America/New_York ke America/Chicago
            $enddatecampaign = $this->formatDate($orienddatecampaign, true, false); // Dari America/New_York ke America/Chicago
    
            // Log::info([
            //     'oristartdatecampaign' => $oristartdatecampaign,
            //     'orienddatecampaign' => $orienddatecampaign,
            //     'startdatecampaign' => $startdatecampaign,
            //     'enddatecampaign' => $enddatecampaign,
            // ]);
            // DATE CAMPAIGN AND ORI DATE CAMPAIGN

            $national_targeting  = $request->national_targeting;
            
            $answers['asec5_4_0_0'] = $national_targeting ? $this->trueOrFalse($national_targeting) : false;
            $answers['asec5_4_0_1'] = is_array($statecode_arr) && !empty($statecode_arr) ? true : false;
            $answers['asec5_4_0_2'] = false;
            $answers['asec5_4_0_3'] = $zip_tx != '' ? true : false;
            //// zipcode
            $answers['asec5_3'] = $zip_tx;
            //// state
            $answers['asec5_4'] = $statecode_arr;

            $answers['asec5_4_1'] = ['8180'];
            $answers['asec5_9_1'] = true;
            $answers['asec5_10'] = $searchKeywordArray;
            $answers['asec5_10_1'] = $contextKeywordArray;
            $answers['asec5_6'] = $leadPerDay;
            $answers['asec6_5'] = "FirstName,LastName,MailingAddress,Phone";
            $answers['startdatecampaign'] = $startdatecampaign ?? "";
            $answers['enddatecampaign'] = $enddatecampaign ?? "";
        }

        $phoneenabled = $request->enable_phone ? $this->trueOrFalse($request->enable_phone) : false;
        $homeaddressenabled = $request->enable_home ? $this->trueOrFalse($request->enable_home) : false;
        $requireemailaddress = $request->require_email ? $this->trueOrFalse($request->require_email) : true;
        $reidentificationtype = $request->reident_time_limit ?? 'never';
        $applyreidentificationall = $request->reidentified_contact ? $this->trueOrFalse($request->reidentified_contact) : false;
        // $adminNotifyTo = $request->userid_admin_gsheet ? implode(',', $request->userid_admin_gsheet) : [];
        
        // VALIDATION ADMIN GOOGLE SHEET
        if($request->userid_admin_gsheet) 
        {
            $valid_user_admin_gsheet = User::where('company_id','=',$companyID)
                                           ->whereRaw("user_type IN ('user','userdownline')")
                                           ->where('isAdmin','=','T')
                                           ->where('active','=','T')
                                           ->whereIn('users.id', $request->userid_admin_gsheet)
                                           ->pluck('users.id')
                                           ->toArray();

            $invalidUserIds = array_diff($request->userid_admin_gsheet, $valid_user_admin_gsheet);
            
            if (!empty($invalidUserIds)) 
            {
                $msgErr = 'Invalid user id admin googlesheet : ' . implode(',', $invalidUserIds) ;
                $this->inserLog($request, 400, 'error', $msgErr, $func, $companyID);
                return response()->json([
                    'status' => 'error',
                    'message' => $msgErr,
                    'status_code' => 400,
                ], 400);
            }

            $adminNotifyTo = $request->userid_admin_gsheet;
        }
        // VALIDATION ADMIN GOOGLE SHEET

        // ADVANCE INFORMATION
        $campaign_information_type = "basic";
        $campaign_information_type_local = ['basic'];
        $listAdvanceInformationId = [];
        if($request->has('advanced_information') && in_array($campaignType, ['local', 'enhance', 'b2b']))
        {
            $advanced_information = $request->advanced_information ?? [];
            // info(['advanced_information' => $advanced_information]);
            
            // IF advanced_information ZERO ITEM IN ARRAY
            if(count($advanced_information) == 0)
            {
                $msgErr = "advanced information list must have at least 1";
                $this->inserLog($request, 400, 'error', $msgErr, $func, $companyID);
                return response()->json(['status' => 'error', 'message' => $msgErr, 'status_code' => 400], 400);
            }
            // IF advanced_information ZERO ITEM IN ARRAY

            // FORMAT ADVANCE INFORMATION
            // key = yang dikirim dari request , value = yang ada di database
            $listFormatAdvanceInformation = [];
            if($campaignType == 'local' || $campaignType == 'enhance')
            {
                $listFormatAdvanceInformation = [
                    'identification' => 'identification',
                    'financial' => 'financialInformation',
                    'occupation' => 'occupation',
                    'miscellaneous' => 'miscellaneous',
                    'contact' => 'contactInformation',
                    'household' => 'householdInformation',
                    'marketing_indicator' => 'marketingIndicators',
                    'house_and_real_estate' => 'houseAndRealEstate',
                    'interests_and_affinities' => 'interestsAndAffinities',
                    'location_and_cencus_data' => 'locationAndCensusData'
                ];
            }
            else if($campaignType == 'b2b')
            {
                $listFormatAdvanceInformation = [
                    'company_name' => 'company_name',
                    'company_email' => 'company_email',
                    'company_phone' => 'company_phone',
                    'company_website' => 'company_website',
                    'job_title' => 'job_title',
                    'level' => 'level',
                    'job_function' => 'job_function',
                    'linkedin' => 'linkedin',
                    'num_employees' => 'num_employees',
                    'sales_volume' => 'sales_volume',
                    'year_founded' => 'year_founded',
                    'last_seen_date' => 'last_seen_date',
                ];
            }
            // FORMAT ADVANCE INFORMATION

            // VALIDATION IF LIST ADVANCE NOT AVALIALABLE IN LIST FORMAT
            foreach($advanced_information as $item)
            {
                if(empty($item) || trim($item) == '')
                {
                    $msgErr = "item in advanced information must be string";
                    $this->inserLog($request, 400, 'error', $msgErr, $func, $companyID);
                    return response()->json(['status' => 'error', 'message' => $msgErr, 'status_code' => 400], 400);
                }
                if(!array_key_exists($item, $listFormatAdvanceInformation))
                {
                    $msgErr = "item {$item} in parameter advanced information not available";
                    $this->inserLog($request, 400, 'error', $msgErr, $func, $companyID);
                    return response()->json(['status' => 'error', 'message' => $msgErr, 'status_code' => 400], 400);
                } 
            }
            // VALIDATION IF LIST ADVANCE NOT AVALIALABLE IN LIST FORMAT

            // VALIDATION LIST UNIQUE
            if(count($advanced_information) > 1 && count($advanced_information) != count(array_unique($advanced_information)))
            {
                $msgErr = "duplicate item(s) found in advanced_information.";
                $this->inserLog($request, 400, 'error', $msgErr, $func, $companyID);
                return response()->json(['status' => 'error', 'message' => $msgErr, 'status_code' => 400], 400);
            }
            // VALIDATION LIST UNIQUE

            // GET VALUD ADVANCED INFORATION WITH KEY ADVANCE INFORMATION
            $listValueAdvanceInformation = [];
            foreach($advanced_information as $item)
            {
                if(isset($listFormatAdvanceInformation[$item]))
                {
                    $listValueAdvanceInformation[] = $listFormatAdvanceInformation[$item];
                }
            }
            // GET VALUD ADVANCED INFORATION WITH KEY ADVANCE INFORMATION

            // GET ID ADVANCE INFORMATION
            if($campaignType == 'local') 
            {
                $campaign_information_type_local[] = 'advanced';
                $listAdvanceInformationId = campaignInformation::where('campaign_type', 'local_adv')
                                                                ->where('status', 'active')
                                                                ->whereIn('type', $listValueAdvanceInformation)
                                                                ->orderBy('id', 'asc')
                                                                ->pluck('id')
                                                                ->toArray();
                // info('ADVANCE INFORMATION 1.1', ['listAdvanceInformationId' => $listAdvanceInformationId, 'listValueAdvanceInformation' => $listValueAdvanceInformation,  'advanced_information' => $advanced_information]);
            }
            else if($campaignType == 'enhance')
            {
                $campaign_information_type = "advanced";
                $listAdvanceInformationId = campaignInformation::where('campaign_type', 'enhance')
                                                                ->where('status', 'active')
                                                                ->whereIn('type', $listValueAdvanceInformation)
                                                                ->orderBy('id', 'asc')
                                                                ->pluck('id')
                                                                ->toArray();
                // info('ADVANCE INFORMATION 1.2', ['listAdvanceInformationId' => $listAdvanceInformationId, 'listValueAdvanceInformation' => $listValueAdvanceInformation,  'advanced_information' => $advanced_information]);
            }
            else if($campaignType == 'b2b')
            {
                $listAdvanceInformationId = campaignInformation::where('campaign_type', 'b2b')
                                                               ->where('status', 'active')
                                                               ->whereIn('type', $listValueAdvanceInformation)
                                                               ->orderBy('id', 'asc')
                                                               ->pluck('id')
                                                               ->toArray();
                // info('ADVANCE INFORMATION 1.3', ['listAdvanceInformationId' => $listAdvanceInformationId, 'listValueAdvanceInformation' => $listValueAdvanceInformation,  'advanced_information' => $advanced_information]);
            }
            // GET ID ADVANCE INFORMATION
        }
        // ADVANCE INFORMATION
        
        $locationtarget = 'Focus';
        $timezone = 'America/Chicago';
        $inOpenApi = true;

        $idSys = $user->company_root_id ?? "";
        if(empty($idSys)){
            $this->inserLog($request, 400, 'error', 'Company ID Root Empty', $func, $companyID);
            return response()->json([
                'status' => 'error',
                'message' => "Company ID Root Empty",
                'status_code' => 400,
            ], 400);
        }
        /* VARIABLE */

        /* PROCESS CREATE CAMPAIGN */
        $data = [];

        if($campaignType == 'local')
        {
            $data = new Request([
                "companyID" => $companyID,
                "userID" => $userID,
                "companyName" => $companyName,
                "reportType" => $reportType,
                "reportSentTo" => $reportSentTo,
                "adminNotifyTo" => $adminNotifyTo,
                "leadsAmountNotification" => $leadsAmountNotification,
                "leadspeekType" => $leadspeekType,
                "companyGroupID" => $companyGroupID,
                "clientHidePhone" => $clientHidePhone,
                "campaignName" => $campaignName,
                "urlCode" => $urlCode,
                "urlCodeThankyou" => $urlCodeThankyou,
                "gtminstalled" => $gtminstalled,
                "phoneenabled" => $phoneenabled,
                "homeaddressenabled" => $homeaddressenabled,
                "requireemailaddress" => $requireemailaddress,
                "reidentificationtype" => $reidentificationtype,
                "applyreidentificationall" => $applyreidentificationall,
                "locationtarget" => $locationtarget,
                "answers" => $answer_local,
                "globalviewmode" => true,
                'idSys' => $idSys,
                "inOpenApi" => $inOpenApi, // parameter ini hanya ada di create campaign di open api saja
                "listAdvanceInformationId" => $listAdvanceInformationId,
                "campaign_information_type_local" => $campaign_information_type_local,
            ]);
        }
        else if($campaignType == 'enhance')
        {
            $data = new Request([
                'companyID' => $companyID,
                'userID' => $userID,
                'companyName' => $companyName,
                'reportType' => $reportType,
                'reportSentTo' => $reportSentTo,
                'adminNotifyTo' => $adminNotifyTo,
                'leadsAmountNotification' => $leadsAmountNotification,
                'leadspeekType' => $leadspeekType,
                'companyGroupID' => $companyGroupID,
                'clientOrganizationID' => $clientOrganizationID,
                'clientCampaignID' => $clientCampaignID,
                'clientHidePhone' => $clientHidePhone,
                'campaignName' => $campaignName,
                'urlCode' => $urlCode,
                'urlCodeThankyou' => $urlCodeThankyou,
                'answers' => $answers,
                'startdatecampaign' => $startdatecampaign,
                'enddatecampaign' => $enddatecampaign,
                'oristartdatecampaign' => $oristartdatecampaign,
                'orienddatecampaign' => $orienddatecampaign,
                'phoneenabled' => $phoneenabled,
                'homeaddressenabled' => $homeaddressenabled,
                'requireemailaddress' => $requireemailaddress,
                'reidentificationtype' => $reidentificationtype,
                'applyreidentificationall' => $applyreidentificationall,
                'locationtarget' => $locationtarget,
                'timezone' => $timezone,
                'idSys' => $idSys,
                "inOpenApi" => $inOpenApi, // parameter ini hanya ada di create campaign di open api saja, enhance
                "listAdvanceInformationId" => $listAdvanceInformationId,
                "campaign_information_type" => $campaign_information_type,
            ]);
        }
        else if($campaignType == 'b2b')
        {
            $data = new Request([
                'companyID' => $companyID,
                'userID' => $userID,
                'companyName' => $companyName,
                'reportType' => $reportType,
                'reportSentTo' => $reportSentTo,
                'adminNotifyTo' => $adminNotifyTo,
                'leadsAmountNotification' => $leadsAmountNotification,
                'leadspeekType' => $leadspeekType,
                'companyGroupID' => $companyGroupID,
                'clientOrganizationID' => $clientOrganizationID,
                'clientCampaignID' => $clientCampaignID,
                'clientHidePhone' => $clientHidePhone,
                'campaignName' => $campaignName,
                'urlCode' => $urlCode,
                'urlCodeThankyou' => $urlCodeThankyou,
                'answers' => $answers,
                'startdatecampaign' => $startdatecampaign,
                'enddatecampaign' => $enddatecampaign,
                'oristartdatecampaign' => $oristartdatecampaign,
                'orienddatecampaign' => $orienddatecampaign,
                'phoneenabled' => $phoneenabled,
                'homeaddressenabled' => $homeaddressenabled,
                'requireemailaddress' => $requireemailaddress,
                'reidentificationtype' => $reidentificationtype,
                'applyreidentificationall' => $applyreidentificationall,
                'locationtarget' => $locationtarget,
                'timezone' => $timezone,
                'idSys' => $idSys,
                "inOpenApi" => $inOpenApi, // parameter ini hanya ada di create campaign di open api saja, b2b
                "listAdvanceInformationId" => $listAdvanceInformationId,
            ]);
        }
        else if($campaignType == 'locator')
        {
            $data = new Request([
                "companyID" => $companyID,
                "userID" => $userID,
                "companyName" => $companyName,
                "reportType" => $reportType,
                "reportSentTo" => $reportSentTo,
                "adminNotifyTo" => $adminNotifyTo,
                "leadsAmountNotification" => $leadsAmountNotification,
                "leadspeekType" => $leadspeekType,
                "companyGroupID" => $companyGroupID,
                "clientOrganizationID" => $clientOrganizationID,
                "clientCampaignID" => $clientCampaignID,
                "clientHidePhone" => $clientHidePhone,
                "campaignName" => $campaignName,
                "urlCode" => $urlCode,
                "urlCodeThankyou" => $urlCodeThankyou,
                "answers" => $answers,
                "startdatecampaign" => $startdatecampaign,
                "enddatecampaign" => $enddatecampaign,
                "oristartdatecampaign" => $oristartdatecampaign,
                "orienddatecampaign" => $orienddatecampaign,
                "phoneenabled" => $phoneenabled,
                "homeaddressenabled" => $homeaddressenabled,
                "requireemailaddress" => $requireemailaddress,
                "reidentificationtype" => $reidentificationtype,
                "applyreidentificationall" => $applyreidentificationall,
                "locationtarget" => $locationtarget,
                "timezone" => $timezone,
                'idSys' => $idSys,
                "inOpenApi" => $inOpenApi, // parameter ini hanya ada di create campaign di open api saja
            ]);
        }

        $createCampaign = "";

        try
        {
            $createCampaign = $this->leadspeekController->createclient($data)->getData();

            $result = $createCampaign->result ?? "";
            $message = $createCampaign->message ?? "";
            if($result == 'failed' && $message != '')
            {
                $this->inserLog($request, 500, 'error', $message, $func, $companyID);
                return response()->json([
                    'status' => 'error', 
                    'message' => $message,
                    'status_code' => 500
                ], 500);
            }

            //set campaign's integration based on client integration settings
            if (isset($createCampaign->leadspeek_api_id) && !empty($createCampaign->leadspeek_api_id)) {
                $created_campaign = LeadspeekUser::where('leadspeek_api_id',$createCampaign->leadspeek_api_id)->first();

                if (!empty($created_campaign)) {                    

                    $client_integration_settings = IntegrationSettings::select('id','company_id','integration_slug','enable_sendgrid','enable_default_campaign')->where('company_id', $companyIDClient)->whereIn('integration_slug', ['mailboxpower','gohighlevel'])->get();
                    
                    if (!empty($client_integration_settings)) {

                        $campaign_fields = [
                            'gohighlevel' => 'ghl_is_active',
                            'mailboxpower' => 'mbp_is_active',
                            // 'sendgrid' => 'sendgrid_is_active',
                            // 'zapier' => 'zap_is_active',
                            // 'clickfunnels' => 'clickfunnels_is_active'
                        ];
                        
                        foreach ($client_integration_settings as $value) {
                            if (isset($campaign_fields[$value->integration_slug]) && $value->enable_sendgrid == 1 && $value->enable_default_campaign == 1) {
                                $field = $campaign_fields[$value->integration_slug];
                                if (isset($created_campaign->$field)) {
                                    $created_campaign->$field = 1;
                                }
                            }
                        }
                        $created_campaign->save();
                    }
                }
            }
            //set campaign's integration based on client integration settings
            
        }
        catch(\Exception $e)
        {
            $this->inserLog($request, 500, 'error', $e->getMessage(), $func, $companyID);
            return response()->json([
                'status' => 'error', 
                'message' => $e->getMessage(),
                'status_code' => 500
            ], 500);
        }
        /* PROCESS CREATE CAMPAIGN */

        // Log::info('', ['data' => $data->toArray()]);
        $this->inserLog($request, 200, 'success', "Campaign Type $campaignType Created Successfully", $func, $companyID, $userID, $createCampaign->leadspeek_api_id ?? "");
        return response()->json([
            'status' => 'success',
            'message' => "Campaign Type $campaignType Created Successfully.",
            'campaign_id' => $createCampaign->leadspeek_api_id ?? "",
            'status_code' => 200,
            // 'data' => $data->toArray()
        ]);
    }

    /**
     * Update Campaign
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateCampaignOpenApi(string $campaignType, Request $request)
    {
        $openApiToken = $request->attributes->get('openApiToken');
        $companyID = $openApiToken->company_id ?? "";
        $func = __FUNCTION__;

        /* VALIDATION ONLY AGENCY */
        $response = $this->openApiValidationService->validationTokenOnlyAgency($companyID);
        // info('validationTokenOnlyAgency', ['response' => $response]);
        if(($response['status'] ?? '') == 'error')
        {
            $resCode = $response['status_code'] ?? 400;
            $resSts = $response['status'];
            $resMsg = $response['message'] ?? "Something Went Wrong";
            $this->inserLog($request, $resCode, $resSts, $resMsg, $func, $companyID);
            return response()->json(['status' => $resSts, 'message' => $resMsg, 'status_code' => $resCode], $resCode);
        }
        /* VALIDATION ONLY AGENCY */

        /* PROCSS CREATE INTEGRATIONS CAMPAIGN */
        $response = $this->openApiCampaignUpdateService->updateCampaignOpenApi($campaignType, $companyID, $request);
        // info('createOrUpdateCampaignIntegration', ['response' => $response]);
        if(($response['status'] ?? '') == 'error')
        {
            $resCode = $response['status_code'] ?? 400;
            $resSts = $response['status'];
            $resMsg = $response['message'] ?? "Something Went Wrong";
            $this->inserLog($request, $resCode, $resSts, $resMsg, $func, $companyID);
            return response()->json(['status' => $resSts, 'message' => $resMsg, 'status_code' => $resCode], $resCode);
        }
        $resMsg = $response['message'] ?? "Successfully Create Campaign Integration";
        /* PROCSS CREATE INTEGRATIONS CAMPAIGN */

        /* RETURN */
        $resCode = 200;
        $resSts = 'success';
        $this->inserLog($request, $resCode, $resSts, $resMsg, $func, $companyID);
        return response()->json(['status' => $resSts, 'message' => $resMsg, 'status_code' => $resCode], $resCode);
        /* RETURN */
    }

    /**
     * Filter Keyword For Enhance And Locator, And Contextual In Locator
     * @return array
     */
    private function filterKeyword($keyword, $campaignType)
    {
        /**
         * Process Yang Terjadi
         * 1. UBAH STRING KE ARRAY DAN HAPUS ELEMEN YANG STRING KOSONG
         * 2. HAPUS KALIMAT YANG MENGANDUNG STOPWORDS
         * 3. HAPUS KALIMAT YANG LEBIH DARI 3 KATA
         * 4. VALIDASI APAKAH HASIL KALIMAT KEYWORDNYA KOSONG
         * 5. VALIDASI JUMLAH KARAKTER TIDAK BOLEH LEBIH DARI 500 KARAKTER
         */
        if($campaignType == 'enhance' || $campaignType == 'b2b')
        {
            /* UBAH STRING KE ARRAY DAN HAPUS ELEMEN YANG STRING KOSONG */
            // String to array
            $keywordArray = explode(',', $keyword);
            $keywordArray = array_filter($keywordArray, function ($item) {
                // Hanya mengambil elemen yang tidak kosong setelah trim
                return !empty(trim($item)); 
            });
            // Menghilangkan spasi di awal/akhir setiap elemen
            $keywordArray = array_map('trim', $keywordArray); 
            // Log::info(['keywordArray' => $keywordArray]);
            /* UBAH STRING KE ARRAY DAN HAPUS ELEMEN YANG STRING KOSONG */

            /* GET STOP WORDS */
            $systemid = config('services.application.systemid');
            $stopWordSetting = $this->getcompanysetting($systemid, 'bigdbmconf');
            $stopWords = [];
            $allowed2CharacterKeywords = [];
            if(!empty($stopWordSetting) && is_object($stopWordSetting))
            {
                $stopWords = (isset($stopWordSetting->stopWords) && is_array($stopWordSetting->stopWords)) ? $stopWordSetting->stopWords : [];
                $allowed2CharacterKeywords = (isset($stopWordSetting->allowed2CharacterKeywords) && is_array($stopWordSetting->allowed2CharacterKeywords)) ? $stopWordSetting->allowed2CharacterKeywords : [];
            }
            /* GET STOP WORDS */

            /* HAPUS KALIMAT YANG MENGANDUNG STOPWORDS */
            $keywordArray = array_map(function ($sentence) use ($stopWords) {
                // string to array 
                $sentenceArray = explode(" ", $sentence);
                // Filter kata yang ada dalam stopWords
                $sentenceArray = array_filter($sentenceArray, function($item) use ($stopWords) {
                    return !in_array($item, $stopWords);
                });
                // array to string
                $sentenceString = implode(" ", $sentenceArray);
                // return setence
                return $sentenceString;
            }, $keywordArray);

            // Menghapus elemen kosong setelah filter
            $keywordArray = array_filter($keywordArray); 
            // Mengurutkan ulang indeks
            $keywordArray = array_values($keywordArray); 
            // Menghilangkan spasi di awal/akhir setiap elemen
            $keywordArray = array_map('trim', $keywordArray); 
            // Log::info(['keywordArray' => $keywordArray]);
            /* HAPUS KALIMAT YANG MENGANDUNG STOPWORDS */

            /* HAPUS 2 KATA DI KALIMAT YANG TIDAK MENGANDUNG allowed2CharacterKeywords */
            $keywordArray = array_map(function ($sentence) use ($allowed2CharacterKeywords) {
                $sentenceArray = explode(' ', $sentence);
                // info(['sentenceArray' => $sentenceArray]);
                
                $filteredArray = array_map(function ($word) use ($allowed2CharacterKeywords) {
                    // Jika kata hanya 2 huruf
                    if(strlen($word) === 2) 
                    {
                        // Jika kata TIDAK ADA di daftar allowed -> hapus
                        if(!in_array($word, $allowed2CharacterKeywords, true)) 
                        {
                            // Cek juga versi uppercase/lowercase
                            $variants = [strtolower($word), strtoupper($word), ucfirst(strtolower($word))];
                            foreach($variants as $v) 
                            {
                                if(in_array($v, $allowed2CharacterKeywords, true)) 
                                {
                                    return $v; // force ke versi sesuai list
                                }
                            }
                            return ''; // hapus kalau tidak ada yang cocok
                        }
            
                        // Jika ada di daftar allowed tapi casing berbeda → sesuaikan casing-nya
                        foreach($allowed2CharacterKeywords as $allowed) 
                        {
                            if(strcasecmp($allowed, $word) === 0) 
                            {
                                return $allowed; // gunakan versi resmi dari daftar
                            }
                        }
                    }
            
                    return $word; // kata normal, biarkan apa adanya
                }, $sentenceArray);
                // info(['filteredArray' => $filteredArray]);
                
                // Hilangkan kata kosong
                $filteredArray = array_filter($filteredArray, fn($w) => !empty($w));
                // info(['filteredArray' => $filteredArray]);

                // implode
                $filteredString = trim(implode(' ', $filteredArray));
                // info(['filteredString' => $filteredString]);
                
                return $filteredString;
            }, $keywordArray);

            // Menghapus elemen kosong setelah filter
            $keywordArray = array_filter($keywordArray); 
            // Mengurutkan ulang indeks
            $keywordArray = array_values($keywordArray);
            // Menghilangkan spasi di awal/akhir setiap elemen
            $keywordArray = array_map('trim', $keywordArray);
            // Log::info(['keywordArray' => $keywordArray]);
            /* HAPUS 2 KATA DI KALIMAT YANG TIDAK MENGANDUNG allowed2CharacterKeywords */

            /* HAPUS KALIMAT YANG LEBIH DARI 3 KATA */
            $keywordArray = array_filter($keywordArray, function ($item) {
                // hitung jumlah kata dalam 1 kalimat
                $wordLength = count(explode(" ", $item));
                // return tidak boleh dari 3 kata dalam 1 kalimat
                return $wordLength <= 3;
            });
            // Mengurutkan ulang indeks
            $keywordArray = array_values($keywordArray); 
            // Log::info(['keywordArray' => $keywordArray]);
            /* HAPUS KALIMAT YANG LEBIH DARI 3 KATA */

            /* VALIDASI APAKAH HASIL KALIMAT KEYWORDNYA KOSONG */
            $keywordLength = count($keywordArray);
            if($keywordLength == 0)
                return [
                    'status' => 'error',
                    'message' => 'keyword is empty because it is affected by validation'
                ];
            /* VALIDASI APAKAH HASIL KALIMAT KEYWORDNYA KOSONG */

            /* VALIDASI APAKAH TOTAL KARAKTER NYA LEBIH DARI 500 KARAKTER */
            $setenceLength = strlen(implode(",", $keywordArray));
            // Log::info(['setenceLength' => $setenceLength]);

            if($setenceLength > 500) 
                return [
                    'status' => 'error',
                    'message' => 'The number of characters must not exceed 500 characters'
                ];
            /* VALIDASI APAKAH TOTAL KARAKTER NYA LEBIH DARI 500 KARAKTER */
        
            return [
                'status' => 'success',
                'data' => $keywordArray,
            ];
        }
        /**
         * Process Yang Terjadi
         * 1. UBAH STRING KE ARRAY DAN HAPUS ELEMEN YANG STRING KOSONG
         * 2. HAPUS KALIMAT YANG KURANG DARI 3 KARAKTER
         * 3. HAPUS KALIMAT YANG LEBIH DARI 3 KATA
         * 4. VALIDASI APAKAH HASIL KALIMAT KEYWORDNYA KOSONG
         */
        else if($campaignType == 'locator')
        {
            /* UBAH STRING KE ARRAY DAN HAPUS ELEMEN YANG STRING KOSONG */
            $keywordArray = explode(',', $keyword); // String to array
            $keywordArray = array_filter($keywordArray, function ($item) {
                return !empty(trim($item)); // Hanya mengambil elemen yang tidak kosong setelah trim
            });
            $keywordArray = array_map('trim', $keywordArray); // Menghilangkan spasi di awal/akhir setiap elemen
            /* UBAH STRING KE ARRAY DAN HAPUS ELEMEN YANG STRING KOSONG */

            /* HAPUS KALIMAT YANG KURANG DARI 3 KARAKTER */
            $keywordArray = array_filter($keywordArray, function ($item) {
                // filter minimal character nya 3
                return strlen($item) >= 3;
            });
            /* HAPUS KALIMAT YANG KURANG DARI 3 KARAKTER */

            /* HAPUS KALIMAT YANG LEBIH DARI 3 KATA */
            $keywordArray = array_filter($keywordArray, function ($item) {
                // hitung jumlah kata dalam 1 kalimat
                $wordLength = count(explode(" ", $item));
                // return tidak boleh dari 3 kata dalam 1 kalimat
                return $wordLength <= 3;
            });
            // Mengurutkan ulang indeks
            $keywordArray = array_values($keywordArray);
            // Log::info(['keywordArray' => $keywordArray]);
            /* HAPUS KALIMAT YANG LEBIH DARI 3 KATA */

            /* VALIDASI APAKAH HASIL KALIMAT KEYWORDNYA KOSONG */
            $keywordLength = count($keywordArray);
            if($keywordLength == 0)
                return [
                    'status' => 'error',
                    'message' => 'keyword is empty because it is affected by validation'
                ];
            /* VALIDASI APAKAH HASIL KALIMAT KEYWORDNYA KOSONG */
            
            return [
                'status' => 'success',
                'data' => $keywordArray,
            ];
        }
        else 
        {
            return [
                'status' => 'error',
                'message' => 'Campaign Type Must Be Locator Or Enhance or B2B',
            ];
        }
    }

    /**
     * the logic this function duplicate in client vue js convert to php, name function "format_date"
     * @return string
     */
    private function formatDate($valdate, $convert = false, $toClientTime = false)
    {
        if ($valdate) 
        {
            if ($convert) 
            {
                $sourceTimezone = 'Asia/Jakarta'; // Zona waktu sumber
                $targetTimezone = 'America/Chicago'; // Zona waktu target

                if ($toClientTime) 
                {
                    $sourceTimezone = 'America/Chicago'; // Mengubah timezone sumber ke America/Chicago
                    $targetTimezone = 'Asia/Jakarta'; // Mengubah timezone target ke Asia/Jakarta
                }

                // Mengonversi waktu ke timezone sumber
                $sourceMoment = Carbon::parse($valdate, $sourceTimezone);

                // Mengonversi waktu ke timezone target
                $targetMoment = $sourceMoment->copy()->setTimezone($targetTimezone);

                // Mengembalikan waktu yang sudah diformat
                return $targetMoment->format('Y-m-d H:i:s');
            } 
            else 
            {
                // Jika tidak perlu mengonversi timezone, hanya format biasa
                return Carbon::parse($valdate)->format('Y-m-d H:i:s');
            }
        }
    }

    /**
     * Update Price Campaign
     * @return \Illuminate\Http\JsonResponse
     */
    public function UpdatePriceCampaignOpenApi(Request $request, string $campaignid, string $paymentterm, string $prepaidtype = "")
    {
        date_default_timezone_set('America/Chicago');

        /* GET ATTRIBUTE */
        $openApiToken = $request->attributes->get('openApiToken');
        $companyID = $openApiToken->company_id;
        /* GET ATTRIBUTE */

        $func = __FUNCTION__;

        /* VALIDATE PAYMENT TERM */
        if(!in_array($paymentterm, ['weekly','monthly','prepaid']))
        {
            $resCode = 422;
            $resSts = 'error';
            $resMsg = "Payment Campaign Must Be weekly Or monthly Or prepaid";
            $this->inserLog($request, $resCode, $resSts, $resMsg, $func, $companyID);
            return response()->json(['status' => $resSts, 'message' => $resMsg, 'status_code' => $resCode], $resCode);
        }
        /* VALIDATE PAYMENT TERM */

        /* VALIDATOR */
        // if use paymentterm prepaid, prepaidtype must be required
        if($paymentterm == 'prepaid')
        {
            if(empty($prepaidtype))
            {
                $resCode = 422;
                $resSts = 'error';
                $resMsg = "Prepaid Type Required If Campaign Type Prepaid";
                $this->inserLog($request, $resCode, $resSts, $resMsg, $func, $companyID);
                return response()->json(['status' => $resSts, 'message' => $resMsg, 'status_code' => $resCode], $resCode);
            }
            else if(!in_array($prepaidtype, ['continual','onetime']))
            {
                $resCode = 422;
                $resSts = 'error';
                $resMsg = "Prepaid Type Must Be continual Or onetime";
                $this->inserLog($request, $resCode, $resSts, $resMsg, $func, $companyID);
                return response()->json(['status' => $resSts, 'message' => $resMsg, 'status_code' => $resCode], $resCode);
            } 
        }
        // if use paymentterm prepaid, prepaidtype must be required

        // check user type must be agency
        $companyIDRoots = User::where('company_parent',null)
                              ->pluck('company_id')
                              ->toArray();

        if(in_array($companyID, $companyIDRoots))
        {
            $resCode = 422;
            $resSts = 'error';
            $resMsg = "Your Token Does Not Have The Ability To Create Campaigns";
            $this->inserLog($request, $resCode, $resSts, $resMsg, $func, $companyID);
            return response()->json(['status' => $resSts, 'message' => $resMsg, 'status_code' => $resCode], $resCode);
        }
        // check user type must be agency

        // get campaign
        $campaign = LeadspeekUser::select(
                'leadspeek_users.id','leadspeek_users.continual_buy_options','leadspeek_users.topupoptions',
                'leadspeek_users.leadsbuy','leadspeek_users.active','leadspeek_users.disabled',
                'leadspeek_users.active_user','leadspeek_users.paymentterm','leadspeek_users.platformfee',
                'leadspeek_users.lp_min_cost_month','leadspeek_users.cost_perlead','leadspeek_users.leadspeek_type',
                'leadspeek_users.lp_limit_freq','leadspeek_users.lp_limit_leads','leadspeek_users.lp_enddate',
                'leadspeek_users.enable_minimum_limit_leads','leadspeek_users.minimum_limit_leads',
                'users.id as agency_id','users.company_root_id')
            ->join('users','users.company_id','=','leadspeek_users.company_id')
            ->where('leadspeek_users.leadspeek_api_id', $campaignid)
            ->where('leadspeek_users.company_id', $companyID)
            ->where('leadspeek_users.archived', 'F')
            ->where('users.user_type','userdownline')
            ->first();

        // Log::info('', [
        //     'campaign' => $campaign
        // ]);

        if(empty($campaign))
        {
            $resCode = 404;
            $resSts = 'error';
            $resMsg = "Campaign Not Found";
            $this->inserLog($request, $resCode, $resSts, $resMsg, $func, $companyID);
            return response()->json(['status' => $resSts, 'message' => $resMsg, 'status_code' => $resCode], $resCode);
        }
        // get campaign

        // prive campaign financials
        $lp_limit_freq = $campaign->lp_limit_freq ?? '';
        $prevCampaignFinancial = [
            'billingFrequency' => $campaign->paymentterm ?? '',
            'topUpOptions' => $campaign->topupoptions ?? '',
            'setupFee' => $campaign->platformfee ?? '',
            'campaignFee' => $campaign->lp_min_cost_month ?? '',
            'costPerLead' => $campaign->cost_perlead ?? '',
            'leadPerDay' => $campaign->lp_limit_leads ?? '',
            'contiDurationSelection' => $campaign->continual_buy_options ?? '',
            'endDate' => $campaign->lp_enddate ?? '',
            'enableMinimumLeadPerDay' => $campaign->enable_minimum_limit_leads ?? '',
            'minimumLeadPerDay' => $campaign->minimum_limit_leads ?? '',
            'lpLimitFreq' => $lp_limit_freq,
        ];
        // prive campaign financials

        $rules = ['lead_per_day' => ['required','integer','min:1']];

        // create rules based on campaign status
        $campaign_active = $campaign->active ?? "";
        $campaign_disabled = $campaign->disabled ?? "";
        $campaign_active_user = $campaign->active_user ?? "";
        $campaign_paymentterm = isset($campaign->paymentterm) ? strtolower($campaign->paymentterm) : "";
        $campaign_topupoptions = isset($campaign->topupoptions) ? (trim($campaign->topupoptions) != '' ? strtolower($campaign->topupoptions) : "continual") : "continual";
        $campaign_leadspeektype = $campaign->leadspeek_type ?? "";

        if($campaign_leadspeektype == 'locator')
        {
            $resMsg = "Update Status For Campaign $campaignid In Modules locator Is Not Permitted";
            $resCode = 400;
            $this->inserLog($request, $resCode, 'error', $resMsg, $func, $companyID);
            return response()->json(['status' => 'error', 'message' => $resMsg, 'status_code' => $resCode], $resCode);
        }

        if($campaign_active == 'F' && $campaign_disabled == 'T' && $campaign_active_user == 'F') // stop
        {
            $rules['setup_fee'] = ['required','numeric','min:0'];
            $rules['campaign_fee'] = ['required','numeric','min:0'];
            $rules['cost_per_lead'] = ['required','numeric','min:0'];
        }
        else if($campaign_active == 'F' && $campaign_disabled == 'T' && $campaign_active_user == 'T') // pause
        {
            if($paymentterm != $campaign_paymentterm)
            {
                $resCode = 422;
                $resSts = 'error';
                $resMsg = "Campaign Is Paused, You Cannot Change Campaign Type";
                $this->inserLog($request, $resCode, $resSts, $resMsg, $func, $companyID);
                return response()->json(['status' => $resSts, 'message' => $resMsg, 'status_code' => $resCode], $resCode); 
            }
            else if($paymentterm == 'prepaid' && $prepaidtype != $campaign_topupoptions)
            {
                $resCode = 422;
                $resSts = 'error';
                $resMsg = "Campaign Is Paused, You Cannot Change Prepaid Type";
                $this->inserLog($request, $resCode, $resSts, $resMsg, $func, $companyID);
                return response()->json(['status' => $resSts, 'message' => $resMsg, 'status_code' => $resCode], $resCode);
            }

            $rules['setup_fee'] = ['required','numeric','min:0'];
            $rules['campaign_fee'] = ['required','numeric','min:0'];
            $rules['cost_per_lead'] = ['required','numeric','min:0'];
        }
        else if(($campaign_active == 'T' && $campaign_disabled == 'F' && $campaign_active_user == 'T') || ($campaign_active == 'F' && $campaign_disabled == 'F' && $campaign_active_user == 'T')) // play or paused on play
        {
            if($paymentterm != $campaign_paymentterm)
            {
                $resCode = 422;
                $resSts = 'error';
                $resMsg = "Campaign Is Play, You Cannot Change Campaign Type";
                $this->inserLog($request, $resCode, $resSts, $resMsg, $func, $companyID);
                return response()->json(['status' => $resSts, 'message' => $resMsg, 'status_code' => $resCode], $resCode);
            }
            else if($paymentterm == 'prepaid' && $prepaidtype != $campaign_topupoptions)
            {
                $resCode = 422;
                $resSts = 'error';
                $resMsg = "Campaign Is Play, You Cannot Change Prepaid Type";
                $this->inserLog($request, $resCode, $resSts, $resMsg, $func, $companyID);
                return response()->json(['status' => $resSts, 'message' => $resMsg, 'status_code' => $resCode], $resCode);
            }
        }
        else 
        {
            $resCode = 422;
            $resSts = 'error';
            $resMsg = "Invalid Campaign Status";
            $this->inserLog($request, $resCode, $resSts, $resMsg, $func, $companyID);
            return response()->json(['status' => $resSts, 'message' => $resMsg, 'status_code' => $resCode], $resCode);
        }
        // create rules based on campaign status

        if($paymentterm == 'prepaid')
        {
            $rules['setup_fee'] = ['required','numeric','min:0'];
            $rules['campaign_fee'] = ['required','numeric','min:0'];
            $rules['cost_per_lead'] = ['required','numeric','min:0'];

            if($prepaidtype == 'continual')
            {
                $rules['continual_buy_options'] = ['required','in:weekly,monthly'];

                if($campaign_leadspeektype == 'local' && $request->has('limit_frequency'))
                {
                    $rules['limit_frequency'] = ['required', 'in:day,month'];
                }
            }
            else if($prepaidtype == 'onetime')
            {
                $rules['lead_buy'] = ['required','integer','min:50'];
            }
            else 
            {
                $resCode = 422;
                $resSts = 'error';
                $resMsg = "Prepaid Type Must Be continual Or onetime";
                $this->inserLog($request, $resCode, $resSts, $resMsg, $func, $companyID);
                return response()->json(['status' => $resSts, 'message' => $resMsg, 'status_code' => $resCode], $resCode);
            }
        }

        $validator = Validator::make($request->all(), $rules);

        if($validator->fails())
        {
            $dtMsg = json_decode($validator->messages(), true);
            $tx_validator = $this->get_tx_validator($dtMsg);
            
            $resCode    = 422;
            $resSts     = 'error';
            $resMsg     = $tx_validator;

            return response()->json([
                'status' => $resSts, 
                'message' => $resMsg,
                'status_code' => $resCode
            ], $resCode);
        }

        // check permission agency sidebar
        $agencySidebar = $this->getcompanysetting($companyID, 'agencysidebar');
        $selectedModules = $agencySidebar->SelectedModules ?? "";

        if(is_array($selectedModules) && count($selectedModules) > 0)
        {
            $filterAgencySidebar = array_filter($selectedModules, function ($item) use ($campaign_leadspeektype) {
                return strtolower($item->type) == $campaign_leadspeektype;
            });

            $type = array_values($filterAgencySidebar)[0]->type ?? "";
            $status = array_values($filterAgencySidebar)[0]->status ?? false;

            // Log::info('', ['filterAgencySidebar' => $filterAgencySidebar,'type' => $type,'status' => $status, ]);

            if($status === false)
            {
                $resMsg = "Update Price For Campaign $campaignid In Modules $type Is Not Permitted";
                $resCode = 422;
                $this->inserLog($request, $resCode, 'error', $resMsg, $func, $companyID);
                return response()->json(['status' => 'error','message' => $resMsg,'status_code' => $resCode], $resCode);
            }
        }
        // check permission agency sidebar
        

        // check permission paymentterm agency
        $companyID = $openApiToken->company_id ?? "";
        $agencyPaymentTerm = $this->getcompanysetting($companyID, 'agencypaymentterm');
        $selectedPaymentTerm = $agencyPaymentTerm->SelectedPaymentTerm ?? "";

        // Log::info('', [
        //     'agencyPaymentTerm' => $agencyPaymentTerm,
        //     'selectedPaymentTerm' => $selectedPaymentTerm,
        //     'is_array_selected_paymentterm' => is_array($selectedPaymentTerm)
        // ]);

        if(is_array($selectedPaymentTerm) && count($selectedPaymentTerm) > 0)
        {
            $filterAgencyPaymentTerm = array_filter($selectedPaymentTerm, function ($item) use ($paymentterm) {
                return strtolower($item->term) == $paymentterm;
            });

            $term = array_values($filterAgencyPaymentTerm)[0]->term ?? "Weekly";
            $status = array_values($filterAgencyPaymentTerm)[0]->status ?? true;

            // Log::info('', [
            //     'filterAgencyPaymentTerm' => $filterAgencyPaymentTerm,
            //     'term' => strtolower($term),
            //     'paymentterm' => $paymentterm,
            //     'campaign_paymentterm' => $campaign_paymentterm,
            //     'status' => $status
            // ]);

            // jika status dari paymenht term agency false dan campaign paymentterm sebelumnya tidak sama dengan term agency yang false
            if($status == false && $campaign_paymentterm != strtolower($term))
            {
                $resCode = 422;
                $resSts = 'error';
                $resMsg = "Update Pricing For $paymentterm Payment Terms Is Not Permitted";
                $this->inserLog($request, $resCode, $resSts, $resMsg, $func, $companyID);
                return response()->json(['status' => $resSts, 'message' => $resMsg, 'status_code' => $resCode], $resCode);
            }
        }
        // check permission paymentterm agency
        /* VALIDATOR */

        /* ATTRIBUTE */
        $ClientID = $campaign->id ?? "";
        $ModuleName = "LeadsPeek";
        $CostSet = $request->cost_per_lead;
        $CostMonth = $request->campaign_fee;
        $CostMaxLead = 0;
        $LimitLead = $request->lead_per_day;
        $LimitLeadFreq = "day";
        $LimitLeadStart = date('Y-m-d');
        $LimitLeadEnd = null;
        $PaymentTerm = ucwords($paymentterm);
        $contiDurationSelection = isset($campaign->continual_buy_options) ? ($campaign->continual_buy_options == "Weekly" ? false : true) : false;
        $topupoptions = "";
        $leadsbuy = $campaign->leadsbuy ?? 50;
        $PlatformFee = $request->setup_fee;
        $LeadspeekApiId = $campaignid;
        $idUser = $campaign->agency_id ?? "";
        $ipAddress = "in-open-api";
        $idSys = $campaign->company_root_id ?? "";
        /* ATTRIBUTE */

        /* IF ENHANCE CHECK CLIENTCAP AND MINIMUM LEAD */
        // progress
        $leadspeekType = $campaign->leadspeek_type ?? "local";
        // Log::info('', [
        //     'leadspeekType' => $leadspeekType,
        //     'campaign_active' => $campaign_active,
        //     'campaign_disabled' => $campaign_disabled,
        //     'campaign_active_user' => $campaign_active_user
        // ]);
        if(in_array($leadspeekType, ['enhance', 'b2b']))
        {
            // Log::info("start check clientcap and clientminlead");
            
            /* CHECK CLIENTCAP */
            $clientCap = $this->getClientCapType($idSys);
            // Log::info('', [
            //     'action' => 'check clientcap',
            //     'clientCap' => $clientCap
            // ]);
            
            if($clientCap['type'] == 'clientcaplead')
            {
                // Log::info('', [
                //     'action' => 'check clientcap block 1',
                //     'CostSet' => $CostSet,
                //     'value' => $clientCap['value'],
                // ]);
                // cost per lead tidak lebih besar dari $clientCap['value'] cost per lead should not be more than 0.8
                if($CostSet > $clientCap['value'])
                {
                    $resCode = 422;
                    $resSts = 'error';
                    $resMsg = "If Campaign In Module Enhance, Cost Per Lead Should Not Be More Than $" . $clientCap['value'];
                    $this->inserLog($request, $resCode, $resSts, $resMsg, $func, $companyID);
                    return response()->json(['status' => $resSts, 'message' => $resMsg, 'status_code' => $resCode], $resCode);
                }
            }
            else 
            {
                // cost per lead minimum harus sama dengan costagency atau 0
                $rootCostAgency = $this->getcompanysetting($idSys, 'rootcostagency');
                $costAgency = $this->getcompanysetting($companyID, 'costagency');

                $agencyCostperlead = 0;
                $typeCostPerlead = ($leadspeekType == 'enhance') ? "EnhanceCostperlead" : "B2bCostperlead";
                if (isset($costAgency->$leadspeekType->$PaymentTerm->$typeCostPerlead))
                    $agencyCostperlead = $costAgency->$leadspeekType->$PaymentTerm->$typeCostPerlead;
                else if (isset($rootCostAgency->$leadspeekType->$PaymentTerm->$typeCostPerlead))
                    $agencyCostperlead = $rootCostAgency->$leadspeekType->$PaymentTerm->$typeCostPerlead;

                // Log::info('', ['action' => 'check clientcap block 2','CostSet' => $CostSet,'rootCostAgency' => $rootCostAgency,'costAgency' => $costAgency,'agencyCostperlead' => $agencyCostperlead,]);

                if($CostSet < $agencyCostperlead && $CostSet != 0)
                {
                    $resCode = 422;
                    $resSts = 'error';
                    $resMsg = "If Campaign In Module " . ($leadspeekType == 'enhance' ? 'Enhance' : 'B2B') . ", Minimum Cost Per Lead Is $$agencyCostperlead ,But It Can Also Be $0";
                    $this->inserLog($request, $resCode, $resSts, $resMsg, $func, $companyID);
                    return response()->json(['status' => $resSts, 'message' => $resMsg, 'status_code' => $resCode], $resCode);
                }
            }
            /* CHECK CLIENTCAP */

            /* CHECK CLIENT MIN LEAD */
            $_data = new Request(['idSys' => $idSys]);
            $getminleaddayenhance = $this->configurationController->getminleaddayenhance($_data)->getData();
            $clientMinLeadDayEnhance = $getminleaddayenhance->clientMinLeadDayEnhance ?? "";

            // Log::info('', [
            //     'action' => 'check clientminlead',
            //     'getminleaddayenhance' => $getminleaddayenhance,
            //     'clientMinLeadDayEnhance' => $clientMinLeadDayEnhance
            // ]);

            if(trim($clientMinLeadDayEnhance) != '' && $LimitLead < $clientMinLeadDayEnhance)
            {
                $resCode = 422;
                $resSts = 'error';
                $resMsg = "If Campaign In Module" . ($leadspeekType == 'enhance' ? 'Enhance' : 'B2B') . ", Minimum Lead Per Day Is $clientMinLeadDayEnhance";
                $this->inserLog($request, $resCode, $resSts, $resMsg, $func, $companyID);
                return response()->json(['status' => $resSts, 'message' => $resMsg, 'status_code' => $resCode], $resCode);
            }
            /* CHECK CLIENT MIN LEAD */
        }
        /* IF ENHANCE CHECK CLIENTCAP AND MINIMUM LEAD */

        if($campaign_leadspeektype == 'local' && $paymentterm == 'prepaid' && $prepaidtype == 'continual')
        {
            /* OVERRIDE LimitLeadFreq FOR SITE ID, ONLY STATUS STOPPED */
            $LimitLeadFreq = $campaign->lp_limit_freq ?? 'day';
            
            if($request->has('limit_frequency'))
            {
                // validate update limit frequency, only campaign status stopped
                if(($LimitLeadFreq != $request->limit_frequency) && !($campaign_active == 'F' && $campaign_disabled == 'T' && $campaign_active_user == 'F')) // (freqInDb != FreqInRequest) && !(stopped)
                {
                    $resCode = 422;
                    $resSts = 'error';
                    $resMsg = "You cannot update the campaign limit frequency from {$LimitLeadFreq} to {$request->limit_frequency}. Ensure the campaign has been stopped before making this change.";
                    $this->inserLog($request, $resCode, $resSts, $resMsg, $func, $companyID);
                    return response()->json(['status' => $resSts, 'message' => $resMsg, 'status_code' => $resCode], $resCode);
                }
                // validate update limit frequency, only campaign status stopped
    
                $LimitLeadFreq = $request->limit_frequency ?? 'day';
            }
            /* OVERRIDE LimitLeadFreq FOR SITE ID, ONLY STATUS STOPPED */
        
            if($LimitLeadFreq == 'month' && $LimitLead < 50)
            {
                $resCode = 422;
                $resSts = 'error';
                $resMsg = "For monthly frequency, the minimum value for the 'lead_per_day' field must be 50.";
                $this->inserLog($request, $resCode, $resSts, $resMsg, $func, $companyID);
                return response()->json(['status' => $resSts, 'message' => $resMsg, 'status_code' => $resCode], $resCode);
            }
        }

        /* VALIDATION */

        /* PROCESS SET PRICE CAMPAIGN */
        $data = [];

        if($paymentterm == 'weekly') 
        {
            // override attribute if weekly and status active, platformfee and cost month and platform fee, use prev value
            if(($campaign_active == 'T' && $campaign_disabled == 'F' && $campaign_active_user == 'T') || ($campaign_active == 'F' && $campaign_disabled == 'F' && $campaign_active_user == 'T'))
            {
                $campaign_platformfee = $campaign->platformfee ?? 0;
                $campaign_cost_month = $campaign->lp_min_cost_month ?? 0;
                $campaign_costperlead = $campaign->cost_perlead ?? 0;

                $PlatformFee = $campaign_platformfee;
                $CostMonth = $campaign_cost_month;
                $CostSet = $campaign_costperlead;
            }
            // override attribute if weekly and status active, platformfee and cost month and platform fee, use prev value

            $data = new Request([
                "companyID" => $companyID,
                "ClientID" => $ClientID,
                "ModuleName" => $ModuleName,
                "CostSet" => $CostSet,
                "CostMonth" => $CostMonth,
                "CostMaxLead" => $CostMaxLead,
                "LimitLead" => $LimitLead,
                "LimitLeadFreq" => $LimitLeadFreq,
                "LimitLeadStart" => $LimitLeadStart,
                "LimitLeadEnd" => $LimitLeadEnd,
                "PaymentTerm" => $PaymentTerm,
                "contiDurationSelection" => $contiDurationSelection,
                "topupoptions" => $topupoptions,
                "leadsbuy" => $leadsbuy,
                "PlatformFee" => $PlatformFee,
                "LeadspeekApiId" => $LeadspeekApiId,
                "idUser" => $idUser,
                "ipAddress" => $ipAddress,
                "idSys" => $idSys, 
            ]);
        }
        else if($paymentterm == 'monthly')
        {
            // override attribute if monthly and status active, platformfee and cost month and platform fee, use prev value
            if(($campaign_active == 'T' && $campaign_disabled == 'F' && $campaign_active_user == 'T') || ($campaign_active == 'F' && $campaign_disabled == 'F' && $campaign_active_user == 'T'))
            {
                $campaign_platformfee = $campaign->platformfee ?? 0;
                $campaign_cost_month = $campaign->lp_min_cost_month ?? 0;
                $campaign_costperlead = $campaign->cost_perlead ?? 0;

                $PlatformFee = $campaign_platformfee;
                $CostMonth = $campaign_cost_month;
                $CostSet = $campaign_costperlead;
            }
            // override attribute if monthly and status active, platformfee and cost month and platform fee, use prev value

            $data = new Request([
                "companyID" => $companyID,
                "ClientID" => $ClientID,
                "ModuleName" => $ModuleName,
                "CostSet" => $CostSet,
                "CostMonth" => $CostMonth,
                "CostMaxLead" => $CostMaxLead,
                "LimitLead" => $LimitLead,
                "LimitLeadFreq" => $LimitLeadFreq,
                "LimitLeadStart" => $LimitLeadStart,
                "LimitLeadEnd" => $LimitLeadEnd,
                "PaymentTerm" => $PaymentTerm,
                "contiDurationSelection" => $contiDurationSelection,
                "topupoptions" => $topupoptions,
                "leadsbuy" => $leadsbuy,
                "PlatformFee" => $PlatformFee,
                "LeadspeekApiId" => $LeadspeekApiId,
                "idUser" => $idUser,
                "ipAddress" => $ipAddress,
                "idSys" => $idSys, 
            ]);
        }
        else if($paymentterm == 'prepaid' && $prepaidtype == 'continual')
        {
            /* OVERRIDE ATTRIBUTE IF PREPAID CONTINUAL */
            $topupoptions = $prepaidtype;
            
            // normal leadsbuy
            $contiDurationSelection = ($request->continual_buy_options == "weekly") ? false : true;
            $leadsbuy = ($request->continual_buy_options == "weekly") ? ($request->lead_per_day * 7) : ($request->lead_per_day * 7 * 4);
            
            // local and limit monthly leadsbuy 
            if($campaign_leadspeektype == 'local' && $LimitLeadFreq == 'month')
            {
                $contiDurationSelection = true;
                $leadsbuy = $LimitLead;
            }
            /* OVERRIDE ATTRIBUTE IF PREPAID CONTINUAL */

            $data = new Request([
                "companyID" => $companyID,
                "ClientID" => $ClientID,
                "ModuleName" => $ModuleName,
                "CostSet" => $CostSet,
                "CostMonth" => $CostMonth,
                "CostMaxLead" => $CostMaxLead,
                "LimitLead" => $LimitLead,
                "LimitLeadFreq" => $LimitLeadFreq,
                "LimitLeadStart" => $LimitLeadStart,
                "LimitLeadEnd" => $LimitLeadEnd,
                "PaymentTerm" => $PaymentTerm,
                "contiDurationSelection" => $contiDurationSelection,
                "topupoptions" => $topupoptions,
                "leadsbuy" => $leadsbuy,
                "PlatformFee" => $PlatformFee,
                "LeadspeekApiId" => $LeadspeekApiId,
                "idUser" => $idUser,
                "ipAddress" => $ipAddress,
                "idSys" => $idSys,
            ]);
        }
        else if($paymentterm == 'prepaid' && $prepaidtype == 'onetime')
        {
            // override attribute if prepaid onetime
            $topupoptions = $prepaidtype;
            $leadsbuy = $request->lead_buy;
            // override attribute if prepaid onetime

            $data = new Request([
                "companyID" => $companyID,
                "ClientID" => $ClientID,
                "ModuleName" => $ModuleName,
                "CostSet" => $CostSet,
                "CostMonth" => $CostMonth,
                "CostMaxLead" => $CostMaxLead,
                "LimitLead" => $LimitLead,
                "LimitLeadFreq" => $LimitLeadFreq,
                "LimitLeadStart" => $LimitLeadStart,
                "LimitLeadEnd" => $LimitLeadEnd,
                "PaymentTerm" => $PaymentTerm,
                "contiDurationSelection" => $contiDurationSelection,
                "topupoptions" => $topupoptions,
                "leadsbuy" => $leadsbuy,
                "PlatformFee" => $PlatformFee,
                "LeadspeekApiId" => $LeadspeekApiId,
                "idUser" => $idUser,
                "ipAddress" => $ipAddress,
                "idSys" => $idSys,
            ]);
        }
        else 
        {
            $resCode = 400;
            $resSts = 'error';
            $resMsg = "Incorrect Payment Term Or Prepaid Type Configuration";
            $this->inserLog($request, $resCode, $resSts, $resMsg, $func, $companyID);
            return response()->json(['status' => $resSts, 'message' => $resMsg, 'status_code' => $resCode], $resCode);
        }

        try
        {
            $updatePrice = $this->configurationController->costmodule($data);
            // Log::info('', ['updatePrice' => $updatePrice->getData()]);
        }
        catch(\Exception $e)
        {
            $resCode = 500;
            $resSts = 'error';
            $resMsg = $e->getMessage();
            $this->inserLog($request, $resCode, $resSts, $resMsg, $func, $companyID);
            return response()->json(['status' => $resSts, 'message' => $resMsg, 'status_code' => $resCode], $resCode);
        }
        /* PROCESS SET PRICE CAMPAIGN */

        /* USER LOGS */
        $descriptionLogs = "";
        $campaignLogs = LeadspeekUser::select(
                    'leadspeek_users.id','leadspeek_users.continual_buy_options','leadspeek_users.topupoptions',
                    'leadspeek_users.leadsbuy','leadspeek_users.active','leadspeek_users.disabled',
                    'leadspeek_users.active_user','leadspeek_users.paymentterm','leadspeek_users.platformfee',
                    'leadspeek_users.lp_min_cost_month','leadspeek_users.cost_perlead','leadspeek_users.leadspeek_type',
                    'leadspeek_users.lp_limit_freq','leadspeek_users.lp_limit_leads','leadspeek_users.lp_enddate',
                    'leadspeek_users.enable_minimum_limit_leads','leadspeek_users.minimum_limit_leads',
                    'users.id as agency_id','users.company_root_id')
            ->join('users','users.company_id','=','leadspeek_users.company_id')
            ->where('leadspeek_users.leadspeek_api_id', $campaignid)
            ->where('leadspeek_users.company_id', $companyID)
            ->where('leadspeek_users.archived', 'F')
            ->where('users.user_type','userdownline')
            ->first();
        $updates = [
            [ 
                'label' => "Billing Frequency", 
                'prev' => $prevCampaignFinancial['billingFrequency'] ?? "", 
                'new' => $campaignLogs->paymentterm ?? "", 
            ],
            [ 
                'label' => "Duration Continual Selection", 
                'prev' => ($prevCampaignFinancial['contiDurationSelection'] ?? ""), 
                'new' => $campaignLogs->continual_buy_options ?? "",
            ],
            [ 
                'label' => "Top Up Options", 
                'prev' => $prevCampaignFinancial['topUpOptions'] ?? "", 
                'new' => $campaignLogs->topupoptions ?? "",
            ],
            [ 
                'label' => "Set Up Fee", 
                'prev' => $prevCampaignFinancial['setupFee'] ?? "", 
                'new' => $campaignLogs->platformfee ?? '',
            ],
            [ 
                'label' => "Campaign Fee", 
                'prev' => $prevCampaignFinancial['campaignFee'] ?? "", 
                'new' => $campaignLogs->lp_min_cost_month ?? "", 
            ],
            [ 
                'label' => "Cost Per Lead", 
                'prev' => $prevCampaignFinancial['costPerLead'] ?? "", 
                'new' => $campaignLogs->cost_perlead ?? "",
            ],
            [ 
                'label' => "Limit Leads Per {$lp_limit_freq}", 
                'prev' => $prevCampaignFinancial['leadPerDay'] ?? "", 
                'new' => $campaignLogs->lp_limit_leads ?? "",
            ],
            [ 
                'label' => "End Date", 
                'prev' => $prevCampaignFinancial['endDate'] ?? "",
                'new' => $campaignLogs->lp_enddate ?? "",
            ],
            [ 
                'label' => "Enable Minimum Leads Per Day", 
                'prev' => $prevCampaignFinancial['enableMinimumLeadPerDay'] ?? "", 
                'new' => $campaignLogs->enable_minimum_limit_leads ?? '',
            ],
            [ 
                'label' => "Minimum Leads Per Day", 
                'prev' => $prevCampaignFinancial['minimumLeadPerDay'] ?? "", 
                'new' => $campaignLogs->minimum_limit_leads ?? '',
            ],
        ];
        if($campaign_leadspeektype == 'local'){
            $updates[] = [ 
                'label' => "How Many Contact", 
                'prev' => $prevCampaignFinancial['lpLimitFreq'] ?? "", 
                'new' => $campaignLogs->lp_limit_freq ?? "", 
            ];
        }
        foreach($updates as $row) {
            $label = $row['label'];
            $prev = $row['prev'];
            $new = $row['new'];
            if($prev !== $new) {
                // Special case seperti di JS
                if($label === 'How Many Contact'){
                    $descriptionLogs .= "{$label} : Update values from Limit per {$prev} to Limit per {$new} | ";
                }else{
                    $descriptionLogs .= "{$label} : Update values from {$prev} to {$new} | ";
                }
            }else{
                $descriptionLogs .= "{$label} : No changes, value remains {$prev} | ";
            }
        }
        $descriptionLogs = rtrim(trim($descriptionLogs), "|");
        $descriptionLogs = "Campaign Id : {$campaignid} | {$descriptionLogs}";
        $userIDAgency = User::where('company_id', $companyID)->where('user_type', 'userdownline')->value('id');
        $campaignTypeFormat = ['local' => 'Local', 'enhance' => 'Enhance', 'b2b' => 'B2B'][$campaign_leadspeektype] ?? "";
        $this->logUserAction($userIDAgency, trim("Edit Campaign Financial {$campaignTypeFormat}"), $descriptionLogs, "in-open-api", $userIDAgency);
        /* USER LOGS */

        $resCode = 200;
        $resSts = 'success';
        $resMsg = "Update Price Campaign Successfully.";
        $this->inserLog($request, $resCode, $resSts, $resMsg, $func, $companyID);
        return response()->json([
            'status' => $resSts, 
            'message' => $resMsg, 
            'status_code' => $resCode,
            // 'data' => $data->toArray()
        ], $resCode);
    }

    /**
     * Update Status Campaign (running,paused,stopped)
     * @return \Illuminate\Http\JsonResponse
     */
    public function UpdateStatusCampaignOpenApi(Request $request, string $campaignid, string $campaignstatus)
    {
        $openApiToken = $request->attributes->get('openApiToken');
        $companyID = $openApiToken->company_id ?? "";
        $func = __FUNCTION__;

        /* VALIDATION ONLY AGENCY */
        $response = $this->openApiValidationService->validationTokenOnlyAgency($companyID);
        // info('validationTokenOnlyAgency', ['response' => $response]);
        if(($response['status'] ?? '') == 'error'){
            $resCode = $response['status_code'] ?? 400;
            $resSts = $response['status'];
            $resMsg = $response['message'] ?? "Something Went Wrong";
            $this->inserLog($request, $resCode, $resSts, $resMsg, $func, $companyID);
            return response()->json(['status' => $resSts, 'message' => $resMsg, 'status_code' => $resCode], $resCode);
        }
        /* VALIDATION ONLY AGENCY */

        /* PROCSS CREATE INTEGRATIONS CAMPAIGN */
        $response = $this->openApiCampaignStatusService->updateStatusCampaign($campaignid, $campaignstatus, $companyID, $request);
        // info('updateStatusCampaign', ['response' => $response]);
        if(($response['status'] ?? '') == 'error'){
            $resCode = $response['status_code'] ?? 400;
            $resSts = $response['status'];
            $resMsg = $response['message'] ?? "Something Went Wrong";
            $this->inserLog($request, $resCode, $resSts, $resMsg, $func, $companyID);
            return response()->json(['status' => $resSts, 'message' => $resMsg, 'status_code' => $resCode], $resCode);
        }
        $resMsg = $response['message'] ?? "Successfully Update Status Campaign";
        /* PROCSS CREATE INTEGRATIONS CAMPAIGN */

        /* RETURN */
        $resCode = 200;
        $resSts = 'success';
        $this->inserLog($request, $resCode, $resSts, $resMsg, $func, $companyID);
        return response()->json(['status' => $resSts, 'message' => $resMsg, 'status_code' => $resCode], $resCode);
        /* RETURN */
    }

    /**
     * @return \Illuminate\Http\Response
     */
    public function getEmbedCode(Request $request, string $campaignid)
    {
        /* GET ATTRIBUTE */
        $openApiToken = $request->attributes->get('openApiToken');
        /* GET ATTRIBUTE */

        $func = __FUNCTION__;

        // check user type must be agency
        $companyID = $openApiToken->company_id;
        $companyIDRoots = User::where('company_parent',null)
                              ->pluck('company_id')
                              ->toArray();

        if(in_array($companyID, $companyIDRoots))
        {
            $resMsg = "Your Token Does Not Have The Ability To Get Embed Code Campaigns";
            $resCode = 422;
            $this->inserLog($request, $resCode, 'error', $resMsg, $func, $companyID);
            return response($resMsg, $resCode)->header('Content-Type', 'application/javascript');
        }
        // check user type must be agency

        // check permission agency sidebar
        $leadspeekType = 'local';
        $agencySidebar = $this->getcompanysetting($companyID, 'agencysidebar');
        $selectedModules = $agencySidebar->SelectedModules ?? "";

        if(is_array($selectedModules) && count($selectedModules) > 0)
        {
            $filterAgencySidebar = array_filter($selectedModules, function ($item) use ($leadspeekType) {
                return strtolower($item->type) == $leadspeekType;
            });

            $type = array_values($filterAgencySidebar)[0]->type ?? "";
            $status = array_values($filterAgencySidebar)[0]->status ?? false;

            // Log::info('', ['filterAgencySidebar' => $filterAgencySidebar,'type' => $type,'status' => $status,]);

            if($status === false)
            {
                $resMsg = "Get Embed Code For Campaign $campaignid In Modules $type Is Not Permitted";
                $resCode = 422;
                $this->inserLog($request, $resCode, 'error', $resMsg, $func, $companyID);
                return response($resMsg, $resCode)->header('Content-Type', 'application/javascript');
            }
        }
        // check permission agency sidebar

        // get company agency
        $company = Company::where('id', $companyID)
                          ->first();
        $domain = $company->subdomain ?? ""; 
        // get company agency

        // get campaign
        $campaign = LeadspeekUser::select('leadspeek_users.*')
                                 ->join('users','users.id','=','leadspeek_users.user_id')
                                 ->where('leadspeek_users.leadspeek_api_id', $campaignid)
                                 ->where('leadspeek_users.company_id', $companyID)
                                 ->where('leadspeek_users.archived', 'F')
                                 ->where('users.active', 'T')
                                 ->first();
        // info('', ['campaign' => $campaign]);
        if(empty($campaign))
        {
            $resMsg = "Campaign Not Found";
            $resCode = 404;
            $this->inserLog($request, $resCode, 'error', $resMsg, $func, $companyID);
            return response($resMsg, $resCode)->header('Content-Type', 'application/javascript');
        }
        if($campaign->leadspeek_type != $leadspeekType)
        {
            $resMsg = "Campaign Type Must Be Local";
            $resCode = 422;
            $this->inserLog($request, $resCode, 'error', $resMsg, $func, $companyID);
            return response($resMsg, $resCode)->header('Content-Type', 'application/javascript');
        }
        // get campaign

        // build embeded code
        $codeembeded = "";
        if($campaign->trysera == 'T')
        {
            // info('masuk if');
            $codeembeded = '<script>';
            $codeembeded .= 'var ts = {';
            $codeembeded .= 'c: "14798651632618831906",';
            $codeembeded .= 'd: "oi.0o0o.io",';
            $codeembeded .= 's: ' . $campaignid . ',';
            $codeembeded .= '};';
            $codeembeded .= 'if("undefined"!=typeof ts){var url="//";ts.hasOwnProperty("d")?url+=ts.d:url+="oi.0o0o.io",url+="/ts.min.js",function(e,t,n,a,r){var o,s,d;e.ts=e.ts||[],o=function(){e.ts=ts},(s=t.createElement(n)).src=a,s.async=1,s.onload=s.onreadystatechange=function(){var e=this.readyState;e&&"loaded"!==e&&"complete"!==e||(o(),s.onload=s.onreadystatechange=null)},(d=t.getElementsByTagName(n)[0]).parentNode.insertBefore(s,d)}(window,document,"script",url)}';
            $codeembeded .= '</script>';
            $codeembeded .= '<noscript>';
            $codeembeded .= '<img src="https://oi.0o0o.io/i/14798651632618831906/s/' . $campaignid . '/tsimg.png" width="1" height="1" style="display:none;overflow:hidden">';
            $codeembeded .= '</noscript>';
            // info("codeembeded : " . $codeembeded);

            $resMsg = "Successfully Get Embed Code"; 
            $resCode = 200;
            $this->inserLog($request, $resCode, 'error', $resMsg, $func, $companyID);
            return response($codeembeded, $resCode)->header('Content-Type', 'application/javascript');
        }
        else
        {
            // info('masuk else');
            // get agency white label domian
            $data = new Request([
                'company_id' => $companyID
            ]);
            $response = $this->companyController->get_agency_whitelabel_domain($data)->getData();
            $result = $response->result ?? "";
            $message = $response->message ?? "Something went wrong. Please try again later";
            $data = $response->data ?? "";
            
            if($result != 'success')
            {
                $resMsg = $message;
                $resCode = 400;
                $this->inserLog($request, $resCode, 'error', $resMsg, $func, $companyID);
                return response($codeembeded, $resCode)->header('Content-Type', 'application/javascript');
            }

            if(!empty($data) && trim($data))
                $domain = $data;

            if(empty($domain))
            {
                $resMsg = 'Subdomain Or Domain Agency Not Found';
                $resCode = 400;
                $this->inserLog($request, $resCode, 'error', $resMsg, $func, $companyID);
                return response($codeembeded, $resCode)->header('Content-Type', 'application/javascript');
            }
            // get agency white label domian

            // get px min js
            $jspx = "px.min.js";
            if(config('services.appconf.devmode') === true)
                $jspx = "px-sandbox.min.js";
            // get px min js

            $codeembeded = '<script>';
            $codeembeded .= '(function(doc, tag, id){';
            $codeembeded .= 'var js = doc.getElementsByTagName(tag)[0];';
            $codeembeded .= 'if (doc.getElementById(id)) {return;}';
            $codeembeded .= 'js = doc.createElement(tag); js.id = id;';
            $codeembeded .= 'js.src = "https://' . $domain . '/' . $jspx . '";';
            $codeembeded .= 'js.type = "text/javascript";';
            $codeembeded .= 'doc.head.appendChild(js);';
            $codeembeded .= 'js.onload = function() {pxfired();};';
            $codeembeded .= "}(document, 'script', 'px-grabber'));";
            // codeembeded .= 'window.addEventListener("load", function () {';
            $codeembeded .= 'function pxfired() {';
            $codeembeded .= 'PxGrabber.setOptions({';
            $codeembeded .= 'Label: "' . $campaignid . '|" + window.location.href,';
            $codeembeded .= '});';
            $codeembeded .= 'PxGrabber.render();';
            $codeembeded .= '};';
            $codeembeded .= '</script>';
            // codeembeded .= '});';

            $resMsg = "Successfully Get Embed Code"; 
            $resCode = 200;
            $this->inserLog($request, $resCode, 'error', $resMsg, $func, $companyID);
            return response($codeembeded, $resCode)->header('Content-Type', 'application/javascript');
        }
        // build embeded code
    }

    /**
     * Search Volume Estimate Tools Enhance ID With Open Api
     * @return \Illuminate\Http\JsonResponse
     */
    public function searchVolumeEstimate(Request $request)
    {
        /* GET ATTRIBUTE */
        $openApiToken = $request->attributes->get('openApiToken');
        /* GET ATTRIBUTE */

        // check user type must be agency
        $companyID = $openApiToken->company_id;
        $companyIDRoots = User::where('company_parent',null)
                              ->pluck('company_id')
                              ->toArray();

        if(in_array($companyID, $companyIDRoots))
            return response()->json([
                'status' => 'error', 
                'message' => 'Your Token Does Not Have The Ability To Search Volume Campaigns',
                'status_code' => 422
            ], 422);
        // check user type must be agency 

        // RULES VALIDATION 
        $rules = [
            'campaign_id' => ['required'],
            'email' => ['required', 'valid_email'],
            'keyword' => ['required'],
            'target_location' => ['numeric','required','in:1,2,3',]
        ];

        $campaignid = $request->campaign_id;
        $target_location = $request->target_location;
        $allowed_target = [1,2,3];

        if (!in_array($target_location, $allowed_target, true)) 
        {
            return response()->json([
                'status' => 'error',
                'message' => ['campaign' => ["Target Location didn't match.. use only 1, 2 or 3"]],
                'status_code' => 509
            ], 509);
        }

        // target_location => 1 = National Targeting; 2 = State; 3 = Zip Code 
        if($target_location == '2')
        {
            $rules['states'] = ['required'];
            $cekstates = $request->states;
            $cekstates_arr = array_map('trim', explode(",", $cekstates));
            if ( count($cekstates_arr) == 1) {
                $rules['cities'] = ['required'];
            }
        }
        else if($target_location == '3')
        {
            $rules['zip_codes'] = ['required'];
        }

        $validator = Validator::make($request->all(), $rules);

        if($validator->fails())
        {
            $dtMsg = json_decode($validator->messages(), true);
            $tx_validator = $this->get_tx_validator($dtMsg);
            $tx_validator = str_replace("validation.valid_email", "The email must be a valid email address", $tx_validator);
            
            $resCode    = 422;
            $resSts     = 'error';
            $resMsg     = $tx_validator;

            return response()->json([
                'status' => $resSts, 
                'message' => $resMsg,
                'status_code' => $resCode
            ], $resCode);
        }
        // END RULES VALIDATION 

        // get campaign
        $campaign = LeadspeekUser::select('id', 'user_id', 'leadspeek_type')
            ->where('leadspeek_users.leadspeek_api_id', $campaignid)
            ->where('leadspeek_users.archived', 'F')
            ->first();

        // Log::info('', [
        //     'campaign_id' => $campaignid,
        //     'company_id' => $companyID
        // ]);

        // dd($campaign);
        // log::info('aaaa : ' . $campaign->leadspeek_type);
        // return $campaign->user_id;
        // enhance

        if(empty($campaign))
            return response()->json([
                'status' => 'error',
                'message' => ['campaign' => ['Campaign Not Found']],
                'status_code' => 404
            ], 404);
        
        if($campaign->leadspeek_type !== 'enhance' && $campaign->leadspeek_type !== 'b2b')
            return response()->json([
                'status' => 'error',
                'message' => ['campaign' => ['Only Enhance Or B2B Campaign can be process Search Volume.']],
                'status_code' => 404
            ], 404);
        // get campaign

        // get data bigdbm queue
        $queue = BigDBMCountHistory::where('leadspeek_api_id', $campaignid)
                                        ->where('type','=','test')
                                        ->whereIn('status', ['queue', 'process'])
                                        ->exists();

        if($queue)
        return response()->json([
            'status' => 'error',
            'message' => ['campaign' => ['This Campaign has already make request Count Search Volume. Please wait while we process the results. This may take anywhere from 2 to 5 minutes or longer. ']],
            'status_code' => 509
        ], 509);
        // end get data bigdbm queue

        // $LeadspeekApiId     = $request->leadspeek_api_id;
        // $companyID          = $request->company_id;
        // $idUser             = $request->user_id;
        $email              = strtolower($request->email);
        $keyword            = $request->keyword;
        $states             = $request->states;
        $cities             = $request->cities;
        $zipcodes           = $request->zip_codes;

        $states_arr = null;
        $cities_arr = null;
        $zipcodes_new = null;
        $statecode_arr = [];
        $fix_keyword_arr = [];

        //// ceking keyword
        //// 1. maksimal 3 suku kata
        //// 2. maksimal keyword 500 karakter
        //// hapus otomatis kata dibawah :
        //// "&, a, all, and, are, at, for, in, is, no, of, on, that, the, this, to, with".
        //// cek bad character and delete
        $badchar = ['&', 'all', 'and', 'are', 'at', 'for', 'in', 'is', 'no', 'of', 'on', 'that', 'the', 'this', 'to', 'with'];
        $pattern = '/\b(' . implode('|', $badchar) . ')\b/i';
        $keyword = preg_replace($pattern, '', $keyword);
        // Hapus spasi ganda yang mungkin muncul
        $keyword = trim(preg_replace('/\s+/', ' ', $keyword));

        //// check all keyword if 500 character
        if(strlen($keyword) > 500)
        {
            return response()->json([
                'status' => 'error',
                'message' => ['keyword' => ['Total length all keyword max 500 character.']],
                'status_code' => 509
            ], 509);
        }

        $keyword_arr = array_map('trim', explode(",", $keyword));
        
        foreach ($keyword_arr as $kw) {
            $jml = str_word_count($kw);
            if($jml > 3)
            {
                return response()->json([
                    'status' => 'error',
                    'message' => ['keyword' => ['Keyword should not have more than 3 words']],
                    'status_code' => 509
                ], 509);
            }

            if($kw !== '')
            {
                array_push($fix_keyword_arr, $kw);
            }

        } 
        // return $fix_keyword_arr;

        // Log::info('aaaaaa',$keyword_arr);

        if($target_location == '1')
        {
            $target_location_tx = 'National Targeting';
        }
        else if($target_location == '2') 
        {
            $target_location_tx = 'State';
            $states_arr = array_map('trim', explode(",", $states));
            $cities_arr = array_map('trim', explode(",", $cities));

            //// untuk states ambil kode states nya saja (dari table states)
            
            foreach ($states_arr as $st) {
                $statecode = DB::select("select state_code from states where state = ?", [$st]);
                // echo $statecode[0]->state_code;
                if($statecode) {
                    array_push($statecode_arr, $statecode[0]->state_code);
                }
            } 

            // log::info('bbbbb',$cities_arr);
            if ( count($statecode_arr) > 1 && $cities && !empty($cities_arr) ) 
            {
                return response()->json([
                    'status' => 'error',
                    'message' => ['campaign' => ['You can input cities only if exactly one state.']],
                    'status_code' => 509
                ], 509);
            }

            if (count($statecode_arr) > count(array_unique($statecode_arr))) 
            {
                return response()->json([
                    'status' => 'error',
                    'message' => ['campaign' => ['States are duplicated.']],
                    'status_code' => 409
                ], 409);
            }

            if (count($cities_arr) > count(array_unique($cities_arr))) 
            {
                return response()->json([
                    'status' => 'error',
                    'message' => ['campaign' => ['Cities are duplicated.']],
                    'status_code' => 409
                ], 409);
            }
            
            if ( count($statecode_arr) > 5)
            {
                return response()->json([
                    'status' => 'error',
                    'message' => ['campaign' => ['You can input max 5 States.']],
                    'status_code' => 509
                ], 509);
            }
            if ( count($cities_arr) > 10)
            {
                return response()->json([
                    'status' => 'error',
                    'message' => ['campaign' => ['You can input max 10 Cities.']],
                    'status_code' => 509
                ], 509);
            }
        } 
        else if($target_location == '3')
        {
            $target_location_tx = 'Zip Code';
            $zipcodes_new = str_replace(',', "\n", $zipcodes);
            $zipcode_arr = array_map('trim', explode(",", $zipcodes));

            if (count($zipcode_arr) > count(array_unique($zipcode_arr))) 
            {
                return response()->json([
                    'status' => 'error',
                    'message' => ['campaign' => ['Zip Codes are duplicated.']],
                    'status_code' => 409
                ], 409);
            }
            
            if ( count($zipcode_arr) > 50)
            {
                return response()->json([
                    'status' => 'error',
                    'message' => ['campaign' => ['You can input max 50 States.']],
                    'status_code' => 509
                ], 509);
            }
        }

        // cek data ketika ada request dengan keyword dan target location sama dalam 7 hari (lewat 7 hari bisa) 
        // $cek_states = implode(',', $statecode_arr);

        // $exist_dt = BigDBMCountHistory::where('leadspeek_api_id', $campaignid)
        //                                 ->where('type','=','test')
                                        
        //                                 ->exists();

        // if($exist_dt)
        //     return response()->json([
        //         'status' => 'error',
        //         'message' => ['campaign' => ['This Campaign has already make request Count Search Volume. Please wait while we process the results. This may take anywhere from 2 to 5 minutes or longer. ']],
        //         'status_code' => 509
        //     ], 509);

        // end cek data ketika ada request dengan keyword dan target location sama dalam 7 hari (lewat 7 hari bisa) 

        // $qr_api = DB::select("SELECT COALESCE(MAX(request_api_id),0)+1 as next_id FROM bigdbm_count_history");
        // $api_id = $qr_api[0]->next_id
        // pake microtime aja biar kalo ada yg belum kesimpen dan request lagi, id nya beda
        $api_id = round(microtime(true) * 1000);

        $data = new Request([
            "campaign_id" => $campaign->id,
            "leadspeek_api_id" => $campaignid,
            "company_id" => $companyID,
            "user_id" => $campaign->user_id,
            "email" => $email,
            "target_location" => $target_location_tx,
            "keyword" => $fix_keyword_arr,
            "states" => $statecode_arr,
            "cities" => $cities_arr,
            "zip_code" => $zipcodes_new,
            "status" => "process",
            "req_api_id" => $api_id
        ]);

        
        try
        {
            $queue_count = $this->bigdbmController->queue_count($data);

            $queuecount = $queue_count->getData();

            $result = $queuecount->result ?? '';
            $errorMessage = $queuecount->message ?? '';

            if ($result == 'success')
            {
                return response()->json([
                    'status' => $result, 
                    'message' => $errorMessage,
                    'status_code' => 200,
                    'request_api_id' => $api_id
                ], 200);
            } else 
            {
                return response()->json([
                    'status' => $result, 
                    'message' => $errorMessage,
                    'status_code' => 509
                ], 509);
            }
            
            
        }
        catch(\Exception $e)
        {
            return response()->json([
                'status' => 'error', 
                'message' => $e->getMessage(),
                'status_code' => 500
            ], 500);
        }
        /* PROCESS Count Search Volume Enhance */

        // return response()->json([
        //     'status' => 'success',
        //     'message' => "Create Search Volume Successfully.",
        //     'status_code' => 200,
        //     // 'data' => $data->toArray()
        // ]);

    }

    /**
     * Check Status Search Volume Estimate Tools Enhance ID With Open Api
     * @return \Illuminate\Http\JsonResponse
     */
    public function statSearchVolume(Request $request)
    {
        /* GET ATTRIBUTE */
        $openApiToken = $request->attributes->get('openApiToken');
        /* GET ATTRIBUTE */

        // check user type must be agency
        $companyID = $openApiToken->company_id;
        $companyIDRoots = User::where('company_parent',null)
                              ->pluck('company_id')
                              ->toArray();

        if(in_array($companyID, $companyIDRoots))
            return response()->json([
                'status' => 'error', 
                'message' => 'Your Token Does Not Have The Ability To Search Volume Campaigns',
                'status_code' => 422
            ], 422);
        // check user type must be agency 

        // RULES VALIDATION 
        $rules = [
            'request_api_id' => ['required', 'numeric']
        ];

        $validator = Validator::make($request->all(), $rules);

        if($validator->fails())
        {
            $dtMsg = json_decode($validator->messages(), true);
            $tx_validator = $this->get_tx_validator($dtMsg);
            
            $resCode    = 422;
            $resSts     = 'error';
            $resMsg     = $tx_validator;

            return response()->json([
                'status' => $resSts, 
                'message' => $resMsg,
                'status_code' => $resCode
            ], $resCode);
        }
        // END RULES VALIDATION

        $req_api_id = $request->request_api_id;
        // get data bigdbm queue by req api id
        $queue = BigDBMCountHistory::where('request_api_id', $req_api_id)
        ->exists();

        if(!$queue)
            return response()->json([
            'status' => 'error',
            'message' => ['campaign' => ['Search Volume Not Found.']],
            'status_code' => 404
            ], 404);
        // get data bigdbm queue by req api id

        $api_id = DB::select("SELECT coalesce((select count(1) from bigdbm_count_history where request_api_id = ? and status = 'done'),0)/(select count(1) from bigdbm_count_history where request_api_id = ?)*100 as pct", [$req_api_id, $req_api_id]);

        if ($api_id)
        {
            if( $api_id[0]->pct < 100)
            {
                return response()->json([
                    'status' => 'success', 
                    'message' => 'Please Wait. Process Search Volume is '. $api_id[0]->pct . "%",
                    'status_code' => 200
                ], 200);
            }
            else if( $api_id[0]->pct == 100)
            {
                return response()->json([
                    'status' => 'success', 
                    'message' => 'Success. Process Search Volume is '. $api_id[0]->pct . "%",
                    'status_code' => 200
                ], 200);
            }
            
        } 
        else
        {
            return response()->json([
                'status' => 'error', 
                'message' => 'Something went wrong.',
                'status_code' => 500
            ], 500);
        }

    }

    /**
     * Get Data Search Volume Estimate Tools Enhance ID With Open Api
     * @return \Illuminate\Http\JsonResponse
     */
    public function getData(Request $request)
    {
        /* GET ATTRIBUTE */
        $openApiToken = $request->attributes->get('openApiToken');
        /* GET ATTRIBUTE */

        // check user type must be agency
        $companyID = $openApiToken->company_id;
        $companyIDRoots = User::where('company_parent',null)
                              ->pluck('company_id')
                              ->toArray();

        if(in_array($companyID, $companyIDRoots))
            return response()->json([
                'status' => 'error', 
                'message' => 'Your Token Does Not Have The Ability To Search Volume Campaigns',
                'status_code' => 422
            ], 422);
        // check user type must be agency 

        // RULES VALIDATION 
        $rules = [
            'request_api_id' => ['required', 'numeric']
        ];

        $validator = Validator::make($request->all(), $rules);

        if($validator->fails())
        {
            $dtMsg = json_decode($validator->messages(), true);
            $tx_validator = $this->get_tx_validator($dtMsg);
            
            $resCode    = 422;
            $resSts     = 'error';
            $resMsg     = $tx_validator;

            return response()->json([
                'status' => $resSts, 
                'message' => $resMsg,
                'status_code' => $resCode
            ], $resCode);
        }
        // END RULES VALIDATION

        $req_api_id = $request->request_api_id;
        // get data bigdbm queue by req api id
        $queue = BigDBMCountHistory::where('request_api_id', $req_api_id)->exists();

        if(!$queue)
            return response()->json([
            'status' => 'error',
            'message' => ['campaign' => ['Search Volume Not Found.']],
            'status_code' => 404
            ], 404);
        // get data bigdbm queue by req api id

        //check still running?
        $running = BigDBMCountHistory::where('request_api_id', $req_api_id)
        ->whereIn('status', ['queue', 'process'])
        ->exists();

        if($running)
            return response()->json([
            'status' => 'error',
            'message' => ['campaign' => ['Please Wait. Search Volume Still Running']],
            'status_code' => 509
            ], 509);
        // // end check still running
        
        // select("leadspeek_api_id", "request_api_id", "national_targeting",
        //                                         // "case when national_targeting = 0 then 'No' 
        //                                         // when national_targeting = 1 then 'yes' else '-' end as national_targeting",
        //                                         "states", "cities", "zip_code", "keyword", "distinct_count", "count_without_geo")

        $api_data = BigDBMCountHistory::select(DB::raw("leadspeek_api_id, request_api_id, 
                                                case when national_targeting = 0 then 'No' 
                                                when national_targeting = 1 then 'yes' else '-' end as national_targeting,
                                                case when status = 'done' then 'done' 
                                                when status = 'error' then 'Connection Error..' end as status,
                                                states, cities, zip_code, keyword, distinct_count, count_without_geo"))
                                        ->where('request_api_id', $req_api_id)->get();

        // log::info('aaaa : ' . json_encode($api_data));

        if ($api_data)
        {
            
            return response()->json([
                'status' => 'success', 
                'message' => 'Success get data Search Volume',
                'data' => $api_data,
                'status_code' => 200
            ], 200);
            
            
        } 
        else
        {
            return response()->json([
                'status' => 'error', 
                'message' => 'Something went wrong.',
                'status_code' => 500
            ], 500);
        }

    }

    /**
     * create user Admin
     * @return \Illuminate\Http\JsonResponse
     */
    private function createUserAdmin(string $usertype, Request $request)
    {
        $openApiToken = $request->attributes->get('openApiToken');

        /* GET VARIABLE */
        $resCode    = 200;
        $resSts     = null;
        $resMsg     = null;
        
        $userType                       = 'user';
        $user_id                        = '';
        $ClientCompanyName              = "";
        $ClientFullName                 = $request->full_name;
        $ClientEmail                    = $request->email ? strtolower($request->email) : '';
        $ClientPass                     = $request->password;
        $isSendEmail                    = ($request->is_send_email == 1) ? true : false;
        $ClientPhone                    = $request->phone_number;
        $ClientPhoneCountryCode         = $request->phone_country_code;
        $ClientPhoneCountryCallingCode  = $request->phone_country_calling_code;

        $adminNotif                     = $request->admin_notification;
        $customerCare                   = $request->customer_care;
        $defaultAdmin                   = $request->admin_default;
        $inOpenApi                      = true;
        /* GET VARIABLE */

        $companyID = $openApiToken->company_id;
        $companyIDRoots = User::where('company_parent',null)
                              ->pluck('company_id')
                              ->toArray();
        
        if(in_array($companyID, $companyIDRoots))                   //// add admin root
        {
            // $resCode    = 422;
            // $resSts     = 'error';
            // $resMsg     = 'Your Token Does Not Have The Ability To Create Admin';

            /* GET VARIABLE FOR ROOT */
            $adminPermission                = $request->admin_permission;
            $external_sf                    = ($request->report_analytics == 1) ? true : false;
            $reportAnalytics                = ($request->report_analytics == 1) ? true : false;

            // VALIDATION
            $rules = [
                'email' => ['required', 'valid_email'],
                'full_name' => ['required', 'valid_name'],
                // 'company_name' => ['required'],
                'password' => ['required'],
                'phone_number' => ['required','max:100', 'regex:/^[0-9()\s-]+$/'],
                'phone_country_code' => ['required'],
                'phone_country_calling_code' => ['required'],
                'admin_notification' => ['required','min:1','max:1','in:T,F'],
                'customer_care' => ['required','min:1','max:1','in:T,F'],
                'admin_default' => ['required','min:1','max:1','in:T,F'],
                'admin_permission' => ['required','min:1','max:1','in:T,F'],
                'report_analytics' => ['required','min:1','max:1','in:0,1'],
                'is_send_email' => ['required','in:0,1']
            ];
    
            if(isset($request->password))
                $rules['password'] = ['required','min:6','string'];

            $messages = [
                'valid_name' => 'The attribute :attribute not valid',
            ];
    
            $validator = Validator::make($request->all(), $rules, $messages);

            if($validator->fails())                                //// if validator false
            {
                $dtMsg = json_decode($validator->messages(), true);
                $tx_validator = $this->get_tx_validator($dtMsg);
                $tx_validator = str_replace("validation.valid_email", "The email must be a valid email address", $tx_validator);

                $resCode    = 422;
                $resSts     = 'error';
                $resMsg     = $tx_validator;
            }
            else                                                    //// MAIN CODE                            
            {
                $companyID = $openApiToken->company_id;

                /* CHECK EMAIL CLIENT HAS BEN IN AGENCY AND USER */
                $emailInAgencyUserExists = User::whereEncrypted('email', strtolower($request->email))
                                            ->whereIn('user_type', ['userdownline','user','sales'])
                                            ->where(function ($query) use ($companyID) {
                                                $query->where('company_id', $companyID)
                                                      ->orWhere('company_parent', $companyID);
                                            })
                                            ->where('active', 'T')
                                            ->first();

                if($emailInAgencyUserExists)                    //// EMAIL EXISTING
                {
                    $userTypeExists = $emailInAgencyUserExists->user_type ?? null;
                    $roleLabels = ['userdownline' => 'as a agency', 'user' => 'as a admin agency', 'client' => 'as a client', 'sales' => 'as a sales'][$userTypeExists] ?? '';

                    $resCode    = 409;
                    $resSts     = 'error';
                    $resMsg     = "This email address is already registered on your platform $roleLabels. Please use a different email address. Thank you!";
                }
                else                                            //// MAIN CODE
                {
                    $user = User::where('company_id', $companyID)
                                ->where('user_type', 'userdownline')
                                ->where('active', 'T')
                                ->first();
                    $idsys = $user->company_root_id;

                    $roles = Role::where('company_id','=',$companyID)->where('active', 'T')->first();
                    $idroles = $roles->id;

                    $user_permission = array(
                        'external_sf' => $external_sf,
                        'report_analytics' => $reportAnalytics
                    );

                    $data = new Request([
                        'ClientCompanyName' => $ClientCompanyName,
                        'ClientEmail' => $ClientEmail,
                        'ClientFullName' => $ClientFullName,
                        'ClientPass' => $ClientPass,
                        'ClientPhone' => $ClientPhone,
                        'ClientPhoneCountryCallingCode' => $ClientPhoneCountryCallingCode,
                        'ClientPhoneCountryCode' => $ClientPhoneCountryCode,
                        'ClientRole' => $idroles,
                        'adminGetNotification' => $adminNotif,
                        'companyID' => $companyID,
                        'customercare' => $customerCare,
                        'defaultAdmin' => $defaultAdmin,
                        'idsys' => $idsys,
                        'permission_active' => $adminPermission,
                        'selectedmodules' => "",
                        'twoFactorAuth' => 'email',
                        'userType' => $userType,
                        'user_permissions' => $user_permission,
                        'inOpenApi' => $inOpenApi,
                        'isSendEmail' => $isSendEmail
                    ]);

                    /* PROCESS CREATE ADMIN */
                    $createAdmin = ""; 
                    $createAdminUserID = "";
                    
                    try 
                    {
                        $createAdmin = $this->configurationController->create($data)->getData();
                        $createAdminUserID = $createAdmin->data[0]->id ?? "";
                    }
                    catch(\Exception $e)
                    {
                        $resCode    = 500;
                        $resSts     = 'error';
                        $resMsg     = $e->getMessage();
                    }

                    if($createAdmin != null) 
                    {
                        if($createAdmin->result == 'error' || $createAdmin->result == 'failed')
                        {
                            $resCode    = 409;
                            $resSts     = 'error';
                            $resMsg     =  $createAdmin->message;
                        } 
                        else
                        {
                            $resCode    = 200;
                            $resSts     = 'success';
                            $resMsg     =  'Admin Root Created Successfully.';
                            $user_id    = $createAdminUserID;
                        }
                    }
                    else 
                    {
                        $resCode    = 409;
                        $resSts     = 'error';
                        $resMsg     = 'Error Creating Data';
                    }
                    /* PROCESS CREATE ADMIN */
                }
            }
        }
        else                                                        //// add admin agency                             
        {
            // VALIDATION
            $rules = [
                'email' => ['required', 'valid_email'],
                'full_name' => ['required', 'valid_name'],
                // 'company_name' => ['required'],
                'password' => ['required'],
                'phone_number' => ['required','max:100', 'regex:/^[0-9()\s-]+$/'],
                'phone_country_code' => ['required'],
                'phone_country_calling_code' => ['required'],
                'admin_notification' => ['required','min:1','max:1','in:T,F'],
                'customer_care' => ['required','min:1','max:1','in:T,F'],
                'admin_default' => ['required','min:1','max:1','in:T,F'],
                'is_send_email' => ['required','in:0,1']
            ];
    
            if(isset($request->password))
                $rules['password'] = ['required','min:6','string'];

            $messages = [
                'valid_name' => 'The attribute :attribute not valid',
            ];
    
            $validator = Validator::make($request->all(), $rules, $messages);

            if($validator->fails())                                //// if validator false
            {
                $dtMsg = json_decode($validator->messages(), true);
                $tx_validator = $this->get_tx_validator($dtMsg);
                $tx_validator = str_replace("validation.valid_email", "The email must be a valid email address", $tx_validator);
                
                $resCode    = 422;
                $resSts     = 'error';
                $resMsg     = $tx_validator;
            }
            else                                                    //// MAIN CODE                            
            {
                $companyID = $openApiToken->company_id;

                /* CHECK EMAIL CLIENT HAS BEN IN AGENCY AND USER */
                $emailInAgencyUserExists = User::whereEncrypted('email', strtolower($request->email))
                                            ->whereIn('user_type', ['userdownline','user','client'])
                                            ->where(function ($query) use ($companyID) {
                                                $query->where('company_id', $companyID)
                                                      ->orWhere('company_parent', $companyID);
                                            })
                                            ->where('active', 'T')
                                            ->first();

                if($emailInAgencyUserExists)                    //// EMAIL EXISTING
                {
                    $userTypeExists = $emailInAgencyUserExists->user_type ?? null;
                    $roleLabels = ['userdownline' => 'as a agency', 'user' => 'as a admin agency', 'client' => 'as a client', 'sales' => 'as a sales'][$userTypeExists] ?? '';

                    $resCode    = 409;
                    $resSts     = 'error';
                    $resMsg     = "This email address is already registered on your platform $roleLabels. Please use a different email address. Thank you!";
                }
                else                                            //// MAIN CODE
                {
                    $user = User::where('company_id', $companyID)
                                ->where('user_type', 'userdownline')
                                ->where('active', 'T')
                                ->first();
                    $idsys = $user->company_root_id;

                    $roles = Role::where('company_id','=',$companyID)->where('active', 'T')->first();
                    $idroles = $roles->id;

                    $data = new Request([
                        'ClientCompanyName' => $ClientCompanyName,
                        'ClientEmail' => $ClientEmail,
                        'ClientFullName' => $ClientFullName,
                        'ClientPass' => $ClientPass,
                        'ClientPhone' => $ClientPhone,
                        'ClientPhoneCountryCallingCode' => $ClientPhoneCountryCallingCode,
                        'ClientPhoneCountryCode' => $ClientPhoneCountryCode,
                        'ClientRole' => $idroles,
                        'adminGetNotification' => $adminNotif,
                        'companyID' => $companyID,
                        'customercare' => $customerCare,
                        'defaultAdmin' => $defaultAdmin,
                        'idsys' => $idsys,
                        'permission_active' => NULL,
                        'selectedmodules' => "",
                        'twoFactorAuth' => 'email',
                        'userType' => $userType,
                        'user_permissions' => NULL,
                        'inOpenApi' => $inOpenApi,
                        'isSendEmail' => $isSendEmail
                    ]);

                    /* PROCESS CREATE ADMIN */
                    $createAdmin = ""; 
                    $createAdminUserID = "";
                    
                    try 
                    {
                        $createAdmin = $this->configurationController->create($data)->getData();
                        $createAdminUserID = $createAdmin->data[0]->id ?? "";
                    }
                    catch(\Exception $e)
                    {
                        $resCode    = 500;
                        $resSts     = 'error';
                        $resMsg     = $e->getMessage();
                    }

                    if($createAdmin != null) 
                    {
                        if($createAdmin->result == 'error' || $createAdmin->result == 'failed')
                        {
                            $resCode    = 409;
                            $resSts     = 'error';
                            $resMsg     =  $createAdmin->message;
                        } 
                        else
                        {
                            $resCode    = 200;
                            $resSts     = 'success';
                            $resMsg     =  'Admin Agency Created Successfully.';
                            $user_id    = $createAdminUserID;
                        }
                    }
                    else 
                    {
                        $resCode    = 409;
                        $resSts     = 'error';
                        $resMsg     = 'Error Creating Data';
                    }
                    /* PROCESS CREATE ADMIN */
                }
            }
        }

        //// insert logs 
        OpenApiLogs::create([
            'method_req' => $request->method(),
            'endpoint' => $request->fullUrl(),
            'function_req' => 'createUserAdmin',
            'token' => $request->bearerToken(),
            'content_type' => $request->header('Content-Type'),
            'request' => json_encode($request->all()),
            'response_code' => $resCode,
            'response_status' => $resSts,
            'response_message' => json_encode($resMsg),
            'company_id' => $companyID,
            'user_id' => $user_id ? $user_id : '',
            'email' => $ClientEmail,
            'phone_number' => $ClientPhone,
            'phone_country_code' => $ClientPhoneCountryCode,
            'phone_country_calling_code' => $ClientPhoneCountryCallingCode,
            'ip_address' => $request->ip()
        ]);

        //// return response
        if($resCode == 200)
        {
            return response()->json([
                'status' => $resSts, 
                'message' => $resMsg,
                'user_id' => $user_id,
                'status_code' => $resCode
            ], $resCode);
        }
        else 
        {
            return response()->json([
                'status' => $resSts, 
                'message' => $resMsg,
                'status_code' => $resCode
            ], $resCode);
        }
        //// END OF FUNCTION 
        
    }

    public function updateAdmin(string $userid, Request $request) 
    {
        $openApiToken = $request->attributes->get('openApiToken');

        /* GET VARIABLE */
        $resCode    = 200;
        $resSts     = null;
        $resMsg     = null;

        $userType                       = 'user';
        $user_id                        = '';
        $ClientCompanyName              = "";
        $ClientFullName                 = $request->full_name;
        $ClientEmail                    = $request->email ? strtolower($request->email) : '';
        $ClientPass                     = $request->password;
        // $isSendEmail                    = ($request->is_send_email == 1) ? true : false;
        $ClientPhone                    = $request->phone_number;
        $ClientPhoneCountryCode         = $request->phone_country_code;
        $ClientPhoneCountryCallingCode  = $request->phone_country_calling_code;

        $adminNotif                     = $request->admin_notification;
        $customerCare                   = $request->customer_care;
        $defaultAdmin                   = $request->admin_default;
        $inOpenApi                      = true;
        /* GET VARIABLE */

        $companyID = $openApiToken->company_id;
        $companyIDRoots = User::where('company_parent',null)
                              ->pluck('company_id')
                              ->toArray();
        
        if(in_array($companyID, $companyIDRoots))                   //// if not in agency
        {
            // $resCode    = 422;
            // $resSts     = 'error';
            // $resMsg     = 'Your Token Does Not Have The Ability To Update Admin';

            /* GET VARIABLE FOR ROOT */
            $adminPermission                = $request->admin_permission;
            $external_sf                    = ($request->report_analytics == 1) ? true : false;
            $reportAnalytics                = ($request->report_analytics == 1) ? true : false;

            // VALIDATION
            $rules = [
                'email' => ['required', 'valid_email'],
                'full_name' => ['required', 'valid_name'],
                // 'company_name' => ['required'],
                'password' => ['required'],
                'phone_number' => ['required','max:100', 'regex:/^[0-9()\s-]+$/'],
                'phone_country_code' => ['required'],
                'phone_country_calling_code' => ['required'],
                'admin_notification' => ['required','min:1','max:1','in:T,F'],
                'customer_care' => ['required','min:1','max:1','in:T,F'],
                'admin_default' => ['required','min:1','max:1','in:T,F'],
                'admin_permission' => ['required','min:1','max:1','in:T,F'],
                'report_analytics' => ['required','min:1','max:1','in:0,1']
            ];
    
            if(isset($request->password))
                $rules['password'] = ['required','min:6','string'];

            $messages = [
                'valid_name' => 'The attribute :attribute not valid',
            ];
    
            $validator = Validator::make($request->all(), $rules, $messages);

            if($validator->fails())                                //// if validator false
            {
                $dtMsg = json_decode($validator->messages(), true);
                $tx_validator = $this->get_tx_validator($dtMsg);
                $tx_validator = str_replace("validation.valid_email", "The email must be a valid email address", $tx_validator);
                
                $resCode    = 422;
                $resSts     = 'error';
                $resMsg     = $tx_validator;
            }
            else                                                                             
            {
                // FIND USER
                $updateUser = User::where('id', $userid)
                                ->where('company_id', $companyID)
                                ->where('user_type', 'user')
                                ->where('active', 'T')
                                ->first();

                if(empty($updateUser))                          //// Cek User Apa ada??
                {
                    $resCode    = 404;
                    $resSts     = 'error';
                    $resMsg     = ['user' => 'Admin Root Not Found'];
                }
                else{
                    $emailExists = User::whereEncrypted('email', strtolower($request->email))
                                    ->whereIn('user_type', ['userdownline','user','sales'])
                                    ->where('id','<>',$userid)
                                    ->where('active','=','T')
                                    ->where(function ($query) use ($companyID) {
                                        $query->where('company_id', $companyID)
                                                ->orWhere('company_parent', $companyID);
                                    })
                                    ->first();

                    if($emailExists)                            //// EMAIL EXISTING
                    {
                        $userTypeExists = $emailExists->user_type ?? null;
                        $roleLabels = ['userdownline' => 'as a agency', 'user' => 'as a admin agency', 'client' => 'as a client', 'sales' => 'as a sales'][$userTypeExists] ?? '';

                        $resCode    = 409;
                        $resSts     = 'error';
                        $resMsg     = "This email address is already registered on your platform $roleLabels. Please use a different email address. Thank you!";
                    }
                    else                                        //// MAIN CODE
                    {
                        $user = User::where('company_id', $companyID)
                                    ->where('user_type', 'userdownline')
                                    ->where('active', 'T')
                                    ->first();
                        $idsys = $user->company_root_id;

                        $roles = Role::where('company_id','=',$companyID)->where('active', 'T')->first();
                        $idroles = $roles->id;

                        $user_permission = array(
                                            'external_sf' => $external_sf,
                                            'report_analytics' => $reportAnalytics
                                        );
                            
                        $xdata = new Request([
                            'ClientID' => $userid,
                            'ClientEmail' => $ClientEmail,
                            'ClientFullName' => $ClientFullName,
                            'ClientPass' => $ClientPass,
                            'ClientPhone' => $ClientPhone,
                            'ClientPhoneCountryCallingCode' => $ClientPhoneCountryCallingCode,
                            'ClientPhoneCountryCode' => $ClientPhoneCountryCode,
                            'ClientRole' => $idroles,
                            'action' => 'administrator',
                            'adminGetNotification' => $adminNotif,
                            'companyID' => $companyID,
                            'customercare' => $customerCare,
                            'defaultAdmin' => $defaultAdmin,
                            'idsys' => $idsys,
                            'permission_active' => $adminPermission,
                            'user_permissions' => $user_permission,
                            'inOpenApi' => $inOpenApi,
                        ]);
    
                        /* PROCESS UPDATE ADMIN */
                        $updateAdmin = ""; 

                        try
                        {
                            $updateAdmin = $this->configurationController->update($xdata);
                            // return $updateAdmin;
                            if($updateAdmin != null) 
                            {
                                $updateAdmin = $updateAdmin->getData();
                                $result = $updateAdmin->result ?? '';
                                if(in_array($result, ['failed', 'error']))
                                {
                                    $errorMessage = $updateAdmin->message ?? "";
                                    $resCode    = 409;
                                    $resSts     = 'error';
                                    $resMsg     =  $updateAdmin->message;
                                }
                            } 
                            else
                            {
                                $resCode    = 200;
                                $resSts     = 'success';
                                $resMsg     =  'Admin Root Updated Successfully.';
                            }
                        }
                        catch (\Exception $e)
                        {
                            $resCode    = 500;
                            $resSts     = 'error';
                            $resMsg     = $e->getMessage();
                        }
                    }
                }
            }
        }
        else                                                        
        {
            // VALIDATION
            $rules = [
                'email' => ['required', 'valid_email'],
                'full_name' => ['required', 'valid_name'],
                // 'company_name' => ['required'],
                'password' => ['required'],
                'phone_number' => ['required','max:100', 'regex:/^[0-9()\s-]+$/'],
                'phone_country_code' => ['required'],
                'phone_country_calling_code' => ['required'],
                'admin_notification' => ['required','min:1','max:1','in:T,F'],
                'customer_care' => ['required','min:1','max:1','in:T,F'],
                'admin_default' => ['required','min:1','max:1','in:T,F']
            ];
    
            if(isset($request->password))
                $rules['password'] = ['required','min:6','string'];
            
            $messages = [
                'valid_name' => 'The attribute :attribute not valid',
            ];
    
            $validator = Validator::make($request->all(), $rules, $messages);

            if($validator->fails())                                //// if validator false
            {
                $dtMsg = json_decode($validator->messages(), true);
                $tx_validator = $this->get_tx_validator($dtMsg);
                $tx_validator = str_replace("validation.valid_email", "The email must be a valid email address", $tx_validator);
                
                $resCode    = 422;
                $resSts     = 'error';
                $resMsg     = $tx_validator;
            }
            else                                                                             
            {
                // FIND USER
                $updateUser = User::where('id', $userid)
                                ->where('company_id', $companyID)
                                ->where('user_type', 'user')
                                ->where('active', 'T')
                                ->first();

                if(empty($updateUser))                          //// Cek User Apa ada??
                {
                    $resCode    = 404;
                    $resSts     = 'error';
                    $resMsg     = ['user' => 'Admin Agency Not Found'];
                }
                else{
                    $emailExists = User::whereEncrypted('email', strtolower($request->email))
                    ->whereIn('user_type', ['userdownline','user','client'])
                    ->where('id','<>',$userid)
                    ->where('active','=','T')
                    ->where(function ($query) use ($companyID) {
                        $query->where('company_id', $companyID)
                                ->orWhere('company_parent', $companyID);
                    })
                    ->first();

                    if($emailExists)                            //// EMAIL EXISTING
                    {
                        $userTypeExists = $emailExists->user_type ?? null;
                        $roleLabels = ['userdownline' => 'as a agency', 'user' => 'as a admin agency', 'client' => 'as a client', 'sales' => 'as a sales'][$userTypeExists] ?? '';

                        $resCode    = 409;
                        $resSts     = 'error';
                        $resMsg     = "This email address is already registered on your platform $roleLabels. Please use a different email address. Thank you!";;
                    }
                    else                                        //// MAIN CODE
                    {
                        $user = User::where('company_id', $companyID)
                                    ->where('user_type', 'userdownline')
                                    ->where('active', 'T')
                                    ->first();
                        $idsys = $user->company_root_id;

                        $roles = Role::where('company_id','=',$companyID)->where('active', 'T')->first();
                        $idroles = $roles->id;
                            
                        $xdata = new Request([
                            'ClientID' => $userid,
                            'ClientEmail' => $ClientEmail,
                            'ClientFullName' => $ClientFullName,
                            'ClientPass' => $ClientPass,
                            'ClientPhone' => $ClientPhone,
                            'ClientPhoneCountryCallingCode' => $ClientPhoneCountryCallingCode,
                            'ClientPhoneCountryCode' => $ClientPhoneCountryCode,
                            'ClientRole' => $idroles,
                            'action' => 'administrator',
                            'adminGetNotification' => $adminNotif,
                            'companyID' => $companyID,
                            'customercare' => $customerCare,
                            'defaultAdmin' => $defaultAdmin,
                            'idsys' => $idsys,
                            'permission_active' => NULL,
                            'user_permissions' => NULL,
                            'inOpenApi' => $inOpenApi
                        ]);
    
                        /* PROCESS UPDATE ADMIN */
                        $updateAdmin = ""; 

                        try
                        {
                            $updateAdmin = $this->configurationController->update($xdata);

                            // return $updateAdmin;
                            if($updateAdmin != null) 
                            {
                                $updateAdmin = $updateAdmin->getData();
                                $result = $updateAdmin->result ?? '';
                                if(in_array($result, ['failed', 'error']))
                                {
                                    $errorMessage = $updateAdmin->message ?? "";
                                    $resCode    = 409;
                                    $resSts     = 'error';
                                    $resMsg     =  $updateAdmin->message;
                                }
                            } 
                            else
                            {
                                $resCode    = 200;
                                $resSts     = 'success';
                                $resMsg     =  'Admin Agency Updated Successfully.';
                            }
                        }
                        catch (\Exception $e)
                        {
                            $resCode    = 500;
                            $resSts     = 'error';
                            $resMsg     = $e->getMessage();
                        }
                    }
                }
            }
        }

        //// insert logs 
        OpenApiLogs::create([
            'method_req' => $request->method(),
            'endpoint' => $request->fullUrl(),
            'function_req' => 'updateAdmin',
            'token' => $request->bearerToken(),
            'content_type' => $request->header('Content-Type'),
            'request' => json_encode($request->all()),
            'response_code' => $resCode,
            'response_status' => $resSts,
            'response_message' => json_encode($resMsg),
            'company_id' => $companyID,
            'user_id' => $userid,
            'email' => $ClientEmail,
            'phone_number' => $ClientPhone,
            'phone_country_code' => $ClientPhoneCountryCode,
            'phone_country_calling_code' => $ClientPhoneCountryCallingCode,
            'ip_address' => $request->ip()
        ]);

        //// return response
        return response()->json([
            'status' => $resSts, 
            'message' => $resMsg,
            'status_code' => $resCode
        ], $resCode);
        
        //// END OF FUNCTION
    }

    /**
     * Delete Client With Open Api
     * @return \Illuminate\Http\JsonResponse
     */
    public function deleteClientOpenApi(string $usertype, string $userid, Request $request)
    {   
        if(!in_array($usertype, ['client', 'agency', 'sales', 'admin']))
        {
            //// insert logs 
            OpenApiLogs::create([
                'method_req' => $request->method(),
                'endpoint' => $request->fullUrl(),
                'function_req' => 'deleteClientOpenApi',
                'token' => $request->bearerToken(),
                'content_type' => $request->header('Content-Type'),
                'request' => json_encode($request->all()),
                'response_code' => 400,
                'response_status' => 'error',
                'response_message' => "User Type Must Be Client Or Agency Or Admin Or Sales",
                'company_id' => '',
                'user_id' => $userid,
                'ip_address' => $request->ip()
            ]);

            return response()->json([
                'status' => 'error', 
                'message' => "User Type Must Be Client Or Agency Or Admin Or Sales",
                'status_code' => 400
            ], 400);
        }

        if($usertype == 'agency' || $usertype == 'client')
        {
            return $this->deleteUserAgencyOrClient($usertype, $userid, $request);
        }
        else if($usertype == 'sales')
        {
            return $this->deleteUserSales($usertype, $userid, $request);
        }
        else if($usertype == 'admin')
        {
            return $this->deleteUserAdmin($usertype, $userid, $request);
        }
    }

    /**
     * Delete User Agency or Client With Open Api
     * @return \Illuminate\Http\JsonResponse
     */
    public function deleteUserAgencyOrClient(string $usertype, string $userid, Request $request)
    {   
        $openApiToken = $request->attributes->get('openApiToken');
        $func = __FUNCTION__;
        $resCode = 200;
        $resSts = 'success';
        $resMsg = '';

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
        
        // ketika token ini hanya mempunyai kemampuan untuk delete client, 
        // namun dia menggunakan url yang usertype agency example /openapi/delete/user/agency. 
        // maka tidak diperbolehkan. dan sebaliknya
        if(trim($userType) !== trim($usertype))
        {
            $resCode = 422;
            $resSts = 'error';
            $resMsg = 'Your Token Only Has The Ability To Create ' . ($userType === 'client' ? 'Client' : 'Agency');
            $this->inserLog($request, $resCode, $resSts, $resMsg, $func, $companyID);
            return response()->json(['status' => $resSts, 'message' => $resMsg, 'status_code' => $resCode], $resCode);
        }

        // validation user found or not found
        $typeUser = ($usertype == 'client') ? 'Client' : 'Agency';
        $user = User::where('id', $userid)
                    ->where('active', 'T')
                    ->where('company_parent', $companyID)
                    ->first();
        if(empty($user))
        {
            $resCode = 404;
            $resSts = 'error';
            $resMsg = "{$typeUser} not found";
            $this->inserLog($request, $resCode, $resSts, $resMsg, $func, $companyID);
            return response()->json(['status' => $resSts, 'message' => $resMsg, 'status_code' => $resCode], $resCode);
        } 
        // validation user found or not found
        
        // validation when campaign stirll running, paused on run and paused
        $campaignIds = LeadspeekUser::where('archived', 'F')
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
                                        })
                                        ->orWhere(function($query){
                                            $query->where('active','=','F')
                                                  ->where('disabled','=','T')
                                                  ->where('active_user','=','T');
                                        });
                                    });
        
        if($userType == 'client')
        {
            // info('masuk client');
            $campaignIds->where('user_id', $userid);
        }
        else if($userType == 'userdownline') 
        {
            // info('masuk userdownline');
            $campaignIds->where('company_id', $companyID);
        }

        $campaignIds = $campaignIds->pluck('leadspeek_api_id')
                                   ->toArray();
        // info('', ['campaignIds' => $campaignIds]);
        if(count($campaignIds) > 0)
        {
            $campaignIds = implode(',', $campaignIds);
            $resCode = 400;
            $resSts = 'error';
            $resMsg = "There are active campaigns still running for this {$typeUser}, Please stop all campaigns prior to deleting, List campaign id : {$campaignIds}";
            $this->inserLog($request, $resCode, $resSts, $resMsg, $func, $companyID);
            return response()->json(['status' => $resSts, 'message' => $resMsg, 'status_code' => $resCode], $resCode);
        }
        // validation when campaign stirll running, paused on run and paused

        $xdata = new Request([
            'CompanyID' => $companyID,
            'UserID' => $userid,
            'inOpenApi' => true,
        ]);

        try
        {
            $deleteUser = $this->configurationController->remove($xdata);
            // info('', ['deleteUser' => $deleteUser]);

            // return $deleteUser;
            if($deleteUser != null) 
            {
                $deleteUser = $deleteUser->getData();
                $result = $deleteUser->result ?? '';

                if(in_array($result, ['failed', 'error']))
                {
                    $resCode = 409;
                    $resSts = 'error';
                    $resMsg = $deleteUser->message ?? "";;
                    $this->inserLog($request, $resCode, $resSts, $resMsg, $func, $companyID);
                    return response()->json(['status' => $resSts, 'message' => $resMsg, 'status_code' => $resCode], $resCode);
                }
                else if ($result == 'success')
                {
                    $resCode = 200;
                    $resSts = 'success';
                    $resMsg = 'User Delete Successfully.';
                    $this->inserLog($request, $resCode, $resSts, $resMsg, $func, $companyID);
                    return response()->json(['status' => $resSts, 'message' => $resMsg, 'status_code' => $resCode], $resCode);
                }
            } 
            else
            {
                $resCode = 404;
                $resSts = 'error';
                $resMsg = 'Not Found';
                $this->inserLog($request, $resCode, $resSts, $resMsg, $func, $companyID);
                return response()->json(['status' => $resSts, 'message' => $resMsg, 'status_code' => $resCode], $resCode);
            }
        }
        catch (\Exception $e)
        {
            $resCode = 500;
            $resSts  = 'error';
            $resMsg  = $e->getMessage();
            $this->inserLog($request, $resCode, $resSts, $resMsg, $func, $companyID);
            return response()->json(['status' => $resSts, 'message' => $resMsg, 'status_code' => $resCode], $resCode);
        }
    }

    /**
     * delete user sales
     * @return \Illuminate\Http\JsonResponse
     */
    private function deleteUserSales(string $usertype, string $userid, Request $request)
    {
        $openApiToken = $request->attributes->get('openApiToken');

        /* CHECK ABILITY TOKEN */
        $companyIDRoot = User::where('company_parent',null)
                               ->pluck('company_id')
                               ->toArray();
        
        $company = Company::where('id', $openApiToken->company_id)
                               ->first();

        $companyID = $company->id ?? "";
        
        /* CHECK ABILITY TOKEN */
        if(!in_array($companyID, $companyIDRoot))
        {
            $resCode    = 422;
            $resSts     = 'error';
            $resMsg     = 'Your Token Only Has The Ability To Delete Sales';
        }
        else
        {
            $xdata = new Request([
                'CompanyID' => $companyID,
                'UserID' => $userid,
                'inOpenApi' => true,
            ]);

            try
            {
                $deleteUser = $this->configurationController->remove($xdata);

                // return $deleteUser;
                if($deleteUser != null) 
                {
                    $deleteUser = $deleteUser->getData();
                    $result = $deleteUser->result ?? '';
                    if(in_array($result, ['failed', 'error']))
                    {
                        $errorMessage = $deleteUser->message ?? "";
                        $resCode    = 409;
                        $resSts     = 'error';
                        $resMsg     =  $deleteUser->message;
                    }
                    else if ($result == 'success')
                    {
                        $resCode    = 200;
                        $resSts     = 'success';
                        $resMsg     =  'User Delete Successfully.';
                    }
                } 
                else
                {
                    $resCode    = 404;
                    $resSts     = 'error';
                    $resMsg     =  'Not Found';
                }
            }
            catch (\Exception $e)
            {
                $resCode    = 500;
                $resSts     = 'error';
                $resMsg     = $e->getMessage();
            }
        }

        //// insert logs 
        OpenApiLogs::create([
            'method_req' => $request->method(),
            'endpoint' => $request->fullUrl(),
            'function_req' => 'deleteUserSales',
            'token' => $request->bearerToken(),
            'content_type' => $request->header('Content-Type'),
            'request' => json_encode($request->all()),
            'response_code' => $resCode,
            'response_status' => $resSts,
            'response_message' => json_encode($resMsg),
            'company_id' => $companyID,
            'user_id' => $userid,
            'ip_address' => $request->ip()
        ]);

        //// return response
        return response()->json([
            'status' => $resSts, 
            'message' => $resMsg,
            'status_code' => $resCode
        ], $resCode);

    }

    public function deleteUserAdmin(string $usertype, string $userid, Request $request) 
    {
        $openApiToken = $request->attributes->get('openApiToken');

        $companyID = $openApiToken->company_id;
        $companyIDRoots = User::where('company_parent',null)
                              ->pluck('company_id')
                              ->toArray();
        
        if(in_array($companyID, $companyIDRoots))                   //// if not in agency
        {
            $resCode    = 422;
            $resSts     = 'error';
            $resMsg     = 'Your Token Does Not Have The Ability To Delete Admin';
        }
        else                                                        
        {
            // FIND USER
            $checkUser = User::where('id', $userid)
                            ->where('company_id', $companyID)
                            ->where('user_type', 'user')
                            ->where('active', 'T')
                            ->first();

            if(empty($checkUser))                          //// Cek User Apa ada??
            {
                $resCode    = 404;
                $resSts     = 'error';
                $resMsg     = ['user' => 'Admin Agency Not Found'];
            }
            else{  
                $xdata = new Request([
                    'CompanyID' => $companyID,
                    'UserID' => $userid,
                    'inOpenApi' => true,
                ]);

                /* PROCESS UPDATE ADMIN */
                $deleteUser = ""; 

                try
                {
                    $deleteUser = $this->configurationController->remove($xdata);

                    // return $deleteUser;
                    if($deleteUser != null) 
                    {
                        $deleteUser = $deleteUser->getData();
                        $result = $deleteUser->result ?? '';
                        if(in_array($result, ['failed', 'error']))
                        {
                            $errorMessage = $deleteUser->message ?? "";
                            $resCode    = 409;
                            $resSts     = 'error';
                            $resMsg     =  $deleteUser->message;
                        }
                        else if ($result == 'success')
                        {
                            $resCode    = 200;
                            $resSts     = 'success';
                            $resMsg     =  'User Delete Successfully.';
                        }
                    } 
                    else
                    {
                        $resCode    = 404;
                        $resSts     = 'error';
                        $resMsg     =  'Not Found';
                    }
                }
                catch (\Exception $e)
                {
                    $resCode    = 500;
                    $resSts     = 'error';
                    $resMsg     = $e->getMessage();
                }
                
            }
            
        }

        //// insert logs 
        OpenApiLogs::create([
            'method_req' => $request->method(),
            'endpoint' => $request->fullUrl(),
            'function_req' => 'deleteUserAdmin',
            'token' => $request->bearerToken(),
            'content_type' => $request->header('Content-Type'),
            'request' => json_encode($request->all()),
            'response_code' => $resCode,
            'response_status' => $resSts,
            'response_message' => json_encode($resMsg),
            'company_id' => $companyID,
            'user_id' => $userid,
            'ip_address' => $request->ip()
        ]);

        //// return response
        return response()->json([
            'status' => $resSts, 
            'message' => $resMsg,
            'status_code' => $resCode
        ], $resCode);
        
        //// END OF FUNCTION
    }

    public function updatePersonalProfile(Request $request) {
        $openApiToken = $request->attributes->get('openApiToken');

        /* GET VARIABLE */
        //// For User
        $id                         = $request->id_user;
        $name                       = $request->full_name;
        $email                      = $request->email ? strtolower($request->email) : '';
        $phone                      = $request->phone_number;
        $phoneCountryCode           = $request->phone_country_code;
        $phoneCountryCallingCode    = $request->phone_country_calling_code;
        $country                    = $request->country;
        $state                      = $request->state;
        $address                    = $request->address;
        $city                       = $request->city;
        $zipcode                    = $request->zipcode;

        //// FOR Company
        $companyName                        = $request->company_name;
        $companyEmail                       = $request->company_email;
        $companyPhone                       = $request->company_phone_number;
        $companyPhoneCountryCode            = $request->company_phone_country_code;
        $companyPhoneCountryCallingCode     = $request->company_phone_country_calling_code;
        $companyCountry                     = $request->company_country;
        $companyState                       = $request->company_state;
        $companyAddress                     = $request->company_address;
        $companyCity                        = $request->company_city;
        $companyZipcode                     = $request->company_zipcode;
        $companySubdomain                   = $request->company_subdomain;
        /* GET VARIABLE */

        $companyID = $openApiToken->company_id;
        $companyIDRoots = User::where('company_parent',null)
                              ->pluck('company_id')
                              ->toArray();
        
        if(in_array($companyID, $companyIDRoots)) {                     //// if not in agency
            $resCode    = 422;
            $resSts     = 'error';
            $resMsg     = 'Your Token Does Not Have The Ability To Update Personal Profile';
        } else {
            // VALIDATION
            $rules = [
                'id_user' => ['required', 'numeric'],
                'email' => ['required', 'valid_email'],
                'full_name' => ['required'],
                'phone_number' => ['required','max:100', 'regex:/^[0-9()\s-]+$/'],
                'phone_country_code' => ['required'],
                'phone_country_calling_code' => ['required'],
                'country' => ['required'],
                'state' => ['required'],
                'address' => ['required'],
                'city' => ['required'],
                'zipcode' => ['required']
            ];

            // if(isset($request->password))
            //     $rules['password'] = ['required','min:6','string'];

            $validator = Validator::make($request->all(), $rules);

            if($validator->fails()) {                                   //// if validator false
                $dtMsg = json_decode($validator->messages(), true);
                $tx_validator = $this->get_tx_validator($dtMsg);
                $tx_validator = str_replace("validation.valid_email", "The email must be a valid email address", $tx_validator);

                $resCode    = 422;
                $resSts     = 'error';
                $resMsg     = $tx_validator;
            } else {
                $company_root_id = User::where('company_id', '=', $companyID)->first()->company_root_id;
                $chkEmailExist = User::where('company_root_id',$company_root_id)
                        ->where('email',Encrypter::encrypt($email))
                        ->where('active','T')
                        ->where('id','<>',$id)
                        ->get();
                if (count($chkEmailExist) > 0) {
                    $resCode    = 400;
                    $resSts     = 'error';
                    $resMsg     =  'This email address is already associated with an existing account. Please log in or use a different email address.';
                } else {
                    //// cek kode country and state
                    $dt = DB::select("select country_code, state_code from states where country = ? and state = ?", [$country, $state]);

                    if($dt) {
                        $country_code = $dt[0]->country_code;
                        $state_code = $dt[0]->state_code;

                        $user = User::where('company_id', $companyID)
                                    ->where('user_type', 'userdownline')
                                    ->where('active', 'T')
                                    ->first();
                        $idsys = $user->company_root_id;

                        $xdata = new Request([
                            'id' => $id,
                            'idsys' => $idsys,
                            'name' => $name,
                            'email' => $email,
                            'phone' => $phone,
                            'phoneCountryCode' => $phoneCountryCode,
                            'phoneCountryDialCode' => $phoneCountryCallingCode,
                            'address' => $address,
                            'city' => $city,
                            'country' => $country_code,
                            'zip' => $zipcode,
                            'state' => $state_code,
                            'profilestep' => 'one',
                            'pict' => NULL,
                            'newpass' => '',
                            'renewpass' => ''
                            // 'currpict' => NULL,

                        ]);

                        // return $xdata;

                        /* PROCESS UPDATE ADMIN */
                        $setProfile = ""; 
                        $resultUpdProfile = false;

                        try {
                            $setProfile = $this->userController->update($xdata);

                            // return $setProfile;
                            if($setProfile != null) {
                                // $setProfile = $setProfile->getData();
                                $result = $setProfile['result'] ?? '';
                                // return $setProfile['result'];
                                if($result == 'success') {
                                    $resCode    = 200;
                                    $resSts     = 'success';
                                    $resMsg     =  'Success Updating User Profile';
                                    $resultUpdProfile = true;
                                }
                            } else {
                                $resCode    = 509;
                                $resSts     = 'error';
                                $resMsg     =  'Error When Updating User Profile.';
                            }
                        } catch (\Exception $e) {
                            $resCode    = 555;
                            $resSts     = 'error';
                            $resMsg     = $e->getMessage();
                        }

                        //// Update profil company
                        if($resultUpdProfile) {
                            //// CEK SUB DOMAIN 
                            //// DISINI
                            $usrProfile = User::where('id',$id)->first();
                            $confAppDomain =  config('services.application.domain');
                            if ($idsys != "") {
                                $conf = $this->getCompanyRootInfo($idsys);
                                $confAppDomain = $conf['domain'];
                            }
                            $urlSubdomain = "{$companySubdomain}.$confAppDomain";
                            $salt = substr(hash('sha256', env('APP_KEY')), 0, 16);
                            $subdomainExist = Company::where('subdomain', $urlSubdomain)
                                                    ->where('id','<>',$usrProfile->company_id)
                                                    ->exists();

                            if($subdomainExist) {
                                $resCode    = 409;
                                $resSts     = 'error';
                                $resMsg     = 'User profile updated successfully, but Subdomain already registered. Please use another Subdomain Thank you!';
                            } else {
                                /// cek kode country and state
                                if($request->has('company_country'))  
                                    $dtcountryCP = DB::select("select distinct country_code from states where country = ?", [$companyCountry]);

                                if ($dtcountryCP) {
                                    if($request->has('company_state'))  
                                        $dtStateCP = DB::select("select distinct state_code from states where state = ?", [$companyState]);
                                    
                                    if ($dtStateCP) {
                                        $country_code = !empty($dtcountryCP) ? $dtcountryCP[0]->country_code : null;
                                        $state_code = !empty($dtStateCP) ? $dtStateCP[0]->state_code : null;

                                        $dataCP = new Request([
                                            'id' => $id,
                                            'idsys' => $idsys,
                                            'companyID' => $companyID,
                                            'companyName' => $companyName,
                                            'companyemail' => $companyEmail,
                                            'companyphone' => $companyPhone,
                                            'companyphoneCountryCode' => $companyPhoneCountryCode,
                                            'companyPhoneCountryCallingCode' => $companyPhoneCountryCallingCode,
                                            'companyaddress' => $companyAddress,
                                            'companycity' => $companyCity,
                                            'companyzip' => $companyZipcode,
                                            'companycountry' => $country_code,
                                            'companystate' => $state_code,
                                            'profilestep' => 'two',
                                            'pict' => NULL,
                                            'industryID' => NULL,
                                            'industryName' => '',
                                            'DownlineSubDomain' => $companySubdomain
                                            // 'currpict' => NULL,

                                        ]);

                                        /* PROCESS UPDATE COMPANY */
                                        $setProfile = ""; 

                                        try {
                                            $setProfile = $this->userController->update($dataCP);

                                            // return $setProfile;
                                            if($setProfile != null) {
                                                // $setProfile = $setProfile->getData();
                                                $result = $setProfile['result'] ?? '';
                                                // return $setProfile['result'];
                                                if($result == 'success') {
                                                    $resCode    = 200;
                                                    $resSts     = 'success';
                                                    $resMsg     =  'Success Updating User Profile and Business Profile';
                                                }
                                            } else {
                                                $resCode    = 200;
                                                $resSts     = 'error';
                                                $resMsg     =  'User profile updated successfully, but there was an error updating the business profile.';
                                            }
                                        } catch (\Exception $e) {
                                            $resCode    = 555;
                                            $resSts     = 'error';
                                            $resMsg     = $e->getMessage();
                                        }
                                    } else {
                                        $resCode    = 200;
                                        $resSts     = 'error';
                                        $resMsg     =  'User profile updated successfully, but Company State Not found.. (Please Use States in United States only)';
                                    }
                                } else {
                                    $resCode    = 200;
                                        $resSts     = 'error';
                                        $resMsg     =  'User profile updated successfully, but Company Country Not found.. (Please Use Country in United States only)';
                                }
                                
                            }
                           
                        }
                        

                    } else {
                        $resCode    = 404;
                        $resSts     = 'error';
                        $resMsg     = 'Country or State Not Found.. (Please Use Country and States in United States only)';
                    }
                }
                
            }
        }

        //// insert logs 
        OpenApiLogs::create([
            'method_req' => $request->method(),
            'endpoint' => $request->fullUrl(),
            'function_req' => 'updatePersonalProfile',
            'token' => $request->bearerToken(),
            'content_type' => $request->header('Content-Type'),
            'request' => json_encode($request->all()),
            'response_code' => $resCode,
            'response_status' => $resSts,
            'response_message' => json_encode($resMsg),
            'company_id' => $companyID,
            'user_id' => $id,
            'ip_address' => $request->ip()
        ]);

        //// return response
        return response()->json([
            'status' => $resSts, 
            'message' => $resMsg,
            'status_code' => $resCode
        ], $resCode);

    }

    // public function updateBusinessProfile(Request $request) {
    //     $openApiToken = $request->attributes->get('openApiToken');

    //     /* GET VARIABLE */
    //     $id                                 = $request->id_user;
    //     $companyName                        = $request->company_name;
    //     $companyEmail                       = $request->company_email;
    //     $companyPhone                       = $request->phone_number;
    //     $companyPhoneCountryCode            = $request->phone_country_code;
    //     $companyPhoneCountryCallingCode     = $request->phone_country_calling_code;
    //     $companyCountry                     = $request->company_country;
    //     $companyState                       = $request->company_state;
    //     $companyAddress                     = $request->company_address;
    //     $companyCity                        = $request->company_city;
    //     $companyZipcode                     = $request->company_zipcode;
    //     $companySubdomain                   = $request->company_subdomain;
    //     /* GET VARIABLE */

    //     $companyID = $openApiToken->company_id;
    //     $companyIDRoots = User::where('company_parent',null)
    //                           ->pluck('company_id')
    //                           ->toArray();
        
    //     if(in_array($companyID, $companyIDRoots)) {                     //// if not in agency
    //         $resCode    = 422;
    //         $resSts     = 'error';
    //         $resMsg     = 'Your Token Does Not Have The Ability To Update Personal Profile';
    //     } else {
    //         // VALIDATION
    //         $rules = [
    //             'id_user' => ['required', 'numeric'],
    //             'company_email' => ['required', 'valid_email'],
    //             'company_name' => ['required'],
    //             'phone_number' => ['required','max:100', 'regex:/^[0-9()\s-]+$/'],
    //             'phone_country_code' => ['required'],
    //             'phone_country_calling_code' => ['required'],
    //             'company_country' => ['required'],
    //             'company_state' => ['required'],
    //             'company_address' => ['required'],
    //             'company_city' => ['required'],
    //             'company_zipcode' => ['required'],
    //             'company_subdomain' => ['required'],
    //         ];

    //         // if(isset($request->password))
    //         //     $rules['password'] = ['required','min:6','string'];

    //         $validator = Validator::make($request->all(), $rules);

    //         if($validator->fails()) {                                   //// if validator false
    //             $dtMsg = json_decode($validator->messages(), true);
    //             $tx_validator = $this->get_tx_validator($dtMsg);
                
    //             $resCode    = 422;
    //             $resSts     = 'error';
    //             $resMsg     = $tx_validator;
    //         } else {
    //             //// cek kode country and state
    //             $dt = DB::select("select country_code, state_code from states where country = ? and state = ?", [$companyCountry, $companyState]);

    //             if($dt) {
    //                 $country_code = $dt[0]->country_code;
    //                 $state_code = $dt[0]->state_code;

    //                 $user = User::where('company_id', $companyID)
    //                             ->where('user_type', 'userdownline')
    //                             ->where('active', 'T')
    //                             ->first();
    //                 $idsys = $user->company_root_id;
    //                 $industryID = $user->industry_id;

    //                 $xdata = new Request([
    //                     'id' => $id,
    //                     'idsys' => $idsys,
    //                     'companyID' => $companyID,
    //                     'companyName' => $companyName,
    //                     'companyemail' => $companyEmail,
    //                     'companyphone' => $companyPhone,
    //                     'companyphoneCountryCode' => $companyPhoneCountryCode,
    //                     'companyPhoneCountryCallingCode' => $companyPhoneCountryCallingCode,
    //                     'companyaddress' => $companyAddress,
    //                     'companycity' => $companyCity,
    //                     'companyzip' => $companyZipcode,
    //                     'companycountry' => $country_code,
    //                     'companystate' => $state_code,
    //                     'profilestep' => 'two',
    //                     'pict' => NULL,
    //                     'industryID' => $industryID,
    //                     'DownlineSubDomain' => $companySubdomain
    //                     // 'currpict' => NULL,

    //                 ]);

    //                 // return $xdata;

    //                 /* PROCESS UPDATE ADMIN */
    //                 $setProfile = ""; 

    //                 try {
    //                     $setProfile = $this->userController->update($xdata);

    //                     // return $setProfile;
    //                     if($setProfile != null) {
    //                         // $setProfile = $setProfile->getData();
    //                         $result = $setProfile['result'] ?? '';
    //                         // return $setProfile['result'];
    //                         if($result == 'success') {
    //                             $resCode    = 200;
    //                             $resSts     = 'success';
    //                             $resMsg     =  'Success Updating Business Profile';
    //                         }
    //                     } else {
    //                         $resCode    = 509;
    //                         $resSts     = 'error';
    //                         $resMsg     =  'Error When Updating Business Profile.';
    //                     }
    //                 } catch (\Exception $e) {
    //                     $resCode    = 555;
    //                     $resSts     = 'error';
    //                     $resMsg     = $e->getMessage();
    //                 }

    //             } else {
    //                 $resCode    = 404;
    //                 $resSts     = 'error';
    //                 $resMsg     = 'Country or State Not Found..';
    //             }
    //         }
    //     }

    //     //// insert logs 
    //     OpenApiLogs::create([
    //         'method_req' => $request->method(),
    //         'endpoint' => $request->fullUrl(),
    //         'function_req' => 'updateBusinessProfile',
    //         'token' => $request->bearerToken(),
    //         'content_type' => $request->header('Content-Type'),
    //         'request' => json_encode($request->all()),
    //         'response_code' => $resCode,
    //         'response_status' => $resSts,
    //         'response_message' => json_encode($resMsg),
    //         'company_id' => $companyID,
    //         'user_id' => $id,
    //         'ip_address' => $request->ip()
    //     ]);

    //     //// return response
    //     return response()->json([
    //         'status' => $resSts, 
    //         'message' => $resMsg,
    //         'status_code' => $resCode
    //     ], $resCode);

    // }

    public function exclusionListRoot(Request $request) {
        $openApiToken = $request->attributes->get('openApiToken');
        $id = '';
        
        $csvFile = $request->file('csv_file');
        $file_name = '';
        $idsys = '';
        // return $csvFile;
        // return count($csvFile);
        // return count(array($csvFile));
        // return $csvFile->getClientOriginalExtension();

        $companyID = $openApiToken->company_id;
        $companyIDRoots = User::where('company_parent',null)
                              ->pluck('company_id')
                              ->toArray();
        
        if(!in_array($companyID, $companyIDRoots)) {                     //// if not in agency
            $resCode    = 422;
            $resSts     = 'error';
            $resMsg     = 'Your Token Does Not Have The Ability To Upload File Exclusion List in Root Level';
        } else {
            // VALIDATION
            $rules = [
                'csv_file' => ['required','file','mimetypes:text/plain']
            ];
            $validator = Validator::make($request->all(), $rules);

            if($validator->fails()) {                                   //// if validator false
                $dtMsg = json_decode($validator->messages(), true);
                $tx_validator = $this->get_tx_validator($dtMsg);
                
                $resCode    = 422;
                $resSts     = 'error';
                $resMsg     = $tx_validator;
            } else {

                if ($csvFile->getClientOriginalExtension() !== 'csv') {
                    $resCode    = 509;
                    $resSts     = 'warning';
                    $resMsg     = 'Only file csv is allowed.';
                } else {
                    //// cek isi csv valid tidak???
                    $fileContent = file_get_contents($csvFile->getPathname());
                    $fileContent = preg_replace('/^\xEF\xBB\xBF/', '', $fileContent);
                    $lines = explode("\n", $fileContent);

                    $invalidEmails = [];
                    $ttlInvalidEmails = 0;
                    foreach ($lines as $line) {
                        // Hapus whitespace atau karakter tidak valid di awal dan akhir baris
                        $email = trim($line);
                        
                        if ($email && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                            // Jika baris bukan email yang valid, tambahkan ke list invalidEmails
                            $invalidEmails[] = $email;
                            $ttlInvalidEmails += 1;
                        }
                    }

                    if (!empty($invalidEmails)) {
                        $resCode    = 509;
                        $resSts     = 'error';
                        $resMsg     = 'Upload Failed.. '.$ttlInvalidEmails.' emails invalid : '.implode(", ", $invalidEmails);
                    } else {
                        $user = User::where('company_id', $companyID)
                                    ->where('user_type', 'userdownline')
                                    ->where('active', 'T')
                                    ->first();
                        $idsys = $user->company_root_id;
                        $id = $user->id;

                        $csvFilePath = $csvFile->getPathname();
                        $client = new Client;

                        $file_name = $csvFile->getClientOriginalName();

                        //// pakai curl guzzle aja request ke emm-api
                        $response = $client->post(env('APISERVER_URL').'/api/tools/optout/upload', [
                            'multipart' => [
                                [
                                    'name'     => 'optoutfile',
                                    'contents' => fopen($csvFilePath, 'r'),
                                    'filename' => $file_name,
                                ],
                                [
                                    'name'     => 'companyRootId',
                                    'contents' => $idsys,
                                ]
                            ]
                        ]);

                        $responseData = json_decode($response->getBody()->getContents(), true);

                        if($responseData['result'] == 'success') {
                            $resCode    = 200;
                            $resSts     = 'success';
                            $resMsg     =  'Success Uploading File Exclusion List';
                        } else {
                            $resCode    = 509;
                            $resSts     = 'error';
                            $resMsg     =  'Error When Uploading File Exclusion List.';
                        }
                    }
                }
            }
        }

        //// insert logs 
        OpenApiLogs::create([
            'method_req' => $request->method(),
            'endpoint' => $request->fullUrl(),
            'function_req' => 'exclusionListRoot',
            'token' => $request->bearerToken(),
            'content_type' => $request->header('Content-Type'),
            'request' => json_encode(array('csv_file' => $file_name, 'companyRootId' => $idsys)),
            'response_code' => $resCode,
            'response_status' => $resSts,
            'response_message' => json_encode($resMsg),
            'company_id' => $companyID,
            'user_id' => $id,
            'ip_address' => $request->ip()
        ]);

        //// return response
        return response()->json([
            'status' => $resSts, 
            'message' => $resMsg,
            'status_code' => $resCode
        ], $resCode);

    }

    public function exclusionListRootStatus(Request $request) {
        $openApiToken = $request->attributes->get('openApiToken');
        $id = '';

        $companyID = $openApiToken->company_id;
        $companyIDRoots = User::where('company_parent',null)
                              ->pluck('company_id')
                              ->toArray();
        
        if(!in_array($companyID, $companyIDRoots)) {                     //// if not in agency
            $resCode    = 422;
            $resSts     = 'error';
            $resMsg     = 'Your Token Does Not Have The Ability To Upload File Exclusion List';
        } else {
            $user = User::where('company_id', $companyID)
                                ->where('user_type', 'userdownline')
                                ->where('active', 'T')
                                ->first();
            $idsys = $user->company_root_id;
            $id = $user->id;

            $data = new Request([
                'leadspeekID' => 'undefined',
                'leadspeekApiId' => 'undefined',
                'campaignType' => 'undefined',
                'companyId' => $idsys

            ]);

            try {
                $returndata = $this->leadspeekController->suppressionprogress($data);

                // return $returndata;
                if($returndata != null) {
                    $returndata = $returndata->getData();
                        // $returndata = $returndata->getData();
                        // $resCode    = 200;
                        // $resSts     = 'success';
                        // $resMsg     = 'Success Get Data File Exclusion List';
                        // $resData    = $returndata;

                        // return $returndata->jobProgress;

                    if (!empty($returndata->jobProgress) || !empty($returndata->jobDone)) {
                        
                        $resCode    = 200;
                        $resSts     = 'success';
                        $resMsg     = 'Success Get Data File Exclusion List';
                        $resData    = $returndata;
                    } else {
                        $resCode    = 404;
                        $resSts     = 'warning';
                        $resMsg     = 'File Upload not found.';
                    }
                } else {
                    $resCode    = 509;
                    $resSts     = 'error';
                    $resMsg     = 'Error When Uploading File Exclusion List.';
                }
            } catch (\Exception $e) {
                $resCode    = 555;
                $resSts     = 'error';
                $resMsg     = $e->getMessage();
            }
            
        }

        //// insert logs 
        OpenApiLogs::create([
            'method_req' => $request->method(),
            'endpoint' => $request->fullUrl(),
            'function_req' => 'exclusionListRoot',
            'token' => $request->bearerToken(),
            'content_type' => $request->header('Content-Type'),
            'request' => json_encode($request->all()),
            'response_code' => $resCode,
            'response_status' => $resSts,
            'response_message' => json_encode($resMsg),
            'company_id' => $companyID,
            'user_id' => $id,
            'ip_address' => $request->ip()
        ]);

        //// return response
        if ($resCode == 200) {
            return response()->json([
                'status' => $resSts, 
                'message' => $resMsg,
                'status_code' => $resCode,
                'data' => $resData
            ], $resCode);
        } else {
            return response()->json([
                'status' => $resSts, 
                'message' => $resMsg,
                'status_code' => $resCode
            ], $resCode);
        }
    }

    public function exclusionListRootPurge(Request $request) {
        $openApiToken = $request->attributes->get('openApiToken');
        $id_user = '';
        
        $companyID = $openApiToken->company_id;
        $companyIDRoots = User::where('company_parent',null)
                              ->pluck('company_id')
                              ->toArray();
        
        if(!in_array($companyID, $companyIDRoots)) {                     //// if not in agency
            $resCode    = 422;
            $resSts     = 'error';
            $resMsg     = 'Your Token Does Not Have The Ability To Upload File Exclusion List';
        } else {
            $user = User::where('company_id', $companyID)
                                ->where('user_type', 'userdownline')
                                ->where('active', 'T')
                                ->first();
            $idsys = $user->company_root_id;
            $id_user = $user->id;

            try {
                $returndata = $this->toolController->purgeOptout($idsys);

                // return $returndata;
                if($returndata != null) {
                    $returndata = $returndata->getData();
                      
                    $resCode    = 200;
                    $resSts     = $returndata->result;
                    $resMsg     = $returndata->msg;
                } else {
                    $resCode    = 509;
                    $resSts     = 'error';
                    $resMsg     = 'Error When Uploading File Exclusion List.';
                }
            } catch (\Exception $e) {
                $resCode    = 555;
                $resSts     = 'error';
                $resMsg     = $e->getMessage();
            }
        }

        //// insert logs 
        OpenApiLogs::create([
            'method_req' => $request->method(),
            'endpoint' => $request->fullUrl(),
            'function_req' => 'exclusionListCampaignPurge',
            'token' => $request->bearerToken(),
            'content_type' => $request->header('Content-Type'),
            'request' => '{"companyRootId": '.$idsys.'}',
            'response_code' => $resCode,
            'response_status' => $resSts,
            'response_message' => json_encode($resMsg),
            'company_id' => $companyID,
            'user_id' => $id_user,
            'ip_address' => $request->ip()
        ]);

        //// return response
        return response()->json([
            'status' => $resSts, 
            'message' => $resMsg,
            'status_code' => $resCode
        ], $resCode);
        
    }

    public function exclusionListCampaign(Request $request) {
        $openApiToken = $request->attributes->get('openApiToken');

        $csvFile = $request->file('csv_file');
        $leadspeek_api_id = $request->campaign_id;
        $file_name = '';
        $id_campaign = '';
        $id_user = '';
        
        $companyID = $openApiToken->company_id;
        $companyIDRoots = User::where('company_parent',null)
                                ->pluck('company_id')
                                ->toArray();

        if(in_array($companyID, $companyIDRoots)) {                     //// if not in agency
            $resCode    = 422;
            $resSts     = 'error';
            $resMsg     = 'Your Token Does Not Have The Ability To Upload File Exclusion List Campaign';
        } else {
            // VALIDATION
            $rules = [
                'csv_file' => ['required','file','mimetypes:text/plain'],
                'campaign_id' => ['required', 'numeric']
            ];
            $validator = Validator::make($request->all(), $rules);

            if($validator->fails()) {                                   //// if validator false
                $dtMsg = json_decode($validator->messages(), true);
                $tx_validator = $this->get_tx_validator($dtMsg);
                
                $resCode    = 422;
                $resSts     = 'error';
                $resMsg     = $tx_validator;
            } else {

                if ($csvFile->getClientOriginalExtension() !== 'csv') {
                    $resCode    = 509;
                    $resSts     = 'warning';
                    $resMsg     = 'Only file csv is allowed.';
                } else {
                    //// cek isi csv valid tidak???
                    $fileContent = file_get_contents($csvFile->getPathname());
                    $fileContent = preg_replace('/^\xEF\xBB\xBF/', '', $fileContent);
                    $lines = explode("\n", $fileContent);

                    $invalidEmails = [];
                    $ttlInvalidEmails = 0;
                    foreach ($lines as $line) {
                        // Hapus whitespace atau karakter tidak valid di awal dan akhir baris
                        $email = trim($line);
                        
                        if ($email && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                            // Jika baris bukan email yang valid, tambahkan ke list invalidEmails
                            $invalidEmails[] = $email;
                            $ttlInvalidEmails += 1;
                        }
                    }

                    if (!empty($invalidEmails)) {
                        // return $invalidEmails;
                        // return implode(", ", $invalidEmails);
                        $resCode    = 509;
                        $resSts     = 'error';
                        $resMsg     = 'Upload Failed.. '.$ttlInvalidEmails.' emails invalid : '.implode(", ", $invalidEmails);
                    } else {
                        $leadspeek_dt   = LeadspeekUser::where('leadspeek_api_id', $leadspeek_api_id)->first();
                        if($leadspeek_dt) {
                            $id_user        = $leadspeek_dt->user_id;
                            $id_campaign    = $leadspeek_dt->id;
    
                            $csvFilePath = $csvFile->getPathname();
                            $client = new Client;
    
                            $file_name = $csvFile->getClientOriginalName();
    
                            //// pakai curl guzzle aja request ke emm-api
                            $response = $client->post(env('APISERVER_URL').'/api/leadspeek/suppressionlist/upload', [
                                'multipart' => [
                                    [
                                        'name'     => 'suppressionfile',
                                        'contents' => fopen($csvFilePath, 'r'),
                                        'filename' => $file_name,
                                    ],
                                    [
                                        'name'     => 'leadspeekID',
                                        'contents' => $id_campaign,
                                    ]
                                ]
                            ]);
    
                            $responseData = json_decode($response->getBody()->getContents(), true);
    
                            if($responseData['result'] == 'success') {
                                $resCode    = 200;
                                $resSts     = 'success';
                                $resMsg     =  'Success Uploading File Exclusion List Campaign.';
                            } else {
                                $resCode    = 509;
                                $resSts     = 'error';
                                $resMsg     =  'Error When Uploading File Exclusion List Campaign.';
                            }
                        } else {
                            $resCode    = 404;
                            $resSts     = 'error';
                            $resMsg     = 'Campaign Not Found';
                        }
                    }
                }
            }
        }

        //// insert logs 
        OpenApiLogs::create([
            'method_req' => $request->method(),
            'endpoint' => $request->fullUrl(),
            'function_req' => 'exclusionListCampaign',
            'token' => $request->bearerToken(),
            'content_type' => $request->header('Content-Type'),
            'request' => json_encode(array('csv_file' => $file_name, 'leadspeekID' => $id_campaign)),
            'response_code' => $resCode,
            'response_status' => $resSts,
            'response_message' => json_encode($resMsg),
            'company_id' => $companyID,
            'user_id' => $id_user,
            'campaign_id' => $id_campaign,
            'ip_address' => $request->ip()
        ]);

        //// return response
        return response()->json([
            'status' => $resSts, 
            'message' => $resMsg,
            'status_code' => $resCode
        ], $resCode);

    }

    public function exclusionListCampaignStatus(Request $request) {
        $openApiToken = $request->attributes->get('openApiToken');
        $leadspeek_api_id = $request->campaign_id;
        $id_user = '';

        $companyID = $openApiToken->company_id;
        $companyIDRoots = User::where('company_parent',null)
                              ->pluck('company_id')
                              ->toArray();
        
        if(in_array($companyID, $companyIDRoots)) {                     //// if not in agency
            $resCode    = 422;
            $resSts     = 'error';
            $resMsg     = 'Your Token Does Not Have The Ability To Upload File Exclusion List';
        } else {
            // VALIDATION
            $rules = [
                'campaign_id' => ['required', 'numeric']
            ];
            $validator = Validator::make($request->all(), $rules);

            if($validator->fails()) {                                   //// if validator false
                $dtMsg = json_decode($validator->messages(), true);
                $tx_validator = $this->get_tx_validator($dtMsg);
                
                $resCode    = 422;
                $resSts     = 'error';
                $resMsg     = $tx_validator;
            } else {
                $leadspeek_dt   = LeadspeekUser::where('leadspeek_api_id', $leadspeek_api_id)->first();
                if($leadspeek_dt) {
                    $id_user        = $leadspeek_dt->user_id;
                    $id_campaign    = $leadspeek_dt->id;
    
                    $user = User::where('id', $id_user)
                                    ->where('active', 'T')
                                    ->first();
    
                    $company_id_user  = $user->company_id;
    
                    $data = new Request([
                        'leadspeekID' => $id_campaign,
                        'companyId' => $company_id_user,
                        'leadspeekApiId' => $leadspeek_api_id,
                        'campaignType' => 'campaign',
    
                    ]);
    
                    try {
                        $returndata = $this->leadspeekController->suppressionprogress($data);
    
                        // return $data;
                        // return $returndata;
                        if($returndata != null) {
                            $returndata = $returndata->getData();
                            if (!empty($returndata->jobProgress) || !empty($returndata->jobDone)) {
                                
                                $resCode    = 200;
                                $resSts     = 'success';
                                $resMsg     = 'Success Get Data File Exclusion List Campaign.';
                                $resData    = $returndata;
                            } else {
                                $resCode    = 404;
                                $resSts     = 'warning';
                                $resMsg     = 'File Upload not found.';
                            }
                        } else {
                            $resCode    = 509;
                            $resSts     = 'error';
                            $resMsg     = 'Error When Uploading File Exclusion List Campaign.';
                        }
                    } catch (\Exception $e) {
                        $resCode    = 555;
                        $resSts     = 'error';
                        $resMsg     = $e->getMessage();
                    }
                } else {
                    $resCode    = 404;
                    $resSts     = 'warning';
                    $resMsg     = 'Campaign not found.';
                }
            }
        }

        //// insert logs 
        OpenApiLogs::create([
            'method_req' => $request->method(),
            'endpoint' => $request->fullUrl(),
            'function_req' => 'exclusionListCampaignStatus',
            'token' => $request->bearerToken(),
            'content_type' => $request->header('Content-Type'),
            'request' => json_encode($request->all()),
            'response_code' => $resCode,
            'response_status' => $resSts,
            'response_message' => json_encode($resMsg),
            'company_id' => $companyID,
            'user_id' => $id_user,
            'ip_address' => $request->ip()
        ]);

        //// return response
        if ($resCode == 200) {
            return response()->json([
                'status' => $resSts, 
                'message' => $resMsg,
                'status_code' => $resCode,
                'data' => $resData
            ], $resCode);
        } else {
            return response()->json([
                'status' => $resSts, 
                'message' => $resMsg,
                'status_code' => $resCode
            ], $resCode);
        }
    }

    public function exclusionListCampaignPurge(Request $request) {
        $openApiToken = $request->attributes->get('openApiToken');
        $leadspeek_api_id = $request->campaign_id;
        $id_user = '';

        $companyID = $openApiToken->company_id;
        $companyIDRoots = User::where('company_parent',null)
                              ->pluck('company_id')
                              ->toArray();
        
        if(in_array($companyID, $companyIDRoots)) {                     //// if not in agency
            $resCode    = 422;
            $resSts     = 'error';
            $resMsg     = 'Your Token Does Not Have The Ability To Upload File Exclusion List';
        } else {
            // VALIDATION
            $rules = [
                'campaign_id' => ['required', 'numeric']
            ];
            $validator = Validator::make($request->all(), $rules);

            if($validator->fails()) {                                   //// if validator false
                $dtMsg = json_decode($validator->messages(), true);
                $tx_validator = $this->get_tx_validator($dtMsg);
                
                $resCode    = 422;
                $resSts     = 'error';
                $resMsg     = $tx_validator;
            } else {
                $leadspeek_dt   = LeadspeekUser::where('leadspeek_api_id', $leadspeek_api_id)->first();
                if($leadspeek_dt) {
                    $id_user        = $leadspeek_dt->user_id;
                    $id_campaign    = $leadspeek_dt->id;
    
                    $data = new Request([
                        'paramID' => $id_campaign,
                        'campaignType' => 'campaign',
                    ]);
    
                    try {
                        $returndata = $this->leadspeekController->suppressionpurge($data);
                        if($returndata != null) {
                            $returndata = $returndata->getData();
                            
                            $resCode    = 200;
                            $resSts     = $returndata->result;
                            $resMsg     = $returndata->msg;
                        } else {
                            $resCode    = 509;
                            $resSts     = 'error';
                            $resMsg     = 'Error When Purge Exclusion List Campaign.';
                        }
                    } catch (\Exception $e) {
                        $resCode    = 555;
                        $resSts     = 'error';
                        $resMsg     = $e->getMessage();
                    }
                } else {
                    $resCode    = 404;
                    $resSts     = 'warning';
                    $resMsg     = 'Campaign not found.';
                }
            }
        }

        //// insert logs 
        OpenApiLogs::create([
            'method_req' => $request->method(),
            'endpoint' => $request->fullUrl(),
            'function_req' => 'exclusionListCampaignPurge',
            'token' => $request->bearerToken(),
            'content_type' => $request->header('Content-Type'),
            'request' => json_encode($request->all()),
            'response_code' => $resCode,
            'response_status' => $resSts,
            'response_message' => json_encode($resMsg),
            'company_id' => $companyID,
            'user_id' => $id_user,
            'ip_address' => $request->ip()
        ]);

        //// return response
        return response()->json([
            'status' => $resSts, 
            'message' => $resMsg,
            'status_code' => $resCode
        ], $resCode);
        
    }

    public function exclusionListClient(Request $request) {
        $openApiToken = $request->attributes->get('openApiToken');

        $csvFile = $request->file('csv_file');
        $id_user = $request->client_id;
        $file_name = '';
        $id_company_client = '';
        
        $companyID = $openApiToken->company_id;
        $companyIDRoots = User::where('company_parent',null)
                                ->pluck('company_id')
                                ->toArray();

        if(in_array($companyID, $companyIDRoots)) {                     //// if not in agency
            $resCode    = 422;
            $resSts     = 'error';
            $resMsg     = 'Your Token Does Not Have The Ability To Upload File Exclusion List Client';
        } else {
            // VALIDATION
            $rules = [
                'csv_file' => ['required','file','mimetypes:text/plain'],
                'client_id' => ['required', 'numeric']
            ];
            $validator = Validator::make($request->all(), $rules);

            if($validator->fails()) {                                   //// if validator false
                $dtMsg = json_decode($validator->messages(), true);
                $tx_validator = $this->get_tx_validator($dtMsg);
                
                $resCode    = 422;
                $resSts     = 'error';
                $resMsg     = $tx_validator;
            } else {

                if ($csvFile->getClientOriginalExtension() !== 'csv') {
                    $resCode    = 509;
                    $resSts     = 'warning';
                    $resMsg     = 'Only file csv is allowed.';
                } else {
                    //// cek isi csv valid tidak???
                    $fileContent = file_get_contents($csvFile->getPathname());
                    $fileContent = preg_replace('/^\xEF\xBB\xBF/', '', $fileContent);
                    $lines = explode("\n", $fileContent);

                    $invalidEmails = [];
                    $ttlInvalidEmails = 0;
                    foreach ($lines as $line) {
                        // Hapus whitespace atau karakter tidak valid di awal dan akhir baris
                        $email = trim($line);
                        
                        if ($email && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                            // Jika baris bukan email yang valid, tambahkan ke list invalidEmails
                            $invalidEmails[] = $email;
                            $ttlInvalidEmails += 1;
                        }
                    }

                    if (!empty($invalidEmails)) {
                        // return json_encode($invalidEmails);
                        $resCode    = 509;
                        $resSts     = 'error';
                        $resMsg     = 'Upload Failed.. '.$ttlInvalidEmails.' emails invalid : '.implode(", ", $invalidEmails);
                    } else {
                        $user_dt   = User::where('id', $id_user)->where('active','=','T')->first();
                        if($user_dt) {
                            $id_company_client    = $user_dt->company_id;
    
                            $csvFilePath = $csvFile->getPathname();
                            $client = new Client;
    
                            $file_name = $csvFile->getClientOriginalName();
    
                            //// pakai curl guzzle aja request ke emm-api
                            $response = $client->post(env('APISERVER_URL').'/api/tools/optout-client/upload', [
                                'multipart' => [
                                    [
                                        'name'     => 'clientoptoutfile',
                                        'contents' => fopen($csvFilePath, 'r'),
                                        'filename' => $file_name,
                                    ],
                                    [
                                        'name'     => 'ClientCompanyID',
                                        'contents' => $id_company_client,
                                    ],
                                    [
                                        'name'     => 'campaigntype',
                                        'contents' => 'client',
                                    ]
                                ]
                            ]);
    
                            $responseData = json_decode($response->getBody()->getContents(), true);
    
                            if($responseData['result'] == 'success') {
                                $resCode    = 200;
                                $resSts     = 'success';
                                $resMsg     =  'Success Uploading File Exclusion List Client.';
                            } else {
                                $resCode    = 509;
                                $resSts     = 'error';
                                $resMsg     =  'Error When Uploading File Exclusion List Client.';
                            }
                        } else {
                            $resCode    = 404;
                            $resSts     = 'error';
                            $resMsg     = 'Client Not Found';
                        }
                    }
                }
            }
        }

        //// insert logs 
        OpenApiLogs::create([
            'method_req' => $request->method(),
            'endpoint' => $request->fullUrl(),
            'function_req' => 'exclusionListClient',
            'token' => $request->bearerToken(),
            'content_type' => $request->header('Content-Type'),
            'request' => json_encode(array('csv_file' => $file_name, 'ClientCompanyID' => $id_company_client)),
            'response_code' => $resCode,
            'response_status' => $resSts,
            'response_message' => json_encode($resMsg),
            'company_id' => $companyID,
            'user_id' => $id_user,
            'ip_address' => $request->ip()
        ]);

        //// return response
        return response()->json([
            'status' => $resSts, 
            'message' => $resMsg,
            'status_code' => $resCode
        ], $resCode);

    }

    public function exclusionListClientStatus(Request $request) {
        $openApiToken = $request->attributes->get('openApiToken');
        $id_user = $request->client_id;

        $companyID = $openApiToken->company_id;
        $companyIDRoots = User::where('company_parent',null)
                              ->pluck('company_id')
                              ->toArray();
        
        if(in_array($companyID, $companyIDRoots)) {                     //// if not in agency
            $resCode    = 422;
            $resSts     = 'error';
            $resMsg     = 'Your Token Does Not Have The Ability To Upload File Exclusion List';
        } else {
            // VALIDATION
            $rules = [
                'client_id' => ['required', 'numeric']
            ];
            $validator = Validator::make($request->all(), $rules);

            if($validator->fails()) {                                   //// if validator false
                $dtMsg = json_decode($validator->messages(), true);
                $tx_validator = $this->get_tx_validator($dtMsg);
                
                $resCode    = 422;
                $resSts     = 'error';
                $resMsg     = $tx_validator;
            } else {
                $user_dt = User::where('id', $id_user)->where('active', 'T')->first();
                if($user_dt) {
                    $id_company_client    = $user_dt->company_id;
    
                    $data = new Request([
                        'leadspeekID' => 'undefined',
                        'companyId' => $id_company_client,
                        'leadspeekApiId' => 'undefined',
                        'campaignType' => 'client',
    
                    ]);
    
                    try {
                        $returndata = $this->leadspeekController->suppressionprogress($data);
    
                        // return $data;
                        // return $returndata;
                        if($returndata != null) {
                            $returndata = $returndata->getData();
                            if (!empty($returndata->jobProgress) || !empty($returndata->jobDone)) {
                                
                                $resCode    = 200;
                                $resSts     = 'success';
                                $resMsg     = 'Success Get Data File Exclusion List Client.';
                                $resData    = $returndata;
                            } else {
                                $resCode    = 404;
                                $resSts     = 'warning';
                                $resMsg     = 'File Upload not found.';
                            }
                        } else {
                            $resCode    = 509;
                            $resSts     = 'error';
                            $resMsg     = 'Error When Uploading File Exclusion List Client.';
                        }
                    } catch (\Exception $e) {
                        $resCode    = 555;
                        $resSts     = 'error';
                        $resMsg     = $e->getMessage();
                    }
                } else {
                    $resCode    = 404;
                    $resSts     = 'warning';
                    $resMsg     = 'Client not found.';
                }
            }
        }

        //// insert logs 
        OpenApiLogs::create([
            'method_req' => $request->method(),
            'endpoint' => $request->fullUrl(),
            'function_req' => 'exclusionListClientStatus',
            'token' => $request->bearerToken(),
            'content_type' => $request->header('Content-Type'),
            'request' => json_encode($request->all()),
            'response_code' => $resCode,
            'response_status' => $resSts,
            'response_message' => json_encode($resMsg),
            'company_id' => $companyID,
            'user_id' => $id_user,
            'ip_address' => $request->ip()
        ]);

        //// return response
        if ($resCode == 200) {
            return response()->json([
                'status' => $resSts, 
                'message' => $resMsg,
                'status_code' => $resCode,
                'data' => $resData
            ], $resCode);
        } else {
            return response()->json([
                'status' => $resSts, 
                'message' => $resMsg,
                'status_code' => $resCode
            ], $resCode);
        }
    }

    public function exclusionListClientPurge(Request $request) {
        $openApiToken = $request->attributes->get('openApiToken');
        $id_user = $request->client_id;

        $companyID = $openApiToken->company_id;
        $companyIDRoots = User::where('company_parent',null)
                              ->pluck('company_id')
                              ->toArray();
        
        if(in_array($companyID, $companyIDRoots)) {                     //// if not in agency
            $resCode    = 422;
            $resSts     = 'error';
            $resMsg     = 'Your Token Does Not Have The Ability To Upload File Exclusion List';
        } else {
            // VALIDATION
            $rules = [
                'client_id' => ['required', 'numeric']
            ];
            $validator = Validator::make($request->all(), $rules);

            if($validator->fails()) {                                   //// if validator false
                $dtMsg = json_decode($validator->messages(), true);
                $tx_validator = $this->get_tx_validator($dtMsg);
                
                $resCode    = 422;
                $resSts     = 'error';
                $resMsg     = $tx_validator;
            } else {
                $user_dt   = User::where('id', $id_user)->where('active','=','T')->first();
                if($user_dt) {
                    $id_company_client    = $user_dt->company_id;
    
                    $data = new Request([
                        'paramID' => $id_company_client,
                        'campaignType' => 'client',
                    ]);
    
                    try {
                        $returndata = $this->leadspeekController->suppressionpurge($data);
                        if($returndata != null) {
                            $returndata = $returndata->getData();
                            
                            $resCode    = 200;
                            $resSts     = $returndata->result;
                            $resMsg     = $returndata->msg;
                        } else {
                            $resCode    = 509;
                            $resSts     = 'error';
                            $resMsg     = 'Error When Purge Exclusion List Client.';
                        }
                    } catch (\Exception $e) {
                        $resCode    = 555;
                        $resSts     = 'error';
                        $resMsg     = $e->getMessage();
                    }
                } else {
                    $resCode    = 404;
                    $resSts     = 'warning';
                    $resMsg     = 'Client not found.';
                }
            }
        }

        //// insert logs 
        OpenApiLogs::create([
            'method_req' => $request->method(),
            'endpoint' => $request->fullUrl(),
            'function_req' => 'exclusionListClientPurge',
            'token' => $request->bearerToken(),
            'content_type' => $request->header('Content-Type'),
            'request' => json_encode($request->all()),
            'response_code' => $resCode,
            'response_status' => $resSts,
            'response_message' => json_encode($resMsg),
            'company_id' => $companyID,
            'user_id' => $id_user,
            'ip_address' => $request->ip()
        ]);

        //// return response
        return response()->json([
            'status' => $resSts, 
            'message' => $resMsg,
            'status_code' => $resCode
        ], $resCode);
        
    }

    // =======INTEGRATION CLIENT START=======
    /**
     * get integration client
     * @return \Illuminate\Http\JsonResponse
     */
    public function getClientIntegrations(string $userid, Request $request)
    {
        $openApiToken = $request->attributes->get('openApiToken');
        $companyIDAgency = $openApiToken->company_id ?? "";
        $func = __FUNCTION__;

        /* VALIDATION ONLY AGENCY */
        $response = $this->openApiValidationService->validationTokenOnlyAgency($companyIDAgency);
        // info('validationTokenOnlyAgency', ['response' => $response]);
        if(($response['status'] ?? '') == 'error')
        {
            $resCode = $response['status_code'] ?? 400;
            $resSts = $response['status'];
            $resMsg = $response['message'] ?? "Something Went Wrong";
            $this->inserLog($request, $resCode, $resSts, $resMsg, $func, $companyIDAgency);
            return response()->json(['status' => $resSts, 'message' => $resMsg, 'status_code' => $resCode], $resCode);
        }
        /* VALIDATION ONLY AGENCY */

        /* PROCSS GET INTEGRATIONS CLIENTS */
        $response = $this->openApiIntegrationService->getClientIntegrations($userid, $companyIDAgency);
        // info('getCampaignIntegrations', ['response' => $response]);
        if(($response['status'] ?? '') == 'error')
        {
            $resCode = $response['status_code'] ?? 400;
            $resSts = $response['status'];
            $resMsg = $response['message'] ?? "Something Went Wrong";
            $this->inserLog($request, $resCode, $resSts, $resMsg, $func, $companyIDAgency);
            return response()->json(['status' => $resSts, 'message' => $resMsg, 'status_code' => $resCode], $resCode);
        }
        $resData = $response['data'] ?? [];
        /* PROCSS GET INTEGRATIONS CLIENTS */

        /* RETURN */
        $resCode = 200;
        $resSts = 'success';
        $resMsg = '';
        $this->inserLog($request, $resCode, $resSts, $resMsg, $func, $companyIDAgency);
        return response()->json(['status' => $resSts, 'message' => $resMsg, 'data' => $resData, 'status_code' => $resCode], $resCode);
        /* RETURN */
    }

    /**
     * create integration client
     * @return \Illuminate\Http\JsonResponse
     */
    public function createOrUpdateClientIntegration(string $userid, Request $request)
    {
        $openApiToken = $request->attributes->get('openApiToken');
        $companyIDAgency = $openApiToken->company_id ?? "";
        $func = __FUNCTION__;
        // info("public function $func", ['userid' => $userid, 'companyIDAgency' => $companyIDAgency]);

        /* VALIDATION ONLY AGENCY */
        $response = $this->openApiValidationService->validationTokenOnlyAgency($companyIDAgency);
        // info('validationTokenOnlyAgency', ['response' => $response]);
        if(($response['status'] ?? '') == 'error')
        {
            $resCode = $response['status_code'] ?? 400;
            $resSts = $response['status'];
            $resMsg = $response['message'] ?? "Something Went Wrong";
            $this->inserLog($request, $resCode, $resSts, $resMsg, $func, $companyIDAgency);
            return response()->json(['status' => $resSts, 'message' => $resMsg, 'status_code' => $resCode], $resCode);
        }
        /* VALIDATION ONLY AGENCY */

        /* PROCSS CREATE INTEGRATIONS CAMPAIGN */
        $response = $this->openApiIntegrationService->createOrUpdateClientIntegration($userid, $companyIDAgency, $request);
        // info('createOrUpdateCampaignIntegration', ['response' => $response]);
        if(($response['status'] ?? '') == 'error')
        {
            $resCode = $response['status_code'] ?? 400;
            $resSts = $response['status'];
            $resMsg = $response['message'] ?? "Something Went Wrong";
            $this->inserLog($request, $resCode, $resSts, $resMsg, $func, $companyIDAgency);
            return response()->json(['status' => $resSts, 'message' => $resMsg, 'status_code' => $resCode], $resCode);
        }
        $resMsg = $response['message'] ?? "Successfully Create Campaign Integration";
        /* PROCSS CREATE INTEGRATIONS CAMPAIGN */

        /* RETURN */
        $resCode = 200;
        $resSts = 'success';
        $this->inserLog($request, $resCode, $resSts, $resMsg, $func, $companyIDAgency);
        return response()->json(['status' => $resSts, 'message' => $resMsg, 'status_code' => $resCode], $resCode);
        /* RETURN */
    }
    // =======INTEGRATION CLIENT END=======

    // =======INTEGRATION TARGS CLIENT START=======
    /**
     * get tag integration client
     * @return \Illuminate\Http\JsonResponse
     */
    public function getTagIntegrations(string $integrationslug, string $userid, Request $request)
    {
        $openApiToken = $request->attributes->get('openApiToken');
        $companyIDAgency = $openApiToken->company_id ?? "";
        $func = __FUNCTION__;

        /* VALIDATION ONLY AGENCY */
        $response = $this->openApiValidationService->validationTokenOnlyAgency($companyIDAgency);
        // info('validationTokenOnlyAgency', ['response' => $response]);
        if(($response['status'] ?? '') == 'error')
        {
            $resCode = $response['status_code'] ?? 400;
            $resSts = $response['status'];
            $resMsg = $response['message'] ?? "Something Went Wrong";
            $this->inserLog($request, $resCode, $resSts, $resMsg, $func, $companyIDAgency);
            return response()->json(['status' => $resSts, 'message' => $resMsg, 'status_code' => $resCode], $resCode);
        }
        /* VALIDATION ONLY AGENCY */

        /* GET TAGS INTEGRATION CLIENT */
        $response = $this->openApiIntegrationService->getTagIntegrations($integrationslug, $userid, $companyIDAgency);
        // info('getTagIntegrations', ['response' => $response]);
        if(($response['status'] ?? '') == 'error')
        {
            $resCode = $response['status_code'] ?? 400;
            $resSts = $response['status'];
            $resMsg = $response['message'] ?? "Something Went Wrong";
            $this->inserLog($request, $resCode, $resSts, $resMsg, $func, $companyIDAgency);
            return response()->json(['status' => $resSts, 'message' => $resMsg, 'status_code' => $resCode], $resCode);
        }
        $resData = $response['data'] ?? [];
        /* GET TAGS INTEGRATION CLIENT */

        /* RETURN */
        $resCode = 200;
        $resSts = 'success';
        $resMsg = '';
        $this->inserLog($request, $resCode, $resSts, $resMsg, $func, $companyIDAgency);
        return response()->json(['status' => $resSts, 'message' => $resMsg, 'tags' => $resData, 'status_code' => $resCode], $resCode);
        /* RETURN */
    }
    // =======INTEGRATION TARGS CLIENT END=======

    // =======INTEGRATION CAMPAIGN START=======
    /**
     * get integration campaign
     * @return \Illuminate\Http\JsonResponse
     */
    public function getCampaignIntegrations(string $campaignid, Request $request)
    {
        $openApiToken = $request->attributes->get('openApiToken');
        $companyIDAgency = $openApiToken->company_id ?? "";
        $func = __FUNCTION__;
        // info('', ['campaignid' => $campaignid, 'companyIDAgency' => $companyIDAgency]);

        /* VALIDATION ONLY AGENCY */
        $response = $this->openApiValidationService->validationTokenOnlyAgency($companyIDAgency);
        // info('validationTokenOnlyAgency', ['response' => $response]);
        if(($response['status'] ?? '') == 'error')
        {
            $resCode = $response['status_code'] ?? 400;
            $resSts = $response['status'];
            $resMsg = $response['message'] ?? "Something Went Wrong";
            $this->inserLog($request, $resCode, $resSts, $resMsg, $func, $companyIDAgency);
            return response()->json(['status' => $resSts, 'message' => $resMsg, 'status_code' => $resCode], $resCode);
        }
        /* VALIDATION ONLY AGENCY */

        /* PROCSS GET INTEGRATIONS CAMPAIGN */
        $response = $this->openApiIntegrationService->getCampaignIntegrations($campaignid, $companyIDAgency);
        // info('getCampaignIntegrations', ['response' => $response]);
        if(($response['status'] ?? '') == 'error')
        {
            $resCode = $response['status_code'] ?? 400;
            $resSts = $response['status'];
            $resMsg = $response['message'] ?? "Something Went Wrong";
            $this->inserLog($request, $resCode, $resSts, $resMsg, $func, $companyIDAgency);
            return response()->json(['status' => $resSts, 'message' => $resMsg, 'status_code' => $resCode], $resCode);
        }
        $resData = $response['data'] ?? [];
        /* PROCSS GET INTEGRATIONS CAMPAIGN */

        /* RETURN */
        $resCode = 200;
        $resSts = 'success';
        $resMsg = '';
        $this->inserLog($request, $resCode, $resSts, $resMsg, $func, $companyIDAgency);
        return response()->json(['status' => $resSts, 'message' => $resMsg, 'data' => $resData, 'status_code' => $resCode], $resCode);
        /* RETURN */
    }

    /**
     * create integration campaign
     * @return \Illuminate\Http\JsonResponse
     */
    public function createOrUpdateCampaignIntegration(string $campaignid, Request $request)
    {
        $openApiToken = $request->attributes->get('openApiToken');
        $companyIDAgency = $openApiToken->company_id ?? "";
        $func = __FUNCTION__;
        // info("public function $func", ['campaignid' => $campaignid, 'companyIDAgency' => $companyIDAgency]);

        /* VALIDATION ONLY AGENCY */
        $response = $this->openApiValidationService->validationTokenOnlyAgency($companyIDAgency);
        // info('validationTokenOnlyAgency', ['response' => $response]);
        if(($response['status'] ?? '') == 'error')
        {
            $resCode = $response['status_code'] ?? 400;
            $resSts = $response['status'];
            $resMsg = $response['message'] ?? "Something Went Wrong";
            $this->inserLog($request, $resCode, $resSts, $resMsg, $func, $companyIDAgency);
            return response()->json(['status' => $resSts, 'message' => $resMsg, 'status_code' => $resCode], $resCode);
        }
        /* VALIDATION ONLY AGENCY */

        /* PROCSS CREATE INTEGRATIONS CAMPAIGN */
        $response = $this->openApiIntegrationService->createOrUpdateCampaignIntegration($campaignid, $companyIDAgency, $request);
        // info('createOrUpdateCampaignIntegration', ['response' => $response]);
        if(($response['status'] ?? '') == 'error')
        {
            $resCode = $response['status_code'] ?? 400;
            $resSts = $response['status'];
            $resMsg = $response['message'] ?? "Something Went Wrong";
            $this->inserLog($request, $resCode, $resSts, $resMsg, $func, $companyIDAgency);
            return response()->json(['status' => $resSts, 'message' => $resMsg, 'status_code' => $resCode], $resCode);
        }
        $resMsg = $response['message'] ?? "Successfully Create Campaign Integration";
        /* PROCSS CREATE INTEGRATIONS CAMPAIGN */

        /* RETURN */
        $resCode = 200;
        $resSts = 'success';
        $this->inserLog($request, $resCode, $resSts, $resMsg, $func, $companyIDAgency);
        return response()->json(['status' => $resSts, 'message' => $resMsg, 'status_code' => $resCode], $resCode);
        /* RETURN */
    }
    // =======INTEGRATION CAMPAIGN END=======
    
    // =======AVAILABLE INTEGRATIONS START=======
    /**
     * get available integrations (master list)
     * @return \Illuminate\Http\JsonResponse
     */
    public function getAgencyAvailableIntegrations(Request $request)
    {
        $openApiToken = $request->attributes->get('openApiToken');
        $companyIDAgency = $openApiToken->company_id ?? "";
        $func = __FUNCTION__;

        /* VALIDATION ONLY AGENCY */
        $response = $this->openApiValidationService->validationTokenOnlyAgency($companyIDAgency);
        // info('validationTokenOnlyAgency', ['response' => $response]);
        if(($response['status'] ?? '') == 'error')
        {
            $resCode = $response['status_code'] ?? 400;
            $resSts = $response['status'];
            $resMsg = $response['message'] ?? "Something Went Wrong";
            $this->inserLog($request, $resCode, $resSts, $resMsg, $func, $companyIDAgency);
            return response()->json(['status' => $resSts, 'message' => $resMsg, 'status_code' => $resCode], $resCode);
        }
        /* VALIDATION ONLY AGENCY */

        /* PROCESS GET AVAILABLE INTEGRATIONS */
        $response = $this->openApiIntegrationService->getAgencyAvailableIntegrations();
        // info('getAgencyAvailableIntegrations', ['response' => $response]);
        if(($response['status'] ?? '') == 'error')
        {
            $resCode = $response['status_code'] ?? 400;
            $resSts = $response['status'];
            $resMsg = $response['message'] ?? "Something Went Wrong";
            $this->inserLog($request, $resCode, $resSts, $resMsg, $func, $companyIDAgency);
            return response()->json(['status' => $resSts, 'message' => $resMsg, 'status_code' => $resCode], $resCode);
        }
        $resData = $response['data'] ?? [];
        /* PROCESS GET AVAILABLE INTEGRATIONS */

        /* RETURN */
        $resCode = 200;
        $resSts = 'success';
        $resMsg = '';
        $this->inserLog($request, $resCode, $resSts, $resMsg, $func, $companyIDAgency);
        return response()->json(['status' => $resSts, 'message' => $resMsg, 'data' => $resData, 'status_code' => $resCode], $resCode);
        /* RETURN */
    }
    // =======AVAILABLE INTEGRATIONS END=======
    
    // =======HIDDEN INTEGRATIONS START=======
    /**
     * get hidden integrations for agency
     * @return \Illuminate\Http\JsonResponse
     */
    public function getAgencyHiddenIntegrations(Request $request)
    {
        $openApiToken = $request->attributes->get('openApiToken');
        $companyIDAgency = $openApiToken->company_id ?? "";
        $func = __FUNCTION__;

        /* VALIDATION ONLY AGENCY */
        $response = $this->openApiValidationService->validationTokenOnlyAgency($companyIDAgency);
        // info('validationTokenOnlyAgency', ['response' => $response]);
        if(($response['status'] ?? '') == 'error')
        {
            $resCode = $response['status_code'] ?? 400;
            $resSts = $response['status'];
            $resMsg = $response['message'] ?? "Something Went Wrong";
            $this->inserLog($request, $resCode, $resSts, $resMsg, $func, $companyIDAgency);
            return response()->json(['status' => $resSts, 'message' => $resMsg, 'status_code' => $resCode], $resCode);
        }
        /* VALIDATION ONLY AGENCY */

        /* PROCESS GET HIDDEN INTEGRATIONS */
        $response = $this->openApiIntegrationService->getAgencyHiddenIntegrations($companyIDAgency);
        // info('getAgencyHiddenIntegrations', ['response' => $response]);
        if(($response['status'] ?? '') == 'error')
        {
            $resCode = $response['status_code'] ?? 400;
            $resSts = $response['status'];
            $resMsg = $response['message'] ?? "Something Went Wrong";
            $this->inserLog($request, $resCode, $resSts, $resMsg, $func, $companyIDAgency);
            return response()->json(['status' => $resSts, 'message' => $resMsg, 'status_code' => $resCode], $resCode);
        }
        $resData = $response['data'] ?? [];
        /* PROCESS GET HIDDEN INTEGRATIONS */

        /* RETURN */
        $resCode = 200;
        $resSts = 'success';
        $resMsg = '';
        $this->inserLog($request, $resCode, $resSts, $resMsg, $func, $companyIDAgency);
        return response()->json(['status' => $resSts, 'message' => $resMsg, 'data' => $resData, 'status_code' => $resCode], $resCode);
        /* RETURN */
    }

    /**
     * create or update hidden integrations for agency
     * @return \Illuminate\Http\JsonResponse
     */
    public function createOrUpdateAgencyHiddenIntegrations(Request $request)
    {
        info('masuk createOrUpdateAgencyHiddenIntegrations');
        info('request', $request->all());
        $openApiToken = $request->attributes->get('openApiToken');
        $companyIDAgency = $openApiToken->company_id ?? "";
        $func = __FUNCTION__;
        // info("public function $func", ['companyIDAgency' => $companyIDAgency]);

        /* VALIDATION ONLY AGENCY */
        $response = $this->openApiValidationService->validationTokenOnlyAgency($companyIDAgency);
        info('validationTokenOnlyAgency', ['response' => $response]);
        if(($response['status'] ?? '') == 'error')
        {
            $resCode = $response['status_code'] ?? 400;
            $resSts = $response['status'];
            $resMsg = $response['message'] ?? "Something Went Wrong";
            $this->inserLog($request, $resCode, $resSts, $resMsg, $func, $companyIDAgency);
            return response()->json(['status' => $resSts, 'message' => $resMsg, 'status_code' => $resCode], $resCode);
        }
        /* VALIDATION ONLY AGENCY */

        /* PROCESS CREATE OR UPDATE HIDDEN INTEGRATIONS */
        $response = $this->openApiIntegrationService->createOrUpdateAgencyHiddenIntegrations($companyIDAgency, $request);
        // info('createOrUpdateAgencyHiddenIntegrations', ['response' => $response]);
        if(($response['status'] ?? '') == 'error')
        {
            $resCode = $response['status_code'] ?? 400;
            $resSts = $response['status'];
            $resMsg = $response['message'] ?? "Something Went Wrong";
            $this->inserLog($request, $resCode, $resSts, $resMsg, $func, $companyIDAgency);
            return response()->json(['status' => $resSts, 'message' => $resMsg, 'status_code' => $resCode], $resCode);
        }
        $resMsg = $response['message'] ?? "Successfully Update Hidden Integrations";
        /* PROCESS CREATE OR UPDATE HIDDEN INTEGRATIONS */

        /* RETURN */
        $resCode = 200;
        $resSts = 'success';
        $this->inserLog($request, $resCode, $resSts, $resMsg, $func, $companyIDAgency);
        return response()->json(['status' => $resSts, 'message' => $resMsg, 'status_code' => $resCode], $resCode);
        /* RETURN */
    }
    // =======HIDDEN INTEGRATIONS END=======
    
    // =======INTEGRATION WEBHOOK START=======
    public function getWebhookEvents(Request $request)
    {
        $openApiToken = $request->attributes->get('openApiToken');
        $companyIDAgency = $openApiToken->company_id ?? "";
        $func = __FUNCTION__;
        // info("public function $func", ['campaignid' => $campaignid, 'companyIDAgency' => $companyIDAgency]);

        /* VALIDATION ONLY AGENCY */
        $response = $this->openApiValidationService->validationTokenOnlyAgency($companyIDAgency);
        // info('validationTokenOnlyAgency', ['response' => $response]);
        if(($response['status'] ?? '') == 'error')
        {
            $resCode = $response['status_code'] ?? 400;
            $resSts = $response['status'];
            $resMsg = $response['message'] ?? "Something Went Wrong";
            $this->inserLog($request, $resCode, $resSts, $resMsg, $func, $companyIDAgency);
            return response()->json(['status' => $resSts, 'message' => $resMsg, 'status_code' => $resCode], $resCode);
        }
        /* VALIDATION ONLY AGENCY */

        /* PROCSS GET WEBHOOK EVENTS */
        $response = $this->openApiWebhookService->getWebhookEvents();
        // info('getWebhookEvents', ['response' => $response]);
        if(($response['status'] ?? '') == 'error')
        {
            $resCode = $response['status_code'] ?? 400;
            $resSts = $response['status'];
            $resMsg = $response['message'] ?? "Something Went Wrong";
            $this->inserLog($request, $resCode, $resSts, $resMsg, $func, $companyIDAgency);
            return response()->json(['status' => $resSts, 'message' => $resMsg, 'status_code' => $resCode], $resCode);
        }
        $resData = $response['data'] ?? [];
        /* PROCSS GET WEBHOOK EVENTS */

        /* RETURN */
        $resCode = 200;
        $resSts = 'success';
        $resMsg = '';
        $this->inserLog($request, $resCode, $resSts, $resMsg, $func, $companyIDAgency);
        return response()->json(['status' => $resSts, 'message' => $resMsg, 'data' => $resData, 'status_code' => $resCode], $resCode);
        /* RETURN */
    }

    public function getWebhookEndpoints(Request $request)
    {
        $openApiToken = $request->attributes->get('openApiToken');
        $companyIDAgency = $openApiToken->company_id ?? "";
        $func = __FUNCTION__;
        // info("public function $func", ['campaignid' => $campaignid, 'companyIDAgency' => $companyIDAgency]);

        /* VALIDATION ONLY AGENCY */
        $response = $this->openApiValidationService->validationTokenOnlyAgency($companyIDAgency);
        // info('validationTokenOnlyAgency', ['response' => $response]);
        if(($response['status'] ?? '') == 'error')
        {
            $resCode = $response['status_code'] ?? 400;
            $resSts = $response['status'];
            $resMsg = $response['message'] ?? "Something Went Wrong";
            $this->inserLog($request, $resCode, $resSts, $resMsg, $func, $companyIDAgency);
            return response()->json(['status' => $resSts, 'message' => $resMsg, 'status_code' => $resCode], $resCode);
        }
        /* VALIDATION ONLY AGENCY */

        /* PROCSS GET WEBHOOK ENDPOINTS */
        $response = $this->openApiWebhookService->getWebhookEndpoints($companyIDAgency);
        // info('getWebhookEndpoints', ['response' => $response]);
        if(($response['status'] ?? '') == 'error')
        {
            $resCode = $response['status_code'] ?? 400;
            $resSts = $response['status'];
            $resMsg = $response['message'] ?? "Something Went Wrong";
            $this->inserLog($request, $resCode, $resSts, $resMsg, $func, $companyIDAgency);
            return response()->json(['status' => $resSts, 'message' => $resMsg, 'status_code' => $resCode], $resCode);
        }
        $resData = $response['data'] ?? [];
        /* PROCSS GET WEBHOOK ENDPOINTS */

        /* RETURN */
        $resCode = 200;
        $resSts = 'success';
        $resMsg = '';
        $this->inserLog($request, $resCode, $resSts, $resMsg, $func, $companyIDAgency);
        return response()->json(['status' => $resSts, 'message' => $resMsg, 'data' => $resData, 'status_code' => $resCode], $resCode);
        /* RETURN */
    }

    public function createWebhookEndpoint(Request $request)
    {
        $openApiToken = $request->attributes->get('openApiToken');
        $companyIDAgency = $openApiToken->company_id ?? "";
        $func = __FUNCTION__;
        // info("public function $func", ['campaignid' => $campaignid, 'companyIDAgency' => $companyIDAgency]);

        /* VALIDATION ONLY AGENCY */
        $response = $this->openApiValidationService->validationTokenOnlyAgency($companyIDAgency);
        // info('validationTokenOnlyAgency', ['response' => $response]);
        if(($response['status'] ?? '') == 'error')
        {
            $resCode = $response['status_code'] ?? 400;
            $resSts = $response['status'];
            $resMsg = $response['message'] ?? "Something Went Wrong";
            $this->inserLog($request, $resCode, $resSts, $resMsg, $func, $companyIDAgency);
            return response()->json(['status' => $resSts, 'message' => $resMsg, 'status_code' => $resCode], $resCode);
        }
        /* VALIDATION ONLY AGENCY */

        /* PROCSS GET WEBHOOK ENDPOINTS */
        $response = $this->openApiWebhookService->createWebhookEndpoint($companyIDAgency, $request);
        // info('createWebhookEndpoint', ['response' => $response]);
        if(($response['status'] ?? '') == 'error')
        {
            $resCode = $response['status_code'] ?? 400;
            $resSts = $response['status'];
            $resMsg = $response['message'] ?? "Something Went Wrong";
            $this->inserLog($request, $resCode, $resSts, $resMsg, $func, $companyIDAgency);
            return response()->json(['status' => $resSts, 'message' => $resMsg, 'status_code' => $resCode], $resCode);
        }
        $webhookEndpointID = $response['webhookEndpointID'] ?? '';
        /* PROCSS GET WEBHOOK ENDPOINTS */

        /* RETURN */
        $resCode = 200;
        $resSts = 'success';
        $resMsg = 'Webhook Endpoint Created Successfully';
        $this->inserLog($request, $resCode, $resSts, $resMsg, $func, $companyIDAgency);
        return response()->json(['status' => $resSts, 'message' => $resMsg, 'endpoint_id' => $webhookEndpointID, 'status_code' => $resCode], $resCode);
        /* RETURN */
    }

    public function updateWebhookEndpoint(string $endpointid, Request $request)
    {
        $openApiToken = $request->attributes->get('openApiToken');
        $companyIDAgency = $openApiToken->company_id ?? "";
        $func = __FUNCTION__;
        // info("public function $func", ['campaignid' => $campaignid, 'companyIDAgency' => $companyIDAgency]);

        /* VALIDATION ONLY AGENCY */
        $response = $this->openApiValidationService->validationTokenOnlyAgency($companyIDAgency);
        // info('validationTokenOnlyAgency', ['response' => $response]);
        if(($response['status'] ?? '') == 'error')
        {
            $resCode = $response['status_code'] ?? 400;
            $resSts = $response['status'];
            $resMsg = $response['message'] ?? "Something Went Wrong";
            $this->inserLog($request, $resCode, $resSts, $resMsg, $func, $companyIDAgency);
            return response()->json(['status' => $resSts, 'message' => $resMsg, 'status_code' => $resCode], $resCode);
        }
        /* VALIDATION ONLY AGENCY */

        /* PROCSS GET WEBHOOK ENDPOINTS */
        $response = $this->openApiWebhookService->updateWebhookEndpoint($endpointid, $companyIDAgency, $request);
        // info('createWebhookEndpoint', ['response' => $response]);
        if(($response['status'] ?? '') == 'error')
        {
            $resCode = $response['status_code'] ?? 400;
            $resSts = $response['status'];
            $resMsg = $response['message'] ?? "Something Went Wrong";
            $this->inserLog($request, $resCode, $resSts, $resMsg, $func, $companyIDAgency);
            return response()->json(['status' => $resSts, 'message' => $resMsg, 'status_code' => $resCode], $resCode);
        }
        /* PROCSS GET WEBHOOK ENDPOINTS */

        /* RETURN */
        $resCode = 200;
        $resSts = 'success';
        $resMsg = 'Webhook Endpoint Updated Successfully';
        $this->inserLog($request, $resCode, $resSts, $resMsg, $func, $companyIDAgency);
        return response()->json(['status' => $resSts, 'message' => $resMsg, 'endpoint_id' => (int) $endpointid, 'status_code' => $resCode], $resCode);
        /* RETURN */
    }

    public function deleteWebhookEndpoint(string $endpointid, Request $request)
    {
        $openApiToken = $request->attributes->get('openApiToken');
        $companyIDAgency = $openApiToken->company_id ?? "";
        $func = __FUNCTION__;
        // info("public function $func", ['campaignid' => $campaignid, 'companyIDAgency' => $companyIDAgency]);

        /* VALIDATION ONLY AGENCY */
        $response = $this->openApiValidationService->validationTokenOnlyAgency($companyIDAgency);
        // info('validationTokenOnlyAgency', ['response' => $response]);
        if(($response['status'] ?? '') == 'error')
        {
            $resCode = $response['status_code'] ?? 400;
            $resSts = $response['status'];
            $resMsg = $response['message'] ?? "Something Went Wrong";
            $this->inserLog($request, $resCode, $resSts, $resMsg, $func, $companyIDAgency);
            return response()->json(['status' => $resSts, 'message' => $resMsg, 'status_code' => $resCode], $resCode);
        }
        /* VALIDATION ONLY AGENCY */

        /* PROCSS GET WEBHOOK ENDPOINTS */
        $response = $this->openApiWebhookService->deleteWebhookEndpoint($endpointid, $companyIDAgency, $request);
        // info('createWebhookEndpoint', ['response' => $response]);
        if(($response['status'] ?? '') == 'error')
        {
            $resCode = $response['status_code'] ?? 400;
            $resSts = $response['status'];
            $resMsg = $response['message'] ?? "Something Went Wrong";
            $this->inserLog($request, $resCode, $resSts, $resMsg, $func, $companyIDAgency);
            return response()->json(['status' => $resSts, 'message' => $resMsg, 'status_code' => $resCode], $resCode);
        }
        /* PROCSS GET WEBHOOK ENDPOINTS */

        /* RETURN */
        $resCode = 200;
        $resSts = 'success';
        $resMsg = 'Webhook Endpoint Deleted Successfully';
        $this->inserLog($request, $resCode, $resSts, $resMsg, $func, $companyIDAgency);
        return response()->json(['status' => $resSts, 'message' => $resMsg, 'endpoint_id' => (int) $endpointid, 'status_code' => $resCode], $resCode);
        /* RETURN */
    }
    // =======INTEGRATION WEBHOOK END=======

    // =======LIST USER START=======
    /**
     * get list client with open api
     * @return \Illuminate\Http\JsonResponse
     */
    public function getListClientOpenApi(string $usertype, string $search_keyword='', Request $request)
    {
        // info(['all_request' => $request->all()]);
        $openApiToken = $request->attributes->get('openApiToken');
        $companyIDAgency = $openApiToken->company_id ?? "";
        $func = __FUNCTION__;
        // info('', ['campaignid' => $campaignid, 'companyIDAgency' => $companyIDAgency]);

        if(!in_array($usertype, ['client','agency','admin','sales'])){
            return response()->json(['status' => 'error', 'message' => "User Type Must Be Client Or Agency Or Admin Or Sales",'status_code' => 400], 400);
        }

        if($usertype == 'agency' || $usertype == 'client'){
            $getListUserAgencyOrClient = $this->openApiUserGetService->getListUserAgencyOrClient($usertype, $search_keyword, $request);
            $resSts = $getListUserAgencyOrClient['status'];
            $resMsg = $getListUserAgencyOrClient['message'] ?? "Something Went Wrong";
            $resCode = $getListUserAgencyOrClient['status_code'] ?? 400;
            $response = $getListUserAgencyOrClient['response'] ?? [];

            $this->inserLog($request, $resCode, $resSts, $resMsg, $func, $companyIDAgency);
            if($resSts == 'error'){
                return response()->json(['status' => 'error', 'message' => $resMsg, 'status_code' => $resCode], $resCode);
            }
            return response()->json($response);
        }elseif($usertype == 'admin'){
            // return $this->getListUserAdmin($search_keyword, $request);
            $getListUserAdmin = $this->openApiUserGetService->getListUserAdmin($search_keyword, $request);
            $resSts = $getListUserAdmin['status'];
            $resMsg = $getListUserAdmin['message'] ?? "Something Went Wrong";
            $resCode = $getListUserAdmin['status_code'] ?? 400;
            $response = $getListUserAdmin['response'] ?? [];

            $this->inserLog($request, $resCode, $resSts, $resMsg, $func, $companyIDAgency);
            if($resSts == 'error'){
                return response()->json(['status' => 'error', 'message' => $resMsg, 'status_code' => $resCode], $resCode);
            }
            return response()->json($response);
        }elseif($usertype == 'sales'){
            // return $this->getListUserSales($search_keyword, $request);

            $getListUserSales = $this->openApiUserGetService->getListUserSales($search_keyword, $request);
            $resSts = $getListUserSales['status'];
            $resMsg = $getListUserSales['message'] ?? "Something Went Wrong";
            $resCode = $getListUserSales['status_code'] ?? 400;
            $response = $getListUserSales['response'] ?? [];

            $this->inserLog($request, $resCode, $resSts, $resMsg, $func, $companyIDAgency);
            if($resSts == 'error'){
                return response()->json(['status' => 'error', 'message' => $resMsg, 'status_code' => $resCode], $resCode);
            }
            return response()->json($response);
        }
    }
    // =======LIST USER START=======

    public function getListCampaign(string $campaignType, Request $request) 
    {
        // info(['all_request' => $request->all()]);
        $openApiToken = $request->attributes->get('openApiToken');
        $companyIDAgency = $openApiToken->company_id ?? "";
        $func = __FUNCTION__;
        // info('', ['campaignid' => $campaignid, 'companyIDAgency' => $companyIDAgency]);

        /* VALIDATION ONLY AGENCY */
        $response = $this->openApiValidationService->validationTokenOnlyAgency($companyIDAgency);
        // info('validationTokenOnlyAgency', ['response' => $response]);
        if(($response['status'] ?? '') == 'error'){
            $resCode = $response['status_code'] ?? 400;
            $resSts = $response['status'];
            $resMsg = $response['message'] ?? "Something Went Wrong";
            $this->inserLog($request, $resCode, $resSts, $resMsg, $func, $companyIDAgency);
            return response()->json(['status' => $resSts, 'message' => $resMsg, 'status_code' => $resCode], $resCode);
        }
        /* VALIDATION ONLY AGENCY */

        /* GET LIST CAMPAIGN */
        $response = $this->openApiCampaignGetService->getListCampaign($campaignType, $companyIDAgency, $request);
        // info('getTagIntegrations', ['response' => $response]);
        if(($response['status'] ?? '') == 'error'){
            $resCode = $response['status_code'] ?? 400;
            $resSts = $response['status'];
            $resMsg = $response['message'] ?? "Something Went Wrong";
            $this->inserLog($request, $resCode, $resSts, $resMsg, $func, $companyIDAgency);
            return response()->json(['status' => $resSts, 'message' => $resMsg, 'status_code' => $resCode], $resCode);
        }
        $current_page = $response['current_page'] ?? 0;
        $last_page = $response['last_page'] ?? 0;
        $per_page = $response['per_page'] ?? 0;
        $total = $response['total'] ?? 0;
        $resData = $response['data'] ?? [];
        /* GET LIST CAMPAIGN */

        /* RETURN */
        $resCode = 200;
        $resSts = 'success';
        $resMsg = 'success get campaign';
        $this->inserLog($request, $resCode, $resSts, $resMsg, $func, $companyIDAgency);
        return response()->json(['status' => $resSts,'message' => $resMsg,'status_code' => $resCode,'current_page' => $current_page,'last_page' => $last_page,'per_page' => $per_page,'total' => $total,'data' => $resData], $resCode);
        /* RETURN */
    }

    public function getCampaign(string $campaignId, Request $request) 
    {
        // info(['all_request' => $request->all()]);
        $openApiToken = $request->attributes->get('openApiToken');
        $companyIDAgency = $openApiToken->company_id ?? "";
        $func = __FUNCTION__;
        // info('', ['campaignid' => $campaignid, 'companyIDAgency' => $companyIDAgency]);

        /* VALIDATION ONLY AGENCY */
        $response = $this->openApiValidationService->validationTokenOnlyAgency($companyIDAgency);
        // info('validationTokenOnlyAgency', ['response' => $response]);
        if(($response['status'] ?? '') == 'error'){
            $resCode = $response['status_code'] ?? 400;
            $resSts = $response['status'];
            $resMsg = $response['message'] ?? "Something Went Wrong";
            $this->inserLog($request, $resCode, $resSts, $resMsg, $func, $companyIDAgency);
            return response()->json(['status' => $resSts, 'message' => $resMsg, 'status_code' => $resCode], $resCode);
        }
        /* VALIDATION ONLY AGENCY */

        /* GET LIST CAMPAIGN */
        $response = $this->openApiCampaignGetService->getCampaign($campaignId, $companyIDAgency, $request);
        // info('getTagIntegrations', ['response' => $response]);
        if(($response['status'] ?? '') == 'error'){
            $resCode = $response['status_code'] ?? 400;
            $resSts = $response['status'];
            $resMsg = $response['message'] ?? "Something Went Wrong";
            $this->inserLog($request, $resCode, $resSts, $resMsg, $func, $companyIDAgency);
            return response()->json(['status' => $resSts, 'message' => $resMsg, 'status_code' => $resCode], $resCode);
        }
        $resData = $response['data'] ?? [];
        /* GET LIST CAMPAIGN */

        /* RETURN */
        $resCode = 200;
        $resSts = 'success';
        $resMsg = 'success get campaign';
        $this->inserLog($request, $resCode, $resSts, $resMsg, $func, $companyIDAgency);
        return response()->json(['status' => $resSts,'message' => $resMsg,'status_code' => $resCode,'data' => $resData], $resCode);
        /* RETURN */
    }

    public function getUser(string $userType, string $userId, Request $request) {
        /* GET ATTRIBUTE */
        $openApiToken = $request->attributes->get('openApiToken');
        /* VARIABLE */

        /* CHECK TYPE CAMPAIGN */
        if(!in_array($userType, ['client','admin'])) {
            $resSts     = 'error';
            $resCode    = 400;
            $resMsg     = 'User Type Must Be Client Or Admin...';
            $this->inserLog($request, 400, $resSts, $resMsg, 'getUser');
            return response()->json([
                'status' => $resSts, 
                'message' => $resMsg,
                'status_code' => $resCode
            ], $resCode);
        } else {
            $type = '---';
            if ($userType == 'client') {
                $type = 'client'; 
            } else if ($userType == 'admin') {
                $type = 'user'; 
            } 
            return $this->getUserAgencyOrClient($type, $userId, $request);
        }

    }

    /**
     * get user agency admin or client
     * @return \Illuminate\Http\JsonResponse
     */
    public function getUserAgencyOrClient(string $userType, string $userId, Request $request) {
        /* GET ATTRIBUTE */
        $openApiToken = $request->attributes->get('openApiToken');
        /* VARIABLE */
        $companyID = $openApiToken->company_id; // company id agency
        $response = [];

        // CHECK USER TYPE MUST BE AGENCY
        $companyIDRoots = User::where('company_parent',null)
                        ->pluck('company_id')
                        ->toArray();

        // CHECK USER TYPE MUST BE AGENCY
        if(in_array($companyID, $companyIDRoots)) {
            $resSts     = 'error';
            $resCode    = 422;
            $resMsg     = 'Your Token Does Not Have The Ability To Get User';
        } else {
            // GET CAMPAIGN
            $user = User::select(
                                    'users.user_type', 'users.name', 'users.email', 'users.phonenum', 'users.phone_country_code', 'users.phone_country_calling_code',
                                    'users.address', 'users.city', 'users.zip', 'users.state_code', 'users.state_name', 'users.isAdmin',
                                    'companies.company_name', 'companies.company_address', 'companies.company_city', 'companies.company_zip', 'companies.company_state_code', 'companies.company_state_name',
                                    'companies.email as company_email', 'users.defaultadmin', 'users.admin_get_notification', 'users.customercare', 'roles.role_name',
                                    'users.disable_client_add_campaign', 'users.disabled_receive_email', 'users.enabled_phone_number', 'users.editor_spreadsheet',
                                    // 'user_state.state as user_state', 'company_state.state as company_state', 
                                    'users.state_code', 'users.country_code', 'companies.company_state_code', 'companies.company_country_code',
                                    'companies.subdomain', 'users.referralcode',
                                    DB::raw("case when users.user_type = 'user' then 'Admin Agency'
                                              when users.user_type = 'userdownline' then 'Owner Agency'
                                              when users.user_type = 'client' then 'Client' end as type_of_user")
                                )
                        ->join('companies', 'users.company_id', '=', 'companies.id')
                        // ->leftJoin('states as user_state',DB::raw("CAST(CONVERT(AES_DECRYPT(FROM_bASE64(users.state_code), '8e651522e38256f2') USING utf8mb4) AS BINARY)"),'=','user_state.state_code')
                        // ->leftJoin('states as company_state',DB::raw("CAST(CONVERT(AES_DECRYPT(FROM_bASE64(companies.company_state_code), '8e651522e38256f2') USING utf8mb4) AS BINARY)"),'=','company_state.state_code')
                        ->leftJoin('roles', 'users.role_id', '=', 'roles.id')
                        ->where('users.id', $userId)
                        // ->where('users.user_type', $userType)
                        ->where(function ($query) use ($userType) {
                            if ($userType == 'user') {
                                $query->whereIn('users.user_type', ['user', 'userdownline']);
                            } else {
                                $query->where('users.user_type', $userType);
                            }
                            
                        })
                        ->where('users.active', 'T')
                        ->where(function ($query) use ($companyID) {
                            $query->where('users.company_id', $companyID)
                                  ->orWhere('users.company_parent', $companyID);
                        })
                        ->first();
            // return $user;
            // Log::info('', ['campaign' => $campaign]);

            if(empty($user)) {
                $resSts     = 'error';
                $resCode    = 404;
                $resMsg     = 'User Not Found';
            } else {
                // /* GET DATA */
                $typeUser   = $user->user_type;

                $response['user_type']          = $user->type_of_user;
                $response['name']               = $user->name;
                $response['email']              = $user->email;
                $response['phonenum']           = $user->phonenum ?? '';
                $response['phone_country_code'] = $user->phone_country_code ?? '';
                $response['phone_country_calling_code'] = $user->phone_country_calling_code ?? '';
                $response['address']            = $user->address ?? '';
                $response['city']               = $user->city ?? '';
                $response['zip']                = $user->zip ?? '';
                $response['user_country']       = $user->country_code ?? '';
                $response['user_state']         = $user->state_code ?? '';
                $response['company_name']       = $user->company_name ?? '';
                $response['company_address']    = $user->company_address ?? '';
                $response['company_city']       = $user->company_city ?? '';
                $response['company_zip']        = $user->company_zip ?? '';
                $response['company_country']    = $user->company_country_code ?? '';
                $response['company_state']      = $user->company_state_code ?? '';
                $response['company_email']      = $user->company_email ?? '';

                if ($typeUser == 'user' || $typeUser == 'userdownline') {
                    $response['is_admin']       = $this->trueOrFalse($user->isAdmin);
                    $response['default_admin']  = $this->trueOrFalse($user->defaultadmin);
                    $response['notif_admin']    = $this->trueOrFalse($user->admin_get_notification);
                    $response['customer_care']  = $this->trueOrFalse($user->customercare);
                    $response['role_name']      = $user->role_name;
                }

                if ($typeUser == 'client') {
                    $response['disable_add_campaign']   = $this->trueOrFalse($user->disable_client_add_campaign);
                    $response['disabled_receive_email'] = $this->trueOrFalse($user->disabled_receive_email);
                    $response['disable_receive_email'] = $this->trueOrFalse($user->disabled_receive_email);
                    $response['enable_phone_number_on_campaign'] = $this->trueOrFalse($user->enabled_phone_number);
                    $response['enable_google_sheet_editor'] = $this->trueOrFalse($user->editor_spreadsheet);
                }

                $resSts     = 'success';
                $resCode    = 200;
                $resMsg     = 'Success get User';
                // $response   = $user;
            }
        }

        //// insert logs 
        OpenApiLogs::create([
            'method_req' => $request->method(),
            'endpoint' => $request->fullUrl(),
            'function_req' => 'getUserAgencyOrClient',
            'token' => $request->bearerToken(),
            'content_type' => $request->header('Content-Type'),
            'request' => json_encode($request->all()),
            'response_code' => $resCode,
            'response_status' => $resSts,
            'response_message' => json_encode($resMsg),
            'company_id' => $companyID,
            'ip_address' => $request->ip()
        ]);

        if ($resCode == 200) {
            return response()->json([
                'status' => $resSts, 
                'message' => $resMsg,
                'status_code' => $resCode,
                'data' => $response
            ], $resCode);
        } else {
            return response()->json([
                'status' => $resSts, 
                'message' => $resMsg,
                'status_code' => $resCode
            ], $resCode);
        }
        /* GET USER */
    }
    
     /**
     * archive campaign
     * @return \Illuminate\Http\JsonResponse
     */
    public function campaignArchive(Request $request) {
        /* GET ATTRIBUTE */
        $openApiToken = $request->attributes->get('openApiToken');
        /* VARIABLE */
        $companyID = $openApiToken->company_id; // company id agency

        // CHECK USER TYPE MUST BE AGENCY
        $companyIDRoots = User::where('company_parent',null)
                        ->pluck('company_id')
                        ->toArray();

        // CHECK USER TYPE MUST BE AGENCY
        if(in_array($companyID, $companyIDRoots)) {
            $resSts     = 'error';
            $resCode    = 422;
            $resMsg     = 'Your Token Does Not Have The Ability To Get User';
        } else {
             // VALIDATION
             $rules = [
                'leadspeek_api_id' => ['required', 'numeric']
            ];
            $validator = Validator::make($request->all(), $rules);

            if($validator->fails()) {                                   //// if validator false
                $dtMsg = json_decode($validator->messages(), true);
                $tx_validator = $this->get_tx_validator($dtMsg);
                
                $resCode    = 422;
                $resSts     = 'error';
                $resMsg     = $tx_validator;
            } else {
                // check campaign
                $campaignId = $request->leadspeek_api_id;
                
                $campaign = LeadspeekUser::select('leadspeek_users.id')
                                            ->join('users', 'users.id', '=', 'leadspeek_users.user_id')
                                            ->join('companies', 'users.company_id', '=', 'companies.id')
                                            ->where('leadspeek_users.leadspeek_api_id', $campaignId)
                                            ->where('leadspeek_users.company_id', $companyID)
                                            ->where('leadspeek_users.archived', 'F')
                                            ->first();

                if(empty($campaign)) {
                    $resSts     = 'error';
                    $resCode    = 404;
                    $resMsg     = 'Campaign Not Found';
                } else {
                    // go to archive campaign
                    $data = new Request([
                        'lpuserid' => $campaign->id,
                        'status' => 'T',
                    ]);

                    try {
                        $returndata = $this->leadspeekController->archivecampaign($data);
                        if($returndata != null) {
                            $returndata = $returndata->getData();

                            if ($returndata->result == 'success'){
                                
                                $resCode    = 200;
                                $resSts     = 'success';
                                $resMsg     = 'Success Archive Campaign';
                                $resData    = $returndata;
                            } else {
                                $resCode    = 404;
                                $resSts     = 'warning';
                                $resMsg     = 'Error Archive Campaign.';
                            }
                        } else {
                            $resCode    = 509;
                            $resSts     = 'error';
                            $resMsg     = 'Error When Archive Campaign.';
                        }
                    } catch (\Exception $e) {
                        $resCode    = 555;
                        $resSts     = 'error';
                        $resMsg     = $e->getMessage();
                    }

                }
            }
            

        }

        //// insert logs 
        OpenApiLogs::create([
            'method_req' => $request->method(),
            'endpoint' => $request->fullUrl(),
            'function_req' => 'campaignArchive',
            'token' => $request->bearerToken(),
            'content_type' => $request->header('Content-Type'),
            'request' => '',
            'response_code' => $resCode,
            'response_status' => $resSts,
            'response_message' => json_encode($resMsg),
            'company_id' => $companyID,
            'ip_address' => $request->ip()
        ]);

        return response()->json([
            'status' => $resSts, 
            'message' => $resMsg,
            'status_code' => $resCode
        ], $resCode);

        /* ARCHIVE CAMPAIGN */
    }

    public function changePasswordUser(string $userType, string $userId, Request $request) {
        /* GET ATTRIBUTE */
        $openApiToken = $request->attributes->get('openApiToken');
        /* VARIABLE */
        $companyID = $openApiToken->company_id; // company id agency

        // CHECK USER TYPE MUST BE AGENCY
        $companyIDRoots = User::where('company_parent',null)
                        ->pluck('company_id')
                        ->toArray();

        /* CHECK TYPE CAMPAIGN */
        if(!in_array($userType, ['client','admin','sales'])) {
            $resSts     = 'error';
            $resCode    = 400;
            $resMsg     = 'User Type Must Be Client Or Admin Or Sales';

            $this->inserLog($request, 400, $resSts, $resMsg, 'changePasswordUser', $companyID);

            return response()->json([
                'status' => $resSts, 
                'message' => $resMsg,
                'status_code' => $resCode
            ], $resCode);
        } else {
            if ($userType == 'admin') {     
                //// admin ga perlu pengecekan token, karna ada admin root dan agency, 
                //// nanti di function bawah di where sesuai companyID dari token
                $type = 'user'; 
                return $this->changePasswd($type, $userId, $request);
            } else if ($userType == 'client') {
                $type = $userType; 
                // CHECK USER TYPE MUST BE AGENCY
                if (in_array($companyID, $companyIDRoots)) {
                    $resSts     = 'error';
                    $resCode    = 422;
                    $resMsg     = 'Your Token Does Not Have The Ability To Update Password User Client';

                    $this->inserLog($request, 400, $resSts, $resMsg, 'changePasswordUser', $companyID);

                    return response()->json([
                        'status' => $resSts, 
                        'message' => $resMsg,
                        'status_code' => $resCode
                    ], $resCode);
                } else {
                    return $this->changePasswd($type, $userId, $request);
                }
            } else if ($userType == 'sales') {
                $type = $userType; 
                // CHECK USER TYPE MUST BE ROOT
                if (!in_array($companyID, $companyIDRoots)) {
                    $resSts     = 'error';
                    $resCode    = 422;
                    $resMsg     = 'Your Token Does Not Have The Ability To Update Password User Sales';

                    $this->inserLog($request, 400, $resSts, $resMsg, 'changePasswordUser', $companyID);

                    return response()->json([
                        'status' => $resSts, 
                        'message' => $resMsg,
                        'status_code' => $resCode
                    ], $resCode);
                } else {
                    return $this->changePasswd($type, $userId, $request);
                }
            } 

        }
        
    }

    private function changePasswd(string $userType, string $userId, Request $request) {
        /* GET ATTRIBUTE */
        $openApiToken = $request->attributes->get('openApiToken');
        /* VARIABLE */
        $companyID = $openApiToken->company_id; // company id agency

        $rules = [];
        $rules['current_password'] = ['required'];
        $rules['new_password'] = ['required','min:6','max:30','confirmed'];
        // $rules['new_password_confirmation '] = ['required','min:6'];
        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            $dtMsg = json_decode($validator->messages(), true);
            $tx_validator = $this->get_tx_validator($dtMsg);

            $resCode = 422;
            $resSts  = 'error';
            $resMsg  = $tx_validator;
        } else {
            $currpass = $request->current_password;
            $newpass = $request->new_password;

            // $currpass = Hash::make(trim($request->current_password));
            // $newpass =  Hash::make(trim($request->new_password));

            $user = User::select('password')
                    ->where('id','=',$userId)
                    ->where('user_type','=',$userType)
                    ->where('active','=','T')
                    ->where(function ($query) use ($companyID) {
                        $query->where('company_id', $companyID)
                                ->orWhere('company_parent', $companyID);
                    })
                    ->first();

            if (empty($user)) {
                $resSts     = 'error';
                $resCode    = 404;
                $resMsg     = 'User Not Found';
            } else {
                $xdata = new Request([
                    'usrID' => $userId,
                    'currpassword' => $currpass,
                    'newpassword' => $newpass
                ]);

                try {
                    $chgPass = $this->userController->resetpassword($xdata);

                    if ($chgPass != null) {
                        $dt = $chgPass->getData(true);
                        $result = $dt['result'] ?? '';
                        if($result == 'success') {
                            $resCode    = 200;
                            $resSts     = 'success';
                            $resMsg     = 'Success Updating Password';
                        } else {
                            $resCode    = 509;
                            $resSts     = $result;
                            $resMsg     = $dt['message'] ?? 'Error';
                        }
                    } else {
                        $resCode    = 509;
                        $resSts     = 'error';
                        $resMsg     =  'Success Updating Password.';
                    }
                } catch (\Exception $e) {
                    $resCode    = 555;
                    $resSts     = 'error';
                    $resMsg     = $e->getMessage();
                }
            }
        }

        //// insert logs 
        OpenApiLogs::create([
            'method_req' => $request->method(),
            'endpoint' => $request->fullUrl(),
            'function_req' => 'changePasswd',
            'token' => $request->bearerToken(),
            'content_type' => $request->header('Content-Type'),
            'request' => json_encode($request->all()),
            'response_code' => $resCode,
            'response_status' => $resSts,
            'response_message' => json_encode($resMsg),
            'company_id' => $companyID,
            'ip_address' => $request->ip()
        ]);

        return response()->json([
            'status' => $resSts, 
            'message' => $resMsg,
            'status_code' => $resCode
        ], $resCode);

    }

    public function changePasswordSelf(Request $request) {
        /* GET ATTRIBUTE */
        $openApiToken = $request->attributes->get('openApiToken');
        /* VARIABLE */
        $companyID = $openApiToken->company_id; // company id agency
        // return $openApiToken;


        $rules = [];
        $rules['current_password'] = ['required'];
        $rules['new_password'] = ['required','min:6','max:30','confirmed'];
        // $rules['new_password_confirmation '] = ['required','min:6'];
        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            $dtMsg = json_decode($validator->messages(), true);
            $tx_validator = $this->get_tx_validator($dtMsg);

            $resCode = 422;
            $resSts  = 'error';
            $resMsg  = $tx_validator;
        } else {
            $currpass = $request->current_password;
            $newpass = $request->new_password;
            // $currpass = Hash::make(trim($request->current_password));
            // $newpass =  Hash::make(trim($request->new_password));

            $user = User::select('id')
                    ->where('company_id','=',$companyID)
                    ->where('user_type','=','userdownline')
                    ->where('active','=','T')
                    ->first();

            if (empty($user)) {
                $resSts     = 'error';
                $resCode    = 404;
                $resMsg     = 'User Not Found';
            } else {
                // return $user->id;
                $xdata = new Request([
                    'usrID' => $user->id,
                    'currpassword' => $currpass,
                    'newpassword' => $newpass
                ]);

                try {
                    $chgPass = $this->userController->resetpassword($xdata);

                    if ($chgPass != null) {
                        $dt = $chgPass->getData(true);
                        $result = $dt['result'] ?? '';
                        if($result == 'success') {
                            $resCode    = 200;
                            $resSts     = 'success';
                            $resMsg     = 'Success Updating Password';
                        } else {
                            $resCode    = 509;
                            $resSts     = $result;
                            $resMsg     = $dt['message'] ?? 'Error';
                        }
                    } else {
                        $resCode    = 509;
                        $resSts     = 'error';
                        $resMsg     =  'Success Updating Password.';
                    }
                } catch (\Exception $e) {
                    $resCode    = 555;
                    $resSts     = 'error';
                    $resMsg     = $e->getMessage();
                }
            }
        }
        

        //// insert logs 
        OpenApiLogs::create([
            'method_req' => $request->method(),
            'endpoint' => $request->fullUrl(),
            'function_req' => 'changePasswordSelf',
            'token' => $request->bearerToken(),
            'content_type' => $request->header('Content-Type'),
            'request' => json_encode($request->all()),
            'response_code' => $resCode,
            'response_status' => $resSts,
            'response_message' => json_encode($resMsg),
            'company_id' => $companyID,
            'ip_address' => $request->ip()
        ]);

        return response()->json([
            'status' => $resSts, 
            'message' => $resMsg,
            'status_code' => $resCode
        ], $resCode);

    }

     /**
     * get user sales
     * @return \Illuminate\Http\JsonResponse
     */
    public function getUserSales(string $userId, Request $request) {
        /* GET ATTRIBUTE */
        $openApiToken = $request->attributes->get('openApiToken');
        /* VARIABLE */
        $companyID = $openApiToken->company_id; // company id agency
        $response = [];

        // CHECK USER TYPE MUST BE AGENCY
        $companyIDRoots = User::where('company_parent',null)
                        ->pluck('company_id')
                        ->toArray();

        // CHECK USER TYPE MUST BE AGENCY
        if(!in_array($companyID, $companyIDRoots)) {
            $resSts     = 'error';
            $resCode    = 422;
            $resMsg     = 'Your Token Does Not Have The Ability To Get User Sales';
        } else {
            // GET CAMPAIGN
            $user = User::select(
                                    'users.user_type', 'users.name', 'users.email', 'users.phonenum', 'users.phone_country_code', 'users.phone_country_calling_code',
                                    'users.address', 'users.city', 'users.zip', 'users.state_code', 'users.state_name', 'users.isAdmin',
                                    'companies.company_name', 'companies.company_address', 'companies.company_city', 'companies.company_zip', 'companies.company_state_code', 'companies.company_state_name',
                                    'companies.email as company_email', 'users.defaultadmin', 'users.admin_get_notification', 'users.customercare', 'roles.role_name',
                                    'users.disable_client_add_campaign', 'users.disabled_receive_email',
                                    'user_state.state as state_user', 'company_state.state as state_company', 'companies.subdomain', 'users.referralcode',
                                    DB::raw("DATE_FORMAT(users.created_at, '%m-%d-%Y') as create_at"),
                                )
                        ->join('companies', 'users.company_parent', '=', 'companies.id')
                        ->leftJoin('states as user_state','users.state_code','=','user_state.state_code')
                        ->leftJoin('states as company_state','companies.company_state_code','=','company_state.state_code')
                        ->leftJoin('roles', 'users.role_id', '=', 'roles.id')
                        ->where('users.id', $userId)
                        ->where('users.company_parent', $companyID)
                        ->where('users.active', 'T')
                        ->where('users.user_type', 'sales')
                        // ->where(function ($query) use ($companyID) {
                        //     $query->where('users.company_id', $companyID)
                        //           ->orWhere('users.company_parent', $companyID);
                        // })
                        ->first();
            // return $user;
            // Log::info('', ['campaign' => $campaign]);

            if(empty($user)) {
                $resSts     = 'error';
                $resCode    = 404;
                $resMsg     = 'User Sales Not Found';
            } else {
                // /* GET DATA */
                $typeUser   = $user->user_type;

                $response['user_type']          = $typeUser;
                $response['name']               = $user->name;
                $response['company']            = $user->company_name;
                $response['email']              = $user->email;
                $response['phonenum']           = $user->phonenum ?? '';
                $response['phone_country_code'] = $user->phone_country_code ?? '';
                $response['phone_country_calling_code'] = $user->phone_country_calling_code ?? '';
                $response['created_at']         = $user->create_at ?? '';
               
                $domain     = $user->subdomain ? $user->subdomain : $user->domain;
                $refercode  = $user->referralcode;
                $response['referral_link']      = $refercode ? 'https://' . $domain . "/agency-register/" . $refercode : '';

                $resSts     = 'success';
                $resCode    = 200;
                $resMsg     = 'Success get User';
                // $response   = $user;
            }
        }

        //// insert logs 
        OpenApiLogs::create([
            'method_req' => $request->method(),
            'endpoint' => $request->fullUrl(),
            'function_req' => 'getUserSales',
            'token' => $request->bearerToken(),
            'content_type' => $request->header('Content-Type'),
            'request' => json_encode($request->all()),
            'response_code' => $resCode,
            'response_status' => $resSts,
            'response_message' => json_encode($resMsg),
            'company_id' => $companyID,
            'ip_address' => $request->ip()
        ]);

        if ($resCode == 200) {
            return response()->json([
                'status' => $resSts, 
                'message' => $resMsg,
                'status_code' => $resCode,
                'data' => $response
            ], $resCode);
        } else {
            return response()->json([
                'status' => $resSts, 
                'message' => $resMsg,
                'status_code' => $resCode
            ], $resCode);
        }
        /* GET USER SALES */
    }

    // =======CONTACTS START=======
    public function getContactsByCampaignId(string $campaignid, Request $request)
    {
        // info(['campaignid' => $campaignid, 'all_request' => $request->all()]);
        $openApiToken = $request->attributes->get('openApiToken');
        $companyIDAgency = $openApiToken->company_id ?? "";
        $func = __FUNCTION__;
        // info('', ['campaignid' => $campaignid, 'companyIDAgency' => $companyIDAgency]);

        /* VALIDATION ONLY AGENCY */
        $response = $this->openApiValidationService->validationTokenOnlyAgency($companyIDAgency);
        // info('validationTokenOnlyAgency', ['response' => $response]);
        if(($response['status'] ?? '') == 'error')
        {
            $resCode = $response['status_code'] ?? 400;
            $resSts = $response['status'];
            $resMsg = $response['message'] ?? "Something Went Wrong";
            $this->inserLog($request, $resCode, $resSts, $resMsg, $func, $companyIDAgency);
            return response()->json(['status' => $resSts, 'message' => $resMsg, 'status_code' => $resCode], $resCode);
        }
        /* VALIDATION ONLY AGENCY */

        /* GET CONTACTS BY CAMPAIGN ID */
        $response = $this->openApiContactsService->getContactsByCampaignId($campaignid, $companyIDAgency, $request);
        // info('getTagIntegrations', ['response' => $response]);
        if(($response['status'] ?? '') == 'error')
        {
            $resCode = $response['status_code'] ?? 400;
            $resSts = $response['status'];
            $resMsg = $response['message'] ?? "Something Went Wrong";
            $this->inserLog($request, $resCode, $resSts, $resMsg, $func, $companyIDAgency);
            return response()->json(['status' => $resSts, 'message' => $resMsg, 'status_code' => $resCode], $resCode);
        }
        $current_page = $response['current_page'] ?? 0;
        $last_page = $response['last_page'] ?? 0;
        $per_page = $response['per_page'] ?? 0;
        $total = $response['total'] ?? 0;
        $resData = $response['data'] ?? [];
        /* GET CONTACTS BY CAMPAIGN ID */

        /* RETURN */
        $resCode = 200;
        $resSts = 'success';
        $resMsg = '';
        $this->inserLog($request, $resCode, $resSts, $resMsg, $func, $companyIDAgency);
        return response()->json(['status' => $resSts,'message' => $resMsg,'status_code' => $resCode,'current_page' => $current_page,'last_page' => $last_page,'per_page' => $per_page,'total' => $total,'data' => $resData], $resCode);
        /* RETURN */
    }

    public function getContactsByClientId(string $clientid, string $campaigntype, Request $request)
    {
        // info(['all_request' => $request->all()]);
        $openApiToken = $request->attributes->get('openApiToken');
        $companyIDAgency = $openApiToken->company_id ?? "";
        $func = __FUNCTION__;
        // info('', ['campaignid' => $campaignid, 'companyIDAgency' => $companyIDAgency]);

        /* VALIDATION ONLY AGENCY */
        $response = $this->openApiValidationService->validationTokenOnlyAgency($companyIDAgency);
        // info('validationTokenOnlyAgency', ['response' => $response]);
        if(($response['status'] ?? '') == 'error')
        {
            $resCode = $response['status_code'] ?? 400;
            $resSts = $response['status'];
            $resMsg = $response['message'] ?? "Something Went Wrong";
            $this->inserLog($request, $resCode, $resSts, $resMsg, $func, $companyIDAgency);
            return response()->json(['status' => $resSts, 'message' => $resMsg, 'status_code' => $resCode], $resCode);
        }
        /* VALIDATION ONLY AGENCY */

        /* GET CONTACTS BY CLIENT ID */
        $response = $this->openApiContactsService->getContactsByClientId($clientid, $campaigntype, $companyIDAgency, $request);
        // info('getTagIntegrations', ['response' => $response]);
        if(($response['status'] ?? '') == 'error')
        {
            $resCode = $response['status_code'] ?? 400;
            $resSts = $response['status'];
            $resMsg = $response['message'] ?? "Something Went Wrong";
            $this->inserLog($request, $resCode, $resSts, $resMsg, $func, $companyIDAgency);
            return response()->json(['status' => $resSts, 'message' => $resMsg, 'status_code' => $resCode], $resCode);
        }
        $current_page = $response['current_page'] ?? 0;
        $last_page = $response['last_page'] ?? 0;
        $per_page = $response['per_page'] ?? 0;
        $total = $response['total'] ?? 0;
        $resData = $response['data'] ?? [];
        /* GET CONTACTS BY CLIENT ID */

        /* RETURN */
        $resCode = 200;
        $resSts = 'success';
        $resMsg = '';
        $this->inserLog($request, $resCode, $resSts, $resMsg, $func, $companyIDAgency);
        return response()->json(['status' => $resSts,'message' => $resMsg,'status_code' => $resCode,'current_page' => $current_page,'last_page' => $last_page,'per_page' => $per_page,'total' => $total,'data' => $resData], $resCode);
        /* RETURN */
    }

    public function getContactById(string $contactid, Request $request)
    {
        // info(['all_request' => $request->all()]);
        $openApiToken = $request->attributes->get('openApiToken');
        $companyIDAgency = $openApiToken->company_id ?? "";
        $func = __FUNCTION__;
        // info('', ['campaignid' => $campaignid, 'companyIDAgency' => $companyIDAgency]);

        /* VALIDATION ONLY AGENCY */
        $response = $this->openApiValidationService->validationTokenOnlyAgency($companyIDAgency);
        // info('validationTokenOnlyAgency', ['response' => $response]);
        if(($response['status'] ?? '') == 'error')
        {
            $resCode = $response['status_code'] ?? 400;
            $resSts = $response['status'];
            $resMsg = $response['message'] ?? "Something Went Wrong";
            $this->inserLog($request, $resCode, $resSts, $resMsg, $func, $companyIDAgency);
            return response()->json(['status' => $resSts, 'message' => $resMsg, 'status_code' => $resCode], $resCode);
        }
        /* VALIDATION ONLY AGENCY */

        /* GET CONTACTS BY CONTACT ID */
        $response = $this->openApiContactsService->getContactById($contactid, $companyIDAgency, $request);
        // info('getTagIntegrations', ['response' => $response]);
        if(($response['status'] ?? '') == 'error')
        {
            $resCode = $response['status_code'] ?? 400;
            $resSts = $response['status'];
            $resMsg = $response['message'] ?? "Something Went Wrong";
            $this->inserLog($request, $resCode, $resSts, $resMsg, $func, $companyIDAgency);
            return response()->json(['status' => $resSts, 'message' => $resMsg, 'status_code' => $resCode], $resCode);
        }
        $resData = $response['data'] ?? [];
        /* GET CONTACTS BY CONTACT ID */

        /* RETURN */
        $resCode = 200;
        $resSts = 'success';
        $resMsg = '';
        $this->inserLog($request, $resCode, $resSts, $resMsg, $func, $companyIDAgency);
        return response()->json(['status' => $resSts,'message' => $resMsg,'status_code' => $resCode,'data' => $resData], $resCode);
        /* RETURN */
    }
    // =======CONTACTS END=======

    // =======LEADCONECTOR START=======
    public function getSubAccountsLeadConnector(Request $request)
    {
        // info(['all_request' => $request->all()]);
        $openApiToken = $request->attributes->get('openApiToken');
        $companyIDAgency = $openApiToken->company_id ?? "";
        $func = __FUNCTION__;
        // info('', ['campaignid' => $campaignid, 'companyIDAgency' => $companyIDAgency]);

        /* VALIDATION ONLY AGENCY */
        $response = $this->openApiValidationService->validationTokenOnlyAgency($companyIDAgency);
        // info('validationTokenOnlyAgency', ['response' => $response]);
        if(($response['status'] ?? '') == 'error')
        {
            $resCode = $response['status_code'] ?? 400;
            $resSts = $response['status'];
            $resMsg = $response['message'] ?? "Something Went Wrong";
            $this->inserLog($request, $resCode, $resSts, $resMsg, $func, $companyIDAgency);
            return response()->json(['status' => $resSts, 'message' => $resMsg, 'status_code' => $resCode], $resCode);
        }
        /* VALIDATION ONLY AGENCY */

        /* GET CONTACTS BY CONTACT ID */
        $response = $this->openApiLeadConnectorService->getSubAccounts($companyIDAgency, $request);
        // info('getTagIntegrations', ['response' => $response]);
        if(($response['status'] ?? '') == 'error')
        {
            $resCode = $response['status_code'] ?? 400;
            $resSts = $response['status'];
            $resMsg = $response['message'] ?? "Something Went Wrong";
            $this->inserLog($request, $resCode, $resSts, $resMsg, $func, $companyIDAgency);
            return response()->json(['status' => $resSts, 'message' => $resMsg, 'status_code' => $resCode], $resCode);
        }
        $current_page = $response['current_page'] ?? 0;
        $last_page = $response['last_page'] ?? 0;
        $per_page = $response['per_page'] ?? 0;
        $total = $response['total'] ?? 0;
        $resData = $response['data'] ?? [];
        /* GET CONTACTS BY CONTACT ID */

        /* RETURN */
        $resCode = 200;
        $resSts = 'success';
        $resMsg = '';
        $this->inserLog($request, $resCode, $resSts, $resMsg, $func, $companyIDAgency);
        return response()->json(['status' => $resSts,'message' => $resMsg,'status_code' => $resCode,'current_page' => $current_page,'last_page' => $last_page,'per_page' => $per_page,'total' => $total,'data' => $resData], $resCode);
        /* RETURN */
    }

    public function createIframeSubAccountLeadConnector(Request $request)
    {
        // info(['all_request' => $request->all()]);
        $openApiToken = $request->attributes->get('openApiToken');
        $companyIDAgency = $openApiToken->company_id ?? "";
        $func = __FUNCTION__;
        // info('', ['campaignid' => $campaignid, 'companyIDAgency' => $companyIDAgency]);

        /* VALIDATION ONLY AGENCY */
        $response = $this->openApiValidationService->validationTokenOnlyAgency($companyIDAgency);
        // info('validationTokenOnlyAgency', ['response' => $response]);
        if(($response['status'] ?? '') == 'error')
        {
            $resCode = $response['status_code'] ?? 400;
            $resSts = $response['status'];
            $resMsg = $response['message'] ?? "Something Went Wrong";
            $this->inserLog($request, $resCode, $resSts, $resMsg, $func, $companyIDAgency);
            return response()->json(['status' => $resSts, 'message' => $resMsg, 'status_code' => $resCode], $resCode);
        }
        /* VALIDATION ONLY AGENCY */

        /* CREATE IFRAME */
        $response = $this->openApiLeadConnectorService->createIframeSubAccount($companyIDAgency, $request);
        // info('getTagIntegrations', ['response' => $response]);
        if(($response['status'] ?? '') == 'error')
        {
            $resCode = $response['status_code'] ?? 400;
            $resSts = $response['status'];
            $resMsg = $response['message'] ?? "Something Went Wrong";
            $this->inserLog($request, $resCode, $resSts, $resMsg, $func, $companyIDAgency);
            return response()->json(['status' => $resSts, 'message' => $resMsg, 'status_code' => $resCode], $resCode);
        }
        $custom_menu_id = $response['custom_menu_id'] ?? '';
        $title = $response['title'] ?? '';
        /* CREATE IFRAME */

        /* RETURN */
        $resCode = 200;
        $resSts = 'success';
        $resMsg = 'Create Iframe Successfully';
        $this->inserLog($request, $resCode, $resSts, $resMsg, $func, $companyIDAgency);
        return response()->json(['status' => $resSts,'message' => $resMsg, 'status_code' => $resCode, 'iframe_id' => $custom_menu_id, 'iframe_name' => $title], $resCode);
        /* RETURN */
    }

    public function deleteIframeSubAccountLeadConnector(string $clientid, Request $request)
    {
        // info(['all_request' => $request->all()]);
        $openApiToken = $request->attributes->get('openApiToken');
        $companyIDAgency = $openApiToken->company_id ?? "";
        $func = __FUNCTION__;
        // info('', ['campaignid' => $campaignid, 'companyIDAgency' => $companyIDAgency]);

        /* VALIDATION ONLY AGENCY */
        $response = $this->openApiValidationService->validationTokenOnlyAgency($companyIDAgency);
        // info('validationTokenOnlyAgency', ['response' => $response]);
        if(($response['status'] ?? '') == 'error')
        {
            $resCode = $response['status_code'] ?? 400;
            $resSts = $response['status'];
            $resMsg = $response['message'] ?? "Something Went Wrong";
            $this->inserLog($request, $resCode, $resSts, $resMsg, $func, $companyIDAgency);
            return response()->json(['status' => $resSts, 'message' => $resMsg, 'status_code' => $resCode], $resCode);
        }
        /* VALIDATION ONLY AGENCY */

        /* DELETE IFRAME */
        $response = $this->openApiLeadConnectorService->deleteIframeSubAccount($clientid, $companyIDAgency, $request);
        // info('getTagIntegrations', ['response' => $response]);
        if(($response['status'] ?? '') == 'error')
        {
            $resCode = $response['status_code'] ?? 400;
            $resSts = $response['status'];
            $resMsg = $response['message'] ?? "Something Went Wrong";
            $this->inserLog($request, $resCode, $resSts, $resMsg, $func, $companyIDAgency);
            return response()->json(['status' => $resSts, 'message' => $resMsg, 'status_code' => $resCode], $resCode);
        }
        /* DELETE IFRAME */

        /* RETURN */
        $resCode = 200;
        $resSts = 'success';
        $resMsg = 'Delete Iframe Successfully';
        $this->inserLog($request, $resCode, $resSts, $resMsg, $func, $companyIDAgency);
        return response()->json(['status' => $resSts,'message' => $resMsg,'status_code' => $resCode], $resCode);
        /* RETURN */
    }
    // =======LEADCONECTOR END=======

    private function trueOrFalse($value) {
        if ($value === 'T') {
            return true;
        } else if ($value === 'F') {
            return false;
        } else {
            return false;
        }
    }

    private function inserLog(Request $request, int $resCode, string $resSts, string $resMsg, string $funct, ?string $companyID=null, ?string $userID=null, ?string $campaignID=null) 
    {
        OpenApiLogs::create([
            'method_req' => $request->method(),
            'endpoint' => $request->fullUrl(),
            'function_req' => $funct,
            'token' => $request->bearerToken(),
            'content_type' => $request->header('Content-Type'),
            'request' => json_encode($request->all(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'response_code' => $resCode,
            'response_status' => $resSts,
            'response_message' => is_array($resMsg) ? json_encode($resMsg, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : $resMsg,
            'company_id' => $companyID,
            'user_id' => $userID,
            'campaign_id' => $campaignID,
            'ip_address' => $request->ip()
        ]);
    }
}
