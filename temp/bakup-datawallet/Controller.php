<?php

// buat di task emm 101 tanggal bulan Nov 27

namespace App\Http\Controllers;

use App\Mail\Gmail;
use App\Models\Company;
use App\Models\CompanySale;
use App\Models\CompanySetting;
use App\Models\CompanyStripe;
use App\Models\EmailNotification;
use App\Models\LeadspeekInvoice;
use App\Models\LeadspeekReport;
use App\Models\LeadspeekUser;
use App\Models\ListAndTag;
use App\Models\PackagePlan;
use App\Models\TopupAgency;
use App\Models\User;
use App\Models\UserLog;
use ESolution\DBEncryption\Encrypter;
use DateTime;
use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use stdClass;
use Stripe\Exception\ApiConnectionException;
use Stripe\Exception\ApiErrorException;
use Stripe\Exception\AuthenticationException as ExceptionAuthenticationException;
use Stripe\Exception\OAuth\InvalidRequestException;
use Stripe\Exception\RateLimitException;
use Stripe\StripeClient;
use Swift_Mailer;
use Swift_SmtpTransport;
use Swift_TransportException;

class Controller extends BaseController
{
    use AuthorizesRequests, DispatchesJobs, ValidatesRequests;
    
    Public function __construct()
    {
        date_default_timezone_set('America/Chicago');
    }
    
    public function getClientCapType($company_root_id)
    {
        $clientTypeLead = [
            'type' => '',
            'value' => ''
        ];

        $rootsetting = $this->getcompanysetting($company_root_id, 'rootsetting');

        if(isset($rootsetting->clientcaplead) && !empty($rootsetting->clientcaplead)) {
            $clientTypeLead['type'] = 'clientcaplead';
            $clientTypeLead['value'] = (isset($rootsetting->clientcaplead) && !empty($rootsetting->clientcaplead))?$rootsetting->clientcaplead:"";
        } 
        if(isset($rootsetting->clientcaplead) && !empty($rootsetting->clientcapleadpercentage)) {
            $clientTypeLead['type'] = 'clientcapleadpercentage';
            $clientTypeLead['value'] = (isset($rootsetting->clientcapleadpercentage) && !empty($rootsetting->clientcapleadpercentage))?$rootsetting->clientcapleadpercentage:"";
        }

        return $clientTypeLead;
    }

    public function generateReferralCode($userId) {
        // Hash the user ID using SHA-256
        $hash = hash('sha256', $userId);
        
        // Take the first 6 characters of the hash and convert them to uppercase
        $referralCode = strtoupper(substr($hash, 0, 6));
        
        return $referralCode;
    }
    
    public function logUserAction($userID,$action,$desc,$userIP = "") {
        /** INSERT INTO USER LOG TABLE */
        $queryUserLog = UserLog::create([
            'user_id' => $userID,
            'user_ip' => $userIP,
            'action' => $action,
            'description' => $desc,
        ]);
        /** INSERT INTO USER LOG TABLE */
    }
    
    public function searchInJSON($json, $searchKey) {
        foreach ($json as $key => $value) {
            if ($key === $searchKey) {
                return $value;
            }
            if (is_array($value) || is_object($value)) {
                $result = $this->searchInJSON($value, $searchKey);
                if ($result !== null) {
                    return $result;
                }
            }
        }
        return null;
    }

    public function send_email($sentTo = array(),$from = array(),$title='',$details = array(),$attachment = array() ,$emailtemplate = '',$companyID = '') {
        $companysetting = "";
        $smtpusername = "";
        $AdminDefaultSMTP = "";
        $AdminDefaultSMTPEmail = "";

        if ($companyID != '') {
            $getcompanysetting = CompanySetting::where('company_id',$companyID)->whereEncrypted('setting_name','customsmtpmenu')->get();      
            $AdminDefaultSMTP = $this->get_default_admin($companyID);
            $AdminDefaultSMTPEmail = (isset($AdminDefaultSMTP[0]['email']))?$AdminDefaultSMTP[0]['email']:'';

        }

        $chktemplate = false;
        $statusSender = 'defaultsmtp';
        // $templatecheck = array("emails.salesfee","emails.tryseracampaignended","emails.tryseracrawlfailed","emails.tryseraembeddedreminder","emails.tryseramatchlist","emails.tryseramatchlistcharge","emails.tryseramatchlistinvoice","emails.tryserastartstop");
        // if (in_array($emailtemplate,$templatecheck)) {
        //     $chktemplate = true;
        // }

        /** HACKED DANIEL SAID ALL EMAIL */
        $chktemplate = true;

        foreach($sentTo as $to) {
            if(trim($to) != '') {
                $smtpusername = "";

                /** CHECK IF USER EMAIL IS DISABLED TO RECEIVED EMAIL */
                
                if ($emailtemplate != "" && $chktemplate) {
                    
                    $chkdisabledemail = User::select('id')
                                            ->whereEncrypted('email','=',trim($to))
                                            ->where('active','=','T')
                                            ->where('disabled_receive_email','=','T')
                                            ->get();
                    if(count($chkdisabledemail) > 0) {
                        continue;
                    }
                }
                /** CHECK IF USER EMAIL IS DISABLED TO RECEIVED EMAIL */

                /** CHECK RECEPIENT IS CLIENT OR NOT OR OUTSIDE SYSTEM*/
                $isRecepientClient = false;
                $chkRecipient = User::select('id','user_type')
                                    ->whereEncrypted('email','=',trim($to))
                                    ->where('active','=','T')
                                    ->where('user_type','=','client')
                                    ->first();
                if ($chkRecipient) {
                    if ($chkRecipient->user_type == "client") {
                        $isRecepientClient = true;
                    }
                }

                $chkExternalEmail = User::select('id')
                                        ->whereEncrypted('email','=',trim($to))
                                        ->where('active','=','T')
                                        ->first();      
                if (!$chkExternalEmail) {
                    $isRecepientClient = true;
                }          
                /** CHECK RECEPIENT IS CLIENT OR NOT OR OUTSIDE SYSTEM*/

                try {
                    /** SET SMTP EMAIL */
                    if ($companyID != '') {
                        if (count($getcompanysetting) > 0) {
                            $companysetting = json_decode($getcompanysetting[0]['setting_value']);
                            if (!isset($companysetting->default)) {
                                $companysetting->default = false;
                            }
                            if (!$companysetting->default) {
                                $statusSender = 'agencysmtp';
                                $security = 'ssl';
                                $tmpsearch = $this->searchInJSON($companysetting,'security');

                                if ($tmpsearch !== null) {
                                    $security = $companysetting->security;
                                    if ($companysetting->security == 'none') {
                                        $security = null;
                                    }
                                }
                                
                                $transport = (new Swift_SmtpTransport(
                                    $companysetting->host, 
                                    $companysetting->port, 
                                    $security))
                                    ->setUsername($companysetting->username)
                                    ->setPassword($companysetting->password);
                    
                        
                                    $maildoll = new Swift_Mailer($transport);
                                    Mail::setSwiftMailer($maildoll);

                                    $smtpusername = (isset($companysetting->username))?$companysetting->username:'';
                                    if ($smtpusername == '') {
                                        $smtpusername = $AdminDefaultSMTPEmail;
                                    }

                            }else{

                                /** FIND ROOT DEFAULT EMAIL */
                                $_security = 'ssl';
                                $_host = config('services.defaultemail.host');
                                $_port = config('services.defaultemail.port');
                                $_usrname = config('services.defaultemail.username');
                                $_password = config('services.defaultemail.password');

                                $smtpusername = (isset($companysetting->username))?$companysetting->username:'';
                                if ($smtpusername == '') {
                                    $smtpusername = $AdminDefaultSMTPEmail;
                                }

                                if ($isRecepientClient == false) {
                                    $rootuser = User::select('company_root_id')
                                            ->where('company_id','=',$companyID)
                                            ->where('user_type','=','userdownline')
                                            ->where('active','=','T')
                                            ->get();
                                    if(count($rootuser) > 0) {
                                        $rootsmtp = CompanySetting::where('company_id',$rootuser[0]['company_root_id'])->whereEncrypted('setting_name','rootsmtp')->get();
                                        if (count($rootsmtp) > 0) {
                                            $smtproot = json_decode($rootsmtp[0]['setting_value']);
                                            $statusSender = 'rootsmtp';

                                            $security = $smtproot->security;
                                            if ($smtproot->security == 'none') {
                                                $security = null;
                                            }

                                            $_host = $smtproot->host;
                                            $_port = $smtproot->port;
                                            $_usrname = $smtproot->username;
                                            $_password = $smtproot->password;
                                            $_security = $security;

                                            $smtpusername = (isset($smtproot->username))?$smtproot->username:'';
                                            $AdminDefaultSMTPEmail = "";
                                        }
                                        /** FIND ROOT DEFAULT EMAIL */
                                    }
                                }

                                $transport = (new Swift_SmtpTransport(
                                    $_host, 
                                    $_port, 
                                    $_security))
                                    ->setUsername($_usrname)
                                    ->setPassword($_password);
                    
                        
                                    $maildoll = new Swift_Mailer($transport);
                                    Mail::setSwiftMailer($maildoll);
                                
                            }
                        }else{

                            /** FIND ROOT DEFAULT EMAIL */
                            $_security = 'ssl';
                            $_host = config('services.defaultemail.host');
                            $_port = config('services.defaultemail.port');
                            $_usrname = config('services.defaultemail.username');
                            $_password = config('services.defaultemail.password');

                            $smtpusername = $_usrname;

                            if ($isRecepientClient == false) {
                                    $rootuser = User::select('company_root_id')
                                            ->where('company_id','=',$companyID)
                                            ->where('user_type','=','userdownline')
                                            ->where('active','=','T')
                                            ->get();
                                    if(count($rootuser) > 0) {
                                        $rootsmtp = CompanySetting::where('company_id',$rootuser[0]['company_root_id'])->whereEncrypted('setting_name','rootsmtp')->get();
                                        
                                        if (count($rootsmtp) > 0) {
                                            $smtproot = json_decode($rootsmtp[0]['setting_value']);
                                            $statusSender = 'rootsmtp';
                                            
                                            $security = $smtproot->security;
                                            if ($smtproot->security == 'none') {
                                                $security = null;
                                            }
                                            
                                            $_host = $smtproot->host;
                                            $_port = $smtproot->port;
                                            $_usrname = $smtproot->username;
                                            $_password = $smtproot->password;
                                            $_security = $security;

                                            $smtpusername = (isset($smtproot->username))?$smtproot->username:'';
                                            $AdminDefaultSMTPEmail = "";
                                        }
                                        /** FIND ROOT DEFAULT EMAIL */
                                    }
                            }

                                $transport = (new Swift_SmtpTransport(
                                    $_host, 
                                    $_port, 
                                    $_security))
                                    ->setUsername($_usrname)
                                    ->setPassword($_password);
                    
                                    $maildoll = new Swift_Mailer($transport);
                                    Mail::setSwiftMailer($maildoll);
                            

                        }

                        if ($smtpusername != '') {
                             $from['address'] = $smtpusername;
                             $from['replyto'] = (isset($AdminDefaultSMTPEmail) && $AdminDefaultSMTPEmail != "")?$AdminDefaultSMTPEmail:$smtpusername;
                        }
                        
                    }else{ /** IF EMPTY */

                        $_security = 'ssl';
                        $_host = config('services.defaultemail.host');
                        $_port = config('services.defaultemail.port');
                        $_usrname = config('services.defaultemail.username');
                        $_password = config('services.defaultemail.password');
                        
                        $transport = (new Swift_SmtpTransport(
                            $_host, 
                            $_port, 
                            $_security))
                            ->setUsername($_usrname)
                            ->setPassword($_password);
            
                
                            $maildoll = new Swift_Mailer($transport);
                            Mail::setSwiftMailer($maildoll);
                    }
                    /** SET SMTP EMAIL */

                    Mail::to($to)->send(new Gmail($title,$from,$details,$emailtemplate,$attachment));
                }catch(Swift_TransportException $e) {

                    try {
                        /** FIND ROOT DEFAULT EMAIL */
                        $_security = 'ssl';
                        $_host = config('services.defaultemail.host');
                        $_port = config('services.defaultemail.port');
                        $_usrname = config('services.defaultemail.username');
                        $_password = config('services.defaultemail.password');

                        $smtpusername = $_usrname;

                        if ($statusSender == 'agencysmtp' && $isRecepientClient == false) {
                            $rootuser = User::select('company_root_id')
                                    ->where('company_id','=',$companyID)
                                    ->where('user_type','=','userdownline')
                                    ->where('active','=','T')
                                    ->get();
                            if(count($rootuser) > 0) {
                                    $rootsmtp = CompanySetting::where('company_id',$rootuser[0]['company_root_id'])->whereEncrypted('setting_name','rootsmtp')->get();
                                    if (count($rootsmtp) > 0) {
                                        $smtproot = json_decode($rootsmtp[0]['setting_value']);

                                        $security = $smtproot->security;
                                        if ($smtproot->security == 'none') {
                                            $security = null;
                                        }

                                        $_host = $smtproot->host;
                                        $_port = $smtproot->port;
                                        $_usrname = $smtproot->username;
                                        $_password = $smtproot->password;
                                        $_security = $security;

                                        $smtpusername = (isset($smtproot->username))?$smtproot->username:'';
                                        $AdminDefaultSMTPEmail = "";
                                    }
                            }
                        }
                        /** FIND ROOT DEFAULT EMAIL */

                        $transport = (new Swift_SmtpTransport(
                                    $_host, 
                                    $_port, 
                                    $_security))
                                    ->setUsername($_usrname)
                                    ->setPassword($_password);
                    
                        
                        $maildoll = new Swift_Mailer($transport);
                        Mail::setSwiftMailer($maildoll);

                        if ($smtpusername != '') {
                            $from['address'] = $smtpusername;
                            $from['replyto'] = (isset($AdminDefaultSMTPEmail) && $AdminDefaultSMTPEmail != "")?$AdminDefaultSMTPEmail:$smtpusername;
                        }
                        
                        Mail::to($to)->send(new Gmail($title,$from,$details,$emailtemplate,$attachment));

                        $this->send_email_smtp_problem_notification($companyID);
                    }catch(Swift_TransportException $e) {
                        $transport = (new Swift_SmtpTransport(
                                    config('services.defaultemail.host'), 
                                    config('services.defaultemail.port'), 
                                    'ssl'))
                                    ->setUsername(config('services.defaultemail.username'))
                                    ->setPassword(config('services.defaultemail.password'));
                    
                        
                        $smtpusername = config('services.defaultemail.username');
                        $AdminDefaultSMTPEmail = "";

                        $maildoll = new Swift_Mailer($transport);
                        Mail::setSwiftMailer($maildoll);
                        
                        if ($smtpusername != '') {
                            $from['address'] = $smtpusername;
                            $from['replyto'] = (isset($AdminDefaultSMTPEmail) && $AdminDefaultSMTPEmail != "")?$AdminDefaultSMTPEmail:$smtpusername;
                        }

                        Mail::to($to)->send(new Gmail($title,$from,$details,$emailtemplate,$attachment));

                        $this->send_email_smtp_problem_notification($companyID);
                    }


                }
            } 
        }
    }

    public function send_email_smtp_problem_notification($companyID) {
        /** FIND AGENCY INFO */
        $agencyemail = '';
        $agencyfirstname = '';
        $_user_id = '';
        $agencyinfo = User::select('id','name','email')->where('company_id','=',$companyID)->where('user_type','=','userdownline')->get();
        if (count($agencyinfo) > 0) {
            $agencyemail = $agencyinfo[0]['email'];
            $tmp = explode(' ',$agencyinfo[0]['name']);
            $agencyfirstname = $tmp[0];
            $_user_id = $agencyinfo[0]['id'];
            
            /** CHECK TO EMAIL NOTIFICATION */
            $chkemailnotif = EmailNotification::select('id','next_try',DB::raw("DATE_FORMAT(next_try, '%Y%m%d') as nexttry"))
                            ->where('user_id','=',$_user_id)
                            ->where('notification_name','smtp-problem')
                            ->get();

            $actionNotify = false;

            if (count($chkemailnotif) == 0) {
                $createEmailNotif = EmailNotification::create([
                    'user_id' => $_user_id,
                    'notification_name' => 'smtp-problem',
                    'notification_subject' => 'SMTP Email Configuration Information need attention',
                    'description' => 'email failed to send possibility because of password updated or turn on 2FA',
                    'next_try' => date('Y-m-d',strtotime(date('Y-m-d') . ' +1Days')),
                ]);

                $actionNotify = true;
            }else if (count($chkemailnotif) > 0) {
                    if ($chkemailnotif[0]['nexttry'] <= date('Ymd')) {
                        $updateEmailNotif = EmailNotification::find($chkemailnotif[0]['id']);
                        $updateEmailNotif->next_try = date('Y-m-d',strtotime(date('Y-m-d') . ' +1Days'));
                        $updateEmailNotif->save();

                        $actionNotify = true;

                    }
            }

            if ($actionNotify == true) {
                $company = Company::select('domain','subdomain')->where('id','=',$companyID)->get();

                $AdminDefault = $this->get_default_admin($companyID);
                $AdminDefaultEmail = (isset($AdminDefault[0]['email']))?$AdminDefault[0]['email']:'';

                $from = [
                    'address' => (trim($AdminDefaultEmail) != '')?trim($AdminDefaultEmail):'noreply@sitesettingsapi.com',
                    'name' => 'Support',
                    'replyto' => (trim($AdminDefaultEmail) != '')?trim($AdminDefaultEmail):'noreply@sitesettingsapi.com',
                ];

                $details = [
                    'firstname' => $agencyfirstname,
                    'urlsetting' => 'https://' . $company[0]['subdomain'] . '/configuration/general-setting',
                ];

                /** FIND THE REST OF ADMIN */
                /** FIND THE REST OF ADMIN */
                
                Mail::to($agencyemail)->send(new Gmail('Your SMTP Email Configuration need attention',$from,$details,'emails.smtptrouble',array()));
            }
            /** CHECK TO EMAIL NOTIFICATION */

        }
        
        /** FIND AGENCY INFO */
    }

    public function set_smtp_email($companyID) {
         /** GET SETTING MENU MODULE */
        $getcompanysetting = CompanySetting::where('company_id',$companyID)->whereEncrypted('setting_name','customsmtpmenu')->get();
        $companysetting = "";
        if (count($getcompanysetting) > 0) {
            $companysetting = json_decode($getcompanysetting[0]['setting_value']);
            if (!isset($companysetting->default)) {
                $companysetting->default = false;
            }

            if (!$companysetting->default) {
               $config = [
                   'driver' => 'smtp',
                   'host' => $companysetting->host,
                   'port' => $companysetting->port,
                   'encryption' => 'ssl',
                   'username' => $companysetting->username,
                   'password' => $companysetting->password,
               ];
               
               Config::set('mail',$config);
               return $companysetting->username;
           }else{

                /** FIND ROOT DEFAULT EMAIL */
                $_security = 'ssl';
                $_host = config('services.defaultemail.host');
                $_port = config('services.defaultemail.port');
                $_usrname = config('services.defaultemail.username');
                $_password = config('services.defaultemail.password');

                $rootuser = User::select('company_root_id')
                            ->where('company_id','=',$companyID)
                            ->where('user_type','=','userdownline')
                            ->where('active','=','T')
                            ->get();
                    if(count($rootuser) > 0) {
                        $rootsmtp = CompanySetting::where('company_id',$rootuser[0]['company_root_id'])->whereEncrypted('setting_name','rootsmtp')->get();
                        if (count($rootsmtp) > 0) {
                            $smtproot = json_decode($rootsmtp[0]['setting_value']);

                            $_host = $smtproot->host;
                            $_port = $smtproot->port;
                            $_usrname = $smtproot->username;
                            $_password = $smtproot->password;
                            $_security = $smtproot->security;
                        }
                    }
                /** FIND ROOT DEFAULT EMAIL */

                $config = [
                    'driver' => 'smtp',
                    'host' => $_host,
                    'port' => $_port,
                    'encryption' => $_security,
                    'username' => $_usrname,
                    'password' => $_password,
                ];
                
                Config::set('mail',$config);
                return $companysetting->username;

           }
        }else{

            /** FIND ROOT DEFAULT EMAIL */
            $_security = 'ssl';
            $_host = config('services.defaultemail.host');
            $_port = config('services.defaultemail.port');
            $_usrname = config('services.defaultemail.username');
            $_password = config('services.defaultemail.password');

            $rootuser = User::select('company_root_id')
                            ->where('company_id','=',$companyID)
                            ->where('user_type','=','userdownline')
                            ->where('active','=','T')
                            ->get();
                    if(count($rootuser) > 0) {
                        $rootsmtp = CompanySetting::where('company_id',$rootuser[0]['company_root_id'])->whereEncrypted('setting_name','rootsmtp')->get();
                        if (count($rootsmtp) > 0) {
                            $smtproot = json_decode($rootsmtp[0]['setting_value']);

                            $_host = $smtproot->host;
                            $_port = $smtproot->port;
                            $_usrname = $smtproot->username;
                            $_password = $smtproot->password;
                            $_security = $smtproot->security;
                        }

                    }
            
            /** FIND ROOT DEFAULT EMAIL */

            $config = [
                'driver' => 'smtp',
                'host' => $_host,
                'port' => $_port,
                'encryption' => $_security,
                'username' => $_usrname,
                'password' => $_password,
            ];
            
            Config::set('mail',$config);
           return "";
        }
        /** GET SETTING MENU MODULE */
    }

    public function getcompanysetting($companyID,$settingname) {

        /** GET SETTING MENU MODULE */
        $getcompanysetting = CompanySetting::where('company_id',$companyID)->whereEncrypted('setting_name',$settingname)->get();
        $companysetting = "";
        if (count($getcompanysetting) > 0) {
            $companysetting = json_decode($getcompanysetting[0]['setting_value']);
        }
        /** GET SETTING MENU MODULE */
        
        return $companysetting;

    }

    public function get_default_admin($companyID) {
        $defaultAdmin = User::where('company_id','=',$companyID)
                            ->where('isAdmin','=','T')
                            //->where('defaultadmin','=','T')
                            ->where('customercare','=','T')
                            ->get();
            
        if (count($defaultAdmin) > 0) {
            return $defaultAdmin;
        }else{
            $defaultAdmin = User::where('company_id','=',$companyID)
                            ->where('isAdmin','=','T')
                            ->where('defaultadmin','=','T')
                            ->get();
            
            if (count($defaultAdmin) > 0) {
                return $defaultAdmin;
            }else{
                $defaultAdmin = User::where('company_id','=',$companyID)
                                ->where('isAdmin','=','T')
                                ->where('user_type','=','userdownline')
                                ->get();

                if (count($defaultAdmin) > 0) {
                    return $defaultAdmin;
                }else{
                    return '';
                }
            }

        }
    }

    public function filterCustomEmail($user,$companyID,$emailContent,$newpassword = '',$subdomain='',$domain='',$campaignID='',$spreadsheeturl='') 
    {
        $company = Company::where('id','=',$companyID)->get();
        $AdminDefault = $this->get_default_admin($companyID);
        $clientCompanyName = "";
        
        if (!isset($user->name)) 
        {
            $jsonResult = json_encode($user[0]);
            $user = json_decode($jsonResult);
        }

        if (isset($user->company_id) && $user->company_id != 'null' && $user->company_id != '') 
        {
            $clientCompany = Company::where('id','=',$user->company_id)->get();
            if (count($clientCompany) > 0) 
            {
                $clientCompanyName = $clientCompany[0]['company_name'];
            }
        }
        
        if (count($company) > 0) 
        {
            if ($subdomain != '') 
            {
                $emailContent = str_replace('[company-subdomain]',$subdomain,$emailContent);
            }
            if ($domain != '') 
            {
                $emailContent = str_replace('[company-domain]',$domain,$emailContent);
            }

            $company_root_id = (isset($AdminDefault[0]['company_root_id']))?$AdminDefault[0]['company_root_id']:'';
            $rootcompany = Company::where('companies.id',$company_root_id)->first();
            $rootAdminDefault = $this->get_default_admin($company_root_id);
            $enterPriseName = isset($rootcompany->company_name)?$rootcompany->company_name:'';
            $enterPriseEmail = isset($rootAdminDefault[0]['email'])?$rootAdminDefault[0]['email']:'';
            // info('', ['rootcompany' => $rootcompany]);

            $AdminDefaultEmail = (isset($AdminDefault[0]['email']))?$AdminDefault[0]['email']:'';
            $AdminDefaultName = (isset($AdminDefault[0]['name']))?$AdminDefault[0]['name']:'';
            $tmpfname = explode(' ',$user->name);
            $ufname = $tmpfname[0];

            $searchArray = array('[client-name]','[client-firstname]','[client-email]','[client-company-name]','[company-name]','[company-domain]','[company-subdomain]','[company-email]','[company-personal-name]','[enterprise-name]','[enterprise-contact-information]');
            $replaceArray = array($user->name,$ufname,$user->email,$clientCompanyName,$company[0]['company_name'],$company[0]['domain'],$company[0]['subdomain'],$AdminDefaultEmail,$AdminDefaultName,$enterPriseName,$enterPriseEmail);
            $emailContent = str_replace($searchArray,$replaceArray,$emailContent);
            // info(['searchArray' => $searchArray,'replaceArray' => $replaceArray,]);

            if (trim($newpassword) != '') 
            {
                $emailContent = str_replace('[client-new-password]',$newpassword,$emailContent);
            }

            if (trim($campaignID) != "") 
            {
                $campaigndetails = LeadspeekUser::select('company_id','leadspeek_type','campaign_name')->where('leadspeek_api_id','=',$campaignID)->get();
                if (count($campaigndetails) > 0) 
                {
                    $customsidebarleadmenu = "";
                    $campaignModuleName = "";
                    $leadspeekType = $campaigndetails[0]['leadspeek_type'];

                    /** CHECK FIRST IF AGENCY HAVE THEIR OWN CUSTOM NAME FOR MODULE */
                    $companysetting = CompanySetting::where('company_id',trim($companyID))->whereEncrypted('setting_name','customsidebarleadmenu')->get();
                    if (count($companysetting) > 0) 
                    {
                        $customsidebarleadmenu = json_decode($companysetting[0]['setting_value']);
                        if ($leadspeekType == "local") 
                        {
                            $campaignModuleName = $customsidebarleadmenu->local->name;
                        }
                        else
                        {
                            $campaignModuleName = $customsidebarleadmenu->locator->name;
                        }
                    }
                    else
                    { /** CHECK FIRST IF AGENCY HAVE THEIR OWN CUSTOM NAME FOR MODULE */
                    
                        /** GET ROOT MODULE CUSTOM NAME */
                        $rootuser = User::select('company_root_id')
                                            ->where('company_id','=',$companyID)
                                            ->where('user_type','=','userdownline')
                                            ->where('active','=','T')
                                            ->get();
                        $rootcompanysetting = CompanySetting::where('company_id',trim($rootuser[0]['company_root_id']))->whereEncrypted('setting_name','rootcustomsidebarleadmenu')->get();
                        if (count($rootcompanysetting) > 0) 
                        {
                            $customsidebarleadmenu = json_decode($rootcompanysetting[0]['setting_value']);
                            if ($leadspeekType == "local") 
                            {
                                $campaignModuleName = $customsidebarleadmenu->local->name;
                            }
                            else
                            {
                                $campaignModuleName = $customsidebarleadmenu->locator->name;
                            }
                        }
                        /** GET ROOT MODULE CUSTOM NAME */
                    }

                    
                    $searchArray = array('[campaign-module-name]','[campaign-name]','[campaign-id]','[campaign-spreadsheet-url]');
                    $replaceArray = array($campaignModuleName,$campaigndetails[0]['campaign_name'],'#' . $campaignID,$spreadsheeturl);
                    $emailContent = str_replace($searchArray,$replaceArray,$emailContent);
                }
            }

            return $emailContent;
        }else{
            return $emailContent;
        }
    }

    public function br2nl($string){
        return preg_replace('#<br\s*/?>#i', "\n", $string);
    }

    public function check_email_template($settingname,$companyID=""){
        if (str_contains($settingname,'em_')) 
        {
            $defaultdomain = "sitesettingsapi.com";
            $_AdminFromEmail = "";
            $_AdminReplyEmail = "";

            /** CHECK SMTP DEFAULT AGENCY */
            if ($companyID != "") 
            {
                
                $getcompanysetting = CompanySetting::where('company_id',$companyID)->whereEncrypted('setting_name','customsmtpmenu')->get();
                $companysetting = "";
                if (count($getcompanysetting) > 0) 
                {
                    $companysetting = json_decode($getcompanysetting[0]['setting_value']);
                    if (!isset($companysetting->default)) 
                    {
                        $companysetting->default = false;
                    }
                    if (!$companysetting->default && trim($companysetting->username) != '') 
                    {
                        $_AdminFromEmail = $companysetting->username;
                        $tmpdomain = explode('@',$companysetting->username);
                        $defaultdomain = $tmpdomain[1];
                    }
                    else
                    {
                        $rootuser = User::select('company_root_id')
                                        ->where('company_id','=',$companyID)
                                        ->where('user_type','=','userdownline')
                                        ->where('active','=','T')
                                        ->get();
                                if(count($rootuser) > 0) 
                                {
                                    $rootsmtp = CompanySetting::where('company_id',$rootuser[0]['company_root_id'])->whereEncrypted('setting_name','rootsmtp')->get();
                                    if (count($rootsmtp) > 0) 
                                    {
                                        $smtproot = json_decode($rootsmtp[0]['setting_value']);
                                        //$_AdminFromEmail = $smtproot->username;
                                        $_AdminFromEmail = "";
                                        $tmpdomain = explode('@',$smtproot->username);
                                        //$defaultdomain = $tmpdomain[1];
                                    }
                                }
                    }
                }
                else
                {
                    $rootuser = User::select('company_root_id')
                                        ->where('company_id','=',$companyID)
                                        ->where('user_type','=','userdownline')
                                        ->where('active','=','T')
                                        ->get();
                                if(count($rootuser) > 0) 
                                {
                                    $rootsmtp = CompanySetting::where('company_id',$rootuser[0]['company_root_id'])->whereEncrypted('setting_name','rootsmtp')->get();
                                    if (count($rootsmtp) > 0) 
                                    {
                                        $smtproot = json_decode($rootsmtp[0]['setting_value']);
                                        //$_AdminFromEmail = $smtproot->username;
                                        $_AdminFromEmail = "";
                                        $tmpdomain = explode('@',$smtproot->username);
                                        //$defaultdomain = $tmpdomain[1];
                                    }
                                }
                }

            }
            /** CHECK SMTP DEFAULT AGENCY */
            
            /** CHECK CUSTOMER CARE */
            if ($_AdminFromEmail == "") 
            {
                $_AdminFromEmail = 'noreply@' . $defaultdomain;
            }
            $AdminDefault = $this->get_default_admin($companyID);
            $_AdminReplyEmail = (isset($AdminDefault[0]['email']))?$AdminDefault[0]['email']:'support@' . $defaultdomain;
            /** CHECK CUSTOMER CARE */

            $companysetting = [
                'title' =>'',
                'subject' => '',
                'content' => '',
                'fromAddress' => $_AdminFromEmail,
                'fromName' => 'Reset Password',
                'fromReplyto' => $_AdminReplyEmail,
            ];
            
            if (str_contains($settingname,'em_forgetpassword')) 
            {
                $companysetting['title'] = 'Forget password template';
                $companysetting['subject'] = 'Your password has been reset';
                $companysetting['content'] = $this->br2nl(view('emails.forgotpassword')->render());
                $companysetting['fromAddress'] = $_AdminFromEmail;
                $companysetting['fromName'] = 'Reset Password';
                $companysetting['fromReplyto'] = $_AdminReplyEmail;
            }
            else if (str_contains($settingname,'em_welcomeemail')) 
            {
                $companysetting['title'] = 'Account setup template';
                $companysetting['subject'] = 'Account Setup';
                $companysetting['content'] = $this->br2nl(view('emails.newregistration')->render());
                $companysetting['fromAddress'] = $_AdminFromEmail;
                $companysetting['fromName'] = 'Welcome';
                $companysetting['fromReplyto'] = $_AdminReplyEmail;
            }
            else if (str_contains($settingname,'em_clientwelcomeemail')) 
            {
                $companysetting['title'] = 'Account setup template';
                $companysetting['subject'] = 'Your [company-name] account setup';
                $companysetting['content'] = $this->br2nl(view('emails.newclientregister')->render());
                $companysetting['fromAddress'] = $_AdminFromEmail;
                $companysetting['fromName'] = 'Welcome';
                $companysetting['fromReplyto'] = $_AdminReplyEmail;
            }
            else if (str_contains($settingname,'em_campaigncreated')) 
            {
                $companysetting['title'] = 'Campaign create template';
                $companysetting['subject'] = '[client-company-name] - [campaign-name] [campaign-id] Google Sheet Link';
                $companysetting['content'] = $this->br2nl(view('emails.spreadsheetlink')->render());
                $companysetting['fromAddress'] = $_AdminFromEmail;
                $companysetting['fromName'] = '[campaign-module-name] Support';
                $companysetting['fromReplyto'] =  $_AdminReplyEmail;
            }
            else if (str_contains($settingname,'em_billingunsuccessful')) 
            {
                $companysetting['title'] = 'Billing Unsuccessful template';
                $companysetting['subject'] = 'Your Credit Card Failed for [campaign-name] [campaign-id]';
                $companysetting['content'] = $this->br2nl(view('emails.client_billingunsuccessful')->render());
                $companysetting['fromAddress'] = $_AdminFromEmail;
                $companysetting['fromName'] = 'Billing Support';
                $companysetting['fromReplyto'] = $_AdminReplyEmail;
            }
            else if (str_contains($settingname,'em_archivecampaign')) 
            {
                $companysetting['title'] = 'Campaign Archived template';
                $companysetting['subject'] = 'Your campaign has been archived';
                $companysetting['content'] = $this->br2nl(view('emails.client_archivecampaign')->render());
                $companysetting['fromAddress'] = $_AdminFromEmail;
                $companysetting['fromName'] = 'Campaign Archived';
                $companysetting['fromReplyto'] = $_AdminReplyEmail;
            }
            else if (str_contains($settingname,'em_agencywelcomeemail'))
            {
                $companysetting['title'] = 'Your Agency Account Setup';
                $companysetting['subject'] = 'Your Agency Account Setup';
                $companysetting['content'] = $this->br2nl(view('emails.newagencyregistration')->render());
                $companysetting['fromAddress'] = $_AdminFromEmail;
                $companysetting['fromName'] = 'Welcome';
                $companysetting['fromReplyto'] = $_AdminReplyEmail;
            }
            else if (str_contains($settingname,'em_prepaidtopuptwodaylimitclient')) 
            {
                $companysetting['title'] = 'Your Campaign Will Auto Topup In 2 Days';
                $companysetting['subject'] = 'Your Campaign [campaign-name] [campaign-id] Will Auto Topup In 2 Days';
                $companysetting['content'] = $this->br2nl(view('emails.prepaidtopuptwodaylimitclient')->render());
                $companysetting['fromAddress'] = $_AdminFromEmail;
                $companysetting['fromName'] = 'Campaign Auto Topup';
                $companysetting['fromReplyto'] = $_AdminReplyEmail;
            }
            else if(str_contains($settingname, 'em_gohighlevelmissingtagsclient')) 
            {
                $companysetting['title'] = 'Campaign Missing Tags [integration-name]';
                $companysetting['subject'] = 'Your Campaign [campaign-name] [campaign-id] Missing Tags [integration-name]';
                $companysetting['content'] = $this->br2nl(view('emails.gohighlevelmissingtagsclient')->render());
                $companysetting['fromAddress'] = $_AdminFromEmail;
                $companysetting['fromName'] = 'Campaign Missing Tags';
                $companysetting['fromReplyto'] = $_AdminReplyEmail;
            }
            else if(str_contains($settingname, 'em_gohighlevelmissingtagsagency')) 
            {
                $companysetting['title'] = 'Campaign Missing Tags [integration-name]';
                $companysetting['subject'] = 'Your Campaign [campaign-name] [campaign-id] Missing Tags [integration-name]';
                $companysetting['content'] = $this->br2nl(view('emails.gohighlevelmissingtagsagency')->render());
                $companysetting['fromAddress'] = $_AdminFromEmail;
                $companysetting['fromName'] = 'Campaign Missing Tags';
                $companysetting['fromReplyto'] = $_AdminReplyEmail;
            }
            return $companysetting;
        }
        return "";
    }

    public function check_connected_account($companyParentID,$idsys = "") {
            $accConID = '';
            $confAppSysID = config('services.application.systemid');
            if ($idsys != "") {
                $confAppSysID = $idsys;
            }

            if ($companyParentID != '' && $companyParentID != $confAppSysID) {
                $usrchk = User::select('user_type')
                                ->where('company_id','=',$companyParentID)
                                ->where('company_parent','=',$confAppSysID)
                                ->where('isAdmin','=','T')
                                ->where('active','=','T')
                                ->where('user_type','=','userdownline')
                                ->get();

                if (count($usrchk) > 0) {
                    if ($usrchk[0]['user_type'] == 'userdownline') {
                        /** CHECK PAYMENT GATEWAY TYPE */
                        $chkCompanyGateway = Company::select('paymentgateway')
                                                    ->where('id','=',$companyParentID)
                                                    ->get();
                        $paymentgateway = 'stripe';
                        if (count($chkCompanyGateway) > 0) {
                            $paymentgateway = $chkCompanyGateway[0]['paymentgateway'];
                        }
                        /** CHECK PAYMENT GATEWAY TYPE */
                        
                        $companyStripe = CompanyStripe::select('acc_connect_id')
                                                ->where('company_id','=',$companyParentID);
                        if ($paymentgateway == 'stripe') {
                            $companyStripe->where('status_acc','=','completed');
                        }
                        $companyStripe = $companyStripe->get();

                        if (count($companyStripe) > 0 && $companyStripe[0]['acc_connect_id'] != "") {
                            $accConID = $companyStripe[0]['acc_connect_id'];
                        }
                    }
                }

            }
            return $accConID;
    }

