-- =====================================================================
-- Setup Presensi Barcode — jalankan di spsdmz2, database hris
-- =====================================================================
USE hris;
GO

-- 1. Kolom barcode & jadwal di pegawai_lengkap (barcode default = NIK)
IF COL_LENGTH('dbo.pegawai_lengkap','barcode') IS NULL
    ALTER TABLE dbo.pegawai_lengkap ADD barcode NVARCHAR(30) NULL;
IF COL_LENGTH('dbo.pegawai_lengkap','jam_masuk') IS NULL
    ALTER TABLE dbo.pegawai_lengkap ADD jam_masuk TIME(0) NOT NULL DEFAULT '08:00';
IF COL_LENGTH('dbo.pegawai_lengkap','jam_pulang') IS NULL
    ALTER TABLE dbo.pegawai_lengkap ADD jam_pulang TIME(0) NOT NULL DEFAULT '17:00';
GO
UPDATE dbo.pegawai_lengkap SET barcode = nik WHERE barcode IS NULL;
GO
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name='UQ_pegawai_barcode')
    CREATE UNIQUE INDEX UQ_pegawai_barcode ON dbo.pegawai_lengkap(barcode) WHERE barcode IS NOT NULL;
GO

-- 2. Perluas tabel absensi lama agar mendukung masuk & pulang per hari
--    (struktur asli: ID_Absensi, ID_Pegawai, Tanggal_Waktu, Status_Kehadiran, Metode_Verifikasi, Lokasi_IP)
IF COL_LENGTH('dbo.absensi','Jam_Pulang') IS NULL
    ALTER TABLE dbo.absensi ADD Jam_Pulang DATETIME NULL;
IF COL_LENGTH('dbo.absensi','Tanggal') IS NULL
    ALTER TABLE dbo.absensi ADD Tanggal AS CAST(Tanggal_Waktu AS DATE) PERSISTED;
GO
-- ID_Absensi lama bukan IDENTITY; buat sequence agar insert otomatis
IF OBJECT_ID('dbo.seq_absensi') IS NULL
    CREATE SEQUENCE dbo.seq_absensi AS INT
    START WITH 1 INCREMENT BY 1;
GO
-- Satu baris per pegawai per hari
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name='UQ_absensi_harian')
    CREATE UNIQUE INDEX UQ_absensi_harian ON dbo.absensi(ID_Pegawai, Tanggal);
GO
