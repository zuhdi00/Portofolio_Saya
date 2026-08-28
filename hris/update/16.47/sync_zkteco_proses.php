<?php
/**
 * presensi/sync_zkteco_proses.php
 * TAHAP 2 - Olah tap mentah (zkteco_checkinout) -> dbo.absensi
 *
 * ATURAN YANG DIPAKAI (hasil konfirmasi HR PT Supracor Sejahtera):
 *  - Shift 3 (malam) yang masuk 22:30 dan pulang 06:30 besoknya
 *    dicatat sebagai hari kerja TANGGAL MASUK.
 *  - Karyawan yang lupa tap pulang TIDAK diisi otomatis, tetapi masuk
 *    antrian dbo.absensi_koreksi untuk approval atasan.
 *
 * Jalankan setelah sync_zkteco_import.php:
 *   C:\xampp\php\php.exe C:\xampp\htdocs\hris\presensi\sync_zkteco_proses.php
 */

// ================== PARAMETER SHIFT ==================
// Jendela jam masuk tiap shift (jam mulai, jam akhir) - format 24 jam
$SHIFT = [
    1 => ['masuk_dari' => '05:00', 'masuk_sampai' => '10:59', 'durasi' => 8],
    2 => ['masuk_dari' => '13:00', 'masuk_sampai' => '18:59', 'durasi' => 8],
    3 => ['masuk_dari' => '20:00', 'masuk_sampai' => '02:59', 'durasi' => 8], // lintas tengah malam
];
$JAM_STD_TELAT = ['1' => '07:00', '2' => '15:00', '3' => '23:00']; // batas dianggap terlambat

$DEDUP_MENIT   = 3;    // dua tap < 3 menit dianggap tap ganda (satu kejadian)
$MIN_KERJA_JAM = 4;    // tap pulang minimal 4 jam setelah masuk
$MAX_KERJA_JAM = 16;   // lebih dari ini -> masuk dianggap tidak berpasangan
// =====================================================

$isCli = (php_sapi_name() === 'cli');
function tulis($s) { global $isCli; echo $s . ($isCli ? PHP_EOL : "<br>\n"); }

require __DIR__ . '/../config/koneksi_sqlsrv.php';        // $conn
require __DIR__ . '/../pegawai/_normalisasi_enum.php';    // enum_db()

$t0 = microtime(true);
tulis("=== Proses absensi dimulai " . date('d-m-Y H:i:s') . " ===");

