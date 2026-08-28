/* ============================================================================
   PT SUPRACOR SEJAHTERA — PEMETAAN BENAR + MODEL STOK BARU
   Dibuat : 04 Agustus 2026

   TEMUAN 1 — POLA NOMOR
       Excel            : 2607/00965
       tbStbBJ.cNoSc    : SLC/2607/01328
       tbStbBJ.cNoOp    : SPS/2607/01328-B01
     Nomor di Excel ternyata cNoSc TANPA awalan 'SLC/'. Jadi pemetaannya:
         cNoSc database = 'SLC/' + nomor Excel

   TEMUAN 2 — RIWAYAT PENUH TIDAK BISA DIPAKAI
     Menjumlahkan seluruh riwayat menghasilkan 112.983 OP dengan 32.614 di
     antaranya minus, dan 276 juta pc kirim yang tidak punya catatan STB sama
     sekali. Pencatatan lama (SLC/0705 s/d SLC/1xxx, tahun 2007-2019) memang
     tidak lengkap. report_backend.php tidak pernah kena karena selalu dibatasi
     rentang tanggal SC.

   MODEL BARU — SESUAI ARAHAN "LANGSUNG PAKAI YANG DI EXCEL"
       stok hari ini = saldo Excel per cut-off
                     + STB setelah cut-off
                     - kirim setelah cut-off
                     + retur setelah cut-off
                     - penyesuaian gudang setelah cut-off
     Riwayat sebelum cut-off tidak dibaca sama sekali. Selain jadi benar,
     ini juga jauh lebih ringan karena yang dibaca cuma transaksi beberapa
     hari terakhir, bukan 19 tahun.

   File ini hanya menyentuh tabel buatan sendiri. tbStbBJ, tbSRJ, tbSRJDtl,
   tbDtStockDtl, tbSC tetap read-only.
   ============================================================================ */

USE dbSopanusa;
GO
SET NOCOUNT ON;
SET TRANSACTION ISOLATION LEVEL READ UNCOMMITTED;
GO

/* ---------------------------------------------------------------------------
   LANGKAH 1 — PERBAIKI PEMETAAN NOMOR
   --------------------------------------------------------------------------- */
IF COL_LENGTH('dbo.tbStokGudangExcel', 'cNoOpExcel') IS NULL
    ALTER TABLE dbo.tbStokGudangExcel ADD cNoOpExcel VARCHAR(30) NULL;
GO
IF COL_LENGTH('dbo.tbStokGudangExcel', 'cNoScDb') IS NULL
    ALTER TABLE dbo.tbStokGudangExcel ADD cNoScDb VARCHAR(30) NULL;
GO

UPDATE dbo.tbStokGudangExcel SET cNoOpExcel = RTRIM(cNoSc) WHERE cNoOpExcel IS NULL;
GO

-- Pemetaan yang benar: tambahkan awalan 'SLC/'
UPDATE dbo.tbStokGudangExcel
SET    cNoScDb = 'SLC/' + RTRIM(cNoOpExcel);
GO

-- Kosongkan lagi yang ternyata tidak ada di tbStbBJ, supaya ketahuan
UPDATE e
SET    e.cNoScDb = NULL
FROM   dbo.tbStokGudangExcel e
WHERE  NOT EXISTS (SELECT 1 FROM dbo.tbStbBJ b WHERE RTRIM(b.cNoSc) = e.cNoScDb);
GO

-- 1a. HASIL. Target: ketemu mendekati 291, belum_ketemu kecil.
SELECT COUNT(*)                                             AS total,
       SUM(CASE WHEN cNoScDb IS NOT NULL THEN 1 ELSE 0 END) AS ketemu,
       SUM(CASE WHEN cNoScDb IS NULL     THEN 1 ELSE 0 END) AS belum_ketemu,
       SUM(CASE WHEN cNoScDb IS NULL THEN nStokAkhirPc ELSE 0 END) AS pc_belum_ketemu
FROM   dbo.tbStokGudangExcel;

-- 1b. Yang belum ketemu, untuk dicek gudang
SELECT cNoOpExcel, cNama, cNamabrg, nStokAkhirPc, cKeterangan
FROM   dbo.tbStokGudangExcel
WHERE  cNoScDb IS NULL
ORDER  BY nStokAkhirPc DESC;

-- 1c. Contoh pemetaan berhasil
SELECT TOP 10 cNoOpExcel, cNoScDb, cNama, nStokAkhirPc
FROM   dbo.tbStokGudangExcel
WHERE  cNoScDb IS NOT NULL
ORDER  BY nStokAkhirPc DESC;
GO

/* ---------------------------------------------------------------------------
   LANGKAH 2 — CEK KOLOM vwReturnSrj
   Model baru menyaring retur memakai tanggal surat jalan (tbSRJ.dTanggal).
   Kalau view ini ternyata punya kolom tanggal retur sendiri, kirim hasil
   query ini supaya penyaringnya bisa dibuat lebih tepat.
   --------------------------------------------------------------------------- */
