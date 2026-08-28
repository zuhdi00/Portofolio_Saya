-- ============================================================
-- INDEX OPTIMASI — LAPORAN STOK BARANG JADI
-- Database: dbSopanusa @ spsdmz2
-- Tujuan: mempercepat query get_stok_barang_jadi.php
--         (CTE stb_agg, srj_agg, retur_agg + JOIN ke tbOP/tbSC)
--
-- CARA PAKAI:
-- 1. Jalankan satu per satu di SSMS pada jam non-sibuk (CREATE INDEX
--    bisa mengunci tabel sebentar / memakan I/O saat build).
-- 2. Untuk tabel besar & live production, pertimbangkan tambahkan
--    WITH (ONLINE = ON) bila edisi SQL Server mendukung (Enterprise).
-- 3. Cek dulu index yang sudah ada (lihat query cek di paling bawah)
--    supaya tidak membuat index duplikat.
-- ============================================================

-- ------------------------------------------------------------
-- 1) tbStbBJ — sumber stb_agg (Saldo Awal & STB periode)
-- Query selalu filter: lPosted='1' AND lVoid='0', GROUP BY cNoOp,
-- dengan kondisi pada dTanggal. Index filtered + cover kolom yang
-- dibaca (nQty, nQtyKg, cRak) supaya tidak perlu Key Lookup ke heap.
-- ------------------------------------------------------------
CREATE NONCLUSTERED INDEX IX_tbStbBJ_cNoOp_dTanggal_Posted
ON tbStbBJ (cNoOp, dTanggal)
INCLUDE (nQty, nQtyKg, cRak, lPosted, lVoid)
WHERE lPosted = '1' AND lVoid = '0';
-- Catatan: filtered index hanya valid kalau lPosted/lVoid bertipe
-- yang mendukung filtered index (char/varchar konstan literal '1'/'0' aman).
-- Kalau SQL Server menolak (edisi/keterbatasan tipe data), pakai versi
-- tanpa WHERE di bawah:
-- CREATE NONCLUSTERED INDEX IX_tbStbBJ_cNoOp_dTanggal
-- ON tbStbBJ (cNoOp, dTanggal)
-- INCLUDE (nQty, nQtyKg, cRak, lPosted, lVoid);

-- ------------------------------------------------------------
-- 2) tbSRJ — header surat jalan, sumber tanggal kirim untuk srj_agg
-- JOIN ke tbSRJDtl via cNoSRJ, filter lPosted/lVoid, filter dTglKirim.
-- ------------------------------------------------------------
CREATE NONCLUSTERED INDEX IX_tbSRJ_cNoSRJ_dTglKirim_Posted
ON tbSRJ (cNoSRJ, dTglKirim)
INCLUDE (lPosted, lVoid)
WHERE lPosted = '1' AND lVoid = '0';
-- Fallback non-filtered bila perlu:
-- CREATE NONCLUSTERED INDEX IX_tbSRJ_cNoSRJ_dTglKirim
-- ON tbSRJ (cNoSRJ, dTglKirim) INCLUDE (lPosted, lVoid);

-- ------------------------------------------------------------
-- 3) tbSRJDtl — detail SRJ, sumber nQty & nBrtOp untuk DLV
-- JOIN balik ke tbSRJ via cNoSRJ, GROUP BY cNoOp.
-- ------------------------------------------------------------
CREATE NONCLUSTERED INDEX IX_tbSRJDtl_cNoSRJ_cNoOp
ON tbSRJDtl (cNoSRJ, cNoOp)
INCLUDE (nQty, nBrtOp);

-- Index tambahan untuk join balik dari sisi cNoOp (dipakai juga oleh
-- WHERE EXISTS dan kemungkinan query lain yang cari per OP):
CREATE NONCLUSTERED INDEX IX_tbSRJDtl_cNoOp
ON tbSRJDtl (cNoOp)
INCLUDE (cNoSRJ, nQty, nBrtOp);

