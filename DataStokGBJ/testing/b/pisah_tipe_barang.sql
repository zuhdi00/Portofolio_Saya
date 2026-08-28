/* ============================================================================
   PT SUPRACOR SEJAHTERA — PISAHKAN QTY PER TIPE BARANG
   Dibuat : 07 Agustus 2026

   TUJUAN
     Memisahkan stok menjadi BOX, PART, LAYER, dan SHEET, supaya angkanya bisa
     langsung diadu dengan sheet BOX dan sheet PART+LAYER di file gudang.

   DASAR PENGGOLONGAN
     Tipe barang terbaca dari akhiran cNoOp di tbStbBJ:
        SPS/2607/01328-B01    -> B  -> BOX
        SPS/2607/00056-P0101  -> P  -> PART
        SPS/2607/01348-S01    -> S  -> SHEET
     Huruf pertama setelah tanda hubung menentukan tipenya. Nama barang
     (cNamabrg) dipakai sebagai penguat, misal yang mengandung kata LAYER.

   KETERBATASAN YANG HARUS DIPAHAMI
     tbStbBJ punya cNoOp, jadi barang MASUK bisa dipisah per tipe dengan pasti.
     tbSRJDtl hanya menyimpan cNoScDtl tanpa nomor OP, jadi barang KELUAR
     belum tentu bisa dipisah. Langkah 2 memeriksa hal ini lebih dulu.

     Untuk NO. SC yang isinya hanya satu tipe (mayoritas), pemisahan PASTI.
     Untuk NO. SC campuran, pengurangan surat jalan dibagi menurut porsi STB
     dan ditandai ESTIMASI, tidak diam-diam dianggap pasti.

   Langkah 1-3 hanya membaca. Langkah 4 membuat tabel baru.
   ============================================================================ */

USE dbSopanusa;
GO
SET NOCOUNT ON;
SET TRANSACTION ISOLATION LEVEL READ UNCOMMITTED;
GO

/* ---------------------------------------------------------------------------
   LANGKAH 1 — PASTIKAN ARTI AKHIRAN cNoOp
   Lihat apakah huruf akhiran benar-benar mencerminkan tipe barangnya.
   --------------------------------------------------------------------------- */
SELECT huruf,
       COUNT(*)                        AS jml_baris,
       COUNT(DISTINCT RTRIM(cNoSc))    AS jml_sc,
       SUM(ISNULL(nQty,0))             AS total_qty,
       MIN(cNamabrg)                   AS contoh_item_1,
       MAX(cNamabrg)                   AS contoh_item_2
FROM ( SELECT cNoSc, cNamabrg, nQty,
              CASE WHEN CHARINDEX('-', RTRIM(cNoOp)) > 0
                   THEN UPPER(SUBSTRING(RTRIM(cNoOp), CHARINDEX('-', RTRIM(cNoOp)) + 1, 1))
                   ELSE '(tanpa akhiran)' END AS huruf
       FROM   dbo.tbStbBJ
       WHERE  dTanggal >= '2026-07-01' ) x
GROUP  BY huruf ORDER BY total_qty DESC;
GO

/* ---------------------------------------------------------------------------
   LANGKAH 2 — APAKAH SURAT JALAN PUNYA PENANDA TIPE?
   Kalau tbSRJDtl ternyata punya kolom nomor OP atau nama barang, pemisahan
   bisa dibuat pasti untuk kedua arah. Kirim hasilnya ke saya kalau ada.
   --------------------------------------------------------------------------- */
SELECT COLUMN_NAME, DATA_TYPE, CHARACTER_MAXIMUM_LENGTH AS lebar
FROM   INFORMATION_SCHEMA.COLUMNS
WHERE  TABLE_NAME = 'tbSRJDtl'
ORDER  BY ORDINAL_POSITION;
GO

/* ---------------------------------------------------------------------------
   LANGKAH 3 — BERAPA BANYAK NO. SC YANG ISINYA CAMPURAN?
   Semakin sedikit yang campuran, semakin akurat pemisahannya.
   --------------------------------------------------------------------------- */
