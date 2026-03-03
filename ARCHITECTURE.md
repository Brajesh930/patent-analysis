# Patent Analysis MVP - Architecture & Workflow

## 📊 System Architecture

```
┌─────────────────────────────────────────────────────────────────┐
│                     Patent Analysis MVP                          │
├─────────────────────────────────────────────────────────────────┤
│                                                                   │
│  ┌──────────────────────  FRONTEND  ──────────────────────────┐  │
│  │                                                             │  │
│  │  public/login.php          public/index.php               │  │
│  │       │                          │                        │  │
│  │       └──────────────────────────┴───────────────────┐   │  │
│  │                                                      │   │  │
│  │         public/step1.php    step2.php    step3.php  │   │  │
│  │         (Seed+Elements)  (Screening)   (Export)    │   │  │
│  │                │              │           │         │   │  │
│  │                └──────────────┴───────────┘         │   │  │
│  │                    patent_detail.php               │   │  │
│  │                                                     │   │  │
│  │  HTML + CSS (styles.css) + Vanilla JS (app.js)    │   │  │
│  │                                                     │   │  │
│  └─────────────────────────────────────────────────────┘   │  │
│                           │                                  │  │
│                         HTTP                                │  │
│                           │                                  │  │
│  ┌──────────────────────  BACKEND  ──────────────────────┐  │  │
│  │                                                        │  │  │
│  │  config/config.php (Settings & Credentials)          │  │  │
│  │                                                        │  │  │
│  │  lib/Database.php          (SQLite Access)           │  │  │
│  │  lib/Logger.php            (Logging)                 │  │  │
│  │  lib/PatentProvider.php    (Patent API Adapter)      │  │  │
│  │  lib/AiProvider.php        (AI API Adapter)          │  │  │
│  │                                                        │  │  │
│  │  prompts/element_context.txt  (AI Prompt Template)  │  │  │
│  │  prompts/screening.txt        (AI Prompt Template)  │  │  │
│  │                                                        │  │  │
│  └─────────────────────────────────────────────────────┘   │  │
│                           │                                  │  │
│              ┌────────────┴────────────┐                    │  │
│              │                         │                    │  │
│  ┌──────────▼──────┐    ┌───────────▼─────────┐           │  │
│  │  data/app.db    │    │  logs/app.log       │           │  │
│  │  (SQLite DB)    │    │  (API & App logs)   │           │  │
│  │                 │    │                     │           │  │
│  │ ┌─────────────┐ │    └─────────────────────┘           │  │
│  │ │ analyses    │ │                                        │  │
│  │ │ key_elem... │ │                                        │  │
│  │ │ patents     │ │                                        │  │
│  │ │ screening.. │ │                                        │  │
│  │ │ exports     │ │                                        │  │
│  │ └─────────────┘ │                                        │  │
│  │                 │                                        │  │
│  └─────────────────┘                                        │  │
│                                                              │  │
└──────────────────────────────────────────────────────────────┘
```

---

## 🔄 3-Step Workflow Data Flow

### STEP 1: Seed + Key Elements + Context

