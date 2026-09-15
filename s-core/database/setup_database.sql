-- =====================================================================
-- DATABASE SETUP SCRIPT untuk Data Migrasi Pegawai
-- Run this script di SQL Server Management Studio (SSMS)
-- Database: dbHR (spsdmz2)
-- =====================================================================
-- INSTRUCTIONS:
-- 1. Buka SSMS
-- 2. Connect ke server: spsdmz2
-- 3. Select database: dbHR
-- 4. Buka file ini
-- 5. Klik Execute (F5)
-- 6. Tunggu hingga selesai
-- =====================================================================

USE dbHR;
GO

-- =====================================================================
-- DROP tables jika sudah ada (optional - uncomment jika perlu reset)
-- =====================================================================
-- IF OBJECT_ID('dbo.pegawai_keluarga','U')   IS NOT NULL DROP TABLE dbo.pegawai_keluarga;
-- IF OBJECT_ID('dbo.pegawai_pendidikan','U') IS NOT NULL DROP TABLE dbo.pegawai_pendidikan;
-- IF OBJECT_ID('dbo.pegawai_pengalaman','U') IS NOT NULL DROP TABLE dbo.pegawai_pengalaman;
-- IF OBJECT_ID('dbo.pegawai_lengkap','U')    IS NOT NULL DROP TABLE dbo.pegawai_lengkap;
-- GO

-- =====================================================================
-- CREATE TABLE: dbo.pegawai_lengkap
-- Main employee data table
-- =====================================================================
IF NOT EXISTS (SELECT * FROM sys.objects WHERE object_id = OBJECT_ID(N'[dbo].[pegawai_lengkap]') AND type in (N'U'))
BEGIN
    CREATE TABLE dbo.pegawai_lengkap (
        id                    INT IDENTITY(1,1) NOT NULL,
        -- Header
        company_name          NVARCHAR(50)  NOT NULL DEFAULT N'GRP1',
        nik                   NVARCHAR(20)  NOT NULL,
        -- Organizational Assignment
        enterprise_begin      DATE          NULL,
        enterprise_end        DATE          NULL,
        termination_effective DATETIME      NULL,
        contract_month        INT           NULL,
        term_reason           NVARCHAR(255) NULL,
        personnel_area        NVARCHAR(100) NULL,
        personnel_subarea     NVARCHAR(100) NULL,
        job_title             NVARCHAR(100) NULL,
        unit_kerja            NVARCHAR(255) NULL,
        position_code         NVARCHAR(50)  NULL,
        work_location         NVARCHAR(100) NULL,
        level_code            NVARCHAR(20)  NULL,
        grade_code            NVARCHAR(20)  NULL,
        employee_subgroup     NVARCHAR(100) NULL,
        -- Personal Data
        nama                  NVARCHAR(255) NOT NULL,
        tempat_lahir          NVARCHAR(100) NULL,
        tanggal_lahir         DATE          NULL,
        agama                 NVARCHAR(30)  NULL,
        npwp                  NVARCHAR(30)  NULL,
        no_ktp                NVARCHAR(20)  NULL,
        gender                NVARCHAR(1)   NULL,   -- M / F
        status_kawin          NVARCHAR(20)  NULL,   -- SINGLE / MARRIED / ...
        email                 NVARCHAR(255) NULL,
        ptkp_status           NVARCHAR(100) NULL,
        -- Permanent Address
        almt_tetap            NVARCHAR(500) NULL,
        almt_tetap_rt         NVARCHAR(5)   NULL,
        almt_tetap_rw         NVARCHAR(5)   NULL,
        almt_tetap_desa       NVARCHAR(100) NULL,
        almt_tetap_kecamatan  NVARCHAR(100) NULL,
        almt_tetap_kota       NVARCHAR(100) NULL,
        almt_tetap_provinsi   NVARCHAR(100) NULL,
        almt_tetap_negara     NVARCHAR(100) NULL DEFAULT N'Indonesia',
        almt_tetap_kodepos    NVARCHAR(10)  NULL,
        no_hp                 NVARCHAR(20)  NULL,
        no_telp               NVARCHAR(20)  NULL,
        -- Temporary Address
        almt_smtr             NVARCHAR(500) NULL,
        almt_smtr_rt          NVARCHAR(5)   NULL,
        almt_smtr_rw          NVARCHAR(5)   NULL,
        almt_smtr_desa        NVARCHAR(100) NULL,
        almt_smtr_kecamatan   NVARCHAR(100) NULL,
        almt_smtr_kota        NVARCHAR(100) NULL,
        almt_smtr_provinsi    NVARCHAR(100) NULL,
        almt_smtr_negara      NVARCHAR(100) NULL,
        almt_smtr_kodepos     NVARCHAR(10)  NULL,
        almt_smtr_telp        NVARCHAR(20)  NULL,
        -- Bank
        bank_payee            NVARCHAR(255) NULL,
        bank_kode             NVARCHAR(10)  NULL,
        bank_nama             NVARCHAR(100) NULL,
        bank_detail           NVARCHAR(255) NULL,
        bank_rekening         NVARCHAR(30)  NULL,
        created_at            DATETIME      NOT NULL DEFAULT GETDATE(),
        updated_at            DATETIME      NOT NULL DEFAULT GETDATE(),
        CONSTRAINT PK_pegawai_lengkap PRIMARY KEY (id),
        CONSTRAINT UQ_pegawai_lengkap_nik UNIQUE (nik)
    );
    PRINT 'Table dbo.pegawai_lengkap created successfully.';
