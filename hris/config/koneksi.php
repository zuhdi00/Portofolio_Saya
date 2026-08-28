<?php
$host = 'localhost';
$username = 'root';
$password = '';
$database = 'hris';

// Buat koneksi
$mysqli = new mysqli($host, $username, $password, $database);

// Cek koneksi
if ($mysqli->connect_error) {
    die("Koneksi gagal: " . $mysqli->connect_error);
}

// Set karakter encoding
$mysqli->set_charset("utf8");