```
┌────────────────────────────────────────────────────────────┐
│ USER: Create Analysis                                      │
├────────────────────────────────────────────────────────────┤
│                                                             │
│  Name: "AI Patents 2026"                                  │
│  Seed Type: Patent Number                                 │
│  Seed Value: "US10123456B2"                               │
│                                                             │
│  ┌──────────────────────────────────────────────────────┐ │
│  │ Frontend (step1.php)                                │ │
│  │  • Display seed info                               │ │
│  │  • Show "Fetch Seed Text" button                   │ │
│  └────────────────┬─────────────────────────────────────┘ │
│                   │                                         │
│                   ▼ (User clicks)                           │
│                                                             │
│  ┌──────────────────────────────────────────────────────┐ │
│  │ Backend: PatentProvider::fetchPatent()             │ │
│  │  • Call Patent API (or mock)                       │ │
│  │  • Return: claims + abstract                       │ │
│  └────────────────┬─────────────────────────────────────┘ │
│                   │                                         │
│                   ▼                                         │
│                                                             │
│  ┌──────────────────────────────────────────────────────┐ │
│  │ Database: Store seed_extracted_text                │ │
│  └────────────────┬─────────────────────────────────────┘ │
│                   │                                         │
│                   ▼ (User adds elements)                    │
│                                                             │
│  Key Elements:                                             │
│  1. machine learning                                      │
│  2. neural network                                        │
│  3. feature extraction                                    │
│                                                             │
│  ┌──────────────────────────────────────────────────────┐ │
│  │ Frontend: Store in key_elements table               │ │
│  └────────────────┬─────────────────────────────────────┘ │
│                   │                                         │
│                   ▼ (User clicks "Generate Context")        │
│                                                             │
│  For each key element:                                    │
│  ┌──────────────────────────────────────────────────────┐ │
│  │ Backend: AiProvider::buildElementContext()          │ │
│  │  • Input: seed_text + element_text                 │ │
│  │  • Call AI (or mock)                               │ │
│  │  • Return: JSON {definition, synonyms, markers...} │ │
│  └────────────────┬─────────────────────────────────────┘ │
│                   │                                         │
│                   ▼                                         │
│                                                             │
│  ┌──────────────────────────────────────────────────────┐ │
│  │ Database: Store context_json in key_elements       │ │
│  └────────────────┬─────────────────────────────────────┘ │
│                   │                                         │
│                   ▼                                         │
│                                                             │
│  ┌──────────────────────────────────────────────────────┐ │
│  │ Frontend: Display context cards                     │ │
│  │  • Show definition, synonyms, markers               │ │
│  │  • Allow edit & approve                             │ │
│  └──────────────────────────────────────────────────────┘ │
│                                                             │
└────────────────────────────────────────────────────────────┘

✅ END STEP 1: Ready for screening
```

### STEP 2: Bulk Patent Upload + Screening

```
┌────────────────────────────────────────────────────────────┐
│ USER: Add Patents & Screen                                 │
├────────────────────────────────────────────────────────────┤
│                                                             │
│  Patents to screen:                                       │
│  US10000001B2                                             │
│  US10000002B2                                             │
│  ... (up to 20-50)                                        │
│                                                             │
│  ┌──────────────────────────────────────────────────────┐ │
│  │ Frontend (step2.php)                                │ │
│  │  • Parse patent numbers                             │ │
│  │  • Store in patents table (with analysis_id)        │ │
│  └────────────────┬─────────────────────────────────────┘ │
│                   │                                         │
│                   ▼ (User clicks "Start Screening")         │
│                                                             │
│  For each patent (X/N loop):                              │
│                                                             │
│  1️⃣  ┌────────────────────────────────────────────────┐   │
│     │ PatentProvider::fetchPatent()                   │   │
│     │  • Fetch patent claims & abstract              │   │
│     │  • Store in patents.claims_text, abstract_text │   │
│     └────────────┬─────────────────────────────────────┘   │
│                  │                                          │
│  2️⃣  ▼ ┌────────────────────────────────────────────────┐   │
│     │ AiProvider::screenPatent()                      │   │
│     │  • Input: element_contexts + patent_text       │   │
│     │  • Call AI (or mock)                           │   │
│     │  • Return: JSON                                │   │
│     │    {                                            │   │
│     │      overall: {                                │   │
│     │        label: "relevant|borderline|not_rel",  │   │
│     │        score: 85,                             │   │
│     │        reasoning: "..."                       │   │
│     │      },                                        │   │
│     │      per_element: [                           │   │
│     │        {label, score, rationale, evidence}    │   │
│     │      ]                                         │   │
│     │    }                                            │   │
│     └────────────┬─────────────────────────────────────┘   │
│                  │                                          │
│  3️⃣  ▼ ┌────────────────────────────────────────────────┐   │
│     │ Database: Store in screening_results           │   │
│     │  • overall_label, overall_score, reasoning     │   │
│     │  • per_element_json (full detail)              │   │
│     └────────────┬─────────────────────────────────────┘   │
│                  │                                          │
│                  ▼ (Rate limit sleep)                       │
│              [AI_SLEEP_MS ms]                              │
│                  │                                          │
│                  ▼ → Next patent or done                    │
│                                                             │
│  ┌──────────────────────────────────────────────────────┐ │
│  │ Frontend: Display results table                     │ │
│  │  • Patent # | Label | Score | Reasoning | Details  │ │
│  │  • Clickable for detail view                        │ │
│  └──────────────────────────────────────────────────────┘ │
│                                                             │
└────────────────────────────────────────────────────────────┘

✅ END STEP 2: Screening complete, ready for export
```

### STEP 3: Ranking + Export

