<?php
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