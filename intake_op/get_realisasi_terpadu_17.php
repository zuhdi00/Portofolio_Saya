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
    // Default query timeout
    $opts = ["QueryTimeout" => 6000];
    $stmt = empty($params)
        ? sqlsrv_query($conn, $sql, [], $opts)
        : sqlsrv_query($conn, $sql, $params, $opts);
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
$shipFrom   = trim($_GET['ship_from']   ?? '');  // filter tgl kirim efektif FROM (dTanggal tbSRJ atau UserDate tbSRJDtl)
$shipTo     = trim($_GET['ship_to']     ?? '');  // filter tgl kirim efektif TO
$includeTotals = intval($_GET['include_totals'] ?? 0);
$scNo       = trim($_GET['sc_no']       ?? '');
$dateScFrom = trim($_GET['date_sc_from']?? '');
$dateScTo   = trim($_GET['date_sc_to']  ?? '');
// Read limit/offset. Support 'all' or 0 to fetch all records (no OFFSET/FETCH).
$limitRaw = $_GET['limit'] ?? null;
if ($limitRaw === 'all' || $limitRaw === '0' || $limitRaw === 0) {
    $limit = 0; // 0 means no limit (fetch all)
} else {
    $limit = intval($limitRaw ?? 200);
    if ($limit < 1) $limit = 200;
    // cap to a large safe number to avoid accidental OOM; adjust if needed
    $limit = min(1000000, $limit);
}
$offset = max(0, intval($_GET['offset'] ?? 0));

// ── Jika filter sc_no aktif: paksa ambil SEMUA OP tanpa pagination ──────────
// Satu SC bisa punya banyak OP. Tanpa ini, data terpotong oleh LIMIT default.
// Berlaku di sisi server agar tidak bergantung pada client mengirim limit=all.
if (!empty($scNo)) {
    $limit  = 0; // 0 = tanpa OFFSET/FETCH → semua baris dikembalikan
    $offset = 0;
}


// DEFAULT: jika tidak ada filter apapun, default ke Tgl SC = hari ini
$noAnyFilter = empty($search) && empty($mc) && empty($client) && empty($product)
            && empty($orderNo) && empty($flexo) && empty($dc) && empty($scNo)
            && empty($dateFrom) && empty($dateTo)
            && empty($shipFrom) && empty($shipTo)
            && empty($dateScFrom) && empty($dateScTo);
// Jika tidak ada filter apapun, jangan otomatis membatasi ke hari ini —
// supaya semua OP dari `tbOP` tetap dapat muncul.

// --- Main OP query — OPTIMIZED: semua aggregasi via pre-joined derived table ---

// =============================================================================
// Main query — basis tbSC (+ tbSCDtl), OP di-LEFT JOIN → SC tanpa OP tetap muncul
// Semua aggregasi realisasi tetap via derived table JOIN (tidak ada scalar subquery).
// =============================================================================
$sql = "SELECT
    -- Identitas (ambil dari OP karena tbSC tidak lagi dipakai)
    ISNULL(op.cNoSc, '')                         AS cNoSc,
    op.dTgl                                     AS tgl_sc,
    ISNULL(op.cnm_c, '')                        AS customer,
    ISNULL(op.cnm_brg, '')                      AS nama_brg,
    ISNULL(op.cJnsSc, '')                       AS jns_sc,
    ISNULL(sc.cSales, '')                       AS sales,
    ISNULL(op.cKetOrder, '')                    AS ket_mkt,
    ISNULL(op.nQty, 0)                          AS qty_sc,
        CASE WHEN op.lTK = 1 THEN 'Tunggu Kabar'
            ELSE CONVERT(VARCHAR, op.dTglkirim2, 23) END AS tgl_kirim_sc,
    CONVERT(VARCHAR, op.dTglkirim2, 23)         AS dTglKirim2,

    -- Dimensi dari OP
    op.nPanjang                                 AS nPanjang,
    op.nLebar                                   AS nLebar,
    op.nTinggi                                  AS nTinggi,
    op.cWarna                                   AS cWarna,

    -- Kualitas: tbTSC tidak lagi dipakai → kosongkan
    ISNULL('', '') AS ckd_b1,
    ISNULL('', '') AS ckd_b2,
    ISNULL('', '') AS ckd_b3,
    ISNULL('', '') AS ckd_b4,
    ISNULL('', '') AS ckd_b5,

    -- Data OP (bisa NULL jika belum ada)
    op.cNoOp                                    AS cNoOp,
    op.cNoMc                                    AS cNoMc,
    op.nQty                                     AS total_order,
    op.nQtyStok                                 AS last_plan,
    op.dTgl                                     AS tgl_op,
    op.dTglkirim                                AS tgl_kirim_awal,
    op.dTglkirim2                               AS tgl_kirim_op,
    op.cTipe,
    op.cFlexo,
    op.cDC,
    op.lTK                                      AS lTK_op,
    op.cMengetahui,
    op.cKetOrder,
    op.nTot_netto                               AS netto,
    op.nRm,
    op.cJnsGel                                  AS flute,
    op.userdate,

        ISNULL(stb_agg.stock_awal, 0)                                           AS stock_awal_gudang,
    ISNULL(stb_agg.jml_stb, 0)
        - 0
        + ISNULL(retur_agg.jml_retur, 0)                                    AS stock_akhir_gudang,

    -- Serah Terima
    ISNULL(stb_agg.jml_stb,  0)                AS jml_serah_trm,
    stb_agg.tgl_serah                           AS tgl_serah,
    stb_agg.cRak                                AS cRak,
    stb_agg.cShift                              AS cShift,

    -- Pengiriman (tidak lagi bergantung ke tbSRJ/tbSRJDtl)
    0                                           AS jml_kirim,
    NULL                                        AS tgl_kirim_srj_min,
    NULL                                        AS tgl_kirim_srj_max,
    NULL                                        AS userdate_srj,
    NULL                                        AS tujuan_kirim,
    0                                           AS tonase,

    -- Corrugating
    ISNULL(corr_agg.hsl_corr,  0)              AS hsl_corr,
    ISNULL(corr_agg.rsak_corr, 0)              AS rsak_corr,
    ISNULL(corr_agg.berat_corr,0)              AS berat_corr,
    ISNULL(corr_agg.plan_corr, 0)              AS plan_corr,

    -- Converting
    ISNULL(op.nQtyStok,        0)              AS hsl_conv,
    ISNULL(conv_agg.rsak_conv, 0)              AS rsak_conv,

    -- Retur
    ISNULL(retur_agg.jml_retur,0)             AS jml_retur

