<?php
/**
 * kedisiplinan/sp_histori.php
 * Histori Surat Peringatan - KHUSUS HRD & Admin IT.
 * ?peg=ID untuk menyaring satu pegawai.
 */
require_once __DIR__ . '/../auth/auth.php';
wajib_peran(['hr','admin_it']);          // hanya HRD & Admin IT
require_once __DIR__ . '/../config/koneksi_sqlsrv.php';

$pesan = '';

/* ---------- ubah status SP ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['aksi'] ?? '') === 'ubah_status') {
    $idSp = (int)($_POST['id_sp'] ?? 0);
    $stat = $_POST['status'] ?? '';
    if ($idSp && in_array($stat, ['AKTIF','SELESAI','DICABUT'], true)) {
        $st = sqlsrv_query($conn, "UPDATE dbo.surat_peringatan SET status=? WHERE id_sp=?", [$stat, $idSp]);
        if ($st !== false) { sqlsrv_free_stmt($st);
            $pesan = "<div class='alert alert-success'>Status SP diperbarui.</div>"; }
        else $pesan = "<div class='alert alert-danger'>Gagal memperbarui status.</div>";
    }
}

/* ---------- filter ---------- */
$fPeg    = (int)($_GET['peg'] ?? 0);
$fJenis  = $_GET['jenis']  ?? '';
$fStatus = $_GET['status'] ?? '';
$cari    = trim($_GET['cari'] ?? '');

$where = ["1=1"]; $par = [];
if ($fPeg)             { $where[] = "s.pegawai_id = ?";  $par[] = $fPeg; }
if ($fJenis !== '')    { $where[] = "s.jenis = ?";       $par[] = $fJenis; }
if ($fStatus !== '')   { $where[] = "s.status = ?";      $par[] = $fStatus; }
if ($cari !== '')      { $where[] = "(s.nama_pegawai LIKE ? OR s.no_sp LIKE ?)"; $par[]="%$cari%"; $par[]="%$cari%"; }

