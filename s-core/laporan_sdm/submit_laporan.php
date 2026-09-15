<?php
include '../connection.php'; // Pastikan koneksi database Anda sudah benar

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Mengambil data dari form
    $judul_laporan = $_POST['judul_laporan'];
    $periode_awal = $_POST['periode_awal'];
    $periode_akhir = $_POST['periode_akhir'];
    $isi_laporan = $_POST['isi_laporan'];
    $tanggal_dibuat = $_POST['tanggal_dibuat'];

    // Query untuk menyimpan data laporan ke dalam tabel laporan_sdm
    $query = "INSERT INTO laporan_sdm (judul_laporan, periode_awal, periode_akhir, isi_laporan, tanggal_dibuat) 
              VALUES (?, ?, ?, ?, ?)";

    // Menyiapkan statement
    if ($stmt = $conn->prepare($query)) {
        // Mengikat parameter
        $stmt->bind_param("sssss", $judul_laporan, $periode_awal, $periode_akhir, $isi_laporan, $tanggal_dibuat);

        // Mengeksekusi query
        if ($stmt->execute()) {
            // Redirect ke halaman dengan status success
            header("Location: index.php?status=success");
        } else {
            // Jika terjadi kesalahan saat menyimpan data
            echo "Error: " . $stmt->error;
        }

        // Menutup statement
        $stmt->close();
    } else {
        // Jika terjadi kesalahan saat menyiapkan query
        echo "Error: " . $conn->error;
    }
}

// Menutup koneksi database
$conn->close();
?>
