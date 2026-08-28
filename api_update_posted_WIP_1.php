<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$cNoSTB = $_POST['cNoSTB'] ?? '';
$lPosted = $_POST['lPosted'] ?? '';
$cKeterangan = $_POST['cKeterangan'] ?? '';

if (!$cNoSTB || $lPosted === '') {
    echo json_encode(['success' => false, 'message' => 'Data tidak lengkap']);
    exit;
}

$serverName = "spsdmz";
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

$sql = "UPDATE tbWIPV2 SET lPosted = ?, cKeterangan = ? WHERE cNoSTB = ?";
$params = array($lPosted, $cKeterangan, $cNoSTB);
$stmt = sqlsrv_query($conn, $sql, $params);

if ($stmt) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => 'Query failed']);
}