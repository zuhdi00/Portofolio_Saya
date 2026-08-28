<?php
require 'c:/xampp/htdocs/hris/config/koneksi_sqlsrv.php';

$sql = "SELECT id_peg, nik, nama_peg, no_ktp FROM pegawai WHERE no_ktp = '3516052105690001'";
$stmt = sqlsrv_query($conn, $sql);
while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    echo "ID: {$row['id_peg']} | NIK: {$row['nik']} | Nama: {$row['nama_peg']} | KTP: {$row['no_ktp']}\n";
}
