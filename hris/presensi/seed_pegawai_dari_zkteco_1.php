<?php
/**
 * presensi/seed_pegawai_dari_zkteco.php
 * Membuat KERANGKA data pegawai di dbHR dari ZKTeco USERINFO,
 * sekaligus mengisi zkteco_userid / zkteco_acno agar sinkronisasi bisa jalan.
 *
 * PENTING:
 *  - Mode default = DRY RUN (tidak menulis apapun). Ubah $TULIS = true untuk eksekusi.
 *  - TIDAK PERNAH menimpa data pegawai yang sudah ada. Hanya menambah yang belum ada,
 *    atau melengkapi zkteco_userid pada pegawai yang NIK-nya sudah cocok.
 *  - Pencocokan berdasarkan NAMA tidak dilakukan otomatis (berisiko salah orang);
 *    hasilnya hanya dilaporkan untuk diperiksa manual.
 *
 * Jalankan:  C:\xampp\php\php.exe C:\xampp\htdocs\hris\presensi\seed_pegawai_dari_zkteco.php
 */

// ================== KONFIGURASI ==================
$TULIS      = false;   // <<< false = uji coba, true = benar-benar menyimpan
$SEED_DEPT  = true;    // ikut membuat data department dari ZKTeco
$SEED_UNIT  = true;    // buat 1 unit_kerja per department (bisa dirapikan HR nanti)
$LEWATI_DEPT = ['RESIGN'];   // departemen yang tidak perlu diimpor
$MIN_TAP    = 100;       // minimal jumlah tap; 0 = impor semua termasuk yg belum pernah tap
// =================================================

$isCli = (php_sapi_name() === 'cli');
function tulis($s='') { global $isCli; echo $s . ($isCli ? PHP_EOL : "<br>\n"); }

require __DIR__ . '/../config/koneksi_sqlsrv.php';   // $conn

tulis("=== Seed pegawai dari ZKTeco " . date('d-m-Y H:i:s') . " ===");
tulis($TULIS ? ">>> MODE SIMPAN (data akan ditulis)" : ">>> MODE UJI COBA (tidak ada yang disimpan)");
tulis();

/* ---------- 1. Baca MDB ---------- */
require __DIR__ . '/../config/koneksi_mdb.php';
try { $mdb = buka_mdb(); }
catch (Exception $e) { tulis("ERROR koneksi MDB: " . $e->getMessage()); exit(1); }

$deptZk = [];
foreach ($mdb->query("SELECT DEPTID, DEPTNAME FROM DEPARTMENTS") as $d) {
    $deptZk[(int)$d['DEPTID']] = trim($d['DEPTNAME']);
}
tulis("Departemen di ZKTeco: " . count($deptZk));

// hitung jumlah tap per user (untuk menyaring karyawan lama yang tidak aktif)
$tapCount = [];
foreach ($mdb->query("SELECT USERID, COUNT(*) AS n FROM CHECKINOUT GROUP BY USERID") as $c) {
    $tapCount[(int)$c['USERID']] = (int)$c['n'];
}

$users = [];
foreach ($mdb->query("SELECT USERID, Badgenumber, Name, DEFAULTDEPTID, Gender, BIRTHDAY, HIREDDAY FROM USERINFO") as $u) {
    $users[] = $u;
}
tulis("Pegawai di ZKTeco   : " . count($users));
tulis();

/* ---------- 2. Seed department ---------- */
$petaDept = [];   // nama dept ZKTeco -> id_dept dbHR
$st = sqlsrv_query($conn, "SELECT id_dept, nama_dept FROM dbo.department");
while ($r = sqlsrv_fetch_array($st, SQLSRV_FETCH_ASSOC)) {
    $petaDept[strtoupper(trim($r['nama_dept']))] = (int)$r['id_dept'];
}

