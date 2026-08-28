<?php
// Proxy: forward requests to the internal ticketing service (root)
// This proxy preserves HTTP method, headers and body (JSON or form-encoded).

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, DELETE, PATCH');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$logFile    = __DIR__ . '/../op_report/ip_log.txt'; // adjust if needed
$defaultIP  = '36.73.249.5';
$targetPort = 8081;
$timeout    = 30;

// Determine active IP from log if available
$activeIP = $defaultIP;
if (file_exists($logFile)) {
    $lines = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines) {
        $lastLine = end($lines);
        if (preg_match('/IP:\s*([\d\.]+)/', $lastLine, $m)) $activeIP = trim($m[1]);
    }
}

$qs = $_SERVER['QUERY_STRING'] ?? '';
$targetUrl = "http://{$activeIP}:{$targetPort}/ticketing" . ($qs ? '?' . $qs : '');

$ch = curl_init($targetUrl);

// Build headers to forward
$forwardHeaders = [];
foreach (getallheaders() as $hn => $hv) {
    if (strtolower($hn) === 'host') continue;
    $forwardHeaders[] = $hn . ': ' . $hv;
}

$body = file_get_contents('php://input');

$opts = [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HEADER         => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_CONNECTTIMEOUT => 5,
    CURLOPT_TIMEOUT        => $timeout,
    CURLOPT_HTTPHEADER     => $forwardHeaders,
    CURLOPT_CUSTOMREQUEST  => $_SERVER['REQUEST_METHOD'],
];

if (in_array($_SERVER['REQUEST_METHOD'], ['POST','PUT','PATCH','DELETE'])) {
    $opts[CURLOPT_POSTFIELDS] = $body;
}

curl_setopt_array($ch, $opts);
$response = curl_exec($ch);

if ($response === false) {
    $curlError = curl_error($ch);
    curl_close($ch);
    http_response_code(502);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success'=>false,'message'=>"Tidak dapat terhubung ke PC: {$curlError}", 'ip_tried'=>"{$activeIP}:{$targetPort}"]);
    exit;
}

$headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
$rawHeaders = substr($response, 0, $headerSize);
$bodyOut    = substr($response, $headerSize);
$httpCode   = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

http_response_code($httpCode);

if (preg_match('/^Content-Type:\s*(.+)$/im', $rawHeaders, $m)) {
    header('Content-Type: ' . trim($m[1]));
} else {
    header('Content-Type: application/json; charset=utf-8');
}

echo $bodyOut;

?>
