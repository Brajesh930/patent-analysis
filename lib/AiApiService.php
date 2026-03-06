<?php
/**
 * UNIFIED AI SERVICE - Single File for All AI Operations
 * 
 * This file consolidates:
 * - All AI provider configurations (OpenAI, Google, Azure, Anthropic, Deepseek, Ollama, Custom)
 * - All helper functions for API communication
 * - All AI operations (buildElementContext, screenPatent)
 * - HTTP request handling (curl)
 * 
 * Requirements:
 * - Logger.php must be loaded before this file
 * - Constants: PROMPTS_PATH, AI_SLEEP_MS
 * 
 * Public API:
 * - AiApiService::callAi($prompt, $options) - Single prompt → string response
 * - AiApiService::callAiJson($prompt, $options) - Single prompt → parsed JSON
 * - AiProvider::buildElementContext($seedText, $elementText) - Patent element analysis
 * - AiProvider::screenPatent($contextsJson, $patentText) - Patent screening
 * - AiProvider methods support mock mode via USE_MOCK_AI_API
 */

// ============================================================================
// AI PROVIDER CONFIGURATION - All Providers
// ============================================================================

// Function to get user settings dynamically
function getUserSettings() {
    $savedSettings = [];
    
    // Check if user is logged in and has session settings
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    if (isset($_SESSION['authenticated']) && $_SESSION['authenticated'] === true && isset($_SESSION['user_id'])) {
        // User is logged in - try to get settings from database
        if ($_SESSION['user_id'] > 0) {
            // Database user - load from database
            $dbPath = defined('DB_PATH') ? DB_PATH : __DIR__ . '/../data/app.db';
            if (file_exists($dbPath)) {
                try {
                    $pdo = new PDO('sqlite:' . $dbPath);
                    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                    $pdo->exec('PRAGMA busy_timeout = 5000');
                    $pdo->exec('PRAGMA journal_mode = WAL');
                    $stmt = $pdo->prepare("SELECT ai_provider, ai_model, api_key, use_mock, use_mock_patent FROM users WHERE id = ?");
                    $stmt->execute([$_SESSION['user_id']]);
                    $userSettings = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if ($userSettings) {
                        $savedSettings = [
                            'provider' => $userSettings['ai_provider'] ?? 'google',
                            'model' => $userSettings['ai_model'] ?? 'gemini-2.5-flash',
                            'api_key' => $userSettings['api_key'] ?? '',
                            'use_mock' => $userSettings['use_mock'] ?? 0,
                            'use_mock_patent' => $userSettings['use_mock_patent'] ?? 1
                        ];
                    }
                } catch (Exception $e) {
                    // Fall back to file settings
                }
            }
        } else {
            // Hardcoded admin - use session or file settings
            $savedSettings = $_SESSION['ai_provider'] ?? [];
        }
    }
    
    // If no user settings, fall back to file settings
    if (empty($savedSettings)) {
        $settingsFile = __DIR__ . '/../data/ai_settings.json';
        if (file_exists($settingsFile)) {
            $savedSettings = json_decode(file_get_contents($settingsFile), true) ?: [];
        }
    }
    
    return $savedSettings;
}

// Get settings dynamically
$savedSettings = getUserSettings();

define('AI_PROVIDER', $savedSettings['provider'] ?? getenv('AI_PROVIDER') ?: 'google');
// Always use real API - no mock mode
define('USE_MOCK_AI_API', false);
define('USE_MOCK_PATENT_API', false);

