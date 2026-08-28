<?php
/**
 * pengunduran_diri/cetak.php?id=ID
 * Cetak surat pengunduran diri untuk TTD fisik atasan & HRD.
 * Ctrl+P & klik-kanan diblokir; hanya tombol PRINT.
 */
require_once __DIR__ . '/../auth/auth.php';
wajib_izin('modul_hr');
require_once __DIR__ . '/../config/koneksi_sqlsrv.php';

$id=(int)($_GET['id']??0);
$st=sqlsrv_query($conn,
  "SELECT r.*, d.nama_dept FROM dbo.pengunduran_diri r
   LEFT JOIN dbo.department d ON d.id_dept=r.department_id WHERE r.id_resign=?",[$id]);
$f=$st?sqlsrv_fetch_array($st,SQLSRV_FETCH_ASSOC):null;
if(!$f) die("Data tidak ditemukan.");

function h($v){return htmlspecialchars((string)($v??''));}
function tglIndo($v){
  if(!($v instanceof DateTime)) return '—';
  $b=['','Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
  return $v->format('d').' '.$b[(int)$v->format('n')].' '.$v->format('Y');
}
$logo = file_exists(__DIR__.'/../lembur/kop_sps.jpeg');
?>
<!DOCTYPE html>
<html lang="id"><head><meta charset="utf-8"><title>Surat Resign <?= h($f['no_surat']) ?></title>
<style>
  *{box-sizing:border-box;margin:0;padding:0}
  body{font-family:'Times New Roman',serif;color:#000;background:#e5e5e5;padding:20px}
  .kertas{background:#fff;width:210mm;min-height:297mm;margin:0 auto;padding:20mm;box-shadow:0 0 10px rgba(0,0,0,.2)}
  .kop{border-bottom:3px double #000;padding-bottom:10px;margin-bottom:20px}
  .kop img{width:100%;height:auto;display:block}
  h2{text-align:center;text-decoration:underline;margin:10px 0;font-size:16px}
  .nomor{text-align:center;font-size:13px;margin-bottom:24px}
  .isi{font-size:14px;line-height:1.8;text-align:justify}
  table.info{margin:16px 0;font-size:14px}
  table.info td{padding:2px 8px;vertical-align:top}
  .ttd{display:flex;justify-content:space-between;gap:30px;margin-top:50px}
  .ttd .kotak{flex:1;text-align:center;font-size:13px}
  .ttd .garis{margin-top:75px;border-top:1px solid #000;padding-top:4px}
  .bar{position:fixed;top:0;left:0;right:0;background:#c00;color:#fff;padding:12px 20px;
       display:flex;justify-content:space-between;align-items:center;z-index:100}
  .bar button{background:#fff;color:#c00;border:none;padding:8px 20px;border-radius:5px;font-weight:bold;cursor:pointer}
  .bar a{color:#fff;text-decoration:underline;font-size:13px}
  @media print{ body{background:#fff;padding:0} .kertas{box-shadow:none;width:auto;padding:15mm;margin:0} .bar{display:none} @page{size:A4;margin:0} }
</style></head><body>
<div class="bar">
  <span>Surat Resign — <?= h($f['no_surat']) ?></span>
  <span><a href="index.php">&larr; Kembali</a> &nbsp; <button onclick="window.print()">🖨 PRINT</button></span>
</div>

<div class="kertas" style="margin-top:60px">
  <?php if($logo): ?><div class="kop"><img src="../lembur/kop_sps.jpeg" alt="PT Supracor Sejahtera"></div><?php endif; ?>

  <h2>SURAT PENGUNDURAN DIRI</h2>
  <div class="nomor">No: <?= h($f['no_surat']) ?></div>

  <div class="isi">
    <p>Yang bertanda tangan di bawah ini:</p>
    <table class="info">
      <tr><td>Nama</td><td>:</td><td><strong><?= h($f['nama_pegawai']) ?></strong></td></tr>
      <tr><td>Divisi/Departemen</td><td>:</td><td><?= h($f['nama_dept']??'—') ?></td></tr>
    </table>
    <p>Dengan ini menyatakan mengundurkan diri dari PT Supracor Sejahtera, dengan rincian sebagai berikut:</p>
    <table class="info">
      <tr><td>Tanggal Pengajuan</td><td>:</td><td><?= tglIndo($f['tgl_mulai']) ?></td></tr>
      <tr><td>Hari Kerja Terakhir</td><td>:</td><td><?= tglIndo($f['tgl_berakhir']) ?></td></tr>
      <tr><td>Alasan</td><td>:</td><td><?= h($f['alasan']??'—') ?></td></tr>
    </table>
    <p>Demikian surat pengunduran diri ini saya buat dengan sebenar-benarnya tanpa paksaan dari pihak manapun.
       Atas perhatian dan kerja samanya selama ini, saya ucapkan terima kasih.</p>
  </div>

  <div class="ttd">
    <div class="kotak">Hormat saya,<br>Karyawan
      <div class="garis">( <?= h($f['nama_pegawai']) ?> )</div></div>
    <div class="kotak">Mengetahui,<br>Atasan Divisi
      <div class="garis">( <?= h($f['atasan_nama'] ?: '..........................') ?> )</div></div>
    <div class="kotak">Menyetujui,<br>HRD
      <div class="garis">( .......................... )</div></div>
  </div>
</div>

<script>
window.addEventListener('keydown',function(e){
  if((e.ctrlKey||e.metaKey)&&(e.key==='p'||e.key==='P')){e.preventDefault();alert('Gunakan tombol PRINT di kanan atas.');return false;}
});
window.addEventListener('contextmenu',e=>e.preventDefault());
</script>
</body></html>
