<?php
/**
 * auth/login.php - halaman login HRIS
 */
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../config/koneksi_sqlsrv.php';

if (sedang_login()) { header('Location: ../index.php'); exit; }

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $u = trim($_POST['username'] ?? '');
    $p = $_POST['password'] ?? '';

    if ($u === '' || $p === '') {
        $error = 'Username dan password wajib diisi.';
    } else {
        $st = sqlsrv_query($conn,
            "SELECT id_user, username, password_hash, nama_lengkap, peran, department_id, pegawai_id, is_aktif
             FROM dbo.hris_users WHERE username = ?", [$u]);
        $row = $st ? sqlsrv_fetch_array($st, SQLSRV_FETCH_ASSOC) : null;

        if (!$row || !$row['is_aktif']) {
            $error = 'Akun tidak ditemukan atau non-aktif.';
        } elseif (!password_verify($p, $row['password_hash'])) {
            $error = 'Password salah.';
        } else {
            // sukses
            $_SESSION['hris_user'] = [
                'id_user'       => $row['id_user'],
                'username'      => $row['username'],
                'nama_lengkap'  => $row['nama_lengkap'],
                'peran'         => $row['peran'],
                'department_id' => $row['department_id'],
                'pegawai_id'    => $row['pegawai_id'],
            ];
            sqlsrv_query($conn, "UPDATE dbo.hris_users SET login_terakhir=GETDATE() WHERE id_user=?", [$row['id_user']]);
            header('Location: ../index.php');
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Login — HRIS PT Supracor Sejahtera</title>
  <style>
    *{box-sizing:border-box;margin:0;padding:0}
    body{font-family:'Segoe UI',sans-serif;background:linear-gradient(135deg,#c00 0%,#800 100%);
         min-height:100vh;display:flex;align-items:center;justify-content:center}
    .box{background:#fff;border-radius:12px;box-shadow:0 10px 40px rgba(0,0,0,.3);
         width:100%;max-width:380px;padding:40px 34px}
    .logo{text-align:center;margin-bottom:24px}
    .logo h1{color:#c00;font-size:1.5rem}
    .logo p{color:#888;font-size:.85rem;margin-top:4px}
    label{display:block;font-size:.85rem;color:#555;margin-bottom:6px;font-weight:600}
    input{width:100%;padding:11px 13px;border:1px solid #ddd;border-radius:7px;
          font-size:.95rem;margin-bottom:18px}
    input:focus{outline:none;border-color:#c00}
    button{width:100%;padding:12px;background:#c00;color:#fff;border:none;border-radius:7px;
           font-size:1rem;font-weight:600;cursor:pointer}
    button:hover{background:#a00}
    .err{background:#fee;color:#c00;padding:10px 13px;border-radius:7px;font-size:.85rem;margin-bottom:18px;border:1px solid #fcc}
  </style>
</head>
<body>
  <div class="box">
    <div class="logo">
      <h1>HRIS</h1>
      <p>PT Supracor Sejahtera</p>
    </div>
    <?php if ($error): ?><div class="err"><?= htmlspecialchars($error) ?></div><?php endif; ?>
    <form method="POST">
      <label>Username</label>
      <input type="text" name="username" autofocus value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
      <label>Password</label>
      <input type="password" name="password">
      <button type="submit">Masuk</button>
    </form>
  </div>
</body>
</html>
