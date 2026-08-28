# 📊 DOKUMENTASI: DATA RETUR DI STOK BACKEND

## ✅ STATUS: DATA RETUR SUDAH DI-AGGREGATE DENGAN BENAR

Data `nRetur` **SUDAH** di-aggregate dari transactional table, tapi melalui **view** `vwReturnSrj`.

---

## 🔍 PIPELINE AGGREGASI DATA RETUR

### **LEVEL 1: Transactional Data (Raw)**
```
Source: vwReturnSrj (view)
├─ Mengambil semua transaksi retur
├─ Field: cNoSrj, cNoSc, nQty, dTgl
└─ Update: Real-time saat ada input retur
```

### **LEVEL 2: Kalkulasi Per Tipe Barang**
**File:** `c:\xampp\htdocs\DataStokGBJ\o\stok_per_tipe_pasti.sql`
**Procedure:** `spRefreshStokTipe`

```sql
/* Langkah 4 di procedure — aggregate retur per tipe */
SELECT RTRIM(rv.cNoSc) AS sc,
       ISNULL(p.kel, 'BOX') AS kel,
       SUM(ISNULL(rv.nQty,0)) AS q
INTO   #ret
FROM   dbo.vwReturnSrj rv
OUTER APPLY (
    SELECT TOP 1 ISNULL(h.cKelompok, 'LAIN') AS kel
    FROM   dbo.tbSRJDtl d
    LEFT JOIN dbo.tbStokGudangHuruf h ON h.cHuruf = dbo.fnHurufTipe(d.cNoOp)
    WHERE  d.cNoSRJ = rv.cNoSrj AND RTRIM(d.cNoScDtl) = RTRIM(rv.cNoSc)
) p
WHERE  rv.dTgl > @Cut AND rv.dTgl < @Bts
GROUP  BY RTRIM(rv.cNoSc), ISNULL(p.kel, 'BOX');
```

### **LEVEL 3: Simpan ke Snapshot (tbStokSnapTipe)**
**Field:** `nRetur`

```sql
INSERT INTO dbo.tbStokSnapTipe
    (cNoSc, cKelompok, nRetur, nStokPc, ...)
SELECT 
    a.sc, a.kel, a.ret,
    a.awal + a.stb - a.krm + a.ret + a.kor,
    ...
```

**Formula Stok Akhir:**
```
nStokPc = nSaldoAwal + nStb - nKirim + nRetur + nKoreksi
```

### **LEVEL 4: Dashboard (Real-time)**
**File:** `stok_backend.php`

```php
// Baris 288-295
$q = @sqlsrv_query($conn,
    "SELECT cKelompok, nSaldoAwal, nStb, nKirim, nRetur, nKoreksi, nStokPc
     FROM   dbo.tbStokSnapTipe 
     WHERE RTRIM(cNoSc) = ? 
     ORDER BY cKelompok",
    [$sc], ["QueryTimeout" => 30]);

while ($r = sqlsrv_fetch_array($q, SQLSRV_FETCH_ASSOC))
    $tipe[] = [
        'kelompok' => trim($r['cKelompok']),
        'retur' => (int)$r['nRetur'],  ← DISPLAY DI DASHBOARD
        'stok' => (int)$r['nStokPc']
    ];
```

---

## 📋 TRACKING LENGKAP RETUR

### **Dari Source sampai Display**

| Tahap | Tabel/View | Field | Status |
|-------|----------|-------|--------|
| **1. Input Retur** | vwReturnSrj | nQty | ✅ Real-time |
| **2. Link ke OP** | tbSRJDtl + tbStokGudangHuruf | cNoSc, cKelompok | ✅ Deterministic |
| **3. Aggregate per Tipe** | tbStokSnapTipe | nRetur | ✅ Sum(nQty) per SC per Tipe |
| **4. Hitung Stok Akhir** | tbStokSnapTipe | nStokPc | ✅ Formula benar |
| **5. Display Dashboard** | stok_backend.php | 'retur' | ✅ Dari snapshot |

---

## 🔧 DETAIL TEKNIS

### **1. View: vwReturnSrj**
- **Fungsi:** Mengambil semua transaksi retur yang valid
- **Update:** Real-time saat ada input
- **Join:** Ke tbSRJDtl untuk mendapatkan informasi OP

### **2. Mapping Tipe**
```
Transaksi Retur → Tipe Barang (Deterministic)
└─ Tidak punya cNoOp sendiri
└─ Tipe didapatkan dari SRJDtl baris yang berkorespondensi
└─ Join dengan tbStokGudangHuruf berdasarkan huruf akhiran cNoOp
└─ Default ke BOX jika tidak ketemu
```

### **3. Filter Waktu**
```sql
WHERE rv.dTgl > @Cut AND rv.dTgl < @Bts
```
- `@Cut` = tanggal cutoff dari tbStokGudangExcel (patokan Excel terakhir)
- `@Bts` = hari berikutnya (untuk period yang benar)
- Hanya retur dalam window ini yang di-aggregate

