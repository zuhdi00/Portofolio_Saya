/* ============================================================================
   PT SUPRACOR SEJAHTERA — STOK PER TIPE BARANG, VERSI PASTI
   Dibuat : 07 Agustus 2026

   DUA TEMUAN DARI HASIL UJI SEBELUMNYA

   1. tbSRJDtl TERNYATA PUNYA cNoOp DAN cTipe
      Berarti barang KELUAR juga bisa dipisah per tipe dengan pasti, sama
      seperti barang masuk. Kolom ESTIMASI tidak diperlukan lagi. Seluruh
      pemisahan di file ini PASTI, bukan perkiraan.

   2. PENGGOLONGAN SEBELUMNYA SALAH URUTAN
      Versi lama memeriksa nama barang lebih dulu, baru akhiran cNoOp.
      Padahal cNamabrg itu nama SC, bukan nama tiap baris OP. Akibatnya
      SLC/2607/00056 yang bernama "PART PENDEK" tapi ber-OP -B01 terbelah
      33% BOX / 67% PART, padahal per OP sudah jelas.
      Sekarang penggolongan MURNI dari akhiran cNoOp.

   3. DUA HURUF BELUM DIPETAKAN
      Huruf T (230.025 pc) dan F (14.729 pc) jatuh ke LAIN. Dari contoh
      itemnya: T adalah tutup dan kaki box, F adalah lembaran.
      Inilah sebab BOX sistem cuma 576.388 padahal patokan 777.727.

   PEMETAAN HURUF AKHIRAN cNoOp
        B  BOX      contoh: CARTON BOX 500G, ZENITH
        T  TUTUP    contoh: BOX KAKI - 4400, TUTUP BOX WONCHICK
        P  PART     contoh: PARTISI 2L1, PART PANJANG
        L  LAYER    contoh: DEVIDER BYGN PART, Z-PARTITION
        S  SHEET    contoh: CARTON SHEET 110 X 110, SLIP SHEET PAPERBOARD
        F  SHEET    contoh: SF 1100 MM, SHEET CARTON CORRUGATED 2000 MM

   KELOMPOK, MENGIKUTI PEMBAGIAN SHEET DI FILE GUDANG
        BOX         = B + T      (tutup dan kaki memang tercatat di sheet BOX)
        PART+LAYER  = P + L
        SHEET       = S + F       (tidak ada di file gudang)
        LAIN        = tanpa akhiran
   ============================================================================ */

USE dbSopanusa;
GO
SET NOCOUNT ON;
GO

/* ---------------------------------------------------------------------------
   LANGKAH 1 — TABEL PEMETAAN HURUF, SUPAYA BISA DIUBAH TANPA UBAH PROGRAM
   --------------------------------------------------------------------------- */
IF OBJECT_ID('dbo.tbStokGudangHuruf') IS NOT NULL DROP TABLE dbo.tbStokGudangHuruf;
GO
CREATE TABLE dbo.tbStokGudangHuruf (
    cHuruf    CHAR(1)     NOT NULL PRIMARY KEY,
    cTipe     VARCHAR(10) NOT NULL,
    cKelompok VARCHAR(12) NOT NULL,
    cContoh   NVARCHAR(120) NULL
);
INSERT INTO dbo.tbStokGudangHuruf (cHuruf, cTipe, cKelompok, cContoh) VALUES
('B','BOX',  'BOX',        N'CARTON BOX 500G INSTANT SUCCESS'),
('T','TUTUP','BOX',        N'BOX KAKI - 4400, TUTUP BOX WONCHICK'),
('P','PART', 'PART+LAYER', N'PARTISI 2L1, PART PANJANG'),
('L','LAYER','PART+LAYER', N'DEVIDER BYGN PART 72 X 6, Z-PARTITION'),
('S','SHEET','SHEET',      N'CARTON SHEET 110 X 110 CM'),
('F','SHEET','SHEET',      N'SF 1100 MM, SHEET CARTON CORRUGATED');
GO

/* Fungsi pembantu: ambil huruf tipe dari sebuah nomor OP */
IF OBJECT_ID('dbo.fnHurufTipe') IS NOT NULL DROP FUNCTION dbo.fnHurufTipe;
GO
CREATE FUNCTION dbo.fnHurufTipe (@cNoOp VARCHAR(30))
RETURNS CHAR(1)
AS
BEGIN
    DECLARE @s VARCHAR(30) = RTRIM(LTRIM(ISNULL(@cNoOp, '')));
    IF CHARINDEX('-', @s) = 0 RETURN '?';
    RETURN UPPER(SUBSTRING(@s, CHARINDEX('-', @s) + 1, 1));
END
GO

/* ---------------------------------------------------------------------------
   LANGKAH 2 — TABEL HASIL
   --------------------------------------------------------------------------- */
