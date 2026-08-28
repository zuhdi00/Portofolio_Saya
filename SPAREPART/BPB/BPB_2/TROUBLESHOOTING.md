# Troubleshooting Guide - BPB Keyword Search

## 🔍 Masalah: Keyword 1 dan 2 Tidak Bisa Mencari

### Perbaikan yang Telah Dilakukan

#### 1. **Perbaikan SQL Query Builder**
**Masalah Sebelumnya:**
- WHERE clause dimulai dengan `WHERE 1=1` yang bisa menyebabkan masalah
- Parameter array menggunakan `array_merge()` yang tidak konsisten dengan SQL Server

**Solusi:**
```php
// SEBELUM (SALAH)
$sql = "... WHERE 1=1";
$parameters = array_merge($parameters, [$term, $term, $term]);

// SESUDAH (BENAR)
$sql = "... WHERE h.cNoBPB IS NOT NULL";
for ($i = 0; $i < 3; $i++) {
    $parameters[] = $term;
}
```

#### 2. **Perbaikan Parameter Binding**
**Masalah:**
- SQL Server prepared statements memerlukan parameter yang dipass secara berurutan
- Menggunakan `array_merge()` bisa menyebabkan index parameter tidak match

**Solusi:**
```php
// General search - 6 parameters
if (!empty($searchParams['search'])) {
    $whereConditions[] = "(h.cNoBPB LIKE ? OR h.cNama LIKE ? OR ...)";
    $searchTerm = "%{$searchParams['search']}%";
    for ($i = 0; $i < 6; $i++) {
        $parameters[] = $searchTerm;
    }
}

// Keyword 1 - 3 parameters
if (!empty($searchParams['keyword1'])) {
    $whereConditions[] = "(h.cNoBPB LIKE ? OR d.cKodeBahan LIKE ? OR d.cNoPP LIKE ?)";
    $keyword1Term = "%{$searchParams['keyword1']}%";
    for ($i = 0; $i < 3; $i++) {
        $parameters[] = $keyword1Term;
    }
}

// Keyword 2 - 3 parameters
if (!empty($searchParams['keyword2'])) {
    $whereConditions[] = "(h.cNama LIKE ? OR d.cNama LIKE ? OR h.cKeterangan LIKE ?)";
    $keyword2Term = "%{$searchParams['keyword2']}%";
    for ($i = 0; $i < 3; $i++) {
        $parameters[] = $keyword2Term;
    }
}
```

#### 3. **Penambahan Debug Information**
**Fitur Baru:**
- Debug info di response JSON
- Console logging di browser
- Test page untuk validasi

```javascript
// Browser console akan menampilkan:
console.log('Search Parameters:', {...});
console.log('Response Data:', data);
console.log('Debug Info:', data.debug);
```

## 🧪 Cara Testing

### 1. Menggunakan Test Page

Upload file `test_bpb_search.html` dan buka di browser:

```
http://localhost/test_bpb_search.html
```

Jalankan test cases:
- **Test 1**: Keyword 1 Only (cari "BPB")
- **Test 2**: Keyword 2 Only (cari "PT")
- **Test 3**: Keyword 1 + 2 (cari "BPB" + "PT")
- **Test 4**: General Search (cari "kardus")
- **Test 5**: All Filters Combined

### 2. Manual Testing via Browser Console

Buka browser console (F12) dan jalankan:

```javascript
// Test keyword1
fetch('http://localhost/get_bpb_list.php?keyword1=BPB&limit=5')
  .then(r => r.json())
  .then(d => console.log('Keyword1 Test:', d));

// Test keyword2
fetch('http://localhost/get_bpb_list.php?keyword2=PT&limit=5')
  .then(r => r.json())
  .then(d => console.log('Keyword2 Test:', d));

// Test both
fetch('http://localhost/get_bpb_list.php?keyword1=BPB&keyword2=PT&limit=5')
  .then(r => r.json())
  .then(d => console.log('Both Keywords Test:', d));
```

### 3. Testing via Postman

**Request URL:**
```
GET http://localhost/get_bpb_list.php
```

**Query Parameters:**
```
keyword1: BPB
keyword2: PT
limit: 10
```

**Expected Response:**
```json
{
  "success": true,
  "data": [...],
  "debug": {
    "total_conditions": 2,
    "total_parameters": 6,
    "has_keyword1": true,
    "has_keyword2": true,
    "has_search": false,
    "conditions": [
      "(h.cNoBPB LIKE ? OR d.cKodeBahan LIKE ? OR d.cNoPP LIKE ?)",
      "(h.cNama LIKE ? OR d.cNama LIKE ? OR h.cKeterangan LIKE ?)"
    ]
  }
}
```

## 📊 Interpretasi Debug Info

