<?php
include '../template/header.php';
include '../template/sidebar.php';
include '../connection.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Ambil data dari form
    $kontrak_id = $_POST['kontrak_id'];
    $nama_dokumen = $_POST['nama_dokumen'];
    $keterangan = $_POST['keterangan'];

    // Validasi apakah kontrak_id ada di tabel kontrak_pegawai
    $check_stmt = $conn->prepare("SELECT id FROM kontrak_pegawai WHERE id = ?");
    $check_stmt->bind_param("i", $kontrak_id);
    $check_stmt->execute();
    $result = $check_stmt->get_result();

    if ($result->num_rows === 0) {
        die("<script>alert('Error: Kontrak ID tidak ditemukan!'); history.back();</script>");
    }
    $check_stmt->close();

    // Proses upload file
    $uploadDir = 'uploads/';
    $fileName = uniqid() . '_' . basename($_FILES['file_upload']['name']);
    $filePath = $uploadDir . $fileName;
    $fileType = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

    // Validasi ekstensi file
    $allowedTypes = ['pdf', 'doc', 'docx', 'xls', 'xlsx'];
    if (!in_array($fileType, $allowedTypes)) {
        die("<script>alert('Hanya file PDF, DOC, DOCX, XLS, XLSX yang diizinkan!'); history.back();</script>");
    }

    // Buat direktori jika belum ada
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    // Upload file
    if (move_uploaded_file($_FILES['file_upload']['tmp_name'], $filePath)) {
        // Simpan ke database
        $stmt = $conn->prepare("INSERT INTO dokumen_pendukung (kontrak_id, nama_dokumen, file_path, keterangan) VALUES (?, ?, ?, ?)");

        if ($stmt) {
            $stmt->bind_param("isss", $kontrak_id, $nama_dokumen, $filePath, $keterangan);
            
            if ($stmt->execute()) {
                echo "<script>alert('Dokumen berhasil disimpan!'); window.location.href='index.php';</script>";
            } else {
                unlink($filePath);
                echo "<script>alert('Gagal menyimpan data: " . addslashes($stmt->error) . "'); history.back();</script>";
            }
            $stmt->close();
        } else {
            unlink($filePath);
            echo "<script>alert('Gagal mempersiapkan query: " . addslashes($conn->error) . "'); history.back();</script>";
        }
    } else {
        echo "<script>alert('Gagal upload file!'); history.back();</script>";
    }
}
?>

<main id="main" class="main">
    <div class="container">
        <h1>Tambah Dokumen Pendukung</h1>
        <form method="post" action="" enctype="multipart/form-data">
            <div class="mb-3">
                <label for="kontrak_id" class="form-label">Kontrak ID</label>
                <input type="number" class="form-control" id="kontrak_id" name="kontrak_id" required>
            </div>
            <div class="mb-3">
                <label for="nama_dokumen" class="form-label">Nama Dokumen</label>
                <input type="text" class="form-control" id="nama_dokumen" name="nama_dokumen" required>
            </div>
            <div class="mb-3">
                <label for="keterangan" class="form-label">Keterangan</label>
                <textarea class="form-control" id="keterangan" name="keterangan"></textarea>
            </div>
            <div class="mb-3">
                <label for="file_upload" class="form-label">Upload File</label>
                <input type="file" class="form-control" id="file_upload" name="file_upload" required>
            </div>
            <button type="submit" class="btn btn-primary">Submit</button>
        </form>
    </div>
</main>

<?php include '../template/footer.php'; ?>
