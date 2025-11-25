<?php

/* MAIN FUNCTION YANG AKAN DIGUNAKAN DI CONTROLLER LAIN */
public function true_list_validation($email,$param = []) {
    $maxRetries = config('services.truelist.max_retries', 5); // Get from config or default to 5
    $attempt = 0;
    $baseDelay = 1; // Base delay in seconds
    
    while ($attempt < $maxRetries) {
        try {
            // Get current rate limit (dynamic or from config)
            $rateLimit = $this->getTrueListRateLimit();
            
            // Wait for rate limit availability before making the API call
            // Rate limit can be set via TRUE_LIST_RATE_LIMIT_PER_SECOND env variable
            // or will be dynamically discovered from API response headers
            $this->waitForTrueListRateLimit($rateLimit);
            
            $http = new Client([
                'timeout' => 30, // Set a reasonable timeout
                'connect_timeout' => 10,
            ]);
            $appkey = config('services.truelist.appkey');
            $apiURL = config('services.truelist.endpoint') . '/v1/verify_inline?email=' . urlencode($email);
            $options = [
                'headers' => [
                    'Authorization' =>  $appkey
                ]
            ];
            $response = $http->post($apiURL,$options);
            // Try to extract and store rate limit info from response headers
            $this->updateTrueListRateLimitFromHeaders($response);
            
            $response_decoded = json_decode($response->getBody());
            if(isset($response_decoded->emails[0]->email_state) && ($response_decoded->emails[0]->email_state != "ok") && ($response_decoded->emails[0]->email_state != "email_invalid")){
                if (isset($param['leadspeek_api_id']) && isset($param['leadspeek_type']) && isset($param['md5param'])) {
                    $this->UpsertFailedLeadRecord([
                        'function' => __FUNCTION__,
                        'type' => 'blocked',
                        'blocked_type' => 'truelist',
                        'description' => 'Truelist Status: ' . $response_decoded->emails[0]->email_state . '|Email',
                        'leadspeek_api_id' => $param['leadspeek_api_id'],
                        'email_encrypt' => $param['md5param'],
                        'leadspeek_type' => $param['leadspeek_type'],
                        'email' => $email,
                        'status' => $response_decoded->emails[0]->email_state,
                    ]);
                }

                if (isset($param['leadspeek_api_id'])) {
                    /** REPORT ANALYTIC */
                    $this->UpsertReportAnalytics($param['leadspeek_api_id'], 'enhance', 'truelist_details', $response_decoded->emails[0]->email_state);
                    /** REPORT ANALYTIC */
                }

                // Try ZB validation as fallback
                Log::info("TrueList not OK, trying ZB validation as fallback", [
                    'email' => $email,
                    'leadspeek_api_id' => $param['leadspeek_api_id'] ?? null
                ]);
                
                // Add fallback_from parameter to track this is a fallback call
                $zbParam = $param;
                $zbParam['fallback_from'] = 'truelist';
                
                $zbResult = $this->zb_validation($email, $zbParam);
                if ($zbResult !== "") {
                    Log::info("ZB validation successful as fallback", [
                        'email' => $email,
                        'leadspeek_api_id' => $param['leadspeek_api_id'] ?? null
                    ]);
                    return $zbResult;
                }
                
                return "";
            }
            return $response_decoded;
            
        } catch (RequestException $e) {
            if (isset($param['leadspeek_api_id']) && isset($param['leadspeek_type']) && isset($param['md5param'])) {
                $this->UpsertFailedLeadRecord([
                    'function' => 'true_list_validation',
                    'type' => 'error',
                    'blocked_type' => 'truelist',
                    'description' => json_encode($e->getMessage()),
                    'leadspeek_api_id' => $param['leadspeek_api_id'],
                    'email_encrypt' => $param['md5param'],
                    'leadspeek_type' => $param['leadspeek_type'],
                    'email' => $email,
                ]);
            }
            return "";
        } catch (Exception $e) {
            $attempt++;
            
            // Try to get status code from any exception
            $statusCode = 0;
            $retryAfter = null;
            
            // Check if exception has a response (Guzzle exceptions)
            try {
                // Check if this is a Guzzle exception with response
                if ($e instanceof \GuzzleHttp\Exception\RequestException) {
                    if ($e->hasResponse()) {
                        $response = $e->getResponse();
                        $statusCode = $response->getStatusCode();
                        
                        // Try to extract rate limit info from error response headers
                        $this->updateTrueListRateLimitFromHeaders($response);
                        
                        // Check for Retry-After header
                        $headers = $response->getHeaders();
                        if (isset($headers['Retry-After'])) {
                            $retryAfter = (int) $headers['Retry-After'][0];
                        }
                    }
                }
            } catch (\Exception $ex) {
                // If we can't get status code, continue with statusCode = 0
            }
            
            // ONLY retry if status code is 429 (Rate Limit)
            if ($statusCode !== 429) {
                // Log TrueList error
                Log::error("TrueList API Error: Not retrying, trying ZB fallback", [
                    'email' => $email,
                    'status_code' => $statusCode,
                    'error' => $e->getMessage(),
                    'leadspeek_api_id' => $param['leadspeek_api_id'] ?? null
                ]);
                
                if (isset($param['leadspeek_api_id']) && isset($param['leadspeek_type']) && isset($param['md5param'])) {
                    $this->UpsertFailedLeadRecord([
                        'function' => 'true_list_validation',
                        'type' => 'error',
                        'blocked_type' => 'truelist',
                        'description' => json_encode([
                            'status_code' => $statusCode,
                            'message' => $e->getMessage()
                        ]),
                        'leadspeek_api_id' => $param['leadspeek_api_id'],
                        'email_encrypt' => $param['md5param'],
                        'leadspeek_type' => $param['leadspeek_type'],
                        'email' => $email,
                    ]);
                }

                // Try ZB validation as fallback
                Log::info("TrueList failed, trying ZB validation as fallback", [
                    'email' => $email,
                    'leadspeek_api_id' => $param['leadspeek_api_id'] ?? null
                ]);
                
                // Add fallback_from parameter to track this is a fallback call
                $zbParam = $param;
                $zbParam['fallback_from'] = 'truelist';
                
                $zbResult = $this->zb_validation($email, $zbParam);
                if ($zbResult !== "") {
                    Log::info("ZB validation successful as fallback", [
                        'email' => $email,
                        'leadspeek_api_id' => $param['leadspeek_api_id'] ?? null
                    ]);
                    return $zbResult;
                }
                
                return "";
            }
            
            // Check if max retries reached (only for 429 errors)
            if ($attempt >= $maxRetries) {
                Log::warning("TrueList API Error: Max retries reached", [
                    'email' => $email,
                    'status_code' => $statusCode,
                    'error' => $e->getMessage(),
                    'leadspeek_api_id' => $param['leadspeek_api_id'] ?? null,
                    'attempts' => $attempt
                ]);
                
                if (isset($param['leadspeek_api_id']) && isset($param['leadspeek_type']) && isset($param['md5param'])) {
                    $this->UpsertFailedLeadRecord([
                        'function' => 'true_list_validation',
                        'type' => 'rate_limit_exceeded',
                        'blocked_type' => 'truelist',
                        'description' => json_encode([
                            'status_code' => $statusCode,
                            'message' => $e->getMessage(),
                            'attempts' => $attempt
                        ]),
                        'leadspeek_api_id' => $param['leadspeek_api_id'],
                        'email_encrypt' => $param['md5param'],
                        'leadspeek_type' => $param['leadspeek_type'],
                        'email' => $email,
                    ]);
                }
                return "";
            }
            
            // Calculate delay: use Retry-After if available, otherwise exponential backoff
            if ($retryAfter !== null && $retryAfter > 0) {
                $delay = $retryAfter;
                $jitter = 0;
                
                Log::info("TrueList API: Using Retry-After header", [
                    'email' => $email,
                    'status_code' => $statusCode,
                    'attempt' => $attempt,
                    'retry_after_seconds' => $retryAfter,
                    'leadspeek_api_id' => $param['leadspeek_api_id'] ?? null
                ]);
            } else {
                // Exponential backoff with jitter
                $delay = $baseDelay * pow(2, $attempt - 1);
                $jitter = rand(0, 1000) / 1000;
                
                Log::info("TrueList API: Retrying with exponential backoff", [
                    'email' => $email,
                    'status_code' => $statusCode,
                    'error' => $e->getMessage(),
                    'attempt' => $attempt,
                    'delay_seconds' => ($delay + $jitter),
                    'leadspeek_api_id' => $param['leadspeek_api_id'] ?? null
                ]);
            }
            
            $totalDelay = ($delay + $jitter) * 1000000;
            usleep($totalDelay);
            continue;
        }
    }
    return "";
}
public function zb_validation($email, $param = []) {
    try {
        $http = new Client();
        $appkey = config('services.zb.appkey');
        $ipaddress = $param['ipaddress'] ?? "";

        $apiURL = config('services.zb.endpoint') . "?api_key=" . $appkey . '&email=' . urlencode($email) . '&ip_address=' . $ipaddress;
        $options = [];
        $response = $http->get($apiURL,$options);
        return json_decode($response->getBody());
    }catch (RequestException $e) {
        Log::error("ZeroBounce API Error", [
            'email' => $email,
            'error' => $e->getMessage(),
            'leadspeek_api_id' => $param['leadspeek_api_id'] ?? null
        ]);
        
        // Record ZB validation failure
        if (isset($param['leadspeek_api_id']) && isset($param['leadspeek_type']) && isset($param['md5param'])) {
            $this->UpsertFailedLeadRecord([
                'function' => 'zb_validation',
                'type' => 'error',
                'blocked_type' => 'zerobounce',
                'description' => json_encode([
                    'message' => $e->getMessage(),
                    'fallback_from' => $param['fallback_from'] ?? null
                ]),
                'leadspeek_api_id' => $param['leadspeek_api_id'],
                'email_encrypt' => $param['md5param'],
                'leadspeek_type' => $param['leadspeek_type'],
                'email' => $email,
            ]);
        }
        
        return "";
    }catch (Exception $e) {
        Log::error("ZeroBounce API Unexpected Error", [
            'email' => $email,
            'error' => $e->getMessage(),
            'leadspeek_api_id' => $param['leadspeek_api_id'] ?? null
        ]);
        
        // Record ZB validation failure
        if (isset($param['leadspeek_api_id']) && isset($param['leadspeek_type']) && isset($param['md5param'])) {
            $this->UpsertFailedLeadRecord([
                'function' => 'zb_validation',
                'type' => 'error',
                'blocked_type' => 'zerobounce',
                'description' => json_encode([
                    'message' => $e->getMessage(),
                    'fallback_from' => $param['fallback_from'] ?? null
                ]),
                'leadspeek_api_id' => $param['leadspeek_api_id'],
                'email_encrypt' => $param['md5param'],
                'leadspeek_type' => $param['leadspeek_type'],
                'email' => $email,
            ]);
        }
        
        return "";
    }
}
/* MAIN FUNCTION YANG AKAN DIGUNAKAN DI CONTROLLER LAIN */


