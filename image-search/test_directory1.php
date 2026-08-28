<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

$networkPath = '\\\\192.168.0.204\\Master Design';

try {
    $result = [
        'network_path' => $networkPath,
        'accessible' => is_dir($networkPath),
        'directories' => [],
        'files' => [],
        'search_results' => []
    ];
    
    if (is_dir($networkPath)) {
        // List directories
        $dirs = glob($networkPath . '/*', GLOB_ONLYDIR);
        foreach ($dirs as $dir) {
            $result['directories'][] = basename($dir);
        }
        
        // List JPG files in root
        $files = glob($networkPath . '/*.{jpg,jpeg,JPG,JPEG}', GLOB_BRACE);
        foreach ($files as $file) {
            $result['files'][] = basename($file);
        }
        
        // Search for specific file
        $searchQuery = isset($_GET['q']) ? $_GET['q'] : 'BE28420';
        
        // Search in subdirectories
        foreach ($dirs as $dir) {
            $subFiles = glob($dir . '/*.{jpg,jpeg,JPG,JPEG}', GLOB_BRACE);
            foreach ($subFiles as $file) {
                $filename = basename($file);
                if (stripos($filename, $searchQuery) !== false) {
                    $result['search_results'][] = [
                        'filename' => $filename,
                        'path' => str_replace($networkPath, '', dirname($file)),
                        'full_path' => $file
                    ];
                }
            }
        }
        
        $result['total_directories'] = count($dirs);
        $result['total_root_files'] = count($files);
        $result['total_search_results'] = count($result['search_results']);
    }
    
    echo json_encode($result, JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    echo json_encode([
        'error' => $e->getMessage(),
        'network_path' => $networkPath
    ]);
}
?>