$GLOBALS['AI_PROVIDERS'] = [
    // ========================================================================
    // OPENAI (GPT-4, GPT-3.5-Turbo, etc.)
    // ========================================================================
    'openai' => [
        'name' => 'OpenAI',
        'api_url' => 'https://api.openai.com/v1',
        'api_key' => $savedSettings['api_key'] ?? getenv('OPENAI_API_KEY') ?: '',
        'model' => $savedSettings['model'] ?? getenv('OPENAI_MODEL') ?: 'gpt-4-turbo',
        'auth_type' => 'bearer',
        'endpoint' => '/chat/completions',
        'rate_limit_ms' => 500,
        'response_format' => ['data_path' => 'choices.0.message.content', 'status_code' => 200]
    ],

    // ========================================================================
    // GOOGLE AI (Gemini, Bard, etc.)
    // ========================================================================
    'google' => [
        'name' => 'Google AI',
        'api_url' => 'https://generativelanguage.googleapis.com/v1',
        'api_key' => $savedSettings['api_key'] ?? getenv('GOOGLE_API_KEY') ?: '',
        'model' => $savedSettings['model'] ?? getenv('GOOGLE_MODEL') ?: 'gemini-2.5-flash',
        'auth_type' => 'query',
        'endpoint' => '/models/{model}:generateContent',
        'rate_limit_ms' => 500,
        'response_format' => ['data_path' => 'candidates.0.content.parts.0.text', 'status_code' => 200]
    ],

    // ========================================================================
    // DEEPSEEK
    // ========================================================================
    'deepseek' => [
        'name' => 'Deepseek',
        'api_url' => 'https://api.deepseek.com/v1',
        'api_key' => $savedSettings['api_key'] ?? getenv('DEEPSEEK_API_KEY') ?: '',
        'model' => $savedSettings['model'] ?? getenv('DEEPSEEK_MODEL') ?: 'deepseek-chat',
        'auth_type' => 'bearer',
        'endpoint' => '/chat/completions',
        'rate_limit_ms' => 500,
        'response_format' => ['data_path' => 'choices.0.message.content', 'status_code' => 200]
    ],

    // ========================================================================
    // AZURE OPENAI
    // ========================================================================
    'azure' => [
        'name' => 'Azure OpenAI',
        'api_url' => getenv('AZURE_OPENAI_URL') ?: 'https://{resource_name}.openai.azure.com/openai/deployments/{model}',
        'api_key' => $savedSettings['api_key'] ?? getenv('AZURE_OPENAI_API_KEY') ?: '',
        'model' => $savedSettings['model'] ?? getenv('AZURE_OPENAI_MODEL') ?: 'gpt-4',
        'api_version' => '2024-02-15-preview',
        'auth_type' => 'header',
        'auth_header_name' => 'api-key',
        'endpoint' => '/chat/completions',
        'rate_limit_ms' => 500,
        'response_format' => ['data_path' => 'choices.0.message.content', 'status_code' => 200]
    ],

    // ========================================================================
    // ANTHROPIC CLAUDE
    // ========================================================================
    'anthropic' => [
        'name' => 'Anthropic Claude',
        'api_url' => 'https://api.anthropic.com/v1',
        'api_key' => $savedSettings['api_key'] ?? getenv('ANTHROPIC_API_KEY') ?: '',
        'model' => $savedSettings['model'] ?? getenv('ANTHROPIC_MODEL') ?: 'claude-3-sonnet-20240229',
        'auth_type' => 'bearer',
        'endpoint' => '/messages',
        'rate_limit_ms' => 500,
        'response_format' => ['data_path' => 'content.0.text', 'status_code' => 200]
    ],

    // ========================================================================
    // OLLAMA (Local AI models)
    // ========================================================================
    'ollama' => [
        'name' => 'Ollama (Local)',
        'api_url' => getenv('OLLAMA_URL') ?: 'http://localhost:11434/api',
        'api_key' => '',
        'model' => $savedSettings['model'] ?? getenv('OLLAMA_MODEL') ?: 'mistral',
        'auth_type' => 'none',
        'endpoint' => '/chat',
        'rate_limit_ms' => 1000,
        'response_format' => ['data_path' => 'message.content', 'status_code' => 200]
    ],

    // ========================================================================
    // CUSTOM PROVIDER
    // ========================================================================
    'custom' => [
        'name' => 'Custom Provider',
        'api_url' => getenv('CUSTOM_AI_URL') ?: '',
        'api_key' => getenv('CUSTOM_AI_KEY') ?: '',
        'model' => getenv('CUSTOM_AI_MODEL') ?: 'custom-model',
        'auth_type' => 'bearer',
        'auth_header_name' => 'Authorization',
        'endpoint' => getenv('CUSTOM_AI_ENDPOINT') ?: '/api/inference',
        'rate_limit_ms' => 500,
        'response_format' => ['data_path' => 'result', 'status_code' => 200]
    ]
];

// ============================================================================
// HELPER FUNCTIONS - Configuration & API Setup
// ============================================================================

/**
 * Get the current active AI provider configuration
 */
function getAiProviderConfig() {
    $provider = strtolower(AI_PROVIDER);
    
    if (!isset($GLOBALS['AI_PROVIDERS'][$provider])) {
        Logger::error("AI provider '$provider' not found. Available: " . implode(', ', array_keys($GLOBALS['AI_PROVIDERS'])));
        return $GLOBALS['AI_PROVIDERS']['openai'];
    }
    
    return $GLOBALS['AI_PROVIDERS'][$provider];
}

