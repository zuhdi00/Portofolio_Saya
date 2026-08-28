<?php
/**
 * config/koneksi_mdb.php   [v2]
 * Satu-satunya tempat mengatur koneksi ke file MDB ZKTeco.
 *
 * Menyediakan:
 *   - variabel $MDB_MODE, $MDB_DSN, $MDB_PATH
 *   - fungsi   buka_mdb()  -> mengembalikan objek PDO
 *
 * Pemakaian di file lain:
 *   require __DIR__ . '/../config/koneksi_mdb.php';
 *   $mdb = buka_mdb();
 */

// ================== KONFIGURASI ==================
// 'dsn'  -> pakai System DSN dari odbcad32.exe   (DISARANKAN)
// 'file' -> pakai path file langsung             (rawan error registry ACE)
$MDB_MODE = 'dsn';

$MDB_DSN  = 'ZKTECO_MDB';                    // nama System DSN
$MDB_PATH = 'C:\\zkteco_data\\ATT2000.mdb';  // dipakai kalau mode 'file'
// =================================================


/**
 * Buka koneksi ke MDB.
 * @throws Exception kalau gagal (biar pemanggil yang menentukan penanganannya)
 */
function buka_mdb()
{
    global $MDB_MODE, $MDB_DSN, $MDB_PATH;

    if ($MDB_MODE === 'dsn') {
        $pdo = new PDO("odbc:" . $MDB_DSN);
    } else {
        if (!file_exists($MDB_PATH)) {
            throw new Exception("File MDB tidak ditemukan: $MDB_PATH");
        }
        $pdo = new PDO("odbc:Driver={Microsoft Access Driver (*.mdb, *.accdb)};Dbq={$MDB_PATH};");
    }

    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    return $pdo;
}
