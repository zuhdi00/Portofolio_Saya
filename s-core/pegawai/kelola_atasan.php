<?php
/**
 * pegawai/kelola_atasan.php
 * Menetapkan atasan untuk pegawai - satuan maupun massal per divisi.
 */
require_once __DIR__ . '/../auth/auth.php';
wajib_izin('pegawai_edit');
require_once __DIR__ . '/../config/koneksi_sqlsrv.php';

$pesan = '';

/* ---------- simpan perubahan ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $aksi = $_POST['aksi'] ?? '';

    if ($aksi === 'simpan_baris') {
        $ids     = $_POST['peg']    ?? [];
        $atasans = $_POST['atasan'] ?? [];
        $n = 0;
        foreach ($ids as $i => $pid) {
            $pid = (int)$pid;
            $aid = (int)($atasans[$i] ?? 0);
            if (!$pid) continue;
            if ($aid === $pid) continue;                 // tidak boleh atasan diri sendiri
            $st = sqlsrv_query($conn,
                "UPDATE dbo.pegawai SET atasan_id = ? WHERE id_peg = ?",
                [$aid ?: null, $pid]);
            if ($st !== false) { sqlsrv_free_stmt($st); $n++; }
        }
        $pesan = "<div class='alert alert-success'>$n baris disimpan.</div>";
    }

    elseif ($aksi === 'massal') {
        $dept   = (int)($_POST['dept_massal'] ?? 0);
        $atasan = (int)($_POST['atasan_massal'] ?? 0);
        $timpa  = !empty($_POST['timpa']);

        if (!$dept || !$atasan) {
            $pesan = "<div class='alert alert-danger'>Pilih divisi dan atasan terlebih dahulu.</div>";
        } else {
            $sql = "UPDATE p SET p.atasan_id = ?
                    FROM dbo.pegawai p
                    LEFT JOIN dbo.unit_kerja u ON u.id = p.unit_kerja_id
                    WHERE u.department_id = ? AND p.is_aktif = 1 AND p.id_peg <> ?";
            if (!$timpa) $sql .= " AND p.atasan_id IS NULL";
            $st = sqlsrv_query($conn, $sql, [$atasan, $dept, $atasan]);
            if ($st === false) {
                $e = sqlsrv_errors();
                $pesan = "<div class='alert alert-danger'>Gagal: "
                       . htmlspecialchars($e[0]['message'] ?? '') . "</div>";
            } else {
                $jml = sqlsrv_rows_affected($st);
                sqlsrv_free_stmt($st);
                $pesan = "<div class='alert alert-success'>"
                       . ($jml > 0 ? "$jml pegawai" : "Tidak ada pegawai yang")
                       . " diperbarui atasannya.</div>";
            }
        }
    }
}

/* ---------- filter ---------- */
$fDept  = (int)($_GET['dept'] ?? 0);
$fStat  = $_GET['stat'] ?? 'semua';     // semua | belum | sudah
$cari   = trim($_GET['cari'] ?? '');

$where = ["p.is_aktif = 1"]; $par = [];
if ($fDept) { $where[] = "u.department_id = ?"; $par[] = $fDept; }
if ($fStat === 'belum') $where[] = "p.atasan_id IS NULL";
if ($fStat === 'sudah') $where[] = "p.atasan_id IS NOT NULL";
if ($cari !== '') { $where[] = "(p.nama_peg LIKE ? OR p.nik LIKE ?)"; $par[]="%$cari%"; $par[]="%$cari%"; }

