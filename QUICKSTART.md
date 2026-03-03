# Patent Analysis MVP - Quick Start Guide

## 🚀 Getting Started in 2 Minutes

### Prerequisites
- PHP 8.0+ installed and in your PATH
- SQLite PDO extension enabled

### Installation

#### **Windows Users:**
```
Double-click: setup.bat
```

#### **Mac/Linux Users:**
```bash
chmod +x setup.sh
./setup.sh
```

#### **Manual Setup (Any OS):**
```bash
# 1. Initialize database
php scripts/init_db.php

# 2. Start server
php -S localhost:8000 -t public

# 3. Open browser
http://localhost:8000
```

### Default Login
```
Username: admin
Password: admin
```

---

## 📋 The 3-Step Workflow

### Step 1️⃣: Seed + Key Elements
1. Create a new analysis
2. Provide seed (patent number or free text)
3. Click "Fetch Seed Text"
4. Add key elements (one per line)
5. Click "Generate Context for All"
6. Review and approve contexts

### Step 2️⃣: Upload + Screen Patents
1. Paste patent numbers (one per line)
2. Click "Add Patents"
3. Choose text scope (claims+abstract recommended)
4. Click "Start Screening"
5. Wait for results table to populate

### Step 3️⃣: Export Results
1. View ranked results
2. Click "📥 Export CSV" or "📄 Export HTML Report"
3. Download file

---

## 💡 Tips

### For Testing
- Use mock mode (default) to test without API credentials
- Mock patents return sample data instantly
- AI mocks return deterministic results

### For Demo
- Create analysis with seed = "machine learning"
- Add elements: "neural network", "feature extraction", "classification"
- Add 5-10 sample patents (any format)
- Run screening to see mock results

### For Real APIs
- Edit `config/config.php`
- Set `USE_MOCK_PATENT_API = false`
- Set `USE_MOCK_AI_API = false`
- Add your API credentials
- Adjust `AI_SLEEP_MS` for rate limiting

---

## 📁 Key Files

| File | Purpose |
|------|---------|
| `config/config.php` | All settings (APIs, auth, limits) |
| `lib/PatentProvider.php` | Patent data fetching |
| `lib/AiProvider.php` | AI context + screening |
| `public/step1.php` | Seed + elements UI |
| `public/step2.php` | Screening UI |
| `public/step3.php` | Results + export UI |
| `prompts/*.txt` | AI prompt templates |
| `logs/app.log` | Debug log file |

---

## ❓ Troubleshooting

### "PHP not found"
- Install PHP 8.0+: https://www.php.net/downloads
- Add PHP to system PATH

### "Database error"
- Run: `php scripts/init_db.php`
- Check `data/` folder is writable

### "Session timeout"
- Adjust `SESSION_TIMEOUT` in `config/config.php`
- Default is 1 hour (3600 seconds)

### "API errors"
- Check `logs/app.log` for details
- Verify API credentials in `config/config.php`
- Test API endpoint manually

### Port 8000 already in use
```bash
php -S localhost:8001 -t public
```

---

## 📊 Sample Data

### Try This Demo:
1. Seed patent: `US10123456B2` (mock returns sample)
2. Key elements:
   ```
   machine learning
   neural network
   classification model
   feature extraction
   ```
3. Patents to screen:
   ```
   US10000001B2
   US10000002B2
   US10000003B2
   ```

---

## 🔐 Security Notes

- Default admin password should be changed in production
- Passwords are hashed in `config.php` (edit to use proper hashing)
- All API keys should be in environment variables (not in git)
- Enable HTTPS in production

---

## 📈 Scale to Production

Current MVP handles: **20-50 patents**

To scale:
1. Implement job queue (e.g., Redis)
2. Add database caching layer
3. Use PostgreSQL instead of SQLite
4. Add result pagination
5. Implement exponential backoff for API retries

---

**Need Help?** Check README.md for detailed documentation.
