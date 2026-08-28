/* ============================================================================
   PT SUPRACOR SEJAHTERA — SAMAKAN TANGGAL DUA SNAPSHOT
   Dibuat : 10 Agustus 2026

   MASALAH DI LAYAR
        Total stok            1.415.204 pc   491 OP   <- posisi 10 Agustus
        Box + Part + Sheet      916.305 pc   431 OP   <- posisi 05 Agustus
        Selisih                 498.899 pc

     tbStokGudangSnap diperbarui SQL Agent tiap 15 menit, jadi selalu posisi
     hari ini. tbStokSnapTipe terakhir dijalankan dengan tanggal 05 Agustus
     karena waktu itu dipakai untuk mencocokkan dengan file Excel. Dua tabel
     ini tidak pernah disamakan tanggalnya, sehingga rincian tipe tertinggal
     lima hari. Persentasenya pun cuma sampai 64,7%.

   PERBAIKAN
     1. tbStokSnapTipe ikut diperbarui setiap kali snapshot utama dihitung,
        sehingga tanggalnya selalu sama.
     2. Job SQL Agent menjalankan keduanya berurutan.
     3. Untuk keperluan pencocokan dengan Excel, tanggal tetap bisa dipilih
        lewat spStokPerTanggal atau lewat cek_dashboard.html, tanpa mengganggu
        tabel yang dipakai dashboard harian.

   Sekalian: tbStokGudangAdj dikosongkan. Tabel itu peninggalan pendekatan
   lama yang nomornya salah petakan, dan isinya membuat dashboard menampilkan
   "Patokan Excel 2026-08-03" padahal patokan yang berlaku sekarang 31 Juli.
   ============================================================================ */

USE dbSopanusa;
GO
SET NOCOUNT ON;
GO

/* ---------------------------------------------------------------------------
   LANGKAH 1 — BUKTIKAN DULU
   --------------------------------------------------------------------------- */
SELECT 'tbStokGudangSnap' AS tabel,
       (SELECT COUNT(*) FROM dbo.tbStokGudangSnap) AS jml_baris,
       (SELECT SUM(nStokPc) FROM dbo.tbStokGudangSnap) AS total_pc,
       CAST(GETDATE() AS DATE) AS posisi
UNION ALL
SELECT 'tbStokSnapTipe',
       (SELECT COUNT(*) FROM dbo.tbStokSnapTipe),
       (SELECT SUM(nStokPc) FROM dbo.tbStokSnapTipe),
       (SELECT MAX(dPosisi) FROM dbo.tbStokSnapTipe);
GO

/* ---------------------------------------------------------------------------
   LANGKAH 2 — SAMAKAN SEKARANG
   Tanpa parameter berarti posisi hari ini.
   --------------------------------------------------------------------------- */
EXEC dbo.spRefreshStokTipe;
GO

-- Selisihnya harus mengecil drastis. Sisa selisih wajar karena spRefreshStokGudang
-- menghitung per NO. SC sedangkan spRefreshStokTipe memecahnya per tipe, dan
-- keduanya membuang baris yang stoknya nol pada tingkat yang berbeda.
SELECT (SELECT SUM(nStokPc) FROM dbo.tbStokGudangSnap) AS total_snap,
       (SELECT SUM(nStokPc) FROM dbo.tbStokSnapTipe)   AS total_tipe,
       (SELECT SUM(nStokPc) FROM dbo.tbStokSnapTipe)
     - (SELECT SUM(nStokPc) FROM dbo.tbStokGudangSnap) AS selisih;

SELECT cKelompok, COUNT(*) AS jml_sc, SUM(nStokPc) AS total_pc, MAX(dPosisi) AS posisi
FROM   dbo.tbStokSnapTipe GROUP BY cKelompok ORDER BY SUM(nStokPc) DESC;
GO

/* ---------------------------------------------------------------------------
   LANGKAH 3 — AGAR TIDAK TERTINGGAL LAGI
   Bungkus keduanya jadi satu prosedur, lalu job SQL Agent memanggil ini.
   --------------------------------------------------------------------------- */
IF OBJECT_ID('dbo.spRefreshSemuaStok') IS NOT NULL DROP PROCEDURE dbo.spRefreshSemuaStok;
GO
CREATE PROCEDURE dbo.spRefreshSemuaStok
    @Sumber VARCHAR(30) = 'JOB'
AS
BEGIN
    SET NOCOUNT ON;
    -- Snapshot utama, dipakai kartu Total stok dan tabel rincian
    EXEC dbo.spRefreshStokGudang @Sumber = @Sumber;
    -- Rincian per tipe, WAJIB tanggal yang sama supaya angkanya sejalan
    EXEC dbo.spRefreshStokTipe;
END
GO

EXEC dbo.spRefreshSemuaStok @Sumber = 'SETUP';
GO

/* ---------------------------------------------------------------------------
   LANGKAH 4 — ARAHKAN JOB SQL AGENT KE PROSEDUR GABUNGAN
   --------------------------------------------------------------------------- */
USE msdb;
GO
IF EXISTS (SELECT 1 FROM msdb.dbo.sysjobs WHERE name = 'SPS - Refresh Stok Gudang BJ')
    EXEC msdb.dbo.sp_update_jobstep
         @job_name  = 'SPS - Refresh Stok Gudang BJ',
         @step_id   = 1,
         @command   = 'EXEC dbo.spRefreshSemuaStok @Sumber = ''JOB'';';
GO
USE dbSopanusa;
GO

/* ---------------------------------------------------------------------------
   LANGKAH 5 — BERSIHKAN PENINGGALAN tbStokGudangAdj
   Isinya dihitung memakai pemetaan nomor yang salah, dan membuat dashboard
   menampilkan patokan 03 Agustus padahal yang berlaku 31 Juli.
   Tabelnya tidak dihapus, hanya dikosongkan, supaya mudah ditelusuri.
   --------------------------------------------------------------------------- */
IF OBJECT_ID('dbo.tbStokGudangAdj') IS NOT NULL
BEGIN
    IF OBJECT_ID('dbo.tbStokGudangAdj_BAK') IS NULL
        SELECT * INTO dbo.tbStokGudangAdj_BAK FROM dbo.tbStokGudangAdj;
    TRUNCATE TABLE dbo.tbStokGudangAdj;
END
GO

SELECT MAX(dCutOff) AS patokan_berlaku,
       COUNT(*)     AS jml_baris_patokan,
       SUM(nStokAkhirPc) AS pc_patokan
FROM   dbo.tbStokGudangExcel;
GO
