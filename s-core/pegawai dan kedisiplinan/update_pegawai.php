<?php
include '../connection.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Ambil data dari form
    $id_peg = $_POST['id_peg'];
    $nama_pegawai = $_POST['nama_pegawai'];
    $jabatan = $_POST['jabatan'];
    $departemen = $_POST['departemen'];

    // Query untuk update data
    $query = "UPDATE `pegawai` SET 
              `nama_pegawai` = ?, 
              `jabatan` = ?, 
              `departemen` = ? 
              WHERE `id_peg` = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("sssi", $nama_pegawai, $jabatan, $departemen, $id_peg);

    // Eksekusi query
    if ($stmt->execute()) {
        echo "<script>alert('Data pegawai berhasil diperbarui!'); window.location.href='index.php';</script>";
    } else {
        echo "<script>alert('Gagal memperbarui data pegawai!'); window.location.href='index.php';</script>";
    }

    // Tutup statement dan koneksi
    $stmt->close();
    $conn->close();
} else {
    // Jika bukan metode POST, redirect ke index.php
    header("Location: index.php");
    exit();
}
?>