$deptBaru = 0;
foreach ($deptZk as $zid => $nama) {
    if ($nama === '' || in_array(strtoupper($nama), array_map('strtoupper', $LEWATI_DEPT))) continue;
    if (isset($petaDept[strtoupper($nama)])) continue;
    if (!$SEED_DEPT) continue;

    if ($TULIS) {
        $kode = strtoupper(substr(preg_replace('/[^A-Za-z0-9]/','', $nama), 0, 10));
        $ins = sqlsrv_query($conn,
            "INSERT INTO dbo.department (kode_dept, nama_dept) VALUES (?,?);
             SELECT SCOPE_IDENTITY() AS id;", [$kode ?: 'DEPT'.$zid, $nama]);
        if ($ins === false) { tulis("  ! gagal dept '$nama': " . print_r(sqlsrv_errors(), true)); continue; }
        sqlsrv_next_result($ins); sqlsrv_fetch($ins);
        $petaDept[strtoupper($nama)] = (int) sqlsrv_get_field($ins, 0);
        sqlsrv_free_stmt($ins);
    }
    $deptBaru++;
}
tulis("Department baru " . ($TULIS ? "dibuat" : "akan dibuat") . ": $deptBaru");

/* ---------- 3. Seed unit_kerja (1 per department) ---------- */
$petaUnit = [];   // id_dept -> id unit_kerja
$st = sqlsrv_query($conn, "SELECT id, nama_unit, department_id FROM dbo.unit_kerja");
while ($r = sqlsrv_fetch_array($st, SQLSRV_FETCH_ASSOC)) {
    if ($r['department_id'] !== null) $petaUnit[(int)$r['department_id']] = (int)$r['id'];
}
$unitBaru = 0;
if ($SEED_UNIT) {
    foreach ($petaDept as $namaUp => $idDept) {
        if (isset($petaUnit[$idDept])) continue;
        if ($TULIS) {
            $kode = 'U' . $idDept;
            $ins = sqlsrv_query($conn,
                "INSERT INTO dbo.unit_kerja (kode_unit, nama_unit, department_id, level) VALUES (?,?,?,1);
                 SELECT SCOPE_IDENTITY() AS id;", [$kode, $namaUp, $idDept]);
            if ($ins === false) { tulis("  ! gagal unit '$namaUp': " . print_r(sqlsrv_errors(), true)); continue; }
            sqlsrv_next_result($ins); sqlsrv_fetch($ins);
            $petaUnit[$idDept] = (int) sqlsrv_get_field($ins, 0);
            sqlsrv_free_stmt($ins);
        }
        $unitBaru++;
    }
}
tulis("Unit kerja baru " . ($TULIS ? "dibuat" : "akan dibuat") . ": $unitBaru");
tulis();

/* ---------- 4. Data pegawai dbHR yang sudah ada ---------- */
$byNik = []; $byZk = []; $byNama = [];
$st = sqlsrv_query($conn, "SELECT id_peg, nik, nama_peg, zkteco_userid FROM dbo.pegawai");
while ($r = sqlsrv_fetch_array($st, SQLSRV_FETCH_ASSOC)) {
    if ($r['nik'])           $byNik[trim($r['nik'])]  = (int)$r['id_peg'];
    if ($r['zkteco_userid']) $byZk[(int)$r['zkteco_userid']] = (int)$r['id_peg'];
    $byNama[strtoupper(preg_replace('/\s+/',' ', trim($r['nama_peg'])))] = (int)$r['id_peg'];
}
tulis("Pegawai sudah ada di dbHR: " . count($byNama) . " (terpetakan ke ZKTeco: " . count($byZk) . ")");
tulis();

/* ---------- 5. Proses tiap pegawai ZKTeco ---------- */
$stat = ['sudah'=>0,'link_nik'=>0,'baru'=>0,'lewati_dept'=>0,'lewati_tap'=>0,'mirip_nama'=>[],'gagal'=>0];

foreach ($users as $u) {
    $zkid  = (int)$u['USERID'];
    $badge = trim((string)$u['Badgenumber']);
    $nama  = trim((string)$u['Name']);
    $dnama = $deptZk[(int)$u['DEFAULTDEPTID']] ?? '';

    if ($nama === '') continue;

    if (in_array(strtoupper($dnama), array_map('strtoupper', $LEWATI_DEPT))) { $stat['lewati_dept']++; continue; }
    if (($tapCount[$zkid] ?? 0) < $MIN_TAP) { $stat['lewati_tap']++; continue; }

    // sudah terpetakan
    if (isset($byZk[$zkid])) { $stat['sudah']++; continue; }

    // cocok lewat NIK -> tinggal isi kolom zkteco
    if ($badge !== '' && isset($byNik[$badge])) {
        if ($TULIS) {
            $up = sqlsrv_query($conn,
                "UPDATE dbo.pegawai SET zkteco_userid = ?, zkteco_acno = ? WHERE id_peg = ?",
                [$zkid, $badge, $byNik[$badge]]);
            if ($up === false) { $stat['gagal']++; continue; }
        }
        $stat['link_nik']++;
        continue;
    }

    // nama mirip -> laporkan saja, JANGAN otomatis
    $key = strtoupper(preg_replace('/\s+/',' ', $nama));
    if (isset($byNama[$key])) {
        $stat['mirip_nama'][] = "$badge | $nama (id_peg={$byNama[$key]})";
        continue;
    }

    // benar-benar baru -> buat kerangka
    $idDept = $petaDept[strtoupper($dnama)] ?? null;
    $idUnit = $idDept !== null ? ($petaUnit[$idDept] ?? null) : null;
    $gender = null;
    $g = strtoupper(trim((string)$u['Gender']));
    if (in_array($g, ['M','L','MALE','LAKI-LAKI'])) $gender = 'L';
    elseif (in_array($g, ['F','P','FEMALE','PEREMPUAN'])) $gender = 'P';

    if ($TULIS) {
        $ins = sqlsrv_query($conn,
            "INSERT INTO dbo.pegawai (nik, nama_peg, gender, unit_kerja_id,
                                      zkteco_userid, zkteco_acno, is_aktif, company_name)
             VALUES (?,?,?,?,?,?,1,'GRP1')",
            [$badge ?: null, $nama, $gender, $idUnit, $zkid, $badge ?: null]);
        if ($ins === false) {
            $stat['gagal']++;
            tulis("  ! gagal '$nama': " . substr(print_r(sqlsrv_errors(), true), 0, 200));
            continue;
        }
        sqlsrv_free_stmt($ins);
    }
    $stat['baru']++;
}

/* ---------- 6. Ringkasan ---------- */
tulis("---------------- RINGKASAN ----------------");
tulis("Sudah terpetakan sebelumnya : {$stat['sudah']}");
tulis("Dihubungkan lewat NIK       : {$stat['link_nik']}");
tulis("Pegawai kerangka baru       : {$stat['baru']}");
tulis("Dilewati (dept dikecualikan): {$stat['lewati_dept']}");
tulis("Dilewati (belum pernah tap) : {$stat['lewati_tap']}");
tulis("Gagal                       : {$stat['gagal']}");
tulis();

if ($stat['mirip_nama']) {
    tulis("PERLU DIPERIKSA MANUAL - nama sama tapi NIK belum cocok (" . count($stat['mirip_nama']) . "):");
    foreach (array_slice($stat['mirip_nama'], 0, 50) as $m) tulis("   $m");
    if (count($stat['mirip_nama']) > 50) tulis("   ... dan " . (count($stat['mirip_nama'])-50) . " lainnya");
    tulis();
    tulis("Hubungkan manual dengan:");
    tulis("   UPDATE dbo.pegawai SET zkteco_userid=<USERID>, zkteco_acno='<Badge>' WHERE id_peg=<id>;");
    tulis();
}

if (!$TULIS) {
    tulis(">>> Ini baru UJI COBA. Kalau angkanya sudah masuk akal,");
    tulis(">>> ubah \$TULIS = true di baris atas, lalu jalankan ulang.");
}
