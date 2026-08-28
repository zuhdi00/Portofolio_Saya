<?php
/* ============================================================================
   PT SUPRACOR SEJAHTERA — BACKEND DASHBOARD STOK BARANG JADI (STB BJ)
   Versi cepat: membaca tabel snapshot, bukan menghitung ulang tiap request.

   Prasyarat : jalankan optimasi_stok.sql lebih dulu
               (membuat tbStokGudangSnap, tbStokGudangMutasi, tbStokGudangLog,
                dan stored procedure spRefreshStokGudang)

   Endpoint  :
     ?action=dashboard          -> data dashboard (default). Cepat, dari snapshot.
     ?action=refresh            -> paksa hitung ulang snapshot, lalu kirim data
     ?action=ping               -> cek koneksi database
   Parameter opsional:
     &batas_pc=150 &batas_kg=10 -> ambang kategori "stok kecil"
     &hari=14                   -> jumlah hari pada grafik mutasi
     &nocache=1                 -> abaikan cache file

   Catatan tim: lVoid / lPosted TIDAK difilter, mengikuti keputusan tim.
   ============================================================================ */

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
@ini_set('memory_limit', '1024M');

define('CACHE_FILE',   __DIR__ . '/cache_stok.json');
define('CACHE_DETIK',  60);   // cache file, aman karena snapshot diperbarui tiap 15 menit

function safeJsonEncode($data) {
    $opts = 0;
    if (defined('JSON_PARTIAL_OUTPUT_ON_ERROR')) $opts |= JSON_PARTIAL_OUTPUT_ON_ERROR;
    if (defined('JSON_UNESCAPED_UNICODE'))       $opts |= JSON_UNESCAPED_UNICODE;
    $json = json_encode($data, $opts);
    if ($json === false) $json = json_encode(['success' => false, 'message' => 'Internal JSON encoding error']);
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
    @error_log('[' . date('c') . "] $msg\n", 3, __DIR__ . '/stok_backend.log');
}
register_shutdown_function(function () {
    $err = error_get_last();
    if ($err && in_array($err['type'], [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_PARSE], true)) {
        logMsg('FATAL: ' . ($err['message'] ?? ''));
        while (ob_get_level()) ob_end_clean();
        http_response_code(500);
        header('Content-Type: application/json');
        echo safeJsonEncode(['success' => false, 'fatal' => true, 'message' => $err['message'] ?? 'Fatal error']);
        exit;
    }
});
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') { http_response_code(200); exit; }

$action    = trim($_GET['action'] ?? $_POST['action'] ?? 'dashboard');
$batasPc   = isset($_GET['batas_pc']) ? (int)$_GET['batas_pc']   : 150;
$batasKg   = isset($_GET['batas_kg']) ? (float)$_GET['batas_kg'] : 10.0;
$hariTrend = isset($_GET['hari'])     ? max(7, min(60, (int)$_GET['hari'])) : 14;
$noCache   = !empty($_GET['nocache']) || $action === 'refresh';

// ---- Cache file: jawab tanpa menyentuh database sama sekali ----
if (!$noCache && $action === 'dashboard' && is_readable(CACHE_FILE)) {
    $umurCache = time() - (int)@filemtime(CACHE_FILE);
    if ($umurCache >= 0 && $umurCache < CACHE_DETIK) {
        $isi = @file_get_contents(CACHE_FILE);
        if ($isi !== false && strlen($isi) > 20) {
            while (ob_get_level()) ob_end_clean();
            header('Content-Type: application/json');
            header('X-Cache: HIT ' . $umurCache . 's');
            echo $isi;
            exit;
        }
    }
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
        'detail'  => $err ? $err[0]['message'] : 'Server tidak aktif atau credentials salah',
        'server'  => $serverName
    ], 503);
}

if ($action === 'ping') {
    sqlsrv_close($conn);
    sendJson(['success' => true, 'message' => 'Koneksi OK', 'server' => $serverName, 'waktu' => date('c')]);
}

// ---- Pastikan snapshot tersedia ----
$cek = sqlsrv_query($conn, "SELECT
        CASE WHEN OBJECT_ID('dbo.tbStokGudangSnap')   IS NULL THEN 0 ELSE 1 END AS ada_snap,
        CASE WHEN OBJECT_ID('dbo.tbStokGudangAdj')    IS NULL THEN 0 ELSE 1 END AS ada_adj,
        CASE WHEN OBJECT_ID('dbo.spRefreshStokGudang') IS NULL THEN 0 ELSE 1 END AS ada_proc",
    [], ["QueryTimeout" => 15]);
