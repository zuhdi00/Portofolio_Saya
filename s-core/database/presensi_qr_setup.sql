-- =====================================================================
-- Setup Presensi QR Dinamis (anti-kecurangan) — spsdmz2, database hris
-- Jalankan SETELAH presensi_barcode_setup.sql
-- =====================================================================
USE hris;
GO

-- PIN pribadi (hash) + device binding
IF COL_LENGTH('dbo.pegawai_lengkap','pin_hash') IS NULL
    ALTER TABLE dbo.pegawai_lengkap ADD pin_hash NVARCHAR(255) NULL;
IF COL_LENGTH('dbo.pegawai_lengkap','device_id') IS NULL
    ALTER TABLE dbo.pegawai_lengkap ADD device_id NVARCHAR(64) NULL;
GO

-- Log semua percobaan scan (sukses & gagal) untuk audit HR
IF OBJECT_ID('dbo.presensi_log','U') IS NULL
CREATE TABLE dbo.presensi_log (
    id          INT IDENTITY(1,1) PRIMARY KEY,
    waktu       DATETIME     NOT NULL DEFAULT GETDATE(),
    nik         NVARCHAR(20) NULL,
    ip          NVARCHAR(45) NULL,
    device_id   NVARCHAR(64) NULL,
    hasil       NVARCHAR(20) NOT NULL,   -- SUKSES / TOKEN_EXPIRED / PIN_SALAH / IP_LUAR / DEVICE_BEDA / NIK_INVALID
    keterangan  NVARCHAR(255) NULL
);
GO

-- Contoh set PIN awal pegawai = 4 digit terakhir NIK (WAJIB diganti pegawai).
-- Hash dibuat dari PHP (password_hash), jadi jalankan lewat halaman admin,
-- atau sementara set PIN default '1234' untuk uji coba dari PHP.