FROM tbOP op WITH (NOLOCK)

-- bring SC header when available to read sales and customer info
LEFT JOIN tbSC sc WITH (NOLOCK) ON op.cNoSc = sc.cNoSc

-- OP sebagai tabel dasar: tidak lagi LEFT JOIN ke tbSC/tbTSC

-- STB aggregat (join via cNoOp, hanya jika OP ada)
-- stock_awal  = total STB masuk sebelum pengiriman pertama (jika belum ada SRJ = semua STB)
-- stock_akhir = total STB - total SRJ + total Retur  → sisa barang di gudang
LEFT JOIN (
    SELECT
        s.cNoOp,
        SUM(ISNULL(s.nQty,0))  AS jml_stb,
        MAX(s.dTglSerah)        AS tgl_serah,
        MAX(s.cRak)             AS cRak,
        MAX(s.cShift)           AS cShift,
        -- Tanpa akses ke tbSRJ: anggap stok_awal = total STB masuk
        SUM(ISNULL(s.nQty, 0))  AS stock_awal
    FROM tbStbBJ s WITH (NOLOCK)
    GROUP BY s.cNoOp
) stb_agg ON stb_agg.cNoOp = op.cNoOp

    -- (SRJ/tbSRJDtl tidak lagi dipakai di intake_op)

    -- Corr aggregat
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

WHERE 1=1";

$params = [];
$where  = [];

if (!empty($search)) {
    $where[] = "(sc.cNoSc LIKE ? OR sc.cNama LIKE ? OR sc.cJenis LIKE ? OR op.cNoOp LIKE ?)";
    $p = '%'.$search.'%';
    $params[] = $p; $params[] = $p; $params[] = $p; $params[] = $p;
}
if (!empty($mc))        { $where[] = "op.cNoMc LIKE ?";        $params[] = '%'.$mc.'%'; }
if (!empty($client))    { $where[] = "sc.cNama LIKE ?";        $params[] = '%'.$client.'%'; }
if (!empty($product))   { $where[] = "sc.cJenis LIKE ?";       $params[] = '%'.$product.'%'; }
if (!empty($orderNo))   { $where[] = "op.cNoOp LIKE ?";        $params[] = '%'.$orderNo.'%'; }
if (!empty($flexo))     { $where[] = "op.cFlexo = ?";          $params[] = $flexo; }
if (!empty($scNo)) {
    // Jika user memberikan No SC, tarik semua OP yang terkait
    // Periksa baik header SC maupun kolom OP.cNoSc/op.cNoOp menggunakan LIKE
    $where[] = "(sc.cNoSc LIKE ? OR op.cNoSc LIKE ? OR op.cNoOp LIKE ?)";
    $params[] = '%'.$scNo.'%';
    $params[] = '%'.$scNo.'%';
    $params[] = $scNo . '%';
}
if (!empty($dc))        { $where[] = "op.cDC = ?";             $params[] = $dc; }
// Date filters: UI 'tgl OP' mapped to op.dTgl
if (!empty($dateScFrom)){
    $where[] = "op.dTgl >= ?";
    $params[] = $dateScFrom;
}
if (!empty($dateScTo)) {
    $where[] = "op.dTgl <= ?";
    $params[] = $dateScTo . ' 23:59:59';
}
if (!empty($dateFrom))  { $where[] = "op.dTgl >= ?";           $params[] = $dateFrom; }
if (!empty($dateTo))    { $where[] = "op.dTgl <= ?";           $params[] = $dateTo.' 23:59:59'; }

