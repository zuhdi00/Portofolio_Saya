<?php
// Simple proxy to forward mobile app requests to internal API server
// Usage:
//  GET:  https://supracor.co.id/proxi_mobile.php?path=api_get_data.php&other=...
//  POST: https://supracor.co.id/proxi_mobile.php?path=api_update_qty.php

// Configuration
$internalHost = 'http://180.251.123.171:8081/';

// CORS & allowed methods
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    // Preflight
    http_response_code(204);
    exit;
}

// Get requested path and basic validation
$path = isset($_GET['path']) ? trim($_GET['path']) : null;
if (!$path) {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Missing path parameter']);
    exit;
}

// Prevent directory traversal and normalize
$path = ltrim($path, '/');
if (strpos($path, '..') !== false) {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid path']);
    exit;
}

// Whitelist allowed endpoints (either local or remote)
$allowed = [
    'api_get_data.php', 'api_get_item_detail.php', 'api_get_item_detail_v2.php',
    'api_update_qty.php', 'api_save_pallet.php', 'api_update_posted.php',
    'api_save_stb.php', 'api_save_stb', 'proxy.php'
];

// Only allow requests for whitelisted paths
$pathBase = basename($path);
if (!in_array($pathBase, $allowed, true)) {
    http_response_code(403);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Path not allowed']);
    exit;
}

// If the target script exists locally on this server, execute it directly
$localFile = __DIR__ . DIRECTORY_SEPARATOR . $pathBase;
if (file_exists($localFile) && is_file($localFile)) {
    // Execute local script and return its output
    ob_start();
    // Let the included script access the same input (php://input, $_GET, $_POST)
    include $localFile;
    $output = ob_get_clean();
    // If the included script already set headers/status, we don't override
    echo $output;
    exit;
}

// Otherwise forward to internal host
// Build target URL (forward remaining query parameters except 'path')
$query = $_SERVER['QUERY_STRING'];
$query = preg_replace('/(^|&)path=[^&]*/', '', $query);
$query = trim($query, '&');
$target = rtrim($internalHost, '/') . '/' . $path;
if ($query) $target .= '?' . $query;

// Initialize curl
$ch = curl_init($target);
$method = $_SERVER['REQUEST_METHOD'];
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HEADER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

// Prepare headers to forward: forward Content-Type and Authorization if present
$forwardHeaders = [];
$allHeaders = function_exists('getallheaders') ? getallheaders() : [];
foreach ($allHeaders as $name => $value) {
    $low = strtolower($name);
    if ($low === 'host') continue;
    if (in_array($low, ['content-type', 'authorization', 'accept'], true)) {
        $forwardHeaders[] = $name . ': ' . $value;
    }
}
curl_setopt($ch, CURLOPT_HTTPHEADER, $forwardHeaders);

// Forward body if present
if (in_array($method, ['POST', 'PUT', 'PATCH'], true)) {
    $body = file_get_contents('php://input');
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
}

curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);

$response = curl_exec($ch);
if ($response === false) {
    http_response_code(502);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Proxy error: ' . curl_error($ch)]);
    curl_close($ch);
    exit;
}

$header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
$raw_headers = substr($response, 0, $header_size);
$body = substr($response, $header_size);
$status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

// Forward status and some headers
http_response_code($status);
if (preg_match('/Content-Type:\s*([^\r\n]+)/i', $raw_headers, $m)) {
    header('Content-Type: ' . trim($m[1]));
} else {
    header('Content-Type: application/json');
}

echo $body;

?>
