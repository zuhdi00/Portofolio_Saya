<?php
require 'c:/xampp/htdocs/hris/config/koneksi_sqlsrv.php';

$sql = "SELECT COUNT(*) as total FROM pegawai";
$stmt = sqlsrv_query($conn, $sql);
$r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
echo "Total rows: " . $r['total'] . "\n";

$sql = "SELECT COUNT(*) as total FROM pegawai WHERE id_peg >= 3000";
$stmt = sqlsrv_query($conn, $sql);
$r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
echo "New rows remaining (id >= 3000): " . $r['total'] . "\n";
