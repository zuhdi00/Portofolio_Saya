<?php
/**
 * pegawai/detail.php   [dbHR / SQL Server]
 * Tampilkan 1 pegawai lengkap + keluarga, pendidikan, pengalaman kerja.
 */
$page_title = "Detail Pegawai";
include '../template/header.php';
include '../config/koneksi_sqlsrv.php';   // $conn
include '../template/sidebar.php';

$id = (int) ($_GET['id'] ?? 0);
if (!$id) { die("ID pegawai tidak valid."); }

// ---------- data utama ----------
$sql = "SELECT p.*, j.nama_jabatan, u.nama_unit, d.nama_dept
        FROM dbo.pegawai p
        LEFT JOIN dbo.jabatan    j ON j.id_jabatan = p.jabatan_id
        LEFT JOIN dbo.unit_kerja u ON u.id         = p.unit_kerja_id
        LEFT JOIN dbo.department d ON d.id_dept    = u.department_id
        WHERE p.id_peg = ?";
$st  = sqlsrv_query($conn, $sql, [$id]);
if ($st === false) die("<pre>" . print_r(sqlsrv_errors(), true) . "</pre>");
$p = sqlsrv_fetch_array($st, SQLSRV_FETCH_ASSOC);
if (!$p) die("Data pegawai tidak ditemukan.");

/** ambil semua baris tabel anak */
function ambil($conn, $sql, $id) {
    $st = sqlsrv_query($conn, $sql, [$id]);
    $out = [];
    if ($st) { while ($r = sqlsrv_fetch_array($st, SQLSRV_FETCH_ASSOC)) $out[] = $r; }
    return $out;
}
$keluarga   = ambil($conn, "SELECT * FROM dbo.keluarga_pegawai   WHERE pegawai_id = ?", $id);
$pendidikan = ambil($conn, "SELECT * FROM dbo.pendidikan_pegawai WHERE pegawai_id = ? ORDER BY tahun_mulai", $id);
$pengalaman = ambil($conn, "SELECT * FROM dbo.pengalaman_kerja   WHERE pegawai_id = ? ORDER BY tgl_mulai", $id);

function tgl($v) { return $v instanceof DateTime ? $v->format('d-m-Y') : '-'; }
function h($v)   { return htmlspecialchars((string)($v ?? '-')); }
function gender_label($g) { return $g === 'L' ? 'Laki-laki' : ($g === 'P' ? 'Perempuan' : '-'); }

/** cetak baris label-nilai */
function baris($label, $nilai) {
    echo '<div class="col-md-4 mb-2"><div class="text-muted" style="font-size:.78rem">'
       . $label . '</div><div>' . $nilai . '</div></div>';
}
?>

