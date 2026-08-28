<?php
/**
 * hris/presensi/qr_token.php
 * Memberi token slot saat ini ke halaman kiosk (dipanggil AJAX oleh qr_kiosk.php).
 * Hanya boleh diakses dari mesin kiosk — batasi via IP di bawah.
 */
include 'qr_config.php';
header('Content-Type: application/json');

// Opsional tapi disarankan: hanya kiosk yang boleh minta token.
// Isi dengan IP PC kiosk; kosongkan array untuk mengizinkan semua (mode uji).
$KIOSK_IPS = [];   // contoh: ['192.168.1.50']

$ip = $_SERVER['REMOTE_ADDR'] ?? '';
if ($KIOSK_IPS && !in_array($ip, $KIOSK_IPS)) {
    http_response_code(403);
    echo json_encode(['error' => 'forbidden']); exit;
}

$slot  = intdiv(time(), QR_WINDOW);
$token = qr_make_token($slot);

echo json_encode([
    'slot' => $slot,
    'url'  => ABSEN_URL . '?t=' . $token,
]);
