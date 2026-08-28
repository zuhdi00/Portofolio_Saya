<?php
$logFile    = __DIR__ . '/../op_report/ip_log.txt'; // sesuaikan path
$defaultIP  = '36.73.249.5';
$targetPort = 8081;
$timeout    = 3000000;

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Baca IP terbaru
$activeIP = $defaultIP;
if (file_exists($logFile)) {
    $lines = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines) {
        $lastLine = end($lines);
        if (preg_match('/IP:\s*([\d\.]+)/', $lastLine, $m)) {
            $activeIP = trim($m[1]);
        }
    }
}

// ✅ Langsung ke root, tanpa subfolder
$qs = $_SERVER['QUERY_STRING'] ?? '';
$targetUrl = "http://{$activeIP}:{$targetPort}/intake/get_realisasi_terpadu.php";
if ($qs) {
    $targetUrl .= '?' . $qs;
}

$ch = curl_init($targetUrl);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HEADER         => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_CONNECTTIMEOUT => 5,
    CURLOPT_TIMEOUT        => $timeout,
]);

$response = curl_exec($ch);

if ($response === false) {
    $curlError = curl_error($ch);
    curl_close($ch);
    http_response_code(502);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => false,
        'message' => "Tidak dapat terhubung ke PC: {$curlError}",
        'ip_tried' => "{$activeIP}:{$targetPort}"
    ]);
    exit;
}

$headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
$rawHeaders = substr($response, 0, $headerSize);
$body       = substr($response, $headerSize);
$httpCode   = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

http_response_code($httpCode);

if (preg_match('/^Content-Type:\s*(.+)$/im', $rawHeaders, $m)) {
    header('Content-Type: ' . trim($m[1]));
} else {
    header('Content-Type: application/json; charset=utf-8');
}

echo $body;
?>