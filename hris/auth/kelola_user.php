<?php
/**
 * auth/kelola_user.php - CRUD akun HRIS (khusus admin IT)
 */
require_once __DIR__ . '/auth.php';
wajib_peran(['admin_it']);
require_once __DIR__ . '/../config/koneksi_sqlsrv.php';

$pesan = '';

/* ---------- aksi: tambah / edit / reset password / aktif-nonaktif ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $aksi = $_POST['aksi'] ?? '';

    if ($aksi === 'tambah') {
        $u  = trim($_POST['username'] ?? '');
        $nm = trim($_POST['nama_lengkap'] ?? '');
        $pr = $_POST['peran'] ?? 'user';
        $pw = $_POST['password'] ?? '';
        $dept = (int)($_POST['department_id'] ?? 0) ?: null;

        if ($u==='' || $nm==='' || $pw==='') {
            $pesan = "<div class='alert alert-danger'>Username, nama, dan password wajib diisi.</div>";
        } else {
            $hash = password_hash($pw, PASSWORD_DEFAULT);
            $st = sqlsrv_query($conn,
                "INSERT INTO dbo.hris_users (username,password_hash,nama_lengkap,peran,department_id)
                 VALUES (?,?,?,?,?)", [$u,$hash,$nm,$pr,$dept]);
            $pesan = $st===false
                ? "<div class='alert alert-danger'>Gagal (username mungkin sudah dipakai).</div>"
                : "<div class='alert alert-success'>Akun <strong>".htmlspecialchars($u)."</strong> dibuat.</div>";
        }
    }
    elseif ($aksi === 'reset_pw') {
        $id = (int)($_POST['id_user'] ?? 0);
        $pw = $_POST['password_baru'] ?? '';
        if ($id && $pw!=='') {
            $hash = password_hash($pw, PASSWORD_DEFAULT);
            sqlsrv_query($conn, "UPDATE dbo.hris_users SET password_hash=? WHERE id_user=?", [$hash,$id]);
            $pesan = "<div class='alert alert-success'>Password direset.</div>";
        }
    }
    elseif ($aksi === 'ubah_peran') {
        $id = (int)($_POST['id_user'] ?? 0);
        $pr = $_POST['peran'] ?? 'user';
        $dept = (int)($_POST['department_id'] ?? 0) ?: null;
        if ($id) {
            sqlsrv_query($conn, "UPDATE dbo.hris_users SET peran=?, department_id=? WHERE id_user=?", [$pr,$dept,$id]);
            $pesan = "<div class='alert alert-success'>Peran diperbarui.</div>";
        }
    }
    elseif ($aksi === 'toggle_aktif') {
        $id = (int)($_POST['id_user'] ?? 0);
        // jangan matikan diri sendiri
        if ($id && $id != user_login()['id_user']) {
            sqlsrv_query($conn, "UPDATE dbo.hris_users SET is_aktif = 1 - is_aktif WHERE id_user=?", [$id]);
            $pesan = "<div class='alert alert-info'>Status akun diubah.</div>";
        } else {
            $pesan = "<div class='alert alert-warning'>Tidak bisa menonaktifkan akun sendiri.</div>";
        }
    }
}

/* ---------- data ---------- */
$deptList=[]; $rs=sqlsrv_query($conn,"SELECT id_dept,nama_dept FROM dbo.department ORDER BY nama_dept");
while($r=sqlsrv_fetch_array($rs,SQLSRV_FETCH_ASSOC)) $deptList[]=$r;

