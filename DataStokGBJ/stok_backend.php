<?php
// ============================================================================
// PT SUPRACOR SEJAHTERA — BACKEND DASHBOARD STOK BARANG JADI (STB BJ)
// Endpoint READ-ONLY. Tidak ada INSERT/UPDATE/DELETE ke tabel manapun.
//
// Dipakai oleh: stok_dashboard.html
// Prasyarat   : tabel tbStokGudangAdj & tbStokGudangExcel sudah dibuat
//               (lihat import_stok_stb_bj.sql)
//
// Catatan tim : lVoid / lPosted TIDAK difilter, mengikuti keputusan tim.
//               Kalau suatu saat mau difilter, tambahkan di CTE stb/kirim.
//
// Action:
//   ?action=dashboard  -> ringkasan + aging + keterangan + mutasi harian + detail
//   ?action=ping       -> cek koneksi database
// ============================================================================

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Max-Age: 86400');
header('Content-Type: application/json');
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Private-Network: true');

@ini_set('display_errors', '0');
@ini_set('log_errors', '1');
error_reporting(E_ALL);
ob_start();
@set_time_limit(0);
@ini_set('memory_limit', '512M');

function safeJsonEncode($data) {
    $opts = 0;
    if (defined('JSON_PARTIAL_OUTPUT_ON_ERROR')) $opts |= JSON_PARTIAL_OUTPUT_ON_ERROR;
    if (defined('JSON_UNESCAPED_UNICODE'))       $opts |= JSON_UNESCAPED_UNICODE;
    $json = json_encode($data, $opts);
    if ($json === false) {
        $json = json_encode(['success' => false, 'message' => 'Gagal encode JSON']);
    }
    return $json;
}

function sendJson($data, $code = 200) {
    while (ob_get_level()) ob_end_clean();
    http_response_code($code);
    header('Content-Type: application/json');
    echo safeJsonEncode($data);
    exit;
}

function logMsg($msg) {
    error_log('[' . date('c') . "] $msg\n", 3, __DIR__ . '/stok_backend.log');
}

register_shutdown_function(function () {
    $err = error_get_last();
    if ($err && in_array($err['type'], [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_PARSE], true)) {
        logMsg('FATAL: ' . $err['message'] . ' @ ' . $err['file'] . ':' . $err['line']);
        while (ob_get_level()) ob_end_clean();
        http_response_code(500);
        header('Content-Type: application/json');
        echo safeJsonEncode(['success' => false, 'fatal' => true, 'message' => $err['message']]);
        exit;
    }
});

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header('Access-Control-Allow-Private-Network: true');
    http_response_code(200);
    exit;
}

// === Database config (sama dengan report_backend.php) ===
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
    sendJson([
        'success' => false,
        'message' => 'Koneksi database gagal',
        'detail'  => $err ? $err[0]['message'] : 'Server tidak merespons',
        'server'  => $serverName
    ], 503);
}

$action = trim($_GET['action'] ?? $_POST['action'] ?? 'dashboard');

if ($action === 'ping') {
    sqlsrv_close($conn);
    sendJson(['success' => true, 'message' => 'Koneksi database OK', 'server' => $serverName]);
}

// ----------------------------------------------------------------------------
// Ambang batas kategori "stok kecil" — samakan dengan file Excel gudang
// ----------------------------------------------------------------------------
$batasPc = isset($_GET['batas_pc']) ? (int)$_GET['batas_pc'] : 150;
$batasKg = isset($_GET['batas_kg']) ? (float)$_GET['batas_kg'] : 10.0;
$hariTrend = isset($_GET['hari']) ? max(7, min(60, (int)$_GET['hari'])) : 14;

