/* ============================================================================
   PT SUPRACOR SEJAHTERA — KOREKSI STOK & KETERANGAN
   Dibuat : 07 Agustus 2026

   MASALAH YANG DIJAWAB
     Kalau stok fisik berbeda dengan sistem, sekarang belum ada cara
     membetulkannya selain menunggu ganti patokan bulan depan. Terlalu lama.
     Begitu juga keterangan (TUNGGU JADWAL, LEBIH PRODUKSI, dan seterusnya)
     yang saat ini hanya ikut dari file Excel dan tidak bisa diubah dari web.

   PRINSIP YANG DIPAKAI

   1. tbStbBJ TIDAK PERNAH DIUBAH
      Itu catatan produksi, bukan catatan stok. Kalau STB-nya memang salah
      input, perbaikannya di modul produksi supaya laporan produksi ikut benar.
      Koreksi stok gudang masuk ke tabel sendiri.

   2. KOREKSI BERTUMPUK, TIDAK MENIMPA
      Tiap koreksi jadi satu baris baru berisi tanggal, jumlah, sebab, catatan,
      dan siapa yang input. Salah input dibatalkan lewat kolom lVoid, tidak
      dihapus. Jadi riwayatnya selalu bisa ditelusuri.

   3. KOREKSI PUNYA TANGGAL
      Karena berdasar tanggal, stok posisi tanggal berapa pun tetap bisa
      dihitung ulang dengan benar.

   RUMUS STOK SETELAH FILE INI
       stok = saldo awal bulan
            + STB          setelah cut-off
            - surat jalan  setelah cut-off
            + retur        setelah cut-off
            + KOREKSI      setelah cut-off      <- baru

   File ini membuat tabel dan prosedur baru, lalu memperbarui dua prosedur
   perhitungan. Tabel transaksi asli tidak disentuh.
   ============================================================================ */

USE dbSopanusa;
GO
SET NOCOUNT ON;
GO

/* ============================================================================
   BAGIAN 1 — TABEL KOREKSI STOK
   ============================================================================ */
IF OBJECT_ID('dbo.tbStokGudangKoreksi') IS NULL
BEGIN
    CREATE TABLE dbo.tbStokGudangKoreksi (
        nId         INT IDENTITY(1,1) PRIMARY KEY,
        cNoSc       VARCHAR(30)   NOT NULL,
        dTanggal    DATE          NOT NULL,          -- tanggal koreksi berlaku
        nQtyPc      INT           NOT NULL,          -- boleh minus, contoh -500
        cJenis      VARCHAR(30)   NOT NULL,          -- lihat daftar di bawah
        cKeterangan NVARCHAR(300) NOT NULL,          -- wajib diisi, alasannya
        cNoBukti    VARCHAR(50)   NULL,              -- no berita acara / opname
        lVoid       BIT           NOT NULL DEFAULT 0,
        cAlasanVoid NVARCHAR(300) NULL,
        dVoid       DATETIME      NULL,
        cUserVoid   VARCHAR(50)   NULL,
        UserId      VARCHAR(50)   NOT NULL,
        dInput      DATETIME      NOT NULL DEFAULT GETDATE(),
        cDivisi     VARCHAR(30)   NULL
    );
    CREATE INDEX IX_Koreksi_Sc  ON dbo.tbStokGudangKoreksi (cNoSc) INCLUDE (dTanggal, nQtyPc, lVoid);
    CREATE INDEX IX_Koreksi_Tgl ON dbo.tbStokGudangKoreksi (dTanggal) INCLUDE (cNoSc, nQtyPc, lVoid);
END
GO

/* Daftar jenis koreksi yang boleh dipakai.
   Dibuat sebagai tabel supaya bisa ditambah tanpa mengubah program. */
