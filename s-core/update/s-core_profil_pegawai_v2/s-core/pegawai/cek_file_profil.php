<?php
/**
 * pegawai/cek_file_profil.php
 * Cek cepat file yang dibutuhkan halaman Kartu & Profil Pegawai. Khusus Admin IT / HR.
 * Boleh dihapus setelah semua OK.
 */
require_once __DIR__ . '/../auth/auth.php';
wajib_peran(['hr']);   // admin_it otomatis lolos

$root = realpath(__DIR__ . '/..');
$wajib = [
    'template/header.php', 'template/sidebar.php', 'template/footer.php', 'template/bootstrap.php',
    'config/koneksi_sqlsrv.php', 'auth/auth.php',
    'pegawai/profil.php', 'pegawai/kartu.php', 'pegawai/_profil_helper.php', 'pegawai/_sidebar_kosong.php',
    'assets/css/profil-pegawai.css', 'assets/vendor/chart.js/chart.umd.js',
];
header('Content-Type: text/html; charset=utf-8');
echo '<style>body{font-family:sans-serif;padding:24px;color:#012970}td,th{padding:6px 12px;border-bottom:1px solid #eef1fb;text-align:left}'
   . '.ok{color:#2eca6a;font-weight:600}.no{color:#e74c3c;font-weight:600}</style>';
echo '<h2>Cek file Profil Pegawai</h2><p>Folder aplikasi: <code>' . htmlspecialchars($root) . '</code></p>';
echo '<table><tr><th>File</th><th>Ada</th><th>Bisa dibaca</th><th>Ukuran</th></tr>';
foreach ($wajib as $f) {
    $p = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $f);
    $ada = is_file($p); $baca = $ada && is_readable($p);
    echo '<tr><td>' . $f . '</td><td class="' . ($ada ? 'ok">Ya' : 'no">Tidak') . '</td>'
       . '<td class="' . ($baca ? 'ok">Ya' : 'no">Tidak') . '</td><td>' . ($ada ? number_format(filesize($p)) . ' B' : '—') . '</td></tr>';
}
echo '</table><p>PHP ' . PHP_VERSION . ' | sqlsrv: ' . (extension_loaded('sqlsrv') ? 'aktif' : 'TIDAK aktif')
   . ' | mbstring: ' . (extension_loaded('mbstring') ? 'aktif' : 'tidak aktif (tetap jalan)') . '</p>';
