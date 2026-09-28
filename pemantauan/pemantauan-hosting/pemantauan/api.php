<?php
// =====================================================================
// PROXY (hosting publik) → API di PC internal.  Pola sama dengan EDP/index.php
// Taruh file ini di folder yang sama dengan index.html & barcode.html di hosting.
// =====================================================================

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Admin-Pin');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

$logFile    = __DIR__ . '/../op_report/ip_log.txt';   // sama seperti EDP, sesuaikan jika perlu
$defaultIP  = '36.81.174.114';   // IP publik PC internal (dipakai jika ip_log.txt tidak ada)
$targetPort = 8081;
$targetPath = '/pemantauan/api.php';
$timeout    = 60;                                       // upload foto butuh waktu lebih

$activeIP = $defaultIP;
if (file_exists($logFile)) {
    $lines = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines && preg_match('/IP:\s*([\d\.]+)/', end($lines), $m)) $activeIP = trim($m[1]);
}

$qs = $_SERVER['QUERY_STRING'] ?? '';
$targetUrl = "http://{$activeIP}:{$targetPort}{$targetPath}" . ($qs ? '?' . $qs : '');

$fwd = [];
foreach (getallheaders() as $hn => $hv) {
    $l = strtolower($hn);
    if (in_array($l, ['host', 'content-length', 'expect', 'accept-encoding'], true)) continue;
    $fwd[] = "$hn: $hv";
}
$fwd[] = 'Expect:';                                           // cegah "100 Continue" merusak parsing header
$fwd[] = 'X-Forwarded-For: ' . ($_SERVER['REMOTE_ADDR'] ?? '');

$ch = curl_init($targetUrl);
$opts = [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HEADER         => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_CONNECTTIMEOUT => 5,
    CURLOPT_TIMEOUT        => $timeout,
    CURLOPT_HTTPHEADER     => $fwd,
    CURLOPT_CUSTOMREQUEST  => $_SERVER['REQUEST_METHOD'],
];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // multipart (upload foto) dibaca ulang dari $_POST/$_FILES karena php://input kosong untuk multipart
    if (stripos($_SERVER['CONTENT_TYPE'] ?? '', 'multipart/form-data') !== false) {
        $post = $_POST;
        foreach ($_FILES as $field => $f) {
            if (is_array($f['name'])) {
                foreach ($f['name'] as $i => $n) {
                    if ($f['error'][$i] === UPLOAD_ERR_OK)
                        $post["{$field}[{$i}]"] = new CURLFile($f['tmp_name'][$i], $f['type'][$i], $n);
                }
            } elseif ($f['error'] === UPLOAD_ERR_OK) {
                $post[$field] = new CURLFile($f['tmp_name'], $f['type'], $f['name']);
            }
        }
        // biarkan curl membuat boundary baru
        $opts[CURLOPT_HTTPHEADER] = array_values(array_filter($fwd, fn($h) => stripos($h, 'content-type:') !== 0));
        $opts[CURLOPT_POSTFIELDS] = $post;
    } else {
        $opts[CURLOPT_POSTFIELDS] = file_get_contents('php://input');
    }
}
curl_setopt_array($ch, $opts);
$response = curl_exec($ch);

if ($response === false) {
    $err = curl_error($ch);
    curl_close($ch);
    http_response_code(502);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'message' => "Tidak dapat terhubung ke PC: {$err}", 'ip_tried' => "{$activeIP}:{$targetPort}"]);
    exit;
}

$headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
$rawHeaders = substr($response, 0, $headerSize);
$bodyOut    = substr($response, $headerSize);
$httpCode   = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

http_response_code($httpCode);
// teruskan header penting (gambar, CSV download, cache)
foreach (['Content-Type', 'Content-Disposition', 'Cache-Control'] as $h) {
    if (preg_match_all('/^' . $h . ':\s*(.+)$/im', $rawHeaders, $m)) header($h . ': ' . trim(end($m[1])));
}
if (!preg_match('/^Content-Type:/im', $rawHeaders)) header('Content-Type: application/json; charset=utf-8');
echo $bodyOut;
