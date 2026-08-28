<?php
// image_proxy.php - Proxy untuk serve file gambar dari Z: atau path lain
// Usage: image_proxy.php?path=Z:/folder/file.jpg

// Allow CORS for local testing
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Get path from query
$path = isset($_GET['path']) ? $_GET['path'] : '';
if (!$path) {
    http_response_code(400);
    echo 'Missing path parameter.';
    exit();
}

// Normalize path (replace double slashes, decode, etc)
$path = str_replace(['\\', '//'], ['/', '/'], $path);
$path = urldecode($path);

// Security: Only allow Z:/ or network share
if (!(stripos($path, 'Z:/') === 0 || stripos($path, '\\') === 0)) {
    http_response_code(403);
    echo 'Forbidden path.';
    exit();
}

// Check file existence
if (!file_exists($path) || !is_file($path)) {
    http_response_code(404);
    echo 'File not found: ' . htmlspecialchars($path);
    exit();
}

// Get mime type
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime = finfo_file($finfo, $path);
finfo_close($finfo);
if (!$mime) $mime = 'application/octet-stream';

// Serve image
header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($path));
header('Cache-Control: public, max-age=86400');
readfile($path);
exit();
?>
