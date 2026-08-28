/* ============================================================================
   PT SUPRACOR SEJAHTERA — PISAHKAN BARANG LEMBARAN DARI STOK GUDANG
   Dibuat : 05 Agustus 2026

   HASIL UJI SEBELUMNYA
     Pengelompokan per customer + item TIDAK menolong. Semua 16 stok minus
     punya sc_seitem = 1, artinya tidak ada SC lain yang seitem. Jumlah minus
     tetap -16.756 pc. Dugaan itu gugur.

   TEMUAN YANG JAUH LEBIH BESAR
     Lihat daftar selisih mutasi terbesar, hampir semuanya barang lembaran:
        MKP      SHEET - 697 X 2134 MM      excel 0, database 6.320
        PACK     SHEET - 468 X 1483 MM      excel 0, database 8.500
        SSSSUN   SHEET - 712 X 633 MM       excel 0, database 5.120
        ESPACK   SHEET - 400 X 1780 MM      excel 0, database 4.200
        WILMAR1  SLIP SHEET PAPERBOARD      excel 0, database 1.000

     Kolom Excel-nya SELALU nol. Barang lembaran memang tidak dicatat di file
     stok gudang, karena sheet BOX dan PART+LAYER tidak mencakup kategori ini.
     Dari 16 stok minus, 5 di antaranya juga barang lembaran.

     Jadi selisihnya bukan karena hitungan salah, tapi karena dua sisi
     menghitung KATEGORI BARANG YANG BERBEDA.

   YANG DILAKUKAN FILE INI
     Memberi penanda jenis barang, lalu mengukur ulang akurasi hanya untuk
     kategori yang memang dipantau gudang. Barang lembaran tetap ditampilkan
     di dashboard, tapi dihitung terpisah supaya tidak mengacaukan angka.

   Langkah 1-3 hanya membaca. Langkah 4 menambah satu kolom di tabel snapshot.
   ============================================================================ */

USE dbSopanusa;
GO
SET NOCOUNT ON;
SET TRANSACTION ISOLATION LEVEL READ UNCOMMITTED;
GO

IF OBJECT_ID('tempdb..#si') IS NOT NULL DROP TABLE #si;

SELECT sc, kode, item,
       CAST(NULL AS VARCHAR(20)) AS jenis
INTO   #si
FROM ( SELECT RTRIM(b.cNoSc) AS sc, RTRIM(b.cKodeCust) AS kode, RTRIM(b.cNamabrg) AS item,
              ROW_NUMBER() OVER (PARTITION BY RTRIM(b.cNoSc)
                                 ORDER BY b.dTanggal DESC, b.cNoSTB DESC) AS rn
       FROM   dbo.tbStbBJ b
       WHERE  b.cNoSc IS NOT NULL AND LTRIM(RTRIM(b.cNoSc)) <> '' ) x
WHERE  rn = 1;
CREATE UNIQUE CLUSTERED INDEX IX_si ON #si (sc);

/* Penggolongan jenis barang:
   - Ikut kategori di file gudang kalau SC-nya memang ada di sana
   - Kalau tidak ada dan namanya lembaran -> LEMBARAN
   - Sisanya -> BELUM DIPANTAU  */
UPDATE i SET i.jenis = e.cKategori
FROM   #si i
INNER JOIN ( SELECT cNoScDb, MIN(cKategori) AS cKategori
             FROM   dbo.tbStokGudangExcel
             WHERE  cNoScDb IS NOT NULL
             GROUP  BY cNoScDb ) e ON e.cNoScDb = i.sc;

UPDATE #si SET jenis = 'LEMBARAN'
WHERE  jenis IS NULL AND (item LIKE 'SHEET%' OR item LIKE '%SLIP SHEET%' OR item LIKE '%LEMBARAN%');

UPDATE #si SET jenis = 'BELUM DIPANTAU' WHERE jenis IS NULL;
GO

/* ---------------------------------------------------------------------------
   LANGKAH 1 — SEBERAPA BESAR PORSI BARANG LEMBARAN?
   --------------------------------------------------------------------------- */
SELECT i.jenis,
       COUNT(*)                                            AS jml_sc,
       SUM(s.nStokPc)                                      AS total_pc,
       SUM(CASE WHEN s.nStokPc < 0 THEN 1 ELSE 0 END)      AS jml_minus,
       SUM(CASE WHEN s.nStokPc < 0 THEN s.nStokPc ELSE 0 END) AS pc_minus
FROM   dbo.tbStokGudangSnap s
INNER JOIN #si i ON i.sc = s.cNoSc
GROUP  BY i.jenis
ORDER  BY ABS(SUM(s.nStokPc)) DESC;
GO

/* ---------------------------------------------------------------------------
   LANGKAH 2 — AKURASI MUTASI 01-03 AGUSTUS, DIPECAH PER JENIS BARANG
   Baris BOX dan PART+LAYER inilah akurasi yang sesungguhnya.
   --------------------------------------------------------------------------- */
