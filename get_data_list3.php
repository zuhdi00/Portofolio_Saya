<?php
//isi get_data_list1.php 
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header('Content-Type: application/json; charset=utf-8'); 

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

$serverName = "spsdmz";
$connectionOptions = array(
    "Database" => "dbSopanusa",
    "Uid" => "sa",
    "PWD" => "supracor",
    "LoginTimeout" => 30,
    "Encrypt" => false,
    "TrustServerCertificate" => true,
    "ReturnDatesAsStrings" => true,
    "CharacterSet" => "UTF-8"
);

function getConnectionError($errors) {
    if (empty($errors)) return "Unknown connection error";
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
            'errors' => $errorDetails,
            'suggestions' => [
                'Check if SQL Server is running',
                'Verify server name and port',
                'Check database name exists',
                'Verify username and password',
                'Check network connectivity'
            ]
        ];
    }
    sqlsrv_close($conn);
    return ['success' => true];
}

try {
    $searchParams = [
        'search' => $_GET['search'] ?? '',
        'mc' => $_GET['mc'] ?? '',
        'rak' => $_GET['rak'] ?? '', 
        'client' => $_GET['client'] ?? '',
        'product' => $_GET['product'] ?? '',
        'order_no' => $_GET['order_no'] ?? '',
        'color' => $_GET['color'] ?? '',
        'date_from' => $_GET['date_from'] ?? '',
        'date_to' => $_GET['date_to'] ?? '',
        'limit' => isset($_GET['limit']) ? intval($_GET['limit']) : 100,
        'offset' => isset($_GET['offset']) ? intval($_GET['offset']) : 0
    ];

    if ($searchParams['limit'] > 1000) $searchParams['limit'] = 1000;
    if ($searchParams['limit'] < 1) $searchParams['limit'] = 100;
    if ($searchParams['offset'] < 0) $searchParams['offset'] = 0;

    $connectionTest = testConnection($serverName, $connectionOptions);
    if (!$connectionTest['success']) {
        throw new Exception("Database connection failed. Details: " . json_encode($connectionTest['errors']));
    }

    $conn = sqlsrv_connect($serverName, $connectionOptions);
    if (!$conn) {
        $errors = sqlsrv_errors();
        throw new Exception("Database connection failed: " . getConnectionError($errors));
    }

    $sql = "SELECT 
                op.cnm_c, 
                op.cNoMc, 
                op.cNoOp, 
                op.cnm_brg,
                op.nPanjang, 
                op.nLebar, 
                op.nTinggi,
                op.nQty, 
                op.dTgl, 
                op.cWarna,
                op.nTot_netto,
                op.cLayer,
                op.cTipe,
                op.dTglkirim,
                op.cNoSc,
                op.cMengetahui,
                op.nRm,
                bj.cRak,
                bj.cNoSTB
            FROM tbOP op
            LEFT JOIN tbStbBJ bj ON op.cNoOp = bj.cNoOp
            WHERE 1=1";

    $whereConditions = [];
    $parameters = [];

    if (!empty($searchParams['search'])) {
        $whereConditions[] = "(
            op.cNoOp LIKE ? OR 
            op.cnm_c LIKE ? OR 
            op.cnm_brg LIKE ? OR 
            op.cWarna LIKE ? OR 
            op.cNoSc LIKE ? OR
            bj.cRak LIKE ? 
        )";
        $searchTerm = "%{$searchParams['search']}%";
        $parameters = array_merge($parameters, [$searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm]);
    }

    if (!empty($searchParams['mc'])) {
        $whereConditions[] = "op.cNoMc LIKE ?";
        $parameters[] = "%{$searchParams['mc']}%";
    }
    

    if (!empty($searchParams['rak'])) {
        $whereConditions[] = "bj.cRak LIKE ?";
        $parameters[] = "%{$searchParams['rak']}%";
        $sql = str_replace(
            "FROM tbOP op
            LEFT JOIN tbStbBJ bj ON op.cNoOp = bj.cNoOp",
            "FROM tbOP op
            INNER JOIN tbStbBJ bj ON op.cNoOp = bj.cNoOp",
            $sql
        );
        $countSql = "SELECT COUNT(*) as total FROM tbOP op INNER JOIN tbStbBJ bj ON op.cNoOp = bj.cNoOp WHERE 1=1";
    } else {
        $countSql = "SELECT COUNT(*) as total FROM tbOP op WHERE op.cNoMc IS NOT NULL";
    }

    if (!empty($whereConditions)) {
        $sql .= " AND " . implode(" AND ", $whereConditions);
        $countSql .= " AND " . implode(" AND ", $whereConditions);
    }

    $sql = str_replace("SELECT ", "SELECT TOP ({$searchParams['limit']}) ", $sql);
    $sql .= " ORDER BY op.dTgl DESC, op.cNoMc DESC";

    $countStmt = sqlsrv_prepare($conn, $countSql, $parameters);
    if ($countStmt === false) {
        throw new Exception("Failed to prepare count query: " . getConnectionError(sqlsrv_errors()));
    }
    if (!sqlsrv_execute($countStmt)) {
        throw new Exception("Failed to execute count query: " . getConnectionError(sqlsrv_errors()));
    }
    $countRow = sqlsrv_fetch_array($countStmt, SQLSRV_FETCH_ASSOC);
    $totalRecords = $countRow['total'] ?? 0;

    $stmt = sqlsrv_prepare($conn, $sql, $parameters);
    if ($stmt === false) {
        throw new Exception("Failed to prepare main query: " . getConnectionError(sqlsrv_errors()));
    }
    if (!sqlsrv_execute($stmt)) {
        throw new Exception("Failed to execute main query: " . getConnectionError(sqlsrv_errors()));
    }

    $data = [];
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $row['nQty'] = $row['nQty'] !== null ? floatval($row['nQty']) : 0;
        $row['nPanjang'] = $row['nPanjang'] !== null ? floatval($row['nPanjang']) : 0;
        $row['nLebar'] = $row['nLebar'] !== null ? floatval($row['nLebar']) : 0;
        $row['nTinggi'] = $row['nTinggi'] !== null ? floatval($row['nTinggi']) : 0;
        $row['nTot_netto'] = $row['nTot_netto'] !== null ? floatval($row['nTot_netto']) : 0;
        $row['nRm'] = $row['nRm'] !== null ? floatval($row['nRm']) : 0;
        $row['dTgl'] = $row['dTgl'] ?? null;
        $row['dTglkirim'] = $row['dTglkirim'] ?? null;
        foreach ($row as $key => $value) {
            if (is_string($value)) {
                $row[$key] = trim($value);
            } elseif ($value === null) {
                $row[$key] = '';
            }
        }
        
        $total_rts = 0;

