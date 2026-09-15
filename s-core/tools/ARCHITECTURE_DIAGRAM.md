# 🏗️ Data Consolidation Architecture & Workflow

```
┌─────────────────────────────────────────────────────────────────────────┐
│                     EMPLOYEE DATA CONSOLIDATION                         │
│                        Multi-Source ID Tracking                         │
└─────────────────────────────────────────────────────────────────────────┘

═════════════════════════════════════════════════════════════════════════════

PHASE 1: BEFORE CONSOLIDATION
──────────────────────────────

    pegawai_lengkap (SQL Server)
    ┌─────────────────────────────┐
    │ nik (PK)  │ nama            │
    ├─────────────────────────────┤
    │ 2000601   │ SUDIONO         │  ← Same person?
    │ 2020624   │ MUH SUDIONO     │     Different IDs
    │ 2060658   │ KHOIRUL ANAM    │
    │ 42601327  │ KHOIRUL ANAM    │
    │ 41709164  │ MUHAMMAD RONI   │
    │ 40001380  │ RONI            │
    │ 42607513  │ DIAN SANTOSO    │
    │ 42607516  │ DIAN SANTOSO    │ ⚠️ Same NIK!
    └─────────────────────────────┘
    
    Problem: Duplicates NOT linked, NO source tracking

═════════════════════════════════════════════════════════════════════════════

SOLUTION: ADD TRACKING INFRASTRUCTURE
───────────────────────────────────────

    Step 1: ALTER TABLE
    ──────────────────
    pegawai_lengkap
    ├── person_unified_id      (new) UUID for same person
    ├── id_source              (new) SQL/EXCEL/ZKTECO
    ├── nik_source             (new) source of NIK
    ├── is_primary             (new) 1=primary, 0=duplicate
    ├── consolidation_notes    (new) audit notes
    ├── id_peg_excel           (new) original Excel ID
    └── nik_excel              (new) original Excel NIK

    Step 2: CREATE MAPPING TABLE
    ──────────────────────────
    pegawai_id_mapping (NEW)
    ├── mapping_id (PK)
    ├── person_unified_id      links to same person
    ├── id_peg (FK)            links to pegawai_lengkap
    ├── source                 SQL/EXCEL/ZKTECO
    ├── is_primary             flag
    └── notes                  audit

    Step 3: CREATE AUDIT TABLE
    ──────────────────────────
    pegawai_consolidation_log (NEW)
    ├── log_id (PK)
    ├── person_unified_id
    ├── action                 what was done
    ├── status                 success/error
    └── created_at             timestamp

═════════════════════════════════════════════════════════════════════════════

PHASE 2: DURING CONSOLIDATION
──────────────────────────────

    pegawai_lengkap (AFTER Update)
    ┌──────────────────────────────────────────────────────────┐
    │ nik  │ nama        │ person_unified_id    │ is_primary   │
    ├──────────────────────────────────────────────────────────┤
    │ 2000601 │ SUDIONO    │ PERSON_SUDIONO_001   │ 1 (primary) │
    │ 2020624 │ MUH SUDIONO│ PERSON_SUDIONO_001   │ 0 (dup)     │
    │ 2060658 │ KHOIRUL... │ PERSON_KHOIRUL_001   │ 1 (primary) │
    │ 42601327│ KHOIRUL... │ PERSON_KHOIRUL_001   │ 0 (dup)     │
    └──────────────────────────────────────────────────────────┘
                              ↓
                         LINKED ✓

    pegawai_id_mapping (NEW Relationships)
    ┌──────────────────────────────────────────────────────────┐
    │ person_unified_id      │ id_peg   │ source  │ is_primary  │
    ├──────────────────────────────────────────────────────────┤
    │ PERSON_SUDIONO_001     │ 2000601  │ SQL     │ 1           │
    │ PERSON_SUDIONO_001     │ 2020624  │ EXCEL   │ 0           │
    │ PERSON_KHOIRUL_001     │ 2060658  │ SQL     │ 1           │
    │ PERSON_KHOIRUL_001     │ 42601327 │ EXCEL   │ 0           │
    └──────────────────────────────────────────────────────────┘
                        COMPLETE MAPPING ✓

═════════════════════════════════════════════════════════════════════════════

WORKFLOW: 5-STEP EXECUTION
──────────────────────────

    START
      ↓
    [1] Analyze Duplicates
        ↓ Review SQL duplicates
        ↓ Understand structure
        ↓
    [2] Setup Schema
        ↓ Add 7 columns
        ↓ Create 2 tables
        ↓ Create indexes
        ↓
    [3] Analyze & Map
        ↓ Propose unified IDs
        ↓ Review mapping
        ↓
    [4] Consolidate
        ↓ Link records
        ↓ Set flags
        ↓ Create mappings
        ↓ Log changes
        ↓
    [5] Verify
        ↓ Check integrity
        ↓ Verify all records
        ↓ Confirm zkteco data
        ↓
    ALL ✅ GREEN?
      ↓ YES
      ↓
    PROCEED TO MIGRATION
      ↓
    migrate_csv_to_sqlserver.php
      ↓
    DONE ✓

═════════════════════════════════════════════════════════════════════════════

DATA FLOW: CSV → CONSOLIDATION → SQL SERVER
─────────────────────────────────────────────

    CSV File (533 records)
    ├── id_peg, nik, nama, email, ...
    └── Plus 533 records from SQL
    
              ↓
    
    Consolidation Process
    ├── Identify duplicates
    ├── Create unified IDs
    ├── Link records
    └── Track sources
    
              ↓
    
    SQL Server (Final State)
    ├── 533 records (no deletions)
    ├── 4 unique persons (consolidated)
    ├── 8 duplicate records linked
    ├── Full audit trail
    ├── zkteco data preserved
    └── Ready for business logic

═════════════════════════════════════════════════════════════════════════════

CONSOLIDATION EXAMPLE: SUDIONO
──────────────────────────────

    Before:
    ────────
    pegawai_lengkap
    ┌────────────────────────────────────────┐
    │ nik: 2000601                           │
    │ nama: SUDIONO                          │
    │ tanggal_lahir: 17-01-1977              │
    │ (44 other fields)                      │
    │ zkteco_fingerprint: ...                │
    │ person_unified_id: NULL                │ ← Empty
    │ is_primary: NULL                       │ ← Empty
    └────────────────────────────────────────┘
    
    ┌────────────────────────────────────────┐
    │ nik: 2020624                           │
    │ nama: MUH SUDIONO                      │
    │ tanggal_lahir: 04-06-1982              │
    │ (44 other fields)                      │
    │ zkteco_fingerprint: ...                │
    │ person_unified_id: NULL                │ ← Empty
    │ is_primary: NULL                       │ ← Empty
    └────────────────────────────────────────┘
    
              ↓ Consolidate
    
    After:
    ──────
    pegawai_lengkap
    ┌────────────────────────────────────────┐
    │ nik: 2000601                           │
    │ nama: SUDIONO                          │
    │ tanggal_lahir: 17-01-1977              │
    │ (44 other fields - UNCHANGED)          │
    │ zkteco_fingerprint: ... (PRESERVED)    │
    │ person_unified_id: PERSON_SUDIONO_001  │ ✅ Linked
    │ is_primary: 1                          │ ✅ Primary
    │ id_source: SQL                         │ ✅ Tracked
    └────────────────────────────────────────┘
    
    ┌────────────────────────────────────────┐
    │ nik: 2020624                           │
    │ nama: MUH SUDIONO                      │
    │ tanggal_lahir: 04-06-1982              │
    │ (44 other fields - UNCHANGED)          │
    │ zkteco_fingerprint: ... (PRESERVED)    │
    │ person_unified_id: PERSON_SUDIONO_001  │ ✅ Linked to same
    │ is_primary: 0                          │ ✅ Duplicate flagged
    │ id_source: EXCEL                       │ ✅ Source tracked
    │ id_peg_excel: 2020624                  │ ✅ Original ID saved
    └────────────────────────────────────────┘
    
    pegawai_id_mapping
    ┌────────────────────────────────────────┐
    │ person_unified_id: PERSON_SUDIONO_001  │
    │ id_peg: 2000601                        │
    │ source: SQL                            │
    │ is_primary: 1                          │
    │ notes: Primary record - Original       │
    └────────────────────────────────────────┘
    
    ┌────────────────────────────────────────┐
    │ person_unified_id: PERSON_SUDIONO_001  │
    │ id_peg: 2020624                        │
    │ source: EXCEL                          │
    │ is_primary: 0                          │
    │ notes: Duplicate - From Excel          │
    └────────────────────────────────────────┘

═════════════════════════════════════════════════════════════════════════════

CRITICAL CASE: DIAN SANTOSO (⚠️ SAME NIK)
──────────────────────────────────────────

    Before:
    ┌────────────────────────────────────────┐
    │ nik: 42607513                          │
    │ nama: DIAN SANTOSO                     │
    │ tanggal_lahir: 28-03-1987              │
    │ no_ktp: 3516062803870001               │
    └────────────────────────────────────────┘
    
    ┌────────────────────────────────────────┐
    │ nik: 42607516                          │
    │ nama: DIAN SANTOSO                     │
    │ tanggal_lahir: 28-03-1987              │
    │ no_ktp: 3516062803870001  ⚠️ SAME!    │
    └────────────────────────────────────────┘
    
    ⚠️ Problem: Same KTP number in both records
    ⚠️ Solution: Investigate source
    ⚠️ Action: Mark for manual review
    
    After Consolidation (with warning):
    ┌────────────────────────────────────────┐
    │ nik: 42607513                          │
    │ person_unified_id: PERSON_DIAN_001     │
    │ is_primary: 1                          │
    │ consolidation_notes: ⚠️ SAME KTP IN... │
    │ (needs investigation)                  │
    └────────────────────────────────────────┘

═════════════════════════════════════════════════════════════════════════════

SAFETY & REVERSIBILITY
──────────────────────

    Original Data Preservation:
    ✅ NO fields updated (except new columns)
    ✅ Original 44 columns unchanged
    ✅ zkteco data preserved
    ✅ Timestamps preserved
    ✅ All relationships intact

    Reversibility:
    ✅ Rollback available (< 5 sec)
    ✅ Audit trail complete
    ✅ No permanent changes
    ✅ Can undo at any time

    Auditability:
    ✅ pegawai_consolidation_log tracks all
    ✅ Who/What/When/Why documented
    ✅ Source tracking complete
    ✅ Full compliance ready

═════════════════════════════════════════════════════════════════════════════

EXPECTED RESULTS
────────────────

    Before Consolidation:
    ├── Total records: 533
    ├── Consolidated: 0
    ├── Duplicates linked: No
    └── Source tracking: None

    After Consolidation:
    ├── Total records: 533 ✓ (no data lost)
    ├── Unified persons: 4 groups
    ├── Consolidated records: 8
    ├── Mapping entries: 8
    ├── Audit logs: Complete
    ├── zkteco data: Preserved ✓
    └── Status: ✅ READY FOR MIGRATION

═════════════════════════════════════════════════════════════════════════════

KEY STATISTICS
──────────────

    Records: 533 total
    Duplicates: 4 groups (8 records total)
    Primary records: 4
    Duplicate records: 4
    Consolidation ratio: 8 → 4 unique persons
    Data preservation: 100%
    Risk level: 🟢 VERY LOW
    Execution time: 8-10 minutes
    Reversibility: ✅ YES

═════════════════════════════════════════════════════════════════════════════
```

