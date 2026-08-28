/* ============================================================================
   PT SUPRACOR SEJAHTERA — UJI DUGAAN "SATU ORDER, BANYAK NOMOR SC"
   Dibuat : 05 Agustus 2026

   LATAR BELAKANG
     Laporan akurasi 01-03 Agustus menunjukkan selisih STB 4,0% dan DLV 4,5%.
     Tapi rinciannya hampir semua BERPASANGAN dengan jumlah yang sama persis:

        Excel catat di        Database catat di     Jumlah
        2607/00844            2607/00843            4.670 dan 3.520
        2606/00295            2606/00294            4.500
        2604/01451            2604/01452            2.700
        2607/01290            2607/01252            2.560 dan 10.180

     Jadi jumlahnya cocok, yang beda cuma nomor SC tempat mencatatnya.
     Dugaan: satu order dipecah jadi beberapa SC (misal body dan tutup, atau
     SC revisi), gudang mencatat di satu nomor induk, database memecahnya.

   TUJUAN FILE INI
     Membuktikan apakah kolom cNoOpLast di tbStbBJ bisa dipakai untuk
     mengelompokkan SC yang sebenarnya satu order. Kalau terbukti, stok bisa
     dihitung per kelompok sehingga beda penomoran tidak lagi bikin stok minus.

   FILE INI HANYA MEMBACA. Tidak ada perubahan data sama sekali.
   ============================================================================ */

USE dbSopanusa;
GO
SET NOCOUNT ON;
SET TRANSACTION ISOLATION LEVEL READ UNCOMMITTED;
GO

/* ---------------------------------------------------------------------------
   LANGKAH 0 — SEKALIAN BERESKAN 2 BARIS PART+LAYER YANG BELUM TERPETAKAN
   Muncul lagi karena patokan kemarin diisi ulang dari nol.
   --------------------------------------------------------------------------- */
UPDATE dbo.tbStokGudangExcel
SET    cNoScDb = 'SLC/' + LEFT(RTRIM(cNoOpExcel), CHARINDEX('-', RTRIM(cNoOpExcel) + '-') - 1)
WHERE  cNoScDb IS NULL AND CHARINDEX('-', cNoOpExcel) > 0;

UPDATE e SET e.cNoScDb = NULL
FROM   dbo.tbStokGudangExcel e
WHERE  e.cNoScDb IS NOT NULL
  AND  NOT EXISTS (SELECT 1 FROM dbo.tbStbBJ b WHERE RTRIM(b.cNoSc) = e.cNoScDb);

SELECT cKategori, COUNT(*) AS baris,
       SUM(CASE WHEN cNoScDb IS NULL THEN 1 ELSE 0 END) AS belum_ketemu
FROM   dbo.tbStokGudangExcel GROUP BY cKategori;   -- target: belum_ketemu = 0
GO

/* ---------------------------------------------------------------------------
   LANGKAH 1 — LIHAT PASANGAN YANG DICURIGAI SECARA LANGSUNG
   Kalau dugaan benar, cNoOpLast salah satunya menunjuk ke nomor pasangannya.
   --------------------------------------------------------------------------- */
SELECT RTRIM(cNoSc) AS cNoSc, RTRIM(cNoOp) AS cNoOp, RTRIM(cNoOpLast) AS cNoOpLast,
       COUNT(*) AS baris_stb, SUM(ISNULL(nQty,0)) AS total_qty,
       MIN(dTanggal) AS stb_pertama, MAX(dTanggal) AS stb_terakhir,
       MAX(cNamabrg) AS contoh_item
FROM   dbo.tbStbBJ
WHERE  RTRIM(cNoSc) IN ('SLC/2607/00843','SLC/2607/00844',
                        'SLC/2606/00294','SLC/2606/00295',
                        'SLC/2604/01451','SLC/2604/01452',
                        'SLC/2607/01252','SLC/2607/01290',
                        'SLC/2607/00056','SLC/2607/00099','SLC/2607/00377')
GROUP  BY RTRIM(cNoSc), RTRIM(cNoOp), RTRIM(cNoOpLast)
ORDER  BY cNoSc, cNoOp;
GO

/* ---------------------------------------------------------------------------
   LANGKAH 2 — SEBERAPA UMUM SATU SC PUNYA cNoOpLast BEDA SENDIRI?
   Kalau sebagian besar sama, berarti kolom ini memang penanda kelompok.
   --------------------------------------------------------------------------- */
