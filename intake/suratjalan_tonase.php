<?php
/**
 * suratjalan_tonase.php
 * Endpoint sederhana untuk mengambil tonase (kg) berdasarkan Surat Jalan (SRJ)
 * dan detail barisnya, menggunakan logika tonase yang dipakai di VB:
 *   ton_per_baris = CASE WHEN cTipe LIKE 'SF%' AND nPanjang <= 0 THEN nQty
 *                        ELSE nQty * nBrtOp END
 *
 * Cara pakai:
 *  - GET params: date_from (YYYY-MM-DD), date_to (YYYY-MM-DD), srj_no, op_no,
 *    group_by ("op" atau "srj"), limit (integer, 0 = all)
 *  - Contoh: suratjalan_tonase.php?date_from=2025-06-01&date_to=2025-06-30&group_by=op
 *
 * NOTE: Silakan isi kredensial DB pada variabel $connectionOptions.
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Quick diagnostics for common failures
if (!function_exists('sqlsrv_connect')) {
    echo json_encode(['error' => 'SQLSRV_EXTENSION_MISSING', 'message' => 'PHP SQLSRV extension tidak ditemukan. Pastikan driver Microsoft SQL Server untuk PHP terinstall dan diaktifkan.']);
    exit;
}

// --- Sesuaikan konfigurasi koneksi MS SQL di bawah ini ---
$serverName = "spsdmz2";
$connectionOptions = [
    // Ganti dengan database, user, password yang sesuai di lingkungan Anda
    "Database" => "YOUR_DATABASE",
    "Uid"      => "YOUR_DB_USER",
    "PWD"      => "YOUR_DB_PASSWORD",
    "CharacterSet" => "UTF-8"
];

function dbConnect($serverName, $opts) {
    $conn = @sqlsrv_connect($serverName, $opts);
    if ($conn === false) {
        $err = sqlsrv_errors();
        echo json_encode(["error" => "DB_CONNECT_FAILED", "detail" => $err], JSON_UNESCAPED_UNICODE);
        exit;
    }
    return $conn;
}

function fetchAll($conn, $sql, $params = []) {
    $stmt = @sqlsrv_query($conn, $sql, $params, ["QueryTimeout" => 600]);
    if ($stmt === false) {
        return ["__error" => sqlsrv_errors()];
    }
    $rows = [];
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $rows[] = $row;
    }
    return $rows;
}

// --- Input params ---
$dateFrom = trim($_GET['date_from'] ?? '');
$dateTo   = trim($_GET['date_to']   ?? '');
$srjNo    = trim($_GET['srj_no']    ?? '');
$opNo     = trim($_GET['op_no']     ?? '');
$groupBy  = trim($_GET['group_by']  ?? 'op'); // 'op' or 'srj'
$limit    = isset($_GET['limit']) ? intval($_GET['limit']) : 1000;

// Connect
$conn = dbConnect($serverName, $connectionOptions);

$where = [];
$params = [];
if ($srjNo !== '') { $where[] = "sj.cNoSRJ LIKE ?"; $params[] = '%'.$srjNo.'%'; }
if ($opNo  !== '') { $where[] = "d.cNoOp LIKE ?";  $params[] = '%'.$opNo.'%'; }
if ($dateFrom !== '') { $where[] = "CONVERT(date, sj.dTanggal) >= ?"; $params[] = $dateFrom; }
if ($dateTo   !== '') { $where[] = "CONVERT(date, sj.dTanggal) <= ?"; $params[] = $dateTo; }

// Select / group expressions
if (strtolower($groupBy) === 'srj') {
    $selectExpr = "sj.cNoSRJ AS srj_no, CONVERT(VARCHAR(10), sj.dTanggal, 120) AS tgl_srj";
    $groupExpr  = "sj.cNoSRJ, CONVERT(VARCHAR(10), sj.dTanggal, 120)";
} else {
    $selectExpr = "d.cNoOp AS op_no, sj.cNoSRJ AS srj_no, CONVERT(VARCHAR(10), sj.dTanggal, 120) AS tgl_srj";
    $groupExpr  = "d.cNoOp, sj.cNoSRJ, CONVERT(VARCHAR(10), sj.dTanggal, 120)";
}

$sql = "SELECT\n  {$selectExpr},\n  SUM(CASE WHEN ISNULL(d.cTipe,'') LIKE 'SF%' AND ISNULL(d.nPanjang,0) <= 0 THEN ISNULL(d.nQty,0)\n           ELSE ISNULL(d.nQty,0) * ISNULL(d.nBrtOp,0) END) AS tonase_kg\nFROM vwSuratJalan sj WITH (NOLOCK)\nJOIN vwSuratJalanDtl d WITH (NOLOCK) ON sj.cNoSRJ = d.cNoSRJ\n";

if (!empty($where)) {
    $sql .= "WHERE " . implode(' AND ', $where) . "\n";
}

$sql .= "GROUP BY {$groupExpr}\nORDER BY tgl_srj DESC";

if ($limit > 0) {
    // OFFSET/FETCH requires ORDER BY (we already have it). We fetch first N rows.
    $sql .= " OFFSET 0 ROWS FETCH NEXT ? ROWS ONLY";
    $params[] = $limit;
}

$rows = fetchAll($conn, $sql, $params);

if (isset($rows['__error'])) {
    echo json_encode(["error" => "QUERY_FAILED", "detail" => $rows['__error']], JSON_UNESCAPED_UNICODE);
    sqlsrv_close($conn);
    exit;
}

// Normalize types
foreach ($rows as &$r) {
    $r['tonase_kg'] = isset($r['tonase_kg']) ? floatval($r['tonase_kg']) : 0.0;
}

sqlsrv_close($conn);

echo json_encode([
    'data' => $rows,
    'count' => count($rows),
    'params' => [
        'date_from' => $dateFrom,
        'date_to'   => $dateTo,
        'srj_no'    => $srjNo,
        'op_no'     => $opNo,
        'group_by'  => $groupBy,
        'limit'     => $limit
    ],
    'timestamp' => date('Y-m-d H:i:s')
], JSON_UNESCAPED_UNICODE);
