/* =====================================================================
   PERBAIKAN skrip 04 - tipe data pegawai_id disamakan otomatis
   Aman dijalankan berulang kali (semua bagian pakai pengecekan IF NOT EXISTS).
   ===================================================================== */
USE dbHR;
GO

/* ---------- 0. Cek tipe data id_peg yang sebenarnya ---------- */
DECLARE @tipe NVARCHAR(100);

SELECT @tipe = UPPER(t.name)
FROM sys.columns c
JOIN sys.types   t ON t.user_type_id = c.user_type_id
WHERE c.object_id = OBJECT_ID('dbo.pegawai') AND c.name = 'id_peg';

PRINT '>> Tipe data dbo.pegawai.id_peg = ' + ISNULL(@tipe, '(TIDAK DITEMUKAN)');

IF @tipe IS NULL
BEGIN
    RAISERROR('Kolom id_peg tidak ditemukan di dbo.pegawai. Periksa nama tabel/kolom.', 16, 1);
    RETURN;
END


/* ---------- 1. Buat absensi_koreksi dengan tipe yang cocok ---------- */
IF OBJECT_ID('dbo.absensi_koreksi', 'U') IS NULL
BEGIN
    DECLARE @sql NVARCHAR(MAX) = N'
    CREATE TABLE dbo.absensi_koreksi (
        id_koreksi        INT IDENTITY(1,1) NOT NULL,
        pegawai_id        ' + @tipe + N' NOT NULL,
        tanggal           DATE          NOT NULL,
        jenis             NVARCHAR(30)  NOT NULL,
        jam_masuk_asli    TIME          NULL,
        jam_keluar_asli   TIME          NULL,
        jam_masuk_usulan  TIME          NULL,
        jam_keluar_usulan TIME          NULL,
        status_approval   NVARCHAR(20)  NOT NULL DEFAULT ''PENDING'',
        diajukan_pada     DATETIME      NOT NULL DEFAULT GETDATE(),
        approver_id       ' + @tipe + N' NULL,
        approved_pada     DATETIME      NULL,
        catatan           NVARCHAR(500) NULL,
        CONSTRAINT PK_absensi_koreksi PRIMARY KEY (id_koreksi),
        CONSTRAINT FK_koreksi_pegawai FOREIGN KEY (pegawai_id)
            REFERENCES dbo.pegawai(id_peg)
    );';
    EXEC sp_executesql @sql;
    PRINT '>> Tabel dbo.absensi_koreksi dibuat (pegawai_id bertipe ' + @tipe + ').';
END
ELSE
    PRINT '>> Tabel dbo.absensi_koreksi sudah ada, dilewati.';
GO


/* ---------- 2. Index antrian approval ---------- */
IF NOT EXISTS (SELECT 1 FROM sys.indexes
               WHERE name = 'IX_koreksi_pending' AND object_id = OBJECT_ID('dbo.absensi_koreksi'))
BEGIN
    CREATE INDEX IX_koreksi_pending ON dbo.absensi_koreksi(status_approval, tanggal);
    PRINT '>> Index IX_koreksi_pending dibuat.';
END
GO


/* ---------- 3. Pastikan kolom tambahan di dbo.absensi sudah ada ---------- */
IF COL_LENGTH('dbo.absensi', 'metode') IS NULL
BEGIN
    ALTER TABLE dbo.absensi ADD
        metode        NVARCHAR(30) NULL,
        sn_mesin      NVARCHAR(20) NULL,
        shift_ke      TINYINT      NULL,
        jml_tap       TINYINT      NULL,
        perlu_koreksi BIT          NOT NULL DEFAULT 0,
        sumber        NVARCHAR(20) NULL;
    PRINT '>> Kolom tambahan di dbo.absensi dibuat.';
END
ELSE
    PRINT '>> Kolom tambahan di dbo.absensi sudah ada, dilewati.';
GO


/* ---------- 4. Index unik: 1 pegawai 1 tanggal ---------- */
IF NOT EXISTS (SELECT 1 FROM sys.indexes
               WHERE name = 'UX_absensi_peg_tgl' AND object_id = OBJECT_ID('dbo.absensi'))
BEGIN
    -- kalau gagal karena sudah ada data ganda, jalankan dulu query pengecekan di bawah
    CREATE UNIQUE INDEX UX_absensi_peg_tgl ON dbo.absensi(pegawai_id, tanggal);
    PRINT '>> Index UX_absensi_peg_tgl dibuat.';
END
GO


/* ---------- 5. VERIFIKASI: pastikan semua objek sudah terbentuk ---------- */
SELECT 'zkteco_checkinout'   AS objek,
       CASE WHEN OBJECT_ID('dbo.zkteco_checkinout','U')   IS NULL THEN 'BELUM ADA' ELSE 'OK' END AS status
UNION ALL SELECT 'sync_zkteco_state',
       CASE WHEN OBJECT_ID('dbo.sync_zkteco_state','U')   IS NULL THEN 'BELUM ADA' ELSE 'OK' END
UNION ALL SELECT 'absensi_koreksi',
       CASE WHEN OBJECT_ID('dbo.absensi_koreksi','U')     IS NULL THEN 'BELUM ADA' ELSE 'OK' END
UNION ALL SELECT 'pegawai.nik',
       CASE WHEN COL_LENGTH('dbo.pegawai','nik')          IS NULL THEN 'BELUM ADA' ELSE 'OK' END
UNION ALL SELECT 'pegawai.zkteco_userid',
       CASE WHEN COL_LENGTH('dbo.pegawai','zkteco_userid')IS NULL THEN 'BELUM ADA' ELSE 'OK' END
UNION ALL SELECT 'absensi.metode',
       CASE WHEN COL_LENGTH('dbo.absensi','metode')       IS NULL THEN 'BELUM ADA' ELSE 'OK' END;
GO

-- Baris sync_zkteco_state harus ada tepat 1 untuk sumber 'ATT2000'
IF NOT EXISTS (SELECT 1 FROM dbo.sync_zkteco_state WHERE sumber = 'ATT2000')
    INSERT INTO dbo.sync_zkteco_state (sumber, last_checktime) VALUES ('ATT2000', '2000-01-01');

SELECT * FROM dbo.sync_zkteco_state;
GO


/* ---------- CATATAN: kalau index unik absensi GAGAL ----------
   Berarti sudah ada data ganda. Cari dulu dengan:

   SELECT pegawai_id, tanggal, COUNT(*) AS jml
   FROM dbo.absensi
   GROUP BY pegawai_id, tanggal
   HAVING COUNT(*) > 1
   ORDER BY jml DESC;

   Bersihkan duplikatnya (sisakan yang paling lengkap), baru buat index.
------------------------------------------------------------- */
