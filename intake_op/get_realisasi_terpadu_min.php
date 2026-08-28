<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

$serverName = "spsdmz2";
$connectionOptions = [
    "Database" => "dbSopanusa",
    "Uid" => "sa",
    "PWD" => "supracor",
    "ReturnDatesAsStrings" => true,
    "CharacterSet" => "UTF-8"
];

function dbConnect($serverName, $opts) {
    $conn = sqlsrv_connect($serverName, $opts);
    if (!$conn) { echo json_encode(['success'=>false,'message'=>'DB connect failed']); exit; }
    return $conn;
}
function safeStr($v){ if ($v===null) return ''; return is_string($v)? trim($v) : $v; }

$action = $_GET['action'] ?? 'list';
if (!in_array($action, ['list','mc_suggest'])) {
    echo json_encode(['success'=>false,'message'=>'only list and mc_suggest supported']); exit;
}

$conn = dbConnect($serverName, $connectionOptions);

// mc_suggest: return distinct MC (cNoMc) from tbOP for autocomplete
if ($action === 'mc_suggest') {
    $q = trim($_GET['search'] ?? '');
    $out = ['success'=>true,'data'=>[]];
    if ($q === '' || strlen($q) < 2) { echo json_encode($out); sqlsrv_close($conn); exit; }
    $like = '%' . $q . '%';
    $msql = "SELECT DISTINCT TOP 30 ISNULL(cNoMc,'') AS cNoMc FROM tbOP WITH (NOLOCK) WHERE cNoMc LIKE ? ORDER BY cNoMc";
    $mstmt = sqlsrv_query($conn, $msql, [$like], ["QueryTimeout"=>300]);
    if ($mstmt !== false) {
        while ($r = sqlsrv_fetch_array($mstmt, SQLSRV_FETCH_ASSOC)) { $out['data'][] = $r['cNoMc']; }
        sqlsrv_free_stmt($mstmt);
    }
    sqlsrv_close($conn);
    echo json_encode($out, JSON_UNESCAPED_UNICODE);
    exit;
}
// params
$mc = trim($_GET['mc'] ?? '');
$flexo = trim($_GET['flexo'] ?? '');
$shipFrom = trim($_GET['ship_from'] ?? '');
$shipTo = trim($_GET['ship_to'] ?? '');
$scNo = trim($_GET['sc_no'] ?? '');
$dateFrom = trim($_GET['date_from'] ?? '');
$dateTo = trim($_GET['date_to'] ?? '');
// Accept SC date filters from UI; apply them to op.dTgl (tbOP.dTgl)
$dateScFrom = trim($_GET['date_sc_from'] ?? '');
$dateScTo   = trim($_GET['date_sc_to'] ?? '');
$search = trim($_GET['search'] ?? '');
$limit = (int)($_GET['limit'] ?? 200);
$offset = (int)($_GET['offset'] ?? 0);

// Build SQL (tbOP as base, no tbSC/tbSRJ)
$sql = "SELECT
    op.cNoOp AS cNoOp,
    ISNULL(op.cnm_c,'') AS customer,
    ISNULL(op.cnm_brg,'') AS nama_brg,
    ISNULL(sc.cSales, '') AS sales,
    ISNULL(op.cNoMc,'') AS cNoMc,
    ISNULL(op.cNoSc,'') AS cNoSc,
    -- op date (used as Tgl OP / mapped from SC filter in UI)
    ISNULL(CONVERT(VARCHAR(19), op.dTgl, 120),'') AS tgl_op,
    -- Qty Produksi: gunakan nQtyStok dari tbOP
    ISNULL(op.nQtyStok,0) AS qty_prod,
    -- netto per box (gram)
    ISNULL(op.nTot_netto,0) AS netto,
    -- tonase in kg: qty_stok * netto(g) / 1000
    CAST(ISNULL(op.nQtyStok,0) * ISNULL(op.nTot_netto,0) / 1000.0 AS DECIMAL(18,2)) AS tonase,
    -- duplicate tonase as 'tinase' for compatibility
    CAST(ISNULL(op.nQtyStok,0) * ISNULL(op.nTot_netto,0) / 1000.0 AS DECIMAL(18,2)) AS tinase,
    ISNULL(op.cFlexo,'') AS cFlexo,
    ISNULL(op.cDC,'') AS cDC,
    ISNULL(corr.plan_corr,0) AS plan_corr,
    ISNULL(corr.hsl_corr,0) AS hsl_corr,
    ISNULL(stb.jml_stb,0) AS jml_stb,
    -- sisa qty = max(0, nQtyStok - jml_stb)
    CASE WHEN ISNULL(op.nQtyStok,0) - ISNULL(stb.jml_stb,0) > 0 THEN ISNULL(op.nQtyStok,0) - ISNULL(stb.jml_stb,0) ELSE 0 END AS sisa_qty,
    -- sisa pcs Netto = jml_stb - sisa_qty
    (ISNULL(stb.jml_stb,0) - CASE WHEN ISNULL(op.nQtyStok,0) - ISNULL(stb.jml_stb,0) > 0 THEN ISNULL(op.nQtyStok,0) - ISNULL(stb.jml_stb,0) ELSE 0 END) AS sisa_pcs_netto
