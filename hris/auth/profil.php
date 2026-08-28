<?php
/**
 * auth/profil.php
 * My Profile + Account Settings (seperlunya): lihat data akun,
 * ubah nama tampilan, ganti password sendiri.
 */
require_once __DIR__ . '/auth.php';
wajib_login();
require_once __DIR__ . '/../config/koneksi_sqlsrv.php';

$u = user_login();
$idUser = $u['id_user'];
$pesan = '';

/* ---------- ambil data akun terbaru ---------- */
$st = sqlsrv_query($conn,
  "SELECT u.username, u.nama_lengkap, u.peran, u.department_id, u.login_terakhir, u.dibuat_pada, d.nama_dept
   FROM dbo.hris_users u LEFT JOIN dbo.department d ON d.id_dept=u.department_id
   WHERE u.id_user=?", [$idUser]);
$akun = $st ? sqlsrv_fetch_array($st, SQLSRV_FETCH_ASSOC) : null;
if (!$akun) die("Akun tidak ditemukan.");

/* ---------- update profil (nama) ---------- */
if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['aksi']??'')==='profil') {
    $nama = trim($_POST['nama_lengkap'] ?? '');
    if ($nama==='') {
        $pesan = "<div class='alert alert-danger'>Nama tidak boleh kosong.</div>";
    } else {
        sqlsrv_query($conn, "UPDATE dbo.hris_users SET nama_lengkap=? WHERE id_user=?", [$nama, $idUser]);
        $_SESSION['hris_user']['nama_lengkap'] = $nama;   // segarkan session
        $akun['nama_lengkap'] = $nama;
        $pesan = "<div class='alert alert-success'>Nama profil diperbarui.</div>";
    }
}

/* ---------- ganti password ---------- */
if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['aksi']??'')==='password') {
    $lama = $_POST['pw_lama'] ?? '';
    $baru = $_POST['pw_baru'] ?? '';
    $ulang= $_POST['pw_ulang'] ?? '';

    // ambil hash sekarang
    $st = sqlsrv_query($conn, "SELECT password_hash FROM dbo.hris_users WHERE id_user=?", [$idUser]);
    $row = $st ? sqlsrv_fetch_array($st, SQLSRV_FETCH_ASSOC) : null;

    if (!$row || !password_verify($lama, $row['password_hash'])) {
        $pesan = "<div class='alert alert-danger'>Password lama salah.</div>";
    } elseif (strlen($baru) < 5) {
        $pesan = "<div class='alert alert-danger'>Password baru minimal 5 karakter.</div>";
    } elseif ($baru !== $ulang) {
        $pesan = "<div class='alert alert-danger'>Konfirmasi password tidak cocok.</div>";
    } else {
        $hash = password_hash($baru, PASSWORD_DEFAULT);
        sqlsrv_query($conn, "UPDATE dbo.hris_users SET password_hash=? WHERE id_user=?", [$hash, $idUser]);
        $pesan = "<div class='alert alert-success'>Password berhasil diganti.</div>";
    }
}

$page_title = "Profil Saya";
include '../template/header.php';
include '../template/sidebar.php';
function h($v){return htmlspecialchars((string)($v??''));}
function waktu($v){return $v instanceof DateTime?$v->format('d-m-Y H:i'):'—';}
$peranLabel = ['admin_it'=>'Administrator IT','hr'=>'HR','atasan'=>'Atasan','admin_divisi'=>'Admin Divisi','user'=>'User'][$akun['peran']] ?? $akun['peran'];
?>

