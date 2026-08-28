/* =====================================================================
   Tambah kolom KLASIFIKASI pengunduran diri (voluntary/involuntary dll)
   Bisa diedit HRD / Admin IT.
   ===================================================================== */
USE dbHR;
GO

IF COL_LENGTH('dbo.pengunduran_diri','klasifikasi') IS NULL
BEGIN
    ALTER TABLE dbo.pengunduran_diri ADD klasifikasi NVARCHAR(40) NULL;
    PRINT '>> kolom klasifikasi dibuat';
END ELSE PRINT '>> klasifikasi sudah ada';
GO

IF COL_LENGTH('dbo.pengunduran_diri','klasifikasi_catatan') IS NULL
BEGIN
    ALTER TABLE dbo.pengunduran_diri ADD klasifikasi_catatan NVARCHAR(300) NULL;
    PRINT '>> kolom klasifikasi_catatan dibuat';
END ELSE PRINT '>> klasifikasi_catatan sudah ada';
GO

/* Nilai yang dipakai (tidak dipaksa CHECK supaya HR bisa menambah kelak):
     VOLUNTARY            - mengundurkan diri atas kemauan sendiri
     INVOLUNTARY          - diberhentikan perusahaan
     RETIREMENT           - pensiun
     CONTRACT_END         - kontrak habis
     MUTUAL_AGREEMENT     - kesepakatan bersama
     ABSCOND              - mangkir / menghilang
     LAINNYA
*/
SELECT name FROM sys.columns WHERE object_id=OBJECT_ID('dbo.pengunduran_diri') ORDER BY column_id;
GO
