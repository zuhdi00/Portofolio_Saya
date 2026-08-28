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
if (!in_array($action, ['list','mc_suggest','check_sc'])) {
    echo json_encode(['success'=>false,'message'=>'only list, mc_suggest, and check_sc supported']); exit;
}

$conn = dbConnect($serverName, $connectionOptions);

// Mode ringan untuk pengecekan keberadaan nomor SC saja.
if ($action === 'check_sc') {
    $scNoCheck = trim($_GET['sc_no'] ?? '');
    if ($scNoCheck === '') {
        echo json_encode(['success'=>false,'message'=>'sc_no wajib diisi untuk action=check_sc']);
        sqlsrv_close($conn);
        exit;
    }

    $scNoCheck = trim($scNoCheck);
    $checkSql = "SELECT TOP 1 'tbSC.cNoSC' AS sumber, LTRIM(RTRIM(cNoSC)) AS nomor
        FROM tbSC WITH (NOLOCK)
        WHERE LTRIM(RTRIM(cNoSC)) = ?";
    $checkStmt = sqlsrv_query($conn, $checkSql, [$scNoCheck], ["QueryTimeout"=>60]);
    if ($checkStmt === false) {
        $errors = sqlsrv_errors();
        sqlsrv_close($conn);
        echo json_encode(['success'=>false,'message'=>'Pengecekan SC gagal','errors'=>$errors], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $found = sqlsrv_fetch_array($checkStmt, SQLSRV_FETCH_ASSOC);
    sqlsrv_free_stmt($checkStmt);
    sqlsrv_close($conn);
    echo json_encode([
        'success'=>true,
        'exists'=>(bool)$found,
        'sc_no'=>$scNoCheck,
        'sumber'=>$found['sumber'] ?? null,
        'nomor'=>$found['nomor'] ?? null
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

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
// Shipment date filters from UI; apply them to dTglkirim2 (shipment date)
$dateScFrom = trim($_GET['date_sc_from'] ?? '');
$dateScTo   = trim($_GET['date_sc_to'] ?? '');
$dateOpFrom = trim($_GET['date_op_from'] ?? '');
$dateOpTo   = trim($_GET['date_op_to'] ?? '');
$status     = trim($_GET['status'] ?? ''); // '', 'OPEN', or 'CLOSE'
$search = trim($_GET['search'] ?? '');
$customer = trim($_GET['customer'] ?? '');
$item = trim($_GET['item'] ?? '');
$limit = (int)($_GET['limit'] ?? 200);
$offset = (int)($_GET['offset'] ?? 0);
$filteredOpsTable = '##realisasi_filtered_ops_' . getmypid() . '_' . bin2hex(random_bytes(4));

// Build SQL (tbOP as base, no tbSC/tbSRJ)
$sql = "SELECT
    op.cNoOp AS cNoOp,
    ISNULL(op.cnm_c,'') AS customer,
    ISNULL(op.cnm_brg,'') AS nama_brg,
    ISNULL(sc.cSales, '') AS sales,
    CASE WHEN op.cNoOp NOT LIKE '%B01%' THEN 'OU' ELSE ISNULL(sc.cJnsSc, '') END AS keterangan,
    ISNULL(op.cTipe, '') AS type,
    CONCAT(CAST(ISNULL(op.nBrutto_usl,0) AS VARCHAR), ' x ', CAST(ISNULL(op.nBrutto_usp,0) AS VARCHAR)) AS ukuran_corr,
    ISNULL(sc.cStatus, 'OPEN') AS sc_status,
    ISNULL(op.cNoMc,'') AS cNoMc,
    ISNULL(op.cNoSc,'') AS cNoSc,
    -- op date (used as Tgl OP / mapped from SC filter in UI)
    ISNULL(CONVERT(VARCHAR(19), op.dTgl, 120),'') AS tgl_op,
    ISNULL(CONVERT(VARCHAR(10), op.dTglkirim2, 120),'') AS tgl_kirim,
    -- Qty Produksi: gunakan nQtyStok dari tbOP
    ISNULL(op.nQtyStok,0) AS qty_prod,
    -- netto per box (gram)
    ISNULL(op.nTot_netto,0) AS netto,
    -- tonase in kg: qty_stok * netto(g) / 1000
    CAST(ISNULL(op.nQtyStok,0) * ISNULL(op.nTot_netto,0) AS DECIMAL(18,2)) AS tonase,
    -- duplicate tonase as 'tinase' for compatibility
    -- CAST(ISNULL(op.nQtyStok,0) * ISNULL(op.nTot_netto,0) / 1000.0 AS DECIMAL(18,2)) AS tinase,
    ISNULL(op.cFlexo,'') AS cFlexo,
    ISNULL(op.cDC,'') AS cDC,
    ISNULL([plan].plan_pcs,0) AS plan_corr,
    ISNULL([hsl].hsl_corr,0) AS hsl_corr,
    ISNULL([stb].jml_stb,0) AS jml_stb,
    -- Beban Corr: plan_pcs - hsl_pcs (pcs) dan kg
    CASE WHEN ISNULL([plan].plan_pcs,0) - ISNULL([hsl].hsl_corr,0) > 0 THEN ISNULL([plan].plan_pcs,0) - ISNULL([hsl].hsl_corr,0) ELSE 0 END AS beban_corr_pcs,
    CASE WHEN ISNULL([plan].plan_kg,0) - ISNULL([hsl].hsl_corr_kg,0) > 0 THEN ISNULL([plan].plan_kg,0) - ISNULL([hsl].hsl_corr_kg,0) ELSE 0 END AS beban_corr_kg,
    -- Beban Conv: hsl_corr - jml_stb (pcs) dan kg
    CASE WHEN ISNULL([hsl].hsl_corr,0) - ISNULL([stb].jml_stb,0) > 0 THEN ISNULL([hsl].hsl_corr,0) - ISNULL([stb].jml_stb,0) ELSE 0 END AS beban_conv_pcs,
    CASE WHEN ISNULL([hsl].hsl_corr_kg,0) - ISNULL([stb].jml_stb_kg,0) > 0 THEN ISNULL([hsl].hsl_corr_kg,0) - ISNULL([stb].jml_stb_kg,0) ELSE 0 END AS beban_conv_kg,
    -- Beban JO: 1 jika plan_corr=0 AND hsl_corr=0 AND jml_stb=0
    CASE WHEN ISNULL([plan].plan_pcs,0)=0 AND ISNULL([hsl].hsl_corr,0)=0 AND ISNULL([stb].jml_stb,0)=0 THEN 1 ELSE 0 END AS is_beban_jo,
    -- Total STB data with kg
    ISNULL([stb].jml_stb,0) AS jml_stb_filtered,
    ISNULL([stb].jml_stb_kg,0) AS jml_stb_filtered_kg
FROM tbOP op WITH (NOLOCK)

INNER JOIN $filteredOpsTable filtered_ops
    ON filtered_ops.cNoOp = op.cNoOp

LEFT JOIN tbSC sc WITH (NOLOCK) ON sc.cNoSc = op.cNoSc

LEFT JOIN (
    SELECT s.cNoOp, SUM(ISNULL(s.nQty,0)) AS jml_stb, SUM(ISNULL(s.nQtyKg,0)) AS jml_stb_kg
    FROM tbStbBJ s WITH (NOLOCK)
    INNER JOIN $filteredOpsTable filtered_stb ON filtered_stb.cNoOp = s.cNoOp
    GROUP BY s.cNoOp
) [stb] ON [stb].cNoOp = op.cNoOp

LEFT JOIN (
    SELECT cNoOp, SUM(hsl_pcs) AS hsl_corr, SUM(hsl_kg) AS hsl_corr_kg
    FROM (
        SELECT d.cNoOp, SUM(ISNULL(d.nHasil,0)) AS hsl_pcs, SUM(ISNULL(d.nBerat,0)) AS hsl_kg
        FROM tbHslCorrDtl d WITH (NOLOCK)
        INNER JOIN $filteredOpsTable filtered_hsl ON filtered_hsl.cNoOp = d.cNoOp
        GROUP BY d.cNoOp
    ) t
    GROUP BY cNoOp
) [hsl] ON [hsl].cNoOp = op.cNoOp

LEFT JOIN (
    SELECT cd.cNoOp, SUM(ISNULL(cd.nQtyOrder,0)) AS plan_pcs, 
           SUM(ISNULL(cd.nQtyOrder,0) * ISNULL(op2.nTot_netto,0) / 1000.0) AS plan_kg
    FROM tbCorrDtl cd WITH (NOLOCK)
    LEFT JOIN tbOP op2 WITH (NOLOCK) ON op2.cNoOp = cd.cNoOp
        INNER JOIN $filteredOpsTable filtered_plan ON filtered_plan.cNoOp = cd.cNoOp
    GROUP BY cd.cNoOp
) [plan] ON [plan].cNoOp = op.cNoOp

";

$where = [];
$params = [];
// Include rows whose OP has an SLC number, or OP numbers ending with -PS.
$where[] = "(
    (
        op.cNoSc IS NOT NULL
        AND LTRIM(RTRIM(op.cNoSc)) <> ''
        AND (
            EXISTS (
                SELECT 1
                FROM tbSC sc_exists WITH (NOLOCK)
                WHERE LTRIM(RTRIM(sc_exists.cNoSc)) = LTRIM(RTRIM(op.cNoSc))
            )
        )
    )
    OR RIGHT(RTRIM(op.cNoOp), 3) = '-PS'
)";
// Status filter (tbSC.cStatus): only applied when the user explicitly picks OPEN or CLOSE.
// Left empty ('Semua'), no status filtering is applied at all.
if ($status === 'OPEN') {
    $where[] = "UPPER(LTRIM(RTRIM(ISNULL(sc.cStatus,'')))) IN ('', 'OPEN', '-')";
} elseif ($status === 'CLOSE') {
    $where[] = "UPPER(LTRIM(RTRIM(ISNULL(sc.cStatus,'')))) = 'CLOSE'";
}
if ($mc) { $where[] = "op.cNoMc LIKE ?"; $params[] = '%'.$mc.'%'; }
if ($flexo) { $where[] = "op.cFlexo = ?"; $params[] = $flexo; }
if ($scNo) { $where[] = "(op.cNoSc LIKE ? OR op.cNoOp LIKE ?)"; $params[] = '%'.$scNo.'%'; $params[] = '%'.$scNo . '%'; }
if ($customer) { $where[] = "op.cnm_c LIKE ?"; $params[] = '%'.$customer.'%'; }
if ($item) { $where[] = "op.cnm_brg LIKE ?"; $params[] = '%'.$item.'%'; }
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
// Prefer Shipment date filter if provided (UI uses SC date inputs for shipment date)
if ($dateScFrom) {
    $where[] = "op.dTglkirim2 >= ?"; $params[] = $dateScFrom;
} elseif ($dateFrom) {
    $where[] = "op.dTglkirim2 >= ?"; $params[] = $dateFrom;
}
if ($dateScTo) {
    $where[] = "op.dTglkirim2 <= ?"; $params[] = $dateScTo . ' 23:59:59';
} elseif ($dateTo) {
    $where[] = "op.dTglkirim2 <= ?"; $params[] = $dateTo . ' 23:59:59';
}
if ($shipFrom && $shipTo) {
    $where[] = "op.dTglkirim2 >= ? AND op.dTglkirim2 <= ?";
    $params[] = $shipFrom; $params[] = $shipTo . ' 23:59:59';
} elseif ($shipFrom) {
    $where[] = "op.dTglkirim2 >= ?"; $params[] = $shipFrom;
} elseif ($shipTo) {
    $where[] = "op.dTglkirim2 <= ?"; $params[] = $shipTo . ' 23:59:59';
}

if ($dateOpFrom) {
    $where[] = "op.dTgl >= ?"; $params[] = $dateOpFrom . ' 00:00:00';
}
if ($dateOpTo) {
    $where[] = "op.dTgl <= ?"; $params[] = $dateOpTo . ' 23:59:59';
}

$filterSql = implode(' AND ', $where);
$filterStmt = sqlsrv_query($conn, "SELECT DISTINCT op.cNoOp
    INTO $filteredOpsTable
    FROM tbOP op WITH (NOLOCK)
    LEFT JOIN tbSC sc WITH (NOLOCK) ON sc.cNoSc = op.cNoSc
    WHERE $filterSql", $params, ["QueryTimeout"=>600]);
if ($filterStmt === false) {
    $errors = sqlsrv_errors();
    sqlsrv_close($conn);
    echo json_encode(['success'=>false,'message'=>'Filter OP gagal','errors'=>$errors], JSON_UNESCAPED_UNICODE);
    exit;
}
sqlsrv_free_stmt($filterStmt);
$indexStmt = sqlsrv_query($conn, "CREATE CLUSTERED INDEX IX_realisasi_filtered_ops ON $filteredOpsTable(cNoOp)");
if ($indexStmt !== false) sqlsrv_free_stmt($indexStmt);
$params = [];

// count
$countSql = "SELECT COUNT(*) AS total FROM $filteredOpsTable";

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

$sql .= " OPTION (RECOMPILE)";

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
