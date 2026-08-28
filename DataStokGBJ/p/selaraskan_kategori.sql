/* ============================================================================
   PT SUPRACOR SEJAHTERA — SELARASKAN KATEGORI SALDO AWAL DENGAN TIPE OP
   Dibuat : 08 Agustus 2026

   HASIL SEBELUMNYA
        Kategori      Excel 05 Agu    Sistem 05 Agu    Selisih
        BOX               649.713         662.845      +13.132
        PART+LAYER        255.891         235.891      -20.000

   PENYEBAB SELISIH -20.000, KETEMU PERSIS
     Dua NO. SC saldo awalnya ditaruh di sheet BOX oleh gudang, padahal nomor
     OP-nya bertipe PART. Akibatnya saldo masuk ke kelompok BOX sementara
     pengirimannya dipotong dari kelompok PART+LAYER:

        SLC/2605/00880  saldo awal 9.600 di BOX, kirim 9.600 bertipe PART
        SLC/2607/01006  saldo awal 10.400 di BOX, kirim 10.400 bertipe PART
                                    9.600 + 10.400 = 20.000

   PERBAIKAN
     Saldo awal tidak lagi mengikuti sheet asalnya secara buta. Kalau kategori
     di file Excel tidak cocok dengan tipe OP yang benar-benar dipakai NO. SC
     itu, saldo dipindahkan ke kelompok yang sesuai tipe OP-nya.

     Aturannya:
       1. Kategori Excel cocok dengan salah satu tipe OP milik SC  -> pakai itu
       2. Tidak cocok, tapi SC punya riwayat OP                    -> pakai tipe
                                                                      dengan STB
                                                                      terbesar
       3. SC tidak punya riwayat OP sama sekali                    -> tetap pakai
                                                                      kategori Excel

     Total per NO. SC tidak berubah sepeser pun. Yang berpindah hanya
     penempatan kelompoknya, supaya saldo dan mutasi dipotong dari tempat
     yang sama.

   PERKIRAAN HASIL
        BOX          662.845 - 20.000 = 642.845   (Excel 649.713, beda 6.868)
        PART+LAYER   235.891 + 20.000 = 255.891   (Excel 255.891, PAS)
   ============================================================================ */

USE dbSopanusa;
GO
SET NOCOUNT ON;
GO

IF OBJECT_ID('dbo.spRefreshStokTipe') IS NOT NULL DROP PROCEDURE dbo.spRefreshStokTipe;
GO

CREATE PROCEDURE dbo.spRefreshStokTipe
    @Posisi DATE = NULL
