# Patent Analysis MVP

A lightweight, single-user patent analysis system demonstrating a 3-step workflow end-to-end.

**Tech Stack:**
- Backend: PHP 8.x
- Frontend: HTML + CSS + vanilla JavaScript
- Storage: SQLite
- No external dependencies or frameworks

---

## Quick Start

### Prerequisites
- PHP 8.0+ (with SQLite PDO extension)
- Command-line access

### Setup (5 minutes)

1. **Initialize the database:**
   ```bash
   php scripts/init_db.php
   ```

2. **Start the PHP development server:**
   ```bash
   php -S localhost:8000 -t public
   ```

3. **Open in browser:**
   ```
   http://localhost:8000
   ```

4. **Login with default credentials:**
   - Username: `admin`
   - Password: `admin`

---

## Features

### ✅ STEP 1: Seed + Key Elements + Context Generation

1. **Seed Input**: Provide either a patent number or free text
2. **Fetch**: If patent number, automatically fetches patent text (claims + abstract)
3. **Key Elements**: Define 2-20 key technical elements to search for
4. **Context Generation**: AI generates structured context for each element
   - Definitions, synonyms, explicit/implicit markers, boundaries, match cues
5. **Approve & Edit**: Review and approve generated contexts; add notes

### ✅ STEP 2: Bulk Patent Upload + Relevance Screening

1. **Upload Patents**: Paste patent numbers (one per line) or upload CSV
2. **Select Scope**: Choose between "claims only" or "claims+abstract"
3. **Batch Screening**: AI screens each patent against key element contexts
   - Overall relevance label: `relevant`, `borderline`, `not_relevant`
   - Overall score: 0-100
   - Per-element breakdown: explicit/implicit/partial/none with evidence
4. **Progress Display**: Shows "X of Y patents" during processing
5. **Results Table**: View all screened patents with status and scores

### ✅ STEP 3: Ranking + Export

1. **Ranked Results**: Automatic ranking by score (descending)
2. **Export CSV**: Download results table with columns:
   - Patent number, label, score, reasoning, per-element summary
3. **Export HTML Report**: Pretty-printed report with:
   - Summary statistics
   - Ranked table
   - Detailed per-patent breakdowns
   - Evidence snippets

### 🔒 Authentication
- Simple single-user login (admin/admin default)
- Session-based; 1-hour timeout

---

## Project Structure

```
patent-analysis-mvp/
├── public/
│   ├── index.php                 # Dashboard: list analyses
│   ├── login.php                 # Login page
│   ├── logout.php                # Logout handler
│   ├── create_analysis.php        # Create new analysis
│   ├── step1.php                 # Seed + elements + context
│   ├── step2.php                 # Upload + screening
│   ├── step3.php                 # Ranking + export
│   ├── patent_detail.php         # Patent detail view
│   └── assets/
│       ├── styles.css            # Main stylesheet
│       └── app.js                # Vanilla JavaScript
├── lib/
│   ├── Database.php              # SQLite access layer
│   ├── Logger.php                # Logging utility
│   ├── PatentProvider.php        # Patent API adapter (mock)
│   └── AiProvider.php            # AI API adapter (mock)
├── config/
│   └── config.php                # Configuration & constants
├── data/
│   └── app.db                    # SQLite database (auto-created)
├── logs/
│   └── app.log                   # Application log
├── prompts/
│   ├── element_context.txt       # Prompt template for context generation
│   └── screening.txt             # Prompt template for patent screening
├── scripts/
│   └── init_db.php               # Database initialization
└── README.md                     # This file
```

---

## Database Schema

### `analyses`
- `id`: Unique identifier
- `name`: Analysis name
- `seed_type`: `patent_number` or `free_text`
- `seed_value`: Patent number or text input
- `seed_extracted_text`: Fetched/extracted text
- `created_at`: Timestamp

### `key_elements`
- `id`: Unique identifier
- `analysis_id`: Foreign key to analyses
- `element_order`: Display order
- `element_text`: The element text
- `approved`: Boolean (0/1)
- `context_json`: Structured context (JSON string)
- `user_notes`: Optional user annotations

### `patents`
- `id`: Unique identifier
- `analysis_id`: Foreign key to analyses
- `patent_number`: Patent identifier
- `claims_text`: Extracted claims
- `abstract_text`: Extracted abstract
- `fetch_status`: `ok`, `failed`, or `pending`
- `fetch_error`: Error message if failed

### `screening_results`
- `id`: Unique identifier
- `patent_id`: Foreign key to patents
- `overall_label`: `relevant`, `borderline`, or `not_relevant`
- `overall_score`: 0-100 numeric score
- `reasoning_short`: 1-3 sentence summary
- `per_element_json`: Array of per-element results (JSON string)
- `created_at`: Timestamp

### `exports`
- `id`: Unique identifier
- `analysis_id`: Foreign key to analyses
- `export_type`: `csv` or `html`
- `file_path`: Path where export was saved
- `created_at`: Timestamp

---

## Configuration

Edit `config/config.php` to customize:

```php
// Authentication
define('ADMIN_USERNAME', 'admin');
define('ADMIN_PASSWORD', 'admin');
define('SESSION_TIMEOUT', 3600); // 1 hour

// Patent Data API
define('PATENT_API_BASE_URL', 'https://api.example.com/patents');
define('PATENT_API_KEY', 'YOUR_KEY_HERE');
define('USE_MOCK_PATENT_API', true);

// AI API
define('AI_API_BASE_URL', 'https://api.example.com/ai');
define('AI_API_KEY', 'YOUR_KEY_HERE');
define('AI_MODEL', 'gpt-4');
define('USE_MOCK_AI_API', true);

// Processing
define('AI_SLEEP_MS', 500);      // Rate limiting between AI calls
define('BATCH_SIZE', 5);         // Patents per batch
define('MAX_PATENTS_MVP', 100);  // Hard limit for MVP

// Logging
define('LOG_API_REQUESTS', true);
```

