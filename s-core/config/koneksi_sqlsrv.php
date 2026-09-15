<?php
/**
 * config/koneksi_sqlsrv.php
 * Koneksi ke SQL Server - database dbHR
 * Menyediakan variabel $conn (resource sqlsrv) untuk dipakai file lain.
 *
 * !! GANTI "GANTI_NAMA_SERVER" dengan nama/IP server SQL yang benar
 *    (contoh sebelumnya di project ini: spsdmz atau spsdmz2)
 */

$serverName = "spsdmz2";
$connectionOptions = array(
    "Database" => "dbHR",
    "Uid" => "sa",
    "PWD" => "supracor",
    "LoginTimeout" => 15,
    "Encrypt" => false,
    "TrustServerCertificate" => true
);

$conn = sqlsrv_connect($serverName, $connectionOptions);

if (!$conn) {
    http_response_code(500);
    die("Koneksi ke dbHR gagal: " . print_r(sqlsrv_errors(), true));
}
