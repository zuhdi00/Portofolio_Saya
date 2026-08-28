@echo off
title Perbaikan Akses XAMPP Folder masterdesign
echo =====================================================
echo  [1] Mengatur hak akses folder masterdesign
echo =====================================================
icacls "C:\xampp\htdocs\masterdesign" /grant Everyone:(OI)(CI)F /T
echo.

echo =====================================================
echo  [2] Menambahkan konfigurasi Apache
echo =====================================================
set HTTPD_CONF=C:\xampp\apache\conf\httpd.conf

REM Backup httpd.conf sebelum edit
copy "%HTTPD_CONF%" "%HTTPD_CONF%.backup" >nul

REM Tambah konfigurasi jika belum ada
findstr /C:"<Directory \"C:/xampp/htdocs/masterdesign\">" "%HTTPD_CONF%" >nul
if %errorlevel% neq 0 (
    echo.>>"%HTTPD_CONF%"
    echo ^<Directory "C:/xampp/htdocs/masterdesign"^>>> "%HTTPD_CONF%"
    echo     Options Indexes FollowSymLinks>> "%HTTPD_CONF%"
    echo     AllowOverride All>> "%HTTPD_CONF%"
    echo     Require all granted>> "%HTTPD_CONF%"
    echo ^</Directory^>>> "%HTTPD_CONF%"
    echo.>>"%HTTPD_CONF%"
    echo EnableSendfile Off>> "%HTTPD_CONF%"
    echo EnableMMAP Off>> "%HTTPD_CONF%"
    echo [INFO] Konfigurasi Apache sudah ditambahkan.
) else (
    echo [INFO] Konfigurasi Apache sudah ada, dilewati.
)
echo.

echo =====================================================
echo  [3] Restart Apache
echo =====================================================
cd /d C:\xampp\apache\bin
httpd.exe -k restart
echo.

echo =====================================================
echo  [4] Cek koneksi di browser
echo =====================================================
echo Silakan buka: http://localhost/masterdesign/
pause