IF OBJECT_ID('dbo.tbStokGudangJenisKoreksi') IS NULL
BEGIN
    CREATE TABLE dbo.tbStokGudangJenisKoreksi (
        cJenis      VARCHAR(30)   NOT NULL PRIMARY KEY,
        cPenjelasan NVARCHAR(200) NOT NULL,
        lAktif      BIT           NOT NULL DEFAULT 1,
        nUrut       INT           NOT NULL DEFAULT 0
    );
    INSERT INTO dbo.tbStokGudangJenisKoreksi (cJenis, cPenjelasan, nUrut) VALUES
    ('SELISIH OPNAME',   N'Hasil hitung fisik berbeda dengan sistem, tanpa sebab yang jelas', 1),
    ('BARANG RUSAK',     N'Barang tidak layak kirim, dikeluarkan dari stok', 2),
    ('SALAH INPUT STB',  N'Setoran barang jadi tercatat lebih atau kurang di sistem', 3),
    ('SALAH INPUT SJ',   N'Surat jalan tercatat lebih atau kurang di sistem', 4),
    ('KIRIM TANPA SJ',   N'Barang sudah keluar tapi surat jalannya belum dibuat', 5),
    ('BELUM TERCATAT',   N'Barang ada di gudang tapi belum pernah masuk sistem', 6),
    ('PINDAH NO. SC',    N'Stok dipindahkan ke atau dari nomor SC lain', 7),
    ('LAIN-LAIN',        N'Sebab lain, wajib dijelaskan di kolom keterangan', 99);
END
GO

/* ============================================================================
   BAGIAN 2 — TABEL KETERANGAN STATUS PER NO. SC
   Menjawab pertanyaan "kenapa barang ini masih di gudang".
   Satu baris aktif per SC, perubahannya dicatat di tabel riwayat.
   ============================================================================ */
IF OBJECT_ID('dbo.tbStokGudangKet') IS NULL
BEGIN
    CREATE TABLE dbo.tbStokGudangKet (
        cNoSc       VARCHAR(30)   NOT NULL PRIMARY KEY,
        cKeterangan NVARCHAR(50)  NOT NULL,
        cCatatan    NVARCHAR(300) NULL,
        dTarget     DATE          NULL,              -- perkiraan kapan dikirim
        UserId      VARCHAR(50)   NOT NULL,
        dUpdate     DATETIME      NOT NULL DEFAULT GETDATE()
    );
END
GO

IF OBJECT_ID('dbo.tbStokGudangKetLog') IS NULL
BEGIN
    CREATE TABLE dbo.tbStokGudangKetLog (
        nId          INT IDENTITY(1,1) PRIMARY KEY,
        cNoSc        VARCHAR(30)   NOT NULL,
        cKetLama     NVARCHAR(50)  NULL,
        cKetBaru     NVARCHAR(50)  NOT NULL,
        cCatatan     NVARCHAR(300) NULL,
        UserId       VARCHAR(50)   NOT NULL,
        dUbah        DATETIME      NOT NULL DEFAULT GETDATE()
    );
    CREATE INDEX IX_KetLog_Sc ON dbo.tbStokGudangKetLog (cNoSc, dUbah);
END
GO

IF OBJECT_ID('dbo.tbStokGudangDaftarKet') IS NULL
BEGIN
    CREATE TABLE dbo.tbStokGudangDaftarKet (
        cKeterangan NVARCHAR(50) NOT NULL PRIMARY KEY,
        lAktif      BIT NOT NULL DEFAULT 1,
        nUrut       INT NOT NULL DEFAULT 0
    );
    INSERT INTO dbo.tbStokGudangDaftarKet (cKeterangan, nUrut) VALUES
    (N'TUNGGU JADWAL', 1), (N'LEBIH PRODUKSI', 2), (N'SISA CONTAINER', 3),
    (N'KURANG KIRIM', 4),  (N'AMBIL SENDIRI', 5),  (N'TUNGGU CONTAINER', 6),
    (N'TIDAK SET', 7),     (N'BELUM KETEMU', 8),   (N'REVISI', 9);
END
GO

/* ============================================================================
   BAGIAN 3 — PROSEDUR INPUT
   ============================================================================ */