FROM tbOP op WITH (NOLOCK)

LEFT JOIN tbSC sc WITH (NOLOCK) ON sc.cNoSc = op.cNoSc

LEFT JOIN (
    SELECT s.cNoOp, SUM(ISNULL(s.nQty,0)) AS jml_stb
    FROM tbStbBJ s WITH (NOLOCK)
    GROUP BY s.cNoOp
) stb ON stb.cNoOp = op.cNoOp

LEFT JOIN (
    SELECT cNoOp, SUM(hsl) AS hsl_corr, SUM(rusak) AS rsak_corr, SUM(plan_qty) AS plan_corr
    FROM (
        SELECT d.cNoOp, SUM(ISNULL(d.nHasil,0)) AS hsl, SUM(ISNULL(d.nRusak,0)) AS rusak, 0 AS plan_qty
        FROM tbHslCorrDtl d WITH (NOLOCK)
        GROUP BY d.cNoOp
        UNION ALL
        SELECT cd.cNoOp, 0, 0, SUM(ISNULL(cd.nQtyOrder,0))
        FROM tbCorrDtl cd WITH (NOLOCK)
        GROUP BY cd.cNoOp
    ) t
    GROUP BY cNoOp
) corr ON corr.cNoOp = op.cNoOp

";

$where = [];
$params = [];
if ($mc) { $where[] = "op.cNoMc LIKE ?"; $params[] = '%'.$mc.'%'; }
if ($flexo) { $where[] = "op.cFlexo = ?"; $params[] = $flexo; }
if ($scNo) { $where[] = "(op.cNoSc LIKE ? OR op.cNoOp LIKE ?)"; $params[] = '%'.$scNo.'%'; $params[] = $scNo . '%'; }
if ($search) {
    // Flexible search: allow entering partial OP like '2605/00012' or MC/item/customer
    $where[] = "(op.cNoOp LIKE ? OR op.cNoOp LIKE ? OR op.cNoSc LIKE ? OR op.cNoMc LIKE ? OR op.cnm_brg LIKE ? OR op.cnm_c LIKE ? )";
    $params[] = $search . '%';        // matches prefix searches
    $params[] = '%' . $search . '%';   // matches fragments anywhere
    $params[] = '%' . $search . '%';
    $params[] = '%' . $search . '%';
    $params[] = '%' . $search . '%';
    $params[] = '%' . $search . '%';
}
// Prefer SC date filter if provided (UI uses SC date inputs)
if ($dateScFrom) {
    $where[] = "op.dTgl >= ?"; $params[] = $dateScFrom;
} elseif ($dateFrom) {
    $where[] = "op.dTgl >= ?"; $params[] = $dateFrom;
}
if ($dateScTo) {
    $where[] = "op.dTgl <= ?"; $params[] = $dateScTo . ' 23:59:59';
} elseif ($dateTo) {
    $where[] = "op.dTgl <= ?"; $params[] = $dateTo . ' 23:59:59';
}
if ($shipFrom && $shipTo) {
    $where[] = "COALESCE(op.dTglkirim2, op.dTglkirim) >= ? AND COALESCE(op.dTglkirim2, op.dTglkirim) <= ?";
    $params[] = $shipFrom; $params[] = $shipTo . ' 23:59:59';
} elseif ($shipFrom) {
    $where[] = "COALESCE(op.dTglkirim2, op.dTglkirim) >= ?"; $params[] = $shipFrom;
} elseif ($shipTo) {
    $where[] = "COALESCE(op.dTglkirim2, op.dTglkirim) <= ?"; $params[] = $shipTo . ' 23:59:59';
}

