<?php

namespace App\Http\Controllers;

use Exception;
use Illuminate\Http\Request;
use App\Models\CompanySetting;
use App\Models\IntegrationList;
use App\Models\IntegrationSettings;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use App\Models\GlobalSettings;
use App\Models\LeadspeekUser;
use App\Models\campaignInformation;
use App\Models\Company;
use App\Models\IntegrationCustom;
use App\Models\SsoAccessToken;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use App\Services\SendJimService;
use ESolution\DBEncryption\Encrypter;

class IntegrationController extends Controller
{
    protected SendJimService $sendJimService;

    public function __construct(SendJimService $sendJimService)
    {
        $this->sendJimService = $sendJimService;
    }

    /**
     * Determine the agency company_id for checking hidden integrations
     * Logic:
     * - For clients: their company_parent is the agency company_id
     * - For agencies: their own company_id is the agency company_id
     * - For roots: their own company_id (though roots typically don't need this feature)
     * 
     * @param int|null $userCompanyId The user's company_id
     * @param int|null $userCompanyParent The user's company_parent
     * @param string|null $userType The user's user_type
     * @return int|null The agency company_id to check for hidden integrations
     */
    private function getAgencyCompanyIdForHiddenIntegrations($userCompanyId, $userCompanyParent, $userType)
    {
        // If user is a client, check the agency's (company_parent) hidden integrations
        if ($userType === 'client' && !empty($userCompanyParent)) {
            return $userCompanyParent;
        }
        
        // If user is an agency (userdownline with company_parent not null), check their own company_id
        // If user is a root (userdownline with company_parent null), check their own company_id
        if ($userType === 'userdownline' && !empty($userCompanyId)) {
            return $userCompanyId;
        }
        
        // Fallback: return the company_id if available
        return $userCompanyId;
    }