if (!empty($row['cNoSc'])) {
    $rtsDtlQuery = "
        SELECT SUM(dtl.nQty) as total_qty_rts
        FROM tbRtsSrj rts
        INNER JOIN tbRtsSrjDtl dtl ON rts.cNomer = dtl.cNomer
        WHERE rts.cNoSc = ?
    ";

    $rtsDtlStmt = sqlsrv_query($conn, $rtsDtlQuery, [$row['cNoSc']]);
    if ($rtsDtlStmt !== false && ($rtsDtlRow = sqlsrv_fetch_array($rtsDtlStmt, SQLSRV_FETCH_ASSOC))) {
        $total_rts = floatval($rtsDtlRow['total_qty_rts']);
    }
}

$row['total_rts'] = $total_rts;


        // Hitung total_stb dari tbStbBJ
        $stbQuery = "SELECT nQty as total_stb FROM tbStbBJ WHERE cNoSTB = ?";
        $stbStmt = sqlsrv_query($conn, $stbQuery, [$row['cNoSTB']]);
        $total_stb = 0;
        if ($stbStmt !== false && ($stbRow = sqlsrv_fetch_array($stbStmt, SQLSRV_FETCH_ASSOC))) {
            $total_stb = floatval($stbRow['total_stb']);
        }

        // Hitung total_srj dari tbSRJDtl
        $srjQuery = "SELECT nQty as total_srj FROM tbSRJDtl WHERE cNoOp = ?";
        $srjStmt = sqlsrv_query($conn, $srjQuery, [$row['cNoOp']]);
        $total_srj = 0;
        if ($srjStmt !== false && ($srjRow = sqlsrv_fetch_array($srjStmt, SQLSRV_FETCH_ASSOC))) {
            $total_srj = floatval($srjRow['total_srj']);
        }
        
        

        $row['total_stb'] = $total_stb;
        $row['total_srj'] = $total_srj;
        $row['qty_sekarang'] = $total_stb - $total_srj + $total_rts;

        $data[] = $row;
    }

    $activeFilters = [];
    foreach ($searchParams as $key => $value) {
        if (!empty($value) && !in_array($key, ['limit', 'offset'])) {
            $activeFilters[] = "$key: '$value'";
        }
    }

    $responseMessage = "Data loaded successfully";
    if (empty($activeFilters)) {
        $responseMessage .= " (showing latest {$searchParams['limit']} records)";
    } else {
        $responseMessage .= " (filtered by: " . implode(", ", $activeFilters) . ")";
    }

    $totalPages = ceil($totalRecords / $searchParams['limit']);
    $currentPage = floor($searchParams['offset'] / $searchParams['limit']) + 1;

    echo json_encode([
        'success' => true,
        'data' => $data,
        'pagination' => [
            'current_page' => $currentPage,
            'total_pages' => $totalPages,
            'total_records' => $totalRecords,
            'records_per_page' => $searchParams['limit'],
            'offset' => $searchParams['offset'],
            'has_next' => ($searchParams['offset'] + $searchParams['limit']) < $totalRecords,
            'has_prev' => $searchParams['offset'] > 0
        ],
        'search_params' => $searchParams,
        'message' => $responseMessage,
        'timestamp' => date('Y-m-d H:i:s')
    ], JSON_UNESCAPED_UNICODE); 

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => $e->getMessage(),
        'timestamp' => date('Y-m-d H:i:s'),
        'search_params' => isset($searchParams) ? $searchParams : [],
        'server_info' => [
            'server' => $serverName,
            'database' => $connectionOptions['Database'],
            'php_version' => PHP_VERSION,
            'sqlsrv_loaded' => extension_loaded('sqlsrv')
        ]
    ], JSON_UNESCAPED_UNICODE);
} finally {
    if (isset($stmt) && $stmt) sqlsrv_free_stmt($stmt);
    if (isset($countStmt) && $countStmt) sqlsrv_free_stmt($countStmt);
    if (isset($conn) && $conn) sqlsrv_close($conn);
}
?>