<?php
// Set CORS headers FIRST — sebelum output lainnya
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Max-Age: 86400');
header('Content-Type: application/json');

// Don't leak PHP warnings/notices into JSON responses; log instead
@ini_set('display_errors', '0');
@ini_set('log_errors', '1');
// (added endpoint for front-end date defaults)
if (isset($_GET['action']) && $_GET['action'] === 'minmax_date') {
    $sql = "SELECT CONVERT(VARCHAR(10), MIN(CONVERT(date,dTanggal)), 23) AS minDate, CONVERT(VARCHAR(10), MAX(CONVERT(date,dTanggal)), 23) AS maxDate FROM tbSC";
    $stmt = sqlsrv_query($conn, $sql, [], ["QueryTimeout" => 30]);
    if ($stmt === false) {
        $err = sqlsrv_errors();
        logMsg('SQL ERROR (minmax_date): ' . json_encode($err));
        sqlsrv_close($conn);
        sendJson(['success' => false, 'message' => 'Gagal mengambil tanggal', 'errors' => $err], 500);
    }
    $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
    sqlsrv_free_stmt($stmt);
    sqlsrv_close($conn);
    $min = $row['minDate'] ?? null;
    $max = $row['maxDate'] ?? null;
    sendJson(['success' => true, 'min' => $min, 'max' => $max]);
}
error_reporting(E_ALL);

// Start output buffering so we can return clean JSON even on fatal errors
ob_start();
// Remove PHP execution time limit for large exports; rely on SQL QueryTimeout instead.
@set_time_limit(0);
@ini_set('memory_limit', '1024M');

// Helper to send JSON and ensure no stray output interferes
function safeJsonEncode($data) {
    $opts = 0;
    if (defined('JSON_PARTIAL_OUTPUT_ON_ERROR')) $opts |= JSON_PARTIAL_OUTPUT_ON_ERROR;
    if (defined('JSON_UNESCAPED_UNICODE')) $opts |= JSON_UNESCAPED_UNICODE;
    $json = json_encode($data, $opts);
    if ($json === false) {
        $errmsg = function_exists('json_last_error_msg') ? json_last_error_msg() : json_last_error();
        error_log("[report_backend] json_encode failed: $errmsg\n", 3, __DIR__.'/report_backend.log');
        // fallback safe payload
        $json = json_encode(['success' => false, 'message' => 'Internal JSON encoding error', 'json_error' => $errmsg], JSON_UNESCAPED_UNICODE);
    }
    return $json;
}

function sendJson($data, $code = 200) {
    // clear all output buffers
    while (ob_get_level()) ob_end_clean();
    http_response_code($code);
    header('Content-Type: application/json');
    echo safeJsonEncode($data);
    exit;
}

function logMsg($msg) {
    $fn = __DIR__ . '/report_backend.log';
    $t = date('c');
    error_log("[$t] $msg\n", 3, $fn);
}

