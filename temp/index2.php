<?php

public function getleadwebhook(Request $request) 
{
    info('getleadwebhook');
    $startTime = microtime(true);
    set_time_limit(0);
    $atdataEqsValue = "";

    /** CHECK IF URL ENCRYPT THEN DECRYPT URL PIXEL FIRE */
    if (isset($_GET['eqs']) && $_GET['eqs'] != '') {
        Log::info("GET IN EQS");
        $key = "164fa2202b52e7f67dd53767ab02c321"; // 128-bit key
        //$key = "d6c60c8e688e0afa7f49416897aadc1f";
        //$atdataEqsValue = "AeZFhZnm_ytYdj06wE5ikO4vPv302-SG9Osx_itiNbxF4gIY3CHdBpvpxd6Jaxvx%20%207i8-_fTb5Ib06zH-K2I1vEXiAhjcId0Gm-nF3olrG_HuLz799NvkhvTrMf4rYjW8%20%20rJmJDtsRTiTCWYfZfF6SDcHuTfwpOhubzIEAFNIeJRoOtRe-zev31MASpLRr2A_g%20%20wGPEJDvSZnokppAKPaWe2w";
        //$atdataEqsValue = "a0dBOP_RYgAncnExb4V-Y6ptSbaSaOq0iDoEsZY6oqJJfJHpTLf_i59couFGlbk5%20%20vK3ZJuSvVV57-no5V5r1C0VMBI0ebVc5_IhJcN9n678";
        $atdataEqsValue = $_GET['eqs'];

        // Initialize the AES cipher service
        $aesCipher = new AESCipher($key);

        // Standardize the base64 string
        $b64CipherText = $aesCipher->standardizeBase64($atdataEqsValue);

        // Decrypt the message
        $clearText = $aesCipher->decrypt($b64CipherText);

        /** DECRYPT URL PIXEL FIRE */
        
        // Parse the query string into an associative array
        parse_str($clearText, $output);

        $_GET = [];
        // Merge the parsed array into $_GET
        $_GET = array_merge($_GET, $output);
        
        $request->merge($_GET);
    }
    /** CHECK IF URL ENCRYPT THEN DECRYPT URL PIXEL FIRE */

    $params = $_GET;
    $md5email = (isset($request->md5email))?$request->md5email:'';
    $md5param = (isset($request->md5_email))?$request->md5_email:'';
    $label = (isset($request->label))?$request->label:'';
    Log::info("Original Label : " . $label);
    Log::info("MD5_EMAIL : " . $md5param);
    Log::info("GET :");
    Log::info($params);
    Log::info("Request : ");
    Log::info($request);

    $replaceURL = $request->url() . '?label=';
    $fullURL = $request->fullUrl();

    $resultProcessData = [
        'leadspeekReportID' => '',
        'executionTimeList' => []
    ];
    $leadspeekReportID = "";
    $executionTimeListGetDataMatch = [];
    $executionTimeListProcessDataMatch = [];
    $executionTimeList = [];
    //$label = urldecode(str_replace(array($replaceURL,"&script=true","&md5_email=",$md5param),array("","","",""),$fullURL));

    /** CONVERT ANY LABEL FORMAT INTO LEGACY $data ARRAY FOR CAMPAIGN LOCAL */
    /*
        format = [
            0 => leadspeek_api_id,
            1 => keyword,
            2 => pixelLeadRecordID,
            3 => customParams (optional),
        ]
    */
    // info('label before', ['label' => $label]);
    $data = $this->buildLegacyLabelArray($label);
    // info('date after', ['data' => $data]);
    /** CONVERT ANY LABEL FORMAT INTO LEGACY $data ARRAY FOR CAMPAIGN LOCAL */

    /** PARSE LABEL FOR GET ID, KEYWORD,etc */
    /** LABEL PATTERN campaignID|keyword **/
    // $data = explode("|",$label);
    $leadspeek_api_id = (isset($data[0]))?trim($data[0]):'';
    $keyword = (isset($data[1]))?trim($data[1]):'';
    
    $param = [];
    foreach ($params as $key => $value) {
        if ($key != "label" && $key != "md5_email") {
            //$param = $param . '&'. $key . '=' . $value;
            $param[$key] = $value;
        }
    }
    $finalparam = "";
    if (count($param) > 0) {
        $finalparam = '&' . http_build_query($param);
    }
    $keyword = $keyword . $finalparam;
    $keyword = preg_replace("/[^a-zA-Z0-9\-._~:\/?#\[\]@!$&'()*+,;= ]/", "",$keyword);

    $pixelLeadRecordID = isset($data[2])?$data[2]:null; // ini hanya ada digunakan di campaign local saja
    $customParams = isset($data[3])?$data[3]:null; // ini hanya ada digunakan di campaign local saja
    /** LABEL PATTERN campaignID|keyword **/
    /** PARSE LABEL FOR GET ID, KEYWORD,etc */

    if ($leadspeek_api_id != "") {
        /** CHECK AGAIN IF THE LEADSPEEK API ID ACTIVE */
        $campaign = LeadspeekUser::select('leadspeek_users.id','leadspeek_users.require_email','leadspeek_users.leadspeek_type','leadspeek_users.location_target','leadspeek_users.leadspeek_locator_zip','leadspeek_users.leadspeek_locator_state','leadspeek_users.leadspeek_locator_state_exclude','leadspeek_users.leadspeek_locator_state_simplifi','leadspeek_users.leadspeek_locator_city','leadspeek_users.leadspeek_locator_city_simplifi','leadspeek_users.national_targeting','leadspeek_users.company_id','users.company_id as clientcompany_id','users.company_root_id as clientcompany_root_id','leadspeek_users.paymentterm','leadspeek_users.advance_information','leadspeek_users.lp_limit_freq','leadspeek_users.stopcontinual','leadspeek_users.topupoptions','leadspeek_users.applyreidentificationall','leadspeek_users.user_id')
                        ->join('users','leadspeek_users.user_id','=','users.id')
                        ->where('leadspeek_users.leadspeek_api_id','=',$leadspeek_api_id)
                        ->where('leadspeek_users.active','=','T')
                        ->where('leadspeek_users.disabled','=','F')
                        ->where('leadspeek_users.active_user','=','T')
                        ->first();
        /** CHECK AGAIN IF THE LEADSPEEK API ID ACTIVE */
        //if (count($campaign) > 0) {
        if ($campaign) {
            /* VALIDATION LEADS ZERO, IN LOCAL, PREPAID, CONTINUAL, LIMIT MONTH */
            $leadspeek_type = isset($campaign->leadspeek_type)?$campaign->leadspeek_type:'';
            $paymentterm = isset($campaign->paymentterm)?$campaign->paymentterm:'';
            $topupoptions = isset($campaign->topupoptions)?$campaign->topupoptions:'';
            $lp_limit_freq = isset($campaign->lp_limit_freq)?$campaign->lp_limit_freq:'';
            $stopcontinual = isset($campaign->stopcontinual)?$campaign->stopcontinual:'';
            
            // info([
            //     'action' => 'VALIDATION LEADS ZERO, IN LOCAL, PREPAID, CONTINUAL, LIMIT MONTH',
            //     'leadspeek_type' => $leadspeek_type,
            //     'paymentterm' => $paymentterm,
            //     'topupoptions' => $topupoptions,
            //     'lp_limit_freq' => $lp_limit_freq,
            //     'stopcontinual' => $stopcontinual,
            // ]);
            
            if($leadspeek_type == 'local' && $paymentterm == 'Prepaid' && $topupoptions == 'continual' && $stopcontinual == 'F' && $lp_limit_freq == 'month')
            {
                $idTopups = Topup::select(['id'])
                                    ->where('leadspeek_api_id','=', $leadspeek_api_id)
                                    ->where('topup_status','<>','done')
                                    ->get();

                $remainingBalanceLeads = Topup::where('leadspeek_api_id','=', $leadspeek_api_id)
                                                ->where('topup_status','<>','done')
                                                ->sum('total_leads') - DB::table('leadspeek_reports')
                                                ->where('leadspeek_api_id','=',$leadspeek_api_id)
                                                ->whereIn('topup_id', $idTopups)
                                                ->count();

                // info('', [
                //     'action' => 'check remainingbalance',
                //     'idTopups' => $idTopups,
                //     'totalLeadsTopup' => Topup::where('leadspeek_api_id','=', $leadspeek_api_id)->where('topup_status','<>','done')->sum('total_leads'),
                //     'totalLeadsReport' => DB::table('leadspeek_reports')->where('leadspeek_api_id','=',$leadspeek_api_id)->whereIn('topup_id', $idTopups)->count(),
                //     'remainingBalanceLeads' => $remainingBalanceLeads,
                // ]);

                if($remainingBalanceLeads <= 0)
                {
                    // karena remaining leads 0 dan status nya running, maka berubah menjadi paused on run
                    $campaign->active = 'F';
                    $campaign->disabled = 'F';
                    $campaign->active_user = 'T';
                    $campaign->save();
                    // karena remaining leads 0 dan status nya running, maka berubah menjadi paused on run

                    // info("PREPAID CONTINUAL LIMIT MONTH IN WILL ALREADY FULL, REMAININGLEADS : {$remainingBalanceLeads}");
                    return;
                }
            }
            /* VALIDATION LEADS ZERO, IN LOCAL, PREPAID, CONTINUAL, LIMIT MONTH */

            /* VARIABLES */
            $loctarget = $campaign->location_target;
            $leadspeektype = $campaign->leadspeek_type;
            $nationaltargeting = $campaign->national_targeting;
            $loczip = "";
            $locstate = "";
            $locstateexclude = "";
            $locstatesifi = "";
            $loccity = "";
            $loccitysifi = "";
            $compleadID = (isset($campaign->company_id) && trim($campaign->company_id) != "")?trim($campaign->company_id):"";
            $clientCompanyID = (isset($campaign->clientcompany_id) && trim($campaign->clientcompany_id) != "")?trim($campaign->clientcompany_id):"";
            $clientCompanyRootId = (isset($campaign->clientcompany_root_id) && trim($campaign->clientcompany_root_id) != "")?trim($campaign->clientcompany_root_id):"";

            if ($leadspeektype == "locator" || $leadspeektype == 'enhance' || $leadspeektype == 'b2b') {
                $loctarget = "Lock";
                $loczip = (isset($campaign->leadspeek_locator_zip) && trim($campaign->leadspeek_locator_zip) != "")?trim($campaign->leadspeek_locator_zip):"";
                $locstate = (isset($campaign->leadspeek_locator_state) && trim($campaign->leadspeek_locator_state) != "")?trim($campaign->leadspeek_locator_state):"";
                $locstatesifi = (isset($campaign->leadspeek_locator_state_simplifi) && trim($campaign->leadspeek_locator_state_simplifi) != "")?trim($campaign->leadspeek_locator_state_simplifi):"";
                //$loccity =  (isset($campaign[0]['leadspeek_locator_city']) && trim($campaign[0]['leadspeek_locator_city']) != "")?trim($campaign[0]['leadspeek_locator_city']):"";
                // $loccity = "";
                if($leadspeektype == 'enhance' || $leadspeektype == 'b2b') {
                    $loccity =  (isset($campaign->leadspeek_locator_city) && trim($campaign->leadspeek_locator_city) != "")?trim($campaign->leadspeek_locator_city):"";
                }
                //$loccitysifi =  (isset($campaign[0]['leadspeek_locator_city_simplifi']) && trim($campaign[0]['leadspeek_locator_city_simplifi']) != "")?trim($campaign[0]['leadspeek_locator_city_simplifi']):"";
                $loccitysifi = "";
            }else{
                $loctarget = "Focus";
                $locstate = (isset($campaign->leadspeek_locator_state) && trim($campaign->leadspeek_locator_state) != "")?trim($campaign->leadspeek_locator_state):"";
                $locstateexclude = (isset($campaign->leadspeek_locator_state_exclude) && trim($campaign->leadspeek_locator_state_exclude) != "")?trim($campaign->leadspeek_locator_state_exclude):"";
            }
            /* VARIABLES */

            /* LOCK EMAIL PROCESS */
            $applyreidentificationall = (isset($campaign->applyreidentificationall) && $campaign->applyreidentificationall == 'T');
            $userId = (isset($campaign->user_id))?$campaign->user_id:null;
            $developemode = env('DEVELOPEMODE', false) ? 'sandbox' : 'production';
            $leadEmailLockKey = "{$developemode}:lock:lead_client:{$userId}:{$md5param}";
            if ($applyreidentificationall)
            {
                while (!$this->acquireLock($leadEmailLockKey))
                {
                    Log::info("Lead Email Lock Key : {$leadEmailLockKey} , Campaign ID : #{$leadspeek_api_id}");
                    sleep(1); // Wait before trying again
                }
            }
            /* LOCK EMAIL PROCESS */

            try
            {
                /* GET DATA */
                $resultGetData = $this->getDataMatch($md5param,$leadspeek_api_id,$data,$keyword,$loctarget,$loczip,$locstate,$locstateexclude,$locstatesifi,$loccity,$loccitysifi,$nationaltargeting,$leadspeektype,$compleadID,$clientCompanyID,$clientCompanyRootId);
                $dataMatch = isset($resultGetData['matches'])?$resultGetData['matches']:[];
                $executionTimeListGetDataMatch = isset($resultGetData['executionTimeList'])?$resultGetData['executionTimeList']:[];
                info('dataMatch', ['dataMatch' => $dataMatch]);
                /* GET DATA */
                
                /* PROCESS DATA */
                if (is_array($dataMatch) || is_object($dataMatch)) {
                    if (count($dataMatch) > 0) {
                        Log::info("Start serve leads campaign ID #" . $leadspeek_api_id);
                        if (($campaign->require_email == 'T' && trim($dataMatch[0]['Email']) != '') || ($campaign->require_email == 'F')) {
                            if (count($dataMatch) > 0) {
                                // /** REPORT ANALYTIC */
                                //     $this->UpsertReportAnalytics($leadspeek_api_id,$leadspeektype,'serveclient');
                                // /** REPORT ANALYTIC */
                                $filterEmail = true;
                                if(trim($dataMatch[0]['Email']) != '') {
                                    $filterEmail = $this->filterEmailInLeadspeekReport($leadspeek_api_id, $dataMatch[0]['Email'], $dataMatch[0]['PersonID'], $clientCompanyID, $leadspeektype, $md5param);
                                }
                                info('', ['filterEmail' => $filterEmail]);

                                if($filterEmail) {
                                    $dataMatch[0]['CustomParams'] = ($leadspeektype == 'local') ? $customParams : "";
                                    $matches = json_decode(json_encode($dataMatch), false);
                                    $resultProcessData = $this->processDataMatch($leadspeek_api_id,$matches,$data,$campaign->paymentterm,$leadspeektype,$md5param);
                                }
                            }
                        }
                    }else{
                        Log::info("Not serve leads 1 campaign ID #" . $leadspeek_api_id);
                        /** REPORT ANALYTIC NOT SERVE AND BIGBDMREMAININGLEADS IF LEADSPEEK_TYPE ENHANCE */
                            $this->UpsertReportAnalytics($leadspeek_api_id,$leadspeektype,'notserve');
                            if($leadspeektype == 'enhance' || $leadspeektype == 'b2b') {
                                $bigbdmremainingleads = BigDBMLeads::where('leadspeek_api_id', $leadspeek_api_id)
                                                                    ->whereDate('created_at', date('Y-m-d'))
                                                                    ->count() - 1;
                                $this->UpsertReportAnalytics($leadspeek_api_id,$leadspeektype,'bigbdmremainingleads',$bigbdmremainingleads);
                            }
                        /** REPORT ANALYTIC NOT SERVE AND BIGBDMREMAININGLEADS IF LEADSPEEK_TYPE ENHANCE */
                    }
                }else{
                    Log::info("Not serve leads 2 campaign ID #" . $leadspeek_api_id);
                    /** REPORT ANALYTIC NOT SERVE AND BIGBDMREMAININGLEADS IF LEADSPEEK_TYPE ENHANCE */
                        $this->UpsertReportAnalytics($leadspeek_api_id,$leadspeektype,'notserve');
                        if($leadspeektype == 'enhance' || $leadspeektype == 'b2b') {
                            $bigbdmremainingleads = BigDBMLeads::where('leadspeek_api_id', $leadspeek_api_id)
                                                                ->whereDate('created_at', date('Y-m-d'))
                                                                ->count() - 1;
                            $this->UpsertReportAnalytics($leadspeek_api_id,$leadspeektype,'bigbdmremainingleads',$bigbdmremainingleads);
                        }
                    /** REPORT ANALYTIC NOT SERVE AND BIGBDMREMAININGLEADS IF LEADSPEEK_TYPE ENHANCE */
                }
                /* PROCESS DATA */
            }
            catch(\Throwable $th)
            {
                Log::error("Error Process Data In Getleadwebhook Campaign ID: #{$leadspeek_api_id} Email MD5: {$md5param}", [
                    'error' => $th->getMessage(),
                    'trace' => $th->getTraceAsString(),
                ]);
            }
            finally
            {
                /* RELEASE LOCK EMAIL PROCESS */
                if ($applyreidentificationall)
                {
                    $this->releaseLock($leadEmailLockKey);
                }
                /* RELEASE LOCK EMAIL PROCESS */
            }

            /** REPORT ANALYTIC */
            if($leadspeektype == 'local')
            {
                $this->UpsertReportAnalytics($leadspeek_api_id,$leadspeektype,'pixelfire');    
                // insert pixel lead record
                // info('insert pixel lead record', ['pixelLeadRecordID' => $pixelLeadRecordID, 'label' => $label]);
                $this->UpdateFeedbackPixelLeadRecord($pixelLeadRecordID, $leadspeek_api_id, $md5param, $label);
            }
            /** REPORT ANALYTIC */

            /** COUNT WEBHOOK BEEN FIRE FOR SPECIFIC LEADSPEEK ID */
                $updatewebhook = LeadspeekUser::find($campaign->id);
                $updatewebhook->webhookfire =  $updatewebhook->webhookfire + 1;
                $updatewebhook->save();
            /** COUNT WEBHOOK BEEN FIRE FOR SPECIFIC LEADSPEEK ID */
        }
    }

    $details = [
        'params' => $keyword . '==>' . $leadspeek_api_id,
        'paramsUrl'  => json_encode($params) . '<br>Label : ' . $label . '<br>MD5 Email : ' . $md5param . '<br>Full URL : ' . $fullURL,
    ];
    $attachement = array();

    $from = [
        'address' => 'newleads@leadspeek.com',
        'name' => 'webhook',
        'replyto' => 'newleads@leadspeek.com',
    ];

    //$this->send_email(array('serverlogs@sitesettingsapi.com'),'SANBOX-GET WEBHOOK FROM TOWER DATA',$details,$attachement,'emails.webhookleadnotification',$from,'');

    /* SAVE TO EXECUTION TIME PROCESS TO LEADSPEEK_REPORT IF DATA MATCH */
    $leadspeekReportID = isset($resultProcessData['leadspeekReportID'])?$resultProcessData['leadspeekReportID']:"";
    $executionTimeListProcessDataMatch = isset($resultProcessData['executionTimeList'])?$resultProcessData['executionTimeList']:[];
    if(!empty($leadspeekReportID) && trim($leadspeekReportID) != '') {
        $executionTimeList = array_merge($executionTimeList, $executionTimeListGetDataMatch);
        $executionTimeList = array_merge($executionTimeList, $executionTimeListProcessDataMatch);

        $endTime = microtime(true);
        
        // convert epochtime to date format ('Y-m-d H:i:s')
        $startDate = Carbon::createFromTimestamp($startTime)->setTimezone('America/Chicago')->format('Y-m-d H:i:s');
        $endDate = Carbon::createFromTimestamp($endTime)->setTimezone('America/Chicago')->format('Y-m-d H:i:s');
        // convert epochtime to date format ('Y-m-d H:i:s') 
        
        $executionTime = $endTime - $startTime;
        $executionTime = number_format($executionTime,2,'.','');

        $executionTimeList['all_process'] = [
            'start_execution_time' => $startDate,
            'end_execution_time' => $endDate,
            'total_execution_time' => $executionTime
        ];

        LeadspeekReport::where('id', $leadspeekReportID)
                        ->update([
                        'start_execution_time' => $startDate,
                        'end_execution_time' => $endDate,
                        'total_execution_time' => $executionTime,
                        'list_execution_time' => json_encode($executionTimeList)
                        ]);
    }
    /* SAVE TO EXECUTION TIME PROCESS TO LEADSPEEK_REPORT IF DATA MATCH */
}