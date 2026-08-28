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

// Decode and normalize slashes while preserving UNC leading slashes
$path = urldecode($path);
$path = str_replace('\\', '/', $path);
// Preserve leading double-slash for UNC (//server/share)
if (substr($path, 0, 2) === '//') {
    $path = '//' . preg_replace('#/+#', '/', substr($path, 2));
} else {
    $path = preg_replace('#/+#', '/', $path);
}

// Security: Only allow drive-letter (e.g. Z:/) or UNC (//server/share)
if (!(stripos($path, 'Z:/') === 0 || substr($path, 0, 2) === '//')) {
    http_response_code(403);
    echo 'Forbidden path.';
    exit();
}

// Convert normalized path to Windows format for file functions
$path_for_fs = $path;
if (substr($path_for_fs, 0, 2) === '//') {
    // make UNC with backslashes: \\\\server\share
    $path_for_fs = '\\\\' . substr($path_for_fs, 2);
} else {
    // ensure backslashes for drive-letter paths (Z:/ -> Z:\)
    $path_for_fs = preg_replace('#/+','#\\\\', $path_for_fs);
}

// Check file existence
if (!file_exists($path_for_fs) || !is_file($path_for_fs)) {
    http_response_code(404);
    echo 'File not found: ' . htmlspecialchars($path);
    exit();
}

// Get mime type
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime = finfo_file($finfo, $path_for_fs);
finfo_close($finfo);
if (!$mime) $mime = 'application/octet-stream';

// Serve image
header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($path_for_fs));
header('Cache-Control: public, max-age=86400');
readfile($path_for_fs);
exit();
?>
