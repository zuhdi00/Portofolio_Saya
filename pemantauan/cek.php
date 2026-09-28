<?php
// Diagnostik instalasi. HAPUS file ini setelah semua OK.
header('Content-Type: text/plain; charset=utf-8');
$cfg = require __DIR__ . '/config.php';
$ok = fn($b) => $b ? "[OK]   " : "[GAGAL]";
echo "=== Cek Instalasi Pemantauan Lokasi ===\n\n";
echo $ok(version_compare(PHP_VERSION, '7.4', '>=')) . " PHP versi " . PHP_VERSION . " (" . (PHP_ZTS ? 'TS' : 'NTS') . ", " . (PHP_INT_SIZE * 8) . "-bit)\n";
echo "        php.ini: " . (php_ini_loaded_file() ?: '-') . "\n";
$drv = class_exists('PDO') ? PDO::getAvailableDrivers() : [];
echo $ok(in_array('sqlsrv', $drv)) . " Driver pdo_sqlsrv  (driver aktif: " . implode(', ', $drv) . ")\n";
echo $ok(function_exists('mb_substr')) . " Extension mbstring\n";
echo $ok(function_exists('getimagesize')) . " Extension gd / getimagesize\n";
echo $ok(function_exists('mime_content_type')) . " Extension fileinfo\n";
echo $ok(is_dir($cfg['upload_dir']) && is_writable($cfg['upload_dir'])) . " Folder uploads bisa ditulis: " . $cfg['upload_dir'] . "\n";
echo "        upload_max_filesize=" . ini_get('upload_max_filesize') . "  post_max_size=" . ini_get('post_max_size') . "  max_file_uploads=" . ini_get('max_file_uploads') . "\n";
if (in_array('sqlsrv', $drv)) {
    $dsn = "sqlsrv:Server={$cfg['db_server']};Database={$cfg['db_name']}" . ($cfg['db_trust_cert'] ? ';TrustServerCertificate=1' : '');
    try {
        $pdo = new PDO($dsn, $cfg['db_user'] ?: null, $cfg['db_user'] ? $cfg['db_pass'] : null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        echo "[OK]    Koneksi SQL Server {$cfg['db_server']} / {$cfg['db_name']}\n";
        foreach (['MonLocation', 'MonChecklistItem', 'MonReport', 'MonReportCheck', 'MonReportPhoto'] as $t) {
            $n = $pdo->query("SELECT COUNT(*) FROM sys.tables WHERE name = '$t'")->fetchColumn();
            echo $ok($n) . " Tabel dbo.$t" . ($n ? '' : '  → jalankan 0-SQL/01_schema.sql') . "\n";
        }
        $n = $pdo->query("SELECT COUNT(*) FROM sys.views WHERE name IN ('vw_MonLocationStatus','vw_MonReportList')")->fetchColumn();
        echo $ok($n == 2) . " View vw_MonLocationStatus & vw_MonReportList\n";
    } catch (Throwable $e) {
        echo "[GAGAL] Koneksi SQL Server: " . $e->getMessage() . "\n";
    }
}
echo "\nJika semua OK, buka: api.php?action=ping lalu index.html\n";
