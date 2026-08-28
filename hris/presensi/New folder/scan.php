<?php /* hris/presensi/scan.php — halaman kiosk presensi barcode */ ?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Presensi Barcode — PT Supracor Sejahtera</title>
    <style>
        * { box-sizing: border-box; font-family: 'Segoe UI', Arial, sans-serif; }
        body { margin: 0; background: #1a1a2e; color: #fff; display: flex; flex-direction: column;
               align-items: center; justify-content: center; min-height: 100vh; }
        h1 { color: #e94560; margin-bottom: 4px; }
        #clock { font-size: 3.2rem; font-weight: 700; letter-spacing: 2px; }
        #tanggal { color: #aaa; margin-bottom: 28px; }
        #barcodeInput { font-size: 1.6rem; padding: 14px 20px; width: 420px; max-width: 90vw;
               border-radius: 10px; border: 3px solid #e94560; text-align: center; outline: none; }
        #hasil { margin-top: 26px; padding: 22px 34px; border-radius: 12px; min-width: 420px;
               max-width: 90vw; text-align: center; display: none; }
        .ok    { background: #0f6e56; }
        .telat { background: #854f0b; }
        .gagal { background: #a32d2d; }
        #hasil .nama   { font-size: 1.6rem; font-weight: 700; }
        #hasil .status { font-size: 1.1rem; margin-top: 6px; }
        .hint { margin-top: 34px; color: #666; font-size: .85rem; }
    </style>
</head>
<body>
    <h1>PRESENSI PEGAWAI</h1>
    <div id="clock">--:--:--</div>
    <div id="tanggal"></div>

    <input type="text" id="barcodeInput" placeholder="Scan barcode / ketik NIK lalu Enter" autofocus autocomplete="off">

    <div id="hasil">
        <div class="nama"></div>
        <div class="status"></div>
    </div>

    <div class="hint">Arahkan kartu pegawai ke scanner. Fokus input otomatis kembali setelah 5 detik.</div>

<script>
const input = document.getElementById('barcodeInput');
const hasil = document.getElementById('hasil');

function jam() {
    const d = new Date();
    document.getElementById('clock').textContent = d.toLocaleTimeString('id-ID');
    document.getElementById('tanggal').textContent =
        d.toLocaleDateString('id-ID', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' });
}
setInterval(jam, 1000); jam();

// scanner USB bertindak seperti keyboard: ketik cepat lalu Enter
input.addEventListener('keydown', async function (e) {
    if (e.key !== 'Enter') return;
    const kode = input.value.trim();
    input.value = '';
    if (!kode) return;

    try {
        const res = await fetch('proses_scan.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'barcode=' + encodeURIComponent(kode)
        });
        const d = await res.json();
        hasil.className = d.ok ? (d.telat ? 'telat' : 'ok') : 'gagal';
        hasil.querySelector('.nama').textContent = d.nama || 'TIDAK DIKENAL';
        hasil.querySelector('.status').textContent = d.pesan;
    } catch (err) {
        hasil.className = 'gagal';
        hasil.querySelector('.nama').textContent = 'ERROR';
        hasil.querySelector('.status').textContent = 'Tidak bisa terhubung ke server';
    }
    hasil.style.display = 'block';
    setTimeout(() => { hasil.style.display = 'none'; input.focus(); }, 5000);
});

// jaga fokus tetap di input (kiosk mode)
setInterval(() => { if (document.activeElement !== input) input.focus(); }, 3000);
</script>
</body>
</html>
