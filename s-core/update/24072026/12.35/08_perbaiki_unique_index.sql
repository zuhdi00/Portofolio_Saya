/* =====================================================================
   Memperbaiki UNIQUE INDEX yang menghalangi banyak baris kosong.
   MASALAH: unique index biasa hanya mengizinkan SATU nilai NULL.
   SOLUSI : filtered index -> aturan "tidak boleh kembar" tetap berlaku
            untuk data yang terisi, tapi baris kosong bebas.
   ===================================================================== */
USE dbHR;
GO

/* ---------- 1. Cari SEMUA unique index yang berpotensi bermasalah ----------
   (unique, tidak difilter, pada kolom yang boleh NULL)              */
SELECT
    t.name  AS tabel,
    i.name  AS nama_index,
    c.name  AS kolom,
    i.has_filter AS sudah_difilter,
    CASE WHEN i.has_filter = 0 AND c.is_nullable = 1
         THEN 'PERLU DIPERBAIKI' ELSE 'aman' END AS status
FROM sys.indexes i
JOIN sys.tables        t  ON t.object_id  = i.object_id
JOIN sys.index_columns ic ON ic.object_id = i.object_id AND ic.index_id = i.index_id
JOIN sys.columns       c  ON c.object_id  = i.object_id AND c.column_id = ic.column_id
WHERE i.is_unique = 1
  AND i.is_primary_key = 0
  AND t.name IN ('pegawai','department','unit_kerja','jabatan')
ORDER BY status DESC, t.name, i.name;
GO


/* ---------- 2. Perbaiki index no_ktp ---------- */
IF EXISTS (SELECT 1 FROM sys.indexes
           WHERE name = 'pegawai_no_ktp_unique' AND object_id = OBJECT_ID('dbo.pegawai'))
BEGIN
    DROP INDEX pegawai_no_ktp_unique ON dbo.pegawai;
    PRINT '>> index lama pegawai_no_ktp_unique dihapus';
END
GO

IF NOT EXISTS (SELECT 1 FROM sys.indexes
               WHERE name = 'UX_pegawai_no_ktp' AND object_id = OBJECT_ID('dbo.pegawai'))
BEGIN
    CREATE UNIQUE INDEX UX_pegawai_no_ktp
        ON dbo.pegawai(no_ktp) WHERE no_ktp IS NOT NULL;
    PRINT '>> index baru UX_pegawai_no_ktp dibuat (filtered)';
END
GO


/* ---------- 3. Bersihkan pegawai hasil percobaan yang gagal ----------
   1 baris sempat masuk sebelum error. Dihapus supaya mulai bersih.
   (department & unit_kerja BIARKAN - itu sudah benar)               */
DELETE FROM dbo.pegawai WHERE sumber = 'ZKTECO';
GO


/* ---------- 4. Verifikasi ---------- */
SELECT 'pegawai'    AS tabel, COUNT(*) AS jml FROM dbo.pegawai
UNION ALL SELECT 'department',  COUNT(*) FROM dbo.department
UNION ALL SELECT 'unit_kerja',  COUNT(*) FROM dbo.unit_kerja;
GO
-- Diharapkan: pegawai 0, department 38, unit_kerja 30


/* =====================================================================
   CATATAN - kalau query nomor 1 menampilkan index lain berstatus
   'PERLU DIPERBAIKI' (misal pada email_peg, npwp, no_bpjs_tk),
   perbaiki dengan pola yang sama:

       DROP INDEX <nama_index_lama> ON dbo.pegawai;
       CREATE UNIQUE INDEX UX_pegawai_<kolom>
           ON dbo.pegawai(<kolom>) WHERE <kolom> IS NOT NULL;
   ===================================================================== */
