/* ============================================================================
   PT SUPRACOR SEJAHTERA — PEMBERSIHAN AKHIR MODEL STOK
   Dibuat : 05 Agustus 2026

   KONDISI SEKARANG (hasil uji jam 08:36)
     366 NO. OP  |  1.167.133 pc  |  refresh 1 detik  |  18 OP minus (-59.715 pc)

   TIGA HAL YANG DIBERESKAN FILE INI

   1. DUA BARIS PART+LAYER BELUM TERPETAKAN
      2606/01142-P0101 dan 2606/01150-P0101 memakai akhiran nomor part.
      cNoSc di database tidak memakai akhiran itu, jadi tinggal dipotong.

   2. PENYARING tbDtStockDtl TERNYATA KELIRU
      Tabel itu tidak punya kolom tanggal transaksi, cuma userdate (kapan baris
      terakhir disimpan). Buktinya SLC/2607/00080: saldo Excel 2.000, tidak ada
      STB maupun kirim setelah cut-off, tapi hasilnya -3.660 — terpotong 5.660
      dari langkah ini. Sekarang DIMATIKAN secara default.

   3. STOK MINUS DIBERI KETERANGAN, BUKAN DISEMBUNYIKAN
      Kolom cStatusData baru memisahkan dua sebab yang berbeda penanganannya.

   TEMUAN DARI PENGECEKAN FILE EXCEL — INI BUKAN MASALAH QUERY

   Pola A — SURAT JALAN MENYUSUL
     SLC/2607/00487 SUN PAPER: Excel mencatat barang keluar 1.620 pc pada
     01 Agustus dan saldo akhirnya 0. Tapi di database surat jalannya bertanggal
     SETELAH 03 Agustus. Jadi barang yang sama dikurangi dua kali — sekali oleh
     gudang di Excel, sekali lagi oleh surat jalan.
     -> Perlu dibahas dengan bagian pengiriman soal tanggal surat jalan.

   Pola B — TIDAK PERNAH TERCATAT DI GUDANG
     SLC/2607/00018 KEBUN TEBU MAS ada di sheet BOX tapi semua kolomnya nol,
     padahal database mencatat STB 5.650 dan kirim 18.500 setelah cut-off.
     Item "SHEET - ..." bahkan tidak ada sama sekali di sheet BOX maupun
     PART+LAYER.
     -> Perlu dipastikan gudang: apakah barang ini memang langsung kirim tanpa
        masuk gudang, atau ada kategori yang belum ikut dipantau.
   ============================================================================ */

USE dbSopanusa;
GO
SET NOCOUNT ON;
GO

/* ---------------------------------------------------------------------------
   LANGKAH 1 — PETAKAN DUA BARIS PART+LAYER YANG TERSISA
   --------------------------------------------------------------------------- */
UPDATE dbo.tbStokGudangExcel
SET    cNoScDb = 'SLC/' + LEFT(RTRIM(cNoOpExcel), CHARINDEX('-', RTRIM(cNoOpExcel) + '-') - 1)
WHERE  cNoScDb IS NULL AND CHARINDEX('-', cNoOpExcel) > 0;
GO

UPDATE e SET e.cNoScDb = NULL
FROM   dbo.tbStokGudangExcel e
WHERE  e.cNoScDb IS NOT NULL
  AND  NOT EXISTS (SELECT 1 FROM dbo.tbStbBJ b WHERE RTRIM(b.cNoSc) = e.cNoScDb);
GO

-- Target: belum_ketemu = 0 untuk kedua kategori
SELECT cKategori, COUNT(*) AS baris,
       SUM(CASE WHEN cNoScDb IS NULL THEN 1 ELSE 0 END) AS belum_ketemu,
       SUM(nStokAkhirPc) AS total_pc
FROM   dbo.tbStokGudangExcel GROUP BY cKategori;
GO

/* ---------------------------------------------------------------------------
   LANGKAH 2 — CEK SEBERAPA BESAR PENGARUH tbDtStockDtl
   Kalau angkanya besar dan gudang memang aktif memakai modul ini, hubungi saya
   supaya penyaringnya dibuat ulang memakai kolom yang tepat.
   --------------------------------------------------------------------------- */
SELECT COUNT(*) AS baris_userdate_stlh_cutoff,
       COUNT(DISTINCT cNoSc) AS jml_op,
       SUM(ISNULL(nStock,0)) AS total_nstock
FROM   dbo.tbDtStockDtl WHERE userdate > '2026-08-03';

SELECT TOP 15 cNoSc, cJnsDt, nStock, nQty, nQtySrj, userdate, cKetDtl
FROM   dbo.tbDtStockDtl WHERE userdate > '2026-08-03' ORDER BY ABS(ISNULL(nStock,0)) DESC;
GO

