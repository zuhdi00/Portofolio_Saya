<!-- ======= Sidebar ======= -->
<aside id="sidebar" class="sidebar">

    <ul class="sidebar-nav" id="sidebar-nav">

        <li class="nav-item">
            <a class="nav-link " href="<?php echo BASE_URL; ?>/hris/index.php">
                <i class="bi bi-grid"></i>
                <span>Dashboard</span>
            </a>
        </li><!-- End Dashboard Nav -->
       
        <li class="nav-item">
            <a class="nav-link collapsed" href="/hris/pegawai/index.php">
                <i class="bi bi-person"></i>
                <span>Pegawai</span>      
            </a>
        </li><!-- End Pegawai Page Nav -->

        <li class="nav-item">
            <a class="nav-link collapsed" data-bs-target="#components-nav" data-bs-toggle="collapse" href="<?php echo BASE_URL; ?>/hris/reward/index.php">
                <i class="bi bi-award"></i><span>Reward</span></i>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link collapsed" data-bs-target="#components-nav" data-bs-toggle="collapse" href="<?php echo BASE_URL; ?>/hris/pegawai dan kedisiplinan/index.php">
                <i class="bi bi-award"></i><span>Kedisiplinan</span></i>
            </a>
        </li><!-- End Components Nav -->

                <!-- ============ PRESENSI & ABSENSI ============ -->
        <li class="nav-item">
            <a class="nav-link collapsed" href="<?php echo BASE_URL; ?>/hris/presensi/qr_kiosk.php">
                <i class="bi bi-qr-code-scan"></i><span>Kiosk Presensi</span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link collapsed" href="<?php echo BASE_URL; ?>/hris/absensi/rekap.php">
                <i class="bi bi-table"></i><span>Rekap Absensi</span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link collapsed" href="<?php echo BASE_URL; ?>/hris/presensi/approval_koreksi.php">
                <i class="bi bi-check2-square"></i><span>Approval Koreksi Absen</span>
            </a>
        </li>

        <!-- ============ LEMBUR ============ -->
        <li class="nav-item">
            <a class="nav-link collapsed" href="<?php echo BASE_URL; ?>/hris/lembur/input.php">
                <i class="bi bi-pencil-square"></i><span>Input Lembur Divisi</span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link collapsed" href="<?php echo BASE_URL; ?>/hris/lembur/rekap_hr.php">
                <i class="bi bi-clock-history"></i><span>Rekap Lembur (HR)</span>
            </a>
        </li><!-- End Presensi/Lembur Nav -->

        <li class="nav-item">
            <a class="nav-link collapsed" href="<?php echo BASE_URL; ?>/hris/recruitment/index.php">
                <i class="bi bi-briefcase"></i><span>Recruitment</span>
            </a>
        </li><!-- End Recruitment Nav -->

        <li class="nav-item">
            <a class="nav-link collapsed" href="<?php echo BASE_URL; ?>/hris/manajemen promosi dan mutasi/index.php">
            <i class="bi bi-p-square-fill"></i><span>Manajemen promosi dan mutasi</span>
        </li><!-- End  Nav -->

            <li class="nav-item">
            <a class="nav-link collapsed" href="<?php echo BASE_URL; ?>/hris/pelatihan/index.php">
                <i class="bi bi-box-seam-fill"></i><span>Pelatihan</span>
            </a>
        </li>

        <a class="nav-link collapsed" href="<?php echo BASE_URL; ?>/hris/recruitment/welcome.php">
            <i class="icon flat-line">
                <svg fill="#000000" width="18px" height="18px" viewBox="0 0 24 24" id="work" data-name="Flat Line" xmlns="http://www.w3.org/2000/svg">
                    <path id="secondary" d="M20.81,7.45,19,11.58A4,4,0,0,1,15.36,14H13v1H11V14H8.64A4,4,0,0,1,5,11.58L3.19,7.45A1,1,0,0,0,3,8V20a1,1,0,0,0,1,1H20a1,1,0,0,0,1-1V8A1,1,0,0,0,20.81,7.45Z" style="fill: rgb(44, 169, 188); stroke-width: 2;"></path>
                    <path id="primary" d="M11,14H8.64A4,4,0,0,1,5,11.58L3.18,7.43A1,1,0,0,1,4,7H20a1,1,0,0,1,.82.43L19,11.58A4,4,0,0,1,15.36,14H13" style="fill: none; stroke: rgb(0, 0, 0); stroke-linecap: round; stroke-linejoin: round; stroke-width: 2;"></path>
                    <path id="primary-2" data-name="primary" d="M16,7H8V4A1,1,0,0,1,9,3h6a1,1,0,0,1,1,1Zm5,13V8a1,1,0,0,0-1-1H4A1,1,0,0,0,3,8V20a1,1,0,0,0,1,1H20A1,1,0,0,0,21,20Zm-8-7H11v2h2Z" style="fill: none; stroke: rgb(0, 0, 0); stroke-linecap: round; stroke-linejoin: round; stroke-width: 2;"></path>
                </svg>
            </i><span>Recruitment</span>
        </a>
        <li class="nav-item">
        <a class="nav-link collapsed" href="<?php echo BASE_URL; ?>/hris/kontrak_pegawai/index.php">
            <i class="bi bi-briefcase"></i><span>Manajemen Kontrak Pegawai</span>
        </a>
        <li class="nav-item">
        <a class="nav-link collapsed" href="<?php echo BASE_URL; ?>/hris/analisis_sdm/index.php">
            <i class="bi bi-briefcase"></i><span>Analis SDM</span>
        </a>
        <li class="nav-item">
        <a class="nav-link collapsed" href="<?php echo BASE_URL; ?>/hris/laporan_sdm/index.php">
            <i class="bi bi-briefcase"></i><span>Laporan sdm</span>
        </a>
        <li class="nav-item">
        <a class="nav-link collapsed" href="<?php echo BASE_URL; ?>/hris/pengunduran_diri/index.php">
            <i class="bi bi-briefcase"></i><span>Pengunduran diri</span>
        </a>
        <li class="nav-item">
        <a class="nav-link collapsed" href="<?php echo BASE_URL; ?>/hris/phk/index.php">
            <i class="bi bi-briefcase"></i><span>PHK</span>
        </a>
        <li class="nav-item">
        <a class="nav-link collapsed" href="<?php echo BASE_URL; ?>/hris/slip_gaji/index.php">
            <i class="bi bi-cash-stack"></i><span>Slip Gaji</span>
        </a>   
    </li><!-- End Recruitment Nav -->

      

        <li class="nav-item">
            <a class="nav-link collapsed" data-bs-target="#tables-nav" data-bs-toggle="collapse" href="#">
                <i class="bi bi-layout-text-window-reverse"></i><span>Tables</span><i class="bi bi-chevron-down ms-auto"></i>
            </a>
            <ul id="tables-nav" class="nav-content collapse " data-bs-parent="#sidebar-nav">
                <li>
                    <a href="tables-general.html">
                        <i class="bi bi-circle"></i><span>General Tables</span>
                    </a>
                </li>
                <li>
                    <a href="tables-data.html">
                        <i class="bi bi-circle"></i><span>Data Tables</span>
                    </a>
                </li>
            </ul>
        </li><!-- End Tables Nav -->

        <li class="nav-item">
            <a class="nav-link collapsed" data-bs-target="#charts-nav" data-bs-toggle="collapse" href="#">
                <i class="bi bi-bar-chart"></i><span>Charts</span><i class="bi bi-chevron-down ms-auto"></i>
            </a>
            <ul id="charts-nav" class="nav-content collapse " data-bs-parent="#sidebar-nav">
                <li>
                    <a href="charts-chartjs.html">
                        <i class="bi bi-circle"></i><span>Chart.js</span>
                    </a>
                </li>
                <li>
                    <a href="charts-apexcharts.html">
                        <i class="bi bi-circle"></i><span>ApexCharts</span>
                    </a>
                </li>
                <li>
                    <a href="charts-echarts.html">
                        <i class="bi bi-circle"></i><span>ECharts</span>
                    </a>
                </li>
            </ul>
        </li><!-- End Charts Nav -->

        <li class="nav-item">
            <a class="nav-link collapsed" data-bs-target="#icons-nav" data-bs-toggle="collapse" href="<?php echo BASE_URL; ?>/hris/icon/index.php">
                <i class="bi bi-backpack4"></i><span>icon</span><i class="bi bi-chevron-down ms-auto"></i>
            </a>
            <ul id="icons-nav" class="nav-content collapse" data-bs-parent="#sidebar-nav">
                <li>
                    <a href="<?php echo BASE_URL; ?>/hris/icon/index.php">
                        <i class="bi bi-caret-right-fill"></i><span>icon</span>
                    </a>
                </li>
                <li>
                    <a href="<?php echo BASE_URL; ?>/hris/icon/index.php">
                        <i class="bi bi-caret-right-fill"></i><span>icon</span>
                    </a>
                </li>
            </ul>
        </li><!-- End Icons Nav -->


        <li class="nav-heading">Pages</li>

        <li class="nav-item">
            <a class="nav-link collapsed" href="users-profile.html">
                <i class="bi bi-person"></i>
                <span>Profile</span>
            </a>
        </li><!-- End Profile Page Nav -->

        <li class="nav-item">
            <a class="nav-link collapsed" href="pages-faq.html">
                <i class="bi bi-question-circle"></i>
                <span>F.A.Q</span>
            </a>
        </li><!-- End F.A.Q Page Nav -->

        <li class="nav-item">
            <a class="nav-link collapsed" href="pages-contact.html">
                <i class="bi bi-envelope"></i>
                <span>Contact</span>
            </a>
        </li><!-- End Contact Page Nav -->

        <li class="nav-item">
            <a class="nav-link collapsed" href="pages-register.html">
                <i class="bi bi-card-list"></i>
                <span>Register</span>
            </a>
        </li><!-- End Register Page Nav -->

        <li class="nav-item">
            <a class="nav-link collapsed" href="pages-login.html">
                <i class="bi bi-box-arrow-in-right"></i>
                <span>Login</span>
            </a>
        </li><!-- End Login Page Nav -->

        <li class="nav-item">
            <a class="nav-link collapsed" href="pages-error-404.html">
                <i class="bi bi-dash-circle"></i>
                <span>Error 404</span>
            </a>
        </li><!-- End Error 404 Page Nav -->

        <li class="nav-item">
            <a class="nav-link collapsed" href="pages-blank.html">
                <i class="bi bi-file-earmark"></i>
                <span>Blank</span>
            </a>
        </li><!-- End Blank Page Nav -->

    </ul>

</aside><!-- End Sidebar-->
