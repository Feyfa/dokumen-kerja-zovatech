<?php

public function getPixelLeadRecords($type, $startDate, $endDate, $companyRootId, $regional)
{
    // info(__FUNCTION__, ['get_defined_vars' => get_defined_vars()]);
    $pixelLeadRecords = PixelLeadRecord::from('pixel_lead_records as plr')
        ->join('leadspeek_users as lu','plr.leadspeek_api_id','=','lu.leadspeek_api_id')
        ->join('users as u','lu.user_id','=','u.id')
        ->whereNotNull('plr.visitor_id')
        ->where('plr.visitor_id', '<>', '')
        ->where('plr.pixel_status', '<>', 'invalid_label');
        /* DEVELOPER */
        // ->where('plr.visitor_id', 'ksTEM01xcnccA3N1mKEZ0iJ21762335434114');
        /* DEVELOPER */
    
    // ketika pilih bedasarkan tipe root nya 
    if(!empty($companyRootId)){
        $pixelLeadRecords->where('u.company_root_id','=',$companyRootId);
    }

    // ketika pilih bedasarkan regional nya
    if($regional == 'us'){
        $pixelLeadRecords->where('plr.timezone','like','%America%');
    }elseif($regional == 'non_us'){
        $pixelLeadRecords->where('plr.timezone','not like','%America%');
    }

    // ketika pilih bedasarkan tanggal nya
    $pixelLeadRecords->where(function($query) use ($startDate,$endDate) {
        if(!empty($startDate) && !empty($endDate)){
            $query->where(DB::raw('DATE_FORMAT(plr.created_at,"%Y%m%d")'),'>=',$startDate)
                    ->where(DB::raw('DATE_FORMAT(plr.created_at,"%Y%m%d")'),'<=',$endDate);
        }else{
            $query->where(DB::raw('DATE_FORMAT(plr.created_at,"%Y%m%d")'),'=',date('Ymd'));
        }
    });

    $result = ['running' => 0, 'stopped' => 0, 'paused' => 0, 'paused_run' => 0, 'total_all' => 0];
    if($type === 'grouping'){ // mode grouping
        $grouped = $pixelLeadRecords->select([
                // DB::raw("COUNT(CASE WHEN plr.campaign_status = 'running' THEN 1 END) AS total_running"),
                // DB::raw("COUNT(CASE WHEN plr.campaign_status = 'stopped' THEN 1 END) AS total_stopped"),
                // DB::raw("COUNT(CASE WHEN plr.campaign_status = 'paused' THEN 1 END) AS total_paused"),
                // DB::raw("COUNT(CASE WHEN plr.campaign_status = 'paused_on_run' THEN 1 END) AS total_paused_on_run"),
                DB::raw("COUNT(DISTINCT CASE WHEN plr.campaign_status = 'running' THEN plr.campaign_status END) AS total_running"),
                DB::raw("COUNT(DISTINCT CASE WHEN plr.campaign_status = 'stopped' THEN plr.campaign_status END) AS total_stopped"),
                DB::raw("COUNT(DISTINCT CASE WHEN plr.campaign_status = 'paused' THEN plr.campaign_status END) AS total_paused"),
                DB::raw("COUNT(DISTINCT CASE WHEN plr.campaign_status = 'paused_on_run' THEN plr.campaign_status END) AS total_paused_on_run"),
                'plr.visitor_id',
            ])
            ->groupBy('plr.visitor_id')
            ->get();
        
        $result['running'] = (int) $grouped->sum('total_running');
        $result['stopped'] = (int) $grouped->sum('total_stopped');
        $result['paused'] = (int) $grouped->sum('total_paused');
        $result['paused_run'] = (int) $grouped->sum('total_paused_on_run');
        $result['total_all'] = $grouped->count(); // total unique visitor/orangnya bukan total campaign status
        // info(__FUNCTION__, ['result' => $result]);
    }else{ // mode detail
        $record = $pixelLeadRecords->select([
                DB::raw("COUNT(CASE WHEN plr.campaign_status = 'running' THEN 1 END) AS total_running"),
                DB::raw("COUNT(CASE WHEN plr.campaign_status = 'stopped' THEN 1 END) AS total_stopped"),
                DB::raw("COUNT(CASE WHEN plr.campaign_status = 'paused' THEN 1 END) AS total_paused"),
                DB::raw("COUNT(CASE WHEN plr.campaign_status = 'paused_on_run' THEN 1 END) AS total_paused_on_run"),
            ])
            ->first();
        $result['running'] = (int) ($record->total_running ?? 0);
        $result['stopped'] = (int) ($record->total_stopped ?? 0);
        $result['paused'] = (int) ($record->total_paused ?? 0);
        $result['paused_run'] = (int) ($record->total_paused_on_run ?? 0);
        $result['total_all'] = $result['running'] + $result['stopped'] + $result['paused'] + $result['paused_run']; // total dari semua status
        // info(__FUNCTION__, ['result' => $result]);
    }

    return $result;
}