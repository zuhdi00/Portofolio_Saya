<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

include '../config/koneksi_sqlsrv.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Fetching data from the form
    $nama_lowongan = $_POST['nama_lowongan'];
    $deskripsi_lowongan = $_POST['deskripsi_lowongan'];
    $id_jabatan = $_POST['id_jabatan'];
    $tgl_posting = $_POST['tgl_posting'];
    $tgl_tutup = $_POST['tgl_tutup'];
    $status_lowongan = $_POST['status_lowongan'];

    // Pastikan nilai status adalah "Tersedia" atau "Tidak Tersedia"
    if ($status_lowongan !== 'Tersedia' && $status_lowongan !== 'Tidak Tersedia') {
        echo "Error: Invalid status value.";
        exit();
    }

    // Periksa apakah id_jabatan ada dalam tabel jabatan
    $stmt = sqlsrv_query($conn, "SELECT id_jabatan FROM dbo.jabatan WHERE id_jabatan = ?", [$id_jabatan]);
    $result = $stmt ? sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC) : null;

    if (!$result) {
        echo "Error: Invalid id_jabatan value.";
        exit();
    }

    // Insert the job vacancy data into the database
    $query = "INSERT INTO dbo.lowongan (nama_lowongan, deskripsi_lowongan, id_jabatan, tgl_posting, tgl_tutup, status_lowongan) 
              VALUES (?, ?, ?, ?, ?, ?)";
    $stmt = sqlsrv_query($conn, $query, [$nama_lowongan, $deskripsi_lowongan, $id_jabatan, $tgl_posting, $tgl_tutup, $status_lowongan]);

    if ($stmt !== false) {
        // Redirect or display a success message
        header("Location: index.php?status=success");
        exit();
    } else {
        echo "Error: " . $stmt->error;
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tambah Lowongan</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
        .content-wrapper {
            margin-left: 260px; /* Adjust this value based on your sidebar width */
            padding: 20px;
            transition: margin-left .3s ease-in-out;
        }
        
        @media (max-width: 768px) {
            .content-wrapper {
                margin-left: 0;
                padding: 15px;
            }
        }

        .card-header h4 {
            color: #007bff; /* Warna teks */
        }
    </style>
</head>
<body>
    <div class="content-wrapper">
        <div class="container-fluid">
            <!-- Breadcrumb -->
            <nav aria-label="breadcrumb" class="mb-4">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="index.php">Dashboard</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Tambah Lowongan</li>
                </ol>
            </nav>

            <!-- Page Content -->
            <div class="card">
                <div class="card-header">
                    <h4 class="mb-0">Tambah Lowongan</h4>
                </div>
                <div class="card-body">
                    <form method="post" action="">
                        <div class="mb-3">
                            <label for="nama_lowongan" class="form-label">Nama Lowongan</label>
                            <input type="text" class="form-control" id="nama_lowongan" name="nama_lowongan" required>
                        </div>
                        <div class="mb-3">
                            <label for="deskripsi_lowongan" class="form-label">Deskripsi Lowongan</label>
                            <textarea class="form-control" id="deskripsi_lowongan" name="deskripsi_lowongan" rows="3" required></textarea>
                        </div>
                        <div class="mb-3">
                            <label for="id_jabatan" class="form-label">ID Jabatan</label>
                            <input type="text" class="form-control" id="id_jabatan" name="id_jabatan" required>
                        </div>
                        <div class="mb-3">
                            <label for="tgl_posting" class="form-label">Tanggal Posting</label>
                            <input type="date" class="form-control" id="tgl_posting" name="tgl_posting" required>
                        </div>
                        <div class="mb-3">
                            <label for="tgl_tutup" class="form-label">Tanggal Tutup</label>
                            <input type="date" class="form-control" id="tgl_tutup" name="tgl_tutup" required>
                        </div>
                        <div class="mb-3">
                            <label for="status_lowongan" class="form-label">Status Lowongan</label>
                            <select class="form-control" id="status_lowongan" name="status_lowongan" required>
                                <option value="Tersedia">Tersedia</option>
                                <option value="Tidak Tersedia">Tidak Tersedia</option>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-primary">Submit</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>