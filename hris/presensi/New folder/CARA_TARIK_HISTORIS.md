# Tarik Data Presensi Historis (Sekali Jalan)

Menarik SEMUA tap sejak 2013 dari MDB ke dbHR. Berat (~2,4 juta baris),
jadi jalankan DI LUAR JAM KERJA (malam / akhir pekan).

## Persiapan

1. Pastikan MDB terbaru sudah tersalin:

       C:\xampp\htdocs\hris\presensi\salin_mdb_zkteco.bat

2. Cek isi staging sekarang (untuk perbandingan nanti):

       (SSMS)
       SELECT COUNT(*) FROM dbo.zkteco_checkinout;
       SELECT MIN(checktime), MAX(checktime) FROM dbo.zkteco_checkinout;

## Jalankan tarik historis

Tarik dari 2013 sampai SEBELUM data yang sudah ada (28 Juni 2026),
supaya tidak menabrak data yang sudah masuk:

    C:\xampp\php\php.exe C:\xampp\htdocs\hris\presensi\tarik_historis_zkteco.php 2013-01-01 2026-06-27

Script akan:
- Mendeteksi rentang kosong -> pakai insert cepat (batch 1000 baris)
- Menampilkan progress tiap 50.000 baris
- Memperbarui posisi sync setelah selesai

Perkiraan waktu: 5-20 menit tergantung kecepatan server.
JANGAN tutup jendela CMD sampai muncul "SELESAI".

## Setelah selesai

1. Verifikasi jumlah:

       SELECT COUNT(*) AS total,
              MIN(checktime) AS paling_lama,
              MAX(checktime) AS terbaru
       FROM dbo.zkteco_checkinout;

   Harusnya jutaan baris, paling_lama ~2013.

2. Proses jadi absensi. INI JUGA BERAT - jalankan sekali:

       C:\xampp\php\php.exe C:\xampp\htdocs\hris\presensi\sync_zkteco_proses.php

   Proses akan mengolah semua tap yang diproses=0 jadi baris absensi,
   dan MENGISI SENDIRI banyak "perlu koreksi" lama karena pasangan
   tap yang dulu hilang (di luar rentang impor) kini ketemu.

3. Cek hasil:

       SELECT COUNT(*) AS baris, COUNT(DISTINCT pegawai_id) AS pegawai,
              MIN(tanggal) AS awal, MAX(tanggal) AS akhir,
              SUM(CASE WHEN perlu_koreksi=1 THEN 1 ELSE 0 END) AS perlu_approval
       FROM dbo.absensi;

   Bandingkan "perlu_approval" dengan angka lama (1.585). Harusnya
   TURUN drastis karena banyak pasangan tap kini lengkap.

## Kalau proses terlalu berat / server lambat

Proses per rentang bulanan supaya ringan. Ulangi query ini per bulan
sebelum menjalankan sync_zkteco_proses.php, ATAU biarkan sekali jalan
di malam hari. Kalau CMD terputus, aman diulang - yang sudah diproses
ditandai diproses=1 dan tidak diproses dua kali.

## Rollback (kalau perlu batal)

    -- hapus tap historis (SEBELUM 28 Juni) dari staging
    DELETE FROM dbo.zkteco_checkinout WHERE checktime < '2026-06-28';
    -- kembalikan posisi sync
    UPDATE dbo.sync_zkteco_state SET last_checktime='2026-07-24 09:01:34' WHERE sumber='ATT2000';
