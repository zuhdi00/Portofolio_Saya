<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

$serverName = "spsdmz2";
$connectionOptions = [
    "Database" => "dbSopanusa",
    "Uid"      => "sa",
    "PWD"      => "supracor",
    "ReturnDatesAsStrings" => true,
    "CharacterSet" => "UTF-8"
];

function dbConnect($serverName, $opts) {
    $conn = sqlsrv_connect($serverName, $opts);
    if (!$conn) { echo json_encode(['success'=>false,'message'=>'DB connect failed']); exit; }
    return $conn;
}
function safeStr($v) { if ($v===null) return ''; return is_string($v) ? trim($v) : $v; }

$action = $_GET['action'] ?? 'list';
if (!in_array($action, ['list','mc_suggest'])) {
    echo json_encode(['success'=>false,'message'=>'only list and mc_suggest supported']); exit;
}

$conn = dbConnect($serverName, $connectionOptions);

// ── mc_suggest ────────────────────────────────────────────────────────────────
if ($action === 'mc_suggest') {
    $q = trim($_GET['search'] ?? '');
    $out = ['success'=>true,'data'=>[]];
    if ($q === '' || strlen($q) < 2) { echo json_encode($out); sqlsrv_close($conn); exit; }
    $like = '%' . $q . '%';
    $msql = "SELECT TOP 30
                 op.cNoMc,
                 COUNT(*) AS usage_count,
                 MAX(op.dTgl) AS last_used
             FROM tbOP op WITH (NOLOCK)
             WHERE op.cNoMc IS NOT NULL AND op.cNoMc != '' AND op.cNoMc LIKE ?
             GROUP BY op.cNoMc
             ORDER BY usage_count DESC";
    $mstmt = sqlsrv_query($conn, $msql, [$like], ["QueryTimeout"=>300]);
    if ($mstmt !== false) {
        while ($r = sqlsrv_fetch_array($mstmt, SQLSRV_FETCH_ASSOC)) {
            $out['data'][] = [
                'cNoMc'       => safeStr($r['cNoMc']),
                'usage_count' => (int)$r['usage_count'],
                'last_used'   => safeStr($r['last_used'])
            ];
        }
        sqlsrv_free_stmt($mstmt);
    }
    sqlsrv_close($conn);
    echo json_encode($out, JSON_UNESCAPED_UNICODE);
    exit;
}

// ── list params ───────────────────────────────────────────────────────────────
$mc         = trim($_GET['mc']           ?? '');
$flexo      = trim($_GET['flexo']        ?? '');
$dc         = trim($_GET['dc']           ?? '');
$scNo       = trim($_GET['sc_no']        ?? '');
$search     = trim($_GET['search']       ?? '');
$dateScFrom = trim($_GET['date_sc_from'] ?? '');
$dateScTo   = trim($_GET['date_sc_to']   ?? '');
$dateFrom   = trim($_GET['date_from']    ?? '');
$dateTo     = trim($_GET['date_to']      ?? '');
$limit      = max(1, (int)($_GET['limit']  ?? 200));
$offset     = max(0, (int)($_GET['offset'] ?? 0));

// ── Main SQL — kolom sesuai kebutuhan ─────────────────────────────────────────
// Kolom: No OP, MC, SC, Customer, Item, Sales, Qty Prod, Tonase, Net (netto/box gram),
//        Tinase, Flexo, DC, Plan Corr (pcs), Hasil Corr (pcs),
//        STB (pcs), Sisa (Qty-STB), Sisa Pcs Netto (STB-Sisa)
$sql = "
SELECT
    op.cNoOp                                        AS cNoOp,
    ISNULL(op.cNoSc,  '')                           AS cNoSc,
    ISNULL(op.cNoMc,  '')                           AS cNoMc,
    ISNULL(op.cnm_c,  '')                           AS customer,
    ISNULL(op.cnm_brg,'')                           AS nama_brg,
    ISNULL(sc.cSales, '')                           AS sales,

    -- Tgl OP
    ISNULL(CONVERT(VARCHAR(10), op.dTgl, 23), '')   AS tgl_op,

    -- Qty Produksi
    ISNULL(op.nQty, 0)                              AS qty_prod,

    -- Netto per box (gram)
    ISNULL(op.nTot_netto, 0)                        AS netto,

    -- Tonase (kg) = qty * netto / 1000
    CAST(ISNULL(op.nQty,0) * ISNULL(op.nTot_netto,0) / 1000.0 AS DECIMAL(18,2))  AS tonase,

    -- Tinase (alias tonase, untuk kompatibilitas)
    CAST(ISNULL(op.nQty,0) * ISNULL(op.nTot_netto,0) / 1000.0 AS DECIMAL(18,2))  AS tinase,

    ISNULL(op.cFlexo, '')                           AS cFlexo,
    ISNULL(op.cDC,    '')                           AS cDC,

    -- Plan Corr (pcs) — dari tbCorrDtl
    ISNULL(corr.plan_corr, 0)                       AS plan_corr,

    -- Hasil Corr (pcs) — dari tbHslCorrDtl
    ISNULL(corr.hsl_corr,  0)                       AS hsl_corr,

    -- STB (pcs) — agregat SUM(nQty) dari tbStbBJ (alias jml_stb)
    ISNULL(stb.jml_stb, 0)                       AS jml_stb,

    -- Sisa = max(0, Qty Prod - STB)
    CASE
        WHEN ISNULL(op.nQty,0) - ISNULL(stb.jml_stb,0) > 0
        THEN ISNULL(op.nQty,0) - ISNULL(stb.jml_stb,0)
        ELSE 0
    END                                             AS sisa_qty,

    -- Sisa Pcs Netto = STB - Sisa
    (
        ISNULL(stb.jml_stb,0) -
        CASE
            WHEN ISNULL(op.nQty,0) - ISNULL(stb.jml_stb,0) > 0
            THEN ISNULL(op.nQty,0) - ISNULL(stb.jml_stb,0)
            ELSE 0
        END
    )                                               AS sisa_pcs_netto