$adaSnap = false; $adaAdj = false; $adaProc = false;
if ($cek !== false) {
    $r = sqlsrv_fetch_array($cek, SQLSRV_FETCH_ASSOC);
    $adaSnap = !empty($r['ada_snap']); $adaAdj = !empty($r['ada_adj']); $adaProc = !empty($r['ada_proc']);
    sqlsrv_free_stmt($cek);
}
if (!$adaSnap) {
    sqlsrv_close($conn);
    sendJson([
        'success' => false,
        'message' => 'Tabel snapshot belum dibuat.',
        'hint'    => 'Jalankan optimasi_stok.sql di dbSopanusa untuk membuat tbStokGudangSnap dan spRefreshStokGudang.'
    ], 500);
}

// ---- Hitung ulang bila diminta ----
$refreshInfo = null;
if ($action === 'refresh') {
    if (!$adaProc) {
        sqlsrv_close($conn);
        sendJson(['success' => false, 'message' => 'Stored procedure spRefreshStokGudang belum ada.',
                  'hint' => 'Jalankan optimasi_stok.sql lebih dulu.'], 500);
    }
    $mulai = microtime(true);
    $st = sqlsrv_query($conn, "{call dbo.spRefreshStokGudang(?, ?)}", ['WEB', 30], ["QueryTimeout" => 1800]);
    if ($st === false) {
        $err = sqlsrv_errors();
        logMsg('REFRESH GAGAL: ' . json_encode($err));
        sqlsrv_close($conn);
        sendJson(['success' => false, 'message' => 'Hitung ulang gagal: ' . ($err ? $err[0]['message'] : '')], 500);
    }
    sqlsrv_free_stmt($st);
    $refreshInfo = ['detik' => round(microtime(true) - $mulai, 1)];
    @unlink(CACHE_FILE);
    logMsg('Refresh manual selesai dalam ' . $refreshInfo['detik'] . ' detik');
}

// ============================================================================
// QUERY UTAMA — cuma baca snapshot, ringan
// ============================================================================
$sql = "SELECT cNoSc, cKodeCust, cNama, cNamabrg, cNoMC, cNamaSales, cType, cRak,
               nBerat, CONVERT(VARCHAR(10), dTglStbAkhir, 23) AS dTglStbAkhir,
               nUmur, nStokPc, nStokKg, ISNULL(cKeterangan,'') AS cKeterangan, lDariExcel,
               ISNULL(cStatusData, 'NORMAL') AS cStatusData
        FROM   dbo.tbStokGudangSnap
        ORDER  BY cNama, cNoSc";

$stmt = sqlsrv_query($conn, $sql, [], ["QueryTimeout" => 120]);
if ($stmt === false) {
    $err = sqlsrv_errors();
    logMsg('SQL ERROR (snap): ' . json_encode($err));
    sqlsrv_close($conn);
    sendJson(['success' => false, 'message' => $err ? $err[0]['message'] : 'Query snapshot gagal'], 500);
}

$rows = []; $totalPc = 0; $totalKg = 0.0; $negatif = 0;
$aging = ['<= 5 hari' => [0,0,0], '<= 7 hari' => [0,0,0], '<= 14 hari' => [0,0,0],
          '> 14 hari' => [0,0,0], 'Stok kecil' => [0,0,0], 'Tanpa tanggal' => [0,0,0]];
$perKet = [];
$perStatus = [];

while ($r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $pc   = (int)$r['nStokPc'];
    $kg   = round((float)$r['nStokKg'], 3);
    $umur = ($r['nUmur'] === null) ? null : (int)$r['nUmur'];

    if ($pc <= $batasPc && $kg <= $batasKg)  $kat = 'Stok kecil';
    elseif ($umur === null)                  $kat = 'Tanpa tanggal';
    elseif ($umur <= 5)                      $kat = '<= 5 hari';
    elseif ($umur <= 7)                      $kat = '<= 7 hari';
    elseif ($umur <= 14)                     $kat = '<= 14 hari';
    else                                     $kat = '> 14 hari';

    $ket = trim($r['cKeterangan']) !== '' ? trim($r['cKeterangan']) : 'Tanpa keterangan';

    $rows[] = [
        'sc'         => trim($r['cNoSc']),
        'customer'   => trim($r['cNama']),
        'item'       => trim($r['cNamabrg']),
        'no_mc'      => trim($r['cNoMC']),
        'sales'      => trim($r['cNamaSales']),
        'tipe'       => trim($r['cType']),
        'rak'        => trim($r['cRak']),
        'berat'      => round((float)$r['nBerat'], 5),
        'tgl_stb'    => $r['dTglStbAkhir'],
        'umur'       => $umur,
        'pc'         => $pc,
        'kg'         => $kg,
        'kategori'   => $kat,
        'keterangan' => $ket,
        'dari_excel' => (int)$r['lDariExcel'],
        'status_data'=> trim($r['cStatusData']),
    ];

    $totalPc += $pc; $totalKg += $kg;
    if ($pc < 0) $negatif++;
    $aging[$kat][0] += $pc; $aging[$kat][1] += $kg; $aging[$kat][2] += 1;
    $sd = trim($r['cStatusData']) !== '' ? trim($r['cStatusData']) : 'NORMAL';
    if (!isset($perStatus[$sd])) $perStatus[$sd] = [0, 0];
    $perStatus[$sd][0] += $pc; $perStatus[$sd][1] += 1;
    if (!isset($perKet[$ket])) $perKet[$ket] = [0,0,0];
    $perKet[$ket][0] += $pc; $perKet[$ket][1] += $kg; $perKet[$ket][2] += 1;
}
sqlsrv_free_stmt($stmt);

