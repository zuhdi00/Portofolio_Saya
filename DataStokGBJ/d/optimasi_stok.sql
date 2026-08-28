/* ============================================================================
   PT SUPRACOR SEJAHTERA — OPTIMASI TARIKAN DATA DASHBOARD STOK BJ
   Database : dbSopanusa (SQL Server)
   Dibuat   : 04 Agustus 2026

   MASALAH
   Query dashboard mengagregasi SELURUH isi tbStbBJ, tbSRJDtl, vwReturnSrj dan
   tbDtStockDtl tanpa batas tanggal. Tiap kali halaman dibuka, semua riwayat
   sejak awal dibaca ulang -> lambat, dan makin lambat tiap bulan.

   SOLUSI (3 lapis)
   LAPIS 1  Index pendukung                 -> query berat jadi jauh lebih cepat
   LAPIS 2  Tabel snapshot + stored proc    -> dashboard baca tabel jadi, instan
   LAPIS 3  SQL Agent job tiap 15 menit     -> snapshot selalu segar otomatis

   Setelah ini dashboard tidak lagi menghitung apa pun. Cuma SELECT dari
   tbStokGudangSnap yang sudah berisi hasil jadi.

   JALANKAN LAPIS 1 DI LUAR JAM SIBUK (pembuatan index mengunci tabel).
   ============================================================================ */

USE dbSopanusa;
GO
SET NOCOUNT ON;
GO

/* ---------------------------------------------------------------------------
   LANGKAH 0 — DIAGNOSA (opsional, untuk tahu bagian mana yang paling berat)
   Jalankan sendiri-sendiri, lihat kolom "elapsed time" di tab Messages.
   --------------------------------------------------------------------------- */
/*
SET STATISTICS TIME ON;
SELECT COUNT(*) AS baris_tbStbBJ     FROM dbo.tbStbBJ;
SELECT COUNT(*) AS baris_tbSRJDtl    FROM dbo.tbSRJDtl;
SELECT COUNT(*) AS baris_tbDtStockDtl FROM dbo.tbDtStockDtl;
SELECT COUNT(*) AS baris_vwReturnSrj FROM dbo.vwReturnSrj;   -- biasanya paling berat
SET STATISTICS TIME OFF;

-- Lihat index yang sudah ada di tabel-tabel ini
SELECT t.name AS tabel, i.name AS index_name, i.type_desc,
       STUFF((SELECT ', ' + c.name
              FROM sys.index_columns ic
              INNER JOIN sys.columns c ON c.object_id = ic.object_id AND c.column_id = ic.column_id
              WHERE ic.object_id = i.object_id AND ic.index_id = i.index_id AND ic.is_included_column = 0
              ORDER BY ic.key_ordinal FOR XML PATH('')), 1, 2, '') AS kolom_kunci
FROM   sys.indexes i
INNER JOIN sys.tables t ON t.object_id = i.object_id
WHERE  t.name IN ('tbStbBJ','tbSRJ','tbSRJDtl','tbDtStockDtl')
ORDER  BY t.name, i.index_id;
*/

/* ============================================================================
   LAPIS 1 — INDEX PENDUKUNG
   Semua index hanya mempercepat pembacaan. Tidak mengubah data sama sekali.
   Dampak ke input harian: sangat kecil (tabel ini jarang di-update masal).
   Kalau edisi SQL Server-nya Enterprise, tambahkan WITH (ONLINE = ON)
   supaya tabel tidak terkunci saat index dibuat.
   ============================================================================ */

-- tbStbBJ : dipakai untuk agregasi per OP dan mutasi harian
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'IX_tbStbBJ_cNoSc_Stok')
CREATE NONCLUSTERED INDEX IX_tbStbBJ_cNoSc_Stok
    ON dbo.tbStbBJ (cNoSc)
    INCLUDE (nQty, nQtyKg, dTanggal, nberat)
    WITH (FILLFACTOR = 90, SORT_IN_TEMPDB = ON);
GO

IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'IX_tbStbBJ_dTanggal')
CREATE NONCLUSTERED INDEX IX_tbStbBJ_dTanggal
    ON dbo.tbStbBJ (dTanggal)
    INCLUDE (cNoSc, nQty, nQtyKg)
    WITH (FILLFACTOR = 90, SORT_IN_TEMPDB = ON);
