/* =====================================================================
   Ubah toleransi keterlambatan jadi 5 menit untuk semua shift.
   Nilai ini dipakai sync_zkteco_proses.php saat menandai status
   'terlambat' di dbo.absensi (versi RESMI / HR).

   Catatan: dashboard untuk atasan menghitung ulang tanpa toleransi
   (langsung dibandingkan dgn jam_mulai shift), jadi tabel ini tetap
   menjadi acuan HR saja.
   ===================================================================== */
USE dbHR;
GO

UPDATE dbo.pengaturan_shift SET toleransi_menit = 5;
PRINT '>> toleransi semua shift = 5 menit';
GO

SELECT shift_ke, jam_mulai, jam_selesai, masuk_dari, masuk_sampai, toleransi_menit
FROM dbo.pengaturan_shift ORDER BY shift_ke;
GO

/* -------------------------------------------------------------------
   Setelah ini, jalankan proses ulang supaya status absensi menyesuaikan
   toleransi baru (mundur 7 hari misalnya):
     C:\xampp\php\php.exe C:\xampp\htdocs\hris\presensi\sync_zkteco_proses.php 7
   ------------------------------------------------------------------- */
