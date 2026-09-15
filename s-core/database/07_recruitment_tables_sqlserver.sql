USE [dbHR];
GO

IF OBJECT_ID(N'dbo.departemen', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.departemen (
        id_departemen INT IDENTITY(1,1) NOT NULL PRIMARY KEY,
        nama_departemen NVARCHAR(100) NOT NULL,
        kepala_departemen NVARCHAR(100) NULL,
        lokasi_departemen NVARCHAR(255) NULL
    );
END;
GO

IF OBJECT_ID(N'dbo.lowongan', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.lowongan (
        id_lowongan INT IDENTITY(1,1) NOT NULL PRIMARY KEY,
        nama_lowongan NVARCHAR(128) NOT NULL,
        deskripsi_lowongan NVARCHAR(MAX) NOT NULL,
        id_jabatan BIGINT NOT NULL,
        tgl_posting DATE NOT NULL,
        tgl_tutup DATE NOT NULL,
        status_lowongan NVARCHAR(20) NOT NULL CONSTRAINT DF_lowongan_status DEFAULT (N'Tersedia'),
        CONSTRAINT CK_lowongan_status CHECK (status_lowongan IN (N'Tersedia', N'Tidak Tersedia'))
    );
END;
GO

IF OBJECT_ID(N'dbo.pelamar', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.pelamar (
        id_pelamar INT NOT NULL PRIMARY KEY,
        nama_pel NVARCHAR(128) NOT NULL,
        email_pel NVARCHAR(128) NOT NULL,
        id_lowongan INT NOT NULL,
        status_pel NVARCHAR(20) NOT NULL CONSTRAINT DF_pelamar_status DEFAULT (N'Diterima'),
        jabatan_dipilih NVARCHAR(100) NOT NULL,
        CONSTRAINT CK_pelamar_status CHECK (status_pel IN (N'Diterima', N'Ditolak'))
    );
END;
GO

IF OBJECT_ID(N'dbo.tahap_lamaran', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.tahap_lamaran (
        id_tahap INT IDENTITY(1,1) NOT NULL PRIMARY KEY,
        nama_tahap NVARCHAR(128) NOT NULL,
        deskripsi_tahap NVARCHAR(MAX) NOT NULL
    );
END;
GO

IF OBJECT_ID(N'dbo.penilaian_pelamar', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.penilaian_pelamar (
        id_penilaian_pel INT IDENTITY(1,1) NOT NULL PRIMARY KEY,
        id_pelamar INT NOT NULL,
        id_tahap INT NOT NULL,
        tgl_dinilai DATE NOT NULL,
        skor INT NOT NULL,
        catatan NVARCHAR(MAX) NOT NULL
    );
END;
GO

SELECT TABLE_NAME
FROM INFORMATION_SCHEMA.TABLES
WHERE TABLE_SCHEMA = N'dbo'
  AND TABLE_NAME IN (N'departemen', N'lowongan', N'pelamar', N'tahap_lamaran', N'penilaian_pelamar', N'recruitment_inbox')
ORDER BY TABLE_NAME;
GO
