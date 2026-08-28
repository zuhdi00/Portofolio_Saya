<?php
header("Access-Control-Allow-Origin: https://supracor.co.id");
header("Access-Control-Allow-Origin: http://supracor.co.id");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Accept");

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

// Simple test script
echo json_encode([
    'success' => true,
    'message' => 'PHP is working!',
    'timestamp' => date('Y-m-d H:i:s'),
    'php_version' => PHP_VERSION,
    'test_query' => isset($_GET['q']) ? $_GET['q'] : 'no query provided'
]);
?>