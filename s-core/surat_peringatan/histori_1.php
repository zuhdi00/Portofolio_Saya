<?php
/**
 * surat_peringatan/histori.php
 * Histori Surat Peringatan - khusus HRD & Admin IT.
 * ?peg=ID untuk melihat riwayat satu karyawan.
 */
require_once __DIR__ . '/../auth/auth.php';
wajib_peran(['hr','admin_it']);
require_once __DIR__ . '/../config/koneksi_sqlsrv.php';

function h($v){ return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8'); }
function tglF($v){ return $v instanceof DateTime ? $v->format('d-m-Y') : ($v ? h($v) : '—'); }

$fPeg  = (int)($_GET['peg'] ?? 0);
$fKat  = trim($_GET['kategori'] ?? '');
$dari  = $_GET['dari']   ?? '';
$sampai= $_GET['sampai'] ?? '';
$cari  = trim($_GET['cari'] ?? '');

$where = ["1=1"]; $par = [];
if ($fPeg)          { $where[] = "s.pegawai_id = ?";  $par[] = $fPeg; }
if ($fKat !== '')   { $where[] = "s.kategori_sp = ?"; $par[] = $fKat; }
if ($dari !== '')   { $where[] = "s.tanggal_sp >= ?"; $par[] = $dari; }
if ($sampai !== '') { $where[] = "s.tanggal_sp <= ?"; $par[] = $sampai; }
if ($cari !== '')   { $where[] = "(p.nama_peg LIKE ? OR p.nik LIKE ? OR s.no_surat LIKE ?)";
                      $par[]="%$cari%"; $par[]="%$cari%"; $par[]="%$cari%"; }

$rows = sqlsrv_query($conn,
  "SELECT TOP 300 s.*, p.nama_peg, p.nik, u.nama_unit, d.nama_dept
   FROM dbo.surat_peringatan s
   LEFT JOIN dbo.pegawai p ON p.id_peg = s.pegawai_id
   LEFT JOIN dbo.unit_kerja u ON u.id = p.unit_kerja_id
   LEFT JOIN dbo.department d ON d.id_dept = u.department_id
   WHERE ".implode(' AND ', $where)."
   ORDER BY s.tanggal_sp DESC, s.id_sp DESC", $par);

$ring = []; $totalSp = 0;
$sqlR = "SELECT s.kategori_sp, COUNT(*) AS n FROM dbo.surat_peringatan s";
$parR = [];
if ($fPeg) { $sqlR .= " WHERE s.pegawai_id = ?"; $parR[] = $fPeg; }
$sqlR .= " GROUP BY s.kategori_sp";
$rs = sqlsrv_query($conn, $sqlR, $parR);
while ($rs && ($x = sqlsrv_fetch_array($rs, SQLSRV_FETCH_ASSOC))) {
    $ring[$x['kategori_sp']] = (int)$x['n']; $totalSp += (int)$x['n'];
}
if ($rs) sqlsrv_free_stmt($rs);

$topPeg = [];
if (!$fPeg) {
    $rs = sqlsrv_query($conn,
      "SELECT TOP 10 p.id_peg, p.nama_peg, p.nik, COUNT(*) AS n, MAX(s.tanggal_sp) AS terakhir
       FROM dbo.surat_peringatan s
       JOIN dbo.pegawai p ON p.id_peg = s.pegawai_id
       GROUP BY p.id_peg, p.nama_peg, p.nik ORDER BY n DESC");
    while ($rs && ($x = sqlsrv_fetch_array($rs, SQLSRV_FETCH_ASSOC))) $topPeg[] = $x;
    if ($rs) sqlsrv_free_stmt($rs);
}

$namaPeg = '';
if ($fPeg) {
    $rs = sqlsrv_query($conn, "SELECT nama_peg FROM dbo.pegawai WHERE id_peg=?", [$fPeg]);
    if ($rs && ($x = sqlsrv_fetch_array($rs, SQLSRV_FETCH_ASSOC))) $namaPeg = $x['nama_peg'];
    if ($rs) sqlsrv_free_stmt($rs);
}

$page_title = "Histori Surat Peringatan";
include __DIR__ . '/../template/header.php';
include __DIR__ . '/../template/sidebar.php';
?>
<main id="main" class="main">
  <div class="pagetitle d-flex justify-content-between align-items-start flex-wrap">
    <div><h1>Histori Surat Peringatan<?= $namaPeg ? ' — '.h($namaPeg) : '' ?></h1>
      <nav><ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="<?php echo BASE_URL; ?>/s-core/index.php">Home</a></li>
        <li class="breadcrumb-item"><a href="index.php">Surat Peringatan</a></li>
        <li class="breadcrumb-item active">Histori</li>
      </ol></nav></div>
    <div class="pt-2">
      <a href="index.php" class="btn btn-danger btn-sm"><i class="bi bi-file-earmark-plus"></i> Terbitkan SP</a>
      <?php if ($fPeg): ?><a href="histori.php" class="btn btn-outline-secondary btn-sm">Lihat Semua</a><?php endif; ?>
    </div>
  </div>

  <section class="section">
    <div class="row g-2 mb-3">
      <?php foreach (['SP I'=>'info','SP 2'=>'warning','SP 3'=>'danger'] as $k=>$w): ?>
        <div class="col-6 col-md-3">
          <div class="border rounded p-3 text-center bg-white">
            <div class="fs-3 fw-bold text-<?= $w ?>"><?= number_format($ring[$k] ?? 0) ?></div>
            <small class="text-muted"><?= h($k) ?></small>
          </div>
        </div>
      <?php endforeach; ?>
      <div class="col-6 col-md-3">
        <div class="border rounded p-3 text-center bg-white">
          <div class="fs-3 fw-bold text-primary"><?= number_format($totalSp) ?></div>
          <small class="text-muted">Total SP</small>
        </div>
      </div>
    </div>

    <form method="GET" class="row g-2 mb-3 align-items-end">
      <?php if ($fPeg): ?><input type="hidden" name="peg" value="<?= $fPeg ?>"><?php endif; ?>
      <div class="col-auto"><label class="form-label small mb-1">Kategori</label>
        <select name="kategori" class="form-select form-select-sm">
          <option value="">-- semua --</option>
          <?php foreach (['SP I','SP 2','SP 3'] as $k): ?>
            <option value="<?= h($k) ?>" <?= $fKat===$k?'selected':'' ?>><?= h($k) ?></option>
          <?php endforeach; ?>
        </select></div>
      <div class="col-auto"><label class="form-label small mb-1">Dari</label>
        <input type="date" name="dari" class="form-control form-control-sm" value="<?= h($dari) ?>"></div>
      <div class="col-auto"><label class="form-label small mb-1">Sampai</label>
        <input type="date" name="sampai" class="form-control form-control-sm" value="<?= h($sampai) ?>"></div>
      <div class="col-auto"><label class="form-label small mb-1">Cari</label>
        <input type="text" name="cari" class="form-control form-control-sm"
               placeholder="nama / NIK / no surat" value="<?= h($cari) ?>"></div>
      <div class="col-auto"><button class="btn btn-primary btn-sm">Filter</button></div>
    </form>

    <div class="row g-3">
      <div class="col-lg-<?= $fPeg ? '12' : '8' ?>">
        <div class="card"><div class="card-body">
          <h5 class="card-title">Daftar Surat Peringatan</h5>
          <div class="table-responsive">
          <table class="table table-sm table-hover align-middle">
            <thead class="table-light"><tr>
              <th>No Surat</th><th>Tanggal</th><th>Karyawan</th><th>Departemen</th>
              <th>Kategori</th><th>Pelanggaran</th><th>Dibuat Oleh</th><th></th>
            </tr></thead>
            <tbody>
            <?php $n=0; while($rows && ($r=sqlsrv_fetch_array($rows,SQLSRV_FETCH_ASSOC))): $n++; ?>
              <tr>
                <td><small><strong><?= h($r['no_surat']) ?></strong>
                  <?php if ((int)($r['revisi'] ?? 0) > 0): ?>
                    <span class="badge bg-dark">Rev <?= sprintf('%02d',(int)$r['revisi']) ?></span>
                  <?php endif; ?></small></td>
                <td><small><?= tglF($r['tanggal_sp']) ?></small></td>
                <td><strong><?= h($r['nama_peg']) ?></strong><br>
                    <small class="text-muted"><?= h($r['nik']) ?></small></td>
                <td><small><?= h($r['nama_dept'] ?: ($r['nama_unit'] ?: '—')) ?></small></td>
                <td><span class="badge bg-<?= !empty($r['status_pemutihan'])?'secondary':'danger' ?>">
                      <?= h($r['kategori_sp']) ?></span>
                    <?php if (!empty($r['status_pemutihan'])): ?>
                      <br><small class="text-muted">pemutihan</small><?php endif; ?></td>
                <td style="max-width:200px"><small><?= h($r['jenis_pelanggaran'] ?: '—') ?></small></td>
                <td><small><?= h($r['dibuat_oleh'] ?: '—') ?></small></td>
                <td class="text-nowrap">
                  <a href="cetak.php?id=<?= (int)$r['id_sp'] ?>" target="_blank"
                     class="btn btn-sm btn-outline-secondary" title="cetak"><i class="bi bi-printer"></i></a>
                  <?php if (!$fPeg): ?>
                  <a href="histori.php?peg=<?= (int)$r['pegawai_id'] ?>"
                     class="btn btn-sm btn-outline-info" title="riwayat karyawan ini">
                     <i class="bi bi-clock-history"></i></a>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endwhile; if(!$n): ?>
              <tr><td colspan="8" class="text-center text-muted py-4">Belum ada surat peringatan.</td></tr>
            <?php endif; ?>
            </tbody>
          </table>
          </div>
        </div></div>
      </div>

      <?php if (!$fPeg): ?>
      <div class="col-lg-4">
        <div class="card"><div class="card-body">
          <h5 class="card-title">Karyawan dengan SP Terbanyak</h5>
          <table class="table table-sm">
            <thead class="table-light"><tr><th>Karyawan</th><th class="text-center">SP</th><th>Terakhir</th></tr></thead>
            <tbody>
            <?php foreach($topPeg as $t):
                $j=(int)$t['n']; $w=$j>=3?'danger':($j>=2?'warning':'secondary'); ?>
              <tr>
                <td><a href="histori.php?peg=<?= (int)$t['id_peg'] ?>" class="text-decoration-none">
                    <?= h($t['nama_peg']) ?></a><br>
                    <small class="text-muted"><?= h($t['nik']) ?></small></td>
                <td class="text-center"><span class="badge bg-<?= $w ?>"><?= $j ?></span></td>
                <td><small><?= tglF($t['terakhir']) ?></small></td>
              </tr>
            <?php endforeach; if(!$topPeg): ?>
              <tr><td colspan="3" class="text-center text-muted py-3">Belum ada data.</td></tr>
            <?php endif; ?>
            </tbody>
          </table>
        </div></div>
      </div>
      <?php endif; ?>
    </div>
  </section>
</main>
<?php include __DIR__ . '/../template/footer.php'; ?>
