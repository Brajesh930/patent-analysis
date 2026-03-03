<?php
/**
 * Patent Analysis MVP - Configuration
 */

// ============================================================================
// DATABASE
// ============================================================================
define('DB_PATH', __DIR__ . '/../data/app.db');

// ============================================================================
// AUTHENTICATION (SIMPLE SINGLE-USER)
// ============================================================================
define('ADMIN_USERNAME', 'admin');
define('ADMIN_PASSWORD', 'admin'); // TODO: use hashed password in production
define('SESSION_TIMEOUT', 3600); // 1 hour

// ============================================================================
// PATENT DATA API (mock by default, replace with real credentials)
// ============================================================================
define('PATENT_API_BASE_URL', 'https://api.example.com/patents');
define('PATENT_API_KEY', 'YOUR_PATENT_API_KEY_HERE');
define('USE_MOCK_PATENT_API', true); // Set to false when using real API

// ============================================================================
// AI API (mock by default, replace with real credentials)
// ============================================================================
define('AI_API_BASE_URL', 'https://api.example.com/ai');
define('AI_API_KEY', 'YOUR_AI_API_KEY_HERE');
define('AI_MODEL', 'gpt-4');
define('USE_MOCK_AI_API', true); // Set to false when using real API

// ============================================================================
// PROCESSING CONFIG
// ============================================================================
define('AI_SLEEP_MS', 500); // milliseconds between AI API calls (rate limiting)
define('BATCH_SIZE', 5);     // number of patents per batch request
define('MAX_PATENTS_MVP', 100); // hard limit for MVP

// ============================================================================
// LOGGING
// ============================================================================
define('LOG_PATH', __DIR__ . '/../logs/app.log');
define('LOG_API_REQUESTS', true);

// ============================================================================
// PATHS
// ============================================================================
define('BASE_PATH', __DIR__ . '/..');
define('PUBLIC_PATH', BASE_PATH . '/public');
define('LIB_PATH', BASE_PATH . '/lib');
define('PROMPTS_PATH', BASE_PATH . '/prompts');

// ============================================================================
// START SESSION
// ============================================================================
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check session timeout
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > SESSION_TIMEOUT) {
    session_destroy();
    // Redirect to login if accessed
    if (php_sapi_name() !== 'cli') {
        header('Location: login.php');
        exit;
    }
}
if (php_sapi_name() !== 'cli') {
    $_SESSION['last_activity'] = time();
}
