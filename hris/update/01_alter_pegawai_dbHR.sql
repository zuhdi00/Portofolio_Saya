/* =====================================================================
   Tambahan kolom di tabel dbo.pegawai (database dbHR)
   Jalankan di SSMS, connect ke server dbHR, database dbHR.
   ===================================================================== */
USE dbHR;
GO

-- Kolom dari form "Tambah Pegawai Lengkap" yang belum ada di skema dbHR asli
ALTER TABLE dbo.pegawai ADD
    company_name      NVARCHAR(50)  NULL DEFAULT N'GRP1',
    contract_month    INT           NULL,
    position_code     NVARCHAR(50)  NULL,
    level_code        NVARCHAR(20)  NULL,
    grade_code        NVARCHAR(20)  NULL,
    employee_subgroup NVARCHAR(100) NULL,
    ptkp_status       NVARCHAR(100) NULL,
    bank_payee        NVARCHAR(255) NULL,
    bank_kode         NVARCHAR(10)  NULL,
    bank_detail       NVARCHAR(255) NULL;
GO

/* ---------------------------------------------------------------------
   Kolom TAMBAHAN yang direkomendasikan supaya modul PRESENSI (QR & barcode)
   bisa jalan. Tabel dbHR asli belum punya ini — presensi butuh cara
   mengidentifikasi pegawai (barcode), PIN login, device binding, dan jam
   kerja standar untuk hitung "telat". Kalau tidak dipakai, lewati bagian ini.
   --------------------------------------------------------------------- */
ALTER TABLE dbo.pegawai ADD
    barcode        NVARCHAR(50)  NULL,   -- kode barcode/QR pegawai (bisa = no_ktp)
    pin_hash       NVARCHAR(255) NULL,   -- hasil password_hash() dari PIN presensi
    device_id      NVARCHAR(64)  NULL,   -- device binding HP presensi QR
    jam_masuk_std  TIME          NULL DEFAULT '08:00:00',  -- jadwal jam masuk standar
    jam_pulang_std TIME          NULL DEFAULT '17:00:00';  -- jadwal jam pulang standar
GO