GO

-- tbDtStockDtl : agregasi nStock per OP
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'IX_tbDtStockDtl_cNoSc')
CREATE NONCLUSTERED INDEX IX_tbDtStockDtl_cNoSc
    ON dbo.tbDtStockDtl (cNoSc)
    INCLUDE (nStock)
    WITH (FILLFACTOR = 90, SORT_IN_TEMPDB = ON);
GO

-- tbSRJDtl : dijoin lewat cNoSRJ dan dikelompokkan lewat cNoScDtl
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'IX_tbSRJDtl_cNoSRJ')
CREATE NONCLUSTERED INDEX IX_tbSRJDtl_cNoSRJ
    ON dbo.tbSRJDtl (cNoSRJ)
    INCLUDE (cNoScDtl, nQty)
    WITH (FILLFACTOR = 90, SORT_IN_TEMPDB = ON);
GO

IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'IX_tbSRJDtl_cNoScDtl')
CREATE NONCLUSTERED INDEX IX_tbSRJDtl_cNoScDtl
    ON dbo.tbSRJDtl (cNoScDtl)
    INCLUDE (cNoSRJ, nQty)
    WITH (FILLFACTOR = 90, SORT_IN_TEMPDB = ON);
GO

-- tbSRJ : header surat jalan
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'IX_tbSRJ_cNoSRJ_Stok')
CREATE NONCLUSTERED INDEX IX_tbSRJ_cNoSRJ_Stok
    ON dbo.tbSRJ (cNoSRJ)
    INCLUDE (cNoSC, dTanggal)
    WITH (FILLFACTOR = 90, SORT_IN_TEMPDB = ON);
GO

IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'IX_tbSRJ_dTanggal')
CREATE NONCLUSTERED INDEX IX_tbSRJ_dTanggal
    ON dbo.tbSRJ (dTanggal)
    INCLUDE (cNoSRJ, cNoSC)
    WITH (FILLFACTOR = 90, SORT_IN_TEMPDB = ON);
GO

-- Perbarui statistik supaya optimizer memakai index baru
UPDATE STATISTICS dbo.tbStbBJ      WITH FULLSCAN;
UPDATE STATISTICS dbo.tbSRJ        WITH FULLSCAN;
UPDATE STATISTICS dbo.tbSRJDtl     WITH FULLSCAN;
UPDATE STATISTICS dbo.tbDtStockDtl WITH FULLSCAN;
GO

/* ============================================================================
   LAPIS 2 — TABEL SNAPSHOT + STORED PROCEDURE
   ============================================================================ */

IF OBJECT_ID('dbo.spRefreshStokGudang') IS NOT NULL DROP PROCEDURE dbo.spRefreshStokGudang;
IF OBJECT_ID('dbo.tbStokGudangSnap')    IS NOT NULL DROP TABLE dbo.tbStokGudangSnap;
IF OBJECT_ID('dbo.tbStokGudangMutasi')  IS NOT NULL DROP TABLE dbo.tbStokGudangMutasi;
IF OBJECT_ID('dbo.tbStokGudangLog')     IS NOT NULL DROP TABLE dbo.tbStokGudangLog;
GO

-- Hasil hitung stok per NO. OP
-- Lebar kolom sengaja dibuat longgar terhadap tabel sumber. Kalau kolom sumber
-- suatu saat diperlebar, tabel ini tidak ikut error karena ada pengaman LEFT()
-- di stored procedure.
CREATE TABLE dbo.tbStokGudangSnap (
    cNoSc        VARCHAR(30)   NOT NULL,
    cKodeCust    VARCHAR(50)   NULL,
    cNama        NVARCHAR(300) NULL,
    cNamabrg     NVARCHAR(500) NULL,
    cNoMC        VARCHAR(100)  NULL,
    cNamaSales   NVARCHAR(200) NULL,
    cType        VARCHAR(100)  NULL,
    cRak         VARCHAR(100)  NULL,
    nBerat       DECIMAL(18,5) NOT NULL DEFAULT 0,
    dTglStbAkhir DATE          NULL,
    nUmur        INT           NULL,
    nStokPc      INT           NOT NULL DEFAULT 0,
    nStokKg      DECIMAL(18,3) NOT NULL DEFAULT 0,
    cKeterangan  NVARCHAR(255) NULL,
    lDariExcel   BIT           NOT NULL DEFAULT 0,
    CONSTRAINT PK_tbStokGudangSnap PRIMARY KEY (cNoSc)
);
GO
CREATE NONCLUSTERED INDEX IX_Snap_Stok ON dbo.tbStokGudangSnap (nStokPc) INCLUDE (cNama, nUmur);
GO

