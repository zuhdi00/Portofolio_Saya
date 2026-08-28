<?php
// File untuk menampilkan gambar dari network drive
$networkPaths = [
    '//192.168.0.204/Master Design/',
    '\\\\192.168.0.204\\Master Design\\',
    'Z:/',
    'Y:/',
    'X:/'
];

$allowedExtensions = ['jpg', 'jpeg', 'JPG', 'JPEG'];

$fileName = isset($_GET['file']) ? $_GET['file'] : '';
$customPath = isset($_GET['path']) ? $_GET['path'] : '';

if (empty($fileName)) {
    header("HTTP/1.0 404 Not Found");
    exit('File not specified');
}


$extension = pathinfo($fileName, PATHINFO_EXTENSION);
if (!in_array($extension, $allowedExtensions)) {
    header("HTTP/1.0 403 Forbidden");
    exit('File type not allowed');
}

$fileName = basename($fileName);

$fullPath = null;
if (!empty($customPath)) {
    $testPath = $customPath . '/' . $fileName;
    if (@file_exists($testPath)) {
        $fullPath = $testPath;
    }
}

if (!$fullPath) {
    foreach ($networkPaths as $path) {
        $testPath = $path . $fileName;
        if (@file_exists($testPath)) {
            $fullPath = $testPath;
            break;
        }
    }
}

if (!$fullPath || !@file_exists($fullPath)) {
    header("HTTP/1.0 404 Not Found");
    
    header('Content-Type: image/svg+xml');
    echo '<?xml version="1.0" encoding="UTF-8"?>
    <svg width="300" height="200" viewBox="0 0 300 200" fill="none" xmlns="http://www.w3.org/2000/svg">
        <rect width="300" height="200" fill="#f8f9fa"/>
        <text x="150" y="100" text-anchor="middle" fill="#6c757d" font-family="Arial" font-size="16">Image Not Found</text>
        <text x="150" y="120" text-anchor="middle" fill="#6c757d" font-family="Arial" font-size="12">' . htmlspecialchars($fileName) . '</text>
    </svg>';
    exit;
}

$mimeType = 'image/jpeg';
if (strtolower($extension) === 'png') {
    $mimeType = 'image/png';
}

$fileSize = @filesize($fullPath);

header('Content-Type: ' . $mimeType);
if ($fileSize) {
    header('Content-Length: ' . $fileSize);
}
header('Cache-Control: public, max-age=3600'); // Cache selama 1 jam
header('Expires: ' . gmdate('D, d M Y H:i:s', time() + 3600) . ' GMT');

// Output file
@readfile($fullPath);
?>