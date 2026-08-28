<?php
/**
 * hris/presensi/absen.php
 * Halaman yang terbuka di HP pegawai setelah scan QR kiosk.
 * Validasi berlapis: token masih hidup → IP WiFi kantor → NIK+PIN → device binding.
 */
include 'qr_config.php';
include '../config/koneksi_sqlsrv.php';

$ip    = $_SERVER['REMOTE_ADDR'] ?? '';
$token = $_GET['t'] ?? $_POST['t'] ?? '';

function tolak($judul, $pesan) {
    echo "<!DOCTYPE html><html lang='id'><head><meta charset='utf-8'>
    <meta name='viewport' content='width=device-width,initial-scale=1'>
    <style>body{font-family:Arial;background:#a32d2d;color:#fff;display:flex;flex-direction:column;
    align-items:center;justify-content:center;min-height:100vh;margin:0;text-align:center;padding:20px}
    h2{margin:0 0 8px}</style></head><body><h2>$judul</h2><p>$pesan</p></body></html>";
    exit;
}

function catat_log($conn, $nik, $ip, $dev, $hasil, $ket = null) {
    sqlsrv_query($conn,
        "INSERT INTO dbo.presensi_log (nik, ip, device_id, hasil, keterangan) VALUES (?,?,?,?,?)",
        [$nik, $ip, $dev, $hasil, $ket]);
}

// ---------- Lapis 1: token QR masih hidup ----------
if ($token === '' || !qr_check_token($token)) {
    catat_log($conn, null, $ip, null, 'TOKEN_EXPIRED');
    tolak('QR Kadaluarsa', 'Scan ulang QR terbaru di layar kiosk. QR hanya berlaku ' . QR_WINDOW . ' detik.');
}

// ---------- Lapis 2: harus dari jaringan kantor ----------
if (!ip_kantor($ip)) {
    catat_log($conn, null, $ip, null, 'IP_LUAR');
    tolak('Di Luar Jaringan Kantor', "Presensi hanya bisa dari WiFi kantor. IP Anda: $ip");
}

