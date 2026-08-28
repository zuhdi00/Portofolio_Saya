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
REM  dengan opsi "Run whether user is logged on or not" +
REM  akun yang punya hak baca ke share tersebut.
REM =====================================================================

REM ---------- SESUAIKAN BAGIAN INI ----------
set SUMBER=\\spsdmz\gg$\HRD\CheckClock
set NAMAFILE=ATT2000.mdb
set TUJUAN=C:\zkteco_data
set USER=Administrator@spsdmz.local
set PASS=Supracor2009
REM ------------------------------------------

set LOG=%TUJUAN%\salin.log

if not exist "%TUJUAN%" mkdir "%TUJUAN%"

echo. >> "%LOG%"
echo ===== %date% %time% ===== >> "%LOG%"

REM Buka koneksi ke share (hidden share butuh kredensial)
net use "%SUMBER%" /user:%USER% %PASS% >> "%LOG%" 2>&1

REM /R:2 = ulangi 2x kalau gagal, /W:5 = tunggu 5 detik
REM /NP  = tanpa progress bar,     /NDL = tanpa daftar folder
robocopy "%SUMBER%" "%TUJUAN%" "%NAMAFILE%" /R:2 /W:5 /NP /NDL >> "%LOG%" 2>&1

set RC=%ERRORLEVEL%
REM robocopy: 0=tidak ada perubahan, 1=berhasil menyalin, >=8 = error
if %RC% GEQ 8 (
    echo GAGAL menyalin, kode %RC% >> "%LOG%"
) else (
    echo OK, kode %RC% >> "%LOG%"
)

REM Tutup koneksi share
net use "%SUMBER%" /delete /y >> "%LOG%" 2>&1

exit /b 0
