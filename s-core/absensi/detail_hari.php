<?php
/**
 * absensi/detail_hari.php?jenis=hadir|terlambat|belum|approval&tgl=YYYY-MM-DD
 * Detail daftar pegawai di balik angka stat card dashboard.
 */
require_once __DIR__ . '/../auth/auth.php';
wajib_izin('absensi_rekap');
require_once __DIR__ . '/../config/koneksi_sqlsrv.php';

$peranSaya      = peran_saya();
$pakaiToleransi = in_array($peranSaya, ['hr','admin_it'], true);

$jenis = $_GET['jenis'] ?? 'hadir';
$tgl   = $_GET['tgl']   ?? date('Y-m-d');
$cari  = trim($_GET['cari'] ?? '');

$judul = [
  'hadir'     => 'Karyawan Hadir',
  'terlambat' => 'Karyawan Terlambat',
  'belum'     => 'Belum Tap',
  'approval'  => 'Perlu Approval Koreksi',
][$jenis] ?? 'Detail Absensi';

$rows = null; $kolomKhusus = false;

if ($jenis === 'belum') {
    // pegawai aktif yang TIDAK punya baris absensi di tanggal itu
    $sql = "SELECT p.id_peg, p.nik, p.nama_peg, d.nama_dept
            FROM dbo.pegawai p
            LEFT JOIN dbo.unit_kerja u ON u.id = p.unit_kerja_id
            LEFT JOIN dbo.department d ON d.id_dept = u.department_id
            WHERE p.is_aktif = 1
              AND NOT EXISTS (SELECT 1 FROM dbo.absensi a
                              WHERE a.pegawai_id = p.id_peg AND a.tanggal = ?)";
    $par = [$tgl];
    if ($cari !== '') { $sql .= " AND (p.nama_peg LIKE ? OR p.nik LIKE ?)"; $par[]="%$cari%"; $par[]="%$cari%"; }
    $sql .= " ORDER BY d.nama_dept, p.nama_peg";
    $rows = sqlsrv_query($conn, $sql, $par);
}
elseif ($jenis === 'approval') {
    $sql = "SELECT k.tanggal, k.jenis AS jenis_koreksi, k.jam_masuk_asli, k.jam_keluar_asli,
                   p.nik, p.nama_peg, d.nama_dept
            FROM dbo.absensi_koreksi k
            JOIN dbo.pegawai p ON p.id_peg = k.pegawai_id
            LEFT JOIN dbo.unit_kerja u ON u.id = p.unit_kerja_id
            LEFT JOIN dbo.department d ON d.id_dept = u.department_id
            WHERE k.status_approval = 'PENDING'";
    $par = [];
    if ($cari !== '') { $sql .= " AND (p.nama_peg LIKE ? OR p.nik LIKE ?)"; $par[]="%$cari%"; $par[]="%$cari%"; }
    $sql .= " ORDER BY k.tanggal DESC, p.nama_peg";
    $rows = sqlsrv_query($conn, $sql, $par);
    $kolomKhusus = 'approval';
}
else {
    // hadir / terlambat
    $sql = "SELECT a.tanggal, a.jam_masuk, a.jam_keluar, a.status, a.shift_ke, a.perlu_koreksi,
                   ps.jam_mulai, p.nik, p.nama_peg, d.nama_dept
            FROM dbo.absensi a
            LEFT JOIN dbo.pengaturan_shift ps ON ps.shift_ke = a.shift_ke
            JOIN dbo.pegawai p ON p.id_peg = a.pegawai_id
            LEFT JOIN dbo.unit_kerja u ON u.id = p.unit_kerja_id
            LEFT JOIN dbo.department d ON d.id_dept = u.department_id
            WHERE a.tanggal = ?";
    $par = [$tgl];
    if ($jenis === 'terlambat') {
        if ($pakaiToleransi) {
            // versi HR: pakai status resmi (sudah termasuk toleransi)
            $sql .= " AND a.status = 'terlambat'";
        } else {
            // versi ketat: lewat jam mulai shift, tanpa toleransi
            $sql .= " AND a.jam_masuk IS NOT NULL AND ps.jam_mulai IS NOT NULL
                      AND DATEDIFF(minute, ps.jam_mulai, a.jam_masuk) > 0
                      AND NOT (a.shift_ke = 3 AND a.jam_masuk >= '21:00:00')";
        }
    }
    if ($cari !== '') { $sql .= " AND (p.nama_peg LIKE ? OR p.nik LIKE ?)"; $par[]="%$cari%"; $par[]="%$cari%"; }
    $sql .= " ORDER BY a.shift_ke, p.nama_peg";
    $rows = sqlsrv_query($conn, $sql, $par);
    $kolomKhusus = 'absensi';
}

