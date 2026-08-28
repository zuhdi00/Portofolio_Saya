/* ============================================================================
   PT SUPRACOR SEJAHTERA — KOREKSI MELEKAT KE NOMOR STB
   Dibuat : 12 Agustus 2026

   HASIL PEMERIKSAAN
     nQtyOut kosong sama sekali: 0 dari 435.295 baris.
     cOutSTB dan dTanggalOut juga kosong.
     Tidak ada prosedur maupun view yang menyentuhnya.
     -> Aman dipakai, tidak akan bertabrakan dengan modul lain.

     Pembagian batch per NO. SC (Agustus):
        1 baris STB           133 SC
        2-3 baris             106 SC
        4-10 baris             56 SC
        lebih dari 10 baris     8 SC
     170 dari 303 SC punya lebih dari satu batch. Pelacakan per STB memang
     berguna, bukan sekadar teori.

   RANCANGAN YANG DIPAKAI — SATU ARAH

     tbStokGudangKoreksi  ->  tbStbBJ.nQtyOut
        (sumber kebenaran)      (bayangan, untuk ditampilkan)

     Koreksi tetap disimpan di tbStokGudangKoreksi, sekarang dengan kolom
     cNoSTB supaya bisa menunjuk batch tertentu. Perhitungan stok tetap
     membaca dari tabel itu.

     nQtyOut diisi OTOMATIS dari tabel koreksi, dan hanya untuk baris STB
     yang memang punya koreksi. Tidak pernah diisi manual.

   KENAPA BUKAN LANGSUNG MENULIS KE nQtyOut

     1. tbStbBJ adalah catatan produksi. Kalau dashboard stok menulis
        langsung ke sana, kesalahan input gudang bisa merembet ke laporan
        produksi, dan tidak ada jejak siapa yang mengubah.
     2. nQtyOut cuma menyimpan satu angka. Tidak bisa menampung sebab,
        keterangan, nomor bukti, nama pengisi, dan riwayat pembatalan.
     3. Koreksi yang dibatalkan tetap harus terlihat. Kalau angkanya
        ditimpa langsung di nQtyOut, jejaknya hilang.

     Dengan cara ini semuanya didapat: kelihatan di layar STB, tapi jejaknya
     utuh dan tbStbBJ tetap bisa dikembalikan kapan saja tanpa kehilangan apa
     pun, karena isinya cuma bayangan.

   Langkah 1-3 mengubah struktur. Langkah 4 sinkronisasi. Langkah 5 pemeriksaan.
   ============================================================================ */

USE dbSopanusa;
GO
SET NOCOUNT ON;
GO

/* ---------------------------------------------------------------------------
   LANGKAH 1 — CADANGKAN KOLOM nQtyOut SEBELUM DISENTUH
   Isinya nol semua, tapi tetap dicadangkan supaya bisa dikembalikan persis.
   --------------------------------------------------------------------------- */
IF OBJECT_ID('dbo.tbStbBJ_nQtyOut_BAK') IS NULL
    SELECT cNoSTB, nQtyOut, cOutSTB, dTanggalOut, GETDATE() AS dCadang
    INTO   dbo.tbStbBJ_nQtyOut_BAK
    FROM   dbo.tbStbBJ;
GO
SELECT COUNT(*) AS baris_cadangan, SUM(ISNULL(nQtyOut,0)) AS total_sebelum
FROM   dbo.tbStbBJ_nQtyOut_BAK;
GO

/* ---------------------------------------------------------------------------
   LANGKAH 2 — TAMBAH KOLOM cNoSTB DI TABEL KOREKSI
   Boleh kosong. Koreksi tanpa nomor STB tetap berlaku untuk NO. SC secara
   keseluruhan, seperti perilaku sekarang.
   --------------------------------------------------------------------------- */
IF COL_LENGTH('dbo.tbStokGudangKoreksi', 'cNoSTB') IS NULL
    ALTER TABLE dbo.tbStokGudangKoreksi ADD cNoSTB VARCHAR(20) NULL;
