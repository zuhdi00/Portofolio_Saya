<?php
header('Content-Type: application/json; charset=utf-8');

$logFile = __DIR__ . '/../op_report/ip_log.txt';
$defaultIP = '36.73.249.5';
$port = 8081;
$timeout = 10;

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

$target = "http://{$activeIP}:{$port}/sparepart/opb/get_opb_list.php?limit=1";

$ch = curl_init($target);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HEADER => true,
    CURLOPT_NOBODY => false,
    CURLOPT_CONNECTTIMEOUT => 5,
    CURLOPT_TIMEOUT => $timeout,
]);

$resp = curl_exec($ch);
$err = null;
if ($resp === false) {
    $err = curl_error($ch);
}
$info = curl_getinfo($ch);
curl_close($ch);

$result = [
    'checked_target' => $target,
    'active_ip' => $activeIP,
    'curl_error' => $err,
    'http_code' => $info['http_code'] ?? null,
    'total_time' => $info['total_time'] ?? null,
    'connect_time' => $info['connect_time'] ?? null,
    'response_headers_snippet' => null,
    'response_body_snippet' => null,
];

if (!$err && $resp) {
    $headerSize = $info['header_size'] ?? 0;
    $result['response_headers_snippet'] = substr($resp, 0, $headerSize);
    $result['response_body_snippet'] = substr($resp, $headerSize, 800);
}

// Also include op_report/ip_log.txt contents if available
if (file_exists($logFile)) {
    $result['ip_log_tail'] = array_slice(file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES), -8);
} else {
    $result['ip_log_tail'] = null;
}

echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
?>
