<?php
/**
 * Patent Analysis MVP - Patent Data Provider (Adapter Pattern)
 * 
 * This adapter handles fetching patent data.
 * For MVP, uses mock data; can be replaced with real API calls later.
 */

class PatentProvider {
    
    /**
     * Fetch patent data by number
     * 
     * @param string $patentNumber e.g., "US10123456B2"
     * @param string $scope "claims" or "claims+abstract"
     * @return array ['claims' => '...', 'abstract' => '...', 'meta' => [...]]
     */
    public static function fetchPatent($patentNumber, $scope = 'claims+abstract') {
        if (USE_MOCK_PATENT_API) {
            return self::mockFetchPatent($patentNumber, $scope);
        }
        
        return self::realFetchPatent($patentNumber, $scope);
    }

    /**
     * Transform a human-entered publication number into the normalized
     * format required by the Unified Patents API.
     * Returns the first transformed number or null on failure.
     */
    public static function transformPatentNumber($raw) {
        $rawArray = is_array($raw) ? $raw : [$raw];
        // determine request URL; skip proxy when running CLI or built-in server
        $useProxy = !in_array(php_sapi_name(), ['cli-server', 'cli']);
        if ($useProxy) {
            $host = $_SERVER['HTTP_HOST'] ?? 'localhost:8000';
            $url = 'http://' . $host . '/api.php?op=transform';
        } else {
            // call unified patents directly
            $url = 'https://api.unifiedpatents.com/helpers/transform-publication-numbers';
        }
        $payload = json_encode(['publications' => $rawArray]);
        $result = self::httpRequest($url, $payload, ['Content-Type: application/json'], 60);

        if (!$result || !is_array($result) || !isset($result[0]) || !is_string($result[0])) {
            Logger::info("transformPatentNumber API failed for raw=$raw, response=" . var_export($result, true));
            // fallback to simple local normalization
            $normalized = self::localNormalizeNumber($raw);
            Logger::info("transformPatentNumber local fallback raw=$raw -> $normalized");
            return $normalized;
        }
        Logger::info("transformPatentNumber raw=$raw -> normalized=" . var_export($result, true));
        return $result[0];
    }

    /**
     * Very simple heuristic normalization when API is unavailable.
     */
    private static function localNormalizeNumber($raw) {
        // strip non-alphanumerics
        $s = preg_replace('/[^A-Za-z0-9]/', '', strtoupper($raw));
        if (preg_match('/^([A-Z]+)(\d+)([A-Z0-9]*)$/', $s, $m)) {
            $norm = $m[1] . '-' . $m[2];
            if ($m[3] !== '') {
                $norm .= '-' . $m[3];
            }
            return $norm;
        }
        return $raw;
    }

    /**
     * Generic HTTP helper (cURL/file_get_contents) used by this class.
     */
    private static function httpRequest($url, $body = null, $headers = [], $timeout = 30) {
        // first preference: PHP cURL extension
        if (function_exists('curl_init')) {
            Logger::info("httpRequest using php-curl for $url");
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            if ($body !== null) {
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
            }
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // for development
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);     // for development
            curl_setopt($ch, CURLOPT_USERAGENT, 'PatentAnalysisMVP/1.0');
            if (!empty($headers)) {
                curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            }
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            
            if ($error) {
                Logger::error("PatentAPI httpRequest curl error: $error (HTTP $httpCode)");
                return null;
            }
            
            // Log response details for debugging
            Logger::debug("PatentAPI httpRequest response (HTTP $httpCode): " . substr($response, 0, 500));
            
            if ($httpCode >= 400) {
                Logger::error("PatentAPI HTTP error $httpCode: " . $response);
                return null;
            }
            
            $decoded = json_decode($response, true);
            if ($decoded === null && !empty($response)) {
                Logger::error("PatentAPI JSON decode failed for response: " . substr($response, 0, 500));
                return null;
            }
            return $decoded;
        }

