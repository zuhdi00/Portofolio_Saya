<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

$input = json_decode(file_get_contents('php://input'), true);

$op     = $input['op']     ?? '';
$pallet = $input['pallet'] ?? '';
$koli   = $input['koli']   ?? '';

if (!$op || !is_numeric($pallet) || !is_numeric($koli)) {
    echo json_encode(['success' => false, 'message' => 'Invalid input: OP, pallet, or koli missing or not numeric.']);
    exit;
}

try {
    $serverName = "spsdmz2";
    $connectionOptions = [
        "Database" => "dbSopanusa",
        "Uid" => "sa",
        "PWD" => "supracor",
        "LoginTimeout" => 15,
        "Encrypt" => false,
        "TrustServerCertificate" => true
    ];

    $conn = sqlsrv_connect($serverName, $connectionOptions);

    if (!$conn) {
        throw new Exception("Connection failed: " . print_r(sqlsrv_errors(), true));
    }

    $sql = "UPDATE tbOp SET clengkap2 = ?, clengkap3 = ? WHERE cNoOp = ?";
    $params = [$pallet, $koli, $op];

    $stmt = sqlsrv_query($conn, $sql, $params);

    if ($stmt === false) {
        throw new Exception("Update query failed: " . print_r(sqlsrv_errors(), true));
    }

    echo json_encode(['success' => true, 'message' => 'Pallet and koli values saved successfully.']);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
