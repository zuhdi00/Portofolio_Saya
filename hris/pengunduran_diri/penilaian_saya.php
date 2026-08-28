<?php
/**
 * pengunduran_diri/penilaian_saya.php
 * Daftar pengunduran diri yang PERLU DINILAI oleh atasan divisi ybs.
 * Atasan hanya melihat karyawan dari divisinya sendiri (hris_users.department_id).
 * HR & Admin IT melihat semua.
 */
require_once __DIR__ . '/../auth/auth.php';
wajib_izin('resign_nilai');
require_once __DIR__ . '/../config/koneksi_sqlsrv.php';

$u        = user_login();
$peran    = peran_saya();
$deptSaya = $u['department_id'] ?? null;
$lihatSemua = in_array($peran, ['hr','admin_it'], true);

$where = ["1=1"]; $params = [];
if (!$lihatSemua) {
    if (!$deptSaya) {
        $pesanKosong = "Akun Anda belum diberi divisi. Hubungi Admin IT agar penilaian bisa ditampilkan.";
    }
    $where[] = "r.department_id = ?";
    $params[] = (int)$deptSaya;
}

$tab = $_GET['tab'] ?? 'belum';
if ($tab === 'belum')  $where[] = "(r.atasan_catatan IS NULL AND r.penilaian_kerja IS NULL)";
if ($tab === 'sudah')  $where[] = "(r.atasan_catatan IS NOT NULL OR r.penilaian_kerja IS NOT NULL)";

$rows = sqlsrv_query($conn,
  "SELECT r.*, d.nama_dept
   FROM dbo.pengunduran_diri r
   LEFT JOIN dbo.department d ON d.id_dept = r.department_id
   WHERE ".implode(' AND ', $where)."
   ORDER BY r.dibuat_pada DESC", $params);

/* hitung yang belum dinilai (untuk badge) */
$wB = ["(r.atasan_catatan IS NULL AND r.penilaian_kerja IS NULL)"]; $pB = [];
if (!$lihatSemua) { $wB[] = "r.department_id = ?"; $pB[] = (int)$deptSaya; }
$rsB = sqlsrv_query($conn, "SELECT COUNT(*) n FROM dbo.pengunduran_diri r WHERE ".implode(' AND ',$wB), $pB);
$nBelum = ($rsB && ($x=sqlsrv_fetch_array($rsB,SQLSRV_FETCH_ASSOC))) ? (int)$x['n'] : 0;

$page_title = "Penilaian Divisi Saya";
include '../template/header.php';
include '../template/sidebar.php';
function h($v){return htmlspecialchars((string)($v??''));}
function tgl($v){return $v instanceof DateTime?$v->format('d-m-Y'):'—';}
?>
<main id="main" class="main">
  <div class="pagetitle"><h1>Penilaian Karyawan Resign</h1>
    <nav><ol class="breadcrumb"><li class="breadcrumb-item"><a href="../index.php">Home</a></li>
    <li class="breadcrumb-item active">Penilaian Divisi</li></ol></nav></div>

  <section class="section">
    <?php if (!empty($pesanKosong)): ?>
      <div class="alert alert-warning"><?= h($pesanKosong) ?></div>
    <?php endif; ?>

    <?php if (!$lihatSemua && $deptSaya): ?>
      <div class="alert alert-info py-2">
        Menampilkan karyawan divisi Anda saja.
      </div>
    <?php endif; ?>

    <ul class="nav nav-tabs mb-3">
      <li class="nav-item"><a class="nav-link <?= $tab==='belum'?'active':'' ?>" href="?tab=belum">
        Belum Dinilai <span class="badge bg-warning"><?= $nBelum ?></span></a></li>
      <li class="nav-item"><a class="nav-link <?= $tab==='sudah'?'active':'' ?>" href="?tab=sudah">Sudah Dinilai</a></li>
    </ul>

    <div class="card"><div class="card-body">
      <div class="table-responsive">
      <table class="table table-hover align-middle">
        <thead class="table-light"><tr>
          <th>No Surat</th><th>Nama Karyawan</th><th>Divisi</th><th>Hari Terakhir</th>
          <th>Status</th><th>Penilaian</th><th style="width:120px">Aksi</th>
        </tr></thead>
        <tbody>
        <?php $ada=false; while($r=sqlsrv_fetch_array($rows,SQLSRV_FETCH_ASSOC)): $ada=true;
            $catatan = $r['atasan_catatan'] ?: ($r['penilaian_kerja'] ?? ''); ?>
          <tr>
            <td><strong><?= h($r['no_surat']) ?></strong></td>
            <td><?= h($r['nama_pegawai']) ?></td>
            <td><small><?= h($r['nama_dept']??'—') ?></small></td>
            <td><?= tgl($r['tanggal_efektif']) ?></td>
            <td><span class="badge bg-<?= $r['status']==='DISETUJUI'?'success':($r['status']==='DITOLAK'?'danger':'warning') ?>">
                <?= h($r['status']) ?></span></td>
            <td style="max-width:280px"><small>
              <?= $catatan ? nl2br(h($catatan)) : '<em class="text-muted">belum dinilai</em>' ?></small></td>
            <td>
              <a href="penilaian.php?id=<?= $r['id_pengunduran'] ?>"
                 class="btn btn-sm btn-<?= $catatan ? 'outline-secondary' : 'success' ?>">
                <i class="bi bi-clipboard-check"></i> <?= $catatan ? 'Lihat' : 'Nilai' ?></a>
            </td>
          </tr>
        <?php endwhile; if(!$ada): ?>
          <tr><td colspan="7" class="text-center text-muted py-4">Tidak ada data.</td></tr>
        <?php endif; ?>
        </tbody>
      </table>
      </div>
    </div></div>
  </section>
</main>
<?php include '../template/footer.php'; ?>
