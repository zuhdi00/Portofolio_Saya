<?php
// File: get_stok_barang_jadi.php
// Endpoint laporan Stok Barang Jadi — dipanggil dari halaman HTML via fetch/AJAX
// Mengikuti pola backend yang sudah ada: report_backend.php (Dashboard Intake)

// === CORS & header dasar — sebelum output lainnya ===
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Max-Age: 86400');
header('Content-Type: application/json');
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Private-Network: true');

// Jangan biarkan warning/notice PHP bocor ke JSON; log saja
@ini_set('display_errors', '0');
@ini_set('log_errors', '1');
error_reporting(E_ALL);

ob_start();
@set_time_limit(0);
@ini_set('memory_limit', '1024M');

function safeJsonEncode($data) {
    $opts = 0;
    if (defined('JSON_PARTIAL_OUTPUT_ON_ERROR')) $opts |= JSON_PARTIAL_OUTPUT_ON_ERROR;
    if (defined('JSON_UNESCAPED_UNICODE')) $opts |= JSON_UNESCAPED_UNICODE;
    $json = json_encode($data, $opts);
    if ($json === false) {
        $errmsg = function_exists('json_last_error_msg') ? json_last_error_msg() : json_last_error();
        error_log("[get_stok_barang_jadi] json_encode failed: $errmsg\n", 3, __DIR__.'/get_stok_barang_jadi.log');
        $json = json_encode(['success' => false, 'message' => 'Internal JSON encoding error', 'json_error' => $errmsg], JSON_UNESCAPED_UNICODE);
    }
    return $json;
}

function sendJson($data, $code = 200) {
    while (ob_get_level()) ob_end_clean();
    http_response_code($code);
    header('Content-Type: application/json');
    echo safeJsonEncode($data);
    exit;
}

function logMsg($msg) {
    $fn = __DIR__ . '/get_stok_barang_jadi.log';
    $t = date('c');
    error_log("[$t] $msg\n", 3, $fn);
}

register_shutdown_function(function () {
    $err = error_get_last();
    if ($err && in_array($err['type'], [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_PARSE], true)) {
        $payload = [
            'success' => false,
            'fatal' => true,
            'message' => $err['message'] ?? 'Fatal error',
            'file' => $err['file'] ?? null,
            'line' => $err['line'] ?? null
        ];
        logMsg('FATAL: ' . ($err['message'] ?? 'fatal') . ' in ' . ($err['file'] ?? '?') . ':' . ($err['line'] ?? '?'));
        while (ob_get_level()) ob_end_clean();
        http_response_code(500);
        header('Content-Type: application/json');
        echo safeJsonEncode($payload);
        exit;
    }
});

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header('Access-Control-Allow-Methods: GET, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization');
    header('Access-Control-Allow-Credentials: true');
    header('Access-Control-Allow-Private-Network: true');
    http_response_code(200);
    exit;
}

logMsg(sprintf("INCOMING %s %s from %s", $_SERVER['REQUEST_METHOD'] ?? '-', $_SERVER['REQUEST_URI'] ?? '-', $_SERVER['REMOTE_ADDR'] ?? 'unknown'));

// === Database config (sama dengan report_backend.php) ===
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
    $errMsg = $err ? $err[0]['message'] : 'Koneksi database gagal. Server mungkin tidak aktif atau credentials salah.';
    sendJson([
        'success' => false,
        'message' => 'Koneksi database gagal',
        'detail' => $errMsg,
        'server' => $serverName
    ], 503);
}

// ============================================================
// Parameter tanggal — default: awal bulan ini s/d hari ini
// ============================================================
$dtStart = trim($_GET['tgl_mulai'] ?? '');
$dtEnd   = trim($_GET['tgl_akhir'] ?? '');
if ($dtStart === '') $dtStart = date('Y-m-01');
if ($dtEnd   === '') $dtEnd   = date('Y-m-d');

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dtStart) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dtEnd)) {
    sqlsrv_close($conn);
    sendJson(['success' => false, 'message' => 'Format tanggal tidak valid. Gunakan YYYY-MM-DD'], 400);
}
if ($dtStart > $dtEnd) {
    sqlsrv_close($conn);
    sendJson(['success' => false, 'message' => 'Tanggal mulai tidak boleh setelah tanggal akhir'], 400);
}

