-- =============================================================================
-- INDEX OPTIMIZATION — Dashboard Intake (get_realisasi_terpadu.php)
-- Database : dbSopanusa
-- Dibuat   : 2026-05-20
--
-- CARA PAKAI:
--   Jalankan script ini di SQL Server Management Studio (SSMS) atau sqlcmd
--   terhadap database dbSopanusa.
--   Jalankan satu per satu blok IF NOT EXISTS jika ingin lebih aman.
--
-- URUTAN PRIORITAS:
--   1. tbSC   — tabel utama, filter terbanyak
--   2. tbOP   — JOIN + filter dTgl, cNoMc, cFlexo, cDC
--   3. tbStbBJ, tbSRJDtl, tbSRJ — aggregasi serah terima & pengiriman
--   4. tbHslCorrDtl, tbCorrDtl  — aggregasi corrugating
--   5. tbConvPlanDtl             — aggregasi converting
--   6. tbRtSrjDtl, tbRtSrj      — aggregasi retur
--   7. tbTSC                     — lookup kualitas
--   8. tbOP.cNoMc                — autocomplete mc_suggest
-- =============================================================================

USE dbSopanusa;
GO

-- ─────────────────────────────────────────────────────────────────────────────
-- 1. tbSC — filter utama: dTanggal (Tgl SC), dTglKirim2 (Tgl Kirim), cNoSc
-- ─────────────────────────────────────────────────────────────────────────────

-- Filter default & range Tgl SC
IF NOT EXISTS (
    SELECT 1 FROM sys.indexes
    WHERE object_id = OBJECT_ID('tbSC') AND name = 'IX_tbSC_dTanggal'
)
CREATE INDEX IX_tbSC_dTanggal
    ON tbSC (dTanggal)
    INCLUDE (cNoSc, cNama, cJenis, cJnsSc, cSales, cKeterangan, cKet_Mkt,
             nQty, dTglKirim2, lTK, nPanjang, nLebar, nTinggi, cWarna);
GO

-- Filter Tgl Kirim (ship_from / ship_to)
IF NOT EXISTS (
    SELECT 1 FROM sys.indexes
    WHERE object_id = OBJECT_ID('tbSC') AND name = 'IX_tbSC_dTglKirim2'
)
CREATE INDEX IX_tbSC_dTglKirim2
    ON tbSC (dTglKirim2)
    INCLUDE (cNoSc, cNama, cJenis, nQty, dTanggal);
GO

-- Lookup by cNoSc (dipakai di action=detail dan JOIN ke tbOP)
-- Catatan: tidak UNIQUE karena ditemukan duplicate key di data (misal SLC/0501/0001)
IF EXISTS (
    SELECT 1 FROM sys.indexes
    WHERE object_id = OBJECT_ID('tbSC') AND name = 'IX_tbSC_cNoSc'
)
DROP INDEX IX_tbSC_cNoSc ON tbSC;

CREATE INDEX IX_tbSC_cNoSc
    ON tbSC (cNoSc);
GO


-- ─────────────────────────────────────────────────────────────────────────────
-- 2. tbOP — JOIN ke tbSC + filter dTgl, cNoMc, cFlexo, cDC
-- ─────────────────────────────────────────────────────────────────────────────

-- JOIN utama: cNoSc → GROUP BY cNoSc (derived table di query list)
IF NOT EXISTS (
    SELECT 1 FROM sys.indexes
    WHERE object_id = OBJECT_ID('tbOP') AND name = 'IX_tbOP_cNoSc'
)
CREATE INDEX IX_tbOP_cNoSc
    ON tbOP (cNoSc)
    INCLUDE (cNoOp, cNoMc, dTgl, dTglkirim, dTglkirim2, cTipe,
             cFlexo, cDC, lTK, cMengetahui, cKetOrder,
             nQty, nQtyStok, nTot_netto, nRm, cJnsGel, userdate);
GO

-- Filter Tgl OP (date_from / date_to) — filter paling sering dipakai user
IF NOT EXISTS (
    SELECT 1 FROM sys.indexes
    WHERE object_id = OBJECT_ID('tbOP') AND name = 'IX_tbOP_dTgl'
)
CREATE INDEX IX_tbOP_dTgl
    ON tbOP (dTgl)
    INCLUDE (cNoSc, cNoOp, cNoMc, cFlexo, cDC, nQty, nQtyStok);
GO

-- Filter cFlexo (filter dropdown Flexo)
IF NOT EXISTS (
    SELECT 1 FROM sys.indexes
    WHERE object_id = OBJECT_ID('tbOP') AND name = 'IX_tbOP_cFlexo'
)
CREATE INDEX IX_tbOP_cFlexo
    ON tbOP (cFlexo)
    INCLUDE (cNoSc, cNoOp, dTgl);
GO

-- Filter cDC
IF NOT EXISTS (
    SELECT 1 FROM sys.indexes
    WHERE object_id = OBJECT_ID('tbOP') AND name = 'IX_tbOP_cDC'
)
CREATE INDEX IX_tbOP_cDC
    ON tbOP (cDC)
    INCLUDE (cNoSc, cNoOp, dTgl);
