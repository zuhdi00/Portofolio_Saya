<?php
ini_set('memory_limit','1024M');
set_time_limit(0);
/**
 * presensi/sync_zkteco_proses.php   [v3 - PERBAIKAN 13-08-2026]
 *  v3: (a) MERGE tidak lagi menimpa jam_masuk/jam_keluar dengan NULL,
 *          melainkan MELENGKAPI baris yang sudah ada.
 *      (b) Tap pulang dini hari ditutup ke shift hari sebelumnya yang
 *          masih terbuka, bukan bikin baris shift 3 baru.
 *      (c) Tap yang pegawainya belum dipetakan dilaporkan di ringkasan.
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
    "SELECT p.id_peg, p.zkteco_userid AS raw_userid
     FROM dbo.pegawai p
     WHERE p.zkteco_userid IS NOT NULL AND p.is_aktif = 1
       AND NOT EXISTS (
           SELECT 1 FROM dbo.zkteco_user_transfer x
           WHERE x.source_userid = p.zkteco_userid AND x.is_aktif = 1
       )
     UNION ALL
     SELECT target.id_peg, transfer.source_userid AS raw_userid
     FROM dbo.zkteco_user_transfer transfer
      INNER JOIN dbo.pegawai target ON target.id_peg = transfer.target_pegawai_id
          OR (transfer.target_pegawai_id IS NULL AND target.zkteco_userid = transfer.target_userid)
     WHERE transfer.is_aktif = 1 AND target.is_aktif = 1");
if ($st === false) {
    tulis("Query mapping pegawai gagal: " . print_r(sqlsrv_errors(), true));
    exit(1);
}
while ($r = sqlsrv_fetch_array($st, SQLSRV_FETCH_ASSOC)) {
    $peta[(int)$r['raw_userid']] = (int)$r['id_peg'];
}
tulis("Pegawai terpetakan: " . count($peta));
if (!$peta) { tulis("BERHENTI: belum ada pegawai dengan zkteco_userid."); exit(1); }

/* ---------- 2. Ambil tap belum diproses ---------- */
// HEMAT MEMORI: ambil daftar userid dulu, tap dimuat per-pegawai nanti.
$st = sqlsrv_query($conn,
    "SELECT DISTINCT zk_userid FROM dbo.zkteco_checkinout WHERE diproses = 0 ORDER BY zk_userid");
if ($st === false) { tulis("Query gagal: " . print_r(sqlsrv_errors(), true)); exit(1); }
$daftarUid = [];
while ($r = sqlsrv_fetch_array($st, SQLSRV_FETCH_ASSOC)) {
    $uid = (int)$r['zk_userid'];
    if (isset($peta[$uid])) $daftarUid[] = $uid;
}
tulis("Pegawai dengan tap belum diproses: " . count($daftarUid));

