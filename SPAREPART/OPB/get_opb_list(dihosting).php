<?php
$logFile    = __DIR__ . '/../op_report/ip_log.txt'; // sesuaikan path
$defaultIP  = '36.73.249.5';
$targetPort = 8081;
$timeout    = 30;

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
// Build target URL (base)
$qs = $_SERVER['QUERY_STRING'] ?? '';
$targetUrl = "http://{$activeIP}:{$targetPort}/sparepart/opb/get_opb_list.php";

// Prepare cURL
$ch = curl_init();

// Detect incoming method and forward accordingly
$method = $_SERVER['REQUEST_METHOD'];
$headers = [];
$incomingContentType = $_SERVER['CONTENT_TYPE'] ?? '';

if ($method === 'POST') {
    // If client sent JSON body, forward raw body. Otherwise forward form-encoded POST.
    $rawBody = file_get_contents('php://input');

    if (stripos($incomingContentType, 'application/json') !== false) {
        $postFields = $rawBody;
        $headers[] = 'Content-Type: ' . $incomingContentType;
    } else {
        // Prefer parsed $_POST when available (application/x-www-form-urlencoded)
        if (!empty($_POST)) {
            $postFields = http_build_query($_POST);
        } else {
            // Fallback to raw body
            $postFields = $rawBody;
        }
        if ($incomingContentType) {
            $headers[] = 'Content-Type: ' . $incomingContentType;
        } else {
            $headers[] = 'Content-Type: application/x-www-form-urlencoded';
        }
    }

    curl_setopt($ch, CURLOPT_URL, $targetUrl . ($qs ? '?' . $qs : ''));
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields);
} else {
    // GET and other methods: pass query string along
    curl_setopt($ch, CURLOPT_URL, $targetUrl . ($qs ? '?' . $qs : ''));
}

// Common cURL options
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HEADER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);

if (!empty($headers)) {
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
}

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