// Shutdown handler: if a fatal error occurred, return JSON with the message
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
        // Use sendJson (which clears buffers) but can't call it directly here after buffer cleanup,
        // so manually perform same steps to avoid recursion.
        // log then emit JSON
        logMsg('FATAL: ' . ($err['message'] ?? 'fatal') . ' in ' . ($err['file'] ?? '?') . ':' . ($err['line'] ?? '?'));
        while (ob_get_level()) ob_end_clean();
        http_response_code(500);
        header('Content-Type: application/json');
        echo safeJsonEncode($payload);
        exit;
    }
});

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// === Database config ===
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
// ACTION: update_status  (POST)
// Mengubah cStatus di tbSC untuk satu cNoSc
// ============================================================
$action = trim($_POST['action'] ?? '');
if ($action === 'update_status') {
    $sc        = trim($_POST['sc']     ?? '');
    $newStatus = strtoupper(trim($_POST['status'] ?? ''));

    if ($sc === '') {
        sqlsrv_close($conn);
        sendJson(['success' => false, 'message' => 'Parameter sc wajib diisi'], 400);
    }
    if (!in_array($newStatus, ['OPEN', 'CLOSE'], true)) {
        sqlsrv_close($conn);
        sendJson(['success' => false, 'message' => 'Nilai status tidak valid. Gunakan OPEN atau CLOSE'], 400);
    }
    // Read current status and cBatalStatus to decide allowed transitions
    $selSql = "SELECT ISNULL(cStatus,'') AS cStatus, ISNULL(cBatalStatus,0) AS cBatalStatus FROM tbSC WHERE cNoSc = ?";
    $selStmt = sqlsrv_query($conn, $selSql, [$sc], ["QueryTimeout" => 30]);
    if ($selStmt === false) {
        $err = sqlsrv_errors();
        sqlsrv_close($conn);
        sendJson(['success' => false, 'message' => $err ? $err[0]['message'] : 'Gagal membaca status saat ini'], 500);
    }
    $curRow = sqlsrv_fetch_array($selStmt, SQLSRV_FETCH_ASSOC);
    sqlsrv_free_stmt($selStmt);

    if (!$curRow) {
        sqlsrv_close($conn);
        sendJson(['success' => false, 'message' => 'No SLC tidak ditemukan: ' . $sc], 404);
    }

    $curStatus = strtoupper(trim($curRow['cStatus'] ?? ''));
    $curBatal  = (int)($curRow['cBatalStatus'] ?? 0);

    // Prevent direct CLOSE -> OPEN if cBatalStatus=1; require batal_close/tolak posting first
    if ($newStatus === 'OPEN' && $curStatus === 'CLOSE' && $curBatal === 1) {
        sqlsrv_close($conn);
        sendJson([
            'success' => false,
            'message' => 'Tidak bisa langsung mengubah CLOSE ke OPEN. Gunakan aksi batal_close atau tolak posting untuk mengubah nilai cBatalStatus menjadi 0.'
        ], 403);
    }

    if ($newStatus === 'OPEN') {
        // Opening: clear cStatus (remove 'CLOSE')
        $sql = "UPDATE tbSC SET cStatus = NULL, cBatalStatus = 0 WHERE cNoSc = ?";
        $params = [$sc];
    } else {
        // Closing: set cStatus='CLOSE' and mark lClose=1 (requires explicit un-close)
        $sql = "UPDATE tbSC SET cStatus = ?, cBatalStatus = 1 WHERE cNoSc = ?";
        $params = ['CLOSE', $sc];
    }

    $stmt = sqlsrv_query($conn, $sql, $params);
    if ($stmt === false) {
        $err = sqlsrv_errors();
        sqlsrv_close($conn);
        sendJson(['success' => false, 'message' => $err ? $err[0]['message'] : 'Update gagal'], 500);
    }

    $rows = sqlsrv_rows_affected($stmt);
    sqlsrv_free_stmt($stmt);
    sqlsrv_close($conn);

    if ($rows === 0) {
        sendJson(['success' => false, 'message' => 'No SLC tidak ditemukan atau tidak ada perubahan: ' . $sc], 404);
    } else {
        sendJson(['success' => true, 'sc' => $sc, 'status' => $newStatus]);
    }
    exit;
}

// ACTION: batal_close — set cBatalStatus = 0 and clear cStatus (requires proper authorization on caller)
if ($action === 'batal_close') {
    $sc = trim($_POST['sc'] ?? '');
    if ($sc === '') {
        sqlsrv_close($conn);
        sendJson(['success' => false, 'message' => 'Parameter sc wajib diisi'], 400);
    }

    $sql = "UPDATE tbSC SET cBatalStatus = 0, cStatus = NULL WHERE cNoSc = ?";
    $stmt = sqlsrv_query($conn, $sql, [$sc]);
    if ($stmt === false) {
        $err = sqlsrv_errors();
        sqlsrv_close($conn);
        sendJson(['success' => false, 'message' => $err ? $err[0]['message'] : 'Batal close gagal'], 500);
    }

    $rows = sqlsrv_rows_affected($stmt);
    sqlsrv_free_stmt($stmt);
    sqlsrv_close($conn);

    if ($rows === 0) {
        sendJson(['success' => false, 'message' => 'No SLC tidak ditemukan atau tidak diubah: ' . $sc], 404);
    } else {
        sendJson(['success' => true, 'sc' => $sc, 'message' => 'Batal close berhasil']);
    }
    exit;
}

// ============================================================
// ACTION: get report  (GET)
// ============================================================
$start      = trim($_GET['start']  ?? '');
$end        = trim($_GET['end']    ?? '');
$statusFilt = strtoupper(trim($_GET['status'] ?? ''));  // '' | 'OPEN' | 'CLOSE'

