/* ============================================================================
   PT SUPRACOR SEJAHTERA — RANTAI ORDER ULANG (KOLOM "OU")
   Dibuat : 10 Agustus 2026

   TEMUAN DARI KARTU OP YANG DIKIRIM PIC

     Kartu 1   No. SC SLC/2605/00397   OU: SLC/2602/00235
     Kartu 2   No. SC SLC/2606/00551   OU: SLC/2605/00397

     Ada kolom OU yang menghubungkan satu SC ke SC sebelumnya, dan keterangan
     OP-nya "ORDER ULANG". Jadi ketiganya satu rantai:

         SLC/2602/00235 -> SLC/2605/00397 -> SLC/2606/00551

     Item sama (S4029001 CARTON R), MC sama (0015826), ukuran sama
     (187 x 157 x 132). Bagi gudang ini satu barang, ditumpuk jadi satu.

   KENAPA ANGKANYA BERBEDA DENGAN KARTU LAMA

     Kartu lama menghitung STB dikurangi Pengiriman sepanjang umur SC, dan
     keduanya menunjukkan Sisa STB = 0. Sistem baru menghitung dari saldo
     Excel 31 Juli ditambah mutasi Agustus.

     SLC/2605/00397 : STB 11.452, SRJ 10.577 (11/06) + 875 (05/08)
                      -> per 31 Juli masih ada 875 pc, keluar 05 Agustus
     SLC/2606/00551 : STB 12.080, SRJ 12.080 (15/07)
                      -> per 31 Juli sudah nol

     Tapi file Excel gudang menaruh sisanya di SC yang BARU (2606/00551),
     bukan di SC yang masih menyimpan barang (2605/00397). Akibatnya
     surat jalan Agustus memotong SC yang saldonya nol.

   KALAU KOLOM OU ADA DI DATABASE, PENGELOMPOKANNYA TIDAK PERLU MENEBAK LAGI.
   Selama ini saya mencoba mengelompokkan lewat cNoOpLast (gagal) dan lewat
   customer + item (tidak menolong). Rantai order ulang jauh lebih tepat.

   FILE INI HANYA MEMBACA.
   ============================================================================ */

USE dbSopanusa;
GO
SET NOCOUNT ON;
SET TRANSACTION ISOLATION LEVEL READ UNCOMMITTED;
GO

/* ---------------------------------------------------------------------------
   LANGKAH 1 — CARI KOLOMNYA DI tbSC
   Di layar tertulis "OU". Kemungkinan nama kolomnya mengandung kata OU,
   Ulang, Lama, Ref, atau Asal.
   --------------------------------------------------------------------------- */
SELECT COLUMN_NAME, DATA_TYPE, CHARACTER_MAXIMUM_LENGTH AS lebar
FROM   INFORMATION_SCHEMA.COLUMNS
WHERE  TABLE_NAME = 'tbSC'
ORDER  BY ORDINAL_POSITION;
GO

/* ---------------------------------------------------------------------------
   LANGKAH 2 — LIHAT ISI BARIS UNTUK KEDUA SC YANG DILAPORKAN
   Cari kolom mana yang berisi SLC/2602/00235 dan SLC/2605/00397.
   --------------------------------------------------------------------------- */
SELECT TOP 5 * FROM dbo.tbSC WHERE RTRIM(cNoSc) = 'SLC/2605/00397';
SELECT TOP 5 * FROM dbo.tbSC WHERE RTRIM(cNoSc) = 'SLC/2606/00551';
GO

/* ---------------------------------------------------------------------------
   LANGKAH 3 — COCOKKAN CARA HITUNG KARTU LAMA
   Kartu lama: Sisa STB = seluruh STB dikurangi seluruh pengiriman.
   Query ini menirunya, lalu dibandingkan dengan angka sistem baru.
   --------------------------------------------------------------------------- */
;WITH sc_uji AS (
    SELECT 'SLC/2605/00397' AS sc UNION ALL SELECT 'SLC/2606/00551'
    UNION ALL SELECT 'SLC/2602/00235'
)
SELECT u.sc,
       ISNULL(b.stb_total, 0)                    AS stb_seluruh_umur,
       ISNULL(k.krm_total, 0)                    AS kirim_seluruh_umur,
       ISNULL(b.stb_total,0) - ISNULL(k.krm_total,0) AS sisa_cara_kartu_lama,
       ISNULL(b.stb_sd_jul, 0)                   AS stb_sd_31jul,
       ISNULL(k.krm_sd_jul, 0)                   AS kirim_sd_31jul,
       ISNULL(b.stb_sd_jul,0) - ISNULL(k.krm_sd_jul,0) AS posisi_31jul_menurut_database,
       ISNULL(e.pc, 0)                           AS saldo_31jul_menurut_excel,
       ISNULL(s.nStokPc, 0)                      AS stok_sistem_sekarang
