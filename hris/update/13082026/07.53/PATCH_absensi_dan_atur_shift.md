# Perbaikan HRIS Absensi + Cara Atur Shift
PT Supracor Sejahtera — modul `presensi`

---

## BAGIAN 1 — Bug utama: jam_masuk tertimpa NULL

### Kenapa terjadi
`sync_zkteco_proses.php` menandai tap `diproses = 1` setelah dipakai. Kalau dalam satu
siklus 15 menit hanya ada **tap masuk**, baris absensi ditulis dengan `jam_keluar = NULL`.
Saat tap pulang datang di siklus berikutnya, tap masuknya sudah `diproses = 1`, jadi
skrip menganggapnya "tap pulang tanpa tap masuk" dan MERGE menimpa:

```
WHEN MATCHED THEN UPDATE SET jam_masuk = ?, jam_keluar = ?, ...
```
`jam_masuk` diisi NULL → **data yang tadinya benar jadi hilang.**
Sering terjadi pada shift 3 (masuk 21:00 hari ini, pulang 07:00 besok).

### Langkah 1 — ganti `$sqlUpsert`
Cari blok `$sqlUpsert = "` di `sync_zkteco_proses.php`, ganti SELURUH isinya dengan:

```php
$sqlUpsert = "
MERGE dbo.absensi AS t
USING (SELECT ? AS pegawai_id, ? AS tanggal,
              CAST(? AS TIME) AS jm, CAST(? AS TIME) AS jk,
              ? AS sts, ? AS mtd, ? AS sn, ? AS shf, ? AS jt, ? AS pk) AS s
   ON t.pegawai_id = s.pegawai_id AND t.tanggal = s.tanggal
WHEN MATCHED THEN UPDATE SET
     jam_masuk  = CASE WHEN s.jm IS NULL              THEN t.jam_masuk
                       WHEN t.jam_masuk IS NULL       THEN s.jm
                       WHEN s.jm < t.jam_masuk        THEN s.jm
                       ELSE t.jam_masuk END,
     jam_keluar = CASE WHEN s.jk IS NULL              THEN t.jam_keluar
                       WHEN t.jam_keluar IS NULL      THEN s.jk
                       WHEN s.jk > t.jam_keluar       THEN s.jk
                       ELSE t.jam_keluar END,
     status     = CASE WHEN s.jm IS NOT NULL AND (t.jam_masuk IS NULL OR s.jm < t.jam_masuk)
                       THEN s.sts ELSE t.status END,
     metode     = COALESCE(t.metode, s.mtd),
     sn_mesin   = COALESCE(t.sn_mesin, s.sn),
     shift_ke   = COALESCE(t.shift_ke, s.shf),
     jml_tap    = ISNULL(t.jml_tap,0) + s.jt,
     perlu_koreksi = CASE
         WHEN (CASE WHEN s.jm IS NULL THEN t.jam_masuk  ELSE s.jm END) IS NULL
           OR (CASE WHEN s.jk IS NULL THEN t.jam_keluar ELSE s.jk END) IS NULL
         THEN 1 ELSE 0 END,
     sumber = 'ZKTECO'
WHEN NOT MATCHED THEN INSERT
     (pegawai_id, tanggal, jam_masuk, jam_keluar, status, metode, sn_mesin,
      shift_ke, jml_tap, perlu_koreksi, sumber, keterangan)
     VALUES (s.pegawai_id, s.tanggal, s.jm, s.jk, s.sts, s.mtd, s.sn,
             s.shf, s.jt, s.pk, 'ZKTECO', N'Sinkron ZKTeco');";
```

### Langkah 2 — parameter tinggal 10 (bukan 20)
Di fungsi `tulisAbsensiPeg()`, ganti:

```php
        $p = [$pegId, $tgl, $d['jam_masuk'], $d['jam_keluar'], $status, $d['metode'], $d['sn'],
              $d['shift'], $d['jml_tap'], $perluKoreksi,
              $pegId, $tgl, $d['jam_masuk'], $d['jam_keluar'], $status, $d['metode'], $d['sn'],
              $d['shift'], $d['jml_tap'], $perluKoreksi];
```
menjadi:
```php
        $p = [$pegId, $tgl, $d['jam_masuk'], $d['jam_keluar'], $status, $d['metode'], $d['sn'],
              $d['shift'], $d['jml_tap'], $perluKoreksi];
```
> Kalau langkah 2 lupa dilakukan, akan muncul error "too many parameters".

### Langkah 3 — tap pulang shift malam masuk ke tanggal yang benar
Di blok `if ($t['arah'] === 'O') {`, tepat setelah baris `$tgl = $t['ts']->format('Y-m-d');`
sisipkan:

```php
        // pulang dini hari = milik shift malam yang MULAI hari sebelumnya
        if ($TGL_SHIFT3 === 'tanggal_tap' && (int)$t['ts']->format('H') < 8) {
            $d2 = clone $t['ts']; $d2->modify('-1 day');
            $tgl = $d2->format('Y-m-d'); $shift = 3;
        }
```
Tambahkan juga `global $TGL_SHIFT3;` bila blok ini berada di dalam fungsi (di versi
sekarang blok ini di scope utama, jadi tidak perlu).

### Langkah 4 — perbaiki data lama yang sudah terlanjur NULL
Jalankan setelah patch (menandai ulang tap 30 hari terakhir supaya diolah ulang):

```sql
UPDATE dbo.zkteco_checkinout
SET diproses = 0
WHERE checktime >= DATEADD(DAY,-30, CAST(GETDATE() AS DATE));
```
Lalu jalankan `sync_zkteco_proses.php` sekali secara manual. Dengan MERGE yang baru,
baris lama akan dilengkapi, bukan ditimpa.

