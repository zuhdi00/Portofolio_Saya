<?php
// Konfigurasi folder tempat file JPG disimpan
$networkPath = '//192.168.0.204/Master Design/'; // Network path format untuk PHP
$windowsPath = '\\\\192.168.0.204\\Master Design\\'; // Windows UNC format
$allowedExtensions = ['jpg', 'jpeg', 'JPG', 'JPEG'];

// Set timeout untuk operasi network (dalam detik)
ini_set('max_execution_time', 300); // 5 menit
ini_set('default_socket_timeout', 10); // 10 detik untuk socket operations

// Fungsi untuk test network connectivity dengan timeout
function testNetworkConnection($host, $port = 445, $timeout = 5) {
    $connection = @fsockopen($host, $port, $errno, $errstr, $timeout);
    if ($connection) {
        fclose($connection);
        return true;
    }
    return false;
}

// Fungsi untuk test berbagai format path dengan timeout protection
function testNetworkAccess() {
    // Pertama test koneksi network
    if (!testNetworkConnection('192.168.0.204')) {
        return false;
    }
    
    $paths = [
        '//192.168.0.204/Master Design/',
        '\\\\192.168.0.204\\Master Design\\',
    ];
    
    foreach ($paths as $path) {
        // Set alarm untuk timeout (Unix/Linux only)
        if (function_exists('pcntl_alarm')) {
            pcntl_alarm(10); // 10 detik timeout
        }
        
        $startTime = microtime(true);
        $result = @is_dir($path);
        $endTime = microtime(true);
        
        if (function_exists('pcntl_alarm')) {
            pcntl_alarm(0); // Cancel alarm
        }
        
        // Jika operasi memakan waktu lebih dari 8 detik, skip
        if (($endTime - $startTime) > 8) {
            continue;
        }
        
        if ($result) {
            return $path;
        }
    }
    
    // Coba dengan mapped drive jika ada
    for ($drive = 'Z'; $drive >= 'M'; $drive--) { // Batasi range drive
        $drivePath = $drive . ':/';
        
        $startTime = microtime(true);
        $isDirResult = @is_dir($drivePath);
        $endTime = microtime(true);
        
        // Skip jika terlalu lama
        if (($endTime - $startTime) > 3) {
            continue;
        }
        
        if ($isDirResult) {
            // Quick test untuk memastikan ini network drive yang benar
            $testFile = @opendir($drivePath);
            if ($testFile) {
                @closedir($testFile);
                return $drivePath;
            }
        }
    }
    
    return false;
}

// Fungsi untuk mendapatkan semua file JPG dari network drive dengan timeout protection
function getJpgFiles($dir, $allowedExt, $maxFiles = 1000) {
    $files = [];
    $fileCount = 0;
    
    // Set timeout untuk operasi ini
    $startTime = time();
    $maxTime = 60; // 60 detik maksimum untuk scan folder
    
    // Coba akses dengan error suppression
    $handle = @opendir($dir);
    if ($handle === false) {
        return $files; // Return empty array jika tidak bisa akses
    }
    
    while (($file = @readdir($handle)) !== false && $fileCount < $maxFiles) {
        // Check timeout
        if ((time() - $startTime) > $maxTime) {
            break;
        }
        
        if ($file != "." && $file != "..") {
            $fullPath = $dir . $file;
            
            // Quick check tanpa @filesize yang lambat
            if (@is_file($fullPath)) {
                $extension = pathinfo($file, PATHINFO_EXTENSION);
                if (in_array($extension, $allowedExt)) {
                    // Dapatkan info file dengan timeout protection
                    $fileSize = 0;
                    $fileTime = time();
                    
                    // Hanya ambil filesize jika tidak memakan waktu lama
                    $sizeStartTime = microtime(true);
                    $tempSize = @filesize($fullPath);
                    $sizeEndTime = microtime(true);
                    
                    if (($sizeEndTime - $sizeStartTime) < 1 && $tempSize !== false) { // 1 detik timeout
                        $fileSize = $tempSize;
                    }
                    
                    // Ambil modified time
                    $tempTime = @filemtime($fullPath);
                    if ($tempTime !== false) {
                        $fileTime = $tempTime;
                    }
                    
                    $files[] = [
                        'name' => $file,
                        'path' => $fullPath,
                        'size' => $fileSize,
                        'modified' => $fileTime,
                        'webpath' => 'show_image.php?file=' . urlencode($file)
                    ];
                    
                    $fileCount++;
                }
            }
        }
    }
    @closedir($handle);
    
    return $files;
}

