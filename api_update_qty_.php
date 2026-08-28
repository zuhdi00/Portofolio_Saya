<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$cNoSTB = $_POST['cNoSTB'] ?? '';
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

$sql = "UPDATE tbTmpStbBJ SET nQty = ? WHERE cNoSTB = ?";
$params = array(floatval($nQty), $cNoSTB);
$stmt = sqlsrv_query($conn, $sql, $params);

if ($stmt) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => 'Query failed']);
}
