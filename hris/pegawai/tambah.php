<?php
$page_title = "Tambah Pegawai";
include '../template/header.php';
include '../connection.php';
include '../template/sidebar.php';

// Membuat koneksi menggunakan OOP
$conn = new mysqli("localhost", "root", "", "hris");

// Periksa koneksi
if ($conn->connect_error) {
    die("Koneksi gagal: " . $conn->connect_error);
}

// Fungsi untuk upload gambar
function upload($redirectUrl) {
    $namaFile = $_FILES['gambar']['name'];
    $ukuranFile = $_FILES['gambar']['size'];
    $error = $_FILES['gambar']['error'];
    $tmpName = $_FILES['gambar']['tmp_name'];

    // Cek apakah tidak ada gambar yang diupload
    if ($error === 4) {
        echo "<script>
                alert('Pilih gambar terlebih dahulu!');
                window.location.href = '$redirectUrl';
              </script>";
        exit();
    }

    // Cek apakah yang diupload adalah gambar
    $ekstensiGambarValid = ['jpg', 'jpeg', 'png'];
    $ekstensiGambar = explode('.', $namaFile);
    $ekstensiGambar = strtolower(end($ekstensiGambar));

    if (!in_array($ekstensiGambar, $ekstensiGambarValid)) {
        echo "<script>
                alert('Yang Anda upload bukan gambar!');
                window.location.href = '$redirectUrl';
              </script>";
        exit();
    }

    // Cek ukuran gambar (max 10MB)
    if ($ukuranFile > 10000000) {
        echo "<script>
                alert('Ukuran gambar terlalu besar!');
                window.location.href = '$redirectUrl';
              </script>";
        exit();
    }

    // Generate nama file baru
    $namaFileBaru = uniqid();
    $namaFileBaru .= '.';
    $namaFileBaru .= $ekstensiGambar;

    // Pindahkan file ke folder tujuan
    $folderTujuan = 'D:/Amali Poenya/Instalasi xampp/xampp/htdocs/hris/img/' . $namaFileBaru;
    if (!move_uploaded_file($tmpName, $folderTujuan)) {
        echo "<script>
                alert('Gagal mengupload gambar!');
                window.location.href = '$redirectUrl';
              </script>";
        exit();
    }

    return $namaFileBaru;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Ambil data dari form
    $nama_peg = $_POST['nama_peg'];
    $gender_peg = $_POST['gender_peg'];
    $status_peg = $_POST['status_peg'];
    $almt_peg = $_POST['almt_peg'];
    $no_telp_peg = $_POST['no_telp_peg'];
    $email_peg = $_POST['email_peg'];
    $jabatan_peg = $_POST['jabatan_peg'];
    $departemen_peg = $_POST['departemen_peg'];
    $tgl_peg = $_POST['tgl_peg'];

    // Upload gambar
    $foto_peg = upload('tambah.php'); // Redirect ke tambah.php jika gagal
    if (!$foto_peg) {
        exit(); // Berhenti jika upload gagal
    }

    // Query untuk insert data
    $query = "INSERT INTO pegawai (nama_peg, gender_peg, status_peg, almt_peg, no_telp_peg, email_peg, jabatan_peg, departemen_peg, tgl_peg, foto_peg) 
              VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ssssssssss", $nama_peg, $gender_peg, $status_peg, $almt_peg, $no_telp_peg, $email_peg, $jabatan_peg, $departemen_peg, $tgl_peg, $foto_peg);

    if ($stmt->execute()) {
        echo "<script>alert('Data pegawai berhasil ditambahkan.'); window.location.href='index.php';</script>";
    } else {
        echo "<script>alert('Gagal menambahkan data: " . $stmt->error . "');</script>";
    }

    $stmt->close();
}
$conn->close();
?>

<main id="main" class="main">
  <div class="pagetitle">
    <h1>Tambah Pegawai</h1>
    <nav>
      <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="/hris/index.php">Home</a></li>
        <li class="breadcrumb-item"><a href="index.php">Data Pegawai</a></li>
        <li class="breadcrumb-item active">Tambah Pegawai</li>
      </ol>
    </nav>
  </div><!-- End Page Title -->

  <section class="section">
    <div class="row">
      <div class="card">
        <div class="card-body">
          <h5 class="card-title">Form Tambah Pegawai</h5>

          <form method="POST" action="" enctype="multipart/form-data">
            <div class="mb-3">
              <label for="nama_peg" class="form-label">Nama Pegawai</label>
              <input type="text" class="form-control" id="nama_peg" name="nama_peg" required>
            </div>
            <div class="mb-3">
              <label for="gender_peg" class="form-label">Gender</label>
              <select class="form-select" id="gender_peg" name="gender_peg" required>
                <option value="laki-laki">Laki-laki</option>
                <option value="perempuan">Perempuan</option>
              </select>
            </div>
            <div class="mb-3">
              <label for="status_peg" class="form-label">Status</label>
              <select class="form-select" id="status_peg" name="status_peg" required>
                <option value="aktif">Aktif</option>
                <option value="cuti">Cuti</option>
                <option value="non-aktif">Non-Aktif</option>
              </select>
            </div>
            <div class="mb-3">
              <label for="almt_peg" class="form-label">Alamat</label>
              <textarea class="form-control" id="almt_peg" name="almt_peg" rows="3" required></textarea>
            </div>
            <div class="mb-3">
              <label for="no_telp_peg" class="form-label">No. Telepon</label>
              <input type="text" class="form-control" id="no_telp_peg" name="no_telp_peg" required>
            </div>
            <div class="mb-3">
              <label for="email_peg" class="form-label">Email</label>
              <input type="email" class="form-control" id="email_peg" name="email_peg" required>
            </div>
            <div class="mb-3">
              <label for="jabatan_peg" class="form-label">Jabatan</label>
              <select class="form-select" id="jabatan_peg" name="jabatan_peg" required>
                <option value="Direktur">Direktur</option>
                <option value="Manager">Manager</option>
                <option value="Staff">Staff</option>
                <option value="Supervisor">Supervisor</option>	
              </select>
            </div>
            <div class="mb-3">
              <label for="departemen_peg" class="form-label">Departemen</label>
              <select class="form-select" id="departemen_peg" name="departemen_peg" required>
                <option value="HRD">HRD</option>
                <option value="Marketing">Marketing</option>
                <option value="IT">IT</option>
                <option value="Finance">Finance</option>	
              </select>
            </div>
            <div class="mb-3">
              <label for="tgl_peg" class="form-label">Tanggal</label>
              <input type="date" class="form-control" id="tgl_peg" name="tgl_peg">
            </div>
            <div class="mb-3">
              <label for="gambar" class="form-label">Foto</label>
              <input type="file" class="form-control" id="gambar" name="gambar">
            </div>
            <div class="text-center">
              <button type="submit" class="btn btn-primary">Simpan</button>
              <a href="index.php" class="btn btn-secondary">Kembali</a>
            </div>
          </form>
        </div>
      </div>
    </div>
  </section>
</main><!-- End #main -->

<?php include '../template/footer.php'; ?>