    /**
     * Get hidden integrations for the agency level
     * This checks the agency's CompanySetting for hidden_integrations
     * 
     * @param int|null $agencyCompanyId The agency company_id to check
     * @return array Array with 'ids' and 'slugs' keys containing hidden integration identifiers
     */
    public function getHiddenIntegrationsForAgency($agencyCompanyId)
    {
        $hiddenIntegrationIds = [];
        $hiddenIntegrationSlugs = [];

        if (empty($agencyCompanyId)) {
            return [
                'ids' => [],
                'slugs' => []
            ];
        }

        try {
            $hiddenSetting = CompanySetting::where('company_id', $agencyCompanyId)
                ->whereEncrypted('setting_name', 'hidden_integrations')
                ->first();

            if ($hiddenSetting) {
                Log::info('getHiddenIntegrationsForAgency - Found setting', [
                    'agency_company_id' => $agencyCompanyId,
                    'setting_value_raw' => $hiddenSetting->setting_value,
                ]);

                $data = json_decode($hiddenSetting->setting_value, true);
                
                // Check if JSON decode was successful
                if (json_last_error() !== JSON_ERROR_NONE) {
                    Log::warning('getHiddenIntegrationsForAgency - JSON decode error', [
                        'agency_company_id' => $agencyCompanyId,
                        'json_error' => json_last_error_msg(),
                        'setting_value' => $hiddenSetting->setting_value,
                    ]);
                    // Return empty arrays to show all integrations
                    return [
                        'ids' => [],
                        'slugs' => []
                    ];
                }

                // Check if structure has integrations array
                if (isset($data['integrations']) && is_array($data['integrations'])) {
                    // Handle empty array case explicitly
                    if (empty($data['integrations'])) {
                        // Log::info('getHiddenIntegrationsForAgency - Setting found but integrations array is empty (all integrations visible)', [
                        //     'agency_company_id' => $agencyCompanyId,
                        // ]);
                        // Return empty arrays to show all integrations
                        return [
                            'ids' => [],
                            'slugs' => []
                        ];
                    }
                    
                    foreach ($data['integrations'] as $integration) {
                        // Support both id and slug
                        if (isset($integration['id']) && !empty($integration['id'])) {
                            $hiddenIntegrationIds[] = $integration['id'];
                        }
                        if (isset($integration['slug']) && !empty($integration['slug'])) {
                            $hiddenIntegrationSlugs[] = $integration['slug'];
                        }
                    }
                    
                    Log::info('getHiddenIntegrationsForAgency - Parsed successfully', [
                        'agency_company_id' => $agencyCompanyId,
                        'hidden_ids' => $hiddenIntegrationIds,
                        'hidden_slugs' => $hiddenIntegrationSlugs,
                    ]);
                } else {
                    Log::warning('getHiddenIntegrationsForAgency - Invalid structure', [
                        'agency_company_id' => $agencyCompanyId,
                        'data_keys' => is_array($data) ? array_keys($data) : 'not_array',
                        'data' => $data,
                    ]);
                }
            } else {
                Log::info('getHiddenIntegrationsForAgency - No setting found', [
                    'agency_company_id' => $agencyCompanyId,
                ]);
            }
        } catch (\Exception $e) {
            // Log error but don't break the flow - return empty arrays to show all integrations
            Log::error('getHiddenIntegrationsForAgency - Exception', [
                'agency_company_id' => $agencyCompanyId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            // Return empty arrays to show all integrations
            return [
                'ids' => [],
                'slugs' => []
            ];
        }

        return [
            'ids' => array_unique($hiddenIntegrationIds),
            'slugs' => array_unique($hiddenIntegrationSlugs)
        ];
    }
    //
    public function getIntegrationList(Request $request){
        // $getintegration=IntegrationSettings::where('company_id', $request->company_id)
        //                             ->where('integration_slug', 'sendgrid')
        //                             ->first();
        //     if(!empty($getintegration->api_key) && ($getintegration->enable_sendgrid == 1))
        //     {
                 $data = IntegrationSettings::with('companyIntegrationDetails')
                ->where('company_id', $request->company_id)
                ->where('enable_sendgrid','=',1)
                ->select('id','company_id','integration_slug')
                ->get();

                $refcode = IntegrationList::select('slug','referralcode')
                                ->where('status','=',1)
                                ->where('referralcode','<>','')
                                ->get();

                $customs = IntegrationCustom::where('company_id', $request->company_parent)->get()->keyBy('integration_id');

                $customKeys = $customs->first()
                    ? collect($customs->first()->makeHidden(['company_id', 'integration_id', 'created_at', 'id', 'updated_at'])->toArray())->keys()
                    : collect();

                // Get agency company_id from request
                // Note: company_parent adalah agency company_id
                // company_id adalah client company_id (untuk query integrations yang enabled)
                $agencyCompanyId = $request->route('company_parent') ?? $request->company_parent ?? null;
                
                // Initialize hidden integrations arrays (default: empty - show all)
                $hiddenIntegrationIds = [];
                $hiddenIntegrationSlugs = [];
                
                // Only try to get hidden integrations if we have agencyCompanyId
                if (!empty($agencyCompanyId)) {
                    try {
                        Log::info('getIntegrationList - Agency Company ID', [
                            'company_id' => $request->route('company_id') ?? $request->company_id,
                            'company_parent_route' => $request->route('company_parent'),
                            'company_parent_request' => $request->company_parent,
                            'agencyCompanyId' => $agencyCompanyId,
                        ]);

                        // Get hidden integrations for the agency
                        $hiddenIntegrations = $this->getHiddenIntegrationsForAgency($agencyCompanyId);
                        $hiddenIntegrationIds = $hiddenIntegrations['ids'] ?? [];
                        $hiddenIntegrationSlugs = $hiddenIntegrations['slugs'] ?? [];
                        
                        Log::info('getIntegrationList - Hidden Integrations', [
                            'hiddenIntegrationIds' => $hiddenIntegrationIds,
                            'hiddenIntegrationSlugs' => $hiddenIntegrationSlugs,
                        ]);
                    } catch (\Exception $e) {
                        // If error getting hidden integrations, log but continue (show all integrations)
                        Log::warning('getIntegrationList - Error getting hidden integrations, showing all', [
                            'error' => $e->getMessage(),
                            'agencyCompanyId' => $agencyCompanyId,
                            'trace' => $e->getTraceAsString(),
                        ]);
                        // Ensure arrays are empty to show all
                        $hiddenIntegrationIds = [];
                        $hiddenIntegrationSlugs = [];
                    }
                } else {
                    Log::info('getIntegrationList - No agencyCompanyId, showing all integrations', [
                        'company_id' => $request->company_id,
                        'company_parent' => $request->company_parent,
                    ]);
                }

                $data->transform(function ($item) use ($customs, $customKeys) {
                    $integrationDetail = $item->companyIntegrationDetails;
                    $integrationDetailId = optional($integrationDetail)->id;

                    if ($integrationDetail) {
                        if ($integrationDetailId && isset($customs[$integrationDetailId])) {
                            $custom = clone $customs[$integrationDetailId];

                            foreach ($custom->makeHidden(['company_id', 'integration_id', 'created_at', 'id', 'updated_at'])->toArray() as $key => $value) {
                                $integrationDetail->setAttribute($key, $value);
                            }
                        } else {
                            // Inject key-key dari custom dengan value null
                            foreach ($customKeys as $key) {
                                $integrationDetail->setAttribute($key, null);
                            }
                        }
                    }

                    if($integrationDetail->slug == 'agencyzoom' && empty($integrationDetail->custom_img)) {
                        $integrationDetail->custom_img = "https://emmspaces.nyc3.cdn.digitaloceanspaces.com/systems/agencyzoom_logo.png";
                    }
                    if($integrationDetail->slug == 'sendjim' && empty($integrationDetail->custom_img)) {
                        // Force SendJim logo to the new brand asset
                        $integrationDetail->custom_img = "https://emmspaces.nyc3.cdn.digitaloceanspaces.com/systems/sendjimlogo.webp";
                    }

                    return $item;
                });

                // Filter out hidden integrations (only if we have hidden integrations)
                // IMPORTANT: Only filter if we have valid hidden integrations AND data is not empty
                if ((!empty($hiddenIntegrationIds) || !empty($hiddenIntegrationSlugs)) && $data->count() > 0) {
                    try {
                        $dataBeforeFilter = $data->count();
                        
                        // Normalize IDs to integers for comparison
                        $normalizedIds = array_map('intval', array_filter($hiddenIntegrationIds, function($id) {
                            return !empty($id) && (is_numeric($id) || is_int($id));
                        }));
                        $normalizedSlugs = array_filter($hiddenIntegrationSlugs, function($slug) {
                            return !empty($slug) && is_string($slug);
                        });
                        
                        Log::info('getIntegrationList - Normalized hidden integrations', [
                            'original_ids' => $hiddenIntegrationIds,
                            'original_slugs' => $hiddenIntegrationSlugs,
                            'normalized_ids' => $normalizedIds,
                            'normalized_slugs' => $normalizedSlugs,
                        ]);
                        
                        // Only filter if we have normalized values
                        if (!empty($normalizedIds) || !empty($normalizedSlugs)) {
                            $data = $data->filter(function ($item) use ($normalizedIds, $normalizedSlugs) {
                                // Safety check: if no integration details, keep the item
                                if (!$item || !$item->companyIntegrationDetails) {
                                    Log::warning('getIntegrationList - Item without companyIntegrationDetails', [
                                        'item_id' => $item->id ?? null,
                                    ]);
                                    return true; // Keep items without integration details
                                }
                                
                                $slug = $item->companyIntegrationDetails->slug ?? null;
                                $id = $item->companyIntegrationDetails->id ?? null;
                                
                                // Convert ID to int for comparison
                                $idInt = is_numeric($id) ? (int)$id : null;
                                
                                // Check if ID or slug is in hidden list
                                $isHiddenById = $idInt !== null && in_array($idInt, $normalizedIds, true);
                                $isHiddenBySlug = $slug !== null && in_array($slug, $normalizedSlugs, true);
                                
                                // Keep item if NOT hidden
                                $shouldKeep = !$isHiddenById && !$isHiddenBySlug;
                                
                                if (!$shouldKeep) {
                                    Log::info('getIntegrationList - Filtering out integration', [
                                        'id' => $id,
                                        'idInt' => $idInt,
                                        'slug' => $slug,
                                        'hidden_by_id' => $isHiddenById,
                                        'hidden_by_slug' => $isHiddenBySlug,
                                    ]);
                                }
                                
                                return $shouldKeep;
                            })->values(); // Reset collection keys to sequential
                            
                            Log::info('getIntegrationList - Filter Result', [
                                'before_filter' => $dataBeforeFilter,
                                'after_filter' => $data->count(),
                                'filtered_data' => $data->map(function($item) {
                                    return [
                                        'id' => $item->companyIntegrationDetails->id ?? null,
                                        'slug' => $item->companyIntegrationDetails->slug ?? null,
                                    ];
                                })->toArray(),
                            ]);
                        } else {
                            Log::info('getIntegrationList - No valid normalized values, skipping filter');
                        }
                    } catch (\Exception $e) {
                        // If error during filtering, log but continue (show all integrations)
                        Log::error('getIntegrationList - Error during filtering, showing all', [
                            'error' => $e->getMessage(),
                            'trace' => $e->getTraceAsString(),
                        ]);
                        // Don't modify $data - keep all integrations
                    }
                } else {
                    Log::info('getIntegrationList - No filtering needed', [
                        'has_hidden_ids' => !empty($hiddenIntegrationIds),
                        'has_hidden_slugs' => !empty($hiddenIntegrationSlugs),
                        'data_count' => $data->count(),
                    ]);
                }

                
                
                if(count($data) > 0){
                    return response()->json(array('result'=>'success','status_code'=>200,'data'=>$data,'refcode'=>$refcode,'message'=>'Integration List'));
                }else{
                return response()->json(array('result'=>'success','status_code'=>200,'data'=>'','refcode'=>$refcode,'message'=>'Nothing in Integration list'));
                }
            // }
            // return response()->json(array('result'=>'success','status_code'=>200,'message'=>'Agency do not have any Integration'));

    }
    public function getClientIntegrationDetails(Request $request){
        $data = IntegrationSettings::with('companyIntegrationDetails')
            ->where('company_id', $request->company_id);
            if (trim($request->slug) != 'all') {
                $data->where('integration_slug', $request->slug);
            }
            $data = $data->get();
            
        if (!empty($data)) {
            foreach ($data as $key => $value) {
                if ($data[$key]['integration_slug'] == 'zapier' || $data[$key]['integration_slug'] == 'webhook') {
                    $urls = explode('|', $value['api_key']);
                    $data[$key]['api_key'] = $urls;
                    if (isset($value['webhook_labels']) && !empty($value['webhook_labels'])) {
                        $labels = explode('|', $value['webhook_labels']);
                        while (count($labels) < count($urls)) {
                            $labels[] = '';
                        }
                        $data[$key]['webhook_labels'] = array_slice($labels, 0, count($urls));
                    } else {
                        $data[$key]['webhook_labels'] = array_fill(0, count($urls), '');
                    }
                }

                if ($data[$key]['integration_slug'] == 'agencyzoom') {
                    $urls = explode('|', $value['api_key']);
                    $data[$key]['api_key'] = $urls;
                }

                if (isset($value->custom_fields) && !empty($value->custom_fields)) {
                    $custom_fields = json_decode($value->custom_fields, true);
                    $data[$key]->custom_fields = ($data[$key]['integration_slug'] != 'sendjim') ? $custom_fields : null;
                }

                if($data[$key]['integration_slug'] === 'clickfunnels'){
                    $global_settings = GlobalSettings::where('setting_name', 'custom_fields_b2b_clickfunnels')
                                                ->where('company_id', $request->company_id)
                                                ->first();
                                                
                    if(isset($global_settings->setting_value) && !empty($global_settings->setting_value)){
                        $data[$key]->custom_fields_b2b = json_decode($global_settings->setting_value, true);
                    } else {
                        $data[$key]->custom_fields_b2b = null;
                    }
                }

                if($data[$key]['integration_slug'] === 'gohighlevel'){
                    $global_settings_b2b = GlobalSettings::where('setting_name', 'custom_fields_b2b_gohighlevel')
                                                ->where('company_id', $request->company_id)
                                                ->first();
                                                
                    if(isset($global_settings_b2b->setting_value) && !empty($global_settings_b2b->setting_value)){
                        $data[$key]->custom_fields_b2b = json_decode($global_settings_b2b->setting_value, true);
                    } else {
                        $data[$key]->custom_fields_b2b = null;
                    }

                    $global_settings_advance = GlobalSettings::where('setting_name', 'custom_fields_advance_gohighlevel')
                                                ->where('company_id', $request->company_id)
                                                ->first();
                                                
                    if(isset($global_settings_advance->setting_value) && !empty($global_settings_advance->setting_value)){
                        $data[$key]->custom_fields_advance = json_decode($global_settings_advance->setting_value, true);
                    } else {
                        $data[$key]->custom_fields_advance = null;
                    }

                    $data[$key]->version = (isset($value->version) && !empty($value->version) && trim($value->version) != '') ? (string) $value->version : '1';
                    
                    $data[$key]->ghlV2Connected = false;
                    $tokens = (isset($value->tokens)) ? json_decode($value->tokens) : '';
                    if(isset($tokens->access_token) && isset($tokens->refresh_token)) {
                        $data[$key]->ghlV2Connected = true;
                    }
                }

                if($data[$key]['integration_slug'] === 'sendjim') {
                    // expose simple connected flag for frontend (true if tokens exist)
                    $tokens = isset($value->tokens) ? trim((string)$value->tokens) : '';
                    $data[$key]->connected = (!empty($tokens));
                }


                unset($data[$key]->companyIntegrationDetails->custom_fields);
                unset($data[$key]->tokens);
            }
        }

        $refcode = IntegrationList::select('slug','referralcode')
                                ->where('status','=',1)
                                ->where('referralcode','<>','')
                                ->get();

        /* CHECK CLIENT HAS CREATE IFRAME */
        $systemid = config('services.application.systemid');   
        $ghlV2CreatedIframe = Company::select('companies.*', 'users.company_parent')
                                     ->join('users', 'users.company_id', '=', 'companies.id')
                                     ->where('companies.id', $request->company_id)
                                     ->where('users.company_root_id', $systemid)
                                     ->where('users.user_type', 'client')
                                     ->where('companies.ghl_custom_menus', '<>', '')
                                     ->whereNotNull('companies.ghl_custom_menus')
                                     ->exists();
        /* CHECK CLIENT HAS CREATE IFRAME */

        /* CHECK CLIENT HAS CREATE IFRAME YOUR ACCOUNT */
        $ghl_custom_menus_client_your_account = $this->getcompanysetting($request->company_id, 'ghl_custom_menus_client_your_account');
        // info(__FUNCTION__, ['ghl_custom_menus_client_your_account' => $ghl_custom_menus_client_your_account]);
        $ghlV2CreatedIframeYourAccount = !empty($ghl_custom_menus_client_your_account);
        // info(__FUNCTION__, ['ghlV2CreatedIframeYourAccount' => $ghlV2CreatedIframeYourAccount]);
        /* CHECK CLIENT HAS CREATE IFRAME YOUR ACCOUNT */

        if(count($data) > 0){
            return response()->json(array('result'=>'success','status_code'=>200,'data'=>$data,'refcode'=>$refcode,'ghlV2CreatedIframe'=>$ghlV2CreatedIframe,'ghlV2CreatedIframeYourAccount'=>$ghlV2CreatedIframeYourAccount,'message'=>'Integration List'));
        }

        return response()->json(array('result'=>'success','status_code'=>200,'data'=>'','refcode'=>$refcode,'ghlV2CreatedIframe'=>$ghlV2CreatedIframe,'ghlV2CreatedIframeYourAccount'=>$ghlV2CreatedIframeYourAccount,'message'=>'Nothing in Integration list'));
    }

    public function getListCustomFields(Request $request) {
        try {
            $custom_fields = IntegrationList::select('slug', 'custom_fields')
                                            ->where('status', '=', 1)
                                            ->get();
    
            foreach ($custom_fields as $item) {
                if (!empty($item->custom_fields)) {
                    $item->custom_fields = json_decode($item->custom_fields, true);
                    if (isset($item->slug) && $item->slug == 'gohighlevel') {
                        $item->custom_fields = array_map(function($category){
                            if(isset($category['type']) && $category['type'] == 'advance'){
                                $desc = campaignInformation::where('status', 'active')->where('campaign_type','enhance')->where('type',$category['category'])
                                                            ->first();
                                if (!empty($desc)) {
                                    $category['description'] = json_decode($desc->description);
                                }else {
                                    $category['description'] = [];
                                }                                                       
                            }
                            return $category;
                        }, $item->custom_fields);
                    }
                }
            }
    
            return response()->json([
                'result' => 'success',
                'status_code' => 200,
                'data' => $custom_fields,
                'message' => 'Successfully get list custom fields'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'result' => 'failed',
                'status_code' => 500,
                'message' => 'Failed to get list custom fields',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function getCustomFieldsBySlug(Request $request) {
        $slug = (isset($request->slug))?$request->slug:"";

        try {
            $integrationList = IntegrationList::select('slug', 'custom_fields')
                                            ->where('status', '=', 1)
                                            ->where('slug', $slug)
                                            ->first();

            if($integrationList){
                $integrationList->custom_fields = json_decode($integrationList->custom_fields);
                if (isset($slug) && $slug == 'gohighlevel') {
                    $integrationList->custom_fields = array_map(function($category){
                        if(isset($category->type) && $category->type == 'advance'){
                            $desc = campaignInformation::where('status', 'active')->where('campaign_type','enhance')->where('type',$category->category)
                                                        ->first();
                            if (!empty($desc)) {
                                $category->description = json_decode($desc->description);
                            }else {
                                $category->description = [];
                            }                                                       
                        }
                        return $category;
                    }, $integrationList->custom_fields);
                }
            } else {
                return response()->json([
                    'result' => 'failed',
                    'status_code' => 404,
                    'message' => 'Integration not found',
                ], 404);
            }     
            
            return response()->json([
                'result' => 'success',
                'status_code' => 200,
                'data' => $integrationList,
                'message' => 'Successfully get custom fields'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'result' => 'failed',
                'status_code' => 500,
                'message' => 'Failed to get list custom fields',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function getStatusKeywordToTags(Request $request) {
        $leadspeek_api_id = $request->input('leadspeek_api_id', '');
        $slug = $request->input('slug', '');

        if(!$leadspeek_api_id){
            return response()->json([
                'result' => 'failed',
                'status_code' => 400,
                'message' => 'The leadspeek_api_id field is required.',
            ], 400);
        }

        if(!$slug){
            return response()->json([
                'result' => 'failed',
                'status_code' => 400,
                'message' => 'The slug field is required.',
            ], 400);
        }

        $query_select = "";
        if($slug == 'gohighlevel'){
            $query_select = 'ghl_status_keyword_to_tags';
        }

        if(!$query_select){
            return response()->json([
                'result' => 'failed',
                'status_code' => 400,
                'message' => 'This feature is not yet available in this integration.',
            ], 400);
        }

        try {
            $campaign = LeadspeekUser::select($query_select)
                                        ->where('leadspeek_api_id', $leadspeek_api_id)
                                        ->first();
            
            $status_keyword_to_tags = false;
            if ($campaign) {
                $status_keyword_to_tags = (bool) $campaign->$query_select;
            }

            return response()->json([
                'result' => 'success',
                'status_code' => 200,
                'data' => $status_keyword_to_tags,
                'message' => 'Successfully get status keyword to tags'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'result' => 'failed',
                'status_code' => 500,
                'message' => 'Failed to get status keyword to tags',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    protected function kartraCredentialValidater($request,$param){
        // Initialize cURL session
        $ch = curl_init();
        // Set the API endpoint
        $api_endpoint = "https://app.kartra.com/api";

        // Set the POST data with your API credentials and action
        $post_data = [
            'app_id' => config('services.kartra.kartraAppID') ,
            'api_key' => $request->api_key,
            'api_password' => $request->password,
            'actions' => [ $param  ]
        ];

        // Configure cURL options
        $curl_options = [
            CURLOPT_URL => $api_endpoint,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($post_data), // Convert the array to URL-encoded format
            CURLOPT_RETURNTRANSFER => true, // Return the response as a string
        ];

        // Apply cURL options
        curl_setopt_array($ch, $curl_options);

        // Execute the cURL request
        $server_output = curl_exec($ch);

        // Check for errors
        if (curl_errno($ch)) {
            echo 'cURL error: ' . curl_error($ch);
        } else {
            // Process the server output as needed
            return  json_decode($server_output, true); // 'true' for associative arrays
        }

        // Close the cURL session
        curl_close($ch);
}
    // to save Integration settings for companies

    public function saveIntegration(Request $request) // save disini saat di client management dan saat view as client
    {
        // info(__FUNCTION__, ['all' => $request->all()]);
        $data = IntegrationSettings::where('company_id', $request->company_id)
                                   ->where('integration_slug', $request->integration_slug)
                                   ->first();

        if($request->integration_slug=="kartra")
        {
            // Validate the incoming request data
            $validatedData = $request->validate([
                'api_key' => 'required|string',
                'password' => 'required|string',
                'company_id' => 'required|integer',
                'enable_sendgrid' => 'required|boolean',
                'enable_default_campaign' => 'required|boolean',
                'integration_slug' => 'required|string',
            ]);

            $param=[ 'cmd' => 'retrieve_account_lists'] ;
            $responce = $this->kartraCredentialValidater( $request ,$param);
            if($responce['status']!="Success"){
                return response()->json([
                    'result' => 'error',
                    'status_code' => 400,
                    'message' => 'Invalid credential.'
                ]);
            }
            $this->kartraCreateCustomField( $request );

            // kartraCreateCustomField function will create if costom field is not yet created. better ti display the syatus through the UI
            //can be modity in future .
            if ($validatedData['integration_slug'] === 'kartra') 
            {
                // Store the data in the database (example using Eloquent)
                $integrationData = [
                    'api_key' => $validatedData['api_key'],
                    'app_id' => config('service.kartra.kartraAppID'),
                    'password' => $validatedData['password'],
                    'company_id' => $validatedData['company_id'],
                    'enable_sendgrid' => $validatedData['enable_sendgrid'],// Need to change like "enable"
                    'enable_default_campaign' => $validatedData['enable_default_campaign'],// Need to change like "enable"
                    'integration_slug' => $validatedData['integration_slug']
                ];
              
                if(empty($data)) {
                    $integrationData['enable_sendgrid'] = 1;
                }

                // Assuming you have a model `Integration` and you're updating based on `company_id` or `integration_slug`
                $integration = IntegrationSettings::updateOrCreate(
                    ['integration_slug' => 'kartra', 'company_id' => $validatedData['company_id']],
                    $integrationData
                );

                // Return a successful response
                $response = [];
                if(empty($data)) {
                    $response = [
                        'result' => 'success',
                        'status_code' => 200,
                        'message' => 'Kartra integration details updated successfully!',
                        'enable_sendgrid'=> ($integrationData['enable_sendgrid'] == 1)
                    ];
                } else {
                    $response = [
                        'result' => 'success',
                        'status_code' => 200,
                        'message' => 'Kartra integration details updated successfully!',
                    ];
                }
                return response()->json($response);
            } 
            else 
            {
                // If the integration_slug doesn't match 'kartra', return an error response
                return response()->json([
                    'result' => 'error',
                    'status_code' => 400,
                    'message' => 'Invalid integration slug.'
                ]);
            }
        }

        try
        {
            if(!empty($data))
            {
                if (trim($request->integration_slug) == "sendgrid") {
                    $this->checkAPIKeyIsValid($request->api_key,'sendgrid');
                } else if (trim($request->integration_slug) == "gohighlevel") {
                    $version = (isset($request->version) && !empty($request->version)) ? $request->version : '1';
                    $tokens = (isset($data->tokens)) ? $data->tokens : '';
                    if($version == '2' && $tokens == '') {
                        return response()->json(array("result"=>'error','status_code'=>401,'message'=>'You Have Not Yet Connected To Lead Connector Version 2'),401); 
                    }
                    
                    $custom_fields_merge = isset($request->custom_fields) ? $request->custom_fields : [];
                    if (isset($request->custom_fields_b2b) && !empty($request->custom_fields_b2b)) {
                        $custom_fields_merge =  array_merge($custom_fields_merge, $request->custom_fields_b2b);
                    }
                    if (isset($request->custom_fields_advance) && !empty($request->custom_fields_advance)) {
                        $custom_fields_merge =  array_merge($custom_fields_merge, $request->custom_fields_advance);
                    }
                    $this->checkAPIKeyIsValid($request->api_key,'gohighlevel',$request->company_id,$custom_fields_merge,$version);
                } else if (trim($request->integration_slug) == "zapier") {
                    if (isset($request->send_test_zapier) && $request->send_test_zapier) {
                        foreach ($request->api_key as $value) {
                            $this->checkAPIKeyIsValid($value,'zapier');
                        }
                    }
                    if (isset($request->api_key) && !empty($request->api_key)) {
                        $result = implode('|', $request->api_key);
                        $request->merge([
                            'api_key' => $result,
                        ]);
                    }
                    if (isset($request->webhook_labels) && is_array($request->webhook_labels)) {
                        $labelsResult = implode('|', $request->webhook_labels);
                        $request->merge([
                            'webhook_labels' => $labelsResult,
                        ]);
                    }

                } else if(trim($request->integration_slug == 'mailboxpower')) {
                    $this->checkAPIKeyIsValid($request->api_key, 'mailboxpower');
                } else if (trim($request->integration_slug == 'clickfunnels')){
                    $workSpace = $this->clickfunnels_GetWorkSpaceId($request->api_key, $request->subdomain, $request->workspace_id);
                    $workspace_id = $workSpace['id'];

                    if($workSpace['result'] == 'failed'){
                        return response()->json(['result' => 'failed', 'message' => $workSpace['message']], $workSpace['status_code']);
                    }

                    $custom_fields_merge =  array_merge($request->custom_fields, $request->custom_fields_b2b);

                    $response = $this->checkApiKeyIsValidClickFunnels($request->api_key, $request->subdomain, $workspace_id, $custom_fields_merge);

                    if($response['result'] == 'failed'){
                        return response()->json(['result' => 'failed', 'message' => $response['message']], $response['status_code']);
                    }

                } else if (trim($request->integration_slug) == "agencyzoom") {
                    if (isset($request->send_test_agency_zoom) && $request->send_test_agency_zoom) {
                        foreach ($request->api_key as $value) {
                            $this->checkAPIKeyIsValid($value,'agencyzoom');
                        }
                    }
                    if (isset($request->api_key) && !empty($request->api_key)) {
                        $result = implode('|', $request->api_key);
                        $request->merge([
                            'api_key' => $result,
                        ]);
                    }
                }

                $update = IntegrationSettings::find($data->id);
                $update->api_key = $request->api_key ?? "";
                $update->enable_sendgrid = $request->enable_sendgrid;
                $update->enable_default_campaign = $request->enable_default_campaign ?? 0;
                if (trim($request->integration_slug) == 'zapier' && isset($request->webhook_labels)) {
                    $update->webhook_labels = $request->webhook_labels ?? null;
                }
                if (trim($request->integration_slug) == 'sendjim') {
                    $update->password = $request->password; // save client secret
                }

                $custom_fields = isset($request->custom_fields) ? json_encode($request->custom_fields) : null;
                $custom_fields_b2b = isset($request->custom_fields_b2b) ? json_encode($request->custom_fields_b2b) : null;
                if($custom_fields_b2b){
                    if(trim($request->integration_slug == 'clickfunnels')){
                        $globalsetting = GlobalSettings::updateOrCreate(
                            [
                                'setting_name' => 'custom_fields_b2b_clickfunnels',
                                'company_id' => $request->company_id
                            ],
                            [
                                'setting_value' => $custom_fields_b2b
                            ]
                        );
                    } else if (trim($request->integration_slug == 'gohighlevel')){
                        $globalsetting = GlobalSettings::updateOrCreate(
                            [
                                'setting_name' => 'custom_fields_b2b_gohighlevel',
                                'company_id' => $request->company_id
                            ],
                            [
                                'setting_value' => $custom_fields_b2b
                            ]
                        );
                    }
                }

                $custom_fields_advance = isset($request->custom_fields_advance) ? json_encode($request->custom_fields_advance) : null;
                if($custom_fields_advance){
                    if(trim($request->integration_slug == 'clickfunnels')){

                    } else if (trim($request->integration_slug == 'gohighlevel')){
                        $globalsetting = GlobalSettings::updateOrCreate(
                            [
                                'setting_name' => 'custom_fields_advance_gohighlevel',
                                'company_id' => $request->company_id
                            ],
                            [
                                'setting_value' => $custom_fields_advance
                            ]
                        );
                    }
                }

                if (trim($request->integration_slug == 'clickfunnels')){
                    $update->subdomain = $request->subdomain;
                    $update->workspace_id = $request->workspace_id;
                }

                if(trim($request->integration_slug == 'clickfunnels') || trim($request->integration_slug == 'gohighlevel') || trim($request->integration_slug == 'sendjim')){
                    $update->custom_fields = $custom_fields;
                }

                if (trim($request->integration_slug) == "gohighlevel"){
                    $update->version = (isset($request->version) && !empty($request->version)) ? $request->version : '1';
                }
                
                $update->save();
                return response()->json(array('result'=>'success','status_code'=>200, 'message'=>'Integration updated!'));
            }
            else
            {
                if (trim($request->integration_slug) == "sendgrid") {
                    if($request->has('enable_sendgrid')) {
                        $request->merge(['enable_sendgrid' => 1]);    
                    }
                    
                    $this->checkAPIKeyIsValid($request->api_key,'sendgrid');
                } else if (trim($request->integration_slug) == "gohighlevel") {
                    if($request->has('enable_sendgrid')) {
                        $request->merge(['enable_sendgrid' => 1]);    
                    }

                    $version = (isset($request->version) && !empty($request->version)) ? $request->version : '1';
                    if($version == '2') {
                        return response()->json(array("result"=>'error','status_code'=>401,'message'=>'You Have Not Yet Connected To LeadConnector Version 2'),401); 
                    }

                    $this->checkAPIKeyIsValid($request->api_key,'gohighlevel',$request->company_id);
                } else if (trim($request->integration_slug) == "zapier") {
                    if($request->has('enable_sendgrid')) {
                        $request->merge(['enable_sendgrid' => 1]);    
                    }
                    
                    if (isset($request->send_test_zapier) && $request->send_test_zapier) {
                        foreach ($request->api_key as $value) {
                            $this->checkAPIKeyIsValid($value,'zapier');
                        }                    }
                    if (isset($request->api_key) && !empty($request->api_key)) {
                        $result = implode('|', $request->api_key);
                        $request->merge([
                            'api_key' => $result,
                        ]);
                    }
                    if (isset($request->webhook_labels) && is_array($request->webhook_labels)) {
                        $labelsResult = implode('|', $request->webhook_labels);
                        $request->merge([
                            'webhook_labels' => $labelsResult,
                        ]);
                    }
                } else if(trim($request->integration_slug == 'mailboxpower')) {
                    if($request->has('enable_sendgrid')) {
                        $request->merge(['enable_sendgrid' => 1]);    
                    }

                    $this->checkAPIKeyIsValid($request->api_key, 'mailboxpower');
                } else if (trim($request->integration_slug == 'clickfunnels')){
                    if($request->has('enable_sendgrid')) {
                        $request->merge(['enable_sendgrid' => 1]);    
                    }
                    
                    $workSpace = $this->clickfunnels_GetWorkSpaceId($request->api_key, $request->subdomain, $request->workspace_id);
                    $workspace_id = $workSpace['id'];

                    if($workSpace['result'] == 'failed'){
                        return response()->json(['result' => 'failed', 'message' => $workSpace['message']], $workSpace['status_code']);
                    }

                    $custom_fields_merge =  array_merge($request->custom_fields, $request->custom_fields_b2b);
                    $response = $this->checkApiKeyIsValidClickFunnels($request->api_key, $request->subdomain, $workspace_id, $custom_fields_merge);

                    if($response['result'] == 'failed'){
                        return response()->json(['result' => 'failed', 'message' => $response['message']], $response['status_code']);
                    }
                } else if (trim($request->integration_slug) == "agencyzoom") {
                    if ($request->has('enable_sendgrid')) {
                        $request->merge(['enable_sendgrid' => 1]);    
                    }
                    if (isset($request->send_test_zoom_agency) && $request->send_test_zoom_agency) {
                        foreach ($request->api_key as $value) {
                            $this->checkAPIKeyIsValid($value,'agencyzoom');
                        }                    
                    }
                    if (isset($request->api_key) && !empty($request->api_key)) {
                        $result = implode('|', $request->api_key);
                        $request->merge([
                            'api_key' => $result,
                        ]);
                    }
                }

                $custom_fields = isset($request->custom_fields) ? json_encode($request->custom_fields) : null;
                $custom_fields_b2b = isset($request->custom_fields_b2b) ? json_encode($request->custom_fields_b2b) : null;
                if($custom_fields_b2b){
                    if(trim($request->integration_slug == 'clickfunnels')){
                        $globalsetting = GlobalSettings::updateOrCreate(
                            [
                                'setting_name' => 'custom_fields_b2b_clickfunnels',
                                'company_id' => $request->company_id
                            ],
                            [
                                'setting_value' => $custom_fields_b2b
                            ]
                        );
                    } else if (trim($request->integration_slug == 'gohighlevel')){

                        $globalsetting = GlobalSettings::updateOrCreate(
                            [
                                'setting_name' => 'custom_fields_b2b_gohighlevel',
                                'company_id' => $request->company_id
                            ],
                            [
                                'setting_value' => $custom_fields_b2b
                            ]
                        );
                        
                    }
                }

                $custom_fields_advance = isset($request->custom_fields_advance) ? json_encode($request->custom_fields_advance) : null;
                if($custom_fields_advance){
                    if(trim($request->integration_slug == 'clickfunnels')){

                    } else if (trim($request->integration_slug == 'gohighlevel')){
                        $globalsetting = GlobalSettings::updateOrCreate(
                            [
                                'setting_name' => 'custom_fields_advance_gohighlevel',
                                'company_id' => $request->company_id
                            ],
                            [
                                'setting_value' => $custom_fields_advance
                            ]
                        );
                    }
                }

                $save = IntegrationSettings::create([
                    'company_id' => $request->company_id,
                    'integration_slug' => $request->integration_slug,
                    'api_key' => trim($request->api_key ?? ""),
                    'webhook_labels' => (trim($request->integration_slug) == 'zapier' && isset($request->webhook_labels)) ? ($request->webhook_labels ?? null) : null,
                    'password' => (trim($request->integration_slug) == 'sendjim') ? ($request->password ?? null) : null,
                    'enable_sendgrid' => $request->enable_sendgrid,
                    'enable_default_campaign' => $request->enable_default_campaign ?? 0,
                    'subdomain' => $request->subdomain ?? '',
                    'workspace_id' => $request->workspace_id ?? '',
                    'custom_fields' => $custom_fields,
                    'version' => null,
                    'access_token' => null,
                ]);
                
                return response()->json([
                    'result'=>'success',
                    'status_code'=>200, 
                    'message'=>'Integration added!', 
                    'enable_sendgrid' => ($request->enable_sendgrid == 1)
                ]);
            }
        }
        catch (Exception $e) 
        {
            Log::info(['error' => $e->getMessage()]);
            if (str_contains($e->getMessage(), '[ZAPIER_TEST]') || str_contains($e->getMessage(), '[AGENCY_ZOOM_TEST]')) {
                return response()->json(["result" => 'error',"status_code" => 400,"message" => "webhook url is not valid"], 400);
            }

            return response()->json(array("result"=>'error','status_code'=> 400, 'message'=>trim(ucfirst($request->integration_slug == 'gohighlevel' ? 'LeadConnector' : $request->integration_slug)) . ' api key is not valid!'),400);
        }
    }

    // to get all integration settings for companies

    public function getIntegration(Request $request){
        $data=IntegrationSettings::where('company_id', $request->company_id)
                                    ->where('integration_slug', $request->integration_slug)
                                    ->select('api_key')
                                    ->get();
        if(count($data) > 0){
            return response()->json(array('result'=>'success','status_code'=>200,'data'=>$data,'message'=>'Available Integration Settings'));
        }

        return response()->json(array('result'=>'success','status_code'=>200, 'message'=>'No Integration available'));
    }

    public function getSendgridApiKey(Request $request){
        $data=IntegrationSettings::where('company_id', $request->company_id)
                                    ->where('integration_slug', $request->integration_slug)
                                    ->select('api_key')
                                    ->get();
        if(count($data) > 0){
            return response()->json(array('result'=>'success','status_code'=>200,'data'=>$data,'message'=>'Sendgrid Api Key'));
        }

        return response()->json(array('result'=>'success','status_code'=>200, 'message'=>'No Api Key enabled for sendgrid'));
    }

    /* GOHIGHLEVELV2 PROCESS */    
    // connect
    public function gohighlevelv2GenerateAuthUrl(Request $request)
    {
        // info(__FUNCTION__, ['all' => $request->all()]);

        /* CHECK COMPANY ID AGENCY OR CLIENT */
        $company_id = isset($request->company_id) ? $request->company_id : '';
        $company_root_ids = User::whereNull('company_parent')->pluck('company_id')->toArray();
        
        $user = User::where('company_id', $company_id)->where('active', 'T')->first();
        $user_type = isset($user->user_type) ? $user->user_type : '';
        // info(__FUNCTION__, ['user_type' => $user_type]);

        if($user_type == 'client') // jika client
        {
            return $this->gohighlevelv2GenerateAuthUrlClient($request);
        }
        else if(in_array($user_type, ['userdownline', 'user']) && !in_array($company_id, $company_root_ids)) // jika agency
        {
            return $this->gohighlevelv2GenerateAuthUrlAgency($request);
        }
        else 
        {
            return response()->json(['result' => 'error', 'message' => 'user type not mathing'], 400);
        }
        /* CHECK COMPANY ID AGENCY OR CLIENT */
    }
    public function gohighlevelv2GenerateAuthUrlClient(Request $request)
    {
        // info(__FUNCTION__, ['all' => $request->all()]);
        
        $user_id_login = optional(auth()->user())->id;
        $user_ip = isset($request->user_ip) ? $request->user_ip : '';
        $is_in_iframe = isset($request->is_in_iframe) ? $request->is_in_iframe : false;
        $app_url = env('APP_URL');
        $base_url = config('services.gohighlevelv2.base_url');
        $client_id = config('services.gohighlevelv2.client_id');
        $scope = config('services.gohighlevelv2.scope');

        $company_id = isset($request->company_id) ? $request->company_id : '';
        $subdomain_url = isset($request->subdomain_url) ? $request->subdomain_url : '';
        $enable_sendgrid = isset($request->enable_sendgrid) ? $request->enable_sendgrid : '';
        $custom_fields_general = isset($request->custom_fields_general) ? json_encode($request->custom_fields_general) : null;
        $custom_fields_b2b = isset($request->custom_fields_b2b) ? json_encode($request->custom_fields_b2b) : null;
        $custom_fields_advance = isset($request->custom_fields_advance) ? json_encode($request->custom_fields_advance) : null;

        // info([
        //     'app_url' => $app_url,
        //     'base_url' => $base_url,
        //     'client_id' => $client_id,
        //     'scope' => $scope
        // ]);

        /* VALIDATION */
        if(empty($app_url))
            return response()->json(['result' => 'error', 'message' => 'app url not empty'], 400);
        if(empty($base_url)) 
            return response()->json(['result' => 'error', 'message' => 'base url not empty'], 400);
        if(empty($client_id))
            return response()->json(['result' => 'error', 'message' => 'client id not empty'], 400);
        if(empty($scope))
            return response()->json(['result' => 'error', 'message' => 'scopes not empty'], 400);
        if(empty($company_id))
            return response()->json(['result' => 'error', 'message' => 'company id not empty'], 400);
        if(empty($subdomain_url))
            return response()->json(['result' => 'error', 'message' => 'subdomain url not empty'], 400);
        /* VALIDATION */
        
        /* DATA STATE */
        $state = [
            'company_id' => $company_id,
            'subdomain_url' => $subdomain_url,
            'enable_sendgrid' => $enable_sendgrid,
            'user_id_login' => $user_id_login,
            'user_ip' => $user_ip,
            'is_in_iframe' => $is_in_iframe,
        ];

        if($custom_fields_general)
            $state['custom_fields_general'] = $custom_fields_general;
        if($custom_fields_b2b)
            $state['custom_fields_b2b'] = $custom_fields_b2b;
        if($custom_fields_advance)
            $state['custom_fields_advance'] = $custom_fields_advance;
        
        // info(['state' => $state]);
        $state = base64_encode(json_encode($state));
        /* DATA STATE */

        $redirect_uri = "{$app_url}/leadconnectorv2/oauth/callback/view";
        $params = [
            'response_type' => 'code',
            'redirect_uri' => $redirect_uri,
            'client_id' => $client_id,
            'scope' => $scope,
            'state' => $state,
            'user_type' => 'client'
        ];
        $auth_url = $base_url . '/oauth/chooselocation?' . http_build_query($params);

        return response()->json(['result' => 'success', 'url' => $auth_url]);
    }
    public function gohighlevelv2GenerateAuthUrlAgency(Request $request)
    {
        // info(__FUNCTION__, ['all' => $request->all()]);

        /* VARIABLE */
        $user_ip = isset($request->user_ip) ? $request->user_ip : '';
        $is_in_iframe = isset($request->is_in_iframe) ? $request->is_in_iframe : false;
        $user_id_login = optional(auth()->user())->id;
        $app_url = env('APP_URL');
        $systemid = config('services.application.systemid');

        $company_id = isset($request->company_id) ? $request->company_id : '';
        $subdomain_url = isset($request->subdomain_url) ? $request->subdomain_url : '';
        $custom_menu_name = isset($request->custom_menu_name) ? trim($request->custom_menu_name) : '';
        $company_name = isset($request->company_name) ? trim($request->company_name) : '';
        $in_app_public = isset($request->in_app_public) ? $request->in_app_public : '';

        $credentials_ghl_emm_private = GlobalSettings::where('company_id', $systemid)->where('setting_name', 'credentials_ghl_emm_private')->first();
        if(empty($credentials_ghl_emm_private))
            return response()->json(['result' => 'error', 'message' => 'credentials ghl not found'], 404);
        
        $credentials = json_decode($credentials_ghl_emm_private->setting_value, true);
        $base_url = $credentials['base_url'] ?? '';
        $client_id = $credentials['client_id'] ?? '';
        $scope = $credentials['scope'] ?? '';
        /* VARIABLE */

        // info(__FUNCTION__, ['app_url' => $app_url,'base_url' => $base_url,'client_id' => $client_id,'scope' => $scope]);

        /* VALIDATION */
        if(empty($app_url))
            return response()->json(['result' => 'error', 'message' => 'app url not empty'], 400);
        if(empty($base_url)) 
            return response()->json(['result' => 'error', 'message' => 'base url not empty'], 400);
        if(empty($client_id))
            return response()->json(['result' => 'error', 'message' => 'client id not empty'], 400);
        if(empty($scope))
            return response()->json(['result' => 'error', 'message' => 'scopes not empty'], 400);
        if(empty($company_id))
            return response()->json(['result' => 'error', 'message' => 'company id not empty'], 400);
        if(empty($subdomain_url) && $in_app_public !== true)
            return response()->json(['result' => 'error', 'message' => 'subdomain url not empty'], 400);
        
        $ghlV2AgencyConnected = Company::join('users', 'users.company_id', '=', 'companies.id')
                                       ->where('users.company_id', $company_id)
                                       ->where('users.user_type', 'userdownline')
                                       ->where('users.active', 'T')
                                       ->where(function ($query) {
                                            $query->whereNotNull('companies.ghl_company_id')->where('companies.ghl_company_id', '<>', '')
                                                  ->whereNotNull('companies.ghl_tokens')->where('companies.ghl_tokens', '<>', '')
                                                  ->whereNotNull('companies.ghl_custom_menus')->where('companies.ghl_custom_menus', '<>', '')
                                                  ->whereNotNull('companies.ghl_credentials_id')->where('companies.ghl_credentials_id', '<>', '');
                                       })
                                       ->exists();
        if($ghlV2AgencyConnected)
            return response()->json(['result' => 'error', 'message' => 'your has ben connect to gohighlevel'], 400);
        /* VALIDATION */

        /* DATA STATE */
        $state = [
            'company_id' => $company_id,
            'subdomain_url' => $subdomain_url,
            'custom_menu_name' => $custom_menu_name,
            'user_id_login' => $user_id_login,
            'user_ip' => $user_ip,
            'in_app_public' => $in_app_public,
            'company_name' => $company_name,
            'user_type' => 'agency',
            'is_in_iframe' => $is_in_iframe,
        ];
        // info(['state' => $state]);
        $state = base64_encode(json_encode($state));
        /* DATA STATE */

        /* URL FOR REDIRECT URL */
        $redirect_uri = "{$app_url}/leadconnectorv2/oauth/callback/view";
        $params = [
            'response_type' => 'code',
            'redirect_uri' => $redirect_uri,
            'client_id' => $client_id,
            'scope' => $scope,
            'state' => $state
        ];
        $auth_url = "$base_url/oauth/chooselocation?" . http_build_query($params);
        // info(__FUNCTION__, ['auth_url' => $auth_url]);
        /* URL FOR REDIRECT URL */

        return response()->json(['result' => 'success', 'url' => $auth_url]);
    }
    // connect

    // disconnect
    public function gohighlevel2Disconnect(Request $request)
    {
        // info(__FUNCTION__, ['all' => $request->all()]);

        /* CHECK COMPANY ID AGENCY OR CLIENT */
        $company_id = isset($request->company_id) ? $request->company_id : '';
        $company_root_ids = User::whereNull('company_parent')->pluck('company_id')->toArray();
        
        $user = User::where('company_id', $company_id)->first();
        $user_type = isset($user->user_type) ? $user->user_type : '';
        // info(__FUNCTION__, ['user_type' => $user_type]);

        if($user_type == 'client') // jika client
        {
            return $this->gohighlevel2DisconnectClient($request);
        }
        else if(in_array($user_type, ['userdownline', 'user']) && !in_array($company_id, $company_root_ids)) // jika agency
        {
            return $this->gohighlevel2DisconnectAgency($request);
        }
        else 
        {
            return response()->json(['result' => 'error', 'message' => 'user type not mathing'], 400);
        }
        /* CHECK COMPANY ID AGENCY OR CLIENT */
    }
    public function gohighlevel2DisconnectClient(Request $request)
    {
        // info(__FUNCTION__, ['all' => $request->all()]);

        $company_id = (isset($request->company_id)) ? $request->company_id : '';
        $enable_sendgrid = false;

        $integration = IntegrationSettings::where('company_id', $company_id)
                                          ->where('integration_slug', 'gohighlevel')
                                          ->first();
        
        if(empty($integration))
            return response()->json(['status' => 'error', 'message' => 'Integration Lead Connector Not Found']);

        if($integration->api_key != '') // kalo masih ada v1, maka hapus token nya saja 
        {
            $integration->version = null;
            $integration->tokens = null;
            $enable_sendgrid = ($integration->enable_sendgrid == 1);
            $integration->save();
        } 
        else // kalo v1, nya tidak ada maka hapus integration setting, gohlcustomfields, global_settings
        {
            // hapus di integration settings
            $enable_sendgrid = false;
            $integration->delete();

            // hapus company setting gohlcustomfields
            CompanySetting::where('company_id','=',$company_id)
                          ->whereEncrypted('setting_name','=','gohlcustomfields')
                          ->delete();

            // hapus global settings custom_fields_advance_gohighlevel and custom_fields_b2b_gohighlevel
            GlobalSettings::where('company_id','=',$company_id)
                          ->whereIn('setting_name', ['custom_fields_advance_gohighlevel', 'custom_fields_b2b_gohighlevel'])
                          ->delete();
        }

        /* USER LOG */
        $salt = substr(hash('sha256', env('APP_KEY')), 0, 16);
        $client = Company::select('companies.*', 'users.id as client_id')
                         ->selectRaw("CONVERT(AES_DECRYPT(FROM_BASE64(users.name), ?) USING utf8mb4) as client_name", [$salt])
                         ->selectRaw("CONVERT(AES_DECRYPT(FROM_BASE64(users.email), ?) USING utf8mb4) as client_email", [$salt])
                         ->join('users', 'users.company_id', '=', 'companies.id')
                         ->where('companies.id', $company_id)
                         ->where('users.user_type', 'client')
                         ->where('users.active', 'T')
                         ->first();
        $client_id = $client->client_id ?? null;
        $client_name = $client->client_name ?? "";
        $client_email = $client->client_email ?? "";
        $company_name = $client->company_name ?? "";
        $user_id_login = optional(auth()->user())->id;
        $user_ip = isset($request->user_ip) ? $request->user_ip : '';
        $description = "The client has successfully disconnect their lead connector account. Name: $client_name | Email: $client_email | Company Name: $company_name | Company ID : $company_id";
        $this->logUserAction($user_id_login,'Client Disconnect Lead Connector',$description,$user_ip,$client_id);
        /* USER LOG */

        return response()->json(['status' => 'success', 'enable_sendgrid' => $enable_sendgrid, 'message' => 'Disconnect Lead Connector Successfully',]);
    }
    public function gohighlevel2DisconnectAgency(Request $request)
    {
        // info(__FUNCTION__);
        /* GET COMPANY AGENCY */
        $salt = substr(hash('sha256', env('APP_KEY')), 0, 16);
        $user_id_login = optional(auth()->user())->id;
        $company_id = isset($request->company_id) ? $request->company_id : '';
        $company = Company::select(
                            'companies.*',
                            'users.id as agency_id')
                          ->selectRaw("CONVERT(AES_DECRYPT(FROM_BASE64(users.name), ?) USING utf8mb4) as agency_name", [$salt])
                          ->selectRaw("CONVERT(AES_DECRYPT(FROM_BASE64(users.email), ?) USING utf8mb4) as agency_email", [$salt])
                          ->join('users', 'users.company_id', '=', 'companies.id')
                          ->where('companies.id', $company_id)
                          ->where('users.user_type', 'userdownline')
                          ->where('users.active', 'T')
                          ->first();
        if(empty($company))
            return response()->json(['result' => 'error', 'message' => 'agency not found'], 404);
        /* GET COMPANY AGENCY */

        /* GET GHL TOKENS */
        $tokens = $this->gohighlevelv2GetTokensDBAgency($company_id);
        // info(__FUNCTION__, ['tokens' => $tokens]);
        $access_token = isset($tokens['access_token']) ? $tokens['access_token'] : '';
        $refresh_token = isset($tokens['refresh_token']) ? $tokens['refresh_token'] : '';
        /* GET GHL TOKENS */

        /* REMOVE CUSTOM MENU LINK */
        $ghl_custom_menus = isset($company->ghl_custom_menus) ? json_decode($company->ghl_custom_menus, true) : [];
        $custom_menu_link_id = isset($ghl_custom_menus['id']) ? $ghl_custom_menus['id'] : '';  
        $deleteCustomMenu = $this->gohighlevelv2DeleteCustomMenuAgency($company_id, $access_token, $refresh_token, $custom_menu_link_id);
        // info(__FUNCTION__, ['deleteCustomMenu' => $deleteCustomMenu]);
        /* REMOVE CUSTOM MENU LINK */

        /* DELETE SSO ACCESS TOKEN */
        $ghl_custom_menus = !empty($company->ghl_custom_menus) ? json_decode($company->ghl_custom_menus, true) : [];
        $url_custom_menu = $ghl_custom_menus['url'] ?? '';

        $parse_url = parse_url($url_custom_menu);
        $parse_query = $parse_url['query'] ?? "";

        $query_array = [];
        parse_str($parse_query, $query_array);
        $token_sso = $query_array['token'] ?? '';

        SsoAccessToken::whereEncrypted('token', $token_sso)
                      ->where('not_remove', 'T')
                      ->delete();
        /* DELETE SSO ACCESS TOKEN */

        /* REMOVE GHL COMPANY ID AND TOKENS */
        $company->ghl_company_id = null;
        $company->ghl_tokens = null;
        $company->ghl_custom_menus = null;
        $company->ghl_credentials_id = null;
        $company->save();
        /* REMOVE GHL COMPANY ID AND TOKENS */

        /* USER LOG */
        $agency_name = $company->agency_name ?? "";
        $agency_email = $company->agency_email ?? "";
        $company_name = $company->company_name ?? "";
        $agency_id = $company->agency_id ?? "";
        $user_ip = isset($request->user_ip) ? $request->user_ip : '';
        $description = "The agency has successfully disconnect their GoHighLevel account. Name: $agency_name | Email: $agency_email | Company Name: $company_name | Company ID : $company_id";
        $this->logUserAction($user_id_login,'Agency Disconnect Gohighlevel',$description,$user_ip,$agency_id);
        /* USER LOG */

        return response()->json(['result' => 'success', 'message' => 'disconnect your account gohighlevel successfully']);
    }
    // disconnect

    // subaccocunts
    public function gohighlevelv2ListSubAccountsAll(Request $request)
    {
        // info(__FUNCTION__, ['company_id' => $request->company_id]);
        /* CHECK IS AGENCY */
        $systemid = config('services.application.systemid');
        $company_id = $request->company_id ?? ''; // company_id agency
        $agency = Company::select('companies.*','users.company_root_id')
                         ->join('users', 'users.company_id', '=', 'companies.id')
                         ->where('companies.id', $company_id)
                         ->where('users.company_parent', $systemid)
                         ->where('users.user_type', 'userdownline')
                         ->where('users.active', 'T')
                         ->first();
        if(empty($agency))
            return response()->json(['result' => 'failed', 'message' => 'agency not found'], 404);
        $company_root_id = $agency->company_root_id ?? null;
        $ghlV2AgencyConnected = ((!empty($agency->ghl_company_id) && trim($agency->ghl_company_id) != '')) &&
                                ((!empty($agency->ghl_tokens) && trim($agency->ghl_tokens) != '')) &&
                                ((!empty($agency->ghl_custom_menus) && trim($agency->ghl_custom_menus) != '')) &&
                                ((!empty($agency->ghl_credentials_id) && trim($agency->ghl_credentials_id) != ''));
        if(!$ghlV2AgencyConnected)
            return response()->json(['result' => 'error', 'message' => 'your has not yet connect to agency lead connector'], 400);
        $ghl_company_id = $agency->ghl_company_id ?? "";
        /* CHECK IS AGENCY */

        /* GET TOKEN GHL AGENCY */
        $agency_ghl_token = $this->gohighlevelv2GetTokensDBAgency($company_id);
        $access_token = isset($agency_ghl_token['access_token']) ? $agency_ghl_token['access_token'] : '';
        $refresh_token = isset($agency_ghl_token['refresh_token']) ? $agency_ghl_token['refresh_token'] : '';
        // info(__FUNCTION__, ['access_token' => $access_token, 'refresh_token' => $refresh_token]);
        /* GET TOKEN GHL AGENCY */

        /* PROCESS GET LISTS SUB ACCOUNT */
        $salt = substr(hash('sha256', env('APP_KEY')), 0, 16);
        $http = new \GuzzleHttp\Client;
        $listSubAccounts = [];
        $listSubAccountsFormat = [];
        $retry = false;
        $limit = 100;
        $skip = 0;

        do
        {
            try
            {
                // get all list sub account
                for($i = 0; $i < 20; $i++)
                {
                    $url = "https://services.leadconnectorhq.com/locations/search";
                    $headers = [
                        'Accept' => 'application/json',
                        'Authorization' => "Bearer {$access_token}",
                        'Version' => '2021-07-28',
                    ];
                    $query = [
                        'companyId' => $ghl_company_id,
                        'limit' => $limit,
                        'skip' => $skip
                    ];
                    $parameter = [
                        'headers' => $headers,
                        'query' => $query
                    ];

                    $response = $http->get($url, $parameter);
                    $response = json_decode($response->getBody(), true);
                    $locations = $response['locations'] ?? [];    
                    $listSubAccounts = array_merge($listSubAccounts, $locations);
                    $countLocations = is_array($locations) ? count($locations) : 0;
                    // info("SKIP BEFORE = $skip");
                    $skip += $limit;
                    // info("SKIP AFTER = $skip");
                    // info(['countLocations' => $countLocations, 'limit' => $limit]);
                    if($countLocations <= 0 || $countLocations < $limit) // jika tidak ada data atau data yang di dapat dibawah limit
                        break;
                }
                // get all list sub account

                // filter whether the sub account has been used on another platform
                $listEmails = array_filter(array_column($listSubAccounts, 'email'));
                $emailPlaceholders = implode(',', array_fill(0, count($listEmails), '?'));
                $existingEmailAnotherPlatform = User::selectRaw("CONVERT(AES_DECRYPT(FROM_BASE64(email), ?) USING utf8mb4) as email", [$salt])
                                                    ->whereRaw("CONVERT(AES_DECRYPT(FROM_BASE64(email), ?) USING utf8mb4) IN ({$emailPlaceholders})", array_merge([$salt], $listEmails))
                                                    ->where('active','T')
                                                    ->where(function ($query) use ($company_id, $company_root_id) {
                                                        $query->where(function ($query) use ($company_id) { // check email di platform/domain itu sendiri, sudah dipakai oleh agency, admin agency belum
                                                            $query->whereIn('user_type',['userdownline','user'])
                                                                  ->where('company_id',$company_id);
                                                        })->orWhere(function ($query) use ($company_root_id) { // check email di root, admin root, sales sudah dipakai atau belum
                                                            $query->whereIn('user_type',['userdownline','user','sales'])
                                                                  ->where('company_id',$company_root_id);
                                                        });
                                                    })
                                                    ->pluck('email')
                                                    ->toArray();
                $existingEmailClient = User::selectRaw("CONVERT(AES_DECRYPT(FROM_BASE64(email), ?) USING utf8mb4) as email", [$salt])
                                           ->whereRaw("CONVERT(AES_DECRYPT(FROM_BASE64(email), ?) USING utf8mb4) IN ({$emailPlaceholders})", array_merge([$salt], $listEmails))
                                           ->where('active', 'T')
                                           ->where('company_parent', $company_id)
                                           ->where('user_type', 'client')
                                           ->pluck('email')
                                           ->toArray();
                foreach($listSubAccounts as $index => $item)
                {
                    $email = $item['email'] ?? "";
                    $isExistsAnotherPlatform = !empty($email) && in_array($email, $existingEmailAnotherPlatform);
                    $isExistsClient = !empty($email) && in_array($email, $existingEmailClient);
                    $listSubAccountsFormat[] = [
                        'id' => $index + 1,
                        'email' => $email,
                        'company' => $item['name'] ?? "",
                        'phone' => $item['phone'] ?? "",
                        'country' => $item['country'] ?? "",
                        'location_id' => $item['id'] ?? "",
                        'company_id' => $item['companyId'] ?? "",
                        'isExistsAnotherPlatform' => $isExistsAnotherPlatform,
                        'isExistsClient' => $isExistsClient,
                    ];
                }
                // filter whether the sub account has been used on another platform

                return response()->json(['result' => 'success','listSubAccounts' => $listSubAccountsFormat]);
            }
            catch (\GuzzleHttp\Exception\BadResponseException $e)
            {
                /* REFRESH TOKEN */
                $code = $e->getResponse()->getStatusCode();
                // info(__FUNCTION__ . ' catch 1', ['code' => $code, 'retry' => $retry, 'error' => $e->getMessage()]);
                if($code == 401 && !$retry) 
                {
                    $retry = true;
                    // info(__FUNCTION__  . ' catch 2', ['company_id' => $company_id,'refresh_token' => $refresh_token,]);
                    $refresh_result = $this->gohighlevelv2RefreshTokenAgency($company_id, $refresh_token);
                    // info(__FUNCTION__ .' catch 3', ['refresh_result' => $refresh_result]);
                    if (isset($refresh_result['status']) && $refresh_result['status'] === 'success')
                    {
                        // info(__FUNCTION__ . 'catch 4');
                        $access_token = $refresh_result['access_token'];
                        $refresh_token = $refresh_result['refresh_token'];
                        continue; // ulangi process
                    }
                    else
                    {
                        // info(__FUNCTION__ . 'catch 5');
                        return response()->json(['result' => 'failed', 'message' => $e->getMessage()], 400);
                    }
                }
                /* REFRESH TOKEN */

                // info(__FUNCTION__ . 'catch 6');
                return response()->json(['result' => 'failed', 'message' => $e->getMessage()], 400);
            }
            catch (\Exception $e)
            {
                // info(__FUNCTION__ . 'catch 7');
                return response()->json(['result' => 'failed', 'message' => $e->getMessage()], 500);
            }
        } while ($retry);
        /* PROCESS GET LISTS SUB ACCOUNT */
    }

    public function gohighlevelv2ListSubAccounts(Request $request)
    {
        // info(__FUNCTION__, ['all' => $request->all()]);
        /* CHECK IS AGENCY */
        $systemid = config('services.application.systemid');
        $company_id = $request->company_id ?? ''; // company_id agency
        $agency = Company::select('companies.*')
                         ->join('users', 'users.company_id', '=', 'companies.id')
                         ->where('companies.id', $company_id)
                         ->where('users.company_parent', $systemid)
                         ->where('users.user_type', 'userdownline')
                         ->where('users.active', 'T')
                         ->first();
        if(empty($agency))
            return response()->json(['result' => 'failed', 'message' => 'agency not found'], 404);

        $ghlV2AgencyConnected = ((!empty($agency->ghl_company_id) && trim($agency->ghl_company_id) != '')) &&
                                ((!empty($agency->ghl_tokens) && trim($agency->ghl_tokens) != '')) &&
                                ((!empty($agency->ghl_custom_menus) && trim($agency->ghl_custom_menus) != '')) &&
                                ((!empty($agency->ghl_credentials_id) && trim($agency->ghl_credentials_id) != ''));
        if(!$ghlV2AgencyConnected)
            return response()->json(['result' => 'success', 'ghlV2AgencyConnected' => $ghlV2AgencyConnected, 'listSubAccounts' => []]);
        $ghl_company_id = $agency->ghl_company_id ?? "";
        /* CHECK IS AGENCY */

        /* GET LOCATION ID LIST IN CLIENT HAS CREATED IFRAME */
        $listExcludeLocationIds = [];
        $clients = User::select('users.*', 'companies.ghl_custom_menus')
                       ->join('companies', 'companies.id', '=', 'users.company_id')
                       ->where('users.company_parent', $company_id)
                       ->where('users.user_type', 'client')
                       ->where('users.active', 'T')
                       ->where('companies.ghl_custom_menus', '<>', '')
                       ->whereNotNull('companies.ghl_custom_menus')
                       ->get();
        // info(__FUNCTION__, ['clients' => $clients]);
        foreach($clients as $item)
        {
            $ghl_custom_menus = !empty($item->ghl_custom_menus) ? json_decode($item->ghl_custom_menus, true) : [];
            $location_id = $ghl_custom_menus['locations'][0] ?? '';
            
            if(!empty($location_id) && trim($location_id) != '')
            {
                $listExcludeLocationIds[] = $location_id; 
            }
        }
        // info(__FUNCTION__, ['listExcludeLocationIds' => $listExcludeLocationIds]);
        /* GET LOCATION ID LIST IN CLIENT HAS CREATED IFRAME */

        /* GET TOKEN GHL AGENCY */
        $agency_ghl_token = $this->gohighlevelv2GetTokensDBAgency($company_id);
        $access_token = isset($agency_ghl_token['access_token']) ? $agency_ghl_token['access_token'] : '';
        $refresh_token = isset($agency_ghl_token['refresh_token']) ? $agency_ghl_token['refresh_token'] : '';
        // info(__FUNCTION__, ['access_token' => $access_token, 'refresh_token' => $refresh_token]);
        /* GET TOKEN GHL AGENCY */

        /* PROCESS GET LISTS SUB ACCOUNT */
        $http = new \GuzzleHttp\Client;
        $listSubAccounts = [];
        $retry = false;
        $limit = 20;
        $page = $request->page ?? 1;
        $skip = ($page - 1) * $limit;
        $countLocations = 0;
        // info(__FUNCTION__, ['limit' => $limit,'page' => $page,'skip' => $skip,]);

        do
        {
            try
            {
                for($i = 0; $i < 20; $i++)
                {
                    $url = "https://services.leadconnectorhq.com/locations/search";
                    $headers = [
                        'Accept' => 'application/json',
                        'Authorization' => "Bearer {$access_token}",
                        'Version' => '2021-07-28',
                    ];
                    $query = [
                        'companyId' => $ghl_company_id,
                        'limit' => $limit,
                        'skip' => $skip
                    ];
                    $parameter = [
                        'headers' => $headers,
                        'query' => $query
                    ];
    
                    $response = $http->get($url, $parameter);
                    $response = json_decode($response->getBody(), true);
                    $locations = $response['locations'] ?? [];
                    $countLocations = is_array($locations) ? count($locations) : 0;
                    // info(__FUNCTION__, ['count_locations' => count($locations), 'locations' => $locations]);
    
                    foreach($locations as $item)
                    {
                        $location_id = $item['id'] ?? ''; 
                        $location_name = $item['name'] ?? ''; 
                        if(!empty($location_id) && !empty($location_name) && !in_array($location_id, $listExcludeLocationIds))
                        {
                            $listSubAccounts[] = [
                                'id' => $location_id,
                                'name' => $location_name,
                            ];
                        }
                    }
                    // info('', ['count_listSubAccounts' => count($listSubAccounts)]);

                    if($countLocations == 0)
                    {
                        // info('masuk break');
                        break;
                    }
                    else 
                    {
                        if(count($listSubAccounts) == 0)
                        {
                            $page += 1;
                            $skip = ($page - 1) * $limit;
                            $countLocations = 0;
                        }
                        else 
                        {
                            // info('masuk break');
                            break;
                        }
                    }
                }

                return response()->json(['result' => 'success', 'ghlV2AgencyConnected' => $ghlV2AgencyConnected, 'listSubAccounts' => $listSubAccounts, 'page' => $page]);
            }   
            catch (\GuzzleHttp\Exception\BadResponseException $e)
            {
                /* REFRESH TOKEN */
                $code = $e->getResponse()->getStatusCode();
                // info(__FUNCTION__ . ' catch 1', ['code' => $code, 'retry' => $retry, 'error' => $e->getMessage()]);
                if($code == 401 && !$retry) 
                {
                    $retry = true;
                    // info(__FUNCTION__  . ' catch 2', ['company_id' => $company_id,'refresh_token' => $refresh_token,]);
                    $refresh_result = $this->gohighlevelv2RefreshTokenAgency($company_id, $refresh_token);
                    // info(__FUNCTION__ .' catch 3', ['refresh_result' => $refresh_result]);
                    if (isset($refresh_result['status']) && $refresh_result['status'] === 'success')
                    {
                        // info(__FUNCTION__ . 'catch 4');
                        $access_token = $refresh_result['access_token'];
                        $refresh_token = $refresh_result['refresh_token'];
                        continue; // ulangi process
                    }
                    else
                    {
                        // info(__FUNCTION__ . 'catch 5');
                        return response()->json(['result' => 'failed', 'message' => $e->getMessage()], 400);
                    }
                }
                /* REFRESH TOKEN */

                // info(__FUNCTION__ . 'catch 6');
                return response()->json(['result' => 'failed', 'message' => $e->getMessage()], 400);
            }
            catch (\Exception $e)
            {
                // info(__FUNCTION__ . 'catch 7');
                return response()->json(['result' => 'failed', 'message' => $e->getMessage()], 500);
            }
        } while ($retry);
        /* PROCESS GET LISTS SUB ACCOUNT */
    }
    // subaccocunts

    // create client from sub accounts lead connector
    public function gohighlevelv2CreateClientFromSubAccount(Request $request)
    {
        date_default_timezone_set('America/Chicago');

        /* VARIABLE */
        $salt = substr(hash('sha256', env('APP_KEY')), 0, 16);
        $max_sub_accounts = 5;
        $company_id = $request->company_id ?? null; // company_id agency
        $sub_accounts = $request->sub_accounts ?? [];
        $user_ip = $request->ip ?? null;
        /* VARIABLE */

        /* VALIDATION */
        if(empty($company_id))
            return response()->json(['result' => 'failed', 'message' => 'company id cannot be empty'], 400);
        if(!is_array($sub_accounts) || count($sub_accounts) == 0)
            return response()->json(['result' => 'failed', 'message' => 'please select at least 1 sub account.'], 400);
        if(count($sub_accounts) > $max_sub_accounts)
            return response()->json(['result' => 'failed', 'message' => 'you can select up to 5 sub accounts only'], 400);
        /* VALIDATION */

        /* GET AGENCY */
        $agency = User::where('company_id', $company_id)
                      ->where('user_type', 'userdownline')
                      ->first();
        if(empty($agency))
            return response()->json(['result' => 'failed', 'message' => 'this agency not found'], 404);
        $company_root_id = $agency->company_root_id ?? null;
        /* GET AGENCY */

        /* FILTER EMAIL */
        // filter email yang valid dan isExistAnotherPlatform false
        $sub_accounts = array_values(array_filter($sub_accounts, function($item) {
            $email = $item['email'] ?? '';
            $isExistAnotherPlatform = $item['isExistAnotherPlatform'] ?? false;
            return (!empty($email) && filter_var($email, FILTER_VALIDATE_EMAIL) && !$isExistAnotherPlatform);
        }));

        // filter email yang sudah ada di platform lain
        $listEmails = array_filter(array_column($sub_accounts, 'email'));
        $emailPlaceholders = implode(',', array_fill(0, count($listEmails), '?'));
        $existingEmailAnotherPlatform = User::selectRaw("CONVERT(AES_DECRYPT(FROM_BASE64(email), ?) USING utf8mb4) as email", [$salt])
                                            ->whereRaw("CONVERT(AES_DECRYPT(FROM_BASE64(email), ?) USING utf8mb4) IN ({$emailPlaceholders})", array_merge([$salt], $listEmails))
                                            ->where('active','T')
                                            ->where(function ($query) use ($company_id, $company_root_id) {
                                                $query->where(function ($query) use ($company_id) { // check email di platform/domain itu sendiri, sudah dipakai oleh agency, admin agency belum
                                                    $query->whereIn('user_type',['userdownline','user'])
                                                          ->where('company_id',$company_id);
                                                })->orWhere(function ($query) use ($company_root_id) { // check email di root, admin root, sales sudah dipakai atau belum
                                                    $query->whereIn('user_type',['userdownline','user','sales'])
                                                          ->where('company_id',$company_root_id);
                                                });
                                            })
                                            ->pluck('email')
                                            ->toArray();
        // info('', ['listEmails' => $listEmails, 'existingEmailAnotherPlatform' => $existingEmailAnotherPlatform]);
        $sub_accounts = array_values(array_filter($sub_accounts, function ($item) use ($existingEmailAnotherPlatform) {
            return !in_array($item['email'] ?? '', $existingEmailAnotherPlatform);
        }));
        /* FILTER EMAIL */
        // info('', ['sub_accounts' => $sub_accounts]);

        // $startTime = microtime(true);
        foreach($sub_accounts as $item)
        {
            // $startTimeLoop = microtime(true);

            $email = $item['email'] ?? null;
            $company = $item['company'] ?? null;
            $phone = $item['phone'] ?? null;
            $country = $item['country'] ?? null;
            $location_id = $item['location_id'] ?? null;
            // info(['email' => $email,'company' => $company,'phone' => $phone,'country' => $country,'location_id' => $location_id,]);
            if(empty($email) || empty($location_id)) // jika kosong maka lanjutkan yang lain
                continue;
            
            $clientExists = User::select('users.*', 'companies.ghl_custom_menus', 'companies.company_name')
                                ->leftJoin('companies', 'companies.id', '=', 'users.company_id')
                                ->whereRaw("CONVERT(AES_DECRYPT(FROM_BASE64(users.email), ?) USING utf8mb4) = ?", [$salt, $email])
                                ->where('users.active', 'T')
                                ->where('users.company_parent', $company_id)
                                ->where('users.user_type', 'client')
                                ->first();
            if($clientExists) // jika client sudah dibuat
            {
                // info('block 1.1');
                // get location id exists
                $client_company_id = $clientExists->company_id ?? "";
                $client_location_id = "";
                $client_custom_menus = json_decode(($clientExists->ghl_custom_menus ?? '[]'), true);
                if(is_array($client_custom_menus) && !empty($client_custom_menus) && count($client_custom_menus) > 0)
                {
                    $client_location_id = isset($client_custom_menus['locations'][0]) ? $client_custom_menus['locations'][0] : "";
                }
                // get location id exists

                // jika location id milik client belom ada atau jika sudah ada namun mliki client yang client. maka buat custom menu baru
                if(empty($client_location_id) || $client_location_id != $location_id)
                {
                    // info('block 1.2', ['client_location_id' => $client_location_id, 'location_id' => $location_id]);
                    // jika client location id sudah ada namun beda sama yang sekarang maka hapus custom menu nya 
                    if(!empty($client_location_id) && $client_location_id != $location_id) 
                    {
                        try 
                        {
                            $dataRequest = new Request([
                                'company_id' => $client_company_id, // company_id client
                                'company_parent' => $company_id, // company_id agency
                                'user_ip' => $user_ip,
                            ]);
                            $response = $this->gohighlevelv2DeleteIframeClient($dataRequest)->getData();
                            // info('block 1.3', ['dataRequest' => $dataRequest->toArray(), 'response' => $response]);
                        }
                        catch(\Exception $e)
                        {
                            // info('block 1.4', ['error' => $e->getMessage()]);
                            Log::error("Error In Function " . __FUNCTION__ . " #1, Message : {$e->getMessage()}");
                        }
                    }
                    // jika client location id sudah ada namun beda sama yang sekarang maka hapus custom menu nya 

                    // buat custom menu
                    try
                    {
                        $custom_menu_name = 'client-' . Carbon::now()->format('Y-m-d-H-i');
                        if(!empty($clientExists->company_name)) 
                            $custom_menu_name = $clientExists->company_name ?? "";
                        elseif(!empty($clientExists->name)) 
                            $custom_menu_name = $clientExists->name ?? "";

                        $dataRequest = new Request([
                            'company_id' => $client_company_id, // company_id client
                            'company_parent' => $company_id, // company_id agency
                            'custom_menu_name' => $custom_menu_name,
                            'user_ip' => $user_ip,
                            'location_id' => $location_id,
                        ]);
                        $response = $this->gohighlevelv2CreateIframeClient($dataRequest)->getData();
                        // info('block 1.5', ['dataRequest' => $dataRequest->toArray(), 'response' => $response]);
                    }
                    catch(\Exception $e)
                    {
                        // info('block 1.6', ['error' => $e->getMessage()]);
                        Log::error("Error In Function " . __FUNCTION__ . " #2, Message : {$e->getMessage()}");
                    }
                    // buat custom menu
                }
                // jika location id milik client belom ada atau jika sudah ada namun mliki client yang client. maka buat custom menu baru
            }
            else // jika client sudah dibuat
            {
                // info('block 2.1');
                if(empty($company)) 
                {
                    // info('block 2.2');
                    $emailExplode = explode('@', $email);
                    $company = $emailExplode[0] ?? 'client-' . Carbon::now()->format('Y-m-d-H-i');
                }

                // agency default modules
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
                $agencyDefaultModules_setting = $this->getcompanysetting($company_id,'agencydefaultmodules');
                if(!empty($agencyDefaultModules_setting) && isset($agencyDefaultModules_setting->DefaultModules)) 
                {
                    // info('block 2.3');
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
                    // info('block 2.4');
                    $rootsidebar = $this->getcompanysetting($company_root_id,'rootcustomsidebarleadmenu');
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
                // agency default modules

                // create client include create custom menu
                try
                {
                    $dataRequest = new Request([
                        'companyID' => $company_id,
                        'userType' => 'client',
                        'ClientCompanyName' => $company,
                        'ClientFullName' => $company,
                        'ClientEmail' => $email,
                        'ClientPhone' => $phone,
                        'ClientPhoneCountryCode' => $country,
                        'ClientPhoneCountryCallingCode' => '1',
                        'ClientDomain' => null,
                        'disabledreceivedemail' => 'F',
                        'enabledDeletedAccountClient' => 'F',
                        'disabledaddcampaign' => 'F',
                        'idsys' => $company_root_id,
                        'selectedmodules' => $selectedmodules,
                        'TokenId' => null,
                        'Amount' => null,
                        'enablephonenumber' => 'F',
                        'editorspreadsheet' => 'F',
                        'location_id' => $location_id,
                        'custom_menu_name' => $company,
                        'ip_login' => $user_ip
                    ]);
                    $configurationController = app(ConfigurationController::class);
                    $response = $configurationController->create($dataRequest)->getData();
                    // info('block 2.5', ['dataRequest' => $dataRequest->toArray(), 'response' => $response]);
                }
                catch(\Exception $e)
                {
                    // info('block 2.5', ['error' => $e->getMessage()]);
                    Log::error("Error In Function " . __FUNCTION__ . " #3, Message : {$e->getMessage()}");
                }
                // create client include create custom menu
            }

            // $endTimeLoop = microtime(true);
            // $diffTime_loop = $endTimeLoop - $startTimeLoop;
            // info(['diffTime_loop' => $diffTime_loop]);
        }
        // $endTime = microtime(true);
        // $diffTime = $endTime - $startTime;
        // info(['diffTime_total' => $diffTime]);

        // info(__FUNCTION__, ['company_id' => $company_id, 'sub_accounts' => $sub_accounts, '0' => $sub_accounts[0]['email'] ?? '']);
        return response()->json(['result' => 'success']);
    }
    // create client from sub accounts lead connector

    // custom menu
    public function gohighlevelv2DeleteCustomMenuAgency($company_id = "", &$access_token = "", &$refresh_token = "", $custom_menu_id = "", $action_type = "")
    {
        // info(__FUNCTION__, ['company_id' => $company_id, 'acces_token' => $access_token, 'refresh_token' => $refresh_token, 'custom_menu_id' => $custom_menu_id]);
        $http = new \GuzzleHttp\Client;
        $retry = false;

        do 
        {
            try
            {
                $url = "https://services.leadconnectorhq.com/custom-menus/{$custom_menu_id}";
                $headers = [
                    'Accept' => 'application/json',
                    'Authorization' => "Bearer {$access_token}",
                    'Version' => '2021-07-28',
                ];
                $parameter = [
                    'headers' => $headers
                ];

                $response = $http->delete($url, $parameter);
                $response = json_decode($response->getBody(), true);
                // info(__FUNCTION__, ['response' => $response]);

                return [
                    'status' => 'success',
                    'data' => $response
                ];
            }   
            catch (\GuzzleHttp\Exception\BadResponseException $e)
            {
                /* REFRESH TOKEN */
                $code = $e->getResponse()->getStatusCode();
                // info(__FUNCTION__ . ' catch 1', ['code' => $code, 'retry' => $retry, 'error' => $e->getMessage()]);
                if($code == 401 && !$retry) 
                {
                    $retry = true;
                    // info(__FUNCTION__  . ' catch 2', ['company_id' => $company_id,'refresh_token' => $refresh_token,]);
                    $refresh_result = $this->gohighlevelv2RefreshTokenAgency($company_id, $refresh_token, $action_type);
                    // info(__FUNCTION__ .' catch 3', ['refresh_result' => $refresh_result]);
                    if (isset($refresh_result['status']) && $refresh_result['status'] === 'success')
                    {
                        // info(__FUNCTION__ . 'catch 4');
                        $access_token = $refresh_result['access_token'];
                        $refresh_token = $refresh_result['refresh_token'];
                        continue; // ulangi process
                    }
                    else
                    {
                        // info(__FUNCTION__ . 'catch 5');
                        return [
                            'status' => 'error',
                            'message' => $e->getMessage(),
                        ];
                    }
                }
                /* REFRESH TOKEN */

                // info(__FUNCTION__ . 'catch 6');
                return [
                    'status' => 'error',
                    'message' => $e->getMessage(),
                ];
            }
            catch (\Exception $e)
            {
                // info(__FUNCTION__ . 'catch 7');
                return [
                    'status' => 'error',
                    'message' => $e->getMessage(),
                ];
            }
        } while ($retry);
    }
    // custom menu

    // iframe client
    public function gohighlevelv2CreateIframeClient(Request $request)
    {
        // info(__FUNCTION__, ['all' => $request->all()]);
        /* VARIABLE */
        $user_ip = isset($request->user_ip) ? $request->user_ip : '';
        $user_id_login = optional(auth()->user())->id;
        $company_id = isset($request->company_id) ? $request->company_id : ''; // company_id client
        $company_parent = isset($request->company_parent) ? $request->company_parent : ''; // company_id client
        $custom_menu_name = (isset($request->custom_menu_name) && !empty($request->custom_menu_name) && trim($request->custom_menu_name) != '') ? $request->custom_menu_name : '';
        $location_id = isset($request->location_id) ? $request->location_id : ''; 
        // info(__FUNCTION__, ['company_id' => $company_id, 'company_parent' => $company_parent]);
        if(empty($company_id))
            return response()->json(['result' => 'failed', 'mesage' => 'company id cannot be empty']);
        if(empty($company_parent))
            return response()->json(['result' => 'failed', 'mesage' => 'company parent cannot be empty']);
        if(empty($location_id)) 
            return response()->json(['result' => 'failed', 'mesage' => 'location id cannot be empty']);
        /* VARIABLE */

        /* IS CLIENT UNDER AGENCY */
        $salt = substr(hash('sha256', env('APP_KEY')), 0, 16);
        $systemid = config('services.application.systemid');
        $client = User::select(
                        'users.*', 
                        'companies.company_name',
                      )
                      ->join('companies', 'companies.id', '=', 'users.company_id')
                      ->where('company_id', $company_id)
                      ->where('company_parent', $company_parent)
                      ->where('company_root_id', $systemid)
                      ->where('user_type', 'client')
                      ->where('active', 'T')
                      ->first();
        if(empty($client))
            return response()->json(['result' => 'failed', 'message' => 'client not found'], 404);
        $client_id = $client->id ?? null;
        $client_name = $client->name ?? "";
        $client_email = $client->email ?? "";
        $company_name = $client->company_name ?? "";
        if(empty($user_id_login))
            $user_id_login = $client_id;
        /* IS CLIENT UNDER AGENCY */

        /* OVERRIDE CUSTOM MENU LINK IF EMPTY */
        if(empty($custom_menu_name) || trim($custom_menu_name) == '')
        {
            if(!empty($company_name) && trim($company_name) != '') 
                $custom_menu_name = "$company_name";
            else 
                $custom_menu_name = "$client_email";
        }
        /* OVERRIDE CUSTOM MENU LINK IF EMPTY */

        /* VALIDATION AGENCY HAS BEN CONNECT GOHIGHLEVEL */
        $companyAgency = Company::select('companies.*')
                                ->join('users', 'users.company_id', '=', 'companies.id')
                                ->where('companies.id', $company_parent)
                                ->where('users.user_type', 'userdownline')
                                ->first();
        if(empty($companyAgency))
            return response()->json(['result' => 'failed', 'message' => 'agency not found']);
        if(empty($companyAgency->ghl_company_id) || empty($companyAgency->ghl_tokens) || empty($companyAgency->ghl_custom_menus) || empty($companyAgency->ghl_credentials_id))
            return response()->json(['result' => 'failed', 'message' => 'your agency not connect account gohighlevel. please contact your agency for connect account gohighlevel before create iframe in client'], 400);
        
        $agency_ghl_token = $this->gohighlevelv2GetTokensDBAgency($company_parent);
        $access_token = isset($agency_ghl_token['access_token']) ? $agency_ghl_token['access_token'] : '';
        $refresh_token = isset($agency_ghl_token['refresh_token']) ? $agency_ghl_token['refresh_token'] : '';
        /* VALIDATION AGENCY HAS BEN CONNECT GOHIGHLEVEL */

        /* LOGIN SSO CLIENT */
        $openApiToken = (object) ['company_id' => $company_parent]; // company id agency
        $dataRequest = new Request(['user_id' => $client_id]);
        $dataRequest->attributes->set('openApiToken', $openApiToken);
        $dataRequest->attributes->set('inGoHighLevel', true);
        
        $openApiController = app(OpenApiController::class);
        $response2 = $openApiController->ssoLogin('client', $dataRequest)->getData();
        $urlCustomMenuLink = isset($response2->url) ? $response2->url : '';
        // info(__FUNCTION__, ['urlCustomMenuLink' => $urlCustomMenuLink]);
        // info(__FUNCTION__, ['response2' => $response2]);
        /* LOGIN SSO CLIENT */

        /* PROCSS CREATE IFRAME CLIENT */
        $http = new \GuzzleHttp\Client;
        $retry = false;

        do 
        {
            try
            {
                $url = 'https://services.leadconnectorhq.com/custom-menus/';
                $headers = [
                    'Accept' => 'application/json',
                    'Authorization' => "Bearer $access_token",
                    'Content-Type' => 'application/json',
                    'Version' => '2021-07-28'
                ];
                $json = [
                    'title' => $custom_menu_name,
                    'url' => $urlCustomMenuLink,
                    'icon' => [
                        'name' => 'envelope-open-text',
                        'fontFamily' => 'fas'
                    ],
                    'showOnCompany' => false,
                    'showOnLocation' => true,
                    'showToAllLocations' => false,
                    'openMode' => 'iframe',
                    'locations' => [$location_id],
                    'userRole' => 'all',
                    'allowCamera' => false,
                    'allowMicrophone' => false
                ];
                $parameter = [
                    'headers' => $headers,
                    'json' => $json,
                ];

                // info(__FUNCTION__, ['paremeter' => $parameter, 'url' => $url]);
                $response = $http->post($url, $parameter);
                $response = json_decode($response->getBody(), true);
                // info(__FUNCTION__, ['response' => $response]);

                Company::where('id', $company_id)->update(['ghl_custom_menus' => json_encode($response, JSON_UNESCAPED_SLASHES)]);

                /* USER LOGS */
                $description = "The client has successfully connected iframe their GoHighLevel account. Name: $client_name | Email: $client_email | Company Name: $company_name | Company ID : $company_id | Menu Name : $custom_menu_name";
                $this->logUserAction($user_id_login,'Client Connect Iframe Lead Connector',$description,$user_ip,$client_id);
                /* USER LOGS */

                return response()->json(['result' => 'success']);
            }   
            catch (\GuzzleHttp\Exception\BadResponseException $e)
            {
                /* REFRESH TOKEN */
                $code = $e->getResponse()->getStatusCode();
                // info(__FUNCTION__ . ' catch 1', ['code' => $code, 'retry' => $retry, 'error' => $e->getMessage()]);
                if($code == 401 && !$retry) 
                {
                    $retry = true;
                    // info(__FUNCTION__  . ' catch 2', ['company_id' => $company_id,'refresh_token' => $refresh_token,]);
                    $refresh_result = $this->gohighlevelv2RefreshTokenAgency($company_id, $refresh_token);
                    // info(__FUNCTION__ .' catch 3', ['refresh_result' => $refresh_result]);
                    if (isset($refresh_result['status']) && $refresh_result['status'] === 'success')
                    {
                        // info(__FUNCTION__ . 'catch 4');
                        $access_token = $refresh_result['access_token'];
                        $refresh_token = $refresh_result['refresh_token'];
                        continue; // ulangi process
                    }
                    else
                    {
                        // info(__FUNCTION__ . 'catch 5');
                        return response()->json(['result' => 'failed', 'message' => $e->getMessage()], 400);
                    }
                }
                /* REFRESH TOKEN */

                return response()->json(['result' => 'failed', 'message' => $e->getMessage()], 400);
            }
            catch (\Exception $e)
            {
                return response()->json(['result' => 'failed', 'message' => $e->getMessage()], 500);
            }
        } while ($retry);
        /* PROCSS CREATE IFRAME CLIENT */
    }
    public function gohighlevelv2DeleteIframeClient(Request $request)
    {
        // info(__FUNCTION__, ['all' => $request->all()]);
        /* VARIABLE */
        $user_ip = isset($request->user_ip) ? $request->user_ip : '';
        $company_id = isset($request->company_id) ? $request->company_id : ''; // company_id client
        $company_parent = isset($request->company_parent) ? $request->company_parent : ''; // company_id client
        // info(__FUNCTION__, ['company_id' => $company_id, 'company_parent' => $company_parent]);
        if(empty($company_id))
            return response()->json(['result' => 'failed', 'mesage' => 'company id cannot be empty']);
        if(empty($company_parent))
            return response()->json(['result' => 'failed', 'mesage' => 'company parent cannot be empty']);
        /* VARIABLE */

        /* IS CLIENT UNDER AGENCY */
        $systemid = config('services.application.systemid');
        $client = User::select('users.*', 'companies.ghl_custom_menus', 'companies.company_name')
                      ->join('companies', 'companies.id', '=', 'users.company_id')
                      ->where('users.company_id', $company_id)
                      ->where('users.company_parent', $company_parent)
                      ->where('users.company_root_id', $systemid)
                      ->where('users.user_type', 'client')
                      ->where('users.active', 'T')
                      ->first();
        if(empty($client))
            return response()->json(['result' => 'failed', 'message' => 'client not found'], 404);

        $ghl_custom_menus = (isset($client->ghl_custom_menus) && !empty($client->ghl_custom_menus)) ? json_decode($client->ghl_custom_menus, true) : [];
        $custom_menu_id = isset($ghl_custom_menus['id']) ? $ghl_custom_menus['id'] : '';
        /* IS CLIENT UNDER AGENCY */

        /* CHECK AGENCY EXISTS */
        $companyAgency = Company::select('companies.*')
                                ->join('users', 'users.company_id', '=', 'companies.id')
                                ->where('companies.id', $company_parent)
                                ->where('users.user_type', 'userdownline')
                                ->first();
        if(empty($companyAgency))
            return response()->json(['result' => 'failed', 'message' => 'agency not found']);

        $agency_ghl_token = $this->gohighlevelv2GetTokensDBAgency($company_parent);
        $access_token = isset($agency_ghl_token['access_token']) ? $agency_ghl_token['access_token'] : '';
        $refresh_token = isset($agency_ghl_token['refresh_token']) ? $agency_ghl_token['refresh_token'] : '';
        // info(__FUNCTION__, ['access_token' => $access_token, 'refresh_token' => $refresh_token]);
        /* CHECK AGENCY EXISTS */

        /* PROCESS DELETE CUSTOM MENU CLIENT */
        $response = $this->gohighlevelv2DeleteCustomMenuAgency($company_parent, $access_token, $refresh_token, $custom_menu_id);
        // info(__FUNCTION__, ['response' => $response]);
        /* PROCESS DELETE CUSTOM MENU CLIENT */

        /* DELETE SSO ACCESS TOKEN */
        $url_custom_menu = $ghl_custom_menus['url'] ?? '';

        $parse_url = parse_url($url_custom_menu);
        $parse_query = $parse_url['query'] ?? "";

        $query_array = [];
        parse_str($parse_query, $query_array);
        $token_sso = $query_array['token'] ?? '';

        $affectedRow = SsoAccessToken::whereEncrypted('token', $token_sso)
                                     ->where('not_remove', 'T')
                                     ->delete();
        // info(__FUNCTION__, [
        //     'affectedRow' => $affectedRow, 
        //     'token_sso' => $token_sso,
        //     'url_custom_menu' => $url_custom_menu,
        //     'parse_url' => $parse_url,
        //     'parse_query' => $parse_query,
        //     'query_array' => $query_array,
        // ]);
        /* DELETE SSO ACCESS TOKEN */

        /* REMOVE CUSTOM MENU CLIENT IN DB */
        Company::where('id', $company_id)
               ->update([
                    'ghl_company_id' => null,
                    'ghl_tokens' => null,
                    'ghl_custom_menus' => null,
                    'ghl_credentials_id' => null,
               ]);
        /* REMOVE CUSTOM MENU CLIENT IN DB */

        /* USER LOGS */
        $user_id_login = optional(auth()->user())->id;
        $client_name = $client->name ?? "";
        $client_email = $client->email ?? "";
        $company_name = $client->company_name ?? "";
        $client_id = $client->id ?? null;
        if(empty($user_id_login))
            $user_id_login = $client_id;
        $description = "The client has successfully disconnected iframe their GoHighLevel account. Name: $client_name | Email: $client_email | Company Name: $company_name | Company ID : $company_id";
        $this->logUserAction($user_id_login,'Client Disconnected Iframe Lead Connector',$description,$user_ip,$client_id);
        /* USER LOGS */

        return response()->json(['result' => 'success']);
    }

    public function gohighlevelv2CreateIframeClientYourAccount(Request $request)
    {
        // for($i = 0; $i < 10; $i++)info(__FUNCTION__);
        // info(__FUNCTION__, ['all' => $request->all()]);

        /* VARIABLE */
        $user_ip = isset($request->user_ip) ? $request->user_ip : '';
        $is_in_iframe = isset($request->is_in_iframe) ? $request->is_in_iframe : false;
        $user_id_login = optional(auth()->user())->id;
        $app_url = env('APP_URL');
        $systemid = config('services.application.systemid');

        $company_id = isset($request->company_id) ? $request->company_id : '';
        $subdomain_url = isset($request->subdomain_url) ? $request->subdomain_url : '';
        $custom_menu_name = isset($request->custom_menu_name) ? trim($request->custom_menu_name) : '';
        $company_name = isset($request->company_name) ? trim($request->company_name) : '';

        $credentials_ghl_emm_private = GlobalSettings::where('company_id', $systemid)->where('setting_name', 'credentials_ghl_emm_private')->first();
        // info(__FUNCTION__, ['credentials_ghl_emm_private' => $credentials_ghl_emm_private]);
        if(empty($credentials_ghl_emm_private))
            return response()->json(['result' => 'error', 'message' => 'credentials ghl not found'], 404);
        
        $credentials = json_decode($credentials_ghl_emm_private->setting_value, true);
        $base_url = $credentials['base_url'] ?? '';
        $client_id = $credentials['client_id'] ?? '';
        $scope = $credentials['scope'] ?? '';
        /* VARIABLE */

        // info(__FUNCTION__, ['app_url' => $app_url,'base_url' => $base_url,'client_id' => $client_id,'scope' => $scope]);

        /* VALIDATION */
        if(empty($app_url))
            return response()->json(['result' => 'error', 'message' => 'app url not empty'], 400);
        if(empty($base_url)) 
            return response()->json(['result' => 'error', 'message' => 'base url not empty'], 400);
        if(empty($client_id))
            return response()->json(['result' => 'error', 'message' => 'client id not empty'], 400);
        if(empty($scope))
            return response()->json(['result' => 'error', 'message' => 'scopes not empty'], 400);
        if(empty($company_id))
            return response()->json(['result' => 'error', 'message' => 'company id not empty'], 400);
        if(empty($subdomain_url))
            return response()->json(['result' => 'error', 'message' => 'subdomain url not empty'], 400);

        $clientCompany = Company::select('companies.*')
            ->join('users', 'users.company_id', '=', 'companies.id')
            ->where('users.company_id', $company_id)
            ->where('users.user_type', 'client')
            ->where('users.active', 'T')
            ->first();
        if(empty($clientCompany))
            return response()->json(['result' => 'error', 'message' => 'client not found'], 404);

        $clientCompanySetting = $this->getcompanysetting($company_id, 'ghl_custom_menus_client_your_account');
        // info(__FUNCTION__, ['clientCompanySetting' => $clientCompanySetting]);
        if($clientCompanySetting)
            return response()->json(['result' => 'error', 'message' => 'your has ben connect to gohighlevel'], 400);
        /* VALIDATION */

        /* DATA STATE */
        $state = [
            'company_id' => $company_id,
            'subdomain_url' => $subdomain_url,
            'custom_menu_name' => $custom_menu_name,
            'user_id_login' => $user_id_login,
            'user_ip' => $user_ip,
            'company_name' => $company_name,
            'user_type' => 'client',
            'action_type' => 'ghl_custom_menus_client_your_account',
            'is_in_iframe' => $is_in_iframe,
        ];
        // info(['state' => $state]);
        $state = base64_encode(json_encode($state));
        /* DATA STATE */

        /* URL FOR REDIRECT URL */
        $redirect_uri = "{$app_url}/leadconnectorv2/oauth/callback/view";
        $params = [
            'response_type' => 'code',
            'redirect_uri' => $redirect_uri,
            'client_id' => $client_id,
            'scope' => $scope,
            'state' => $state
        ];
        $auth_url = "$base_url/oauth/chooselocation?" . http_build_query($params);
        // info(__FUNCTION__, ['auth_url' => $auth_url]);
        // for($i = 0; $i < 10; $i++)info(__FUNCTION__);
        /* URL FOR REDIRECT URL */

        return response()->json(['result' => 'success', 'url' => $auth_url]);
    }
    public function gohighlevelv2DeleteIframeClientYourAccount(Request $request)
    {
        // info(__FUNCTION__, ['all' => $request->all()]);

        /* GET COMPANY CLIENT */
        $salt = substr(hash('sha256', env('APP_KEY')), 0, 16);
        $user_id_login = optional(auth()->user())->id;
        $company_id = isset($request->company_id) ? $request->company_id : '';
        $clientCompany = Company::select(
                'companies.*',
                'users.id as client_id')
            ->selectRaw("CONVERT(AES_DECRYPT(FROM_BASE64(users.name), ?) USING utf8mb4) as client_name", [$salt])
            ->selectRaw("CONVERT(AES_DECRYPT(FROM_BASE64(users.email), ?) USING utf8mb4) as client_email", [$salt])
            ->join('users', 'users.company_id', '=', 'companies.id')
            ->where('users.company_id', $company_id)
            ->where('users.user_type', 'client')
            ->where('users.active', 'T')
            ->first();
        if(empty($clientCompany))
            return response()->json(['result' => 'error', 'message' => 'client not found'], 404);
        /* GET COMPANY CLIENT */

        /* GET GHL TOKENS */
        $ghl_custom_menus_client_your_account = $this->getcompanysetting($company_id, 'ghl_custom_menus_client_your_account');
        $access_token = isset($ghl_custom_menus_client_your_account->ghl_tokens->access_token) ? $ghl_custom_menus_client_your_account->ghl_tokens->access_token : '';
        $refresh_token = isset($ghl_custom_menus_client_your_account->ghl_tokens->refresh_token) ? $ghl_custom_menus_client_your_account->ghl_tokens->refresh_token : '';
        // info(__FUNCTION__, ['ghl_custom_menus_client_your_account' => $ghl_custom_menus_client_your_account]);
        /* GET GHL TOKENS */

        /* REMOVE CUSTOM MENU LINK */
        $custom_menu_link_id = isset($ghl_custom_menus_client_your_account->ghl_custom_menus) ? $ghl_custom_menus_client_your_account->ghl_custom_menus->id : "";
        $deleteCustomMenu = $this->gohighlevelv2DeleteCustomMenuAgency($company_id, $access_token, $refresh_token, $custom_menu_link_id, 'ghl_custom_menus_client_your_account');
        // info(__FUNCTION__, ['deleteCustomMenu' => $deleteCustomMenu]);
        /* REMOVE CUSTOM MENU LINK */

        /* DELETE SSO ACCESS TOKEN */
        $url_custom_menu = isset($ghl_custom_menus_client_your_account->ghl_custom_menus->url) ? $ghl_custom_menus_client_your_account->ghl_custom_menus->url : "";

        $parse_url = parse_url($url_custom_menu);
        $parse_query = $parse_url['query'] ?? "";

        $query_array = [];
        parse_str($parse_query, $query_array);
        $token_sso = $query_array['token'] ?? '';

        SsoAccessToken::whereEncrypted('token', $token_sso)->where('not_remove', 'T')->delete();
        /* DELETE SSO ACCESS TOKEN */

        /* REMOVE GHL COMPANY ID AND TOKENS */
        CompanySetting::where('company_id', $company_id)->whereEncrypted('setting_name', 'ghl_custom_menus_client_your_account')->delete();
        /* REMOVE GHL COMPANY ID AND TOKENS */

        /* USER LOG */
        $client_name = $clientCompany->client_name ?? "";
        $client_email = $clientCompany->client_email ?? "";
        $company_name = $clientCompany->company_name ?? "";
        $client_id = $clientCompany->client_id ?? "";
        $user_ip = isset($request->user_ip) ? $request->user_ip : '';
        $description = "The client has successfully disconnect their lead connector account. Name: $client_name | Email: $client_email | Company Name: $company_name | Company ID : $company_id";
        $this->logUserAction($user_id_login,'Client Disconnect Lead Connector Your Account',$description,$user_ip,$client_id);
        /* USER LOG */
    }
    // iframe client

    // token
    public function gohighlevelv2GetTokensDBAgency($company_id = "")
    {
        // info(__FUNCTION__);

        $company = Company::where('id', $company_id)
                          ->first();
    
        $ghl_tokens = isset($company->ghl_tokens) ? json_decode($company->ghl_tokens, true): [];
        $access_token = isset($ghl_tokens['access_token']) ? $ghl_tokens['access_token'] : '';
        $refresh_token = isset($ghl_tokens['refresh_token']) ? $ghl_tokens['refresh_token'] : '';
    
        return [
            'access_token' => $access_token,
            'refresh_token' => $refresh_token,
        ];
    }
    public function gohighlevelv2RefreshTokenAgency($company_id = "", $refresh_token = "", $action_type = "")
    {
        // info(__FUNCTION__, ['company_id' => $company_id, 'refresh_token' => $refresh_token]);
        $http = new \GuzzleHttp\Client;
        $systemid = config('services.application.systemid');

        $ghl_credentials_id = "";
        if($action_type == 'ghl_custom_menus_client_your_account'){
            $ghl_custom_menus_client_your_account = $this->getcompanysetting($company_id, 'ghl_custom_menus_client_your_account');
            $ghl_credentials_id = isset($ghl_custom_menus_client_your_account->ghl_credentials_id) ? $ghl_custom_menus_client_your_account->ghl_credentials_id : "";
            // info(__FUNCTION__ . ' 1.1', ['ghl_custom_menus_client_your_account' => $ghl_custom_menus_client_your_account]);
        }else{
            $ghl_credentials_id = Company::where('id', $company_id)->value('ghl_credentials_id');
            // info(__FUNCTION__ . ' 2.1', ['ghl_credentials_id' => $ghl_credentials_id]);
        }

        $credentials_ghl_emm = GlobalSettings::where('company_id', $systemid)
                                             ->where('id', $ghl_credentials_id)
                                             ->whereIn('setting_name', ['credentials_ghl_emm_private', 'credentials_ghl_emm_public'])
                                             ->first();
        // info(__FUNCTION__, ['ghl_credentials_id' => $ghl_credentials_id, 'credentials_ghl_emm' => $credentials_ghl_emm]);
        if(empty($credentials_ghl_emm))
            return response()->json(['message' => 'credentials ghl not found'], 404);
        
        $credentials = json_decode($credentials_ghl_emm->setting_value, true);
        $client_id = $credentials['client_id'] ?? '';
        $client_secret = $credentials['client_secret'] ?? '';
        /* VARIABLE */

        try
        {
            $url = "https://services.leadconnectorhq.com/oauth/token";
            $form_params = [
                'client_id' => $client_id,
                'client_secret' => $client_secret,
                'grant_type' => 'refresh_token',
                'refresh_token' => $refresh_token,
            ];
            $headers = [
                'Accept' => 'application/json',
                'Content-Type' => 'application/x-www-form-urlencoded',
            ];
            $parameter = [
                'form_params' => $form_params,
                'headers' => $headers
            ];

            $response = $http->post($url, $parameter);
            $response = json_decode($response->getBody(), true);
            // info(__FUNCTION__ . ' 3.1', ['response' => $response]);

            $access_token = isset($response['access_token']) ? $response['access_token'] : '';
            $refresh_token = isset($response['refresh_token']) ? $response['refresh_token'] : '';

            if($action_type == 'ghl_custom_menus_client_your_account'){
                $ghl_custom_menus_client_your_account_updated = $this->getcompanysetting($company_id, 'ghl_custom_menus_client_your_account', true);
                // info(__FUNCTION__ . ' 4.1', ['ghl_custom_menus_client_your_account_updated' => $ghl_custom_menus_client_your_account_updated]);
                if(!empty($ghl_custom_menus_client_your_account_updated)){
                    $ghl_custom_menus_client_your_account_updated['ghl_tokens'] = $response;
                    // info(__FUNCTION__ . ' 4.2', ['ghl_custom_menus_client_your_account_updated' => $ghl_custom_menus_client_your_account_updated]);
                    $ghl_custom_menus_client_your_account_updated = json_encode($ghl_custom_menus_client_your_account_updated, JSON_UNESCAPED_SLASHES);
                    // info(__FUNCTION__ . ' 4.3', ['ghl_custom_menus_client_your_account_updated' => $ghl_custom_menus_client_your_account_updated]);
                    $companySetting = CompanySetting::where('company_id', $company_id)->whereEncrypted('setting_name', 'ghl_custom_menus_client_your_account')->first();
                    $companySetting->setting_value = $ghl_custom_menus_client_your_account_updated;
                    $companySetting->save();
                }
            }else{
                $tokens_encode = json_encode($response, JSON_UNESCAPED_SLASHES);
                // info(__FUNCTION__ . ' 5.1', ['tokens_encode' => $tokens_encode]);
                Company::where('id', $company_id)->update(['ghl_tokens' => $tokens_encode]);
            }
            // info(__FUNCTION__ . ' try 1.1', ['status' => 'success','access_token' => $access_token,'refresh_token' => $refresh_token]);
            return [
                'status' => 'success',
                'access_token' => $access_token,
                'refresh_token' => $refresh_token
            ];
        }
        catch (\GuzzleHttp\Exception\BadResponseException $e)
        {
            // info(__FUNCTION__ . ' catch 1', ['status' => 'error','message' => $e->getMessage()]);
            return [
                'status' => 'error',
                'message' => $e->getMessage()
            ];
        }
        catch (\Exception $e)
        {
            // info(__FUNCTION__ . ' catch 1', ['status' => 'error','message' => $e->getMessage()]);
            return [
                'status' => 'error',
                'message' => $e->getMessage()
            ];
        }
    }
    // token

    // redirect url
    public function gohighlevelv2OauthCallbackView(Request $request)
    {
        $code = isset($request->code) ? $request->code : '';
        $data_state = isset($request->state) ? $request->state : '';

        return view('gohighlevelv2-oauth-callback', compact('code','data_state'));
    }
    public function gohighlevelv2OauthCallbackProcess(Request $request) 
    {
        // for($i = 0; $i < 10; $i++)info(__FUNCTION__);
        /* CHECK COMPANY ID AGENCY OR CLIENT */
        $data_state = isset($request->state) ? json_decode(base64_decode($request->state), true) : [];

        $company_id = isset($data_state['company_id']) ? $data_state['company_id'] : '';
        $company_root_ids = User::whereNull('company_parent')->pluck('company_id')->toArray();

        $user = User::where('company_id', $company_id)->first();
        $user_type = isset($user->user_type) ? $user->user_type : '';
        $action_type = isset($data_state['action_type']) ? $data_state['action_type'] : '';
        // info(__FUNCTION__, ['user_type' => $user_type, 'action_type' => $action_type]);
        
        if($user_type == 'client'){ // jika client
            if($action_type == 'ghl_custom_menus_client_your_account'){
                // for($i = 0; $i < 10; $i++)info(__FUNCTION__);
                return $this->gohighlevelv2OauthCallbackProcessClientYourAccount($request);
            }else{
                // for($i = 0; $i < 10; $i++)info(__FUNCTION__);
                return $this->gohighlevelv2OauthCallbackProcessClient($request);
            }
        }elseif(in_array($user_type, ['userdownline', 'user']) && !in_array($company_id, $company_root_ids)){ // jika agency
            // for($i = 0; $i < 10; $i++)info(__FUNCTION__);
            return $this->gohighlevelv2OauthCallbackProcessAgency($request);
        }else{
            // for($i = 0; $i < 10; $i++)info(__FUNCTION__);
            return response()->json(['result' => 'error', 'message' => 'user type not mathing'], 400);
        }
        /* CHECK COMPANY ID AGENCY OR CLIENT */
    }
    public function gohighlevelv2OauthCallbackProcessClient(Request $request)
    {
        $startTime = microtime(true);
        // info('start function ' . __FUNCTION__);

        $http = new \GuzzleHttp\Client;
        
        $code = isset($request->code) ? $request->code : '';
        $data_state = isset($request->state) ? json_decode(base64_decode($request->state), true) : [];

        $company_id = isset($data_state['company_id']) ? $data_state['company_id'] : '';
        $enable_sendgrid = isset($data_state['enable_sendgrid']) ? $data_state['enable_sendgrid'] : '';
        $subdomain_url = isset($data_state['subdomain_url']) ? $data_state['subdomain_url'] : '';
        $user_id_login = isset($data_state['user_id_login']) ? $data_state['user_id_login'] : '';
        $user_ip = isset($data_state['user_ip']) ? $data_state['user_ip'] : '';
        $is_in_iframe = isset($data_state['is_in_iframe']) ? $data_state['is_in_iframe'] : false;

        $grant_type = 'authorization_code';
        $user_type = 'Location';
        $client_id = config('services.gohighlevelv2.client_id');
        $client_secret = config('services.gohighlevelv2.client_secret');

        /* GET DETAILS CLIENT */
        $salt = substr(hash('sha256', env('APP_KEY')), 0, 16);
        $client = Company::select(
                                'companies.*',
                                'users.id as client_id')
                            ->selectRaw("CONVERT(AES_DECRYPT(FROM_BASE64(users.name), ?) USING utf8mb4) as client_name", [$salt])
                            ->selectRaw("CONVERT(AES_DECRYPT(FROM_BASE64(users.email), ?) USING utf8mb4) as client_email", [$salt])
                            ->join('users', 'users.company_id', '=', 'companies.id')
                            ->where('companies.id', $company_id)
                            ->where('users.user_type', 'client')
                            ->where('users.active', 'T')
                            ->first();
        $client_user_id = $client->client_id ?? null;
        $client_name = $client->client_name ?? "";
        $client_email = $client->client_email ?? "";
        $company_name = $client->company_name ?? "";
        /* GET DETAILS CLIENT */

        // info([
        //     'code' => $code,
        //     'data_state' => $data_state,
        //     'company_id' => $company_id,
        //     'enable_sendgrid' => $enable_sendgrid,
        //     'subdomain_url' => $subdomain_url,
        //     'grant_type' => $grant_type,
        //     'user_type' => $user_type,
        //     'client_id' => $client_id,
        //     'client_secret' => $client_secret
        // ]);    

        if(empty($code) || empty($company_id) ||empty($subdomain_url) || empty($client_id) || empty($client_secret)) 
        {
            // $user_type, $company_id, $company_name, $personal_name, $personal_email, $function, $message
            // build fields for message error
            $fields = [
                'code' => $code,
                'company_id' => $company_id,
                'subdomain_url' => $subdomain_url,
                'client_id' => $client_id,
                'client_secret' => $client_secret,
            ];

            $emptyFields = [];
            foreach ($fields as $key => $value) 
            {
                if (empty($value)) 
                {
                    $emptyFields[] = $key;
                }
            }

            $message = (!empty($emptyFields)) ? 
                       (implode(', ', $emptyFields) . ' required') :
                       ('All fields valid');
            // build fields for message error

            $this->gohighlevelv2SendEmailErrorLog([
                'user_type' => 'client',
                'company_id' => $company_id,
                'company_name' => $company_name,
                'personal_name' => $client_name,
                'personal_email' => $client_email,
                'function' => __FUNCTION__,
                'message' => "#1 $message"
            ]);
            return response()->json(['status' => 'error', 'error' => $message], 400);
        }

        $url = "https://services.leadconnectorhq.com/oauth/token";
        $form_params = [
            'client_id' => $client_id,
            'client_secret' => $client_secret,
            'grant_type' => $grant_type,
            'code' => $code,
            'user_type' => $user_type,
        ];
        $headers = [
            'Accept' => 'application/json',
            'Content-Type' => 'application/x-www-form-urlencoded',
        ];
        $parameter = [
            'form_params' => $form_params,
            'headers' => $headers
        ];

        try
        {
            /* GET ACCESS TOKEN */
            $response = $http->post($url, $parameter);
            $response = json_decode($response->getBody(), true);

            $access_token = isset($response['access_token']) ? $response['access_token'] : '';
            $refresh_token = isset($response['refresh_token']) ? $response['refresh_token'] : '';
            $location_id = isset($response['locationId']) ? $response['locationId'] : '';
            $ghl_user_type = isset($response['userType']) ? strtolower(trim($response['userType'])) : '';
            $tokens_encode = json_encode($response, JSON_UNESCAPED_SLASHES);

            if($ghl_user_type != 'location')
            {
                $message = "user type gohighlevel must be sub acccount";
                $this->gohighlevelv2SendEmailErrorLog([
                    'user_type' => 'client',
                    'company_id' => $company_id,
                    'company_name' => $company_name,
                    'personal_name' => $client_name,
                    'personal_email' => $client_email,
                    'function' => __FUNCTION__,
                    'message' => "#2 $message",
                ]);
                return response()->json(['user_type' => 'client', 'status' => 'error', 'error_type' => 'permission_denied', 'error' => $message], 400);
            }
            
            // return response()->json(['response' => $response,'subdomain_url' => $subdomain_url]);
            // return redirect()->to($subdomain_url);
            /* GET ACCESS TOKEN */
            
            /* SAVE ACCESS TOKEN TO DB */
            $data = IntegrationSettings::where('company_id','=',$company_id)
                                       ->where('integration_slug','=','gohighlevel')
                                       ->first();

            if(!empty($data))
            {
                // info('gohighlevelv2OauthCallback if');
                /* MERGE CUSTOM FIELD */
                $custom_fields_merge = isset($data_state['custom_fields_general']) ? json_decode($data_state['custom_fields_general'], true) : [];
                if (isset($data_state['custom_fields_b2b']) && !empty($data_state['custom_fields_b2b']))
                {
                    $custom_fields_b2b = json_decode($data_state['custom_fields_b2b'], true);
                    $custom_fields_merge = array_merge($custom_fields_merge, $custom_fields_b2b);
                }
                if (isset($data_state['custom_fields_advance']) && !empty($data_state['custom_fields_advance']))
                {
                    $custom_fields_advance = json_decode($data_state['custom_fields_advance'], true);
                    $custom_fields_merge = array_merge($custom_fields_merge, $custom_fields_advance);
                }
                /* MERGE CUSTOM FIELD */

                /* CHECK API KEY VALID */
                $this->gohighlevelv2CheckApiKeyIsValid($company_id, $access_token, $refresh_token, $location_id, $custom_fields_merge);
                /* CHECK API KEY VALID */

                /* FIND INTEGRATION SETTING */
                $update = IntegrationSettings::find($data->id);
                $update->enable_sendgrid = $enable_sendgrid;
                /* FIND INTEGRATION SETTING */

                /* SAVE CUSTOM FIELD TO GLOBAL SETTINGS */
                $custom_fields = isset($data_state['custom_fields_general']) ? $data_state['custom_fields_general'] : null;
                $custom_fields_b2b = isset($data_state['custom_fields_b2b']) ? $data_state['custom_fields_b2b'] : null;
                if($custom_fields_b2b)
                {
                    $globalsetting = GlobalSettings::updateOrCreate(
                        [
                            'setting_name' => 'custom_fields_b2b_gohighlevel',
                            'company_id' => $company_id
                        ],
                        [
                            'setting_value' => $custom_fields_b2b
                        ]
                    );
                }

                $custom_fields_advance = isset($data_state['custom_fields_advance']) ? $data_state['custom_fields_advance'] : null;
                if($custom_fields_advance)
                {
                    $globalsetting = GlobalSettings::updateOrCreate(
                        [
                            'setting_name' => 'custom_fields_advance_gohighlevel',
                            'company_id' => $company_id
                        ],
                        [
                            'setting_value' => $custom_fields_advance
                        ]
                    );
                }
                /* SAVE CUSTOM FIELD TO GLOBAL SETTINGS */

                $update->custom_fields = $custom_fields;
                $update->version = 2;
                $update->tokens = $tokens_encode;
                $update->save();
            }
            else 
            {
                // info('gohighlevelv2OauthCallback else');
                /* CHECK API KEY */
                $this->gohighlevelv2CheckApiKeyIsValid($company_id, $access_token, $refresh_token, $location_id);
                /* CHECK API KEY */

                $custom_fields_general = isset($data_state['custom_fields_general']) ? $data_state['custom_fields_general'] : null;
                
                /* SAVE CUSTOM FIELD B2B */
                $custom_fields_b2b = isset($data_state['custom_fields_b2b']) ? $data_state['custom_fields_b2b'] : null;
                // info('gohighlevelv2OauthCallback save custom field b2b', [
                //     'custom_fields_b2b' => $custom_fields_b2b,
                //     'company_id' => $company_id
                // ]);
                if($custom_fields_b2b)
                {
                    $globalsetting = GlobalSettings::updateOrCreate(
                        [
                            'setting_name' => 'custom_fields_b2b_gohighlevel',
                            'company_id' => $company_id
                        ],
                        [
                            'setting_value' => $custom_fields_b2b
                        ]
                    );
                }
                /* SAVE CUSTOM FIELD B2B */

                /* SAVE CUSTOM FIELD ADVANCE */
                $custom_fields_advance = isset($data_state['custom_fields_advance']) ? $data_state['custom_fields_advance'] : null;
                // info('gohighlevelv2OauthCallback save custom field advance', [
                //     'custom_fields_advance' => $custom_fields_advance,
                //     'company_id' => $company_id
                // ]);
                if($custom_fields_advance)
                {
                    $globalsetting = GlobalSettings::updateOrCreate(
                        [
                            'setting_name' => 'custom_fields_advance_gohighlevel',
                            'company_id' => $company_id
                        ],
                        [
                            'setting_value' => $custom_fields_advance
                        ]
                    );
                }
                /* SAVE CUSTOM FIELD ADVANCE */

                /* SAVE INTEGRATION SETTINGS */
                // info('gohighlevelv2OauthCallback save integration settings');
                IntegrationSettings::create([
                    'company_id' => $company_id,
                    'integration_slug' => 'gohighlevel',
                    'api_key' => '',
                    'enable_sendgrid' => 1,
                    'enable_default_campaign' => 0,
                    'subdomain' => '',
                    'workspace_id' => '',
                    'custom_fields' => $custom_fields_general,
                    'version' => 2,
                    'tokens' => $tokens_encode,
                ]);
                /* SAVE INTEGRATION SETTINGS */
            }
            /* SAVE ACCESS TOKEN TO DB */

            $endTime = microtime(true);
            // info(['difftTime' => ($endTime - $startTime),'status' => 'success','subdomain_url' => $subdomain_url]);

            /* USER LOGS */
            $description = "The client has successfully connected their lead connector account. Name: $client_name | Email: $client_email | Company Name: $company_name | Company ID : $company_id";
            $this->logUserAction($user_id_login,'Client Connect Lead Connector',$description,$user_ip,$client_user_id);
            /* USER LOGS */

            return response()->json([
                'user_type' => 'client',
                'status' => 'success',
                'subdomain_url' => $subdomain_url,
                'is_in_iframe' => $is_in_iframe,
                'company_id' => $company_id,
                'email' => $client_email,
            ]);
        }
        catch (\GuzzleHttp\Exception\ClientException $e)
        {
            $body = json_decode((string) $e->getResponse()->getBody());
            $message = $body->error_description ?? "Something Went Wrong";
            info(['error1' => $message]);
            $this->gohighlevelv2SendEmailErrorLog([
                'user_type' => 'client',
                'company_id' => $company_id,
                'company_name' => $company_name,
                'personal_name' => $client_name,
                'personal_email' => $client_email,
                'function' => __FUNCTION__,
                'message' => "#3 $message"
            ]);
            return response()->json(['user_type' => 'client', 'status' => 'error', 'error' => $message], 400);
        }
        catch (\Exception $e)
        {
            info(['error2' => $e->getMessage()]);
            $message = $e->getMessage();
            $this->gohighlevelv2SendEmailErrorLog([
                'user_type' => 'client',
                'company_id' => $company_id,
                'company_name' => $company_name,
                'personal_name' => $client_name,
                'personal_email' => $client_email,
                'function' => __FUNCTION__,
                'message' => "#4 $message"
            ]);
            return response()->json(['user_type' => 'client', 'status' => 'error', 'error' => $message], 400);
        }
    }
    public function gohighlevelv2OauthCallbackProcessClientYourAccount(Request $request)
    {
        // for($i = 0; $i < 10; $i++)info(__FUNCTION__);
        // info(__FUNCTION__, ['all' => $request->all()]);
        $company_root_id = config('services.application.systemid');
        $http = new \GuzzleHttp\Client;
        $code = isset($request->code) ? $request->code : '';
        $grant_type = 'authorization_code';
        $user_type = 'Company';
        $data_state = isset($request->state) ? json_decode(base64_decode($request->state), true) : [];
        // info(__FUNCTION__, ['data_state' => $data_state]);
        $company_id = isset($data_state['company_id']) ? $data_state['company_id'] : '';
        $subdomain_url = isset($data_state['subdomain_url']) ? $data_state['subdomain_url'] : '';
        $is_in_iframe = isset($data_state['is_in_iframe']) ? $data_state['is_in_iframe'] : false;
        $custom_menu_name = $data_state['custom_menu_name'];
        $user_id_login = isset($data_state['user_id_login']) ? $data_state['user_id_login'] : '';
        $user_ip = isset($data_state['user_ip']) ? $data_state['user_ip'] : '';
        $action_type = isset($data_state['action_type']) ? $data_state['action_type'] : '';

        /* GET CLIENT */
        $salt = substr(hash('sha256', env('APP_KEY')), 0, 16);
        $company = Company::select(
                'companies.*',
                'users.id as user_id',
                'users.company_parent as company_parent')
            ->selectRaw("CONVERT(AES_DECRYPT(FROM_BASE64(users.name), ?) USING utf8mb4) as client_name", [$salt])
            ->selectRaw("CONVERT(AES_DECRYPT(FROM_BASE64(users.email), ?) USING utf8mb4) as client_email", [$salt])
            ->join('users', 'users.company_id', '=', 'companies.id')
            ->where('companies.id', $company_id)
            ->where('users.user_type', 'client')
            ->first();
        if(empty($company)){
            $message = "client not found";
            $this->gohighlevelv2SendEmailErrorLog([
                'user_type' => 'client',
                'company_id' => $company_id,
                'function' => __FUNCTION__,
                'message' => "#1 $message"
            ]);
            return response()->json(['result' => 'error', 'message' => 'client not found'], 404);
        }
        $user_id = isset($company->user_id) ? $company->user_id : null;
        $client_name = isset($company->client_name) ? $company->client_name : '';
        $client_email = isset($company->client_email) ? $company->client_email : '';
        $company_name = isset($company->company_name) ? $company->company_name : '';
        $company_parent = isset($company->company_parent) ? $company->company_parent : '';
        /* GET CLIENT */

        /* GET CREDENTIAL EMM PRIVATE */
        $credentials_ghl_emm_private = GlobalSettings::where('company_id', $company_root_id)->where('setting_name', 'credentials_ghl_emm_private')->first();
        if(empty($credentials_ghl_emm_private)){
            $message = "credential ghl emm private not found";
            $this->gohighlevelv2SendEmailErrorLog([
                'user_type' => 'client',
                'company_id' => $company_id,
                'company_name' => $company_name,
                'personal_name' => $client_name,
                'personal_email' => $client_email,
                'function' => __FUNCTION__,
                'message' => "#2 $message"
            ]);
            return response()->json(['result' => 'error', 'message' => $message], 404);
        }
        $ghl_credentials_id = $credentials_ghl_emm_private->id;
        $credentials = json_decode($credentials_ghl_emm_private->setting_value, true);
        $client_id = $credentials['client_id'] ?? '';
        $client_secret = $credentials['client_secret'] ?? '';
        /* GET CREDENTIAL EMM PRIVATE */

        try{
            /* GET ACCESS TOKEN */
            // Log::info(__FUNCTION__ . " GET ACCESS TOKEN");
            $url = "https://services.leadconnectorhq.com/oauth/token";
            $form_params = [
                'client_id' => $client_id,
                'client_secret' => $client_secret,
                'grant_type' => $grant_type,
                'code' => $code,
                'user_type' => $user_type,
            ];
            $headers = [
                'Accept' => 'application/json',
                'Content-Type' => 'application/x-www-form-urlencoded',
            ];
            $parameter = [
                'form_params' => $form_params,
                'headers' => $headers
            ];
            $response = $http->post($url, $parameter);
            $response = json_decode($response->getBody(), true);

            $access_token = $response['access_token'] ?? "";
            $ghl_company_id = $response['companyId'] ?? "";
            $ghl_user_type = $response['userType'] ?? "";
            // info('response1', ['response' => $response]);

            if(strtolower(trim($ghl_user_type)) != 'company'){
                $message = "user type gohighlevel must be sub acccount";
                $this->gohighlevelv2SendEmailErrorLog([
                    'user_type' => 'client',
                    'company_id' => $company_id,
                    'company_name' => $company_name,
                    'personal_name' => $client_name,
                    'personal_email' => $client_email,
                    'function' => __FUNCTION__,
                    'message' => "#3 $message"
                ]);
                return response()->json(['user_type' => 'client', 'action_type' => $action_type, 'status' => 'error', 'error_type' => 'permission_denied',  'error' => $message], 400);
            }
            /* GET ACCESS TOKEN */

            /* OVERRIDE CUSTOM MENU NAME IF EMPTY */
            if(empty($custom_menu_name) || trim($custom_menu_name) == '') 
                $custom_menu_name = "$company_name";
            /* OVERRIDE CUSTOM MENU NAME IF EMPTY */

            /* LOGIN SSO */
            // Log::info(__FUNCTION__ . " LOGIN SSO");
            $openApiToken = (object) ['company_id' => $company_parent]; // company id agency
            $dataRequest = new Request(['user_id' => $user_id]);
            $dataRequest->attributes->set('openApiToken', $openApiToken);
            $dataRequest->attributes->set('inGoHighLevel', true);
            
            $openApiController = app(OpenApiController::class);
            $response2 = $openApiController->ssoLogin('client', $dataRequest)->getData();
            $urlCustomMenuLink = isset($response2->url) ? $response2->url : '';
            // info(__FUNCTION__, ['urlCustomMenuLink' => $urlCustomMenuLink]);
            // info(__FUNCTION__, ['response2' => $response2]);
            /* LOGIN SSO */

            /* PROCESS CREATE CUSTOM MENU LINK */
            // Log::info(__FUNCTION__ . " PROCESS CREATE CUSTOM MENU LINK");
            $url3 = 'https://services.leadconnectorhq.com/custom-menus/';
            $headers3 = [
                'Accept' => 'application/json',
                'Authorization' => "Bearer $access_token",
                'Content-Type' => 'application/json',
                'Version' => '2021-07-28'
            ];
            $json3 = [
                'title' => $custom_menu_name,
                'url' => $urlCustomMenuLink,
                'icon' => [
                    'name' => 'envelope-open-text',
                    'fontFamily' => 'fas'
                ],
                'showOnCompany' => true,
                'showOnLocation' => false,
                'showToAllLocations' => false,
                'openMode' => 'iframe',
                'userRole' => 'all',
                'allowCamera' => false,
                'allowMicrophone' => false
            ];
            $parameter3 = [
                'headers' => $headers3,
                'json' => $json3,
            ];
            // info(__FUNCTION__, ['parameter3' => $parameter3]);
            $response3 = $http->post($url3, $parameter3);
            $response3 = json_decode($response3->getBody(), true);
            // info(__FUNCTION__, ['response3' => $response3]);
            $custom_menu_link_id = $response3['id'] ?? "";
            /* PROCESS CREATE CUSTOM MENU LINK */

            /* SAVE CUSTOM MENU LINK */
            $ghl_custom_menus_client_your_account = [
                'ghl_company_id' => $ghl_company_id,
                'ghl_tokens' => $response,
                'ghl_custom_menus' => $response3,
                'ghl_credentials_id' => $ghl_credentials_id,
            ];
            $clientCompanySetting = $this->getcompanysetting($company_id, 'ghl_custom_menus_client_your_account');
            if(empty($clientCompanySetting)){
                CompanySetting::create([
                    'company_id' => $company_id,
                    'setting_name' => 'ghl_custom_menus_client_your_account',
                    'setting_value' => json_encode($ghl_custom_menus_client_your_account, JSON_UNESCAPED_SLASHES),
                ]);
            }else{
                CompanySetting::where('company_id', $company_id)->whereEncrypted('setting_name', 'ghl_custom_menus_client_your_account')->update(['setting_value' => json_encode($ghl_custom_menus_client_your_account, JSON_UNESCAPED_SLASHES)]);
            }
            /* SAVE CUSTOM MENU LINK */

            /* USER LOGS */
            $description = "The client has successfully connected their lead connector account. Name: $client_name | Email: $client_email | Company Name: $company_name | Company ID : $company_id | Menu Name: $custom_menu_name";
            $this->logUserAction($user_id_login,'Client Connect Lead Connector Your Account',$description,$user_ip,$user_id);
            /* USER LOGS */

            // for($i = 0; $i < 10; $i++)info(__FUNCTION__);
            return response()->json([
                'user_type' => 'client',
                'status' => 'success',
                'subdomain_url' => $subdomain_url,
                'is_in_iframe' => $is_in_iframe,
                'action_type' => $action_type,
            ]);

        }catch(\GuzzleHttp\Exception\ClientException $e){
            $body = json_decode((string) $e->getResponse()->getBody());
            $message = $body->error_description ?? "Something Went Wrong";
            info(__FUNCTION__, ['error1' => $e->getMessage()]);
            // for($i = 0; $i < 10; $i++)info(__FUNCTION__);
            $this->gohighlevelv2SendEmailErrorLog([
                'user_type' => 'client',
                'company_id' => $company_id,
                'company_name' => $company_name,
                'personal_name' => $client_name,
                'personal_email' => $client_email,
                'function' => __FUNCTION__,
                'message' => "#4 $message"
            ]);
            return response()->json(['user_type' => 'client', 'action_type' => $action_type, 'status' => 'error', 'error' => $message], 400);
        }catch (\Exception $e){
            info(__FUNCTION__, ['error2' => $e->getMessage()]);
            $message = $e->getMessage();
            $this->gohighlevelv2SendEmailErrorLog([
                'user_type' => 'client',
                'company_id' => $company_id,
                'company_name' => $company_name,
                'personal_name' => $client_name,
                'personal_email' => $client_email,
                'function' => __FUNCTION__,
                'message' => "#5 $message"
            ]);
            // for($i = 0; $i < 10; $i++)info(__FUNCTION__);
            return response()->json(['user_type' => 'client', 'action_type' => $action_type, 'status' => 'error', 'error' => $message], 400);
        }
    }
    public function gohighlevelv2OauthCallbackProcessAgency(Request $request)
    {
        // info(__FUNCTION__, ['all' => $request->all()]);
        $company_root_id = config('services.application.systemid');
        $http = new \GuzzleHttp\Client;
        $code = isset($request->code) ? $request->code : '';
        $grant_type = 'authorization_code';
        $user_type = 'Company';

        $data_state = isset($request->state) ? json_decode(base64_decode($request->state), true) : [];
        $company_id = isset($data_state['company_id']) ? $data_state['company_id'] : '';
        $subdomain_url = isset($data_state['subdomain_url']) ? $data_state['subdomain_url'] : '';
        $in_app_public = isset($data_state['in_app_public']) ? $data_state['in_app_public'] : '';
        $is_in_iframe = isset($data_state['is_in_iframe']) ? $data_state['is_in_iframe'] : false;

        $custom_menu_name = '';
        if (isset($data_state['custom_menu_name']) && !empty($data_state['custom_menu_name']) && trim($data_state['custom_menu_name']) !== '')
            $custom_menu_name = $data_state['custom_menu_name'];
        else if (isset($request->custom_menu_name) && !empty($request->custom_menu_name) && trim($request->custom_menu_name) !== '')
            $custom_menu_name = $request->custom_menu_name;

        $user_id_login = isset($data_state['user_id_login']) ? $data_state['user_id_login'] : '';
        $user_ip = isset($data_state['user_ip']) ? $data_state['user_ip'] : '';
        // info(__FUNCTION__, ['data_state' => $data_state]);

        /* GET SUBDOMAIN OR DOMAIN AGENCY */
        $salt = substr(hash('sha256', env('APP_KEY')), 0, 16);
        $company = Company::select(
                            'companies.*',
                            'users.id as user_id')
                            ->selectRaw("CONVERT(AES_DECRYPT(FROM_BASE64(users.name), ?) USING utf8mb4) as agency_name", [$salt])
                            ->selectRaw("CONVERT(AES_DECRYPT(FROM_BASE64(users.email), ?) USING utf8mb4) as agency_email", [$salt])
                            ->join('users', 'users.company_id', '=', 'companies.id')
                            ->where('companies.id', $company_id)
                            ->where('users.user_type', 'userdownline')
                            ->first();
        if(empty($company))
        {
            $message = "agency not found";
            $this->gohighlevelv2SendEmailErrorLog([
                'user_type' => 'agency',
                'company_id' => $company_id,
                'function' => __FUNCTION__,
                'message' => "#1 $message"
            ]);
            return response()->json(['result' => 'error', 'message' => 'agency not found'], 404);
        }
        
        $user_id = isset($company->user_id) ? $company->user_id : null;
        $agency_name = isset($company->agency_name) ? $company->agency_name : '';
        $agency_email = isset($company->agency_email) ? $company->agency_email : '';
        $company_name = isset($company->company_name) ? $company->company_name : '';
        /* GET SUBDOMAIN OR DOMAIN AGENCY */

        /* GET CREDENTIAL EMM PRIVATE */
        $credentials_ghl_emm_private = GlobalSettings::where('company_id', $company_root_id)->where('setting_name', 'credentials_ghl_emm_private')->first();
        if(empty($credentials_ghl_emm_private))
        {
            $message = "credential ghl emm private not found";
            $this->gohighlevelv2SendEmailErrorLog([
                'user_type' => 'agency',
                'company_id' => $company_id,
                'company_name' => $company_name,
                'personal_name' => $agency_name,
                'personal_email' => $agency_email,
                'function' => __FUNCTION__,
                'message' => "#2 $message"
            ]);
            return response()->json(['result' => 'error', 'message' => $message], 404);
        }

        $ghl_credentials_id = $credentials_ghl_emm_private->id;
        $credentials = json_decode($credentials_ghl_emm_private->setting_value, true);
        $client_id = $credentials['client_id'] ?? '';
        $client_secret = $credentials['client_secret'] ?? '';
        /* GET CREDENTIAL EMM PRIVATE */

        try
        {
            /* GET ACCESS TOKEN */
            Log::info(__FUNCTION__ . " GET ACCESS TOKEN");
            $url = "https://services.leadconnectorhq.com/oauth/token";
            $form_params = [
                'client_id' => $client_id,
                'client_secret' => $client_secret,
                'grant_type' => $grant_type,
                'code' => $code,
                'user_type' => $user_type,
            ];
            $headers = [
                'Accept' => 'application/json',
                'Content-Type' => 'application/x-www-form-urlencoded',
            ];
            $parameter = [
                'form_params' => $form_params,
                'headers' => $headers
            ];
            $response = $http->post($url, $parameter);
            $response = json_decode($response->getBody(), true);

            $access_token = $response['access_token'] ?? "";
            $ghl_company_id = $response['companyId'] ?? "";
            $ghl_user_type = $response['userType'] ?? "";
            // info('response1', ['response' => $response]);

            if(strtolower(trim($ghl_user_type)) != 'company')
            {
                $message = "user type gohighlevel must be sub acccount";
                $this->gohighlevelv2SendEmailErrorLog([
                    'user_type' => 'agency',
                    'company_id' => $company_id,
                    'company_name' => $company_name,
                    'personal_name' => $agency_name,
                    'personal_email' => $agency_email,
                    'function' => __FUNCTION__,
                    'message' => "#3 $message"
                ]);
                return response()->json(['user_type' => 'agency', 'status' => 'error', 'error_type' => 'permission_denied',  'error' => $message], 400);
            }
            /* GET ACCESS TOKEN */

            /* OVERRIDE CUSTOM MENU NAME IF EMPTY */
            if(empty($custom_menu_name) || trim($custom_menu_name) == '') 
                $custom_menu_name = "$company_name";
            /* OVERRIDE CUSTOM MENU NAME IF EMPTY */

            /* LOGIN SSO */
            Log::info(__FUNCTION__ . " LOGIN SSO");
            $openApiToken = (object) ['company_id' => $company_root_id]; // company id agency
            $dataRequest = new Request(['user_id' => $user_id]);
            $dataRequest->attributes->set('openApiToken', $openApiToken);
            $dataRequest->attributes->set('inGoHighLevel', true);
            
            $openApiController = app(OpenApiController::class);
            $response2 = $openApiController->ssoLogin('agency', $dataRequest)->getData();
            $urlCustomMenuLink = isset($response2->url) ? $response2->url : '';
            // info('', ['urlCustomMenuLink' => $urlCustomMenuLink]);
            // info('', ['response2' => $response2]);
            /* LOGIN SSO */

            /* PROCESS CREATE CUSTOM MENU LINK */
            Log::info(__FUNCTION__ . " PROCESS CREATE CUSTOM MENU LINK");
            $url3 = 'https://services.leadconnectorhq.com/custom-menus/';
            $headers3 = [
                'Accept' => 'application/json',
                'Authorization' => "Bearer $access_token",
                'Content-Type' => 'application/json',
                'Version' => '2021-07-28'
            ];
            $json3 = [
                'title' => $custom_menu_name,
                'url' => $urlCustomMenuLink,
                'icon' => [
                    'name' => 'envelope-open-text',
                    'fontFamily' => 'fas'
                ],
                'showOnCompany' => true,
                'showOnLocation' => false,
                'showToAllLocations' => false,
                'openMode' => 'iframe',
                'userRole' => 'all',
                'allowCamera' => false,
                'allowMicrophone' => false
            ];
            $parameter3 = [
                'headers' => $headers3,
                'json' => $json3,
            ];
            // info('', ['parameter3' => $parameter3]);
            $response3 = $http->post($url3, $parameter3);
            $response3 = json_decode($response3->getBody(), true);
            $custom_menu_link_id = $response3['id'] ?? "";
            // info('', ['response3' => $response3]);
            /* PROCESS CREATE CUSTOM MENU LINK */

            /* OVERIDDE SUBDOMAIN WHEN FROM IN APP PUBLIC */
            if($in_app_public === true && !empty($subdomain_url) && trim($subdomain_url) != '')
            {
                $subdomain_url = "https://$subdomain_url/custom-menu-link/$custom_menu_link_id";
            }
            /* OVERIDDE SUBDOMAIN WHEN FROM IN APP PUBLIC */

            /* UPDATE COMPANIES */
            $company->ghl_company_id = $ghl_company_id;
            $company->ghl_tokens = json_encode($response, JSON_UNESCAPED_SLASHES);
            $company->ghl_custom_menus = json_encode($response3, JSON_UNESCAPED_SLASHES);
            $company->ghl_credentials_id = $ghl_credentials_id;
            $company->ghl_first_time_connect = 1;
            $company->save();
            /* UPDATE COMPANIES */

            /* USER LOGS */
            $description = "The agency has successfully connected their GoHighLevel account. Name: $agency_name | Email: $agency_email | Company Name: $company_name | Company ID : $company_id | Menu Name: $custom_menu_name";
            $this->logUserAction($user_id_login,'Agency Connect Gohighlevel',$description,$user_ip,$user_id);
            /* USER LOGS */

            return response()->json([
                'user_type' => 'agency',
                'status' => 'success',
                'subdomain_url' => $subdomain_url,
                'is_in_iframe' => $is_in_iframe,
            ]);
        }
        catch (\GuzzleHttp\Exception\ClientException $e)
        {
            $body = json_decode((string) $e->getResponse()->getBody());
            $message = $body->error_description ?? "Something Went Wrong";
            info(__FUNCTION__, ['error1' => $message]);
            $this->gohighlevelv2SendEmailErrorLog([
                'user_type' => 'agency',
                'company_id' => $company_id,
                'company_name' => $company_name,
                'personal_name' => $agency_name,
                'personal_email' => $agency_email,
                'function' => __FUNCTION__,
                'message' => "#4 $message"
            ]);
            return response()->json(['user_type' => 'agency', 'status' => 'error', 'error' => $message], 400);
        }
        catch (\Exception $e)
        {
            info(__FUNCTION__, ['error2' => $e->getMessage()]);
            $message = $e->getMessage();
            $this->gohighlevelv2SendEmailErrorLog([
                'user_type' => 'agency',
                'company_id' => $company_id,
                'company_name' => $company_name,
                'personal_name' => $agency_name,
                'personal_email' => $agency_email,
                'function' => __FUNCTION__,
                'message' => "#5 $message"
            ]);
            return response()->json(['user_type' => 'agency', 'status' => 'error', 'error' => $message], 400);
        }
    }
    // redirect url

    public function gohighlevelv2CheckApiKeyIsValid($company_id = "", $access_token = "", $refresh_token = "", $location_id = "", $custom_fields = [])
    {
        $email2Id = "";
        $phone2Id = "";
        $address2Id = "";
        $keywordId = "";
        //$urlId = "";
        $contactId = "";
        $clickDateId = "";
        $global_setting = GlobalSettings::where('setting_name','ghl_custom_fields_create')->first();
        $ghl_custom_fields_create = [];
        if (!empty($global_setting) && isset($global_setting->setting_value)) 
        {
            $ghl_custom_fields_create = json_decode($global_setting->setting_value);
        }

        $dynamicVariables = [];
        if (!empty($ghl_custom_fields_create)) 
        {
            foreach ($ghl_custom_fields_create as $item) 
            {
                $dynamicVariables[$item->id] = '';
            }
        }

        /** GET IF CUSTOM FIELD ALREADY EXIST */
        // info('gohighlevelv2CheckApiKeyIsValid block 1.0');
        $comset_name = 'gohlcustomfields';
        $customfields = CompanySetting::where('company_id',$company_id)->whereEncrypted('setting_name',$comset_name)->get();
        if (count($customfields) > 0) 
        {
            $_customfields = json_decode($customfields[0]['setting_value']);
            $email2Id = (isset($_customfields->email2Id))?$_customfields->email2Id:'';
            $phone2Id = (isset($_customfields->phone2Id))?$_customfields->phone2Id:'';
            $address2Id = (isset($_customfields->address2Id))?$_customfields->address2Id:'';
            $keywordId = (isset($_customfields->keywordId))?$_customfields->keywordId:'';
            //$urlId = (isset($_customfields->urlId))?$_customfields->urlId:'';
            $contactId = (isset($_customfields->contactId))?$_customfields->contactId:'';
            $clickDateId = (isset($_customfields->clickDateId))?$_customfields->clickDateId:'';

            if (!empty($ghl_custom_fields_create)) 
            {
                foreach ($ghl_custom_fields_create as $item) 
                {
                    $dynamicVariables[$item->id] = (isset($_customfields->{$item->id})) ? $_customfields->{$item->id} : '';
                }
            }
        }
        /** GET IF CUSTOM FIELD ALREADY EXIST */

        // GENERAL FIELDS
        // info('gohighlevelv2CheckApiKeyIsValid block 1.1');
        /** CREATE CUSTOM FIELD Alternative Email */
        $createField = $this->gohighlevelv2CreateCustomField($company_id, $access_token, $refresh_token, $location_id, 'Alternative Email', 'Alternative Email', 'TEXT');
        if (trim($createField) != '') 
        {
            $email2Id = trim($createField);
        }
        /** CREATE CUSTOM FIELD Alternative Email */

        /** CREATE CUSTOM FIELD Alternative Phone */
        $createField = $this->gohighlevelv2CreateCustomField($company_id, $access_token, $refresh_token, $location_id, 'Alternative Phone', 'Alternative Phone', 'TEXT');
        if (trim($createField) != '') 
        {
            $phone2Id = trim($createField);
        }
        /** CREATE CUSTOM FIELD Alternative Phone */

        /** CREATE CUSTOM FIELD Street Address 2 */
        $createField = $this->gohighlevelv2CreateCustomField($company_id, $access_token, $refresh_token, $location_id, 'Street Address 2', 'Street Address 2', 'TEXT');
        if (trim($createField) != '') 
        {
            $address2Id = trim($createField);
        }
        /** CREATE CUSTOM FIELD Street Address 2 */

        /** CREATE CUSTOM FIELD ID */
        $createField = $this->gohighlevelv2CreateCustomField($company_id, $access_token, $refresh_token, $location_id, 'Contact ID', 'Contact ID', 'TEXT');
        if (trim($createField) != '') 
        {
            $contactId = trim($createField);
        }
        /** CREATE CUSTOM FIELD ID */

        /** CREATE CUSTOM FIELD CLICKDATE */
        $createField = $this->gohighlevelv2CreateCustomField($company_id, $access_token, $refresh_token, $location_id, 'Click Date', 'Click Date', 'TEXT');
        if (trim($createField) != '') 
        {
            $clickDateId = trim($createField);
        }
        /** CREATE CUSTOM FIELD CLICKDATE */

        /** CREATE CUSTOM FIELD Keyword */
        $createField = $this->gohighlevelv2CreateCustomField($company_id, $access_token, $refresh_token, $location_id, 'Keywords', 'Keywords', 'TEXT');
        if (trim($createField) != '') 
        {
            $keywordId = trim($createField);
        }
        /** CREATE CUSTOM FIELD Keyword */

        /** CREATE CUSTOM FIELD URL */
        // $createField = $this->ghl_CreateCustomField($api_key,'jDrGGQMiOgQcv1S2qvjb','URL','URL','TEXT',true);
        // if (trim($createField) != '') {
        //     $urlId = trim($createField);
        // }
        /** CREATE CUSTOM FIELD URL */
        // GENERAL FIELDS 

        // CUSTOM FIELDS (ADVANCE, ETC)
        // info('gohighlevelv2CheckApiKeyIsValid block 1.2');
        $comset_val_custom = [];
        if (!empty($ghl_custom_fields_create)) 
        {
            foreach ($ghl_custom_fields_create as $item) 
            {
                if (isset($dynamicVariables[$item->id]) && isset($item->name) && isset($item->placeholder) && isset($item->dataType) && isset($item->showInForms)) // tetep bikin custom field walapun sebelumnya ada
                { 
                    $createField = $this->gohighlevelv2CreateCustomField($company_id, $access_token, $refresh_token, $location_id, $item->name, $item->placeholder, $item->dataType);
                    if (trim($createField) != '') 
                    {
                        $dynamicVariables[$item->id] = trim($createField);
                    }
                }
                $comset_val_custom[$item->id] = $dynamicVariables[$item->id];
            }
        }
        // CUSTOM FIELDS (ADVANCE, ETC)

        // SAVE ID CUSTOM FIELD TO COMPANY_SETTING
        if ($company_id != '') 
        {
            $comset_val = [
                "contactId" => $contactId,
                "clickDateId" => $clickDateId,
                "email2Id" => $email2Id,
                "phone2Id" => $phone2Id,
                "address2Id" => $address2Id,
                "keywordId" => $keywordId,
                //"urlId" => $urlId
            ];

            if (!empty($comset_val_custom)) 
            {
                $comset_val = array_merge($comset_val, $comset_val_custom);
            }
            $companysetting = CompanySetting::where('company_id',$company_id)->whereEncrypted('setting_name',$comset_name)->get();

            if (count($companysetting) > 0) 
            {
                $updatesetting = CompanySetting::find($companysetting[0]['id']);
                $updatesetting->setting_value = json_encode($comset_val);
                $updatesetting->save();
            }
            else
            {
                $createsetting = CompanySetting::create([
                    'company_id' => $company_id,
                    'setting_name' => $comset_name,
                    'setting_value' => json_encode($comset_val),
                ]);
            }

        }
        // SAVE ID CUSTOM FIELD TO COMPANY_SETTING

        /** CREATE DUMMY JOHN DOE DATA */
        // variable define 
        // info('gohighlevelv2CheckApiKeyIsValid block 1.3', [
        //     'custom_fields' => $custom_fields
        // ]);
        $contact_id = in_array('Contact Id', $custom_fields) ? '00000000' : '';
        $clickdate = in_array('Click Date', $custom_fields) ? date('Y-m-d') : '';
        $email_2 = in_array('Email 2', $custom_fields) ? 'johndoe2@example.com' : '';
        $phone_2 = in_array('Phone 2', $custom_fields) ? '567-567-5678' : '';
        $address_2 = in_array('Address 2', $custom_fields) ? 'suite 101' : '';

        $additional_fields = [];
        if (!empty($ghl_custom_fields_create)) 
        {
            foreach ($ghl_custom_fields_create as $item) 
            {
                if (isset($item->id) && isset($item->name)) 
                {   
                    $selected = isset($item->category) ? $item->category : $item->name;          
                    if (in_array($selected, $custom_fields)) 
                    {
                        $additional_fields[] = [
                            'db_id' => $item->id,
                            'value' => $item->name
                        ];
                    }
                }
            }
        }
        // info('gohighlevelv2CheckApiKeyIsValid block 1.4', [
        //     'additional_fields' => $additional_fields
        // ]);

        // variable define 
        $this->gohighlevelv2CreateContact($company_id, $access_token, $refresh_token, $location_id, $contact_id, $clickdate,'John','Doe','johndoe1@example.com',$email_2,'123-123-1234',$phone_2,'John Doe Street',$address_2,'Columbus','OH','43055','keyword',[],'00000000',$additional_fields); // personal
    }
    /* GOHIGHLEVELV2 PROCESS */


    /* ===================== SENDJIM OAUTH ===================== */
    public function sendjimGenerateAuthUrl(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'client_id' => 'required',
            'client_secret' => 'required',
            'company_id' => 'required',
            'subdomain_url' => 'required',
        ]); 

        if($validator->fails()) {
            return response()->json(['result' => 'error', 'message' => $validator->errors()->first()], 400);
        }

        $base_url = config('services.sendjim.base_member_url');
        $authorize_path = config('services.sendjim.authorize_path', '/oauth/authorize');
        $company_id = isset($request->company_id) ? $request->company_id : '';
        $subdomain_url = isset($request->subdomain_url) ? $request->subdomain_url : '';
        $user_id_login = optional(auth()->user())->id;
        $user_ip = isset($request->user_ip) ? $request->user_ip : '';
        $send_test = true;
        $client_id = isset($request->client_id) ? trim((string)$request->client_id) : '';
        $integration = IntegrationSettings::where('company_id', $company_id)
                                            ->where('integration_slug', 'sendjim')
                                            ->first();
        $app_url = env('APP_URL');

        $client_secret_from_db = '';
        if(!empty($integration)) {
            $client_id_int = trim((string)$integration->api_key);
            $client_secret_from_db = trim((string)$integration->password);
            if(!empty($client_id_int)) {
                $client_id = $client_id_int; // override with company client id
            }
        }

        $client_secret = isset($request->client_secret) && !empty(trim((string)$request->client_secret)) 
                        ? trim((string)$request->client_secret) 
                        : $client_secret_from_db;

        if(empty($app_url) || empty($base_url) || empty($client_id) || empty($company_id) || empty($subdomain_url)) {
            $message = empty($client_id) ? 'SendJim Client ID is required. Please save your Client ID first.' : 'missing required parameters';
            return response()->json(['result' => 'error', 'message' => $message], 400);
        }
        if(empty($client_secret)) {
            return response()->json(['result' => 'error', 'message' => 'SendJim Client Secret is required. Please save your Client Secret first.'], 400);
        }

        try {
            $http = new \GuzzleHttp\Client(['http_errors' => false, 'timeout' => 10]);
            $verifyUrl = rtrim($base_url, '/') . '/OAuth/Grant';
            
            $res = $http->post($verifyUrl, [
                'multipart' => [
                    ['name' => 'ClientKey', 'contents' => $client_id],
                    ['name' => 'ClientSecret', 'contents' => $client_secret],
                    ['name' => 'RequestToken', 'contents' => 'test'],
                ]
            ]);

            $body = json_decode($res->getBody()->getContents(), true);
            $errorMsg = strtolower($body['error'] ?? $body['Message'] ?? '');
            if ($res->getStatusCode() === 401 || str_contains($errorMsg, 'client')) {
                return response()->json([
                    'result' => 'error', 
                    'message' => 'Invalid SendJim Client ID or Client Secret.'
                ], 400);
            }
        } catch (\Exception $e) {
            return response()->json(['result' => 'error', 'message' => 'Failed to connect to SendJim API.'], 500);
        }

        $state = [
            'company_id' => $company_id,
            'subdomain_url' => $subdomain_url,
            'user_id_login' => $user_id_login,
            'user_ip' => $user_ip,
            'client_id' => $client_id,
            'send_test' => $send_test,
        ];
        $state = base64_encode(json_encode($state));
        $redirect_uri = "$app_url/sendjim/oauth/callback/view";

        $params = [
            'clientKey' => $client_id,
            'callbackUrl' => $redirect_uri,
            'state' => $state,
        ];
        $auth_url = rtrim($base_url, '/').$authorize_path.'?'.http_build_query($params);
        return response()->json(['result' => 'success', 'url' => $auth_url]);
    }

    public function sendjimOauthCallbackView(Request $request)
    {
        // Accept both 'code' and 'requestToken' from provider
        $code = $request->get('requestToken', $request->get('code', ''));
        $data_state = isset($request->state) ? $request->state : '';
        return view('sendjim-oauth-callback', compact('code','data_state'));
    }
    
    public function sendjimOauthCallbackProcess(Request $request)
    {
        $http = new \GuzzleHttp\Client;
        $token_path = config('services.sendjim.token_path', '/OAuth/Grant');
        $base_url = config('services.sendjim.base_member_url');
        $client_id = '';
        $client_secret = '';

        $code = $request->input('code', '');
        if(empty($code))
            $code = $request->input('requestToken', '');
        $data_state = isset($request->state) ? json_decode(base64_decode($request->state), true) : [];
        $company_id = isset($data_state['company_id']) ? $data_state['company_id'] : '';
        $subdomain_url = isset($data_state['subdomain_url']) ? $data_state['subdomain_url'] : '';
        $user_id_login = isset($data_state['user_id_login']) ? $data_state['user_id_login'] : '';
        $user_ip = isset($data_state['user_ip']) ? $data_state['user_ip'] : '';
        $send_test = isset($data_state['send_test']) ? (bool)$data_state['send_test'] : true;
        $client_id_from_state = $data_state['client_id'] ?? '';

        $integrationCred = IntegrationSettings::where('company_id', $company_id)
                                                ->where('integration_slug', 'sendjim')
                                                ->first();
        if(!empty($integrationCred)) {
            $client_id_int = trim((string)$integrationCred->api_key);
            $client_secret_int = trim((string)$integrationCred->password);
            if(!empty($client_id_int)) { $client_id = $client_id_int; }
            if(!empty($client_secret_int)) { $client_secret = $client_secret_int; }
        }

        if(empty($client_id) && !empty($client_id_from_state)) {
            $client_id = $client_id_from_state;
        }
        if(empty($client_secret)) {
            return response()->json(['status' => 'error', 'error' => 'SendJim Client Secret is required. Please save your Client Secret first.'], 400);
        }

        if(empty($code) || empty($company_id) || empty($subdomain_url) || empty($base_url)) {
            $message = 'missing required parameters';
            return response()->json(['status' => 'error', 'error' => $message], 400);
        }

        try {
            $url = rtrim($base_url, '/').$token_path;
            $multipart = [
                [ 'name' => 'ClientKey', 'contents' => $client_id ],
                [ 'name' => 'ClientSecret', 'contents' => $client_secret ],
                [ 'name' => 'RequestToken', 'contents' => $code ],
            ];
            $headers = [ 'Accept' => 'application/json' ];
            $parameter = [ 'multipart' => $multipart, 'headers' => $headers ];
            $response = $http->post($url, $parameter);
            $response = json_decode($response->getBody(), true);
            $tokens_encode = json_encode($response, JSON_UNESCAPED_SLASHES);

            $data = IntegrationSettings::where('company_id','=',$company_id)
                                        ->where('integration_slug','=','sendjim')
                                        ->first();

                                        if(!empty($data)) {
                $update = IntegrationSettings::find($data->id);
                if(!empty($client_id_from_state) && trim((string)$update->api_key) === '') {
                    $update->api_key = $client_id_from_state; // auto-save client id after connect
                }
                if(!empty($client_secret)) {
                    $update->password = $client_secret; // persist client secret
                }
                $update->version = 2;
                $update->tokens = $tokens_encode;
                $update->save();
            } else {
                IntegrationSettings::create([
                    'company_id' => $company_id,
                    'integration_slug' => 'sendjim',
                    'api_key' => $client_id_from_state,
                    'password' => $client_secret,
                    'enable_sendgrid' => 0,
                    'enable_default_campaign' => 0,
                    'subdomain' => '',
                    'workspace_id' => '',
                    'custom_fields' => null,
                    'version' => 2,
                    'tokens' => $tokens_encode,
                ]);
            }

            // Send test data if requested
            if ($send_test === true) {
                try {
                    $tokens_arr = json_decode($tokens_encode, true);
                    $access_token = $tokens_arr['GrantToken'] ?? ($tokens_arr['GrantToken'] ?? '');
                    if(!empty($access_token)) {
                        $now = date('YmdHis');
                        $contact = [
                            "StreetAddress" => "John Doe Street",
                            "City" => "Columbus",
                            "State" => "OH",
                            "PostalCode" => "43055",
                            "FirstName" => "John",
                            "LastName" => "Doe",
                            "Tags" => ["Integration Test"],
                            "Email" => "johndoe1-{$now}-{$company_id}@example.com",
                            "PhoneNumber" => "5551234567"
                        ];
                        $createResp = $this->sendJimService->createContacts([$contact], $access_token);

                        $selectedTemplateIds = [];
                        $quicksendEnabled = false;
                        $settings = IntegrationSettings::where('company_id', $company_id)
                            ->where('integration_slug', 'sendjim')->first();
                        if ($settings && !empty($settings->custom_fields)) {
                            $cfg = is_string($settings->custom_fields) ? json_decode($settings->custom_fields, true) : $settings->custom_fields;
                            if (is_array($cfg)) {
                                $quicksendEnabled = (bool)($cfg['quicksend_is_active'] ?? false);
                                $selectedTemplateIds = is_array($cfg['quicksend_templates'] ?? null) ? $cfg['quicksend_templates'] : [];
                            }
                        }

                    }
                } catch (\Throwable $th) {
                    Log::warning('sendjim send_test failed', ['error' => $th->getMessage()]);
                }
            }

            $description = "The client has successfully connected their SendJim account. Company ID : $company_id";
            $this->logUserAction($user_id_login,'Client Connect SendJim',$description,$user_ip,$user_id_login);

            return response()->json([
                'status' => 'success',
                'subdomain_url' => $subdomain_url
            ]);
        } catch (\GuzzleHttp\Exception\ClientException $e) {
            $body = json_decode((string) $e->getResponse()->getBody());
            $message = $body->error_description ?? "Something Went Wrong";
            return response()->json(['status' => 'error', 'error' => $message], 400);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'error' => $e->getMessage()], 400);
        }
    }
    
    public function sendjimDisconnect(Request $request)
    {
        $company_id = isset($request->company_id) ? $request->company_id : '';
        $integration = IntegrationSettings::where('company_id', $company_id)
                                            ->where('integration_slug', 'sendjim')
                                            ->first();
        if(empty($integration))
            return response()->json(['status' => 'error', 'message' => 'Integration SendJim Not Found']);

        if($integration->api_key != '') {
            $integration->version = null;
            $integration->tokens = null;
            $integration->save();
        } else {
            $integration->delete();
        }

        $user_id_login = optional(auth()->user())->id;
        $user_ip = isset($request->user_ip) ? $request->user_ip : '';
        $description = "The client has successfully disconnect their SendJim account. Company ID : $company_id";
        $this->logUserAction($user_id_login,'Client Disconnect SendJim',$description,$user_ip,$user_id_login);

        return response()->json(['status' => 'success', 'message' => 'Disconnect SendJim Successfully']);
    }
    /* ===================== SENDJIM OAUTH ===================== */

    public function checkAPIKeyIsValid($api_key,$connectType = 'sendgrid',$companyID = '', $custom_fields = [], $version = 1){
        $http = new \GuzzleHttp\Client;

        if (trim($connectType) == 'sendgrid') {
            $apiEndpoint = "https://api.sendgrid.com/v3/marketing/field_definitions";

            $dataOptions = [
                'headers' => [
                    'Authorization' => 'Bearer ' . $api_key,
                    //'Content-Type' => 'application/json'
                ],
                'json' => [

                ]
            ];

            $getcontact = $http->get($apiEndpoint,$dataOptions);
            $result[] =  json_decode($getcontact->getBody(), TRUE);

            foreach($result as $val[])
            {
                $custom_field_count = count($val[0]);
            }
            if($custom_field_count == 2)
                {
                    $apiEndpoint1 = "https://api.sendgrid.com/v3/marketing/field_definitions";
                    $dataOptions1 = [
                        'headers' => [
                            'Authorization' => 'Bearer ' . $api_key,
                            'Content-Type' => 'application/json'
                        ],
                        'json' => [
                            'name' => 'keyword',
                            'field_type' => 'Text'

                        ]

                    ];

                    $dataOptions2 = [
                        'headers' => [
                            'Authorization' => 'Bearer ' . $api_key,
                            'Content-Type' => 'application/json'
                        ],
                        'json' => [
                            'name' => 'url',
                            'field_type' => 'Text'
                        ]

                    ];
                    $getcontact1 = $http->post($apiEndpoint1,$dataOptions1);
                    $getcontact2 = $http->post($apiEndpoint1,$dataOptions2);

                }

        } else if (trim($connectType) == 'gohighlevel') {
            $result = [];
            
            // info('checkAPIKeyIsValid gohighlevel 1.0', ['version' => $version]);
            if($version == 1) {
                /** CHECK IF API CAN BE WORKED FIRST */
                $apiEndpoint =  "https://rest.gohighlevel.com/v1/contacts/?limit=1";
                $dataOptions = [
                    'headers' => [
                        'Authorization' => 'Bearer ' . $api_key,
                        'Content-Type' => 'application/json'
                    ],
                    'json' => [
    
                    ]
                ];
    
                $getcontact = $http->get($apiEndpoint,$dataOptions);
                $result[] =  json_decode($getcontact->getBody(), TRUE);
                /** CHECK IF API CAN BE WORKED FIRST */
            }

            /** START CREATE CUSTOM FIELD */
            if (count($result) > 0 || $version == 2) {
                $email2Id = "";
                $phone2Id = "";
                $address2Id = "";
                $keywordId = "";
                //$urlId = "";
                $contactId = "";
                $clickDateId = "";
                $global_setting = GlobalSettings::where('setting_name','ghl_custom_fields_create')->first();
                $ghl_custom_fields_create = [];
                if (!empty($global_setting) && isset($global_setting->setting_value)) {
                    // info('checkAPIKeyIsValid gohighlevel 1.1');
                    $ghl_custom_fields_create = json_decode($global_setting->setting_value);
                }

                $dynamicVariables = [];
                if (!empty($ghl_custom_fields_create)) {
                    // info('checkAPIKeyIsValid gohighlevel 1.2', ['count' => count($ghl_custom_fields_create)]);
                    foreach ($ghl_custom_fields_create as $item) {
                        $dynamicVariables[$item->id] = '';
                    }
                    
                }

                $comset_name = 'gohlcustomfields';
                /** GET IF CUSTOM FIELD ALREADY EXIST */
                $customfields = CompanySetting::where('company_id',$companyID)->whereEncrypted('setting_name',$comset_name)->get();
                if (count($customfields) > 0) {
                    // info('checkAPIKeyIsValid gohighlevel 1.3');
                    $_customfields = json_decode($customfields[0]['setting_value']);
                    $email2Id = (isset($_customfields->email2Id))?$_customfields->email2Id:'';
                    $phone2Id = (isset($_customfields->phone2Id))?$_customfields->phone2Id:'';
                    $address2Id = (isset($_customfields->address2Id))?$_customfields->address2Id:'';
                    $keywordId = (isset($_customfields->keywordId))?$_customfields->keywordId:'';
                    //$urlId = (isset($_customfields->urlId))?$_customfields->urlId:'';
                    $contactId = (isset($_customfields->contactId))?$_customfields->contactId:'';
                    $clickDateId = (isset($_customfields->clickDateId))?$_customfields->clickDateId:'';

                    if (!empty($ghl_custom_fields_create)) {
                        // info('checkAPIKeyIsValid gohighlevel 1.4', ['count' => count($ghl_custom_fields_create)]);
                        foreach ($ghl_custom_fields_create as $item) {
                            $dynamicVariables[$item->id] = (isset($_customfields->{$item->id})) ? $_customfields->{$item->id} : '';
                        }
                    }
                }
                /** GET IF CUSTOM FIELD ALREADY EXIST */

                // GENERAL FIELDS 
                /** CREATE CUSTOM FIELD Alternative Email */
                if ($email2Id == '') {
                // info("CREATE CUSTOM FIELD Alternative Email");
                $createField = $this->ghl_CreateCustomField($api_key,'','Alternative Email','Alternative Email','TEXT',true);
                if (trim($createField) != '') {
                    $email2Id = trim($createField);
                }
                }
                /** CREATE CUSTOM FIELD Alternative Email */

                /** CREATE CUSTOM FIELD Alternative Phone */
                if ($phone2Id == '') {
                // info("CREATE CUSTOM FIELD Alternative Phone");
                $createField = $this->ghl_CreateCustomField($api_key,'','Alternative Phone','Alternative Phone','TEXT',true);
                if (trim($createField) != '') {
                    $phone2Id = trim($createField);
                }
                }
                /** CREATE CUSTOM FIELD Alternative Phone */

                /** CREATE CUSTOM FIELD Street Address 2 */
                if ($address2Id == '') {
                // info("CREATE CUSTOM FIELD Alternative Address 2");
                $createField = $this->ghl_CreateCustomField($api_key,'','Street Address 2','Street Address 2','TEXT',true);
                if (trim($createField) != '') {
                    $address2Id = trim($createField);
                }
                }
                /** CREATE CUSTOM FIELD Street Address 2 */

                /** CREATE CUSTOM FIELD ID */
                if ($contactId == '') {
                // info("CREATE CUSTOM FIELD Alternative ID");
                $createField = $this->ghl_CreateCustomField($api_key,'','Contact ID','Contact ID','TEXT',true);
                if (trim($createField) != '') {
                    $contactId = trim($createField);
                }
                }
                /** CREATE CUSTOM FIELD ID */

                /** CREATE CUSTOM FIELD CLICKDATE */
                if ($clickDateId == '') {
                // info("CREATE CUSTOM FIELD CLICKDATE");
                $createField = $this->ghl_CreateCustomField($api_key,'','Click Date','Click Date','DATE',true);
                if (trim($createField) != '') {
                    $clickDateId = trim($createField);
                }
                }
                /** CREATE CUSTOM FIELD CLICKDATE */

                /** CREATE CUSTOM FIELD Keyword */
                if ($keywordId == '') {
                // info("CREATE CUSTOM FIELD Keyword");
                $createField = $this->ghl_CreateCustomField($api_key,'','Keywords','Keywords','TEXT',true);
                if (trim($createField) != '') {
                    $keywordId = trim($createField);
                }
                }
                /** CREATE CUSTOM FIELD Keyword */

                /** CREATE CUSTOM FIELD URL */
                // $createField = $this->ghl_CreateCustomField($api_key,'jDrGGQMiOgQcv1S2qvjb','URL','URL','TEXT',true);
                // if (trim($createField) != '') {
                //     $urlId = trim($createField);
                // }
                /** CREATE CUSTOM FIELD URL */
                // CUSTOM FIELDS (ADVANCE, ETC)
                $comset_val_custom = [];
                if (!empty($ghl_custom_fields_create)) {
                    // info('checkAPIKeyIsValid gohighlevel 1.5', ['count' => count($ghl_custom_fields_create)]);
                    foreach ($ghl_custom_fields_create as $item) {
                        if (isset($dynamicVariables[$item->id]) && $dynamicVariables[$item->id] == '' && isset($item->name) && isset($item->placeholder) && isset($item->dataType) && isset($item->showInForms)) { 
                            // info("CREATE CUSTOM FIELD ADVANCE,ETC");
                            $createField = $this->ghl_CreateCustomField($api_key, '', $item->name, $item->placeholder, $item->dataType, $item->showInForms);
                            if (trim($createField) != '') {
                                $dynamicVariables[$item->id] = trim($createField);
                            }
                        }
                        $comset_val_custom[$item->id] = $dynamicVariables[$item->id];
                    }
                }
                // CUSTOM FIELDS (ADVANCE, ETC)

                if ($companyID != '') {
                    // info('checkAPIKeyIsValid gohighlevel 1.6', ['count' => count($ghl_custom_fields_create)]);
                    $comset_val = [
                        "contactId" => $contactId,
                        "clickDateId" => $clickDateId,
                        "email2Id" => $email2Id,
                        "phone2Id" => $phone2Id,
                        "address2Id" => $address2Id,
                        "keywordId" => $keywordId,
                        //"urlId" => $urlId
                    ];

                    if (!empty($comset_val_custom)) {
                        $comset_val = array_merge($comset_val, $comset_val_custom);
                    }
                    $companysetting = CompanySetting::where('company_id',$companyID)->whereEncrypted('setting_name',$comset_name)->get();

                    if (count($companysetting) > 0) {
                        // info("checkAPIKeyIsValid gohighlevel 1.7 $comset_name");
                        $updatesetting = CompanySetting::find($companysetting[0]['id']);
                        $updatesetting->setting_value = json_encode($comset_val);
                        $updatesetting->save();
                    }else{
                        // info("checkAPIKeyIsValid gohighlevel 1.8 $comset_name");
                        $createsetting = CompanySetting::create([
                            'company_id' => $companyID,
                            'setting_name' => $comset_name,
                            'setting_value' => json_encode($comset_val),
                        ]);
                    }

                }

                if($version == 1) {
                    // info("checkAPIKeyIsValid gohighlevel 1.9");
                    /** CREATE DUMMY JOHN DOE DATA */
                    // variable define 
                    $contact_id = in_array('Contact Id', $custom_fields) ? '00000000' : '';
                    $clickdate = in_array('Click Date', $custom_fields) ? date('Y-m-d') : '';
                    $email_2 = in_array('Email 2', $custom_fields) ? 'johndoe2@example.com' : '';
                    $phone_2 = in_array('Phone 2', $custom_fields) ? '567-567-5678' : '';
                    $address_2 = in_array('Address 2', $custom_fields) ? 'suite 101' : '';
    
                    $additional_fields = [];
                    if (!empty($ghl_custom_fields_create)) {
                        foreach ($ghl_custom_fields_create as $item) {
                            if (isset($item->id) && isset($item->name)) {   
                                $selected = isset($item->category) ? $item->category : $item->name;          
                                if (in_array($selected, $custom_fields)) {
                                    $additional_fields[] = [
                                        'db_id' => $item->id,
                                        'value' => $item->name
                                    ];
                                }
                            }
                        }
                    }
    
                    // variable define 
                    $this->ghl_createContact($companyID,$api_key,$contact_id,$clickdate,'John','Doe','johndoe1@example.com',$email_2,'123-123-1234',$phone_2,'John Doe Street',$address_2,'Columbus','OH','43055','keyword',array(),"00000000",$additional_fields);
                    /** CREATE DUMMY JOHN DOE DATA */
                }
            }
            /** START CREATE CUSTOM FIELD */

        } else if (trim($connectType == 'zapier')) {
            // SEND DUMMY RECORD
            try {
            $this->zap_sendrecord($api_key,date('Y-m-d H:is'),'John','Doe','johndoe1@example.com','johndoe2@example.com','123-123-1234','567-567-5678','John Doe Street','suite 101','Columbus','OH','43055','keyword','https://yourwebsite.com',array(),"00000000", '');
            } catch (\Exception $e) {
                throw new \Exception("[ZAPIER_TEST] " . $e->getMessage(), 401);
            }
            // SEND DUMMY RECORD
        } else if(trim($connectType) == 'mailboxpower') {
            $apiEndpoint = "https://www.mailboxpower.com/api/v3/groups";
            $dataOptions = [
                'headers' => [
                    'APIKEY' => $api_key,
                ],
            ];
            $getcontact = $http->get($apiEndpoint,$dataOptions);
            // $result =  json_decode($getcontact->getBody(), TRUE);
            // Log::info('', ['result' => $result]);
        } else if (trim($connectType == 'agencyzoom')) {
            // SEND DUMMY RECORD
            try {
                $this->agencyzoom_sendrecord($api_key,'John','Doe','Jhon Doe Business','johndoe1@example.com','johndoe2@example.com','123-123-1234','567-567-5678','John Doe Street 1','John Doe Street 2','Chicago','IL','60666','keyword');
            } catch (\Exception $e) {
                throw new \Exception("[AGENCY_ZOOM_TEST] " . $e->getMessage(), 401);
            }
            // SEND DUMMY RECORD
        }
    }

    public function checkApiKeyIsValidClickFunnels($api_key = '', $subdomain = '', $workspace_id = '', $custom_fields = []){
        $dateFormat = date('Y-m-d-H:i:s');

        $custom_attributes = [
            'id_campaign' => '',
            'id_contact' => '',
            'click_date' => '',
            'email_2' => "",
            'phone_2' => '',
            'country' => '',
            'state' => '',
            'city' => '',
            'postal_code' => '',
            'address_1' => '',
            'address_2' => '',
            'keyword' => ''
        ];

        if(in_array('Id Campaign', $custom_fields)){
            $custom_attributes['id_campaign'] = '123456';
        }
        if(in_array('Id Contact', $custom_fields)){
            $custom_attributes['id_contact'] = '123456';
        }
        if(in_array('Click Date', $custom_fields)){
            $custom_attributes['click_date'] = date('Y-m-d H:i:s');
        }
        if(in_array('Email 2', $custom_fields)){
            $custom_attributes['email_2'] = "johndoe2-{$dateFormat}@example.com";
        }
        if(in_array('Phone 2', $custom_fields)){
            $custom_attributes['phone_2'] = "567-567-5678";
        }
        if(in_array('Country', $custom_fields)){
            $custom_attributes['country'] = "US";
        }
        if(in_array('State', $custom_fields)){
            $custom_attributes['state'] = "Columbus";
        }
        if(in_array('City', $custom_fields)){
            $custom_attributes['city'] = "OH";
        }
        if(in_array('Postal Code', $custom_fields)){
            $custom_attributes['postal_code'] = "43055";
        }
        if(in_array('Address 1', $custom_fields)){
            $custom_attributes['address_1'] = "John Doe Street";
        }
        if(in_array('Address 2', $custom_fields)){
            $custom_attributes['address_2'] = "John Doe Street 2";
        }
        if(in_array('Keyword', $custom_fields)){
            $custom_attributes['keyword'] = "Testing - Integration";
        }
        if(in_array('Employee ID', $custom_fields)){
            $custom_attributes['id_employee'] = "12526368";
        }
        if(in_array('Company Name', $custom_fields)){
            $custom_attributes['company_name'] = "AI COMPANY";
        }
        if(in_array('Company Website', $custom_fields)){
            $custom_attributes['company_website'] = "aicompany.com";
        }
        if(in_array('Number of Employees', $custom_fields)){
            $custom_attributes['number_of_employees'] = "1 TO 49";
        }
        if(in_array('Sales Volume', $custom_fields)){
            $custom_attributes['sales_volume'] = "LESS THAN $500.000";
        }
        if(in_array('Year Founded', $custom_fields)){
            $custom_attributes['year_founded'] = "1999";
        }
        if(in_array('Job Title', $custom_fields)){
            $custom_attributes['job_title'] = "SALES STAFF";
        }
        if(in_array('Level', $custom_fields)){
            $custom_attributes['level'] = "STAFF";
        }
        if(in_array('Job function', $custom_fields)){
            $custom_attributes['job_function'] = "SALES";
        }
        if(in_array('Headquartes Branch', $custom_fields)){
            $custom_attributes['headquartes_branch'] = "0";
        }
        if(in_array('NAICS Code', $custom_fields)){
            $custom_attributes['naics_code'] = "441310";
        }
        if(in_array('Last Seen Date', $custom_fields)){
            $custom_attributes['last_seen_date'] = "01/24/2024";
        }
        if(in_array('Linked In', $custom_fields)){
            $custom_attributes['linked_in'] = "linkedin.com/in/jhon-doe";
        }

        $data = [
            'email_address' => "johndoe1-{$dateFormat}@example.com",
            'first_name' => 'John',
            'last_name' => 'Doe',
            'phone_number' => '123-123-1234',
            'custom_attributes' => $custom_attributes
        ];

        return $this->clickfunnels_CreateContact($api_key, $subdomain, $workspace_id, $data);
    }

    public function _ghl_CreateCustomField($api_key,$parentId = '8k2SowpRdjWbXSjJfG3S',$name = '', $placeholder='',$dataType = 'TEXT',$showInForms = true){
        $http = new \GuzzleHttp\Client;
        try {
            $apiEndpoint =  "https://rest.gohighlevel.com/v1/custom-fields/";
            $dataOptions = [
                'headers' => [
                    'Authorization' => 'Bearer ' . $api_key,
                    'Content-Type' => 'application/json'
                ],
                'json' => [
                    "parentId" => $parentId,
                    "name" => $name,
                    "dataType" => $dataType,
                    "placeholder" => ($placeholder != '')?$placeholder:$name,
                    "position" => 0,
                    "model" => "contact",
                    "showInForms" => $showInForms
                ]
            ];

            $createfield = $http->post($apiEndpoint,$dataOptions);
            $result =  json_decode($createfield->getBody());
            return $result->customField->id;
        }catch (\GuzzleHttp\Exception\BadResponseException $e) {
            return "";
        }
    }

    protected function kartraCreateCustomField($request)
    {
        $ch = curl_init();
        $api_endpoint = "https://app.kartra.com/api";
        $post_data = [
            'app_id' => config('services.kartra.kartraAppID'),
            'api_key' => $request->api_key,
            'api_password' => $request->password,
            'actions' => [
                [
                    'cmd' => 'create_custom_field',
                    'custom_field_identifier' => 'secondphone',
                    'custom_field_type' => 'input_field',
                ],
                [
                    'cmd' => 'create_custom_field',
                    'custom_field_identifier' => 'secondemail',
                    'custom_field_type' => 'input_field',
                ],
                [
                    'cmd' => 'create_custom_field',
                    'custom_field_identifier' => 'keyword',
                    'custom_field_type' => 'input_field',
                ],
                [
                    'cmd' => 'create_custom_field',
                    'custom_field_identifier' => 'secondaddress',
                    'custom_field_type' => 'input_field',
                ],
            ],
        ];
        $curl_options = [
            CURLOPT_URL => $api_endpoint,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($post_data), // Convert the array to URL-encoded format
            CURLOPT_RETURNTRANSFER => true, // Return the response as a string
        ];
        curl_setopt_array($ch, $curl_options);
        $server_output = curl_exec($ch);
        if (curl_errno($ch)) {
            echo 'cURL error: ' . curl_error($ch);
        } else {
            return json_decode($server_output, true);
        }
        curl_close($ch);
    }

    public function sendTestLeads(Request $request)
    {
        $http = new \GuzzleHttp\Client;

        $type = (isset($request->type))?$request->type:"";
        $date = (isset($request->date))?$request->date:"";
        $campaign_id = (isset($request->campaign_id))?$request->campaign_id:"";
        $company_id = (isset($request->company_id))?$request->company_id:"";
        $format = (isset($request->format))?$request->format:null;
        $webhook_url = (isset($request->webhook_url))?$request->webhook_url:[];
        $webhook_tags = (isset($request->webhook_tags))?$request->webhook_tags:[];
        $tags_go_high_level = (isset($request->tags_go_high_level))?$request->tags_go_high_level:[];
        $sendgrid_list = (isset($request->sendgrid_list))?$request->sendgrid_list:'';
        $group_mail_box_power = (isset($request->group_mail_box_power))?$request->group_mail_box_power:'';
        $new_group_mail_box_power = (isset($request->new_group_mail_box_power))?$request->new_group_mail_box_power:'';
        $kartra_list = (isset($request->kartra_list))?$request->kartra_list:[];
        $kartra_tags = (isset($request->kartra_tags))?$request->kartra_tags:[];
        $clickfunnels_tags = (isset($request->clickfunnels_tags))?$request->clickfunnels_tags:[];
        $status_keyword_to_tags = (isset($request->status_keyword_to_tags))?$request->status_keyword_to_tags:false;
        
        if (empty($type)) {
            return response()->json(['result' => 'failed', 'message' => 'Type is required'], 400);
        }
        
        if (empty($format)) {
            return response()->json(['result' => 'failed', 'message' => 'Format is required'], 400);
        }
        
        if (empty($date)) {
            return response()->json(['result' => 'failed', 'message' => 'Date is required'], 400);
        }
        
        if (empty($campaign_id)) {
            return response()->json(['result' => 'failed', 'message' => 'Campaign ID is required'], 400);
        }

        if (empty($company_id)) {
            return response()->json(['result' => 'failed', 'message' => 'Company ID is required'], 400);
        }

        if($type == 'kartra'){
            $get_leadspeek_type = LeadspeekUser::select('leadspeek_type','advance_information')->where('id',$campaign_id)->first();
        } else {
            $get_leadspeek_type = LeadspeekUser::select('leadspeek_type','advance_information')->where('leadspeek_api_id',$campaign_id)->first();
        }
        $is_advance = false;
        if (($get_leadspeek_type->leadspeek_type == 'enhance' || $get_leadspeek_type->leadspeek_type == 'local') && isset($get_leadspeek_type->advance_information) && !empty($get_leadspeek_type->advance_information) && trim($get_leadspeek_type->advance_information) != '') {
            $is_advance = true;
        }

        $now = Carbon::now()->format('YmdHis');
        $payload = [
            'clickdate' => $date,
            'firstName' => 'John',
            'lastName' => 'Doe',
            'name' => 'John Doe',
            'keyword' => 'Testing - Send Contact',
            'url' => '',
            'email1' => "johndoe1-{$now}-{$campaign_id}@example.com",
            'email2' => "johndoe2-{$now}-{$campaign_id}@example.com",
            'address1' => "",
            'address2' => "",
            'city' => "",
            'state' => "",
            'postalCode' => "",
            'phone1' => "",
            'phone2' => "",
            'campaignID' => $campaign_id,
            'campaignType' => "Module Name",
            'contactID' => "00000000",
            'tags' => [],
        ];

        if($format['home_address_enabled'] == 'T'){
            $payload['address1'] = "John Doe Address 1";
            $payload['address2'] = "John Doe Address 2";
            $payload['city'] = "San Francisco";
            $payload['state'] = "California";
            $payload['postalCode'] = "91234";
        }

        if($format['phone_enabled'] == 'T'){
            // Generate dynamic phone numbers based on timestamp
            $phone_timestamp = substr($now, -10); // Get last 10 digits of timestamp
            $payload['phone1'] = "1" . $phone_timestamp; // Format: 1XXXXXXXXXX (US format)
            $payload['phone2'] = "2" . $phone_timestamp; // Format: 2XXXXXXXXXX (different from phone1)
        }

        if($get_leadspeek_type->leadspeek_type == 'local'){
            $payload['url'] = 'https://yourwebsite.com';
            $payload['keyword'] = '';
        } else {
            $payload['keyword'] = 'Testing - Send Contact';
            $payload['url'] = '';
        }

        $integration_settings = IntegrationSettings::select('api_key', 'enable_sendgrid', 'password', 'subdomain', 'workspace_id', 'custom_fields','tokens')
                                    ->where('company_id', '=', $company_id)
                                    ->where('integration_slug', '=', $type)
                                    ->where('enable_sendgrid', '=', '1')
                                    ->first();
        $api_key = $integration_settings->api_key;

        try {
            if ($type == 'zapier') {
                if(!empty($webhook_tags)){
                    $payload['tags'] = $webhook_tags;
                }

                if ($get_leadspeek_type->leadspeek_type == 'b2b') {
                    $payload['phone1'] = "";
                    $payload['phone2'] = "";
                    $payload['keyword'] = 'Testing - B2B Contact';
                    $payload['url'] = '';
                    
                    $payload = array_merge($payload,[
                        'EmployeeID' => '12526368',
                        'CompanyName' => 'AI COMPANY',
                        'CompanyPhone' => '555-987-6543',
                        'CompanyWebsite' => 'aicompany.com',
                        'NumEmployees' => '1 TO 49',
                        'SalesVolume' => 'LESS THAN $500.000',
                        'YearFounded' => '1999',
                        'JobTitle' => 'SALES STAFF',
                        'Level' => 'STAFF',
                        'JobFunction' => 'SALES',
                        'HeadquartersBranch' => '0',
                        'NaicsCode' => '441310',
                        'LastSeenDate' => '01/24/2024',
                        'LinkedIn' => 'linkedin.com/in/jhon-doe',
                    ]);            
                }
                
                if ($is_advance) {
                    $information_array = [];
                    
                    if($get_leadspeek_type->leadspeek_type == 'local') {
                        $campaignInformation = campaignInformation::select('id', 'type')
                            ->where('status', 'active')
                            ->where('campaign_type','local_adv')
                            ->orderBy('start_index', 'asc')
                            ->get();
                        
                        $categories = [
                            'identification' => ['GenderAux', 'Generation', 'MaritalStatus'],
                            'contactInformation' => ['Phone3', 'TaxBillInformation'],
                            'houseAndRealEstate' => ['DwellingType', 'HomeOwner', 'HomeOwnerOrdinal', 'LengthOfResidence', 'HomePrice', 'HomeValue', 'MedianHomeValue', 'LivingSqft', 'YrBuiltOrig', 'YrBuiltRange', 'LotNumber', 'LegalDescription', 'LandSqft', 'GarageSqft', 'Subdivision', 'ZoningCode'],
                            'financialInformation' => ['IncomeHousehold', 'IncomeMidptsHousehold', 'NetWorthHousehold', 'NetWorthMidptHousehold', 'DiscretionaryIncome', 'CreditMidpts', 'CreditRange'],
                            'householdInformation' => ['NumAdultsHousehold', 'NumChildrenHousehold', 'NumPersonsHousehold', 'ChildAged03Household', 'ChildAged46Household', 'ChildAged79Household', 'ChildAged1012Household', 'ChildAged1318Household', 'ChildrenHousehold'],
                            'interestsAndAffinities' => ['Cooking', 'Gardening', 'Music', 'Diy', 'Books', 'TravelVacation', 'HealthBeautyProducts', 'PetOwner', 'Photography', 'Fitness', 'Epicurean'],
                            'occupation' => ['OccupationCategory', 'OccupationType', 'OccupationDetail'],
                            'marketingIndicators' => ['MagazineSubscriber', 'CharityInterest', 'LikelyCharitableDonor'],
                            'locationAndCensusData' => ['Cbsa', 'CensusBlock', 'CensusBlockGroup', 'CensusTract'],
                            'miscellaneous' => ['Voter', 'Urbanicity'],
                        ];
                        
                        // Sample test values for each field
                        $testValues = [
                            'GenderAux' => 'Male',
                            'Generation' => 'Baby Boomer',
                            'MaritalStatus' => 'Married',
                            'Phone3' => '555-555-5555',
                            'TaxBillInformation' => 'Test Tax Bill Info',
                            'DwellingType' => 'Single Family',
                            'HomeOwner' => '1',
                            'HomeOwnerOrdinal' => '1',
                            'LengthOfResidence' => '5-10 years',
                            'HomePrice' => '$250,000',
                            'HomeValue' => '$275,000',
                            'MedianHomeValue' => '$260,000',
                            'LivingSqft' => '2000',
                            'YrBuiltOrig' => '2010',
                            'YrBuiltRange' => '2005-2015',
                            'LotNumber' => 'LOT123',
                            'LegalDescription' => 'Test Legal Description',
                            'LandSqft' => '5000',
                            'GarageSqft' => '400',
                            'Subdivision' => 'Test Subdivision',
                            'ZoningCode' => 'R-1',
                            'IncomeHousehold' => '$75,000-$99,999',
                            'IncomeMidptsHousehold' => '$87,500',
                            'NetWorthHousehold' => '$250,000-$499,999',
                            'NetWorthMidptHousehold' => '$375,000',
                            'DiscretionaryIncome' => '$25,000',
                            'CreditMidpts' => '720',
                            'CreditRange' => '700-750',
                            'NumAdultsHousehold' => '2',
                            'NumChildrenHousehold' => '2',
                            'NumPersonsHousehold' => '4',
                            'ChildAged03Household' => '0',
                            'ChildAged46Household' => '1',
                            'ChildAged79Household' => '1',
                            'ChildAged1012Household' => '0',
                            'ChildAged1318Household' => '0',
                            'ChildrenHousehold' => '1',
                            'Cooking' => '1',
                            'Gardening' => '1',
                            'Music' => '0',
                            'Diy' => '1',
                            'Books' => '1',
                            'TravelVacation' => '1',
                            'HealthBeautyProducts' => '0',
                            'PetOwner' => '1',
                            'Photography' => '0',
                            'Fitness' => '1',
                            'Epicurean' => '0',
                            'OccupationCategory' => 'Professional',
                            'OccupationType' => 'Management',
                            'OccupationDetail' => 'Business Manager',
                            'MagazineSubscriber' => '1',
                            'CharityInterest' => '1',
                            'LikelyCharitableDonor' => '1',
                            'Cbsa' => '12345',
                            'CensusBlock' => 'Block123',
                            'CensusBlockGroup' => 'Group1',
                            'CensusTract' => 'Tract456',
                            'Voter' => '1',
                            'Urbanicity' => '3. Suburban',
                        ];
                        
                        // Parse advance_information for local type (JSON format: {"advance":[47,48,50,...]})
                        $advance_info = $get_leadspeek_type->advance_information;
                        $decoded = json_decode($advance_info, true);
                        if (json_last_error() === JSON_ERROR_NONE && isset($decoded['advance']) && is_array($decoded['advance'])) {
                            // JSON format: {"advance":[47,48,50,...]}
                            $selected_information = array_map('strval', $decoded['advance']);
                        } else {
                            // Fallback: try comma-separated format if JSON parsing fails
                            $selected_information = array_map('trim', array_map('strval', explode(',', $advance_info)));
                        }
                        // Remove empty values
                        $selected_information = array_filter($selected_information, function($val) {
                            return !empty($val);
                        });
                        if (!empty($campaignInformation)) {
                            foreach ($campaignInformation as $info) {
                                $category = $info->type;
                                if (array_key_exists($category, $categories) && in_array($info->id, $selected_information)) {
                                    foreach ($categories[$category] as $column) {
                                        $value = isset($testValues[$column]) ? $testValues[$column] : 'Test Value';
                                        $camel = lcfirst(str_replace(' ', '', ucwords(str_replace(['-', '_'], ' ', strtolower($column)))));
                                        $information_array[$camel] = $value;
                                    }
                                }
                            }
                        }
                    } else if ($get_leadspeek_type->leadspeek_type == 'enhance') {
                        $campaignInformation = campaignInformation::select('id', 'type', 'description')
                            ->where('status', 'active')
                            ->where('campaign_type','enhance')
                            ->orderBy('start_index', 'asc')
                            ->get();
                        
                        $categories = [
                            'identification' => ['GenderAux', 'Generation', 'MaritalStatus'],
                            'contactInformation' => ['Phone3', 'TaxBillInformation'],
                            'houseAndRealEstate' => ['DwellingType', 'HomeOwner', 'HomeOwnerOrdinal', 'LengthOfResidence', 'HomePrice', 'HomeValue', 'MedianHomeValue', 'LivingSqft', 'YrBuiltOrig', 'YrBuiltRange', 'LotNumber', 'LegalDescription', 'LandSqft', 'GarageSqft', 'Subdivision', 'ZoningCode'],
                            'financialInformation' => ['IncomeHousehold', 'IncomeMidptsHousehold', 'NetWorthHousehold', 'NetWorthMidptHousehold', 'DiscretionaryIncome', 'CreditMidpts', 'CreditRange'],
                            'householdInformation' => ['NumAdultsHousehold', 'NumChildrenHousehold', 'NumPersonsHousehold', 'ChildAged03Household', 'ChildAged46Household', 'ChildAged79Household', 'ChildAged1012Household', 'ChildAged1318Household', 'ChildrenHousehold'],
                            'interestsAndAffinities' => ['Cooking', 'Gardening', 'Music', 'Diy', 'Books', 'TravelVacation', 'HealthBeautyProducts', 'PetOwner', 'Photography', 'Fitness', 'Epicurean'],
                            'occupation' => ['OccupationCategory', 'OccupationType', 'OccupationDetail'],
                            'marketingIndicators' => ['MagazineSubscriber', 'CharityInterest', 'LikelyCharitableDonor'],
                            'locationAndCensusData' => ['Cbsa', 'CensusBlock', 'CensusBlockGroup', 'CensusTract'],
                            'miscellaneous' => ['Voter', 'Urbanicity'],
                        ];
                        
                        // Same test values as local
                        $testValues = [
                            'GenderAux' => 'Male',
                            'Generation' => 'Baby Boomer',
                            'MaritalStatus' => 'Married',
                            'Phone3' => '555-555-5555',
                            'TaxBillInformation' => 'Test Tax Bill Info',
                            'DwellingType' => 'Single Family',
                            'HomeOwner' => '1',
                            'HomeOwnerOrdinal' => '1',
                            'LengthOfResidence' => '5-10 years',
                            'HomePrice' => '$250,000',
                            'HomeValue' => '$275,000',
                            'MedianHomeValue' => '$260,000',
                            'LivingSqft' => '2000',
                            'YrBuiltOrig' => '2010',
                            'YrBuiltRange' => '2005-2015',
                            'LotNumber' => 'LOT123',
                            'LegalDescription' => 'Test Legal Description',
                            'LandSqft' => '5000',
                            'GarageSqft' => '400',
                            'Subdivision' => 'Test Subdivision',
                            'ZoningCode' => 'R-1',
                            'IncomeHousehold' => '$75,000-$99,999',
                            'IncomeMidptsHousehold' => '$87,500',
                            'NetWorthHousehold' => '$250,000-$499,999',
                            'NetWorthMidptHousehold' => '$375,000',
                            'DiscretionaryIncome' => '$25,000',
                            'CreditMidpts' => '720',
                            'CreditRange' => '700-750',
                            'NumAdultsHousehold' => '2',
                            'NumChildrenHousehold' => '2',
                            'NumPersonsHousehold' => '4',
                            'ChildAged03Household' => '0',
                            'ChildAged46Household' => '1',
                            'ChildAged79Household' => '1',
                            'ChildAged1012Household' => '0',
                            'ChildAged1318Household' => '0',
                            'ChildrenHousehold' => '1',
                            'Cooking' => '1',
                            'Gardening' => '1',
                            'Music' => '0',
                            'Diy' => '1',
                            'Books' => '1',
                            'TravelVacation' => '1',
                            'HealthBeautyProducts' => '0',
                            'PetOwner' => '1',
                            'Photography' => '0',
                            'Fitness' => '1',
                            'Epicurean' => '0',
                            'OccupationCategory' => 'Professional',
                            'OccupationType' => 'Management',
                            'OccupationDetail' => 'Business Manager',
                            'MagazineSubscriber' => '1',
                            'CharityInterest' => '1',
                            'LikelyCharitableDonor' => '1',
                            'Cbsa' => '12345',
                            'CensusBlock' => 'Block123',
                            'CensusBlockGroup' => 'Group1',
                            'CensusTract' => 'Tract456',
                            'Voter' => '1',
                            'Urbanicity' => '3. Suburban',
                        ];
                        
                        // Parse advance_information for enhance type (comma-separated format: "1,2,4,...")
                        $advance_info = $get_leadspeek_type->advance_information;
                        // Try comma-separated format first (standard format for enhance)
                        if (strpos($advance_info, ',') !== false || is_numeric(trim($advance_info))) {
                            // Comma-separated format: "1,2,4,..."
                            $selected_information = array_map('trim', array_map('strval', explode(',', $advance_info)));
                        } else {
                            // Fallback: try JSON format if comma-separated doesn't work
                            $decoded = json_decode($advance_info, true);
                            if (json_last_error() === JSON_ERROR_NONE && isset($decoded['advance']) && is_array($decoded['advance'])) {
                                $selected_information = array_map('strval', $decoded['advance']);
                            } else {
                                $selected_information = [];
                            }
                        }
                        $selected_information = array_filter($selected_information, function($val) {
                            return !empty($val);
                        });
                        if (!empty($campaignInformation)) {
                            foreach ($campaignInformation as $info) {
                                $category = $info->type;
                                $description = json_decode($info->description, true);
                                if (array_key_exists($category, $categories) && in_array($info->id, $selected_information)) {
                                    foreach ($categories[$category] as $index => $column) {
                                        $value = isset($testValues[$column]) ? $testValues[$column] : 'Test Value';
                                        $camel = lcfirst(str_replace(' ', '', ucwords(str_replace(['-', '_'], ' ', strtolower($description[$index])))));
                                        $information_array[$camel] = $value;
                                    }
                                }
                            }
                        }
                    }
                    $payload = array_merge($payload,$information_array);            
                }

                if(!empty($webhook_url)){
                    foreach($webhook_url as $url){
                        $response = $http->post($url, [
                            'json' => $payload
                        ]);
                    }

                    return response()->json(['result' => 'success', 'message' => 'Sent test lead successfully!']);
                }

                return response()->json(['result' => 'failed', 'message' => 'Webhook url is required'], 400);
            } else if ($type == 'gohighlevel') {

                try {
                    /** START CREATE CUSTOM FIELD */
                        $tokens = $this->gohighlevelv2GetTokensDB($company_id);
                        $access_token = isset($tokens['access_token']) ? $tokens['access_token'] : '';
                        $refresh_token = isset($tokens['refresh_token']) ? $tokens['refresh_token'] : '';
                        $location_id = isset($tokens['location_id']) ? $tokens['location_id'] : '';
                        $ghlv2Connected = ($access_token != '' && $refresh_token != '' && $location_id != '');
                    
                        $email2Id = "";
                        $phone2Id = "";
                        $address2Id = "";
                        $keywordId = "";
                        //$urlId = "";
                        $contactId = "";
                        $clickDateId = "";
                        $global_setting = GlobalSettings::where('setting_name','ghl_custom_fields_create')->first();
                        $ghl_custom_fields_create = [];
                        if (!empty($global_setting) && isset($global_setting->setting_value)) {
                            $ghl_custom_fields_create = json_decode($global_setting->setting_value);
                        }

                        $dynamicVariables = [];
                        if (!empty($ghl_custom_fields_create)) {
                            foreach ($ghl_custom_fields_create as $item) {
                                $dynamicVariables[$item->id] = '';
                            }
                            
                        }

                        $comset_name = 'gohlcustomfields';
                        /** GET IF CUSTOM FIELD ALREADY EXIST */
                        $customfields = CompanySetting::where('company_id',$company_id)->whereEncrypted('setting_name',$comset_name)->get();
                        if (count($customfields) > 0) {
                            $_customfields = json_decode($customfields[0]['setting_value']);
                            $email2Id = (isset($_customfields->email2Id))?$_customfields->email2Id:'';
                            $phone2Id = (isset($_customfields->phone2Id))?$_customfields->phone2Id:'';
                            $address2Id = (isset($_customfields->address2Id))?$_customfields->address2Id:'';
                            $keywordId = (isset($_customfields->keywordId))?$_customfields->keywordId:'';
                            //$urlId = (isset($_customfields->urlId))?$_customfields->urlId:'';
                            $contactId = (isset($_customfields->contactId))?$_customfields->contactId:'';
                            $clickDateId = (isset($_customfields->clickDateId))?$_customfields->clickDateId:'';

                            if (!empty($ghl_custom_fields_create)) {
                                foreach ($ghl_custom_fields_create as $item) {
                                    $dynamicVariables[$item->id] = (isset($_customfields->{$item->id})) ? $_customfields->{$item->id} : '';
                                }
                            }
                        }
                        /** GET IF CUSTOM FIELD ALREADY EXIST */

                        // GENERAL FIELDS 
                            /** CREATE CUSTOM FIELD Alternative Email */
                            if ($email2Id == '') {
                                $createField = "";
                                if($ghlv2Connected) { // version 2
                                    // info('sendTestLeads_v2 1');
                                    $createField = $this->gohighlevelv2CreateCustomField($company_id, $access_token, $refresh_token, $location_id, 'Alternative Email', 'Alternative Email', 'TEXT');
                                } else { // version 1
                                    // info('sendTestLeads_v1 1');
                                    $createField = $this->ghl_CreateCustomField($api_key,'','Alternative Email','Alternative Email','TEXT',true);
                                }

                                if (trim($createField) != '') {
                                    $email2Id = trim($createField);
                                }
                            }
                            /** CREATE CUSTOM FIELD Alternative Email */

                            /** CREATE CUSTOM FIELD Alternative Phone */
                            if ($phone2Id == '') {
                                $createField = "";
                                if($ghlv2Connected) { // version 2
                                    // info('sendTestLeads_v2 2');
                                    $createField = $this->gohighlevelv2CreateCustomField($company_id, $access_token, $refresh_token, $location_id, 'Alternative Phone', 'Alternative Phone', 'TEXT');
                                } else { // version 1
                                    // info('sendTestLeads_v1 2');
                                    $createField = $this->ghl_CreateCustomField($api_key,'','Alternative Phone','Alternative Phone','TEXT',true);
                                }

                                if (trim($createField) != '') {
                                    $phone2Id = trim($createField);
                                }
                            }
                            /** CREATE CUSTOM FIELD Alternative Phone */

                            /** CREATE CUSTOM FIELD Street Address 2 */
                            if ($address2Id == '') {
                                $createField = "";
                                if($ghlv2Connected) { // version 2
                                    // info('sendTestLeads_v2 3');
                                    $createField = $this->gohighlevelv2CreateCustomField($company_id, $access_token, $refresh_token, $location_id, 'Street Address 2', 'Street Address 2', 'TEXT');
                                } else { // version 1
                                    // info('sendTestLeads_v1 3');
                                    $createField = $this->ghl_CreateCustomField($api_key,'','Street Address 2','Street Address 2','TEXT',true);
                                }

                                if (trim($createField) != '') {
                                    $address2Id = trim($createField);
                                }
                            }
                            /** CREATE CUSTOM FIELD Street Address 2 */

                            /** CREATE CUSTOM FIELD ID */
                            if ($contactId == '') {
                                $createField = "";
                                if($ghlv2Connected) { // version 2
                                    // info('sendTestLeads_v2 4');
                                    $createField = $this->gohighlevelv2CreateCustomField($company_id, $access_token, $refresh_token, $location_id, 'Contact ID', 'Contact ID', 'TEXT');
                                } else { // version 1
                                    // info('sendTestLeads_v1 4');
                                    $createField = $this->ghl_CreateCustomField($api_key,'','Contact ID','Contact ID','TEXT',true);
                                }

                                if (trim($createField) != '') {
                                    $contactId = trim($createField);
                                }
                            }
                            /** CREATE CUSTOM FIELD ID */

                            /** CREATE CUSTOM FIELD CLICKDATE */
                            if ($clickDateId == '') {
                                $createField = "";
                                if($ghlv2Connected) { // version 2
                                    // info('sendTestLeads_v2 5');
                                    $createField = $this->gohighlevelv2CreateCustomField($company_id, $access_token, $refresh_token, $location_id, 'Click Date', 'Click Date', 'TEXT');
                                } else { // version 1
                                    // info('sendTestLeads_v1 5');
                                    $createField = $this->ghl_CreateCustomField($api_key,'','Click Date','Click Date','TEXT',true);
                                }

                                if (trim($createField) != '') {
                                    $clickDateId = trim($createField);
                                }
                            }
                            /** CREATE CUSTOM FIELD CLICKDATE */

                            /** CREATE CUSTOM FIELD Keyword */
                            if ($keywordId == '') {
                                $createField = "";
                                if($ghlv2Connected) { // version 2
                                    // info('sendTestLeads_v2 6');
                                    $createField = $this->gohighlevelv2CreateCustomField($company_id, $access_token, $refresh_token, $location_id, 'Keywords', 'Keywords', 'TEXT');
                                } else { // version 1
                                    // info('sendTestLeads_v1 6');
                                    $createField = $this->ghl_CreateCustomField($api_key,'','Keywords','Keywords','TEXT',true);
                                }

                                if (trim($createField) != '') {
                                    $keywordId = trim($createField);
                                }
                            }
                            /** CREATE CUSTOM FIELD Keyword */

                            /** CREATE CUSTOM FIELD URL */
                            // $createField = $this->ghl_CreateCustomField($api_key,'jDrGGQMiOgQcv1S2qvjb','URL','URL','TEXT',true);
                            // if (trim($createField) != '') {
                            //     $urlId = trim($createField);
                            // }
                            /** CREATE CUSTOM FIELD URL */
                        // GENERAL FIELDS 

                        // CUSTOM FIELDS (ADVANCE, ETC)
                            $comset_val_custom = [];
                            if (!empty($ghl_custom_fields_create)) {
                                foreach ($ghl_custom_fields_create as $item) {
                                    if (isset($dynamicVariables[$item->id]) && $dynamicVariables[$item->id] == '' && isset($item->name) && isset($item->placeholder) && isset($item->dataType) && isset($item->showInForms)) { 
                                        $createField = "";
                                        if($ghlv2Connected) { // version 2
                                            // info('sendTestLeads_v2 7');
                                            $createField = $this->gohighlevelv2CreateCustomField($company_id, $access_token, $refresh_token, $location_id, $item->name, $item->placeholder, $item->dataType);
                                        } else { // version 1
                                            // info('sendTestLeads_v1 7');
                                            $createField = $this->ghl_CreateCustomField($api_key, '', $item->name, $item->placeholder, $item->dataType, $item->showInForms);
                                        }
                                        
                                        if (trim($createField) != '') {
                                            $dynamicVariables[$item->id] = trim($createField);
                                        }
                                    }
                                    $comset_val_custom[$item->id] = $dynamicVariables[$item->id];
                                }
                            }
                        // CUSTOM FIELDS (ADVANCE, ETC)

                        if ($company_id != '') {
                            $comset_val = [
                                "contactId" => $contactId,
                                "clickDateId" => $clickDateId,
                                "email2Id" => $email2Id,
                                "phone2Id" => $phone2Id,
                                "address2Id" => $address2Id,
                                "keywordId" => $keywordId,
                                //"urlId" => $urlId
                            ];

                            if (!empty($comset_val_custom)) {
                                $comset_val = array_merge($comset_val, $comset_val_custom);
                            }
                            $companysetting = CompanySetting::where('company_id',$company_id)->whereEncrypted('setting_name',$comset_name)->get();

                            if (count($companysetting) > 0) {
                                $updatesetting = CompanySetting::find($companysetting[0]['id']);
                                $updatesetting->setting_value = json_encode($comset_val);
                                $updatesetting->save();
                            }else{
                                $createsetting = CompanySetting::create([
                                    'company_id' => $company_id,
                                    'setting_name' => $comset_name,
                                    'setting_value' => json_encode($comset_val),
                                ]);
                            }

                        }
                    /** START CREATE CUSTOM FIELD */
                } catch (\Throwable $th) {
                    Log::info([
                        'error_create_custom_fields_send_test_leads' => $th->getMessage()
                    ]);
                }

                // General field setting
                $custom_fields = [
                    'Contact Id' => '',
                    'Click Date' => '',
                    'Email 2' => '',
                    'Phone 2' => '',
                    'Address 2' => '',
                    'Keyword' => '',
                ];

                $custom_fields_db = isset($integration_settings->custom_fields) ? json_decode($integration_settings->custom_fields) : null;
                if($custom_fields_db){
                    
                    if(in_array('Contact Id', $custom_fields_db)){
                        $custom_fields['Contact Id'] = '00000000';
                    }

                    if(in_array('Click Date', $custom_fields_db)){
                        $custom_fields['Click Date'] = $payload['clickdate'];
                    }

                    if(in_array('Email 2', $custom_fields_db)){
                        $custom_fields['Email 2'] = $payload['email2'];
                    }

                    if(in_array('Phone 2', $custom_fields_db)){
                        $custom_fields['Phone 2'] = $payload['phone2'];
                    }

                    if(in_array('Address 2', $custom_fields_db)){
                        $custom_fields['Address 2'] = $payload['address2'];
                    }

                    if(in_array('Keyword', $custom_fields_db)){
                        $custom_fields['Keyword'] = $payload['keyword'];
                    }

                } else {
                    $custom_fields = [
                        'Contact Id' => '00000000',
                        'Click Date' => $payload['clickdate'],
                        'Email 2' => $payload['email2'],
                        'Phone 2' => $payload['phone2'],
                        'Address 2' => $payload['address2'],
                        'Keyword' => $payload['keyword'],
                    ];
                }

                //Advance field setting
                $additional_fields = [];
                if ($is_advance || $get_leadspeek_type->leadspeek_type == 'b2b') {
                    if ($get_leadspeek_type->leadspeek_type == 'b2b') {
                        $payload['phone2'] = "";
                    }

                    $setting_name = '';
                    if ($get_leadspeek_type->leadspeek_type == 'b2b') {
                        $setting_name = 'custom_fields_b2b_gohighlevel';
                    }elseif ($is_advance) {
                        $setting_name ='custom_fields_advance_gohighlevel';
                    } 

                    $global_setting = GlobalSettings::where('company_id',$company_id)->where('setting_name', $setting_name)->first();
                    
                    $global_setting_custom_fields = GlobalSettings::where('setting_name','ghl_custom_fields_create')->first();

                    $ghl_custom_fields_create = [];
                    if (!empty($global_setting_custom_fields) && isset($global_setting_custom_fields->setting_value)) {
                        $ghl_custom_fields_create = json_decode($global_setting_custom_fields->setting_value);
                    }

                    if (isset($global_setting->setting_value) && !empty($global_setting->setting_value)) {
                        $selected_fields = json_decode($global_setting->setting_value);
                        if ($setting_name == 'custom_fields_b2b_gohighlevel' && is_array($selected_fields)) {
                            $selected_fields = array_map('strtolower', $selected_fields);
                        }
                        foreach ($ghl_custom_fields_create as $field) {
                            $selected = isset($field->category) ? $field->category : $field->name;  
                            if ($setting_name == 'custom_fields_b2b_gohighlevel') {
                                $selected = strtolower((string) $selected);
                                $selected = $selected === 'headquarters branch' ? 'headquartes branch' : $selected;
                            }
                            if (in_array($selected, $selected_fields)) {
                                $additional_fields[] = [
                                    'db_id' => $field->id,
                                    'value' => $field->name
                                ];
                            }
    
                        }
                    } else {
                        foreach ($ghl_custom_fields_create as $field) {
                            $additional_fields[] = [
                                'db_id' => $field->id,
                                'value' => $field->name
                            ];    
                        }
                    }
                }
                //Advance field setting
                $email = "johndoe1-{$now}-{$campaign_id}@example.com";
                // info(['email' => $email]);

                // add existing tag when email has ben already
                // $getContact = [];
                // if($ghlv2Connected) { // version 2
                //     info('sendTestLeads_v2 8');
                //     $getContact = $this->gohighlevelv2GetContact($company_id, $access_token, $refresh_token, $location_id, $email);
                // } else { // version 1
                //     info('sendTestLeads_v1 8');
                //     $getContact = $this->ghl_GetContact($api_key, $email);
                // }
                
                // $getContactStatus = isset($getContact['status']) ? $getContact['status'] : '';
                // $getContactTags = isset($getContact['data']['contacts'][0]['tags']) ? $getContact['data']['contacts'][0]['tags'] : [];
                
                // if($getContactStatus == 'success' && is_array($getContactTags) && count($getContactTags) > 0) {
                //     $tags_go_high_level = array_merge($tags_go_high_level,$getContactTags);
                //     $tags_go_high_level = array_unique($tags_go_high_level);
                //     $tags_go_high_level = array_values($tags_go_high_level);
                // }
                // add existing tag when email has ben already

                $response = [];
                if($ghlv2Connected) { // version 2
                    // info('sendTestLeads_v2 9');
                    $response = $this->gohighlevelv2CreateContact($company_id,$access_token,$refresh_token,$location_id,$custom_fields['Contact Id'],$custom_fields['Click Date'],'Jhon','Doe',$email,$custom_fields['Email 2'],$payload['phone1'],$custom_fields['Phone 2'],$payload['address1'],$custom_fields['Address 2'],$payload['city'],$payload['state'],$payload['postalCode'],$custom_fields['Keyword'],$tags_go_high_level,$campaign_id,$additional_fields,$status_keyword_to_tags);
                } else { // version 1
                    // info('sendTestLeads_v1 9');
                    $response = $this->ghl_createContact($company_id,$api_key,$custom_fields['Contact Id'],$custom_fields['Click Date'],'John','Doe',$email,$custom_fields['Email 2'],$payload['phone1'],$custom_fields['Phone 2'],$payload['address1'],$custom_fields['Address 2'],$payload['city'],$payload['state'],$payload['postalCode'],$custom_fields['Keyword'],$tags_go_high_level,$campaign_id,$additional_fields,$status_keyword_to_tags);
                }

                if($response['success'] == true){
                    return response()->json(['result' => 'success', 'message' => 'Sent test lead successfully!']);
                } else {
                    return response()->json(['result' => 'failed', 'message' => $response['error']], 500);
                }
            } else if ($type == 'sendgrid') {
                $list = '';
                if (empty($sendgrid_list)) {
                    $list = '';
                } else {
                    $list = $sendgrid_list;
                }

                $response = $this->sendContactToSendgrid($api_key, $list, "johndoe1-{$date}-{$campaign_id}@example.com", 'John', 'Doe', $payload['address1'], $payload['address2'], $payload['city'], $payload['state'], $payload['postalCode'], $payload['phone1'], "johndoe2-{$date}-{$campaign_id}@example.com", $payload['keyword'], 'https://url.example.com');
                
                if($response['success'] == true){
                    return response()->json(['result' => 'success', 'message' => 'Sent test lead successfully!']);
                } else {
                    return response()->json(['result' => 'failed', 'message' => $response['error']], 500);
                }
            } else if ($type == 'mailboxpower') {

                //B2B field setting
                $additional_fields = [];
                if($get_leadspeek_type->leadspeek_type == 'b2b'){
                    $payload['phone2'] = "";
                    $additional_fields = [
                        [
                            'text' => 'Employee ID',
                            'value' => '12526368',
                        ],
                        [
                            'text' => 'Company Name',
                            'value' => 'AI COMPANY',
                        ],
                        [
                            'text' => 'Company Phone',
                            'value' => '555-987-6543',
                        ],
                        [
                            'text' => 'Company Website',
                            'value' => 'aicompany.com',
                        ],
                        [
                            'text' => 'Number of Employees',
                            'value' => '1 TO 49',
                        ],
                        [
                            'text' => 'Sales Volume',
                            'value' => 'LESS THAN $500.000',
                        ],
                        [
                            'text' => 'Year Founded',
                            'value' => '1999',
                        ],
                        [
                            'text' => 'Job Title',
                            'value' => 'SALES STAFF',
                        ],
                        [
                            'text' => 'Level',
                            'value' => 'STAFF',
                        ],
                        [
                            'text' => 'Job Function',
                            'value' => 'SALES',
                        ],
                        [
                            'text' => 'Headquarters Branch',
                            'value' => '0',
                        ],
                        [
                            'text' => 'NAICS Code',
                            'value' => '441310',
                        ],
                        [
                            'text' => 'Last Seen Date',
                            'value' => '01/24/2024',
                        ],
                        [
                            'text' => 'LinkedIn',
                            'value' => 'linkedin.com/in/jhon-doe',
                        ],
                    ];
                    
                }
                //B2B field setting

                // Use group mail box power
                if(!empty($new_group_mail_box_power)){
                    foreach($new_group_mail_box_power as $key => $new_group){
                        $idGroup = $this->mbp_CreateGroups($api_key, $new_group);

                        if(in_array($new_group, $group_mail_box_power) && $idGroup != ''){
                            $group_mail_box_power[$key] = $idGroup;
                        }
                    }
                }

                if (!empty($group_mail_box_power)) {
                    foreach($group_mail_box_power as $group){
                        $response = $this->mbp_createContact($api_key, $group, ' ', '00000000', $campaign_id, $date, 'John', 'Doe', $payload['email1'], $payload['email2'], $payload['phone1'], $payload['phone2'], $payload['address1'], $payload['address2'], $payload['city'],$payload['state'], $payload['postalCode'], $payload['keyword'],'',$additional_fields);
                    }

                    return response()->json(['result' => 'success', 'message' => 'Sent test lead successfully!']);
                }
                // Use group mail box power
                $response = $this->mbp_createContact($api_key, $group_mail_box_power, ' ', '00000000', $campaign_id, $date, 'John', 'Doe', $payload['email1'], $payload['email2'], $payload['phone1'], $payload['phone2'], $payload['address1'], $payload['address2'], $payload['city'],$payload['state'], $payload['postalCode'], $payload['keyword'],'',$additional_fields);
                
                if($response['success'] == true){
                    return response()->json(['result' => 'success', 'message' => 'Sent test lead successfully!']);
                } else {
                    return response()->json(['result' => 'failed', 'message' => $response['error']], 500);
                }
            } else if ($type == 'kartra') {
                $api_password = $integration_settings->password;
                $this->NewKartraLead($api_key, $api_password, $company_id, $campaign_id, $date, $payload['firstName'], $payload['lastName'], $payload['email1'], $payload['phone1'], $payload['address1'], $payload['city'], $payload['state'], $payload['postalCode'], 'https://url.example.com', $payload['phone2'], $payload['email2'], $payload['address2'], $payload['keyword'], $kartra_tags, $kartra_list);
            } else if ($type == 'clickfunnels') {
                $workSpace = $this->clickfunnels_GetWorkSpaceId($api_key, $integration_settings->subdomain, $integration_settings->workspace_id);
                $workspace_id = $workSpace['id'];

                if($workSpace['result'] == 'failed'){
                    return response()->json(['result' => 'failed', 'message' => $workSpace['message']], $workSpace['status_code']);
                }

                $dateFormat = date('Y-m-d-H:i:s');
                
                $custom_fields_db = [];
                $b2b_custom_fields = [];
                if ($get_leadspeek_type->leadspeek_type == 'b2b') {
                    $global_setting = GlobalSettings::where('company_id',$company_id)->where('setting_name','custom_fields_b2b_clickfunnels')->first();
                    if (isset($global_setting->setting_value) && !empty($global_setting->setting_value)) {
                        $b2b_custom_fields = json_decode($global_setting->setting_value);
                    }else {
                        $b2b_custom_fields =  [
                            'Id Contact',
                            'Company Name',
                            'Company Website',
                            'Number of Employees',
                            'Sales Volume',
                            'Year Founded',
                            'Job Title',
                            'Level',
                            'Job function',
                            'Headquartes Branch',
                            'NAICS Code',
                            'Last Seen Date',
                            'Linked In'
                        ];
                    }
                }

                if($integration_settings->custom_fields){
                    $custom_fields_db = json_decode($integration_settings->custom_fields);
                    if (!empty($b2b_custom_fields)) {
                        $custom_fields_db = array_merge($custom_fields_db,$b2b_custom_fields);
                    }
                } else {
                    $integrationList = IntegrationList::select('custom_fields')
                                                        ->where('status', 1)
                                                        ->where('slug', $type)
                                                        ->first();
    
                    $decode_custom_fields = json_decode($integrationList->custom_fields, true);
                    $custom_fields_db = array_map(fn($field) => $field['name'], $decode_custom_fields);
                }

                $custom_attributes = [
                    'id_campaign' => '',
                    'id_contact' => '',
                    'click_date' => '',
                    'email_2' => "",
                    'phone_2' => '',
                    'country' => '',
                    'state' => '',
                    'city' => '',
                    'postal_code' => '',
                    'address_1' => '',
                    'address_2' => '',
                    'keyword' => ''
                ];

                $data = [
                    'email_address' => "johndoe1-{$dateFormat}@example.com",
                    'first_name' => 'John',
                    'last_name' => 'Doe',
                    'phone_number' => '',
                ];

                if(in_array('Id Campaign', $custom_fields_db)){
                    $custom_attributes['id_campaign'] = '123456';
                }
                if(in_array('Id Contact', $custom_fields_db)){
                    $custom_attributes['id_contact'] = '123456';
                }
                if(in_array('Click Date', $custom_fields_db)){
                    $custom_attributes['click_date'] = date('Y-m-d H:i:s');
                }
                if(in_array('Country', $custom_fields_db)){
                    $custom_attributes['country'] = "US";
                }
                if(in_array('Keyword', $custom_fields_db)){
                    $custom_attributes['keyword'] = "Testing - Send Contact";
                }

                // If Require Email Address
                if(in_array('Email 2', $custom_fields_db)){
                    $custom_attributes['email_2'] = "johndoe2-{$dateFormat}@example.com";
                }
                // If Require Email Address

                if($format['home_address_enabled'] == 'T'){
                    if(in_array('State', $custom_fields_db)){
                        $custom_attributes['state'] = "Columbus";
                    }
                    if(in_array('City', $custom_fields_db)){
                        $custom_attributes['city'] = "OH";
                    }
                    if(in_array('Postal Code', $custom_fields_db)){
                        $custom_attributes['postal_code'] = "43055";
                    }
                    if(in_array('Address 1', $custom_fields_db)){
                        $custom_attributes['address_1'] = "John Doe Street";
                    }
                    if(in_array('Address 2', $custom_fields_db)){
                        $custom_attributes['address_2'] = "John Doe Street 2";
                    }
                }

                if($format['phone_enabled'] == 'T'){
                    $data['phone_number'] = "555-123-4567";
                    
                    if(in_array('Phone 2', $custom_fields_db)){
                        $custom_attributes['phone_2'] = "567-567-5678";
                    }
                }

                if($get_leadspeek_type->leadspeek_type == 'b2b'){
                    $custom_attributes['phone_2'] = "";

                    if(in_array('Employee ID', $custom_fields_db)){
                        $custom_attributes['id_employee'] = "12526368";
                    }
                    if(in_array('Company Name', $custom_fields_db)){
                        $custom_attributes['company_name'] = "AI COMPANY";
                    }
                    if(in_array('Company Name', $custom_fields_db)){
                        $custom_attributes['company_phone'] = "555-987-6543";
                    }
                    if(in_array('Company Website', $custom_fields_db)){
                        $custom_attributes['company_website'] = "aicompany.com";
                    }
                    if(in_array('Number of Employees', $custom_fields_db)){
                        $custom_attributes['number_of_employees'] = "1 TO 49";
                    }
                    if(in_array('Sales Volume', $custom_fields_db)){
                        $custom_attributes['sales_volume'] = "LESS THAN $500.000";
                    }
                    if(in_array('Year Founded', $custom_fields_db)){
                        $custom_attributes['year_founded'] = "1999";
                    }
                    if(in_array('Job Title', $custom_fields_db)){
                        $custom_attributes['job_title'] = "SALES STAFF";
                    }
                    if(in_array('Level', $custom_fields_db)){
                        $custom_attributes['level'] = "STAFF";
                    }
                    if(in_array('Job function', $custom_fields_db)){
                        $custom_attributes['job_function'] = "SALES";
                    }
                    if(in_array('Headquartes Branch', $custom_fields_db)){
                        $custom_attributes['headquartes_branch'] = "0";
                    }
                    if(in_array('NAICS Code', $custom_fields_db)){
                        $custom_attributes['naics_code'] = "441310";
                    }
                    if(in_array('Last Seen Date', $custom_fields_db)){
                        $custom_attributes['last_seen_date'] = "01/24/2024";
                    }
                    if(in_array('Linked In', $custom_fields_db)){
                        $custom_attributes['linked_in'] = "linkedin.com/in/jhon-doe";
                    }
                }

                if ($is_advance) {
                    $campaignInformation = campaignInformation::where('status', 'active')->where('campaign_type','enhance')->orderBy('start_index', 'asc')->get();
                    $information_array = [];
                    $selected_information = explode(',',$get_leadspeek_type->advance_information);
                    foreach($campaignInformation as $item)
                    {
                        if (in_array($item->id, $selected_information)) {
                            $description = json_decode($item->description, true);                    
                            $index = [];
                            foreach ($description as $desc) {
                                $camel = str_replace(['-', '_', ' '], '_', strtolower($desc));                    
                                $index[$camel] = $desc;
                            }
                            $information_array = array_merge($information_array, $index);
                        }
                    }
                    $custom_attributes = array_merge($custom_attributes,$information_array);            
                }
                
                $data['custom_attributes'] = $custom_attributes;

                if (!empty($clickfunnels_tags)) {
                    $data['tag_ids'] = $clickfunnels_tags;
                }

                $response = $this->clickfunnels_CreateContact($api_key, $integration_settings->subdomain, $workspace_id, $data);
                return response()->json(['result' => $response['result'], 'message' => $response['message'], 'data' => $response['data']], $response['status_code']);
            } else if ($type == 'agencyzoom') {
                // info(__FUNCTION__ . ' agencyzoom', ['webhook_url' => $webhook_url]);
                if (empty($webhook_url)) {
                    return response()->json(['result' => 'failed', 'message' => 'Webhook url is required'], 400);
                }
                foreach ($webhook_url as $url) {
                    $this->agencyzoom_sendrecord($url, $payload['firstName'], $payload['lastName'], "{$payload['firstName']} {$payload['lastName']} Business", $payload['email1'], $payload['email2'], $payload['phone1'], $payload['phone2'], $payload['address1'], $payload['address2'], $payload['city'], $payload['state'], $payload['postalCode'], $payload['keyword']);
                }
                return response()->json(['result' => 'success', 'message' => 'Sent test lead successfully!']);
            } else if ($type == 'sendjim') {
                $integration = IntegrationSettings::where('company_id', $company_id)
                                                  ->where('integration_slug', 'sendjim')
                                                  ->first();
                if (empty($integration) || empty($integration->tokens)) {
                    return response()->json(['result' => 'failed', 'message' => 'SendJim is not connected'], 400);
                }

                $tokens = json_decode($integration->tokens, true);
                $apiToken = $tokens['GrantToken'] ?? '';
                if (empty($apiToken)) {
                    return response()->json(['result' => 'failed', 'message' => 'GrantToken missing for SendJim'], 400);
                }

                $campaign = LeadspeekUser::where('id', $campaign_id)
                                                        ->orWhere('leadspeek_api_id', $campaign_id)
                                                        ->first();
                $tags = ['Integration Test'];
                if ($campaign && !empty($campaign->sendjim_tags)) {
                    $decoded = json_decode($campaign->sendjim_tags, true);
                    if (is_array($decoded) && count($decoded) > 0) {
                        $tags = array_values(array_unique(array_filter($decoded, fn($t) => is_string($t) && trim($t) !== '')));
                    }
                }

                $now = date('YmdHis');
                $contact = [
                    'FirstName' => 'John',
                    'LastName' => 'Doe',
                    'StreetAddress' => 'John Doe Street',
                    'City' => 'Columbus',
                    'State' => 'OH',
                    'PostalCode' => '43055',
                    'Email' => "johndoe1-{$now}-{$campaign_id}@example.com",
                    'PhoneNumber' => '5551234567',
                    'Tags' => $tags,
                ];

                $createResp = $this->sendJimService->createContacts([$contact], $apiToken);

                $quicksendEnabled = false;
                if ($campaign) {
                    $quicksendEnabled = (bool) ($campaign->sendjim_quicksend_is_active ?? false);
                }
                if (!$quicksendEnabled) {
                    $clientCfg = IntegrationSettings::where('company_id', $company_id)
                        ->where('integration_slug', 'sendjim')->first();
                    if ($clientCfg) {
                        $cfg = json_decode($clientCfg->custom_fields ?? '[]', true) ?: [];
                        $quicksendEnabled = (bool)($cfg['quicksend_is_active'] ?? false);
                    }
                }

                return response()->json(['result' => 'success', 'message' => 'Sent test lead successfully!']);
            }
        } catch (\Throwable $th) {
            return response()->json(['result' => 'failed', 'message' => 'Something went wrong, please try again later.','error' => 'Error: ' . $th->getMessage()], 500);
        }
    }

    public function sendjimGetQuickSends(Request $request)
    {
        $company_id = (isset($request->company_id)) ? $request->company_id : '';
        if (empty($company_id)) {
            return response()->json(['result' => 'failed', 'message' => 'Company ID is required'], 400);
        }
        $integration = IntegrationSettings::where('company_id', $company_id)
            ->where('integration_slug', 'sendjim')->first();
        if (empty($integration) || empty($integration->tokens)) {
            return response()->json(['result' => 'failed', 'message' => 'SendJim is not connected'], 400);
        }
        $tokens = json_decode($integration->tokens, true);
        $apiToken = $tokens['GrantToken'] ?? '';
        if (empty($apiToken)) {
            return response()->json(['result' => 'failed', 'message' => 'GrantToken missing for SendJim'], 400);
        }
        try {
            $list = $this->sendJimService->getQuickSends($apiToken);
            return response()->json(['result' => 'success', 'data' => $list, 'status_code' => 200]);
        } catch (\Throwable $e) {
            return response()->json(['result' => 'failed', 'message' => $e->getMessage()], 500);
        }
    }
    
    public function getCustomIntegrations(Request $request)
    {
        // info(__FUNCTION__, ['all' => $request->all()]);
        try {
            // Validate company_id
            $company_id = (isset($request->company_id))?$request->company_id:"";
            
            if (empty($company_id)) {
                return response()->json([
                    'success' => false,
                    'message' => 'The company_id field is required.',
                    'status_code' => 400,
                ], 400);
            }

            // Get agency company_id from request
            // Frontend sends agency company_id in company_id parameter
            // (Integrations.vue sends company_parent, ConfigApp/Client.vue sends company_id which is agency)
            $agencyCompanyId = $company_id;
            
            // Initialize hidden integrations arrays (default: empty - show all)
            $hiddenIntegrationIds = [];
            $hiddenIntegrationSlugs = [];
            
            try {
                Log::info('getCustomIntegrations - Agency Company ID', [
                    'company_id' => $company_id,
                    'agencyCompanyId' => $agencyCompanyId,
                ]);

                // Get hidden integrations for the agency
                $hiddenIntegrations = $this->getHiddenIntegrationsForAgency($agencyCompanyId);
                $hiddenIntegrationIds = $hiddenIntegrations['ids'];
                $hiddenIntegrationSlugs = $hiddenIntegrations['slugs'];
                
                Log::info('getCustomIntegrations - Hidden Integrations', [
                    'hiddenIntegrationIds' => $hiddenIntegrationIds,
                    'hiddenIntegrationSlugs' => $hiddenIntegrationSlugs,
                ]);
            } catch (\Exception $e) {
                // If error getting hidden integrations, log but continue (show all integrations)
                Log::warning('getCustomIntegrations - Error getting hidden integrations, showing all', [
                    'error' => $e->getMessage(),
                    'agencyCompanyId' => $agencyCompanyId,
                ]);
            }


            // Fetch all integration list
            $integrationList = IntegrationList::all();
            // info('', ['integrationList' => $integrationList]);
            
            // Fetch custom integrations for the given company
            $customs = IntegrationCustom::where('company_id', $company_id)->get()->keyBy('integration_id');
            // info('', ['customs' => $customs]);

            // Map the integration data with custom values
            $data = $integrationList->map(function ($integration) use ($customs) {
                $custom = $customs->get($integration->id);

                return [
                    'id' => $integration->id,
                    'name' => $integration->name,
                    'custom_name' => optional($custom)->custom_name,
                    'slug' => $integration->slug,
                    'custom_slug' => optional($custom)->custom_slug,
                    'icon' => $integration->logo,
                    'custom_icon' => optional($custom)->custom_icon,
                    'custom_img' => optional($custom)->custom_img,
                    'custom_description' => optional($custom)->custom_description,
                    'refcode' => $integration->referralcode,
                ];
            });
            
            // Filter out googlesheet and hidden integrations
            try {
                $dataBeforeFilter = $data->count();
                
                // Normalize IDs to integers for comparison
                $hiddenIntegrationIds = array_map('intval', array_filter($hiddenIntegrationIds, function($id) {
                    return !empty($id) && (is_numeric($id) || is_int($id));
                }));
                $hiddenIntegrationSlugs = array_filter($hiddenIntegrationSlugs, function($slug) {
                    return !empty($slug) && is_string($slug);
                });
                
                Log::info('getCustomIntegrations - Normalized hidden integrations', [
                    'hidden_ids' => $hiddenIntegrationIds,
                    'hidden_slugs' => $hiddenIntegrationSlugs,
                ]);
                
                $data = $data->filter(function ($item) use ($hiddenIntegrationIds, $hiddenIntegrationSlugs) {
                    // Always filter out googlesheet
                    if ($item['slug'] === 'googlesheet') {
                        return false;
                    }
                    
                    $id = $item['id'] ?? null;
                    $slug = $item['slug'] ?? null;
                    
                    // Convert ID to int for comparison
                    $idInt = is_numeric($id) ? (int)$id : null;
                    
                    // Check if ID or slug is in hidden list
                    $isHiddenById = $idInt !== null && in_array($idInt, $hiddenIntegrationIds, true);
                    $isHiddenBySlug = $slug !== null && in_array($slug, $hiddenIntegrationSlugs, true);
                    
                    // Keep item if NOT hidden
                    return !$isHiddenById && !$isHiddenBySlug;
                })->values(); // Reset collection keys to sequential
                
                Log::info('getCustomIntegrations - Filter Result', [
                    'before_filter' => $dataBeforeFilter,
                    'after_filter' => $data->count(),
                    'filtered_data' => $data->map(function($item) {
                        return [
                            'id' => $item['id'] ?? null,
                            'slug' => $item['slug'] ?? null,
                        ];
                    })->toArray(),
                ]);
            } catch (\Exception $e) {
                // If error during filtering, log but continue (show all integrations except googlesheet)
                Log::error('getCustomIntegrations - Error during filtering, showing all except googlesheet', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
                // Fallback: only filter googlesheet
                $data = $data->filter(function ($item) {
                    return $item['slug'] !== 'googlesheet';
                });
            }
            
            $data = $data->map(function ($item) {
                if ($item['slug'] === 'gohighlevel') {
                    $item['name'] = 'Lead Connector';
                    $item['icon'] = 'fa-light fa-cube';
                } else if ($item['slug'] === 'agencyzoom' && empty($item['custom_img'])) {
                    $item['custom_img'] = 'https://emmspaces.nyc3.cdn.digitaloceanspaces.com/systems/agencyzoom_logo.png';
                } else if ($item['slug'] === 'sendjim' && empty($item['custom_img'])) {
                    // Force new SendJim logo everywhere this list is used
                    $item['custom_img'] = 'https://emmspaces.nyc3.cdn.digitaloceanspaces.com/systems/sendjimlogo.webp';
                }
                return $item;
            })->values();

            return response()->json([
                'success' => true,
                'message' => 'Integration list retrieved successfully.',
                'data' => $data,
                'status_code' => 200,
            ]);
        } catch (\Exception $e) {
            Log::info('getCustomIntegrations - Error', ['error' => $e->getMessage()]);
            // Handle any unexpected error
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while retrieving the integration data.',
                'error' => $e->getMessage(),
                'status_code' => 500,
            ], 500);
        }
    }

   public function getCustomIntegrationById(Request $request, $company_id, $slug)
    {
        try {
            if (empty($company_id) || empty($slug)) {
                return response()->json([
                    'success' => false,
                    'message' => 'The company_id and slug fields are required.',
                    'status_code' => 400,
                ], 400);
            }

            $integration = IntegrationList::where('slug', $slug)->first();

            if (!$integration || $slug === 'googlesheet') {
                return response()->json([
                    'success' => false,
                    'message' => 'Integration not found.',
                    'status_code' => 404,
                ], 404);
            }

            $custom = IntegrationCustom::where('company_id', $company_id)
                        ->where('integration_id', $integration->id)
                        ->first();

            $data = [
                'id' => $integration->id,
                'name' => $integration->name,
                'custom_name' => optional($custom)->custom_name,
                'slug' => $integration->slug,
                'custom_slug' => optional($custom)->custom_slug,
                'icon' => $integration->logo,
                'custom_icon' => optional($custom)->custom_icon,
                'custom_img' => optional($custom)->custom_img,
                'custom_description' => optional($custom)->custom_description,
                'refcode' => $integration->referralcode,
            ];

            return response()->json([
                'success' => true,
                'message' => 'Integration detail retrieved successfully.',
                'data' => $data,
                'status_code' => 200,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while retrieving the integration detail.',
                'error' => $e->getMessage(),
                'status_code' => 500,
            ]);
        }
    }
}
