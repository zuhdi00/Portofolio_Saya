/* =====================================================================
   Tabel PENGUNDURAN DIRI (resign).
   Alur: DIAJUKAN -> (cetak, ttd fisik) -> BERKAS_MASUK (HRD upload PDF)
         -> DISETUJUI (atasan isi penilaian, auto-ACC)
   ===================================================================== */
USE dbHR;
GO

IF OBJECT_ID('dbo.pengunduran_diri','U') IS NULL
BEGIN
    DECLARE @tipe NVARCHAR(20);
    SELECT @tipe = UPPER(t.name) FROM sys.columns c JOIN sys.types t ON t.user_type_id=c.user_type_id
    WHERE c.object_id=OBJECT_ID('dbo.pegawai') AND c.name='id_peg';

    DECLARE @sql NVARCHAR(MAX) = N'
    CREATE TABLE dbo.pengunduran_diri (
        id_resign      INT IDENTITY(1,1) NOT NULL,
        no_surat       NVARCHAR(40)  NULL,           -- RSG-YYYYMM-NNN
        pegawai_id     ' + @tipe + N' NOT NULL,      -- yang resign
        nama_pegawai   NVARCHAR(128) NULL,           -- disimpan utk arsip
        department_id  INT           NULL,
        tgl_mulai      DATE          NULL,           -- mulai proses/pengajuan
        tgl_berakhir   DATE          NULL,           -- hari kerja terakhir
        alasan         NVARCHAR(500) NULL,
        status         NVARCHAR(20)  NOT NULL DEFAULT ''DIAJUKAN'',
                        -- DIAJUKAN / BERKAS_MASUK / DISETUJUI / DITOLAK
        -- input
        dibuat_oleh    NVARCHAR(128) NULL,
        dibuat_pada    DATETIME      NOT NULL DEFAULT GETDATE(),
        -- berkas PDF (diupload HRD)
        file_pdf       NVARCHAR(300) NULL,           -- path/nama file di \\spsdmz\gg$\HRD\SuratResign
        pdf_oleh       NVARCHAR(128) NULL,
        pdf_pada       DATETIME      NULL,
        -- penilaian atasan (auto-ACC)
        atasan_nama    NVARCHAR(128) NULL,
        atasan_catatan NVARCHAR(1000) NULL,
        atasan_pada    DATETIME      NULL,
        CONSTRAINT PK_pengunduran_diri PRIMARY KEY (id_resign),
        CONSTRAINT FK_resign_pegawai FOREIGN KEY (pegawai_id) REFERENCES dbo.pegawai(id_peg)
    );';
    EXEC sp_executesql @sql;
    PRINT '>> tabel pengunduran_diri dibuat';
END ELSE PRINT '>> pengunduran_diri sudah ada';
GO

IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name='IX_resign_status')
    CREATE INDEX IX_resign_status ON dbo.pengunduran_diri(status, dibuat_pada DESC);
GO

SELECT 'pengunduran_diri' AS tabel,
       CASE WHEN OBJECT_ID('dbo.pengunduran_diri') IS NULL THEN 'BELUM' ELSE 'OK' END AS status;
GO
