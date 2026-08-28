<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

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

if ($conn === false) {
    $errors = sqlsrv_errors();
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Database connection failed',
        'details' => $errors
    ]);
    exit;
}

// Pastikan kolom-kolom ini sesuai dengan tabel tbOP
$sql = "SELECT TOP 10 cNoOp, cnm_c, cnm_brg FROM tbOP";
$stmt = sqlsrv_query($conn, $sql);

if ($stmt === false) {
    $errors = sqlsrv_errors();
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Query execution failed',
        'details' => $errors
    ]);
    sqlsrv_close($conn);
    exit;
}

$data = array();
while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $data[] = $row;
}

echo json_encode([
    'status' => 'success',
    'data' => $data
]);

sqlsrv_close($conn);
