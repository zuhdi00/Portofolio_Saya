<?php
/**
 * pegawai/kartu.php
 * Tampilan KARTU untuk Data Pegawai. Klik kartu -> profil.php (dashboard data diri + 6 kategori presensi).
 */
require_once __DIR__ . '/../auth/auth.php';
require_once __DIR__ . '/_profil_helper.php';
wajib_izin('pegawai_lihat');
$page_title = "Kartu Pegawai";
include __DIR__ . '/../template/header.php';
include __DIR__ . '/../config/koneksi_sqlsrv.php';   // $conn
include pf_path_sidebar();

$pakaiToleransi = in_array(peran_saya(), ['hr', 'admin_it'], true);

/* ---------- filter ---------- */
$cari   = trim($_GET['cari'] ?? '');
$dept   = $_GET['dept'] ?? '';
$status = $_GET['status'] ?? '';
$urut   = $_GET['urut'] ?? 'nama';
$bulan  = preg_match('/^\d{4}-\d{2}$/', $_GET['bulan'] ?? '') ? $_GET['bulan'] : date('Y-m');
$hal    = max(1, (int)($_GET['hal'] ?? 1));
$perHal = 24;

$bulanAwal  = $bulan . '-01';
$bulanAkhir = min(date('Y-m-t', strtotime($bulanAwal)), date('Y-m-d'));
if ($bulanAwal > date('Y-m-d')) $bulanAkhir = $bulanAwal;   // bulan depan -> kosong

/* ---------- dropdown departemen ---------- */
$deptList = [];
$rs = sqlsrv_query($conn, "SELECT id_dept, nama_dept FROM dbo.department ORDER BY nama_dept");
if ($rs) while ($r = sqlsrv_fetch_array($rs, SQLSRV_FETCH_ASSOC)) $deptList[] = $r;

/* ---------- WHERE ---------- */
$where  = ["p.is_aktif = 1"];
$params = [];
if ($cari !== '') {
    $where[] = "(p.nama_peg LIKE ? OR p.nik LIKE ? OR p.no_ktp LIKE ?)";
    array_push($params, "%$cari%", "%$cari%", "%$cari%");
}
if ($dept !== '')   { $where[] = "d.id_dept = ?";          $params[] = (int)$dept; }
if ($status !== '') { $where[] = "p.status_karyawan = ?";  $params[] = $status; }
$whereSql = implode(' AND ', $where);

$orderSql = [
    'nama'       => 'p.nama_peg ASC',
    'masuk_baru' => 'p.tgl_masuk DESC, p.nama_peg ASC',
    'masuk_lama' => 'p.tgl_masuk ASC, p.nama_peg ASC',
][$urut] ?? 'p.nama_peg ASC';

$FROM = "FROM dbo.pegawai p
         LEFT JOIN dbo.jabatan    j ON j.id_jabatan = p.jabatan_id
         LEFT JOIN dbo.unit_kerja u ON u.id         = p.unit_kerja_id
         LEFT JOIN dbo.department d ON d.id_dept    = u.department_id";

$st = sqlsrv_query($conn, "SELECT COUNT(*) AS n $FROM WHERE $whereSql", $params);
$totalPeg = $st ? (int)(sqlsrv_fetch_array($st, SQLSRV_FETCH_ASSOC)['n'] ?? 0) : 0;
$jmlHal   = max(1, (int)ceil($totalPeg / $perHal));
$hal      = min($hal, $jmlHal);
$offset   = ($hal - 1) * $perHal;

$sql = "SELECT p.id_peg, p.nik, p.nama_peg, p.foto_peg, p.status_karyawan, p.tgl_masuk,
               j.nama_jabatan, d.nama_dept
        $FROM WHERE $whereSql
        ORDER BY $orderSql
        OFFSET $offset ROWS FETCH NEXT $perHal ROWS ONLY";
$st = sqlsrv_query($conn, $sql, $params);
if ($st === false) die("<pre>Query gagal: " . print_r(sqlsrv_errors(), true) . "</pre>");
$pegawai = [];
while ($r = sqlsrv_fetch_array($st, SQLSRV_FETCH_ASSOC)) $pegawai[(int)$r['id_peg']] = $r;

/* ---------- ringkasan presensi bulan terpilih (hanya pegawai di halaman ini) ---------- */
$rekap = [];
if ($pegawai) {
    $ids = array_keys($pegawai);
    $ph  = implode(',', array_fill(0, count($ids), '?'));
    $st  = sqlsrv_query($conn,
        "SELECT a.pegawai_id, a.status, a.jam_masuk, a.shift_ke, ps.jam_mulai
         FROM dbo.absensi a
         LEFT JOIN dbo.pengaturan_shift ps ON ps.shift_ke = a.shift_ke
         WHERE a.pegawai_id IN ($ph) AND a.tanggal BETWEEN ? AND ?",
        array_merge($ids, [$bulanAwal, $bulanAkhir]));
    $perPeg = [];
    if ($st) while ($r = sqlsrv_fetch_array($st, SQLSRV_FETCH_ASSOC)) $perPeg[(int)$r['pegawai_id']][] = $r;
    foreach ($ids as $pid) $rekap[$pid] = pf_hitung($perPeg[$pid] ?? [], $pakaiToleransi);
}

