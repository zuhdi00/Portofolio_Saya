<?php
/**
 * pegawai/index.php   [dbHR / SQL Server]
 * Daftar pegawai dari dbo.pegawai + join jabatan, unit_kerja, department.
 */
$page_title = "Data Pegawai";
include '../template/header.php';
include '../config/koneksi_sqlsrv.php';   // $conn
include '../template/sidebar.php';

// --- filter pencarian sederhana ---
$cari   = trim($_GET['cari'] ?? '');
$status = trim($_GET['status'] ?? '');

$where  = ["p.is_aktif = 1"];
$params = [];

if ($cari !== '') {
    $where[]  = "(p.nama_peg LIKE ? OR p.no_ktp LIKE ?)";
    $params[] = "%$cari%";
    $params[] = "%$cari%";
}
if ($status !== '') {
    $where[]  = "p.status_karyawan = ?";
    $params[] = $status;
}

$sql = "SELECT
            p.id_peg, p.no_ktp, p.nama_peg, p.gender, p.email_peg, p.no_hp_peg,
            p.tgl_lahir, p.tgl_masuk, p.status_karyawan, p.lokasi_kerja,
            p.kota, p.foto_peg,
            j.nama_jabatan,
            u.nama_unit,
            d.nama_dept
        FROM dbo.pegawai p
        LEFT JOIN dbo.jabatan     j ON j.id_jabatan = p.jabatan_id
        LEFT JOIN dbo.unit_kerja  u ON u.id         = p.unit_kerja_id
        LEFT JOIN dbo.department  d ON d.id_dept    = u.department_id
        WHERE " . implode(' AND ', $where) . "
        ORDER BY p.nama_peg";

$stmt = sqlsrv_query($conn, $sql, array_values($params));
if ($stmt === false) {
    die("<pre>Query gagal: " . print_r(sqlsrv_errors(), true) . "</pre>");
}

/** tampilkan tanggal DateTime -> d-m-Y */
function tgl($v) {
    return $v instanceof DateTime ? $v->format('d-m-Y') : '-';
}
/** L/P -> label */
function gender_label($g) {
    return $g === 'L' ? 'Laki-laki' : ($g === 'P' ? 'Perempuan' : '-');
}
/** warna badge status karyawan */
function badge_status($s) {
    return ['tetap' => 'success', 'kontrak' => 'warning', 'harian' => 'secondary'][$s] ?? 'light';
}
?>

<main id="main" class="main">
  <div class="pagetitle">
    <h1>PEGAWAI</h1>
    <nav>
      <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="/hris/index.php">Home</a></li>
        <li class="breadcrumb-item active">Pegawai</li>
      </ol>
    </nav>
  </div>

  <section class="section dashboard">
    <div class="row">
      <div class="card">
        <div class="card-body">
          <h5 class="card-title">
            Daftar Pegawai
            <a href="tambah_pegawai_lengkap.php" class="btn btn-primary btn-sm float-end">
              <i class="bi bi-plus-circle"></i> Tambah Pegawai
            </a>
          </h5>

          <!-- Filter -->
          <form method="GET" class="row g-2 mb-3">
            <div class="col-md-4">
              <input type="text" name="cari" class="form-control" placeholder="Cari nama / no. KTP"
                     value="<?= htmlspecialchars($cari) ?>">
            </div>
            <div class="col-md-3">
              <select name="status" class="form-select">
                <option value="">-- semua status --</option>
                <?php foreach (['tetap','kontrak','harian'] as $s): ?>
                  <option value="<?= $s ?>" <?= $status === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-3">
              <button type="submit" class="btn btn-secondary">Filter</button>
              <a href="index.php" class="btn btn-outline-secondary">Reset</a>
            </div>
          </form>

          <div class="table-responsive">
          <table class="table table-hover datatable">
            <thead>
              <tr>
                <th>Foto</th>
                <th>No. KTP</th>
                <th>Nama Pegawai</th>
                <th>Gender</th>
                <th>Jabatan</th>
                <th>Unit Kerja</th>
                <th>Departemen</th>
                <th>Status</th>
                <th>Tgl Masuk</th>
                <th>Email</th>
                <th>No. HP</th>
                <th>Aksi</th>
              </tr>
            </thead>
            <tbody>
              <?php $ada = false; while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)): $ada = true; ?>
              <tr>
                <td>
                  <img src="<?= !empty($row['foto_peg']) ? htmlspecialchars($row['foto_peg']) : '/hris/img/default.png' ?>"
                       alt="Foto" style="width:44px;height:44px;border-radius:50%;object-fit:cover;">
                </td>
                <td><?= htmlspecialchars($row['no_ktp'] ?? '-') ?></td>
                <td><strong><?= htmlspecialchars($row['nama_peg']) ?></strong></td>
                <td><?= gender_label($row['gender']) ?></td>
                <td><?= htmlspecialchars($row['nama_jabatan'] ?? '-') ?></td>
                <td><?= htmlspecialchars($row['nama_unit'] ?? '-') ?></td>
                <td><?= htmlspecialchars($row['nama_dept'] ?? '-') ?></td>
                <td><span class="badge bg-<?= badge_status($row['status_karyawan']) ?>">
                      <?= htmlspecialchars($row['status_karyawan'] ?? '-') ?></span></td>
                <td><?= tgl($row['tgl_masuk']) ?></td>
                <td><?= htmlspecialchars($row['email_peg'] ?? '-') ?></td>
                <td><?= htmlspecialchars($row['no_hp_peg'] ?? '-') ?></td>
                <td style="white-space:nowrap">
                  <a href="detail.php?id=<?= $row['id_peg'] ?>" class="btn btn-sm btn-info" title="Detail">
                    <i class="bi bi-eye"></i></a>
                  <a href="edit.php?id=<?= $row['id_peg'] ?>" class="btn btn-sm btn-warning" title="Edit">
                    <i class="bi bi-pencil-square"></i></a>
                  <a href="hapus.php?id=<?= $row['id_peg'] ?>" class="btn btn-sm btn-danger" title="Nonaktifkan"
                     onclick="return confirm('Nonaktifkan pegawai ini?')">
                    <i class="bi bi-trash"></i></a>
                </td>
              </tr>
              <?php endwhile; ?>

              <?php if (!$ada): ?>
              <tr><td colspan="12" class="text-center text-muted py-4">Belum ada data pegawai.</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
          </div>

        </div>
      </div>
    </div>
  </section>
</main>

<?php
sqlsrv_free_stmt($stmt);
include '../template/footer.php';
?>
