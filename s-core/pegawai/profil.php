<?php
/**
 * pegawai/profil.php
 * Profil Kehadiran Pegawai: kartu data diri + dashboard 6 kategori
 * (Tepat Waktu, Terlambat, Izin, Sakit, Cuti, Alfa).
 *
 * Akses:
 *  - peran dengan izin 'pegawai_lihat' -> semua pegawai
 *  - peran lain -> hanya profil dirinya sendiri (session pegawai_id)
 */
require_once __DIR__ . '/../auth/auth.php';
wajib_login();

$idSaya = (int)($_SESSION['score_user']['pegawai_id'] ?? 0);
$id     = (int)($_GET['id'] ?? 0) ?: $idSaya;

if (!$id) {
    if (boleh('pegawai_lihat')) { header('Location: kartu.php'); exit; }
    tampilkan_tolak();
}
if (!boleh('pegawai_lihat') && $id !== $idSaya) tampilkan_tolak();

$page_title = "Profil Pegawai";
include '../template/header.php';
include '../config/koneksi_sqlsrv.php';   // $conn
include '../template/sidebar.php';
require_once __DIR__ . '/_profil_helper.php';

/* ---------- mode toleransi (sama dengan Dashboard Presensi) ---------- */
$pakaiToleransi = in_array(peran_saya(), ['hr', 'admin_it'], true);

/* ---------- periode ---------- */
$hariIni = new DateTime('today');
$preset  = $_GET['p'] ?? 'bulan_ini';
switch ($preset) {
    case 'bulan_lalu':
        $dari   = (new DateTime('first day of last month'))->format('Y-m-d');
        $sampai = (new DateTime('last day of last month'))->format('Y-m-d');
        break;
    case '3bulan':
        $dari   = (new DateTime('first day of this month'))->modify('-2 months')->format('Y-m-d');
        $sampai = $hariIni->format('Y-m-d');
        break;
    case 'tahun_ini':
        $dari   = $hariIni->format('Y') . '-01-01';
        $sampai = $hariIni->format('Y-m-d');
        break;
    case 'custom':
        $dari   = pf_tanggal_valid($_GET['dari'] ?? '', $hariIni->format('Y-m-01'));
        $sampai = pf_tanggal_valid($_GET['sampai'] ?? '', $hariIni->format('Y-m-d'));
        if ($dari > $sampai) [$dari, $sampai] = [$sampai, $dari];
        // batasi maksimal 2 tahun agar tetap ringan
        $batas = (new DateTime($sampai))->modify('-730 days')->format('Y-m-d');
        if ($dari < $batas) $dari = $batas;
        break;
    default:
        $preset = 'bulan_ini';
        $dari   = $hariIni->format('Y-m-01');
        $sampai = $hariIni->format('Y-m-d');
}

// grafik 12 bulan berakhir di bulan "sampai"
$grafikAwal = (new DateTime($sampai))->modify('first day of this month')->modify('-11 months');
$ambilDari  = min($dari, $grafikAwal->format('Y-m-d'));

/* ---------- data pegawai ---------- */
$sqlPeg = "SELECT p.*, j.nama_jabatan, u.nama_unit, d.nama_dept,
                  ats.nama_peg AS nama_atasan, ats.id_peg AS id_atasan
           FROM dbo.pegawai p
           LEFT JOIN dbo.jabatan    j   ON j.id_jabatan = p.jabatan_id
           LEFT JOIN dbo.unit_kerja u   ON u.id         = p.unit_kerja_id
           LEFT JOIN dbo.department d   ON d.id_dept    = u.department_id
           LEFT JOIN dbo.pegawai    ats ON ats.id_peg   = p.atasan_id
           WHERE p.id_peg = ?";
$st = sqlsrv_query($conn, $sqlPeg, [$id]);
if ($st === false) die("<pre>Query gagal: " . print_r(sqlsrv_errors(), true) . "</pre>");
$p = sqlsrv_fetch_array($st, SQLSRV_FETCH_ASSOC);
if (!$p) {
    echo '<main id="main" class="main"><div class="alert alert-warning">Data pegawai tidak ditemukan. '
       . '<a href="kartu.php">Kembali ke Kartu Pegawai</a></div></main>';
    include '../template/footer.php';
    exit;
}

