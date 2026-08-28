// filepath: c:\xampp\htdocs\proxy_mobile.php
<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit;
}

// Daftar endpoint yang diizinkan
$allowed_targets = [
    'api_get_item_detail_v2.php',
    'api_save_pallet.php',
    'api_update_qty.php',
    'api_update_rak.php',
    'api_update_posted.php',
    'api_approve_stb.php',
    'api_delete_barcode.php',
    // Tambahkan endpoint lain sesuai kebutuhan
];

$target = $_GET['target'] ?? $_POST['target'] ?? '';
if (!in_array($target, $allowed_targets)) {
    echo json_encode(['success' => false, 'message' => 'Target API tidak valid']);
    exit;
}

// IP lama server API
$base_url = "http://180.251.123.171:8081/";

$url = $base_url . $target;

// GET: Forward semua parameter kecuali target
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $params = $_GET;
    unset($params['target']);
    if (!empty($params)) {
        $url .= '?' . http_build_query($params);
    }
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
}
// POST: Forward body
elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = file_get_contents('php://input');
    $ch = curl_init($url);
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
}
else {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
}
?>