```
┌────────────────────────────────────────────────────────────┐
│ USER: View Results & Export                                │
├────────────────────────────────────────────────────────────┤
│                                                             │
│  ┌──────────────────────────────────────────────────────┐ │
│  │ Frontend (step3.php)                                │ │
│  │  • Query: SELECT * FROM screening_results           │ │
│  │           ORDER BY overall_score DESC               │ │
│  │  • Display ranked table                             │ │
│  └────────────────┬─────────────────────────────────────┘ │
│                   │                                         │
│                   ├──────┐  ┌─────────┐                   │
│                   │      │  │         │                   │
│                   ▼      │  │         ▼                   │
│                           │  │                             │
│  ┌────────────────────┐  │  │  ┌───────────────────────┐  │
│  │ CSV Export         │  │  │  │ HTML Report Export    │  │
│  │  • Generate CSV    │  │  │  │  • Generate HTML      │  │
│  │  • Columns:        │  │  │  │  • Styled report      │  │
│  │    patent #        │  │  │  │  • Per-patent detail  │  │
│  │    label           │  │  │  │  • Summary stats      │  │
│  │    score           │  │  │  │  • Evidence snippets  │  │
│  │    reasoning       │  │  │  │                       │  │
│  │    elements        │  │  │  └───────────┬───────────┘  │
│  │  • Download file   │  │  │              │                │
│  │                    │  │  │              │                │
│  └────────────────────┘  │  │  Download HTML report       │
│                          │  │  (AI_patents_report.html)   │
│              Download CSV│  │                               │
│              (results.csv)  │                               │
│                             │                               │
│                             ▼                               │
│                      File saved                             │
│                                                             │
└────────────────────────────────────────────────────────────┘

✅ END STEP 3: Export complete
```

---

## 📦 Database Schema Diagram

```
┌─────────────────────────────────┐
│ analyses                        │
├─────────────────────────────────┤
│ id (PK)                        │
│ name                           │
│ seed_type                      │
│ seed_value                     │
│ seed_extracted_text            │
│ created_at, updated_at         │
└──────────────┬──────────────────┘
               │ (1..N)
               │
        ┌──────┴───────┬──────────────┬────────────┐
        │              │              │            │
        ▼              ▼              ▼            ▼
┌─────────────────────────────────┐  │  │
│ key_elements                    │  │  │
├─────────────────────────────────┤  │  │
│ id (PK)                        │  │  │
│ analysis_id (FK)               │  │  │
│ element_order                  │  │  │
│ element_text                   │  │  │
│ context_json                   │  │  │
│ approved                       │  │  │
│ user_notes                     │  │  │
└─────────────────────────────────┘  │  │
                                      │  │
┌──────────────────────────────────┐  │  │
│ patents                          │◄─┘  │
├──────────────────────────────────┤     │
│ id (PK)                         │     │
│ analysis_id (FK)                │     │
│ patent_number                   │     │
│ claims_text                     │     │
│ abstract_text                   │     │
│ fetch_status                    │     │
│ fetch_error                     │     │
└──────────────┬───────────────────┘     │
               │ (1..N)                   │
               │                         │
               ▼                         │
┌──────────────────────────────────┐    │
│ screening_results                │    │
├──────────────────────────────────┤    │
│ id (PK)                         │    │
│ patent_id (FK)                  │    │
│ overall_label                   │    │
│ overall_score                   │    │
│ reasoning_short                 │    │
│ per_element_json                │    │
│ created_at                      │    │
└──────────────────────────────────┘    │
                                         │
        ┌────────────────────────────────┘
        │
        ▼
┌──────────────────────────────────┐
│ exports                          │
├──────────────────────────────────┤
│ id (PK)                         │
│ analysis_id (FK)                │
│ export_type (csv|html)          │
│ file_path                       │
│ created_at                      │
└──────────────────────────────────┘
```

---

## 🔌 API Integration Points

