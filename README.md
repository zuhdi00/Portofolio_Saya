# Portofolio Saya — Zuhdi Abdillah Hidayat

Repository ini berisi kumpulan project dan tools yang saya buat selama bekerja di bidang **IT / EDP (Electronic Data Processing)**, khususnya untuk mendukung operasional produksi, gudang, dan administrasi di lingkungan manufaktur. Sebagian besar aplikasi dibangun untuk menyelesaikan kebutuhan internal perusahaan: pencatatan stok, pelabelan produk, monitoring mesin, pengelolaan tiket IT, hingga sistem HR dan aplikasi mobile scanning barcode.

> ⚠️ **Catatan:** Repo ini adalah kumpulan dokumentasi portofolio pribadi. Beberapa project berisi banyak versi/iterasi (`v1`, `v2`, `WIP`, dsb.) yang merupakan riwayat pengembangan, bukan seluruhnya siap pakai di production.

## 📂 Daftar Project

| Folder | Deskripsi Singkat |
|---|---|
| `hris` | Sistem informasi HR (absensi, kontrak pegawai, penggajian, rekrutmen, dll.) |
| `dashboard`, `dashboardMC` | Dashboard monitoring, termasuk status/performa mesin produksi |
| `trackMC` | Pelacakan (tracking) status/aktivitas mesin |
| `ticketing` | Sistem ticketing untuk pelaporan dan penanganan masalah IT/maintenance |
| `DataStokGBJ`, `GBJstok` | Pencatatan dan pengelolaan stok Gudang Barang Jadi |
| `SPAREPART` | Manajemen data sparepart mesin |
| `SCList` | Daftar/rekap stock card |
| `LabelSTB`, `LabelSTB-v1`, `Label_supracor`, `LabelCorrWIP` | Pembuatan dan koreksi label produk (STB beserta versi iterasinya, Supracor, WIP) |
| `LabelQC` | Modul label untuk proses Quality Control (QC) |
| `stbtotal`, `stbtotalv1` | Rekap total data STB |
| **`SPSBarcode_version. - Untuk STB`** | Aplikasi **Android (Kotlin/Java)** untuk scan barcode STB — dilengkapi backend PHP (`api_get_data.php`, `api_update_qty.php`, `api_update_rak.php`, dll.) dan modul `api-redirector` (Spring Boot) sebagai proxy API |
| **`SPSBarcode_version. - Untuk WIP`** | Varian aplikasi Android scan barcode untuk proses **WIP (Work in Process)**, dengan struktur project serupa versi STB |
| `TIMBANGANCORR`, `timbanganpython` | Koreksi data timbangan & integrasi timbangan digital menggunakan Python |
| `OrderRecapDaily` | Rekap pesanan/order harian |
| `intake`, `intake-v1`, `intake-v2`, `intake_op`, `intake(publish)` | Sistem input/pencatatan data intake produksi (beberapa versi iterasi) |
| `realisasi`, `realisasi_op` | Pencatatan realisasi produksi/operasional |
| `WarnaEdit` | Tools untuk pengeditan data warna produk |
| `BukaNopol` | Tools terkait data nomor polisi/kendaraan |
| `AddRSJ` | Tools penambahan data (modul internal) |
| `image-search` | Aplikasi pencarian gambar |
| `allWeb`, `allWeb2` | Kumpulan halaman/aset web pendukung |
| `EDP` | Berkas-berkas terkait departemen EDP |

Selain folder di atas, terdapat juga berbagai file pendukung berdiri sendiri di root repo, seperti:
- Modul PHP (`api_add_stb.php`, `api_approve_stb*.php`, `API_Search_MC.php`, dll.) — endpoint API untuk proses approval dan pencarian data
- Laporan & rekap (`Laporan*.html`, `PJS_Report_*.xls`, `Rekap_Servis_Printer_EDP_SPS.xlsx`, `ProductionOrders.xlsx`)
- Dokumentasi proses (`Flowchart_STB_Label.html`, `Flowchart_TSC-SC-OP.png`, `alur_hris_source_code.png`, `IKLabelSTB.html`, `SOPSTB.html`, `Konfigurasimikrotik.html`, `SpesifikasiPcServer.html`)
- Berbagai versi daftar MC (`MCList*.html`) sebagai riwayat pengembangan fitur pencarian/list data mesin/kartu

