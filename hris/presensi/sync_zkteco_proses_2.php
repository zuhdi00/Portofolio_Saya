<?php
/**
 * presensi/sync_zkteco_proses.php   [v2]
 * TAHAP 2 - Olah tap mentah (zkteco_checkinout) -> dbo.absensi
 *
 * PERUBAHAN BESAR dari v1:
 *  Arah tap (masuk/pulang) TIDAK lagi ditebak dari selisih jam, melainkan
 *  dibaca dari SERIAL MESIN. Terbukti dari data: mesin AXW8190960045
 *  100% tap masuk, mesin AXW8191660095 100% tap pulang.
 *
 * Jalankan setelah sync_zkteco_import.php:
 *   C:\xampp\php\php.exe C:\xampp\htdocs\hris\presensi\sync_zkteco_proses.php
 */

// ============ KONFIGURASI DIAMBIL DARI DATABASE ============
// Ubah lewat tabel dbo.pengaturan_shift & dbo.pengaturan_absensi
// (lihat sql/09_pengaturan_shift.sql). Tidak perlu mengedit file ini.
// Nilai di bawah hanya cadangan kalau tabel belum ada.
$SHIFT_CADANGAN = [
    1 => ['masuk_dari'=>'05:00','masuk_sampai'=>'11:59','mulai'=>'08:00','selesai'=>'16:00','toleransi'=>0],
    2 => ['masuk_dari'=>'13:00','masuk_sampai'=>'19:59','mulai'=>'16:00','selesai'=>'00:00','toleransi'=>0],
    3 => ['masuk_dari'=>'21:00','masuk_sampai'=>'02:59','mulai'=>'00:00','selesai'=>'08:00','toleransi'=>0],
];
$UMUM_CADANGAN = [
    'tgl_shift3'    => 'tanggal_tap',
    'mesin_masuk'   => 'AXW8190960045',
    'mesin_keluar'  => 'AXW8191660095',
    'dedup_menit'   => '3',
    'max_kerja_jam' => '16',
];
// ============================================================

$isCli = (php_sapi_name() === 'cli');
function tulis($s = '') { global $isCli; echo $s . ($isCli ? PHP_EOL : "<br>\n"); }

require __DIR__ . '/../config/koneksi_sqlsrv.php';        // $conn
require __DIR__ . '/../pegawai/_normalisasi_enum.php';    // enum_db()

$t0 = microtime(true);
tulis("=== Proses absensi " . date('d-m-Y H:i:s') . " ===");

