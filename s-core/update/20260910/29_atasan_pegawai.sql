/* =====================================================================
   Tambah kolom ATASAN di tabel pegawai (self-reference).
   pegawai.atasan_id -> pegawai.id_peg
   ===================================================================== */
USE dbHR;
GO

/* tipe id_peg terdeteksi otomatis (BIGINT di dbHR) */
IF COL_LENGTH('dbo.pegawai','atasan_id') IS NULL
BEGIN
    DECLARE @tipe NVARCHAR(20);
    SELECT @tipe = UPPER(t.name)
    FROM sys.columns c JOIN sys.types t ON t.user_type_id = c.user_type_id
    WHERE c.object_id = OBJECT_ID('dbo.pegawai') AND c.name = 'id_peg';

    DECLARE @sql NVARCHAR(MAX) = N'ALTER TABLE dbo.pegawai ADD atasan_id ' + @tipe + N' NULL;';
    EXEC sp_executesql @sql;
    PRINT '>> kolom atasan_id dibuat';
END ELSE PRINT '>> atasan_id sudah ada';
GO

/* foreign key ke pegawai sendiri (NO ACTION supaya tidak cascade) */
IF NOT EXISTS (SELECT 1 FROM sys.foreign_keys WHERE name = 'FK_pegawai_atasan')
BEGIN
    ALTER TABLE dbo.pegawai WITH NOCHECK
        ADD CONSTRAINT FK_pegawai_atasan FOREIGN KEY (atasan_id)
        REFERENCES dbo.pegawai(id_peg);
    PRINT '>> FK_pegawai_atasan dibuat';
END ELSE PRINT '>> FK sudah ada';
GO

IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'IX_pegawai_atasan')
    CREATE INDEX IX_pegawai_atasan ON dbo.pegawai(atasan_id);
GO

/* ---------- cek hasil ---------- */
SELECT COUNT(*) AS total_pegawai,
       SUM(CASE WHEN atasan_id IS NOT NULL THEN 1 ELSE 0 END) AS sudah_ada_atasan,
       SUM(CASE WHEN atasan_id IS NULL     THEN 1 ELSE 0 END) AS belum_ada_atasan
FROM dbo.pegawai WHERE is_aktif = 1;
GO
