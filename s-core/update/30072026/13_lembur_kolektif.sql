/* =====================================================================
   LEMBUR KOLEKTIF - satu form banyak karyawan (per divisi/tanggal)
   Struktur header-detail:
     lembur_form   = 1 baris per pengajuan (divisi, tanggal, atasan)
     lembur_detail = banyak baris karyawan di bawah 1 form
   Tabel lama dbo.lembur (per individu) dibiarkan - tidak dihapus.
   ===================================================================== */
USE dbHR;
GO

/* ---------- HEADER ---------- */
IF OBJECT_ID('dbo.lembur_form','U') IS NULL
BEGIN
    DECLARE @tipe NVARCHAR(20);
    SELECT @tipe = UPPER(t.name) FROM sys.columns c JOIN sys.types t ON t.user_type_id=c.user_type_id
    WHERE c.object_id=OBJECT_ID('dbo.pegawai') AND c.name='id_peg';

    DECLARE @sql NVARCHAR(MAX) = N'
    CREATE TABLE dbo.lembur_form (
        id_form        INT IDENTITY(1,1) NOT NULL,
        no_form        NVARCHAR(30)  NULL,            -- nomor urut cetak, mis LMB-2026-07-001
        tanggal        DATE          NOT NULL,        -- tanggal lembur
        department_id  INT           NULL,            -- divisi pengaju
        jenis          NVARCHAR(20)  NOT NULL DEFAULT ''biasa'',  -- biasa/libur/hari_besar
        keterangan     NVARCHAR(500) NULL,            -- uraian pekerjaan umum
        dibuat_oleh    ' + @tipe + N' NULL,           -- atasan/admin divisi (pegawai_id)
        dibuat_nama    NVARCHAR(128) NULL,            -- nama pembuat (untuk cetak)
        dibuat_pada    DATETIME      NOT NULL DEFAULT GETDATE(),
        status         NVARCHAR(30)  NOT NULL DEFAULT ''DRAFT'',
                        -- DRAFT / DIAJUKAN / DISETUJUI_HR / DITOLAK
        hr_id          ' + @tipe + N' NULL,
        hr_pada        DATETIME      NULL,
        hr_catatan     NVARCHAR(500) NULL,
        CONSTRAINT PK_lembur_form PRIMARY KEY (id_form)
    );';
    EXEC sp_executesql @sql;
    PRINT '>> tabel lembur_form dibuat';
END ELSE PRINT '>> lembur_form sudah ada';
GO

/* ---------- DETAIL (baris karyawan) ---------- */
IF OBJECT_ID('dbo.lembur_detail','U') IS NULL
BEGIN
    DECLARE @tipe2 NVARCHAR(20);
    SELECT @tipe2 = UPPER(t.name) FROM sys.columns c JOIN sys.types t ON t.user_type_id=c.user_type_id
    WHERE c.object_id=OBJECT_ID('dbo.pegawai') AND c.name='id_peg';

    DECLARE @sql2 NVARCHAR(MAX) = N'
    CREATE TABLE dbo.lembur_detail (
        id_detail    INT IDENTITY(1,1) NOT NULL,
        id_form      INT           NOT NULL,
        pegawai_id   ' + @tipe2 + N' NOT NULL,
        jam_mulai    TIME          NOT NULL,
        jam_selesai  TIME          NOT NULL,
        durasi_jam   DECIMAL(5,2)  NULL,
        uraian       NVARCHAR(300) NULL,             -- pekerjaan spesifik org ini
        CONSTRAINT PK_lembur_detail PRIMARY KEY (id_detail),
        CONSTRAINT FK_ld_form FOREIGN KEY (id_form)
            REFERENCES dbo.lembur_form(id_form) ON DELETE CASCADE,
        CONSTRAINT FK_ld_pegawai FOREIGN KEY (pegawai_id)
            REFERENCES dbo.pegawai(id_peg)
    );';
    EXEC sp_executesql @sql2;
    PRINT '>> tabel lembur_detail dibuat';
END ELSE PRINT '>> lembur_detail sudah ada';
GO

IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name='IX_ld_form')
    CREATE INDEX IX_ld_form ON dbo.lembur_detail(id_form);
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name='IX_ld_pegawai')
    CREATE INDEX IX_ld_pegawai ON dbo.lembur_detail(pegawai_id);
GO

SELECT 'lembur_form'   AS tabel, CASE WHEN OBJECT_ID('dbo.lembur_form')   IS NULL THEN 'BELUM' ELSE 'OK' END AS status
UNION ALL
SELECT 'lembur_detail', CASE WHEN OBJECT_ID('dbo.lembur_detail') IS NULL THEN 'BELUM' ELSE 'OK' END;
GO
