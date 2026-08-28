<?php
// Konfigurasi Database
$host = "localhost";     // Host database
$username = "root";      // Username database
$password = "";          // Password database
$database = "hris";       // Nama database diubah menjadi hris

// Membuat koneksi dengan error handling
try {
    $conn = new mysqli($host, $username, $password, $database);
    
    // Cek koneksi
    if ($conn->connect_error) {
        throw new Exception("Koneksi gagal: " . $conn->connect_error);
    }
    
    // Set mode SQL yang lebih longgar
    $conn->query("SET sql_mode = ''");
    
    // Set charset
    $conn->set_charset("utf8mb4");
    
} catch (Exception $e) {
    die("Error: " . $e->getMessage());
}

// Zona waktu
date_default_timezone_set('Asia/Jakarta');
?> 