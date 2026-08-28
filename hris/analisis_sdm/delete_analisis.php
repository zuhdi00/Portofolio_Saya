<?php
include '../connection.php'; // Menghubungkan ke database

if (isset($_GET['id'])) {
    $id_analisis = $_GET['id'];
    
    // Query untuk menghapus data berdasarkan ID
    $delete_sql = "DELETE FROM analisis_sdm WHERE id_analisis = '$id_analisis'";

    if ($conn->query($delete_sql) === TRUE) {
        header('Location: index.php'); // Redirect setelah data berhasil dihapus
        exit;
    } else {
        echo "Error: " . $conn->error;
    }
} else {
    echo "ID tidak ditemukan!";
}
?>
