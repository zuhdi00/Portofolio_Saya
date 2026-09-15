/* ============================================================================
   PT SUPRACOR SEJAHTERA — PEMERIKSAAN SEBELUM ADJUST MASUK KE tbStbBJ.nQtyOut
   Dibuat : 12 Agustus 2026

   PERTANYAANNYA
     "Apakah data stok nyambung ke STB? Ada rencana adjust dimasukkan ke
      nomor STB di tabel tbStbBJ field nQtyOut."

   KEADAAN SEKARANG — BELUM NYAMBUNG KE nQtyOut
     Perhitungan stok memakai SUM(nQty) per cNoSc:
         stok = saldo awal Excel + SUM(nQty) - kirim + retur + koreksi
     Kolom nQtyOut, cOutSTB, dan dTanggalOut TIDAK PERNAH dibaca.
     Jadi kalau adjust ditulis ke nQtyOut hari ini, angkanya tidak berubah.

   KENAPA IDE ITU MENARIK
     Sekarang stok dihitung per NO. SC. Padahal satu SC bisa punya banyak
     baris STB dengan tanggal, rak, dan operator berbeda. Kalau adjust melekat
     ke nomor STB, tiga hal jadi mungkin:
       - ketahuan batch mana yang berkurang, bukan cuma SC-nya
       - umur stok bisa dihitung per batch, bukan dari STB terakhir saja
       - FIFO bisa diterapkan, barang lama keluar lebih dulu

   YANG PERLU DIPASTIKAN LEBIH DULU
     tbStbBJ adalah catatan produksi. Kalau dashboard stok ikut menulis ke
     sana, kesalahan input bisa merembet ke laporan produksi. Karena itu tiga
     hal di bawah harus dijawab sebelum diputuskan.

   FILE INI HANYA MEMBACA.
   ============================================================================ */

USE dbSopanusa;
GO
SET NOCOUNT ON;
SET TRANSACTION ISOLATION LEVEL READ UNCOMMITTED;
GO

/* ---------------------------------------------------------------------------
   PERTANYAAN 1 — APAKAH nQtyOut SUDAH DIPAKAI MODUL LAIN?
   Kalau sudah terisi, berarti ada proses lain yang memakainya dan menulis ke
   sana akan bertabrakan.
   --------------------------------------------------------------------------- */
SELECT COUNT(*)                                              AS total_baris,
       SUM(CASE WHEN ISNULL(nQtyOut,0) <> 0 THEN 1 ELSE 0 END) AS ada_isi_nQtyOut,
       SUM(ISNULL(nQtyOut,0))                                AS total_nQtyOut,
       SUM(CASE WHEN LTRIM(RTRIM(ISNULL(cOutSTB,''))) <> '' THEN 1 ELSE 0 END) AS ada_isi_cOutSTB,
       SUM(CASE WHEN dTanggalOut IS NOT NULL THEN 1 ELSE 0 END) AS ada_isi_dTanggalOut
FROM   dbo.tbStbBJ;

-- Kalau ada isinya, lihat contohnya dan sejak kapan
SELECT TOP 20 cNoSTB, cNoSc, CONVERT(VARCHAR(10), dTanggal, 23) AS tgl_stb,
       nQty, nQtyOut, cOutSTB, CONVERT(VARCHAR(10), dTanggalOut, 23) AS tgl_out,
       cKeterangan, UserId
FROM   dbo.tbStbBJ
WHERE  ISNULL(nQtyOut,0) <> 0
ORDER  BY dTanggalOut DESC, cNoSTB DESC;

-- Sebaran per tahun, untuk tahu ini fitur aktif atau peninggalan lama
SELECT YEAR(dTanggalOut) AS tahun, COUNT(*) AS jml, SUM(nQtyOut) AS total_pc
FROM   dbo.tbStbBJ WHERE dTanggalOut IS NOT NULL
GROUP  BY YEAR(dTanggalOut) ORDER BY tahun DESC;
GO

/* ---------------------------------------------------------------------------
   PERTANYAAN 2 — SEBERAPA BANYAK SATU NO. SC PUNYA BANYAK BARIS STB?
   Ini yang menentukan apakah pindah ke tingkat STB benar-benar berguna.
   --------------------------------------------------------------------------- */
;WITH per_sc AS (
    SELECT RTRIM(b.cNoSc) AS sc, COUNT(*) AS jml_stb
    FROM   dbo.tbStbBJ b
    INNER JOIN dbo.tbStokGudangSnap s ON s.cNoSc = RTRIM(b.cNoSc)
    WHERE  b.dTanggal > '2026-07-31'
    GROUP  BY RTRIM(b.cNoSc)
)
SELECT CASE WHEN jml_stb = 1 THEN '1 baris STB'
            WHEN jml_stb <= 3 THEN '2-3 baris'
            WHEN jml_stb <= 10 THEN '4-10 baris'
            ELSE 'lebih dari 10 baris' END AS kelompok,
       COUNT(*) AS jml_sc
FROM   per_sc
GROUP  BY CASE WHEN jml_stb = 1 THEN '1 baris STB'
               WHEN jml_stb <= 3 THEN '2-3 baris'
               WHEN jml_stb <= 10 THEN '4-10 baris'
               ELSE 'lebih dari 10 baris' END
ORDER  BY jml_sc DESC;

-- Contoh NO. SC dengan banyak batch, beserta rak dan tanggalnya
SELECT TOP 25 RTRIM(b.cNoSc) AS cNoSc, b.cNoSTB,
       CONVERT(VARCHAR(10), b.dTanggal, 23) AS tgl_stb,
       b.nQty, ISNULL(b.nQtyOut,0) AS nQtyOut, b.cRak, b.cKeterangan
FROM   dbo.tbStbBJ b
WHERE  RTRIM(b.cNoSc) IN (
         SELECT TOP 3 RTRIM(cNoSc) FROM dbo.tbStbBJ
         WHERE dTanggal > '2026-07-31' AND cNoSc IS NOT NULL
         GROUP BY RTRIM(cNoSc) ORDER BY COUNT(*) DESC)
ORDER  BY b.cNoSc, b.dTanggal;
GO

/* ---------------------------------------------------------------------------
   PERTANYAAN 3 — APAKAH ADA MODUL LAIN YANG MENULIS KE nQtyOut?
   Cari di dalam prosedur dan view yang ada.
   --------------------------------------------------------------------------- */
SELECT o.type_desc, o.name AS objek
FROM   sys.sql_modules m
INNER JOIN sys.objects o ON o.object_id = m.object_id
WHERE  m.definition LIKE '%nQtyOut%'
ORDER  BY o.type_desc, o.name;
GO

/* ---------------------------------------------------------------------------
   SIMULASI — SEANDAINYA RUMUS IKUT MENGURANGI nQtyOut
   Belum mengubah apa pun. Sekadar melihat dampaknya kalau rumus stok diubah
   dari SUM(nQty) menjadi SUM(nQty - nQtyOut).
   --------------------------------------------------------------------------- */
SELECT 'Cara sekarang: SUM(nQty)'            AS cara,
       SUM(ISNULL(b.nQty,0))                 AS total_stb_agustus
FROM   dbo.tbStbBJ b WHERE b.dTanggal > '2026-07-31'
UNION ALL
SELECT 'Usulan: SUM(nQty - nQtyOut)',
       SUM(ISNULL(b.nQty,0) - ISNULL(b.nQtyOut,0))
FROM   dbo.tbStbBJ b WHERE b.dTanggal > '2026-07-31';
GO
