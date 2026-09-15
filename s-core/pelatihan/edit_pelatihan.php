<?php
require_once 'koneksi.php';

// Inisialisasi pesan
$error = '';
$success = '';

// Ambil data pelatihan yang akan diedit
if (isset($_GET['id'])) {
    $id = $_GET['id'];
    $query = "SELECT * FROM pelatihan WHERE id_pelatihan = ?";
    if ($stmt = mysqli_prepare($conn, $query)) {
        mysqli_stmt_bind_param($stmt, "i", $id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $data = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);
    }
}

// Proses form jika ada POST request
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        $id = $_POST['id_pelatihan'];
        
        // Update data
        $query = "UPDATE pelatihan SET 
            nama_pelatihan = ?,
            deskripsi_pelatihan = ?,
            tgl_pelatihan = ?,
            jam_pelatihan = ?,
            durasi_pelatihan = ?,
            lokasi_pelatihan = ?,
            pemateri_pelatihan = ?,
            id_peg = ?,
            kapasitas = ?,
            status_pelatihan = ?
            WHERE id_pelatihan = ?";

        if ($stmt = mysqli_prepare($conn, $query)) {
            $id_peg = !empty($_POST['id_peg']) ? $_POST['id_peg'] : null;
            
            mysqli_stmt_bind_param($stmt, "ssssssssssi",
                $_POST['nama_pelatihan'],
                $_POST['deskripsi_pelatihan'],
                $_POST['tgl_pelatihan'],
                $_POST['jam_pelatihan'],
                $_POST['durasi_pelatihan'],
                $_POST['lokasi_pelatihan'],
                $_POST['pemateri_pelatihan'],
                $id_peg,
                $_POST['kapasitas'],
                $_POST['status_pelatihan'],
                $id
            );

            if (mysqli_stmt_execute($stmt)) {
                $success = "Data pelatihan berhasil diupdate!";
                header("Location: index.php");
                exit();
            } else {
                throw new Exception(mysqli_stmt_error($stmt));
            }
            mysqli_stmt_close($stmt);
        } else {
            throw new Exception(mysqli_error($conn));
        }
    } catch (Exception $e) {
        $error = "Error: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Pelatihan</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .content-wrapper {
            margin-left: 260px;
            margin-top: 70px;
            padding: 15px 25px;
        }
    </style>
</head>
<body class="bg-light">
    <div class="content-wrapper">
        <div class="container-fluid">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Edit Pelatihan</h5>
                </div>
                <div class="card-body">
                    <?php if($error): ?>
                        <div class="alert alert-danger"><?= $error ?></div>
                    <?php endif; ?>
                    <?php if($success): ?>
                        <div class="alert alert-success"><?= $success ?></div>
                    <?php endif; ?>

                    <form method="POST">
                        <input type="hidden" name="id_pelatihan" value="<?= $data['id_pelatihan'] ?>">
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Nama Pelatihan</label>
                                <input type="text" name="nama_pelatihan" class="form-control" value="<?= $data['nama_pelatihan'] ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Deskripsi Pelatihan</label>
                                <input type="text" name="deskripsi_pelatihan" class="form-control" value="<?= $data['deskripsi_pelatihan'] ?>" required>
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-4">
                                <label class="form-label">Tanggal Pelatihan</label>
                                <input type="date" name="tgl_pelatihan" class="form-control" value="<?= $data['tgl_pelatihan'] ?>" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Jam Pelatihan</label>
                                <input type="time" name="jam_pelatihan" class="form-control" value="<?= $data['jam_pelatihan'] ?>" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Durasi Pelatihan</label>
                                <input type="text" name="durasi_pelatihan" class="form-control" value="<?= $data['durasi_pelatihan'] ?>" required>
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Lokasi Pelatihan</label>
                                <input type="text" name="lokasi_pelatihan" class="form-control" value="<?= $data['lokasi_pelatihan'] ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Pemateri Pelatihan</label>
                                <input type="text" name="pemateri_pelatihan" class="form-control" value="<?= $data['pemateri_pelatihan'] ?>" required>
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-4">
                                <label class="form-label">Status Pelatihan</label>
                                <select name="status_pelatihan" class="form-select" required>
                                    <option value="Belum Terlaksana" <?= $data['status_pelatihan'] == 'Belum Terlaksana' ? 'selected' : '' ?>>Belum Terlaksana</option>
                                    <option value="Terlaksana" <?= $data['status_pelatihan'] == 'Terlaksana' ? 'selected' : '' ?>>Terlaksana</option>
                                    <option value="Gagal Terlaksana" <?= $data['status_pelatihan'] == 'Gagal Terlaksana' ? 'selected' : '' ?>>Gagal Terlaksana</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">ID Pegawai</label>
                                <input type="number" name="id_peg" class="form-control" value="<?= $data['id_peg'] ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Kapasitas</label>
                                <input type="text" name="kapasitas" class="form-control" value="<?= $data['kapasitas'] ?>" required>
                            </div>
                        </div>

                        <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                            <a href="index.php" class="btn btn-secondary">Kembali</a>
                            <button type="submit" class="btn btn-primary">Update</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 