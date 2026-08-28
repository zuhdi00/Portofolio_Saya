/* =====================================================================
   AUDIT & LENGKAPI KOLOM  - database dbHR
   Memastikan semua kolom yang dipakai HRIS (form pegawai, edit, presensi)
   ADA di tabel. Kolom yang belum ada akan DIBUAT otomatis.
   Aman dijalankan berulang kali (idempoten).
   ===================================================================== */
USE dbHR;
GO

SET NOCOUNT ON;

/* Helper: tambah kolom kalau belum ada -------------------------------- */
-- dijalankan via dynamic SQL di bawah, per kolom.

/* ---------- 1. Tabel PEGAWAI ---------- */
DECLARE @kol TABLE (nama SYSNAME, tipe NVARCHAR(50));
INSERT INTO @kol VALUES
 ('nik','NVARCHAR(20)'),
 ('nama_peg','NVARCHAR(128)'),
 ('no_ktp','NVARCHAR(20)'),
 ('npwp','NVARCHAR(30)'),
 ('email_peg','NVARCHAR(128)'),
 ('no_hp_peg','NVARCHAR(20)'),
 ('gender','NVARCHAR(255)'),
 ('agama','NVARCHAR(20)'),
 ('tempat_lahir','NVARCHAR(255)'),
 ('tgl_lahir','DATE'),
 ('status_nikah','NVARCHAR(255)'),
 ('status_kawin','NVARCHAR(20)'),
 ('status_karyawan','NVARCHAR(255)'),
 ('alamat_ktp_peg','NVARCHAR(255)'),
 ('rt','NVARCHAR(5)'),
 ('rw','NVARCHAR(5)'),
 ('kelurahan','NVARCHAR(100)'),
 ('kecamatan','NVARCHAR(100)'),
 ('kota','NVARCHAR(100)'),
 ('provinsi','NVARCHAR(100)'),
 ('kode_pos','NVARCHAR(10)'),
 ('alamat_domi_peg','NVARCHAR(255)'),
 ('rt_dom','NVARCHAR(5)'),
 ('rw_dom','NVARCHAR(5)'),
 ('kelurahan_dom','NVARCHAR(100)'),
 ('kecamatan_dom','NVARCHAR(100)'),
 ('kota_dom','NVARCHAR(100)'),
 ('provinsi_dom','NVARCHAR(100)'),
 ('kode_pos_dom','NVARCHAR(10)'),
 ('tgl_masuk','DATE'),
 ('tgl_akhir_kontrak','DATE'),
 ('tgl_berhenti','DATE'),
 ('alasan_berhenti','NVARCHAR(255)'),
 ('lokasi_kerja','NVARCHAR(255)'),
 ('work_location','NVARCHAR(255)'),
 ('position_code','NVARCHAR(100)'),
 ('level_code','NVARCHAR(20)'),
 ('grade_code','NVARCHAR(20)'),
 ('employee_subgroup','NVARCHAR(100)'),
 ('company_name','NVARCHAR(50)'),
 ('contract_month','INT'),
 ('nama_bank','NVARCHAR(100)'),
 ('bank_kode','NVARCHAR(10)'),
 ('no_rekening','NVARCHAR(50)'),
 ('bank_payee','NVARCHAR(255)'),
 ('bank_detail','NVARCHAR(255)'),
 ('no_bpjs_tk','NVARCHAR(30)'),
 ('no_bpjs_kes','NVARCHAR(30)'),
 ('jabatan_id','INT'),
 ('unit_kerja_id','INT'),
 ('is_aktif','BIT'),
 ('sumber','NVARCHAR(20)'),
 ('zkteco_userid','INT'),
 ('zkteco_acno','NVARCHAR(24)');

