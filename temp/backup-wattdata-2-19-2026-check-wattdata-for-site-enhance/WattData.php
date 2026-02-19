<?php

namespace App\Services;

use App\Http\Controllers\Controller;
use App\Models\GlobalSettings;
use Exception;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;
use Throwable;

class WattData 
{
    private $token;
    private $base_url;
    protected $client;
    protected $controller;

    /**
     * Constructor untuk WattData.
     * Menginisialisasi Guzzle Client dengan konfigurasi default.
     */
    public function __construct(Controller $controller)
    {
        // get credentials
        $get_credentials_db = $this->get_credentials_db();
        $this->token = $get_credentials_db['token'] ?? '';
        $this->base_url = $get_credentials_db['base_url'] ?? '';

        // init client
        $this->client = new Client([
            'base_uri' => $this->base_url,
            'headers' => [
                'Content-Type' => 'application/json',
                'Authorization' => "Basic {$this->token}",
                'Accept' => 'application/json,text/event-stream'
            ]
        ]);

        // controller
        $this->controller = $controller;
    }

    // =======================================================================
    // PRIVATE METHODS START
    // =======================================================================
    /**
     * Metode privat untuk menangani semua permintaan API.
     *
     * @param string $method Metode HTTP (GET, POST, PUT, DELETE)
     * @param string $uri Endpoint API
     * @param array $options Opsi tambahan untuk Guzzle (misal: 'json' untuk body)
     */
    private function request(string $method, string $uri, array $options = [])
    {
        try{
            $response = $this->client->request($method, $uri, $options);
            return $response->getBody()->getContents();
        }catch(RequestException $e){
            // Log error untuk debugging
            Log::error('Watt Data API request failed: ' . $e->getMessage(), [
                'request_uri' => $uri,
                'request_method' => $method,
                'response_body' => $e->hasResponse() ? (string) $e->getResponse()->getBody() : null,
            ]);
            throw $e;
        }catch(Exception $e){
            // Log error untuk debugging
            Log::error('Watt Data API exception: ' . $e->getMessage(), [
                'request_uri' => $uri,
                'request_method' => $method,
                'response_body' => $e->getMessage(),
            ]);
            throw $e;
        }catch (Throwable $th){
            // Log error untuk debugging
            Log::error('Watt Data API request throwable: ' . $th->getMessage(), [
                'request_uri' => $uri,
                'request_method' => $method,
                'line' => $th->getLine(),
                'file' => $th->getFile(),
                'error' => $th->getMessage(),
            ]);
            throw $th;
        }
    }

    /**
     * untuk mengambil credentials watt dari database
     */
    private function get_credentials_db()
    {
        $setting = GlobalSettings::where('setting_name', 'wattdata_credentials')->first();

        if(!$setting || empty($setting->setting_value)){
            return ['token' => '', 'base_url' => ''];
        }

        $decoded = json_decode($setting->setting_value, true);

        if(!is_array($decoded)){
            return ['token' => '', 'base_url' => '',];
        }

        return [
            'token' => $decoded['token'] ?? '',
            'base_url' => $decoded['base_url'] ?? '',
        ];
    }

    /**
     * untuk memparse address US, menjadi street, city, state, zip
     * @param string|null $address
     * @return array
     */
    public function parse_us_address(?string $address)
    {
        $result = [
            'fullAddress' => $address,
            'street' => null,
            'city' => null,
            'state' => null,
            'zip' => null,
        ];

        if (empty($address)) {
            return $result;
        }

        // Harus ada minimal 2 koma → street, city, state+zip
        if (substr_count($address, ',') < 2) {
            return $result;
        }

        $parts = array_map('trim', explode(',', $address));
        $stateZip = array_pop($parts);
        $city = array_pop($parts);
        $street = implode(', ', $parts);

        // Regex: capture state (2 huruf) + ZIP (5 digit), abaikan extension apapun setelah hyphen
        if (preg_match('/^([A-Z]{2})\s+(\d{5})(?:-\d+)?$/', $stateZip, $m)) {
            $result['street'] = $street;
            $result['city'] = $city;
            $result['state'] = $m[1];
            $result['zip'] = $m[2];
        }

        return $result;
    }