-- Mutasi harian 30 hari terakhir
CREATE TABLE dbo.tbStokGudangMutasi (
    dTanggal DATE NOT NULL,
    nStbPc   INT  NOT NULL DEFAULT 0,
    nStbKg   DECIMAL(18,3) NOT NULL DEFAULT 0,
    nKirimPc INT  NOT NULL DEFAULT 0,
    nKirimKg DECIMAL(18,3) NOT NULL DEFAULT 0,
    CONSTRAINT PK_tbStokGudangMutasi PRIMARY KEY (dTanggal)
);
GO

-- Catatan waktu refresh
CREATE TABLE dbo.tbStokGudangLog (
    nId       INT IDENTITY(1,1) PRIMARY KEY,
    dMulai    DATETIME NOT NULL,
    dSelesai  DATETIME NULL,
    nDetik    INT      NULL,
    nJmlOp    INT      NULL,
    nTotalPc  INT      NULL,
    cStatus   VARCHAR(20)   NULL,
    cPesan    NVARCHAR(500) NULL,
    cSumber   VARCHAR(30)   NULL
);
GO

CREATE PROCEDURE dbo.spRefreshStokGudang
    @Sumber   VARCHAR(30) = 'MANUAL',   -- 'JOB' kalau dipanggil SQL Agent
    @HariTrend INT        = 30