// ============================================================
// QUERY CTE — sesuai Rancangan_Tabel_List_StokBJ_FINAL.docx
// Catatan: sqlsrv tidak mendukung named-parameter PDO (:dtStart),
// jadi setiap kemunculan "?" harus diisi berurutan sesuai posisinya
// di dalam SQL (lihat $params di bawah).
// ============================================================
$sql = "
WITH stb_agg AS (
    -- Transaksi penerimaan barang jadi dari produksi (tbStbBJ)
    -- Saldo Awal = transaksi sebelum periode filter
    -- STB Periode = transaksi dalam rentang filter
    SELECT
        cNoOp,
        SUM(CASE WHEN dTanggal < ? THEN nQty   ELSE 0 END) AS nSaldoAwalPcs,
        SUM(CASE WHEN dTanggal < ? THEN nQtyKg ELSE 0 END) AS nSaldoAwalKg,
        SUM(CASE WHEN dTanggal BETWEEN ? AND ? THEN nQty   ELSE 0 END) AS nStbPcs,
        SUM(CASE WHEN dTanggal BETWEEN ? AND ? THEN nQtyKg ELSE 0 END) AS nStbKg,
        MAX(dTanggal) AS dTglStbTerakhir,
        MAX(cRak)     AS cLokasi
    FROM tbStbBJ
    WHERE lPosted = '1' AND lVoid = '0'
    GROUP BY cNoOp
),
srj_agg AS (
    -- Transaksi pengiriman ke customer (tbSRJDtl JOIN tbSRJ)
    -- Filter tanggal dari tbSRJ.dTglKirim (header, bukan detail)
    -- Kg = nBrtOp x nQty (tidak ada kolom Kg langsung di tbSRJDtl)
    SELECT
        d.cNoOp,
        SUM(CASE WHEN h.dTglKirim < ? THEN d.nQty            ELSE 0 END) AS nSaldoAwalPcs,
        SUM(CASE WHEN h.dTglKirim < ? THEN d.nBrtOp * d.nQty ELSE 0 END) AS nSaldoAwalKg,
        SUM(CASE WHEN h.dTglKirim BETWEEN ? AND ? THEN d.nQty            ELSE 0 END) AS nDlvPcs,
        SUM(CASE WHEN h.dTglKirim BETWEEN ? AND ? THEN d.nBrtOp * d.nQty ELSE 0 END) AS nDlvKg
    FROM tbSRJDtl d
    JOIN tbSRJ    h ON h.cNoSRJ = d.cNoSRJ
    WHERE h.lPosted = '1' AND h.lVoid = '0'
    GROUP BY d.cNoOp
),
retur_agg AS (
    -- Retur dari customer via vwReturnSrj (sudah dipakai di STB Total Report)
    -- vwReturnSrj tidak punya cNoOp langsung, join via tbSRJDtl
    -- Kg = nBerat x nQty (nBerat = berat per pcs di vwReturnSrj)
    SELECT
        d.cNoOp,
        SUM(CASE WHEN v.dTglKirim < ? THEN v.nQty             ELSE 0 END) AS nSaldoAwalPcs,
        SUM(CASE WHEN v.dTglKirim < ? THEN v.nBerat * v.nQty  ELSE 0 END) AS nSaldoAwalKg,
        SUM(CASE WHEN v.dTglKirim BETWEEN ? AND ? THEN v.nQty            ELSE 0 END) AS nRtPcs,
        SUM(CASE WHEN v.dTglKirim BETWEEN ? AND ? THEN v.nBerat * v.nQty ELSE 0 END) AS nRtKg
    FROM vwReturnSrj v
    JOIN tbSRJDtl    d ON d.cNoSRJ = v.cNoSrj
    WHERE v.lPosted = '1' AND v.lVoid = '0'
    GROUP BY d.cNoOp
)
SELECT
    ROW_NUMBER() OVER (ORDER BY sc.cNama, o.cnm_brg) AS nNo,
    sc.cNama    AS cCustomer,
    o.cnm_brg   AS cItem,
    sc.cSales,
    CAST(o.nPanjang AS VARCHAR) + ' x '
        + CAST(o.nLebar   AS VARCHAR) + ' x '
        + CAST(o.nTinggi  AS VARCHAR) AS cUkuran,
    o.cNoMc,
    o.cNoOp,
    o.cNoSc,
    st.dTglStbTerakhir,
    st.cLokasi,
    -- Saldo Awal
    ISNULL(st.nSaldoAwalPcs, 0) - ISNULL(sj.nSaldoAwalPcs, 0) + ISNULL(rt.nSaldoAwalPcs, 0) AS nSaldoAwalPcs,
    ISNULL(st.nSaldoAwalKg,  0) - ISNULL(sj.nSaldoAwalKg,  0) + ISNULL(rt.nSaldoAwalKg,  0) AS nSaldoAwalKg,
    -- Pergerakan periode
    ISNULL(st.nStbPcs, 0) AS nStbPcs,
    ISNULL(st.nStbKg,  0) AS nStbKg,
    ISNULL(sj.nDlvPcs, 0) AS nDlvPcs,
    ISNULL(sj.nDlvKg,  0) AS nDlvKg,
    ISNULL(rt.nRtPcs,  0) AS nRtPcs,
    ISNULL(rt.nRtKg,   0) AS nRtKg,
    -- Saldo Akhir (floor ke 0)
    CASE WHEN (ISNULL(st.nSaldoAwalPcs,0) - ISNULL(sj.nSaldoAwalPcs,0) + ISNULL(rt.nSaldoAwalPcs,0)
             + ISNULL(st.nStbPcs,0) - ISNULL(sj.nDlvPcs,0) + ISNULL(rt.nRtPcs,0)) < 0
        THEN 0
        ELSE (ISNULL(st.nSaldoAwalPcs,0) - ISNULL(sj.nSaldoAwalPcs,0) + ISNULL(rt.nSaldoAwalPcs,0)
             + ISNULL(st.nStbPcs,0) - ISNULL(sj.nDlvPcs,0) + ISNULL(rt.nRtPcs,0))
    END AS nSaldoAkhirPcs,
    CASE WHEN (ISNULL(st.nSaldoAwalKg,0) - ISNULL(sj.nSaldoAwalKg,0) + ISNULL(rt.nSaldoAwalKg,0)
             + ISNULL(st.nStbKg,0) - ISNULL(sj.nDlvKg,0) + ISNULL(rt.nRtKg,0)) < 0
        THEN 0
        ELSE (ISNULL(st.nSaldoAwalKg,0) - ISNULL(sj.nSaldoAwalKg,0) + ISNULL(rt.nSaldoAwalKg,0)
             + ISNULL(st.nStbKg,0) - ISNULL(sj.nDlvKg,0) + ISNULL(rt.nRtKg,0))
    END AS nSaldoAkhirKg,
    -- Umur stok (hari)
    DATEDIFF(day, st.dTglStbTerakhir, GETDATE()) AS nUmurHari
