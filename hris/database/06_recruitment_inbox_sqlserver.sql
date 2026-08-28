USE [dbHR];
GO

IF OBJECT_ID(N'dbo.recruitment_inbox', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.recruitment_inbox (
        id INT IDENTITY(1,1) NOT NULL,
        message_uid NVARCHAR(150) NOT NULL,
        sender_email NVARCHAR(255) NULL,
        subject NVARCHAR(500) NULL,
        body NVARCHAR(MAX) NULL,
        attachment_path NVARCHAR(500) NULL,
        received_at DATETIME2(0) NULL,
        status NVARCHAR(20) NOT NULL CONSTRAINT DF_recruitment_inbox_status DEFAULT (N'baru'),
        created_at DATETIME2(0) NOT NULL CONSTRAINT DF_recruitment_inbox_created_at DEFAULT (SYSDATETIME()),
        CONSTRAINT PK_recruitment_inbox PRIMARY KEY (id),
        CONSTRAINT UQ_recruitment_inbox_message_uid UNIQUE (message_uid),
        CONSTRAINT CK_recruitment_inbox_status CHECK (status IN (N'baru', N'diproses', N'diabaikan'))
    );
END;
GO

SELECT TABLE_SCHEMA, TABLE_NAME
FROM INFORMATION_SCHEMA.TABLES
WHERE TABLE_SCHEMA = N'dbo' AND TABLE_NAME = N'recruitment_inbox';
GO