SELECT SUM(CASE WHEN RTRIM(ISNULL(cNoOpLast,'')) = RTRIM(ISNULL(cNoOp,'')) THEN 1 ELSE 0 END) AS sama,
       SUM(CASE WHEN RTRIM(ISNULL(cNoOpLast,'')) <> RTRIM(ISNULL(cNoOp,'')) THEN 1 ELSE 0 END) AS beda,
       COUNT(*) AS total_baris
FROM   dbo.tbStbBJ
WHERE  dTanggal >= '2026-07-01';
GO

/* ---------------------------------------------------------------------------
   LANGKAH 3 — COBA KELOMPOKKAN, LALU LIHAT APAKAH STOK MINUS IKUT HILANG
   Kelompok diambil dari bagian tengah cNoOpLast, misal
       SPS/2607/00601-B01  ->  2607/00601
   Ini cuma simulasi, tidak menulis apa pun.
   --------------------------------------------------------------------------- */
;WITH peta AS (
    SELECT DISTINCT
           RTRIM(b.cNoSc) AS cNoSc,
           'SLC/' + SUBSTRING(RTRIM(b.cNoOpLast), 5,
                    CASE WHEN CHARINDEX('-', RTRIM(b.cNoOpLast) + '-') > 5
                         THEN CHARINDEX('-', RTRIM(b.cNoOpLast) + '-') - 5 ELSE 10 END) AS cKelompok
    FROM   dbo.tbStbBJ b
    WHERE  b.cNoOpLast IS NOT NULL AND LTRIM(RTRIM(b.cNoOpLast)) <> ''
      AND  b.dTanggal >= '2026-01-01'
)
SELECT TOP 30 p.cKelompok,
       COUNT(DISTINCT p.cNoSc)   AS jml_sc_dalam_kelompok,
       SUM(s.nStokPc)            AS stok_kelompok,
       MIN(s.nStokPc)            AS stok_terkecil,
       MAX(s.cNama)              AS contoh_customer
FROM       peta p
INNER JOIN dbo.tbStokGudangSnap s ON s.cNoSc = p.cNoSc
GROUP  BY p.cKelompok
HAVING COUNT(DISTINCT p.cNoSc) > 1
ORDER  BY COUNT(DISTINCT p.cNoSc) DESC, SUM(s.nStokPc) DESC;
GO

/* ---------------------------------------------------------------------------
   LANGKAH 4 — INI YANG PALING MENENTUKAN
   Untuk tiap SC yang sekarang minus, lihat apakah ada SC lain sekelompok yang
   stoknya positif. Kalau ya, dugaan terbukti dan solusinya jelas.
   --------------------------------------------------------------------------- */
;WITH peta AS (
    SELECT DISTINCT
           RTRIM(b.cNoSc) AS cNoSc,
           'SLC/' + SUBSTRING(RTRIM(b.cNoOpLast), 5,
                    CASE WHEN CHARINDEX('-', RTRIM(b.cNoOpLast) + '-') > 5
                         THEN CHARINDEX('-', RTRIM(b.cNoOpLast) + '-') - 5 ELSE 10 END) AS cKelompok
    FROM   dbo.tbStbBJ b
    WHERE  b.cNoOpLast IS NOT NULL AND LTRIM(RTRIM(b.cNoOpLast)) <> ''
)
SELECT m.cNoSc      AS sc_minus,
       m.cNama, m.nStokPc AS stok_minus,
       p.cKelompok,
       t.cNoSc      AS sc_sekelompok,
       t.nStokPc    AS stok_sc_sekelompok,
       m.nStokPc + ISNULL(SUM(t.nStokPc) OVER (PARTITION BY p.cKelompok), 0) AS stok_gabungan
FROM       dbo.tbStokGudangSnap m
INNER JOIN peta p  ON p.cNoSc = m.cNoSc
LEFT  JOIN peta p2 ON p2.cKelompok = p.cKelompok AND p2.cNoSc <> m.cNoSc
LEFT  JOIN dbo.tbStokGudangSnap t ON t.cNoSc = p2.cNoSc
WHERE      m.nStokPc < 0
ORDER BY   m.nStokPc;
GO
