/* =====================================================================
   Tambah kolom KONTAK DARURAT di tabel pegawai.
   Kolom hanya dibuat kalau belum ada (aman dijalankan berulang).
   ===================================================================== */
USE dbHR;
GO

IF COL_LENGTH('dbo.pegawai','darurat_nama') IS NULL
    ALTER TABLE dbo.pegawai ADD darurat_nama NVARCHAR(128) NULL;
GO
IF COL_LENGTH('dbo.pegawai','darurat_hubungan') IS NULL
    ALTER TABLE dbo.pegawai ADD darurat_hubungan NVARCHAR(40) NULL;
GO
IF COL_LENGTH('dbo.pegawai','darurat_hp') IS NULL
    ALTER TABLE dbo.pegawai ADD darurat_hp NVARCHAR(30) NULL;
GO
IF COL_LENGTH('dbo.pegawai','darurat_alamat') IS NULL
    ALTER TABLE dbo.pegawai ADD darurat_alamat NVARCHAR(300) NULL;
GO

PRINT '>> kolom kontak darurat siap';
SELECT name FROM sys.columns
WHERE object_id=OBJECT_ID('dbo.pegawai') AND name LIKE 'darurat%';
GO
