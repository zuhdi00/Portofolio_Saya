<?php
include '../template/header.php';
include '../template/sidebar.php';
include '../connection.php';

// Validasi ID
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    echo "<script>alert('ID tidak valid!'); window.location.href='index.php';</script>";
    exit;
}

$id = (int)$_GET['id']; // Konversi ke integer

// Ambil data yang akan diedit
$stmt = $conn->prepare("SELECT * FROM `dokumen_pendukung` WHERE id = ?");
if (!$stmt) {
    die("<script>alert('Gagal mempersiapkan query: " . addslashes($conn->error) . "'); window.location.href='index.php';</script>");
}

$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$document = $result->fetch_assoc();
$stmt->close();

if (!$document) {
    echo "<script>alert('Dokumen tidak ditemukan!'); window.location.href='index.php';</script>";
    exit;
}
?>

<main id="main" class="main">
    <div class="container">
        <h1>Edit Dokumen Pendukung</h1>
        <form method="post" action="update_dokumen.php" enctype="multipart/form-data">
            <input type="hidden" name="id" value="<?= htmlspecialchars($document['id']) ?>">
            
            <div class="mb-3">
                <label for="kontrak_id" class="form-label">ID Kontrak</label>
                <input type="text" class="form-control" id="kontrak_id" name="kontrak_id" value="<?= htmlspecialchars($document['kontrak_id']) ?>" required>
            </div>
            
            <div class="mb-3">
                <label for="nama_dokumen" class="form-label">Nama Dokumen</label>
                <input type="text" class="form-control" id="nama_dokumen" name="nama_dokumen" value="<?= htmlspecialchars($document['nama_dokumen']) ?>" required>
            </div>
            
            <div class="mb-3">
                <label for="tanggal_upload" class="form-label">Tanggal Upload</label>
                <input type="datetime-local" class="form-control" id="tanggal_upload" name="tanggal_upload" value="<?= date('Y-m-d\TH:i', strtotime($document['tanggal_upload'])) ?>" required>
            </div>
            
            <div class="mb-3">
                <label for="keterangan" class="form-label">Keterangan</label>
                <textarea class="form-control" id="keterangan" name="keterangan" rows="3"><?= htmlspecialchars($document['keterangan']) ?></textarea>
            </div>
            
            <div class="mb-3">
                <label for="file_upload" class="form-label">Upload File Baru (Opsional)</label>
                <input type="file" class="form-control" id="file_upload" name="file_upload">
                <small class="text-muted">Biarkan kosong jika tidak ingin mengubah file.</small>
            </div>
            
            <div class="mb-3">
                <label class="form-label">File Saat Ini</label>
                <div>
                    <a href="<?= htmlspecialchars($document['file_path']) ?>" target="_blank" class="btn btn-sm btn-outline-primary">Lihat File</a>
                </div>
            </div>
            
            <button type="submit" class="btn btn-primary">Update</button>
            <a href="index.php" class="btn btn-secondary">Batal</a>
        </form>
    </div>
</main>

<?php include '../template/footer.php'; ?>
