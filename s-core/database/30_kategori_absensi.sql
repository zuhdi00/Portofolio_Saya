/* =====================================================================
   Kategori pegawai: MASUK ABSENSI (tap mesin) vs TIDAK MASUK ABSENSI
   (dikelola manual oleh PIC HR).

   ikut_absensi = 1 -> presensi dari mesin ZKTeco
   ikut_absensi = 0 -> tidak tap mesin, kehadiran dicatat manual HR
   ===================================================================== */
USE dbHR;
GO

IF COL_LENGTH('dbo.pegawai','ikut_absensi') IS NULL
BEGIN
    ALTER TABLE dbo.pegawai ADD ikut_absensi BIT NOT NULL DEFAULT 1;
    PRINT '>> kolom ikut_absensi dibuat (default 1)';
END ELSE PRINT '>> ikut_absensi sudah ada';
GO

/* alasan / keterangan kenapa tidak ikut absensi mesin */
IF COL_LENGTH('dbo.pegawai','absensi_keterangan') IS NULL
BEGIN
    ALTER TABLE dbo.pegawai ADD absensi_keterangan NVARCHAR(200) NULL;
    PRINT '>> kolom absensi_keterangan dibuat';
END ELSE PRINT '>> absensi_keterangan sudah ada';
GO

/* PIC HR yang bertanggung jawab mencatat kehadiran manual */
IF COL_LENGTH('dbo.pegawai','absensi_pic') IS NULL
BEGIN
    ALTER TABLE dbo.pegawai ADD absensi_pic NVARCHAR(128) NULL;
    PRINT '>> kolom absensi_pic dibuat';
END ELSE PRINT '>> absensi_pic sudah ada';
GO

/* ---------- pengisian awal otomatis ----------
   Pegawai TANPA zkteco_userid dianggap tidak tap mesin.
   Hanya dijalankan sekali (saat semua masih bernilai default 1).      */
IF NOT EXISTS (SELECT 1 FROM dbo.pegawai WHERE ikut_absensi = 0)
BEGIN
    UPDATE dbo.pegawai
    SET ikut_absensi = 0,
        absensi_keterangan = N'Otomatis: belum punya ID mesin ZKTeco'
    WHERE is_aktif = 1 AND (zkteco_userid IS NULL OR zkteco_userid = 0);
    PRINT '>> pegawai tanpa ID mesin ditandai tidak ikut absensi';
END
GO

/* ---------- ringkasan ---------- */
SELECT
    SUM(CASE WHEN ikut_absensi = 1 THEN 1 ELSE 0 END) AS masuk_absensi,
    SUM(CASE WHEN ikut_absensi = 0 THEN 1 ELSE 0 END) AS tidak_masuk_absensi,
    COUNT(*) AS total_aktif
FROM dbo.pegawai WHERE is_aktif = 1;
GO
