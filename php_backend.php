<?php
// search_images.php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Konfigurasi path
$networkPath = '//192.168.0.204/Master Design'; // Sesuaikan dengan drive letter yang di-mount
$webPath = 'images/'; // Path relatif untuk web access

function searchImageFiles($searchTerm, $directory, $webPath) {
    $results = [];
    
    if (!is_dir($directory)) {
        return ['success' => false, 'message' => 'Directory tidak ditemukan. Pastikan network drive sudah di-mount.'];
    }
    
    try {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, RecursiveDirectoryIterator::SKIP_DOTS)
        );
        
        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $filename = $file->getFilename();
                $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
                $nameWithoutExt = pathinfo($filename, PATHINFO_FILENAME);
                
                // Check if it's a JPG file and matches search term
                if ($extension === 'jpg' || $extension === 'jpeg') {
                    if (empty($searchTerm) || stripos($nameWithoutExt, $searchTerm) !== false) {
                        $relativePath = str_replace($directory, '', $file->getPathname());
                        $relativePath = str_replace('\\', '/', $relativePath);
                        
                        $results[] = [
                            'name' => $filename,
                            'url' => $webPath . ltrim($relativePath, '/'),
                            'size' => formatBytes($file->getSize()),
                            'path' => $file->getPathname()
                        ];
                    }
                }
            }
        }
        
        return ['success' => true, 'images' => $results];
        
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Error scanning directory: ' . $e->getMessage()];
    }
}

function formatBytes($size, $precision = 2) {
    $base = log($size, 1024);
    $suffixes = array('B', 'KB', 'MB', 'GB', 'TB');
    return round(pow(1024, $base - floor($base)), $precision) . ' ' . $suffixes[floor($base)];
}

// Handle POST request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $searchTerm = isset($_POST['search']) ? trim($_POST['search']) : '';
    
    if (empty($searchTerm)) {
        echo json_encode(['success' => false, 'message' => 'Search term is required']);
        exit;
    }
    
    $result = searchImageFiles($searchTerm, $networkPath, $webPath);
    echo json_encode($result);
} else {
    echo json_encode(['success' => false, 'message' => 'Only POST method allowed']);
}
?>

<?php
// get_all_images.php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');

// Konfigurasi path
$networkPath = 'Z:/'; // Sesuaikan dengan drive letter yang di-mount
$webPath = 'images/'; // Path relatif untuk web access

function getAllImageFiles($directory, $webPath, $limit = 100) {
    $results = [];
    $count = 0;
    
    if (!is_dir($directory)) {
        return ['success' => false, 'message' => 'Directory tidak ditemukan. Pastikan network drive sudah di-mount ke drive Z:'];
    }
    
    try {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, RecursiveDirectoryIterator::SKIP_DOTS)
        );
        
        foreach ($iterator as $file) {
            if ($count >= $limit) break;
            
            if ($file->isFile()) {
                $filename = $file->getFilename();
                $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
                
                // Check if it's a JPG file
                if ($extension === 'jpg' || $extension === 'jpeg') {
                    $relativePath = str_replace($directory, '', $file->getPathname());
                    $relativePath = str_replace('\\', '/', $relativePath);
                    
                    $results[] = [
                        'name' => $filename,
                        'url' => $webPath . ltrim($relativePath, '/'),
                        'size' => formatBytes($file->getSize()),
                        'path' => $file->getPathname()
                    ];
                    
                    $count++;
                }
            }
        }
        
        return ['success' => true, 'images' => $results, 'total' => $count];
        
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Error scanning directory: ' . $e->getMessage()];
    }
}

function formatBytes($size, $precision = 2) {
    if ($size == 0) return '0 B';
    $base = log($size, 1024);
    $suffixes = array('B', 'KB', 'MB', 'GB', 'TB');
    return round(pow(1024, $base - floor($base)), $precision) . ' ' . $suffixes[floor($base)];
}

// Handle GET request
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 100;
    $result = getAllImageFiles($networkPath, $webPath, $limit);
    echo json_encode($result);
} else {
    echo json_encode(['success' => false, 'message' => 'Only GET method allowed']);
}
?>

<?php
// config.php - File konfigurasi
<?php
// Konfigurasi Path
define('NETWORK_DRIVE', 'Z:/'); // Drive letter yang di-mount
define('NETWORK_PATH', '\\\\192.168.0.204\\Master Design'); // Original network path
define('WEB_PATH', 'images/'); // Path untuk akses web
define('MAX_IMAGES', 200); // Maksimum gambar yang ditampilkan

// Fungsi untuk check koneksi network
function checkNetworkConnection() {
    return is_dir(NETWORK_DRIVE);
}

// Fungsi untuk get network status
function getNetworkStatus() {
    if (checkNetworkConnection()) {
        return ['connected' => true, 'message' => 'Network drive connected'];
    } else {
        return ['connected' => false, 'message' => 'Network drive not mounted. Please run mount_network.bat'];
    }
}
?>

<?php
// status.php - Check network status
header('Content-Type: application/json');
require_once 'config.php';

$status = getNetworkStatus();
echo json_encode($status);
?>