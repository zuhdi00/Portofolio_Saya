<?php
/**
 * hris/presensi/qr_kiosk.php
 * Layar kiosk / TV: menampilkan QR dinamis yang berganti tiap QR_WINDOW detik.
 * QR berisi URL absen + token HMAC — hanya berlaku beberapa detik.
 */
include 'qr_config.php';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Presensi QR — PT Supracor Sejahtera</title>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
    <style>
        body { margin: 0; min-height: 100vh; display: flex; flex-direction: column; align-items: center;
               justify-content: center; background: #1a1a2e; color: #fff; font-family: 'Segoe UI', Arial; }
        h1 { color: #e94560; margin: 0 0 4px; }
        #clock { font-size: 2.6rem; font-weight: 700; }
        #qr { background: #fff; padding: 22px; border-radius: 16px; margin: 24px 0 10px; }
        #timerbar { width: 300px; height: 8px; background: #333; border-radius: 4px; overflow: hidden; }
        #timerfill { height: 100%; background: #e94560; width: 100%; transition: width 1s linear; }
        .cara { color: #999; margin-top: 22px; text-align: center; font-size: .95rem; line-height: 1.6; }
    </style>
</head>
<body>
    <h1>SCAN UNTUK PRESENSI</h1>
    <div id="clock">--:--:--</div>
    <div id="qr"></div>
    <div id="timerbar"><div id="timerfill"></div></div>
    <div class="cara">
        1. Buka kamera HP &nbsp;→&nbsp; 2. Scan QR di atas &nbsp;→&nbsp; 3. Masukkan NIK + PIN<br>
        QR berganti otomatis — pastikan HP tersambung WiFi kantor
    </div>

<script>
const WINDOW = <?= QR_WINDOW ?>;
const qrDiv = document.getElementById('qr');
let qr = null, lastSlot = 0;

setInterval(() => {
    document.getElementById('clock').textContent = new Date().toLocaleTimeString('id-ID');
}, 1000);

async function refreshQR() {
    const res = await fetch('qr_token.php');
    const d = await res.json();
    if (d.slot === lastSlot) return;
    lastSlot = d.slot;
    qrDiv.innerHTML = '';
    qr = new QRCode(qrDiv, { text: d.url, width: 260, height: 260, correctLevel: QRCode.CorrectLevel.M });
}

function tickBar() {
    const sisa = WINDOW - (Math.floor(Date.now() / 1000) % WINDOW);
    document.getElementById('timerfill').style.width = (sisa / WINDOW * 100) + '%';
    if (sisa === WINDOW) refreshQR();
}

refreshQR();
setInterval(tickBar, 1000);
setInterval(refreshQR, 3000);   // jaga-jaga bila tab sempat tidur
</script>
</body>
</html>
