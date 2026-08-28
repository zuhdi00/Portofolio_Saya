<?php
require_once __DIR__ . '/../auth/auth.php';
wajib_izin('absensi_rekap');
$page_title = "Rekap Absensi";
include '../template/header.php';
include '../config/koneksi_sqlsrv.php';   // $conn
include '../template/sidebar.php';

/* ---------- filter ---------- */
$hari_ini   = date('Y-m-d');
$awal_bulan = date('Y-m-01');
$dari    = $_GET['dari']    ?? $awal_bulan;
$sampai  = $_GET['sampai']  ?? $hari_ini;
$dept    = $_GET['dept']    ?? '';
$shift   = $_GET['shift']   ?? '';
$status  = $_GET['status']  ?? '';
$cari    = trim($_GET['cari'] ?? '');
$status_kary = $_GET['status_kary'] ?? '';
// URL lama memakai status_kary=staff; perlakukan sebagai filter default Semua.
if (strtolower(trim($status_kary)) === 'staff') {
  $status_kary = '';
}

$peran_user  = $_SESSION['hris_user']['peran'] ?? '';
$dept_user   = $_SESSION['hris_user']['department_id'] ?? '';
$is_restricted = in_array($peran_user, ['atasan', 'admin_divisi']);
if ($is_restricted && $dept_user) {
    $dept = $dept_user;
}

/* ---------- daftar departemen utk dropdown ---------- */
$deptList = [];
$rs = sqlsrv_query($conn, "SELECT id_dept, nama_dept FROM dbo.department ORDER BY nama_dept");
while ($r = sqlsrv_fetch_array($rs, SQLSRV_FETCH_ASSOC)) $deptList[] = $r;

/* ---------- bangun WHERE ---------- */
$where  = [
  "a.tanggal BETWEEN ? AND ?",
  "LOWER(LTRIM(RTRIM(ISNULL(d.nama_dept, '')))) NOT IN ('borongan', 'harian lepas', 'kpu', 'proyek')"
];
$params = [$dari, $sampai];

if ($dept !== '')   { $where[] = "d.id_dept = ?";       $params[] = (int)$dept; }
if ($shift !== '')  { $where[] = "a.shift_ke = ?";      $params[] = (int)$shift; }
if ($status !== '') { $where[] = "a.status = ?";        $params[] = $status; }
if ($status_kary !== '') {
  $where[] = "LOWER(LTRIM(RTRIM(ISNULL(p.status_karyawan, '')))) = LOWER(?)";
  $params[] = trim($status_kary);
}
if ($cari !== '')   { $where[] = "(p.nama_peg LIKE ? OR p.nik LIKE ?)"; $params[] = "%$cari%"; $params[] = "%$cari%"; }
$whereSql = implode(' AND ', $where);

/* ---------- ringkasan ---------- */
$sqlStat = "SELECT
      COUNT(*) AS total,
      SUM(CASE WHEN a.status='hadir'     THEN 1 ELSE 0 END) AS hadir,
      SUM(CASE WHEN a.status='terlambat' THEN 1 ELSE 0 END) AS terlambat,
      SUM(CASE WHEN a.perlu_koreksi=1    THEN 1 ELSE 0 END) AS koreksi
    FROM dbo.absensi a
    JOIN dbo.pegawai p    ON p.id_peg = a.pegawai_id
    LEFT JOIN dbo.unit_kerja u ON u.id = p.unit_kerja_id
    LEFT JOIN dbo.department d  ON d.id_dept = u.department_id
    WHERE $whereSql";
$st = sqlsrv_query($conn, $sqlStat, $params);
$stat = $st ? sqlsrv_fetch_array($st, SQLSRV_FETCH_ASSOC) : ['total'=>0,'hadir'=>0,'terlambat'=>0,'koreksi'=>0];

/* ---------- data detail (batasi 500 baris demi kecepatan) ---------- */
$sqlData = "SELECT TOP 500
      a.tanggal, a.shift_ke, a.jam_masuk, a.jam_keluar, a.status,
      a.metode, a.perlu_koreksi,
      p.nik, p.nama_peg, d.nama_dept, u.nama_unit
    FROM dbo.absensi a
    JOIN dbo.pegawai p    ON p.id_peg = a.pegawai_id
    LEFT JOIN dbo.unit_kerja u ON u.id = p.unit_kerja_id
    LEFT JOIN dbo.department d  ON d.id_dept = u.department_id
    WHERE $whereSql
    ORDER BY a.tanggal DESC, p.nama_peg";