IF OBJECT_ID('tempdb..#mut') IS NOT NULL DROP TABLE #mut;

SELECT i.jenis,
       SUM(ISNULL(x.stb,0)) AS stb_excel, SUM(ISNULL(t.stb,0)) AS stb_db,
       SUM(ISNULL(x.dlv,0)) AS dlv_excel, SUM(ISNULL(t.dlv,0)) AS dlv_db
INTO   #mut
FROM   #si i
LEFT JOIN ( SELECT cNoScDb AS sc, SUM(nStbPc) AS stb, SUM(nDlvPc) AS dlv
            FROM   dbo.tbCekMutasiExcel GROUP BY cNoScDb ) x ON x.sc = i.sc
LEFT JOIN ( SELECT sc, SUM(stb) AS stb, SUM(dlv) AS dlv FROM (
                SELECT RTRIM(cNoSc) AS sc, SUM(ISNULL(nQty,0)) AS stb, 0 AS dlv
                FROM   dbo.tbStbBJ
                WHERE  dTanggal >= '2026-08-01' AND dTanggal < '2026-08-04'
                GROUP  BY RTRIM(cNoSc)
                UNION ALL
                SELECT RTRIM(COALESCE(d.cNoScDtl, s.cNoSC)), 0, SUM(ISNULL(d.nQty,0))
                FROM   dbo.tbSRJ s INNER JOIN dbo.tbSRJDtl d ON d.cNoSRJ = s.cNoSRJ
                WHERE  s.dTanggal >= '2026-08-01' AND s.dTanggal < '2026-08-04'
                GROUP  BY RTRIM(COALESCE(d.cNoScDtl, s.cNoSC))
            ) y GROUP BY sc ) t ON t.sc = i.sc
WHERE  x.sc IS NOT NULL OR t.sc IS NOT NULL
GROUP  BY i.jenis;

SELECT jenis, stb_excel, stb_db, stb_db - stb_excel AS selisih_stb,
       CAST(100.0 * (1 - ABS(stb_db - stb_excel) / NULLIF(stb_db,0)) AS DECIMAL(5,2)) AS akurasi_stb_persen,
       dlv_excel, dlv_db, dlv_db - dlv_excel AS selisih_dlv,
       CAST(100.0 * (1 - ABS(dlv_db - dlv_excel) / NULLIF(dlv_db,0)) AS DECIMAL(5,2)) AS akurasi_dlv_persen
FROM   #mut ORDER BY stb_db DESC;

-- Gabungan hanya untuk kategori yang dipantau gudang
SELECT 'HANYA BOX + PART/LAYER' AS lingkup,
       SUM(stb_excel) AS stb_excel, SUM(stb_db) AS stb_db,
       SUM(dlv_excel) AS dlv_excel, SUM(dlv_db) AS dlv_db,
       CAST(100.0 * (1 - ABS(SUM(stb_db) - SUM(stb_excel)) / NULLIF(SUM(stb_db),0)) AS DECIMAL(5,2)) AS akurasi_stb,
       CAST(100.0 * (1 - ABS(SUM(dlv_db) - SUM(dlv_excel)) / NULLIF(SUM(dlv_db),0)) AS DECIMAL(5,2)) AS akurasi_dlv
FROM   #mut WHERE jenis IN ('BOX','PART+LAYER');
GO

/* ---------------------------------------------------------------------------
   LANGKAH 3 — SC "BELUM DIPANTAU" TERBESAR
   Ini bahan pertanyaan ke gudang: perlu ikut dicatat atau memang di luar
   tanggung jawab gudang barang jadi?
   --------------------------------------------------------------------------- */
SELECT TOP 25 s.cNoSc, s.cNama, i.item, s.nStokPc, i.jenis
FROM   dbo.tbStokGudangSnap s
INNER JOIN #si i ON i.sc = s.cNoSc
WHERE  i.jenis IN ('LEMBARAN','BELUM DIPANTAU')
ORDER  BY ABS(s.nStokPc) DESC;
GO

/* ---------------------------------------------------------------------------
   LANGKAH 4 — SIMPAN PENANDA JENIS DI SNAPSHOT
   Supaya dashboard bisa memisahkan angkanya. Cuma menambah satu kolom.
   --------------------------------------------------------------------------- */
IF COL_LENGTH('dbo.tbStokGudangSnap', 'cJenisBrg') IS NULL
    ALTER TABLE dbo.tbStokGudangSnap ADD cJenisBrg VARCHAR(20) NULL;
GO

UPDATE s SET s.cJenisBrg = i.jenis
FROM   dbo.tbStokGudangSnap s INNER JOIN #si i ON i.sc = s.cNoSc;

SELECT cJenisBrg, COUNT(*) AS jml_sc, SUM(nStokPc) AS total_pc
FROM   dbo.tbStokGudangSnap GROUP BY cJenisBrg ORDER BY ABS(SUM(nStokPc)) DESC;
GO

DROP TABLE #si;
DROP TABLE #mut;
GO
