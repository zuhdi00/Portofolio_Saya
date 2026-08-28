<?php
/**
 * hris/presensi/admin_pin.php
 * Halaman HR/IT: set/reset PIN pegawai & reset device binding (saat pegawai ganti HP).
 * TODO: lindungi dengan login admin sebelum dipakai produksi.
 */
include '../config/koneksi_sqlsrv.php';

$pesan = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nik  = trim($_POST['nik'] ?? '');
    $aksi = $_POST['aksi'] ?? '';

    if ($aksi === 'set_pin') {
        $pin = trim($_POST['pin'] ?? '');
        if (strlen($pin) < 4) {
            $pesan = 'PIN minimal 4 digit';
        } else {
            $hash = password_hash($pin, PASSWORD_DEFAULT);
            $st = sqlsrv_query($conn, "UPDATE dbo.pegawai_lengkap SET pin_hash = ? WHERE nik = ?", [$hash, $nik]);
            $pesan = ($st && sqlsrv_rows_affected($st) > 0) ? "PIN untuk NIK $nik berhasil di-set" : "NIK $nik tidak ditemukan";
        }
    } elseif ($aksi === 'reset_device') {
        $st = sqlsrv_query($conn, "UPDATE dbo.pegawai_lengkap SET device_id = NULL WHERE nik = ?", [$nik]);
        $pesan = ($st && sqlsrv_rows_affected($st) > 0) ? "Device binding NIK $nik direset — HP baru akan terikat saat presensi berikutnya" : "NIK $nik tidak ditemukan";
    }
}

// 20 log terakhir untuk monitoring kecurangan
$logs = [];
$st = sqlsrv_query($conn,
    "SELECT TOP 20 waktu, nik, ip, hasil, keterangan FROM dbo.presensi_log ORDER BY id DESC");
if ($st) while ($r = sqlsrv_fetch_array($st, SQLSRV_FETCH_ASSOC)) $logs[] = $r;
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Admin Presensi QR</title>
    <style>
        body { font-family: Arial; margin: 30px; background: #f5f5f5; }
        h2 { color: #c00; }
        form { background: #fff; padding: 18px; border-radius: 10px; margin-bottom: 16px;
               display: inline-block; margin-right: 16px; vertical-align: top; }
        input, button { padding: 8px 12px; margin: 4px 0; }
        button { background: #c00; color: #fff; border: none; border-radius: 6px; cursor: pointer; }
        .pesan { background: #0f6e56; color: #fff; padding: 10px 16px; border-radius: 8px; display: inline-block; }
        table { border-collapse: collapse; background: #fff; margin-top: 20px; }
        th, td { border: 1px solid #ccc; padding: 6px 12px; font-size: .85rem; }
        th { background: #eee; }
        .SUKSES { color: #0f6e56; font-weight: 700; }
        .PIN_SALAH, .DEVICE_BEDA, .IP_LUAR, .NIK_INVALID, .TOKEN_EXPIRED { color: #a32d2d; font-weight: 700; }
    </style>
</head>
<body>
    <h2>Admin Presensi QR</h2>
    <?php if ($pesan): ?><div class="pesan"><?= htmlspecialchars($pesan) ?></div><br><br><?php endif; ?>

    <form method="POST">
        <b>Set / Reset PIN</b><br>
        <input type="hidden" name="aksi" value="set_pin">
        <input name="nik" placeholder="NIK" required><br>
        <input name="pin" placeholder="PIN baru (min 4 digit)" required><br>
        <button>Simpan PIN</button>
    </form>

    <form method="POST">
        <b>Reset Device (pegawai ganti HP)</b><br>
        <input type="hidden" name="aksi" value="reset_device">
        <input name="nik" placeholder="NIK" required><br>
        <button>Reset Device Binding</button>
    </form>

    <h3>20 Aktivitas Scan Terakhir</h3>
    <table>
        <tr><th>Waktu</th><th>NIK</th><th>IP</th><th>Hasil</th><th>Keterangan</th></tr>
        <?php foreach ($logs as $l): ?>
        <tr>
            <td><?= $l['waktu'] instanceof DateTime ? $l['waktu']->format('d-m-Y H:i:s') : '' ?></td>
            <td><?= htmlspecialchars($l['nik'] ?? '-') ?></td>
            <td><?= htmlspecialchars($l['ip'] ?? '-') ?></td>
            <td class="<?= htmlspecialchars($l['hasil']) ?>"><?= htmlspecialchars($l['hasil']) ?></td>
            <td><?= htmlspecialchars($l['keterangan'] ?? '') ?></td>
        </tr>
        <?php endforeach; ?>
    </table>
</body>
</html>
