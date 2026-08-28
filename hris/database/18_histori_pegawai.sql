/* =====================================================================
   Tabel HISTORI perubahan data pegawai (audit trail per kolom).
   Diisi dari PHP saat tambah/edit pegawai.
   Satu baris = satu kolom yang berubah.
   ===================================================================== */
USE dbHR;
GO

IF OBJECT_ID('dbo.histori_pegawai','U') IS NULL
BEGIN
    DECLARE @tipe NVARCHAR(20);
    SELECT @tipe = UPPER(t.name) FROM sys.columns c JOIN sys.types t ON t.user_type_id=c.user_type_id
    WHERE c.object_id=OBJECT_ID('dbo.pegawai') AND c.name='id_peg';

    DECLARE @sql NVARCHAR(MAX) = N'
    CREATE TABLE dbo.histori_pegawai (
        id_histori   BIGINT IDENTITY(1,1) NOT NULL,
        pegawai_id   ' + @tipe + N' NOT NULL,        -- pegawai yang diubah
        aksi         NVARCHAR(10)  NOT NULL,          -- TAMBAH / EDIT
        kolom        NVARCHAR(64)  NULL,              -- nama kolom yang berubah
        nilai_lama   NVARCHAR(MAX) NULL,
        nilai_baru   NVARCHAR(MAX) NULL,
        diubah_oleh  NVARCHAR(128) NULL,              -- username/nama user yang mengubah
        diubah_id    INT           NULL,              -- id_user hris_users (opsional)
        diubah_pada  DATETIME      NOT NULL DEFAULT GETDATE(),
        CONSTRAINT PK_histori_pegawai PRIMARY KEY (id_histori)
    );';
    EXEC sp_executesql @sql;
    PRINT '>> tabel histori_pegawai dibuat';
END ELSE PRINT '>> histori_pegawai sudah ada';
GO

IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name='IX_histori_pegawai')
    CREATE INDEX IX_histori_pegawai ON dbo.histori_pegawai(pegawai_id, diubah_pada DESC);
GO

SELECT 'histori_pegawai' AS tabel,
       CASE WHEN OBJECT_ID('dbo.histori_pegawai') IS NULL THEN 'BELUM' ELSE 'OK' END AS status;
GO