$rows = sqlsrv_query($conn, $sqlData, $params);
if ($rows === false) die("<pre>Query gagal: ".print_r(sqlsrv_errors(),true)."</pre>");

function jam($v){ return $v instanceof DateTime ? $v->format('H:i') : '—'; }
function tgl($v){ return $v instanceof DateTime ? $v->format('d-m-Y') : '—'; }
function h($v){ return htmlspecialchars((string)($v ?? '')); }
?>

<main id="main" class="main">
  <div class="pagetitle">
    <h1>Rekap Absensi</h1>
    <nav><ol class="breadcrumb">
      <li class="breadcrumb-item"><a href="/hris/index.php">Home</a></li>
      <li class="breadcrumb-item active">Rekap Absensi</li>
    </ol></nav>
  </div>

  <section class="section">

    <!-- kartu ringkasan -->
    <div class="row">
      <div class="col-md-3 col-6">
        <div class="card info-card"><div class="card-body">
          <h6 class="text-muted" style="font-size:.8rem">Total Hari Kerja</h6>
          <h3 class="mb-0"><?= number_format($stat['total']) ?></h3>
        </div></div>
      </div>
      <div class="col-md-3 col-6">
        <div class="card info-card"><div class="card-body">
          <h6 class="text-muted" style="font-size:.8rem">Hadir</h6>
          <h3 class="mb-0 text-success"><?= number_format($stat['hadir']) ?></h3>
        </div></div>
      </div>
      <div class="col-md-3 col-6">
        <div class="card info-card"><div class="card-body">
          <h6 class="text-muted" style="font-size:.8rem">Terlambat</h6>
          <h3 class="mb-0 text-warning"><?= number_format($stat['terlambat']) ?></h3>
        </div></div>
      </div>
      <div class="col-md-3 col-6">
        <div class="card info-card"><div class="card-body">
          <h6 class="text-muted" style="font-size:.8rem">Perlu Koreksi</h6>
          <h3 class="mb-0 text-danger"><?= number_format($stat['koreksi']) ?></h3>
        </div></div>
      </div>
    </div>

    <div class="card">
      <div class="card-body pt-3">

        <!-- filter -->
        <form method="GET" class="row g-2 mb-3">
          <div class="col-md-2">
            <label class="form-label small">Dari</label>
            <input type="date" name="dari" class="form-control" value="<?= h($dari) ?>">
          </div>
          <div class="col-md-2">
            <label class="form-label small">Sampai</label>
            <input type="date" name="sampai" class="form-control" value="<?= h($sampai) ?>">
          </div>
          <div class="col-md-2">
            <label class="form-label small">Departemen</label>
            <select name="dept" class="form-select" <?= $is_restricted ? 'disabled' : '' ?>>
              <?php if (!$is_restricted): ?><option value="">Semua</option><?php endif; ?>
              <?php foreach ($deptList as $d): ?>
                <?php if ($is_restricted && $d['id_dept'] != $dept_user) continue; ?>
                <option value="<?= $d['id_dept'] ?>" <?= $dept==$d['id_dept']?'selected':'' ?>>
                  <?= h($d['nama_dept']) ?></option>
              <?php endforeach; ?>
            </select>
            <?php if ($is_restricted): ?><input type="hidden" name="dept" value="<?= h($dept) ?>"><?php endif; ?>
          </div>
          <div class="col-md-2">
            <label class="form-label small">Status Pegawai</label>
            <select name="status_kary" class="form-select">
              <option value="">Semua</option>
              <option value="staff" <?= $status_kary=='staff'?'selected':'' ?>>Staff</option>
              <option value="tetap" <?= $status_kary=='tetap'?'selected':'' ?>>Tetap</option>
              <option value="kontrak" <?= $status_kary=='kontrak'?'selected':'' ?>>Kontrak</option>
              <option value="harian" <?= $status_kary=='harian'?'selected':'' ?>>Harian</option>
              <option value="borongan" <?= $status_kary=='borongan'?'selected':'' ?>>Borongan</option>
            </select>
          </div>
          <div class="col-md-2">
            <label class="form-label small">Shift</label>
            <select name="shift" class="form-select">
              <option value="">Semua</option>
              <option value="1" <?= $shift=='1'?'selected':'' ?>>Shift 1</option>
              <option value="2" <?= $shift=='2'?'selected':'' ?>>Shift 2</option>
              <option value="3" <?= $shift=='3'?'selected':'' ?>>Shift 3</option>
            </select>
          </div>
          <div class="col-md-2">
            <label class="form-label small">Status</label>
            <select name="status" class="form-select">
              <option value="">Semua</option>
              <option value="hadir"     <?= $status=='hadir'?'selected':'' ?>>Hadir</option>
              <option value="terlambat" <?= $status=='terlambat'?'selected':'' ?>>Terlambat</option>
            </select>
          </div>
          <div class="col-md-2">
            <label class="form-label small">Cari Nama / NIK</label>
            <input type="text" name="cari" class="form-control" value="<?= h($cari) ?>">
          </div>
          <div class="col-12">
            <button class="btn btn-primary btn-sm"><i class="bi bi-funnel"></i> Terapkan</button>
            <a href="rekap_absensi.php" class="btn btn-outline-secondary btn-sm">Reset</a>
            <button type="button" onclick="exportTabel()" class="btn btn-success btn-sm float-end">
              <i class="bi bi-download"></i> Export CSV</button>
          </div>
        </form>

        <?php if ($stat['total'] > 500): ?>
          <div class="alert alert-info py-2">
            Menampilkan 500 baris teratas dari <?= number_format($stat['total']) ?>.
            Persempit filter untuk melihat sisanya.
          </div>
        <?php endif; ?>

        <div class="table-responsive">
        <table class="table table-hover table-sm" id="tabelRekap">
          <thead class="table-light">
            <tr>
              <th>Tanggal</th><th>NIK</th><th>Nama</th><th>Departemen</th>
              <th>Shift</th><th>Masuk</th><th>Pulang</th><th>Status</th><th>Metode</th>
            </tr>
          </thead>
          <tbody>
          <?php $ada=false; while ($r = sqlsrv_fetch_array($rows, SQLSRV_FETCH_ASSOC)): $ada=true; ?>
            <tr>
              <td><?= tgl($r['tanggal']) ?></td>
              <td><?= h($r['nik']) ?></td>
              <td><?= h($r['nama_peg']) ?></td>
              <td><?= h($r['nama_dept'] ?? '—') ?></td>
              <td class="text-center"><?= h($r['shift_ke'] ?? '—') ?></td>
              <td><?= jam($r['jam_masuk']) ?></td>
              <td><?= jam($r['jam_keluar']) ?></td>
              <td>
                <?php if ($r['perlu_koreksi']): ?>
                  <span class="badge bg-danger">perlu koreksi</span>
                <?php elseif ($r['status']=='terlambat'): ?>
                  <span class="badge bg-warning text-dark">terlambat</span>
                <?php else: ?>
                  <span class="badge bg-success">hadir</span>
                <?php endif; ?>
              </td>
              <td><?= h($r['metode'] ?? '—') ?></td>
            </tr>
          <?php endwhile; ?>
          <?php if (!$ada): ?>
            <tr><td colspan="9" class="text-center text-muted py-4">
              Tidak ada data untuk filter ini.</td></tr>
          <?php endif; ?>
          </tbody>
        </table>
        </div>

      </div>
    </div>
  </section>
</main>

<script>
function exportTabel(){
  const rows = [...document.querySelectorAll('#tabelRekap tr')];
  const csv = rows.map(tr =>
    [...tr.querySelectorAll('th,td')]
      .map(td => '"' + td.innerText.replace(/"/g,'""').trim() + '"').join(',')
  ).join('\n');
  const blob = new Blob(["\uFEFF"+csv], {type:'text/csv;charset=utf-8'});
  const a = document.createElement('a');
  a.href = URL.createObjectURL(blob);
  a.download = 'rekap_absensi_<?= $dari ?>_sd_<?= $sampai ?>.csv';
  a.click();
}
</script>

<?php
sqlsrv_free_stmt($rows);
include '../template/footer.php';
?>
