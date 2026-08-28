<?php
/**
 * pengunduran_diri/penilaian.php?id=ID
 * Atasan isi catatan penilaian -> otomatis ACC (status DISETUJUI).
 */
require_once __DIR__ . '/../auth/auth.php';
wajib_login();   // atasan/admin_it/hr
require_once __DIR__ . '/../config/koneksi_sqlsrv.php';

$id = (int)($_GET['id'] ?? 0);
if (!$id) die("ID tidak valid.");

$st = sqlsrv_query($conn,"SELECT * FROM dbo.pengunduran_diri WHERE id_pengunduran=?",[$id]);
$r = $st ? sqlsrv_fetch_array($st,SQLSRV_FETCH_ASSOC) : null;
if (!$r) die("Data tidak ditemukan.");

$pesan='';
if ($_SERVER['REQUEST_METHOD']==='POST') {
    $nama    = trim($_POST['atasan_nama'] ?? '');
    $catatan = trim($_POST['atasan_catatan'] ?? '');
    if ($catatan==='') {
        $pesan="<div class='alert alert-danger'>Catatan penilaian wajib diisi.</div>";
    } else {
        // isi penilaian + otomatis ACC
        sqlsrv_query($conn,
            "UPDATE dbo.pengunduran_diri
             SET atasan_nama=?, atasan_catatan=?, penilaian_kerja=?, atasan_pada=GETDATE(), status='DISETUJUI'
             WHERE id_pengunduran=?",
            [$nama?:null, $catatan, $catatan, $id]);
        $pesan="<div class='alert alert-success'>Penilaian tersimpan & surat resign otomatis <strong>DISETUJUI</strong>.</div>";
        $st = sqlsrv_query($conn,"SELECT * FROM dbo.pengunduran_diri WHERE id_pengunduran=?",[$id]);
        $r = sqlsrv_fetch_array($st,SQLSRV_FETCH_ASSOC);
    }
}

$page_title="Penilaian Resign";
include '../template/header.php';
include '../template/sidebar.php';
function h($v){return htmlspecialchars((string)($v??''));}
?>
<main id="main" class="main">
  <div class="pagetitle"><h1>Penilaian Atasan</h1></div>
  <section class="section">
    <?= $pesan ?>
    <div class="card"><div class="card-body">
      <h5 class="card-title"><?= h($r['no_surat']) ?> — <?= h($r['nama_pegawai']) ?></h5>
      <p><span class="badge bg-<?= $r['status']==='DISETUJUI'?'success':'warning' ?>"><?= h($r['status']) ?></span></p>

      <?php if($r['status']==='DISETUJUI'): ?>
        <div class="alert alert-success">
          <strong>Sudah dinilai & disetujui.</strong><br>
          Oleh: <?= h($r['atasan_nama']??'—') ?><br>
          Catatan: <?= nl2br(h($r['atasan_catatan'] ?: ($r['penilaian_kerja']??''))) ?>
        </div>
        <a href="index.php" class="btn btn-secondary">Kembali</a>
      <?php else: ?>
        <p class="text-muted small">Isi catatan penilaian karyawan yang resign. Menyimpan akan
           otomatis menyetujui (ACC) surat resign.</p>
        <form method="POST">
          <div class="mb-2"><label class="form-label small">Nama Atasan</label>
            <input type="text" name="atasan_nama" class="form-control" placeholder="Nama atasan penilai"></div>
          <div class="mb-3"><label class="form-label small">Catatan Penilaian</label>
            <textarea name="atasan_catatan" class="form-control" rows="4" required
                      placeholder="catatan kinerja/sikap karyawan selama bekerja"></textarea></div>
          <button class="btn btn-success"><i class="bi bi-check-circle"></i> Simpan & Setujui</button>
          <a href="index.php" class="btn btn-secondary">Batal</a>
        </form>
      <?php endif; ?>
    </div></div>
  </section>
</main>
<?php include '../template/footer.php'; ?>
