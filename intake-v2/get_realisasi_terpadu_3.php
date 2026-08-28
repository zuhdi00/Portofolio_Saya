<?php
/**
 * get_realisasi_terpadu.php
 * Backend terpadu: menggabungkan data OP, Corrugating, Converting,
 * Serah Terima, Pengiriman, Retur, dan data MCList (dari tbOP join tbStbBJ, tbSRJDtl).
 * 
 * Endpoint:
 *   ?action=list          → daftar OP (flat table, mirip Excel header_intake_order)
 *   ?action=detail&sc=... → detail lengkap 1 SC
 *   ?action=mc_suggest&search=... → autocomplete MC
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
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Koneksi DB gagal.', 'errors' => sqlsrv_errors()]);
        exit;
    }
    return $conn;
}

function safeStr($val) {
    if ($val === null) return '';
    if (is_string($val)) return trim(iconv('ISO-8859-1', 'UTF-8//IGNORE//TRANSLIT', $val));
    return $val;
}

function fetchAll($conn, $sql, $params = []) {
    $stmt = empty($params)
        ? sqlsrv_query($conn, $sql)
        : sqlsrv_query($conn, $sql, $params);
    if ($stmt === false) {
        return ['error' => sqlsrv_errors()];
    }
    $rows = [];
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $clean = [];
        foreach ($row as $k => $v) {
            $clean[$k] = is_string($v) ? safeStr($v) : $v;
        }
        $rows[] = $clean;
    }
    sqlsrv_free_stmt($stmt);
    return $rows;
}

function mapRack($cRak) {
    $map = [
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
// ACTION: mc_suggest — autocomplete MC dari tbOP
// ══════════════════════════════════════════════════════════════════════════════
if ($action === 'mc_suggest') {
    $search = trim($_GET['search'] ?? '');
    if (strlen($search) < 2) {
        echo json_encode(['success' => false, 'message' => 'Minimal 2 karakter.']);
        exit;
    }
    $conn = dbConnect($serverName, $connectionOptions);
    $sql = "SELECT TOP 30 op.cNoMc,
                COUNT(*) AS usage_count,
                MAX(op.dTgl) AS last_used
            FROM tbOP op
            WHERE op.cNoMc IS NOT NULL AND op.cNoMc != '' AND op.cNoMc LIKE ?
            GROUP BY op.cNoMc
            ORDER BY usage_count DESC, last_used DESC";
    $rows = fetchAll($conn, $sql, ['%'.$search.'%']);
    sqlsrv_close($conn);
    echo json_encode(['success' => true, 'data' => $rows]);
    exit;
}

// ══════════════════════════════════════════════════════════════════════════════
// ACTION: detail — 1 SC lengkap (corr, conv, STB, SRJ, retur)
// ══════════════════════════════════════════════════════════════════════════════
if ($action === 'detail') {
    $sc = trim($_GET['sc'] ?? '');
    if (empty($sc)) {
        echo json_encode(['success' => false, 'message' => 'Parameter sc dibutuhkan.']);
        exit;
    }
    $conn = dbConnect($serverName, $connectionOptions);

    // SC header
    $scRows = fetchAll($conn, "SELECT * FROM tbSC WHERE cNoSc = ?", [$sc]);
    if (empty($scRows)) {
        sqlsrv_close($conn);
        echo json_encode(['success' => false, 'message' => 'SC tidak ditemukan.']);
        exit;
    }
    $dataSC = $scRows[0];

    // OP list
    $dataOP = fetchAll($conn, "SELECT * FROM tbOP WHERE cNoSc = ? ORDER BY cNoOp", [$sc]);
    if (empty($dataOP)) {
        $dataOP = fetchAll($conn, "SELECT * FROM tbOP WHERE cNoOp LIKE ? ORDER BY cNoOp", [$sc.'%']);
    }

    $opNos = array_column($dataOP, 'cNoOp');
    $opIn  = !empty($opNos) ? implode(',', array_fill(0, count($opNos), '?')) : null;

    // Corrugating Planning
    $corrPlan = [];
    if ($opIn) {
        $corrPlan = fetchAll($conn,
            "SELECT c.cNoCorr, c.cKodeCorr, c.dTanggal, c.cKeterangan,
                    d.cNoOp, d.cType, d.cNoMc, d.nHasil, d.nRusak,
                    d.dStart, d.dFinish, d.cFlute, d.nQtyOrder
             FROM tbCorr c
             LEFT JOIN tbCorrDtl d ON c.cNoCorr = d.cNoCorr
             WHERE d.cNoOp IN ($opIn)
             ORDER BY c.dTanggal, c.cNoCorr",
            $opNos
        );
    }

    // Corrugating Hasil
    $corrHasil = [];
    if ($opIn) {
        $corrHasil = fetchAll($conn,
            "SELECT h.cNoCorr, h.cKodeCorr, h.dTanggal,
                    d.cNoOp, d.cNoMc, d.nHasil, d.nRusak, d.dStart, d.dFinish,
                    d.cFlute, d.nBerat, d.nOut
             FROM tbHslCorr h
             LEFT JOIN tbHslCorrDtl d ON h.cNoCorr = d.cNoCorr
             WHERE d.cNoOp IN ($opIn)
             ORDER BY h.dTanggal, h.cNoCorr",
            $opNos
        );
    }

    // Converting Plan (tbOP itu sendiri)
    $convPlan = fetchAll($conn, "SELECT * FROM tbOP WHERE cNoSc = ? ORDER BY cNoOp", [$sc]);

    // Converting Hasil (tbConvPlan + tbConvPlanDtl)
    $convHasil = [];
    if ($opIn) {
        $convHasil = fetchAll($conn,
            "SELECT d.cNoOp, p.dTanggal,
                    ISNULL(m.cNama, p.cKodeFlx) AS cNamaMsn,
                    ISNULL(d.nHasil,0) AS nHasil,
                    ISNULL(d.nRusak,0) AS nRusak
             FROM tbConvPlan p
             INNER JOIN tbConvPlanDtl d ON d.cNoConv = p.cNoConv
             LEFT JOIN tbMesin m ON p.cKodeFlx = m.cKode
             WHERE d.cNoOp IN ($opIn)
             ORDER BY d.cNoOp, p.dTanggal",
            $opNos
        );
    }

    // Serah Terima
    $stb = fetchAll($conn,
        "SELECT * FROM tbStbBJ WHERE cNoSc = ? OR cNoOp LIKE ? ORDER BY cNoOp, dTanggal",
        [$sc, $sc.'%']
    );

    // Pengiriman
    $srj = fetchAll($conn,
        "SELECT d.cNoSRJ, d.cNama, d.nQty, d.cNoOp, d.cNoScDtl,
                s.dTanggal, s.cKeterangan, s.cNoPol, s.cTujuanKirim
         FROM tbSRJDtl d
         INNER JOIN tbSRJ s ON d.cNoSRJ = s.cNoSRJ
         WHERE d.cNoScDtl = ? OR d.cNoOp LIKE ? OR s.cNoSC = ?
         ORDER BY s.dTanggal",
        [$sc, $sc.'%', $sc]
    );

    // Retur
    $retur = fetchAll($conn,
        "SELECT d.cNomer AS cNoRetur, d.cItem, d.nQty, d.cKeterangan AS cKetRetur,
                r.dTgl, r.cNoSc, r.cNoSrj, r.cNama
         FROM tbRtSrjDtl d
         INNER JOIN tbRtSrj r ON d.cNomer = r.cNomer
         WHERE r.cNoSc = ?
         ORDER BY r.dTgl",
        [$sc]
    );

    // Aggregasi hasil mesin
    $hasilMesin = [];
    foreach ($convHasil as $r) {
        $msn = strtoupper(trim($r['cNamaMsn'] ?? ''));
        if (!isset($hasilMesin[$msn])) $hasilMesin[$msn] = ['hasil' => 0, 'rusak' => 0];
        $hasilMesin[$msn]['hasil'] += (float)($r['nHasil'] ?? 0);
        $hasilMesin[$msn]['rusak'] += (float)($r['nRusak'] ?? 0);
    }

    // Total corrHasil
    $totalHslCorr  = array_sum(array_column($corrHasil, 'nHasil'));
    $totalRusakCorr = array_sum(array_column($corrHasil, 'nRusak'));
    $totalBeratCorr = array_sum(array_column($corrHasil, 'nBerat'));
    $totalPlanCorr  = array_sum(array_column($corrPlan, 'nQtyOrder'));
    $totalConvPlan  = array_sum(array_column($convPlan, 'nQtyStok'));
    $totalConvHasil = array_sum(array_column($convHasil, 'nHasil'));
    $totalConvRusak = array_sum(array_column($convHasil, 'nRusak'));
    $totalSTB       = array_sum(array_column($stb, 'nQty'));
    $totalSRJ       = array_sum(array_column($srj, 'nQty'));
    $totalRetur     = array_sum(array_column($retur, 'nQty'));

    sqlsrv_close($conn);

    echo json_encode([
        'success'   => true,
        'sc'        => $dataSC,
        'op'        => $dataOP,
        'corr_plan' => $corrPlan,
        'corr_hasil'=> $corrHasil,
        'conv_plan' => $convPlan,
        'conv_hasil'=> $convHasil,
        'stb'       => $stb,
        'srj'       => $srj,
        'retur'     => $retur,
        'hasil_mesin' => $hasilMesin,
        'totals' => [
            'plan_corr'  => $totalPlanCorr,
            'hsl_corr'   => $totalHslCorr,
            'rusak_corr' => $totalRusakCorr,
            'berat_corr' => $totalBeratCorr,
            'conv_plan'  => $totalConvPlan,
            'conv_hasil' => $totalConvHasil,
            'conv_rusak' => $totalConvRusak,
            'stb'        => $totalSTB,
            'srj'        => $totalSRJ,
            'retur'      => $totalRetur,
            'net_kirim'  => $totalSRJ - $totalRetur,
        ]
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// ══════════════════════════════════════════════════════════════════════════════
// ACTION: list — flat table mirip header_intake_order.xlsx
// Kolom: No, Tgl Kontrak, No Kontrak (SC), Jenis, No Artikel (OP), Tipe,
//        Tinggi Box, Nama Barang, Ukuran Dalam, Customer, Sales, Total Order,
//        Jml Serah Trm, Tgl Kirim, New Jadwal, Bungkus, Jml Kirim, Pcs Kurang,
//        RM Kurang, Flute, Kualitas 1-5, Lebar Kertas, Berat 1-5, Warna, Join,
//        Proses, Mesin (Flexo), toleransi, Jml Out, Jml Bx/Sh, Last Plan,
//        Jml Plan, Gram Timbang, Panjang Sheet, Lebar Sheet, Kurang Sheet,
//        Stok Sheet, Hasil Sheet,
//        + kolom realisasi: Plan Corr, Hasil Corr, Rusak Corr,
//          Hasil Conv (per mesin), Hasil STB, Kirim, Retur
// ══════════════════════════════════════════════════════════════════════════════
$conn = dbConnect($serverName, $connectionOptions);

// ── Query hints for faster execution ──────────────────────────────────────────
// Use READ UNCOMMITTED to avoid lock waits on busy OLTP tables
sqlsrv_query($conn, "SET TRANSACTION ISOLATION LEVEL READ UNCOMMITTED");

// --- Filter params ---

$search     = trim($_GET['search']      ?? '');
$mc         = trim($_GET['mc']          ?? '');
$client     = trim($_GET['client']      ?? '');
$product    = trim($_GET['product']     ?? '');
$orderNo    = trim($_GET['order_no']    ?? '');
$flexo      = trim($_GET['flexo']       ?? '');
$dc         = trim($_GET['dc']          ?? '');
$dateFrom   = trim($_GET['date_from']   ?? '');
$dateTo     = trim($_GET['date_to']     ?? '');
$shipFrom   = trim($_GET['ship_from']   ?? '');
$shipTo     = trim($_GET['ship_to']     ?? '');
$scNo       = trim($_GET['sc_no']       ?? '');
$dateScFrom = trim($_GET['date_sc_from']?? '');
$dateScTo   = trim($_GET['date_sc_to']  ?? '');
$limit      = max(1, min(100000, intval($_GET['limit']  ?? 200)));
$offset     = max(0, intval($_GET['offset'] ?? 0));


// DEFAULT: jika tidak ada filter apapun, default ke Tgl OP = hari ini (1 hari)
$noAnyFilter = empty($search) && empty($mc) && empty($client) && empty($product)
            && empty($orderNo) && empty($flexo) && empty($dc) && empty($scNo)
            && empty($dateFrom) && empty($dateTo)
            && empty($shipFrom) && empty($shipTo)
            && empty($dateScFrom) && empty($dateScTo);
if ($noAnyFilter) {
    $dateFrom = date('Y-m-d');
    $dateTo   = date('Y-m-d');
}

// --- Main OP query — OPTIMIZED: semua aggregasi via pre-joined derived table ---
// Tidak ada scalar subquery per-row; semua dihitung sekali via GROUP BY JOINs.
// WITH (NOLOCK) + READ UNCOMMITTED di atas = bebas block read.
$sql = "SELECT
    op.cNoOp,
    op.cNoSc,
    op.cNoMc,
    op.cnm_c         AS customer,
    op.cnm_brg       AS nama_brg,
    op.nPanjang, op.nLebar, op.nTinggi,
    op.nQty          AS total_order,
    op.nQtyStok      AS last_plan,
    op.dTgl          AS tgl_op,
    op.dTglkirim     AS tgl_kirim_awal,
    op.dTglkirim2    AS tgl_kirim,
    sc.dTanggal      AS tgl_sc,
    op.cWarna,
    op.cTipe,
    op.cFlexo,
    op.cDC,
    op.lTK,
    op.cMengetahui,
    op.cKetOrder,
    op.nTot_netto    AS netto,
    op.nRm,
    op.ckd_b1, op.ckd_b2, op.ckd_b3, op.ckd_b4, op.ckd_b5,
    op.cLayer        AS flute,
    op.cKetOrder     AS keterangan,
    op.userdate,

    -- Stock Awal / Akhir Gudang (dihitung di dalam STB aggregat, bukan subquery per-row)
    ISNULL(stb_agg.stock_awal,  0) AS stock_awal_gudang,
    ISNULL(stb_agg.stock_akhir, 0) AS stock_akhir_gudang,

    -- Serah Terima
    ISNULL(stb_agg.jml_stb,  0) AS jml_serah_trm,
    stb_agg.tgl_serah            AS tgl_serah,
    stb_agg.cRak                 AS cRak,
    stb_agg.cShift               AS cShift,

    -- Pengiriman
    ISNULL(srj_agg.jml_kirim,   0) AS jml_kirim,
    srj_agg.tgl_kirim_srj          AS tgl_kirim_srj,
    srj_agg.tujuan_kirim           AS tujuan_kirim,

    -- Corrugating
    ISNULL(corr_agg.hsl_corr,  0) AS hsl_corr,
    ISNULL(corr_agg.rsak_corr, 0) AS rsak_corr,
    ISNULL(corr_agg.berat_corr,0) AS berat_corr,
    ISNULL(corr_agg.plan_corr, 0) AS plan_corr,

    -- Converting
    ISNULL(op.nQtyStok,        0) AS hsl_conv,
    ISNULL(conv_agg.rsak_conv, 0) AS rsak_conv,

    -- Retur
    ISNULL(retur_agg.jml_retur,0) AS jml_retur

FROM tbOP op WITH (NOLOCK)
LEFT JOIN tbSC sc WITH (NOLOCK) ON sc.cNoSc = op.cNoSc

-- STB aggregat: sekaligus hitung stock awal & akhir gudang tanpa subquery per-row
LEFT JOIN (
    SELECT
        s.cNoOp,
        SUM(ISNULL(s.nQty,0)) AS jml_stb,
        MAX(s.dTglSerah)       AS tgl_serah,
        MAX(s.cRak)            AS cRak,
        MAX(s.cShift)          AS cShift,
        SUM(CASE WHEN s.dTglSerah < ISNULL(srj_first.tgl_first, GETDATE())
                 THEN ISNULL(s.nQty,0) ELSE 0 END) AS stock_awal,
        SUM(CASE WHEN s.dTglSerah <= ISNULL(srj_last.tgl_last, GETDATE())
                 THEN ISNULL(s.nQty,0) ELSE 0 END) AS stock_akhir
    FROM tbStbBJ s WITH (NOLOCK)
    LEFT JOIN (
        SELECT d2.cNoOp, MIN(s2.dTanggal) AS tgl_first
        FROM tbSRJ s2 WITH (NOLOCK)
        INNER JOIN tbSRJDtl d2 WITH (NOLOCK) ON s2.cNoSRJ = d2.cNoSRJ
        GROUP BY d2.cNoOp
    ) srj_first ON srj_first.cNoOp = s.cNoOp
    LEFT JOIN (
        SELECT d3.cNoOp, MAX(s3.dTanggal) AS tgl_last
        FROM tbSRJ s3 WITH (NOLOCK)
        INNER JOIN tbSRJDtl d3 WITH (NOLOCK) ON s3.cNoSRJ = d3.cNoSRJ
        GROUP BY d3.cNoOp
    ) srj_last ON srj_last.cNoOp = s.cNoOp
    GROUP BY s.cNoOp
) stb_agg ON stb_agg.cNoOp = op.cNoOp

-- SRJ aggregat
LEFT JOIN (
    SELECT d.cNoOp,
           SUM(ISNULL(d.nQty,0)) AS jml_kirim,
           MAX(s.dTanggal)       AS tgl_kirim_srj,
           MAX(s.cTujuanKirim)   AS tujuan_kirim
    FROM tbSRJDtl d WITH (NOLOCK)
    INNER JOIN tbSRJ s WITH (NOLOCK) ON s.cNoSRJ = d.cNoSRJ
    GROUP BY d.cNoOp
) srj_agg ON srj_agg.cNoOp = op.cNoOp

-- Corr aggregat (Hasil + Plan digabung sekali lewat UNION ALL)
LEFT JOIN (
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
               0                        AS plan_qty
        FROM tbHslCorrDtl d WITH (NOLOCK)
        GROUP BY d.cNoOp
        UNION ALL
        SELECT cd.cNoOp, 0, 0, 0,
               SUM(ISNULL(cd.nQtyOrder,0))
        FROM tbCorrDtl cd WITH (NOLOCK)
        GROUP BY cd.cNoOp
    ) t
    GROUP BY cNoOp
) corr_agg ON corr_agg.cNoOp = op.cNoOp

-- Conv rusak aggregat
LEFT JOIN (
    SELECT d.cNoOp,
           SUM(ISNULL(d.nRusak,0)) AS rsak_conv
    FROM tbConvPlanDtl d WITH (NOLOCK)
    GROUP BY d.cNoOp
) conv_agg ON conv_agg.cNoOp = op.cNoOp

-- Retur aggregat
LEFT JOIN (
    SELECT d2.cNoOp,
           SUM(ISNULL(rd.nQty,0)) AS jml_retur
    FROM tbRtSrjDtl rd WITH (NOLOCK)
    INNER JOIN tbRtSrj r WITH (NOLOCK) ON rd.cNomer = r.cNomer
    INNER JOIN tbSRJDtl d2 WITH (NOLOCK) ON d2.cNoSRJ = r.cNoSrj
    GROUP BY d2.cNoOp
) retur_agg ON retur_agg.cNoOp = op.cNoOp

WHERE op.cNoMc IS NOT NULL";

$params = [];
$where  = [];

if (!empty($search)) {
    $where[] = "(op.cNoOp LIKE ? OR op.cnm_c LIKE ? OR op.cnm_brg LIKE ?)";
    $p = '%'.$search.'%';
    $params[] = $p; $params[] = $p; $params[] = $p;
}
if (!empty($mc))      { $where[] = "op.cNoMc LIKE ?";      $params[] = '%'.$mc.'%'; }
if (!empty($client))  { $where[] = "op.cnm_c LIKE ?";      $params[] = '%'.$client.'%'; }
if (!empty($product)) { $where[] = "op.cnm_brg LIKE ?";    $params[] = '%'.$product.'%'; }
if (!empty($orderNo)) { $where[] = "op.cNoOp LIKE ?";      $params[] = '%'.$orderNo.'%'; }
if (!empty($flexo))   { $where[] = "op.cFlexo = ?";        $params[] = $flexo; }
if (!empty($scNo))      { $where[] = "op.cNoSc LIKE ?";      $params[] = '%'.$scNo.'%'; }
if (!empty($dateScFrom)){ $where[] = "sc.dTanggal >= ?";         $params[] = $dateScFrom; }
if (!empty($dateScTo))  { $where[] = "sc.dTanggal <= ?";         $params[] = $dateScTo.' 23:59:59'; }
if (!empty($dc))      { $where[] = "op.cDC = ?";           $params[] = $dc; }
if (!empty($dateFrom)){ $where[] = "op.dTgl >= ?";         $params[] = $dateFrom; }
if (!empty($dateTo))  { $where[] = "op.dTgl <= ?";         $params[] = $dateTo.' 23:59:59'; }
if (!empty($shipFrom)){ $where[] = "op.dTglkirim2 >= ?";   $params[] = $shipFrom; }
if (!empty($shipTo))  { $where[] = "op.dTglkirim2 <= ?";   $params[] = $shipTo.' 23:59:59'; }

if (!empty($where)) {
    $sql .= " AND " . implode(" AND ", $where);
}

// Count — query terpisah, params sama dengan filter WHERE (tanpa OFFSET/LIMIT)
$countSql = "SELECT COUNT(*) AS total FROM tbOP op WITH (NOLOCK) LEFT JOIN tbSC sc WITH (NOLOCK) ON sc.cNoSc = op.cNoSc WHERE op.cNoMc IS NOT NULL";
if (!empty($where)) $countSql .= " AND " . implode(" AND ", $where);
// Gunakan salinan $params agar tidak dikonsumsi ulang
$countParams = $params;
$cStmt = sqlsrv_query($conn, $countSql, empty($countParams) ? [] : $countParams);
$total = 0;
if ($cStmt) {
    $cRow  = sqlsrv_fetch_array($cStmt, SQLSRV_FETCH_ASSOC);
    $total = (int)($cRow['total'] ?? 0);
    sqlsrv_free_stmt($cStmt);
}

$sql .= " ORDER BY op.dTglkirim2 DESC, op.dTgl DESC OFFSET $offset ROWS FETCH NEXT $limit ROWS ONLY";

$stmt = sqlsrv_query($conn, $sql, empty($params) ? [] : $params);
if ($stmt === false) {
    $errs = sqlsrv_errors();
    sqlsrv_close($conn);
    echo json_encode(['success' => false, 'message' => 'Query list gagal.', 'errors' => $errs], JSON_UNESCAPED_UNICODE);
    exit;
}

$rows = [];
$no   = $offset + 1;

if ($stmt !== false) {
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $r = [];
        foreach ($row as $k => $v) {
            $r[$k] = is_string($v) ? safeStr($v) : $v;
        }

        // Derived fields (sesuai header Excel)
        $r['no']         = $no++;
        $r['ukuran_dalam'] = ($r['nPanjang'] ?? 0).'x'.($r['nLebar'] ?? 0).'x'.($r['nTinggi'] ?? 0);
        $r['kualitas']   = implode(' / ', array_filter([
            $r['ckd_b1'] ?? '', $r['ckd_b2'] ?? '', $r['ckd_b3'] ?? '',
            $r['ckd_b4'] ?? '', $r['ckd_b5'] ?? ''
        ]));
        $r['tgl_kirim_label'] = ($r['lTK'] ?? '') == '1' ? 'Tunggu Kabar' : ($r['tgl_kirim'] ?? '-');
        $r['rack_name']  = mapRack($r['cRak'] ?? '');
        $r['pcs_kurang'] = max(0, (float)($r['total_order'] ?? 0) - (float)($r['jml_kirim'] ?? 0));
        $r['net_kirim']  = (float)($r['jml_kirim'] ?? 0) - (float)($r['jml_retur'] ?? 0);
        $r['status_lengkap'] = ((float)($r['pcs_kurang'] ?? 0) <= 0) ? 'SELESAI' : 'PROSES';

        // Flag MCList: data belum lengkap jika Corr atau Conv = 0 dan order > 0
        $orderQty = (float)($r['total_order'] ?? 0);
        $r['missing_corr']   = ($orderQty > 0 && (float)($r['hsl_corr'] ?? 0) == 0);
        $r['missing_conv']   = ($orderQty > 0 && (float)($r['hsl_conv'] ?? 0) == 0);
        $r['missing_stb']    = ($orderQty > 0 && (float)($r['jml_serah_trm'] ?? 0) == 0);
        $r['missing_kirim']  = ($orderQty > 0 && (float)($r['jml_kirim'] ?? 0) == 0);
        $r['data_incomplete']= ($r['missing_corr'] || $r['missing_conv'] || $r['missing_stb'] || $r['missing_kirim']);

        $rows[] = $r;
    }
    sqlsrv_free_stmt($stmt);
}

sqlsrv_close($conn);

$totalPages = $limit > 0 ? ceil($total / $limit) : 1;
$curPage    = $limit > 0 ? floor($offset / $limit) + 1 : 1;

echo json_encode([
    'success' => true,
    'data'    => $rows,
    'pagination' => [
        'total_records'   => (int)$total,
        'total_pages'     => $totalPages,
        'current_page'    => $curPage,
        'records_per_page'=> $limit,
        'offset'          => $offset,
        'has_prev'        => $offset > 0,
        'has_next'        => ($offset + $limit) < $total,
    ],
    'timestamp' => date('Y-m-d H:i:s'),
], JSON_UNESCAPED_UNICODE);
