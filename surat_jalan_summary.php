<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header('Content-Type: application/json; charset=utf-8'); 

$serverName = "spsdmz2";  
$connectionOptions = array(
    "Database" => "dbSopanusa",
    "Uid" => "sa",
    "PWD" => "supracor",
    "LoginTimeout" => 15,
    "Encrypt" => false,
    "TrustServerCertificate" => true
);

function getConnectionError($errors) {
    if (empty($errors)) {
        return "Unknown connection error";
    }
    
    $errorMessages = [];
    foreach ($errors as $error) {
        $sqlstate = $error['SQLSTATE'] ?? 'Unknown';
        $code = $error['code'] ?? 'Unknown';
        $message = $error['message'] ?? 'Unknown error';
        
        $errorMessages[] = "SQLSTATE: $sqlstate, Code: $code, Message: $message";
    }
    
    return implode("; ", $errorMessages);
}

function testConnection($serverName, $connectionOptions) {
    $conn = sqlsrv_connect($serverName, $connectionOptions);
    
    if (!$conn) {
        $errors = sqlsrv_errors();
        $errorDetails = [];
        
        foreach ($errors as $error) {
            $errorDetails[] = [
                'SQLSTATE' => $error['SQLSTATE'],
                'code' => $error['code'],
                'message' => $error['message']
            ];
        }
        
        return [
            'success' => false,
            'errors' => $errorDetails
        ];
    }
    
    sqlsrv_close($conn);
    return ['success' => true];
}

try {
    $connectionTest = testConnection($serverName, $connectionOptions);
    
    if (!$connectionTest['success']) {
        throw new Exception("Database connection failed. Details: " . json_encode($connectionTest['errors']));
    }

    $conn = sqlsrv_connect($serverName, $connectionOptions);

    if (!$conn) {
        $errors = sqlsrv_errors();
        throw new Exception("Database connection failed: " . getConnectionError($errors));
    }

    $sql = "SELECT FORMAT(srj.dTanggal, 'dd/MM/yyyy') as date, srj.cNoSRJ, CAST(SUM(dtl.nTon) / 1000 AS DECIMAL(10, 2)) as total_nQty
            FROM tbSRJ srj
            INNER JOIN tbSRJDtl dtl ON srj.cNoSRJ = dtl.cNoSRJ
            WHERE srj.dTanggal >= DATEADD(DAY, -7, GETDATE())
            GROUP BY FORMAT(srj.dTanggal, 'dd/MM/yyyy'), srj.cNoSRJ, srj.dTanggal
            ORDER BY srj.dTanggal DESC, srj.cNoSRJ ASC";

    $stmt = sqlsrv_query($conn, $sql);

    if ($stmt === false) {
        throw new Exception("Failed to execute query: " . getConnectionError(sqlsrv_errors()));
    }

    $data = [];
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $data[] = array_merge($row, ['total_nQty' => number_format((float)$row['total_nQty'], 2, '.', '') . ' Ton']);
    }

    $dailyTotalSql = "SELECT FORMAT(srj.dTanggal, 'dd/MM/yyyy') as date, CAST(SUM(dtl.nTon) / 1000 AS DECIMAL(10, 2)) as daily_total
                      FROM tbSRJ srj
                      INNER JOIN tbSRJDtl dtl ON srj.cNoSRJ = dtl.cNoSRJ
                      WHERE srj.dTanggal >= DATEADD(DAY, -7, GETDATE())
                      GROUP BY FORMAT(srj.dTanggal, 'dd/MM/yyyy'), srj.dTanggal
                      ORDER BY srj.dTanggal DESC";

    $dailyStmt = sqlsrv_query($conn, $dailyTotalSql);

    if ($dailyStmt === false) {
        throw new Exception("Failed to execute daily total query: " . getConnectionError(sqlsrv_errors()));
    }

    $dailyTotals = [];
    while ($row = sqlsrv_fetch_array($dailyStmt, SQLSRV_FETCH_ASSOC)) {
        $dailyTotals[] = array_merge($row, ['daily_total' => number_format((float)$row['daily_total'], 2, '.', '') . ' Ton']);
    }

    echo json_encode([
        'success' => true,
        'data' => $data,
        'daily_totals' => $dailyTotals,
        'timestamp' => date('Y-m-d H:i:s')
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => $e->getMessage(),
        'timestamp' => date('Y-m-d H:i:s')
    ], JSON_UNESCAPED_UNICODE);
} finally {
    if (isset($stmt) && $stmt) {
        sqlsrv_free_stmt($stmt);
    }
    if (isset($dailyStmt) && $dailyStmt) {
        sqlsrv_free_stmt($dailyStmt);
    }
    if (isset($conn) && $conn) {
        sqlsrv_close($conn);
    }
}
?>