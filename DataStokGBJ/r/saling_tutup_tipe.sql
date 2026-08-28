/* ============================================================================
   PT SUPRACOR SEJAHTERA — SALING TUTUP ANTAR TIPE DALAM SATU NO. SC
   Dibuat : 08 Agustus 2026

   PERJALANAN ANGKANYA
        Percobaan            BOX        PART+LAYER
        Excel 05 Agu      649.713         255.891     <- sasaran
        Percobaan 1       662.845         235.891     (+13.132 / -20.000)
        Percobaan 2       636.345         262.391     (-13.368 /  +6.500)

   KENAPA PERCOBAAN 2 MELESET
     Aturannya menebak: "kalau kategori Excel tidak cocok dengan tipe OP,
     pindahkan ke tipe dengan STB terbesar". Tebakan itu memindahkan 26.500 pc
     padahal yang benar-benar salah tempat cuma 20.000 pc. Sisanya 6.500 pc
     adalah NO. SC yang sebetulnya sudah benar.

   PENDEKATAN BARU — TIDAK MENEBAK SAMA SEKALI
     Datanya bisa menunjukkan sendiri mana yang salah tempat. Kalau dalam satu
     NO. SC ada kelompok yang MINUS sementara kelompok lain POSITIF, itu tanda
     pasti bahwa saldo dan mutasinya terpotong dari kelompok yang berbeda.
     Yang positif dipakai menutup yang minus, sebatas yang tersedia.

        SLC/2605/00880   BOX +9.600   PART -9.600   ->  keduanya jadi 0
        SLC/2607/01006   BOX +10.400  PART -10.400  ->  keduanya jadi 0
        SLC/2607/00099   BOX -13.200  PART -15.840  ->  dua-duanya minus,
                                                        tidak ada yang bisa
                                                        menutup, dibiarkan
                                                        supaya tetap terlihat

     NO. SC yang tidak punya masalah tidak disentuh sama sekali. Total stok
     per NO. SC juga tidak berubah, yang berpindah hanya antar kelompok.

   Jumlah yang dipindahkan disimpan di kolom nPindah, jadi selalu bisa
   diperiksa dan tidak terjadi diam-diam.
   ============================================================================ */

USE dbSopanusa;
GO
SET NOCOUNT ON;
GO

