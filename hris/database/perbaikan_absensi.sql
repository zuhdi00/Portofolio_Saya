/* =====================================================================
   PERBAIKAN DATA ABSENSI  -  jalankan SETELAH sync_zkteco_proses.php v3
   dipasang dan Task Scheduler DIMATIKAN sementara.

   Urutan: LANGKAH 0 -> 1 -> 2 -> 3 -> (jalankan PHP) -> 4
   Jalankan per LANGKAH, jangan F5 semuanya sekaligus.
   ===================================================================== */
USE dbHR;
GO

/* =============== LANGKAH 0 - BACKUP (WAJIB) =============== */
-- Ganti path sesuai drive yang ada di server:
-- BACKUP DATABASE dbHR TO DISK = 'D:\backup\dbHR_sebelum_perbaikan.bak' WITH INIT;
GO


/* =============== LANGKAH 1 - lihat dulu apa yang akan dihapus =============== */
DECLARE @dari DATE = '2026-07-14';   -- <<< ubah kalau mau rentang lain

SELECT COUNT(*) AS baris_hantu_akan_dihapus
FROM dbo.absensi a
WHERE a.tanggal >= @dari
  AND a.sumber = 'ZKTECO'
  AND a.jam_masuk IS NULL
  AND NOT EXISTS (SELECT 1 FROM dbo.absensi_koreksi k
                  WHERE k.pegawai_id = a.pegawai_id AND k.tanggal = a.tanggal
                    AND k.status_approval = 'DISETUJUI');

SELECT COUNT(*) AS koreksi_pending_akan_dihapus
FROM dbo.absensi_koreksi
WHERE status_approval = 'PENDING' AND tanggal >= @dari;

SELECT COUNT(*) AS tap_akan_diproses_ulang
FROM dbo.zkteco_checkinout
WHERE checktime >= @dari;
GO


/* =============== LANGKAH 2 - hapus baris hantu & koreksi palsu ===============
   Baris "hantu" = baris ZKTECO tanpa jam_masuk yang lahir dari bug penimpaan.
   Data yang sudah DISETUJUI atasan TIDAK ikut terhapus.                     */
DECLARE @dari DATE = '2026-07-14';

BEGIN TRAN;

    DELETE k
    FROM dbo.absensi_koreksi k
    WHERE k.status_approval = 'PENDING' AND k.tanggal >= @dari;

    DELETE a
    FROM dbo.absensi a
    WHERE a.tanggal >= @dari
      AND a.sumber = 'ZKTECO'
      AND a.jam_masuk IS NULL
      AND NOT EXISTS (SELECT 1 FROM dbo.absensi_koreksi k
                      WHERE k.pegawai_id = a.pegawai_id AND k.tanggal = a.tanggal
                        AND k.status_approval = 'DISETUJUI');

    SELECT @@ROWCOUNT AS terhapus;

COMMIT;   -- ganti jadi ROLLBACK kalau angkanya terasa janggal
GO


/* =============== LANGKAH 3 - tandai tap supaya diolah ulang =============== */
DECLARE @dari DATE = '2026-07-14';

UPDATE dbo.zkteco_checkinout
SET diproses = 0
WHERE checktime >= @dari;

SELECT COUNT(*) AS siap_diproses FROM dbo.zkteco_checkinout WHERE diproses = 0;
GO

/* ---------------------------------------------------------------------
   >>> SEKARANG JALANKAN DI CMD (bisa 10-30 menit, biarkan sampai selesai):
       C:\xampp\php\php.exe C:\xampp\htdocs\hris\presensi\sync_zkteco_proses.php
   Baru lanjut ke LANGKAH 4.
   --------------------------------------------------------------------- */


/* =============== LANGKAH 4 - verifikasi hasil =============== */
SELECT CAST(z.checktime AS DATE) AS tgl,
       COUNT(DISTINCT z.zk_userid) AS org_nge_tap,
       (SELECT COUNT(DISTINCT a.pegawai_id) FROM dbo.absensi a
         WHERE a.tanggal = CAST(z.checktime AS DATE)) AS org_di_absensi,
       (SELECT COUNT(*) FROM dbo.absensi a
         WHERE a.tanggal = CAST(z.checktime AS DATE) AND a.jam_masuk IS NULL) AS tanpa_jam_masuk,
       (SELECT COUNT(*) FROM dbo.absensi a
         WHERE a.tanggal = CAST(z.checktime AS DATE) AND a.jam_keluar IS NULL) AS tanpa_jam_keluar
FROM dbo.zkteco_checkinout z
WHERE z.checktime >= '2026-07-14'
GROUP BY CAST(z.checktime AS DATE)
ORDER BY tgl DESC;
-- TARGET: tanpa_jam_masuk & tanpa_jam_keluar turun ke belasan/puluhan,
--         bukan lagi ratusan seperti 07/10/11/12 Agustus.
GO


/* =====================================================================
   LAMPIRAN A - daftar zkteco_userid yang BELUM DIPETAKAN
   (18 orang per 13-08-2026: karyawan baru sejak 24 Juli)
   ===================================================================== */
SELECT z.zk_userid,
       COUNT(*)          AS jml_tap,
       MIN(z.checktime)  AS tap_pertama,
       MAX(z.checktime)  AS tap_terakhir
FROM dbo.zkteco_checkinout z
WHERE NOT EXISTS (SELECT 1 FROM dbo.pegawai p
                  WHERE p.zkteco_userid = z.zk_userid AND p.is_aktif = 1)
GROUP BY z.zk_userid
ORDER BY jml_tap DESC;
GO

/* Cara memetakan (JANGAN copy tanda < > nya!):
   1. Cari id_peg karyawan yang benar:
        SELECT id_peg, nik, nama_peg FROM dbo.pegawai WHERE nama_peg LIKE '%budi%';
   2. Isi userid-nya - ganti 4509 dan 1740 dengan angka yang sesungguhnya:
        UPDATE dbo.pegawai SET zkteco_userid = 4509 WHERE id_peg = 1740;
   3. Kalau karyawannya memang belum ada di tabel pegawai, pakai
      presensi/seed_pegawai_dari_zkteco.php (set $TULIS = false dulu untuk
      melihat daftarnya, baru true untuk menyimpan).
   4. Setelah semua dipetakan, ulangi LANGKAH 3 lalu jalankan lagi
      sync_zkteco_proses.php supaya tap mereka ikut terolah.
*/
