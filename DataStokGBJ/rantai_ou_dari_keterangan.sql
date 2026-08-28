/* ============================================================================
   PT SUPRACOR SEJAHTERA — RANTAI ORDER ULANG DARI tbSC.cKeterangan
   Dibuat : 10 Agustus 2026

   TEMUAN

   1. NOMOR OU ADA DI DALAM TEKS KETERANGAN
        tbSC.cKeterangan = 'OU SLC/2602/00235 - POSISI STITCH DITEPI ...'
        tbSC.cJnsSc      = 'OU'
      Bukan kolom tersendiri, tapi bisa diambil dari awal teksnya.

   2. KASUS INTERBAT TUNTAS
                            per 31 Juli menurut     menurut
                            database                file Excel
        SLC/2605/00397           900                    0
        SLC/2606/00551             0                  900
      Persis tertukar. Database benar: 900 pc memang tersimpan di 2605/00397
      dan baru keluar 05 Agustus. Gudang mencatatnya di nomor SC yang lebih
      baru karena fisiknya satu tumpukan.

   3. DUGAAN SAYA SOAL dTglKirim TERBUKTI SALAH
        SRJ/2605/00397-02   tgl dokumen 2026-08-05   tgl kirim 2026-06-05
      Angka 2026-06-05 itu sama dengan dTglKirim di SC-nya, jadi kolom itu
      TANGGAL RENCANA KIRIM, bukan tanggal barang keluar. Tidak boleh dipakai
      sebagai penyaring stok. Usulan sebelumnya dibatalkan.

   4. HASIL PENCOCOKAN PASANGAN
        TERTUTUP PENUH      27 OP    -90.370  bisa ditutup semua
        TERTUTUP SEBAGIAN    7 OP    -27.062  tertutup 15.362
        TIDAK ADA PASANGAN  43 OP   -108.798  perlu dicek fisik
        Total               77 OP   -226.230  sisa nyata -120.498

      Yang tidak punya pasangan didominasi barang SHEET, yang memang tidak
      dicatat di file gudang sama sekali.

   FILE INI HANYA MEMBACA sampai Langkah 4.
   ============================================================================ */

USE dbSopanusa;
GO
SET NOCOUNT ON;
SET TRANSACTION ISOLATION LEVEL READ UNCOMMITTED;
GO

/* ---------------------------------------------------------------------------
   LANGKAH 1 — PERIKSA POLA PENULISAN OU
   Sebelum mengambil otomatis, pastikan penulisannya seragam.
   --------------------------------------------------------------------------- */
SELECT TOP 20 LEFT(cKeterangan, 25) AS awal_keterangan, COUNT(*) AS jml
FROM   dbo.tbSC
WHERE  cKeterangan LIKE 'OU%' OR cJnsSc = 'OU'
GROUP  BY LEFT(cKeterangan, 25)
ORDER  BY COUNT(*) DESC;

SELECT cJnsSc, COUNT(*) AS jml,
       SUM(CASE WHEN cKeterangan LIKE 'OU SLC/%' THEN 1 ELSE 0 END) AS ketemu_nomor_ou
FROM   dbo.tbSC GROUP BY cJnsSc ORDER BY COUNT(*) DESC;
GO

/* ---------------------------------------------------------------------------
   LANGKAH 2 — AMBIL NOMOR OU JADI TABEL PEMETAAN
   --------------------------------------------------------------------------- */
IF OBJECT_ID('tempdb..#ou') IS NOT NULL DROP TABLE #ou;

SELECT sc, induk
INTO   #ou
FROM (
    SELECT RTRIM(cNoSC) AS sc,
           RTRIM(LEFT(SUBSTRING(cKeterangan, 4, 25),
                 CHARINDEX(' ', SUBSTRING(cKeterangan, 4, 25) + ' ') - 1)) AS induk
    FROM   dbo.tbSC
    WHERE  cKeterangan LIKE 'OU SLC/%'
) z
WHERE  induk LIKE 'SLC/%' AND LEN(induk) BETWEEN 12 AND 20 AND induk <> sc;

CREATE UNIQUE CLUSTERED INDEX IX_ou ON #ou (sc);

SELECT COUNT(*) AS jml_sc_punya_induk FROM #ou;
SELECT TOP 15 * FROM #ou ORDER BY sc DESC;

-- Cek kasus INTERBAT
SELECT * FROM #ou WHERE sc IN ('SLC/2605/00397','SLC/2606/00551');
GO

/* ---------------------------------------------------------------------------
   LANGKAH 3 — TELUSURI SAMPAI PANGKAL RANTAI
   2606/00551 -> 2605/00397 -> 2602/00235. Pangkalnya 2602/00235.
   --------------------------------------------------------------------------- */
IF OBJECT_ID('tempdb..#akar') IS NOT NULL DROP TABLE #akar;

