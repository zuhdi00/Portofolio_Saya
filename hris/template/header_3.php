<!DOCTYPE html>
<html lang="en">
<?php

include 'bootstrap.php';

// nama & peran user yang login (untuk ditampilkan di header)
if (session_status() === PHP_SESSION_NONE) session_start();
$__u = $_SESSION['hris_user'] ?? null;
$__nama = $__u['nama_lengkap'] ?? 'Administrator';
$__peran = $__u['peran'] ?? '';
$__peranLabel = [
    'admin_it'=>'Administrator IT','hr'=>'HR','atasan'=>'Atasan',
    'admin_divisi'=>'Admin Divisi','user'=>'User'
][$__peran] ?? 'Pengguna';

// --- Ambil notifikasi dari database ---
require_once __DIR__ . '/../config/koneksi_sqlsrv.php';
$notif_count = 0;
$notif_items = [];
if ($__u && isset($__u['id_user'])) {
    // Ambil jumlah unread
    $sql_count = "SELECT COUNT(*) as n FROM dbo.hris_notifications WHERE user_id = ? AND is_read = 0";
    $st_c = sqlsrv_query($conn, $sql_count, [$__u['id_user']]);
    if ($st_c) {
        $row_c = sqlsrv_fetch_array($st_c, SQLSRV_FETCH_ASSOC);
        $notif_count = $row_c['n'] ?? 0;
    }

    // Ambil 5 notifikasi terbaru (termasuk yang read, tapi unread di atas/ditandai)
    $sql_list = "SELECT TOP 5 id, judul, pesan, link, is_read, created_at 
                 FROM dbo.hris_notifications 
                 WHERE user_id = ? 
                 ORDER BY is_read ASC, created_at DESC";
    $st_l = sqlsrv_query($conn, $sql_list, [$__u['id_user']]);
    if ($st_l) {
        while ($r = sqlsrv_fetch_array($st_l, SQLSRV_FETCH_ASSOC)) {
            $r['virtual'] = false;
            $notif_items[] = $r;
        }
    }

    // Tampilkan pengajuan yang masih menunggu review tanpa membuat duplikat permanen.
    $reviewer = in_array($__peran, ['hr', 'admin_it'], true);
    if ($reviewer) {
        $pendingResign = sqlsrv_query($conn,
            "SELECT TOP 5 id_pengunduran, no_surat, nama_pegawai, status, dibuat_pada
             FROM dbo.pengunduran_diri
             WHERE status IN ('DIAJUKAN', 'BERKAS_MASUK')
             ORDER BY dibuat_pada DESC, id_pengunduran DESC");
        if ($pendingResign) {
            while ($r = sqlsrv_fetch_array($pendingResign, SQLSRV_FETCH_ASSOC)) {
                $notif_items[] = [
                    'id' => null,
                    'judul' => 'Pengunduran Diri Menunggu Review',
                    'pesan' => ($r['no_surat'] ?: 'Pengajuan baru') . ' - ' . ($r['nama_pegawai'] ?: 'Nama tidak tersedia'),
                    'link' => BASE_URL . '/hris/pengunduran_diri/index.php',
                    'is_read' => 0,
                    'created_at' => $r['dibuat_pada'],
                    'virtual' => true,
                ];
                $notif_count++;
            }
        }

        $pendingLembur = sqlsrv_query($conn,
            "SELECT TOP 5 id_form, no_form, dibuat_pada
             FROM dbo.lembur_form
             WHERE status = 'DIAJUKAN'
             ORDER BY dibuat_pada DESC, id_form DESC");
        if ($pendingLembur) {
            while ($r = sqlsrv_fetch_array($pendingLembur, SQLSRV_FETCH_ASSOC)) {
                $notif_items[] = [
                    'id' => null,
                    'judul' => 'Pengajuan Lembur Menunggu Review',
                    'pesan' => ($r['no_form'] ?: 'Form lembur baru') . ' masih dalam pengajuan.',
                    'link' => BASE_URL . '/hris/lembur/approval_hr.php',
                    'is_read' => 0,
                    'created_at' => $r['dibuat_pada'],
                    'virtual' => true,
                ];
                $notif_count++;
            }
        }
    }

    usort($notif_items, function ($left, $right) {
        $leftTime = $left['created_at'] instanceof DateTime ? $left['created_at']->getTimestamp() : 0;
        $rightTime = $right['created_at'] instanceof DateTime ? $right['created_at']->getTimestamp() : 0;
        return $rightTime <=> $leftTime;
    });
    $notif_items = array_slice($notif_items, 0, 5);
}
?>

<head>
    <meta charset="utf-8">
    <meta content="width=device-width, initial-scale=1.0" name="viewport">

    <title>Dashboard - Aplikasi HRIS</title>
    <meta content="" name="description">
    <meta content="" name="keywords">

    <!-- Favicons -->
    <link href="<?php echo BASE_URL; ?>/hris/assets/img/SPS_Logo1.png" rel="icon">
    <link href="<?php echo BASE_URL; ?>/hris/assets/img/apple-touch-icon.png" rel="apple-touch-icon">

    <!-- Google Fonts -->
    <link href="https://fonts.gstatic.com" rel="preconnect">
    <link href="https://fonts.googleapis.com/css?family=Open+Sans:300,300i,400,400i,600,600i,700,700i|Nunito:300,300i,400,400i,600,600i,700,700i|Poppins:300,300i,400,400i,500,500i,600,600i,700,700i" rel="stylesheet">

    <!-- Vendor CSS Files -->
    <link href="<?php echo BASE_URL; ?>/hris/assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="<?php echo BASE_URL; ?>/hris/assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
    <link href="<?php echo BASE_URL; ?>/hris/assets/vendor/boxicons/css/boxicons.min.css" rel="stylesheet">
    <link href="<?php echo BASE_URL; ?>/hris/assets/vendor/quill/quill.snow.css" rel="stylesheet">
    <link href="<?php echo BASE_URL; ?>/hris/assets/vendor/quill/quill.bubble.css" rel="stylesheet">
    <link href="<?php echo BASE_URL; ?>/hris/assets/vendor/remixicon/remixicon.css" rel="stylesheet">
    <link href="<?php echo BASE_URL; ?>/hris/assets/vendor/simple-datatables/style.css" rel="stylesheet">
    <!-- Include Bootstrap CSS -->
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/5.1.3/css/bootstrap.min.css" rel="stylesheet">

<!-- Include Bootstrap JS -->
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/5.1.3/js/bootstrap.bundle.min.js"></script>

    <!-- Template Main CSS File -->
    <link href="<?php echo BASE_URL; ?>/hris/assets/css/style.css" rel="stylesheet">

    <!-- =======================================================
  * Template Name: NiceAdmin
  * Template URL: https://bootstrapmade.com/nice-admin-bootstrap-admin-html-template/
  * Updated: Apr 20 2024 with Bootstrap v5.3.3
  * Author: BootstrapMade.com
  * License: https://bootstrapmade.com/license/
  ======================================================== -->
</head>

<body>

    <!-- ======= Header ======= -->
    <header id="header" class="header fixed-top d-flex align-items-center">

        <div class="d-flex align-items-center justify-content-between">
            <a href="index.html" class="logo d-flex align-items-center">
                <img src="<?php echo BASE_URL; ?>/hris/assets/img/logo.png" alt="">
                <span class="d-none d-lg-block">HRIS</span>
            </a>
            <i class="bi bi-list toggle-sidebar-btn"></i>
        </div><!-- End Logo -->

        <div class="search-bar">
            <form class="search-form d-flex align-items-center" method="POST" action="#">
                <input type="text" name="query" placeholder="Search" title="Enter search keyword">
                <button type="submit" title="Search"><i class="bi bi-search"></i></button>
            </form>
        </div><!-- End Search Bar -->

        <nav class="header-nav ms-auto">
            <ul class="d-flex align-items-center">

                <li class="nav-item d-block d-lg-none">
                    <a class="nav-link nav-icon search-bar-toggle " href="#">
                        <i class="bi bi-search"></i>
                    </a>
                </li><!-- End Search Icon-->

                <li class="nav-item dropdown">

                    <a class="nav-link nav-icon" href="#" data-bs-toggle="dropdown">
                        <i class="bi bi-bell"></i>
                        <?php if ($notif_count > 0): ?>
                        <span class="badge bg-primary badge-number"><?= $notif_count ?></span>
                        <?php endif; ?>
                    </a><!-- End Notification Icon -->

                    <ul class="dropdown-menu dropdown-menu-end dropdown-menu-arrow notifications" style="max-height: 400px; overflow-y: auto;">
                        <li class="dropdown-header">
                            You have <?= $notif_count ?> new notifications
                            <a href="<?php echo BASE_URL; ?>/hris/notifikasi.php"><span class="badge rounded-pill bg-primary p-2 ms-2">View all</span></a>
                        </li>
                        <li>
                            <hr class="dropdown-divider">
                        </li>

                        <?php if (empty($notif_items)): ?>
                            <li class="notification-item">
                                <div>
                                    <p class="text-center text-muted">Tidak ada notifikasi.</p>
                                </div>
                            </li>
                        <?php else: ?>
                            <?php foreach ($notif_items as $n): ?>
                                <li class="notification-item <?= $n['is_read'] == 0 ? 'bg-light' : '' ?>">
                                    <i class="bi bi-info-circle text-primary"></i>
                                    <a href="<?= $n['virtual'] ? htmlspecialchars($n['link']) : BASE_URL . '/hris/notifikasi_read.php?id=' . (int)$n['id'] ?>" class="text-dark d-block">
                                        <div>
                                            <h4><?= htmlspecialchars($n['judul']) ?></h4>
                                            <p><?= htmlspecialchars($n['pesan']) ?></p>
                                            <p><?= $n['created_at']->format('Y-m-d H:i') ?></p>
                                        </div>
                                    </a>
                                </li>
                                <li>
                                    <hr class="dropdown-divider">
                                </li>
                            <?php endforeach; ?>
                        <?php endif; ?>

                        <li class="dropdown-footer">
                            <a href="<?php echo BASE_URL; ?>/hris/notifikasi.php">Show all notifications</a>
                        </li>

                    </ul><!-- End Notification Dropdown Items -->

                </li><!-- End Notification Nav -->

                <li class="nav-item dropdown" style="display:none"><!-- pesan disembunyikan -->

                    <a class="nav-link nav-icon" href="#" data-bs-toggle="dropdown">
                        <i class="bi bi-chat-left-text"></i>
                        <span class="badge bg-success badge-number">3</span>
                    </a><!-- End Messages Icon -->

                    <ul class="dropdown-menu dropdown-menu-end dropdown-menu-arrow messages">
                        <li class="dropdown-header">
                            You have 3 new messages
                            <a href="#"><span class="badge rounded-pill bg-primary p-2 ms-2">View all</span></a>
                        </li>
                        <li>
                            <hr class="dropdown-divider">
                        </li>

                        <li class="message-item">
                            <a href="#">
                                <img src="<?php echo BASE_URL; ?>/hris/assets/img/messages-1.jpg" alt="" class="rounded-circle">
                                <div>
                                    <h4>Maria Hudson</h4>
                                    <p>Velit asperiores et ducimus soluta repudiandae labore officia est ut...</p>
                                    <p>4 hrs. ago</p>
                                </div>
                            </a>
                        </li>
                        <li>
                            <hr class="dropdown-divider">
                        </li>

                        <li class="message-item">
                            <a href="#">
                                <img src="<?php echo BASE_URL; ?>/hris/assets/img/messages-2.jpg" alt="" class="rounded-circle">
                                <div>
                                    <h4>Anna Nelson</h4>
                                    <p>Velit asperiores et ducimus soluta repudiandae labore officia est ut...</p>
                                    <p>6 hrs. ago</p>
                                </div>
                            </a>
                        </li>
                        <li>
                            <hr class="dropdown-divider">
                        </li>

                        <li class="message-item">
                            <a href="#">
                                <img src="<?php echo BASE_URL; ?>/hris/assets/img/messages-3.jpg" alt="" class="rounded-circle">
                                <div>
                                    <h4>David Muldon</h4>
                                    <p>Velit asperiores et ducimus soluta repudiandae labore officia est ut...</p>
                                    <p>8 hrs. ago</p>
                                </div>
                            </a>
                        </li>
                        <li>
                            <hr class="dropdown-divider">
                        </li>

                        <li class="dropdown-footer">
                            <a href="#">Show all messages</a>
                        </li>

                    </ul><!-- End Messages Dropdown Items -->

                </li><!-- End Messages Nav -->

                <li class="nav-item dropdown pe-3">

                    <a class="nav-link nav-profile d-flex align-items-center pe-0" href="#" data-bs-toggle="dropdown">
                        <img src="<?php echo BASE_URL; ?>/hris/assets/img/profile-img.jpg" alt="Profile" class="rounded-circle">
                        <span class="d-none d-md-block dropdown-toggle ps-2"><?= htmlspecialchars($__nama) ?></span>
                    </a><!-- End Profile Iamge Icon -->

                    <ul class="dropdown-menu dropdown-menu-end dropdown-menu-arrow profile">
                        <li class="dropdown-header">
                            <h6><?= htmlspecialchars($__nama) ?></h6>
                            <span><?= htmlspecialchars($__peranLabel) ?></span>
                        </li>
                        <li>
                            <hr class="dropdown-divider">
                        </li>

                        <li>
                            <a class="dropdown-item d-flex align-items-center" href="users-profile.html">
                                <i class="bi bi-person"></i>
                                <span>My Profile</span>
                            </a>
                        </li>
                        <li>
                            <hr class="dropdown-divider">
                        </li>

                        <li>
                            <a class="dropdown-item d-flex align-items-center" href="users-profile.html">
                                <i class="bi bi-gear"></i>
                                <span>Account Settings</span>
                            </a>
                        </li>
                        <li>
                            <hr class="dropdown-divider">
                        </li>

                        <li>
                            <a class="dropdown-item d-flex align-items-center" href="pages-faq.html">
                                <i class="bi bi-question-circle"></i>
                                <span>Need Help?</span>
                            </a>
                        </li>
                        <li>
                            <hr class="dropdown-divider">
                        </li>

                        <li>
                            <a class="dropdown-item d-flex align-items-center" href="<?php echo BASE_URL; ?>/hris/auth/logout.php">
                                <i class="bi bi-box-arrow-right"></i>
                                <span>Logout</span>
                            </a>
                        </li>

                    </ul><!-- End Profile Dropdown Items -->
                </li><!-- End Profile Nav -->

            </ul>
        </nav><!-- End Icons Navigation -->

    </header><!-- End Header -->
    <!-- Include Bootstrap CSS -->
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/5.1.3/css/bootstrap.min.css" rel="stylesheet">

    <!-- Include Bootstrap JS -->
     <script src="https://stackpath.bootstrapcdn.com/bootstrap/5.1.3/js/bootstrap.bundle.min.js"></script>
</body>    