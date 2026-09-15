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

/* ---- kop: No. Revisi & Tanggal mengikuti data surat ---- */
$v_revisi = sprintf('%02d', $blank ? 0 : (int)kol($f,'revisi'));
if ($blank) {
    $v_tglkop = d_indo(date('Y-m-d'));
} else {
    $sumber = kol($f,'diubah_pada');
    if (!$sumber) $sumber = $f['dibuat_pada'] ?? $f['tanggal_sp'];
    $v_tglkop = d_indo($sumber);
}

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
  .c-logo img { height: 15mm; display:block; margin: 2px auto 1px; }
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
    body { background:#fff; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    .c-logo img { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    .sheet { margin:0; width:auto; }
    .bar { display:none !important; }
  }
</style>
</head>
<body>

<div class="bar">
  <span>FM-HRD.22 &mdash; Surat Peringatan <?= h($v_nomor) ?> &middot; Rev. <?= h($v_revisi) ?></span>
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
          <tr><td class="k">No. Revisi.</td><td>: <?= h($v_revisi) ?></td></tr>
          <tr><td class="k">Tanggal</td><td>: <?= h($v_tglkop) ?></td></tr>
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
        <td><span class="ln w-nomor"><?= h($v_nomor) ?></span><?php if (!$blank && (int)kol($f,'revisi') > 0): ?><span style="font-size:9pt;padding-left:4mm">(Revisi ke-<?= (int)kol($f,'revisi') ?><?= kol($f,'diubah_oleh') ? ' oleh '.h(kol($f,'diubah_oleh')) : '' ?>)</span><?php endif; ?></td>
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
