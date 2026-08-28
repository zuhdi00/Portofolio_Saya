/* Tambah kolom nama atasan divisi di lembur_form (untuk cetak TTD) */
USE dbHR;
GO
IF COL_LENGTH('dbo.lembur_form','atasan_nama') IS NULL
BEGIN
    ALTER TABLE dbo.lembur_form ADD atasan_nama NVARCHAR(128) NULL;
    PRINT '>> kolom atasan_nama dibuat';
END
ELSE PRINT '>> atasan_nama sudah ada';
GO
