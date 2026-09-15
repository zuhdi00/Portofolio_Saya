<?php
/**
 * kedisiplinan/sp_form.php[?peg=ID]
 * Terbitkan Surat Peringatan - KHUSUS HRD & Admin IT.
 */
require_once __DIR__ . '/../auth/auth.php';
wajib_peran(['hr','admin_it']);
require_once __DIR__ . '/../config/koneksi_sqlsrv.php';

$pesan = '';
$pegAwal = (int)($_GET['peg'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pegId  = (int)($_POST['pegawai_id'] ?? 0);
    $jenis  = $_POST['jenis'] ?? '';
    $kat    = trim($_POST['kategori'] ?? '');
    $terbit = $_POST['tanggal_terbit'] ?? date('Y-m-d');
    $sampai = $_POST['berlaku_sampai'] ?? null;
    $alasan = trim($_POST['alasan'] ?? '');
    $ket    = trim($_POST['keterangan'] ?? '');
    $u      = user_login();

    if (!$pegId || !in_array($jenis, ['TEGURAN','SP1','SP2','SP3'], true)) {
        $pesan = "<div class='alert alert-danger'>Pilih pegawai dan jenis SP terlebih dahulu.</div>";
    } else {
        /* ambil data pegawai untuk arsip */
        $rs = sqlsrv_query($conn,
          "SELECT p.nama_peg, u2.department_id
           FROM dbo.pegawai p LEFT JOIN dbo.unit_kerja u2 ON u2.id = p.unit_kerja_id
           WHERE p.id_peg = ?", [$pegId]);
        $pg = $rs ? sqlsrv_fetch_array($rs, SQLSRV_FETCH_ASSOC) : null;
        if ($rs) sqlsrv_free_stmt($rs);

        /* nomor SP otomatis */
        $bulan = date('Ym', strtotime($terbit));
        $rs = sqlsrv_query($conn,
          "SELECT COUNT(*)+1 AS n FROM dbo.surat_peringatan WHERE FORMAT(tanggal_terbit,'yyyyMM')=?", [$bulan]);
        $urut = ($rs && ($x=sqlsrv_fetch_array($rs,SQLSRV_FETCH_ASSOC))) ? (int)$x['n'] : 1;
        if ($rs) sqlsrv_free_stmt($rs);
        $noSp = sprintf('SP-%s-%03d', $bulan, $urut);

        $st = sqlsrv_query($conn,
          "INSERT INTO dbo.surat_peringatan
           (no_sp,pegawai_id,nama_pegawai,department_id,jenis,kategori,
            tanggal_terbit,berlaku_sampai,alasan,keterangan,dibuat_oleh,status)
           VALUES (?,?,?,?,?,?,?,?,?,?,?,'AKTIF')",
          [$noSp,$pegId,$pg['nama_peg'] ?? null,$pg['department_id'] ?? null,$jenis,$kat ?: null,
           $terbit,$sampai ?: null,$alasan ?: null,$ket ?: null,$u['nama_lengkap'] ?? null]);

        if ($st === false) {
            $e = sqlsrv_errors();
            $pesan = "<div class='alert alert-danger'>Gagal: ".h($e[0]['message'] ?? '')."</div>";
        } else {
            sqlsrv_free_stmt($st);
            $pesan = "<div class='alert alert-success'>SP <strong>$noSp</strong> diterbitkan. "
                   . "<a href='sp_histori.php?peg=$pegId'>Lihat histori pegawai ini</a>.</div>";
        }
    }
}

/* daftar pegawai aktif */
$pegList=[]; $rs=sqlsrv_query($conn,
  "SELECT p.id_peg,p.nik,p.nama_peg,d.nama_dept
   FROM dbo.pegawai p
   LEFT JOIN dbo.unit_kerja u ON u.id=p.unit_kerja_id
   LEFT JOIN dbo.department d ON d.id_dept=u.department_id
   WHERE p.is_aktif=1 ORDER BY p.nama_peg");
if ($rs) { while($x=sqlsrv_fetch_array($rs,SQLSRV_FETCH_ASSOC)) $pegList[]=$x; sqlsrv_free_stmt($rs); }

/* riwayat SP pegawai terpilih (untuk acuan eskalasi) */
$riwayat = [];
if ($pegAwal) {
    $rs = sqlsrv_query($conn,
      "SELECT no_sp,jenis,tanggal_terbit,status FROM dbo.surat_peringatan
       WHERE pegawai_id=? ORDER BY tanggal_terbit DESC", [$pegAwal]);
    if ($rs) { while($x=sqlsrv_fetch_array($rs,SQLSRV_FETCH_ASSOC)) $riwayat[]=$x; sqlsrv_free_stmt($rs); }
}

$page_title = "Terbitkan Surat Peringatan";
include __DIR__ . '/../template/header.php';
include __DIR__ . '/../template/sidebar.php';
function h($v){ return htmlspecialchars((string)($v ?? '')); }
function tglF($v){ return $v instanceof DateTime ? $v->format('d-m-Y') : '—'; }
?>
<main id="main" class="main">
  <div class="pagetitle d-flex justify-content-between align-items-start flex-wrap">
    <div><h1>Terbitkan Surat Peringatan</h1>
      <nav><ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="sp_histori.php">Histori SP</a></li>
        <li class="breadcrumb-item active">Terbitkan</li>
      </ol></nav></div>
    <div class="pt-2"><a href="sp_histori.php" class="btn btn-outline-primary btn-sm">
      <i class="bi bi-arrow-left"></i> Histori SP</a></div>
  </div>

  <section class="section">
    <?= $pesan ?>
    <div class="row">
      <div class="col-lg-7">
        <div class="card"><div class="card-body">
          <h5 class="card-title">Formulir SP</h5>
          <form method="POST">
            <div class="row g-3">
              <div class="col-md-12">
                <label class="form-label">Pegawai</label>
                <select name="pegawai_id" class="form-select" required>
                  <option value="">-- pilih pegawai --</option>
                  <?php foreach($pegList as $p): ?>
                    <option value="<?= (int)$p['id_peg'] ?>" <?= $pegAwal===(int)$p['id_peg']?'selected':'' ?>>
                      <?= h($p['nama_peg']) ?><?= $p['nik'] ? ' — '.h($p['nik']) : '' ?>
                      <?= $p['nama_dept'] ? ' ('.h($p['nama_dept']).')' : '' ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="col-md-4">
                <label class="form-label">Jenis</label>
                <select name="jenis" class="form-select" required>
                  <option value="TEGURAN">Teguran Lisan/Tertulis</option>
                  <option value="SP1">SP 1</option>
                  <option value="SP2">SP 2</option>
                  <option value="SP3">SP 3</option>
                </select>
              </div>
              <div class="col-md-4">
                <label class="form-label">Kategori Pelanggaran</label>
                <select name="kategori" class="form-select">
                  <option value="">-- pilih --</option>
                  <option value="TERLAMBAT">Keterlambatan</option>
                  <option value="MANGKIR">Mangkir / Alpha</option>
                  <option value="DISIPLIN">Disiplin Kerja</option>
                  <option value="SOP">Pelanggaran SOP</option>
                  <option value="LAINNYA">Lainnya</option>
                </select>
              </div>
              <div class="col-md-4">
                <label class="form-label">Tanggal Terbit</label>
                <input type="date" name="tanggal_terbit" class="form-control" value="<?= date('Y-m-d') ?>" required>
              </div>
              <div class="col-md-4">
                <label class="form-label">Berlaku Sampai</label>
                <input type="date" name="berlaku_sampai" class="form-control"
                       value="<?= date('Y-m-d', strtotime('+6 months')) ?>">
                <small class="text-muted">Umumnya 6 bulan.</small>
              </div>
              <div class="col-md-8">
                <label class="form-label">Alasan Singkat</label>
                <input type="text" name="alasan" class="form-control"
                       placeholder="mis. terlambat 12 kali dalam 1 bulan">
              </div>
              <div class="col-md-12">
                <label class="form-label">Keterangan / Rincian</label>
                <textarea name="keterangan" class="form-control" rows="3"
                          placeholder="rincian pelanggaran, tindakan sebelumnya, dll"></textarea>
              </div>
            </div>
            <button class="btn btn-danger mt-3"><i class="bi bi-file-earmark-text"></i> Terbitkan SP</button>
          </form>
        </div></div>
      </div>

      <div class="col-lg-5">
        <div class="card"><div class="card-body">
          <h5 class="card-title">Riwayat SP Pegawai Ini</h5>
          <?php if ($pegAwal && $riwayat): ?>
            <table class="table table-sm">
              <thead class="table-light"><tr><th>No SP</th><th>Jenis</th><th>Terbit</th><th>Status</th></tr></thead>
              <tbody>
              <?php foreach($riwayat as $x): ?>
                <tr>
                  <td><small><?= h($x['no_sp']) ?></small></td>
                  <td><span class="badge bg-secondary"><?= h($x['jenis']) ?></span></td>
                  <td><small><?= tglF($x['tanggal_terbit']) ?></small></td>
                  <td><small><?= h($x['status']) ?></small></td>
                </tr>
              <?php endforeach; ?>
              </tbody>
            </table>
            <div class="alert alert-warning py-2 small mb-0">
              Pegawai ini sudah memiliki <?= count($riwayat) ?> SP. Pertimbangkan tingkat eskalasi.
            </div>
          <?php elseif ($pegAwal): ?>
            <p class="text-muted small mb-0">Belum ada SP untuk pegawai ini.</p>
          <?php else: ?>
            <p class="text-muted small mb-0">Pilih pegawai dari daftar keterlambatan
              untuk melihat riwayat SP-nya di sini.</p>
          <?php endif; ?>
        </div></div>
      </div>
    </div>
  </section>
</main>
<?php include __DIR__ . '/../template/footer.php'; ?>
