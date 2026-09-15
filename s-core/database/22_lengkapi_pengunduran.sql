/* =====================================================================
   Lengkapi tabel pengunduran_diri yang sudah ada dengan kolom yang
   dibutuhkan fitur (no_surat, department_id, file PDF, penilaian atasan).
   Menyesuaikan struktur milik user (id_pengunduran, tanggal_pengajuan,
   tanggal_efektif, penilaian_kerja).
   ===================================================================== */
USE dbHR;
GO

/* no surat */
IF COL_LENGTH('dbo.pengunduran_diri','no_surat') IS NULL
    ALTER TABLE dbo.pengunduran_diri ADD no_surat NVARCHAR(40) NULL;
GO
/* nama pegawai (arsip) */
IF COL_LENGTH('dbo.pengunduran_diri','nama_pegawai') IS NULL
    ALTER TABLE dbo.pengunduran_diri ADD nama_pegawai NVARCHAR(128) NULL;
GO
/* divisi */
IF COL_LENGTH('dbo.pengunduran_diri','department_id') IS NULL
    ALTER TABLE dbo.pengunduran_diri ADD department_id INT NULL;
GO
/* berkas PDF (upload HRD) */
IF COL_LENGTH('dbo.pengunduran_diri','file_pdf') IS NULL
    ALTER TABLE dbo.pengunduran_diri ADD file_pdf NVARCHAR(300) NULL;
GO
IF COL_LENGTH('dbo.pengunduran_diri','pdf_oleh') IS NULL
    ALTER TABLE dbo.pengunduran_diri ADD pdf_oleh NVARCHAR(128) NULL;
GO
IF COL_LENGTH('dbo.pengunduran_diri','pdf_pada') IS NULL
    ALTER TABLE dbo.pengunduran_diri ADD pdf_pada DATETIME NULL;
GO
/* penilaian atasan (auto-ACC) */
IF COL_LENGTH('dbo.pengunduran_diri','atasan_nama') IS NULL
    ALTER TABLE dbo.pengunduran_diri ADD atasan_nama NVARCHAR(128) NULL;
GO
IF COL_LENGTH('dbo.pengunduran_diri','atasan_catatan') IS NULL
    ALTER TABLE dbo.pengunduran_diri ADD atasan_catatan NVARCHAR(1000) NULL;
GO
IF COL_LENGTH('dbo.pengunduran_diri','atasan_pada') IS NULL
    ALTER TABLE dbo.pengunduran_diri ADD atasan_pada DATETIME NULL;
GO

/* isi nama_pegawai untuk data lama dari tabel pegawai */
UPDATE r SET r.nama_pegawai = p.nama_peg
FROM dbo.pengunduran_diri r JOIN dbo.pegawai p ON p.id_peg = r.pegawai_id
WHERE r.nama_pegawai IS NULL;
GO

/* isi department_id dari unit_kerja pegawai untuk data lama */
UPDATE r SET r.department_id = u.department_id
FROM dbo.pengunduran_diri r
JOIN dbo.pegawai p ON p.id_peg = r.pegawai_id
LEFT JOIN dbo.unit_kerja u ON u.id = p.unit_kerja_id
WHERE r.department_id IS NULL;
GO

PRINT '>> kolom pengunduran_diri dilengkapi';
SELECT name FROM sys.columns WHERE object_id=OBJECT_ID('dbo.pengunduran_diri') ORDER BY column_id;
GO
