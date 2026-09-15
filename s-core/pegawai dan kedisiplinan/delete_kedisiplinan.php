<?php
include '../connection.php';

if (isset($_GET['id'])) {
    $id_kedisiplinan = $_GET['id'];

    // Query untuk menghapus data kedisiplinan
    $query = "DELETE FROM `kedisiplinan` WHERE `id_kedisiplinan` = ?";
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        die("Error prepare statement: " . $conn->error);
    }
    $stmt->bind_param("i", $id_kedisiplinan);

    // Eksekusi query
    if ($stmt->execute()) {
        echo "<script>alert('Data kedisiplinan berhasil dihapus!'); window.location.href='index.php';</script>";
    } else {
        echo "<script>alert('Gagal menghapus data kedisiplinan!'); window.location.href='index.php';</script>";
    }

    // Tutup statement dan koneksi
    $stmt->close();
    $conn->close();
} else {
    // Jika tidak ada ID, redirect ke index.php
    header("Location: index.php");
    exit();
}
?>