/* ---------- data absensi ---------- */
$sqlAbs = "SELECT a.tanggal, a.shift_ke, a.jam_masuk, a.jam_keluar, a.status, a.metode,
                  a.perlu_koreksi, a.keterangan, ps.jam_mulai, ps.nama_shift
           FROM dbo.absensi a
           LEFT JOIN dbo.pengaturan_shift ps ON ps.shift_ke = a.shift_ke
           WHERE a.pegawai_id = ? AND a.tanggal BETWEEN ? AND ?
           ORDER BY a.tanggal DESC";
$st = sqlsrv_query($conn, $sqlAbs, [$id, $ambilDari, $sampai]);
if ($st === false) {
    // cadangan bila kolom keterangan / tabel pengaturan_shift belum ada
    $st = sqlsrv_query($conn,
        "SELECT a.tanggal, a.shift_ke, a.jam_masuk, a.jam_keluar, a.status, a.metode, a.perlu_koreksi
         FROM dbo.absensi a WHERE a.pegawai_id = ? AND a.tanggal BETWEEN ? AND ?
         ORDER BY a.tanggal DESC", [$id, $ambilDari, $sampai]);
}
$semua = [];
if ($st) while ($r = sqlsrv_fetch_array($st, SQLSRV_FETCH_ASSOC)) {
    if (!$r['tanggal'] instanceof DateTimeInterface) continue;
    $r['tgl']  = $r['tanggal']->format('Y-m-d');
    $r['k']    = pf_kategori($r, $pakaiToleransi);
    $r['telat'] = $r['k'] === 'terlambat' ? pf_menit_telat($r) : 0;
    $semua[] = $r;
}

/* ---------- agregasi periode ---------- */
$periode = array_values(array_filter($semua, fn($r) => $r['tgl'] >= $dari && $r['tgl'] <= $sampai));
$jml     = pf_hitung($periode, $pakaiToleransi);
$total   = count($periode);
$hadir   = $jml['tepat'] + $jml['terlambat'];
$pctHadir = $total ? round($hadir / $total * 100) : 0;
$pctTepat = $hadir ? round($jml['tepat'] / $hadir * 100) : 0;

$telatMenit = array_filter(array_column($periode, 'telat'));
$rataTelat  = $telatMenit ? round(array_sum($telatMenit) / count($telatMenit)) : 0;
$koreksi    = count(array_filter($periode, fn($r) => !empty($r['perlu_koreksi'])));

$shiftHit = [];
foreach ($periode as $r) if (in_array($r['k'], ['tepat', 'terlambat'], true) && $r['shift_ke']) {
    $shiftHit[$r['shift_ke']] = ($shiftHit[$r['shift_ke']] ?? 0) + 1;
}
arsort($shiftHit);
$shiftDominan = $shiftHit ? 'Shift ' . array_key_first($shiftHit) : '—';

/* ---------- grafik 12 bulan ---------- */
$BLN = ['Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agu','Sep','Okt','Nov','Des'];
$bulanan = [];
$cur = clone $grafikAwal;
for ($i = 0; $i < 12; $i++) {
    $bulanan[$cur->format('Y-m')] = ['label' => $BLN[(int)$cur->format('n') - 1] . ' ' . $cur->format('y'),
        'tepat' => 0, 'terlambat' => 0, 'izin' => 0, 'sakit' => 0, 'cuti' => 0, 'alfa' => 0];
    $cur->modify('+1 month');
}
foreach ($semua as $r) {
    $kunci = substr($r['tgl'], 0, 7);
    if (isset($bulanan[$kunci])) $bulanan[$kunci][$r['k']]++;
}

/* ---------- data kalender ---------- */
$kalender = [];
foreach ($semua as $r) {
    $kalender[$r['tgl']] = [
        'k' => $r['k'], 'in' => pf_jam($r['jam_masuk']), 'out' => pf_jam($r['jam_keluar']),
        's' => $r['shift_ke'] ? (int)$r['shift_ke'] : null,
    ];
}

/* ---------- data diri turunan ---------- */
$aktif      = !empty($p['is_aktif']);
$lokasi     = ($p['work_location'] ?? '') ?: ($p['lokasi_kerja'] ?? '');
$umur       = $p['tgl_lahir'] instanceof DateTimeInterface ? $p['tgl_lahir']->diff(new DateTime())->y : null;
$masaKerja  = pf_masa_kerja($p['tgl_masuk'], $aktif ? null : $p['tgl_berhenti']);
$gender     = ['L' => 'Laki-laki', 'P' => 'Perempuan'][$p['gender'] ?? ''] ?? '—';

$sisaKontrak = null;
if (strtolower((string)$p['status_karyawan']) === 'kontrak' && $p['tgl_akhir_kontrak'] instanceof DateTimeInterface) {
    $sisaKontrak = (int)$hariIni->diff($p['tgl_akhir_kontrak'])->format('%r%a');
}

$hp = preg_replace('/\D/', '', (string)($p['no_hp_peg'] ?? ''));
$wa = $hp ? (substr($hp, 0, 1) === '0' ? '62' . substr($hp, 1) : $hp) : '';

$HARI = ['Min', 'Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab'];
$labelPeriode = pf_tgl(new DateTime($dari)) . ' – ' . pf_tgl(new DateTime($sampai));
$presetList = ['bulan_ini' => 'Bulan ini', 'bulan_lalu' => 'Bulan lalu', '3bulan' => '3 bulan', 'tahun_ini' => 'Tahun ini'];
?>
<link href="<?= BASE_URL ?>/s-core/assets/css/profil-pegawai.css?v=1" rel="stylesheet">

<main id="main" class="main">
  <div class="pagetitle">
    <h1>Profil Pegawai</h1>
    <nav>
      <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="/s-core/index.php">Home</a></li>
        <?php if (boleh('pegawai_lihat')): ?>
        <li class="breadcrumb-item"><a href="index.php">Pegawai</a></li>
        <li class="breadcrumb-item"><a href="kartu.php">Kartu Pegawai</a></li>
        <?php endif; ?>
        <li class="breadcrumb-item active"><?= pf_h($p['nama_peg']) ?></li>
      </ol>
    </nav>
  </div>

  <section class="section">
    <div class="row">

      <!-- ================= KARTU DATA DIRI ================= -->
      <div class="col-xl-4">
        <div class="card pf-hero">
          <div class="pf-cover">
            <span class="pf-state <?= $aktif ? '' : 'is-off' ?>"><?= $aktif ? 'Aktif' : 'Nonaktif' ?></span>
          </div>
          <?= pf_avatar_html($p['foto_peg'] ?? '', $p['nama_peg']) ?>

          <div class="card-body text-center pb-3">
            <h2 class="pf-name"><?= pf_h($p['nama_peg']) ?></h2>
            <div class="pf-role"><?= pf_h($p['nama_jabatan'] ?: 'Jabatan belum diisi') ?></div>
            <div class="d-flex justify-content-center flex-wrap gap-2">
              <?php if (!empty($p['nama_dept'])): ?>
                <span class="pf-chip"><i class="bi bi-diagram-3"></i><?= pf_h($p['nama_dept']) ?></span>
              <?php endif; ?>
              <?php if (!empty($p['status_karyawan'])): ?>
                <span class="badge bg-<?= pf_badge_status($p['status_karyawan']) ?> rounded-pill px-3 py-2">
                  <?= pf_h(ucfirst($p['status_karyawan'])) ?></span>
              <?php endif; ?>
            </div>

            <div class="pf-facts">
              <div><b><?= pf_h($masaKerja) ?></b><small>Masa kerja</small></div>
              <div><b><?= pf_h($p['nik'] ?: '—') ?></b><small>NIK</small></div>
              <div><b><?= pf_h($shiftDominan) ?></b><small>Shift utama</small></div>
            </div>

            <ul class="pf-info text-start">
              <li><i class="bi bi-building"></i><div><small>Unit kerja</small>
                <span><?= pf_h($p['nama_unit'] ?: '—') ?></span></div></li>
              <li><i class="bi bi-calendar-event"></i><div><small>Tanggal masuk</small>
                <span><?= pf_tgl($p['tgl_masuk']) ?></span></div></li>
              <?php if ($sisaKontrak !== null): ?>
              <li><i class="bi bi-file-earmark-text"></i><div><small>Akhir kontrak</small>
                <span><?= pf_tgl($p['tgl_akhir_kontrak']) ?>
                  <?php if ($sisaKontrak < 0): ?>
                    <span class="badge bg-danger ms-1">lewat <?= abs($sisaKontrak) ?> hari</span>
                  <?php elseif ($sisaKontrak <= 30): ?>
                    <span class="badge bg-warning text-dark ms-1">sisa <?= $sisaKontrak ?> hari</span>
                  <?php else: ?>
                    <span class="text-muted small ms-1">(sisa <?= $sisaKontrak ?> hari)</span>
                  <?php endif; ?>
                </span></div></li>
              <?php endif; ?>
              <?php if (!$aktif && $p['tgl_berhenti'] instanceof DateTimeInterface): ?>
              <li><i class="bi bi-box-arrow-right"></i><div><small>Tanggal berhenti</small>
                <span><?= pf_tgl($p['tgl_berhenti']) ?></span></div></li>
              <?php endif; ?>
              <li><i class="bi bi-person-up"></i><div><small>Atasan langsung</small>
                <span>
                  <?php if (!empty($p['id_atasan']) && boleh('pegawai_lihat')): ?>
                    <a href="profil.php?id=<?= (int)$p['id_atasan'] ?>"><?= pf_h($p['nama_atasan']) ?></a>
                  <?php else: ?><?= pf_h($p['nama_atasan'] ?: '—') ?><?php endif; ?>
                </span></div></li>
              <li><i class="bi bi-geo-alt"></i><div><small>Lokasi kerja</small>
                <span><?= pf_h($lokasi ?: '—') ?></span></div></li>
              <li><i class="bi bi-person-vcard"></i><div><small>Jenis kelamin, usia</small>
                <span><?= $gender ?><?= $umur !== null ? ', ' . $umur . ' tahun' : '' ?></span></div></li>
              <li><i class="bi bi-cake2"></i><div><small>Tempat, tanggal lahir</small>
                <span><?= pf_h($p['tempat_lahir'] ?: '—') ?>, <?= pf_tgl($p['tgl_lahir']) ?></span></div></li>
              <li><i class="bi bi-house"></i><div><small>Domisili</small>
                <span><?= pf_h(trim(($p['kota_dom'] ?? '') ?: ($p['kota'] ?? '')) ?: '—') ?></span></div></li>
              <li><i class="bi bi-telephone"></i><div><small>No. HP</small>
                <span>
                  <?php if ($hp): ?>
                    <a href="tel:<?= pf_h($hp) ?>"><?= pf_h($p['no_hp_peg']) ?></a>
                    <a href="https://wa.me/<?= pf_h($wa) ?>" target="_blank" rel="noopener" class="ms-2 text-success" title="Chat WhatsApp">
                      <i class="bi bi-whatsapp"></i></a>
                  <?php else: ?>—<?php endif; ?>
                </span></div></li>
              <li><i class="bi bi-envelope"></i><div><small>Email</small>
                <span><?= !empty($p['email_peg'])
                    ? '<a href="mailto:' . pf_h($p['email_peg']) . '">' . pf_h($p['email_peg']) . '</a>' : '—' ?></span></div></li>
            </ul>

            <div class="d-grid gap-2 mt-3">
              <?php if (boleh('pegawai_lihat')): ?>
                <a href="detail.php?id=<?= $id ?>" class="btn btn-primary btn-sm">
                  <i class="bi bi-person-lines-fill"></i> Lihat data lengkap</a>
              <?php endif; ?>
              <div class="d-flex gap-2">
                <?php if (boleh('pegawai_edit')): ?>
                  <a href="edit.php?id=<?= $id ?>" class="btn btn-outline-secondary btn-sm flex-fill">
                    <i class="bi bi-pencil-square"></i> Edit</a>
                <?php endif; ?>
                <?php if (boleh('absensi_rekap')): ?>
                  <a href="../absensi/rekap.php?cari=<?= urlencode((string)$p['nik']) ?>&dari=<?= $dari ?>&sampai=<?= $sampai ?>"
                     class="btn btn-outline-secondary btn-sm flex-fill"><i class="bi bi-table"></i> Rekap absensi</a>
                <?php endif; ?>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- ================= DASHBOARD KEHADIRAN ================= -->
      <div class="col-xl-8">

        <div class="card">
          <div class="card-body">
            <div class="d-flex flex-wrap justify-content-between align-items-center">
              <h5 class="card-title mb-0">Rekap Kehadiran <span>| <?= $labelPeriode ?></span></h5>
            </div>

            <form method="GET" class="pf-periode mb-2" id="formPeriode">
              <input type="hidden" name="id" value="<?= $id ?>">
              <?php foreach ($presetList as $k => $lbl): ?>
                <button name="p" value="<?= $k ?>" class="btn btn-sm <?= $preset === $k ? 'btn-primary' : 'btn-outline-primary' ?>"><?= $lbl ?></button>
              <?php endforeach; ?>
              <button type="button" class="btn btn-sm <?= $preset === 'custom' ? 'btn-primary' : 'btn-outline-primary' ?>"
                      data-bs-toggle="collapse" data-bs-target="#rentangCustom" aria-expanded="<?= $preset === 'custom' ? 'true' : 'false' ?>">
                <i class="bi bi-calendar-range"></i> Pilih tanggal</button>
              <div class="collapse w-100 <?= $preset === 'custom' ? 'show' : '' ?>" id="rentangCustom">
                <div class="d-flex flex-wrap gap-2 align-items-end pt-2">
                  <div><label class="form-label small mb-1" for="dari">Dari</label>
                    <input type="date" id="dari" name="dari" class="form-control form-control-sm" value="<?= $dari ?>"></div>
                  <div><label class="form-label small mb-1" for="sampai">Sampai</label>
                    <input type="date" id="sampai" name="sampai" class="form-control form-control-sm" value="<?= $sampai ?>"></div>
                  <button name="p" value="custom" class="btn btn-sm btn-primary">Tampilkan</button>
                </div>
              </div>
            </form>
            <div class="pf-mode mb-3"><i class="bi bi-info-circle"></i>
              <?= $pakaiToleransi
                  ? 'Terlambat dihitung dari status resmi HR (sudah termasuk toleransi shift).'
                  : 'Terlambat dihitung ketat: tap masuk lewat jam mulai shift.' ?>
              Klik kategori untuk menyaring riwayat.</div>

            <!-- 6 kategori -->
            <div class="row g-3">
              <?php foreach ($PF_KATEGORI as $k => $c): $pct = $total ? round($jml[$k] / $total * 100) : 0; ?>
              <div class="col-6 col-md-4">
                <button type="button" class="pf-tile" data-filter="<?= $k ?>" aria-pressed="false"
                        style="--c:<?= $c['warna'] ?>;--t:<?= $c['tint'] ?>">
                  <div class="pf-tile-top">
                    <span class="pf-tile-ico"><i class="bi <?= $c['icon'] ?>"></i></span>
                    <span class="pf-tile-pct"><?= $pct ?>%</span>
                  </div>
                  <div class="pf-tile-num"><?= number_format($jml[$k]) ?><small>hari</small></div>
                  <div class="pf-tile-label"><?= $c['label'] ?></div>
                  <div class="pf-tile-bar"><span style="width:<?= $pct ?>%"></span></div>
                </button>
              </div>
              <?php endforeach; ?>
            </div>

            <div class="pf-ringkas">
              <div><small>Hari tercatat</small><b><?= number_format($total) ?></b></div>
              <div><small>Tingkat kehadiran</small><b><?= $pctHadir ?>%</b></div>
              <div><small>Ketepatan waktu</small><b><?= $pctTepat ?>%</b></div>
              <div><small>Rata-rata telat</small><b><?= $rataTelat ?> <span class="small fw-normal text-muted">mnt</span></b></div>
              <div><small>Perlu koreksi</small><b class="<?= $koreksi ? 'is-warn' : '' ?>"><?= $koreksi ?></b></div>
            </div>
          </div>
        </div>

        <div class="row">
          <div class="col-lg-8">
            <div class="card pf-eq">
              <div class="card-body">
                <h5 class="card-title">Tren Bulanan <span>| 12 bulan</span></h5>
                <div style="height:260px"><canvas id="pfChartBulan" aria-label="Grafik kehadiran 12 bulan" role="img"></canvas></div>
              </div>
            </div>
          </div>
          <div class="col-lg-4">
            <div class="card pf-eq">
              <div class="card-body">
                <h5 class="card-title">Komposisi <span>| periode</span></h5>
                <?php if ($total): ?>
                <div class="pf-donut">
                  <canvas id="pfChartDonut" aria-label="Komposisi kategori" role="img"></canvas>
                  <div class="pf-donut-center"><b><?= $pctHadir ?>%</b><small>hadir</small></div>
                </div>
                <?php else: ?>
                  <div class="pf-empty"><i class="bi bi-pie-chart"></i>Belum ada data pada periode ini.</div>
                <?php endif; ?>
              </div>
            </div>
          </div>
        </div>

      </div>
    </div>

    <div class="row">
      <!-- ================= KALENDER ================= -->
      <div class="col-lg-5">
        <div class="card pf-eq">
          <div class="card-body">
            <h5 class="card-title">Kalender Kehadiran</h5>
            <div class="pf-cal-head">
              <button type="button" class="btn btn-outline-secondary" id="calPrev" aria-label="Bulan sebelumnya"><i class="bi bi-chevron-left"></i></button>
              <b id="calTitle" aria-live="polite"></b>
              <button type="button" class="btn btn-outline-secondary" id="calNext" aria-label="Bulan berikutnya"><i class="bi bi-chevron-right"></i></button>
            </div>
            <div class="pf-cal" id="calGrid"></div>
            <div class="pf-cal-legend">
              <?php foreach ($PF_KATEGORI as $c): ?>
                <span><i class="pf-dot" style="background:<?= $c['warna'] ?>"></i><?= $c['label'] ?></span>
              <?php endforeach; ?>
              <span><i class="pf-dot" style="background:#e9eef9"></i>Tidak ada catatan</span>
            </div>
          </div>
        </div>
      </div>

      <!-- ================= RIWAYAT ================= -->
      <div class="col-lg-7">
        <div class="card pf-eq">
          <div class="card-body">
            <div class="d-flex flex-wrap justify-content-between align-items-center">
              <h5 class="card-title">Riwayat Presensi <span id="pfFilterLabel">| semua kategori</span></h5>
              <div class="d-flex gap-2">
                <button type="button" class="btn btn-sm btn-outline-secondary d-none" id="pfReset">
                  <i class="bi bi-x-lg"></i> Hapus filter</button>
                <button type="button" class="btn btn-sm btn-success" id="pfExport" <?= $total ? '' : 'disabled' ?>>
                  <i class="bi bi-download"></i> Export CSV</button>
              </div>
            </div>

            <?php if (!$total): ?>
              <div class="pf-empty"><i class="bi bi-calendar-x"></i>
                Belum ada catatan presensi pada <?= $labelPeriode ?>.<br>Pilih periode lain di atas.</div>
            <?php else: ?>
            <div class="pf-table-wrap">
              <table class="table table-hover table-sm" id="pfTabel">
                <thead><tr>
                  <th>Tanggal</th><th>Shift</th><th>Masuk</th><th>Pulang</th><th>Kategori</th><th>Metode</th><th>Keterangan</th>
                </tr></thead>
                <tbody>
                <?php foreach ($periode as $r): $c = $PF_KATEGORI[$r['k']]; ?>
                  <tr data-k="<?= $r['k'] ?>">
                    <td><span class="text-muted"><?= $HARI[(int)$r['tanggal']->format('w')] ?>,</span> <?= pf_tgl($r['tanggal']) ?></td>
                    <td class="text-center"><?= $r['shift_ke'] ? (int)$r['shift_ke'] : '—' ?></td>
                    <td><?= pf_jam($r['jam_masuk']) ?></td>
                    <td><?= pf_jam($r['jam_keluar']) ?></td>
                    <td>
                      <span class="pf-badge" style="--c:<?= $c['warna'] ?>;--t:<?= $c['tint'] ?>">
                        <i class="bi <?= $c['icon'] ?>"></i><?= $c['label'] ?><?= $r['telat'] ? ' ' . $r['telat'] . 'm' : '' ?></span>
                      <?php if (!empty($r['perlu_koreksi'])): ?>
                        <i class="bi bi-exclamation-triangle-fill pf-flag ms-1" title="Perlu koreksi"></i>
                      <?php endif; ?>
                    </td>
                    <td><?= pf_h($r['metode'] ?? '—') ?></td>
                    <td class="pf-ket"><?= pf_h($r['keterangan'] ?? '') ?></td>
                  </tr>
                <?php endforeach; ?>
                </tbody>
              </table>
            </div>
            <div class="small text-muted mt-2" id="pfCount"><?= $total ?> catatan</div>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>
  </section>
</main>

<script>
(function () {
  const KAT      = <?= json_encode($PF_KATEGORI) ?>;
  const BULANAN  = <?= json_encode(array_values($bulanan)) ?>;
  const JML      = <?= json_encode($jml) ?>;
  const KALENDER = <?= json_encode($kalender) ?>;
  const CAL_MIN  = '<?= substr($ambilDari, 0, 7) ?>';
  const CAL_MAX  = '<?= substr($sampai, 0, 7) ?>';
  const TODAY    = '<?= $hariIni->format('Y-m-d') ?>';
  const NAMA     = <?= json_encode($p['nama_peg']) ?>;
  const BLN = ['Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];

  /* ---------- filter riwayat via kartu kategori ---------- */
  const tiles = document.querySelectorAll('.pf-tile');
  const rows  = document.querySelectorAll('#pfTabel tbody tr');
  const label = document.getElementById('pfFilterLabel');
  const reset = document.getElementById('pfReset');
  const count = document.getElementById('pfCount');
  let aktif = null;

  function terapkan(k) {
    aktif = (aktif === k) ? null : k;
    tiles.forEach(t => t.setAttribute('aria-pressed', t.dataset.filter === aktif ? 'true' : 'false'));
    let n = 0;
    rows.forEach(tr => { const tampil = !aktif || tr.dataset.k === aktif; tr.hidden = !tampil; if (tampil) n++; });
    label.textContent = '| ' + (aktif ? KAT[aktif].label : 'semua kategori');
    reset.classList.toggle('d-none', !aktif);
    if (count) count.textContent = n + ' catatan';
    const kartu = rows.length ? document.getElementById('pfTabel').closest('.card') : null;
    if (aktif && kartu && kartu.getBoundingClientRect().top > window.innerHeight * 0.6) {
      kartu.scrollIntoView({behavior: matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth', block: 'start'});
    }
  }
  tiles.forEach(t => t.addEventListener('click', () => terapkan(t.dataset.filter)));
  reset.addEventListener('click', () => terapkan(aktif));

  /* ---------- export CSV (baris yang tampil) ---------- */
  document.getElementById('pfExport').addEventListener('click', () => {
    const tr = [...document.querySelectorAll('#pfTabel tr')].filter(r => !r.hidden);
    const csv = tr.map(r => [...r.querySelectorAll('th,td')]
      .map(td => '"' + td.innerText.replace(/"/g, '""').trim() + '"').join(',')).join('\n');
    const a = document.createElement('a');
    a.href = URL.createObjectURL(new Blob(["\uFEFF" + csv], {type: 'text/csv;charset=utf-8'}));
    a.download = 'presensi_' + NAMA.replace(/\W+/g, '_') + '_<?= $dari ?>_sd_<?= $sampai ?>.csv';
    a.click();
  });

  /* ---------- kalender ---------- */
  let calYm = CAL_MAX;
  const grid = document.getElementById('calGrid');
  function renderCal() {
    const [y, m] = calYm.split('-').map(Number);
    document.getElementById('calTitle').textContent = BLN[m - 1] + ' ' + y;
    document.getElementById('calPrev').disabled = calYm <= CAL_MIN;
    document.getElementById('calNext').disabled = calYm >= CAL_MAX;
    let html = ['Sen','Sel','Rab','Kam','Jum','Sab','Min'].map(d => `<div class="pf-cal-dow">${d}</div>`).join('');
    const awal = (new Date(y, m - 1, 1).getDay() + 6) % 7;      // Senin = 0
    const hari = new Date(y, m, 0).getDate();
    for (let i = 0; i < awal; i++) html += '<div class="pf-cal-day is-out"></div>';
    for (let d = 1; d <= hari; d++) {
      const key = `${y}-${String(m).padStart(2,'0')}-${String(d).padStart(2,'0')}`;
      const x = KALENDER[key];
      const today = key === TODAY ? ' is-today' : '';
      if (x) {
        const c = KAT[x.k];
        const tip = `${d} ${BLN[m-1]}: ${c.label}` + (x.in !== '—' || x.out !== '—' ? ` (masuk ${x.in}, pulang ${x.out}${x.s ? ', shift ' + x.s : ''})` : '');
        html += `<div class="pf-cal-day has k-${x.k}${today}" style="--c:${c.warna}" title="${tip}" aria-label="${tip}">${d}${x.in !== '—' ? `<em>${x.in}</em>` : ''}</div>`;
      } else {
        html += `<div class="pf-cal-day${today}" aria-label="${d} ${BLN[m-1]}: tidak ada catatan">${d}</div>`;
      }
    }
    grid.innerHTML = html;
  }
  function geser(n) {
    const [y, m] = calYm.split('-').map(Number);
    const d = new Date(y, m - 1 + n, 1);
    calYm = d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0');
    renderCal();
  }
  document.getElementById('calPrev').addEventListener('click', () => geser(-1));
  document.getElementById('calNext').addEventListener('click', () => geser(1));
  renderCal();

  /* ---------- grafik (Chart.js dari footer; cadangan CDN) ---------- */
  function grafik() {
    Chart.defaults.font.family = '"Nunito", sans-serif';
    Chart.defaults.color = '#899bbd';
    const keys = Object.keys(KAT);
    new Chart(document.getElementById('pfChartBulan'), {
      type: 'bar',
      data: {
        labels: BULANAN.map(b => b.label),
        datasets: keys.map(k => ({ label: KAT[k].label, data: BULANAN.map(b => b[k]),
          backgroundColor: KAT[k].warna, borderRadius: 3, maxBarThickness: 26 }))
      },
      options: {
        maintainAspectRatio: false,
        plugins: { legend: { position: 'bottom', labels: { boxWidth: 10, boxHeight: 10, usePointStyle: true, pointStyle: 'rectRounded' } },
                   tooltip: { mode: 'index', intersect: false } },
        scales: { x: { stacked: true, grid: { display: false } },
                  y: { stacked: true, beginAtZero: true, ticks: { precision: 0 }, grid: { color: '#eef1fb' } } }
      }
    });
    const donut = document.getElementById('pfChartDonut');
    if (donut) new Chart(donut, {
      type: 'doughnut',
      data: { labels: keys.map(k => KAT[k].label),
              datasets: [{ data: keys.map(k => JML[k]), backgroundColor: keys.map(k => KAT[k].warna), borderWidth: 2, borderColor: '#fff' }] },
      options: { cutout: '72%', plugins: { legend: { display: false } } }
    });
  }
  window.addEventListener('load', function () {
    if (window.Chart) return grafik();
    const s = document.createElement('script');
    s.src = 'https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.0/chart.umd.min.js';
    s.onload = grafik;
    document.head.appendChild(s);
  });
})();
</script>

<?php include '../template/footer.php'; ?>
