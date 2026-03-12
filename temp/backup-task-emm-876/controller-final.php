<?php

class Controller
{
    public function UpsertReportAnalytics($leadspeek_api_id,$leadspeekType = "",$typeReport,$value = '', $date = '') {
        try {
            $chkExist = ReportAnalytic::select('id');

            if($leadspeekType == 'clean_id') {
                $chkExist->where('clean_file_id','=',$leadspeek_api_id);
            } else {
                $chkExist->where('leadspeek_api_id','=',$leadspeek_api_id);
            }

            if ($typeReport == 'simplifi_impressions') {
                $chkExist->where('date','=',$date);
            } else {
                $chkExist->where('date','=',date('Y-m-d'));
            }

            $chkExist = $chkExist->get();

            if (count($chkExist) > 0) {
                $reportAnalytic = ReportAnalytic::find($chkExist[0]['id']);
                if ($typeReport == "pixelfire") {
                    $reportAnalytic->pixelfire = $reportAnalytic->pixelfire + 1;
                }else if ($typeReport == 'renderingpixelfire') {
                    $reportAnalytic->renderingpixelfire = $reportAnalytic->renderingpixelfire + 1;
                }else if ($typeReport == "towerpostal") {
                    $reportAnalytic->towerpostal = $reportAnalytic->towerpostal + 1;
                }else if ($typeReport == "endatoenrichment") {
                    $reportAnalytic->endatoenrichment = $reportAnalytic->endatoenrichment + 1;
                }else if ($typeReport == "toweremail") {
                    $reportAnalytic->toweremail = $reportAnalytic->toweremail + 1;
                }else if ($typeReport == "zerobounce") {
                    $reportAnalytic->zerobounce = $reportAnalytic->zerobounce + 1;
                }else if ($typeReport == "zerobouncefailed") {
                    $reportAnalytic->zerobouncefailed = $reportAnalytic->zerobouncefailed + 1;
                }else if ($typeReport == "zerobounce_details" && !empty($value)) {
                    if (empty($reportAnalytic->zerobounce_details)) {
                        $zerobounce_details = (object)[
                            'valid' => 0,
                            'invalid' => 0,
                            'catch_all' => 0,
                            'unknown' => 0,
                            'spamtrap' => 0,
                            'abuse' => 0,
                            'do_not_mail' => 0,
                            'total' => 0
                        ];
                    } else {
                        $zerobounce_details = json_decode($reportAnalytic->zerobounce_details);

                        foreach (['valid','invalid', 'catch_all', 'unknown', 'spamtrap', 'abuse', 'do_not_mail','total'] as $field) {
                            if (!isset($zerobounce_details->$field)) {
                                $zerobounce_details->$field = 0;
                            }
                        }
                    }

                    if ($value == 'valid') {
                        $zerobounce_details->valid = $zerobounce_details->valid + 1; 
                    }elseif ($value == 'invalid') {
                        $zerobounce_details->invalid = $zerobounce_details->invalid + 1; 
                    }elseif ($value == 'catch-all') {
                        $zerobounce_details->catch_all = $zerobounce_details->catch_all + 1; 
                    }elseif ($value == 'unknown') {
                        $zerobounce_details->unknown = $zerobounce_details->unknown + 1; 
                    }elseif ($value == 'spamtrap') {
                        $zerobounce_details->spamtrap = $zerobounce_details->spamtrap + 1; 
                    }elseif ($value == 'abuse') {
                        $zerobounce_details->abuse = $zerobounce_details->abuse + 1; 
                    }elseif ($value == 'do_not_mail') {
                        $zerobounce_details->do_not_mail = $zerobounce_details->do_not_mail + 1; 
                    }
                    $zerobounce_details->total = $zerobounce_details->total + 1;
                    $reportAnalytic->zerobounce_details = json_encode($zerobounce_details);
                }else if ($typeReport == "truelist_details" && !empty($value)) {

                    if (empty($reportAnalytic->truelist_details)) {
                        $truelist_details = (object)[
                            $value => 0,
                            'total' => 0,
                        ];
                    } else {
                        $truelist_details = json_decode($reportAnalytic->truelist_details);
                        if (!isset($truelist_details->$value)) {
                            $truelist_details->$value = 0;
                        }
                        if (!isset($truelist_details->total)) {
                            $truelist_details->total = 0;
                        }
                    }
                    
                    $truelist_details->$value = $truelist_details->$value + 1; 
                    $truelist_details->total = $truelist_details->total + 1;
                    $reportAnalytic->truelist_details = json_encode($truelist_details);
                }else if ($typeReport == "locationlock") {
                    $reportAnalytic->locationlock = $reportAnalytic->locationlock + 1;
                }else if ($typeReport == "locationlockfailed") {
                    $reportAnalytic->locationlockfailed = $reportAnalytic->locationlockfailed + 1;
                }else if ($typeReport == "serveclient") {
                    $reportAnalytic->serveclient = $reportAnalytic->serveclient + 1;
                }else if ($typeReport == "serveclient_bigdbm") {
                    $reportAnalytic->serveclient_bigdbm = is_null($reportAnalytic->serveclient_bigdbm) ? 0 : $reportAnalytic->serveclient_bigdbm;
                    $reportAnalytic->serveclient_bigdbm = $reportAnalytic->serveclient_bigdbm + 1;
                }else if ($typeReport == "serveclient_wattdata") {
                    $reportAnalytic->serveclient_wattdata = is_null($reportAnalytic->serveclient_wattdata) ? 0 : $reportAnalytic->serveclient_wattdata;
                    $reportAnalytic->serveclient_wattdata = $reportAnalytic->serveclient_wattdata + 1;
                }else if ($typeReport == "notserve") {
                    $reportAnalytic->notserve = $reportAnalytic->notserve + 1;
                }else if ($typeReport == "bigbdmemail") {
                    $reportAnalytic->bigbdmemail = $reportAnalytic->bigbdmemail + 1;
                }else if ($typeReport == "bigbdmpii") {
                    $reportAnalytic->bigbdmpii = $reportAnalytic->bigbdmpii + 1;
                }else if ($typeReport == "wattdatamd5") {
                    $reportAnalytic->wattdatamd5 = $reportAnalytic->wattdatamd5 + 1;
                }else if ($typeReport == "wattdatapii") {
                    $reportAnalytic->wattdatapii = $reportAnalytic->wattdatapii + 1;
                }else if($typeReport == 'bigbdmhems') {
                    $reportAnalytic->bigbdmhems = $value;
                }else if($typeReport == 'bigbdmtotalleads') {
                    $reportAnalytic->bigbdmtotalleads = $value;
                }else if($typeReport == 'bigbdmremainingleads') {
                    $reportAnalytic->bigbdmremainingleads = $value;
                }else if($typeReport == 'getleadfailed') {
                    $reportAnalytic->getleadfailed = $reportAnalytic->getleadfailed + 1;
                }else if($typeReport == 'getleadfailed_bigbdmmd5') {
                    $reportAnalytic->getleadfailed_bigbdmmd5 = $reportAnalytic->getleadfailed_bigbdmmd5 + 1;
                }else if($typeReport == 'getleadfailed_gettowerdata') {
                    $reportAnalytic->getleadfailed_gettowerdata = $reportAnalytic->getleadfailed_gettowerdata + 1;
                }else if($typeReport == 'getleadfailed_bigbdmpii') {
                    $reportAnalytic->getleadfailed_bigbdmpii = $reportAnalytic->getleadfailed_bigbdmpii + 1;
                }else if($typeReport == 'getleadfailed_wattdatamd5') {
                    $reportAnalytic->getleadfailed_wattdatamd5 = $reportAnalytic->getleadfailed_wattdatamd5 + 1;
                }else if($typeReport == 'getleadfailed_wattdatapii') {
                    $reportAnalytic->getleadfailed_wattdatapii = $reportAnalytic->getleadfailed_wattdatapii + 1;
                }elseif ($typeReport == 'simplifi_impressions') {
                    $reportAnalytic->simplifi_impressions = $reportAnalytic->simplifi_impressions + $value; 
                }

                $reportAnalytic->save();
            }else{
                $pixelfire = ($typeReport == "pixelfire")?1:0;
                $renderingpixelfire = ($typeReport == 'renderingpixelfire')?1:0;
                $towerpostal = ($typeReport == "towerpostal")?1:0;
                $endatoenrichment =($typeReport == "endatoenrichment")?1:0;
                $toweremail = ($typeReport == "toweremail")?1:0;
                $zerobounce = ($typeReport == "zerobounce")?1:0;
                $zerobouncefailed = ($typeReport == "zerobouncefailed")?1:0;
                if ($typeReport == "zerobounce_details") {
                    $zerobounce_details = (object)[
                        'valid' => ($value == 'valid') ? 1 : 0,
                        'invalid' => ($value == 'invalid') ? 1 : 0,
                        'catch_all' => ($value == 'catch-all') ? 1 : 0,
                        'unknown' => ($value == 'unknown') ? 1 : 0,
                        'spamtrap' => ($value == 'spamtrap') ? 1 : 0,
                        'abuse' => ($value == 'abuse') ? 1 : 0,
                        'do_not_mail' => ($value == 'do_not_mail') ? 1 : 0,
                        'total' => ($value == 'total') ? 1 : 0
                    ];
                }else {
                    $zerobounce_details = (object)[
                        'valid' => 0,
                        'invalid' => 0,
                        'catch_all' => 0,
                        'unknown' => 0,
                        'spamtrap' => 0,
                        'abuse' => 0,
                        'do_not_mail' => 0,
                        'total' => 0
                    ];
                }

                if ($typeReport == "truelist_details") {
                    if (!empty($value)) {
                        $truelist_details = (object)[
                            $value => 1,
                            'total' => 1,
                        ];
                    }else {
                        $truelist_details = (object)[
                            'total' => 1,
                        ];
                    }
                }else {
                    $truelist_details = (object)[
                        'total' => 0,
                    ];
                }

                $locationlock = ($typeReport == "locationlock")?1:0;
                $locationlockfailed = ($typeReport == "locationlockfailed")?1:0;
                $leadspeek_type = ($leadspeekType != "")?$leadspeekType:"local";
                $serveclient = ($typeReport == "serveclient")?1:0;
                $serveclient_bigdbm = ($typeReport == "serveclient_bigdbm")?1:0;
                $serveclient_wattdata = ($typeReport == "serveclient_wattdata")?1:0;
                $notserve = ($typeReport == "notserve")?1:0;
                $bigbdmhems = ($typeReport == 'bigbdmhems')?$value:0;
                $bigbdmtotalleads = ($typeReport == 'bigbdmtotalleads')?$value:0;
                $bigbdmremainingleads = ($typeReport == 'bigbdmremainingleads')?$value:0;
                $getleadfailed = ($typeReport == 'getleadfailed')?1:0;
                $getleadfailed_bigbdmmd5 = ($typeReport == 'getleadfailed_bigbdmmd5')?1:0;
                $getleadfailed_gettowerdata = ($typeReport == 'getleadfailed_gettowerdata')?1:0;
                $getleadfailed_bigbdmpii = ($typeReport == 'getleadfailed_bigbdmpii')?1:0;
                $getleadfailed_wattdatamd5 = ($typeReport == 'getleadfailed_wattdatamd5')?1:0;
                $getleadfailed_wattdatapii = ($typeReport == 'getleadfailed_wattdatapii')?1:0;
                $simplifi_impressions = ($typeReport == 'simplifi_impressions')?$value:0;

                $reportAnalytic = ReportAnalytic::create([
                    'date' => ($typeReport == 'simplifi_impressions') ? $date : date('Y-m-d'),
                    'leadspeek_api_id'=>($leadspeekType != 'clean_id')?$leadspeek_api_id:'',
                    'clean_file_id'=>($leadspeekType == 'clean_id')?$leadspeek_api_id:null,
                    'pixelfire' => $pixelfire,
                    'renderingpixelfire' => $renderingpixelfire,
                    'towerpostal' => $towerpostal,
                    'endatoenrichment' => $endatoenrichment,
                    'toweremail' => $toweremail,
                    'zerobounce' => $zerobounce,
                    'zerobouncefailed' => $zerobouncefailed,
                    'zerobounce_details' => json_encode($zerobounce_details),
                    'truelist_details' => json_encode($truelist_details),
                    'locationlock' => $locationlock,
                    'locationlockfailed' => $locationlockfailed,
                    'leadspeek_type' => $leadspeek_type,
                    'serveclient' => $serveclient,
                    'serveclient_bigdbm' => $serveclient_bigdbm,
                    'serveclient_wattdata' => $serveclient_wattdata,
                    'notserve' => $notserve,
                    'bigbdmhems' => $bigbdmhems,
                    'bigbdmtotalleads' => $bigbdmtotalleads,
                    'bigbdmremainingleads' => $bigbdmremainingleads,
                    'getleadfailed' => $getleadfailed,
                    'getleadfailed_bigbdmmd5' => $getleadfailed_bigbdmmd5,
                    'getleadfailed_gettowerdata' => $getleadfailed_gettowerdata,
                    'getleadfailed_bigbdmpii' => $getleadfailed_bigbdmpii,
                    'getleadfailed_wattdatamd5' => $getleadfailed_wattdatamd5,
                    'getleadfailed_wattdatapii' => $getleadfailed_wattdatapii,
                    'simplifi_impressions' => $simplifi_impressions,
                ]);

            }
        } catch (\Throwable $e) {
            Log::error('Error UpsertReportAnalytics: ' . $e->getMessage(), [
                'function' => __FUNCTION__,
                'leadspeek_api_id' => $leadspeek_api_id,
                'leadspeekType' => $leadspeekType,
                'typeReport' => $typeReport,
                'value' => $value,
                'date' => $date,
            ]);
        }
    }
}