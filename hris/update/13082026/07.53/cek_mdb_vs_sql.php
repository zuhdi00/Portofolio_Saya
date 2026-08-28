<?php
/**
 * presensi/cek_mdb_vs_sql.php
 * PEMBANDING: jumlah tap di ATT2000.MDB  vs  dbo.zkteco_checkinout per hari.
 * TIDAK mengubah apa pun. Aman dijalankan kapan saja.
 *
 * Pakai:
 *   C:\xampp\php\php.exe C:\xampp\htdocs\hris\presensi\cek_mdb_vs_sql.php 30
 *   (angka = berapa hari ke belakang, default 30)
 *
 * Bacaan hasil:
 *   SELISIH > 0  -> data ADA di MDB tapi TIDAK masuk staging  => masalah IMPOR
 *   SELISIH = 0  -> impor sehat, masalahnya di sync_zkteco_proses.php
 */
set_time_limit(0);
ini_set('memory_limit', '1024M');

$HARI = isset($argv[1]) ? max(1, (int)$argv[1]) : 30;
function tulis($s = '') { echo $s . PHP_EOL; }

require __DIR__ . '/../config/koneksi_sqlsrv.php';   // $conn
require __DIR__ . '/../config/koneksi_mdb.php';

$mulai = date('Y-m-d', strtotime("-{$HARI} days"));
$akhir = date('Y-m-d', strtotime('+1 day'));

tulis("=== BANDING MDB vs SQL SERVER ({$mulai} s/d hari ini) ===");

/* ---- 1. hitung dari MDB ---- */
try { $mdb = buka_mdb(); }
catch (Exception $e) { tulis("ERROR MDB: " . $e->getMessage()); exit(1); }

$sql = "SELECT USERID, CHECKTIME, sn FROM CHECKINOUT
        WHERE CHECKTIME >= #" . date('m/d/Y', strtotime($mulai)) . "#
          AND CHECKTIME <  #" . date('m/d/Y', strtotime($akhir)) . "#";
$mdbPerHari = []; $mdbUser = []; $mdbSn = [];
foreach ($mdb->query($sql) as $r) {
    $d = substr(date_create($r['CHECKTIME'])->format('Y-m-d'), 0, 10);
    $mdbPerHari[$d] = ($mdbPerHari[$d] ?? 0) + 1;
    $mdbUser[$d][(int)$r['USERID']] = 1;
    $sn = trim((string)$r['sn']); $mdbSn[$sn] = ($mdbSn[$sn] ?? 0) + 1;
}

/* ---- 2. hitung dari SQL Server ---- */
$sqlPerHari = []; $sqlUser = [];
$st = sqlsrv_query($conn,
    "SELECT CAST(checktime AS DATE) tgl, COUNT(*) n, COUNT(DISTINCT zk_userid) org
     FROM dbo.zkteco_checkinout
     WHERE checktime >= ? AND checktime < ?
     GROUP BY CAST(checktime AS DATE)", [$mulai, $akhir]);
while ($r = sqlsrv_fetch_array($st, SQLSRV_FETCH_ASSOC)) {
    $d = $r['tgl'] instanceof DateTime ? $r['tgl']->format('Y-m-d') : substr((string)$r['tgl'], 0, 10);
    $sqlPerHari[$d] = (int)$r['n'];
    $sqlUser[$d]    = (int)$r['org'];
}

/* ---- 3. tampilkan ---- */
$semua = array_unique(array_merge(array_keys($mdbPerHari), array_keys($sqlPerHari)));
sort($semua);
tulis(str_pad('TANGGAL', 12) . str_pad('MDB', 8) . str_pad('SQL', 8)
    . str_pad('SELISIH', 9) . str_pad('ORG_MDB', 9) . str_pad('ORG_SQL', 9) . 'KETERANGAN');
tulis(str_repeat('-', 78));
$totalHilang = 0;
foreach ($semua as $d) {
    $m = $mdbPerHari[$d] ?? 0; $s = $sqlPerHari[$d] ?? 0; $sel = $m - $s;
    $totalHilang += max(0, $sel);
    $ket = $sel > 0 ? '<< TAP HILANG saat impor' : ($sel < 0 ? '(SQL lebih banyak?)' : '');
    tulis(str_pad($d, 12) . str_pad($m, 8) . str_pad($s, 8) . str_pad($sel, 9)
        . str_pad(count($mdbUser[$d] ?? []), 9) . str_pad($sqlUser[$d] ?? 0, 9) . $ket);
}
tulis(str_repeat('-', 78));
tulis("TOTAL tap ada di MDB tapi belum masuk staging: $totalHilang");

tulis();
tulis("--- Serial mesin yang muncul di MDB periode ini ---");
arsort($mdbSn);
$st = sqlsrv_query($conn, "SELECT kunci, nilai FROM dbo.pengaturan_absensi WHERE kunci IN ('mesin_masuk','mesin_keluar')");
$daftar = [];
while ($r = sqlsrv_fetch_array($st, SQLSRV_FETCH_ASSOC))
    foreach (explode(',', $r['nilai']) as $x) $daftar[trim($x)] = $r['kunci'];
foreach ($mdbSn as $sn => $n)
    tulis("  " . str_pad($sn === '' ? '(kosong)' : $sn, 20) . str_pad($n, 10)
        . (isset($daftar[$sn]) ? $daftar[$sn] : '<< BELUM TERDAFTAR (arah tap ditebak!)'));

tulis();
tulis("--- Posisi sinkron terakhir ---");
$st = sqlsrv_query($conn, "SELECT last_checktime, last_run, status, pesan FROM dbo.sync_zkteco_state");
while ($r = sqlsrv_fetch_array($st, SQLSRV_FETCH_ASSOC)) {
    $lc = $r['last_checktime'] instanceof DateTime ? $r['last_checktime']->format('Y-m-d H:i:s') : '-';
    $lr = $r['last_run'] instanceof DateTime ? $r['last_run']->format('Y-m-d H:i:s') : '-';
    tulis("  last_checktime : $lc" . (strtotime($lc) > time() ? '   << MASA DEPAN! jam mesin salah' : ''));
    tulis("  last_run       : $lr   status: {$r['status']}  {$r['pesan']}");
}
tulis();
tulis("Selesai.");