DECLARE @n SYSNAME, @t NVARCHAR(50), @sql NVARCHAR(MAX), @dibuat INT = 0;
DECLARE cur CURSOR FOR SELECT nama, tipe FROM @kol;
OPEN cur; FETCH NEXT FROM cur INTO @n, @t;
WHILE @@FETCH_STATUS = 0
BEGIN
    IF COL_LENGTH('dbo.pegawai', @n) IS NULL
    BEGIN
        SET @sql = 'ALTER TABLE dbo.pegawai ADD ' + QUOTENAME(@n) + ' ' + @t + ' NULL;';
        EXEC sp_executesql @sql;
        PRINT '  + pegawai.' + @n + ' dibuat';
        SET @dibuat = @dibuat + 1;
    END
    FETCH NEXT FROM cur INTO @n, @t;
END
CLOSE cur; DEALLOCATE cur;
PRINT 'Pegawai: ' + CAST(@dibuat AS VARCHAR) + ' kolom baru dibuat.';
GO


/* ---------- 2. Tabel anak: keluarga_pegawai ---------- */
IF COL_LENGTH('dbo.keluarga_pegawai','pegawai_id')   IS NULL ALTER TABLE dbo.keluarga_pegawai ADD pegawai_id BIGINT NULL;
IF COL_LENGTH('dbo.keluarga_pegawai','nama')         IS NULL ALTER TABLE dbo.keluarga_pegawai ADD nama NVARCHAR(128) NULL;
IF COL_LENGTH('dbo.keluarga_pegawai','hubungan')     IS NULL ALTER TABLE dbo.keluarga_pegawai ADD hubungan NVARCHAR(20) NULL;
IF COL_LENGTH('dbo.keluarga_pegawai','gender')       IS NULL ALTER TABLE dbo.keluarga_pegawai ADD gender NVARCHAR(5) NULL;
IF COL_LENGTH('dbo.keluarga_pegawai','status_nikah') IS NULL ALTER TABLE dbo.keluarga_pegawai ADD status_nikah NVARCHAR(20) NULL;
IF COL_LENGTH('dbo.keluarga_pegawai','status_hidup') IS NULL ALTER TABLE dbo.keluarga_pegawai ADD status_hidup NVARCHAR(20) NULL;
IF COL_LENGTH('dbo.keluarga_pegawai','tempat_lahir') IS NULL ALTER TABLE dbo.keluarga_pegawai ADD tempat_lahir NVARCHAR(255) NULL;
IF COL_LENGTH('dbo.keluarga_pegawai','tgl_lahir')    IS NULL ALTER TABLE dbo.keluarga_pegawai ADD tgl_lahir DATE NULL;
IF COL_LENGTH('dbo.keluarga_pegawai','no_ktp')       IS NULL ALTER TABLE dbo.keluarga_pegawai ADD no_ktp NVARCHAR(20) NULL;
IF COL_LENGTH('dbo.keluarga_pegawai','no_kk')        IS NULL ALTER TABLE dbo.keluarga_pegawai ADD no_kk NVARCHAR(20) NULL;
IF COL_LENGTH('dbo.keluarga_pegawai','no_bpjs')      IS NULL ALTER TABLE dbo.keluarga_pegawai ADD no_bpjs NVARCHAR(30) NULL;
GO

