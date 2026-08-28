<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

$query = isset($_GET['q']) ? $_GET['q'] : '';
$maxResults = 20;



function findImageRecursive($dir, $query, &$results, $maxResults = 20, $depth = 0, $maxDepth = 3) {
    if ($depth > $maxDepth) return;
    $files = glob($dir . '/*');
    if ($files === false) return;
    $allowedExt = ['jpg','jpeg','png','gif','bmp','webp','tiff','svg'];
    foreach ($files as $file) {
        if (is_dir($file)) {
            // Skip system folders (opsional, bisa ditambah)
            $basename = basename($file);
            if ($basename === '.' || $basename === '..' || $basename === '$RECYCLE.BIN' || $basename === 'System Volume Information') continue;
            findImageRecursive($file, $query, $results, $maxResults, $depth + 1, $maxDepth);
        } else {
            $filename = basename($file);
            $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
            if (in_array($ext, $allowedExt) && ($query === '' || stripos($filename, $query) !== false)) {
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


$searchResults = [];
// Batasi pencarian maksimal 3 level folder
findImageRecursive('Z:/', $query, $searchResults, $maxResults, 0, 3);

// Build URLs for image_proxy.php
foreach ($searchResults as &$img) {
    $img['url'] = 'image_proxy.php?path=' . urlencode($img['full_path']);
}

$response = [
    'success' => true,
    'query' => $query,
    'count' => count($searchResults),
    'images' => $searchResults,
    'php_user' => get_current_user(),
    'working_dir' => getcwd()
];

echo json_encode($response, JSON_PRETTY_PRINT);
?>
