<?php
/**
 * hris/presensi/cetak_barcode.php
 * Cetak kartu barcode pegawai (Code128, isi = NIK).
 * Butuh internet sekali untuk JsBarcode CDN — atau download jsbarcode.all.min.js
 * ke assets/vendor/ lalu ganti src di bawah agar full offline.
 */
include '../config/koneksi_sqlsrv.php';

$rows = [];
$st = sqlsrv_query($conn,
    "SELECT nik, nama, job_title, unit_kerja, ISNULL(barcode, nik) AS barcode
     FROM dbo.pegawai_lengkap ORDER BY nama");
while ($r = sqlsrv_fetch_array($st, SQLSRV_FETCH_ASSOC)) $rows[] = $r;
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Cetak Kartu Barcode Pegawai</title>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jsbarcode/3.11.5/JsBarcode.all.min.js"></script>
    <style>
        body { font-family: Arial, sans-serif; background: #eee; margin: 20px; }
        .toolbar { margin-bottom: 16px; }
        .grid { display: flex; flex-wrap: wrap; gap: 14px; }
        .kartu { width: 320px; background: #fff; border: 1px solid #999; border-radius: 10px;
                 padding: 14px; text-align: center; page-break-inside: avoid; }
        .kartu .perusahaan { font-weight: 700; color: #c00; font-size: .8rem; }
        .kartu .nama { font-weight: 700; margin: 6px 0 2px; font-size: 1rem; }
        .kartu .jabatan { color: #555; font-size: .78rem; margin-bottom: 6px; }
        svg { width: 100%; height: 70px; }
        @media print {
            .toolbar { display: none; }
            body { background: #fff; }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <button onclick="window.print()">🖨 Cetak Semua Kartu</button>
        <input type="text" id="cari" placeholder="Cari nama..." oninput="filterKartu(this.value)">
    </div>

    <div class="grid">
        <?php foreach ($rows as $i => $r): ?>
        <div class="kartu" data-nama="<?= strtolower(htmlspecialchars($r['nama'])) ?>">
            <div class="perusahaan">PT SUPRACOR SEJAHTERA</div>
            <div class="nama"><?= htmlspecialchars($r['nama']) ?></div>
            <div class="jabatan"><?= htmlspecialchars($r['job_title'] ?? '-') ?> — NIK <?= htmlspecialchars($r['nik']) ?></div>
            <svg id="bc<?= $i ?>"></svg>
        </div>
        <?php endforeach; ?>
    </div>

<script>
const data = <?= json_encode(array_column($rows, 'barcode')) ?>;
data.forEach((kode, i) => {
    JsBarcode('#bc' + i, kode, { format: 'CODE128', displayValue: true, fontSize: 14, height: 48, margin: 4 });
});
function filterKartu(q) {
    q = q.toLowerCase();
    document.querySelectorAll('.kartu').forEach(k =>
        k.style.display = k.dataset.nama.includes(q) ? '' : 'none');
}
</script>
</body>
</html>
