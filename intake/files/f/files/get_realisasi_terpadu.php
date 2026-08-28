<?php
/**
 * get_realisasi_terpadu_v2.php
 * Version 2 — corrected tonase aggregation.
 *
 * Tonase calculation (per SRJ detail row):
 *   CASE WHEN cTipe LIKE 'SF%' AND nPanjang <= 0 THEN nQty
 *        ELSE nQty * nBrtOp
 *   END
 * Sum all SRJ-detail rows per OP, subtract returns (vwReturnSrj),
 * then divide by 1000 to convert kg → ton.
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

@set_time_limit(6000);
@ini_set('memory_limit', '512M');
$sqlOpts = ["QueryTimeout" => 60000000];

// DB config (match existing environment)
$serverName = "spsdmz2";
$connectionOptions = [
    "Database" => "dbSopanusa",
    "Uid"      => "sa",
    "PWD"      => "supracor",
    "LoginTimeout" => 30,
    "Encrypt"  => false,
    "TrustServerCertificate" => true,
    "ReturnDatesAsStrings" => true,
    "CharacterSet" => "UTF-8",
];

function dbConnect($serverName, $opts) {
    $conn = sqlsrv_connect($serverName, $opts);
    if (!$conn) {
        $errs = sqlsrv_errors();
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'message' => 'DB connect failed', 'errors' => $errs], JSON_UNESCAPED_UNICODE);
        exit;
    }
    return $conn;
}

function fetchAll($conn, $sql, $params = []) {
    $opts = ["QueryTimeout" => 60000000];
    $stmt = empty($params) ? sqlsrv_query($conn, $sql, [], $opts) : sqlsrv_query($conn, $sql, $params, $opts);
    if ($stmt === false) {
        $errs = sqlsrv_errors();
        sqlsrv_close($conn);
        echo json_encode(['success' => false, 'message' => 'Query failed', 'errors' => $errs], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $rows = [];
    while ($r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $rows[] = $r;
    }
    sqlsrv_free_stmt($stmt);
    return $rows;
}

// Router
$action = $_GET['action'] ?? 'list';

if ($action === 'list') {
    $mc        = trim($_GET['mc'] ?? '');
    $search    = trim($_GET['search'] ?? '');
    $dateFrom  = trim($_GET['date_from'] ?? '');
    $dateTo    = trim($_GET['date_to'] ?? '');
    $limitRaw  = $_GET['limit'] ?? null;
    if ($limitRaw === 'all' || $limitRaw === '0' || $limitRaw === 0) {
        $limit = 0;
    } else {
        $limit = intval($limitRaw ?? 200);
        if ($limit < 1) $limit = 200;
        $limit = min(1000000, $limit);
    }
    $offset = max(0, intval($_GET['offset'] ?? 0));
    $includeTotals = isset($_GET['include_totals']) && $_GET['include_totals'] == '1';

    // Default to today if no filters (keeps behaviour similar to original)
    $noAnyFilter = empty($mc) && empty($search) && empty($dateFrom) && empty($dateTo);
    if ($noAnyFilter) {
        $dateFrom = date('Y-m-d');
        $dateTo   = date('Y-m-d');
    }

    $params = [];
    $where  = [];
    if (!empty($search)) {
        $p = '%'.$search.'%';
        $where[] = "(sc.cNoSc LIKE ? OR sc.cNama LIKE ? OR op.cNoOp LIKE ? OR op.cNoMc LIKE ?)";
        $params[] = $p; $params[] = $p; $params[] = $p; $params[] = $p;
    }
    if (!empty($mc)) { $where[] = "op.cNoMc LIKE ?"; $params[] = '%'.$mc.'%'; }
    if (!empty($dateFrom)) { $where[] = "op.dTgl >= ?"; $params[] = $dateFrom; }
    if (!empty($dateTo))   { $where[] = "op.dTgl <= ?"; $params[] = $dateTo . ' 23:59:59'; }

    // Main query: per-OP rows + corrected tonase from vwSuratJalanDtl and vwReturnSrj
    $sql = "SELECT
        op.cNoOp,
        op.cNoSc,
        sc.dTanggal AS tgl_sc,
        sc.cNama     AS customer,
        op.cNoMc,
        ISNULL(op.nQty,0) AS total_order,
        ISNULL(srj.qty_kirim,0) AS jml_kirim,
        ISNULL(srj.kg_srj,0) AS kg_srj,
        ISNULL(r.kg_retur,0) AS kg_retur,
        (ISNULL(srj.kg_srj,0) - ISNULL(r.kg_retur,0))/1000.0 AS tonase
    FROM tbOP op WITH (NOLOCK)
    LEFT JOIN tbSC sc WITH (NOLOCK) ON sc.cNoSc = op.cNoSc

    LEFT JOIN (
        -- Use tbSRJDtl as the authoritative SRJ-detail source (contains cNoOp)
        SELECT d.cNoOp,
               SUM(ISNULL(d.nQty,0)) AS qty_kirim,
               SUM(CASE WHEN ISNULL(d.cTipe,'') LIKE 'SF%' AND ISNULL(d.nPanjang,0) <= 0
                        THEN ISNULL(d.nQty,0)
                        ELSE ISNULL(d.nQty,0) * ISNULL(d.nBrtOp,0)
                   END) AS kg_srj
        FROM tbSRJDtl d WITH (NOLOCK)
        GROUP BY d.cNoOp
    ) srj ON srj.cNoOp = op.cNoOp

    LEFT JOIN (
        -- Aggregate returns by mapping return.cNoSrj -> tbSRJDtl.cNoOp
        SELECT dtl.cNoOp,
               SUM(ISNULL(r.nBerat,0) * ISNULL(r.nQty,0)) AS kg_retur
        FROM vwReturnSrj r WITH (NOLOCK)
        LEFT JOIN tbSRJDtl dtl WITH (NOLOCK) ON dtl.cNoSRJ = r.cNoSrj
        GROUP BY dtl.cNoOp
    ) r ON r.cNoOp = op.cNoOp

    WHERE 1=1";

    if (!empty($where)) {
        $sql .= " AND " . implode(" AND ", $where);
    }

    $sql .= " ORDER BY sc.dTanggal DESC, op.cNoOp DESC";
    if ($limit > 0) {
        $sql .= " OFFSET $offset ROWS FETCH NEXT $limit ROWS ONLY";
    }

    $conn = dbConnect($serverName, $connectionOptions);
    $rows = fetchAll($conn, $sql, $params);

    // Normalize numeric fields
    foreach ($rows as &$r) {
        $r['total_order'] = isset($r['total_order']) ? (int)$r['total_order'] : 0;
        $r['jml_kirim']   = isset($r['jml_kirim'])   ? (int)$r['jml_kirim']   : 0;
        $r['kg_srj']      = isset($r['kg_srj'])      ? (float)$r['kg_srj']    : 0.0;
        $r['kg_retur']    = isset($r['kg_retur'])    ? (float)$r['kg_retur']  : 0.0;
        $r['tonase']      = isset($r['tonase'])      ? (float)$r['tonase']    : 0.0; // tons
    }

    $summary = null;
    if ($includeTotals) {
        // Totals across all matching OPs (ignore pagination)
        $totSql = "SELECT
            SUM(ISNULL(srj.kg_srj,0) - ISNULL(r.kg_retur,0))/1000.0 AS total_tonase,
            SUM(ISNULL(op.nQty,0)) AS total_order,
            COUNT(1) AS total_ops
        FROM tbOP op WITH (NOLOCK)
        LEFT JOIN (
            SELECT d.cNoOp,
                   SUM(CASE WHEN ISNULL(d.cTipe,'') LIKE 'SF%' AND ISNULL(d.nPanjang,0) <= 0
                            THEN ISNULL(d.nQty,0)
                            ELSE ISNULL(d.nQty,0) * ISNULL(d.nBrtOp,0)
                       END) AS kg_srj
            FROM tbSRJDtl d WITH (NOLOCK)
            GROUP BY d.cNoOp
        ) srj ON srj.cNoOp = op.cNoOp
        LEFT JOIN (
            SELECT dtl.cNoOp, SUM(ISNULL(r.nBerat,0) * ISNULL(r.nQty,0)) AS kg_retur
            FROM vwReturnSrj r WITH (NOLOCK)
            LEFT JOIN tbSRJDtl dtl WITH (NOLOCK) ON dtl.cNoSRJ = r.cNoSrj
            GROUP BY dtl.cNoOp
        ) r ON r.cNoOp = op.cNoOp
        LEFT JOIN tbSC sc WITH (NOLOCK) ON sc.cNoSc = op.cNoSc
        WHERE 1=1";

        if (!empty($where)) $totSql .= " AND " . implode(" AND ", $where);
        $totRow = fetchAll($conn, $totSql, $params);
        if (!empty($totRow)) {
            $t = $totRow[0];
            $summary = [
                'total_tonase' => isset($t['total_tonase']) ? (float)$t['total_tonase'] : 0.0,
                'total_order'  => isset($t['total_order']) ? (float)$t['total_order'] : 0.0,
                'total_ops'    => isset($t['total_ops']) ? (int)$t['total_ops'] : 0,
            ];
        }
    }

    sqlsrv_close($conn);

    echo json_encode(['success' => true, 'data' => $rows, 'summary' => $summary], JSON_UNESCAPED_UNICODE);
    exit;
}

// Unsupported action
echo json_encode(['success' => false, 'message' => 'Invalid action'], JSON_UNESCAPED_UNICODE);
