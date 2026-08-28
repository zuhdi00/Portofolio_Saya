<?php
/**
 * pengunduran_diri/cetak.php?id=ID
 * Cetak surat pengunduran diri (format template SPS) untuk TTD fisik.
 * Ctrl+P & klik-kanan diblokir; hanya tombol PRINT.
 */
require_once __DIR__ . '/../auth/auth.php';
wajib_izin('resign_input');
require_once __DIR__ . '/../config/koneksi_sqlsrv.php';

$id=(int)($_GET['id']??0);
$st=sqlsrv_query($conn,
  "SELECT r.*, d.nama_dept, p.nik, p.position_code
   FROM dbo.pengunduran_diri r
   LEFT JOIN dbo.department d ON d.id_dept=r.department_id
   LEFT JOIN dbo.pegawai p ON p.id_peg=r.pegawai_id
   WHERE r.id_pengunduran=?",[$id]);
$f=$st?sqlsrv_fetch_array($st,SQLSRV_FETCH_ASSOC):null;
if(!$f) die("Data tidak ditemukan.");

function h($v){return htmlspecialchars((string)($v??''));}
function tglIndo($v){
  if(!($v instanceof DateTime)) return '................';
  $b=['','Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
  return $v->format('d').' '.$b[(int)$v->format('n')].' '.$v->format('Y');
}
// tanggal surat = tanggal_pengajuan (pengajuan), fallback hari ini
$tglSurat = $f['tanggal_pengajuan'] instanceof DateTime ? $f['tanggal_pengajuan'] : new DateTime();
$b=['','Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
$tglSuratStr = $tglSurat->format('d').' '.$b[(int)$tglSurat->format('n')].' '.$tglSurat->format('Y');

$logo = file_exists(__DIR__.'/kop_resign.jpeg') ? 'kop_resign.jpeg'
      : (file_exists(__DIR__.'/../lembur/kop_sps.jpeg') ? '../lembur/kop_sps.jpeg' : null);
?>
<!DOCTYPE html>
<html lang="id"><head><meta charset="utf-8"><title>Surat Resign <?= h($f['no_surat']) ?></title>
<style>
  *{box-sizing:border-box;margin:0;padding:0}
  body{font-family:'Times New Roman',serif;color:#000;background:#e5e5e5;padding:20px;font-size:12pt}
  .kertas{background:#fff;width:210mm;min-height:297mm;margin:0 auto;padding:22mm 25mm;box-shadow:0 0 10px rgba(0,0,0,.2)}
  .kop{border-bottom:3px double #000;padding-bottom:8px;margin-bottom:24px}
  .kop img{width:100%;height:auto;display:block}
  h2{text-align:center;text-decoration:underline;margin:6px 0 24px;font-size:14pt;font-weight:bold}
  .isi{line-height:1.6;text-align:justify}
  .isi p{margin-bottom:12px}
  table.info{margin:4px 0 12px 0}
  table.info td{padding:1px 6px;vertical-align:top}
  table.info td.lbl{width:110px}
  table.info td.sep{width:12px}
  .ttd-wrap{margin-top:36px}
  .ttd-atas{display:flex;justify-content:space-around;margin-bottom:60px}
  .ttd-bawah{display:flex;justify-content:space-around}
  .kolom{width:40%;text-align:center;line-height:1.5}
  .nm{text-decoration:underline;font-weight:bold}
  .bar{position:fixed;top:0;left:0;right:0;background:#c00;color:#fff;padding:12px 20px;
       display:flex;justify-content:space-between;align-items:center;z-index:100}
  .bar button{background:#fff;color:#c00;border:none;padding:8px 20px;border-radius:5px;font-weight:bold;cursor:pointer}
  .bar a{color:#fff;text-decoration:underline;font-size:13px}
  @media print{ body{background:#fff;padding:0;font-size:12pt} .kertas{box-shadow:none;width:auto;padding:18mm 20mm;margin:0} .bar{display:none} @page{size:A4;margin:0} }
</style></head><body>
<div class="bar">
  <span>Surat Resign — <?= h($f['no_surat']) ?></span>
  <span><a href="index.php">&larr; Kembali</a> &nbsp; <button onclick="window.print()">🖨 PRINT</button></span>
</div>

<div class="kertas" style="margin-top:60px">
  <?php if($logo): ?><div class="kop"><img src="<?= $logo ?>" alt="PT Supracor Sejahtera"></div><?php endif; ?>

  <h2>SURAT PENGUNDURAN DIRI</h2>

  <div class="isi">
    <p style="margin-bottom:2px">Kepada Yth.<br>HRD PT Supracor Sejahtera<br>Di tempat,</p>
    <p>Dengan hormat,</p>
    <p style="margin-bottom:4px">Saya yang bertanda tangan di bawah ini:</p>
    <table class="info">
      <tr><td class="lbl">Nama</td><td class="sep">:</td><td><strong><?= h($f['nama_pegawai']) ?></strong></td></tr>
      <tr><td class="lbl">NIK</td><td class="sep">:</td><td><?= h($f['nik']??'—') ?></td></tr>
      <tr><td class="lbl">Departemen</td><td class="sep">:</td><td><?= h($f['nama_dept']??'—') ?></td></tr>
      <tr><td class="lbl">Jabatan</td><td class="sep">:</td><td><?= h($f['position_code']??'—') ?></td></tr>
    </table>
    <p>Menyatakan dengan sesungguhnya bahwa mulai tanggal <strong><?= tglIndo($f['tanggal_efektif']) ?></strong>
       saya mengajukan permohonan untuk mengundurkan diri sebagai karyawan PT Supracor Sejahtera,
       berkenaan dengan <strong><?= h($f['alasan']??'................') ?></strong>.</p>
    <p>Ucapan terima kasih yang sebesar-besarnya saya sampaikan atas kesempatan yang diberikan untuk dapat
       bekerja di PT Supracor Sejahtera.</p>
    <p>Melalui surat ini saya memohon maaf kepada segenap manajemen dan karyawan PT Supracor Sejahtera jika
       terdapat kesalahan yang saya perbuat selama bekerja. Besar harapan saya untuk PT Supracor Sejahtera
       akan terus berkembang dan maju.</p>
  </div>

  <div class="ttd-wrap">
    <p style="text-align:right">Mojokerto, <?= $tglSuratStr ?></p>

    <div class="ttd-atas">
      <div class="kolom">Hormat Saya,</div>
      <div class="kolom">Disetujui oleh,</div>
    </div>
    <div class="ttd-bawah">
      <div class="kolom">
        <span class="nm"><?= h($f['nama_pegawai']) ?></span><br>
        <?= h($f['position_code']??'') ?>
      </div>
      <div class="kolom">
        <span class="nm"><?= h($f['atasan_nama'] ?: '(...................)') ?></span><br>
        SPV. <?= h($f['nama_dept']??'') ?>
      </div>
    </div>
  </div>
</div>

<script>
window.addEventListener('keydown',function(e){
  if((e.ctrlKey||e.metaKey)&&(e.key==='p'||e.key==='P')){e.preventDefault();alert('Gunakan tombol PRINT di kanan atas.');return false;}
});
window.addEventListener('contextmenu',e=>e.preventDefault());
</script>
</body></html>
