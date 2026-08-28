@echo off
setlocal enabledelayedexpansion
REM =====================================================================
REM  salin_mdb_zkteco.bat  [v2 - tanpa replace langsung]
REM
REM  Alur aman:
REM   1. Salin MDB dari server ke file SEMENTARA (.tmp)
REM   2. Kalau sukses -> arsipkan MDB lama (rename dengan timestamp)
REM   3. Jadikan file .tmp sebagai MDB aktif
REM   Kalau langkah 1 gagal, MDB lama TIDAK disentuh (tetap bisa dipakai).
REM =====================================================================

REM ---------- SESUAIKAN ----------
set SUMBER=\\spsdmz\gg$\HRD\CheckClock
set NAMAFILE=ATT2000.mdb
set TUJUAN=C:\zkteco_data
set SIMPAN_ARSIP=3
REM SIMPAN_ARSIP = berapa file lama disimpan sebelum dihapus otomatis
REM -------------------------------

set AKTIF=%TUJUAN%\%NAMAFILE%
set TMP=%TUJUAN%\_tmp
set LOG=%TUJUAN%\salin.log

if not exist "%TUJUAN%" mkdir "%TUJUAN%"
if not exist "%TMP%"    mkdir "%TMP%"

echo. >> "%LOG%"
echo ===== %date% %time% ===== >> "%LOG%"

REM ---------- 1. Salin ke folder sementara ----------
robocopy "%SUMBER%" "%TMP%" "%NAMAFILE%" /R:2 /W:5 /NP /NDL >> "%LOG%" 2>&1
set RC=%ERRORLEVEL%

if %RC% GEQ 8 (
    echo GAGAL menyalin dari server, kode %RC%. MDB lama tetap dipakai. >> "%LOG%"
    exit /b 1
)

if not exist "%TMP%\%NAMAFILE%" (
    echo GAGAL: file hasil salin tidak ditemukan. MDB lama tetap dipakai. >> "%LOG%"
    exit /b 1
)

REM ---------- 2. Arsipkan MDB lama (kalau ada) ----------
if exist "%AKTIF%" (
    REM buat timestamp: YYYYMMDD_HHMMSS
    for /f "tokens=1-3 delims=/. " %%a in ("%date%") do set TGL=%%c%%b%%a
    set JAM=%time: =0%
    set JAM=!JAM:~0,2!!JAM:~3,2!!JAM:~6,2!
    set STAMP=!TGL!_!JAM!
    ren "%AKTIF%" "ATT2000_!STAMP!.mdb.bak" >> "%LOG%" 2>&1
    if errorlevel 1 (
        echo GAGAL mengganti nama MDB lama - mungkin sedang dipakai. >> "%LOG%"
        exit /b 1
    )
    echo MDB lama diarsipkan: ATT2000_!STAMP!.mdb.bak >> "%LOG%"
)

REM ---------- 3. Pindahkan file baru jadi aktif ----------
move /y "%TMP%\%NAMAFILE%" "%AKTIF%" >> "%LOG%" 2>&1
if errorlevel 1 (
    echo GAGAL memindahkan file baru jadi aktif. >> "%LOG%"
    exit /b 1
)
echo OK - MDB aktif diperbarui %date% %time% >> "%LOG%"

REM ---------- 4. Bersihkan arsip lama (sisakan N terbaru) ----------
set /a HITUNG=0
for /f "delims=" %%f in ('dir /b /o-d "%TUJUAN%\ATT2000_*.mdb.bak" 2^>nul') do (
    set /a HITUNG+=1
    if !HITUNG! GTR %SIMPAN_ARSIP% (
        del "%TUJUAN%\%%f" >> "%LOG%" 2>&1
        echo Arsip lama dihapus: %%f >> "%LOG%"
    )
)

exit /b 0