// ============================================================================
// QUERY 1 — Stok per NO. OP
// sisa_stb = (STB - dtstock - adj) + retur - kirim   (sama dgn report_backend)
// ============================================================================
$sqlStok = "
WITH stb AS (
    SELECT cNoSc, SUM(ISNULL(nQty,0)) AS qty, MAX(dTanggal) AS tgl_stb_akhir
    FROM   tbStbBJ
    GROUP  BY cNoSc
),
dtstock AS (
    SELECT cNoSc, SUM(ISNULL(nStock,0)) AS qty FROM tbDtStockDtl GROUP BY cNoSc
),
adj AS (
    SELECT cNoSc, SUM(ISNULL(nAdjust,0)) AS qty FROM tbStokGudangAdj GROUP BY cNoSc
),
retur AS (
    SELECT COALESCE(d.cNoScDtl, s.cNoSC) AS cNoSc, SUM(ISNULL(rv.nQty,0)) AS qty
    FROM   vwReturnSrj rv
    INNER JOIN tbSRJDtl d ON d.cNoSRJ = rv.cNoSrj
    INNER JOIN tbSRJ    s ON s.cNoSRJ = d.cNoSRJ
    GROUP  BY COALESCE(d.cNoScDtl, s.cNoSC)
),
kirim AS (
    SELECT COALESCE(d.cNoScDtl, s.cNoSC) AS cNoSc, SUM(ISNULL(d.nQty,0)) AS qty
    FROM   tbSRJ s
    INNER JOIN tbSRJDtl d ON s.cNoSRJ = d.cNoSRJ
    GROUP  BY COALESCE(d.cNoScDtl, s.cNoSC)
),
info AS (
    SELECT cNoSc, cKodeCust, cNama, cNamabrg, cNoMC, cNoOp, cKdSales, cNamaSales,
           nberat, nPanjang, nLebar, nTinggi, cType, cRak,
           ROW_NUMBER() OVER (PARTITION BY cNoSc ORDER BY dTanggal DESC, cNoSTB DESC) AS rn
    FROM   tbStbBJ
)
SELECT  s.cNoSc,
        ISNULL(i.cKodeCust,'')  AS kode_cust,
        ISNULL(i.cNama,'')      AS customer,
        ISNULL(i.cNamabrg,'')   AS item,
        ISNULL(i.cNoMC,'')      AS no_mc,
        ISNULL(i.cNamaSales,'') AS sales,
        ISNULL(i.cType,'')      AS tipe,
        ISNULL(i.cRak,'')       AS rak,
        ISNULL(i.nberat,0)      AS berat,
        CONVERT(VARCHAR(10), s.tgl_stb_akhir, 23) AS tgl_stb_akhir,
        DATEDIFF(day, s.tgl_stb_akhir, CAST(GETDATE() AS date)) AS umur,
        (ISNULL(s.qty,0) - ISNULL(dt.qty,0) - ISNULL(aj.qty,0)
            + ISNULL(rt.qty,0) - ISNULL(kr.qty,0))              AS stok_pc,
        ISNULL(e.cKeterangan,'') AS keterangan,
        CASE WHEN e.cNoSc IS NULL THEN 0 ELSE 1 END AS dari_excel
FROM       stb s
LEFT JOIN  dtstock dt ON dt.cNoSc = s.cNoSc
LEFT JOIN  adj     aj ON aj.cNoSc = s.cNoSc
LEFT JOIN  retur   rt ON rt.cNoSc = s.cNoSc
LEFT JOIN  kirim   kr ON kr.cNoSc = s.cNoSc
LEFT JOIN  tbStokGudangExcel e ON e.cNoSc = s.cNoSc
LEFT JOIN  info    i  ON i.cNoSc  = s.cNoSc AND i.rn = 1
WHERE      (ISNULL(s.qty,0) - ISNULL(dt.qty,0) - ISNULL(aj.qty,0)
            + ISNULL(rt.qty,0) - ISNULL(kr.qty,0)) <> 0
ORDER BY   customer, s.cNoSc
";