    /** FOR STRIPE THINGS */
    public function create_freesubscription($companyID,$pricingID) {
        $chkUser = User::select('id','customer_payment_id','customer_card_id','email','company_root_id')
                            ->where('company_id','=',$companyID)
                            ->where('company_parent','<>',$companyID)
                            ->where('user_type','=','userdownline')
                            ->where('isAdmin','=','T')
                            ->where('active','=','T')
                            ->get();

        if(count($chkUser) > 0) {

                $cardID = $chkUser[0]['customer_card_id'];

                /** GET STRIPE KEY */
                $stripeseckey = config('services.stripe.secret');
                $stripepublish = $this->getcompanysetting($chkUser[0]['company_root_id'],'rootstripe');
                if ($stripepublish != '') {
                    $stripeseckey = (isset($stripepublish->secretkey))?$stripepublish->secretkey:"";
                }
                /** GET STRIPE KEY */

                $stripe = new StripeClient([
                    'api_key' => $stripeseckey,
                    'stripe_version' => '2020-08-27'
                ]);

                /** GET FREE PACKAGE PLAN */
                $getfreePlan = $this->getcompanysetting($chkUser[0]['company_root_id'],'agencyplan');
                if ($getfreePlan != '') {
                    $freeplanID = (isset($getfreePlan->livemode->free))?$getfreePlan->livemode->free:"";
                    if (config('services.appconf.devmode') === true) {
                        $freeplanID = (isset($getfreePlan->testmode->free))?$getfreePlan->testmode->free:"";
                    }
                }
                /** GET FREE PACKAGE PLAN */
                
                // $freeplanID = 'price_1MBWo7Cm8XcQag44lhijmoNW';
                // if (config('services.appconf.devmode') === true) {
                //     $freeplanID = 'price_1M6v81Cm8XcQag44pvgpAf1D';
                // }
                /** CHECK IF THIS UPDATE OR CREATE */
                $companycheck = CompanyStripe::where('company_id','=',$companyID)
                                ->get();

                if($companycheck->count() > 0) {
                    if(trim($companycheck[0]->package_id) == '' && trim($companycheck[0]->subscription_id) == '') {
                        try{
                             /** CREATE SUBSCRIPTION */
                            $createSub = $stripe->subscriptions->create([
                                "customer" => trim($chkUser[0]['customer_payment_id']),
                                "items" => [
                                    ["price" => $freeplanID],
                                ],
                                "default_source" => $cardID,

                            ]);
                            /** CREATE SUBSCRIPTION */

                            /** UPDATE COMPANY STRIPE */
                            $updateCompanyStripe = CompanyStripe::find($companycheck[0]->id);
                            $updateCompanyStripe->package_id = $freeplanID;
                            $updateCompanyStripe->subscription_id = $createSub->id;
                            $updateCompanyStripe->subscription_item_id = $createSub->items->data[0]['id'];
                            $updateCompanyStripe->plan_date_created = date('Y-m-d');
                            $updateCompanyStripe->plan_next_date = date('Y-m-d',strtotime(date('Y-m-d') . ' +1 years'));
                            $updateCompanyStripe->save();
                            /** UPDATE COMPANY STRIPE */

                            return json_encode(array('result'=>'success','message'=>'','params'=>'','packageid'=>$freeplanID));
                        }catch (Exception $e) {
                            return json_encode(array('result'=>'failed','message'=>$e->getMessage(),'params'=>''));
                        }
                    }else{
                        try{
                            /** UPDATE SUBSCRIPTION */
                            $updatesub = $stripe->subscriptionItems->update(trim($companycheck[0]->subscription_item_id),[
                                "proration_behavior" => "none",
                                "price" =>  $freeplanID,
                                //"default_source" => $cardID,
                            ]);
                            /** UPDATE SUBSCRIPTION */

                            /** UPDATE COMPANY STRIPE */
                            $updateCompanyStripe = CompanyStripe::find($companycheck[0]->id);
                            $updateCompanyStripe->package_id = $freeplanID;
                            $updateCompanyStripe->plan_next_date = date('Y-m-d',strtotime(date('Y-m-d') . ' +1 years'));
                            $updateCompanyStripe->save();
                            /** UPDATE COMPANY STRIPE */

                            return json_encode(array('result'=>'success','message'=>'','params'=>'','packageid'=>$freeplanID));
                        }catch(Exception $e) {
                            return json_encode(array('result'=>'failed','message'=>$e->getMessage(),'params'=>''));
                        }
                    }

                    
                }
        }
    }

    public function create_subscription($companyID,$pricingID) {
        $chkUser = User::select('id','customer_payment_id','customer_card_id','email','company_root_id')
                            ->where('company_id','=',$companyID)
                            ->where('company_parent','<>',$companyID)
                            ->where('user_type','=','userdownline')
                            ->where('isAdmin','=','T')
                            ->where('active','=','T')
                            ->get();

        if(count($chkUser) > 0) {

            $cardID = $chkUser[0]['customer_card_id'];

            /** GET STRIPE KEY */
            $stripeseckey = config('services.stripe.secret');
            $stripepublish = $this->getcompanysetting($chkUser[0]['company_root_id'],'rootstripe');
            if ($stripepublish != '') {
                $stripeseckey = (isset($stripepublish->secretkey))?$stripepublish->secretkey:"";
            }
            /** GET STRIPE KEY */

            $stripe = new StripeClient([
                'api_key' => $stripeseckey,
                'stripe_version' => '2020-08-27'
            ]);

            /** CHECK IF THIS UPDATE OR CREATE */
            $companycheck = CompanyStripe::where('company_id','=',$companyID)
                            ->get();

             if($companycheck->count() > 0) {
                
                $whitelabellingpackage = 'F';
                $is_whitelabeling = null;

                if(trim($companycheck[0]->package_id) == '' && trim($companycheck[0]->subscription_id) == '') {
                    
                    try {
                        /** CREATE SUBSCRIPTION */
                        $createSub = $stripe->subscriptions->create([
                            "customer" => trim($chkUser[0]['customer_payment_id']),
                            //"proration_behavior" => "none",
                            "items" => [
                                ["price" => $pricingID],
                            ],
                            "default_source" => $cardID,
                            //"payment_behavior" => "default_incomplete",
                            //"expand" => ["latest_invoice.payment_intent"],
                        ]);
                        /** CREATE SUBSCRIPTION */

                        /** UPDATE COMPANY STRIPE */
                        $updateCompanyStripe = CompanyStripe::find($companycheck[0]->id);
                        $updateCompanyStripe->package_id = $pricingID;
                        $updateCompanyStripe->subscription_id = $createSub->id;
                        $updateCompanyStripe->subscription_item_id = $createSub->items->data[0]['id'];
                        $updateCompanyStripe->plan_date_created = date('Y-m-d');
                        $updateCompanyStripe->plan_next_date = date('Y-m-d',strtotime(date('Y-m-d') . ' +1 years'));
                        $updateCompanyStripe->save();
                        /** UPDATE COMPANY STRIPE */

                        /** GET PACKAGE WHITELABELLING OR NON */
                        if (trim($pricingID) != '') {
                            $chkPackage = PackagePlan::select('whitelabelling')
                                                    ->where('package_id','=',trim($pricingID))
                                                    ->get();
                            foreach($chkPackage as $chkpak) {
                                $whitelabellingpackage = $chkpak['whitelabelling'];
                            }
                        }
                        /** GET PACKAGE WHITELABELLING OR NON */

                        /** CREATE SIMPLIFI CLIENT AS AGENCY */
                        $defaultParentOrganization = config('services.sifidefaultorganization.organizationid');

                        $simplifiOrganizationID = Company::select('company_name','simplifi_organizationid')
                                                            ->where('id','=',$companyID)
                                                            ->get();

                        if(count($simplifiOrganizationID) > 0) {
                            if (trim($simplifiOrganizationID[0]['simplifi_organizationid']) == '') {
                                $sifiEMMStatus = "[AGENCY]";
                                if (config('services.appconf.devmode') === true) {
                                    $sifiEMMStatus = "[AGENCY BETA]";
                                }

                                $simplifiOrganizationID = $this->createOrganization(trim($simplifiOrganizationID[0]['company_name']) . ' ' . $sifiEMMStatus,$defaultParentOrganization);
                            }
                        }
                        // Update is_whitelabeling in company
                        $companyById = Company::find($companyID);
                        if ($companyById) {
                            $is_whitelabeling = $whitelabellingpackage;
                            $companyById->is_whitelabeling = $whitelabellingpackage;
                            $companyById->save();
                        }

                        /** CREATE SIMPLIFI CLIENT AS AGENCY */

                        return json_encode(array('result'=>'success','message'=>'','params'=>'','packagewhite'=>$whitelabellingpackage,"is_whitelabeling" => $is_whitelabeling,'plannextbill'=>date('F j, Y',strtotime(date('Y-m-d') . ' +1 years'))));
                    }catch (Exception $e) {
                        return json_encode(array('result'=>'failed','message'=>$e->getMessage(),'params'=>''));
                    }

                }else{
                    try {
                        /** UPDATE SUBSCRIPTION */
                        $updatesub = $stripe->subscriptionItems->update(trim($companycheck[0]->subscription_item_id),[
                            //"proration_behavior" => "always_invoice",
                            "price" =>  $pricingID,
                            //"default_source" => $cardID,
                        ]);
                        /** UPDATE SUBSCRIPTION */

                        /** UPDATE COMPANY STRIPE */
                        $updateCompanyStripe = CompanyStripe::find($companycheck[0]->id);
                        $updateCompanyStripe->package_id = $pricingID;
                        $updateCompanyStripe->plan_next_date = date('Y-m-d',strtotime(date('Y-m-d') . ' +1 years'));
                        $updateCompanyStripe->save();
                        /** UPDATE COMPANY STRIPE */

                        /** GET PACKAGE WHITELABELLING OR NON */
                        if (trim($pricingID) != '') {
                            $chkPackage = PackagePlan::select('whitelabelling')
                                                    ->where('package_id','=',trim($pricingID))
                                                    ->get();
                            foreach($chkPackage as $chkpak) {
                                $whitelabellingpackage = $chkpak['whitelabelling'];
                            }
                        }
                        /** GET PACKAGE WHITELABELLING OR NON */

                         // Update is_whitelabeling in company
                         $companyById = Company::find($companyID);
                         if ($companyById) {
                             $is_whitelabeling = $whitelabellingpackage;
                             $companyById->is_whitelabeling = $whitelabellingpackage;
                             $companyById->save();
                         }

                        return json_encode(array('result'=>'success','message'=>'','params'=>'','packagewhite'=>$whitelabellingpackage,"is_whitelabeling" => $is_whitelabeling,'plannextbill'=>date('Y-m-d',strtotime(date('Y-m-d') . ' +1 years'))));
                    }catch(Exception $e) {
                        return json_encode(array('result'=>'failed','message'=>$e->getMessage(),'params'=>''));
                    }

                }
            }

            /** CHECK IF THIS UPDATE OR CREATE */
        
        }
    }

    private function createOrganization($organizationName,$parentOrganization = "",$customID="") {
        $http = new Client();

        $appkey = config('services.simplifi.app_key');
        $usrkey = config('services.simplifi.usr_key');
        $apiURL = config('services.simplifi.endpoint') . "organizations";
        
        $parentID = (trim($parentOrganization) == "")?config('services.sifidefaultorganization.organizationid'):trim($parentOrganization);
        
        $organizationName = $this->makeSafeTitleName($organizationName);

        try {
            $options = [
                'headers' => [
                    'X-App-Key' => $appkey,        
                    'X-User-Key' => $usrkey,
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    "organization" =>[
                        "name" => $organizationName . ' - ' . date('His'),
                        "parent_id" => $parentID,
                        "custom_id" => $customID
                    ]
                ]
            ]; 
            
           
            $response = $http->post($apiURL,$options);
            $result =  json_decode($response->getBody());
            
            return $result->organizations[0]->id;

        }catch (\GuzzleHttp\Exception\BadResponseException $e) {
            // if ($e->getCode() === 400) {
            //     return "";
            // } else if ($e->getCode() === 401) {
            //     return "";
            // }
            $details = [
                'errormsg'  => 'Error when trying to create SIFI Organization : ' . $organizationName . ' parent ID :' . $parentID . ' (' . $e->getMessage() . ')',
            ];

            $from = [
                'address' => 'noreply@exactmatchmarketing.com',
                'name' => 'Support',
                'replyto' => 'support@exactmatchmarketing.com',
            ];
            $this->send_email(array('serverlogs@sitesettingsapi.com'),$from,'Error Log SIFI Create Organization :' . $organizationName . ' parent ID:' . $parentID . '(Apps DATA - createOrganization - Controller - L1074) ',$details,array(),'emails.tryseramatcherrorlog','');

            return "";
        }

    }

    public function send_notif_stripeerror($title,$content,$idsys = "") {

        $details = [
            'title' => $title,
            'content'  => $content,
        ];

        $attachement = array();

        $from = [
            'address' => 'noreply@exactmatchmarketing.com',
            'name' => 'Charge Error',
            'replyto' => 'support@exactmatchmarketing.com',
        ];

        $confAppSysID = config('services.application.systemid');
        if ($idsys != "") {
            $confAppSysID = $idsys;
        }

        $rootAdmin = User::select('name','email')->where('company_id','=',$confAppSysID)->where('active','T')->whereRaw("user_type IN ('user','userdownline')")->where('isAdmin','=','T')->get();

        $adminEmail = array();
        foreach($rootAdmin as $ad) {
            array_push($adminEmail,$ad['email']);
        }

        $this->send_email($adminEmail,$from,$title,$details,$attachement,'emails.customemail','');

    }

    public function transfer_commission_sales($companyParentID,$platformfee,$_leadspeek_api_id = "",$startdate = "0000-00-00",$enddate = "0000-00-00",$stripeseckey = "",$ongoingleads = "",$cleanProfit = "",$dataCustomCommissionSales = []) {
        $stripe = new StripeClient([
            'api_key' => $stripeseckey,
            'stripe_version' => '2020-08-27'
        ]);

        $srID = 0;
        $aeID = 0;
        $arID = 0;

        /* CHECK TYPE CHARGE IN COMMISSION SALES */
        // info(['dataCustomCommissionSales' => $dataCustomCommissionSales]);
        $chargeType = isset($dataCustomCommissionSales['type'])?$dataCustomCommissionSales['type']:''; // untuk mengetahui transfer commission sale itu dari invoice atau topup
        $platformPriceArray = []; // jika createInvoice, data ini untuk menampung seluruh platform_price_lead
        $platformPriceTopup = 0; // jika topup, data ini untuk menampung platformPrice topup
        $totalLeadTopup = 0; // jika topup, data ini untuk menampung totalLead topup

        if($chargeType == 'invoice') {
            $_lp_user_id = isset($dataCustomCommissionSales['_lp_user_id'])?$dataCustomCommissionSales['_lp_user_id']:'';
            $_company_id = isset($dataCustomCommissionSales['_company_id'])?$dataCustomCommissionSales['_company_id']:'';
            $_user_id = isset($dataCustomCommissionSales['_user_id'])?$dataCustomCommissionSales['_user_id']:'';
            $_leadspeek_api_id = isset($dataCustomCommissionSales['_leadspeek_api_id'])?$dataCustomCommissionSales['_leadspeek_api_id']:'';
            $startBillingDate = isset($dataCustomCommissionSales['startBillingDate'])?$dataCustomCommissionSales['startBillingDate']:'';
            $endBillingDate = isset($dataCustomCommissionSales['endBillingDate'])?$dataCustomCommissionSales['endBillingDate']:'';

            
            $platformPriceArray = LeadspeekReport::where('lp_user_id','=',$_lp_user_id)
                                                ->where('company_id','=',$_company_id)
                                                ->where('user_id','=',$_user_id)
                                                ->where('leadspeek_api_id','=',$_leadspeek_api_id)
                                                ->where('active','=','T')
                                                //->whereBetween(DB::raw('DATE_FORMAT(clickdate,"%Y-%m-%d")'),[$startBillingDate,$endBillingDate])
                                                ->where(DB::raw('DATE_FORMAT(clickdate,"%Y%m%d%H%i%s")'),'>=',$startBillingDate)
                                                ->where(DB::raw('DATE_FORMAT(clickdate,"%Y%m%d%H%i%s")'),'<=',$endBillingDate)
                                                ->pluck('platform_price_lead');
            // info('persiapan data untuk custom_commission_type, type pembayaran createInvoice', ['lp_user_id' => $_lp_user_id,'_company_id' => $_company_id,'_user_id' => $_user_id,'_leadspeek_api_id' => $_leadspeek_api_id,'lp_user_id' => $_lp_user_id,'platformPriceArray' => $platformPriceArray]);
        } else if($chargeType == 'topup') {
            $platformPriceTopup = isset($dataCustomCommissionSales['platform_price_lead'])?$dataCustomCommissionSales['platform_price_lead']:0;
            $totalLeadTopup = isset($dataCustomCommissionSales['total_lead_topup'])?$dataCustomCommissionSales['total_lead_topup']:0;
            // info('persiapan data untuk custom_commission_type, type pembayaran topup', ['platformPriceTopup' => $platformPriceTopup,'totalLeadTopup' => $totalLeadTopup,]);
        }
        /* CHECK TYPE CHARGE IN COMMISSION SALES */

         /** CHECK IF THERE ARE SALES AND ACCOUNT EXECUTIVE */
         $salt = substr(hash('sha256', env('APP_KEY')), 0, 16);
         $saleslist = CompanySale::select(
                                    DB::raw("CONVERT(AES_DECRYPT(FROM_bASE64(users.custom_commission), '" . $salt . "') USING utf8mb4) as `custom_commission`"),
                                    DB::raw("CONVERT(AES_DECRYPT(FROM_bASE64(users.custom_commission_fixed), '" . $salt . "') USING utf8mb4) as `custom_commission_fixed`"),
                                    'users.custom_commission_enabled','users.custom_commission_type','company_sales.id','company_sales.sales_id','company_sales.sales_title','users.company_root_id',
                                    DB::raw("CONVERT(AES_DECRYPT(FROM_bASE64(`company_name`), '" . $salt . "') USING utf8mb4) as `company_name`"),
                                    DB::raw("CONVERT(AES_DECRYPT(FROM_bASE64(users.acc_connect_id), '" . $salt . "') USING utf8mb4) as `accconnectid`"),
                                    DB::raw("CONVERT(AES_DECRYPT(FROM_bASE64(users.name), '" . $salt . "') USING utf8mb4) as `name`"),
                                    DB::raw("CONVERT(AES_DECRYPT(FROM_bASE64(users.email), '" . $salt . "') USING utf8mb4) as `email`"))
                                 ->join('users','company_sales.sales_id','=','users.id')
                                 ->join('companies','company_sales.company_id','=','companies.id')
                                 ->where('company_sales.company_id','=',$companyParentID)
                                 ->where('users.active','=','T')
                                 ->where('users.user_type','=','sales')
                                 ->where('users.status_acc','=','completed')
                                 ->get();

        /** CHECK DEFAULT SALES COMMISSION */
        $AgencyPercentageCommission = 0.05;
        if (count($saleslist) > 0) {
            $rootAgencyPercentageCommission = $this->getcompanysetting($saleslist[0]['company_root_id'],'rootsetting');
            if ($rootAgencyPercentageCommission != '') {
                if(isset($rootAgencyPercentageCommission->defaultagencypercentagecommission) && $rootAgencyPercentageCommission->defaultagencypercentagecommission != "") {
                    $AgencyPercentageCommission = $rootAgencyPercentageCommission->defaultagencypercentagecommission;
                }
            }
        }
        /** CHECK DEFAULT SALES COMMISSION */

        $salesfee = ($platformfee * (float) $AgencyPercentageCommission);
        //$salesfee = ($cleanProfit != "")?($cleanProfit * (float) $AgencyPercentageCommission):$salesfee;
        $salesfee = number_format($salesfee,2,'.','');

        // info('hitung salesfee pertama kali', ['platformfee' => $platformfee,'AgencyPercentageCommission' => $AgencyPercentageCommission,'salesfee' => $salesfee,]);
        if (count($saleslist) > 0 && $platformfee > 0 && $salesfee > 0) {

            /** GET OTHER DETAILS */
            $_campaign_name = "";
            $_client_name = "";
            $_leadspeek_type = "";

            $campaigndetails = LeadspeekUser::select('leadspeek_users.campaign_name','companies.company_name','leadspeek_users.leadspeek_type')
                                            ->join('users','leadspeek_users.user_id','=','users.id')
                                            ->join('companies','users.company_id','=','companies.id')
                                            ->where('leadspeek_users.leadspeek_api_id','=',$_leadspeek_api_id)
                                            ->get();
            if (count($campaigndetails) > 0) {
                $_campaign_name = $campaigndetails[0]['campaign_name'];
                $_client_name = $campaigndetails[0]['company_name'];
                $_leadspeek_type = $campaigndetails[0]['leadspeek_type'];
            }
            /** GET OTHER DETAILS */
            
            foreach($saleslist as $sale) {

                $overrideCommission = array();

                $salesfee = ($platformfee * (float) $AgencyPercentageCommission);
                //$salesfee = ($cleanProfit != "")?($cleanProfit * (float) $AgencyPercentageCommission):$salesfee;
                $salesfee = number_format($salesfee,2,'.','');

                /** OVERRIDE THE COMMISSION IF ENABLED */
                if ($sale['custom_commission_enabled'] == 'T' && $ongoingleads != "") {
                    if($sale['custom_commission_type'] == 'fixed') {
                        $chkCommissionOverride = json_decode($sale['custom_commission_fixed']);
                        // info('pakai cara custom_commission_fixed', ['chkCommissionOverride' => $chkCommissionOverride]);

                        if($_leadspeek_type == 'local') {
                            // sales representative siteid
                            if(isset($chkCommissionOverride->sr->siteid) && $chkCommissionOverride->sr->siteid > 0 && $chkCommissionOverride->sr->siteid != '') {
                                $calculateCommission_srSiteID = 0;

                                if($chargeType == 'invoice') {
                                    foreach($platformPriceArray as $item) {
                                        if($item > $chkCommissionOverride->sr->siteid) {
                                            $calculateCommission_srSiteID += ($item - $chkCommissionOverride->sr->siteid);    
                                        }
                                    }
                                } else if($chargeType == 'topup' && $platformPriceTopup > $chkCommissionOverride->sr->siteid) {
                                    $calculateCommission_srSiteID = ($platformPriceTopup - $chkCommissionOverride->sr->siteid) * $totalLeadTopup;
                                }

                                $overrideCommission['srSiteID'] = $calculateCommission_srSiteID;

                                // info("overrideCommission srSiteID if akhir", ['chargeType' => $chargeType,'srSiteID' => $overrideCommission['srSiteID'] ?? "masih kosong",'chkCommissionOverride->sr->siteid' => $chkCommissionOverride->sr->siteid,'platformPriceTopup' => $platformPriceTopup,'totalLeadTopup' => $totalLeadTopup,]);
                            } else {
                                $overrideCommission['srSiteID'] = $salesfee;
                                // info("overrideCommission srSiteID else", ['srSiteID' => $overrideCommission['srSiteID'] ?? "masih kosong",'salesfee' => $salesfee]);
                            }
                            // sales representative siteid

                            // account executive siteid
                            if(isset($chkCommissionOverride->ae->siteid) && $chkCommissionOverride->ae->siteid > 0 && $chkCommissionOverride->ae->siteid != '') {
                                $calculateCommission_aeSiteID = 0;

                                if($chargeType == 'invoice') {
                                    foreach($platformPriceArray as $item) {
                                        if($item > $chkCommissionOverride->ae->siteid) {
                                            $calculateCommission_aeSiteID += ($item - $chkCommissionOverride->ae->siteid);    
                                        }
                                    }
                                } else if($chargeType == 'topup' && $platformPriceTopup > $chkCommissionOverride->ae->siteid) {
                                    $calculateCommission_aeSiteID = ($platformPriceTopup - $chkCommissionOverride->ae->siteid) * $totalLeadTopup;
                                }

                                $overrideCommission['aeSiteID'] = $calculateCommission_aeSiteID;

                                // info("overrideCommission aeSiteID if end", ['chargeType' => $chargeType,'aeSiteID' => $overrideCommission['srSiteID'] ?? "masih kosong",'chkCommissionOverride->ae->siteid' => $chkCommissionOverride->ae->siteid,'platformPriceTopup' => $platformPriceTopup,'totalLeadTopup' => $totalLeadTopup,]);
                            } else {
                                $overrideCommission['aeSiteID'] = $salesfee;
                                // info("overrideCommission aeSiteID else", ['aeSiteID' => $overrideCommission['aeSiteID'] ?? "masih kosong",'salesfee' => $salesfee]);
                            }
                            // account executive siteid

                            // account referral siteid, ini yang akan dipakai
                            if(isset($chkCommissionOverride->ar->siteid) && $chkCommissionOverride->ar->siteid > 0 && $chkCommissionOverride->ar->siteid != '') {
                                $calculateCommission_arSiteID  = 0;

                                if($chargeType == 'invoice') {
                                    foreach($platformPriceArray as $item) {
                                        if($item > $chkCommissionOverride->ar->siteid) {
                                            $calculateCommission_arSiteID += ($item - $chkCommissionOverride->ar->siteid);
                                        }
                                    }  
                                } else if($chargeType == 'topup' && $platformPriceTopup > $chkCommissionOverride->ar->siteid) {
                                    $calculateCommission_arSiteID = ($platformPriceTopup - $chkCommissionOverride->ar->siteid) * $totalLeadTopup;
                                }

                                $overrideCommission['arSiteID'] = $calculateCommission_arSiteID;
                                
                                // info("overrideCommission arSiteID if end", ['chargeType' => $chargeType,'arSiteID' => isset($overrideCommission['arSiteID'])?$overrideCommission['arSiteID']:'','chkCommissionOverride->ar->siteid' => $chkCommissionOverride->ar->siteid,'platformPriceTopup' => $platformPriceTopup,'totalLeadTopup' => $totalLeadTopup,]);
                            } else {
                                $overrideCommission['arSiteID'] = $salesfee;
                                // info("overrideCommission arSiteID else", ['arSiteID' => $overrideCommission['arSiteID'] ?? "masih kosong",'salesfee' => $salesfee]);
                            }
                            // account referral siteid, ini yang akan dipakai
                        } else if($_leadspeek_type == 'locator') {
                            // sales representative searchid
                            if(isset($chkCommissionOverride->sr->searchid) && $chkCommissionOverride->sr->searchid > 0 && $chkCommissionOverride->sr->searchid != '') {
                                $calculateCommission_srSearchID = 0;
                                
                                if($chargeType == 'invoice') {
                                    foreach($platformPriceArray as $item) {
                                        if($item > $chkCommissionOverride->sr->searchid) {
                                            $calculateCommission_srSearchID += ($item - $chkCommissionOverride->sr->searchid);
                                        }
                                    }
                                } else if($chargeType == 'topup' && $platformPriceTopup > $chkCommissionOverride->sr->searchid) {
                                    $calculateCommission_srSearchID = ($platformPriceTopup - $chkCommissionOverride->sr->searchid) * $totalLeadTopup;
                                }

                                $overrideCommission['srSearchID'] = $calculateCommission_srSearchID;
                                // info("overrideCommission srSearchID if end", ['chargeType' => $chargeType,'srSearchID' => $overrideCommission['srSearchID'] ?? "masih kosong",'chkCommissionOverride->sr->searchid' => $chkCommissionOverride->sr->searchid,'platformPriceTopup' => $platformPriceTopup,'totalLeadTopup' => $totalLeadTopup,]);
                            } else {
                                $overrideCommission['srSearchID'] = $salesfee;
                                // info("overrideCommission srSearchID else", ['srSearchID' => $overrideCommission['srSearchID'] ?? "masih kosong",'salesfee' => $salesfee]);
                            }
                            // sales representative searchid

                            // account executive searchid
                            if(isset($chkCommissionOverride->ae->searchid) && $chkCommissionOverride->ae->searchid > 0 && $chkCommissionOverride->ae->searchid != '') {
                                $calculateCommission_aeSearchID = 0;

                                if($chargeType == 'invoice') {
                                    foreach($platformPriceArray as $item) {
                                        if($item > $chkCommissionOverride->ae->searchid) {
                                            $calculateCommission_aeSearchID += ($item - $chkCommissionOverride->ae->searchid);
                                        }
                                    }
                                } else if($chargeType == 'topup' && $platformPriceTopup > $chkCommissionOverride->ae->searchid) {
                                    $calculateCommission_aeSearchID = ($platformPriceTopup - $chkCommissionOverride->ae->searchid) * $totalLeadTopup;
                                }

                                $overrideCommission['aeSearchID'] = $calculateCommission_aeSearchID;
                                // info("overrideCommission aeSearchID if end", ['chargeType' => $chargeType,'aeSearchID' => $overrideCommission['aeSearchID'] ?? "masih kosong",'chkCommissionOverride->ae->searchid' => $chkCommissionOverride->ae->searchid,'platformPriceTopup' => $platformPriceTopup,'totalLeadTopup' => $totalLeadTopup,]);
                            } else {
                                $overrideCommission['aeSearchID'] = $salesfee;
                                // info("overrideCommission aeSearchID else", ['aeSearchID' => $overrideCommission['aeSearchID'] ?? "masih kosong",'salesfee' => $salesfee]);
                            }
                            // account executive searchid

                            // account reveral searchid, ini yang akan dipakai
                            if(isset($chkCommissionOverride->ar->searchid) && $chkCommissionOverride->ar->searchid > 0 && $chkCommissionOverride->ar->searchid != '') {
                                $calculateCommission_arSearchID = 0;

                                if($chargeType == 'invoice') {
                                    foreach($platformPriceArray as $item) {
                                        if($item > $chkCommissionOverride->ar->searchid) {
                                            $calculateCommission_arSearchID += ($item - $chkCommissionOverride->ar->searchid);
                                        }
                                    }
                                } else if($chargeType == 'topup' && $platformPriceTopup > $chkCommissionOverride->ar->searchid) {
                                    $calculateCommission_arSearchID = ($platformPriceTopup - $chkCommissionOverride->ar->searchid) * $totalLeadTopup;
                                }

                                $overrideCommission['arSearchID'] = $calculateCommission_arSearchID;
                                // info("overrideCommission arSearchID if end", ['chargeType' => $chargeType,'arSearchID' => $overrideCommission['arSearchID'] ?? "masih kosong",'chkCommissionOverride->ar->searchid' => $chkCommissionOverride->ar->searchid,'platformPriceTopup' => $platformPriceTopup,'totalLeadTopup' => $totalLeadTopup,]);
                            } else {
                                $overrideCommission['arSearchID'] = $salesfee;
                                // info("overrideCommission arSearchID else", ['arSearchID' => $overrideCommission['arSearchID'] ?? "masih kosong",'salesfee' => $salesfee]);
                            }
                            // account executive searchid, ini yang akan dipakai
                        } else if($_leadspeek_type == 'enhance') {
                            // sales representative enhance
                            if(isset($chkCommissionOverride->sr->enhanceid) && $chkCommissionOverride->sr->enhanceid > 0 && $chkCommissionOverride->sr->enhanceid != '') {
                                $calculateCommission_srEnhanceID = 0;

                                if($chargeType == 'invoice') {
                                    foreach($platformPriceArray as $item) {
                                        if($item > $chkCommissionOverride->sr->enhanceid) {
                                            $calculateCommission_srEnhanceID += ($item - $chkCommissionOverride->sr->enhanceid);
                                        }
                                    }
                                } else if($chargeType == 'topup' && $platformPriceTopup > $chkCommissionOverride->sr->enhanceid) {
                                    $calculateCommission_srEnhanceID = ($platformPriceTopup - $chkCommissionOverride->sr->enhanceid) * $totalLeadTopup;
                                } 

                                $overrideCommission['srEnhanceID'] = $calculateCommission_srEnhanceID;
                                // info("overrideCommission srEnhanceID if end", ['chargeType' => $chargeType,'srEnhanceID' => $overrideCommission['srEnhanceID'] ?? "masih kosong",'chkCommissionOverride->sr->enhanceid' => $chkCommissionOverride->sr->enhanceid,'platformPriceTopup' => $platformPriceTopup,'totalLeadTopup' => $totalLeadTopup,]);
                            } else {
                                $overrideCommission['srEnhanceID'] = $salesfee;
                                // info("overrideCommission srEnhanceID else", ['arSearchID' => $overrideCommission['srEnhanceID'] ?? "masih kosong",'salesfee' => $salesfee]);
                            }
                            // sales representative enhance

                            // account executive enhance
                            if(isset($chkCommissionOverride->ae->enhanceid) && $chkCommissionOverride->ae->enhanceid > 0 && $chkCommissionOverride->ae->enhanceid != '') {
                                $calculateCommission_aeEnhanceID = 0;

                                if($chargeType == 'invoice') {
                                    foreach($platformPriceArray as $item) {
                                        if($item > $chkCommissionOverride->ae->enhanceid) {
                                            $calculateCommission_aeEnhanceID += ($item - $chkCommissionOverride->ae->enhanceid);
                                        }
                                    }
                                } else if($chargeType == 'topup' && $platformPriceTopup > $chkCommissionOverride->ae->enhanceid) {
                                    $calculateCommission_aeEnhanceID = ($platformPriceTopup - $chkCommissionOverride->ae->enhanceid) * $totalLeadTopup;
                                }

                                $overrideCommission['aeEnhanceID'] = $calculateCommission_aeEnhanceID;
                                // info("overrideCommission aeEnhanceID if end", ['chargeType' => $chargeType,'aeEnhanceID' => $overrideCommission['aeEnhanceID'] ?? "masih kosong",'chkCommissionOverride->ae->enhanceid' => $chkCommissionOverride->ae->enhanceid,'platformPriceTopup' => $platformPriceTopup,'totalLeadTopup' => $totalLeadTopup,]);
                            } else {
                                $overrideCommission['aeEnhanceID'] = $salesfee;
                                // info("overrideCommission aeEnhanceID else", ['aeEnhanceID' => $overrideCommission['aeEnhanceID'] ?? "masih kosong",'salesfee' => $salesfee]);
                            }
                            // account executive enhance

                            // account reveral enhance
                            if(isset($chkCommissionOverride->ar->enhanceid) && $chkCommissionOverride->ar->enhanceid > 0 && $chkCommissionOverride->ar->enhanceid != '') {
                                $calculateCommission_arEnhanceID = 0;

                                if($chargeType == 'invoice') {
                                    foreach($platformPriceArray as $item) {
                                        if($item > $chkCommissionOverride->ar->enhanceid) {
                                            $calculateCommission_arEnhanceID += ($item - $chkCommissionOverride->ar->enhanceid);
                                        }
                                    }
                                } else if($chargeType == 'topup' && $platformPriceTopup > $chkCommissionOverride->ar->enhanceid) {
                                    $calculateCommission_arEnhanceID = ($platformPriceTopup - $chkCommissionOverride->ar->enhanceid) * $totalLeadTopup;;
                                }

                                $overrideCommission['arEnhanceID'] = $calculateCommission_arEnhanceID;
                                // info("overrideCommission arEnhanceID if end", ['chargeType' => $chargeType,'arEnhanceID' => $overrideCommission['arEnhanceID'] ?? "masih kosong",'chkCommissionOverride->ar->enhanceid' => $chkCommissionOverride->ar->enhanceid,'platformPriceTopup' => $platformPriceTopup,'totalLeadTopup' => $totalLeadTopup,]);
                            } else {
                                $overrideCommission['arEnhanceID'] = $salesfee;
                                // info("overrideCommission arEnhanceID else", ['arEnhanceID' => $overrideCommission['arEnhanceID'] ?? "masih kosong",'salesfee' => $salesfee]);
                            }
                            // account executive enhance
                        }
                    } else {
                        $chkCommissionOverride = json_decode($sale['custom_commission']);
                        // info('pakai cara custom_commission', ['chkCommissionOverride' => $chkCommissionOverride]);
                        if ($_leadspeek_type == 'local') {
                            $overrideCommission['srSiteID'] = (isset($chkCommissionOverride->sr->siteid) && $chkCommissionOverride->sr->siteid > 0)?$platformfee * (float) $chkCommissionOverride->sr->siteid:$salesfee;
                            $overrideCommission['aeSiteID'] = (isset($chkCommissionOverride->ae->siteid) && $chkCommissionOverride->ae->siteid > 0)?$platformfee * (float) $chkCommissionOverride->ae->siteid:$salesfee;
                            $overrideCommission['arSiteID'] = (isset($chkCommissionOverride->ar->siteid) && $chkCommissionOverride->ar->siteid > 0)?$platformfee * (float) $chkCommissionOverride->ar->siteid:$salesfee;
                            // info('', ['_leadspeek_type' => $_leadspeek_type,'srSiteID' => $overrideCommission['srSiteID'],'aeSiteID' => $overrideCommission['aeSiteID'],'arSiteID' => $overrideCommission['arSiteID'],]);
                        }else if ($_leadspeek_type == 'locator') {
                            $overrideCommission['srSearchID'] = (isset($chkCommissionOverride->sr->searchid) && $chkCommissionOverride->sr->searchid > 0)?$platformfee * (float) $chkCommissionOverride->sr->searchid:$salesfee;
                            $overrideCommission['aeSearchID'] = (isset($chkCommissionOverride->ae->searchid) && $chkCommissionOverride->ae->searchid > 0)?$platformfee * (float) $chkCommissionOverride->ae->searchid:$salesfee;
                            $overrideCommission['arSearchID'] = (isset($chkCommissionOverride->ar->searchid) && $chkCommissionOverride->ar->searchid > 0)?$platformfee * (float) $chkCommissionOverride->ar->searchid:$salesfee;
                            // info('', ['_leadspeek_type' => $_leadspeek_type,'srSearchID' => $overrideCommission['srSearchID'],'aeSearchID' => $overrideCommission['aeSearchID'],'arSearchID' => $overrideCommission['arSearchID'],]);
                        }else if ($_leadspeek_type == 'enhance') {
                            $overrideCommission['srEnhanceID'] = (isset($chkCommissionOverride->sr->enhanceid) && $chkCommissionOverride->sr->enhanceid > 0)?$platformfee * (float) $chkCommissionOverride->sr->enhanceid:$salesfee;
                            $overrideCommission['aeEnhanceID'] = (isset($chkCommissionOverride->ae->enhanceid) && $chkCommissionOverride->ae->enhanceid > 0)?$platformfee * (float) $chkCommissionOverride->ae->enhanceid:$salesfee;
                            $overrideCommission['arEnhanceID'] = (isset($chkCommissionOverride->ar->enhanceid) && $chkCommissionOverride->ar->enhanceid > 0)?$platformfee * (float) $chkCommissionOverride->ar->enhanceid:$salesfee;
                            // info('', ['_leadspeek_type' => $_leadspeek_type,'srEnhanceID' => $overrideCommission['srEnhanceID'],'aeEnhanceID' => $overrideCommission['aeEnhanceID'],'aeEnhanceID' => $overrideCommission['aeEnhanceID'],]);
                        }
                    }
                }
                /** OVERRIDE THE COMMISSION IF ENABLED */

                /** RETRIVE BALANCE */
                    $balance = $stripe->balance->retrieve([]);
                    $currbalance = $balance->available[0]->amount / 100;
                    $currbalance = number_format($currbalance,2,'.','');
                /** RETRIVE BALANCE */

                if ($currbalance >= $salesfee) {
                    $tmp = explode(" ",$sale['name']);

                    $details = [
                        'firstname' => $tmp[0],
                        'salesfee'  => $salesfee,
                        'companyname' =>  $sale['company_name'],
                        'clientname' => $_client_name,
                        'campaignname' =>  $_campaign_name,
                        'campaignid' =>$_leadspeek_api_id,
                        'start' => date('Y-m-d H:i:s',strtotime($startdate)),
                        'end' => date('Y-m-d H:i:s',strtotime($enddate)),
                    ];
                    $attachement = array();
        
                    $from = [
                        'address' => 'noreply@exactmatchmarketing.com',
                        'name' => 'Commission Fee',
                        'replyto' => 'support@exactmatchmarketing.com',
                    ];
                    
                    if ($sale['sales_title'] == "Sales Representative") {
                        try {

                            /** CHECK IF OVERRIDE COMMISSION ENABLED */
                            if ($sale['custom_commission_enabled'] == 'T' && $ongoingleads != "") {
                                if ($_leadspeek_type == 'local') {
                                    $salesfee = (isset($overrideCommission['srSiteID']))?number_format($overrideCommission['srSiteID'],2,'.',''):$salesfee;
                                    $details['salesfee'] = $salesfee;
                                }else if ($_leadspeek_type == 'locator') {
                                    $salesfee = (isset($overrideCommission['srSearchID']))?number_format( $overrideCommission['srSearchID'],2,'.',''):$salesfee;
                                    $details['salesfee'] = $salesfee;
                                }else if ($_leadspeek_type == 'enhance') {
                                    $salesfee = (isset($overrideCommission['srEnhanceID']))?number_format( $overrideCommission['srEnhanceID'],2,'.',''):$salesfee;
                                    $details['salesfee'] = $salesfee;
                                }
                            }
                            /** CHECK IF OVERRIDE COMMISSION ENABLED */

                            // info('Fee Sales Representative', ['_leadspeek_type' => $_leadspeek_type,'salesfee' => $salesfee]);
                           if ($salesfee > 0) {
                                // info('process transfer Sales Representative', [
                                //     'amount' => ($salesfee * 100),
                                //     'currency' => 'usd',
                                //     'destination' => $sale['accconnectid'],
                                //     'description' => 'Commision from ' . $sale['company_name'] . '-' . $_client_name  . '-' . $_campaign_name . ' #' . $_leadspeek_api_id,
                                // ]);

                                $transferSales = $stripe->transfers->create([
                                    'amount' => ($salesfee * 100),
                                    'currency' => 'usd',
                                    'destination' => $sale['accconnectid'],
                                    'description' => 'Commision from ' . $sale['company_name'] . '-' . $_client_name  . '-' . $_campaign_name . ' #' . $_leadspeek_api_id,
                                ]);

                                if (isset($transferSales->destination_payment)) {
                                    $despay = $transferSales->destination_payment;

                                    $transferSalesDesc =  $stripe->charges->update($despay,
                                            [
                                                'description' => 'Commision from ' . $sale['company_name'] . '-' . $_client_name  . '-' . $_campaign_name . ' #' . $_leadspeek_api_id,
                                            ],['stripe_account' => $sale['accconnectid']]);
                                }
                                
                                $srID = $sale['sales_id'];
                                $this->send_email(array($sale['email']),$from,'Commission fee from ' . $sale['company_name'] . ' #' . $_leadspeek_api_id . '(SR)',$details,$attachement,'emails.salesfee',$sale['company_root_id']);
                            }

                        }catch (Exception $e) {
                            //return json_encode(array('result'=>'failed','payment_intentID'=>'','srID'=>'','aeID'=>'','salesfee'=>'','error'=>'SE error transfer'));
                            $this->send_notif_stripeerror('SE error transfer','SE error transfer for ' . $sale['company_name'] . ' #' . $_leadspeek_api_id,$sale['company_root_id']);
                        }

                    }else if ($sale['sales_title'] == "Account Executive") {
                        try {

                            /** CHECK IF OVERRIDE COMMISSION ENABLED */
                            if ($sale['custom_commission_enabled'] == 'T' && $ongoingleads != "") {
                                if ($_leadspeek_type == 'local') {
                                    $salesfee = (isset($overrideCommission['aeSiteID']))?number_format( $overrideCommission['aeSiteID'],2,'.',''):$salesfee;
                                    $details['salesfee'] = $salesfee;
                                }else if ($_leadspeek_type == 'locator') {
                                    $salesfee = (isset($overrideCommission['aeSearchID']))?number_format( $overrideCommission['aeSearchID'],2,'.',''):$salesfee;
                                    $details['salesfee'] = $salesfee;
                                }else if ($_leadspeek_type == 'enhance') {
                                    $salesfee = (isset($overrideCommission['aeEnhanceID']))?number_format( $overrideCommission['aeEnhanceID'],2,'.',''):$salesfee;
                                    $details['salesfee'] = $salesfee;
                                }
                            }
                            /** CHECK IF OVERRIDE COMMISSION ENABLED */

                            // info('Fee Account Executive', ['_leadspeek_type' => $_leadspeek_type,'salesfee' => $salesfee]);
                            if ($salesfee > 0) {
                                // info('process transfer Account Executive', [
                                //     'amount' => ($salesfee * 100),
                                //     'currency' => 'usd',
                                //     'destination' => $sale['accconnectid'],
                                //     'description' => 'Commision from ' . $sale['company_name'] . '-' . $_client_name  . '-' . $_campaign_name . ' #' . $_leadspeek_api_id,
                                // ]);

                                $transferSales = $stripe->transfers->create([
                                    'amount' => ($salesfee * 100),
                                    'currency' => 'usd',
                                    'destination' => $sale['accconnectid'],
                                    'description' => 'Commision from ' . $sale['company_name'] . '-' . $_client_name  . '-' . $_campaign_name . ' #' . $_leadspeek_api_id,
                                ]);

                                if (isset($transferSales->destination_payment)) {
                                    $despay = $transferSales->destination_payment;

                                    $transferSalesDesc =  $stripe->charges->update($despay,
                                            [
                                                'description' => 'Commision from ' . $sale['company_name'] . '-' . $_client_name  . '-' . $_campaign_name . ' #' . $_leadspeek_api_id,
                                            ],['stripe_account' => $sale['accconnectid']]);
                                }

                                $aeID = $sale['sales_id'];
                                $this->send_email(array($sale['email']),$from,'Commission fee from ' . $sale['company_name'] . ' #' . $_leadspeek_api_id . '(AE)',$details,$attachement,'emails.salesfee',$sale['company_root_id']);
                            }

                        }catch (Exception $e) {
                            //return json_encode(array('result'=>'failed','payment_intentID'=>'','srID'=>'','aeID'=>'','salesfee'=>'','error'=>'AE error transfer'));
                            $this->send_notif_stripeerror('AE error transfer','AE error transfer for ' . $sale['company_name'] . ' #' . $_leadspeek_api_id,$sale['company_root_id']);
                        }
                        
                    }else if ($sale['sales_title'] == "Account Referral") {
                        try {

                            /** CHECK IF OVERRIDE COMMISSION ENABLED */
                            if ($sale['custom_commission_enabled'] == 'T' && $ongoingleads != "") {
                                if ($_leadspeek_type == 'local') {
                                    $salesfee = (isset($overrideCommission['arSiteID']))?number_format( $overrideCommission['arSiteID'],2,'.',''):$salesfee;
                                    $details['salesfee'] = $salesfee;
                                }else if ($_leadspeek_type == 'locator') {
                                    $salesfee = (isset($overrideCommission['arSearchID']))?number_format( $overrideCommission['arSearchID'],2,'.',''):$salesfee;
                                    $details['salesfee'] = $salesfee;
                                }else if ($_leadspeek_type == 'enhance') {
                                    $salesfee = (isset($overrideCommission['arEnhanceID']))?number_format( $overrideCommission['arEnhanceID'],2,'.',''):$salesfee;
                                    $details['salesfee'] = $salesfee;
                                }
                            }
                            /** CHECK IF OVERRIDE COMMISSION ENABLED */

                            // info('Fee Account Referral', ['_leadspeek_type' => $_leadspeek_type,'salesfee' => $salesfee]);
                            if ($salesfee > 0) {
                                // info('process transfer Account Referral', [
                                //     'amount' => ($salesfee * 100),
                                //     'currency' => 'usd',
                                //     'destination' => $sale['accconnectid'],
                                //     'description' => 'Commision from ' . $sale['company_name'] . '-' . $_client_name  . '-' . $_campaign_name . ' #' . $_leadspeek_api_id,
                                // ]);

                                $transferSales = $stripe->transfers->create([
                                    'amount' => ($salesfee * 100),
                                    'currency' => 'usd',
                                    'destination' => $sale['accconnectid'],
                                    'description' => 'Commision from ' . $sale['company_name'] . '-' . $_client_name  . '-' . $_campaign_name . ' #' . $_leadspeek_api_id,
                                ]);

                                if (isset($transferSales->destination_payment)) {
                                    $despay = $transferSales->destination_payment;

                                    $transferSalesDesc =  $stripe->charges->update($despay,
                                            [
                                                'description' => 'Commision from ' . $sale['company_name'] . '-' . $_client_name  . '-' . $_campaign_name . ' #' . $_leadspeek_api_id,
                                            ],['stripe_account' => $sale['accconnectid']]);
                                }

                                $arID = $sale['sales_id'];
                                $this->send_email(array($sale['email']),$from,'Commission fee from ' . $sale['company_name'] . ' #' . $_leadspeek_api_id . '(AR)',$details,$attachement,'emails.salesfee',$sale['company_root_id']);
                            }
                        }catch (Exception $e) {
                            //return json_encode(array('result'=>'failed','payment_intentID'=>'','srID'=>'','aeID'=>'','salesfee'=>'','error'=>'AE error transfer'));
                            $this->send_notif_stripeerror('ACREF error transfer','ACREF error transfer for ' . $sale['company_name'] . ' #' . $_leadspeek_api_id,$sale['company_root_id']);
                        }
                        
                    }
                }else{
                    $tmp = explode(" ",$sale['name']);
                    $this->send_notif_stripeerror('insufficient balance Commision for ' . $tmp[0],'Insufficient balance to transfer for ' . $sale['company_name'] . ' #' . $_leadspeek_api_id,$sale['company_root_id']);
                }
            }

            
        }

        return json_encode(array('result'=>'success','payment_intentID'=>'','srID'=>$srID,'aeID'=>$aeID,'arID'=>$arID,'salesfee'=>$salesfee,'error'=>''));
        
    }

