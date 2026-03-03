# Patent Analysis MVP - API Integration Guide

This document describes how to connect real Patent Data APIs and AI APIs to the system.

---

## 🔌 Patent Data API Integration

### Current Mock Implementation
File: `lib/PatentProvider.php::mockFetchPatent()`

Returns deterministic sample data for any patent number.

### Implementing Real API

**Location:** `lib/PatentProvider.php::realFetchPatent()`

**Function Signature:**
```php
private static function realFetchPatent($patentNumber, $scope)
```

**Parameters:**
- `$patentNumber` (string): e.g., "US10123456B2"
- `$scope` (string): "claims", "abstract", or "claims+abstract"

**Expected Return:**
```php
[
    'patent_number' => 'US10123456B2',
    'status' => 'ok',              // or 'failed'
    'claims' => 'Claim text...',
    'abstract' => 'Abstract text...',
    'meta' => [
        'title' => 'Patent Title',
        'filing_date' => '2020-01-15',
        'issue_date' => '2022-06-20',
        'assignee' => 'Company Inc.'
    ]
]
```

**Error Return:**
```php
[
    'status' => 'failed',
    'error' => 'Error message'
]
```

### Example: USPTO API Integration

```php
private static function realFetchPatent($patentNumber, $scope) {
    $url = "https://api.uspto.gov/patent/" . urlencode($patentNumber);
    $params = ['scope' => $scope];
    
    Logger::logApiRequest('PatentAPI', 'GET', $url, $params);
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url . "?scope=" . urlencode($scope));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . PATENT_API_KEY,
        'Content-Type: application/json'
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode !== 200) {
        Logger::error("Patent API failed: HTTP $httpCode");
        return ['status' => 'failed', 'error' => "HTTP $httpCode"];
    }
    
    $data = json_decode($response, true);
    
    return [
        'patent_number' => $patentNumber,
        'status' => 'ok',
        'claims' => $data['claims'] ?? '',
        'abstract' => $data['abstract'] ?? '',
        'meta' => $data['meta'] ?? []
    ];
}
```

### Step-by-step Integration

1. **Get API credentials:**
   - Sign up at patent data provider
   - Get API key and endpoint URL

2. **Update `config/config.php`:**
   ```php
   define('PATENT_API_BASE_URL', 'https://your-api.com/patents');
   define('PATENT_API_KEY', 'your_api_key_here');
   define('USE_MOCK_PATENT_API', false);  // Disable mock
   ```

3. **Test the integration:**
   ```php
   $result = PatentProvider::fetchPatent('US10123456B2', 'claims+abstract');
   echo json_encode($result, JSON_PRETTY_PRINT);
   ```

4. **Monitor logs:**
   ```bash
   tail -f logs/app.log | grep PatentAPI
   ```

---

## 🤖 AI API Integration

### Current Mock Implementation
File: `lib/AiProvider.php`

Two mock functions:
1. `mockBuildElementContext()` - Generates element context
2. `mockScreenPatent()` - Screens patent for relevance

### Implementing Real AI API

**Location:** `lib/AiProvider.php::realBuildElementContext()` and `realScreenPatent()`

### 1. Element Context Generation

**Function Signature:**
```php
private static function realBuildElementContext($seedText, $elementText)
```

**Prompt Template:** `prompts/element_context.txt`

**Expected Return:**
```php
[
    'definition' => 'Technical definition...',
    'synonyms' => ['term1', 'term2'],
    'explicit_markers' => ['phrase1', 'phrase2'],
    'implicit_markers' => ['concept1', 'concept2'],
    'boundaries' => ['includes', 'excludes'],
    'match_cues' => ['cue1', 'cue2']
]
```

**Example: GPT-4 Integration**

```php
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
            [
                'role' => 'system',
                'content' => 'You are a patent analysis assistant. Produce technically precise, legally neutral context for a claim element.'
            ],
            [
                'role' => 'user',
                'content' => $prompt
            ]
        ],
        'temperature' => 0.3,
        'response_format' => ['type' => 'json_object']
    ];
    
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
    curl_close($ch);
    
    if ($httpCode !== 200) {
        Logger::error("AI API failed: HTTP $httpCode");
        return null;
    }
    
    $data = json_decode($response, true);
    $content = $data['choices'][0]['message']['content'] ?? '';
    
    return json_decode($content, true);
}
```

### 2. Patent Screening

**Function Signature:**
```php
private static function realScreenPatent($contextsJson, $patentText)
```

**Prompt Template:** `prompts/screening.txt`

**Expected Return:**
```php
[
    'overall' => [
        'label' => 'relevant|borderline|not_relevant',
        'score' => 85,  // 0-100
        'reasoning_short' => '1-3 sentence summary'
    ],
    'per_element' => [
        [
            'element_index' => 0,
            'label' => 'explicit|implicit|partial|none',
            'score' => 5,  // 0-5
            'rationale' => 'Why this label',
            'evidence' => [
                [
                    'location' => 'claim 1',
                    'snippet' => 'quote from patent'
                ]
            ]
        ]
    ],
    'notes' => [
        'uncertainties' => ['what needs manual review'],
        'missing_sections' => []
    ]
]
```

