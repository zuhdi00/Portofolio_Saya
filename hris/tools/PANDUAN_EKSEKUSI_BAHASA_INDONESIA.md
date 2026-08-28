# 🚀 PANDUAN EKSEKUSI KONSOLIDASI DATA
## Petunjuk Langkah-demi-Langkah dalam Bahasa Indonesia

---

## 📋 RINGKASAN CEPAT

**Apa yang akan kita lakukan?**
- Menggabungkan 4 grup duplikat (8 record) menjadi 1 person each
- Melacak ID dari berbagai sumber (Excel, SQL, zkteco)
- Menjaga semua data tetap aman (NO DELETE)
- Total waktu: **8-10 menit**

**Keamanan Data:**
- ✅ Tidak ada data yang dihapus
- ✅ Semua 533 record tetap ada
- ✅ zkteco biometric data aman
- ✅ Bisa dibatalkan kapan saja

---

## 🎯 5 LANGKAH EKSEKUSI

### LANGKAH 1️⃣: ANALISIS DUPLIKAT (2 menit)

**Tujuan:** Melihat semua duplikat yang ada

**Cara menjalankan:**

1. Buka browser (Chrome, Firefox, Edge)
2. Ketik URL:
   ```
   http://localhost/hris/tools/analyze_sql_duplicates_detailed.php
   ```
3. Tekan Enter

**Apa yang akan Anda lihat:**
```
📋 DUPLIKAT DITEMUKAN:

Grup 1: SUDIONO
├─ ID: 2000601 (NIK: 3514111701770001)
├─ ID: 2020624 (NIK: 3516050406820001)
└─ Nama: sama orang? ✓

Grup 2: KHOIRUL ANAM
├─ ID: 2060658 (NIK: 3516050906860003)
├─ ID: 42601327 (NIK: 3516052303920002)
└─ Nama: sama orang? ✓

Grup 3: RONI
├─ ID: 41709164 (NIK: 3516061806990002)
├─ ID: 40001380 (NIK: <NULL>)
└─ Nama: sama orang? ✓

Grup 4: DIAN SANTOSO ⚠️
├─ ID: 42607513 (NIK: 3516062803870001)
├─ ID: 42607516 (NIK: 3516062803870001) ← SAME NIK!
└─ Nama: sama orang? Perlu cek!
```

**Langkah berikutnya:**
- Baca hasilnya
- Pahami 4 grup duplikat
- Siap untuk LANGKAH 2

---

### LANGKAH 2️⃣: SETUP SCHEMA (1 menit)

**Tujuan:** Membuat struktur tabel baru untuk tracking

**Cara menjalankan:**

1. Buka browser
2. Ketik URL:
   ```
   http://localhost/hris/tools/setup_schema_and_consolidate.php?action=setup&dry_run=0
   ```
3. Tekan Enter
4. **Cari tombol "🔴 Execute Setup"**
5. **Klik tombol tersebut**

**Apa yang terjadi:**
- ✅ Menambah 7 kolom baru ke tabel pegawai_lengkap:
  - person_unified_id (ID unik untuk orang yang sama)
  - id_source (Excel/SQL/ZKTECO)
  - nik_source (sumber NIK)
  - is_primary (1 = utama, 0 = duplikat)
  - consolidation_notes (catatan)
  - id_peg_excel (ID Excel original)
  - nik_excel (NIK Excel original)

- ✅ Membuat 2 tabel baru:
  - pegawai_id_mapping (untuk linking)
  - pegawai_consolidation_log (audit trail)

**Berapa lama?**
- Sekitar 5-10 detik

**Berhasil atau Error?**
- Lihat pesan di bawah
- Jika "✅ SUCCESS" → Lanjut ke LANGKAH 3
- Jika "❌ ERROR" → Hubungi admin

---

### LANGKAH 3️⃣: ANALISIS & MAPPING (1 menit)

**Tujuan:** Melihat struktur mapping yang akan dibuat

**Cara menjalankan:**

1. Buka browser
2. Ketik URL:
   ```
   http://localhost/hris/tools/setup_schema_and_consolidate.php?action=analyze
   ```
3. Tekan Enter