---

## BAGIAN 2 — Penyebab lain yang harus dicek (urut prioritas)

| # | Gejala | Penyebab | Perbaikan |
|---|--------|----------|-----------|
| 1 | Satu orang **tidak pernah** muncul | `pegawai.zkteco_userid` kosong atau `is_aktif = 0` | query C di `diagnosa_absensi.sql`; isi userid-nya |
| 2 | Jam pulang selalu kosong untuk sebagian orang | ada mesin baru yang SN-nya belum terdaftar → arah tap ditebak | query D; tambahkan SN ke `mesin_masuk`/`mesin_keluar` |
| 3 | Ada tanggal yang kosong total | sync tidak jalan hari itu, atau `last_checktime` melompat ke masa depan (jam mesin salah) | cek `sync_zkteco_state`; tarik ulang dgn `tarik_historis_zkteco.php` |
| 4 | Data > 7 hari lalu tidak ikut | `$BATAS_MUNDUR_HARI = 7` di `sync_zkteco_import.php` sengaja membatasi | pakai `tarik_historis_zkteco.php 2026-07-01 2026-08-13` |
| 5 | Tap terlambat masuk mesin (> 1 hari) terlewat | `$MUNDUR_HARI = 1` | naikkan jadi `3` (aman, impor idempoten) |

---

## BAGIAN 3 — Apakah salah `sync_langsung_zkteco.bat`?

File .bat itu **hanya pemanggil**, tidak mengolah data. Tapi bisa jadi sumber masalah
kalau: akun Task Scheduler tidak punya akses ke `\\spsdmz\gg$\HRD\CheckClock`
(mode `unc` di `koneksi_mdb.php`), atau task-nya mati.

Cek isi log dulu:
```bat
type C:\zkteco_data\sync.log | more
```
Yang harus muncul tiap siklus: `Tap baru masuk : N`. Kalau tertulis
`ERROR koneksi MDB` atau `MDB jaringan tidak terjangkau` → masalah hak akses akun.

Perbaikan .bat yang disarankan (tambah exit code + rotasi log):
```bat
"%PHP%" "%DIR%\sync_zkteco_import.php" >> "%LOG%" 2>&1
if errorlevel 1 (
  echo *** IMPOR GAGAL - proses dibatalkan *** >> "%LOG%"
  exit /b 1
)
```
Task Scheduler: centang **Run whether user is logged on or not**, isi akun domain
yang punya akses share, dan **jangan** pakai akun `SYSTEM` (tidak bisa akses UNC).

---

## BAGIAN 4 — Cara mengatur shift

Semua diatur lewat **database**, tidak perlu edit PHP. Tabel: `dbo.pengaturan_shift`.

```sql
USE dbHR;
SELECT * FROM dbo.pengaturan_shift ORDER BY shift_ke;
```

| Kolom | Arti |
|---|---|
| `jam_mulai` / `jam_selesai` | jam kerja resmi (dipakai menghitung **terlambat**) |
| `masuk_dari` / `masuk_sampai` | **jendela tap** — jam berapa tap dianggap milik shift ini |
| `toleransi_menit` | keterlambatan yang dimaafkan |
| `is_aktif` | 0 = shift tidak dipakai |

Contoh perubahan:
```sql
-- toleransi 10 menit untuk semua shift
UPDATE dbo.pengaturan_shift SET toleransi_menit = 10, diubah_pada = GETDATE();

-- shift 1 masuk 07:30, pulang 15:30
UPDATE dbo.pengaturan_shift
SET jam_mulai='07:30', jam_selesai='15:30', masuk_dari='05:00', masuk_sampai='11:59'
WHERE shift_ke = 1;

-- tambah shift 4 (non-shift / office 08:00-17:00)
INSERT INTO dbo.pengaturan_shift
 (shift_ke,nama_shift,jam_mulai,jam_selesai,masuk_dari,masuk_sampai,toleransi_menit,is_aktif)
VALUES (4,N'Non-Shift (Office)','08:00','17:00','05:00','11:59',15,1);
```

> **PENTING:** jendela `masuk_dari`–`masuk_sampai` antar shift **tidak boleh bertabrakan**.
> Skrip memilih shift pertama yang cocok (urut `shift_ke`), jadi jendela yang tumpang
> tindih membuat orang shift 2 terdeteksi shift 1 → dianggap terlambat 8 jam.
> Shift 3 (`21:00`–`02:59`) memang melewati tengah malam; itu sudah ditangani skrip.

Pengaturan umum ada di `dbo.pengaturan_absensi`:

```sql
SELECT * FROM dbo.pengaturan_absensi;

-- daftarkan mesin baru di gerbang masuk
UPDATE dbo.pengaturan_absensi
SET nilai = N'AXW8190960045,SERIAL_MESIN_BARU', diubah_pada = GETDATE()
WHERE kunci = N'mesin_masuk';

-- tanggal kerja shift malam: 'tanggal_tap' (ikut tgl tap masuk) atau 'tanggal_shift'
UPDATE dbo.pengaturan_absensi SET nilai = N'tanggal_tap' WHERE kunci = N'tgl_shift3';
```

Setelah mengubah shift, data lama **tidak** otomatis dihitung ulang. Kalau perlu:
`UPDATE dbo.zkteco_checkinout SET diproses = 0 WHERE checktime >= 'YYYY-MM-DD';`
lalu jalankan ulang `sync_zkteco_proses.php`.
