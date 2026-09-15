<?php
/**
 * absensi/detail_terlambat.php?dept=ID&dari=..&sampai=..
 * Daftar pegawai yang terlambat pada periode tertentu.
 * Dibuka dari angka "Total Terlambat" di dashboard.
 */
require_once __DIR__ . '/../auth/auth.php';
wajib_izin('absensi_rekap');
require_once __DIR__ . '/../config/koneksi_sqlsrv.php';

$peranSaya      = peran_saya();
$pakaiToleransi = in_array($peranSaya, ['hr','admin_it'], true);
$bolehSP        = in_array($peranSaya, ['hr','admin_it'], true);

$fDept  = (int)($_GET['dept'] ?? 0);
$dari   = $_GET['dari']   ?? date('Y-m-01');
$sampai = $_GET['sampai'] ?? date('Y-m-t');
$mode   = $_GET['mode']   ?? 'ringkas';    // ringkas (per pegawai) | rinci (per tanggal)
$cari   = trim($_GET['cari'] ?? '');

/* ekspresi terlambat sesuai peran */
if ($pakaiToleransi) {
    $EKS = "CASE WHEN a.status='terlambat' THEN 1 ELSE 0 END";
    $FILTER_TELAT = "a.status = 'terlambat'";
    $JOIN_SHIFT = "";
} else {
    $EKS = "CASE WHEN a.jam_masuk IS NOT NULL AND ps.jam_mulai IS NOT NULL
                  AND DATEDIFF(minute, ps.jam_mulai, a.jam_masuk) > 0
                  AND NOT (a.shift_ke = 3 AND a.jam_masuk >= '21:00:00')
             THEN 1 ELSE 0 END";
    $FILTER_TELAT = "a.jam_masuk IS NOT NULL AND ps.jam_mulai IS NOT NULL
                     AND DATEDIFF(minute, ps.jam_mulai, a.jam_masuk) > 0
                     AND NOT (a.shift_ke = 3 AND a.jam_masuk >= '21:00:00')";
    $JOIN_SHIFT = "LEFT JOIN dbo.pengaturan_shift ps ON ps.shift_ke = a.shift_ke";
}
if ($JOIN_SHIFT === "") $JOIN_SHIFT = "LEFT JOIN dbo.pengaturan_shift ps ON ps.shift_ke = a.shift_ke";

$where = ["a.tanggal BETWEEN ? AND ?", $FILTER_TELAT];
$par   = [$dari, $sampai];
if ($fDept) { $where[] = "u.department_id = ?"; $par[] = $fDept; }
if ($cari !== '') { $where[] = "(p.nama_peg LIKE ? OR p.nik LIKE ?)"; $par[]="%$cari%"; $par[]="%$cari%"; }
$W = implode(' AND ', $where);

