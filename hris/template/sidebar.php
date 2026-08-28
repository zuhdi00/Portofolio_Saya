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

        <!-- ============ KEPEGAWAIAN ============ -->
        <?php if (boleh('pegawai_lihat') || boleh('modul_hr')): ?>
        <li class="nav-item">
            <a class="nav-link collapsed" data-bs-target="#kepegawaian-nav" data-bs-toggle="collapse" href="#">
                <i class="bi bi-person"></i><span>Kepegawaian</span><i class="bi bi-chevron-down ms-auto"></i>
            </a>
            <ul id="kepegawaian-nav" class="nav-content collapse " data-bs-parent="#sidebar-nav">
                <?php if (boleh('pegawai_lihat')): ?>
                <li>
                    <a href="<?php echo BASE_URL; ?>/hris/pegawai/index.php">
                        <i class="bi bi-circle"></i><span>Data Pegawai</span>
                    </a>
                </li>
                <?php endif; ?>
                <?php if (boleh('modul_hr')): ?>
                <li>
                    <a href="<?php echo BASE_URL; ?>/hris/kontrak_pegawai/index.php">
                        <i class="bi bi-circle"></i><span>Kontrak Pegawai</span>
                    </a>
                </li>
                <li>
                    <a href="<?php echo BASE_URL; ?>/hris/manajemen promosi dan mutasi/index.php">
                        <i class="bi bi-circle"></i><span>Promosi dan Mutasi</span>
                    </a>
                </li>
                <li>
                    <a href="<?php echo BASE_URL; ?>/hris/pegawai dan kedisiplinan/index.php">
                        <i class="bi bi-circle"></i><span>Kedisiplinan</span>
                    </a>
                </li>
                <li>
                    <a href="<?php echo BASE_URL; ?>/hris/reward/index.php">
                        <i class="bi bi-circle"></i><span>Reward</span>
                    </a>
                </li>
                <?php endif; ?>
            </ul>
        </li>
        <?php endif; ?>

        <!-- ============ PRESENSI & ABSENSI ============ -->
        <li class="nav-item">
            <a class="nav-link collapsed" data-bs-target="#absensi-nav" data-bs-toggle="collapse" href="#">
                <i class="bi bi-calendar-check"></i><span>Presensi & Absensi</span><i class="bi bi-chevron-down ms-auto"></i>
            </a>
            <ul id="absensi-nav" class="nav-content collapse " data-bs-parent="#sidebar-nav">
                <?php if (boleh('dashboard')): ?>
                <li>
                    <a href="<?php echo BASE_URL; ?>/hris/presensi/qr_kiosk.php">
                        <i class="bi bi-circle"></i><span>Kiosk Presensi</span>
                    </a>
                </li>
                <?php endif; ?>
                
                <?php if (boleh('absensi_rekap') || boleh('absensi_sendiri')): ?>
                <!-- REKAP ABSENSI LAYERS -->
                <li>
                    <a class="nav-link collapsed" data-bs-target="#rekap-absen-nav" data-bs-toggle="collapse" href="#" style="background:transparent; font-size:15px; font-weight:600; padding:10px 15px 10px 40px; color:#012970;"> <i class="bi bi-circle"></i>
                        <span>Rekap Absensi</span><i class="bi bi-chevron-down ms-auto" style="font-size:14px;"></i>
                    </a>
                    <ul id="rekap-absen-nav" class="nav-content collapse" data-bs-parent="#absensi-nav" style="padding-left:15px;">
                        <li>
                            <a href="<?php echo BASE_URL; ?>/hris/absensi/rekap.php?status_kary=staff">
                                <i class="bi bi-circle"></i><span>Staff</span>
                            </a>
                        </li>
                        <li>
                            <a href="javascript:void(0)" onclick="promptPasswordLainnya()">
                                <i class="bi bi-circle"></i><span>Lainnya</span>
                            </a>
                        </li>
                    </ul>
                </li>
                <?php endif; ?>

                <?php if (boleh('koreksi_approval')): ?>
                <li>
                    <a href="<?php echo BASE_URL; ?>/hris/presensi/approval_koreksi.php">
                        <i class="bi bi-circle"></i><span>Approval Koreksi Absen</span>
                    </a>
                </li>
                <?php endif; ?>
                <?php if (boleh('lupa_absen_input')): ?>
                <li>
                    <a href="<?php echo BASE_URL; ?>/hris/lupa_absen/input.php">
                        <i class="bi bi-circle"></i><span>Input Lupa Absen</span>
                    </a>
                </li>
                <?php endif; ?>
                <?php if (boleh('modul_hr')): ?>
                <li>
                    <a href="<?php echo BASE_URL; ?>/hris/lupa_absen/approval_hr.php">
                        <i class="bi bi-circle"></i><span>Approval Lupa Absen</span>
                    </a>
                </li>
                <?php endif; ?>
            </ul>
        </li>

        <!-- ============ LEMBUR ============ -->
        <?php if (boleh('lembur_input') || boleh('lembur_rekap')): ?>
        <li class="nav-item">
            <a class="nav-link collapsed" data-bs-target="#lembur-nav" data-bs-toggle="collapse" href="#">
                <i class="bi bi-clock"></i><span>Lembur</span><i class="bi bi-chevron-down ms-auto"></i>
            </a>
            <ul id="lembur-nav" class="nav-content collapse " data-bs-parent="#sidebar-nav">
                <?php if (boleh('lembur_input')): ?>
                <li>
                    <a href="<?php echo BASE_URL; ?>/hris/lembur/input.php">
                        <i class="bi bi-circle"></i><span>Input Lembur Divisi</span>
                    </a>
                </li>
                <?php endif; ?>
                <?php if (boleh('lembur_rekap')): ?>
                <li>
                    <a href="<?php echo BASE_URL; ?>/hris/lembur/rekap_hr.php">
                        <i class="bi bi-circle"></i><span>Rekap Lembur (HR)</span>
                    </a>
                </li>
                <li>
                    <a href="<?php echo BASE_URL; ?>/hris/lembur/approval_hr.php">
                        <i class="bi bi-circle"></i><span>Approval Lembur (HR)</span>
                    </a>
                </li>
                <?php endif; ?>
            </ul>
        </li>
        <?php endif; ?>

        <!-- ============ PENGUNDURAN DIRI ============ -->
        <?php if (boleh('resign_nilai') || boleh('modul_hr')): ?>
        <li class="nav-item">
            <a class="nav-link collapsed" data-bs-target="#resign-nav" data-bs-toggle="collapse" href="#">
                <i class="bi bi-box-arrow-right"></i><span>Pengunduran Diri</span><i class="bi bi-chevron-down ms-auto"></i>
            </a>
            <ul id="resign-nav" class="nav-content collapse " data-bs-parent="#sidebar-nav">
                <?php if (boleh('resign_nilai')): ?>
                <li>
                    <a href="<?php echo BASE_URL; ?>/hris/pengunduran_diri/penilaian_saya.php">
                        <i class="bi bi-circle"></i><span>Penilaian Divisi</span>
                    </a>
                </li>
                <?php endif; ?>
                <?php if (boleh('modul_hr')): ?>
                <li>
                    <a href="<?php echo BASE_URL; ?>/hris/pengunduran_diri/index.php">
                        <i class="bi bi-circle"></i><span>Data Pengunduran Diri</span>
                    </a>
                </li>
                <li>
                    <a href="<?php echo BASE_URL; ?>/hris/pengunduran_diri/daftar_penilaian.php">
                        <i class="bi bi-circle"></i><span>Penilaian Resign</span>
                    </a>
                </li>
                <li>
                    <a href="<?php echo BASE_URL; ?>/hris/pengunduran_diri/kelola_klasifikasi.php">
                        <i class="bi bi-circle"></i><span>Klasifikasi Resign</span>
                    </a>
                </li>
                <li>
                    <a href="<?php echo BASE_URL; ?>/hris/phk/index.php">
                        <i class="bi bi-circle"></i><span>PHK</span>
                    </a>
                </li>
                <?php endif; ?>
            </ul>
        </li>
        <?php endif; ?>

        <!-- ============ ADMINISTRASI HR ============ -->
        <?php if (boleh('modul_hr')): ?>
        <li class="nav-item">
            <a class="nav-link collapsed" data-bs-target="#admin-hr-nav" data-bs-toggle="collapse" href="#">
                <i class="bi bi-briefcase"></i><span>Administrasi HR</span><i class="bi bi-chevron-down ms-auto"></i>
            </a>
            <ul id="admin-hr-nav" class="nav-content collapse " data-bs-parent="#sidebar-nav">
                <li>
                    <a href="<?php echo BASE_URL; ?>/hris/recruitment/index.php">
                        <i class="bi bi-circle"></i><span>Recruitment</span>
                    </a>
                </li>
                <li>
                    <a href="<?php echo BASE_URL; ?>/hris/pegawai/transfer_zkteco.php">
                        <i class="bi bi-arrow-left-right"></i><span>Transfer Data ZKTeco</span>
                    </a>
                </li>
                <li>
                    <a href="<?php echo BASE_URL; ?>/hris/pelatihan/index.php">
                        <i class="bi bi-circle"></i><span>Pelatihan</span>
                    </a>
                </li>
                <li>
                    <a href="<?php echo BASE_URL; ?>/hris/analisis_sdm/index.php">
                        <i class="bi bi-circle"></i><span>Analis SDM</span>
                    </a>
                </li>
                <li>
                    <a href="<?php echo BASE_URL; ?>/hris/laporan_sdm/index.php">
                        <i class="bi bi-circle"></i><span>Laporan SDM</span>
                    </a>
                </li>
                <li>
                    <a href="<?php echo BASE_URL; ?>/hris/slip_gaji/index.php">
                        <i class="bi bi-circle"></i><span>Slip Gaji</span>
                    </a>
                </li>
            </ul>
        </li>
        <?php endif; ?>

        <!-- ============ KELOLA USER (admin IT) ============ -->
        <?php if (function_exists('peran_saya') && peran_saya()==='admin_it'): ?>
        <li class="nav-item">
            <a class="nav-link collapsed" href="<?php echo BASE_URL; ?>/hris/auth/kelola_user.php">
                <i class="bi bi-people-fill"></i><span>Kelola User</span>
            </a>
        </li>
        <?php endif; ?>

        <li class="nav-heading">Pages</li>
        <li class="nav-item">
            <a class="nav-link collapsed" href="#">
                <i class="bi bi-envelope"></i>
                <span>Contact</span>
            </a>
        </li>
    </ul>
