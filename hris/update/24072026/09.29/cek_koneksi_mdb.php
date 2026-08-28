<?php
/**
 * presensi/cek_koneksi_mdb.php
 * Uji apakah PHP bisa membaca file MDB ZKTeco. Tidak menulis apapun.
 * Jalankan:  C:\xampp\php\php.exe C:\xampp\htdocs\hris\presensi\cek_koneksi_mdb.php
 */

$MDB_PATH = 'C:\\zkteco_data\\ATT2000.mdb';   // <<< SESUAIKAN

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

// 4. File
t("4. File MDB:");
t("   Path  : $MDB_PATH");
if (!file_exists($MDB_PATH)) {
    t("   Status: TIDAK DITEMUKAN  <-- perbaiki path dulu");
    exit(1);
}
t("   Status: ada");
t("   Ukuran: " . number_format(filesize($MDB_PATH)/1048576, 1) . " MB");
t("   Diubah: " . date('d-m-Y H:i:s', filemtime($MDB_PATH)));
t("   Bisa dibaca: " . (is_readable($MDB_PATH) ? "ya" : "TIDAK  <-- cek hak akses folder"));
t();

// 5. Koneksi
t("5. Mencoba koneksi...");
try {
    $mdb = new PDO("odbc:Driver={Microsoft Access Driver (*.mdb, *.accdb)};Dbq={$MDB_PATH};");
    $mdb->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    t("   BERHASIL");
} catch (Exception $e) {
    t("   GAGAL: " . $e->getMessage());
    t();
    t("   Penyebab tersering:");
    t("   - Microsoft Access Database Engine belum terpasang");
    t("     unduh 'Access Database Engine 2016 Redistributable' dari situs Microsoft");
    t("   - Bitness tidak cocok (PHP " . (PHP_INT_SIZE===8?"64":"32") . "-bit butuh engine "
      . (PHP_INT_SIZE===8?"64":"32") . "-bit)");
    t("   - extension=pdo_odbc belum diaktifkan di php.ini");
    exit(1);
}
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