// Optional pagination: numeric limit/offset or `limit=all` to fetch everything
$limitRaw  = trim($_GET['limit']  ?? ''); // 'all' | '' | number
$offsetRaw = trim($_GET['offset'] ?? ''); // number
$usePagination = false;
$limitVal = null;
$offsetVal = 0;
if ($limitRaw !== '') {
    if (strtolower($limitRaw) === 'all' || intval($limitRaw) <= 0) {
        // explicit 'all' or non-positive -> no pagination (fetch all)
        $usePagination = false;
    } else {
        $limitVal = (int)$limitRaw;
        $offsetVal = max(0, (int)$offsetRaw);
        $usePagination = true;
    }
}

// If an explicit SLC search is provided, bypass date checks and run a global lookup
$slcSearch = trim($_GET['slc'] ?? '');
if ($slcSearch !== '') {
    // Single-SLC query (no date restriction) — reuse main query but build sc_list from provided SLC
    $params = [$slcSearch];
    $sql = "
WITH sc_list AS (
    SELECT ? AS sc
),
stb_agg AS (
    SELECT cNoSc AS sc, ISNULL(SUM(nQty), 0) AS total_nqty_stb
    FROM   tbStbBJ
    WHERE  cNoSc IN (SELECT sc FROM sc_list)
    GROUP BY cNoSc
),
srj_agg AS (
    SELECT COALESCE(d.cNoScDtl, s.cNoSC) AS sc,
           ISNULL(SUM(d.nQty), 0)         AS total_nqty_sj
    FROM   tbSRJ    s
    INNER JOIN tbSRJDtl d ON s.cNoSRJ = d.cNoSRJ
    WHERE  s.cNoSC     IN (SELECT sc FROM sc_list)
       OR  d.cNoScDtl  IN (SELECT sc FROM sc_list)
    GROUP BY COALESCE(d.cNoScDtl, s.cNoSC)
),
order_sc AS (
    SELECT
        cNoSc                           AS sc,
        ISNULL(nQty, 0)                 AS order_qty,
        ISNULL(nToleransi, 0)           AS nToleransi,
        ISNULL(cStatus, '')             AS cStatus,
        ISNULL(cNama, '')               AS cNama,
        ISNULL(cJenis, '')              AS cJenis
    FROM tbSC
    WHERE cNoSc IN (SELECT sc FROM sc_list)
)
SELECT
    sl.sc,
    ISNULL(stb.total_nqty_stb, 0)                                     AS total_nqty_stb,
    ISNULL(srj.total_nqty_sj,  0)                                     AS total_nqty_sj,
    ISNULL(stb.total_nqty_stb, 0) - ISNULL(srj.total_nqty_sj,  0)    AS sisa_stb,
    ISNULL(o.order_qty, 0)                                           AS order_qty,
    ISNULL(o.order_qty, 0)                                            AS total_order,
    ISNULL(o.order_qty, 0)
        - ISNULL(stb.total_nqty_stb, 0)                               AS sisa_order,
    UPPER(ISNULL(o.cStatus, ''))                                       AS cStatus,
    ISNULL(o.cNama,'')                                                 AS cNama,
    ISNULL(o.cJenis,'')                                                AS cJenis,
    ISNULL(o.nToleransi,0)                                             AS nToleransi
FROM      sc_list  sl
LEFT JOIN stb_agg  stb ON stb.sc = sl.sc
LEFT JOIN srj_agg  srj ON srj.sc = sl.sc
LEFT JOIN order_sc o   ON o.sc   = sl.sc
LEFT JOIN tbSC sc_base ON sc_base.cNoSc = sl.sc
ORDER BY sl.sc
";

    // Apply pagination to the SLC lookup if requested
    if ($usePagination) {
        $sql .= "\nOFFSET ? ROWS FETCH NEXT ? ROWS ONLY";
        $params[] = $offsetVal;
        $params[] = $limitVal;
    }

    $sqlOpts = ["QueryTimeout" => 3000];
    $stmt = sqlsrv_query($conn, $sql, $params, $sqlOpts);
    if ($stmt === false) {
        $err = sqlsrv_errors();
        logMsg('SQL ERROR (SLC lookup): ' . json_encode($err));
        sqlsrv_close($conn);
        sendJson(['success' => false, 'message' => $err ? $err[0]['message'] : 'Query gagal', 'errors' => $err], 500);
    }

    $results = [];
    $qstart = microtime(true);
    $count = 0;
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $count++;
        $cs = strtoupper(trim($row['cStatus'] ?? ''));
        $results[] = [
            'sc'             => trim($row['sc']             ?? ''),
            'total_nqty_stb' => (int)($row['total_nqty_stb'] ?? 0),
            'total_nqty_sj'  => (int)($row['total_nqty_sj']  ?? 0),
            'sisa_stb'       => (int)($row['sisa_stb']        ?? 0),
            'total_order'    => (int)($row['total_order']     ?? 0),
            'sisa_order'     => (int)($row['sisa_order']      ?? 0),
            'cStatus'        => ($cs === 'CLOSE') ? 'CLOSE' : 'OPEN',
            'cNama'          => trim($row['cNama']          ?? ''),
            'cJenis'         => trim($row['cJenis']         ?? ''),
            'nToleransi'     => (int)($row['nToleransi']     ?? 0),
            'order_qty'      => (int)($row['order_qty']      ?? 0),
        ];
    }
    $dur = microtime(true) - $qstart;
    $limLog = $usePagination ? "limit={$limitVal} offset={$offsetVal}" : 'limit=all';
    logMsg("SLC lookup '{$slcSearch}' rows={$count} duration={$dur} {$limLog}");

    sqlsrv_free_stmt($stmt);
    sqlsrv_close($conn);
    sendJson(['success' => true, 'rows' => $results]);
}

