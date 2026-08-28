<?php
/**
 * lembur/cetak.php?id=ID_FORM
 * Cetak form lembur kolektif untuk TTD atasan divisi.
 * - Ctrl+P & klik-kanan diblokir; hanya tombol Print yang berfungsi.
 */
require_once __DIR__ . '/../auth/auth.php';
wajib_izin('lembur_input');
require_once __DIR__ . '/../config/koneksi_sqlsrv.php';

$id = (int)($_GET['id'] ?? 0);
if (!$id) die("ID form tidak valid.");

/* header form */
$st = sqlsrv_query($conn,
    "SELECT lf.*, d.nama_dept
     FROM dbo.lembur_form lf
     LEFT JOIN dbo.department d ON d.id_dept = lf.department_id
     WHERE lf.id_form = ?", [$id]);
$f = $st ? sqlsrv_fetch_array($st, SQLSRV_FETCH_ASSOC) : null;
if (!$f) die("Form lembur tidak ditemukan.");

/* detail karyawan */
$rows = sqlsrv_query($conn,
    "SELECT ld.*, p.nik, p.nama_peg
     FROM dbo.lembur_detail ld
     JOIN dbo.pegawai p ON p.id_peg = ld.pegawai_id
     WHERE ld.id_form = ?
     ORDER BY p.nama_peg", [$id]);
$detail = [];
$totalJam = 0;
while ($r = sqlsrv_fetch_array($rows, SQLSRV_FETCH_ASSOC)) { $detail[] = $r; $totalJam += (float)$r['durasi_jam']; }
$halaman = array_chunk($detail, 5);

function h($v){return htmlspecialchars((string)($v??''));}
function jam($v){return $v instanceof DateTime ? $v->format('H:i') : '—';}
function tglIndo($v){
    if (!($v instanceof DateTime)) return '—';
    $bln = ['','Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
    return $v->format('d').' '.$bln[(int)$v->format('n')].' '.$v->format('Y');
}
$jenisLabel = ['biasa'=>'Hari Kerja Biasa','libur'=>'Hari Libur/Weekend','hari_besar'=>'Hari Besar'][$f['jenis']] ?? $f['jenis'];
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<title>Form Lembur <?= h($f['no_form']) ?></title>
<style>
  *{box-sizing:border-box;margin:0;padding:0}
  body{font-family:'Times New Roman',serif;color:#000;background:#e5e5e5;padding:20px}
  .kertas{background:#fff;width:210mm;min-height:148mm;margin:60px auto 0;padding:8mm;box-shadow:0 0 10px rgba(0,0,0,.2)}
  .kop{border-bottom:2px double #000;padding-bottom:6px;margin-bottom:4px}
  .kop img{height:60px;margin-right:16px}
  .kop .nama{font-size:20px;font-weight:bold}
  .kop .alamat{font-size:11px}
  h2{text-align:center;text-decoration:underline;margin:10px 0 3px;font-size:13px}
  .noform{text-align:center;font-size:10px;margin-bottom:10px}
  table.info{width:100%;font-size:10px;margin-bottom:8px}
  table.info td{padding:2px 0;vertical-align:top}
  table.info td.lbl{width:90px}
  table.data{width:100%;border-collapse:collapse;font-size:9px;margin-bottom:12px}
  table.data th,table.data td{border:1px solid #000;padding:3px 4px}
  table.data th{background:#f0f0f0;text-align:center}
  table.data td.c{text-align:center}
  .tgl-ttd{text-align:right;font-size:10px;margin-top:14px;margin-bottom:4px}
  .ttd{display:flex;justify-content:space-between;gap:10px}
  .ttd .kotak{flex:1;text-align:center;font-size:10px}
  .ttd .garis{margin-top:40px;border-top:1px solid #000;padding-top:3px}
  .catatan{font-size:8px;margin-top:6px;color:#333}
  .nomor-halaman{text-align:right;font-size:8px;margin-top:4px;color:#555}

  /* tombol print - hilang saat dicetak */
  .bar{position:fixed;top:0;left:0;right:0;background:#c00;color:#fff;padding:12px 20px;
       display:flex;justify-content:space-between;align-items:center;z-index:100}
  .bar button{background:#fff;color:#c00;border:none;padding:8px 20px;border-radius:5px;
       font-size:14px;font-weight:bold;cursor:pointer}
  .bar button:hover{background:#eee}
  .bar a{color:#fff;text-decoration:underline;font-size:13px}

  @media print{
    body{background:#fff;padding:0}
    .kertas{box-shadow:none;width:210mm;height:148mm;min-height:148mm;padding:5mm;margin:0;overflow:hidden}
    .bar{display:none}
    .kertas{page-break-after:always}
    .kertas:last-of-type{page-break-after:auto}
    @page{size:A4 portrait;margin:0}
  }
</style>
</head>
<body>

<div class="bar">
  <span>Form Lembur — <?= h($f['no_form']) ?></span>
  <span>
    <a href="rekap_hr.php">&larr; Kembali</a>
    &nbsp;&nbsp;
    <button onclick="cetak()">🖨 PRINT</button>
  </span>
</div>

<?php foreach ($halaman as $nomorHalaman => $dataHalaman):
  $totalJamHalaman = 0;
  foreach ($dataHalaman as $d) { $totalJamHalaman += (float)$d['durasi_jam']; }
?>
<div class="kertas">
  <div class="kop">
    <img src="kop_sps.jpeg" alt="PT Supracor Sejahtera" style="width:100%;height:auto;display:block">
  </div>

  <h2>FORMULIR PENGAJUAN LEMBUR</h2>
  <div class="noform">No: <?= h($f['no_form']) ?></div>

  <table class="info">
    <tr><td class="lbl">Divisi / Departemen</td><td>: <?= h($f['nama_dept'] ?? '—') ?></td>
        <td class="lbl">Tanggal Lembur</td><td>: <?= tglIndo($f['tanggal']) ?></td></tr>
    <tr><td class="lbl">Jenis Lembur</td><td>: <?= h($jenisLabel) ?></td>
        <td class="lbl">Dibuat Oleh</td><td>: <?= h($f['dibuat_nama'] ?? '—') ?></td></tr>
    <tr><td class="lbl">Keterangan</td><td colspan="3">: <?= h($f['keterangan'] ?? '—') ?></td></tr>
  </table>

  <table class="data">
    <thead>
      <tr>
        <th style="width:30px">No</th>
        <th style="width:70px">NIK</th>
        <th>Nama Karyawan</th>
        <th style="width:85px">Jam</th>
        <th style="width:55px">Istirahat</th>
        <th style="width:60px">Durasi</th>
        <th>Uraian Pekerjaan</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($dataHalaman as $i => $d): ?>
      <tr>
        <td class="c"><?= ($nomorHalaman * 5) + $i + 1 ?></td>
        <td class="c"><?= h($d['nik']) ?></td>
        <td><?= h($d['nama_peg']) ?></td>
        <td class="c"><?= jam($d['jam_mulai']) ?> - <?= jam($d['jam_selesai']) ?></td>
        <td class="c"><?= number_format((float)($d['istirahat_jam'] ?? 0),1) ?> jam</td>
        <td class="c"><?= number_format($d['durasi_jam'],1) ?> jam</td>
        <td><?= h($d['uraian'] ?? '') ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>

  <div class="tgl-ttd"><?= h($f['nama_dept'] ?? '') ?>, <?= tglIndo($f['tanggal']) ?></div>
  <div class="ttd">
    <div class="kotak">
      Dibuat oleh,<br>Admin / Pengaju
      <div class="garis">( <?= h($f['dibuat_nama'] ?: '..........................') ?> )</div>
    </div>
    <div class="kotak">
      Mengetahui,<br>Supervisor Divisi
      <div class="garis">( <?= h($f['atasan_nama'] ?: '..........................') ?> )</div>
    </div>
    <div class="kotak">
      Menyetujui,<br>Manager Pabrik
      <div class="garis">( David L. )</div>
    </div>
  </div>

  <div class="catatan">
    * Formulir ini sah setelah dibubuhi tanda tangan atasan divisi terkait.<br>
    * Batas lembur mengacu PP 35/2021: maksimal 4 jam/hari, 18 jam/minggu.
  </div>
  <div class="nomor-halaman">Halaman <?= $nomorHalaman + 1 ?> dari <?= count($halaman) ?></div>
</div>
<?php endforeach; ?>

<script>
// blokir Ctrl+P / Cmd+P -> arahkan pakai tombol
window.addEventListener('keydown', function(e){
  if ((e.ctrlKey || e.metaKey) && (e.key === 'p' || e.key === 'P')) {
    e.preventDefault();
    alert('Untuk mencetak, gunakan tombol PRINT berwarna di kanan atas.');
    return false;
  }
});
// blokir klik kanan
window.addEventListener('contextmenu', function(e){ e.preventDefault(); });

function cetak(){ window.print(); }
</script>
</body>
</html>
