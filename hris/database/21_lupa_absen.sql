/* =====================================================================
   FORM LUPA ABSEN (koreksi manual diajukan atasan/admin divisi).
   Header-detail seperti lembur: 1 form bisa banyak karyawan.
   Alur: DIAJUKAN -> (HRD cek) -> DISETUJUI (tulis balik ke absensi) / DITOLAK
   ===================================================================== */
USE dbHR;
GO

/* ---------- HEADER ---------- */
IF OBJECT_ID('dbo.lupa_absen_form','U') IS NULL
BEGIN
    DECLARE @tipe NVARCHAR(20);
    SELECT @tipe = UPPER(t.name) FROM sys.columns c JOIN sys.types t ON t.user_type_id=c.user_type_id
    WHERE c.object_id=OBJECT_ID('dbo.pegawai') AND c.name='id_peg';

    DECLARE @sql NVARCHAR(MAX) = N'
    CREATE TABLE dbo.lupa_absen_form (
        id_form       INT IDENTITY(1,1) NOT NULL,
        no_form       NVARCHAR(40)  NULL,            -- LAB-YYYYMM-NNN
        department_id INT           NULL,
        keterangan    NVARCHAR(500) NULL,            -- alasan umum
        file_bukti    NVARCHAR(300) NULL,            -- nama file gambar di \\spsdmz\gg$\HRD\BuktiLupaAbsensi
        dibuat_oleh   NVARCHAR(128) NULL,
        atasan_nama   NVARCHAR(128) NULL,
        dibuat_pada   DATETIME      NOT NULL DEFAULT GETDATE(),
        status        NVARCHAR(20)  NOT NULL DEFAULT ''DIAJUKAN'',
                       -- DIAJUKAN / DISETUJUI / DITOLAK
        hr_nama       NVARCHAR(128) NULL,
        hr_pada       DATETIME      NULL,
        hr_catatan    NVARCHAR(500) NULL,
        CONSTRAINT PK_lupa_absen_form PRIMARY KEY (id_form)
    );';
    EXEC sp_executesql @sql;
    PRINT '>> lupa_absen_form dibuat';
END ELSE PRINT '>> lupa_absen_form sudah ada';
GO

/* ---------- DETAIL (baris karyawan) ---------- */
IF OBJECT_ID('dbo.lupa_absen_detail','U') IS NULL
BEGIN
    DECLARE @tipe2 NVARCHAR(20);
    SELECT @tipe2 = UPPER(t.name) FROM sys.columns c JOIN sys.types t ON t.user_type_id=c.user_type_id
    WHERE c.object_id=OBJECT_ID('dbo.pegawai') AND c.name='id_peg';

    DECLARE @sql2 NVARCHAR(MAX) = N'
    CREATE TABLE dbo.lupa_absen_detail (
        id_detail    INT IDENTITY(1,1) NOT NULL,
        id_form      INT           NOT NULL,
        pegawai_id   ' + @tipe2 + N' NOT NULL,
        tanggal      DATE          NOT NULL,
        jenis        NVARCHAR(20)  NOT NULL,          -- MASUK / PULANG / KEDUANYA
        jam_masuk    TIME          NULL,              -- jam masuk yang benar (kalau lupa masuk)
        jam_keluar   TIME          NULL,              -- jam pulang yang benar (kalau lupa pulang)
        alasan       NVARCHAR(300) NULL,
        CONSTRAINT PK_lupa_absen_detail PRIMARY KEY (id_detail),
        CONSTRAINT FK_lad_form FOREIGN KEY (id_form)
            REFERENCES dbo.lupa_absen_form(id_form) ON DELETE CASCADE,
        CONSTRAINT FK_lad_pegawai FOREIGN KEY (pegawai_id)
            REFERENCES dbo.pegawai(id_peg)
    );';
    EXEC sp_executesql @sql2;
    PRINT '>> lupa_absen_detail dibuat';
END ELSE PRINT '>> lupa_absen_detail sudah ada';
GO

IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name='IX_lad_form')
    CREATE INDEX IX_lad_form ON dbo.lupa_absen_detail(id_form);
GO

SELECT 'lupa_absen_form' AS tabel, CASE WHEN OBJECT_ID('dbo.lupa_absen_form') IS NULL THEN 'BELUM' ELSE 'OK' END AS status
UNION ALL
SELECT 'lupa_absen_detail', CASE WHEN OBJECT_ID('dbo.lupa_absen_detail') IS NULL THEN 'BELUM' ELSE 'OK' END;
GO