;WITH tipe AS (
    SELECT RTRIM(b.cNoSc) AS sc,
           CASE WHEN b.cNamabrg LIKE '%LAYER%'                       THEN 'LAYER'
                WHEN b.cNamabrg LIKE 'SHEET%'
                  OR b.cNamabrg LIKE '%SLIP SHEET%'                  THEN 'SHEET'
                WHEN CHARINDEX('-', RTRIM(b.cNoOp)) = 0              THEN 'LAIN'
                WHEN UPPER(SUBSTRING(RTRIM(b.cNoOp), CHARINDEX('-', RTRIM(b.cNoOp)) + 1, 1)) = 'B' THEN 'BOX'
                WHEN UPPER(SUBSTRING(RTRIM(b.cNoOp), CHARINDEX('-', RTRIM(b.cNoOp)) + 1, 1)) = 'P' THEN 'PART'
                WHEN UPPER(SUBSTRING(RTRIM(b.cNoOp), CHARINDEX('-', RTRIM(b.cNoOp)) + 1, 1)) = 'L' THEN 'LAYER'
                WHEN UPPER(SUBSTRING(RTRIM(b.cNoOp), CHARINDEX('-', RTRIM(b.cNoOp)) + 1, 1)) = 'S' THEN 'SHEET'
                ELSE 'LAIN' END AS tipe,
           ISNULL(b.nQty,0) AS qty
    FROM   dbo.tbStbBJ b
    WHERE  b.dTanggal >= '2026-07-01' AND b.cNoSc IS NOT NULL
),
per_sc AS (
    SELECT sc, COUNT(DISTINCT tipe) AS jml_tipe FROM tipe GROUP BY sc
)
SELECT jml_tipe AS tipe_dalam_satu_sc, COUNT(*) AS jml_sc
FROM   per_sc GROUP BY jml_tipe ORDER BY jml_tipe;

-- Contoh NO. SC campuran
;WITH tipe AS (
    SELECT RTRIM(b.cNoSc) AS sc,
           CASE WHEN b.cNamabrg LIKE '%LAYER%' THEN 'LAYER'
                WHEN b.cNamabrg LIKE 'SHEET%' OR b.cNamabrg LIKE '%SLIP SHEET%' THEN 'SHEET'
                WHEN CHARINDEX('-', RTRIM(b.cNoOp)) = 0 THEN 'LAIN'
                WHEN UPPER(SUBSTRING(RTRIM(b.cNoOp), CHARINDEX('-', RTRIM(b.cNoOp)) + 1, 1)) = 'B' THEN 'BOX'
                WHEN UPPER(SUBSTRING(RTRIM(b.cNoOp), CHARINDEX('-', RTRIM(b.cNoOp)) + 1, 1)) = 'P' THEN 'PART'
                WHEN UPPER(SUBSTRING(RTRIM(b.cNoOp), CHARINDEX('-', RTRIM(b.cNoOp)) + 1, 1)) = 'L' THEN 'LAYER'
                WHEN UPPER(SUBSTRING(RTRIM(b.cNoOp), CHARINDEX('-', RTRIM(b.cNoOp)) + 1, 1)) = 'S' THEN 'SHEET'
                ELSE 'LAIN' END AS tipe,
           ISNULL(b.nQty,0) AS qty
    FROM   dbo.tbStbBJ b
    WHERE  b.dTanggal >= '2026-07-01' AND b.cNoSc IS NOT NULL
)
SELECT TOP 20 sc, tipe, SUM(qty) AS qty_stb
FROM   tipe
WHERE  sc IN (SELECT sc FROM tipe GROUP BY sc HAVING COUNT(DISTINCT tipe) > 1)
GROUP  BY sc, tipe ORDER BY sc, tipe;
GO

/* ---------------------------------------------------------------------------
   LANGKAH 4 — TABEL KOMPOSISI TIPE PER NO. SC
   Menyimpan porsi tiap tipe dalam satu NO. SC, dipakai untuk membagi stok.
   --------------------------------------------------------------------------- */
