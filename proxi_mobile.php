<?php
// ================================
// FILE: proxi_mobile.php
// Fungsi: Menerima IP publik dari client
// Format: JSON atau Form-urlencoded
// Simpan ke file ip_log.txt
// ================================

// Set timezone (optional, untuk log waktu)
date_default_timezone_set('Asia/Jakarta');

// Ambil header Content-Type
$contentType = isset($_SERVER["CONTENT_TYPE"]) ? trim($_SERVER["CONTENT_TYPE"]) : '';

// Inisialisasi variabel
$ipAddress = "";
$clientIP = $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';

// Cek apakah format JSON
if (stripos($contentType, 'application/json') !== false) {
    $input = json_decode(file_get_contents('php://input'), true);
    if (json_last_error() === JSON_ERROR_NONE && isset($input['ip'])) {
        $ipAddress = trim($input['ip']);
    }
} 
// Atau jika format form-urlencoded
elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ip'])) {
    $ipAddress = trim($_POST['ip']);
}

// Jika tidak ada IP dikirim
if (empty($ipAddress)) {
    http_response_code(400);
    echo json_encode([
        "status" => "error",
        "message" => "No IP address received"
    ]);
    exit;
}

// Format data log
$timestamp = date('Y-m-d H:i:s');
$logLine = "[$timestamp] Received IP: $ipAddress | Client: $clientIP" . PHP_EOL;

// Simpan ke file log
$logFile = __DIR__ . '/ip_log.txt';
file_put_contents($logFile, $logLine, FILE_APPEND | LOCK_EX);

// Kirim respons sukses
header('Content-Type: application/json');
echo json_encode([
    "status" => "success",
    "received_ip" => $ipAddress,
    "client_ip" => $clientIP,
    "time" => $timestamp
]);
?>