<main id="main" class="main">
  <div class="pagetitle"><h1>Profil Saya</h1>
    <nav><ol class="breadcrumb"><li class="breadcrumb-item"><a href="../index.php">Home</a></li>
    <li class="breadcrumb-item active">Profil</li></ol></nav></div>

  <section class="section">
    <?= $pesan ?>
    <div class="row">

      <!-- KARTU INFO AKUN -->
      <div class="col-lg-4">
        <div class="card"><div class="card-body text-center pt-4">
          <img src="<?php echo BASE_URL; ?>/hris/assets/img/profile-img.jpg" alt="Profile" class="rounded-circle" style="width:110px">
          <h3 class="mt-3 mb-0"><?= h($akun['nama_lengkap']) ?></h3>
          <span class="badge bg-primary mt-1"><?= h($peranLabel) ?></span>
        </div></div>
      </div>

      <!-- TAB DETAIL / EDIT / PASSWORD -->
      <div class="col-lg-8">
        <div class="card"><div class="card-body pt-3">
          <ul class="nav nav-tabs nav-tabs-bordered">
            <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tab-detail">Detail</button></li>
            <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-edit">Edit Profil</button></li>
            <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-pw">Ganti Password</button></li>
          </ul>

          <div class="tab-content pt-3">
            <!-- DETAIL -->
            <div class="tab-pane fade show active" id="tab-detail">
              <div class="row mb-2"><div class="col-4 text-muted">Username</div><div class="col-8"><?= h($akun['username']) ?></div></div>
              <div class="row mb-2"><div class="col-4 text-muted">Nama Lengkap</div><div class="col-8"><?= h($akun['nama_lengkap']) ?></div></div>
              <div class="row mb-2"><div class="col-4 text-muted">Peran</div><div class="col-8"><?= h($peranLabel) ?></div></div>
              <div class="row mb-2"><div class="col-4 text-muted">Divisi</div><div class="col-8"><?= h($akun['nama_dept'] ?? '—') ?></div></div>
              <div class="row mb-2"><div class="col-4 text-muted">Login Terakhir</div><div class="col-8"><?= waktu($akun['login_terakhir']) ?></div></div>
              <div class="row mb-2"><div class="col-4 text-muted">Akun Dibuat</div><div class="col-8"><?= waktu($akun['dibuat_pada']) ?></div></div>
            </div>

            <!-- EDIT PROFIL -->
            <div class="tab-pane fade" id="tab-edit">
              <form method="POST">
                <input type="hidden" name="aksi" value="profil">
                <div class="row mb-3">
                  <label class="col-md-4 col-form-label">Username</label>
                  <div class="col-md-8"><input type="text" class="form-control" value="<?= h($akun['username']) ?>" disabled>
                    <small class="text-muted">Username tidak bisa diubah sendiri. Hubungi Admin IT.</small></div>
                </div>
                <div class="row mb-3">
                  <label class="col-md-4 col-form-label">Nama Lengkap</label>
                  <div class="col-md-8"><input type="text" name="nama_lengkap" class="form-control" value="<?= h($akun['nama_lengkap']) ?>" required></div>
                </div>
                <div class="text-center"><button class="btn btn-primary">Simpan Perubahan</button></div>
              </form>
            </div>

            <!-- GANTI PASSWORD -->
            <div class="tab-pane fade" id="tab-pw">
              <form method="POST">
                <input type="hidden" name="aksi" value="password">
                <div class="row mb-3">
                  <label class="col-md-4 col-form-label">Password Lama</label>
                  <div class="col-md-8"><input type="password" name="pw_lama" class="form-control" required></div>
                </div>
                <div class="row mb-3">
                  <label class="col-md-4 col-form-label">Password Baru</label>
                  <div class="col-md-8"><input type="password" name="pw_baru" class="form-control" required>
                    <small class="text-muted">Minimal 5 karakter.</small></div>
                </div>
                <div class="row mb-3">
                  <label class="col-md-4 col-form-label">Ulangi Password Baru</label>
                  <div class="col-md-8"><input type="password" name="pw_ulang" class="form-control" required></div>
                </div>
                <div class="text-center"><button class="btn btn-primary">Ganti Password</button></div>
              </form>
            </div>
          </div>
        </div></div>
      </div>
    </div>
  </section>
</main>

<?php include '../template/footer.php'; ?>
