<?php
include '../template/header.php';
include '../template/sidebar.php';
include '../connection.php';

// Check if ID is provided
if (!isset($_GET['id'])) {
    echo "<script>alert('ID tidak diberikan. Silakan berikan ID yang valid.'); window.history.back();</script>";
    exit;
}

$id = $_GET['id'];

// Fetch existing contract data
$result = $conn->query("SELECT * FROM `kontrak_pegawai` WHERE `id` = '$id'");
$contract = $result->fetch_assoc();

if (!$contract) {
    echo "<script>alert('Kontrak tidak ditemukan. Silakan periksa ID.'); window.history.back();</script>";
    exit;
}
?>

<main id="main" class="main">
    <div class="container">
        <h1>Edit Kontrak Pegawai</h1>
        <form method="post" action="update_kontrak.php">
            <input type="hidden" name="id" value="<?= htmlspecialchars($contract['id']) ?>">
            <div class="mb-3">
                <label for="pegawai_id" class="form-label">ID Pegawai</label>
                <input type="number" class="form-control" id="pegawai_id" name="pegawai_id" value="<?= htmlspecialchars($contract['pegawai_id']) ?>" required>
            </div>
            <div class="mb-3">
                <label for="nomor_kontrak" class="form-label">Nomor Kontrak</label>
                <input type="text" class="form-control" id="nomor_kontrak" name="nomor_kontrak" value="<?= htmlspecialchars($contract['nomor_kontrak']) ?>" required>
            </div>
            <div class="mb-3">
                <label for="tanggal_mulai" class="form-label">Tanggal Mulai Kontrak</label>
                <input type="date" class="form-control" id="tanggal_mulai" name="tanggal_mulai" value="<?= htmlspecialchars($contract['tanggal_mulai']) ?>" required>
            </div>
            <div class="mb-3">
                <label for="tanggal_berakhir" class="form-label">Tanggal Berakhir Kontrak</label>
                <input type="date" class="form-control" id="tanggal_berakhir" name="tanggal_berakhir" value="<?= htmlspecialchars($contract['tanggal_berakhir']) ?>" required>
            </div>
            <div class="mb-3">
                <label for="jabatan" class="form-label">Jabatan</label>
                <input type="text" class="form-control" id="jabatan" name="jabatan" value="<?= htmlspecialchars($contract['jabatan']) ?>" required>
            </div>
            <div class="mb-3">
                <label for="gaji" class="form-label">Gaji Bulanan</label>
                <input type="text" class="form-control" id="gaji" name="gaji" value="<?= htmlspecialchars($contract['gaji']) ?>" required>
            </div>
            <div class="mb-3">
                <label for="status_kontrak" class="form-label">Status Kontrak</label>
                <select class="form-select" id="status_kontrak" name="status_kontrak" required>
                    <option value="Aktif" <?= $contract['status_kontrak'] == 'Aktif' ? 'selected' : '' ?>>Aktif</option>
                    <option value="Non-Aktif" <?= $contract['status_kontrak'] == 'Non-Aktif' ? 'selected' : '' ?>>Non-Aktif</option>
                </select>
            </div>
            <button type="submit" class="btn btn-primary">Update Kontrak</button>
        </form>
    </div>
</main>

<?php
include '../template/footer.php';
?>
