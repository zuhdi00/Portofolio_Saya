<?php
include '../config/koneksi_sqlsrv.php';
require_once __DIR__ . '/_catat_histori.php';

// Ambil ID pegawai dari URL
$id_peg = (int)($_GET['id'] ?? 0);
if (!$id_peg) {
    die("ID tidak valid.");
}

// Hapus data pegawai (Nonaktifkan via is_aktif = 0)
$query = "UPDATE dbo.pegawai SET is_aktif = 0 WHERE id_peg = ?";
$stmt = sqlsrv_query($conn, $query, [$id_peg]);

if ($stmt) {
    // Catat histori hapus
    [$namaUser, $idUser] = _userSekarang();
    catatHistoriHapus($conn, $id_peg, $namaUser, $idUser);
    
    echo "<script>alert('Data pegawai berhasil dinonaktifkan.'); window.location.href='index.php';</script>";
} else {
    echo "<script>alert('Gagal menonaktifkan data.'); window.location.href='index.php';</script>";
}
?>