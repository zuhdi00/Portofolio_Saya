<?php
/**
 * get_beban_corr.php
 * Backend untuk Dashboard Beban Corrugating
 * Menampilkan: Planning Corrugating vs Hasil Corrugating
 * 
 * Endpoint:
 *   ?action=beban_corr - daftar beban corrugating
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// ── DB Config ──────────────────────────────────────────────────────────────────
$serverName = "spsdmz2";
$connectionOptions = [
    "Database"             => "dbSopanusa",
    "Uid"                  => "sa",
    "PWD"                  => "supracor",
    "LoginTimeout"         => 30,
    "Encrypt"              => false,
    "TrustServerCertificate" => true,
    "ReturnDatesAsStrings" => true,
    "CharacterSet"         => "UTF-8"
];

// ── Helpers ────────────────────────────────────────────────────────────────────
function dbConnect($serverName, $opts) {
    $conn = sqlsrv_connect($serverName, $opts);
    if (!$conn) {
        die(json_encode(['success' => false, 'message' => 'Database connection failed: '.print_r(sqlsrv_errors(), true)]));
    }
    return $conn;
}

function safeStr($val) {
    if ($val === null) return '';
    if (is_string($val)) return trim(iconv('ISO-8859-1', 'UTF-8//IGNORE//TRANSLIT', $val));
    return $val;
}

function fetchAll($conn, $sql, $params = []) {
    $opts = ["QueryTimeout" => 6000];
    $stmt = empty($params)
        ? sqlsrv_query($conn, $sql, [], $opts)
        : sqlsrv_query($conn, $sql, $params, $opts);
    if ($stmt === false) {
        die(json_encode(['success' => false, 'message' => 'Query failed: '.print_r(sqlsrv_errors(), true)]));
    }
    $rows = [];
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        foreach ($row as $k => $v) {
            $row[$k] = safeStr($v);
        }
        $rows[] = $row;
    }
    sqlsrv_free_stmt($stmt);
    return $rows;
}

// ── Router ─────────────────────────────────────────────────────────────────────
$action = $_GET['action'] ?? 'beban_corr';

// ══════════════════════════════════════════════════════════════════════════════
// ACTION: beban_corr — Planning vs Hasil Corrugating
// ══════════════════════════════════════════════════════════════════════════════
if ($action === 'beban_corr') {
    $conn = dbConnect($serverName, $connectionOptions);
    
    sqlsrv_query($conn, "SET TRANSACTION ISOLATION LEVEL READ UNCOMMITTED");

    $mc = trim($_GET['mc'] ?? '');
    $dateFrom = trim($_GET['date_from'] ?? '');
    $dateTo = trim($_GET['date_to'] ?? '');
    
    $limit = max(0, intval($_GET['limit'] ?? 200));
    $offset = max(0, intval($_GET['offset'] ?? 0));

    // Main query: aggregate Planning (dari tbCorrPlan) vs Hasil (dari tbCorrPlanDtl)
    $sql = "SELECT
        ROW_NUMBER() OVER (ORDER BY MAX(cp.dTanggal) DESC, MAX(op.cNoMc)) AS no,
        op.cNoMc,
        MAX(op.dTglKirim) AS tgl_kirim,
        ISNULL(SUM(cp.nQtyOrder), 0) AS plan_corr,
        ISNULL(SUM(cpd.nHasil), 0) AS hsl_corr,
        ISNULL(SUM(cpd.nRusak), 0) AS rsak_corr
    FROM tbCorrPlan cp WITH (NOLOCK)
    LEFT JOIN tbCorrPlanDtl cpd WITH (NOLOCK) ON cpd.cKodeCorr = cp.cKodeCorr
    LEFT JOIN tbOP op WITH (NOLOCK) ON op.cNoOp = cpd.cNoOp
    WHERE 1=1";

    $params = [];

    if (!empty($mc)) {
        $sql .= " AND op.cNoMc LIKE ?";
        $params[] = '%'.$mc.'%';
    }

    if (!empty($dateFrom)) {
        $sql .= " AND CAST(cp.dTanggal AS DATE) >= CAST(? AS DATE)";
        $params[] = $dateFrom;
    }

    if (!empty($dateTo)) {
        $sql .= " AND CAST(cp.dTanggal AS DATE) <= CAST(? AS DATE)";
        $params[] = $dateTo;
    }

    $sql .= " GROUP BY op.cNoMc
              ORDER BY MAX(cp.dTanggal) DESC, MAX(op.cNoMc)";

    // Count total
    $countSql = "SELECT COUNT(DISTINCT op.cNoMc) AS total
                 FROM tbCorrPlan cp WITH (NOLOCK)
                 LEFT JOIN tbCorrPlanDtl cpd WITH (NOLOCK) ON cpd.cKodeCorr = cp.cKodeCorr
                 LEFT JOIN tbOP op WITH (NOLOCK) ON op.cNoOp = cpd.cNoOp
                 WHERE 1=1";

    if (!empty($mc)) $countSql .= " AND op.cNoMc LIKE ?";
    if (!empty($dateFrom)) $countSql .= " AND CAST(cp.dTanggal AS DATE) >= CAST(? AS DATE)";
    if (!empty($dateTo)) $countSql .= " AND CAST(cp.dTanggal AS DATE) <= CAST(? AS DATE)";

    $cStmt = sqlsrv_query($conn, $countSql, $params, ["QueryTimeout"=>600]);
    $total = 0;
    if ($cStmt) {
        $cRow = sqlsrv_fetch_array($cStmt, SQLSRV_FETCH_ASSOC);
        $total = $cRow['total'] ?? 0;
        sqlsrv_free_stmt($cStmt);
    }

    // Pagination
    if ($limit > 0) {
        $sql .= " OFFSET ? ROWS FETCH NEXT ? ROWS ONLY";
        $params[] = $offset;
        $params[] = $limit;
        $totalPages = ceil($total / $limit);
        $hasNext = ($offset + $limit) < $total;
        $hasPrev = $offset > 0;
    } else {
        $totalPages = 1;
        $hasNext = false;
        $hasPrev = false;
    }

    $rows = fetchAll($conn, $sql, $params);
    sqlsrv_close($conn);

    $currentPage = $limit > 0 ? floor($offset / $limit) + 1 : 1;

    echo json_encode([
        'success' => true,
        'data' => $rows,
        'pagination' => [
            'total_records' => $total,
            'total_pages' => $totalPages,
            'current_page' => $currentPage,
            'has_prev' => $hasPrev,
            'has_next' => $hasNext
        ],
        'timestamp' => date('Y-m-d H:i:s')
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// Default: error
echo json_encode(['success' => false, 'message' => 'Invalid action']);
