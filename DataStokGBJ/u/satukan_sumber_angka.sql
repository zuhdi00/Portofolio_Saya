/* ============================================================================
   PT SUPRACOR SEJAHTERA — SATU SUMBER ANGKA UNTUK KEDUA TABEL
   Dibuat : 10 Agustus 2026

   MASALAH
     Setelah tanggal disamakan, masih ada selisih:
        tbStokGudangSnap  1.430.854 pc   494 baris
        tbStokSnapTipe    1.291.125 pc   563 baris
        Selisih            -139.729 pc

   PENYEBABNYA BUKAN TANGGAL, TAPI CAKUPAN
     spRefreshStokTipe memasukkan NO. OP yang punya surat jalan saja, walau
     tanpa saldo awal dan tanpa STB. spRefreshStokGudang tidak memasukkannya.
     OP semacam itu stoknya minus, jadi tabel tipe totalnya lebih rendah.

     Pembatas di spRefreshStokGudang dulu dipasang untuk menangkal data lama
     tahun 2007 yang tidak lengkap. Sekarang patokannya 31 Juli 2026, jadi
     yang dihitung hanya transaksi Agustus. Pembatas itu tidak lagi berguna,
     dan justru membuang pengiriman yang sah.

   PERBAIKAN — TIDAK MENAMBAL, TAPI MENYATUKAN
     tbStokGudangSnap sekarang DITURUNKAN dari tbStokSnapTipe, bukan dihitung
     ulang dengan rumus terpisah. Angka per NO. OP adalah penjumlahan tipenya.
     Dengan begitu dua tabel itu mustahil berbeda lagi, bukan karena kebetulan
     cocok tapi karena memang satu sumber.

        spRefreshSemuaStok
            1. spRefreshStokTipe     -> hitung per NO. OP + tipe
            2. spRefreshStokGudang   -> jumlahkan jadi per NO. OP, lalu
                                        lengkapi umur, keterangan, status

   Keterangan manual dari tbStokGudangKet menang atas keterangan file Excel,
   sehingga perubahan lewat dashboard tidak tertimpa saat ganti patokan.
   ============================================================================ */

USE dbSopanusa;
GO
SET NOCOUNT ON;
GO

/* ---------------------------------------------------------------------------
   LANGKAH 1 — ARSIPKAN PROSEDUR LAMA, BARANGKALI PERLU DIBANDINGKAN
   --------------------------------------------------------------------------- */
IF OBJECT_ID('dbo.tbArsipProsedur') IS NULL
    CREATE TABLE dbo.tbArsipProsedur (
        cNama VARCHAR(100), dArsip DATETIME DEFAULT GETDATE(), cIsi NVARCHAR(MAX));
GO
IF NOT EXISTS (SELECT 1 FROM dbo.tbArsipProsedur WHERE cNama = 'spRefreshStokGudang')
    INSERT INTO dbo.tbArsipProsedur (cNama, cIsi)
    SELECT 'spRefreshStokGudang', OBJECT_DEFINITION(OBJECT_ID('dbo.spRefreshStokGudang'));
GO

/* ---------------------------------------------------------------------------
   LANGKAH 2 — spRefreshStokGudang VERSI TURUNAN
   --------------------------------------------------------------------------- */
IF OBJECT_ID('dbo.spRefreshStokGudang') IS NOT NULL DROP PROCEDURE dbo.spRefreshStokGudang;
GO

CREATE PROCEDURE dbo.spRefreshStokGudang
    @Sumber    VARCHAR(30) = 'MANUAL',
    @HariTrend INT         = 30
