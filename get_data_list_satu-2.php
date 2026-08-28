<?php
//isi get_mc_list.php
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
    "LoginTimeout" => 30,
    "Encrypt" => false,
    "TrustServerCertificate" => true,
    "ReturnDatesAsStrings" => true,
    "CharacterSet" => "UTF-8"
);

function mapRackCode($cRak) {
    $mapping = [
        '1' => 'A-1',
        '2' => 'A-2',
        '3' => 'B-1',
        '4' => 'B-2',
        '5' => 'C-1',
        '6' => 'C-2',
        '7' => 'CORRUGATING 1',
        '8' => 'CORRUGATING 2',
        '9' => 'FOLDER GLUE',
        '10' => 'FLADBAD',
        '11' => 'FLEXO-1',
        '12' => 'FLEXO-2',
        '13' => 'FLEXO-4',
        '14' => 'FLEXO-5',
        '15' => 'FLEXO-6',
        '16' => 'FLEXO-7',
        '17' => 'FLEXO-8',
        '18' => 'FLEXO-9',
        '19' => 'IKAT',
        '20' => 'LANTHEC',
        '21' => 'LANGSUNG KIRIM',
        '22' => 'RDC',
        '23' => 'RAK-A',
        '24' => 'RAK-B',
        '25' => 'SLITTER',
        '26' => 'STITCHING'
    ];

    return $mapping[trim($cRak)] ?? '-';
}

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

// Helper to normalize date parameters for sqlsrv queries
function sqlsrv_date_param($date, $endOfDay = false) {
    $d = trim((string)$date);
    if ($d === '') return null;
    // If time already included, return as-is
    if (preg_match('/\d{4}-\d{2}-\d{2}\s+\d{2}:\d{2}:\d{2}/', $d)) return $d;
    // Append time portion
    return $d . ($endOfDay ? ' 23:59:59' : ' 00:00:00');
}