/* ---------- 1. Peta zkteco_userid -> pegawai_id ---------- */
$peta = [];
$st = sqlsrv_query($conn,
    "SELECT id_peg, zkteco_userid FROM dbo.pegawai
     WHERE zkteco_userid IS NOT NULL AND is_aktif = 1");
while ($r = sqlsrv_fetch_array($st, SQLSRV_FETCH_ASSOC)) {
    $peta[(int)$r['zkteco_userid']] = (int)$r['id_peg'];
}
tulis("Pegawai terpetakan: " . count($peta));
if (!$peta) {
    tulis("BERHENTI: belum ada pegawai dengan zkteco_userid terisi.");
    tulis("Isi dulu lewat file pemetaan (lihat pemetaan_pegawai_zkteco.xlsx).");
    exit(1);
}

/* ---------- 2. Ambil tap yang belum diproses ---------- */
// diambil per pegawai, diurutkan waktu, plus 1 hari sebelumnya sebagai konteks
$st = sqlsrv_query($conn,
    "SELECT zk_userid, checktime, checktype, verifycode, sn
     FROM dbo.zkteco_checkinout
     WHERE diproses = 0
     ORDER BY zk_userid, checktime");
if ($st === false) { tulis("Query gagal: " . print_r(sqlsrv_errors(), true)); exit(1); }

$tapPer = [];
$jml = 0;
while ($r = sqlsrv_fetch_array($st, SQLSRV_FETCH_ASSOC)) {
    $uid = (int)$r['zk_userid'];
    if (!isset($peta[$uid])) continue;          // pegawai belum dipetakan -> lewati
    $ts = $r['checktime'] instanceof DateTime ? $r['checktime'] : new DateTime($r['checktime']);
    $tapPer[$uid][] = [
        'ts'   => $ts,
        'type' => $r['checktype'],
        'vc'   => (int)$r['verifycode'],
        'sn'   => $r['sn'],
    ];
    $jml++;
}
tulis("Tap belum diproses (terpetakan): $jml");

/* ---------- fungsi bantu ---------- */

/** tentukan shift & tanggal kerja dari jam tap masuk */
function tentukanShift($dt, $SHIFT) {
    $jam = (int)$dt->format('H') * 60 + (int)$dt->format('i');
    foreach ($SHIFT as $no => $s) {
        [$h1,$m1] = explode(':', $s['masuk_dari']);
        [$h2,$m2] = explode(':', $s['masuk_sampai']);
        $a = (int)$h1*60 + (int)$m1;
        $b = (int)$h2*60 + (int)$m2;
        if ($a <= $b) {                       // jendela normal
            if ($jam >= $a && $jam <= $b) return $no;
        } else {                              // jendela lintas tengah malam (shift 3)
            if ($jam >= $a || $jam <= $b) return $no;
        }
    }
    return null;
}

/**
 * TANGGAL KERJA:
 * Sesuai aturan HR -> memakai tanggal saat TAP MASUK.
 * Pengecualian: shift 3 yang tap masuknya sudah lewat tengah malam
 * (mis. 00:30) sebenarnya milik hari sebelumnya.
 */
function tanggalKerja($dtMasuk, $shift) {
    $d = clone $dtMasuk;
    if ($shift === 3 && (int)$d->format('H') < 5) {
        $d->modify('-1 day');                 // masih shift malam hari kemarin
    }
    return $d->format('Y-m-d');
}

/* ---------- 3. Pasangkan tap ---------- */
$hasil = [];       // [pegawai_id][tanggal] => data
$statTap = ['pasangan'=>0,'tunggal'=>0,'ganda_dibuang'=>0];

foreach ($tapPer as $uid => $taps) {
    $pegId = $peta[$uid];

    // buang tap ganda (beda < DEDUP_MENIT)
    $bersih = [];
    foreach ($taps as $t) {
        $n = count($bersih);
        if ($n && ($t['ts']->getTimestamp() - $bersih[$n-1]['ts']->getTimestamp()) < $GLOBALS['DEDUP_MENIT']*60) {
            $statTap['ganda_dibuang']++;
            continue;
        }
        $bersih[] = $t;
    }

    $i = 0; $n = count($bersih);
    while ($i < $n) {
        $masuk = $bersih[$i];
        $shift = tentukanShift($masuk['ts'], $SHIFT) ?? 1;
        $tgl   = tanggalKerja($masuk['ts'], $shift);

        // cari tap pulang: tap berikutnya yang jaraknya >= MIN_KERJA_JAM
        $keluar = null; $jmlTap = 1; $j = $i + 1;
        while ($j < $n) {
            $sel = ($bersih[$j]['ts']->getTimestamp() - $masuk['ts']->getTimestamp()) / 3600;
            if ($sel > $GLOBALS['MAX_KERJA_JAM']) break;         // terlalu jauh, bukan pasangan
            if ($sel >= $GLOBALS['MIN_KERJA_JAM']) { $keluar = $bersih[$j]; $jmlTap++; break; }
            $jmlTap++; $j++;                                     // tap tengah (istirahat dll)
        }

        $metode = $masuk['vc'] == 15 ? 'wajah' : ($masuk['vc'] == 1 ? 'sidik_jari' : 'lainnya');

        $hasil[$pegId][$tgl] = [
            'shift'      => $shift,
            'jam_masuk'  => $masuk['ts']->format('H:i:s'),
            'jam_keluar' => $keluar ? $keluar['ts']->format('H:i:s') : null,
            'metode'     => $metode,
            'sn'         => $masuk['sn'],
            'jml_tap'    => $keluar ? $jmlTap : 1,
        ];

        if ($keluar) { $statTap['pasangan']++; $i = $j + 1; }
        else         { $statTap['tunggal']++;  $i = $i + 1; }
    }
}

/* ---------- 4. Tulis ke dbo.absensi ---------- */
$sqlUpsert = "
MERGE dbo.absensi AS t
USING (SELECT ? AS pegawai_id, ? AS tanggal) AS s
   ON t.pegawai_id = s.pegawai_id AND t.tanggal = s.tanggal
WHEN MATCHED THEN UPDATE SET
     jam_masuk = ?, jam_keluar = ?, status = ?, metode = ?, sn_mesin = ?,
     shift_ke = ?, jml_tap = ?, perlu_koreksi = ?, sumber = 'ZKTECO'
WHEN NOT MATCHED THEN INSERT
     (pegawai_id, tanggal, jam_masuk, jam_keluar, status, metode, sn_mesin,
      shift_ke, jml_tap, perlu_koreksi, sumber, keterangan)
     VALUES (?,?,?,?,?,?,?,?,?,?, 'ZKTECO', N'Sinkron otomatis ZKTeco');";

$sqlKoreksi = "
IF NOT EXISTS (SELECT 1 FROM dbo.absensi_koreksi
               WHERE pegawai_id=? AND tanggal=? AND status_approval='PENDING')
INSERT INTO dbo.absensi_koreksi (pegawai_id, tanggal, jenis, jam_masuk_asli, catatan)
VALUES (?,?,?,?,?)";

$simpan = 0; $koreksi = 0;
sqlsrv_begin_transaction($conn);
try {
    foreach ($hasil as $pegId => $perTgl) {
        foreach ($perTgl as $tgl => $d) {
            $perluKoreksi = $d['jam_keluar'] === null ? 1 : 0;

            // status: hadir / terlambat  (nilai huruf kecil sesuai CHECK constraint)
            $batas  = $JAM_STD_TELAT[(string)$d['shift']] ?? '07:00';
            $telat  = substr($d['jam_masuk'],0,5) > $batas;
            $status = enum_db('absensi.status', $telat ? 'Terlambat' : 'Hadir');

            $p = [
                $pegId, $tgl,
                // UPDATE
                $d['jam_masuk'], $d['jam_keluar'], $status, $d['metode'], $d['sn'],
                $d['shift'], $d['jml_tap'], $perluKoreksi,
                // INSERT
                $pegId, $tgl, $d['jam_masuk'], $d['jam_keluar'], $status, $d['metode'], $d['sn'],
                $d['shift'], $d['jml_tap'], $perluKoreksi,
            ];
            $r = sqlsrv_query($conn, $sqlUpsert, $p);
            if ($r === false) throw new Exception("absensi $pegId/$tgl: " . print_r(sqlsrv_errors(), true));
            sqlsrv_free_stmt($r);
            $simpan++;

            // masuk antrian approval kalau tap tidak lengkap
            if ($perluKoreksi) {
                $pk = [$pegId, $tgl, $pegId, $tgl, 'LUPA_TAP_PULANG', $d['jam_masuk'],
                       'Tidak ada tap pulang. Shift ' . $d['shift'] . '. Butuh approval atasan.'];
                $r = sqlsrv_query($conn, $sqlKoreksi, $pk);
                if ($r === false) throw new Exception("koreksi $pegId/$tgl: " . print_r(sqlsrv_errors(), true));
                sqlsrv_free_stmt($r);
                $koreksi++;
            }
        }
    }

    // tandai tap mentah sudah diproses
    $r = sqlsrv_query($conn, "UPDATE dbo.zkteco_checkinout SET diproses = 1 WHERE diproses = 0");
    if ($r === false) throw new Exception("tandai diproses: " . print_r(sqlsrv_errors(), true));

    sqlsrv_commit($conn);
} catch (Exception $e) {
    sqlsrv_rollback($conn);
    tulis("GAGAL: " . $e->getMessage());
    exit(1);
}

$dur = round(microtime(true) - $t0, 1);
tulis("Tap berpasangan     : {$statTap['pasangan']}");
tulis("Tap tunggal (koreksi): {$statTap['tunggal']}");
tulis("Tap ganda dibuang   : {$statTap['ganda_dibuang']}");
tulis("Baris absensi ditulis: $simpan");
tulis("Masuk antrian approval: $koreksi");
tulis("Selesai dalam {$dur} detik.");
