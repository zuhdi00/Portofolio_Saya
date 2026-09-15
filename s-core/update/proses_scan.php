<?php
/**
 * presensi/proses_scan.php
 * Presensi barcode -> dbHR (tabel pegawai + absensi).
 * Respon JSON: { ok, nama, telat, pesan }
 */
header('Content-Type: application/json; charset=utf-8');
include '../config/koneksi_sqlsrv.php';   // $conn

$barcode = trim($_POST['barcode'] ?? '');
if ($barcode === '') {
    echo json_encode(['ok' => false, 'pesan' => 'Barcode kosong']); exit;
}

function out($arr) { echo json_encode($arr, JSON_UNESCAPED_UNICODE); exit; }

// ---------- 1. Cari pegawai berdasarkan barcode / no_ktp ----------
$st = sqlsrv_query($conn,
    "SELECT TOP 1 id_peg, no_ktp, nama_peg, jam_masuk_std, jam_pulang_std
     FROM dbo.pegawai WHERE barcode = ? OR no_ktp = ?",
    [$barcode, $barcode]);
if ($st === false) out(['ok' => false, 'pesan' => 'Query error']);
$peg = sqlsrv_fetch_array($st, SQLSRV_FETCH_ASSOC);
if (!$peg) out(['ok' => false, 'nama' => null, 'pesan' => "Barcode '$barcode' tidak terdaftar"]);

$idPeg = (int)$peg['id_peg'];
$nama  = $peg['nama_peg'];
$jamMasukStd  = $peg['jam_masuk_std']  instanceof DateTime ? $peg['jam_masuk_std']->format('H:i:s')  : '08:00:00';
$jamPulangStd = $peg['jam_pulang_std'] instanceof DateTime ? $peg['jam_pulang_std']->format('H:i:s') : '17:00:00';

// ---------- 2. Cek absensi hari ini ----------
$st = sqlsrv_query($conn,
    "SELECT TOP 1 id_absensi, jam_keluar
     FROM dbo.absensi
     WHERE pegawai_id = ? AND tanggal = CAST(GETDATE() AS DATE)",
    [$idPeg]);
$hariIni = $st ? sqlsrv_fetch_array($st, SQLSRV_FETCH_ASSOC) : null;

if (!$hariIni) {
    // ---------- 3a. Belum ada -> catat MASUK ----------
    $telat  = (date('H:i:s') > $jamMasukStd);
    $status = $telat ? 'Terlambat' : 'Hadir';

    $ins = sqlsrv_query($conn,
        "INSERT INTO dbo.absensi (pegawai_id, tanggal, jam_masuk, status, keterangan)
         VALUES (?, CAST(GETDATE() AS DATE), CAST(GETDATE() AS TIME), ?, N'Barcode')",
        [$idPeg, $status]);
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
    if ($hariIni['jam_keluar'] !== null) {
        out(['ok' => true, 'nama' => $nama, 'telat' => false,
             'pesan' => 'Anda sudah presensi masuk & pulang hari ini']);
    }
    // catat PULANG
    $upd = sqlsrv_query($conn,
        "UPDATE dbo.absensi SET jam_keluar = CAST(GETDATE() AS TIME) WHERE id_absensi = ?",
        [$hariIni['id_absensi']]);
    if ($upd === false) out(['ok' => false, 'nama' => $nama, 'pesan' => 'Gagal update jam pulang']);

    $pulangCepat = (date('H:i:s') < $jamPulangStd);
    out([
        'ok' => true, 'nama' => $nama, 'telat' => false,
        'pesan' => 'PULANG ' . date('H:i') .
                   ($pulangCepat ? ' — sebelum jam ' . substr($jamPulangStd, 0, 5) : '. Hati-hati di jalan!')
    ]);
}
