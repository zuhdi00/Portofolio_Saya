# 📚 DOKUMENTASI TOOLS MIGRASI DATA PEGAWAI

## Ringkasan Umum

Paket tools ini membantu Anda:
1. **Membandingkan** data pegawai antara CSV dan SQL Server database
2. **Mengidentifikasi** perbedaan dan data yang belum tersinkronisasi
3. **Melakukan migrasi** otomatis dengan preview/dry-run terlebih dahulu
4. **Generate SQL scripts** untuk eksekusi manual di SSMS jika diperlukan

## 📊 Tool 1: Data Comparison Tool

**File:** `compare_data_employee.php`

### Fungsi:
Membandingkan data CSV dengan SQL Server database secara detail.

### Cara Kerja:
1. Membaca file CSV: `../database/DATA KARYAWAN (2).csv`
2. Query data dari SQL Server: `dbo.pegawai_lengkap`
3. Membandingkan kolom-kolom secara case-insensitive
4. Menghasilkan laporan:
   - ✅ **Data Sama**: Data yang cocok di kedua sumber
   - ⚠️ **Data Berbeda**: Data dengan perbedaan nilai
   - ❌ **Hanya di CSV**: Data baru yang belum ada di SQL
   - ℹ️ **Hanya di SQL**: Data yang tidak ada di CSV

### Output:
- Tabel detail perbandingan
- Summary statistik
- Identifikasi field yang berbeda

### Kolom yang Dibandingkan:
```
- no_ktp, nama, email, no_hp
- tanggal_lahir, tempat_lahir, gender, agama, status_kawin
- Alamat: alamat_ktp, rt, rw, kelurahan, kecamatan, kota, provinsi, kode_pos
```

---

## 🚀 Tool 2: Migration Tool

**File:** `migrate_csv_to_sqlserver.php`

### Fungsi:
Melakukan migrasi data dari CSV ke SQL Server dengan opsi:
- **DRY RUN** (default): Preview perubahan tanpa mengubah database
- **EXECUTE**: Benar-benar mengubah database

### Mode Operasi:

#### Mode 1: DRY RUN (Preview)
```
http://localhost/hris/tools/migrate_csv_to_sqlserver.php?dry_run=1
```
- Menampilkan action apa yang akan dilakukan (INSERT/UPDATE)
- Tidak mengubah database
- Aman untuk verifikasi

#### Mode 2: EXECUTE (Actual Migration)
```
http://localhost/hris/tools/migrate_csv_to_sqlserver.php?dry_run=0
```
- Benar-benar mengubah database
- Memerlukan konfirmasi
- Jalankan hanya setelah yakin dengan DRY RUN

### Proses Migrasi:

**Untuk setiap record di CSV:**

1. Cek apakah NIK sudah ada di SQL Server
   - **Jika ADA** → UPDATE record dengan data CSV terbaru
   - **Jika TIDAK ADA** → INSERT record baru

2. Konversi format tanggal dari `d/m/Y` ke `Y-m-d`

3. Normalisasi nilai NULL (kolom kosong → NULL)

4. Jalankan query dengan parameter binding untuk keamanan

### Statistics Output:
- Total records CSV
- Jumlah INSERT (data baru)
- Jumlah UPDATE (data existing)
- Error details (jika ada)

### Contoh Workflow:

```
1. Buka: migrate_csv_to_sqlserver.php?dry_run=1
   ↓
   Preview menunjukkan:
   - 10 records akan di-INSERT
   - 50 records akan di-UPDATE
   
2. Jika sudah yakin, klik "EXECUTE MIGRASI"
   ↓
   
3. System akan benar-benar mengubah database
   ↓
   
4. Verifikasi dengan compare_data_employee.php
```

---

## 💾 Tool 3: SQL Script Generator

**File:** `generate_migration_sql.php`

### Fungsi:
Generate SQL script lengkap yang bisa dijalankan di SQL Server Management Studio.

### Output:
File: `migration_pegawai_YYYY-MM-DD_HHmmss.sql`

### Isi Script:
```sql
-- Header dengan informasi
-- Disable triggers untuk performa
-- Loop setiap record:
   -- IF EXISTS: UPDATE
   -- ELSE: INSERT
-- Re-enable triggers
-- Verification queries
```

### Cara Pakai:

1. **Klik:** "Download SQL Script"
2. **Simpan** file `.sql` di komputer Anda
3. **Buka SSMS** dan connect ke server `spsdmz2`
4. **Select database** `dbHR`
5. **Buka file** `.sql`
6. **Review** sebentar, pastikan benar
7. **Klik Execute** (F5)

### Keuntungan:
- Bisa di-review sebelum dijalankan
- Tidak perlu PHP/web server
- Bisa di-schedule di SQL Agent
- Mudah di-audit dan di-track

---

## 🔍 Perbandingan Case-Insensitive

Sistem membandingkan data **TANPA memperhatikan besar-kecil huruf**.

### Contoh:
```
CSV: "SUDIONO"
SQL: "Sudiono"
SQL: "sudiono"

HASIL: ✅ DIANGGAP SAMA
```

Ini berarti perbedaan kapitalisasi nama tidak akan memicu UPDATE.

---

## 🗄️ Struktur Database Target

Database Target: **SQL Server - dbHR**
Tabel: **dbo.pegawai_lengkap**

