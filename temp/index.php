<?php

private function rememberIsCampaignLocal(string $cacheKey, int $ttlIfTrue, int $ttlIfFalse, Closure $resolver)
{
    try 
    {
        // coba check di redis apakah ada keynya jika ada return
        $cached = Cache::store('redis')->get($cacheKey);
        // info('rememberIsCampaignLocal 1.1', ['cacheKey' => $cacheKey, 'cached' => $cached]);
        if(!is_null($cached)){
            // info('rememberIsCampaignLocal 1.2');
            return (bool) $cached;
        }

        // jika tidak ada maka jalankan callback resolver, dan put hasilnya ke redis
        $value = (bool) $resolver();
        $ttl = $value ? $ttlIfTrue : $ttlIfFalse;
        Cache::store('redis')->put($cacheKey, $value, $ttl);
        // info('rememberIsCampaignLocal 1.3', ['cacheKey' => $cacheKey, 'value' => $value, 'ttl' => $ttl]);

        return $value;
    }
    catch(\Throwable $e)
    {
        info("rememberIsCampaignLocal error : {$e->getMessage()}");
        return false;
    }
}

private function extractLeadspeekApiId($label)
{
    try
    {
        if(empty($label)){
            return '';
        }

        // cari delimiter terdekat (| atau -)
        $posPipe = strpos($label, '|');
        $posDash = strpos($label, '-');

        if($posPipe === false && $posDash === false){
            return '';
        }

        if($posPipe === false){
            return substr($label, 0, $posDash);
        }

        if($posDash === false){
            return substr($label, 0, $posPipe);
        }

        return substr($label, 0, min($posPipe, $posDash));
    }
    catch(\Throwable $e)
    {
        info("extractLeadspeekApiId error : {$e->getMessage()}");
        return '';
    }
}

private function buildLegacyLabelArray($label)
{
    try 
    {
        /*
            Contoh Case
            * INPUT : "{campaignid}-{pixelLeadRecordID}|{keyword}" OUTPUT : "{campaignid}|{keyword}|{pixelLeadRecordID}"
            * INPUT : "{campaignid}-{pixelLeadRecordID}-{customParams}|{keyword}" OUTPUT : "{campaignid}|{keyword}|{pixelLeadRecordID}|{customParams}"
        */

        // validation is campaign local or not
        $leadspeek_api_id = $this->extractLeadspeekApiId($label);
        $appEnvironment = env('DEVELOPEMODE', false) ? 'sandbox' : 'production';
        $cacheKey = "{$appEnvironment}_buildLegacyLabelArray_iscampaignlocal_{$leadspeek_api_id}";
        $isCampaignLocal = $this->rememberIsCampaignLocal($cacheKey,86400,600,function () use ($leadspeek_api_id) {
            return LeadspeekUser::where('leadspeek_api_id', $leadspeek_api_id)->where('leadspeek_type', 'local')->exists();
        });
        if(!$isCampaignLocal || empty($label)){
            return explode('|', $label);
        }

        // siapkan format array nya
        $data = [
            0 => null, // leadspeek_api_id
            1 => null, // keyword
            2 => null, // pixelLeadRecordID
            3 => null, // customParams
        ];

        // Pisahkan keyword → selalu ambil PIPE TERAKHIR
        $pipePos = strrpos($label, '|');
        if($pipePos === false){
            return $data;
        }
        $head = substr($label, 0, $pipePos);
        $keyword = substr($label, $pipePos + 1);

        // Parse bagian depan (ID, pixel, custom) , Jika pakai format baru (local): ada "-"
        if(strpos($head, '-') !== false){
            // Ambil maksimal 3 part: id - pixel - custom
            $parts = explode('-', $head, 3);
            $data[0] = trim($parts[0] ?? ''); // leadspeek_api_id
            $data[2] = trim($parts[1] ?? ''); // pixelLeadRecordID
            $data[3] = trim($parts[2] ?? ''); // customParams
        }

        // Set keyword and filter array
        $data[1] = trim($keyword);
        $data = array_filter($data, fn($v) => $v !== null && $v !== '');

        return $data;
    }
    catch(\Throwable $e)
    {
        info("buildLegacyLabelArray error : {$e->getMessage()}");
        return $label;
    }
}


// nanti dipakai kaya gini
$label = (isset($request->label))?$request->label:'';
$data = $this->buildLegacyLabelArray($label);