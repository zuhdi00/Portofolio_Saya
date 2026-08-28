<?php
require 'c:/xampp/htdocs/hris/config/koneksi_sqlsrv.php';

echo "=== MENGEMBALIKAN CHECK CONSTRAINTS PADA TABEL PEGAWAI ===\n";

$queries = [
    "ALTER TABLE dbo.pegawai ADD CONSTRAINT CK_pegawai_gender CHECK (gender IS NULL OR gender IN ('L', 'P'))",
    "ALTER TABLE dbo.pegawai ADD CONSTRAINT CK_pegawai_agama CHECK (agama IS NULL OR agama IN ('Islam', 'Kristen', 'Katolik', 'Hindu', 'Buddha', 'Konghucu'))",
    "ALTER TABLE dbo.pegawai ADD CONSTRAINT CK_pegawai_status_nikah CHECK (status_nikah IS NULL OR status_nikah IN ('TK', 'K0', 'K1', 'K2', 'K3'))",
    "ALTER TABLE dbo.pegawai ADD CONSTRAINT CK_pegawai_status_karyawan CHECK (status_karyawan IS NULL OR status_karyawan IN ('harian', 'kontrak', 'tetap'))"
];

$success = 0;
$errors = 0;

foreach ($queries as $sql) {
    $stmt = sqlsrv_query($conn, $sql);
    if ($stmt === false) {
        $err = sqlsrv_errors()[0]['message'] ?? 'Unknown error';
        echo "GAGAL: $sql\nAlasan: $err\n\n";
        $errors++;
    } else {
        echo "BERHASIL: Constraint ditambahkan.\n";
        $success++;
    }
}

echo "\nSelesai: $success Berhasil, $errors Gagal.\n";
