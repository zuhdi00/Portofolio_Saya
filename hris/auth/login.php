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
  <link rel="icon" type="image/png" href="/hris/assets/img/logo.png">
  <style>
    *{box-sizing:border-box;margin:0;padding:0}
    body{font-family:'Segoe UI',sans-serif;background: url('../assets/img/Background.png') no-repeat center center fixed; background-size: cover;
         min-height:100vh;display:flex;align-items:center;justify-content:center;
         position: relative; z-index: 1;}
    body::before {
         content: ""; position: absolute; top: 0; left: 0; width: 100%; height: 100%;
         background: rgba(0, 0, 0, 0.4); z-index: -1;
    }
    .box{background:rgba(255, 255, 255, 0.95);border-radius:16px;box-shadow:0 15px 35px rgba(0,0,0,0.5);
         width:100%;max-width:400px;padding:40px 34px;backdrop-filter: blur(10px);border: 1px solid rgba(255,255,255,0.2);}
    .logo{text-align:center;margin-bottom:24px}
    .logo img { max-width: 150px; height: auto; margin-bottom: 15px; }
    .logo h1{color:#c00;font-size:1.6rem;font-weight:700;}
    .logo p{color:#555;font-size:.9rem;margin-top:4px;font-weight:500;}
    label{display:block;font-size:.85rem;color:#444;margin-bottom:6px;font-weight:600}
    input{width:100%;padding:12px 14px;border:1px solid #ccc;border-radius:8px;
          font-size:.95rem;margin-bottom:20px;transition: border-color 0.3s, box-shadow 0.3s;}
    input:focus{outline:none;border-color:#c00;box-shadow: 0 0 0 3px rgba(204, 0, 0, 0.1);}
    button{width:100%;padding:13px;background:linear-gradient(135deg, #d32f2f, #b71c1c);color:#fff;border:none;border-radius:8px;
           font-size:1rem;font-weight:600;cursor:pointer;transition: transform 0.2s, box-shadow 0.2s;}
    button:hover{transform: translateY(-2px);box-shadow: 0 6px 15px rgba(183, 28, 28, 0.4);}
    button:active{transform: translateY(0);}
    .err{background:#ffebee;color:#c62828;padding:12px 14px;border-radius:8px;font-size:.85rem;margin-bottom:20px;border:1px solid #ffcdd2;}
  </style>
</head>
<body>
  <div class="box">
    <div class="logo">
      <img src="../assets/img/logo.png" alt="SPS Logo">
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
