<?php
/**
 * ip_receiver.php
 * ============================================================
 * Letakkan di hosting: /public_html/system/ip_receiver.php
 *
 * Menerima update IP publik dari PC (dikirim oleh ip_updater.php)
 * lalu menyimpannya ke ip_log.txt untuk dibaca proxy_web.php
 * ============================================================
 */

// =================== KONFIGURASI ===================
$logFile   = __DIR__ . '/ip_log.txt';
$secretKey = 'Supracor@2026'; // Harus sama dengan di ip_updater.php
$maxLines  = 100; // Batas baris log agar tidak membengkak
// ====================================================

header('Content-Type: application/json');

// Hanya terima POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Baca body JSON
$input = json_decode(file_get_contents('php://input'), true);

if (!$input || empty($input['ip']) || empty($input['secret'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Data tidak lengkap']);
    exit;
}

// Validasi secret key
if ($input['secret'] !== $secretKey) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Akses ditolak']);
    exit;
}

// Validasi format IP
$ip = trim($input['ip']);
if (!filter_var($ip, FILTER_VALIDATE_IP)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Format IP tidak valid']);
    exit;
}

// Tulis ke log
$timestamp  = date('Y-m-d H:i:s');
$clientIP   = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$logEntry   = "[{$timestamp}] IP: {$ip} | From: {$clientIP}" . PHP_EOL;

// Jaga ukuran log: trim ke $maxLines baris terakhir
$existingLines = [];
if (file_exists($logFile)) {
    $existingLines = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
}
$existingLines[] = trim($logEntry);
if (count($existingLines) > $maxLines) {
    $existingLines = array_slice($existingLines, -$maxLines);
}
file_put_contents($logFile, implode(PHP_EOL, $existingLines) . PHP_EOL, LOCK_EX);

echo json_encode([
    'success' => true,
    'message' => "IP {$ip} berhasil disimpan",
    'time'    => $timestamp,
]);
?>
