-- ============================================================================
-- FILE  : create_index_realisasi_terpadu.sql
-- TUJUAN: Mempercepat query di get_realisasi_terpadu.php (dbSopanusa)
-- DB    : SQL Server (dbSopanusa)
-- CARA  : Jalankan satu per satu atau sekaligus di SSMS / sqlcmd
--         Setiap CREATE INDEX sudah dibungkus DROP IF EXISTS agar aman
--         dijalankan ulang tanpa error duplikat.
-- ============================================================================

USE dbSopanusa;
GO

-- ============================================================================
-- 1. tbSC  — tabel induk kontrak (SC)
--    Dipakai di: JOIN, WHERE, ORDER BY
-- ============================================================================

-- Filter utama: cNoSc (PK-like, kemungkinan sudah ada — lewati jika error)
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID('tbSC') AND name = 'IX_tbSC_cNoSc')
    CREATE INDEX IX_tbSC_cNoSc
        ON tbSC (cNoSc)
        INCLUDE (dTanggal, cNama, cJenis, cJnsSc, cSales, cKeterangan,
                 cKet_Mkt, nQty, dTglKirim2, lTK,
                 nPanjang, nLebar, nTinggi, cWarna);
GO

-- Filter tanggal SC (date_sc_from / date_sc_to)
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID('tbSC') AND name = 'IX_tbSC_dTanggal')
    CREATE INDEX IX_tbSC_dTanggal
        ON tbSC (dTanggal)
        INCLUDE (cNoSc, cNama, cJenis, cJnsSc, nQty, dTglKirim2, lTK);
GO

-- Filter pencarian teks: cNama (client), cJenis (product/jenis)
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID('tbSC') AND name = 'IX_tbSC_cNama_cJenis')
    CREATE INDEX IX_tbSC_cNama_cJenis
        ON tbSC (cNama, cJenis)
        INCLUDE (cNoSc, dTanggal);
GO


-- ============================================================================
-- 2. tbOP  — tabel order produksi
--    Dipakai di: JOIN ke tbSC (cNoSc), filter cNoOp, cNoMc, cFlexo, cDC, dTgl
-- ============================================================================

-- JOIN utama tbSC → tbOP
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID('tbOP') AND name = 'IX_tbOP_cNoSc')
    CREATE INDEX IX_tbOP_cNoSc
        ON tbOP (cNoSc)
        INCLUDE (cNoOp, cNoMc, nQty, nQtyStok, dTgl, dTglkirim, dTglkirim2,
                 cTipe, cFlexo, cDC, lTK, cMengetahui, cKetOrder,
                 nTot_netto, nRm, cJnsGel, userdate);
GO

-- Filter/search berdasarkan cNoOp (order_no)
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID('tbOP') AND name = 'IX_tbOP_cNoOp')
    CREATE INDEX IX_tbOP_cNoOp
        ON tbOP (cNoOp)
        INCLUDE (cNoSc, cNoMc, nQty, nQtyStok, dTgl, cFlexo, cDC, lTK, cJnsGel);
GO

-- Filter tanggal OP (date_from / date_to)
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID('tbOP') AND name = 'IX_tbOP_dTgl')
    CREATE INDEX IX_tbOP_dTgl
        ON tbOP (dTgl)
        INCLUDE (cNoOp, cNoSc, cNoMc, nQty, cFlexo, cDC, lTK);
GO

-- Filter mc (autocomplete & filter mc=)
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID('tbOP') AND name = 'IX_tbOP_cNoMc_dTgl')
    CREATE INDEX IX_tbOP_cNoMc_dTgl
        ON tbOP (cNoMc, dTgl)
        INCLUDE (cNoOp, cNoSc, nQty);
GO

-- Filter flexo
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID('tbOP') AND name = 'IX_tbOP_cFlexo')
    CREATE INDEX IX_tbOP_cFlexo
        ON tbOP (cFlexo)
        INCLUDE (cNoOp, cNoSc);
GO

-- Filter DC
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID('tbOP') AND name = 'IX_tbOP_cDC')
    CREATE INDEX IX_tbOP_cDC
        ON tbOP (cDC)
        INCLUDE (cNoOp, cNoSc);
GO


-- ============================================================================
-- 3. tbTSC  — kualitas bahan (ckd_b1..ckd_b5)
--    Dipakai di: LEFT JOIN ON tsc.cNoSc = sc.cNoSc
-- ============================================================================

IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID('tbTSC') AND name = 'IX_tbTSC_cNoSc')
    CREATE INDEX IX_tbTSC_cNoSc
        ON tbTSC (cNoSc)
        INCLUDE (ckd_b1, ckd_b2, ckd_b3, ckd_b4, ckd_b5);
