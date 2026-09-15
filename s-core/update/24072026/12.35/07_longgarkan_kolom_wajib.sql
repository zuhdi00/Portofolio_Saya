/* =====================================================================
   Melonggarkan kolom NOT NULL yang tidak mungkin diketahui saat seeding.
   ALASAN: data pegawai masuk BERTAHAP - identitas dari ZKTeco dulu
   (nama, NIK, departemen), detail lain dilengkapi HR kemudian.
   Memaksa NOT NULL akan mendorong pengisian data karangan.

   Ini perubahan yang MELONGGARKAN, jadi aman: tidak ada data hilang,
   dan baris yang sudah terisi tetap seperti semula.
   ===================================================================== */
USE dbHR;
GO

/* ---------- pegawai ----------
   nama_peg TETAP wajib - itu satu-satunya yang selalu diketahui.   */

ALTER TABLE dbo.pegawai ALTER COLUMN no_ktp          NVARCHAR(20)  NULL;
ALTER TABLE dbo.pegawai ALTER COLUMN no_hp_peg       NVARCHAR(15)  NULL;
ALTER TABLE dbo.pegawai ALTER COLUMN tgl_lahir       DATE          NULL;
ALTER TABLE dbo.pegawai ALTER COLUMN tempat_lahir    NVARCHAR(255) NULL;
ALTER TABLE dbo.pegawai ALTER COLUMN alamat_ktp_peg  NVARCHAR(255) NULL;
ALTER TABLE dbo.pegawai ALTER COLUMN tgl_masuk       DATE          NULL;
GO

-- Tiga ini NOT NULL sekaligus punya CHECK constraint.
-- Dibiarkan kosong lebih jujur daripada diisi tebakan.
ALTER TABLE dbo.pegawai ALTER COLUMN gender          NVARCHAR(255) NULL;
ALTER TABLE dbo.pegawai ALTER COLUMN status_nikah    NVARCHAR(255) NULL;
ALTER TABLE dbo.pegawai ALTER COLUMN status_karyawan NVARCHAR(255) NULL;
GO


/* ---------- department ----------
   Departemen baru belum tentu sudah ditunjuk kepalanya.            */
ALTER TABLE dbo.department ALTER COLUMN kepala_dept NVARCHAR(255) NULL;
GO


/* ---------- jabatan ----------
   Untuk kebutuhan form nanti: deskripsi & level boleh menyusul.    */
ALTER TABLE dbo.jabatan ALTER COLUMN desk_jabatan  NVARCHAR(MAX) NULL;
ALTER TABLE dbo.jabatan ALTER COLUMN level_jabatan NVARCHAR(255) NULL;
GO


/* ---------- unit_kerja & absensi: TIDAK diubah ----------
   kode_unit/nama_unit selalu diisi script seeding.
   absensi.pegawai_id & tanggal memang harus wajib.                 */


/* ---------- Cek apakah no_ktp punya index UNIQUE ----------
   Kalau ada, 578 baris dengan no_ktp kosong akan bentrok.
   Setelah dilonggarkan jadi NULL, ini tidak masalah karena
   SQL Server memperbolehkan banyak NULL pada unique index filtered.
   Tapi tetap perlu dipastikan.                                     */
SELECT i.name AS nama_index, i.is_unique, i.has_filter, i.filter_definition
FROM sys.indexes i
JOIN sys.index_columns ic ON ic.object_id = i.object_id AND ic.index_id = i.index_id
JOIN sys.columns c        ON c.object_id  = i.object_id AND c.column_id = ic.column_id
WHERE i.object_id = OBJECT_ID('dbo.pegawai') AND c.name = 'no_ktp';
GO


/* ---------- Verifikasi hasil ---------- */
SELECT c.COLUMN_NAME AS kolom, c.DATA_TYPE AS tipe, c.IS_NULLABLE AS boleh_null
FROM INFORMATION_SCHEMA.COLUMNS c
WHERE c.TABLE_SCHEMA = 'dbo' AND c.TABLE_NAME = 'pegawai'
  AND c.COLUMN_NAME IN ('no_ktp','no_hp_peg','tgl_lahir','tempat_lahir','gender',
                        'status_nikah','alamat_ktp_peg','tgl_masuk','status_karyawan','nama_peg')
ORDER BY c.COLUMN_NAME;
GO
-- Yang diharapkan: semua YES, kecuali nama_peg = NO
