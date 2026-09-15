/* =====================================================================
   Pengaturan SHIFT & TOLERANSI di database.
   Tujuan: HR bisa mengubah jam kerja / toleransi tanpa mengedit kode PHP.
   ===================================================================== */
USE dbHR;
GO

/* ---------- 1. Tabel pengaturan shift ---------- */
IF OBJECT_ID('dbo.pengaturan_shift', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.pengaturan_shift (
        shift_ke        TINYINT       NOT NULL,
        nama_shift      NVARCHAR(50)  NOT NULL,
        jam_mulai       TIME          NOT NULL,   -- jam kerja resmi mulai
        jam_selesai     TIME          NOT NULL,
        masuk_dari      TIME          NOT NULL,   -- jendela tap masuk yg dianggap shift ini
        masuk_sampai    TIME          NOT NULL,
        toleransi_menit INT           NOT NULL DEFAULT 0,
        is_aktif        BIT           NOT NULL DEFAULT 1,
        diubah_pada     DATETIME      NOT NULL DEFAULT GETDATE(),
        CONSTRAINT PK_pengaturan_shift PRIMARY KEY (shift_ke)
    );
    PRINT '>> tabel pengaturan_shift dibuat';
END
GO

/* Isi awal - hasil analisis data tap Juni-Juli 2026.
   >>> COCOKKAN dengan menu Shift di Attendance Management <<< */
MERGE dbo.pengaturan_shift AS t
USING (VALUES
    (1, N'Shift 1 (Pagi)',  '08:00', '16:00', '05:00', '11:59', 0),
    (2, N'Shift 2 (Sore)',  '16:00', '00:00', '13:00', '19:59', 0),
    (3, N'Shift 3 (Malam)', '00:00', '08:00', '21:00', '02:59', 0)
) AS s (shift_ke, nama_shift, jam_mulai, jam_selesai, masuk_dari, masuk_sampai, toleransi_menit)
ON t.shift_ke = s.shift_ke
WHEN NOT MATCHED THEN
    INSERT (shift_ke, nama_shift, jam_mulai, jam_selesai, masuk_dari, masuk_sampai, toleransi_menit)
    VALUES (s.shift_ke, s.nama_shift, s.jam_mulai, s.jam_selesai, s.masuk_dari, s.masuk_sampai, s.toleransi_menit);
GO


/* ---------- 2. Pengaturan umum (kunci-nilai) ---------- */
IF OBJECT_ID('dbo.pengaturan_absensi', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.pengaturan_absensi (
        kunci       NVARCHAR(50)   NOT NULL,
        nilai       NVARCHAR(255)  NOT NULL,
        keterangan  NVARCHAR(500)  NULL,
        diubah_pada DATETIME       NOT NULL DEFAULT GETDATE(),
        CONSTRAINT PK_pengaturan_absensi PRIMARY KEY (kunci)
    );
    PRINT '>> tabel pengaturan_absensi dibuat';
END
GO

MERGE dbo.pengaturan_absensi AS t
USING (VALUES
    (N'tgl_shift3', N'tanggal_tap',
     N'Tanggal kerja shift malam: tanggal_tap = ikut tanggal tap masuk (dikonfirmasi HR 24-07-2026). Alternatif: tanggal_shift'),
    (N'mesin_masuk',  N'AXW8190960045', N'Serial mesin gerbang MASUK, pisahkan koma bila lebih dari satu'),
    (N'mesin_keluar', N'AXW8191660095', N'Serial mesin gerbang KELUAR, pisahkan koma bila lebih dari satu'),
    (N'dedup_menit',  N'3',             N'Dua tap sejenis dalam N menit dianggap satu kejadian'),
    (N'max_kerja_jam',N'16',            N'Batas atas jam kerja; lebih dari ini tap dianggap tidak berpasangan')
) AS s (kunci, nilai, keterangan)
ON t.kunci = s.kunci
WHEN NOT MATCHED THEN INSERT (kunci, nilai, keterangan) VALUES (s.kunci, s.nilai, s.keterangan);
GO


/* ---------- 3. Lihat isinya ---------- */
SELECT * FROM dbo.pengaturan_shift ORDER BY shift_ke;
SELECT * FROM dbo.pengaturan_absensi ORDER BY kunci;
GO


/* =====================================================================
   CONTOH mengubah pengaturan nanti (tanpa menyentuh kode PHP):

   -- toleransi 10 menit untuk semua shift
   UPDATE dbo.pengaturan_shift SET toleransi_menit = 10, diubah_pada = GETDATE();

   -- toleransi hanya untuk shift 1
   UPDATE dbo.pengaturan_shift SET toleransi_menit = 15 WHERE shift_ke = 1;

   -- jam masuk shift 1 diubah jadi 07:30
   UPDATE dbo.pengaturan_shift SET jam_mulai = '07:30' WHERE shift_ke = 1;

   -- tambah mesin baru di gerbang masuk
   UPDATE dbo.pengaturan_absensi
   SET nilai = N'AXW8190960045,SERIAL_BARU' WHERE kunci = N'mesin_masuk';
   ===================================================================== */
