<?php
include '../template/header.php';
include '../template/sidebar.php';
include '../connection.php'; 

// Mengambil data pegawai untuk dropdown
$queryPegawai = mysqli_query($conn, "SELECT id_peg, nama_peg FROM pegawai");

// Mengambil data jabatan untuk dropdown
$queryJabatan = mysqli_query($conn, "SELECT id_jabatan, nama_jabatan FROM jabatan");

// Mengambil data departemen untuk dropdown
$queryDepartemen = mysqli_query($conn, "SELECT id_dep, nama_departemen FROM departemen");

// Proses tambah data
if (isset($_POST['submit'])) {
    $id_peg        = $_POST['id_peg'];
    $id_jabatan    = $_POST['id_jabatan'];
    $id_dep        = $_POST['id_dep'];
    $tgl_mutasi    = $_POST['tgl_mutasi'];
    $alasan_mutasi = $_POST['alasan_mutasi'];
    $status_mutasi = 'Menunggu'; // Default status

    $query = "INSERT INTO mutasi (id_peg, id_jabatan, id_dep, tgl_mutasi, alasan_mutasi, status_mutasi) 
              VALUES ('$id_peg', '$id_jabatan', '$id_dep', '$tgl_mutasi', '$alasan_mutasi', '$status_mutasi')";
    
    if (mysqli_query($conn, $query)) {
        echo "<script>
                alert('Data mutasi berhasil ditambahkan');
                window.location.href = 'index.php';
              </script>";
    } else {
        echo "<script>
                alert('Gagal menambahkan data mutasi: " . mysqli_error($conn) . "');
              </script>";
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Tambah Data Mutasi</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .content-wrapper {
            margin-top: 80px;
            margin-left: 260px;
            padding: 20px;
            background-color: #f4f6f9;
        }
        .card {
            max-width: 800px;
            margin: 0 auto;
            box-shadow: 0 0 1px rgba(0,0,0,.125), 0 1px 3px rgba(0,0,0,.2);
        }
        .card-header {
            background-color: #fff;
            border-bottom: 1px solid rgba(0,0,0,.125);
            padding: 15px;
        }
        .card-body {
            padding: 20px;
            background-color: #fff;
        }
        @media (max-width: 768px) {
            .content-wrapper {
                margin-left: 0;
                padding: 15px;
            }
        }
    </style>
</head>
<body>
    <div class="content-wrapper">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Tambah Data Mutasi</h3>
            </div>
            <div class="card-body">
                <form method="POST" action="">
                    <div class="mb-3">
                        <label for="id_peg" class="form-label">Pegawai</label>
                        <select class="form-select" name="id_peg" required>
                            <option value="">Pilih Pegawai</option>
                            <?php while ($peg = mysqli_fetch_assoc($queryPegawai)) { ?>
                                <option value="<?= $peg['id_peg'] ?>"><?= htmlspecialchars($peg['nama_peg']) ?></option>
                            <?php } ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="id_jabatan" class="form-label">Jabatan Baru</label>
                        <select class="form-select" name="id_jabatan" required>
                            <option value="">Pilih Jabatan</option>
                            <?php while ($jab = mysqli_fetch_assoc($queryJabatan)) { ?>
                                <option value="<?= $jab['id_jabatan'] ?>"><?= htmlspecialchars($jab['nama_jabatan']) ?></option>
                            <?php } ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="id_dep" class="form-label">Departemen Baru</label>
                        <select class="form-select" name="id_dep" required>
                            <option value="">Pilih Departemen</option>
                            <?php while ($dep = mysqli_fetch_assoc($queryDepartemen)) { ?>
                                <option value="<?= $dep['id_dep'] ?>"><?= htmlspecialchars($dep['nama_departemen']) ?></option>
                            <?php } ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="tgl_mutasi" class="form-label">Tanggal Mutasi</label>
                        <input type="date" class="form-control" name="tgl_mutasi" required>
                    </div>

                    <div class="mb-3">
                        <label for="alasan_mutasi" class="form-label">Alasan Mutasi</label>
                        <textarea class="form-control" name="alasan_mutasi" rows="3" required></textarea>
                    </div>

                    <div class="mb-3">
                        <button type="submit" name="submit" class="btn btn-primary">Simpan</button>
                        <a href="index.php" class="btn btn-secondary">Kembali</a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

<?php include '../template/footer.php'; ?> 
