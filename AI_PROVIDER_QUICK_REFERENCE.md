# AI Provider Quick Reference

Quick commands to switch between AI providers without changing code.

## Usage Pattern

1. **Create `.env` file** (copy from `.env.example`)
2. **Set AI_PROVIDER** and credentials
3. **Done!** No code changes needed

---

## Provider Quick Setup

### OpenAI (GPT-4, GPT-3.5-Turbo)
```bash
AI_PROVIDER=openai
OPENAI_API_KEY=sk-...
OPENAI_MODEL=gpt-4-turbo
USE_MOCK_AI_API=false
```

### Google Gemini
```bash
AI_PROVIDER=google
GOOGLE_API_KEY=...
GOOGLE_MODEL=gemini-2.5-flash
USE_MOCK_AI_API=false
```

### Deepseek
```bash
AI_PROVIDER=deepseek
DEEPSEEK_API_KEY=sk-...
DEEPSEEK_MODEL=deepseek-chat
USE_MOCK_AI_API=false
```

### Azure OpenAI
```bash
AI_PROVIDER=azure
AZURE_OPENAI_URL=https://your-resource.openai.azure.com/openai/deployments/gpt-4
AZURE_OPENAI_API_KEY=...
AZURE_OPENAI_MODEL=gpt-4
USE_MOCK_AI_API=false
```

### Anthropic Claude
```bash
AI_PROVIDER=anthropic
ANTHROPIC_API_KEY=sk-ant-...
ANTHROPIC_MODEL=claude-3-sonnet-20240229
USE_MOCK_AI_API=false
```

### Ollama (Local - Free!)
```bash
AI_PROVIDER=ollama
OLLAMA_URL=http://localhost:11434/api
OLLAMA_MODEL=mistral
USE_MOCK_AI_API=false
```

### Custom Provider
```bash
AI_PROVIDER=custom
CUSTOM_AI_URL=https://your-service.com
CUSTOM_AI_KEY=...
CUSTOM_AI_MODEL=your-model
CUSTOM_AI_ENDPOINT=/api/inference
USE_MOCK_AI_API=false
```

---

## Testing & Development

### Start with Mock (No API Key Needed)
```bash
AI_PROVIDER=openai  # or any provider
USE_MOCK_AI_API=true
```

### Enable Real API
```bash
USE_MOCK_AI_API=false
```

### Check Current Provider
Look in logs:
```bash
tail -f logs/app.log
```

Should show:
```
AI Provider: OpenAI
AI Model: gpt-4-turbo
```

---

## Cost vs Quality Comparison

| Provider | Speed | Cost | Quality | Free Tier |
|----------|-------|------|---------|-----------|
| **OpenAI GPT-4** | ⚡⚡ | $$$ | ⭐⭐⭐⭐⭐ | ❌ |
| **OpenAI GPT-3.5** | ⚡⚡⚡ | $ | ⭐⭐⭐⭐ | ❌ |
| **Google Gemini** | ⚡⚡⚡ | $$ | ⭐⭐⭐⭐⭐ | ✅ Limited |
| **Deepseek** | ⚡⚡⚡ | $ | ⭐⭐⭐⭐ | ❌ |
| **Claude 3 Opus** | ⚡⚡ | $$$ | ⭐⭐⭐⭐⭐ | ❌ |
| **Ollama Local** | ⚡ | Free | ⭐⭐⭐ | ✅ Offline |

---

## Common Errors & Solutions

| Error | Cause | Solution |
|-------|-------|----------|
| `API provider 'xxx' not found` | Wrong provider name | Check `.env` file - use exact name |
| `curl error 401` | Invalid API key | Verify key in `.env` |
| `curl error 429` | Rate limit | Increase `AI_SLEEP_MS` |
| `could not extract response content` | Provider response changed | Check logs, update `ai-config.php` |
| `failed with code 401` | Expired/invalid credentials | Refresh API key from provider |

---

## File Locations

| File | Purpose |
|------|---------|
| `.env` | Environment variables - **Your credentials go here** |
| `config/ai-config.php` | Provider configurations - All provider logic |
| `config/config.php` | Loads ai-config.php - One simple line |
| `lib/AiProvider.php` | Business logic - Uses centralized config, no hardcoding |
| `AI_CONFIG_GUIDE.md` | Complete guide - Full documentation |
| `AI_PROVIDER_QUICK_REFERENCE.md` | This file - Quick lookup |

---

## One-Provider Switch Example

### Scenario: Switch from OpenAI to Google Gemini

**Before (old way):** Edit multiple PHP files, change URLs, headers, response parsing

**Now (new way):** Edit `.env` only!

```env
# Change from:
AI_PROVIDER=openai
OPENAI_API_KEY=sk-...

# To:
AI_PROVIDER=google
GOOGLE_API_KEY=your-google-key-here
GOOGLE_MODEL=gemini-2.5-flash
```

✅ Done! No code changes.

---

## Environment Variable Loading

Your web server must load `.env` file. Choose one approach:

### Option 1: PHP (Easiest)
Add to `public/index.php`:
```php
if (file_exists(__DIR__ . '/../.env')) {
    $env = parse_ini_file(__DIR__ . '/../.env');
    foreach ($env as $key => $value) {
        putenv("$key=$value");
    }
}
```

### Option 2: Apache .htaccess
Add to `public/.htaccess`:
```apache
PassEnv AI_PROVIDER OPENAI_API_KEY GOOGLE_API_KEY ... (all variables)
```

### Option 3: Docker/Deployment
Set environment variables directly:
```bash
export AI_PROVIDER=openai
export OPENAI_API_KEY=sk-...
```

---

## API Key Acquisition

| Provider | Get Key |
|----------|---------|
| OpenAI | https://platform.openai.com/api-keys |
| Google AI | https://makersuite.google.com/app/apikey |
| Deepseek | https://platform.deepseek.com/ |
| Azure OpenAI | Azure Portal → OpenAI resource page |
| Anthropic | https://console.anthropic.com/ |
| Ollama | Install locally - no key needed |
| Custom | Your own backend |

---

## Next Steps

1. **Copy template:** `cp .env.example .env`
2. **Choose provider:** Edit `AI_PROVIDER=`
3. **Add credentials:** Fill in API key from provider
4. **Start server:** `php -S localhost:8000 -t public`
5. **Test:** Create analysis and run screening
6. **Check logs:** `tail -f logs/app.log`

For detailed setup instructions, see: **[AI_CONFIG_GUIDE.md](AI_CONFIG_GUIDE.md)**

---

## Support

- 📚 Full guide: [AI_CONFIG_GUIDE.md](AI_CONFIG_GUIDE.md)
- 🔧 Config file: [config/ai-config.php](config/ai-config.php)
- 📝 Template: [.env.example](.env.example)
- 📋 Provider logic: [lib/AiProvider.php](lib/AiProvider.php)

---

**Switch AI providers in seconds - No code changes needed! 🚀**