IF OBJECT_ID('dbo.tbStokGudangTipe') IS NOT NULL DROP TABLE dbo.tbStokGudangTipe;
GO
CREATE TABLE dbo.tbStokGudangTipe (
    cNoSc    VARCHAR(30)   NOT NULL,
    cTipe    VARCHAR(10)   NOT NULL,      -- BOX / PART / LAYER / SHEET / LAIN
    nQtyStb  BIGINT        NOT NULL DEFAULT 0,
    nPorsi   DECIMAL(9,6)  NOT NULL DEFAULT 0,   -- porsi tipe ini dalam SC
    lTunggal BIT           NOT NULL DEFAULT 0,   -- 1 = SC ini isinya satu tipe
    cContoh  NVARCHAR(300) NULL,
    CONSTRAINT PK_tbStokGudangTipe PRIMARY KEY (cNoSc, cTipe)
);
GO

;WITH tipe AS (
    SELECT RTRIM(b.cNoSc) AS sc,
           CASE WHEN b.cNamabrg LIKE '%LAYER%' THEN 'LAYER'
                WHEN b.cNamabrg LIKE 'SHEET%' OR b.cNamabrg LIKE '%SLIP SHEET%' THEN 'SHEET'
                WHEN CHARINDEX('-', RTRIM(b.cNoOp)) = 0 THEN 'LAIN'
                WHEN UPPER(SUBSTRING(RTRIM(b.cNoOp), CHARINDEX('-', RTRIM(b.cNoOp)) + 1, 1)) = 'B' THEN 'BOX'
                WHEN UPPER(SUBSTRING(RTRIM(b.cNoOp), CHARINDEX('-', RTRIM(b.cNoOp)) + 1, 1)) = 'P' THEN 'PART'
                WHEN UPPER(SUBSTRING(RTRIM(b.cNoOp), CHARINDEX('-', RTRIM(b.cNoOp)) + 1, 1)) = 'L' THEN 'LAYER'
                WHEN UPPER(SUBSTRING(RTRIM(b.cNoOp), CHARINDEX('-', RTRIM(b.cNoOp)) + 1, 1)) = 'S' THEN 'SHEET'
                ELSE 'LAIN' END AS tipe,
           ISNULL(b.nQty,0) AS qty, b.cNamabrg
    FROM   dbo.tbStbBJ b
    WHERE  b.cNoSc IS NOT NULL AND LTRIM(RTRIM(b.cNoSc)) <> ''
      AND  b.dTanggal >= '2026-01-01'
),
rekap AS (
    SELECT sc, tipe, SUM(qty) AS qty, MAX(cNamabrg) AS contoh
    FROM   tipe GROUP BY sc, tipe
)
INSERT INTO dbo.tbStokGudangTipe (cNoSc, cTipe, nQtyStb, nPorsi, lTunggal, cContoh)
SELECT r.sc, r.tipe, r.qty,
       CASE WHEN t.total > 0 THEN CAST(r.qty AS DECIMAL(18,6)) / t.total ELSE 0 END,
       CASE WHEN t.jml_tipe = 1 THEN 1 ELSE 0 END,
       LEFT(r.contoh, 300)
FROM       rekap r
INNER JOIN (SELECT sc, SUM(qty) AS total, COUNT(*) AS jml_tipe
            FROM   rekap GROUP BY sc) t ON t.sc = r.sc;
GO

SELECT cTipe, COUNT(*) AS jml_sc, SUM(nQtyStb) AS total_stb_sejak_januari,
       SUM(CASE WHEN lTunggal = 1 THEN 1 ELSE 0 END) AS sc_tipe_tunggal
FROM   dbo.tbStokGudangTipe GROUP BY cTipe ORDER BY SUM(nQtyStb) DESC;
GO

/* ---------------------------------------------------------------------------
   LANGKAH 5 — STOK DIPISAH PER TIPE
   NO. SC bertipe tunggal dihitung PASTI. NO. SC campuran dibagi menurut porsi
   STB dan ditandai ESTIMASI supaya tidak dikira angka pasti.
   --------------------------------------------------------------------------- */
