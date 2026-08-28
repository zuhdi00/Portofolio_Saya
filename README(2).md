# Portofolio Saya — Zuhdi

Repository ini berisi kumpulan project dan tools yang saya buat selama bekerja di bidang **IT / EDP (Electronic Data Processing)**, khususnya untuk mendukung operasional produksi, gudang, dan administrasi di lingkungan manufaktur. Sebagian besar aplikasi dibangun untuk menyelesaikan kebutuhan internal perusahaan: pencatatan stok, pelabelan produk, monitoring mesin, pengelolaan tiket IT, hingga sistem HR.

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
| `LabelSTB`, `Label_supracor`, `LabelCorrWIP` | Pembuatan dan koreksi label produk (STB, Supracor, WIP) |
| `stbtotal`, `stbtotalv1` | Rekap total data STB |
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

- **Backend:** PHP (native)
- **Frontend:** HTML, CSS, JavaScript
- **Database:** MySQL
- **Automasi/Integrasi Hardware:** Python (untuk integrasi timbangan digital)
- **Lainnya:** Laporan berbasis Excel (`.xls`/`.xlsx`), Crystal Report (`.rpt`)

## 🎯 Tujuan Repository

Repository ini digunakan sebagai:
1. **Arsip portofolio** — menunjukkan pengalaman membangun berbagai sistem internal berbasis web untuk kebutuhan produksi dan administrasi perusahaan.
2. **Riwayat pengembangan** — beberapa folder menyimpan iterasi (`v1`, `v2`, `WIP`) sebagai jejak proses pengembangan dan penyempurnaan fitur.

## 🚀 Menjalankan Project Secara Lokal

Karena repo ini berisi banyak project independen, jalankan tiap project secara terpisah sesuai kebutuhannya. Secara umum:

1. **Clone repository**
   ```bash
   git clone https://github.com/zuhdi00/Portofolio_Saya.git
   ```
2. **Pindahkan folder project yang ingin dijalankan** ke direktori server lokal (mis. `htdocs` pada XAMPP/Laragon) — karena sebagian besar project berbasis PHP native.
3. **Siapkan database MySQL** sesuai kebutuhan masing-masing project (skema/kredensial biasanya ada di file koneksi seperti `connection.php` di masing-masing folder).
4. **Untuk project berbasis Python** (mis. `timbanganpython`), pastikan Python dan library pendukungnya (lihat `requirements.txt` bila tersedia di folder tersebut) sudah terpasang.
5. Jalankan Apache/MySQL, lalu akses melalui browser sesuai path folder project, contoh:
   ```
   http://localhost/Portofolio_Saya/<nama_folder_project>/
   ```

## 📌 Catatan

- Beberapa file (`Untitled-1.html`, versi ganda `MCList*.html`, dsb.) merupakan berkas eksperimen/percobaan yang dapat dibersihkan lebih lanjut.
- Pastikan untuk **tidak menyertakan kredensial database sensitif** (username, password) secara publik pada file konfigurasi — gunakan `.env` atau file konfigurasi terpisah yang di-`.gitignore` bila repo akan tetap publik.
- File-file berukuran besar (arsip `.rar`/`.zip`) sebaiknya tidak ikut di-*commit* ke repository; gunakan `.gitignore` atau Git LFS bila memang perlu disertakan.

## 📄 Lisensi

Belum ditentukan. Tambahkan berkas `LICENSE` (mis. MIT License) apabila project ini akan dipublikasikan secara open source.
