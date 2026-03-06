# Patent Analysis MVP - Complete Technical Documentation

## Table of Contents
1. [Project Overview](#project-overview)
2. [Architecture & File Structure](#architecture--file-structure)
3. [Database Schema](#database-schema)
4. [Authentication System](#authentication-system)
5. [AI Configuration System](#ai-configuration-system)
6. [Core API Services](#core-api-services)
7. [Public Pages](#public-pages)
8. [Configuration Files](#configuration-files)
9. [How to Modify & Extend](#how-to-modify--extend)

---

## Project Overview

This is a **Patent Analysis MVP (Minimum Viable Product)** - a web application for analyzing patents against key elements using AI. It supports multiple users with their own AI configurations.

**Key Features:**
- Multi-user support with admin approval
- User-specific AI configuration (provider, model, API key)
- Patent data fetching from Unified Patents API
- AI-powered element context generation
- Patent relevance screening

---

## Architecture & File Structure

```
Analysis vs code/
├── config/
│   ├── config.php           # Main configuration
│   └── ai-config.php        # AI settings (legacy)
├── data/
│   ├── app.db               # SQLite database
│   └── ai_settings.json    # Fallback AI settings
├── lib/
│   ├── Database.php         # Database access layer
│   ├── Logger.php           # Logging system
│   ├── AiApiService.php    # AI API integration
│   ├── AiProvider.php      # AI operations (legacy)
│   ├── PatentProvider.php  # Patent data provider
│   └── PatentDataService.php
├── public/
│   ├── index.php            # Dashboard
│   ├── login.php           # Login page
│   ├── register.php         # User registration
│   ├── logout.php          # Logout handler
│   ├── admin_users.php     # Admin user management
│   ├── ai_config.php       # AI configuration page
│   ├── create_analysis.php # Create new analysis
│   ├── step1.php           # Step 1: Define elements
│   ├── step2.php           # Step 2: Generate context
│   ├── step3.php           # Step 3: Screen patents
│   ├── patent_detail.php   # View patent details
│   ├── api.php             # Patent API proxy
│   ├── diagnostics.php     # System diagnostics
│   └── assets/             # CSS & JS files
├── scripts/
│   ├── init_db.php         # Database initialization
│   └── patch_db.php        # Database patches
├── prompts/
│   ├── element_context.txt # AI prompt for context
│   └── screening.txt       # AI prompt for screening
└── logs/
    └── app.log             # Application logs
```

---

## Database Schema

### Tables Created by `scripts/init_db.php`

#### 1. `users` - User accounts table
```sql
CREATE TABLE users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    username TEXT NOT NULL UNIQUE,      -- Unique username
    password TEXT NOT NULL,              -- Hashed password
    email TEXT,                          -- Optional email
    api_key TEXT,                        -- User's AI API key
    ai_provider TEXT DEFAULT 'google',  -- AI provider: google, openai, anthropic, deepseek, azure, ollama
    ai_model TEXT DEFAULT 'gemini-2.5-flash', -- AI model
    role TEXT DEFAULT 'user',           -- 'admin' or 'user'
    status TEXT DEFAULT 'pending',       -- 'pending', 'approved', 'rejected'
    use_mock INTEGER DEFAULT 0,         -- (deprecated)
    use_mock_patent INTEGER DEFAULT 1,  -- (deprecated)
    created_at TEXT DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT DEFAULT CURRENT_TIMESTAMP
);
```

#### 2. `analyses` - Patent analyses
```sql
CREATE TABLE analyses (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,                  -- Analysis name
    seed_type TEXT NOT NULL,             -- 'patent_number' or 'text'
    seed_value TEXT NOT NULL,            -- Patent number or seed text
    seed_extracted_text TEXT,            -- Extracted text from seed
    created_at TEXT DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT DEFAULT CURRENT_TIMESTAMP
);
```

#### 3. `key_elements` - Elements to analyze
```sql
CREATE TABLE key_elements (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    analysis_id INTEGER NOT NULL,
    element_order INTEGER NOT NULL,
    element_text TEXT NOT NULL,
    approved INTEGER DEFAULT 0,
    context_json TEXT,                   -- AI-generated context
    context_error TEXT,
    user_notes TEXT,
    created_at TEXT DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY(analysis_id) REFERENCES analyses(id) ON DELETE CASCADE
);
```

#### 4. `patents` - Patents to screen
```sql
CREATE TABLE patents (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    analysis_id INTEGER NOT NULL,
    patent_number TEXT NOT NULL,
    claims_text TEXT,
    abstract_text TEXT,
    fetch_status TEXT DEFAULT 'pending',  -- 'pending', 'success', 'error'
    fetch_error TEXT,
    created_at TEXT DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY(analysis_id) REFERENCES analyses(id) ON DELETE CASCADE
);
```

#### 5. `screening_results` - AI screening results
```sql
CREATE TABLE screening_results (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    patent_id INTEGER NOT NULL,
    overall_label TEXT,           -- 'relevant', 'borderline', 'not_relevant'
    overall_score REAL,           -- 0-100 score
    reasoning_short TEXT,
    per_element_json TEXT,         -- Per-element results
    created_at TEXT DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY(patent_id) REFERENCES patents(id) ON DELETE CASCADE
);
```

#### 6. `user_analyses` - Links users to their analyses
```sql
CREATE TABLE user_analyses (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    analysis_id INTEGER NOT NULL,
    created_at TEXT DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY(analysis_id) REFERENCES analyses(id) ON DELETE CASCADE
);
```

---

## Authentication System

### Flow

1. **Registration** (`public/register.php`)
   - User fills form with username, password, email
   - Selects AI provider and model
   - Enters their API key
   - Account created with `status = 'pending'`
   - Requires admin approval to login

2. **Login** (`public/login.php`)
   - Checks username against `users` table
   - Verifies password with `password_verify()`
   - Only allows login if `status = 'approved'`
   - Sets session variables:
     ```php
     $_SESSION['authenticated'] = true;
     $_SESSION['user_id'] = $user['id'];
     $_SESSION['username'] = $user['username'];
     $_SESSION['role'] = $user['role'];
     ```

3. **Admin Approval** (`public/admin_users.php`)
   - Admin can approve, reject, or delete pending users
   - Only users with `role = 'admin'` can access

### Files Involved
- `lib/Database.php` - `getUserByUsername()`, `createUser()`, `updateUserStatus()`
- `config/config.php` - Session management, auth checks

---

## AI Configuration System

### How It Works

1. **User Registers** with AI settings OR updates via `ai_config.php`
2. **Settings Saved** to `users` table in database
3. **On Each API Call**, `AiApiService.php` loads user's settings from database
4. **API Calls** use the selected provider/model/API key

### Supported AI Providers

| Provider | API URL | Auth Type |
|----------|---------|-----------|
| Google (Gemini) | `generativelanguage.googleapis.com/v1` | Query param |
| OpenAI | `api.openai.com/v1` | Bearer token |
| Anthropic | `api.anthropic.com/v1` | Bearer token |
| Deepseek | `api.deepseek.com/v1` | Bearer token |
| Azure | `{resource}.openai.azure.com` | Header |
| Ollama | `localhost:11434/api` | None |

### Key Functions in `lib/AiApiService.php`

```php
// Get user settings from database (called on each API request)
function getUserSettings() { ... }

// Get current AI provider configuration
function getAiProviderConfig() { ... }

// Get full API URL for current provider
function getAiApiUrl() { ... }

// Get request headers for current provider
function getAiRequestHeaders() { ... }

// Get model name for current provider
function getAiModel() { ... }

// Format request payload for current provider
function formatAiProviderPayload($method, $params = []) { ... }

// Extract response from provider-specific format
function extractAiProviderResponse($response) { ... }
```

### AiApiService Class
```php
class AiApiService {
    // Call AI with a prompt, returns string
    public static function callAi($prompt, $options = []) { ... }
    
    // Call AI and parse JSON response
    public static function callAiJson($prompt, $options = []) { ... }
}
```

### AiProvider Class
```php
class AiProvider {
    // Build context for a key element from seed text
    public static function buildElementContext($seedText, $elementText) { ... }
    
    // Screen patent against key element contexts
    public static function screenPatent($contextsJson, $patentText) { ... }
}
```

---

## Core API Services

### Database Class (`lib/Database.php`)

**Singleton Pattern:** Use `Database::getInstance()` to get the database connection.

**User Methods:**
```php
// Create new user (status = 'pending')
$db->createUser($username, $password, $email, $apiKey, $aiProvider, $aiModel);

// Get user by username
$user = $db->getUserByUsername($username);

// Get user by ID
$user = $db->getUserById($id);

// Get all users (admin only)
$users = $db->getAllUsers();

// Get pending users
$pending = $db->getPendingUsers();

// Update user status (pending/approved/rejected)
$db->updateUserStatus($userId, $status);

// Update user details (AI config, etc.)
$db->updateUser($userId, [
    'ai_provider' => 'openai',
    'ai_model' => 'gpt-4',
    'api_key' => 'sk-...',
    'email' => 'user@example.com'
]);

// Delete user
$db->deleteUser($userId);

// Check if username exists
$exists = $db->userExists($username);
```

**Analysis Methods:**
```php
// Create new analysis
$id = $db->createAnalysis($name, $seedType, $seedValue);

// Get analysis by ID
$analysis = $db->getAnalysis($id);

// Get all analyses
$analyses = $db->getAllAnalyses();

// Delete analysis
$db->deleteAnalysis($id);
```

**Key Element Methods:**
```php
// Add key element
$db->addKeyElement($analysisId, $order, $elementText);

// Get all elements for analysis
$elements = $db->getKeyElements($analysisId);

// Update element context (AI generated)
$db->updateKeyElementContext($elementId, $contextJson);

// Update element approval status
$db->updateKeyElementApproval($elementId, $approved, $notes);

// Delete element
$db->deleteKeyElement($elementId);
```

**Patent Methods:**
```php
// Add patent to analysis
$id = $db->addPatent($analysisId, $patentNumber);

// Get patent by ID
$patent = $db->getPatent($id);

// Get all patents for analysis
$patents = $db->getPatentsByAnalysis($analysisId);

// Update patent text (fetched from API)
$db->updatePatentText($patentId, $claims, $abstract, $status, $error);
```

**Screening Results Methods:**
```php
// Add screening result
$db->addScreeningResult($patentId, $label, $score, $reasoning, $perElementJson);

// Get latest screening result for patent
$result = $db->getScreeningResult($patentId);

// Get all results for analysis
$results = $db->getAnalysisResults($analysisId);
```

---

## Public Pages

### URL Routes (assuming running on localhost:8000)

| URL | File | Description |
|-----|------|-------------|
| `/` | index.php | Dashboard with analyses list |
| `/login.php` | login.php | Login form |
| `/register.php` | register.php | User registration |
| `/logout.php` | logout.php | Logout handler |
| `/admin_users.php` | admin_users.php | User management (admin) |
| `/ai_config.php` | ai_config.php | AI configuration |
| `/create_analysis.php` | create_analysis.php | Create new analysis |
| `/step1.php?analysis_id=X` | step1.php | Define key elements |
| `/step2.php?analysis_id=X` | step2.php | Generate context |
| `/step3.php?analysis_id=X` | step3.php | Screen patents |
| `/patent_detail.php?id=X` | patent_detail.php | View patent details |
| `/api.php` | api.php | Patent API proxy |
| `/diagnostics.php` | diagnostics.php | System diagnostics |

### Page Details

#### 1. Login (`public/login.php`)
- Accepts username/password
- Checks against `users` table
- Only allows `status = 'approved'` users
- Redirects to dashboard on success

#### 2. Register (`public/register.php`)
- Form fields: username, email, password, confirm password
- AI config: provider, model, API key
- Creates user with `status = 'pending'`
- Shows success message after registration

#### 3. Admin Users (`public/admin_users.php`)
- Lists all users with status
- Actions: Approve, Reject, Deactivate, Delete
- Only accessible to admin role

#### 4. AI Config (`public/ai_config.php`)
- Change AI provider, model, API key
- Saves to user's record in database
- Changes take effect immediately

#### 5. Dashboard (`public/index.php`)
- Lists all analyses
- Shows "My Analyses" section
- Patent Info Viewer at bottom

#### 6. Step 1 - Define Elements (`public/step1.php`)
- Add key elements to analyze
- Set element order
- Can approve/edit elements

#### 7. Step 2 - Generate Context (`public/step2.php`)
- Uses AI to generate context for each element
- Shows progress bar during generation
- Stores context in `key_elements.context_json`

#### 8. Step 3 - Screen Patents (`public/step3.php`)
- Enter patent numbers to screen
- Uses AI to screen each patent
- Shows results with scores and labels

---

## Configuration Files

### `config/config.php`

```php
// Database
define('DB_PATH', __DIR__ . '/../data/app.db');

// Auth (legacy - now in database)
define('ADMIN_USERNAME', 'admin');
define('ADMIN_PASSWORD', 'admin');
define('SESSION_TIMEOUT', 3600);

// Patent API
define('PATENT_API_BASE_URL', 'https://api.unifiedpatents.com/patents');
define('PATENT_API_KEY', '');

// Processing
define('AI_SLEEP_MS', 500);      // Rate limiting
define('BATCH_SIZE', 5);
define('MAX_PATENTS_MVP', 100);

// Logging
define('LOG_PATH', __DIR__ . '/../logs/app.log');
define('LOG_API_REQUESTS', true);
```

### `lib/AiApiService.php` - Provider Configuration

To add a new AI provider, add to `$GLOBALS['AI_PROVIDERS']`:

```php
'newprovider' => [
    'name' => 'New Provider',
    'api_url' => 'https://api.newprovider.com/v1',
    'api_key' => $savedSettings['api_key'] ?? '',
    'model' => $savedSettings['model'] ?? 'default-model',
    'auth_type' => 'bearer',  // 'bearer', 'header', 'query', 'none'
    'endpoint' => '/chat/completions',
    'rate_limit_ms' => 500,
    'response_format' => ['data_path' => 'choices.0.message.content', 'status_code' => 200]
]
```

---

## How to Modify & Extend

### 1. Change Default AI Provider

Edit `lib/AiApiService.php`, find:
```php
define('AI_PROVIDER', $savedSettings['provider'] ?? getenv('AI_PROVIDER') ?: 'google');
```
Change `'google'` to your preferred default.

### 2. Add New AI Provider

1. Add provider config in `lib/AiApiService.php`:
```php
'yourprovider' => [
    'name' => 'Your AI',
    'api_url' => 'https://api.yourprovider.com',
    'api_key' => '',
    'model' => 'default-model',
    'auth_type' => 'bearer',
    'endpoint' => '/completions',
    'rate_limit_ms' => 500,
    'response_format' => ['data_path' => 'result', 'status_code' => 200]
]
```

2. Add to dropdown in `public/register.php` and `public/ai_config.php`:
```html
<option value="yourprovider">Your AI Provider</option>
```

### 3. Change Session Timeout

Edit `config/config.php`:
```php
define('SESSION_TIMEOUT', 7200); // 2 hours
```

### 4. Add New Database Field to Users

1. Modify `scripts/init_db.php` to add column
2. Modify `lib/Database.php` `createUser()` method
3. Modify `public/register.php` form if needed

### 5. Change Patent API Source

Edit `config/config.php`:
```php
define('PATENT_API_BASE_URL', 'https://your-patent-api.com/patents');
define('PATENT_API_KEY', 'your-api-key');
```

### 6. Add New Page

1. Create file in `public/`
2. Add authentication check at top:
```php
<?php
require_once __DIR__ . '/../config/config.php';
if (!isset($_SESSION['authenticated'])) {
    header('Location: login.php');
    exit;
}
?>
```
3. Add link in header navigation

### 7. Modify AI Prompt

Edit files in `prompts/`:
- `element_context.txt` - Prompt for generating element context
- `screening.txt` - Prompt for patent screening

### 8. Reset Database

```bash
php scripts/init_db.php
```

This deletes existing data and creates fresh tables with default admin user (admin/admin).

---

## Troubleshooting

### "Database is locked" Error
This is fixed with WAL mode. If it persists:
- Check no other process is using the database
- Increase busy timeout in `lib/Database.php`:
```php
$this->pdo->exec('PRAGMA busy_timeout = 10000');
```

### AI API Not Working
1. Check user has valid API key in database
2. Check logs in `logs/app.log`
3. Verify API key has sufficient credits
4. Check provider status (Outages)

### User Can't Login
1. Check user status in database (must be 'approved')
2. Check username/password is correct
3. Check session is not expired

---

## API Keys Required

Get free/paid API keys from:

| Provider | URL |
|----------|-----|
| Google AI | https://aistudio.google.com/app/apikey |
| OpenAI | https://platform.openai.com/api-keys |
| Anthropic | https://console.anthropic.com/ |
| Deepseek | https://platform.deepseek.com/ |
| Azure | https://azure.microsoft.com/products/ai-services/openai-service |
| Ollama | https://ollama.ai/ |

---

## Quick Reference

| Task | File to Change |
|------|----------------|
| Add user field | `lib/Database.php`, `public/register.php` |
| Add AI provider | `lib/AiApiService.php`, registration pages |
| Change auth logic | `public/login.php`, `lib/Database.php` |
| Add admin feature | `public/admin_users.php` |
| Modify prompts | `prompts/*.txt` |
| Change API settings | `config/config.php` |

---

*Last Updated: 2026*

