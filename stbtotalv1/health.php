<?php
// Lightweight health endpoint for connectivity checks
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

echo json_encode([
    'success' => true,
    'service' => 'stbtotalv1',
    'time' => date('c'),
    'php_version' => PHP_VERSION
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
