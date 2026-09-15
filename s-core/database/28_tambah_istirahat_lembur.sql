USE dbHR;
GO

IF COL_LENGTH('dbo.lembur_detail', 'istirahat_jam') IS NULL
BEGIN
    ALTER TABLE dbo.lembur_detail
        ADD istirahat_jam DECIMAL(5,2) NOT NULL
            CONSTRAINT DF_lembur_detail_istirahat_jam DEFAULT (0);
END;
GO