$rows = sqlsrv_query($conn,
  "SELECT TOP 300 p.id_peg, p.nik, p.nama_peg, p.atasan_id,
          d.nama_dept, a.nama_peg AS nama_atasan
   FROM dbo.pegawai p
   LEFT JOIN dbo.unit_kerja u ON u.id = p.unit_kerja_id
   LEFT JOIN dbo.department d ON d.id_dept = u.department_id
   LEFT JOIN dbo.pegawai a ON a.id_peg = p.atasan_id
   WHERE ".implode(' AND ', $where)."
   ORDER BY d.nama_dept, p.nama_peg", $par);

/* ---------- data pendukung ---------- */
$deptList = []; $rs = sqlsrv_query($conn,"SELECT id_dept, nama_dept FROM dbo.department ORDER BY nama_dept");
if ($rs) { while($x = sqlsrv_fetch_array($rs, SQLSRV_FETCH_ASSOC)) $deptList[] = $x; sqlsrv_free_stmt($rs); }

/* kandidat atasan: semua pegawai aktif */
$pegList = []; $rs = sqlsrv_query($conn,
  "SELECT p.id_peg, p.nik, p.nama_peg, d.nama_dept
   FROM dbo.pegawai p
   LEFT JOIN dbo.unit_kerja u ON u.id = p.unit_kerja_id
   LEFT JOIN dbo.department d ON d.id_dept = u.department_id
   WHERE p.is_aktif = 1 ORDER BY p.nama_peg");
if ($rs) { while($x = sqlsrv_fetch_array($rs, SQLSRV_FETCH_ASSOC)) $pegList[] = $x; sqlsrv_free_stmt($rs); }

/* ringkasan */
$rs = sqlsrv_query($conn,
  "SELECT COUNT(*) AS total,
          SUM(CASE WHEN atasan_id IS NULL THEN 1 ELSE 0 END) AS belum
   FROM dbo.pegawai WHERE is_aktif = 1");
$ring = $rs ? sqlsrv_fetch_array($rs, SQLSRV_FETCH_ASSOC) : ['total'=>0,'belum'=>0];
if ($rs) sqlsrv_free_stmt($rs);

$page_title = "Kelola Atasan Pegawai";
include __DIR__ . '/../template/header.php';
include __DIR__ . '/../template/sidebar.php';
function h($v){ return htmlspecialchars((string)($v ?? '')); }
?>

<main id="main" class="main">
  <div class="pagetitle d-flex justify-content-between align-items-start flex-wrap">
    <div><h1>Kelola Atasan Pegawai</h1>
      <nav><ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="index.php">Pegawai</a></li>
        <li class="breadcrumb-item active">Kelola Atasan</li>
      </ol></nav></div>
    <div class="pt-2"><a href="index.php" class="btn btn-outline-primary btn-sm">
      <i class="bi bi-arrow-left"></i> Daftar Pegawai</a></div>
  </div>

  <section class="section">
    <?= $pesan ?>

    <div class="alert alert-info py-2">
      <strong><?= number_format((int)$ring['belum']) ?></strong> dari
      <strong><?= number_format((int)$ring['total']) ?></strong> pegawai aktif belum punya atasan.
    </div>

    <!-- PENETAPAN MASSAL -->
    <div class="card mb-3"><div class="card-body">
      <h5 class="card-title"><i class="bi bi-people me-2"></i>Tetapkan Atasan Massal (per Divisi)</h5>
      <form method="POST" class="row g-2 align-items-end"
            onsubmit="return confirm('Terapkan atasan ke seluruh pegawai divisi ini?')">
        <input type="hidden" name="aksi" value="massal">
        <div class="col-md-4">
          <label class="form-label small mb-1">Divisi</label>
          <select name="dept_massal" class="form-select form-select-sm" required>
            <option value="">-- pilih divisi --</option>
            <?php foreach($deptList as $d): ?>
              <option value="<?= (int)$d['id_dept'] ?>"><?= h($d['nama_dept']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-4">
          <label class="form-label small mb-1">Atasan</label>
          <input type="text" class="form-control form-control-sm" list="dlAtasan"
                 id="cariAtasanMassal" placeholder="ketik nama atasan" autocomplete="off">
          <input type="hidden" name="atasan_massal" id="atasanMassalId">
        </div>
        <div class="col-md-2">
          <div class="form-check">
            <input class="form-check-input" type="checkbox" name="timpa" id="timpa" value="1">
            <label class="form-check-label small" for="timpa">Timpa yang sudah ada</label>
          </div>
        </div>
        <div class="col-md-2">
          <button class="btn btn-primary btn-sm w-100"><i class="bi bi-check2-all"></i> Terapkan</button>
        </div>
      </form>
      <small class="text-muted">Tanpa centang "timpa", hanya pegawai yang belum punya atasan yang diisi.</small>
    </div></div>

    <!-- FILTER -->
    <form method="GET" class="row g-2 mb-3 align-items-end">
      <div class="col-md-3"><label class="form-label small mb-1">Divisi</label>
        <select name="dept" class="form-select form-select-sm">
          <option value="0">-- semua --</option>
          <?php foreach($deptList as $d): ?>
            <option value="<?= (int)$d['id_dept'] ?>" <?= $fDept===(int)$d['id_dept']?'selected':'' ?>>
              <?= h($d['nama_dept']) ?></option>
          <?php endforeach; ?>
        </select></div>
      <div class="col-md-2"><label class="form-label small mb-1">Status</label>
        <select name="stat" class="form-select form-select-sm">
          <option value="semua" <?= $fStat==='semua'?'selected':'' ?>>Semua</option>
          <option value="belum" <?= $fStat==='belum'?'selected':'' ?>>Belum ada atasan</option>
          <option value="sudah" <?= $fStat==='sudah'?'selected':'' ?>>Sudah ada atasan</option>
        </select></div>
      <div class="col-md-3"><label class="form-label small mb-1">Cari nama / NIK</label>
        <input type="text" name="cari" class="form-control form-control-sm" value="<?= h($cari) ?>"></div>
      <div class="col-md-2"><button class="btn btn-secondary btn-sm">Filter</button></div>
    </form>

    <!-- DAFTAR + EDIT SATUAN -->
    <form method="POST">
      <input type="hidden" name="aksi" value="simpan_baris">
      <div class="card"><div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-2">
          <h5 class="card-title mb-0"><i class="bi bi-person-lines-fill me-2"></i>Daftar Pegawai</h5>
          <button class="btn btn-success btn-sm"><i class="bi bi-save"></i> Simpan Semua Perubahan</button>
        </div>
        <div class="table-responsive">
        <table class="table table-sm table-hover align-middle">
          <thead class="table-light"><tr>
            <th style="width:90px">NIK</th><th>Nama Pegawai</th><th>Divisi</th>
            <th style="width:32%">Atasan</th>
          </tr></thead>
          <tbody>
          <?php $n=0; while($rows && ($r = sqlsrv_fetch_array($rows, SQLSRV_FETCH_ASSOC))): $n++; ?>
            <tr>
              <td><?= h($r['nik']) ?></td>
              <td><strong><?= h($r['nama_peg']) ?></strong></td>
              <td><small><?= h($r['nama_dept'] ?: '—') ?></small></td>
              <td>
                <input type="hidden" name="peg[]" value="<?= (int)$r['id_peg'] ?>">
                <select name="atasan[]" class="form-select form-select-sm">
                  <option value="">— belum ditentukan —</option>
                  <?php foreach($pegList as $a):
                        if ((int)$a['id_peg'] === (int)$r['id_peg']) continue;   // bukan diri sendiri
                        $sel = ((int)$r['atasan_id'] === (int)$a['id_peg']) ? 'selected' : ''; ?>
                    <option value="<?= (int)$a['id_peg'] ?>" <?= $sel ?>>
                      <?= h($a['nama_peg']) ?><?= $a['nama_dept'] ? ' — '.h($a['nama_dept']) : '' ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </td>
            </tr>
          <?php endwhile; if(!$n): ?>
            <tr><td colspan="4" class="text-center text-muted py-4">Tidak ada data.</td></tr>
          <?php endif; ?>
          </tbody>
        </table>
        </div>
        <?php if ($n >= 300): ?>
          <div class="alert alert-warning py-2 mb-0 mt-2">
            Menampilkan 300 baris teratas. Persempit filter untuk melihat sisanya.</div>
        <?php endif; ?>
      </div></div>
    </form>
  </section>
</main>

<datalist id="dlAtasan">
  <?php foreach($pegList as $a): ?>
    <option value="<?= h($a['nama_peg'].' — '.$a['nik']) ?>" data-id="<?= (int)$a['id_peg'] ?>"></option>
  <?php endforeach; ?>
</datalist>

<script>
// petakan teks datalist -> id pegawai
var PEG_MAP = <?= json_encode(array_reduce($pegList, function($c,$a){
    $c[$a['nama_peg'].' — '.$a['nik']] = (int)$a['id_peg']; return $c; }, [])) ?>;

document.addEventListener('DOMContentLoaded', function () {
  var inp = document.getElementById('cariAtasanMassal');
  var hid = document.getElementById('atasanMassalId');
  if (!inp || !hid) return;
  inp.addEventListener('input', function () {
    hid.value = PEG_MAP[inp.value] || '';
    inp.classList.toggle('is-invalid', inp.value !== '' && !hid.value);
  });
});
</script>

<?php include __DIR__ . '/../template/footer.php'; ?>
