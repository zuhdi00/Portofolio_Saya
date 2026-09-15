<?php
require_once __DIR__ . '/auth/auth.php';
wajib_izin('dashboard');
include 'template/header.php';
include 'config/koneksi_sqlsrv.php';   // $conn  (di root, tanpa ../)
include 'template/sidebar.php';

$hari_ini = date('Y-m-d');

/** ambil 1 baris hasil query */
function satu($conn, $sql, $params = []) {
    $st = sqlsrv_query($conn, $sql, $params);
    return $st ? sqlsrv_fetch_array($st, SQLSRV_FETCH_ASSOC) : null;
}

/* ---------- kartu hari ini ---------- */
$hariIni = satu($conn,
    "SELECT
        COUNT(*) AS total,
        SUM(CASE WHEN status='hadir'     THEN 1 ELSE 0 END) AS hadir,
        SUM(CASE WHEN status='terlambat' THEN 1 ELSE 0 END) AS terlambat,
        SUM(CASE WHEN perlu_koreksi=1    THEN 1 ELSE 0 END) AS koreksi
     FROM dbo.absensi WHERE tanggal = ?", [$hari_ini]);

$totalPeg = satu($conn, "SELECT COUNT(*) AS n FROM dbo.pegawai WHERE is_aktif=1")['n'] ?? 0;
$hadirIni = (int)($hariIni['total'] ?? 0);
$belumHadir = max(0, $totalPeg - $hadirIni);

/* ---------- antrian approval ---------- */
$pending = satu($conn,
    "SELECT COUNT(*) AS n FROM dbo.absensi_koreksi WHERE status_approval='PENDING'")['n'] ?? 0;

/* ---------- tren 14 hari ---------- */
$tren = [];
$st = sqlsrv_query($conn,
    "SELECT tanggal,
            SUM(CASE WHEN status='hadir'     THEN 1 ELSE 0 END) AS hadir,
            SUM(CASE WHEN status='terlambat' THEN 1 ELSE 0 END) AS terlambat
     FROM dbo.absensi
     WHERE tanggal >= DATEADD(day,-13,CAST(GETDATE() AS DATE))
     GROUP BY tanggal ORDER BY tanggal");
while ($r = sqlsrv_fetch_array($st, SQLSRV_FETCH_ASSOC)) {
    $tren[] = [
        'tgl' => $r['tanggal'] instanceof DateTime ? $r['tanggal']->format('d/m') : '',
        'hadir' => (int)$r['hadir'], 'terlambat' => (int)$r['terlambat'],
    ];
}

/* ---------- sebaran per shift (30 hari) ---------- */
$shiftData = [];
$st = sqlsrv_query($conn,
    "SELECT shift_ke,
            SUM(CASE WHEN status='hadir'     THEN 1 ELSE 0 END) AS hadir,
            SUM(CASE WHEN status='terlambat' THEN 1 ELSE 0 END) AS terlambat
     FROM dbo.absensi
     WHERE tanggal >= DATEADD(day,-30,CAST(GETDATE() AS DATE)) AND shift_ke IS NOT NULL
     GROUP BY shift_ke ORDER BY shift_ke");
while ($r = sqlsrv_fetch_array($st, SQLSRV_FETCH_ASSOC)) {
    $shiftData[] = ['shift'=>(int)$r['shift_ke'],'hadir'=>(int)$r['hadir'],'terlambat'=>(int)$r['terlambat']];
}

/* ---------- 10 departemen paling sering terlambat (30 hari) ---------- */
$deptTelat = [];
$st = sqlsrv_query($conn,
    "SELECT TOP 10 d.nama_dept,
            SUM(CASE WHEN a.status='terlambat' THEN 1 ELSE 0 END) AS terlambat,
            COUNT(*) AS total
     FROM dbo.absensi a
     JOIN dbo.pegawai p    ON p.id_peg = a.pegawai_id
     LEFT JOIN dbo.unit_kerja u ON u.id = p.unit_kerja_id
     LEFT JOIN dbo.department d  ON d.id_dept = u.department_id
     WHERE a.tanggal >= DATEADD(day,-30,CAST(GETDATE() AS DATE)) AND d.nama_dept IS NOT NULL
     GROUP BY d.nama_dept
     HAVING SUM(CASE WHEN a.status='terlambat' THEN 1 ELSE 0 END) > 0
     ORDER BY terlambat DESC");
while ($r = sqlsrv_fetch_array($st, SQLSRV_FETCH_ASSOC)) {
    $deptTelat[] = ['dept'=>$r['nama_dept'],'terlambat'=>(int)$r['terlambat'],'total'=>(int)$r['total']];
}

function h($v){ return htmlspecialchars((string)($v ?? '')); }
?>

<main id="main" class="main">
  <div class="pagetitle">
    <h1>Dashboard Presensi</h1>
    <nav><ol class="breadcrumb">
      <li class="breadcrumb-item">Home</li>
      <li class="breadcrumb-item active">Dashboard</li>
    </ol></nav>
  </div>

  <section class="section dashboard">
    <div class="row">

      <!-- ============ KARTU RINGKASAN HARI INI ============ -->
      <div class="col-lg-8">
        <div class="row">

          <div class="col-md-3 col-6">
            <div class="card info-card"><div class="card-body">
              <h5 class="card-title" style="font-size:.85rem">Hadir <span>| Hari ini</span></h5>
              <div class="d-flex align-items-center">
                <div class="card-icon rounded-circle d-flex align-items-center justify-content-center"
                     style="background:#e0f8e9;color:#2eca6a"><i class="bi bi-check-circle"></i></div>
                <div class="ps-3"><h6><?= number_format($hadirIni) ?></h6>
                  <span class="text-muted small pt-2">dari <?= number_format($totalPeg) ?> aktif</span></div>
              </div>
            </div></div>
          </div>

          <div class="col-md-3 col-6">
            <div class="card info-card"><div class="card-body">
              <h5 class="card-title" style="font-size:.85rem">Terlambat <span>| Hari ini</span></h5>
              <div class="d-flex align-items-center">
                <div class="card-icon rounded-circle d-flex align-items-center justify-content-center"
                     style="background:#fff3e0;color:#ff9800"><i class="bi bi-clock-history"></i></div>
                <div class="ps-3"><h6><?= number_format($hariIni['terlambat'] ?? 0) ?></h6></div>
              </div>
            </div></div>
          </div>

          <div class="col-md-3 col-6">
            <div class="card info-card"><div class="card-body">
              <h5 class="card-title" style="font-size:.85rem">Belum Tap <span>| Hari ini</span></h5>
              <div class="d-flex align-items-center">
                <div class="card-icon rounded-circle d-flex align-items-center justify-content-center"
                     style="background:#eceff1;color:#78909c"><i class="bi bi-dash-circle"></i></div>
                <div class="ps-3"><h6><?= number_format($belumHadir) ?></h6></div>
              </div>
            </div></div>
          </div>

          <div class="col-md-3 col-6">
            <div class="card info-card"><div class="card-body">
              <h5 class="card-title" style="font-size:.85rem">Perlu Approval</h5>
              <div class="d-flex align-items-center">
                <div class="card-icon rounded-circle d-flex align-items-center justify-content-center"
                     style="background:#fde0e0;color:#e74c3c"><i class="bi bi-exclamation-triangle"></i></div>
                <div class="ps-3"><h6><?= number_format($pending) ?></h6>
                  <?php if ($pending > 0): ?>
                    <a href="presensi/approval_koreksi.php" class="small">tinjau</a>
                  <?php endif; ?></div>
              </div>
            </div></div>
          </div>

          <!-- ============ TREN 14 HARI ============ -->
          <div class="col-12">
            <div class="card"><div class="card-body">
              <h5 class="card-title">Tren Kehadiran <span>| 14 hari terakhir</span></h5>
              <canvas id="chartTren" height="100"></canvas>
            </div></div>
          </div>

          <!-- ============ DEPARTEMEN TERLAMBAT ============ -->
          <div class="col-12">
            <div class="card"><div class="card-body">
              <h5 class="card-title">Keterlambatan per Departemen <span>| 30 hari</span></h5>
              <div class="table-responsive">
              <table class="table table-sm">
                <thead><tr><th>Departemen</th><th>Terlambat</th><th>Total</th><th style="width:35%">Rasio</th></tr></thead>
                <tbody>
                <?php foreach ($deptTelat as $d):
                    $pct = $d['total'] ? round($d['terlambat']/$d['total']*100) : 0; ?>
                  <tr>
                    <td><?= h($d['dept']) ?></td>
                    <td><?= number_format($d['terlambat']) ?></td>
                    <td><?= number_format($d['total']) ?></td>
                    <td>
                      <div class="progress" style="height:16px">
                        <div class="progress-bar <?= $pct>=25?'bg-danger':($pct>=15?'bg-warning':'bg-success') ?>"
                             style="width:<?= $pct ?>%"><?= $pct ?>%</div>
                      </div>
                    </td>
                  </tr>
                <?php endforeach; ?>
                <?php if (!$deptTelat): ?>
                  <tr><td colspan="4" class="text-center text-muted">Belum ada data.</td></tr>
                <?php endif; ?>
                </tbody>
              </table>
              </div>
            </div></div>
          </div>

        </div>
      </div>

      <!-- ============ SEBARAN SHIFT + AKSI CEPAT ============ -->
      <div class="col-lg-4">
        <div class="card"><div class="card-body">
          <h5 class="card-title">Kehadiran per Shift <span>| 30 hari</span></h5>
          <canvas id="chartShift" height="200"></canvas>
        </div></div>

        <div class="card"><div class="card-body">
          <h5 class="card-title">Akses Cepat</h5>
          <div class="d-grid gap-2">
            <a href="presensi/rekap_absensi.php" class="btn btn-outline-primary btn-sm">
              <i class="bi bi-table"></i> Rekap Absensi</a>
            <a href="presensi/approval_koreksi.php" class="btn btn-outline-danger btn-sm">
              <i class="bi bi-check2-square"></i> Approval Koreksi (<?= $pending ?>)</a>
            <a href="pegawai/index.php" class="btn btn-outline-secondary btn-sm">
              <i class="bi bi-people"></i> Data Pegawai</a>
          </div>
        </div></div>
      </div>

    </div>
  </section>
</main>

<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.0/chart.umd.min.js"></script>
<script>
const tren = <?= json_encode($tren) ?>;
const shiftData = <?= json_encode($shiftData) ?>;

new Chart(document.getElementById('chartTren'), {
  type:'line',
  data:{
    labels: tren.map(t=>t.tgl),
    datasets:[
      {label:'Hadir', data:tren.map(t=>t.hadir), borderColor:'#2eca6a',
       backgroundColor:'rgba(46,202,106,.1)', fill:true, tension:.3},
      {label:'Terlambat', data:tren.map(t=>t.terlambat), borderColor:'#ff9800',
       backgroundColor:'rgba(255,152,0,.1)', fill:true, tension:.3},
    ]
  },
  options:{plugins:{legend:{position:'top'}}, scales:{y:{beginAtZero:true}}}
});

new Chart(document.getElementById('chartShift'), {
  type:'bar',
  data:{
    labels: shiftData.map(s=>'Shift '+s.shift),
    datasets:[
      {label:'Hadir', data:shiftData.map(s=>s.hadir), backgroundColor:'#4154f1'},
      {label:'Terlambat', data:shiftData.map(s=>s.terlambat), backgroundColor:'#ff9800'},
    ]
  },
  options:{plugins:{legend:{position:'top'}},
           scales:{x:{stacked:true},y:{stacked:true,beginAtZero:true}}}
});
</script>

<?php include 'template/footer.php'; ?>
