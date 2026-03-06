<?php
/**
 * Environment Variable Loader for AI Configuration
 * 
 * Use this file to load environment variables from .env file.
 * 
 * OPTION 1 (Recommended): Add this to your entry point (public/index.php at top):
 *    require_once __DIR__ . '/lib/EnvLoader.php';
 * 
 * OPTION 2: Use with Apache .htaccess (see public/.htaccess)
 * 
 * OPTION 3: Manually set environment variables:
 *    export AI_PROVIDER=openai
 *    export OPENAI_API_KEY=sk-...
 */

class EnvLoader {
    
    /**
     * Load environment variables from .env file
     * 
     * @param string $envFilePath Path to .env file (relative or absolute)
     * @return bool True if loaded successfully
     */
    public static function load($envFilePath = null) {
        if ($envFilePath === null) {
            // Try to find .env in parent directory
            $envFilePath = dirname(__DIR__) . '/.env';
        }
        
        if (!file_exists($envFilePath)) {
            error_log("EnvLoader: .env file not found at $envFilePath");
            return false;
        }
        
        // Parse .env file
        $lines = file($envFilePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        
        if ($lines === false) {
            error_log("EnvLoader: Failed to read .env file");
            return false;
        }
        
        $loaded = 0;
        foreach ($lines as $line) {
            // Skip comments
            if (strpos(trim($line), '#') === 0) {
                continue;
            }
            
            // Parse KEY=VALUE
            if (strpos($line, '=') !== false) {
                list($key, $value) = explode('=', $line, 2);
                $key = trim($key);
                $value = trim($value);
                
                // Remove quotes if present
                if ((strpos($value, '"') === 0 && strrpos($value, '"') === strlen($value) - 1) ||
                    (strpos($value, "'") === 0 && strrpos($value, "'") === strlen($value) - 1)) {
                    $value = substr($value, 1, -1);
                }
                
                // Set only if not already set
                if (!getenv($key)) {
                    putenv("$key=$value");
                    $loaded++;
                }
            }
        }
        
        error_log("EnvLoader: Loaded $loaded environment variables from .env");
        return true;
    }
    
    /**
     * Get environment variable with optional default
     * 
     * @param string $key Environment variable name
     * @param mixed $default Default value if not set
     * @return mixed Value or default
     */
    public static function get($key, $default = null) {
        $value = getenv($key);
        return $value !== false ? $value : $default;
    }
    
    /**
     * Check if environment variable is set
     * 
     * @param string $key Environment variable name
     * @return bool True if set
     */
    public static function has($key) {
        return getenv($key) !== false;
    }
    
    /**
     * Validate required environment variables for AI configuration
     * 
     * @return array Array of missing variables, empty if all present
     */
    public static function validateAiConfig() {
        $provider = getenv('AI_PROVIDER') ?: 'openai';
        $mockMode = getenv('USE_MOCK_AI_API') === 'true' || getenv('USE_MOCK_AI_API') === '1';
        
        $required = [];
        $optional = [];
        
        // In mock mode, only provider is needed
        if ($mockMode) {
            return [];
        }
        
        // Real mode - check provider-specific requirements
        switch (strtolower($provider)) {
            case 'openai':
                $required = ['OPENAI_API_KEY', 'OPENAI_MODEL'];
                break;
            
            case 'google':
                $required = ['GOOGLE_API_KEY', 'GOOGLE_MODEL'];
                break;
            
            case 'deepseek':
                $required = ['DEEPSEEK_API_KEY', 'DEEPSEEK_MODEL'];
                break;
            
            case 'azure':
                $required = ['AZURE_OPENAI_URL', 'AZURE_OPENAI_API_KEY', 'AZURE_OPENAI_MODEL'];
                break;
            
            case 'anthropic':
                $required = ['ANTHROPIC_API_KEY', 'ANTHROPIC_MODEL'];
                break;
            
            case 'ollama':
                $required = ['OLLAMA_URL', 'OLLAMA_MODEL'];
                break;
            
            case 'custom':
                $required = ['CUSTOM_AI_URL', 'CUSTOM_AI_KEY', 'CUSTOM_AI_MODEL'];
                break;
            
            default:
                return ["Unknown provider: $provider"];
        }
        
        $missing = [];
        foreach ($required as $var) {
            if (!self::has($var)) {
                $missing[] = $var;
            }
        }
        
        return $missing;
    }
    
    /**
     * Print environment status for debugging
     */
    public static function printStatus() {
        echo "=== Environment Status ===\n";
        echo "AI Provider: " . (getenv('AI_PROVIDER') ?: 'openai') . "\n";
        echo "Mock Mode: " . (getenv('USE_MOCK_AI_API') === 'true' ? 'YES' : 'NO') . "\n";
        
        $missing = self::validateAiConfig();
        if (empty($missing)) {
            echo "Status: ✓ All required variables set\n";
        } else {
            echo "Status: ✗ Missing variables:\n";
            foreach ($missing as $var) {
                echo "  - $var\n";
            }
        }
    }
}

// Auto-load if this file is included
if (php_sapi_name() !== 'cli') {
    EnvLoader::load();
}

?>