<main id="main" class="main">
  <div class="pagetitle">
    <h1>Detail Pegawai</h1>
    <nav>
      <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="/hris/index.php">Home</a></li>
        <li class="breadcrumb-item"><a href="index.php">Pegawai</a></li>
        <li class="breadcrumb-item active"><?= h($p['nama_peg']) ?></li>
      </ol>
    </nav>
  </div>

  <section class="section">

    <!-- ===== Identitas ===== -->
    <div class="card">
      <div class="card-body pt-3">
        <h5 class="card-title">
          <?= h($p['nama_peg']) ?>
          <a href="edit.php?id=<?= $id ?>" class="btn btn-warning btn-sm float-end">Edit</a>
          <a href="index.php" class="btn btn-secondary btn-sm float-end me-2">Kembali</a>
        </h5>
        <div class="row">
          <?php
          baris('No. KTP',        h($p['no_ktp']));
          baris('NPWP',           h($p['npwp']));
          baris('Gender',         gender_label($p['gender']));
          baris('Tempat/Tgl Lahir', h($p['tempat_lahir']) . ', ' . tgl($p['tgl_lahir']));
          baris('Agama',          h($p['agama']));
          baris('Status PTKP',    h($p['status_nikah']));
          baris('Email',          h($p['email_peg']));
          baris('No. HP',         h($p['no_hp_peg']));
          baris('Company',        h($p['company_name']));
          ?>
        </div>
      </div>
    </div>

    <!-- ===== Kepegawaian ===== -->
    <div class="card">
      <div class="card-body pt-3">
        <h5 class="card-title">Data Kepegawaian</h5>
        <div class="row">
          <?php
          baris('Jabatan',          h($p['nama_jabatan']));
          baris('Unit Kerja',       h($p['nama_unit']));
          baris('Departemen',       h($p['nama_dept']));
          baris('Status Karyawan',  '<span class="badge bg-primary">' . h($p['status_karyawan']) . '</span>');
          baris('Employee Subgroup',h($p['employee_subgroup']));
          baris('Lokasi Kerja',     h($p['lokasi_kerja']));
          baris('Tgl Masuk',        tgl($p['tgl_masuk']));
          baris('Durasi Kontrak',   h($p['contract_month']) . ' bulan');
          baris('Akhir Kontrak',    tgl($p['tgl_akhir_kontrak']));
          baris('Position Code',    h($p['position_code']));
          baris('Level / Grade',    h($p['level_code']) . ' / ' . h($p['grade_code']));
          baris('Tgl Berhenti',     tgl($p['tgl_berhenti']));
          ?>
        </div>
      </div>
    </div>

    <!-- ===== Alamat ===== -->
    <div class="card">
      <div class="card-body pt-3">
        <h5 class="card-title">Alamat</h5>
        <div class="row">
          <div class="col-md-6">
            <div class="text-muted" style="font-size:.78rem">Alamat KTP</div>
            <div><?= h($p['alamat_ktp_peg']) ?><br>
              RT <?= h($p['rt']) ?>/RW <?= h($p['rw']) ?>,
              <?= h($p['kelurahan']) ?>, <?= h($p['kecamatan']) ?><br>
              <?= h($p['kota']) ?>, <?= h($p['provinsi']) ?> <?= h($p['kode_pos']) ?>
            </div>
          </div>
          <div class="col-md-6">
            <div class="text-muted" style="font-size:.78rem">Alamat Domisili</div>
            <div><?= h($p['alamat_domi_peg']) ?><br>
              RT <?= h($p['rt_dom']) ?>/RW <?= h($p['rw_dom']) ?>,
              <?= h($p['kelurahan_dom']) ?>, <?= h($p['kecamatan_dom']) ?><br>
              <?= h($p['kota_dom']) ?>, <?= h($p['provinsi_dom']) ?> <?= h($p['kode_pos_dom']) ?>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- ===== Bank & BPJS ===== -->
    <div class="card">
      <div class="card-body pt-3">
        <h5 class="card-title">Bank &amp; Jaminan Sosial</h5>
        <div class="row">
          <?php
          baris('Nama Bank',    h($p['nama_bank']) . ' (' . h($p['bank_kode']) . ')');
          baris('No. Rekening', h($p['no_rekening']));
          baris('Atas Nama',    h($p['bank_payee']));
          baris('Detail Bank',  h($p['bank_detail']));
          baris('BPJS TK',      h($p['no_bpjs_tk']));
          baris('BPJS Kesehatan', h($p['no_bpjs_kes']));
          ?>
        </div>
      </div>
    </div>

    <!-- ===== Keluarga ===== -->
    <div class="card">
      <div class="card-body pt-3">
        <h5 class="card-title">Data Keluarga</h5>
        <div class="table-responsive">
        <table class="table table-sm table-bordered">
          <thead class="table-light">
            <tr><th>Nama</th><th>Hubungan</th><th>Gender</th><th>Status Nikah</th>
                <th>Status Hidup</th><th>Tempat/Tgl Lahir</th><th>No. KTP</th><th>No. KK</th><th>BPJS</th></tr>
          </thead>
          <tbody>
          <?php if (!$keluarga): ?>
            <tr><td colspan="9" class="text-center text-muted">Tidak ada data keluarga.</td></tr>
          <?php else: foreach ($keluarga as $k): ?>
            <tr>
              <td><?= h($k['nama']) ?></td>
              <td><?= h($k['hubungan']) ?></td>
              <td><?= gender_label($k['gender']) ?></td>
              <td><?= h($k['status_nikah']) ?></td>
              <td><?= h($k['status_hidup']) ?></td>
              <td><?= h($k['tempat_lahir']) ?>, <?= tgl($k['tgl_lahir']) ?></td>
              <td><?= h($k['no_ktp']) ?></td>
              <td><?= h($k['no_kk']) ?></td>
              <td><?= h($k['no_bpjs']) ?></td>
            </tr>
          <?php endforeach; endif; ?>
          </tbody>
        </table>
        </div>
      </div>
    </div>

    <!-- ===== Pendidikan ===== -->
    <div class="card">
      <div class="card-body pt-3">
        <h5 class="card-title">Riwayat Pendidikan</h5>
        <div class="table-responsive">
        <table class="table table-sm table-bordered">
          <thead class="table-light">
            <tr><th>Jenjang</th><th>Sekolah / Institusi</th><th>Jurusan</th>
                <th>Lokasi</th><th>Tahun</th><th>IPK</th></tr>
          </thead>
          <tbody>
          <?php if (!$pendidikan): ?>
            <tr><td colspan="6" class="text-center text-muted">Tidak ada data pendidikan.</td></tr>
          <?php else: foreach ($pendidikan as $e): ?>
            <tr>
              <td><strong><?= h($e['jenjang']) ?></strong></td>
              <td><?= h($e['nama_sekolah']) ?></td>
              <td><?= h($e['jurusan']) ?></td>
              <td><?= h($e['lokasi']) ?></td>
              <td><?= h($e['tahun_mulai']) ?> &ndash; <?= h($e['tahun_selesai']) ?></td>
              <td><?= h($e['ipk']) ?></td>
            </tr>
          <?php endforeach; endif; ?>
          </tbody>
        </table>
        </div>
      </div>
    </div>

    <!-- ===== Pengalaman Kerja ===== -->
    <div class="card">
      <div class="card-body pt-3">
        <h5 class="card-title">Pengalaman Kerja</h5>
        <div class="table-responsive">
        <table class="table table-sm table-bordered">
          <thead class="table-light">
            <tr><th>Perusahaan</th><th>Jabatan</th><th>Periode</th><th>Keterangan</th></tr>
          </thead>
          <tbody>
          <?php if (!$pengalaman): ?>
            <tr><td colspan="4" class="text-center text-muted">Tidak ada data pengalaman kerja.</td></tr>
          <?php else: foreach ($pengalaman as $x): ?>
            <tr>
              <td><?= h($x['nama_perusahaan']) ?></td>
              <td><?= h($x['jabatan']) ?></td>
              <td><?= tgl($x['tgl_mulai']) ?> &ndash; <?= tgl($x['tgl_selesai']) ?></td>
              <td><?= h($x['keterangan']) ?></td>
            </tr>
          <?php endforeach; endif; ?>
          </tbody>
        </table>
        </div>
      </div>
    </div>

  </section>
</main>

<?php include '../template/footer.php'; ?>
