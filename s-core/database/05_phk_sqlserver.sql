USE [hris];
GO

IF OBJECT_ID(N'dbo.phk', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.phk (
        id_phk INT IDENTITY(1,1) NOT NULL,
        id_karyawan INT NOT NULL,
        tanggal_phk DATE NOT NULL,
        alasan_phk NVARCHAR(MAX) NOT NULL,
        status_kompensasi NVARCHAR(20) NOT NULL,
        jumlah_kompensasi DECIMAL(10,0) NOT NULL CONSTRAINT DF_phk_jumlah_kompensasi DEFAULT (0),
        CONSTRAINT PK_phk PRIMARY KEY (id_phk),
        CONSTRAINT CK_phk_status_kompensasi CHECK (status_kompensasi IN (N'Diberikan', N'Tidak Diberikan'))
    );
END;
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.indexes
    WHERE name = N'IX_phk_id_karyawan'
      AND object_id = OBJECT_ID(N'dbo.phk')
)
BEGIN
    CREATE INDEX IX_phk_id_karyawan ON dbo.phk (id_karyawan);
END;
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.indexes
    WHERE name = N'IX_phk_tanggal_phk'
      AND object_id = OBJECT_ID(N'dbo.phk')
)
BEGIN
    CREATE INDEX IX_phk_tanggal_phk ON dbo.phk (tanggal_phk);
END;
GO

SELECT TABLE_SCHEMA, TABLE_NAME
FROM INFORMATION_SCHEMA.TABLES
WHERE TABLE_SCHEMA = N'dbo' AND TABLE_NAME = N'phk';
GO
