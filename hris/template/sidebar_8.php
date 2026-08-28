<?php
// baca hak akses untuk menyembunyikan menu sesuai peran
if (!function_exists('boleh')) { @include_once __DIR__ . '/../auth/auth.php'; }
if (!function_exists('boleh')) { function boleh($x){ return true; } } // fallback kalau auth belum ada
?>
<!-- ======= Sidebar ======= -->
<aside id="sidebar" class="sidebar">

    <ul class="sidebar-nav" id="sidebar-nav">

        <li class="nav-item">
            <a class="nav-link " href="<?php echo BASE_URL; ?>/hris/index.php">
                <i class="bi bi-grid"></i>
                <span>Dashboard</span>
            </a>
        </li><!-- End Dashboard Nav -->
       
        <?php if (boleh('pegawai_lihat')): ?>
        <li class="nav-item">
            <a class="nav-link collapsed" href="/hris/pegawai/index.php">
                <i class="bi bi-person"></i>
                <span>Pegawai</span>      
            </a>
        </li>
        <?php endif; ?><!-- End Pegawai Page Nav -->

        <?php if (boleh('modul_hr')): ?>
        <li class="nav-item">
            <a class="nav-link collapsed" data-bs-target="#components-nav" data-bs-toggle="collapse" href="<?php echo BASE_URL; ?>/hris/reward/index.php">
                <i class="bi bi-award"></i><span>Reward</span></i>
            </a>
        </li>
        <?php endif; ?>
        <?php if (boleh('modul_hr')): ?>
        <li class="nav-item">
            <a class="nav-link collapsed" data-bs-target="#components-nav" data-bs-toggle="collapse" href="<?php echo BASE_URL; ?>/hris/pegawai dan kedisiplinan/index.php">
                <i class="bi bi-award"></i><span>Kedisiplinan</span></i>
            </a>
        </li>
        <?php endif; ?><!-- End Components Nav -->

                <!-- ============ PRESENSI & ABSENSI ============ -->
        <?php if (boleh('dashboard')): ?>
        <li class="nav-item">
            <a class="nav-link collapsed" href="<?php echo BASE_URL; ?>/hris/presensi/qr_kiosk.php">
                <i class="bi bi-qr-code-scan"></i><span>Kiosk Presensi</span></a>
        </li>
        <?php endif; ?>
        <?php if (boleh('absensi_rekap')): ?>
        <li class="nav-item">
            <a class="nav-link collapsed" href="<?php echo BASE_URL; ?>/hris/absensi/rekap.php">
                <i class="bi bi-table"></i><span>Rekap Absensi</span></a>
        </li>
        <?php endif; ?>
        <?php if (boleh('koreksi_approval')): ?>
        <li class="nav-item">
            <a class="nav-link collapsed" href="<?php echo BASE_URL; ?>/hris/presensi/approval_koreksi.php">
                <i class="bi bi-check2-square"></i><span>Approval Koreksi Absen</span></a>
        </li>
        <?php endif; ?>

        <!-- ============ LEMBUR ============ -->
        <?php if (boleh('lembur_input')): ?>
        <li class="nav-item">
            <a class="nav-link collapsed" href="<?php echo BASE_URL; ?>/hris/lembur/input.php">
                <i class="bi bi-pencil-square"></i><span>Input Lembur Divisi</span></a>
        </li>
        <?php endif; ?>
        <?php if (boleh('lembur_rekap')): ?>
        <li class="nav-item">
            <a class="nav-link collapsed" href="<?php echo BASE_URL; ?>/hris/lembur/rekap_hr.php">
                <i class="bi bi-clock-history"></i><span>Rekap Lembur (HR)</span></a>
        </li>
        <li class="nav-item">
            <a class="nav-link collapsed" href="<?php echo BASE_URL; ?>/hris/lembur/approval_hr.php">
                <i class="bi bi-check2-circle"></i><span>Approval Lembur (HR)</span></a>
        </li>
        <?php endif; ?>

        <?php if (sedang_login()): ?>
        <li class="nav-item">
            <a class="nav-link collapsed" href="<?php echo BASE_URL; ?>/hris/pengunduran_diri/pengajuan.php">
                <i class="bi bi-door-open"></i><span>Pengajuan Resign</span></a>
        </li>
        <?php endif; ?>

        <!-- ============ KELOLA USER (admin IT) ============ -->
        <?php if (function_exists('peran_saya') && peran_saya()==='admin_it'): ?>
        <li class="nav-item">
            <a class="nav-link collapsed" href="<?php echo BASE_URL; ?>/hris/auth/kelola_user.php">
                <i class="bi bi-people-fill"></i><span>Kelola User</span></a>
        </li>
        <?php endif; ?><!-- End Presensi/Lembur Nav -->

        <?php if (boleh('modul_hr')): ?>
        <li class="nav-item">
            <a class="nav-link collapsed" href="<?php echo BASE_URL; ?>/hris/recruitment/index.php">
                <i class="bi bi-briefcase"></i><span>Recruitment</span>
            </a>
        </li>
        <?php endif; ?><!-- End Recruitment Nav -->

        <?php if (boleh('modul_hr')): ?>
        <li class="nav-item">
            <a class="nav-link collapsed" href="<?php echo BASE_URL; ?>/hris/manajemen promosi dan mutasi/index.php">
            <i class="bi bi-p-square-fill"></i><span>Manajemen promosi dan mutasi</span>
        </li>
        <?php endif; ?><!-- End  Nav -->

            <?php if (boleh('modul_hr')): ?>
        <li class="nav-item">
            <a class="nav-link collapsed" href="<?php echo BASE_URL; ?>/hris/pelatihan/index.php">
                <i class="bi bi-box-seam-fill"></i><span>Pelatihan</span>
            </a>
        </li>
        <?php endif; ?>

        <a class="nav-link collapsed" href="<?php echo BASE_URL; ?>/hris/recruitment/welcome.php">
            <i class="icon flat-line">
                <svg fill="#000000" width="18px" height="18px" viewBox="0 0 24 24" id="work" data-name="Flat Line" xmlns="http://www.w3.org/2000/svg">
                    <path id="secondary" d="M20.81,7.45,19,11.58A4,4,0,0,1,15.36,14H13v1H11V14H8.64A4,4,0,0,1,5,11.58L3.19,7.45A1,1,0,0,0,3,8V20a1,1,0,0,0,1,1H20a1,1,0,0,0,1-1V8A1,1,0,0,0,20.81,7.45Z" style="fill: rgb(44, 169, 188); stroke-width: 2;"></path>
                    <path id="primary" d="M11,14H8.64A4,4,0,0,1,5,11.58L3.18,7.43A1,1,0,0,1,4,7H20a1,1,0,0,1,.82.43L19,11.58A4,4,0,0,1,15.36,14H13" style="fill: none; stroke: rgb(0, 0, 0); stroke-linecap: round; stroke-linejoin: round; stroke-width: 2;"></path>
                    <path id="primary-2" data-name="primary" d="M16,7H8V4A1,1,0,0,1,9,3h6a1,1,0,0,1,1,1Zm5,13V8a1,1,0,0,0-1-1H4A1,1,0,0,0,3,8V20a1,1,0,0,0,1,1H20A1,1,0,0,0,21,20Zm-8-7H11v2h2Z" style="fill: none; stroke: rgb(0, 0, 0); stroke-linecap: round; stroke-linejoin: round; stroke-width: 2;"></path>
                </svg>
            </i><span>Recruitment</span>
        </a>
        <?php if (boleh('modul_hr')): ?>
        <li class="nav-item">
        <a class="nav-link collapsed" href="<?php echo BASE_URL; ?>/hris/kontrak_pegawai/index.php">
            <i class="bi bi-briefcase"></i><span>Manajemen Kontrak Pegawai</span>
        </a></li>
        <li class="nav-item">
        <a class="nav-link collapsed" href="<?php echo BASE_URL; ?>/hris/analisis_sdm/index.php">
            <i class="bi bi-briefcase"></i><span>Analis SDM</span>
        </a></li>
        <li class="nav-item">
        <a class="nav-link collapsed" href="<?php echo BASE_URL; ?>/hris/laporan_sdm/index.php">
            <i class="bi bi-briefcase"></i><span>Laporan sdm</span>
        </a></li>
        <li class="nav-item">
        <a class="nav-link collapsed" href="<?php echo BASE_URL; ?>/hris/pengunduran_diri/index.php">
            <i class="bi bi-briefcase"></i><span>Pengunduran diri (HR)</span>
        </a></li>
        <li class="nav-item">
        <a class="nav-link collapsed" href="<?php echo BASE_URL; ?>/hris/phk/index.php">
            <i class="bi bi-briefcase"></i><span>PHK</span>
        </a></li>
        <li class="nav-item">
        <a class="nav-link collapsed" href="<?php echo BASE_URL; ?>/hris/slip_gaji/index.php">
            <i class="bi bi-cash-stack"></i><span>Slip Gaji</span>
        </a>   
    </li>
        <?php endif; ?><!-- End Recruitment Nav -->

      

        <?php /* ===== Menu demo bawaan template disembunyikan ===== ?>
        Tables, Charts, icon di-hide.
        <?php */ ?>

        <li class="nav-heading">Pages</li>

        <li class="nav-item">
            <a class="nav-link collapsed" href="pages-contact.html">
                <i class="bi bi-envelope"></i>
                <span>Contact</span>
            </a>
        </li><!-- End Contact Page Nav -->

    </ul>

</aside><!-- End Sidebar-->
