<?php
/**
 * Patent Analysis MVP - Logging Utility
 */

class Logger {
    public static function log($message, $level = 'INFO') {
        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[$timestamp] [$level] $message\n";
        file_put_contents(LOG_PATH, $logMessage, FILE_APPEND);
    }
    
    public static function info($message) {
        self::log($message, 'INFO');
    }
    
    public static function error($message) {
        self::log($message, 'ERROR');
    }
    
    public static function debug($message) {
        self::log($message, 'DEBUG');
    }
    
    public static function logApiRequest($service, $method, $url, $params) {
        if (!LOG_API_REQUESTS) return;
        
        $message = "API REQUEST | Service: $service | Method: $method | URL: $url | Params: " 
            . json_encode($params, JSON_UNESCAPED_SLASHES);
        self::log($message, 'API_REQ');
    }
    
    public static function logApiResponse($service, $status, $response) {
        if (!LOG_API_REQUESTS) return;
        
        // Truncate long responses
        $respStr = is_array($response) ? json_encode($response) : (string)$response;
        $respStr = strlen($respStr) > 500 ? substr($respStr, 0, 500) . '...' : $respStr;
        
        $message = "API RESPONSE | Service: $service | Status: $status | Response: $respStr";
        self::log($message, 'API_RESP');
    }
}
