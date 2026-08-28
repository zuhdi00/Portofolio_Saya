/* ============================================================================
   PT SUPRACOR SEJAHTERA — KELOMPOKKAN STOK PER CUSTOMER + ITEM
   Dibuat : 05 Agustus 2026

   DUGAAN cNoOpLast TERBUKTI SALAH
     Contoh SLC/2607/00843 dan SLC/2607/00844: keenam OP-nya menunjuk ke
     cNoOpLast yang sama (SPS/2607/00847-B02), tapi kolom itu juga menunjuk
     ke SC lain yang sama sekali tidak berhubungan. Dari 4.471 baris STB sejak
     Juli, 2.058 (46%) punya cNoOpLast berbeda dari cNoOp-nya sendiri. Kolom
     ini bukan penanda kelompok.

   POLA YANG SEBENARNYA — CUSTOMER + ITEM
     SLC/2607/00843 dan 00844  -> MEGASURYA MAS, "BOX REFILL MINYAK 2 LTR"
     SLC/2606/00294 dan 00295  -> HOKKAN DELTAPACK, "BOX UK. 530 X 420 X 415"
     SLC/2607/00056/00099/00377-> ETIKA DAIRIES, "CB PB DC 2.5 KG LOKAL"
     SLC/2604/01451 dan 01452  -> SUMBER MUTIARA, "LAYER"
     SLC/2607/01252 dan 01290  -> "POLOS COKLAT UK LUAR 510 X 310 X 145 MM"

     Semuanya barang yang sama persis untuk customer yang sama. Di gudang
     barang itu ditumpuk jadi satu, jadi wajar kalau gudang mencatat mutasinya
     di salah satu nomor saja sementara database memecah per SC.

   YANG DILAKUKAN FILE INI
     Menghitung stok pada tingkat customer + item, bukan per SC. Rincian per SC
     tetap ada untuk penelusuran, tapi angka yang dipakai adalah angka kelompok.

   FILE INI HANYA MEMBACA. Tidak ada perubahan data.
   ============================================================================ */

USE dbSopanusa;
GO
SET NOCOUNT ON;
SET TRANSACTION ISOLATION LEVEL READ UNCOMMITTED;
GO

/* ---------------------------------------------------------------------------
   LANGKAH 1 — APAKAH STOK MINUS TERTUTUP OLEH SC LAIN YANG SEITEM?
   Ini penentu utama. Kolom stok_kelompok idealnya tidak lagi minus.
   --------------------------------------------------------------------------- */
;WITH sc_item AS (
    SELECT sc, kode, item FROM (
        SELECT RTRIM(b.cNoSc) AS sc, RTRIM(b.cKodeCust) AS kode, RTRIM(b.cNamabrg) AS item,
               ROW_NUMBER() OVER (PARTITION BY RTRIM(b.cNoSc)
                                  ORDER BY b.dTanggal DESC, b.cNoSTB DESC) AS rn
        FROM   dbo.tbStbBJ b
        WHERE  b.cNoSc IS NOT NULL AND LTRIM(RTRIM(b.cNoSc)) <> ''
    ) x WHERE rn = 1
),
snap AS (
    SELECT s.cNoSc, s.cNama, s.nStokPc, i.kode, i.item
    FROM   dbo.tbStokGudangSnap s
    INNER JOIN sc_item i ON i.sc = s.cNoSc
)
SELECT  m.cNoSc          AS sc_minus,
        m.cNama,
        m.item,
        m.nStokPc        AS stok_sc,
        g.jml_sc         AS sc_seitem,
        g.stok_kelompok,
        CASE WHEN g.stok_kelompok >= 0 THEN 'TERTUTUP' ELSE 'MASIH MINUS' END AS hasil
FROM        snap m
INNER JOIN (SELECT kode, item, COUNT(*) AS jml_sc, SUM(nStokPc) AS stok_kelompok
            FROM   snap GROUP BY kode, item) g
        ON  g.kode = m.kode AND g.item = m.item
WHERE   m.nStokPc < 0
ORDER BY m.nStokPc;
GO

/* ---------------------------------------------------------------------------
   LANGKAH 2 — RINGKASAN. Berapa minus yang tersisa setelah dikelompokkan?
   --------------------------------------------------------------------------- */