**Apa yang akan Anda lihat:**
```
📊 MAPPING YANG AKAN DIBUAT:

PERSON_SUDIONO_001
├─ Record 1: ID 2000601 (is_primary: 1)
└─ Record 2: ID 2020624 (is_primary: 0)

PERSON_KHOIRUL_ANAM_001
├─ Record 1: ID 2060658 (is_primary: 1)
└─ Record 2: ID 42601327 (is_primary: 0)

PERSON_RONI_001
├─ Record 1: ID 41709164 (is_primary: 1)
└─ Record 2: ID 40001380 (is_primary: 0)

PERSON_DIAN_SANTOSO_001
├─ Record 1: ID 42607513 (is_primary: 1)
└─ Record 2: ID 42607516 (is_primary: 0)
   ⚠️ Catatan: SAME NIK - perlu verifikasi
```

**Yang perlu dipahami:**
- Setiap person_unified_id mengelompokkan duplikat
- is_primary = 1 adalah record utama
- is_primary = 0 adalah duplikat (tetap ada, hanya ditandai)

**Langkah berikutnya:**
- Setuju dengan mapping? Lanjut LANGKAH 4
- Ragu? Baca dokumentasi atau hubungi admin

---

### LANGKAH 4️⃣: KONSOLIDASI DATA (2 menit)

**Tujuan:** Menghubungkan semua duplikat dengan person_unified_id

**Cara menjalankan:**

1. Buka browser
2. Ketik URL:
   ```
   http://localhost/hris/tools/setup_schema_and_consolidate.php?action=consolidate&dry_run=0
   ```
3. Tekan Enter
4. **Cari tombol "🔴 Execute Consolidation"**
5. **Klik tombol tersebut**

**Apa yang terjadi (LIVE PROCESS):**
```
⏳ PROCESSING...
├─ Consolidating SUDIONO... ✅
├─ Consolidating KHOIRUL ANAM... ✅
├─ Consolidating RONI... ✅
├─ Consolidating DIAN SANTOSO... ✅
└─ Creating mappings... ✅

📊 HASIL:
├─ Record dikonsolidasi: 8
├─ Unique persons: 4
├─ Mappings created: 8
└─ Audit logs created: 4
```

**Data apa yang berubah?**
```
SEBELUM:
├─ Record SUDIONO #1: person_unified_id = NULL
└─ Record SUDIONO #2: person_unified_id = NULL

SESUDAH:
├─ Record SUDIONO #1: person_unified_id = PERSON_SUDIONO_001
└─ Record SUDIONO #2: person_unified_id = PERSON_SUDIONO_001 ← LINKED!
```

**Data apa yang TIDAK berubah?**
- ✅ Semua 44 kolom original TETAP SAMA
- ✅ nama, email, NIK, tanggal lahir → TIDAK BERUBAH
- ✅ zkteco fingerprint → TIDAK BERUBAH
- ✅ Timestamps → TIDAK BERUBAH

**Langkah berikutnya:**
- Lihat "✅ SUCCESS" → Lanjut LANGKAH 5
- Lihat "❌ ERROR" → Hubungi admin

---

### LANGKAH 5️⃣: VERIFIKASI HASIL (2 menit)

**Tujuan:** Memastikan konsolidasi berjalan dengan benar

**Cara menjalankan:**

1. Buka browser
2. Ketik URL:
   ```
   http://localhost/hris/tools/verify_consolidation.php
   ```
3. Tekan Enter

**Apa yang akan Anda lihat:**

```
✅ STATISTIK KONSOLIDASI:
├─ Total Records: 533 ✅ (tidak ada yang hilang!)
├─ Unique Persons (consolidated): 4
├─ Consolidated Records: 8
├─ Primary Records: 4
├─ Duplicate Records: 4
└─ Status: READY FOR MIGRATION

✅ INTEGRITY CHECKS:
├─ Missing is_primary: 0 ✅
├─ Multiple primaries per group: 0 ✅
├─ Orphaned unified IDs: 0 ✅
└─ Mapping integrity: OK ✅

✅ CONSOLIDATED GROUPS:
├─ PERSON_SUDIONO_001 (2 records)
├─ PERSON_KHOIRUL_ANAM_001 (2 records)
├─ PERSON_RONI_001 (2 records)
└─ PERSON_DIAN_SANTOSO_001 (2 records)

✅ AUDIT TRAIL:
├─ Konsolidasi SUDIONO: 2024-XX-XX HH:MM:SS
├─ Konsolidasi KHOIRUL ANAM: 2024-XX-XX HH:MM:SS
├─ Konsolidasi RONI: 2024-XX-XX HH:MM:SS
└─ Konsolidasi DIAN SANTOSO: 2024-XX-XX HH:MM:SS
```