    public function getCampaignDetails($leadspeek_api_id) {
        $campaigndetails = LeadspeekUser::select('leadspeek_users.campaign_name','leadspeek_users.paymentterm','companies.company_name')
                                                ->join('users','leadspeek_users.user_id','=','users.id')
                                                ->join('companies','users.company_id','=','companies.id')
                                                ->where('leadspeek_users.leadspeek_api_id','=',$leadspeek_api_id)
                                                ->get();
        return $campaigndetails;
    }

    private function process_charge_agency_stripeinfo($stripeseckey = '',$customer_payment_id = '',$platformfee = 0, $email = '',$customer_card_id = '',$defaultInvoice = '',$transferGroup = '',$_leadspeek_api_id = '',$_agency_name = '',$_client_name = '',$_campaign_name = '',$company_root_id = '')
    {
        $payment_intent = "";
        $statusPayment = "";
        $errorstripe = "";

        if($platformfee < 0.5 || $platformfee == '')
        {
            $statusPayment = 'paid';
            return [
                'payment_intent' => $payment_intent,
                'statusPayment' => $statusPayment,
                'errorstripe' => $errorstripe
            ];
        }

        $stripe = new StripeClient([
            'api_key' => $stripeseckey,
            'stripe_version' => '2020-08-27'
        ]);

        try
        {
            $payment_intent =  $stripe->paymentIntents->create([
                'payment_method_types' => ['card'],
                'customer' => trim($customer_payment_id),
                'amount' => ($platformfee * 100),
                'currency' => 'usd',
                'receipt_email' => $email,
                'payment_method' => trim($customer_card_id),
                'confirm' => true,
                'description' => $defaultInvoice,
                'transfer_group' => $transferGroup,
            ]);
            $statusPayment = 'paid';

            /* CHECK STATUS PAYMENT INTENTS */
            $payment_intent_status = (isset($payment_intent->status))?$payment_intent->status:"";
            if($payment_intent_status == 'requires_action') 
            {
                $statusPayment = 'failed';
                $errorstripe = "Payment for campaign $_leadspeek_api_id was unsuccessful: Stripe status '$payment_intent_status' indicates further user action is needed.";
                $this->send_notif_stripeerror("Agency Billing Failure Notice","<p>Dear Admin,</p><p>The following agency's card failed to process a payment.</p><p>Agency: " . $_agency_name . "</p><p>Client: " . $_client_name . "</p>Campaign: " . $_campaign_name . "#" . $_leadspeek_api_id . "</p><p>&nbsp;</p><p>&nbsp;</p><p>Stripe Error : " . $errorstripe . "</p>",$company_root_id);
            }
            /* CHECK STATUS PAYMENT INTENTS */

        }
        catch (RateLimitException $e) 
        {
            $statusPayment = 'failed';
            // Too many requests made to the API too quickly
            $errorstripe = $e->getHttpStatus() . '|' . $e->getError()->type . '|' . $e->getError()->code . '|' . $e->getError()->param . '|' . $e->getError()->message;
            $this->send_notif_stripeerror("Agency Billing Failure Notice","<p>Dear Admin,</p><p>The following agency's card failed to process a payment.</p><p>Agency: " . $_agency_name . "</p><p>Client: " . $_client_name . "</p>Campaign: " . $_campaign_name . "#" . $_leadspeek_api_id . "</p><p>&nbsp;</p><p>&nbsp;</p><p>Stripe Error : " . $errorstripe . "</p>",$company_root_id);
            //return json_encode(array('result'=>'failed','payment_intentID'=>'','srID'=>'','aeID'=>'','salesfee'=>'','error'=>$errorstripe));
        } 
        catch (InvalidRequestException $e) 
        {
            $statusPayment = 'failed';
            // Invalid parameters were supplied to Stripe's API
            $errorstripe = $e->getHttpStatus() . '|' . $e->getError()->type . '|' . $e->getError()->code . '|' . $e->getError()->param . '|' . $e->getError()->message;
            $this->send_notif_stripeerror("Agency Billing Failure Notice","<p>Dear Admin,</p><p>The following agency's card failed to process a payment.</p><p>Agency: " . $_agency_name . "</p><p>Client: " . $_client_name . "</p>Campaign: " . $_campaign_name . "#" . $_leadspeek_api_id . "</p><p>&nbsp;</p><p>&nbsp;</p><p>Stripe Error : " . $errorstripe . "</p>",$company_root_id);
            //return json_encode(array('result'=>'failed','payment_intentID'=>'','srID'=>'','aeID'=>'','salesfee'=>'','error'=>$errorstripe));
        } 
        catch (ExceptionAuthenticationException $e) 
        {
            $statusPayment = 'failed';
            // Authentication with Stripe's API failed
            // (maybe you changed API keys recently)
            $errorstripe = $e->getHttpStatus() . '|' . $e->getError()->type . '|' . $e->getError()->code . '|' . $e->getError()->param . '|' . $e->getError()->message;
            $this->send_notif_stripeerror("Agency Billing Failure Notice","<p>Dear Admin,</p><p>The following agency's card failed to process a payment.</p><p>Agency: " . $_agency_name . "</p><p>Client: " . $_client_name . "</p>Campaign: " . $_campaign_name . "#" . $_leadspeek_api_id . "</p><p>&nbsp;</p><p>&nbsp;</p><p>Stripe Error : " . $errorstripe . "</p>",$company_root_id);
            //return json_encode(array('result'=>'failed','payment_intentID'=>'','srID'=>'','aeID'=>'','salesfee'=>'','error'=>$errorstripe));
        } 
        catch (ApiConnectionException $e) 
        {
            $statusPayment = 'failed';
            // Network communication with Stripe failed
            $errorstripe = $e->getHttpStatus() . '|' . $e->getError()->type . '|' . $e->getError()->code . '|' . $e->getError()->param . '|' . $e->getError()->message;
            $this->send_notif_stripeerror("Agency Billing Failure Notice","<p>Dear Admin,</p><p>The following agency's card failed to process a payment.</p><p>Agency: " . $_agency_name . "</p><p>Client: " . $_client_name . "</p>Campaign: " . $_campaign_name . "#" . $_leadspeek_api_id . "</p><p>&nbsp;</p><p>&nbsp;</p><p>Stripe Error : " . $errorstripe . "</p>",$company_root_id);
            //return json_encode(array('result'=>'failed','payment_intentID'=>'','srID'=>'','aeID'=>'','salesfee'=>'','error'=>$errorstripe));
        } 
        catch (ApiErrorException $e) 
        {
            $statusPayment = 'failed';
            // Display a very generic error to the user, and maybe send
            // yourself an email
            $errorstripe = $e->getHttpStatus() . '|' . $e->getError()->type . '|' . $e->getError()->code . '|' . $e->getError()->param . '|' . $e->getError()->message;
            $this->send_notif_stripeerror("Agency Billing Failure Notice","<p>Dear Admin,</p><p>The following agency's card failed to process a payment.</p><p>Agency: " . $_agency_name . "</p><p>Client: " . $_client_name . "</p>Campaign: " . $_campaign_name . "#" . $_leadspeek_api_id . "</p><p>&nbsp;</p><p>&nbsp;</p><p>Stripe Error : " . $errorstripe . "</p>",$company_root_id);
            //return json_encode(array('result'=>'failed','payment_intentID'=>'','srID'=>'','aeID'=>'','salesfee'=>'','error'=>$errorstripe));
        } 
        catch (Exception $e) 
        {
            $statusPayment = 'failed';
            // Something else happened, completely unrelated to Stripe
            $errorstripe = 'error not stripe things';
            $this->send_notif_stripeerror("Agency Billing Failure Notice","<p>Dear Admin,</p><p>The following agency's card failed to process a payment.</p><p>Agency: " . $_agency_name . "</p><p>Client: " . $_client_name . "</p>Campaign: " . $_campaign_name . "#" . $_leadspeek_api_id . "</p><p>&nbsp;</p><p>&nbsp;</p><p>Stripe Error : " . $errorstripe . "</p>",$company_root_id);
            //return json_encode(array('result'=>'failed','payment_intentID'=>'','srID'=>'','aeID'=>'','salesfee'=>'','error'=>$errorstripe));
        }

        return [
            'payment_intent' => $payment_intent,
            'statusPayment' => $statusPayment,
            'errorstripe' => $errorstripe
        ];
    }
    