;WITH sc_item AS (
    SELECT sc, kode, item FROM (
        SELECT RTRIM(b.cNoSc) AS sc, RTRIM(b.cKodeCust) AS kode, RTRIM(b.cNamabrg) AS item,
               ROW_NUMBER() OVER (PARTITION BY RTRIM(b.cNoSc)
                                  ORDER BY b.dTanggal DESC, b.cNoSTB DESC) AS rn
        FROM   dbo.tbStbBJ b WHERE b.cNoSc IS NOT NULL AND LTRIM(RTRIM(b.cNoSc)) <> ''
    ) x WHERE rn = 1
),
grup AS (
    SELECT i.kode, i.item, COUNT(*) AS jml_sc, SUM(s.nStokPc) AS pc
    FROM   dbo.tbStokGudangSnap s INNER JOIN sc_item i ON i.sc = s.cNoSc
    GROUP  BY i.kode, i.item
)
SELECT 'Per NO. SC (cara sekarang)' AS tingkat,
       COUNT(*) AS jml_baris,
       SUM(CASE WHEN nStokPc < 0 THEN 1 ELSE 0 END)       AS jml_minus,
       SUM(CASE WHEN nStokPc < 0 THEN nStokPc ELSE 0 END) AS pc_minus,
       SUM(nStokPc) AS total_pc
FROM   dbo.tbStokGudangSnap
UNION ALL
SELECT 'Per customer + item (usulan)',
       COUNT(*),
       SUM(CASE WHEN pc < 0 THEN 1 ELSE 0 END),
       SUM(CASE WHEN pc < 0 THEN pc ELSE 0 END),
       SUM(pc)
FROM   grup;
GO

/* ---------------------------------------------------------------------------
   LANGKAH 3 — UKUR ULANG AKURASI PADA TINGKAT CUSTOMER + ITEM
   Kalau dugaan benar, selisih 4% kemarin harus turun drastis, karena mutasi
   yang tercatat di SC berbeda kini masuk ke kelompok yang sama.
   --------------------------------------------------------------------------- */
;WITH sc_item AS (
    SELECT sc, kode, item FROM (
        SELECT RTRIM(b.cNoSc) AS sc, RTRIM(b.cKodeCust) AS kode, RTRIM(b.cNamabrg) AS item,
               ROW_NUMBER() OVER (PARTITION BY RTRIM(b.cNoSc)
                                  ORDER BY b.dTanggal DESC, b.cNoSTB DESC) AS rn
        FROM   dbo.tbStbBJ b WHERE b.cNoSc IS NOT NULL AND LTRIM(RTRIM(b.cNoSc)) <> ''
    ) x WHERE rn = 1
),
xl AS (   -- catatan gudang, dinaikkan ke tingkat kelompok
    SELECT i.kode, i.item, e.dTanggal,
           SUM(e.nStbPc) AS stb, SUM(e.nDlvPc) AS dlv
    FROM   dbo.tbCekMutasiExcel e
    INNER JOIN sc_item i ON i.sc = e.cNoScDb
    GROUP  BY i.kode, i.item, e.dTanggal
),
db AS (   -- catatan database, dinaikkan ke tingkat kelompok
    SELECT i.kode, i.item, t.d, SUM(t.stb) AS stb, SUM(t.dlv) AS dlv
    FROM (
        SELECT RTRIM(cNoSc) AS sc, CAST(dTanggal AS DATE) AS d,
               SUM(ISNULL(nQty,0)) AS stb, 0 AS dlv
        FROM   dbo.tbStbBJ
        WHERE  dTanggal >= '2026-08-01' AND dTanggal < '2026-08-04'
        GROUP  BY RTRIM(cNoSc), CAST(dTanggal AS DATE)
        UNION ALL
        SELECT RTRIM(COALESCE(d.cNoScDtl, s.cNoSC)), CAST(s.dTanggal AS DATE),
               0, SUM(ISNULL(d.nQty,0))
        FROM   dbo.tbSRJ s INNER JOIN dbo.tbSRJDtl d ON d.cNoSRJ = s.cNoSRJ
        WHERE  s.dTanggal >= '2026-08-01' AND s.dTanggal < '2026-08-04'
        GROUP  BY RTRIM(COALESCE(d.cNoScDtl, s.cNoSC)), CAST(s.dTanggal AS DATE)
    ) t
    INNER JOIN sc_item i ON i.sc = t.sc
    GROUP  BY i.kode, i.item, t.d
)
SELECT 'STB (barang masuk)' AS jenis,
       SUM(ISNULL(x.stb,0)) AS menurut_excel,
       SUM(ISNULL(d.stb,0)) AS menurut_database,
       SUM(ABS(ISNULL(d.stb,0) - ISNULL(x.stb,0))) AS total_selisih_mutlak
