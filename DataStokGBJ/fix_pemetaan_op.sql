/* ============================================================================
   PT SUPRACOR SEJAHTERA — PEMETAAN NO. OP (EXCEL) KE cNoSc (DATABASE)
   Dibuat : 04 Agustus 2026

   TEMUAN
     Nomor di file Excel gudang berformat  2607/00965
     Kolom cNoSc di database berformat     SLC/0708/10299
     Keduanya BUKAN kolom yang sama. Format Excel cocok dengan kolom cNoOp
     di tbStbBJ, bukan cNoSc.

   AKIBATNYA
     - tbStokGudangExcel diisi dengan nomor OP, tapi dipasang di kolom cNoSc
     - tbStokGudangAdj karena itu dihitung terhadap NO. OP yang tidak pernah
       ketemu, sehingga penyesuaiannya tidak berpengaruh apa-apa
     - Di Langkah 4c seluruh kolom "sistem" jadi 0

   File ini HANYA MEMBACA dan mendiagnosa (Langkah 1-3). Tidak ada perubahan
   data sampai Langkah 4, dan Langkah 4 pun cuma menambah kolom bantu di
   tabel buatan sendiri (tbStokGudangExcel). Tabel asli tidak disentuh.
   ============================================================================ */

USE dbSopanusa;
GO
SET NOCOUNT ON;
SET TRANSACTION ISOLATION LEVEL READ UNCOMMITTED;
GO

/* ---------------------------------------------------------------------------
   LANGKAH 1 — LIHAT BENTUK DATANYA
   --------------------------------------------------------------------------- */
SELECT TOP 15 cNoSTB, cNoSc, cNoOp, cNoOpLast, dTanggal, cNama, nQty
FROM   dbo.tbStbBJ
ORDER  BY dTanggal DESC;
GO

/* ---------------------------------------------------------------------------
   LANGKAH 2 — BERAPA BANYAK YANG COCOK?
   Dari 291 NO. OP di file Excel, berapa yang ketemu lewat masing-masing kolom.
   --------------------------------------------------------------------------- */
SELECT 'lewat cNoSc'      AS dicoba_lewat,
       COUNT(*)           AS ketemu
FROM   dbo.tbStokGudangExcel e
WHERE  EXISTS (SELECT 1 FROM dbo.tbStbBJ b WHERE RTRIM(b.cNoSc) = RTRIM(e.cNoSc))
UNION ALL
SELECT 'lewat cNoOp',     COUNT(*)
FROM   dbo.tbStokGudangExcel e
WHERE  EXISTS (SELECT 1 FROM dbo.tbStbBJ b WHERE RTRIM(b.cNoOp) = RTRIM(e.cNoSc))
UNION ALL
SELECT 'lewat cNoOpLast', COUNT(*)
FROM   dbo.tbStokGudangExcel e
WHERE  EXISTS (SELECT 1 FROM dbo.tbStbBJ b WHERE RTRIM(b.cNoOpLast) = RTRIM(e.cNoSc))
UNION ALL
SELECT 'total baris Excel', COUNT(*) FROM dbo.tbStokGudangExcel;
GO

/* ---------------------------------------------------------------------------
   LANGKAH 3 — APAKAH SATU NO. OP SELALU MENUNJUK KE SATU cNoSc?
   Ini penting. Kalau ada yang menunjuk ke lebih dari satu, pemetaannya harus
   pakai aturan tambahan (misal ambil yang STB terakhir).
   --------------------------------------------------------------------------- */
;WITH peta AS (
    SELECT RTRIM(b.cNoOp) AS no_op, COUNT(DISTINCT RTRIM(b.cNoSc)) AS jml_sc
    FROM   dbo.tbStbBJ b
    INNER JOIN dbo.tbStokGudangExcel e ON RTRIM(b.cNoOp) = RTRIM(e.cNoSc)
    GROUP  BY RTRIM(b.cNoOp)
)
SELECT SUM(CASE WHEN jml_sc = 1 THEN 1 ELSE 0 END) AS op_ke_satu_sc,
       SUM(CASE WHEN jml_sc > 1 THEN 1 ELSE 0 END) AS op_ke_banyak_sc,
       COUNT(*)                                    AS total_op_ketemu
FROM   peta;