// Fungsi untuk scan folder secara rekursif dengan timeout protection
function scanFolderRecursive($dir, $allowedExt, $maxDepth = 1, $currentDepth = 0, $maxFiles = 500) {
    $files = [];
    static $totalFiles = 0;
    static $startTime;
    
    if ($currentDepth === 0) {
        $startTime = time();
        $totalFiles = 0;
    }
    
    // Timeout protection
    if ($currentDepth >= $maxDepth || $totalFiles >= $maxFiles || (time() - $startTime) > 90) {
        return $files;
    }
    
    $handle = @opendir($dir);
    if ($handle === false) {
        return $files;
    }
    
    while (($item = @readdir($handle)) !== false && $totalFiles < $maxFiles) {
        // Check timeout setiap iterasi
        if ((time() - $startTime) > 90) { // 90 detik maksimum
            break;
        }
        
        if ($item != "." && $item != "..") {
            $fullPath = $dir . $item;
            
            // Quick file check
            $checkStartTime = microtime(true);
            $isFile = @is_file($fullPath);
            $isDir = false;
            if (!$isFile) {
                $isDir = @is_dir($fullPath);
            }
            $checkEndTime = microtime(true);
            
            // Skip jika checking memakan waktu terlalu lama
            if (($checkEndTime - $checkStartTime) > 2) {
                continue;
            }
            
            if ($isFile) {
                $extension = pathinfo($item, PATHINFO_EXTENSION);
                if (in_array($extension, $allowedExt)) {
                    $files[] = [
                        'name' => $item,
                        'path' => $fullPath,
                        'size' => @filesize($fullPath) ?: 0,
                        'modified' => @filemtime($fullPath) ?: time(),
                        'webpath' => 'show_image.php?file=' . urlencode($item) . '&path=' . urlencode(dirname($fullPath))
                    ];
                    $totalFiles++;
                }
            } elseif ($isDir && $currentDepth < $maxDepth) {
                // Scan subdirectory
                $subFiles = scanFolderRecursive($fullPath . '/', $allowedExt, $maxDepth, $currentDepth + 1, $maxFiles);
                $files = array_merge($files, $subFiles);
            }
        }
    }
    @closedir($handle);
    
    return $files;
}

// Proses pencarian
$searchQuery = isset($_GET['search']) ? trim($_GET['search']) : '';
$connectionStatus = '';
$useRecursive = isset($_GET['recursive']) ? true : false;
$allFiles = [];

// Test koneksi ke network drive dengan timeout
$accessiblePath = false;

try {
    // Set timeout untuk seluruh proses
    set_time_limit(240); // 4 menit
    
    $connectionStart = microtime(true);
    $accessiblePath = testNetworkAccess();
    $connectionEnd = microtime(true);
    
    if ($accessiblePath) {
        $connectionTime = round($connectionEnd - $connectionStart, 2);
        $connectionStatus = '<div class="alert alert-success">
            <i class="fas fa-check-circle"></i> Terhubung ke network drive: ' . htmlspecialchars($accessiblePath) . ' 
            <small class="text-muted">(' . $connectionTime . 's)</small>
        </div>';
        
        // Scan files dengan limit
        $scanStart = microtime(true);
        if ($useRecursive) {
            $allFiles = scanFolderRecursive($accessiblePath, $allowedExtensions, 2, 0, 500);
        } else {
            $allFiles = getJpgFiles($accessiblePath, $allowedExtensions, 1000);
        }
        $scanEnd = microtime(true);
        
        $scanTime = round($scanEnd - $scanStart, 2);
        $connectionStatus .= '<div class="alert alert-info">
            <i class="fas fa-clock"></i> Scan selesai dalam ' . $scanTime . 's. 
            Ditemukan ' . count($allFiles) . ' file.
        </div>';
        
    } else {
        $connectionStatus = '<div class="alert alert-danger">
            <h6><i class="fas fa-exclamation-triangle"></i> Tidak dapat mengakses network drive</h6>
            <p class="mb-2"><strong>Kemungkinan penyebab:</strong></p>
            <ul class="mb-2">
                <li>Network drive tidak terhubung (192.168.0.204)</li>
                <li>SMB/CIFS service tidak running</li>
                <li>Firewall memblokir akses</li>
                <li>Path "Master Design" tidak exist atau tidak accessible</li>
                <li>PHP tidak memiliki permission untuk akses network share</li>
            </ul>
            <p class="mb-0"><strong>Solusi cepat:</strong></p>
            <ol class="mb-0">
                <li>Cek koneksi: <code>ping 192.168.0.204</code></li>
                <li>Test akses manual: buka <code>\\\\192.168.0.204\\Master Design</code> di Explorer</li>
                <li>Mount sebagai drive: <code>net use Z: "\\\\192.168.0.204\\Master Design"</code></li>
                <li>Restart Apache service setelah mounting</li>
            </ol>
        </div>';
    }
    
} catch (Exception $e) {
    $connectionStatus = '<div class="alert alert-warning">
        <h6><i class="fas fa-exclamation-circle"></i> Error saat mengakses network drive</h6>
        <p>Error: ' . htmlspecialchars($e->getMessage()) . '</p>
        <p>Coba refresh halaman atau check network connection.</p>
    </div>';
    $allFiles = [];
}

