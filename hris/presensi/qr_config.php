<?php
/**
 * hris/presensi/qr_config.php
 * Konfigurasi token QR dinamis. JANGAN share file ini keluar tim IT.
 */

// GANTI dengan string acak panjang milik sendiri (min 32 karakter)
define('QR_SECRET', 'SupracorSejahtera-GANTI-DENGAN-RANDOM-PANJANG-2026!');

// Masa berlaku 1 token (detik). QR di kiosk refresh mengikuti ini.
define('QR_WINDOW', 20);

// Prefix subnet jaringan kantor yang boleh presensi. Sesuaikan!
// Contoh: WiFi kantor 192.168.1.x dan 10.10.0.x
define('ALLOWED_IP_PREFIX', ['192.168.', '10.']);

// URL dasar halaman absen yang dibuka HP setelah scan QR
define('ABSEN_URL', 'http://edp2:8081/hris/presensi/absen.php');

/** Buat token untuk slot waktu tertentu (slot = floor(time / WINDOW)) */
function qr_make_token(int $slot): string {
    return hash_hmac('sha256', 'presensi|' . $slot, QR_SECRET);
}

/** Validasi token: terima slot sekarang & slot sebelumnya (toleransi pergantian) */
function qr_check_token(string $token): bool {
    $slot = intdiv(time(), QR_WINDOW);
    return hash_equals(qr_make_token($slot), $token)
        || hash_equals(qr_make_token($slot - 1), $token);
}

/** Cek IP client masuk subnet kantor */
function ip_kantor(string $ip): bool {
    foreach (ALLOWED_IP_PREFIX as $p) {
        if (strpos($ip, $p) === 0) return true;
    }
    return false;
}
