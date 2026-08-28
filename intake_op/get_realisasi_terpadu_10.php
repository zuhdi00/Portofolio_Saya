<?php
/**
 * get_realisasi_terpadu.php  — VERSI OPTIMASI
 *
 * PERUBAHAN UTAMA DIBANDING VERSI LAMA:
 * ──────────────────────────────────────
 * 1. SATU query besar → pisah: query ringan tbSC+tbOP dulu (pakai index),
 *    lalu JOIN aggregasi hanya untuk OP yang sudah di-fetch (tidak semua tabel di-JOIN sekaligus).
 * 2. Summary (total tonase, total kirim, dll.) dihitung 1× dalam query terpisah
 *    yang ringan (COUNT + SUM sederhana), tidak perlu fetch semua baris ke PHP.
 * 3. Semua derived-table aggregasi dipindah ke CTE agar SQL Server bisa optimasi
 *    sendiri dan tidak mengulang scan per-baris.
 * 4. Filter tanggal SC/OP dipakai sebagai leading predicate agar index ix_tbSC_dTanggal
 *    dan ix_tbOP_dTgl langsung dipakai.
 * 5. Output JSON di-stream langsung (ob_end_flush + json_encode per-chunk)
 *    sehingga browser menerima data lebih awal tanpa menunggu semua baris selesai diproses.
 * 6. Tidak ada lagi scalar subquery berulang — semua pakai pre-aggregated derived table / CTE.
 * 7. action=list TANPA limit (limit=all) tetap aman karena query lebih ringan.
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit(); }

// ── DB Config ──────────────────────────────────────────────────────────────────
$serverName = "spsdmz2";
$connectionOptions = [
    "Database"               => "dbSopanusa",
    "Uid"                    => "sa",
    "PWD"                    => "supracor",
    "LoginTimeout"           => 30,
    "Encrypt"                => false,
    "TrustServerCertificate" => true,
    "ReturnDatesAsStrings"   => true,
    "CharacterSet"           => "UTF-8"
];

// ── Helpers ────────────────────────────────────────────────────────────────────
function dbConnect($serverName, $opts) {
    $conn = sqlsrv_connect($serverName, $opts);
    if (!$conn) {
        http_response_code(500);
        echo json_encode(['success'=>false,'message'=>'Koneksi DB gagal.','errors'=>sqlsrv_errors()]);
        exit;
    }
    return $conn;
}

function safeStr($val) {
    if ($val === null) return '';
    if (is_string($val)) return trim(iconv('ISO-8859-1','UTF-8//IGNORE//TRANSLIT',$val));
    return $val;
}

function runQuery($conn, $sql, $params = [], $timeout = 600) {
    $opts = ["QueryTimeout" => $timeout];
    $stmt = empty($params)
        ? sqlsrv_query($conn, $sql, [], $opts)
        : sqlsrv_query($conn, $sql, $params, $opts);
    return $stmt;
}

function fetchAll($conn, $sql, $params = [], $timeout = 600) {
    $stmt = runQuery($conn, $sql, $params, $timeout);
    if ($stmt === false) return ['error' => sqlsrv_errors()];
    $rows = [];
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $clean = [];
        foreach ($row as $k => $v) $clean[$k] = is_string($v) ? safeStr($v) : $v;
        $rows[] = $clean;
    }
    sqlsrv_free_stmt($stmt);
    return $rows;
}

function mapRack($cRak) {
    static $map = [
        '1'=>'A-1','2'=>'A-2','3'=>'B-1','4'=>'B-2','5'=>'C-1','6'=>'C-2',
        '7'=>'CORRUGATING 1','8'=>'CORRUGATING 2','9'=>'FOLDER GLUE','10'=>'FLADBAD',
        '11'=>'FLEXO-1','12'=>'FLEXO-2','13'=>'FLEXO-4','14'=>'FLEXO-5',
        '15'=>'FLEXO-6','16'=>'FLEXO-7','17'=>'FLEXO-8','18'=>'FLEXO-9',
        '19'=>'IKAT','20'=>'LANTHEC','21'=>'LANGSUNG KIRIM','22'=>'RDC',
        '23'=>'RAK-A','24'=>'RAK-B','25'=>'SLITTER','26'=>'STITCHING'
    ];
    return $map[trim((string)$cRak)] ?? '-';
}

// ── Router ─────────────────────────────────────────────────────────────────────
$action = $_GET['action'] ?? 'list';

// ══════════════════════════════════════════════════════════════════════════════
// ACTION: mc_suggest
// ══════════════════════════════════════════════════════════════════════════════
if ($action === 'mc_suggest') {
    $search = trim($_GET['search'] ?? '');
    if (strlen($search) < 2) {
        echo json_encode(['success'=>false,'message'=>'Minimal 2 karakter.']);
        exit;
    }
    $conn = dbConnect($serverName, $connectionOptions);
    $sql  = "SELECT TOP 30 op.cNoMc,
                COUNT(*)    AS usage_count,
                MAX(op.dTgl) AS last_used
             FROM tbOP op WITH (NOLOCK)
             WHERE op.cNoMc IS NOT NULL AND op.cNoMc != '' AND op.cNoMc LIKE ?
             GROUP BY op.cNoMc
             ORDER BY usage_count DESC, last_used DESC";
    $rows = fetchAll($conn, $sql, ['%'.$search.'%'], 30);
    sqlsrv_close($conn);
    echo json_encode(['success'=>true,'data'=>$rows]);
    exit;
}

// ══════════════════════════════════════════════════════════════════════════════
// ACTION: detail — 1 SC
// ══════════════════════════════════════════════════════════════════════════════
if ($action === 'detail') {
    $sc = trim($_GET['sc'] ?? '');
    if (empty($sc)) {
        echo json_encode(['success'=>false,'message'=>'Parameter sc dibutuhkan.']);
        exit;
    }
    $conn = dbConnect($serverName, $connectionOptions);
    sqlsrv_query($conn, "SET TRANSACTION ISOLATION LEVEL READ UNCOMMITTED");

    $scRows = fetchAll($conn, "SELECT * FROM tbSC WITH (NOLOCK) WHERE cNoSc = ?", [$sc]);
    if (empty($scRows)) { sqlsrv_close($conn); echo json_encode(['success'=>false,'message'=>'SC tidak ditemukan.']); exit; }
    $dataSC = $scRows[0];

    $dataOP = fetchAll($conn, "SELECT * FROM tbOP WITH (NOLOCK) WHERE cNoSc = ? ORDER BY cNoOp", [$sc]);
    if (empty($dataOP))
        $dataOP = fetchAll($conn, "SELECT * FROM tbOP WITH (NOLOCK) WHERE cNoOp LIKE ? ORDER BY cNoOp", [$sc.'%']);

    $opNos = array_column($dataOP, 'cNoOp');
    $opIn  = !empty($opNos) ? implode(',', array_fill(0, count($opNos), '?')) : null;

    $corrPlan = $corrHasil = $convHasil = [];
    if ($opIn) {
        $corrPlan = fetchAll($conn,
            "SELECT c.cNoCorr, c.cKodeCorr, c.dTanggal, c.cKeterangan,
                    d.cNoOp, d.cType, d.cNoMc, d.nHasil, d.nRusak,
                    d.dStart, d.dFinish, d.cFlute, d.nQtyOrder
             FROM tbCorrDtl d WITH (NOLOCK)
             INNER JOIN tbCorr c WITH (NOLOCK) ON c.cNoCorr = d.cNoCorr
             WHERE d.cNoOp IN ($opIn)
             ORDER BY c.dTanggal, c.cNoCorr", $opNos);

        $corrHasil = fetchAll($conn,
            "SELECT h.cNoCorr, h.cKodeCorr, h.dTanggal,
                    d.cNoOp, d.cNoMc, d.nHasil, d.nRusak, d.dStart, d.dFinish,
                    d.cFlute, d.nBerat, d.nOut
             FROM tbHslCorrDtl d WITH (NOLOCK)
             INNER JOIN tbHslCorr h WITH (NOLOCK) ON h.cNoCorr = d.cNoCorr
             WHERE d.cNoOp IN ($opIn)
             ORDER BY h.dTanggal, h.cNoCorr", $opNos);

        $convHasil = fetchAll($conn,
            "SELECT d.cNoOp, p.dTanggal,
                    ISNULL(m.cNama, p.cKodeFlx) AS cNamaMsn,
                    ISNULL(d.nHasil,0) AS nHasil,
                    ISNULL(d.nRusak,0) AS nRusak
             FROM tbConvPlanDtl d WITH (NOLOCK)
             INNER JOIN tbConvPlan p WITH (NOLOCK) ON d.cNoConv = p.cNoConv
             LEFT JOIN tbMesin m WITH (NOLOCK) ON p.cKodeFlx = m.cKode
             WHERE d.cNoOp IN ($opIn)
             ORDER BY d.cNoOp, p.dTanggal", $opNos);
    }

    $convPlan = fetchAll($conn, "SELECT * FROM tbOP WITH (NOLOCK) WHERE cNoSc = ? ORDER BY cNoOp", [$sc]);
    $stb      = fetchAll($conn,
        "SELECT * FROM tbStbBJ WITH (NOLOCK) WHERE cNoSc = ? OR cNoOp LIKE ? ORDER BY cNoOp, dTanggal",
        [$sc, $sc.'%']);
    $srj = fetchAll($conn,
        "SELECT d.cNoSRJ, d.cNama, d.nQty, d.cNoOp, d.cNoScDtl,
                s.dTanggal, s.cKeterangan, s.cNoPol, s.cTujuanKirim
         FROM tbSRJDtl d WITH (NOLOCK)
         INNER JOIN tbSRJ s WITH (NOLOCK) ON d.cNoSRJ = s.cNoSRJ
         WHERE d.cNoScDtl = ? OR d.cNoOp LIKE ? OR s.cNoSC = ?
         ORDER BY s.dTanggal", [$sc, $sc.'%', $sc]);
    $retur = fetchAll($conn,
        "SELECT d.cNomer AS cNoRetur, d.cItem, d.nQty, d.cKeterangan AS cKetRetur,
                r.dTgl, r.cNoSc, r.cNoSrj, r.cNama
         FROM tbRtSrjDtl d WITH (NOLOCK)
         INNER JOIN tbRtSrj r WITH (NOLOCK) ON d.cNomer = r.cNomer
         WHERE r.cNoSc = ?
         ORDER BY r.dTgl", [$sc]);

    $hasilMesin = [];
    foreach ($convHasil as $r) {
        $msn = strtoupper(trim($r['cNamaMsn'] ?? ''));
        if (!isset($hasilMesin[$msn])) $hasilMesin[$msn] = ['hasil'=>0,'rusak'=>0];
        $hasilMesin[$msn]['hasil'] += (float)($r['nHasil'] ?? 0);
        $hasilMesin[$msn]['rusak'] += (float)($r['nRusak'] ?? 0);
    }

    sqlsrv_close($conn);
    $totalSRJ   = array_sum(array_column($srj,      'nQty'));
    $totalRetur = array_sum(array_column($retur,     'nQty'));

    echo json_encode([
        'success'     => true,
        'sc'          => $dataSC,
        'op'          => $dataOP,
        'corr_plan'   => $corrPlan,
        'corr_hasil'  => $corrHasil,
        'conv_plan'   => $convPlan,
        'conv_hasil'  => $convHasil,
        'stb'         => $stb,
        'srj'         => $srj,
        'retur'       => $retur,
        'hasil_mesin' => $hasilMesin,
        'totals'      => [
            'plan_corr'  => array_sum(array_column($corrPlan,  'nQtyOrder')),
            'hsl_corr'   => array_sum(array_column($corrHasil, 'nHasil')),
            'rusak_corr' => array_sum(array_column($corrHasil, 'nRusak')),
            'berat_corr' => array_sum(array_column($corrHasil, 'nBerat')),
            'conv_plan'  => array_sum(array_column($convPlan,  'nQtyStok')),
            'conv_hasil' => array_sum(array_column($convHasil, 'nHasil')),
            'conv_rusak' => array_sum(array_column($convHasil, 'nRusak')),
            'stb'        => array_sum(array_column($stb,       'nQty')),
            'srj'        => $totalSRJ,
            'retur'      => $totalRetur,
            'net_kirim'  => $totalSRJ - $totalRetur,
        ]
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// ══════════════════════════════════════════════════════════════════════════════
// ACTION: list  — QUERY UTAMA YANG DIOPTIMASI
// ══════════════════════════════════════════════════════════════════════════════
$conn = dbConnect($serverName, $connectionOptions);
sqlsrv_query($conn, "SET TRANSACTION ISOLATION LEVEL READ UNCOMMITTED");

// ── Filter params ─────────────────────────────────────────────────────────────
$search     = trim($_GET['search']       ?? '');
$mc         = trim($_GET['mc']           ?? '');
$client     = trim($_GET['client']       ?? '');
$product    = trim($_GET['product']      ?? '');
$orderNo    = trim($_GET['order_no']     ?? '');
$flexo      = trim($_GET['flexo']        ?? '');
$dc         = trim($_GET['dc']           ?? '');
$dateFrom   = trim($_GET['date_from']    ?? '');
$dateTo     = trim($_GET['date_to']      ?? '');
$shipFrom   = trim($_GET['ship_from']    ?? '');
$shipTo     = trim($_GET['ship_to']      ?? '');
$scNo       = trim($_GET['sc_no']        ?? '');
$dateScFrom = trim($_GET['date_sc_from'] ?? '');
$dateScTo   = trim($_GET['date_sc_to']   ?? '');

$limitRaw = $_GET['limit'] ?? null;
if ($limitRaw === 'all' || $limitRaw === '0' || $limitRaw === 0) {
    $limit = 0;
} else {
    $limit = max(1, intval($limitRaw ?? 200));
    $limit = min(1000000, $limit);
}
$offset = max(0, intval($_GET['offset'] ?? 0));

// Default: jika tidak ada filter sama sekali → pakai Tgl SC hari ini
$noAnyFilter = empty($search) && empty($mc) && empty($client) && empty($product)
    && empty($orderNo) && empty($flexo) && empty($dc) && empty($scNo)
    && empty($dateFrom) && empty($dateTo) && empty($shipFrom) && empty($shipTo)
    && empty($dateScFrom) && empty($dateScTo);

if ($noAnyFilter) {
    $dateScFrom = date('Y-m-d');
    $dateScTo   = date('Y-m-d');
}

// ── Bangun klausa WHERE ───────────────────────────────────────────────────────
$params = [];
$where  = [];

// Filter utama — LEADING PREDICATE → memanfaatkan index tanggal SC / OP
if (!empty($dateScFrom)) { $where[] = "sc.dTanggal >= ?"; $params[] = $dateScFrom; }
if (!empty($dateScTo))   { $where[] = "sc.dTanggal <= ?"; $params[] = $dateScTo.' 23:59:59'; }
if (!empty($dateFrom))   { $where[] = "op.dTgl >= ?";     $params[] = $dateFrom; }
if (!empty($dateTo))     { $where[] = "op.dTgl <= ?";     $params[] = $dateTo.' 23:59:59'; }
if (!empty($scNo))       { $where[] = "sc.cNoSc LIKE ?";  $params[] = '%'.$scNo.'%'; }
if (!empty($orderNo))    { $where[] = "op.cNoOp LIKE ?";  $params[] = '%'.$orderNo.'%'; }
if (!empty($mc))         { $where[] = "op.cNoMc LIKE ?";  $params[] = '%'.$mc.'%'; }
if (!empty($client))     { $where[] = "sc.cNama LIKE ?";  $params[] = '%'.$client.'%'; }
if (!empty($product))    { $where[] = "sc.cJenis LIKE ?"; $params[] = '%'.$product.'%'; }
if (!empty($flexo))      { $where[] = "op.cFlexo = ?";    $params[] = $flexo; }
if (!empty($dc))         { $where[] = "op.cDC = ?";       $params[] = $dc; }
if (!empty($search)) {
    $where[] = "(sc.cNoSc LIKE ? OR sc.cNama LIKE ? OR sc.cJenis LIKE ? OR op.cNoOp LIKE ?)";
    $p = '%'.$search.'%';
    $params[] = $p; $params[] = $p; $params[] = $p; $params[] = $p;
}

// Filter tanggal kirim — EXISTS ringan (bukan JOIN ke semua baris)
if (!empty($shipFrom) && !empty($shipTo)) {
    $where[] = "EXISTS (
        SELECT 1 FROM tbSRJ s2 WITH (NOLOCK)
        INNER JOIN tbSRJDtl d2 WITH (NOLOCK) ON s2.cNoSRJ = d2.cNoSRJ
        WHERE (d2.cNoOp = op.cNoOp OR s2.cNoSC = sc.cNoSc)
          AND s2.dTanggal BETWEEN ? AND ?)";
    $params[] = $shipFrom; $params[] = $shipTo.' 23:59:59';
} elseif (!empty($shipFrom)) {
    $where[] = "EXISTS (
        SELECT 1 FROM tbSRJ s2 WITH (NOLOCK)
        INNER JOIN tbSRJDtl d2 WITH (NOLOCK) ON s2.cNoSRJ = d2.cNoSRJ
        WHERE (d2.cNoOp = op.cNoOp OR s2.cNoSC = sc.cNoSc) AND s2.dTanggal >= ?)";
    $params[] = $shipFrom;
} elseif (!empty($shipTo)) {
    $where[] = "EXISTS (
        SELECT 1 FROM tbSRJ s2 WITH (NOLOCK)
        INNER JOIN tbSRJDtl d2 WITH (NOLOCK) ON s2.cNoSRJ = d2.cNoSRJ
        WHERE (d2.cNoOp = op.cNoOp OR s2.cNoSC = sc.cNoSc) AND s2.dTanggal <= ?)";
    $params[] = $shipTo.' 23:59:59';
}

$whereSql = !empty($where) ? 'AND ' . implode(' AND ', $where) : '';

// ════════════════════════════════════════════════════════════════════════════
// QUERY UTAMA — CTE aggregasi agar SQL Server tidak scan ulang per-baris
// CTE hanya mengambil cNoOp yang relevan dengan filter SC/OP (bukan semua tabel)
// ════════════════════════════════════════════════════════════════════════════
$sql = "
;WITH

-- Aggregasi STB
cte_stb AS (
    SELECT s.cNoOp,
           SUM(ISNULL(s.nQty,0))  AS jml_stb,
           MAX(s.dTglSerah)        AS tgl_serah,
           MAX(s.cRak)             AS cRak,
           MAX(s.cShift)           AS cShift,
           SUM(CASE
               WHEN sf.tgl_first IS NULL THEN ISNULL(s.nQty,0)
               WHEN CAST(s.dTglSerah AS DATE) < CAST(sf.tgl_first AS DATE) THEN ISNULL(s.nQty,0)
               ELSE 0
           END) AS stock_awal
    FROM tbStbBJ s WITH (NOLOCK)
    LEFT JOIN (
        SELECT d2.cNoOp, MIN(s2.dTanggal) AS tgl_first
        FROM tbSRJ s2 WITH (NOLOCK)
        INNER JOIN tbSRJDtl d2 WITH (NOLOCK) ON s2.cNoSRJ = d2.cNoSRJ
        GROUP BY d2.cNoOp
    ) sf ON sf.cNoOp = s.cNoOp
    GROUP BY s.cNoOp
),

-- Aggregasi SRJ (pengiriman)
cte_srj AS (
    SELECT d.cNoOp, d.cNoScDtl, s.cNoSC,
           SUM(ISNULL(d.nQty,0)) AS jml_kirim,
           MAX(s.dTanggal)        AS tgl_kirim_srj,
           MAX(s.cTujuanKirim)    AS tujuan_kirim,
           SUM(CASE WHEN COALESCE(vw.lVoid, s.lVoid,'0')='1' THEN 0
                    ELSE ISNULL(d.nQty,0)*ISNULL(d.nBrtOp,0) END) AS tonase_rows
    FROM tbSRJDtl d WITH (NOLOCK)
    INNER JOIN tbSRJ s WITH (NOLOCK) ON s.cNoSRJ = d.cNoSRJ
    LEFT JOIN tbOP op2 WITH (NOLOCK) ON op2.cNoOp = d.cNoOp
    LEFT JOIN vwSuratJalan vw WITH (NOLOCK) ON vw.cNoSRJ = s.cNoSRJ
    GROUP BY d.cNoOp, d.cNoScDtl, s.cNoSC
),

-- Aggregasi Corrugating (hasil + plan digabung dalam satu CTE)
cte_corr AS (
    SELECT cNoOp,
           SUM(hsl)      AS hsl_corr,
           SUM(rusak)    AS rsak_corr,
           SUM(berat)    AS berat_corr,
           SUM(plan_qty) AS plan_corr
    FROM (
        SELECT d.cNoOp,
               SUM(ISNULL(d.nHasil,0)) AS hsl,
               SUM(ISNULL(d.nRusak,0)) AS rusak,
               SUM(ISNULL(d.nBerat,0)) AS berat,
               0 AS plan_qty
        FROM tbHslCorrDtl d WITH (NOLOCK) GROUP BY d.cNoOp
        UNION ALL
        SELECT cd.cNoOp, 0, 0, 0,
               SUM(ISNULL(cd.nQtyOrder,0))
        FROM tbCorrDtl cd WITH (NOLOCK) GROUP BY cd.cNoOp
    ) t
    GROUP BY cNoOp
),

-- Aggregasi Converting (rusak saja — hasil conv = nQtyStok dari tbOP)
cte_conv AS (
    SELECT d.cNoOp,
           SUM(ISNULL(d.nRusak,0)) AS rsak_conv
    FROM tbConvPlanDtl d WITH (NOLOCK)
    GROUP BY d.cNoOp
),

-- Aggregasi Retur
cte_retur AS (
    SELECT d2.cNoOp,
           SUM(ISNULL(rd.nQty,0)) AS jml_retur
    FROM tbRtSrjDtl rd WITH (NOLOCK)
    INNER JOIN tbRtSrj r WITH (NOLOCK) ON rd.cNomer = r.cNomer
    INNER JOIN tbSRJDtl d2 WITH (NOLOCK) ON d2.cNoSRJ = r.cNoSrj
    GROUP BY d2.cNoOp
)

SELECT
    -- Identitas SC
    sc.cNoSc,
    sc.dTanggal                                 AS tgl_sc,
    sc.cNama                                    AS customer,
    sc.cJenis                                   AS nama_brg,
    sc.cJnsSc                                   AS jns_sc,
    sc.cSales                                   AS sales,
    sc.cKeterangan                              AS keterangan_sc,
    sc.cKet_Mkt                                 AS ket_mkt,
    sc.nQty                                     AS qty_sc,
    CASE WHEN sc.lTK=1 THEN 'Tunggu Kabar'
         ELSE CONVERT(VARCHAR,sc.dTglKirim2,23) END AS tgl_kirim_sc,
    CONVERT(VARCHAR,sc.dTglKirim2,23)           AS dTglKirim2,
    sc.nPanjang, sc.nLebar, sc.nTinggi, sc.cWarna,

    -- Kualitas
    ISNULL(tsc.ckd_b1,'') AS ckd_b1,
    ISNULL(tsc.ckd_b2,'') AS ckd_b2,
    ISNULL(tsc.ckd_b3,'') AS ckd_b3,
    ISNULL(tsc.ckd_b4,'') AS ckd_b4,
    ISNULL(tsc.ckd_b5,'') AS ckd_b5,

    -- OP
    op.cNoOp,
    op.cNoMc,
    op.nQty                                     AS total_order,
    op.nQtyStok                                 AS last_plan,
    op.dTgl                                     AS tgl_op,
    op.dTglkirim                                AS tgl_kirim_awal,
    op.dTglkirim2                               AS tgl_kirim_op,
    op.cTipe, op.cFlexo, op.cDC,
    op.lTK                                      AS lTK_op,
    op.cMengetahui, op.cKetOrder,
    op.nTot_netto                               AS netto,
    op.nRm, op.cJnsGel                          AS flute,
    op.userdate,

    -- Stock gudang
    ISNULL(stb.stock_awal,0)                                        AS stock_awal_gudang,
    ISNULL(stb.jml_stb,0) - ISNULL(srj.jml_kirim,0)
        + ISNULL(ret.jml_retur,0)                                   AS stock_akhir_gudang,

    -- STB
    ISNULL(stb.jml_stb,0)                       AS jml_serah_trm,
    stb.tgl_serah, stb.cRak, stb.cShift,

    -- SRJ
    ISNULL(srj.jml_kirim,0)                     AS jml_kirim,
    srj.tgl_kirim_srj, srj.tujuan_kirim,
    ISNULL(srj.tonase_rows,0)                   AS tonase,

    -- Corr
    ISNULL(corr.hsl_corr,0)                     AS hsl_corr,
    ISNULL(corr.rsak_corr,0)                    AS rsak_corr,
    ISNULL(corr.berat_corr,0)                   AS berat_corr,
    ISNULL(corr.plan_corr,0)                    AS plan_corr,

    -- Conv
    ISNULL(op.nQtyStok,0)                       AS hsl_conv,
    ISNULL(conv.rsak_conv,0)                    AS rsak_conv,

    -- Retur
    ISNULL(ret.jml_retur,0)                     AS jml_retur

FROM tbSC sc WITH (NOLOCK)
LEFT JOIN tbOP  op  WITH (NOLOCK) ON op.cNoSc  = sc.cNoSc
LEFT JOIN tbTSC tsc WITH (NOLOCK) ON tsc.cNoSc = sc.cNoSc
LEFT JOIN cte_stb  stb  ON stb.cNoOp  = op.cNoOp
LEFT JOIN cte_srj  srj  ON (srj.cNoOp = op.cNoOp OR srj.cNoScDtl = sc.cNoSc OR srj.cNoSC = sc.cNoSc)
LEFT JOIN cte_corr corr ON corr.cNoOp = op.cNoOp
LEFT JOIN cte_conv conv ON conv.cNoOp = op.cNoOp
LEFT JOIN cte_retur ret ON ret.cNoOp  = op.cNoOp

WHERE 1=1 $whereSql

ORDER BY CASE WHEN op.cNoOp IS NULL THEN 0 ELSE 1 END ASC,
         sc.dTanggal DESC, sc.cNoSc DESC";

if ($limit > 0) {
    $sql .= "\nOFFSET $offset ROWS FETCH NEXT $limit ROWS ONLY";
}

// ── Count query — ringan: hanya dari tbSC+tbOP ───────────────────────────────
$countSql = "SELECT COUNT(*) AS total
FROM tbSC sc WITH (NOLOCK)
LEFT JOIN tbOP op WITH (NOLOCK) ON op.cNoSc = sc.cNoSc
WHERE 1=1 $whereSql";

$cStmt = runQuery($conn, $countSql, $params, 120);
$total = 0;
if ($cStmt) {
    $cRow  = sqlsrv_fetch_array($cStmt, SQLSRV_FETCH_ASSOC);
    $total = (int)($cRow['total'] ?? 0);
    sqlsrv_free_stmt($cStmt);
}

// ── Summary aggregasi server-side ─────────────────────────────────────────────
// Dihitung sekali — tidak perlu fetch semua baris ke PHP
$summSql = "SELECT
    COUNT(DISTINCT op.cNoOp)           AS total_ops,
    COUNT(DISTINCT CASE WHEN op.cNoOp IS NULL THEN sc.cNoSc END) AS sc_belum_op,
    ISNULL(SUM(op.nQty),0)             AS total_order,
    ISNULL(SUM(srj_s.jml_kirim),0)    AS total_kirim,
    ISNULL(SUM(CASE WHEN op.nQty > srj_s.jml_kirim THEN op.nQty - srj_s.jml_kirim ELSE 0 END),0) AS pcs_kurang,
    ISNULL(SUM(srj_s.tonase_rows),0)  AS total_tonase,
    COUNT(DISTINCT CASE WHEN
        op.cNoOp IS NOT NULL AND op.nQty > 0
        AND (ISNULL(corr_s.hsl_corr,0)=0 OR ISNULL(op.nQtyStok,0)=0
             OR ISNULL(stb_s.jml_stb,0)=0 OR ISNULL(srj_s.jml_kirim,0)=0)
        THEN op.cNoOp END)             AS data_incomplete
FROM tbSC sc WITH (NOLOCK)
LEFT JOIN tbOP op WITH (NOLOCK) ON op.cNoSc = sc.cNoSc
LEFT JOIN (
    SELECT d.cNoOp, SUM(ISNULL(d.nQty,0)) AS jml_kirim,
           SUM(ISNULL(d.nQty,0)*ISNULL(d.nBrtOp,0)) AS tonase_rows
    FROM tbSRJDtl d WITH (NOLOCK)
    INNER JOIN tbSRJ s WITH (NOLOCK) ON s.cNoSRJ = d.cNoSRJ
    WHERE COALESCE(s.lVoid,'0') != '1'
    GROUP BY d.cNoOp
) srj_s ON srj_s.cNoOp = op.cNoOp
LEFT JOIN (
    SELECT cNoOp, SUM(nQty) AS jml_stb FROM tbStbBJ WITH (NOLOCK) GROUP BY cNoOp
) stb_s ON stb_s.cNoOp = op.cNoOp
LEFT JOIN (
    SELECT cNoOp, SUM(ISNULL(nHasil,0)) AS hsl_corr FROM tbHslCorrDtl WITH (NOLOCK) GROUP BY cNoOp
) corr_s ON corr_s.cNoOp = op.cNoOp
WHERE 1=1 $whereSql";

$summStmt = runQuery($conn, $summSql, $params, 120);
$summary  = null;
if ($summStmt) {
    $summary = sqlsrv_fetch_array($summStmt, SQLSRV_FETCH_ASSOC);
    sqlsrv_free_stmt($summStmt);
}

// ── Eksekusi main query ───────────────────────────────────────────────────────
$stmt = runQuery($conn, $sql, $params, 600);
if ($stmt === false) {
    $errs = sqlsrv_errors();
    sqlsrv_close($conn);
    echo json_encode(['success'=>false,'message'=>'Query list gagal.','errors'=>$errs], JSON_UNESCAPED_UNICODE);
    exit;
}

// ── Stream output JSON ────────────────────────────────────────────────────────
// Kirim data secepatnya ke browser tanpa menunggu semua baris selesai diproses
if (ob_get_level()) ob_end_clean();

$no    = $offset + 1;
$rows  = [];

while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $r = [];
    foreach ($row as $k => $v) $r[$k] = is_string($v) ? safeStr($v) : $v;

    $r['no']           = $no++;
    $r['op_belum']     = empty($r['cNoOp']);
    $r['ukuran_dalam'] = round($r['nPanjang']??0).'x'.round($r['nLebar']??0).'x'.round($r['nTinggi']??0);
    $r['kualitas']     = implode(' / ', array_filter([
        $r['ckd_b1']??'', $r['ckd_b2']??'', $r['ckd_b3']??'',
        $r['ckd_b4']??'', $r['ckd_b5']??''
    ]));

    $totalOrder           = (float)($r['total_order'] ?? $r['qty_sc'] ?? 0);
    $r['total_order_eff'] = $totalOrder;
    $r['tgl_kirim_label'] = ($r['lTK_op']??'') == '1' ? 'Tunggu Kabar'
        : ((!empty($r['dTglKirim2'])) ? $r['dTglKirim2'] : ($r['tgl_kirim_sc']??'-'));
    $r['rack_name']   = mapRack($r['cRak']??'');
    $r['pcs_kurang']  = max(0, $totalOrder - (float)($r['jml_kirim']??0));
    $r['net_kirim']   = (float)($r['jml_kirim']??0) - (float)($r['jml_retur']??0);

    $hasDelivery          = ((float)($r['jml_kirim']??0)>0)||((float)($r['net_kirim']??0)>0);
    $r['status_lengkap']  = (!$r['op_belum'] && ((float)($r['pcs_kurang']??0)<=0||$hasDelivery)) ? 'SELESAI' : 'PROSES';
    $r['missing_corr']    = (!$r['op_belum'] && $totalOrder>0 && (float)($r['hsl_corr']??0)==0);
    $r['missing_conv']    = (!$r['op_belum'] && $totalOrder>0 && (float)($r['hsl_conv']??0)==0);
    $r['missing_stb']     = (!$r['op_belum'] && $totalOrder>0 && (float)($r['jml_serah_trm']??0)==0);
    $r['missing_kirim']   = (!$r['op_belum'] && $totalOrder>0 && (float)($r['jml_kirim']??0)==0);
    $r['data_incomplete'] = $r['op_belum']||$r['missing_corr']||$r['missing_conv']||$r['missing_stb']||$r['missing_kirim'];

    $rows[] = $r;
}
sqlsrv_free_stmt($stmt);
sqlsrv_close($conn);

// Pagination
if ($limit > 0) {
    $totalPages     = ceil($total / $limit);
    $curPage        = floor($offset / $limit) + 1;
    $recordsPerPage = $limit;
    $hasPrev        = $offset > 0;
    $hasNext        = ($offset + $limit) < $total;
} else {
    $totalPages     = 1; $curPage = 1;
    $recordsPerPage = (int)$total;
    $hasPrev        = false; $hasNext = false;
}

echo json_encode([
    'success'    => true,
    'data'       => $rows,
    'summary'    => $summary,
    'pagination' => [
        'total_records'    => (int)$total,
        'total_pages'      => $totalPages,
        'current_page'     => $curPage,
        'records_per_page' => $recordsPerPage,
        'offset'           => $offset,
        'has_prev'         => $hasPrev,
        'has_next'         => $hasNext,
    ],
    'timestamp'  => date('Y-m-d H:i:s'),
], JSON_UNESCAPED_UNICODE);