// Filter tanggal kirim: gunakan nilai `s.dTanggal` dari `tbSRJ` dan/atau `d.UserDate` dari `tbSRJDtl`
// agar tarikan data lengkap walau user mengisi UserDate pada detail SRJ.
if (!empty($shipFrom) && !empty($shipTo)) {
    // Keep OP rows that have no SRJ (so they still appear), but when SRJ exists
    // require at least one SRJ detail in the requested date range.
    $where[] = "(EXISTS (
        SELECT 1 FROM tbSRJDtl d2 WITH (NOLOCK)
        INNER JOIN tbSRJ s2 WITH (NOLOCK) ON s2.cNoSRJ = d2.cNoSRJ
        WHERE d2.cNoOp = op.cNoOp
          AND COALESCE(d2.UserDate, s2.dTanggal) >= ?
          AND COALESCE(d2.UserDate, s2.dTanggal) <= ?
    ) OR NOT EXISTS (SELECT 1 FROM tbSRJDtl d3 WITH (NOLOCK) WHERE d3.cNoOp = op.cNoOp))";
    $params[] = $shipFrom;
    $params[] = $shipTo . ' 23:59:59';
} elseif (!empty($shipFrom)) {
    $where[] = "(EXISTS (
        SELECT 1 FROM tbSRJDtl d2 WITH (NOLOCK)
        INNER JOIN tbSRJ s2 WITH (NOLOCK) ON s2.cNoSRJ = d2.cNoSRJ
        WHERE d2.cNoOp = op.cNoOp
          AND COALESCE(d2.UserDate, s2.dTanggal) >= ?
    ) OR NOT EXISTS (SELECT 1 FROM tbSRJDtl d3 WITH (NOLOCK) WHERE d3.cNoOp = op.cNoOp))";
    $params[] = $shipFrom;
} elseif (!empty($shipTo)) {
    $where[] = "(EXISTS (
        SELECT 1 FROM tbSRJDtl d2 WITH (NOLOCK)
        INNER JOIN tbSRJ s2 WITH (NOLOCK) ON s2.cNoSRJ = d2.cNoSRJ
        WHERE d2.cNoOp = op.cNoOp
          AND COALESCE(d2.UserDate, s2.dTanggal) <= ?
    ) OR NOT EXISTS (SELECT 1 FROM tbSRJDtl d3 WITH (NOLOCK) WHERE d3.cNoOp = op.cNoOp))";
    $params[] = $shipTo . ' 23:59:59';
}

if (!empty($where)) {
    $sql .= " AND " . implode(" AND ", $where);
}

// Count query — mirror main query filters. We use the same WHERE clauses
// (which now rely on EXISTS(...) against tbSRJ/tbSRJDtl), so no extra joins needed.
$countSql = "SELECT COUNT(*) AS total
FROM tbOP op WITH (NOLOCK)
LEFT JOIN tbSC sc WITH (NOLOCK) ON op.cNoSc = sc.cNoSc
WHERE 1=1";
if (!empty($where)) $countSql .= " AND " . implode(" AND ", $where);

$countParams = $params;
$cStmt = sqlsrv_query($conn, $countSql, empty($countParams) ? [] : $countParams, ["QueryTimeout"=>600]);
$total = 0;
if ($cStmt) {
    $cRow  = sqlsrv_fetch_array($cStmt, SQLSRV_FETCH_ASSOC);
    $total = (int)($cRow['total'] ?? 0);
    sqlsrv_free_stmt($cStmt);
}

// If client asks for totals across all matching rows, run unpaged summary
// (no unpaged summary to avoid heavy server load)

// Ordering: prioritas OP yg cNoOp-nya ada duluan, lalu tgl OP desc
// Gunakan TOP-based paging agar kompatibel dengan SQL Server versi lama
// (OFFSET/FETCH NEXT tidak didukung di semua versi).
$orderBy = " ORDER BY CASE WHEN op.cNoOp IS NULL THEN 0 ELSE 1 END ASC, op.dTgl DESC";

if ($limit > 0) {
    $fetchTop = $offset + $limit;
    // Inject TOP N setelah kata SELECT pertama
    $sql = preg_replace('/^(\s*SELECT\s+)/i', "SELECT TOP $fetchTop ", $sql, 1);
    $sql .= $orderBy;
} else {
    $sql .= $orderBy;
}

