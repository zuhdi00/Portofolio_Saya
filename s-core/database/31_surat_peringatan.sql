/* =====================================================================
   HISTORI SURAT PERINGATAN (SP) PEGAWAI
   Hanya diakses HRD & Admin IT.
   ===================================================================== */
USE dbHR;
GO

IF OBJECT_ID('dbo.surat_peringatan','U') IS NULL
BEGIN
    DECLARE @tipe NVARCHAR(20);
    SELECT @tipe = UPPER(t.name) FROM sys.columns c JOIN sys.types t ON t.user_type_id=c.user_type_id
    WHERE c.object_id = OBJECT_ID('dbo.pegawai') AND c.name = 'id_peg';

    DECLARE @sql NVARCHAR(MAX) = N'
    CREATE TABLE dbo.surat_peringatan (
        id_sp          INT IDENTITY(1,1) NOT NULL,
        no_sp          NVARCHAR(50)  NULL,          -- SP-YYYYMM-NNN
        pegawai_id     ' + @tipe + N' NOT NULL,
        nama_pegawai   NVARCHAR(128) NULL,          -- arsip, tetap terbaca bila data berubah
        department_id  INT           NULL,
        jenis          NVARCHAR(20)  NOT NULL,      -- TEGURAN / SP1 / SP2 / SP3
        kategori       NVARCHAR(50)  NULL,          -- TERLAMBAT / MANGKIR / DISIPLIN / LAINNYA
        tanggal_terbit DATE          NOT NULL,
        berlaku_sampai DATE          NULL,          -- masa berlaku SP
        alasan         NVARCHAR(500) NULL,
        keterangan     NVARCHAR(1000) NULL,
        file_sp        NVARCHAR(300) NULL,          -- nama file scan SP (opsional)
        status         NVARCHAR(20)  NOT NULL DEFAULT ''AKTIF'',
                        -- AKTIF / SELESAI / DICABUT
        dibuat_oleh    NVARCHAR(128) NULL,
        dibuat_pada    DATETIME      NOT NULL DEFAULT GETDATE(),
        CONSTRAINT PK_surat_peringatan PRIMARY KEY (id_sp),
        CONSTRAINT FK_sp_pegawai FOREIGN KEY (pegawai_id) REFERENCES dbo.pegawai(id_peg)
    );';
    EXEC sp_executesql @sql;
    PRINT '>> tabel surat_peringatan dibuat';
END ELSE PRINT '>> surat_peringatan sudah ada';
GO

IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name='IX_sp_pegawai')
    CREATE INDEX IX_sp_pegawai ON dbo.surat_peringatan(pegawai_id, tanggal_terbit DESC);
GO
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name='IX_sp_status')
    CREATE INDEX IX_sp_status ON dbo.surat_peringatan(status, tanggal_terbit DESC);
GO

SELECT 'surat_peringatan' AS tabel,
       CASE WHEN OBJECT_ID('dbo.surat_peringatan') IS NULL THEN 'BELUM' ELSE 'OK' END AS status;
GO
