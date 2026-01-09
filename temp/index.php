<?php

public function getleadwebhook(Request $request) 
{
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
        $campaign = LeadspeekUser::select('leadspeek_users.id','leadspeek_users.require_email','leadspeek_users.leadspeek_type','leadspeek_users.location_target','leadspeek_users.leadspeek_locator_zip','leadspeek_users.leadspeek_locator_state','leadspeek_users.leadspeek_locator_state_exclude','leadspeek_users.leadspeek_locator_state_simplifi','leadspeek_users.leadspeek_locator_city','leadspeek_users.leadspeek_locator_city_simplifi','leadspeek_users.national_targeting','leadspeek_users.company_id','users.company_id as clientcompany_id','users.company_root_id as clientcompany_root_id','leadspeek_users.paymentterm','leadspeek_users.advance_information','leadspeek_users.lp_limit_freq','leadspeek_users.stopcontinual','leadspeek_users.topupoptions')
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
            
            // $resultGetData = $this->getDataMatch($md5param,$leadspeek_api_id,$data,$keyword,$loctarget,$loczip,$locstate,$locstateexclude,$locstatesifi,$loccity,$loccitysifi,$nationaltargeting,$leadspeektype,$compleadID,$clientCompanyID,$clientCompanyRootId);
            // $dataMatch = isset($resultGetData['matches'])?$resultGetData['matches']:[];
            // $executionTimeListGetDataMatch = isset($resultGetData['executionTimeList'])?$resultGetData['executionTimeList']:[];

            $idReport = $this->generateReportUniqueNumber();
            $dataMatch = [
                [
                    "ID" => $idReport,
                    "Email" => "ptrhrt57@gmail.com",
                    "Email2" => "",
                    "OriginalMD5" => "8bc64c9c289d18a05dd9652422208f05",
                    "IP" => "",
                    "Source" => "",
                    "OptInDate" => "2026-01-06 22:00:00",
                    "ClickDate" => "2026-01-06 22:00:00",
                    "Referer" => "",
                    "Phone" => "5868567638",
                    "Phone2" => "5868557639",
                    "FirstName" => "Krista",
                    "LastName" => "Hart",
                    "Address1" => "29467 Tropea Dr",
                    "Address2" => "",
                    "City" => "Warren",
                    "State" => "MI",
                    "Zipcode" => "48092",
                    "PersonID" => 7795,
                    "Keyword" => "fashion",
                    "Description" => "EmailExistonDB|LastEntryLessSixMonthPermissionYes|dataExistOnDB|12149",
                    "LeadFrom" => "person",
                    "GenderAux" => "Female",
                    "Generation" => "2. Generation X (1961-1981)",
                    "MaritalStatus" => "Married",
                    "IncomeHousehold" => "IncomeHousehold",
                    "IncomeMidptsHousehold" => "300000",
                    "NetWorthHousehold" => "F. $25,000 - $49,999",
                    "NetWorthMidptHousehold" => "37500",
                    "DiscretionaryIncome" => "H. $75,000-$99,999",
                    "CreditMidpts" => "625",
                    "CreditRange" => "CreditRange",
                    "OccupationCategory" => "Professional",
                    "OccupationDetail" => "Nurse (Registered)",
                    "OccupationType" => "White Collar",
                    "Voter" => "Voter",
                    "Urbanicity" => "4. Urban",
                    "Phone3" => "Phone3",
                    "TaxBillInformation" => "29467 Tropea Dr, Warren, Macomb County, MI, 48092-3322",
                    "NumAdultsHousehold" => "3",
                    "NumChildrenHousehold" => "4",
                    "NumPersonsHousehold" => "2",
                    "ChildAged03Household" => "",
                    "ChildAged46Household" => "1",
                    "ChildAged79Household" => "1",
                    "ChildAged1012Household" => "",
                    "ChildAged1318Household" => "",
                    "ChildrenHousehold" => "1",
                    "MagazineSubscriber" => "MagazineSubscriber",
                    "CharityInterest" => "1",
                    "LikelyCharitableDonor" => "1",
                    "DwellingType" => "Single Family",
                    "HomeOwner" => "Home Owner",
                    "HomeOwnerOrdinal" => "4",
                    "LengthOfResidence" => "7",
                    "HomePrice" => "170000",
                    "HomeValue" => "262200",
                    "MedianHomeValue" => "90000",
                    "LivingSqft" => "1688",
                    "YrBuiltOrig" => "1965",
                    "YrBuiltRange" => "8",
                    "LotNumber" => "134",
                    "LegalDescription" => "CARLETON ESTATES SUB. LOT 134 L.53 P.7-9",
                    "LandSqft" => "9104",
                    "GarageSqft" => "428",
                    "Subdivision" => "CARLETON ESTATES SUB",
                    "ZoningCode" => "R-1-C",
                    "Cooking" => "111",
                    "Gardening" => "111",
                    "Music" => "1",
                    "Diy" => "1",
                    "Books" => "1",
                    "TravelVacation" => "TravelVacation",
                    "HealthBeautyProducts" => "HealthBeautyProducts",
                    "PetOwner" => "",
                    "Photography" => "1",
                    "Fitness" => "1",
                    "Epicurean" => "Epicurean",
                    "Cbsa" => "19820",
                    "CensusBlock" => "2007",
                    "CensusBlockGroup" => "CensusBlockGroup",
                    "CensusTract" => "260900"
                ]
            ];
            $executionTimeListGetDataMatch = [];

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


private function getDataMatch($md5param,$leadspeek_api_id,$data,$keyword = '',$loctarget = 'Focus',$loczip = "",$locstate = "",$locstateexclude = "",$locstatesifi = "",$loccity = "",$loccitysifi = "",$nationaltargeting = "F",$leadspeektype="local",$compleadID = "",$clientCompanyID = "",$clientCompanyRootId = "") 
{
    $executionTimeList = [];
    
    date_default_timezone_set('America/Chicago');

    // /* LOCK PROCESS GETDATAMATCH */
    // Log::info('START GETDATAMATCH PROCESS #' . $leadspeek_api_id);
    // $initLock = 'initGetDataMatch' . $leadspeek_api_id;
    // while(!$this->acquireLock($initLock)) {
    //     Log::info("Initial Get Data Match Processing. Waiting to acquire lock. CAMPAIGN ID #" . $leadspeek_api_id);
    //     sleep(1); // Wait before trying again
    // }
    // /* LOCK PROCESS GETDATAMATCH */

    try {
        $reidentification = "";
        $matches = array();
        $salt = substr(hash('sha256', env('APP_KEY')), 0, 16);
        $dataflow = "";

        /** RECORD ANY INCOMING MD5 PARAM THAT WE GET FROM TOWERDATA WEBHOOK FIRED */
        $fr = FailedRecord::create([
            'email_encrypt' => $md5param,
            'leadspeek_api_id' => $leadspeek_api_id,
            'description' => 'md5email fired|' . $keyword,
        ]);
        $failedRecordID = $fr->id;
        /** RECORD ANY INCOMING MD5 PARAM THAT WE GET FROM TOWERDATA WEBHOOK FIRED */

        /** FILTER IF THERE IS KEYWORD TV AND PUT IT ON OPTOUT LIST */
        $blockedkeyword = array("tv");
        $word = strtolower(trim($keyword));

        if (in_array($word,$blockedkeyword)) {
            $createoptout = OptoutList::create([
                'email' => '',
                'emailmd5' => $md5param,
                'blockedcategory' => 'keyword',
                'description' => 'blocked because keyword : ' . $word,
            ]);
        }
        /** FILTER IF THERE IS KEYWORD TV AND PUT IT ON OPTOUT LIST */

        /** FILTER IF SIMPLIFI GIVE NOT FIT KEYWORD */
        if ($word != "") {
            if (str_contains($word, '_audience')) {
                $keyword = "";
            }
        }
        /** FILTER IF SIMPLIFI GIVE NOT FIT KEYWORD */

        /** CHECK AGAINST EMM OPT OUT LIST */
        $notAgainstOptout = true;

        Log::info("Start Check OptOut 1 CampaignID : #" . $leadspeek_api_id);

        //$optoutlist = OptoutList::select('emailmd5')
        // $optoutlist = OptoutList::where('emailmd5','=',$md5param)
        //                         ->where('company_root_id','=',$clientCompanyRootId)
        //                         //->get();
        //                         ->exists();
        $_cacheKey = "opt1_" . $md5param . '_' . $clientCompanyRootId;
        $optoutlist = $this->cacheGetResult($_cacheKey);
        if(is_null($optoutlist)) {
            $optoutlist = OptoutList::where('emailmd5','=',$md5param)
                                    ->where('company_root_id','=',$clientCompanyRootId)
                                    ->exists();
            if($optoutlist) {
                $optoutlist = $this->cacheQueryResult($_cacheKey,86400,function () use ($optoutlist) {
                    return $optoutlist;
                });
            } else {
                if($this->cacheHasResult($_cacheKey)) {
                    $this->cacheForget($_cacheKey);
                }
            }
        }

        //if (count($optoutlist) > 0) {
        if ($optoutlist) {
            $notAgainstOptout = false;
            /** UPDATE FOR DESCRIPTION ON FAILED RECORD */
                $frupdate = FailedRecord::find($failedRecordID);
                $frupdate->description = $frupdate->description . '|AgainstEMMOptList';
                $frupdate->save();
            /** UPDATE FOR DESCRIPTION ON FAILED RECORD */

            // /* RELEASE LOCK PROCESS FOR GETDATAMATCH */
            // Log::info("RELEASE GETDATAMATCH LOCK OptOut 1 CampaignID #" . $leadspeek_api_id);
            // $this->releaseLock($initLock);
            // /* RELEASE LOCK PROCESS FOR GETDATAMATCH */
            Log::info("Failed Serve Check OptOut 1 CampaignID : #" . $leadspeek_api_id);

            /* WRITE UPSER FAILED LEAD RECORD */
            $this->UpsertFailedLeadRecord([
                'function' => 'getDataMatch',
                'type' => 'blocked',
                'blocked_type' => 'optoutlist',
                'description' => 'blocked in table optoutlist',
                'leadspeek_api_id' => $leadspeek_api_id,
                'email_encrypt' => $md5param,
                'leadspeek_type' => $leadspeektype,
            ]);
            /* WRITE UPSER FAILED LEAD RECORD */
            
            return array();
            exit;die();
        }
        /** CHECK AGAINST EMM OPT OUT LIST */

        /** CHECK AGAINST CAMPAIGN OR ACCOUNT SUPPRESSION LIST */
        if ($notAgainstOptout) {
            $notOnSuppressionList = true;

            Log::info("Start Check SupressionList 1 CampaignID : #" . $leadspeek_api_id);

            $_cacheKey = 'suplist_' . $md5param . '_' . $compleadID . '_' . $clientCompanyID . '_' . $leadspeek_api_id; 
            $supressionListExists = $this->cacheGetResult($_cacheKey);
            if(is_null($supressionListExists)) {
                $supressionListExists = SuppressionList::select('suppression_type') 
                                                    ->where(function ($query) use ($md5param, $compleadID) {
                                                        $query->where('emailmd5', $md5param)
                                                              ->where('company_id', $compleadID)
                                                              ->where('suppression_type', 'account');
                                                    })
                                                    ->orWhere(function ($query) use ($md5param, $clientCompanyID) {
                                                        $query->where('emailmd5', $md5param)
                                                              ->where('company_id', $clientCompanyID)
                                                              ->where('suppression_type', 'client');
                                                    })
                                                    ->orWhere(function ($query) use ($md5param, $leadspeek_api_id) {
                                                        $query->where('emailmd5', $md5param)
                                                              ->where('leadspeek_api_id', $leadspeek_api_id)
                                                              ->where('suppression_type', 'campaign');
                                                    })
                                                    ->first();
                if(!empty($supressionListExists)) {
                    $supressionListExists = $this->cacheQueryResult($_cacheKey,86400,function() use($supressionListExists) {
                        return $supressionListExists;
                    });
                } else {
                    if($this->cacheHasResult($_cacheKey)) {
                        $this->cacheForget($_cacheKey);
                    }
                }
            }

            if(!empty($supressionListExists)) {
                $supressionType = isset($supressionListExists->suppression_type)?$supressionListExists->suppression_type:'';
                $notOnSuppressionList = false;

                /** UPDATE FOR DESCRIPTION ON FAILED RECORD */
                $frupdate = FailedRecord::find($failedRecordID);
                $frupdate->description = $frupdate->description . '|AgainstSupressionList';
                $frupdate->save();
                /** UPDATE FOR DESCRIPTION ON FAILED RECORD */

                // /* RELEASE LOCK PROCESS FOR GETDATAMATCH */
                // Log::info("RELEASE GETDATAMATCH LOCK SupressionList 1 CampaignID #" . $leadspeek_api_id);
                // $this->releaseLock($initLock);
                // /* RELEASE LOCK PROCESS FOR GETDATAMATCH */
                Log::info("Failed Serve SupressionList CampaignID : #" . $leadspeek_api_id);

                /* WRITE UPSER FAILED LEAD RECORD */
                $this->UpsertFailedLeadRecord([
                    'function' => 'getDataMatch',
                    'type' => 'blocked',
                    'blocked_type' => 'supressionlist',
                    'description' => 'blocked in table supression_lists where supression_type ' . $supressionType,
                    'leadspeek_api_id' => $leadspeek_api_id,
                    'email_encrypt' => $md5param,
                    'leadspeek_type' => $leadspeektype,
                ]);
                /* WRITE UPSER FAILED LEAD RECORD */

                return array();
                exit;die();
            }

            /** CHECK ACCOUNT / AGENCY LEVEL */
            //$suppressionlistAccount = SuppressionList::select('emailmd5')
            // $suppressionlistAccount = SuppressionList::where('emailmd5','=',$md5param)
            //                             ->where('company_id','=',$compleadID)
            //                             ->where('suppression_type','=','account')
            //                             //->get();
            //                             ->exists();

            // $_cacheKey = "suplist1_" . $md5param . '_' . $compleadID;
            // $suppressionlistAccount = $this->cacheGetResult($_cacheKey);
            // if(is_null($suppressionlistAccount)) {
            //     $suppressionlistAccount = SuppressionList::where('emailmd5','=',$md5param)
            //                                              ->where('company_id','=',$compleadID)
            //                                              ->where('suppression_type','=','account')
            //                                              ->exists();
            //     if($suppressionlistAccount) {
            //         $suppressionlistAccount = $this->cacheQueryResult($_cacheKey,86400,function () use ($suppressionlistAccount) {
            //             return $suppressionlistAccount;
            //         });
            //     } else {
            //         if($this->cacheHasResult($_cacheKey)) {
            //             $this->cacheForget($_cacheKey);
            //         }
            //     }
            // } 
            
            // //if (count($suppressionlistAccount) > 0) {
            // if ($suppressionlistAccount) {
            //     $notOnSuppressionList = false;
            //     /** UPDATE FOR DESCRIPTION ON FAILED RECORD */
            //         $frupdate = FailedRecord::find($failedRecordID);
            //         $frupdate->description = $frupdate->description . '|AgainstAccountOptList';
            //         $frupdate->save();
            //     /** UPDATE FOR DESCRIPTION ON FAILED RECORD */

            //     // /* RELEASE LOCK PROCESS FOR GETDATAMATCH */
            //     // Log::info("RELEASE GETDATAMATCH LOCK SupressionList 1 CampaignID #" . $leadspeek_api_id);
            //     // $this->releaseLock($initLock);
            //     // /* RELEASE LOCK PROCESS FOR GETDATAMATCH */
            //     Log::info("Failed Serve SupressionList 1 CampaignID : #" . $leadspeek_api_id);

            //     /* WRITE UPSER FAILED LEAD RECORD */
            //     $this->UpsertFailedLeadRecord([
            //         'function' => 'getDataMatch',
            //         'type' => 'blocked',
            //         'blocked_type' => 'supressionlist',
            //         'description' => 'blocked in table supression_lists where supression_type account',
            //         'leadspeek_api_id' => $leadspeek_api_id,
            //         'email_encrypt' => $md5param,
            //         'leadspeek_type' => $leadspeektype,
            //     ]);
            //     /* WRITE UPSER FAILED LEAD RECORD */

            //     return array();
            //     exit;die();
            // }

            /** CHECK CLIENT LEVEL */

            // Log::info("Start Check SupressionList 2 CampaignID : #" . $leadspeek_api_id);

            //$suppressionlistAccount = SuppressionList::select('emailmd5')
            // $suppressionlistAccount = SuppressionList::where('emailmd5','=',$md5param)
            //                             ->where('company_id','=',$clientCompanyID)
            //                             ->where('suppression_type','=','client')
            //                             //->get();
            //                             ->exists();
            
            // $_cacheKey = "suplist2_" . $md5param . '_' . $clientCompanyID; --
            // $suppressionlistClient = $this->cacheGetResult($_cacheKey);
            // if(is_null($suppressionlistClient)) {
            //     $suppressionlistClient = SuppressionList::where('emailmd5','=',$md5param)
            //                                             ->where('company_id','=',$clientCompanyID)
            //                                             ->where('suppression_type','=','client')
            //                                             ->exists();
            //     if($suppressionlistClient) {
            //         $suppressionlistAccount = $this->cacheQueryResult($_cacheKey,86400,function () use ($suppressionlistClient) {
            //             return $suppressionlistClient;
            //         });
            //     } else {
            //         if($this->cacheHasResult($_cacheKey)) {
            //             $this->cacheForget($_cacheKey);
            //         }
            //     }
            // }--
            //if (count($suppressionlistAccount) > 0) {
            // if ($suppressionlistClient) {--
            //     $notOnSuppressionList = false;
            //     /** UPDATE FOR DESCRIPTION ON FAILED RECORD */
            //         $frupdate = FailedRecord::find($failedRecordID);
            //         $frupdate->description = $frupdate->description . '|AgainstClientOptList';
            //         $frupdate->save();
            //     /** UPDATE FOR DESCRIPTION ON FAILED RECORD */

            //     // /* RELEASE LOCK PROCESS FOR GETDATAMATCH */
            //     // Log::info("RELEASE GETDATAMATCH LOCK SupressionList 2 CampaignID #" . $leadspeek_api_id);
            //     // $this->releaseLock($initLock);
            //     // /* RELEASE LOCK PROCESS FOR GETDATAMATCH */
            //     Log::info("Failed Serve SupressionList 2 CampaignID : #" . $leadspeek_api_id);

            //     /* WRITE UPSER FAILED LEAD RECORD */
            //     $this->UpsertFailedLeadRecord([
            //         'function' => 'getDataMatch',
            //         'type' => 'blocked',
            //         'blocked_type' => 'supressionlist',
            //         'description' => 'blocked in table supression_lists where supression_type client',
            //         'leadspeek_api_id' => $leadspeek_api_id,
            //         'email_encrypt' => $md5param,
            //         'leadspeek_type' => $leadspeektype,
            //     ]);
            //     /* WRITE UPSER FAILED LEAD RECORD */

            //     return array();
            //     exit;die();
            // }--

            // Log::info("Start Check SupressionList 3 CampaignID : #" . $leadspeek_api_id);--
            /** CHECK CAMPAIGN LEVEL */
            //$suppressionlistCampaign = SuppressionList::select('emailmd5')
            // $suppressionlistCampaign = SuppressionList::where('emailmd5','=',$md5param)
            //                             ->where('leadspeek_api_id','=',$leadspeek_api_id)
            //                             ->where('suppression_type','=','campaign')
            //                             //->get();
            //                             ->exists();

            // $_cacheKey = "suplist3_" . $md5param . '_' . $leadspeek_api_id;--
            // $suppressionlistCampaign = $this->cacheGetResult($_cacheKey);
            // if(is_null($suppressionlistCampaign)) {
            //     $suppressionlistCampaign = SuppressionList::where('emailmd5','=',$md5param)
            //                                               ->where('leadspeek_api_id','=',$leadspeek_api_id)
            //                                               ->where('suppression_type','=','campaign')
            //                                               ->exists();
            //     if($suppressionlistCampaign) {
            //         $suppressionlistCampaign = $this->cacheQueryResult($_cacheKey,86400,function () use ($suppressionlistCampaign) {
            //             return $suppressionlistCampaign;
            //         });
            //     } else {
            //         if($this->cacheHasResult($_cacheKey)) {
            //             $this->cacheForget($_cacheKey);
            //         }
            //     }
            // }--
            //if (count($suppressionlistCampaign) > 0) {
            // if ($suppressionlistCampaign) {--
            //     $notOnSuppressionList = false;
            //     /** UPDATE FOR DESCRIPTION ON FAILED RECORD */
            //         $frupdate = FailedRecord::find($failedRecordID);
            //         $frupdate->description = $frupdate->description . '|AgainstCampaignOptList';
            //         $frupdate->save();
            //     /** UPDATE FOR DESCRIPTION ON FAILED RECORD */
                
            //     // /* RELEASE LOCK PROCESS FOR GETDATAMATCH */
            //     // Log::info("RELEASE GETDATAMATCH LOCK SupressionList 3 CampaignID #" . $leadspeek_api_id);
            //     // $this->releaseLock($initLock);
            //     // /* RELEASE LOCK PROCESS FOR GETDATAMATCH */
            //     Log::info("Failed Serve SupressionList 3 CampaignID : #" . $leadspeek_api_id);
                
            //     /* WRITE UPSER FAILED LEAD RECORD */
            //     $this->UpsertFailedLeadRecord([
            //         'function' => 'getDataMatch',
            //         'type' => 'blocked',
            //         'blocked_type' => 'supressionlist',
            //         'description' => 'blocked in table supression_lists where supression_type campaign',
            //         'leadspeek_api_id' => $leadspeek_api_id,
            //         'email_encrypt' => $md5param,
            //         'leadspeek_type' => $leadspeektype,
            //     ]);
            //     /* WRITE UPSER FAILED LEAD RECORD */

            //     return array();
            //     exit;die();
            // }--

        }
        /** CHECK AGAINST CAMPAIGN OR ACCOUNT SUPPRESSION LIST */

        /** CHECK REGARDING RE-IDENTIFICATION FOR LEADS */
        if ($notOnSuppressionList) {
            $reidentification = 'never';
            $notOnReidentification = true;
            $applyreidentificationall = false;

            // $chkreidentification = LeadspeekUser::select('reidentification_type','applyreidentificationall')
            //                             ->where('leadspeek_api_id','=',$leadspeek_api_id)
            //                             ->first();

            $_cacheKey = "reidentification_" . $leadspeek_api_id;
            $chkreidentification = $this->cacheQueryResult($_cacheKey,30,function () use ($leadspeek_api_id) {
                return LeadspeekUser::select('reidentification_type','applyreidentificationall')
                                        ->where('leadspeek_api_id','=',$leadspeek_api_id)
                                        ->first();
            });
            //if (count($chkreidentification) > 0) {
            if ($chkreidentification) {
                $reidentification = $chkreidentification->reidentification_type;
                $applyreidentificationall = ($chkreidentification->applyreidentificationall == 'T')?true:false;
            }

            /** CHECK IF DATA ALREADY IN THAT CAMPAIGN OR NOT */
            $chkExistOnCampaign = array();

            if ($applyreidentificationall) {
                // $chkExistOnCampaign = LeadspeekReport::select('leadspeek_reports.id','leadspeek_reports.clickdate','leadspeek_reports.created_at')
                //                         ->join('leadspeek_users','leadspeek_reports.lp_user_id','=','leadspeek_users.id')
                //                         ->where('leadspeek_reports.company_id','=',$clientCompanyID)
                //                         ->where('leadspeek_users.applyreidentificationall','=','T')
                //                         ->where('leadspeek_users.archived','=','F')
                //                         ->where(function($query) use ($salt,$md5param){
                //                             // $query->where(DB::raw("MD5(CONVERT(AES_DECRYPT(FROM_bASE64(`leadspeek_reports`.`email`), '" . $salt . "') USING utf8mb4))"),'=',$md5param)
                //                             //         ->orWhere(DB::raw("MD5(CONVERT(AES_DECRYPT(FROM_bASE64(`leadspeek_reports`.`email2`), '" . $salt . "') USING utf8mb4))"),'=',$md5param)
                //                             //         ->orWhere('leadspeek_reports.original_md5','=',$md5param);
                //                             $query->where('leadspeek_reports.email_mdfive','=',$md5param)
                //                                      ->orWhere('leadspeek_reports.email2_mdfive','=',$md5param)
                //                                      ->orWhere('leadspeek_reports.original_md5','=',$md5param);
                //                         })
                //                         // ->orderBy(DB::raw("DATE_FORMAT(leadspeek_reports.clickdate,'%Y%m%d')"),'DESC')
                //                         ->orderBy('leadspeek_reports.clickdate','DESC')	
                //                         // ->limit(1)
                //                         // ->get();
                //                         ->first();
                
                $_cacheKey = "chkExistOnCampaign1_" . $md5param . '_' . $clientCompanyID;
                $chkExistOnCampaign = $this->cacheGetResult($_cacheKey);
                if(is_null($chkExistOnCampaign)) {
                    $chkExistOnCampaign = LeadspeekReport::select('leadspeek_reports.id','leadspeek_reports.clickdate','leadspeek_reports.created_at')
                                                        ->join('leadspeek_users','leadspeek_reports.lp_user_id','=','leadspeek_users.id')
                                                        ->where('leadspeek_users.applyreidentificationall','=','T')
                                                        ->where('leadspeek_users.archived','=','F')
                                                        ->where('leadspeek_reports.company_id','=',$clientCompanyID)
                                                        ->where('leadspeek_reports.original_md5','=',$md5param)
                                                        // ->where(function($query) use ($md5param){
                                                        //     // $query->where('leadspeek_reports.email_mdfive','=',$md5param)
                                                        //     //         ->orWhere('leadspeek_reports.email2_mdfive','=',$md5param)
                                                        //     //         ->orWhere('leadspeek_reports.original_md5','=',$md5param);
                                                        //     $query->where('leadspeek_reports.original_md5','=',$md5param);
                                                        // })
                                                        ->orderBy('leadspeek_reports.clickdate','DESC')	
                                                        ->first();
                    if(!empty($chkExistOnCampaign)) {
                        $chkExistOnCampaign = $this->cacheQueryResult($_cacheKey,86400,function () use ($chkExistOnCampaign) {
                            return $chkExistOnCampaign;
                        });
                    } else {
                        if($this->cacheHasResult($_cacheKey)) {
                            $this->cacheForget($_cacheKey);
                        }
                    }
                }
            }else{
                // $chkExistOnCampaign = LeadspeekReport::select('id','clickdate','created_at')
                //                         ->where('leadspeek_api_id','=',$leadspeek_api_id)
                //                         ->where(function($query) use ($salt,$md5param){
                //                             // $query->where(DB::raw("MD5(CONVERT(AES_DECRYPT(FROM_bASE64(`email`), '" . $salt . "') USING utf8mb4))"),'=',$md5param)
                //                             //         ->orWhere(DB::raw("MD5(CONVERT(AES_DECRYPT(FROM_bASE64(`email2`), '" . $salt . "') USING utf8mb4))"),'=',$md5param)
                //                             //         ->orWhere('original_md5','=',$md5param);
                //                             $query->where('email_mdfive','=',$md5param)
                //                                      ->orWhere('email2_mdfive','=',$md5param)
                //                                      ->orWhere('original_md5','=',$md5param);
                //                         })
                //                         // ->orderBy(DB::raw("DATE_FORMAT(clickdate,'%Y%m%d')"),'DESC')
                //                         ->orderBy('clickdate','DESC')	
                //                         //->limit(1)
                //                         //->get();
                //                         ->first();

                $_cacheKey = "chkExistOnCampaign2_" . $md5param . '_' . $leadspeek_api_id;
                $chkExistOnCampaign = $this->cacheGetResult($_cacheKey);
                if(is_null($chkExistOnCampaign)) {
                    $chkExistOnCampaign = LeadspeekReport::select('id','clickdate','created_at')
                                                        ->where('leadspeek_api_id','=',$leadspeek_api_id)
                                                        ->where('original_md5','=',$md5param)
                                                        // ->where(function($query) use ($md5param){
                                                        //     // $query->where('email_mdfive','=',$md5param)
                                                        //     //     ->orWhere('email2_mdfive','=',$md5param)
                                                        //     //     ->orWhere('original_md5','=',$md5param);
                                                        //     $query->where('original_md5','=',$md5param);
                                                        // })
                                                        ->orderBy('clickdate','DESC')	
                                                        ->first();
                    if(!empty($chkExistOnCampaign)) {
                        $chkExistOnCampaign = $this->cacheQueryResult($_cacheKey,86400,function () use ($chkExistOnCampaign) {
                            return $chkExistOnCampaign;
                        });
                    } else {
                        if($this->cacheHasResult($_cacheKey)) {
                            $this->cacheForget($_cacheKey);
                        }
                    }
                }
            }

            //if (count($chkExistOnCampaign) > 0 && $reidentification != 'never') {
            //if (count($chkExistOnCampaign) > 0) {
            info('getdatamatch 1.1', [
                'chkExistOnCampaign' => $chkExistOnCampaign,
            ]);
            if ($chkExistOnCampaign) {
                //$clickDate = date('Ymd',strtotime($chkExistOnCampaign[0]['clickdate']));
                $clickDate = date('Ymd',strtotime($chkExistOnCampaign->clickdate));
                $date1=date_create(date('Ymd'));
                $date2=date_create($clickDate);
                $diff=date_diff($date1,$date2);
                info('getdatamatch 2.1', [
                    'clickDate' => $clickDate,
                    'date1' => $date1,
                    'date2' => $date2,
                    'diff' => $diff,
                    'reidentification' => $reidentification,
                    '$diff->format("%a")' => $diff->format("%a"),
                ]);

                if ($reidentification == 'never') {
                    $notOnReidentification = false;
                    info('getdatamatch 3.1', ['notOnReidentification' => $notOnReidentification]);
                }else if ($diff->format("%a") <= 7 && $reidentification == '1 week') {
                    $notOnReidentification = false;
                    info('getdatamatch 3.2', ['notOnReidentification' => $notOnReidentification]);
                }else if ($diff->format("%a") <= 30 && $reidentification == '1 month') {
                    $notOnReidentification = false;
                    info('getdatamatch 3.3', ['notOnReidentification' => $notOnReidentification]);
                }else if ($diff->format("%a") <= 90 && $reidentification == '3 months') {
                    $notOnReidentification = false;
                    info('getdatamatch 3.4', ['notOnReidentification' => $notOnReidentification]);
                }else if ($diff->format("%a") <= 120 && $reidentification == '6 months') {
                    $notOnReidentification = false;
                    info('getdatamatch 3.5', ['notOnReidentification' => $notOnReidentification]);
                }else if ($diff->format("%a") <= 360 && $reidentification == '1 year') {
                    $notOnReidentification = false;
                    info('getdatamatch 3.6', ['notOnReidentification' => $notOnReidentification]);
                }
            }
            /** CHECK IF DATA ALREADY IN THAT CAMPAIGN OR NOT */

        }
        /** CHECK REGARDING RE-IDENTIFICATION FOR LEADS */

        /** CHECK ON DATABASE IF EXIST */
        if ($notOnReidentification) {
            Log::info("Start Process Check If Data Exist On Our Database");

            // $chkEmailExist = PersonEmail::select('person_emails.email','person_emails.id as emailID','person_emails.permission','p.lastEntry','p.uniqueID',DB::raw("CONVERT(AES_DECRYPT(FROM_bASE64(`firstName`), '" . $salt . "') USING utf8mb4) as firstName"),
            // DB::raw("CONVERT(AES_DECRYPT(FROM_bASE64(`lastName`), '" . $salt . "') USING utf8mb4) as lastName"),'p.id')
            // $chkEmailExist = PersonEmail::select('person_emails.email','person_emails.id as emailID','person_emails.permission','p.lastEntry','p.uniqueID','p.firstName','p.lastName','p.id')
            //                     ->join('persons as p','person_emails.person_id','=','p.id')
            //                     ->where('person_emails.email_encrypt','=',$md5param)
            //                     ->first();

            $_cacheKey = "chkEmailExistPersonEmail_" . $md5param;
            $chkEmailExist = $this->cacheGetResult($_cacheKey);
            if(is_null($chkEmailExist)) {
                $chkEmailExist = PersonEmail::select('person_emails.email','person_emails.id as emailID','person_emails.permission','p.lastEntry','p.uniqueID','p.firstName','p.lastName','p.id')
                                        ->join('persons as p','person_emails.person_id','=','p.id')
                                        ->where('person_emails.email_encrypt','=',$md5param)
                                        ->first();

                if(!empty($chkEmailExist)) {
                    $chkEmailExist = $this->cacheQueryResult($_cacheKey,86400,function () use ($chkEmailExist) {
                        return $chkEmailExist;
                    });
                } else {
                    if($this->cacheHasResult($_cacheKey)) {
                        $this->cacheForget($_cacheKey);
                    }
                }
            }

            $is_advance = false;
            if ($leadspeektype == 'enhance') {
                $campaign = LeadspeekUser::select('advance_information')->where('leadspeek_api_id',$leadspeek_api_id)->first();
                if (isset($campaign->advance_information) && !empty($campaign->advance_information) && trim($campaign->advance_information) != '') {
                    $is_advance = true;
                }
            }

            $is_local_custom = [
                'is_advance' => false,
                'is_b2b' => false,
                'advance' => [],
                'b2b' => [],
            ];
            $campaign_information_type_local = 'basic';
            if ($leadspeektype == 'local') {
                    $campaign = LeadspeekUser::select('paymentterm','advance_information','campaign_information_type_local')->where('leadspeek_api_id',$leadspeek_api_id)->first();

                    if (strtolower($campaign->paymentterm) == 'prepaid') {
                        $topup = Topup::select('advance_information','campaign_information_type_local')
                                ->where('leadspeek_api_id',$leadspeek_api_id)
                                ->where('leadspeek_type','local')
                                ->where('topup_status', 'progress')
                                ->orderBy('id','ASC')
                                ->first();
                                
                        $campaign_information_type_local = !empty($topup->campaign_information_type_local) ? explode(',',$topup->campaign_information_type_local) : [];
                        $advance_information = !empty($topup->advance_information) ? json_decode($topup->advance_information) : '';

                        if (in_array('advanced',$campaign_information_type_local)) {
                            $is_local_custom['is_advance'] = in_array('advanced',$campaign_information_type_local) ? true : false;
                            $is_local_custom['is_b2b'] = false;
                            $is_local_custom['advance'] = isset($advance_information->advance) ? $advance_information->advance : [];
                            $is_local_custom['b2b'] = [];
                        }

                    }else {
                        $campaign_information_type_local = !empty($campaign->campaign_information_type_local) ? explode(',',$campaign->campaign_information_type_local) : [];
                        $advance_information = !empty($campaign->advance_information) ? json_decode($campaign->advance_information) : '';

                        if (in_array('advanced',$campaign_information_type_local)) {
                            $is_local_custom['is_advance'] = in_array('advanced',$campaign_information_type_local) ? true : false;
                            $is_local_custom['is_b2b'] = false;
                            $is_local_custom['advance'] = isset($advance_information->advance) ? $advance_information->advance : [];
                            $is_local_custom['b2b'] = [];
                        }
                    }
            }
            //if (count($chkEmailExist) > 0) {
            if ($chkEmailExist) {
                    Log::info("Start Process Data Exist on Our Database");

                    $lastEntry = date('Ymd',strtotime($chkEmailExist->lastEntry));
                    $date1=date_create(date('Ymd'));
                    $date2=date_create($lastEntry);
                    $diff=date_diff($date1,$date2);

                    $persondata['id'] = $chkEmailExist->id;
                    $persondata['emailID'] = $chkEmailExist->emailID;
                    $persondata['uniqueID'] = $chkEmailExist->uniqueID;
                    $persondata['firstName'] = Encrypter::decrypt($chkEmailExist->firstName);
                    $persondata['lastName'] = Encrypter::decrypt($chkEmailExist->lastName);

                    /** CHECK FOR LOCATION LOCK */
                    //$datalocation = PersonAddress::select('state','zip','city')->where('person_id','=',$persondata['id'])->first();
                    
                    $_cacheKey = "PersonLocDataLoc_" . $persondata['id'];
                    $datalocation = $this->cacheGetResult($_cacheKey);
                    if(is_null($datalocation)) {
                        $datalocation = PersonAddress::select('state','zip','city')->where('person_id','=',$persondata['id'])->first();

                        if(!empty($datalocation)) {
                            $datalocation = $this->cacheQueryResult($_cacheKey,86400,function () use ($datalocation) {
                                return $datalocation;
                            });
                        } else {
                            if($this->cacheHasResult($_cacheKey)) {
                                $this->cacheForget($_cacheKey);
                            }
                        }
                    }

                    //if (count($datalocation) > 0) {
                    if ($datalocation) {
                        $chkloc = $this->checklocationlock($loctarget,$datalocation->zip,$datalocation->state,$datalocation->city,$loczip,$locstate,$locstateexclude,$locstatesifi,$loccity,$loccitysifi,$nationaltargeting,$failedRecordID);
                        if ($chkloc) {
                            /** REPORT ANALYTIC */
                                $this->UpsertReportAnalytics($leadspeek_api_id,$leadspeektype,'locationlockfailed');
                            /** REPORT ANALYTIC */

                            // /* RELEASE LOCK PROCESS FOR GETDATAMATCH */
                            // Log::info("RELEASE GETDATAMATCH LOCK Process DATA 1 CampaignID #" . $leadspeek_api_id);
                            // $this->releaseLock($initLock);
                            // /* RELEASE LOCK PROCESS FOR GETDATAMATCH */

                            /* WRITE UPSER FAILED LEAD RECORD */
                            $this->UpsertFailedLeadRecord([
                                'function' => 'getDataMatch',
                                'type' => 'blocked',
                                'blocked_type' => 'locationlock',
                                'description' => "blocked in checklocationlock zip = $loczip, state = $locstate, stateexclude = $locstateexclude, city = $loccity function getDataMatch",
                                'leadspeek_api_id' => $leadspeek_api_id,
                                'email_encrypt' => $md5param,
                                'leadspeek_type' => $leadspeektype,
                            ]);
                            /* WRITE UPSER FAILED LEAD RECORD */

                            return array();
                            exit;die();
                        }else{
                            /** REPORT ANALYTIC */
                                $this->UpsertReportAnalytics($leadspeek_api_id,$leadspeektype,'locationlock');
                            /** REPORT ANALYTIC */
                        }
                    }
                    /** CHECK FOR LOCATION LOCK */

                    $dataresult = array();
                    $personEmail = $chkEmailExist->email;

                    $dataflow = $dataflow . 'EmailExistonDB|';

                if ($diff->format("%a") <= 120 && $chkEmailExist->permission == "T") { /** customer exist, permission YES, last Entry < 6 Month **/
                    // DATA EXIST ON DB
                    $dataflow = $dataflow . 'LastEntryLessSixMonthPermissionYes|';
                    $resultDataExistOnDB = $this->dataExistOnDB($personEmail,$persondata,$data,$keyword,$dataflow,$failedRecordID,$md5param,$leadspeek_api_id,$leadspeektype,$is_advance,$is_local_custom);
                    $dataresult = isset($resultDataExistOnDB['data'])?$resultDataExistOnDB['data']:[];
                    $executionTimeList = isset($resultDataExistOnDB['executionTimeList'])?$resultDataExistOnDB['executionTimeList']:[]; 
                }else if ($diff->format("%a") > 120 && $chkEmailExist->permission == "T") {  /** customer exist, permission YES, last Entry > 6 Month **/
                    // UPDATE OR QUERY TO ENDATO
                    $dataflow = $dataflow . 'LastEntryMoreSixMonthPermissionYes|';
                    $resultdataNotExistOnDBBIG = $this->dataNotExistOnDBBIG($persondata['firstName'],$persondata['lastName'],$personEmail,"","","","","",$persondata['id'],$keyword,$dataflow,$failedRecordID,$md5param,$leadspeek_api_id,$leadspeektype,$is_advance,$is_local_custom);
                    $dataresult = isset($resultdataNotExistOnDBBIG['data'])?$resultdataNotExistOnDBBIG['data']:[];
                    $executionTimeList = isset($resultdataNotExistOnDBBIG['executionTimeList'])?$resultdataNotExistOnDBBIG['executionTimeList']:[]; 
                    //$dataresult = $this->dataNotExistOnDB($persondata['firstName'],$persondata['lastName'],$personEmail,"","","","","",$persondata['id'],$keyword,$dataflow,$failedRecordID,$md5param,$leadspeek_api_id,$leadspeektype);
                }else if ($diff->format("%a") > 120 && $chkEmailExist->permission == "F") {  /** customer exist, permission NOT, last Entry > 6 Month **/
                    // UPDATE OR QUERY TO ENDATO
                    $dataflow = $dataflow . 'LastEntryMoreSixMonthPermissionNo|';
                    $resultdataNotExistOnDBBIG = $this->dataNotExistOnDBBIG($persondata['firstName'],$persondata['lastName'],$personEmail,"","","","","",$persondata['id'],$keyword,$dataflow,$failedRecordID,$md5param,$leadspeek_api_id,$leadspeektype,$is_advance,$is_local_custom);
                    $dataresult = isset($resultdataNotExistOnDBBIG['data'])?$resultdataNotExistOnDBBIG['data']:[];
                    $executionTimeList = isset($resultdataNotExistOnDBBIG['executionTimeList'])?$resultdataNotExistOnDBBIG['executionTimeList']:[]; 
                    //$dataresult = $this->dataNotExistOnDB($persondata['firstName'],$persondata['lastName'],$personEmail,"","","","","",$persondata['id'],$keyword,$dataflow,$failedRecordID,$md5param,$leadspeek_api_id,$leadspeektype);
                }

                // /* RELEASE LOCK PROCESS FOR GETDATAMATCH */
                //     Log::info("RELEASE GETDATAMATCH LOCK Process DATA 2 CampaignID #" . $leadspeek_api_id);
                //     $this->releaseLock($initLock);
                // /* RELEASE LOCK PROCESS FOR GETDATAMATCH */

                if (count($dataresult) > 0) {
                    array_push($matches,$dataresult);
                    
                    /* RELEASE LOCK PROCESS FOR GETDATAMATCH */
                    // Log::info("RELEASE GETDATAMATCH LOCK");
                    // $this->releaseLock($initLock);
                    /* RELEASE LOCK PROCESS FOR GETDATAMATCH */

                    return [
                        'matches' => $matches,
                        'executionTimeList' => $executionTimeList,
                    ];
                }else{
                    /** UPDATE FOR DESCRIPTION ON FAILED RECORD */
                        $frupdate = FailedRecord::find($failedRecordID);
                        $frupdate->description = $frupdate->description . '|NotAll4RequiredDataReturned';
                        $frupdate->save();
                    /** UPDATE FOR DESCRIPTION ON FAILED RECORD */

                    /** RECORD AS FAILURE */
                    /*$fr = FailedRecord::create([
                        'email_encrypt' => $md5param,
                        'leadspeek_api_id' => $leadspeek_api_id,
                        'description' => 'Not All 4 Required data returned',
                    ]);*/
                    
                    /* RELEASE LOCK PROCESS FOR GETDATAMATCH */
                    // Log::info("RELEASE GETDATAMATCH LOCK");
                    // $this->releaseLock($initLock);
                    /* RELEASE LOCK PROCESS FOR GETDATAMATCH */
                    
                    return array();
                    /** RECORD AS FAILURE */
                }

            }else{ //IF NOT EXIST ON DB

                Log::info("Start Process Data On BigDBM");

                /** QUERY BIG BDM TO GET RESULT */
                    $dataresult = [];
                    if ($is_advance) {
                    $processBDM = $this->process_BDM_advance($loctarget,$keyword,$dataflow,$failedRecordID,$md5param,$leadspeek_api_id,$loczip,$locstate,$locstateexclude,$locstatesifi,$loccity,$loccitysifi,$nationaltargeting,$leadspeektype,'',$is_advance);
                    }elseif($leadspeektype == 'b2b'){
                    $processBDM = $this->process_BDM_B2B($loctarget,$keyword,$dataflow,$failedRecordID,$md5param,$leadspeek_api_id,$loczip,$locstate,$locstateexclude,$locstatesifi,$loccity,$loccitysifi,$nationaltargeting,$leadspeektype);
                    // }elseif($is_local_custom['is_advance'] || $is_local_custom['is_b2b']){
                    }elseif($is_local_custom['is_advance']){
                    $processBDM = $this->process_BDM_local_custom($loctarget,$keyword,$dataflow,$failedRecordID,$md5param,$leadspeek_api_id,$loczip,$locstate,$locstateexclude,$locstatesifi,$loccity,$loccitysifi,$nationaltargeting,$leadspeektype,'',$is_local_custom);
                    }else {
                    $processBDM = $this->process_BDM_TowerDATA($loctarget,$keyword,$dataflow,$failedRecordID,$md5param,$leadspeek_api_id,$loczip,$locstate,$locstateexclude,$locstatesifi,$loccity,$loccitysifi,$nationaltargeting,$leadspeektype);
                    }
                    $dataresult = isset($processBDM['data'])?$processBDM['data']:[];
                    $executionTimeList = isset($processBDM['executionTimeList'])?$processBDM['executionTimeList']:[];

                    // /* RELEASE LOCK PROCESS FOR GETDATAMATCH */
                    // Log::info("RELEASE GETDATAMATCH LOCK Process DATA BIG BDM HAVE RESULT CampaignID #" . $leadspeek_api_id);
                    // $this->releaseLock($initLock);
                    // /* RELEASE LOCK PROCESS FOR GETDATAMATCH */

                    if (count($dataresult) > 0) {
                        array_push($matches,$dataresult);
                        
                        /* RELEASE LOCK PROCESS FOR GETDATAMATCH */
                        // Log::info("RELEASE GETDATAMATCH LOCK");
                        // $this->releaseLock($initLock);
                        /* RELEASE LOCK PROCESS FOR GETDATAMATCH */
                        Log::info("Start Process Data On BigDBM - Matched");

                        return [
                            'matches' => $matches,
                            'executionTimeList' => $executionTimeList,
                        ];
                    }else{
                        /* RELEASE LOCK PROCESS FOR GETDATAMATCH */
                        // Log::info("RELEASE GETDATAMATCH LOCK");
                        // $this->releaseLock($initLock);
                        /* RELEASE LOCK PROCESS FOR GETDATAMATCH */
                        Log::info("Start Process Data On BigDBM - NOT Matched");

                        return array();
                        exit;die();
                    }
                /** QUERY BIG BDM TO GET RESULT */

                /** QUERYING TO TOWER DATA WITH DATA POSTAL TO GET SOME OTHER INFORMATION WITH MD5 EMAIL */
                    // $tower = $this->getTowerData("postal",$md5param);

                    // if (isset($tower->postal_address)) {
                    //     $_fname = $tower->postal_address->first_name;
                    //     $_lname = $tower->postal_address->last_name;
                    //     $_email = "";
                    //     $_phone = "";
                    //     $_address = $tower->postal_address->address;
                    //     $_city = $tower->postal_address->city;
                    //     $_state = $tower->postal_address->state;
                    //     $_zip = $tower->postal_address->zip;

                    //     /** REPORT ANALYTIC */
                    //         $this->UpsertReportAnalytics($leadspeek_api_id,$leadspeektype,'towerpostal');
                    //     /** REPORT ANALYTIC */

                    //     $dataresult = $this->dataNotExistOnDB($_fname,$_lname,$_email,$_phone,$_address,$_city,$_state,$_zip,"",$keyword,$dataflow,$failedRecordID,$md5param,$leadspeek_api_id,$leadspeektype);

                    //     if (count($dataresult) > 0) {

                    //         /** REPORT ANALYTIC */
                    //             $this->UpsertReportAnalytics($leadspeek_api_id,$leadspeektype,'endatoenrichment');
                    //         /** REPORT ANALYTIC */

                    //         /** CHECK FOR LOCATION LOCK */
                    //             $chkloc = $this->checklocationlock($loctarget,$dataresult['Zipcode'],$dataresult['State'],$dataresult['City'],$loczip,$locstate,$loccity,$nationaltargeting,$failedRecordID);
                    //             if ($chkloc) {
                    //                 /** REPORT ANALYTIC */
                    //                     $this->UpsertReportAnalytics($leadspeek_api_id,$leadspeektype,'locationlockfailed');
                    //                 /** REPORT ANALYTIC */
                    //                 return array();
                    //                 exit;die();
                    //             }else{
                    //                 /** REPORT ANALYTIC */
                    //                 $this->UpsertReportAnalytics($leadspeek_api_id,$leadspeektype,'locationlock');
                    //                 /** REPORT ANALYTIC */
                    //             }
                    //         /** CHECK FOR LOCATION LOCK */

                    //         if (trim($dataresult['Email']) != "") {
                    //             array_push($matches,$dataresult);
                    //             return $matches;
                    //         }else{
                    //             $tower = $this->getTowerData("md5",$md5param);
                    //             if (isset($tower->target_email)) {
                    //                 if ($tower->target_email != "") {
                    //                     $tmpEmail = strtolower(trim($tower->target_email));
                    //                     $tmpMd5 = md5($tmpEmail);
                    //                     $dataresult['Email'] = $tmpEmail;

                    //                     /** REPORT ANALYTIC */
                    //                         $this->UpsertReportAnalytics($leadspeek_api_id,$leadspeektype,'toweremail');
                    //                     /** REPORT ANALYTIC */

                    //                     if(trim($tmpEmail) != "") {
                    //                         $zbcheck = $this->zb_validation($tmpEmail,"");
                    //                         if (isset($zbcheck->status)) {
                    //                             if($zbcheck->status == "invalid" || $zbcheck->status == "catch-all" || $zbcheck->status == "abuse" || $zbcheck->status == "do_not_mail" || $zbcheck->status == "spamtrap") {
                    //                                 /** PUT IT ON OPTOUT LIST */
                    //                                 $createoptout = OptoutList::create([
                    //                                     'email' => $tmpEmail,
                    //                                     'emailmd5' => md5($tmpEmail),
                    //                                     'blockedcategory' => 'zbnotvalid',
                    //                                     'description' => 'Zero Bounce Status. : ' . $zbcheck->status . '|Email1fromTD',
                    //                                 ]);
                    //                                 /** PUT IT ON OPTOUT LIST */
                    //                                 $dataresult['Email'] = "";

                    //                                 /** REPORT ANALYTIC */
                    //                                 $this->UpsertReportAnalytics($leadspeek_api_id,$leadspeektype,'zerobouncefailed');
                    //                                 /** REPORT ANALYTIC */
                    //                             }else{
                    //                                 $newpersonemail = PersonEmail::create([
                    //                                     'person_id' => $dataresult['PersonID'],
                    //                                     'email' => $tmpEmail,
                    //                                     'email_encrypt' => $tmpMd5,
                    //                                     'permission' => 'T',
                    //                                     'zbvalidate' => date('Y-m-d H:i:s'),
                    //                                 ]);

                    //                                 /** REPORT ANALYTIC */
                    //                                     $this->UpsertReportAnalytics($leadspeek_api_id,$leadspeektype,'zerobounce');
                    //                                 /** REPORT ANALYTIC */

                    //                             }

                    //                         }else{
                    //                             $newpersonemail = PersonEmail::create([
                    //                                 'person_id' => $dataresult['PersonID'],
                    //                                 'email' => $tmpEmail,
                    //                                 'email_encrypt' => $tmpMd5,
                    //                                 'permission' => 'T',
                    //                                 'zbvalidate' => null,
                    //                             ]);
                    //                         }
                    //                     }

                    //                 }
                    //             }

                    //             array_push($matches,$dataresult);
                    //             return $matches;
                    //         }
                    //     }else{
                    //         /** CHECK FOR LOCATION LOCK */
                    //         $chkloc = $this->checklocationlock($loctarget,$_zip,$_state,$_city,$loczip,$locstate,$loccity,$nationaltargeting,$failedRecordID);
                    //             if ($chkloc) {
                    //                 /** REPORT ANALYTIC */
                    //                 $this->UpsertReportAnalytics($leadspeek_api_id,$leadspeektype,'locationlockfailed');
                    //                 /** REPORT ANALYTIC */
                    //                 return array();
                    //                 exit;die();
                    //             }else{
                    //                 /** REPORT ANALYTIC */
                    //                 $this->UpsertReportAnalytics($leadspeek_api_id,$leadspeektype,'locationlock');
                    //                 /** REPORT ANALYTIC */
                    //             }
                    //         /** CHECK FOR LOCATION LOCK */

                    //         /** GET FROM MD5 TO QUERY AND GET WHATEVER WE CAN GET */
                    //         $tower = $this->getTowerData("md5",$md5param);
                    //         if (isset($tower->target_email)) {
                    //             if ($tower->target_email != "") {
                    //                 $tmpEmail = strtolower(trim($tower->target_email));
                    //                 $tmpMd5 = md5($tmpEmail);
                    //                 $_email = $tmpEmail;

                    //                 /** REPORT ANALYTIC */
                    //                     $this->UpsertReportAnalytics($leadspeek_api_id,$leadspeektype,'toweremail');
                    //                 /** REPORT ANALYTIC */

                    //                 if(trim($tmpEmail) != "") {

                    //                     $uniqueID = uniqid();
                    //                     /** INSERT INTO DATABASE PERSON */
                    //                         $newPerson = Person::create([
                    //                             'uniqueID' => $uniqueID,
                    //                             'firstName' => $_fname,
                    //                             'middleName' => '',
                    //                             'lastName' => $_lname,
                    //                             'age' => '0',
                    //                             'identityScore' => '0',
                    //                             'lastEntry' => date('Y-m-d H:i:s'),
                    //                         ]);

                    //                         $newPersonID = $newPerson->id;
                    //                     /** INSERT INTO DATABASE PERSON */

                    //                     $zbcheck = $this->zb_validation($tmpEmail,"");
                    //                     if (isset($zbcheck->status)) {
                    //                         if($zbcheck->status == "invalid" || $zbcheck->status == "catch-all" || $zbcheck->status == "abuse" || $zbcheck->status == "do_not_mail" || $zbcheck->status == "spamtrap") {
                    //                             /** PUT IT ON OPTOUT LIST */
                    //                             $createoptout = OptoutList::create([
                    //                                 'email' => $tmpEmail,
                    //                                 'emailmd5' => md5($tmpEmail),
                    //                                 'blockedcategory' => 'zbnotvalid',
                    //                                 'description' => 'Zero Bounce Status. : ' . $zbcheck->status . '|Email1fromTD',
                    //                             ]);
                    //                             /** PUT IT ON OPTOUT LIST */
                    //                             $_email = "";

                    //                             /** REPORT ANALYTIC */
                    //                             $this->UpsertReportAnalytics($leadspeek_api_id,$leadspeektype,'zerobouncefailed');
                    //                             /** REPORT ANALYTIC */
                    //                         }else{

                    //                             $newpersonemail = PersonEmail::create([
                    //                                 'person_id' => $newPersonID,
                    //                                 'email' => $tmpEmail,
                    //                                 'email_encrypt' => $tmpMd5,
                    //                                 'permission' => 'T',
                    //                                 'zbvalidate' => date('Y-m-d H:i:s'),
                    //                             ]);

                    //                             $_email = $tmpEmail;

                    //                             /** REPORT ANALYTIC */
                    //                                 $this->UpsertReportAnalytics($leadspeek_api_id,$leadspeektype,'zerobounce');
                    //                             /** REPORT ANALYTIC */

                    //                         }

                    //                     }else{
                    //                         $newpersonemail = PersonEmail::create([
                    //                             'person_id' => $newPersonID,
                    //                             'email' => $tmpEmail,
                    //                             'email_encrypt' => $tmpMd5,
                    //                             'permission' => 'T',
                    //                             'zbvalidate' => null,
                    //                         ]);

                    //                         $_email = $tmpEmail;

                    //                     }

                    //                     /** INSERT INTO PERSON_ADDRESSES */
                    //                     $newpersonaddress = PersonAddress::create([
                    //                         'person_id' => $newPersonID,
                    //                         'street' => $_address,
                    //                         'unit' => '',
                    //                         'city' => $_city,
                    //                         'state' => $_state,
                    //                         'zip' => $_zip,
                    //                         'fullAddress' => $_address . ' ' . $_city . ',' . $_state . ' ' . $_zip,
                    //                         'firstReportedDate' => date('Y-m-d'),
                    //                         'lastReportedDate' => date('Y-m-d'),
                    //                     ]);
                    //                     /** INSERT INTO PERSON_ADDRESSES */

                    //                 }

                    //             }
                    //         }

                    //         if (trim($_email) != "") {
                    //             $_ID = $this->generateReportUniqueNumber();

                    //             $new = array(
                    //                 "ID" => $_ID,
                    //                 "Email" => $_email,
                    //                 "Email2" => '',
                    //                 "OriginalMD5" => $md5param,
                    //                 "IP" => '',
                    //                 "Source" => "",
                    //                 "OptInDate" => date('Y-m-d H:i:s'),
                    //                 "ClickDate" => date('Y-m-d H:i:s'),
                    //                 "Referer" => "",
                    //                 "Phone" => $_phone,
                    //                 "Phone2" => '',
                    //                 "FirstName" => $_fname,
                    //                 "LastName" => $_lname,
                    //                 "Address1" => $_address,
                    //                 "Address2" => '',
                    //                 "City" => $_city,
                    //                 "State" => $_state,
                    //                 "Zipcode" => $_zip,
                    //                 "PersonID" => $newPersonID,
                    //                 "Keyword" => $keyword,
                    //                 "Description" => 'TowerDataPostal|NotGetDataEndato',
                    //             );

                    //             array_push($matches,$new);
                    //             return $matches;
                    //         }else{
                    //             return array();
                    //         }
                    //         /** GET FROM MD5 TO QUERY AND GET WHATEVER WE CAN GET */

                    //     }
                    // }else{
                    //     /** UPDATE FOR DESCRIPTION ON FAILED RECORD */
                    //         $frupdate = FailedRecord::find($failedRecordID);
                    //         $frupdate->description = $frupdate->description . '|NoDataReturnFromPostalTowerData';
                    //         $frupdate->save();
                    //     /** UPDATE FOR DESCRIPTION ON FAILED RECORD */
                    //     /** RECORD AS FAILURE */
                    //     /*$fr = FailedRecord::create([
                    //         'email_encrypt' => $md5param,
                    //         'leadspeek_api_id' => $leadspeek_api_id,
                    //         'description' => 'No Data Return from Postal Tower Data',
                    //     ]);*/
                    //     return array();
                    //     /** RECORD AS FAILURE */
                    // }
                /** QUERYING TO TOWER DATA WITH DATA POSTAL TO GET SOME OTHER INFORMATION WITH MD5 EMAIL */
            }



        }else{
            /** UPDATE FOR DESCRIPTION ON FAILED RECORD */
                $frupdate = FailedRecord::find($failedRecordID);
                $frupdate->description = $frupdate->description . '|MatchReidentification:' . $reidentification;
                $frupdate->save();
            /** UPDATE FOR DESCRIPTION ON FAILED RECORD */

            // /* RELEASE LOCK PROCESS FOR GETDATAMATCH */
            // Log::info("RELEASE GETDATAMATCH LOCK Process DATA RE-Identification Failed CampaignID #" . $leadspeek_api_id);
            // $this->releaseLock($initLock);
            // /* RELEASE LOCK PROCESS FOR GETDATAMATCH */
            // Log::info("Re-Indentification Failed");

            /* WRITE UPSER FAILED LEAD RECORD */
            $this->UpsertFailedLeadRecord([
                'function' => 'getDataMatch',
                'type' => 'blocked',
                'blocked_type' => 'reidentification',
                'description' => "blocked in reidentification type = $reidentification function getDataMatch",
                'leadspeek_api_id' => $leadspeek_api_id,
                'email_encrypt' => $md5param,
                'leadspeek_type' => $leadspeektype,
                // 'lead_not_serve_type' => 'reidentification_failed' //prepare new schem
            ]);
            /* WRITE UPSER FAILED LEAD RECORD */

            return array();
        }
        /** CHECK ON DATABASE IF EXIST */
    } catch (\Exception $e) {
        Log::info([
            'error' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
        ]);

        // /* RELEASE LOCK PROCESS FOR GETDATAMATCH */
        // Log::info("RELEASE GETDATAMATCH LOCK Process DATA Catch Failed CampaignID #" . $leadspeek_api_id);
        // $this->releaseLock($initLock);
        // /* RELEASE LOCK PROCESS FOR GETDATAMATCH */

        return array();
    }
}


private function processDataMatch($leadspeek_api_id,$matches,$data,$paymentTerm = "",$leadspeek_type = "",$md5param = "") 
{
    $executionTimeList = [];

    date_default_timezone_set('America/Chicago');

    $leadspeekReportID = "";

    /** LOCK PROCESS FOR PREPAID UNTIL DONE FIRST */
    if (trim($paymentTerm) != "" && trim($paymentTerm) == "Prepaid") 
    {
        Log::info("START PREPAID LOCK Campaign ID: #" . $leadspeek_api_id);
        while (!$this->acquireLock('initPrepaidStart' . $leadspeek_api_id)) 
        {
            Log::info("Initial Prepaid Processing. Waiting to acquire lock. Campaign ID : #" . $leadspeek_api_id);
            sleep(1); // Wait before trying again
        }
    }
    /** LOCK PROCESS FOR PREPAID UNTIL DONE FIRST */

    $clientList = LeadspeekUser::select('leadspeek_users.id','leadspeek_users.url_code','leadspeek_users.company_id as clientowner','leadspeek_users.report_type','leadspeek_users.report_sent_to','leadspeek_users.admin_notify_to','leadspeek_users.leadspeek_api_id','leadspeek_users.active','leadspeek_users.leadspeek_organizationid','leadspeek_users.leadspeek_campaignsid','leadspeek_users.gtminstalled'
    ,'leadspeek_users.leadspeek_locator_state','leadspeek_users.leadspeek_locator_require','leadspeek_users.leadspeek_locator_zip','leadspeek_users.leads_amount_notification','leadspeek_users.total_leads','leadspeek_users.ongoing_leads','leadspeek_users.last_lead_added','leadspeek_users.spreadsheet_id','leadspeek_users.filename','leadspeek_users.report_frequency_id','leadspeek_users.lp_max_lead_month','leadspeek_users.lp_min_cost_month','leadspeek_users.cost_perlead'
    ,'users.customer_payment_id','users.customer_card_id','users.email','users.company_parent','users.company_root_id','leadspeek_users.paymentterm','leadspeek_users.leadspeek_type','leadspeek_users.lp_enddate','leadspeek_users.platformfee','leadspeek_users.hide_phone','leadspeek_users.campaign_name','leadspeek_users.campaign_enddate','leadspeek_users.phoneenabled','leadspeek_users.homeaddressenabled'
    ,'leadspeek_users.lp_limit_leads','leadspeek_users.lp_limit_freq','leadspeek_users.lp_limit_startdate','leadspeek_users.report_frequency','leadspeek_users.report_frequency_unit','leadspeek_users.last_lead_check','leadspeek_users.start_billing_date','users.name','leadspeek_users.user_id','leadspeek_users.is_send_email_prepaid','companies.id as company_id','companies.company_name'
    ,'leadspeek_users.created_at','leadspeek_users.embedded_lastreminder','leadspeek_users.trysera','leadspeek_users.campaign_information_type_local','leadspeek_users.advance_information',
    'leadspeek_users.ghl_tags','leadspeek_users.ghl_is_active','leadspeek_users.ghl_remove_tags','leadspeek_users.ghl_status_keyword_to_tags','leadspeek_users.sendgrid_is_active as sendgrid_is_active', 'leadspeek_users.sendgrid_action as sendgrid_action','leadspeek_users.sendgrid_list as sendgrid_list','leadspeek_users.mbp_groups','leadspeek_users.mbp_is_active','leadspeek_users.mbp_states','leadspeek_users.mbp_options','leadspeek_users.mbp_zip','leadspeek_users.clickfunnels_is_active','leadspeek_users.clickfunnels_tags','leadspeek_users.topupoptions','leadspeek_users.leadsbuy','leadspeek_users.stopcontinual','leadspeek_users.continual_buy_options','leadspeek_users.sendjim_is_active as sendjim_is_active','leadspeek_users.sendjim_tags as sendjim_tags','leadspeek_users.sendjim_quicksend_templates as sendjim_quicksend_templates','leadspeek_users.sendjim_quicksend_is_active as sendjim_quicksend_is_active')
    ->selectRaw('TIMESTAMPDIFF(MINUTE,leadspeek_users.last_lead_check,NOW()) as minutesdiff')
    ->selectRaw('TIMESTAMPDIFF(HOUR,leadspeek_users.last_lead_check,NOW()) as hoursdiff')
                    ->join('users','leadspeek_users.user_id','=','users.id')
                    ->join('companies','users.company_id','=','companies.id')
                    //->leftjoin('companies_integration_settings','users.company_id','=','companies_integration_settings.company_id')
                    ->where('leadspeek_users.active','=','T')
                    ->where('leadspeek_users.active_user','=','T')
                    ->where('users.user_type','=','client')
                    ->where('users.active','=','T')
                    ->where('leadspeek_users.leadspeek_api_id','=',$leadspeek_api_id)
                    ->get();

    foreach($clientList as $cl) 
    {
        //$clientEmail = explode(PHP_EOL, $cl['report_sent_to']);
        $_clientEmail = str_replace(["\r\n", "\r"], "\n", trim($cl['report_sent_to']));
        $clientEmail = explode("\n", $_clientEmail);
        $clientAdminNotify = explode(',',$cl['admin_notify_to']);
        $clientReportType = $cl['report_type'];
        $clientSpreadSheetID = $cl['spreadsheet_id'];
        $clientFilename = $cl['filename'];
        $clientTotalLeads = $cl['total_leads'];
        $clientOngoingLeads = $cl['ongoing_leads'];
        $limitleadsnotif = $cl['leads_amount_notification'];
        $clientURLcode = trim($cl['url_code']);
        $gtminstalled = ($cl['gtminstalled'] == 'T')?true:false;

        $clientLimitLeads = $cl['lp_limit_leads'];
        $clientLimitFreq = $cl['lp_limit_freq'];
        $clientLimitStartDate = ($cl['lp_limit_startdate'] == null || $cl['lp_limit_startdate'] == '0000-00-00')?'':$cl['lp_limit_startdate'];

        $clientPaymentTerm = $cl['paymentterm'];
        $clientPlatformfee = $cl['platformfee'];
        $clientMaxperTerm = $cl['lp_max_lead_month'];
        //$clientLimitEndDate = ($cl['lp_enddate'] == null || $cl['lp_enddate'] == '0000-00-00 00:00:00')?'':$cl['lp_enddate'];
        $clientLimitEndDate = ($cl['campaign_enddate'] == null || $cl['campaign_enddate'] == '0000-00-00 00:00:00' || trim($cl['campaign_enddate']) == '')?'':$cl['campaign_enddate'];
        $clientCostPerLead = $cl['cost_perlead'];
        $clientMinCostMonth = $cl['lp_min_cost_month'];
        $custStripeID = $cl['customer_payment_id'];
        $custStripeCardID = $cl['customer_card_id'];
        $custEmail = $cl['email'];
        $phoneenabled = $cl['phoneenabled'];
        $homeaddressenabled = $cl['homeaddressenabled'];

        $_lp_user_id = $cl['id'];
        $_company_id = $cl['company_id'];
        $_user_id = $cl['user_id'];
        $_leadspeek_api_id = $cl['leadspeek_api_id'];
        $campaignName = '';
        $campaignNameOri = '';
        $companyNameOri = $cl['company_name'];
        if (isset($cl['campaign_name']) && trim($cl['campaign_name']) != '') 
        {
            $campaignName = ' - ' . str_replace($_leadspeek_api_id,'',$cl['campaign_name']);
            $campaignNameOri = str_replace($_leadspeek_api_id,'',$cl['campaign_name']);
        }

        $company_name = str_replace($_leadspeek_api_id,'',$cl['company_name']) . $campaignName;

        $_last_lead_check = '';
        $_last_lead_added = '';

        $clientFilterState = explode(',',trim($cl['leadspeek_locator_state']));
        $clientFilterZipCode = explode(',',trim($cl['leadspeek_locator_zip']));
        $clientFilterRequire = explode(',',trim($cl['leadspeek_locator_require']));

        $req_fname = false;
        $req_lname = false;
        $req_mailingaddress = false;
        $req_phone = false;

        $companyRootID = (isset($cl['company_root_id']))?$cl['company_root_id']:'';
        $rootFeeCost = 0;
        $ori_rootFeeCost = 0;
        $platform_price_lead = 0;
        $ori_platform_price_lead = 0;

        /** GET PLATFORM MARGIN */
        $resultProcessGetPlatformMargin = $this->processGetPlatformMargin($cl);
        $platform_LeadspeekCostperlead = isset($resultProcessGetPlatformMargin['platform_LeadspeekCostperlead'])?$resultProcessGetPlatformMargin['platform_LeadspeekCostperlead']:0;
        $platform_LeadspeekMinCostMonth = isset($resultProcessGetPlatformMargin['platform_LeadspeekMinCostMonth'])?$resultProcessGetPlatformMargin['platform_LeadspeekMinCostMonth']:0;
        $platform_LeadspeekPlatformFee = isset($resultProcessGetPlatformMargin['platform_LeadspeekPlatformFee'])?$resultProcessGetPlatformMargin['platform_LeadspeekPlatformFee']:0;
        // info(['action' => 'processGetPlatformMargin','resultProcessGetPlatformMargin' => $resultProcessGetPlatformMargin,'clientCostPerLead' => $clientCostPerLead]);
        if(isset($resultProcessGetPlatformMargin['cost_perlead']) && $resultProcessGetPlatformMargin['cost_perlead'] != '' && $resultProcessGetPlatformMargin['cost_perlead'] != $clientCostPerLead) 
        {
            $cl['cost_perlead'] = $resultProcessGetPlatformMargin['cost_perlead'];
            $clientCostPerLead = $cl['cost_perlead'];
        }
        /** GET PLATFORM MARGIN */

        $organizationid = $cl['leadspeek_organizationid'];
        $campaignsid = $cl['leadspeek_campaignsid'];
        $price_lead = ($cl['cost_perlead'] != '')?$cl['cost_perlead']:0;
        $platform_price_lead = $platform_LeadspeekCostperlead;
        $ori_platform_price_lead = $platform_price_lead;
        $clientHidePhone = $cl['hide_phone'];
        // info([
        //     'price_lead' => $price_lead,
        //     'platform_LeadspeekCostperlead' => $platform_LeadspeekCostperlead,
        //     'platform_LeadspeekMinCostMonth' => $platform_LeadspeekMinCostMonth,
        //     'platform_LeadspeekPlatformFee' => $platform_LeadspeekPlatformFee,
        // ]);

        /** GET ROOT FEE PER LEADS FROM SUPER ROOT */
        $resultProcessGetRootFee = $this->processGetRootFee($cl);
        // info(['action' => 'processGetRootFee','resultProcessGetRootFee' => $resultProcessGetRootFee]);
        $rootFeeCost = isset($resultProcessGetRootFee['rootFeeCost']) ? $resultProcessGetRootFee['rootFeeCost'] : 0;
        $ori_rootFeeCost = $rootFeeCost;
        /** GET ROOT FEE PER LEADS FROM SUPER ROOT */

        $attachementlist = array();
        $attachementlink = array();
        $attachment = array();

        /** CHECK IF THERE END DATE ON WEEKLY OR MONTHLY PAYMENT TERM */
        if (($cl['leadspeek_type'] == "local" && $clientLimitEndDate == '') || $cl['leadspeek_type'] == "enhance" || $cl['leadspeek_type'] == "b2b") 
        {
            //$clientLimitEndDate = $cl['campaign_enddate'];
            $oneYearLater = date('Y-m-d', strtotime('+1 year', strtotime(date('Y-m-d'))));
            $clientLimitEndDate = ($cl['lp_enddate'] == null || $cl['lp_enddate'] == '0000-00-00 00:00:00' || $cl['lp_enddate'] == '' || $cl['leadspeek_type'] == "enhance" || $cl['leadspeek_type'] == "b2b")? $oneYearLater . ' 00:00:00':$cl['lp_enddate'];
        }

        if ($clientPaymentTerm != 'One Time' && $clientPaymentTerm != 'Prepaid' && $clientLimitEndDate != '') 
        {
            $EndDate = date('YmdHis',strtotime($clientLimitEndDate));
            if (date('YmdHis') > $EndDate) 
            {
                /** ACTIVATE CAMPAIGN SIMPLIFI */
                if ($organizationid != '' && $campaignsid != '') 
                {
                    $camp = $this->startPause_campaign($organizationid,$campaignsid,'stop');
                    if ($camp == true) 
                    {
                        /** PUT CLIENT TO ARCHIVE OR STOP */
                        $updateLeadspeekUser = LeadspeekUser::find($_lp_user_id);
                        $updateLeadspeekUser->active = 'F';
                        $updateLeadspeekUser->disabled = 'T';
                        $updateLeadspeekUser->active_user = 'F';
                        $updateLeadspeekUser->last_lead_pause = date('Y-m-d H:i:s');
                        $updateLeadspeekUser->save();
                        /** PUT CLIENT TO ARCHIVE OR STOP */

                        /** UPDATE USER END DATE */
                        $updateUser = User::find($_user_id);
                        $updateUser->lp_enddate = null;
                        $updateUser->lp_limit_startdate = null;
                        $updateUser->save();
                        /** UPDATE USER END DATE */

                        /** CHECK IF THE CONTRACTED ENDED IN THE MIDDLE OF WEEK */
                        $LastBillDate = date('YmdHis',strtotime($updateLeadspeekUser->start_billing_date));
                        $platformFee = 0;

                        $clientStartBilling = date('YmdHis',strtotime($updateLeadspeekUser->start_billing_date));
                        //$nextBillingDate = date('Ymd');
                        $nextBillingDate = date("YmdHis", strtotime("-1 days"));

                        /** CREATE INVOICE AND SENT IT */
                        $invoiceCreated = $this->createInvoice($_lp_user_id,$_company_id,$_user_id,$_leadspeek_api_id,$clientMaxperTerm,$clientCostPerLead,$platformFee,$clientPaymentTerm,$company_name,$clientEmail,$clientAdminNotify,$clientStartBilling,$nextBillingDate,$custStripeID,$custStripeCardID,$custEmail,$cl,$cl['clientowner']);
                        /** CREATE INVOICE AND SENT IT */

                        /** SEND EMAIL NOTIFICATION ONE TIME FINISHED*/
                        $TotalLimitLeads = '0';
                        $this->notificationStartStopLeads($clientAdminNotify,'Stopped (Campaign End on ' . date('m-d-Y',strtotime($clientLimitEndDate)) . ' - ' . date('m-d-Y') . ')',$company_name . ' #' . $_leadspeek_api_id,$clientLimitLeads,$clientLimitFreq,$TotalLimitLeads,$cl['clientowner'],true);
                        /** SEND EMAIL NOTIFICATION ONE TIME FINISHED*/
                        continue;
                    }
                    else
                    {
                        /** SEND EMAIL TO ME */
                        $details = [
                            'errormsg'  => 'Simpli.Fi Error Leadspeek ID :' . $_leadspeek_api_id. '<br/>',
                        ];
                        $from = [
                            'address' => 'noreply@sitesettingsapi.com',
                            'name' => 'support',
                            'replyto' => 'noreply@sitesettingsapi.com',
                        ];
                        $this->send_email(array('serverlogs@sitesettingsapi.com'),'Start Pause Campaign Failed (INTERNAL - Webhook-ProcessDataMatch - L512) #' .$_leadspeek_api_id,$details,array(),'emails.tryseramatcherrorlog',$from,'',true);
                        /** SEND EMAIL TO ME */
                        continue;
                    }
                }
                else if ($cl['leadspeek_type'] == "local") 
                {
                    /** PUT CLIENT TO ARCHIVE OR STOP */
                    $updateLeadspeekUser = LeadspeekUser::find($_lp_user_id);
                    $updateLeadspeekUser->active = 'F';
                    $updateLeadspeekUser->disabled = 'T';
                    $updateLeadspeekUser->active_user = 'F';
                    $updateLeadspeekUser->last_lead_pause = date('Y-m-d H:i:s');
                    $updateLeadspeekUser->save();
                    /** PUT CLIENT TO ARCHIVE OR STOP */

                    /** UPDATE USER END DATE */
                    $updateUser = User::find($_user_id);
                    $updateUser->lp_enddate = null;
                    $updateUser->lp_limit_startdate = null;
                    $updateUser->save();
                    /** UPDATE USER END DATE */

                    /** CHECK IF THE CONTRACTED ENDED IN THE MIDDLE OF WEEK */
                    $LastBillDate = date('YmdHis',strtotime($updateLeadspeekUser->start_billing_date));
                    $platformFee = 0;

                    $clientStartBilling = date('YmdHis',strtotime($updateLeadspeekUser->start_billing_date));
                    //$nextBillingDate = date('Ymd');
                    $nextBillingDate = date("YmdHis", strtotime("-1 days"));

                    /** CREATE INVOICE AND SENT IT */
                    $invoiceCreated = $this->createInvoice($_lp_user_id,$_company_id,$_user_id,$_leadspeek_api_id,$clientMaxperTerm,$clientCostPerLead,$platformFee,$clientPaymentTerm,$company_name,$clientEmail,$clientAdminNotify,$clientStartBilling,$nextBillingDate,$custStripeID,$custStripeCardID,$custEmail,$cl,$cl['clientowner']);
                    /** CREATE INVOICE AND SENT IT */

                    /** SEND EMAIL NOTIFICATION ONE TIME FINISHED*/
                    $TotalLimitLeads = '0';
                    $this->notificationStartStopLeads($clientAdminNotify,'Stopped (Campaign End on ' . date('m-d-Y',strtotime($clientLimitEndDate)) . ' - ' . date('m-d-Y') . ')',$company_name . ' #' . $_leadspeek_api_id,$clientLimitLeads,$clientLimitFreq,$TotalLimitLeads,$cl['clientowner'],true);
                    /** SEND EMAIL NOTIFICATION ONE TIME FINISHED*/
                    continue;
                }
                /** ACTIVATE CAMPAIGN SIMPLIFI */
            }
        } 
        else if ($clientPaymentTerm == 'Prepaid') 
        {
            /** CHECK IF LEADS EMPTY */
            $dataContinualProgressIsNotDoneExists = Topup::where('leadspeek_api_id', $_leadspeek_api_id)
                                                            ->whereIn('topup_status', ['progress', 'queue'])
                                                            ->exists();
            
            if(!$dataContinualProgressIsNotDoneExists) 
            {
                /** PUT CLIENT TO ARCHIVE OR STOP */
                $updateLeadspeekUser = LeadspeekUser::find($_lp_user_id);
                $updateLeadspeekUser->active = 'F';
                $updateLeadspeekUser->disabled = 'T';
                $updateLeadspeekUser->active_user = 'F';
                $updateLeadspeekUser->last_lead_pause = date('Y-m-d H:i:s');
                $updateLeadspeekUser->save();
                /** PUT CLIENT TO ARCHIVE OR STOP */

                /** UPDATE USER END DATE */
                $updateUser = User::find($_user_id);
                $updateUser->lp_enddate = null;
                $updateUser->lp_limit_startdate = null;
                $updateUser->save();
                /** UPDATE USER END DATE */

                /** UPDATE STOP CONTINUAL */
                $this->stop_continual_topup($_lp_user_id);
                /** UPDATE STOP CONTINUAL */
                
                /** SEND EMAIL TO ME */
                $details = [
                    'errormsg'  => 'PREPAID Stopped No TOP UP EXIST (L716) Leadspeek ID :' . $_leadspeek_api_id. '<br/>',
                ];
                $from = [
                    'address' => 'noreply@sitesettingsapi.com',
                    'name' => 'support',
                    'replyto' => 'noreply@sitesettingsapi.com',
                ];
                $this->send_email(array('serverlogs@sitesettingsapi.com'),'PREPAID STOPPED #' .$_leadspeek_api_id,$details,array(),'emails.tryseramatcherrorlog',$from,'',true);
                /** SEND EMAIL TO ME */
                continue;
            }
            /** CHECK IF LEADS EMPTY */
        }
        /** CHECK IF THERE END DATE ON WEEKLY OR MONTHLY PAYMENT TERM */

        $leadcount = 0;

        /** PROCESS MATCHED DATA */
        if (count($matches) > 0) 
        {
            /** CHECK IS ADVANCE */
            $is_advance = false;
            if ($leadspeek_type == 'enhance' || $leadspeek_type == 'b2b') 
            {
                $campaign_advance_information = LeadspeekUser::select('advance_information')->where('leadspeek_api_id',$leadspeek_api_id)->first();
                if (isset($campaign_advance_information->advance_information) && !empty($campaign_advance_information->advance_information) && trim($campaign_advance_information->advance_information) != '') 
                {
                    $is_advance = true;
                }
            }
            /** CHECK IS ADVANCE */

            /** CHECK IS LOCAL CUSTOM */
            $is_local_custom = [
                'is_advance' => false,
                'is_b2b' => false,
                'advance' => [],
                'b2b' => [],
            ];
            $campaign_information_type_local = 'basic';
            if ($leadspeek_type == 'local') {
                    $campaign = LeadspeekUser::select('paymentterm','advance_information','campaign_information_type_local')->where('leadspeek_api_id',$leadspeek_api_id)->first();

                    if (strtolower($campaign->paymentterm) == 'prepaid') {
                        $topup = Topup::select('advance_information','campaign_information_type_local')
                                ->where('leadspeek_api_id',$leadspeek_api_id)
                                ->where('leadspeek_type','local')
                                ->where('topup_status', 'progress')
                                ->orderBy('id','ASC')
                                ->first();
                                
                        $campaign_information_type_local = !empty($topup->campaign_information_type_local) ? explode(',',$topup->campaign_information_type_local) : [];
                        $advance_information = !empty($topup->advance_information) ? json_decode($topup->advance_information) : '';

                        // if ((in_array('advanced',$campaign_information_type_local) || in_array('b2b',$campaign_information_type_local))) {
                        if (in_array('advanced',$campaign_information_type_local)) {
                            $is_local_custom['is_advance'] = in_array('advanced',$campaign_information_type_local) ? true : false;
                            $is_local_custom['is_b2b'] = false;
                            $is_local_custom['advance'] = isset($advance_information->advance) ? $advance_information->advance : [];
                            $is_local_custom['b2b'] = [];
                        }

                    }else {
                        $campaign_information_type_local = !empty($campaign->campaign_information_type_local) ? explode(',',$campaign->campaign_information_type_local) : [];
                        $advance_information = !empty($campaign->advance_information) ? json_decode($campaign->advance_information) : '';

                        if (in_array('advanced',$campaign_information_type_local)) {
                            $is_local_custom['is_advance'] = in_array('advanced',$campaign_information_type_local) ? true : false;
                            $is_local_custom['is_b2b'] = false;
                            $is_local_custom['advance'] = isset($advance_information->advance) ? $advance_information->advance : [];
                            $is_local_custom['b2b'] = [];
                        }
                    }
            }
            
            /** CHECK IS LOCAL CUSTOM */

            /** PROCESSING BASED ON REPORT TYPE */
            if ($clientReportType == 'GoogleSheet')
            {
                $content = array();

                /** CHECK FOR LIMIT LEADS */
                if ($clientLimitLeads > 0) 
                {
                    // untuk mengatahui apakah limit lead days hari ini sudah terpenuhi atau belom, jika sudah tolak jangan lanjut kebawah
                    $resultCheckLimitLeadsBeforeInsertLeadspeekReport = $this->checkLimitLeadsBeforeInsertLeadspeekReport($cl);
                    // info(['action' => 'checkLimitLeadsBeforeInsertLeadspeekReport', 'resultCheckLimitLeadsBeforeInsertLeadspeekReport' => $resultCheckLimitLeadsBeforeInsertLeadspeekReport]);
                    if(!$resultCheckLimitLeadsBeforeInsertLeadspeekReport)
                    {
                        continue;
                    }
                }
                /** CHECK FOR LIMIT LEADS */

                /* INSERT LEADSPEEK REPORT */
                $resultProcessInsertLeadspeekReport = $this->processInsertLeadspeekReport($cl, $matches, $is_advance, $price_lead, $platform_price_lead, $rootFeeCost,$is_local_custom);
                // info(['action' => 'processInsertLeadspeekReport','resultProcessInsertLeadspeekReport' => $resultProcessInsertLeadspeekReport]);
                $content = isset($resultProcessInsertLeadspeekReport['content']) ? $resultProcessInsertLeadspeekReport['content'] : [];
                $leadspeekReportID = isset($resultProcessInsertLeadspeekReport['leadspeekReportID']) ? $resultProcessInsertLeadspeekReport['leadspeekReportID'] : "";
                /* INSERT LEADSPEEK REPORT */

                /** CHECK FOR LIMIT LEADS */
                if ($clientLimitLeads > 0)
                {
                    // untuk mengatahui apakah limit lead days hari ini sudah terpenuhi atau belom, jika sudah ubah status campaign dari run to paused on run
                    $this->checkLimitLeadsAfterInsertLeadspeekReport($cl);
                }
                /** CHECK FOR LIMIT LEADS */

                /** CHECK IF PREPAID */
                // process auto topup prepaid
                $lockKey = 'topup_process_lock' . $_leadspeek_api_id;
                if ($clientPaymentTerm == 'Prepaid') 
                {
                    while (!$this->acquireLock($lockKey)) 
                    {
                        Log::info("Another top-up process is running. Waiting to acquire lock.");
                        sleep(1); // Wait before trying again
                    }

                    $this->processAutoTopupPrepaid($cl, $ori_platform_price_lead, $ori_rootFeeCost);

                    $this->releaseLock($lockKey);
                }
                /** CHECK IF PREPAID */

                /* INSERT SPREADSHEET */
                $this->processSendSpreadsheetJob($cl, $content, $md5param);
                /* INSERT SPREADSHEET */
            }
            /** PROCESSING BASED ON REPORT TYPE */

            /** INSERT SENDGRID */
            $resultProcessSendGrid = $this->processSendgrid($leadspeek_api_id,$leadspeek_type,$md5param,$matches,$cl,$campaignName);
            // info(['action' => 'processSendgrid','resultProcessSendGrid' => $resultProcessSendGrid]);
            if(isset($resultProcessSendGrid['executionTimeList']) && is_array($resultProcessSendGrid['executionTimeList']) && count($resultProcessSendGrid['executionTimeList']) > 0) 
            {
                $executionTimeList['sendgrid'] = $resultProcessSendGrid['executionTimeList'];
            }
            /** INSERT SENDGRID */

            /** INSERT GOHIGHLEVEL */
            $resultProcessGHL = $this->processGHL($leadspeek_api_id,$leadspeek_type,$md5param,$matches,$cl,$campaignName);
            // info(['action' => 'processGHL','resultProcessGHL' => $resultProcessGHL]);
            if(isset($resultProcessGHL['executionTimeList']) && is_array($resultProcessGHL['executionTimeList']) && count($resultProcessGHL['executionTimeList']) > 0) 
            {
                $executionTimeList['gohighlevel'] = $resultProcessGHL['executionTimeList'];
            }
            /** INSERT GOHIGHLEVEL */

            /* INSERT MAILBOXPOWER */
            $resultProcessMailBoxPower = $this->processMailBoxPower($leadspeek_api_id,$leadspeek_type,$md5param,$matches,$cl,$campaignName);
            // info(['action' => 'processMailBoxPower','resultProcessMailBoxPower' => $resultProcessMailBoxPower]);
            if(isset($resultProcessMailBoxPower['executionTimeList']) && is_array($resultProcessMailBoxPower['executionTimeList']) && count($resultProcessMailBoxPower['executionTimeList']) > 0) 
            {
                $executionTimeList['mailboxpower'] = $resultProcessMailBoxPower['executionTimeList'];
            }
            /* INSERT MAILBOXPOWER */

            /* INSERT CLICK FUNNELS */
            $resultProcessClickFunnels = $this->processClickFunnels($leadspeek_api_id,$leadspeek_type,$md5param,$matches,$cl,$campaignName);
            // info(['action' => 'processClickFunnels','resultProcessClickFunnels' => $resultProcessClickFunnels]);
            if(isset($resultProcessClickFunnels['executionTimeList']) && is_array($resultProcessClickFunnels['executionTimeList']) && count($resultProcessClickFunnels['executionTimeList']) > 0) 
            {
                $executionTimeList['clickfunnels'] = $resultProcessClickFunnels['executionTimeList'];
            }
            /* INSERT CLICK FUNNELS */

            /* INSERT KARTRA */
            $resultProcessKartra = $this->processKartra($leadspeek_api_id,$leadspeek_type,$md5param,$matches,$cl,$campaignName);
            // info(['action' => 'processKartra','resultProcessKartra' => $resultProcessKartra]);
            if(isset($resultProcessKartra['executionTimeList']) && is_array($resultProcessKartra['executionTimeList']) && count($resultProcessKartra['executionTimeList']) > 0) 
            {
                $executionTimeList['kartra'] = $resultProcessKartra['executionTimeList'];
            }
            /* INSERT KARTRA */

            /* INSERT ZAPIER */
            $resultProcessZapier = $this->processZapier($leadspeek_api_id,$leadspeek_type,$md5param,$matches,$cl,$campaignName,$is_local_custom);
            // info(['action' => 'processZapier','resultProcessZapier' => $resultProcessZapier]);
            if(isset($resultProcessZapier['executionTimeList']) && is_array($resultProcessZapier['executionTimeList']) && count($resultProcessZapier['executionTimeList']) > 0) 
            {
                $executionTimeList['zapier'] = $resultProcessZapier['executionTimeList'];
            }
            /* INSERT ZAPIER */

            /* INSERT AGENCYZOOM */
            $resultProcessAgencyZoom = $this->processAgencyZoom($leadspeek_api_id,$leadspeek_type,$md5param,$matches,$cl,$campaignName);
            // info(['action' => 'processAgencyZoom','resultProcessAgencyZoom' => $resultProcessAgencyZoom]);
            if(isset($resultProcessAgencyZoom['executionTimeList']) && is_array($resultProcessAgencyZoom['executionTimeList']) && count($resultProcessAgencyZoom['executionTimeList']) > 0) 
            {
                $executionTimeList['agencyzoom'] = $resultProcessAgencyZoom['executionTimeList'];
            }
            /* INSERT AGENCYZOOM */

            /* INSERT SENDJIM */
            $resultProcessSendJim = $this->processSendJim($leadspeek_api_id,$leadspeek_type,$md5param,$matches,$cl,$campaignName);
            if(isset($resultProcessSendJim['executionTimeList']) && is_array($resultProcessSendJim['executionTimeList']) && count($resultProcessSendJim['executionTimeList']) > 0) 
            {
                $executionTimeList['sendjim'] = $resultProcessSendJim['executionTimeList'];
            }
            /* INSERT SENDJIM */
        }
        /** PROCESS MATCHED DATA */
    }

    /** RELEASE LOCK PROCESS FOR PREPAID */
    if (trim($paymentTerm) != "" && trim($paymentTerm) == "Prepaid") 
    {
        Log::info("RELEASE PREPAID LOCK Campaign ID : #" . $leadspeek_api_id);
        $this->releaseLock('initPrepaidStart' . $leadspeek_api_id);
    }
    /** RELEASE LOCK PROCESS FOR PREPAID */

    return [
        'leadspeekReportID' => $leadspeekReportID,
        'executionTimeList' => $executionTimeList,
    ];
}


public function filterEmailInLeadspeekReport($leadspeek_api_id = '', $email = '', $personID = '', $clientCompanyID = '', $leadspeektype = '', $md5Email = '') 
{
    /** CHECK REGARDING RE-IDENTIFICATION FOR LEADS */
    $salt = substr(hash('sha256', env('APP_KEY')), 0, 16);
    $reidentification = 'never';
    $notOnReidentification = true;
    $applyreidentificationall = false;

    // $chkreidentification = LeadspeekUser::select('reidentification_type','applyreidentificationall')
    //                             ->where('leadspeek_api_id','=',$leadspeek_api_id)
    //                             ->first();

    $_cacheKey = "reidentification_" . $leadspeek_api_id;
    $chkreidentification = $this->cacheQueryResult($_cacheKey,30,function () use ($leadspeek_api_id) {
        return LeadspeekUser::select('reidentification_type','applyreidentificationall')
                                ->where('leadspeek_api_id','=',$leadspeek_api_id)
                                ->first();
    });

    //if (count($chkreidentification) > 0) {
    if ($chkreidentification) {
        $reidentification = $chkreidentification->reidentification_type;
        $applyreidentificationall = ($chkreidentification->applyreidentificationall == 'T')?true:false;
    }

    /** CHECK IF DATA ALREADY IN THAT CAMPAIGN OR NOT */
    $chkExistOnCampaign = null;
    $email = Encrypter::encrypt($email);

    if ($applyreidentificationall) {
        // $chkExistOnCampaign = LeadspeekReport::select('leadspeek_reports.id','leadspeek_reports.clickdate','leadspeek_reports.created_at')
        //                         ->join('leadspeek_users','leadspeek_reports.lp_user_id','=','leadspeek_users.id')
        //                         ->where('leadspeek_reports.company_id','=',$clientCompanyID)
        //                         ->where('leadspeek_users.applyreidentificationall','=','T')
        //                         ->where('leadspeek_users.archived','=','F')
        //                         //->where(DB::raw("CONVERT(AES_DECRYPT(FROM_bASE64(`leadspeek_reports`.`email`), '" . $salt . "') USING utf8mb4)"),'=',$email)
        //                         ->where('leadspeek_reports.email','=',$email)
        //                         // ->orderBy(DB::raw("DATE_FORMAT(leadspeek_reports.clickdate,'%Y%m%d')"),'DESC')
        //                         ->orderBy('leadspeek_reports.clickdate','DESC')	
        //                         //->limit(1)
        //                         //->get();
        //                         ->first();
        
        $_cacheKey = "chkExistOnCampaignFilterEmail1_" . $email . '_' . $clientCompanyID;
        $chkExistOnCampaign = $this->cacheGetResult($_cacheKey);
        if(is_null($chkExistOnCampaign)) {
            $chkExistOnCampaign = LeadspeekReport::select('leadspeek_reports.id','leadspeek_reports.clickdate','leadspeek_reports.created_at')
                                                ->join('leadspeek_users','leadspeek_reports.lp_user_id','=','leadspeek_users.id')
                                                ->where('leadspeek_users.applyreidentificationall','=','T')
                                                ->where('leadspeek_users.archived','=','F')
                                                ->where('leadspeek_reports.company_id','=',$clientCompanyID)
                                                ->where('leadspeek_reports.email','=',$email)
                                                ->orderBy('leadspeek_reports.clickdate','DESC')	
                                                ->first();
            if(!empty($chkExistOnCampaign)) {
                $chkExistOnCampaign = $this->cacheQueryResult($_cacheKey,100,function () use ($chkExistOnCampaign) {
                    return $chkExistOnCampaign; 
                });
            } else {
                if($this->cacheHasResult($_cacheKey)) {
                    $this->cacheForget($_cacheKey);
                }
            }
        }
    }else{
        // $chkExistOnCampaign = LeadspeekReport::select('id','clickdate','created_at')
        //                         ->where('leadspeek_api_id','=',$leadspeek_api_id)
        //                         //->where(DB::raw("CONVERT(AES_DECRYPT(FROM_bASE64(`email`), '" . $salt . "') USING utf8mb4)"),'=',$email)
        //                         ->where('email','=',$email)
        //                         // ->orderBy(DB::raw("DATE_FORMAT(clickdate,'%Y%m%d')"),'DESC')
        //                         ->orderBy('clickdate','DESC')	
        //                         //->limit(1)
        //                         //->get();
        //                         ->first();

        $_cacheKey = "chkExistOnCampaignFilterEmail2_" . $email . '_' . $leadspeek_api_id;
        $chkExistOnCampaign = $this->cacheGetResult($_cacheKey);
        if(is_null($chkExistOnCampaign)) {
            $chkExistOnCampaign = LeadspeekReport::select('id','clickdate','created_at')
                                                ->where('leadspeek_api_id','=',$leadspeek_api_id)
                                                ->where('email','=',$email)
                                                ->orderBy('clickdate','DESC')	
                                                ->first();
            if(!empty($chkExistOnCampaign)) {
                $chkExistOnCampaign = $this->cacheQueryResult($_cacheKey,100,function () use ($chkExistOnCampaign) {
                    return $chkExistOnCampaign;
                });
            } else {
                if($this->cacheHasResult($_cacheKey)) {
                    $this->cacheForget($_cacheKey);
                }
            }
        }
    }

    //if (count($chkExistOnCampaign) > 0 && $reidentification != 'never') {
   //if (count($chkExistOnCampaign) > 0) {
   if ($chkExistOnCampaign) {
        //$clickDate = date('Ymd',strtotime($chkExistOnCampaign[0]['clickdate']));
        $clickDate = date('Ymd',strtotime($chkExistOnCampaign->clickdate));
        $date1=date_create(date('Ymd'));
        $date2=date_create($clickDate);
        $diff=date_diff($date1,$date2);

        if ($reidentification == 'never') {
            $notOnReidentification = false;
        }else if ($diff->format("%a") <= 7 && $reidentification == '1 week') {
            $notOnReidentification = false;
        }else if ($diff->format("%a") <= 30 && $reidentification == '1 month') {
            $notOnReidentification = false;
        }else if ($diff->format("%a") <= 90 && $reidentification == '3 months') {
            $notOnReidentification = false;
        }else if ($diff->format("%a") <= 120 && $reidentification == '6 months') {
            $notOnReidentification = false;
        }else if ($diff->format("%a") <= 360 && $reidentification == '1 year') {
            $notOnReidentification = false;
        }
    }

    if(!$notOnReidentification) {
        /* WRITE UPSER FAILED LEAD RECORD */
        $this->UpsertFailedLeadRecord([
            'function' => 'filterEmailInLeadspeekReport',
            'type' => 'blocked',
            'blocked_type' => 'reidentification',
            'description' => "blocked in reidentification type = $reidentification function filterEmailInLeadspeekReport",
            'leadspeek_api_id' => $leadspeek_api_id,
            'email_encrypt' => "$md5Email|$email",
            'leadspeek_type' => $leadspeektype,
        ]);
        /* WRITE UPSER FAILED LEAD RECORD */

        /* DELETE PERSON, PERSON EMAIL, PERSON PHONE, PERSON ADDRESS */
        // $person = Person::where('id',$personID)->delete();
        // $personEmail = PersonEmail::where('person_id',$personID)->delete();
        // $personPhone = PersonPhone::where('person_id',$personID)->delete();
        // $personAddress = PersonAddress::where('person_id',$personID)->delete();
        // /* DELETE PERSON, PERSON EMAIL, PERSON PHONE, PERSON ADDRESS */
    }

    return $notOnReidentification;
}