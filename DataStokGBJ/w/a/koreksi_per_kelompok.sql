/* ============================================================================
   PT SUPRACOR SEJAHTERA — KOREKSI BISA MEMILIH KELOMPOK TIPE
   Dibuat : 10 Agustus 2026

   KEADAAN SEKARANG
     Semua koreksi manual dipaksa masuk kelompok BOX, karena tbStokGudangKoreksi
     belum punya kolom kelompok. Untuk NO. OP yang punya stok di dua kelompok
     sekaligus, koreksi Part+Layer akan salah tempat.

     Contoh SLC/2607/01151 ETIKA DAIRIES:
        BOX          36.000 pc
        PART+LAYER   11.520 pc
     Koreksi -1 yang dimaksudkan untuk Part+Layer akan mengurangi Box.

   YANG DIKERJAKAN FILE INI
     1. Tambah kolom cKelompok di tbStokGudangKoreksi
     2. spTambahKoreksiStok menerima parameter @cKelompok, dengan pemeriksaan
     3. spRefreshStokTipe memakai kolom itu, tidak lagi memaksa BOX

   Tabel koreksi masih berisi satu baris uji coba, jadi aman diubah sekarang.
   Baris lama otomatis dianggap BOX, sesuai perilaku selama ini.
   ============================================================================ */

USE dbSopanusa;
GO
SET NOCOUNT ON;
GO

/* ---------------------------------------------------------------------------
   LANGKAH 1 — TAMBAH KOLOM
   --------------------------------------------------------------------------- */
IF COL_LENGTH('dbo.tbStokGudangKoreksi', 'cKelompok') IS NULL
    ALTER TABLE dbo.tbStokGudangKoreksi
        ADD cKelompok VARCHAR(12) NOT NULL CONSTRAINT DF_Koreksi_Kelompok DEFAULT 'BOX';
GO

SELECT nId, cNoSc, cKelompok, nQtyPc, cJenis, lVoid
FROM   dbo.tbStokGudangKoreksi ORDER BY nId;
GO

/* ---------------------------------------------------------------------------
   LANGKAH 2 — PROSEDUR TAMBAH KOREKSI, KINI DENGAN KELOMPOK
   --------------------------------------------------------------------------- */
IF OBJECT_ID('dbo.spTambahKoreksiStok') IS NOT NULL DROP PROCEDURE dbo.spTambahKoreksiStok;
GO
CREATE PROCEDURE dbo.spTambahKoreksiStok
    @cNoSc       VARCHAR(30),
    @nQtyPc      INT,
    @cJenis      VARCHAR(30),
    @cKeterangan NVARCHAR(300),
    @UserId      VARCHAR(50),
    @dTanggal    DATE          = NULL,
    @cNoBukti    VARCHAR(50)   = NULL,
    @cDivisi     VARCHAR(30)   = NULL,
    @cKelompok   VARCHAR(12)   = NULL      -- BOX / PART+LAYER / SHEET / LAIN
