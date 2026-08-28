/* ============================================================================
   PT SUPRACOR SEJAHTERA — KELOMPOK TIPE OTOMATIS DARI BATCH STB
   Dibuat : 12 Agustus 2026

   TIGA TEMUAN DARI HASIL SEBELUMNYA

   1. cOutSTB ternyata char(1)
      Kolom itu dirancang sebagai penanda ya/tidak, bukan untuk teks.
      Sekarang diisi huruf 'K'. Perlu dicatat: kalau suatu saat ada modul lain
      memakai kolom ini sebagai flag, hurufnya bisa bentrok.

   2. cRak bukan lokasi rak
      Isinya LANTHEC, IKAT, FOLDER GLUE, SLITTER. Itu nama proses produksi,
      bukan tempat penyimpanan. Label di dashboard diperbaiki jadi "Proses".

   3. BATCH STB SUDAH MEMBAWA TIPE BARANGNYA
      Lihat cNoOp tiap batch pada SLC/2607/01151:
         SPS/2607/01151-B01     -> BOX
         SPS/2607/01151-P0101   -> PART   (part panjang)
         SPS/2607/01151-P0201   -> PART   (part pendek)
      Jadi begitu batch dipilih, kelompoknya bisa ditentukan sendiri oleh
      sistem. Tidak perlu ditebak orang, dan tidak bisa salah pilih.

   YANG DIKERJAKAN FILE INI
     Kalau koreksi menunjuk batch STB, kelompok tipenya diambil dari cNoOp
     batch tersebut. Kalau orang memilih kelompok yang berbeda, prosedurnya
     menolak dengan pesan yang menyebutkan kelompok yang benar.
   ============================================================================ */

USE dbSopanusa;
GO
SET NOCOUNT ON;
GO

/* ---------------------------------------------------------------------------
   LANGKAH 1 — VIEW BATCH IKUT MENAMPILKAN KELOMPOK TIPENYA
   --------------------------------------------------------------------------- */
IF OBJECT_ID('dbo.vwStokPerStb') IS NOT NULL DROP VIEW dbo.vwStokPerStb;
GO
CREATE VIEW dbo.vwStokPerStb AS
SELECT  RTRIM(b.cNoSTB)   AS cNoSTB,
        RTRIM(b.cNoSc)    AS cNoSc,
        CAST(b.dTanggal AS DATE) AS dTglStb,
        b.cNoOp,
        ISNULL(h.cKelompok, 'LAIN')                AS cKelompok,
        b.cRak            AS cProses,   -- isinya nama proses, bukan rak
        b.cShift,
        ISNULL(b.nQty, 0)                          AS nQtyMasuk,
        ISNULL(b.nQtyOut, 0)                       AS nQtyKoreksi,
        ISNULL(b.nQty, 0) - ISNULL(b.nQtyOut, 0)   AS nSisaBatch,
        DATEDIFF(day, b.dTanggal, CAST(GETDATE() AS DATE)) AS nUmur,
        b.cNama, b.cNamabrg
FROM    dbo.tbStbBJ b
LEFT JOIN dbo.tbStokGudangHuruf h ON h.cHuruf = dbo.fnHurufTipe(b.cNoOp)
WHERE   b.cNoSc IS NOT NULL AND LTRIM(RTRIM(b.cNoSc)) <> '';
GO

/* ---------------------------------------------------------------------------
   LANGKAH 2 — PROSEDUR MENENTUKAN KELOMPOK DARI BATCH
   --------------------------------------------------------------------------- */
IF OBJECT_ID('dbo.spTambahKoreksiStok') IS NOT NULL DROP PROCEDURE dbo.spTambahKoreksiStok;
GO
CREATE PROCEDURE dbo.spTambahKoreksiStok
    @cNoSc       VARCHAR(30),
    @nQtyPc      INT,
    @cJenis      VARCHAR(30),
    @cKeterangan NVARCHAR(300),
    @UserId      VARCHAR(50),
    @dTanggal    DATE          = NULL,
    @cNoBukti    VARCHAR(50)   = NULL,
    @cDivisi     VARCHAR(30)   = NULL,
    @cKelompok   VARCHAR(12)   = NULL,
    @cNoSTB      VARCHAR(20)   = NULL
