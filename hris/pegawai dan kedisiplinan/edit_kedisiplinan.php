<?php
error_reporting(E_ALL); // Menampilkan semua jenis error
ini_set('display_errors', 1); // Mengaktifkan tampilan error

include '../template/header.php';
include '../template/sidebar.php';
include '../connection.php';

if (!isset($_GET['id'])) {
    header("Location: index.php");
    exit();
}

$id_kedisiplinan = $_GET['id'];

// Fetch data kedisiplinan berdasarkan ID
$query = "SELECT k.*, p.nama_pegawai FROM `kedisiplinan` k 
          JOIN `pegawai` p ON k.id_peg = p.id_peg 
          WHERE k.id_kedisiplinan = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $id_kedisiplinan);
$stmt->execute();
$result = $stmt->get_result();
$kedisiplinan = $result->fetch_assoc();

if (!$kedisiplinan) {
    echo "<script>alert('Data kedisiplinan tidak ditemukan!'); window.location.href='index.php';</script>";
    exit();
}

// Fetch data pegawai untuk dropdown
$pegawai = $conn->query("SELECT * FROM `pegawai`");
?>

<main id="main" class="main">
    <div class="container">
        <h1>Edit Riwayat Kedisiplinan</h1>
        <form method="POST" action="update_kedisiplinan.php">
            <input type="hidden" name="id_kedisiplinan" value="<?= $kedisiplinan['id_kedisiplinan'] ?>">
            <div class="mb-3">
                <label for="id_peg" class="form-label">Nama Pegawai</label>
                <select name="id_peg" id="id_peg" class="form-control" required>
                    <?php while ($p = $pegawai->fetch_assoc()): ?>
                        <option value="<?= $p['id_peg'] ?>" <?= ($p['id_peg'] == $kedisiplinan['id_peg']) ? 'selected' : '' ?>>
                            <?= $p['nama_pegawai'] ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div class="mb-3">
                <label for="jenis_pelanggaran" class="form-label">Jenis Pelanggaran</label>
                <input type="text" name="jenis_pelanggaran" id="jenis_pelanggaran" class="form-control" value="<?= $kedisiplinan['jenis_pelanggaran'] ?>" required>
            </div>
            <div class="mb-3">
                <label for="sanksi" class="form-label">Sanksi</label>
                <input type="text" name="sanksi" id="sanksi" class="form-control" value="<?= $kedisiplinan['sanksi'] ?>" required>
            </div>
            <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
            <a href="index.php" class="btn btn-secondary">Batal</a>
        </form>
    </div>
</main>

<?php
include '../template/footer.php';
?>