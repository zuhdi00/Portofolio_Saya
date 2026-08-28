/* =====================================================================
   Tabel PENGAJUAN LEMBUR - database dbHR
   Alur: Diajukan -> Approval Atasan -> Approval HR -> Disetujui/Ditolak
   ===================================================================== */
USE dbHR;
GO

IF OBJECT_ID('dbo.lembur', 'U') IS NULL
BEGIN
    -- samakan tipe pegawai_id dengan pegawai.id_peg
    DECLARE @tipe NVARCHAR(20);
    SELECT @tipe = UPPER(t.name)
    FROM sys.columns c JOIN sys.types t ON t.user_type_id = c.user_type_id
    WHERE c.object_id = OBJECT_ID('dbo.pegawai') AND c.name = 'id_peg';

    DECLARE @sql NVARCHAR(MAX) = N'
    CREATE TABLE dbo.lembur (
        id_lembur      INT IDENTITY(1,1) NOT NULL,
        pegawai_id     ' + @tipe + N' NOT NULL,
        tanggal        DATE          NOT NULL,          -- tanggal lembur
        jam_mulai      TIME          NOT NULL,
        jam_selesai    TIME          NOT NULL,
        durasi_jam     DECIMAL(5,2)  NULL,              -- dihitung otomatis
        jenis          NVARCHAR(20)  NOT NULL DEFAULT ''biasa'',  -- biasa / libur / hari_besar
        alasan         NVARCHAR(500) NOT NULL,

        -- alur approval bertingkat
        status         NVARCHAR(30)  NOT NULL DEFAULT ''DIAJUKAN'',
                        -- DIAJUKAN / DISETUJUI_ATASAN / DITOLAK_ATASAN /
                        -- DISETUJUI_HR / DITOLAK_HR
        diajukan_oleh  ' + @tipe + N' NULL,             -- pegawai_id pengaju
        diajukan_pada  DATETIME      NOT NULL DEFAULT GETDATE(),

        atasan_id      ' + @tipe + N' NULL,
        atasan_pada    DATETIME      NULL,
        atasan_catatan NVARCHAR(500) NULL,

        hr_id          ' + @tipe + N' NULL,
        hr_pada        DATETIME      NULL,
        hr_catatan     NVARCHAR(500) NULL,

        CONSTRAINT PK_lembur PRIMARY KEY (id_lembur),
        CONSTRAINT FK_lembur_pegawai FOREIGN KEY (pegawai_id)
            REFERENCES dbo.pegawai(id_peg)
    );';
    EXEC sp_executesql @sql;
    PRINT '>> tabel dbo.lembur dibuat (pegawai_id bertipe ' + @tipe + ')';
END
ELSE PRINT '>> tabel dbo.lembur sudah ada';
GO

IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name='IX_lembur_status')
    CREATE INDEX IX_lembur_status ON dbo.lembur(status, tanggal);
GO
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name='IX_lembur_pegawai')
    CREATE INDEX IX_lembur_pegawai ON dbo.lembur(pegawai_id, tanggal);
GO

SELECT 'dbo.lembur' AS tabel,
       CASE WHEN OBJECT_ID('dbo.lembur') IS NULL THEN 'BELUM ADA' ELSE 'OK' END AS status;
GO
