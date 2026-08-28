<?php
$page_title = "Data Pegawai";
include '../template/header.php';
include '../connection.php'; // Pastikan file config.php sudah menggunakan koneksi OOP
include '../template/sidebar.php';
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
  </div><!-- End Page Title -->

  <section class="section dashboard">
    <div class="row">
      <div class="card">
        <div class="card-body">
          <h5 class="card-title">Daftar Pegawai <a href="submit_pegawai_lengkap.php" class="btn btn-primary float-end"><i class="bi bi-plus-circle"></i> Submit</a></h5><a href="submit_pegawai_lengkap.php" class="btn btn-primary float-end"><i class="bi bi-plus-circle"></i> Tambah</a></h5>

          <table class="table datatable">
            <thead>
              <tr>
                <th>Foto</th>
                <th>Nama Pegawai </th>
                <th>Gender</th>
                <th>Status</th>
                <th>Jabatan</th>
                <th>Departemen</th>
                <th>Email</th>
                <th>No. Telepon</th>
                <th>Tanggal Lahir</th>
                <th>Alamat</th>
                <th>Aksi</th>
              </tr>
            </thead>
            <tbody>
              <?php
              // Membuat koneksi menggunakan MySQLi OOP
              $conn = new mysqli("localhost", "root", "", "hris");

              // Memeriksa koneksi
              if ($conn->connect_error) {
                  die("Koneksi gagal: " . $conn->connect_error);
              }

              // Query untuk mengambil data pegawai
              $query = "SELECT * FROM pegawai";
              $result = $conn->query($query);

              // Menampilkan data
              while ($row = $result->fetch_assoc()) {
              ?>
              <tr>
                <td>
                  <?php if (!empty($row['foto_peg'])): ?>
                    <img src="/hris/img/<?= $row['foto_peg'] ?>" alt="Foto Pegawai" style="width: 50px; height: 50px; border-radius: 50%;">
                  <?php else: ?>
                    <img src="/hris/img/default.png" alt="Default Foto" style="width: 50px; height: 50px; border-radius: 50%;">
                  <?php endif; ?>
                </td>
                <td><?= $row['nama_peg'] ?></td>
                <td><?= $row['gender_peg'] ?></td>
                <td>
                  <span class="badge bg-<?=
                    ($row['status_peg'] == 'aktif') ? 'success' : 
                    (($row['status_peg'] == 'cuti') ? 'warning' : 'danger') 
                  ?>">
                    <?= $row['status_peg'] ?>
                  </span>
                </td>
                <td><?= $row['jabatan_peg'] ?></td>
                <td><?= $row['departemen_peg'] ?></td>
                <td><?= $row['email_peg'] ?></td>
                <td><?= $row['no_telp_peg'] ?></td>
                <td><?= $row['tgl_peg'] ?? 'Tidak ada data' ?></td>
                <td><?= $row['almt_peg'] ?? 'Tidak ada data' ?></td>
                <td>
                  <a href="edit.php?id=<?= $row['id_peg'] ?>" class="btn btn-sm btn-warning"><i class="bi bi-pencil-square"></i></a>
                  <a href="hapus.php?id=<?= $row['id_peg'] ?>" class="btn btn-sm btn-danger"><i class="bi bi-trash"></i></a>
                </td>
              </tr>
              <?php } ?>

              <?php
              // Menutup koneksi
              $conn->close();
              ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </section>
</main><!-- End #main -->

<?php include '../template/footer.php'; ?>