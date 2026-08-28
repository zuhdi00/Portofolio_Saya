<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header('Content-Type: application/json'); 

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit(0);
}

$serverName = "spsdmz2";
$connectionOptions = array(
    "Database" => "dbSopanusa",
    "Uid" => "sa",
    "PWD" => "supracor",
    "LoginTimeout" => 30, // Increased timeout
    "Encrypt" => false,
    "TrustServerCertificate" => true,
    "ReturnDatesAsStrings" => true, // Handle dates as strings
    "CharacterSet" => "UTF-8"
);

// Function to map rack codes to names
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

// Function to test database connection
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
                'Verify server name and port (try IP address)',
                'Check database name exists',
                'Verify username and password',
                'Check network connectivity',
                'Ensure SQL Server allows TCP/IP connections'
            ]
        ];
    }
    
    sqlsrv_close($conn);
    return ['success' => true];
}

try {
    $connectionTest = testConnection($serverName, $connectionOptions);
    
    if (!$connectionTest['success']) {
        throw new Exception("Database connection failed. Details: " . json_encode($connectionTest['errors']) . ". Please check: " . implode(', ', $connectionTest['suggestions']));
    }
    
    $conn = sqlsrv_connect($serverName, $connectionOptions);

    if (!$conn) {
        $errors = sqlsrv_errors();
        error_log("Connection failed: " . print_r($errors, true));
        throw new Exception("Database connection failed. Please check server configuration.");
    }

    $searchMc = isset($_GET['mc']) ? trim($_GET['mc']) : '';
    $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 100;
    
    if ($limit > 1000) $limit = 1000;
    if ($limit < 1) $limit = 100;

    $sql = "SELECT TOP ($limit) 
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
            WHERE op.cNoMc IS NOT NULL";

    $params = [];

    if (!empty($searchMc)) {
        $sql .= " AND op.cNoMc LIKE ?";
        $params[] = "%$searchMc%";
    }

    $sql .= " ORDER BY op.dTgl DESC, op.cNoMc DESC";

    $stmt = sqlsrv_prepare($conn, $sql, $params);

    if ($stmt === false) {
        $errors = sqlsrv_errors();
        error_log("SQL prepare failed: " . print_r($errors, true));
        throw new Exception("Failed to prepare query. SQL Error: " . $errors[0]['message']);
    }

    if (!sqlsrv_execute($stmt)) {
        $errors = sqlsrv_errors();
        error_log("SQL execute failed: " . print_r($errors, true));
        throw new Exception("Failed to execute query. SQL Error: " . $errors[0]['message']);
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
        $row['dTglkirim'] = $row['dTglkirim'] ?? null;

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

    $responseMessage = "Data loaded successfully";
    if (!empty($searchMc)) {
        $responseMessage .= " (filtered by MC: '$searchMc')";
    }

    echo json_encode([
        'success' => true,
        'data' => $data,
        'count' => $recordCount,
        'search_mc' => $searchMc,
        'limit' => $limit,
        'message' => $responseMessage,
        'timestamp' => date('Y-m-d H:i:s')
    ], JSON_UNESCAPED_UNICODE); 
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => $e->getMessage(),
        'timestamp' => date('Y-m-d H:i:s'),
        'search_mc' => isset($searchMc) ? $searchMc : '',
        'limit' => isset($limit) ? $limit : 100,
        'debug_info' => [
            'server' => $serverName,
            'database' => $connectionOptions['Database'],
            'user' => $connectionOptions['Uid']
        ]
    ], JSON_UNESCAPED_UNICODE);
} finally {
    if (isset($stmt) && $stmt) {
        sqlsrv_free_stmt($stmt);
    }
    if (isset($conn) && $conn) {
        sqlsrv_close($conn);
    }
}
?>