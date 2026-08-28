<?php
include '../template/header.php';
include '../template/sidebar.php';
include '../connection.php';

// Pastikan ID tersedia dan valid
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    echo "<script>alert('ID tidak ditemukan atau tidak valid.'); window.history.back();</script>";
    exit;
}

$id = intval($_GET['id']); // Sanitasi ID

// Ambil data perubahan kontrak berdasarkan ID
$stmt = $conn->prepare("SELECT * FROM `riwayat_perubahan_kontrak` WHERE `id` = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$data = $result->fetch_assoc();
$stmt->close();

if (!$data) {
    echo "<script>alert('Data tidak ditemukan.'); window.history.back();</script>";
    exit;
}
?>

<main id="main" class="main">
    <div class="container">
        <h1>Edit Riwayat Perubahan Kontrak</h1>
        <form method="post" action="update_perubahan.php">
            <input type="hidden" name="id" value="<?= htmlspecialchars($data['id']) ?>">
            
            <div class="mb-3">
                <label for="kontrak_id" class="form-label">ID Kontrak</label>
                <input type="number" class="form-control" id="kontrak_id" name="kontrak_id" value="<?= htmlspecialchars($data['kontrak_id']) ?>" required>
            </div>

            <div class="mb-3">
                <label for="tanggal_perubahan" class="form-label">Tanggal Perubahan</label>
                <input type="datetime-local" class="form-control" id="tanggal_perubahan" name="tanggal_perubahan" 
                    value="<?= date('Y-m-d\TH:i', strtotime($data['tanggal_perubahan'])) ?>" required>
            </div>

            <div class="mb-3">
                <label for="gaji_sebelum_perubahan" class="form-label">Gaji Sebelum Perubahan</label>
                <input type="text" class="form-control" id="gaji_sebelum_perubahan" name="gaji_sebelum_perubahan" 
                    value="<?= htmlspecialchars($data['gaji_sebelum_perubahan']) ?>" required>
            </div>

            <div class="mb-3">
                <label for="gaji_setelah_perubahan" class="form-label">Gaji Setelah Perubahan</label>
                <input type="text" class="form-control" id="gaji_setelah_perubahan" name="gaji_setelah_perubahan" 
                    value="<?= htmlspecialchars($data['gaji_setelah_perubahan']) ?>" required>
            </div>

            <div class="mb-3">
                <label for="perubahan" class="form-label">Keterangan Perubahan</label>
                <textarea class="form-control" id="perubahan" name="perubahan" required><?= htmlspecialchars($data['perubahan']) ?></textarea>
            </div>

            <div class="mb-3">
                <label for="dibuat_oleh" class="form-label">Dibuat Oleh</label>
                <input type="text" class="form-control" id="dibuat_oleh" name="dibuat_oleh" 
                    value="<?= htmlspecialchars($data['dibuat_oleh']) ?>" required>
            </div>

            <button type="submit" class="btn btn-primary">Update</button>
        </form>
    </div>
</main>

<?php
include '../template/footer.php';
?>