    /**
     * tujuan dari function ini adalah untuk mengambil text dari response text dan mengkonversi menjadi array
     * Response dari API berbentuk text dengan format:
     *      event: message
     *      data: {"result":{"content":[{"type":"text","text":"{...}"}]}}
     * 
     * @param string $response Response text dari API
     * @return array
     */
    private function convert_response_text_to_array(string $response)
    {
        $data = [];
        $jsonString = str_replace("event: message\ndata: ", "", trim($response)); // ambil string dari response text ini
        $jsonArray = json_decode($jsonString, true); // decode json string ini
        $contentText = isset($jsonArray['result']['content'][0]['text']) ? $jsonArray['result']['content'][0]['text'] : null; // ambil text dari json array ini
        if(!empty($contentText)){
            $data = json_decode($contentText, true);
        }
        return $data;
    }
    // =======================================================================
    // PRIVATE METHODS END
    // =======================================================================


    // =======================================================================
    // PUBLIC METHODS START
    // =======================================================================
    /**
     * untuk mendapatkan resolve identities dari watt data
     * @param array $data Keyed identifiers (email/md5/phone/address/maid/..)
     * @param $file_id ID file dari database clean_id_file
     * @return array
     */
    public function get_resolve_identities(array $data, $file_id = null)
    {
        // $startTime = microtime(true);
        // Simpan input data untuk error handling
        $inputData = $data;
        $id_type_features = ['email', 'phone', 'address', 'maid'];
        $hash_type_features = ['md5', 'sha1', 'sha256', 'plaintext'];
        
        try
        {
            /* RAKIT MULTI IDENTIFIERS */
            /*
                $data = [
                    ['id_type' => 'email', 'hash_type' => 'md5', 'value' => ['1231xxxxx']],
                    ['id_type' => 'phone', 'hash_type' => 'plaintext', 'value' => ['1231xxxxx']],
                    ['id_type' => 'address', 'hash_type' => 'plaintext', 'value' => ['1231xxxxx']],
                    ['id_type' => 'maid', 'hash_type' => 'plaintext', 'value' => ['1231xxxxx']],
                ]
            */
            // info(['data' => $data]);
            $multi_identifiers = [];
            foreach($data as $item){
                // Validasi bahwa item adalah array dan memiliki key yang diperlukan
                if(
                    is_array($item) && 
                    isset($item['id_type']) && 
                    isset($item['hash_type']) && 
                    isset($item['value']) &&
                    in_array($item['id_type'], $id_type_features) &&
                    in_array($item['hash_type'], $hash_type_features)
                ){
                    $multi_identifiers[] = [
                        'id_type' => $item['id_type'],
                        'hash_type' => $item['hash_type'],
                        'values' => $item['value'],
                    ];
                }
            }

            // info(['multi_identifiers' => $multi_identifiers]);
            /* RAKIT MULTI IDENTIFIERS */

            // Buat struktur JSON sesuai dengan format JSON-RPC dari Postman
            $requestBody = [
                'jsonrpc' => '2.0',
                'method' => 'tools/call',
                'params' => [
                    'name' => 'resolve_identities',
                    'arguments' => [
                        'multi_identifiers' => $multi_identifiers
                    ]
                ],
                'id' => 1
            ];

            // Kirim request dengan body JSON
            $response = $this->request('POST', '', ['json' => $requestBody]);

            // Convert response text ke array
            $responseData = $this->convert_response_text_to_array($response);
            // info('get_resolve_identities', ['data' => $responseData, 'requestBody' => $requestBody]);

            // $endTime = microtime(true);
            // $diffTime = $endTime - $startTime;
            // info('get_resolve_identities', ['diffTime' => $diffTime]);

            return ['status' => 'success', 'data' => $responseData];
        }
        catch(Exception $th)
        {
            info('get_resolve_identities error', ['error' => $th->getMessage()]);
            
            // Extract data dari format array of arrays
            $email = "";
            $phone = "";
            $address = "";
            $maid = "";
            
            foreach($inputData as $item){
                if(is_array($item) && isset($item['id_type']) && isset($item['value'])){
                    switch($item['id_type']){
                        case 'email':
                            $email = json_encode($item['value'], JSON_UNESCAPED_SLASHES);
                            break;
                        case 'phone':
                            $phone = json_encode($item['value'], JSON_UNESCAPED_SLASHES);
                            break;
                        case 'address':
                            $address = json_encode($item['value'], JSON_UNESCAPED_SLASHES);
                            break;
                        case 'maid':
                            $maid = json_encode($item['value'], JSON_UNESCAPED_SLASHES);
                            break;
                    }
                }
            }
            
            $this->controller->UpsertFailedLeadRecord([
                'function' => 'get_resolve_identities',
                'type' => 'error',
                'description' => $th->getMessage(),
                'clean_file_id' => is_numeric($file_id)?$file_id:null,
                'email_encrypt' => $email,
                'phone' => $phone,
                'address' => $address,
                'maid' => $maid,
                'url' => $this->base_url,
                'leadspeek_type' => 'clean_id',
            ]);
            // $endTime = microtime(true);
            // $diffTime = $endTime - $startTime;
            // info('get_resolve_identities', ['diffTime' => $diffTime]);
            return ['status' => 'error', 'message' => $th->getMessage()];
        }
    }

