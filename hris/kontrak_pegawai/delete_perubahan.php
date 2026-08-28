<?php
include '../connection.php';

// Pastikan ID tersedia dan valid
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    echo "<script>alert('ID tidak ditemukan atau tidak valid.'); window.history.back();</script>";
    exit;
}

$id = intval($_GET['id']); // Sanitasi ID

// Periksa apakah data ada sebelum dihapus
$checkStmt = $conn->prepare("SELECT id FROM `riwayat_perubahan_kontrak` WHERE `id` = ?");
$checkStmt->bind_param("i", $id);
$checkStmt->execute();
$checkResult = $checkStmt->get_result();

if ($checkResult->num_rows === 0) {
    echo "<script>alert('Data tidak ditemukan.'); window.history.back();</script>";
    exit;
}

$checkStmt->close();

// Lakukan penghapusan data
$stmt = $conn->prepare("DELETE FROM `riwayat_perubahan_kontrak` WHERE `id` = ?");
$stmt->bind_param("i", $id);

if ($stmt->execute()) {
    echo "<script>alert('Data berhasil dihapus!'); window.location.href='index.php';</script>";
} else {
    echo "<script>alert('Gagal menghapus data: " . htmlspecialchars($stmt->error) . "'); window.history.back();</script>";
}

$stmt->close();
$conn->close();
?>
