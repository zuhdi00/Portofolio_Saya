<?php
/**
 * hris/presensi/proses_scan.php
 * Logika presensi barcode → SQL Server (tabel absensi yang sudah ada).
 * Respon JSON: { ok, nama, telat, pesan }
 */
header('Content-Type: application/json; charset=utf-8');
include '../config/koneksi_sqlsrv.php';   // $conn

$barcode = trim($_POST['barcode'] ?? '');
if ($barcode === '') {
    echo json_encode(['ok' => false, 'pesan' => 'Barcode kosong']); exit;
}

function out($arr) { echo json_encode($arr, JSON_UNESCAPED_UNICODE); exit; }

// ---------- 1. Cari pegawai berdasarkan barcode / NIK ----------
$st = sqlsrv_query($conn,
    "SELECT TOP 1 id, nik, nama, jam_masuk, jam_pulang
     FROM dbo.pegawai_lengkap WHERE barcode = ? OR nik = ?",
    [$barcode, $barcode]);
if ($st === false) out(['ok' => false, 'pesan' => 'Query error']);
$peg = sqlsrv_fetch_array($st, SQLSRV_FETCH_ASSOC);
if (!$peg) out(['ok' => false, 'nama' => null, 'pesan' => "Barcode '$barcode' tidak terdaftar"]);

$idPeg = (int)$peg['id'];
$nama  = $peg['nama'];
$ip    = $_SERVER['REMOTE_ADDR'] ?? '';
$jamMasukStd  = $peg['jam_masuk']  instanceof DateTime ? $peg['jam_masuk']->format('H:i:s')  : '08:00:00';
$jamPulangStd = $peg['jam_pulang'] instanceof DateTime ? $peg['jam_pulang']->format('H:i:s') : '17:00:00';

// ---------- 2. Cek absensi hari ini ----------
$st = sqlsrv_query($conn,
    "SELECT TOP 1 ID_Absensi, Tanggal_Waktu, Jam_Pulang
     FROM dbo.absensi
     WHERE ID_Pegawai = ? AND CAST(Tanggal_Waktu AS DATE) = CAST(GETDATE() AS DATE)",
    [$idPeg]);
$hariIni = $st ? sqlsrv_fetch_array($st, SQLSRV_FETCH_ASSOC) : null;

if (!$hariIni) {
    // ---------- 3a. Belum ada → catat MASUK ----------
    $telat = (date('H:i:s') > $jamMasukStd);
    $status = $telat ? 'Terlambat' : 'Hadir';

    $ins = sqlsrv_query($conn,
        "INSERT INTO dbo.absensi (ID_Absensi, ID_Pegawai, Tanggal_Waktu, Status_Kehadiran, Metode_Verifikasi, Lokasi_IP)
         VALUES (NEXT VALUE FOR dbo.seq_absensi, ?, GETDATE(), ?, N'Barcode', ?)",
        [$idPeg, $status, $ip]);
    if ($ins === false) out(['ok' => false, 'nama' => $nama, 'pesan' => 'Gagal menyimpan absensi']);

    $jam = date('H:i');
    out([
        'ok' => true, 'nama' => $nama, 'telat' => $telat,
        'pesan' => $telat
            ? "MASUK $jam — TERLAMBAT (jadwal " . substr($jamMasukStd, 0, 5) . ")"
            : "MASUK $jam — Tepat waktu. Selamat bekerja!"
    ]);
} else {
    // ---------- 3b. Sudah ada ----------
    if ($hariIni['Jam_Pulang'] !== null) {
        out(['ok' => true, 'nama' => $nama, 'telat' => false,
             'pesan' => 'Anda sudah presensi masuk & pulang hari ini']);
    }
    // catat PULANG
    $upd = sqlsrv_query($conn,
        "UPDATE dbo.absensi SET Jam_Pulang = GETDATE() WHERE ID_Absensi = ?",
        [$hariIni['ID_Absensi']]);
    if ($upd === false) out(['ok' => false, 'nama' => $nama, 'pesan' => 'Gagal update jam pulang']);

    $pulangCepat = (date('H:i:s') < $jamPulangStd);
    out([
        'ok' => true, 'nama' => $nama, 'telat' => false,
        'pesan' => 'PULANG ' . date('H:i') .
                   ($pulangCepat ? ' — sebelum jam ' . substr($jamPulangStd, 0, 5) : '. Hati-hati di jalan!')
    ]);
}
