<?php
include '../connection.php'; // Pastikan file koneksi database sudah benar

// Mengecek apakah parameter id_laporan tersedia
if (isset($_GET['id_laporan'])) {
    $id_laporan = $_GET['id_laporan']; // Ambil id_laporan dari URL

    // Query untuk menghapus laporan berdasarkan id_laporan
    $query = "DELETE FROM laporan_sdm WHERE id_laporan = ?";
    if ($stmt = $conn->prepare($query)) {
        // Mengikat parameter
        $stmt->bind_param("i", $id_laporan);

        // Mengeksekusi query
        if ($stmt->execute()) {
            // Redirect ke halaman index dengan pesan sukses
            header("Location: index.php?status=deleted");
            exit;
        } else {
            // Jika terjadi kesalahan saat eksekusi query
            echo "Error: Gagal menghapus laporan. " . $stmt->error;
        }

        // Menutup statement
        $stmt->close();
    } else {
        // Jika statement gagal dipersiapkan
        echo "Error: " . $conn->error;
    }
} else {
    // Jika parameter id_laporan tidak ada
    echo "Error: ID laporan tidak ditemukan.";
}

// Menutup koneksi database
$conn->close();
?>