AS
BEGIN
    SET NOCOUNT ON;
    SET @dTanggal  = ISNULL(@dTanggal, CAST(GETDATE() AS DATE));
    SET @cNoSc     = RTRIM(LTRIM(@cNoSc));
    SET @cKelompok = NULLIF(RTRIM(LTRIM(ISNULL(@cKelompok, ''))), '');
    SET @cNoSTB    = NULLIF(RTRIM(LTRIM(ISNULL(@cNoSTB, ''))), '');

    IF @cNoSc = '' OR @cNoSc IS NULL THROW 51001, 'Nomor SC wajib diisi.', 1;
    IF @nQtyPc = 0                   THROW 51002, 'Jumlah koreksi tidak boleh nol.', 1;
    IF LTRIM(RTRIM(ISNULL(@cKeterangan,''))) = ''
        THROW 51003, 'Keterangan wajib diisi. Tulis alasan koreksinya supaya bisa ditelusuri.', 1;
    IF LTRIM(RTRIM(ISNULL(@UserId,''))) = '' THROW 51004, 'UserId wajib diisi.', 1;
    IF NOT EXISTS (SELECT 1 FROM dbo.tbStokGudangJenisKoreksi WHERE cJenis = @cJenis AND lAktif = 1)
        THROW 51005, 'Jenis koreksi tidak dikenal.', 1;
    IF NOT EXISTS (SELECT 1 FROM dbo.tbStbBJ b WHERE RTRIM(b.cNoSc) = @cNoSc)
       AND NOT EXISTS (SELECT 1 FROM dbo.tbStokGudangExcel e WHERE e.cNoScDb = @cNoSc)
        THROW 51006, 'Nomor SC tidak ditemukan di tbStbBJ maupun di patokan gudang.', 1;

    DECLARE @Cut DATE = (SELECT MAX(dCutOff) FROM dbo.tbStokGudangExcel);
    IF @dTanggal <= @Cut THROW 51007, 'Tanggal koreksi harus SETELAH tanggal cut-off patokan.', 1;
    IF @dTanggal > CAST(GETDATE() AS DATE) THROW 51008, 'Tanggal koreksi tidak boleh di masa depan.', 1;

    IF @cNoSTB IS NOT NULL
    BEGIN
        IF NOT EXISTS (SELECT 1 FROM dbo.tbStbBJ b
                       WHERE RTRIM(b.cNoSTB) = @cNoSTB AND RTRIM(b.cNoSc) = @cNoSc)
            THROW 51012, 'Nomor STB tersebut bukan milik NO. SC ini. Periksa kembali pilihan batch-nya.', 1;

        /* Kelompok ditentukan dari nomor OP batch itu sendiri, bukan ditebak */
        DECLARE @KelBatch VARCHAR(12) =
            (SELECT TOP 1 ISNULL(h.cKelompok, 'LAIN')
             FROM   dbo.tbStbBJ b
             LEFT JOIN dbo.tbStokGudangHuruf h ON h.cHuruf = dbo.fnHurufTipe(b.cNoOp)
             WHERE  RTRIM(b.cNoSTB) = @cNoSTB);

        /* THROW tidak bisa memakai pesan dari variabel, jadi dipakai RAISERROR
           supaya nama kelompok yang benar ikut disebut. */
        IF @cKelompok IS NOT NULL AND @cKelompok <> @KelBatch
        BEGIN
            DECLARE @Pesan NVARCHAR(400) =
                N'Batch ' + @cNoSTB + N' berisi barang kelompok ' + @KelBatch +
                N', bukan ' + @cKelompok + N'. Pilih kelompok yang sesuai, atau pilih batch lain.';
            RAISERROR(@Pesan, 16, 1);
            RETURN;
        END
        SET @cKelompok = @KelBatch;

        IF @nQtyPc < 0
        BEGIN
            DECLARE @Isi INT = (SELECT SUM(ISNULL(nQty,0)) FROM dbo.tbStbBJ WHERE RTRIM(cNoSTB) = @cNoSTB);
            DECLARE @Sudah INT = (SELECT ISNULL(SUM(-nQtyPc),0) FROM dbo.tbStokGudangKoreksi
                                  WHERE RTRIM(cNoSTB) = @cNoSTB AND lVoid = 0 AND nQtyPc < 0);
            IF (-@nQtyPc) + @Sudah > @Isi
                THROW 51013, 'Pengurangan melebihi isi batch STB tersebut. Periksa jumlahnya, atau pilih batch lain.', 1;
        END
    END

    IF @cKelompok IS NULL
        SELECT TOP 1 @cKelompok = cKelompok FROM dbo.tbStokSnapTipe
        WHERE cNoSc = @cNoSc ORDER BY ABS(nStokPc) DESC;
    SET @cKelompok = ISNULL(@cKelompok, 'BOX');

    IF @cKelompok NOT IN ('BOX','PART+LAYER','SHEET','LAIN')
        THROW 51009, 'Kelompok tipe tidak dikenal.', 1;

    INSERT INTO dbo.tbStokGudangKoreksi
          (cNoSc, dTanggal, nQtyPc, cJenis, cKeterangan, cNoBukti, UserId,
           cDivisi, cKelompok, cNoSTB)
    VALUES(@cNoSc, @dTanggal, @nQtyPc, @cJenis, @cKeterangan, @cNoBukti, @UserId,
           @cDivisi, @cKelompok, @cNoSTB);

    DECLARE @Id INT = SCOPE_IDENTITY();
    IF @cNoSTB IS NOT NULL EXEC dbo.spSinkronNQtyOut @cNoSTB = @cNoSTB;

    SELECT @Id AS nId, @cNoSc AS cNoSc, @nQtyPc AS nQtyPc, @cKelompok AS cKelompok,
           @cNoSTB AS cNoSTB,
           (SELECT ISNULL(SUM(nQtyPc),0) FROM dbo.tbStokGudangKoreksi
            WHERE cNoSc = @cNoSc AND lVoid = 0) AS total_koreksi_sc;
END
GO

/* ---------------------------------------------------------------------------
   LANGKAH 3 — PERIKSA
   --------------------------------------------------------------------------- */
SELECT TOP 20 cNoSTB, dTglStb, cNoOp, cKelompok, cProses,
       nQtyMasuk, nQtyKoreksi, nSisaBatch, nUmur
FROM   dbo.vwStokPerStb
WHERE  cNoSc = 'SLC/2607/01151' ORDER BY dTglStb DESC, cNoSTB DESC;

-- Sebaran kelompok per batch, memastikan pemetaan hurufnya kena
SELECT cKelompok, COUNT(*) AS jml_batch, SUM(nSisaBatch) AS total_sisa
FROM   dbo.vwStokPerStb
WHERE  dTglStb > '2026-07-31'
GROUP  BY cKelompok ORDER BY SUM(nSisaBatch) DESC;
GO
