<?php
$page_title = "Edit Pegawai";
include '../template/header.php';
include '../connection.php';
include '../template/sidebar.php';

// Fungsi untuk upload gambar (jika ada perubahan gambar)
function upload()
{
    $namaFile = $_FILES['gambar']['name'];
    $ukuranFile = $_FILES['gambar']['size'];
    $error = $_FILES['gambar']['error'];
    $tmpName = $_FILES['gambar']['tmp_name'];

    if ($error === 4) {
        return null; // Tidak ada gambar yang di-upload
    }

    $ekstensiGambarValid = ['jpg', 'jpeg', 'png'];
    $ekstensiGambar = strtolower(pathinfo($namaFile, PATHINFO_EXTENSION));

    if (!in_array($ekstensiGambar, $ekstensiGambarValid)) {
        echo "<script>alert('Yang Anda upload bukan gambar!');</script>";
        return false;
    }

    if ($ukuranFile > 10000000) {
        echo "<script>alert('Ukuran gambar terlalu besar!');</script>";
        return false;
    }

    $namaFileBaru = uniqid() . '.' . $ekstensiGambar;
    $folderTujuan = '../img/' . $namaFileBaru;
    if (!move_uploaded_file($tmpName, $folderTujuan)) {
        echo "<script>alert('Gagal mengupload gambar!');</script>";
        return false;
    }

    return $namaFileBaru;
}

// Ambil ID pegawai dari URL
$id = $_GET['id'];
$stmt = $koneksi->prepare("SELECT * FROM pegawai WHERE id_peg = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$data = $result->fetch_assoc();
$stmt->close();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $nama_peg = $_POST['nama_peg'];
    $gender_peg = $_POST['gender_peg'];
    $status_peg = $_POST['status_peg'];
    $almt_peg = $_POST['almt_peg'];
    $no_telp_peg = $_POST['no_telp_peg'];
    $email_peg = $_POST['email_peg'];
    $jabatan_peg = $_POST['jabatan_peg'];
    $tgl_peg = $_POST['tgl_peg'];

    // Proses upload foto baru jika ada
    $foto_peg = upload();
    if ($foto_peg === false) return; // Berhenti jika upload gagal

    if ($foto_peg === null) {
        // Jika tidak ada gambar baru yang di-upload, tetap gunakan gambar lama
        $foto_peg = $data['foto_peg'];
    } else {
        // Jika ada gambar baru yang di-upload, hapus gambar lama
        $fotoLama = $data['foto_peg'];
        if ($fotoLama && file_exists("../img/" . $fotoLama)) {
            unlink("../img/" . $fotoLama);
        }
    }

    $stmt = $koneksi->prepare("UPDATE pegawai SET nama_peg = ?, gender_peg = ?, status_peg = ?, almt_peg = ?, no_telp_peg = ?, email_peg = ?, jabatan_peg = ?, tgl_peg = ?, foto_peg = ? WHERE id_peg = ?");
    $stmt->bind_param("sssssssssi", $nama_peg, $gender_peg, $status_peg, $almt_peg, $no_telp_peg, $email_peg, $jabatan_peg, $tgl_peg, $foto_peg, $id);

    if ($stmt->execute()) {
        echo "<script>alert('Data pegawai berhasil diperbarui.'); window.location.href='index.php';</script>";
    } else {
        echo "<script>alert('Gagal memperbarui data.');</script>";
    }
    $stmt->close();
}
?>

<main id="main" class="main">
  <div class="pagetitle">
    <h1>Edit Pegawai</h1>
    <nav>
      <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="/hris/index.php">Home</a></li>
        <li class="breadcrumb-item"><a href="index.php">Data Pegawai</a></li>
        <li class="breadcrumb-item active">Edit Pegawai</li>
      </ol>
    </nav>
  </div><!-- End Page Title -->

  <section class="section">
    <div class="row">
      <div class="card">
        <div class="card-body">
          <h5 class="card-title">Form Edit Pegawai</h5>

          <form method="POST" action="" enctype="multipart/form-data">
            <div class="mb-3">
              <label for="nama_peg" class="form-label">Nama Pegawai</label>
              <input type="text" class="form-control" id="nama_peg" name="nama_peg" value="<?= $data['nama_peg']; ?>" required>
            </div>
            <div class="mb-3">
              <label for="gender_peg" class="form-label">Gender</label>
              <select class="form-select" id="gender_peg" name="gender_peg" required>
                <option value="laki-laki" <?= $data['gender_peg'] == 'laki-laki' ? 'selected' : ''; ?>>Laki-laki</option>
                <option value="perempuan" <?= $data['gender_peg'] == 'perempuan' ? 'selected' : ''; ?>>Perempuan</option>
              </select>
            </div>
            <div class="mb-3">
              <label for="status_peg" class="form-label">Status</label>
              <select class="form-select" id="status_peg" name="status_peg" required>
                <option value="aktif" <?= $data['status_peg'] == 'aktif' ? 'selected' : ''; ?>>Aktif</option>
                <option value="cuti" <?= $data['status_peg'] == 'cuti' ? 'selected' : ''; ?>>Cuti</option>
                <option value="non-aktif" <?= $data['status_peg'] == 'non-aktif' ? 'selected' : ''; ?>>Non-Aktif</option>
              </select>
            </div>
            <div class="mb-3">
              <label for="almt_peg" class="form-label">Alamat</label>
              <textarea class="form-control" id="almt_peg" name="almt_peg" rows="3" required><?= $data['almt_peg']; ?></textarea>
            </div>
            <div class="mb-3">
              <label for="no_telp_peg" class="form-label">No. Telepon</label>
              <input type="text" class="form-control" id="no_telp_peg" name="no_telp_peg" value="<?= $data['no_telp_peg']; ?>" required>
            </div>
            <div class="mb-3">
              <label for="email_peg" class="form-label">Email</label>
              <input type="email" class="form-control" id="email_peg" name="email_peg" value="<?= $data['email_peg']; ?>" required>
            </div>
            <div class="mb-3">
              <label for="jabatan_peg" class="form-label">Jabatan</label>
              <input type="text" class="form-control" id="jabatan_peg" name="jabatan_peg" value="<?= $data['jabatan_peg']; ?>" required>
            </div>
            <div class="mb-3">
              <label for="tgl_peg" class="form-label">Tanggal</label>
              <input type="date" class="form-control" id="tgl_peg" name="tgl_peg" value="<?= $data['tgl_peg']; ?>">
            </div>
            <div class="mb-3">
              <label for="gambar" class="form-label">Foto</label>
              <input type="file" class="form-control" id="gambar" name="gambar">
              <p class="mt-2">Foto saat ini: <img src="/hris/img/<?= $data['foto_peg']; ?>" alt="Foto Pegawai" width="100
                          <div class="mb-3">
              <label for="tgl_peg" class="form-label">Tanggal</label>
              <input type="date" class="form-control" id="tgl_peg" name="tgl_peg" value="<?= $data['tgl_peg']; ?>">
            </div>
            <div class="mb-3">
              <label for="gambar" class="form-label">Foto</label>
              <input type="file" class="form-control" id="gambar" name="gambar">
              <p class="mt-2">Foto saat ini: <img src="/hris/img/<?= $data['foto_peg']; ?>" alt="Foto Pegawai" width="100"></p>
            </div>
            <div class="text-center">
              <button type="submit" class="btn btn-primary">Update</button>
              <a href="index.php" class="btn btn-secondary">Kembali</a>
            </div>
          </form>
        </div>
      </div>
    </div>
  </section>
</main><!-- End #main -->

<?php include '../template/footer.php'; ?>
