# AI Configuration Guide - Patent Analysis MVP

## Overview

This guide explains how to use the centralized AI configuration system to easily switch between different AI providers (OpenAI, Google AI, Deepseek, Azure OpenAI, Anthropic Claude, Ollama, or custom) without modifying any code.

## Quick Start

### 1. Copy Environment Template

```bash
cp .env.example .env
```

### 2. Choose Your AI Provider

Edit `.env` and set:

```env
AI_PROVIDER=openai  # Change to: google, deepseek, azure, anthropic, ollama, or custom
```

### 3. Add Your API Credentials

Get your API key from the provider and add it to `.env`:

#### For OpenAI:
```env
AI_PROVIDER=openai
OPENAI_API_KEY=sk-your-key-here
OPENAI_MODEL=gpt-4-turbo
USE_MOCK_AI_API=false
```

#### For Google AI:
```env
AI_PROVIDER=google
GOOGLE_API_KEY=your-key-here
GOOGLE_MODEL=gemini-2.5-flash
USE_MOCK_AI_API=false
```

#### For Deepseek:
```env
AI_PROVIDER=deepseek
DEEPSEEK_API_KEY=sk-your-key-here
DEEPSEEK_MODEL=deepseek-chat
USE_MOCK_AI_API=false
```

### 4. Load Environment Variables (Using .htaccess)

Create or update `.htaccess` in the `public/` directory:

```apache
# Load environment variables
SetEnvIf Request_URI "^/api" APP_ENV=production

# For Apache 2.2+
PassEnv AI_PROVIDER
PassEnv USE_MOCK_AI_API
PassEnv OPENAI_API_KEY
PassEnv OPENAI_MODEL
PassEnv GOOGLE_API_KEY
PassEnv GOOGLE_MODEL
PassEnv DEEPSEEK_API_KEY
PassEnv DEEPSEEK_MODEL
PassEnv AZURE_OPENAI_URL
PassEnv AZURE_OPENAI_API_KEY
PassEnv AZURE_OPENAI_MODEL
PassEnv ANTHROPIC_API_KEY
PassEnv ANTHROPIC_MODEL
PassEnv OLLAMA_URL
PassEnv OLLAMA_MODEL
PassEnv CUSTOM_AI_URL
PassEnv CUSTOM_AI_KEY
PassEnv CUSTOM_AI_MODEL
PassEnv CUSTOM_AI_ENDPOINT
```

Alternatively, use PHP to load from `.env`:

```php
<?php
// At the top of public/index.php or your entry point
if (file_exists(__DIR__ . '/../.env')) {
    $env = parse_ini_file(__DIR__ . '/../.env');
    foreach ($env as $key => $value) {
        putenv("$key=$value");
    }
}
?>
```

### 5. Test Your Configuration

Visit: `http://localhost:8000`

Check logs:
```bash
tail -f logs/app.log
```

## Supported AI Providers

### 1. OpenAI (GPT-4, GPT-3.5-Turbo)

**Best for:** Most use cases, high quality responses

**Setup:**
```env
AI_PROVIDER=openai
OPENAI_API_KEY=sk-...
OPENAI_MODEL=gpt-4-turbo
USE_MOCK_AI_API=false
```

**Get API Key:** https://platform.openai.com/api-keys

**Models Available:**
- `gpt-4` - Most capable, most expensive
- `gpt-4-turbo` - Good balance of quality and cost
- `gpt-3.5-turbo` - Fast and cheap

---

### 2. Google AI (Gemini)

**Best for:** Free tier available, good performance, latest models

**Setup:**
```env
AI_PROVIDER=google
GOOGLE_API_KEY=...
GOOGLE_MODEL=gemini-2.5-flash
USE_MOCK_AI_API=false
```

**Get API Key:** https://ai.google.dev

**Models Available:**
- `gemini-2.5-flash` - Latest, fastest, best value
- `gemini-2.0-flash` - Previous stable release

**Note:** Free tier available with usage limits

---

### 3. Deepseek

**Best for:** Cost-effective, fast

**Setup:**
```env
AI_PROVIDER=deepseek
DEEPSEEK_API_KEY=sk-...
DEEPSEEK_MODEL=deepseek-chat
USE_MOCK_AI_API=false
```

**Get API Key:** https://platform.deepseek.com/

**Models Available:**
- `deepseek-chat` - Main model for chat
- `deepseek-coder` - For code-related tasks

---

### 4. Azure OpenAI

**Best for:** Enterprise deployments, security compliance

**Setup:**
```env
AI_PROVIDER=azure
AZURE_OPENAI_URL=https://your-resource.openai.azure.com/openai/deployments/gpt-4
AZURE_OPENAI_API_KEY=...
AZURE_OPENAI_MODEL=gpt-4
USE_MOCK_AI_API=false
```