$stmt = sqlsrv_query($conn, $sqlStok, [], ["QueryTimeout" => 600]);
if ($stmt === false) {
    $err = sqlsrv_errors();
    logMsg('SQL ERROR (stok): ' . json_encode($err));
    sqlsrv_close($conn);
    sendJson([
        'success' => false,
        'message' => $err ? $err[0]['message'] : 'Query stok gagal',
        'hint'    => 'Pastikan tabel tbStokGudangAdj dan tbStokGudangExcel sudah dibuat lewat import_stok_stb_bj.sql'
    ], 500);
}

$rows       = [];
$totalPc    = 0;
$totalKg    = 0.0;
$aging      = ['<= 5 hari' => [0,0,0], '<= 7 hari' => [0,0,0], '<= 14 hari' => [0,0,0],
               '> 14 hari' => [0,0,0], 'Stok kecil' => [0,0,0], 'Tanpa tanggal' => [0,0,0]];
$perKet     = [];
$negatif    = 0;

while ($r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $pc    = (int)$r['stok_pc'];
    $berat = (float)$r['berat'];
    $kg    = round($pc * $berat, 5);
    $umur  = $r['umur'] === null ? null : (int)$r['umur'];

    if ($pc <= $batasPc && $kg <= $batasKg)  $kat = 'Stok kecil';
    elseif ($umur === null)                  $kat = 'Tanpa tanggal';
    elseif ($umur <= 5)                      $kat = '<= 5 hari';
    elseif ($umur <= 7)                      $kat = '<= 7 hari';
    elseif ($umur <= 14)                     $kat = '<= 14 hari';
    else                                     $kat = '> 14 hari';

    $ket = trim($r['keterangan']) !== '' ? trim($r['keterangan']) : 'Tanpa keterangan';

    $rows[] = [
        'sc'         => trim($r['cNoSc']),
        'customer'   => trim($r['customer']),
        'item'       => trim($r['item']),
        'no_mc'      => trim($r['no_mc']),
        'sales'      => trim($r['sales']),
        'tipe'       => trim($r['tipe']),
        'rak'        => trim($r['rak']),
        'berat'      => $berat,
        'tgl_stb'    => $r['tgl_stb_akhir'],
        'umur'       => $umur,
        'pc'         => $pc,
        'kg'         => $kg,
        'kategori'   => $kat,
        'keterangan' => $ket,
        'dari_excel' => (int)$r['dari_excel'],
    ];

    $totalPc += $pc;
    $totalKg += $kg;
    if ($pc < 0) $negatif++;

    $aging[$kat][0] += $pc;
    $aging[$kat][1] += $kg;
    $aging[$kat][2] += 1;

    if (!isset($perKet[$ket])) $perKet[$ket] = [0, 0, 0];
    $perKet[$ket][0] += $pc;
    $perKet[$ket][1] += $kg;
    $perKet[$ket][2] += 1;
}
sqlsrv_free_stmt($stmt);

// ============================================================================
// QUERY 2 — Mutasi harian (STB masuk vs kirim keluar)
// ============================================================================
$sqlHarian = "
WITH kalender AS (
    SELECT CAST(DATEADD(day, -n, CAST(GETDATE() AS date)) AS date) AS tgl
    FROM (SELECT TOP (?) ROW_NUMBER() OVER (ORDER BY (SELECT NULL)) - 1 AS n
          FROM sys.all_objects) x
),
stb_h AS (
    SELECT CAST(dTanggal AS date) AS tgl, SUM(ISNULL(nQty,0)) AS qty
    FROM   tbStbBJ
    WHERE  dTanggal >= DATEADD(day, -?, CAST(GETDATE() AS date))
    GROUP  BY CAST(dTanggal AS date)
),
kirim_h AS (
    SELECT CAST(s.dTanggal AS date) AS tgl, SUM(ISNULL(d.nQty,0)) AS qty
    FROM   tbSRJ s
    INNER JOIN tbSRJDtl d ON d.cNoSRJ = s.cNoSRJ
    WHERE  s.dTanggal >= DATEADD(day, -?, CAST(GETDATE() AS date))
    GROUP  BY CAST(s.dTanggal AS date)
)
SELECT CONVERT(VARCHAR(10), k.tgl, 23) AS tgl,
       ISNULL(sh.qty, 0) AS stb,
       ISNULL(kh.qty, 0) AS kirim
