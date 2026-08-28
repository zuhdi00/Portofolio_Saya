<?php
ob_start();
error_reporting(E_ALL);
ini_set('display_errors', 0);

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

ob_clean();

try {
    function logDebug($message) {
        error_log("[ImageSearchCMD] " . $message);
    }
    
    $query = isset($_GET['q']) ? trim($_GET['q']) : '';
    if (empty($query)) {
        throw new Exception('Parameter q (query) diperlukan');
    }
    
    logDebug("Query: " . $query);
    
    function searchFilesWithCMD($searchQuery, $maxResults = 20) {
        $results = [];
        
        $networkPath = '\\\\192.168.0.204\\Master Design';
        $cmd = 'dir "' . $networkPath . '" /s /b *.jpg 2>nul | findstr /i "' . $searchQuery . '"';
        
        logDebug("Executing command: " . $cmd);
        
        $output = [];
        $returnCode = 0;
        exec($cmd, $output, $returnCode);
        
        logDebug("Command return code: " . $returnCode);
        logDebug("Output lines: " . count($output));
        
        if ($returnCode === 0 && !empty($output)) {
            foreach ($output as $line) {
                if (count($results) >= $maxResults) break;
                
                $filePath = trim($line);
                if (empty($filePath)) continue;
                
                $filename = basename($filePath);
                $relativePath = str_replace($networkPath . '\\', '', dirname($filePath));
                
                $sizeCmd = 'dir "' . $filePath . '" 2>nul | findstr /v "Directory"';
                $sizeOutput = [];
                exec($sizeCmd, $sizeOutput);
                
                $fileSize = 0;
                if (!empty($sizeOutput)) {
                    foreach ($sizeOutput as $sizeLine) {
                        if (strpos($sizeLine, $filename) !== false) {
                            $parts = preg_split('/\s+/', trim($sizeLine));
                            if (count($parts) >= 4) {
                                $fileSize = intval(str_replace(',', '', $parts[2]));
                            }
                            break;
                        }
                    }
                }
                
                $results[] = [
                    'filename' => $filename,
                    'full_path' => $filePath,
                    'relative_path' => $relativePath,
                    'size' => $fileSize
                ];
                
                logDebug("Found: " . $filename . " (Size: " . $fileSize . ")");
            }
        }
        
        return $results;
    }
    
    $searchResults = searchFilesWithCMD($query, 10);
    
    $localImagesPath = __DIR__ . '/images/';
    if (!is_dir($localImagesPath)) {
        mkdir($localImagesPath, 0755, true);
    }
    
    $baseUrl = 'images/';
    $processedImages = [];
    
    foreach ($searchResults as $fileInfo) {
        $filename = $fileInfo['filename'];
        $sourcePath = $fileInfo['full_path'];
        
        $hashedName = 'img_' . md5($sourcePath) . '.jpg';
        $localFilePath = $localImagesPath . $hashedName;
        $webUrl = $baseUrl . $hashedName;
        
        $copied = false;
        if (!file_exists($localFilePath)) {
            $copyCmd = 'copy "' . $sourcePath . '" "' . $localFilePath . '" >nul 2>&1';
            $copyResult = 0;
            exec($copyCmd, $copyOutput, $copyResult);
            
            if ($copyResult === 0 && file_exists($localFilePath)) {
                $copied = true;
                logDebug("Copied: " . $filename);
            } else {
                logDebug("Failed to copy: " . $filename . " (Code: " . $copyResult . ")");
            }
        } else {
            $copied = true; 
        }
        
        $processedImages[] = [
            'name' => $filename,
            'url' => $copied ? $webUrl : 'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMzAwIiBoZWlnaHQ9IjIwMCIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48cmVjdCB3aWR0aD0iMzAwIiBoZWlnaHQ9IjIwMCIgZmlsbD0iI2RkZCIvPjx0ZXh0IHg9IjUwJSIgeT0iNTAlIiBmb250LWZhbWlseT0iQXJpYWwiIGZvbnQtc2l6ZT0iMTQiIGZpbGw9IiM5OTkiIHRleHQtYW5jaG9yPSJtaWRkbGUiIGR5PSIuM2VtIj5JbWFnZSBOb3QgQXZhaWxhYmxlPC90ZXh0Pjwvc3ZnPg==',
            'size' => $fileInfo['size'],
            'path' => $fileInfo['relative_path'],
            'copied' => $copied,
            'source_path' => $sourcePath
        ];
    }
    
    $response = [
        'success' => true,
        'query' => $query,
        'count' => count($processedImages),
        'images' => $processedImages,
        'debug' => [
            'search_method' => 'Windows CMD',
            'found_files_count' => count($searchResults),
            'processed_count' => count($processedImages),
            'local_images_path' => $localImagesPath,
            'php_user' => get_current_user()
        ]
    ];
    
    logDebug("Sending response with " . count($processedImages) . " images");
    echo json_encode($response, JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    logDebug("Error: " . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'debug' => [
            'php_version' => PHP_VERSION,
            'query' => isset($query) ? $query : 'not set',
            'php_user' => get_current_user(),
            'working_directory' => getcwd(),
            'exec_available' => function_exists('exec')
        ]
    ], JSON_PRETTY_PRINT);
}

ob_end_flush();
?>