GO
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'IX_Koreksi_Stb')
    CREATE INDEX IX_Koreksi_Stb ON dbo.tbStokGudangKoreksi (cNoSTB)
        INCLUDE (nQtyPc, lVoid);
GO

/* ---------------------------------------------------------------------------
   LANGKAH 3 — PROSEDUR TAMBAH KOREKSI, KINI BISA MENUNJUK BATCH
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
    @cNoSTB      VARCHAR(20)   = NULL      -- boleh kosong
AS
BEGIN
    SET NOCOUNT ON;
    SET @dTanggal  = ISNULL(@dTanggal, CAST(GETDATE() AS DATE));
    SET @cNoSc     = RTRIM(LTRIM(@cNoSc));
    SET @cKelompok = NULLIF(RTRIM(LTRIM(ISNULL(@cKelompok, ''))), '');
    SET @cNoSTB    = NULLIF(RTRIM(LTRIM(ISNULL(@cNoSTB, ''))), '');

    IF @cNoSc = '' OR @cNoSc IS NULL
        THROW 51001, 'Nomor SC wajib diisi.', 1;
    IF @nQtyPc = 0
        THROW 51002, 'Jumlah koreksi tidak boleh nol.', 1;
    IF LTRIM(RTRIM(ISNULL(@cKeterangan,''))) = ''
        THROW 51003, 'Keterangan wajib diisi. Tulis alasan koreksinya supaya bisa ditelusuri.', 1;
    IF LTRIM(RTRIM(ISNULL(@UserId,''))) = ''
        THROW 51004, 'UserId wajib diisi.', 1;
    IF NOT EXISTS (SELECT 1 FROM dbo.tbStokGudangJenisKoreksi WHERE cJenis = @cJenis AND lAktif = 1)
        THROW 51005, 'Jenis koreksi tidak dikenal.', 1;
    IF NOT EXISTS (SELECT 1 FROM dbo.tbStbBJ b WHERE RTRIM(b.cNoSc) = @cNoSc)
       AND NOT EXISTS (SELECT 1 FROM dbo.tbStokGudangExcel e WHERE e.cNoScDb = @cNoSc)
        THROW 51006, 'Nomor SC tidak ditemukan di tbStbBJ maupun di patokan gudang.', 1;

    DECLARE @Cut DATE = (SELECT MAX(dCutOff) FROM dbo.tbStokGudangExcel);
    IF @dTanggal <= @Cut
        THROW 51007, 'Tanggal koreksi harus SETELAH tanggal cut-off patokan.', 1;
    IF @dTanggal > CAST(GETDATE() AS DATE)
        THROW 51008, 'Tanggal koreksi tidak boleh di masa depan.', 1;

    /* Nomor STB, kalau diisi, wajib milik NO. SC yang sama. Ini mencegah
       koreksi menempel ke batch milik order lain. */
    IF @cNoSTB IS NOT NULL
    BEGIN
        IF NOT EXISTS (SELECT 1 FROM dbo.tbStbBJ b
                       WHERE RTRIM(b.cNoSTB) = @cNoSTB AND RTRIM(b.cNoSc) = @cNoSc)
            THROW 51012, 'Nomor STB tersebut bukan milik NO. SC ini. Periksa kembali pilihan batch-nya.', 1;

        /* Pengurangan tidak boleh melebihi isi batch itu sendiri. */
        IF @nQtyPc < 0
        BEGIN
            DECLARE @Isi INT = (SELECT SUM(ISNULL(nQty,0)) FROM dbo.tbStbBJ
                                WHERE RTRIM(cNoSTB) = @cNoSTB);
            DECLARE @Sudah INT = (SELECT ISNULL(SUM(-nQtyPc),0) FROM dbo.tbStokGudangKoreksi
                                  WHERE RTRIM(cNoSTB) = @cNoSTB AND lVoid = 0 AND nQtyPc < 0);
            IF (-@nQtyPc) + @Sudah > @Isi
                THROW 51013, 'Pengurangan melebihi isi batch STB tersebut. Periksa jumlahnya, atau pilih batch lain.', 1;
        END
    END

    IF @cKelompok IS NULL
        SELECT TOP 1 @cKelompok = cKelompok
        FROM   dbo.tbStokSnapTipe WHERE cNoSc = @cNoSc
        ORDER  BY ABS(nStokPc) DESC;
    SET @cKelompok = ISNULL(@cKelompok, 'BOX');

    IF @cKelompok NOT IN ('BOX','PART+LAYER','SHEET','LAIN')
        THROW 51009, 'Kelompok tipe tidak dikenal.', 1;

    INSERT INTO dbo.tbStokGudangKoreksi
          (cNoSc, dTanggal, nQtyPc, cJenis, cKeterangan, cNoBukti, UserId,
           cDivisi, cKelompok, cNoSTB)
    VALUES(@cNoSc, @dTanggal, @nQtyPc, @cJenis, @cKeterangan, @cNoBukti, @UserId,
           @cDivisi, @cKelompok, @cNoSTB);

    DECLARE @Id INT = SCOPE_IDENTITY();

    /* Perbarui bayangan di tbStbBJ untuk batch yang bersangkutan */
    IF @cNoSTB IS NOT NULL EXEC dbo.spSinkronNQtyOut @cNoSTB = @cNoSTB;

    SELECT @Id AS nId, @cNoSc AS cNoSc, @nQtyPc AS nQtyPc, @cKelompok AS cKelompok,
           @cNoSTB AS cNoSTB,
           (SELECT ISNULL(SUM(nQtyPc),0) FROM dbo.tbStokGudangKoreksi
            WHERE cNoSc = @cNoSc AND lVoid = 0) AS total_koreksi_sc;
