<?php
/**
 * lupa_absen/cetak.php?id=ID - cetak form lupa absen (format spt lembur).
 * Ctrl+P & klik-kanan diblokir; hanya tombol PRINT.
 */
require_once __DIR__ . '/../auth/auth.php';
wajib_izin('lupa_absen_input');
require_once __DIR__ . '/../config/koneksi_sqlsrv.php';

$id=(int)($_GET['id']??0);
$st=sqlsrv_query($conn,
  "SELECT lf.*,d.nama_dept FROM dbo.lupa_absen_form lf
   LEFT JOIN dbo.department d ON d.id_dept=lf.department_id WHERE lf.id_form=?",[$id]);
$f=$st?sqlsrv_fetch_array($st,SQLSRV_FETCH_ASSOC):null;
if(!$f) die("Form tidak ditemukan.");

$rows=sqlsrv_query($conn,
  "SELECT ld.*,p.nik,p.nama_peg FROM dbo.lupa_absen_detail ld
   JOIN dbo.pegawai p ON p.id_peg=ld.pegawai_id WHERE ld.id_form=? ORDER BY p.nama_peg",[$id]);
$detail=[]; while($r=sqlsrv_fetch_array($rows,SQLSRV_FETCH_ASSOC)) $detail[]=$r;

function h($v){return htmlspecialchars((string)($v??''));}
function jam($v){return $v instanceof DateTime?$v->format('H:i'):'—';}
function tglR($v){return $v instanceof DateTime?$v->format('d-m-Y'):'—';}
$jenisLbl=['MASUK'=>'Lupa Masuk','PULANG'=>'Lupa Pulang','KEDUANYA'=>'Lupa Keduanya'];
$logo=file_exists(__DIR__.'/../lembur/kop_sps.jpeg');
?>
<!DOCTYPE html><html lang="id"><head><meta charset="utf-8"><title>Form Lupa Absen <?= h($f['no_form']) ?></title>
<style>
  *{box-sizing:border-box;margin:0;padding:0}
  body{font-family:'Times New Roman',serif;color:#000;background:#e5e5e5;padding:20px}
  .kertas{background:#fff;width:210mm;min-height:297mm;margin:0 auto;padding:20mm;box-shadow:0 0 10px rgba(0,0,0,.2)}
  .kop{border-bottom:3px double #000;padding-bottom:10px;margin-bottom:6px}
  .kop img{width:100%;height:auto;display:block}
  h2{text-align:center;text-decoration:underline;margin:18px 0 4px;font-size:16px}
  .noform{text-align:center;font-size:12px;margin-bottom:16px}
  table.info{width:100%;font-size:13px;margin-bottom:14px}
  table.info td{padding:3px 0}
  table.info td.lbl{width:130px}
  table.data{width:100%;border-collapse:collapse;font-size:12px;margin-bottom:20px}
  table.data th,table.data td{border:1px solid #000;padding:5px 6px}
  table.data th{background:#f0f0f0;text-align:center}
  table.data td.c{text-align:center}
  .tgl-ttd{text-align:right;font-size:13px;margin-top:30px;margin-bottom:6px}
  .ttd{display:flex;justify-content:space-between;gap:20px}
  .ttd .kotak{flex:1;text-align:center;font-size:13px}
  .ttd .garis{margin-top:70px;border-top:1px solid #000;padding-top:4px}
  .catatan{font-size:11px;margin-top:8px;color:#333}
  .bar{position:fixed;top:0;left:0;right:0;background:#c00;color:#fff;padding:12px 20px;display:flex;justify-content:space-between;align-items:center;z-index:100}
  .bar button{background:#fff;color:#c00;border:none;padding:8px 20px;border-radius:5px;font-weight:bold;cursor:pointer}
  .bar a{color:#fff;text-decoration:underline;font-size:13px}
  @media print{body{background:#fff;padding:0}.kertas{box-shadow:none;width:auto;padding:15mm;margin:0}.bar{display:none}@page{size:A4;margin:0}}
</style></head><body>
<div class="bar">
  <span>Form Lupa Absen — <?= h($f['no_form']) ?></span>
  <span><a href="input.php">&larr; Kembali</a> &nbsp; <button onclick="window.print()">🖨 PRINT</button></span>
</div>

<div class="kertas" style="margin-top:60px">
  <?php if($logo): ?><div class="kop"><img src="../lembur/kop_sps.jpeg" alt="PT Supracor Sejahtera"></div><?php endif; ?>

  <h2>FORMULIR KOREKSI LUPA ABSEN</h2>
  <div class="noform">No: <?= h($f['no_form']) ?></div>

  <table class="info">
    <tr><td class="lbl">Divisi / Departemen</td><td>: <?= h($f['nama_dept']??'—') ?></td>
        <td class="lbl">Dibuat Oleh</td><td>: <?= h($f['dibuat_oleh']??'—') ?></td></tr>
    <tr><td class="lbl">Keterangan</td><td colspan="3">: <?= h($f['keterangan']??'—') ?></td></tr>
  </table>

  <table class="data">
    <thead><tr>
      <th style="width:30px">No</th><th style="width:70px">NIK</th><th>Nama Karyawan</th>
      <th style="width:80px">Tanggal</th><th>Jenis</th><th style="width:55px">Masuk</th>
      <th style="width:55px">Pulang</th><th>Alasan</th>
    </tr></thead>
    <tbody>
      <?php foreach($detail as $i=>$d): ?>
      <tr>
        <td class="c"><?= $i+1 ?></td>
        <td class="c"><?= h($d['nik']) ?></td>
        <td><?= h($d['nama_peg']) ?></td>
        <td class="c"><?= tglR($d['tanggal']) ?></td>
        <td class="c"><?= h($jenisLbl[$d['jenis']]??$d['jenis']) ?></td>
        <td class="c"><?= jam($d['jam_masuk']) ?></td>
        <td class="c"><?= jam($d['jam_keluar']) ?></td>
        <td><?= h($d['alasan']??'') ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>

  <div class="tgl-ttd"><?= h($f['nama_dept']??'') ?></div>
  <div class="ttd">
    <div class="kotak">Dibuat oleh,<br>Admin / Pengaju
      <div class="garis">( <?= h($f['dibuat_oleh'] ?: '..........................') ?> )</div></div>
    <div class="kotak">Mengetahui,<br>Atasan Divisi
      <div class="garis">( <?= h($f['atasan_nama'] ?: '..........................') ?> )</div></div>
    <div class="kotak">Menyetujui,<br>HRD
      <div class="garis">( <?= h($f['hr_nama'] ?: 'Laura Dewi Fortuna') ?> )</div></div>
  </div>

  <div class="catatan">* Formulir ini sah setelah dibubuhi tanda tangan atasan divisi & HRD.</div>
</div>

<script>
window.addEventListener('keydown',function(e){if((e.ctrlKey||e.metaKey)&&(e.key==='p'||e.key==='P')){e.preventDefault();alert('Gunakan tombol PRINT di kanan atas.');return false;}});
window.addEventListener('contextmenu',e=>e.preventDefault());
</script>
</body></html>
