<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$cNoOp= $_POST['cNoOp'] ?? '';
$cRak = $_POST['cRak'] ?? '';

if (!$cNoOp || !$cRak) {
    echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
    exit;
}

try {
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
        throw new Exception("Database connection failed.");
    }

    $sql = "UPDATE tbWIPV2 SET cRak = ? WHERE cNoOp = ?";
    $params = array($cRak, $cNoSTB);
    $stmt = sqlsrv_query($conn, $sql, $params);

    if ($stmt) {
        echo json_encode(['success' => true, 'message' => 'Rak updated']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update']);
    }

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