// ---- Mutasi harian dari tabel snapshot mutasi ----
$harian = [];
$st2 = @sqlsrv_query($conn,
    "SELECT TOP (?) CONVERT(VARCHAR(10), dTanggal, 23) AS tgl, nStbPc, nKirimPc
     FROM   dbo.tbStokGudangMutasi ORDER BY dTanggal DESC",
    [$hariTrend], ["QueryTimeout" => 60]);
if ($st2 !== false) {
    while ($r = sqlsrv_fetch_array($st2, SQLSRV_FETCH_ASSOC)) {
        $harian[] = ['tgl' => $r['tgl'], 'stb' => (int)$r['nStbPc'], 'kirim' => (int)$r['nKirimPc']];
    }
    sqlsrv_free_stmt($st2);
    $harian = array_reverse($harian);
}

// ---- Info sinkronisasi Excel ----
$sync = null;
if ($adaAdj) {
    $st3 = @sqlsrv_query($conn, "SELECT TOP 1 CONVERT(VARCHAR(10), dCutOff, 23) AS cut_off,
                                        CONVERT(VARCHAR(19), dHitung, 120) AS d_hitung,
                                        (SELECT COUNT(*) FROM dbo.tbStokGudangAdj) AS jml_op
                                 FROM dbo.tbStokGudangAdj ORDER BY dHitung DESC", [], ["QueryTimeout" => 30]);
    if ($st3 !== false) {
        $r = sqlsrv_fetch_array($st3, SQLSRV_FETCH_ASSOC);
        if ($r) $sync = ['cut_off' => $r['cut_off'], 'dihitung' => $r['d_hitung'], 'jml_op' => (int)$r['jml_op']];
        sqlsrv_free_stmt($st3);
    }
}

// ---- Kapan snapshot terakhir dihitung ----
$snap = null;
$st4 = @sqlsrv_query($conn, "SELECT TOP 1 CONVERT(VARCHAR(19), dSelesai, 120) AS selesai,
                                    nDetik, nJmlOp, cStatus, cSumber
                             FROM dbo.tbStokGudangLog
                             WHERE cStatus = 'SUKSES' ORDER BY nId DESC", [], ["QueryTimeout" => 30]);
if ($st4 !== false) {
    $r = sqlsrv_fetch_array($st4, SQLSRV_FETCH_ASSOC);
    if ($r) {
        $umurMenit = null;
        $ts = strtotime($r['selesai']);
        if ($ts) $umurMenit = (int)floor((time() - $ts) / 60);
        $snap = ['selesai' => $r['selesai'], 'detik' => (int)$r['nDetik'],
                 'jml_op' => (int)$r['nJmlOp'], 'sumber' => $r['cSumber'], 'umur_menit' => $umurMenit];
    }
    sqlsrv_free_stmt($st4);
}

sqlsrv_close($conn);

$agingOut = [];
foreach ($aging as $k => $v) $agingOut[] = ['kategori' => $k, 'pc' => $v[0], 'kg' => round($v[1], 2), 'op' => $v[2]];
$ketOut = [];
foreach ($perKet as $k => $v) $ketOut[] = ['keterangan' => $k, 'pc' => $v[0], 'kg' => round($v[1], 2), 'op' => $v[2]];
usort($ketOut, function ($a, $b) { return $b['pc'] - $a['pc']; });

$statusOut = [];
foreach ($perStatus as $k => $v) $statusOut[] = ['status' => $k, 'pc' => $v[0], 'op' => $v[1]];
usort($statusOut, function ($a, $b) { return $b['op'] - $a['op']; });

$hariIni = date('Y-m-d');
$stbHariIni = 0; $dlvHariIni = 0;
foreach ($harian as $h) {
    if ($h['tgl'] === $hariIni) { $stbHariIni = $h['stb']; $dlvHariIni = $h['kirim']; }
}

$hasil = [
    'success'    => true,
    'updated_at' => date('Y-m-d H:i:s'),
    'snapshot'   => $snap,
    'refresh'    => $refreshInfo,
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
    'status' => $statusOut,
    'aging'  => $agingOut,
    'ket'    => $ketOut,
    'harian' => $harian,
    'rows'   => $rows,
];

$json = safeJsonEncode($hasil);
@file_put_contents(CACHE_FILE, $json, LOCK_EX);

while (ob_get_level()) ob_end_clean();
header('Content-Type: application/json');
header('X-Cache: MISS');
echo $json;
exit;