    public function check_agency_stripeinfo($companyParentID,$platformfee,$_leadspeek_api_id = "",$defaultInvoice = "",$startdate = "0000-00-00 00:00:00",$enddate = "0000-00-00 23:59:59",$ongoingleads = "",$cleanProfit = "",$dataCustomCommissionSales = [],$agencyManualBill = 'F') 
    {
        $chkUser = User::select('users.id','users.customer_payment_id','users.customer_card_id','users.email','users.company_root_id','users.amount','users.custom_amount','users.last_balance_amount','users.stopcontinual','users.payment_type','users.ip_login','companies.company_name')
                        ->leftjoin('companies','companies.id','=','users.company_id')
                        ->where('users.company_id','=',$companyParentID)
                        ->where('users.company_parent','<>',$companyParentID)
                        ->where('users.user_type','=','userdownline')
                        ->where('users.isAdmin','=','T')
                        ->where('users.active','=','T')
                        ->get();
        // info('start function check_agency_stripeinfo', ['companyParentID' => $companyParentID, 'chkUser' => $chkUser]);
        if(count($chkUser) > 0) 
        {
            /** GET STRIPE KEY */
            $stripeseckey = config('services.stripe.secret');
            $stripepublish = $this->getcompanysetting($chkUser[0]['company_root_id'],'rootstripe');
            if ($stripepublish != '') 
            {
                $stripeseckey = (isset($stripepublish->secretkey))?$stripepublish->secretkey:"";
            }
            /** GET STRIPE KEY */

            $stripe = new StripeClient([
                'api_key' => $stripeseckey,
                'stripe_version' => '2020-08-27'
            ]);

            $transferGroup = 'AI_' . $chkUser[0]['id'] . '_' . $_leadspeek_api_id . uniqid();
            $srID = "";
            $aeID = "";
            $arID = "";
            $salesfee = 0;
            $topup_agencies_id = null;
            $leadspeek_invoices_id = null;

            /* CHECK TYPE CHARGE IN COMMISSION SALES */
            // info(['dataCustomCommissionSales' => $dataCustomCommissionSales,]);
            $chargeType = isset($dataCustomCommissionSales['type'])?$dataCustomCommissionSales['type']:''; // untuk mengetahui transfer commission sale itu dari invoice atau topup
            $platformPriceArray = []; // jika createInvoice, data ini untuk menampung seluruh platform_price_lead
            $platformPriceTopup = 0; // jika topup, data ini untuk menampung platformPrice topup
            $totalLeadTopup = 0; // jika topup, data ini untuk menampung totalLead topup

            if($chargeType == 'invoice') 
            {
                $_lp_user_id = isset($dataCustomCommissionSales['_lp_user_id'])?$dataCustomCommissionSales['_lp_user_id']:'';
                $_company_id = isset($dataCustomCommissionSales['_company_id'])?$dataCustomCommissionSales['_company_id']:'';
                $_user_id = isset($dataCustomCommissionSales['_user_id'])?$dataCustomCommissionSales['_user_id']:'';
                $_leadspeek_api_id = isset($dataCustomCommissionSales['_leadspeek_api_id'])?$dataCustomCommissionSales['_leadspeek_api_id']:'';
                $startBillingDate = isset($dataCustomCommissionSales['startBillingDate'])?$dataCustomCommissionSales['startBillingDate']:'';
                $endBillingDate = isset($dataCustomCommissionSales['endBillingDate'])?$dataCustomCommissionSales['endBillingDate']:'';

                $platformPriceArray = LeadspeekReport::where('lp_user_id','=',$_lp_user_id)
                                                    ->where('company_id','=',$_company_id)
                                                    ->where('user_id','=',$_user_id)
                                                    ->where('leadspeek_api_id','=',$_leadspeek_api_id)
                                                    ->where('active','=','T')
                                                    //->whereBetween(DB::raw('DATE_FORMAT(clickdate,"%Y-%m-%d")'),[$startBillingDate,$endBillingDate])
                                                    ->where(DB::raw('DATE_FORMAT(clickdate,"%Y%m%d%H%i%s")'),'>=',$startBillingDate)
                                                    ->where(DB::raw('DATE_FORMAT(clickdate,"%Y%m%d%H%i%s")'),'<=',$endBillingDate)
                                                    ->pluck('platform_price_lead');
                // info('persiapan data untuk custom_commission_type, type pembayaran createInvoice', ['lp_user_id' => $_lp_user_id,'_company_id' => $_company_id,'_user_id' => $_user_id,'_leadspeek_api_id' => $_leadspeek_api_id,'lp_user_id' => $_lp_user_id,'platformPriceArray' => $platformPriceArray]);
            } 
            else if($chargeType == 'topup') 
            {
                $platformPriceTopup = isset($dataCustomCommissionSales['platform_price_lead'])?$dataCustomCommissionSales['platform_price_lead']:0;
                $totalLeadTopup = isset($dataCustomCommissionSales['total_lead_topup'])?$dataCustomCommissionSales['total_lead_topup']:0;
                // info('persiapan data untuk custom_commission_type, type pembayaran topup', ['platformPriceTopup' => $platformPriceTopup,'totalLeadTopup' => $totalLeadTopup,]);
            }
            /* CHECK TYPE CHARGE IN COMMISSION SALES */

            /** CHECK IF THERE ARE SALES AND ACCOUNT EXECUTIVE */
            $salt = substr(hash('sha256', env('APP_KEY')), 0, 16);
            $saleslist = CompanySale::select(
                                        DB::raw("CONVERT(AES_DECRYPT(FROM_bASE64(users.custom_commission), '" . $salt . "') USING utf8mb4) as `custom_commission`"),
                                        DB::raw("CONVERT(AES_DECRYPT(FROM_bASE64(users.custom_commission_fixed), '" . $salt . "') USING utf8mb4) as `custom_commission_fixed`"),
                                        'users.custom_commission_enabled','users.custom_commission_type','company_sales.id','company_sales.sales_id','company_sales.sales_title','users.company_root_id',
                                        DB::raw("CONVERT(AES_DECRYPT(FROM_bASE64(`company_name`), '" . $salt . "') USING utf8mb4) as `company_name`"),
                                        DB::raw("CONVERT(AES_DECRYPT(FROM_bASE64(users.acc_connect_id), '" . $salt . "') USING utf8mb4) as `accconnectid`"),
                                        DB::raw("CONVERT(AES_DECRYPT(FROM_bASE64(users.name), '" . $salt . "') USING utf8mb4) as `name`"),
                                        DB::raw("CONVERT(AES_DECRYPT(FROM_bASE64(users.email), '" . $salt . "') USING utf8mb4) as `email`"))
                                    ->join('users','company_sales.sales_id','=','users.id')
                                    ->join('companies','company_sales.company_id','=','companies.id')
                                    ->where('company_sales.company_id','=',$companyParentID)
                                    ->where('users.active','=','T')
                                    ->where('users.user_type','=','sales')
                                    ->where('users.status_acc','=','completed')
                                    ->get();

            /*if(count($saleslist) > 0) {
                $transferGroup = 'AI_' . $chkUser[0]['id'] . '_' . $_leadspeek_api_id . uniqid();
            }*/
            /** CHECK IF THERE ARE SALES AND ACCOUNT EXECUTIVE */
            $payment_intent = "";
            $statusPayment = "";
            $chargeStatusWithPrepaid  = "";
            $errorstripe = "";

            /** GET OTHER DETAILS */
            $_campaign_name = "";
            $_campaign_paymentterm = ""; 
            $_client_name = "";
            $_agency_name = (isset($chkUser[0]['company_name'])) ? $chkUser[0]['company_name'] : '';
            $_agency_stop_continual = (isset($chkUser[0]['stopcontinual'])) ? $chkUser[0]['stopcontinual'] : 'F';
            $_agency_amount = (isset($chkUser[0]['amount'])) ? $chkUser[0]['amount'] : 0;
            $_agency_custom_amount = (isset($chkUser[0]['custom_amount'])) ? $chkUser[0]['custom_amount'] : 'F';
            $_agency_payment_type = (isset($chkUser[0]['payment_type'])) ? $chkUser[0]['payment_type'] : 'F';

            $_agency_ip_login = (isset($chkUser[0]['ip_login'])) ? explode('|', $chkUser[0]['ip_login']) : [];
            $_agency_ip_user = (isset($_agency_ip_login[0])) ? $_agency_ip_login[0] : '';
            $_agency_timezone = (isset($_agency_ip_login[1])) ? $_agency_ip_login[1] : '';

            $_agency_last_balance_amount = (isset($chkUser[0]['last_balance_amount'])) ? $chkUser[0]['last_balance_amount'] : 0;
            $_agency_balance_threshold = ($_agency_last_balance_amount * 10 / 100);
            $_agency_balance_threshold = (float) number_format($_agency_balance_threshold,2,'.','');
            
            // info([
            //     '_agency_name' => $_agency_name,
            //     '_agency_stop_continual' => $_agency_stop_continual,
            //     '_agency_amount' => $_agency_amount,
            //     '_agency_custom_amount' => $_agency_custom_amount,
            //     '_agency_payment_type' => $_agency_payment_type,
            //     '_agency_ip_login' => $_agency_ip_login,
            //     '_agency_ip_user' => $_agency_ip_user,
            //     '_agency_timezone' => $_agency_timezone,
            //     '_agency_last_balance_amount' => $_agency_last_balance_amount,
            //     '_agency_balance_threshold' => $_agency_balance_threshold,
            // ]);

            $campaigndetails = $this->getCampaignDetails($_leadspeek_api_id);

            if (count($campaigndetails) > 0) 
            {
                $_campaign_name = $campaigndetails[0]['campaign_name'];
                $_campaign_paymentterm = $campaigndetails[0]['paymentterm']; 
                $_client_name = $campaigndetails[0]['company_name'];
            }
            /** GET OTHER DETAILS */

            /** GET AGENCY BALANCE AMOUNT IF MANUAL BILL */
            $topupAgencyExists = false;
            $remainingPlatformfee = 0;
            
            if($agencyManualBill == 'T')
            {
                // info('start db::transaction');
                DB::transaction(function () use ($companyParentID, $platformfee, $_campaign_paymentterm, &$topupAgencyExists, &$remainingPlatformfee, &$statusPayment, &$topup_agencies_id, &$leadspeek_invoices_id, &$chargeStatusWithPrepaid, $_agency_stop_continual, $_agency_amount, $_agency_custom_amount, $_agency_payment_type, $_agency_ip_user, $_agency_timezone, $_agency_balance_threshold, $stripeseckey, $chkUser, $defaultInvoice, $transferGroup, $_leadspeek_api_id, $_agency_name, $_client_name, $_campaign_name) {
                    $topupAgency = TopupAgency::select('balance_amount','topup_status')
                                              ->where('company_id','=',$companyParentID)
                                              ->where('topup_status','<>','done')
                                              ->lockForUpdate()
                                              ->orderBy('id','asc')
                                              ->get();
                    $balanceAmount = $topupAgency->sum('balance_amount');
                    // info(['balanceAmount' => $balanceAmount]);

                    if($balanceAmount > 0)
                    {
                        // info('pakai agency topup'); 
                        // info('block 1.1');
                        $configurationController = App::make(ConfigurationController::class);
                        $topupAgencyExists = true;
                        
                        if($balanceAmount >= $platformfee) // jika balance amount lebih dari platformfee
                        {
                            // update balance amount
                            $topupAgencyProgress = TopupAgency::where('company_id','=',$companyParentID)
                                                              ->where('topup_status','=','progress')
                                                              ->first();
                            // info('block 2.1', ['topupAgencyProgress' => $topupAgencyProgress]);
                            if(!empty($topupAgencyProgress)) 
                            {
                                // info('block 2.2');
                                if($topupAgencyProgress->balance_amount >= $platformfee) // jika balance_amount lebih atau sama dengan platformfee, maka kurangi dengan normal
                                {
                                    // info('block 2.3');
                                    $topupAgencyProgress->balance_amount -= $platformfee;
                                    $topupAgencyProgress->balance_amount = (float) number_format($topupAgencyProgress->balance_amount,2,'.','');
                                }
                                else // jika balance amount lebih kecil dari platformfee, tetapi ada topup yang queue, maka ubah balance_amount menjadi 0 karena pasti minus, lalu kurangi lagi dengan yang masih queue
                                {
                                    // info('block 2.4');
                                    $remainingPlatformfee = $platformfee - $topupAgencyProgress->balance_amount;
                                    $remainingPlatformfee = (float) number_format($remainingPlatformfee,2,'.','');
                                    $topupAgencyProgress->balance_amount = 0;
                                }

                                if($topupAgencyProgress->balance_amount <= 0) 
                                {
                                    // info('block 2.5');
                                    $topupAgencyProgress->topup_status = 'done';
                                }

                                $topupAgencyProgress->save();
                                $topup_agencies_id = $topupAgencyProgress->id;
                            }

                            // info(['remainingPlatformfee luar' => $remainingPlatformfee]);
                            if($remainingPlatformfee > 0) 
                            {
                                $topupAgencyQueue = TopupAgency::where('company_id','=',$companyParentID)
                                                                ->where('topup_status','=','queue')
                                                                ->orderBy('id','asc')
                                                                ->get();
                                // info('block 2.6', ['topupAgencyQueue' => $topupAgencyQueue]);
                                foreach($topupAgencyQueue as $item)
                                {
                                    // info('block 2.7', ['balanceAmount dalam' => $item->balance_amount, 'remainingPlatformfee dalam' => $remainingPlatformfee]);
                                    $topup_agencies_id = $item->id;
                                    $item->topup_status = 'progress';
                                    $item->save();

                                    if($item->balance_amount >= $remainingPlatformfee)
                                    {
                                        // info('block 2.8');
                                        $diffBalanceAmount = $item->balance_amount - $remainingPlatformfee;  
                                        $diffBalanceAmount = (float) number_format($diffBalanceAmount,2,'.','');
                                        $remainingPlatformfee = 0;
                                        
                                        if($diffBalanceAmount <= 0) 
                                        {
                                            // info('block 2.9');
                                            $item->topup_status = 'done';
                                        }
                                        $item->balance_amount = $diffBalanceAmount;
                                        $item->save();
                                        break;
                                    }
                                    else
                                    {
                                        // info('block 2.10');
                                        $remainingPlatformfee -= $item->balance_amount;
                                        $remainingPlatformfee = (float) number_format($remainingPlatformfee,2,'.','');
                                        $item->balance_amount = 0;
                                        $item->topup_status = 'done';
                                        $item->save();
                                    }
                                }
                            }
                            // update balance amount

                            // create leadspeek invoice
                            // info('buat invoice in if');
                            $invoiceCreated = LeadspeekInvoice::create([
                                'topup_agencies_id' => $topup_agencies_id,
                                'company_id' => 0,
                                'user_id' => 0,
                                'leadspeek_api_id' => $_leadspeek_api_id,
                                'invoice_number' => '',
                                'payment_term' => '',
                                'onetimefee' => 0,
                                'platform_onetimefee' => 0,
                                'min_leads' => 0,
                                'exceed_leads' => 0,
                                'total_leads' => 0,
                                'min_cost' => 0,
                                'platform_min_cost' => 0,
                                'cost_leads' => 0,
                                'platform_cost_leads' => 0,
                                'total_amount' => 0,
                                'platform_total_amount' => $platformfee,
                                'root_total_amount' => 0,
                                'status' => 'pending',
                                'customer_payment_id' => '',
                                'platform_customer_payment_id' => '',
                                'error_payment' => '',
                                'platform_error_payment' => '',
                                'invoice_date' => date('Y-m-d'),
                                'invoice_start' => date('Y-m-d'),
                                'invoice_end' => date('Y-m-d'),
                                'sent_to' => '',
                                'sr_id' => 0,
                                'sr_fee' => 0,
                                'ae_id' => 0,
                                'ae_fee' => 0,
                                'ar_id' => 0,
                                'ar_fee' => 0,
                                'active' => 'T',
                            ]);
                            $leadspeek_invoices_id = $invoiceCreated->id;
                            // create leadspeek invoice

                            // check auto topup if stopcontinual 'F'
                            $chargeStatusWithPrepaid = "paid";
                            $statusPayment = 'paid';
                            if($_agency_stop_continual == 'F')
                            {
                                $currentBalanceAmount = TopupAgency::where('company_id','=',$companyParentID)
                                                                   ->where('topup_status','<>','done')
                                                                   ->orderBy('id','asc')
                                                                   ->sum('balance_amount');
                                // info('block 2.13', ['currentBalanceAmount' => $currentBalanceAmount,'_agency_balance_threshold' => $_agency_balance_threshold]);
                                if($currentBalanceAmount < $_agency_balance_threshold)
                                {
                                    // info('block 2.14');
                                    $request = [
                                        'company_id' => $companyParentID,
                                        'amount' => $_agency_amount,
                                        'stop_continual' => ($_agency_stop_continual == 'F') ? false : true,
                                        'custom_amount' => ($_agency_custom_amount == 'F') ? false : true,
                                        'ip_user' => $_agency_ip_user,
                                        'timezone' => $_agency_timezone,
                                        'payment_type' => $_agency_payment_type
                                    ];
                                    $request = new Request($request);

                                    try 
                                    {
                                        $charge = $configurationController->chargePrepaidDirectPayment($request)->getData();
                                        $statusPayment = (isset($charge->result) && $charge->result == 'failed') ? 'failed' : 'paid';
                                        // info('block 2.15', ['statusPayment' => $statusPayment, 'charge' => $charge]);
                                    }
                                    catch (\Exception $e)
                                    {
                                        // info('block 2.16');
                                        $statusPayment = 'failed';
                                    }
                                }
                            }
                            // check auto topup if stopcontinual 'F'
                        }
                        else // jika balance amount kurang dari platformfee
                        {
                            // info('block 3.1', ['_agency_stop_continual' => $_agency_stop_continual]);
                            $statusPayment = 'paid';

                            $topupAgencyProgressBuffer = [];
                            $topupAgencyQueueBuffer = [];

                            if($_agency_stop_continual == 'F')
                            {
                                for($i = 0; $i < 20; $i++) // max 20x
                                {
                                    // info('block 3.2');

                                    $request = [
                                        'company_id' => $companyParentID,
                                        'amount' => $_agency_amount,
                                        'stop_continual' => ($_agency_stop_continual == 'F') ? false : true,
                                        'custom_amount' => ($_agency_custom_amount == 'F') ? false : true,
                                        'ip_user' => $_agency_ip_user,
                                        'timezone' => $_agency_timezone,
                                        'payment_type' => $_agency_payment_type
                                    ];
                                    $request = new Request($request);

                                    try 
                                    {
                                        $charge = $configurationController->chargePrepaidDirectPayment($request)->getData();
                                        $statusPayment = (isset($charge->result) && $charge->result == 'failed') ? 'failed' : 'paid';
                                        // info('block 3.3', ['statusPayment' => $statusPayment, 'charge' => $charge]);
                                    }
                                    catch (\Exception $e)
                                    {
                                        // info('block 3.4');
                                        $statusPayment = 'failed';
                                    }
                                    
                                    if($statusPayment == 'failed') 
                                        break;

                                    $currentBalanceAmount = TopupAgency::where('company_id','=',$companyParentID)
                                                                       ->where('topup_status','<>','done')
                                                                       ->orderBy('id','asc')
                                                                       ->sum('balance_amount');
                                    if($currentBalanceAmount < $platformfee)
                                        continue;

                                    break;
                                }
                            }

                            if($_campaign_paymentterm != 'Prepaid' || ($_campaign_paymentterm == 'Prepaid' && $statusPayment == 'paid'))
                            {
                                $topupAgencyProgress = TopupAgency::where('company_id','=',$companyParentID)
                                                                  ->where('topup_status','=','progress')
                                                                  ->first();
                                $topupAgencyProgressBuffer = clone $topupAgencyProgress;
                                // info('block 3.5', ['topupAgencyProgress' => $topupAgencyProgress]);
                                if(!empty($topupAgencyProgress)) 
                                {
                                    // info('block 3.6');
                                    $remainingPlatformfee = $platformfee - $topupAgencyProgress->balance_amount;
                                    $remainingPlatformfee = (float) number_format($remainingPlatformfee,2,'.','');
                                    
                                    $topupAgencyProgress->balance_amount = 0;
                                    $topupAgencyProgress->topup_status = 'done';
                                    $topupAgencyProgress->save();
                                    $topup_agencies_id = $topupAgencyProgress->id;
                                }
    
                                if($remainingPlatformfee > 0) 
                                {
                                    // info('block 3.7');
                                    $topupAgencyQueue = TopupAgency::where('company_id','=',$companyParentID)
                                                                    ->where('topup_status','=','queue')
                                                                    ->orderBy('id','asc')
                                                                    ->get();
                                    $topupAgencyQueueBuffer = $topupAgencyQueue->map(fn($item) => clone $item);
                                    
                                    foreach($topupAgencyQueue as $item)
                                    {
                                        // info('block 3.8', ['balanceAmount dalam' => $item->balance_amount, 'remainingPlatformfee luar' => $remainingPlatformfee]);
                                        $topup_agencies_id = $item->id;
                                        $item->topup_status = 'progress';
                                        $item->save();
    
                                        if($item->balance_amount >= $remainingPlatformfee)
                                        {
                                            // info('block 3.9');
                                            $diffBalanceAmount = $item->balance_amount - $remainingPlatformfee;  
                                            $diffBalanceAmount = (float) number_format($diffBalanceAmount,2,'.','');
                                            $remainingPlatformfee = 0;
                                            
                                            if($diffBalanceAmount <= 0) 
                                            {
                                                // info('block 3.10');
                                                $item->topup_status = 'done';
                                            }
                                            $item->balance_amount = $diffBalanceAmount;
                                            $item->save();
                                            break;
                                        }
                                        else
                                        {
                                            // info('block 3.11');
                                            $remainingPlatformfee -= $item->balance_amount;
                                            $remainingPlatformfee = (float) number_format($remainingPlatformfee,2,'.','');
                                            $item->balance_amount = 0;
                                            $item->topup_status = 'done';
                                            $item->save();
                                        }
                                    }
                                }

                                if($statusPayment == 'paid') 
                                {
                                    // info('block 3.12');
                                    $chargeStatusWithPrepaid = "paid";
                                }

                                // info('buat invoice in else');
                                $invoiceCreated = LeadspeekInvoice::create([
                                    'topup_agencies_id' => $topup_agencies_id,
                                    'company_id' => 0,
                                    'user_id' => 0,
                                    'leadspeek_api_id' => $_leadspeek_api_id,
                                    'invoice_number' => '',
                                    'payment_term' => '',
                                    'onetimefee' => 0,
                                    'platform_onetimefee' => 0,
                                    'min_leads' => 0,
                                    'exceed_leads' => 0,
                                    'total_leads' => 0,
                                    'min_cost' => 0,
                                    'platform_min_cost' => 0,
                                    'cost_leads' => 0,
                                    'platform_cost_leads' => 0,
                                    'total_amount' => 0,
                                    'platform_total_amount' => $platformfee,
                                    'root_total_amount' => 0,
                                    'status' => 'pending',
                                    'customer_payment_id' => '',
                                    'platform_customer_payment_id' => '',
                                    'error_payment' => '',
                                    'platform_error_payment' => '',
                                    'invoice_date' => date('Y-m-d'),
                                    'invoice_start' => date('Y-m-d'),
                                    'invoice_end' => date('Y-m-d'),
                                    'sent_to' => '',
                                    'sr_id' => 0,
                                    'sr_fee' => 0,
                                    'ae_id' => 0,
                                    'ae_fee' => 0,
                                    'ar_id' => 0,
                                    'ar_fee' => 0,
                                    'active' => 'T',
                                ]);
                                $leadspeek_invoices_id = $invoiceCreated->id;
                            }

                            if($_agency_stop_continual == 'T' && $remainingPlatformfee > 0) 
                            {
                                // coba charge sisanya
                                $resultCharge = $resultCharge = $this->process_charge_agency_stripeinfo($stripeseckey,$chkUser[0]['customer_payment_id'],$remainingPlatformfee,$chkUser[0]['email'],$chkUser[0]['customer_card_id'],$defaultInvoice,$transferGroup,$_leadspeek_api_id,$_agency_name,$_client_name,$_campaign_name,$chkUser[0]['company_root_id']);
                                $statusPayment = (isset($resultCharge['statusPayment'])) ? $resultCharge['statusPayment'] : '';
                                $errorstripe = (isset($resultCharge['errorstripe'])) ? $resultCharge['errorstripe'] : '';
                                // info('block 3.13', ['stripeseckey' => $stripeseckey,'customer_payment_id' => $chkUser[0]['customer_payment_id'],'remainingPlatformfee' => $remainingPlatformfee,'email' => $chkUser[0]['email'],'customer_card_id' => $chkUser[0]['customer_card_id'],'defaultInvoice' => $defaultInvoice,'transferGroup' => $transferGroup,'leadspeek_api_id' => $_leadspeek_api_id,'agency_name' => $_agency_name,'client_name' => $_client_name,'campaign_name' => $_campaign_name,'company_root_id'=>$chkUser[0]['company_root_id'],'statusPayment'=>$statusPayment,'errorstripe'=>$errorstripe]);
                                
                                // jika error revert ulang topupnya untuk Prepaid
                                if($statusPayment == 'failed')
                                {
                                    // info('block 3.14');
                                    $chargeStatusWithPrepaid = "failed";

                                    if($_campaign_paymentterm == 'Prepaid')
                                    {
                                        // info('block 3.15', ['topupAgencyProgressBuffer'=>$topupAgencyProgressBuffer]);
                                        if(!empty($topupAgencyProgressBuffer)) 
                                        {
                                            // info('block 3.16');
                                            $topup_id = $topupAgencyProgressBuffer->id;
                                            $topup_status = $topupAgencyProgressBuffer->topup_status;
                                            $topup_balance_amount = $topupAgencyProgressBuffer->balance_amount;
                                            TopupAgency::where('id','=',$topup_id)
                                                        ->update([
                                                            'topup_status' => $topup_status,
                                                            'balance_amount' => $topup_balance_amount
                                                        ]);
                                        }
    
                                        // info('block 3.17', ['topupAgencyQueueBuffer' => $topupAgencyQueueBuffer]);
                                        if(!empty($topupAgencyQueueBuffer))
                                        {
                                            foreach($topupAgencyQueueBuffer as $item)
                                            {
                                                // info('block 3.18');
                                                $topup_id = $item->id;
                                                $topup_status = $item->topup_status;
                                                $topup_balance_amount = $item->balance_amount;
                                                TopupAgency::where('id','=',$topup_id)
                                                           ->update([
                                                                'topup_status' => $topup_status,
                                                                'balance_amount' => $topup_balance_amount
                                                           ]);
                                            }
                                        }
    
                                        // remove leadspeekinvoice
                                        LeadspeekInvoice::where('id','=',$leadspeek_invoices_id)->delete();
                                        $leadspeek_invoices_id = null;
                                        // remove leadspeekinvoice
                                    }
                                }
                            }

                            if($_agency_stop_continual == 'F' && $statusPayment == 'paid')
                            {
                                $currentBalanceAmount = TopupAgency::where('company_id','=',$companyParentID)
                                                                   ->where('topup_status','<>','done')
                                                                   ->orderBy('id','asc')
                                                                   ->sum('balance_amount');

                                $agency = User::select('last_balance_amount')
                                              ->where('company_id','=',$companyParentID)
                                              ->where('active','=','T')
                                              ->where('user_type','=','userdownline')
                                              ->first();

                                $_agency_last_balance_amount = (isset($agency->last_balance_amount)) ? $agency->last_balance_amount : 0;
                                $_agency_balance_threshold = ($_agency_last_balance_amount * 10 / 100);
                                $_agency_balance_threshold = (float) number_format($_agency_balance_threshold,2,'.','');
                                // info('block 3.19', ['currentBalanceAmount' => $currentBalanceAmount,'_agency_balance_threshold' => $_agency_balance_threshold]);
                                
                                if($currentBalanceAmount < $_agency_balance_threshold)
                                {
                                    // info('block 3.20');
                                    $request = [
                                        'company_id' => $companyParentID,
                                        'amount' => $_agency_amount,
                                        'stop_continual' => ($_agency_stop_continual == 'F') ? false : true,
                                        'custom_amount' => ($_agency_custom_amount == 'F') ? false : true,
                                        'ip_user' => $_agency_ip_user,
                                        'timezone' => $_agency_timezone,
                                        'payment_type' => $_agency_payment_type
                                    ];
                                    $request = new Request($request);

                                    try 
                                    {
                                        $charge = $configurationController->chargePrepaidDirectPayment($request)->getData();
                                        $statusPayment = (isset($charge->result) && $charge->result == 'failed') ? 'failed' : 'paid';
                                        // info('block 3.21', ['statusPayment' => $statusPayment, 'charge' => $charge]);
                                    }
                                    catch (\Exception $e)
                                    {
                                        // info('block 3.22');
                                        $statusPayment = 'failed';
                                    }
                                }
                            }
                        }

                        // If there is no progress, check again whether there is any queue
                        $topupAgencyProgressExists = TopupAgency::where('company_id','=',$companyParentID)
                                                                ->where('topup_status','=','progress')
                                                                ->exists();
                        if(!$topupAgencyProgressExists)
                        {
                            // info('block 4.1');
                            $topupAgencyQueue = TopupAgency::where('company_id','=',$companyParentID)
                                                           ->where('topup_status','=','queue')
                                                           ->orderBy('created_at','asc')
                                                           ->first();
                            if(!empty($topupAgencyQueue)) 
                            {
                                // info('block 4.2');
                                $topupAgencyQueue->topup_status = 'progress';
                                $topupAgencyQueue->save();
                            }
                        }
                        // If there is no progress, check again whether there is any queue
                    }
                });
            }
            /** GET AGENCY BALANCE AMOUNT IF MANUAL BILL */

            // info(['remainingPlatformfee_final' => $remainingPlatformfee, 'topupAgencyExists' => $topupAgencyExists]);
            
            if(!$topupAgencyExists)
            {
                $resultCharge = $this->process_charge_agency_stripeinfo($stripeseckey,$chkUser[0]['customer_payment_id'],$platformfee,$chkUser[0]['email'],$chkUser[0]['customer_card_id'],$defaultInvoice,$transferGroup,$_leadspeek_api_id,$_agency_name,$_client_name,$_campaign_name,$chkUser[0]['company_root_id']);
                $payment_intent = (isset($resultCharge['payment_intent'])) ? $resultCharge['payment_intent'] : '';
                $statusPayment = (isset($resultCharge['statusPayment'])) ? $resultCharge['statusPayment'] : '';
                $errorstripe = (isset($resultCharge['errorstripe'])) ? $resultCharge['errorstripe'] : '';
                // info('charge pakai stripe karena manual bill atau balance 0', ['resultCharge' => $resultCharge,]);
            }

            // try
            // {
            //     $payment_intent =  $stripe->paymentIntents->create([
            //         'payment_method_types' => ['card'],
            //         'customer' => trim($chkUser[0]['customer_payment_id']),
            //         'amount' => ($platformfee * 100),
            //         'currency' => 'usd',
            //         'receipt_email' => $chkUser[0]['email'],
            //         'payment_method' => $chkUser[0]['customer_card_id'],
            //         'confirm' => true,
            //         'description' => $defaultInvoice,
            //         'transfer_group' => $transferGroup,
            //     ]);
            //     $statusPayment = 'paid';

            //     /* CHECK STATUS PAYMENT INTENTS */
            //     $payment_intent_status = (isset($payment_intent->status))?$payment_intent->status:"";
            //     if($payment_intent_status == 'requires_action') 
            //     {
            //         $statusPayment = 'failed';
            //         $errorstripe = "Payment for campaign $_leadspeek_api_id was unsuccessful: Stripe status '$payment_intent_status' indicates further user action is needed.";
            //         $this->send_notif_stripeerror("Agency Billing Failure Notice","<p>Dear Admin,</p><p>The following agency's card failed to process a payment.</p><p>Agency: " . $_agency_name . "</p><p>Client: " . $_client_name . "</p>Campaign: " . $_campaign_name . "#" . $_leadspeek_api_id . "</p><p>&nbsp;</p><p>&nbsp;</p><p>Stripe Error : " . $errorstripe . "</p>",$chkUser[0]['company_root_id']);
            //     }
            //     /* CHECK STATUS PAYMENT INTENTS */

            // }
            // catch (RateLimitException $e) 
            // {
            //     $statusPayment = 'failed';
            //     // Too many requests made to the API too quickly
            //     $errorstripe = $e->getHttpStatus() . '|' . $e->getError()->type . '|' . $e->getError()->code . '|' . $e->getError()->param . '|' . $e->getError()->message;
            //     $this->send_notif_stripeerror("Agency Billing Failure Notice","<p>Dear Admin,</p><p>The following agency's card failed to process a payment.</p><p>Agency: " . $_agency_name . "</p><p>Client: " . $_client_name . "</p>Campaign: " . $_campaign_name . "#" . $_leadspeek_api_id . "</p><p>&nbsp;</p><p>&nbsp;</p><p>Stripe Error : " . $errorstripe . "</p>",$chkUser[0]['company_root_id']);
            //     //return json_encode(array('result'=>'failed','payment_intentID'=>'','srID'=>'','aeID'=>'','salesfee'=>'','error'=>$errorstripe));
            // } 
            // catch (InvalidRequestException $e) 
            // {
            //     $statusPayment = 'failed';
            //     // Invalid parameters were supplied to Stripe's API
            //     $errorstripe = $e->getHttpStatus() . '|' . $e->getError()->type . '|' . $e->getError()->code . '|' . $e->getError()->param . '|' . $e->getError()->message;
            //     $this->send_notif_stripeerror("Agency Billing Failure Notice","<p>Dear Admin,</p><p>The following agency's card failed to process a payment.</p><p>Agency: " . $_agency_name . "</p><p>Client: " . $_client_name . "</p>Campaign: " . $_campaign_name . "#" . $_leadspeek_api_id . "</p><p>&nbsp;</p><p>&nbsp;</p><p>Stripe Error : " . $errorstripe . "</p>",$chkUser[0]['company_root_id']);
            //     //return json_encode(array('result'=>'failed','payment_intentID'=>'','srID'=>'','aeID'=>'','salesfee'=>'','error'=>$errorstripe));
            // } 
            // catch (ExceptionAuthenticationException $e) 
            // {
            //     $statusPayment = 'failed';
            //     // Authentication with Stripe's API failed
            //     // (maybe you changed API keys recently)
            //     $errorstripe = $e->getHttpStatus() . '|' . $e->getError()->type . '|' . $e->getError()->code . '|' . $e->getError()->param . '|' . $e->getError()->message;
            //     $this->send_notif_stripeerror("Agency Billing Failure Notice","<p>Dear Admin,</p><p>The following agency's card failed to process a payment.</p><p>Agency: " . $_agency_name . "</p><p>Client: " . $_client_name . "</p>Campaign: " . $_campaign_name . "#" . $_leadspeek_api_id . "</p><p>&nbsp;</p><p>&nbsp;</p><p>Stripe Error : " . $errorstripe . "</p>",$chkUser[0]['company_root_id']);
            //     //return json_encode(array('result'=>'failed','payment_intentID'=>'','srID'=>'','aeID'=>'','salesfee'=>'','error'=>$errorstripe));
            // } 
            // catch (ApiConnectionException $e) 
            // {
            //     $statusPayment = 'failed';
            //     // Network communication with Stripe failed
            //     $errorstripe = $e->getHttpStatus() . '|' . $e->getError()->type . '|' . $e->getError()->code . '|' . $e->getError()->param . '|' . $e->getError()->message;
            //     $this->send_notif_stripeerror("Agency Billing Failure Notice","<p>Dear Admin,</p><p>The following agency's card failed to process a payment.</p><p>Agency: " . $_agency_name . "</p><p>Client: " . $_client_name . "</p>Campaign: " . $_campaign_name . "#" . $_leadspeek_api_id . "</p><p>&nbsp;</p><p>&nbsp;</p><p>Stripe Error : " . $errorstripe . "</p>",$chkUser[0]['company_root_id']);
            //     //return json_encode(array('result'=>'failed','payment_intentID'=>'','srID'=>'','aeID'=>'','salesfee'=>'','error'=>$errorstripe));
            // } 
            // catch (ApiErrorException $e) 
            // {
            //     $statusPayment = 'failed';
            //     // Display a very generic error to the user, and maybe send
            //     // yourself an email
            //     $errorstripe = $e->getHttpStatus() . '|' . $e->getError()->type . '|' . $e->getError()->code . '|' . $e->getError()->param . '|' . $e->getError()->message;
            //     $this->send_notif_stripeerror("Agency Billing Failure Notice","<p>Dear Admin,</p><p>The following agency's card failed to process a payment.</p><p>Agency: " . $_agency_name . "</p><p>Client: " . $_client_name . "</p>Campaign: " . $_campaign_name . "#" . $_leadspeek_api_id . "</p><p>&nbsp;</p><p>&nbsp;</p><p>Stripe Error : " . $errorstripe . "</p>",$chkUser[0]['company_root_id']);
            //     //return json_encode(array('result'=>'failed','payment_intentID'=>'','srID'=>'','aeID'=>'','salesfee'=>'','error'=>$errorstripe));
            // } 
            // catch (Exception $e) 
            // {
            //     $statusPayment = 'failed';
            //     // Something else happened, completely unrelated to Stripe
            //     $errorstripe = 'error not stripe things';
            //     $this->send_notif_stripeerror("Agency Billing Failure Notice","<p>Dear Admin,</p><p>The following agency's card failed to process a payment.</p><p>Agency: " . $_agency_name . "</p><p>Client: " . $_client_name . "</p>Campaign: " . $_campaign_name . "#" . $_leadspeek_api_id . "</p><p>&nbsp;</p><p>&nbsp;</p><p>Stripe Error : " . $errorstripe . "</p>",$chkUser[0]['company_root_id']);
            //     //return json_encode(array('result'=>'failed','payment_intentID'=>'','srID'=>'','aeID'=>'','salesfee'=>'','error'=>$errorstripe));
            // }

            /** CHECK DEFAULT SALES COMMISSION */
            $AgencyPercentageCommission = 0.05;
            if (count($saleslist) > 0) 
            {
                $rootAgencyPercentageCommission = $this->getcompanysetting($saleslist[0]['company_root_id'],'rootsetting');
                if ($rootAgencyPercentageCommission != '') 
                {
                    if(isset($rootAgencyPercentageCommission->defaultagencypercentagecommission) && $rootAgencyPercentageCommission->defaultagencypercentagecommission != "") 
                    {
                        $AgencyPercentageCommission = $rootAgencyPercentageCommission->defaultagencypercentagecommission;
                    }
                }
            }
            /** CHECK DEFAULT SALES COMMISSION */

            $salesfee = ($platformfee * (float) $AgencyPercentageCommission);
            //$salesfee = ($cleanProfit != "")?($cleanProfit * (float) $AgencyPercentageCommission):$salesfee;
            $salesfee = number_format($salesfee,2,'.','');

            // info('hitung salesfee pertama kali', ['platformfee' => $platformfee,'AgencyPercentageCommission' => $AgencyPercentageCommission,'salesfee' => $salesfee,]);
            //if (count($saleslist) > 0 && $platformfee > 0 && $statusPayment == "" && $salesfee > 0) {
            // if (count($saleslist) > 0 && $platformfee > 0 && $salesfee > 0 && $statusPayment != 'failed') 
            if (count($saleslist) > 0 && $platformfee > 0 && $salesfee > 0 && (($statusPayment != 'failed') || ($agencyManualBill == 'T' && $topupAgencyExists && $chargeStatusWithPrepaid == 'paid'))) 
            {
                // info('transfer sales');
                /** GET OTHER DETAILS */
                $_campaign_name = "";
                $_client_name = "";
                $_leadspeek_type = "";

                $campaigndetails = LeadspeekUser::select('leadspeek_users.campaign_name','companies.company_name','leadspeek_users.leadspeek_type')
                                                ->join('users','leadspeek_users.user_id','=','users.id')
                                                ->join('companies','users.company_id','=','companies.id')
                                                ->where('leadspeek_users.leadspeek_api_id','=',$_leadspeek_api_id)
                                                ->get();
                if (count($campaigndetails) > 0) 
                {
                    $_campaign_name = $campaigndetails[0]['campaign_name'];
                    $_client_name = $campaigndetails[0]['company_name'];
                    $_leadspeek_type = $campaigndetails[0]['leadspeek_type'];
                }
                /** GET OTHER DETAILS */
                
                foreach($saleslist as $sale) 
                {

                    $overrideCommission = array();

                    $salesfee = ($platformfee * (float) $AgencyPercentageCommission);
                    //$salesfee = ($cleanProfit != "")?($cleanProfit * (float) $AgencyPercentageCommission):$salesfee;
                    $salesfee = number_format($salesfee,2,'.','');

                    /** OVERRIDE THE COMMISSION IF ENABLED */
                    if ($sale['custom_commission_enabled'] == 'T' && $ongoingleads != "") 
                    {
                        if($sale['custom_commission_type'] == 'fixed') 
                        {
                            $chkCommissionOverride = json_decode($sale['custom_commission_fixed']);
                            // info('pakai cara custom_commission_fixed', ['chkCommissionOverride' => $chkCommissionOverride]);
                            if($_leadspeek_type == 'local') {
                                // sales representative siteid
                                if(isset($chkCommissionOverride->sr->siteid) && $chkCommissionOverride->sr->siteid > 0 && $chkCommissionOverride->sr->siteid != '') {
                                    $calculateCommission_srSiteID = 0;

                                    if($chargeType == 'invoice') {
                                        foreach($platformPriceArray as $item) {
                                            if($item > $chkCommissionOverride->sr->siteid) {
                                                $calculateCommission_srSiteID += ($item - $chkCommissionOverride->sr->siteid);    
                                            }
                                        }
                                    } else if($chargeType == 'topup' && $platformPriceTopup > $chkCommissionOverride->sr->siteid) {
                                        $calculateCommission_srSiteID = ($platformPriceTopup - $chkCommissionOverride->sr->siteid) * $totalLeadTopup;
                                    }

                                    $overrideCommission['srSiteID'] = $calculateCommission_srSiteID;

                                    // info("overrideCommission srSiteID if akhir", ['chargeType' => $chargeType,'srSiteID' => $overrideCommission['srSiteID'] ?? "masih kosong",'chkCommissionOverride->sr->siteid' => $chkCommissionOverride->sr->siteid,'platformPriceTopup' => $platformPriceTopup,'totalLeadTopup' => $totalLeadTopup,]);
                                } else {
                                    $overrideCommission['srSiteID'] = $salesfee;
                                    // info("overrideCommission srSiteID else", ['srSiteID' => $overrideCommission['srSiteID'] ?? "masih kosong",'salesfee' => $salesfee]);
                                }
                                // sales representative siteid

                                // account executive siteid
                                if(isset($chkCommissionOverride->ae->siteid) && $chkCommissionOverride->ae->siteid > 0 && $chkCommissionOverride->ae->siteid != '') {
                                    $calculateCommission_aeSiteID = 0;

                                    if($chargeType == 'invoice') {
                                        foreach($platformPriceArray as $item) {
                                            if($item > $chkCommissionOverride->ae->siteid) {
                                                $calculateCommission_aeSiteID += ($item - $chkCommissionOverride->ae->siteid);    
                                            }
                                        }
                                    } else if($chargeType == 'topup' && $platformPriceTopup > $chkCommissionOverride->ae->siteid) {
                                        $calculateCommission_aeSiteID = ($platformPriceTopup - $chkCommissionOverride->ae->siteid) * $totalLeadTopup;
                                    }

                                    $overrideCommission['aeSiteID'] = $calculateCommission_aeSiteID;
                                    
                                    // info("overrideCommission aeSiteID if end", ['chargeType' => $chargeType,'aeSiteID' => $overrideCommission['srSiteID'] ?? "masih kosong",'chkCommissionOverride->ae->siteid' => $chkCommissionOverride->ae->siteid,'platformPriceTopup' => $platformPriceTopup,'totalLeadTopup' => $totalLeadTopup,]);
                                } else {
                                    $overrideCommission['aeSiteID'] = $salesfee;
                                    // info("overrideCommission aeSiteID else", ['aeSiteID' => $overrideCommission['aeSiteID'] ?? "masih kosong",'salesfee' => $salesfee]);
                                }
                                // account executive siteid

                                // account referral siteid, ini yang akan dipakai
                                if(isset($chkCommissionOverride->ar->siteid) && $chkCommissionOverride->ar->siteid > 0 && $chkCommissionOverride->ar->siteid != '') {
                                    $calculateCommission_arSiteID  = 0;

                                    if($chargeType == 'invoice') {
                                        foreach($platformPriceArray as $item) {
                                            if($item > $chkCommissionOverride->ar->siteid) {
                                                $calculateCommission_arSiteID += ($item - $chkCommissionOverride->ar->siteid);
                                            }
                                        }  
                                    } else if($chargeType == 'topup' && $platformPriceTopup > $chkCommissionOverride->ar->siteid) {
                                        $calculateCommission_arSiteID = ($platformPriceTopup - $chkCommissionOverride->ar->siteid) * $totalLeadTopup;
                                    }

                                    $overrideCommission['arSiteID'] = $calculateCommission_arSiteID;
                                    
                                    // info("overrideCommission arSiteID if end", ['chargeType' => $chargeType,'arSiteID' => isset($overrideCommission['arSiteID'])?$overrideCommission['arSiteID']:'','chkCommissionOverride->ar->siteid' => $chkCommissionOverride->ar->siteid,'platformPriceTopup' => $platformPriceTopup,'totalLeadTopup' => $totalLeadTopup,]);
                                } else {
                                    $overrideCommission['arSiteID'] = $salesfee;
                                    // info("overrideCommission arSiteID else", ['arSiteID' => $overrideCommission['arSiteID'] ?? "masih kosong",'salesfee' => $salesfee]);
                                }
                                // account referral siteid, ini yang akan dipakai
                            } else if($_leadspeek_type == 'locator') {
                                // sales representative searchid
                                if(isset($chkCommissionOverride->sr->searchid) && $chkCommissionOverride->sr->searchid > 0 && $chkCommissionOverride->sr->searchid != '') {
                                    $calculateCommission_srSearchID = 0;
                                    
                                    if($chargeType == 'invoice') {
                                        foreach($platformPriceArray as $item) {
                                            if($item > $chkCommissionOverride->sr->searchid) {
                                                $calculateCommission_srSearchID += ($item - $chkCommissionOverride->sr->searchid);
                                            }
                                        }
                                    } else if($chargeType == 'topup' && $platformPriceTopup > $chkCommissionOverride->sr->searchid) {
                                        $calculateCommission_srSearchID = ($platformPriceTopup - $chkCommissionOverride->sr->searchid) * $totalLeadTopup;
                                    }

                                    $overrideCommission['srSearchID'] = $calculateCommission_srSearchID;
                                    // info("overrideCommission srSearchID if end", ['chargeType' => $chargeType,'srSearchID' => $overrideCommission['srSearchID'] ?? "masih kosong",'chkCommissionOverride->sr->searchid' => $chkCommissionOverride->sr->searchid,'platformPriceTopup' => $platformPriceTopup,'totalLeadTopup' => $totalLeadTopup,]);
                                } else {
                                    $overrideCommission['srSearchID'] = $salesfee;
                                    // info("overrideCommission srSearchID else", ['srSearchID' => $overrideCommission['srSearchID'] ?? "masih kosong",'salesfee' => $salesfee]);
                                }
                                // sales representative searchid

                                // account executive searchid
                                if(isset($chkCommissionOverride->ae->searchid) && $chkCommissionOverride->ae->searchid > 0 && $chkCommissionOverride->ae->searchid != '') {
                                    $calculateCommission_aeSearchID = 0;

                                    if($chargeType == 'invoice') {
                                        foreach($platformPriceArray as $item) {
                                            if($item > $chkCommissionOverride->ae->searchid) {
                                                $calculateCommission_aeSearchID += ($item - $chkCommissionOverride->ae->searchid);
                                            }
                                        }
                                    } else if($chargeType == 'topup' && $platformPriceTopup > $chkCommissionOverride->ae->searchid) {
                                        $calculateCommission_aeSearchID = ($platformPriceTopup - $chkCommissionOverride->ae->searchid) * $totalLeadTopup;
                                    }

                                    $overrideCommission['aeSearchID'] = $calculateCommission_aeSearchID;
                                    // info("overrideCommission aeSearchID if end", ['chargeType' => $chargeType,'aeSearchID' => $overrideCommission['aeSearchID'] ?? "masih kosong",'chkCommissionOverride->ae->searchid' => $chkCommissionOverride->ae->searchid,'platformPriceTopup' => $platformPriceTopup,'totalLeadTopup' => $totalLeadTopup,]);
                                } else {
                                    $overrideCommission['aeSearchID'] = $salesfee;
                                    // info("overrideCommission aeSearchID else", ['aeSearchID' => $overrideCommission['aeSearchID'] ?? "masih kosong",'salesfee' => $salesfee]);
                                }
                                // account executive searchid

                                // account reveral searchid, ini yang akan dipakai
                                if(isset($chkCommissionOverride->ar->searchid) && $chkCommissionOverride->ar->searchid > 0 && $chkCommissionOverride->ar->searchid != '') {
                                    $calculateCommission_arSearchID = 0;

                                    if($chargeType == 'invoice') {
                                        foreach($platformPriceArray as $item) {
                                            if($item > $chkCommissionOverride->ar->searchid) {
                                                $calculateCommission_arSearchID += ($item - $chkCommissionOverride->ar->searchid);
                                            }
                                        }
                                    } else if($chargeType == 'topup' && $platformPriceTopup > $chkCommissionOverride->ar->searchid) {
                                        $calculateCommission_arSearchID = ($platformPriceTopup - $chkCommissionOverride->ar->searchid) * $totalLeadTopup;
                                    }

                                    $overrideCommission['arSearchID'] = $calculateCommission_arSearchID;
                                    // info("overrideCommission arSearchID if end", ['chargeType' => $chargeType,'arSearchID' => $overrideCommission['arSearchID'] ?? "masih kosong",'chkCommissionOverride->ar->searchid' => $chkCommissionOverride->ar->searchid,'platformPriceTopup' => $platformPriceTopup,'totalLeadTopup' => $totalLeadTopup,]);
                                } else {
                                    $overrideCommission['arSearchID'] = $salesfee;
                                    // info("overrideCommission arSearchID else", ['arSearchID' => $overrideCommission['arSearchID'] ?? "masih kosong",'salesfee' => $salesfee]);
                                }
                                // account executive searchid, ini yang akan dipakai
                            } else if($_leadspeek_type == 'enhance') {
                                // sales representative enhance
                                if(isset($chkCommissionOverride->sr->enhanceid) && $chkCommissionOverride->sr->enhanceid > 0 && $chkCommissionOverride->sr->enhanceid != '') {
                                    $calculateCommission_srEnhanceID = 0;

                                    if($chargeType == 'invoice') {
                                        foreach($platformPriceArray as $item) {
                                            if($item > $chkCommissionOverride->sr->enhanceid) {
                                                $calculateCommission_srEnhanceID += ($item - $chkCommissionOverride->sr->enhanceid);
                                            }
                                        }
                                    } else if($chargeType == 'topup' && $platformPriceTopup > $chkCommissionOverride->sr->enhanceid) {
                                        $calculateCommission_srEnhanceID = ($platformPriceTopup - $chkCommissionOverride->sr->enhanceid) * $totalLeadTopup;
                                    } 

                                    $overrideCommission['srEnhanceID'] = $calculateCommission_srEnhanceID;
                                    // info("overrideCommission srEnhanceID if end", ['chargeType' => $chargeType,'srEnhanceID' => $overrideCommission['srEnhanceID'] ?? "masih kosong",'chkCommissionOverride->sr->enhanceid' => $chkCommissionOverride->sr->enhanceid,'platformPriceTopup' => $platformPriceTopup,'totalLeadTopup' => $totalLeadTopup,]);
                                } else {
                                    $overrideCommission['srEnhanceID'] = $salesfee;
                                    // info("overrideCommission srEnhanceID else", ['arSearchID' => $overrideCommission['srEnhanceID'] ?? "masih kosong",'salesfee' => $salesfee]);
                                }
                                // sales representative enhance

                                // account executive enhance
                                if(isset($chkCommissionOverride->ae->enhanceid) && $chkCommissionOverride->ae->enhanceid > 0 && $chkCommissionOverride->ae->enhanceid != '') {
                                    $calculateCommission_aeEnhanceID = 0;

                                    if($chargeType == 'invoice') {
                                        foreach($platformPriceArray as $item) {
                                            if($item > $chkCommissionOverride->ae->enhanceid) {
                                                $calculateCommission_aeEnhanceID += ($item - $chkCommissionOverride->ae->enhanceid);
                                            }
                                        }
                                    } else if($chargeType == 'topup' && $platformPriceTopup > $chkCommissionOverride->ae->enhanceid) {
                                        $calculateCommission_aeEnhanceID = ($platformPriceTopup - $chkCommissionOverride->ae->enhanceid) * $totalLeadTopup;
                                    }

                                    $overrideCommission['aeEnhanceID'] = $calculateCommission_aeEnhanceID;
                                    // info("overrideCommission aeEnhanceID if end", ['chargeType' => $chargeType,'aeEnhanceID' => $overrideCommission['aeEnhanceID'] ?? "masih kosong",'chkCommissionOverride->ae->enhanceid' => $chkCommissionOverride->ae->enhanceid,'platformPriceTopup' => $platformPriceTopup,'totalLeadTopup' => $totalLeadTopup,]);
                                } else {
                                    $overrideCommission['aeEnhanceID'] = $salesfee;
                                    // info("overrideCommission aeEnhanceID else", ['aeEnhanceID' => $overrideCommission['aeEnhanceID'] ?? "masih kosong",'salesfee' => $salesfee]);
                                }
                                // account executive enhance

                                // account reveral enhance
                                if(isset($chkCommissionOverride->ar->enhanceid) && $chkCommissionOverride->ar->enhanceid > 0 && $chkCommissionOverride->ar->enhanceid != '') {
                                    $calculateCommission_arEnhanceID = 0;

                                    if($chargeType == 'invoice') {
                                        foreach($platformPriceArray as $item) {
                                            if($item > $chkCommissionOverride->ar->enhanceid) {
                                                $calculateCommission_arEnhanceID += ($item - $chkCommissionOverride->ar->enhanceid);
                                            }
                                        }
                                    } else if($chargeType == 'topup' && $platformPriceTopup > $chkCommissionOverride->ar->enhanceid) {
                                        $calculateCommission_arEnhanceID = ($platformPriceTopup - $chkCommissionOverride->ar->enhanceid) * $totalLeadTopup;;
                                    }

                                    $overrideCommission['arEnhanceID'] = $calculateCommission_arEnhanceID;
                                    // info("overrideCommission arEnhanceID if end", ['chargeType' => $chargeType,'arEnhanceID' => $overrideCommission['arEnhanceID'] ?? "masih kosong",'chkCommissionOverride->ar->enhanceid' => $chkCommissionOverride->ar->enhanceid,'platformPriceTopup' => $platformPriceTopup,'totalLeadTopup' => $totalLeadTopup,]);
                                } else {
                                    $overrideCommission['arEnhanceID'] = $salesfee;
                                    // info("overrideCommission arEnhanceID else", ['arEnhanceID' => $overrideCommission['arEnhanceID'] ?? "masih kosong",'salesfee' => $salesfee]);
                                }
                                // account executive enhance
                            }
                        } 
                        else 
                        {
                            $chkCommissionOverride = json_decode($sale['custom_commission']);
                            // info('pakai cara custom_commission', ['chkCommissionOverride' => $chkCommissionOverride]);

                            if ($_leadspeek_type == 'local') {
                                $overrideCommission['srSiteID'] = (isset($chkCommissionOverride->sr->siteid) && $chkCommissionOverride->sr->siteid > 0)?$platformfee * (float) $chkCommissionOverride->sr->siteid:$salesfee;
                                $overrideCommission['aeSiteID'] = (isset($chkCommissionOverride->ae->siteid) && $chkCommissionOverride->ae->siteid > 0)?$platformfee * (float) $chkCommissionOverride->ae->siteid:$salesfee;
                                $overrideCommission['arSiteID'] = (isset($chkCommissionOverride->ar->siteid) && $chkCommissionOverride->ar->siteid > 0)?$platformfee * (float) $chkCommissionOverride->ar->siteid:$salesfee;
                                // info('', ['_leadspeek_type' => $_leadspeek_type,'srSiteID' => $overrideCommission['srSiteID'],'aeSiteID' => $overrideCommission['aeSiteID'],'arSiteID' => $overrideCommission['arSiteID'],]);
                            }else if ($_leadspeek_type == 'locator') {
                                $overrideCommission['srSearchID'] = (isset($chkCommissionOverride->sr->searchid) && $chkCommissionOverride->sr->searchid > 0)?$platformfee * (float) $chkCommissionOverride->sr->searchid:$salesfee;
                                $overrideCommission['aeSearchID'] = (isset($chkCommissionOverride->ae->searchid) && $chkCommissionOverride->ae->searchid > 0)?$platformfee * (float) $chkCommissionOverride->ae->searchid:$salesfee;
                                $overrideCommission['arSearchID'] = (isset($chkCommissionOverride->ar->searchid) && $chkCommissionOverride->ar->searchid > 0)?$platformfee * (float) $chkCommissionOverride->ar->searchid:$salesfee;
                                // info('', ['_leadspeek_type' => $_leadspeek_type,'srSearchID' => $overrideCommission['srSearchID'],'aeSearchID' => $overrideCommission['aeSearchID'],'arSearchID' => $overrideCommission['arSearchID'],]);
                            }else if ($_leadspeek_type == 'enhance') {
                                $overrideCommission['srEnhanceID'] = (isset($chkCommissionOverride->sr->enhanceid) && $chkCommissionOverride->sr->enhanceid > 0)?$platformfee * (float) $chkCommissionOverride->sr->enhanceid:$salesfee;
                                $overrideCommission['aeEnhanceID'] = (isset($chkCommissionOverride->ae->enhanceid) && $chkCommissionOverride->ae->enhanceid > 0)?$platformfee * (float) $chkCommissionOverride->ae->enhanceid:$salesfee;
                                $overrideCommission['arEnhanceID'] = (isset($chkCommissionOverride->ar->enhanceid) && $chkCommissionOverride->ar->enhanceid > 0)?$platformfee * (float) $chkCommissionOverride->ar->enhanceid:$salesfee;
                                // info('', ['_leadspeek_type' => $_leadspeek_type,'srEnhanceID' => $overrideCommission['srEnhanceID'],'aeEnhanceID' => $overrideCommission['aeEnhanceID'],'aeEnhanceID' => $overrideCommission['aeEnhanceID'],]);
                            }
                        }
                    }
                    /** OVERRIDE THE COMMISSION IF ENABLED */

                    $tmp = explode(" ",$sale['name']);

                    $details = [
                        'firstname' => $tmp[0],
                        'salesfee'  => $salesfee,
                        'companyname' =>  $sale['company_name'],
                        'clientname' => $_client_name,
                        'campaignname' =>  $_campaign_name,
                        'campaignid' =>$_leadspeek_api_id,
                        'start' => date('Y-m-d H:i:s',strtotime($startdate)),
                        'end' => date('Y-m-d H:i:s',strtotime($enddate)),
                    ];
                    $attachement = array();
        
                    $from = [
                        'address' => 'noreply@exactmatchmarketing.com',
                        'name' => 'Commission Fee',
                        'replyto' => 'support@exactmatchmarketing.com',
                    ];
                    
                    if ($sale['sales_title'] == "Sales Representative") {
                        try {

                            /** CHECK IF OVERRIDE COMMISSION ENABLED */
                            if ($sale['custom_commission_enabled'] == 'T' && $ongoingleads != "") {
                                if ($_leadspeek_type == 'local') {
                                    $salesfee = (isset($overrideCommission['srSiteID']))?number_format($overrideCommission['srSiteID'],2,'.',''):$salesfee;
                                    $details['salesfee'] = $salesfee;
                                }else if ($_leadspeek_type == 'locator') {
                                    $salesfee = (isset($overrideCommission['srSearchID']))?number_format( $overrideCommission['srSearchID'],2,'.',''):$salesfee;
                                    $details['salesfee'] = $salesfee;
                                }else if ($_leadspeek_type == 'enhance') {
                                    $salesfee = (isset($overrideCommission['srEnhanceID']))?number_format( $overrideCommission['srEnhanceID'],2,'.',''):$salesfee;
                                    $details['salesfee'] = $salesfee;
                                }
                            }
                            /** CHECK IF OVERRIDE COMMISSION ENABLED */

                            // info('Fee Sales Representative', ['_leadspeek_type' => $_leadspeek_type,'salesfee' => $salesfee]);
                            if ($salesfee > 0) {
                                // info('process transfer Sales Representative', [
                                //     'amount' => ($salesfee * 100),
                                //     'currency' => 'usd',
                                //     'destination' => $sale['accconnectid'],
                                //     'description' => 'Commision from ' . $sale['company_name'] . '-' . $_client_name  . '-' . $_campaign_name . ' #' . $_leadspeek_api_id,
                                // ]);

                                $transferData = [
                                    'amount' => ($salesfee * 100),
                                    'currency' => 'usd',
                                    'destination' => $sale['accconnectid'],
                                    'transfer_group' => $transferGroup,
                                    'description' => 'Commision from ' . $sale['company_name'] . '-' . $_client_name  . '-' . $_campaign_name . ' #' . $_leadspeek_api_id,
                                ];

                                if(is_object($payment_intent) && isset($payment_intent->charges->data[0]->id) && $payment_intent->charges->data[0]->id != '') {
                                    $transferData['source_transaction'] = $payment_intent->charges->data[0]->id;
                                }

                                // info(['action' => 'transfer sales representative','transferData' => $transferData]);
                                $transferSales = $stripe->transfers->create($transferData);
                                // info('result sales representative', ['transferSales' => $transferSales]);

                                // $transferSales = $stripe->transfers->create([
                                //     'amount' => ($salesfee * 100),
                                //     'currency' => 'usd',
                                //     'destination' => $sale['accconnectid'],
                                //     'source_transaction' => $payment_intent->charges->data[0]->id,
                                //     'transfer_group' => $transferGroup,
                                //     'description' => 'Commision from ' . $sale['company_name'] . '-' . $_client_name  . '-' . $_campaign_name . ' #' . $_leadspeek_api_id,
                                // ]);

                                if (isset($transferSales->destination_payment)) {
                                    $despay = $transferSales->destination_payment;

                                    $transferSalesDesc =  $stripe->charges->update($despay,
                                            [
                                                'description' => 'Commision from ' . $sale['company_name'] . '-' . $_client_name  . '-' . $_campaign_name . ' #' . $_leadspeek_api_id,
                                            ],['stripe_account' => $sale['accconnectid']]);
                                }
                                
                                $srID = $sale['sales_id'];
                                $this->send_email(array($sale['email']),$from,'Commission fee from ' . $sale['company_name'] . ' #' . $_leadspeek_api_id . '(SR)',$details,$attachement,'emails.salesfee',$sale['company_root_id']);
                            }

                        }catch (Exception $e) {
                            //return json_encode(array('result'=>'failed','payment_intentID'=>'','srID'=>'','aeID'=>'','salesfee'=>'','error'=>'SE error transfer'));
                            $this->send_notif_stripeerror('SE error transfer','SE error transfer for ' . $sale['company_name'] . ' #' . $_leadspeek_api_id,$sale['company_root_id']);
                        }

                    }else if ($sale['sales_title'] == "Account Executive") {
                        try {

                            /** CHECK IF OVERRIDE COMMISSION ENABLED */
                            if ($sale['custom_commission_enabled'] == 'T' && $ongoingleads != "") {
                                if ($_leadspeek_type == 'local') {
                                    $salesfee = (isset($overrideCommission['aeSiteID']))?number_format( $overrideCommission['aeSiteID'],2,'.',''):$salesfee;
                                    $details['salesfee'] = $salesfee;
                                }else if ($_leadspeek_type == 'locator') {
                                    $salesfee = (isset($overrideCommission['aeSearchID']))?number_format( $overrideCommission['aeSearchID'],2,'.',''):$salesfee;
                                    $details['salesfee'] = $salesfee;
                                }else if ($_leadspeek_type == 'enhance') {
                                    $salesfee = (isset($overrideCommission['aeEnhanceID']))?number_format( $overrideCommission['aeEnhanceID'],2,'.',''):$salesfee;
                                    $details['salesfee'] = $salesfee;
                                }
                            }
                            /** CHECK IF OVERRIDE COMMISSION ENABLED */

                            // info('Fee Account Executive', ['_leadspeek_type' => $_leadspeek_type,'salesfee' => $salesfee]);
                            if ($salesfee > 0) {
                                // info('process transfer Account Executive', [
                                //     'amount' => ($salesfee * 100),
                                //     'currency' => 'usd',
                                //     'destination' => $sale['accconnectid'],
                                //     'description' => 'Commision from ' . $sale['company_name'] . '-' . $_client_name  . '-' . $_campaign_name . ' #' . $_leadspeek_api_id,
                                // ]);

                                $transferData = [
                                    'amount' => ($salesfee * 100),
                                    'currency' => 'usd',
                                    'destination' => $sale['accconnectid'],
                                    'transfer_group' => $transferGroup,
                                    'description' => 'Commision from ' . $sale['company_name'] . '-' . $_client_name  . '-' . $_campaign_name . ' #' . $_leadspeek_api_id,
                                ];

                                if(is_object($payment_intent) && isset($payment_intent->charges->data[0]->id) && $payment_intent->charges->data[0]->id != '') {
                                    $transferData['source_transaction'] = $payment_intent->charges->data[0]->id;
                                }

                                // info(['action' => 'transfer account executive','transferData' => $transferData]);
                                $transferSales = $stripe->transfers->create($transferData);
                                // info('result account executive', ['transferSales' => $transferSales]);

                                // $transferSales = $stripe->transfers->create([
                                //     'amount' => ($salesfee * 100),
                                //     'currency' => 'usd',
                                //     'destination' => $sale['accconnectid'],
                                //     'source_transaction' => $payment_intent->charges->data[0]->id,
                                //     'transfer_group' => $transferGroup,
                                //     'description' => 'Commision from ' . $sale['company_name'] . '-' . $_client_name  . '-' . $_campaign_name . ' #' . $_leadspeek_api_id,
                                // ]);

                                if (isset($transferSales->destination_payment)) {
                                    $despay = $transferSales->destination_payment;

                                    $transferSalesDesc =  $stripe->charges->update($despay,
                                            [
                                                'description' => 'Commision from ' . $sale['company_name'] . '-' . $_client_name  . '-' . $_campaign_name . ' #' . $_leadspeek_api_id,
                                            ],['stripe_account' => $sale['accconnectid']]);
                                }

                                $aeID = $sale['sales_id'];
                                $this->send_email(array($sale['email']),$from,'Commission fee from ' . $sale['company_name'] . ' #' . $_leadspeek_api_id . '(AE)',$details,$attachement,'emails.salesfee',$sale['company_root_id']);
                            }

                        }catch (Exception $e) {
                            //return json_encode(array('result'=>'failed','payment_intentID'=>'','srID'=>'','aeID'=>'','salesfee'=>'','error'=>'AE error transfer'));
                            $this->send_notif_stripeerror('AE error transfer','AE error transfer for ' . $sale['company_name'] . ' #' . $_leadspeek_api_id,$sale['company_root_id']);
                        }
                        
                    }else if ($sale['sales_title'] == "Account Referral") {
                        try {

                            /** CHECK IF OVERRIDE COMMISSION ENABLED */
                            if ($sale['custom_commission_enabled'] == 'T' && $ongoingleads != "") {
                                if ($_leadspeek_type == 'local') {
                                    $salesfee = (isset($overrideCommission['arSiteID']))?number_format( $overrideCommission['arSiteID'],2,'.',''):$salesfee;
                                    $details['salesfee'] = $salesfee;
                                }else if ($_leadspeek_type == 'locator') {
                                    $salesfee = (isset($overrideCommission['arSearchID']))?number_format( $overrideCommission['arSearchID'],2,'.',''):$salesfee;
                                    $details['salesfee'] = $salesfee;
                                }else if ($_leadspeek_type == 'enhance') {
                                    $salesfee = (isset($overrideCommission['arEnhanceID']))?number_format( $overrideCommission['arEnhanceID'],2,'.',''):$salesfee;
                                    $details['salesfee'] = $salesfee;
                                }
                            }
                            /** CHECK IF OVERRIDE COMMISSION ENABLED */

                            // info('Fee Account Referral', ['_leadspeek_type' => $_leadspeek_type,'salesfee' => $salesfee]);
                            if ($salesfee > 0) {
                                // info('process transfer Account Referral', [
                                //     'amount' => ($salesfee * 100),
                                //     'currency' => 'usd',
                                //     'destination' => $sale['accconnectid'],
                                //     'description' => 'Commision from ' . $sale['company_name'] . '-' . $_client_name  . '-' . $_campaign_name . ' #' . $_leadspeek_api_id,
                                // ]);

                                $transferData = [
                                    'amount' => ($salesfee * 100),
                                    'currency' => 'usd',
                                    'destination' => $sale['accconnectid'],
                                    'transfer_group' => $transferGroup,
                                    'description' => 'Commision from ' . $sale['company_name'] . '-' . $_client_name  . '-' . $_campaign_name . ' #' . $_leadspeek_api_id,
                                ];

                                if(is_object($payment_intent) && isset($payment_intent->charges->data[0]->id) && $payment_intent->charges->data[0]->id != '') {
                                    $transferData['source_transaction'] = $payment_intent->charges->data[0]->id;
                                }

                                // info(['action' => 'transfer account reveral','transferData' => $transferData]);
                                $transferSales = $stripe->transfers->create($transferData);
                                // info('result account reveral', ['transferSales' => $transferSales]);

                                // $transferSales = $stripe->transfers->create([
                                //     'amount' => ($salesfee * 100),
                                //     'currency' => 'usd',
                                //     'destination' => $sale['accconnectid'],
                                //     'source_transaction' => $payment_intent->charges->data[0]->id,
                                //     'transfer_group' => $transferGroup,
                                //     'description' => 'Commision from ' . $sale['company_name'] . '-' . $_client_name  . '-' . $_campaign_name . ' #' . $_leadspeek_api_id,
                                // ]);

                                if (isset($transferSales->destination_payment)) {
                                    $despay = $transferSales->destination_payment;

                                    $transferSalesDesc =  $stripe->charges->update($despay,
                                            [
                                                'description' => 'Commision from ' . $sale['company_name'] . '-' . $_client_name  . '-' . $_campaign_name . ' #' . $_leadspeek_api_id,
                                            ],['stripe_account' => $sale['accconnectid']]);
                                }

                                $arID = $sale['sales_id'];
                                $this->send_email(array($sale['email']),$from,'Commission fee from ' . $sale['company_name'] . ' #' . $_leadspeek_api_id . '(AR)',$details,$attachement,'emails.salesfee',$sale['company_root_id']);
                            }
                        }catch (Exception $e) {
                            //return json_encode(array('result'=>'failed','payment_intentID'=>'','srID'=>'','aeID'=>'','salesfee'=>'','error'=>'AE error transfer'));
                            $this->send_notif_stripeerror('ACREF error transfer','ACREF error transfer for ' . $sale['company_name'] . ' #' . $_leadspeek_api_id,$sale['company_root_id']);
                        }
                        
                    }

                }
            }

            Log::info([
                'payment_status _agency' => $statusPayment,
                'failed_total_amount' => $platformfee
            ]);
            //// check if agency payment failed???
            if($statusPayment == 'failed') 
            {   
                $idUsr = $chkUser[0]['id'];

                $leadsuser = LeadspeekUser::select('leadspeek_users.leadspeek_type','leadspeek_users.active','leadspeek_users.disabled','leadspeek_users.trysera','leadspeek_users.leadspeek_organizationid','leadspeek_users.leadspeek_campaignsid','users.customer_payment_id','leadspeek_users.user_id','users.company_id')
                                    ->join('users','leadspeek_users.user_id','=','users.id')
                                    ->where('leadspeek_users.leadspeek_api_id','=',$_leadspeek_api_id)
                                    ->get();
                if(count($leadsuser) > 0) 
                {
                    foreach($leadsuser as $lds) 
                    {

                        if(!($lds['active'] == "F" && $lds['disabled'] == "T" && $lds['active_user'] == "F")) 
                        {
                            /** UPDATE USER CARD STATUS */
                            $updateUser = User::find($idUsr);

                            $failedTotalAmount = $platformfee;
                            if($_campaign_paymentterm != 'Prepaid' && $topupAgencyExists && $remainingPlatformfee > 0)
                            {
                                $failedTotalAmount = $remainingPlatformfee;
                            }

                            $failedCampaignID = $_leadspeek_api_id;

                            if(trim($updateUser->failed_total_amount) != '') 
                            {
                                $failedTotalAmount = $updateUser->failed_total_amount . '|' . $failedTotalAmount;
                            }
                            if(trim($updateUser->failed_campaignid) != '') 
                            {
                                $failedCampaignID = $updateUser->failed_campaignid . '|' . $failedCampaignID;
                            }

                            // info('failed 1.1', ['topupAgencyExists' => $topupAgencyExists, 'remainingPlatformfee' => $remainingPlatformfee, '_campaign_paymentterm' => $_campaign_paymentterm]);
                            // (jika tidak pakai topup agency) || (pakai topup agency && masih ada sisa platformfee && paymentterm selain prepaid)
                            // if(($topupAgencyExists === false && $_campaign_paymentterm != 'Prepaid') || ($topupAgencyExists === true && $_campaign_paymentterm != 'Prepaid' && $remainingPlatformfee > 0))
                            if(($_campaign_paymentterm != 'Prepaid') && ((!$topupAgencyExists) || ($topupAgencyExists && $remainingPlatformfee > 0)))
                            {
                                $updateUser->failed_total_amount = $failedTotalAmount;
                                $updateUser->failed_campaignid = $failedCampaignID; 
                            }
                            $updateUser->payment_status = 'failed';
                            $updateUser->save();
                            /** UPDATE USER CARD STATUS */
                        }
                    }
                }

                // jika agency manual bill && pakai agency prepaid && chargeCampaignWithPrepaid = 'paid'
                if($agencyManualBill == 'T' && $topupAgencyExists && $chargeStatusWithPrepaid == 'paid')
                {
                    $statusPayment = 'paid';
                }
                // info('check statusPayment final', ['statusPayment' => $statusPayment, 'agencyManualBill' => $agencyManualBill,'topupAgencyExists' => $topupAgencyExists,'chargeStatusWithPrepaid' => $chargeStatusWithPrepaid]);
            }

            $_paymentID = ($topupAgencyExists === true) ? "topup_agency" : ((isset($payment_intent->id)) ? $payment_intent->id : '');
            return json_encode(array('result'=>'success','payment_intentID'=>$_paymentID,'srID'=>$srID,'aeID'=>$aeID,'arID'=>$arID,'salesfee'=>$salesfee,'error'=>'','topup_agencies_id'=>$topup_agencies_id,'leadspeek_invoices_id'=>$leadspeek_invoices_id,'statusPayment'=>$statusPayment));
        }

        return json_encode(array('result'=>'failed','payment_intentID'=>'','srID'=>'','aeID'=>'','arID'=>'','salesfee'=>'','error'=>'','topup_agencies_id'=>'','leadspeek_invoices_id'=>'','statusPayment'=>''));
    }
    
