<?php
// Start output buffering
ob_start();
include '../template/header.php';
include '../template/sidebar.php';
include '../connection.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $kontrak_id = $_POST['kontrak_id'];
    $perubahan = $_POST['perubahan'];
    $dibuat_oleh = $_POST['dibuat_oleh'];
    $gaji_sebelum = $_POST['gaji_sebelum'];
    $gaji_setelah = $_POST['gaji_setelah'];

    $stmt = $conn->prepare("INSERT INTO riwayat_perubahan_kontrak (kontrak_id, perubahan, dibuat_oleh, gaji_sebelum_perubahan, gaji_setelah_perubahan) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("issss", $kontrak_id, $perubahan, $dibuat_oleh, $gaji_sebelum, $gaji_setelah);

    if ($stmt->execute()) {
        // Redirect sebelum output HTML
        header("Location: submit_perubahan.php?message=success");
        exit();
    } else {
        $error_message = "<div class='alert alert-danger'>Terjadi kesalahan dalam menyimpan data.</div>";
    }
}

// Ambil data kontrak pegawai
$kontrak_pegawai = $conn->query("SELECT id, nomor_kontrak, gaji FROM kontrak_pegawai WHERE status_kontrak = 'Aktif'")->fetch_all(MYSQLI_ASSOC);

ob_end_flush(); // Stop output buffering
?>

<main id="main" class="main">
    <?php if (isset($_GET['message']) && $_GET['message'] == 'success'): ?>
        <div class="alert alert-success animate__animated animate__fadeIn" role="alert">
            Perubahan kontrak berhasil disimpan!
        </div>
    <?php endif; ?>

    <?php if (isset($error_message)) echo $error_message; ?>

    <div class="container">
        <h1 class="animate__animated animate__fadeInDown">Tambah Perubahan Kontrak</h1>
        <form method="POST" action="">
            <div class="mb-3">
                <label for="kontrak_id" class="form-label">Pilih Kontrak</label>
                <select class="form-control" name="kontrak_id" id="kontrak_id" required>
                    <option value="">-- Pilih Kontrak --</option>
                    <?php foreach ($kontrak_pegawai as $kontrak): ?>
                        <option value="<?= $kontrak['id'] ?>" data-gaji="<?= $kontrak['gaji'] ?>">
                            <?= $kontrak['nomor_kontrak'] ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="mb-3">
                <label for="perubahan" class="form-label">Deskripsi Perubahan</label>
                <textarea class="form-control" name="perubahan" id="perubahan" required></textarea>
            </div>
            <div class="mb-3">
                <label for="dibuat_oleh" class="form-label">Dibuat Oleh</label>
                <input type="text" class="form-control" name="dibuat_oleh" id="dibuat_oleh" required>
            </div>
            <div class="mb-3">
                <label for="gaji_sebelum" class="form-label">Gaji Sebelum</label>
                <input type="text" class="form-control" name="gaji_sebelum" id="gaji_sebelum" readonly>
            </div>
            <div class="mb-3">
                <label for="gaji_setelah" class="form-label">Gaji Setelah</label>
                <input type="text" class="form-control" name="gaji_setelah" id="gaji_setelah" required>
            </div>
            <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
        </form>
    </div>
</main>

<script>
    document.getElementById('kontrak_id').addEventListener('change', function() {
        let selectedOption = this.options[this.selectedIndex];
        document.getElementById('gaji_sebelum').value = selectedOption.getAttribute('data-gaji');
    });
</script>

<?php include '../template/footer.php'; ?>
