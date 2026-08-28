<?php
require_once 'koneksi.php';

// Tampilkan error
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Inisialisasi pesan
$error = '';
$success = '';

// Debug: Tampilkan struktur tabel
$result = mysqli_query($conn, "DESCRIBE pelatihan");
echo "<pre>Struktur Tabel:\n";
while ($row = mysqli_fetch_assoc($result)) {
    print_r($row);
}
echo "</pre>";

// Proses form jika ada POST request
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        // Ambil data dari form
        $nama_pelatihan = mysqli_real_escape_string($conn, $_POST['nama_pelatihan'] ?? '');
        $deskripsi_pelatihan = mysqli_real_escape_string($conn, $_POST['deskripsi_pelatihan'] ?? '');
        $tgl_pelatihan = mysqli_real_escape_string($conn, $_POST['tgl_pelatihan'] ?? '');
        $jam_pelatihan = mysqli_real_escape_string($conn, $_POST['jam_pelatihan'] ?? '');
        $durasi_pelatihan = mysqli_real_escape_string($conn, $_POST['durasi_pelatihan'] ?? '');
        $lokasi_pelatihan = mysqli_real_escape_string($conn, $_POST['lokasi_pelatihan'] ?? '');
        $pemateri_pelatihan = mysqli_real_escape_string($conn, $_POST['pemateri_pelatihan'] ?? '');
        $id_peg = !empty($_POST['id_peg']) ? intval($_POST['id_peg']) : 'NULL';
        $kapasitas = mysqli_real_escape_string($conn, $_POST['kapasitas'] ?? '');
        $status_pelatihan = mysqli_real_escape_string($conn, $_POST['status_pelatihan'] ?? '');

        // Query INSERT
        $query = "INSERT INTO pelatihan (
            nama_pelatihan, 
            deskripsi_pelatihan, 
            tgl_pelatihan, 
            jam_pelatihan, 
            durasi_pelatihan, 
            lokasi_pelatihan, 
            pemateri_pelatihan, 
            id_peg, 
            kapasitas, 
            status_pelatihan
        ) VALUES (
            '$nama_pelatihan',
            '$deskripsi_pelatihan',
            '$tgl_pelatihan',
            '$jam_pelatihan',
            '$durasi_pelatihan',
            '$lokasi_pelatihan',
            '$pemateri_pelatihan',
            $id_peg,
            '$kapasitas',
            '$status_pelatihan'
        )";

        // Debug: tampilkan query
        echo "<pre>Query yang akan dijalankan:\n$query</pre>";

        // Eksekusi query
        if (mysqli_query($conn, $query)) {
            $new_id = mysqli_insert_id($conn);
            $success = "Data pelatihan berhasil ditambahkan dengan ID: $new_id";
            echo "<pre>Data berhasil ditambahkan dengan ID: $new_id</pre>";
            header("refresh:2;url=index.php");
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
    <title>Tambah Pelatihan</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    
    <style>
        .card {
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
        }
        .form-label {
            font-weight: 500;
        }
        .required:after {
            content: " *";
            color: red;
        }
    </style>
</head>
<body class="bg-light">
    <div class="container py-4">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-plus-circle"></i> Tambah Pelatihan Baru
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if($error): ?>
                            <div class="alert alert-danger">
                                <i class="fas fa-exclamation-circle"></i> <?= $error ?>
                            </div>
                        <?php endif; ?>
                        
                        <?php if($success): ?>
                            <div class="alert alert-success">
                                <i class="fas fa-check-circle"></i> <?= $success ?>
                            </div>
                        <?php endif; ?>

                        <form method="POST" action="">
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label class="form-label">Nama Pelatihan</label>
                                    <input type="text" name="nama_pelatihan" class="form-control" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Deskripsi Pelatihan</label>
                                    <input type="text" name="deskripsi_pelatihan" class="form-control" required>
                                </div>
                            </div>

                            <div class="row mb-3">
                                <div class="col-md-4">
                                    <label class="form-label">Tanggal Pelatihan</label>
                                    <input type="date" name="tgl_pelatihan" class="form-control" required>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Jam Pelatihan</label>
                                    <input type="time" name="jam_pelatihan" class="form-control" required>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Durasi Pelatihan</label>
                                    <input type="text" name="durasi_pelatihan" class="form-control" placeholder="Contoh: 3 Jam" required>
                                </div>
                            </div>

                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label class="form-label">Lokasi Pelatihan</label>
                                    <input type="text" name="lokasi_pelatihan" class="form-control" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Pemateri Pelatihan</label>
                                    <input type="text" name="pemateri_pelatihan" class="form-control" required>
                                </div>
                            </div>

                            <div class="row mb-3">
                                <div class="col-md-4">
                                    <label class="form-label">Status Pelatihan</label>
                                    <select name="status_pelatihan" class="form-select" required>
                                        <option value="Belum Terlaksana">Belum Terlaksana</option>
                                        <option value="Terlaksana">Terlaksana</option>
                                        <option value="Gagal Terlaksana">Gagal Terlaksana</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">ID Pegawai</label>
                                    <input type="number" name="id_peg" class="form-control" min="1">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Kapasitas</label>
                                    <input type="text" name="kapasitas" class="form-control" placeholder="Contoh: 30 Orang" required>
                                </div>
                            </div>

                            <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                <a href="index.php" class="btn btn-secondary">
                                    <i class="fas fa-arrow-left"></i> Kembali
                                </a>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save"></i> Simpan
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 