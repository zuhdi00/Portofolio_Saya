/* ============================================================
   31_surat_peringatan_detail.sql
   Menambah kolom pelengkap FORMULIR FM-HRD.22 pada tabel
   dbo.surat_peringatan (database dbHR / SQL Server).

   OPSIONAL. cetak.php tetap jalan tanpa patch ini,
   kolom-kolom di bawah hanya akan tercetak kosong.

   Jalankan sekali saja lewat SSMS / sqlcmd.
   ============================================================ */
USE dbHR;
GO

IF COL_LENGTH('dbo.surat_peringatan','tempat_pelanggaran') IS NULL
    ALTER TABLE dbo.surat_peringatan ADD tempat_pelanggaran NVARCHAR(150) NULL;
GO
IF COL_LENGTH('dbo.surat_peringatan','pukul_pelanggaran') IS NULL
    ALTER TABLE dbo.surat_peringatan ADD pukul_pelanggaran NVARCHAR(10) NULL;
GO
IF COL_LENGTH('dbo.surat_peringatan','saksi_bukti') IS NULL
    ALTER TABLE dbo.surat_peringatan ADD saksi_bukti NVARCHAR(200) NULL;
GO
IF COL_LENGTH('dbo.surat_peringatan','kerugian_material') IS NULL
    ALTER TABLE dbo.surat_peringatan ADD kerugian_material NVARCHAR(100) NULL;
GO
IF COL_LENGTH('dbo.surat_peringatan','lama_skorsing') IS NULL
    ALTER TABLE dbo.surat_peringatan ADD lama_skorsing NVARCHAR(50) NULL;
GO

/* verifikasi */
SELECT c.name AS kolom, t.name AS tipe, c.max_length
FROM sys.columns c
JOIN sys.types t ON t.user_type_id = c.user_type_id
WHERE c.object_id = OBJECT_ID('dbo.surat_peringatan')
ORDER BY c.column_id;
GO
