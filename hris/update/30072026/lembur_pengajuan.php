<?php
/**
 * presensi/lembur_pengajuan.php
 * Form pengajuan lembur + daftar pengajuan. Jam diisi manual.
 * Alur: DIAJUKAN -> approval atasan -> approval HR.
 */
$page_title = "Pengajuan Lembur";
include '../template/header.php';
include '../config/koneksi_sqlsrv.php';
include '../template/sidebar.php';

/* ---------- proses submit ---------- */
$pesan = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['aksi'] ?? '') === 'ajukan') {
    $peg    = (int)($_POST['pegawai_id'] ?? 0);
    $tgl    = $_POST['tanggal'] ?? '';
    $mulai  = $_POST['jam_mulai'] ?? '';
    $selesai= $_POST['jam_selesai'] ?? '';
    $jenis  = $_POST['jenis'] ?? 'biasa';
    $alasan = trim($_POST['alasan'] ?? '');

    if (!$peg || !$tgl || !$mulai || !$selesai || $alasan==='') {
        $pesan = "<div class='alert alert-danger'>Semua field wajib diisi.</div>";
    } else {
        // hitung durasi (jam), tangani lintas tengah malam
        $m = strtotime("2000-01-01 $mulai");
        $s = strtotime("2000-01-01 $selesai");
        if ($s <= $m) $s += 86400;
        $durasi = round(($s - $m)/3600, 2);

        $sql = "INSERT INTO dbo.lembur
                (pegawai_id, tanggal, jam_mulai, jam_selesai, durasi_jam, jenis, alasan, diajukan_oleh)
                VALUES (?,?,?,?,?,?,?,?)";
        $st = sqlsrv_query($conn, $sql, [$peg,$tgl,$mulai,$selesai,$durasi,$jenis,$alasan,$peg]);
        if ($st === false) {
            $pesan = "<div class='alert alert-danger'>Gagal: ".print_r(sqlsrv_errors(),true)."</div>";
        } else {
            $pesan = "<div class='alert alert-success'>Pengajuan lembur ".number_format($durasi,1)." jam berhasil dikirim. Menunggu approval atasan.</div>";
        }
    }
}

/* ---------- dropdown pegawai ---------- */
$pegList = [];
$rs = sqlsrv_query($conn, "SELECT id_peg, nik, nama_peg FROM dbo.pegawai WHERE is_aktif=1 ORDER BY nama_peg");
while ($r = sqlsrv_fetch_array($rs, SQLSRV_FETCH_ASSOC)) $pegList[] = $r;

/* ---------- daftar pengajuan terbaru ---------- */
$rows = sqlsrv_query($conn,
    "SELECT TOP 100 l.*, p.nama_peg, p.nik
     FROM dbo.lembur l JOIN dbo.pegawai p ON p.id_peg = l.pegawai_id
     ORDER BY l.diajukan_pada DESC");

function jam($v){ return $v instanceof DateTime ? $v->format('H:i') : '—'; }
function tgl($v){ return $v instanceof DateTime ? $v->format('d-m-Y') : '—'; }
function h($v){ return htmlspecialchars((string)($v ?? '')); }
function badgeStatus($s){
    $map = [
        'DIAJUKAN'          => ['warning','Menunggu Atasan'],
        'DISETUJUI_ATASAN'  => ['info','Menunggu HR'],
        'DITOLAK_ATASAN'    => ['danger','Ditolak Atasan'],
        'DISETUJUI_HR'      => ['success','Disetujui'],
        'DITOLAK_HR'        => ['danger','Ditolak HR'],
    ];
    [$w,$t] = $map[$s] ?? ['secondary',$s];
    return "<span class='badge bg-$w'>$t</span>";
}
?>

<main id="main" class="main">
  <div class="pagetitle"><h1>Pengajuan Lembur</h1>
    <nav><ol class="breadcrumb">
      <li class="breadcrumb-item"><a href="/hris/index.php">Home</a></li>
      <li class="breadcrumb-item active">Lembur</li>
    </ol></nav>
  </div>

  <section class="section">
    <?= $pesan ?>
    <div class="row">

      <!-- FORM -->
      <div class="col-lg-4">
        <div class="card"><div class="card-body">
          <h5 class="card-title">Ajukan Lembur</h5>
          <form method="POST">
            <input type="hidden" name="aksi" value="ajukan">
            <div class="mb-3">
              <label class="form-label">Pegawai</label>
              <select name="pegawai_id" class="form-select" required>
                <option value="">-- pilih --</option>
                <?php foreach ($pegList as $p): ?>
                  <option value="<?= $p['id_peg'] ?>"><?= h($p['nik']) ?> — <?= h($p['nama_peg']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="mb-3">
              <label class="form-label">Tanggal Lembur</label>
              <input type="date" name="tanggal" class="form-control" value="<?= date('Y-m-d') ?>" required>
            </div>
            <div class="row">
              <div class="col-6 mb-3">
                <label class="form-label">Jam Mulai</label>
                <input type="time" name="jam_mulai" class="form-control" required>
              </div>
              <div class="col-6 mb-3">
                <label class="form-label">Jam Selesai</label>
                <input type="time" name="jam_selesai" class="form-control" required>
              </div>
            </div>
            <div class="mb-3">
              <label class="form-label">Jenis Lembur</label>
              <select name="jenis" class="form-select">
                <option value="biasa">Hari Kerja Biasa</option>
                <option value="libur">Hari Libur / Weekend</option>
                <option value="hari_besar">Hari Besar / Nasional</option>
              </select>
            </div>
            <div class="mb-3">
              <label class="form-label">Alasan / Uraian Pekerjaan</label>
              <textarea name="alasan" class="form-control" rows="3" required
                        placeholder="Jelaskan pekerjaan yang dikerjakan saat lembur"></textarea>
            </div>
            <button class="btn btn-primary w-100"><i class="bi bi-send"></i> Ajukan</button>
          </form>
        </div></div>
      </div>

      <!-- DAFTAR -->
      <div class="col-lg-8">
        <div class="card"><div class="card-body">
          <h5 class="card-title">Pengajuan Terbaru</h5>
          <div class="table-responsive">
          <table class="table table-hover table-sm">
            <thead class="table-light"><tr>
              <th>Tanggal</th><th>Nama</th><th>Jam</th><th>Durasi</th><th>Jenis</th><th>Status</th>
            </tr></thead>
            <tbody>
            <?php $ada=false; while ($r = sqlsrv_fetch_array($rows, SQLSRV_FETCH_ASSOC)): $ada=true; ?>
              <tr>
                <td><?= tgl($r['tanggal']) ?></td>
                <td><?= h($r['nama_peg']) ?></td>
                <td><?= jam($r['jam_mulai']) ?>–<?= jam($r['jam_selesai']) ?></td>
                <td><?= number_format($r['durasi_jam'],1) ?> jam</td>
                <td><?= h($r['jenis']) ?></td>
                <td><?= badgeStatus($r['status']) ?></td>
              </tr>
            <?php endwhile; ?>
            <?php if(!$ada): ?>
              <tr><td colspan="6" class="text-center text-muted py-3">Belum ada pengajuan.</td></tr>
            <?php endif; ?>
            </tbody>
          </table>
          </div>
        </div></div>
      </div>

    </div>
  </section>
</main>

<?php include '../template/footer.php'; ?>