FROM tbOP op WITH (NOLOCK)

-- Sales dari tbSC
LEFT JOIN tbSC sc WITH (NOLOCK) ON sc.cNoSc = op.cNoSc

-- STB aggregat per OP
LEFT JOIN (
    SELECT s.cNoOp,
           SUM(ISNULL(s.nQty, 0)) AS jml_stb
    FROM tbStbBJ s WITH (NOLOCK)
    GROUP BY s.cNoOp
) stb ON stb.cNoOp = op.cNoOp

-- Corr aggregat per OP
LEFT JOIN (
    SELECT cNoOp,
           SUM(hsl)      AS hsl_corr,
           SUM(plan_qty) AS plan_corr
    FROM (
        -- Hasil Corr
        SELECT d.cNoOp,
               SUM(ISNULL(d.nHasil, 0)) AS hsl,
               0                         AS plan_qty
        FROM tbHslCorrDtl d WITH (NOLOCK)
        GROUP BY d.cNoOp
        UNION ALL
        -- Plan Corr
        SELECT cd.cNoOp,
               0,
               SUM(ISNULL(cd.nQtyOrder, 0))
        FROM tbCorrDtl cd WITH (NOLOCK)
        GROUP BY cd.cNoOp
    ) t
    GROUP BY cNoOp
) corr ON corr.cNoOp = op.cNoOp
";

// ── WHERE clauses ─────────────────────────────────────────────────────────────
$where  = [];
$params = [];

if ($mc) {
    $where[] = "op.cNoMc LIKE ?";
    $params[] = '%'.$mc.'%';
}
if ($flexo) {
    $where[] = "op.cFlexo = ?";
    $params[] = $flexo;
}
if ($dc) {
    $where[] = "op.cDC = ?";
    $params[] = $dc;
}
if ($scNo) {
    $where[] = "(op.cNoSc LIKE ? OR op.cNoOp LIKE ?)";
    $params[] = '%'.$scNo.'%';
    $params[] = $scNo.'%';
}
if ($search) {
    $where[] = "(op.cNoOp LIKE ? OR op.cNoSc LIKE ? OR op.cNoMc LIKE ? OR op.cnm_brg LIKE ? OR op.cnm_c LIKE ?)";
    $like = '%'.$search.'%';
    $params[] = $like; $params[] = $like; $params[] = $like;
    $params[] = $like; $params[] = $like;
}
// Tanggal: prioritas date_sc_from/to (filter tgl OP dari UI)
if ($dateScFrom) {
    $where[] = "op.dTgl >= ?"; $params[] = $dateScFrom;
} elseif ($dateFrom) {
    $where[] = "op.dTgl >= ?"; $params[] = $dateFrom;
}
if ($dateScTo) {
    $where[] = "op.dTgl <= ?"; $params[] = $dateScTo.' 23:59:59';
} elseif ($dateTo) {
    $where[] = "op.dTgl <= ?"; $params[] = $dateTo.' 23:59:59';
}

if (!empty($where)) {
    $sql .= " WHERE " . implode(' AND ', $where);
}

// ── Count ─────────────────────────────────────────────────────────────────────
$countSql = "SELECT COUNT(*) AS total FROM tbOP op WITH (NOLOCK)";
if (!empty($where)) $countSql .= " WHERE " . implode(' AND ', $where);

$cStmt = sqlsrv_query($conn, $countSql, $params, ["QueryTimeout"=>600]);
$total = 0;
if ($cStmt) {
    $crow  = sqlsrv_fetch_array($cStmt, SQLSRV_FETCH_ASSOC);
    $total = (int)($crow['total'] ?? 0);
    sqlsrv_free_stmt($cStmt);
}

// ── TOP-based paging (kompatibel dengan semua versi SQL Server) ───────────────
$fetchTop = $offset + $limit;
$sql = preg_replace('/^\s*SELECT\s+/i', "SELECT TOP $fetchTop ", $sql, 1);
$sql .= " ORDER BY op.dTgl DESC, op.cNoOp DESC";

// ── Execute ───────────────────────────────────────────────────────────────────
$stmt = sqlsrv_query($conn, $sql, $params, ["QueryTimeout"=>600]);
if ($stmt === false) {
    $errs = sqlsrv_errors();
    sqlsrv_close($conn);
    echo json_encode(['success'=>false,'message'=>'Query failed','errors'=>$errs], JSON_UNESCAPED_UNICODE);
    exit;
}

$no   = $offset + 1;
$raw  = [];
while ($r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $clean = [];
    foreach ($r as $k => $v) { $clean[$k] = is_string($v) ? safeStr($v) : $v; }
    $raw[] = $clean;
}
sqlsrv_free_stmt($stmt);
sqlsrv_close($conn);

// Slice ke halaman yang diminta
$rows = array_slice($raw, $offset, $limit);

// Nomor urut
foreach ($rows as &$r) { $r['no'] = $no++; }
unset($r);

echo json_encode([
    'success' => true,
    'data'    => $rows,
    'pagination' => [
        'total_records'  => $total,
        'total_pages'    => ceil($total / $limit),
        'current_page'   => floor($offset / $limit) + 1,
        'records_per_page'=> $limit,
        'offset'         => $offset,
        'has_prev'       => $offset > 0,
        'has_next'       => ($offset + $limit) < $total,
    ]
], JSON_UNESCAPED_UNICODE);