IF OBJECT_ID('dbo.vwStokPerTipe') IS NOT NULL DROP VIEW dbo.vwStokPerTipe;
GO
CREATE VIEW dbo.vwStokPerTipe AS
SELECT  s.cNoSc,
        s.cNama,
        s.cNamabrg,
        t.cTipe,
        CASE WHEN t.lTunggal = 1 THEN s.nStokPc
             ELSE CAST(ROUND(s.nStokPc * t.nPorsi, 0) AS INT) END AS nStokPc,
        CASE WHEN t.lTunggal = 1 THEN s.nStokKg
             ELSE CAST(ROUND(s.nStokKg * t.nPorsi, 3) AS DECIMAL(18,3)) END AS nStokKg,
        CASE WHEN t.lTunggal = 1 THEN 'PASTI' ELSE 'ESTIMASI' END AS cSifat,
        CAST(ROUND(t.nPorsi * 100, 2) AS DECIMAL(6,2)) AS nPorsiPersen,
        s.nUmur, s.cKeterangan, s.cStatusData
FROM       dbo.tbStokGudangSnap s
INNER JOIN dbo.tbStokGudangTipe t ON t.cNoSc = s.cNoSc;
GO

/* ---------------------------------------------------------------------------
   LANGKAH 6 — HASILNYA, SIAP DIADU DENGAN FILE GUDANG
   --------------------------------------------------------------------------- */

-- 6a. Rekap per tipe
SELECT cTipe,
       COUNT(*)        AS jml_sc,
       SUM(nStokPc)    AS total_pc,
       SUM(nStokKg)    AS total_kg,
       SUM(CASE WHEN cSifat = 'ESTIMASI' THEN 1 ELSE 0 END) AS sc_estimasi
FROM   dbo.vwStokPerTipe GROUP BY cTipe ORDER BY SUM(nStokPc) DESC;

-- 6b. Digabung mengikuti pembagian sheet di file gudang
SELECT CASE WHEN cTipe = 'BOX' THEN 'BOX'
            WHEN cTipe IN ('PART','LAYER') THEN 'PART+LAYER'
            ELSE cTipe END AS sheet_gudang,
       COUNT(*) AS jml_sc, SUM(nStokPc) AS total_pc, SUM(nStokKg) AS total_kg
FROM   dbo.vwStokPerTipe
GROUP  BY CASE WHEN cTipe = 'BOX' THEN 'BOX'
               WHEN cTipe IN ('PART','LAYER') THEN 'PART+LAYER'
               ELSE cTipe END
ORDER  BY total_pc DESC;

-- 6c. Bandingkan langsung dengan patokan Excel per kategori
SELECT k.kategori,
       ISNULL(x.pc, 0) AS menurut_excel,
       ISNULL(y.pc, 0) AS menurut_sistem,
       ISNULL(y.pc, 0) - ISNULL(x.pc, 0) AS selisih
FROM   (SELECT 'BOX' AS kategori UNION ALL SELECT 'PART+LAYER') k
LEFT JOIN (SELECT cKategori AS kategori, SUM(nStokAkhirPc) AS pc
           FROM   dbo.tbStokGudangExcel GROUP BY cKategori) x ON x.kategori = k.kategori
LEFT JOIN (SELECT CASE WHEN cTipe = 'BOX' THEN 'BOX'
                       WHEN cTipe IN ('PART','LAYER') THEN 'PART+LAYER' END AS kategori,
                  SUM(nStokPc) AS pc
           FROM   dbo.vwStokPerTipe
           WHERE  cTipe IN ('BOX','PART','LAYER')
           GROUP  BY CASE WHEN cTipe = 'BOX' THEN 'BOX'
                          WHEN cTipe IN ('PART','LAYER') THEN 'PART+LAYER' END) y
       ON y.kategori = k.kategori;

-- 6d. NO. SC campuran, yang angkanya masih perkiraan
SELECT cNoSc, cNama, cTipe, nPorsiPersen, nStokPc, cNamabrg
FROM   dbo.vwStokPerTipe WHERE cSifat = 'ESTIMASI'
ORDER  BY cNoSc, nStokPc DESC;
GO
