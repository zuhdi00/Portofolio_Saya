<?php
header('Content-Type: application/json');

// Lokasi file di network
$filePath = 'R:\\250830.txt';

if (!file_exists($filePath)) {
    echo json_encode(["error" => "File tidak ditemukan"]);
    exit;
}

$lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
$data = [];

foreach ($lines as $line) {
    // Mapping fixed-width sesuai kebutuhan
    $row = [
        "seq"      => trim(substr($line, 0, 10)),
        "customer" => trim(substr($line, 10, 30)),
        "flu"      => trim(substr($line, 40, 2)),
        "db"       => trim(substr($line, 60, 10)),
        "c1m"      => trim(substr($line, 70, 10)),
        "c1l"      => trim(substr($line, 80, 10)),
        "c2m"      => trim(substr($line, 90, 10)),
        "c2l"      => trim(substr($line, 100, 10)),
        "width"    => trim(substr($line, 120, 5)),
        "length"   => trim(substr($line, 125, 5)),
        "cuts"     => trim(substr($line, 130, 5)),
        "good"     => trim(substr($line, 135, 5)),
    ];
    $data[] = $row;
}

echo json_encode($data, JSON_PRETTY_PRINT);
