<?php
/**
 * pegawai/nonaktif.php
 * Daftar pegawai NONAKTIF (is_aktif = 0) - keluar/resign/PHK.
 * Bisa diaktifkan kembali oleh HR / Admin IT.
 */
require_once __DIR__ . '/../auth/auth.php';
wajib_izin('pegawai_lihat');
require_once __DIR__ . '/../config/koneksi_sqlsrv.php';

$bolehUbah = boleh('pegawai_edit');
$pesan = '';

/* aktifkan kembali */
if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['aksi']??'')==='aktifkan' && $bolehUbah) {
    $id = (int)($_POST['id_peg'] ?? 0);
    if ($id) {
        $st = sqlsrv_query($conn, "UPDATE dbo.pegawai SET is_aktif=1 WHERE id_peg=?", [$id]);
        if ($st !== false) { sqlsrv_free_stmt($st);
            $pesan = "<div class='alert alert-success'>Pegawai diaktifkan kembali.</div>"; }
        else $pesan = "<div class='alert alert-danger'>Gagal mengaktifkan.</div>";
    }
}

$cari = trim($_GET['cari'] ?? '');
$where = ["p.is_aktif = 0"]; $par = [];
if ($cari !== '') { $where[]="(p.nama_peg LIKE ? OR p.nik LIKE ?)"; $par[]="%$cari%"; $par[]="%$cari%"; }

$rows = sqlsrv_query($conn,
  "SELECT p.id_peg, p.nik, p.nama_peg, p.zkteco_userid, p.tgl_masuk,
          p.tgl_keluar, p.alasan_berhenti, d.nama_dept
   FROM dbo.pegawai p
   LEFT JOIN dbo.unit_kerja u ON u.id = p.unit_kerja_id
   LEFT JOIN dbo.department d ON d.id_dept = u.department_id
   WHERE ".implode(' AND ',$where)."
   ORDER BY p.tgl_keluar DESC, p.nama_peg", $par);

$rsN = sqlsrv_query($conn,"SELECT COUNT(*) n FROM dbo.pegawai WHERE is_aktif=0");
$jml = ($rsN && ($x=sqlsrv_fetch_array($rsN,SQLSRV_FETCH_ASSOC))) ? (int)$x['n'] : 0;

$page_title = "Pegawai Nonaktif";
include '../template/header.php';
include '../template/sidebar.php';
function h($v){return htmlspecialchars((string)($v??''));}
function tglF($v){return $v instanceof DateTime ? $v->format('d-m-Y') : '—';}
?>
<main id="main" class="main">
  <div class="pagetitle d-flex justify-content-between align-items-start flex-wrap">
    <div><h1>Pegawai Nonaktif</h1>
      <nav><ol class="breadcrumb"><li class="breadcrumb-item"><a href="index.php">Pegawai</a></li>
      <li class="breadcrumb-item active">Nonaktif</li></ol></nav></div>
    <div class="pt-2"><a href="index.php" class="btn btn-outline-primary btn-sm">
      <i class="bi bi-arrow-left"></i> Pegawai Aktif</a></div>
  </div>

  <section class="section">
    <?= $pesan ?>
    <form method="GET" class="row g-2 mb-3">
      <div class="col-md-4"><input type="text" name="cari" class="form-control form-control-sm"
             placeholder="cari nama / NIK" value="<?= h($cari) ?>"></div>
      <div class="col-md-2"><button class="btn btn-sm btn-primary">Cari</button></div>
    </form>

    <div class="card"><div class="card-body">
      <h5 class="card-title">Daftar Pegawai Nonaktif
        <span class="badge bg-secondary"><?= number_format($jml) ?></span></h5>
      <p class="text-muted small">Pegawai yang sudah tidak aktif (keluar/resign/PHK).
         Data & riwayat absensinya tetap tersimpan.</p>
      <div class="table-responsive">
      <table class="table table-hover table-sm align-middle">
        <thead class="table-light"><tr>
          <th>NIK</th><th>Nama</th><th>Divisi</th><th>Masuk</th><th>Keluar</th>
          <th>Alasan</th><th>ZKTeco ID</th><th></th>
        </tr></thead>
        <tbody>
        <?php $n=0; while($r=sqlsrv_fetch_array($rows,SQLSRV_FETCH_ASSOC)): $n++; ?>
          <tr>
            <td><?= h($r['nik']) ?></td>
            <td><strong><?= h($r['nama_peg']) ?></strong></td>
            <td><small><?= h($r['nama_dept']??'—') ?></small></td>
            <td><small><?= tglF($r['tgl_masuk']) ?></small></td>
            <td><small><?= tglF($r['tgl_keluar']) ?></small></td>
            <td><small><?= h($r['alasan_berhenti']??'—') ?></small></td>
            <td><small><?= h($r['zkteco_userid']??'—') ?></small></td>
            <td>
              <a href="detail.php?id=<?= $r['id_peg'] ?>" class="btn btn-sm btn-outline-secondary" title="detail">
                <i class="bi bi-eye"></i></a>
              <?php if ($bolehUbah): ?>
              <form method="POST" class="d-inline"
                    onsubmit="return confirm('Aktifkan kembali <?= h($r['nama_peg']) ?>?')">
                <input type="hidden" name="aksi" value="aktifkan">
                <input type="hidden" name="id_peg" value="<?= $r['id_peg'] ?>">
                <button class="btn btn-sm btn-outline-success" title="aktifkan kembali">
                  <i class="bi bi-arrow-counterclockwise"></i></button>
              </form>
              <?php endif; ?>
            </td>
          </tr>
        <?php endwhile; if(!$n): ?>
          <tr><td colspan="8" class="text-center text-muted py-4">Tidak ada pegawai nonaktif.</td></tr>
        <?php endif; ?>
        </tbody>
      </table>
      </div>
    </div></div>
  </section>
</main>
<?php include '../template/footer.php'; ?>
