<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../lib/Database.php';
require_once __DIR__ . '/../lib/Logger.php';
require_once __DIR__ . '/../lib/PatentProvider.php';

// Check authentication
if (!isset($_SESSION['authenticated'])) {
    header('Location: login.php');
    exit;
}

$diagnostics = [];
$testPatentNumber = $_POST['test_patent'] ?? 'US10123456B2';
$testResult = null;

// Run diagnostics when form submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'run_diagnostics') {
    // Check 1: Network connectivity
    $diagnostics['network'] = [
        'name' => 'Network Connectivity',
        'status' => 'testing',
        'details' => 'Checking connection to Unified Patents API...'
    ];
    
    if (@fsockopen('api.unifiedpatents.com', 443, $errno, $errstr, 5)) {
        $diagnostics['network']['status'] = 'pass';
        $diagnostics['network']['details'] = 'Successfully connected to api.unifiedpatents.com:443';
    } else {
        $diagnostics['network']['status'] = 'fail';
        $diagnostics['network']['details'] = "Cannot connect to API: $errstr (Error $errno)";
    }
    
    // Check 2: cURL extension
    $diagnostics['curl'] = [
        'name' => 'cURL Extension',
        'status' => function_exists('curl_init') ? 'pass' : 'fail',
        'details' => function_exists('curl_init') ? 'cURL extension is enabled' : 'cURL extension is NOT enabled'
    ];
    
    // Check 3: SSL/TLS
    $diagnostics['ssl'] = [
        'name' => 'SSL/TLS Support',
        'status' => 'testing',
        'details' => 'Testing SSL connectivity...'
    ];
    
    if (function_exists('curl_init')) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://api.unifiedpatents.com/');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_NOBODY, true);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        
        if ($curlError) {
            $diagnostics['ssl']['status'] = 'fail';
            $diagnostics['ssl']['details'] = "SSL Error: $curlError";
        } else if ($httpCode > 0) {
            $diagnostics['ssl']['status'] = 'pass';
            $diagnostics['ssl']['details'] = "SSL connection successful (HTTP $httpCode)";
        } else {
            $diagnostics['ssl']['status'] = 'fail';
            $diagnostics['ssl']['details'] = "No response from API";
        }
    } else {
        $diagnostics['ssl']['status'] = 'skip';
        $diagnostics['ssl']['details'] = 'cURL not available, skipping test';
    }
    
    // Check 4: Current Mock Mode Settings
    $settingsFile = __DIR__ . '/../data/ai_settings.json';
    $settings = [];
    if (file_exists($settingsFile)) {
        $settings = json_decode(file_get_contents($settingsFile), true) ?: [];
    }
    
    $diagnostics['settings'] = [
        'name' => 'Current Settings',
        'status' => 'info',
        'details' => 'Use Mock AI: ' . ($settings['use_mock'] ? 'YES (Mock)' : 'NO (Real)') . 
                    '<br>Use Mock Patent API: ' . ($settings['use_mock_patent'] ? 'YES (Mock)' : 'NO (Real)') .
                    '<br>AI Provider: ' . ($settings['provider'] ?? 'google') .
                    '<br>AI Model: ' . ($settings['model'] ?? 'gemini-2.0-flash')
    ];
    
    // Check 5: Test Patent Fetch
    $diagnostics['patent_fetch'] = [
        'name' => 'Patent API Test (Patent: ' . htmlspecialchars($testPatentNumber) . ')',
        'status' => 'testing',
        'details' => 'Testing real patent data fetch...'
    ];
    
    $testResult = PatentProvider::fetchPatent($testPatentNumber, 'claims+abstract');
    
    if ($testResult['status'] === 'ok') {
        $diagnostics['patent_fetch']['status'] = 'pass';
        $diagnostics['patent_fetch']['details'] = "Successfully fetched patent data!<br>";
        $diagnostics['patent_fetch']['details'] .= "Title: " . (isset($testResult['meta']['title']) ? htmlspecialchars($testResult['meta']['title']) : 'N/A') . "<br>";
        $diagnostics['patent_fetch']['details'] .= "Claims Length: " . strlen($testResult['claims'] ?? '') . " chars<br>";
        $diagnostics['patent_fetch']['details'] .= "Abstract Length: " . strlen($testResult['abstract'] ?? '') . " chars";
    } else {
        $diagnostics['patent_fetch']['status'] = 'fail';
        $diagnostics['patent_fetch']['details'] = 'Patent Fetch Failed<br>';
        $diagnostics['patent_fetch']['details'] .= "Error: " . htmlspecialchars($testResult['error'] ?? 'Unknown error');
    }
    
    // Check 6: File/Directory Permissions
    $diagnostics['permissions'] = [
        'name' => 'File/Directory Permissions',
        'status' => 'info',
        'details' => ''
    ];
    
    $logsDir = __DIR__ . '/../logs/';
    $dataDir = __DIR__ . '/../data/';
    
    $details = [];
    if (is_writable($logsDir)) {
        $details[] = '✓ logs/ directory is writable';
    } else {
        $details[] = '✗ logs/ directory is NOT writable';
    }
    
    if (is_writable($dataDir)) {
        $details[] = '✓ data/ directory is writable';
    } else {
        $details[] = '✗ data/ directory is NOT writable';
    }
    
    $diagnostics['permissions']['details'] = implode('<br>', $details);
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>API Diagnostics - Patent Analysis MVP</title>
    <link rel="stylesheet" href="assets/styles.css">
    <style>
        .diagnostics-container {
            max-width: 900px;
            margin: 20px auto;
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        
        .diagnostic-item {
            margin-bottom: 20px;
            border: 1px solid #ddd;
            border-radius: 6px;
            overflow: hidden;
        }
        
        .diagnostic-header {
            padding: 15px;
            font-weight: 600;
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: #f5f5f5;
            border-bottom: 1px solid #ddd;
        }
        
        .diagnostic-status {
            padding: 8px 12px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .status-pass {
            background: #d4edda;
            color: #155724;
        }
        
        .status-fail {
            background: #f8d7da;
            color: #721c24;
        }
        
        .status-info {
            background: #d1ecf1;
            color: #0c5460;
        }
        
        .status-testing {
            background: #fff3cd;
            color: #856404;
        }
        
        .status-skip {
            background: #e2e3e5;
            color: #383d41;
        }
        
        .diagnostic-details {
            padding: 15px;
            background: white;
            font-size: 14px;
            line-height: 1.6;
        }
        
        .test-form {
            margin: 20px 0;
            padding: 15px;
            background: #f9f9f9;
            border-radius: 6px;
            border: 1px solid #ddd;
        }
        
        .test-form input {
            padding: 8px;
            border: 1px solid #ccc;
            border-radius: 4px;
            width: 300px;
            margin-right: 10px;
        }
        
        .test-form button {
            padding: 8px 20px;
        }
        
        .button-group {
            margin-top: 20px;
            display: flex;
            gap: 10px;
        }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <h1>🔍 Patent Analysis MVP</h1>
            <div class="header-actions">
                <span>Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?></span>
                <a href="logout.php" class="btn btn-logout">Logout</a>
            </div>
        </header>

        <main>
            <div class="diagnostics-container">
                <h2>🔧 API Diagnostics</h2>
                <p style="color: #666; margin-bottom: 20px;">Test your Patent API configuration and connectivity. This helps diagnose issues with real patent data fetching.</p>
                
                <div class="test-form">
                    <form method="POST">
                        <input type="hidden" name="action" value="run_diagnostics">
                        <label for="test_patent">Test Patent Number:</label>
                        <input type="text" id="test_patent" name="test_patent" value="<?php echo htmlspecialchars($testPatentNumber); ?>" placeholder="e.g., US10123456B2">
                        <button type="submit" class="btn btn-primary">Run Diagnostics</button>
                    </form>
                </div>

                <?php if (!empty($diagnostics)): ?>
                    <h3 style="margin-top: 30px; margin-bottom: 20px;">📋 Diagnostic Results</h3>
                    
                    <?php foreach ($diagnostics as $key => $diag): ?>
                        <div class="diagnostic-item">
                            <div class="diagnostic-header">
                                <span><?php echo htmlspecialchars($diag['name']); ?></span>
                                <span class="diagnostic-status status-<?php echo htmlspecialchars($diag['status']); ?>">
                                    <?php echo strtoupper($diag['status']); ?>
                                </span>
                            </div>
                            <div class="diagnostic-details">
                                <?php echo $diag['details']; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    
                    <h3 style="margin-top: 30px;">💡 Troubleshooting Guide</h3>
                    <div style="background: #fffbea; padding: 20px; border-left: 4px solid #ffc107; border-radius: 4px;">
                        <h4>Common Issues & Solutions:</h4>
                        <ul>
                            <li><strong>Network Connectivity FAIL:</strong> Check your internet connection and firewall settings. The application may be blocking HTTPS connections.</li>
                            <li><strong>cURL Extension FAIL:</strong> Enable cURL in php.ini by uncommenting `extension=curl` and restart your PHP server.</li>
                            <li><strong>SSL/TLS FAIL:</strong> This is normal for development. SSL verification is disabled automatically. If using in production, see security settings.</li>
                            <li><strong>Patent Fetch FAIL (Empty Response):</strong> 
                                <ul>
                                    <li>The patent number format may be incorrect (try: US10123456B2, EP3123456A1, etc.)</li>
                                    <li>The Unified Patents API may have rate limiting - wait 5-10 minutes and try again</li>
                                    <li>Switch to Mock Mode from AI Config page for testing</li>
                                </ul>
                            </li>
                            <li><strong>Patent Fetch FAIL (Connection Error):</strong> Check your firewall and ensure HTTPS connections are allowed to api.unifiedpatents.com</li>
                        </ul>
                    </div>
                    
                    <h3 style="margin-top: 20px;">🔧 Configuration Tips</h3>
                    <div style="background: #e7f3ff; padding: 20px; border-left: 4px solid #2196F3; border-radius: 4px;">
                        <ul>
                            <li>Visit <a href="ai_config.php" style="color: #0066cc;">AI Configuration</a> to toggle between real and mock APIs</li>
                            <li>Use Mock Mode while developing and testing</li>
                            <li>Switch to Real APIs when you're ready for production-like testing</li>
                            <li>Check <strong>logs/app.log</strong> for detailed error messages</li>
                        </ul>
                    </div>
                <?php else: ?>
                    <div style="background: #f0f0f0; padding: 40px; text-align: center; border-radius: 6px; margin: 30px 0;">
                        <p style="color: #666; font-size: 16px;">Click "Run Diagnostics" to test your Patent API configuration</p>
                    </div>
                <?php endif; ?>

                <div class="button-group" style="margin-top: 30px;">
                    <a href="index.php" class="btn btn-secondary">← Back to Dashboard</a>
                    <a href="ai_config.php" class="btn btn-primary">⚙️ AI Configuration</a>
                </div>
            </div>
        </main>
    </div>

    <footer>
        <p>&copy; 2026 Patent Analysis MVP</p>
    </footer>
</body>
</html>
