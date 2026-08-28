# Dashboard BPB - Sistem Pencarian Bukti Penerimaan Barang

Dashboard modern untuk mencari dan memantau data Bukti Penerimaan Barang (BPB) dengan fitur filter tanggal dan pencarian multi-keyword.

## 📋 Fitur Utama

### 1. **Pencarian Fleksibel**
   - **Pencarian Umum**: Mencari di semua kolom (No BPB, Supplier, Barang, dll)
   - **Keyword 1**: Pencarian spesifik untuk No BPB, Kode Bahan, No OPB
   - **Keyword 2**: Pencarian spesifik untuk Supplier, Nama Barang, Keterangan

### 2. **Filter Tanggal**
   - Filter berdasarkan rentang tanggal
   - Default: 30 hari terakhir
   - Format: dd/mm/yyyy

### 3. **Statistik Real-time**
   - Total Records
   - Jumlah Supplier Unik
   - Jumlah Item Unik
   - Rentang Tanggal

### 4. **Export Excel**
   - Export semua data hasil pencarian
   - Format: .xls
   - Nama file otomatis dengan tanggal

### 5. **Pagination**
   - Navigasi Previous/Next
   - Pilihan jumlah data per halaman (50, 100, 200, 500, 1000)
   - Info halaman dan total data

## 🚀 Instalasi

### Persyaratan Sistem
- PHP 7.4 atau lebih tinggi
- SQL Server (Microsoft SQL Server)
- Extension PHP: `sqlsrv`, `pdo_sqlsrv`
- Web Server: Apache/Nginx/IIS

### Langkah Instalasi

1. **Upload File**
   ```
   BPBList.html           -> Letakkan di folder web root
   get_bpb_list.php       -> Letakkan di folder yang sama
   ```

2. **Konfigurasi Database**
   
   Edit file `get_bpb_list.php`, sesuaikan koneksi database:
   ```php
   $serverName = "spsdmz2";  // Nama server SQL
   $connectionOptions = array(
       "Database" => "dbSopanusa",  // Nama database
       "Uid" => "sa",               // Username
       "PWD" => "supracor",         // Password
       ...
   );
   ```

3. **Konfigurasi URL API**
   
   Edit file `BPBList.html`, sesuaikan URL API:
   ```javascript
   const API_BASE_URL = 'http://localhost'; // Ganti dengan URL server Anda
   ```

4. **Set Permission**
   
   Pastikan file PHP memiliki permission yang sesuai:
   ```bash
   chmod 644 get_bpb_list.php
   chmod 644 BPBList.html
   ```

## 📖 Cara Penggunaan

### 1. Pencarian Dasar
- Buka `BPBList.html` di browser
- Masukkan kata kunci di kolom "Pencarian Umum"
- Klik tombol "🔍 Cari Data"

### 2. Pencarian dengan 2 Keyword
- **Keyword 1**: Masukkan No BPB atau Kode Bahan
- **Keyword 2**: Masukkan Nama Supplier atau Nama Barang
- Kedua keyword akan dicari secara bersamaan (AND)

### 3. Filter Tanggal
- Pilih "Tanggal Dari" dan "Tanggal Sampai"
- Sistem akan menampilkan data dalam rentang tersebut
- Default: 30 hari terakhir

### 4. Kombinasi Filter
Anda bisa mengkombinasikan semua filter:
```
Pencarian Umum: "kardus"
Keyword 1: "BPB2024"
Keyword 2: "PT Indo"
Tanggal Dari: 01/01/2024
Tanggal Sampai: 31/01/2024
```

### 5. Export ke Excel
- Atur filter sesuai kebutuhan
- Klik tombol "📥 Export Excel"
- File akan otomatis terunduh

### 6. Reset Filter
- Klik tombol "🔄 Reset Filter"
- Semua filter akan dikembalikan ke default
- Tanggal akan direset ke 30 hari terakhir

## 🎨 Struktur Database

### Table: tbBPB (Header)
```sql
- cNoBPB       : Nomor BPB
- dTanggal     : Tanggal BPB
- cNama        : Nama Supplier
- cKeterangan  : Keterangan
```