IF OBJECT_ID('dbo.vwStokPerTipe')      IS NOT NULL DROP VIEW  dbo.vwStokPerTipe;
IF OBJECT_ID('dbo.tbStokGudangTipe')   IS NOT NULL DROP TABLE dbo.tbStokGudangTipe;
IF OBJECT_ID('dbo.tbStokSnapTipe')     IS NOT NULL DROP TABLE dbo.tbStokSnapTipe;
GO
CREATE TABLE dbo.tbStokSnapTipe (
    cNoSc      VARCHAR(30)   NOT NULL,
    cKelompok  VARCHAR(12)   NOT NULL,
    cNama      NVARCHAR(255) NULL,
    cNamabrg   NVARCHAR(500) NULL,
    nSaldoAwal INT           NOT NULL DEFAULT 0,
    nStb       INT           NOT NULL DEFAULT 0,
    nKirim     INT           NOT NULL DEFAULT 0,
    nRetur     INT           NOT NULL DEFAULT 0,
    nKoreksi   INT           NOT NULL DEFAULT 0,
    nStokPc    INT           NOT NULL DEFAULT 0,
    nStokKg    DECIMAL(18,3) NOT NULL DEFAULT 0,
    dPosisi    DATE          NOT NULL,
    dHitung    DATETIME      NOT NULL DEFAULT GETDATE(),
    CONSTRAINT PK_tbStokSnapTipe PRIMARY KEY (cNoSc, cKelompok)
);
GO

/* ---------------------------------------------------------------------------
   LANGKAH 3 — PROSEDUR HITUNG STOK PER TIPE
   Semua komponen dipisah lewat cNoOp, jadi pasti. Hanya retur yang tidak
   punya cNoOp sendiri, sehingga dilacak lewat baris surat jalannya.
   --------------------------------------------------------------------------- */
IF OBJECT_ID('dbo.spRefreshStokTipe') IS NOT NULL DROP PROCEDURE dbo.spRefreshStokTipe;
GO
CREATE PROCEDURE dbo.spRefreshStokTipe
    @Posisi DATE = NULL          -- kosong = hari ini
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

    /* 1. Saldo awal dari patokan Excel. cKategori sudah BOX / PART+LAYER. */
    INSERT INTO #a (sc, kel, awal)
    SELECT RTRIM(cNoScDb), cKategori, SUM(nStokAkhirPc)
    FROM   dbo.tbStokGudangExcel
    WHERE  cNoScDb IS NOT NULL
    GROUP  BY RTRIM(cNoScDb), cKategori;

    /* 2. STB per tipe, dari akhiran cNoOp */
    IF OBJECT_ID('tempdb..#stb') IS NOT NULL DROP TABLE #stb;
    SELECT RTRIM(b.cNoSc) AS sc,
           ISNULL(h.cKelompok, 'LAIN') AS kel,
           SUM(ISNULL(b.nQty,0)) AS q
    INTO   #stb
    FROM   dbo.tbStbBJ b
    LEFT JOIN dbo.tbStokGudangHuruf h ON h.cHuruf = dbo.fnHurufTipe(b.cNoOp)
    WHERE  b.dTanggal > @Cut AND b.dTanggal < @Bts
      AND  b.cNoSc IS NOT NULL AND LTRIM(RTRIM(b.cNoSc)) <> ''
    GROUP  BY RTRIM(b.cNoSc), ISNULL(h.cKelompok, 'LAIN');

    /* 3. Kirim per tipe, dari akhiran cNoOp di tbSRJDtl */
    IF OBJECT_ID('tempdb..#krm') IS NOT NULL DROP TABLE #krm;
    SELECT RTRIM(COALESCE(d.cNoScDtl, s.cNoSC)) AS sc,
           ISNULL(h.cKelompok, 'LAIN') AS kel,
           SUM(ISNULL(d.nQty,0)) AS q
    INTO   #krm
    FROM   dbo.tbSRJ s
    INNER JOIN dbo.tbSRJDtl d ON d.cNoSRJ = s.cNoSRJ
    LEFT  JOIN dbo.tbStokGudangHuruf h ON h.cHuruf = dbo.fnHurufTipe(d.cNoOp)
    WHERE  s.dTanggal > @Cut AND s.dTanggal < @Bts
      AND  COALESCE(d.cNoScDtl, s.cNoSC) IS NOT NULL
    GROUP  BY RTRIM(COALESCE(d.cNoScDtl, s.cNoSC)), ISNULL(h.cKelompok, 'LAIN');

    /* 4. Retur. Tidak punya cNoOp sendiri, jadi tipenya dilacak lewat baris
          surat jalan yang bersangkutan. Kalau tidak ketemu, masuk BOX. */
    IF OBJECT_ID('tempdb..#ret') IS NOT NULL DROP TABLE #ret;
    SELECT RTRIM(rv.cNoSc) AS sc,
           ISNULL(p.kel, 'BOX') AS kel,
           SUM(ISNULL(rv.nQty,0)) AS q
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

    /* 5. Koreksi manual, kalau tabelnya sudah ada */
    IF OBJECT_ID('tempdb..#kor') IS NOT NULL DROP TABLE #kor;
    CREATE TABLE #kor (sc VARCHAR(30), kel VARCHAR(12), q INT);
    IF OBJECT_ID('dbo.tbStokGudangKoreksi') IS NOT NULL
        INSERT INTO #kor (sc, kel, q)
        SELECT RTRIM(cNoSc),
               CASE WHEN COL_LENGTH('dbo.tbStokGudangKoreksi','cKelompok') IS NULL
                    THEN 'BOX' ELSE 'BOX' END,     -- lihat catatan di bawah file
               SUM(nQtyPc)
        FROM   dbo.tbStokGudangKoreksi
        WHERE  lVoid = 0 AND dTanggal > @Cut AND dTanggal < @Bts
        GROUP  BY RTRIM(cNoSc);

    /* 6. Satukan semua kunci */
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

    /* 7. Simpan */
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
                 WHERE x.cNoScDb = a.sc AND x.cKategori = a.kel) e
    OUTER APPLY (SELECT TOP 1 y.cNama, y.cNamabrg, y.nberat FROM dbo.tbStbBJ y
                 WHERE y.cNoSc = a.sc ORDER BY y.dTanggal DESC, y.cNoSTB DESC) d
    WHERE  a.awal + a.stb - a.krm + a.ret + a.kor <> 0;

    DROP TABLE #a; DROP TABLE #stb; DROP TABLE #krm; DROP TABLE #ret; DROP TABLE #kor;

    SELECT cKelompok, COUNT(*) AS jml_sc, SUM(nStokPc) AS total_pc, SUM(nStokKg) AS total_kg
    FROM   dbo.tbStokSnapTipe GROUP BY cKelompok ORDER BY SUM(nStokPc) DESC;
