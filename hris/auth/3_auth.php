<?php
/**
 * auth/auth.php
 * Pusat autentikasi & otorisasi HRIS.
 * Include di ATAS setiap halaman yang perlu login:
 *     require_once __DIR__ . '/../auth/auth.php';
 *     wajib_login();                    // semua yang login boleh
 *     wajib_peran(['admin_it','hr']);   // batasi peran tertentu
 */

if (session_status() === PHP_SESSION_NONE) session_start();

/* ---------- daftar hak akses per peran ---------- */
$HAK_AKSES = [
    'admin_it'     => ['*'],  // semua
    'hr'           => ['dashboard','pegawai_lihat','pegawai_edit','absensi_rekap',
                       'koreksi_approval','lembur_input','lembur_rekap','modul_hr','resign_input'],
    'atasan'       => ['dashboard','pegawai_lihat','absensi_rekap',
                       'koreksi_approval','lembur_input','resign_input'],
    'admin_divisi' => ['dashboard','pegawai_lihat','absensi_rekap','lembur_input','resign_input'],
    'user'         => ['dashboard','absensi_sendiri','resign_input'],
];

function sedang_login(): bool {
    return isset($_SESSION['hris_user']);
}

function user_login(): ?array {
    return $_SESSION['hris_user'] ?? null;
}

function peran_saya(): string {
    return $_SESSION['hris_user']['peran'] ?? '';
}

/** cek apakah peran user punya izin tertentu */
function boleh(string $izin): bool {
    global $HAK_AKSES;
    $peran = peran_saya();
    $daftar = $HAK_AKSES[$peran] ?? [];
    return in_array('*', $daftar, true) || in_array($izin, $daftar, true);
}

/** paksa harus login - kalau belum, lempar ke login */
function wajib_login(): void {
    if (!sedang_login()) {
        header('Location: ' . base_url_auth() . '/hris/auth/login.php');
        exit;
    }
}

/** batasi ke peran tertentu - kalau tidak cocok, tampilkan tolak */
function wajib_peran(array $peranBoleh): void {
    wajib_login();
    if (!in_array(peran_saya(), $peranBoleh, true) && peran_saya() !== 'admin_it') {
        tampilkan_tolak();
    }
}

/** batasi berdasarkan izin fitur */
function wajib_izin(string $izin): void {
    wajib_login();
    if (!boleh($izin)) tampilkan_tolak();
}

function tampilkan_tolak(): void {
    http_response_code(403);
    echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Akses Ditolak</title>'
       . '<style>body{font-family:sans-serif;text-align:center;padding:60px;color:#444}'
       . 'h1{color:#c00}a{color:#0066cc}</style></head><body>'
       . '<h1>403 — Akses Ditolak</h1>'
       . '<p>Anda tidak memiliki hak untuk membuka halaman ini.</p>'
       . '<p>Peran Anda: <strong>' . htmlspecialchars(peran_saya()) . '</strong></p>'
       . '<p><a href="' . base_url_auth() . '/hris/index.php">Kembali ke Dashboard</a></p>'
       . '</body></html>';
    exit;
}

/** base url sederhana (protokol + host) */
function base_url_auth(): string {
    $proto = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS']!=='off') ? 'https' : 'http';
    return $proto . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
}