FROM tbOP o
LEFT JOIN tbSC      sc ON sc.cNoSC = o.cNoSc
LEFT JOIN stb_agg   st ON st.cNoOp = o.cNoOp
LEFT JOIN srj_agg   sj ON sj.cNoOp = o.cNoOp
LEFT JOIN retur_agg rt ON rt.cNoOp = o.cNoOp
WHERE EXISTS (
    SELECT 1 FROM tbStbBJ x
    WHERE x.cNoOp = o.cNoOp
      AND x.lPosted = '1' AND x.lVoid = '0'
)
ORDER BY sc.cNama, o.cnm_brg
";

// Urutan parameter HARUS sama persis dengan urutan tanda "?" di atas:
// stb_agg: dtStart, dtStart, dtStart, dtEnd, dtStart, dtEnd
// srj_agg: dtStart, dtStart, dtStart, dtEnd, dtStart, dtEnd
// retur_agg: dtStart, dtStart, dtStart, dtEnd, dtStart, dtEnd
$params = [
    $dtStart, $dtStart, $dtStart, $dtEnd, $dtStart, $dtEnd,   // stb_agg
    $dtStart, $dtStart, $dtStart, $dtEnd, $dtStart, $dtEnd,   // srj_agg
    $dtStart, $dtStart, $dtStart, $dtEnd, $dtStart, $dtEnd,   // retur_agg
];