AS
BEGIN
    SET NOCOUNT ON;
    SET TRANSACTION ISOLATION LEVEL READ UNCOMMITTED;

    SET @Posisi = ISNULL(@Posisi, CAST(GETDATE() AS DATE));
    DECLARE @Cut DATE     = (SELECT MAX(dCutOff) FROM dbo.tbStokGudangExcel);
    DECLARE @Bts DATETIME = DATEADD(day, 1, CAST(@Posisi AS DATETIME));

    CREATE TABLE #a (
        sc VARCHAR(30) NOT NULL, kel VARCHAR(12) NOT NULL,
        awal INT NOT NULL DEFAULT 0, stb INT NOT NULL DEFAULT 0,
        krm  INT NOT NULL DEFAULT 0, ret INT NOT NULL DEFAULT 0,
        kor  INT NOT NULL DEFAULT 0,
        PRIMARY KEY (sc, kel)
    );

    /* ---- Tipe OP yang benar-benar dipakai tiap NO. SC, beserta bobotnya ---- */
    IF OBJECT_ID('tempdb..#kelsc') IS NOT NULL DROP TABLE #kelsc;
    SELECT RTRIM(b.cNoSc) AS sc,
           ISNULL(h.cKelompok, 'LAIN') AS kel,
           SUM(ISNULL(b.nQty, 0)) AS qty
    INTO   #kelsc
    FROM   dbo.tbStbBJ b
    LEFT JOIN dbo.tbStokGudangHuruf h ON h.cHuruf = dbo.fnHurufTipe(b.cNoOp)
    WHERE  b.cNoSc IS NOT NULL AND LTRIM(RTRIM(b.cNoSc)) <> ''
      AND  b.dTanggal >= DATEADD(month, -18, @Cut)
    GROUP  BY RTRIM(b.cNoSc), ISNULL(h.cKelompok, 'LAIN');
    CREATE INDEX IX_kelsc ON #kelsc (sc, kel);

    /* ---- 1. Saldo awal, kategorinya diselaraskan dengan tipe OP ---- */
    INSERT INTO #a (sc, kel, awal)
    SELECT y.sc, y.kel_final, SUM(y.pc)
    FROM (
        SELECT RTRIM(e.cNoScDb) AS sc,
               e.nStokAkhirPc   AS pc,
               CASE
                   -- 1) kategori Excel memang salah satu tipe OP milik SC ini
                   WHEN EXISTS (SELECT 1 FROM #kelsc k
                                WHERE k.sc = RTRIM(e.cNoScDb) AND k.kel = e.cKategori)
                        THEN e.cKategori
                   -- 2) tidak cocok, pakai tipe dengan STB terbesar
                   WHEN EXISTS (SELECT 1 FROM #kelsc k WHERE k.sc = RTRIM(e.cNoScDb))
                        THEN (SELECT TOP 1 k.kel FROM #kelsc k
                              WHERE k.sc = RTRIM(e.cNoScDb) ORDER BY k.qty DESC, k.kel)
                   -- 3) tidak punya riwayat OP, biarkan apa adanya
                   ELSE e.cKategori
               END AS kel_final
        FROM   dbo.tbStokGudangExcel e
        WHERE  e.cNoScDb IS NOT NULL
    ) y
    GROUP BY y.sc, y.kel_final;

    /* ---- 2. STB per tipe ---- */
    IF OBJECT_ID('tempdb..#stb') IS NOT NULL DROP TABLE #stb;
    SELECT RTRIM(b.cNoSc) AS sc, ISNULL(h.cKelompok, 'LAIN') AS kel,
           SUM(ISNULL(b.nQty,0)) AS q
    INTO   #stb
    FROM   dbo.tbStbBJ b
    LEFT JOIN dbo.tbStokGudangHuruf h ON h.cHuruf = dbo.fnHurufTipe(b.cNoOp)
    WHERE  b.dTanggal > @Cut AND b.dTanggal < @Bts
      AND  b.cNoSc IS NOT NULL AND LTRIM(RTRIM(b.cNoSc)) <> ''
    GROUP  BY RTRIM(b.cNoSc), ISNULL(h.cKelompok, 'LAIN');

    /* ---- 3. Kirim per tipe ---- */
    IF OBJECT_ID('tempdb..#krm') IS NOT NULL DROP TABLE #krm;
    SELECT RTRIM(COALESCE(d.cNoScDtl, s.cNoSC)) AS sc,
           ISNULL(h.cKelompok, 'LAIN') AS kel, SUM(ISNULL(d.nQty,0)) AS q
    INTO   #krm
    FROM   dbo.tbSRJ s
    INNER JOIN dbo.tbSRJDtl d ON d.cNoSRJ = s.cNoSRJ
    LEFT  JOIN dbo.tbStokGudangHuruf h ON h.cHuruf = dbo.fnHurufTipe(d.cNoOp)
    WHERE  s.dTanggal > @Cut AND s.dTanggal < @Bts
      AND  COALESCE(d.cNoScDtl, s.cNoSC) IS NOT NULL
    GROUP  BY RTRIM(COALESCE(d.cNoScDtl, s.cNoSC)), ISNULL(h.cKelompok, 'LAIN');

    /* ---- 4. Retur, tipenya dilacak lewat baris surat jalannya ---- */
    IF OBJECT_ID('tempdb..#ret') IS NOT NULL DROP TABLE #ret;
    SELECT RTRIM(rv.cNoSc) AS sc, ISNULL(p.kel, 'BOX') AS kel, SUM(ISNULL(rv.nQty,0)) AS q
    INTO   #ret
    FROM   dbo.vwReturnSrj rv
    OUTER APPLY (
        SELECT TOP 1 ISNULL(h.cKelompok, 'LAIN') AS kel
        FROM   dbo.tbSRJDtl d
        LEFT JOIN dbo.tbStokGudangHuruf h ON h.cHuruf = dbo.fnHurufTipe(d.cNoOp)
        WHERE  d.cNoSRJ = rv.cNoSrj AND RTRIM(d.cNoScDtl) = RTRIM(rv.cNoSc)
    ) p
    WHERE  rv.dTgl > @Cut AND rv.dTgl < @Bts
      AND  rv.cNoSc IS NOT NULL AND LTRIM(RTRIM(rv.cNoSc)) <> ''
    GROUP  BY RTRIM(rv.cNoSc), ISNULL(p.kel, 'BOX');

    /* ---- 5. Koreksi manual ---- */
    IF OBJECT_ID('tempdb..#kor') IS NOT NULL DROP TABLE #kor;
    CREATE TABLE #kor (sc VARCHAR(30), kel VARCHAR(12), q INT);
    IF OBJECT_ID('dbo.tbStokGudangKoreksi') IS NOT NULL
        INSERT INTO #kor (sc, kel, q)
        SELECT RTRIM(cNoSc), 'BOX', SUM(nQtyPc)
        FROM   dbo.tbStokGudangKoreksi
        WHERE  lVoid = 0 AND dTanggal > @Cut AND dTanggal < @Bts
        GROUP  BY RTRIM(cNoSc);

    /* ---- 6. Satukan ---- */
    INSERT INTO #a (sc, kel)
    SELECT DISTINCT z.sc, z.kel FROM (
        SELECT sc, kel FROM #stb UNION
        SELECT sc, kel FROM #krm UNION
        SELECT sc, kel FROM #ret UNION
        SELECT sc, kel FROM #kor
    ) z
    WHERE NOT EXISTS (SELECT 1 FROM #a a WHERE a.sc = z.sc AND a.kel = z.kel);

    UPDATE a SET a.stb = x.q FROM #a a INNER JOIN #stb x ON x.sc = a.sc AND x.kel = a.kel;
    UPDATE a SET a.krm = x.q FROM #a a INNER JOIN #krm x ON x.sc = a.sc AND x.kel = a.kel;
    UPDATE a SET a.ret = x.q FROM #a a INNER JOIN #ret x ON x.sc = a.sc AND x.kel = a.kel;
    UPDATE a SET a.kor = x.q FROM #a a INNER JOIN #kor x ON x.sc = a.sc AND x.kel = a.kel;

    /* ---- 7. Simpan ---- */
    TRUNCATE TABLE dbo.tbStokSnapTipe;

    INSERT INTO dbo.tbStokSnapTipe
          (cNoSc, cKelompok, cNama, cNamabrg, nSaldoAwal, nStb, nKirim, nRetur,
           nKoreksi, nStokPc, nStokKg, dPosisi)
    SELECT a.sc, a.kel,
           LEFT(ISNULL(e.cNama, ISNULL(d.cNama, '')), 255),
           LEFT(ISNULL(e.cNamabrg, ISNULL(d.cNamabrg, '')), 500),
           a.awal, a.stb, a.krm, a.ret, a.kor,
           a.awal + a.stb - a.krm + a.ret + a.kor,
           CAST((a.awal + a.stb - a.krm + a.ret + a.kor) * ISNULL(d.nberat, 0) AS DECIMAL(18,3)),
           @Posisi
    FROM   #a a
    OUTER APPLY (SELECT TOP 1 x.cNama, x.cNamabrg FROM dbo.tbStokGudangExcel x
                 WHERE x.cNoScDb = a.sc ORDER BY x.nStokAkhirPc DESC) e
    OUTER APPLY (SELECT TOP 1 y.cNama, y.cNamabrg, y.nberat FROM dbo.tbStbBJ y
                 WHERE y.cNoSc = a.sc ORDER BY y.dTanggal DESC, y.cNoSTB DESC) d
    WHERE  a.awal + a.stb - a.krm + a.ret + a.kor <> 0;

    DROP TABLE #a; DROP TABLE #stb; DROP TABLE #krm;
    DROP TABLE #ret; DROP TABLE #kor; DROP TABLE #kelsc;

    SELECT cKelompok, COUNT(*) AS jml_sc, SUM(nStokPc) AS total_pc, SUM(nStokKg) AS total_kg
    FROM   dbo.tbStokSnapTipe GROUP BY cKelompok ORDER BY SUM(nStokPc) DESC;
END
GO

/* ---------------------------------------------------------------------------
   JALANKAN & BANDINGKAN DENGAN SALDO AKHIR EXCEL PER 05 AGUSTUS
   --------------------------------------------------------------------------- */
EXEC dbo.spRefreshStokTipe '2026-08-05';
GO

-- Pembanding yang benar: SALDO AKHIR Excel 05 Agustus, bukan saldo awal 31 Juli
SELECT k.kategori,
       k.excel_05agu,
       ISNULL(y.pc, 0) AS sistem_05agu,
       ISNULL(y.pc, 0) - k.excel_05agu AS selisih,
       CAST(100.0 * (1 - ABS(ISNULL(y.pc,0) - k.excel_05agu)
            / NULLIF(CAST(k.excel_05agu AS DECIMAL(18,2)),0)) AS DECIMAL(5,2)) AS akurasi_persen
FROM   (SELECT 'BOX' AS kategori, 649713 AS excel_05agu
        UNION ALL SELECT 'PART+LAYER', 255891
        UNION ALL SELECT 'SHEET', 0) k
LEFT JOIN (SELECT cKelompok AS kategori, SUM(nStokPc) AS pc
           FROM dbo.tbStokSnapTipe GROUP BY cKelompok) y ON y.kategori = k.kategori;

-- Rincian aliran per kelompok
SELECT cKelompok, SUM(nSaldoAwal) AS saldo_awal, SUM(nStb) AS stb, SUM(nKirim) AS kirim,
       SUM(nRetur) AS retur, SUM(nKoreksi) AS koreksi, SUM(nStokPc) AS stok_akhir
FROM   dbo.tbStokSnapTipe GROUP BY cKelompok ORDER BY SUM(nStokPc) DESC;

-- NO. SC yang saldo awalnya dipindahkan kelompok. Harus ikut berubah dari
-- hasil sebelumnya, terutama SLC/2605/00880 dan SLC/2607/01006.
SELECT cNoSc, cKelompok, nSaldoAwal, nStb, nKirim, nStokPc, cNama
FROM   dbo.tbStokSnapTipe
WHERE  cNoSc IN ('SLC/2605/00880','SLC/2607/01006','SLC/2607/00099','SLC/2607/00377')
ORDER  BY cNoSc, cKelompok;

-- Sisa stok minus per kelompok, untuk ditindaklanjuti
SELECT TOP 25 cNoSc, cKelompok, cNama, cNamabrg, nSaldoAwal, nStb, nKirim, nStokPc
FROM   dbo.tbStokSnapTipe WHERE nStokPc < 0 ORDER BY nStokPc;
GO