AS
BEGIN
    SET NOCOUNT ON;
    SET TRANSACTION ISOLATION LEVEL READ UNCOMMITTED;

    DECLARE @Mulai DATETIME = GETDATE(), @Id INT, @Cut DATE;
    INSERT INTO dbo.tbStokGudangLog (dMulai, cStatus, cSumber) VALUES (@Mulai, 'JALAN', @Sumber);
    SET @Id = SCOPE_IDENTITY();

    BEGIN TRY
        SELECT @Cut = MAX(dCutOff) FROM dbo.tbStokGudangExcel;
        IF @Cut IS NULL
            THROW 50010, 'tbStokGudangExcel kosong. Isi dulu patokan gudang dari file Excel.', 1;

        IF NOT EXISTS (SELECT 1 FROM dbo.tbStokSnapTipe)
            THROW 50011, 'tbStokSnapTipe kosong. Jalankan spRefreshStokTipe lebih dulu, atau pakai spRefreshSemuaStok.', 1;

        BEGIN TRANSACTION;

        TRUNCATE TABLE dbo.tbStokGudangSnap;

        /* Angka stok = penjumlahan tipe. Satu-satunya sumber. */
        INSERT INTO dbo.tbStokGudangSnap
              (cNoSc, cKodeCust, cNama, cNamabrg, cNoMC, cNamaSales, cType, cRak,
               nBerat, dTglStbAkhir, nUmur, nStokPc, nStokKg, cKeterangan,
               lDariExcel, cStatusData)
        SELECT t.cNoSc,
               LEFT(ISNULL(d.cKodeCust, ''), 50),
               LEFT(ISNULL(NULLIF(t.cNama, ''), ISNULL(d.cNama, '')), 300),
               LEFT(ISNULL(NULLIF(t.cNamabrg, ''), ISNULL(d.cNamabrg, '')), 500),
               LEFT(ISNULL(d.cNoMC, ''), 100),
               LEFT(ISNULL(d.cNamaSales, ''), 200),
               LEFT(ISNULL(d.cType, ''), 100),
               LEFT(ISNULL(d.cRak, ''), 100),
               ISNULL(d.nberat, 0),
               CAST(d.dTanggal AS DATE),
               DATEDIFF(day, d.dTanggal, CAST(GETDATE() AS DATE)),
               t.pc,
               t.kg,
               /* keterangan manual menang atas keterangan file Excel */
               LEFT(ISNULL(k.cKeterangan, e.cKeterangan), 255),
               CASE WHEN e.cNoSc IS NULL THEN 0 ELSE 1 END,
               CASE WHEN t.pc >= 0        THEN 'NORMAL'
                    WHEN e.cNoSc IS NULL  THEN 'MINUS - TIDAK ADA PATOKAN GUDANG'
                    ELSE                       'MINUS - KIRIM MELEBIHI PATOKAN'
               END
        FROM ( SELECT cNoSc,
                      SUM(nStokPc) AS pc,
                      CAST(SUM(nStokKg) AS DECIMAL(18,3)) AS kg,
                      MAX(cNama)    AS cNama,
                      MAX(cNamabrg) AS cNamabrg
               FROM   dbo.tbStokSnapTipe
               GROUP  BY cNoSc
               HAVING SUM(nStokPc) <> 0 ) t
        OUTER APPLY (SELECT TOP 1 x.cNoSc, x.cKeterangan
                     FROM   dbo.tbStokGudangExcel x
                     WHERE  x.cNoScDb = t.cNoSc
                     ORDER  BY x.nStokAkhirPc DESC) e
        OUTER APPLY (SELECT TOP 1 z.cKeterangan
                     FROM   dbo.tbStokGudangKet z
                     WHERE  z.cNoSc = t.cNoSc) k
        OUTER APPLY (SELECT TOP 1 y.cKodeCust, y.cNama, y.cNamabrg, y.cNoMC, y.cNamaSales,
                            y.cType, y.cRak, y.nberat, y.dTanggal
                     FROM   dbo.tbStbBJ y
                     WHERE  y.cNoSc = t.cNoSc
                     ORDER  BY y.dTanggal DESC, y.cNoSTB DESC) d;

        /* Mutasi harian untuk grafik */
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
            FROM   dbo.tbStbBJ WHERE dTanggal >= @Awal AND dTanggal < @Akhir
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
               cPesan   = 'Diturunkan dari tbStokSnapTipe. Cut-off ' + CONVERT(VARCHAR(10), @Cut, 23)
        WHERE  nId = @Id;
    END TRY
    BEGIN CATCH
        IF @@TRANCOUNT > 0 ROLLBACK TRANSACTION;
        UPDATE dbo.tbStokGudangLog
        SET    dSelesai = GETDATE(),
               nDetik   = DATEDIFF(second, @Mulai, GETDATE()),
               cStatus  = 'GAGAL', cPesan = ERROR_MESSAGE()
        WHERE  nId = @Id;
        THROW;
    END CATCH
END
GO

/* ---------------------------------------------------------------------------
   LANGKAH 3 — URUTANNYA WAJIB: TIPE DULU, BARU SNAPSHOT UTAMA
   --------------------------------------------------------------------------- */
IF OBJECT_ID('dbo.spRefreshSemuaStok') IS NOT NULL DROP PROCEDURE dbo.spRefreshSemuaStok;
GO
CREATE PROCEDURE dbo.spRefreshSemuaStok
    @Sumber VARCHAR(30) = 'JOB'
AS
BEGIN
    SET NOCOUNT ON;
    EXEC dbo.spRefreshStokTipe;                       -- 1. per NO. OP + tipe
    EXEC dbo.spRefreshStokGudang @Sumber = @Sumber;   -- 2. dijumlahkan per NO. OP
END
GO

EXEC dbo.spRefreshSemuaStok @Sumber = 'SATUKAN';
GO

/* ---------------------------------------------------------------------------
   LANGKAH 4 — PERIKSA. Kolom selisih HARUS nol.
   --------------------------------------------------------------------------- */
SELECT (SELECT SUM(nStokPc) FROM dbo.tbStokGudangSnap) AS total_snap,
       (SELECT SUM(nStokPc) FROM dbo.tbStokSnapTipe)   AS total_tipe,
       (SELECT SUM(nStokPc) FROM dbo.tbStokSnapTipe)
     - (SELECT SUM(nStokPc) FROM dbo.tbStokGudangSnap) AS selisih;

-- NO. OP yang berbeda antara dua tabel. Harus 0 baris.
SELECT TOP 20 COALESCE(a.cNoSc, b.cNoSc) AS cNoSc,
       ISNULL(a.nStokPc, 0) AS snap, ISNULL(b.pc, 0) AS tipe,
       ISNULL(b.pc,0) - ISNULL(a.nStokPc,0) AS beda
FROM      dbo.tbStokGudangSnap a
FULL JOIN (SELECT cNoSc, SUM(nStokPc) AS pc FROM dbo.tbStokSnapTipe
           GROUP BY cNoSc HAVING SUM(nStokPc) <> 0) b ON b.cNoSc = a.cNoSc
WHERE     ISNULL(a.nStokPc,0) <> ISNULL(b.pc,0);

SELECT cKelompok, COUNT(*) AS jml_sc, SUM(nStokPc) AS total_pc, MAX(dPosisi) AS posisi
FROM   dbo.tbStokSnapTipe GROUP BY cKelompok ORDER BY SUM(nStokPc) DESC;

SELECT cStatusData, COUNT(*) AS jml_op, SUM(nStokPc) AS total_pc
FROM   dbo.tbStokGudangSnap GROUP BY cStatusData ORDER BY jml_op DESC;

SELECT TOP 5 nId, nDetik, nJmlOp, nTotalPc, cStatus, cSumber, cPesan
FROM   dbo.tbStokGudangLog ORDER BY nId DESC;
GO
