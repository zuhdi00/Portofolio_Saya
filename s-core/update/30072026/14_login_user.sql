/* =====================================================================
   LOGIN & OTORITAS HRIS - database dbHR
   5 peran: admin_it, hr, atasan, admin_divisi, user
   Tabel terpisah (hris_users) - tidak memakai tabel Laravel bawaan
   supaya sederhana untuk HRIS PHP native.
   ===================================================================== */
USE dbHR;
GO

IF OBJECT_ID('dbo.hris_users','U') IS NULL
BEGIN
    DECLARE @tipe NVARCHAR(20);
    SELECT @tipe = UPPER(t.name) FROM sys.columns c JOIN sys.types t ON t.user_type_id=c.user_type_id
    WHERE c.object_id=OBJECT_ID('dbo.pegawai') AND c.name='id_peg';

    DECLARE @sql NVARCHAR(MAX) = N'
    CREATE TABLE dbo.hris_users (
        id_user       INT IDENTITY(1,1) NOT NULL,
        username      NVARCHAR(50)  NOT NULL,
        password_hash NVARCHAR(255) NOT NULL,        -- hasil password_hash() PHP
        nama_lengkap  NVARCHAR(128) NOT NULL,
        peran         NVARCHAR(20)  NOT NULL DEFAULT ''user'',
                       -- admin_it / hr / atasan / admin_divisi / user
        pegawai_id    ' + @tipe + N' NULL,           -- kaitan ke data pegawai (opsional)
        department_id INT           NULL,            -- utk batasi atasan/admin_divisi ke divisinya
        is_aktif      BIT           NOT NULL DEFAULT 1,
        login_terakhir DATETIME     NULL,
        dibuat_pada   DATETIME      NOT NULL DEFAULT GETDATE(),
        CONSTRAINT PK_hris_users PRIMARY KEY (id_user),
        CONSTRAINT UQ_hris_users_username UNIQUE (username),
        CONSTRAINT CK_hris_peran CHECK (peran IN (''admin_it'',''hr'',''atasan'',''admin_divisi'',''user''))
    );';
    EXEC sp_executesql @sql;
    PRINT '>> tabel hris_users dibuat';
END ELSE PRINT '>> hris_users sudah ada';
GO

/* ---------- akun admin IT default ----------
   username: admin   password: admin123
   Hash di bawah = password_hash('admin123', PASSWORD_DEFAULT).
   >>> WAJIB GANTI password setelah login pertama! <<<               */
IF NOT EXISTS (SELECT 1 FROM dbo.hris_users WHERE username='admin')
BEGIN
    INSERT INTO dbo.hris_users (username, password_hash, nama_lengkap, peran)
    VALUES ('admin',
            '$2b$10$oYp9p6V2Tmyfs59g4JIYOezshLAB.UreoglDohSZhvK9CaZL2hqyC',
            'Administrator IT', 'admin_it');
    PRINT '>> akun admin dibuat (username: admin, password: admin123)';
END
GO

SELECT id_user, username, nama_lengkap, peran, is_aktif FROM dbo.hris_users;
GO