/* ---------------------------------------------------------------------------
   LANGKAH 3 — TAMBAH KOLOM KETERANGAN STATUS DI SNAPSHOT
   --------------------------------------------------------------------------- */
IF COL_LENGTH('dbo.tbStokGudangSnap', 'cStatusData') IS NULL
    ALTER TABLE dbo.tbStokGudangSnap ADD cStatusData VARCHAR(40) NULL;
GO

/* ---------------------------------------------------------------------------
   LANGKAH 4 — PROSEDUR VERSI FINAL
   --------------------------------------------------------------------------- */
IF OBJECT_ID('dbo.spRefreshStokGudang') IS NOT NULL DROP PROCEDURE dbo.spRefreshStokGudang;
GO

CREATE PROCEDURE dbo.spRefreshStokGudang
    @Sumber       VARCHAR(30) = 'MANUAL',
    @HariTrend    INT         = 30,
    -- tbDtStockDtl tidak punya kolom tanggal transaksi, hanya userdate (kapan
    -- baris terakhir disimpan). Memakainya sebagai penyaring terbukti keliru:
    -- SLC/2607/00080 saldo Excel 2.000 tapi ikut terpotong 5.660. Karena itu
    -- default-nya MATI. Nyalakan hanya kalau sudah diverifikasi gudang.
    @PakaiDtStock BIT         = 0
