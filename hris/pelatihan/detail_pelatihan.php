<?php
require_once 'koneksi.php';

// Cek apakah ada ID yang dikirim
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header('Location: index.php');
    exit;
}

$id_pelatihan = $_GET['id'];

// Query untuk mengambil detail pelatihan
$query = "SELECT * FROM pelatihan WHERE id_pelatihan = ?";
$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, 'i', $id_pelatihan);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if (mysqli_num_rows($result) == 0) {
    header('Location: index.php');
    exit;
}

$data = mysqli_fetch_assoc($result);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detail Pelatihan</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        .detail-label {
            font-weight: bold;
            color: #555;
        }
        .status-badge {
            padding: 5px 15px;
            border-radius: 15px;
            font-size: 0.9em;
        }
    </style>
</head>
<body class="bg-light">
    <div class="container py-4">
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h5 class="card-title mb-0">
                    <i class="fas fa-info-circle"></i> Detail Pelatihan
                </h5>
            </div>
            <div class="card-body">
                <div class="row mb-4">
                    <div class="col-md-6">
                        <h4><?= htmlspecialchars($data['nama_pelatihan']) ?></h4>
                        <span class="badge <?= $data['status_pelatihan'] == 'Terlaksana' ? 'bg-success' : 'bg-danger' ?> status-badge">
                            <?= $data['status_pelatihan'] ?>
                        </span>
                    </div>
                    <div class="col-md-6 text-md-end">
                        <p class="mb-1">
                            <i class="fas fa-calendar"></i> 
                            <?= date('d F Y', strtotime($data['tgl_pelaksanaan'])) ?>
                        </p>
                        <p class="mb-0">
                            <i class="fas fa-clock"></i> 
                            <?= date('H:i', strtotime($data['jam_pelaksanaan'])) ?> WIB
                        </p>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <table class="table table-borderless">
                            <tr>
                                <td class="detail-label" width="40%">Deskripsi</td>
                                <td>: <?= htmlspecialchars($data['deskripsi_pelatihan']) ?></td>
                            </tr>
                            <tr>
                                <td class="detail-label">Durasi</td>
                                <td>: <?= htmlspecialchars($data['durasi_pelatihan']) ?></td>
                            </tr>
                            <tr>
                                <td class="detail-label">Lokasi</td>
                                <td>: <?= htmlspecialchars($data['lokasi_pelatihan']) ?></td>
                            </tr>
                            <tr>
                                <td class="detail-label">Pemateri</td>
                                <td>: <?= htmlspecialchars($data['pemateri_pelatihan']) ?></td>
                            </tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <table class="table table-borderless">
                            <tr>
                                <td class="detail-label" width="40%">Kapasitas</td>
                                <td>: <?= htmlspecialchars($data['kapasitas']) ?></td>
                            </tr>
                            <tr>
                                <td class="detail-label">ID Pegawai</td>
                                <td>: <?= $data['id_pegawai'] ?? '-' ?></td>
                            </tr>
                            <tr>
                                <td class="detail-label">ID Pelatihan</td>
                                <td>: <?= $data['id_pelatihan'] ?></td>
                            </tr>
                        </table>
                    </div>
                </div>

                <hr>

                <div class="d-flex justify-content-between">
                    <a href="index.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Kembali
                    </a>
                    <div>
                        <a href="edit_pelatihan.php?id=<?= $data['id_pelatihan'] ?>" class="btn btn-warning">
                            <i class="fas fa-edit"></i> Edit
                        </a>
                        <button type="button" class="btn btn-danger" onclick="konfirmasiHapus(<?= $data['id_pelatihan'] ?>)">
                            <i class="fas fa-trash"></i> Hapus
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <script>
    function konfirmasiHapus(id) {
        Swal.fire({
            title: 'Konfirmasi Hapus',
            text: "Apakah Anda yakin ingin menghapus data ini?",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Ya, Hapus!',
            cancelButtonText: 'Batal'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = 'hapus_pelatihan.php?id=' + id;
            }
        });
    }
    </script>
</body>
</html> 