    /**
     * untuk mendapatkan data person dari watt data
     * @param array $person_ids Array of person IDs ["12345", "56789"]
     * @return array
     */
    public function get_person(array $person_ids, $file_id = null)
    {
        // $startTime = microtime(true);
        try
        {
            // Normalisasi sederhana: hanya terima string, trim, buang kosong
            $normalized_ids = [];
            foreach($person_ids as $v){
                if(!is_string($v) && !is_numeric($v)){
                    continue;
                }
                $s = trim($v);
                if($s !== ''){
                    $normalized_ids[] = $s;
                }
            }
            $person_ids = $normalized_ids;
            if(empty($person_ids)){
                return ['status' => 'error', 'message' => 'person_ids is empty after normalization.'];
            }

            // Buat struktur JSON sesuai dengan format JSON-RPC dari Postman
            $requestBody = [
                'jsonrpc' => '2.0',
                'method' => 'tools/call',
                'params' => [
                    'name' => 'get_person',
                    'arguments' => [
                        'person_ids' => $person_ids,
                        'domains' => ['id', 'email', 'name', 'phone', 'address'],
                        'format' => 'none'
                    ]
                ],
                'id' => 2
            ];

            // Kirim request dengan body JSON
            $response = $this->request('POST', '', ['json' => $requestBody]);

            // Convert response text ke array
            $data = $this->convert_response_text_to_array($response);
            // info('get_person', ['data' => $data]);

            // $endTime = microtime(true);
            // $diffTime = $endTime - $startTime;
            // info('get_person', ['diffTime' => $diffTime]);

            return ['status' => 'success', 'data' => $data];
        }
        catch(Throwable $th)
        {
            info('get_person error', ['error' => $th->getMessage()]);
            $this->controller->UpsertFailedLeadRecord([
                'function' => 'get_person',
                'type' => 'error',
                'description' => $th->getMessage(),
                'clean_file_id' => is_numeric($file_id)?$file_id:null,
                'person_ids' => json_encode($person_ids, JSON_UNESCAPED_SLASHES),
                'url' => $this->base_url,
                'leadspeek_type' => 'clean_id',
            ]);
            // $endTime = microtime(true);
            // $diffTime = $endTime - $startTime;
            // info('get_person', ['diffTime' => $diffTime]);
            return ['status' => 'error', 'message' => $th->getMessage()];
        }
    }