$BLN = ['Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
$labelBulan = $BLN[(int)substr($bulan, 5, 2) - 1] . ' ' . substr($bulan, 0, 4);

/** url dengan query saat ini + perubahan */
function pf_url(array $ubah) {
    $q = array_merge($_GET, $ubah);
    return 'kartu.php?' . http_build_query(array_filter($q, fn($v) => $v !== '' && $v !== null));
}
?>
<link href="<?= BASE_URL ?>/s-core/assets/css/profil-pegawai.css?v=1" rel="stylesheet">

<main id="main" class="main">
  <div class="pagetitle">
    <h1>Kartu Pegawai</h1>
    <nav>
      <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="/s-core/index.php">Home</a></li>
        <li class="breadcrumb-item"><a href="index.php">Pegawai</a></li>
        <li class="breadcrumb-item active">Kartu Pegawai</li>
      </ol>
    </nav>
  </div>

  <section class="section">
    <div class="card">
      <div class="card-body pt-3">
        <form method="GET" class="row g-2 align-items-end pf-toolbar">
          <div class="col-xl-4 col-md-6">
            <label class="form-label small mb-1" for="cari">Cari pegawai</label>
            <div class="input-group">
              <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
              <input type="text" id="cari" name="cari" class="form-control" placeholder="Nama, NIK, atau no. KTP"
                     value="<?= pf_h($cari) ?>">
            </div>
          </div>
          <div class="col-xl-3 col-md-6">
            <label class="form-label small mb-1" for="dept">Departemen</label>
            <select id="dept" name="dept" class="form-select">
              <option value="">Semua departemen</option>
              <?php foreach ($deptList as $d): ?>
                <option value="<?= (int)$d['id_dept'] ?>" <?= (string)$dept === (string)$d['id_dept'] ? 'selected' : '' ?>><?= pf_h($d['nama_dept']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-xl-2 col-md-4">
            <label class="form-label small mb-1" for="status">Status</label>
            <select id="status" name="status" class="form-select">
              <option value="">Semua status</option>
              <?php foreach (['tetap', 'kontrak', 'harian'] as $s): ?>
                <option value="<?= $s ?>" <?= $status === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-xl-3 col-md-4">
            <label class="form-label small mb-1" for="bulan">Presensi bulan</label>
            <input type="month" id="bulan" name="bulan" class="form-control" value="<?= pf_h($bulan) ?>" max="<?= date('Y-m') ?>">
          </div>
          <div class="col-xl-3 col-md-4">
            <label class="form-label small mb-1" for="urut">Urutkan</label>
            <select id="urut" name="urut" class="form-select">
              <option value="nama" <?= $urut === 'nama' ? 'selected' : '' ?>>Nama</option>
              <option value="masuk_baru" <?= $urut === 'masuk_baru' ? 'selected' : '' ?>>Masuk terbaru</option>
              <option value="masuk_lama" <?= $urut === 'masuk_lama' ? 'selected' : '' ?>>Masuk terlama</option>
            </select>
          </div>
          <div class="col-xl-3 col-md-6 d-flex gap-2">
            <button class="btn btn-primary flex-fill"><i class="bi bi-funnel"></i> Terapkan</button>
            <a href="kartu.php" class="btn btn-outline-secondary" title="Reset filter"><i class="bi bi-arrow-counterclockwise"></i></a>
          </div>
        </form>

        <div class="d-flex flex-wrap justify-content-between align-items-center mt-3 gap-2">
          <div class="small text-muted">
            <?php if ($totalPeg): ?>
              Menampilkan <?= $offset + 1 ?>–<?= $offset + count($pegawai) ?> dari <?= number_format($totalPeg) ?> pegawai aktif.
              Ringkasan presensi: <?= $labelBulan ?>.
            <?php else: ?>Tidak ada pegawai yang cocok.<?php endif; ?>
          </div>
          <div class="d-flex gap-2">
            <a href="index.php" class="btn btn-sm btn-outline-secondary"><i class="bi bi-table"></i> Tampilan tabel</a>
            <?php if (boleh('pegawai_edit')): ?>
            <a href="tambah_pegawai_lengkap.php" class="btn btn-sm btn-primary"><i class="bi bi-plus-circle"></i> Tambah pegawai</a>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>

    <?php if (!$pegawai): ?>
      <div class="card"><div class="card-body pf-empty">
        <i class="bi bi-person-x"></i>
        Tidak ada pegawai untuk filter ini. <a href="kartu.php">Reset filter</a> untuk melihat semua pegawai.
      </div></div>
    <?php else: ?>
    <div class="row g-4">
      <?php foreach ($pegawai as $pid => $r):
        $n = $rekap[$pid]; $tot = array_sum($n); ?>
      <div class="col-12 col-sm-6 col-lg-4 col-xxl-3">
        <a href="profil.php?id=<?= $pid ?>" class="card pf-card" aria-label="Buka profil <?= pf_h($r['nama_peg']) ?>">
          <div class="pf-cover">
            <?php if (!empty($r['nik'])): ?><span class="pf-nik">NIK <?= pf_h($r['nik']) ?></span><?php endif; ?>
            <?php if (!empty($r['status_karyawan'])): ?>
              <span class="badge rounded-pill bg-<?= pf_badge_status($r['status_karyawan']) ?>"><?= pf_h(ucfirst($r['status_karyawan'])) ?></span>
            <?php endif; ?>
          </div>
          <?= pf_avatar_html($r['foto_peg'] ?? '', $r['nama_peg'], 'pf-avatar-sm') ?>
          <div class="pf-card-body">
            <div class="pf-card-name"><?= pf_h($r['nama_peg']) ?></div>
            <div class="pf-card-role"><?= pf_h($r['nama_jabatan'] ?: '—') ?></div>
            <div class="pf-card-meta">
              <span class="pf-chip"><i class="bi bi-diagram-3"></i><?= pf_h($r['nama_dept'] ?: 'Tanpa departemen') ?></span>
            </div>

            <div class="pf-mini">
              <div class="pf-mini-bar" role="img" aria-label="Sebaran presensi <?= $labelBulan ?>">
                <?php if ($tot) foreach ($PF_KATEGORI as $k => $c): if (!$n[$k]) continue; ?>
                  <span style="width:<?= round($n[$k] / $tot * 100, 2) ?>%;background:<?= $c['warna'] ?>"></span>
                <?php endforeach; ?>
              </div>
              <div class="pf-mini-grid">
                <?php foreach ($PF_KATEGORI as $k => $c): ?>
                  <div class="<?= $n[$k] ? '' : 'is-zero' ?>" title="<?= $c['label'] ?>: <?= $n[$k] ?>">
                    <i class="bi <?= $c['icon'] ?>" style="color:<?= $n[$k] ? $c['warna'] : '#c5cde3' ?>"></i>
                    <b><?= $n[$k] ?></b>
                  </div>
                <?php endforeach; ?>
              </div>
              <div class="pf-mini-cap"><?= $tot ? $tot . ' hari tercatat' : 'Belum ada catatan bulan ini' ?></div>
            </div>
          </div>
        </a>
      </div>
      <?php endforeach; ?>
    </div>

    <!-- legenda ikon -->
    <div class="d-flex flex-wrap justify-content-center gap-3 small text-muted mt-4">
      <?php foreach ($PF_KATEGORI as $c): ?>
        <span><i class="bi <?= $c['icon'] ?>" style="color:<?= $c['warna'] ?>"></i> <?= $c['label'] ?></span>
      <?php endforeach; ?>
    </div>

    <?php if ($jmlHal > 1): ?>
    <nav class="mt-4" aria-label="Halaman kartu pegawai">
      <ul class="pagination pagination-sm justify-content-center flex-wrap">
        <li class="page-item <?= $hal <= 1 ? 'disabled' : '' ?>">
          <a class="page-link" href="<?= pf_h(pf_url(['hal' => $hal - 1])) ?>" aria-label="Sebelumnya"><i class="bi bi-chevron-left"></i></a></li>
        <?php
        $mulai = max(1, $hal - 2); $akhir = min($jmlHal, $hal + 2);
        if ($mulai > 1) echo '<li class="page-item"><a class="page-link" href="' . pf_h(pf_url(['hal' => 1])) . '">1</a></li>'
                           . ($mulai > 2 ? '<li class="page-item disabled"><span class="page-link">…</span></li>' : '');
        for ($i = $mulai; $i <= $akhir; $i++):
        ?>
          <li class="page-item <?= $i === $hal ? 'active' : '' ?>"><a class="page-link" href="<?= pf_h(pf_url(['hal' => $i])) ?>"><?= $i ?></a></li>
        <?php endfor;
        if ($akhir < $jmlHal) echo ($akhir < $jmlHal - 1 ? '<li class="page-item disabled"><span class="page-link">…</span></li>' : '')
                               . '<li class="page-item"><a class="page-link" href="' . pf_h(pf_url(['hal' => $jmlHal])) . '">' . $jmlHal . '</a></li>';
        ?>
        <li class="page-item <?= $hal >= $jmlHal ? 'disabled' : '' ?>">
          <a class="page-link" href="<?= pf_h(pf_url(['hal' => $hal + 1])) ?>" aria-label="Berikutnya"><i class="bi bi-chevron-right"></i></a></li>
      </ul>
    </nav>
    <?php endif; ?>
    <?php endif; ?>
  </section>
</main>

<?php include __DIR__ . '/../template/footer.php'; ?>
