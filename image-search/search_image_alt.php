<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit(0);
}

// Configuration menggunakan mounted drive
$NETWORK_PATH = 'Z:/'; // Gunakan drive yang sudah di-mount
$BASE_URL = 'http://180.251.120.19:8081/images/';
$LOCAL_IMAGES_PATH = __DIR__ . '/images/';

function createDirectoryIfNotExists($path) {
    if (!is_dir($path)) {
        mkdir($path, 0755, true);
    }
}

function generateThumbnail($sourcePath, $destPath, $maxWidth = 800, $maxHeight = 600, $quality = 85) {
    $imageInfo = getimagesize($sourcePath);
    if (!$imageInfo) return false;
    
    $originalWidth = $imageInfo[0];
    $originalHeight = $imageInfo[1];
    $imageType = $imageInfo[2];
    
    // Hitung dimensi baru dengan mempertahankan aspect ratio
    $ratio = min($maxWidth / $originalWidth, $maxHeight / $originalHeight);
    $newWidth = intval($originalWidth * $ratio);
    $newHeight = intval($originalHeight * $ratio);
    
    // Buat image resource dari file asli
    switch ($imageType) {
        case IMAGETYPE_JPEG:
            $sourceImage = imagecreatefromjpeg($sourcePath);
            break;
        default:
            return false;
    }
    
    if (!$sourceImage) return false;
    
    // Buat image baru dengan dimensi yang sudah dihitung
    $destImage = imagecreatetruecolor($newWidth, $newHeight);
    
    // Resize image
    imagecopyresampled($destImage, $sourceImage, 0, 0, 0, 0, 
                      $newWidth, $newHeight, $originalWidth, $originalHeight);
    
    // Simpan image
    $result = imagejpeg($destImage, $destPath, $quality);
    
    // Cleanup
    imagedestroy($sourceImage);
    imagedestroy($destImage);
    
    return $result;
}

function searchImagesAdvanced($query, $networkPath, $localPath, $baseUrl) {
    $results = [];
    $processed = 0;
    $maxResults = 50; // Batasi hasil untuk performa
    
    try {
        createDirectoryIfNotExists($localPath);
        
        if (!is_dir($networkPath)) {
            throw new Exception("Network path not accessible: $networkPath");
        }
        
        // Fungsi rekursif untuk scan direktori
        function scanDirectory($dir, $query, &$results, &$processed, $maxResults, $localPath, $baseUrl) {
            if ($processed >= $maxResults) return;
            
            $files = glob($dir . '/*');
            
            foreach ($files as $file) {
                if ($processed >= $maxResults) break;
                
                if (is_dir($file)) {
                    // Rekursif untuk subdirectory
                    scanDirectory($file, $query, $results, $processed, $maxResults, $localPath, $baseUrl);
                } else {
                    $filename = basename($file);
                    $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
                    
                    // Filter hanya file JPG/JPEG
                    if (!in_array($extension, ['jpg', 'jpeg'])) {
                        continue;
                    }
                    
                    // Filter berdasarkan query pencarian (case insensitive)
                    if (!empty($query) && stripos($filename, $query) === false) {
                        continue;
                    }
                    
                    $fileSize = filesize($file);
                    $relativePath = str_replace($GLOBALS['NETWORK_PATH'], '', $file);
                    $relativePath = ltrim(str_replace('\\', '/', $relativePath), '/');
                    
                    $hashedName = md5($relativePath) . '.' . $extension;
                    $localFilePath = $localPath . $hashedName;
                    $webUrl = $baseUrl . $hashedName;
                    
                    // Generate thumbnail/copy file ke direktori lokal
                    if (!file_exists($localFilePath)) {
                        try {
                            // Coba generate thumbnail, jika gagal copy langsung
                            if (!generateThumbnail($file, $localFilePath)) {
                                copy($file, $localFilePath);
                            }
                        } catch (Exception $e) {
                            error_log("Error processing file $filename: " . $e->getMessage());
                            continue;
                        }
                    }
                    
                    $results[] = [
                        'name' => $filename,
                        'url' => $webUrl,
                        'size' => $fileSize,
                        'path' => $relativePath,
                        'modified' => date('Y-m-d H:i:s', filemtime($file)),
                        'dimensions' => getImageDimensions($file)
                    ];
                    
                    $processed++;
                }
            }
        }
        
        scanDirectory($networkPath, $query, $results, $processed, $maxResults, $localPath, $baseUrl);
        
    } catch (Exception $e) {
        throw new Exception("Error searching files: " . $e->getMessage());
    }
    
    // Sort hasil berdasarkan nama file
    usort($results, function($a, $b) {
        return strcasecmp($a['name'], $b['name']);
    });
    
    return $results;
}

function getImageDimensions($imagePath) {
    try {
        $imageInfo = getimagesize($imagePath);
        if ($imageInfo) {
            return [
                'width' => $imageInfo[0],
                'height' => $imageInfo[1]
            ];
        }
    } catch (Exception $e) {
        // Ignore errors
    }
    return null;
}

// API endpoint handler
try {
    $query = isset($_GET['q']) ? trim($_GET['q']) : '';
    
    if (empty($query)) {
        echo json_encode([
            'success' => false,
            'error' => 'Query parameter (q) is required'
        ]);
        exit;
    }
    
    // Validasi query untuk mencegah path traversal
    if (strpos($query, '..') !== false || strpos($query, '/') !== false || strpos($query, '\\') !== false) {
        echo json_encode([
            'success' => false,
            'error' => 'Invalid query format'
        ]);
        exit;
    }
    
    $startTime = microtime(true);
    $images = searchImagesAdvanced($query, $NETWORK_PATH, $LOCAL_IMAGES_PATH, $BASE_URL);
    $endTime = microtime(true);
    
    echo json_encode([
        'success' => true,
        'query' => $query,
        'count' => count($images),
        'execution_time' => round($endTime - $startTime, 3),
        'images' => $images,
        'message' => count($images) === 50 ? 'Results limited to 50 items for performance' : null
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'debug' => [
            'network_path' => $NETWORK_PATH,
            'local_path' => $LOCAL_IMAGES_PATH,
            'php_version' => PHP_VERSION
        ]
    ]);
}
?>