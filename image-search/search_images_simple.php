<?php
// Disable any output before JSON
ob_start();

// Set error reporting
error_reporting(E_ALL);
ini_set('display_errors', 0);

// Set headers first
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Clear any previous output
ob_clean();

try {
    // Logging function
    function logDebug($message) {
        error_log("[ImageSearch] " . $message);
    }
    
    logDebug("Script started");
    
    // Get query parameter
    $query = isset($_GET['q']) ? trim($_GET['q']) : '';
    
    if (empty($query)) {
        throw new Exception('Parameter q (query) diperlukan');
    }
    
    logDebug("Query: " . $query);
    
    // Configuration - coba beberapa path
    $possiblePaths = [
        'Z:',  // Mounted drive
        'Z:/',
        '//192.168.0.204/Master Design',
        '\\\\192.168.0.204\\Master Design'
    ];
    
    $networkPath = null;
    foreach ($possiblePaths as $path) {
        if (is_dir($path)) {
            $networkPath = $path;
            logDebug("Found accessible path: " . $path);
            break;
        }
    }
    
    if (!$networkPath) {
        throw new Exception('Tidak dapat mengakses network path. Pastikan drive sudah di-mount.');
    }
    
    // Setup local cache directory
    $localImagesPath = __DIR__ . '/images/';
    if (!is_dir($localImagesPath)) {
        if (!mkdir($localImagesPath, 0755, true)) {
            throw new Exception('Tidak dapat membuat direktori cache');
        }
    }
    
    $baseUrl = 'images/'; // Relative URL
    $results = [];
    $maxResults = 20; // Limit untuk testing
    
    logDebug("Searching in: " . $networkPath);
    
    // Simple file search function
    function searchFiles($directory, $searchQuery, $maxResults) {
        $results = [];
        $count = 0;
        
        // Use glob for simple search
        $patterns = [
            $directory . '/*' . $searchQuery . '*.jpg',
            $directory . '/*' . $searchQuery . '*.jpeg',
            $directory . '/**/*' . $searchQuery . '*.jpg',
            $directory . '/**/*' . $searchQuery . '*.jpeg'
        ];
        
        foreach ($patterns as $pattern) {
            $files = glob($pattern, GLOB_BRACE);
            if ($files) {
                foreach ($files as $file) {
                    if ($count >= $maxResults) break 2;
                    
                    if (is_file($file)) {
                        $results[] = $file;
                        $count++;
                    }
                }
            }
        }
        
        return $results;
    }
    
    $foundFiles = searchFiles($networkPath, $query, $maxResults);
    logDebug("Found " . count($foundFiles) . " files");
    
    foreach ($foundFiles as $file) {
        $filename = basename($file);
        $fileSize = filesize($file);
        $relativePath = str_replace($networkPath, '', $file);
        $hashedName = 'img_' . md5($relativePath) . '.jpg';
        $localFilePath = $localImagesPath . $hashedName;
        $webUrl = $baseUrl . $hashedName;
        
        // Copy file if not exists
        if (!file_exists($localFilePath)) {
            if (copy($file, $localFilePath)) {
                logDebug("Copied: " . $filename);
            } else {
                logDebug("Failed to copy: " . $filename);
                continue;
            }
        }
        
        $results[] = [
            'name' => $filename,
            'url' => $webUrl,
            'size' => $fileSize,
            'path' => $relativePath
        ];
    }
    
    // Success response
    $response = [
        'success' => true,
        'query' => $query,
        'count' => count($results),
        'images' => $results,
        'network_path' => $networkPath,
        'debug' => [
            'php_version' => PHP_VERSION,
            'local_images_path' => $localImagesPath,
            'found_files_count' => count($foundFiles)
        ]
    ];
    
    logDebug("Sending response with " . count($results) . " images");
    echo json_encode($response, JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    logDebug("Error: " . $e->getMessage());
    
    $errorResponse = [
        'success' => false,
        'error' => $e->getMessage(),
        'debug' => [
            'php_version' => PHP_VERSION,
            'query' => isset($query) ? $query : 'not set',
            'possible_paths_checked' => isset($possiblePaths) ? $possiblePaths : [],
            'local_images_path' => __DIR__ . '/images/'
        ]
    ];
    
    echo json_encode($errorResponse, JSON_PRETTY_PRINT);
} catch (Error $e) {
    logDebug("Fatal Error: " . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'error' => 'Fatal error: ' . $e->getMessage()
    ]);
}

// Flush output
ob_end_flush();
?>