<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header('Content-Type: application/json; charset=utf-8'); 

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

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
        'search' => isset($_GET['search']) ? trim($_GET['search']) : '',
        'min_length' => 2,  // Minimum search length
        'limit' => isset($_GET['limit']) ? intval($_GET['limit']) : 50
    ];

    if (empty($searchParams['search'])) {
        throw new Exception("Search parameter is required (minimum 2 characters)");
    }

    if (strlen($searchParams['search']) < $searchParams['min_length']) {
        throw new Exception("Search term must be at least {$searchParams['min_length']} characters long");
    }

    // Cap results to avoid expensive full scans
    if ($searchParams['limit'] < 1) $searchParams['limit'] = 50;
    if ($searchParams['limit'] > 200) $searchParams['limit'] = 200;

    $connectionTest = testConnection($serverName, $connectionOptions);
    
    if (!$connectionTest['success']) {
        throw new Exception("Database connection failed. Details: " . json_encode($connectionTest['errors']));
    }

    $conn = sqlsrv_connect($serverName, $connectionOptions);

    if (!$conn) {
        error_log("Connection failed: " . print_r(sqlsrv_errors(), true));
        throw new Exception("Database connection failed. Please try again later.");
    }

    $sql = "SELECT TOP ({$searchParams['limit']})
                op.cNoMc,
                COUNT(*) as usage_count,
                MAX(op.dTgl) as last_used,
                COUNT(DISTINCT op.cnm_c) as unique_clients,
                SUM(op.nQty) as total_quantity,
                MIN(op.dTgl) as first_used
            FROM tbOP op
            WHERE op.cNoMc IS NOT NULL 
                AND op.cNoMc != '' 
                AND op.cNoMc LIKE ?
            GROUP BY op.cNoMc
            ORDER BY usage_count DESC, last_used DESC";

    $searchTerm = "%{$searchParams['search']}%";
    $parameters = [$searchTerm];

    $stmt = sqlsrv_prepare($conn, $sql, $parameters);

    if ($stmt === false) {
        error_log("SQL prepare failed: " . print_r(sqlsrv_errors(), true));
        throw new Exception("Failed to prepare MC search query. Please contact support.");
    }

    if (!sqlsrv_execute($stmt)) {
        error_log("SQL execute failed: " . print_r(sqlsrv_errors(), true));
        throw new Exception("Failed to execute MC search query. Please contact support.");
    }

    $data = [];
    $recordCount = 0;
    
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $recordCount++;
        
        $row['usage_count'] = $row['usage_count'] !== null ? intval($row['usage_count']) : 0;
        $row['unique_clients'] = $row['unique_clients'] !== null ? intval($row['unique_clients']) : 0;
        $row['total_quantity'] = $row['total_quantity'] !== null ? floatval($row['total_quantity']) : 0;

        if ($row['last_used'] && is_object($row['last_used'])) {
            $row['last_used'] = $row['last_used']->format('Y-m-d H:i:s');
        }
        if ($row['first_used'] && is_object($row['first_used'])) {
            $row['first_used'] = $row['first_used']->format('Y-m-d H:i:s');
        }

        foreach ($row as $key => $value) {
            if (is_string($value)) {
                $row[$key] = trim($value);
            } elseif ($value === null) {
                $row[$key] = '';
            }
        }

        $row['mc_code'] = $row['cNoMc']; 
        $row['popularity_score'] = $row['usage_count'] * 0.7 + $row['unique_clients'] * 0.3; 
        
        $data[] = $row;
    }

    $totalMcSql = "SELECT COUNT(DISTINCT cNoMc) as total_mc FROM tbOP WHERE cNoMc IS NOT NULL AND cNoMc != ''";
    $totalMcStmt = sqlsrv_prepare($conn, $totalMcSql);
    $totalMcCount = 0;
    
    if ($totalMcStmt && sqlsrv_execute($totalMcStmt)) {
        $totalMcRow = sqlsrv_fetch_array($totalMcStmt, SQLSRV_FETCH_ASSOC);
        $totalMcCount = $totalMcRow['total_mc'] ?? 0;
    }

    $responseMessage = "MC search completed successfully";
    if ($recordCount > 0) {
        $responseMessage .= " (found {$recordCount} MC matching '{$searchParams['search']}')";
    } else {
        $responseMessage .= " (no MC found matching '{$searchParams['search']}')";
    }

    $searchStats = [
        'total_found' => $recordCount,
        'search_term' => $searchParams['search'],
        'total_mc_in_system' => $totalMcCount,
        'match_percentage' => $totalMcCount > 0 ? round(($recordCount / $totalMcCount) * 100, 2) : 0
    ];

    echo json_encode([
        'success' => true,
        'data' => $data,
        'search_stats' => $searchStats,
        'search_params' => $searchParams,
        'message' => $responseMessage,
        'timestamp' => date('Y-m-d H:i:s')
    ], JSON_UNESCAPED_UNICODE); 
    
} catch (Exception $e) {
    http_response_code(400);
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
    if (isset($stmt) && $stmt) {
        sqlsrv_free_stmt($stmt);
    }
    if (isset($totalMcStmt) && $totalMcStmt) {
        sqlsrv_free_stmt($totalMcStmt);
    }
    if (isset($conn) && $conn) {
        sqlsrv_close($conn);
    }
}
?>