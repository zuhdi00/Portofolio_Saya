<?php
/**
 * pengunduran_diri/cetak.php?id=ID_PENGUNDURAN
 * Cetak form pengunduran diri untuk TTD
 */
require_once __DIR__ . '/../auth/auth.php';
wajib_izin('modul_hr');
require_once __DIR__ . '/../config/koneksi_sqlsrv.php';

$id = (int)($_GET['id'] ?? 0);
if (!$id) die("ID pengunduran diri tidak valid.");

// Ambil data
$st = sqlsrv_query($conn, "
    SELECT pd.*, p.nik, p.nama_peg, d.nama_dept 
    FROM dbo.pengunduran_diri pd
    JOIN dbo.pegawai p ON p.id_peg = pd.pegawai_id
    LEFT JOIN dbo.unit_kerja u ON u.id = p.unit_kerja_id
    LEFT JOIN dbo.department d ON d.id_dept = u.department_id
    WHERE pd.id_pengunduran = ?
", [$id]);

$f = $st ? sqlsrv_fetch_array($st, SQLSRV_FETCH_ASSOC) : null;
if (!$f) die("Data pengunduran diri tidak ditemukan.");

function h($v){ return htmlspecialchars((string)($v??'')); }
function tglIndo($v){
    if (!($v instanceof DateTime)) return '—';
    $bln = ['','Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
    return $v->format('d').' '.$bln[(int)$v->format('n')].' '.$v->format('Y');
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<title>Form Pengunduran Diri - <?= h($f['nama_peg']) ?></title>
<style>
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body { font-family: 'Times New Roman', serif; color: #000; background: #e5e5e5; padding: 20px; }
  .kertas { background: #fff; width: 210mm; min-height: 297mm; margin: 0 auto; padding: 20mm; box-shadow: 0 0 10px rgba(0,0,0,.2); }
  .kop { border-bottom: 3px double #000; padding-bottom: 10px; margin-bottom: 20px; }
  h2 { text-align: center; text-decoration: underline; margin: 10px 0 20px; font-size: 18px; }
  
  table.info { width: 100%; font-size: 14px; margin-bottom: 20px; border-collapse: collapse; }
  table.info td { padding: 8px; border: 1px solid #000; vertical-align: top; }
  table.info td.lbl { width: 30%; font-weight: bold; background: #f9f9f9; }

  .ttd { margin-top: 60px; display: flex; justify-content: space-between; }
  .ttd .kotak { width: 200px; text-align: center; font-size: 14px; }
  .ttd .garis { margin-top: 80px; border-top: 1px solid #000; padding-top: 4px; font-weight: bold; }

  /* tombol print - hilang saat dicetak */
  .bar { position: fixed; top: 0; left: 0; right: 0; background: #c00; color: #fff; padding: 12px 20px;
         display: flex; justify-content: space-between; align-items: center; z-index: 100; }
  .bar button { background: #fff; color: #c00; border: none; padding: 8px 20px; border-radius: 5px;
         font-size: 14px; font-weight: bold; cursor: pointer; }
  .bar button:hover { background: #eee; }
  .bar a { color: #fff; text-decoration: underline; font-size: 14px; }

  @media print {
    body { background: #fff; padding: 0; }
    .kertas { box-shadow: none; width: auto; min-height: auto; padding: 15mm; margin: 0; }
    .bar { display: none; }
    @page { size: A4; margin: 0; }
  }
</style>
</head>
<body>

<div class="bar">
  <span>Cetak Form Pengunduran Diri</span>
  <span>
    <a href="index.php">&larr; Kembali</a>
    &nbsp;&nbsp;
    <button onclick="cetak()">🖨 PRINT</button>
  </span>
</div>

<div class="kertas" style="margin-top:60px">
  <div class="kop">
    <img src="../lembur/kop_sps.jpeg" alt="PT Supracor Sejahtera" style="width:100%;height:auto;display:block" onerror="this.src='kop_sps.jpeg'">
  </div>

  <h2>FORMULIR PENGUNDURAN DIRI KARYAWAN</h2>

  <table class="info">
    <tr>
      <td class="lbl">Nama Karyawan</td>
      <td><?= h($f['nama_peg']) ?></td>
    </tr>
    <tr>
      <td class="lbl">NIK</td>
      <td><?= h($f['nik']) ?></td>
    </tr>
    <tr>
      <td class="lbl">Divisi / Departemen</td>
      <td><?= h($f['nama_dept'] ?? '—') ?></td>
    </tr>
    <tr>
      <td class="lbl">Tanggal Diajukan</td>
      <td><?= tglIndo($f['tanggal_pengajuan']) ?></td>
    </tr>
    <tr>
      <td class="lbl">Tanggal Efektif Resign</td>
      <td><?= tglIndo($f['tanggal_efektif']) ?></td>
    </tr>
    <tr>
      <td class="lbl">Alasan Pengunduran Diri</td>
      <td><?= nl2br(h($f['alasan'])) ?></td>
    </tr>
    <tr>
      <td class="lbl">Keterangan / Notes HR</td>
      <td><?= nl2br(h($f['keterangan'])) ?></td>
    </tr>
    <tr>
      <td class="lbl">Penilaian Kerja (Singkat)</td>
      <td><?= nl2br(h($f['penilaian_kerja'])) ?></td>
    </tr>
  </table>

  <div style="margin-top:20px; font-size:13px; text-align:justify; line-height:1.5;">
    Dengan ditandatanganinya formulir ini, maka yang bersangkutan menyatakan mengundurkan diri secara sadar dan tanpa paksaan dari pihak manapun. Hak dan kewajiban terkait pengunduran diri akan diselesaikan sesuai dengan peraturan perusahaan yang berlaku.
  </div>

  <div class="ttd">
    <div class="kotak">
      Diajukan Oleh,<br>Karyawan
      <div class="garis">( <?= h($f['nama_peg']) ?> )</div>
    </div>
    <div class="kotak">
      Mengetahui,<br>Supervisor Divisi
      <div class="garis">( ........................................ )</div>
    </div>
    <div class="kotak">
      Menyetujui,<br>Manager Pabrik
      <div class="garis">( David L. )</div>
    </div>
  </div>
</div>

<script>
// Blokir Ctrl+P / Cmd+P agar memakai tombol
window.addEventListener('keydown', function(e){
  if ((e.ctrlKey || e.metaKey) && (e.key === 'p' || e.key === 'P')) {
    e.preventDefault();
    alert('Untuk mencetak, gunakan tombol PRINT berwarna di kanan atas.');
    return false;
  }
});
window.addEventListener('contextmenu', function(e){ e.preventDefault(); });

function cetak(){ window.print(); }
</script>
</body>
</html>
