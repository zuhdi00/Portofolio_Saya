<?php
include '../template/header.php';
include '../template/sidebar.php';
include '../connection.php';

// Ambil ID promosi dari URL
$id_promosi = $_GET['id'];

// Ambil data promosi yang akan diedit
$query = mysqli_query($conn, "SELECT * FROM promosi WHERE id_promosi = '$id_promosi'");
$data = mysqli_fetch_assoc($query);

// Ambil data pegawai untuk dropdown
$queryPegawai = mysqli_query($conn, "SELECT * FROM pegawai");

// Ambil data jabatan untuk dropdown
$queryJabatan = mysqli_query($conn, "SELECT * FROM jabatan");

// Proses update data
if(isset($_POST['submit'])) {
    $id_peg = $_POST['id_peg'];
    $id_jabatan = $_POST['id_jabatan'];
    $tgl_promosi = $_POST['tgl_promosi'];
    $alasan_promosi = $_POST['alasan_promosi'];
    $status_promosi = $_POST['status_promosi'];

    $updateQuery = mysqli_query($conn, "UPDATE promosi SET 
        id_peg = '$id_peg',
        id_jabatan = '$id_jabatan',
        tgl_promosi = '$tgl_promosi',
        alasan_promosi = '$alasan_promosi',
        status_promosi = '$status_promosi'
        WHERE id_promosi = '$id_promosi'");

    if($updateQuery) {
        echo "<script>
            alert('Data promosi berhasil diupdate!');
            window.location.href = 'index.php';
        </script>";
    } else {
        echo "<script>
            alert('Gagal mengupdate data promosi!');
        </script>";
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Edit Promosi</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .content-wrapper {
            margin-top: 80px;
            margin-left: 260px;
            padding: 30px;
            background-color: #f8f9fa;
        }
        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 0 15px rgba(0,0,0,0.1);
        }
        .card-header {
            background-color: #fff;
            border-bottom: 1px solid #eee;
            padding: 20px;
            border-radius: 15px 15px 0 0;
        }
    </style>
</head>
<body>
    <div class="content-wrapper">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Edit Data Promosi</h3>
            </div>
            <div class="card-body">
                <form method="POST" action="">
                    <div class="mb-3">
                        <label class="form-label">Pegawai</label>
                        <select name="id_peg" class="form-select" required>
                            <?php while($peg = mysqli_fetch_assoc($queryPegawai)) { ?>
                                <option value="<?= $peg['id_peg'] ?>" <?= ($peg['id_peg'] == $data['id_peg']) ? 'selected' : '' ?>>
                                    <?= $peg['nama_peg'] ?>
                                </option>
                            <?php } ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Jabatan Baru</label>
                        <select name="id_jabatan" class="form-select" required>
                            <?php while($jab = mysqli_fetch_assoc($queryJabatan)) { ?>
                                <option value="<?= $jab['id_jabatan'] ?>" <?= ($jab['id_jabatan'] == $data['id_jabatan']) ? 'selected' : '' ?>>
                                    <?= $jab['nama_jabatan'] ?>
                                </option>
                            <?php } ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Tanggal Promosi</label>
                        <input type="date" name="tgl_promosi" class="form-control" value="<?= $data['tgl_promosi'] ?>" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Alasan Promosi</label>
                        <textarea name="alasan_promosi" class="form-control" rows="3" required><?= $data['alasan_promosi'] ?></textarea>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Status</label>
                        <select name="status_promosi" class="form-select" required>
                            <option value="Pending" <?= ($data['status_promosi'] == 'Pending') ? 'selected' : '' ?>>Pending</option>
                            <option value="Di Setujui" <?= ($data['status_promosi'] == 'Di Setujui') ? 'selected' : '' ?>>Di Setujui</option>
                            <option value="Di Tolak" <?= ($data['status_promosi'] == 'Di Tolak') ? 'selected' : '' ?>>Di Tolak</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <button type="submit" name="submit" class="btn btn-primary">Update</button>
                        <a href="index.php" class="btn btn-secondary">Kembali</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</body>
</html>

<?php include '../template/footer.php'; ?> 