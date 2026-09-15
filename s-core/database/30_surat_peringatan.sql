/* Tabel SURAT PERINGATAN - database dbHR (SQL Server). */
USE dbHR;
GO
IF OBJECT_ID('dbo.surat_peringatan', 'U') IS NULL
BEGIN
    DECLARE @tipe_pegawai_id NVARCHAR(128);
    SELECT @tipe_pegawai_id = CASE
        WHEN t.name IN ('nvarchar','nchar') THEN t.name + '(' + CASE WHEN c.max_length = -1 THEN 'MAX' ELSE CONVERT(VARCHAR(10), c.max_length / 2) END + ')'
        WHEN t.name IN ('varchar','char','varbinary','binary') THEN t.name + '(' + CASE WHEN c.max_length = -1 THEN 'MAX' ELSE CONVERT(VARCHAR(10), c.max_length) END + ')'
        WHEN t.name IN ('decimal','numeric') THEN t.name + '(' + CONVERT(VARCHAR(10), c.precision) + ',' + CONVERT(VARCHAR(10), c.scale) + ')'
        ELSE t.name
    END
    FROM sys.columns c
    JOIN sys.types t ON t.user_type_id = c.user_type_id
    WHERE c.object_id = OBJECT_ID('dbo.pegawai') AND c.name = 'id_peg';

    IF @tipe_pegawai_id IS NULL
        THROW 50001, 'Kolom dbo.pegawai.id_peg tidak ditemukan.', 1;

    DECLARE @sql NVARCHAR(MAX) = N'
    CREATE TABLE dbo.surat_peringatan (
        id_sp INT IDENTITY(1,1) NOT NULL,
        no_surat NVARCHAR(50) NOT NULL,
        pegawai_id ' + @tipe_pegawai_id + N' NOT NULL,
        tanggal_sp DATE NOT NULL,
        kategori_sp NVARCHAR(20) NOT NULL,
        status_pemutihan BIT NOT NULL DEFAULT 0,
        jenis_pelanggaran NVARCHAR(255) NOT NULL,
        kronologi NVARCHAR(MAX) NULL,
        dasar_pelanggaran NVARCHAR(500) NULL,
        tindakan NVARCHAR(500) NULL,
        dibuat_oleh NVARCHAR(100) NULL,
        dibuat_pada DATETIME NOT NULL DEFAULT GETDATE(),
        CONSTRAINT PK_surat_peringatan PRIMARY KEY (id_sp),
        CONSTRAINT UQ_surat_peringatan_nomor UNIQUE (no_surat),
        CONSTRAINT FK_surat_peringatan_pegawai FOREIGN KEY (pegawai_id) REFERENCES dbo.pegawai(id_peg)
    );';
    EXEC sp_executesql @sql;
END
GO
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name='IX_surat_peringatan_pegawai_tanggal')
    CREATE INDEX IX_surat_peringatan_pegawai_tanggal ON dbo.surat_peringatan(pegawai_id, tanggal_sp DESC);
GO