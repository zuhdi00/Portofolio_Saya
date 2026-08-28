<?php
/**
 * presensi/lembur_approval.php
 * Approval lembur bertingkat: tab Atasan & tab HR.
 * ?peran=atasan  -> approve tahap 1 (DIAJUKAN -> DISETUJUI_ATASAN)
 * ?peran=hr      -> approve tahap 2 (DISETUJUI_ATASAN -> DISETUJUI_HR)
 */
$page_title = "Approval Lembur";
include '../template/header.php';
include '../config/koneksi_sqlsrv.php';
include '../template/sidebar.php';

$peran = ($_GET['peran'] ?? 'atasan') === 'hr' ? 'hr' : 'atasan';

/* ---------- proses keputusan ---------- */
$pesan = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id       = (int)($_POST['id_lembur'] ?? 0);
    $keputusan= $_POST['keputusan'] ?? '';   // setuju / tolak
    $catatan  = trim($_POST['catatan'] ?? '');
    $olehPeran= $_POST['peran'] ?? 'atasan';

    if ($olehPeran === 'atasan') {
        $status = $keputusan === 'setuju' ? 'DISETUJUI_ATASAN' : 'DITOLAK_ATASAN';
        $sql = "UPDATE dbo.lembur
                SET status=?, atasan_pada=GETDATE(), atasan_catatan=?
                WHERE id_lembur=? AND status='DIAJUKAN'";
    } else {
        $status = $keputusan === 'setuju' ? 'DISETUJUI_HR' : 'DITOLAK_HR';
        $sql = "UPDATE dbo.lembur
                SET status=?, hr_pada=GETDATE(), hr_catatan=?
                WHERE id_lembur=? AND status='DISETUJUI_ATASAN'";
    }
    $st = sqlsrv_query($conn, $sql, [$status, $catatan?:null, $id]);
    if ($st === false) $pesan = "<div class='alert alert-danger'>Gagal memproses.</div>";
    else $pesan = "<div class='alert alert-success'>Keputusan tersimpan.</div>";
}

/* ---------- daftar yang menunggu ---------- */
$statusFilter = $peran === 'atasan' ? 'DIAJUKAN' : 'DISETUJUI_ATASAN';
$rows = sqlsrv_query($conn,
    "SELECT l.*, p.nama_peg, p.nik, d.nama_dept
     FROM dbo.lembur l
     JOIN dbo.pegawai p ON p.id_peg = l.pegawai_id
     LEFT JOIN dbo.unit_kerja u ON u.id = p.unit_kerja_id
     LEFT JOIN dbo.department d ON d.id_dept = u.department_id
     WHERE l.status = ?
     ORDER BY l.tanggal, l.diajukan_pada", [$statusFilter]);

/* hitung badge */
function hitung($conn,$s){ $r=sqlsrv_query($conn,"SELECT COUNT(*) n FROM dbo.lembur WHERE status=?",[$s]); $x=sqlsrv_fetch_array($r,SQLSRV_FETCH_ASSOC); return $x['n']??0; }
$nAtasan = hitung($conn,'DIAJUKAN');
$nHR     = hitung($conn,'DISETUJUI_ATASAN');

function jam($v){ return $v instanceof DateTime ? $v->format('H:i') : '—'; }
function tgl($v){ return $v instanceof DateTime ? $v->format('d-m-Y') : '—'; }
function h($v){ return htmlspecialchars((string)($v ?? '')); }
?>

<main id="main" class="main">
  <div class="pagetitle"><h1>Approval Lembur</h1>
    <nav><ol class="breadcrumb">
      <li class="breadcrumb-item"><a href="/hris/index.php">Home</a></li>
      <li class="breadcrumb-item active">Approval Lembur</li>
    </ol></nav>
  </div>

  <section class="section">
    <?= $pesan ?>

    <ul class="nav nav-tabs mb-3">
      <li class="nav-item">
        <a class="nav-link <?= $peran==='atasan'?'active':'' ?>" href="?peran=atasan">
          Approval Atasan <span class="badge bg-warning"><?= $nAtasan ?></span></a>
      </li>
      <li class="nav-item">
        <a class="nav-link <?= $peran==='hr'?'active':'' ?>" href="?peran=hr">
          Approval HR <span class="badge bg-info"><?= $nHR ?></span></a>
      </li>
    </ul>

    <div class="card"><div class="card-body">
      <h5 class="card-title">
        Menunggu Approval <?= $peran==='atasan'?'Atasan':'HR' ?>
      </h5>
      <div class="table-responsive">
      <table class="table table-hover">
        <thead class="table-light"><tr>
          <th>Tanggal</th><th>Nama</th><th>Dept</th><th>Jam</th><th>Durasi</th>
          <th>Jenis</th><th>Alasan</th><th style="width:180px">Keputusan</th>
        </tr></thead>
        <tbody>
        <?php $ada=false; while ($r = sqlsrv_fetch_array($rows, SQLSRV_FETCH_ASSOC)): $ada=true; ?>
          <tr>
            <td><?= tgl($r['tanggal']) ?></td>
            <td><strong><?= h($r['nama_peg']) ?></strong><br><small class="text-muted"><?= h($r['nik']) ?></small></td>
            <td><?= h($r['nama_dept'] ?? '—') ?></td>
            <td><?= jam($r['jam_mulai']) ?>–<?= jam($r['jam_selesai']) ?></td>
            <td><?= number_format($r['durasi_jam'],1) ?> jam</td>
            <td><?= h($r['jenis']) ?></td>
            <td><small><?= h($r['alasan']) ?></small>
              <?php if ($peran==='hr' && $r['atasan_catatan']): ?>
                <br><em class="text-info">Atasan: <?= h($r['atasan_catatan']) ?></em>
              <?php endif; ?>
            </td>
            <td>
              <form method="POST" class="d-flex flex-column gap-1">
                <input type="hidden" name="id_lembur" value="<?= $r['id_lembur'] ?>">
                <input type="hidden" name="peran" value="<?= $peran ?>">
                <input type="text" name="catatan" class="form-control form-control-sm" placeholder="catatan (opsional)">
                <div class="btn-group btn-group-sm">
                  <button name="keputusan" value="setuju" class="btn btn-success">Setuju</button>
                  <button name="keputusan" value="tolak" class="btn btn-danger">Tolak</button>
                </div>
              </form>
            </td>
          </tr>
        <?php endwhile; ?>
        <?php if(!$ada): ?>
          <tr><td colspan="8" class="text-center text-muted py-4">Tidak ada pengajuan menunggu.</td></tr>
        <?php endif; ?>
        </tbody>
      </table>
      </div>
    </div></div>
  </section>
</main>

<?php include '../template/footer.php'; ?>
