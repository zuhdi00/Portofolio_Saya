<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit(0);
}

// Configuration
$NETWORK_PATH = '//192.168.0.204/Master Design'; // Network share path
$BASE_URL = 'http://180.251.120.19:8081/images/'; // Base URL untuk akses gambar
$LOCAL_IMAGES_PATH = __DIR__ . '/images/'; // Local path untuk cache gambar

// Fungsi untuk membuat direktori jika belum ada
function createDirectoryIfNotExists($path) {
    if (!is_dir($path)) {
        mkdir($path, 0755, true);
    }
}

// Fungsi untuk mendapatkan ukuran file yang dapat dibaca
function formatBytes($size, $precision = 2) {
    if ($size == 0) return '0 B';
    $base = log($size, 1024);
    $suffixes = array('B', 'KB', 'MB', 'GB', 'TB');
    return round(pow(1024, $base - floor($base)), $precision) . ' ' . $suffixes[floor($base)];
}

// Fungsi untuk mencari file JPG
function searchImages($query, $networkPath, $localPath, $baseUrl) {
    $results = [];
    
    try {
        // Pastikan direktori lokal ada
        createDirectoryIfNotExists($localPath);
        
        // Coba akses network share
        if (!is_dir($networkPath)) {
            // Jika network share tidak dapat diakses, coba mount atau gunakan credentials
            // Untuk Windows, mungkin perlu menggunakan net use command
            throw new Exception("Cannot access network path: $networkPath");
        }
        
        // Fungsi rekursif untuk mencari file
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($networkPath, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::LEAVES_ONLY
        );
        
        $count = 0;
        foreach ($iterator as $file) {
            if ($count >= 100) break; // Batasi hasil maksimal 100 file
            
            $filename = $file->getFilename();
            $extension = strtolower($file->getExtension());
            
            // Filter hanya file JPG/JPEG
            if (!in_array($extension, ['jpg', 'jpeg'])) {
                continue;
            }
            
            // Filter berdasarkan query pencarian
            if (!empty($query) && stripos($filename, $query) === false) {
                continue;
            }
            
            $relativePath = str_replace($networkPath . DIRECTORY_SEPARATOR, '', $file->getPathname());
            $localFilePath = $localPath . md5($relativePath) . '.' . $extension;
            $webUrl = $baseUrl . md5($relativePath) . '.' . $extension;
            
            // Copy file ke direktori lokal jika belum ada
            if (!file_exists($localFilePath)) {
                try {
                    copy($file->getPathname(), $localFilePath);
                } catch (Exception $e) {
                    continue; // Skip jika tidak bisa copy
                }
            }
            
            $results[] = [
                'name' => $filename,
                'url' => $webUrl,
                'size' => $file->getSize(),
                'path' => $relativePath,
                'modified' => date('Y-m-d H:i:s', $file->getMTime())
            ];
            
            $count++;
        }
        
    } catch (Exception $e) {
        throw new Exception("Error searching files: " . $e->getMessage());
    }
    
    return $results;
}

try {
    // Ambil parameter query
    $query = isset($_GET['q']) ? trim($_GET['q']) : '';
    
    if (empty($query)) {
        echo json_encode(['error' => 'Query parameter required']);
        exit;
    }
    
    // Cari file
    $images = searchImages($query, $NETWORK_PATH, $LOCAL_IMAGES_PATH, $BASE_URL);
    
    // Return hasil
    echo json_encode([
        'success' => true,
        'query' => $query,
        'count' => count($images),
        'images' => $images
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>