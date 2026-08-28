<?php
/* ============================================================================
   PT SUPRACOR SEJAHTERA — BACKEND DASHBOARD STOK BARANG JADI (STB BJ)
   Versi cepat: membaca tabel snapshot, bukan menghitung ulang tiap request.

   Prasyarat : jalankan optimasi_stok.sql lebih dulu
               (membuat tbStokGudangSnap, tbStokGudangMutasi, tbStokGudangLog,
                dan stored procedure spRefreshStokGudang)

   Endpoint  :
     ?action=dashboard          -> data dashboard (default). Cepat, dari snapshot.
     ?action=refresh            -> paksa hitung ulang snapshot, lalu kirim data
     ?action=ping               -> cek koneksi database
     ?action=master             -> daftar jenis koreksi & daftar keterangan
     ?action=riwayat&sc=...     -> riwayat koreksi + perubahan keterangan satu OP

   Endpoint TULIS (kirim lewat POST):
     action=simpan_koreksi      sc, qty, jenis, keterangan, user [, tanggal, bukti, kelompok]
     action=batal_koreksi       id, alasan, user
     action=simpan_keterangan   sc, keterangan, user [, catatan, target]

   Semua penulisan lewat stored procedure yang punya pemeriksaan sendiri.
   tbStbBJ, tbSRJ, tbSRJDtl TIDAK PERNAH diubah dari sini.
   Parameter opsional:
     &batas_pc=150 &batas_kg=10 -> ambang kategori "stok kecil"
     &hari=14                   -> jumlah hari pada grafik mutasi
     &nocache=1                 -> abaikan cache file

   Catatan tim: lVoid / lPosted TIDAK difilter, mengikuti keputusan tim.
   ============================================================================ */

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Max-Age: 86400');
header('Content-Type: application/json');
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Private-Network: true');

@ini_set('display_errors', '0');
@ini_set('log_errors', '1');
error_reporting(E_ALL);
ob_start();
@set_time_limit(0);
@ini_set('memory_limit', '1024M');

define('VERSI_BACKEND',   '2.3-kelompok');
define('TANGGAL_BACKEND', '2026-08-10');
define('CACHE_FILE',   __DIR__ . '/cache_stok.json');
define('CACHE_DETIK',  60);   // cache file, aman karena snapshot diperbarui tiap 15 menit

function safeJsonEncode($data) {
    $opts = 0;
    if (defined('JSON_PARTIAL_OUTPUT_ON_ERROR')) $opts |= JSON_PARTIAL_OUTPUT_ON_ERROR;
    if (defined('JSON_UNESCAPED_UNICODE'))       $opts |= JSON_UNESCAPED_UNICODE;
    $json = json_encode($data, $opts);
    if ($json === false) $json = json_encode(['success' => false, 'message' => 'Internal JSON encoding error']);
    return $json;
}
function sendJson($data, $code = 200) {
    while (ob_get_level()) ob_end_clean();
    http_response_code($code);
    header('Content-Type: application/json');
    echo safeJsonEncode($data);
    exit;
}
function logMsg($msg) {
    @error_log('[' . date('c') . "] $msg\n", 3, __DIR__ . '/stok_backend.log');
}
register_shutdown_function(function () {
    $err = error_get_last();
    if ($err && in_array($err['type'], [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_PARSE], true)) {
        logMsg('FATAL: ' . ($err['message'] ?? ''));
        while (ob_get_level()) ob_end_clean();
        http_response_code(500);
        header('Content-Type: application/json');
        echo safeJsonEncode(['success' => false, 'fatal' => true, 'message' => $err['message'] ?? 'Fatal error']);
        exit;
    }
});
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') { http_response_code(200); exit; }

