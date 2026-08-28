<?php
/**
 * Script untuk memproses klik notifikasi
 * Mengubah status is_read menjadi 1 lalu mengarahkan ke link tujuan
 */
require_once __DIR__ . '/auth/auth.php';
wajib_login();

if (session_status() === PHP_SESSION_NONE) session_start();
$__u = $_SESSION['hris_user'] ?? null;
if (!$__u) {
    header('Location: auth/login.php');
    exit;
}

require_once __DIR__ . '/config/koneksi_sqlsrv.php';

$id = $_GET['id'] ?? null;
if ($id) {
    // Pastikan notifikasi ini milik user yang sedang login
    $sql_check = "SELECT link FROM dbo.hris_notifications WHERE id = ? AND user_id = ?";
    $st = sqlsrv_query($conn, $sql_check, [$id, $__u['id_user']]);
    
    if ($st && $row = sqlsrv_fetch_array($st, SQLSRV_FETCH_ASSOC)) {
        $link = $row['link'] ? $row['link'] : 'index.php';
        
        // Update is_read
        $sql_update = "UPDATE dbo.hris_notifications SET is_read = 1 WHERE id = ?";
        sqlsrv_query($conn, $sql_update, [$id]);
        
        header("Location: $link");
        exit;
    }
}

// Fallback jika tidak ketemu / salah ID
header('Location: index.php');
exit;
?>