// Filter file berdasarkan pencarian
$filteredFiles = [];
if (!empty($searchQuery)) {
    foreach ($allFiles as $file) {
        if (stripos($file['name'], $searchQuery) !== false) {
            $filteredFiles[] = $file;
        }
    }
} else {
    $filteredFiles = $allFiles;
}

?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pencari File JPG</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .main-container {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            padding: 2rem;
            margin: 2rem auto;
            backdrop-filter: blur(10px);
        }
        
        .header {
            text-align: center;
            margin-bottom: 2rem;
            color: #333;
        }
        
        .header h1 {
            font-weight: 700;
            margin-bottom: 0.5rem;
            background: linear-gradient(45deg, #667eea, #764ba2);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        
        .search-box {
            background: white;
            border-radius: 15px;
            padding: 1.5rem;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
        }
        
        .image-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-top: 2rem;
        }
        
        .image-card {
            background: white;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
            position: relative;
        }
        
        .image-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 30px rgba(0,0,0,0.2);
        }
        
        .image-card img {
            width: 100%;
            height: 200px;
            object-fit: cover;
            transition: transform 0.3s ease;
        }
        
        .image-card:hover img {
            transform: scale(1.05);
        }
        
        .image-info {
            padding: 1rem;
        }
        
        .image-name {
            font-weight: 600;
            color: #333;
            margin-bottom: 0.5rem;
            font-size: 0.9rem;
            word-break: break-all;
        }
        
        .image-meta {
            font-size: 0.8rem;
            color: #666;
        }
        
        .btn-custom {
            background: linear-gradient(45deg, #667eea, #764ba2);
            border: none;
            border-radius: 10px;
            color: white;
            padding: 0.75rem 1.5rem;
            transition: all 0.3s ease;
        }
        
        .btn-custom:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
            color: white;
        }
        
        .form-control {
            border-radius: 10px;
            border: 2px solid #e9ecef;
            padding: 0.75rem 1rem;
            transition: all 0.3s ease;
        }
        
        .form-control:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }
        
        .alert {
            border-radius: 10px;
            border: none;
        }
        
        .zoom-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.9);
            display: none;
            justify-content: center;
            align-items: center;
            z-index: 1000;
            cursor: pointer;
        }
        
        .zoom-image {
            max-width: 90%;
            max-height: 90%;
            border-radius: 10px;
        }
        
        .loading-spinner {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid #f3f3f3;
            border-top: 3px solid #667eea;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="main-container">
            <div class="header">
                <h1><i class="fas fa-network-wired"></i> Pencari File JPG - Network Drive</h1>
                <p class="text-muted">Cari file gambar JPG di \\192.168.0.204\Master Design</p>
            </div>
            
            <?php echo $connectionStatus; ?>
            
            <!-- Search Section -->
            <div class="search-box">
                <form method="GET" class="row g-3" id="searchForm">
                    <div class="col-md-7">
                        <input type="text" name="search" class="form-control" 
                               placeholder="Masukkan nama file untuk mencari..." 
                               value="<?php echo htmlspecialchars($searchQuery); ?>">
                    </div>
                    <div class="col-md-3">
                        <button type="submit" class="btn btn-custom w-100" id="searchBtn">
                            <i class="fas fa-search"></i> Cari
                        </button>
                    </div>
                    <div class="col-md-2">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="recursive" id="recursive" 
                                   <?php echo $useRecursive ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="recursive">
                                Scan Subfolder
                            </label>
                        </div>
                    </div>
                </form>
                
                <?php if (!empty($searchQuery)): ?>
                    <div class="mt-3">
                        <a href="?" class="btn btn-outline-secondary">
                            <i class="fas fa-times"></i> Clear Search
                        </a>
                        <span class="ms-3 text-muted">
                            Ditemukan <?php echo count($filteredFiles); ?> file untuk "<?php echo htmlspecialchars($searchQuery); ?>"
                        </span>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Results Section -->
            <div class="row">
                <div class="col-12">
                    <h4><i class="fas fa-folder-open"></i> File JPG 
                        <span class="badge bg-primary"><?php echo count($filteredFiles); ?></span>
                    </h4>
                    
                    <?php if (empty($filteredFiles) && $accessiblePath): ?>
                        <div class="text-center py-5">
                            <i class="fas fa-images fa-3x text-muted mb-3"></i>
                            <h5 class="text-muted">
                                <?php echo !empty($searchQuery) ? 'Tidak ada file yang ditemukan' : 'Belum ada file JPG'; ?>
                            </h5>
                            <p class="text-muted">
                                <?php echo !empty($searchQuery) ? 'Coba kata kunci lain' : 'Tidak ada file JPG ditemukan di network drive'; ?>
                            </p>
                        </div>
                    <?php elseif (!empty($filteredFiles)): ?>
                        <div class="image-grid">
                            <?php foreach ($filteredFiles as $file): ?>
                                <div class="image-card">
                                    <img src="<?php echo htmlspecialchars($file['webpath']); ?>" 
                                         alt="<?php echo htmlspecialchars($file['name']); ?>"
                                         onclick="showZoom('<?php echo htmlspecialchars($file['webpath']); ?>')"
                                         loading="lazy"
                                         onerror="this.src='data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMjAwIiBoZWlnaHQ9IjIwMCIgdmlld0JveD0iMCAwIDIwMCAyMDAiIGZpbGw9Im5vbmUiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyI+CjxyZWN0IHdpZHRoPSIyMDAiIGhlaWdodD0iMjAwIiBmaWxsPSIjZjhmOWZhIi8+CjxwYXRoIGQ9Ik0xMDAgNTBDMTI3LjYxNCA1MCAyMzcuNSA2MS4zODU4IDEzNy41IDEwMEMxMjcuNTAwIDEzOC42MTQgMTE2LjExNCAxMzggODcuNSAxMzBDNTguODg1OCAxMzAgNDcuNSAxMTguNjE0IDQ3LjUgODBDNDcuNSA2MS4zODU4IDU4Ljg4NTggNTAgODAgNTBIMTAwWiIgZmlsbD0iI2U5ZWNlZiIvPgo8Y2lyY2xlIGN4PSI3NSIgY3k9Ijc1IiByPSIxMCIgZmlsbD0iI2RlZTJlNiIvPgo8cGF0aCBkPSJNMTMwIDEyMEwxMTAgMTAwTDkwIDEyMEw4MCAxMTBMNjAgMTMwSDE3MFYxMzBMMTMwIDEyMFoiIGZpbGw9IiNkZWUyZTYiLz4KPC9zdmc+Cg=='">
                                    <div class="image-info">
                                        <div class="image-name" title="<?php echo htmlspecialchars($file['name']); ?>">
                                            <?php echo htmlspecialchars($file['name']); ?>
                                        </div>
                                        <div class="image-meta">
                                            <i class="fas fa-weight-hanging"></i> <?php echo $file['size'] > 0 ? round($file['size']/1024, 2) . ' KB' : 'Unknown'; ?><br>
                                            <i class="fas fa-calendar"></i> <?php echo date('d/m/Y H:i', $file['modified']); ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Zoom Overlay -->
    <div class="zoom-overlay" id="zoomOverlay" onclick="hideZoom()">
        <img src="" alt="" class="zoom-image" id="zoomImage">
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function showZoom(imagePath) {
            document.getElementById('zoomImage').src = imagePath;
            document.getElementById('zoomOverlay').style.display = 'flex';
        }
        
        function hideZoom() {
            document.getElementById('zoomOverlay').style.display = 'none';
        }
        
        // Show loading state when form is submitted
        document.getElementById('searchForm').addEventListener('submit', function() {
            const btn = document.getElementById('searchBtn');
            btn.innerHTML = '<span class="loading-spinner"></span> Mencari...';
            btn.disabled = true;
        });
        
        // Auto-hide success alerts after 10 seconds
        setTimeout(function() {
            const alerts = document.querySelectorAll('.alert-success, .alert-info');
            alerts.forEach(alert => {
                alert.style.transition = 'opacity 0.5s';
                alert.style.opacity = '0';
                setTimeout(() => alert.remove(), 500);
            });
        }, 10000);
    </script>
</body>
</html>