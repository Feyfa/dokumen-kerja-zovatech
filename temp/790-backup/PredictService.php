<?php

namespace App\Services;

use Carbon\Carbon;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class PredictService
{
    /**
     * untuk mendapat person dari mcp mastra
     * @param array $json
     * @return array
     * format json:
     *  {
            "expression": "(900000002) AND (900000023) AND (1800000051) AND (900000021)",
            "export_format": "csv",
            "domains": ["name", "email", "demographic", "address"],
            "audience_limit": 50,
            "location": {
                "latitude": 40,
                "longitude": -83,
                "radius": 100,
                "unit": "km"
            }
        }
     */
    public function findPerson_mcpMastra(array $json)
    {
        // info('findPerson_mcpMastra', ['json' => $json]);
        /* CONFIG */
        $baseUrl = config('services.predict_service.base_url', 'localhost:4111');
        $timeout = (int) config('services.predict_service.timeout', 120);
        $token = config('services.predict_service.token', '');
        $url = "{$baseUrl}/find-persons";
        /* CONFIG */

        /* REQUEST */
        try{
            $client = new Client([
                'timeout' => $timeout,
                'http_errors' => false,
            ]);
            
            $response = $client->post($url, [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                    'x-internal-token' => $token,
                ],
                'json' => $json,
            ]);
            $responseBody = $response->getBody()->getContents();
            $responseData = json_decode($responseBody, true);
            // info('findPerson_mcpMastra response', ['responseBody' => $responseBody]);
            $file_download = isset($responseData['export']['url']) ? $responseData['export']['url'] : "";
            $total_leads = isset($responseData['export']['rows']) ? $responseData['export']['rows'] : 0;
            // info('findPerson_mcpMastra file', ['file_download' => $file_download]);
            
            return ['status' => 'success', 'file_download' => $file_download, 'total_leads' => $total_leads];
        }catch(\Exception $e){
            Log::info('findPerson_mcpMastra error', ['message' => $e->getMessage()]);
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
        /* REQUEST */
    }

    public function transferUrlFileFromWattDataToDigitalOceanSpaces($url, $leadspeek_api_id, $now_date, $next_week_date)
    {
        try{
            // ambil nama file dari URL
            $path = parse_url($url, PHP_URL_PATH);
            $fileNameMcp = basename($path);
            // info('transferUrlFileFromWattDataToDigitalOceanSpaces 1.1', ['url' => $url, 'fileNameMcp' => $fileNameMcp]);

            // buka stream dari S3 (public URL)
            $readStream = fopen($url, 'r');
            if(!$readStream){
                // info('transferUrlFileFromWattDataToDigitalOceanSpaces 1.2', ['readStream' => $readStream]);
                return ['status' => 'error', 'message' => 'Failed to open S3 file stream'];
            }
            
            // upload stream langsung ke DigitalOcean Spaces
            $epochtime = Carbon::now()->timestamp;
            $fileNameDo = "predict_report_campaign{$leadspeek_api_id}_{$now_date}_{$next_week_date}_{$epochtime}_{$fileNameMcp}";
            $spacesPath = "users/predictid/{$fileNameDo}";
            // info('transferUrlFileFromWattDataToDigitalOceanSpaces 1.3', ['spacesPath' => $spacesPath]);
            Storage::disk('spaces')->put($spacesPath, $readStream, 'public'); // atau 'private'
            $file_download = Storage::disk('spaces')->url($spacesPath);
            $file_download_cdn = str_replace('digitaloceanspaces.com', 'cdn.digitaloceanspaces.com', $file_download);
            // info('transferUrlFileFromWattDataToDigitalOceanSpaces 1.4', ['file_download' => $file_download, 'file_download_cdn' => $file_download_cdn]);
            
            return ['status' => 'success', 'file_download' => $file_download_cdn];
        }catch(\Exception $e){
            Log::info('transferUrlFileFromWattDataToDigitalOceanSpaces error', ['message' => $e->getMessage()]);
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }
}