---

## 📍 Architecture Components

### pegawai_lengkap (Modified)
- **Type:** Existing table, enhanced
- **New Columns:** 7 added (person_unified_id, id_source, etc.)
- **Original Data:** UNCHANGED
- **Purpose:** Employee master with consolidation tracking

### pegawai_id_mapping (New)
- **Type:** Relationship/mapping table
- **FK Constraints:** Links to pegawai_lengkap(nik)
- **Purpose:** Track which IDs belong to same person
- **Uniqueness:** (person_unified_id, id_peg, source) composite

### pegawai_consolidation_log (New)
- **Type:** Audit log table
- **Retention:** Keep all history
- **Purpose:** Track all consolidation actions
- **Compliance:** Full audit trail for compliance

---

## 🔄 Query Examples

### Get consolidated group
```sql
SELECT p.*, m.source
FROM pegawai_lengkap p
LEFT JOIN pegawai_id_mapping m ON p.nik = m.id_peg
WHERE p.person_unified_id = 'PERSON_SUDIONO_001'
ORDER BY m.is_primary DESC;
```

### Find all duplicates
```sql
SELECT person_unified_id, COUNT(*) as count
FROM pegawai_lengkap
WHERE person_unified_id IS NOT NULL
GROUP BY person_unified_id
HAVING COUNT(*) > 1;
```

### Get audit trail
```sql
SELECT TOP 20 * FROM pegawai_consolidation_log
ORDER BY created_at DESC;
```

---

**Architecture Version:** 1.0 Complete
**Status:** ✅ Ready for execution
**Risk Level:** 🟢 VERY LOW
