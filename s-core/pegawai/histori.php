<?php
/**
 * pegawai/histori.php?id=ID_PEGAWAI
 * Lihat riwayat perubahan data seorang pegawai.
 */
require_once __DIR__ . '/../auth/auth.php';
wajib_izin('pegawai_lihat');
$page_title = "Histori Data Pegawai";
include '../template/header.php';
include '../config/koneksi_sqlsrv.php';
include '../template/sidebar.php';

$id = (int)($_GET['id'] ?? 0);
if (!$id) die("ID pegawai tidak valid.");

$st = sqlsrv_query($conn, "SELECT nik, nama_peg FROM dbo.pegawai WHERE id_peg=?", [$id]);
$peg = $st ? sqlsrv_fetch_array($st, SQLSRV_FETCH_ASSOC) : null;
if (!$peg) die("Pegawai tidak ditemukan.");

$rows = sqlsrv_query($conn,
    "SELECT aksi, kolom, nilai_lama, nilai_baru, diubah_oleh, diubah_pada
     FROM dbo.histori_pegawai WHERE pegawai_id=? ORDER BY diubah_pada DESC, id_histori DESC", [$id]);

function h($v){return htmlspecialchars((string)($v??''));}
function waktu($v){return $v instanceof DateTime?$v->format('d-m-Y H:i'):'—';}
?>

<main id="main" class="main">
  <div class="pagetitle"><h1>Histori Perubahan Data</h1>
    <nav><ol class="breadcrumb">
      <li class="breadcrumb-item"><a href="index.php">Pegawai</a></li>
      <li class="breadcrumb-item"><a href="detail.php?id=<?= $id ?>"><?= h($peg['nama_peg']) ?></a></li>
      <li class="breadcrumb-item active">Histori</li>
    </ol></nav></div>

  <section class="section">
    <div class="card"><div class="card-body">
      <h5 class="card-title"><?= h($peg['nama_peg']) ?> <small class="text-muted">(<?= h($peg['nik']) ?>)</small></h5>
      <div class="table-responsive">
      <table class="table table-sm table-hover">
        <thead class="table-light"><tr>
          <th>Waktu</th><th>Aksi</th><th>Kolom</th><th>Dari</th><th>Menjadi</th><th>Oleh</th>
        </tr></thead>
        <tbody>
        <?php $ada=false; while($r=sqlsrv_fetch_array($rows,SQLSRV_FETCH_ASSOC)): $ada=true; ?>
          <tr>
            <td><small><?= waktu($r['diubah_pada']) ?></small></td>
            <td><span class="badge bg-<?= $r['aksi']==='TAMBAH'?'success':'info' ?>"><?= h($r['aksi']) ?></span></td>
            <td><?= h($r['kolom']??'—') ?></td>
            <td><small class="text-muted"><?= h($r['nilai_lama']) ?: '<em>kosong</em>' ?></small></td>
            <td><small><?= h($r['nilai_baru']) ?: '<em>kosong</em>' ?></small></td>
            <td><small><?= h($r['diubah_oleh']??'—') ?></small></td>
          </tr>
        <?php endwhile; if(!$ada): ?>
          <tr><td colspan="6" class="text-center text-muted py-4">Belum ada histori perubahan.</td></tr>
        <?php endif; ?>
        </tbody>
      </table>
      </div>
      <a href="detail.php?id=<?= $id ?>" class="btn btn-secondary btn-sm">&larr; Kembali</a>
    </div></div>
  </section>
</main>

<?php include '../template/footer.php'; ?>
