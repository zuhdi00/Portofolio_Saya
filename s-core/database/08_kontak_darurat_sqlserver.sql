USE [dbHR];
GO

IF OBJECT_ID(N'dbo.kontak_darurat', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.kontak_darurat (
        id_kontak INT IDENTITY(1,1) NOT NULL,
        pegawai_id BIGINT NOT NULL,
        nama NVARCHAR(150) NULL,
        hubungan NVARCHAR(80) NULL,
        no_hp NVARCHAR(50) NULL,
        alamat NVARCHAR(500) NULL,
        created_at DATETIME2(0) NOT NULL CONSTRAINT DF_kontak_darurat_created_at DEFAULT (SYSDATETIME()),
        updated_at DATETIME2(0) NOT NULL CONSTRAINT DF_kontak_darurat_updated_at DEFAULT (SYSDATETIME()),
        CONSTRAINT PK_kontak_darurat PRIMARY KEY (id_kontak),
        CONSTRAINT UQ_kontak_darurat_pegawai UNIQUE (pegawai_id)
    );
END;
GO

SELECT TABLE_SCHEMA, TABLE_NAME
FROM INFORMATION_SCHEMA.TABLES
WHERE TABLE_SCHEMA = N'dbo' AND TABLE_NAME = N'kontak_darurat';
GO
