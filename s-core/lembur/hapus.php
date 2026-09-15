<?php
/**
 * lembur/hapus.php?id=ID_FORM
 * Hapus form lembur (beserta detail via FK cascade).
 * Aturan:
 *   - status DIAJUKAN (belum di-ACC)  -> semua user yang boleh input bisa hapus
 *   - status DISETUJUI_HR / DITOLAK   -> hanya HR & admin_it
 */
require_once __DIR__ . '/../auth/auth.php';
wajib_login();
require_once __DIR__ . '/../config/koneksi_sqlsrv.php';

$id   = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$balik = $_GET['dari'] ?? 'rekap_hr.php';   // halaman asal utk redirect
if (!$id) { header("Location: $balik"); exit; }

/* ambil status form dulu */
$st = sqlsrv_query($conn, "SELECT status FROM dbo.lembur_form WHERE id_form=?", [$id]);
$f = $st ? sqlsrv_fetch_array($st, SQLSRV_FETCH_ASSOC) : null;
if (!$f) { header("Location: $balik?msg=notfound"); exit; }

$status = $f['status'];
$peran  = peran_saya();

/* cek izin */
$sudahAcc = in_array($status, ['DISETUJUI_HR','DITOLAK'], true);
$bolehHapus = false;

if ($sudahAcc) {
    // sudah di-ACC: hanya hr & admin_it
    $bolehHapus = in_array($peran, ['hr','admin_it'], true);
} else {
    // belum di-ACC (DIAJUKAN/DRAFT): siapa saja yang bisa input lembur
    $bolehHapus = boleh('lembur_input') || boleh('lembur_rekap');
}

if (!$bolehHapus) {
    header("Location: $balik?msg=notallowed");
    exit;
}

/* hapus - detail ikut terhapus via ON DELETE CASCADE */
$st = sqlsrv_query($conn, "DELETE FROM dbo.lembur_form WHERE id_form=?", [$id]);
$msg = $st === false ? 'gagal' : 'terhapus';
header("Location: $balik?msg=$msg");
exit;
