# Setup Otomasi ZKTeco → dbHR (Task Scheduler)

Dua tugas berjalan berurutan tiap 15 menit:

```
[Tugas 1] salin_mdb_zkteco.bat        -> salin MDB dari \\spsdmz ke C:\zkteco_data
[Tugas 2] jalankan_sync_zkteco.bat    -> impor tap + proses jadi absensi
```

Tugas 2 diberi jeda 3 menit setelah Tugas 1 supaya file MDB selesai tersalin dulu.

---

## Persiapan

1. Pastikan folder `C:\zkteco_data` ada (tempat salinan MDB + file log).
2. Letakkan `jalankan_sync_zkteco.bat` di `C:\xampp\htdocs\hris\presensi\`.
3. Buka isinya, cek `set PHP=` dan `set DIR=` sudah sesuai.

---

## Tugas 1 — Salin MDB

1. Buka **Task Scheduler** (ketik di Start menu).
2. Klik **Create Task** (bukan Basic Task).
3. Tab **General**:
   - Name: `ZKTeco 1 - Salin MDB`
   - Pilih **Run whether user is logged on or not**
   - Centang **Run with highest privileges**
   - Gunakan akun yang punya hak baca ke `\\spsdmz\gg$` (lihat catatan akun di bawah)
4. Tab **Triggers** → New:
   - Begin: **On a schedule** → **Daily**
   - **Repeat task every: 15 minutes**, for a duration of: **Indefinitely**
   - Enabled ✓
5. Tab **Actions** → New:
   - Action: **Start a program**
   - Program: `C:\xampp\htdocs\hris\presensi\salin_mdb_zkteco.bat`
6. Tab **Settings**:
   - Centang **Run task as soon as possible after a scheduled start is missed**
   - **If the task is already running: Do not start a new instance**
7. OK. Masukkan password akun bila diminta.

---

## Tugas 2 — Impor + Proses

Sama seperti di atas, dengan perbedaan:

- **General → Name:** `ZKTeco 2 - Sync Absensi`
  Akun boleh **SYSTEM** (tidak perlu akses jaringan, hanya baca file lokal & SQL Server).
- **Triggers:** Daily, **Repeat every 15 minutes**, Indefinitely.
  Klik **Advanced → Delay task for: 3 minutes** (beri jeda setelah Tugas 1).
- **Actions → Program:** `C:\xampp\htdocs\hris\presensi\jalankan_sync_zkteco.bat`
- **Settings:** sama.

---

## Catatan akun (Tugas 1)

Tugas 1 mengakses share jaringan, jadi TIDAK bisa pakai SYSTEM.
Idealnya minta IT buat akun layanan khusus (mis. `svc_zkteco`) dengan hak
baca saja ke folder CheckClock. Kalau memakai kredensial di dalam
`salin_mdb_zkteco.bat`, batasi agar file .bat hanya bisa dibaca administrator.

---

## Uji coba

1. Klik kanan **Tugas 1 → Run**, tunggu, cek `C:\zkteco_data\salin.log`.
2. Klik kanan **Tugas 2 → Run**, cek `C:\zkteco_data\sync.log`.
3. Verifikasi data baru masuk:

```sql
SELECT MAX(checktime) AS tap_terbaru FROM dbo.zkteco_checkinout;
SELECT MAX(tanggal)   AS absensi_terbaru FROM dbo.absensi;
SELECT * FROM dbo.sync_zkteco_state;
```

`tap_terbaru` harus mendekati waktu sekarang (maksimal tertinggal
beberapa menit + interval sinkron).

---

## Pemantauan harian

Cukup buka `C:\zkteco_data\sync.log` sesekali. Kalau ada baris "GAGAL",
itu tanda perlu diperiksa. Untuk pantauan cepat lewat SQL:

```sql
SELECT sumber, last_run, jml_baru, status, pesan
FROM dbo.sync_zkteco_state;
```

Kalau `status` = GAGAL atau `last_run` sudah lama tidak berubah,
berarti sinkronisasi berhenti dan perlu dicek.

---

## Kalau perlu tarik data historis (opsional)

Untuk menarik tap sebelum Juni 2026 (mundurkan titik mulai), jalankan
SEKALI di luar jam kerja karena bebannya besar:

```sql
UPDATE dbo.sync_zkteco_state SET last_checktime = '2013-01-01' WHERE sumber='ATT2000';
```

lalu jalankan `jalankan_sync_zkteco.bat` manual. Setelah selesai,
otomasi 15-menit akan lanjut normal dari titik terbaru.
