# AI Configuration System - Implementation Reference

## Overview

This document explains the technical implementation of the centralized AI configuration system for developers who want to understand or extend it.

## System Design

### Core Components

```
┌─────────────────────────────────────────────────────────────┐
│ Environment Variables (.env file)                           │
│ AI_PROVIDER=openai                                          │
│ OPENAI_API_KEY=sk-...                                       │
│ OPENAI_MODEL=gpt-4-turbo                                    │
└─────────────────────────────────────────────────────────────┘
                              ↓
┌─────────────────────────────────────────────────────────────┐
│ config/ai-config.php                                        │
│ - $AI_PROVIDERS array (all provider definitions)            │
│ - Helper functions (getAiApiUrl, etc.)                      │
└─────────────────────────────────────────────────────────────┘
                              ↓
┌─────────────────────────────────────────────────────────────┐
│ config/config.php                                           │
│ require_once 'ai-config.php'                                │
└─────────────────────────────────────────────────────────────┘
                              ↓
┌─────────────────────────────────────────────────────────────┐
│ lib/AiProvider.php                                          │
│ - Uses helper functions                                      │
│ - No provider-specific code                                  │
└─────────────────────────────────────────────────────────────┘
                              ↓
┌─────────────────────────────────────────────────────────────┐
│ HTTP Request to AI Provider                                 │
│ (OpenAI, Google, Deepseek, etc.)                            │
└─────────────────────────────────────────────────────────────┘
```

## Configuration Structure

### Provider Definition Format

Each provider in `config/ai-config.php` has this structure:

```php
'provider_key' => [
    'name' => 'Display Name',
    'api_url' => 'https://api.provider.com/v1',
    'api_key' => getenv('PROVIDER_API_KEY') ?: '',
    'model' => getenv('PROVIDER_MODEL') ?: 'default-model',
    'auth_type' => 'bearer|header|query|none',
    'auth_header_name' => 'Authorization',  // if auth_type is 'header'
    'endpoint' => '/chat/completions',
    'rate_limit_ms' => 500,
    'request_format' => [
        'model' => 'model_name',
        'messages' => 'array',
        // Provider-specific payload structure
    ],
    'response_format' => [
        'data_path' => 'choices.0.message.content',  // JSON path
        'status_code' => 200
    ]
],
```

### Example: OpenAI Configuration

```php
'openai' => [
    'name' => 'OpenAI',
    'api_url' => 'https://api.openai.com/v1',
    'api_key' => getenv('OPENAI_API_KEY') ?: '',
    'model' => getenv('OPENAI_MODEL') ?: 'gpt-4-turbo',
    'auth_type' => 'bearer',  // Uses "Authorization: Bearer {key}"
    'endpoint' => '/chat/completions',
    'rate_limit_ms' => 500,
    'request_format' => [
        'model' => 'model_name',
        'messages' => 'array',
        'temperature' => 0.3,
        'max_tokens' => 2000,
        'response_format' => ['type' => 'json_object']
    ],
    'response_format' => [
        'data_path' => 'choices.0.message.content',
        'status_code' => 200
    ]
],
```

### Example: Google AI Configuration

```php
'google' => [
    'name' => 'Google AI',
    'api_url' => 'https://generativelanguage.googleapis.com/v1',
    'api_key' => getenv('GOOGLE_API_KEY') ?: '',
    'model' => getenv('GOOGLE_MODEL') ?: 'gemini-2.5-flash',
    'auth_type' => 'query',  // Uses ?key={key} in URL
    'endpoint' => '/models/{model}:generateContent',
    'rate_limit_ms' => 500,
    'request_format' => [
        'contents' => 'array',
        'generationConfig' => [
            'temperature' => 0.3
        ]
    ],
    'response_format' => [
        'data_path' => 'candidates.0.content.parts.0.text',
        'status_code' => 200
    ]
],
```

## Helper Functions

### 1. `getAiProviderConfig()` 

Returns the active provider's configuration array.

```php
$config = getAiProviderConfig();
// Returns: Array with keys: name, api_url, api_key, model, etc.
```

**Usage:**
```php
$config = getAiProviderConfig();
echo $config['name'];        // "OpenAI"
echo $config['model'];       // "gpt-4-turbo"
```

### 2. `getAiApiUrl()`

Returns the full API endpoint URL for the active provider.

```php
$url = getAiApiUrl();
// Returns: "https://api.openai.com/v1/chat/completions"
```

**Handles:**
- Provider-specific URL construction
- Model name placeholders (Azure, Google)
- Query parameters (Google API)

**Usage:**
```php
$url = getAiApiUrl();
curl_setopt($ch, CURLOPT_URL, $url);
```

### 3. `getAiRequestHeaders()`

Returns HTTP headers for authentication to the active provider.

```php
$headers = getAiRequestHeaders();
// Returns: Array of header strings
```

