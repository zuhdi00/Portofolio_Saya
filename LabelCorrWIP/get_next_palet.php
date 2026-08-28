<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$op = $_GET['op'] ?? '';
if (!$op) {
    echo json_encode(['success' => false, 'message' => 'Parameter op required']);
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
    if (!$conn) throw new Exception('Connection failed');

    $sql = "SELECT MAX(last_nisi) AS last_nisi FROM (
                SELECT MAX(nIsi) AS last_nisi FROM tbWIPV2 WHERE cNoOp = ?
                UNION ALL
                SELECT MAX(nIsi) AS last_nisi FROM tbTmpWIP WHERE cNoOp = ?
            ) x";
    $params = array($op, $op);
    $stmt = sqlsrv_query($conn, $sql, $params);
    if ($stmt === false) throw new Exception('Query failed: ' . print_r(sqlsrv_errors(), true));

    $last = 0;
    if ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $last = intval($row['last_nisi']);
    }
    $next = $last + 1;

    echo json_encode(['success' => true, 'last_nisi' => $last, 'next_nisi' => $next]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