// If not an SLC search, continue with normal date-based flow

if ($start === '' || $end === '') {
    sqlsrv_close($conn);
    sendJson(['success' => false, 'message' => 'Parameter start dan end (YYYY-MM-DD) wajib diisi'], 400);
}
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $start) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $end)) {
    sqlsrv_close($conn);
    sendJson(['success' => false, 'message' => 'Format tanggal tidak valid. Gunakan YYYY-MM-DD'], 400);
}
if ($start > $end) {
    sqlsrv_close($conn);
    sendJson(['success' => false, 'message' => 'Tanggal mulai tidak boleh setelah tanggal akhir'], 400);
}
if ($statusFilt !== '' && !in_array($statusFilt, ['OPEN', 'CLOSE'], true)) {
    sqlsrv_close($conn);
    sendJson(['success' => false, 'message' => 'Nilai filter status tidak valid'], 400);
}

// ============================================================
// SINGLE QUERY — CTE + LEFT JOIN (N→1 query)
//
// cStatus logic:
//   - NULL / '' / anything other than 'CLOSE' → dianggap OPEN
//   - 'CLOSE'                                 → CLOSE
//
// Filter status:
//   - OPEN  → WHERE UPPER(ISNULL(o.cStatus,'')) != 'CLOSE'
//   - CLOSE → WHERE UPPER(ISNULL(o.cStatus,'')) =  'CLOSE'
//   - ''    → no filter
// ============================================================
$statusWhere = '';
$params      = [$start, $end];

if ($statusFilt === 'OPEN') {
    $statusWhere = "AND UPPER(ISNULL(sc_base.cStatus,'')) != 'CLOSE'";
} elseif ($statusFilt === 'CLOSE') {
    $statusWhere = "AND UPPER(ISNULL(sc_base.cStatus,'')) = 'CLOSE'";
}