;WITH jalur AS (
    SELECT sc, induk AS akar, 1 AS tingkat FROM #ou
    UNION ALL
    SELECT j.sc, o.induk, j.tingkat + 1
    FROM   jalur j INNER JOIN #ou o ON o.sc = j.akar
    WHERE  j.tingkat < 12
),
puncak AS (
    SELECT sc, akar, tingkat,
           ROW_NUMBER() OVER (PARTITION BY sc ORDER BY tingkat DESC) AS rn
    FROM   jalur
)
SELECT sc, akar, tingkat AS panjang_rantai
INTO   #akar
FROM   puncak WHERE rn = 1
OPTION (MAXRECURSION 200);

-- Setiap SC tanpa induk jadi pangkalnya sendiri
INSERT INTO #akar (sc, akar, panjang_rantai)
SELECT DISTINCT RTRIM(s.cNoSc), RTRIM(s.cNoSc), 0
FROM   dbo.tbStokGudangSnap s
WHERE  NOT EXISTS (SELECT 1 FROM #akar a WHERE a.sc = RTRIM(s.cNoSc));

CREATE UNIQUE CLUSTERED INDEX IX_akar ON #akar (sc);

SELECT panjang_rantai, COUNT(*) AS jml_sc FROM #akar GROUP BY panjang_rantai ORDER BY panjang_rantai;
GO

/* ---------------------------------------------------------------------------
   LANGKAH 4 — INI PENENTUNYA
   Berapa stok minus yang tertutup kalau dikelompokkan per rantai order ulang?
   Bandingkan dengan pencocokan customer + item yang menutup 105.732 pc.
   --------------------------------------------------------------------------- */
IF OBJECT_ID('tempdb..#grp') IS NOT NULL DROP TABLE #grp;
SELECT a.akar, SUM(s.nStokPc) AS pc_rantai, COUNT(*) AS jml_sc
INTO   #grp
FROM   dbo.tbStokGudangSnap s
INNER JOIN #akar a ON a.sc = s.cNoSc
GROUP  BY a.akar;

SELECT CASE WHEN g.pc_rantai >= 0 THEN 'TERTUTUP OLEH RANTAI'
            ELSE 'MASIH MINUS SETELAH DIGABUNG' END AS hasil,
       COUNT(*) AS jml_op,
       SUM(s.nStokPc) AS pc_minus
FROM   dbo.tbStokGudangSnap s
INNER JOIN #akar a ON a.sc = s.cNoSc
INNER JOIN #grp  g ON g.akar = a.akar
WHERE  s.nStokPc < 0
GROUP  BY CASE WHEN g.pc_rantai >= 0 THEN 'TERTUTUP OLEH RANTAI'
               ELSE 'MASIH MINUS SETELAH DIGABUNG' END;

-- Rinciannya, seperti kasus INTERBAT
SELECT TOP 40 s.cNoSc AS sc_minus, s.nStokPc AS stok_minus,
       a.akar AS pangkal_rantai, g.jml_sc AS sc_dalam_rantai,
       g.pc_rantai AS stok_gabungan, s.cNama, s.cNamabrg
FROM   dbo.tbStokGudangSnap s
INNER JOIN #akar a ON a.sc = s.cNoSc
INNER JOIN #grp  g ON g.akar = a.akar
WHERE  s.nStokPc < 0
ORDER  BY s.nStokPc;
GO

/* ---------------------------------------------------------------------------
   LANGKAH 5 — YANG PALING BESAR DAN BELUM JELAS
   SLC/2608/00208 minus 53.760 tanpa nama customer. Perlu dilihat isinya.
   --------------------------------------------------------------------------- */
SELECT TOP 10 s.cNoSRJ, CONVERT(VARCHAR(10), s.dTanggal, 23) AS tgl_dokumen,
       s.cNama, d.cNoOp, d.nQty, d.cNoScDtl
FROM   dbo.tbSRJ s INNER JOIN dbo.tbSRJDtl d ON d.cNoSRJ = s.cNoSRJ
WHERE  RTRIM(COALESCE(d.cNoScDtl, s.cNoSC)) = 'SLC/2608/00208'
ORDER  BY s.dTanggal;

SELECT COUNT(*) AS baris_stb, SUM(nQty) AS total_stb
FROM   dbo.tbStbBJ WHERE RTRIM(cNoSc) = 'SLC/2608/00208';

SELECT cNoSC, cNama, cKeterangan, cJnsSc, nQty, CONVERT(VARCHAR(10), dTanggal, 23) AS tgl_sc
FROM   dbo.tbSC WHERE RTRIM(cNoSC) = 'SLC/2608/00208';

DROP TABLE #ou; DROP TABLE #akar; DROP TABLE #grp;
GO
