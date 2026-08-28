<?php
session_start();
include '../template/header.php';
include '../template/sidebar.php';
include '../connection.php'; // Sesuaikan path ke file connection.php

// Tambahkan query pencarian setelah include connection.php
$search = isset($_GET['search']) ? $_GET['search'] : '';

// Konfigurasi pagination
$per_page = 5; // Jumlah data per halaman

// Pagination untuk Promosi
$page_promosi = isset($_GET['page_promosi']) ? (int)$_GET['page_promosi'] : 1;
$start_promosi = ($page_promosi - 1) * $per_page;

// Hitung total data promosi
$total_promosi = mysqli_num_rows(mysqli_query($conn, "
    SELECT p.id_promosi FROM promosi p 
    JOIN pegawai pe ON p.id_peg = pe.id_peg 
    JOIN jabatan j ON p.id_jabatan = j.id_jabatan
    WHERE pe.nama_peg LIKE '%$search%' 
    OR j.nama_jabatan LIKE '%$search%'
    OR p.alasan_promosi LIKE '%$search%'
    OR p.status_promosi LIKE '%$search%'
"));
$total_pages_promosi = ceil($total_promosi / $per_page);

// Query promosi dengan limit
$queryPromosi = mysqli_query($conn, "
    SELECT p.id_promosi, pe.nama_peg, j.nama_jabatan, p.tgl_promosi, p.alasan_promosi, p.status_promosi 
    FROM promosi p 
    JOIN pegawai pe ON p.id_peg = pe.id_peg 
    JOIN jabatan j ON p.id_jabatan = j.id_jabatan
    WHERE pe.nama_peg LIKE '%$search%' 
    OR j.nama_jabatan LIKE '%$search%'
    OR p.alasan_promosi LIKE '%$search%'
    OR p.status_promosi LIKE '%$search%'
    LIMIT $start_promosi, $per_page
");

// Ambil data mutasi
$queryMutasi = mysqli_query($conn, "
    SELECT m.id_mutasi, pe.nama_peg, d.nama_departemen, j.nama_jabatan, m.tgl_mutasi, m.alasan_mutasi, m.status_mutasi 
    FROM mutasi m 
    JOIN pegawai pe ON m.id_peg = pe.id_peg 
    JOIN departemen d ON m.id_dep = d.id_dep 
    JOIN jabatan j ON m.id_jabatan = j.id_jabatan
    WHERE pe.nama_peg LIKE '%$search%'
    OR d.nama_departemen LIKE '%$search%'
    OR j.nama_jabatan LIKE '%$search%'
    OR m.alasan_mutasi LIKE '%$search%'
    OR m.status_mutasi LIKE '%$search%'
");

// Pagination untuk Mutasi
$page_mutasi = isset($_GET['page_mutasi']) ? (int)$_GET['page_mutasi'] : 1;
$start_mutasi = ($page_mutasi - 1) * $per_page;

// Hitung total data mutasi
$total_mutasi = mysqli_num_rows(mysqli_query($conn, "
    SELECT m.id_mutasi FROM mutasi m 
    JOIN pegawai pe ON m.id_peg = pe.id_peg 
    JOIN departemen d ON m.id_dep = d.id_dep 
    JOIN jabatan j ON m.id_jabatan = j.id_jabatan
    WHERE pe.nama_peg LIKE '%$search%'
    OR d.nama_departemen LIKE '%$search%'
    OR j.nama_jabatan LIKE '%$search%'
    OR m.alasan_mutasi LIKE '%$search%'
    OR m.status_mutasi LIKE '%$search%'
"));
$total_pages_mutasi = ceil($total_mutasi / $per_page);

// Query mutasi dengan limit
$queryMutasi = mysqli_query($conn, "
    SELECT m.id_mutasi, pe.nama_peg, d.nama_departemen, j.nama_jabatan, m.tgl_mutasi, m.alasan_mutasi, m.status_mutasi 
    FROM mutasi m 
    JOIN pegawai pe ON m.id_peg = pe.id_peg 
    JOIN departemen d ON m.id_dep = d.id_dep 
    JOIN jabatan j ON m.id_jabatan = j.id_jabatan
    WHERE pe.nama_peg LIKE '%$search%'
    OR d.nama_departemen LIKE '%$search%'
    OR j.nama_jabatan LIKE '%$search%'
    OR m.alasan_mutasi LIKE '%$search%'
    OR m.status_mutasi LIKE '%$search%'
    LIMIT $start_mutasi, $per_page
");
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <title>Manajemen Promosi & Mutasi</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@sweetalert2/theme-material-ui/material-ui.css">
    <style>
        /* Enhanced Dashboard Container */
        .dashboard-container {
            margin-left: 280px;
            margin-top: 90px;
            padding: 40px;
            min-height: calc(100vh - 90px);
            width: calc(100% - 280px);
            background: 
                linear-gradient(120deg, rgba(240,244,247,0.8) 0%, rgba(255,255,255,0.8) 100%),
                radial-gradient(circle at 10% 20%, rgba(43,76,139,0.05) 0%, transparent 20%),
                radial-gradient(circle at 90% 80%, rgba(26,54,93,0.05) 0%, transparent 20%);
        }

        /* Animated Header Section */
        .header-section {
            background: linear-gradient(135deg, #1a365d 0%, #2b4c8b 100%);
            border-radius: 25px;
            padding: 40px;
            margin-bottom: 40px;
            position: relative;
            overflow: hidden;
            box-shadow: 
                0 20px 40px rgba(0,0,0,0.1),
                inset 0 -3px 0 rgba(0,0,0,0.1);
            animation: headerGlow 3s infinite alternate;
        }

        .header-section::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 60%);
            animation: rotateGradient 15s linear infinite;
        }

        /* Enhanced Stats Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 25px;
            margin-bottom: 40px;
        }

        .stat-card {
            background: rgba(255,255,255,0.9);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 30px;
            border: 1px solid rgba(255,255,255,0.2);
            box-shadow: 
                0 10px 30px rgba(0,0,0,0.05),
                0 5px 15px rgba(43,76,139,0.05);
            transition: all 0.4s ease;
            position: relative;
            overflow: hidden;
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(135deg, transparent 0%, rgba(255,255,255,0.2) 100%);
            opacity: 0;
            transition: opacity 0.4s ease;
        }

        .stat-card:hover {
            transform: translateY(-5px) scale(1.02);
            box-shadow: 
                0 15px 35px rgba(0,0,0,0.1),
                0 8px 20px rgba(43,76,139,0.08);
        }

        .stat-card:hover::before {
            opacity: 1;
        }

        /* Enhanced Table Design */
        .table-wrapper {
            background: rgba(255,255,255,0.95);
            backdrop-filter: blur(15px);
            border-radius: 30px;
            padding: 35px;
            margin-bottom: 40px;
            box-shadow: 
                0 20px 40px rgba(0,0,0,0.08),
                0 10px 20px rgba(43,76,139,0.05);
            border: 1px solid rgba(255,255,255,0.2);
            position: relative;
            overflow: hidden;
            transition: all 0.3s ease;
        }

        .table-wrapper:hover {
            transform: translateY(-5px);
            box-shadow: 
                0 25px 45px rgba(0,0,0,0.1),
                0 15px 25px rgba(43,76,139,0.08);
        }

        /* Enhanced Table Headers */
        .table thead th {
            background: linear-gradient(145deg, #2b4c8b, #1a365d);
            color: white;
            padding: 20px 25px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
            font-size: 13px;
            border: none;
            position: relative;
            overflow: hidden;
        }

        .table thead th::after {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            animation: shine 3s infinite;
        }

        /* Enhanced Table Rows */
        .table tbody tr {
            transition: all 0.3s ease;
            background: rgba(255,255,255,0.7);
        }

        .table tbody tr:hover {
            transform: scale(1.01);
            background: rgba(255,255,255,0.95);
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
        }

        /* Enhanced Action Buttons */
        .btn-action {
            width: 35px;
            height: 35px;
            border-radius: 12px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin: 0 3px;
            transition: all 0.3s ease;
            background: rgba(255,255,255,0.9);
            border: 1px solid rgba(0,0,0,0.1);
        }

        .btn-view {
            color: #2196F3;
            box-shadow: 0 2px 8px rgba(33,150,243,0.15);
        }

        .btn-edit {
            color: #FFC107;
            box-shadow: 0 2px 8px rgba(255,193,7,0.15);
        }

        .btn-delete {
            color: #F44336;
            box-shadow: 0 2px 8px rgba(244,67,54,0.15);
        }

        .btn-action:hover {
            transform: translateY(-3px) scale(1.05);
        }

        /* Enhanced Status Badges */
        .status-badge {
            padding: 8px 16px;
            border-radius: 50px;
            font-size: 12px;
            font-weight: 600;
            letter-spacing: 0.5px;
            text-transform: uppercase;
            transition: all 0.3s ease;
            background: rgba(255,255,255,0.9);
            border: 1px solid rgba(0,0,0,0.1);
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }

        /* Animations */
        @keyframes headerGlow {
            0% { box-shadow: 0 20px 40px rgba(0,0,0,0.1); }
            100% { box-shadow: 0 20px 40px rgba(43,76,139,0.2); }
        }

        @keyframes rotateGradient {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        @keyframes shine {
            0% { left: -100%; }
            20% { left: 100%; }
            100% { left: 100%; }
        }

        /* Enhanced Search Container */
        .search-container {
            margin: 30px 0;
            position: relative;
            width: 100%;
            display: flex;
            justify-content: center;
            align-items: center;
            background: rgba(255, 255, 255, 0.95);
            padding: 25px 30px;
            border-radius: 20px;
            box-shadow: 
                0 10px 30px rgba(0,0,0,0.05),
                0 5px 15px rgba(43,76,139,0.05);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255,255,255,0.2);
        }

        /* Search Form Group */
        .search-input-group {
            position: relative;
            width: 100%;
            max-width: 800px;
            display: flex;
            align-items: center;
            gap: 15px;
        }

        /* Input Wrapper */
        .input-wrapper {
            position: relative;
            flex: 1;
        }

        /* Modern Search Input */
        .search-input {
            width: 100%;
            padding: 15px 45px;
            font-size: 15px;
            color: #2b4c8b;
            background: rgba(255,255,255,0.95);
            border: 2px solid rgba(43,76,139,0.1);
            border-radius: 15px;
            transition: all 0.3s ease;
            box-shadow: 
                0 5px 15px rgba(0,0,0,0.05),
                inset 0 2px 5px rgba(43,76,139,0.03);
        }

        .search-input:focus {
            outline: none;
            border-color: #2b4c8b;
            transform: translateY(-2px);
            box-shadow: 
                0 8px 20px rgba(43,76,139,0.1),
                inset 0 2px 5px rgba(43,76,139,0.03);
        }

        /* Search Icon */
        .search-icon {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #2b4c8b;
            font-size: 18px;
            pointer-events: none;
            transition: all 0.3s ease;
        }

        /* Clear Button */
        .search-clear {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #94a3b8;
            cursor: pointer;
            width: 24px;
            height: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            transition: all 0.3s ease;
            background: transparent;
            opacity: 0.7;
        }

        .search-clear:hover {
            background: rgba(43,76,139,0.1);
            color: #2b4c8b;
            opacity: 1;
        }

        /* Search Button */
        .search-button {
            padding: 15px 30px;
            background: linear-gradient(135deg, #2b4c8b, #1a365d);
            color: white;
            border: none;
            border-radius: 12px;
            font-weight: 500;
            font-size: 15px;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 5px 15px rgba(43,76,139,0.2);
            display: flex;
            align-items: center;
            gap: 8px;
            white-space: nowrap;
        }

        .search-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(43,76,139,0.3);
            background: linear-gradient(135deg, #1a365d, #2b4c8b);
        }

        .search-button i {
            font-size: 16px;
        }

        /* Placeholder Styling */
        .search-input::placeholder {
            color: #94a3b8;
            opacity: 0.7;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .search-container {
                padding: 20px;
            }

            .search-input-group {
                flex-direction: column;
            }

            .search-button {
                width: 100%;
                justify-content: center;
            }
        }

        /* Enhanced Pagination */
        .pagination {
            margin-top: 30px;
            gap: 8px;
        }

        .page-link {
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 12px;
            background: rgba(255,255,255,0.9);
            backdrop-filter: blur(5px);
            border: 1px solid rgba(255,255,255,0.2);
            color: #2b4c8b;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .page-link:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(43,76,139,0.15);
        }

        .page-item.active .page-link {
            background: linear-gradient(145deg, #2b4c8b, #1a365d);
            color: white;
            border: none;
        }

        /* Action Buttons Styling */
        .action-buttons {
            display: flex;
            gap: 10px;
            justify-content: flex-start;
        }

        .btn-action {
            width: 35px;
            height: 35px;
            border-radius: 8px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
            text-decoration: none;
        }

        .btn-edit {
            background: rgba(255, 193, 7, 0.1);
            color: #FFC107;
        }

        .btn-edit:hover {
            background: #FFC107;
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(255, 193, 7, 0.2);
        }

        .btn-delete {
            background: rgba(244, 67, 54, 0.1);
            color: #F44336;
        }

        .btn-delete:hover {
            background: #F44336;
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(244, 67, 54, 0.2);
        }

        /* Hover Animation */
        .btn-action i {
            transition: all 0.3s ease;
        }

        .btn-action:hover i {
            transform: scale(1.1);
        }

        /* Active State */
        .btn-action:active {
            transform: translateY(0);
            box-shadow: none;
        }

        .action-container {
            margin-bottom: 30px;
            padding: 20px;
            background: rgba(255, 255, 255, 0.9);
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
        }

        .button-group {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
        }

        .btn-add {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 12px 24px;
            background: linear-gradient(135deg, #2b4c8b, #1a365d);
            color: white;
            border: none;
            border-radius: 12px;
            font-weight: 500;
            text-decoration: none;
            transition: all 0.3s ease;
            box-shadow: 0 4px 12px rgba(43,76,139,0.15);
        }

        .btn-add:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 15px rgba(43,76,139,0.25);
            background: linear-gradient(135deg, #1a365d, #2b4c8b);
            color: white;
        }

        .btn-add i {
            font-size: 16px;
        }

        @media (max-width: 768px) {
            .button-group {
                flex-direction: column;
            }
            
            .btn-add {
                width: 100%;
                justify-content: center;
            }
        }

        .pagination-container {
            margin: 30px 0;
            display: flex;
            justify-content: center;
        }

        .pagination {
            display: flex;
            gap: 8px;
            padding: 0;
            margin: 0;
            list-style: none;
        }

        .page-item {
            margin: 0;
        }

        .page-link {
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 12px;
            background: rgba(255,255,255,0.9);
            border: 1px solid rgba(43,76,139,0.1);
            color: #2b4c8b;
            font-weight: 500;
            text-decoration: none;
            transition: all 0.3s ease;
            box-shadow: 0 2px 6px rgba(43,76,139,0.08);
        }

        .page-link:hover {
            background: rgba(43,76,139,0.05);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(43,76,139,0.12);
            color: #1a365d;
        }

        .page-item.active .page-link {
            background: linear-gradient(135deg, #2b4c8b, #1a365d);
            color: white;
            border: none;
            box-shadow: 0 4px 12px rgba(43,76,139,0.2);
        }

        .page-item.disabled .page-link {
            background: rgba(255,255,255,0.5);
            color: #94a3b8;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }

        .page-link i {
            font-size: 14px;
        }

        @media (max-width: 768px) {
            .pagination {
                gap: 4px;
            }

            .page-link {
                width: 35px;
                height: 35px;
                font-size: 14px;
            }
        }
    </style>
</head>

<body>
    <div class="content-wrapper">
        <div class="dashboard-container">
            <!-- Header Section -->
            <div class="header-section">
                <h1 class="text-white mb-0">Manajemen Promosi & Mutasi</h1>
            </div>

            <!-- Stats Grid -->
            <div class="stats-grid">
                <div class="stat-card">
                    <h3>Total Promosi</h3>
                    <p class="h2 mb-0"><?= $total_promosi ?></p>
                </div>
                <div class="stat-card">
                    <h3>Total Mutasi</h3>
                    <p class="h2 mb-0"><?= $total_mutasi ?></p>
                </div>
            </div>

            <!-- Search Form HTML -->
            <div class="search-container">
                <form method="GET" action="" class="search-input-group" id="searchForm">
                    <div class="input-wrapper">
                        <i class="fas fa-search search-icon"></i>
                        <input 
                            type="text" 
                            name="search" 
                            class="search-input" 
                            placeholder="Cari data promosi atau mutasi..." 
                            value="<?= htmlspecialchars($search ?? '') ?>"
                        >
                        <?php if (!empty($search)): ?>
                            <i class="fas fa-times search-clear" onclick="clearSearch(event)"></i>
                        <?php endif; ?>
                    </div>
                    <button type="submit" class="search-button">
                        <i class="fas fa-search"></i>
                        Cari Data
                    </button>
                </form>
            </div>

            <!-- Tambahkan setelah judul dan sebelum tabel -->
            <div class="action-container">
                <div class="button-group">
                    <a href="tambah_promosi.php" class="btn-add">
                        <i class="fas fa-plus"></i>
                        Tambah Data Promosi
                    </a>
                    <a href="tambah_mutasi.php" class="btn-add">
                        <i class="fas fa-plus"></i>
                        Tambah Data Mutasi
                    </a>
                </div>
            </div>

            <!-- Tabel Promosi -->
            <div class="table-wrapper mb-5">
                <h2 class="mb-4">Data Promosi</h2>
                <table class="table">
                    <thead>
                        <tr>
                            <th>No</th>
                            <th>Nama Pegawai</th>
                            <th>Jabatan</th>
                            <th>Tanggal</th>
                            <th>Alasan</th>
                            <th>Status</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(mysqli_num_rows($queryPromosi) > 0): ?>
                            <?php $no = $start_promosi + 1; ?>
                            <?php while($row = mysqli_fetch_assoc($queryPromosi)): ?>
                                <tr>
                                    <td><?= $no++ ?></td>
                                    <td><?= htmlspecialchars($row['nama_peg']) ?></td>
                                    <td><?= htmlspecialchars($row['nama_jabatan']) ?></td>
                                    <td><?= date('d/m/Y', strtotime($row['tgl_promosi'])) ?></td>
                                    <td><?= htmlspecialchars($row['alasan_promosi']) ?></td>
                                    <td>
                                        <span class="status-badge status-<?= strtolower($row['status_promosi']) ?>">
                                            <?= htmlspecialchars($row['status_promosi']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="action-buttons">
                                            <a href="edit_promosi.php?id=<?= $row['id_promosi'] ?>" class="btn-action btn-edit" title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <a href="javascript:void(0)" onclick="confirmDelete(<?= $row['id_promosi'] ?>)" class="btn-action btn-delete" title="Hapus">
                                                <i class="fas fa-trash-alt"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="text-center py-4">
                                    <div class="empty-state">
                                        <i class="fas fa-folder-open fa-3x mb-3"></i>
                                        <p class="text-muted">Tidak ada data promosi</p>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
                <!-- Pagination Promosi -->
                <div class="pagination-container">
                    <nav aria-label="Page navigation">
                        <ul class="pagination">
                            <!-- Previous Button -->
                            <li class="page-item <?= ($page_promosi <= 1) ? 'disabled' : '' ?>">
                                <a class="page-link" href="?page_promosi=<?= $page_promosi - 1 ?>&page_mutasi=<?= $page_mutasi ?>&search=<?= $search ?>" aria-label="Previous">
                                    <i class="fas fa-chevron-left"></i>
                                </a>
                            </li>

                            <!-- Page Numbers -->
                            <?php for($i = 1; $i <= $total_pages_promosi; $i++): ?>
                                <li class="page-item <?= ($page_promosi == $i) ? 'active' : '' ?>">
                                    <a class="page-link" href="?page_promosi=<?= $i ?>&page_mutasi=<?= $page_mutasi ?>&search=<?= $search ?>">
                                        <?= $i ?>
                                    </a>
                                </li>
                            <?php endfor; ?>

                            <!-- Next Button -->
                            <li class="page-item <?= ($page_promosi >= $total_pages_promosi) ? 'disabled' : '' ?>">
                                <a class="page-link" href="?page_promosi=<?= $page_promosi + 1 ?>&page_mutasi=<?= $page_mutasi ?>&search=<?= $search ?>" aria-label="Next">
                                    <i class="fas fa-chevron-right"></i>
                                </a>
                            </li>
                        </ul>
                    </nav>
                </div>
            </div>

            <!-- Tabel Mutasi -->
            <div class="table-wrapper">
                <h2 class="mb-4">Data Mutasi</h2>
                <table class="table">
                    <thead>
                        <tr>
                            <th>No</th>
                            <th>Nama Pegawai</th>
                            <th>Departemen</th>
                            <th>Jabatan</th>
                            <th>Tanggal</th>
                            <th>Alasan</th>
                            <th>Status</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(mysqli_num_rows($queryMutasi) > 0): ?>
                            <?php $no = $start_mutasi + 1; ?>
                            <?php while($row = mysqli_fetch_assoc($queryMutasi)): ?>
                                <tr>
                                    <td><?= $no++ ?></td>
                                    <td><?= htmlspecialchars($row['nama_peg']) ?></td>
                                    <td><?= htmlspecialchars($row['nama_departemen']) ?></td>
                                    <td><?= htmlspecialchars($row['nama_jabatan']) ?></td>
                                    <td><?= date('d/m/Y', strtotime($row['tgl_mutasi'])) ?></td>
                                    <td><?= htmlspecialchars($row['alasan_mutasi']) ?></td>
                                    <td>
                                        <span class="status-badge status-<?= strtolower($row['status_mutasi']) ?>">
                                            <?= htmlspecialchars($row['status_mutasi']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="action-buttons">
                                            <a href="edit_mutasi.php?id=<?= $row['id_mutasi'] ?>" class="btn-action btn-edit" title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <a href="javascript:void(0)" onclick="confirmDeleteMutasi(<?= $row['id_mutasi'] ?>)" class="btn-action btn-delete" title="Hapus">
                                                <i class="fas fa-trash-alt"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" class="text-center py-4">
                                    <div class="empty-state">
                                        <i class="fas fa-exchange-alt fa-3x mb-3"></i>
                                        <p class="text-muted">Tidak ada data mutasi</p>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
                <!-- Pagination Mutasi -->
                <div class="pagination-container">
                    <nav aria-label="Page navigation">
                        <ul class="pagination">
                            <li class="page-item <?= ($page_mutasi <= 1) ? 'disabled' : '' ?>">
                                <a class="page-link" href="?page_promosi=<?= $page_promosi ?>&page_mutasi=<?= $page_mutasi - 1 ?>&search=<?= $search ?>" aria-label="Previous">
                                    <i class="fas fa-chevron-left"></i>
                                </a>
                            </li>

                            <?php for($i = 1; $i <= $total_pages_mutasi; $i++): ?>
                                <li class="page-item <?= ($page_mutasi == $i) ? 'active' : '' ?>">
                                    <a class="page-link" href="?page_promosi=<?= $page_promosi ?>&page_mutasi=<?= $i ?>&search=<?= $search ?>">
                                        <?= $i ?>
                                    </a>
                                </li>
                            <?php endfor; ?>

                            <li class="page-item <?= ($page_mutasi >= $total_pages_mutasi) ? 'disabled' : '' ?>">
                                <a class="page-link" href="?page_promosi=<?= $page_promosi ?>&page_mutasi=<?= $page_mutasi + 1 ?>&search=<?= $search ?>" aria-label="Next">
                                    <i class="fas fa-chevron-right"></i>
                                </a>
                            </li>
                        </ul>
                    </nav>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
    // Konfigurasi Toastr
    toastr.options = {
        closeButton: true,
        progressBar: true,
        positionClass: "toast-top-right",
        timeOut: 3000,
        extendedTimeOut: 1000,
        preventDuplicates: true,
        newestOnTop: true,
        showEasing: "swing",
        hideEasing: "linear",
        showMethod: "fadeIn",
        hideMethod: "fadeOut"
    };

    // Fungsi untuk konfirmasi hapus dengan animasi
    function confirmDelete(id) {
        Swal.fire({
            title: 'Apakah Anda yakin?',
            html: `<div class="delete-confirm">
                    <div class="delete-icon">
                        <i class="fas fa-trash-alt fa-bounce"></i>
                    </div>
                    <p>Data promosi akan dihapus permanen!</p>
                  </div>`,
            icon: false,
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Ya, Hapus!',
            cancelButtonText: 'Batal',
            customClass: {
                popup: 'animated-popup',
                title: 'delete-title',
                confirmButton: 'btn-confirm',
                cancelButton: 'btn-cancel'
            },
            showClass: {
                popup: 'animate__animated animate__fadeInDown'
            },
            hideClass: {
                popup: 'animate__animated animate__fadeOutUp'
            }
        }).then((result) => {
            if (result.isConfirmed) {
                // Animasi loading sebelum hapus
                Swal.fire({
                    title: 'Menghapus Data...',
                    html: '<i class="fas fa-spinner fa-spin fa-2x"></i>',
                    showConfirmButton: false,
                    timer: 1000,
                    customClass: {
                        popup: 'loading-popup'
                    }
                }).then(() => {
                    window.location.href = `hapus_promosi.php?id=${id}`;
                });
            }
        });
    }

    // Fungsi untuk notifikasi edit
    function showEditNotification(type) {
        const Toast = Swal.mixin({
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            timer: 3000,
            timerProgressBar: true,
            customClass: {
                popup: 'edit-toast'
            },
            didOpen: (toast) => {
                toast.addEventListener('mouseenter', Swal.stopTimer)
                toast.addEventListener('mouseleave', Swal.resumeTimer)
            }
        });

        Toast.fire({
            icon: 'success',
            title: `Data promosi berhasil diperbarui!`
        });
    }

    // Tambahkan CSS untuk styling notifikasi
    document.head.insertAdjacentHTML('beforeend', `
        <style>
            /* Animasi untuk popup */
            .animated-popup {
                border-radius: 20px !important;
                background: linear-gradient(145deg, #ffffff, #f3f3f3) !important;
                box-shadow: 0 15px 35px rgba(0,0,0,0.2) !important;
            }

            /* Styling untuk konfirmasi hapus */
            .delete-confirm {
                padding: 20px;
                text-align: center;
            }

            .delete-icon {
                font-size: 3rem;
                color: #d33;
                margin-bottom: 20px;
            }

            .delete-title {
                color: #d33 !important;
                font-size: 1.8rem !important;
                font-weight: 600 !important;
            }

            /* Styling untuk tombol */
            .btn-confirm, .btn-cancel {
                padding: 12px 30px !important;
                font-size: 1.1rem !important;
                border-radius: 10px !important;
                font-weight: 500 !important;
                transition: all 0.3s ease !important;
            }

            .btn-confirm:hover, .btn-cancel:hover {
                transform: translateY(-2px) !important;
                box-shadow: 0 5px 15px rgba(0,0,0,0.2) !important;
            }

            /* Loading popup */
            .loading-popup {
                background: rgba(255, 255, 255, 0.95) !important;
                backdrop-filter: blur(10px) !important;
            }

            /* Edit notification toast */
            .edit-toast {
                background: linear-gradient(135deg, #4CAF50, #45a049) !important;
                color: white !important;
                border-radius: 15px !important;
            }

            /* Animasi bounce untuk ikon */
            .fa-bounce {
                animation: bounce 1s infinite;
            }

            @keyframes bounce {
                0%, 100% { transform: translateY(0); }
                50% { transform: translateY(-10px); }
            }

            /* Animasi spin untuk loading */
            .fa-spin {
                animation: spin 1s infinite linear;
            }

            @keyframes spin {
                0% { transform: rotate(0deg); }
                100% { transform: rotate(360deg); }
            }
        </style>
    `);

    // Event listener untuk notifikasi
    document.addEventListener('DOMContentLoaded', function() {
        <?php if(isset($_GET['status']) && isset($_GET['message'])): ?>
            <?php if($_GET['status'] === 'success'): ?>
                toastr.success('<?= htmlspecialchars($_GET['message']) ?>', 'Berhasil!', {
                    timeOut: 3000,
                    closeButton: true,
                    progressBar: true,
                    positionClass: "toast-top-right"
                });
            <?php else: ?>
                toastr.error('<?= htmlspecialchars($_GET['message']) ?>', 'Gagal!', {
                    timeOut: 4000,
                    closeButton: true,
                    progressBar: true,
                    positionClass: "toast-top-right"
                });
            <?php endif; ?>
        <?php endif; ?>
    });

    // Fungsi untuk konfirmasi hapus mutasi
    function confirmDeleteMutasi(id) {
        Swal.fire({
            title: 'Konfirmasi Hapus',
            html: `<div class="delete-confirm">
                    <div class="delete-icon mutasi-icon">
                        <i class="fas fa-exchange-alt fa-bounce"></i>
                    </div>
                    <p>Apakah Anda yakin ingin menghapus data mutasi ini?</p>
                  </div>`,
            icon: false,
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Ya, Hapus!',
            cancelButtonText: 'Batal',
            customClass: {
                popup: 'animated-popup mutasi-popup',
                title: 'delete-title',
                confirmButton: 'btn-confirm',
                cancelButton: 'btn-cancel'
            },
            showClass: {
                popup: 'animate__animated animate__fadeInDown'
            },
            hideClass: {
                popup: 'animate__animated animate__fadeOutUp'
            }
        }).then((result) => {
            if (result.isConfirmed) {
                // Animasi loading sebelum hapus
                Swal.fire({
                    title: 'Menghapus Data Mutasi...',
                    html: '<i class="fas fa-spinner fa-spin fa-2x"></i>',
                    showConfirmButton: false,
                    timer: 1000,
                    customClass: {
                        popup: 'loading-popup'
                    }
                }).then(() => {
                    window.location.href = `hapus_mutasi.php?id=${id}`;
                });
            }
        });
    }

    // Fungsi untuk notifikasi edit mutasi
    function showEditMutasiNotification() {
        const Toast = Swal.mixin({
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            timer: 3000,
            timerProgressBar: true,
            customClass: {
                popup: 'edit-toast mutasi-toast'
            },
            didOpen: (toast) => {
                toast.addEventListener('mouseenter', Swal.stopTimer)
                toast.addEventListener('mouseleave', Swal.resumeTimer)
            }
        });

        Toast.fire({
            icon: 'success',
            title: 'Data mutasi berhasil diperbarui!'
        });
    }

    // Tambahkan CSS khusus untuk mutasi
    document.head.insertAdjacentHTML('beforeend', `
        <style>
            /* Styling khusus untuk popup mutasi */
            .mutasi-popup {
                background: linear-gradient(145deg, #ffffff, #f0f7ff) !important;
            }

            .mutasi-icon {
                color: #2196F3 !important;
                font-size: 3.5rem;
                margin-bottom: 25px;
            }

            /* Toast notification untuk mutasi */
            .mutasi-toast {
                background: linear-gradient(135deg, #2196F3, #1976D2) !important;
            }

            /* Animasi khusus untuk ikon mutasi */
            .mutasi-icon .fa-exchange-alt {
                animation: slideExchange 1.5s infinite;
            }

            @keyframes slideExchange {
                0% { transform: translateX(-10px); }
                50% { transform: translateX(10px); }
                100% { transform: translateX(-10px); }
            }

            /* Efek hover untuk tombol mutasi */
            .btn-mutasi {
                position: relative;
                overflow: hidden;
            }

            .btn-mutasi:after {
                content: '';
                position: absolute;
                top: 50%;
                left: 50%;
                width: 0;
                height: 0;
                background: rgba(255,255,255,0.2);
                border-radius: 50%;
                transform: translate(-50%, -50%);
                transition: width 0.3s ease, height 0.3s ease;
            }

            .btn-mutasi:hover:after {
                width: 200%;
                height: 200%;
            }

            /* Status badge untuk mutasi */
            .mutasi-status {
                padding: 8px 15px;
                border-radius: 50px;
                font-weight: 500;
                transition: all 0.3s ease;
            }

            .mutasi-status:hover {
                transform: translateY(-2px);
                box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            }

            /* Loading animation untuk mutasi */
            .mutasi-loading {
                position: relative;
            }

            .mutasi-loading:before {
                content: '';
                position: absolute;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: linear-gradient(90deg, 
                    transparent 25%, 
                    rgba(33, 150, 243, 0.1) 50%, 
                    transparent 75%
                );
                background-size: 200% 100%;
                animation: shimmer 1.5s infinite;
            }

            @keyframes shimmer {
                0% { background-position: -200% 0; }
                100% { background-position: 200% 0; }
            }
        </style>
    `);

    // Perbaikan fungsi clear search
    function clearSearch(event) {
        event.preventDefault(); // Mencegah form submit default
        const searchInput = document.querySelector('.search-input');
        const searchForm = document.getElementById('searchForm');
        
        // Reset nilai input
        searchInput.value = '';
        
        // Menyimpan nilai page_promosi dan page_mutasi yang ada
        const pagePromosi = new URLSearchParams(window.location.search).get('page_promosi');
        const pageMutasi = new URLSearchParams(window.location.search).get('page_mutasi');
        
        // Membuat hidden input untuk mempertahankan nilai pagination
        if (pagePromosi) {
            const promosiInput = document.createElement('input');
            promosiInput.type = 'hidden';
            promosiInput.name = 'page_promosi';
            promosiInput.value = pagePromosi;
            searchForm.appendChild(promosiInput);
        }
        
        if (pageMutasi) {
            const mutasiInput = document.createElement('input');
            mutasiInput.type = 'hidden';
            mutasiInput.name = 'page_mutasi';
            mutasiInput.value = pageMutasi;
            searchForm.appendChild(mutasiInput);
        }
        
        // Submit form
        searchForm.submit();
    }

    // Dynamic clear button visibility
    document.querySelector('.search-input').addEventListener('input', function() {
        let clearButton = document.querySelector('.search-clear');
        
        // Jika tombol clear belum ada dan ada text di input, buat tombol clear
        if (!clearButton && this.value) {
            clearButton = document.createElement('i');
            clearButton.className = 'fas fa-times search-clear';
            clearButton.onclick = clearSearch;
            this.parentNode.appendChild(clearButton);
        }
        
        // Toggle visibility
        if (clearButton) {
            clearButton.style.display = this.value ? 'flex' : 'none';
        }
    });

    // Tambahkan event listener untuk form submission
    document.getElementById('searchForm').addEventListener('submit', function(event) {
        const searchInput = this.querySelector('.search-input');
        if (!searchInput.value.trim()) {
            event.preventDefault();
            return false;
        }
    });
    </script>
</body>

</html>

<?php
include '../template/footer.php';
?>

