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
        'limit' => isset($_GET['limit']) ? intval($_GET['limit']) : 200,
        'offset' => isset($_GET['offset']) ? intval($_GET['offset']) : 0
    ];
    
    // For normal requests keep a reasonable cap to avoid heavy pulls; allow large limits for Excel export
    if (!isset($_GET['export']) || $_GET['export'] !== 'excel') {
        if ($searchParams['limit'] > 10000) $searchParams['limit'] = 10000;
    }
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

    // Make the default result lightweight to avoid heavy joins; add ?expand=1 to include stb/srj/corr details
    $expand = isset($_GET['expand']) && $_GET['expand'] === '1';

    $selectCore = "op.cnm_c, op.cNoMc, op.cNoOp, op.cnm_brg,
                op.nPanjang, op.nLebar, op.nTinggi,
                op.nQty, op.dTgl, op.cWarna,
                op.nTot_netto, op.cLayer, op.cTipe, op.cFlexo,
                op.userdate, op.dTglkirim2, op.cNoSc, op.cMengetahui,
                op.nRm, op.ckd_b1, op.ckd_b2, op.ckd_b3,
                op.ckd_b4, op.ckd_b5, op.nQtyStok, op.lTK,
                op.cKetOrder, op.cDC, srj.dTanggal AS srj_dTanggal";

    $selectExtra = "";
    $joinExtra = "";
    if ($expand) {
        $selectExtra = ", stb.cNoSTB AS stb_cNoSTB, stb.cJns AS stb_cJns, stb.cType AS stb_cType, stb.cNamabrg AS stb_cNamabrg,
                stb.cNoMC AS stb_cNoMC, stb.cKodeCust AS stb_cKodeCust, stb.cNama AS stb_cNamaCustomer,
                stb.nPanjang AS stb_nPanjang, stb.nLebar AS stb_nLebar, stb.nTinggi AS stb_nTinggi, stb.cWarna AS stb_cWarna,
                stb.cJnsGel AS stb_cJnsGel, stb.cSub1 AS stb_cSub1, stb.cSub2 AS stb_cSub2, stb.cSub3 AS stb_cSub3,
                stb.cSub4 AS stb_cSub4, stb.cSub5 AS stb_cSub5, stb.nQtyPalet AS stb_nQtyPalet, stb.nQtyCol AS stb_nQtyCol,
                stb.nIsi AS stb_nIsi, stb.nQtyLbr AS stb_nQtyLbr, stb.nQtyKgFisik AS stb_nQtyKgFisik, stb.nQtyKgFormula AS stb_nQtyKgFormula,
                stb.dTglSerah AS stb_dTglSerah, stb.dTglkirim AS stb_dTglkirim, stb.cKdSales AS stb_cKdSales, stb.cNamaSales AS stb_cNamaSales,
                stb.nQty AS stb_nQty, stb.nQtyKg AS stb_nQtyKg, stb.nQtyOut AS stb_nQtyOut, stb.cRak AS stb_cRak, stb.cShift AS stb_cShift,
                stb.nberat AS stb_nberat, stb.nberat2 AS stb_nberat2, stb.nOrder AS stb_nOrder, stb.cKeterangan AS stb_cKeterangan,
                srj.cNoSRJ AS srj_cNoSRJ, srj.cCom AS srj_cCom, srj.cNoid AS srj_cNoid, srj.cNama AS srj_cNama,
                srj.nPanjang AS srj_nPanjang, srj.nLebar AS srj_nLebar, srj.nTinggi AS srj_nTinggi, srj.cSub1 AS srj_cSub1,
                srj.cSub2 AS srj_cSub2, srj.cSub3 AS srj_cSub3, srj.cSub4 AS srj_cSub4, srj.cSub5 AS srj_cSub5,
                srj.cJnsGelDtl AS srj_cJnsGelDtl, srj.nQty AS srj_nQty, srj.nIsi AS srj_nIsi, srj.cLayerDtl AS srj_cLayerDtl,
                srj.cTipe AS srj_cTipe, srj.nHarga AS srj_nHarga, srj.cTySrj AS srj_cTySrj, srj.cNoScDtl AS srj_cNoScDtl,
                srj.cNoBaru AS srj_cNoBaru, srj.nBrtOp AS srj_nBrtOp, srj.nTon AS srj_nTon, srj.cLine AS srj_cLine, srj.nWaste AS srj_nWaste,
                corr.nHasil AS corr_nHasil, corr.nRusak AS corr_nRusak, corr.nOut AS corr_nOut";

        $joinExtra = " LEFT JOIN tbStbBJ stb ON stb.cNoOp = op.cNoOp
               LEFT JOIN tbSRJDtl srj ON srj.cNoOp = op.cNoOp
               LEFT JOIN tbCorrDtl corr ON corr.cNoOp = op.cNoOp";
    }

    // Always join SRJ via detail table to allow filtering by tbSRJ.dTanggal (rbSRJDtl -> tbSRJ)
    $joinSrj = " LEFT JOIN rbSRJDtl rd ON rd.cNoOp = op.cNoOp LEFT JOIN tbSRJ srj ON srj.cNoSRJ = rd.cNoSRJ";

    $sql = "SELECT " . $selectCore . $selectExtra . " FROM tbOP op " . $joinExtra . $joinSrj . " WHERE 1=1";

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

    // Ensure shipping_date_from and shipping_date_to are properly formatted
    if (!empty($searchParams['shipping_date_from'])) {
        // filter by SRJ.dTanggal (shipping date) via rbSRJDtl
        $whereConditions[] = "srj.dTanggal >= ?";
        $parameters[] = $searchParams['shipping_date_from'];
    }

    if (!empty($searchParams['shipping_date_to'])) {
        $whereConditions[] = "srj.dTanggal <= ?";
        $parameters[] = $searchParams['shipping_date_to'] . ' 23:59:59';
    }

    if (!empty($whereConditions)) {
        $sql .= " AND " . implode(" AND ", $whereConditions);
    }

    // Server-side pagination using ROW_NUMBER to support offset + limit efficiently
    $limit = (int)$searchParams['limit'];
    $offset = (int)$searchParams['offset'];
    $startRow = $offset + 1;
    $endRow = $offset + $limit;

    // Build count query to get total matching records
    // count should reflect same SRJ join when filtering by shipping date
    $countSql = "SELECT COUNT(*) as total FROM tbOP op " . $joinSrj . " WHERE op.cNoMc IS NOT NULL";
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

    // Use ROW_NUMBER for pagination; keep the original SELECT list but add row number
    // Order by SRJ date when present, otherwise fallback to op.dTglkirim2
    $orderClause = "ORDER BY ISNULL(srj.dTanggal, op.dTglkirim2) DESC, op.cNoOp DESC";

    $innerSql = preg_replace('/^\s*SELECT\s+/i', 'SELECT ', $sql); // ensure starts with SELECT
    // Wrap in a derived table with ROW_NUMBER
    $pagedSql = "SELECT * FROM (\n    SELECT ROW_NUMBER() OVER ({$orderClause}) AS rn, \n    " . substr($innerSql, 7) . "\n) t WHERE t.rn BETWEEN ? AND ?";

    // Prepare parameters: existing search params, then start and end row
    $pagedParams = $parameters;
    $pagedParams[] = $startRow;
    $pagedParams[] = $endRow;

    $stmt = sqlsrv_prepare($conn, $pagedSql, $pagedParams);
    if ($stmt === false) {
        $errors = sqlsrv_errors();
        throw new Exception("Failed to prepare paged query: " . getConnectionError($errors));
    }
    if (!sqlsrv_execute($stmt)) {
        $errors = sqlsrv_errors();
        throw new Exception("Failed to execute paged query: " . getConnectionError($errors));
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
        // Map rack code from tbStbBJ if available
        if (isset($row['stb_cRak'])) {
            $row['stb_rack_name'] = mapRackCode($row['stb_cRak']);
        }

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

    // Add debugging logs for SQL query and parameters
    error_log("SQL Query: " . $sql);
    error_log("Parameters: " . json_encode($parameters));
    // Log the final SQL query and parameters for debugging
    error_log("Final SQL Query: " . $sql);
    error_log("Final Parameters: " . json_encode($parameters));

    if (isset($_GET['export']) && $_GET['export'] === 'excel') {
        if (ob_get_length()) ob_end_clean();

        header('Content-Type: application/vnd.ms-excel');
        header('Content-Disposition: attachment; filename="ProductionOrders.xls"');
        header('Pragma: no-cache');
        header('Expires: 0');

        echo "No. Order\tSC No.\tMC No.\tFlexo\tDC\tClient\tProduct\tTanggal OP\tDimensions\tQuality\tQty Order\tQty Produksi\tNetto\tColor\tShipping Date\tType\tApproved By\tKeterangan Order\n";
        foreach ($data as $row) {
            $dimensions = "{$row['nPanjang']}x{$row['nLebar']}x{$row['nTinggi']}";
            $quality = "{$row['ckd_b1']} / {$row['ckd_b2']} / {$row['ckd_b3']} / {$row['ckd_b4']} / {$row['ckd_b5']}";
            // Prefer SRJ date when available, fallback to op.dTglkirim2
            $shippingDate = '';
            if (isset($row['lTK']) && $row['lTK'] == '1') {
                $shippingDate = 'Masih Menunggu Kabar';
            } else if (!empty($row['srj_dTanggal'])) {
                $shippingDate = substr($row['srj_dTanggal'], 0, 10);
            } else if (!empty($row['dTglkirim2'])) {
                $shippingDate = substr($row['dTglkirim2'], 0, 10);
            }
            echo "{$row['cNoOp']}\t{$row['cNoSc']}\t{$row['cNoMc']}\t{$row['cFlexo']}\t{$row['cDC']}\t{$row['cnm_c']}\t{$row['cnm_brg']}\t" . ($row['dTgl'] ? substr($row['dTgl'], 0, 10) : '') . "\t{$dimensions}\t{$quality}\t{$row['nQty']}\t{$row['nQtyStok']}\t{$row['nTot_netto']}\t{$row['cWarna']}\t{$shippingDate}\t{$row['cTipe']}\t{$row['cMengetahui']}\t{$row['cKetOrder']}\n";
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