        // second preference: external curl binary if available
        $hasCliCurl = false;
        if (function_exists('shell_exec')) {
            $ver = @shell_exec('curl --version 2>&1');
            if ($ver && stripos($ver, 'curl') !== false) {
                $hasCliCurl = true;
            }
        }
        if ($hasCliCurl) {
            Logger::info("httpRequest using cli-curl for $url");
            $cmd = 'curl -s -m ' . (int)$timeout;
            foreach ($headers as $h) {
                $cmd .= ' -H ' . escapeshellarg($h);
            }
            if ($body !== null) {
                // On Windows the command shell does not strip single quotes, so we
                // must quote differently to avoid malformed JSON.  Use double
                // quotes and escape any internal double quotes.  On Unix we can
                // rely on escapeshellarg as before.
                if (stripos(PHP_OS, 'WIN') === 0) {
                    // escape double quotes for Windows cmd.exe
                    $bodyEsc = str_replace('"', '\\"', $body);
                    $cmd .= ' -X POST -d "' . $bodyEsc . '"';
                } else {
                    $cmd .= ' -X POST -d ' . escapeshellarg($body);
                }
            }
            $cmd .= ' ' . escapeshellarg($url);
            Logger::debug("curl command: $cmd");
            $response = @shell_exec($cmd);
            if ($response === null) {
                Logger::error("PatentAPI httpRequest cli curl failed for URL $url");
                return null;
            }
            return json_decode($response, true);
        }

