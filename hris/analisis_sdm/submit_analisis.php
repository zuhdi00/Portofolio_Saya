<?php
include '../connection.php'; // Menghubungkan ke database

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $judul_analisis = $_POST['judul_analisis'];
    $deskripsi = $_POST['deskripsi'];
    $tanggal_analisis = $_POST['tanggal_analisis'];
    $jenis_analisis = $_POST['jenis_analisis'];

    // Query untuk menyimpan data analisis
    $sql = "INSERT INTO analisis_sdm (judul_analisis, deskripsi, tanggal_analisis, jenis_analisis)
            VALUES ('$judul_analisis', '$deskripsi', '$tanggal_analisis', '$jenis_analisis')";

    if ($conn->query($sql) === TRUE) {
        header("Location: index.php"); // Redirect ke halaman daftar analisis setelah berhasil
        exit();
    } else {
        echo "Error: " . $sql . "<br>" . $conn->error;
    }
}
?>