$chkEmailExist = PersonEmail::select('person_emails.email','person_emails.id as emailID','person_emails.permission','p.lastEntry','p.uniqueID','p.firstName','p.lastName','p.id')
    ->join('persons as p','person_emails.person_id','=','p.id')
    ->where('person_emails.email_encrypt','=',$md5Final)
    ->orderBy('person_emails.id','desc')
    ->first();


/* PARTIALS DARI FUNCTION MAIN*/
/**
 * Get the current rate limit from cache or config
 * Supports dynamic rate limit adjustment based on API responses
 * 
 * @return int Current rate limit per second
 */
private function getTrueListRateLimit() {
    // Check if we have a dynamically discovered rate limit in cache
    $dynamicRateLimit = Cache::store('redis')->get('truelist_dynamic_rate_limit');
    
    if ($dynamicRateLimit !== null) {
        Log::info("TrueList: Using dynamically discovered rate limit", [
            'rate_limit' => $dynamicRateLimit,
            'source' => 'cache'
        ]);
        return $dynamicRateLimit;
    }
    
    // Fall back to configuration
    $configRateLimit = config('services.truelist.rate_limit_per_second', 10);
    Log::info("TrueList: Using configured rate limit", [
        'rate_limit' => $configRateLimit,
        'source' => 'config'
    ]);
    
    return $configRateLimit;
}