$sqlOpts = ["QueryTimeout" => 600];
$stmt = sqlsrv_query($conn, $sql, $params, $sqlOpts);
if ($stmt === false) {
    $err = sqlsrv_errors();
    logMsg('SQL ERROR (stok barang jadi): ' . json_encode($err));
    sqlsrv_close($conn);
    sendJson(['success' => false, 'message' => $err ? $err[0]['message'] : 'Query gagal', 'errors' => $err], 500);
}

$rows = [];
$qstart = microtime(true);
$count = 0;
while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $count++;

    $tglStb = $row['dTglStbTerakhir'] ?? null;
    $tglStbStr = null;
    if ($tglStb instanceof DateTime) {
        $tglStbStr = $tglStb->format('Y-m-d');
    } elseif ($tglStb) {
        $tglStbStr = (string)$tglStb;
    }

    $nUmurHari = $row['nUmurHari'];
    $nUmurHari = ($nUmurHari === null) ? null : (int)$nUmurHari;

    $nSaldoAkhirPcs = (int)($row['nSaldoAkhirPcs'] ?? 0);

    // Hitung Status & Umur di PHP (sesuai pola docx) — tidak di SQL
    if ($nSaldoAkhirPcs === 0) {
        $cStatus = 'KOSONG';
    } elseif ($nUmurHari !== null && $nUmurHari > 14) {
        $cStatus = 'MENUMPUK';
    } else {
        $cStatus = 'NORMAL';
    }

    if ($nUmurHari === null) {
        $cUmurLabel = '-';
    } elseif ($nUmurHari <= 5) {
        $cUmurLabel = '<= 5 hr';
    } elseif ($nUmurHari <= 7) {
        $cUmurLabel = '<= 7 hr';
    } elseif ($nUmurHari <= 14) {
        $cUmurLabel = '<= 14 hr';
    } else {
        $cUmurLabel = '> 14 hr';
    }

    $rows[] = [
        'nNo'             => (int)($row['nNo'] ?? 0),
        'cCustomer'       => trim($row['cCustomer'] ?? ''),
        'cItem'           => trim($row['cItem'] ?? ''),
        'cSales'          => trim($row['cSales'] ?? ''),
        'cUkuran'         => trim($row['cUkuran'] ?? ''),
        'cNoMc'           => trim($row['cNoMc'] ?? ''),
        'cNoOp'           => trim($row['cNoOp'] ?? ''),
        'cNoSc'           => trim($row['cNoSc'] ?? ''),
        'dTglStbTerakhir' => $tglStbStr,
        'cLokasi'         => trim($row['cLokasi'] ?? ''),
        'nSaldoAwalPcs'   => (float)($row['nSaldoAwalPcs'] ?? 0),
        'nSaldoAwalKg'    => (float)($row['nSaldoAwalKg']  ?? 0),
        'nStbPcs'         => (float)($row['nStbPcs'] ?? 0),
        'nStbKg'          => (float)($row['nStbKg']  ?? 0),
        'nDlvPcs'         => (float)($row['nDlvPcs'] ?? 0),
        'nDlvKg'          => (float)($row['nDlvKg']  ?? 0),
        'nRtPcs'          => (float)($row['nRtPcs'] ?? 0),
        'nRtKg'           => (float)($row['nRtKg']  ?? 0),
        'nSaldoAkhirPcs'  => $nSaldoAkhirPcs,
        'nSaldoAkhirKg'   => (float)($row['nSaldoAkhirKg'] ?? 0),
        'nUmurHari'       => $nUmurHari,
        'cUmurLabel'      => $cUmurLabel,
        'cStatus'         => $cStatus,
    ];
}
$dur = microtime(true) - $qstart;
logMsg("Stok Barang Jadi report start={$dtStart} end={$dtEnd} rows={$count} duration={$dur}");

sqlsrv_free_stmt($stmt);
sqlsrv_close($conn);

sendJson([
    'success'   => true,
    'tgl_mulai' => $dtStart,
    'tgl_akhir' => $dtEnd,
    'total'     => $count,
    'data'      => $rows,
]);
?>
