<?php
include '../template/header.php';
include '../template/sidebar.php';
include '../connection.php';

// Fetch Pegawai Data
function getPegawai() {
    global $conn;
    $result = $conn->query("SELECT * FROM `pegawai`");
    return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
}

$pegawai = getPegawai();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $id_peg = $_POST['id_peg'];
    $jenis_pelanggaran = $_POST['jenis_pelanggaran'];
    $sanksi = $_POST['sanksi'];

    $stmt = $conn->prepare("INSERT INTO kedisiplinan (id_peg, jenis_pelanggaran, sanksi) VALUES (?, ?, ?)");
    $stmt->bind_param("iss", $id_peg, $jenis_pelanggaran, $sanksi);

    if ($stmt->execute()) {
        echo "<script>alert('Data kedisiplinan berhasil ditambahkan!'); window.location.href='index.php';</script>";
    } else {
        echo "<script>alert('Gagal menambahkan data!');</script>";
    }
    
    $stmt->close();
    $conn->close();
}
?>

<main id="main" class="main">
    <div class="container">
        <h1>Tambah Riwayat Kedisiplinan</h1>
        <form method="POST" action="">
            <div class="mb-3">
                <label for="id_peg" class="form-label">Nama Pegawai</label>
                <select name="id_peg" id="id_peg" class="form-control" required>
                    <option value="">Pilih Pegawai</option>
                    <?php foreach ($pegawai as $p): ?>
                        <option value="<?= $p['id_peg'] ?>"><?= $p['nama_pegawai'] ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="mb-3">
                <label for="jenis_pelanggaran" class="form-label">Jenis Pelanggaran</label>
                <input type="text" name="jenis_pelanggaran" id="jenis_pelanggaran" class="form-control" required>
            </div>
            <div class="mb-3">
                <label for="sanksi" class="form-label">Sanksi</label>
                <input type="text" name="sanksi" id="sanksi" class="form-control" required>
            </div>
            <button type="submit" class="btn btn-primary">Simpan</button>
            <a href="index.php" class="btn btn-secondary">Batal</a>
        </form>
    </div>
</main>

<?php
include '../template/footer.php';
?>