**Semua hijau (✅)?**
- ✅ YES → Konsolidasi BERHASIL! Lanjut ke migrasi CSV
- ❌ NO → Ada masalah, hubungi admin

---

## ✅ SETELAH LANGKAH 5 - ADA PILIHAN

### Pilihan A: Lanjut ke Migrasi CSV Sekarang

**Jika sudah ingin migrasi data CSV:**

1. Buka browser
2. Ketik URL:
   ```
   http://localhost/hris/tools/migrate_csv_to_sqlserver.php
   ```
3. Lihat preview data
4. Klik tombol "Execute Migration"
5. Tunggu selesai (30-60 detik)
6. Lihat laporan hasil

### Pilihan B: Jeda Dulu, Lanjut Nanti

**Jika ingin istirahat atau review:**
- Semua data sudah aman
- Bisa lanjut kapan saja
- Tinggal buka URL migrasi CSV saat siap

---

## ⚠️ KASUS KHUSUS: DIAN SANTOSO

### Apa masalahnya?

Kedua record DIAN SANTOSO punya **NIK yang SAMA**:
- Record 1: ID 42607513 → NIK: 3516062803870001
- Record 2: ID 42607516 → NIK: 3516062803870001 ← SAMA!

### Ini normal?

**Tidak** - Biasanya tidak boleh ada 2 ID berbeda dengan NIK sama. Ini bisa:
1. Data quality issue di SQL Server
2. Kesalahan input saat entry
3. Orang yang sama dengan 2 ID berbeda

### Apa yang harus dilakukan?

**SEBELUM Langkah 4 (Konsolidasi):**

1. Buka http://localhost/8081/hris/tools/analyze_sql_duplicates_detailed.php
2. Cari DIAN SANTOSO
3. Bandingkan kedua record:
   - Mana yang created lebih dulu? (check timestamp)
   - Mana yang di-update terakhir? (check updated_at)
   - Mana yang ada di zkteco system?

**Verifikasi dengan stakeholder:**
- Tanya ke HR: "DIAN SANTOSO punya 1 atau 2 ID di sistem?"
- Tanya ke zkteco admin: "Mana ID yang registered di fingerprint?"

**Keputusan:**
- Jika dari Excel → Gunakan yang dari Excel sebagai primary
- Jika dari SQL → Gunakan yang lebih lama sebagai primary
- Jika tidak tahu → Hubungi admin sebelum konsolidasi

**Saat Konsolidasi:**
- Script akan otomatis link keduanya
- Catatan akan ditambahkan: "⚠️ SAME NIK - needs verification"
- Bisa diadjustment nanti jika ada keputusan baru

---

## 🛑 APA JIKA TERJADI KESALAHAN?

### Kesalahan 1: "Connection Error" atau "Database Error"

**Berarti:**
- Koneksi ke SQL Server putus

**Cara memperbaiki:**
1. Pastikan SQL Server running
   ```
   Buka: Services (Win+R → services.msc)
   Cari: SQL Server (MSSQLSERVER)
   Status: Harus "Running"
   ```
2. Pastikan koneksi.php sudah benar
3. Coba ulang dari LANGKAH 1

### Kesalahan 2: "Table already exists"

**Berarti:**
- Setup sudah pernah dijalankan sebelumnya

**Cara memperbaiki:**
- Aman! Artinya schema sudah siap
- Langsung lanjut ke LANGKAH 3 (Analyze)
- Tidak perlu jalankan Langkah 2 lagi

### Kesalahan 3: "Access Denied" atau "Permission Error"

**Berarti:**
- User SQL Server tidak punya permission

**Cara memperbaiki:**
1. Hubungi DBA/Admin
2. Minta grant permission pada:
   - ALTER TABLE
   - CREATE TABLE
   - INSERT/UPDATE/SELECT

### Kesalahan 4: Consolidation berhenti di tengah