$rows = sqlsrv_query($conn,
  "SELECT TOP 300 s.*, d.nama_dept, p.nik
   FROM dbo.surat_peringatan s
   LEFT JOIN dbo.pegawai p ON p.id_peg = s.pegawai_id
   LEFT JOIN dbo.department d ON d.id_dept = s.department_id
   WHERE ".implode(' AND ', $where)."
   ORDER BY s.tanggal_terbit DESC, s.id_sp DESC", $par);

/* ---------- ringkasan ---------- */
$rs = sqlsrv_query($conn,
  "SELECT SUM(CASE WHEN jenis='TEGURAN' THEN 1 ELSE 0 END) AS teguran,
          SUM(CASE WHEN jenis='SP1' THEN 1 ELSE 0 END) AS sp1,
          SUM(CASE WHEN jenis='SP2' THEN 1 ELSE 0 END) AS sp2,
          SUM(CASE WHEN jenis='SP3' THEN 1 ELSE 0 END) AS sp3,
          SUM(CASE WHEN status='AKTIF' THEN 1 ELSE 0 END) AS aktif,
          COUNT(*) AS total
   FROM dbo.surat_peringatan");
$ring = $rs ? sqlsrv_fetch_array($rs, SQLSRV_FETCH_ASSOC) : [];
if ($rs) sqlsrv_free_stmt($rs);

/* nama pegawai bila difilter */
$namaPeg = '';
if ($fPeg) {
    $rs = sqlsrv_query($conn, "SELECT nama_peg FROM dbo.pegawai WHERE id_peg=?", [$fPeg]);
    if ($rs && ($x = sqlsrv_fetch_array($rs, SQLSRV_FETCH_ASSOC))) $namaPeg = $x['nama_peg'];
    if ($rs) sqlsrv_free_stmt($rs);
}

$page_title = "Histori Surat Peringatan";
include __DIR__ . '/../template/header.php';
include __DIR__ . '/../template/sidebar.php';
function h($v){ return htmlspecialchars((string)($v ?? '')); }
function tglF($v){ return $v instanceof DateTime ? $v->format('d-m-Y') : '—'; }
$warnaJenis = ['TEGURAN'=>'secondary','SP1'=>'info','SP2'=>'warning','SP3'=>'danger'];
$warnaStat  = ['AKTIF'=>'danger','SELESAI'=>'success','DICABUT'=>'secondary'];
?>
<main id="main" class="main">
  <div class="pagetitle d-flex justify-content-between align-items-start flex-wrap">
    <div><h1>Histori Surat Peringatan<?= $namaPeg ? ' — '.h($namaPeg) : '' ?></h1>
      <nav><ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="<?php echo BASE_URL; ?>/s-core/index.php">Home</a></li>
        <li class="breadcrumb-item active">Histori SP</li>
      </ol></nav></div>
    <div class="pt-2">
      <a href="sp_form.php" class="btn btn-danger btn-sm"><i class="bi bi-file-earmark-plus"></i> Terbitkan SP</a>
      <?php if ($fPeg): ?>
        <a href="sp_histori.php" class="btn btn-outline-secondary btn-sm">Lihat Semua</a>
      <?php endif; ?>
    </div>
  </div>

  <section class="section">
    <?= $pesan ?>

    <!-- RINGKASAN -->
    <div class="row g-2 mb-3">
      <?php foreach ([['Teguran','teguran','secondary'],['SP 1','sp1','info'],
                      ['SP 2','sp2','warning'],['SP 3','sp3','danger'],
                      ['Masih Aktif','aktif','dark'],['Total','total','primary']] as $c): ?>
        <div class="col-6 col-md-2">
          <div class="border rounded p-2 text-center bg-white">
            <div class="fs-4 fw-bold text-<?= $c[2] ?>"><?= number_format((int)($ring[$c[1]] ?? 0)) ?></div>
            <small class="text-muted"><?= $c[0] ?></small>
          </div>
        </div>
      <?php endforeach; ?>
    </div>

    <!-- FILTER -->
    <form method="GET" class="row g-2 mb-3 align-items-end">
      <?php if ($fPeg): ?><input type="hidden" name="peg" value="<?= $fPeg ?>"><?php endif; ?>
      <div class="col-auto"><label class="form-label small mb-1">Jenis</label>
        <select name="jenis" class="form-select form-select-sm">
          <option value="">-- semua --</option>
          <?php foreach (['TEGURAN','SP1','SP2','SP3'] as $j): ?>
            <option value="<?= $j ?>" <?= $fJenis===$j?'selected':'' ?>><?= $j ?></option>
          <?php endforeach; ?>
        </select></div>
      <div class="col-auto"><label class="form-label small mb-1">Status</label>
        <select name="status" class="form-select form-select-sm">
          <option value="">-- semua --</option>
          <?php foreach (['AKTIF','SELESAI','DICABUT'] as $s2): ?>
            <option value="<?= $s2 ?>" <?= $fStatus===$s2?'selected':'' ?>><?= $s2 ?></option>
          <?php endforeach; ?>
        </select></div>
      <div class="col-auto"><label class="form-label small mb-1">Cari</label>
        <input type="text" name="cari" class="form-control form-control-sm"
               placeholder="nama / no SP" value="<?= h($cari) ?>"></div>
      <div class="col-auto"><button class="btn btn-primary btn-sm">Filter</button></div>
    </form>

    <div class="card"><div class="card-body">
      <div class="table-responsive">
      <table class="table table-sm table-hover align-middle">
        <thead class="table-light"><tr>
          <th>No SP</th><th>Pegawai</th><th>Departemen</th><th>Jenis</th><th>Kategori</th>
          <th>Terbit</th><th>Berlaku s/d</th><th>Alasan</th><th>Status</th><th></th>
        </tr></thead>
        <tbody>
        <?php $n=0; while($rows && ($r=sqlsrv_fetch_array($rows,SQLSRV_FETCH_ASSOC))): $n++; ?>
          <tr>
            <td><small><strong><?= h($r['no_sp']) ?></strong></small></td>
            <td><strong><?= h($r['nama_pegawai']) ?></strong>
                <?php if ($r['nik']): ?><br><small class="text-muted"><?= h($r['nik']) ?></small><?php endif; ?></td>
            <td><small><?= h($r['nama_dept'] ?: '—') ?></small></td>
            <td><span class="badge bg-<?= $warnaJenis[$r['jenis']] ?? 'secondary' ?>"><?= h($r['jenis']) ?></span></td>
            <td><small><?= h($r['kategori'] ?: '—') ?></small></td>
            <td><small><?= tglF($r['tanggal_terbit']) ?></small></td>
            <td><small><?= tglF($r['berlaku_sampai']) ?></small></td>
            <td style="max-width:220px"><small><?= h($r['alasan'] ?: '—') ?></small></td>
            <td><span class="badge bg-<?= $warnaStat[$r['status']] ?? 'secondary' ?>"><?= h($r['status']) ?></span></td>
            <td>
              <form method="POST" class="d-flex gap-1">
                <input type="hidden" name="aksi" value="ubah_status">
                <input type="hidden" name="id_sp" value="<?= (int)$r['id_sp'] ?>">
                <select name="status" class="form-select form-select-sm" style="width:105px">
                  <?php foreach (['AKTIF','SELESAI','DICABUT'] as $s3): ?>
                    <option value="<?= $s3 ?>" <?= $r['status']===$s3?'selected':'' ?>><?= $s3 ?></option>
                  <?php endforeach; ?>
                </select>
                <button class="btn btn-sm btn-outline-primary" title="simpan"><i class="bi bi-save"></i></button>
              </form>
            </td>
          </tr>
        <?php endwhile; if(!$n): ?>
          <tr><td colspan="10" class="text-center text-muted py-4">Belum ada surat peringatan.</td></tr>
        <?php endif; ?>
        </tbody>
      </table>
      </div>
    </div></div>
  </section>
</main>
<?php include __DIR__ . '/../template/footer.php'; ?>
