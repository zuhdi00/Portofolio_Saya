<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

@ini_set('display_errors', 0);
ob_start();

function sendJson($data, $code = 200) {
    while (ob_get_level()) ob_end_clean();
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

$from  = trim($_POST['from']  ?? '');
$to    = trim($_POST['to']    ?? '');
$showAll = isset($_POST['all']) && trim($_POST['all']) === '1';

$serverName = "spsdmz2";
$connectionOptions = [
    "Database"             => "dbSopanusa",
    "Uid"                  => "sa",
    "PWD"                  => "supracor",
    "LoginTimeout"         => 15,
    "Encrypt"              => false,
    "TrustServerCertificate" => true
];

$conn = @sqlsrv_connect($serverName, $connectionOptions);
if (!$conn) {
    $err = sqlsrv_errors();
    $msg = $err ? $err[0]['message'] : 'Koneksi DB gagal';
    sendJson(['success' => false, 'message' => 'Koneksi DB gagal', 'detail' => $msg], 503);
}

// --- Kuota (kg → ton) from tbSettingFitur, fallback to tbSetting ---
$quotaKg = 0;
$qStmt = sqlsrv_query($conn, "SELECT TOP 1 Nilai FROM tbSettingFitur");
if ($qStmt !== false) {
    $qr = sqlsrv_fetch_array($qStmt, SQLSRV_FETCH_ASSOC);
    $quotaKg = isset($qr['Nilai']) ? floatval($qr['Nilai']) : 0;
    sqlsrv_free_stmt($qStmt);
} else {
    $qStmt2 = sqlsrv_query($conn, "SELECT TOP 1 Nilai FROM tbSetting");
    if ($qStmt2 !== false) {
        $qr2 = sqlsrv_fetch_array($qStmt2, SQLSRV_FETCH_ASSOC);
        $quotaKg = isset($qr2['Nilai']) ? floatval($qr2['Nilai']) : 0;
        sqlsrv_free_stmt($qStmt2);
    }
}
$quotaTon = $quotaKg / 1000.0;

// --- Date filter ---
$whereDate = "";
$params    = [];
if (!$showAll) {
    if ($from !== '' && $to !== '') {
        $whereDate = "AND CONVERT(date, t.Dtgl) BETWEEN CONVERT(date, ?) AND CONVERT(date, ?)";
        $params[]  = $from;
        $params[]  = $to;
    } elseif ($from !== '') {
        $whereDate = "AND CONVERT(date, t.Dtgl) >= CONVERT(date, ?)";
        $params[]  = $from;
    } elseif ($to !== '') {
        $whereDate = "AND CONVERT(date, t.Dtgl) <= CONVERT(date, ?)";
        $params[]  = $to;
    }
}

// --- Main query: total order per day (kg → ton) ---
$sql = "
    SELECT
        FORMAT(t.Dtgl, 'yyyy-MM-dd') AS date,
        CAST(ISNULL(SUM(CAST(t.nTot_brutto AS FLOAT) * CAST(t.nQty AS FLOAT)), 0) AS FLOAT) AS total_order_kg
    FROM tbTSC t
    WHERE 1=1 {$whereDate}
    GROUP BY FORMAT(t.Dtgl, 'yyyy-MM-dd'), CONVERT(date, t.Dtgl)
    ORDER BY CONVERT(date, t.Dtgl) DESC
";

$stmt = sqlsrv_query($conn, $sql, $params, ["QueryTimeout" => 60]);
if ($stmt === false) {
    $err = sqlsrv_errors();
    sqlsrv_close($conn);
    sendJson(['success' => false, 'message' => 'Gagal query data', 'errors' => $err], 500);
}

$rows          = [];
$grandTotalKg  = 0;

while ($r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $totalOrderKg  = floatval($r['total_order_kg']);
    $totalOrderTon = $totalOrderKg / 1000.0;
    $penambahan    = $quotaTon - $totalOrderTon;
    $totalAkhir    = $quotaTon; // kuota is the ceiling

    $grandTotalKg += $totalOrderKg;

    $rows[] = [
        'date'            => $r['date'],
        'kuota_ton'       => round($quotaTon, 2),
        'total_order_ton' => round($totalOrderTon, 2),
        'penambahan_ton'  => round($penambahan, 2),
        'total_akhir_ton' => round($totalAkhir, 2),
    ];
}

sqlsrv_free_stmt($stmt);
sqlsrv_close($conn);

$grandTotalTon = $grandTotalKg / 1000.0;
$avgOrderTon   = count($rows) > 0 ? $grandTotalTon / count($rows) : 0;

sendJson([
    'success'       => true,
    'rows'          => $rows,
    'quota_kg'      => $quotaKg,
    'quota_ton'     => round($quotaTon, 2),
    'summary'       => [
        'total_days'       => count($rows),
        'grand_total_ton'  => round($grandTotalTon, 2),
        'avg_order_ton'    => round($avgOrderTon, 2),
    ]
]);
?>