/**
 * Wait for rate limit availability using cache-based token bucket algorithm
 * This ensures we don't exceed TrueList API rate limits across all webhook instances
 * 
 * @param int $maxRequestsPerSecond Maximum requests allowed per second
 * @return void
 */
private function waitForTrueListRateLimit($maxRequestsPerSecond = 10) {
    $cacheKey = 'truelist_rate_limit_bucket';
    $currentTime = microtime(true);
    
    // Try to get or initialize the rate limit data
    $rateLimitData = Cache::store('redis')->get($cacheKey, [
        'tokens' => $maxRequestsPerSecond,
        'last_update' => $currentTime
    ]);
    
    $timePassed = $currentTime - $rateLimitData['last_update'];
    
    // Refill tokens based on time passed
    $tokensToAdd = $timePassed * $maxRequestsPerSecond;
    $rateLimitData['tokens'] = min($maxRequestsPerSecond, $rateLimitData['tokens'] + $tokensToAdd);
    $rateLimitData['last_update'] = $currentTime;
    
    // If we don't have a token available, wait
    if ($rateLimitData['tokens'] < 1) {
        $waitTime = (1 - $rateLimitData['tokens']) / $maxRequestsPerSecond;
        $waitMicroseconds = (int)($waitTime * 1000000);
        
        Log::info("TrueList Rate Limiter: Waiting before API call", [
            'wait_seconds' => $waitTime,
            'current_tokens' => $rateLimitData['tokens'],
            'rate_limit_per_second' => $maxRequestsPerSecond
        ]);
        
        usleep($waitMicroseconds);
        
        // Update tokens after waiting
        $rateLimitData['tokens'] = 1;
        $rateLimitData['last_update'] = microtime(true);
    }
    
    // Consume one token
    $rateLimitData['tokens'] -= 1;
    
    // Store back to cache (expires in 60 seconds as a safety measure)
    Cache::store('redis')->put($cacheKey, $rateLimitData, 60);
}

/**
 * Update rate limit dynamically based on API response headers
 * 
 * @param \Psr\Http\Message\ResponseInterface $response API response
 * @return void
 */
private function updateTrueListRateLimitFromHeaders($response) {
    // Check for common rate limit headers
    $headers = $response->getHeaders();
    
    // X-RateLimit-Limit: Total requests allowed in the time window
    if (isset($headers['X-RateLimit-Limit'])) {
        $limit = (int) $headers['X-RateLimit-Limit'][0];
        
        // Assume the limit is per second (or adjust based on X-RateLimit-Reset if available)
        if ($limit > 0) {
            Cache::store('redis')->put('truelist_dynamic_rate_limit', $limit, 3600); // Store for 1 hour
            
            Log::info("TrueList: Rate limit discovered from API headers", [
                'rate_limit' => $limit,
                'header' => 'X-RateLimit-Limit'
            ]);
        }
    }
    
    // Log remaining requests for monitoring
    if (isset($headers['X-RateLimit-Remaining'])) {
        $remaining = (int) $headers['X-RateLimit-Remaining'][0];
        Log::info("TrueList: Rate limit remaining", [
            'remaining' => $remaining
        ]);
    }
}
/* PARTIALS DARI FUNCTION MAIN*/