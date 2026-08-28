<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

$query = isset($_GET['q']) ? $_GET['q'] : '';
$maxResults = 20;

// =====================================================================
// PERBAIKAN UTAMA: Gunakan UNC path langsung, BUKAN drive letter Z:
// Alasan: Drive letter di-mount per-user. PHP (SYSTEM user) tidak bisa
// akses drive yang di-mount oleh user EDP2 (sesi interaktif).
// UNC path bekerja lintas user jika credential sudah di-cache untuk SYSTEM.
// =====================================================================
$networkPath = '\\\\192.168.0.204\\Master Design';

// Fallback: coba Z: jika UNC tidak tersedia (untuk backward compatibility)
$searchRoot = $networkPath;
if (!is_dir($networkPath)) {
    // Coba koneksi eksplisit dulu
    $connectCmd = 'C:\\Windows\\System32\\net.exe use "' . $networkPath . '" /user:EDP2 PASSWORD 2>&1';
    @shell_exec($connectCmd);
    
    // Jika masih gagal, fallback ke Z:
    if (!is_dir($networkPath) && is_dir('Z:/')) {
        $searchRoot = 'Z:/';
    }
}

function findImageRecursive($dir, $query, &$results, $maxResults = 20, $depth = 0, $maxDepth = 3) {
    if ($depth > $maxDepth) return;
    if (!is_dir($dir) || !is_readable($dir)) return;
    
    $files = glob($dir . '/*');
    if ($files === false) return;
    
    $allowedExt = ['jpg','jpeg','png','gif','bmp','webp','tiff','svg'];
    
    foreach ($files as $file) {
        if (count($results) >= $maxResults) return;
        
        if (is_dir($file)) {
            $basename = basename($file);
            // Skip folder sistem Windows
            if (in_array($basename, ['.', '..', '$RECYCLE.BIN', 'System Volume Information'])) continue;
            findImageRecursive($file, $query, $results, $maxResults, $depth + 1, $maxDepth);
        } else {
            $filename = basename($file);
            $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
            if (in_array($ext, $allowedExt) && ($query === '' || stripos($filename, $query) !== false)) {
                $results[] = [
                    'filename' => $filename,
                    'directory' => dirname($file),
                    'full_path' => $file,
                    'size'      => @filesize($file)
                ];
            }
        }
    }
}

$searchResults = [];
findImageRecursive($searchRoot, $query, $searchResults, $maxResults, 0, 3);

// Build URL proxy untuk setiap gambar
foreach ($searchResults as &$img) {
    $img['url'] = 'image_proxy.php?path=' . urlencode($img['full_path']);
}
unset($img);

// Diagnostik (hapus di production jika tidak diperlukan)
$diag = [
    'search_root'       => $searchRoot,
    'is_dir_network'    => is_dir($networkPath),
    'is_readable_network' => is_readable($networkPath),
    'php_user'          => get_current_user(),
    'working_dir'       => getcwd(),
];

$response = [
    'success'     => true,
    'query'       => $query,
    'count'       => count($searchResults),
    'images'      => $searchResults,
    'diagnostics' => $diag,
];

echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
?>
