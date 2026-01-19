<?php

namespace App\Http\Controllers;

use App\Models\Role;
use App\Models\User;
use App\Models\State;
use App\Models\Module;
use App\Models\PackagePlan;
use App\Models\Company;
use App\Models\Industry;
use Stripe\StripeClient;
use App\Models\RoleModule;
use App\Models\CompanySale;
use Illuminate\Http\Request;
use App\Models\CompanyStripe;
use App\Models\LeadspeekUser;
use App\Models\CompanySetting;
use App\Models\FeatureUser;
use App\Models\MasterFeature;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use ESolution\DBEncryption\Encrypter;

class UserController extends Controller
{
    public function resetpassword(Request $request) {
        $usrID = $request->usrID;
        $newpass =  Hash::make(trim($request->newpassword));
        $currpass = Hash::make(trim($request->currpassword));

        $usrchk = User::select('password')->where('id','=',$usrID)->get();
        $existpass = $usrchk[0]['password'];

        if (Hash::check($request->currpassword,$existpass)) {
            $usrupdate = User::find($usrID);
            $usrupdate->password = $newpass;
            $usrupdate->save();
            return response()->json(array('result'=>'success'));
        }else{
            return response()->json(array('result'=>'failed','message'=>'Sorry, your current password invalid'));
        }
        
        
    }
    