GO

-- Filter & autocomplete cNoMc (mc_suggest + filter MC)
IF NOT EXISTS (
    SELECT 1 FROM sys.indexes
    WHERE object_id = OBJECT_ID('tbOP') AND name = 'IX_tbOP_cNoMc'
)
CREATE INDEX IX_tbOP_cNoMc
    ON tbOP (cNoMc)
    INCLUDE (cNoSc, cNoOp, dTgl);
GO

-- Lookup by cNoOp (action=detail: WHERE cNoOp LIKE sc%)
IF NOT EXISTS (
    SELECT 1 FROM sys.indexes
    WHERE object_id = OBJECT_ID('tbOP') AND name = 'IX_tbOP_cNoOp'
)
CREATE INDEX IX_tbOP_cNoOp
    ON tbOP (cNoOp)
    INCLUDE (cNoSc, dTgl, nQty, nQtyStok);
GO


-- ─────────────────────────────────────────────────────────────────────────────
-- 3. tbStbBJ — aggregasi serah terima, GROUP BY cNoOp
-- ─────────────────────────────────────────────────────────────────────────────

IF NOT EXISTS (
    SELECT 1 FROM sys.indexes
    WHERE object_id = OBJECT_ID('tbStbBJ') AND name = 'IX_tbStbBJ_cNoOp'
)
CREATE INDEX IX_tbStbBJ_cNoOp
    ON tbStbBJ (cNoOp)
    INCLUDE (nQty, dTglSerah, cRak, cShift, cNoSc);
GO

-- Untuk action=detail: WHERE cNoSc = ? OR cNoOp LIKE ?
IF NOT EXISTS (
    SELECT 1 FROM sys.indexes
    WHERE object_id = OBJECT_ID('tbStbBJ') AND name = 'IX_tbStbBJ_cNoSc'
)
CREATE INDEX IX_tbStbBJ_cNoSc
    ON tbStbBJ (cNoSc)
    INCLUDE (cNoOp, nQty, dTglSerah);
GO


-- ─────────────────────────────────────────────────────────────────────────────
-- 4. tbSRJDtl & tbSRJ — aggregasi pengiriman, GROUP BY cNoOp
-- ─────────────────────────────────────────────────────────────────────────────

-- JOIN utama: d.cNoOp → GROUP BY d.cNoOp
IF NOT EXISTS (
    SELECT 1 FROM sys.indexes
    WHERE object_id = OBJECT_ID('tbSRJDtl') AND name = 'IX_tbSRJDtl_cNoOp'
)
CREATE INDEX IX_tbSRJDtl_cNoOp
    ON tbSRJDtl (cNoOp)
    INCLUDE (nQty, cNoSRJ, cNama, cNoScDtl);
GO

-- JOIN: tbSRJ ON d.cNoSRJ = s.cNoSRJ → lookup header SRJ
IF NOT EXISTS (
    SELECT 1 FROM sys.indexes
    WHERE object_id = OBJECT_ID('tbSRJ') AND name = 'IX_tbSRJ_cNoSRJ'
)
CREATE INDEX IX_tbSRJ_cNoSRJ
    ON tbSRJ (cNoSRJ)
    INCLUDE (dTanggal, cTujuanKirim, cNoPol, cKeterangan, cNoSC);
GO


-- ─────────────────────────────────────────────────────────────────────────────
-- 5. tbHslCorrDtl & tbCorrDtl — aggregasi hasil corrugating
-- ─────────────────────────────────────────────────────────────────────────────

IF NOT EXISTS (
    SELECT 1 FROM sys.indexes
    WHERE object_id = OBJECT_ID('tbHslCorrDtl') AND name = 'IX_tbHslCorrDtl_cNoOp'
)
CREATE INDEX IX_tbHslCorrDtl_cNoOp
    ON tbHslCorrDtl (cNoOp)
    INCLUDE (nHasil, nRusak, nBerat, cNoCorr, cNoMc, dStart, dFinish, cFlute, nOut);
GO

IF NOT EXISTS (
    SELECT 1 FROM sys.indexes
    WHERE object_id = OBJECT_ID('tbCorrDtl') AND name = 'IX_tbCorrDtl_cNoOp'
)
CREATE INDEX IX_tbCorrDtl_cNoOp
    ON tbCorrDtl (cNoOp)
    INCLUDE (nQtyOrder, cNoCorr, cType, cNoMc, nHasil, nRusak, dStart, dFinish, cFlute);
GO

-- JOIN: tbHslCorr ON d.cNoCorr = h.cNoCorr (action=detail)
IF NOT EXISTS (
    SELECT 1 FROM sys.indexes
    WHERE object_id = OBJECT_ID('tbHslCorr') AND name = 'IX_tbHslCorr_cNoCorr'
)
CREATE INDEX IX_tbHslCorr_cNoCorr
    ON tbHslCorr (cNoCorr)
    INCLUDE (dTanggal, cKodeCorr);
GO