AS
BEGIN
    SET NOCOUNT ON;
    SET TRANSACTION ISOLATION LEVEL READ UNCOMMITTED;  -- tidak mengunci input gudang

    DECLARE @Mulai DATETIME = GETDATE(), @Id INT;
    INSERT INTO dbo.tbStokGudangLog (dMulai, cStatus, cSumber) VALUES (@Mulai, 'JALAN', @Sumber);
    SET @Id = SCOPE_IDENTITY();

    BEGIN TRY
        DECLARE @AdaAdj BIT = CASE WHEN OBJECT_ID('dbo.tbStokGudangAdj')   IS NULL THEN 0 ELSE 1 END;
        DECLARE @AdaXls BIT = CASE WHEN OBJECT_ID('dbo.tbStokGudangExcel') IS NULL THEN 0 ELSE 1 END;

        -- 1) Agregasi ringan lebih dulu, hasilnya kecil (per NO. OP)
        CREATE TABLE #agg (cNoSc VARCHAR(30) PRIMARY KEY, nStok INT NOT NULL DEFAULT 0);

        -- CATATAN PENTING
        -- Stok gudang HANYA dihitung untuk NO. OP yang benar-benar punya catatan
        -- setor barang jadi (tbStbBJ). Tanpa pembatas ini, semua NO. OP lama yang
        -- punya surat jalan tapi tidak punya STB ikut terhitung sebagai stok
        -- minus, dan hasilnya jadi puluhan ribu OP dengan total minus ratusan juta.
        CREATE TABLE #sc (cNoSc VARCHAR(30) PRIMARY KEY);
        INSERT INTO #sc (cNoSc)
        SELECT DISTINCT RTRIM(cNoSc) FROM dbo.tbStbBJ
        WHERE  cNoSc IS NOT NULL AND LTRIM(RTRIM(cNoSc)) <> '';

        INSERT INTO #agg (cNoSc, nStok)
        SELECT x.cNoSc, SUM(x.q)
        FROM (
            SELECT RTRIM(b.cNoSc) AS cNoSc, SUM(ISNULL(b.nQty,0)) AS q
            FROM   dbo.tbStbBJ b
            INNER JOIN #sc k ON k.cNoSc = b.cNoSc
            GROUP  BY RTRIM(b.cNoSc)
            UNION ALL
            SELECT RTRIM(t.cNoSc), -SUM(ISNULL(t.nStock,0))
            FROM   dbo.tbDtStockDtl t
            INNER JOIN #sc k ON k.cNoSc = t.cNoSc
            GROUP  BY RTRIM(t.cNoSc)
            UNION ALL
            SELECT RTRIM(COALESCE(d.cNoScDtl, s.cNoSC)), -SUM(ISNULL(d.nQty,0))
            FROM   dbo.tbSRJ s
            INNER JOIN dbo.tbSRJDtl d ON d.cNoSRJ = s.cNoSRJ
            INNER JOIN #sc k ON k.cNoSc = COALESCE(d.cNoScDtl, s.cNoSC)
            GROUP  BY RTRIM(COALESCE(d.cNoScDtl, s.cNoSC))
            UNION ALL
            SELECT RTRIM(COALESCE(d.cNoScDtl, s.cNoSC)), SUM(ISNULL(rv.nQty,0))
            FROM   dbo.vwReturnSrj rv
            INNER JOIN dbo.tbSRJDtl d ON d.cNoSRJ = rv.cNoSrj
            INNER JOIN dbo.tbSRJ    s ON s.cNoSRJ = d.cNoSRJ
            INNER JOIN #sc k ON k.cNoSc = COALESCE(d.cNoScDtl, s.cNoSC)
            GROUP  BY RTRIM(COALESCE(d.cNoScDtl, s.cNoSC))
        ) x
        GROUP BY x.cNoSc;

        DROP TABLE #sc;

        -- Kurangi penyesuaian hasil sinkronisasi Excel
        IF @AdaAdj = 1
            UPDATE a SET a.nStok = a.nStok - ISNULL(j.nAdjust, 0)
            FROM   #agg a
            INNER JOIN dbo.tbStokGudangAdj j ON j.cNoSc = a.cNoSc;

        -- 2) Buang yang stoknya nol. Sisanya sedikit, jadi lookup detail murah.
        --    Stok minus TIDAK dibuang: itu tanda data yang perlu diperiksa gudang,
        --    dan sudah dihitung terpisah sebagai "op_negatif" di dashboard.
        DELETE FROM #agg WHERE nStok = 0;

        -- 3) Ambil detail hanya untuk OP yang tersisa
        BEGIN TRANSACTION;

        TRUNCATE TABLE dbo.tbStokGudangSnap;

        INSERT INTO dbo.tbStokGudangSnap
              (cNoSc, cKodeCust, cNama, cNamabrg, cNoMC, cNamaSales, cType, cRak,
               nBerat, dTglStbAkhir, nUmur, nStokPc, nStokKg, cKeterangan, lDariExcel)
        SELECT cNoSc, cKodeCust, cNama, cNamabrg, cNoMC, cNamaSales, cType, cRak,
               nBerat, dTglStbAkhir, nUmur, nStokPc, nStokKg, cKeterangan, lDariExcel
        FROM (
            SELECT LEFT(a.cNoSc, 30)                                          AS cNoSc,
                   LEFT(ISNULL(d.cKodeCust, ''), 50)                          AS cKodeCust,
                   LEFT(ISNULL(NULLIF(sc.cNama, ''), ISNULL(d.cNama, '')), 300) AS cNama,
                   LEFT(ISNULL(d.cNamabrg, ''), 500)   AS cNamabrg,
                   LEFT(ISNULL(d.cNoMC, ''), 100)      AS cNoMC,
                   LEFT(ISNULL(d.cNamaSales, ''), 200) AS cNamaSales,
                   LEFT(ISNULL(d.cType, ''), 100)      AS cType,
                   LEFT(ISNULL(d.cRak, ''), 100)       AS cRak,
                   ISNULL(d.nberat, 0)      AS nBerat,
                   CAST(d.dTanggal AS DATE) AS dTglStbAkhir,
                   DATEDIFF(day, d.dTanggal, CAST(GETDATE() AS DATE))         AS nUmur,
                   a.nStok                                                    AS nStokPc,
                   CAST(a.nStok * ISNULL(d.nberat, 0) AS DECIMAL(18,3))       AS nStokKg,
                   CASE WHEN @AdaXls = 1 THEN LEFT(e.cKeterangan, 255) END    AS cKeterangan,
                   CASE WHEN e.cNoSc IS NULL THEN 0 ELSE 1 END                AS lDariExcel,
                   -- Jaring pengaman: kalau suatu saat ada join yang menggandakan
                   -- baris lagi, hanya baris pertama yang dipakai (bukan error).
                   ROW_NUMBER() OVER (PARTITION BY a.cNoSc ORDER BY (SELECT 1)) AS rn
            FROM      #agg a
            -- tbSC BISA punya lebih dari satu baris per cNoSc, jadi WAJIB TOP 1.
            -- Memakai LEFT JOIN biasa di sini menyebabkan duplikat primary key.
            OUTER APPLY (
                SELECT TOP 1 x.cNama
                FROM   dbo.tbSC x
                WHERE  x.cNoSc = a.cNoSc
                ORDER  BY x.dTanggal DESC, x.cNoSc
            ) sc
            LEFT JOIN dbo.tbStokGudangExcel e ON @AdaXls = 1 AND e.cNoSc = a.cNoSc
            OUTER APPLY (
                SELECT TOP 1 s.cKodeCust, s.cNama, s.cNamabrg, s.cNoMC, s.cNamaSales,
                             s.cType, s.cRak, s.nberat, s.dTanggal
                FROM   dbo.tbStbBJ s
                WHERE  s.cNoSc = a.cNoSc
                ORDER  BY s.dTanggal DESC, s.cNoSTB DESC
            ) d
        ) src
        WHERE src.rn = 1;

        -- 4) Mutasi harian
        DECLARE @Awal DATE = DATEADD(day, -(@HariTrend - 1), CAST(GETDATE() AS DATE));
        DECLARE @Akhir DATE = DATEADD(day, 1, CAST(GETDATE() AS DATE));

        TRUNCATE TABLE dbo.tbStokGudangMutasi;

        ;WITH berat_sc AS (
            SELECT cNoSc, MAX(ISNULL(nberat,0)) AS nberat FROM dbo.tbStbBJ GROUP BY cNoSc
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
            LEFT  JOIN berat_sc     bs ON bs.cNoSc  = COALESCE(dt.cNoScDtl, s.cNoSC)
            WHERE  s.dTanggal >= @Awal AND s.dTanggal < @Akhir
            GROUP  BY CAST(s.dTanggal AS DATE)
        ),
        kalender AS (
            SELECT @Awal AS d
            UNION ALL
            SELECT DATEADD(day, 1, d) FROM kalender WHERE d < CAST(GETDATE() AS DATE)
        )
        INSERT INTO dbo.tbStokGudangMutasi (dTanggal, nStbPc, nStbKg, nKirimPc, nKirimKg)
        SELECT k.d,
               ISNULL(sh.pc, 0), CAST(ISNULL(sh.kg, 0) AS DECIMAL(18,3)),
               ISNULL(kh.pc, 0), CAST(ISNULL(kh.kg, 0) AS DECIMAL(18,3))
        FROM      kalender k
        LEFT JOIN stb_h sh ON sh.d = k.d
        LEFT JOIN krm_h kh ON kh.d = k.d
        OPTION (MAXRECURSION 366);

        COMMIT TRANSACTION;

        UPDATE dbo.tbStokGudangLog
        SET    dSelesai = GETDATE(),
               nDetik   = DATEDIFF(second, @Mulai, GETDATE()),
               nJmlOp   = (SELECT COUNT(*)          FROM dbo.tbStokGudangSnap),
               nTotalPc = (SELECT ISNULL(SUM(nStokPc),0) FROM dbo.tbStokGudangSnap),
               cStatus  = 'SUKSES'
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

