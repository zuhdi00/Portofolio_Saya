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
        die(json_encode([
            'success' => false,
            'message' => 'Database connection failed: ' . json_encode($errors),
            'timestamp' => date('Y-m-d H:i:s')
        ]));
    }


    $sql = "SELECT 
        d.cNoid,
        d.cKodeBahan,
        d.cNoBPJ,
        h.cKodeSup,
        h.cNama AS supplier_name,
        h.dTanggal,
        h.cKeterangan AS header_keterangan,
        d.cNama AS nama_barang,
        d.cNote AS cNote,
        d.cUkuran AS ukuran,
        d.nQtyK AS jumlah,
        d.cSatK AS satuan,
        d.lNstock,
        d.cNoPP
    FROM tbBPJSpDtl d
    INNER JOIN tbBPJSp h ON d.cNoBPJ = h.cNoBPJ
    WHERE 1=1";

    $whereConditions = [];
    $parameters = [];

    // General search
    if (!empty($searchParams['search'])) {
        $whereConditions[] = "(
            h.cNoBPJ LIKE ? OR
            d.cKodeBahan LIKE ? OR
            d.cNama LIKE ? OR
            d.cNote LIKE ? OR
            h.cKeterangan LIKE ? OR
            d.cNoPP LIKE ? OR
            h.cNama LIKE ?
        )";
        $searchTerm = "%{$searchParams['search']}%";
        $parameters = array_merge($parameters, [$searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm]);
    }


    if (!empty($searchParams['keyword1'])) {
        $whereConditions[] = "(
            h.cKodeSup LIKE ? OR
            d.cNoPP LIKE ? OR
            h.cNama LIKE ?
        )";
        $k1 = "%{$searchParams['keyword1']}%";
        $parameters = array_merge($parameters, [$k1, $k1, $k1]);
    }


    if (!empty($searchParams['keyword2'])) {
        $whereConditions[] = "(
            d.cUkuran LIKE ? OR
            d.cNote LIKE ? OR
            d.cNama LIKE ? OR
            h.cKeterangan LIKE ?
        )";
        $k2 = "%{$searchParams['keyword2']}%";
        $parameters = array_merge($parameters, [$k2, $k2, $k2, $k2]);
    }


    function sqlsrv_date_param_bpj($date, $endOfDay = false) {
        $dt = date_create($date);
        if (!$dt) return $date;
        if ($endOfDay) {
            $dt->setTime(23, 59, 59);
        }
        return $dt->format('Y-m-d H:i:s.000');
    }

    if (!empty($searchParams['date_from'])) {
        $whereConditions[] = "h.dTanggal >= ?";
        $parameters[] = sqlsrv_date_param_bpj($searchParams['date_from']);
    }

    if (!empty($searchParams['date_to'])) {
        $whereConditions[] = "h.dTanggal <= ?";
        $parameters[] = sqlsrv_date_param_bpj($searchParams['date_to'], true);
    }

    if (!empty($whereConditions)) {
        $sql .= " AND " . implode(" AND ", $whereConditions);
    }

    $sql .= " ORDER BY h.dTanggal DESC, d.cNoBPJ DESC";
    $sql .= " OFFSET {$searchParams['offset']} ROWS FETCH NEXT {$searchParams['limit']} ROWS ONLY";

    // Count query - Same exact WHERE conditions, just different SELECT
    $countSql = "SELECT COUNT(*) as total_records
                 FROM tbBPJSpDtl d
                 LEFT JOIN tbBPJSp h ON d.cNoBPJ = h.cNoBPJ
                 WHERE 1=1";
    if (!empty($whereConditions)) {
        $countSql .= " AND " . implode(" AND ", $whereConditions);
    }

    $countStmt = sqlsrv_prepare($conn, $countSql, $parameters);
    if ($countStmt === false) {
        $errors = sqlsrv_errors();
        $debugLogFile = __DIR__ . '/debug_bpj_error.txt';
        file_put_contents($debugLogFile, "=== COUNT QUERY ERROR ===\n" . date('Y-m-d H:i:s') . "\nQuery: $countSql\nParameters: " . print_r($parameters, true) . "\nErrors: " . print_r($errors, true) . "\n\n", FILE_APPEND);
        throw new Exception("Failed to prepare count query: " . getConnectionError($errors));
    }
    if (!sqlsrv_execute($countStmt)) {
        $errors = sqlsrv_errors();
        $debugLogFile = __DIR__ . '/debug_bpj_error.txt';
        file_put_contents($debugLogFile, "=== COUNT EXECUTE ERROR ===\n" . date('Y-m-d H:i:s') . "\nQuery: $countSql\nParameters: " . print_r($parameters, true) . "\nErrors: " . print_r($errors, true) . "\n\n", FILE_APPEND);
        die(json_encode([
            'success' => false,
            'message' => 'Failed to execute count query: ' . json_encode($errors),
            'timestamp' => date('Y-m-d H:i:s'),
            'debug_query' => $countSql
        ]));
    }

    $countRow = sqlsrv_fetch_array($countStmt, SQLSRV_FETCH_ASSOC);
    $totalRecords = $countRow['total_records'] ?? 0;

    // Prepare and execute main query
    $stmt = sqlsrv_prepare($conn, $sql, $parameters);
    if ($stmt === false) {
        $errors = sqlsrv_errors();
        $debugLogFile = __DIR__ . '/debug_bpj_error.txt';
        file_put_contents($debugLogFile, "=== MAIN QUERY PREPARE ERROR ===\n" . date('Y-m-d H:i:s') . "\nQuery: $sql\nParameters: " . print_r($parameters, true) . "\nErrors: " . print_r($errors, true) . "\n\n", FILE_APPEND);
        throw new Exception("Failed to prepare main query: " . getConnectionError($errors));
    }
    if (!sqlsrv_execute($stmt)) {
        $errors = sqlsrv_errors();
        $debugLogFile = __DIR__ . '/debug_bpj_error.txt';
        file_put_contents($debugLogFile, "=== MAIN QUERY EXECUTE ERROR ===\n" . date('Y-m-d H:i:s') . "\nQuery: $sql\nParameters: " . print_r($parameters, true) . "\nErrors: " . print_r($errors, true) . "\n\n", FILE_APPEND);
        die(json_encode([
            'success' => false,
            'message' => 'Failed to execute main query: ' . json_encode($errors),
            'timestamp' => date('Y-m-d H:i:s'),
            'debug_query' => $sql
        ]));
    }

    $data = [];
    $recordCount = 0;
    $suppliers = [];
    $items = [];
    $minDate = null;
    $maxDate = null;
    $header_keterangan_map = null;

    // parse header keterangan like "1-2 U/ F.GLUE II 3-4 U/ DOWN STECKER CORR II"
    function parse_keterangan_ranges($text) {
        $map = [];
        if (!$text) return $map;
        $s = preg_replace('/\s+/', ' ', trim($text));

        // Match patterns: number or range followed by description until next number/range
        preg_match_all('/\b(\d+(?:-\d+)?)\b\s*([^\d]+)/', $s, $matches, PREG_SET_ORDER);
        foreach ($matches as $m) {
            $range = $m[1];
            $desc = trim($m[2]);
            if ($desc === '') continue;
            if (strpos($range, '-') !== false) {
                list($a, $b) = explode('-', $range, 2);
            } else { $a = $b = $range; }
            $start = intval($a);
            $end = intval($b);
            if ($start > $end) { $tmp = $start; $start = $end; $end = $tmp; }
            for ($i = $start; $i <= $end; $i++) {
                // map numeric index to description
                $map[$i] = $desc;
            }
        }
        return $map;
    }

    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $recordCount++;

        // Convert numeric values
        $row['jumlah'] = $row['jumlah'] !== null ? floatval($row['jumlah']) : 0;

        // Handle dates
        $row['dTanggal'] = $row['dTanggal'] ?? null;
        
        // Track unique values for stats
        if (!empty($row['supplier_name'])) {
            $suppliers[$row['supplier_name']] = true;
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

        // Build header keterangan map once (same header applies to all detail rows)
        if ($header_keterangan_map === null) {
            $header_keterangan_map = [];
            if (!empty($row['header_keterangan'])) {
                $header_keterangan_map = parse_keterangan_ranges($row['header_keterangan']);
            }
        }

        // If there is a mapping for this detail's cNoid, use it as cKeterangan
        $noidInt = isset($row['cNoid']) ? intval(preg_replace('/[^0-9]/','', $row['cNoid'])) : 0;
        if ($noidInt && isset($header_keterangan_map[$noidInt]) && $header_keterangan_map[$noidInt] !== '') {
            $row['cKeterangan'] = $header_keterangan_map[$noidInt];
        } else {
            // fall back to header_keterangan if detail cKeterangan is empty
            if (empty($row['cKeterangan']) && !empty($row['header_keterangan'])) {
                $row['cKeterangan'] = $row['header_keterangan'];
            }
        }

        // Combine jumlah and satuan
        $formattedQty = '';
        if ($row['jumlah'] !== '' && $row['jumlah'] !== null) {
            $num = $row['jumlah'];
            if (is_numeric($num)) {
                if (floor($num) == $num) {
                    $formattedQty = (string)intval($num);
                } else {
                    $formattedQty = rtrim(rtrim(number_format($num, 2, '.', ''), '0'), '.');
                }
            } else {
                $formattedQty = (string)$num;
            }
        }
        $satuanVal = isset($row['satuan']) ? trim($row['satuan']) : '';
        $row['jumlah_satuan'] = $formattedQty !== '' ? trim($formattedQty . ($satuanVal !== '' ? ' ' . $satuanVal : '')) : ($satuanVal !== '' ? $satuanVal : '');

        // Map supplier key
        $row['supplier'] = $row['supplier_name'] ?? '';
        
        // cNoPP is now fetched directly from tbBPJSpDtl

        $data[] = $row;
    }

    // Format date range
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
        'total_suppliers' => count($suppliers),
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
        header('Content-Disposition: attachment; filename="BPJ_Report_' . date('Y-m-d') . '.xls"');
        header('Pragma: no-cache');
        header('Expires: 0');

        echo "No BPJ\tPJS\tTanggal\tKode Bahan\tNama Barang\tUkuran\tJumlah\tSatuan\tNote\tKeterangan\tSupplier\n";
        foreach ($data as $row) {
            $jumlahVal = isset($row['jumlah']) && $row['jumlah'] !== '' ? $row['jumlah'] : '';
            $satuanVal = isset($row['satuan']) ? $row['satuan'] : '';
            $keteranganVal = isset($row['cKeterangan']) ? $row['cKeterangan'] : '';
            echo "{$row['cNoBPJ']}\t{$row['cNoPP']}\t" . ($row['dTanggal'] ? substr($row['dTanggal'], 0, 10) : '') . "\t{$row['cKodeBahan']}\t{$row['nama_barang']}\t{$row['ukuran']}\t{$jumlahVal}\t{$satuanVal}\t{$row['cNote']}\t{$keteranganVal}\t{$row['supplier']}\n";
        }
        exit;
    }

    // Debug log with detailed info
    $debugLogFile = __DIR__ . '/debug_bpj_list_query.txt';
    $debugInfo = "=== SUCCESS ===\n" . date('Y-m-d H:i:s') . 
        "\nTotal Records in DB: $totalRecords\nRecords returned: " . count($data) . 
        "\nOffset: {$searchParams['offset']}, Limit: {$searchParams['limit']}" .
        "\nMain Query: $sql\nCount Query: $countSql\nParameters: " . print_r($parameters, true) . "\n\n";
    file_put_contents($debugLogFile, $debugInfo, FILE_APPEND);

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