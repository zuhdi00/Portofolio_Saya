<?php
require_once __DIR__ . '/../auth/auth.php';
wajib_izin('lupa_absen_input');
require_once __DIR__ . '/../config/koneksi_sqlsrv.php';
$FOLDER_BUKTI='\\\\spsdmz\\gg$\\HRD\\BuktiLupaAbsensi';
$id=(int)($_GET['id']??0);
$st=sqlsrv_query($conn,"SELECT file_bukti FROM dbo.lupa_absen_form WHERE id_form=?",[$id]);
$r=$st?sqlsrv_fetch_array($st,SQLSRV_FETCH_ASSOC):null;
if(!$r||!$r['file_bukti']) die("Bukti tidak ada.");
$path=rtrim($FOLDER_BUKTI,'\\/').DIRECTORY_SEPARATOR.$r['file_bukti'];
if(!is_file($path)) die("File tidak ditemukan: ".htmlspecialchars($path));
$ext=strtolower(pathinfo($path,PATHINFO_EXTENSION));
$mime=['jpg'=>'image/jpeg','jpeg'=>'image/jpeg','png'=>'image/png','gif'=>'image/gif','pdf'=>'application/pdf'][$ext]??'application/octet-stream';
header("Content-Type: $mime");
header('Content-Disposition: inline; filename="'.basename($path).'"');
readfile($path); exit;
