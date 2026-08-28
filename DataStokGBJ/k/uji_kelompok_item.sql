/* ============================================================================
   PT SUPRACOR SEJAHTERA — KELOMPOKKAN STOK PER CUSTOMER + ITEM
   Dibuat  : 05 Agustus 2026
   Revisi  : perbaikan alias kolom tanggal (Msg 207 'Invalid column name dTanggal')

   DUGAAN cNoOpLast TERBUKTI SALAH
     Dari 4.471 baris STB sejak Juli, 2.058 (46%) punya cNoOpLast berbeda dari
     cNoOp-nya sendiri, dan menunjuk ke SC yang tidak berhubungan. Bukan
     penanda kelompok.

   POLA YANG SEBENARNYA — CUSTOMER + ITEM
     SLC/2607/00843 dan 00844   -> MEGASURYA MAS, "BOX REFILL MINYAK 2 LTR"
     SLC/2606/00294 dan 00295   -> HOKKAN DELTAPACK, "BOX UK. 530 X 420 X 415"
     SLC/2607/00056/00099/00377 -> ETIKA DAIRIES, "CB PB DC 2.5 KG LOKAL"
     SLC/2604/01451 dan 01452   -> SUMBER MUTIARA, "LAYER"
     Barang yang sama untuk customer yang sama, di gudang ditumpuk jadi satu.

   FILE INI HANYA MEMBACA. Tidak ada perubahan data.
   ============================================================================ */

USE dbSopanusa;
GO
SET NOCOUNT ON;
SET TRANSACTION ISOLATION LEVEL READ UNCOMMITTED;
GO

/* ---------------------------------------------------------------------------
   PERSIAPAN — tabel bantu: tiap NO. SC dipetakan ke kode customer + item,
   diambil dari baris STB paling akhir.
   --------------------------------------------------------------------------- */
IF OBJECT_ID('tempdb..#si')  IS NOT NULL DROP TABLE #si;
IF OBJECT_ID('tempdb..#xl')  IS NOT NULL DROP TABLE #xl;
IF OBJECT_ID('tempdb..#db')  IS NOT NULL DROP TABLE #db;
IF OBJECT_ID('tempdb..#grp') IS NOT NULL DROP TABLE #grp;

SELECT sc, kode, item
INTO   #si
FROM ( SELECT RTRIM(b.cNoSc)      AS sc,
              RTRIM(b.cKodeCust)  AS kode,
              RTRIM(b.cNamabrg)   AS item,
              ROW_NUMBER() OVER (PARTITION BY RTRIM(b.cNoSc)
                                 ORDER BY b.dTanggal DESC, b.cNoSTB DESC) AS rn
       FROM   dbo.tbStbBJ b
       WHERE  b.cNoSc IS NOT NULL AND LTRIM(RTRIM(b.cNoSc)) <> '' ) x
WHERE  rn = 1;
CREATE UNIQUE CLUSTERED INDEX IX_si ON #si (sc);
GO

/* ---------------------------------------------------------------------------
   LANGKAH 1 — APAKAH STOK MINUS TERTUTUP OLEH SC LAIN YANG SEITEM?
   --------------------------------------------------------------------------- */
SELECT i.kode, i.item, COUNT(*) AS jml_sc, SUM(s.nStokPc) AS pc
INTO   #grp
FROM   dbo.tbStokGudangSnap s
INNER JOIN #si i ON i.sc = s.cNoSc
GROUP  BY i.kode, i.item;

SELECT  s.cNoSc AS sc_minus, s.cNama, i.item, s.nStokPc AS stok_sc,
        g.jml_sc AS sc_seitem, g.pc AS stok_kelompok,
        CASE WHEN g.pc >= 0 THEN 'TERTUTUP' ELSE 'MASIH MINUS' END AS hasil
FROM       dbo.tbStokGudangSnap s
INNER JOIN #si  i ON i.sc = s.cNoSc
INNER JOIN #grp g ON g.kode = i.kode AND g.item = i.item
WHERE      s.nStokPc < 0
ORDER BY   s.nStokPc;
GO

/* ---------------------------------------------------------------------------
   LANGKAH 2 — RINGKASAN: per SC vs per customer + item
   --------------------------------------------------------------------------- */
SELECT 'Per NO. SC (cara sekarang)' AS tingkat, COUNT(*) AS jml_baris,
       SUM(CASE WHEN nStokPc < 0 THEN 1 ELSE 0 END)       AS jml_minus,
       SUM(CASE WHEN nStokPc < 0 THEN nStokPc ELSE 0 END) AS pc_minus,
       SUM(nStokPc) AS total_pc
FROM   dbo.tbStokGudangSnap
UNION ALL
SELECT 'Per customer + item (usulan)', COUNT(*),
       SUM(CASE WHEN pc < 0 THEN 1 ELSE 0 END),
       SUM(CASE WHEN pc < 0 THEN pc ELSE 0 END),
       SUM(pc)
FROM   #grp;
GO

/* ---------------------------------------------------------------------------
   LANGKAH 3 — AKURASI MUTASI 01-03 AGUSTUS PADA TINGKAT CUSTOMER + ITEM
   --------------------------------------------------------------------------- */
SELECT i.kode, i.item, e.dTanggal AS tgl,
       SUM(e.nStbPc) AS stb, SUM(e.nDlvPc) AS dlv
INTO   #xl
FROM   dbo.tbCekMutasiExcel e
INNER JOIN #si i ON i.sc = e.cNoScDb
GROUP  BY i.kode, i.item, e.dTanggal;