END
GO

/* ---------------------------------------------------------------------------
   LANGKAH 4 — SINKRONISASI nQtyOut, SATU ARAH DARI TABEL KOREKSI
   Hanya menyentuh baris STB yang punya koreksi. Baris lain tidak disentuh
   sama sekali, jadi catatan produksi tetap utuh.
   --------------------------------------------------------------------------- */
IF OBJECT_ID('dbo.spSinkronNQtyOut') IS NOT NULL DROP PROCEDURE dbo.spSinkronNQtyOut;
GO
CREATE PROCEDURE dbo.spSinkronNQtyOut
    @cNoSTB VARCHAR(20) = NULL     -- kosong = semua batch yang punya koreksi
AS
BEGIN
    SET NOCOUNT ON;

    /* Panjang penanda disesuaikan dengan lebar kolom cOutSTB yang sebenarnya,
       supaya tidak kena error 8152 kalau kolomnya sempit. */
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
    SET    b.nQtyOut = k.keluar, b.cOutSTB = @Tag, b.dTanggalOut = k.tgl
    FROM   dbo.tbStbBJ b
    INNER JOIN kor k ON RTRIM(b.cNoSTB) = k.stb;

    DECLARE @Isi INT = @@ROWCOUNT;

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

/* Pembatalan koreksi ikut memperbarui bayangannya */
IF OBJECT_ID('dbo.spBatalkanKoreksiStok') IS NOT NULL DROP PROCEDURE dbo.spBatalkanKoreksiStok;
GO
CREATE PROCEDURE dbo.spBatalkanKoreksiStok
    @nId INT, @Alasan NVARCHAR(300), @UserId VARCHAR(50)
