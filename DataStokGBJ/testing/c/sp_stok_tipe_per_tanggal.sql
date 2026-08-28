/* ============================================================================
   PT SUPRACOR SEJAHTERA — STOK PER TIPE PADA TANGGAL TERTENTU, TANPA MENULIS
   Dibuat : 14 Agustus 2026

   KENAPA PERLU PROSEDUR BARU
     spRefreshStokTipe menghitung dengan benar, tapi MENULIS hasilnya ke
     tbStokSnapTipe. Kalau dipakai untuk perbandingan, snapshot dashboard ikut
     berpindah ke tanggal lampau dan angka di layar jadi salah sampai dihitung
     ulang. Sudah pernah terjadi pada 10 Agustus.

     Prosedur ini rumusnya sama persis, tapi hasilnya dikembalikan langsung
     sebagai tabel. Tidak menyentuh tabel mana pun, jadi aman dijalankan
     kapan saja sambil dashboard tetap berjalan.

   DIPAKAI OLEH
     banding_backend.php, untuk halaman perbandingan Excel vs sistem.
   ============================================================================ */

USE dbSopanusa;
GO
SET NOCOUNT ON;
GO

IF OBJECT_ID('dbo.spStokTipePerTanggal') IS NOT NULL DROP PROCEDURE dbo.spStokTipePerTanggal;
GO

