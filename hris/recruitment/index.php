<?php
include '../template/header.php';
include '../template/sidebar.php';
include '../config/koneksi_sqlsrv.php';

// Tambahkan error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Fungsi untuk mendapatkan data dari tabel
function getData($table) {
    global $conn;
    $allowedTables = ['lowongan', 'departemen', 'jabatan', 'pelamar', 'tahap_lamaran', 'penilaian_pelamar', 'recruitment_inbox'];
    if (!in_array($table, $allowedTables, true)) {
        return [];
    }
    $result = sqlsrv_query($conn, "SELECT * FROM dbo.$table");
    if ($result === false) {
        return [];
    }
    $rows = [];
    while ($row = sqlsrv_fetch_array($result, SQLSRV_FETCH_ASSOC)) {
        $rows[] = $row;
    }
    return $rows;
}

// Mendapatkan data dari tabel
$lowongan = getData('lowongan');
$departemen = getData('departemen');
$jabatan = getData('jabatan');
$pelamar = getData('pelamar');
$tahap_lamaran = getData('tahap_lamaran');
$penilaian_pelamar = getData('penilaian_pelamar');
$recruitment_inbox = getData('recruitment_inbox');
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Data Recruitment</title>
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
    <div class="content-wrapper">
        <div class="container-fluid">
            <h1 class="text-center my-4 page-title">Recruitment</h1>
            <div class="text-end mb-3">
                <a href="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>" class="btn btn-outline-primary">
                    <i class="fas fa-sync-alt me-2"></i>Refresh Data
                </a>
            </div>
            <ul class="nav nav-tabs justify-content-center" id="recruitmentTab" role="tablist">
                <li class="nav-item" role="presentation">
                    <a class="nav-link active" id="lowongan-tab" data-bs-toggle="tab" href="#lowongan" role="tab" aria-controls="lowongan" aria-selected="true">Lowongan</a>
                </li>
                <li class="nav-item" role="presentation">
                    <a class="nav-link" id="departemen-tab" data-bs-toggle="tab" href="#departemen" role="tab" aria-controls="departemen" aria-selected="false">Departemen</a>
                </li>
                <li class="nav-item" role="presentation">
                    <a class="nav-link" id="jabatan-tab" data-bs-toggle="tab" href="#jabatan" role="tab" aria-controls="jabatan" aria-selected="false">Jabatan</a>
                </li>
                <li class="nav-item" role="presentation">
                    <a class="nav-link" id="pelamar-tab" data-bs-toggle="tab" href="#pelamar" role="tab" aria-controls="pelamar" aria-selected="false">Pelamar</a>
                </li>
                <li class="nav-item" role="presentation">
                    <a class="nav-link" id="tahap-lamaran-tab" data-bs-toggle="tab" href="#tahap-lamaran" role="tab" aria-controls="tahap-lamaran" aria-selected="false">Tahap Lamaran</a>
                </li>
                <li class="nav-item" role="presentation">
                    <a class="nav-link" id="penilaian-pelamar-tab" data-bs-toggle="tab" href="#penilaian-pelamar" role="tab" aria-controls="penilaian-pelamar" aria-selected="false">Penilaian Pelamar</a>
                </li>
                <li class="nav-item" role="presentation">
                    <a class="nav-link" id="inbox-tab" data-bs-toggle="tab" href="#inbox" role="tab" aria-controls="inbox" aria-selected="false">Inbox Recruitment</a>
                </li>
            </ul>
            <div class="tab-content mt-4" id="recruitmentTabContent">
                <div class="tab-pane fade show active" id="lowongan" role="tabpanel" aria-labelledby="lowongan-tab">
                    <div class="card">
                        <div class="card-header">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h4 class="text-white mb-0">
                                        <i class="fas fa-chalkboard-teacher me-2"></i>Data Lowongan
                                    </h4>
                                    <small class="text-white-50"></small>
                                </div>
                                <a href="tambah_lowongan.php" class="btn btn-add text-white">
                                    <i class="fas fa-plus-circle me-2"></i>Tambah Lowongan
                                </a>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-hover" id="tabelRecruitment">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Nama Lowongan</th>
                                            <th>Deskripsi</th>
                                            <th>ID Jabatan</th>
                                            <th>Tanggal Posting</th>
                                            <th>Tanggal Tutup</th>
                                            <th>Status Lowongan</th>
                                            
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($lowongan as $row): ?>
                                            <tr>
                                                <td><?= $row['id_lowongan'] ?></td>
                                                <td><?= htmlspecialchars($row['nama_lowongan']) ?></td>
                                                <td><?= htmlspecialchars($row['deskripsi_lowongan']) ?></td>
                                                <td><?= htmlspecialchars($row['id_jabatan']) ?></td>
                                                <td><?= date('d/m/Y', strtotime($row['tgl_posting'])) ?></td>
                                                <td><?= date('d/m/Y', strtotime($row['tgl_tutup'])) ?></td>
                                                <td>
                                                    <span class="badge <?php 
                                                        echo match($row['status_lowongan']) {
                                                            'Open' => 'bg-success',
                                                            'Closed' => 'bg-danger',
                                                            default => 'bg-secondary'
                                                        };
                                                    ?>">
                                                        <?= htmlspecialchars($row['status_lowongan']) ?>
                                                    </span>
                                                </td>
                                                
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="tab-pane fade" id="departemen" role="tabpanel" aria-labelledby="departemen-tab">
                    <div class="card">
                        <div class="card-header">
                            <h4 class="text-white mb-0">Data Departemen</h4>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-hover" id="tabelDepartemen">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Nama Departemen</th>
                                            <th>Kepala Departemen</th>
                                            <th>Lokasi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($departemen as $row): ?>
                                            <tr>
                                                <td><?= $row['id_departemen'] ?></td>
                                                <td><?= htmlspecialchars($row['nama_departemen']) ?></td>
                                                <td><?= htmlspecialchars($row['kepala_departemen']) ?></td>
                                                <td><?= htmlspecialchars($row['lokasi_departemen']) ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="tab-pane fade" id="jabatan" role="tabpanel" aria-labelledby="jabatan-tab">
                    <div class="card">
                        <div class="card-header">
                            <h4 class="text-white mb-0">Data Jabatan</h4>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-hover" id="tabelJabatan">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Nama Jabatan</th>
                                            <th>Deskripsi</th>
                                            <th>Level</th>
                                            <th>Dibuat</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($jabatan as $row): ?>
                                            <tr>
                                                <td><?= $row['id_jabatan'] ?></td>
                                                <td><?= htmlspecialchars($row['nama_jabatan']) ?></td>
                                                <td><?= htmlspecialchars($row['desk_jabatan']) ?></td>
                                                <td><?= htmlspecialchars((string)($row['level_jabatan'] ?? '-')) ?></td>
                                                <td><?= htmlspecialchars((string)($row['created_at'] ?? '-')) ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="tab-pane fade" id="pelamar" role="tabpanel" aria-labelledby="pelamar-tab">
                    <div class="card">
                        <div class="card-header">
                            <h4 class="text-white mb-0">Data Pelamar</h4>
                        </div>
                        <div class="card-body">
                            <form method="post" action="submit_pelamar.php">
                                <div class="mb-3">
                                    <label for="id_pelamar" class="form-label">ID Pelamar</label>
                                    <input type="text" class="form-control" id="id_pelamar" name="id_pelamar" required>
                                </div>
                                <div class="mb-3">
                                    <label for="nama_pel" class="form-label">Nama Pelamar</label>
                                    <input type="text" class="form-control" id="nama_pel" name="nama_pel" required>
                                </div>
                                <div class="mb-3">
                                    <label for="email_pel" class="form-label">Email Pelamar</label>
                                    <input type="email" class="form-control" id="email_pel" name="email_pel" required>
                                </div>
                                <div class="mb-3">
                                    <label for="id_lowongan" class="form-label">ID Lowongan</label>
                                    <input type="text" class="form-control" id="id_lowongan" name="id_lowongan" required>
                                </div>
                                <div class="mb-3">
                                    <label for="jabatan_dipilih" class="form-label">Jabatan Dipilih</label>
                                    <input type="text" class="form-control" id="jabatan_dipilih" name="jabatan_dipilih" required>
                                </div>
                                <button type="submit" class="btn btn-primary submit-button">Submit</button>
                            </form>
                            <div class="table-responsive mt-3">
                                <table class="table table-hover" id="tabelPelamar">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Nama</th>
                                            <th>Email</th>
                                            <th>ID Lowongan</th>
                                            <th>Status</th>
                                            <th>Jabatan Dipilih</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($pelamar as $row): ?>
                                            <tr>
                                                <td><?= $row['id_pelamar'] ?></td>
                                                <td><?= htmlspecialchars($row['nama_pel']) ?></td>
                                                <td><?= htmlspecialchars($row['email_pel']) ?></td>
                                                <td><?= htmlspecialchars($row['id_lowongan']) ?></td>
                                                <td><?= htmlspecialchars($row['status_pel']) ?></td>
                                                <td><?= htmlspecialchars($row['jabatan_dipilih']) ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="tab-pane fade" id="tahap-lamaran" role="tabpanel" aria-labelledby="tahap-lamaran-tab">
                    <div class="card">
                        <div class="card-header">
                            <h4 class="text-white mb-0">Data Tahap Lamaran</h4>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-hover" id="tabelTahapLamaran">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Nama Tahap</th>
                                            <th>Deskripsi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($tahap_lamaran as $row): ?>
                                            <tr>
                                                <td><?= $row['id_tahap'] ?></td>
                                                <td><?= htmlspecialchars($row['nama_tahap']) ?></td>
                                                <td><?= htmlspecialchars($row['deskripsi_tahap']) ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="tab-pane fade" id="penilaian-pelamar" role="tabpanel" aria-labelledby="penilaian-pelamar-tab">
    <div class="card">
        <div class="card-header">
            <h4 class="text-white mb-0">Data Penilaian Pelamar</h4>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover" id="tabelPenilaianPelamar">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>ID Pelamar</th>
                            <th>ID Tahap</th>
                            <th>Tanggal Dinilai</th>
                            <th>Skor</th>
                            <th>Catatan</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($penilaian_pelamar as $row): ?>
                            <tr>
                                <td><?= $row['id_penilaian_pel'] ?></td>
                                <td><?= htmlspecialchars($row['id_pelamar']) ?></td>
                                <td><?= htmlspecialchars($row['id_tahap']) ?></td>
                                <td><?= date('d/m/Y', strtotime($row['tgl_dinilai'])) ?></td>
                                <td><?= htmlspecialchars($row['skor']) ?></td>
                                <td><?= htmlspecialchars($row['catatan']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<div class="tab-pane fade" id="inbox" role="tabpanel" aria-labelledby="inbox-tab">
    <div class="card">
        <div class="card-header">
            <h4 class="text-white mb-0">Inbox Recruitment</h4>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover" id="tabelInbox">
                    <thead>
                        <tr>
                            <th>Pengirim</th>
                            <th>Subject</th>
                            <th>Tanggal</th>
                            <th>Status</th>
                            <th>Lampiran</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recruitment_inbox as $row): ?>
                            <tr>
                                <td><?= htmlspecialchars((string)($row['sender_email'] ?? '-')) ?></td>
                                <td><?= htmlspecialchars((string)($row['subject'] ?? '-')) ?></td>
                                <td><?= htmlspecialchars((string)($row['received_at'] ?? '-')) ?></td>
                                <td><?= htmlspecialchars((string)($row['status'] ?? 'baru')) ?></td>
                                <td><?= htmlspecialchars((string)($row['attachment_path'] ?? '-')) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
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
        $('#tabelRecruitment').DataTable({
            language: {
                url: '//cdn.datatables.net/plug-ins/1.11.5/i18n/id.json'
            },
            pageLength: 10,
            ordering: true,
            responsive: true,
            dom: '<"d-flex justify-content-between align-items-center mb-3"lf>rt<"d-flex justify-content-between align-items-center mt-3"ip>',
            lengthMenu: [[5, 10, 25, 50, -1], [5, 10, 25, 50, "Semua"]]
        });

        $('#tabelDepartemen').DataTable({
            language: {
                url: '//cdn.datatables.net/plug-ins/1.11.5/i18n/id.json'
            },
            pageLength: 10,
            ordering: true,
            responsive: true,
            dom: '<"d-flex justify-content-between align-items-center mb-3"lf>rt<"d-flex justify-content-between align-items-center mt-3"ip>',
            lengthMenu: [[5, 10, 25, 50, -1], [5, 10, 25, 50, "Semua"]]
        });

        $('#tabelJabatan').DataTable({
            language: {
                url: '//cdn.datatables.net/plug-ins/1.11.5/i18n/id.json'
            },
            pageLength: 10,
            ordering: true,
            responsive: true,
            dom: '<"d-flex justify-content-between align-items-center mb-3"lf>rt<"d-flex justify-content-between align-items-center mt-3"ip>',
            lengthMenu: [[5, 10, 25, 50, -1], [5, 10, 25, 50, "Semua"]]
        });

        $('#tabelPelamar').DataTable({
            language: {
                url: '//cdn.datatables.net/plug-ins/1.11.5/i18n/id.json'
            },
            pageLength: 10,
            ordering: true,
            responsive: true,
            dom: '<"d-flex justify-content-between align-items-center mb-3"lf>rt<"d-flex justify-content-between align-items-center mt-3"ip>',
            lengthMenu: [[5, 10, 25, 50, -1], [5, 10, 25, 50, "Semua"]]
        });

        $('#tabelTahapLamaran').DataTable({
            language: {
                url: '//cdn.datatables.net/plug-ins/1.11.5/i18n/id.json'
            },
            pageLength: 10,
            ordering: true,
            responsive: true,
            dom: '<"d-flex justify-content-between align-items-center mb-3"lf>rt<"d-flex justify-content-between align-items-center mt-3"ip>',
            lengthMenu: [[5, 10, 25, 50, -1], [5, 10, 25, 50, "Semua"]]
        });

        $('#tabelPenilaianPelamar').DataTable({
            language: {
                url: '//cdn.datatables.net/plug-ins/1.11.5/i18n/id.json'
            },
            pageLength: 10,
            ordering: true,
            responsive: true,
            dom: '<"d-flex justify-content-between align-items-center mb-3"lf>rt<"d-flex justify-content-between align-items-center mt-3"ip>',
            lengthMenu: [[5, 10, 25, 50, -1], [5, 10, 25, 50, "Semua"]]
        });

        $('#tabelInbox').DataTable({
            language: {
                url: '//cdn.datatables.net/plug-ins/1.11.5/i18n/id.json'
            },
            pageLength: 10,
            ordering: true,
            responsive: true
        });
    });
