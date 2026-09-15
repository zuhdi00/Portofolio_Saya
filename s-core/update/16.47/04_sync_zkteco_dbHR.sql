/* =====================================================================
   Tabel pendukung SINKRONISASI ZKTeco -> dbHR
   Jalankan di SSMS, database dbHR. Perhatikan pemisah GO.
   ===================================================================== */
USE dbHR;
GO

/* ---------- 0. Kolom penghubung di tabel pegawai ---------- */
-- Jalankan hanya jika belum ada (cek dulu dgn query di bawah)
-- SELECT name FROM sys.columns WHERE object_id=OBJECT_ID('dbo.pegawai');

ALTER TABLE dbo.pegawai ADD
    nik           NVARCHAR(20) NULL,   -- Nomor Induk Karyawan resmi HR
    zkteco_userid INT          NULL,   -- USERINFO.USERID  (PK internal ZKTeco)
    zkteco_acno   NVARCHAR(24) NULL;   -- USERINFO.Badgenumber (= AC-No. di layar)
GO

CREATE UNIQUE INDEX UX_pegawai_nik
    ON dbo.pegawai(nik) WHERE nik IS NOT NULL;
GO
CREATE UNIQUE INDEX UX_pegawai_zkuserid
    ON dbo.pegawai(zkteco_userid) WHERE zkteco_userid IS NOT NULL;
GO


/* ---------- 1. STAGING: tap mentah dari ZKTeco ----------
   Disimpan apa adanya. UNIQUE (zk_userid, checktime) membuat impor
   bisa diulang berkali-kali tanpa data ganda (idempoten).            */
CREATE TABLE dbo.zkteco_checkinout (
    id          BIGINT IDENTITY(1,1) NOT NULL,
    zk_userid   INT           NOT NULL,
    checktime   DATETIME      NOT NULL,
    checktype   NVARCHAR(1)   NULL,     -- 'I' / 'O' (tidak selalu akurat)
    verifycode  INT           NULL,     -- 15=wajah, 1=sidik jari, 0=mesin lama
    sn          NVARCHAR(20)  NULL,     -- serial mesin
    diproses    BIT           NOT NULL DEFAULT 0,
    imported_at DATETIME      NOT NULL DEFAULT GETDATE(),
    CONSTRAINT PK_zkteco_checkinout PRIMARY KEY (id),
    CONSTRAINT UQ_zkteco_tap UNIQUE (zk_userid, checktime)
);
GO
CREATE INDEX IX_zk_userid_time ON dbo.zkteco_checkinout(zk_userid, checktime);
CREATE INDEX IX_zk_diproses    ON dbo.zkteco_checkinout(diproses) WHERE diproses = 0;
GO


/* ---------- 2. Status sinkronisasi ---------- */
CREATE TABLE dbo.sync_zkteco_state (
    id             INT IDENTITY(1,1) NOT NULL,
    sumber         NVARCHAR(50)  NOT NULL,   -- 'ATT2000'
    last_checktime DATETIME      NULL,       -- tap terakhir yang sudah diimpor
    last_run       DATETIME      NULL,
    jml_baru       INT           NULL,
    status         NVARCHAR(30)  NULL,       -- SUKSES / GAGAL
    pesan          NVARCHAR(1000) NULL,
    CONSTRAINT PK_sync_zkteco_state PRIMARY KEY (id)
);
GO
INSERT INTO dbo.sync_zkteco_state (sumber, last_checktime) VALUES ('ATT2000', '2000-01-01');
GO


/* ---------- 3. Antrian koreksi / approval atasan ----------
   Dipakai saat tap tidak lengkap (lupa tap pulang, dsb).             */
CREATE TABLE dbo.absensi_koreksi (
    id_koreksi        INT IDENTITY(1,1) NOT NULL,
    pegawai_id        INT           NOT NULL,
    tanggal           DATE          NOT NULL,
    jenis             NVARCHAR(30)  NOT NULL,  -- LUPA_TAP_PULANG / LUPA_TAP_MASUK / TAP_GANJIL
    jam_masuk_asli    TIME          NULL,
    jam_keluar_asli   TIME          NULL,
    jam_masuk_usulan  TIME          NULL,      -- diisi atasan/HR
    jam_keluar_usulan TIME          NULL,
    status_approval   NVARCHAR(20)  NOT NULL DEFAULT 'PENDING', -- PENDING/DISETUJUI/DITOLAK
    diajukan_pada     DATETIME      NOT NULL DEFAULT GETDATE(),
    approver_id       INT           NULL,      -- pegawai_id atasan
    approved_pada     DATETIME      NULL,
    catatan           NVARCHAR(500) NULL,
    CONSTRAINT PK_absensi_koreksi PRIMARY KEY (id_koreksi),
    CONSTRAINT FK_koreksi_pegawai FOREIGN KEY (pegawai_id) REFERENCES dbo.pegawai(id_peg)
);
GO
CREATE INDEX IX_koreksi_pending ON dbo.absensi_koreksi(status_approval, tanggal);
GO


/* ---------- 4. Kolom tambahan di absensi ---------- */
ALTER TABLE dbo.absensi ADD
    metode      NVARCHAR(30) NULL,   -- 'wajah' / 'sidik_jari' / 'manual'
    sn_mesin    NVARCHAR(20) NULL,
    shift_ke    TINYINT      NULL,   -- 1 / 2 / 3
    jml_tap     TINYINT      NULL,   -- berapa kali tap hari itu
    perlu_koreksi BIT        NOT NULL DEFAULT 0,
    sumber      NVARCHAR(20) NULL DEFAULT 'ZKTECO';
GO

-- cegah duplikat 1 pegawai 1 tanggal
CREATE UNIQUE INDEX UX_absensi_peg_tgl ON dbo.absensi(pegawai_id, tanggal);
GO
