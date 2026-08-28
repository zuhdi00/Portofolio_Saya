<?php
include '../template/header.php';
include '../template/sidebar.php';
// Tambahkan error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Koneksi database
require_once 'koneksi.php';

// Debug koneksi
if ($conn->connect_error) {
    die("Koneksi database gagal: " . $conn->connect_error);
}

// Query untuk mengambil semua data pelatihan
$query = "SELECT * FROM pelatihan ";
$result = mysqli_query($conn, $query);

// Cek apakah query berhasil
if (!$result) {
    die("Query error: " . mysqli_error($conn));
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Data Pelatihan</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
    <!-- DataTables CSS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css">
    
    <style>
        body {
            font-family: 'Poppins', sans-serif;
            background-color: #f0f2f5;
        }
        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
        }
        .card-header {
            background: linear-gradient(135deg, #0d6efd 0%, #0099ff 100%);
            border-radius: 15px 15px 0 0 !important;
            padding: 1.2rem;
        }
        .btn-add {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            border: none;
            padding: 8px 20px;
            border-radius: 8px;
            transition: all 0.3s ease;
        }
        .btn-add:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(40, 167, 69, 0.3);
        }
        .table {
            vertical-align: middle;
        }
        .table thead th {
            background-color: #f8f9fa;
            font-weight: 600;
            border-bottom: 2px solid #dee2e6;
        }
        .status-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.85em;
            font-weight: 500;
            letter-spacing: 0.3px;
        }
        .action-buttons .btn {
            padding: 5px 10px;
            margin: 0 2px;
            border-radius: 6px;
            transition: all 0.2s;
        }
        .action-buttons .btn:hover {
            transform: translateY(-2px);
        }
        .btn-detail {
            background-color: #17a2b8;
            border: none;
        }
        .btn-edit {
            background-color: #ffc107;
            border: none;
        }
        .btn-delete {
            background-color: #dc3545;
            border: none;
        }
        .table-hover tbody tr:hover {
            background-color: #f8f9fa;
            transition: all 0.2s;
        }
        .empty-state {
            text-align: center;
            padding: 40px 0;
            color: #6c757d;
        }
        .empty-state i {
            font-size: 48px;
            margin-bottom: 15px;
        }
        .dataTables_wrapper .dataTables_paginate .paginate_button {
            padding: 5px 10px;
            margin: 0 2px;
            border-radius: 5px;
        }
        .search-box {
            border-radius: 20px;
            padding: 8px 15px;
            border: 1px solid #ced4da;
            transition: all 0.3s;
        }
        .search-box:focus {
            box-shadow: 0 0 0 0.2rem rgba(13, 110, 253, 0.15);
            border-color: #0d6efd;
        }
        .content-wrapper {
            margin-left: 260px;
            margin-top: 70px;
            padding: 15px 25px;
            transition: margin-left .3s ease-in-out;
        }
        
        .container-fluid {
            padding-left: 20px;
            padding-right: 20px;
            max-width: 100%;
        }
        
        @media (max-width: 768px) {
            .content-wrapper {
                margin-left: 0;
                padding: 15px;
            }
            .container-fluid {
                padding-left: 15px;
                padding-right: 15px;
            }
        }
        
        .card {
            margin-bottom: 20px;
            background: #fff;
        }
        
        .table-responsive {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }
        
        .table-responsive::-webkit-scrollbar {
            height: 8px;
        }
        
        .table-responsive::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 4px;
        }
        
        .table-responsive::-webkit-scrollbar-thumb {
            background: #888;
            border-radius: 4px;
        }
        
        .table-responsive::-webkit-scrollbar-thumb:hover {
            background: #555;
        }
    </style>
