<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header('Content-Type: application/json'); 

$serverName = "spsdmz";
$connectionOptions = array(
    "Database" => "dbSopanusa",
    "Uid" => "sa",
    "PWD" => "supracor",
    "LoginTimeout" => 15,
    "Encrypt" => false,
    "TrustServerCertificate" => true
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

try {
    $conn = sqlsrv_connect($serverName, $connectionOptions);

    if (!$conn) {
        error_log("Connection failed: " . print_r(sqlsrv_errors(), true));
        throw new Exception("Database connection failed. Please try again later.");
    }

    $searchParams = [
        'search' => isset($_GET['search']) ? trim($_GET['search']) : '',
        'mc' => isset($_GET['mc']) ? trim($_GET['mc']) : '',
        'client' => isset($_GET['client']) ? trim($_GET['client']) : '',
        'product' => isset($_GET['product']) ? trim($_GET['product']) : '',
        'order_no' => isset($_GET['order_no']) ? trim($_GET['order_no']) : '',
        'color' => isset($_GET['color']) ? trim($_GET['color']) : '',
        'date_from' => isset($_GET['date_from']) ? trim($_GET['date_from']) : '',
        'date_to' => isset($_GET['date_to']) ? trim($_GET['date_to']) : '',
        'limit' => isset($_GET['limit']) ? intval($_GET['limit']) : 500,
        'offset' => isset($_GET['offset']) ? intval($_GET['offset']) : 0
    ];
    
    if ($searchParams['limit'] > 1000) $searchParams['limit'] = 1000;
    if ($searchParams['limit'] < 1) $searchParams['limit'] = 500;
    if ($searchParams['offset'] < 0) $searchParams['offset'] = 0;

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
                op.nRm
            FROM tbOP op
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
            op.cMengetahui LIKE ?
        )";
        $searchTerm = "%{$searchParams['search']}%";
        $parameters = array_merge($parameters, [$searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm]);
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

    if (!empty($whereConditions)) {
        $sql .= " AND " . implode(" AND ", $whereConditions);
    }

    $sql .= " ORDER BY op.dTgl DESC, op.cNoMc DESC";
    $sql .= " OFFSET ? ROWS FETCH NEXT ? ROWS ONLY";
    $parameters[] = $searchParams['offset'];
    $parameters[] = $searchParams['limit'];

    $countSql = "SELECT COUNT(*) as total FROM tbOP op WHERE 1=1";
    if (!empty($whereConditions)) {
        $countSql .= " AND " . implode(" AND ", $whereConditions);
    }

    $countParams = array_slice($parameters, 0, -2);
    $countStmt = sqlsrv_prepare($conn, $countSql, $countParams);
    
    if ($countStmt === false) {
        error_log("Count SQL prepare failed: " . print_r(sqlsrv_errors(), true));
        throw new Exception("Failed to prepare count query.");
    }

    if (!sqlsrv_execute($countStmt)) {
        error_log("Count SQL execute failed: " . print_r(sqlsrv_errors(), true));
        throw new Exception("Failed to execute count query.");
    }

    $countRow = sqlsrv_fetch_array($countStmt, SQLSRV_FETCH_ASSOC);
    $totalRecords = $countRow['total'];

    $stmt = sqlsrv_prepare($conn, $sql, $parameters);

    if ($stmt === false) {
        error_log("SQL prepare failed: " . print_r(sqlsrv_errors(), true));
        throw new Exception("Failed to prepare query. Please contact support.");
    }

    if (!sqlsrv_execute($stmt)) {
        error_log("SQL execute failed: " . print_r(sqlsrv_errors(), true));
        throw new Exception("Failed to execute query. Please contact support.");
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

        if ($row['dTgl'] && is_object($row['dTgl'])) {
            $row['dTgl'] = $row['dTgl']->format('Y-m-d H:i:s');
        } elseif ($row['dTgl']) {
            $row['dTgl'] = $row['dTgl'];
        } else {
            $row['dTgl'] = null;
        }

        if ($row['dTglkirim'] && is_object($row['dTglkirim'])) {
            $row['dTglkirim'] = $row['dTglkirim']->format('Y-m-d H:i:s');
        } elseif ($row['dTglkirim']) {
            $row['dTglkirim'] = $row['dTglkirim'];
        } else {
            $row['dTglkirim'] = null;
        }

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

        $data[] = $row;
    }

    $activeFilters = [];
    foreach ($searchParams as $key => $value) {
        if (!empty($value) && !in_array($key, ['limit', 'offset'])) {
            $activeFilters[] = "$key: '$value'";
        }
    }

    $responseMessage = "Data loaded successfully";
    if (!empty($activeFilters)) {
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
    ]); 
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => $e->getMessage(),
        'timestamp' => date('Y-m-d H:i:s'),
        'search_params' => isset($searchParams) ? $searchParams : []
    ]);
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