// ---------- Proses submit NIK + PIN ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nik = trim($_POST['nik'] ?? '');
    $pin = trim($_POST['pin'] ?? '');
    $dev = substr(trim($_POST['device'] ?? ''), 0, 64);

    $st = sqlsrv_query($conn,
        "SELECT TOP 1 id, nama, pin_hash, device_id, jam_masuk, jam_pulang
         FROM dbo.pegawai_lengkap WHERE nik = ?", [$nik]);
    $peg = $st ? sqlsrv_fetch_array($st, SQLSRV_FETCH_ASSOC) : null;

    if (!$peg) {
        catat_log($conn, $nik, $ip, $dev, 'NIK_INVALID');
        tolak('NIK Tidak Terdaftar', "NIK $nik tidak ditemukan. Hubungi HR.");
    }
    // Lapis 3: PIN
    if (empty($peg['pin_hash']) || !password_verify($pin, $peg['pin_hash'])) {
        catat_log($conn, $nik, $ip, $dev, 'PIN_SALAH');
        tolak('PIN Salah', 'PIN tidak cocok. Percobaan ini dicatat.');
    }
    // Lapis 4: device binding (HP pertama yang dipakai akan terkunci ke NIK ini)
    if (empty($peg['device_id'])) {
        sqlsrv_query($conn, "UPDATE dbo.pegawai_lengkap SET device_id = ? WHERE id = ?", [$dev, $peg['id']]);
    } elseif ($peg['device_id'] !== $dev) {
        catat_log($conn, $nik, $ip, $dev, 'DEVICE_BEDA', 'terdaftar: ' . $peg['device_id']);
        tolak('Perangkat Berbeda', 'NIK ini terikat ke HP lain. Ganti HP? Minta reset ke HR/IT.');
    }

    // ---------- Semua lolos → catat masuk / pulang (logika sama dgn barcode) ----------
    $idPeg = (int)$peg['id'];
    $jamMasukStd = $peg['jam_masuk'] instanceof DateTime ? $peg['jam_masuk']->format('H:i:s') : '08:00:00';

    $st = sqlsrv_query($conn,
        "SELECT TOP 1 ID_Absensi, Jam_Pulang FROM dbo.absensi
         WHERE ID_Pegawai = ? AND CAST(Tanggal_Waktu AS DATE) = CAST(GETDATE() AS DATE)", [$idPeg]);
    $hariIni = $st ? sqlsrv_fetch_array($st, SQLSRV_FETCH_ASSOC) : null;

    if (!$hariIni) {
        $telat  = (date('H:i:s') > $jamMasukStd);
        $status = $telat ? 'Terlambat' : 'Hadir';
        sqlsrv_query($conn,
            "INSERT INTO dbo.absensi (ID_Absensi, ID_Pegawai, Tanggal_Waktu, Status_Kehadiran, Metode_Verifikasi, Lokasi_IP)
             VALUES (NEXT VALUE FOR dbo.seq_absensi, ?, GETDATE(), ?, N'QR Dinamis', ?)",
            [$idPeg, $status, $ip]);
        $judul = 'MASUK ' . date('H:i');
        $pesan = $telat ? 'Status: TERLAMBAT (jadwal ' . substr($jamMasukStd,0,5) . ')' : 'Tepat waktu. Selamat bekerja!';
        $warna = $telat ? '#854f0b' : '#0f6e56';
    } elseif ($hariIni['Jam_Pulang'] === null) {
        sqlsrv_query($conn, "UPDATE dbo.absensi SET Jam_Pulang = GETDATE() WHERE ID_Absensi = ?", [$hariIni['ID_Absensi']]);
        $judul = 'PULANG ' . date('H:i');
        $pesan = 'Hati-hati di jalan!';
        $warna = '#0f6e56';
    } else {
        $judul = 'Sudah Lengkap';
        $pesan = 'Anda sudah presensi masuk & pulang hari ini.';
        $warna = '#5f5e5a';
    }
    catat_log($conn, $nik, $ip, $dev, 'SUKSES', $judul);

    echo "<!DOCTYPE html><html lang='id'><head><meta charset='utf-8'>
    <meta name='viewport' content='width=device-width,initial-scale=1'>
    <style>body{font-family:Arial;background:$warna;color:#fff;display:flex;flex-direction:column;
    align-items:center;justify-content:center;min-height:100vh;margin:0;text-align:center;padding:20px}
    h1{margin:0 0 6px}.n{font-size:1.3rem;font-weight:700;margin-bottom:14px}</style></head><body>
    <div class='n'>" . htmlspecialchars($peg['nama']) . "</div><h1>$judul</h1><p>$pesan</p></body></html>";
    exit;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Konfirmasi Presensi</title>
    <style>
        body { font-family: Arial; background: #1a1a2e; color: #fff; display: flex; flex-direction: column;
               align-items: center; justify-content: center; min-height: 100vh; margin: 0; padding: 20px; }
        h2 { color: #e94560; }
        input { font-size: 1.3rem; padding: 12px; width: 260px; margin-bottom: 12px; border-radius: 8px;
                border: none; text-align: center; }
        button { font-size: 1.2rem; padding: 12px 40px; background: #e94560; color: #fff; border: none;
                 border-radius: 8px; }
        .info { color: #888; font-size: .8rem; margin-top: 18px; text-align: center; }
    </style>
</head>
<body>
    <h2>Konfirmasi Identitas</h2>
    <form method="POST">
        <input type="hidden" name="t" value="<?= htmlspecialchars($token) ?>">
        <input type="hidden" name="device" id="device">
        <input type="text" name="nik" placeholder="NIK" inputmode="numeric" required autofocus><br>
        <input type="password" name="pin" placeholder="PIN" inputmode="numeric" maxlength="6" required><br>
        <button type="submit">PRESENSI</button>
    </form>
    <div class="info">HP ini akan terikat ke NIK Anda saat presensi pertama.<br>Selesaikan sebelum QR kadaluarsa.</div>
<script>
// ID perangkat sederhana: dibuat sekali, tersimpan permanen di browser HP
let dev = localStorage.getItem('spsDeviceId');
if (!dev) {
    dev = crypto.randomUUID ? crypto.randomUUID() : (Date.now() + '-' + Math.random().toString(36).slice(2));
    localStorage.setItem('spsDeviceId', dev);
}
document.getElementById('device').value = dev;
</script>
</body>
</html>