    public function check_stripe_customer_platform_exist($user,$accConID) {
        $custStripeID = $user[0]['customer_payment_id'];
        $companyID = $user[0]['company_id'];
        $usrID = $user[0]['id'];
        
        /** GET STRIPE KEY */
        $stripeseckey = config('services.stripe.secret');
        $stripepublish = $this->getcompanysetting($user[0]['company_root_id'],'rootstripe');
        if ($stripepublish != '') {
            $stripeseckey = (isset($stripepublish->secretkey))?$stripepublish->secretkey:"";
        }
        /** GET STRIPE KEY */

        $stripe = new StripeClient([
            'api_key' => $stripeseckey,
            'stripe_version' => '2020-08-27'
        ]);

        try {
            $custInfo = $stripe->customers->retrieve($custStripeID,[],['stripe_account' => $accConID]);
            return json_encode(array('result'=>'success','params'=>'','custStripeID'=>$custInfo->id,'CardID'=>$custInfo->default_source));
        }catch(Exception $e) {
            try{
                $custInfo = $stripe->customers->retrieve($custStripeID,[]);

                $custStripeID = (isset($custInfo->id))?$custInfo->id:'';

                $token = $stripe->tokens->create(
                    ['customer' => $custStripeID],
                    ['stripe_account' => $accConID],
                );

                $company_name = "";
                
                $companyrslt = Company::select('company_name','simplifi_organizationid')
                                    ->where('id','=',$companyID)
                                    ->get();

                if(count($companyrslt) > 0) {
                    $company_name = $companyrslt[0]['company_name'];
                }

                $name = (isset($custInfo->name))?$custInfo->name:'';
                $phone = (isset($custInfo->phone))?$custInfo->phone:'';
                $email = (isset($custInfo->email))?$custInfo->email:'';
                
                $newCardID = $stripe->customers->create(
                    [   
                        'name' => $name,
                        'phone' => $phone,
                        'email' => $email,
                        'description' => $company_name,
                        'source' => $token
                    ],
                    ['stripe_account' => $accConID],
                );

                /** UPDATE USER STRIPE INFO */
                $usrupdte = User::find($usrID);
                $usrupdte->customer_payment_id = $newCardID->id;
                $usrupdte->customer_card_id = $newCardID->default_source;
                $usrupdte->save();
                /** UPDATE USER STRIPE INFO */

                return json_encode(array('result'=>'success','params'=>'','custStripeID'=>$newCardID->id,'CardID'=>$newCardID->default_source));

            }catch(Exception $e) {
                return json_encode(array('result'=>'failed','params'=>'','custStripeID'=>'','CardID'=>''));
            }
            
        }
    }
    /** FOR STRIPE THINGS */

    public function checkwhitelabellingpackage($agencyID) {
        $companyStripe = CompanyStripe::where('company_id','=',$agencyID)
                        ->get();

        $getIsWhitelabelingByCompany = Company::select('is_whitelabeling')->where('id', '=', $agencyID)->first();

        if(count($companyStripe) > 0) {
            /** GET PACKAGE WHITELABELLING OR NON */
            if (trim($companyStripe[0]->package_id) != '') {
                $whitelabellingpackage = 'F';
                $chkPackage = PackagePlan::select('whitelabelling')
                                        ->where('package_id','=',trim($companyStripe[0]->package_id))
                                        ->get();
                foreach($chkPackage as $chkpak) {
                    $whitelabellingpackage = $chkpak['whitelabelling'];
                }

                $is_whitelabeling = $getIsWhitelabelingByCompany->is_whitelabeling ? $getIsWhitelabelingByCompany->is_whitelabeling : $whitelabellingpackage;
                if ($is_whitelabeling == 'T') {
                    return true;
                }else{
                    return false;
                }

            }else{
                return false;
            }
            /** GET PACKAGE WHITELABELLING OR NON */
        }else{
            return false;
        }
    }

    /** FOR ENDATO, TOWER DATA AND OTHER API CALL */
    public function getDataEnrichment($firstname,$lastname,$email,$phone = '',$address = '',$city = '',$state = '',$zip = '') {
        $http = new Client();

        $appkey = config('services.endato.appkey');
        $apppass = config('services.endato.apppass');

        $apiURL =  config('services.endato.endpoint') . 'Contact/Enrich';

        $email = strtolower($email);
        $email = str_replace(' ','',$email);
        
        try {
            $options = [
                'headers' => [
                    'accept' => 'application/json',
                    'content-type' => 'application/json',
                    'galaxy-ap-name' => $appkey,
                    'galaxy-ap-password' => $apppass,
                    'galaxy-search-type' => 'DevAPIContactEnrich',
                ],
                'json' => [
                    "FirstName" => $firstname,
                    "LastName" => $lastname,
                    "Email" => $email,
                    "Phone" => $phone,
                    "Address" => [
                        "addressLine1" => $address,
                        "addressLine2" => $city . ", " . $state . " " . $zip,
                    ],
                    
                ]
            ]; 
           
            $response = $http->post($apiURL,$options);
            
            return json_decode($response->getBody());
        }catch (\GuzzleHttp\Exception\BadResponseException $e) {
            if ($e->getCode() === 400) {
                Log::warning("Endato Error 400 when find :" . $firstname . ' - ' . $lastname . ' - ' . $email . ' - ' .  $phone);
                return "";
            } else if ($e->getCode() === 401) {
                Log::warning("Endato Error 401 when find :" . $firstname . ' - ' . $lastname . ' - ' . $email . ' - ' .  $phone);
                return "";
            }else {
                Log::warning("Endato Error " . $e->getCode() . " when find :" . $firstname . ' - ' . $lastname . ' - ' . $email . ' - ' .  $phone);
                return "";
            }
        }catch (\GuzzleHttp\Exception\ClientException $e) {
            $response = $e->getResponse();
            $responseBodyAsString = $response->getBody()->getContents();
            Log::warning("Endato Client Exception : " . $responseBodyAsString);
            return "";
        }catch (\GuzzleHttp\Exception\ServerException $e) {
            $response = $e->getResponse();
            $responseBodyAsString = $response->getBody()->getContents();
            Log::warning("Endato Server Exception : " . $responseBodyAsString);
            return "";
        }


    }

    public function getTowerData($method="postal",$md5_email = "") {
        $http = new Client();

        $appkey = config('services.tower.postal');
        if ($method == "md5") {
            $appkey = config('services.tower.md5');
        }

        $apiURL =  config('services.tower.endpoint') . '?api_key=' . $appkey . '&md5_email=' . $md5_email;
        $options = [];
        $response = $http->get($apiURL,$options);
        return json_decode($response->getBody());
    }
    /** FOR ENDATO, TOWER DATA AND OTHER API CALL */

    public function generateLeadSpeekIDUniqueNumber() {
        $randomCode = mt_rand(10000000,99999999);
        while(LeadspeekUser::where('leadspeek_api_id','=',$randomCode)->count() > 0) {
                $randomCode = mt_rand(10000000,99999999);
        }

        return $randomCode;
    }

    public function getCompanyRootInfo($companyID) {
        $company = Company::select('id','logo','simplifi_organizationid','domain','subdomain','sidebar_bgcolor','template_bgcolor','box_bgcolor','text_color','link_color','font_theme','login_image','client_register_image','agency_register_image','company_name','phone','company_address','company_city','company_zip','company_state_name')
                            ->where('id','=',$companyID)
                            ->where('approved','=','T')
                            ->get();
        if(count($company) > 0) {
            return $company[0];
        }else{
            return array();
        }
    }

    public function getDefaultDomainEmail($companyID) {
        $defaultdomain = "sitesettingsapi.com";
        $customsmtp = CompanySetting::where('company_id',trim($companyID))->whereEncrypted('setting_name','customsmtpmenu')->get();
        if (count($customsmtp) > 0) {
            $csmtp = json_decode($customsmtp[0]['setting_value']);
            if (!isset($csmtp->default)) {
                $csmtp->default = false;
            }

            if (!$csmtp->default) {
                $tmpdomain = explode('@',$csmtp->username);
                $defaultdomain = $tmpdomain[1];
            }else{
                $rootsmtp = CompanySetting::where('company_id',$companyID)->whereEncrypted('setting_name','rootsmtp')->get();
                if (count($rootsmtp) > 0) {
                    $smtproot = json_decode($rootsmtp[0]['setting_value']);
                    $tmpdomain = explode('@',$smtproot->username);
                    $defaultdomain = $tmpdomain[1];
                }
            }
        }else{
            $rootsmtp = CompanySetting::where('company_id',$companyID)->whereEncrypted('setting_name','rootsmtp')->get();
            if (count($rootsmtp) > 0) {
                $smtproot = json_decode($rootsmtp[0]['setting_value']);
                $tmpdomain = explode('@',$smtproot->username);
                $defaultdomain = $tmpdomain[1];
            }
        }

        return $defaultdomain;
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

    public function registerGlobal(Request $request,$_product_id='',$_transaction_subscription_id='',$_lead_id = '')
    {   
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:6',
        ]);
       
        $ownedcompanyid = (isset($request->ownedcompanyid))?$request->ownedcompanyid:null;

        if ($request->gtoken != md5('kartrawebhook')) {
            if (config('services.recaptcha.enabled') && !$this->checkRecaptcha($request->gtoken, $request->ip(),$ownedcompanyid)) {
                return response()->json(array('result'=>'error','message'=>'Recaptcha Invalid','errors'=>array('captcha'=>["Recaptcha Invalid"])), 500);
            }
        }
        
        $domainName = (isset($request->domainName))?$request->domainName:'';
        $userType = (isset($request->userType))?$request->userType:'client';
        $phonenum = (isset($request->phonenum))?$request->phonenum:'';
        $AccountType = 'Account';
        $companyName = (isset($request->companyname))?$request->companyname:'';
        $idsys = (isset($request->idsys))?$request->idsys:'';
        $DownlineSubDomain = '';
        $paymentgateway = (isset($request->paymentgateway))?$request->paymentgateway:'stripe';
        $packageID = (isset($request->packageID) && trim($request->packageID) != '')?$request->packageID:$_product_id;
        $transactionID = (isset($request->transactionID) && trim($request->transactionID) != '')?$request->transactionID:$_transaction_subscription_id;
        $leadID = (isset($request->leadID) && trim($request->leadID) != '')?$request->leadID:$_lead_id;

        $isAdmin = 'F';
        $sortorder = 0;

        $defaultadmin = 'F';
        $customercare = 'F';

        $newCompanyID = null;

        if ($userType == 'userdownline') {
            $isAdmin = 'T';
            $defaultadmin = 'T';
            $customercare = 'T';
            $AccountType = 'Agency account';
        }
        
        /** CHECK IF EMAIL ALREADY EXIST */
        $chkusrname = $request->email;
        $chkEmailExist = User::where(function ($query) use ($ownedcompanyid,$chkusrname) {
            $query->where('company_id','=',$ownedcompanyid)
                    ->where('email',Encrypter::encrypt($chkusrname))
                    ->where('user_type','=','user');
            })->orWhere(function ($query) use ($ownedcompanyid,$chkusrname) {
                $query->where('company_id','=',$ownedcompanyid)
                        ->where('email',Encrypter::encrypt($chkusrname))
                        ->where('user_type','=','userdownline');
            })->orWhere(function ($query) use ($ownedcompanyid,$chkusrname) {
                $query->where('company_parent','=',$ownedcompanyid)
                        ->where('email',Encrypter::encrypt($chkusrname))
                        ->where('user_type','=','client');
            })
            ->where('active','T')
            ->get();

        //$chkEmailExist = User::where('email','=',trim(Encrypter::encrypt($request->email)))->get();
        if (count($chkEmailExist) > 0) {
            return response()->json(array('result'=>'error','message'=>'email exist','error'=>''));
        }
        /** CHECK IF EMAIL ALREADY EXIST */

        /** GET ROOT SYS CONF */
        $confAppDomain =  config('services.application.domain');
        if ($idsys != "") {
            $conf = $this->getCompanyRootInfo($idsys);
            $confAppDomain = $conf['domain'];
        }
        /** GET ROOT SYS CONF */

        /** CHECK IF REGISTER AS AGENCY OR USERDOWNLINE */
        if ($userType == 'userdownline' && trim($companyName) != '') {
            $_comname = explode(' ',strtolower($companyName));
            $subresult = '';

            foreach ($_comname as $w) {
                $subresult .= mb_substr($w, 0, 1);
            }
            $subresult = preg_replace('/[^a-zA-Z0-9]/', '', $subresult);
            
            $DownlineSubDomain = $subresult . date('ynjis') . '.' . $confAppDomain;

            while ($this->check_subordomain_exist($DownlineSubDomain)) {
                $DownlineSubDomain = $subresult . date('ynjis') . '.' . $confAppDomain;
            }
            
            /** CREATE SIMPLIFI CLIENT AS AGENCY */
            $defaultParentOrganization = config('services.sifidefaultorganization.organizationid');

            $simplifiOrganizationID = Company::select('simplifi_organizationid')
                                                ->where('id','=',$ownedcompanyid)
                                                ->get();

            if(count($simplifiOrganizationID) > 0) {
                if ($simplifiOrganizationID[0]['simplifi_organizationid'] != '') {
                    $defaultParentOrganization = $simplifiOrganizationID[0]['simplifi_organizationid'];
                }
            }

            $sifiEMMStatus = "[AGENCY]";
            if (config('services.appconf.devmode') === true) {
                $sifiEMMStatus = "[AGENCY BETA]";
            }

            $simplifiOrganizationID = $this->createOrganization(trim($companyName) . ' ' . $sifiEMMStatus,$defaultParentOrganization);
            //$simplifiOrganizationID = '000000';
            if ($simplifiOrganizationID != "" && $simplifiOrganizationID != null) {
            /** CREATE SIMPLIFI CLIENT AS AGENCY */

                /** UPDATE DEFAULT PAYMENT IF SETTING EXIST IF NOT THEN USE DEFAULT DB */
                $_paymentterm_default = "Weekly";

                $getRootSetting = $this->getcompanysetting($idsys,'rootsetting');
                if ($getRootSetting != '') {
                    if (isset($getRootSetting->defaultpaymentterm) && $getRootSetting->defaultpaymentterm != '') {
                        $_paymentterm_default = trim($getRootSetting->defaultpaymentterm);
                    }
                }
                /** UPDATE DEFAULT PAYMENT IF SETTING EXIST IF NOT THEN USE DEFAULT DB */

                $newCompany = Company::create([
                    'company_name' => $companyName,
                    'company_city' => '',
                    'company_zip' => '',
                    'company_state_code' => '',
                    'company_state_name' => '', 
                    'simplifi_organizationid' => $simplifiOrganizationID,
                    'domain' => '',
                    'subdomain' => $DownlineSubDomain,
                    'sidebar_bgcolor' => '',
                    'template_bgcolor' => '',
                    'box_bgcolor' => '',
                    'font_theme' => '',
                    'login_image' => '',
                    'client_register_image' => '',
                    'agency_register_image' => '',
                    'approved' => 'T',
                    'paymentgateway' => $paymentgateway,
                    'paymentterm_default' => $_paymentterm_default
                ]);

                $newCompanyID = $newCompany->id;

                /** IF KARTRA THEN SHOULD CREATE DEFAULT COMPANY STRIPE WITH PACKAGE ID */
                if ($paymentgateway == 'kartra') {
                    $chkComStripeExist = CompanyStripe::select('id')->where('company_id','=',$newCompanyID)->get();
                    if (count($chkComStripeExist) == 0) {
                        $createCompanyStripe = CompanyStripe::create([
                            'company_id' => $newCompanyID,
                            'acc_connect_id' => '',
                            'acc_prod_id' => '',
                            'acc_email' => '',
                            'acc_ba_id' => '',
                            'acc_holder_name' => '',
                            'acc_holder_type' => '',
                            'ba_name' => '',
                            'ba_route' => '',
                            'package_id' => $packageID,
                            'subscription_id' => $transactionID,
                            'subscription_item_id' => $leadID,
                            'status_acc' => '',
                            'ipaddress' => '',
                        ]);
                    }
                }
                /** CREATE DEFAULT PRICE FOR AGENCY */

                    $comset_val = [
                        "local" => [
                            "Monthly" => [
                                    "LeadspeekCostperlead" => '0.65',
                                    "LeadspeekMinCostMonth" => '0',
                                    "LeadspeekPlatformFee" => '0'
                            ],
                            "OneTime" => [
                                "LeadspeekCostperlead" => "0.65",
                                "LeadspeekMinCostMonth" => "0",
                                "LeadspeekPlatformFee" => "0",
                            ],
                            "Weekly" => [
                                "LeadspeekCostperlead" => "0.65",
                                "LeadspeekMinCostMonth" => "0",
                                "LeadspeekPlatformFee" => "0",
                            ]
                        ],

                        "locator" => [
                            "Monthly" => [
                                    "LocatorCostperlead" => '0.95',
                                    "LocatorMinCostMonth" => '0',
                                    "LocatorPlatformFee" => '0'
                            ],
                            "OneTime" => [
                                "LocatorCostperlead" => "0.95",
                                "LocatorMinCostMonth" => "0",
                                "LocatorPlatformFee" => "0",
                            ],
                            "Weekly" => [
                                "LocatorCostperlead" => "0.95",
                                "LocatorMinCostMonth" => "0",
                                "LocatorPlatformFee" => "0",
                            ]
                        ],

                        "enhance" => [
                            "Monthly" => [
                                "EnhanceCostperlead" => '0.95',
                                "EnhanceMinCostMonth" => '0',
                                "EnhancePlatformFee" => '0'
                            ],
                            "OneTime" => [
                                "EnhanceCostperlead" => "0.95",
                                "EnhanceMinCostMonth" => "0",
                                "EnhancePlatformFee" => "0",
                            ],
                            "Weekly" => [
                                "EnhanceCostperlead" => "0.95",
                                "EnhanceMinCostMonth" => "0",
                                "EnhancePlatformFee" => "0",
                            ]
                        ]
                    ];

                    /** GET DEFAULT ROOT COST AGENCY */
                    $comset_val = $this->getcompanysetting($idsys,'rootcostagency');
                    /** GET DEFAULT ROOT COST AGENCY */
                    
                    $createsetting = CompanySetting::create([
                        'company_id' => $newCompanyID,
                        'setting_name' => 'costagency',
                        'setting_value' => json_encode($comset_val),
                    ]);
                
                /* SAVE PAYMENTTERMCONTROL */
                try {
                    $setting_rootpaymentterm = $this->getcompanysetting($idsys, 'rootpaymentterm');
                    $setting_rootpaymenttermnewagencies = $this->getcompanysetting($idsys, 'rootPaymentTermsNewAgencies');
                    if (!empty($setting_rootpaymenttermnewagencies) && !empty( $setting_rootpaymentterm)) {
                        $paymentterm = json_decode(json_encode($setting_rootpaymenttermnewagencies), true);
                        $allFalse = true;
                        foreach ($paymentterm as $term) {
                            if ($term['status'] === true) {
                                $allFalse = false;
                                break;
                            }
                        }
                        if (!$allFalse) {
                            $paymentterm = [
                                "SelectedPaymentTerm" => $setting_rootpaymenttermnewagencies,
                            ];
                            $createsetting = CompanySetting::create([
                                'company_id' => $newCompanyID,
                                'setting_name' => 'agencypaymentterm',
                                'setting_value' => json_encode($paymentterm),
                            ]);
                        }
                    }else {
                        $paymentterm = [];
                        $paymentTermFormat= [];
                        foreach ($setting_rootpaymentterm->PaymentTerm as $pt) {
                            $paymentTermFormat[] = [
                                'term' => $pt->value,
                                'status' => true
                            ];
                        }
                        $paymentterm['SelectedPaymentTerm'] = $paymentTermFormat;
            
                        $createsetting = CompanySetting::create([
                            'company_id' => $newCompanyID,
                            'setting_name' => 'agencypaymentterm',
                            'setting_value' => json_encode($paymentterm),
                        ]);
                    }
                } catch (\Throwable $th) {
                    // return response()->json(['error' => $th->getMessage()]);
                }
                /* SAVE PAYMENTTERMCONTROL */

                //SAVE AGENCY MODULES SETTING
                $modules = [];
                $root_default_setting = $this->getcompanysetting($idsys, 'rootdefaultmodules');
                if (!empty($root_default_setting) && isset($root_default_setting->DefaultModules)) {
                    $modules = [
                        "SelectedModules" => $root_default_setting->DefaultModules,
                    ];
                }else {
                    $root_agencysidebar = $this->getcompanysetting($idsys, 'rootcustomsidebarleadmenu');
                    $agencysidebar_frmt = [];
                    foreach ($root_agencysidebar as $key => $value) {
                            $agencysidebar_frmt[] = [
                                'type' => $key,
                                'status' => true
                            ];
                    } 
                    $modules['SelectedModules'] = $agencysidebar_frmt;
                }

                if ($modules != []) {
                    $createsetting = CompanySetting::create([
                    'company_id' => $newCompanyID,
                    'setting_name' => 'agencysidebar',
                    'setting_value' => json_encode($modules),
                    ]);
                }
                //SAVE AGENCY MODULES SETTING

                /** CREATE DEFAULT PRICE FOR AGENCY */
            }

        }
        /** CHECK IF REGISTER AS AGENCY OR USERDOWNLINE */

        /** CHECK IF CLIENT */
        if ($userType == 'client') {
            $getparentCompany = Company::select('domain','subdomain','status_domain')
                                        ->where('approved','=','T')
                                        ->where('id','=',$ownedcompanyid)
                                        ->get();
            if (count($getparentCompany) > 0 && $DownlineSubDomain == '') {
                if (trim($getparentCompany[0]['domain']) != '' &&  trim($getparentCompany[0]['status_domain']) == 'ssl_acquired') {
                    /** CHECK IF DOMAIN ALREADY POINTED TO OUR IP */
                    $ip = gethostbyname(trim($getparentCompany[0]['domain']));
                    if ($ip == '157.230.213.72') {
                        $DownlineSubDomain = $getparentCompany[0]['domain'];
                    }else{
                        $DownlineSubDomain = $getparentCompany[0]['subdomain'];
                    }
                    /** CHECK IF DOMAIN ALREADY POINTED TO OUR IP */
                }else{
                    $DownlineSubDomain = $getparentCompany[0]['subdomain'];
                }
            }

            if ($paymentgateway == 'kartra') {
                $newCompany = Company::create([
                    'company_name' => $companyName,
                    'company_city' => '',
                    'company_zip' => '',
                    'company_state_code' => '',
                    'company_state_name' => '', 
                    'simplifi_organizationid' => '',
                    'domain' => '',
                    'subdomain' => '',
                    'sidebar_bgcolor' => '',
                    'template_bgcolor' => '',
                    'box_bgcolor' => '',
                    'font_theme' => '',
                    'login_image' => '',
                    'client_register_image' => '',
                    'agency_register_image' => '',
                    'approved' => 'T',
                    'paymentgateway' => $paymentgateway,
                ]);

                $newCompanyID = $newCompany->id;

                $chkComStripeExist = CompanyStripe::select('id')->where('company_id','=',$newCompanyID)->get();
                if (count($chkComStripeExist) == 0) {
                    $createCompanyStripe = CompanyStripe::create([
                        'company_id' => $newCompanyID,
                        'acc_connect_id' => '',
                        'acc_prod_id' => '',
                        'acc_email' => '',
                        'acc_ba_id' => '',
                        'acc_holder_name' => '',
                        'acc_holder_type' => '',
                        'ba_name' => '',
                        'ba_route' => '',
                        'package_id' => $packageID,
                        'subscription_id' => $transactionID,
                        'subscription_item_id' => $leadID,
                        'status_acc' => '',
                        'ipaddress' => '',
                    ]);
                }
            }
            
        }
        /** CHECK IF CLIENT */
        $ipAddress = $request->ip();

        if ($request->ip() == null) {
            $ipAddress = "";
        }

        /* TWO FACTOR AUTH */
        $tfa_active = 0;
        $tfa_type = null;
        if ($userType == 'userdownline') {
            $tfa_active = 1;
            $tfa_type = 'email';
        }
        /* TWO FACTOR AUTH */
       
