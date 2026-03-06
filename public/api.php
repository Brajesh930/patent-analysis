
<?php
// Simple proxy for Unified Patents API to avoid CORS and embed API key if needed.
// Usage:
//  - /public/api.php?op=transform  (POST body: JSON {publications: [...]})
//  - /public/api.php?op=patent&number=XXXX
//  - /public/api.php?op=get_patent_data&patent_id=XXXX
// No authentication is implemented; this is MVP.

header('Content-Type: application/json');

$apiBase = 'https://api.unifiedpatents.com';

$op = $_GET['op'] ?? '';

if ($op === 'transform') {
    // forward POST data
    $body = file_get_contents('php://input');
    $url = $apiBase . '/helpers/transform-publication-numbers';
    $resp = forwardRequest($url, $body, ['Content-Type: application/json']);
    echo $resp;
    exit;
} elseif ($op === 'patent') {
    $number = $_GET['number'] ?? '';
    if (!$number) {
        http_response_code(400);
        echo json_encode(['error' => 'missing number']);
        exit;
    }
    $url = $apiBase . '/patents/' . urlencode($number) . '?with_cases=true';
    $resp = forwardRequest($url);
    echo $resp;
    exit;
} elseif ($op === 'get_patent_data') {
    // Get patent data from database for modal display
    $patentId = $_GET['patent_id'] ?? 0;
    
    if (!$patentId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'missing patent_id']);
        exit;
    }
    
    // Load required files
    require_once __DIR__ . '/../config/config.php';
    require_once __DIR__ . '/../lib/Database.php';
    
    $db = Database::getInstance();
    $patent = $db->getPatent($patentId);
    
    if (!$patent) {
        echo json_encode(['success' => false, 'error' => 'Patent not found']);
        exit;
    }
    
    // Try to fetch fresh data if not in DB
    $claims = $patent['claims_text'] ?? '';
    $abstract = $patent['abstract_text'] ?? '';
    
    if (empty($claims) && empty($abstract)) {
        // Fetch from API if not in DB
        require_once __DIR__ . '/../lib/PatentProvider.php';
        $fetchResult = PatentProvider::fetchCompletePatentData($patent['patent_number'], 'full');
        
        if ($fetchResult && isset($fetchResult['status']) && $fetchResult['status'] === 'ok') {
            $claims = $fetchResult['claims'] ?? '';
            $abstract = $fetchResult['abstract'] ?? '';
            $title = $fetchResult['title'] ?? '';
            
            // Update DB
            $db->updatePatentText($patentId, $claims, $abstract, 'ok');
        }
    } else {
        $title = '';
    }
    
    echo json_encode([
        'success' => true,
        'patent_number' => $patent['patent_number'],
        'title' => $title,
        'abstract' => $abstract,
        'claims' => $claims
    ]);
    exit;
} else {
    http_response_code(400);
    echo json_encode(['error' => 'invalid operation']);
    exit;
}

function forwardRequest($url, $body = null, $headers = []) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    if ($body !== null) {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
    }
    $default = [];
    if ($headers) $default = $headers;
    // Add any auth headers here if needed (e.g. API key)
    curl_setopt($ch, CURLOPT_HTTPHEADER, $default);
    $out = curl_exec($ch);
    $err = curl_error($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    // curl_close() is deprecated in PHP 8.0+ and removed in PHP 8.5
    // cURL handles are automatically closed when they go out of scope
    unset($ch);
    if ($err) {
        http_response_code(502);
        return json_encode(['error' => $err]);
    }
    http_response_code($code);
    return $out;
}

