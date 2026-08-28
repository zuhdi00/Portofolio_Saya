<?php
// filepath: c:\xampp\htdocs\get_data_list_mc.php
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

try {
    $cNoMc = isset($_GET['cNoMc']) ? trim($_GET['cNoMc']) : '';
    $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 20;
    if ($limit < 1) $limit = 20;
    if ($limit > 1000) $limit = 1000;

    $conn = sqlsrv_connect($serverName, $connectionOptions);
    if (!$conn) {
        $errors = sqlsrv_errors();
        throw new Exception("Database connection failed: " . getConnectionError($errors));
    }

    $sql = "SELECT TOP ($limit)
        cNoMc, cNoTSC, Dtgl, ckd_c, cnm_c, cAdd_c, ckd_Brg, cnm_brg, nTot_netto, cTipe, nPanjang, nLebar, nTinggi, cWarna
        FROM tbMc
        WHERE 1=1";
    $params = [];

    if ($cNoMc !== '') {
        $sql .= " AND cNoMc = ?";
        $params[] = $cNoMc;
    }

    $sql .= " ORDER BY Dtgl DESC, cNoMc DESC";

    $stmt = sqlsrv_prepare($conn, $sql, $params);
    if ($stmt === false) {
        $errors = sqlsrv_errors();
        throw new Exception("Failed to prepare query: " . getConnectionError($errors));
    }

    if (!sqlsrv_execute($stmt)) {
        $errors = sqlsrv_errors();
        throw new Exception("Failed to execute query: " . getConnectionError($errors));
    }

    $data = [];
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        foreach ($row as $key => $value) {
            if (is_string($value)) $row[$key] = trim($value);
            elseif ($value === null) $row[$key] = '';
        }
        $data[] = $row;
    }

    echo json_encode([
        'success' => true,
        'data' => $data,
        'message' => 'Data loaded successfully',
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
    if (isset($stmt) && $stmt) sqlsrv_free_stmt($stmt);
    if (isset($conn) && $conn) sqlsrv_close($conn);
}
?>