IF COL_LENGTH('dbo.tbStokSnapTipe', 'nPindah') IS NULL
    ALTER TABLE dbo.tbStokSnapTipe ADD nPindah INT NOT NULL DEFAULT 0;
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
        kor  INT NOT NULL DEFAULT 0, pc INT NOT NULL DEFAULT 0,
        pindah INT NOT NULL DEFAULT 0,
        PRIMARY KEY (sc, kel)
    );

    /* 1. Saldo awal APA ADANYA sesuai sheet di file gudang.
          Tidak ada tebakan di sini. Kalau penempatannya salah, akan ketahuan
          sendiri di langkah 7 karena memunculkan kelompok minus. */
    INSERT INTO #a (sc, kel, awal)
    SELECT RTRIM(cNoScDb), cKategori, SUM(nStokAkhirPc)
    FROM   dbo.tbStokGudangExcel WHERE cNoScDb IS NOT NULL
    GROUP  BY RTRIM(cNoScDb), cKategori;

    /* 2. STB per tipe */
    IF OBJECT_ID('tempdb..#stb') IS NOT NULL DROP TABLE #stb;
    SELECT RTRIM(b.cNoSc) AS sc, ISNULL(h.cKelompok, 'LAIN') AS kel,
           SUM(ISNULL(b.nQty,0)) AS q
    INTO   #stb
    FROM   dbo.tbStbBJ b
    LEFT JOIN dbo.tbStokGudangHuruf h ON h.cHuruf = dbo.fnHurufTipe(b.cNoOp)
    WHERE  b.dTanggal > @Cut AND b.dTanggal < @Bts
      AND  b.cNoSc IS NOT NULL AND LTRIM(RTRIM(b.cNoSc)) <> ''
    GROUP  BY RTRIM(b.cNoSc), ISNULL(h.cKelompok, 'LAIN');

    /* 3. Kirim per tipe */
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

    /* 4. Retur */
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

    /* 5. Koreksi manual */
    IF OBJECT_ID('tempdb..#kor') IS NOT NULL DROP TABLE #kor;
    CREATE TABLE #kor (sc VARCHAR(30), kel VARCHAR(12), q INT);
    IF OBJECT_ID('dbo.tbStokGudangKoreksi') IS NOT NULL
        INSERT INTO #kor (sc, kel, q)
        SELECT RTRIM(cNoSc), 'BOX', SUM(nQtyPc)
        FROM   dbo.tbStokGudangKoreksi
        WHERE  lVoid = 0 AND dTanggal > @Cut AND dTanggal < @Bts
        GROUP  BY RTRIM(cNoSc);

    /* 6. Satukan dan hitung */
    INSERT INTO #a (sc, kel)
    SELECT DISTINCT z.sc, z.kel FROM (
        SELECT sc, kel FROM #stb UNION SELECT sc, kel FROM #krm UNION
        SELECT sc, kel FROM #ret UNION SELECT sc, kel FROM #kor
    ) z
    WHERE NOT EXISTS (SELECT 1 FROM #a a WHERE a.sc = z.sc AND a.kel = z.kel);

    UPDATE a SET a.stb = x.q FROM #a a INNER JOIN #stb x ON x.sc = a.sc AND x.kel = a.kel;
    UPDATE a SET a.krm = x.q FROM #a a INNER JOIN #krm x ON x.sc = a.sc AND x.kel = a.kel;
    UPDATE a SET a.ret = x.q FROM #a a INNER JOIN #ret x ON x.sc = a.sc AND x.kel = a.kel;
    UPDATE a SET a.kor = x.q FROM #a a INNER JOIN #kor x ON x.sc = a.sc AND x.kel = a.kel;

    UPDATE #a SET pc = awal + stb - krm + ret + kor;

    /* ------------------------------------------------------------------
       7. SALING TUTUP DALAM SATU NO. SC
       Hanya berlaku bila dalam satu NO. SC ada kelompok minus DAN kelompok
       positif sekaligus. Yang dipindahkan sebatas yang tersedia.
       ------------------------------------------------------------------ */
    IF OBJECT_ID('tempdb..#net') IS NOT NULL DROP TABLE #net;
    SELECT sc,
           SUM(CASE WHEN pc > 0 THEN pc ELSE 0 END)  AS pos,
           -SUM(CASE WHEN pc < 0 THEN pc ELSE 0 END) AS neg
    INTO   #net
    FROM   #a
    GROUP  BY sc
    HAVING SUM(CASE WHEN pc < 0 THEN pc ELSE 0 END) < 0
       AND SUM(CASE WHEN pc > 0 THEN pc ELSE 0 END) > 0;

    UPDATE a
    SET    a.pindah =
           CASE WHEN a.pc < 0
                THEN  CAST(ROUND(1.0 * (-a.pc) / n.neg *
                        (CASE WHEN n.pos < n.neg THEN n.pos ELSE n.neg END), 0) AS INT)
                ELSE -CAST(ROUND(1.0 * ( a.pc) / n.pos *
                        (CASE WHEN n.pos < n.neg THEN n.pos ELSE n.neg END), 0) AS INT)
           END
    FROM   #a a INNER JOIN #net n ON n.sc = a.sc
    WHERE  a.pc <> 0;

    UPDATE #a SET pc = pc + pindah WHERE pindah <> 0;

    /* 8. Simpan */
    TRUNCATE TABLE dbo.tbStokSnapTipe;

    INSERT INTO dbo.tbStokSnapTipe
          (cNoSc, cKelompok, cNama, cNamabrg, nSaldoAwal, nStb, nKirim, nRetur,
           nKoreksi, nPindah, nStokPc, nStokKg, dPosisi)
    SELECT a.sc, a.kel,
           LEFT(ISNULL(e.cNama, ISNULL(d.cNama, '')), 255),
           LEFT(ISNULL(e.cNamabrg, ISNULL(d.cNamabrg, '')), 500),
           a.awal, a.stb, a.krm, a.ret, a.kor, a.pindah, a.pc,
           CAST(a.pc * ISNULL(d.nberat, 0) AS DECIMAL(18,3)),
           @Posisi
    FROM   #a a
    OUTER APPLY (SELECT TOP 1 x.cNama, x.cNamabrg FROM dbo.tbStokGudangExcel x
                 WHERE x.cNoScDb = a.sc ORDER BY x.nStokAkhirPc DESC) e
    OUTER APPLY (SELECT TOP 1 y.cNama, y.cNamabrg, y.nberat FROM dbo.tbStbBJ y
                 WHERE y.cNoSc = a.sc ORDER BY y.dTanggal DESC, y.cNoSTB DESC) d
    WHERE  a.pc <> 0;

    DROP TABLE #a; DROP TABLE #stb; DROP TABLE #krm;
    DROP TABLE #ret; DROP TABLE #kor; DROP TABLE #net;

    SELECT cKelompok, COUNT(*) AS jml_sc, SUM(nStokPc) AS total_pc, SUM(nStokKg) AS total_kg
    FROM   dbo.tbStokSnapTipe GROUP BY cKelompok ORDER BY SUM(nStokPc) DESC;
END
GO

/* ---------------------------------------------------------------------------
   JALANKAN & PERIKSA
   --------------------------------------------------------------------------- */
EXEC dbo.spRefreshStokTipe '2026-08-05';
GO

-- 1. Sasaran: BOX 649.713, PART+LAYER 255.891
SELECT k.kategori, k.excel_05agu, ISNULL(y.pc, 0) AS sistem_05agu,
       ISNULL(y.pc, 0) - k.excel_05agu AS selisih,
       CAST(100.0 * (1 - ABS(ISNULL(y.pc,0) - k.excel_05agu)
            / NULLIF(CAST(k.excel_05agu AS DECIMAL(18,2)),0)) AS DECIMAL(5,2)) AS akurasi_persen
FROM   (SELECT 'BOX' AS kategori, 649713 AS excel_05agu
        UNION ALL SELECT 'PART+LAYER', 255891
        UNION ALL SELECT 'SHEET', 0) k
LEFT JOIN (SELECT cKelompok AS kategori, SUM(nStokPc) AS pc
           FROM dbo.tbStokSnapTipe GROUP BY cKelompok) y ON y.kategori = k.kategori;

-- 2. Berapa yang dipindahkan antar kelompok, dan pada NO. SC mana
SELECT COUNT(DISTINCT cNoSc) AS jml_sc_dipindah,
       SUM(CASE WHEN nPindah > 0 THEN nPindah ELSE 0 END) AS total_dipindahkan
FROM   dbo.tbStokSnapTipe WHERE nPindah <> 0;

SELECT cNoSc, cKelompok, nSaldoAwal, nStb, nKirim, nPindah, nStokPc, cNama
FROM   dbo.tbStokSnapTipe
WHERE  cNoSc IN (SELECT cNoSc FROM dbo.tbStokSnapTipe WHERE nPindah <> 0)
ORDER  BY cNoSc, cKelompok;

-- 3. Aliran per kelompok
SELECT cKelompok, SUM(nSaldoAwal) AS saldo_awal, SUM(nStb) AS stb, SUM(nKirim) AS kirim,
       SUM(nRetur) AS retur, SUM(nKoreksi) AS koreksi, SUM(nPindah) AS pindah,
       SUM(nStokPc) AS stok_akhir
FROM   dbo.tbStokSnapTipe GROUP BY cKelompok ORDER BY SUM(nStokPc) DESC;

-- 4. Sisa minus. Ini kasus nyata yang perlu koreksi manual lewat dashboard.
SELECT TOP 25 cNoSc, cKelompok, cNama, cNamabrg, nSaldoAwal, nStb, nKirim, nStokPc
FROM   dbo.tbStokSnapTipe WHERE nStokPc < 0 ORDER BY nStokPc;
GO
