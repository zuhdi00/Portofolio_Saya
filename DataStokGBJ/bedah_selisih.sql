/* ============================================================================
   PT SUPRACOR SEJAHTERA — BEDAH SELISIH: EXCEL vs SISTEM, KOMPONEN PER KOMPONEN
   Dibuat : 05 Agustus 2026

   PELURUSAN DULU
     Angka 649.713 itu sheet BOX saja. Total Excel = BOX 649.713 + PART/LAYER
     255.891 = 905.604 pc. Jadi sistem (818.664) LEBIH RENDAH 86.940 pc,
     bukan lebih tinggi.

   CARA KERJA FILE INI
     Excel dan sistem berangkat dari saldo awal yang SAMA (1.034.498 pc), lalu
     jalannya berbeda. Kalau tiap komponen dibandingkan terpisah, ketahuan
     persis komponen mana yang menyimpang:

        Komponen         Excel        Sistem     Selisih
        Saldo awal   1.034.498    1.034.498          0
        + STB        1.913.374          ?            ?
        - Kirim      2.066.890          ?            ?
        + Retur         19.088          ?            ?
        = Saldo akhir  905.604      818.664    -86.940

     Begitu ketahuan komponennya, Langkah 3 dan 4 langsung menunjuk
     tanggal dan NO. OP penyebabnya.

   FILE INI HANYA MEMBACA kecuali Langkah 1 yang membuat tabel bantu berisi
   angka dari file Excel.
   ============================================================================ */

USE dbSopanusa;
GO
SET NOCOUNT ON;
SET TRANSACTION ISOLATION LEVEL READ UNCOMMITTED;
GO

/* ---------------------------------------------------------------------------
   LANGKAH 1 — ANGKA HARIAN MENURUT FILE EXCEL (BOX + PART/LAYER digabung)
   --------------------------------------------------------------------------- */
IF OBJECT_ID('tempdb..#xl') IS NOT NULL DROP TABLE #xl;
CREATE TABLE #xl (tgl DATE PRIMARY KEY, stb INT, dlv INT, ret INT);

INSERT INTO #xl (tgl, stb, dlv, ret) VALUES
 ('2026-08-01', 234786, 356002,  3675),
 ('2026-08-02', 146184,      0,     0),
 ('2026-08-03', 483147, 553185, 13588),
 ('2026-08-04', 534771, 657862,     3),
 ('2026-08-05', 514486, 499841,  1822);
GO

/* ---------------------------------------------------------------------------
   LANGKAH 2 — JEMBATAN ANGKA BERDAMPINGAN
   Baris mana yang selisihnya besar, di situlah masalahnya.
   --------------------------------------------------------------------------- */
DECLARE @Cut DATE = '2026-07-31';
DECLARE @Patokan INT = (SELECT SUM(nStokAkhirPc) FROM dbo.tbStokGudangExcel);
DECLARE @Sistem  INT = (SELECT SUM(nStokPc)      FROM dbo.tbStokGudangSnap);
DECLARE @XlAkhir INT = (SELECT SUM(nSaldoPc)     FROM dbo.tbCekSaldoExcel);

/* Mutasi menurut database, DIBATASI pada NO. OP yang ikut dihitung snapshot,
   supaya setara dengan cara prosedur menghitung. */
DECLARE @Stb INT, @Krm INT, @Ret INT;

SELECT @Stb = SUM(ISNULL(b.nQty,0)) FROM dbo.tbStbBJ b
WHERE  b.dTanggal > @Cut
  AND  EXISTS (SELECT 1 FROM dbo.tbStokGudangSnap x WHERE x.cNoSc = RTRIM(b.cNoSc));

SELECT @Krm = SUM(ISNULL(d.nQty,0))
FROM   dbo.tbSRJ s INNER JOIN dbo.tbSRJDtl d ON d.cNoSRJ = s.cNoSRJ
WHERE  s.dTanggal > @Cut
  AND  EXISTS (SELECT 1 FROM dbo.tbStokGudangSnap x
               WHERE x.cNoSc = RTRIM(COALESCE(d.cNoScDtl, s.cNoSC)));

SELECT @Ret = SUM(ISNULL(rv.nQty,0)) FROM dbo.vwReturnSrj rv
WHERE  rv.dTgl > @Cut
  AND  EXISTS (SELECT 1 FROM dbo.tbStokGudangSnap x WHERE x.cNoSc = RTRIM(rv.cNoSc));

