/* =====================================================================
   Penanda ASAL DATA - supaya hasil seeding ZKTeco bisa dibedakan
   dari data yang diinput manual, dan bisa dirapikan/dibatalkan nanti.
   Jalankan di SSMS, database dbHR.
   ===================================================================== */
USE dbHR;
GO

/* ---------- department ---------- */
IF COL_LENGTH('dbo.department', 'sumber') IS NULL
BEGIN
    ALTER TABLE dbo.department ADD sumber NVARCHAR(20) NULL;
    PRINT '>> kolom department.sumber dibuat';
END
GO
-- data yang sudah ada sekarang = buatan manual
UPDATE dbo.department SET sumber = 'MANUAL' WHERE sumber IS NULL;
GO

/* ---------- unit_kerja ---------- */
IF COL_LENGTH('dbo.unit_kerja', 'sumber') IS NULL
BEGIN
    ALTER TABLE dbo.unit_kerja ADD sumber NVARCHAR(20) NULL;
    PRINT '>> kolom unit_kerja.sumber dibuat';
END
GO
UPDATE dbo.unit_kerja SET sumber = 'MANUAL' WHERE sumber IS NULL;
GO

/* ---------- pegawai ---------- */
IF COL_LENGTH('dbo.pegawai', 'sumber') IS NULL
BEGIN
    ALTER TABLE dbo.pegawai ADD sumber NVARCHAR(20) NULL;
    PRINT '>> kolom pegawai.sumber dibuat';
END
GO
UPDATE dbo.pegawai SET sumber = 'MANUAL' WHERE sumber IS NULL;
GO


/* =====================================================================
   QUERY BANTUAN - dipakai NANTI saat merapikan
   ===================================================================== */

-- 1. Lihat departemen mana yang dari ZKTeco, berapa pegawainya
/*
SELECT d.id_dept, d.kode_dept, d.nama_dept, d.sumber,
       COUNT(p.id_peg) AS jml_pegawai
FROM dbo.department d
LEFT JOIN dbo.unit_kerja u ON u.department_id = d.id_dept
LEFT JOIN dbo.pegawai    p ON p.unit_kerja_id = u.id AND p.is_aktif = 1
GROUP BY d.id_dept, d.kode_dept, d.nama_dept, d.sumber
ORDER BY d.sumber, jml_pegawai DESC;
*/

-- 2. Gabungkan departemen ZKTeco ke departemen dbHR yang setara.
--    Contoh: pindahkan semua pegawai dept 'IT' (ZKTeco) ke 'EDP' (dbHR).
/*
DECLARE @unit_asal INT, @unit_tujuan INT;
SELECT @unit_asal   = u.id FROM dbo.unit_kerja u
       JOIN dbo.department d ON d.id_dept = u.department_id
       WHERE d.nama_dept = 'IT' AND d.sumber = 'ZKTECO';
SELECT @unit_tujuan = u.id FROM dbo.unit_kerja u
       JOIN dbo.department d ON d.id_dept = u.department_id
       WHERE d.kode_dept = 'EDP';

UPDATE dbo.pegawai SET unit_kerja_id = @unit_tujuan WHERE unit_kerja_id = @unit_asal;
*/

-- 3. BATALKAN seluruh hasil seeding (kalau perlu ulang dari awal)
/*
DELETE FROM dbo.pegawai    WHERE sumber = 'ZKTECO';
DELETE FROM dbo.unit_kerja WHERE sumber = 'ZKTECO';
DELETE FROM dbo.department WHERE sumber = 'ZKTECO';
-- lalu jalankan ulang seed_pegawai_dari_zkteco.php
*/
