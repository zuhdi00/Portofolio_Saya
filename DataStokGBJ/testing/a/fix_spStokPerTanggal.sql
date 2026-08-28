/* ============================================================================
   PT SUPRACOR SEJAHTERA — PERBAIKAN spStokPerTanggal
   Dibuat : 07 Agustus 2026

   ERROR YANG DIPERBAIKI
     Msg 2627 — Violation of PRIMARY KEY constraint pada #s5.
     Duplicate key value is (SLC/2607/00377).

   PENYEBAB
     Di dalam prosedur, bagian rincian memakai LEFT JOIN ke tbStokGudangExcel.
     Tabel itu punya primary key gabungan (cNoSc, cKategori), jadi satu NO. SC
     bisa punya DUA baris: satu BOX, satu PART+LAYER. SLC/2607/00377 termasuk
     salah satunya. LEFT JOIN karena itu menghasilkan dua baris untuk satu SC,
     dan menabrak primary key tabel penampung.

   PERBAIKAN
     LEFT JOIN diganti OUTER APPLY (SELECT TOP 1 ...), sehingga tiap NO. SC
     dijamin menghasilkan satu baris. Nilai stoknya sendiri sudah benar sejak
     awal — penjumlahan BOX dan PART+LAYER terjadi di tabel #a lewat GROUP BY,
     bukan di bagian ini. Yang salah hanya cara mengambil nama customer.

   File ini hanya membuat ulang prosedur, lalu menjalankan pencocokan.
   ============================================================================ */

USE dbSopanusa;
GO
SET NOCOUNT ON;
GO

IF OBJECT_ID('dbo.spStokPerTanggal') IS NOT NULL DROP PROCEDURE dbo.spStokPerTanggal;
GO

CREATE PROCEDURE dbo.spStokPerTanggal
    @Posisi   DATE,                 -- stok pada akhir tanggal ini
    @Rinci    BIT = 0               -- 1 = tampilkan per NO. SC
AS
BEGIN
    SET NOCOUNT ON;
    SET TRANSACTION ISOLATION LEVEL READ UNCOMMITTED;

    DECLARE @Cut DATE = (SELECT MAX(dCutOff) FROM dbo.tbStokGudangExcel);
    DECLARE @Bts DATETIME = DATEADD(day, 1, CAST(@Posisi AS DATETIME));

    CREATE TABLE #a (sc VARCHAR(30) PRIMARY KEY, pc INT NOT NULL DEFAULT 0);

    -- Saldo awal dari patokan Excel
    INSERT INTO #a (sc, pc)
    SELECT RTRIM(cNoScDb), SUM(nStokAkhirPc)
    FROM   dbo.tbStokGudangExcel WHERE cNoScDb IS NOT NULL
    GROUP  BY RTRIM(cNoScDb);

    -- NO. OP yang punya mutasi setelah cut-off tapi belum ada di patokan.
    -- Kirim dan retur ikut diperhitungkan, tidak hanya STB seperti versi lama.
    INSERT INTO #a (sc, pc)
    SELECT DISTINCT sc, 0 FROM (
        SELECT RTRIM(cNoSc) AS sc FROM dbo.tbStbBJ
        WHERE  dTanggal > @Cut AND dTanggal < @Bts
          AND  cNoSc IS NOT NULL AND LTRIM(RTRIM(cNoSc)) <> ''
        UNION
        SELECT RTRIM(COALESCE(d.cNoScDtl, s.cNoSC))
        FROM   dbo.tbSRJ s INNER JOIN dbo.tbSRJDtl d ON d.cNoSRJ = s.cNoSRJ
        WHERE  s.dTanggal > @Cut AND s.dTanggal < @Bts
          AND  COALESCE(d.cNoScDtl, s.cNoSC) IS NOT NULL
    ) z
    WHERE NOT EXISTS (SELECT 1 FROM #a a WHERE a.sc = z.sc);

    UPDATE a SET a.pc = a.pc + x.q FROM #a a
    INNER JOIN (SELECT RTRIM(cNoSc) AS sc, SUM(ISNULL(nQty,0)) AS q
                FROM   dbo.tbStbBJ WHERE dTanggal > @Cut AND dTanggal < @Bts
                GROUP  BY RTRIM(cNoSc)) x ON x.sc = a.sc;

    UPDATE a SET a.pc = a.pc - x.q FROM #a a
    INNER JOIN (SELECT RTRIM(COALESCE(d.cNoScDtl, s.cNoSC)) AS sc, SUM(ISNULL(d.nQty,0)) AS q
                FROM   dbo.tbSRJ s INNER JOIN dbo.tbSRJDtl d ON d.cNoSRJ = s.cNoSRJ
                WHERE  s.dTanggal > @Cut AND s.dTanggal < @Bts
                GROUP  BY RTRIM(COALESCE(d.cNoScDtl, s.cNoSC))) x ON x.sc = a.sc;

    UPDATE a SET a.pc = a.pc + x.q FROM #a a
    INNER JOIN (SELECT RTRIM(cNoSc) AS sc, SUM(ISNULL(nQty,0)) AS q
                FROM   dbo.vwReturnSrj WHERE dTgl > @Cut AND dTgl < @Bts
                  AND  cNoSc IS NOT NULL AND LTRIM(RTRIM(cNoSc)) <> ''
                GROUP  BY RTRIM(cNoSc)) x ON x.sc = a.sc;

    IF @Rinci = 1
        -- tbStokGudangExcel bisa punya DUA baris untuk satu cNoSc (BOX dan
        -- PART+LAYER), jadi LEFT JOIN biasa menggandakan baris dan menabrak
        -- primary key di tabel penampung. Wajib TOP 1.
        SELECT a.sc AS cNoSc, a.pc AS nStokPc,
               LEFT(ISNULL(e.cNama, ISNULL(s.cNama, '')), 255)      AS cNama,
               LEFT(ISNULL(e.cNamabrg, ISNULL(s.cNamabrg, '')), 500) AS cNamabrg
        FROM   #a a
        OUTER APPLY (SELECT TOP 1 x.cNama, x.cNamabrg
                     FROM   dbo.tbStokGudangExcel x
                     WHERE  x.cNoScDb = a.sc
                     ORDER  BY x.nStokAkhirPc DESC) e
        OUTER APPLY (SELECT TOP 1 y.cNama, y.cNamabrg
                     FROM   dbo.tbStokGudangSnap y
                     WHERE  y.cNoSc = a.sc) s
        WHERE  a.pc <> 0 ORDER BY a.pc DESC;
    ELSE
        SELECT @Posisi AS posisi,
               COUNT(*)                                   AS jml_op,
               SUM(pc)                                    AS total_pc,
               SUM(CASE WHEN pc > 0 THEN pc ELSE 0 END)   AS pc_positif,
               SUM(CASE WHEN pc < 0 THEN 1 ELSE 0 END)    AS jml_minus
        FROM   #a WHERE pc <> 0;

    DROP TABLE #a;
END
GO

/* ---------------------------------------------------------------------------
   PENCOCOKAN PADA POSISI 05 AGUSTUS 2026
   --------------------------------------------------------------------------- */
SET TRANSACTION ISOLATION LEVEL READ UNCOMMITTED;

IF OBJECT_ID('tempdb..#s5') IS NOT NULL DROP TABLE #s5;
CREATE TABLE #s5 (cNoSc VARCHAR(30) PRIMARY KEY, nStokPc INT,
                  cNama NVARCHAR(255), cNamabrg NVARCHAR(500));