FROM       sc_uji u
OUTER APPLY (SELECT SUM(ISNULL(nQty,0)) AS stb_total,
                    SUM(CASE WHEN dTanggal <= '2026-07-31' THEN ISNULL(nQty,0) ELSE 0 END) AS stb_sd_jul
             FROM   dbo.tbStbBJ WHERE RTRIM(cNoSc) = u.sc) b
OUTER APPLY (SELECT SUM(ISNULL(d.nQty,0)) AS krm_total,
                    SUM(CASE WHEN s2.dTanggal <= '2026-07-31' THEN ISNULL(d.nQty,0) ELSE 0 END) AS krm_sd_jul
             FROM   dbo.tbSRJ s2 INNER JOIN dbo.tbSRJDtl d ON d.cNoSRJ = s2.cNoSRJ
             WHERE  RTRIM(COALESCE(d.cNoScDtl, s2.cNoSC)) = u.sc) k
OUTER APPLY (SELECT SUM(nStokAkhirPc) AS pc FROM dbo.tbStokGudangExcel
             WHERE cNoScDb = u.sc) e
OUTER APPLY (SELECT nStokPc FROM dbo.tbStokGudangSnap WHERE cNoSc = u.sc) s;
GO

/* ---------------------------------------------------------------------------
   LANGKAH 4 — SURAT JALAN KEDUA SC, LENGKAP DENGAN TANGGAL KIRIM
   Membuktikan bahwa 875 pc itu memang keluar 05 Agustus.
   --------------------------------------------------------------------------- */
SELECT RTRIM(COALESCE(d.cNoScDtl, s.cNoSC)) AS cNoSc, s.cNoSRJ,
       CONVERT(VARCHAR(10), s.dTanggal, 23)  AS tgl_dokumen,
       CONVERT(VARCHAR(10), s.dTglKirim, 23) AS tgl_kirim,
       d.cNoOp, d.nQty
FROM   dbo.tbSRJ s INNER JOIN dbo.tbSRJDtl d ON d.cNoSRJ = s.cNoSRJ
WHERE  RTRIM(COALESCE(d.cNoScDtl, s.cNoSC)) IN
       ('SLC/2605/00397','SLC/2606/00551','SLC/2602/00235')
ORDER  BY s.dTanggal;
GO

/* ---------------------------------------------------------------------------
   LANGKAH 5 — KALAU KOLOM OU SUDAH KETEMU, UJI PENGELOMPOKANNYA
   GANTI nama kolom di bawah sesuai hasil Langkah 1, lalu jalankan.
   Contoh kalau kolomnya bernama cNoScOU:
   --------------------------------------------------------------------------- */
/*
;WITH rantai AS (
    SELECT RTRIM(cNoSc) AS sc, RTRIM(cNoScOU) AS induk        -- <-- ganti di sini
    FROM   dbo.tbSC
    WHERE  cNoScOU IS NOT NULL AND LTRIM(RTRIM(cNoScOU)) <> ''
)
SELECT m.cNoSc AS sc_minus, m.nStokPc AS stok_minus,
       r.induk AS sc_induk, i.nStokPc AS stok_induk,
       m.nStokPc + ISNULL(i.nStokPc, 0) AS gabungan,
       m.cNama, m.cNamabrg
FROM       dbo.tbStokGudangSnap m
LEFT JOIN  rantai r ON r.sc = m.cNoSc
LEFT JOIN  dbo.tbStokGudangSnap i ON i.cNoSc = r.induk
WHERE      m.nStokPc < 0
ORDER BY   m.nStokPc;
*/

/* ---------------------------------------------------------------------------
   YANG BISA DISAMPAIKAN KE PIC SEKARANG

     Stoknya tidak hilang dan tidak ada yang salah hitung.

     Kartu lama menghitung sepanjang umur SC, jadi wajar keduanya nol.
     Sistem baru menghitung dari saldo gudang 31 Juli, dan pada tanggal itu
     SLC/2605/00397 memang masih menyimpan 875 pc yang baru keluar 05 Agustus.

     Yang berbeda hanya PENEMPATAN nomornya: gudang mencatat sisa di nomor SC
     terbaru (2606/00551), sistem memotongnya dari nomor SC yang tertulis di
     surat jalan (2605/00397). Total barang INTERBAT untuk item ini tetap benar.
   --------------------------------------------------------------------------- */