/**
 * Get the full API URL for the current provider
 */
function getAiApiUrl() {
    $config = getAiProviderConfig();
    $url = $config['api_url'];
    $endpoint = $config['endpoint'];
    
    if (AI_PROVIDER === 'azure') {
        $url = str_replace('{model}', $config['model'], $url);
    }
    
    if (AI_PROVIDER === 'google') {
        $endpoint = str_replace('{model}', $config['model'], $endpoint);
    }
    
    return $url . $endpoint . (AI_PROVIDER === 'google' ? ('?key=' . $config['api_key']) : '');
}

/**
 * Build request headers for the current provider
 */
function getAiRequestHeaders() {
    $config = getAiProviderConfig();
    $headers = ['Content-Type: application/json'];
    
    switch ($config['auth_type']) {
        case 'bearer':
            $headers[] = 'Authorization: Bearer ' . $config['api_key'];
            break;
        
        case 'header':
            $headerName = $config['auth_header_name'] ?? 'api-key';
            $headers[] = "$headerName: " . $config['api_key'];
            break;
        
        case 'query':
            break;
        
        case 'none':
            break;
    }
    
    if (AI_PROVIDER === 'azure') {
        $headers[] = 'api-version: ' . ($config['api_version'] ?? '2024-02-15-preview');
    }
    
    if (AI_PROVIDER === 'anthropic') {
        $headers[] = 'anthropic-version: 2023-06-01';
    }
    
    return $headers;
}

/**
 * Get the model name for the current provider
 */
function getAiModel() {
    $config = getAiProviderConfig();
    return $config['model'];
}

/**
 * Get the rate limit (ms) for the current provider
 */
function getAiRateLimit() {
    $config = getAiProviderConfig();
    return $config['rate_limit_ms'] ?? (defined('AI_SLEEP_MS') ? AI_SLEEP_MS : 500);
}

/**
 * Format request payload for the current provider
 */
function formatAiProviderPayload($method, $params = []) {
    $config = getAiProviderConfig();
    $provider = strtolower(AI_PROVIDER);
    
    $messages = $params['messages'] ?? [];
    
    switch ($provider) {
        case 'openai':
        case 'azure':
        case 'deepseek':
            return [
                'model' => $config['model'],
                'messages' => $messages,
                'temperature' => 0.3,
                'response_format' => ['type' => 'json_object']
            ];
        
        case 'google':
            $combinedText = '';
            foreach ($messages as $msg) {
                if ($msg['role'] === 'system') {
                    $combinedText .= $msg['content'] . "\n\n";
                } elseif ($msg['role'] === 'user') {
                    $combinedText .= $msg['content'];
                }
            }
            
            return [
                'contents' => [
                    [
                        'role' => 'user',
                        'parts' => [['text' => trim($combinedText)]]
                    ]
                ],
                'generationConfig' => ['temperature' => 0.3]
            ];
        
        case 'anthropic':
            return [
                'model' => $config['model'],
                'max_tokens' => 2000,
                'messages' => $messages,
                'temperature' => 0.3
            ];
        
        case 'ollama':
            return [
                'model' => $config['model'],
                'messages' => $messages,
                'stream' => false
            ];
        
        default:
            return [
                'model' => $config['model'],
                'messages' => $messages,
                'temperature' => 0.3
            ];
    }
}

/**
 * Extract response from provider-specific API response
 */
function extractAiProviderResponse($response) {
    $config = getAiProviderConfig();
    $data = json_decode($response, true);
    
    if (!$data) {
        return null;
    }
    
    $path = $config['response_format']['data_path'] ?? 'choices.0.message.content';
    $parts = explode('.', $path);
    $current = $data;
    
    foreach ($parts as $part) {
        if (is_numeric($part)) {
            $current = $current[(int)$part] ?? null;
        } else {
            $current = $current[$part] ?? null;
        }
        
        if ($current === null) {
            return null;
        }
    }
    
    return $current;
}

/**
 * Log the current AI provider configuration
 */
function logAiProviderConfig() {
    $config = getAiProviderConfig();
    Logger::info("AI Provider: " . $config['name']);
    Logger::info("AI Model: " . $config['model']);
    Logger::info("AI Base URL: " . $config['api_url']);
}

// ============================================================================
// UNIFIED API SERVICE CLASS - All API Calls
// ============================================================================

class AiApiService {
    