AS
BEGIN
    SET NOCOUNT ON;
    SET @dTanggal  = ISNULL(@dTanggal, CAST(GETDATE() AS DATE));
    SET @cNoSc     = RTRIM(LTRIM(@cNoSc));
    SET @cKelompok = NULLIF(RTRIM(LTRIM(ISNULL(@cKelompok, ''))), '');

    IF @cNoSc = '' OR @cNoSc IS NULL
        THROW 51001, 'Nomor SC wajib diisi.', 1;
    IF @nQtyPc = 0
        THROW 51002, 'Jumlah koreksi tidak boleh nol.', 1;
    IF LTRIM(RTRIM(ISNULL(@cKeterangan,''))) = ''
        THROW 51003, 'Keterangan wajib diisi. Tulis alasan koreksinya supaya bisa ditelusuri.', 1;
    IF LTRIM(RTRIM(ISNULL(@UserId,''))) = ''
        THROW 51004, 'UserId wajib diisi.', 1;
    IF NOT EXISTS (SELECT 1 FROM dbo.tbStokGudangJenisKoreksi WHERE cJenis = @cJenis AND lAktif = 1)
        THROW 51005, 'Jenis koreksi tidak dikenal. Lihat tabel tbStokGudangJenisKoreksi.', 1;
    IF NOT EXISTS (SELECT 1 FROM dbo.tbStbBJ b WHERE RTRIM(b.cNoSc) = @cNoSc)
       AND NOT EXISTS (SELECT 1 FROM dbo.tbStokGudangExcel e WHERE e.cNoScDb = @cNoSc)
        THROW 51006, 'Nomor SC tidak ditemukan di tbStbBJ maupun di patokan gudang.', 1;

    DECLARE @Cut DATE = (SELECT MAX(dCutOff) FROM dbo.tbStokGudangExcel);
    IF @dTanggal <= @Cut
        THROW 51007, 'Tanggal koreksi harus SETELAH tanggal cut-off patokan. Koreksi sebelum cut-off tidak berpengaruh, karena saldo awal sudah mencakupnya.', 1;
    IF @dTanggal > CAST(GETDATE() AS DATE)
        THROW 51008, 'Tanggal koreksi tidak boleh di masa depan.', 1;

    /* Kelompok: kalau tidak diisi, ikuti kelompok yang stoknya terbesar pada
       NO. SC tersebut. Kalau SC-nya belum punya stok sama sekali, pakai BOX. */
    IF @cKelompok IS NULL
        SELECT TOP 1 @cKelompok = cKelompok
        FROM   dbo.tbStokSnapTipe WHERE cNoSc = @cNoSc
        ORDER  BY ABS(nStokPc) DESC;
    SET @cKelompok = ISNULL(@cKelompok, 'BOX');

    IF @cKelompok NOT IN ('BOX','PART+LAYER','SHEET','LAIN')
        THROW 51009, 'Kelompok tipe tidak dikenal. Pilihannya BOX, PART+LAYER, SHEET, atau LAIN.', 1;

    INSERT INTO dbo.tbStokGudangKoreksi
          (cNoSc, dTanggal, nQtyPc, cJenis, cKeterangan, cNoBukti, UserId, cDivisi, cKelompok)
    VALUES(@cNoSc, @dTanggal, @nQtyPc, @cJenis, @cKeterangan, @cNoBukti, @UserId, @cDivisi, @cKelompok);

    SELECT SCOPE_IDENTITY() AS nId, @cNoSc AS cNoSc, @nQtyPc AS nQtyPc, @cKelompok AS cKelompok,
           (SELECT ISNULL(SUM(nQtyPc),0) FROM dbo.tbStokGudangKoreksi
            WHERE cNoSc = @cNoSc AND lVoid = 0) AS total_koreksi_sc;
END
GO

/* ---------------------------------------------------------------------------
   LANGKAH 3 — spRefreshStokTipe MEMAKAI KOLOM KELOMPOK
   --------------------------------------------------------------------------- */
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
        SELECT RTRIM(cNoSc), ISNULL(NULLIF(RTRIM(cKelompok), ''), 'BOX'), SUM(nQtyPc)
        FROM   dbo.tbStokGudangKoreksi
        WHERE  lVoid = 0 AND dTanggal > @Cut AND dTanggal < @Bts
        GROUP  BY RTRIM(cNoSc), ISNULL(NULLIF(RTRIM(cKelompok), ''), 'BOX');

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
   LANGKAH 4 — HITUNG ULANG & PERIKSA
   --------------------------------------------------------------------------- */
EXEC dbo.spRefreshSemuaStok @Sumber = 'KELOMPOK';
GO

SELECT cKelompok, COUNT(*) AS jml_sc, SUM(nStokPc) AS total_pc FROM dbo.tbStokSnapTipe
GROUP BY cKelompok ORDER BY SUM(nStokPc) DESC;

-- Koreksi yang berlaku, kini lengkap dengan kelompoknya
SELECT nId, cNoSc, cKelompok, dTanggal, nQtyPc, cJenis, UserId, lVoid
FROM   dbo.tbStokGudangKoreksi ORDER BY nId DESC;

-- Rincian SLC/2607/01151, contoh NO. OP yang punya dua kelompok
SELECT cNoSc, cKelompok, nSaldoAwal, nStb, nKirim, nKoreksi, nStokPc
FROM   dbo.tbStokSnapTipe WHERE cNoSc = 'SLC/2607/01151' ORDER BY cKelompok;
GO
