<?php
// Set CORS headers FIRST — sebelum output lainnya
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Max-Age: 86400');
header('Content-Type: application/json');
// Allow Private Network Access (for browsers enforcing PNA). Note: the client
// must be a secure context for the browser to send the private-network preflight.
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Private-Network: true');

// Don't leak PHP warnings/notices into JSON responses; log instead
@ini_set('display_errors', '0');
@ini_set('log_errors', '1');
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
    // Reply with PNA header for preflight
    header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization');
    header('Access-Control-Allow-Credentials: true');
    header('Access-Control-Allow-Private-Network: true');
    http_response_code(200);
    exit;
}

// Log incoming request for diagnostics (URL, method, client IP)
logMsg(sprintf("INCOMING %s %s from %s", $_SERVER['REQUEST_METHOD'] ?? '-', $_SERVER['REQUEST_URI'] ?? '-', $_SERVER['REMOTE_ADDR'] ?? 'unknown'));

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

    // Read current status and cBatalStatus first
    $chkSql = "SELECT ISNULL(cStatus,'') AS cStatus, ISNULL(cBatalStatus,'0') AS cBatalStatus FROM tbSC WHERE cNoSc = ?";
    $chkStmt = sqlsrv_query($conn, $chkSql, [$sc], ["QueryTimeout" => 30]);
    if ($chkStmt === false) {
        $err = sqlsrv_errors();
        sqlsrv_close($conn);
        sendJson(['success' => false, 'message' => $err ? $err[0]['message'] : 'Gagal memeriksa status SLC'], 500);
    }
    $chkRow = sqlsrv_fetch_array($chkStmt, SQLSRV_FETCH_ASSOC);
    sqlsrv_free_stmt($chkStmt);
    if (!$chkRow) {
        sqlsrv_close($conn);
        sendJson(['success' => false, 'message' => 'No SLC tidak ditemukan: ' . $sc], 404);
    }

    $currentStatus = strtoupper(trim($chkRow['cStatus'] ?? ''));
    $currentBatal  = trim($chkRow['cBatalStatus'] ?? '0');

    // Business rules:
    // - When setting to CLOSE: set cStatus='CLOSE' and mark cBatalStatus='1'
    // - When setting to OPEN: disallow if currentStatus='CLOSE' AND cBatalStatus='1'.
    //   Allowed open will clear cStatus (set to empty) and set cBatalStatus='0'.
    if ($newStatus === 'CLOSE') {
        $sql  = "UPDATE tbSC SET cStatus = 'CLOSE', cBatalStatus = '1' WHERE cNoSc = ?";
        $params = [$sc];
    } else { // OPEN
        if ($currentStatus === 'CLOSE' && $currentBatal === '1') {
            sqlsrv_close($conn);
            sendJson([
                'success' => false,
                'message' => 'SLC sudah CLOSE dan tidak bisa di-OPEN langsung. Silakan lakukan batal close atau tolak posting (ubah cBatalStatus dari 1 menjadi 0) terlebih dahulu.'
            ], 403);
        }
        // Remove the 'CLOSE' marker and reset batal flag
        $sql  = "UPDATE tbSC SET cStatus = '', cBatalStatus = '0' WHERE cNoSc = ?";
        $params = [$sc];
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
        sendJson(['success' => false, 'message' => 'Tidak ada baris yang terpengaruh untuk SLC: ' . $sc], 404);
    } else {
        // Return logical status: normalize to 'CLOSE' or 'OPEN'
        $outStatus = ($newStatus === 'CLOSE') ? 'CLOSE' : 'OPEN';
        sendJson(['success' => true, 'sc' => $sc, 'status' => $outStatus]);
    }
    exit;
}

// ============================================================
// ACTION: get report  (GET)
// ==========================
$start      = trim($_GET['start']  ?? '');
$end        = trim($_GET['end']    ?? '');
$statusFilt = strtoupper(trim($_GET['status'] ?? ''));  // '' | 'OPEN' | 'CLOSE'

