<?php
/**
 * presensi/cek_koneksi_mdb.php   [v3]
 * Uji apakah PHP bisa membaca file MDB ZKTeco. Tidak menulis apapun.
 * Konfigurasi diambil dari config/koneksi_mdb.php
 *
 * Jalankan lewat browser, atau lewat CLI:
 *   C:\xampp\php\php.exe C:\xampp\htdocs\hris\presensi\cek_koneksi_mdb.php
 */

$isCli = (php_sapi_name() === 'cli');
function t($s = '') { global $isCli; echo $s . ($isCli ? PHP_EOL : "<br>\n"); }

t("=== Uji Koneksi MDB ===");
t("Dijalankan lewat: " . ($isCli ? "CLI (command prompt)" : "Browser / Apache"));
t();

/* ---------- 1. Arsitektur PHP ---------- */
t("1. PHP " . PHP_VERSION . " | " . (PHP_INT_SIZE === 8 ? "64-bit" : "32-bit"));
t("   Access Database Engine harus bitness yang sama.");
t();

/* ---------- 2. Extension ---------- */
t("2. Extension:");
t("   pdo_odbc : " . (extension_loaded('pdo_odbc') ? "AKTIF" : "TIDAK AKTIF  <-- aktifkan di php.ini"));
t("   PDO drivers: " . implode(', ', class_exists('PDO') ? PDO::getAvailableDrivers() : []));
t();

/* ---------- 3. Konfigurasi ---------- */
t("3. Konfigurasi (config/koneksi_mdb.php):");
$cfg = __DIR__ . '/../config/koneksi_mdb.php';
if (!file_exists($cfg)) { t("   FILE TIDAK ADA: $cfg"); exit(1); }

require $cfg;   // menyediakan $MDB_MODE, $MDB_DSN, $MDB_PATH, fungsi buka_mdb()

if (!function_exists('buka_mdb')) {
    t("   File config masih versi lama (fungsi buka_mdb() tidak ada).");
    t("   Ganti dengan config/koneksi_mdb.php versi terbaru.");
    exit(1);
}

t("   Mode  : $MDB_MODE");
if ($MDB_MODE === 'dsn') {
    t("   DSN   : $MDB_DSN");
    t("   >> DSN harus dibuat di C:\\Windows\\System32\\odbcad32.exe");
    t("      pada tab SYSTEM DSN (bukan User DSN - tidak terbaca service Apache)");
} else {
    t("   Path  : $MDB_PATH");
    if (!file_exists($MDB_PATH)) { t("   Status: TIDAK DITEMUKAN"); exit(1); }
    $mb = filesize($MDB_PATH) / 1048576;
    t("   Ukuran: " . number_format($mb, 1) . " MB");
    t("   Diubah: " . date('d-m-Y H:i:s', filemtime($MDB_PATH)));
    if ($mb > 1500) t("   !! PERINGATAN: mendekati batas 2 GB Access. Segera Compact & Repair.");
}
t();

/* ---------- 4. Koneksi ---------- */
t("4. Mencoba koneksi...");
try {
    $mdb = buka_mdb();
    t("   BERHASIL");
} catch (Exception $e) {
    t("   GAGAL: " . $e->getMessage());
    t();
    t("   Penyebab tersering:");
    if ($MDB_MODE === 'dsn') {
        t("   - System DSN '$MDB_DSN' belum dibuat, atau terbuat di tab User DSN");
        t("   - DSN dibuat lewat odbcad32.exe 32-bit (SysWOW64); harus yang System32");
    } else {
        t("   - 'Unable to open registry key ... Ace DSN' = akun service Apache tidak");
        t("     boleh menulis registry. Solusinya: ubah \$MDB_MODE menjadi 'dsn'.");
    }
    t("   - Access Database Engine belum terpasang / beda bitness dengan PHP");
    exit(1);
}
t();

/* ---------- 5. Uji baca ---------- */
t("5. Uji baca tabel:");
try {
    foreach (['USERINFO', 'CHECKINOUT', 'DEPARTMENTS'] as $tb) {
        $n = $mdb->query("SELECT COUNT(*) AS n FROM $tb")->fetch(PDO::FETCH_ASSOC)['n'];
        t("   $tb : " . number_format($n) . " baris");
    }
    t();

    t("   5 tap terakhir:");
    $r = $mdb->query("SELECT TOP 5 USERID, CHECKTIME, CHECKTYPE, VERIFYCODE
                      FROM CHECKINOUT ORDER BY CHECKTIME DESC");
    foreach ($r as $x) {
        $vc = (int) $x['VERIFYCODE'];
        $m  = $vc == 15 ? 'wajah' : ($vc == 1 ? 'sidik jari' : "kode $vc");
        t("     USERID {$x['USERID']} | {$x['CHECKTIME']} | {$x['CHECKTYPE']} | $m");
    }
} catch (Exception $e) {
    t("   GAGAL baca: " . $e->getMessage());
    exit(1);
}

t();
t("=== SEMUA UJI LULUS ===");
t("Lanjut ke: seed_pegawai_dari_zkteco.php (mode uji coba)");
