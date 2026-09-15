<?php
/**
 * lembur/approval_hr.php
 * HR menyetujui / menolak form lembur kolektif (per form).
 * Form dibuat admin_divisi/atasan (status DIAJUKAN) -> HR setuju/tolak.
 */
require_once __DIR__ . '/../auth/auth.php';
wajib_izin('lembur_rekap');   // hanya hr & admin_it
require_once __DIR__ . '/../config/koneksi_sqlsrv.php';

$pesan = '';

/* ---------- proses keputusan ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id        = (int)($_POST['id_form'] ?? 0);
    $keputusan = $_POST['keputusan'] ?? '';
    $catatan   = trim($_POST['hr_catatan'] ?? '');
    $u = user_login();

    if ($id && in_array($keputusan, ['setuju','tolak'], true)) {
        $status = $keputusan === 'setuju' ? 'DISETUJUI_HR' : 'DITOLAK';
        $st = sqlsrv_query($conn,
            "UPDATE dbo.lembur_form
             SET status=?, hr_pada=GETDATE(), hr_catatan=?
             WHERE id_form=? AND status='DIAJUKAN'",
            [$status, $catatan?:null, $id]);
        if ($st === false) {
            $pesan = "<div class='alert alert-danger'>Gagal memproses.</div>";
        } else {
            $rows = sqlsrv_rows_affected($st);
            $pesan = $rows > 0
                ? "<div class='alert alert-success'>Form ".($keputusan==='setuju'?'disetujui':'ditolak').".</div>"
                : "<div class='alert alert-warning'>Form sudah diproses sebelumnya.</div>";
        }
    }
}

/* ---------- filter status ---------- */
$tab = $_GET['tab'] ?? 'diajukan';
$statusFilter = ['diajukan'=>'DIAJUKAN','disetujui'=>'DISETUJUI_HR','ditolak'=>'DITOLAK'][$tab] ?? 'DIAJUKAN';

/* hitung badge */
function hitung($conn,$s){ $r=sqlsrv_query($conn,"SELECT COUNT(*) n FROM dbo.lembur_form WHERE status=?",[$s]); return sqlsrv_fetch_array($r,SQLSRV_FETCH_ASSOC)['n']??0; }
$nDiajukan = hitung($conn,'DIAJUKAN');

/* ---------- daftar form ---------- */
$forms = sqlsrv_query($conn,
    "SELECT lf.*, d.nama_dept,
            (SELECT COUNT(*) FROM dbo.lembur_detail x WHERE x.id_form=lf.id_form) AS jml_org,
            (SELECT SUM(durasi_jam) FROM dbo.lembur_detail x WHERE x.id_form=lf.id_form) AS total_jam
     FROM dbo.lembur_form lf
     LEFT JOIN dbo.department d ON d.id_dept = lf.department_id
     WHERE lf.status = ?
     ORDER BY lf.tanggal DESC, lf.id_form DESC", [$statusFilter]);

$page_title = "Approval Lembur HR";
include '../template/header.php';
include '../template/sidebar.php';
function h($v){return htmlspecialchars((string)($v??''));}
function tgl($v){return $v instanceof DateTime?$v->format('d-m-Y'):'—';}
$jenisLabel = ['biasa'=>'Biasa','libur'=>'Libur','hari_besar'=>'Hari Besar'];
?>

<main id="main" class="main">
  <div class="pagetitle"><h1>Approval Lembur (HR)</h1>
    <nav><ol class="breadcrumb"><li class="breadcrumb-item"><a href="../index.php">Home</a></li>
    <li class="breadcrumb-item active">Approval Lembur</li></ol></nav></div>

  <section class="section">
    <?= $pesan ?>

    <ul class="nav nav-tabs mb-3">
      <li class="nav-item"><a class="nav-link <?= $tab==='diajukan'?'active':'' ?>" href="?tab=diajukan">
        Menunggu <span class="badge bg-warning"><?= $nDiajukan ?></span></a></li>
      <li class="nav-item"><a class="nav-link <?= $tab==='disetujui'?'active':'' ?>" href="?tab=disetujui">Disetujui</a></li>
      <li class="nav-item"><a class="nav-link <?= $tab==='ditolak'?'active':'' ?>" href="?tab=ditolak">Ditolak</a></li>
    </ul>

    <div class="card"><div class="card-body">
      <div class="table-responsive">
      <table class="table table-hover align-middle">
        <thead class="table-light"><tr>
          <th>No Form</th><th>Tanggal</th><th>Divisi</th><th>Jenis</th>
          <th>Karyawan</th><th>Total Jam</th><th>Dibuat Oleh</th>
          <?php if ($tab==='diajukan'): ?><th style="width:230px">Keputusan</th>
          <?php else: ?><th>Catatan HR</th><?php endif; ?>
        </tr></thead>
        <tbody>
        <?php $ada=false; while($r=sqlsrv_fetch_array($forms,SQLSRV_FETCH_ASSOC)): $ada=true; ?>
          <tr>
            <td><strong><?= h($r['no_form']) ?></strong></td>
            <td><?= tgl($r['tanggal']) ?></td>
            <td><small><?= h($r['nama_dept']??'—') ?></small></td>
            <td><?= h($jenisLabel[$r['jenis']]??$r['jenis']) ?></td>
            <td class="text-center"><?= $r['jml_org'] ?> org</td>
            <td><strong><?= number_format((float)$r['total_jam'],1) ?> jam</strong></td>
            <td><small><?= h($r['dibuat_nama']??'—') ?></small></td>
            <?php if ($tab==='diajukan'): ?>
              <td>
                <div class="d-flex gap-1 mb-1">
                  <a href="cetak.php?id=<?= $r['id_form'] ?>" target="_blank" class="btn btn-sm btn-outline-secondary" title="lihat/cetak">
                    <i class="bi bi-eye"></i></a>
                </div>
                <form method="POST" class="d-flex flex-column gap-1">
                  <input type="hidden" name="id_form" value="<?= $r['id_form'] ?>">
                  <input type="text" name="hr_catatan" class="form-control form-control-sm" placeholder="catatan (opsional)">
                  <div class="btn-group btn-group-sm">
                    <button name="keputusan" value="setuju" class="btn btn-success">Setuju</button>
                    <button name="keputusan" value="tolak" class="btn btn-danger">Tolak</button>
                  </div>
                </form>
              </td>
            <?php else: ?>
              <td><small><?= h($r['hr_catatan']??'—') ?><br>
                  <span class="text-muted"><?= $r['hr_pada'] instanceof DateTime?$r['hr_pada']->format('d-m-Y H:i'):'' ?></span></small>
                <a href="cetak.php?id=<?= $r['id_form'] ?>" target="_blank" class="btn btn-sm btn-outline-secondary ms-1"><i class="bi bi-printer"></i></a>
              </td>
            <?php endif; ?>
          </tr>
        <?php endwhile; if(!$ada): ?>
          <tr><td colspan="8" class="text-center text-muted py-4">Tidak ada form.</td></tr>
        <?php endif; ?>
        </tbody>
      </table>
      </div>
    </div></div>
  </section>
</main>

<?php include '../template/footer.php'; ?>