```
┌─────────────────────────────────────────────────────────────┐
│ Patent Analysis MVP - External Integration                 │
├─────────────────────────────────────────────────────────────┤
│                                                              │
│  Step 1: Fetch Seed Patent                                 │
│  ┌──────────────────────────────────────────────────────┐  │
│  │ PatentProvider::fetchPatent()                        │  │
│  │   Input: patentNumber, scope                        │  │
│  │   Output: {claims, abstract, meta}                  │  │
│  │                                                      │  │
│  │   Current: ✅ Mock implementation                    │  │
│  │   Real API: Edit realFetchPatent() method           │  │
│  │                                                      │  │
│  └──────────┬───────────────────────────────────────────┘  │
│             │                                               │
│             │ Set USE_MOCK_PATENT_API = false             │
│             ▼                                               │
│   ┌─────────────────────────┐                              │
│   │ Real Patent Data API    │                              │
│   │ (USPTO, Google Patents) │                              │
│   └─────────────────────────┘                              │
│                                                              │
│  ────────────────────────────────────────────────────────  │
│                                                              │
│  Step 1-3: AI Calls (2 types)                              │
│  ┌──────────────────────────────────────────────────────┐  │
│  │ AiProvider::buildElementContext()                    │  │
│  │   Input: seedText, elementText                      │  │
│  │   Output: {definition, synonyms, markers...}       │  │
│  │                                                      │  │
│  │   Current: ✅ Mock implementation                    │  │
│  │   Real API: Edit realBuildElementContext() method  │  │
│  │                                                      │  │
│  └──────────┬───────────────────────────────────────────┘  │
│             │                                               │
│  ┌──────────────────────────────────────────────────────┐  │
│  │ AiProvider::screenPatent()                           │  │
│  │   Input: contextsJson, patentText                   │  │
│  │   Output: {overall, per_element, notes}            │  │
│  │                                                      │  │
│  │   Current: ✅ Mock implementation                    │  │
│  │   Real API: Edit realScreenPatent() method         │  │
│  │                                                      │  │
│  └──────────┬───────────────────────────────────────────┘  │
│             │                                               │
│             │ Set USE_MOCK_AI_API = false                 │
│             ▼                                               │
│   ┌─────────────────────────┐                              │
│   │ Real AI API             │                              │
│   │ (OpenAI, Claude, etc)   │                              │
│   └─────────────────────────┘                              │
│                                                              │
└─────────────────────────────────────────────────────────────┘
```

---

## ⚙️ Configuration Flow

```
app starts
  │
  ├─→ config/config.php
  │   │
  │   ├─→ Read API Keys
  │   ├─→ Read Mock Flags
  │   ├─→ Read Limits
  │   └─→ Read Settings
  │
  ├─→ lib/Database.php
  │   └─→ Connect to data/app.db
  │
  ├─→ lib/Logger.php
  │   └─→ Prepare logs/app.log
  │
  ├─→ public/step*.php
  │   │
  │   ├─→ Use mock or real PatentProvider
  │   ├─→ Use mock or real AiProvider
  │   └─→ Store results in DB
  │
  └─→ Ready to serve requests
```

---

## 📊 Request/Response Flow Example

```
USER INTERACTION: User creates analysis and adds key element

Client (Browser)              HTTP                Server (PHP)
─────────────────────────────────────────────────────────────────

1. POST /create_analysis.php
   {name, seed_type, seed_value}
        ──────────────────────→  Database::createAnalysis()
                                └─→ INSERT into analyses
                                └─→ return analysis_id
        ←──────────────────────  Redirect to step1.php?id=X

2. GET /step1.php?analysis_id=1
        ──────────────────────→  Database::getAnalysis(1)
                                Database::getKeyElements(1)
                                └─→ return HTML page
        ←──────────────────────  Display form

3. POST /step1.php?analysis_id=1
   {action: add_elements, elements_text}
        ──────────────────────→  Parse elements
                                For each:
                                  Database::addKeyElement()
                                  └─→ INSERT into key_elements
        ←──────────────────────  Return to form with elements

4. User clicks "Generate Context"
   POST /step1.php?analysis_id=1
   {action: generate_context}
        ──────────────────────→  Database::getKeyElements(1)
                                For each element:
                                  AiProvider::buildElementContext()
                                  │
                                  ├─→ If mock:
                                  │   └─→ return deterministic JSON
                                  │
                                  └─→ If real:
                                      └─→ Call AI API
                                          curl POST to AI_API_BASE_URL
                                          parse JSON response
                                          Logger::logApiResponse()
                                  
                                  Database::updateKeyElementContext()
                                  └─→ UPDATE key_elements SET context_json=...
                                  
                                  usleep(AI_SLEEP_MS * 1000)
        ←──────────────────────  Display updated page with contexts
```

---

**Diagrams created for documentation purposes. Actual implementation follows this architecture.**
