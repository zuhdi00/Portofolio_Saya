<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$year = $_GET['year'] ?? '';
$month = $_GET['month'] ?? '';

if (!$year || !$month) {
    echo json_encode(['success' => false, 'message' => 'Year and month required']);
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
        throw new Exception("Connection failed.");
    }

    $prefix = "STB-" . $year . $month;
    $sql = "SELECT MAX(last_number) AS last_number FROM (
                SELECT MAX(CAST(RIGHT(cNoSTB, 4) AS INT)) AS last_number FROM tbStbBJ WHERE cNoSTB LIKE ?
                UNION ALL
                SELECT MAX(CAST(RIGHT(cNoSTB, 4) AS INT)) AS last_number FROM tbTmpStbBJ WHERE cNoSTB LIKE ?
            ) x";
    $params = array($prefix . '%', $prefix . '%');
    $stmt = sqlsrv_query($conn, $sql, $params);

    if ($stmt === false) {
        throw new Exception("Query failed.");
    }

    $lastNumber = 0;
    if ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $lastNumber = intval($row['last_number']);
    }

    echo json_encode(['success' => true, 'last_number' => $lastNumber]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}