/* --- 3a. Tambah koreksi stok --- */
IF OBJECT_ID('dbo.spTambahKoreksiStok') IS NOT NULL DROP PROCEDURE dbo.spTambahKoreksiStok;
GO
CREATE PROCEDURE dbo.spTambahKoreksiStok
    @cNoSc       VARCHAR(30),
    @nQtyPc      INT,
    @cJenis      VARCHAR(30),
    @cKeterangan NVARCHAR(300),
    @UserId      VARCHAR(50),
    @dTanggal    DATE          = NULL,     -- kosong = hari ini
    @cNoBukti    VARCHAR(50)   = NULL,
    @cDivisi     VARCHAR(30)   = NULL
AS
BEGIN
    SET NOCOUNT ON;
    SET @dTanggal = ISNULL(@dTanggal, CAST(GETDATE() AS DATE));
    SET @cNoSc    = RTRIM(LTRIM(@cNoSc));

    IF @cNoSc = '' OR @cNoSc IS NULL
        THROW 51001, 'Nomor SC wajib diisi.', 1;

    IF @nQtyPc = 0
        THROW 51002, 'Jumlah koreksi tidak boleh nol.', 1;

    IF LTRIM(RTRIM(ISNULL(@cKeterangan,''))) = ''
        THROW 51003, 'Keterangan wajib diisi. Tulis alasan koreksinya supaya bisa ditelusuri.', 1;

    IF LTRIM(RTRIM(ISNULL(@UserId,''))) = ''
        THROW 51004, 'UserId wajib diisi.', 1;

    IF NOT EXISTS (SELECT 1 FROM dbo.tbStokGudangJenisKoreksi
                   WHERE cJenis = @cJenis AND lAktif = 1)
        THROW 51005, 'Jenis koreksi tidak dikenal. Lihat tabel tbStokGudangJenisKoreksi.', 1;

    IF NOT EXISTS (SELECT 1 FROM dbo.tbStbBJ b WHERE RTRIM(b.cNoSc) = @cNoSc)
       AND NOT EXISTS (SELECT 1 FROM dbo.tbStokGudangExcel e WHERE e.cNoScDb = @cNoSc)
        THROW 51006, 'Nomor SC tidak ditemukan di tbStbBJ maupun di patokan gudang.', 1;

    DECLARE @Cut DATE = (SELECT MAX(dCutOff) FROM dbo.tbStokGudangExcel);
    IF @dTanggal <= @Cut
        THROW 51007, 'Tanggal koreksi harus SETELAH tanggal cut-off patokan. Koreksi sebelum cut-off tidak berpengaruh, karena saldo awal sudah mencakupnya.', 1;

    IF @dTanggal > CAST(GETDATE() AS DATE)
        THROW 51008, 'Tanggal koreksi tidak boleh di masa depan.', 1;

    INSERT INTO dbo.tbStokGudangKoreksi
          (cNoSc, dTanggal, nQtyPc, cJenis, cKeterangan, cNoBukti, UserId, cDivisi)
    VALUES(@cNoSc, @dTanggal, @nQtyPc, @cJenis, @cKeterangan, @cNoBukti, @UserId, @cDivisi);

    SELECT SCOPE_IDENTITY() AS nId, @cNoSc AS cNoSc, @nQtyPc AS nQtyPc,
           (SELECT ISNULL(SUM(nQtyPc),0) FROM dbo.tbStokGudangKoreksi
            WHERE cNoSc = @cNoSc AND lVoid = 0) AS total_koreksi_sc;
END
GO

/* --- 3b. Batalkan koreksi (tidak menghapus) --- */
IF OBJECT_ID('dbo.spBatalkanKoreksiStok') IS NOT NULL DROP PROCEDURE dbo.spBatalkanKoreksiStok;
GO
CREATE PROCEDURE dbo.spBatalkanKoreksiStok
    @nId    INT,
    @Alasan NVARCHAR(300),
    @UserId VARCHAR(50)
