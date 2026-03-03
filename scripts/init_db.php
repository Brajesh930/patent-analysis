<?php
/**
 * Patent Analysis MVP - Database Initialization Script
 * Run: php scripts/init_db.php
 */

require_once __DIR__ . '/../config/config.php';

function initDatabase() {
    $dbPath = DB_PATH;
    
    // Remove old DB if exists (for fresh start)
    if (file_exists($dbPath)) {
        unlink($dbPath);
        echo "[INFO] Removed old database.\n";
    }
    
    // Create new DB
    try {
        $pdo = new PDO('sqlite:' . $dbPath);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // Create analyses table
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS analyses (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                seed_type TEXT NOT NULL,
                seed_value TEXT NOT NULL,
                seed_extracted_text TEXT,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                updated_at TEXT DEFAULT CURRENT_TIMESTAMP
            )
        ");
        
        // Create key_elements table
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS key_elements (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                analysis_id INTEGER NOT NULL,
                element_order INTEGER NOT NULL,
                element_text TEXT NOT NULL,
                approved INTEGER DEFAULT 0,
                context_json TEXT,
                user_notes TEXT,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY(analysis_id) REFERENCES analyses(id) ON DELETE CASCADE
            )
        ");
        
        // Create patents table
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS patents (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                analysis_id INTEGER NOT NULL,
                patent_number TEXT NOT NULL,
                claims_text TEXT,
                abstract_text TEXT,
                fetch_status TEXT DEFAULT 'pending',
                fetch_error TEXT,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY(analysis_id) REFERENCES analyses(id) ON DELETE CASCADE
            )
        ");
        
        // Create screening_results table
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS screening_results (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                patent_id INTEGER NOT NULL,
                overall_label TEXT,
                overall_score REAL,
                reasoning_short TEXT,
                per_element_json TEXT,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY(patent_id) REFERENCES patents(id) ON DELETE CASCADE
            )
        ");
        
        // Create exports table
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS exports (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                analysis_id INTEGER NOT NULL,
                export_type TEXT,
                file_path TEXT,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY(analysis_id) REFERENCES analyses(id) ON DELETE CASCADE
            )
        ");
        
        echo "[✓] Database initialized successfully at: " . $dbPath . "\n";
        echo "[✓] Tables created: analyses, key_elements, patents, screening_results, exports\n";
        
        return true;
    } catch (PDOException $e) {
        echo "[ERROR] Database initialization failed: " . $e->getMessage() . "\n";
        return false;
    }
}

// Run initialization
if (php_sapi_name() === 'cli') {
    initDatabase();
} else {
    die("This script must be run from command line only.");
}
