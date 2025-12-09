<?php

public function getclient(Request $request) {
    $view = (isset($request->view))?$request->view:'';
    $CompanyID = (isset($request->CompanyID))?$request->CompanyID:'';
    if ($CompanyID == '') {
        $CompanyID = (isset($request->companyID))?$request->companyID:'';
    }
    $PerPage = (isset($request->PerPage)) ? (int) $request->PerPage : 10;
    $Page = (isset($request->Page))?$request->Page:'';
    $leadspeekID = (isset($request->leadspeekID))?$request->leadspeekID:'';
    $leadspeekType = (isset($request->leadspeekType))?$request->leadspeekType:'';
    $groupCompanyID = (isset($request->groupCompanyID))?$request->groupCompanyID:'';
    $sortby = (isset($request->SortBy) && $request->SortBy != '0')?$request->SortBy:'';
    $order = (isset($request->OrderBy) && $request->OrderBy != '0')?$request->OrderBy:'';
    $searchKey = (isset($request->searchKey))?$request->searchKey:'';
    $CampaignStatus = (isset($request->CampaignStatus))?$request->CampaignStatus:'';
    $clientID = (isset($request->clientID))?$request->clientID:'';
    info('', [
        'view' => $view,
        'CompanyID' => $CompanyID,
        'PerPage' => $PerPage,
        'Page' => $Page,
        'leadspeekID' => $leadspeekID,
        'leadspeekType' => $leadspeekType,
        'groupCompanyID' => $groupCompanyID,
        'sortby' => $sortby,
        'order' => $order,
        'searchKey' => $searchKey,
        'CampaignStatus' => $CampaignStatus,
        'clientID' => $ClientID
    ]);

    //get list queue id for enhance
    $yesterday = Carbon::yesterday()->toDateString();

    $latestListQueueSubquery = DB::table('lead_list_queue')
        ->select('leadspeek_api_id', 'list_queue_id')
        ->whereDate('created_at', '=', $yesterday)
        ->latest('created_at')
        ->groupBy('leadspeek_api_id');

    $client = LeadspeekUser::select(
            'leadspeek_users.id','leadspeek_users.report_type','leadspeek_users.leadspeek_type','leadspeek_users.leadspeek_locator_zip','leadspeek_users.leadspeek_locator_desc','leadspeek_users.leadspeek_locator_keyword','leadspeek_locator_keyword_contextual','leadspeek_users.leadspeek_locator_state','leadspeek_users.leadspeek_locator_state_exclude','leadspeek_users.leadspeek_locator_state_type','leadspeek_users.leadspeek_locator_state_simplifi as leadspeek_locator_state_external','leadspeek_users.gtminstalled',
            'leadspeek_users.leadspeek_locator_city','leadspeek_users.leadspeek_locator_city_simplifi as leadspeek_locator_city_external','leadspeek_users.leadspeek_locator_require','leadspeek_users.hide_phone','leadspeek_users.national_targeting','leadspeek_users.location_target','leadspeek_users.start_billing_date','leadspeek_users.lp_invoice_date','leadspeek_users.phoneenabled','leadspeek_users.homeaddressenabled','leadspeek_users.reidentification_type','leadspeek_users.require_email','leadspeek_users.advance_information','leadspeek_users.campaign_information_type','leadspeek_users.campaign_information_type_local',
            'leadspeek_users.file_url','leadspeek_users.report_sent_to','leadspeek_users.admin_notify_to','leadspeek_users.leadspeek_api_id','leadspeek_users.active','leadspeek_users.disabled','leadspeek_users.active_user','leadspeek_users.leadspeek_organizationid as clientorganizationid','leadspeek_users.leadspeek_campaignsid as clientcampaignsid',
            'leadspeek_users.leads_amount_notification','leadspeek_users.total_leads',DB::raw('DATE_FORMAT(leadspeek_users.last_lead_added,"%m-%d-%Y") as last_lead_added'),'leadspeek_users.spreadsheet_id','leadspeek_users.filename','users.customer_payment_id','users.customer_card_id','users.company_parent','users.company_root_id',
            'leadspeek_users.ongoing_leads',DB::raw('DATE_FORMAT(leadspeek_users.last_lead_check,"%d-%m-%Y") as last_lead_check'),'leadspeek_users.lifetime_cost','leadspeek_users.cost_perlead','leadspeek_users.lp_max_lead_month','leadspeek_users.lp_min_cost_month','leadspeek_users.paymentterm','leadspeek_users.continual_buy_options','leadspeek_users.topupoptions','leadspeek_users.leadsbuy','leadspeek_users.stopcontinual','leadspeek_users.platformfee','leadspeek_users.lp_limit_startdate','leadspeek_users.lp_enddate',
            'leadspeek_users.report_frequency_id','leadspeek_users.lp_limit_leads','leadspeek_users.enable_minimum_limit_leads','leadspeek_users.minimum_limit_leads','leadspeek_users.lp_limit_freq','users.id as user_id','users.name','users.email','users.phonenum','companies.company_name','companies.id as company_id','leadspeek_users.group_company_id','leadspeek_users.campaign_name','leadspeek_users.campaign_startdate','leadspeek_users.campaign_enddate','leadspeek_users.ori_campaign_startdate','leadspeek_users.ori_campaign_enddate','leadspeek_users.url_code','leadspeek_users.url_code_thankyou','leadspeek_users.url_code_ads',
            'leadspeek_users.embeddedcode_crawl','leadspeek_users.embedded_status','leadspeek_users.questionnaire_answers','leadspeek_users.trysera','leadspeek_users.trysera','leadspeek_users.archived','leadspeek_users.timezone','leadspeek_users.applyreidentificationall',
            'users.paymentterm as m_paymentterm','users.platformfee as m_platformfee','users.lp_max_lead_month as m_lp_max_lead_month','users.lp_min_cost_month as m_lp_min_cost_month','users.cost_perlead as m_cost_perlead','users.lp_limit_leads as m_lp_limit_leads','users.lp_limit_freq as m_lp_limit_freq','users.lp_limit_startdate as m_lp_limit_startdate','users.lp_enddate as m_lp_enddate','leadspeek_users.sendgrid_is_active as sendgrid_is_active', 'leadspeek_users.googlesheet_is_active as googlesheet_is_active','leadspeek_users.sendgrid_action as sendgrid_action','leadspeek_users.sendgrid_list as sendgrid_list',
            'leadspeek_users.ghl_is_active','leadspeek_users.mbp_is_active','leadspeek_users.clickfunnels_is_active',
            'leadspeek_users.sendjim_is_active as sendjim_is_active','leadspeek_users.sendjim_tags as sendjim_tags',
            'leadspeek_users.sendjim_quicksend_is_active as sendjim_quicksend_is_active','leadspeek_users.sendjim_quicksend_templates as sendjim_quicksend_templates',
            'users.payment_status', 'leadspeek_users.created_at',
            'latest_list_queue.list_queue_id','leadspeek_users.frequency_capping_impressions','leadspeek_users.frequency_capping_hours','leadspeek_users.max_bid','leadspeek_users.monthly_budget','leadspeek_users.daily_budget','leadspeek_users.goal_type','leadspeek_users.goal_value','leadspeek_users.device_type','leadspeek_users.simplifi_selected_campaign','leadspeek_users.simplifi_selected_audience','leadspeek_users.agency_markup','leadspeek_users.simplifi_selected_media','leadspeek_users.audience_status','leadspeek_users.ads_upload_status','leadspeek_users.media_type','leadspeek_users.destination_url'
        )
        ->join('users','leadspeek_users.user_id','=','users.id')
        ->join('companies','users.company_id','=','companies.id')
        ->leftjoin('companies_integration_settings','leadspeek_users.company_id','=','companies_integration_settings.company_id')
        ->leftJoinSub(
            $latestListQueueSubquery,
            'latest_list_queue',
            'leadspeek_users.leadspeek_api_id',
            '=',
            'latest_list_queue.leadspeek_api_id'
        )
        //->leftjoin('company_groups','leadspeek_users.group_company_id','=','company_groups.id')
        ->where('leadspeek_users.module_id','=', $this->_moduleID)
        ->where('leadspeek_users.company_id','=',$CompanyID)
        ->where('leadspeek_users.archived','=','F')
        ->where('users.active','=','T');
        //->where('companies_integration_settings.integration_slug','=','sendgrid');
    
    if(isset($request->clientID) && $request->clientID != '' && $request->clientID != '0' && $request->clientID != 'view_all') {
        //$client->where('leadspeek_users.user_id','=',$request->clientID);
        $client->where('leadspeek_users.id','=',$request->clientID);
    }else if($leadspeekID != '') {
        $client->where('leadspeek_users.id','=',$leadspeekID);
    }

    if(trim($leadspeekType) != '' && trim($leadspeekType) != 'all') {
        $client->where('leadspeek_users.leadspeek_type','=',trim($leadspeekType));
    }

    if(trim($groupCompanyID) != '' && trim($groupCompanyID) != 'all') {
        // $client->where('leadspeek_users.group_company_id','=',trim($groupCompanyID));
        $client->where('leadspeek_users.user_id','=',trim($groupCompanyID));
    }

    if (trim($searchKey) != '') {
        $salt = substr(hash('sha256', env('APP_KEY')), 0, 16);
        
        $client->where(function($query) use ($searchKey,$salt) {
            $query->orWhere(DB::raw("CONVERT(AES_DECRYPT(FROM_bASE64(companies.company_name), '" . $salt . "') USING utf8mb4)"),'like','%' . $searchKey . '%')
            ->orWhere(DB::raw("CONVERT(AES_DECRYPT(FROM_bASE64(leadspeek_users.campaign_name), '" . $salt . "') USING utf8mb4)"),'like','%' . $searchKey . '%')
            ->orWhere('leadspeek_users.leadspeek_api_id','like','%' . $searchKey . '%')
            ->orWhere('latest_list_queue.list_queue_id', 'like', '%' . $searchKey . '%'); 
        });
    }

    
    if (trim($order) != '') {
        if (trim($order) == 'descending') {
            $order = "DESC";
        }else{
            $order = "ASC";
        }
    }

    if($CampaignStatus == 'all'){
        $client->where(function($query){
            $query->where(function($query) {
                        $query->where('leadspeek_users.active_user', 'T')
                        ->orWhere('leadspeek_users.active_user', 'F');
                    })
                    ->where(function($query) {
                        $query->where('leadspeek_users.active', 'T')
                            ->orWhere('leadspeek_users.active', 'F');
                    })
                    ->where(function($query) {
                        $query->where('leadspeek_users.disabled', 'T')
                            ->orWhere('leadspeek_users.disabled', 'F');
                    });
        });
    } else if ($CampaignStatus == 'play'){
        $client->where(function($query){
            $query->where(function($subquery){
                $subquery->where('leadspeek_users.active_user', 'T')
                    ->where('leadspeek_users.disabled', 'F')
                    ->where('leadspeek_users.active', 'T');
            })->orWhere(function($subquery){
                $subquery->where('leadspeek_users.active_user', 'T')
                    ->where('leadspeek_users.disabled', 'F')
                    ->where('leadspeek_users.active', 'F');
            });
        });
    } else if ($CampaignStatus == 'paused'){
        $client->where(function($query){
            $query->where('leadspeek_users.active', 'F')
                    ->where('leadspeek_users.active_user', 'T')
                    ->where('leadspeek_users.disabled', 'T');
        });
    } else if ($CampaignStatus == 'stop'){
        $client->where(function($query){
            $query->where('leadspeek_users.active', 'F')
                    ->where('leadspeek_users.active_user', 'F')
                    ->where('leadspeek_users.disabled', 'T');
        });
    }
    
    if (trim($sortby) != '') {
        if (trim($sortby) == "company_name") {
            $client->orderByEncrypted('companies.company_name',$order);
        }else if (trim($sortby) == "campaign_name") {
            $client->orderByEncrypted('leadspeek_users.campaign_name',$order);
        }else if (trim($sortby) == "leadspeek_api_id") {
            $client->orderBy(DB::raw('CAST(leadspeek_users.leadspeek_api_id AS DECIMAL)'),$order);
        }else if (trim($sortby) == "total_leads") {
            $client->orderBy(DB::raw('CAST(leadspeek_users.total_leads AS DECIMAL)'),$order);
        }else if (trim($sortby) == "last_lead_added") {
            $client->orderBy(DB::raw('CAST(leadspeek_users.last_lead_added AS DATETIME)'),$order);
        }
    }else{
        $client->orderBy(DB::raw('CAST(leadspeek_users.last_lead_added AS DATETIME)'),'DESC');
    }

    if ($Page == '') { 
        $client = $client->get();
    }else{
        $client = $client->paginate($PerPage, ['*'], 'page', $Page);
    }
    
    $client->map(function ($item){
        if(!empty($item->sendgrid_action))
        {
            $item->sendgrid_action = explode(',', $item->sendgrid_action);
            return $item;
        }
        return array();
        
    });

    $client->map(function ($item1){
        if(!empty($item1->sendgrid_list))
        {
            $item1->sendgrid_list = explode(',', $item1->sendgrid_list);
            return $item1;
        }
        return array();            
    });

    /*
        $client = $client->orderByDesc('leadspeek_users.created_at')
                    ->get();
    */
    if((isset($request->clientID) && $request->clientID != '' && $request->clientID != '0' && $request->clientID != 'view_all') &&  (trim($leadspeekType) != '' && trim($leadspeekType) == 'locator') ) {
        /** CHECK FOR START DATE AND END DATE IF NOT FILLED WILL FOLLOW THE LAST BUDGET PLAN*/
            if ($client[0]['campaign_startdate'] == '0000-00-00' || $client[0]['campaign_enddate'] == '0000-00-00') {
                $_campaignID = $client[0]['clientcampaignsid'];
                if (trim($_campaignID) != '') {
                    $budgetplan = $this->getDefaultBudgetPlan($_campaignID);
                    if (count($budgetplan->budget_plans) > 0) {
                        $count = count($budgetplan->budget_plans) - 1;
                        $client[0]['campaign_startdate'] = $budgetplan->budget_plans[$count]->start_date;
                        $client[0]['campaign_enddate'] = $budgetplan->budget_plans[$count]->end_date;
                    }
                }
            }
        /** CHECK FOR START DATE AND END DATE IF NOT FILLED WILL FOLLOW THE LAST BUDGET PLAN*/
    }

    $available_device_type = [
        'desktop' => 36,
        'tablet' => 37,
        'mobile' => 35,
        'connected tv' => 1
    ];

    $device_names = [
        'desktop' => 'Desktop',
        'tablet' => 'Tablet',
        'mobile' => 'Mobile',
        'connected tv' => 'Connected TV',
    ];


    /** SYNC TOTAL LEADS WITH THE REPORT TABLE */
    foreach($client as $a => $cl) {
        // /** GET TOTAL LEADS SINCE BILLING */
        // $reportotal = LeadspeekReport::select(DB::raw("COUNT(*) as total"),DB::raw("SUM(price_lead) as pricetotal"))
        //                             ->where('leadspeek_api_id','=',$cl['leadspeek_api_id'])
        //                             ->where('active','=','T')
        //                             ->where(DB::raw('DATE_FORMAT(clickdate,"%Y%m%d")'),'>=',date('Ymd',strtotime($cl['start_billing_date'])))
        //                             ->get();
        // if(count($reportotal) > 0) {
        //     $client[$a]['total_leads_sincebilling'] = $reportotal[0]['total'];
        //     $client[$a]['total_cost_sincebilling'] = $reportotal[0]['pricetotal'];
        // }else{
        //     $client[$a]['total_leads_sincebilling'] = '0';
        //     $client[$a]['total_cost_sincebilling'] = '0';
        // }
        // /** GET TOTAL LEADS SINCE BILLING */

        /* ADD 1 MONTH LP_INVOICE_DATE */
        $client[$a]['lp_invoice_date_next_month'] = "";
        if($client[$a]['leadspeek_type'] == 'local')
        {
            if($client[$a]['active'] == 'F' && $client[$a]['disabled'] == 'T' && $client[$a]['active_user'] == 'F') // jika status stop pakai waktu sekarang
            {
                $client[$a]['lp_invoice_date_next_month'] = Carbon::now()->addMonthsNoOverflow()->setTimezone('America/Chicago')->format('Y-m-d');
            }
            else if(empty($client[$a]['lp_invoice_date'])) // jika status play, paused on play, paused dan lp_invoice_date null, maka check invoice terakhir
            {
                $invoice = LeadspeekInvoice::where('leadspeek_api_id', $client[$a]['leadspeek_api_id'])->where('invoice_type', 'campaign')->orderBy('id', 'desc')->first();
                if($invoice)
                {
                    $lp_invoice_date = $invoice->invoice_date;
                    $client[$a]['lp_invoice_date_next_month'] = Carbon::parse($lp_invoice_date)->addMonthNoOverflow()->setTimezone('America/Chicago')->format('Y-m-d');
                }
            }
            else // jika status play, paused on play, paused dan lp_invoice_date tidak null, maka pakai lp_invoice_date
            {
                $client[$a]['lp_invoice_date_next_month'] = Carbon::parse($client[$a]['lp_invoice_date'])->addMonthsNoOverflow()->setTimezone('America/Chicago')->format('Y-m-d');
            }
        }

        /* ADD 1 MONTH LP_INVOICE_DATE */

        /** SET FOR TARGET LOCATION*/
        $client[$a]['target_nt'] = false;
        $client[$a]['target_state'] = false;
        $client[$a]['target_city'] = false;
        $client[$a]['target_zip'] = false;
        $client[$a]['selects_state'] = array();
        $client[$a]['selects_citylist'] = array();
        $client[$a]['selects_city'] = array();
        $client[$a]['selects_citystate'] = array();
        $client[$a]['selects_citystatelist'] = array();
        $client[$a]['leadspeek_locator_keyword_bulk'] = $cl['leadspeek_locator_keyword']; 
        $client[$a]['continual_buy_options'] = $cl['continual_buy_options'] === 'Monthly' ? true : false;
        /** SET FOR TARGET LOCATION*/

        /** CHECK IF ORI CAMPAIGN START AND END DATE IS STILL EMPTY THEN MAKE IT SAME WITH EXISTING */
        if ($cl['ori_campaign_startdate'] == "0000-00-00 00:00:00") {
            $client[$a]['ori_campaign_startdate'] = $cl['campaign_startdate'];
        }
        if ($cl['ori_campaign_enddate'] == "0000-00-00 00:00:00") {
            $client[$a]['ori_campaign_enddate'] = $cl['campaign_enddate'];
        }

        if ($cl['lp_enddate'] == 'null' || $cl['lp_enddate'] == "" || is_null($cl['lp_enddate'])) {
            $client[$a]['lp_enddate'] = ($cl['ori_campaign_enddate'] == "0000-00-00 00:00:00")?$cl['campaign_enddate']:$cl['ori_campaign_enddate'];
        }
        
        if (trim($cl['lp_enddate']) == '0000-00-00 23:59:59' || trim($cl['lp_enddate']) == '0000-00-00 00:00:00') {
            $client[$a]['lp_enddate'] = '';
        }

        if ($client[$a]['ori_campaign_enddate'] == "0000-00-00 00:00:00") {
            $client[$a]['ori_campaign_enddate'] = "";
        }

        if ($client[$a]['campaign_enddate'] == "0000-00-00 00:00:00") {
            $client[$a]['campaign_enddate'] = "";
        }

        /** CHECK IF ORI CAMPAIGN START AND END DATE IS STILL EMPTY THEN MAKE IT SAME WITH EXISTING */

        /* IF VIEW IN DASHBOARD */
        if($view === 'dashboard') {
            /* GET agency_lifetime_total_leads, GET agency_lifetime_total_leads_cost, GET agency_lifetime_total_leads_profit */
            $totalLeadsCostProfit = LeadspeekReport::selectRaw('
                                                        COUNT(*) as lifetime_total_leads,
                                                        SUM(platform_price_lead) as agency_lifetime_total_leads_cost,
                                                        SUM(price_lead) as client_lifetime_total_leads_cost
                                                    ')
                                                    ->where('leadspeek_api_id', $cl['leadspeek_api_id'])
                                                    ->where('active','=','T')
                                                    ->first();

            $client[$a]['lifetime_total_leads'] = !empty($totalLeadsCostProfit->lifetime_total_leads) ? $totalLeadsCostProfit->lifetime_total_leads : 0;
            $client[$a]['agency_lifetime_total_leads_cost'] = !empty($totalLeadsCostProfit->agency_lifetime_total_leads_cost) ? $totalLeadsCostProfit->agency_lifetime_total_leads_cost : 0;
            $client[$a]['client_lifetime_total_leads_cost'] = !empty($totalLeadsCostProfit->client_lifetime_total_leads_cost) ? $totalLeadsCostProfit->client_lifetime_total_leads_cost : 0;
            /* GET agency_lifetime_total_leads, GET agency_lifetime_total_leads_cost, GET agency_lifetime_total_leads_profit */            

            /* GET agency_total_leads_last_billing, GET agency_total_cost_since_last_billing, GET agency_total_cost_since_last_billing_profit */
            $lastInvoiceDate = LeadspeekInvoice::where('leadspeek_api_id', $cl['leadspeek_api_id'])
                                                ->where('status', 'paid')
                                                ->orderBy('created_at', 'desc')
                                                ->value('created_at');

            if(!empty($lastInvoiceDate)) {
                $leadsCostProfit = LeadspeekReport::selectRaw('
                                                    COUNT(*) as total_leads_last_billing,
                                                    SUM(platform_price_lead) as agency_total_cost_since_last_billing,
                                                    SUM(price_lead) as client_total_cost_since_last_billing
                                                ')
                                                ->where('leadspeek_api_id', $cl['leadspeek_api_id'])
                                                ->where('created_at', '>=', $lastInvoiceDate)
                                                ->where('active','=','T')
                                                ->first();

                $client[$a]['total_leads_last_billing'] = !empty($leadsCostProfit->total_leads_last_billing) ? $leadsCostProfit->total_leads_last_billing : 0;
                $client[$a]['agency_total_cost_since_last_billing'] = !empty($leadsCostProfit->agency_total_cost_since_last_billing) ? $leadsCostProfit->agency_total_cost_since_last_billing : 0;
                $client[$a]['client_total_cost_since_last_billing'] = !empty($leadsCostProfit->client_total_cost_since_last_billing) ? $leadsCostProfit->client_total_cost_since_last_billing : 0;
            } else {
                $client[$a]['total_leads_last_billing'] = 0;
                $client[$a]['agency_total_cost_since_last_billing'] = 0;
                $client[$a]['client_total_cost_since_last_billing'] = 0;
            }
            /* GET agency_total_leads_last_billing, GET agency_total_cost_since_last_billing, GET agency_total_cost_since_last_billing_profit */
        }
        /* IF VIEW IN DASHBOARD */

        /* IF VIEW IN CAMPAIGN MANAGEMENT */
        else if($view === 'campaign') {
            /* FORCE ENABLE MINIMUM LIMIT LEADS TO 'F' */
            $client[$a]['enable_minimum_limit_leads'] = 'F';
            /* FORCE ENABLE MINIMUM LIMIT LEADS TO 'F' */

            /* GET TOTAL LEADS AND COST */
            $reportotal = LeadspeekReport::select(DB::raw("COUNT(*) as total"),DB::raw("SUM(price_lead) as pricetotal"))
                                        ->where('leadspeek_api_id','=',$cl['leadspeek_api_id'])
                                        ->where('active','=','T')
                                        ->get();
            if(count($reportotal) > 0) {
                $client[$a]['total_leads'] = $reportotal[0]['total'];
                $client[$a]['total_cost'] = $reportotal[0]['pricetotal'];
            }else{
                $client[$a]['total_leads'] = '0';
                $client[$a]['total_cost'] = '0';
            }
            /* GET TOTAL LEADS AND COST */

            /** GET YESTERDAY LEADS */
            $yesterday = date("Ymd", strtotime( '-1 days' ) );
            $yesterdaytotal = LeadspeekReport::select(DB::raw("COUNT(*) as total"))
                                        ->where(DB::raw('DATE_FORMAT(clickdate,"%Y%m%d")'),'=',$yesterday)
                                        ->where('leadspeek_api_id','=',$cl['leadspeek_api_id'])
                                        ->where('active','=','T')
                                        ->get();
            if(count($yesterdaytotal) > 0) {
                $client[$a]['yerterday_leads'] = $yesterdaytotal[0]['total'];
            }else{
                $client[$a]['yerterday_leads'] = 0;
            }
            /** GET YESTERDAY LEADS */

            /** GET YESTERDAY PREVIOUS LEADS */
            $yesterdaypreviousday = date("Ymd", strtotime( '-2 days' ) );
            $yesterdayprevioustotal = LeadspeekReport::select(DB::raw("COUNT(*) as total"))
                                        ->where(DB::raw('DATE_FORMAT(clickdate,"%Y%m%d")'),'=',$yesterdaypreviousday)
                                        ->where('leadspeek_api_id','=',$cl['leadspeek_api_id'])
                                        ->where('active','=','T')
                                        ->get();
            if(count($yesterdayprevioustotal) > 0) {
                $client[$a]['yerterday_previous_leads'] = $yesterdayprevioustotal[0]['total'];
            }else{
                $client[$a]['yerterday_previous_leads'] = 0;
            }
            /** GET YESTERDAY PREVIOUS LEADS */

            /** GET LIST QUEUE ID FOR ENHANCE */
            if (!empty($cl['list_queue_id']) && isset($cl['list_queue_id'])) {
                $client[$a]['list_queue_id'] = $cl['list_queue_id'];

            }else {
                $client[$a]['list_queue_id'] = '';
            }
            /** GET LIST QUEUE ID FOR ENHANCE */

            /* CHANGE ADVANCE INFORMATION TO ARRAY */
            if ($client[$a]['leadspeek_type'] == 'enhance' || $client[$a]['leadspeek_type'] == 'b2b') {
                if(!empty($client[$a]['advance_information']) && trim($client[$a]['advance_information']) != '') {
                    $client[$a]['advance_information'] = explode(',', $client[$a]['advance_information']);
                } else {
                    $client[$a]['advance_information'] = [];
                    $client[$a]['b2b_information'] = [];
                }
            }

            if ($client[$a]['leadspeek_type'] == 'local') {
               if(is_string($client[$a]['advance_information']) && !empty($client[$a]['advance_information']) && trim($client[$a]['advance_information']) != '') {
                    $information = json_decode($client[$a]['advance_information']);
                    if(isset($information->advance)){
                        $client[$a]['advance_information'] = $information->advance;
                    }else {
                        $client[$a]['advance_information'] = [];
                    }
                    
                    if(isset($information->b2b)){
                        $client[$a]['b2b_information'] = $information->b2b;
                    }else {
                        $client[$a]['b2b_information'] = [];
                    }
                } else {
                    $client[$a]['advance_information'] = [];
                    $client[$a]['b2b_information'] = [];
                    
                    /* IF PREPAID WHEN advance_information IN leadspeek_users EMPTY, CHECK AGAIN  IN topup_campaigns */
                    if($client[$a]['paymentterm'] == "Prepaid")  
                    {
                        $topupProgress = Topup::where('leadspeek_api_id', $client[$a]['leadspeek_api_id'])
                                              ->where('topup_status', 'progress')                                          
                                              ->orderBy('id', 'ASC')
                                              ->first();
                        // info("IF PREPAID CHECK AGAIN ADVANCE_INFORMATION IN TOPUP PROGRESS", ['topupProgress' => $topupProgress, 'leadspeek_api_id' => $client[$a]['leadspeek_api_id']]);
                        if(!empty($topupProgress)) 
                        {
                            $campaign_information_type_local = !empty($topupProgress->campaign_information_type_local) ? explode(',', $topupProgress->campaign_information_type_local) : [];
                            $information = !empty($topupProgress->advance_information) ? json_decode($topupProgress->advance_information) : '';
                            // info('topup available 1', ['campaign_information_type_local' => $topupProgress->campaign_information_type_local, 'campaign_information_type_local_array' => $campaign_information_type_local,'information' => $information,'in_array_advanced' => in_array('advanced', $campaign_information_type_local),'in_array_b2b' => in_array('b2b', $campaign_information_type_local),'isset_advance' => isset($information->advance),'isset_b2b' => isset($information->b2b)]);

                            if(in_array('advanced', $campaign_information_type_local) && isset($information->advance)) 
                            {
                                $client[$a]['advance_information'] = $information->advance;
                                // info('topup advanced 1', ['advance_information' => $client[$a]['advance_information']]);
                            } 
                            else 
                            {
                                $topupQueue = Topup::where('leadspeek_api_id', $client[$a]['leadspeek_api_id'])
                                                   ->where('topup_status', 'queue')
                                                   ->orderBy('id', 'ASC')
                                                   ->first();
                                // info("CHECK AGAIN ADVANCE_INFORMATION IN TOPUP QUEUE", ['topupQueue' => $topupQueue, 'leadspeek_api_id' => $client[$a]['leadspeek_api_id']]);
                                if(!empty($topupQueue))
                                {
                                    $campaign_information_type_local = !empty($topupQueue->campaign_information_type_local) ? explode(',', $topupQueue->campaign_information_type_local) : [];
                                    $information = !empty($topupQueue->advance_information) ? json_decode($topupQueue->advance_information) : '';
                                    // info('topup available 2', ['campaign_information_type_local' => $topupProgress->campaign_information_type_local, 'campaign_information_type_local_array' => $campaign_information_type_local,'information' => $information,'in_array_advanced' => in_array('advanced', $campaign_information_type_local),'in_array_b2b' => in_array('b2b', $campaign_information_type_local),'isset_advance' => isset($information->advance),'isset_b2b' => isset($information->b2b)]);
                                
                                    if(in_array('advanced', $campaign_information_type_local) && isset($information->advance)) 
                                    {
                                        $client[$a]['advance_information'] = $information->advance;
                                        // info('topup advanced 2', ['advance_information' => $client[$a]['advance_information']]);
                                    }
                                }
                            }
                        }
                    }
                    /* IF PREPAID WHEN advance_information IN leadspeek_users EMPTY, CHECK AGAIN  IN topup_campaigns */
                }
            }

            if (is_string($client[$a]['campaign_information_type_local']) && !empty($client[$a]['campaign_information_type_local']) && $client[$a]['campaign_information_type_local'] !== '') {
                $client[$a]['campaign_information_type_local'] = explode(',',$client[$a]['campaign_information_type_local']);
            }else {
                $client[$a]['campaign_information_type_local'] = ['basic'];
            }
            /* CHANGE ADVANCE INFORMATION TO ARRAY */

            /** CHECK IF CAMPAIGN EVER STARTED */
            $client[$a]['campaign_never_started'] = 'F';

            $checkUserLog = UserLog::where('action', 'Campaign activate')
                                    ->where('description','like','campaign id:%' . $cl['leadspeek_api_id'] . '%')
                                    ->first();
            if (!$checkUserLog && $client[$a]['total_leads'] == '0') {
                if (date('YmdHi',strtotime($client[$a]['start_billing_date'])) == date('YmdHi',strtotime($client[$a]['created_at']))) {
                    $client[$a]['campaign_never_started'] = 'T';
                }
            }
            /** CHECK IF CAMPAIGN EVER STARTED */

            /* FILTER CAMPAIGN ONLY DRAFT FOR LEADSPEEK_TYPE SIMPLIFI */
            if (
                ($leadspeekType == 'simplifi') &&
                (
                    ($CampaignStatus == 'draft' && $client[$a]['campaign_never_started'] == "F") || 
                    ($CampaignStatus == 'stop' && $client[$a]['campaign_never_started'] == "T")
                )
            ) {
                unset($client[$a]);
                continue;
            }
            /* FILTER CAMPAIGN ONLY DRAFT FOR LEADSPEEK_TYPE SIMPLIFI */

            // SIMPLIFI CAMPAIGN ATTRIBUTE
            $client[$a]['simplifi_selected_campaign'] = !empty($client[$a]['simplifi_selected_campaign']) && is_string($client[$a]['simplifi_selected_campaign'])
                ? array_map('intval', explode(',', $client[$a]['simplifi_selected_campaign']))
                : [];

            $client[$a]['simplifi_selected_audience'] = !empty($client[$a]['simplifi_selected_audience']) && is_string($client[$a]['simplifi_selected_audience'])
                ? array_map('intval', explode(',', $client[$a]['simplifi_selected_audience']))
                : [];

            $client[$a]['simplifi_selected_media'] = !empty($client[$a]['simplifi_selected_media']) && is_string($client[$a]['simplifi_selected_media'])
                ? array_map('intval', explode(',', $client[$a]['simplifi_selected_media']))
                : [];

            $selected_array = [];
            if (!empty($client[$a]['device_type']) && is_string($client[$a]['device_type'])) {
                $selected_array = json_decode($client[$a]['device_type'], true);
            }

            $selected_array = array_map('strtolower', $selected_array ?? []);

            $result = [];
            foreach ($available_device_type as $name => $id) {
                $result[] = [
                    'name' => $device_names[$name] ? $device_names[$name] : ucwords($name),
                    'val'  => in_array($name, $selected_array)
                ];
            }
            $client[$a]['device_types'] = $result;
            // SIMPLIFI CAMPAIGN ATTRIBUTE
        }
        /* IF VIEW IN CAMPAIGN MANAGEMENT */
    }
        
    /** SYNC TOTAL LEADS WITH THE REPORT TABLE */
    if (trim($sortby) == "total_leads") {
        $client = $client->toArray();

        $total_leads = array();
        foreach ($client['data'] as $key => $row)
        {
            $total_leads[$key] = $row['total_leads'];
        }

        if ($order == "DESC") {
            array_multisort($total_leads,SORT_DESC,$client['data']);
        }else{
            array_multisort($total_leads,SORT_ASC,$client['data']);
        }
    }

    return $client;
    
}