</aside><!-- End Sidebar-->

<!-- Custom JS for Prompt Lainnya -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
function promptPasswordLainnya() {
    Swal.fire({
        title: 'Masukkan Password',
        text: 'Anda memerlukan verifikasi password untuk mengakses rekap ini',
        input: 'password',
        inputAttributes: {
            autocapitalize: 'off',
            autocorrect: 'off'
        },
        showCancelButton: true,
        confirmButtonText: 'Verifikasi',
        showLoaderOnConfirm: true,
        preConfirm: (password) => {
            if (!password) {
                Swal.showValidationMessage('Password wajib diisi');
                return;
            }
            return fetch('<?php echo BASE_URL; ?>/hris/auth/verify_password_ajax.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'password=' + encodeURIComponent(password)
            })
            .then(response => {
                if (!response.ok) throw new Error(response.statusText)
                return response.json()
            })
            .catch(error => {
                Swal.showValidationMessage(`Request failed: ${error}`)
            });
        },
        allowOutsideClick: () => !Swal.isLoading()
    }).then((result) => {
        if (result.isConfirmed) {
            if (result.value && result.value.success) {
                window.location.href = '<?php echo BASE_URL; ?>/hris/absensi/rekap.php?status_kary=lainnya';
            } else {
                Swal.fire('Gagal', result.value.message || 'Password salah!', 'error');
            }
        }
    })
}
</script>
