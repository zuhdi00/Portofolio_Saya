/* =====================================================================
   [REVISI] Hitung ulang absensi.status dengan toleransi PER MENIT.

   Aturan: jam mulai 08:00, toleransi 5 menit
     08:00:00 - 08:05:59  -> HADIR      (menit ke-0 s/d ke-5, masih toleransi)
     08:06:00 dan sesudahnya -> TERLAMBAT

   Perbaikan dari 27: dulu membandingkan sampai detik, sehingga
   08:05:30 salah dihitung terlambat. Sekarang pakai selisih MENIT.
   ===================================================================== */
USE dbHR;
GO

DECLARE @dari DATE = DATEADD(day, -30, CAST(GETDATE() AS DATE));
-- ganti bila perlu, contoh: SET @dari = '2026-01-01';

/* ---------- sebelum ---------- */
SELECT 'sebelum' AS tahap,
       SUM(CASE WHEN a.status='terlambat' THEN 1 ELSE 0 END) AS terlambat,
       SUM(CASE WHEN a.status='hadir'     THEN 1 ELSE 0 END) AS hadir
FROM dbo.absensi a
WHERE a.tanggal >= @dari AND a.status IN ('hadir','terlambat');

/* ---------- hitung ulang (selisih MENIT) ---------- */
UPDATE a
SET a.status = CASE
        /* shift 3: tap 21:00-23:59 = datang lebih awal */
        WHEN a.shift_ke = 3 AND a.jam_masuk >= '21:00:00' THEN 'hadir'
        /* selisih menit dari jam mulai melebihi toleransi -> terlambat */
        WHEN DATEDIFF(minute, ps.jam_mulai, a.jam_masuk) > ps.toleransi_menit THEN 'terlambat'
        ELSE 'hadir'
    END
FROM dbo.absensi a
JOIN dbo.pengaturan_shift ps ON ps.shift_ke = a.shift_ke
WHERE a.tanggal >= @dari
  AND a.jam_masuk IS NOT NULL
  AND a.status IN ('hadir','terlambat');

PRINT '>> status dihitung ulang (toleransi per menit)';

/* ---------- sesudah ---------- */
SELECT 'sesudah' AS tahap,
       SUM(CASE WHEN a.status='terlambat' THEN 1 ELSE 0 END) AS terlambat,
       SUM(CASE WHEN a.status='hadir'     THEN 1 ELSE 0 END) AS hadir
FROM dbo.absensi a
WHERE a.tanggal >= @dari AND a.status IN ('hadir','terlambat');
GO

/* ---------- uji batas: 08:05:xx harus HADIR, 08:06:xx harus TERLAMBAT ---------- */
SELECT TOP 30 a.tanggal, p.nama_peg, a.jam_masuk, a.status
FROM dbo.absensi a
JOIN dbo.pegawai p ON p.id_peg = a.pegawai_id
WHERE a.shift_ke = 1
  AND a.jam_masuk >= '08:05:00' AND a.jam_masuk < '08:07:00'
  AND a.tanggal >= DATEADD(day,-30,CAST(GETDATE() AS DATE))
ORDER BY a.jam_masuk;
GO
