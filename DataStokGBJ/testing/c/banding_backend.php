<?php
/* ============================================================================
   PT SUPRACOR SEJAHTERA — BACKEND HALAMAN PERBANDINGAN EXCEL vs SISTEM
   Dibuat : 14 Agustus 2026

   HANYA MEMBACA. Tidak ada INSERT, UPDATE, DELETE, atau DDL.
   Yang dipanggil hanya spStokTipePerTanggal, prosedur baca-saja yang memakai
   tabel #temp. Snapshot dashboard tidak terpengaruh sama sekali, jadi halaman
   ini aman dibuka kapan saja sambil dashboard tetap berjalan.

   Pemakaian:
     banding_backend.php?tanggal=2026-08-12   -> stok sistem pada tanggal itu
     banding_backend.php?action=ping          -> cek koneksi
   ============================================================================ */

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json');
header('Access-Control-Allow-Private-Network: true');

@ini_set('display_errors', '0');
error_reporting(E_ALL);
ob_start();
@set_time_limit(0);
@ini_set('memory_limit', '1024M');

define('VERSI_BANDING', '1.0');

function kirim($data, $code = 200) {
    while (ob_get_level()) ob_end_clean();
    http_response_code($code);
    header('Content-Type: application/json');
    $o = 0;
    if (defined('JSON_PARTIAL_OUTPUT_ON_ERROR')) $o |= JSON_PARTIAL_OUTPUT_ON_ERROR;
    if (defined('JSON_UNESCAPED_UNICODE'))       $o |= JSON_UNESCAPED_UNICODE;
    echo json_encode($data, $o);
    exit;
}
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') { http_response_code(200); exit; }

$serverName = "spsdmz2";
$connectionOptions = [
    "Database" => "dbSopanusa", "Uid" => "sa", "PWD" => "supracor",
    "LoginTimeout" => 15, "Encrypt" => false, "TrustServerCertificate" => true
];
$conn = @sqlsrv_connect($serverName, $connectionOptions);
if (!$conn) {
    $e = sqlsrv_errors();
    kirim(['success' => false, 'message' => 'Koneksi database gagal',
           'detail' => $e ? $e[0]['message'] : 'Server tidak aktif'], 503);
}

$action = trim($_GET['action'] ?? 'stok');

if ($action === 'ping' || $action === 'versi') {
    sqlsrv_close($conn);
    kirim(['success' => true, 'versi' => VERSI_BANDING, 'waktu' => date('c')]);
}

// Prosedur wajib ada
$adaProc = false;
$c = @sqlsrv_query($conn, "SELECT CASE WHEN OBJECT_ID('dbo.spStokTipePerTanggal') IS NULL THEN 0 ELSE 1 END AS ada",
                   [], ["QueryTimeout" => 15]);
if ($c !== false) { $r = sqlsrv_fetch_array($c, SQLSRV_FETCH_ASSOC); $adaProc = !empty($r['ada']); sqlsrv_free_stmt($c); }
if (!$adaProc) {
    sqlsrv_close($conn);
    kirim(['success' => false,
           'message' => 'Prosedur spStokTipePerTanggal belum ada di database.',
           'hint' => 'Jalankan sp_stok_tipe_per_tanggal.sql lebih dulu.'], 500);
}

$tgl = trim($_GET['tanggal'] ?? date('Y-m-d'));
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $tgl)) $tgl = date('Y-m-d');

// Patokan yang berlaku
$patokan = null;
$cp = @sqlsrv_query($conn, "SELECT CONVERT(VARCHAR(10), MAX(dCutOff), 23) AS cut_off,
                                   COUNT(*) AS baris, SUM(nStokAkhirPc) AS pc
                            FROM dbo.tbStokGudangExcel", [], ["QueryTimeout" => 30]);
if ($cp !== false) {
    $r = sqlsrv_fetch_array($cp, SQLSRV_FETCH_ASSOC);
    if ($r && $r['cut_off'])
        $patokan = ['cut_off' => $r['cut_off'], 'baris' => (int)$r['baris'], 'pc' => (int)$r['pc']];
    sqlsrv_free_stmt($cp);
}

// Patokan per kategori, untuk memeriksa apakah saldo awal masih sama dengan Excel
$patKat = [];
$ck = @sqlsrv_query($conn, "SELECT cKategori, COUNT(*) AS baris, SUM(nStokAkhirPc) AS pc
                            FROM dbo.tbStokGudangExcel GROUP BY cKategori", [], ["QueryTimeout" => 30]);
if ($ck !== false) {
    while ($r = sqlsrv_fetch_array($ck, SQLSRV_FETCH_ASSOC))
        $patKat[trim($r['cKategori'])] = ['baris' => (int)$r['baris'], 'pc' => (int)$r['pc']];
    sqlsrv_free_stmt($ck);
}

// Stok sistem pada tanggal yang diminta
$mulai = microtime(true);
$st = sqlsrv_query($conn, "{call dbo.spStokTipePerTanggal(?)}", [$tgl], ["QueryTimeout" => 600]);
if ($st === false) {
    $e = sqlsrv_errors();
    sqlsrv_close($conn);
    kirim(['success' => false, 'message' => 'Gagal menghitung stok: ' . ($e ? $e[0]['message'] : '')], 500);
}
do { if (sqlsrv_has_rows($st) === true) break; } while (sqlsrv_next_result($st));

$rows = [];
while ($r = sqlsrv_fetch_array($st, SQLSRV_FETCH_ASSOC)) {
    $rows[] = [
        'sc'        => trim($r['cNoSc']),
        'kelompok'  => trim($r['cKelompok']),
        'awal'      => (int)$r['nSaldoAwal'],
        'stb'       => (int)$r['nStb'],
        'kirim'     => (int)$r['nKirim'],
        'retur'     => (int)$r['nRetur'],
        'koreksi'   => (int)$r['nKoreksi'],
        'pc'        => (int)$r['nStokPc'],
        'customer'  => trim($r['cNama'] ?? ''),
        'item'      => trim($r['cNamabrg'] ?? ''),
    ];
}
sqlsrv_free_stmt($st);
sqlsrv_close($conn);

$perKel = [];
foreach ($rows as $r) {
    $k = $r['kelompok'];
    if (!isset($perKel[$k])) $perKel[$k] = ['kelompok' => $k, 'pc' => 0, 'op' => 0];
    $perKel[$k]['pc'] += $r['pc'];
    $perKel[$k]['op'] += 1;
}
$kelOut = array_values($perKel);
usort($kelOut, function ($a, $b) { return $b['pc'] - $a['pc']; });

kirim([
    'success'      => true,
    'versi'        => VERSI_BANDING,
    'tanggal'      => $tgl,
    'detik'        => round(microtime(true) - $mulai, 1),
    'patokan'      => $patokan,
    'patokan_kat'  => $patKat,
    'per_kelompok' => $kelOut,
    'total_pc'     => array_sum(array_column($rows, 'pc')),
    'jml_baris'    => count($rows),
    'rows'         => $rows,
]);
