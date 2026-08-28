<?php
// Atur header untuk memberitahu browser bahwa ini adalah respons JSON.
header('Content-Type: application/json');

// 1. Ambil data JSON yang dikirim dari JavaScript
$input = json_decode(file_get_contents('php://input'), true);

$username = $input['username'] ?? '';
$password = $input['password'] ?? '';

// 2. Baca file login.json dari server (ini aman, tidak akan dikirim ke browser)
$users_data_json = file_get_contents('login.json');
$users = json_decode($users_data_json, true);

$found_user = null;

// 3. Cari pengguna berdasarkan username
foreach ($users as $user) {
    if (strtolower($user['username']) === strtolower($username)) {
        $found_user = $user;
        break;
    }
}

// 4. Verifikasi password dan kirim respons
if ($found_user && $found_user['password'] === $password) {
    // Jika berhasil, kirim status sukses dan peran pengguna
    echo json_encode([
        'success' => true,
        'role' => $found_user['role']
    ]);
} else {
    // Jika gagal, kirim status gagal
    // Kita tidak memberikan pesan spesifik "password salah" atau "username salah" untuk keamanan.
    echo json_encode([
        'success' => false,
        'message' => 'Username atau password salah.'
    ]);
}

?>