### Step-by-step Integration

1. **Choose AI Provider:**
   - OpenAI (GPT-4) - Most flexible
   - Anthropic (Claude) - Good for long contexts
   - Azure OpenAI - Enterprise
   - Local (LLaMA) - Privacy-focused

2. **Get API credentials:**
   ```php
   define('AI_API_BASE_URL', 'https://api.openai.com/v1');
   define('AI_API_KEY', 'sk-...');
   define('AI_MODEL', 'gpt-4');
   define('USE_MOCK_AI_API', false);
   ```

3. **Adjust rate limiting:**
   ```php
   define('AI_SLEEP_MS', 500);  // Between requests
   ```

4. **Test the integration:**
   ```php
   $contexts = json_encode([
       ['definition' => 'test element']
   ]);
   $result = AiProvider::screenPatent($contexts, 'patent claims text...');
   echo json_encode($result, JSON_PRETTY_PRINT);
   ```

5. **Monitor and adjust:**
   - Check logs for API response times
   - Adjust `AI_SLEEP_MS` if hitting rate limits
   - Validate JSON responses before storing

---

## 🔄 Data Flow

### Step 1: Seed Context Generation
```
User Input (seed patent #)
    ↓
PatentProvider::fetchPatent()
    ↓
Store in DB (seed_extracted_text)
    ↓
User defines key elements
    ↓
AiProvider::buildElementContext() [for each element]
    ↓
Store context_json in DB
```

### Step 2: Patent Screening
```
User uploads patents
    ↓
PatentProvider::fetchPatent() [for each]
    ↓
Store claims/abstract in DB
    ↓
AiProvider::screenPatent() [for each]
    ↓
Store screening_results in DB
    ↓
Display results in UI
```

### Step 3: Export
```
Retrieve screening_results from DB
    ↓
Format as CSV or HTML
    ↓
Download to user
```

---

## 🚨 Error Handling

### Graceful Degradation
The system handles errors gracefully:

```php
if ($result['status'] === 'failed') {
    // Log error
    Logger::error("API failed: " . $result['error']);
    
    // Mark patent as failed
    $db->updatePatentText($id, '', '', 'failed', $result['error']);
    
    // Continue with next patent
    continue;
}
```

### Retry Logic
For production, implement exponential backoff:

```php
private static function retryWithBackoff($fn, $maxRetries = 3) {
    for ($i = 0; $i < $maxRetries; $i++) {
        try {
            return $fn();
        } catch (Exception $e) {
            if ($i === $maxRetries - 1) throw $e;
            usleep(pow(2, $i) * 1000000);  // 1s, 2s, 4s
        }
    }
}
```

---

## 💰 Cost Estimation

### OpenAI Pricing (Example)
- GPT-4: $0.03 per 1K input tokens, $0.06 per 1K output tokens
- 50 patents × 2 APIs = ~$5-10 per analysis

### Optimization Tips
1. Use cheaper models for screening (GPT-3.5 vs GPT-4)
2. Batch similar requests
3. Cache results (don't re-screen same patent)
4. Use smaller context for borderline cases

---

## 🧪 Testing

### Unit Test Template

```php
function testPatentProviderIntegration() {
    $result = PatentProvider::fetchPatent('US10123456B2', 'claims+abstract');
    
    assert($result['status'] === 'ok', 'Status should be ok');
    assert(!empty($result['claims']), 'Claims should not be empty');
    assert(!empty($result['abstract']), 'Abstract should not be empty');
    
    echo "✓ Patent fetch test passed";
}

function testAiProviderIntegration() {
    $contexts = json_encode([
        ['definition' => 'test element', 'synonyms' => ['test']]
    ]);
    $result = AiProvider::screenPatent($contexts, 'Test patent text with claims...');
    
    assert($result['overall']['label'] !== null, 'Label should be set');
    assert($result['overall']['score'] >= 0 && $result['overall']['score'] <= 100, 'Score should be 0-100');
    assert(!empty($result['per_element']), 'Per-element results should exist');
    
    echo "✓ Screening test passed";
}
```

---

## 📋 Checklist for Integration

- [ ] API credentials obtained
- [ ] Endpoints tested manually (curl/Postman)
- [ ] Error handling implemented
- [ ] Logging configured
- [ ] Rate limiting adjusted
- [ ] Response format validated
- [ ] Unit tests passing
- [ ] Integration tests passing
- [ ] Production credentials in environment variables
- [ ] API costs monitored

---

## 🆘 Troubleshooting

| Issue | Solution |
|-------|----------|
| "401 Unauthorized" | Check API key in config |
| "Timeout" | Increase CURLOPT_TIMEOUT or check network |
| "Invalid JSON" | Log full response and validate schema |
| "Rate limit hit" | Increase AI_SLEEP_MS |
| "Missing data" | Check API response fields; may need parsing |

---

**Version:** 1.0  
**Last Updated:** March 3, 2026
