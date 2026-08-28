/* =====================================================================
   Master KLASIFIKASI pengunduran diri - bisa ditambah/edit/hapus lewat web.
   Menggantikan daftar hardcoded di PHP.
   ===================================================================== */
USE dbHR;
GO

IF OBJECT_ID('dbo.klasifikasi_resign','U') IS NULL
BEGIN
    CREATE TABLE dbo.klasifikasi_resign (
        id_klasifikasi INT IDENTITY(1,1) NOT NULL,
        kode           NVARCHAR(40)  NOT NULL,      -- VOLUNTARY, dst
        label          NVARCHAR(120) NOT NULL,      -- teks yang tampil
        keterangan     NVARCHAR(300) NULL,
        urutan         INT           NOT NULL DEFAULT 99,
        is_aktif       BIT           NOT NULL DEFAULT 1,
        dibuat_pada    DATETIME      NOT NULL DEFAULT GETDATE(),
        CONSTRAINT PK_klasifikasi_resign PRIMARY KEY (id_klasifikasi),
        CONSTRAINT UQ_klasifikasi_kode UNIQUE (kode)
    );
    PRINT '>> tabel klasifikasi_resign dibuat';
END ELSE PRINT '>> klasifikasi_resign sudah ada';
GO

/* isi default (hanya kalau kosong) */
IF NOT EXISTS (SELECT 1 FROM dbo.klasifikasi_resign)
BEGIN
    INSERT INTO dbo.klasifikasi_resign (kode, label, keterangan, urutan) VALUES
    ('VOLUNTARY',       'Voluntary',        'Mengundurkan diri atas kemauan sendiri', 1),
    ('INVOLUNTARY',     'Involuntary',      'Diberhentikan perusahaan',               2),
    ('RETIREMENT',      'Retirement',       'Pensiun',                                3),
    ('CONTRACT_END',    'Contract End',     'Kontrak habis / tidak diperpanjang',     4),
    ('MUTUAL_AGREEMENT','Mutual Agreement', 'Kesepakatan bersama',                    5),
    ('ABSCOND',         'Abscond',          'Mangkir / menghilang tanpa kabar',       6),
    ('LAINNYA',         'Lainnya',          'Klasifikasi lain',                      99);
    PRINT '>> 7 klasifikasi default diisi';
END
GO

SELECT id_klasifikasi, kode, label, urutan, is_aktif FROM dbo.klasifikasi_resign ORDER BY urutan;
GO