END
GO

/* ---------------------------------------------------------------------------
   LANGKAH 4 — JALANKAN PADA POSISI 05 AGUSTUS, LALU BANDINGKAN
   --------------------------------------------------------------------------- */
EXEC dbo.spRefreshStokTipe '2026-08-05';
GO

-- 4a. INI JAWABANNYA. Bandingkan langsung dengan sheet di file gudang.
SELECT k.kategori,
       ISNULL(x.pc, 0) AS patokan_excel_31jul,
       ISNULL(y.pc, 0) AS stok_sistem_05agu,
       ISNULL(y.pc, 0) - ISNULL(x.pc, 0) AS selisih
FROM   (SELECT 'BOX' AS kategori UNION ALL SELECT 'PART+LAYER'
        UNION ALL SELECT 'SHEET' UNION ALL SELECT 'LAIN') k
LEFT JOIN (SELECT cKategori AS kategori, SUM(nStokAkhirPc) AS pc
           FROM dbo.tbStokGudangExcel GROUP BY cKategori) x ON x.kategori = k.kategori
LEFT JOIN (SELECT cKelompok AS kategori, SUM(nStokPc) AS pc
           FROM dbo.tbStokSnapTipe GROUP BY cKelompok) y ON y.kategori = k.kategori;

-- 4b. Rincian komponen per kelompok, supaya kelihatan alirannya
SELECT cKelompok,
       SUM(nSaldoAwal) AS saldo_awal, SUM(nStb) AS stb, SUM(nKirim) AS kirim,
       SUM(nRetur) AS retur, SUM(nKoreksi) AS koreksi, SUM(nStokPc) AS stok_akhir
FROM   dbo.tbStokSnapTipe GROUP BY cKelompok ORDER BY SUM(nStokPc) DESC;

-- 4c. NO. SC yang punya lebih dari satu kelompok, kini terpisah rapi
SELECT cNoSc, cKelompok, nSaldoAwal, nStb, nKirim, nStokPc, cNama
FROM   dbo.tbStokSnapTipe
WHERE  cNoSc IN (SELECT cNoSc FROM dbo.tbStokSnapTipe GROUP BY cNoSc HAVING COUNT(*) > 1)
ORDER  BY cNoSc, cKelompok;

-- 4d. Baris tanpa akhiran OP, perlu dicek apakah penomorannya benar
SELECT TOP 20 cNoSc, cKelompok, cNama, cNamabrg, nStokPc
FROM   dbo.tbStokSnapTipe WHERE cKelompok = 'LAIN' ORDER BY ABS(nStokPc) DESC;
GO

/* ---------------------------------------------------------------------------
   CATATAN — KOREKSI MANUAL DAN TIPE
   tbStokGudangKoreksi belum punya kolom kelompok, jadi untuk sementara semua
   koreksi dianggap BOX. Tabelnya masih kosong, jadi aman ditambah sekarang:

     ALTER TABLE dbo.tbStokGudangKoreksi
         ADD cKelompok VARCHAR(12) NOT NULL DEFAULT 'BOX';

   Setelah itu ganti bagian nomor 5 di prosedur supaya memakai kolom tersebut,
   dan tambahkan parameter @cKelompok di spTambahKoreksiStok.
   --------------------------------------------------------------------------- */
