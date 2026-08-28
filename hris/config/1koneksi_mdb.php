<?php
/**
 * config/koneksi_mdb.php
 * Satu-satunya tempat mengatur koneksi ke file MDB ZKTeco.
 * Semua script (cek_koneksi, seed, sync_import) memanggil file ini.
 *
 * Menyediakan: $mdb (objek PDO)
 */

// ================== KONFIGURASI ==================
// Mode 'dsn'  -> pakai System DSN yang dibuat lewat odbcad32.exe  (DISARANKAN)
// Mode 'file' -> pakai path file langsung (rawan error registry ACE)
$MDB_MODE = 'dsn';

$MDB_DSN  = 'ZKTECO_MDB';                    // nama System DSN
$MDB_PATH = 'C:\\zkteco_data\\ATT2000.mdb';  // dipakai kalau mode 'file'
// =================================================

try {
    if ($MDB_MODE === 'dsn') {
        $mdb = new PDO("odbc:" . $MDB_DSN);
    } else {
        if (!file_exists($MDB_PATH)) {
            throw new Exception("File MDB tidak ditemukan: $MDB_PATH");
        }
        $mdb = new PDO("odbc:Driver={Microsoft Access Driver (*.mdb, *.accdb)};Dbq={$MDB_PATH};");
    }
    $mdb->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

} catch (Exception $e) {
    $pesan = "Koneksi MDB gagal: " . $e->getMessage();
    if (php_sapi_name() === 'cli') { fwrite(STDERR, $pesan . PHP_EOL); }
    else { echo "<pre style='color:#900'>$pesan</pre>"; }
    exit(1);
}
