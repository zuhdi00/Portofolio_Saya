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
    "LoginTimeout" => 30,
    "Encrypt" => false,
    "TrustServerCertificate" => true,
    "ReturnDatesAsStrings" => true,
    "CharacterSet" => "UTF-8"
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
        'keyword1' => isset($_GET['keyword1']) ? trim($_GET['keyword1']) : '',
        'keyword2' => isset($_GET['keyword2']) ? trim($_GET['keyword2']) : '',
        'date_from' => isset($_GET['date_from']) ? trim($_GET['date_from']) : '',
        'date_to' => isset($_GET['date_to']) ? trim($_GET['date_to']) : '',
        'limit' => isset($_GET['limit']) ? intval($_GET['limit']) : 100,
        'offset' => isset($_GET['offset']) ? intval($_GET['offset']) : 0
    ];
    
    if ($searchParams['limit'] > 10000) $searchParams['limit'] = 10000;
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

    // Main query (PJS)
    $sql = "SELECT
                d.cKodeBahan,
                h.cNoPJS,
                h.ckodeDep AS dept,
                h.dTanggal,
                d.cNama AS nama_barang,
                d.cTglPakai,
                d.cUser AS user_ditujukan,
                d.cKetDtl AS cKeterangan,
                d.cUkuran AS ukuran,
                d.nQty AS jumlah,
                d.cSat AS satuan
            FROM tbPJSDtl d
            LEFT JOIN tbPJS h ON d.cNoPJS = h.cNoPJS
            WHERE 1=1";

    $whereConditions = [];
    $parameters = [];

    if (!empty($searchParams['search'])) {
        $whereConditions[] = "(
            h.cNoPJS LIKE ? OR
            h.ckodeDep LIKE ? OR
            d.cKodeBahan LIKE ? OR
            d.cNama LIKE ? OR
            d.cUser LIKE ? OR
            d.cKetDtl LIKE ? OR
            h.cKeterangan LIKE ?
        )";
        $searchTerm = "%{$searchParams['search']}%";
        $parameters = array_merge($parameters, [$searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm]);
    }

    if (!empty($searchParams['keyword1'])) {
        $whereConditions[] = "(
            h.ckodeDep LIKE ? OR
            d.cNama LIKE ? OR
            d.cUser LIKE ? OR
            d.cKetDtl LIKE ? OR
            h.cKeterangan LIKE ?
        )";
        $k1 = "%{$searchParams['keyword1']}%";
        $parameters = array_merge($parameters, [$k1, $k1, $k1, $k1, $k1]);
    }

    if (!empty($searchParams['keyword2'])) {
        $whereConditions[] = "(
            d.cUkuran LIKE ? OR
            d.cSat LIKE ? OR
            d.cNama LIKE ? OR
            d.cKetDtl LIKE ?
        )";
        $k2 = "%{$searchParams['keyword2']}%";
        $parameters = array_merge($parameters, [$k2, $k2, $k2, $k2]);
    }

    function sqlsrv_date_param($date, $endOfDay = false) {
        $dt = date_create($date);
        if (!$dt) return $date;
        if ($endOfDay) {
            $dt->setTime(23, 59, 59);
        }
        return $dt->format('Y-m-d H:i:s.000');
    }

    if (!empty($searchParams['date_from'])) {
        $whereConditions[] = "h.dTanggal >= ?";
        $parameters[] = sqlsrv_date_param($searchParams['date_from']);
    }

    if (!empty($searchParams['date_to'])) {
        $whereConditions[] = "h.dTanggal <= ?";
        $parameters[] = sqlsrv_date_param($searchParams['date_to'], true);
    }

    if (!empty($whereConditions)) {
        $sql .= " AND " . implode(" AND ", $whereConditions);
    }

    $sql = str_replace("SELECT", "SELECT TOP ({$searchParams['limit']})", $sql);
    $sql .= " ORDER BY h.dTanggal DESC, h.cNoPJS DESC";

    // Debug: persist constructed SQL and params for troubleshooting
    $debugFile = __DIR__ . '/debug_pjs_list_query.txt';
    @file_put_contents($debugFile, "[".date('c')."] Constructed SQL:\n" . $sql . "\nParameters: " . print_r($parameters, true) . "\n\n", FILE_APPEND);

    // If caller requests debug output, return SQL and params without executing
    if (isset($_GET['debug']) && $_GET['debug'] === '1') {
        if (ob_get_length()) ob_end_clean();
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'debug_sql' => $sql, 'parameters' => $parameters], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Count query (PJS)
    $countSql = "SELECT COUNT(*) as total 
                 FROM tbPJSDtl d
                 LEFT JOIN tbPJS h ON d.cNoPJS = h.cNoPJS
                 WHERE 1=1";
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
    $departments = [];
    $items = [];
    $minDate = null;
    $maxDate = null;

    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $recordCount++;

        // Convert numeric values
        $row['jumlah'] = $row['jumlah'] !== null ? floatval($row['jumlah']) : 0;

        // Handle dates
        $row['dTanggal'] = $row['dTanggal'] ?? null;
        
        // Track unique values for stats
        if (!empty($row['dept'])) {
            $departments[$row['dept']] = true;
        }
        if (!empty($row['cKodeBahan'])) {
            $items[$row['cKodeBahan']] = true;
        }
        
        // Track date range
        if ($row['dTanggal']) {
            if ($minDate === null || $row['dTanggal'] < $minDate) {
                $minDate = $row['dTanggal'];
            }
            if ($maxDate === null || $row['dTanggal'] > $maxDate) {
                $maxDate = $row['dTanggal'];
            }
        }

        // Trim string values
        foreach ($row as $key => $value) {
            if (is_string($value)) {
                $row[$key] = trim($value);
            } elseif ($value === null) {
                $row[$key] = '';
            }
        }

        // Normalize user field: some drivers return original column name (cUser)
        // Ensure `user_ditujukan` key exists for frontend and export
        if (!isset($row['user_ditujukan'])) {
            foreach ($row as $k => $v) {
                if (strcasecmp($k, 'cUser') === 0) {
                    $row['user_ditujukan'] = $v;
                    break;
                }
            }
            if (!isset($row['user_ditujukan'])) $row['user_ditujukan'] = '';
        }

        $data[] = $row;
    }

    $dateRangeStr = '-';
    if ($minDate && $maxDate) {
        $minDateFormatted = date('d/m/Y', strtotime($minDate));
        $maxDateFormatted = date('d/m/Y', strtotime($maxDate));
        if ($minDateFormatted === $maxDateFormatted) {
            $dateRangeStr = $minDateFormatted;
        } else {
            $dateRangeStr = $minDateFormatted . ' - ' . $maxDateFormatted;
        }
    }

    $stats = [
        'total_departments' => count($departments),
        'total_items' => count($items),
        'date_range' => $dateRangeStr
    ];

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

    // Excel export (BPB-style output)
    if (isset($_GET['export']) && $_GET['export'] === 'excel') {
        if (ob_get_length()) ob_end_clean();

        header('Content-Type: application/vnd.ms-excel');
        header('Content-Disposition: attachment; filename="PJS_Report_' . date('Y-m-d') . '.xls"');
        header('Pragma: no-cache');
        header('Expires: 0');

        echo "No PJS\tTanggal\tKode Bahan\tDepartemen\tNama Barang\tTgl Pakai\tUkuran\tJumlah\tSatuan\tUser Ditujukan\tKeterangan\n";
        foreach ($data as $row) {
            $jumlahVal = isset($row['jumlah']) && $row['jumlah'] !== '' ? $row['jumlah'] : '';
            $satuanVal = isset($row['satuan']) ? $row['satuan'] : '';
            $userVal = isset($row['user_ditujukan']) ? $row['user_ditujukan'] : '';
            echo "{$row['cNoPJS']}\t" . ($row['dTanggal'] ? substr($row['dTanggal'], 0, 10) : '') . "\t{$row['cKodeBahan']}\t{$row['dept']}\t{$row['nama_barang']}\t" . ($row['cTglPakai'] ? $row['cTglPakai'] : '') . "\t{$row['ukuran']}\t{$jumlahVal}\t{$satuanVal}\t{$userVal}\t{$row['cKeterangan']}\n";
        }
        exit;
    }

    $debugLogFile = __DIR__ . '/debug_pjs_list_query.txt';
    file_put_contents($debugLogFile, "Constructed Query: $sql\nParameters: " . print_r($parameters, true), FILE_APPEND);

    echo json_encode([
        'success' => true,
        'data' => $data,
        'stats' => $stats,
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