-- Contoh NO. OP yang menunjuk ke lebih dari satu cNoSc (kalau ada)
;WITH peta AS (
    SELECT RTRIM(b.cNoOp) AS no_op, RTRIM(b.cNoSc) AS no_sc, COUNT(*) AS baris,
           MAX(b.dTanggal) AS stb_terakhir
    FROM   dbo.tbStbBJ b
    INNER JOIN dbo.tbStokGudangExcel e ON RTRIM(b.cNoOp) = RTRIM(e.cNoSc)
    GROUP  BY RTRIM(b.cNoOp), RTRIM(b.cNoSc)
)
SELECT TOP 30 * FROM peta
WHERE  no_op IN (SELECT no_op FROM peta GROUP BY no_op HAVING COUNT(*) > 1)
ORDER  BY no_op, stb_terakhir DESC;
GO

/* ---------------------------------------------------------------------------
   LANGKAH 4 — SIMPAN HASIL PEMETAAN
   Jalankan HANYA kalau Langkah 2 menunjukkan cNoOp yang paling banyak cocok.
   Kolom cNoSc lama TIDAK dihapus, cuma ditambah kolom baru di sebelahnya.
   --------------------------------------------------------------------------- */

-- 4a. Tambah kolom bantu
IF COL_LENGTH('dbo.tbStokGudangExcel', 'cNoOpExcel') IS NULL
    ALTER TABLE dbo.tbStokGudangExcel ADD cNoOpExcel VARCHAR(30) NULL;
GO
IF COL_LENGTH('dbo.tbStokGudangExcel', 'cNoScDb') IS NULL
    ALTER TABLE dbo.tbStokGudangExcel ADD cNoScDb VARCHAR(30) NULL;
GO

-- 4b. Nilai yang selama ini tersimpan di cNoSc sebenarnya nomor OP
UPDATE dbo.tbStokGudangExcel SET cNoOpExcel = RTRIM(cNoSc) WHERE cNoOpExcel IS NULL;
GO

-- 4c. Cari cNoSc yang benar, diambil dari baris STB paling akhir
UPDATE e
SET    e.cNoScDb = p.cNoSc
FROM   dbo.tbStokGudangExcel e
OUTER APPLY (
    SELECT TOP 1 RTRIM(b.cNoSc) AS cNoSc
    FROM   dbo.tbStbBJ b
    WHERE  RTRIM(b.cNoOp) = e.cNoOpExcel
    ORDER  BY b.dTanggal DESC, b.cNoSTB DESC
) p;
GO

-- 4d. HASIL PEMETAAN. Idealnya belum_ketemu = 0.
SELECT COUNT(*)                                              AS total,
       SUM(CASE WHEN cNoScDb IS NOT NULL THEN 1 ELSE 0 END)  AS ketemu,
       SUM(CASE WHEN cNoScDb IS NULL     THEN 1 ELSE 0 END)  AS belum_ketemu,
       SUM(CASE WHEN cNoScDb IS NULL THEN nStokAkhirPc ELSE 0 END) AS pc_belum_ketemu
FROM   dbo.tbStokGudangExcel;

-- 4e. Daftar yang belum ketemu, untuk dicek manual ke gudang
SELECT cNoOpExcel, cNama, cNamabrg, nStokAkhirPc, cKeterangan
FROM   dbo.tbStokGudangExcel
WHERE  cNoScDb IS NULL
ORDER  BY nStokAkhirPc DESC;

-- 4f. Contoh pemetaan yang berhasil, untuk diperiksa mata
SELECT TOP 20 cNoOpExcel AS no_op_excel, cNoScDb AS no_sc_database,
       cNama, nStokAkhirPc
FROM   dbo.tbStokGudangExcel
WHERE  cNoScDb IS NOT NULL
ORDER  BY nStokAkhirPc DESC;
GO

/* ---------------------------------------------------------------------------
   LANGKAH 5 — SETELAH PEMETAAN BENAR, tbStokGudangAdj HARUS DIHITUNG ULANG
   Isi yang sekarang tidak berlaku karena dihitung terhadap nomor yang salah.
   Jangan jalankan dulu sebelum hasil Langkah 4d dikonfirmasi.
   --------------------------------------------------------------------------- */
/*
TRUNCATE TABLE dbo.tbStokGudangAdj;
-- lalu jalankan ulang STEP 3 pada import_stok_stb_bj.sql,
-- dengan e.cNoSc diganti e.cNoScDb
*/