CREATE PROCEDURE dbo.spStokTipePerTanggal
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
        krm INT NOT NULL DEFAULT 0, ret INT NOT NULL DEFAULT 0,
        kor INT NOT NULL DEFAULT 0, pc INT NOT NULL DEFAULT 0,
        pindah INT NOT NULL DEFAULT 0,
        PRIMARY KEY (sc, kel)
    );

    INSERT INTO #a (sc, kel, awal)
    SELECT RTRIM(cNoScDb), cKategori, SUM(nStokAkhirPc)
    FROM   dbo.tbStokGudangExcel WHERE cNoScDb IS NOT NULL
    GROUP  BY RTRIM(cNoScDb), cKategori;

    IF OBJECT_ID('tempdb..#s') IS NOT NULL DROP TABLE #s;
    SELECT RTRIM(b.cNoSc) AS sc, ISNULL(h.cKelompok,'LAIN') AS kel, SUM(ISNULL(b.nQty,0)) AS q
    INTO   #s
    FROM   dbo.tbStbBJ b
    LEFT JOIN dbo.tbStokGudangHuruf h ON h.cHuruf = dbo.fnHurufTipe(b.cNoOp)
    WHERE  b.dTanggal > @Cut AND b.dTanggal < @Bts
      AND  b.cNoSc IS NOT NULL AND LTRIM(RTRIM(b.cNoSc)) <> ''
    GROUP  BY RTRIM(b.cNoSc), ISNULL(h.cKelompok,'LAIN');

    IF OBJECT_ID('tempdb..#k') IS NOT NULL DROP TABLE #k;
    SELECT RTRIM(COALESCE(d.cNoScDtl, s.cNoSC)) AS sc, ISNULL(h.cKelompok,'LAIN') AS kel,
           SUM(ISNULL(d.nQty,0)) AS q
    INTO   #k
    FROM   dbo.tbSRJ s
    INNER JOIN dbo.tbSRJDtl d ON d.cNoSRJ = s.cNoSRJ
    LEFT  JOIN dbo.tbStokGudangHuruf h ON h.cHuruf = dbo.fnHurufTipe(d.cNoOp)
    WHERE  s.dTanggal > @Cut AND s.dTanggal < @Bts
      AND  COALESCE(d.cNoScDtl, s.cNoSC) IS NOT NULL
    GROUP  BY RTRIM(COALESCE(d.cNoScDtl, s.cNoSC)), ISNULL(h.cKelompok,'LAIN');

    IF OBJECT_ID('tempdb..#r') IS NOT NULL DROP TABLE #r;
    SELECT RTRIM(rv.cNoSc) AS sc, ISNULL(p.kel,'BOX') AS kel, SUM(ISNULL(rv.nQty,0)) AS q
    INTO   #r
    FROM   dbo.vwReturnSrj rv
    OUTER APPLY (SELECT TOP 1 ISNULL(h.cKelompok,'LAIN') AS kel
                 FROM dbo.tbSRJDtl d
                 LEFT JOIN dbo.tbStokGudangHuruf h ON h.cHuruf = dbo.fnHurufTipe(d.cNoOp)
                 WHERE d.cNoSRJ = rv.cNoSrj AND RTRIM(d.cNoScDtl) = RTRIM(rv.cNoSc)) p
    WHERE  rv.dTgl > @Cut AND rv.dTgl < @Bts
      AND  rv.cNoSc IS NOT NULL AND LTRIM(RTRIM(rv.cNoSc)) <> ''
    GROUP  BY RTRIM(rv.cNoSc), ISNULL(p.kel,'BOX');

    IF OBJECT_ID('tempdb..#c') IS NOT NULL DROP TABLE #c;
    CREATE TABLE #c (sc VARCHAR(30), kel VARCHAR(12), q INT);
    IF OBJECT_ID('dbo.tbStokGudangKoreksi') IS NOT NULL
        INSERT INTO #c (sc, kel, q)
        SELECT RTRIM(cNoSc),
               CASE WHEN COL_LENGTH('dbo.tbStokGudangKoreksi','cKelompok') IS NULL
                    THEN 'BOX' ELSE ISNULL(NULLIF(RTRIM(cKelompok),''),'BOX') END,
               SUM(nQtyPc)
        FROM   dbo.tbStokGudangKoreksi
        WHERE  lVoid = 0 AND dTanggal > @Cut AND dTanggal < @Bts
        GROUP  BY RTRIM(cNoSc),
                  CASE WHEN COL_LENGTH('dbo.tbStokGudangKoreksi','cKelompok') IS NULL
                       THEN 'BOX' ELSE ISNULL(NULLIF(RTRIM(cKelompok),''),'BOX') END;

    INSERT INTO #a (sc, kel)
    SELECT DISTINCT z.sc, z.kel FROM (
        SELECT sc, kel FROM #s UNION SELECT sc, kel FROM #k
        UNION SELECT sc, kel FROM #r UNION SELECT sc, kel FROM #c
    ) z
    WHERE NOT EXISTS (SELECT 1 FROM #a a WHERE a.sc = z.sc AND a.kel = z.kel);

    UPDATE a SET a.stb = x.q FROM #a a INNER JOIN #s x ON x.sc = a.sc AND x.kel = a.kel;
    UPDATE a SET a.krm = x.q FROM #a a INNER JOIN #k x ON x.sc = a.sc AND x.kel = a.kel;
    UPDATE a SET a.ret = x.q FROM #a a INNER JOIN #r x ON x.sc = a.sc AND x.kel = a.kel;
    UPDATE a SET a.kor = x.q FROM #a a INNER JOIN #c x ON x.sc = a.sc AND x.kel = a.kel;

    UPDATE #a SET pc = awal + stb - krm + ret + kor;

    /* Saling tutup antar kelompok dalam satu NO. SC, sama seperti prosedur utama */
    IF OBJECT_ID('tempdb..#n') IS NOT NULL DROP TABLE #n;
    SELECT sc,
           SUM(CASE WHEN pc > 0 THEN pc ELSE 0 END)  AS pos,
           -SUM(CASE WHEN pc < 0 THEN pc ELSE 0 END) AS neg
    INTO   #n
    FROM   #a GROUP BY sc
    HAVING SUM(CASE WHEN pc < 0 THEN pc ELSE 0 END) < 0
       AND SUM(CASE WHEN pc > 0 THEN pc ELSE 0 END) > 0;

    UPDATE a
    SET    a.pindah = CASE WHEN a.pc < 0
                THEN  CAST(ROUND(1.0 * (-a.pc) / n.neg * (CASE WHEN n.pos < n.neg THEN n.pos ELSE n.neg END), 0) AS INT)
                ELSE -CAST(ROUND(1.0 * ( a.pc) / n.pos * (CASE WHEN n.pos < n.neg THEN n.pos ELSE n.neg END), 0) AS INT) END
    FROM   #a a INNER JOIN #n n ON n.sc = a.sc
    WHERE  a.pc <> 0;

    UPDATE #a SET pc = pc + pindah WHERE pindah <> 0;

    SELECT a.sc AS cNoSc, a.kel AS cKelompok,
           a.awal AS nSaldoAwal, a.stb AS nStb, a.krm AS nKirim,
           a.ret AS nRetur, a.kor AS nKoreksi, a.pc AS nStokPc,
           LEFT(ISNULL(e.cNama, ISNULL(d.cNama, '')), 255)       AS cNama,
           LEFT(ISNULL(e.cNamabrg, ISNULL(d.cNamabrg, '')), 500) AS cNamabrg
    FROM   #a a
    OUTER APPLY (SELECT TOP 1 x.cNama, x.cNamabrg FROM dbo.tbStokGudangExcel x
                 WHERE x.cNoScDb = a.sc ORDER BY x.nStokAkhirPc DESC) e
    OUTER APPLY (SELECT TOP 1 y.cNama, y.cNamabrg FROM dbo.tbStbBJ y
                 WHERE y.cNoSc = a.sc ORDER BY y.dTanggal DESC, y.cNoSTB DESC) d
    WHERE  a.pc <> 0
    ORDER  BY a.sc, a.kel;

    DROP TABLE #a; DROP TABLE #s; DROP TABLE #k; DROP TABLE #r; DROP TABLE #c; DROP TABLE #n;
END
GO

/* ---------------------------------------------------------------------------
   UJI — tidak mengubah apa pun
   --------------------------------------------------------------------------- */
EXEC dbo.spStokTipePerTanggal '2026-08-12';
GO
