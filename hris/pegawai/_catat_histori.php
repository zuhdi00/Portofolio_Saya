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
function _tulisHistori($conn, $pegId, $aksi, $kolom, $lama, $baru, $namaUser, $idUser, $namaPeg=null) {
    $sql = "INSERT INTO dbo.histori_pegawai
            (pegawai_id, nama_pegawai, aksi, kolom, nilai_lama, nilai_baru, diubah_oleh, diubah_id)
            VALUES (?,?,?,?,?,?,?,?)";
    sqlsrv_query($conn, $sql, [$pegId, $namaPeg, $aksi, $kolom, $lama, $baru, $namaUser, $idUser]);
}

/** ambil nama pegawai dari DB (untuk dicatat di histori) */
function _namaPegawai($conn, $pegId) {
    $st = sqlsrv_query($conn, "SELECT nama_peg FROM dbo.pegawai WHERE id_peg=?", [$pegId]);
    $r = $st ? sqlsrv_fetch_array($st, SQLSRV_FETCH_ASSOC) : null;
    return $r['nama_peg'] ?? null;
}

/**
 * EDIT: bandingkan array data lama vs baru, catat hanya kolom yang berubah.
 * $dataLama & $dataBaru: array asosiatif [kolom => nilai]
 */
function catatHistoriEdit($conn, $pegId, array $dataLama, array $dataBaru, $namaUser=null, $idUser=null) {
    // nama pegawai: pakai dari data baru kalau ada, kalau tidak ambil dari DB
    $namaPeg = _valStr($dataBaru['nama_peg'] ?? '') ?: _namaPegawai($conn, $pegId);
    foreach ($dataBaru as $kolom => $nilaiBaru) {
        $lama = _valStr($dataLama[$kolom] ?? null);
        $baru = _valStr($nilaiBaru);
        if ($lama !== $baru) {
            _tulisHistori($conn, $pegId, 'EDIT', $kolom, $lama, $baru, $namaUser, $idUser, $namaPeg);
        }
    }
}

/**
 * TAMBAH: catat semua kolom terisi sebagai penciptaan data baru.
 */
function catatHistoriTambah($conn, $pegId, array $dataBaru, $namaUser=null, $idUser=null) {
    $namaPeg = _valStr($dataBaru['nama_peg'] ?? '') ?: _namaPegawai($conn, $pegId);
    _tulisHistori($conn, $pegId, 'TAMBAH', null, null, 'Pegawai baru dibuat', $namaUser, $idUser, $namaPeg);
    foreach ($dataBaru as $kolom => $nilaiBaru) {
        $baru = _valStr($nilaiBaru);
        if ($baru !== '') {
            _tulisHistori($conn, $pegId, 'TAMBAH', $kolom, null, $baru, $namaUser, $idUser, $namaPeg);
        }
    }
}

/**
 * HAPUS: catat histori saat pegawai dihapus/dinonaktifkan
 */
function catatHistoriHapus($conn, $pegId, $namaUser=null, $idUser=null) {
    $namaPeg = _namaPegawai($conn, $pegId);
    _tulisHistori($conn, $pegId, 'HAPUS', 'is_aktif', '1', '0', $namaUser, $idUser, $namaPeg);
}

/** ambil nama user yang login (untuk kolom diubah_oleh) */
function _userSekarang() {
    if (session_status() === PHP_SESSION_NONE) @session_start();
    $u = $_SESSION['hris_user'] ?? null;
    return [$u['nama_lengkap'] ?? ($u['username'] ?? 'sistem'), $u['id_user'] ?? null];
}
