<?php
/* ============================================================================
   PT SUPRACOR SEJAHTERA — BACKEND PENCOCOKAN STOK vs EXCEL
   Dibuat : 07 Agustus 2026

   HANYA MEMBACA. Tidak ada INSERT, UPDATE, DELETE, atau DDL apa pun.
   Yang dipanggil cuma:
       EXEC dbo.spStokPerTanggal @Posisi, 1   (prosedur baca, pakai tabel #temp)
       SELECT dari dbo.tbCekSaldoExcel
   Tabel tbStokGudangSnap, tbStokGudangExcel, tbStbBJ, tbSRJ TIDAK disentuh.
   Dashboard stok yang berjalan sekarang sama sekali tidak terpengaruh.

   Pemakaian:
       cek_backend.php?tanggal=2026-08-05     -> pencocokan pada tanggal itu
       cek_backend.php?action=ping            -> cek koneksi
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

if (trim($_GET['action'] ?? '') === 'ping') {
    sqlsrv_close($conn);
    kirim(['success' => true, 'message' => 'Koneksi OK', 'waktu' => date('c')]);
}

// Tanggal posisi, default 05 Agustus 2026 (posisi terakhir file Excel)
$tgl = trim($_GET['tanggal'] ?? '2026-08-05');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $tgl)) $tgl = '2026-08-05';

// Pastikan prosedur & tabel pembanding sudah ada
$cek = sqlsrv_query($conn, "SELECT
        CASE WHEN OBJECT_ID('dbo.spStokPerTanggal') IS NULL THEN 0 ELSE 1 END AS ada_proc,
        CASE WHEN OBJECT_ID('dbo.tbCekSaldoExcel')  IS NULL THEN 0 ELSE 1 END AS ada_xl",
    [], ["QueryTimeout" => 15]);
$adaProc = false; $adaXl = false;
if ($cek !== false) {
    $r = sqlsrv_fetch_array($cek, SQLSRV_FETCH_ASSOC);
    $adaProc = !empty($r['ada_proc']); $adaXl = !empty($r['ada_xl']);
    sqlsrv_free_stmt($cek);
}
if (!$adaProc || !$adaXl) {
    sqlsrv_close($conn);
    kirim(['success' => false,
           'message' => 'Prasyarat belum lengkap.',
           'hint'    => 'Jalankan stok_per_tanggal.sql dulu — file itu membuat spStokPerTanggal dan tbCekSaldoExcel.',
           'ada_proc' => $adaProc, 'ada_excel' => $adaXl], 500);
}

// --- Stok sistem pada tanggal yang diminta ---
$sistem = [];
$st = sqlsrv_query($conn, "{call dbo.spStokPerTanggal(?, ?)}", [$tgl, 1], ["QueryTimeout" => 600]);
if ($st === false) {
    $e = sqlsrv_errors();
    sqlsrv_close($conn);
    kirim(['success' => false, 'message' => 'Gagal menghitung stok: ' . ($e ? $e[0]['message'] : '')], 500);
}
do { if (sqlsrv_has_rows($st) === true) break; } while (sqlsrv_next_result($st));
while ($r = sqlsrv_fetch_array($st, SQLSRV_FETCH_ASSOC)) {
    $sc = trim($r['cNoSc'] ?? '');
    if ($sc === '') continue;
    $sistem[$sc] = ['pc' => (int)$r['nStokPc'],
                    'nama' => trim($r['cNama'] ?? ''),
                    'item' => trim($r['cNamabrg'] ?? '')];
}
sqlsrv_free_stmt($st);

// --- Saldo menurut file Excel gudang ---
$excel = [];
$st2 = sqlsrv_query($conn,
    "SELECT cNoScDb, cNama, cNamabrg, nSaldoPc, nSaldoKg,
            CONVERT(VARCHAR(10), dPosisi, 23) AS dPosisi
     FROM   dbo.tbCekSaldoExcel", [], ["QueryTimeout" => 120]);
$posisiExcel = null;
if ($st2 !== false) {
    while ($r = sqlsrv_fetch_array($st2, SQLSRV_FETCH_ASSOC)) {
        $sc = trim($r['cNoScDb']);
        $excel[$sc] = ['pc' => (int)$r['nSaldoPc'], 'kg' => round((float)$r['nSaldoKg'], 2),
                       'nama' => trim($r['cNama'] ?? ''), 'item' => trim($r['cNamabrg'] ?? '')];
        $posisiExcel = $r['dPosisi'];
    }
    sqlsrv_free_stmt($st2);
}
sqlsrv_close($conn);

// --- Cocokkan ---
$rows = []; $totXl = 0; $totSy = 0;
$kel = ['SAMA PERSIS' => [0,0], 'BEDA <= 100 pc' => [0,0], 'BEDA > 100 pc' => [0,0],
        'HANYA DI EXCEL' => [0,0], 'HANYA DI SISTEM' => [0,0]];

foreach (array_unique(array_merge(array_keys($excel), array_keys($sistem))) as $sc) {
    $x = $excel[$sc]['pc']  ?? null;
    $s = $sistem[$sc]['pc'] ?? null;
    $xv = $x ?? 0; $sv = $s ?? 0;
    $sel = $sv - $xv;

    if ($x === null)                 $k = 'HANYA DI SISTEM';
    elseif ($s === null)             $k = 'HANYA DI EXCEL';
    elseif ($sel === 0)              $k = 'SAMA PERSIS';
    elseif (abs($sel) <= 100)        $k = 'BEDA <= 100 pc';
    else                             $k = 'BEDA > 100 pc';

    $rows[] = [
        'sc'       => $sc,
        'customer' => $excel[$sc]['nama'] ?? ($sistem[$sc]['nama'] ?? ''),
        'item'     => $excel[$sc]['item'] ?? ($sistem[$sc]['item'] ?? ''),
        'excel'    => $xv, 'sistem' => $sv, 'selisih' => $sel, 'kelompok' => $k,
    ];
    $totXl += $xv; $totSy += $sv;
    $kel[$k][0] += 1; $kel[$k][1] += abs($sel);
}
usort($rows, function ($a, $b) { return abs($b['selisih']) - abs($a['selisih']); });

$kelOut = [];
foreach ($kel as $k => $v) $kelOut[] = ['kelompok' => $k, 'op' => $v[0], 'pc_selisih' => $v[1]];

$akurasi = $totXl != 0 ? round(100 * (1 - abs($totSy - $totXl) / $totXl), 2) : null;
$cocok   = $kel['SAMA PERSIS'][0] + $kel['BEDA <= 100 pc'][0];
$persenOp = count($rows) ? round(100 * $cocok / count($rows), 1) : null;

kirim([
    'success'       => true,
    'tanggal'       => $tgl,
    'posisi_excel'  => $posisiExcel,
    'setara'        => ($posisiExcel === $tgl),
    'dihitung_pada' => date('Y-m-d H:i:s'),
    'ringkas' => [
        'excel_pc'   => $totXl,
        'sistem_pc'  => $totSy,
        'selisih_pc' => $totSy - $totXl,
        'akurasi'    => $akurasi,
        'jml_op'     => count($rows),
        'op_cocok'   => $cocok,
        'persen_op'  => $persenOp,
    ],
    'kelompok' => $kelOut,
    'rows'     => $rows,
]);
