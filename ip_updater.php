<?php
/**
 * ============================================================
 *  ip_updater.php  —  Kirim IP publik PC ke hosting
 * ============================================================
 *  Letakkan di PC: C:\xampp\htdocs\ip_updater.php
 *  Akses di browser PC: http://localhost/ip_updater.php
 *
 *  Cara otomatis (Task Scheduler Windows):
 *    Program : C:\xampp\php\php.exe
 *    Argumen : C:\xampp\htdocs\ip_updater.php
 *    Jadwal  : Setiap 30 menit (atau saat startup)
 * ============================================================
 */

// =================== KONFIGURASI ===================
$receiverUrl = 'https://supracor.co.id/realisasi/ip_receiver.php';
$secretKey   = 'Supracor@2026';  // Harus sama dengan ip_receiver.php
// ====================================================


// --- 1. Dapatkan IP publik saat ini ---
$ip = null;
$usedService = '';

$ipServices = [
    'https://api.ipify.org',
    'https://icanhazip.com',
    'https://checkip.amazonaws.com',
    'https://api4.my-ip.io/ip',
];

foreach ($ipServices as $service) {
    $ch = curl_init($service);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 5,
        CURLOPT_SSL_VERIFYPEER => false,
    ]);
    $result = curl_exec($ch);
    curl_close($ch);

    if ($result) {
        $candidate = trim($result);
        if (filter_var($candidate, FILTER_VALIDATE_IP)) {
            $ip = $candidate;
            $usedService = $service;
            break;
        }
    }
}

if (!$ip) {
    renderPage('error', null, null, 'Gagal mendapatkan IP publik dari semua layanan. Periksa koneksi internet.');
    exit;
}


// --- 2. Kirim IP ke hosting ---
$payload = json_encode([
    'ip'     => $ip,
    'secret' => $secretKey,
    'time'   => date('Y-m-d H:i:s'),
    'host'   => gethostname(),
]);

$ch = curl_init($receiverUrl);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $payload,
    CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
    CURLOPT_TIMEOUT        => 10,
    CURLOPT_SSL_VERIFYPEER => true,
]);

$response  = curl_exec($ch);
$httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);


// --- 3. Tampilkan hasil ---
if ($curlError) {
    renderPage('error', $ip, $usedService, 'Gagal kirim ke hosting: ' . $curlError);
} elseif ($httpCode === 200) {
    $result = json_decode($response, true);
    renderPage('success', $ip, $usedService, $result['message'] ?? 'OK');
} else {
    renderPage('error', $ip, $usedService, "Server merespons HTTP {$httpCode}: {$response}");
}


// ============================================================
//  Helper render halaman
// ============================================================
function renderPage($status, $ip, $service, $message) {
    // Jika dipanggil dari CLI (Task Scheduler), output teks biasa
    if (php_sapi_name() === 'cli') {
        $icon = $status === 'success' ? '[OK]' : '[ERROR]';
        echo "{$icon} IP: {$ip} | {$message}" . PHP_EOL;
        return;
    }

    $bgColor  = $status === 'success' ? '#d4edda' : '#f8d7da';
    $bdColor  = $status === 'success' ? '#28a745'  : '#dc3545';
    $icon     = $status === 'success' ? '✅' : '❌';
    $title    = $status === 'success' ? 'IP Berhasil Diupdate' : 'Gagal Update IP';
    $ipHtml   = $ip ? "<p>IP publik saat ini: <code>" . htmlspecialchars($ip) . "</code></p>" : '';
    $svcHtml  = $service ? "<p>Sumber IP: <code>" . htmlspecialchars($service) . "</code></p>" : '';
    $time     = date('Y-m-d H:i:s');

    header('Content-Type: text/html; charset=utf-8');
    echo <<<HTML
    <!DOCTYPE html>
    <html lang="id">
    <head>
      <meta charset="UTF-8">
      <meta name="viewport" content="width=device-width, initial-scale=1">
      <title>IP Updater</title>
      <style>
        body  { font-family: Arial, sans-serif; max-width: 520px; margin: 80px auto; padding: 20px; background: #f5f5f5; }
        .box  { background: {$bgColor}; border: 1px solid {$bdColor}; border-radius: 8px; padding: 24px; }
        h3    { margin-top: 0; }
        code  { background: rgba(0,0,0,.08); padding: 2px 7px; border-radius: 4px; }
        .note { margin-top: 20px; color: #666; font-size: 13px; line-height: 1.6; }
        a     { color: #0066cc; }
      </style>
    </head>
    <body>
      <div class="box">
        <h3>{$icon} {$title}</h3>
        {$ipHtml}
        {$svcHtml}
        <p>Waktu: <code>{$time}</code></p>
        <p>Pesan: {$message}</p>
      </div>
      <p class="note">
        Akses halaman ini setiap kali IP publik PC berubah.<br>
        Atau otomatis dengan <b>Task Scheduler Windows</b>:<br>
        Program: <code>C:\xampp\php\php.exe</code><br>
        Argumen: <code>C:\xampp\htdocs\ip_updater.php</code>
      </p>
    </body>
    </html>
    HTML;
}
?>