AS
BEGIN
    SET NOCOUNT ON;
    SET TRANSACTION ISOLATION LEVEL READ UNCOMMITTED;   -- tidak mengunci input gudang

    DECLARE @Mulai DATETIME = GETDATE(), @Id INT, @CutOff DATE;
    INSERT INTO dbo.tbStokGudangLog (dMulai, cStatus, cSumber) VALUES (@Mulai, 'JALAN', @Sumber);
    SET @Id = SCOPE_IDENTITY();

    BEGIN TRY
        SELECT @CutOff = MAX(dCutOff) FROM dbo.tbStokGudangExcel;
        IF @CutOff IS NULL
            THROW 50010, 'tbStokGudangExcel kosong. Isi dulu saldo gudang dari file Excel.', 1;

        CREATE TABLE #agg (cNoSc VARCHAR(30) PRIMARY KEY, nStok INT NOT NULL DEFAULT 0);

        /* 1. Saldo awal dari file Excel gudang (patokan utama) */
        INSERT INTO #agg (cNoSc, nStok)
        SELECT RTRIM(cNoScDb), SUM(nStokAkhirPc)
        FROM   dbo.tbStokGudangExcel
        WHERE  cNoScDb IS NOT NULL
        GROUP  BY RTRIM(cNoScDb);

        /* 2. NO. OP baru yang mulai berproduksi setelah cut-off */
        INSERT INTO #agg (cNoSc, nStok)
        SELECT DISTINCT RTRIM(b.cNoSc), 0
        FROM   dbo.tbStbBJ b
        WHERE  b.dTanggal > @CutOff
          AND  b.cNoSc IS NOT NULL AND LTRIM(RTRIM(b.cNoSc)) <> ''
          AND  NOT EXISTS (SELECT 1 FROM #agg a WHERE a.cNoSc = RTRIM(b.cNoSc));

        /* 3. Tambah setoran barang jadi setelah cut-off */
        UPDATE a SET a.nStok = a.nStok + x.q
        FROM   #agg a
        INNER JOIN (SELECT RTRIM(cNoSc) AS sc, SUM(ISNULL(nQty,0)) AS q
                    FROM   dbo.tbStbBJ WHERE dTanggal > @CutOff
                    GROUP  BY RTRIM(cNoSc)) x ON x.sc = a.cNoSc;

        /* 4. Kurangi pengiriman setelah cut-off */
        UPDATE a SET a.nStok = a.nStok - x.q
        FROM   #agg a
        INNER JOIN (SELECT RTRIM(COALESCE(d.cNoScDtl, s.cNoSC)) AS sc, SUM(ISNULL(d.nQty,0)) AS q
                    FROM   dbo.tbSRJ s
                    INNER JOIN dbo.tbSRJDtl d ON d.cNoSRJ = s.cNoSRJ
                    WHERE  s.dTanggal > @CutOff
                    GROUP  BY RTRIM(COALESCE(d.cNoScDtl, s.cNoSC))) x ON x.sc = a.cNoSc;

        /* 5. Tambah retur setelah cut-off.
              Dibaca LANGSUNG dari vwReturnSrj memakai kolom cNoSc dan dTgl
              miliknya sendiri. Versi lama menjoin ke tbSRJDtl, padahal satu
              surat jalan punya banyak baris detail, sehingga qty retur
              terganda sebanyak jumlah baris detail surat jalan itu. */
        UPDATE a SET a.nStok = a.nStok + x.q
        FROM   #agg a
        INNER JOIN (SELECT RTRIM(rv.cNoSc) AS sc, SUM(ISNULL(rv.nQty,0)) AS q
                    FROM   dbo.vwReturnSrj rv
                    WHERE  rv.dTgl > @CutOff
                      AND  rv.cNoSc IS NOT NULL AND LTRIM(RTRIM(rv.cNoSc)) <> ''
                    GROUP  BY RTRIM(rv.cNoSc)) x ON x.sc = a.cNoSc;

        /* 6. Penyesuaian modul gudang setelah cut-off — OPSIONAL, lihat @PakaiDtStock */
        IF @PakaiDtStock = 1
            UPDATE a SET a.nStok = a.nStok - x.q
            FROM   #agg a
            INNER JOIN (SELECT RTRIM(cNoSc) AS sc, SUM(ISNULL(nStock,0)) AS q
                        FROM   dbo.tbDtStockDtl WHERE userdate > @CutOff
                        GROUP  BY RTRIM(cNoSc)) x ON x.sc = a.cNoSc;

        /* 7. Buang yang habis. Stok minus dipertahankan sebagai penanda
              data yang perlu diperiksa gudang. */
        DELETE FROM #agg WHERE nStok = 0;

        BEGIN TRANSACTION;

        TRUNCATE TABLE dbo.tbStokGudangSnap;

        INSERT INTO dbo.tbStokGudangSnap
              (cNoSc, cKodeCust, cNama, cNamabrg, cNoMC, cNamaSales, cType, cRak,
               nBerat, dTglStbAkhir, nUmur, nStokPc, nStokKg, cKeterangan, lDariExcel,
               cStatusData)
        SELECT cNoSc, cKodeCust, cNama, cNamabrg, cNoMC, cNamaSales, cType, cRak,
               nBerat, dTglStbAkhir, nUmur, nStokPc, nStokKg, cKeterangan, lDariExcel,
               CASE WHEN nStokPc >= 0            THEN 'NORMAL'
                    WHEN lDariExcel = 0          THEN 'MINUS - TIDAK ADA PATOKAN GUDANG'
                    ELSE                              'MINUS - KIRIM MELEBIHI PATOKAN'
               END
        FROM (
            SELECT LEFT(a.cNoSc, 30)                                            AS cNoSc,
                   LEFT(ISNULL(d.cKodeCust, ''), 50)                            AS cKodeCust,
                   LEFT(ISNULL(NULLIF(d.cNama, ''), ISNULL(e.cNama, '')), 300)   AS cNama,
                   LEFT(ISNULL(NULLIF(d.cNamabrg, ''), ISNULL(e.cNamabrg,'')), 500) AS cNamabrg,
                   LEFT(ISNULL(d.cNoMC, ''), 100)      AS cNoMC,
                   LEFT(ISNULL(d.cNamaSales, ''), 200) AS cNamaSales,
                   LEFT(ISNULL(d.cType, ''), 100)      AS cType,
                   LEFT(ISNULL(d.cRak, ''), 100)       AS cRak,
                   ISNULL(d.nberat, 0)                                          AS nBerat,
                   CAST(d.dTanggal AS DATE)                                     AS dTglStbAkhir,
                   DATEDIFF(day, ISNULL(d.dTanggal, e.dProduksiAkhir), CAST(GETDATE() AS DATE)) AS nUmur,
                   a.nStok                                                      AS nStokPc,
                   CAST(a.nStok * ISNULL(d.nberat, 0) AS DECIMAL(18,3))         AS nStokKg,
                   LEFT(e.cKeterangan, 255)                                     AS cKeterangan,
                   CASE WHEN e.cNoSc IS NULL THEN 0 ELSE 1 END                  AS lDariExcel,
                   ROW_NUMBER() OVER (PARTITION BY a.cNoSc ORDER BY (SELECT 1)) AS rn
            FROM      #agg a
            OUTER APPLY (
                SELECT TOP 1 x.cNoSc, x.cNama, x.cNamabrg, x.cKeterangan, x.dProduksiAkhir
                FROM   dbo.tbStokGudangExcel x
                WHERE  RTRIM(x.cNoScDb) = a.cNoSc
                ORDER  BY x.nStokAkhirPc DESC
            ) e
            OUTER APPLY (
                SELECT TOP 1 s.cKodeCust, s.cNama, s.cNamabrg, s.cNoMC, s.cNamaSales,
                             s.cType, s.cRak, s.nberat, s.dTanggal
                FROM   dbo.tbStbBJ s
                WHERE  s.cNoSc = a.cNoSc
                ORDER  BY s.dTanggal DESC, s.cNoSTB DESC
            ) d
        ) src
        WHERE src.rn = 1;

        /* 8. Mutasi harian untuk grafik dashboard */
        DECLARE @Awal  DATE = DATEADD(day, -(@HariTrend - 1), CAST(GETDATE() AS DATE));
        DECLARE @Akhir DATE = DATEADD(day, 1, CAST(GETDATE() AS DATE));

        TRUNCATE TABLE dbo.tbStokGudangMutasi;

        ;WITH berat_sc AS (
            SELECT RTRIM(cNoSc) AS cNoSc, MAX(ISNULL(nberat,0)) AS nberat
            FROM   dbo.tbStbBJ WHERE dTanggal >= DATEADD(year, -2, @Awal)
            GROUP  BY RTRIM(cNoSc)
        ),
        stb_h AS (
            SELECT CAST(dTanggal AS DATE) AS d,
                   SUM(ISNULL(nQty,0)) AS pc, SUM(ISNULL(nQtyKg,0)) AS kg
            FROM   dbo.tbStbBJ
            WHERE  dTanggal >= @Awal AND dTanggal < @Akhir
            GROUP  BY CAST(dTanggal AS DATE)
        ),
        krm_h AS (
            SELECT CAST(s.dTanggal AS DATE) AS d,
                   SUM(ISNULL(dt.nQty,0)) AS pc,
                   SUM(ISNULL(dt.nQty,0) * ISNULL(bs.nberat,0)) AS kg
            FROM   dbo.tbSRJ s
            INNER JOIN dbo.tbSRJDtl dt ON dt.cNoSRJ = s.cNoSRJ
            LEFT  JOIN berat_sc     bs ON bs.cNoSc  = RTRIM(COALESCE(dt.cNoScDtl, s.cNoSC))
            WHERE  s.dTanggal >= @Awal AND s.dTanggal < @Akhir
            GROUP  BY CAST(s.dTanggal AS DATE)
        ),
        kalender AS (
            SELECT @Awal AS d
            UNION ALL SELECT DATEADD(day, 1, d) FROM kalender WHERE d < CAST(GETDATE() AS DATE)
        )
        INSERT INTO dbo.tbStokGudangMutasi (dTanggal, nStbPc, nStbKg, nKirimPc, nKirimKg)
        SELECT k.d, ISNULL(sh.pc,0), CAST(ISNULL(sh.kg,0) AS DECIMAL(18,3)),
                    ISNULL(kh.pc,0), CAST(ISNULL(kh.kg,0) AS DECIMAL(18,3))
        FROM      kalender k
        LEFT JOIN stb_h sh ON sh.d = k.d
        LEFT JOIN krm_h kh ON kh.d = k.d
        OPTION (MAXRECURSION 366);

        COMMIT TRANSACTION;

        UPDATE dbo.tbStokGudangLog
        SET    dSelesai = GETDATE(),
               nDetik   = DATEDIFF(second, @Mulai, GETDATE()),
               nJmlOp   = (SELECT COUNT(*) FROM dbo.tbStokGudangSnap),
               nTotalPc = (SELECT ISNULL(SUM(nStokPc),0) FROM dbo.tbStokGudangSnap),
               cStatus  = 'SUKSES',
               cPesan   = 'Cut-off saldo Excel: ' + CONVERT(VARCHAR(10), @CutOff, 23)
        WHERE  nId = @Id;

        DROP TABLE #agg;
    END TRY
    BEGIN CATCH
        IF @@TRANCOUNT > 0 ROLLBACK TRANSACTION;
        UPDATE dbo.tbStokGudangLog
        SET    dSelesai = GETDATE(),
               nDetik   = DATEDIFF(second, @Mulai, GETDATE()),
               cStatus  = 'GAGAL',
               cPesan   = ERROR_MESSAGE()
        WHERE  nId = @Id;
        THROW;
    END CATCH
END
GO

/* ---------------------------------------------------------------------------
   LANGKAH 5 — JALANKAN & PERIKSA
   --------------------------------------------------------------------------- */
EXEC dbo.spRefreshStokGudang @Sumber = 'FINAL';
GO

-- 5a. Ringkasan per status. "NORMAL" harus mendominasi.
SELECT cStatusData, COUNT(*) AS jml_op, SUM(nStokPc) AS total_pc
FROM   dbo.tbStokGudangSnap GROUP BY cStatusData ORDER BY jml_op DESC;

-- 5b. Total keseluruhan
SELECT TOP 3 nId, nDetik, nJmlOp, nTotalPc, cStatus, cPesan
FROM   dbo.tbStokGudangLog ORDER BY nId DESC;

-- 5c. DAFTAR UNTUK GUDANG — stok minus yang perlu dicocokkan fisik
SELECT cNoSc, cNama, cNamabrg, nStokPc, cStatusData
FROM   dbo.tbStokGudangSnap WHERE nStokPc < 0 ORDER BY nStokPc;
GO
