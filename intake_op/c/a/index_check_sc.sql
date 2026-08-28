USE [dbSopanusa];
GO

-- Jalankan sekali di SQL Server pada jam non-sibuk.
-- Index ini mempercepat pengecekan nomor SC langsung.
IF NOT EXISTS (
    SELECT 1 FROM sys.indexes
    WHERE name = N'IX_tbSC_cNoSC_check'
      AND object_id = OBJECT_ID(N'dbo.tbSC')
)
BEGIN
    CREATE NONCLUSTERED INDEX IX_tbSC_cNoSC_check
        ON dbo.tbSC (cNoSC);
END;
GO

IF NOT EXISTS (
    SELECT 1 FROM sys.indexes
    WHERE name = N'IX_tbTSC_cNoTSC_check'
      AND object_id = OBJECT_ID(N'dbo.tbTSC')
)
BEGIN
    CREATE NONCLUSTERED INDEX IX_tbTSC_cNoTSC_check
        ON dbo.tbTSC (cNoTSC);
END;
GO

-- Suffix cNoTSC untuk pencocokan nomor SC mulai karakter ke-5.
IF COL_LENGTH(N'dbo.tbTSC', N'cNoTSC_Suffix') IS NULL
BEGIN
    ALTER TABLE dbo.tbTSC ADD cNoTSC_Suffix AS
        CONVERT(varchar(200), SUBSTRING(CONVERT(varchar(200), cNoTSC), 5, 200)) PERSISTED;
END;
GO

IF NOT EXISTS (
    SELECT 1 FROM sys.indexes
    WHERE name = N'IX_tbTSC_cNoTSC_Suffix'
      AND object_id = OBJECT_ID(N'dbo.tbTSC')
)
BEGIN
    CREATE NONCLUSTERED INDEX IX_tbTSC_cNoTSC_Suffix
        ON dbo.tbTSC (cNoTSC_Suffix);
END;
GO

-- Tambahkan suffix yang sama pada tabel retur TSC.
IF COL_LENGTH(N'dbo.tbRvTsc', N'cNoTSC_Suffix') IS NULL
BEGIN
    ALTER TABLE dbo.tbRvTsc ADD cNoTSC_Suffix AS
        CONVERT(varchar(200), SUBSTRING(CONVERT(varchar(200), cNoTSC), 5, 200)) PERSISTED;
END;
GO

IF NOT EXISTS (
    SELECT 1 FROM sys.indexes
    WHERE name = N'IX_tbRvTsc_cNoTSC_Suffix'
      AND object_id = OBJECT_ID(N'dbo.tbRvTsc')
)
BEGIN
    CREATE NONCLUSTERED INDEX IX_tbRvTsc_cNoTSC_Suffix
        ON dbo.tbRvTsc (cNoTSC_Suffix);
END;
GO

-- Index untuk kandidat OP berdasarkan nomor SC dan tanggal kirim.
IF NOT EXISTS (
    SELECT 1 FROM sys.indexes
    WHERE name = N'IX_tbOP_cNoSc_dTglkirim2'
      AND object_id = OBJECT_ID(N'dbo.tbOP')
)
BEGIN
    CREATE NONCLUSTERED INDEX IX_tbOP_cNoSc_dTglkirim2
        ON dbo.tbOP (cNoSc, dTglkirim2)
        INCLUDE (cNoOp, cNoMc);
END;
GO

-- Index utama untuk URL list dengan date_sc_from/date_sc_to.
IF NOT EXISTS (
    SELECT 1 FROM sys.indexes
    WHERE name = N'IX_tbOP_dTglkirim2_realisasi'
      AND object_id = OBJECT_ID(N'dbo.tbOP')
)
BEGIN
    CREATE NONCLUSTERED INDEX IX_tbOP_dTglkirim2_realisasi
        ON dbo.tbOP (dTglkirim2, cNoSc, cNoOp)
        INCLUDE (cNoMc, cnm_c, cnm_brg, cTipe, cFlexo, cDC,
                 dTgl, nQtyStok, nTot_netto, nBrutto_usl, nBrutto_usp);
END;
GO

IF NOT EXISTS (
    SELECT 1 FROM sys.indexes
    WHERE name = N'IX_tbStbBJ_cNoOp_realisasi'
      AND object_id = OBJECT_ID(N'dbo.tbStbBJ')
)
BEGIN
    CREATE NONCLUSTERED INDEX IX_tbStbBJ_cNoOp_realisasi
        ON dbo.tbStbBJ (cNoOp)
        INCLUDE (nQty, nQtyKg);
END;
GO

IF NOT EXISTS (
    SELECT 1 FROM sys.indexes
    WHERE name = N'IX_tbHslCorrDtl_cNoOp_realisasi'
      AND object_id = OBJECT_ID(N'dbo.tbHslCorrDtl')
)
BEGIN
    CREATE NONCLUSTERED INDEX IX_tbHslCorrDtl_cNoOp_realisasi
        ON dbo.tbHslCorrDtl (cNoOp)
        INCLUDE (nHasil, nBerat);
END;
GO

IF NOT EXISTS (
    SELECT 1 FROM sys.indexes
    WHERE name = N'IX_tbCorrDtl_cNoOp_realisasi'
      AND object_id = OBJECT_ID(N'dbo.tbCorrDtl')
)
BEGIN
    CREATE NONCLUSTERED INDEX IX_tbCorrDtl_cNoOp_realisasi
        ON dbo.tbCorrDtl (cNoOp)
        INCLUDE (nQtyOrder);
END;
GO