AS
BEGIN
    SET NOCOUNT ON;
    IF LTRIM(RTRIM(ISNULL(@Alasan,''))) = ''
        THROW 51010, 'Alasan pembatalan wajib diisi.', 1;
    IF NOT EXISTS (SELECT 1 FROM dbo.tbStokGudangKoreksi WHERE nId = @nId)
        THROW 51011, 'Koreksi tidak ditemukan.', 1;
    IF EXISTS (SELECT 1 FROM dbo.tbStokGudangKoreksi WHERE nId = @nId AND lVoid = 1)
        THROW 51012, 'Koreksi ini sudah dibatalkan sebelumnya.', 1;

    UPDATE dbo.tbStokGudangKoreksi
    SET    lVoid = 1, cAlasanVoid = @Alasan, dVoid = GETDATE(), cUserVoid = @UserId
    WHERE  nId = @nId;

    SELECT nId, cNoSc, nQtyPc, lVoid, cAlasanVoid
    FROM   dbo.tbStokGudangKoreksi WHERE nId = @nId;
END
GO

/* --- 3c. Ubah keterangan status --- */
IF OBJECT_ID('dbo.spSetKeteranganStok') IS NOT NULL DROP PROCEDURE dbo.spSetKeteranganStok;
GO
CREATE PROCEDURE dbo.spSetKeteranganStok
    @cNoSc       VARCHAR(30),
    @cKeterangan NVARCHAR(50),
    @UserId      VARCHAR(50),
    @cCatatan    NVARCHAR(300) = NULL,
    @dTarget     DATE          = NULL
AS
BEGIN
    SET NOCOUNT ON;
    SET @cNoSc = RTRIM(LTRIM(@cNoSc));

    IF NOT EXISTS (SELECT 1 FROM dbo.tbStokGudangDaftarKet
                   WHERE cKeterangan = @cKeterangan AND lAktif = 1)
        THROW 51020, 'Keterangan tidak dikenal. Lihat tabel tbStokGudangDaftarKet.', 1;

    DECLARE @Lama NVARCHAR(50) =
        (SELECT cKeterangan FROM dbo.tbStokGudangKet WHERE cNoSc = @cNoSc);

    IF @Lama IS NULL
        INSERT INTO dbo.tbStokGudangKet (cNoSc, cKeterangan, cCatatan, dTarget, UserId)
        VALUES (@cNoSc, @cKeterangan, @cCatatan, @dTarget, @UserId);
    ELSE
        UPDATE dbo.tbStokGudangKet
        SET    cKeterangan = @cKeterangan, cCatatan = @cCatatan, dTarget = @dTarget,
               UserId = @UserId, dUpdate = GETDATE()
        WHERE  cNoSc = @cNoSc;

    INSERT INTO dbo.tbStokGudangKetLog (cNoSc, cKetLama, cKetBaru, cCatatan, UserId)
    VALUES (@cNoSc, @Lama, @cKeterangan, @cCatatan, @UserId);

    SELECT @cNoSc AS cNoSc, @Lama AS keterangan_lama, @cKeterangan AS keterangan_baru;
END
GO

/* ============================================================================
   BAGIAN 4 — MASUKKAN KOREKSI KE PERHITUNGAN STOK
   ============================================================================ */

/* --- 4a. spStokPerTanggal --- */
IF OBJECT_ID('dbo.spStokPerTanggal') IS NOT NULL DROP PROCEDURE dbo.spStokPerTanggal;
GO
CREATE PROCEDURE dbo.spStokPerTanggal
    @Posisi DATE,
    @Rinci  BIT = 0
