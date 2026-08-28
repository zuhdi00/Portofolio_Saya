# 🔧 Data Consolidation & Multi-Source ID Tracking
## Panduan Lengkap untuk Handle Duplikat & Preserve Data zkteco

---

## 📋 Situasi Saat Ini

### ✅ Berhasil Dilakukan
- ✅ 533 data dari CSV sudah cocok 100% dengan SQL Server (ID sama)
- ✅ Deteksi 4 duplikat di CSV dan 4 duplikat di SQL (orang sama, ID/NIK berbeda)

### ⚠️ Masalah yang Perlu Diselesaikan
1. **Duplikat di SQL Server** - Sudah ada sebelum migrasi
2. **Data zkteco perlu dipreservasi** - Jangan dihapus/diubah
3. **Track berbagai sumber ID** - Excel, SQL original, zkteco
4. **Data integrity** - Consolidate tanpa data loss

---

## 🏗️ Solusi: Multi-Source ID Tracking

### Arsitektur

```
┌─────────────────────────────────────────────────────┐
│           pegawai_lengkap (Main Table)              │
│                                                     │
│  NEW COLUMNS:                                       │
│  • person_unified_id  ← Universal ID untuk orang   │
│  • id_source          ← Sumber: SQL/EXCEL/ZKTECO   │
│  • nik_source         ← Sumber NIK                  │
│  • is_primary         ← Record primary (1) / duplikat│
│  • consolidation_notes ← Notes untuk audit          │
│  • id_peg_excel       ← ID asli dari Excel          │
│  • nik_excel          ← NIK asli dari Excel         │
└─────────────────────────────────────────────────────┘
         │
         ├── Links ke
         │
┌──────────────────────────────────────────────────────┐
│      pegawai_id_mapping (Relationship Table)         │
│                                                      │
│  person_unified_id  ← Universal person ID            │
│  id_peg              ← ID di pegawai_lengkap         │
│  source              ← SQL / EXCEL / ZKTECO          │
│  is_primary          ← Primary record flag           │
│  notes               ← Audit trail                   │
└──────────────────────────────────────────────────────┘
```

### Contoh Data Duplikat SUDIONO

**Sebelum Consolidation:**
```
pegawai_lengkap:
ID: 2000601  | Nama: SUDIONO      | NIK: 3514111701770001
ID: 2020624  | Nama: MUH SUDIONO  | NIK: 3516050406820001  ← Duplikat
```

**Setelah Consolidation:**
```
pegawai_lengkap:
ID: 2000601  | Nama: SUDIONO | person_unified_id: PERSON_SUDIONO_001
             | id_source: SQL | nik_source: SQL | is_primary: 1

ID: 2020624  | Nama: SUDIONO | person_unified_id: PERSON_SUDIONO_001
             | id_source: EXCEL | nik_source: EXCEL | is_primary: 0

pegawai_id_mapping:
person_unified_id: PERSON_SUDIONO_001 | id_peg: 2000601 | source: SQL       | is_primary: 1
person_unified_id: PERSON_SUDIONO_001 | id_peg: 2020624 | source: EXCEL     | is_primary: 0
```

---

## 🚀 Execution Steps

### TAHAP 1: Setup Schema (SAFE - Tidak ada data yang dihapus)

**URL:** `http://localhost/hris/tools/analyze_sql_duplicates_detailed.php`
- Review semua duplikat di SQL
- Pahami struktur data
- Catat mana yang person-sama vs person-berbeda

**Atau langsung buka:** `http://localhost/hris/tools/setup_schema_and_consolidate.php`
1. Klik "**📋 Step 1: Setup Schema**"
2. Klik "**🔴 Execute Setup**"
3. Tunggu hingga selesai

**Apa yang dilakukan:**
- ✅ Menambah kolom baru ke tabel `pegawai_lengkap`
- ✅ Membuat tabel `pegawai_id_mapping` untuk tracking
- ✅ Membuat tabel `pegawai_consolidation_log` untuk audit

**Waktu:** ~5-10 detik | **Risk:** VERY LOW (hanya ALTER TABLE & CREATE TABLE)

---

### TAHAP 2: Analisis Duplikat & Mapping

**URL:** `http://localhost/hris/tools/setup_schema_and_consolidate.php?action=analyze`

Akan menampilkan:
- List semua duplikat
- Proposed unified ID untuk setiap group
- Details (ID & NIK dari masing-masing)

**Output contoh:**
```
Unified ID: PERSON_SUDIONO_001
  - ID: 2000601 | NIK: 3514111701770001
  - ID: 2020624 | NIK: 3516050406820001

Unified ID: PERSON_KHOIRUL_ANAM_001
  - ID: 2060658 | NIK: 3516050906860003
  - ID: 42601327 | NIK: 3516052303920002

Unified ID: PERSON_RONI_001
  - ID: 41709164 | NIK: 3516061806990002
  - ID: 40001380 | NIK: (kosong)

Unified ID: PERSON_DIAN_SANTOSO_001
  - ID: 42607513 | NIK: 3516062803870001
  - ID: 42607516 | NIK: 3516062803870001  ← CRITICAL: Same NIK!
```

---

### TAHAP 3: Consolidate Data

**URL:** `http://localhost/hris/tools/setup_schema_and_consolidate.php?action=consolidate&dry_run=0`

**Apa yang dilakukan:**
1. ✅ Populate `person_unified_id` untuk duplikat records
2. ✅ Set `is_primary` = 1 untuk record pertama
3. ✅ Populate `pegawai_id_mapping` dengan mapping info
4. ✅ Preserve SEMUA data original (tidak ada delete)
5. ✅ Log semua perubahan di `pegawai_consolidation_log`

