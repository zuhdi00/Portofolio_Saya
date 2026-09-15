/* =====================================================================
   Hitung ulang kolom absensi.status memakai toleransi terbaru
   (dbo.pengaturan_shift.toleransi_menit).

   Tidak perlu memproses ulang tap - langsung menghitung dari jam_masuk
   yang sudah tersimpan. Aman & cepat.

   Yang DIUBAH  : baris berstatus 'hadir' / 'terlambat' saja.
   Yang DIBIARKAN: izin, sakit, cuti, alpha (status manual HR).
   ===================================================================== */
USE dbHR;
GO

/* ---------- atur rentang tanggal yang dihitung ulang ---------- */
DECLARE @dari DATE = DATEADD(day, -30, CAST(GETDATE() AS DATE));   -- ubah sesuai kebutuhan
-- contoh seluruh 2026: SET @dari = '2026-01-01';

/* ---------- lihat dampak SEBELUM diubah ---------- */
SELECT
    'sebelum' AS tahap,
    SUM(CASE WHEN a.status='terlambat' THEN 1 ELSE 0 END) AS terlambat,
    SUM(CASE WHEN a.status='hadir'     THEN 1 ELSE 0 END) AS hadir
FROM dbo.absensi a
WHERE a.tanggal >= @dari AND a.status IN ('hadir','terlambat');

/* ---------- hitung ulang ---------- */
UPDATE a
SET a.status = CASE
        /* shift 3: tap 21:00-23:59 = datang lebih awal, bukan terlambat */
        WHEN a.shift_ke = 3 AND a.jam_masuk >= '21:00:00' THEN 'hadir'
        /* lewat jam mulai + toleransi -> terlambat */
        WHEN a.jam_masuk > DATEADD(minute, ps.toleransi_menit, ps.jam_mulai) THEN 'terlambat'
        ELSE 'hadir'
    END
FROM dbo.absensi a
JOIN dbo.pengaturan_shift ps ON ps.shift_ke = a.shift_ke
WHERE a.tanggal >= @dari
  AND a.jam_masuk IS NOT NULL
  AND a.status IN ('hadir','terlambat');

PRINT '>> status dihitung ulang dengan toleransi terbaru';

/* ---------- hasil SESUDAH ---------- */
SELECT
    'sesudah' AS tahap,
    SUM(CASE WHEN a.status='terlambat' THEN 1 ELSE 0 END) AS terlambat,
    SUM(CASE WHEN a.status='hadir'     THEN 1 ELSE 0 END) AS hadir
FROM dbo.absensi a
WHERE a.tanggal >= @dari AND a.status IN ('hadir','terlambat');
GO

/* ---------- contoh cek: yang tap 08:00-08:05 harus jadi 'hadir' ---------- */
SELECT TOP 20 a.tanggal, p.nama_peg, a.shift_ke, a.jam_masuk, a.status
FROM dbo.absensi a
JOIN dbo.pegawai p ON p.id_peg = a.pegawai_id
WHERE a.shift_ke = 1
  AND a.jam_masuk > '08:00:00' AND a.jam_masuk <= '08:05:00'
  AND a.tanggal >= DATEADD(day,-30,CAST(GETDATE() AS DATE))
ORDER BY a.tanggal DESC;
GO
