<?php
// Test file untuk cek koneksi proxy
header('Content-Type: application/json');

$testUrl = "http://36.81.175.156:8081/debug_login.php";

$testData = json_encode([
    'username' => 'test',
    'password' => 'test'
]);

$ch = curl_init($testUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $testData);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json'
]);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);
curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);

curl_close($ch);

echo json_encode([
    'proxy_test' => true,
    'http_code' => $httpCode,
    'curl_error' => $curlError,
    'response' => $response,
    'target_url' => $testUrl
]);
?>