-- ------------------------------------------------------------
-- 4) TbRtSrj / TbRtSrjDtl — tabel fisik di balik vwReturnSrj.
-- DIKONFIRMASI via sp_helptext vwReturnSrj:
--   FROM dbo.TbRtSrj INNER JOIN dbo.TbRtSrjDtl
--   ON TbRtSrj.cNomer = TbRtSrjDtl.cNomer AND TbRtSrj.cCom = TbRtSrjDtl.cCom
-- PENTING: join key antar dua tabel ini cNomer+cCom (BUKAN cNoSrj).
-- Kolom cNoSrj cuma ada di TbRtSrj, dan itu yang dipakai untuk JOIN
-- ke tbSRJDtl.cNoSRJ pada query laporan Stok Barang Jadi.
-- ------------------------------------------------------------

-- 4a. TbRtSrjDtl — untuk JOIN ke TbRtSrj (cNomer+cCom)
CREATE NONCLUSTERED INDEX IX_TbRtSrjDtl_cNomer_cCom
ON TbRtSrjDtl (cNomer, cCom)
INCLUDE (nQty, nBerat);

-- 4b. TbRtSrj — dua index: satu untuk JOIN ke detail (cNomer+cCom),
-- satu lagi untuk JOIN ke tbSRJDtl (cNoSrj) sekaligus filter periode.
CREATE NONCLUSTERED INDEX IX_TbRtSrj_cNomer_cCom
ON TbRtSrj (cNomer, cCom);

CREATE NONCLUSTERED INDEX IX_TbRtSrj_cNoSrj_dTglKirim_Posted
ON TbRtSrj (cNoSrj, dTglKirim)
INCLUDE (lPosted, lVoid)
WHERE lPosted = '1' AND lVoid = '0';
-- Fallback non-filtered bila SQL Server menolak filtered index:
-- CREATE NONCLUSTERED INDEX IX_TbRtSrj_cNoSrj_dTglKirim
-- ON TbRtSrj (cNoSrj, dTglKirim) INCLUDE (lPosted, lVoid);

-- ------------------------------------------------------------
-- 5) tbOP — root/driving table. cNoOp biasanya sudah PK/clustered,
-- tapi join ke tbSC pakai cNoSc — pastikan ada index di situ.
-- ------------------------------------------------------------
CREATE NONCLUSTERED INDEX IX_tbOP_cNoSc
ON tbOP (cNoSc)
INCLUDE (cnm_brg, cNoMc, nPanjang, nLebar, nTinggi);

-- ------------------------------------------------------------
-- 6) tbSC — pastikan cNoSC (PK) sudah ter-index (biasanya sudah,
-- karena ini PK/clustered index). Tambahan: cover cNama & cSales
-- supaya JOIN tbOP -> tbSC tidak perlu Key Lookup.
-- ------------------------------------------------------------
CREATE NONCLUSTERED INDEX IX_tbSC_cNoSC_Cover
ON tbSC (cNoSC)
INCLUDE (cNama, cSales, cStatus);
-- (Lewati index ini jika cNoSC sudah clustered index / PK — biasanya sudah.)

-- ============================================================
-- CEK INDEX YANG SUDAH ADA SEBELUM MEMBUAT BARU
-- Jalankan dulu untuk masing-masing tabel agar tidak duplikat:
-- ============================================================
-- EXEC sp_helpindex 'tbStbBJ';
-- EXEC sp_helpindex 'tbSRJ';
-- EXEC sp_helpindex 'tbSRJDtl';
-- EXEC sp_helpindex 'tbOP';
-- EXEC sp_helpindex 'tbSC';
-- EXEC sp_helpindex 'TbRtSrj';
-- EXEC sp_helpindex 'TbRtSrjDtl';

-- ============================================================
-- OPSIONAL — Update statistik setelah index baru dibuat
-- (membantu optimizer langsung pakai index baru, bukan plan lama)
-- ============================================================
UPDATE STATISTICS tbStbBJ;
UPDATE STATISTICS tbSRJ;
UPDATE STATISTICS tbSRJDtl;
UPDATE STATISTICS tbOP;
UPDATE STATISTICS tbSC;
UPDATE STATISTICS TbRtSrj;
UPDATE STATISTICS TbRtSrjDtl;
