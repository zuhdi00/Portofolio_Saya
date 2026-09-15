/* =====================================================================
   DIAGNOSA: kenapa data absensi tidak terekam semua
   Jalankan di SSMS, database dbHR. Urutkan dari A -> F.
   Tujuan: menemukan DI TAHAP MANA data hilang.
   Alur data: MDB -> zkteco_checkinout -> absensi
   ===================================================================== */
USE dbHR;
GO

/* ---------------------------------------------------------------------
   A. Status mesin sinkron (paling cepat menunjukkan sync mati/melompat)
   --------------------------------------------------------------------- */
SELECT sumber, last_checktime, last_run, jml_baru, status, pesan
FROM dbo.sync_zkteco_state;
-- BACA: kalau last_run tua  -> Task Scheduler / .bat tidak jalan.
--       kalau last_checktime LEBIH BARU dari sekarang (tanggal masa depan)
--       -> ada mesin dgn jam salah; semua tap normal setelahnya TIDAK ikut
--          terjaring karena jendela impor melompat ke depan.
GO

/* ---------------------------------------------------------------------
   B. Volume tap masuk staging per hari (30 hari terakhir)
   --------------------------------------------------------------------- */
SELECT CAST(checktime AS DATE) AS tgl,
       COUNT(*)                         AS tap,
       SUM(CASE WHEN diproses=0 THEN 1 ELSE 0 END) AS belum_diproses,
       COUNT(DISTINCT zk_userid)        AS org,
       MIN(checktime) AS tap_awal, MAX(checktime) AS tap_akhir
FROM dbo.zkteco_checkinout
WHERE checktime >= DATEADD(DAY,-30,CAST(GETDATE() AS DATE))
GROUP BY CAST(checktime AS DATE)
ORDER BY tgl;
-- BACA: hari dengan tap jauh lebih sedikit / 0 = data hilang SEBELUM masuk
--       staging (masalah impor / MDB tak terbaca), bukan masalah proses.
GO

/* ---------------------------------------------------------------------
   C. Tap yang masuk tapi pegawainya TIDAK dikenal  <-- penyebab umum
   --------------------------------------------------------------------- */
SELECT z.zk_userid, COUNT(*) AS jml_tap,
       MIN(z.checktime) AS pertama, MAX(z.checktime) AS terakhir,
       CASE WHEN p.id_peg IS NULL THEN 'BELUM DIPETAKAN di pegawai'
            WHEN p.is_aktif = 0    THEN 'ADA tapi is_aktif = 0'
            ELSE 'OK' END AS masalah
FROM dbo.zkteco_checkinout z
LEFT JOIN dbo.pegawai p ON p.zkteco_userid = z.zk_userid
WHERE z.checktime >= DATEADD(DAY,-30,GETDATE())
  AND (p.id_peg IS NULL OR p.is_aktif = 0)
GROUP BY z.zk_userid, p.id_peg, p.is_aktif
ORDER BY jml_tap DESC;
-- BACA: setiap userid di sini = 1 karyawan yang absensinya TIDAK PERNAH
--       muncul di HRIS. Perbaiki: isi pegawai.zkteco_userid / set is_aktif=1.
GO

/* ---------------------------------------------------------------------
   D. Serial mesin yang belum terdaftar sebagai gerbang masuk/keluar
   --------------------------------------------------------------------- */
SELECT z.sn, COUNT(*) AS jml_tap, MIN(z.checktime) AS pertama, MAX(z.checktime) AS terakhir
FROM dbo.zkteco_checkinout z
WHERE z.checktime >= DATEADD(DAY,-30,GETDATE())
GROUP BY z.sn
ORDER BY jml_tap DESC;

SELECT kunci, nilai FROM dbo.pengaturan_absensi WHERE kunci IN ('mesin_masuk','mesin_keluar');
-- BACA: SN yang muncul di query pertama tapi TIDAK ada di mesin_masuk /
--       mesin_keluar akan ditebak arahnya (sering salah) -> jam pulang kosong.
GO

/* ---------------------------------------------------------------------
   E. Ada tap di staging TAPI baris absensi tidak lengkap / hilang
      (ini membuktikan masalah ada di sync_zkteco_proses.php)
   --------------------------------------------------------------------- */
;WITH tap AS (
    SELECT p.id_peg, CAST(z.checktime AS DATE) AS tgl, COUNT(*) AS jml_tap,
           MIN(CAST(z.checktime AS TIME)) AS tap_awal,
           MAX(CAST(z.checktime AS TIME)) AS tap_akhir
    FROM dbo.zkteco_checkinout z
    JOIN dbo.pegawai p ON p.zkteco_userid = z.zk_userid
    WHERE z.checktime >= DATEADD(DAY,-30,GETDATE())
    GROUP BY p.id_peg, CAST(z.checktime AS DATE)
)
SELECT t.tgl, t.id_peg, pg.nama_peg, t.jml_tap, t.tap_awal, t.tap_akhir,
       a.jam_masuk, a.jam_keluar, a.shift_ke, a.perlu_koreksi,
       CASE WHEN a.pegawai_id IS NULL      THEN 'TIDAK ADA BARIS ABSENSI'
            WHEN a.jam_masuk  IS NULL      THEN 'JAM MASUK HILANG (ada tapnya)'
            WHEN a.jam_keluar IS NULL      THEN 'JAM KELUAR HILANG'
            ELSE 'ok' END AS gejala
FROM tap t
JOIN dbo.pegawai pg ON pg.id_peg = t.id_peg
LEFT JOIN dbo.absensi a ON a.pegawai_id = t.id_peg AND a.tanggal = t.tgl
WHERE a.pegawai_id IS NULL OR a.jam_masuk IS NULL OR a.jam_keluar IS NULL
ORDER BY t.tgl DESC, t.id_peg;
-- BACA: kalau baris "JAM MASUK HILANG" banyak padahal tap_awal ada,
--       itu bug MERGE menimpa jam_masuk dgn NULL (lihat PATCH).
GO

/* ---------------------------------------------------------------------
   F. Rekap harian: berapa orang seharusnya vs yang tercatat
   --------------------------------------------------------------------- */
SELECT CAST(z.checktime AS DATE) AS tgl,
       COUNT(DISTINCT z.zk_userid) AS org_nge_tap,
       (SELECT COUNT(DISTINCT a.pegawai_id) FROM dbo.absensi a
         WHERE a.tanggal = CAST(z.checktime AS DATE)) AS org_di_absensi,
       (SELECT COUNT(*) FROM dbo.absensi a
         WHERE a.tanggal = CAST(z.checktime AS DATE) AND a.jam_keluar IS NULL) AS tanpa_jam_keluar,
       (SELECT COUNT(*) FROM dbo.absensi a
         WHERE a.tanggal = CAST(z.checktime AS DATE) AND a.jam_masuk IS NULL) AS tanpa_jam_masuk
FROM dbo.zkteco_checkinout z
WHERE z.checktime >= DATEADD(DAY,-30,CAST(GETDATE() AS DATE))
GROUP BY CAST(z.checktime AS DATE)
ORDER BY tgl DESC;
-- BACA: selisih org_nge_tap vs org_di_absensi = jumlah orang yang hilang tiap hari.
GO