---

## Adding Real APIs

### Step 1: Implement Real Patent Provider
Edit `lib/PatentProvider.php` - replace mock with real API calls:

```php
private static function realFetchPatent($patentNumber, $scope) {
    // Implement your patent data API here
    // Expected return: ['claims' => '...', 'abstract' => '...', 'meta' => [...]]
}
```

### Step 2: Implement Real AI Provider
Edit `lib/AiProvider.php` - replace mock with real AI API calls:

```php
private static function realBuildElementContext($seedText, $elementText) {
    // Call your AI endpoint
    // Return parsed JSON context
}

private static function realScreenPatent($contextsJson, $patentText) {
    // Call your AI endpoint with prompt templates
    // Return parsed screening result
}
```

### Step 3: Update Configuration
In `config/config.php`:
```php
define('USE_MOCK_PATENT_API', false);  // Disable mock
define('USE_MOCK_AI_API', false);      // Disable mock
define('PATENT_API_KEY', 'actual_key');
define('AI_API_KEY', 'actual_key');
```

---

## Mock Data Example

The MVP includes deterministic mock data for offline testing:

- **Mock Patents**: Returns sample claims/abstract for any patent number
- **Mock Context Generation**: Returns structured context with definitions, markers, boundaries
- **Mock Screening**: Returns consistent relevance scores based on patent text length

All mocks log to `logs/app.log` for debugging.

---

## API Response Formats

### Element Context JSON
```json
{
  "definition": "Technical definition of the element",
  "synonyms": ["term1", "term2"],
  "explicit_markers": ["exact phrase"],
  "implicit_markers": ["concept"],
  "boundaries": ["what it includes"],
  "match_cues": ["search term"]
}
```

### Screening Result JSON
```json
{
  "overall": {
    "label": "relevant|borderline|not_relevant",
    "score": 85,
    "reasoning_short": "Patent exhibits strong relevance..."
  },
  "per_element": [
    {
      "element_index": 0,
      "label": "explicit|implicit|partial|none",
      "score": 5,
      "rationale": "Clearly stated in claims",
      "evidence": [
        {
          "location": "claim 1",
          "snippet": "quote from patent"
        }
      ]
    }
  ],
  "notes": {
    "uncertainties": [],
    "missing_sections": []
  }
}
```

---

## Rate Limiting

The MVP implements basic rate limiting to avoid API throttling:

```php
usleep(AI_SLEEP_MS * 1000);  // Sleep between API calls
```

Adjust `AI_SLEEP_MS` in config (milliseconds):
- `100-200`: Aggressive (for fast APIs)
- `500`: Default (balanced)
- `1000+`: Conservative (for strict rate limits)

---

## Logging

All API requests/responses are logged to `logs/app.log`:

```
[2026-03-03 12:34:56] [INFO] Analysis created: ID=1, Name=AI Patents
[2026-03-03 12:35:01] [API_REQ] Service: PatentAPI | Method: GET | URL: mock://patent/US10123456B2
[2026-03-03 12:35:02] [API_RESP] Service: PatentAPI | Status: 200 | Response: {"claims": "...", "abstract": "..."}
```

Disable in config with `define('LOG_API_REQUESTS', false)`.

---

## Performance Notes

### MVP Limits (Intentional)
- Max 100 patents per analysis (adjustable in config)
- Synchronous processing (no job queue)
- Progress shown via "X of Y" display in UI

### Scale to Production
To handle 1000+ patents:
1. Implement **background job queue** (e.g., Redis + Resque)
2. Add **caching layer** (e.g., Memcached)
3. Migrate to **PostgreSQL** (better for large datasets)
4. Add **rate limit handling** with exponential backoff
5. Implement **result pagination** in UI

---

## Troubleshooting

### Database Not Found
```bash
php scripts/init_db.php
```

### Permission Errors
```bash
chmod 755 data logs
chmod 644 data/app.db logs/app.log
```

### Session Issues
- Clear browser cookies
- Check `SESSION_TIMEOUT` in config
- Verify PHP session handler configured

### API Errors
1. Check `logs/app.log` for details
2. Verify API keys in `config/config.php`
3. Test endpoint connectivity
4. Confirm JSON response format matches expected schema

### CURL Errors
Ensure PHP is compiled with CURL extension:
```bash
php -i | grep cURL
```

---

## Development Tips

### Add a New Analysis Type
1. Edit `key_elements` table schema (if needed)
2. Modify `lib/AiProvider.php` prompt templates
3. Add new form field in `public/step1.php`

### Modify Export Format
Edit export section in `public/step3.php`:
```php
if ($exportType === 'csv') {
    // Add/modify columns here
}
```

### Add Email Notifications
Create `lib/Mailer.php` and integrate into step completion handlers.

---

## License

Open source. Use freely for research and educational purposes.

---

## Support

- **Logs**: Check `logs/app.log` for detailed error traces
- **Config**: All settings in `config/config.php`
- **Schema**: Database structure in `scripts/init_db.php`
- **Prompts**: AI templates in `prompts/` directory

---

**Version**: 1.0 MVP  
**Last Updated**: March 3, 2026  
**Status**: Production-ready for small-scale (20-50 patent) analyses
