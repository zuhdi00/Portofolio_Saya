# Pasang: Kartu Pegawai & Profil Kehadiran (S-CORE)

## File
| File | Status |
|---|---|
| pegawai/kartu.php | BARU - grid kartu pegawai, klik -> profil |
| pegawai/profil.php | BARU - dashboard data diri + 6 kategori presensi |
| pegawai/_profil_helper.php | BARU - fungsi bersama (awalan pf_) + pemuat sidebar aman |
| pegawai/_sidebar_kosong.php | BARU - pesan bila sidebar tidak ditemukan |
| pegawai/cek_file_profil.php | BARU - cek file di server (hapus setelah OK) |
| assets/css/profil-pegawai.css | BARU - gaya halaman |
| pegawai/index.php | DIUBAH - tombol "Tampilan Kartu", nama/foto & tombol profil bisa diklik |
| template/sidebar.php | DIUBAH - menu "Kartu Pegawai" & "Kehadiran Saya" |

## Langkah
1. Backup `pegawai/index.php` dan `template/sidebar.php` yang lama.
2. Salin isi folder `s-core/` ini ke `htdocs/s-core/` (timpa).
3. Buka `/s-core/pegawai/cek_file_profil.php` -> semua kolom harus "Ya".
4. Buka `/s-core/pegawai/kartu.php` (login sebagai HR / Admin IT).

## Jika muncul "Failed opening '../template/sidebar.php'"
File `template/sidebar.php` tidak ada di server. Salin ulang dari zip ini ke `C:\xampp\htdocs\s-core\template\`.
Halaman profil & kartu tetap tampil (memakai sidebar_*.php terbaru), tapi halaman lama S-CORE akan error sampai file dikembalikan.

Tidak ada perubahan database. Butuh SQL Server 2012+ (OFFSET/FETCH) dan PHP 7.4+.

## Aturan hitung
- Sumber: dbo.absensi.status (hadir/terlambat/izin/sakit/cuti/alpha).
- HR & Admin IT: terlambat = status resmi (sudah toleransi shift).
- Peran lain: terlambat ketat = tap masuk > jam_mulai shift (sama dgn Dashboard Presensi).
- Alfa = status 'alpha' yang diinput HR. Hari tanpa catatan = abu-abu di kalender.

## Hak akses
- Izin `pegawai_lihat` (admin_it, hr, atasan, admin_divisi): semua kartu & profil.
- Peran `user`: hanya profil sendiri via menu "Kehadiran Saya" (butuh pegawai_id di hris_users).
- Tombol Edit hanya muncul untuk izin `pegawai_edit`; tombol Rekap untuk `absensi_rekap`.
