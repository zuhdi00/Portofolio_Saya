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

$from    = trim($_POST['from']  ?? '');
$to      = trim($_POST['to']    ?? '');
$showAll = isset($_POST['all']) && trim($_POST['all']) === '1';

$serverName = "spsdmz2";
$connectionOptions = [
    "Database"               => "dbSopanusa",
    "Uid"                    => "sa",
    "PWD"                    => "supracor",
    "LoginTimeout"           => 15,
    "Encrypt"                => false,
    "TrustServerCertificate" => true
];

$conn = @sqlsrv_connect($serverName, $connectionOptions);
if (!$conn) {
    $err = sqlsrv_errors();
    $msg = $err ? $err[0]['message'] : 'Koneksi DB gagal';
    sendJson(['success' => false, 'message' => 'Koneksi DB gagal', 'detail' => $msg], 503);
}

// ─── KUOTA: ambil dari tbSettingFitur WHERE KodeSetting = 'NilaiMaxOrderPerHari' ───
$quotaKg = 0;
$qStmt = sqlsrv_query($conn, "SELECT TOP 1 Nilai FROM tbSettingFitur WHERE KodeSetting = 'NilaiMaxOrderPerHari'");
if ($qStmt !== false) {
    $qr = sqlsrv_fetch_array($qStmt, SQLSRV_FETCH_ASSOC);
    $quotaKg = isset($qr['Nilai']) ? floatval($qr['Nilai']) : 0;
    sqlsrv_free_stmt($qStmt);
}

$quotaTon = $quotaKg / 1000.0;

// ─── DATE FILTER ───────────────────────────────────────────────────────────────
$whereDate = "";
$params    = [];
if (!$showAll) {
    if ($from !== '' && $to !== '') {
        // Tambahkan 1 hari ke $to agar tanggal $to ikut masuk (< hari berikutnya)
        $toNext = date('Y-m-d', strtotime($to . ' +1 day'));
        $whereDate = "AND CONVERT(date, t.Dtgl) >= CONVERT(date, ?) AND CONVERT(date, t.Dtgl) < CONVERT(date, ?)";
        $params[] = $from;
        $params[] = $toNext;
    } elseif ($from !== '') {
        $whereDate = "AND CONVERT(date, t.Dtgl) >= CONVERT(date, ?)";
        $params[]  = $from;
    } elseif ($to !== '') {
        $toNext = date('Y-m-d', strtotime($to . ' +1 day'));
        $whereDate = "AND CONVERT(date, t.Dtgl) < CONVERT(date, ?)";
        $params[]  = $toNext;
    }
}

// ─── MAIN QUERY ────────────────────────────────────────────────────────────────
// GROUP BY CONVERT(date, Dtgl) saja — hindari double grouping dengan FORMAT yang kadang beda hasil
$sql = "
    SELECT
        CONVERT(varchar(10), CONVERT(date, t.Dtgl), 23) AS date,
        CAST(ISNULL(SUM(
            CAST(ISNULL(t.nTot_brutto, 0) AS FLOAT) * CAST(ISNULL(t.nQty, 0) AS FLOAT)
        ), 0) AS FLOAT) AS total_order_kg
    FROM tbTSC t
    WHERE 1=1 {$whereDate}
    GROUP BY CONVERT(date, t.Dtgl)
    ORDER BY CONVERT(date, t.Dtgl) DESC
";

$stmt = sqlsrv_query($conn, $sql, $params, ["QueryTimeout" => 60]);
if ($stmt === false) {
    $err = sqlsrv_errors();
    sqlsrv_close($conn);
    sendJson(['success' => false, 'message' => 'Gagal query data', 'errors' => $err, 'sql' => $sql], 500);
}

$rows         = [];
$grandTotalKg = 0;

while ($r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $totalOrderKg  = floatval($r['total_order_kg']);
    $totalOrderTon = $totalOrderKg / 1000.0;
    $penambahan    = $quotaTon - $totalOrderTon;
    $totalAkhir    = $quotaTon; // Total akhir = kuota (batas atas)

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
        'total_days'      => count($rows),
        'grand_total_ton' => round($grandTotalTon, 2),
        'avg_order_ton'   => round($avgOrderTon, 2),
    ]
]);
?>
