<?php
/**
 * AI Configuration - Centralized AI Provider Settings
 * 
 * This file provides backward compatibility for config/config.php
 * The actual AI service logic is in lib/AiApiService.php
 * 
 * Configuration is loaded from:
 * 1. data/ai_settings.json (saved via UI)
 * 2. Environment variables (.env)
 * 3. Default values
 * 
 * NOTE: This file is loaded by config.php BEFORE LOG_PATH is defined,
 * so we cannot use Logger here. Logging will happen after config.php loads.
 */

// Load the Logger (will work once config.php is fully loaded)
// Note: Logger needs LOG_PATH which is defined in config.php after this include
// So we load it but won't call logging functions until after config.php completes
require_once __DIR__ . '/../lib/Logger.php';

// Load the unified AI service (includes all provider configs and helper functions)
require_once __DIR__ . '/../lib/AiApiService.php';

// Define USE_MOCK_PATENT_API for backward compatibility
// This is now handled in AiApiService.php but we keep it here for compatibility
if (!defined('USE_MOCK_PATENT_API')) {
    define('USE_MOCK_PATENT_API', isset($savedSettings['use_mock_patent']) ? $savedSettings['use_mock_patent'] : true);
}

// Configuration loaded successfully
// Note: Logging will be done by the application after config.php fully loads