SELECT i.kode, i.item, t.tgl, SUM(t.stb) AS stb, SUM(t.dlv) AS dlv
INTO   #db
FROM ( SELECT RTRIM(cNoSc) AS sc, CAST(dTanggal AS DATE) AS tgl,
              SUM(ISNULL(nQty,0)) AS stb, 0 AS dlv
       FROM   dbo.tbStbBJ
       WHERE  dTanggal >= '2026-08-01' AND dTanggal < '2026-08-04'
       GROUP  BY RTRIM(cNoSc), CAST(dTanggal AS DATE)
       UNION ALL
       SELECT RTRIM(COALESCE(d.cNoScDtl, s.cNoSC)), CAST(s.dTanggal AS DATE),
              0, SUM(ISNULL(d.nQty,0))
       FROM   dbo.tbSRJ s INNER JOIN dbo.tbSRJDtl d ON d.cNoSRJ = s.cNoSRJ
       WHERE  s.dTanggal >= '2026-08-01' AND s.dTanggal < '2026-08-04'
       GROUP  BY RTRIM(COALESCE(d.cNoScDtl, s.cNoSC)), CAST(s.dTanggal AS DATE) ) t
INNER JOIN #si i ON i.sc = t.sc
GROUP  BY i.kode, i.item, t.tgl;

-- Bandingkan dengan hasil per SC kemarin: STB selisih 34.815, DLV selisih 41.189
SELECT 'STB (barang masuk)' AS jenis,
       SUM(ISNULL(x.stb,0)) AS menurut_excel,
       SUM(ISNULL(b.stb,0)) AS menurut_database,
       SUM(ABS(ISNULL(b.stb,0) - ISNULL(x.stb,0))) AS selisih_mutlak
FROM      #xl x FULL JOIN #db b ON b.kode = x.kode AND b.item = x.item AND b.tgl = x.tgl
UNION ALL
SELECT 'DLV (barang keluar)',
       SUM(ISNULL(x.dlv,0)), SUM(ISNULL(b.dlv,0)),
       SUM(ABS(ISNULL(b.dlv,0) - ISNULL(x.dlv,0)))
FROM      #xl x FULL JOIN #db b ON b.kode = x.kode AND b.item = x.item AND b.tgl = x.tgl;
GO

/* ---------------------------------------------------------------------------
   LANGKAH 4 — SISA SELISIH MUTASI TERBESAR SETELAH DIKELOMPOKKAN
   Yang muncul di sini benar-benar beda jumlah, bukan beda penomoran.
   --------------------------------------------------------------------------- */
SELECT TOP 25
       COALESCE(x.kode, b.kode) AS kode_cust,
       COALESCE(x.item, b.item) AS item,
       COALESCE(x.tgl,  b.tgl)  AS tanggal,
       ISNULL(x.stb,0) AS stb_excel, ISNULL(b.stb,0) AS stb_db,
       ISNULL(b.stb,0) - ISNULL(x.stb,0) AS selisih_stb,
       ISNULL(x.dlv,0) AS dlv_excel, ISNULL(b.dlv,0) AS dlv_db,
       ISNULL(b.dlv,0) - ISNULL(x.dlv,0) AS selisih_dlv
FROM      #xl x FULL JOIN #db b ON b.kode = x.kode AND b.item = x.item AND b.tgl = x.tgl
WHERE     ISNULL(x.stb,0) <> ISNULL(b.stb,0) OR ISNULL(x.dlv,0) <> ISNULL(b.dlv,0)
ORDER BY  ABS(ISNULL(b.stb,0) - ISNULL(x.stb,0)) + ABS(ISNULL(b.dlv,0) - ISNULL(x.dlv,0)) DESC;
GO

/* ---------------------------------------------------------------------------
   LANGKAH 5 — PERBANDINGAN TOTAL PER ITEM: PATOKAN EXCEL vs STOK SISTEM
   Inilah jawaban langsung atas "berapa jauh totalnya dari Excel".
   Catatan: patokan Excel posisi 03 Agustus, stok sistem posisi hari ini,
   jadi selisih wajar sebesar mutasi 04-05 Agustus.
   --------------------------------------------------------------------------- */
SELECT 'Patokan Excel per 03 Agu' AS sumber, SUM(nStokAkhirPc) AS total_pc
FROM   dbo.tbStokGudangExcel
UNION ALL
SELECT 'Stok sistem hari ini', SUM(nStokPc) FROM dbo.tbStokGudangSnap;

-- Rincian per item, selisih terbesar di atas
SELECT TOP 30
       COALESCE(g.kode, ex.kode)  AS kode_cust,
       COALESCE(g.item, ex.item)  AS item,
       ISNULL(ex.pc, 0)           AS patokan_excel,
       ISNULL(g.pc, 0)            AS stok_sistem,
       ISNULL(g.pc, 0) - ISNULL(ex.pc, 0) AS selisih
FROM   #grp g
FULL JOIN ( SELECT i.kode, i.item, SUM(e.nStokAkhirPc) AS pc
            FROM   dbo.tbStokGudangExcel e
            INNER JOIN #si i ON i.sc = e.cNoScDb
            GROUP  BY i.kode, i.item ) ex
       ON ex.kode = g.kode AND ex.item = g.item
WHERE  ISNULL(g.pc,0) <> ISNULL(ex.pc,0)
ORDER BY ABS(ISNULL(g.pc,0) - ISNULL(ex.pc,0)) DESC;
GO

DROP TABLE #si; DROP TABLE #xl; DROP TABLE #db; DROP TABLE #grp;
GO
