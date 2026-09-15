@echo off
REM =====================================================================
REM  salin_mdb_zkteco.bat
REM  Menyalin file MDB ZKTeco dari share jaringan ke disk lokal server XAMPP.
REM  PHP membaca SALINAN ini, bukan file aslinya.
REM
REM  Alasan:
REM   - Apache berjalan sebagai Local System, tidak punya hak akses jaringan
REM   - Membaca MDB lewat jaringan lambat & rawan file-lock
REM   - Kalau file asli terkunci ATT2000, salinan tetap bisa dibaca
REM
REM  Pasang di Task Scheduler, jalankan TIAP 15 MENIT,
REM  "Run whether user is logged on or not" + akun berhak baca ke share.
REM =====================================================================

REM ---------- SESUAIKAN BAGIAN INI ----------
set SUMBER=\\spsdmz\gg$\HRD\CheckClock
set NAMAFILE=ATT2000.mdb
set TUJUAN=C:\zkteco_data
REM ------------------------------------------

set LOG=%TUJUAN%\salin.log

if not exist "%TUJUAN%" mkdir "%TUJUAN%"

echo. >> "%LOG%"
echo ===== %date% %time% ===== >> "%LOG%"

REM CATATAN: baris "net use" di bawah SENGAJA dinonaktifkan (REM).
REM Windows sudah punya koneksi ke \\spsdmz dari akun Task Scheduler,
REM sehingga net use menimbulkan error 1219 tanpa perlu. Robocopy tetap
REM berhasil menyalin. Aktifkan kembali (hapus REM) HANYA jika salinan gagal
REM karena "access denied", dan isikan USER/PASS akun berhak baca.
REM set USER=SPSDMZ\svc_zkteco
REM set PASS=password_akun
REM net use "%SUMBER%" /user:%USER% %PASS% >> "%LOG%" 2>&1

REM /R:2 = ulang 2x bila gagal, /W:5 = tunggu 5 detik
REM /NP  = tanpa progress bar,    /NDL = tanpa daftar folder
robocopy "%SUMBER%" "%TUJUAN%" "%NAMAFILE%" /R:2 /W:5 /NP /NDL >> "%LOG%" 2>&1

set RC=%ERRORLEVEL%
REM robocopy: 0=tidak ada perubahan, 1=berhasil salin, >=8 = error
if %RC% GEQ 8 (
    echo GAGAL menyalin, kode %RC% >> "%LOG%"
) else (
    echo OK, kode %RC% >> "%LOG%"
)

REM net use "%SUMBER%" /delete /y >> "%LOG%" 2>&1

exit /b 0
