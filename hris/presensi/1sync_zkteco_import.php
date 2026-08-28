<?php
/**
 * presensi/sync_zkteco_import.php
 * TAHAP 1 - Impor tap mentah dari ATT2000.MDB -> dbo.zkteco_checkinout
 *
 * Jalankan dari Task Scheduler Windows, contoh tiap 15 menit:
 *   C:\xampp\php\php.exe C:\xampp\htdocs\hris\presensi\sync_zkteco_import.php
 *
 * SYARAT:
 *  - Microsoft Access Database Engine terpasang (bitness SAMA dgn PHP: 32/64-bit)
 *  - extension=pdo_odbc aktif di php.ini
 *  - File MDB bisa dibaca (share folder / lokal)
 *
 * Sifatnya IDEMPOTEN: aman dijalankan berulang, tap yang sudah ada dilewati.
 */

// ================== KONFIGURASI ==================
$SUMBER     = 'ATT2000';
$BATCH      = 5000;   // jumlah baris per commit
$MUNDUR_HARI = 3;     // impor ulang N hari ke belakang (jaga2 tap telat sinkron dari mesin)
// =================================================

$isCli = (php_sapi_name() === 'cli');
function tulis($s) { global $isCli; echo $s . ($isCli ? PHP_EOL : "<br>\n"); }

require __DIR__ . '/../config/koneksi_sqlsrv.php';   // $conn

$t0 = microtime(true);
tulis("=== Impor ZKTeco dimulai " . date('d-m-Y H:i:s') . " ===");

// ---------- 1. Ambil posisi terakhir ----------
$st = sqlsrv_query($conn,
    "SELECT TOP 1 id, last_checktime FROM dbo.sync_zkteco_state WHERE sumber = ?", [$SUMBER]);
$state = $st ? sqlsrv_fetch_array($st, SQLSRV_FETCH_ASSOC) : null;
if (!$state) { tulis("ERROR: baris sync_zkteco_state untuk '$SUMBER' belum ada."); exit(1); }

$stateId = (int) $state['id'];
$sejak   = $state['last_checktime'] instanceof DateTime
         ? $state['last_checktime'] : new DateTime('2000-01-01');
// mundurkan beberapa hari supaya tap yang telat masuk tetap terjaring
$sejak->modify("-{$MUNDUR_HARI} days");
$sejakStr = $sejak->format('Y-m-d H:i:s');
tulis("Ambil tap sejak: $sejakStr");

// ---------- 2. Buka MDB ----------
require __DIR__ . '/../config/koneksi_mdb.php';   // $mdb

// Access pakai #tanggal# sebagai literal tanggal
$sqlMdb = "SELECT USERID, CHECKTIME, CHECKTYPE, VERIFYCODE, sn
           FROM CHECKINOUT
           WHERE CHECKTIME >= #" . $sejak->format('m/d/Y H:i:s') . "#
           ORDER BY CHECKTIME";
$rows = $mdb->query($sqlMdb);

// ---------- 3. Masukkan ke staging ----------
$sqlIns = "IF NOT EXISTS (SELECT 1 FROM dbo.zkteco_checkinout WHERE zk_userid = ? AND checktime = ?)
           INSERT INTO dbo.zkteco_checkinout (zk_userid, checktime, checktype, verifycode, sn)
           VALUES (?,?,?,?,?)";

$baru = 0; $total = 0; $maxTime = null; $gagal = 0;
sqlsrv_begin_transaction($conn);

try {
    foreach ($rows as $r) {
        $total++;
        $uid = (int) $r['USERID'];
        $ct  = $r['CHECKTIME'];
        // normalisasi ke format SQL Server
        $dt  = date_create($ct);
        if (!$dt) { $gagal++; continue; }
        $ctS = $dt->format('Y-m-d H:i:s');

        $p = [$uid, $ctS, $uid, $ctS,
              $r['CHECKTYPE'] ?: null,
              isset($r['VERIFYCODE']) ? (int)$r['VERIFYCODE'] : null,
              $r['sn'] ?: null];

        $ins = sqlsrv_query($conn, $sqlIns, $p);
        if ($ins === false) { throw new Exception(print_r(sqlsrv_errors(), true)); }
        $baru += sqlsrv_rows_affected($ins);
        sqlsrv_free_stmt($ins);

        if ($maxTime === null || $ctS > $maxTime) $maxTime = $ctS;

        if ($total % $BATCH === 0) {
            sqlsrv_commit($conn);
            sqlsrv_begin_transaction($conn);
            tulis("  ... diproses $total baris (baru: $baru)");
        }
    }
    sqlsrv_commit($conn);
} catch (Exception $e) {
    sqlsrv_rollback($conn);
    sqlsrv_query($conn,
        "UPDATE dbo.sync_zkteco_state SET last_run=GETDATE(), status='GAGAL', pesan=? WHERE id=?",
        [substr($e->getMessage(), 0, 900), $stateId]);
    tulis("GAGAL: " . $e->getMessage());
    exit(1);
}

// ---------- 4. Simpan posisi terakhir ----------
if ($maxTime) {
    sqlsrv_query($conn,
        "UPDATE dbo.sync_zkteco_state
         SET last_checktime=?, last_run=GETDATE(), jml_baru=?, status='SUKSES', pesan=NULL
         WHERE id=?", [$maxTime, $baru, $stateId]);
} else {
    sqlsrv_query($conn,
        "UPDATE dbo.sync_zkteco_state SET last_run=GETDATE(), jml_baru=0, status='SUKSES' WHERE id=?",
        [$stateId]);
}

$dur = round(microtime(true) - $t0, 1);
tulis("Dibaca dari MDB : $total baris");
tulis("Tap baru masuk  : $baru");
if ($gagal) tulis("Tanggal tidak terbaca: $gagal");
tulis("Tap terakhir    : " . ($maxTime ?: '-'));
tulis("Selesai dalam {$dur} detik.");
tulis("Lanjutkan dengan: sync_zkteco_proses.php");