/* ---------- 3. pendidikan_pegawai ---------- */
IF COL_LENGTH('dbo.pendidikan_pegawai','pegawai_id')    IS NULL ALTER TABLE dbo.pendidikan_pegawai ADD pegawai_id BIGINT NULL;
IF COL_LENGTH('dbo.pendidikan_pegawai','nama_sekolah')  IS NULL ALTER TABLE dbo.pendidikan_pegawai ADD nama_sekolah NVARCHAR(255) NULL;
IF COL_LENGTH('dbo.pendidikan_pegawai','jenjang')       IS NULL ALTER TABLE dbo.pendidikan_pegawai ADD jenjang NVARCHAR(20) NULL;
IF COL_LENGTH('dbo.pendidikan_pegawai','jurusan')       IS NULL ALTER TABLE dbo.pendidikan_pegawai ADD jurusan NVARCHAR(255) NULL;
IF COL_LENGTH('dbo.pendidikan_pegawai','lokasi')        IS NULL ALTER TABLE dbo.pendidikan_pegawai ADD lokasi NVARCHAR(255) NULL;
IF COL_LENGTH('dbo.pendidikan_pegawai','tahun_mulai')   IS NULL ALTER TABLE dbo.pendidikan_pegawai ADD tahun_mulai INT NULL;
IF COL_LENGTH('dbo.pendidikan_pegawai','tahun_selesai') IS NULL ALTER TABLE dbo.pendidikan_pegawai ADD tahun_selesai INT NULL;
IF COL_LENGTH('dbo.pendidikan_pegawai','ipk')           IS NULL ALTER TABLE dbo.pendidikan_pegawai ADD ipk DECIMAL(4,2) NULL;
GO

/* ---------- 4. pengalaman_kerja ---------- */
IF COL_LENGTH('dbo.pengalaman_kerja','pegawai_id')      IS NULL ALTER TABLE dbo.pengalaman_kerja ADD pegawai_id BIGINT NULL;
IF COL_LENGTH('dbo.pengalaman_kerja','nama_perusahaan') IS NULL ALTER TABLE dbo.pengalaman_kerja ADD nama_perusahaan NVARCHAR(255) NULL;
IF COL_LENGTH('dbo.pengalaman_kerja','jabatan')         IS NULL ALTER TABLE dbo.pengalaman_kerja ADD jabatan NVARCHAR(255) NULL;
IF COL_LENGTH('dbo.pengalaman_kerja','tgl_mulai')       IS NULL ALTER TABLE dbo.pengalaman_kerja ADD tgl_mulai DATE NULL;
IF COL_LENGTH('dbo.pengalaman_kerja','tgl_selesai')     IS NULL ALTER TABLE dbo.pengalaman_kerja ADD tgl_selesai DATE NULL;
IF COL_LENGTH('dbo.pengalaman_kerja','keterangan')      IS NULL ALTER TABLE dbo.pengalaman_kerja ADD keterangan NVARCHAR(500) NULL;
GO


/* ---------- 5. VERIFIKASI AKHIR ----------
   Daftar semua kolom pegawai yang MASIH hilang (harusnya kosong).      */
PRINT '';
PRINT '=== Kolom pegawai yang masih hilang (harusnya tidak ada) ===';
DECLARE @cek TABLE (nama SYSNAME);
INSERT INTO @cek VALUES
 ('nik'),('nama_peg'),('no_ktp'),('npwp'),('email_peg'),('no_hp_peg'),('gender'),
 ('agama'),('tempat_lahir'),('tgl_lahir'),('status_nikah'),('status_kawin'),
 ('status_karyawan'),('alamat_ktp_peg'),('rt'),('rw'),('kelurahan'),('kecamatan'),
 ('kota'),('provinsi'),('kode_pos'),('alamat_domi_peg'),('rt_dom'),('rw_dom'),
 ('kelurahan_dom'),('kecamatan_dom'),('kota_dom'),('provinsi_dom'),('kode_pos_dom'),
 ('tgl_masuk'),('tgl_akhir_kontrak'),('tgl_berhenti'),('alasan_berhenti'),
 ('lokasi_kerja'),('work_location'),('position_code'),('level_code'),('grade_code'),
 ('employee_subgroup'),('company_name'),('contract_month'),('nama_bank'),('bank_kode'),
 ('no_rekening'),('bank_payee'),('bank_detail'),('no_bpjs_tk'),('no_bpjs_kes'),
 ('jabatan_id'),('unit_kerja_id'),('is_aktif'),('sumber'),('zkteco_userid'),('zkteco_acno');

SELECT nama AS kolom_masih_hilang FROM @cek
WHERE COL_LENGTH('dbo.pegawai', nama) IS NULL;
GO
