<?php
include '../connection.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Ambil data dari form
    $id_kedisiplinan = $_POST['id_kedisiplinan'];
    $id_peg = $_POST['id_peg'];
    $jenis_pelanggaran = $_POST['jenis_pelanggaran'];
    $sanksi = $_POST['sanksi'];

    // Query untuk update data
    $query = "UPDATE `kedisiplinan` SET 
              `id_peg` = ?, 
              `jenis_pelanggaran` = ?, 
              `sanksi` = ? 
              WHERE `id_kedisiplinan` = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("issi", $id_peg, $jenis_pelanggaran, $sanksi, $id_kedisiplinan);

    // Eksekusi query
    if ($stmt->execute()) {
        echo "<script>alert('Data kedisiplinan berhasil diperbarui!'); window.location.href='index.php';</script>";
    } else {
        echo "<script>alert('Gagal memperbarui data kedisiplinan!'); window.location.href='index.php';</script>";
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