        $usr = User::create([
            'name' => $request->name,
            'email' => strtolower($request->email),
            'phonenum' => $phonenum,
            'address' => (isset($request->address))?$request->address:'',
            'city' => (isset($request->city))?$request->city:'',
            'zip' => (isset($request->zip))?$request->zip:'',
            'state_code' => (isset($request->state_code))?$request->state_code:'',
            'state_name' => (isset($request->state_name))?$request->state_name:'',
            'password' => Hash::make($request->password),
            'role_id' => null,
            'company_id' => $newCompanyID,
            'company_parent' => $ownedcompanyid,
            'company_root_id' => $idsys,
            'user_type' => $userType,
            'isAdmin' => $isAdmin,
            'defaultadmin' => $defaultadmin,
            'customercare' => $customercare,
            'sort' => $sortorder,
            'lp_limit_freq' => 'day',
            'last_time_login' => date('Y-m-d H:i:s'),
            'customer_payment_id' => '',
            'customer_card_id' => '',
            'ip_login' => $ipAddress,
            'ip_register' => $ipAddress,
            'tfa_active' => $tfa_active,
            'tfa_type' => $tfa_type,
        ]);

        if ($usr->user_type == 'client') {
           //SAVE CLIENT SIDE BAR
           $userclient = User::find($usr->id);
           if ($userclient->user_type == 'client') {
               $clientsidebar = [];
               $root_module = CompanySetting::where('company_id',trim($usr->company_root_id))->whereEncrypted('setting_name','rootcustomsidebarleadmenu')->get();
               if (count($root_module) > 0) {
                   $rootsidebarleadmenu = json_decode($root_module[0]['setting_value']);
                   $root_module = $rootsidebarleadmenu;
               }
               $agency_default_setting = $this->getcompanysetting($usr->company_parent, 'agencydefaultmodules');

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
               $clientsidebar_setting = CompanySetting::where('company_id', $usr->company_id)
               ->whereEncrypted('setting_name', 'clientsidebar')
               ->first();

               if ($clientsidebar_setting) {
                   $clientsidebar_setting->setting_value = json_encode($clientsidebar);
                   $clientsidebar_setting->save();
               } else {
                   if ($clientsidebar != []) {
                       $createsetting = CompanySetting::create([
                       'company_id' => $usr->company_id,
                       'setting_name' => 'clientsidebar',
                       'setting_value' => json_encode($clientsidebar),
                       ]);
                   }
               }
           }
           //SAVE CLIENT SIDE BAR
        }

        //$usrID = $usr->id;

        /** SEND WELCOME EMAIL AND NOTIFICATION */
        $details = [
            'username' => $request->email,
            'name'  => $request->name,
            'newpass' => $request->password,
            'domain' => $DownlineSubDomain,
            'accounttype' => $AccountType,
        ];

        $from = [
            'address' => 'noreply@sitesettingsapi.com',
            'name' => 'Welcome',
            'replyto' => 'noreply@sitesettingsapi.com',
        ];
        
        $smtpusername = $this->set_smtp_email($ownedcompanyid);

        $emailtype = 'em_clientwelcomeemail';
        if ($AccountType == 'Agency account') {
            $emailtype = 'em_agencywelcomeemail';
        }
        $customsetting = $this->getcompanysetting($ownedcompanyid,$emailtype);
        $chkcustomsetting = $customsetting;

        if ($customsetting == '') {
            $customsetting =  json_decode(json_encode($this->check_email_template($emailtype,$ownedcompanyid)));
        }

            $finalcontent = nl2br($this->filterCustomEmail($usr,$ownedcompanyid,$customsetting->content,$request->password,$DownlineSubDomain));
            $finalsubject = $this->filterCustomEmail($usr,$ownedcompanyid,$customsetting->subject,$request->password,$DownlineSubDomain);
            $finalfrom = $this->filterCustomEmail($usr,$ownedcompanyid,$customsetting->fromName,$request->password,$DownlineSubDomain);

            $details = [
                'title' => ucwords($finalsubject),
                'content' => $finalcontent,
            ];
            
            $from = [
                'address' => (isset($customsetting->fromAddress) && $customsetting->fromAddress != '')?$customsetting->fromAddress:'noreply@sitesettingsapi.com',
                'name' => (isset($finalfrom) && $finalfrom != '')?$finalfrom:'Welcome',
                'replyto' => (isset($customsetting->fromReplyto) && $customsetting->fromReplyto != '')?$customsetting->fromReplyto:'support@sitesettingsapi.com',
            ];

            if ($smtpusername != "" && $chkcustomsetting == "") {
                $from = [
                    'address' => $smtpusername,
                    'name' => (isset($finalfrom) && $finalfrom != '')?$finalfrom:'Welcome',
                    'replyto' => $smtpusername,
                ];
            }
            
            $this->send_email(array($request->email),$from,ucwords($customsetting->subject),$details,array(),'emails.customemail',$ownedcompanyid);
          
        /** SEND WELCOME EMAIL AND NOTIFICATION */

        /** SEND EMAIL NOTIFICATION NEW REGISTER */
        $tmp = User::select('email')->where('company_id','=',$ownedcompanyid)->where('active','T')
                                    ->where(function($query) {
                                        $query->where('user_type','=','user')
                                                ->orWhere('user_type','=','userdownline');
                                    })
                                    ->where('isAdmin','=','T')
                                    ->where('active','=','T')
                                    ->orderByEncrypted('name')->get();
        $adminEmail = array();
        foreach($tmp as $ad) {
            array_push($adminEmail,$ad['email']);
        }

        $AdminDefault = $this->get_default_admin($ownedcompanyid);
        $AdminDefaultEmail = (isset($AdminDefault[0]['email']))?$AdminDefault[0]['email']:'';
        $defaultdomain = $this->getDefaultDomainEmail($ownedcompanyid);

        $fullNameParts = explode(' ', trim($request->name));
        $sales_agent = '';
        $referral_agent = '';
        $account_executive = '';
                    
        $details = [
            'email' => $request->email,
            'name'  => $request->name,
            'first_name' => $fullNameParts[0], 
            'last_name' => end($fullNameParts),
            'phone' => $phonenum,
            'business_name' => $companyName,
            'sales_agent' => $sales_agent,
            'referral_agent' => $referral_agent,
            'account_executive' => $account_executive,
            'domain' => $DownlineSubDomain,
            'accounttype' => $AccountType,
            'defaultadmin' => $AdminDefaultEmail,
        ];

        $from = [
            'address' => 'noreply@' . $defaultdomain,
            'name' => 'New Account Registered',
            'replyto' => 'support@' . $defaultdomain,
        ];
       
        $this->send_email($adminEmail,$from,'New ' . $AccountType . ' Registered',$details,array(),'emails.adminnewaccountregister',$ownedcompanyid);
         /** SEND EMAIL NOTIFICATION NEW REGISTER */