try {
    $searchParams = [
        'search' => isset($_GET['search']) ? trim($_GET['search']) : '',
        'mc' => isset($_GET['mc']) ? trim($_GET['mc']) : '',
        'client' => isset($_GET['client']) ? trim($_GET['client']) : '',
        'product' => isset($_GET['product']) ? trim($_GET['product']) : '',
        'order_no' => isset($_GET['order_no']) ? trim($_GET['order_no']) : '',
        'color' => isset($_GET['color']) ? trim($_GET['color']) : '',
        'date_from' => isset($_GET['date_from']) ? trim($_GET['date_from']) : '',
        'date_to' => isset($_GET['date_to']) ? trim($_GET['date_to']) : '',
        'flexo' => isset($_GET['flexo']) ? trim($_GET['flexo']) : '',
        'dc' => isset($_GET['dc']) ? trim($_GET['dc']) : '',
        'shipping_date_from' => isset($_GET['shipping_date_from']) ? trim($_GET['shipping_date_from']) : '',
        'shipping_date_to' => isset($_GET['shipping_date_to']) ? trim($_GET['shipping_date_to']) : '',
        'status' => isset($_GET['status']) ? trim($_GET['status']) : '',
        'limit' => isset($_GET['limit']) ? intval($_GET['limit']) : 200,
        'offset' => isset($_GET['offset']) ? intval($_GET['offset']) : 0
    ];
    
    if ($searchParams['limit'] > 100000) $searchParams['limit'] = 100000;
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
                op.cFlexo,
                op.nBrutto_usp,
                op.nBrutto_usl,
                op.userdate,
                op.dTglkirim2,
                op.cNoSc,
                op.cMengetahui,
                op.nRm,
                op.nTot_netto,
                op.ckd_b1,
                op.ckd_b2,
                op.ckd_b3,
                op.ckd_b4,
                op.ckd_b5,
                op.nQtyStok,
                op.lTK,
                op.cKetOrder,
                op.cDC,
                sc.cStatus
            FROM tbOP op
            LEFT JOIN tbSC sc ON op.cNoSc = sc.cNoSC
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
            op.cMengetahui LIKE ? OR
            op.cDC LIKE ?
        )";
        $searchTerm = "%{$searchParams['search']}%";
        $parameters = array_merge($parameters, [$searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm]);
    }

    if (!empty($searchParams['mc'])) {
        $whereConditions[] = "op.cNoMc LIKE ?";
        $parameters[] = "%{$searchParams['mc']}%";
    }

    if (!empty($searchParams['client'])) {
        $whereConditions[] = "op.cnm_c LIKE ?";
        $parameters[] = "%{$searchParams['client']}%";
    }

    if (!empty($searchParams['product'])) {
        $whereConditions[] = "op.cnm_brg LIKE ?";
        $parameters[] = "%{$searchParams['product']}%";
    }

    if (!empty($searchParams['order_no'])) {
        $whereConditions[] = "op.cNoOp LIKE ?";
        $parameters[] = "%{$searchParams['order_no']}%";
    }

    if (!empty($searchParams['color'])) {
        $whereConditions[] = "op.cWarna LIKE ?";
        $parameters[] = "%{$searchParams['color']}%";
    }

    if (!empty($searchParams['date_from'])) {
        $whereConditions[] = "op.dTgl >= ?";
        $parameters[] = $searchParams['date_from'];
    }

    if (!empty($searchParams['date_to'])) {
        $whereConditions[] = "op.dTgl <= ?";
        $parameters[] = $searchParams['date_to'] . ' 23:59:59';
    }

    if (!empty($searchParams['flexo'])) {
        $whereConditions[] = "op.cFlexo = ?";
        $parameters[] = $searchParams['flexo'];
    }

    if (!empty($searchParams['dc'])) {
        $whereConditions[] = "op.cDC = ?";
        $parameters[] = $searchParams['dc'];
    }

    if (!empty($searchParams['shipping_date_from'])) {
        $whereConditions[] = "op.dTglKirim2 >= ?";
        $parameters[] = sqlsrv_date_param($searchParams['shipping_date_from']);
    }

    if (!empty($searchParams['shipping_date_to'])) {
        $whereConditions[] = "op.dTglKirim2 <= ?";
        $parameters[] = sqlsrv_date_param($searchParams['shipping_date_to'], true);
    }

    if (!empty($searchParams['status'])) {
        if (strtoupper($searchParams['status']) === 'OPEN') {
            $whereConditions[] = "(sc.cStatus IS NULL OR sc.cStatus = '' OR sc.cStatus = '-' OR sc.cStatus = 'null' OR sc.cStatus = 'undefined' OR sc.cStatus = 'OPEN')";
        } else {
            $whereConditions[] = "sc.cStatus = ?";
            $parameters[] = strtoupper($searchParams['status']);
        }
    }

    if (!empty($whereConditions)) {
        $sql .= " AND " . implode(" AND ", $whereConditions);
    }

    $sql = str_replace("SELECT ", "SELECT TOP ({$searchParams['limit']}) ", $sql);
    $sql .= " ORDER BY op.dTglKirim2 DESC";

    $countSql = "SELECT COUNT(*) as total FROM tbOP op LEFT JOIN tbSC sc ON op.cNoSc = sc.cNoSC WHERE op.cNoMc IS NOT NULL";
    if (!empty($whereConditions)) {
        $countSql .= " AND " . implode(" AND ", $whereConditions);
    }

    $countStmt = sqlsrv_prepare($conn, $countSql, $parameters);
    
    if ($countStmt === false) {
        $errors = sqlsrv_errors();
        throw new Exception("Failed to prepare count query: " . getConnectionError($errors));
    }

    if (!sqlsrv_execute($countStmt)) {
        $errors = sqlsrv_errors();
        throw new Exception("Failed to execute count query: " . getConnectionError($errors));
    }

    $countRow = sqlsrv_fetch_array($countStmt, SQLSRV_FETCH_ASSOC);
    $totalRecords = $countRow['total'] ?? 0;

    $stmt = sqlsrv_prepare($conn, $sql, $parameters);

    if ($stmt === false) {
        $errors = sqlsrv_errors();
        throw new Exception("Failed to prepare main query: " . getConnectionError($errors));
    }

    if (!sqlsrv_execute($stmt)) {
        $errors = sqlsrv_errors();
        throw new Exception("Failed to execute main query: " . getConnectionError($errors));
    }

    $data = [];
    $recordCount = 0;
    
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $recordCount++;
        
        $row['nQty'] = $row['nQty'] !== null ? floatval($row['nQty']) : 0;
        $row['nPanjang'] = $row['nPanjang'] !== null ? floatval($row['nPanjang']) : 0;
        $row['nLebar'] = $row['nLebar'] !== null ? floatval($row['nLebar']) : 0;
        $row['nTinggi'] = $row['nTinggi'] !== null ? floatval($row['nTinggi']) : 0;
        $row['nTot_netto'] = $row['nTot_netto'] !== null ? floatval($row['nTot_netto']) : 0;
        $row['nRm'] = $row['nRm'] !== null ? floatval($row['nRm']) : 0;
        $row['nBrutto_usp'] = $row['nBrutto_usp'] !== null ? floatval($row['nBrutto_usp']) : 0;
        $row['nBrutto_usl'] = $row['nBrutto_usl'] !== null ? floatval($row['nBrutto_usl']) : 0;

        $row['dTgl'] = $row['dTgl'] ?? null;
        $row['dTglkirim2'] = $row['dTglkirim2'] ?? null;
        $row['userdate'] = $row['userdate'] ?? null;

        foreach ($row as $key => $value) {
            if (is_string($value)) {
                $row[$key] = trim($value);
            } elseif ($value === null) {
                $row[$key] = '';
            }
        }

        if (isset($row['cRak'])) {
            $row['rack_name'] = mapRackCode($row['cRak']);
        }

        $statusVal = trim($row['cStatus'] ?? '');
        $row['cStatus'] = ($statusVal === '' || $statusVal === '-' || strtolower($statusVal) === 'null' || strtolower($statusVal) === 'undefined') ? 'OPEN' : $statusVal;

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

    if (isset($_GET['export']) && $_GET['export'] === 'excel') {
        if (ob_get_length()) ob_end_clean();

        header('Content-Type: application/vnd.ms-excel');
        header('Content-Disposition: attachment; filename="ProductionOrders.xls"');
        header('Pragma: no-cache');
        header('Expires: 0');

        echo "No. Order\tSC No.\tMC No.\tFlexo\tDC\tClient\tProduct\tTanggal OP\tDimensions\tUkuran Corr\tQuality\tQty Order\tQty Produksi\tNetto\tColor\tShipping Date\tType\tApproved By\tKeterangan Order\tOPEN/CLOSE\n";
        foreach ($data as $row) {
            $dimensions = "{$row['nPanjang']}x{$row['nLebar']}x{$row['nTinggi']}";
            $ukuranCorr = "{$row['nBrutto_usl']}x{$row['nBrutto_usp']}";
            $quality = "{$row['ckd_b1']} / {$row['ckd_b2']} / {$row['ckd_b3']} / {$row['ckd_b4']} / {$row['ckd_b5']}";
            $shippingDate = $row['lTK'] == '1' ? 'Masih Menunggu Kabar' : ($row['dTglkirim2'] ? substr($row['dTglkirim2'], 0, 10) : '');
            echo "{$row['cNoOp']}\t{$row['cNoSc']}\t{$row['cNoMc']}\t{$row['cFlexo']}\t{$row['cDC']}\t{$row['cnm_c']}\t{$row['cnm_brg']}\t" . ($row['dTgl'] ? substr($row['dTgl'], 0, 10) : '') . "\t{$dimensions}\t{$ukuranCorr}\t{$quality}\t{$row['nQty']}\t{$row['nQtyStok']}\t{$row['nTot_netto']}\t{$row['cWarna']}\t{$shippingDate}\t{$row['cTipe']}\t{$row['cMengetahui']}\t{$row['cKetOrder']}\t{$row['cStatus']}\n";
        }
        exit;
    }

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
    if (isset($stmt) && $stmt) {
        sqlsrv_free_stmt($stmt);
    }
    if (isset($countStmt) && $countStmt) {
        sqlsrv_free_stmt($countStmt);
    }
    if (isset($conn) && $conn) {
        sqlsrv_close($conn);
        
    }
}
?>