    public function checksetupcomplete(Request $request) {
        $usrID = $request->usrID;
        $usr = User::select('profile_setup_completed','user_type','disable_client_add_campaign','payment_status','failed_invoiceid','failed_invoicenumber','failed_total_amount',
                            'failed_campaignid','customer_payment_id','customer_card_id','questionnaire_setup_completed','company_id','company_parent','company_root_id')
                    ->where('id','=',$usrID)
                    ->get();
        $userSetup = 'F';
        $userType = '';
        $companyParent = '';
        $clientPaymentFailed = false;
        $clientPaymentFailed = false;
        $paymentStatusFailed = false;
        $failed_campaignid = array();
        $failed_total_amount = array();

        if(count($usr) > 0) {
            $userSetup = $usr[0]['profile_setup_completed'];
            $userType = $usr[0]['user_type'];
            $companyParent = $usr[0]['company_parent'];
            $clientPaymentFailed = ($usr[0]['payment_status'] == 'failed' && trim($usr[0]['failed_campaignid']) != '')?true:false;
            $paymentStatusFailed = ($usr[0]['payment_status'] == 'failed')?true:false;
        }

        /** CHECK IF CLIENT THEY REGISTER TO WHAT MODULE */
        $accessmodule['leadspeek'] = false;
        $accessmodule['leadspeek_type'] = 'local';

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
            ],
        ];
        $isBeta = false;

        if($userType == 'client') {
            // $leadspeek = LeadspeekUser::select('leadspeek_type')
            //                 ->where('user_id','=',$usrID)
            //                 ->distinct()
            //                 ->get();
            //if (count($leadspeek) > 0) {
            if ($usr[0]['customer_payment_id'] != '' && $usr[0]['customer_card_id'] != '') {
                $accessmodule['leadspeek'] = true;
                $accessmodule['leadspeek_type'] = 'local|locator';

                if ($usr[0]['questionnaire_setup_completed'] == 'F') {
                    $updateUsr = User::find($usrID);
                    $updateUsr->questionnaire_setup_completed = 'T';
                    // $updateUsr->profile_setup_completed = 'T';
                    $updateUsr->save();
                }
                // $accessmodule['leadspeek'] = true;
                // $leadspeektype = "";
                // foreach($leadspeek as $lp) {
                //     $leadspeektype .= $lp['leadspeek_type'] . '|';
                // }
                // $leadspeektype = rtrim($leadspeektype,'|');

                // $accessmodule['leadspeek_type'] = $leadspeektype;
            }

            if ($usr[0]['disable_client_add_campaign'] == 'T') {
                $accessmodule['leadspeek'] = true;
                $accessmodule['leadspeek_type'] = 'local|locator';
                $userSetup = 'T';
            }

            /** IF PAYMENT FAILED THEN DISABLED ACCES AND REDIRECT TO UPDATE CC */
            if ($clientPaymentFailed) {
                $userSetup = 'F';
                $accessmodule = 'paymentfailed';
                $failed_campaignid = explode('|',$usr[0]['failed_campaignid']);
                $failed_total_amount = explode('|',$usr[0]['failed_total_amount']);
            }
            
            // SELECTED MODULES FOR CLIENT
                $agencysidebar_setting = '';
                $clientsidebar_setting = '';
                $rootsidebarsetting = '';
                $clientsidebar = '';
                $root_module = CompanySetting::where('company_id', trim($usr[0]['company_root_id']))
                    ->whereEncrypted('setting_name', 'rootcustomsidebarleadmenu')
                    ->get();
                if (count($root_module) > 0) {
                    $rootsidebarsetting = json_decode($root_module[0]['setting_value']);
                    $root_module_original = json_decode($root_module[0]['setting_value']); // Simpan value original sebelum filtering
                    $agencysidebar_setting = $this->getcompanysetting($usr[0]['company_parent'], 'agencysidebar');
                    $clientsidebar_setting = $this->getcompanysetting($usr[0]['company_id'], 'clientsidebar');
                    $exist_setting_agency = $this->getcompanysetting($usr[0]['company_root_id'], 'rootexistagencymoduleselect');
                    $exist_setting_client = $this->getcompanysetting($usr[0]['company_root_id'], 'rootexistclientmoduleselect');


                    if (!empty($clientsidebar_setting) && isset($clientsidebar_setting->SelectedModules) || !empty($clientsidebar_setting) && isset($clientsidebar_setting->SelectedSideBar)) {//IF CLIENT ALREADY HAS IT OWN SETTING

                        if (!empty($agencysidebar_setting) && isset($agencysidebar_setting->SelectedModules)) {
                            foreach ($agencysidebar_setting->SelectedModules as $key => $value) {
                                foreach ($rootsidebarsetting as $key1 => $value1) {
                                    if ($key1 == $value->type && $value->status == false) {
                                        unset($rootsidebarsetting->$key1);
                                    }
                                }
                            }
                        } elseif (!empty($exist_setting_agency) && isset($exist_setting_agency->SelectedModules)) {
                            foreach ($exist_setting_agency->SelectedModules as $key => $value) {
                                foreach ($rootsidebarsetting as $key2 => $value2) {
                                    if ($key2 == $value->type && $value->status == false) {
                                        unset($rootsidebarsetting->$key2);
                                    }
                                }
                            }
                        }

                        if (isset($clientsidebar_setting->SelectedModules)) {

                            // for handle when root add new modules after clientsidebar created
                            $exist_setting_client = $this->getcompanysetting($usr[0]['company_root_id'], 'rootexistclientmoduleselect');
                            if (!empty($exist_setting_client) && isset($exist_setting_client->SelectedModules)) { 
                                $selected_modules = $clientsidebar_setting->SelectedModules;
                                $root_modules = array_keys((array)$rootsidebarsetting);
                                
                                $existing_modules = [];
                                foreach ($selected_modules as $module) {
                                    $existing_modules[] = $module->type;
                                }

                                foreach ($root_modules as $mod) {
                                    if (!in_array($mod, $existing_modules)) {
                                        foreach ($exist_setting_client->SelectedModules as $key => $value) {
                                            foreach ($rootsidebarsetting as $key2 => $value2) {
                                                if ($mod == $key2 && $mod == $value->type && $value->status == false) {
                                                    unset($rootsidebarsetting->$key2);
                                                }
                                            }
                                        }
                                    }
                                }
                            }
                            // for handle when root add new modules after clientsidebar created

                            foreach ($clientsidebar_setting->SelectedModules as $key => $value) {
                                foreach ($rootsidebarsetting as $key3 => $value3) {
                                    if ($key3 == $value->type && $value->status == false) {
                                        unset($rootsidebarsetting->$key3);
                                    }
                                }
                            }

                        }elseif (isset($clientsidebar_setting->SelectedSideBar)) {//it's for the latest setting that use SelectedSideBar for variable 

                            // for handle when root add new modules after clientsidebar created
                            $exist_setting_client = $this->getcompanysetting($usr[0]['company_root_id'], 'rootexistclientmoduleselect');
                            if (!empty($exist_setting_client) && isset($exist_setting_client->SelectedModules)) { 
                                $selected_modules = $clientsidebar_setting->SelectedSideBar;
                                $root_modules = array_keys((array)$rootsidebarsetting);
                                
                                $existing_modules = [];
                                foreach ($selected_modules as $module) {
                                    $existing_modules[] = $module->type;
                                }

                                foreach ($root_modules as $mod) {
                                    if (!in_array($mod, $existing_modules)) {
                                        foreach ($exist_setting_client->SelectedModules as $key => $value) {
                                            foreach ($rootsidebarsetting as $key2 => $value2) {
                                                if ($mod == $key2 && $mod == $value->type && $value->status == false) {
                                                    unset($rootsidebarsetting->$key2);
                                                }
                                            }
                                        }
                                    }
                                }
                            }
                            // for handle when root add new modules after clientsidebar created

                            foreach ($clientsidebar_setting->SelectedSideBar as $key => $value) {
                                foreach ($rootsidebarsetting as $key4 => $value4) {
                                    if ($key4 == $value->type && $value->status == false) {
                                        unset($rootsidebarsetting->$key4);
                                    }
                                }
                            }
                        }

                        $clientsidebar = $rootsidebarsetting;
                        
                    } else {//IF CLIENT DIDN'T HAVE IT OWN SETTING

                        if (!empty($agencysidebar_setting) && isset($agencysidebar_setting->SelectedModules)) {

                            foreach ($agencysidebar_setting->SelectedModules as $key => $value) {
                                foreach ($rootsidebarsetting as $key1 => $value1) {
                                    if ($key1 == $value->type && $value->status == false) {
                                        unset($rootsidebarsetting->$key1);
                                    }
                                }
                            }

                        } elseif (!empty($exist_setting_agency) && isset($exist_setting_agency->SelectedModules)) {

                            foreach ($exist_setting_agency->SelectedModules as $key => $value) {
                                foreach ($rootsidebarsetting as $key2 => $value2) {
                                    if ($key2 == $value->type && $value->status == false) {
                                        unset($rootsidebarsetting->$key2);
                                    }
                                }
                            }

                        } 
                        
                        if (!empty($exist_setting_client) && isset($exist_setting_client->SelectedModules)) {
                            
                            foreach ($exist_setting_client->SelectedModules as $key => $value) {
                                foreach ($rootsidebarsetting as $key3 => $value3) {
                                    if ($key3 == $value->type && $value->status == false) {
                                        unset($rootsidebarsetting->$key3);
                                    }
                                }
                            }
                        }

                        $clientsidebar = $rootsidebarsetting;
                    }

                    // Force predict to always be true for clients (if root has predict and agency enables it) -jidan-
                    if(isset($root_module_original->predict) && !empty($clientsidebar)) {
                        // Check if agency has enabled predict
                        $agencyPredictEnabled = false;
                        
                        // Check from agencysidebar_setting
                        if (!empty($agencysidebar_setting) && isset($agencysidebar_setting->SelectedModules)) {
                            foreach ($agencysidebar_setting->SelectedModules as $module) {
                                if (isset($module->type) && $module->type === 'predict' && $module->status === true) {
                                    $agencyPredictEnabled = true;
                                    break;
                                }
                            }
                        }
                        
                        // Only add predict if agency has enabled it
                        if ($agencyPredictEnabled && !isset($clientsidebar->predict)) {
                            $clientsidebar->predict = $root_module_original->predict;
                        }
                    }
                    // Force predict to always be true for clients (if root has predict and agency enables it) -jidan-
                }
            // SELECTED MODULES FOR CLIENT

            /* SIDEBARMENU */
            $customsidebarleadmenu = "";
            $rootsidebarleadmenu = "";

            $rootcompanysetting = CompanySetting::where('company_id',trim($usr[0]['company_root_id']))->whereEncrypted('setting_name','rootcustomsidebarleadmenu')->get();
            if (count($rootcompanysetting) > 0) {
                $rootsidebarleadmenu = json_decode($rootcompanysetting[0]['setting_value']);
            }
            
            $companysetting = CompanySetting::where('company_id',trim($usr[0]['company_parent']))->whereEncrypted('setting_name','customsidebarleadmenu')->get();
            if (count($companysetting) > 0) {
                $customsidebarleadmenu = json_decode($companysetting[0]['setting_value']);
            }
            /* SIDEBARMENU */

            /* BETA FEATURE */
            $featureBeta = MasterFeature::all();
            $featureUser = FeatureUser::where('company_id','=',$companyParent)->first();
            $isBeta = (isset($featureUser->is_beta) && $featureUser->is_beta == 'T') ? true : false;
            // info('', ['featureBeta' => $featureBeta, 'featureUser' => $featureUser, 'companyParent' => $companyParent]);
            
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
            /* BETA FEATURE */
            // companyParent
        }
        /** CHECK IF CLIENT THEY REGISTER TO WHAT MODULE */

        /** CHECK STRIPE CONNECTED ACCOUNT FOR AGENCY */
        $companyConnectStripe = CompanyStripe::select('status_acc','acc_connect_id','package_id')
                                             ->where('company_id','=',$companyParent)
                                             ->first();
        $companyPaymentGateway = Company::select('paymentgateway')
                                        ->where('id','=',$companyParent)
                                        ->first();

        $accountConnected = '';
        $package_id = '';
        $paymentgateway = 'stripe';
        if(!empty($companyConnectStripe)) 
        {
            $accountConnected = $companyConnectStripe->status_acc;
            $package_id = $companyConnectStripe->package_id;
            if ($accountConnected == "" && !empty($companyPaymentGateway)) 
            {
                $paymentgateway = $companyPaymentGateway->paymentgateway;
            }
        }
        // info('checksetupcomplete', ['companyParent' => $companyParent, 'companyConnectStripe' => $companyConnectStripe, 'accountConnected' => $accountConnected, 'package_id' => $package_id, 'paymentgateway' => $paymentgateway]);
        /** CHECK STRIPE CONNECTED ACCOUNT FOR AGENCY */

        $enabledClientDeletedAccount = 'F';
        if($userType == 'client') {
            $agencyOwner = User::select('enabled_client_deleted_account')
                            ->where('active','=','T')
                            ->where('company_id','=',$companyParent)
                            ->where('user_type','=','userdownline')
                            ->first();
            if($agencyOwner && $agencyOwner->enabled_client_deleted_account) {
                $enabledClientDeletedAccount = $agencyOwner->enabled_client_deleted_account;
            }
        }

        return response()->json(array('result'=>'success','setupcomplete'=>$userSetup,'accessmodule'=>$accessmodule,'fcampid'=>$failed_campaignid,'finamt'=>$failed_total_amount,'sidemenu'=>$customsidebarleadmenu,'rootsidemenu'=>$rootsidebarleadmenu,'clientsidebar'=>$clientsidebar,'paymentStatusFailed'=>$paymentStatusFailed,'isBeta'=>$isBeta,'betaFeature'=>$betaFeature,'accountconnected'=>$accountConnected,'package_id'=>$package_id,'paymentgateway'=>$paymentgateway,'enabledClientDeletedAccount'=>$enabledClientDeletedAccount));

    }

    public function setupcomplete(Request $request) {
        $usrID = $request->usrID;
        $statusComplete = $request->statuscomplete;

        $usr = User::find($usrID);
        $usr->profile_setup_completed = $statusComplete;
        $usr->save();

        return response()->json(array('result'=>'success','params'=>$usr));
    }
    public function onlyuser(Request $request) {
        $usrID = (isset($request->usrID) && $request->usrID != '')?$request->usrID:'';
        $user = $request->user()->find($usrID);
        if ($user['disable_client_add_campaign'] == 'T' && $user['user_type'] == 'client') {
            $user['questionnaire_setup_completed'] = 'T';
        }
        
        return $user;
    }

    public function show(Request $request)
    {
        $usrID = (isset($request->usrID) && $request->usrID != '')?$request->usrID:'';
        $user = $request->user();
        if ($usrID != '') {
            $user = $request->user()->find($usrID);
        }else{
            $usrID = $user->id;
        }

        $user->industry_name = '';
        $user->company_name = '';
        $user->company_phone = '';
        $user->company_phone_country_calling_code = '';
        $user->company_phone_country_code = '';
        $user->company_email = '';
        $user->company_address = '';
        $user->company_logo = '';
        $user->systemuser = false;
        $user->domain = '';
        $user->subdomain = '';
        $user->sidebar_bgcolor = '#942434';
        $user->template_bgcolor = '#1E1E2F';
        $user->box_bgcolor = '#27293d';
        $user->text_color = '#FFFFFF';
        $user->link_color = '#942434';
        $user->font_theme = '';
        $user->paymentterm_default = 'Weekly';
        $user->login_image = '/img/EMMLogin.png';
        $user->client_register_image = '/img/EMMLogin.png';
        $user->agency_register_image = 'https://emmspaces.nyc3.cdn.digitaloceanspaces.com/systems/agencyregister.png';
        $user->logo_login_register = 'https://emmspaces.nyc3.cdn.digitaloceanspaces.com/systems/yourlogohere.png';
        $user->paymentgateway = 'stripe';
        $user->manual_bill = 'F';

        if ($user->industry_id != '' && $user->industry_id != '0') {
            $industry = Industry::where('id','=',$user->industry_id)->first();
            $user->industry_name = $industry->industry_name;
            $user->industry_category = $industry->realestate;
            $user->industry_code = $industry->industry_code;
        }

        $getClientParentManualBill = "";

        if ($user->user_type == 'client') {
            //$company = Company::where('id','=',$user->company_parent)->first();
            if ($user->company_id == '' || is_null($user->company_id) || $user->company_id === null) {
                $company = Company::where('id','=',$user->company_parent)->first();
            }else{
                $company = Company::where('id','=',$user->company_id)->first();
                $companyParent = Company::where('id','=',$user->company_parent)->first();
                if (isset($companyParent) && $companyParent->count() > 0) {
                    $getClientParentManualBill = $companyParent->manual_bill;
                }
            }
        }else if ($user->company_id != '' && $user->company_id != '0') {
            $company = Company::where('id','=',$user->company_id)->first();
        }

        /** GET ROOT SYS CONF */
        $confAppDomain =  config('services.application.domain');
        $confAppSysID = config('services.application.systemid');
        if ($user->company_root_id != "") {
            $conf = $this->getCompanyRootInfo($user->company_root_id);
            $confAppDomain = $conf['domain'];
            $confAppSysID = $user->company_root_id;
        }
        /** GET ROOT SYS CONF */

        if (isset($company) && $company->count() > 0) {
            $user->company_name = $company->company_name;
            $user->company_phone = $company->phone;
            $user->company_email = $company->email;
            $user->company_address = $company->company_address;
            $user->company_logo = $company->logo;
            $user->company_city = $company->company_city;
            $user->company_zip = $company->company_zip;
            $user->company_country_code = $company->company_country_code;
            $user->company_state_code = $company->company_state_code;
            $user->company_state_name = $company->company_state_name;
            $user->company_phone_country_code = $company->phone_country_code;
            $user->company_phone_country_calling_code = $company->phone_country_calling_code;
            $user->externalorgid = $company->simplifi_organizationid;
            $user->domain = $company->domain;
            $user->subdomain = str_replace('.' . $confAppDomain,'',$company->subdomain);
            $user->status_domain = $company->status_domain;
            $user->whitelabelling = $company->whitelabelling;
            $user->sidebar_bgcolor = ($company->sidebar_bgcolor != '' && $company->sidebar_bgcolor != 'null')?$company->sidebar_bgcolor:$conf['sidebar_bgcolor'];
            $user->template_bgcolor = ($company->template_bgcolor != '' && $company->template_bgcolor != 'null')?$company->template_bgcolor:$conf['template_bgcolor'];
            $user->box_bgcolor = ($company->box_bgcolor != '' && $company->box_bgcolor != 'null')?$company->box_bgcolor:$conf['box_bgcolor'];
            $user->text_color = ($company->text_color != '' && $company->text_color != 'null')?$company->text_color:$conf['text_color'];
            $user->link_color = ($company->link_color != '' && $company->link_color != 'null')?$company->link_color:$conf['link_color'];
            $user->font_theme = ($company->font_theme != '' && $company->font_theme != 'null')?$company->font_theme:$conf['font_theme'];
            $user->paymentterm_default = ($company->paymentterm_default != '' && $company->paymentterm_default != 'null')?$company->paymentterm_default:'';

            $user->login_image = ($company->login_image != '' && $company->login_image != 'null')?$company->login_image:'';
            $user->client_register_image = ($company->client_register_image != '' && $company->client_register_image != 'null')?$company->client_register_image:'';
            $user->agency_register_image = ($company->agency_register_image != '' && $company->agency_register_image != 'null')?$company->agency_register_image:'';
            $user->logo_login_register = ($company->logo_login_register != '' && $company->logo_login_register != 'null')?$company->logo_login_register:$company->logo;

            $user->paymentgateway = ($company->paymentgateway != '' && $company->paymentgateway != 'null')?$company->paymentgateway:'stripe';
            if ($getClientParentManualBill != '') {
                $user->manual_bill = $getClientParentManualBill;
            }else{
                $user->manual_bill = $company->manual_bill;
            }
        }

        /** CHECK IF THIS USER IS DEFAULT SYSTEM USER */
        if ($user->company_id == $confAppSysID) {
            $user->systemuser = true;
        }
        /** CHECK IF THIS USER IS DEFAULT SYSTEM USER */

        /** CHECK USER IF ADMIN AND NOT THE OWNER ADMIN OF AGENCY */
        if($user->user_type == 'user' && $user->isAdmin == 'T' && $user->company_id == $user->company_parent) {
            $ownerPaymentSetup = User::select('id')
                                        ->where('company_id','=',$user->company_id)
                                        ->where('company_parent','=',$confAppSysID)
                                        ->where('customer_payment_id','<>','')
                                        ->where('customer_card_id','<>','')
                                        ->get();

            if (count($ownerPaymentSetup) > 0) {
                $user->customer_card_id = 'completedsetup';
            }
        }
        /** CHECK USER IF ADMIN AND NOT THE OWNER ADMIN OF AGENCY */


        /** CHECK IF CLIENT THEY REGISTER TO WHAT MODULE */
        if($user->user_type == 'client') {
            // $leadspeek = LeadspeekUser::select('id','leadspeek_type')
            //                 ->where('user_id','=',$user->id)
            //                 ->get();
            // if (count($leadspeek) > 0) {
            if ($user->customer_payment_id != "" && $user->customer_card_id != "") {
                $user->menuLeadspeek = true;
                //$user->leadspeek_type = $leadspeek[0]['leadspeek_type'];
                $user->leadspeek_type = 'local|locator';
                if ($user->questionnaire_setup_completed  == 'F') {
                    $user->questionnaire_setup_completed = 'T';
                    $updateUsr = User::find($user->id);
                    $updateUsr->questionnaire_setup_completed = 'T';
                    // $updateUsr->profile_setup_completed = 'T';
                    $updateUsr->save();
                }
            }
            //}

            $defaultCustomerCare = $this->get_default_admin($user->company_parent);
            $AdminDefaultEmail = (isset($defaultCustomerCare[0]['email']))?$defaultCustomerCare[0]['email']:'';
            $user->customercare = $AdminDefaultEmail;
            $user->customercarename = (isset($defaultCustomerCare[0]['name']))?$defaultCustomerCare[0]['name']:'';
            
            if ($user->disable_client_add_campaign == 'T') {
                $user->questionnaire_setup_completed = 'T'; 
            }
            
            $companyparent = Company::select('company_name','company_address','company_city','company_zip','company_state_name','company_state_code','company_country_code','logo','email')->where('id','=',$user->company_parent)->get();
            $user->companyparentname = (isset($companyparent[0]['company_name']))?ucwords($companyparent[0]['company_name']):'';
            $user->companyparentaddress = (isset($companyparent[0]['company_address']))?$companyparent[0]['company_address']:'';
            $user->companyparentcity = (isset($companyparent[0]['company_city']))?$companyparent[0]['company_city']:'';
            $user->companyparentzip = (isset($companyparent[0]['company_zip']))?$companyparent[0]['company_zip']:'';
            $user->companyparentstate = (isset($companyparent[0]['company_state_code']))?$companyparent[0]['company_state_code']:'';
            $user->companyparentcountry = (isset($companyparent[0]['company_country_code']))?$companyparent[0]['company_country_code']:'';
            $user->companyparentlogo = (isset($companyparent[0]['logo']))?$companyparent[0]['logo']:'';
            $user->companyparentemail = (isset($companyparent[0]['email']))?$companyparent[0]['email']:'';
            
            $companyClient = Company::where('id','=',$user->company_parent)->first();
            $user->sidebar_bgcolor = ($companyClient->sidebar_bgcolor != '' && $companyClient->sidebar_bgcolor != 'null')?$companyClient->sidebar_bgcolor:$user->sidebar_bgcolor;
            $user->text_color = ($companyClient->text_color != '' && $companyClient->text_color != 'null')?$companyClient->text_color:$user->text_color;
            $user->template_bgcolor = ($companyClient->template_bgcolor != '' && $companyClient->template_bgcolor != 'null')?$companyClient->template_bgcolor:$user->template_bgcolor;
            $user->box_bgcolor = ($companyClient->box_bgcolor != '' && $companyClient->box_bgcolor != 'null')?$companyClient->box_bgcolor:$user->box_bgcolor;
            $user->font_theme = ($companyClient->font_theme != '' && $companyClient->font_theme != 'null')?$companyClient->font_theme:$user->font_theme;
            $user->paymentterm_default = ($companyClient->paymentterm_default != '' && $companyClient->paymentterm_default != 'null')?$companyClient->paymentterm_default:'';

           
            /** GET PACKAGE WHITELABELLING OR NON */
            $companyStripeInClient = CompanyStripe::where('company_id','=',$user->company_parent)
            ->where('status_acc','<>','')
            ->get();
            $whitelabellingpackageplansInClient = 'F';
            if( $companyStripeInClient->count() > 0){
                if (trim($companyStripeInClient[0]->package_id) != '') {
                    $chkPackage = PackagePlan::select('whitelabelling')
                                            ->where('package_id','=',trim($companyStripeInClient[0]->package_id))
                                            ->get();
                    foreach($chkPackage as $chkpak) {
                            $whitelabellingpackageplansInClient = $chkpak['whitelabelling'];
                    }
                    
                } 
            }
            /** GET PACKAGE WHITELABELLING OR NON */
            $is_whitelabelingInClient = $companyClient->is_whitelabeling ? $companyClient->is_whitelabeling : $whitelabellingpackageplansInClient;
            $user->sidebar_bgcolor = $is_whitelabelingInClient == 'T' ? $companyClient->sidebar_bgcolor : '#942434';
            $user->text_color = $is_whitelabelingInClient == 'T' ? $companyClient->text_color : '#FFFFFF';
        }
        /** CHECK IF CLIENT THEY REGISTER TO WHAT MODULE */

        /* PRODUCT NAME AND URL */
        if($usrID != '' && trim($user->company_id) != '') 
        {
            $user->leadlocalname = "Site ID";
            $user->leadlocalurl = "siteid";
            $user->leadlocatorname = "Search ID";
            $user->leadlocatorurl = "searchid";
            $user->leadenhancename = "Enhance ID";
            $user->leadenhanceurl = "enhanceid";
            $user->leadb2bname = "B2B ID";
            $user->leadb2burl = "b2bid";
            $user->leadsimplifiname = "Simplifi ID";
            $user->leadsimplifiurl = "simplifiid";
            $user->leadpredictname = "Predict ID";
            $user->leadpredicturl = "predictid";

            $rootcompanysetting = CompanySetting::where('company_id',trim($confAppSysID))->whereEncrypted('setting_name','rootcustomsidebarleadmenu')->get(); // setting module root
            if(count($rootcompanysetting) > 0) 
            {
                $productname = json_decode($rootcompanysetting[0]['setting_value']);
                $user->leadlocalname = $productname->local->name;
                $user->leadlocalurl = $productname->local->url;
                $user->leadlocatorname = $productname->locator->name;
                $user->leadlocatorurl = $productname->locator->url;
                $user->leadenhancename = isset($productname->enhance->name)?$productname->enhance->name:null;
                $user->leadenhanceurl = isset($productname->enhance->url)?$productname->enhance->url:null;
                $user->leadb2bname = isset($productname->b2b->name)?$productname->b2b->name:null;
                $user->leadb2burl = isset($productname->b2b->url)?$productname->b2b->url:null;
                $user->leadsimplifiname = isset($productname->simplifi->name)?$productname->simplifi->name:null;
                $user->leadsimplifiurl = isset($productname->simplifi->url)?$productname->simplifi->url:null;
                $user->leadpredictname = isset($productname->predict->name)?$productname->predict->name:null;
                $user->leadpredicturl = isset($productname->predict->url)?$productname->predict->url:null;
            }

            if($user->user_type == 'client') 
            {
                if($this->checkwhitelabellingpackage(trim($user->company_parent))) 
                {
                    $companysetting = CompanySetting::where('company_id',trim($user->company_parent))->whereEncrypted('setting_name','customsidebarleadmenu')->get(); // setting module agency
                    if(count($companysetting) > 0) 
                    {
                        $cs = json_decode($companysetting[0]['setting_value']);
                        $user->leadlocalname = $cs->local->name;
                        $user->leadlocalurl = $cs->local->url;
                        $user->leadlocatorname = $cs->locator->name;
                        $user->leadlocatorurl = $cs->locator->url;

                        $productname = "";
                        if(count($rootcompanysetting) > 0){
                            $productname = json_decode($rootcompanysetting[0]['setting_value']);
                        }
                        
                        /* ENHANCE */
                        if(isset($productname->enhance->name)){ // IF ROOT IS READY WITH THE ENHANCE, BUT THE CLIENT DOES NOT HAVE IT YET, THEN USE THE ROOT SETUP
                            $user->leadenhancename = isset($cs->enhance->name)?($cs->enhance->name):(isset($productname->enhance->name)?($productname->enhance->name):(null));
                        }else{ // IF ROOT NOT READY ENHANCE
                            $user->leadenhancename = null;
                        }

                        if(isset($productname->enhance->url)){ // IF ROOT IS READY WITH THE ENHANCE, BUT THE CLIENT DOES NOT HAVE IT YET, THEN USE THE ROOT SETUP
                            $user->leadenhanceurl = isset($cs->enhance->name)?($cs->enhance->url):(isset($productname->enhance->url)?($productname->enhance->url):(null));
                        }else{ // IF ROOT NOT READY ENHANCE
                            $user->leadenhanceurl = null;
                        }
                        /* ENHANCE */

                        /* B2B */
                        if(isset($productname->b2b->name)){ // IF ROOT IS READY WITH THE B2B, BUT THE CLIENT DOES NOT HAVE IT YET, THEN USE THE ROOT SETUP
                            $user->leadb2bname = isset($cs->b2b->name)?($cs->b2b->name):(isset($productname->b2b->name)?($productname->b2b->name):(null));
                        }else{ // IF ROOT NOT READY B2B
                            $user->leadb2bname = null;
                        }

                        if(isset($productname->b2b->url)){ // IF ROOT IS READY WITH THE B2B, BUT THE CLIENT DOES NOT HAVE IT YET, THEN USE THE ROOT SETUP
                            $user->leadb2burl = isset($cs->b2b->url)?($cs->b2b->url):(isset($productname->b2b->url)?($productname->b2b->url):(null));
                        }else{ // IF ROOT NOT READY B2B
                            $user->leadb2burl = null;
                        }
                        /* B2B */

                        /* SIMPLIFI */
                        if(isset($productname->simplifi->name)){ // IF ROOT IS READY WITH THE SIMPLIFI, BUT THE CLIENT DOES NOT HAVE IT YET, THEN USE THE ROOT SETUP
                            $user->leadsimplifiname = isset($cs->simplifi->name)?($cs->simplifi->name):(isset($productname->simplifi->name)?($productname->simplifi->name):(null));
                        }else{ // IF ROOT NOT READY SIMPLIFI
                            $user->leadsimplifiname = null;
                        }

                        if(isset($productname->simplifi->url)){ // IF ROOT IS READY WITH THE SIMPLIFI, BUT THE CLIENT DOES NOT HAVE IT YET, THEN USE THE ROOT SETUP
                            $user->leadsimplifiurl = isset($cs->simplifi->url)?($cs->simplifi->url):(isset($productname->simplifi->url)?($productname->simplifi->url):(null));
                        }else{ // IF ROOT NOT READY SIMPLIFI
                            $user->leadsimplifiurl = null;
                        }
                        /* SIMPLIFI */

                        /* PREDICT */
                        if(isset($productname->predict->name)){ // IF ROOT IS READY WITH THE PREDICT, BUT THE CLIENT DOES NOT HAVE IT YET, THEN USE THE ROOT SETUP
                            $user->leadpredictname = isset($cs->predict->name)?($cs->predict->name):(isset($productname->predict->name)?($productname->predict->name):(null));
                        }else{ // IF ROOT NOT READY PREDICT
                            $user->leadpredictname = null;
                        }

                        if(isset($productname->predict->url)){ // IF ROOT IS READY WITH THE PREDICT, BUT THE CLIENT DOES NOT HAVE IT YET, THEN USE THE ROOT SETUP
                            $user->leadpredicturl = isset($cs->predict->url)?($cs->predict->url):(isset($productname->predict->url)?($productname->predict->url):(null));
                        }else{ // IF ROOT NOT READY PREDICT
                            $user->leadpredicturl = null;
                        }
                        /* PREDICT */
                    }
                }
            } 
            else 
            {
                if($this->checkwhitelabellingpackage(trim($user->company_id))) 
                {
                    $companysetting = CompanySetting::where('company_id',trim($user->company_id))->whereEncrypted('setting_name','customsidebarleadmenu')->get(); // setting module agency
                    if(count($companysetting) > 0)
                    {
                        $cs = json_decode($companysetting[0]['setting_value']);
                        $user->leadlocalname = $cs->local->name;
                        $user->leadlocalurl = $cs->local->url;
                        $user->leadlocatorname = $cs->locator->name;
                        $user->leadlocatorurl = $cs->locator->url;

                        $productname = "";
                        if(count($rootcompanysetting) > 0) {
                            $productname = json_decode($rootcompanysetting[0]['setting_value']);
                        }

                        /* ENHANCE */
                        if(isset($productname->enhance->name)){ // IF ROOT IS READY WITH THE ENHANCE, BUT THE CLIENT DOES NOT HAVE IT YET, THEN USE THE ROOT SETUP
                            $user->leadenhancename = isset($cs->enhance->name)?($cs->enhance->name):(isset($productname->enhance->name)?($productname->enhance->name):(null));
                        }else{ // IF ROOT NOT READY ENHANCE
                            $user->leadenhancename = null;
                        }

                        if(isset($productname->enhance->url)){ // IF ROOT IS READY WITH THE ENHANCE, BUT THE CLIENT DOES NOT HAVE IT YET, THEN USE THE ROOT SETUP
                            $user->leadenhanceurl = isset($cs->enhance->name)?($cs->enhance->url):(isset($productname->enhance->url)?($productname->enhance->url):(null));
                        }else{ // IF ROOT NOT READY ENHANCE
                            $user->leadenhanceurl = null;
                        }
                        /* ENHANCE */

                        /* B2B */
                        if(isset($productname->b2b->name)){ // IF ROOT IS READY WITH THE B2B, BUT THE CLIENT DOES NOT HAVE IT YET, THEN USE THE ROOT SETUP
                            $user->leadb2bname = isset($cs->b2b->name)?($cs->b2b->name):(isset($productname->b2b->name)?($productname->b2b->name):(null));
                        }else{ // IF ROOT NOT READY B2B
                            $user->leadb2bname = null;
                        }

                        if(isset($productname->b2b->url)){ // IF ROOT IS READY WITH THE B2B, BUT THE CLIENT DOES NOT HAVE IT YET, THEN USE THE ROOT SETUP
                            $user->leadb2burl = isset($cs->b2b->url)?($cs->b2b->url):(isset($productname->b2b->url)?($productname->b2b->url):(null));
                        }else{ // IF ROOT NOT READY B2B
                            $user->leadb2burl = null;
                        }
                        /* B2B */

                        /* SIMPLIFI */
                        if(isset($productname->simplifi->name)){ // IF ROOT IS READY WITH THE SIMPLIFI, BUT THE CLIENT DOES NOT HAVE IT YET, THEN USE THE ROOT SETUP
                            $user->leadsimplifiname = isset($cs->simplifi->name)?($cs->simplifi->name):(isset($productname->simplifi->name)?($productname->simplifi->name):(null));
                        }else{ // IF ROOT NOT READY SIMPLIFI
                            $user->leadsimplifiname = null;
                        }

                        if(isset($productname->simplifi->url)){ // IF ROOT IS READY WITH THE SIMPLIFI, BUT THE CLIENT DOES NOT HAVE IT YET, THEN USE THE ROOT SETUP
                            $user->leadsimplifiurl = isset($cs->simplifi->url)?($cs->simplifi->url):(isset($productname->simplifi->url)?($productname->simplifi->url):(null));
                        }else{ // IF ROOT NOT READY SIMPLIFI
                            $user->leadsimplifiurl = null;
                        }
                        /* SIMPLIFI */

                        /* PREDICT */
                        if(isset($productname->predict->name)){ // IF ROOT IS READY WITH THE PREDICT, BUT THE CLIENT DOES NOT HAVE IT YET, THEN USE THE ROOT SETUP
                            $user->leadpredictname = isset($cs->predict->name)?($cs->predict->name):(isset($productname->predict->name)?($productname->predict->name):(null));
                        }else{ // IF ROOT NOT READY PREDICT
                            $user->leadpredictname = null;
                        }

                        if(isset($productname->predict->url)){ // IF ROOT IS READY WITH THE PREDICT, BUT THE CLIENT DOES NOT HAVE IT YET, THEN USE THE ROOT SETUP
                            $user->leadpredicturl = isset($cs->predict->url)?($cs->predict->url):(isset($productname->predict->url)?($productname->predict->url):(null));
                        }else{ // IF ROOT NOT READY PREDICT
                            $user->leadpredicturl = null;
                        }
                        /* PREDICT */
                    }
                }
            }
        }
        /* PRODUCT NAME AND URL */

        /* CHECK USER THIRD PARTY STATUS */
        $companyStripe = CompanyStripe::where('company_id','=',$user->company_id);
        if ($user->manual_bill == 'F') {
            $companyStripe->where('status_acc','<>','');
        }
        $companyStripe = $companyStripe->get();
        /* CHECK USER THIRD PARTY STATUS */

       /** GET STRIPE KEY */
       $stripeseckey = config('services.stripe.secret');
       $stripepublish = $this->getcompanysetting($confAppSysID,'rootstripe');
       if ($stripepublish != '') {
           $stripeseckey = (isset($stripepublish->secretkey))?$stripepublish->secretkey:"";
       }
       /** GET STRIPE KEY */

        $stripe = new StripeClient([
            'api_key' => $stripeseckey,
            'stripe_version' => '2020-08-27'
        ]);
        $user->charges_enabled = '';
        $user->payouts_enabled = '';
        $user->requirements = '';
        try {
            $chkAcc  = $stripe->accounts->retrieve(
            $companyStripe[0]->acc_connect_id,
            []
            );
            if ($chkAcc) {
                $user->charges_enabled = $chkAcc->charges_enabled;
                $user->payouts_enabled = $chkAcc->payouts_enabled;
                $user->requirements = $chkAcc->requirements;            
            }
           
        } catch (\Throwable $th) {
        }
        // CHECK USER THIRD PARTY STATUS
        

        $user->isAccExecutive = "F";

        /** CHECK IF THERE ARE ANY DOWNLINE LIST HOLD AS AE */
        if ($user->user_type == 'sales') {
            $chkAE = CompanySale::select('id')
                                ->where('sales_id','=',$user->id)
                                ->where('sales_title','=','Account Executive')
                                ->get();
            if (count($chkAE) > 0) {
                $user->isAccExecutive = "T";
            }
        }
        /** CHECK IF THERE ARE ANY DOWNLINE LIST HOLD AS AE */

        
        /** GET PACKAGE WHITELABELLING OR NON */
        $whitelabellingpackageplans = 'F';
            if( $companyStripe->count() > 0){
                if (trim($companyStripe[0]->package_id) != '') {
                    $chkPackage = PackagePlan::select('whitelabelling')
                                            ->where('package_id','=',trim($companyStripe[0]->package_id))
                                            ->get();
                    foreach($chkPackage as $chkpak) {
                            $whitelabellingpackageplans = $chkpak['whitelabelling'];
                    }
                
                } 
            }
        /** GET PACKAGE WHITELABELLING OR NON */
        

        // Check if whitelabeling exists
        if($user->user_type == 'userdownline'){
            $_companyParent = $user->company_parent;
            if ($_companyParent === null || $_companyParent) {
                $_companyParent = $user->company_root_id;
            }
            $getCompanyParent = Company::where('id','=',$_companyParent)->first();
            $getIsWhitelabelingByCompany = Company::select('is_whitelabeling')->where('id', '=', $user->company_id)->first();
            $is_whitelabeling = $getIsWhitelabelingByCompany->is_whitelabeling ? $getIsWhitelabelingByCompany->is_whitelabeling : $whitelabellingpackageplans;
            $user->sidebar_bgcolor = $is_whitelabeling == 'T' ? $user->sidebar_bgcolor : $getCompanyParent->sidebar_bgcolor;
            $user->text_color = $is_whitelabeling == 'T' ? $user->text_color : $getCompanyParent->text_color;
        }

        return $user ;
    }

    public function update(Request $request) {
        
        if ($request->profilestep == 'one') {
            return $this->save_step_one($request);
        }else if ($request->profilestep == 'two') {
            return $this->save_step_two($request);
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

    private function save_step_two($request) {
        $pictexist = "T";

        if (isset($request->id) || $request->id != "") {
            $userID = $request->id;
        } else {
            $userID = $request->user()->id;
        }

        $city = (isset($request->companycity))?$request->companycity:'';
        $zip = (isset($request->companyzip))?$request->companyzip:'';
        $country_code = (isset($request->companycountry))?$request->companycountry:'';
        $state_code = (isset($request->companystate))?$request->companystate:'';

        $phone_country_code = (isset($request->ClientPhoneCountryCode))?$request->ClientPhoneCountryCode:'';
        $phone_country_calling_code = (isset($request->ClientPhoneCountryCallingCode))?$request->ClientPhoneCountryCallingCode:'';

        $idsys = (isset($request->idsys))?$request->idsys:'';

        if (isset($request->companyphoneCountryCode)) {
            $phone_country_code = $request->companyphoneCountryCode;
            $phone_country_calling_code = $request->companyPhoneCountryCallingCode;
        }


        $companyID = $request->companyID;
        $industryID = $request->industryID;

        /** GET ROOT SYS CONF */
        $confAppDomain =  config('services.application.domain');
        if ($idsys != "") {
            $conf = $this->getCompanyRootInfo($idsys);
            $confAppDomain = $conf['domain'];
        }
        /** GET ROOT SYS CONF */
        
        /** FOR THE DOMAIN AND SUBDOMAIN */
        $DownlineSubDomain = (isset($request->DownlineSubDomain) && $request->DownlineSubDomain != '')?trim($request->DownlineSubDomain):'';
        $statusdomain = "";
        $statusdomainerror = "";

        if ($DownlineSubDomain != "") {
            $DownlineSubDomain = str_replace('http://','',$DownlineSubDomain);
            $DownlineSubDomain = trim(str_replace('https://','',$DownlineSubDomain));
            $DownlineSubDomain = $DownlineSubDomain . '.' . $confAppDomain;
            if ($companyID == '' || $companyID == 'null') {
                if ($this->check_subordomain_exist($DownlineSubDomain)) {
                    return response()->json(array('result'=>'failed','message'=>'This subdomain already exists',"picexist"=>$pictexist,"companyID"=>$companyID, "industryID"=>$industryID));
                }
            }
        }
        /** FOR THE DOMAIN AND SUBDOMAIN */

        /** FIND THE STATE NAME */
            // $stateinfo = State::select('state')
            //         ->where('state_code','=',$state_code)
            //         ->get();
        $stateName = "";
            // if(count($stateinfo) > 0) {
            //     $stateName = $stateinfo[0]['state'];
            // }
        /** FIND THE STATE NAME */

       
        /** CHECK NEW OR NOT INDUSTRY */
        if ($request->industryID == '') {
            //CREATE NEW INDUSTRY IN MODERATION
            $newIndustry = Industry::create([
                'industry_name' => $request->industryName,
                'approved' => 'F',
                'user_create_id' => $userID,
            ]);

            $industryID = $newIndustry->id;
            //CREATE NEW INDUSTRY IN MODERATION
        }
        /** CHECK NEW OR NOT INDUSTRY */

       
        $usrRoleID = '';
        $usrType = '';
        $_paymentterm_default = "Weekly";

        /** CHECK IF REALLY EMPTY COMPANY ID ON USER */
        $chkUsr = User::select('company_id','company_parent','role_id','user_type')->where('id','=',$userID)->get();
        if(count($chkUsr) > 0) {
            $usrType = $chkUsr[0]['user_type'];
            if (isset($chkUsr[0]['company_id']) && $chkUsr[0]['company_id'] != '') {
                $companyID = $chkUsr[0]['company_id'];
                $usrRoleID = $chkUsr[0]['role_id'];
            }
        }
        /** CHECK IF REALLY EMPTY COMPANY ID ON USER */

        /** CHECK NEW OR NOT COMPANY */
        //if ($request->companyID == '') {
        // if($companyID == '' || $companyID == 'null') { // mulai sekarang kalau ingin check kosong atau tidak lebih baik pakai empty() 
        if (empty($companyID) || $companyID == 'null') {
            $companylogo = '';

            /** GET DEFAULT PAYMENT TERM */
            if (($usrType != '' && $usrType == 'client') && (isset($chkUsr[0]['company_parent']) && $chkUsr[0]['company_parent'] != '')) {
                $getCompanyParentDefaultPayment = Company::select('paymentterm_default')->where('id',$chkUsr[0]['company_parent'])->first();
                if ($getCompanyParentDefaultPayment) {
                    $_paymentterm_default = $getCompanyParentDefaultPayment->paymentterm_default;
                } 
            }
            /** GET DEFAULT PAYMENT TERM */

            //if($request->hasFile('pict')) {
            //    $companylogo = $image_url;
           // }else if ($request->currpict == 'undefined' || $request->currpict == 'null') {
            if ($request->currpict == 'undefined' || $request->currpict == 'null') {
                $companylogo = '';
                $pictexist = "";
            }

            $newCompany = Company::create([
                'company_name' => $request->companyName,
                'company_address' => $request->companyaddress,
                'company_city' => $city,
                'company_zip' => $zip,
                'company_country_code' => $country_code,
                'company_state_code' => $state_code,
                'company_state_name' => $stateName,
                'phone_country_code' => $phone_country_code,
                'phone_country_calling_code' => $phone_country_calling_code,
                'phone' => $request->companyphone,
                'email' => $request->companyemail,
                'logo' => $companylogo,
                'sidebar_bgcolor' => '',
                'template_bgcolor' => '',
                'box_bgcolor' => '',
                'font_theme' => '',
                'login_image' => '',
                'client_register_image' => '',
                'agency_register_image' => '',
                'subdomain' => $DownlineSubDomain,
                'approved' => 'T',
                'user_create_id' => $userID,
                'paymentterm_default' => $_paymentterm_default,
            ]);

            $companyID = $newCompany->id;

            //SAVE CLIENT SIDE BAR
            $userclient = User::find($userID);
            if ($userclient->user_type == 'client') {
                $clientsidebar = [];
                $root_module = CompanySetting::where('company_id',trim($userclient->company_root_id))->whereEncrypted('setting_name','rootcustomsidebarleadmenu')->get();
                if (count($root_module) > 0) {
                    $rootsidebarleadmenu = json_decode($root_module[0]['setting_value']);
                    $root_module = $rootsidebarleadmenu;
                }
                $agency_default_setting = $this->getcompanysetting($userclient->company_parent, 'agencydefaultmodules');

                if (!empty($agency_default_setting) && isset($agency_default_setting->DefaultModules)) {

                    $clientsidebar_default = $agency_default_setting->DefaultModules;
                
                    // if there is AgencyDefaultModules setting, it will use it. but it will check to rootcustomsidebarleadmenu first to match the modules.
                    $clientsidebar_val = [];
                    foreach ($clientsidebar_default as $key => $value) {
                        foreach ($root_module as $key1 => $value1) {
                            if ($value->type == $key1 ) {
                                $clientsidebar_val[] = [
                                    'type' => $value->type,
                                    'status' => $value->status,
                                ];
                            }
                        }
                    }
                    $clientsidebar = [
                        "SelectedModules" => $clientsidebar_val,
                    ];

                    // jika root sudah siap dengan b2b, namun agencydefaultmodules belum ada b2b -jidan-
                    $agency_default_modules = $agency_default_setting->DefaultModules;
                    $agency_default_modules_b2b_exists = !empty(array_filter($agency_default_modules, function ($item) {
                        return $item->type === 'b2b';
                    }));

                    if(isset($root_module->b2b) && !$agency_default_modules_b2b_exists) {
                        $clientsidebar['SelectedModules'][] = [
                            'type' => 'b2b',
                            'status' => true
                        ];
                    }
                    // jika root sudah siap dengan b2b, namun agencydefaultmodules belum ada b2b -jidan-

                    // jika root sudah siap dengan simplifi, namun agencydefaultmodules belum ada simplifi -jidan-
                    $agency_default_modules_simplifi_exists = !empty(array_filter($agency_default_modules, function ($item) {
                        return $item->type === 'simplifi';
                    }));

                    if(isset($root_module->simplifi) && !$agency_default_modules_simplifi_exists) {
                        $clientsidebar['SelectedModules'][] = [
                            'type' => 'simplifi',
                            'status' => true
                        ];
                    }
                    // jika root sudah siap dengan simplifi, namun agencydefaultmodules belum ada simplifi -jidan-

                    // Force predict to always be true for clients (if root has predict) -jidan-
                    if(isset($root_module->predict)) {
                        $predictExists = false;
                        foreach ($clientsidebar['SelectedModules'] as $key => $module) {
                            if (isset($module['type']) && $module['type'] === 'predict') {
                                $clientsidebar['SelectedModules'][$key]['status'] = true; // Force status to true
                                $predictExists = true;
                                break;
                            }
                        }
                        
                        // If predict doesn't exist, add it with status true
                        if (!$predictExists) {
                            $clientsidebar['SelectedModules'][] = [
                                'type' => 'predict',
                                'status' => true
                            ];
                        }
                    }
                    // Force predict to always be true for clients (if root has predict) -jidan-
                }else {
                    $clientsidebar_val = [];
                    foreach ($root_module as $key => $value) {
                            $clientsidebar_val[] = [
                                'type' => $key,
                                'status' => true
                            ];
                    } 
                    $clientsidebar['SelectedModules'] = $clientsidebar_val;
                }
                $clientsidebar_setting = CompanySetting::where('company_id', $companyID)
                ->whereEncrypted('setting_name', 'clientsidebar')
                ->first();

                if ($clientsidebar_setting) {
                    $clientsidebar_setting->setting_value = json_encode($clientsidebar);
                    $clientsidebar_setting->save();
                } else {
                    if ($clientsidebar != []) {
                        $createsetting = CompanySetting::create([
                        'company_id' => $companyID,
                        'setting_name' => 'clientsidebar',
                        'setting_value' => json_encode($clientsidebar),
                        ]);
                    }
                }
            }
            //SAVE CLIENT SIDE BAR
            
        }else{
            
            $updateCompany = Company::find($companyID);

            $updateCompany->company_name = $request->companyName;
            $updateCompany->company_address = $request->companyaddress;
            $updateCompany->company_city = $city;
            $updateCompany->company_zip = $zip;
            $updateCompany->company_country_code = $country_code;
            $updateCompany->company_state_code = $state_code;
            $updateCompany->company_state_name = $stateName;
            $updateCompany->phone_country_code = $phone_country_code;
            $updateCompany->phone_country_calling_code = $phone_country_calling_code;
            $updateCompany->phone = $request->companyphone;
            $updateCompany->email = strtolower($request->companyemail);
            $updateCompany->subdomain = $DownlineSubDomain;
            //$updateCompany->status_domain = $statusdomain;
            //$updateCompany->status_domain_error = $statusdomainerror;
            
            $updateCompany->save();

        }
        /** CHECK NEW OR NOT COMPANY */

        /** CHECK IF ROLE USER NOT EXIST YET */
        if($usrType != 'client') {
            $rolechk = Role::where('company_id','=',$companyID)->get();
            if(count($rolechk) == 0) {
                $Role = Role::create([
                    'role_name' => 'Super Admin',
                    'role_icon' => 'fa-solid fa-key',
                    'company_id' => $companyID,
                ]);
        
                $RoleID = $Role->id;

                $modules = Module::get();
                foreach($modules as $mdl) {
                    $rolemodule = RoleModule::create([
                        'role_id' => $RoleID,
                        'module_id' => $mdl->id,
                        'create_permission' => 'T',
                        'read_permission' => 'T',
                        'update_permission' => 'T',
                        'delete_permission' => 'T',
                        'enable_permission' => 'T',
                    ]);
                }

                $usrRoleID = $RoleID;
            }
        }
        /** CHECK IF ROLE USER NOT EXIST YET */

        /** COMPANY LOGO CHECK */
        if($request->hasFile('pict')) {
            $request->validate([
               //'pict' => 'required|file|image|size:1024|dimensions:max_width=500,max_height=500',
               'pict' => 'image',
            ]);
            
            $uploadFolder = 'users/companylogo';
            $filenameWithExt = $request->file('pict')->getClientOriginalName();
            $filename = pathinfo($filenameWithExt, PATHINFO_FILENAME);
            $extension = $request->file('pict')->getClientOriginalExtension();
            $image_name = $filename.'_'. $companyID .'.'.$extension;
            $path = $request->file('pict')->storeAs($uploadFolder, $image_name,'spaces');    
            $image_url = Storage::disk('spaces')->url($path);
            $image_url = str_replace('digitaloceanspaces','cdn.digitaloceanspaces',$image_url);    

            /*if ($validator->fails()) {
            }*/

            
            //$image = $request->file('pict');
            //$OriName = $image->getClientOriginalName();

            //$image_uploaded_path = $image->storeAs($uploadFolder, $OriName);
            //$image_uploaded_path = Storage::disk('public_access')->put($uploadFolder,$image);
            //$imgURL = Storage::disk('public_access')->url($image_uploaded_path);
            $uploadedImageResponse = array(
                "result" => "success",
                "image_name" => basename($image_name), 
                "image_url" => $image_url,
                "mime" => $request->file('pict')->getClientMimeType(),
                "picexist" => $pictexist,
             );

             /** UPDATE COMPANY LOGO */
             $dbcompany = Company::find($companyID);
                $dbcompany->logo = $image_url;
             $dbcompany->save();
             /** UPDATE COMPANY LOGO */
        }
        /** COMPANY LOGO CHECK */

      


        /** UPDATE USER BUSINESS PROFILE */
        $user = User::find($userID);
            $user->industry_id = $industryID;
            $user->company_id = $companyID;
            $user->role_id = $usrRoleID;
            
            $user->franchize = ($request->franchize == 'true')?'T':'F';
            $user->franchizedirector = ($request->franchizedirector == 'true')?'T':'F';
            $user->directorfn = $request->directorfn;
            $user->directorln = $request->directorln;
            $user->directoremail = $request->directoremail;
            $user->directorphone = $request->directorphone;

            $user->officemanager = ($request->officemanager == 'true')?'T':'F';
            $user->managerfn = $request->managerfn;
            $user->managerln = $request->managerln;
            $user->manageremail = $request->manageremail;
            $user->managerphone = $request->managerphone;

            $user->team = ($request->team == 'true')?'T':'F';
            $user->teammanager = ($request->teammanager == 'true')?'T':'F';
            $user->teamfn = $request->teamfn;
            $user->teamln = $request->teamln;
            $user->teamphone = $request->teamphone;
            $user->teamemail = $request->teamemail;

        $user->save();
        /** UPDATE USER BUSINESS PROFILE */

        
        if($request->hasFile('pict')) {
            return array("result"=>"success","image_url"=>$image_url,"image_name" => basename($image_name),"mime" => $request->file('pict')->getClientMimeType(), "picexist"=>$pictexist, "companyID"=>$companyID, "industryID"=>$industryID);
        }else{
            return array("result"=>"success","image_url"=>"","picexist"=>$pictexist,"companyID"=>$companyID, "industryID"=>$industryID);
        }
    }

    private function save_step_one($request) {
        $pictexist = "T";

        if (isset($request->id) || $request->id != "") {
            $userID = $request->id;
        } else {
            $userID = $request->user()->id;
        }
       
        $city = (isset($request->city))?$request->city:'';
        $zip = (isset($request->zip))?$request->zip:'';
        $state_code = (isset($request->state))?$request->state:'';
        $country_code = (isset($request->country))?$request->country:'';
        $phone_country_code = (isset($request->ClientPhoneCountryCode))?$request->ClientPhoneCountryCode:'';
        $phone_country_calling_code = (isset($request->ClientPhoneCountryCallingCode))?$request->ClientPhoneCountryCallingCode:'';

        if (isset($request->phoneCountryCode)) {
            $phone_country_code = $request->phoneCountryCode;
            $phone_country_calling_code = $request->phoneCountryDialCode;
        }

        if ($request->id) {
           $userID = $request->id;
        }

        /** CHECK IF EMAIL ALREADY EXIST */
        $usrr = User::find($userID);
        $chkusrname = strtolower($request->email);
        if($usrr->email != $request->email){
            $idSys = $usrr->company_root_id;
            $userType = $usrr->user_type;
            $ownedcompanyid = null;
            $chkEmailExist = null;
            if ($userType == 'client') {
                $ownedcompanyid = $usrr->company_parent;
                $chkEmailExist = User::where('email',Encrypter::encrypt($chkusrname))
                                     ->where('active','T')
                                     ->where('id','<>',$userID)
                                     ->where(function ($query) use ($ownedcompanyid, $idSys) {
                                        $query->where(function ($query) use ($ownedcompanyid) { // check email di platform/domain itu sendiri, sudah dipakai oleh agency, admin agency, client belum
                                            $query->whereIn('user_type',['userdownline','user','client'])
                                                  ->where(function ($query) use ($ownedcompanyid) {
                                                        $query->where('company_id',$ownedcompanyid)
                                                              ->orWhere('company_parent',$ownedcompanyid);
                                                  });
                                        })->orWhere(function ($query) use ($idSys) { // check email di root, admin root, sales sudah dipakai atau belum
                                            $query->whereIn('user_type',['userdownline','user','sales'])
                                                  ->where('company_id',$idSys);
                                        });
                                     })
                                     ->first();
            } else {
                $ownedcompanyid = $usrr->company_id;
                $chkEmailExist = User::where('company_root_id',$idSys)
                                     ->where('email',Encrypter::encrypt($chkusrname))
                                     ->where('active','T')
                                     ->where('id','!=',$userID)
                                     ->orderByRaw( // order by company_id, company_parent, id
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
                return response()->json(array('result'=>'error_email','message'=>$messageError,'error'=>''));
                // return array("result"=>"error","message"=>"This email address is already associated with an existing account. Please log in or use a different email address.");
            }
        }
        /** CHECK IF EMAIL ALREADY EXIST */

        if($request->hasFile('pict')) {
            $request->validate([
               //'pict' => 'required|file|image|size:1024|dimensions:max_width=500,max_height=500',
               'pict' => 'image',
            ]);
            
            $uploadFolder = 'users/profile';
            $filenameWithExt = $request->file('pict')->getClientOriginalName();
            $filename = pathinfo($filenameWithExt, PATHINFO_FILENAME);
            $extension = $request->file('pict')->getClientOriginalExtension();
            $image_name = $filename.'_'. $userID .'.'.$extension;
            $path = $request->file('pict')->storeAs($uploadFolder, $image_name,'spaces');    
            $image_url = Storage::disk('spaces')->url($path);
            $image_url = str_replace('digitaloceanspaces','cdn.digitaloceanspaces',$image_url);    
            /*if ($validator->fails()) {
            }*/

            
            //$image = $request->file('pict'); 
            //$OriName = $image->getClientOriginalName();

            //$image_uploaded_path = $image->storeAs($uploadFolder, $OriName);
            //$image_uploaded_path = Storage::disk('public_access')->put($uploadFolder,$image);
            //$imgURL = Storage::disk('public_access')->url($image_uploaded_path);

            $uploadedImageResponse = array(
                "result" => "success",
                "image_name" => basename($image_name), 
                "image_url" => $image_url,
                "mime" => $request->file('pict')->getClientMimeType(),
                "picexist" => $pictexist,
             );

        }

        /** FIND THE STATE NAME */
        // $stateinfo = State::select('state')
        //         ->where('state_code','=',$state_code)
        //         ->get();
             $stateName = "";
        //     if(count($stateinfo) > 0) {
        //     $stateName = $stateinfo[0]['state'];
        //     }
        /** FIND THE STATE NAME */

        $user = User::find($userID);
            $user->name = $request->name;
            $user->email = strtolower($request->email);
            $user->phonenum = $request->phone;
            $user->phone_country_code = $phone_country_code;
            $user->phone_country_calling_code = $phone_country_calling_code;
            $user->address = $request->address;
            $user->city = $city;
            $user->zip = $zip;
            $user->country_code = $country_code;
            $user->state_code = $state_code;
            $user->state_name = $stateName;
            if($request->hasFile('pict')) {
                $user->profile_pict = $image_url;
            }else if ($request->currpict == 'undefined' || $request->currpict == 'null') {
                $user->profile_pict = '';
                $pictexist = "";
            }

        if(trim($request->newpass) == trim($request->renewpass) && trim($request->newpass) != '') {
            $user->password = Hash::make($request->newpass);
        }

        $user->save();
        
        if($request->hasFile('pict')) {
            return $uploadedImageResponse;
        }else{
            return array("result"=>"success","image_url"=>"","picexist"=>$pictexist);
        }
    }

    public function get_onboarding_status(Request $request)
    {
        $user_id = (isset($request->user_id))?$request->user_id:'';

        if(empty($user_id)){
            return response()->json(['result' => 'failed', 'status_code' => 404, 'message' => 'user_id is required', 'data' => false], 404);
        }
        
        $is_onboard_charged = false;
        $exclude_onboard_charge = false;
        $registration_method = null;

        try {
            $user = User::select('is_onboard_charged','exclude_onboard_charge', 'registration_method')
                        ->where('id', $user_id)
                        ->where('user_type', 'userdownline')
                        ->where('active', 'T')
                        ->first();

            if (isset($user->is_onboard_charged) && isset($user->exclude_onboard_charge) && (isset($user->registration_method) || $user->registration_method === null)) {
                $is_onboard_charged = (bool) $user->is_onboard_charged;
                $exclude_onboard_charge = (bool) $user->exclude_onboard_charge;
                $registration_method = $user->registration_method;
            }

            return response()->json(['result' => 'success', 'status_code' => 200, 'message' => 'Successfully get status', 'data' => $is_onboard_charged, 'data_exclude_onboard_charge' => $exclude_onboard_charge, 'registration_method' => $registration_method], 200);
        } catch (\Throwable $th) {
            return response()->json(['result' => 'failed', 'status_code' => 404, 'message' => 'user_id is required', 'data' => $is_onboard_charged, 'data_exclude_onboard_charge' => $exclude_onboard_charge, 'registration_method' => $registration_method], 404);
        }
    }
}