AS
BEGIN
    SET NOCOUNT ON;
    IF LTRIM(RTRIM(ISNULL(@Alasan,''))) = ''
        THROW 51010, 'Alasan pembatalan wajib diisi.', 1;
    IF NOT EXISTS (SELECT 1 FROM dbo.tbStokGudangKoreksi WHERE nId = @nId)
        THROW 51011, 'Koreksi tidak ditemukan.', 1;
    IF EXISTS (SELECT 1 FROM dbo.tbStokGudangKoreksi WHERE nId = @nId AND lVoid = 1)
        THROW 51014, 'Koreksi ini sudah dibatalkan sebelumnya.', 1;

    DECLARE @Stb VARCHAR(20) = (SELECT RTRIM(cNoSTB) FROM dbo.tbStokGudangKoreksi WHERE nId = @nId);

    UPDATE dbo.tbStokGudangKoreksi
    SET    lVoid = 1, cAlasanVoid = @Alasan, dVoid = GETDATE(), cUserVoid = @UserId
    WHERE  nId = @nId;

    IF @Stb IS NOT NULL EXEC dbo.spSinkronNQtyOut @cNoSTB = @Stb;

    SELECT nId, cNoSc, cNoSTB, nQtyPc, lVoid, cAlasanVoid
    FROM   dbo.tbStokGudangKoreksi WHERE nId = @nId;
END
GO

/* ---------------------------------------------------------------------------
   LANGKAH 5 — SISA STOK PER BATCH STB
   Dipakai dashboard untuk menampilkan pilihan batch, dan berguna untuk FIFO.
   --------------------------------------------------------------------------- */
IF OBJECT_ID('dbo.vwStokPerStb') IS NOT NULL DROP VIEW dbo.vwStokPerStb;
GO
CREATE VIEW dbo.vwStokPerStb AS
SELECT  RTRIM(b.cNoSTB)   AS cNoSTB,
        RTRIM(b.cNoSc)    AS cNoSc,
        CAST(b.dTanggal AS DATE) AS dTglStb,
        b.cNoOp,
        b.cRak,
        b.cShift,
        ISNULL(b.nQty, 0)                          AS nQtyMasuk,
        ISNULL(b.nQtyOut, 0)                       AS nQtyKoreksi,
        ISNULL(b.nQty, 0) - ISNULL(b.nQtyOut, 0)   AS nSisaBatch,
        DATEDIFF(day, b.dTanggal, CAST(GETDATE() AS DATE)) AS nUmur,
        b.cNama, b.cNamabrg
FROM    dbo.tbStbBJ b
WHERE   b.cNoSc IS NOT NULL AND LTRIM(RTRIM(b.cNoSc)) <> '';
GO

/* ---------------------------------------------------------------------------
   LANGKAH 6 — JALANKAN & PERIKSA
   --------------------------------------------------------------------------- */
EXEC dbo.spSinkronNQtyOut;
GO

-- Koreksi yang ada sekarang, beserta batch-nya
SELECT nId, cNoSc, cNoSTB, cKelompok, dTanggal, nQtyPc, cJenis, UserId, lVoid
FROM   dbo.tbStokGudangKoreksi ORDER BY nId;

-- Baris tbStbBJ yang nQtyOut-nya terisi. Harus hanya yang punya koreksi.
SELECT cNoSTB, cNoSc, nQty, nQtyOut, cOutSTB,
       CONVERT(VARCHAR(10), dTanggalOut, 23) AS tgl_out
FROM   dbo.tbStbBJ WHERE ISNULL(nQtyOut,0) <> 0;

-- Contoh batch untuk satu NO. SC
SELECT TOP 20 cNoSTB, dTglStb, cNoOp, cRak, nQtyMasuk, nQtyKoreksi, nSisaBatch, nUmur
FROM   dbo.vwStokPerStb
WHERE  cNoSc = 'SLC/2607/01151' ORDER BY dTglStb, cNoSTB;
GO

/* ---------------------------------------------------------------------------
   MENGEMBALIKAN SEPERTI SEMULA, KALAU DIPERLUKAN
   nQtyOut cuma bayangan, jadi dikosongkan tidak menghilangkan data apa pun.
   --------------------------------------------------------------------------- */
/*
UPDATE dbo.tbStbBJ SET nQtyOut = 0, cOutSTB = NULL, dTanggalOut = NULL
WHERE  RTRIM(ISNULL(cOutSTB,'')) IN ('KOREKSI STOK','KOREKSI','KOR','K');
*/
