# 🚀 AI Config Quick Start - 5 Minutes

Get up and running with the new centralized AI configuration system in 5 minutes.

## The Goal

**Switch between ANY AI provider (OpenAI, Google, Deepseek, etc.) by editing ONE file – no code changes!**

---

## 5-Minute Setup

### Step 1: Copy Template (30 seconds)

```bash
cp .env.example .env
```

### Step 2: Choose Your Provider (1 minute)

Edit `.env` and uncomment your choice:

#### Option A: Mock Mode (No API Key - Great for Testing)
```env
AI_PROVIDER=openai
USE_MOCK_AI_API=true
```

#### Option B: OpenAI (GPT-4)
Get key from: https://platform.openai.com/api-keys
```env
AI_PROVIDER=openai
OPENAI_API_KEY=sk-your-key-here
OPENAI_MODEL=gpt-4-turbo
USE_MOCK_AI_API=false
```

#### Option C: Google Gemini (Free Tier Available!)
Get key from: https://makersuite.google.com/app/apikey
```env
AI_PROVIDER=google
GOOGLE_API_KEY=your-key-here
GOOGLE_MODEL=gemini-2.5-flash
USE_MOCK_AI_API=false
```

#### Option D: Deepseek (Cheap!)
Get key from: https://platform.deepseek.com/
```env
AI_PROVIDER=deepseek
DEEPSEEK_API_KEY=sk-...
DEEPSEEK_MODEL=deepseek-chat
USE_MOCK_AI_API=false
```

### Step 3: Load Environment Variables (2 minutes)

**Choose ONE approach:**

#### Approach 1: PHP Loader (Easiest for Development)

Edit `public/index.php` - add this at the TOP:

```php
<?php
// Load environment variables
require_once __DIR__ . '/../lib/EnvLoader.php';
EnvLoader::load();

// Rest of your code...
```

#### Approach 2: Apache .htaccess (For Production)

File `public/.htaccess` already has it configured! Just verify:

```apache
PassEnv AI_PROVIDER
PassEnv OPENAI_API_KEY
PassEnv OPENAI_MODEL
# etc...
```

#### Approach 3: Manual (Command Line)

```bash
export AI_PROVIDER=openai
export OPENAI_API_KEY=sk-your-key
export OPENAI_MODEL=gpt-4-turbo
php -S localhost:8000 -t public
```

### Step 4: Test (1.5 minutes)

```bash
php -S localhost:8000 -t public
```

Visit: http://localhost:8000

1. Login (admin/admin)
2. Create new analysis
3. Run a patent screening
4. Check logs:
   ```bash
   tail -f logs/app.log
   ```

You should see:
```
AI Provider: OpenAI
AI Model: gpt-4-turbo
[API_REQ] AiAPI POST https://api.openai.com/v1/chat/completions
```

---

## Common Questions

### Q: How do I switch providers?
**A:** Edit `.env`, change `AI_PROVIDER`, and reload browser. That's it!

### Q: My provider needs a proxy/custom URL?
**A:** Use `custom` provider in `.env`:
```env
AI_PROVIDER=custom
CUSTOM_AI_URL=https://your-proxy.com/api
CUSTOM_AI_KEY=your-key
```

Edit response extraction in `config/ai-config.php` if needed.

### Q: Do I need to change PHP code?
**A:** Nope! Everything is in `.env`. The system is provider-agnostic.

### Q: What if I want to test multiple providers?
**A:** Set up a `.env.openai`, `.env.google`, etc. Copy the one you want to `.env` and reload.

### Q: How do I know if it's working?
**A:** Check `logs/app.log` for:
- `AI Provider: [Your Provider]`
- `AI Model: [Your Model]`
- `[API_REQ]` and `[API_RESP]` entries

---

## Provider Comparison

| Provider | Speed | Cost | Quality | Setup Time | Free Tier |
|----------|-------|------|---------|------------|-----------|
| **Mock** | Instant | Free | Beta | 1 min | ✅ Yes |
| **OpenAI GPT-4** | Medium | $$ | Excellent | 2 min | ❌ No |
| **Google Gemini** | Fast | $ | Excellent | 2 min | ✅ Limited |
| **Deepseek** | Fast | $ | Good | 2 min | ❌ No |
| **Ollama Local** | Slow | Free | Medium | 5 min | ✅ Offline |

---

## File Reference

| File | Purpose |
|------|---------|
| `.env` | Your configuration (create by copying `.env.example`) |
| `config/ai-config.php` | All provider definitions (don't edit unless adding new provider) |
| `lib/AiProvider.php` | Uses centralized config (no hardcoding) |
| `lib/EnvLoader.php` | Loads `.env` file (optional, for development) |

---

## Troubleshooting

### Error: "API provider 'openai' not found"
**Solution:** Check `.env` file exists and has `AI_PROVIDER=openai`

### Error: "API key not valid"
**Solution:** 
1. Verify API key is correct
2. Check provider-specific format:
   - OpenAI: starts with `sk-`
   - Google: long alphanumeric
   - Azure: get from portal

### No logs appearing
**Solution:** Enable environment loading (use EnvLoader.php approach)

### Still using mock data?
**Solution:** Check `USE_MOCK_AI_API=false` in `.env`

---

## Next: Deep Dive

Want to know more?

- **Provider-specific setup:** [AI_CONFIG_GUIDE.md](AI_CONFIG_GUIDE.md)
- **All providers & options:** [AI_PROVIDER_QUICK_REFERENCE.md](AI_PROVIDER_QUICK_REFERENCE.md)
- **Technical details:** [IMPLEMENTATION_REFERENCE.md](IMPLEMENTATION_REFERENCE.md)
- **Full setup guide:** [SETUP_AI_CONFIG.md](SETUP_AI_CONFIG.md)

---

## You're Done! 🎉

You now have a centralized AI configuration system that:

✅ Works with OpenAI, Google, Deepseek, Azure, Anthropic, Ollama, or custom providers
✅ Requires NO code changes to switch providers
✅ Only needs `.env` file editing
✅ Supports mock mode for development
✅ Is production-ready

**All configured? Start building! 🚀**

```bash
php -S localhost:8000 -t public
```

Visit: http://localhost:8000 (admin/admin)
