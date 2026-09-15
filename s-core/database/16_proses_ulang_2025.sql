/* =====================================================================
   Proses ulang SEMUA absensi 2025+ dengan logika terkini.
   MASALAH: sebagian baris absensi adalah warisan proses versi lama
   (mis. shift salah, jam_masuk NULL padahal tap lengkap) karena tap-nya
   sudah diproses=1 sehingga logika baru tak menyentuhnya.
   SOLUSI: reset tap 2025+ jadi diproses=0, hapus absensi 2025+,
   lalu jalankan sync_zkteco_proses.php untuk membangun ulang.

   Data mentah TIDAK dihapus - hanya diproses ulang.
   ===================================================================== */
USE dbHR;
GO

/* ---------- lihat dampak sebelum eksekusi ---------- */
SELECT 'absensi 2025+'  AS item, COUNT(*) AS jml FROM dbo.absensi WHERE tanggal >= '2025-01-01'
UNION ALL
SELECT 'koreksi 2025+', COUNT(*) FROM dbo.absensi_koreksi WHERE tanggal >= '2025-01-01'
UNION ALL
SELECT 'tap 2025+ (akan direset)', COUNT(*) FROM dbo.zkteco_checkinout WHERE checktime >= '2025-01-01';
GO

/* ---------- 1. hapus absensi & koreksi 2025+ ---------- */
DELETE FROM dbo.absensi_koreksi WHERE tanggal >= '2025-01-01';
PRINT '>> koreksi 2025+ dihapus';

DELETE FROM dbo.absensi WHERE tanggal >= '2025-01-01';
PRINT '>> absensi 2025+ dihapus';
GO

/* ---------- 2. reset tap 2025+ jadi belum diproses ---------- */
UPDATE dbo.zkteco_checkinout SET diproses = 0 WHERE checktime >= '2025-01-01';
PRINT '>> tap 2025+ direset ke diproses=0';
GO

/* ---------- 3. pastikan tap PRA-2025 tetap diproses=1 (tidak ikut diolah) ----------
   Data pra-2025 arah tapnya tidak akurat (era 1 mesin), biarkan terkunci. */
UPDATE dbo.zkteco_checkinout SET diproses = 1 WHERE checktime < '2025-01-01';
PRINT '>> tap pra-2025 dipastikan terkunci (diproses=1)';
GO

SELECT COUNT(*) AS tap_siap_diproses FROM dbo.zkteco_checkinout WHERE diproses = 0;
GO

PRINT '';
PRINT '=== SELESAI. Sekarang jalankan di CMD: ===';
PRINT 'C:\xampp\php\php.exe C:\xampp\htdocs\hris\presensi\sync_zkteco_proses.php';
GO