    /**
     * @param array $identities Array dari identities dari resolve_identities (untuk mendapatkan overall_quality_score)
     * @param array $profiles Array dari profiles yang akan dievaluasi
     * @param string|null $fullName Full name identifier untuk matching di STEP 1
     * @return array ['topProfile' => array|null, 'multiple_profile_found' => int] Profile dengan score tertinggi dan flag multiple profile
     */
    /*
        |--------------------------------------------------------------------------
        | Alur Process Penentuan Top Profile
        |--------------------------------------------------------------------------
        |
        | Keterangan:
        | multiple_profile_found:
        |   0 = tidak multiple (cukup yakin satu profile)
        |   1 = multiple (masih ambigu)
        |
        |--------------------------------------------------------------------------
        | STEP 1: Full Name Exact Match
        |--------------------------------------------------------------------------
        | Lakukan pengecekan apakah Full Name Identifier
        | sama persis dengan field 'name' yang diberikan oleh Watt.
        |
        | Jika ditemukan kecocokan:
        | - langsung gunakan profile tersebut
        | - set multiple_profile_found = 0
        |
        |--------------------------------------------------------------------------
        | STEP 2: Quality Score (overall_quality_score)
        |--------------------------------------------------------------------------
        | Lakukan pengecekan berdasarkan overall_quality_score
        | yang berasal dari resolve_identities.
        |
        | Ambil profile dengan quality score tertinggi.
        |
        | Jika hanya terdapat SATU profile dengan score tertinggi:
        | - gunakan profile tersebut
        | - set multiple_profile_found = 0
        |
        |--------------------------------------------------------------------------
        | STEP 3: Tie Quality Score (Score Sama)
        |--------------------------------------------------------------------------
        | Jika terdapat lebih dari satu profile dengan quality score yang sama:
        |
        | - Jika hanya terdapat 2 profile:
        |   1. Hitung bobot (weighted score) masing-masing profile
        |   2. Ambil profile dengan bobot terbesar sebagai topProfile
        |   3. Hitung GAP dalam persentase menggunakan rumus:
        |        gap = ((score_tertinggi - score_kedua) / score_tertinggi) * 100
        |
        |   4. Penentuan multiple_profile_found:
        |        - Jika gap < 60%  → multiple_profile_found = 1
        |          (kedua profile masih cukup dekat / ambigu)
        |
        |        - Jika gap ≥ 60% → multiple_profile_found = 0
        |          (profile teratas cukup dominan)
        |
        | - Jika terdapat lebih dari 2 profile:
        |   1. Hitung bobot seluruh profile
        |   2. Ambil profile dengan bobot terbesar sebagai topProfile
        |   3. Set multiple_profile_found = 1
        |      (karena lebih dari dua kandidat dianggap ambigu)
        |
        |--------------------------------------------------------------------------
        | Catatan:
        | - Sistem ini bertujuan menentukan DOMINANSI data,
        |   bukan probabilitas atau pembagian persentase total.
        | - Pendekatan GAP digunakan untuk mengukur selisih kekuatan
        |   antar profile, bukan kepastian absolut.
        |
    */
    public function getTopProfile($identities, $profiles, $fullName = null)
    {
        // info('public function getTopProfile', ['identities' => $identities, 'profiles' => $profiles, 'fullName' => $fullName]);

        if(empty($profiles) || !is_array($profiles)){
            return ['topProfile' => null, 'multiple_profile_found' => 0];
        }

        /* ------------- STEP 1 ------------- */
        // info('getTopProfile step 1 (1.1) START', ['fullName' => $fullName]);
        // Cek Full Name Identifier sama dengan Name yang di kasih watt
        if(!empty($fullName)){
            $fullName = trim(strtolower($fullName));
            // info('getTopProfile step 1 (1.2)', ['fullName' => $fullName]);
            foreach($profiles as $profile){
                $domains = $profile['domains'] ?? [];
                $names = $domains['name'] ?? [];
                // info('getTopProfile step 1 (1.3)', ['domains' => $domains, 'names' => $names]);
                
                foreach($names as $name){
                    // info('getTopProfile step 1 (1.4)', ['trim_strtolower_name' => trim(strtolower($name)), 'fullName' => $fullName]);
                    if(trim(strtolower($name)) === $fullName){
                        // info('getTopProfile step 1 MATCH (1.5)');
                        return ['topProfile' => $profile, 'multiple_profile_found' => 0];
                    }
                }
            }
        }
        // info('getTopProfile step 1 (1.6) END');
        /* ------------- STEP 1 ------------- */



        /* ------------- STEP 2 ------------- */
        // Buat mapping overall_quality_score dari identities berdasarkan person_id
        // info('getTopProfile step 2 (2.1.1) START', ['identities' => $identities]);
        $qualityScoreMap = [];
        if(!empty($identities) && is_array($identities)){
            // info('getTopProfile step 2 (2.1.2)');
            foreach($identities as $identity){
                $personId = $identity['person_id'] ?? null;
                $overallQualityScore = $identity['overall_quality_score'] ?? 0;
                // info('getTopProfile step 2 (2.1.3)', ['personId' => $personId, 'overallQualityScore' => $overallQualityScore]);
                if($personId !== null){
                    // Convert person_id ke string untuk memastikan matching (karena profiles menggunakan string)
                    $qualityScoreMap[(string)$personId] = (int) $overallQualityScore;
                    // info('getTopProfile step 2 (2.1.4)', ['qualityScoreMap' => $qualityScoreMap]);
                }
            }
        }
        // info('getTopProfile step 2 (2.1.5) END', ['qualityScoreMap' => $qualityScoreMap]);
        
        // Hitung quality score untuk setiap profile menggunakan overall_quality_score
        // info('getTopProfile step 2 (2.2.1) START', ['profiles' => $profiles]);
        $profilesWithScore = [];
        foreach($profiles as $profile){
            $personId = $profile['person_id'] ?? null;
            $score = 0;
            // info('getTopProfile step 2 (2.2.2)', ['personId' => $personId, 'qualityScoreMap' => $qualityScoreMap]);
            // Gunakan overall_quality_score dari identities jika ada
            if($personId !== null && isset($qualityScoreMap[(string)$personId])){
                $score = (int) $qualityScoreMap[(string)$personId];
                // info('getTopProfile step 2 (2.2.3)', ['score' => $score]);
            }
            
            $profilesWithScore[] = [
                'profile' => $profile,
                'score' => $score
            ];
            // info('getTopProfile step 2 (2.2.4)', ['score' => $score, 'profilesWithScore' => $profilesWithScore]);
        }
        // info('getTopProfile step 2 (2.2.5) END', ['profilesWithScore' => $profilesWithScore]);

        // info('getTopProfile step 2 (2.3.1)', ['profilesWithScore' => $profilesWithScore]);
        if(empty($profilesWithScore)){
            // info('getTopProfile step 2 (2.3.2)');
            return ['topProfile' => null, 'multiple_profile_found' => 0];
        }

        // Urutkan berdasarkan score tertinggi
        usort($profilesWithScore, function($a, $b){
            return $b['score'] - $a['score'];
        });
        $maxScore = $profilesWithScore[0]['score'];
        // info('getTopProfile step 2 (2.4.1)', ['maxScore' => $maxScore, 'profilesWithScore' => $profilesWithScore]);

        // Cek apakah ada lebih dari 1 profile dengan score yang sama
        $topScoredProfiles = array_filter($profilesWithScore, function($item) use ($maxScore){
            return $item['score'] == $maxScore;
        });
        // info('getTopProfile step 2 (2.5.1)', ['topScoredProfiles' => $topScoredProfiles]);

        // Jika hanya 1 profile dengan score tertinggi
        if(count($topScoredProfiles) == 1){
            // info('getTopProfile step 2 (2.6.1)');
            return ['topProfile' => $topScoredProfiles[0]['profile'], 'multiple_profile_found' => 0];
        }
        /* ------------- STEP 2 ------------- */
        


        /* ------------- STEP 3 ------------- */
        // Jika lebih dari 1 profile dengan score yang sama
        $topScoredProfiles = array_values($topScoredProfiles);
        // info('getTopProfile step 3 (3.1)', ['topScoredProfiles' => $topScoredProfiles, 'count_topScoredProfiles' => count($topScoredProfiles)]);
        
        // Weighting untuk setiap domain
        $domainWeights = ['email' => 5, 'phone' => 2, 'address' => 2, 'name' => 1];
        // info('getTopProfile step 3 (3.2)', ['domainWeights' => $domainWeights]);

        // Hitung weighted score untuk setiap profile
        // info('getTopProfile step 3 (3.3.1) START - only 2 profiles');
        $maxWeightedScore = 0;
        foreach($topScoredProfiles as $item){
            $profile = $item['profile'];
            $domains = $profile['domains'] ?? [];
            $weightedScore = (count($domains['email'] ?? []) * $domainWeights['email']) +
                             (count($domains['phone'] ?? []) * $domainWeights['phone']) +
                             (count($domains['address'] ?? []) * $domainWeights['address']) +
                             (count($domains['name'] ?? []) * $domainWeights['name']);
            // info('getTopProfile step 3 (3.3.2)', [
            //     'email' => count($domains['email'] ?? []) . " x " . $domainWeights['email'] . " = " . (count($domains['email'] ?? []) * $domainWeights['email']),
            //     'phone' => count($domains['phone'] ?? []) . " x " . $domainWeights['phone'] . " = " . (count($domains['phone'] ?? []) * $domainWeights['phone']),
            //     'address' => count($domains['address'] ?? []) . " x " . $domainWeights['address'] . " = " . (count($domains['address'] ?? []) * $domainWeights['address']),
            //     'name' => count($domains['name'] ?? []) . " x " . $domainWeights['name'] . " = " . (count($domains['name'] ?? []) * $domainWeights['name']),
            //     'weightedScore' => $weightedScore,
            //     'maxWeightedScore' => $maxWeightedScore,
            // ]);
            if($weightedScore > $maxWeightedScore){
                $maxWeightedScore = $weightedScore;
                // info('getTopProfile step 3 (3.3.3)', ['maxWeightedScore' => $maxWeightedScore, 'weightedScore' => $weightedScore]);
            }
        }
        // info('getTopProfile step 3 (3.3.4)', ['maxWeightedScore' => $maxWeightedScore]);

        // info('getTopProfile step 3 (3.3.5)');
        $profilesWithWeight = [];
        foreach($topScoredProfiles as $item){
            $profile = $item['profile'];
            $domains = $profile['domains'] ?? [];
            $weightedScore = (count($domains['email'] ?? []) * $domainWeights['email']) +
                             (count($domains['phone'] ?? []) * $domainWeights['phone']) +
                             (count($domains['address'] ?? []) * $domainWeights['address']) +
                             (count($domains['name'] ?? []) * $domainWeights['name']);
            // info('getTopProfile step 3 (3.3.6)', [
            //     'email' => count($domains['email'] ?? []) . " x " . $domainWeights['email'] . " = " . (count($domains['email'] ?? []) * $domainWeights['email']),
            //     'phone' => count($domains['phone'] ?? []) . " x " . $domainWeights['phone'] . " = " . (count($domains['phone'] ?? []) * $domainWeights['phone']),
            //     'address' => count($domains['address'] ?? []) . " x " . $domainWeights['address'] . " = " . (count($domains['address'] ?? []) * $domainWeights['address']),
            //     'name' => count($domains['name'] ?? []) . " x " . $domainWeights['name'] . " = " . (count($domains['name'] ?? []) * $domainWeights['name']),
            //     'weightedScore' => $weightedScore,
            //     'profile' => $profile,
            //     'domains' => $domains,
            // ]);
            $profilesWithWeight[] = [
                'profile' => $profile,
                'weightedScore' => $weightedScore
            ];
            // info('getTopProfile step 3 (3.3.7)', ['profilesWithWeight' => $profilesWithWeight]);
        }

        // Urutkan berdasarkan weightedScore tertinggi (GAP-based)
        usort($profilesWithWeight, function($a, $b){
            return $b['weightedScore'] - $a['weightedScore'];
        });
        // info('getTopProfile step 3 (3.3.8)', ['profilesWithWeight' => $profilesWithWeight]);

        // hitung multiple_profile_found jika terdapat 2 data profile
        $multipleProfileFound = 1;
        if(count($topScoredProfiles) == 2){
            // Ambil dua skor teratas
            $score1 = $profilesWithWeight[0]['weightedScore']; // tertinggi
            $score2 = $profilesWithWeight[1]['weightedScore']; // kedua
            // info('getTopProfile step 3 (3.3.9)', ['score1' => $score1, 'score2' => $score2]);

            // Hitung GAP eksplisit
            $thresholdGap = 60;
            $gap = 0;
            if($score1 > 0){
                $gap = (($score1 - $score2) / $score1) * 100;
            }
            // info('getTopProfile step 3 (3.3.10)', ['gap' => "{$gap} %"]);

            // Penentuan multiple_profile_found
            $multipleProfileFound = ($gap >= $thresholdGap) ? 0 : 1;
            // info('getTopProfile step 3 (3.3.11)', ['multipleProfileFound' => $multipleProfileFound]);
        }
        /* ------------- STEP 3 ------------- */

        // return result
        return ['topProfile' => $profilesWithWeight[0]['profile'], 'multiple_profile_found' => $multipleProfileFound];
    }
    // =======================================================================
    // PUBLIC METHODS END
    // =======================================================================
}