if ($mode === 'rinci') {
    $rows = sqlsrv_query($conn,
      "SELECT TOP 500 a.tanggal, a.shift_ke, a.jam_masuk, ps.jam_mulai,
              p.id_peg, p.nik, p.nama_peg, d.nama_dept
       FROM dbo.absensi a $JOIN_SHIFT
       JOIN dbo.pegawai p ON p.id_peg = a.pegawai_id
       LEFT JOIN dbo.unit_kerja u ON u.id = p.unit_kerja_id
       LEFT JOIN dbo.department d ON d.id_dept = u.department_id
       WHERE $W ORDER BY a.tanggal DESC, p.nama_peg", $par);
} else {
    $rows = sqlsrv_query($conn,
      "SELECT TOP 500 p.id_peg, p.nik, p.nama_peg, d.nama_dept,
              COUNT(*) AS jml_telat,
              MIN(a.tanggal) AS telat_pertama,
              MAX(a.tanggal) AS telat_terakhir
       FROM dbo.absensi a $JOIN_SHIFT
       JOIN dbo.pegawai p ON p.id_peg = a.pegawai_id
       LEFT JOIN dbo.unit_kerja u ON u.id = p.unit_kerja_id
       LEFT JOIN dbo.department d ON d.id_dept = u.department_id
       WHERE $W
       GROUP BY p.id_peg, p.nik, p.nama_peg, d.nama_dept
       ORDER BY jml_telat DESC", $par);
}

$deptList=[]; $rs=sqlsrv_query($conn,"SELECT id_dept,nama_dept FROM dbo.department ORDER BY nama_dept");
if ($rs) { while($x=sqlsrv_fetch_array($rs,SQLSRV_FETCH_ASSOC)) $deptList[]=$x; sqlsrv_free_stmt($rs); }
$namaDept = '';
foreach ($deptList as $d) if ((int)$d['id_dept'] === $fDept) $namaDept = $d['nama_dept'];

$page_title = "Detail Keterlambatan";
include __DIR__ . '/../template/header.php';
include __DIR__ . '/../template/sidebar.php';
function h($v){ return htmlspecialchars((string)($v ?? '')); }
function tglF($v){ return $v instanceof DateTime ? $v->format('d-m-Y') : '—'; }
function jamF($v){ return $v instanceof DateTime ? $v->format('H:i') : '—'; }
?>
<main id="main" class="main">
  <div class="pagetitle d-flex justify-content-between align-items-start flex-wrap">
    <div><h1>Detail Keterlambatan<?= $namaDept ? ' — '.h($namaDept) : '' ?></h1>
      <nav><ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="<?php echo BASE_URL; ?>/s-core/index.php">Dashboard</a></li>
        <li class="breadcrumb-item active">Detail Keterlambatan</li>
      </ol></nav></div>
    <div class="pt-2"><a href="<?php echo BASE_URL; ?>/s-core/index.php" class="btn btn-outline-primary btn-sm">
      <i class="bi bi-arrow-left"></i> Dashboard</a></div>
  </div>

  <section class="section">
    <form method="GET" class="row g-2 mb-3 align-items-end">
      <div class="col-auto"><label class="form-label small mb-1">Dari</label>
        <input type="date" name="dari" class="form-control form-control-sm" value="<?= h($dari) ?>"></div>
      <div class="col-auto"><label class="form-label small mb-1">Sampai</label>
        <input type="date" name="sampai" class="form-control form-control-sm" value="<?= h($sampai) ?>"></div>
      <div class="col-auto"><label class="form-label small mb-1">Departemen</label>
        <select name="dept" class="form-select form-select-sm">
          <option value="0">-- semua --</option>
          <?php foreach($deptList as $d): ?>
            <option value="<?= (int)$d['id_dept'] ?>" <?= $fDept===(int)$d['id_dept']?'selected':'' ?>>
              <?= h($d['nama_dept']) ?></option>
          <?php endforeach; ?>
        </select></div>
      <div class="col-auto"><label class="form-label small mb-1">Tampilan</label>
        <select name="mode" class="form-select form-select-sm">
          <option value="ringkas" <?= $mode==='ringkas'?'selected':'' ?>>Ringkas (per pegawai)</option>
          <option value="rinci"   <?= $mode==='rinci'?'selected':'' ?>>Rinci (per tanggal)</option>
        </select></div>
      <div class="col-auto"><label class="form-label small mb-1">Cari</label>
        <input type="text" name="cari" class="form-control form-control-sm"
               placeholder="nama / NIK" value="<?= h($cari) ?>"></div>
      <div class="col-auto"><button class="btn btn-primary btn-sm">Terapkan</button></div>
    </form>

    <div class="card"><div class="card-body">
      <h5 class="card-title">
        <?= $mode==='rinci' ? 'Rincian per Tanggal' : 'Rekap per Pegawai' ?>
        <span class="text-muted small">| <?= date('d M Y', strtotime($dari)) ?> – <?= date('d M Y', strtotime($sampai)) ?></span>
      </h5>

      <div class="table-responsive">
      <table class="table table-sm table-hover align-middle">
        <thead class="table-light">
        <?php if ($mode==='rinci'): ?>
          <tr><th>Tanggal</th><th>NIK</th><th>Nama</th><th>Departemen</th>
              <th class="text-center">Shift</th><th>Jam Masuk</th><th>Seharusnya</th>
              <?php if ($bolehSP): ?><th></th><?php endif; ?></tr>
        <?php else: ?>
          <tr><th>NIK</th><th>Nama</th><th>Departemen</th>
              <th class="text-center">Jumlah Terlambat</th><th>Pertama</th><th>Terakhir</th>
              <?php if ($bolehSP): ?><th></th><?php endif; ?></tr>
        <?php endif; ?>
        </thead>
        <tbody>
        <?php $n=0; while($rows && ($r=sqlsrv_fetch_array($rows,SQLSRV_FETCH_ASSOC))): $n++; ?>
          <tr>
          <?php if ($mode==='rinci'): ?>
            <td><?= tglF($r['tanggal']) ?></td>
            <td><?= h($r['nik']) ?></td>
            <td><strong><?= h($r['nama_peg']) ?></strong></td>
            <td><small><?= h($r['nama_dept'] ?: '—') ?></small></td>
            <td class="text-center"><?= h($r['shift_ke'] ?: '—') ?></td>
            <td><span class="badge bg-warning"><?= jamF($r['jam_masuk']) ?></span></td>
            <td><small class="text-muted"><?= jamF($r['jam_mulai']) ?></small></td>
          <?php else:
              $j = (int)$r['jml_telat'];
              $w = $j >= 10 ? 'danger' : ($j >= 5 ? 'warning' : 'secondary'); ?>
            <td><?= h($r['nik']) ?></td>
            <td><strong><?= h($r['nama_peg']) ?></strong></td>
            <td><small><?= h($r['nama_dept'] ?: '—') ?></small></td>
            <td class="text-center"><span class="badge bg-<?= $w ?>"><?= $j ?>×</span></td>
            <td><small><?= tglF($r['telat_pertama']) ?></small></td>
            <td><small><?= tglF($r['telat_terakhir']) ?></small></td>
          <?php endif; ?>
          <?php if ($bolehSP): ?>
            <td class="text-nowrap">
              <a href="<?php echo BASE_URL; ?>/s-core/surat_peringatan/index.php?peg=<?= (int)$r['id_peg'] ?>"
                 class="btn btn-sm btn-outline-danger" title="terbitkan SP">
                <i class="bi bi-file-earmark-text"></i></a>
              <a href="<?php echo BASE_URL; ?>/s-core/surat_peringatan/histori.php?peg=<?= (int)$r['id_peg'] ?>"
                 class="btn btn-sm btn-outline-secondary" title="histori SP">
                <i class="bi bi-clock-history"></i></a>
            </td>
          <?php endif; ?>
          </tr>
        <?php endwhile; if(!$n): ?>
          <tr><td colspan="8" class="text-center text-muted py-4">Tidak ada keterlambatan pada periode ini.</td></tr>
        <?php endif; ?>
        </tbody>
      </table>
      </div>
      <div class="text-muted small">
        Total <strong><?= number_format($n) ?></strong> baris
        &middot; <?= $pakaiToleransi ? 'dihitung dengan toleransi' : 'dihitung dari jam mulai shift' ?>
      </div>
    </div></div>
  </section>
</main>
<?php include __DIR__ . '/../template/footer.php'; ?>
