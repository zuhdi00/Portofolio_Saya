<?php
/**
 * presensi/cek_pegawai_belum_dipetakan.php
 * Menampilkan zkteco_userid yang punya tap TAPI belum terhubung ke dbo.pegawai,
 * LENGKAP DENGAN NAMA & AC-No dari ZKTeco (tabel USERINFO di ATT2000.MDB),
 * plus tebakan pegawai mana yang cocok berdasarkan kemiripan nama.
 *
 * HANYA MEMBACA. Tidak mengubah apa pun.
 *
 * Jalankan:
 *   C:\xampp\php\php.exe C:\xampp\htdocs\hris\presensi\cek_pegawai_belum_dipetakan.php
 */
set_time_limit(0);
ini_set('memory_limit', '512M');

$isCli = (php_sapi_name() === 'cli');
if (!$isCli) header('Content-Type: text/plain; charset=utf-8');
function tulis($s = '') { echo $s . PHP_EOL; }

require __DIR__ . '/../config/koneksi_sqlsrv.php';   // $conn
require __DIR__ . '/../config/koneksi_mdb.php';

tulis("=== ZKTeco userid yang BELUM dipetakan ke pegawai ===");
tulis("Dibuat " . date('d-m-Y H:i:s'));
tulis(str_repeat('=', 92));

/* ---- 1. userid bermasalah dari staging ---- */
$st = sqlsrv_query($conn,
    "SELECT z.zk_userid, COUNT(*) n, MIN(z.checktime) awal, MAX(z.checktime) akhir
     FROM dbo.zkteco_checkinout z
     WHERE NOT EXISTS (SELECT 1 FROM dbo.pegawai p
                       WHERE p.zkteco_userid = z.zk_userid AND p.is_aktif = 1)
     GROUP BY z.zk_userid
     ORDER BY COUNT(*) DESC");
if ($st === false) { tulis("Query gagal: " . print_r(sqlsrv_errors(), true)); exit(1); }

$uid = [];
while ($r = sqlsrv_fetch_array($st, SQLSRV_FETCH_ASSOC)) {
    $uid[(int)$r['zk_userid']] = [
        'n'     => (int)$r['n'],
        'awal'  => $r['awal'] instanceof DateTime ? $r['awal']->format('Y-m-d') : '-',
        'akhir' => $r['akhir'] instanceof DateTime ? $r['akhir']->format('Y-m-d') : '-',
    ];
}
if (!$uid) { tulis("Bagus - semua userid sudah terpetakan."); exit(0); }
tulis("Ditemukan " . count($uid) . " userid belum terpetakan.");
tulis();

/* ---- 2. ambil nama dari MDB USERINFO ---- */
try { $mdb = buka_mdb(); }
catch (Exception $e) { tulis("ERROR MDB: " . $e->getMessage()); exit(1); }

$info = [];
foreach ($mdb->query("SELECT USERID, Badgenumber, Name, DEFAULTDEPTID FROM USERINFO") as $r) {
    $info[(int)$r['USERID']] = [
        'acno' => trim((string)$r['Badgenumber']),
        'nama' => trim((string)$r['Name']),
        'dept' => (int)$r['DEFAULTDEPTID'],
    ];
}

/* ---- 3. daftar pegawai untuk pencocokan nama ---- */
$peg = [];
$st = sqlsrv_query($conn,
    "SELECT id_peg, nik, nama_peg, zkteco_userid, is_aktif FROM dbo.pegawai");
while ($r = sqlsrv_fetch_array($st, SQLSRV_FETCH_ASSOC)) $peg[] = $r;

function normal($s) {
    return preg_replace('/[^a-z ]/', '', strtolower(trim((string)$s)));
}

/* ---- 4. tampilkan ---- */
tulis(str_pad('USERID', 9) . str_pad('AC-No', 10) . str_pad('NAMA DI ZKTECO', 28)
    . str_pad('TAP', 6) . str_pad('PERTAMA', 12) . str_pad('TERAKHIR', 12) . 'KEMUNGKINAN COCOK');
tulis(str_repeat('-', 110));

foreach ($uid as $u => $d) {
    $nama = $info[$u]['nama'] ?? '(tidak ada di USERINFO)';
    $acno = $info[$u]['acno'] ?? '-';

    // cari kandidat di tabel pegawai
    $cari = normal($nama);
    $kandidat = [];
    if ($cari !== '') {
        foreach ($peg as $p) {
            $np = normal($p['nama_peg']);
            if ($np === '') continue;
            similar_text($cari, $np, $persen);
            if ($persen >= 70) {
                $kandidat[] = sprintf('id_peg=%d %s (%.0f%%)%s',
                    $p['id_peg'], $p['nama_peg'], $persen,
                    $p['zkteco_userid'] !== null ? ' [sudah punya userid '.$p['zkteco_userid'].']' :
                    ($p['is_aktif'] ? '' : ' [is_aktif=0]'));
            }
        }
    }
    $ket = $kandidat ? implode(' | ', array_slice($kandidat, 0, 2)) : '-- tidak ada yang mirip --';

    tulis(str_pad($u, 9) . str_pad($acno, 10) . str_pad(substr($nama, 0, 27), 28)
        . str_pad($d['n'], 6) . str_pad($d['awal'], 12) . str_pad($d['akhir'], 12) . $ket);
}

tulis();
tulis(str_repeat('=', 92));
tulis("CARA MEMETAKAN (jalankan di SSMS, ganti angkanya, JANGAN pakai tanda < >):");
tulis("  UPDATE dbo.pegawai SET zkteco_userid = 4509 WHERE id_peg = 1740;");
tulis();
tulis("Kalau orangnya memang belum ada di tabel pegawai, pakai:");
tulis("  presensi/seed_pegawai_dari_zkteco.php   (set \$TULIS = false dulu untuk uji coba)");
tulis();
tulis("Setelah dipetakan, jalankan LANGKAH 3 di perbaikan_absensi.sql,");
tulis("lalu jalankan ulang sync_zkteco_proses.php.");