$sql = "
WITH sc_list AS (
    SELECT DISTINCT cNoSc AS sc
    FROM   tbSC
    WHERE  CONVERT(date, dTanggal) BETWEEN ? AND ?
),
stb_agg AS (
    SELECT cNoSc AS sc, ISNULL(SUM(nQty), 0) AS total_nqty_stb
    FROM   tbStbBJ
    WHERE  cNoSc IN (SELECT sc FROM sc_list)
    GROUP BY cNoSc
),
srj_agg AS (
    SELECT COALESCE(d.cNoScDtl, s.cNoSC) AS sc,
           ISNULL(SUM(d.nQty), 0)         AS total_nqty_sj
    FROM   tbSRJ    s
    INNER JOIN tbSRJDtl d ON s.cNoSRJ = d.cNoSRJ
    WHERE  s.cNoSC     IN (SELECT sc FROM sc_list)
       OR  d.cNoScDtl  IN (SELECT sc FROM sc_list)
    GROUP BY COALESCE(d.cNoScDtl, s.cNoSC)
),
order_sc AS (
    SELECT
        cNoSc                           AS sc,
        ISNULL(nQty, 0)                 AS order_qty,
        ISNULL(nToleransi, 0)           AS nToleransi,
        ISNULL(cStatus, '')             AS cStatus,
        ISNULL(cNama, '')               AS cNama,
        ISNULL(cJenis, '')              AS cJenis
    FROM tbSC
    WHERE cNoSc IN (SELECT sc FROM sc_list)
)
SELECT
    sl.sc,
    ISNULL(stb.total_nqty_stb, 0)                                     AS total_nqty_stb,
    ISNULL(srj.total_nqty_sj,  0)                                     AS total_nqty_sj,
    ISNULL(stb.total_nqty_stb, 0) - ISNULL(srj.total_nqty_sj,  0)    AS sisa_stb,
    ISNULL(o.order_qty, 0)                                           AS order_qty,
    ISNULL(o.order_qty, 0)                                            AS total_order,
    ISNULL(o.order_qty, 0)
        - ISNULL(stb.total_nqty_stb, 0)                               AS sisa_order,
    UPPER(ISNULL(o.cStatus, ''))                                       AS cStatus,
    ISNULL(o.cNama,'')                                                 AS cNama,
    ISNULL(o.cJenis,'')                                                AS cJenis,
    ISNULL(o.nToleransi,0)                                             AS nToleransi
FROM      sc_list  sl
LEFT JOIN stb_agg  stb ON stb.sc = sl.sc
LEFT JOIN srj_agg  srj ON srj.sc = sl.sc
LEFT JOIN order_sc o   ON o.sc   = sl.sc
-- join back to get cStatus for WHERE filter
LEFT JOIN tbSC sc_base ON sc_base.cNoSc = sl.sc
$statusWhere
ORDER BY sl.sc
";


// Apply pagination for date-range flow when requested
if ($usePagination) {
    $sql .= "\nOFFSET ? ROWS FETCH NEXT ? ROWS ONLY";
    $params[] = $offsetVal;
    $params[] = $limitVal;
}

$sqlOpts = ["QueryTimeout" => 600];
$stmt = sqlsrv_query($conn, $sql, $params, $sqlOpts);
if ($stmt === false) {
    $err = sqlsrv_errors();
    logMsg('SQL ERROR (date-range report): ' . json_encode($err));
    sqlsrv_close($conn);
    sendJson(['success' => false, 'message' => $err ? $err[0]['message'] : 'Query gagal', 'errors' => $err], 500);
}

$results = [];
$qstart = microtime(true);
$count = 0;
while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $count++;
    // Normalise cStatus: NULL / '' / anything ≠ 'CLOSE'  →  'OPEN'
    $cs = strtoupper(trim($row['cStatus'] ?? ''));
    $results[] = [
        'sc'             => trim($row['sc']             ?? ''),
        'total_nqty_stb' => (int)($row['total_nqty_stb'] ?? 0),
        'total_nqty_sj'  => (int)($row['total_nqty_sj']  ?? 0),
        'sisa_stb'       => (int)($row['sisa_stb']        ?? 0),
        'total_order'    => (int)($row['total_order']     ?? 0),
        'sisa_order'     => (int)($row['sisa_order']      ?? 0),
        'cStatus'        => ($cs === 'CLOSE') ? 'CLOSE' : 'OPEN',
        'cNama'          => trim($row['cNama']          ?? ''),
        'cJenis'         => trim($row['cJenis']         ?? ''),
        'nToleransi'     => (int)($row['nToleransi']     ?? 0),
        'order_qty'      => (int)($row['order_qty']      ?? 0),
    ];
}
$dur = microtime(true) - $qstart;
$limLog = $usePagination ? "limit={$limitVal} offset={$offsetVal}" : 'limit=all';
logMsg("Date-range report start={$start} end={$end} status={$statusFilt} rows={$count} duration={$dur} {$limLog}");

sqlsrv_free_stmt($stmt);
sqlsrv_close($conn);

sendJson(['success' => true, 'rows' => $results]);
?>
