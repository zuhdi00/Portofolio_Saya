<?php
header('Content-Type: text/html; charset=utf-8');

echo "<h2>Testing Proxy & API Routing</h2>";

echo "<h3>Allowed Paths di proxy_mobile_v2.php:</h3>";
$allowed_paths = [
    'api_get_item_detail_v3.php',
    'api_update_qty_v2.php',
    'api_update_rak_v2.php',
    'api_update_posted_v2.php',
    'api_approve_stb_v2.php',
    'api_approve_stb_check_v2.php',
    'api_get_op_detail.php',
    'api_upload_to_wipv2.php',
    'api_delete_barcode_v2.php',
];

echo "<ul>";
foreach ($allowed_paths as $path) {
    $exists = file_exists($path) ? "✓ EXISTS" : "✗ MISSING";
    echo "<li>" . $path . " - " . $exists . "</li>";
}
echo "</ul>";

echo "<h3>Test Files in This Directory:</h3>";
echo "<pre>";
$files = glob('api_*.php');
foreach ($files as $file) {
    echo $file . "\n";
}
echo "</pre>";

echo "<h3>Recent PHP Errors (if any):</h3>";
$log_file = ini_get('error_log');
if ($log_file && file_exists($log_file)) {
    echo "<p>Log file: " . $log_file . "</p>";
    $lines = file($log_file);
    $recent = array_slice($lines, -20);
    echo "<pre>";
    foreach ($recent as $line) {
        echo htmlspecialchars($line);
    }
    echo "</pre>";
} else {
    echo "<p>No error log file found</p>";
}
?>
