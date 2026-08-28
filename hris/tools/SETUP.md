# 🔧 Setup Database - Panduan Lengkap

## 📋 Permasalahan

Ketika mencoba menjalankan tools migrasi, Anda mendapatkan error:
```
Query SQL Server gagal: 
[SQLSTATE 42S02, Code 208]
Invalid object name 'dbo.pegawai_lengkap'
```

**Penyebab:** Tabel `dbo.pegawai_lengkap` belum dibuat di database SQL Server `dbHR` pada server `spsdmz2`.

---

## ✅ Solusi (Step-by-Step)

### **Step 1: Download SQL Setup Script**

File setup script sudah tersedia di folder tools:

```
📁 c:\xampp\htdocs\hris\tools\
   ├── setup_database.sql          ← File ini yang dibutuhkan
   ├── database_setup_guide.html   ← Panduan interaktif
   ├── index.html                  ← Portal utama
   └── ...
```

### **Step 2: Buka SQL Server Management Studio (SSMS)**

Jika belum install, download dari:
- [SQL Server Management Studio Download](https://learn.microsoft.com/en-us/sql/ssms/download-sql-server-management-studio-ssms)

### **Step 3: Koneksi ke Server**

Di SSMS, lakukan koneksi dengan detail:

| Parameter | Nilai |
|-----------|-------|
| **Server Name** | `spsdmz2` |
| **Authentication** | SQL Server Authentication |
| **Login** | `sa` |
| **Password** | `supracor` |

**Langkah:**
1. Buka SSMS
2. Di layar Connect to Server:
   - Server name: `spsdmz2`
   - Authentication: pilih "SQL Server Authentication"
   - Login: `sa`
   - Password: `supracor`
3. Klik **Connect**

### **Step 4: Pilih Database Target**

Setelah connected:
1. Di **Object Explorer** (panel kiri), expand node server `spsdmz2`
2. Expand **Databases**
3. Klik kanan pada database `dbHR`
4. Pilih **"New Query"** atau tekan **Ctrl+N**

> **Penting:** Pastikan database yang dipilih adalah `dbHR`, bukan master!

### **Step 5: Buka Setup Script**

1. Menu: **File → Open → File...**
2. Cari file: `setup_database.sql`
   - Path: `c:\xampp\htdocs\hris\tools\setup_database.sql`
3. Klik **Open**

Script akan ter-load di query editor.

### **Step 6: Review Script Sebelum Eksekusi**

Script ini akan membuat:
- ✓ Table `dbo.pegawai_lengkap` - data pegawai utama
- ✓ Table `dbo.pegawai_keluarga` - data keluarga
- ✓ Table `dbo.pegawai_pendidikan` - data pendidikan
- ✓ Table `dbo.pegawai_pengalaman` - data pengalaman kerja
- ✓ Indexes untuk performa query

**Catatan:** Script menggunakan `IF NOT EXISTS`, jadi aman dijalankan berkali-kali. Tidak akan menghapus data yang sudah ada.

### **Step 7: Eksekusi Script**

1. Pilih semua text script (Ctrl+A)
2. Klik button **Execute** atau tekan **F5**
3. Tunggu hingga selesai ⏳

**Jangan:**
- ❌ Interrupt atau cancel proses
- ❌ Buka tab lain
- ❌ Minimize window SSMS

### **Step 8: Cek Hasil Eksekusi**

Di tab **Messages**, Anda akan lihat output:

```
Table dbo.pegawai_lengkap created successfully.
Table dbo.pegawai_keluarga created successfully.
Table dbo.pegawai_pendidikan created successfully.
Table dbo.pegawai_pengalaman created successfully.
Index IX_pegawai_lengkap_nik created.
Index IX_pegawai_lengkap_nama created.
Index IX_pegawai_lengkap_updated created.

=== DATABASE SETUP VERIFICATION ===

TableName          Status    RecordCount
pegawai_lengkap    ✓ EXISTS  0
pegawai_keluarga   ✓ EXISTS  0
pegawai_pendidikan ✓ EXISTS  0
pegawai_pengalaman ✓ EXISTS  0

✓ DATABASE SETUP COMPLETE!

Next steps:
1. Go to: http://localhost/hris/tools/
2. Click: "Jalankan Analisis" to verify connection
3. Click: "Mulai Migrasi" to start migration
```

---

## 🔍 Verifikasi Setup Berhasil

### Opsi 1: Menggunakan Database Setup Guide (Recommended)

1. Buka browser: `http://localhost/hris/tools/database_setup_guide.html`
2. Page akan otomatis mengecek status:
   - ✓ SQL Server Connection
   - ✓ Table Existence
   - ✓ CSV File Status
3. Jika semua hijau (✓), setup sudah berhasil!

### Opsi 2: Menggunakan Portal Utama

1. Buka: `http://localhost/hris/tools/`
2. Klik: **Jalankan Analisis Perbandingan Data**
3. Jika halaman terbuka tanpa error, setup berhasil

### Opsi 3: Manual Query di SSMS

Di SSMS, jalankan query:
```sql
SELECT COUNT(*) as RecordCount FROM dbo.pegawai_lengkap;
```

Jika berhasil menampilkan angka (bahkan 0), berarti tabel sudah ada.

---

## 🚀 Mulai Menggunakan Tools

Setelah setup berhasil, Anda bisa menggunakan:

### 1. **Jalankan Analisis Perbandingan**
```
http://localhost/hris/tools/compare_data_employee.php
```
- Bandingkan data CSV dengan SQL Server
- Lihat data yang sama, berbeda, hanya di CSV, hanya di SQL

### 2. **Mulai Migrasi Data**
```
http://localhost/hris/tools/migrate_csv_to_sqlserver.php
```
- DRY RUN mode (default, aman) - lihat preview tanpa perubahan
- EXECUTE mode - lakukan migrasi sebenarnya

### 3. **Generate SQL Script**
```
http://localhost/hris/tools/generate_migration_sql.php
```
- Generate `.sql` file untuk dijalankan di SSMS
- Berguna untuk dokumentasi dan audit trail

### 4. **Verifikasi Hasil**
```
http://localhost/hris/tools/verify_migration.php
```
- Dashboard untuk cek hasil migrasi
- Statistik dan data quality report

---

## 🐛 Troubleshooting

### ❌ Error: "Cannot connect to server spsdmz2"

**Penyebab:**
- Server tidak accessible (network issue)
- Server name salah
- SQL Server service tidak running
- Firewall blocking

**Solusi:**
1. Cek koneksi: `ping spsdmz2`
2. Cek SQL Server sedang running:
   - Windows: Services > SQL Server (MSSQLSERVER)
   - Pastikan status = "Running"
3. Cek firewall settings
4. Gunakan IP address jika DNS tidak resolve: `ping <IP>`

### ❌ Error: "Login failed for user 'sa'"

**Penyebab:**
- Username/password salah
- SQL Server Authentication belum diaktifkan
- User 'sa' tidak exist

**Solusi:**
1. Verifikasi password 'supracor' benar
2. Di SSMS, cek SQL Server Authentication:
   - Server Properties → Security → Server Authentication
   - Pilih "SQL Server and Windows Authentication mode"
   - Restart SQL Server service
3. Cek user 'sa' ada:
   ```sql
   SELECT * FROM sys.sql_logins WHERE name = 'sa'
   ```

### ❌ Error: "Connection timeout"

**Penyebab:**
- Network latency tinggi
- Server busy
- Firewall rule blocking port 1433

**Solusi:**
1. Cek bandwidth/latency: `ping -t spsdmz2`
2. Jalankan setup di non-peak hours
3. Hubungi IT untuk cek firewall rules

### ❌ Error saat eksekusi script di SSMS

**Penyebab:**
- Script terinterrupt
- Syntax error
- Permissions issue

**Solusi:**
1. Copy seluruh script, clear query window
2. Paste ulang dan jalankan
3. Jika masih error, cek permissions:
   ```sql
   -- Query untuk cek permissions
   SELECT * FROM fn_my_permissions(NULL, 'DATABASE') 
   WHERE permission_name = 'CREATE TABLE'
   ```

---

## 📞 Support & Resources

| Resource | Link |
|----------|------|
| **Portal Utama** | http://localhost/hris/tools/ |
| **Setup Guide Interaktif** | http://localhost/hris/tools/database_setup_guide.html |
| **Dokumentasi Lengkap** | c:\xampp\htdocs\hris\tools\README.md |
| **Quickstart Guide** | c:\xampp\htdocs\hris\tools\QUICKSTART.md |
| **Pre-Migration Checklist** | http://localhost/hris/tools/checklist.html |

---

## ✅ Checklist Sebelum Migrasi

- [ ] SQL Server connection successful
- [ ] Table `dbo.pegawai_lengkap` created
- [ ] Database Setup Guide shows all green (✓)
- [ ] CSV file found at `c:\xampp\htdocs\hris\database\DATA KARYAWAN (2).csv`
- [ ] Comparison tool runs without error
- [ ] Review comparison results
- [ ] Run DRY RUN migration mode first
- [ ] Verify DRY RUN output
- [ ] Backup database before EXECUTE
- [ ] Run EXECUTE migration mode
- [ ] Verify migration results
- [ ] Check data integrity

---

**Last Updated:** 2026-08-13
**Tools Version:** 1.0
**Database:** SQL Server (spsdmz2 / dbHR)