-- Isi pertama kali. Catat berapa detik jalannya.
EXEC dbo.spRefreshStokGudang @Sumber = 'SETUP';
GO

SELECT TOP 5 nId, dMulai, nDetik, nJmlOp, nTotalPc, cStatus, cSumber
FROM   dbo.tbStokGudangLog ORDER BY nId DESC;
GO

/* ============================================================================
   LAPIS 3 — JADWAL OTOMATIS (SQL Agent, tiap 15 menit)
   Kalau SQL Agent tidak aktif, lewati blok ini dan pakai tombol
   "Hitung ulang" di dashboard, atau jadwalkan lewat Windows Task Scheduler.
   ============================================================================ */
USE msdb;
GO

IF EXISTS (SELECT 1 FROM msdb.dbo.sysjobs WHERE name = 'SPS - Refresh Stok Gudang BJ')
    EXEC msdb.dbo.sp_delete_job @job_name = 'SPS - Refresh Stok Gudang BJ', @delete_unused_schedule = 1;
GO

EXEC msdb.dbo.sp_add_job
     @job_name    = 'SPS - Refresh Stok Gudang BJ',
     @description = 'Menghitung ulang snapshot stok barang jadi untuk dashboard.',
     @enabled     = 1;

EXEC msdb.dbo.sp_add_jobstep
     @job_name   = 'SPS - Refresh Stok Gudang BJ',
     @step_name  = 'Jalankan spRefreshStokGudang',
     @subsystem  = 'TSQL',
     @database_name = 'dbSopanusa',
     @command    = 'EXEC dbo.spRefreshStokGudang @Sumber = ''JOB'';',
     @retry_attempts = 2,
     @retry_interval = 1;

