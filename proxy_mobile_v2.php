<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit;
}

// Daftar endpoint yang diizinkan
$allowed_paths = [
    'api_get_item_detail_v3.php',
    'api_update_qty_v2.php',
    'api_update_rak_v2.php',
    'api_update_posted_v2.php',
    'api_approve_stb_v2.php',
    'api_approve_stb_check_v2.php',
    'api_get_op_detail.php',           // NEW: Get OP from tbOP
    'api_upload_to_wipv2.php',         // NEW: Upload to tbWIPV2
    'api_delete_barcode_v2.php',
];

$path = $_GET['path'] ?? $_POST['path'] ?? '';

if (!in_array($path, $allowed_paths)) {
    echo json_encode(['success' => false, 'message' => 'Path API tidak valid']);
    exit;
}

// GET: Forward request
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $params = $_GET;
    unset($params['path']);
    
    // Include dan execute file API yang sesuai
    if (file_exists($path)) {
        // Pass parameters ke dalam scope
        $_GET = array_merge(['path' => $path], $params);
        ob_start();
        include $path;
        $response = ob_get_clean();
        echo $response;
    } else {
        echo json_encode(['success' => false, 'message' => 'File API tidak ditemukan: ' . $path]);
    }
}
// POST: Forward request
elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Parse POST data
    if (!empty($_POST)) {
        $_GET = array_merge(['path' => $path], $_GET);
    } else {
        $input = json_decode(file_get_contents('php://input'), true);
        $_POST = $input ? $input : [];
        $_GET = array_merge(['path' => $path], $_GET);
    }
    
    if (file_exists($path)) {
        ob_start();
        include $path;
        $response = ob_get_clean();
        echo $response;
    } else {
        echo json_encode(['success' => false, 'message' => 'File API tidak ditemukan: ' . $path]);
    }
}
else {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
}
?>