INSERT INTO #s5 EXEC dbo.spStokPerTanggal '2026-08-05', 1;

-- Pengaman: harus 0 baris
SELECT cNoSc, COUNT(*) AS jml FROM #s5 GROUP BY cNoSc HAVING COUNT(*) > 1;

-- 1. Total dan akurasinya
DECLARE @X INT = (SELECT SUM(nSaldoPc) FROM dbo.tbCekSaldoExcel);
DECLARE @S INT = (SELECT SUM(nStokPc)  FROM #s5);
SELECT @X AS excel_05agu, @S AS sistem_05agu, @S - @X AS selisih,
       CAST(100.0 * (1 - ABS(@S - @X) / NULLIF(CAST(@X AS DECIMAL(18,2)),0)) AS DECIMAL(5,2)) AS akurasi_persen;

-- 2. Tingkat kecocokan per NO. OP
SELECT CASE WHEN ISNULL(x.nSaldoPc,0) = ISNULL(s.nStokPc,0)             THEN 'SAMA PERSIS'
            WHEN ABS(ISNULL(s.nStokPc,0) - ISNULL(x.nSaldoPc,0)) <= 100 THEN 'BEDA <= 100 pc'
            WHEN x.cNoScDb IS NULL                                      THEN 'HANYA DI SISTEM'
            WHEN s.cNoSc   IS NULL                                      THEN 'HANYA DI EXCEL'
            ELSE 'BEDA > 100 pc' END AS kecocokan,
       COUNT(*) AS jml_op,
       SUM(ISNULL(x.nSaldoPc,0)) AS pc_excel, SUM(ISNULL(s.nStokPc,0)) AS pc_sistem
FROM      dbo.tbCekSaldoExcel x FULL JOIN #s5 s ON s.cNoSc = x.cNoScDb
GROUP BY  CASE WHEN ISNULL(x.nSaldoPc,0) = ISNULL(s.nStokPc,0)             THEN 'SAMA PERSIS'
               WHEN ABS(ISNULL(s.nStokPc,0) - ISNULL(x.nSaldoPc,0)) <= 100 THEN 'BEDA <= 100 pc'
               WHEN x.cNoScDb IS NULL                                      THEN 'HANYA DI SISTEM'
               WHEN s.cNoSc   IS NULL                                      THEN 'HANYA DI EXCEL'
               ELSE 'BEDA > 100 pc' END
ORDER BY  jml_op DESC;

-- 3. 25 selisih terbesar
SELECT TOP 25 COALESCE(x.cNoScDb, s.cNoSc) AS no_sc,
       COALESCE(x.cNama, s.cNama)          AS customer,
       COALESCE(x.cNamabrg, s.cNamabrg)    AS item,
       ISNULL(x.nSaldoPc,0) AS excel, ISNULL(s.nStokPc,0) AS sistem,
       ISNULL(s.nStokPc,0) - ISNULL(x.nSaldoPc,0) AS selisih
FROM      dbo.tbCekSaldoExcel x FULL JOIN #s5 s ON s.cNoSc = x.cNoScDb
WHERE     ISNULL(x.nSaldoPc,0) <> ISNULL(s.nStokPc,0)
ORDER BY  ABS(ISNULL(s.nStokPc,0) - ISNULL(x.nSaldoPc,0)) DESC;

-- 4. Stok sistem hari per hari, 01 s/d 07 Agustus
EXEC dbo.spStokPerTanggal '2026-08-01';
EXEC dbo.spStokPerTanggal '2026-08-02';
EXEC dbo.spStokPerTanggal '2026-08-03';
EXEC dbo.spStokPerTanggal '2026-08-04';
EXEC dbo.spStokPerTanggal '2026-08-05';
EXEC dbo.spStokPerTanggal '2026-08-06';
EXEC dbo.spStokPerTanggal '2026-08-07';

DROP TABLE #s5;
GO