SELECT COLUMN_NAME, DATA_TYPE
FROM   INFORMATION_SCHEMA.COLUMNS
WHERE  TABLE_NAME = 'vwReturnSrj'
ORDER  BY ORDINAL_POSITION;
GO

/* ---------------------------------------------------------------------------
   LANGKAH 3 — PROSEDUR BARU BERBASIS SALDO EXCEL
   --------------------------------------------------------------------------- */
IF OBJECT_ID('dbo.spRefreshStokGudang') IS NOT NULL DROP PROCEDURE dbo.spRefreshStokGudang;
GO

CREATE PROCEDURE dbo.spRefreshStokGudang
    @Sumber    VARCHAR(30) = 'MANUAL',
    @HariTrend INT         = 30
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

        /* 5. Tambah retur setelah cut-off (disaring pakai tanggal surat jalan) */
        UPDATE a SET a.nStok = a.nStok + x.q
        FROM   #agg a
        INNER JOIN (SELECT RTRIM(COALESCE(d.cNoScDtl, s.cNoSC)) AS sc, SUM(ISNULL(rv.nQty,0)) AS q
                    FROM   dbo.vwReturnSrj rv
                    INNER JOIN dbo.tbSRJDtl d ON d.cNoSRJ = rv.cNoSrj
                    INNER JOIN dbo.tbSRJ    s ON s.cNoSRJ = d.cNoSRJ
                    WHERE  s.dTanggal > @CutOff
                    GROUP  BY RTRIM(COALESCE(d.cNoScDtl, s.cNoSC))) x ON x.sc = a.cNoSc;

        /* 6. Kurangi penyesuaian modul gudang setelah cut-off */
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
               nBerat, dTglStbAkhir, nUmur, nStokPc, nStokKg, cKeterangan, lDariExcel)
        SELECT cNoSc, cKodeCust, cNama, cNamabrg, cNoMC, cNamaSales, cType, cRak,
               nBerat, dTglStbAkhir, nUmur, nStokPc, nStokKg, cKeterangan, lDariExcel
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
   LANGKAH 4 — JALANKAN & PERIKSA
   --------------------------------------------------------------------------- */
EXEC dbo.spRefreshStokGudang @Sumber = 'MODELBARU';
GO

-- 4a. Harus SUKSES. nJmlOp wajar (ratusan), nTotalPc dekat 776.500 + produksi baru.
SELECT TOP 5 nId, dMulai, nDetik, nJmlOp, nTotalPc, cStatus, cSumber, cPesan
FROM   dbo.tbStokGudangLog ORDER BY nId DESC;

-- 4b. Ringkasan
SELECT COUNT(*) AS jml_op,
       SUM(CASE WHEN nStokPc > 0 THEN nStokPc ELSE 0 END) AS total_pc,
       SUM(CASE WHEN nStokPc < 0 THEN 1 ELSE 0 END)       AS op_negatif,
       SUM(CASE WHEN lDariExcel = 1 THEN 1 ELSE 0 END)    AS op_dari_excel,
       SUM(CASE WHEN lDariExcel = 0 THEN 1 ELSE 0 END)    AS op_produksi_baru
FROM   dbo.tbStokGudangSnap;

-- 4c. Cocokkan dengan Excel. Selisih HANYA boleh berasal dari transaksi
--     setelah 03 Agustus 2026 (STB masuk / surat jalan keluar).
SELECT TOP 30 e.cNoOpExcel, e.cNama, e.nStokAkhirPc AS excel,
       ISNULL(s.nStokPc, 0) AS sistem,
       ISNULL(s.nStokPc, 0) - e.nStokAkhirPc AS selisih
FROM      dbo.tbStokGudangExcel e
LEFT JOIN dbo.tbStokGudangSnap  s ON s.cNoSc = e.cNoScDb
WHERE     e.cNoScDb IS NOT NULL
  AND     ISNULL(s.nStokPc, 0) <> e.nStokAkhirPc
ORDER BY  ABS(ISNULL(s.nStokPc, 0) - e.nStokAkhirPc) DESC;

-- 4d. Stok minus, kalau ada
SELECT TOP 20 cNoSc, cNama, cNamabrg, nStokPc
FROM   dbo.tbStokGudangSnap WHERE nStokPc < 0 ORDER BY nStokPc;
GO

/* ---------------------------------------------------------------------------
   CATATAN — tbStokGudangAdj DAN PATCH report_backend.php
   Model baru ini TIDAK memakai tbStokGudangAdj. Dashboard sudah benar tanpa
   tabel itu. Tapi patch adj_agg di report_backend.php tetap dibutuhkan kalau
   laporan SLC juga mau ikut terkoreksi — dan isinya harus dihitung ulang
   memakai cNoScDb, karena isi sekarang memakai nomor yang salah.

   Sementara ini KOSONGKAN saja supaya laporan SLC tidak ikut salah:
   --------------------------------------------------------------------------- */
-- TRUNCATE TABLE dbo.tbStokGudangAdj;
-- GO
