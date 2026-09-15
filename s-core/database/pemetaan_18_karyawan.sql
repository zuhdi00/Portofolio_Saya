/* =====================================================================
   PEMETAAN 18 KARYAWAN AKTIF YANG BELUM TERHUBUNG KE ZKTECO
   Data per 13-08-2026. AC-No di ZKTeco = NIK di dbo.pegawai,
   jadi pencocokan dilakukan lewat NIK (bukan tebak nama).

   Urutan: LANGKAH A -> B -> C -> D
   Jalankan per LANGKAH, jangan F5 semuanya sekaligus.
   ===================================================================== */
USE dbHR;
GO

/* ---------- daftar 18 orang (userid, AC-No/NIK, nama di ZKTeco) ---------- */
IF OBJECT_ID('tempdb..#zk') IS NOT NULL DROP TABLE #zk;
CREATE TABLE #zk (uid INT, acno NVARCHAR(20), nama NVARCHAR(100));
INSERT INTO #zk (uid, acno, nama) VALUES
 (4449, N'13105', N'Erisa'),
 (4506, N'13136', N'Mukhammad Zaiunuddin'),
 (4509, N'13138', N'Aditya Santoso'),
 (4510, N'13139', N'Rahmat Syarifudin'),
 (4511, N'13140', N'Subiantoro'),
 (4516, N'13716', N'Faisal'),
 (4536, N'13144', N'Dedik Arrohman'),
 (4537, N'13145', N'Idul Sadewa'),
 (4538, N'13146', N'Rachmad Purwanto'),
 (4539, N'13147', N'Danang Maulana Ibrahim'),
 (4542, N'13150', N'Rynaldo Dwi Putra'),
 (4543, N'13151', N'Much Nur Eka Kusuma'),
 (4544, N'13152', N'Muhammad Ide Trianjaya'),
 (4546, N'13153', N'Dealova Apriliawan'),
 (4547, N'13717', N'Ega Nanda Yusharrizal'),
 (4548, N'13154', N'Ifan Rendiansyah'),
 (4549, N'13155', N'Fahmi Dzikri Oktavian'),
 (4551, N'13156', N'Ahmad Qusyaini');
GO


/* =============== LANGKAH A - lihat dulu, JANGAN ubah apa pun ===============
   Periksa apakah nama di ZKTeco cocok dengan nama di dbo.pegawai.
   Kalau ada yang jelas beda orang, JANGAN lanjut - lapor HR dulu.        */
SELECT z.uid          AS zkteco_userid,
       z.acno         AS nik_acno,
       z.nama         AS nama_di_zkteco,
       p.id_peg,
       p.nama_peg     AS nama_di_hris,
       p.is_aktif,
       p.zkteco_userid AS userid_lama,
       CASE WHEN p.id_peg IS NULL              THEN 'BELUM ADA DI PEGAWAI - pakai seed'
            WHEN p.is_aktif = 0                THEN 'ADA tapi is_aktif=0 - aktifkan dulu'
            WHEN p.zkteco_userid IS NULL       THEN 'SIAP DIPETAKAN'
            WHEN p.zkteco_userid = z.uid       THEN 'sudah benar'
            ELSE 'PUNYA USERID LAMA - akan diganti' END AS tindakan
FROM #zk z
LEFT JOIN dbo.pegawai p ON p.nik = z.acno
ORDER BY z.uid;
GO


/* =============== LANGKAH B - pastikan tidak ada bentrok userid ===============
   Unique index UX_pegawai_zkuserid melarang 1 userid dipakai 2 pegawai.   */
SELECT z.uid, z.nama AS nama_di_zkteco,
       p.id_peg, p.nik, p.nama_peg AS sudah_dipakai_oleh
FROM #zk z
JOIN dbo.pegawai p ON p.zkteco_userid = z.uid
WHERE p.nik <> z.acno OR p.nik IS NULL;
-- Kalau ada hasil: userid itu terlanjur menempel di pegawai LAIN.
-- Kosongkan dulu yang salah:  UPDATE dbo.pegawai SET zkteco_userid = NULL WHERE id_peg = <id yang salah>;
GO


/* =============== LANGKAH C - eksekusi pemetaan ===============
   Hanya menyentuh pegawai yang NIK-nya cocok dan masih aktif.   */
BEGIN TRAN;

    UPDATE p
    SET p.zkteco_userid = z.uid
    FROM dbo.pegawai p
    JOIN #zk z ON p.nik = z.acno
    WHERE p.is_aktif = 1
      AND (p.zkteco_userid IS NULL OR p.zkteco_userid <> z.uid);

    SELECT @@ROWCOUNT AS berhasil_dipetakan;

COMMIT;   -- ubah jadi ROLLBACK kalau angkanya tidak sesuai harapan
GO


/* =============== LANGKAH D - sisa yang belum tertangani ===============
   Ini orang-orang yang NIK-nya tidak ketemu di dbo.pegawai.
   Tambahkan manual lewat menu Pegawai di HRIS, atau pakai
   presensi/seed_pegawai_dari_zkteco.php (set $TULIS = false dulu).      */
SELECT z.uid, z.acno AS nik_acno, z.nama AS nama_di_zkteco
FROM #zk z
WHERE NOT EXISTS (SELECT 1 FROM dbo.pegawai p
                  WHERE p.zkteco_userid = z.uid AND p.is_aktif = 1)
ORDER BY z.uid;
GO

/* Setelah semua tertangani:
   UPDATE dbo.zkteco_checkinout SET diproses = 0 WHERE checktime >= '2026-07-14';
   lalu jalankan lagi sync_zkteco_proses.php  */


/* =====================================================================
   OPSIONAL - rapikan tap mantan karyawan (mesin lama, 2013-2020)
   1.263 userid "belum dipetakan" itu sebagian besar karyawan lama yang
   sudah dihapus dari mesin. Tapnya akan terus di-scan tiap sinkronisasi
   dan bikin daftar peringatan jadi panjang. Tandai selesai supaya diam:
   ===================================================================== */
-- Lihat dulu berapa banyak:
SELECT COUNT(*) AS tap_mantan_karyawan
FROM dbo.zkteco_checkinout z
WHERE z.diproses = 0
  AND z.checktime < '2025-01-01'
  AND NOT EXISTS (SELECT 1 FROM dbo.pegawai p
                  WHERE p.zkteco_userid = z.zk_userid AND p.is_aktif = 1);

-- Kalau angkanya masuk akal, baru tandai (tap tetap tersimpan, hanya tidak diproses):
/*
UPDATE z SET z.diproses = 1
FROM dbo.zkteco_checkinout z
WHERE z.diproses = 0
  AND z.checktime < '2025-01-01'
  AND NOT EXISTS (SELECT 1 FROM dbo.pegawai p
                  WHERE p.zkteco_userid = z.zk_userid AND p.is_aktif = 1);
*/
GO