### Kolom Utama:
```
- nik (NVARCHAR(20)) - PRIMARY KEY
- no_ktp, nama, email, no_hp
- tanggal_lahir, tempat_lahir, gender, agama, status_kawin
- almt_tetap_* (Alamat tetap: RT, RW, desa, kecamatan, kota, provinsi, kodepos)
- company_name, contract_month, position_code, level_code, grade_code
- bank_* (bank_payee, bank_kode, bank_nama, bank_detail, bank_rekening)
- created_at, updated_at (DATETIME)
```

---

## 📋 Mapping CSV → SQL

| CSV Column | SQL Column | Tipe Data |
|---|---|---|
| id_peg | nik | NVARCHAR(20) |
| no_ktp | no_ktp | NVARCHAR(20) |
| nama | nama | NVARCHAR(255) |
| email_peg | email | NVARCHAR(255) |
| no_hp_peg | no_hp | NVARCHAR(20) |
| tgl_lahir | tanggal_lahir | DATE |
| tempat_lahir | tempat_lahir | NVARCHAR(100) |
| gender | gender | NVARCHAR(1) |
| agama | agama | NVARCHAR(30) |
| status_kawin | status_kawin | NVARCHAR(20) |
| alamat_ktp_peg | almt_tetap | NVARCHAR(500) |
| rt | almt_tetap_rt | NVARCHAR(5) |
| rw | almt_tetap_rw | NVARCHAR(5) |
| kelurahan | almt_tetap_desa | NVARCHAR(100) |
| kecamatan | almt_tetap_kecamatan | NVARCHAR(100) |
| kota | almt_tetap_kota | NVARCHAR(100) |
| provinsi | almt_tetap_provinsi | NVARCHAR(100) |
| kode_pos | almt_tetap_kodepos | NVARCHAR(10) |

---

## ⚙️ Konfigurasi

### Koneksi SQL Server:
**File:** `../config/koneksi_sqlsrv.php`

```php
$serverName = "spsdmz2";        // Server name
$database = "dbHR";             // Database name
$uid = "sa";                    // Username
$password = "supracor";         // Password
```

### Lokasi CSV:
**Path:** `../database/DATA KARYAWAN (2).csv`

---

## 🎯 Panduan Step-by-Step

### Scenario 1: Cek Status Data
```
1. Buka: http://localhost/hris/tools/
2. Klik: "📊 Jalankan Analisis"
3. Lihat laporan perbandingan
4. Identifikasi masalah yang perlu diperbaiki
```

### Scenario 2: Sinkronisasi Data (Aman)
```
1. Buka: http://localhost/hris/tools/migrate_csv_to_sqlserver.php
   (otomatis DRY RUN mode)
2. Review jumlah INSERT/UPDATE
3. Klik: "🔴 EXECUTE MIGRASI" jika sudah yakin
4. Tunggu hingga selesai
5. Verifikasi dengan tool #1
```

### Scenario 3: Migrasi via SSMS
```
1. Buka: http://localhost/hris/tools/generate_migration_sql.php
2. Klik: "Download SQL Script"
3. Buka SSMS → spsdmz2 → dbHR
4. File → Open → Pilih file .sql
5. Review script
6. Execute (F5)
```

---

## ⚠️ Tips Penting

### Sebelum Migrasi:
- ✅ **Backup database dbHR**
- ✅ **Test di environment non-production terlebih dahulu**
- ✅ **Review DRY RUN output dengan teliti**
- ✅ **Validasi data CSV sudah benar**

### Selama Migrasi:
- ✅ **Jangan tutup/refresh halaman**
- ✅ **Tunggu hingga prosesnya selesai**
- ✅ **Catat error message jika ada**

### Sesudah Migrasi:
- ✅ **Jalankan perbandingan ulang untuk verifikasi**
- ✅ **Cek data di aplikasi**
- ✅ **Pastikan report/dashboard menampilkan data dengan benar**
- ✅ **Dokumentasikan perubahan untuk audit trail**

---

## 🐛 Troubleshooting

### Error: "CSV file tidak ditemukan"
**Solusi:** Pastikan file ada di `c:\xampp\htdocs\hris\database\DATA KARYAWAN (2).csv`

### Error: "Koneksi ke dbHR gagal"
**Solusi:** 
1. Cek apakah server `spsdmz2` accessible
2. Cek credentials di `koneksi_sqlsrv.php`
3. Cek apakah SQL Server sedang berjalan
4. Test koneksi dengan `telnet spsdmz2 1433`

### Data tidak berubah setelah migrasi
**Solusi:**
1. Cek apakah Anda klik "EXECUTE" (bukan hanya DRY RUN)
2. Verifikasi di SSMS:
   ```sql
   SELECT TOP 10 * FROM dbo.pegawai_lengkap ORDER BY updated_at DESC
   ```

### Performance lambat saat migrasi
**Solusi:**
- Script secara default **disable triggers** untuk performa lebih cepat
- Tunggu sebentar atau jalankan di luar jam kerja

---

## 📞 Support & Questions

Jika ada pertanyaan atau masalah:
1. Lihat bagian FAQ di halaman utama tools
2. Check error message yang ditampilkan
3. Lihat SQL error details
4. Review dokumentasi ini lagi

---

**Versi:** 1.0  
**Last Updated:** 2026-01-13  
**Database:** SQL Server spsdmz2 / dbHR  
**Source Data:** CSV File (DATA KARYAWAN (2).csv)

