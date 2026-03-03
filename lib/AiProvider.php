<?php
/**
 * Patent Analysis MVP - AI Provider (Adapter Pattern)
 * 
 * This adapter handles AI calls for context generation and patent screening.
 * For MVP, uses mock data; can be replaced with real API calls later.
 */

class AiProvider {
    
    /**
     * Build context for a key element based on seed text
     * 
     * @param string $seedText The source/seed patent text
     * @param string $elementText The key element to analyze
     * @return array Parsed JSON context with: definition, synonyms, explicit_markers, implicit_markers, boundaries, match_cues
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
     * Real implementation - calls AI API endpoint
     * TODO: Implement when credentials are available
     */
    private static function realBuildElementContext($seedText, $elementText) {
        $promptTemplate = file_get_contents(PROMPTS_PATH . '/element_context.txt');
        $prompt = str_replace(
            ['{{SEED_TEXT}}', '{{ELEMENT}}'],
            [$seedText, $elementText],
            $promptTemplate
        );
        
        $payload = [
            'model' => AI_MODEL,
            'messages' => [
                ['role' => 'system', 'content' => 'You are a patent analysis assistant. Produce technically precise, legally neutral context for a claim element.'],
                ['role' => 'user', 'content' => $prompt]
            ],
            'temperature' => 0.3,
            'response_format' => ['type' => 'json_object']
        ];
        
        Logger::logApiRequest('AiAPI', 'POST', AI_API_BASE_URL . '/chat/completions', []);
        
        try {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, AI_API_BASE_URL . '/chat/completions');
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Authorization: Bearer ' . AI_API_KEY,
                'Content-Type: application/json'
            ]);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 60);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);
            
            if ($error) {
                Logger::error("AiAPI curl error: $error");
                return null;
            }
            
            $data = json_decode($response, true);
            Logger::logApiResponse('AiAPI', $httpCode, $data);
            
            if ($httpCode !== 200 || !isset($data['choices'][0]['message']['content'])) {
                Logger::error("AiAPI failed with code $httpCode");
                return null;
            }
            
            $content = $data['choices'][0]['message']['content'];
            return json_decode($content, true);
        } catch (Exception $e) {
            Logger::error("AiAPI exception: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Screen a patent for relevance against key element contexts
     * 
     * @param array $contextsJson Array of element context JSONs
     * @param string $patentText Full patent text (claims + abstract)
     * @return array Parsed result with: overall (label, score, reasoning_short), per_element array, notes
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
        usleep(USE_MOCK_AI_API ? 100000 : AI_SLEEP_MS * 1000);
        
        Logger::logApiRequest('AiAPI', 'POST', 'mock://ai/screen_patent', [
            'contexts_count' => count(is_array($contextsJson) ? $contextsJson : json_decode($contextsJson, true) ?: []),
            'patent_text_length' => strlen($patentText)
        ]);
        
        // Deterministic mock based on patent text
        $isRelevant = strlen($patentText) > 100;
        $score = $isRelevant ? rand(65, 95) : rand(20, 50);
        $label = $score >= 70 ? 'relevant' : ($score >= 50 ? 'borderline' : 'not_relevant');
        
        $perElement = [];
        $contextsArray = is_array($contextsJson) ? $contextsJson : json_decode($contextsJson, true) ?: [];
        
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
     * Real implementation - calls AI API endpoint for screening
     * TODO: Implement when credentials are available
     */
    private static function realScreenPatent($contextsJson, $patentText) {
        $promptTemplate = file_get_contents(PROMPTS_PATH . '/screening.txt');
        $prompt = str_replace(
            ['{{CONTEXTS_JSON}}', '{{PATENT_TEXT}}'],
            [$contextsJson, $patentText],
            $promptTemplate
        );
        
        $payload = [
            'model' => AI_MODEL,
            'messages' => [
                ['role' => 'system', 'content' => 'You are screening a candidate patent for relevance to defined key elements. Be conservative and evidence-based.'],
                ['role' => 'user', 'content' => $prompt]
            ],
            'temperature' => 0.3,
            'response_format' => ['type' => 'json_object']
        ];
        
        Logger::logApiRequest('AiAPI', 'POST', AI_API_BASE_URL . '/chat/completions', []);
        
        try {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, AI_API_BASE_URL . '/chat/completions');
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Authorization: Bearer ' . AI_API_KEY,
                'Content-Type: application/json'
            ]);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 60);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);
            
            usleep(AI_SLEEP_MS * 1000);
            
            if ($error) {
                Logger::error("AiAPI curl error: $error");
                return null;
            }
            
            $data = json_decode($response, true);
            Logger::logApiResponse('AiAPI', $httpCode, $data);
            
            if ($httpCode !== 200 || !isset($data['choices'][0]['message']['content'])) {
                Logger::error("AiAPI failed with code $httpCode");
                return null;
            }
            
            $content = $data['choices'][0]['message']['content'];
            return json_decode($content, true);
        } catch (Exception $e) {
            Logger::error("AiAPI exception: " . $e->getMessage());
            return null;
        }
    }
}