/* ---------- 0. Baca pengaturan dari database ---------- */
$SHIFT = [];
$st = @sqlsrv_query($conn,
    "SELECT shift_ke, jam_mulai, jam_selesai, masuk_dari, masuk_sampai, toleransi_menit
     FROM dbo.pengaturan_shift WHERE is_aktif = 1 ORDER BY shift_ke");
if ($st) {
    while ($r = sqlsrv_fetch_array($st, SQLSRV_FETCH_ASSOC)) {
        $j = fn($v) => $v instanceof DateTime ? $v->format('H:i') : substr((string)$v, 0, 5);
        $SHIFT[(int)$r['shift_ke']] = [
            'masuk_dari'   => $j($r['masuk_dari']),
            'masuk_sampai' => $j($r['masuk_sampai']),
            'mulai'        => $j($r['jam_mulai']),
            'selesai'      => $j($r['jam_selesai']),
            'toleransi'    => (int)$r['toleransi_menit'],
        ];
    }
}
if (!$SHIFT) { $SHIFT = $SHIFT_CADANGAN; tulis("(!) tabel pengaturan_shift kosong - pakai nilai cadangan"); }

$UMUM = $UMUM_CADANGAN;
$st = @sqlsrv_query($conn, "SELECT kunci, nilai FROM dbo.pengaturan_absensi");
if ($st) while ($r = sqlsrv_fetch_array($st, SQLSRV_FETCH_ASSOC)) $UMUM[$r['kunci']] = $r['nilai'];

$MESIN_MASUK   = array_map('trim', explode(',', $UMUM['mesin_masuk']));
$MESIN_KELUAR  = array_map('trim', explode(',', $UMUM['mesin_keluar']));
$TGL_SHIFT3    = $UMUM['tgl_shift3'];
$DEDUP_MENIT   = (int)$UMUM['dedup_menit'];
$MAX_KERJA_JAM = (int)$UMUM['max_kerja_jam'];

tulis("Tanggal shift 3 : $TGL_SHIFT3");
foreach ($SHIFT as $no => $sh)
    tulis("  Shift $no: kerja {$sh['mulai']}-{$sh['selesai']}, "
        . "tap masuk {$sh['masuk_dari']}-{$sh['masuk_sampai']}, toleransi {$sh['toleransi']} mnt");
tulis();

/* ---------- 1. Peta zkteco_userid -> pegawai_id ---------- */
$peta = [];
$st = sqlsrv_query($conn,
    "SELECT id_peg, zkteco_userid FROM dbo.pegawai
     WHERE zkteco_userid IS NOT NULL AND is_aktif = 1");
while ($r = sqlsrv_fetch_array($st, SQLSRV_FETCH_ASSOC)) {
    $peta[(int)$r['zkteco_userid']] = (int)$r['id_peg'];
}
tulis("Pegawai terpetakan: " . count($peta));
if (!$peta) { tulis("BERHENTI: belum ada pegawai dengan zkteco_userid."); exit(1); }

/* ---------- 2. Ambil tap belum diproses ---------- */
$st = sqlsrv_query($conn,
    "SELECT zk_userid, checktime, checktype, verifycode, sn
     FROM dbo.zkteco_checkinout
     WHERE diproses = 0
     ORDER BY zk_userid, checktime");
if ($st === false) { tulis("Query gagal: " . print_r(sqlsrv_errors(), true)); exit(1); }

$tapPer = []; $jml = 0; $tanpaMesin = 0;
while ($r = sqlsrv_fetch_array($st, SQLSRV_FETCH_ASSOC)) {
    $uid = (int)$r['zk_userid'];
    if (!isset($peta[$uid])) continue;

    $sn = trim((string)$r['sn']);
    if (in_array($sn, $MESIN_MASUK, true))       $arah = 'I';
    elseif (in_array($sn, $MESIN_KELUAR, true))  $arah = 'O';
    else { $arah = ($r['checktype'] === 'O') ? 'O' : 'I'; $tanpaMesin++; }  // cadangan

    $ts = $r['checktime'] instanceof DateTime ? $r['checktime'] : new DateTime($r['checktime']);
    $tapPer[$uid][] = ['ts' => $ts, 'arah' => $arah, 'vc' => (int)$r['verifycode'], 'sn' => $sn];
    $jml++;
}
tulis("Tap belum diproses: $jml");
if ($tanpaMesin) tulis("  ($tanpaMesin tap dari mesin tak dikenal - pakai CHECKTYPE sbg cadangan)");

/* ---------- fungsi bantu ---------- */

function menit($jamStr) { [$h,$m] = explode(':', $jamStr); return (int)$h*60 + (int)$m; }

/**
 * Simpan hasil tanpa menimpa data yang lebih lengkap.
 * Kalau tanggal yang sama sudah terisi, keduanya DIGABUNG:
 * ambil jam_masuk paling awal dan jam_keluar paling akhir.
 */
function simpanHasil(&$hasil, $pegId, $tgl, $baru, &$stat) {
    if (!isset($hasil[$pegId][$tgl])) { $hasil[$pegId][$tgl] = $baru; return; }

    $lama = $hasil[$pegId][$tgl];
    $stat['digabung']++;

    $gab = $lama;
    // jam masuk: pilih yang ada, kalau dua-duanya ada pilih paling awal
    if ($lama['jam_masuk'] === null)      $gab['jam_masuk'] = $baru['jam_masuk'];
    elseif ($baru['jam_masuk'] !== null)  $gab['jam_masuk'] = min($lama['jam_masuk'], $baru['jam_masuk']);
    // jam keluar: pilih paling akhir
    if ($lama['jam_keluar'] === null)     $gab['jam_keluar'] = $baru['jam_keluar'];
    elseif ($baru['jam_keluar'] !== null) $gab['jam_keluar'] = max($lama['jam_keluar'], $baru['jam_keluar']);

    $gab['jml_tap'] = $lama['jml_tap'] + $baru['jml_tap'];
    $gab['shift']   = $gab['jam_masuk'] !== null ? ($lama['jam_masuk'] !== null ? $lama['shift'] : $baru['shift'])
                                                 : $lama['shift'];
    // masih perlu koreksi hanya kalau tetap ada yang kosong
    if ($gab['jam_masuk'] === null)       $gab['jenis_koreksi'] = 'LUPA_TAP_MASUK';
    elseif ($gab['jam_keluar'] === null)  $gab['jenis_koreksi'] = 'LUPA_TAP_PULANG';
    else                                  $gab['jenis_koreksi'] = null;

    $hasil[$pegId][$tgl] = $gab;
}

/** tentukan nomor shift dari jam tap masuk */
function tentukanShift($dt, $SHIFT) {
    $j = (int)$dt->format('H')*60 + (int)$dt->format('i');
    foreach ($SHIFT as $no => $s) {
        $a = menit($s['masuk_dari']); $b = menit($s['masuk_sampai']);
        if ($a <= $b) { if ($j >= $a && $j <= $b) return $no; }
        else          { if ($j >= $a || $j <= $b) return $no; }   // lintas tengah malam
    }
    return null;
}

/** tanggal kerja resmi */
function tanggalKerja($dtMasuk, $shift, $mode) {
    $d = clone $dtMasuk;
    if ($shift === 3) {
        $jam = (int)$d->format('H');
        if ($mode === 'tanggal_shift') {
            // tap 21:00-23:59 -> shift berjalan hari BERIKUTNYA
            if ($jam >= 21) $d->modify('+1 day');
        } else {
            // tap 00:00-02:59 -> masih shift yang mulai hari SEBELUMNYA
            if ($jam < 3) $d->modify('-1 day');
        }
    }
    return $d->format('Y-m-d');
}

/* ---------- 3. Pasangkan tap ---------- */
$hasil = [];
$stat = ['pasangan'=>0,'tanpa_pulang'=>0,'tanpa_masuk'=>0,'ganda'=>0,'digabung'=>0];

foreach ($tapPer as $uid => $taps) {
    $pegId = $peta[$uid];

    // buang tap ganda berurutan dgn arah sama
    $bersih = [];
    foreach ($taps as $t) {
        $n = count($bersih);
        if ($n && $bersih[$n-1]['arah'] === $t['arah'] &&
            ($t['ts']->getTimestamp() - $bersih[$n-1]['ts']->getTimestamp()) < $DEDUP_MENIT*60) {
            $stat['ganda']++; continue;
        }
        $bersih[] = $t;
    }

    $i = 0; $n = count($bersih);
    while ($i < $n) {
        $t = $bersih[$i];

        if ($t['arah'] === 'O') {          // tap pulang tanpa tap masuk
            $shift = tentukanShift($t['ts'], $SHIFT) ?? 1;
            $tgl   = $t['ts']->format('Y-m-d');
            simpanHasil($hasil, $pegId, $tgl, [
                'shift'=>$shift, 'jam_masuk'=>null,
                'jam_keluar'=>$t['ts']->format('H:i:s'),
                'metode'=>$t['vc']==15?'wajah':($t['vc']==1?'sidik_jari':'lainnya'),
                'sn'=>$t['sn'], 'jml_tap'=>1, 'jenis_koreksi'=>'LUPA_TAP_MASUK',
            ], $stat);
            $stat['tanpa_masuk']++; $i++; continue;
        }

        // tap masuk -> cari tap pulang berikutnya
        $shift = tentukanShift($t['ts'], $SHIFT) ?? 1;
        $tgl   = tanggalKerja($t['ts'], $shift, $TGL_SHIFT3);
        $keluar = null; $j = $i + 1;
        while ($j < $n) {
            $sel = ($bersih[$j]['ts']->getTimestamp() - $t['ts']->getTimestamp()) / 3600;
            if ($sel > $MAX_KERJA_JAM) break;
            if ($bersih[$j]['arah'] === 'I') break;      // ketemu tap masuk lagi
            $keluar = $bersih[$j]; break;
        }

        simpanHasil($hasil, $pegId, $tgl, [
            'shift'=>$shift,
            'jam_masuk'=>$t['ts']->format('H:i:s'),
            'jam_keluar'=>$keluar ? $keluar['ts']->format('H:i:s') : null,
            'metode'=>$t['vc']==15?'wajah':($t['vc']==1?'sidik_jari':'lainnya'),
            'sn'=>$t['sn'],
            'jml_tap'=>$keluar ? 2 : 1,
            'jenis_koreksi'=>$keluar ? null : 'LUPA_TAP_PULANG',
        ], $stat);

        if ($keluar) { $stat['pasangan']++; $i = $j + 1; }
        else         { $stat['tanpa_pulang']++; $i = $i + 1; }
    }
}

/* ---------- 4. Tulis ke absensi ---------- */
$sqlUpsert = "
MERGE dbo.absensi AS t
USING (SELECT ? AS pegawai_id, ? AS tanggal) AS s
   ON t.pegawai_id = s.pegawai_id AND t.tanggal = s.tanggal
WHEN MATCHED THEN UPDATE SET
     jam_masuk=?, jam_keluar=?, status=?, metode=?, sn_mesin=?,
     shift_ke=?, jml_tap=?, perlu_koreksi=?, sumber='ZKTECO'
WHEN NOT MATCHED THEN INSERT
     (pegawai_id, tanggal, jam_masuk, jam_keluar, status, metode, sn_mesin,
      shift_ke, jml_tap, perlu_koreksi, sumber, keterangan)
     VALUES (?,?,?,?,?,?,?,?,?,?, 'ZKTECO', N'Sinkron ZKTeco');";

$sqlKoreksi = "
IF NOT EXISTS (SELECT 1 FROM dbo.absensi_koreksi
               WHERE pegawai_id=? AND tanggal=? AND status_approval='PENDING')
INSERT INTO dbo.absensi_koreksi
    (pegawai_id, tanggal, jenis, jam_masuk_asli, jam_keluar_asli, catatan)
VALUES (?,?,?,?,?,?)";

$simpan = 0; $koreksi = 0;
sqlsrv_begin_transaction($conn);
try {
    foreach ($hasil as $pegId => $perTgl) {
        foreach ($perTgl as $tgl => $d) {
            $perluKoreksi = $d['jenis_koreksi'] ? 1 : 0;

            // telat dihitung dari jam MULAI shift + toleransi
            $status = 'hadir';
            if ($d['jam_masuk'] !== null) {
                $mulai = menit($SHIFT[$d['shift']]['mulai'])
                       + ($SHIFT[$d['shift']]['toleransi'] ?? 0);
                [$hh,$mm] = explode(':', substr($d['jam_masuk'], 0, 5));
                $jamTap = (int)$hh*60 + (int)$mm;
                // shift 3 mulai 00:00; tap 23:xx berarti datang lebih awal
                if ($d['shift'] == 3 && $jamTap >= 21*60) $jamTap -= 24*60;
                if ($jamTap > $mulai) $status = 'terlambat';
            }
            $status = enum_db('absensi.status', ucfirst($status));

            $p = [$pegId, $tgl,
                  $d['jam_masuk'], $d['jam_keluar'], $status, $d['metode'], $d['sn'],
                  $d['shift'], $d['jml_tap'], $perluKoreksi,
                  $pegId, $tgl, $d['jam_masuk'], $d['jam_keluar'], $status, $d['metode'], $d['sn'],
                  $d['shift'], $d['jml_tap'], $perluKoreksi];
            $r = sqlsrv_query($conn, $sqlUpsert, $p);
            if ($r === false) throw new Exception("absensi $pegId/$tgl: " . print_r(sqlsrv_errors(), true));
            sqlsrv_free_stmt($r); $simpan++;

            if ($perluKoreksi) {
                $pk = [$pegId, $tgl, $pegId, $tgl, $d['jenis_koreksi'],
                       $d['jam_masuk'], $d['jam_keluar'],
                       'Shift ' . $d['shift'] . '. Tap tidak lengkap, butuh approval atasan.'];
                $r = sqlsrv_query($conn, $sqlKoreksi, $pk);
                if ($r === false) throw new Exception("koreksi $pegId/$tgl: " . print_r(sqlsrv_errors(), true));
                sqlsrv_free_stmt($r); $koreksi++;
            }
        }
    }

    $r = sqlsrv_query($conn, "UPDATE dbo.zkteco_checkinout SET diproses = 1 WHERE diproses = 0");
    if ($r === false) throw new Exception("tandai diproses: " . print_r(sqlsrv_errors(), true));

    sqlsrv_commit($conn);
} catch (Exception $e) {
    sqlsrv_rollback($conn);
    tulis("GAGAL: " . $e->getMessage());
    exit(1);
}

$dur = round(microtime(true) - $t0, 1);
tulis();
tulis("Tap berpasangan lengkap : {$stat['pasangan']}");
tulis("Tanpa tap pulang        : {$stat['tanpa_pulang']}");
tulis("Tanpa tap masuk         : {$stat['tanpa_masuk']}");
tulis("Tap ganda dibuang       : {$stat['ganda']}");
tulis("Digabung (tgl sama)     : {$stat['digabung']}");
tulis("Baris absensi ditulis   : $simpan");
tulis("Masuk antrian approval  : $koreksi");
tulis("Selesai dalam {$dur} detik.");
