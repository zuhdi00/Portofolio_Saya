<?php
/**
 * presensi/cek_koneksi_mdb.php
 * Uji apakah PHP bisa membaca file MDB ZKTeco. Tidak menulis apapun.
 * Jalankan:  C:\xampp\php\php.exe C:\xampp\htdocs\hris\presensi\cek_koneksi_mdb.php
 */


$isCli = (php_sapi_name() === 'cli');
function t($s='') { global $isCli; echo $s . ($isCli ? PHP_EOL : "<br>\n"); }

t("=== Uji Koneksi MDB ===");
t();

// 1. Arsitektur PHP
t("1. PHP " . PHP_VERSION . " | " . (PHP_INT_SIZE === 8 ? "64-bit" : "32-bit"));
t("   >> Access Database Engine yang terpasang HARUS bitness yang sama.");
t();

// 2. Extension
t("2. Extension:");
t("   pdo_odbc : " . (extension_loaded('pdo_odbc') ? "AKTIF" : "TIDAK AKTIF  <-- aktifkan di php.ini"));
t("   odbc     : " . (extension_loaded('odbc')     ? "AKTIF" : "tidak aktif (opsional)"));
t();

// 3. Driver tersedia
t("3. Driver ODBC terdeteksi:");
$drv = class_exists('PDO') ? PDO::getAvailableDrivers() : [];
t("   PDO drivers: " . implode(', ', $drv));
t();

// 4. Konfigurasi & file
t("4. Konfigurasi MDB (dari config/koneksi_mdb.php):");
$cfgPath = __DIR__ . '/../config/koneksi_mdb.php';
if (!file_exists($cfgPath)) { t("   config/koneksi_mdb.php TIDAK ADA"); exit(1); }
$src = file_get_contents($cfgPath);
preg_match("/\\$MDB_MODE\\s*=\\s*'([^']+)'/", $src, $m1);
preg_match("/\\$MDB_DSN\\s*=\\s*'([^']+)'/", $src, $m2);
preg_match("/\\$MDB_PATH\\s*=\\s*'([^']+)'/", $src, $m3);
$mode = $m1[1] ?? '?'; $dsnName = $m2[1] ?? '?'; $path = str_replace('\\\\','\\', $m3[1] ?? '');
t("   Mode  : $mode");
if ($mode === 'dsn') {
    t("   DSN   : $dsnName");
    t("   >> pastikan System DSN ini sudah dibuat di odbcad32.exe (tab System DSN)");
} else {
    t("   Path  : $path");
    if (file_exists($path)) {
        t("   Ukuran: " . number_format(filesize($path)/1048576, 1) . " MB");
        t("   Diubah: " . date('d-m-Y H:i:s', filemtime($path)));
        $mb = filesize($path)/1048576;
        if ($mb > 1500) t("   PERINGATAN: mendekati batas 2 GB Access! Lakukan Compact & Repair.");
    } else { t("   Status: TIDAK DITEMUKAN"); exit(1); }
}
t();

// 5. Koneksi
t("5. Mencoba koneksi...");
require __DIR__ . '/../config/koneksi_mdb.php';   // $mdb
t("   BERHASIL");
t();

// 6. Baca data
t("6. Uji baca tabel:");
try {
    foreach (['USERINFO','CHECKINOUT','DEPARTMENTS'] as $tb) {
        $n = $mdb->query("SELECT COUNT(*) AS n FROM $tb")->fetch(PDO::FETCH_ASSOC)['n'];
        t("   $tb : " . number_format($n) . " baris");
    }
    t();

    $r = $mdb->query("SELECT TOP 5 USERID, CHECKTIME, CHECKTYPE, VERIFYCODE
                      FROM CHECKINOUT ORDER BY CHECKTIME DESC");
    t("   5 tap terakhir:");
    foreach ($r as $x) {
        $vc = (int)$x['VERIFYCODE'];
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
