<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

$query = isset($_GET['q']) ? $_GET['q'] : 'BE28420';

try {
    $tests = [];
    
    // Test 1: Check if Z: drive is accessible
    $tests['z_drive_accessible'] = is_dir('Z:');
    $tests['z_drive_readable'] = is_readable('Z:');
    
    // Test 2: Try to list Z: root directory
    if ($tests['z_drive_accessible']) {
        $rootDirs = glob('Z:/*', GLOB_ONLYDIR);
        $tests['root_directories'] = array_map('basename', array_slice($rootDirs, 0, 5));
        $tests['total_root_dirs'] = count($rootDirs);
        
        // Test 3: Look for JPG files in root
        $rootFiles = glob('Z:/*.{jpg,jpeg,JPG,JPEG}', GLOB_BRACE);
        $tests['root_jpg_files'] = array_map('basename', array_slice($rootFiles, 0, 5));
        $tests['total_root_jpg'] = count($rootFiles);
        
        // Test 4: Search in subdirectories (one level)
        $searchResults = [];
        foreach ($rootDirs as $dir) {
            $subFiles = glob($dir . '/*.{jpg,jpeg,JPG,JPEG}', GLOB_BRACE);
            foreach ($subFiles as $file) {
                $filename = basename($file);
                if (stripos($filename, $query) !== false) {
                    $searchResults[] = [
                        'filename' => $filename,
                        'directory' => basename(dirname($file)),
                        'full_path' => $file,
                        'size' => filesize($file)
                    ];
                }
                if (count($searchResults) >= 10) break 2;
            }
        }
        $tests['search_results'] = $searchResults;
        $tests['found_matches'] = count($searchResults);

        // Test 4b: Recursive search in all subdirectories
        function findJpgRecursive($dir, $query, &$results, $maxResults = 10) {
            $files = glob($dir . '/*');
            if ($files === false) return;
            foreach ($files as $file) {
                if (is_dir($file)) {
                    findJpgRecursive($file, $query, $results, $maxResults);
                } else {
                    $filename = basename($file);
                    if (preg_match('/\.(jpg|jpeg)$/i', $filename) && stripos($filename, $query) !== false) {
                        $results[] = [
                            'filename' => $filename,
                            'directory' => dirname($file),
                            'full_path' => $file,
                            'size' => filesize($file)
                        ];
                        if (count($results) >= $maxResults) return;
                    }
                }
                if (count($results) >= $maxResults) return;
            }
        }
        $searchResultsRecursive = [];
        findJpgRecursive('Z:/', $query, $searchResultsRecursive, 10);
        $tests['search_results_recursive'] = $searchResultsRecursive;
        $tests['found_matches_recursive'] = count($searchResultsRecursive);
    }
    
    // Test 5: Direct file check if we know the path
    $knownPaths = [
        'Z:/BE28420.jpg',
        'Z:\BE28420.jpg'
    ];
    
    $tests['direct_file_check'] = [];
    foreach ($knownPaths as $path) {
        $tests['direct_file_check'][$path] = [
            'exists' => file_exists($path),
            'readable' => is_readable($path),
            'size' => file_exists($path) ? filesize($path) : 0
        ];
    }
    
    echo json_encode([
        'success' => true,
        'query' => $query,
        'tests' => $tests,
        'php_user' => get_current_user(),
        'working_dir' => getcwd()
    ], JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'query' => $query
    ]);
}
?>