END
ELSE
BEGIN
    PRINT 'Table dbo.pegawai_lengkap already exists.';
END
GO

-- =====================================================================
-- CREATE TABLE: dbo.pegawai_keluarga
-- Family member data
-- =====================================================================
IF NOT EXISTS (SELECT * FROM sys.objects WHERE object_id = OBJECT_ID(N'[dbo].[pegawai_keluarga]') AND type in (N'U'))
BEGIN
    CREATE TABLE dbo.pegawai_keluarga (
        id            INT IDENTITY(1,1) NOT NULL,
        pegawai_id    INT NOT NULL,
        nama          NVARCHAR(255) NOT NULL,
        hubungan      NVARCHAR(30)  NULL,   -- SPOUSE / CHILD / FATHER / MOTHER
        gender        NVARCHAR(10)  NULL,   -- MALE / FEMALE
        status_kawin  NVARCHAR(20)  NULL,
        status_hidup  NVARCHAR(10)  NULL,   -- ALIVE / DECEASED
        tempat_lahir  NVARCHAR(100) NULL,
        tanggal_lahir DATE          NULL,
        no_ktp        NVARCHAR(20)  NULL,
        no_kk         NVARCHAR(20)  NULL,
        alamat        NVARCHAR(500) NULL,
        no_bpjs       NVARCHAR(30)  NULL,
        CONSTRAINT PK_pegawai_keluarga PRIMARY KEY (id),
        CONSTRAINT FK_keluarga_pegawai FOREIGN KEY (pegawai_id)
            REFERENCES dbo.pegawai_lengkap(id) ON DELETE CASCADE
    );
    PRINT 'Table dbo.pegawai_keluarga created successfully.';
END
ELSE
BEGIN
    PRINT 'Table dbo.pegawai_keluarga already exists.';
END
GO

-- =====================================================================
-- CREATE TABLE: dbo.pegawai_pendidikan
-- Education history
-- =====================================================================
IF NOT EXISTS (SELECT * FROM sys.objects WHERE object_id = OBJECT_ID(N'[dbo].[pegawai_pendidikan]') AND type in (N'U'))
BEGIN
    CREATE TABLE dbo.pegawai_pendidikan (
        id           INT IDENTITY(1,1) NOT NULL,
        pegawai_id   INT NOT NULL,
        sekolah      NVARCHAR(255) NOT NULL,
        jenjang      NVARCHAR(30)  NULL,    -- SD/SMP/SMA/D3/Strata 1/Strata 2
        jurusan      NVARCHAR(100) NULL,
        lokasi       NVARCHAR(100) NULL,
        tahun_mulai  INT           NULL,
        tahun_selesai INT          NULL,
        ipk          DECIMAL(4,2)  NULL,
        CONSTRAINT PK_pegawai_pendidikan PRIMARY KEY (id),
        CONSTRAINT FK_pendidikan_pegawai FOREIGN KEY (pegawai_id)
            REFERENCES dbo.pegawai_lengkap(id) ON DELETE CASCADE
    );
    PRINT 'Table dbo.pegawai_pendidikan created successfully.';
