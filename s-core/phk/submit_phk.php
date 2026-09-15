<?php
require_once __DIR__ . '/../auth/auth.php';
wajib_izin('modul_hr');
include '../connection.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $id_karyawan = $_POST['id_karyawan'];
    $tanggal_phk = $_POST['tanggal_phk'];
    $alasan_phk = $_POST['alasan_phk'];
    $status_kompensasi = $_POST['status_kompensasi'];
    $jumlah_kompensasi = $_POST['jumlah_kompensasi'];

    // Query untuk insert data ke tabel phk
    $query = "INSERT INTO phk (id_karyawan, tanggal_phk, alasan_phk, status_kompensasi, jumlah_kompensasi) 
              VALUES (?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("issss", $id_karyawan, $tanggal_phk, $alasan_phk, $status_kompensasi, $jumlah_kompensasi);

    if ($stmt->execute()) {
        header("Location: index.php?status=success");
        exit();
    } else {
        echo "Error: " . $stmt->error;
    }
}
?>
