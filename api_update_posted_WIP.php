<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$cNoOp = $_POST['cNoOp'] ?? '';
$lPosted = $_POST['lPosted'] ?? '';
$cKeterangan = $_POST['cKeterangan'] ?? '';

if (!$cNoOp || $lPosted === '') {
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

$sql = "UPDATE tbWIPV2 SET lPosted = ?, cKeterangan = ? WHERE cNoOp = ?";
$params = array($lPosted, $cKeterangan, $cNoOp);
$stmt = sqlsrv_query($conn, $sql, $params);

if ($stmt) {
    echo json_encode(['success' => true]);
} else {
    $errors = sqlsrv_errors();
    $errMsg = [];
    if ($errors !== null) {
        foreach ($errors as $error) {
            $errMsg[] = "SQLSTATE: " . ($error['SQLSTATE'] ?? '') . "; code: " . ($error['code'] ?? '') . "; message: " . ($error['message'] ?? '');
        }
    }
    echo json_encode(['success' => false, 'message' => 'Query failed', 'error' => $errMsg]);
}