FROM      xl x FULL JOIN db d ON d.kode = x.kode AND d.item = x.item AND d.d = x.dTanggal
UNION ALL
SELECT 'DLV (barang keluar)',
       SUM(ISNULL(x.dlv,0)), SUM(ISNULL(d.dlv,0)),
       SUM(ABS(ISNULL(d.dlv,0) - ISNULL(x.dlv,0)))
FROM      xl x FULL JOIN db d ON d.kode = x.kode AND d.item = x.item AND d.d = x.dTanggal;
GO

/* ---------------------------------------------------------------------------
   LANGKAH 4 — SISA SELISIH TERBESAR SETELAH DIKELOMPOKKAN
   Yang muncul di sini berarti benar-benar beda jumlah, bukan beda penomoran.
   --------------------------------------------------------------------------- */
;WITH sc_item AS (
    SELECT sc, kode, item FROM (
        SELECT RTRIM(b.cNoSc) AS sc, RTRIM(b.cKodeCust) AS kode, RTRIM(b.cNamabrg) AS item,
               ROW_NUMBER() OVER (PARTITION BY RTRIM(b.cNoSc)
                                  ORDER BY b.dTanggal DESC, b.cNoSTB DESC) AS rn
        FROM   dbo.tbStbBJ b WHERE b.cNoSc IS NOT NULL AND LTRIM(RTRIM(b.cNoSc)) <> ''
    ) x WHERE rn = 1
),
xl AS (
    SELECT i.kode, i.item, e.dTanggal AS d, SUM(e.nStbPc) AS stb, SUM(e.nDlvPc) AS dlv
    FROM   dbo.tbCekMutasiExcel e INNER JOIN sc_item i ON i.sc = e.cNoScDb
    GROUP  BY i.kode, i.item, e.dTanggal
),
db AS (
    SELECT i.kode, i.item, t.d, SUM(t.stb) AS stb, SUM(t.dlv) AS dlv
    FROM (
        SELECT RTRIM(cNoSc) AS sc, CAST(dTanggal AS DATE) AS d, SUM(ISNULL(nQty,0)) AS stb, 0 AS dlv
        FROM dbo.tbStbBJ WHERE dTanggal >= '2026-08-01' AND dTanggal < '2026-08-04'
        GROUP BY RTRIM(cNoSc), CAST(dTanggal AS DATE)
        UNION ALL
        SELECT RTRIM(COALESCE(d.cNoScDtl, s.cNoSC)), CAST(s.dTanggal AS DATE), 0, SUM(ISNULL(d.nQty,0))
        FROM dbo.tbSRJ s INNER JOIN dbo.tbSRJDtl d ON d.cNoSRJ = s.cNoSRJ
        WHERE s.dTanggal >= '2026-08-01' AND s.dTanggal < '2026-08-04'
        GROUP BY RTRIM(COALESCE(d.cNoScDtl, s.cNoSC)), CAST(s.dTanggal AS DATE)
    ) t INNER JOIN sc_item i ON i.sc = t.sc
    GROUP BY i.kode, i.item, t.d
)
SELECT TOP 25
       COALESCE(x.kode, d.kode) AS kode_cust,
       COALESCE(x.item, d.item) AS item,
       COALESCE(x.d, d.d)       AS tanggal,
       ISNULL(x.stb,0) AS stb_excel, ISNULL(d.stb,0) AS stb_db,
       ISNULL(d.stb,0) - ISNULL(x.stb,0) AS selisih_stb,
       ISNULL(x.dlv,0) AS dlv_excel, ISNULL(d.dlv,0) AS dlv_db,
       ISNULL(d.dlv,0) - ISNULL(x.dlv,0) AS selisih_dlv
FROM      xl x FULL JOIN db d ON d.kode = x.kode AND d.item = x.item AND d.d = x.dTanggal
WHERE     ISNULL(x.stb,0) <> ISNULL(d.stb,0) OR ISNULL(x.dlv,0) <> ISNULL(d.dlv,0)
ORDER BY  ABS(ISNULL(d.stb,0) - ISNULL(x.stb,0)) + ABS(ISNULL(d.dlv,0) - ISNULL(x.dlv,0)) DESC;
GO
