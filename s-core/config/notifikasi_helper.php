<?php
/**
 * Helper untuk mengirim notifikasi ke user (menggunakan tabel hris_notifications di SQL Server)
 */

function kirim_notifikasi($conn, $user_id, $judul, $pesan, $link = '#') {
    $sql = "INSERT INTO dbo.hris_notifications (user_id, judul, pesan, link, is_read, created_at) 
            VALUES (?, ?, ?, ?, 0, GETDATE())";
    $params = [$user_id, $judul, $pesan, $link];
    $stmt = sqlsrv_query($conn, $sql, $params);
    return $stmt !== false;
}

function kirim_notifikasi_role($conn, $role, $judul, $pesan, $link = '#') {
    // Cari semua user dengan peran tertentu yang aktif
    $sql_users = "SELECT id_user FROM dbo.hris_users WHERE peran = ? AND is_aktif = 1";
    $stmt_users = sqlsrv_query($conn, $sql_users, [$role]);
    
    if ($stmt_users === false) return false;

    $success = true;
    while ($row = sqlsrv_fetch_array($stmt_users, SQLSRV_FETCH_ASSOC)) {
        $res = kirim_notifikasi($conn, $row['id_user'], $judul, $pesan, $link);
        if (!$res) $success = false;
    }
    
    return $success;
}

// Tambahan: Mengirim notifikasi ke daftar ID user tertentu
function kirim_notifikasi_users($conn, $user_ids, $judul, $pesan, $link = '#') {
    $success = true;
    foreach ($user_ids as $uid) {
        $res = kirim_notifikasi($conn, $uid, $judul, $pesan, $link);
        if (!$res) $success = false;
    }
    return $success;
}

// Fungsi pembungkus agar tidak bentrok dengan $conn MariaDB di file lama
function kirim_notifikasi_role_auto($role, $judul, $pesan, $link = '#') {
    $serverName = "spsdmz2";
    $connectionOptions = array(
        "Database" => "dbHR",
        "Uid" => "sa",
        "PWD" => "supracor",
        "LoginTimeout" => 5,
        "Encrypt" => false,
        "TrustServerCertificate" => true
    );
    $conn_sqlsrv = sqlsrv_connect($serverName, $connectionOptions);
    if ($conn_sqlsrv) {
        kirim_notifikasi_role($conn_sqlsrv, $role, $judul, $pesan, $link);
        sqlsrv_close($conn_sqlsrv);
    }
}
?>
