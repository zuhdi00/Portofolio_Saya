<?php
require_once 'koneksi.php';

// Tampilkan error
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Cek apakah tabel ada
$check_table = "SHOW TABLES LIKE 'pelatihan'";
$result = $conn->query($check_table);

if ($result->num_rows > 0) {
    echo "Tabel pelatihan ditemukan.<br><br>";
    
    // Tampilkan struktur tabel
    echo "Struktur tabel pelatihan:<br>";
    $desc_table = "DESCRIBE pelatihan";
    $result = $conn->query($desc_table);
    
    if ($result) {
        echo "<pre>";
        while ($row = $result->fetch_assoc()) {
            print_r($row);
        }
        echo "</pre>";
    }
} else {
    echo "Tabel pelatihan tidak ditemukan!";
}

$conn->close();
?> 