FROM       kalender k
LEFT JOIN  stb_h   sh ON sh.tgl = k.tgl
LEFT JOIN  kirim_h kh ON kh.tgl = k.tgl
ORDER BY   k.tgl
";

$harian = [];
$stmt2 = sqlsrv_query($conn, $sqlHarian, [$hariTrend, $hariTrend, $hariTrend], ["QueryTimeout" => 300]);
if ($stmt2 !== false) {
    while ($r = sqlsrv_fetch_array($stmt2, SQLSRV_FETCH_ASSOC)) {
        $harian[] = [
            'tgl'   => $r['tgl'],
            'stb'   => (int)$r['stb'],
            'kirim' => (int)$r['kirim'],
        ];
    }
    sqlsrv_free_stmt($stmt2);
} else {
    logMsg('SQL ERROR (harian): ' . json_encode(sqlsrv_errors()));
}

// ============================================================================
// QUERY 3 — Info sinkronisasi terakhir
// ============================================================================
$sync = null;
$stmt3 = @sqlsrv_query($conn, "SELECT TOP 1 CONVERT(VARCHAR(10), dCutOff, 23) AS cut_off,
                                      CONVERT(VARCHAR(19), dHitung, 120) AS d_hitung,
                                      (SELECT COUNT(*) FROM tbStokGudangAdj) AS jml_op
                               FROM tbStokGudangAdj ORDER BY dHitung DESC", [], ["QueryTimeout" => 30]);
if ($stmt3 !== false) {
    $r = sqlsrv_fetch_array($stmt3, SQLSRV_FETCH_ASSOC);
    if ($r) $sync = ['cut_off' => $r['cut_off'], 'dihitung' => $r['d_hitung'], 'jml_op' => (int)$r['jml_op']];
    sqlsrv_free_stmt($stmt3);
}

sqlsrv_close($conn);

// Rapikan agregat jadi array
$agingOut = [];
foreach ($aging as $k => $v) {
    $agingOut[] = ['kategori' => $k, 'pc' => $v[0], 'kg' => round($v[1], 2), 'op' => $v[2]];
}
$ketOut = [];
foreach ($perKet as $k => $v) {
    $ketOut[] = ['keterangan' => $k, 'pc' => $v[0], 'kg' => round($v[1], 2), 'op' => $v[2]];
}
usort($ketOut, function ($a, $b) { return $b['pc'] - $a['pc']; });

$hariIni    = date('Y-m-d');
$stbHariIni = 0;
$dlvHariIni = 0;
foreach ($harian as $h) {
    if ($h['tgl'] === $hariIni) { $stbHariIni = $h['stb']; $dlvHariIni = $h['kirim']; }
}

logMsg("dashboard rows=" . count($rows) . " total_pc={$totalPc}");

sendJson([
    'success'    => true,
    'updated_at' => date('Y-m-d H:i:s'),
    'summary'    => [
        'total_pc'     => $totalPc,
        'total_kg'     => round($totalKg, 2),
        'jml_op'       => count($rows),
        'stb_hari_ini' => $stbHariIni,
        'dlv_hari_ini' => $dlvHariIni,
        'op_negatif'   => $negatif,
        'batas_pc'     => $batasPc,
        'batas_kg'     => $batasKg,
    ],
    'sync'   => $sync,
    'aging'  => $agingOut,
    'ket'    => $ketOut,
    'harian' => $harian,
    'rows'   => $rows,
]);