**Setup Instructions:**
1. Create an Azure OpenAI resource in Azure Portal
2. Deploy a model (e.g., gpt-4)
3. Get your endpoint URL and API key from the resource page
4. Format URL as: `https://{resource-name}.openai.azure.com/openai/deployments/{deployment-name}`

---

### 5. Anthropic Claude

**Best for:** Strong reasoning, longer contexts

**Setup:**
```env
AI_PROVIDER=anthropic
ANTHROPIC_API_KEY=sk-ant-...
ANTHROPIC_MODEL=claude-3-sonnet-20240229
USE_MOCK_AI_API=false
```

**Get API Key:** https://console.anthropic.com/

**Models Available:**
- `claude-3-opus-20240229` - Most capable
- `claude-3-sonnet-20240229` - Balanced performance
- `claude-3-haiku-20240307` - Fast and efficient

---

### 6. Ollama (Local AI)

**Best for:** Privacy, offline operation, no API costs

**Setup:**
```env
AI_PROVIDER=ollama
OLLAMA_URL=http://localhost:11434/api
OLLAMA_MODEL=mistral
USE_MOCK_AI_API=false
```

**Installation:**
1. Download Ollama: https://ollama.ai
2. Install and run: `ollama serve`
3. In another terminal, pull a model: `ollama pull mistral`

**Available Models:**
- `mistral` - Great general purpose model
- `llama2` - Meta's open-source model
- `neural-chat` - Intel's optimized model

---

### 7. Custom Provider

**Best for:** Your own backend or proxy service

**Setup:**
```env
AI_PROVIDER=custom
CUSTOM_AI_URL=https://your-service.com
CUSTOM_AI_KEY=your-key
CUSTOM_AI_MODEL=your-model
CUSTOM_AI_ENDPOINT=/api/inference
USE_MOCK_AI_API=false
```

**Implement your endpoint to accept:**
```json
{
  "model": "your-model",
  "messages": [
    {"role": "system", "content": "..."},
    {"role": "user", "content": "..."}
  ],
  "temperature": 0.3
}
```

**Return response in format:**
```json
{
  "result": "{json_response_content}"
}
```

---

## How It Works (Behind the Scenes)

### Configuration & Service Files

**`lib/AiApiService.php`** - Consolidated Single File (All AI functionality)
   - All provider configurations (OpenAI, Google, Azure, Anthropic, Deepseek, Ollama, Custom)
   - Helper functions: `getAiProviderConfig()`, `getAiApiUrl()`, `getAiRequestHeaders()`, etc.
   - Response parsing logic specific to each provider
   - `AiApiService` class: `callAi()`, `callAiJson()`, HTTP/curl handling
   - `AiProvider` class: `buildElementContext()`, `screenPatent()` with mock support

**`config/config.php`** - Loads ai-config.php
   - Simple one-liner integration

### Provider-Specific Logic

Each provider has different:
- **API endpoints:** OpenAI uses `/chat/completions`, Google uses `/models/{model}:generateContent`
- **Authentication:** Bearer token vs. header vs. query parameter
- **Request format:** Different payload structures
- **Response format:** Different JSON paths to extract answer

The system automatically handles all these differences!

### Adding a New Provider

Adding support for a new AI provider requires only updating `lib/AiApiService.php` (look for the `$GLOBALS['AI_PROVIDERS']` array):

```php
'yourprovider' => [
    'name' => 'Your Provider Name',
    'api_url' => 'https://api.yourprovider.com/v1',
    'api_key' => $savedSettings['api_key'] ?? getenv('YOURPROVIDER_API_KEY') ?: '',
    'model' => $savedSettings['model'] ?? getenv('YOURPROVIDER_MODEL') ?: 'model-name',
    'auth_type' => 'bearer', // bearer, header, query, or none
    'endpoint' => '/chat/completions',
    'rate_limit_ms' => 500,
    'response_format' => [
        'data_path' => 'your.response.path',  // JSON path to extract answer
        'status_code' => 200
    ]
],
```

Then add environment variables to `.env`:

```env
YOURPROVIDER_API_KEY=...
YOURPROVIDER_MODEL=...
```

---

## Testing & Debugging

### Step 1: Enable Mock Mode First

Always start with mock API enabled:

```env
USE_MOCK_AI_API=true
```

Test the application works end-to-end with mock data.

### Step 2: Check Logs

```bash
tail -f logs/app.log
```

Look for:
- `AI Provider: OpenAI` (confirms provider loaded)
- `AI Model: gpt-4-turbo` (confirms model)
- API request/response details

### Step 3: Switch to Real API

```env
USE_MOCK_AI_API=false
```

If errors occur, check logs for:
- `AiAPI curl error:` - Network issue
- `AiAPI failed with code 401:` - Invalid API key
- `AiAPI failed with code 429:` - Rate limit exceeded
- `could not extract response content` - Response format doesn't match provider

### Step 4: Test One Provider at a Time

When switching providers:

