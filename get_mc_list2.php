<?php
//isi get_mc_list2.php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

$serverName = "spsdmz";
$connectionOptions = [
    "Database" => "dbSopanusa",
    "Uid" => "sa",
    "PWD" => "supracor",
    "LoginTimeout" => 30,
    "Encrypt" => false,
    "TrustServerCertificate" => true,
    "ReturnDatesAsStrings" => true,
    "CharacterSet" => "UTF-8"
];

function getConnectionError($errors) {
    $messages = [];
    foreach ($errors as $e) {
        $messages[] = "SQLSTATE: {$e['SQLSTATE']}, Code: {$e['code']}, Message: {$e['message']}";
    }
    return implode("; ", $messages);
}

try {
    $search = isset($_GET['search']) ? trim($_GET['search']) : '';
    if (strlen($search) < 2) {
        throw new Exception("Parameter 'search' minimal 2 karakter.");
    }

    $conn = sqlsrv_connect($serverName, $connectionOptions);
    if (!$conn) {
        throw new Exception("Database connection failed: " . getConnectionError(sqlsrv_errors()));
    }

    $combinedResults = [];

    // Query untuk MC
    $sqlMC = "
        SELECT 
            op.cNoMc AS search_key,
            'mc' AS search_type,
            COUNT(*) AS usage_count,
            MAX(op.dTgl) AS last_used,
            MIN(op.dTgl) AS first_used,
            COUNT(DISTINCT op.cnm_c) AS unique_clients,
            SUM(op.nQty) AS total_quantity
        FROM tbOP op
        INNER JOIN tbStbBJ sbj ON op.cNoMc = sbj.cNoMC
        WHERE op.cNoMc IS NOT NULL AND op.cNoMc != '' AND op.cNoMc LIKE ?
        GROUP BY op.cNoMc
        ORDER BY usage_count DESC
    ";

    $paramsMC = ["%$search%"];
    $stmtMC = sqlsrv_prepare($conn, $sqlMC, $paramsMC);
    if (!$stmtMC || !sqlsrv_execute($stmtMC)) {
        throw new Exception("Gagal menjalankan query MC: " . getConnectionError(sqlsrv_errors()));
    }

    while ($row = sqlsrv_fetch_array($stmtMC, SQLSRV_FETCH_ASSOC)) {
        $formattedRow = [
            'search_key' => trim($row['search_key']),
            'search_type' => $row['search_type'],
            'display_text' => trim($row['search_key']) . ' (MC)',
            'usage_count' => intval($row['usage_count']),
            'unique_clients' => intval($row['unique_clients']),
            'total_quantity' => floatval($row['total_quantity']),
            'last_used' => $row['last_used'] instanceof DateTime ? $row['last_used']->format("Y-m-d H:i:s") : null,
            'first_used' => $row['first_used'] instanceof DateTime ? $row['first_used']->format("Y-m-d H:i:s") : null
        ];
        $combinedResults[] = $formattedRow;
    }

    // Query untuk Rak
    $sqlRak = "
        SELECT 
            sbj.cRak AS search_key,
            'rak' AS search_type,
            COUNT(DISTINCT sbj.cNoMC) AS mc_count,
            COUNT(*) AS total_ops,
            MIN(op.dTgl) AS first_used,
            MAX(op.dTgl) AS last_used,
            SUM(ISNULL(sbj.nQty, 0)) AS total_quantity
        FROM tbStbBJ sbj
        INNER JOIN tbOP op ON 
            LTRIM(RTRIM(CAST(sbj.cNoMC AS VARCHAR(50)))) = LTRIM(RTRIM(CAST(op.cNoMc AS VARCHAR(50))))
        WHERE sbj.cRak LIKE ?
        GROUP BY sbj.cRak
        ORDER BY mc_count DESC
    ";

    $paramsRak = ["%$search%"];
    $stmtRak = sqlsrv_prepare($conn, $sqlRak, $paramsRak);
    if (!$stmtRak || !sqlsrv_execute($stmtRak)) {
        throw new Exception("Gagal menjalankan query Rak: " . getConnectionError(sqlsrv_errors()));
    }

    while ($row = sqlsrv_fetch_array($stmtRak, SQLSRV_FETCH_ASSOC)) {
        $formattedRow = [
            'search_key' => trim($row['search_key']),
            'search_type' => $row['search_type'],
            'display_text' => trim($row['search_key']) . ' (Rak)',
            'mc_count' => intval($row['mc_count']),
            'total_ops' => intval($row['total_ops']),
            'total_quantity' => floatval($row['total_quantity']),
            'last_used' => $row['last_used'] instanceof DateTime ? $row['last_used']->format("Y-m-d H:i:s") : null,
            'first_used' => $row['first_used'] instanceof DateTime ? $row['first_used']->format("Y-m-d H:i:s") : null
        ];
        $combinedResults[] = $formattedRow;
    }

    if (count($combinedResults) > 0) {
        echo json_encode([
            'success' => true,
            'mode' => 'combined_search',
            'data' => $combinedResults,
            'message' => "Ditemukan " . count($combinedResults) . " hasil yang cocok dengan '$search'",
            'search_term' => $search,
            'timestamp' => date('Y-m-d H:i:s')
        ], JSON_UNESCAPED_UNICODE);
        exit();
    }

    echo json_encode([
        'success' => false,
        'message' => "Tidak ditemukan data MC atau Rak yang cocok dengan '$search'",
        'search_term' => $search,
        'timestamp' => date('Y-m-d H:i:s')
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'timestamp' => date('Y-m-d H:i:s'),
        'search_term' => isset($search) ? $search : '',
        'server_info' => [
            'server' => $serverName,
            'database' => $connectionOptions['Database'],
            'php_version' => PHP_VERSION,
            'sqlsrv_loaded' => extension_loaded('sqlsrv')
        ]
    ], JSON_UNESCAPED_UNICODE);
} finally {
    if (isset($stmtMC)) sqlsrv_free_stmt($stmtMC);
    if (isset($stmtRak)) sqlsrv_free_stmt($stmtRak);
    if (isset($conn)) sqlsrv_close($conn);
}
?>