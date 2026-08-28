<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$cNoSTB = $_POST['cNoSTB'] ?? '';
$cNoOp = $_POST['cNoOp'] ?? '';
$nQty = $_POST['nQty'] ?? '';

if (!$cNoSTB || !$nQty) {
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

$sql = "UPDATE tbWIPV2 SET nQty = ? WHERE cNoOp = ? AND cNoSTB = ?";
$params = array(floatval($nQty), $cNoOp, $cNoSTB);
$stmt = sqlsrv_query($conn, $sql, $params);

if ($stmt) {
    echo json_encode(['success' => true, 'message' => 'Qty updated']);
} else {
    $err = sqlsrv_errors();
    echo json_encode(['success' => false, 'message' => 'Query failed', 'error' => $err]);
}