SELECT 1 AS urut, 'Saldo awal Agustus' AS komponen,
       (SELECT SUM(stb) FROM #xl) * 0 + @Patokan AS menurut_excel,
       @Patokan AS menurut_sistem, 0 AS selisih
UNION ALL SELECT 2, 'Ditambah STB Agustus',
       (SELECT SUM(stb) FROM #xl), ISNULL(@Stb,0),
       ISNULL(@Stb,0) - (SELECT SUM(stb) FROM #xl)
UNION ALL SELECT 3, 'Dikurangi kirim Agustus',
       (SELECT SUM(dlv) FROM #xl), ISNULL(@Krm,0),
       ISNULL(@Krm,0) - (SELECT SUM(dlv) FROM #xl)
UNION ALL SELECT 4, 'Ditambah retur Agustus',
       (SELECT SUM(ret) FROM #xl), ISNULL(@Ret,0),
       ISNULL(@Ret,0) - (SELECT SUM(ret) FROM #xl)
UNION ALL SELECT 5, '= SALDO AKHIR',
       @XlAkhir, @Sistem, @Sistem - @XlAkhir
ORDER BY urut;
GO

/* ---------------------------------------------------------------------------
   LANGKAH 3 — PER TANGGAL, SUPAYA KETAHUAN HARI MANA YANG MELESET
   Kolom cakupan_snapshot = angka yang benar-benar dipakai prosedur.
   Kolom seluruh_database = semua transaksi tanpa pembatas.
   Kalau dua kolom itu jauh berbeda, berarti banyak transaksi TIDAK ikut
   terhitung karena NO. OP-nya di luar cakupan snapshot.
   --------------------------------------------------------------------------- */
SELECT x.tgl AS tanggal,
       x.stb AS stb_excel,
       (SELECT SUM(ISNULL(b.nQty,0)) FROM dbo.tbStbBJ b
        WHERE CAST(b.dTanggal AS DATE) = x.tgl
          AND EXISTS (SELECT 1 FROM dbo.tbStokGudangSnap s WHERE s.cNoSc = RTRIM(b.cNoSc))) AS stb_cakupan_snapshot,
       (SELECT SUM(ISNULL(b.nQty,0)) FROM dbo.tbStbBJ b
        WHERE CAST(b.dTanggal AS DATE) = x.tgl) AS stb_seluruh_database,
       x.dlv AS dlv_excel,
       (SELECT SUM(ISNULL(d.nQty,0)) FROM dbo.tbSRJ s
        INNER JOIN dbo.tbSRJDtl d ON d.cNoSRJ = s.cNoSRJ
        WHERE CAST(s.dTanggal AS DATE) = x.tgl
          AND EXISTS (SELECT 1 FROM dbo.tbStokGudangSnap t
                      WHERE t.cNoSc = RTRIM(COALESCE(d.cNoScDtl, s.cNoSC)))) AS dlv_cakupan_snapshot,
       (SELECT SUM(ISNULL(d.nQty,0)) FROM dbo.tbSRJ s
        INNER JOIN dbo.tbSRJDtl d ON d.cNoSRJ = s.cNoSRJ
        WHERE CAST(s.dTanggal AS DATE) = x.tgl) AS dlv_seluruh_database
FROM   #xl x ORDER BY x.tgl;
GO

/* ---------------------------------------------------------------------------
   LANGKAH 4 — TRANSAKSI YANG TERBUANG DARI PERHITUNGAN
   Ini dugaan utama saya. Prosedur hanya menghitung NO. OP yang punya saldo
   awal Excel ATAU punya STB setelah cut-off. NO. OP yang cuma punya SURAT
   JALAN di Agustus tidak ikut, sehingga pengurangannya hilang dan stok
   sistem jadi lebih tinggi dari seharusnya di sisi itu — atau sebaliknya,
   barang yang di-STB tapi OP-nya sudah keburu nol ikut terbuang.
   --------------------------------------------------------------------------- */

-- 4a. Surat jalan Agustus yang NO. OP-nya tidak ada di snapshot
SELECT COUNT(DISTINCT RTRIM(COALESCE(d.cNoScDtl, s.cNoSC))) AS jml_op,
       SUM(ISNULL(d.nQty,0))                                AS pc_kirim_di_luar_snapshot
FROM   dbo.tbSRJ s INNER JOIN dbo.tbSRJDtl d ON d.cNoSRJ = s.cNoSRJ
WHERE  s.dTanggal > '2026-07-31'
  AND  NOT EXISTS (SELECT 1 FROM dbo.tbStokGudangSnap t
                   WHERE t.cNoSc = RTRIM(COALESCE(d.cNoScDtl, s.cNoSC)));

-- 4b. STB Agustus yang NO. OP-nya tidak ada di snapshot (karena stoknya nol)
SELECT COUNT(DISTINCT RTRIM(b.cNoSc)) AS jml_op,
       SUM(ISNULL(b.nQty,0))          AS pc_stb_di_luar_snapshot
FROM   dbo.tbStbBJ b
WHERE  b.dTanggal > '2026-07-31'
  AND  NOT EXISTS (SELECT 1 FROM dbo.tbStokGudangSnap s WHERE s.cNoSc = RTRIM(b.cNoSc));

-- 4c. 25 NO. OP dengan kirim terbesar yang terbuang dari perhitungan
SELECT TOP 25 RTRIM(COALESCE(d.cNoScDtl, s.cNoSC)) AS no_sc,
       MAX(RTRIM(s.cNama))    AS customer,
       SUM(ISNULL(d.nQty,0))  AS pc_kirim,
       MIN(CAST(s.dTanggal AS DATE)) AS kirim_pertama,
       CASE WHEN EXISTS (SELECT 1 FROM dbo.tbStokGudangExcel e
                         WHERE e.cNoScDb = RTRIM(COALESCE(d.cNoScDtl, s.cNoSC)))
            THEN 'ADA DI PATOKAN EXCEL' ELSE 'TIDAK ADA DI PATOKAN' END AS status
FROM   dbo.tbSRJ s INNER JOIN dbo.tbSRJDtl d ON d.cNoSRJ = s.cNoSRJ
WHERE  s.dTanggal > '2026-07-31'
  AND  NOT EXISTS (SELECT 1 FROM dbo.tbStokGudangSnap t
                   WHERE t.cNoSc = RTRIM(COALESCE(d.cNoScDtl, s.cNoSC)))
GROUP  BY RTRIM(COALESCE(d.cNoScDtl, s.cNoSC))
ORDER  BY SUM(ISNULL(d.nQty,0)) DESC;
GO

/* ---------------------------------------------------------------------------
   LANGKAH 5 — NO. OP DENGAN SELISIH TERBESAR, DILENGKAPI RINCIAN MUTASI
   Supaya bisa langsung dilihat: apakah bedanya di STB, di kirim, atau di
   saldo awalnya.
   --------------------------------------------------------------------------- */
SELECT TOP 30
       COALESCE(x.cNoScDb, s.cNoSc)     AS no_sc,
       COALESCE(x.cNama, s.cNama)       AS customer,
       ISNULL(e.nStokAkhirPc, 0)        AS saldo_awal_excel,
       ISNULL(x.nSaldoPc, 0)            AS akhir_excel,
       ISNULL(s.nStokPc, 0)             AS akhir_sistem,
       ISNULL(s.nStokPc,0) - ISNULL(x.nSaldoPc,0) AS selisih,
       ISNULL(m.stb, 0)                 AS stb_db_agustus,
       ISNULL(k.krm, 0)                 AS kirim_db_agustus
FROM       dbo.tbCekSaldoExcel x
FULL JOIN  dbo.tbStokGudangSnap s ON s.cNoSc = x.cNoScDb
LEFT  JOIN dbo.tbStokGudangExcel e ON e.cNoScDb = COALESCE(x.cNoScDb, s.cNoSc)
OUTER APPLY (SELECT SUM(ISNULL(nQty,0)) AS stb FROM dbo.tbStbBJ
             WHERE RTRIM(cNoSc) = COALESCE(x.cNoScDb, s.cNoSc)
               AND dTanggal > '2026-07-31') m
OUTER APPLY (SELECT SUM(ISNULL(d2.nQty,0)) AS krm
             FROM dbo.tbSRJ s2 INNER JOIN dbo.tbSRJDtl d2 ON d2.cNoSRJ = s2.cNoSRJ
             WHERE RTRIM(COALESCE(d2.cNoScDtl, s2.cNoSC)) = COALESCE(x.cNoScDb, s.cNoSc)
               AND s2.dTanggal > '2026-07-31') k
WHERE      ISNULL(x.nSaldoPc,0) <> ISNULL(s.nStokPc,0)
ORDER BY   ABS(ISNULL(s.nStokPc,0) - ISNULL(x.nSaldoPc,0)) DESC;
GO

DROP TABLE #xl;
GO
