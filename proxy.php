<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *'); 
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {

    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $barcode = $_GET['barcode'] ?? '';
    if (!$barcode) {
        echo json_encode(['success' => false, 'message' => 'No barcode provided']);
        exit;
    }

    $url = "http://edp2:8081/api_get_item_detail_v2.php?barcode=" . urlencode($barcode);

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
    $input = file_get_contents('php://input');

    $ch = curl_init("http://edp2:8081/api_save_pallet.php");
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