if (!empty($where)) { $sql .= " WHERE " . implode(' AND ', $where); }

// count
$countSql = "SELECT COUNT(*) AS total FROM tbOP op WITH (NOLOCK)";
if (!empty($where)) $countSql .= " WHERE " . implode(' AND ', $where);

$cStmt = sqlsrv_query($conn, $countSql, $params, ["QueryTimeout"=>600]);
$total = 0; if ($cStmt) { $crow = sqlsrv_fetch_array($cStmt, SQLSRV_FETCH_ASSOC); $total = (int)($crow['total'] ?? 0); sqlsrv_free_stmt($cStmt); }

// paging: avoid OFFSET/FETCH to support servers with varying SQL support.
// When $limit>0 we use a TOP() prefix to limit rows and slice in PHP.
if ($limit > 0) {
    $fetchTop = $offset + $limit;
    // inject TOP N after the initial SELECT
    $sql = preg_replace('/^SELECT\s+/i', "SELECT TOP $fetchTop ", $sql, 1);
    // ensure there is a deterministic ORDER BY for stable paging
       $sql .= " ORDER BY op.dTgl DESC, op.cNoOp DESC";
}

$stmt = sqlsrv_query($conn, $sql, $params, ["QueryTimeout"=>600]);
$rows = [];
if ($stmt === false) {
    $errs = sqlsrv_errors();
    sqlsrv_close($conn);
    echo json_encode(['success'=>false,'message'=>'Query failed','errors'=>$errs,'debug'=>['sql'=>$sql,'params'=>$params]], JSON_UNESCAPED_UNICODE);
    exit;
} else {
    while ($r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $clean = [];
        foreach ($r as $k=>$v) { $clean[$k] = is_string($v)? safeStr($v) : $v; }
        $rows[] = $clean;
    }
    sqlsrv_free_stmt($stmt);
}

// If we used TOP-based paging, slice the results to the requested page
if ($limit > 0 && count($rows) > 0) {
    if ($offset > 0) {
        $rows = array_slice($rows, $offset, $limit);
    } else {
        $rows = array_slice($rows, 0, $limit);
    }
}

// If count says there are rows but fetch returned none, include SQL for debugging
if (empty($rows) && isset($total) && $total > 0) {
    sqlsrv_close($conn);
    echo json_encode(['success'=>true,'data'=>[],'pagination'=>['total_records'=>$total,'total_pages'=> $limit>0 ? ceil($total / $limit) : 1,'current_page'=> $limit>0 ? floor($offset/$limit)+1 : 1,'records_per_page'=>$limit,'offset'=>$offset,'has_prev'=>$offset>0,'has_next'=> ($offset+$limit) < $total],'debug'=>['sql'=>$sql,'params'=>$params]], JSON_UNESCAPED_UNICODE);
    exit;
}
sqlsrv_close($conn);

// add sequential numbering
$no = $offset + 1;
foreach ($rows as &$r) { $r['no'] = $no++; }
unset($r);

echo json_encode([
    'success'=>true,
    'data'=>$rows,
    'pagination'=>[
        'total_records'=>(int)$total,
        'total_pages'=> $limit>0 ? ceil($total / $limit) : 1,
        'current_page'=> $limit>0 ? floor($offset/$limit)+1 : 1,
        'records_per_page'=>$limit,
        'offset'=>$offset,
        'has_prev'=>$offset>0,
        'has_next'=> ($offset+$limit) < $total
    ]
], JSON_UNESCAPED_UNICODE);