// v3: laporkan userid yang TIDAK dikenal supaya tidak diam-diam hilang
$stX = sqlsrv_query($conn,
    "SELECT z.zk_userid, COUNT(*) n FROM dbo.zkteco_checkinout z
     WHERE z.diproses = 0
       AND NOT EXISTS (SELECT 1 FROM dbo.pegawai p
                       WHERE p.zkteco_userid = z.zk_userid AND p.is_aktif = 1)
     GROUP BY z.zk_userid ORDER BY COUNT(*) DESC");
$takDikenal = [];
if ($stX) while ($r = sqlsrv_fetch_array($stX, SQLSRV_FETCH_ASSOC))
    $takDikenal[] = $r['zk_userid'] . ' (' . $r['n'] . ' tap)';
if ($takDikenal) {
    tulis("(!) " . count($takDikenal) . " zkteco_userid BELUM DIPETAKAN / tidak aktif:");
    tulis("    " . implode(', ', $takDikenal));
    tulis("    Absensi mereka TIDAK akan muncul sampai pegawai.zkteco_userid diisi.");
}
$jml = 0; $tanpaMesin = 0;

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

/* SQL & counter didefinisikan SEBELUM loop (dipakai di dalam loop) */
$sqlUpsert = "
MERGE dbo.absensi AS t
USING (SELECT ? AS pegawai_id, ? AS tanggal,
              CAST(? AS TIME) AS jm, CAST(? AS TIME) AS jk,
              ? AS sts, ? AS mtd, ? AS sn, ? AS shf, ? AS jt, ? AS pk) AS s
   ON t.pegawai_id = s.pegawai_id AND t.tanggal = s.tanggal
WHEN MATCHED THEN UPDATE SET
     jam_masuk  = CASE WHEN s.jm IS NULL         THEN t.jam_masuk
                       WHEN t.jam_masuk IS NULL  THEN s.jm
                       WHEN s.jm < t.jam_masuk   THEN s.jm
                       ELSE t.jam_masuk END,
     jam_keluar = CASE WHEN s.jk IS NULL         THEN t.jam_keluar
                       WHEN t.jam_keluar IS NULL THEN s.jk
                       WHEN s.jk > t.jam_keluar  THEN s.jk
                       ELSE t.jam_keluar END,
     status     = CASE WHEN s.jm IS NOT NULL AND (t.jam_masuk IS NULL OR s.jm < t.jam_masuk)
                       THEN s.sts ELSE t.status END,
     metode     = COALESCE(t.metode,   s.mtd),
     sn_mesin   = COALESCE(t.sn_mesin, s.sn),
     shift_ke   = COALESCE(t.shift_ke, s.shf),
     jml_tap    = ISNULL(t.jml_tap, 0) + s.jt,
     perlu_koreksi = CASE
         WHEN (CASE WHEN s.jm IS NULL THEN t.jam_masuk  ELSE s.jm END) IS NULL
           OR (CASE WHEN s.jk IS NULL THEN t.jam_keluar ELSE s.jk END) IS NULL
         THEN 1 ELSE 0 END,
     sumber = 'ZKTECO'
WHEN NOT MATCHED THEN INSERT
     (pegawai_id, tanggal, jam_masuk, jam_keluar, status, metode, sn_mesin,
      shift_ke, jml_tap, perlu_koreksi, sumber, keterangan)
     VALUES (s.pegawai_id, s.tanggal, s.jm, s.jk, s.sts, s.mtd, s.sn,
             s.shf, s.jt, s.pk, 'ZKTECO', N'Sinkron ZKTeco');";
$sqlKoreksi = "
IF NOT EXISTS (SELECT 1 FROM dbo.absensi_koreksi
               WHERE pegawai_id=? AND tanggal=? AND status_approval='PENDING')
INSERT INTO dbo.absensi_koreksi
    (pegawai_id, tanggal, jenis, jam_masuk_asli, jam_keluar_asli, catatan)
VALUES (?,?,?,?,?,?)";
$simpan = 0; $koreksi = 0;

foreach ($daftarUid as $uid) {
    $pegId = $peta[$uid];

    // muat tap HANYA untuk pegawai ini (hemat memori)
    $taps = [];
    $stTap = sqlsrv_query($conn,
        "SELECT checktime, checktype, verifycode, sn
         FROM dbo.zkteco_checkinout
         WHERE diproses = 0 AND zk_userid = ?
         ORDER BY checktime", [$uid]);
    if ($stTap !== false) {
        while ($r = sqlsrv_fetch_array($stTap, SQLSRV_FETCH_ASSOC)) {
            $sn = trim((string)$r['sn']);
            if (in_array($sn, $MESIN_MASUK, true))       $arah = 'I';
            elseif (in_array($sn, $MESIN_KELUAR, true))  $arah = 'O';
            else { $arah = ($r['checktype'] === 'O') ? 'O' : 'I'; $tanpaMesin++; }
            $ts = $r['checktime'] instanceof DateTime ? $r['checktime'] : new DateTime($r['checktime']);
            $taps[] = ['ts'=>$ts, 'arah'=>$arah, 'vc'=>(int)$r['verifycode'], 'sn'=>$sn];
            $jml++;
        }
        sqlsrv_free_stmt($stTap);
    }

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
    unset($taps);  // bebaskan memori

    $i = 0; $n = count($bersih);
    while ($i < $n) {
        $t = $bersih[$i];

        if ($t['arah'] === 'O') {          // tap pulang tanpa tap masuk
            $shift = tentukanShift($t['ts'], $SHIFT) ?? 1;
            $tgl   = $t['ts']->format('Y-m-d');

            // --- v3: tap pulang 00:00-08:59 hampir pasti milik hari SEBELUMNYA
            //     (shift 2 selesai 00:00, shift 3 selesai 08:00).
            //     Jangan ditebak dari jam: cari baris kemarin yang jam_keluar-nya kosong.
            if ((int)$t['ts']->format('H') < 9) {
                $d2 = clone $t['ts']; $d2->modify('-1 day');
                $tglSblm = $d2->format('Y-m-d');
                if (isset($hasil[$pegId][$tglSblm]) &&
                    $hasil[$pegId][$tglSblm]['jam_keluar'] === null) {
                    $tgl   = $tglSblm;
                    $shift = $hasil[$pegId][$tglSblm]['shift'];
                } else {
                    $qq = sqlsrv_query($conn,
                        "SELECT TOP 1 shift_ke FROM dbo.absensi
                         WHERE pegawai_id=? AND tanggal=?
                           AND jam_masuk IS NOT NULL AND jam_keluar IS NULL",
                        [$pegId, $tglSblm]);
                    $rw = $qq ? sqlsrv_fetch_array($qq, SQLSRV_FETCH_ASSOC) : null;
                    if ($qq) sqlsrv_free_stmt($qq);
                    if ($rw) {
                        $tgl = $tglSblm;
                        if ($rw['shift_ke']) $shift = (int)$rw['shift_ke'];
                    }
                }
            }

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

    // ---- flush hasil pegawai ini ke DB, lalu bebaskan memori ----
    if (!empty($hasil[$pegId])) {
        sqlsrv_begin_transaction($conn);
        try {
            tulisAbsensiPeg($conn, $pegId, $hasil[$pegId], $SHIFT, $sqlUpsert, $sqlKoreksi, $simpan, $koreksi);
            $r = sqlsrv_query($conn,
                "UPDATE dbo.zkteco_checkinout SET diproses=1 WHERE diproses=0 AND zk_userid=?", [$uid]);
            if ($r === false) throw new Exception("tandai diproses uid $uid: ".print_r(sqlsrv_errors(),true));
            sqlsrv_commit($conn);
        } catch (Exception $e) {
            sqlsrv_rollback($conn);
            tulis("GAGAL pegawai uid $uid: " . $e->getMessage());
            // lanjut ke pegawai berikutnya, jangan hentikan seluruh proses
        }
        unset($hasil[$pegId]);  // bebaskan memori
    }
    $procPeg = ($procPeg ?? 0) + 1;
    if ($procPeg % 50 === 0) tulis("  ... $procPeg pegawai diproses, $simpan baris (".round(microtime(true)-$t0)."s)");
}

/* ---------- fungsi tulis per pegawai ---------- */
/** tulis hasil 1 pegawai ke DB, lalu array dikosongkan pemanggil */
function tulisAbsensiPeg($conn, $pegId, $perTgl, $SHIFT, $sqlUpsert, $sqlKoreksi, &$simpan, &$koreksi) {
    foreach ($perTgl as $tgl => $d) {
        $perluKoreksi = $d['jenis_koreksi'] ? 1 : 0;
        $status = 'hadir';
        if ($d['jam_masuk'] !== null) {
            $mulai = menit($SHIFT[$d['shift']]['mulai']) + ($SHIFT[$d['shift']]['toleransi'] ?? 0);
            [$hh,$mm] = explode(':', substr($d['jam_masuk'], 0, 5));
            $jamTap = (int)$hh*60 + (int)$mm;
            if ($d['shift'] == 3 && $jamTap >= 21*60) $jamTap -= 24*60;
            if ($jamTap > $mulai) $status = 'terlambat';
        }
        $status = enum_db('absensi.status', ucfirst($status));

        $p = [$pegId, $tgl, $d['jam_masuk'], $d['jam_keluar'], $status, $d['metode'], $d['sn'],
              $d['shift'], $d['jml_tap'], $perluKoreksi];
        $r = sqlsrv_query($conn, $sqlUpsert, $p);
        if ($r === false) throw new Exception("absensi $pegId/$tgl: " . print_r(sqlsrv_errors(), true));
        sqlsrv_free_stmt($r); $simpan++;

        if ($perluKoreksi) {
            $pk = [$pegId, $tgl, $pegId, $tgl, $d['jenis_koreksi'], $d['jam_masuk'], $d['jam_keluar'],
                   'Shift ' . $d['shift'] . '. Tap tidak lengkap, butuh approval atasan.'];
            $r = sqlsrv_query($conn, $sqlKoreksi, $pk);
            if ($r === false) throw new Exception("koreksi $pegId/$tgl: " . print_r(sqlsrv_errors(), true));
            sqlsrv_free_stmt($r); $koreksi++;
        }
    }
}

/* ---------- 5. Ringkasan ---------- */
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