EXEC msdb.dbo.sp_add_schedule
     @schedule_name  = 'SPS Tiap 15 Menit',
     @freq_type      = 4,          -- harian
     @freq_interval  = 1,
     @freq_subday_type     = 4,    -- satuan menit
     @freq_subday_interval = 15,
     @active_start_time = 000000,
     @active_end_time   = 235959;

EXEC msdb.dbo.sp_attach_schedule
     @job_name      = 'SPS - Refresh Stok Gudang BJ',
     @schedule_name = 'SPS Tiap 15 Menit';

EXEC msdb.dbo.sp_add_jobserver
     @job_name = 'SPS - Refresh Stok Gudang BJ';
GO

USE dbSopanusa;
GO

/* ---------------------------------------------------------------------------
   PEMANTAUAN — pakai ini kalau dashboard terasa lambat lagi
   --------------------------------------------------------------------------- */
-- Riwayat waktu refresh
-- SELECT TOP 30 * FROM dbo.tbStokGudangLog ORDER BY nId DESC;

-- Rata-rata & terlama 7 hari terakhir
-- SELECT AVG(nDetik) AS rata2_detik, MAX(nDetik) AS terlama_detik, COUNT(*) AS jml_refresh
-- FROM   dbo.tbStokGudangLog
-- WHERE  cStatus = 'SUKSES' AND dMulai >= DATEADD(day, -7, GETDATE());

/* ---------------------------------------------------------------------------
   ROLLBACK — hapus semua yang dibuat file ini
   Data asli tidak pernah diubah, jadi aman.
   --------------------------------------------------------------------------- */
/*
EXEC msdb.dbo.sp_delete_job @job_name = 'SPS - Refresh Stok Gudang BJ', @delete_unused_schedule = 1;
DROP PROCEDURE dbo.spRefreshStokGudang;
DROP TABLE dbo.tbStokGudangSnap;
DROP TABLE dbo.tbStokGudangMutasi;
DROP TABLE dbo.tbStokGudangLog;
DROP INDEX IX_tbStbBJ_cNoSc_Stok    ON dbo.tbStbBJ;
DROP INDEX IX_tbStbBJ_dTanggal      ON dbo.tbStbBJ;
DROP INDEX IX_tbDtStockDtl_cNoSc    ON dbo.tbDtStockDtl;
DROP INDEX IX_tbSRJDtl_cNoSRJ       ON dbo.tbSRJDtl;
DROP INDEX IX_tbSRJDtl_cNoScDtl     ON dbo.tbSRJDtl;
DROP INDEX IX_tbSRJ_cNoSRJ_Stok     ON dbo.tbSRJ;
DROP INDEX IX_tbSRJ_dTanggal        ON dbo.tbSRJ;
GO
*/
