<?php
require_once __DIR__ . '/config/config.php';

echo "=== Listing Available Google Gemini Models ===\n\n";

// Get API key from settings
$settingsFile = __DIR__ . '/data/ai_settings.json';
$settings = json_decode(file_get_contents($settingsFile), true) ?? [];
$apiKey = $settings['api_key'] ?? '';

if (!$apiKey) {
    echo "✗ No API key found!\n";
    exit(1);
}

$listUrl = "https://generativelanguage.googleapis.com/v1/models?key=" . urlencode($apiKey);

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $listUrl);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);

echo "HTTP Code: $httpCode\n";
echo "cURL Error: " . ($error ?: 'None') . "\n\n";

if ($response) {
    $data = json_decode($response, true);
    echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n\n";
    
    if (isset($data['models'])) {
        echo "Available Models:\n";
        foreach ($data['models'] as $model) {
            $name = $model['name'] ?? 'Unknown';
            // Extract just the model name part
            $modelName = str_replace('models/', '', $name);
            $methods = $model['supportedGenerationMethods'] ?? [];
            $supported = in_array('generateContent', $methods) ? '✓' : '✗';
            echo "  [$supported] $modelName\n";
            if ($supported === '✗' && !empty($methods)) {
                echo "       Supported methods: " . implode(', ', $methods) . "\n";
            }
        }
    }
} else {
    echo "No response from API!\n";
}
?>
