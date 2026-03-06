# AI Configuration System Setup Summary

## What's New

You now have a **centralized AI configuration system** that allows you to easily switch between different AI providers (OpenAI, Google AI, Deepseek, Azure OpenAI, Anthropic Claude, Ollama, or custom providers) **without changing any code!**

## Files Created/Modified

### New Files Created

1. **`config/ai-config.php`** (NEW!)
   - Centralized configuration for all AI providers
   - Pre-configured providers: OpenAI, Google, Deepseek, Azure, Anthropic, Ollama, Custom
   - Helper functions: `getAiApiUrl()`, `getAiRequestHeaders()`, `formatAiProviderPayload()`, `extractAiProviderResponse()`
   - No hardcoded provider logic - all handled dynamically

2. **`.env.example`** (NEW!)
   - Template for environment variables
   - Shows how to configure each provider
   - Copy to `.env` and fill in your credentials

3. **`AI_CONFIG_GUIDE.md`** (NEW!)
   - Comprehensive guide with step-by-step instructions
   - Setup instructions for each provider (OpenAI, Google, Deepseek, Azure, Anthropic, Ollama, Custom)
   - Troubleshooting guide
   - Performance comparison chart
   - Migration guide for switching providers

4. **`AI_PROVIDER_QUICK_REFERENCE.md`** (NEW!)
   - Quick lookup reference for each provider
   - One-liner configurations
   - Common errors and solutions
   - API key acquisition links

5. **`public/.htaccess`** (NEW!)
   - Apache configuration to load environment variables
   - Security headers
   - MIME types and compression settings

6. **`lib/EnvLoader.php`** (NEW!)
   - Alternative PHP-based environment loader
   - Use this if .htaccess doesn't work (e.g., Windows + PHP built-in server)
   - Includes validation and debugging methods

### Files Modified

1. **`config/config.php`** - UPDATED
   - Replaced hardcoded AI configuration with a single line loading `ai-config.php`
   - Much cleaner and simpler

2. **`lib/AiProvider.php`** - UPDATED
   - `realBuildElementContext()` - Now uses centralized config functions
   - `realScreenPatent()` - Now uses centralized config functions
   - `mockScreenPatent()` - Uses `getAiRateLimit()` function
   - No provider-specific hardcoding anymore

3. **`README.md`** - UPDATED
   - Added section on new AI configuration system
   - Updated "Adding Real APIs" section with new approach
   - Links to detailed guides

## How to Use

### Step 1: Copy Environment Template
```bash
cp .env.example .env
```

### Step 2: Choose Your AI Provider
Edit `.env` and set the provider and credentials:

**Example 1: OpenAI**
```env
AI_PROVIDER=openai
OPENAI_API_KEY=sk-your-key-here
OPENAI_MODEL=gpt-4-turbo
USE_MOCK_AI_API=false
```

**Example 2: Google AI**
```env
AI_PROVIDER=google
GOOGLE_API_KEY=your-api-key-here
GOOGLE_MODEL=gemini-2.5-flash
USE_MOCK_AI_API=false
```

**Example 3: Stay in Mock Mode (No API Key Needed)**
```env
AI_PROVIDER=openai  # Can be any provider
USE_MOCK_AI_API=true
```

### Step 3: Load Environment Variables

Choose **ONE** of these methods:

#### Option A: Apache + .htaccess (Recommended for Production)
1. Already provided in `public/.htaccess`
2. Apache automatically loads `.env` variables
3. Works with any web server

#### Option B: PHP Loader (Recommended for Development)
Add this to top of `public/index.php` (or entry point):
```php
<?php
require_once __DIR__ . '/../lib/EnvLoader.php';
EnvLoader::load();
// ... rest of index.php
```

#### Option C: Manual Environment Variables (CLI/Deployment)
```bash
export AI_PROVIDER=openai
export OPENAI_API_KEY=sk-your-key
export USE_MOCK_AI_API=false
php -S localhost:8000 -t public
```

### Step 4: Test Your Configuration
1. Start server: `php -S localhost:8000 -t public`
2. Create a new analysis
3. Run a patent screening
4. Check logs: `tail -f logs/app.log`

Expected log output:
```
AI Provider: OpenAI
AI Model: gpt-4-turbo
```

## Supported Providers

| Provider | API Key | Model | Best For |
|----------|---------|-------|----------|
| **OpenAI** | From platform.openai.com | gpt-4-turbo, gpt-3.5-turbo | Most use cases |
| **Google AI** | From ai.google.dev | gemini-2.5-flash, gemini-2.0-flash | Free tier available |
| **Deepseek** | From platform.deepseek.com | deepseek-chat, deepseek-coder | Cost-effective |
| **Azure OpenAI** | From Azure Portal | gpt-4, gpt-3.5-turbo | Enterprise |
| **Anthropic** | From console.anthropic.com | claude-3-opus, claude-3-sonnet | Strong reasoning |
| **Ollama** | None (local) | mistral, llama2, neural-chat | Free, offline, private |
| **Custom** | Your own | Your model | Your backend |

