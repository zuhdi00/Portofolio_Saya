/* =====================================================================
   Lihat SEMUA CHECK constraint di dbHR beserta nilai yang diizinkan.
   Jalankan di SSMS -> database dbHR -> lihat kolom "definition".
   ===================================================================== */
USE dbHR;
GO

SELECT
    t.name              AS tabel,
    c.name              AS kolom,
    cc.name             AS nama_constraint,
    cc.definition       AS nilai_yang_diizinkan
FROM sys.check_constraints cc
JOIN sys.tables  t ON t.object_id = cc.parent_object_id
LEFT JOIN sys.columns c
       ON c.object_id  = cc.parent_object_id
      AND c.column_id  = cc.parent_column_id
WHERE t.name IN ('pegawai','keluarga_pegawai','pendidikan_pegawai','pengalaman_kerja','absensi')
ORDER BY t.name, c.name;
GO

/* Contoh hasil yang akan muncul di kolom "nilai_yang_diizinkan":
   ([gender]='P' OR [gender]='L')
   ([agama]='Konghucu' OR [agama]='Buddha' OR ...)
   -> Nilai di dalam tanda kutip itulah yang HARUS dikirim dari form.
*/