## 🛠️ Teknologi yang Digunakan

- **Backend:** PHP (native), Java Spring Boot (modul `api-redirector`)
- **Mobile:** Android native — Kotlin & Java (Gradle Kotlin DSL)
- **Frontend:** HTML, CSS, JavaScript
- **Database:** MySQL
- **Automasi/Integrasi Hardware:** Python (untuk integrasi timbangan digital)
- **Lainnya:** Laporan berbasis Excel (`.xls`/`.xlsx`), Crystal Report (`.rpt`)

## 🎯 Tujuan Repository

Repository ini digunakan sebagai:
1. **Arsip portofolio** — menunjukkan pengalaman membangun berbagai sistem internal berbasis web dan mobile untuk kebutuhan produksi dan administrasi perusahaan.
2. **Riwayat pengembangan** — beberapa folder menyimpan iterasi (`v1`, `v2`, `WIP`) sebagai jejak proses pengembangan dan penyempurnaan fitur.

## 🚀 Menjalankan Project Secara Lokal

Karena repo ini berisi banyak project independen, jalankan tiap project secara terpisah sesuai kebutuhannya.

### Project berbasis PHP (mayoritas folder)

1. **Clone repository**
   ```bash
   git clone https://github.com/zuhdi00/Portofolio_Saya.git
   ```
2. **Pindahkan folder project yang ingin dijalankan** ke direktori server lokal (mis. `htdocs` pada XAMPP/Laragon).
3. **Siapkan database MySQL** sesuai kebutuhan masing-masing project (skema/kredensial biasanya ada di file koneksi seperti `connection.php` di masing-masing folder).
4. Jalankan Apache/MySQL, lalu akses melalui browser sesuai path folder project, contoh:
   ```
   http://localhost/Portofolio_Saya/<nama_folder_project>/
   ```

### Project Android (`SPSBarcode_version. - Untuk STB` / `- Untuk WIP`)

1. Buka folder project di **Android Studio**.
2. Sinkronkan Gradle (project menggunakan `build.gradle.kts` dan Kotlin DSL).
3. Sesuaikan endpoint API (mis. `ApiClient.java`/`ApiService.java`) agar mengarah ke backend PHP yang sesuai.
4. Jalankan di emulator atau perangkat Android fisik.
5. Modul `api-redirector` (Spring Boot) bisa dijalankan terpisah sebagai proxy API bila diperlukan — jalankan dengan `./gradlew bootRun` atau melalui IDE Java/Kotlin.

### Project Python (`timbanganpython`)

Pastikan Python dan library pendukungnya (lihat `requirements.txt` bila tersedia di folder tersebut) sudah terpasang, lalu jalankan skrip sesuai dokumentasi masing-masing.

## 📌 Catatan

- Beberapa file (`Untitled-1.html`, versi ganda `MCList*.html`, dsb.) merupakan berkas eksperimen/percobaan yang dapat dibersihkan lebih lanjut.
- Folder `SPSBarcode_version. - Untuk STB` dan `- Untuk WIP` masih menyertakan beberapa arsip `.zip` riwayat build — untuk repo yang lebih ringkas, arsip ini bisa dihapus dan cukup diandalkan `.gitignore` untuk mencegahnya ter-*commit* ulang.
- Pastikan untuk **tidak menyertakan kredensial database sensitif** (username, password) secara publik pada file konfigurasi — gunakan `.env` atau file konfigurasi terpisah yang di-`.gitignore` bila repo akan tetap publik.

## 📄 Lisensi

Belum ditentukan. Tambahkan berkas `LICENSE` (mis. MIT License) apabila project ini akan dipublikasikan secara open source.
