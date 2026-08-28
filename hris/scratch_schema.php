<?php
require 'c:/xampp/htdocs/hris/config/koneksi_sqlsrv.php';
$sql = "SELECT COLUMN_NAME, DATA_TYPE FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'pegawai'";
$stmt = sqlsrv_query($conn, $sql);
$out = "";
if ($stmt === false) {
    $out = print_r(sqlsrv_errors(), true);
} else {
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $out .= $row['COLUMN_NAME'] . " - " . $row['DATA_TYPE'] . "\n";
    }
}
file_put_contents('c:/xampp/htdocs/hris/scratch_schema.txt', $out);