$action    = trim($_GET['action'] ?? $_POST['action'] ?? 'dashboard');
$isTulis   = in_array($action, ['simpan_koreksi', 'batal_koreksi', 'simpan_keterangan'], true);
function inp($k, $d = null) {
    $v = $_POST[$k] ?? $_GET[$k] ?? $d;
    return is_string($v) ? trim($v) : $v;
}
$batasPc   = isset($_GET['batas_pc']) ? (int)$_GET['batas_pc']   : 150;
$batasKg   = isset($_GET['batas_kg']) ? (float)$_GET['batas_kg'] : 10.0;
$hariTrend = isset($_GET['hari'])     ? max(7, min(60, (int)$_GET['hari'])) : 14;
$noCache   = !empty($_GET['nocache']) || $action === 'refresh' || $isTulis;

// ---- Cache file: jawab tanpa menyentuh database sama sekali ----
if (!$noCache && $action === 'dashboard' && is_readable(CACHE_FILE)) {
    $umurCache = time() - (int)@filemtime(CACHE_FILE);
    if ($umurCache >= 0 && $umurCache < CACHE_DETIK) {
        $isi = @file_get_contents(CACHE_FILE);
        if ($isi !== false && strlen($isi) > 20) {
            while (ob_get_level()) ob_end_clean();
            header('Content-Type: application/json');
            header('X-Cache: HIT ' . $umurCache . 's');
            echo $isi;
            exit;
        }
    }
}

// === Database config (sama dengan report_backend.php) ===
$serverName = "spsdmz2";
$connectionOptions = [
    "Database"               => "dbSopanusa",
    "Uid"                    => "sa",
    "PWD"                    => "supracor",
    "LoginTimeout"           => 15,
    "Encrypt"                => false,
    "TrustServerCertificate" => true
];

$conn = @sqlsrv_connect($serverName, $connectionOptions);
if (!$conn) {
    $err = sqlsrv_errors();
    sendJson([
        'success' => false,
        'message' => 'Koneksi database gagal',
        'detail'  => $err ? $err[0]['message'] : 'Server tidak aktif atau credentials salah',
        'server'  => $serverName
    ], 503);
}

if ($action === 'ping') {
    sqlsrv_close($conn);
    sendJson(['success' => true, 'message' => 'Koneksi OK', 'server' => $serverName,
              'versi' => VERSI_BACKEND, 'waktu' => date('c')]);
}

// Penanda versi. Buka stok_backend.php?action=versi untuk memastikan file di
// server memang yang terbaru, tanpa perlu membandingkan isinya baris per baris.
if ($action === 'versi') {
    sqlsrv_close($conn);
    sendJson([
        'success'  => true,
        'versi'    => VERSI_BACKEND,
        'tanggal'  => TANGGAL_BACKEND,
        'endpoint' => ['dashboard','refresh','ping','versi','master','riwayat',
                       'simpan_koreksi','batal_koreksi','simpan_keterangan'],
        'catatan'  => 'Kalau daftar endpoint di atas tidak memuat simpan_koreksi, berarti file di server masih versi lama.'
    ]);
}

