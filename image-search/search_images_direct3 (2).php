<?php
// Disable any output before JSON
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
        error_log("[ImageSearch] " . $message);
    }
    
    $query = isset($_GET['q']) ? trim($_GET['q']) : '';
    if (empty($query)) {
        throw new Exception('Parameter q (query) diperlukan');
    }
    
    logDebug("Query: " . $query);
    
    // Try direct network access with UNC path first (since it works)
    $possiblePaths = [
        '\\\\192.168.0.204\\Master Design',  // This works based on your test
        '//192.168.0.204/Master Design',
        'Z:',
        'Z:/',
    ];
    
    $networkPath = null;
    $accessMethod = '';
    
    foreach ($possiblePaths as $path) {
        logDebug("Testing path: " . $path);
        
        // Use is_readable instead of is_dir for network paths
        if (@is_readable($path)) {
            $networkPath = $path;
            $accessMethod = 'readable';
            logDebug("Found readable path: " . $path);
            break;
        }
        
        // Alternative: try opendir
        if (@opendir($path)) {
            $networkPath = $path;
            $accessMethod = 'opendir';
            logDebug("Found opendir path: " . $path);
            break;
        }
        
        // Alternative: try glob
        $testFiles = @glob($path . '/*.jpg');
        if ($testFiles !== false) {
            $networkPath = $path;
            $accessMethod = 'glob';
            logDebug("Found via glob: " . $path . " with " . count($testFiles) . " files");
            break;
        }
    }
    
    if (!$networkPath) {
        // Try to get more detailed error info
        $errorDetails = [];
        foreach ($possiblePaths as $path) {
            $errorDetails[] = [
                'path' => $path,
                'is_dir' => @is_dir($path) ? 'yes' : 'no',
                'is_readable' => @is_readable($path) ? 'yes' : 'no',
                'file_exists' => @file_exists($path) ? 'yes' : 'no'
            ];
        }
        
        throw new Exception('Tidak dapat mengakses network path. Detail: ' . json_encode($errorDetails));
    }
    
    // Setup local cache
    $localImagesPath = __DIR__ . '/images/';
    if (!is_dir($localImagesPath)) {
        mkdir($localImagesPath, 0755, true);
    }
    
    $baseUrl = 'images/';
    $results = [];
    $maxResults = 10; // Reduce for testing
    
    logDebug("Searching in: " . $networkPath . " using method: " . $accessMethod);
    
    // Simple search function with better directory scanning
    function findImages($directory, $searchQuery, $maxResults) {
        $results = [];
        $count = 0;
        
        logDebug("Starting search in: " . $directory);
        
        // Method 1: Try RecursiveDirectoryIterator for deep search
        try {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($directory, RecursiveDirectoryIterator::SKIP_DOTS),
                RecursiveIteratorIterator::LEAVES_ONLY
            );
            
            foreach ($iterator as $file) {
                if ($count >= $maxResults) break;
                
                $filename = $file->getFilename();
                $extension = strtolower($file->getExtension());
                
                // Check file extension
                if (!in_array($extension, ['jpg', 'jpeg'])) {
                    continue;
                }
                
                // Check if filename contains search query (case insensitive)
                if (!empty($searchQuery) && stripos($filename, $searchQuery) === false) {
                    continue;
                }
                
                $results[] = $file->getPathname();
                $count++;
                logDebug("Found file: " . $filename);
            }
            
        } catch (Exception $e) {
            logDebug("RecursiveIterator failed: " . $e->getMessage());
            
            // Method 2: Fallback to manual directory scanning
            $subdirs = [$directory];
            $processedDirs = 0;
            $maxDirs = 50; // Limit subdirectories for performance
            
            while (!empty($subdirs) && $processedDirs < $maxDirs && $count < $maxResults) {
                $currentDir = array_shift($subdirs);
                $processedDirs++;
                
                logDebug("Scanning directory: " . $currentDir);
                
                // Get files in current directory
                $patterns = [
                    $currentDir . '/*.jpg',
                    $currentDir . '/*.jpeg',
                    $currentDir . '/*.JPG',
                    $currentDir . '/*.JPEG'
                ];
                
                foreach ($patterns as $pattern) {
                    $files = glob($pattern);
                    if ($files) {
                        foreach ($files as $file) {
                            if ($count >= $maxResults) break 3;
                            
                            $filename = basename($file);
                            
                            // Check if filename contains search query
                            if (!empty($searchQuery) && stripos($filename, $searchQuery) === false) {
                                continue;
                            }
                            
                            $results[] = $file;
                            $count++;
                            logDebug("Found file (glob): " . $filename);
                        }
                    }
                }
                
                // Add subdirectories to scan list
                $subdirPattern = $currentDir . '/*';
                $dirs = glob($subdirPattern, GLOB_ONLYDIR);
                if ($dirs) {
                    foreach ($dirs as $dir) {
                        if ($processedDirs < $maxDirs) {
                            $subdirs[] = $dir;
                        }
                    }
                }
            }
        }
        
        logDebug("Search completed. Found " . count($results) . " files");
        return $results;
    }
    
    $foundFiles = findImages($networkPath, $query, $maxResults);
    logDebug("Found " . count($foundFiles) . " files");
    
    // Get sample directory structure for debugging
    $sampleDirs = [];
    $sampleFiles = [];
    
    try {
        // List root directories
        $rootDirs = glob($networkPath . '/*', GLOB_ONLYDIR);
        $sampleDirs = array_slice($rootDirs, 0, 5);
        
        // Get sample JPG files from root
        $rootFiles = glob($networkPath . '/*.{jpg,jpeg,JPG,JPEG}', GLOB_BRACE);
        $sampleFiles = array_slice($rootFiles, 0, 5);
        
        logDebug("Root dirs: " . count($rootDirs) . ", Root JPG files: " . count($rootFiles));
    } catch (Exception $e) {
        logDebug("Error listing directories: " . $e->getMessage());
    }
    
    // Process found files
    foreach ($foundFiles as $file) {
        $filename = basename($file);
        $fileSize = @filesize($file);
        $relativePath = str_replace([$networkPath . '\\', $networkPath . '/'], '', $file);
        $hashedName = 'img_' . md5($relativePath . $filename) . '.jpg';
        $localFilePath = $localImagesPath . $hashedName;
        $webUrl = $baseUrl . $hashedName;
        
        // Try to copy file
        if (!file_exists($localFilePath)) {
            if (@copy($file, $localFilePath)) {
                logDebug("Copied: " . $filename);
            } else {
                logDebug("Failed to copy: " . $filename);
                // Still add to results but note the issue
            }
        }
        
        $results[] = [
            'name' => $filename,
            'url' => file_exists($localFilePath) ? $webUrl : 'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMzAwIiBoZWlnaHQ9IjIwMCIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48cmVjdCB3aWR0aD0iMzAwIiBoZWlnaHQ9IjIwMCIgZmlsbD0iI2RkZCIvPjx0ZXh0IHg9IjUwJSIgeT0iNTAlIiBmb250LWZhbWlseT0iQXJpYWwiIGZvbnQtc2l6ZT0iMTQiIGZpbGw9IiM5OTkiIHRleHQtYW5jaG9yPSJtaWRkbGUiIGR5PSIuM2VtIj5JbWFnZSBOb3QgQXZhaWxhYmxlPC90ZXh0Pjwvc3ZnPg==',
            'size' => $fileSize ?: 0,
            'path' => $relativePath,
            'source_path' => $file,
            'copied' => file_exists($localFilePath)
        ];
    }
    
    // Success response
    $response = [
        'success' => true,
        'query' => $query,
        'count' => count($results),
        'images' => $results,
        'debug' => [
            'network_path' => $networkPath,
            'access_method' => $accessMethod,
            'found_files_count' => count($foundFiles),
            'local_images_path' => $localImagesPath,
            'sample_directories' => array_map('basename', $sampleDirs),
            'sample_root_files' => array_map('basename', $sampleFiles),
            'total_root_dirs' => isset($rootDirs) ? count($rootDirs) : 0,
            'total_root_files' => isset($rootFiles) ? count($rootFiles) : 0
        ]
    ];
    
    logDebug("Sending response with " . count($results) . " images");
    echo json_encode($response, JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    logDebug("Error: " . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'debug' => [
            'php_version' => PHP_VERSION,
            'query' => isset($query) ? $query : 'not set',
            'current_user' => get_current_user(),
            'working_directory' => getcwd()
        ]
    ], JSON_PRETTY_PRINT);
}

ob_end_flush();
?>