**Handles:**
- Bearer token: `Authorization: Bearer {key}`
- Custom header: `api-key: {key}` (Azure)
- Query parameter: `?key={key}` (Google - in URL)
- No auth: Empty headers
- Special headers: API version, Anthropic version, etc.

**Usage:**
```php
$headers = getAiRequestHeaders();
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
```

### 4. `getAiModel()`

Returns the model name for the active provider.

```php
$model = getAiModel();  // "gpt-4-turbo"
```

### 5. `getAiRateLimit()`

Returns the rate limit in milliseconds for the active provider.

```php
$ms = getAiRateLimit();  // 500
usleep($ms * 1000);      // Sleep 500ms
```

### 6. `formatAiProviderPayload($method, $params)`

Formats the JSON request payload for the active provider.

```php
$payload = formatAiProviderPayload('screen_patent', [
    'messages' => [
        ['role' => 'system', 'content' => 'You are...'],
        ['role' => 'user', 'content' => 'Screen this patent...']
    ]
]);
```

**Returns:** Payload formatted for the active provider
- OpenAI: `{"model": "...", "messages": [...], "temperature": 0.3, ...}`
- Google: `{"contents": [...], "generationConfig": {...}}`
- Anthropic: `{"model": "...", "messages": [...], "max_tokens": 2000}`
- etc.

**Provider Differences Handled:**
- Different message formats (OpenAI vs. Google vs. Anthropic)
- Different parameter names
- Provider-specific constraints
- Temperature, model, tokens handling

### 7. `extractAiProviderResponse($response)`

Extracts the AI response text from provider-specific API response.

```php
$responseJson = json_decode(curl_exec($ch), true);
$content = extractAiProviderResponse($responseJson);
// Returns: The actual response text
```

**Handles:**
- Different JSON paths for each provider:
  - OpenAI: `choices[0].message.content`
  - Google: `candidates[0].content.parts[0].text`
  - Anthropic: `content[0].text`
  - Ollama: `message.content`
- Navigating nested JSON structures using dot notation
- Returning null if path doesn't exist

## Integration in AiProvider.php

### Before (Hardcoded for OpenAI)

```php
private static function realBuildElementContext($seedText, $elementText) {
    $payload = [
        'model' => AI_MODEL,  // Global constant
        'messages' => [...],
        'temperature' => 0.3,
        'response_format' => ['type' => 'json_object']
    ];
    
    curl_setopt($ch, CURLOPT_URL, AI_API_BASE_URL . '/chat/completions');
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . AI_API_KEY,  // Hardcoded header
        'Content-Type: application/json'
    ]);
    
    // ... curl call ...
    
    $data = json_decode($response, true);
    $content = $data['choices'][0]['message']['content'];  // Hardcoded path
}
```

### After (Provider-agnostic)

```php
private static function realBuildElementContext($seedText, $elementText) {
    $messages = [
        ['role' => 'system', 'content' => '...'],
        ['role' => 'user', 'content' => $prompt]
    ];
    
    $payload = formatAiProviderPayload('element_context', ['messages' => $messages]);
    
    $apiUrl = getAiApiUrl();
    curl_setopt($ch, CURLOPT_URL, $apiUrl);
    curl_setopt($ch, CURLOPT_HTTPHEADER, getAiRequestHeaders());
    
    // ... curl call ...
    
    $content = extractAiProviderResponse($response);
}
```

**Benefits:**
- No hardcoding of provider specifics
- One-line changes to support new providers
- Same code works with any configured provider
- Easy to test different providers

## Adding a New Provider

### Step 1: Add Configuration to `config/ai-config.php`

```php
'yourprovider' => [
    'name' => 'Your Provider Name',
    'api_url' => 'https://api.yourprovider.com/v1',
    'api_key' => getenv('YOURPROVIDER_API_KEY') ?: '',
    'model' => getenv('YOURPROVIDER_MODEL') ?: 'your-model',
    'auth_type' => 'bearer',
    'endpoint' => '/chat/completions',
    'rate_limit_ms' => 500,
    'request_format' => [
        'model' => 'model_name',
        'messages' => 'array',
        'temperature' => 0.3
    ],
    'response_format' => [
        'data_path' => 'your.response.path',
        'status_code' => 200
    ]
],
```

### Step 2: Add Case to `formatAiProviderPayload()`

```php
case 'yourprovider':
    return [
        'model' => $config['model'],
        'messages' => $messages,
        'temperature' => 0.3,
        // Your provider-specific fields
    ];
```

### Step 3: Add Environment Variables

Add to `.env.example`:
```env
YOURPROVIDER_API_KEY=your-key
YOURPROVIDER_MODEL=your-model
```

### Step 4: Test

```env
AI_PROVIDER=yourprovider
YOURPROVIDER_API_KEY=test-key
USE_MOCK_AI_API=false
```

Done! Your new provider is now supported.

## Environment Variable Loading

### Method 1: Apache .htaccess

```apache
PassEnv AI_PROVIDER
PassEnv OPENAI_API_KEY
PassEnv OPENAI_MODEL
# ... etc for all variables
```

