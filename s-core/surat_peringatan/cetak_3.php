<?php
/**
 * s-core/surat_peringatan/cetak.php
 * Cetak Surat Peringatan mengikuti FORMULIR FM-HRD.22 (SPS/FM-HRD.22 Rev.00).
 * Layout dibuat persis seperti file Word aslinya saat di-print (A4).
 *
 * Pemakaian:
 *   cetak.php?id=12          -> cetak SP tersimpan
 *   cetak.php?id=12&auto=1   -> langsung buka dialog print
 *   cetak.php?blank=1        -> cetak formulir kosong untuk diisi tangan
 */
require_once __DIR__ . '/../auth/auth.php';
wajib_izin('modul_hr');

function h($v){ return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8'); }
function d_indo($v){
    $b=['','Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
    if ($v instanceof DateTime) return $v->format('j').' '.$b[(int)$v->format('n')].' '.$v->format('Y');
    if (!$v) return '';
    $t=strtotime((string)$v); return $t? date('j',$t).' '.$b[(int)date('n',$t)].' '.date('Y',$t) : '';
}
function d_short($v){
    if ($v instanceof DateTime) return $v->format('d-m-Y');
    if (!$v) return '';
    $t=strtotime((string)$v); return $t? date('d-m-Y',$t) : '';
}
/** ambil kolom opsional yang mungkin belum ada di tabel */
function kol($f,$nama){ return isset($f[$nama]) ? $f[$nama] : ''; }

$blank = isset($_GET['blank']);
$f = [];

if (!$blank) {
    require_once __DIR__ . '/../config/koneksi_sqlsrv.php';
    $id = (int)($_GET['id'] ?? 0);
    $rs = sqlsrv_query($conn,
        "SELECT s.*, p.nama_peg, p.nik, u.nama_unit
           FROM dbo.surat_peringatan s
           JOIN dbo.pegawai p ON p.id_peg = s.pegawai_id
      LEFT JOIN dbo.unit_kerja u ON u.id = p.unit_kerja_id
          WHERE s.id_sp = ?", [$id]);
    $f = $rs ? sqlsrv_fetch_array($rs, SQLSRV_FETCH_ASSOC) : null;
    if (!$f) { die('Data surat peringatan tidak ditemukan.'); }
}

/* ---- isi baris "Karena yang bersangkutan telah melakukan pelanggaran ..." ---- */
$uraian = '';
if (!$blank) {
    $bag = [];
    if (trim((string)$f['jenis_pelanggaran']) !== '') $bag[] = trim($f['jenis_pelanggaran']);
    if (trim((string)($f['kronologi'] ?? '')) !== '') $bag[] = trim($f['kronologi']);
    if (trim((string)($f['dasar_pelanggaran'] ?? '')) !== '') $bag[] = 'Dasar: '.trim($f['dasar_pelanggaran']);
    $uraian = implode("\n", $bag);
}

/* ---- tentukan nomor sanksi yang dilingkari (1..5) ---- */
$sanksi = 0;
if (!$blank) {
    $kat = strtoupper(preg_replace('/\s+/', ' ', trim((string)$f['kategori_sp'])));
    $tind = strtolower((string)($f['tindakan'] ?? ''));
    if (strpos($tind,'phk') !== false || strpos($tind,'pemutusan') !== false)      $sanksi = 5;
    elseif (strpos($tind,'skorsing') !== false)                                     $sanksi = 4;
    elseif ($kat === 'SP III' || $kat === 'SP 3')                                    $sanksi = 3;
    elseif ($kat === 'SP II'  || $kat === 'SP 2')                                    $sanksi = 2;
    elseif ($kat === 'SP I'   || $kat === 'SP 1')                                    $sanksi = 1;
}

$u = function_exists('user_login') ? user_login() : null;

$v_nomor    = $blank ? '' : $f['no_surat'];
$v_nama     = $blank ? '' : $f['nama_peg'];
$v_jabatan  = $blank ? '' : ($f['nama_unit'] ?? '');
$v_nik      = $blank ? '' : $f['nik'];
$v_tglpel   = $blank ? '' : d_short($f['tanggal_sp']);
$v_tempat   = $blank ? '' : kol($f,'tempat_pelanggaran');
$v_kerugian = $blank ? '' : kol($f,'kerugian_material');
$v_saksi    = $blank ? '' : kol($f,'saksi_bukti');
$v_pukul    = $blank ? '' : kol($f,'pukul_pelanggaran');
$v_skorsing = $blank ? '' : kol($f,'lama_skorsing');
$v_tglsurat = $blank ? '' : d_indo($f['tanggal_sp']);
$v_hrd      = $blank ? '' : ($f['dibuat_oleh'] ?? ($u['nama_lengkap'] ?? ''));
$v_karyawan = $blank ? '' : $f['nama_peg'];

$LOGO = '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';
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<title>FM-HRD.22 - Surat Peringatan <?= h($v_nomor) ?></title>
<style>
  @page { size: A4 portrait; margin: 20mm 15mm 12mm 15mm; }
  html, body { margin:0; padding:0; }
  body { font-family: Arial, Helvetica, sans-serif; font-size: 11pt; color:#000; background:#e9e9e9; }
  .sheet { width: 180mm; margin: 8mm auto 20mm; background:#fff; padding:0; }
  table { border-collapse: collapse; }

  /* ---------- KOP FORMULIR ---------- */
  .hdr { width:100%; }
  .hdr, .hdr > tbody > tr > td { border: 1px solid #000; }
  .hdr > tbody > tr > td { padding: 2px 4px; vertical-align: middle; }
  .c-logo { width: 29.8%; text-align: center; }
  .c-mid  { width: 35.1%; text-align: center; }
  .c-doc  { width: 35.1%; padding:0 !important; }
  .c-logo img { height: 13mm; display:block; margin: 2px auto 1px; }
  .company  { font-size: 9pt; font-weight: bold; }
  .formulir { font-size: 14pt; font-weight: bold; }
  .judul    { font-size: 12pt; font-weight: bold; text-align:center; height: 12mm; }
  .title    { font-size: 14pt; font-weight: bold; text-align:center; }
  .docinfo { width:100%; font-size: 9.5pt; }
  .docinfo td { border:0; border-bottom: 1px solid #000; padding: 1px 4px; height: 5.4mm; }
  .docinfo tr:last-child td { border-bottom: 0; }
  .docinfo td.k { width: 47%; }

  /* ---------- ISI FORMULIR ---------- */
  .body { width:100%; border:1px solid #000; border-top:0; }
  .body > tbody > tr > td { border:0; padding:0; vertical-align: top; }
  .sec  { padding:0 !important; }
  .sec2 { padding:0 !important; border-top:1px solid #000 !important; }
  .pad1 { padding: 26px 24px 10px 24px; }
  .pad2 { padding: 15px 24px 18px 24px; }

  table.f { border:0; table-layout: fixed; }
  table.f.w1 { width: 100%; }
  table.f td { border:0; padding: 0 0 4mm 0; vertical-align: bottom; white-space: nowrap; }
  table.f td.l { width: 42mm; }
  table.f td.s { width: 4mm; }
  .ind { margin-left: 12mm; width: 88% !important; }

  /* garis isian: tampil sebagai underline seperti form Word */
  .ln { display:inline-block; border-bottom:1px solid #000; height:5.4mm; line-height:5.4mm;
        padding:0 2px; font-size:11pt; white-space:nowrap; vertical-align:bottom; }
  .w-nomor{min-width:65mm} .w-full{min-width:92mm} .w-sm{min-width:30mm} .w-md{min-width:30mm}
  .w-sk{min-width:40mm} .w-kota{min-width:44mm} .w-ttd{min-width:40mm; text-align:center}

  .uraian { min-height:22mm; line-height:6mm; padding-top:1mm; white-space:pre-wrap;
            word-wrap:break-word; font-size:11pt; }

  .sanksi-t { font-weight:bold; margin: 3mm 0 2mm; }
  ol.sanksi { margin: 0 0 0 4mm; padding: 0 0 0 7mm; }
  ol.sanksi li { margin-bottom: 1.8mm; }
  ol.sanksi li.on { font-weight: bold; }
  ol.sanksi li.on > span.pilih { outline: 1.2px solid #000; outline-offset: 2.5px; border-radius: 10px; }

  .know { text-align:center; margin-top: 4mm; }
  .ttd { width:100%; border:0; margin-top:1mm; }
  .ttd td { border:0; text-align:center; padding:0; }
  .ttd .sp { height: 20mm; }

  /* ---------- toolbar (tidak ikut tercetak) ---------- */
  .bar { position:sticky; top:0; background:#b42318; color:#fff; padding:8px 16px;
         display:flex; justify-content:space-between; align-items:center; font-size:11pt; }
  .bar a, .bar button { color:#b42318; background:#fff; border:0; padding:6px 14px;
         margin-left:6px; text-decoration:none; cursor:pointer; border-radius:3px; font-size:11pt; }
  @media print {
    body { background:#fff; }
    .sheet { margin:0; width:auto; }
    .bar { display:none !important; }
  }
</style>
</head>
<body>

<div class="bar">
  <span>FM-HRD.22 &mdash; Surat Peringatan <?= h($v_nomor) ?></span>
  <span>
    <a href="index.php">Kembali</a>
    <a href="cetak.php?blank=1" target="_blank">Form Kosong</a>
    <button onclick="window.print()">CETAK</button>
  </span>
</div>

<div class="sheet">

  <table class="hdr">
    <tr>
      <td class="c-logo">
        <img src="data:image/png;base64,<?= $LOGO ?>" alt="SPS">
        <div class="company">PT. Supracor Sejahtera</div>
      </td>
      <td class="c-mid"><span class="formulir">FORMULIR</span></td>
      <td class="c-doc">
        <table class="docinfo">
          <tr><td class="k">No.Dokumen</td><td>: SPS/FM-HRD.22</td></tr>
          <tr><td class="k">No. Revisi.</td><td>: 00</td></tr>
          <tr><td class="k">Tanggal</td><td>: 05 Oktober 2006</td></tr>
          <tr><td class="k">Halaman</td><td>: 1 / 1</td></tr>
        </table>
      </td>
    </tr>
    <tr>
      <td class="judul">JUDUL :</td>
      <td colspan="2" class="title">SURAT PERINGATAN</td>
    </tr>
  </table>

  <table class="body">
    <tr><td class="sec"><div class="pad1">

      <table class="f w1"><tr>
        <td class="l">Nomor</td><td class="s">:</td>
        <td><span class="ln w-nomor"><?= h($v_nomor) ?></span></td>
      </tr></table>

      <div style="height:3mm"></div>
      <div>Dengan ini memberikan Surat Peringatan Tertulis kepada :</div>
      <div style="height:3mm"></div>

      <table class="f w1 ind">
        <tr><td class="l">Nama</td><td class="s">:</td><td><span class="ln w-full"><?= h($v_nama) ?></span></td></tr>
        <tr><td class="l">Jabatan / Bagian</td><td class="s">:</td><td><span class="ln w-full"><?= h($v_jabatan) ?></span></td></tr>
        <tr><td class="l">Nomor Karyawan</td><td class="s">:</td><td><span class="ln w-full"><?= h($v_nik) ?></span></td></tr>
      </table>

      <div style="height:4mm"></div>
      <div>Karena yang bersangkutan telah melakukan pelanggaran tata tertib / disiplin kerja sbb :</div>
      <div class="uraian"><?= h($uraian) ?></div>

    </div></td></tr>

    <tr><td class="sec2"><div class="pad2">

      <table class="f w1">
        <tr>
          <td class="l">Tgl. Pelanggaran</td><td class="s">:</td>
          <td style="width:42mm"><span class="ln w-sm"><?= h($v_tglpel) ?></span></td>
          <td style="width:34mm">Saksi / Bukti</td><td class="s">:</td>
          <td><span class="ln w-md"><?= h($v_saksi) ?></span></td>
        </tr>
        <tr>
          <td class="l">Tempat</td><td class="s">:</td>
          <td><span class="ln w-sm"><?= h($v_tempat) ?></span></td>
          <td>Pukul</td><td class="s">:</td>
          <td><span class="ln w-md"><?= h($v_pukul) ?></span> WIB</td>
        </tr>
        <tr>
          <td class="l">Kerugian Material</td><td class="s">:</td>
          <td><span class="ln w-sm"><?= h($v_kerugian) ?></span></td>
          <td></td><td></td><td></td>
        </tr>
      </table>

      <div class="sanksi-t">Sanksi yang diberikan :</div>
      <ol class="sanksi">
        <li class="<?= $sanksi===1?'on':'' ?>"><span class="pilih">Peringatan Pertama</span></li>
        <li class="<?= $sanksi===2?'on':'' ?>"><span class="pilih">Peringatan Kedua</span></li>
        <li class="<?= $sanksi===3?'on':'' ?>"><span class="pilih">Peringatan Ketiga / Terakhir</span></li>
        <li class="<?= $sanksi===4?'on':'' ?>"><span class="pilih">Skorsing Pembinaan Selama</span> <span class="ln w-sk"><?= h($v_skorsing) ?></span></li>
        <li class="<?= $sanksi===5?'on':'' ?>"><span class="pilih">Pemutusan Hubungan Kerja ( PHK )</span></li>
      </ol>

      <table class="f w1" style="margin-top:3mm"><tr>
        <td style="width:26mm">Mojokerto,</td><td class="s"></td>
        <td><span class="ln w-kota"><?= h($v_tglsurat) ?></span></td>
      </tr></table>

      <div class="know">Mengetahui,</div>
      <table class="ttd">
        <tr>
          <td>HRD &amp; GA,</td>
          <td>Chieft Terkait,</td>
          <td>Yang bersangkutan,</td>
        </tr>
        <tr><td class="sp" colspan="3"></td></tr>
        <tr>
          <td>( <span class="ln w-ttd"><?= h($v_hrd) ?></span> )</td>
          <td>( <span class="ln w-ttd"></span> )</td>
          <td>( <span class="ln w-ttd"><?= h($v_karyawan) ?></span> )</td>
        </tr>
      </table>

    </div></td></tr>
  </table>

</div>

<?php if (isset($_GET['auto'])): ?>
<script>window.addEventListener('load', function(){ window.print(); });</script>
<?php endif; ?>
</body>
</html>