$users=[]; $rs=sqlsrv_query($conn,
    "SELECT u.*, d.nama_dept FROM dbo.hris_users u
     LEFT JOIN dbo.department d ON d.id_dept=u.department_id ORDER BY u.peran, u.username");
while($r=sqlsrv_fetch_array($rs,SQLSRV_FETCH_ASSOC)) $users[]=$r;

$page_title="Kelola User";
include '../template/header.php';
include '../template/sidebar.php';
function h($v){return htmlspecialchars((string)($v??''));}
$PERAN = ['admin_it'=>'Administrator IT','hr'=>'HR','atasan'=>'Atasan','admin_divisi'=>'Admin Divisi','user'=>'User'];
function optPeran($PERAN,$dipilih){ $o=''; foreach($PERAN as $k=>$v){$s=$k===$dipilih?' selected':'';$o.="<option value='$k'$s>$v</option>";} return $o; }
function optDept($list,$dipilih){ $o="<option value=''>— semua —</option>"; foreach($list as $d){$s=$d['id_dept']==$dipilih?' selected':'';$o.="<option value='{$d['id_dept']}'$s>".htmlspecialchars($d['nama_dept'])."</option>";} return $o; }
?>

<main id="main" class="main">
  <div class="pagetitle"><h1>Kelola User</h1>
    <nav><ol class="breadcrumb"><li class="breadcrumb-item"><a href="../index.php">Home</a></li>
    <li class="breadcrumb-item active">Kelola User</li></ol></nav></div>

  <section class="section">
    <?= $pesan ?>
    <div class="row">

      <!-- TAMBAH -->
      <div class="col-lg-4">
        <div class="card"><div class="card-body">
          <h5 class="card-title">Tambah Akun</h5>
          <form method="POST">
            <input type="hidden" name="aksi" value="tambah">
            <div class="mb-2"><label class="form-label small">Username</label>
              <input type="text" name="username" class="form-control" required></div>
            <div class="mb-2"><label class="form-label small">Nama Lengkap</label>
              <input type="text" name="nama_lengkap" class="form-control" required></div>
            <div class="mb-2"><label class="form-label small">Password</label>
              <input type="text" name="password" class="form-control" required
                     placeholder="password awal"></div>
            <div class="mb-2"><label class="form-label small">Peran</label>
              <select name="peran" class="form-select"><?= optPeran($PERAN,'user') ?></select></div>
            <div class="mb-3"><label class="form-label small">Divisi (utk atasan/admin divisi)</label>
              <select name="department_id" class="form-select"><?= optDept($deptList,0) ?></select></div>
            <button class="btn btn-primary w-100"><i class="bi bi-person-plus"></i> Buat Akun</button>
          </form>
        </div></div>
      </div>

      <!-- DAFTAR -->
      <div class="col-lg-8">
        <div class="card"><div class="card-body">
          <h5 class="card-title">Daftar Akun (<?= count($users) ?>)</h5>
          <div class="table-responsive">
          <table class="table table-hover align-middle">
            <thead class="table-light"><tr>
              <th>Username</th><th>Nama</th><th>Peran</th><th>Divisi</th><th>Status</th><th>Aksi</th>
            </tr></thead>
            <tbody>
            <?php foreach($users as $u): ?>
              <tr>
                <td><strong><?= h($u['username']) ?></strong></td>
                <td><?= h($u['nama_lengkap']) ?></td>
                <td>
                  <form method="POST" class="d-flex gap-1">
                    <input type="hidden" name="aksi" value="ubah_peran">
                    <input type="hidden" name="id_user" value="<?= $u['id_user'] ?>">
                    <select name="peran" class="form-select form-select-sm" style="width:130px"><?= optPeran($PERAN,$u['peran']) ?></select>
                    <select name="department_id" class="form-select form-select-sm" style="width:120px"><?= optDept($deptList,$u['department_id']) ?></select>
                    <button class="btn btn-sm btn-outline-primary" title="simpan peran"><i class="bi bi-save"></i></button>
                  </form>
                </td>
                <td><small><?= h($u['nama_dept']??'—') ?></small></td>
                <td><?= $u['is_aktif']
                    ? '<span class="badge bg-success">aktif</span>'
                    : '<span class="badge bg-secondary">nonaktif</span>' ?></td>
                <td class="d-flex gap-1">
                  <!-- reset password -->
                  <form method="POST" onsubmit="return resetPw(this)">
                    <input type="hidden" name="aksi" value="reset_pw">
                    <input type="hidden" name="id_user" value="<?= $u['id_user'] ?>">
                    <input type="hidden" name="password_baru" value="">
                    <button class="btn btn-sm btn-outline-warning" title="reset password"><i class="bi bi-key"></i></button>
                  </form>
                  <!-- aktif/nonaktif -->
                  <form method="POST">
                    <input type="hidden" name="aksi" value="toggle_aktif">
                    <input type="hidden" name="id_user" value="<?= $u['id_user'] ?>">
                    <button class="btn btn-sm btn-outline-secondary" title="aktif/nonaktif"><i class="bi bi-power"></i></button>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
          </div>
        </div></div>
      </div>

    </div>
  </section>
</main>

<script>
function resetPw(form){
  const pw = prompt('Password baru untuk akun ini:');
  if(!pw) return false;
  form.password_baru.value = pw;
  return true;
}
</script>

<?php include '../template/footer.php'; ?>
