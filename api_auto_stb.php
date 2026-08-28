<?php
header('Content-Type: application/json');

$year = $_GET['year'] ?? '';
$month = $_GET['month'] ?? '';
$prefix = "STB-{$year}{$month}";

$conn = new mysqli('localhost', 'user', 'pass', 'db');
$sql = "SELECT cNoSTB FROM stb WHERE cNoSTB LIKE '{$prefix}%' ORDER BY cNoSTB DESC LIMIT 1";
$result = $conn->query($sql);

$lastNumber = 0;
if ($row = $result->fetch_assoc()) {
    $lastNumber = intval(substr($row['cNoSTB'], -4));
}
echo json_encode($lastNumber);