<?php


include '../template/header.php';
include '../template/sidebar.php';
include '../connection.php';

if (!isset($_GET['id'])) {
    header("Location: index.php");
    exit();
}

$id_peg = $_GET['id'];

// Fetch data pegawai berdasarkan ID
$query = "SELECT * FROM `pegawai` WHERE `id_peg` = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $id_peg);
$stmt->execute();
$result = $stmt->get_result();
$pegawai = $result->fetch_assoc();

if (!$pegawai) {
    echo "<script>alert('Data pegawai tidak ditemukan!'); window.location.href='index.php';</script>";
    exit();
}
?>

<main id="main" class="main">
    <div class="container">
        <h1>Edit Pegawai</h1>
        <form method="POST" action="update_pegawai.php">
            <input type="hidden" name="id_peg" value="<?= $pegawai['id_peg'] ?>">
            <div class="mb-3">
                <label for="nama_pegawai" class="form-label">Nama Pegawai</label>
                <input type="text" class="form-control" id="nama_pegawai" name="nama_pegawai" value="<?= $pegawai['nama_pegawai'] ?>" required>
            </div>
            <div class="mb-3">
                <label for="jabatan" class="form-label">Jabatan</label>
                <input type="text" class="form-control" id="jabatan" name="jabatan" value="<?= $pegawai['jabatan'] ?>" required>
            </div>
            <div class="mb-3">
                <label for="departemen" class="form-label">Departemen</label>
                <input type="text" class="form-control" id="departemen" name="departemen" value="<?= $pegawai['departemen'] ?>" required>
            </div>
            <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
            <a href="index.php" class="btn btn-secondary">Batal</a>
        </form>
    </div>
</main>

<?php
include '../template/footer.php';
?>