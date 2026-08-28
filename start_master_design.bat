@echo off
title Start Master Design Web - Auto Mount + XAMPP

:: ========== KONFIGURASI ==========

set "DRIVE=Z:"
set "NETWORK_PATH=\\192.168.0.204\Master Design"
set "USERNAME=administrator@spsdmz.local"
set "PASSWORD=Supracor2009"

:: Ganti lokasi XAMPP jika beda
set "XAMPP_PATH=C:\xampp"
set "URL=http://180.251.120.19:8081/master-design/"

:: ========== MOUNT NETWORK DRIVE ==========
echo.
echo [1/3] Memasang network drive %DRIVE% ke %NETWORK_PATH% ...
net use %DRIVE% /delete >nul 2>&1
net use %DRIVE% "%NETWORK_PATH%" /user:%USERNAME% %PASSWORD% /persistent:no

if errorlevel 1 (
    echo GAGAL memasang network drive!
    pause
    exit /b
)

echo Network drive berhasil dipasang!

:: ========== JALANKAN XAMPP ==========
echo.
echo [2/3] Menjalankan Apache dan MySQL dari XAMPP...
cd /d "%XAMPP_PATH%"
start "" apache_start.bat
start "" mysql_start.bat

timeout /t 3 >nul

:: ========== BUKA BROWSER ==========
echo.
echo [3/3] Membuka browser ke %URL%
start "" "%URL%"

echo.
echo Semua langkah selesai. Tekan tombol apa saja untuk keluar...
pause >nul
