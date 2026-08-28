<?php
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');

$imagePath = isset($_GET['path']) ? $_GET['path'] : '';

if (empty($imagePath)) {
    http_response_code(400);
    die('Path parameter required');
}

$allowedPaths = [
    'C:/xampp/htdocs/images/',
    '\\\\192.168.0.204\\Master Design\\'
];

$isValidPath = false;
foreach ($allowedPaths as $allowedPath) {
    if (strpos($imagePath, $allowedPath) === 0) {
        $isValidPath = true;
        break;
    }
}

if (!$isValidPath) {
    http_response_code(403);
    die('Access denied - Invalid path');
}

// Check if file exists and is readable
if (!file_exists($imagePath) || !is_readable($imagePath)) {
    http_response_code(404);
    die('Image not found or not readable');
}

// Get file info
$fileInfo = pathinfo($imagePath);
$extension = strtolower($fileInfo['extension']);

// Check if it's a valid image file
$allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp'];
if (!in_array($extension, $allowedExtensions)) {
    http_response_code(400);
    die('Invalid image format');
}

// Set appropriate content type
$contentTypes = [
    'jpg'  => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'png'  => 'image/png',
    'gif'  => 'image/gif',
    'webp' => 'image/webp',
    'bmp'  => 'image/bmp'
];

$contentType = $contentTypes[$extension];

// Get file size for Content-Length header
$fileSize = filesize($imagePath);

// Set cache headers
$lastModified = filemtime($imagePath);
$etag = md5($imagePath . $lastModified);

header('Content-Type: ' . $contentType);
header('Content-Length: ' . $fileSize);
header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $lastModified) . ' GMT');
header('ETag: "' . $etag . '"');
header('Cache-Control: public, max-age=3600'); // Cache for 1 hour
header('Expires: ' . gmdate('D, d M Y H:i:s', time() + 3600) . ' GMT');

// Check if client has cached version
$clientETag = isset($_SERVER['HTTP_IF_NONE_MATCH']) ? trim($_SERVER['HTTP_IF_NONE_MATCH'], '"') : '';
$clientLastModified = isset($_SERVER['HTTP_IF_MODIFIED_SINCE']) ? strtotime($_SERVER['HTTP_IF_MODIFIED_SINCE']) : 0;

if ($clientETag === $etag || $clientLastModified >= $lastModified) {
    http_response_code(304);
    exit;
}

// Security check - make sure it's actually an image
$imageInfo = getimagesize($imagePath);
if ($imageInfo === false) {
    http_response_code(400);
    die('Invalid image file');
}

// Optional: Resize image if too large (untuk optimasi)
$maxWidth = isset($_GET['w']) ? (int)$_GET['w'] : 0;
$maxHeight = isset($_GET['h']) ? (int)$_GET['h'] : 0;

if ($maxWidth > 0 || $maxHeight > 0) {
    resizeAndOutput($imagePath, $maxWidth, $maxHeight, $contentType);
} else {
    // Output original image
    readfile($imagePath);
}

/**
 * Resize image and output
 */
function resizeAndOutput($imagePath, $maxWidth, $maxHeight, $contentType) {
    $imageInfo = getimagesize($imagePath);
    $originalWidth = $imageInfo[0];
    $originalHeight = $imageInfo[1];
    
    // Calculate new dimensions
    if ($maxWidth <= 0) $maxWidth = $originalWidth;
    if ($maxHeight <= 0) $maxHeight = $originalHeight;
    
    $ratio = min($maxWidth / $originalWidth, $maxHeight / $originalHeight);
    $newWidth = round($originalWidth * $ratio);
    $newHeight = round($originalHeight * $ratio);
    
    // Skip resize if image is already smaller
    if ($newWidth >= $originalWidth && $newHeight >= $originalHeight) {
        readfile($imagePath);
        return;
    }
    
    // Create image resource based on type
    $extension = strtolower(pathinfo($imagePath, PATHINFO_EXTENSION));
    
    switch ($extension) {
        case 'jpg':
        case 'jpeg':
            $sourceImage = imagecreatefromjpeg($imagePath);
            break;
        case 'png':
            $sourceImage = imagecreatefrompng($imagePath);
            break;
        case 'gif':
            $sourceImage = imagecreatefromgif($imagePath);
            break;
        case 'webp':
            $sourceImage = imagecreatefromwebp($imagePath);
            break;
        default:
            readfile($imagePath);
            return;
    }
    
    if (!$sourceImage) {
        readfile($imagePath);
        return;
    }
    
    // Create new image
    $newImage = imagecreatetruecolor($newWidth, $newHeight);
    
    // Preserve transparency for PNG/GIF
    if ($extension === 'png' || $extension === 'gif') {
        imagealphablending($newImage, false);
        imagesavealpha($newImage, true);
        $transparent = imagecolorallocatealpha($newImage, 0, 0, 0, 127);
        imagefill($newImage, 0, 0, $transparent);
    }
    
    // Resize
    imagecopyresampled(
        $newImage, $sourceImage,
        0, 0, 0, 0,
        $newWidth, $newHeight, $originalWidth, $originalHeight
    );
    
    // Output based on original format
    switch ($extension) {
        case 'jpg':
        case 'jpeg':
            imagejpeg($newImage, null, 85); // 85% quality
            break;
        case 'png':
            imagepng($newImage);
            break;
        case 'gif':
            imagegif($newImage);
            break;
        case 'webp':
            imagewebp($newImage);
            break;
    }
    
    // Clean up memory
    imagedestroy($sourceImage);
    imagedestroy($newImage);
}

// Error handler
function handleError($errno, $errstr, $errfile, $errline) {
    error_log("Image Proxy Error: $errstr in $errfile:$errline");
    http_response_code(500);
    die('Internal server error');
}

set_error_handler('handleError');
?>