/* ============================================================
   32_surat_peringatan_revisi.sql
   Menambah pelacakan REVISI pada dbo.surat_peringatan (dbHR).

   - kolom revisi / diubah_oleh / diubah_pada dipakai untuk mengisi
     "No. Revisi." dan "Tanggal" pada kop FORMULIR FM-HRD.22
   - tabel dbo.surat_peringatan_revisi menyimpan riwayat setiap
     perubahan (siapa, kapan, alasan, isi sebelum diubah)

   Jalankan sekali saja lewat SSMS / sqlcmd.
   ============================================================ */
USE dbHR;
GO

/* ---------- 1. kolom pelacak revisi ---------- */
IF COL_LENGTH('dbo.surat_peringatan','revisi') IS NULL
    ALTER TABLE dbo.surat_peringatan ADD revisi INT NOT NULL CONSTRAINT DF_sp_revisi DEFAULT 0;
GO
IF COL_LENGTH('dbo.surat_peringatan','diubah_oleh') IS NULL
    ALTER TABLE dbo.surat_peringatan ADD diubah_oleh NVARCHAR(100) NULL;
GO
IF COL_LENGTH('dbo.surat_peringatan','diubah_pada') IS NULL
    ALTER TABLE dbo.surat_peringatan ADD diubah_pada DATETIME NULL;
GO

/* surat lama: pastikan revisi = 0 */
UPDATE dbo.surat_peringatan SET revisi = 0 WHERE revisi IS NULL;
GO

/* ---------- 2. tabel riwayat revisi ---------- */
IF OBJECT_ID('dbo.surat_peringatan_revisi','U') IS NULL
BEGIN
    CREATE TABLE dbo.surat_peringatan_revisi (
        id_revisi         INT IDENTITY(1,1) NOT NULL,
        id_sp             INT NOT NULL,
        revisi_ke         INT NOT NULL,
        alasan_revisi     NVARCHAR(500) NULL,
        data_sebelum      NVARCHAR(MAX) NULL,   -- snapshot JSON sebelum diubah
        diubah_oleh       NVARCHAR(100) NULL,
        peran_pengubah    NVARCHAR(50)  NULL,
        diubah_pada       DATETIME NOT NULL CONSTRAINT DF_spr_pada DEFAULT GETDATE(),
        CONSTRAINT PK_surat_peringatan_revisi PRIMARY KEY (id_revisi),
        CONSTRAINT FK_spr_sp FOREIGN KEY (id_sp)
            REFERENCES dbo.surat_peringatan(id_sp) ON DELETE CASCADE
    );
END
GO

IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name='IX_spr_sp')
    CREATE INDEX IX_spr_sp ON dbo.surat_peringatan_revisi(id_sp, revisi_ke DESC);
GO

/* ---------- verifikasi ---------- */
SELECT c.name AS kolom, t.name AS tipe, c.max_length
FROM sys.columns c
JOIN sys.types t ON t.user_type_id = c.user_type_id
WHERE c.object_id = OBJECT_ID('dbo.surat_peringatan')
ORDER BY c.column_id;
GO