**Pros:** Works with any web server
**Cons:** Requires Apache configured with mod_env

### Method 2: PHP EnvLoader

```php
// In public/index.php or entry point
require_once __DIR__ . '/../lib/EnvLoader.php';
EnvLoader::load();
```

**Pros:** Works everywhere (Windows, Linux, Mac)
**Cons:** Slight performance overhead (file read)

### Method 3: Manual Environment Variables

```bash
export AI_PROVIDER=openai
export OPENAI_API_KEY=sk-...
php -S localhost:8000 -t public
```

**Pros:** Simple, no files needed
**Cons:** Need to set before each run

## Error Handling

### Provider Not Found

```php
if (!isset($AI_PROVIDERS[$provider])) {
    Logger::error("AI provider '$provider' not found");
    return $AI_PROVIDERS['openai'];  // Fallback
}
```

### Response Extraction Failure

```php
$content = extractAiProviderResponse($response);
if (!$content) {
    Logger::error("Could not extract response content");
    return null;
}
```

### API Error

```php
if ($httpCode !== 200) {
    Logger::error("AiAPI failed with code $httpCode");
    return null;
}
```

## Performance Considerations

### Provider-Specific Rate Limits

Each provider has different rate limits:
- OpenAI: 3,500 RPM (free), higher for paid
- Google: Rate limited per quota
- Deepseek: Rate limited
- Azure: Based on deployment tier
- Anthropic: Rate limited
- Ollama: No rate limit (local)

**Solution:** Set in config:
```php
'openai' => [
    'rate_limit_ms' => 100,  // Aggressive
],
'ollama' => [
    'rate_limit_ms' => 1000,  // Conservative for local
],
```

### Caching Responses

Implement provider-agnostic caching:
```php
$cacheKey = md5($seedText . $elementText);
$cached = Cache::get($cacheKey);

if ($cached) {
    return $cached;
}

$result = self::realBuildElementContext($seedText, $elementText);
Cache::set($cacheKey, $result, 3600);  // Cache 1 hour

return $result;
```

## Common Patterns

### Pattern 1: Provider Feature Detection

```php
function supportsJsonResponse() {
    $config = getAiProviderConfig();
    return isset($config['request_format']['response_format']);
}
```

### Pattern 2: Dynamic Model Selection

```php
function selectModel($quality = 'balanced') {
    $config = getAiProviderConfig();
    $models = $config['available_models'] ?? [];
    
    switch($quality) {
        case 'fast':
            return $models['fast'] ?? $config['model'];
        case 'quality':
            return $models['quality'] ?? $config['model'];
        default:
            return $config['model'];
    }
}
```

### Pattern 3: A/B Testing Providers

```php
function randomProvider() {
    global $AI_PROVIDERS;
    $providers = array_keys($AI_PROVIDERS);
    return $providers[array_rand($providers)];
}

// Usage: Test multiple providers
$provider = randomProvider();
putenv("AI_PROVIDER=$provider");
```

## Debugging

### Enable Detailed Logging

```php
Logger::info("AI Provider: " . AI_PROVIDER);
Logger::info("API URL: " . getAiApiUrl());
Logger::info("Headers: " . json_encode(getAiRequestHeaders()));
Logger::info("Payload: " . json_encode($payload));
Logger::info("Response: " . $response);
```

### Validate Configuration

```php
// In EnvLoader.php
$missing = EnvLoader::validateAiConfig();
if (!empty($missing)) {
    Logger::error("Missing AI config: " . implode(', ', $missing));
}
```

### Test Provider Directly

```bash
# Test OpenAI
curl -X POST https://api.openai.com/v1/chat/completions \
  -H "Authorization: Bearer $OPENAI_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{"model":"gpt-4-turbo","messages":[{"role":"user","content":"Hello"}]}'
```

## Security Best Practices

1. **Never commit `.env`** - Add to `.gitignore`
2. **Use `.env.example`** - Template without real keys
3. **Validate API keys** - Check format before use
4. **Log safely** - Never log full API keys, redact
5. **Use HTTPS** - All API calls must be HTTPS
6. **Rate limit** - Prevent brute force on APIs
7. **Rotate keys** - Regularly refresh API keys
8. **Monitor usage** - Track API call patterns

---

## Summary

The centralized AI configuration system works by:

1. **Defining** all provider configurations in `config/ai-config.php`
2. **Loading** environment variables via `.env` or .htaccess
3. **Providing** helper functions that are provider-agnostic
4. **Abstracting** provider specifics (URLs, headers, payloads, responses)
5. **Using** helper functions in `AiProvider.php` instead of hardcoding

**Result:** Switch providers by editing ONE line in `.env`. No code changes needed!

This design makes the system:
- **Flexible**: Support any AI provider easily
- **Maintainable**: All provider logic in one place
- **Secure**: API keys in `.env`, not in code
- **Extensible**: Add new providers without touching business logic
- **Testable**: Easy to mock or swap providers for testing
