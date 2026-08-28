<?php

header("Access-Control-Allow-Origin: https://supracor.co.id");
header("Access-Control-Allow-Origin: http://supracor.co.id"); // jika belum SSL
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Accept");
header("Access-Control-Max-Age: 86400"); // 24 hours
//isi test_connection_.php
// Handle preflight OPTIONS request

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

header('Content-Type: application/json');

// Test script untuk verifikasi koneksi network
$tests = [];

// Test 1: PHP Configuration
$tests['php_info'] = [
    'version' => PHP_VERSION,
    'memory_limit' => ini_get('memory_limit'),
    'max_execution_time' => ini_get('max_execution_time'),
    'gd_enabled' => extension_loaded('gd'),
    'allow_url_fopen' => ini_get('allow_url_fopen') ? 'Yes' : 'No'
];

// Test 2: Directory Permissions
$localImagesPath = __DIR__ . '/images/';
$tests['directory_permissions'] = [
    'images_dir_exists' => is_dir($localImagesPath),
    'images_dir_writable' => is_writable($localImagesPath) || is_writable(__DIR__),
    'current_dir_writable' => is_writable(__DIR__)
];

// Test 3: Network Path Access
$networkPaths = [
    'unc_path' => '//192.168.0.204/Master Design',
    'mounted_drive' => 'Z:/'
];

foreach ($networkPaths as $key => $path) {
    $tests['network_access'][$key] = [
        'path' => $path,
        'accessible' => false,
        'files_found' => 0,
        'error' => null
    ];
    
    try {
        if (is_dir($path)) {
            $tests['network_access'][$key]['accessible'] = true;
            
            // Count JPG files in root directory
            $files = glob($path . '/*.{jpg,jpeg,JPG,JPEG}', GLOB_BRACE);
            $tests['network_access'][$key]['files_found'] = count($files);
            
            // Sample files
            $tests['network_access'][$key]['sample_files'] = array_slice($files, 0, 5);
        }
    } catch (Exception $e) {
        $tests['network_access'][$key]['error'] = $e->getMessage();
    }
}

// Test 4: Web Server Configuration
$tests['web_server'] = [
    'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
    'document_root' => $_SERVER['DOCUMENT_ROOT'] ?? 'Unknown',
    'script_filename' => $_SERVER['SCRIPT_FILENAME'] ?? 'Unknown',
    'mod_rewrite' => function_exists('apache_get_modules') ? 
        in_array('mod_rewrite', apache_get_modules()) : 'Unknown'
];

// Test 5: CORS Headers Test
$tests['cors_test'] = [
    'access_control_allow_origin' => 'Set via header()',
    'access_control_allow_methods' => 'GET, POST, OPTIONS',
    'access_control_allow_headers' => 'Content-Type'
];

// Test 6: Sample Image Processing
$testImagePath = null;
foreach ($networkPaths as $path) {
    if (is_dir($path)) {
        $sampleFiles = glob($path . '/*.{jpg,jpeg}', GLOB_BRACE);
        if (!empty($sampleFiles)) {
            $testImagePath = $sampleFiles[0];
            break;
        }
    }
}

$tests['image_processing'] = [
    'test_image_found' => $testImagePath !== null,
    'test_image_path' => $testImagePath
];

if ($testImagePath) {
    try {
        $imageInfo = getimagesize($testImagePath);
        $tests['image_processing']['getimagesize_works'] = true;
        $tests['image_processing']['sample_dimensions'] = [
            'width' => $imageInfo[0],
            'height' => $imageInfo[1]
        ];
    } catch (Exception $e) {
        $tests['image_processing']['getimagesize_works'] = false;
        $tests['image_processing']['error'] = $e->getMessage();
    }
}

// Test 7: Write Test
try {
    if (!is_dir($localImagesPath)) {
        mkdir($localImagesPath, 0755, true);
    }
    
    $testFile = $localImagesPath . 'test_' . time() . '.txt';
    $writeSuccess = file_put_contents($testFile, 'test') !== false;
    
    if ($writeSuccess && file_exists($testFile)) {
        $tests['write_test'] = [
            'success' => true,
            'test_file' => $testFile
        ];
        unlink($testFile); // Cleanup
    } else {
        $tests['write_test'] = [
            'success' => false,
            'error' => 'Cannot write to images directory'
        ];
    }
} catch (Exception $e) {
    $tests['write_test'] = [
        'success' => false,
        'error' => $e->getMessage()
    ];
}

// Summary and Recommendations
$summary = [
    'overall_status' => 'unknown',
    'recommendations' => [],
    'critical_issues' => [],
    'warnings' => []
];

// Check critical issues
if (!$tests['php_info']['gd_enabled']) {
    $summary['critical_issues'][] = 'GD extension not enabled - required for image processing';
}

if (!$tests['directory_permissions']['images_dir_writable'] && !$tests['directory_permissions']['current_dir_writable']) {
    $summary['critical_issues'][] = 'Cannot write to images directory - check permissions';
}

if (!$tests['network_access']['unc_path']['accessible'] && !$tests['network_access']['mounted_drive']['accessible']) {
    $summary['critical_issues'][] = 'No network path accessible - mount network drive first';
}

// Check warnings
if ($tests['php_info']['memory_limit'] === '128M') {
    $summary['warnings'][] = 'Memory limit is 128M - consider increasing for better performance';
}

if (!$tests['write_test']['success']) {
    $summary['warnings'][] = 'Write test failed - may affect image caching';
}

// Determine overall status
if (empty($summary['critical_issues'])) {
    if (empty($summary['warnings'])) {
        $summary['overall_status'] = 'excellent';
        $summary['recommendations'][] = 'All systems ready! You can proceed with the application.';
    } else {
        $summary['overall_status'] = 'good';
        $summary['recommendations'][] = 'System is functional with minor issues.';
    }
} else {
    $summary['overall_status'] = 'issues';
    $summary['recommendations'][] = 'Fix critical issues before using the application.';
}

// Specific recommendations
if (!$tests['network_access']['mounted_drive']['accessible'] && $tests['network_access']['unc_path']['accessible']) {
    $summary['recommendations'][] = 'Consider mounting the network drive for better performance.';
}

if ($tests['network_access']['mounted_drive']['accessible']) {
    $summary['recommendations'][] = 'Use search_images_alt.php (mounted drive version) for optimal performance.';
}

echo json_encode([
    'timestamp' => date('Y-m-d H:i:s'),
    'tests' => $tests,
    'summary' => $summary
], JSON_PRETTY_PRINT);
?>