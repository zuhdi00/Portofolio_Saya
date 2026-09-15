<?php
include '../connection.php';

// Ambil ID mutasi dari URL
$id_mutasi = $_GET['id'];

// Query untuk menghapus data mutasi
$query = mysqli_query($conn, "DELETE FROM mutasi WHERE id_mutasi = '$id_mutasi'");

if($query) {
    // Jika berhasil dihapus
    echo "<script>
        alert('Data mutasi berhasil dihapus!');
        window.location.href = 'index.php';
    </script>";
} else {
    // Jika gagal menghapus
    echo "<script>
        alert('Gagal menghapus data mutasi!');
        window.location.href = 'index.php';
    </script>";
}
?> 