**Contoh hasil:**
```sql
-- Record primary tetap unchanged
UPDATE pegawai_lengkap SET
  person_unified_id = 'PERSON_SUDIONO_001',
  id_source = 'SQL',
  is_primary = 1
WHERE nik = '2000601';

-- Record duplikat tetap ada, tapi ditandai
UPDATE pegawai_lengkap SET
  person_unified_id = 'PERSON_SUDIONO_001',
  id_source = 'EXCEL',
  id_peg_excel = '2020624',
  is_primary = 0,
  consolidation_notes = 'Duplicate of 2000601 - Same person different ID'
WHERE nik = '2020624';
```

---

### TAHAP 4: Verify & Validate

**URL:** `http://localhost/hris/tools/verify_consolidation.php`

Akan menampilkan:
- ✅ Total records: 533
- ✅ Unique persons (grouped by person_unified_id)
- ✅ Mapping integrity (semua linked dengan benar)
- ✅ zkteco data status (preserved or impacted)

---

## 📊 Data Flow Chart

```
┌─────────────────────────────────────────────────┐
│ CSV (Excel) Data                                │
│ 533 records                                     │
└─────────────────────────────────────────────────┘
                    │
                    ▼
        ┌───────────────────────┐
        │  Verify Duplikat      │ ← analyze_sql_duplicates_detailed.php
        │  4 duplicates found   │
        └───────────────────────┘
                    │
                    ▼
        ┌───────────────────────────────────────┐
        │ Setup Schema                          │ ← setup_schema_and_consolidate.php
        │ - Add new columns                     │   Step 1: Setup Schema
        │ - Create mapping table                │
        └───────────────────────────────────────┘
                    │
                    ▼
        ┌────────────────────────────────────────┐
        │ Analyze & Map Duplicates              │ ← Step 2: Analyze Duplicates
        │ Create unified IDs                     │
        │ PERSON_SUDIONO_001, etc                │
        └────────────────────────────────────────┘
                    │
                    ▼
        ┌────────────────────────────────────────┐
        │ Consolidate                           │ ← Step 3: Consolidate
        │ - Populate person_unified_id          │
        │ - Set is_primary flags                │
        │ - Create mappings                     │
        │ - Log all changes                     │
        └────────────────────────────────────────┘
                    │
                    ▼
        ┌────────────────────────────────────────┐
        │ Verify Result                         │ ← verify_consolidation.php
        │ - Check data integrity                │
        │ - Validate mappings                   │
        │ - Confirm zkteco preserved            │
        └────────────────────────────────────────┘
                    │
                    ▼
        ┌────────────────────────────────────────┐
        │ ✅ Ready for Production                │
        └────────────────────────────────────────┘
```

---

## 🛡️ Safety Measures

### Data Preservation
- ✅ NO data deletion - semua records tetap
- ✅ Original IDs preserved di `id_peg_excel`, `nik_excel`
- ✅ zkteco data untouched
- ✅ Audit trail di `pegawai_consolidation_log`

### Rollback Plan
Jika ada masalah:
```sql
-- Restore original state (before consolidation)
UPDATE pegawai_lengkap SET
  person_unified_id = NULL,
  id_source = NULL,
  nik_source = NULL,
  is_primary = NULL,
  consolidation_notes = NULL;

DELETE FROM pegawai_id_mapping;
DELETE FROM pegawai_consolidation_log;
```

### Verification Queries
```sql
-- Check consolidated data
SELECT person_unified_id, COUNT(*) as record_count
FROM pegawai_lengkap
WHERE person_unified_id IS NOT NULL
GROUP BY person_unified_id
ORDER BY record_count DESC;

-- Check mapping integrity
SELECT COUNT(*) as total_mappings
FROM pegawai_id_mapping;

-- Check audit trail
SELECT * FROM pegawai_consolidation_log
ORDER BY created_at DESC;
```

---

## ⚠️ Special Cases

### DIAN SANTOSO (CRITICAL)
- ID: 42607513 | NIK: 3516062803870001
- ID: 42607516 | NIK: 3516062803870001 ← **SAME NIK!**

**Action:** 
1. Investigate source - apakah data entry error?
2. Verify dengan mesin zkteco
3. Jika benar-benar duplikat → consolidate dengan warning
4. Jika berbeda → fix NIK yang salah

### RONI (Missing Data)
- ID: 41709164 | NIK: 3516061806990002 | Nama: MUHAMMAD RONI
- ID: 40001380 | NIK: (kosong) | Nama: RONI

**Action:**
1. Cari NIK untuk ID 40001380
2. Verify apakah orang yang sama
3. Jika sama → fill NIK dari 41709164

---

## 📞 Questions & Support

### Q: Apakah data zkteco akan terhapus?
**A:** TIDAK! Semua data dipreservasi. zkteco data tetap ada dan linked via `person_unified_id`.

### Q: Berapa lama proses consolidation?
**A:** ~5-10 detik untuk 533 records.

### Q: Bisa rollback?
**A:** YA! Ada SQL rollback script. Tapi lebih baik dry-run dulu.

### Q: Akan affect application lain?
**A:** Tidak, selama app tidak query `person_unified_id` column. Jika ada, perlu update untuk handle NULL values di old records.

---

## 🎯 Next Steps

1. **Jalankan:** `analyze_sql_duplicates_detailed.php` - Review semua duplikat
2. **Setup:** `setup_schema_and_consolidate.php` Step 1 - Setup schema
3. **Analyze:** Step 2 - Analyze & map
4. **Execute:** Step 3 - Consolidate (dry-run = 1 dulu)
5. **Verify:** `verify_consolidation.php` - Validate result
6. **Migrate:** `migrate_csv_to_sqlserver.php` - Lanjutkan migrasi

---

**Status:** 🟡 READY FOR IMPLEMENTATION | **Risk Level:** 🟢 LOW
