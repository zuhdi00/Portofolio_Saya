USE [dbHR];
GO

SET ANSI_NULLS ON;
SET QUOTED_IDENTIFIER ON;
GO

IF OBJECT_ID(N'dbo.zkteco_user_transfer', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.zkteco_user_transfer (
        id_transfer BIGINT IDENTITY(1,1) NOT NULL,
        source_userid INT NOT NULL,
        source_badgenumber NVARCHAR(50) NULL,
        target_userid INT NULL,
        target_badgenumber NVARCHAR(50) NULL,
        source_pegawai_id BIGINT NULL,
        target_pegawai_id BIGINT NULL,
        alasan NVARCHAR(500) NULL,
        dibuat_oleh NVARCHAR(150) NULL,
        dibuat_pada DATETIME2(0) NOT NULL CONSTRAINT DF_zkteco_transfer_dibuat_pada DEFAULT (SYSDATETIME()),
        is_aktif BIT NOT NULL CONSTRAINT DF_zkteco_transfer_is_aktif DEFAULT (1),
        CONSTRAINT PK_zkteco_user_transfer PRIMARY KEY (id_transfer),
        CONSTRAINT CK_zkteco_transfer_userid CHECK (source_userid <> target_userid)
    );
END;
GO

IF COL_LENGTH(N'dbo.zkteco_user_transfer', N'source_pegawai_id') IS NULL
    ALTER TABLE dbo.zkteco_user_transfer ADD source_pegawai_id BIGINT NULL;
IF COL_LENGTH(N'dbo.zkteco_user_transfer', N'target_pegawai_id') IS NULL
    ALTER TABLE dbo.zkteco_user_transfer ADD target_pegawai_id BIGINT NULL;
ALTER TABLE dbo.zkteco_user_transfer ALTER COLUMN target_userid INT NULL;
GO

IF NOT EXISTS (
    SELECT 1 FROM sys.indexes
    WHERE name = N'UQ_zkteco_transfer_source_aktif'
      AND object_id = OBJECT_ID(N'dbo.zkteco_user_transfer')
)
BEGIN
    CREATE UNIQUE INDEX UQ_zkteco_transfer_source_aktif
        ON dbo.zkteco_user_transfer(source_userid)
        WHERE is_aktif = 1;
END;
GO

CREATE OR ALTER PROCEDURE dbo.usp_transfer_zkteco_user
    @source_userid INT,
    @target_pegawai_id BIGINT,
    @alasan NVARCHAR(500) = NULL,
    @dibuat_oleh NVARCHAR(150) = NULL
AS
BEGIN
    SET NOCOUNT ON;
    SET XACT_ABORT ON;

    DECLARE @source_pegawai_id BIGINT;
    DECLARE @target_userid INT;
    DECLARE @source_badgenumber NVARCHAR(50);
    DECLARE @target_badgenumber NVARCHAR(50);

    IF @target_pegawai_id IS NULL
        THROW 50001, 'ID pegawai tujuan wajib diisi.', 1;

    SELECT @source_pegawai_id = id_peg, @source_badgenumber = zkteco_acno
    FROM dbo.pegawai
    WHERE zkteco_userid = @source_userid;

    SELECT @target_userid = zkteco_userid, @target_badgenumber = zkteco_acno
    FROM dbo.pegawai
    WHERE id_peg = @target_pegawai_id;

    IF @source_pegawai_id IS NULL OR @target_userid = @source_userid
        THROW 50002, 'UserID sumber atau ID pegawai tujuan tidak valid.', 1;

    BEGIN TRANSACTION;

    UPDATE dbo.zkteco_user_transfer
    SET is_aktif = 0
    WHERE source_userid = @source_userid AND is_aktif = 1;

    INSERT INTO dbo.zkteco_user_transfer
        (source_userid, source_badgenumber, target_userid, target_badgenumber, source_pegawai_id, target_pegawai_id, alasan, dibuat_oleh)
    VALUES
        (@source_userid, @source_badgenumber, @target_userid, @target_badgenumber, @source_pegawai_id, @target_pegawai_id, @alasan, @dibuat_oleh);

    COMMIT TRANSACTION;

    SELECT
        SCOPE_IDENTITY() AS id_transfer,
        @source_userid AS source_userid,
        @source_badgenumber AS source_badgenumber,
        @target_pegawai_id AS target_pegawai_id,
        @target_userid AS target_userid,
        @target_badgenumber AS target_badgenumber,
        N'Pemetaan transfer aktif. Data mentah tidak diubah.' AS pesan;
END;
GO

-- Contoh pemakaian setelah memastikan User 1 dan User 2 benar:
-- EXEC dbo.usp_transfer_zkteco_user
--     @source_userid = 123,
--     @target_pegawai_id = 2025,
--     @alasan = N'Data duplikat pegawai',
--     @dibuat_oleh = N'admin';
