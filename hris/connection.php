<?php
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "hris";

// Membuat koneksi dengan error handling
try {
    $conn = new mysqli($servername, $username, $password, $dbname);
    
    // Cek koneksi
    if ($conn->connect_error) {
        throw new Exception("Koneksi gagal: " . $conn->connect_error);
    }
    
    // Set mode SQL yang lebih longgar
    $conn->query("SET sql_mode = ''");
    
    // Set charset
    $conn->set_charset("utf8mb4");
    
} catch (Exception $e) {
    die("Koneksi gagal: " . $e->getMessage());
}
?>
