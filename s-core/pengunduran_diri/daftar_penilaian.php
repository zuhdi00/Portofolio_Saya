<?php
/**
 * pengunduran_diri/daftar_penilaian.php
 * HRD & Admin IT melihat semua penilaian atasan atas karyawan yang resign.
 */
require_once __DIR__ . '/../auth/auth.php';
wajib_izin('modul_hr');
require_once __DIR__ . '/../config/koneksi_sqlsrv.php';

$cari = trim($_GET['cari'] ?? '');
$fkl  = trim($_GET['klasifikasi'] ?? '');

$where = ["1=1"]; $params = [];
if ($cari !== '') { $where[] = "(r.nama_pegawai LIKE ? OR r.no_surat LIKE ?)"; $params[]="%$cari%"; $params[]="%$cari%"; }
if ($fkl  !== '') { $where[] = "r.klasifikasi = ?"; $params[] = $fkl; }

$rows = sqlsrv_query($conn,
  "SELECT r.*, d.nama_dept
   FROM dbo.pengunduran_diri r
   LEFT JOIN dbo.department d ON d.id_dept = r.department_id
   WHERE ".implode(' AND ',$where)."
   ORDER BY r.dibuat_pada DESC", $params);

$KLASIFIKASI = [];
$rsK = @sqlsrv_query($conn, "SELECT kode,label FROM dbo.klasifikasi_resign ORDER BY urutan,kode");
if ($rsK) { while($k=sqlsrv_fetch_array($rsK,SQLSRV_FETCH_ASSOC)) $KLASIFIKASI[$k['kode']]=$k['label']; sqlsrv_free_stmt($rsK); }

$page_title="Penilaian Karyawan Resign";
include '../template/header.php';
include '../template/sidebar.php';
function h($v){return htmlspecialchars((string)($v??''));}
function tgl($v){return $v instanceof DateTime?$v->format('d-m-Y'):'—';}
function waktu($v){return $v instanceof DateTime?$v->format('d-m-Y H:i'):'—';}
?>
<main id="main" class="main">
  <div class="pagetitle d-flex justify-content-between align-items-start flex-wrap">
    <div><h1>Penilaian Karyawan Resign</h1>
    <nav><ol class="breadcrumb"><li class="breadcrumb-item"><a href="index.php">Pengunduran Diri</a></li>
    <li class="breadcrumb-item active">Penilaian</li></ol></nav></div>
    <div class="pt-2">
      <a href="index.php" class="btn btn-outline-primary btn-sm"><i class="bi bi-arrow-left"></i> Pengunduran Diri</a>
      <a href="kelola_klasifikasi.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-tags"></i> Kelola Klasifikasi</a>
    </div>
  </div>

  <section class="section">
    <form method="GET" class="row g-2 mb-3">
      <div class="col-md-4"><input type="text" name="cari" class="form-control form-control-sm"
             placeholder="cari nama / no surat" value="<?= h($cari) ?>"></div>
      <div class="col-md-3">
        <select name="klasifikasi" class="form-select form-select-sm">
          <option value="">-- semua klasifikasi --</option>
          <?php foreach($KLASIFIKASI as $k=>$lbl): ?>
            <option value="<?= h($k) ?>" <?= $fkl===$k?'selected':'' ?>><?= h($lbl) ?></option>
          <?php endforeach; ?>
        </select></div>
      <div class="col-md-2"><button class="btn btn-sm btn-primary">Filter</button></div>
    </form>

    <div class="card"><div class="card-body">
      <h5 class="card-title">Daftar Penilaian Atasan</h5>
      <div class="table-responsive">
      <table class="table table-hover align-middle">
        <thead class="table-light"><tr>
          <th>No Surat</th><th>Nama</th><th>Divisi</th><th>Efektif</th>
          <th>Klasifikasi</th><th>Penilaian Atasan</th><th>Dinilai Oleh</th><th>Status</th>
        </tr></thead>
        <tbody>
        <?php $ada=false; while($r=sqlsrv_fetch_array($rows,SQLSRV_FETCH_ASSOC)): $ada=true;
            $catatan = $r['atasan_catatan'] ?: ($r['penilaian_kerja'] ?? ''); ?>
          <tr>
            <td><strong><?= h($r['no_surat']) ?></strong></td>
            <td><?= h($r['nama_pegawai']) ?></td>
            <td><small><?= h($r['nama_dept']??'—') ?></small></td>
            <td><?= tgl($r['tanggal_efektif']) ?></td>
            <td><?php if($r['klasifikasi']): ?>
                  <span class="badge bg-secondary"><?= h($KLASIFIKASI[$r['klasifikasi']] ?? $r['klasifikasi']) ?></span>
                <?php else: ?><span class="text-muted">—</span><?php endif; ?></td>
            <td style="max-width:320px"><small><?= $catatan ? nl2br(h($catatan)) : '<em class="text-muted">belum dinilai</em>' ?></small></td>
            <td><small><?= h($r['atasan_nama']??'—') ?><br>
                <span class="text-muted"><?= waktu($r['atasan_pada']) ?></span></small></td>
            <td><span class="badge bg-<?= $r['status']==='DISETUJUI'?'success':($r['status']==='DITOLAK'?'danger':'warning') ?>">
                <?= h($r['status']) ?></span></td>
          </tr>
        <?php endwhile; if(!$ada): ?>
          <tr><td colspan="8" class="text-center text-muted py-4">Belum ada data.</td></tr>
        <?php endif; ?>
        </tbody>
      </table>
      </div>
    </div></div>
  </section>
</main>
<?php include '../template/footer.php'; ?>
