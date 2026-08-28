<?php
if (!isset($_GET['file'])) {
    http_response_code(400);
    echo "File not specified.";
    exit;
}

$filePath = $_GET['file'];

if (!file_exists($filePath)) {
    http_response_code(404);
    echo "File not found.";
    exit;
}

$mimeType = mime_content_type($filePath);
header("Content-Type: $mimeType");
readfile($filePath);
exit;
