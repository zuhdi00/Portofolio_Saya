/* =====================================================================
   Tambahan kolom di tabel dbo.pegawai (database dbHR)
   Jalankan di SSMS, connect ke server dbHR, database dbHR.
   ===================================================================== */
USE dbHR;
GO

-- Kolom dari form "Tambah Pegawai Lengkap" yang belum ada di skema dbHR asli
IF COL_LENGTH('dbo.pegawai', 'company_name') IS NULL ALTER TABLE dbo.pegawai ADD company_name NVARCHAR(50) NULL DEFAULT N'PT. Supracor Sejahtera';
IF COL_LENGTH('dbo.pegawai', 'contract_month') IS NULL ALTER TABLE dbo.pegawai ADD contract_month INT NULL;
IF COL_LENGTH('dbo.pegawai', 'position_code') IS NULL ALTER TABLE dbo.pegawai ADD position_code NVARCHAR(50) NULL;
IF COL_LENGTH('dbo.pegawai', 'level_code') IS NULL ALTER TABLE dbo.pegawai ADD level_code NVARCHAR(20) NULL;
IF COL_LENGTH('dbo.pegawai', 'grade_code') IS NULL ALTER TABLE dbo.pegawai ADD grade_code NVARCHAR(20) NULL;
IF COL_LENGTH('dbo.pegawai', 'employee_subgroup') IS NULL ALTER TABLE dbo.pegawai ADD employee_subgroup NVARCHAR(100) NULL;
IF COL_LENGTH('dbo.pegawai', 'ptkp_status') IS NULL ALTER TABLE dbo.pegawai ADD ptkp_status NVARCHAR(100) NULL;
IF COL_LENGTH('dbo.pegawai', 'bank_payee') IS NULL ALTER TABLE dbo.pegawai ADD bank_payee NVARCHAR(255) NULL;
IF COL_LENGTH('dbo.pegawai', 'bank_kode') IS NULL ALTER TABLE dbo.pegawai ADD bank_kode NVARCHAR(10) NULL;
IF COL_LENGTH('dbo.pegawai', 'bank_detail') IS NULL ALTER TABLE dbo.pegawai ADD bank_detail NVARCHAR(255) NULL;
GO

-- Field alamat dan telepon yang dipakai form tambah/edit pegawai.
IF COL_LENGTH('dbo.pegawai', 'almt_tetap_negara') IS NULL
    ALTER TABLE dbo.pegawai ADD almt_tetap_negara NVARCHAR(100) NULL;
IF COL_LENGTH('dbo.pegawai', 'no_telp') IS NULL
    ALTER TABLE dbo.pegawai ADD no_telp NVARCHAR(20) NULL;
IF COL_LENGTH('dbo.pegawai', 'almt_smtr_negara') IS NULL
    ALTER TABLE dbo.pegawai ADD almt_smtr_negara NVARCHAR(100) NULL;
IF COL_LENGTH('dbo.pegawai', 'almt_smtr_telp') IS NULL
    ALTER TABLE dbo.pegawai ADD almt_smtr_telp NVARCHAR(20) NULL;
GO

/* ---------------------------------------------------------------------
   Kolom TAMBAHAN yang direkomendasikan supaya modul PRESENSI (QR & barcode)
   bisa jalan. Tabel dbHR asli belum punya ini — presensi butuh cara
   mengidentifikasi pegawai (barcode), PIN login, device binding, dan jam
   kerja standar untuk hitung "telat". Kalau tidak dipakai, lewati bagian ini.
   --------------------------------------------------------------------- */
IF COL_LENGTH('dbo.pegawai', 'barcode') IS NULL ALTER TABLE dbo.pegawai ADD barcode NVARCHAR(50) NULL;
IF COL_LENGTH('dbo.pegawai', 'pin_hash') IS NULL ALTER TABLE dbo.pegawai ADD pin_hash NVARCHAR(255) NULL;
IF COL_LENGTH('dbo.pegawai', 'device_id') IS NULL ALTER TABLE dbo.pegawai ADD device_id NVARCHAR(64) NULL;
IF COL_LENGTH('dbo.pegawai', 'jam_masuk_std') IS NULL ALTER TABLE dbo.pegawai ADD jam_masuk_std TIME NULL DEFAULT '08:00:00';
IF COL_LENGTH('dbo.pegawai', 'jam_pulang_std') IS NULL ALTER TABLE dbo.pegawai ADD jam_pulang_std TIME NULL DEFAULT '17:00:00';
GO
