<?php
/**
 * pegawai/kategori_absensi.php
 * Dashboard klasifikasi pegawai:
 *   - MASUK ABSENSI        : presensi dari mesin ZKTeco
 *   - TIDAK MASUK ABSENSI  : kehadiran dicatat manual oleh PIC HR
 */
require_once __DIR__ . '/../auth/auth.php';
wajib_izin('pegawai_lihat');
require_once __DIR__ . '/../config/koneksi_sqlsrv.php';

$bolehUbah = boleh('pegawai_edit');
$pesan = '';

/* ---------- simpan perubahan ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $bolehUbah) {
    $aksi = $_POST['aksi'] ?? '';

    if ($aksi === 'ubah_satuan') {
        $id  = (int)($_POST['id_peg'] ?? 0);
        $ikt = (int)($_POST['ikut'] ?? 1);
        $ket = trim($_POST['ket'] ?? '');
        $pic = trim($_POST['pic'] ?? '');
        if ($id) {
            $st = sqlsrv_query($conn,
              "UPDATE dbo.pegawai SET ikut_absensi=?, absensi_keterangan=?, absensi_pic=? WHERE id_peg=?",
              [$ikt, $ket ?: null, $pic ?: null, $id]);
            if ($st !== false) { sqlsrv_free_stmt($st);
                $pesan = "<div class='alert alert-success'>Kategori diperbarui.</div>"; }
            else $pesan = "<div class='alert alert-danger'>Gagal menyimpan.</div>";
        }
    }
    elseif ($aksi === 'massal') {
        $ids = $_POST['pilih'] ?? [];
        $ikt = (int)($_POST['ikut_massal'] ?? 1);
        $ket = trim($_POST['ket_massal'] ?? '');
        $pic = trim($_POST['pic_massal'] ?? '');
        $n = 0;
        foreach ($ids as $pid) {
            $pid = (int)$pid; if (!$pid) continue;
            $st = sqlsrv_query($conn,
              "UPDATE dbo.pegawai SET ikut_absensi=?, absensi_keterangan=?, absensi_pic=? WHERE id_peg=?",
              [$ikt, $ket ?: null, $pic ?: null, $pid]);
            if ($st !== false) { sqlsrv_free_stmt($st); $n++; }
        }
        $pesan = "<div class='alert alert-success'>$n pegawai diperbarui.</div>";
    }
}

/* ---------- ringkasan ---------- */
$rs = sqlsrv_query($conn,
  "SELECT SUM(CASE WHEN ikut_absensi=1 THEN 1 ELSE 0 END) AS masuk,
          SUM(CASE WHEN ikut_absensi=0 THEN 1 ELSE 0 END) AS tidak,
          COUNT(*) AS total
   FROM dbo.pegawai WHERE is_aktif=1");
$ring = $rs ? sqlsrv_fetch_array($rs, SQLSRV_FETCH_ASSOC) : [];
if ($rs) sqlsrv_free_stmt($rs);
$masuk = (int)($ring['masuk'] ?? 0);
$tidak = (int)($ring['tidak'] ?? 0);
$total = (int)($ring['total'] ?? 0);

/* ---------- sebaran per divisi ---------- */
$perDept = [];
$rs = sqlsrv_query($conn,
  "SELECT ISNULL(d.nama_dept,'(tanpa divisi)') AS dept,
          SUM(CASE WHEN p.ikut_absensi=1 THEN 1 ELSE 0 END) AS masuk,
          SUM(CASE WHEN p.ikut_absensi=0 THEN 1 ELSE 0 END) AS tidak
   FROM dbo.pegawai p
   LEFT JOIN dbo.unit_kerja u ON u.id = p.unit_kerja_id
   LEFT JOIN dbo.department d ON d.id_dept = u.department_id
   WHERE p.is_aktif=1
   GROUP BY d.nama_dept ORDER BY d.nama_dept");
if ($rs) { while($x=sqlsrv_fetch_array($rs,SQLSRV_FETCH_ASSOC)) $perDept[]=$x; sqlsrv_free_stmt($rs); }

/* ---------- filter daftar ---------- */
$kat   = $_GET['kat']  ?? 'tidak';       // masuk | tidak | semua
$fDept = (int)($_GET['dept'] ?? 0);
$cari  = trim($_GET['cari'] ?? '');

$where = ["p.is_aktif = 1"]; $par = [];
if ($kat === 'masuk') $where[] = "p.ikut_absensi = 1";
if ($kat === 'tidak') $where[] = "p.ikut_absensi = 0";
if ($fDept) { $where[] = "u.department_id = ?"; $par[] = $fDept; }
if ($cari !== '') { $where[] = "(p.nama_peg LIKE ? OR p.nik LIKE ?)"; $par[]="%$cari%"; $par[]="%$cari%"; }

$rows = sqlsrv_query($conn,
  "SELECT TOP 300 p.id_peg, p.nik, p.nama_peg, p.zkteco_userid, p.ikut_absensi,
          p.absensi_keterangan, p.absensi_pic, d.nama_dept, p.position_code
   FROM dbo.pegawai p
   LEFT JOIN dbo.unit_kerja u ON u.id = p.unit_kerja_id
   LEFT JOIN dbo.department d ON d.id_dept = u.department_id
   WHERE ".implode(' AND ',$where)."
   ORDER BY d.nama_dept, p.nama_peg", $par);

$deptList=[]; $rs=sqlsrv_query($conn,"SELECT id_dept,nama_dept FROM dbo.department ORDER BY nama_dept");
if ($rs) { while($x=sqlsrv_fetch_array($rs,SQLSRV_FETCH_ASSOC)) $deptList[]=$x; sqlsrv_free_stmt($rs); }

$page_title = "Kategori Absensi Pegawai";
include __DIR__ . '/../template/header.php';
include __DIR__ . '/../template/sidebar.php';
function h($v){ return htmlspecialchars((string)($v ?? '')); }
$pMasuk = $total ? round($masuk/$total*100) : 0;
?>
<style>
  .k-card{border:1px solid #eef1f6;border-radius:14px;padding:20px;background:#fff;height:100%;
          transition:transform .15s, box-shadow .15s}
  .k-card:hover{transform:translateY(-2px);box-shadow:0 .4rem 1rem rgba(1,41,112,.1)}
  .k-ic{width:48px;height:48px;border-radius:12px;display:flex;align-items:center;
        justify-content:center;font-size:1.4rem;margin-bottom:12px}
  .k-n{font-size:2rem;font-weight:700;color:#012970;line-height:1}
  .k-l{font-size:.85rem;color:#899bbd;margin-top:5px}
  .bar{height:8px;border-radius:8px;background:#eef1f6;overflow:hidden}
  .bar>span{display:block;height:100%;background:#4154f1}
</style>

<main id="main" class="main">
  <div class="pagetitle d-flex justify-content-between align-items-start flex-wrap">
    <div><h1>Kategori Absensi Pegawai</h1>
      <nav><ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="index.php">Pegawai</a></li>
        <li class="breadcrumb-item active">Kategori Absensi</li>
      </ol></nav></div>
    <div class="pt-2"><a href="index.php" class="btn btn-outline-primary btn-sm">
      <i class="bi bi-arrow-left"></i> Daftar Pegawai</a></div>
  </div>

  <section class="section">
    <?= $pesan ?>

    <!-- KARTU RINGKASAN -->
    <div class="row g-3 mb-4">
      <div class="col-md-4">
        <a href="?kat=masuk" class="text-decoration-none">
        <div class="k-card">
          <div class="k-ic" style="background:#ecfdf5;color:#10b981"><i class="bi bi-fingerprint"></i></div>
          <div class="k-n"><?= number_format($masuk) ?></div>
          <div class="k-l">Masuk Absensi <span class="text-muted">(tap mesin ZKTeco)</span></div>
        </div></a>
      </div>
      <div class="col-md-4">
        <a href="?kat=tidak" class="text-decoration-none">
        <div class="k-card">
          <div class="k-ic" style="background:#fffbeb;color:#f59e0b"><i class="bi bi-clipboard-check"></i></div>
          <div class="k-n"><?= number_format($tidak) ?></div>
          <div class="k-l">Tidak Masuk Absensi <span class="text-muted">(manual PIC HR)</span></div>
        </div></a>
      </div>
      <div class="col-md-4">
        <div class="k-card">
          <div class="k-ic" style="background:#eff6ff;color:#3b82f6"><i class="bi bi-people"></i></div>
          <div class="k-n"><?= number_format($total) ?></div>
          <div class="k-l">Total Pegawai Aktif</div>
          <div class="bar mt-2"><span style="width:<?= $pMasuk ?>%"></span></div>
          <small class="text-muted"><?= $pMasuk ?>% memakai mesin</small>
        </div>
      </div>
    </div>

    <div class="row g-3">
      <!-- SEBARAN PER DIVISI -->
      <div class="col-lg-4">
        <div class="card h-100"><div class="card-body">
          <h5 class="card-title"><i class="bi bi-diagram-3 me-2"></i>Sebaran per Divisi</h5>
          <div class="table-responsive" style="max-height:520px;overflow-y:auto">
          <table class="table table-sm mb-0">
            <thead class="table-light"><tr><th>Divisi</th>
              <th class="text-center">Mesin</th><th class="text-center">Manual</th></tr></thead>
            <tbody>
            <?php foreach($perDept as $d): ?>
              <tr>
                <td><small><?= h($d['dept']) ?></small></td>
                <td class="text-center"><span class="badge bg-success"><?= (int)$d['masuk'] ?></span></td>
                <td class="text-center">
                  <?php if ((int)$d['tidak'] > 0): ?>
                    <span class="badge bg-warning"><?= (int)$d['tidak'] ?></span>
                  <?php else: ?><span class="text-muted">—</span><?php endif; ?>
                </td>
              </tr>
            <?php endforeach; if(!$perDept): ?>
              <tr><td colspan="3" class="text-center text-muted py-3">Belum ada data.</td></tr>
            <?php endif; ?>
            </tbody>
          </table>
          </div>
        </div></div>
      </div>

      <!-- DAFTAR PEGAWAI -->
      <div class="col-lg-8">
        <div class="card"><div class="card-body">
          <h5 class="card-title"><i class="bi bi-person-lines-fill me-2"></i>Daftar Pegawai</h5>

          <form method="GET" class="row g-2 mb-3 align-items-end">
            <div class="col-md-3"><label class="form-label small mb-1">Kategori</label>
              <select name="kat" class="form-select form-select-sm">
                <option value="semua" <?= $kat==='semua'?'selected':'' ?>>Semua</option>
                <option value="masuk" <?= $kat==='masuk'?'selected':'' ?>>Masuk absensi</option>
                <option value="tidak" <?= $kat==='tidak'?'selected':'' ?>>Tidak masuk absensi</option>
              </select></div>
            <div class="col-md-3"><label class="form-label small mb-1">Divisi</label>
              <select name="dept" class="form-select form-select-sm">
                <option value="0">-- semua --</option>
                <?php foreach($deptList as $d): ?>
                  <option value="<?= (int)$d['id_dept'] ?>" <?= $fDept===(int)$d['id_dept']?'selected':'' ?>>
                    <?= h($d['nama_dept']) ?></option>
                <?php endforeach; ?>
              </select></div>
            <div class="col-md-3"><label class="form-label small mb-1">Cari</label>
              <input type="text" name="cari" class="form-control form-control-sm"
                     placeholder="nama / NIK" value="<?= h($cari) ?>"></div>
            <div class="col-md-2"><button class="btn btn-secondary btn-sm w-100">Filter</button></div>
          </form>

          <?php if ($bolehUbah): ?>
          <form method="POST" id="formMassal">
            <input type="hidden" name="aksi" value="massal">
            <div class="row g-2 mb-2 align-items-end bg-light p-2 rounded">
              <div class="col-md-3">
                <label class="form-label small mb-1">Ubah terpilih jadi</label>
                <select name="ikut_massal" class="form-select form-select-sm">
                  <option value="1">Masuk absensi (mesin)</option>
                  <option value="0">Tidak masuk absensi (manual)</option>
                </select></div>
              <div class="col-md-3"><label class="form-label small mb-1">Keterangan</label>
                <input type="text" name="ket_massal" class="form-control form-control-sm"
                       placeholder="mis. Direksi / kerja luar"></div>
              <div class="col-md-3"><label class="form-label small mb-1">PIC HR</label>
                <input type="text" name="pic_massal" class="form-control form-control-sm"
                       placeholder="nama PIC"></div>
              <div class="col-md-3">
                <button class="btn btn-primary btn-sm w-100"
                        onclick="return confirm('Terapkan ke pegawai yang dicentang?')">
                  <i class="bi bi-check2-all"></i> Terapkan</button></div>
            </div>
          <?php endif; ?>

          <div class="table-responsive">
          <table class="table table-sm table-hover align-middle">
            <thead class="table-light"><tr>
              <?php if ($bolehUbah): ?>
                <th style="width:34px"><input type="checkbox" onclick="cekSemua(this)"></th>
              <?php endif; ?>
              <th>NIK</th><th>Nama</th><th>Divisi</th><th>ID Mesin</th>
              <th>Kategori</th><th>Keterangan / PIC</th>
            </tr></thead>
            <tbody>
            <?php $n=0; while($rows && ($r=sqlsrv_fetch_array($rows,SQLSRV_FETCH_ASSOC))): $n++; ?>
              <tr>
                <?php if ($bolehUbah): ?>
                  <td><input type="checkbox" name="pilih[]" form="formMassal" value="<?= (int)$r['id_peg'] ?>"></td>
                <?php endif; ?>
                <td><?= h($r['nik']) ?></td>
                <td><strong><?= h($r['nama_peg']) ?></strong>
                    <?php if ($r['position_code']): ?>
                      <br><small class="text-muted"><?= h($r['position_code']) ?></small><?php endif; ?></td>
                <td><small><?= h($r['nama_dept'] ?: '—') ?></small></td>
                <td><small><?= h($r['zkteco_userid'] ?: '—') ?></small></td>
                <td>
                  <?php if ($r['ikut_absensi']): ?>
                    <span class="badge bg-success"><i class="bi bi-fingerprint"></i> Mesin</span>
                  <?php else: ?>
                    <span class="badge bg-warning"><i class="bi bi-clipboard-check"></i> Manual</span>
                  <?php endif; ?>
                </td>
                <td>
                  <small><?= h($r['absensi_keterangan'] ?: '—') ?>
                  <?php if ($r['absensi_pic']): ?>
                    <br><span class="text-muted">PIC: <?= h($r['absensi_pic']) ?></span>
                  <?php endif; ?></small>
                </td>
              </tr>
            <?php endwhile; if(!$n): ?>
              <tr><td colspan="7" class="text-center text-muted py-4">Tidak ada data.</td></tr>
            <?php endif; ?>
            </tbody>
          </table>
          </div>
          <?php if ($bolehUbah): ?></form><?php endif; ?>

          <?php if ($n >= 300): ?>
            <div class="alert alert-warning py-2 mb-0">Menampilkan 300 teratas. Persempit filter untuk sisanya.</div>
          <?php endif; ?>
        </div></div>
      </div>
    </div>
  </section>
</main>

<script>
function cekSemua(el){
  document.querySelectorAll('input[name="pilih[]"]').forEach(function(c){ c.checked = el.checked; });
}
</script>

<?php include __DIR__ . '/../template/footer.php'; ?>
