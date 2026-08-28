@echo off
REM ================================================
REM INSTALLER UNTUK IMAGE SEARCH SYSTEM
REM ================================================

title Image Search System - Setup Installer

:MAIN_MENU
cls
echo.
echo ================================================================
echo              IMAGE SEARCH SYSTEM INSTALLER
echo ================================================================
echo.
echo Sistem pencari dan penampil file JPG dari folder lokal dan network drive
echo Target IP Publik: http://36.81.168.66:8081/
echo Network Drive: \\192.168.0.204\Master Design
echo.
echo ================================================================
echo.
echo 1. Install System Files
echo 2. Setup XAMPP Configuration
echo 3. Create Sample Images
echo 4. Test Installation
echo 5. Mount Network Drive
echo 6. Start Services
echo 7. Open Web Interface
echo 8. Uninstall System
echo 9. Exit
echo.
set /p choice="Pilih opsi (1-9): "

if "%choice%"=="1" goto INSTALL_FILES
if "%choice%"=="2" goto SETUP_XAMPP
if "%choice%"=="3" goto CREATE_SAMPLES
if "%choice%"=="4" goto TEST_INSTALL
if "%choice%"=="5" goto MOUNT_DRIVE
if "%choice%"=="6" goto START_SERVICES
if "%choice%"=="7" goto OPEN_WEB
if "%choice%"=="8" goto UNINSTALL
if "%choice%"=="9" goto EXIT
goto MAIN_MENU

:INSTALL_FILES
cls
echo.
echo ================================================================
echo                    INSTALLING SYSTEM FILES
echo ================================================================
echo.

REM Check if XAMPP exists
if not exist "C:\xampp" (
    echo ❌ XAMPP tidak ditemukan!
    echo.
    echo Silakan install XAMPP terlebih dahulu dari: https://www.apachefriends.org/
    echo Atau download dari: https://sourceforge.net/projects/xampp/
    echo.
    echo Setelah install XAMPP, jalankan installer ini lagi.
    goto PAUSE_RETURN
)

echo ✅ XAMPP ditemukan di C:\xampp
echo.

REM Create directories
echo Membuat direktori...
mkdir "C:\xampp\htdocs\image-search" 2>nul
mkdir "C:\xampp\htdocs\image-search\images" 2>nul
mkdir "C:\xampp\htdocs\image-search\cache" 2>nul
echo ✅ Direktori dibuat

