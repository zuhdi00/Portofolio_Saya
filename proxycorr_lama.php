<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *'); // Bisa disesuaikan jika ingin restrict
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    // Untuk preflight CORS
    exit;
}

function getPublicIP() {
    $logFile = __DIR__ . '/ip_log.txt';
    if (!file_exists($logFile)) return '180.251.123.55';
    $lines = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if (!$lines || count($lines) == 0) return '180.251.123.55';
    $lastLine = $lines[count($lines) - 1];
    if (preg_match('/Received IP: ([\d\.]+)/', $lastLine, $matches)) {
        return $matches[1];
    }
    return '180.251.123.55';
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Ambil data item berdasarkan barcode
    $barcode = $_GET['barcode'] ?? '';
    if (!$barcode) {
        echo json_encode(['success' => false, 'message' => 'No barcode provided']);
        exit;
    }

    $publicIP = getPublicIP();
    $url = "http://$publicIP:8081/api_get_item_detail_v2.php?barcode=" . urlencode($barcode);

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    if (curl_errno($ch)) {
        echo json_encode(['success' => false, 'message' => 'Curl Error: ' . curl_error($ch)]);
    } else {
        http_response_code($httpCode);
        echo $response;
    }

    curl_close($ch);

} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Simpan jumlah koli dan pallet
    $input = file_get_contents('php://input');
    $publicIP = getPublicIP();
    $ch = curl_init("http://$publicIP:8081/api_save_pallet.php");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $input);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    if (curl_errno($ch)) {
        echo json_encode(['success' => false, 'message' => 'Curl Error: ' . curl_error($ch)]);
    } else {
        http_response_code($httpCode);
        echo $response;
    }

    curl_close($ch);

} else {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
}
