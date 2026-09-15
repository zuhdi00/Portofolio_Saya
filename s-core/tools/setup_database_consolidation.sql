-- ============================================================
-- SCRIPT: Setup Field Baru untuk Data Consolidation
-- Database: dbHR (SQL Server)
-- Purpose: Create unified tracking untuk multi-source employee IDs
-- ============================================================

-- ============================================================
-- PART 1: ALTER TABLE - Add new columns ke pegawai_lengkap
-- ============================================================
USE [dbHR]
GO

-- Check if columns already exist before adding
IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME='pegawai_lengkap' AND COLUMN_NAME='person_unified_id')
BEGIN
    ALTER TABLE dbo.pegawai_lengkap ADD 
        person_unified_id NVARCHAR(50) NULL;
    PRINT 'Added: person_unified_id'
END

IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME='pegawai_lengkap' AND COLUMN_NAME='id_source')
BEGIN
    ALTER TABLE dbo.pegawai_lengkap ADD 
        id_source NVARCHAR(20) NULL;
    PRINT 'Added: id_source'
END

IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME='pegawai_lengkap' AND COLUMN_NAME='nik_source')
BEGIN
    ALTER TABLE dbo.pegawai_lengkap ADD 
        nik_source NVARCHAR(20) NULL;
    PRINT 'Added: nik_source'
END

IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME='pegawai_lengkap' AND COLUMN_NAME='is_primary')
BEGIN
    ALTER TABLE dbo.pegawai_lengkap ADD 
        is_primary BIT DEFAULT 0;
    PRINT 'Added: is_primary'
END

IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME='pegawai_lengkap' AND COLUMN_NAME='consolidation_notes')
BEGIN
    ALTER TABLE dbo.pegawai_lengkap ADD 
        consolidation_notes NVARCHAR(MAX) NULL;
    PRINT 'Added: consolidation_notes'
END

IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME='pegawai_lengkap' AND COLUMN_NAME='id_peg_excel')
BEGIN
    ALTER TABLE dbo.pegawai_lengkap ADD 
        id_peg_excel NVARCHAR(50) NULL;
    PRINT 'Added: id_peg_excel'
END

IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME='pegawai_lengkap' AND COLUMN_NAME='nik_excel')
BEGIN
    ALTER TABLE dbo.pegawai_lengkap ADD 
        nik_excel NVARCHAR(50) NULL;
    PRINT 'Added: nik_excel'
END

-- ============================================================
-- PART 2: Create Mapping Table
-- ============================================================
IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_NAME='pegawai_id_mapping')
BEGIN
    CREATE TABLE dbo.pegawai_id_mapping (
        mapping_id INT IDENTITY(1,1) PRIMARY KEY,
        person_unified_id NVARCHAR(50) NOT NULL,
        id_peg NVARCHAR(50) NOT NULL,
        source NVARCHAR(20) NOT NULL,
        is_primary BIT DEFAULT 0,
        created_at DATETIME DEFAULT GETDATE(),
        updated_at DATETIME DEFAULT GETDATE(),
        notes NVARCHAR(MAX) NULL,
        CONSTRAINT UK_mapping UNIQUE(person_unified_id, id_peg, source),
        CONSTRAINT FK_mapping_pegawai FOREIGN KEY (id_peg) REFERENCES dbo.pegawai_lengkap(nik)
    );
    
    CREATE INDEX IDX_mapping_unified_id ON dbo.pegawai_id_mapping(person_unified_id);
    CREATE INDEX IDX_mapping_id_peg ON dbo.pegawai_id_mapping(id_peg);
    CREATE INDEX IDX_mapping_source ON dbo.pegawai_id_mapping(source);
    
    PRINT 'Created: pegawai_id_mapping table'
END

-- ============================================================
-- PART 3: Create Tracking Log Table
-- ============================================================
IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_NAME='pegawai_consolidation_log')
BEGIN
    CREATE TABLE dbo.pegawai_consolidation_log (
        log_id INT IDENTITY(1,1) PRIMARY KEY,
        person_unified_id NVARCHAR(50) NOT NULL,
        affected_ids NVARCHAR(500) NOT NULL,
        action NVARCHAR(50) NOT NULL,
        status NVARCHAR(20) NOT NULL,
        details NVARCHAR(MAX) NULL,
        created_by NVARCHAR(100) NULL,
        created_at DATETIME DEFAULT GETDATE(),
        INDEX IDX_log_unified ON pegawai_consolidation_log(person_unified_id)
    );
    
    PRINT 'Created: pegawai_consolidation_log table'
END

