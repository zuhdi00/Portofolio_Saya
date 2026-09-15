/* =====================================================================
   Melengkapi tabel surat_peringatan YANG SUDAH ADA dengan kolom
   yang dibutuhkan modul SP. Tidak menghapus kolom/data lama.
   ===================================================================== */
USE dbHR;
GO

/* ---------- 1. LIHAT STRUKTUR SAAT INI (kirim hasilnya ke saya) ---------- */
SELECT c.column_id, c.name AS kolom, t.name AS tipe, c.max_length, c.is_nullable
FROM sys.columns c
JOIN sys.types t ON t.user_type_id = c.user_type_id
WHERE c.object_id = OBJECT_ID('dbo.surat_peringatan')
ORDER BY c.column_id;
GO

/* ---------- 2. Tambah kolom yang belum ada ---------- */
IF COL_LENGTH('dbo.surat_peringatan','no_sp') IS NULL
    ALTER TABLE dbo.surat_peringatan ADD no_sp NVARCHAR(50) NULL;
GO
IF COL_LENGTH('dbo.surat_peringatan','nama_pegawai') IS NULL
    ALTER TABLE dbo.surat_peringatan ADD nama_pegawai NVARCHAR(128) NULL;
GO
IF COL_LENGTH('dbo.surat_peringatan','department_id') IS NULL
    ALTER TABLE dbo.surat_peringatan ADD department_id INT NULL;
GO
IF COL_LENGTH('dbo.surat_peringatan','jenis') IS NULL
    ALTER TABLE dbo.surat_peringatan ADD jenis NVARCHAR(20) NULL;
GO
IF COL_LENGTH('dbo.surat_peringatan','kategori') IS NULL
    ALTER TABLE dbo.surat_peringatan ADD kategori NVARCHAR(50) NULL;
GO
IF COL_LENGTH('dbo.surat_peringatan','tanggal_terbit') IS NULL
    ALTER TABLE dbo.surat_peringatan ADD tanggal_terbit DATE NULL;
GO
IF COL_LENGTH('dbo.surat_peringatan','berlaku_sampai') IS NULL
    ALTER TABLE dbo.surat_peringatan ADD berlaku_sampai DATE NULL;
GO
IF COL_LENGTH('dbo.surat_peringatan','alasan') IS NULL
    ALTER TABLE dbo.surat_peringatan ADD alasan NVARCHAR(500) NULL;
GO
IF COL_LENGTH('dbo.surat_peringatan','keterangan') IS NULL
    ALTER TABLE dbo.surat_peringatan ADD keterangan NVARCHAR(1000) NULL;
GO
IF COL_LENGTH('dbo.surat_peringatan','file_sp') IS NULL
    ALTER TABLE dbo.surat_peringatan ADD file_sp NVARCHAR(300) NULL;
GO
IF COL_LENGTH('dbo.surat_peringatan','status') IS NULL
    ALTER TABLE dbo.surat_peringatan ADD status NVARCHAR(20) NULL;
GO
IF COL_LENGTH('dbo.surat_peringatan','dibuat_oleh') IS NULL
    ALTER TABLE dbo.surat_peringatan ADD dibuat_oleh NVARCHAR(128) NULL;
GO
IF COL_LENGTH('dbo.surat_peringatan','dibuat_pada') IS NULL
    ALTER TABLE dbo.surat_peringatan ADD dibuat_pada DATETIME NULL;
GO
IF COL_LENGTH('dbo.surat_peringatan','pegawai_id') IS NULL
BEGIN
    DECLARE @tipe NVARCHAR(20);
    SELECT @tipe = UPPER(t.name) FROM sys.columns c JOIN sys.types t ON t.user_type_id=c.user_type_id
    WHERE c.object_id = OBJECT_ID('dbo.pegawai') AND c.name = 'id_peg';
    DECLARE @sql NVARCHAR(MAX) = N'ALTER TABLE dbo.surat_peringatan ADD pegawai_id ' + @tipe + N' NULL;';
    EXEC sp_executesql @sql;
END
GO

PRINT '>> kolom dilengkapi';
GO

/* ---------- 3. Isi nilai default untuk baris lama ---------- */
UPDATE dbo.surat_peringatan SET status = 'AKTIF'      WHERE status IS NULL;
UPDATE dbo.surat_peringatan SET dibuat_pada = GETDATE() WHERE dibuat_pada IS NULL;
GO

/* ---------- 4. Indeks (dibuat setelah kolom dipastikan ada) ---------- */
IF COL_LENGTH('dbo.surat_peringatan','pegawai_id') IS NOT NULL
   AND COL_LENGTH('dbo.surat_peringatan','tanggal_terbit') IS NOT NULL
   AND NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name='IX_sp_pegawai')
    CREATE INDEX IX_sp_pegawai ON dbo.surat_peringatan(pegawai_id, tanggal_terbit DESC);
GO
IF COL_LENGTH('dbo.surat_peringatan','status') IS NOT NULL
   AND COL_LENGTH('dbo.surat_peringatan','tanggal_terbit') IS NOT NULL
   AND NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name='IX_sp_status')
    CREATE INDEX IX_sp_status ON dbo.surat_peringatan(status, tanggal_terbit DESC);
GO

/* ---------- 5. Struktur akhir ---------- */
SELECT c.name AS kolom, t.name AS tipe
FROM sys.columns c JOIN sys.types t ON t.user_type_id = c.user_type_id
WHERE c.object_id = OBJECT_ID('dbo.surat_peringatan')
ORDER BY c.column_id;
GO