        Logger::info("httpRequest using file_get_contents for $url");
        // fallback to file_get_contents
        $opts = ['http' => ['method' => $body !== null ? 'POST' : 'GET', 'timeout' => $timeout]];
        if ($body !== null) {
            $opts['http']['header'] = "Content-Type: application/json\r\n";
            $opts['http']['content'] = $body;
        }
        $context = stream_context_create($opts);
        $response = @file_get_contents($url, false, $context);
        if ($response === false) {
            $err = error_get_last()['message'] ?? 'unknown';
            Logger::error("PatentAPI httpRequest file_get_contents error: $err");
            return null;
        }
        return json_decode($response, true);
    }    
    /**
     * Mock implementation - returns deterministic sample data
     */
    private static function mockFetchPatent($patentNumber, $scope) {
        Logger::logApiRequest('PatentAPI', 'GET', 'mock://patent/' . $patentNumber, ['scope' => $scope]);
        
        $mockClaims = <<<'CLAIMS'
1. A method for patent analysis comprising:
   - receiving a patent document;
   - extracting key elements from the document;
   - comparing with a reference set;
   - generating a relevance score.
   
2. The method of claim 1, wherein the key elements include:
   - claim scope
   - technical field
   - distinguishing features
   
3. A system implementing the method of claim 1, comprising:
   - a document processor
   - an element extractor
   - a comparison engine
   - a scoring module
CLAIMS;
        
        $mockAbstract = <<<'ABSTRACT'
This patent discloses a system and method for analyzing patents with respect to predefined key elements.
The system extracts technical features, identifies scope elements, and provides relevance scoring.
Applications include prior art searching, competitive analysis, and technology landscape assessment.
ABSTRACT;
        
        $result = [
            'patent_number' => $patentNumber,
            'status' => 'ok',
            'claims' => $scope === 'claims' || $scope === 'claims+abstract' ? $mockClaims : '',
            'abstract' => $scope === 'abstract' || $scope === 'claims+abstract' ? $mockAbstract : '',
            'meta' => [
                'title' => 'Patent Analysis System and Method',
                'filing_date' => '2020-01-15',
                'issue_date' => '2022-06-20',
                'assignee' => 'Tech Corp Inc.'
            ]
        ];
        
        Logger::logApiResponse('PatentAPI', 200, $result);
        return $result;
    }
    
    /**
     * Real implementation - calls actual Patent API
     * TODO: Implement when credentials are available
     */
    private static function realFetchPatent($patentNumber, $scope) {
        // allow longer execution because external API may respond slowly
        @set_time_limit(120);
        
        // decide whether to send request via local proxy.  Skip proxy when
        // running under CLI or the built-in server (both are local contexts).
        $useProxy = !in_array(php_sapi_name(), ['cli-server', 'cli']);

        if ($useProxy) {
            $host = $_SERVER['HTTP_HOST'] ?? 'localhost:8000';
            $requestUrl = 'http://' . $host . '/api.php?op=patent&number=' . urlencode($patentNumber);
        } else {
            $requestUrl = 'https://api.unifiedpatents.com/patents/' . urlencode($patentNumber) . '?with_cases=true';
        }

        Logger::info("PatentProvider: Fetching real patent data for: $patentNumber (useProxy=$useProxy)");
        Logger::logApiRequest('PatentAPI', 'GET', $requestUrl, ['scope' => $scope]);

        try {
            // perform request using helper which prefers cli-curl / php-curl
            $data = self::httpRequest($requestUrl, null, [], 60);
            
            if ($data === null) {
                Logger::error("PatentAPI httpRequest returned NULL (connection/SSL error?)");
                return ['status' => 'failed', 'error' => 'Failed to connect to patent API. Check your internet connection or firewall settings.'];
            }
            
            if (!is_array($data) || empty((array)$data)) {
                Logger::error("PatentAPI empty response from: $requestUrl - Response type: " . gettype($data) . " - Response: " . json_encode($data));
                return ['status' => 'failed', 'error' => 'Empty response from patent API. Patent number may not exist or API credentials may be invalid.'];
            }
            
            // Check for API error response
            if (isset($data['error']) || isset($data['status']) && $data['status'] === 'error') {
                $errorMsg = $data['error'] ?? $data['message'] ?? 'Unknown error';
                Logger::error("PatentAPI returned error: $errorMsg");
                return ['status' => 'failed', 'error' => "API Error: $errorMsg"];
            }
            // httpRequest does not supply http code separately; rely on error field if any
            Logger::logApiResponse('PatentAPI', 200, $data);
        
            // convert unifiedpatents response to generic structure
            if (isset($data['_source'])) {
                $src = $data['_source'];
                $claims = $src['claims_text'] ?? ($src['claims'] ?? '');
                $abstract = $src['abstract'] ?? '';
                $description = '';
                
                // fetch full-language content for claims and description
                $fullLangUrl = 'https://api.unifiedpatents.com/patents/' . urlencode($patentNumber) . '/full-language?mode=old';
                $fullData = self::httpRequest($fullLangUrl, null, [], 60);
                if ($fullData && is_array($fullData)) {
                    // extract claims from array
                    if (isset($fullData['claims']) && is_array($fullData['claims'])) {
                        $claimTexts = [];
                        foreach ($fullData['claims'] as $claim) {
                            if (is_array($claim) && isset($claim['text'])) {
                                $claimTexts[] = $claim['text'];
                            } elseif (is_string($claim)) {
                                $claimTexts[] = $claim;
                            }
                        }
                        if (!empty($claimTexts)) {
                            $claims = implode("\n\n", $claimTexts);
                        }
                    }
                    // extract description
                    if (isset($fullData['description'])) {
                        $description = $fullData['description'];
                    }
                }
                
                return [
                    'patent_number' => $patentNumber,
                    'status' => 'ok',
                    'claims' => $claims,
                    'abstract' => $abstract,
                    'description' => $description,
                    'meta' => [
                        'title' => $src['title'] ?? '',
                        'filing_date' => $src['priority_date'] ?? '',
                        'issue_date' => $src['publication_date'] ?? '',
                        'assignee' => $src['assignee'] ?? ''
                    ]
                ];
            }

            // fallback to raw response
            return ['status' => 'ok', 'raw' => $data];
        } catch (Exception $e) {
            Logger::error("PatentAPI exception: " . $e->getMessage());
            return ['status' => 'failed', 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Parse CSV of patent numbers
     */
    public static function parsePatentList($csvText) {
        $lines = explode("\n", $csvText);
        $patents = [];
        foreach ($lines as $line) {
            $line = trim($line);
            if (!empty($line)) {
                $patents[] = $line;
            }
        }
        return $patents;
    }
    
    /**
     * Fetch complete patent data including title, abstract, claims, description, and bibliographic info
     */
    public static function fetchCompletePatentData($patentNumber, $scope = 'full') {
        // First normalize the patent number
        $normalizedNumber = self::transformPatentNumber($patentNumber);
        
        if (USE_MOCK_PATENT_API) {
            return self::mockFetchCompletePatentData($patentNumber, $scope);
        }
        return self::realFetchCompletePatentData($normalizedNumber, $scope);
    }
    
    private static function mockFetchCompletePatentData($patentNumber, $scope) {
        return [
            'patent_number' => $patentNumber,
            'status' => 'ok',
            'title' => 'Patent Analysis System and Method',
            'abstract' => 'This patent discloses a system and method for analyzing patents.',
            'claims' => ($scope === 'claims' || $scope === 'full' || $scope === 'claims+abstract') ? 
                "1. A method for patent analysis comprising:\n   - receiving a patent document;\n   - extracting key elements." : '',
            'description' => $scope === 'full' ? "FIELD OF THE INVENTION\nThe present invention relates to patent analysis." : '',
            'bibliographic' => [
                'filing_date' => '2020-01-15',
                'issue_date' => '2022-06-20',
                'assignee' => 'Tech Corp Inc.',
                'inventor' => 'John Smith',
                'application_number' => 'US16/123456'
            ]
        ];
    }
    
    private static function realFetchCompletePatentData($patentNumber, $scope) {
        @set_time_limit(120);
        $useProxy = !in_array(php_sapi_name(), ['cli-server', 'cli']);
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost:8000';
        $requestUrl = $useProxy ? 'http://' . $host . '/api.php?op=patent&number=' . urlencode($patentNumber) 
            : 'https://api.unifiedpatents.com/patents/' . urlencode($patentNumber) . '?with_cases=true';

        try {
            $data = self::httpRequest($requestUrl, null, [], 60);
            if ($data === null || !is_array($data)) {
                return ['status' => 'failed', 'error' => 'Failed to connect to patent API'];
            }
            if (isset($data['error'])) {
                return ['status' => 'failed', 'error' => $data['error']];
            }
            
            $src = $data['_source'] ?? $data;
            $claims = '';
            $description = '';
            
            $fullLangUrl = 'https://api.unifiedpatents.com/patents/' . urlencode($patentNumber) . '/full-language?mode=old';
            $fullData = self::httpRequest($fullLangUrl, null, [], 60);
            
            if ($fullData && is_array($fullData)) {
                if (isset($fullData['claims']) && is_array($fullData['claims'])) {
                    $claimTexts = [];
                    foreach ($fullData['claims'] as $claim) {
                        $claimTexts[] = is_array($claim) ? ($claim['text'] ?? '') : $claim;
                    }
                    $claims = implode("\n\n", array_filter($claimTexts));
                }
                $description = $fullData['description'] ?? '';
            }
            
            return [
                'patent_number' => $patentNumber,
                'status' => 'ok',
                'title' => $src['title'] ?? '',
                'abstract' => $src['abstract'] ?? '',
                'claims' => ($scope === 'claims' || $scope === 'full' || $scope === 'claims+abstract') ? $claims : '',
                'description' => $scope === 'full' ? $description : '',
                'bibliographic' => [
                    'filing_date' => $src['filing_date'] ?? $src['priority_date'] ?? '',
                    'issue_date' => $src['publication_date'] ?? '',
                    'assignee' => $src['assignee'] ?? '',
                    'inventor' => $src['inventor'] ?? '',
                    'application_number' => $src['application_number'] ?? ''
                ]
            ];
        } catch (Exception $e) {
            return ['status' => 'failed', 'error' => $e->getMessage()];
        }
    }
}