### Debug Response Structure
```json
{
  "debug": {
    "total_conditions": 2,        // Jumlah WHERE conditions
    "total_parameters": 6,        // Jumlah parameter (keyword1:3 + keyword2:3)
    "has_keyword1": true,        // Keyword1 terdeteksi
    "has_keyword2": true,        // Keyword2 terdeteksi
    "has_search": false,         // General search tidak aktif
    "conditions": [              // SQL conditions yang dijalankan
      "(...)",
      "(...)"
    ]
  }
}
```

### Validasi yang Benar

✅ **Keyword1 aktif:**
```json
{
  "total_conditions": 1,
  "total_parameters": 3,
  "has_keyword1": true,
  "has_keyword2": false
}
```

✅ **Keyword2 aktif:**
```json
{
  "total_conditions": 1,
  "total_parameters": 3,
  "has_keyword1": false,
  "has_keyword2": true
}
```

✅ **Both keywords aktif:**
```json
{
  "total_conditions": 2,
  "total_parameters": 6,
  "has_keyword1": true,
  "has_keyword2": true
}
```

❌ **Keyword tidak terdeteksi:**
```json
{
  "total_conditions": 0,
  "total_parameters": 0,
  "has_keyword1": false,
  "has_keyword2": false
}
```

## 🔧 Troubleshooting Checklist

### Jika Keyword Masih Tidak Bekerja

1. **Cek Browser Console**
   ```
   F12 → Console tab
   ```
   - Lihat "Search Parameters" - apakah keyword terkirim?
   - Lihat "Response Data" - apakah ada error?
   - Lihat "Debug Info" - apakah keyword terdeteksi?

2. **Cek Network Tab**
   ```
   F12 → Network tab → Cari "get_bpb_list.php"
   ```
   - Klik request → Preview/Response
   - Lihat apakah data ada atau error

3. **Cek PHP Error Log**
   ```bash
   # Linux
   tail -f /var/log/apache2/error.log
   
   # Windows (XAMPP)
   C:\xampp\apache\logs\error.log
   ```

4. **Cek SQL Server Connection**
   ```php
   // Test connection di get_bpb_list.php
   $connectionTest = testConnection($serverName, $connectionOptions);
   if (!$connectionTest['success']) {
       // Connection failed
   }
   ```

5. **Validate Database Columns**
   ```sql
   -- Pastikan kolom-kolom ini ada di database
   SELECT TOP 1
       h.cNoBPB,
       h.cNama,
       d.cKodeBahan,
       d.cNama,
       d.cNoPP,
       h.cKeterangan
   FROM tbBPBdtl d
   LEFT JOIN tbBPB h ON d.cNoBPB = h.cNoBPB
   ```

## 🎯 Common Issues & Solutions

### Issue 1: Empty Response
**Symptom:** `data: []` meski ada keyword
**Cause:** Data di database tidak match dengan keyword
**Solution:**
- Gunakan keyword yang pasti ada di database
- Coba keyword yang lebih umum (contoh: single letter)
- Cek case sensitivity

### Issue 2: SQL Error
**Symptom:** `Failed to execute main query`
**Cause:** Parameter count mismatch
**Solution:**
- Pastikan jumlah `?` di SQL = jumlah parameter
- Cek debug info: `total_parameters` harus match dengan jumlah `?`

### Issue 3: Keyword Tidak Terdeteksi
**Symptom:** `has_keyword1: false` padahal sudah diisi
**Cause:** Input form tidak terkirim atau empty string
**Solution:**
- Cek console: apakah `keyword1` ada di Search Parameters?
- Pastikan input field memiliki id yang benar
- Cek apakah ada trim() yang menghapus value

### Issue 4: Connection Timeout
**Symptom:** Request timeout atau long loading
**Cause:** Query terlalu berat atau index tidak optimal
**Solution:**
- Tambahkan WHERE condition untuk date range
- Kurangi limit data
- Buat index di kolom yang sering di-search

## 📈 Performance Tips

1. **Selalu gunakan Date Filter**
   - Default: 30 hari terakhir
   - Mengurangi data yang harus di-scan

2. **Batasi Limit**
   - Gunakan 100-200 untuk performance optimal
   - 1000+ hanya jika diperlukan

3. **Index Database Columns**
   ```sql
   CREATE INDEX idx_bpb_tanggal ON tbBPB(dTanggal);
   CREATE INDEX idx_bpb_nobpb ON tbBPB(cNoBPB);
   CREATE INDEX idx_bpbdtl_nobpb ON tbBPBdtl(cNoBPB);
   CREATE INDEX idx_bpbdtl_kodebahan ON tbBPBdtl(cKodeBahan);
   ```

## 🚀 Next Steps

Setelah perbaikan ini:
1. Test semua keyword combinations
2. Validate dengan data real
3. Remove debug info di production
4. Monitor performance
5. Collect user feedback

---

**Last Updated:** 2024-02-02
**Version:** 1.1.0 (Fixed)
