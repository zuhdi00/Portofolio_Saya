/* ============================================================================
   PT SUPRACOR SEJAHTERA — PERBAIKAN HASIL spRefreshStokGudang
   Dibuat : 04 Agustus 2026

   MASALAH
     Refresh berhasil jalan (8 detik), tapi hasilnya salah besar:
        144.382 NO. OP  |  total -209.917.614 pc
     Padahal patokan gudang cuma sekitar 291 NO. OP / 776.500 pc.

   PENYEBAB
     Agregasi saya menjumlahkan SEMUA NO. OP yang muncul di tbSRJ / tbSRJDtl,
     termasuk ribuan OP lama yang punya surat jalan tapi TIDAK punya catatan
     setor barang jadi di tbStbBJ. Untuk OP seperti itu perhitungannya jadi
     0 - kirim = minus, dan kalau dijumlahkan hasilnya minus ratusan juta.

     report_backend.php tidak pernah kena masalah ini karena selalu dibatasi
     rentang tanggal SC (sc_list), jadi OP lama tidak pernah ikut terhitung.

   PERBAIKAN
     Stok gudang sekarang HANYA dihitung untuk NO. OP yang benar-benar punya
     baris di tbStbBJ. OP tanpa STB bukan stok gudang, jadi tidak relevan.
     Nomor OP juga di-RTRIM supaya versi berspasi (tbSC char(18)) dan versi
     tanpa spasi (tbStbBJ varchar(24)) tidak terpecah jadi dua baris.

   Index, tbStokGudangSnap, tbStokGudangMutasi dan tbStokGudangLog tidak
   perlu dibuat ulang. File ini hanya mengganti stored procedure.
   ============================================================================ */

USE dbSopanusa;
GO
SET NOCOUNT ON;
GO

/* ---------------------------------------------------------------------------
   LANGKAH 1 — BUKTIKAN DULU PENYEBABNYA (jalankan, lihat angkanya)
   --------------------------------------------------------------------------- */

-- 1a. Berapa banyak NO. OP di surat jalan yang TIDAK punya catatan STB sama sekali?
--     Inilah yang tadi ikut terhitung sebagai stok minus.
SELECT COUNT(DISTINCT sc) AS op_tanpa_stb, SUM(qty) AS qty_kirim_tanpa_stb
FROM (
    SELECT RTRIM(COALESCE(d.cNoScDtl, s.cNoSC)) AS sc, SUM(ISNULL(d.nQty,0)) AS qty
    FROM   dbo.tbSRJ s
    INNER JOIN dbo.tbSRJDtl d ON d.cNoSRJ = s.cNoSRJ
    GROUP  BY RTRIM(COALESCE(d.cNoScDtl, s.cNoSC))
) k
WHERE NOT EXISTS (SELECT 1 FROM dbo.tbStbBJ b WHERE RTRIM(b.cNoSc) = k.sc);

-- 1b. Berapa NO. OP yang punya STB? Ini calon isi dashboard.
SELECT COUNT(DISTINCT RTRIM(cNoSc)) AS op_punya_stb FROM dbo.tbStbBJ;
GO

/* ---------------------------------------------------------------------------
   LANGKAH 2 — PRATINJAU HASIL BARU, TANPA MENGUBAH APA PUN
   Jalankan blok ini dulu. Kalau angkanya sudah wajar, baru lanjut LANGKAH 3.
   --------------------------------------------------------------------------- */
SET TRANSACTION ISOLATION LEVEL READ UNCOMMITTED;

IF OBJECT_ID('tempdb..#sc')  IS NOT NULL DROP TABLE #sc;
IF OBJECT_ID('tempdb..#pre') IS NOT NULL DROP TABLE #pre;

CREATE TABLE #sc (cNoSc VARCHAR(30) PRIMARY KEY);
INSERT INTO #sc SELECT DISTINCT RTRIM(cNoSc) FROM dbo.tbStbBJ
WHERE cNoSc IS NOT NULL AND LTRIM(RTRIM(cNoSc)) <> '';