GO


-- ============================================================================
-- 4. tbStbBJ  — serah terima barang jadi
--    Dipakai di: STB aggregat (GROUP BY cNoOp), filter cNoSc & cNoOp
-- ============================================================================

-- Join aggregat utama
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID('tbStbBJ') AND name = 'IX_tbStbBJ_cNoOp')
    CREATE INDEX IX_tbStbBJ_cNoOp
        ON tbStbBJ (cNoOp)
        INCLUDE (nQty, dTglSerah, cRak, cShift, cNoSc);
GO

-- Filter di action=detail (WHERE cNoSc = ? OR cNoOp LIKE ?)
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID('tbStbBJ') AND name = 'IX_tbStbBJ_cNoSc')
    CREATE INDEX IX_tbStbBJ_cNoSc
        ON tbStbBJ (cNoSc)
        INCLUDE (cNoOp, nQty, dTglSerah, cRak, cShift);
GO


-- ============================================================================
-- 5. tbSRJ  — surat jalan (header pengiriman)
--    Dipakai di: JOIN ke tbSRJDtl, filter dTanggal (ship_from/ship_to), cNoSC
-- ============================================================================

IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID('tbSRJ') AND name = 'IX_tbSRJ_cNoSRJ')
    CREATE INDEX IX_tbSRJ_cNoSRJ
        ON tbSRJ (cNoSRJ)
        INCLUDE (dTanggal, cTujuanKirim, cKeterangan, cNoPol, cNoSC, lVoid);
GO

-- Filter tanggal pengiriman (ship_from / ship_to)
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID('tbSRJ') AND name = 'IX_tbSRJ_dTanggal')
    CREATE INDEX IX_tbSRJ_dTanggal
        ON tbSRJ (dTanggal)
        INCLUDE (cNoSRJ, cTujuanKirim, cNoSC, lVoid);
GO

-- Filter cNoSC (EXISTS subquery di filter ship_from/ship_to)
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID('tbSRJ') AND name = 'IX_tbSRJ_cNoSC')
    CREATE INDEX IX_tbSRJ_cNoSC
        ON tbSRJ (cNoSC)
        INCLUDE (cNoSRJ, dTanggal, cTujuanKirim, lVoid);
GO


-- ============================================================================
-- 6. tbSRJDtl  — detail surat jalan (per item/OP)
--    Dipakai di: SRJ aggregat, EXISTS filter, action=detail
-- ============================================================================

-- JOIN utama ke tbSRJ
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID('tbSRJDtl') AND name = 'IX_tbSRJDtl_cNoSRJ')
    CREATE INDEX IX_tbSRJDtl_cNoSRJ
        ON tbSRJDtl (cNoSRJ)
        INCLUDE (cNoOp, cNoScDtl, cNama, nQty, nBrtOp, UserDate);
GO

-- GROUP BY / JOIN per cNoOp (SRJ aggregat + EXISTS)
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID('tbSRJDtl') AND name = 'IX_tbSRJDtl_cNoOp')
    CREATE INDEX IX_tbSRJDtl_cNoOp
        ON tbSRJDtl (cNoOp)
        INCLUDE (cNoSRJ, cNoScDtl, nQty, nBrtOp, UserDate);
GO

-- Filter cNoScDtl (EXISTS filter ship_from/ship_to)
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID('tbSRJDtl') AND name = 'IX_tbSRJDtl_cNoScDtl')
    CREATE INDEX IX_tbSRJDtl_cNoScDtl
        ON tbSRJDtl (cNoScDtl)
        INCLUDE (cNoSRJ, cNoOp, nQty, UserDate);
GO

-- Filter UserDate (EXISTS filter tanggal kirim)
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID('tbSRJDtl') AND name = 'IX_tbSRJDtl_UserDate')
    CREATE INDEX IX_tbSRJDtl_UserDate
        ON tbSRJDtl (UserDate)
        INCLUDE (cNoSRJ, cNoOp, cNoScDtl, nQty);
GO


-- ============================================================================
-- 7. tbHslCorr / tbHslCorrDtl  — hasil corrugating
--    Dipakai di: corr_agg (GROUP BY cNoOp) dan action=detail
-- ============================================================================

IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID('tbHslCorr') AND name = 'IX_tbHslCorr_cNoCorr')
    CREATE INDEX IX_tbHslCorr_cNoCorr
        ON tbHslCorr (cNoCorr)
        INCLUDE (cKodeCorr, dTanggal);
GO

IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID('tbHslCorrDtl') AND name = 'IX_tbHslCorrDtl_cNoOp')
    CREATE INDEX IX_tbHslCorrDtl_cNoOp
        ON tbHslCorrDtl (cNoOp)
        INCLUDE (cNoCorr, cNoMc, nHasil, nRusak, nBerat, nOut, dStart, dFinish, cFlute);
GO

IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID('tbHslCorrDtl') AND name = 'IX_tbHslCorrDtl_cNoCorr')
    CREATE INDEX IX_tbHslCorrDtl_cNoCorr
        ON tbHslCorrDtl (cNoCorr)
        INCLUDE (cNoOp, nHasil, nRusak, nBerat);
GO


-- ============================================================================
-- 8. tbCorr / tbCorrDtl  — planning corrugating
--    Dipakai di: corr_agg (plan_qty) dan action=detail
-- ============================================================================

IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID('tbCorr') AND name = 'IX_tbCorr_cNoCorr')
    CREATE INDEX IX_tbCorr_cNoCorr
        ON tbCorr (cNoCorr)
        INCLUDE (cKodeCorr, dTanggal, cKeterangan);
GO

IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID('tbCorrDtl') AND name = 'IX_tbCorrDtl_cNoOp')
    CREATE INDEX IX_tbCorrDtl_cNoOp
        ON tbCorrDtl (cNoOp)
        INCLUDE (cNoCorr, cType, cNoMc, nHasil, nRusak, nQtyOrder, dStart, dFinish, cFlute);
GO

IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID('tbCorrDtl') AND name = 'IX_tbCorrDtl_cNoCorr')
    CREATE INDEX IX_tbCorrDtl_cNoCorr
        ON tbCorrDtl (cNoCorr)
        INCLUDE (cNoOp, nQtyOrder);
GO


-- ============================================================================
-- 9. tbConvPlan / tbConvPlanDtl  — planning & hasil converting
--    Dipakai di: conv_agg (GROUP BY cNoOp) dan action=detail
-- ============================================================================

IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID('tbConvPlan') AND name = 'IX_tbConvPlan_cNoConv')
    CREATE INDEX IX_tbConvPlan_cNoConv
        ON tbConvPlan (cNoConv)
        INCLUDE (dTanggal, cKodeFlx);
GO

IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID('tbConvPlanDtl') AND name = 'IX_tbConvPlanDtl_cNoOp')
    CREATE INDEX IX_tbConvPlanDtl_cNoOp
        ON tbConvPlanDtl (cNoOp)
        INCLUDE (cNoConv, nHasil, nRusak);
GO

IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID('tbConvPlanDtl') AND name = 'IX_tbConvPlanDtl_cNoConv')
    CREATE INDEX IX_tbConvPlanDtl_cNoConv
        ON tbConvPlanDtl (cNoConv)
        INCLUDE (cNoOp, nHasil, nRusak);
GO


-- ============================================================================
-- 10. tbRtSrj / tbRtSrjDtl  — retur pengiriman
--     Dipakai di: retur_agg (GROUP BY cNoOp) dan action=detail
-- ============================================================================

IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID('tbRtSrj') AND name = 'IX_tbRtSrj_cNomer')
    CREATE INDEX IX_tbRtSrj_cNomer
        ON tbRtSrj (cNomer)
        INCLUDE (dTgl, cNoSc, cNoSrj, cNama);
GO

IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID('tbRtSrj') AND name = 'IX_tbRtSrj_cNoSrj')
    CREATE INDEX IX_tbRtSrj_cNoSrj
        ON tbRtSrj (cNoSrj)
        INCLUDE (cNomer, dTgl, cNoSc);
GO

-- Filter retur di action=detail (WHERE r.cNoSc = ?)
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID('tbRtSrj') AND name = 'IX_tbRtSrj_cNoSc')
    CREATE INDEX IX_tbRtSrj_cNoSc
        ON tbRtSrj (cNoSc)
        INCLUDE (cNomer, dTgl, cNoSrj, cNama);
GO

IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID('tbRtSrjDtl') AND name = 'IX_tbRtSrjDtl_cNomer')
    CREATE INDEX IX_tbRtSrjDtl_cNomer
        ON tbRtSrjDtl (cNomer)
        INCLUDE (cItem, nQty, cKeterangan);
GO


-- ============================================================================
-- 11. tbMesin  — master mesin converting
--     Dipakai di: LEFT JOIN ON m.cKode = p.cKodeFlx
-- ============================================================================

IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID('tbMesin') AND name = 'IX_tbMesin_cKode')
    CREATE INDEX IX_tbMesin_cKode
        ON tbMesin (cKode)
        INCLUDE (cNama);
GO


-- ============================================================================
-- SELESAI
-- ============================================================================
PRINT 'Semua index berhasil dibuat / sudah ada.';
GO
