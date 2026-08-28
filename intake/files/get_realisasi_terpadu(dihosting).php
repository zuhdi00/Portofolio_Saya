<?php
$logFile    = __DIR__ . '/../op_report/ip_log.txt'; // sesuaikan path untuk IP log
$defaultIP  = '36.73.249.5';
$targetPort = 8081;
$timeout    = 3000000;

// Proxy debug log (lokal di folder ini)
$proxyLog = __DIR__ . '/proxy_dihosting.log';

function proxy_log($msg) {
    global $proxyLog;
    $line = date('Y-m-d H:i:s') . ' ' . $msg . PHP_EOL;
    @file_put_contents($proxyLog, $line, FILE_APPEND | LOCK_EX);
}

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

// Log probe
proxy_log("Probe request -> target={$targetUrl}, remote_addr={$_SERVER['REMOTE_ADDR']}");

$ch = curl_init($targetUrl);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HEADER         => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_CONNECTTIMEOUT => 5,
    CURLOPT_TIMEOUT        => $timeout,
]);

// execute
$response = curl_exec($ch);

if ($response === false) {
    $curlErrNo = curl_errno($ch);
    $curlErr = curl_error($ch);
    proxy_log("cURL ERROR -> errno={$curlErrNo}, err='${curlErr}', tried={$activeIP}:{$targetPort}");
    curl_close($ch);
    http_response_code(502);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => false,
        'message' => "Tidak dapat terhubung ke PC: {$curlErr}",
        'ip_tried' => "{$activeIP}:{$targetPort}"
    ]);
    exit;
}

// If response exists, log HTTP status for debugging
$headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
$httpCode   = curl_getinfo($ch, CURLINFO_HTTP_CODE);
proxy_log("cURL OK -> http_code={$httpCode}, header_size={$headerSize}, tried={$activeIP}:{$targetPort}");

$headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
$rawHeaders = substr($response, 0, $headerSize);
$body       = substr($response, $headerSize);
$httpCode   = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

// If server returned error code, log truncated body for diagnosis
if ($httpCode >= 400) {
    $snippet = substr($body, 0, 2000);
    proxy_log("Upstream returned http={$httpCode}, body_snippet=" . preg_replace('/\s+/', ' ', $snippet));
}

http_response_code($httpCode);

if (preg_match('/^Content-Type:\s*(.+)$/im', $rawHeaders, $m)) {
    header('Content-Type: ' . trim($m[1]));
} else {
    header('Content-Type: application/json; charset=utf-8');
}

echo $body;
?>