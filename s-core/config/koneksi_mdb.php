<?php
/**
 * config/koneksi_mdb.php   [v3 - dukung baca langsung dari share jaringan]
 * Satu-satunya tempat mengatur koneksi ke file MDB ZKTeco.
 *
 * Pemakaian:
 *   require __DIR__ . '/../config/koneksi_mdb.php';
 *   $mdb = buka_mdb();
 */

// ================== KONFIGURASI ==================
// Mode koneksi:
//   'unc'  -> baca LANGSUNG dari share jaringan (tanpa salin). TERBARU.
//   'file' -> baca file lokal hasil salin
//   'dsn'  -> pakai System DSN dari odbcad32.exe
$MDB_MODE = 'unc';

// Path langsung ke MDB di server (mode 'unc')
$MDB_UNC  = '\\\\spsdmz\\gg$\\HRD\\CheckClock\\ATT2000.mdb';

$MDB_DSN  = 'ZKTECO_MDB';                    // mode 'dsn'
$MDB_PATH = 'C:\\zkteco_data\\ATT2000.mdb';  // mode 'file'
// =================================================


/**
 * Buka koneksi ke MDB.
 * @throws Exception kalau gagal.
 */
function buka_mdb()
{
    global $MDB_MODE, $MDB_DSN, $MDB_PATH, $MDB_UNC;

    if ($MDB_MODE === 'dsn') {
        $pdo = new PDO("odbc:" . $MDB_DSN);
    }
    elseif ($MDB_MODE === 'unc') {
        // Baca langsung dari jaringan. Mode=Read untuk hindari file-lock
        // saat ATT2000 sedang menulis. ReadOnly juga lebih aman.
        if (!@file_exists($MDB_UNC)) {
            throw new Exception(
                "MDB jaringan tidak terjangkau: $MDB_UNC\n" .
                "Kemungkinan: (1) akun yang menjalankan skrip ini tidak punya akses ke \\\\spsdmz, " .
                "atau (2) share sedang tidak tersedia. " .
                "Cek: apakah Apache/Task Scheduler pakai akun yang bisa buka \\\\spsdmz ?"
            );
        }
        $dsn = "odbc:Driver={Microsoft Access Driver (*.mdb, *.accdb)};"
             . "Dbq={$MDB_UNC};Mode=Read;ReadOnly=1;";
        $pdo = new PDO($dsn);
    }
    else { // 'file'
        if (!file_exists($MDB_PATH)) {
            throw new Exception("File MDB tidak ditemukan: $MDB_PATH");
        }
        $pdo = new PDO("odbc:Driver={Microsoft Access Driver (*.mdb, *.accdb)};Dbq={$MDB_PATH};Mode=Read;ReadOnly=1;");
    }

    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    return $pdo;
}