$page_title = $judul;
include '../template/header.php';
include '../template/sidebar.php';
function h($v){return htmlspecialchars((string)($v??''));}
function jam($v){return $v instanceof DateTime ? $v->format('H:i') : '—';}
function tglF($v){return $v instanceof DateTime ? $v->format('d-m-Y') : '—';}
?>
<main id="main" class="main">
  <div class="pagetitle d-flex justify-content-between align-items-start flex-wrap">
    <div><h1><?= h($judul) ?></h1>
      <nav><ol class="breadcrumb"><li class="breadcrumb-item"><a href="../index.php">Dashboard</a></li>
      <li class="breadcrumb-item active"><?= h($judul) ?></li></ol></nav></div>
    <div class="pt-2"><a href="../index.php" class="btn btn-outline-primary btn-sm">
      <i class="bi bi-arrow-left"></i> Dashboard</a></div>
  </div>

  <section class="section">
    <!-- tab jenis -->
    <ul class="nav nav-pills mb-3 flex-wrap gap-1">
      <?php foreach (['hadir'=>'Hadir','terlambat'=>'Terlambat','belum'=>'Belum Tap','approval'=>'Perlu Approval'] as $k=>$lbl): ?>
        <li class="nav-item">
          <a class="nav-link <?= $jenis===$k?'active':'' ?>"
             href="?jenis=<?= $k ?>&tgl=<?= h($tgl) ?>"><?= $lbl ?></a></li>
      <?php endforeach; ?>
    </ul>

    <form method="GET" class="row g-2 mb-3">
      <input type="hidden" name="jenis" value="<?= h($jenis) ?>">
      <?php if ($jenis !== 'approval'): ?>
      <div class="col-md-3"><label class="form-label small">Tanggal</label>
        <input type="date" name="tgl" class="form-control form-control-sm" value="<?= h($tgl) ?>"></div>
      <?php endif; ?>
      <div class="col-md-3"><label class="form-label small">Cari nama / NIK</label>
        <input type="text" name="cari" class="form-control form-control-sm" value="<?= h($cari) ?>"></div>
      <div class="col-md-2 d-flex align-items-end"><button class="btn btn-sm btn-primary">Terapkan</button></div>
    </form>

    <div class="card"><div class="card-body">
      <div class="table-responsive">
      <table class="table table-hover table-sm align-middle">
        <thead class="table-light"><tr>
          <th>NIK</th><th>Nama</th><th>Divisi</th>
          <?php if ($kolomKhusus === 'absensi'): ?>
            <th>Shift</th><th>Masuk</th><th>Pulang</th><th>Status</th>
          <?php elseif ($kolomKhusus === 'approval'): ?>
            <th>Tanggal</th><th>Jenis</th><th>Masuk</th><th>Pulang</th>
          <?php endif; ?>
        </tr></thead>
        <tbody>
        <?php $n=0; while($r = sqlsrv_fetch_array($rows, SQLSRV_FETCH_ASSOC)): $n++; ?>
          <tr>
            <td><?= h($r['nik']) ?></td>
            <td><strong><?= h($r['nama_peg']) ?></strong></td>
            <td><small><?= h($r['nama_dept'] ?? '—') ?></small></td>
            <?php if ($kolomKhusus === 'absensi'): ?>
              <td class="text-center"><?= h($r['shift_ke'] ?? '—') ?></td>
              <td><?= jam($r['jam_masuk']) ?></td>
              <td><?= jam($r['jam_keluar']) ?></td>
              <?php
                // status yang ditampilkan menyesuaikan mode peran
                $telatKetat = false;
                if ($r['jam_masuk'] && $r['jam_mulai']) {
                    // bandingkan per MENIT (detik diabaikan)
                    $mMasuk = (int)$r['jam_masuk']->format('H')*60 + (int)$r['jam_masuk']->format('i');
                    $mMulai = (int)$r['jam_mulai']->format('H')*60 + (int)$r['jam_mulai']->format('i');
                    $telatKetat = $mMasuk > $mMulai
                        && !((int)$r['shift_ke'] === 3 && $r['jam_masuk']->format('H:i:s') >= '21:00:00');
                }
                $tampilStatus = $pakaiToleransi ? $r['status'] : ($telatKetat ? 'terlambat' : 'hadir');
              ?>
              <td><span class="badge bg-<?= $tampilStatus==='terlambat'?'warning':'success' ?>">
                  <?= h($tampilStatus) ?></span>
                <?php if (!empty($r['perlu_koreksi'])): ?>
                  <span class="badge bg-danger">koreksi</span><?php endif; ?></td>
            <?php elseif ($kolomKhusus === 'approval'): ?>
              <td><?= tglF($r['tanggal']) ?></td>
              <td><small><?= h(str_replace('_',' ', $r['jenis_koreksi'])) ?></small></td>
              <td><?= jam($r['jam_masuk_asli']) ?></td>
              <td><?= jam($r['jam_keluar_asli']) ?></td>
            <?php endif; ?>
          </tr>
        <?php endwhile; if(!$n): ?>
          <tr><td colspan="8" class="text-center text-muted py-4">Tidak ada data.</td></tr>
        <?php endif; ?>
        </tbody>
      </table>
      </div>
      <div class="text-muted small">Total: <strong><?= number_format($n) ?></strong> baris
        <?php if ($pakaiToleransi): ?>
          &middot; keterlambatan dihitung dengan toleransi 5 menit
        <?php else: ?>
          &middot; keterlambatan dihitung dari jam mulai shift
        <?php endif; ?></div>
    </div></div>
  </section>
</main>
<?php include '../template/footer.php'; ?>
