<?php
header('Content-Type: application/json; charset=utf-8');
// Start session if available
if (session_status() !== PHP_SESSION_ACTIVE) session_start();

function get_request_headers_safe() {
    if (function_exists('getallheaders')) return getallheaders();
    $headers = [];
    foreach ($_SERVER as $name => $value) {
        if (substr($name, 0, 5) == 'HTTP_') {
            $headers[str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))))] = $value;
        }
    }
    return $headers;
}

echo json_encode([
    'host' => $_SERVER['HTTP_HOST'] ?? null,
    'scheme' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http',
    'cookie_header' => $_SERVER['HTTP_COOKIE'] ?? null,
    'cookies' => $_COOKIE,
    'session_id' => session_id(),
    'session' => isset($_SESSION) ? $_SESSION : null,
    'request_headers' => get_request_headers_safe(),
    'server_time' => date(DATE_ATOM)
], JSON_PRETTY_PRINT);