    /**
     * Call AI API with a single prompt
     * 
     * @param string $prompt The prompt to send to AI
     * @param array $options Optional configuration (system_message, temperature, etc)
     * @return string|null AI response text, or null on error
     */
    public static function callAi($prompt, $options = []) {
        try {
            // Get configuration
            $config = getAiProviderConfig();
            
            // Build messages
            $systemMessage = $options['system_message'] ?? 'You are a helpful AI assistant. Respond with valid JSON.';
            $messages = [
                ['role' => 'system', 'content' => $systemMessage],
                ['role' => 'user', 'content' => $prompt]
            ];
            
            // Format payload for provider
            $payload = formatAiProviderPayload('generic', ['messages' => $messages]);
            
            // Get API endpoint and headers
            $apiUrl = getAiApiUrl();
            $headers = getAiRequestHeaders();
            
            Logger::info("AI API Call - Provider: " . AI_PROVIDER . ", Model: " . getAiModel());
            Logger::logApiRequest('AiAPI', 'POST', $apiUrl, ['prompt_length' => strlen($prompt)]);
            
            // Make HTTP request
            $response = self::makeHttpRequest($apiUrl, $payload, $headers);
            
            if (!$response) {
                Logger::error("AiApiService: No response from API");
                return null;
            }
            
            // Extract response content
            $content = extractAiProviderResponse($response);
            
            if (!$content) {
                Logger::error("AiApiService: Could not extract response content");
                return null;
            }
            
            Logger::logApiResponse('AiAPI', 200, ['response_length' => strlen($content)]);
            return $content;
            
        } catch (Exception $e) {
            Logger::error("AiApiService exception: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Call AI API and return as JSON
     * 
     * @param string $prompt The prompt to send to AI
     * @param array $options Optional configuration
     * @return array|null Parsed JSON response, or null on error
     */
    public static function callAiJson($prompt, $options = []) {
        $response = self::callAi($prompt, $options);
        
        if (!$response) {
            return null;
        }
        
        // Try to parse as JSON
        $jsonResponse = json_decode($response, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            // If wrapped in markdown code blocks, extract JSON
            if (preg_match('/```(?:json)?\s*({.*})\s*```/s', $response, $matches)) {
                $jsonResponse = json_decode($matches[1], true);
            }
        }
        
        return $jsonResponse;
    }
    
    /**
     * Make HTTP request to AI API
     * 
     * @param string $url API endpoint URL
     * @param array $payload Request payload
     * @param array $headers Request headers
     * @return string|null Response body, or null on error
     */
    private static function makeHttpRequest($url, $payload, $headers) {
        try {
            $ch = curl_init();
            
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 60);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            
            // curl_close() is deprecated in PHP 8.0+ and removed in PHP 8.5
            // cURL handles are automatically closed when they go out of scope
            // Just unset the handle to ensure cleanup
            unset($ch);
            
            // Check for errors
            if ($error) {
                Logger::error("AiApiService curl error: $error");
                return null;
            }
            
            if ($httpCode !== 200) {
                Logger::error("AiApiService HTTP $httpCode");
                $responseData = json_decode($response, true);
                if (isset($responseData['error'])) {
                    Logger::error("API Error: " . $responseData['error']['message'] ?? 'Unknown');
                }
                return null;
            }
            
            return $response;
            
        } catch (Exception $e) {
            Logger::error("AiApiService HTTP request exception: " . $e->getMessage());
            return null;
        }
    }
}

// ============================================================================
// AI PROVIDER CLASS - High-level AI Operations
// ============================================================================
// This class handles patent analysis operations (element context, patent screening)
// Supports both mock and real modes for testing and production use

class AiProvider {
    
    /**
     * Build context for a key element based on seed text
     * 
     * @param string $seedText The source/seed patent text
     * @param string $elementText The key element to analyze
     * @return array Parsed JSON context with analysis
     */
    public static function buildElementContext($seedText, $elementText) {
        if (USE_MOCK_AI_API) {
            return self::mockBuildElementContext($seedText, $elementText);
        }
        
        return self::realBuildElementContext($seedText, $elementText);
    }
    
    /**
     * Mock implementation - returns deterministic context
     */
    private static function mockBuildElementContext($seedText, $elementText) {
        Logger::logApiRequest('AiAPI', 'POST', 'mock://ai/element_context', [
            'seed_length' => strlen($seedText),
            'element' => $elementText
        ]);
        
        $context = [
            'definition' => "Technical element referring to: " . $elementText,
            'synonyms' => [
                trim($elementText),
                strtolower($elementText),
                str_replace('system', 'apparatus', $elementText)
            ],
            'explicit_markers' => [
                $elementText,
                '"' . $elementText . '"'
            ],
            'implicit_markers' => [
                'similar to ' . $elementText,
                'comprising ' . $elementText,
                $elementText . ' or equivalent'
            ],
            'boundaries' => [
                'specifically excludes: non-' . $elementText,
                'includes only direct implementations'
            ],
            'match_cues' => [
                'functional equivalence',
                'structural similarity',
                'conceptual correspondence'
            ]
        ];
        
        Logger::logApiResponse('AiAPI', 200, $context);
        return $context;
    }
    
    /**
     * Real implementation - builds element context via AI API
     */
    private static function realBuildElementContext($seedText, $elementText) {
        $promptTemplate = file_get_contents(PROMPTS_PATH . '/element_context.txt');
        $prompt = str_replace(
            ['{{SEED_TEXT}}', '{{ELEMENT}}'],
            [$seedText, $elementText],
            $promptTemplate
        );
        
        $systemMessage = 'You are a patent analysis assistant. Produce technically precise, legally neutral context for a claim element. Respond only with valid JSON.';
        $result = AiApiService::callAiJson($prompt, ['system_message' => $systemMessage]);
        
        return $result;
    }
    
    /**
     * Screen a patent for relevance against key element contexts
     * 
     * @param array $contextsJson Array of element context JSONs
     * @param string $patentText Full patent text (claims + abstract)
     * @return array Parsed screening result
     */
    public static function screenPatent($contextsJson, $patentText) {
        if (USE_MOCK_AI_API) {
            return self::mockScreenPatent($contextsJson, $patentText);
        }
        
        return self::realScreenPatent($contextsJson, $patentText);
    }
    
    /**
     * Mock implementation - returns deterministic screening result
     */
    private static function mockScreenPatent($contextsJson, $patentText) {
        usleep(USE_MOCK_AI_API ? 100000 : (getAiRateLimit() * 1000));
        
        Logger::logApiRequest('AiAPI', 'POST', 'mock://ai/screen_patent', [
            'contexts_count' => count(
                is_array($contextsJson)
                    ? $contextsJson
                    : (json_decode($contextsJson, true) ?: [])
            ),
            'patent_text_length' => strlen($patentText)
        ]);
        
        $isRelevant = strlen($patentText) > 100;
        $score = $isRelevant ? rand(65, 95) : rand(20, 50);
        $label = $score >= 70 ? 'relevant' : ($score >= 50 ? 'borderline' : 'not_relevant');
        
        $perElement = [];
        $contextsArray = is_array($contextsJson)
            ? $contextsJson
            : (json_decode($contextsJson, true) ?: []);
        
        foreach ($contextsArray as $idx => $ctx) {
            $elementLabel = $score >= 70 ? 'explicit' : ($score >= 50 ? 'implicit' : 'partial');
            $perElement[] = [
                'element_index' => $idx,
                'label' => $elementLabel,
                'score' => $elementLabel === 'explicit' ? rand(4, 5) : ($elementLabel === 'implicit' ? rand(2, 3) : 1),
                'rationale' => 'Found ' . $elementLabel . ' mentions in patent text.',
                'evidence' => [
                    [
                        'location' => 'claim 1',
                        'snippet' => 'technical feature related to element ' . ($idx + 1)
                    ]
                ]
            ];
        }
        
        $result = [
            'overall' => [
                'label' => $label,
                'score' => $score,
                'reasoning_short' => 'Patent exhibits ' . $score . '% relevance to key elements. ' . ucfirst($label) . ' match.'
            ],
            'per_element' => $perElement,
            'notes' => [
                'uncertainties' => ['Some implicit references may require domain expertise.'],
                'missing_sections' => []
            ]
        ];
        
        Logger::logApiResponse('AiAPI', 200, $result);
        return $result;
    }
    
    /**
     * Real implementation - screens patent via AI API
     */
    private static function realScreenPatent($contextsJson, $patentText) {
        $promptTemplate = file_get_contents(PROMPTS_PATH . '/screening.txt');
        $prompt = str_replace(
            ['{{CONTEXTS_JSON}}', '{{PATENT_TEXT}}'],
            [$contextsJson, $patentText],
            $promptTemplate
        );
        
        $systemMessage = 'You are screening a candidate patent for relevance to defined key elements. Be conservative and evidence-based. Respond only with valid JSON.';
        $result = AiApiService::callAiJson($prompt, ['system_message' => $systemMessage]);
        
        usleep(getAiRateLimit() * 1000);
        
        return $result;
    }
}

?>
