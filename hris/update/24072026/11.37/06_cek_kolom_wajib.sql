/* =====================================================================
   Lihat kolom WAJIB (NOT NULL tanpa default) di tabel dbHR.
   Berguna untuk tahu apa saja yang harus diisi saat INSERT.
   ===================================================================== */
USE dbHR;
GO

SELECT
    c.TABLE_NAME                              AS tabel,
    c.COLUMN_NAME                             AS kolom,
    c.DATA_TYPE                               AS tipe,
    c.CHARACTER_MAXIMUM_LENGTH                AS panjang,
    c.IS_NULLABLE                             AS boleh_null,
    ISNULL(c.COLUMN_DEFAULT, '-')             AS nilai_default
FROM INFORMATION_SCHEMA.COLUMNS c
WHERE c.TABLE_SCHEMA = 'dbo'
  AND c.TABLE_NAME IN ('department','unit_kerja','pegawai','jabatan','absensi')
  AND c.IS_NULLABLE = 'NO'
  AND c.COLUMN_DEFAULT IS NULL
  AND COLUMNPROPERTY(OBJECT_ID('dbo.' + c.TABLE_NAME), c.COLUMN_NAME, 'IsIdentity') = 0
ORDER BY c.TABLE_NAME, c.ORDINAL_POSITION;
GO


/* ---------------------------------------------------------------------
   OPSIONAL - kalau ada kolom yang seharusnya boleh kosong.
   Contoh: sebuah department belum tentu sudah punya kepala.
   Longgarkan supaya tidak memaksa data karangan.
   --------------------------------------------------------------------- */
/*
ALTER TABLE dbo.department ALTER COLUMN kepala_dept NVARCHAR(100) NULL;
GO
*/
