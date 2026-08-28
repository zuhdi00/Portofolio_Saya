<?php
$logFile    = __DIR__ . '/../op_report/ip_log.txt'; // sesuaikan path untuk IP log
$defaultIP  = '36.73.249.5';
$targetPort = 8081;

// ─── FIX 1: Naikkan timeout cURL ────────────────────────────────────────────
// Dulu: 3000000 µs = 3 detik → terlalu cepat untuk query besar
// Sekarang: 120 detik (2 menit), cukup untuk dataset besar
// Increase timeouts: allow longer transfers for big queries
$curlTimeout = 300;  // detik
$curlConnectTimeout = 30; // detik untuk koneksi awal

// ─── FIX 2: Naikkan PHP execution time & memory untuk export besar ───────────
// Allow longer execution & more memory for proxy handling large responses
@ini_set('max_execution_time', 600);   // 10 menit
@ini_set('memory_limit', '1024M');

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

// ─── FIX 3: Keep-Alive & buffer control ─────────────────────────────────────
// Hosting sering membatasi idle connection. Kirim header agar koneksi tetap terbuka.
header('Connection: keep-alive');
header('X-Accel-Buffering: no'); // matikan buffering nginx jika ada

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

$qs = $_SERVER['QUERY_STRING'] ?? '';
$targetUrl = "http://{$activeIP}:{$targetPort}/intake/get_realisasi_terpadu.php";
if ($qs) {
    $targetUrl .= '?' . $qs;
}

proxy_log("Probe request -> target={$targetUrl}, remote_addr={$_SERVER['REMOTE_ADDR']}");

$ch = curl_init($targetUrl);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HEADER         => true,
    CURLOPT_FOLLOWLOCATION => true,

    // ─── FIX 4: Gunakan timeout dalam detik (bukan microseconds) ─────────────
    CURLOPT_CONNECTTIMEOUT => $curlConnectTimeout,
    CURLOPT_TIMEOUT        => $curlTimeout,
    // Keep-alive at TCP level
    CURLOPT_TCP_KEEPALIVE  => 1,
    CURLOPT_TCP_KEEPIDLE   => 30,
    CURLOPT_TCP_KEEPINTVL  => 10,

    // ─── FIX 5: Encoding agar response tidak rusak ───────────────────────────
    CURLOPT_ENCODING       => 'gzip, deflate',

    // ─── FIX 6: Tambah User-Agent agar tidak diblokir firewall lokal ─────────
    CURLOPT_USERAGENT      => 'ProxyDihosting/1.0',
]);

$response = curl_exec($ch);

if ($response === false) {
    $curlErrNo = curl_errno($ch);
    $curlErr   = curl_error($ch);
    proxy_log("cURL ERROR -> errno={$curlErrNo}, err='{$curlErr}', tried={$activeIP}:{$targetPort}");
    curl_close($ch);

    http_response_code(502);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success'  => false,
        'message'  => "Tidak dapat terhubung ke PC: {$curlErr}",
        'ip_tried' => "{$activeIP}:{$targetPort}"
    ]);
    exit;
}

$headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
$httpCode   = curl_getinfo($ch, CURLINFO_HTTP_CODE);
proxy_log("cURL OK -> http_code={$httpCode}, header_size={$headerSize}, tried={$activeIP}:{$targetPort}");

$rawHeaders = substr($response, 0, $headerSize);
$body       = substr($response, $headerSize);
curl_close($ch);

// Jika server lokal mengembalikan error, log potongan body untuk diagnosis
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
