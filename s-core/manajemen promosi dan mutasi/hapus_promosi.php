<?php
include '../connection.php';

// Ambil ID mutasi dari URL
$id_promosi = $_GET['id'];

// Query untuk menghapus data mutasi
$query = mysqli_query($conn, "DELETE FROM promosi WHERE id_promosi = '$id_promosi'");

if($query) {
    // Jika berhasil dihapus
    echo "<script>
        alert('Data promosi berhasil dihapus!');
        window.location.href = 'index.php';
    </script>";
} else {
    // Jika gagal menghapus
    echo "<script>
        alert('Gagal menghapus data promosi!');
        window.location.href = 'index.php';
    </script>";
}
?> 