<?php
/**
 * Database patch - adds missing columns to existing database
 */

require_once __DIR__ . '/../config/config.php';

function patchDatabase() {
    try {
        $pdo = new PDO('sqlite:' . DB_PATH);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // Check if context_error column exists
        $result = $pdo->query("PRAGMA table_info(key_elements)");
        $columns = $result->fetchAll(PDO::FETCH_ASSOC);
        $columnNames = array_column($columns, 'name');
        
        if (!in_array('context_error', $columnNames)) {
            echo "[INFO] Adding context_error column to key_elements table...\n";
            $pdo->exec("ALTER TABLE key_elements ADD COLUMN context_error TEXT");
            echo "[✓] context_error column added successfully\n";
        } else {
            echo "[INFO] context_error column already exists\n";
        }
        
        echo "[✓] Database patch completed successfully\n";
        return true;
    } catch (PDOException $e) {
        echo "[ERROR] Database patch failed: " . $e->getMessage() . "\n";
        return false;
    }
}

if (php_sapi_name() === 'cli') {
    patchDatabase();
} else {
    die("This script must be run from command line only.");
}
?>
