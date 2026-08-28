/* ============================================================================
   PT SUPRACOR SEJAHTERA — PERBAIKAN spSinkronNQtyOut
   Dibuat : 12 Agustus 2026

   ERROR
     Msg 8152 — String or binary data would be truncated,
     Procedure spSinkronNQtyOut, Line 7.

   PENYEBAB
     Prosedur menulis teks 'KOREKSI STOK' (12 karakter) ke kolom cOutSTB,
     padahal lebar kolom itu lebih sempit. Saya menebak lebarnya tanpa
     memeriksa lebih dulu.

   PERBAIKAN
     Panjang teks disesuaikan OTOMATIS dengan lebar kolom yang sebenarnya,
     memakai COL_LENGTH. Jadi tidak perlu menebak, dan tetap benar walau
     nanti lebar kolomnya diubah.

   Langkah 1 menampilkan lebar kolomnya, sekadar supaya terlihat.
   ============================================================================ */

USE dbSopanusa;
GO
SET NOCOUNT ON;
GO

/* ---------------------------------------------------------------------------
   LANGKAH 1 — LEBAR KOLOM YANG SEBENARNYA
   --------------------------------------------------------------------------- */
SELECT COLUMN_NAME, DATA_TYPE, CHARACTER_MAXIMUM_LENGTH AS lebar
FROM   INFORMATION_SCHEMA.COLUMNS
WHERE  TABLE_NAME = 'tbStbBJ'
  AND  COLUMN_NAME IN ('nQtyOut','cOutSTB','dTanggalOut','cNoSTB','cKeterangan')
ORDER  BY COLUMN_NAME;
GO

/* ---------------------------------------------------------------------------
   LANGKAH 2 — PROSEDUR YANG SUDAH DIPERBAIKI
   --------------------------------------------------------------------------- */
IF OBJECT_ID('dbo.spSinkronNQtyOut') IS NOT NULL DROP PROCEDURE dbo.spSinkronNQtyOut;
GO
CREATE PROCEDURE dbo.spSinkronNQtyOut
    @cNoSTB VARCHAR(20) = NULL     -- kosong = semua batch yang punya koreksi
AS
BEGIN
    SET NOCOUNT ON;

    /* Panjang penanda disesuaikan dengan lebar kolom yang sebenarnya.
       Kalau kolomnya sempit, cukup 'KOR' atau bahkan satu huruf. */
    DECLARE @Lebar INT = ISNULL(COL_LENGTH('dbo.tbStbBJ', 'cOutSTB'), 1);
    DECLARE @Tag VARCHAR(30) =
        CASE WHEN @Lebar >= 12 THEN 'KOREKSI STOK'
             WHEN @Lebar >= 7  THEN 'KOREKSI'
             WHEN @Lebar >= 3  THEN 'KOR'
             ELSE 'K' END;
    SET @Tag = LEFT(@Tag, @Lebar);

    ;WITH kor AS (
        SELECT RTRIM(cNoSTB) AS stb,
               SUM(CASE WHEN nQtyPc < 0 THEN -nQtyPc ELSE 0 END) AS keluar,
               MAX(dTanggal) AS tgl
        FROM   dbo.tbStokGudangKoreksi
        WHERE  lVoid = 0 AND cNoSTB IS NOT NULL
          AND  (@cNoSTB IS NULL OR RTRIM(cNoSTB) = @cNoSTB)
        GROUP  BY RTRIM(cNoSTB)
    )
    UPDATE b
    SET    b.nQtyOut     = k.keluar,
           b.cOutSTB     = @Tag,
           b.dTanggalOut = k.tgl
    FROM   dbo.tbStbBJ b
    INNER JOIN kor k ON RTRIM(b.cNoSTB) = k.stb;

    DECLARE @Isi INT = @@ROWCOUNT;

    /* Batch yang koreksinya sudah dibatalkan seluruhnya, kembalikan ke nol */
    UPDATE b
    SET    b.nQtyOut = 0, b.cOutSTB = NULL, b.dTanggalOut = NULL
    FROM   dbo.tbStbBJ b
    WHERE  RTRIM(ISNULL(b.cOutSTB,'')) = @Tag
      AND  (@cNoSTB IS NULL OR RTRIM(b.cNoSTB) = @cNoSTB)
      AND  NOT EXISTS (SELECT 1 FROM dbo.tbStokGudangKoreksi k
                       WHERE RTRIM(k.cNoSTB) = RTRIM(b.cNoSTB)
                         AND k.lVoid = 0 AND k.nQtyPc < 0);

    SELECT @Isi AS batch_diisi, @@ROWCOUNT AS batch_dikosongkan,
           @Tag AS penanda_dipakai, @Lebar AS lebar_kolom_cOutSTB;
END
GO

/* ---------------------------------------------------------------------------
   LANGKAH 3 — JALANKAN & PERIKSA
   --------------------------------------------------------------------------- */
EXEC dbo.spSinkronNQtyOut;
GO

-- Koreksi yang ada, beserta batch-nya
SELECT nId, cNoSc, cNoSTB, cKelompok, dTanggal, nQtyPc, cJenis, UserId, lVoid
FROM   dbo.tbStokGudangKoreksi ORDER BY nId;

-- Baris tbStbBJ yang nQtyOut-nya terisi
SELECT cNoSTB, cNoSc, nQty, nQtyOut, cOutSTB,
       CONVERT(VARCHAR(10), dTanggalOut, 23) AS tgl_out
FROM   dbo.tbStbBJ WHERE ISNULL(nQtyOut,0) <> 0;

-- Contoh batch untuk satu NO. SC
SELECT TOP 20 cNoSTB, dTglStb, cNoOp, cRak, nQtyMasuk, nQtyKoreksi, nSisaBatch, nUmur
FROM   dbo.vwStokPerStb
WHERE  cNoSc = 'SLC/2607/01151' ORDER BY dTglStb DESC;
GO
