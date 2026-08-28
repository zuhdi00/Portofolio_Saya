<?php
/**
 * presensi/tarik_historis_zkteco.php
 * SEKALI JALAN - tarik SEMUA tap historis dari MDB -> dbo.zkteco_checkinout.
 * Lebih cepat dari sync_zkteco_import.php karena:
 *   - insert batch multi-baris (bukan per baris)
 *   - lewati IF NOT EXISTS di rentang yang sudah dipastikan kosong
 *
 * JALANKAN DI LUAR JAM KERJA. Contoh:
 *   C:\xampp\php\php.exe C:\xampp\htdocs\hris\presensi\tarik_historis_zkteco.php 2013-01-01 2026-06-27
 *
 * Argumen: [tgl_mulai] [tgl_sampai]  (format YYYY-MM-DD)
 *   Kosong = 2013-01-01 sampai kemarin.
 * Setelah selesai, sync rutin 15-menit lanjut normal dari titik terbaru.
 */

set_time_limit(0);
ini_set('memory_limit', '1024M');

$mulai  = $argv[1] ?? '2013-01-01';
$sampai = $argv[2] ?? date('Y-m-d', strtotime('-1 day'));
$BATCH  = 1000;   // baris per insert statement

function tulis($s){ echo $s . PHP_EOL; }

require __DIR__ . '/../config/koneksi_sqlsrv.php';   // $conn
require __DIR__ . '/../config/koneksi_mdb.php';

$t0 = microtime(true);
tulis("=== TARIK HISTORIS ZKTeco ===");
tulis("Rentang: $mulai s/d $sampai");
tulis("Mulai " . date('d-m-Y H:i:s'));
tulis(str_repeat('-',50));

/* ---------- cek dulu berapa yang sudah ada di rentang ini ---------- */
$st = sqlsrv_query($conn,
    "SELECT COUNT(*) n FROM dbo.zkteco_checkinout WHERE checktime >= ? AND checktime < ?",
    [$mulai, date('Y-m-d', strtotime($sampai.' +1 day'))]);
$sudahAda = sqlsrv_fetch_array($st, SQLSRV_FETCH_ASSOC)['n'] ?? 0;
if ($sudahAda > 0) {
    tulis("PERHATIAN: sudah ada $sudahAda baris di rentang ini.");
    tulis("Script ini memakai IF NOT EXISTS supaya tidak dobel (sedikit lebih lambat).");
    $pakaiCek = true;
} else {
    tulis("Rentang kosong - pakai insert cepat tanpa cek duplikat.");
    $pakaiCek = false;
}

/* ---------- buka MDB ---------- */
try { $mdb = buka_mdb(); }
catch (Exception $e) { tulis("ERROR MDB: ".$e->getMessage()); exit(1); }

/* ---------- baca dari MDB per rentang ---------- */
$sqlMdb = "SELECT USERID, CHECKTIME, CHECKTYPE, VERIFYCODE, sn
           FROM CHECKINOUT
           WHERE CHECKTIME >= #".date('m/d/Y', strtotime($mulai))."#
             AND CHECKTIME <  #".date('m/d/Y', strtotime($sampai.' +1 day'))."#
           ORDER BY CHECKTIME";
tulis("Membaca MDB...");
$rows = $mdb->query($sqlMdb);
if (!$rows) { tulis("ERROR query MDB: ".print_r($mdb->errorInfo(),true)); exit(1); }

/* ---------- insert batch ---------- */
$total=0; $buffer=[];

function flushBatch($conn, &$buffer, $pakaiCek) {
    if (!$buffer) return 0;
    if ($pakaiCek) {
        // mode aman: per baris IF NOT EXISTS
        $sql="IF NOT EXISTS (SELECT 1 FROM dbo.zkteco_checkinout WHERE zk_userid=? AND checktime=?)
              INSERT INTO dbo.zkteco_checkinout (zk_userid,checktime,checktype,verifycode,sn) VALUES (?,?,?,?,?)";
        $n=0;
        foreach ($buffer as $r) {
            $st=sqlsrv_query($conn,$sql,[$r[0],$r[1],$r[0],$r[1],$r[2],$r[3],$r[4]]);
            if ($st!==false){ sqlsrv_free_stmt($st); $n++; }
        }
        $buffer=[]; return $n;
    }
    // mode cepat: satu INSERT banyak VALUES
    $vals=[]; $params=[];
    foreach ($buffer as $r){ $vals[]="(?,?,?,?,?)"; array_push($params,$r[0],$r[1],$r[2],$r[3],$r[4]); }
    $sql="INSERT INTO dbo.zkteco_checkinout (zk_userid,checktime,checktype,verifycode,sn) VALUES ".implode(',',$vals);
    $st=sqlsrv_query($conn,$sql,$params);
    $n = $st===false ? 0 : count($buffer);
    if ($st===false) tulis("  ! batch gagal: ".print_r(sqlsrv_errors(),true));
    else sqlsrv_free_stmt($st);
    $buffer=[]; return $n;
}

while ($r = $rows->fetch(PDO::FETCH_NUM)) {
    // r: [USERID, CHECKTIME, CHECKTYPE, VERIFYCODE, sn]
    $ct = $r[1];
    // normalkan format tanggal ke Y-m-d H:i:s
    $ts = date('Y-m-d H:i:s', strtotime($ct));
    $buffer[] = [(int)$r[0], $ts, $r[2] ?: 'I', (int)$r[3], trim((string)$r[4])];

    if (count($buffer) >= $BATCH) {
        $total += flushBatch($conn, $buffer, $pakaiCek);
        if ($total % 50000 === 0) tulis("  ... $total baris (".round((microtime(true)-$t0))."s)");
    }
}
$total += flushBatch($conn, $buffer, $pakaiCek);

/* ---------- perbarui posisi sync supaya rutin lanjut dari titik terbaru ---------- */
$st=sqlsrv_query($conn,"SELECT MAX(checktime) mx FROM dbo.zkteco_checkinout");
$mx=sqlsrv_fetch_array($st,SQLSRV_FETCH_ASSOC)['mx'] ?? null;
if ($mx instanceof DateTime) {
    sqlsrv_query($conn,"UPDATE dbo.sync_zkteco_state SET last_checktime=?, last_run=GETDATE(),
                        status='HISTORIS', jml_baru=? WHERE sumber='ATT2000'",
                 [$mx->format('Y-m-d H:i:s'), $total]);
}

$dur=round(microtime(true)-$t0);
tulis(str_repeat('-',50));
tulis("SELESAI. $total baris masuk dalam {$dur} detik (".round($dur/60,1)." menit).");
tulis("Posisi sync diperbarui. Sekarang jalankan proses:");
tulis("  C:\\xampp\\php\\php.exe C:\\xampp\\htdocs\\hris\\presensi\\sync_zkteco_proses.php");