END
ELSE
BEGIN
    PRINT 'Table dbo.pegawai_pendidikan already exists.';
END
GO

-- =====================================================================
-- CREATE TABLE: dbo.pegawai_pengalaman
-- Work experience history
-- =====================================================================
IF NOT EXISTS (SELECT * FROM sys.objects WHERE object_id = OBJECT_ID(N'[dbo].[pegawai_pengalaman]') AND type in (N'U'))
BEGIN
    CREATE TABLE dbo.pegawai_pengalaman (
        id            INT IDENTITY(1,1) NOT NULL,
        pegawai_id    INT NOT NULL,
        perusahaan    NVARCHAR(255) NOT NULL,
        jabatan       NVARCHAR(100) NULL,
        tanggal_mulai DATE NULL,
        tanggal_akhir DATE NULL,
        keterangan    NVARCHAR(255) NULL,
        CONSTRAINT PK_pegawai_pengalaman PRIMARY KEY (id),
        CONSTRAINT FK_pengalaman_pegawai FOREIGN KEY (pegawai_id)
            REFERENCES dbo.pegawai_lengkap(id) ON DELETE CASCADE
    );
    PRINT 'Table dbo.pegawai_pengalaman created successfully.';
END
ELSE
BEGIN
    PRINT 'Table dbo.pegawai_pengalaman already exists.';
END
GO

-- =====================================================================
-- CREATE INDEXES untuk query performance
-- =====================================================================
IF NOT EXISTS (SELECT * FROM sys.indexes WHERE name = 'IX_pegawai_lengkap_nik')
BEGIN
    CREATE UNIQUE INDEX IX_pegawai_lengkap_nik ON dbo.pegawai_lengkap(nik);
    PRINT 'Index IX_pegawai_lengkap_nik created.';
END
GO

IF NOT EXISTS (SELECT * FROM sys.indexes WHERE name = 'IX_pegawai_lengkap_nama')
BEGIN
    CREATE INDEX IX_pegawai_lengkap_nama ON dbo.pegawai_lengkap(nama);
    PRINT 'Index IX_pegawai_lengkap_nama created.';
END
GO

IF NOT EXISTS (SELECT * FROM sys.indexes WHERE name = 'IX_pegawai_lengkap_updated')
BEGIN
    CREATE INDEX IX_pegawai_lengkap_updated ON dbo.pegawai_lengkap(updated_at);
    PRINT 'Index IX_pegawai_lengkap_updated created.';
END
GO

-- =====================================================================
-- VERIFICATION QUERIES
-- =====================================================================
PRINT '';
PRINT '=== DATABASE SETUP VERIFICATION ===';
PRINT '';

-- Check table existence and record count
SELECT 'pegawai_lengkap' AS TableName, '✓ EXISTS' AS Status, COUNT(*) AS RecordCount FROM dbo.pegawai_lengkap
UNION ALL
SELECT 'pegawai_keluarga', '✓ EXISTS', COUNT(*) FROM dbo.pegawai_keluarga
UNION ALL
SELECT 'pegawai_pendidikan', '✓ EXISTS', COUNT(*) FROM dbo.pegawai_pendidikan
UNION ALL
SELECT 'pegawai_pengalaman', '✓ EXISTS', COUNT(*) FROM dbo.pegawai_pengalaman;

PRINT '';
PRINT '✓ DATABASE SETUP COMPLETE!';
PRINT '';
PRINT 'Next steps:';
PRINT '1. Go to: http://localhost/hris/tools/';
PRINT '2. Click: "Jalankan Analisis" to verify connection';
PRINT '3. Click: "Mulai Migrasi" to start migration';
PRINT '';