</head>
<body>
    <!-- Content Wrapper -->
    <div class="content-wrapper">
        <!-- Main Content -->
        <div class="container-fluid">
            <!-- Breadcrumb -->
            <nav aria-label="breadcrumb" class="mb-4">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="../index.php">Dashboard</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Data Pelatihan</li>
                </ol>
            </nav>

            <!-- Page Content -->
            <div class="card">
                <div class="card-header">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h4 class="text-white mb-0">
                                <i class="fas fa-chalkboard-teacher me-2"></i>Data Pelatihan
                            </h4>
                            <small class="text-white-50">Kelola data pelatihan karyawan</small>
                        </div>
                        <a href="tambah_pelatihan.php" class="btn btn-add text-white">
                            <i class="fas fa-plus-circle me-2"></i>Tambah Pelatihan
                        </a>
                    </div>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover" id="tabelPelatihan">
                            <thead>
                                <tr>
                                    <th>No</th>
                                    <th>Nama Pelatihan</th>
                                    <th>Deskripsi</th>
                                    <th>Tanggal</th>
                                    <th>Waktu</th>
                                    <th>Durasi</th>
                                    <th>Lokasi</th>
                                    <th>Pemateri</th>
                                    <th>Status</th>
                                    <th>Kapasitas</th>
                                    <th class="text-center">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $no = 1;
                                while ($row = mysqli_fetch_assoc($result)) : 
                                ?>
                                <tr>
                                    <td><?= $no++ ?></td>
                                    <td><?= htmlspecialchars($row['nama_pelatihan']) ?></td>
                                    <td><?= htmlspecialchars($row['deskripsi_pelatihan']) ?></td>
                                    <td><?= date('d/m/Y', strtotime($row['tgl_pelatihan'])) ?></td>
                                    <td><?= date('H:i', strtotime($row['jam_pelatihan'])) ?></td>
                                    <td><?= htmlspecialchars($row['durasi_pelatihan']) ?></td>
                                    <td><?= htmlspecialchars($row['lokasi_pelatihan']) ?></td>
                                    <td><?= htmlspecialchars($row['pemateri_pelatihan']) ?></td>
                                    <td>
                                        <span class="badge <?php 
                                            echo match($row['status_pelatihan']) {
                                                'Terlaksana' => 'bg-success',
                                                'Belum Terlaksana' => 'bg-warning',
                                                'Gagal Terlaksana' => 'bg-danger',
                                                default => 'bg-secondary'
                                            };
                                        ?>">
                                            <?= htmlspecialchars($row['status_pelatihan']) ?>
                                        </span>
                                    </td>
                                    <td><?= htmlspecialchars($row['kapasitas']) ?></td>
                                    <td>
                                        <div class="action-buttons d-flex justify-content-center">
                                            <a href="detail_pelatihan.php?id=<?= $row['id_pelatihan'] ?>" 
                                               class="btn btn-detail text-white" title="Detail">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <a href="edit_pelatihan.php?id=<?= $row['id_pelatihan'] ?>" 
                                               class="btn btn-edit" title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <button type="button" 
                                                    class="btn btn-delete text-white" 
                                                    onclick="konfirmasiHapus(<?= $row['id_pelatihan'] ?>)" 
                                                    title="Hapus">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <!-- DataTables JS -->
    <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <script>
    $(document).ready(function() {
        $('#tabelPelatihan').DataTable({
            language: {
                url: '//cdn.datatables.net/plug-ins/1.11.5/i18n/id.json'
            },
            pageLength: 10,
            ordering: true,
            responsive: true,
            dom: '<"d-flex justify-content-between align-items-center mb-3"lf>rt<"d-flex justify-content-between align-items-center mt-3"ip>',
            lengthMenu: [[5, 10, 25, 50, -1], [5, 10, 25, 50, "Semua"]]
        });
    });

    function konfirmasiHapus(id) {
        Swal.fire({
            title: 'Konfirmasi Hapus',
            text: "Apakah Anda yakin ingin menghapus data ini?",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc3545',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Ya, Hapus!',
            cancelButtonText: 'Batal',
            reverseButtons: true
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = 'hapus_pelatihan.php?id=' + id;
            }
        });
    }
    </script>
</body>
</html>