AS
BEGIN
    SET NOCOUNT ON;
    SET TRANSACTION ISOLATION LEVEL READ UNCOMMITTED;

    DECLARE @Cut DATE = (SELECT MAX(dCutOff) FROM dbo.tbStokGudangExcel);
    DECLARE @Bts DATETIME = DATEADD(day, 1, CAST(@Posisi AS DATETIME));

    CREATE TABLE #a (sc VARCHAR(30) PRIMARY KEY, pc INT NOT NULL DEFAULT 0);

    INSERT INTO #a (sc, pc)
    SELECT RTRIM(cNoScDb), SUM(nStokAkhirPc)
    FROM   dbo.tbStokGudangExcel WHERE cNoScDb IS NOT NULL
    GROUP  BY RTRIM(cNoScDb);

    INSERT INTO #a (sc, pc)
    SELECT DISTINCT sc, 0 FROM (
        SELECT RTRIM(cNoSc) AS sc FROM dbo.tbStbBJ
        WHERE  dTanggal > @Cut AND dTanggal < @Bts
          AND  cNoSc IS NOT NULL AND LTRIM(RTRIM(cNoSc)) <> ''
        UNION
        SELECT RTRIM(COALESCE(d.cNoScDtl, s.cNoSC))
        FROM   dbo.tbSRJ s INNER JOIN dbo.tbSRJDtl d ON d.cNoSRJ = s.cNoSRJ
        WHERE  s.dTanggal > @Cut AND s.dTanggal < @Bts
          AND  COALESCE(d.cNoScDtl, s.cNoSC) IS NOT NULL
        UNION
        SELECT RTRIM(cNoSc) FROM dbo.tbStokGudangKoreksi
        WHERE  lVoid = 0 AND dTanggal > @Cut AND dTanggal < @Bts
    ) z
    WHERE NOT EXISTS (SELECT 1 FROM #a a WHERE a.sc = z.sc);

    UPDATE a SET a.pc = a.pc + x.q FROM #a a
    INNER JOIN (SELECT RTRIM(cNoSc) AS sc, SUM(ISNULL(nQty,0)) AS q
                FROM   dbo.tbStbBJ WHERE dTanggal > @Cut AND dTanggal < @Bts
                GROUP  BY RTRIM(cNoSc)) x ON x.sc = a.sc;

    UPDATE a SET a.pc = a.pc - x.q FROM #a a
    INNER JOIN (SELECT RTRIM(COALESCE(d.cNoScDtl, s.cNoSC)) AS sc, SUM(ISNULL(d.nQty,0)) AS q
                FROM   dbo.tbSRJ s INNER JOIN dbo.tbSRJDtl d ON d.cNoSRJ = s.cNoSRJ
                WHERE  s.dTanggal > @Cut AND s.dTanggal < @Bts
                GROUP  BY RTRIM(COALESCE(d.cNoScDtl, s.cNoSC))) x ON x.sc = a.sc;

    UPDATE a SET a.pc = a.pc + x.q FROM #a a
    INNER JOIN (SELECT RTRIM(cNoSc) AS sc, SUM(ISNULL(nQty,0)) AS q
                FROM   dbo.vwReturnSrj WHERE dTgl > @Cut AND dTgl < @Bts
                  AND  cNoSc IS NOT NULL AND LTRIM(RTRIM(cNoSc)) <> ''
                GROUP  BY RTRIM(cNoSc)) x ON x.sc = a.sc;

    -- KOREKSI MANUAL
    UPDATE a SET a.pc = a.pc + x.q FROM #a a
    INNER JOIN (SELECT RTRIM(cNoSc) AS sc, SUM(nQtyPc) AS q
                FROM   dbo.tbStokGudangKoreksi
                WHERE  lVoid = 0 AND dTanggal > @Cut AND dTanggal < @Bts
                GROUP  BY RTRIM(cNoSc)) x ON x.sc = a.sc;

    IF @Rinci = 1
        SELECT a.sc AS cNoSc, a.pc AS nStokPc,
               LEFT(ISNULL(e.cNama, ISNULL(s.cNama, '')), 255)       AS cNama,
               LEFT(ISNULL(e.cNamabrg, ISNULL(s.cNamabrg, '')), 500) AS cNamabrg
        FROM   #a a
        OUTER APPLY (SELECT TOP 1 x.cNama, x.cNamabrg FROM dbo.tbStokGudangExcel x
                     WHERE x.cNoScDb = a.sc ORDER BY x.nStokAkhirPc DESC) e
        OUTER APPLY (SELECT TOP 1 y.cNama, y.cNamabrg FROM dbo.tbStokGudangSnap y
                     WHERE y.cNoSc = a.sc) s
        WHERE  a.pc <> 0 ORDER BY a.pc DESC;
    ELSE
        SELECT @Posisi AS posisi, COUNT(*) AS jml_op, SUM(pc) AS total_pc,
               SUM(CASE WHEN pc > 0 THEN pc ELSE 0 END) AS pc_positif,
               SUM(CASE WHEN pc < 0 THEN 1 ELSE 0 END)  AS jml_minus
        FROM   #a WHERE pc <> 0;

    DROP TABLE #a;
END
GO

/* --- 4b. Tambahkan koreksi ke spRefreshStokGudang.
       Sisipkan blok berikut di dalam prosedur, TEPAT SETELAH langkah retur
       dan SEBELUM baris DELETE FROM #agg WHERE nStok = 0:

        UPDATE a SET a.nStok = a.nStok + x.q
        FROM   #agg a
        INNER JOIN (SELECT RTRIM(cNoSc) AS sc, SUM(nQtyPc) AS q
                    FROM   dbo.tbStokGudangKoreksi
                    WHERE  lVoid = 0 AND dTanggal > @CutOff
                    GROUP  BY RTRIM(cNoSc)) x ON x.sc = a.cNoSc;

       Dan untuk keterangan, ganti pengambilan cKeterangan supaya keterangan
       manual menang atas keterangan dari file Excel:

        LEFT(ISNULL(k.cKeterangan, e.cKeterangan), 255) AS cKeterangan

       dengan tambahan OUTER APPLY:

        OUTER APPLY (SELECT TOP 1 z.cKeterangan FROM dbo.tbStokGudangKet z
                     WHERE z.cNoSc = a.cNoSc) k
   --------------------------------------------------------------------------- */

/* ============================================================================
   BAGIAN 5 — CONTOH PEMAKAIAN & PEMANTAUAN
   ============================================================================ */

/* Contoh menambah koreksi:
EXEC dbo.spTambahKoreksiStok
     @cNoSc       = 'SLC/2607/00928',
     @nQtyPc      = 5180,
     @cJenis      = 'KIRIM TANPA SJ',
     @cKeterangan = N'Barang keluar 2 Agustus, surat jalan baru dibuat 5 Agustus sehingga terpotong dua kali. Dicek bersama gudang.',
     @cNoBukti    = 'BA-GD-2608-001',
     @UserId      = 'zuhdi',
     @cDivisi     = 'IT';
*/

/* Contoh membatalkan koreksi:
EXEC dbo.spBatalkanKoreksiStok @nId = 1, @Alasan = N'Salah nomor SC', @UserId = 'zuhdi';
*/

/* Contoh mengubah keterangan:
EXEC dbo.spSetKeteranganStok
     @cNoSc = 'SLC/2607/00932', @cKeterangan = N'TUNGGU JADWAL',
     @cCatatan = N'Customer minta tahan sampai akhir bulan', @dTarget = '2026-08-25',
     @UserId = 'zuhdi';
*/

-- Rekap koreksi yang berlaku
SELECT k.cJenis, COUNT(*) AS jml, SUM(k.nQtyPc) AS total_pc
FROM   dbo.tbStokGudangKoreksi k WHERE k.lVoid = 0
GROUP  BY k.cJenis ORDER BY ABS(SUM(k.nQtyPc)) DESC;

-- Riwayat lengkap satu NO. SC
-- SELECT * FROM dbo.tbStokGudangKoreksi WHERE cNoSc = 'SLC/2607/00928' ORDER BY nId;

-- Koreksi terbesar bulan ini, untuk ditinjau atasan
SELECT TOP 30 nId, cNoSc, dTanggal, nQtyPc, cJenis, cKeterangan, UserId, dInput
FROM   dbo.tbStokGudangKoreksi
WHERE  lVoid = 0 AND dTanggal >= DATEADD(day, -30, CAST(GETDATE() AS DATE))
ORDER  BY ABS(nQtyPc) DESC;
GO
