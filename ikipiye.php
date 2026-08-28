<?php
// Proxy untuk aplikasi mobile ke API server lama
// Contoh pemakaian:
//   GET  http://supracor.co.id/proxy_mobile.php?path=api_get_item_detail_v2.php&barcode=123
//   POST http://supracor.co.id/proxy_mobile.php?path=api_update_qty.php

$internalHost = 'http://36.76.113.224:8081/';

// CORS & allowed methods
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, Accept');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit;
}

// Ambil parameter path
$path = isset($_GET['path']) ? trim($_GET['path']) : null;
if (!$path) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing path parameter']);
    exit;
}

// Cegah directory traversal
$path = ltrim($path, '/');
if (strpos($path, '..') !== false) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid path']);
    exit;
}

// Daftar endpoint yang diizinkan
$allowed = [
    'api_get_data.php',
    'api_get_item_detail.php',
    'api_get_item_detail_29092025.php',
    'api_save_pallet.php',
    'api_update_qty.php',
    'api_update_rak.php',
    'api_update_posted.php',
    'api_approve_stb.php',
    'api_delete_barcode.php',
    'test_connection.php',
    'api_outgo.php',
    // Tambahkan endpoint lain sesuai kebutuhan aplikasi
];

$pathBase = basename($path);
if (!in_array($pathBase, $allowed, true)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Path not allowed']);
    exit;
}

// Jika file ada di lokal, eksekusi langsung
$localFile = __DIR__ . DIRECTORY_SEPARATOR . $pathBase;
if (file_exists($localFile) && is_file($localFile)) {
    ob_start();
    include $localFile;
    $output = ob_get_clean();
    echo $output;
    exit;
}

// Forward ke server API lama
// Build target URL (forward semua query kecuali 'path')
$query = $_SERVER['QUERY_STRING'];
$query = preg_replace('/(^|&)path=[^&]*/', '', $query);
$query = trim($query, '&');
$target = rtrim($internalHost, '/') . '/' . $pathBase;
if ($query) $target .= '?' . $query;

// Init curl
$ch = curl_init($target);
$method = $_SERVER['REQUEST_METHOD'];
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HEADER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

// Forward header penting
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

// Forward body jika POST/PUT/PATCH
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
    echo json_encode(['success' => false, 'message' => 'Proxy error: ' . curl_error($ch)]);
    curl_close($ch);
    exit;
}

$header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
$raw_headers = substr($response, 0, $header_size);
$body = substr($response, $header_size);
$status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

// Forward status dan Content-Type
http_response_code($status);
if (preg_match('/Content-Type:\s*([^\r\n]+)/i', $raw_headers, $m)) {
    header('Content-Type: ' . trim($m[1]));
} else {
    header('Content-Type: application/json');
}

echo $body;
?>
