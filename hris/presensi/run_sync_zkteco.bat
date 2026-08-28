@echo off
setlocal

set "PHP=C:\xampp\php\php.exe"
set "APP=C:\xampp\htdocs\hris\presensi"
set "LOGDIR=%APP%\logs"

if not exist "%PHP%" (
    echo [%date% %time%] ERROR: PHP tidak ditemukan di %PHP%
    exit /b 10
)

if not exist "%LOGDIR%" mkdir "%LOGDIR%"
cd /d "%APP%"

set "LOG=%LOGDIR%\sync_zkteco_%date:~-4%%date:~3,2%%date:~0,2%.log"
echo.>>"%LOG%"
echo [%date% %time%] === Sinkronisasi ZKTeco mulai ===>>"%LOG%"

"%PHP%" "%APP%\sync_zkteco_import.php" >>"%LOG%" 2>&1
if errorlevel 1 (
    echo [%date% %time%] ERROR: tahap import gagal.>>"%LOG%"
    exit /b 20
)

"%PHP%" "%APP%\sync_zkteco_proses.php" >>"%LOG%" 2>&1
if errorlevel 1 (
    echo [%date% %time%] ERROR: tahap proses gagal.>>"%LOG%"
    exit /b 30
)

echo [%date% %time%] === Sinkronisasi ZKTeco selesai ===>>"%LOG%"
exit /b 0