**Berarti:**
- Ada error saat proses

**Cara memperbaiki:**
1. Lihat pesan error di layar
2. Catat error code dan message
3. Hubungi developer
4. Jangan jalankan ulang sebelum tau penyebabnya

---

## 🔄 BAGAIMANA JIKA SALAH ATAU INGIN UNDO?

### Skenario: Konsolidasi sudah jalan, tapi ada yang salah

**Ada ROLLBACK SCRIPT:**

1. Hubungi admin
2. Admin akan jalankan:
   ```
   rollback_consolidation.sql
   ```
3. Semua perubahan di UNDO
4. Durasi: < 5 detik
5. Bisa mulai dari awal

### Skenario: Ingin ganti primary record

Misal: SUDIONO ID 2020624 yang harusnya primary, bukan 2000601

**Caranya:**
1. Hubungi admin
2. Admin update field `is_primary` di tabel
3. Mapping otomatis terupdate
4. Tidak perlu redo seluruh konsolidasi

---

## 📱 NOMOR KONTAK

Jika ada masalah atau pertanyaan:

**Developer:**
- Hubungi: [Masukkan nomor/email developer]

**DBA/Admin SQL Server:**
- Hubungi: [Masukkan nomor/email DBA]

**HR/Business Owner:**
- Hubungi: [Masukkan nomor/email stakeholder]

---

## 📚 DOKUMENTASI LENGKAP

Jika ingin detail lebih lanjut:

**File yang tersedia di folder hris/tools/:**
1. **START_HERE.md** - Ringkasan singkat
2. **DATA_CONSOLIDATION_GUIDE.md** - Detail lengkap
3. **ARCHITECTURE_DIAGRAM.md** - Diagram teknis
4. **CONSOLIDATION_WORKFLOW.html** - Visual guide (buka di browser)
5. **QUICKSTART.md** - Cheat sheet

---

## 🎬 RINGKASAN LANGKAH CEPAT

```
┌─────────────────────────────────────────────────────────┐
│ STEP 1: Analisis (2 min)                                │
│ URL: analyze_sql_duplicates_detailed.php                │
│ Aksi: Buka & baca                                       │
└─────────────────────────────────────────────────────────┘
                        ↓
┌─────────────────────────────────────────────────────────┐
│ STEP 2: Setup (1 min)                                   │
│ URL: setup_schema_and_consolidate.php?action=setup      │
│      &dry_run=0                                         │
│ Aksi: Klik "Execute Setup"                              │
└─────────────────────────────────────────────────────────┘
                        ↓
┌─────────────────────────────────────────────────────────┐
│ STEP 3: Map (1 min)                                     │
│ URL: setup_schema_and_consolidate.php?action=analyze    │
│ Aksi: Buka & lihat mapping                              │
└─────────────────────────────────────────────────────────┘
                        ↓
┌─────────────────────────────────────────────────────────┐
│ STEP 4: Konsolidasi (2 min)                             │
│ URL: setup_schema_and_consolidate.php                   │
│      ?action=consolidate&dry_run=0                      │
│ Aksi: Klik "Execute Consolidation"                      │
└─────────────────────────────────────────────────────────┘
                        ↓
┌─────────────────────────────────────────────────────────┐
│ STEP 5: Verifikasi (2 min)                              │
│ URL: verify_consolidation.php                           │
│ Aksi: Cek semua ✅ hijau                                 │
└─────────────────────────────────────────────────────────┘
                        ↓
           ✅ SEMUA OK? LANJUT MIGRASI CSV!
```

---

## 🎯 CHECKLIST SEBELUM JALANKAN

```
☐ Sudah backup database? (Safety first!)
☐ Sudah baca dokumentasi?
☐ Sudah cek server SQL aktif?
☐ Browser sudah buka?
☐ Sudah paham 5 langkah di atas?
☐ Siap eksekusi?

JIKA SEMUA ✓ → SILAKAN MULAI LANGKAH 1
```

---

**Selamat mengerjakan! 🚀**

Jika ada pertanyaan atau error, jangan ragu untuk menghubungi!

---

*Panduan ini dibuat untuk memudahkan eksekusi konsolidasi data karyawan. Semua data aman dan bisa di-undo kapan saja.*