### Table: tbBPBDtl (Detail)
```sql
- cNoBPB       : Nomor BPB (FK)
- cKodeBahan   : Kode Bahan
- cNama        : Nama Barang
- cNoPP        : Nomor OPB
- cUkuran      : Ukuran
- nQtyK        : Jumlah
- cSatK        : Satuan
```

## 🔧 Troubleshooting

### Error: "Database connection failed"
**Solusi:**
1. Periksa koneksi SQL Server
2. Pastikan kredensial database benar
3. Cek firewall tidak memblokir port SQL Server (default: 1433)
4. Pastikan extension `sqlsrv` terinstall di PHP

### Error: "No data found"
**Solusi:**
1. Cek filter tanggal terlalu sempit
2. Pastikan keyword pencarian benar
3. Verifikasi data ada di database

### Data tidak muncul
**Solusi:**
1. Buka Developer Console (F12) di browser
2. Cek tab Network untuk melihat response API
3. Pastikan URL API sudah benar di JavaScript
4. Cek CORS headers di PHP sudah aktif

### Export Excel tidak berfungsi
**Solusi:**
1. Pastikan parameter `export=excel` ter-pass dengan benar
2. Cek browser tidak memblokir download
3. Verifikasi permission file di server

## 📊 Format Response API

### Success Response
```json
{
  "success": true,
  "data": [
    {
      "cNoBPB": "BPB/2024/001",
      "dTanggal": "2024-01-15",
      "cKodeBahan": "BRG001",
      "supplier": "PT Indo Jaya",
      "nama_barang": "Kardus Box",
      "no_opb": "OPB/2024/001",
      "ukuran": "50x40x30",
      "jumlah": 100,
      "satuan": "PCS",
      "cKeterangan": "Order reguler"
    }
  ],
  "stats": {
    "total_suppliers": 10,
    "total_items": 25,
    "date_range": "01/01/2024 - 31/01/2024"
  },
  "pagination": {
    "current_page": 1,
    "total_pages": 5,
    "total_records": 500,
    "records_per_page": 100,
    "offset": 0,
    "has_next": true,
    "has_prev": false
  },
  "message": "Data loaded successfully",
  "timestamp": "2024-02-02 10:30:00"
}
```

### Error Response
```json
{
  "success": false,
  "message": "Database connection failed",
  "timestamp": "2024-02-02 10:30:00",
  "server_info": {
    "server": "spsdmz2",
    "database": "dbSopanusa",
    "php_version": "7.4.0",
    "sqlsrv_loaded": true
  }
}
```

## 🎯 Best Practices

1. **Gunakan Filter Tanggal**
   - Selalu batasi rentang tanggal untuk performa optimal
   - Hindari query tanpa filter tanggal pada database besar

2. **Kombinasi Pencarian**
   - Gunakan pencarian umum untuk eksplorasi
   - Gunakan keyword 1 & 2 untuk pencarian spesifik

3. **Pagination**
   - Mulai dengan 100 data per halaman
   - Tingkatkan hanya jika diperlukan

4. **Export Excel**
   - Export hanya data yang diperlukan
   - Gunakan filter untuk membatasi data

## 🔐 Keamanan

1. **SQL Injection Protection**
   - Semua query menggunakan prepared statements
   - Parameter di-escape otomatis oleh sqlsrv

2. **CORS Headers**
   - Configured untuk production use
   - Sesuaikan `Access-Control-Allow-Origin` sesuai kebutuhan

3. **Error Handling**
   - Error details tidak ditampilkan ke user
   - Logging untuk debugging

## 📞 Support

Jika ada pertanyaan atau masalah:
1. Periksa error log PHP
2. Cek browser console untuk error JavaScript
3. Verifikasi koneksi database
4. Review konfigurasi server

## 📝 Changelog

### Version 1.0.0 (2024-02-02)
- ✨ Initial release
- 🔍 Pencarian umum dan 2 keyword
- 📅 Filter tanggal
- 📊 Statistik real-time
- 📥 Export Excel
- 📄 Pagination
- 🎨 Modern UI/UX

## 📄 License

Proprietary - Internal Use Only

---

**Developed with ❤️ for efficient BPB management**
