@echo off
setlocal
REM =====================================================================
REM  salin_mdb_zkteco.bat  [v4 - hapus lama, ambil baru]
REM
REM  Prinsip: salinan lokal sekali pakai. Data sudah masuk SQL (dbHR),
REM  jadi MDB salinan lama tak perlu disimpan. Tiap jalan:
REM    1. Hapus salinan lama + arsip + tmp (bebaskan disk)
REM    2. Salin MDB baru dari server ke _tmp
REM    3. Pindah jadi file aktif
REM  Kebutuhan disk: ~2x ukuran MDB saja (tmp + aktif), TIDAK menumpuk.
REM =====================================================================

REM ---------- SESUAIKAN ----------
set SUMBER=\\spsdmz\gg$\HRD\CheckClock
set NAMAFILE=ATT2000.mdb
set TUJUAN=C:\zkteco_data
REM -------------------------------

set AKTIF=%TUJUAN%\%NAMAFILE%
set TMP=%TUJUAN%\_tmp
set LOG=%TUJUAN%\salin.log

if not exist "%TUJUAN%" mkdir "%TUJUAN%"
if not exist "%TMP%"    mkdir "%TMP%"

echo. >> "%LOG%"
echo ===== %date% %time% ===== >> "%LOG%"

REM ---------- 1. HAPUS semua salinan lama (aman: data sudah di SQL) ----------
if exist "%AKTIF%"                  del /q "%AKTIF%"                  >> "%LOG%" 2>&1
if exist "%TUJUAN%\ATT2000_*.mdb.bak" del /q "%TUJUAN%\ATT2000_*.mdb.bak" >> "%LOG%" 2>&1
if exist "%TMP%\%NAMAFILE%"         del /q "%TMP%\%NAMAFILE%"         >> "%LOG%" 2>&1
echo Salinan lama dibersihkan. >> "%LOG%"

REM ---------- 2. Salin MDB baru dari server ke _tmp ----------
robocopy "%SUMBER%" "%TMP%" "%NAMAFILE%" /R:2 /W:5 /NP /NDL >> "%LOG%" 2>&1
set RC=%ERRORLEVEL%

if %RC% GEQ 8 (
    echo GAGAL menyalin dari server, kode %RC%. >> "%LOG%"
    exit /b 1
)
if not exist "%TMP%\%NAMAFILE%" (
    echo GAGAL: file hasil salin tidak ada. >> "%LOG%"
    exit /b 1
)

REM ---------- 3. Pindah jadi aktif ----------
move /y "%TMP%\%NAMAFILE%" "%AKTIF%" >> "%LOG%" 2>&1
if errorlevel 1 (
    echo GAGAL memindahkan file ke aktif. >> "%LOG%"
    exit /b 1
)
echo OK - MDB baru siap dipakai %date% %time% >> "%LOG%"

exit /b 0
