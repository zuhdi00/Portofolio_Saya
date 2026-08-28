@echo off
REM ================================================================
REM mount_system_fix.bat
REM Jalankan SEKALI sebagai Administrator untuk fix akses network
REM dari PHP (yang berjalan sebagai SYSTEM user)
REM ================================================================

echo ============================================
echo  Fix Network Drive untuk SYSTEM user (PHP)
echo ============================================
echo.

REM Pastikan dijalankan sebagai Administrator
net session >nul 2>&1
if %errorLevel% neq 0 (
    echo ERROR: Script ini harus dijalankan sebagai Administrator!
    echo Klik kanan file ini, pilih "Run as administrator"
    pause
    exit /b 1
)

REM Cek apakah PsExec tersedia
set "PSEXEC=%~dp0PsExec.exe"
if not exist "%PSEXEC%" (
    echo PsExec.exe tidak ditemukan di folder yang sama dengan script ini.
    echo Download dari: https://learn.microsoft.com/en-us/sysinternals/downloads/psexec
    echo Letakkan PsExec.exe di: %~dp0
    echo.
    pause
    exit /b 1
)

echo [1/4] Stop Apache dulu...
net stop Apache2.4 2>nul
taskkill /f /im httpd.exe 2>nul
timeout /t 2 >nul

echo.
echo [2/4] Simpan credential untuk SYSTEM user...
"%PSEXEC%" -accepteula -s -i cmd /c "cmdkey /add:192.168.0.204 /user:EDP2 /pass:PASSWORD"
timeout /t 2 >nul

echo.
echo [3/4] Test akses UNC path sebagai SYSTEM...
"%PSEXEC%" -accepteula -s cmd /c "dir \"\\192.168.0.204\Master Design\" 2>&1"
if %errorLevel% equ 0 (
    echo.
    echo SUCCESS: SYSTEM user bisa akses UNC path!
    echo PHP sekarang bisa akses \\192.168.0.204\Master Design langsung.
    echo TIDAK PERLU mount Z: - edit PHP untuk pakai UNC path.
) else (
    echo.
    echo WARNING: UNC test gagal. Coba mount Z: untuk SYSTEM...
    "%PSEXEC%" -accepteula -s cmd /c "net use Z: \"\\192.168.0.204\Master Design\" /user:EDP2 PASSWORD /persistent:yes"
    "%PSEXEC%" -accepteula -s cmd /c "net use"
)

echo.
echo [4/4] Start Apache lagi...
net start Apache2.4
timeout /t 2 >nul

echo.
echo ============================================
echo  Selesai! Cek PHP sekarang.
echo  Jika berhasil, edit search_images_z.php:
echo  Ganti 'Z:/' dengan '\\192.168.0.204\Master Design'
echo ============================================
pause
