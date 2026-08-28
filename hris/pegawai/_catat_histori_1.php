<?php
/**
 * pegawai/_catat_histori.php
 * Fungsi pencatat histori perubahan data pegawai.
 * Panggil dari submit (tambah) dan update (edit).
 *
 * Pemakaian:
 *   require_once __DIR__ . '/_catat_histori.php';
 *   // saat EDIT: bandingkan data lama vs baru
 *   catatHistoriEdit($conn, $pegId, $dataLama, $dataBaru, $namaUser, $idUser);
 *   // saat TAMBAH: catat sebagai penciptaan
 *   catatHistoriTambah($conn, $pegId, $dataBaru, $namaUser, $idUser);
 */

/** ubah nilai jadi string aman untuk dibandingkan & disimpan */
function _valStr($v) {
    if ($v === null) return '';
    if ($v instanceof DateTime) return $v->format('Y-m-d');
    return trim((string)$v);
}

/** catat satu baris histori */
function _tulisHistori($conn, $pegId, $aksi, $kolom, $lama, $baru, $namaUser, $idUser) {
    $sql = "INSERT INTO dbo.histori_pegawai
            (pegawai_id, aksi, kolom, nilai_lama, nilai_baru, diubah_oleh, diubah_id)
            VALUES (?,?,?,?,?,?,?)";
    sqlsrv_query($conn, $sql, [$pegId, $aksi, $kolom, $lama, $baru, $namaUser, $idUser]);
}

/**
 * EDIT: bandingkan array data lama vs baru, catat hanya kolom yang berubah.
 * $dataLama & $dataBaru: array asosiatif [kolom => nilai]
 */
function catatHistoriEdit($conn, $pegId, array $dataLama, array $dataBaru, $namaUser=null, $idUser=null) {
    foreach ($dataBaru as $kolom => $nilaiBaru) {
        $lama = _valStr($dataLama[$kolom] ?? null);
        $baru = _valStr($nilaiBaru);
        if ($lama !== $baru) {
            _tulisHistori($conn, $pegId, 'EDIT', $kolom, $lama, $baru, $namaUser, $idUser);
        }
    }
}

/**
 * TAMBAH: catat semua kolom terisi sebagai penciptaan data baru.
 */
function catatHistoriTambah($conn, $pegId, array $dataBaru, $namaUser=null, $idUser=null) {
    // satu baris penanda
    _tulisHistori($conn, $pegId, 'TAMBAH', null, null, 'Pegawai baru dibuat', $namaUser, $idUser);
    // lalu tiap kolom terisi
    foreach ($dataBaru as $kolom => $nilaiBaru) {
        $baru = _valStr($nilaiBaru);
        if ($baru !== '') {
            _tulisHistori($conn, $pegId, 'TAMBAH', $kolom, null, $baru, $namaUser, $idUser);
        }
    }
}

/** ambil nama user yang login (untuk kolom diubah_oleh) */
function _userSekarang() {
    if (session_status() === PHP_SESSION_NONE) @session_start();
    $u = $_SESSION['hris_user'] ?? null;
    return [$u['nama_lengkap'] ?? ($u['username'] ?? 'sistem'), $u['id_user'] ?? null];
}
