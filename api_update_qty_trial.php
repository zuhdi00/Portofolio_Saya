<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$token = $_GET['token'] ?? '';
if ($token !== 'my_secure_token') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$cNoOp = $_POST['cNoOp'] ?? '';
$nQty = $_POST['nQty'] ?? '';

if (!$cNoOp || !$nQty) {
    echo json_encode(['success' => false, 'message' => 'Data tidak lengkap']);
    exit;
}

$serverName = "spsdmz2";
$connectionOptions = array(
    "Database" => "dbSopanusa",
    "Uid" => "sa",
    "PWD" => "supracor",
    "LoginTimeout" => 15,
    "Encrypt" => false,
    "TrustServerCertificate" => true
);
$conn = sqlsrv_connect($serverName, $connectionOptions);

if (!$conn) {
    echo json_encode(['success' => false, 'message' => 'Connection failed']);
    exit;
}

$sql = "UPDATE tbOP SET nQty = ? WHERE cNoOp = ?";
$params = array(floatval($nQty), $cNoOp);
$stmt = sqlsrv_query($conn, $sql, $params);

if ($stmt) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => 'Query failed']);
}