SELECT x.cNoSc, SUM(x.q) AS nStok
INTO   #pre
FROM (
    SELECT RTRIM(b.cNoSc) AS cNoSc, SUM(ISNULL(b.nQty,0)) AS q
    FROM dbo.tbStbBJ b INNER JOIN #sc k ON k.cNoSc = b.cNoSc GROUP BY RTRIM(b.cNoSc)
    UNION ALL
    SELECT RTRIM(t.cNoSc), -SUM(ISNULL(t.nStock,0))
    FROM dbo.tbDtStockDtl t INNER JOIN #sc k ON k.cNoSc = t.cNoSc GROUP BY RTRIM(t.cNoSc)
    UNION ALL
    SELECT RTRIM(COALESCE(d.cNoScDtl, s.cNoSC)), -SUM(ISNULL(d.nQty,0))
    FROM dbo.tbSRJ s
    INNER JOIN dbo.tbSRJDtl d ON d.cNoSRJ = s.cNoSRJ
    INNER JOIN #sc k ON k.cNoSc = COALESCE(d.cNoScDtl, s.cNoSC)
    GROUP BY RTRIM(COALESCE(d.cNoScDtl, s.cNoSC))
    UNION ALL
    SELECT RTRIM(COALESCE(d.cNoScDtl, s.cNoSC)), SUM(ISNULL(rv.nQty,0))
    FROM dbo.vwReturnSrj rv
    INNER JOIN dbo.tbSRJDtl d ON d.cNoSRJ = rv.cNoSrj
    INNER JOIN dbo.tbSRJ    s ON s.cNoSRJ = d.cNoSRJ
    INNER JOIN #sc k ON k.cNoSc = COALESCE(d.cNoScDtl, s.cNoSC)
    GROUP BY RTRIM(COALESCE(d.cNoScDtl, s.cNoSC))
) x
GROUP BY x.cNoSc;

-- Kurangi penyesuaian Excel bila tabelnya sudah ada
IF OBJECT_ID('dbo.tbStokGudangAdj') IS NOT NULL
    UPDATE p SET p.nStok = p.nStok - ISNULL(j.nAdjust, 0)
    FROM #pre p INNER JOIN dbo.tbStokGudangAdj j ON RTRIM(j.cNoSc) = p.cNoSc;

-- RINGKASAN — bandingkan dengan patokan Excel: 291 OP / 776.500 pc
SELECT COUNT(*)                                    AS op_stok_tidak_nol,
       SUM(CASE WHEN nStok > 0 THEN 1 ELSE 0 END)  AS op_positif,
       SUM(CASE WHEN nStok < 0 THEN 1 ELSE 0 END)  AS op_negatif,
       SUM(CASE WHEN nStok > 0 THEN nStok ELSE 0 END) AS total_pc_positif,
       SUM(nStok)                                  AS total_pc_bersih
FROM   #pre WHERE nStok <> 0;

-- 20 stok minus terbesar, untuk ditelusuri gudang
SELECT TOP 20 cNoSc, nStok FROM #pre WHERE nStok < 0 ORDER BY nStok;

DROP TABLE #pre;
DROP TABLE #sc;
GO

/* ---------------------------------------------------------------------------
   LANGKAH 3 — PASANG PROSEDUR YANG SUDAH DIPERBAIKI
   --------------------------------------------------------------------------- */
IF OBJECT_ID('dbo.spRefreshStokGudang') IS NOT NULL DROP PROCEDURE dbo.spRefreshStokGudang;
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

/* ---------------------------------------------------------------------------
   LANGKAH 4 — JALANKAN & PERIKSA
   --------------------------------------------------------------------------- */
EXEC dbo.spRefreshStokGudang @Sumber = 'PERBAIKAN2';
GO

-- 4a. Harus SUKSES, dan nJmlOp / nTotalPc harus jauh lebih masuk akal
SELECT TOP 5 nId, dMulai, nDetik, nJmlOp, nTotalPc, cStatus, cSumber, cPesan
FROM   dbo.tbStokGudangLog ORDER BY nId DESC;

-- 4b. Ringkasan isi snapshot
SELECT COUNT(*) AS jml_op,
       SUM(CASE WHEN nStokPc > 0 THEN nStokPc ELSE 0 END) AS total_pc_positif,
       SUM(CASE WHEN nStokPc < 0 THEN 1 ELSE 0 END)       AS op_negatif,
       SUM(nStokKg)                                       AS total_kg
FROM   dbo.tbStokGudangSnap;

-- 4c. Cocokkan dengan patokan Excel per 03 Agustus 2026.
--     Kolom selisih idealnya 0 untuk 291 OP yang ada di file gudang.
IF OBJECT_ID('dbo.tbStokGudangExcel') IS NOT NULL
SELECT TOP 30 e.cNoSc, e.cNama, e.nStokAkhirPc AS excel,
       ISNULL(s.nStokPc, 0) AS sistem,
       e.nStokAkhirPc - ISNULL(s.nStokPc, 0) AS selisih
FROM      dbo.tbStokGudangExcel e
LEFT JOIN dbo.tbStokGudangSnap  s ON RTRIM(s.cNoSc) = RTRIM(e.cNoSc)
WHERE     e.nStokAkhirPc <> ISNULL(s.nStokPc, 0)
ORDER BY  ABS(e.nStokAkhirPc - ISNULL(s.nStokPc, 0)) DESC;
GO