## Key Features

✅ **No Code Changes**: Switch providers by editing `.env` only  
✅ **Pre-configured**: 6 major providers ready to use  
✅ **Extensible**: Adding new providers is just adding to `config/ai-config.php`  
✅ **Secure**: API keys in `.env`, not in code  
✅ **Flexible**: Mock mode for testing, real mode for production  
✅ **Provider-specific Logic**: Handles authentication, request format, response parsing automatically  
✅ **Environment Variables**: Complete `.env` template with all providers  
✅ **Helpers**: Utility functions for rate limiting, URL building, header generation, response extraction  

## Architecture

```
.env file (your credentials)
    ↓
config/ai-config.php (defines all provider configs)
    ↓
Helper functions:
  - getAiApiUrl()
  - getAiRequestHeaders()
  - formatAiProviderPayload()
  - extractAiProviderResponse()
    ↓
lib/AiProvider.php (uses helpers, no provider hardcoding)
    ↓
HTTP calls to any AI provider
```

## Common Workflows

### Workflow 1: Development with Mock Data
```env
AI_PROVIDER=openai
USE_MOCK_AI_API=true
# No API key needed, works offline
```

### Workflow 2: Testing with Real API (Cost-conscious)
```env
AI_PROVIDER=google
GOOGLE_API_KEY=your-key
GOOGLE_MODEL=gemini-2.5-flash  # Latest, best value
USE_MOCK_AI_API=false
```

### Workflow 3: Production with Best Quality
```env
AI_PROVIDER=openai
OPENAI_API_KEY=sk-...
OPENAI_MODEL=gpt-4-turbo
USE_MOCK_AI_API=false
```

### Workflow 4: Enterprise Deployment
```env
AI_PROVIDER=azure
AZURE_OPENAI_URL=https://your-resource.openai.azure.com/...
AZURE_OPENAI_API_KEY=...
AZURE_OPENAI_MODEL=gpt-4
USE_MOCK_AI_API=false
```

### Workflow 5: Switching Providers (Live)
Just edit `.env` and reload your browser. Done!

## Troubleshooting

### Issue: Variables not loading
**Solution:** Make sure `.env` file exists and is readable
```bash
ls -la .env
```

### Issue: "API provider 'xxx' not found"
**Solution:** Check `.env` - provider name must be exact:
- `openai` (not `openai-api`)
- `google` (not `google-ai`)
- Check `config/ai-config.php` for exact names

### Issue: "API key not found" errors
**Solution 1:** Check .htaccess is working:
```apache
PassEnv OPENAI_API_KEY
# in public/.htaccess
```

**Solution 2:** Use PHP loader instead:
```php
// In public/index.php
require_once __DIR__ . '/../lib/EnvLoader.php';
EnvLoader::load();
```

### Issue: .htaccess not found errors
**Solution:** On Windows or with PHP built-in server, use `EnvLoader.php` instead

## Next Steps

1. **Copy template:** `cp .env.example .env`
2. **Choose provider:** See [AI_CONFIG_GUIDE.md](AI_CONFIG_GUIDE.md)
3. **Add credentials:** Fill in API key and model
4. **Load variables:** Use .htaccess or EnvLoader.php
5. **Test:** Start server and create an analysis
6. **Check logs:** Verify provider loaded correctly

## Documentation

- **Quick Start:** [AI_PROVIDER_QUICK_REFERENCE.md](AI_PROVIDER_QUICK_REFERENCE.md) - 2-minute read
- **Full Guide:** [AI_CONFIG_GUIDE.md](AI_CONFIG_GUIDE.md) - Complete documentation
- **Config File:** [config/ai-config.php](config/ai-config.php) - All provider definitions
- **Environment Template:** [.env.example](.env.example) - Configuration template
- **Environment Loader:** [lib/EnvLoader.php](lib/EnvLoader.php) - PHP-based loader
- **README:** [README.md](README.md) - Updated main documentation

## Support Resources

- OpenAI: https://platform.openai.com/docs/
- Google AI: https://ai.google.dev/
- Deepseek: https://platform.deepseek.com/
- Azure OpenAI: https://learn.microsoft.com/azure/cognitive-services/openai/
- Anthropic: https://docs.anthropic.com/
- Ollama: https://github.com/ollama/ollama

---

**You can now switch between any AI provider without touching any code! 🎉**

**Edit `.env` → Provider switches → API calls go to new provider → Done!**