IF NOT EXISTS (
    SELECT 1 FROM sys.indexes
    WHERE object_id = OBJECT_ID('tbCorr') AND name = 'IX_tbCorr_cNoCorr'
)
CREATE INDEX IX_tbCorr_cNoCorr
    ON tbCorr (cNoCorr)
    INCLUDE (dTanggal, cKodeCorr, cKeterangan);
GO


-- ─────────────────────────────────────────────────────────────────────────────
-- 6. tbConvPlanDtl & tbConvPlan — aggregasi converting
-- ─────────────────────────────────────────────────────────────────────────────

IF NOT EXISTS (
    SELECT 1 FROM sys.indexes
    WHERE object_id = OBJECT_ID('tbConvPlanDtl') AND name = 'IX_tbConvPlanDtl_cNoOp'
)
CREATE INDEX IX_tbConvPlanDtl_cNoOp
    ON tbConvPlanDtl (cNoOp)
    INCLUDE (nHasil, nRusak, cNoConv);
GO

-- JOIN: tbConvPlan ON d.cNoConv = p.cNoConv (action=detail)
IF NOT EXISTS (
    SELECT 1 FROM sys.indexes
    WHERE object_id = OBJECT_ID('tbConvPlan') AND name = 'IX_tbConvPlan_cNoConv'
)
CREATE INDEX IX_tbConvPlan_cNoConv
    ON tbConvPlan (cNoConv)
    INCLUDE (dTanggal, cKodeFlx);
GO


-- ─────────────────────────────────────────────────────────────────────────────
-- 7. tbRtSrjDtl & tbRtSrj — aggregasi retur
-- ─────────────────────────────────────────────────────────────────────────────

IF NOT EXISTS (
    SELECT 1 FROM sys.indexes
    WHERE object_id = OBJECT_ID('tbRtSrjDtl') AND name = 'IX_tbRtSrjDtl_cNomer'
)
CREATE INDEX IX_tbRtSrjDtl_cNomer
    ON tbRtSrjDtl (cNomer)
    INCLUDE (nQty, cItem, cKeterangan);
GO

IF NOT EXISTS (
    SELECT 1 FROM sys.indexes
    WHERE object_id = OBJECT_ID('tbRtSrj') AND name = 'IX_tbRtSrj_cNomer'
)
CREATE INDEX IX_tbRtSrj_cNomer
    ON tbRtSrj (cNomer)
    INCLUDE (dTgl, cNoSc, cNoSrj, cNama);
GO

-- JOIN via tbSRJDtl: r.cNoSrj = d2.cNoSRJ (di retur aggregat query list)
IF NOT EXISTS (
    SELECT 1 FROM sys.indexes
    WHERE object_id = OBJECT_ID('tbRtSrj') AND name = 'IX_tbRtSrj_cNoSrj'
)
CREATE INDEX IX_tbRtSrj_cNoSrj
    ON tbRtSrj (cNoSrj)
    INCLUDE (cNomer, dTgl, cNoSc);
GO

-- Filter action=detail: WHERE r.cNoSc = ?
IF NOT EXISTS (
    SELECT 1 FROM sys.indexes
    WHERE object_id = OBJECT_ID('tbRtSrj') AND name = 'IX_tbRtSrj_cNoSc'
)
CREATE INDEX IX_tbRtSrj_cNoSc
    ON tbRtSrj (cNoSc)
    INCLUDE (cNomer, dTgl, cNoSrj, cNama);
GO


-- ─────────────────────────────────────────────────────────────────────────────
-- 8. tbTSC — lookup kualitas per SC
-- ─────────────────────────────────────────────────────────────────────────────

IF NOT EXISTS (
    SELECT 1 FROM sys.indexes
    WHERE object_id = OBJECT_ID('tbTSC') AND name = 'IX_tbTSC_cNoSc'
)
CREATE INDEX IX_tbTSC_cNoSc
    ON tbTSC (cNoSc)
    INCLUDE (ckd_b1, ckd_b2, ckd_b3, ckd_b4, ckd_b5);
GO


-- ─────────────────────────────────────────────────────────────────────────────
-- VERIFIKASI — cek semua index sudah terbuat
-- ─────────────────────────────────────────────────────────────────────────────
SELECT
    t.name        AS [Tabel],
    i.name        AS [Nama Index],
    i.type_desc   AS [Tipe],
    i.is_unique   AS [Unique],
    STRING_AGG(c.name, ', ') WITHIN GROUP (ORDER BY ic.key_ordinal) AS [Key Columns]
FROM sys.indexes i
JOIN sys.tables t ON i.object_id = t.object_id
JOIN sys.index_columns ic ON i.object_id = ic.object_id AND i.index_id = ic.index_id
JOIN sys.columns c ON ic.object_id = c.object_id AND ic.column_id = c.column_id
WHERE t.name IN (
    'tbSC','tbOP','tbStbBJ','tbSRJ','tbSRJDtl',
    'tbHslCorrDtl','tbHslCorr','tbCorrDtl','tbCorr',
    'tbConvPlanDtl','tbConvPlan','tbRtSrjDtl','tbRtSrj','tbTSC'
)
AND i.name LIKE 'IX_%'
AND ic.is_included_column = 0
ORDER BY t.name, i.name;
GO
