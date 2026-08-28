<?php
/**
 * config/_notif.php
 * Helper kirim notifikasi ke tabel dbo.hris_notifications.
 * Dipakai lintas modul (lupa absen, lembur, resign, dll).
 *
 * Pemakaian:
 *   require_once __DIR__ . '/../config/_notif.php';
 *   notifKePeran($conn, ['hr','admin_it'], 'Pengajuan Lupa Absen',
 *                'Form LAB-202608-001 menunggu approval', 'lupa_absen/approval_hr.php');
 */

/** kirim notifikasi ke semua user dengan peran tertentu */
function notifKePeran($conn, array $peran, string $judul, string $pesan, string $link='') {
    if (!$peran) return 0;
    // rakit placeholder IN (?, ?, ...)
    $ph = implode(',', array_fill(0, count($peran), '?'));
    $st = sqlsrv_query($conn,
        "SELECT id_user FROM dbo.hris_users WHERE is_aktif=1 AND peran IN ($ph)", $peran);
    $ids = [];
    if ($st) while ($r = sqlsrv_fetch_array($st, SQLSRV_FETCH_ASSOC)) $ids[] = (int)$r['id_user'];
    return notifKeUser($conn, $ids, $judul, $pesan, $link);
}

/** kirim notifikasi ke daftar user_id tertentu */
function notifKeUser($conn, array $userIds, string $judul, string $pesan, string $link='') {
    $n = 0;
    $sql = "INSERT INTO dbo.hris_notifications (user_id, judul, pesan, link, is_read, created_at)
            VALUES (?,?,?,?,0,GETDATE())";
    foreach (array_unique($userIds) as $uid) {
        $st = sqlsrv_query($conn, $sql, [(int)$uid, $judul, $pesan, $link ?: null]);
        if ($st !== false) $n++;
    }
    return $n;
}