// Optional pagination: numeric limit/offset or `limit=all` to fetch everything
$limitRaw  = trim($_GET['limit']  ?? ''); // 'all' | '' | number
$offsetRaw = trim($_GET['offset'] ?? ''); // number
$defaultLimit = 100000; // kept for backward-compat if needed
$maxLimit = 100000;   // safety cap for numeric requests
$usePagination = false;
$limitVal = null;
$offsetVal = 0;

// Export safety: maximum allowed distinct SLC rows for a full export
$maxExportRows = 100000; // abort exports larger than this

// Default behavior: UI without explicit 'limit' should use pagination to avoid heavy scans.
if ($limitRaw === '') {
    // default UI preview page
    $usePagination = true;
    $limitVal = $defaultLimit;
    $offsetVal = 0;
} elseif (strtolower($limitRaw) === 'all') {
    // explicit full export requested — will perform a count check before heavy processing
    $usePagination = false;
} else {
    $limitVal = min((int)$limitRaw, $maxLimit);
    $offsetVal = max(0, (int)$offsetRaw);
    $usePagination = true;
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
    SELECT s.cNoSc AS sc, ISNULL(SUM(s.nQty), 0) AS total_nqty_stb
    FROM   tbStbBJ s
    INNER JOIN sc_list sl ON s.cNoSc = sl.sc
    -- aggregate raw STB quantities per SC (restricted to sc_list)
    GROUP BY s.cNoSc
),
dtstock_agg AS (
    SELECT d.cNoSc AS sc, ISNULL(SUM(ISNULL(d.nStock,0)),0) AS total_dtstock
    FROM tbDtStockDtl d
    INNER JOIN sc_list sl ON d.cNoSc = sl.sc
    GROUP BY d.cNoSc
),
adj_agg AS (
    -- Penyesuaian stok gudang hasil sinkronisasi file Excel (cut-off 03 Agu 2026).
    -- Tabel terpisah, tidak mengganggu tbDtStockDtl yang masih dipakai modul gudang.
    SELECT a.cNoSc AS sc, ISNULL(SUM(ISNULL(a.nAdjust,0)),0) AS total_adj
    FROM tbStokGudangAdj a
    INNER JOIN sc_list sl ON a.cNoSc = sl.sc
    GROUP BY a.cNoSc
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
retur_agg AS (
    -- Total nQty retur per SC, mengikuti pola get_realisasi_terpadu.php:
    -- vwReturnSrj (retur) di-join ke tbSRJDtl untuk mendapatkan cNoSc terkait
    SELECT COALESCE(d2.cNoScDtl, s2.cNoSC) AS sc,
           ISNULL(SUM(rv.nQty), 0)          AS total_nqty_retur
    FROM   vwReturnSrj rv
    INNER JOIN tbSRJDtl d2 ON d2.cNoSRJ = rv.cNoSrj
    INNER JOIN tbSRJ    s2 ON s2.cNoSRJ = d2.cNoSRJ
    WHERE  s2.cNoSC     IN (SELECT sc FROM sc_list)
       OR  d2.cNoScDtl  IN (SELECT sc FROM sc_list)
    GROUP BY COALESCE(d2.cNoScDtl, s2.cNoSC)
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
    -- Adjust total STB by subtracting any dtstock (held stock) recorded in tbDtStockDtl
    (ISNULL(stb.total_nqty_stb, 0) - ISNULL(dt.total_dtstock, 0) - ISNULL(adj.total_adj, 0))    AS total_nqty_stb,
    ISNULL(srj.total_nqty_sj,  0)                                     AS total_nqty_sj,
    ISNULL(ret.total_nqty_retur, 0)                                   AS total_nqty_retur,
    -- Sisa STB = Total STB + Retur - Jumlah Pengiriman
    (ISNULL(stb.total_nqty_stb, 0) - ISNULL(dt.total_dtstock, 0) - ISNULL(adj.total_adj, 0))
        + ISNULL(ret.total_nqty_retur, 0)
        - ISNULL(srj.total_nqty_sj,  0)                                AS sisa_stb,
    ISNULL(o.order_qty, 0)                                           AS order_qty,
    ISNULL(o.order_qty, 0)                                            AS total_order,
    -- Sisa Order = Total Order + Retur - Jumlah Pengiriman, minimum 0
    CASE WHEN (ISNULL(o.order_qty, 0) + ISNULL(ret.total_nqty_retur, 0) - ISNULL(srj.total_nqty_sj, 0)) < 0
         THEN 0
         ELSE (ISNULL(o.order_qty, 0) + ISNULL(ret.total_nqty_retur, 0) - ISNULL(srj.total_nqty_sj, 0))
    END                                                                AS sisa_order,
    UPPER(ISNULL(o.cStatus, ''))                                       AS cStatus,
    ISNULL(o.cNama,'')                                                 AS cNama,
    ISNULL(o.cJenis,'')                                                AS cJenis,
    ISNULL(o.nToleransi,0)                                             AS nToleransi
FROM      sc_list  sl
LEFT JOIN stb_agg  stb ON stb.sc = sl.sc
LEFT JOIN dtstock_agg dt ON dt.sc = sl.sc
LEFT JOIN adj_agg adj ON adj.sc = sl.sc
LEFT JOIN srj_agg  srj ON srj.sc = sl.sc
LEFT JOIN retur_agg ret ON ret.sc = sl.sc
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
            'total_nqty_retur' => (int)($row['total_nqty_retur'] ?? 0),
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

// --- ACTION: minmax_date (GET) ---
// Returns earliest and latest dTanggal in tbSC as { min: 'YYYY-MM-DD', max: 'YYYY-MM-DD' }
if (isset($_GET['action']) && $_GET['action'] === 'minmax_date') {
    $sql = "SELECT CONVERT(VARCHAR(10), MIN(CONVERT(date,dTanggal)), 23) AS minDate, CONVERT(VARCHAR(10), MAX(CONVERT(date,dTanggal)), 23) AS maxDate FROM tbSC";
    $stmt = sqlsrv_query($conn, $sql, [], ["QueryTimeout" => 30]);
    if ($stmt === false) {
        $err = sqlsrv_errors();
        logMsg('SQL ERROR (minmax_date v1): ' . json_encode($err));
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
if ($statusFilt === 'OPEN') {
    $statusWhere = "AND UPPER(ISNULL(sc_base.cStatus,'')) != 'CLOSE'";
} elseif ($statusFilt === 'CLOSE') {
    $statusWhere = "AND UPPER(ISNULL(sc_base.cStatus,'')) = 'CLOSE'";
}

// Build main body of query (aggregations + final select)
$body = "
stb_agg AS (
    SELECT s.cNoSc AS sc, ISNULL(SUM(s.nQty), 0) AS total_nqty_stb
    FROM   tbStbBJ s
    INNER JOIN sc_list sl ON s.cNoSc = sl.sc
    GROUP BY s.cNoSc
),
dtstock_agg AS (
    SELECT d.cNoSc AS sc, ISNULL(SUM(ISNULL(d.nStock,0)),0) AS total_dtstock
    FROM tbDtStockDtl d
    INNER JOIN sc_list sl ON d.cNoSc = sl.sc
    GROUP BY d.cNoSc
),
adj_agg AS (
    -- Penyesuaian stok gudang hasil sinkronisasi file Excel (cut-off 03 Agu 2026).
    -- Tabel terpisah, tidak mengganggu tbDtStockDtl yang masih dipakai modul gudang.
    SELECT a.cNoSc AS sc, ISNULL(SUM(ISNULL(a.nAdjust,0)),0) AS total_adj
    FROM tbStokGudangAdj a
    INNER JOIN sc_list sl ON a.cNoSc = sl.sc
    GROUP BY a.cNoSc
),
srj_agg AS (
    SELECT scKey AS sc, ISNULL(SUM(x.nQty), 0) AS total_nqty_sj
    FROM (
        SELECT COALESCE(d.cNoScDtl, s.cNoSC) AS scKey, d.nQty
        FROM tbSRJ s
        INNER JOIN tbSRJDtl d ON s.cNoSRJ = d.cNoSRJ
    ) x
    INNER JOIN sc_list sl ON x.scKey = sl.sc
    GROUP BY scKey
),
retur_agg AS (
    -- Total nQty retur per SC, mengikuti pola get_realisasi_terpadu.php:
    -- vwReturnSrj (retur) di-join ke tbSRJDtl untuk mendapatkan cNoSc terkait
    SELECT scKey AS sc, ISNULL(SUM(x.nQty), 0) AS total_nqty_retur
    FROM (
        SELECT COALESCE(d.cNoScDtl, s.cNoSC) AS scKey, rv.nQty
        FROM vwReturnSrj rv
        INNER JOIN tbSRJDtl d ON d.cNoSRJ = rv.cNoSrj
        INNER JOIN tbSRJ    s ON s.cNoSRJ = d.cNoSRJ
    ) x
    INNER JOIN sc_list sl ON x.scKey = sl.sc
    GROUP BY scKey
),
order_sc AS (
    SELECT sc.cNoSc AS sc,
           ISNULL(sc.nQty, 0)       AS order_qty,
           ISNULL(sc.nToleransi,0)  AS nToleransi,
           ISNULL(sc.cStatus,'')    AS cStatus,
           ISNULL(sc.cNama,'')      AS cNama,
           ISNULL(sc.cJenis,'')     AS cJenis
    FROM tbSC sc
    INNER JOIN sc_list sl ON sc.cNoSc = sl.sc
)
SELECT
    sl.sc,
    (ISNULL(stb.total_nqty_stb, 0) - ISNULL(dt.total_dtstock, 0) - ISNULL(adj.total_adj, 0))     AS total_nqty_stb,
    ISNULL(srj.total_nqty_sj,  0)                                     AS total_nqty_sj,
    ISNULL(ret.total_nqty_retur, 0)                                   AS total_nqty_retur,
    -- Sisa STB = Total STB + Retur - Jumlah Pengiriman
    (ISNULL(stb.total_nqty_stb, 0) - ISNULL(dt.total_dtstock, 0) - ISNULL(adj.total_adj, 0))
        + ISNULL(ret.total_nqty_retur, 0)
        - ISNULL(srj.total_nqty_sj,  0)                                AS sisa_stb,
    ISNULL(o.order_qty, 0)                                           AS order_qty,
    ISNULL(o.order_qty, 0)                                            AS total_order,
    -- Sisa Order = Total Order + Retur - Jumlah Pengiriman, minimum 0
    CASE WHEN (ISNULL(o.order_qty, 0) + ISNULL(ret.total_nqty_retur, 0) - ISNULL(srj.total_nqty_sj, 0)) < 0
         THEN 0
         ELSE (ISNULL(o.order_qty, 0) + ISNULL(ret.total_nqty_retur, 0) - ISNULL(srj.total_nqty_sj, 0))
    END                                                                AS sisa_order,
    UPPER(ISNULL(o.cStatus, ''))                                       AS cStatus,
    ISNULL(o.cNama,'')                                                 AS cNama,
    ISNULL(o.cJenis,'')                                                AS cJenis,
    ISNULL(o.nToleransi,0)                                             AS nToleransi
FROM      sc_list  sl
LEFT JOIN stb_agg  stb ON stb.sc = sl.sc
LEFT JOIN dtstock_agg dt ON dt.sc = sl.sc
LEFT JOIN adj_agg adj ON adj.sc = sl.sc
LEFT JOIN srj_agg  srj ON srj.sc = sl.sc
LEFT JOIN retur_agg ret ON ret.sc = sl.sc
LEFT JOIN order_sc o   ON o.sc   = sl.sc
-- join back to get cStatus for WHERE filter
LEFT JOIN tbSC sc_base ON sc_base.cNoSc = sl.sc
" . $statusWhere . "
ORDER BY sl.sc
";

if ($usePagination) {
    $pageSql = "SELECT cNoSc FROM tbSC WHERE dTanggal >= ? AND dTanggal < DATEADD(day,1, ?) ORDER BY cNoSc OFFSET ? ROWS FETCH NEXT ? ROWS ONLY";
    $pageParams = [$start, $end, $offsetVal, $limitVal];
    $pageOpts = ["QueryTimeout" => 60];
    $pageStmt = sqlsrv_query($conn, $pageSql, $pageParams, $pageOpts);
    if ($pageStmt === false) {
        $err = sqlsrv_errors();
        logMsg('SQL ERROR (sc page select): ' . json_encode($err));
        sqlsrv_close($conn);
        sendJson(['success' => false, 'message' => $err ? $err[0]['message'] : 'Query halaman SLC gagal', 'errors' => $err], 500);
    }

    $scList = [];
    while ($r = sqlsrv_fetch_array($pageStmt, SQLSRV_FETCH_ASSOC)) {
        $scList[] = trim($r['cNoSc'] ?? '');
    }
    sqlsrv_free_stmt($pageStmt);

    if (count($scList) === 0) {
        sqlsrv_close($conn);
        sendJson(['success' => true, 'rows' => []]);
    }
    
    $countSc = count($scList);
    if ($countSc === 0) {
        sqlsrv_close($conn);
        sendJson(['success' => true, 'rows' => []]);
    }
    if ($countSc > 2000) {
        // Build literal VALUES list (escape single quotes). This avoids parameter binding limit.
        $valsParts = array_map(function($v){ return "('" . str_replace("'", "''", $v) . "')"; }, $scList);
        $vals = implode(',', $valsParts);
        $sql = "WITH sc_list AS (SELECT sc FROM (VALUES " . $vals . ") v(sc) ),\n" . $body;
        $params = []; // literals inlined
        logMsg('Using literal VALUES for sc_list due to large count=' . $countSc);
    } else {
        $vals = implode(',', array_fill(0, $countSc, '(?)'));
        $sql = "WITH sc_list AS (SELECT sc FROM (VALUES " . $vals . ") v(sc) ),\n" . $body;
        $params = $scList;
    }
} else {
    // Full scan (explicit 'all') — perform a lightweight COUNT first to enforce maxExportRows
    $countSql = "SELECT COUNT(DISTINCT cNoSc) AS cnt FROM tbSC WHERE dTanggal >= ? AND dTanggal < DATEADD(day,1, ?)";
    $countStmt = sqlsrv_query($conn, $countSql, [$start, $end], ["QueryTimeout" => 30]);
    if ($countStmt === false) {
        $err = sqlsrv_errors();
        logMsg('SQL ERROR (count precheck): ' . json_encode($err));
        sqlsrv_close($conn);
        sendJson(['success' => false, 'message' => 'Gagal melakukan precheck jumlah baris sebelum export', 'errors' => $err], 500);
    }
    $cntRow = sqlsrv_fetch_array($countStmt, SQLSRV_FETCH_ASSOC);
    $totalSc = isset($cntRow['cnt']) ? (int)$cntRow['cnt'] : 0;
    sqlsrv_free_stmt($countStmt);

    if ($totalSc > $maxExportRows) {
        sqlsrv_close($conn);
        sendJson([
            'success' => false,
            'message' => 'Export terlalu besar: ' . $totalSc . ' baris. Batasi rentang tanggal atau gunakan pagination.',
            'rows' => $totalSc,
            'max_allowed' => $maxExportRows
        ], 413);
    }

    // Full scan allowed — use range without CONVERT so index on dTanggal can be used
    $sql = "WITH sc_list AS (
        SELECT DISTINCT cNoSc AS sc
        FROM   tbSC
        WHERE  dTanggal >= ? AND dTanggal < DATEADD(day,1, ?)
    ),\n" . $body;
    $params = [$start, $end];
}

$sqlOpts = ["QueryTimeout" => 1800]; // 1800s = 30 minutes; raise for heavy exports
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
        'total_nqty_retur' => (int)($row['total_nqty_retur'] ?? 0),
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