// Main query may be expensive for wide date ranges — give it more time
$stmt = sqlsrv_query($conn, $sql, empty($params) ? [] : $params, ["QueryTimeout"=>600]);
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

        $r['no']          = $no++;
        $r['op_belum']    = empty($r['cNoOp']);  // true jika SC belum punya OP
        $r['ukuran_dalam'] = round($r['nPanjang'] ?? 0).'x'.round($r['nLebar'] ?? 0).'x'.round($r['nTinggi'] ?? 0);
        $r['kualitas']    = implode(' / ', array_filter([
            $r['ckd_b1'] ?? '', $r['ckd_b2'] ?? '', $r['ckd_b3'] ?? '',
            $r['ckd_b4'] ?? '', $r['ckd_b5'] ?? ''
        ]));

        // Gunakan total_order dari OP jika ada, fallback ke qty_sc
        $totalOrder = (float)($r['total_order'] ?? $r['qty_sc'] ?? 0);
        $r['total_order_eff'] = $totalOrder;

        $r['tgl_kirim_label'] = ($r['lTK_op'] ?? '') == '1' ? 'Tunggu Kabar'
            : ((!empty($r['dTglKirim2'])) ? $r['dTglKirim2'] : ($r['tgl_kirim_sc'] ?? '-'));
        // Label tanggal kirim aktual dari SRJ: gunakan MIN(dTanggal) sebagai acuan
        $r['tgl_kirim_aktual'] = !empty($r['tgl_kirim_srj_min'])
            ? $r['tgl_kirim_srj_min']
            : null;
        // Tanggal kirim terakhir: gunakan MAX(dTanggal)
        $r['tgl_kirim_terakhir'] = $r['tgl_kirim_srj_max'] ?? null;
        // UserDate jika tersedia (untuk informasi tambahan)
        $r['tgl_userdate'] = $r['userdate_srj'] ?? null;
        $r['rack_name']   = mapRack($r['cRak'] ?? '');
        $r['pcs_kurang']  = max(0, $totalOrder - (float)($r['jml_kirim'] ?? 0));
        $r['net_kirim']   = (float)($r['jml_kirim'] ?? 0) - (float)($r['jml_retur'] ?? 0);
        // Jika sudah ada pengiriman (jml_kirim>0) atau net_kirim>0, anggap selesai juga
        $hasDelivery = ((float)($r['jml_kirim'] ?? 0) > 0) || ((float)($r['net_kirim'] ?? 0) > 0);
        $r['status_lengkap'] = (!$r['op_belum'] && ( (float)($r['pcs_kurang'] ?? 0) <= 0 || $hasDelivery )) ? 'SELESAI' : 'PROSES';

        $orderQty = $totalOrder;
        $r['missing_corr']  = (!$r['op_belum'] && $orderQty > 0 && (float)($r['hsl_corr']     ?? 0) == 0);
        $r['missing_conv']  = (!$r['op_belum'] && $orderQty > 0 && (float)($r['hsl_conv']     ?? 0) == 0);
        $r['missing_stb']   = (!$r['op_belum'] && $orderQty > 0 && (float)($r['jml_serah_trm']?? 0) == 0);
        $r['missing_kirim'] = (!$r['op_belum'] && $orderQty > 0 && (float)($r['jml_kirim']    ?? 0) == 0);
        $r['data_incomplete'] = $r['op_belum'] || $r['missing_corr'] || $r['missing_conv'] || $r['missing_stb'] || $r['missing_kirim'];

        $rows[] = $r;
    }
    sqlsrv_free_stmt($stmt);
}

sqlsrv_close($conn);

// Jika gunakan TOP-based paging, potong baris sesuai halaman
if ($limit > 0 && count($rows) > 0) {
    $rows = array_slice($rows, $offset, $limit);
}

if ($limit > 0) {
    $totalPages = $limit > 0 ? ceil($total / $limit) : 1;
    $curPage    = $limit > 0 ? floor($offset / $limit) + 1 : 1;
    $recordsPerPage = $limit;
    $respOffset = $offset;
    $hasPrev = $offset > 0;
    $hasNext = ($offset + $limit) < $total;
} else {
    // No pagination mode: return all rows
    $totalPages = 1;
    $curPage = 1;
    $recordsPerPage = (int)$total;
    $respOffset = 0;
    $hasPrev = false;
    $hasNext = false;
}

echo json_encode([
    'success' => true,
    'data'    => $rows,
    'pagination' => [
        'total_records'   => (int)$total,
        'total_pages'     => $totalPages,
        'current_page'    => $curPage,
        'records_per_page'=> $recordsPerPage,
        'offset'          => $respOffset,
        'has_prev'        => $hasPrev,
        'has_next'        => $hasNext,
    ],
    'timestamp' => date('Y-m-d H:i:s'),
], JSON_UNESCAPED_UNICODE);
