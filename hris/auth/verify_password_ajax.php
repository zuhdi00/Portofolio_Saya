<?php
session_start();
require_once '../config/koneksi_sqlsrv.php';

header('Content-Type: application/json');

if (!isset($_SESSION['hris_user'])) {
    echo json_encode(['success' => false, 'message' => 'Anda belum login']);
    exit;
}

$id_user = $_SESSION['hris_user']['id_user'] ?? 0;
$password_input = $_POST['password'] ?? '';

if (empty($password_input)) {
    echo json_encode(['success' => false, 'message' => 'Password tidak boleh kosong']);
    exit;
}

$st = sqlsrv_query($conn, "SELECT password_hash FROM dbo.hris_users WHERE id_user = ?", [$id_user]);
$row = $st ? sqlsrv_fetch_array($st, SQLSRV_FETCH_ASSOC) : null;

if (!$row) {
    echo json_encode(['success' => false, 'message' => 'User tidak ditemukan di database']);
    exit;
}

if (password_verify($password_input, $row['password_hash'])) {
    // Set a flag in session if needed for further backend validation
    $_SESSION['hris_rekap_lainnya_auth'] = true;
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => 'Password yang Anda masukkan salah']);
}