// ============================================================================
// DAFTAR PILIHAN UNTUK FORM KOREKSI
// ============================================================================
if ($action === 'master') {
    $jenis = []; $ket = [];
    $q1 = @sqlsrv_query($conn, "SELECT cJenis, cPenjelasan FROM dbo.tbStokGudangJenisKoreksi
                                WHERE lAktif = 1 ORDER BY nUrut, cJenis", [], ["QueryTimeout" => 30]);
    if ($q1 !== false) {
        while ($r = sqlsrv_fetch_array($q1, SQLSRV_FETCH_ASSOC))
            $jenis[] = ['jenis' => trim($r['cJenis']), 'penjelasan' => trim($r['cPenjelasan'])];
        sqlsrv_free_stmt($q1);
    }
    $q2 = @sqlsrv_query($conn, "SELECT cKeterangan FROM dbo.tbStokGudangDaftarKet
                                WHERE lAktif = 1 ORDER BY nUrut, cKeterangan", [], ["QueryTimeout" => 30]);
    if ($q2 !== false) {
        while ($r = sqlsrv_fetch_array($q2, SQLSRV_FETCH_ASSOC)) $ket[] = trim($r['cKeterangan']);
        sqlsrv_free_stmt($q2);
    }
    sqlsrv_close($conn);
    sendJson(['success' => true, 'jenis' => $jenis, 'keterangan' => $ket,
              'siap' => (count($jenis) > 0 && count($ket) > 0),
              'hint' => count($jenis) ? null : 'Jalankan koreksi_stok.sql lebih dulu.']);
}

// ============================================================================
// RIWAYAT SATU NOMOR OP
// ============================================================================
if ($action === 'riwayat') {
    $sc = inp('sc', '');
    if ($sc === '') { sqlsrv_close($conn); sendJson(['success' => false, 'message' => 'Nomor OP wajib diisi.'], 400); }

    $koreksi = []; $ketLog = []; $ketAktif = null;

    $q = @sqlsrv_query($conn,
        "SELECT nId, CONVERT(VARCHAR(10), dTanggal, 23) AS dTanggal, nQtyPc, cJenis,
                cKeterangan, cNoBukti, lVoid, cAlasanVoid, UserId,
                CONVERT(VARCHAR(19), dInput, 120) AS dInput
         FROM   dbo.tbStokGudangKoreksi WHERE RTRIM(cNoSc) = ? ORDER BY nId DESC",
        [$sc], ["QueryTimeout" => 60]);
    if ($q !== false) {
        while ($r = sqlsrv_fetch_array($q, SQLSRV_FETCH_ASSOC)) {
            $koreksi[] = ['id' => (int)$r['nId'], 'tanggal' => $r['dTanggal'], 'qty' => (int)$r['nQtyPc'],
                          'jenis' => trim($r['cJenis']), 'keterangan' => trim($r['cKeterangan']),
                          'bukti' => trim($r['cNoBukti'] ?? ''), 'void' => (int)$r['lVoid'],
                          'alasan_void' => trim($r['cAlasanVoid'] ?? ''),
                          'user' => trim($r['UserId']), 'input' => $r['dInput']];
        }
        sqlsrv_free_stmt($q);
    }

    $q = @sqlsrv_query($conn,
        "SELECT cKetLama, cKetBaru, cCatatan, UserId, CONVERT(VARCHAR(19), dUbah, 120) AS dUbah
         FROM   dbo.tbStokGudangKetLog WHERE RTRIM(cNoSc) = ? ORDER BY nId DESC",
        [$sc], ["QueryTimeout" => 60]);
    if ($q !== false) {
        while ($r = sqlsrv_fetch_array($q, SQLSRV_FETCH_ASSOC))
            $ketLog[] = ['lama' => trim($r['cKetLama'] ?? ''), 'baru' => trim($r['cKetBaru']),
                         'catatan' => trim($r['cCatatan'] ?? ''), 'user' => trim($r['UserId']),
                         'waktu' => $r['dUbah']];
        sqlsrv_free_stmt($q);
    }

    $q = @sqlsrv_query($conn,
        "SELECT cKeterangan, cCatatan, CONVERT(VARCHAR(10), dTarget, 23) AS dTarget
         FROM   dbo.tbStokGudangKet WHERE RTRIM(cNoSc) = ?", [$sc], ["QueryTimeout" => 30]);
    if ($q !== false) {
        $r = sqlsrv_fetch_array($q, SQLSRV_FETCH_ASSOC);
        if ($r) $ketAktif = ['keterangan' => trim($r['cKeterangan']),
                             'catatan' => trim($r['cCatatan'] ?? ''), 'target' => $r['dTarget']];
        sqlsrv_free_stmt($q);
    }

    // rincian per tipe barang
    $tipe = [];
    $q = @sqlsrv_query($conn,
        "SELECT cKelompok, nSaldoAwal, nStb, nKirim, nRetur, nKoreksi, nStokPc
         FROM   dbo.tbStokSnapTipe WHERE RTRIM(cNoSc) = ? ORDER BY cKelompok",
        [$sc], ["QueryTimeout" => 30]);
    if ($q !== false) {
        while ($r = sqlsrv_fetch_array($q, SQLSRV_FETCH_ASSOC))
            $tipe[] = ['kelompok' => trim($r['cKelompok']), 'saldo_awal' => (int)$r['nSaldoAwal'],
                       'stb' => (int)$r['nStb'], 'kirim' => (int)$r['nKirim'],
                       'retur' => (int)$r['nRetur'], 'koreksi' => (int)$r['nKoreksi'],
                       'stok' => (int)$r['nStokPc']];
        sqlsrv_free_stmt($q);
    }

    sqlsrv_close($conn);
    sendJson(['success' => true, 'sc' => $sc, 'koreksi' => $koreksi,
              'keterangan_aktif' => $ketAktif, 'riwayat_keterangan' => $ketLog, 'tipe' => $tipe]);
}

// ============================================================================
// PENULISAN — semua lewat stored procedure
// ============================================================================
if ($isTulis) {
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        sqlsrv_close($conn);
        sendJson(['success' => false, 'message' => 'Perubahan data harus dikirim lewat POST.'], 405);
    }
    $user = inp('user', '');
    if ($user === '') { sqlsrv_close($conn); sendJson(['success' => false, 'message' => 'Nama pengguna wajib diisi.'], 400); }

    try {
        if ($action === 'simpan_koreksi') {
            $sc  = inp('sc', '');
            $qty = (int)inp('qty', 0);
            $par = [$sc, $qty, inp('jenis', ''), inp('keterangan', ''), $user,
                    inp('tanggal') ?: null, inp('bukti') ?: null, inp('divisi') ?: null,
                    inp('kelompok') ?: null];
            $st = sqlsrv_query($conn, "{call dbo.spTambahKoreksiStok(?,?,?,?,?,?,?,?,?)}", $par,
                               ["QueryTimeout" => 120]);
            $pesan = 'Koreksi tersimpan.';
        } elseif ($action === 'batal_koreksi') {
            $par = [(int)inp('id', 0), inp('alasan', ''), $user];
            $st = sqlsrv_query($conn, "{call dbo.spBatalkanKoreksiStok(?,?,?)}", $par, ["QueryTimeout" => 60]);
            $pesan = 'Koreksi dibatalkan.';
        } else {
            $par = [inp('sc', ''), inp('keterangan', ''), $user,
                    inp('catatan') ?: null, inp('target') ?: null];
            $st = sqlsrv_query($conn, "{call dbo.spSetKeteranganStok(?,?,?,?,?)}", $par, ["QueryTimeout" => 60]);
            $pesan = 'Keterangan diperbarui.';
        }

        if ($st === false) {
            $e = sqlsrv_errors();
            // Pesan dari THROW di prosedur biasanya ada di baris TERAKHIR, bukan
            // baris pertama. Versi lama hanya menampilkan yang pertama sehingga
            // sering muncul pesan umum dan sebab aslinya tidak kelihatan.
            $msg = 'Perintah gagal';
            $semua = [];
            if ($e) {
                foreach ($e as $x) {
                    $semua[] = ['sqlstate' => $x['SQLSTATE'] ?? '', 'kode' => $x['code'] ?? '',
                                'pesan' => $x['message'] ?? ''];
                    if (!empty($x['message'])) $msg = $x['message'];
                }
            }
            logMsg('TULIS GAGAL (' . $action . ') param=' . json_encode($par) . ' err=' . json_encode($semua));
            sqlsrv_close($conn);
            sendJson(['success' => false, 'message' => $msg, 'detail' => $semua,
                      'dikirim' => $par], 400);
        }

        $hasil = [];
        do { if (sqlsrv_has_rows($st) === true) break; } while (sqlsrv_next_result($st));
        while ($r = sqlsrv_fetch_array($st, SQLSRV_FETCH_ASSOC)) $hasil[] = $r;
        sqlsrv_free_stmt($st);
        sqlsrv_close($conn);

        @unlink(CACHE_FILE);
        logMsg($action . ' oleh ' . $user . ' — ' . json_encode($hasil));
        sendJson(['success' => true, 'message' => $pesan, 'hasil' => $hasil,
                  'catatan' => 'Angka stok ikut berubah setelah snapshot dihitung ulang. Tekan "Hitung ulang" bila ingin langsung terlihat.']);
    } catch (Exception $ex) {
        sqlsrv_close($conn);
        sendJson(['success' => false, 'message' => $ex->getMessage()], 400);
    }
}

// ---- Pastikan snapshot tersedia ----
$cek = sqlsrv_query($conn, "SELECT
        CASE WHEN OBJECT_ID('dbo.tbStokGudangSnap')   IS NULL THEN 0 ELSE 1 END AS ada_snap,
        CASE WHEN OBJECT_ID('dbo.tbStokGudangAdj')    IS NULL THEN 0 ELSE 1 END AS ada_adj,
        CASE WHEN OBJECT_ID('dbo.spRefreshStokGudang') IS NULL THEN 0 ELSE 1 END AS ada_proc,
        CASE WHEN OBJECT_ID('dbo.spRefreshSemuaStok')  IS NULL THEN 0 ELSE 1 END AS ada_semua",
    [], ["QueryTimeout" => 15]);
$adaSnap = false; $adaAdj = false; $adaProc = false; $adaSemua = false;
if ($cek !== false) {
    $r = sqlsrv_fetch_array($cek, SQLSRV_FETCH_ASSOC);
    $adaSnap = !empty($r['ada_snap']); $adaAdj = !empty($r['ada_adj']);
    $adaProc = !empty($r['ada_proc']); $adaSemua = !empty($r['ada_semua']);
    sqlsrv_free_stmt($cek);
}
if (!$adaSnap) {
    sqlsrv_close($conn);
    sendJson([
        'success' => false,
        'message' => 'Tabel snapshot belum dibuat.',
        'hint'    => 'Jalankan optimasi_stok.sql di dbSopanusa untuk membuat tbStokGudangSnap dan spRefreshStokGudang.'
    ], 500);
}

// ---- Hitung ulang bila diminta ----
$refreshInfo = null;
if ($action === 'refresh') {
    if (!$adaProc) {
        sqlsrv_close($conn);
        sendJson(['success' => false, 'message' => 'Stored procedure spRefreshStokGudang belum ada.',
                  'hint' => 'Jalankan optimasi_stok.sql lebih dulu.'], 500);
    }
    $mulai = microtime(true);
    // PENTING: tbStokGudangSnap sekarang DITURUNKAN dari tbStokSnapTipe.
    // Memanggil spRefreshStokGudang saja hanya menjumlahkan ulang tabel tipe
    // yang isinya belum diperbarui, sehingga koreksi manual tidak pernah masuk.
    // Karena itu yang dipanggil harus spRefreshSemuaStok.
    if ($adaSemua) {
        $st = sqlsrv_query($conn, "{call dbo.spRefreshSemuaStok(?)}", ['WEB'], ["QueryTimeout" => 1800]);
    } else {
        $st = sqlsrv_query($conn, "{call dbo.spRefreshStokGudang(?, ?)}", ['WEB', 30], ["QueryTimeout" => 1800]);
    }
    if ($st === false) {
        $err = sqlsrv_errors();
        logMsg('REFRESH GAGAL: ' . json_encode($err));
        sqlsrv_close($conn);
        sendJson(['success' => false, 'message' => 'Hitung ulang gagal: ' . ($err ? $err[0]['message'] : '')], 500);
    }
    sqlsrv_free_stmt($st);
    $refreshInfo = ['detik' => round(microtime(true) - $mulai, 1),
                    'prosedur' => $adaSemua ? 'spRefreshSemuaStok' : 'spRefreshStokGudang',
                    'tipe_ikut' => $adaSemua];
    @unlink(CACHE_FILE);
    logMsg('Refresh manual selesai dalam ' . $refreshInfo['detik'] . ' detik');
}

// ============================================================================
// RINCIAN PER TIPE BARANG (BOX / PART+LAYER / SHEET / LAIN)
// Diambil dari tbStokSnapTipe kalau sudah dibuat lewat stok_per_tipe_pasti.sql.
// Kalau belum ada, dashboard tetap jalan tanpa kolom tipe.
// ============================================================================
$tipeSc = []; $perTipe = []; $adaTipe = false; $posisiTipe = null; $totalTipe = 0;
$cekT = @sqlsrv_query($conn, "SELECT CASE WHEN OBJECT_ID('dbo.tbStokSnapTipe') IS NULL THEN 0 ELSE 1 END AS ada",
                      [], ["QueryTimeout" => 15]);
if ($cekT !== false) {
    $r = sqlsrv_fetch_array($cekT, SQLSRV_FETCH_ASSOC);
    $adaTipe = !empty($r['ada']);
    sqlsrv_free_stmt($cekT);
}
if ($adaTipe) {
    $stT = @sqlsrv_query($conn,
        "SELECT cNoSc, cKelompok, nStokPc, nStokKg,
                CONVERT(VARCHAR(10), dPosisi, 23) AS dPosisi
         FROM   dbo.tbStokSnapTipe", [], ["QueryTimeout" => 120]);
    if ($stT !== false) {
        while ($r = sqlsrv_fetch_array($stT, SQLSRV_FETCH_ASSOC)) {
            $sc  = trim($r['cNoSc']);
            $kel = trim($r['cKelompok']);
            $pc  = (int)$r['nStokPc'];
            $kg  = round((float)$r['nStokKg'], 3);
            if (!isset($tipeSc[$sc])) $tipeSc[$sc] = [];
            $tipeSc[$sc][$kel] = $pc;
            if (!isset($perTipe[$kel])) $perTipe[$kel] = [0, 0.0, 0];
            $perTipe[$kel][0] += $pc; $perTipe[$kel][1] += $kg; $perTipe[$kel][2] += 1;
            $posisiTipe = $r['dPosisi'];
            $totalTipe += $pc;
        }
        sqlsrv_free_stmt($stT);
    }
}

// ============================================================================
// QUERY UTAMA — cuma baca snapshot, ringan
// ============================================================================
$sql = "SELECT cNoSc, cKodeCust, cNama, cNamabrg, cNoMC, cNamaSales, cType, cRak,
               nBerat, CONVERT(VARCHAR(10), dTglStbAkhir, 23) AS dTglStbAkhir,
               nUmur, nStokPc, nStokKg, ISNULL(cKeterangan,'') AS cKeterangan, lDariExcel,
               ISNULL(cStatusData, 'NORMAL') AS cStatusData
        FROM   dbo.tbStokGudangSnap
        ORDER  BY cNama, cNoSc";

$stmt = sqlsrv_query($conn, $sql, [], ["QueryTimeout" => 120]);
if ($stmt === false) {
    $err = sqlsrv_errors();
    logMsg('SQL ERROR (snap): ' . json_encode($err));
    sqlsrv_close($conn);
    sendJson(['success' => false, 'message' => $err ? $err[0]['message'] : 'Query snapshot gagal'], 500);
}

$rows = []; $totalPc = 0; $totalKg = 0.0; $negatif = 0;
$aging = ['<= 5 hari' => [0,0,0], '<= 7 hari' => [0,0,0], '<= 14 hari' => [0,0,0],
          '> 14 hari' => [0,0,0], 'Stok kecil' => [0,0,0], 'Tanpa tanggal' => [0,0,0],
          'Stok minus' => [0,0,0]];
$perKet = [];
$perStatus = [];

while ($r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $pc   = (int)$r['nStokPc'];
    $kg   = round((float)$r['nStokKg'], 3);
    $umur = ($r['nUmur'] === null) ? null : (int)$r['nUmur'];

    if ($pc < 0)                             $kat = 'Stok minus';
    elseif ($pc <= $batasPc && $kg <= $batasKg) $kat = 'Stok kecil';
    elseif ($umur === null)                  $kat = 'Tanpa tanggal';
    elseif ($umur <= 5)                      $kat = '<= 5 hari';
    elseif ($umur <= 7)                      $kat = '<= 7 hari';
    elseif ($umur <= 14)                     $kat = '<= 14 hari';
    else                                     $kat = '> 14 hari';

    $ket = trim($r['cKeterangan']) !== '' ? trim($r['cKeterangan']) : 'Tanpa keterangan';

    // Kelompok utama = tipe dengan stok terbesar pada OP ini
    $kelUtama = '';
    $pecah = $tipeSc[trim($r['cNoSc'])] ?? null;
    if ($pecah) {
        $terbesar = null;
        foreach ($pecah as $k => $v) {
            if ($terbesar === null || abs($v) > abs($pecah[$terbesar])) $terbesar = $k;
        }
        $kelUtama = $terbesar ?? '';
        if (count($pecah) > 1) $kelUtama = 'CAMPURAN';
    }

    $rows[] = [
        'sc'         => trim($r['cNoSc']),
        'customer'   => trim($r['cNama']),
        'item'       => trim($r['cNamabrg']),
        'no_mc'      => trim($r['cNoMC']),
        'sales'      => trim($r['cNamaSales']),
        'tipe'       => trim($r['cType']),
        'rak'        => trim($r['cRak']),
        'berat'      => round((float)$r['nBerat'], 5),
        'tgl_stb'    => $r['dTglStbAkhir'],
        'umur'       => $umur,
        'pc'         => $pc,
        'kg'         => $kg,
        'kategori'   => $kat,
        'keterangan' => $ket,
        'dari_excel' => (int)$r['lDariExcel'],
        'status_data'=> trim($r['cStatusData']),
        'tipe_pc'    => $tipeSc[trim($r['cNoSc'])] ?? null,
        'kelompok'   => $kelUtama,
    ];

    $totalPc += $pc; $totalKg += $kg;
    if ($pc < 0) $negatif++;
    $aging[$kat][0] += $pc; $aging[$kat][1] += $kg; $aging[$kat][2] += 1;
    $sd = trim($r['cStatusData']) !== '' ? trim($r['cStatusData']) : 'NORMAL';
    if (!isset($perStatus[$sd])) $perStatus[$sd] = [0, 0];
    $perStatus[$sd][0] += $pc; $perStatus[$sd][1] += 1;
    if (!isset($perKet[$ket])) $perKet[$ket] = [0,0,0];
    $perKet[$ket][0] += $pc; $perKet[$ket][1] += $kg; $perKet[$ket][2] += 1;
}
sqlsrv_free_stmt($stmt);

// ---- Mutasi harian dari tabel snapshot mutasi ----
$harian = [];
$st2 = @sqlsrv_query($conn,
    "SELECT TOP (?) CONVERT(VARCHAR(10), dTanggal, 23) AS tgl, nStbPc, nKirimPc
     FROM   dbo.tbStokGudangMutasi ORDER BY dTanggal DESC",
    [$hariTrend], ["QueryTimeout" => 60]);
if ($st2 !== false) {
    while ($r = sqlsrv_fetch_array($st2, SQLSRV_FETCH_ASSOC)) {
        $harian[] = ['tgl' => $r['tgl'], 'stb' => (int)$r['nStbPc'], 'kirim' => (int)$r['nKirimPc']];
    }
    sqlsrv_free_stmt($st2);
    $harian = array_reverse($harian);
}

// ---- Info sinkronisasi Excel ----
// Patokan yang BERLAKU diambil dari tbStokGudangExcel. Versi lama membaca
// tbStokGudangAdj yang sudah tidak dipakai, sehingga menampilkan tanggal usang.
$sync = null;
$st3 = @sqlsrv_query($conn, "SELECT CONVERT(VARCHAR(10), MAX(dCutOff), 23) AS cut_off,
                                    COUNT(*) AS jml_op, SUM(nStokAkhirPc) AS pc
                             FROM dbo.tbStokGudangExcel", [], ["QueryTimeout" => 30]);
if ($st3 !== false) {
    $r = sqlsrv_fetch_array($st3, SQLSRV_FETCH_ASSOC);
    if ($r && $r['cut_off'])
        $sync = ['cut_off' => $r['cut_off'], 'jml_op' => (int)$r['jml_op'], 'pc' => (int)$r['pc']];
    sqlsrv_free_stmt($st3);
}

// ---- Kapan snapshot terakhir dihitung ----
$snap = null;
$st4 = @sqlsrv_query($conn, "SELECT TOP 1 CONVERT(VARCHAR(19), dSelesai, 120) AS selesai,
                                    nDetik, nJmlOp, cStatus, cSumber
                             FROM dbo.tbStokGudangLog
                             WHERE cStatus = 'SUKSES' ORDER BY nId DESC", [], ["QueryTimeout" => 30]);
if ($st4 !== false) {
    $r = sqlsrv_fetch_array($st4, SQLSRV_FETCH_ASSOC);
    if ($r) {
        $umurMenit = null;
        $ts = strtotime($r['selesai']);
        if ($ts) $umurMenit = (int)floor((time() - $ts) / 60);
        $snap = ['selesai' => $r['selesai'], 'detik' => (int)$r['nDetik'],
                 'jml_op' => (int)$r['nJmlOp'], 'sumber' => $r['cSumber'], 'umur_menit' => $umurMenit];
    }
    sqlsrv_free_stmt($st4);
}

sqlsrv_close($conn);

$agingOut = [];
foreach ($aging as $k => $v) $agingOut[] = ['kategori' => $k, 'pc' => $v[0], 'kg' => round($v[1], 2), 'op' => $v[2]];
$ketOut = [];
foreach ($perKet as $k => $v) $ketOut[] = ['keterangan' => $k, 'pc' => $v[0], 'kg' => round($v[1], 2), 'op' => $v[2]];
usort($ketOut, function ($a, $b) { return $b['pc'] - $a['pc']; });

$tipeOut = [];
foreach ($perTipe as $k => $v)
    $tipeOut[] = ['kelompok' => $k, 'pc' => $v[0], 'kg' => round($v[1], 2), 'op' => $v[2]];
usort($tipeOut, function ($a, $b) { return $b['pc'] - $a['pc']; });

$statusOut = [];
foreach ($perStatus as $k => $v) $statusOut[] = ['status' => $k, 'pc' => $v[0], 'op' => $v[1]];
usort($statusOut, function ($a, $b) { return $b['op'] - $a['op']; });

$hariIni = date('Y-m-d');
$stbHariIni = 0; $dlvHariIni = 0;
foreach ($harian as $h) {
    if ($h['tgl'] === $hariIni) { $stbHariIni = $h['stb']; $dlvHariIni = $h['kirim']; }
}

$hasil = [
    'success'    => true,
    'updated_at' => date('Y-m-d H:i:s'),
    'snapshot'   => $snap,
    'refresh'    => $refreshInfo,
    'summary'    => [
        'total_pc'     => $totalPc,
        'total_kg'     => round($totalKg, 2),
        'jml_op'       => count($rows),
        'stb_hari_ini' => $stbHariIni,
        'dlv_hari_ini' => $dlvHariIni,
        'op_negatif'   => $negatif,
        'batas_pc'     => $batasPc,
        'batas_kg'     => $batasKg,
    ],
    'sync'   => $sync,
    'tipe'   => $tipeOut,
    'tipe_posisi' => $posisiTipe,
    'tipe_sinkron' => ($posisiTipe === null) ? null : ($posisiTipe === date('Y-m-d')),
    'tipe_total'   => $totalTipe,
    'status' => $statusOut,
    'aging'  => $agingOut,
    'ket'    => $ketOut,
    'harian' => $harian,
    'rows'   => $rows,
];

$json = safeJsonEncode($hasil);
@file_put_contents(CACHE_FILE, $json, LOCK_EX);

while (ob_get_level()) ob_end_clean();
header('Content-Type: application/json');
header('X-Cache: MISS');
echo $json;
exit;
