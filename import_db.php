<?php
header('Content-Type: text/html; charset=utf-8');

$host = "localhost";
$username = "root";
$password = "";
$dbname = "hris";

$conn = @new mysqli($host, $username, $password);
if ($conn->connect_error) {
    die("Koneksi ke MySQL gagal: " . $conn->connect_error);
}

// Buat database hris jika belum ada
if (!$conn->query("CREATE DATABASE IF NOT EXISTS `$dbname` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci")) {
    die("Gagal membuat database hris: " . $conn->error);
}

// Pilih database hris
$conn->select_db($dbname);

// Path file sql
$sqlFile = __DIR__ . '/hris/database/hris(17).sql';
if (!file_exists($sqlFile)) {
    $sqlFile = __DIR__ . '/hris/database/hris31-01-2025.sql';
}

if (!file_exists($sqlFile)) {
    die("File SQL dump tidak ditemukan di folder database HRIS.");
}

$sqlContent = file_get_contents($sqlFile);

// Parsing query SQL dengan benar
$queries = [];
$query = '';
$lines = explode("\n", $sqlContent);

foreach ($lines as $line) {
    $trimmed = trim($line);
    if ($trimmed === '' || strpos($trimmed, '--') === 0 || strpos($trimmed, '/*') === 0 || strpos($trimmed, '#') === 0) {
        continue;
    }
    
    $query .= $line . "\n";
    
    if (substr($trimmed, -1) === ';') {
        $queries[] = $query;
        $query = '';
    }
}

echo "<h3>Memulai impor database dari " . basename($sqlFile) . "...</h3>";
$successCount = 0;
$failCount = 0;

foreach ($queries as $q) {
    $q = trim($q);
    if ($q === '') continue;
    if ($conn->query($q)) {
        $successCount++;
    } else {
        $failCount++;
        echo "<p style='color: red;'>Gagal mengeksekusi query: " . htmlspecialchars($conn->error) . "<br><small>" . htmlspecialchars(substr($q, 0, 150)) . "...</small></p>";
    }
}

echo "<h4 style='color: green;'>Proses impor database selesai!</h4>";
echo "<p>Berhasil: <strong>$successCount</strong> query, Gagal: <strong>$failCount</strong> query.</p>";
echo "<p><a href='/hris/index.php'>Buka Dashboard HRIS</a></p>";

$conn->close();
?>