http://edp2:8081/hris/recruitment/index.php
    document.querySelectorAll('#recruitmentTab [data-bs-toggle="tab"]').forEach(function(tab) {
        tab.addEventListener('click', function(event) {
            event.preventDefault();
            var targetId = tab.getAttribute('href');
            var target = document.querySelector(targetId);
            if (!target) {
                return;
            }

            document.querySelectorAll('#recruitmentTab .nav-link').forEach(function(link) {
                link.classList.remove('active');
                link.setAttribute('aria-selected', 'false');
            });
            document.querySelectorAll('#recruitmentTabContent .tab-pane').forEach(function(pane) {
                pane.classList.remove('show', 'active');
                pane.style.display = 'none';
            });

            tab.classList.add('active');
            tab.setAttribute('aria-selected', 'true');
            target.classList.add('show', 'active');
            target.style.display = 'block';
            history.replaceState(null, '', targetId);
        });
    });

    var initialTab = window.location.hash;
    if (initialTab) {
        var initialTabLink = document.querySelector('#recruitmentTab [href="' + initialTab + '"]');
        if (initialTabLink) {
            initialTabLink.click();
        }
    }

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
                window.location.href = 'hapus_lowongan.php?id=' + id;
            }
        });
    }

    function filterTable() {
        const input = document.getElementById('searchInput');
        const filter = input.value.toLowerCase();
        const tables = document.querySelectorAll('.tab-pane table');

        tables.forEach(table => {
            const tr = table.getElementsByTagName('tr');
            for (let i = 1; i < tr.length; i++) { // Mulai dari 1 untuk melewati header
                const tds = tr[i].getElementsByTagName('td');
                let match = false;
                for (let j = 0; j < tds.length; j++) {
                    if (tds[j].innerText.toLowerCase().indexOf(filter) > -1) {
                        match = true;
                        break;
                    }
                }
                tr[i].style.display = match ? '' : 'none';
            }
        });
    }
    </script>
</body>
</html>