        return response()->json(array('result'=>'success','message'=>'redirect','url'=>'https://' . $DownlineSubDomain));
        
    }

    public function cancel_campaign($companyParentID,$userID,$transactionID,$paymentgateway) {        
        $leadsuser = LeadspeekUser::select('leadspeek_users.id','leadspeek_users.leadspeek_api_id','leadspeek_users.leadspeek_type','leadspeek_users.active','leadspeek_users.disabled','leadspeek_users.active_user','users.company_id','leadspeek_users.leadspeek_organizationid','leadspeek_users.leadspeek_campaignsid','users.customer_payment_id','leadspeek_users.user_id',
        'leadspeek_users.paymentterm','leadspeek_users.lp_min_cost_month','leadspeek_users.report_sent_to','leadspeek_users.admin_notify_to','users.email','leadspeek_users.lp_limit_startdate','leadspeek_users.lp_enddate','leadspeek_users.lp_max_lead_month','leadspeek_users.cost_perlead','users.customer_card_id','leadspeek_users.start_billing_date',
        'companies.company_name','leadspeek_users.campaign_name','leadspeek_users.company_id as company_parent','users.active','users.company_root_id')
                                ->join('users','leadspeek_users.user_id','=','users.id')
                                ->join('companies','users.company_id','=','companies.id')
                                ->where('leadspeek_users.user_id','=',$userID)
                                ->where('leadspeek_users.company_id','=',$companyParentID)
                                ->where('leadspeek_users.archived','=','F')
                                ->where(function($query){
                                    $query->where(function($query){
                                        $query->where('leadspeek_users.active','=','T')
                                            ->where('leadspeek_users.disabled','=','F')
                                            ->where('leadspeek_users.active_user','=','T');
                                    })
                                    ->orWhere(function($query){
                                        $query->where('leadspeek_users.active','=','F')
                                            ->where('leadspeek_users.disabled','=','F')
                                            ->where('leadspeek_users.active_user','=','T');
                                    })
                                    ->orWhere(function($query){
                                        $query->where('leadspeek_users.active','=','F')
                                            ->where('leadspeek_users.disabled','=','T')
                                            ->where('leadspeek_users.active_user','=','T');
                                    });
                                })->distinct()->get();
                                
        $companyID = "";

        if (count($leadsuser) > 0) {

            foreach($leadsuser as $lds) {
                $companyID = (isset($lds['company_id']))?$lds['company_id']:'';
                $leadspeekID = (isset($lds['id']))?$lds['id']:'';
                $status = 'F';
                $userID = (isset($lds['user_id']))?$lds['user_id']:'';

                /** DIRECTLY DISABLED LEADSPEEK USER */
                $query = LeadspeekUser::find($leadspeekID);
                $query->archived = 'T';
                $query->save();
                /** DIRECTLY DISABLED LEADSPEEK USER */

                //if ($lds['active'] == "T" || ($lds['active'] == "F" && $lds['disabled'] == "F")) {
                if (!($lds['active'] == "F" && $lds['disabled'] == "T" && $lds['disabled'] == "F")) {
                        $http = new \GuzzleHttp\Client;
                        $organizationid = ($lds['leadspeek_organizationid'] != "")?$lds['leadspeek_organizationid']:"";
                        $campaignsid = ($lds['leadspeek_campaignsid'] != "")?$lds['leadspeek_campaignsid']:"";    
                        $start_billing_date = ($lds['start_billing_date'] != "")?$lds['start_billing_date']:"";    
                        
                         /** DISABLED / STOP CAMPAIGN */
                            $updateLeadspeekUser = LeadspeekUser::find($leadspeekID);
                            $updateLeadspeekUser->active = 'F';
                            $updateLeadspeekUser->disabled = 'T';
                            $updateLeadspeekUser->active_user = 'F';
                            $updateLeadspeekUser->save();
                        /** DISABLED / STOP CAMPAIGN */

                        /** ACTIVATE CAMPAIGN SIMPLIFI */
                         if ($organizationid != '' && $campaignsid != '' && $lds['leadspeek_type'] == 'locator') {
                            $camp = $this->startPause_campaign($organizationid,$campaignsid,'stop');
                            if (!$camp) {
                                $details = [
                                    'errormsg'  => 'Error when trying to Stop Campaign Organization ID : ' . $organizationid . ' Campaign ID :' . $campaignsid,
                                ];
                                $from = [
                                    'address' => 'noreply@exactmatchmarketing.com',
                                    'name' => 'Support',
                                    'replyto' => 'support@exactmatchmarketing.com',
                                ];
                                $this->send_email(array('serverlogs@sitesettingsapi.com'),$from,'Error Log for Stop Campaign (Apps DATA - Kartra-cancel_campaign - L2156) ',$details,array(),'emails.tryseramatcherrorlog','');
                
                            }
                        }
                        /** ACTIVATE CAMPAIGN SIMPLIFI */
                    
                        /** CHECK IF THERE END DATE ON WEEKLY OR MONTHLY PAYMENT TERM */
                        $clientPaymentTerm = $lds['paymentterm'];
                        $_company_id = $lds['company_id'];
                        $_user_id = $lds['user_id'];
                        $_leadspeek_api_id = $lds['leadspeek_api_id'];
                        $clientPaymentTerm = $lds['paymentterm'];
                        $minCostLeads = $lds['lp_min_cost_month'];
                        $_lp_user_id = $lds['id'];
                        $company_name = $lds['company_name'];
                        $clientEmail = explode(PHP_EOL, $lds['report_sent_to']);
                        $clientAdminNotify = explode(',',$lds['admin_notify_to']);
                        $custEmail = $lds['email'];    
                    
                    $clientLimitStartDate = ($lds['lp_limit_startdate'] == null || $lds['lp_limit_startdate'] == '0000-00-00 00:00:00')?'':$lds['lp_limit_startdate'];
                    $clientLimitEndDate = ($lds['lp_enddate'] == null || $lds['lp_enddate'] == '0000-00-00 00:00:00')?'':$lds['lp_enddate'];

                    $clientMaxperTerm = $lds['lp_max_lead_month'];
                    $clientCostPerLead = $lds['cost_perlead'];
                    
                    $custStripeID = $lds['customer_payment_id'];
                    $custStripeCardID = $lds['customer_card_id'];

                    if ($clientPaymentTerm != 'One Time' && $start_billing_date != '') {
                        $EndDate = date('YmdHis',strtotime($start_billing_date));
                        $platformFee = 0;
                        
                        if (date('YmdHis') >= $EndDate) {
                            /** CHECK IF NEED TO BILLED PLATFORM FEE OR NOT */
                            if ($clientPaymentTerm == 'Weekly') {
                                $date1=date_create(date('Ymd'));
                                $date2=date_create($EndDate);
                                $diff=date_diff($date1,$date2);
                                if ($diff->format("%a") >= 6) {
                                    $platformFee = $minCostLeads;
                                }
                            }else if ($clientPaymentTerm == 'Monthly') {
                                if(date('m') > date('m',strtotime($start_billing_date))) {
                                    $platformFee = $minCostLeads;
                                }
                            }
                            /** CHECK IF NEED TO BILLED PLATFORM FEE OR NOT */

                            /** CHECK IF PLATFORM FEE NOT ZERO AND THEN PUT THE FORMULA */
                            if ($platformFee != 0 && $platformFee != '' && $clientPaymentTerm == 'Weekly') {
                                $clientWeeksContract = 52; //assume will be one year if end date is null or empty
                                $clientMonthRange = 12;

                                /** PUT FORMULA TO DEVIDED HOW MANY TUESDAY FROM PLATFORM FEE COST */
                                if ($platformFee != '' && $platformFee > 0) {
                                    if ($clientLimitEndDate != '') {
                                        $d1 = new DateTime($clientLimitStartDate);
                                        $d2 = new DateTime($clientLimitEndDate);
                                        $interval = $d1->diff($d2);
                                        $clientMonthRange = $interval->m;

                                        $d1 = strtotime($clientLimitStartDate);
                                        $d2 = strtotime($clientLimitEndDate);
                                        $clientWeeksContract = $this->countDays(2, $d1, $d2);

                                        $platformFee = ($minCostLeads * $clientMonthRange) / $clientWeeksContract;

                                    }else{
                                        $platformFee = ($minCostLeads * $clientMonthRange) / $clientWeeksContract;
                                    }
                                }
                                /** PUT FORMULA TO DEVIDED HOW MANY TUESDAY FROM PLATFORM FEE COST */
                            }
                            /** CHECK IF PLATFORM FEE NOT ZERO AND THEN PUT THE FORMULA*/

                            /** UPDATE USER END DATE */
                            $updateUser = User::find($userID);
                            $updateUser->lp_enddate = null;
                            $updateUser->lp_limit_startdate = null;
                            $updateUser->save();
                            /** UPDATE USER END DATE */

                            $clientStartBilling = date('YmdHis',strtotime($start_billing_date));
                            $nextBillingDate = date('YmdHis');

                            /** HACKED ENDED CLIENT NO PLATFORM FEE */
                            $platformFee = 0;
                            /** HACKED ENDED CLIENT NO PLATFORM FEE */

                            /** CREATE INVOICE AND SENT IT */
                            $invoiceCreated = $this->createInvoice($_lp_user_id,$_company_id,$_user_id,$_leadspeek_api_id,$clientMaxperTerm,$clientCostPerLead,$platformFee,$clientPaymentTerm,$company_name,$clientEmail,$clientAdminNotify,$clientStartBilling,$nextBillingDate,$custStripeID,$custStripeCardID,$custEmail,$lds);
                            /** CREATE INVOICE AND SENT IT */

                        }
                    }
                    /** CHECK IF THERE END DATE ON WEEKLY OR MONTHLY PAYMENT TERM */

                        /** ACTIVATE CAMPAIGN SIMPLIFI */
                        // if ($organizationid != '' && $campaignsid != '' && $lds['leadspeek_type'] == 'locator') {
                        //     $camp = $this->startPause_campaign($organizationid,$campaignsid,'stop');
                        // }
                        /** ACTIVATE CAMPAIGN SIMPLIFI */

                       

                }

            }

        }

    }

    public function createInvoice($_lp_user_id,$_company_id,$_user_id,$_leadspeek_api_id,$minLeads,$costLeads,$minCostLeads,$clientPaymentTerm,$companyName,$reportSentTo,$adminnotify,$startBillingDate,$endBillingDate,$custStripeID,$custStripeCardID,$custEmail,$usrInfo) {
        date_default_timezone_set('America/Chicago');
        $todayDate = date('Y-m-d H:i:s');
        $invoiceNum = date('Ymd') . '-' . $_lp_user_id;
        $exceedLeads = 0;
        $totalAmount = 0;
        $costPriceLeads = 0;
        $platform_costPriceLeads = 0;
        $root_costPriceLeads = 0;
        $rootFee = 0;
        $cleanProfit = 0;
        $ongoingLeads = 0;
        $rootAccCon = "";

        /** FIND IF THERE IS ANY EXCEED LEADS */
        $reportCat = LeadspeekReport::select(DB::raw("COUNT(*) as total"),DB::raw("SUM(price_lead) as costleadprice"),DB::raw("SUM(platform_price_lead) as platform_costleadprice"),DB::raw("SUM(root_price_lead) as root_costleadprice"))
                        ->where('lp_user_id','=',$_lp_user_id)
                        ->where('company_id','=',$_company_id)
                        ->where('user_id','=',$_user_id)
                        ->where('leadspeek_api_id','=',$_leadspeek_api_id)
                        ->where('active','=','T')
                        //->whereBetween(DB::raw('DATE_FORMAT(clickdate,"%Y-%m-%d")'),[$startBillingDate,$endBillingDate])
                        ->where(DB::raw('DATE_FORMAT(clickdate,"%Y%m%d%H%i%s")'),'>=',$startBillingDate)
                        ->where(DB::raw('DATE_FORMAT(clickdate,"%Y%m%d%H%i%s")'),'<=',$endBillingDate)
                        ->get();
        if(count($reportCat) > 0) {
            $ongoingLeads = $reportCat[0]['total'];
            $costPriceLeads = $reportCat[0]['costleadprice'];
            $platform_costPriceLeads = $reportCat[0]['platform_costleadprice'];
            $root_costPriceLeads = $reportCat[0]['root_costleadprice'];

            // Log::info([
            //     'ongoingLeads' => $ongoingLeads,
            //     'costPriceLeads' => $costPriceLeads,
            //     'platform_costPriceLeads' => $platform_costPriceLeads,
            //     'root_costPriceLeads' => $root_costPriceLeads,
            // ]);
        }

        /** IF JUST WILL CHARGE PER LEAD */
        if ($clientPaymentTerm != 'One Time' && ($costLeads != '0' || trim($costLeads) != '')) {
            //$totalAmount =  ($costLeads * $ongoingLeads) + $minCostLeads;
            $totalAmount = $costPriceLeads + $minCostLeads;
        }else if($clientPaymentTerm == 'One Time') {
            $totalAmount = $minCostLeads;
        }
        /** IF JUST WILL CHARGE PER LEAD */

        $_company_parent = (isset($usrInfo[0]->company_parent))?$usrInfo[0]->company_parent:$usrInfo['company_parent'];
        $_company_root_id = (isset($usrInfo[0]->company_root_id))?$usrInfo[0]->company_root_id:$usrInfo['company_root_id'];
        $_customer_payment_id = (isset($usrInfo[0]->customer_payment_id))?$usrInfo[0]->customer_payment_id:$usrInfo['customer_payment_id'];
        $_company_id = (isset($usrInfo[0]->company_id))?$usrInfo[0]->company_id:$usrInfo['company_id'];
        $_id = (isset($usrInfo[0]->user_id))?$usrInfo[0]->user_id:$usrInfo['user_id'];
        $_company_root_id = (isset($usrInfo[0]->company_root_id))?$usrInfo[0]->company_root_id:$usrInfo['company_root_id'];
        $_leadspeek_type = (isset($usrInfo[0]->leadspeek_type))?$usrInfo[0]->leadspeek_type:$usrInfo['leadspeek_type'];

        $companyParentName = "";
        $AgencyManualBill = "F";

        /** GET COMPANY PARENT NAME / AGENCY */
        $getParentInfo = Company::select('company_name','manual_bill')->where('id','=',$_company_parent)->get();
        if(count($getParentInfo) > 0) {
            $companyParentName = $getParentInfo[0]['company_name'];
            $AgencyManualBill = $getParentInfo[0]['manual_bill'];
        }
        /** GET COMPANY PARENT NAME / AGENCY */

        /** CHECK IF THIS COMPANY USERDOWNLINE OR CLIENT */
        $accConID = '';
        if ($_company_parent != '') {
            $accConID = $this->check_connected_account($_company_parent,$_company_root_id);
        }
        /** CHECK IF THIS COMPANY USERDOWNLINE OR CLIENT */

        /** CHECK IF USER DATA STILL ON PLATFORM */
        $validuser = true;
        $user[0]['customer_payment_id'] = $_customer_payment_id;
        $user[0]['company_id'] = $_company_id;
        $user[0]['id'] = $_id;
        $user[0]['company_root_id'] = $_company_root_id;

        $chkStripeUser = $this->check_stripe_customer_platform_exist($user,$accConID);
        $chkResultUser = json_decode($chkStripeUser);
        if ($chkResultUser->result == 'success') {
            $validuser = true;
            $custStripeID = $chkResultUser->custStripeID;
            $custStripeCardID = $chkResultUser->CardID;
        }else{
            $validuser = false;
        }
        /** CHECK IF USER DATA STILL ON PLATFORM */        

        /** CHECK IF AGENCY MANUAL BILL */
        if ($AgencyManualBill == "T") {
            $validuser = true;
            $custStripeID = "agencyDirectPayment";
            $custStripeCardID = "agencyDirectPayment";
            /** GET STRIPE ACC AND CARD AGENCY */
            $custAgencyStripeID = "";
            $custAgencyStripeCardID = "";

            $chkAgency = User::select('id','customer_payment_id','customer_card_id','email')
                    ->where('company_id','=',$_company_parent)
                    ->where('company_parent','<>',$_company_parent)
                    ->where('user_type','=','userdownline')
                    ->where('isAdmin','=','T')
                    ->where('active','=','T')
                    ->get();
            if(count($chkAgency) > 0) {
                $custAgencyStripeID = $chkAgency[0]['customer_payment_id'];
                $custAgencyStripeCardID = $chkAgency[0]['customer_card_id'];
            }
            /** GET STRIPE ACC AND CARD AGENCY */
        }
        /** CHECK IF AGENCY MANUAL BILL */

        /** CHARGE WITH STRIPE */
        $topup_agencies_id = null;
        $leadspeek_invoices_id = null;
        $paymentintentID = '';
        $errorstripe = '';
        $platform_errorstripe = '';
        $statusPayment = 'pending';
        $platformStatusPayment = "";
        $cardlast = '';
        $platform_paymentintentID = '';
        $sr_id = 0;
        $ae_id = 0;
        $ar_id = 0;
        $sales_fee = 0;
        $platformfee_charge = false;

        $totalAmount = number_format($totalAmount,2,'.','');
        $minCostLeads = number_format($minCostLeads,2,'.','');

        if ($root_costPriceLeads != "0") {
            $rootFee = number_format($root_costPriceLeads,2,'.','');
        }

        $platform_LeadspeekCostperlead = 0;
        $platform_LeadspeekMinCostMonth = 0;
        $platform_LeadspeekPlatformFee = 0;
        $platformfee_ori = 0;
        $platformfee = 0;

        if(trim($custStripeID) != '' && trim($custStripeCardID) != '' && $validuser) { 
            /** GET STRIPE KEY */
            $stripeseckey = config('services.stripe.secret');
            $stripepublish = $this->getcompanysetting($_company_root_id,'rootstripe');
            if ($stripepublish != '') {
                $stripeseckey = (isset($stripepublish->secretkey))?$stripepublish->secretkey:"";
            }
            /** GET STRIPE KEY */

            $stripe = new StripeClient([
                'api_key' => $stripeseckey,
                'stripe_version' => '2020-08-27'
            ]);

            /** GET PLATFORM MARGIN */
            $platformMargin = $this->getcompanysetting($_company_parent,'costagency');
            

            $paymentterm = (isset($usrInfo[0]->paymentterm))?trim($usrInfo[0]->paymentterm):trim($usrInfo['paymentterm']);
            $paymentterm = str_replace(' ','',$paymentterm);
            if ($platformMargin != '') {
                if ($_leadspeek_type == "local") {
                    $platform_LeadspeekCostperlead = (isset($platformMargin->local->$paymentterm->LeadspeekCostperlead))?$platformMargin->local->$paymentterm->LeadspeekCostperlead:0;
                    $platform_LeadspeekMinCostMonth = (isset($platformMargin->local->$paymentterm->LeadspeekMinCostMonth))?$platformMargin->local->$paymentterm->LeadspeekMinCostMonth:0;
                    $platform_LeadspeekPlatformFee = (isset($platformMargin->local->$paymentterm->LeadspeekPlatformFee))?$platformMargin->local->$paymentterm->LeadspeekPlatformFee:0;
                }else if ($_leadspeek_type == "locator") {
                    $platform_LeadspeekCostperlead = (isset($platformMargin->locator->$paymentterm->LocatorCostperlead))?$platformMargin->locator->$paymentterm->LocatorCostperlead:0;
                    $platform_LeadspeekMinCostMonth = (isset($platformMargin->locator->$paymentterm->LocatorMinCostMonth))?$platformMargin->locator->$paymentterm->LocatorMinCostMonth:0;
                    $platform_LeadspeekPlatformFee = (isset($platformMargin->locator->$paymentterm->LocatorPlatformFee))?$platformMargin->locator->$paymentterm->LocatorPlatformFee:0;
                }
                /** HACKED BECAUSE CAMPAIGN ENDED NO NEED TO CHARGE PLATFORM FEE */
                $platform_LeadspeekMinCostMonth = 0;
            }
            /** GET PLATFORM MARGIN */

            if ($clientPaymentTerm != 'One Time' && ($costLeads != '0' || trim($costLeads) != '')) {
                //$platformfee =  ($platform_LeadspeekCostperlead * $ongoingLeads) + $platform_LeadspeekMinCostMonth;
                $platformfee =  $platform_costPriceLeads + $platform_LeadspeekMinCostMonth;
                /** HACKED PLATFORMFEE FOR ONLY CAMPAIGN ID #642466 */
                // if ($_leadspeek_api_id == '642466') {
                //     $platform_LeadspeekCostperlead = 0.15;
                //     $platformfee = (0.15 * $ongoingLeads) + $platform_LeadspeekMinCostMonth;
                // }
                /** HACKED PLATFORMFEE FOR ONLY CAMPAIGN ID #642466 */
            }else if($clientPaymentTerm == 'One Time') {
                $platformfee = $platform_LeadspeekMinCostMonth;
            }
            
            $platformfee = number_format($platformfee,2,'.','');
            $platformfee_ori = $platformfee;
            
            $defaultInvoice = '#' . $invoiceNum . '-' . $companyName . ' #' . $_leadspeek_api_id . '(ended)';

            /** CHECK IF TOTAL AMOUNT IS SMALLER THAN PLATFORM FEE */
            //if (($totalAmount < $platformfee) && $platformfee > 0) {
            // if ($platformfee >= 0.5) {
            //     $agencystripe = $this->check_agency_stripeinfo($usrInfo[0]->company_parent,$platformfee,$_leadspeek_api_id,'Agency ' . $defaultInvoice);
            //     $agencystriperesult = json_decode($agencystripe);
            //     $platformfee_charge = true;

            //     if ($agencystriperesult->result == 'success') {
            //         $platform_paymentintentID = $agencystriperesult->payment_intentID;
            //         $sr_id = $agencystriperesult->srID;
            //         $ae_id = $agencystriperesult->aeID;
            //         $sales_fee = $agencystriperesult->salesfee;
            //         $platformfee = 0;
            //         $platform_errorstripe = '';
            //     }else{
            //         $platform_paymentintentID = $agencystriperesult->payment_intentID;
            //         $platform_errorstripe .= $agencystriperesult->error;
            //     }
            // }
            /** CHECK IF TOTAL AMOUNT IS SMALLER THAN PLATFORM FEE */

            /** CREATE ONE TIME PAYMENT USING PAYMENT INTENT */
            if ($totalAmount < 0.5 || $totalAmount <= 0) 
            {
                $paymentintentID = '';
                $statusPayment = 'paid';
                $platformfee_charge = false;
            }
            else
            {
                // info(['AgencyManualBill' => $AgencyManualBill]);
                if ($AgencyManualBill == 'F') 
                {
                    try 
                    {
                        if($usrInfo[0]->leadspeek_type == "enhance") 
                        {
                            $masterRootFee = $this->getcompanysetting($usrInfo[0]->company_root_id,'rootfee');
                            
                            if((isset($masterRootFee->feepercentagemob) && $masterRootFee->feepercentagemob != "") || (isset($masterRootFee->feepercentagedom) && $masterRootFee->feepercentagedom != "")) 
                            {
                                // if root mobile or dominator
                                // Log::info("platformfee = $platformfee ke-1");
                                $feePercentageEmm = (isset($masterRootFee->feepercentageemm))?$masterRootFee->feepercentageemm:0;
                                $platformfee = ($platformfee * $feePercentageEmm) / 100;
                                $platformfee = number_format($platformfee,2,'.','');
                                // Log::info("platformfee = $platformfee ke-2");
                                
                                // Log::info([
                                //     'msg' => 'pembagian ke emm jika enhance 1',
                                //     'platformfee' => $platformfee,
                                //     'feePercentageEmm' => $feePercentageEmm,
                                //     'customer' => trim($custStripeID),
                                //     'stripe_account' => $accConID,
                                //     'masterRootFee' => $masterRootFee
                                // ]);
                            }
                        }

                        
                        $chargeAmount = $totalAmount * 100;
                        // Log::info([
                        //     'payment_method_types' => ['card'],
                        //     'customer' => trim($custStripeID),
                        //     'amount' => $chargeAmount,
                        //     'currency' => 'usd',
                        //     'receipt_email' => $custEmail,
                        //     'payment_method' => $custStripeCardID,
                        //     'confirm' => true,
                        //     'application_fee_amount' => ($platformfee * 100),
                        //     'description' => $defaultInvoice,
                        //     'stripe_account' => $accConID
                        // ]);
                        $payment_intent =  $stripe->paymentIntents->create([
                            'payment_method_types' => ['card'],
                            'customer' => trim($custStripeID),
                            'amount' => $chargeAmount,
                            'currency' => 'usd',
                            'receipt_email' => $custEmail,
                            'payment_method' => $custStripeCardID,
                            'confirm' => true,
                            'application_fee_amount' => ($platformfee * 100),
                            'description' => $defaultInvoice,
                        ],['stripe_account' => $accConID]);

                        $statusPayment = 'paid';
                        $platformfee_charge = true;
                        $errorstripe = '';

                        /* CHECK STATUS PAYMENT INTENTS */
                        $payment_intent_status = (isset($payment_intent->status))?$payment_intent->status:"";
                        if($payment_intent_status == 'requires_action') 
                        {
                            $statusPayment = 'failed';
                            $platformfee_charge = false;
                            $errorstripe = "Payment for campaign $_leadspeek_api_id was unsuccessful: Stripe status '$payment_intent_status' indicates further user action is needed.";
                        }
                        /* CHECK STATUS PAYMENT INTENTS */

                        $paymentintentID = $payment_intent->id;

                        if($statusPayment == 'paid' && $platformfee_charge) 
                        {
                            /** TRANSFER SALES COMMISSION IF ANY */
                            $dataCustomCommissionSales = [
                                'type' => 'invoice',
                                '_lp_user_id' => $_lp_user_id,
                                '_company_id' => $_company_id,
                                '_user_id' => $_user_id,
                                '_leadspeek_api_id' => $_leadspeek_api_id,
                                'startBillingDate' => $startBillingDate,
                                'endBillingDate' => $endBillingDate,
                            ];
                            $_cleanProfit = "";
                            if($rootFee != "0" && $rootFee != "") 
                            {
                                $_cleanProfit = $platformfee_ori - $rootFee;
                            }
                            $salesfee = $this->transfer_commission_sales($_company_parent,$platformfee,$_leadspeek_api_id,$startBillingDate,$endBillingDate,$stripeseckey,$ongoingLeads,$_cleanProfit,$dataCustomCommissionSales);
                            $salesfeeresult = json_decode($salesfee);
                            $platform_paymentintentID = $salesfeeresult->payment_intentID;
                            $sr_id = $salesfeeresult->srID;
                            $ae_id = $salesfeeresult->aeID;
                            $ar_id = $salesfeeresult->arID;
                            $sales_fee = $salesfeeresult->salesfee;
                            /** TRANSFER SALES COMMISSION IF ANY */
                        }
                    }
                    catch (RateLimitException $e) 
                    {
                        $statusPayment = 'failed';
                        $platformfee_charge = false;
                        // Too many requests made to the API too quickly
                        $errorstripe = $e->getHttpStatus() . '|' . $e->getError()->type . '|' . $e->getError()->code . '|' . $e->getError()->param . '|' . $e->getError()->message;
                    } 
                    catch (InvalidRequestException $e) 
                    {
                        $statusPayment = 'failed';
                        $platformfee_charge = false;
                        // Invalid parameters were supplied to Stripe's API
                        $errorstripe = $e->getHttpStatus() . '|' . $e->getError()->type . '|' . $e->getError()->code . '|' . $e->getError()->param . '|' . $e->getError()->message;
                    } 
                    catch (ExceptionAuthenticationException $e) 
                    {
                        $statusPayment = 'failed';
                        $platformfee_charge = false;
                        // Authentication with Stripe's API failed
                        // (maybe you changed API keys recently)
                        $errorstripe = $e->getHttpStatus() . '|' . $e->getError()->type . '|' . $e->getError()->code . '|' . $e->getError()->param . '|' . $e->getError()->message;
                    } 
                    catch (ApiConnectionException $e) 
                    {
                        $statusPayment = 'failed';
                        $platformfee_charge = false;
                        // Network communication with Stripe failed
                        $errorstripe = $e->getHttpStatus() . '|' . $e->getError()->type . '|' . $e->getError()->code . '|' . $e->getError()->param . '|' . $e->getError()->message;
                    } 
                    catch (ApiErrorException $e) 
                    {
                        $statusPayment = 'failed';
                        $platformfee_charge = false;
                        // Display a very generic error to the user, and maybe send
                        // yourself an email
                        $errorstripe = $e->getHttpStatus() . '|' . $e->getError()->type . '|' . $e->getError()->code . '|' . $e->getError()->param . '|' . $e->getError()->message;
                    } 
                    catch (Exception $e) 
                    {
                        $statusPayment = 'failed';
                        $platformfee_charge = false;
                        // Something else happened, completely unrelated to Stripe
                        $errorstripe = 'error not stripe things :' . $e->getMessage();
                    }
                }
                else
                {
                    $statusPayment = 'paid';
                    $platformfee_charge = false;
                    $errorstripe = "This direct Agency Bill Method";
                }
            }

            $cardinfo = "";
            $cardlast = "";

            if ($AgencyManualBill == "T") {
                $custStripeID = $custAgencyStripeID;
                $custStripeCardID = $custAgencyStripeCardID;

                $cardinfo = $stripe->customers->retrieveSource(trim($custStripeID),trim($custStripeCardID),[]);
                $cardlast = $cardinfo->last4;
            }else {
                $cardinfo = $stripe->customers->retrieveSource(trim($custStripeID),trim($custStripeCardID),[],['stripe_account' => $accConID]);
                $cardlast = $cardinfo->last4;
            }
            
            /** CHECK IF FAILED CHARGE CLIENT WE STILL CHARGE THE AGENCY */
            //if ($statusPayment == 'failed' && $platformfee_charge == false && $platformfee >= 0.5) {
            if ($platformfee_charge == false && $platformfee >= 0.5) {
                $dataCustomCommissionSales = [
                    'type' => 'invoice',
                    '_lp_user_id' => $_lp_user_id,
                    '_company_id' => $_company_id,
                    '_user_id' => $_user_id,
                    '_leadspeek_api_id' => $_leadspeek_api_id,
                    'startBillingDate' => $startBillingDate,
                    'endBillingDate' => $endBillingDate,
                ];
                $_cleanProfit = "";
                if($rootFee != "0" && $rootFee != "") {
                    $_cleanProfit = $platformfee_ori - $rootFee;
                }
                $agencystripe = $this->check_agency_stripeinfo($_company_parent,$platformfee,$_leadspeek_api_id,'Agency ' . $defaultInvoice,$startBillingDate,$endBillingDate,$ongoingLeads,$_cleanProfit,$dataCustomCommissionSales,$AgencyManualBill);
                $agencystriperesult = json_decode($agencystripe);

                if (isset($agencystriperesult->result) && $agencystriperesult->result == 'success') {
                    $platform_paymentintentID = $agencystriperesult->payment_intentID;
                    $sr_id = $agencystriperesult->srID;
                    $ae_id = $agencystriperesult->aeID;
                    $ar_id = $agencystriperesult->arID;
                    $sales_fee = $agencystriperesult->salesfee;
                    $topup_agencies_id = $agencystriperesult->topup_agencies_id;
                    $leadspeek_invoices_id = $agencystriperesult->leadspeek_invoices_id;
                    $platformStatusPayment = $agencystriperesult->statusPayment;
                    $platformfee = 0;
                    $platform_errorstripe = '';
                }else{
                    $platform_paymentintentID = $agencystriperesult->payment_intentID;
                    $platform_errorstripe .= $agencystriperesult->error;
                }
            }
            /** CHECK IF FAILED CHARGE CLIENT WE STILL CHARGE THE AGENCY */
            
            /** CHARGE ROOT FEE AGENCY */
            if($rootFee != "0" && $rootFee != "") {
                // $rootCommissionFee = 0;
                // $rootCommissionFee = ($rootFee * 0.05);
                // $rootCommissionFee = number_format($rootCommissionFee,2,'.','');
                $rootCommissionFee = 0;
                $rootCommissionFee = ($usrInfo[0]->leadspeek_type == 'enhance')?($platformfee * 0.05):($rootFee * 0.05);
                $rootCommissionFee = number_format($rootCommissionFee,2,'.','');
                $rootCommissionSRAccVal = $rootCommissionFee;
                $rootCommissionAEAccVal = $rootCommissionFee;

                $cleanProfit = $platformfee_ori - $rootFee;
                //if ($cleanProfit > 0.5) {
                    /** GET ROOT CONNECTED ACCOUNT TO BE TRANSFER FOR CLEAN PROFIT AFTER CUT BY ROOT FEE COST */
                    $rootAccCon = "";
                    $rootAccConMob = "";
                    $rootCommissionSRAcc = "";
                    $rootCommissionAEAcc = "";

                    $rootAccConResult = $this->getcompanysetting($_company_root_id,'rootfee');
                    if ($rootAccConResult != '') {
                        $rootAccCon = (isset($rootAccConResult->rootfeeaccid))?$rootAccConResult->rootfeeaccid:"";
                        $rootAccConMob = (isset($rootAccConResult->rootfeeaccidmob))?$rootAccConResult->rootfeeaccidmob:"";
                        $rootCommissionSRAcc = (isset($rootAccConResult->rootcomsr))?$rootAccConResult->rootcomsr:"";
                        $rootCommissionAEAcc = (isset($rootAccConResult->rootcomae))?$rootAccConResult->rootcomae:"";
                        /** OVERRIDE IF EXIST ANOTHER VALUE NOT 5% from Root FEE */
                        if ($usrInfo[0]->leadspeek_type == 'enhance') {
                            if (isset($rootAccConResult->rootcomfee) && $rootAccConResult->rootcomfee != "") {
                                $rootCommissionSRAcc = $rootAccConResult->rootcomfee;
                                $rootAccConResult->rootcomsrval = $rootAccConResult->rootcomfeeval;
                            }
                            if (isset($rootAccConResult->rootcomfee1) && $rootAccConResult->rootcomfee1 != "") {
                                $rootCommissionAEAcc = $rootAccConResult->rootcomfee1;
                                $rootAccConResult->rootcomaeval = $rootAccConResult->rootcomfeeval1;
                            }
                        }

                        if (isset($rootAccConResult->rootcomsrval) && $rootAccConResult->rootcomsrval != "") {
                            $_rootFee = ($usrInfo[0]->leadspeek_type == 'enhance')?$platformfee:$rootFee;
                            $rootCommissionSRAccVal = ($_rootFee * (float) $rootAccConResult->rootcomsrval);
                            $rootCommissionSRAccVal = number_format($rootCommissionSRAccVal,2,'.','');
                        }
                        if (isset($rootAccConResult->rootcomaeval) && $rootAccConResult->rootcomaeval != "") {
                            $_rootFee = ($usrInfo[0]->leadspeek_type == 'enhance')?$platformfee:$rootFee;
                            $rootCommissionAEAccVal = ($_rootFee * (float) $rootAccConResult->rootcomaeval);
                            $rootCommissionAEAccVal = number_format($rootCommissionAEAccVal,2,'.','');
                        }
                        /** OVERRIDE IF EXIST ANOTHER VALUE NOT 5% from Root FEE */
                    }
                    /** GET ROOT CONNECTED ACCOUNT TO BE TRANSFER FOR CLEAN PROFIT AFTER CUT BY ROOT FEE COST */
                    if ($rootAccCon != "" && $cleanProfit > 0.5) {
                        
                        $cleanProfit = number_format($cleanProfit,2,'.','');

                        $stripe = new StripeClient([
                            'api_key' => $stripeseckey,
                            'stripe_version' => '2020-08-27'
                        ]);

                        try {
                            if($usrInfo[0]->leadspeek_type == 'enhance') {
                                // if root mobile
                                if(isset($rootAccConResult->feepercentagemob) && $rootAccConResult->feepercentagemob != "") {
                                    // calculation cleanProfit for mobile
                                    $feePercentageMob = (isset($rootAccConResult->feepercentagemob))?$rootAccConResult->feepercentagemob:0;
                                    $cleanProfitMob = ($platformfee_ori * $feePercentageMob) / 100;
                                    $cleanProfitMob = number_format($cleanProfitMob,2,'.','');

                                    // send cleanProfit to mobile
                                    $transferRootProfit = $stripe->transfers->create([
                                        'amount' => ($cleanProfitMob * 100),
                                        'currency' => 'usd',
                                        'destination' => $rootAccConMob,
                                        'description' => 'Profit Root App from Invoice ' . $companyName . ' #' . $_leadspeek_api_id . ' (' . date('m-d-Y',strtotime($todayDate)) . ')',
                                    ]);

                                    // Log::info([
                                    //     'action' => 'TRANSFER FOR ROOT FEE COMISSION',
                                    //     'leadspeek_type' => $usrInfo[0]->leadspeek_type,
                                    //     'action' => 'pembagian ke mobile jika enhance 1.1',
                                    //     'rootAccConMob' => $rootAccConMob,
                                    //     'feePercentageMob' => $feePercentageMob,
                                    //     'cleanProfitMob' => $cleanProfitMob,
                                    //     'amount' => ($cleanProfitMob * 100),
                                    //     'currency' => 'usd',
                                    //     'destination' => $rootAccConMob,
                                    //     'description' => 'Profit Root App from Invoice ' . $companyName . ' #' . $_leadspeek_api_id . ' (' . date('m-d-Y',strtotime($todayDate)) . ')',
                                    // ]);
                                }

                                if(isset($rootAccConResult->feepercentagedom) && $rootAccConResult->feepercentagedom != "") {
                                    // calculation cleanProfit for dominator
                                    $feePercentageDom = (isset($rootAccConResult->feepercentagedom))?$rootAccConResult->feepercentagedom:0;
                                    $cleanProfitDom = ($platformfee_ori * $feePercentageDom) / 100;
                                    $cleanProfitDom = number_format($cleanProfitDom,2,'.','');

                                    // // send cleanProfit to dominator
                                    $transferRootProfit = $stripe->transfers->create([
                                        'amount' => ($cleanProfitDom * 100),
                                        'currency' => 'usd',
                                        'destination' => $rootAccCon,
                                        'description' => 'Profit Root App from Invoice ' . $companyName . ' #' . $_leadspeek_api_id . ' (' . date('m-d-Y',strtotime($todayDate)) . ')',
                                    ]);

                                    // Log::info([
                                    //     'action' => 'TRANSFER FOR ROOT FEE COMISSION',
                                    //     'leadspeek_type' => $usrInfo[0]->leadspeek_type,
                                    //     'action' => 'pembagian ke dominator jika enhance 1.2',
                                    //     'rootAccCon' => $rootAccCon,
                                    //     'feePercentageDom' => $feePercentageDom,
                                    //     'cleanProfitDom' => $cleanProfitDom,
                                    //     'amount' => ($cleanProfitDom * 100),
                                    //     'currency' => 'usd',
                                    //     'destination' => $rootAccCon,
                                    //     'description' => 'Profit Root App from Invoice ' . $companyName . ' #' . $_leadspeek_api_id . ' (' . date('m-d-Y',strtotime($todayDate)) . ')',
                                    // ]);
                                }
                            }
                            else {
                                $transferRootProfit = $stripe->transfers->create([
                                    'amount' => ($cleanProfit * 100),
                                    'currency' => 'usd',
                                    'destination' => $rootAccCon,
                                    'description' => 'Profit Root App from Invoice ' . $companyName . ' #' . $_leadspeek_api_id . ' (' . date('m-d-Y',strtotime($todayDate)) . ' Ended Campaign)',
                                ]);

                                // Log::info([
                                //     'amount' => ($cleanProfit * 100),
                                //     'currency' => 'usd',
                                //     'destination' => $rootAccCon,
                                //     'description' => 'Profit Root App from Invoice ' . $companyName . ' #' . $_leadspeek_api_id . ' (' . date('m-d-Y',strtotime($todayDate)) . ' Ended Campaign)',
                                // ]);
                            }

                            // if (isset($transferSales->destination_payment)) {
                            //     $despay = $transferSales->destination_payment;

                            //     $transferSalesDesc =  $stripe->charges->update($despay,
                            //             [
                            //                 'description' => 'Profit Root App from Agency Invoice'
                            //             ],['stripe_account' => $rootAccCon]);
                            // }
                            
                            //$this->send_email(array($sale['email']),$from,'Commission fee from ' . $sale['company_name'] . ' #' . $_leadspeek_api_id ,$details,$attachement,'emails.salesfee',$companyParentID);
                        }catch (Exception $e) {
                            $this->send_notif_stripeerror('Profit Root Transfer Error','Profit Root Transfer Error to ' . $rootAccCon ,$_company_root_id);
                        }

                    }

                     /** SEND EMAIL TO CHECK */
                        $fromDebug = [
                            'address' => 'noreply@sitesettingsapi.com',
                            'name' => 'KartraWebhook',
                            'replyto' => 'noreply@sitesettingsapi.com',
                        ];

                        $detailsDebug = [
                            'title' => ucwords("Comission Root Test"),
                            'content' => "accConSR:" . $rootCommissionSRAcc . "<br/>comission: " . $rootCommissionFee . "<br>Company Root ID : " . $_company_root_id . '<br>Root Clean Profit :' . $cleanProfit,
                        ];
                        

                        //return response()->json(array('result'=>$appID,'postdata'=>$postdata_decoded));
                        $this->send_email(array('harrison.budiman@gmail.com'),$fromDebug,ucwords("Comission Root Test"),$detailsDebug,array(),'emails.customemail',"");
                        /** SEND EMAIL TO CHECK */

                    /** TRANSFER FOR ROOT SALES Representative */
                    if ($rootCommissionSRAcc != "" && $rootCommissionSRAccVal > 0.5) {
                        $stripe = new StripeClient([
                            'api_key' => $stripeseckey,
                            'stripe_version' => '2020-08-27'
                        ]);

                        try {
                            $transferRootProfitSR = $stripe->transfers->create([
                                'amount' => ($rootCommissionSRAccVal * 100),
                                'currency' => 'usd',
                                'destination' => $rootCommissionSRAcc,
                                'description' => 'Commision Root app from  Invoice ' . $companyName . ' #' . $_leadspeek_api_id . ' (' . date('m-d-Y',strtotime($todayDate)) . ' Ended Campaign)',
                            ]);

                            
                            //$this->send_email(array($sale['email']),$from,'Commission fee from ' . $sale['company_name'] . ' #' . $_leadspeek_api_id ,$details,$attachement,'emails.salesfee',$companyParentID);
                        }catch (Exception $e) {
                            $this->send_notif_stripeerror('Commission Root Transfer Error','Profit Root Transfer Error to ' . $rootCommissionSRAcc ,$_company_root_id);
                        }
                    }
                    /** TRANSFER FOR ROOT SALES Representative */

                    /** TRANSFER FOR ROOT Account Executive */
                    if ($rootCommissionAEAcc != "" && $rootCommissionAEAccVal > 0.5) {
                        $stripe = new StripeClient([
                            'api_key' => $stripeseckey,
                            'stripe_version' => '2020-08-27'
                        ]);

                        try {
                            $transferRootProfitAE = $stripe->transfers->create([
                                'amount' => ($rootCommissionAEAccVal * 100),
                                'currency' => 'usd',
                                'destination' => $rootCommissionAEAcc,
                                'description' => 'Commision Root app from  Invoice ' . $companyName . ' #' . $_leadspeek_api_id . ' (' . date('m-d-Y',strtotime($todayDate)) . ' Ended Campaign)',
                            ]);

                            
                            //$this->send_email(array($sale['email']),$from,'Commission fee from ' . $sale['company_name'] . ' #' . $_leadspeek_api_id ,$details,$attachement,'emails.salesfee',$companyParentID);
                        }catch (Exception $e) {
                            $this->send_notif_stripeerror('Commission Root Transfer Error','Profit Root Transfer Error to ' . $rootCommissionAEAcc ,$_company_root_id);
                        }
                    }
                   /** TRANSFER FOR ROOT Account Executive */

                //}
            }
            /** CHARGE ROOT FEE AGENCY */

        }
        /** CHARGE WITH STRIPE */

        if (trim($sr_id) == "") {
            $sr_id = 0;
        }
        if (trim($ae_id) == "") {
            $ae_id = 0;
        }

        if (trim($ar_id) == "") {
            $ar_id = 0;
        }

        $statusClientPayment = $statusPayment;
        $invoiceID = "";
        if(empty($leadspeek_invoices_id) || trim($leadspeek_invoices_id) == '')
        {
            // info('buat invoice');
            $invoiceCreated = LeadspeekInvoice::create([
                'topup_agencies_id' => $topup_agencies_id,
                'company_id' => $_company_id,
                'user_id' => $_user_id,
                'leadspeek_api_id' => $_leadspeek_api_id,
                'invoice_number' => '',
                'payment_term' => $clientPaymentTerm,
                'onetimefee' => 0,
                'platform_onetimefee' => $platform_LeadspeekPlatformFee,
                'min_leads' => $minLeads,
                'exceed_leads' => $exceedLeads,
                'total_leads' => $ongoingLeads,
                'min_cost' => $minCostLeads,
                'platform_min_cost' => $platform_LeadspeekMinCostMonth,
                'cost_leads' => $costLeads,
                'platform_cost_leads' => $platform_LeadspeekCostperlead,
                'total_amount' => $totalAmount,
                'platform_total_amount' => $platformfee_ori,
                'root_total_amount' => $rootFee,
                'status' => $statusPayment,
                'customer_payment_id' => $paymentintentID,
                'customer_stripe_id' => $custStripeID,
                'customer_card_id' => $custStripeCardID,
                'platform_customer_payment_id' => $platform_paymentintentID,
                'error_payment' => $errorstripe,
                'platform_error_payment' => $platform_errorstripe,
                'invoice_date' => $todayDate,
                'invoice_start' => date('Y-m-d H:i:s',strtotime($startBillingDate)),
                'invoice_end' => date('Y-m-d H:i:s',strtotime($endBillingDate)),
                'sent_to' => json_encode($reportSentTo),
                'sr_id' => $sr_id,
                'sr_fee' => $sales_fee,
                'ae_id' => $ae_id,
                'ae_fee' => $sales_fee,
                'ar_id' => $ar_id,
                'ar_fee' => $sales_fee,
                'active' => 'T',
            ]);
            $invoiceID = $invoiceCreated->id;
        }
        else 
        {
            // info('update invoice');
            $invoiceID = $leadspeek_invoices_id;
            LeadspeekInvoice::where('id','=',$invoiceID)
                            ->update([
                                'topup_agencies_id' => $topup_agencies_id,
                                'company_id' => $_company_id,
                                'user_id' => $_user_id,
                                'invoice_number' => '',
                                'payment_term' => $clientPaymentTerm,
                                'onetimefee' => 0,
                                'platform_onetimefee' => $platform_LeadspeekPlatformFee,
                                'min_leads' => $minLeads,
                                'exceed_leads' => '0',
                                'total_leads' => $ongoingLeads,
                                'min_cost' => $minCostLeads,
                                'platform_min_cost' => $platform_LeadspeekMinCostMonth,
                                'cost_leads' => $costLeads,
                                'platform_cost_leads' => $platform_LeadspeekCostperlead,
                                'total_amount' => $totalAmount,
                                'root_total_amount' => $rootFee,
                                'status' => $statusPayment,
                                'customer_payment_id' => $paymentintentID,
                                'platform_customer_payment_id' => $platform_paymentintentID,
                                'error_payment' => $errorstripe,
                                'platform_error_payment' => $platform_errorstripe,
                                'invoice_date' => date('Y-m-d'),
                                'invoice_start' => date('Y-m-d'),
                                'invoice_end' => date('Y-m-d'),
                                'sent_to' => json_encode($reportSentTo),
                                'sr_id' => $sr_id,
                                'sr_fee' => $sales_fee,
                                'ae_id' => $ae_id,
                                'ae_fee' => $sales_fee,
                                'ar_id' => $ar_id,
                                'ar_fee' => $sales_fee,
                                'active' => 'T',
                            ]);
        }

        $invoice = LeadspeekInvoice::find($invoiceID);
        if(!empty($invoice)) {
            $invoice->invoice_number = $invoiceNum . '-' . $invoiceID;
            $invoice->save();
        }

        $lpupdate = LeadspeekUser::find($_lp_user_id);
        $lpupdate->ongoing_leads = 0;
        //$lpupdate->start_billing_date = $todayDate;
        $lpupdate->start_billing_date = date('Y-m-d H:i:s',strtotime($endBillingDate));
        $lpupdate->lifetime_cost = ($lpupdate->lifetime_cost + $totalAmount);
        $lpupdate->save();
        
        /** FIND ADMIN EMAIL */
        $tmp = User::select('email')->whereIn('id', $adminnotify)->get();
        $adminEmail = array();
        foreach($tmp as $ad) {
            array_push($adminEmail,$ad['email']);
        }
        //array_push($adminEmail,'serverlogs@sitesettingsapi.com');
        /** FIND ADMIN EMAIL */
        
        if ($statusPayment == 'paid') {
            $statusPayment = "Customer's Credit Card Successfully Charged ";
        }else if ($statusPayment == 'failed') {
            $statusPayment = "Customer's Credit Card Failed";
        }else{
            $statusPayment = "Customer's Credit Card Successfully Charged ";
        }

        if ($totalAmount == '0.00' || $totalAmount == '0') {
            $statusPayment = "Customer's Credit Card Successfully Charged";
        }

        if ($platformfee_ori != '0.00' || $platformfee_ori != '0') {
            if ($statusClientPayment == 'failed' && $clientPaymentTerm != 'Prepaid') {
                $statusPayment .= " and Agency's Card Charged For Overage";
            }
        }

        if ($AgencyManualBill == "T") {
            $statusPayment = "You must directly bill your client the amount due.";
        }
        
        $platform_LeadspeekCostperlead = number_format($platform_LeadspeekCostperlead,2,'.','');
        
        $agencyNet = "";
        if ($totalAmount > $platformfee_ori) {
            $agencyNet = $totalAmount - $platformfee_ori;
            $agencyNet = number_format($agencyNet,2,'.','');
        }
        
        $AdminDefault = $this->get_default_admin($_company_root_id);
        $AdminDefaultEmail = (isset($AdminDefault[0]['email']))?$AdminDefault[0]['email']:'';
        $rootCompanyInfo = $this->getCompanyRootInfo($_company_root_id);

        $defaultdomain = $this->getDefaultDomainEmail($_company_root_id);

        $details = [
            'name'  => $companyName,
            'invoiceNumber' => $invoiceNum . '-' . $invoiceID,
            'min_leads' => $minLeads,
            //'exceed_leads' => $exceedLeads,
            'total_leads' => $ongoingLeads,
            'min_cost' => $minCostLeads,
            'platform_min_cost' => $platform_LeadspeekMinCostMonth,
            'cost_leads' => $costLeads,
            'platform_cost_leads' => $platform_LeadspeekCostperlead,
            'total_amount' => $totalAmount,
            'platform_total_amount' => $platformfee_ori,
            //'invoiceDate' => date('m-d-Y',strtotime($todayDate)),
            'startBillingDate' => date('m-d-Y H:i:s',strtotime($startBillingDate)),
            'endBillingDate' =>  date('m-d-Y H:i:s',strtotime($endBillingDate)),
            'invoiceStatus' => $statusPayment,
            'cardlast' => trim($cardlast),
            'leadspeekapiid' => $_leadspeek_api_id,
            'paymentterm' => $clientPaymentTerm,
            //'onetimefee' => '0',
            'invoicetype' => 'agency',
            'agencyname' => $rootCompanyInfo['company_name'],
            'defaultadmin' => $AdminDefaultEmail,
            'agencyNet' => $agencyNet,
            'rootFee' => $rootFee,
            'cleanProfit' => $cleanProfit,
        ];
        $attachement = array();
        
        $from = [
            'address' => 'noreply@' . $defaultdomain,
            'name' => 'Invoice',
            'replyto' => 'support@' . $defaultdomain,
        ];

        $subjectFailed = "";
        if ($statusClientPayment == 'failed') {
            $subjectFailed = "Failed Payment - ";
        }

        $this->send_email($adminEmail,$from,$subjectFailed . 'Invoice for ' . $companyName . ' #' . $_leadspeek_api_id . ' (' . date('m-d-Y',strtotime($todayDate)) . ' Ended Campaign)',$details,$attachement,'emails.tryseramatchlistinvoice',$_company_parent);
        $this->send_email(array('serverlogs@sitesettingsapi.com'),$from,$subjectFailed . 'Invoice for ' . $companyName . ' #' . $_leadspeek_api_id . ' (' . date('m-d-Y',strtotime($todayDate)) . ' Ended Campaign)',$details,$attachement,'emails.tryseramatchlistinvoice','');

        /** UPDATE ROOT FEE PAYMENT */
        if (isset($transferRootProfit->destination_payment)) {
            $despay = $transferRootProfit->destination_payment;
            try {
                $transferSalesDesc =  $stripe->charges->update($despay,
                        [
                            'description' => 'Profit Root App from Invoice ' . $companyName . ' #' . $_leadspeek_api_id . ' (' . date('m-d-Y',strtotime($todayDate)) . ' Ended Campaign)'
                        ],['stripe_account' => $rootAccCon]);
                }catch (Exception $e) {
                    Log::warning('ERROR : Profit Root App from Invoice ' . $companyName . ' #' . $_leadspeek_api_id . ' (' . date('m-d-Y',strtotime($todayDate)) . ') - ' . $despay);
                }
        }
        
        /** GET ROOT ADMIN */
        $rootAdmin = User::select('email')
                                ->where('company_id','=',$_company_root_id)
                                ->where('isAdmin','=','T')
                                ->where('user_type','=','userdownline')
                                ->orWhere('user_type','=','user')
                                ->get();
        /** GET ROOT ADMIN */
        //foreach($rootAdmin as $radm) {
            //$this->send_email(array($radm['email']),$from,'Profit Root App from Invoice ' . $companyName . ' #' . $_leadspeek_api_id . ' (' . date('m-d-Y',strtotime($todayDate)) . ' Ended Campaign)' ,$details,$attachement,'emails.rootProfitTransfer',$usrInfo[0]->company_parent);
            if ($details['cleanProfit'] != '0' && $details['cleanProfit'] != '') {
                $this->send_email(array('serverlogs@sitesettingsapi.com'),$from,'Profit Root App from Invoice ' . $companyName . ' #' . $_leadspeek_api_id . ' (' . date('m-d-Y',strtotime($todayDate)) . ' Ended Campaign)' ,$details,$attachement,'emails.rootProfitTransfer',$_company_parent);
            }
        //}
        /** UPDATE ROOT FEE PAYMENT */
        
        /** UPDATE COMISSION ROOT SALES */
        if (isset($transferRootProfitSR->destination_payment)) {
            $despay = $transferRootProfitSR->destination_payment;

            try {
                $transferSalesDescSR =  $stripe->charges->update($despay,
                        [
                            'description' => 'Commision Root app from  Invoice ' . $companyName . ' #' . $_leadspeek_api_id . ' (' . date('m-d-Y',strtotime($todayDate)) . ' Ended Campaign)'
                        ],['stripe_account' => $rootCommissionSRAcc]);
             }catch (Exception $e) {
                Log::warning('Commision Root app from  Invoice ' . $companyName . ' #' . $_leadspeek_api_id . ' (' . date('m-d-Y',strtotime($todayDate)) . ') - SA -' . $despay);
            }
        }

        if (isset($transferRootProfitAE->destination_payment)) {
            $despay = $transferRootProfitAE->destination_payment;
            try {
                $transferSalesDescAE =  $stripe->charges->update($despay,
                        [
                            'description' => 'Commision Root app from  Invoice ' . $companyName . ' #' . $_leadspeek_api_id . ' (' . date('m-d-Y',strtotime($todayDate)) . ' Ended Campaign)'
                        ],['stripe_account' => $rootCommissionAEAcc]);
                }catch (Exception $e) {
                    Log::warning('Commision Root app from  Invoice ' . $companyName . ' #' . $_leadspeek_api_id . ' (' . date('m-d-Y',strtotime($todayDate)) . ') - AE -' . $despay);
                }
        }

        
        /** UPDATE COMISSION ROOT SALES */

        /** UPDATE CLIENT STRIPE PAYMENT DESCRIPTION */
        if ($paymentintentID != "") {
            try {
                $updatePaymentClientDesc =  $stripe->paymentIntents->update($paymentintentID,
                    [
                        'description' => '#' . $invoiceNum . '-' . $invoiceID . ' ' . $companyParentName . '-' . $companyName . ' #' . $_leadspeek_api_id . '(ended)',
                    ],['stripe_account' => $accConID]);
            } catch (Exception $e) {
                Log::warning('Error Update Client Stripe Payment Description');
            }
            /** UPDATE CLIENT STRIPE PAYMENT DESCRIPTION */
        }
        if ($platform_paymentintentID != "" && $platform_paymentintentID != "topup_agency") {
            /** UPDATE AGENCY STRIPE PAYMENT DESCRIPTION */
            try {
                $updatePaymentClientDesc =  $stripe->paymentIntents->update($platform_paymentintentID,
                    [
                        'description' => 'Agency #' . $invoiceNum . '-' . $invoiceID . ' ' . $companyParentName . '-' . $companyName . ' #' . $_leadspeek_api_id . '(ended)',
                    ]);
            } catch (Exception $e) {
                Log::warning('Error Update Agency Stripe Payment Description');
            }
            /** UPDATE AGENCY STRIPE PAYMENT DESCRIPTION */
        }

        /** CHECK IF FAILED PAYMENT THEN PAUSED THE CAMPAIGN AND SENT EMAIL*/
        // info(['statusClientPayment' => $statusClientPayment]);
        if ($statusClientPayment == 'failed') {
            $ClientCompanyIDFailed = "";
            $ListFailedCampaign = "";
            $_ListFailedCampaign = "";
            $_failedUserID = "";

            $leadsuser = LeadspeekUser::select('leadspeek_users.leadspeek_type','leadspeek_users.active','leadspeek_users.disabled','leadspeek_users.trysera','leadspeek_users.leadspeek_organizationid','leadspeek_users.leadspeek_campaignsid','users.customer_payment_id','leadspeek_users.user_id','users.company_id')
                                ->join('users','leadspeek_users.user_id','=','users.id')
                                ->where('leadspeek_users.leadspeek_api_id','=',$_leadspeek_api_id)
                                ->get();
            if (count($leadsuser) > 0) {
                foreach($leadsuser as $lds) {
                    $ClientCompanyIDFailed = $lds['company_id'];

                    //if ($lds['active'] == "T" || ($lds['active'] == "F" && $lds['disabled'] == "F")) {
                    if (!($lds['active'] == "F" && $lds['disabled'] == "T" && $lds['active_user'] == "F")) {
                        $http = new \GuzzleHttp\Client;
                        $appkey = config('services.trysera.api_id');
                        $organizationid = ($lds['leadspeek_organizationid'] != "")?$lds['leadspeek_organizationid']:"";
                        $campaignsid = ($lds['leadspeek_campaignsid'] != "")?$lds['leadspeek_campaignsid']:"";    
                        $userStripeID = $lds['customer_payment_id'];

                        $updateLeadspeekUser = LeadspeekUser::find($_lp_user_id);
                        $updateLeadspeekUser->active = 'F';
                        $updateLeadspeekUser->disabled = 'T';
                        $updateLeadspeekUser->active_user = 'F';
                        // $updateLeadspeekUser->last_lead_pause = date('Y-m-d H:i:s');
                        $updateLeadspeekUser->save();
                        /** DISABLED THE TRYSERA ALSO MAKE IT IN ACTIVE */

                        /** UPDATE USER CARD STATUS */
                        $_failedUserID = $lds['user_id'];
                        $updateUser = User::find($lds['user_id']);

                        $failedInvoiceID = $invoiceID;
                        $failedInvoiceNumber = $invoiceNum . '-' . $invoiceID;
                        $failedTotalAmount = $totalAmount;
                        $failedCampaignID = $_leadspeek_api_id;

                        if (trim($updateUser->failed_invoiceid) != '') {
                            $failedInvoiceID = $updateUser->failed_invoiceid . '|' . $failedInvoiceID;
                        }
                        if (trim($updateUser->failed_invoicenumber) != '') {
                            $failedInvoiceNumber = $updateUser->failed_invoicenumber . '|' . $failedInvoiceNumber;
                        }
                        if (trim($updateUser->failed_total_amount) != '') {
                            $failedTotalAmount = $updateUser->failed_total_amount . '|' . $failedTotalAmount;
                        }
                        if (trim($updateUser->failed_campaignid) != '') {
                            $failedCampaignID = $updateUser->failed_campaignid . '|' . $failedCampaignID;
                        }

                        
                        $updateUser->payment_status = 'failed';
                        $updateUser->failed_invoiceid = $failedInvoiceID;
                        $updateUser->failed_invoicenumber = $failedInvoiceNumber;
                        $updateUser->failed_total_amount = $failedTotalAmount;
                        $updateUser->failed_campaignid = $failedCampaignID;
                        $updateUser->save();
                        /** UPDATE USER CARD STATUS */

                        /** ACTIVATE CAMPAIGN SIMPLIFI */
                        // if ($organizationid != '' && $campaignsid != '' && $lds['leadspeek_type'] == 'locator') {
                        //     $camp = $this->startPause_campaign($organizationid,$campaignsid,'stop');
                        //     if ($camp != true) {
                        //         /** SEND EMAIL TO ME */
                        //             $details = [
                        //                 'errormsg'  => 'Simpli.Fi Error Leadspeek ID :' . $_leadspeek_api_id. '<br/>',
                        //             ];

                        //             $from = [
                        //                 'address' => 'noreply@sitesettingsapi.com',
                        //                 'name' => 'support',
                        //                 'replyto' => 'noreply@sitesettingsapi.com',
                        //             ];
                        //             $this->send_email(array('serverlogs@sitesettingsapi.com'),'Start Pause Campaign Failed (INTERNAL - CronAPI-due the payment failed - L2197) #' .$_leadspeek_api_id,$details,array(),'emails.tryseramatcherrorlog',$from,'');
                        //         /** SEND EMAIL TO ME */
                        //     }
                        // }
                        /** ACTIVATE CAMPAIGN SIMPLIFI */

                        $ListFailedCampaign = $ListFailedCampaign . $_leadspeek_api_id . '<br/>';
                        $_ListFailedCampaign = $_ListFailedCampaign . $_leadspeek_api_id . '|';

                    }
                }

                /** PAUSED THE OTHER ACTIVE CAMPAIGN FOR THIS CLIENT */
                $otherCampaignPause = false;

                $leadsuser = LeadspeekUser::select('leadspeek_users.id','leadspeek_users.leadspeek_type','leadspeek_users.active','leadspeek_users.disabled','leadspeek_users.trysera','leadspeek_users.leadspeek_organizationid','leadspeek_users.leadspeek_campaignsid','users.customer_payment_id','leadspeek_users.user_id','users.company_id','leadspeek_users.leadspeek_api_id')
                                ->join('users','leadspeek_users.user_id','=','users.id')
                                ->where('users.company_id','=',$ClientCompanyIDFailed)
                                ->where('users.user_type','=','client')
                                ->where('leadspeek_users.paymentterm','<>','Prepaid')
                                ->where('leadspeek_users.leadspeek_api_id','<>',$_leadspeek_api_id)
                                ->where(function($query){
                                    $query->where(function($query){
                                        $query->where('leadspeek_users.active','=','T')
                                            ->where('leadspeek_users.disabled','=','F')
                                            ->where('leadspeek_users.active_user','=','T');
                                    })
                                    ->orWhere(function($query){
                                        $query->where('leadspeek_users.active','=','F')
                                            ->where('leadspeek_users.disabled','=','F')
                                            ->where('leadspeek_users.active_user','=','T');
                                    });
                                })->get();

                if (count($leadsuser) > 0) {
                    $otherCampaignPause = true;
                    foreach($leadsuser as $lds) {
                        $http = new \GuzzleHttp\Client;
                        
                        $organizationid = ($lds['leadspeek_organizationid'] != "")?$lds['leadspeek_organizationid']:"";
                        $campaignsid = ($lds['leadspeek_campaignsid'] != "")?$lds['leadspeek_campaignsid']:"";    
                        $userStripeID = $lds['customer_payment_id'];

                        $updateLeadspeekUser = LeadspeekUser::find($lds['id']);
                        $updateLeadspeekUser->active = 'F';
                        $updateLeadspeekUser->disabled = 'T';
                        $updateLeadspeekUser->active_user = 'T';
                        $updateLeadspeekUser->last_lead_pause = date('Y-m-d H:i:s');
                        $updateLeadspeekUser->save();
                        /** DISABLED THE TRYSERA ALSO MAKE IT IN ACTIVE */
                        
                        /** ACTIVATE CAMPAIGN SIMPLIFI */
                        if ($organizationid != '' && $campaignsid != '' && $lds['leadspeek_type'] == 'locator') {
                            $camp = $this->startPause_campaign($organizationid,$campaignsid,'pause');
                            if ($camp != true) {
                                /** SEND EMAIL TO ME */
                                    $details = [
                                        'errormsg'  => 'Simpli.Fi Error Leadspeek ID :' . $_leadspeek_api_id. '<br/>',
                                    ];

                                    $from = [
                                        'address' => 'noreply@sitesettingsapi.com',
                                        'name' => 'support',
                                        'replyto' => 'noreply@sitesettingsapi.com',
                                    ];
                                    $this->send_email(array('serverlogs@sitesettingsapi.com'),$from,'Start Pause Campaign Failed (INTERNAL - CronAPI-due the payment failed - L2197) #' .$_leadspeek_api_id,$details,array(),'emails.tryseramatcherrorlog','');
                                /** SEND EMAIL TO ME */
                            }
                        }
                        /** ACTIVATE CAMPAIGN SIMPLIFI */

                        $ListFailedCampaign = $ListFailedCampaign . $lds['leadspeek_api_id'] . '<br/>';
                        $_ListFailedCampaign = $_ListFailedCampaign . $lds['leadspeek_api_id'] . '|';
                    }
                }
                /** PAUSED THE OTHER ACTIVE CAMPAIGN FOR THIS CLIENT */
                
                if ($otherCampaignPause) {
                    /** UPDATE ON INVOICE TABLE THAT FAILED WITH CAMPAIGN LIST THAT PAUSED */
                    $updateInvoiceCampaignPaused = LeadspeekInvoice::find($invoiceID);
                    $updateInvoiceCampaignPaused->campaigns_paused = rtrim($_ListFailedCampaign,"|");
                    $updateInvoiceCampaignPaused->save();
                    /** UPDATE ON INVOICE TABLE THAT FAILED WITH CAMPAIGN LIST THAT PAUSED */

                    $usrUpdate = User::find($_failedUserID);
                    $usrUpdate->failed_campaigns_paused = rtrim($_ListFailedCampaign,"|");
                    $usrUpdate->save();
                    
                    if (trim($ListFailedCampaign) != '' && (isset($userStripeID) && $userStripeID != '')) {
                        /** SEND EMAIL TELL THIS CAMPAIGN HAS BEEN PAUSED BECAUSE FAILED PAYMENT */
                        $from = [
                            'address' => 'noreply@' . $defaultdomain,
                            'name' => 'Invoice',
                            'replyto' => 'support@' . $defaultdomain,
                        ];
                        
                        $details = [
                            'campaignid'  => $_leadspeek_api_id,
                            'stripeid' => (isset($userStripeID))?$userStripeID:'',
                            'othercampaigneffected' => $ListFailedCampaign,
                        ];
                        
                        $this->send_email($adminEmail,$from,'Campaign ' . $companyName . ' #' . $_leadspeek_api_id . ' (has been pause due the payment failed)',$details,$attachement,'emails.invoicefailed','');
                        /** SEND EMAIL TELL THIS CAMPAIGN HAS BEEN PAUSED BECAUSE FAILED PAYMENT */
                    }
                }
            }
        }
        /** CHECK IF FAILED PAYMENT THEN PAUSED THE CAMPAIGN AND SENT EMAIL*/

        if($platformStatusPayment == 'failed') {
            $statusClientPayment = 'failed';
        }

        return $statusClientPayment;
    }

    public function startPause_campaign($_organizationID,$_campaignsID,$status='') {
        $http = new \GuzzleHttp\Client;

        $appkey = "86bb19a0-43e6-0139-8548-06b4c2516bae";
        $usrkey = "63c52610-87cd-0139-b15f-06a60fe5fe77";
        $organizationID = $_organizationID;
        $campaignsID = explode(PHP_EOL, $_campaignsID);

        $ProcessStatus = true;

        for($i=0;$i<count($campaignsID);$i++) {
            
           
            try {
                /** CHECK ACTIONS IF CAMPAIGN ALLOW TO RUN STATUS  */
                $apiURL = "https://app.simpli.fi/api/organizations/" . $organizationID . "/campaigns/" . $campaignsID[$i];
                $options = [
                    'headers' => [
                        'X-App-Key' => $appkey,        
                        'X-User-Key' => $usrkey,
                        'Content-Type' => 'application/json',
                    ],
                ];

                $response = $http->get($apiURL,$options);
                $result =  json_decode($response->getBody());
                
                for($j=0;$j<count($result->campaigns[0]->actions);$j++) {
                    if ($status == 'activate') {
                        if(isset($result->campaigns[0]->actions[$j]->activate)) {
                            //echo "activate";
                            try {
                                /** ACTIVATE THE CAMPAIGN */
                                $ActionApiURL = "https://app.simpli.fi/api/organizations/" . $organizationID . "/campaigns/" . $campaignsID[$i] . "/activate";
                                $ActionResponse = $http->post($ActionApiURL,$options);
                                /** ACTIVATE THE CAMPAIGN */
                            }catch (\GuzzleHttp\Exception\BadResponseException $e) {
                                $details = [
                                    'errormsg'  => 'Error when trying to Activate Campaign Organization ID : ' . $organizationID . ' Campaign ID :' . $campaignsID[$i] . ' (' . $e->getMessage() . ')',
                                ];
                                $from = [
                                    'address' => 'noreply@exactmatchmarketing.com',
                                    'name' => 'Support',
                                    'replyto' => 'support@exactmatchmarketing.com',
                                ];
                                $ProcessStatus = false;
                                $this->send_email(array('serverlogs@sitesettingsapi.com'),$from,'Error Log for Activate Campaign (Apps DATA - startPause_campaign - L2883) ' . $e->getCode(),$details,array(),'emails.tryseramatcherrorlog','');
                            }
                        }
                    }else if ($status == 'pause') {
                        if(isset($result->campaigns[0]->actions[$j]->pause)) {
                            //echo "Pause";
                            try {
                                /** PAUSE THE CAMPAIGN */
                                $ActionApiURL = "https://app.simpli.fi/api/organizations/" . $organizationID . "/campaigns/" . $campaignsID[$i] . "/pause";
                                $ActionResponse = $http->post($ActionApiURL,$options);
                                /** PAUSE THE CAMPAIGN */
                            }catch (\GuzzleHttp\Exception\BadResponseException $e) {
                                $details = [
                                    'errormsg'  => 'Error when trying to Pause Campaign Organization ID : ' . $organizationID . ' Campaign ID :' . $campaignsID[$i] . ' (' . $e->getMessage() . ')',
                                ];
                                $from = [
                                    'address' => 'noreply@exactmatchmarketing.com',
                                    'name' => 'Support',
                                    'replyto' => 'support@exactmatchmarketing.com',
                                ];
                                $ProcessStatus = false;
                                $this->send_email(array('serverlogs@sitesettingsapi.com'),$from,'Error Log for Pause Campaign (Apps DATA - startPause_campaign - L2903) ' . $e->getCode(),$details,array(),'emails.tryseramatcherrorlog','');
                            }
                        }
                    }else if ($status == 'stop') {
                        if(isset($result->campaigns[0]->actions[$j]->end)) {
                            //echo "Pause";
                            try {
                                /** PAUSE THE CAMPAIGN */
                                $ActionApiURL = "https://app.simpli.fi/api/organizations/" . $organizationID . "/campaigns/" . $campaignsID[$i] . "/end";
                                $ActionResponse = $http->post($ActionApiURL,$options);
                                /** PAUSE THE CAMPAIGN */
                            }catch (\GuzzleHttp\Exception\BadResponseException $e) {
                                $details = [
                                    'errormsg'  => 'Error when trying to Pause Campaign Organization ID : ' . $organizationID . ' Campaign ID :' . $campaignsID[$i] . ' (' . $e->getMessage() . ')',
                                ];
                                $from = [
                                    'address' => 'noreply@exactmatchmarketing.com',
                                    'name' => 'Support',
                                    'replyto' => 'support@exactmatchmarketing.com',
                                ];
                                $ProcessStatus = false;
                                $this->send_email(array('serverlogs@sitesettingsapi.com'),$from,'Error Log for Pause Campaign (Apps DATA - startPause_campaign - L2923) ' . $e->getCode(),$details,array(),'emails.tryseramatcherrorlog','');
                            }
                        }
                    }
                    //echo $result->campaigns[0]->actions[$j]->activate[0];
                }
                
                //return response()->json(array("result"=>'success','message'=>'xx','param'=>$result));
                /** CHECK ACTIONS IF CAMPAIGN ALLOW TO RUN STATUS  */
            }catch (\GuzzleHttp\Exception\BadResponseException $e) {
                $ProcessStatus = false;

                $details = [
                    'errormsg'  => 'Error when trying to get campaign information Organization ID : ' . $organizationID . ' Campaign ID :' . $campaignsID[$i] . '(' . $e->getMessage() . ')',
                ];
                $from = [
                    'address' => 'noreply@exactmatchmarketing.com',
                    'name' => 'Support',
                    'replyto' => 'support@exactmatchmarketing.com',
                ];
                $this->send_email(array('serverlogs@sitesettingsapi.com'),$from,'Error Log for Start / Pause Get Campaign (Apps DATA - startPause_campaign - L2941) ' . $e->getCode(),$details,array(),'emails.tryseramatcherrorlog','');

                // if ($e->getCode() === 400) {
                //     return response()->json(array("result"=>'failed','message'=>'Invalid Request. Please enter a username or a password.'), $e->getCode());
                // } else if ($e->getCode() === 401) {
                //     return response()->json(array("result"=>'failed','message'=>'Your credentials are incorrect. Please try again'), $e->getCode());
                // }

                // return response()->json(array("result"=>'failed','message'=>'Something went wrong on the server.'), $e->getCode());
            }
            
        }
        
        return $ProcessStatus;
        
    }

    public function  makeSafeTitleName($fileName) {
        // Remove any characters that are not alphanumeric, underscores, dots, hyphens, or spaces
        $fileName = preg_replace("/[^a-zA-Z0-9+#_. -]/", "", $fileName);

        // Remove multiple consecutive dots, underscores, hyphens, or spaces
        $fileName = preg_replace("/(\.|-|_|\s){2,}/", "$1", $fileName);

        // Trim any leading or trailing dots, underscores, hyphens, or spaces
        $fileName = trim($fileName, '._- ');

        return $fileName;
    }

    public function  makeSafeFileName($fileName) {
        // Remove any characters that are not alphanumeric, underscores, dots, hyphens, or spaces
        $fileName = preg_replace("/[^a-zA-Z0-9_. -]/", "", $fileName);

        // Remove multiple consecutive dots, underscores, hyphens, or spaces
        $fileName = preg_replace("/(\.|-|_|\s){2,}/", "$1", $fileName);

        // Trim any leading or trailing dots, underscores, hyphens, or spaces
        $fileName = trim($fileName, '._- ');

        return $fileName;
    }

    /** GOHIGHLEVEL */

    public function ghl_createContact($company_id = "",$api_key = "",$ID = "",$ClickDate = "",$FirstName = "",$LastName = "",$Email = "",$Email2 = "",$Phone = "",$Phone2 = "",$Address = "",$Address2 = "",$City = "",$State = "",$Zipcode = "",$Keyword = "",$tags = array(),$campaignID="", $additional_fields = [], $status_keyword_to_tags = false) {
        if($api_key != '') {
            $http = new \GuzzleHttp\Client;

            $comset_name = 'gohlcustomfields';
            /** GET IF CUSTOM FIELD ALREADY EXIST */
            $email2Id = "";
            $phone2Id = "";
            $address2Id = "";
            $keywordId = "";
            //$urlId = "";
            $contactId = "";
            $clickDateId = "";
            
            $customfields = CompanySetting::where('company_id','=',$company_id)->whereEncrypted('setting_name',$comset_name)->get();
            if (count($customfields) > 0) {
                $_customfields = json_decode($customfields[0]['setting_value']);
                $email2Id = (isset($_customfields->email2Id))?$_customfields->email2Id:'';
                $phone2Id = (isset($_customfields->phone2Id))?$_customfields->phone2Id:'';
                $address2Id = (isset($_customfields->address2Id))?$_customfields->address2Id:'';
                $keywordId = (isset($_customfields->keywordId))?$_customfields->keywordId:'';
                //$urlId = (isset($_customfields->urlId))?$_customfields->urlId:'';
                $contactId = (isset($_customfields->contactId))?$_customfields->contactId:'';
                $clickDateId = (isset($_customfields->clickDateId))?$_customfields->clickDateId:'';

                if (!empty($additional_fields)) {
                    foreach ($additional_fields as &$field) {
                        $field['ghl_id'] = (isset($_customfields->{$field['db_id']})) ? $_customfields->{$field['db_id']} : '' ;
                    }
                    unset($field);
                }
            }
            /** GET IF CUSTOM FIELD ALREADY EXIST */

            $custom_fields = [
                $contactId => $ID,
                $clickDateId => $ClickDate,
                $email2Id => $Email2,
                $phone2Id => $Phone2,
                $address2Id => $Address2,
                $keywordId => $Keyword,
                //$urlId => $Url
            ];

            if (is_array($additional_fields) && !empty($additional_fields)) {
                foreach ($additional_fields as $fields) {      
                    if (isset($fields['ghl_id']) && $fields['value']) {
                        $custom_fields[$fields['ghl_id']] = $fields['value'];
                    }  
               }
            }

            if($status_keyword_to_tags){
                array_push($tags, $Keyword);
            }

            
            //$custom_fields = json_decode($custom_fields);
            //$tags = json_encode($tags);
            try {
                $apiEndpoint =  "https://rest.gohighlevel.com/v1/contacts/";
                $dataOptions = [
                    'headers' => [
                        'Authorization' => 'Bearer ' . $api_key,
                        'Content-Type' => 'application/json'
                    ],
                    'json' => [
                        "firstName" => $FirstName,
                        "lastName" => $LastName,
                        "name" => $FirstName . ' ' . $LastName,
                        "email" => $Email,
                        "phone" => $Phone,
                        "address1" => $Address,
                        "country" => "US",
                        "city" => $City,
                        "state" => $State,
                        "postalCode" => $Zipcode,
                        "customField" => $custom_fields,
                        "source" => "Campaign ID : #" . $campaignID,
                        "tags" => $tags,
                    ]
                ];
                
                
                $createContact = $http->post($apiEndpoint,$dataOptions);
                
                if ($createContact->getStatusCode() == 200) {
                    return [
                        'success' => true,
                        'error' => false,
                    ];
                } else {
                    return [
                        'success' => false,
                        'error' => 'Something went wrong, please try again later.',
                    ];
                }

            }catch (\GuzzleHttp\Exception\BadResponseException $e) {
                $errorBody = (string) $e->getResponse()->getBody();
                $decodedError = json_decode($errorBody, true);
                Log::warning('ghl_createContact (' . $Email . ') error add msg :' . $e);

                return [
                    'success' => false,
                    'error' => $decodedError['msg'],
                ];
            }
        }
    }

    public function ghl_searchCustomFieldID($api_key,$name) {
        $http = new \GuzzleHttp\Client;
        try {
            $apiEndpoint =  "https://rest.gohighlevel.com/v1/custom-fields";
            $dataOptions = [
                'headers' => [
                    'Authorization' => 'Bearer ' . $api_key,
                    'Content-Type' => 'application/json'
                ],
                'json' => [
                   
                ]
            ];

            $listfield = $http->get($apiEndpoint,$dataOptions);
            $result =  json_decode($listfield->getBody());
            $listResult = $result->customFields;
            foreach($listResult as $lr) {
                if (trim($name) == trim($lr->name)) {
                    return $lr->id;
                    break;
                }
            }

           
        }catch (\GuzzleHttp\Exception\BadResponseException $e) {
            return "error : " . $e->getMessage();
        }
    }

    public function ghl_CreateCustomField($api_key,$parentId = '',$name = '', $placeholder='',$dataType = 'TEXT',$showInForms = true){
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
            return $this->ghl_searchCustomFieldID($api_key,$name);
        }
    }

    public function ghl_CreateTag($api_key,$tagname = "") {
        $http = new \GuzzleHttp\Client;
        try {
            $apiEndpoint =  "https://rest.gohighlevel.com/v1/tags/";
            $dataOptions = [
                'headers' => [
                    'Authorization' => 'Bearer ' . $api_key,
                    'Content-Type' => 'application/json'
                ],
                'json' => [
                    "name" => $tagname
                ]
            ];

            $createfield = $http->post($apiEndpoint,$dataOptions);
            $result =  json_decode($createfield->getBody());
            return $result->id;
        }catch (\GuzzleHttp\Exception\BadResponseException $e) {
            return "";
        }
    }
    public function ghl_RemoveTag($api_key,$tagId = "") {
        $http = new \GuzzleHttp\Client;
        try {
            $apiEndpoint =  "https://rest.gohighlevel.com/v1/tags/" . $tagId;
            $dataOptions = [
                'headers' => [
                    'Authorization' => 'Bearer ' . $api_key,
                    'Content-Type' => 'application/json'
                ],
                'json' => [
                   
                ]
            ];

            $createfield = $http->delete($apiEndpoint,$dataOptions);
            //$result =  json_decode($createfield->getBody());
            return "";
        }catch (\GuzzleHttp\Exception\BadResponseException $e) {
            return "";
        }
    }
    /** GOHIGHLEVEL */

    /* MAILBOXPOWER */
    public function mbp_CreateGroups($api_key, $groups = '')
    {
        $http = new \GuzzleHttp\Client;
        try 
        {
            $apiEndpoint = "https://www.mailboxpower.com/api/v3/groups";
            $dataOptions = [
                'headers' => [
                    'APIKEY' => $api_key,
                ],
                'json' => [
                    'linkmessage' => '',
                    'groupname' => $groups
                ]
            ];

            $createTag = $http->post($apiEndpoint, $dataOptions);
            $result = json_decode($createTag->getBody());
            // Log::info(['result' => $result]);

            return $result->GROUPID;
        } 
        catch (\GuzzleHttp\Exception\BadResponseException $e) 
        {
            Log::error(['error_mbp_createtag_1' => $e->getMessage()]);
            return "";
        }
        catch (\Exception $e)
        {
            Log::error(['error_mbp_createtag_2' => $e->getMessage()]);
            return "";
        }
    }
    /* MAILBOXPOWER */

     /** ZAPIER */

     public function zap_sendrecord($webhook = "",$ClickDate = "",$FirstName = "",$LastName = "",$Email = "",$Email2 = "",$Phone = "",$Phone2 = "",$Address = "",$Address2 = "",$City = "",$State = "",$Zipcode = "",$Keyword = "",$url = "",$tags = array(),$campaignID="", $campaign_type = "") {
        if($webhook != '') {
            $http = new \GuzzleHttp\Client;
            // $newTags = [];
            // if (!empty($tags)) {
            //     foreach ($tags as $index => $tag) {
            //         $newTags[$index + 1] = $tag; 
            //     }
            // }
            try {
                $dataOptions = [
                    'json' => [
                        "clickdate" => $ClickDate,
                        "firstName" => $FirstName,
                        "lastName" => $LastName,
                        "name" => $FirstName . ' ' . $LastName,
                        "email1" => $Email,
                        "email2" => $Email2,
                        "phone1" => $Phone,
                        "phone2" => $Phone2,
                        "address1" => $Address,
                        "address2" => $Address2,    
                        "city" => $City,
                        "state" => $State,
                        "postalCode" => $Zipcode,
                        "keyword" => $Keyword,
                        "url" => $url,
                        "campaignID" => $campaignID,
                        "tags" => $tags,
                        "campaignType" => $campaign_type,
                    ]
                ];
                $send_record = $http->post($webhook,$dataOptions);
                $result =  json_decode($send_record->getBody());
            //    echo "<pre>";
            //    print_r($dataOptions);
            //    echo "</pre>";
            }catch (\GuzzleHttp\Exception\BadResponseException $e) {
                //log::warning('GHL Failed Create Contact : ' . $e->getMessage());
                echo $e->getMessage();
            }
        }
    }

    /** ZAPIER */

    /** CLICK FUNNELS */
    public function clickfunnels_CreateContact($api_key = '', $subdomain = '', $workspace_id = '', $data = []){
        $http = new \GuzzleHttp\Client;
        $url = "https://{$subdomain}.myclickfunnels.com/api/v2/workspaces/{$workspace_id}/contacts/upsert";

        if (trim($api_key) === '' || trim($subdomain) === '' || trim($workspace_id) === '') {
            return ['result' => 'failed', 'message' => 'please check required fields', 'status_code' => 400, 'data' => []];
        }

        try {
            $response = $http->request('POST', $url, [
                'headers' => [
                    'accept' => 'application/json',
                    'authorization' => "Bearer {$api_key}",
                    'content-type' => 'application/json',
                ],
                'json' => [
                    'contact' => $data
                ]
            ]);

            $body = $response->getBody();
            $resData = json_decode($body, true);

            return [
                'result' => 'success',
                'message' => 'Successfully create contact',
                'status_code' => 201,
                'data' => $resData,
            ];
        } catch (RequestException $e) {
            if ($e->hasResponse()) {
                $statusCode = $e->getResponse()->getStatusCode();

                if($statusCode == 400){
                    return [
                        'result' => 'failed',
                        'message' => 'invalid subdomain',
                        'status_code' => $statusCode,
                        'data' => []
                    ];
                }

                if($statusCode == 401){
                    return [
                        'result' => 'failed',
                        'message' => 'API key missing or invalid',
                        'status_code' => $statusCode,
                        'data' => []
                    ];
                }

                if($statusCode == 404){
                    return [
                        'result' => 'failed',
                        'message' => 'invalid workspace',
                        'status_code' => $statusCode,
                        'data' => []
                    ];
                }

                $errorMessage = $e->getResponse()->getBody()->getContents();
                $errorData = json_decode($errorMessage, true);
                $error = isset($errorData['error']) ? $errorData['error'] : 'Unknown error';

                return [
                    'result' => 'failed',
                    'message' => $error,
                    'status_code' => $statusCode,
                    'data' => []
                ];

            } else {
                return [
                    'result' => 'failed',
                    'message' => 'No response from server',
                    'status_code' => 500,
                    'data' => []
                ];
            }
        } catch (\Exception $e){
            $errorMessage = $e->getMessage();

            if(strpos($errorMessage, 'cURL error') === 0){
                return [
                    'result' => 'failed',
                    'message' => 'invalid subdomain',
                    'status_code' => 500,
                    'data' => []
                ];
            }

            return [
                'result' => 'failed',
                'message' => $errorMessage,
                'status_code' => 500,
                'data' => []
            ];
        }
    }

    public function clickfunnels_CreateTag($api_key = '', $subdomain = '', $workspace_id = '', $data = []){
        $http = new \GuzzleHttp\Client;
        $url = "https://{$subdomain}.myclickfunnels.com/api/v2/workspaces/{$workspace_id}/contacts/tags";

        if (trim($api_key) === '' || trim($subdomain) === '' || trim($workspace_id) === '') {
            return ['result' => 'failed', 'message' => 'please check required fields', 'status_code' => 400, 'data' => []];
        }

        try {
            $response = $http->request('POST', $url, [
                'headers' => [
                    'accept' => 'application/json',
                    'authorization' => "Bearer {$api_key}",
                    'content-type' => 'application/json',
                ],
                'json' => [
                    'contacts_tag' => $data
                ]
            ]);

            $body = $response->getBody();
            $resData = json_decode($body, true);

            return [
                'result' => 'success',
                'message' => 'Successfully create tag',
                'status_code' => 201,
                'data' => $resData,
            ];
        } catch (RequestException $e) {
            if ($e->hasResponse()) {
                $statusCode = $e->getResponse()->getStatusCode();

                if($statusCode == 400){
                    return [
                        'result' => 'failed',
                        'message' => 'invalid subdomain',
                        'status_code' => $statusCode,
                        'data' => []
                    ];
                }

                if($statusCode == 401){
                    return [
                        'result' => 'failed',
                        'message' => 'API key missing or invalid',
                        'status_code' => $statusCode,
                        'data' => []
                    ];
                }

                if($statusCode == 404){
                    return [
                        'result' => 'failed',
                        'message' => 'invalid workspace',
                        'status_code' => $statusCode,
                        'data' => []
                    ];
                }

                $errorMessage = $e->getResponse()->getBody()->getContents();
                $errorData = json_decode($errorMessage, true);
                $error = isset($errorData['error']) ? $errorData['error'] : 'Unknown error';

                return [
                    'result' => 'failed',
                    'message' => $error,
                    'status_code' => $statusCode,
                    'data' => []
                ];

            } else {
                return [
                    'result' => 'failed',
                    'message' => 'No response from server',
                    'status_code' => 500,
                    'data' => []
                ];
            }
        } catch (\Exception $e){
            $errorMessage = $e->getMessage();

            if(strpos($errorMessage, 'cURL error') === 0){
                return [
                    'result' => 'failed',
                    'message' => 'invalid subdomain',
                    'status_code' => 500,
                    'data' => []
                ];
            }

            return [
                'result' => 'failed',
                'message' => $errorMessage,
                'status_code' => 500,
                'data' => []
            ];
        }
    }

    public function clickfunnels_GetWorkSpaceId($api_key = '', $subdomain = '', $workspace_id = ''){
        $http = new \GuzzleHttp\Client;
        $url = "https://{$subdomain}.myclickfunnels.com/api/v2/workspaces/{$workspace_id}";

        if (trim($api_key) === '' || trim($subdomain) === '' || trim($workspace_id) === '') {
            return response()->json(['result' => 'failed', 'message' => 'please check required fields', 'status_code' => 400, 'id' => null], 400);
        }

        try {
            $response = $http->request('GET', $url, [
                'headers' => [
                    'accept' => 'application/json',
                    'authorization' => "Bearer {$api_key}",
                ]
            ]);

            $body = $response->getBody();
            $resData = json_decode($body, true);

            return [
                'result' => 'success',
                'message' => 'Successfully get workspace id',
                'status_code' => 200,
                'id' => $resData['id']
            ];
        } catch (RequestException $e) {
            if ($e->hasResponse()) {
                $statusCode = $e->getResponse()->getStatusCode();

                if($statusCode == 400){
                    return[
                        'result' => 'failed',
                        'message' => 'invalid subdomain',
                        'status_code' => $statusCode,
                        'id' => null
                    ];
                }

                if($statusCode == 401){
                    return [
                        'result' => 'failed',
                        'message' => 'API key missing or invalid',
                        'status_code' => $statusCode,
                        'id' => null
                    ];
                }

                if($statusCode == 404){
                    return [
                        'result' => 'failed',
                        'message' => 'invalid workspace',
                        'status_code' => $statusCode,
                        'id' => null
                    ];
                }

                $errorMessage = $e->getResponse()->getBody()->getContents();
                $errorData = json_decode($errorMessage, true);
                $error = isset($errorData['error']) ? $errorData['error'] : 'Unknown error';

                return [
                    'result' => 'failed',
                    'message' => $error,
                    'status_code' => $statusCode,
                    'id' => null
                ];
            } else {
                return [
                    'result' => 'failed',
                    'message' => 'No response from server',
                    'status_code' => 500,
                    'id' => null
                ];
            }
        } catch (\Exception $e){
            $errorMessage = $e->getMessage();

            if(strpos($errorMessage, 'cURL error') === 0){
                return [
                    'result' => 'failed',
                    'message' => 'invalid subdomain',
                    'status_code' => 500,
                    'id' => null
                ];
            }

            return [
                'result' => 'failed',
                'message' => $errorMessage ,
                'status_code' => 500,
                'id' => null
            ];
        }
    }

    public function clickfunnels_GetListTags($api_key = '', $subdomain = '', $workspace_id = ''){
        $http = new \GuzzleHttp\Client;
        $url = "https://{$subdomain}.myclickfunnels.com/api/v2/workspaces/{$workspace_id}/contacts/tags";

        if (trim($api_key) === '' || trim($subdomain) === '' || trim($workspace_id) === '') {
            return response()->json(['result' => 'failed', 'message' => 'please check required fields', 'status_code' => 400, 'data' => []], 400);
        }

        try {
            $response = $http->request('GET', $url, [
                'headers' => [
                    'accept' => 'application/json',
                    'authorization' => "Bearer {$api_key}",
                ]
            ]);

            $body = $response->getBody();
            $resData = json_decode($body, true);

            return [
                'result' => 'success',
                'message' => 'Successfully get list tags',
                'status_code' => 200,
                'data' => $resData
            ];
        } catch (RequestException $e) {
            if ($e->hasResponse()) {
                $statusCode = $e->getResponse()->getStatusCode();

                if($statusCode == 400){
                    return[
                        'result' => 'failed',
                        'message' => 'invalid subdomain',
                        'status_code' => $statusCode,
                        'data' => []
                    ];
                }

                if($statusCode == 401){
                    return [
                        'result' => 'failed',
                        'message' => 'API key missing or invalid',
                        'status_code' => $statusCode,
                        'data' => []
                    ];
                }

                if($statusCode == 404){
                    return [
                        'result' => 'failed',
                        'message' => 'invalid workspace',
                        'status_code' => $statusCode,
                        'data' => []
                    ];
                }

                $errorMessage = $e->getResponse()->getBody()->getContents();
                $errorData = json_decode($errorMessage, true);
                $error = isset($errorData['error']) ? $errorData['error'] : 'Unknown error';

                return [
                    'result' => 'failed',
                    'message' => $error,
                    'status_code' => $statusCode,
                    'data' => []
                ];
            } else {
                return [
                    'result' => 'failed',
                    'message' => 'No response from server',
                    'status_code' => 500,
                    'data' => []
                ];
            }
        } catch (\Exception $e){
            $errorMessage = $e->getMessage();

            if(strpos($errorMessage, 'cURL error') === 0){
                return [
                    'result' => 'failed',
                    'message' => 'invalid subdomain',
                    'status_code' => 500,
                    'data' => []
                ];
            }

            return [
                'result' => 'failed',
                'message' => $errorMessage ,
                'status_code' => 500,
                'data' => []
            ];
        }
    }
    /** CLICK FUNNELS */
    
    /** GENERAL CAMPAIGN FUNCTION */
    public function stopAllCampaignAndBill($companyID="",$ipAddress="",$userID="",$archived = false) {

        $campaignlist = LeadspeekUser::select('leadspeek_users.id','leadspeek_users.leadspeek_api_id','leadspeek_users.leadspeek_type','leadspeek_users.active','leadspeek_users.disabled','leadspeek_users.active_user','users.company_id','leadspeek_users.leadspeek_organizationid','leadspeek_users.leadspeek_campaignsid','users.customer_payment_id','leadspeek_users.user_id',
                                            'leadspeek_users.paymentterm','leadspeek_users.lp_min_cost_month','leadspeek_users.report_sent_to','leadspeek_users.admin_notify_to','users.email','leadspeek_users.lp_limit_startdate','leadspeek_users.lp_enddate','leadspeek_users.lp_max_lead_month','leadspeek_users.cost_perlead','users.customer_card_id','leadspeek_users.start_billing_date',
                                            'companies.company_name','leadspeek_users.campaign_name','leadspeek_users.company_id as company_parent','users.active','users.company_root_id')
                        ->join('users','leadspeek_users.user_id','=','users.id')
                        ->join('companies','users.company_id','=','companies.id')
                        ->where('leadspeek_users.company_id','=',$companyID)
                        ->where('leadspeek_users.archived','=','F')
                        ->where(function($query){
                            $query->where(function($query){
                                $query->where('leadspeek_users.active','=','T')
                                    ->where('leadspeek_users.disabled','=','F')
                                    ->where('leadspeek_users.active_user','=','T');
                            })
                            ->orWhere(function($query){
                                $query->where('leadspeek_users.active','=','F')
                                    ->where('leadspeek_users.disabled','=','F')
                                    ->where('leadspeek_users.active_user','=','T');
                            })
                            ->orWhere(function($query){
                                $query->where('leadspeek_users.active','=','F')
                                    ->where('leadspeek_users.disabled','=','T')
                                    ->where('leadspeek_users.active_user','=','T');
                            });
                        })->get();

                foreach($campaignlist as $cl) {
                    $campaignStatus = 'stop';
                    $leadspeekID = $cl['leadspeek_api_id'];
                    
                    $organizationid = (isset($cl['leadspeek_organizationid']) && $cl['leadspeek_organizationid'] != '')?$cl['leadspeek_organizationid']:'';
                    $campaignsid = (isset($cl['leadspeek_campaignsid']) && $cl['leadspeek_campaignsid'] != '')?$cl['leadspeek_campaignsid']:'';
                    $start_billing_date = (isset($cl['start_billing_date']) && $cl['start_billing_date'] != '')?$cl['start_billing_date']:'';

                    /** LOG ACTION */
                    $loguser = $this->logUserAction($userID,'Campaign Stopped - Manual Billing OFF','Campaign Status : ' . $campaignStatus . ' | CampaignID :' . $leadspeekID,$ipAddress);
                    /** LOG ACTION */

                    /** ACTIVATE CAMPAIGN SIMPLIFI */
                    if ($organizationid != '' && $campaignsid != '') {
                        $camp = $this->startPause_campaign($organizationid,$campaignsid,$campaignStatus);
                    }
                    /** ACTIVATE CAMPAIGN SIMPLIFI */

                    $clientEmail = "";
                    $clientAdminNotify = "";
                    $custEmail = "";

                    /** CREATE INVOICE */

                    /** CHECK IF THERE END DATE ON WEEKLY OR MONTHLY PAYMENT TERM */
                    $clientPaymentTerm = $cl['paymentterm'];
                    $_company_id = $cl['company_id'];
                    $_user_id = $cl['user_id'];
                    $_leadspeek_api_id = $cl['leadspeek_api_id'];
                    $clientPaymentTerm = $cl['paymentterm'];
                    $minCostLeads = $cl['lp_min_cost_month'];
                    $_lp_user_id = $cl['id'];
                    $company_name = $cl['company_name'];
                    $clientEmail = explode(PHP_EOL, $cl['report_sent_to']);
                    $clientAdminNotify = explode(',',$cl['admin_notify_to']);
                    $custEmail = $cl['email'];    
                    
                    $clientMaxperTerm = $cl['lp_max_lead_month'];
                    $clientCostPerLead = $cl['cost_perlead'];
                    
                    $custStripeID = $cl['customer_payment_id'];
                    $custStripeCardID = $cl['customer_card_id'];

                    if ($clientPaymentTerm != 'One Time' && $start_billing_date != '') {
                        $EndDate = date('YmdHis',strtotime($start_billing_date));
                        $platformFee = 0;
                        
                        if (date('YmdHis') >= $EndDate) {
                            

                            /** UPDATE USER END DATE */
                            // $updateUser = User::find($userID);
                            // $updateUser->lp_enddate = null;
                            // $updateUser->lp_limit_startdate = null;
                            // $updateUser->save();
                            /** UPDATE USER END DATE */

                            $clientStartBilling = date('YmdHis',strtotime($start_billing_date));
                            $nextBillingDate = date('YmdHis');

                            /** HACKED ENDED CLIENT NO PLATFORM FEE */
                            $platformFee = 0;
                            /** HACKED ENDED CLIENT NO PLATFORM FEE */

                            /** CREATE INVOICE AND SENT IT */
                            $invoiceCreated = $this->createInvoice($_lp_user_id,$_company_id,$_user_id,$_leadspeek_api_id,$clientMaxperTerm,$clientCostPerLead,$platformFee,$clientPaymentTerm,$company_name,$clientEmail,$clientAdminNotify,$clientStartBilling,$nextBillingDate,$custStripeID,$custStripeCardID,$custEmail,$cl);
                            /** CREATE INVOICE AND SENT IT */
                        }       
                    }
                    /** CREATE INVOICE */     
                    
                    /** STOP CAMPAIGN STATUS */
                    $leads = LeadspeekUser::find($_lp_user_id);
                    $leads->active_user = 'F';
                    $leads->active = 'F';
                    $leads->disabled = 'T';
                    if ($archived) {
                        $leads->archived = 'T';
                    }
                    $leads->save();
                    /** STOP CAMPAIGN STATUS */
                }
    }
    /** GENERAL CAMPAIGN FUNCTION */

    /** TO SEND LEADS INTO SENDGRID ACCOUNT */
    public function sendContactToSendgrid($_sendgrid_api_key,$list,$email,$firstname,$lastname,$address1,$address2,$city,$state,$zipcode,$phoneno,$email2,$keyword="",$url=""){
        $http = new \GuzzleHttp\Client;
        $apiEndpoint = "https://api.sendgrid.com/v3/marketing/contacts";

        $_alternate_emails = array();
        if (trim($email2) != "") {
            $_alternate_emails[] = $email2;
        }else{
            $_alternate_emails = array();
        }


        $contactData = [
                'email' => $email,
                'alternate_emails' => $_alternate_emails,
                'first_name' => $firstname,
                'last_name' => $lastname,
                'address_line_1' => $address1,
                'address_line_2' => $address2,
                'city' => $city,
                'state_province_region' => $state,
                'postal_code' => $zipcode,
                'phone_number' => (string)$phoneno,
                'keyword' => $keyword,
                'url' => $url
        ];


        $payload = [
            'contacts' => [$contactData]
        ];

        if($list != ''){
            $payload['list_ids'] = $list;
        }

        $dataOptions = [
            'headers' => [
                'Authorization' => 'Bearer ' . $_sendgrid_api_key,
                'Content-Type' => 'application/json'
            ],
            'json' => $payload
        ];

        try {
            $response = $http->put($apiEndpoint,$dataOptions);
            if ($response->getStatusCode() == 202) {
                return [
                    'success' => true,
                    'error' => false,
                ];
            } else {
                return [
                    'success' => false,
                    'error' => 'Something went wrong, please try again later.',
                ];
            }
        }catch(\GuzzleHttp\Exception\BadResponseException $e) {
            Log::warning('sendContactToSendgrid (' . $email . ') error add msg :' . $e);
            $errorBody = (string) $e->getResponse()->getBody();
            $decodedError = json_decode($errorBody, true);

            return [
                'success' => false,
                'error' => $decodedError['errors'][0]['message'] == 'unauthorized' ? 'Invalid api key' : $decodedError['errors'][0]['message'],
            ];
        }
    }
    /** TO SEND LEADS INTO SENDGRID ACCOUNT */

    /* MAILBOXPOWER FUNCTIONS */
    public function mbp_createContact($apikey = "", $groupid = "", $companyname = "", $id ="", $leadspeek_api_id = "", $clickdate = "", $firstname = "", $lastname = "", $email = "", $email2 = "", $phone = "", $phone2 = "", $address1 = "", $address2 = "", $city = "", $state = "", $zipcode = "", $keyword = "", $errMsg = "")
    {
        if(!empty($apikey) && trim($apikey) != '')
        {
            $http = new \GuzzleHttp\Client;

            /* DATA REQUIRED */
            $firstname = (!empty($firstname) && trim($firstname) != '') ? $firstname : " "; // data ini required di mailboxpower. jika string kosong, null maka akan menyebabkan bad request. minimal 2 karakter spasi
            $lastname = (!empty($lastname) && trim($lastname) != '') ? $lastname : " "; // data ini required di mailboxpower. jika string kosong, null maka akan menyebabkan bad request. minimal 2 karakter spasi
            $address1 = (!empty($address1) && trim($address1) != '') ? $address1 : " "; // data ini required di mailboxpower. jika string kosong, null maka akan menyebabkan bad request. minimal 2 karakter spasi
            $city = (!empty($city) && trim($city) != '') ? $city : " "; // data ini required di mailboxpower. jika string kosong, null maka akan menyebabkan bad request. minimal 2 karakter spasi
            $state = (!empty($state) && trim($state) != '') ? $state : " "; // data ini required di mailboxpower. jika string kosong, null maka akan menyebabkan bad request. minimal 2 karakter spasi
            // Log::info(['firstname' => $firstname, 'lastname' => $lastname, 'address1' => $address1, 'city' => $city, 'state' => $state]);
            /* DATA REQUIRED */

            /* ATTRIBUTE FOR REQUEST */
            $url = "https://www.mailboxpower.com/api/v3/contacts";
            $headers = [
                'APIKEY' => $apikey
            ];
            $multipart = [
                [
                    'name' => 'groupId',
                    'contents' =>  $groupid // jika group id tidak ditemukan, maka akan ditetapkan secara global
                ],
                [
                    // required
                    'name' => 'firstname',
                    'contents' => $firstname, // jika tidak ingin diisi maka berikan karakter spasi minimal 2, kalau string kosong atau null bad request
                ],
                [
                    // required
                    'name' => 'lastname',
                    'contents' => $lastname, // jika tidak ingin diisi maka berikan karakter spasi minimal 2, kalau string kosong atau null bad request
                ],
                [
                    'name' => 'company',
                    'contents' => '',
                    // 'contents' => $companyname,
                ],
                [
                    'name' => 'email',
                    'contents' => $email,
                ],
                [
                    'name' => 'phone',
                    'contents' => $phone
                ],
                [
                    // required
                    'name' => 'street',
                    'contents' => $address1 // jika tidak ingin diisi maka berikan karakter spasi minimal 2, kalau string kosong atau null bad request
                ],
                [
                    
                    'name' => 'street2',
                    'contents' => $address2
                ],
                [
                    // required
                    'name' => 'city',
                    'contents' => $city // jika tidak ingin diisi maka berikan karakter spasi minimal 2, kalau string kosong atau null bad request
                ],
                [
                    // required
                    'name' => 'state',
                    'contents' => $state // jika tidak ingin diisi maka berikan karakter spasi minimal 2, kalau string kosong atau null bad request
                ],
                [
                    'name' => 'postalcode',
                    'contents' => $zipcode
                ],
                [
                    'name' => 'country',
                    'contents' => 'US'
                ],
                [
                    'name' => 'forcenew',
                    'contents' => 'true' // jika ada data yang sebelumnya di mailboxpower, maka data yang sebelumnya tidak di replace. dan data yang baru akan di create
                ],
                [
                    'name' => 'merge1',
                    'contents' => "[Contact ID] : $id",
                ],
                [
                    'name' => 'merge2',
                    'contents' => "[Campaign ID] : $leadspeek_api_id",
                ],
                [
                    'name' => 'merge3',
                    'contents' => "[Click Date] : $clickdate",
                ],
                [
                    'name' => 'merge4',
                    'contents' => "[Email 2] : $email2",
                ],
                [
                    'name' => 'merge5',
                    'contents' => "[Phone 2] : $phone2",
                ],
                [
                    'name' => 'merge6',
                    'contents' => "[Keyword] : $keyword",
                ],
            ];
            $options = [
                'headers' => $headers,
                'multipart' => $multipart
            ];
            /* ATTRIBUTE FOR REQUEST */

            try
            {
                // Log::info(['options' => $options]);
                $response = $http->post($url, $options);

                if ($response->getStatusCode() == 200) {
                    return [
                        'success' => true,
                        'error' => false,
                    ];
                } else {
                    return [
                        'success' => false,
                        'error' => 'Something went wrong, please try again later.',
                    ];
                }
            }
            catch (\GuzzleHttp\Exception\BadResponseException $e)
            {
                Log::warning("MBP Create Contact ErrMsg: {$e->getMessage()} $errMsg");
                $errorBody = (string) $e->getResponse()->getBody();
                $decodedError = json_decode($errorBody, true);

                return [
                    'success' => false,
                    'error' => $decodedError,
                ];
            }

        }
    }
    /* MAILBOXPOWER FUNCTIONS */

    /* KARTRA FUNCTIONS */
    protected function NewKartraLead($api_key, $api_password,$company_id,$campaign_id,$transaction_date,$first_name,$last_name,$email,$phone,$address,$city,$state,$zip,$website,$phone2,$email2,$address2,$keyword, $kartra_tags, $kartra_list){

        // $company_id = (isset($company_id))?$company_id:'';
        // $listAndTag = ListAndTag::select('list','tag')
        //                         //->where("company_id","=",$company_id)
        //                         ->where("campaign_id","=",$campaign_id)
        //                         ->where("kartra_is_active","=","1")
        //                         ->first();

            $tags= $kartra_tags;
            $taglist=[];
            if(!empty($tags)){
                foreach ($tags as $key => $tag) {array_push($taglist, ['cmd' => 'assign_tag', 'tag_name' =>$tag]);}
            }
            #
            $leadLists = $kartra_list;
            $leadList=[];
            if(!empty($leadLists)){
                foreach ($leadLists as $key => $listname) {array_push($leadList, ['cmd' => 'subscribe_lead_to_list', 'list_name' =>$listname]);}
                array_push($taglist, $leadList[0]);
                array_push($taglist,  ['cmd' => 'subscribe_lead_to_list', 'list_name' =>"mynewlist"]);
            }
            #
            array_push($taglist,  ['cmd' => 'create_lead']);

            $LeadData= [
                'transaction_date' => $transaction_date,
                'first_name' => $first_name,
                'last_name' => $last_name,
                'email' => $email,
                'phone' => $phone,
                'address' => $address,
                'city' => $city,
                'state' => $state,
                'zip' => $zip,
                'website'=>   $website,
            ];
            $custom_fields= [
                ['field_identifier' => 'keyword','field_value' => $keyword ,],
                ['field_identifier' => 'secondphone','field_value' => $phone2 ,],
                ['field_identifier' => 'secondemail','field_value' => $email2 ,],
                ['field_identifier' => 'secondaddress','field_value' => $address2 ,],
            ];

            $LeadData["custom_fields"]=$custom_fields;
            #
               $actions=   $taglist;
            return $list = $this->kartrLeadCreater($api_key, $api_password, $actions,$LeadData);
            #

    }

    protected function kartrLeadCreater($api_key, $api_password, $actions,$lead){
            // Initialize cURL session
            $ch = curl_init();
            // Set the API endpoint
            $api_endpoint = "https://app.kartra.com/api";
            // Set the POST data with your API credentials and action
            $post_data =[
                            'app_id' =>  config('services.kartra.kartraAppID'),
                            'api_key' => $api_key,
                            'api_password' => $api_password,
                            'lead' => $lead,
                            'actions' =>$actions
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
                Log::warning('cURL error: ' . curl_error($ch));
            } else {
                // Process the server output as needed
                Log::warning('kartaleadcreate curl : ' . $server_output);
                return  json_decode($server_output, true); // 'true' for associative arrays
            }
            // Close the cURL session
            curl_close($ch);
    }
    /* KARTRA FUNCTIONS */
}