REM Create main HTML file
echo.
echo Membuat file index.html...
(
echo ^<!DOCTYPE html^>
echo ^<html lang="id"^>
echo ^<head^>
echo     ^<meta charset="UTF-8"^>
echo     ^<title^>Image Search System^</title^>
echo     ^<style^>
echo         body { font-family: Arial; margin: 20px; background: #f5f5f5; }
echo         .container { max-width: 1200px; margin: 0 auto; background: white; padding: 20px; border-radius: 10px; }
echo         .search-form { margin: 20px 0; }
echo         .search-input { width: 300px; padding: 10px; margin-right: 10px; }
echo         .search-btn { padding: 10px 20px; background: #007bff; color: white; border: none; cursor: pointer; }
echo         .image-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 15px; margin-top: 20px; }
echo         .image-card { border: 1px solid #ddd; padding: 10px; border-radius: 5px; }
echo         .image-card img { width: 100%%; height: 150px; object-fit: cover; }
echo     ^</style^>
echo ^</head^>
echo ^<body^>
echo     ^<div class="container"^>
echo         ^<h1^>🔍 Image Search System^</h1^>
echo         ^<p^>Sistem pencari file JPG dari folder lokal dan network drive^</p^>
echo         ^<div class="search-form"^>
echo             ^<input type="text" class="search-input" id="searchInput" placeholder="Masukkan nama file..."^>
echo             ^<button class="search-btn" onclick="searchImages()"^>Cari Gambar^</button^>
echo         ^</div^>
echo         ^<div id="results"^>^</div^>
echo     ^</div^>
echo     ^<script^>
echo         function searchImages() {
echo             const searchTerm = document.getElementById('searchInput').value;
echo             if (!searchTerm) { alert('Masukkan kata kunci pencarian'); return; }
echo             window.location.href = 'search.php?q=' + encodeURIComponent(searchTerm);
echo         }
echo     ^</script^>
echo ^</body^>
echo ^</html^>
) > "C:\xampp\htdocs\image-search\index.html"
echo ✅ File index.html dibuat

REM Create PHP search file
echo.
echo Membuat file search.php...
(
echo ^<?php
echo header('Content-Type: text/html; charset=utf-8'^);
echo $searchTerm = $_GET['q'] ?? '';
echo $localPath = 'C:/xampp/htdocs/image-search/images/';
echo $networkPath = '\\\\192.168.0.204\\Master Design\\';
echo ?^>
echo ^<!DOCTYPE html^>
echo ^<html^>^<head^>^<meta charset="UTF-8"^>^<title^>Hasil Pencarian^</title^>^</head^>
echo ^<body^>
echo ^<h2^>Hasil Pencarian: ^<?= htmlspecialchars($searchTerm^) ?^>^</h2^>
echo ^<a href="index.html"^>← Kembali^</a^>
echo ^<?php
echo if ($searchTerm^) {
echo     echo "^<p^>Mencari file yang mengandung: $searchTerm^</p^>";
echo     // Implementasi pencarian akan ditambahkan di sini
echo }
echo ?^>
echo ^</body^>^</html^>
) > "C:\xampp\htdocs\image-search\search.php"
echo ✅ File search.php dibuat

echo.
echo ✅ Instalasi file selesai!
echo.
echo File yang dibuat:
echo - C:\xampp\htdocs\image-search\index.html
echo - C:\xampp\htdocs\image-search\search.php
echo - C:\xampp\htdocs\image-search\images\ (folder)

goto PAUSE_RETURN

:SETUP_XAMPP
cls
echo.
echo ================================================================
echo                   SETUP XAMPP CONFIGURATION
echo ================================================================
echo.

REM Check XAMPP installation
if not exist "C:\xampp\apache\conf\httpd.conf" (
    echo ❌ File konfigurasi XAMPP tidak ditemukan!
    goto PAUSE_RETURN
)

echo Konfigurasi XAMPP untuk Image Search System...
echo.

REM Backup original config
if not exist "C:\xampp\apache\conf\httpd.conf.backup" (
    copy "C:\xampp\apache\conf\httpd.conf" "C:\xampp\apache\conf\httpd.conf.backup"
    echo ✅ Backup konfigurasi dibuat
)

REM Create virtual host configuration
echo Membuat konfigurasi virtual host...
(
echo.
echo # Image Search System Configuration
echo ^<Directory "C:/xampp/htdocs/image-search"^>
echo     Options Indexes FollowSymLinks MultiViews
echo     AllowOverride All
echo     Require all granted
echo ^</Directory^>
echo.
echo # Alias untuk akses gambar
echo Alias /images "C:/xampp/htdocs/image-search/images"
echo.
) >> "C:\xampp\apache\conf\httpd.conf"

echo ✅ Konfigurasi virtual host ditambahkan

REM PHP configuration
echo.
echo Mengecek konfigurasi PHP...
findstr "file_uploads = On" "C:\xampp\php\php.ini" >nul
if %errorlevel%==0 (
    echo ✅ File uploads sudah diaktifkan
) else (
    echo ⚠️  File uploads mungkin belum diaktifkan
)

findstr "max_file_uploads = 20" "C:\xampp\php\php.ini" >nul
if %errorlevel%==0 (
    echo ✅ Max file uploads: 20
) else (
    echo ⚠️  Max file uploads mungkin perlu disesuaikan
)

echo.
echo ✅ Setup XAMPP selesai!
echo ⚠️  Restart Apache setelah konfigurasi ini untuk menerapkan perubahan.

goto PAUSE_RETURN

:CREATE_SAMPLES
cls
echo.
echo ================================================================
echo                  CREATE SAMPLE IMAGES
echo ================================================================
echo.

echo Membuat sample images untuk testing...
echo.

REM Create sample folder structure
mkdir "C:\xampp\htdocs\image-search\images\designs" 2>nul
mkdir "C:\xampp\htdocs\image-search\images\photos" 2>nul
mkdir "C:\xampp\htdocs\image-search\images\logos" 2>nul

REM Create simple placeholder images using echo
echo Membuat placeholder images...

REM Create simple HTML files that look like images for demo
(
echo ^<svg width="200" height="150" xmlns="http://www.w3.org/2000/svg"^>
echo   ^<rect width="100%%" height="100%%" fill="#4CAF50"/^>
echo   ^<text x="50%%" y="50%%" text-anchor="middle" fill="white" font-size="16"^>Design 1^</text^>
echo ^</svg^>
) > "C:\xampp\htdocs\image-search\images\designs\design1.svg"

(
echo ^<svg width="200" height="150" xmlns="http://www.w3.org/2000/svg"^>
echo   ^<rect width="100%%" height="100%%" fill="#2196F3"/^>
echo   ^<text x="50%%" y="50%%" text-anchor="middle" fill="white" font-size="16"^>Logo Sample^</text^>
echo ^</svg^>
) > "C:\xampp\htdocs\image-search\images\logos\logo_sample.svg"

(
echo ^<svg width="200" height="150" xmlns="http://www.w3.org/2000/svg"^>
echo   ^<rect width="100%%" height="100%%" fill="#FF9800"/^>
echo   ^<text x="50%%" y="50%%" text-anchor="middle" fill="white" font-size="16"^>Photo Test^</text^>
echo ^</svg^>
) > "C:\xampp\htdocs\image-search\images\photos\photo_test.svg"

echo ✅ Sample images dibuat:
echo - designs/design1.svg
echo - logos/logo_sample.svg  
echo - photos/photo_test.svg
echo.
echo 📝 Catatan: Ini adalah placeholder SVG files.
echo    Untuk testing dengan file JPG asli, copy file JPG ke:
echo    C:\xampp\htdocs\image-search\images\

goto PAUSE_RETURN

:TEST_INSTALL
cls
echo.
echo ================================================================
echo                    TEST INSTALLATION
echo ================================================================
echo.

echo Testing installation components...
echo.

REM Test 1: Check files
echo 1. Checking system files...
if exist "C:\xampp\htdocs\image-search\index.html" (
    echo ✅ index.html found
) else (
    echo ❌ index.html missing
)

if exist "C:\xampp\htdocs\image-search\search.php" (
    echo ✅ search.php found  
) else (
    echo ❌ search.php missing
)

REM Test 2: Check XAMPP
echo.
echo 2. Checking XAMPP services...
tasklist /FI "IMAGENAME eq httpd.exe" 2>NUL | find /I /N "httpd.exe" >NUL
if %errorlevel%==0 (
    echo ✅ Apache is running
) else (
    echo ❌ Apache is not running
    echo    Run: net start apache2.4
)

tasklist /FI "IMAGENAME eq mysqld.exe" 2>NUL | find /I /N "mysqld.exe" >NUL
if %errorlevel%==0 (
    echo ✅ MySQL is running
) else (
    echo ⚠️  MySQL is not running (optional for basic image search)
)

REM Test 3: Check network connectivity
echo.
echo 3. Testing network connectivity...
ping -n 2 192.168.0.204 >nul 2>&1
if %errorlevel%==0 (
    echo ✅ Network server 192.168.0.204 is reachable
) else (
    echo ❌ Cannot reach 192.168.0.204
    echo    Check network connection and server availability
)

REM Test 4: Check public IP access
echo.
echo 4. Testing public IP configuration...
echo Public IP: 36.81.168.66:8081
echo ⚠️  Manual test required: Try accessing http://36.81.168.66:8081 from external network

REM Test 5: Check directory permissions
echo.
echo 5. Checking directory permissions...
echo Test > "C:\xampp\htdocs\image-search\test_write.tmp" 2>nul
if exist "C:\xampp\htdocs\image-search\test_write.tmp" (
    echo ✅ Write permissions OK
    del "C:\xampp\htdocs\image-search\test_write.tmp" 2>nul
) else (
    echo ❌ No write permissions
)

echo.
echo ================================================================
echo                        TEST SUMMARY
echo ================================================================
echo.
echo Manual tests to perform:
echo 1. Open: http://localhost/image-search/
echo 2. Try searching for files
echo 3. Test from external IP: http://36.81.168.66:8081/image-search/
echo 4. Verify network drive access
echo.

goto PAUSE_RETURN

:MOUNT_DRIVE
cls
echo.
echo Mounting network drive...
call mount_network.bat
goto PAUSE_RETURN

:START_SERVICES
cls
echo.
echo ================================================================
echo                     STARTING SERVICES
echo ================================================================
echo.

echo Starting XAMPP services...
echo.

net start apache2.4 2>nul
if %errorlevel%==0 (
    echo ✅ Apache started successfully
) else (
    echo ⚠️  Apache may already be running or failed to start
)

net start mysql 2>nul
if %errorlevel%==0 (
    echo ✅ MySQL started successfully
) else (
    echo ⚠️  MySQL may already be running or failed to start
)

REM Alternative method
if exist "C:\xampp\xampp_start.exe" (
    echo.
    echo Starting XAMPP Control Panel...
    start "XAMPP" "C:\xampp\xampp_start.exe"
)

echo.
echo ✅ Services startup completed!
echo.
echo Access points:
echo - Local: http://localhost/image-search/
echo - Public: http://36.81.168.66:8081/image-search/
echo - XAMPP Dashboard: http://localhost/dashboard/

goto PAUSE_RETURN

:OPEN_WEB
cls
echo.
echo Opening web interfaces...
echo.

start http://localhost/image-search/
echo ✅ Local interface opened

timeout /t 2 >nul

start http://36.81.168.66:8081/image-search/
echo ✅ Public interface opened

timeout /t 2 >nul

start http://localhost/dashboard/
echo ✅ XAMPP dashboard opened

goto PAUSE_RETURN

:UNINSTALL
cls
echo.
echo ================================================================
echo                      UNINSTALL SYSTEM  
echo ================================================================
echo.
echo ⚠️  PERINGATAN: Ini akan menghapus semua file Image Search System
echo.
set /p confirm="Apakah Anda yakin ingin uninstall? (Y/N): "
if /i "%confirm%" NEQ "Y" goto MAIN_MENU