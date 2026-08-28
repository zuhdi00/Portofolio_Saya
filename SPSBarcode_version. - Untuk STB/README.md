Aplikasi Scan Barcode Android

👨‍💻 Zuhdi Abdillah Hidayat 

📅 11 April 2025


Tujuan Proyek

=> Membuat aplikasi Android untuk memindai barcode menggunakan kamera.

=> Menampilkan hasil pemindaian dalam halaman terpisah.

=> Mempermudah proses input data melalui pemindaian barcode.

#Tools dan Teknologi#

Android Studio (IDE)

- Java (Bahasa Pemrograman)

- CameraX (untuk akses kamera)

- ML Kit Barcode Scanning (Google ML untuk scan barcode)

- Gradle Kotlin DSL (build system)


Fitur yang Sudah Selesai ✅

📷 Mengakses kamera perangkat.

📦 Deteksi barcode dengan ML Kit.

✅ Menampilkan hasil scan secara real-time.

🔁 Navigasi ke halaman hasil scan.

🔒 Hentikan scanner setelah hasil pertama diterima (menghindari scan berulang).

Tampilan Aplikasi (Screenshots)

🖼️ Halaman Scanner

![WhatsApp Image 2025-04-11 at 07 55 32](https://github.com/user-attachments/assets/f2810e59-10ce-4985-a679-8329fc11e6f7)

🖼️ Halaman Hasil Scan

![WhatsApp Image 2025-04-11 at 07 54 43](https://github.com/user-attachments/assets/60219846-2e83-431b-887f-50becde7bc7d)


Struktur Kode (Singkat)

BarcodeScannerActivity.java

↳ Kamera & proses scan

ScanResultActivity.java

↳ Menampilkan hasil barcode

activity_barcode_scanner.xml

↳ Preview kamera + hasil

activity_scan_result.xml

↳ Teks hasil scan + tombol kembali


Kendala yang Dihadapi

🔧 Permasalahan force close saat awal implementasi.

🧩 Error Cannot resolve symbol saat integrasi awal.

🚫 Navigasi ke halaman hasil yang langsung kembali ke home.

🛠️ Solusi: Validasi layout, perbaikan Intent, kontrol hasScanned.


Rencana Selanjutnya 🚀

✨ Tambahkan fitur scan QR Code (selain barcode biasa).

📝 Simpan hasil scan ke database lokal.

📤 Kirim hasil scan ke server/API.

🎨 Perbaikan desain UI/UX agar lebih user friendly.


Penutup 🛠️

Aplikasi ini diharapkan dapat membantu proses pelacakan dan pendataan barang gudang secara cepat dan efisien.

Terima kasih! 🙏