1. Keep `USE_MOCK_AI_API=true` first
2. Verify it works
3. Add credentials and set `USE_MOCK_AI_API=false`
4. Test thoroughly before switching to another

---

## Common Issues & Solutions

### Issue: "API provider 'xxx' not found"

**Solution:** Check `.env` file - provider name must match exactly:
- `openai` (not `openai-api`)
- `google` (not `google-ai`)
- `deepseek` (not `deep-seek`)

### Issue: "Authorization header invalid"

**Solution:** Check API key format:
- OpenAI: starts with `sk-`
- Google: long alphanumeric
- Azure: get from resource page, not regular OpenAI

### Issue: "Could not extract response content"

**Solution:** Provider response format changed. Check logs for exact response and update `response_format.data_path` in `ai-config.php`

### Issue: Rate limit errors

**Solution:** Increase `AI_SLEEP_MS`:
```env
AI_SLEEP_MS=1000  # Increased to 1 second
```

### Issue: Some providers work, others don't

**Solution:**
1. Verify API credentials are correct
2. Test with curl first:
   ```bash
   curl -X POST https://api.provider.com/endpoint \
     -H "Authorization: Bearer YOUR_KEY" \
     -H "Content-Type: application/json" \
     -d '{...}'
   ```
3. Check provider status page for outages
4. Review provider documentation for any changes

---

## Performance Comparison

| Provider | Speed | Cost | Quality | Free Tier |
|----------|-------|------|---------|-----------|
| OpenAI GPT-4 | Medium | High | Excellent | No |
| OpenAI GPT-3.5 | Fast | Low | Good | No |
| Google Gemini | Fast | Low | Very Good | Yes (limited) |
| Deepseek | Fast | Low | Good | No |
| Azure OpenAI | Varies | High | Excellent | No (enterprise) |
| Anthropic Claude | Medium | High | Excellent | No |
| Ollama (Local) | Slow | Free | Medium | Yes (offline) |

---

## Recommended Configurations

### Development (Local Testing)
```env
AI_PROVIDER=ollama
USE_MOCK_AI_API=true  # Start with mock
```

### Testing with Real API (Cost-Conscious)
```env
AI_PROVIDER=google
GOOGLE_MODEL=gemini-1.5-flash  # Cheaper than pro
USE_MOCK_AI_API=false
```

### Production (Quality Priority)
```env
AI_PROVIDER=openai
OPENAI_MODEL=gpt-4-turbo
USE_MOCK_AI_API=false
```

### Production (Enterprise)
```env
AI_PROVIDER=azure
USE_MOCK_AI_API=false
```

---

## Migration Guide: Switching Providers

### From OpenAI to Google AI

1. Get Google API key: https://makersuite.google.com/app/apikey
2. Edit `.env`:
   ```env
   AI_PROVIDER=google
   GOOGLE_API_KEY=your-key-here
   GOOGLE_MODEL=gemini-1.5-pro
   ```
3. No code changes required!
4. Test in UI: Load your analysis and run screening again
5. Results will use Google Gemini instead of OpenAI

### From Mock to Real API

1. Enable real API in `.env`:
   ```env
   USE_MOCK_AI_API=false
   ```
2. Ensure API credentials are set
3. Test with single patent first
4. Monitor logs for errors

---

## Architecture

```
config/config.php
    ↓
    Loads: config/ai-config.php
    ↓
    Defines: getAiApiUrl(), getAiRequestHeaders(), 
             formatAiProviderPayload(), extractAiProviderResponse()
    ↓
lib/AiProvider.php
    ↓
    Uses: getAiApiUrl(), getAiRequestHeaders(), etc.
    ↓
    Makes HTTP calls to any AI provider


Environment Variables → config/ai-config.php → Helper Functions → AiProvider.php
                                                     ↓
                                        No hard-coded provider logic
```

---

## Support & Resources

### Provider Documentation
- OpenAI: https://platform.openai.com/docs
- Google AI: https://ai.google.dev/
- Deepseek: https://platform.deepseek.com/docs
- Azure OpenAI: https://learn.microsoft.com/azure/cognitive-services/openai/
- Anthropic: https://docs.anthropic.com
- Ollama: https://github.com/ollama/ollama
- 

### Troubleshooting
- Check `logs/app.log` for detailed error messages
- Review provider status pages for API outages
- Test curl requests directly to provider API

### Questions?
- Review the inline comments in `config/ai-config.php`
- Check the example configuration in `.env.example`
- See the implementation in `lib/AiProvider.php`

---

## Summary

With this centralized AI configuration system, you can:

✅ Switch between any AI provider in seconds (just edit `.env`)
✅ Support multiple providers simultaneously
✅ Add new providers without touching code
✅ Manage credentials securely via environment variables
✅ Implement provider-specific logic once in `ai-config.php`
✅ Keep your business logic provider-agnostic

**No more code changes needed when changing AI providers!**
