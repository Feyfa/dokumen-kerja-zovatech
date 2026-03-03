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
     * @param $leadspeek_api_id ID campaign dari leadspeek
     * @param $leadspeek_type Tipe leadspeek (local, enhance)
     * @return array
     */
    public function get_resolve_identities(array $data, $leadspeek_api_id = null, $leadspeek_type = null, $context = null)
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
            // info('get_resolve_identities requestBody', ['requestBody' => $requestBody]);
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
                'leadspeek_api_id' => $leadspeek_api_id,
                'email_encrypt' => $email,
                'url' => $this->base_url,
                'leadspeek_type' => $leadspeek_type,
            ]);
            
            // Track error analytics berdasarkan context
            if($context === 'md5'){
                $this->controller->UpsertReportAnalytics($leadspeek_api_id, $leadspeek_type, 'getleadfailed');
                $this->controller->UpsertReportAnalytics($leadspeek_api_id, $leadspeek_type, 'getleadfailed_wattdatamd5');
            }elseif ($context === 'pii'){
                $this->controller->UpsertReportAnalytics($leadspeek_api_id, $leadspeek_type, 'getleadfailed');
                $this->controller->UpsertReportAnalytics($leadspeek_api_id, $leadspeek_type, 'getleadfailed_wattdatapii');
            }
            
            // $endTime = microtime(true);
            // $diffTime = $endTime - $startTime;
            // info('get_resolve_identities', ['diffTime' => $diffTime]);
            return ['status' => 'error', 'message' => $th->getMessage()];
        }
    }

    /**
     * untuk mendapatkan data person dari watt data
     * @param array $person_ids Array of person IDs ["12345", "56789"]
     * @param $leadspeek_api_id ID campaign dari leadspeek
     * @param $leadspeek_type Tipe leadspeek (site/enhance/local/b2b/dll)
     * @param $context Context untuk tracking analytics ('md5' atau 'pii')
     * @return array
     */
    public function get_person(array $person_ids, $leadspeek_api_id = null, $leadspeek_type = null, $context = null, $domains = ['id', 'email', 'name', 'phone', 'address'])
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
                        'domains' => $domains,
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
                'leadspeek_api_id' => $leadspeek_api_id,
                'email_encrypt' => json_encode($person_ids, JSON_UNESCAPED_SLASHES),
                'url' => $this->base_url,
                'leadspeek_type' => $leadspeek_type,
            ]);
            
            // Track error analytics berdasarkan context
            if($context === 'md5'){
                $this->controller->UpsertReportAnalytics($leadspeek_api_id, $leadspeek_type, 'getleadfailed');
                $this->controller->UpsertReportAnalytics($leadspeek_api_id, $leadspeek_type, 'getleadfailed_wattdatamd5');
            }elseif($context === 'pii'){
                $this->controller->UpsertReportAnalytics($leadspeek_api_id, $leadspeek_type, 'getleadfailed');
                $this->controller->UpsertReportAnalytics($leadspeek_api_id, $leadspeek_type, 'getleadfailed_wattdatapii');
            }
            
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
                $names = $domains['names'] ?? [];
                // info('getTopProfile step 1 (1.3)', ['domains' => $domains, 'names' => $names]);
                
                foreach($names as $nameItem){
                    // names[] adalah object { first_name, last_name }, gabungkan jadi full name
                    $nameFirst = isset($nameItem['first_name']) ? $nameItem['first_name'] : '';
                    $nameLast = isset($nameItem['last_name']) ? $nameItem['last_name'] : '';
                    $name = trim($nameFirst . ' ' . $nameLast);
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
        $domainWeights = ['emails' => 5, 'phones' => 2, 'addresses' => 2, 'names' => 1];
        // info('getTopProfile step 3 (3.2)', ['domainWeights' => $domainWeights]);

        // Hitung weighted score untuk setiap profile
        // info('getTopProfile step 3 (3.3.1) START - only 2 profiles');
        $maxWeightedScore = 0;
        foreach($topScoredProfiles as $item){
            $profile = $item['profile'];
            $domains = $profile['domains'] ?? [];
            $weightedScore = (count($domains['emails'] ?? []) * $domainWeights['emails']) +
                             (count($domains['phones'] ?? []) * $domainWeights['phones']) +
                             (count($domains['addresses'] ?? []) * $domainWeights['addresses']) +
                             (count($domains['names'] ?? []) * $domainWeights['names']);
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
            $weightedScore = (count($domains['emails'] ?? []) * $domainWeights['emails']) +
                             (count($domains['phones'] ?? []) * $domainWeights['phones']) +
                             (count($domains['addresses'] ?? []) * $domainWeights['addresses']) +
                             (count($domains['names'] ?? []) * $domainWeights['names']);
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

    /**
     * Wrapper function yang menjalankan 3 step sekaligus:
     *   1. get_resolve_identities  → cari person_id berdasarkan $identifiers
     *   2. get_person              → ambil detail profile berdasarkan person_id
     *   3. getTopProfile           → pilih 1 profile terbaik dari semua kandidat
     *
     * Lalu extract basic info dari topProfile dan return sebagai flat array.
     *
     * @param  array       $identifiers        Array multi_identifiers untuk dikirim ke resolve_identities.
     *                                         Contoh MD5 email : [['id_type'=>'email',  'hash_type'=>'md5',       'value'=>[$md5]]]
     *                                         Contoh address   : [['id_type'=>'address','hash_type'=>'plaintext', 'value'=>[$addr]]]
     * @param  mixed       $leadspeek_api_id   ID campaign (opsional, untuk error logging)
     * @param  mixed       $leadspeek_type     Tipe leadspeek (opsional, untuk error logging)
     * @return array Basic info array jika ditemukan, empty array [] jika tidak
     */
    public function getWattBasicInfo(array $identifiers, $leadspeek_api_id = null, $leadspeek_type = null, $isAdvanced = false)
    {
        // Ambil value pertama dari identifiers untuk keperluan logging
        $logKey = (isset($identifiers[0]['value'][0])) ? $identifiers[0]['value'][0] : '';

        // Detect context (MD5 atau PII) dari identifiers
        // Jika id_type = 'email' dan hash_type = 'md5' -> MD5 context
        // Jika id_type = 'address' -> PII context
        $context = null;
        if(isset($identifiers[0]['id_type']) && isset($identifiers[0]['hash_type'])){
            if($identifiers[0]['id_type'] === 'email' && $identifiers[0]['hash_type'] === 'md5'){
                $context = 'md5';
            }elseif ($identifiers[0]['id_type'] === 'address'){
                $context = 'pii';
            }
        }

        // -----------------------------------------------------------------------
        // STEP 1: resolve_identities — cari person_id dari identifiers
        // -----------------------------------------------------------------------
        $resolveResult = $this->get_resolve_identities(
            $identifiers,
            $leadspeek_api_id,
            $leadspeek_type,
            $context
        );

        // Jika API error (exception terjadi di dalam get_resolve_identities)
        if(!isset($resolveResult['status']) || $resolveResult['status'] === 'error'){
            $this->controller->UpsertFailedLeadRecord([
                'function' => 'getWattBasicInfo',
                'type' => 'error',
                'description' => isset($resolveResult['message']) ? $resolveResult['message'] : 'resolve_identities API error',
                'leadspeek_api_id' => $leadspeek_api_id,
                'email_encrypt' => $logKey,
                'leadspeek_type' => $leadspeek_type,
            ]);
            return [];
        }
        // Jika API sukses tapi data kosong
        if(empty($resolveResult['data'])){
            $this->controller->UpsertFailedLeadRecord([
                'function' => 'getWattBasicInfo',
                'type' => 'empty',
                'description' => 'resolve_identities succeeded but returned empty data',
                'leadspeek_api_id' => $leadspeek_api_id,
                'email_encrypt' => $logKey,
                'leadspeek_type' => $leadspeek_type,
            ]);
            return [];
        }

        // Ambil list identities — Response structure: data.identities[]
        $resolvedIdentities = (isset($resolveResult['data']['identities']) && is_array($resolveResult['data']['identities'])) ? $resolveResult['data']['identities'] : [];
        if(empty($resolvedIdentities)){
            $this->controller->UpsertFailedLeadRecord([
                'function' => 'getWattBasicInfo',
                'type' => 'empty',
                'description' => 'resolve_identities returned empty identities',
                'leadspeek_api_id' => $leadspeek_api_id,
                'email_encrypt' => $logKey,
                'leadspeek_type' => $leadspeek_type,
            ]);
            return [];
        }

        // Kumpulkan semua person_id
        $personIds = array_column($resolvedIdentities, 'person_id');
        $personIds = array_filter(array_map('strval', $personIds));
        $personIds = array_values($personIds);
        if(empty($personIds)){
            $this->controller->UpsertFailedLeadRecord([
                'function' => 'getWattBasicInfo',
                'type' => 'empty',
                'description' => 'No person_id found from identities',
                'leadspeek_api_id' => $leadspeek_api_id,
                'email_encrypt' => $logKey,
                'leadspeek_type' => $leadspeek_type,
            ]);
            return [];
        }

        // -----------------------------------------------------------------------
        // STEP 2: get_person — ambil detail profile berdasarkan person_id
        // -----------------------------------------------------------------------
        $requestDomains = ['id', 'email', 'name', 'phone', 'address'];
        if ($isAdvanced) {
            $requestDomains = ['id', 'email', 'name', 'phone', 'address', 'demographic', 'financial', 'household', 'lifestyle', 'interest', 'content', 'purchase'];
        }
        $personResult = $this->get_person(
            $personIds,
            $leadspeek_api_id,
            $leadspeek_type,
            $context,
            $requestDomains
        );

        // Jika API error (exception terjadi di dalam get_person)
        if(!isset($personResult['status']) || $personResult['status'] === 'error'){
            $this->controller->UpsertFailedLeadRecord([
                'function' => 'getWattBasicInfo',
                'type' => 'error',
                'description' => isset($personResult['message']) ? $personResult['message'] : 'get_person API error',
                'leadspeek_api_id' => $leadspeek_api_id,
                'email_encrypt' => $logKey,
                'leadspeek_type' => $leadspeek_type,
            ]);
            return [];
        }
        // Jika API sukses tapi data kosong
        if(empty($personResult['data'])){
            $this->controller->UpsertFailedLeadRecord([
                'function' => 'getWattBasicInfo',
                'type' => 'empty',
                'description' => 'get_person succeeded but returned empty data',
                'leadspeek_api_id' => $leadspeek_api_id,
                'email_encrypt' => $logKey,
                'leadspeek_type' => $leadspeek_type,
            ]);
            return [];
        }

        // Ambil profiles — Response structure: data.profiles[]
        $profiles = (isset($personResult['data']['profiles']) && is_array($personResult['data']['profiles'])) ? $personResult['data']['profiles'] : [];
        if(empty($profiles)){
            $this->controller->UpsertFailedLeadRecord([
                'function' => 'getWattBasicInfo',
                'type' => 'empty',
                'description' => 'get_person returned empty profiles',
                'leadspeek_api_id' => $leadspeek_api_id,
                'email_encrypt' => $logKey,
                'leadspeek_type' => $leadspeek_type,
            ]);
            return [];
        }

        // -----------------------------------------------------------------------
        // STEP 3: getTopProfile — pilih 1 profile terbaik dari semua kandidat
        // -----------------------------------------------------------------------
        $topProfileResult = $this->getTopProfile(
            $resolvedIdentities,
            $profiles
        );

        $topProfile = isset($topProfileResult['topProfile']) ? $topProfileResult['topProfile'] : null;
        if(empty($topProfile)){
            $this->controller->UpsertFailedLeadRecord([
                'function' => 'getWattBasicInfo',
                'type' => 'empty',
                'description' => 'getTopProfile returned no profile',
                'leadspeek_api_id' => $leadspeek_api_id,
                'email_encrypt' => $logKey,
                'leadspeek_type' => $leadspeek_type,
            ]);
            return [];
        }

        // -----------------------------------------------------------------------
        // STEP 4: Extract basic info dari topProfile
        // Struktur domain dari Watt Data (plural key + object per item):
        //   names[]     → { first_name, last_name }
        //   emails[]    → { email_address, opted_in }
        //   phones[]    → { phone_number, phone_type, ... }
        //   addresses[] → { address_primary, address_secondary, city, state, zip, ... }
        // -----------------------------------------------------------------------
        $domains = (isset($topProfile['domains']) && is_array($topProfile['domains'])) ? $topProfile['domains'] : [];

        // Nama
        $names = (isset($domains['names']) && is_array($domains['names'])) ? $domains['names'] : [];
        $wattFirstName = isset($names[0]['first_name']) ? $names[0]['first_name'] : '';
        $wattLastName = isset($names[0]['last_name']) ? $names[0]['last_name'] : '';

        // Email (all, as comma-separated string)
        $emails = (isset($domains['emails']) && is_array($domains['emails'])) ? $domains['emails'] : [];
        $wattEmailArr = [];
        foreach ($emails as $emailItem) {
            if (isset($emailItem['email_address']) && trim($emailItem['email_address']) != '') {
                $wattEmailArr[] = trim($emailItem['email_address']);
            }
        }
        $wattEmail = implode(',', $wattEmailArr);

        // Phone (all, as comma-separated string)
        $phones = (isset($domains['phones']) && is_array($domains['phones'])) ? $domains['phones'] : [];
        $wattPhoneArr = [];
        foreach ($phones as $phoneItem) {
            if (isset($phoneItem['phone_number']) && trim($phoneItem['phone_number']) != '') {
                $wattPhoneArr[] = trim($phoneItem['phone_number']);
            }
        }
        $wattPhone = implode(',', $wattPhoneArr);

        // Address
        $addresses = (isset($domains['addresses']) && is_array($domains['addresses'])) ? $domains['addresses'] : [];
        $wattAddress1 = isset($addresses[0]['address_primary']) ? $addresses[0]['address_primary'] : '';
        $wattAddress2 = isset($addresses[0]['address_secondary']) ? $addresses[0]['address_secondary'] : '';
        $wattCity = isset($addresses[0]['city']) ? $addresses[0]['city'] : '';
        $wattState = isset($addresses[0]['state']) ? $addresses[0]['state'] : '';
        $wattZipcode = isset($addresses[0]['zip']) ? $addresses[0]['zip'] : '';

        $result = [
            'FirstName' => $wattFirstName,
            'LastName' => $wattLastName,
            'Email' => $wattEmail,
            'Phone' => $wattPhone,
            'Address1' => $wattAddress1,
            'Address2' => $wattAddress2,
            'City' => $wattCity,
            'State' => $wattState,
            'Zipcode' => $wattZipcode,
        ];

        // -----------------------------------------------------------------------
        // STEP 4b: Extract advanced fields if $isAdvanced = true
        // -----------------------------------------------------------------------
        if ($isAdvanced) {
            $boolToStr = function($value) { return $value === true ? '1' : ''; };

            $demographicDomain = isset($domains['demographic']) && is_array($domains['demographic']) ? $domains['demographic'] : [];
            $financialDomain   = isset($domains['financial'])   && is_array($domains['financial'])   ? $domains['financial']   : [];
            $householdDomain   = isset($domains['household'])   && is_array($domains['household'])   ? $domains['household']   : [];
            $lifestyleDomain   = isset($domains['lifestyle'])   && is_array($domains['lifestyle'])   ? $domains['lifestyle']   : [];
            $interestDomain    = isset($domains['interest'])    && is_array($domains['interest'])    ? $domains['interest']    : [];
            $contentDomain     = isset($domains['content'])     && is_array($domains['content'])     ? $domains['content']     : [];
            $purchaseDomain    = isset($domains['purchase'])    && is_array($domains['purchase'])    ? $domains['purchase']    : [];

            // Mobile phones with DNC from phones[]
            $wattPhone1    = isset($phones[0]['phone_number']) ? trim($phones[0]['phone_number']) : '';
            $wattPhone1Dnc = isset($phones[0]['do_not_call'])  ? $boolToStr($phones[0]['do_not_call']) : '';
            $wattPhone2    = isset($phones[1]['phone_number']) ? trim($phones[1]['phone_number']) : '';
            $wattPhone2Dnc = isset($phones[1]['do_not_call'])  ? $boolToStr($phones[1]['do_not_call']) : '';
            $wattPhone3    = isset($phones[2]['phone_number']) ? trim($phones[2]['phone_number']) : '';
            $wattPhone3Dnc = isset($phones[2]['do_not_call'])  ? $boolToStr($phones[2]['do_not_call']) : '';
            // Strip +1 prefix from phones
            foreach ([&$wattPhone1, &$wattPhone2, &$wattPhone3] as &$ph) {
                if (substr($ph, 0, 2) === '+1') { $ph = substr($ph, 2); }
            }
            unset($ph);

            $result['Mobile_Phone_1']     = $wattPhone1;
            $result['Mobile_Phone_1_DNC'] = $wattPhone1Dnc;
            $result['Mobile_Phone_2']     = $wattPhone2;
            $result['Mobile_Phone_2_DNC'] = $wattPhone2Dnc;
            $result['Mobile_Phone_3']     = $wattPhone3;
            $result['Mobile_Phone_3_DNC'] = $wattPhone3Dnc;

            // Demographic
            $result['Gender_aux']      = isset($demographicDomain['gender']['value'])          ? (string)$demographicDomain['gender']['value']          : '';
            $result['Marital_Status']  = isset($demographicDomain['marital_status']['value'])   ? (string)$demographicDomain['marital_status']['value']   : '';

            // Financial
            $result['Income_HH']               = isset($householdDomain['household_income_range']['value'])     ? (string)$householdDomain['household_income_range']['value']     : '';
            $result['Net_Worth_HH']            = isset($householdDomain['household_net_worth_range']['value'])  ? (string)$householdDomain['household_net_worth_range']['value']  : '';
            $result['Num_Children_HH']         = isset($householdDomain['number_children_in_household']['value']) ? (string)$householdDomain['number_children_in_household']['value'] : '';
            $result['Children_HH']             = isset($householdDomain['has_children_in_household']['value'])  ? $boolToStr($householdDomain['has_children_in_household']['value'])  : '';
            $result['Child_Aged_0_3_HH']       = isset($householdDomain['has_child_aged_0_3_in_household']['value'])   ? $boolToStr($householdDomain['has_child_aged_0_3_in_household']['value'])   : '';
            $result['Child_Aged_4_6_HH']       = isset($householdDomain['has_child_aged_4_6_in_household']['value'])   ? $boolToStr($householdDomain['has_child_aged_4_6_in_household']['value'])   : '';
            $result['Child_Aged_7_9_HH']       = isset($householdDomain['has_child_aged_7_9_in_household']['value'])   ? $boolToStr($householdDomain['has_child_aged_7_9_in_household']['value'])   : '';
            $result['Child_Aged_10_12_HH']     = isset($householdDomain['has_child_aged_10_12_in_household']['value'])  ? $boolToStr($householdDomain['has_child_aged_10_12_in_household']['value'])  : '';
            $result['Child_Aged_13_18_HH']     = isset($householdDomain['has_child_aged_13_18_in_household']['value'])  ? $boolToStr($householdDomain['has_child_aged_13_18_in_household']['value'])  : '';
            $result['Credit_Range']            = isset($financialDomain['credit_rating_range']['value'])  ? (string)$financialDomain['credit_rating_range']['value']  : '';
            $result['Likely_Charitable_Donor'] = isset($financialDomain['is_charitable_donor']['value'])  ? $boolToStr($financialDomain['is_charitable_donor']['value'])  : '';

            // Content
            $result['Magazine_Subscriber'] = isset($contentDomain['subscribes_magazines']['value']) ? $boolToStr($contentDomain['subscribes_magazines']['value']) : '';

            // Lifestyle
            $result['Home_Owner']      = isset($lifestyleDomain['is_home_owner']['value']) ? ($lifestyleDomain['is_home_owner']['value'] === true ? 'Home Owner' : '') : '';
            $result['Pet_Owner']       = isset($lifestyleDomain['is_pet_owner']['value'])       ? $boolToStr($lifestyleDomain['is_pet_owner']['value'])       : '';
            $result['Travel_Vacation'] = isset($lifestyleDomain['is_vacation_traveler']['value'])? $boolToStr($lifestyleDomain['is_vacation_traveler']['value'])  : '';
            $result['DIY']             = isset($lifestyleDomain['practices_diy']['value'])       ? $boolToStr($lifestyleDomain['practices_diy']['value'])          : '';

            // Interest
            $result['Cooking']          = isset($interestDomain['interested_cooking']['value'])     ? $boolToStr($interestDomain['interested_cooking']['value'])     : '';
            $result['Gardening']        = isset($interestDomain['interested_gardening']['value'])   ? $boolToStr($interestDomain['interested_gardening']['value'])   : '';
            $result['Music']            = isset($interestDomain['interested_music']['value'])       ? $boolToStr($interestDomain['interested_music']['value'])       : '';
            $result['Fitness']          = isset($interestDomain['interested_fitness']['value'])     ? $boolToStr($interestDomain['interested_fitness']['value'])     : '';
            $result['Photography']      = isset($interestDomain['interested_photography']['value']) ? $boolToStr($interestDomain['interested_photography']['value']) : '';
            $result['Charity_Interest'] = isset($interestDomain['interested_charity']['value'])     ? $boolToStr($interestDomain['interested_charity']['value'])     : '';
            $result['Epicurean']        = isset($interestDomain['interested_epicurean']['value'])   ? $boolToStr($interestDomain['interested_epicurean']['value'])   : '';

            // Purchase
            $result['Books']                  = isset($purchaseDomain['purchased_books']['value'])                  ? $boolToStr($purchaseDomain['purchased_books']['value'])                  : '';
            $result['Health_Beauty_Products'] = isset($purchaseDomain['purchased_health_beauty_products']['value']) ? $boolToStr($purchaseDomain['purchased_health_beauty_products']['value']) : '';

            // Fields not available in WattData — always empty
            $result['Age_aux']               = '';
            $result['Birth_Year_aux']        = '';
            $result['Generation']            = '';
            $result['Occupation_Category']   = '';
            $result['Occupation_Type']       = '';
            $result['Occupation_Detail']     = '';
            $result['Income_Midpts_HH']      = '';
            $result['Net_Worth_Midpt_HH']    = '';
            $result['Discretionary_Income']  = '';
            $result['Num_Adults_HH']         = '';
            $result['Num_Persons_HH']        = '';
            $result['Voter']                 = '';
            $result['Urbanicity']            = '';
            $result['Dwelling_Type']         = '';
            $result['Home_Price']            = '';
            $result['Home_Value']            = '';
            $result['Median_Home_Value']     = '';
            $result['Length_of_Residence']   = '';
            $result['Cbsa']                  = '';
            $result['Census_Block']          = '';
            $result['Census_Block_Group']    = '';
            $result['Census_Tract']          = '';
            $result['Credit_Midpts']         = '';
            $result['HasEmail']              = '';
            $result['HasPhone']              = '';
        }

        return $result;
    }

    // =======================================================================
    // PUBLIC METHODS END
    // =======================================================================
}