<?php
/**
 * pengunduran_diri/file.php?id=ID
 * Tampilkan/unduh PDF surat resign dari folder server.
 */
require_once __DIR__ . '/../auth/auth.php';
wajib_izin('modul_hr');
require_once __DIR__ . '/../config/koneksi_sqlsrv.php';

$FOLDER_SIMPAN = '\\\\spsdmz\\gg$\\HRD\\SuratResign';

$id=(int)($_GET['id']??0);
$st=sqlsrv_query($conn,"SELECT file_pdf FROM dbo.pengunduran_diri WHERE id_resign=?",[$id]);
$r=$st?sqlsrv_fetch_array($st,SQLSRV_FETCH_ASSOC):null;
if(!$r || !$r['file_pdf']) die("File tidak ditemukan.");

$path = rtrim($FOLDER_SIMPAN,'\\/') . DIRECTORY_SEPARATOR . $r['file_pdf'];
if(!is_file($path)) die("File fisik tidak ditemukan di folder: ".htmlspecialchars($path));

header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="'.basename($r['file_pdf']).'"');
header('Content-Length: '.filesize($path));
readfile($path);
exit;
