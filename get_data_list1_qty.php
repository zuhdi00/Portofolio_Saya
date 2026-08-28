
<?php
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

try {
    $conn = sqlsrv_connect($serverName, $connectionOptions);
    if (!$conn) throw new Exception("Connection failed: " . getConnectionError(sqlsrv_errors()));

    $cNoOp = $_GET['cNoOp'] ?? '';
    if (empty($cNoOp)) throw new Exception("Parameter 'cNoOp' is required");

    $sql = "
        SELECT 
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
            bj.nQty AS stbQty,
            bj.cNoSTB,
            ISNULL(srj.total_srj, 0) AS nQtySrj
        FROM tbOP op
        LEFT JOIN tbStbBJ bj ON op.cNoOp = bj.cNoOp
        LEFT JOIN (
            SELECT cNoSc, SUM(nQtySrj) AS total_srj
            FROM TbTmpSrjPerTgl
            GROUP BY cNoSc
        ) srj ON bj.cNoSc = srj.cNoSc
        WHERE op.cNoOp = ?";

    $stmt = sqlsrv_query($conn, $sql, [$cNoOp]);
    if (!$stmt) throw new Exception("Query error: " . getConnectionError(sqlsrv_errors()));

    $rows = [];
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        foreach ($row as $k => $v) {
            if ($v instanceof DateTime) $row[$k] = $v->format('Y-m-d');
            elseif (is_null($v)) $row[$k] = '';
            elseif (is_numeric($v)) $row[$k] = floatval($v);
        }
        $row['qty_sekarang'] = floatval($row['stbQty']) - floatval($row['nQtySrj']);
        $rows[] = $row;
    }

    echo json_encode([
        'success' => true,
        'data' => $rows,
        'count' => count($rows),
        'timestamp' => date('Y-m-d H:i:s')
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'timestamp' => date('Y-m-d H:i:s')
    ]);
} finally {
    if (isset($stmt) && $stmt) sqlsrv_free_stmt($stmt);
    if (isset($conn) && $conn) sqlsrv_close($conn);
}
?>
