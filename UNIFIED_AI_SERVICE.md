# Unified AI API Service

## Overview

All AI API calls in the application now go through a **single centralized service** (`AiApiService`), eliminating duplicate code and ensuring consistent error handling, logging, and configuration across all AI interactions.

---

## Architecture

### Before (Old Way)
```
buildElementContext()
  ├─ curl_init()
  ├─ curl_exec()
  └─ extractResponse()

screenPatent()
  ├─ curl_init()
  ├─ curl_exec()
  └─ extractResponse()
```

**Issues:**
- ❌ Duplicate HTTP code
- ❌ Inconsistent error handling
- ❌ Hard to maintain
- ❌ Hard to modify API behavior globally

### After (New Way)
```
buildElementContext() ──┐
                        ├─→ AiApiService::callAiJson() ──→ HTTP Call
screenPatent() ────────┤
                        └─→ AiApiService::callAi()
```

**Benefits:**
- ✅ Single point of API handling
- ✅ Consistent logging
- ✅ Easy to modify behavior globally
- ✅ Centralized error handling
- ✅ All AI calls go through same path

---

## Usage

### Basic Call (Returns String)
```php
require_once 'lib/AiApiService.php';

$response = AiApiService::callAi('Your prompt here');
```

### JSON Call (Auto-parses JSON)
```php
$jsonData = AiApiService::callAiJson('Your prompt', [
    'system_message' => 'You are a helpful assistant'
]);

// Returns parsed JSON array or null
if ($jsonData) {
    echo $jsonData['definition'];
}
```

### With Custom System Message
```php
$result = AiApiService::callAiJson(
    'Analyze this: cache optimization',
    ['system_message' => 'You are a patent analyst. Respond with JSON.']
);
```

---

## API Methods

### `AiApiService::callAi($prompt, $options = [])`

**Parameters:**
- `$prompt` (string): The prompt to send to AI
- `$options` (array): Optional configuration
  - `system_message`: Custom system message (default: generic)
  - `temperature`: Can be added to options

**Returns:** `string|null` - Raw AI response or null on error

**Features:**
- Automatic provider detection
- Consistent error handling
- Built-in logging
- SSL/TLS support

---

### `AiApiService::callAiJson($prompt, $options = [])`

**Parameters:** Same as `callAi()`

**Returns:** `array|null` - Parsed JSON response or null on error

**Features:**
- Auto-parses JSON responses
- Handles markdown code blocks (e.g., ` ```json {...}``` `)
- Returns associative array

---

## Configuration (Unchanged)

No new configuration needed! The service uses existing settings:

```env
AI_PROVIDER=google              # Provider: google, openai, deepseek, etc.
GOOGLE_API_KEY=your-api-key    # API credentials
GOOGLE_MODEL=gemini-2.5-flash  # Model name
USE_MOCK_AI_API=false          # Use real or mock API
```

---

## Internal Implementation

The service handles:

1. **Message Formatting** - Converts to provider-specific format
2. **HTTP Requests** - cURL with proper headers and options
3. **Error Handling** - HTTP codes, cURL errors, JSON parsing
4. **Response Extraction** - Provider-specific response parsing
5. **Logging** - All calls and responses logged

### Flow Diagram
```
callAi($prompt, $options)
  │
  ├─ Get config: getAiProviderConfig()
  ├─ Build messages (system + user)
  ├─ Format for provider: formatAiProviderPayload()
  ├─ Get API URL: getAiApiUrl()
  ├─ Get headers: getAiRequestHeaders()
  │
  ├─ makeHttpRequest()
  │  ├─ curl_init()
  │  ├─ Set options
  │  ├─ curl_exec()
  │  ├─ Check HTTP code (200 = success)
  │  └─ curl_close()
  │
  ├─ Extract response: extractAiProviderResponse()
  └─ Return response
```

---

## Migration Summary

### Files Modified

1. **`lib/AiApiService.php`** (NEW)
   - New centralized service class
   - Handles all HTTP calls
   - Public methods: `callAi()`, `callAiJson()`
   - Private method: `makeHttpRequest()`

2. **`lib/AiProvider.php`** (UPDATED)
   - `realBuildElementContext()` - Now uses `AiApiService::callAiJson()`
   - `realScreenPatent()` - Now uses `AiApiService::callAiJson()`
   - Removed all direct cURL code
   - Added `require_once 'AiApiService.php'`

### Code Reduction

- **Removed:** ~120 lines of duplicate cURL code
- **Added:** ~90 lines in unified service
- **Net savings:** ~30 lines + massive code quality improvement

---

## Benefits

### For Developers
- ✅ Write `AiApiService::callAi($prompt)` instead of 15 lines of cURL
- ✅ Change providers by editing `.env`, not code
- ✅ Consistent error handling everywhere

### For Maintenance
- ✅ One place to add debugging
- ✅ One place to add retry logic
- ✅ One place to add rate limiting
- ✅ One place to add caching

### For Testing
- ✅ Mock the service easily
- ✅ All tests use same API path
- ✅ Easier to test error scenarios

---

## Future Enhancements

The centralized service makes these additions trivial:

```php
// Add retry logic (one place)
AiApiService::callAi($prompt, ['max_retries' => 3]);

// Add caching (one place)
AiApiService::callAi($prompt, ['cache' => true]);

// Add rate limiting (one place)
AiApiService::callAi($prompt, ['rate_limit' => 2000]);

// Switch provider (one place - .env)
AI_PROVIDER=openai  # Changes all calls!
```

---

## Testing

Run the test script:
```bash
php test_unified_ai_service.php
```

Expected output:
```
================================================================================
UNIFIED AI API SERVICE TEST
================================================================================

TEST 1: Simple AI Call
✓ Response received

TEST 2: AI Call with JSON Parsing
✓ JSON parsed successfully

TEST 3: Active Configuration
Provider: Google AI
Model: gemini-2.5-flash
```

---

## Troubleshooting

### Service returns null
1. Check API key in settings
2. Check application logs
3. Run `test_unified_ai_service.php`

### Quota exceeded errors
1. Check `/memories/session/google-gemini-api-fix.md` for details
2. Upgrade plan or wait for quota reset

### JSON parsing failed
- Response is still returned as string
- Use `callAi()` instead of `callAiJson()`
- Check prompt format

---

## File Locations

| File | Purpose |
|------|---------|
| `lib/AiApiService.php` | Unified API service |
| `lib/AiProvider.php` | Uses AiApiService now |
| `config/ai-config.php` | Provider configurations |
| `test_unified_ai_service.php` | Test the service |

