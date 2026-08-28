<?php
// filepath: c:\xampp\htdocs\proxi_mobile.php

header('Content-Type: application/json');

// Validasi parameter target
if (!isset($_GET['target'])) {
    http_response_code(400);
    echo json_encode(['error' => 'No target specified']);
    exit;
}

$target = basename($_GET['target']); // Hindari directory traversal

// URL tujuan (ganti sesuai kebutuhan)
$remote_base = 'http://36.90.176.73:8081/';
$remote_url = $remote_base . $target;

// Gabungkan parameter GET dan POST (kecuali 'target')
$params = $_GET;
unset($params['target']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $params = array_merge($params, $_POST);
}

// Siapkan cURL
$ch = curl_init();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    curl_setopt($ch, CURLOPT_URL, $remote_url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
} else {
    $query = http_build_query($params);
    $url_with_query = $remote_url . ($query ? '?' . $query : '');
    curl_setopt($ch, CURLOPT_URL, $url_with_query);
}

curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HEADER, false);
curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10); // 10 detik koneksi
curl_setopt($ch, CURLOPT_TIMEOUT, 15); // 15 detik total

// Jika perlu, tambahkan timeout atau header di sini

$response = curl_exec($ch);

if ($response === false) {
    http_response_code(502);
    echo json_encode(['error' => 'Proxy error: ' . curl_error($ch)]);
    curl_close($ch);
    exit;
}

$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

http_response_code($http_code);
echo $response;