-- ============================================================
-- PART 4: Sample Data - Initialize Existing Duplicates
-- ============================================================
-- NOTE: Uncomment dan jalankan setelah verifikasi manual dari duplikat

/*
-- SUDIONO Group
INSERT INTO dbo.pegawai_id_mapping (person_unified_id, id_peg, source, is_primary, notes)
SELECT 'PERSON_SUDIONO_001', '2000601', 'SQL', 1, 'Primary record - Original from SQL'
WHERE NOT EXISTS (SELECT 1 FROM dbo.pegawai_id_mapping WHERE id_peg='2000601' AND source='SQL');

INSERT INTO dbo.pegawai_id_mapping (person_unified_id, id_peg, source, is_primary, notes)
SELECT 'PERSON_SUDIONO_001', '2020624', 'EXCEL', 0, 'Duplicate - From Excel migration'
WHERE NOT EXISTS (SELECT 1 FROM dbo.pegawai_id_mapping WHERE id_peg='2020624' AND source='EXCEL');

-- KHOIRUL ANAM Group
INSERT INTO dbo.pegawai_id_mapping (person_unified_id, id_peg, source, is_primary, notes)
SELECT 'PERSON_KHOIRUL_ANAM_001', '2060658', 'SQL', 1, 'Primary record - Original from SQL'
WHERE NOT EXISTS (SELECT 1 FROM dbo.pegawai_id_mapping WHERE id_peg='2060658' AND source='SQL');

INSERT INTO dbo.pegawai_id_mapping (person_unified_id, id_peg, source, is_primary, notes)
SELECT 'PERSON_KHOIRUL_ANAM_001', '42601327', 'EXCEL', 0, 'Duplicate - From Excel migration'
WHERE NOT EXISTS (SELECT 1 FROM dbo.pegawai_id_mapping WHERE id_peg='42601327' AND source='EXCEL');

-- RONI Group
INSERT INTO dbo.pegawai_id_mapping (person_unified_id, id_peg, source, is_primary, notes)
SELECT 'PERSON_RONI_001', '41709164', 'SQL', 1, 'Primary record - Original from SQL'
WHERE NOT EXISTS (SELECT 1 FROM dbo.pegawai_id_mapping WHERE id_peg='41709164' AND source='SQL');

INSERT INTO dbo.pegawai_id_mapping (person_unified_id, id_peg, source, is_primary, notes)
SELECT 'PERSON_RONI_001', '40001380', 'EXCEL', 0, 'Duplicate - From Excel migration'
WHERE NOT EXISTS (SELECT 1 FROM dbo.pegawai_id_mapping WHERE id_peg='40001380' AND source='EXCEL');

-- DIAN SANTOSO Group (CRITICAL: sama NIK!)
INSERT INTO dbo.pegawai_id_mapping (person_unified_id, id_peg, source, is_primary, notes)
SELECT 'PERSON_DIAN_SANTOSO_001', '42607513', 'SQL', 1, 'Primary record - Original from SQL'
WHERE NOT EXISTS (SELECT 1 FROM dbo.pegawai_id_mapping WHERE id_peg='42607513' AND source='SQL');

INSERT INTO dbo.pegawai_id_mapping (person_unified_id, id_peg, source, is_primary, notes)
SELECT 'PERSON_DIAN_SANTOSO_001', '42607516', 'SQL', 0, 'Duplicate - Same NIK! Needs investigation'
WHERE NOT EXISTS (SELECT 1 FROM dbo.pegawai_id_mapping WHERE id_peg='42607516' AND source='SQL');
*/

-- ============================================================
-- PART 5: Verification Queries
-- ============================================================
PRINT ''
PRINT '===== VERIFICATION QUERIES ====='
PRINT ''

-- Show column additions
SELECT TABLE_NAME, COLUMN_NAME, DATA_TYPE, IS_NULLABLE
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_NAME IN ('pegawai_lengkap', 'pegawai_id_mapping')
ORDER BY TABLE_NAME, ORDINAL_POSITION;

-- Show table structure
EXEC sp_help 'dbo.pegawai_id_mapping';

-- ============================================================
-- DONE
-- ============================================================
PRINT ''
PRINT '✅ Schema setup completed successfully!'
PRINT 'New columns added to pegawai_lengkap'
PRINT 'New tables created: pegawai_id_mapping, pegawai_consolidation_log'
PRINT ''
PRINT 'Next steps:'
PRINT '1. Run analyze_sql_duplicates_detailed.php to verify duplicates'
PRINT '2. Manually review each duplicate group'
PRINT '3. Execute mapping initialization (uncomment PART 4 after verification)'
PRINT '4. Run consolidation script to populate person_unified_id'