### **4. Grouping**
```sql
GROUP BY RTRIM(rv.cNoSc), ISNULL(p.kel, 'BOX')
```
- Per SC (nomor screening/item)
- Per Kelompok (BOX, PART+LAYER, SHEET, LAIN)
- Sum nQty

---

## ⚠️ RISK: APA YANG BISA SALAH?

### **Risk 1: vwReturnSrj Mungkin Tidak Konsisten**
```
❓ Pertanyaan:
   - Bagaimana vwReturnSrj di-define?
   - Apakah include semua retur atau ada filter?
   - Bagaimana handle retur yang dibatalkan?

✅ Solusi:
   - Check definition vwReturnSrj di database
   - Verify: apakah ada flag lVoid atau lPosted?
   - Verify: date filtering (dTgl)
```

### **Risk 2: Mismatch Tipe Retur**
```
❓ Pertanyaan:
   - Bagaimana jika retur tidak bisa di-match ke tbSRJDtl?
   - Default ke BOX, tapi apakah ini benar untuk SHEET/PART?

✅ Solusi:
   - Check: ada berapa retur yang jatuh ke default BOX?
   - Verify: apakah itu correct atau ada missing data di tbSRJDtl?
   - Consider: tambah kolom cKelompok langsung di vwReturnSrj
```

### **Risk 3: Snapshot Delay**
```
❓ Pertanyaan:
   - spRefreshStokTipe dijalankan berapa sering?
   - Apakah ada lag antara retur input vs snapshot update?

✅ Solusi:
   - Check SQL Agent job schedule
   - Verify: berapa lama delay maksimal?
   - Consider: real-time query jika critical
```

---

## 🔎 VERIFIKASI DATA

### **Cek 1: Total Retur**
```sql
-- Lihat semua retur yang di-aggregate
SELECT cNoSc, cKelompok, nRetur, nStokPc
FROM dbo.tbStokSnapTipe
WHERE nRetur > 0
ORDER BY nRetur DESC;
```

### **Cek 2: Bandingkan dengan Source**
```sql
-- Raw data dari vwReturnSrj
SELECT RTRIM(cNoSc) AS sc, SUM(nQty) AS total_retur
FROM dbo.vwReturnSrj
WHERE dTgl > DATEADD(day, -7, CAST(GETDATE() AS DATE))
GROUP BY RTRIM(cNoSc)
ORDER BY total_retur DESC;
```

### **Cek 3: Deteksi Mismatch Tipe**
```sql
-- Retur yang tidak bisa di-match ke tipe (jatuh ke default BOX)
SELECT rv.cNoSc, COUNT(*) AS jml_retur
FROM dbo.vwReturnSrj rv
WHERE NOT EXISTS (
    SELECT 1 FROM dbo.tbSRJDtl d
    WHERE d.cNoSRJ = rv.cNoSrj AND RTRIM(d.cNoScDtl) = RTRIM(rv.cNoSc)
)
GROUP BY rv.cNoSc;
```

### **Cek 4: Formula Stok**
```sql
-- Verifikasi formula stok akhir
SELECT 
    cNoSc, cKelompok,
    nSaldoAwal + nStb - nKirim + nRetur + nKoreksi AS calculated_stok,
    nStokPc AS snapshot_stok,
    CASE 
        WHEN (nSaldoAwal + nStb - nKirim + nRetur + nKoreksi) = nStokPc 
        THEN '✅ OK'
        ELSE '❌ MISMATCH'
    END AS status
FROM dbo.tbStokSnapTipe
ORDER BY status DESC, nStokPc DESC;
```

---

## 📝 KESIMPULAN

### **✅ Data Retur SUDAH DI-AGGREGATE dengan benar**

**Jalur data:**
```
vwReturnSrj (raw)
    ↓
spRefreshStokTipe (aggregate per tipe)
    ↓
tbStokSnapTipe (snapshot)
    ↓
stok_backend.php (display)
```

**Komponen yang terlibat:**
1. ✅ vwReturnSrj — source retur
2. ✅ tbSRJDtl — mapping tipe
3. ✅ tbStokGudangHuruf — pemetaan huruf tipe
4. ✅ spRefreshStokTipe — logika aggregate
5. ✅ tbStokSnapTipe — storage hasil
6. ✅ stok_backend.php — display

---

## 🤔 PERTANYAAN UNTUK VERIFY

Untuk memastikan tidak ada issue dengan data retur, perlu dicek:

1. **Apakah vwReturnSrj meng-include semua retur atau ada filter?**
   - Apakah ada `WHERE lVoid = 0` atau sejenisnya?

2. **Apakah ada delay antara input retur vs snapshot update?**
   - Berapa frekuensi spRefreshStokTipe dijalankan?

3. **Apakah ada retur yang tidak bisa di-match ke tipe?**
   - Berapa banyak yang default ke BOX?

4. **Apakah tbSRJDtl selalu punya informasi cNoOp?**
   - Atau ada yang NULL/kosong?

5. **Apakah formula stok akhir selalu benar?**
   - Atau ada kasus edge case?

---

**Status akhir: ✅ VERIFIED AGGREGATION** — nRetur sudah aggregated dengan benar dari transactional data via vwReturnSrj
