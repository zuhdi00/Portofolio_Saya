@echo off
REM mount_network.bat - Mount Network Drive ke Z:
echo ========================================
echo     MASTER DESIGN NETWORK MOUNTER
echo ========================================
echo.

REM Check if already mounted
if exist "Z:\" (
    echo [INFO] Drive Z: sudah terpasang
    echo [INFO] Checking connection...
    dir Z: >nul 2>&1
    if %errorlevel% equ 0 (
        echo [SUCCESS] Network drive Z: berhasil terhubung!
        echo [INFO] Path: \\192.168.0.204\Master Design
        goto :end
    ) else (
        echo [WARNING] Drive Z: terpasang tapi tidak dapat diakses
        echo [INFO] Mencoba disconnect dan mount ulang...
        net use Z: /delete /yes >nul 2>&1
    )
)

echo [INFO] Mounting network drive...
echo [INFO] Target: \\192.168.0.204\Master Design
echo [INFO] Drive Letter: Z:
echo.

REM Mount the network drive
net use Z: "\\192.168.0.204\Master Design" /persistent:yes

if %errorlevel% equ 0 (
    echo.
    echo [SUCCESS] Network drive berhasil di-mount ke Z:
    echo [INFO] Testing access...
    
    REM Test access
    dir Z: >nul 2>&1
    if %errorlevel% equ 0 (
        echo [SUCCESS] Network drive dapat diakses dengan baik!
        echo.
        echo [INFO] Anda sekarang dapat menggunakan web application
        echo [INFO] URL: http://localhost:8081/
        echo [INFO] atau: http://180.251.120.19:8081/
    ) else (
        echo [ERROR] Network drive terpasang tapi tidak dapat diakses
        echo [INFO] Periksa koneksi network dan permissions
    )
) else (
    echo.
    echo [ERROR] Gagal mount network drive
    echo [INFO] Possible causes:
    echo        - Network tidak terhubung
    echo        - Server 192.168.0.204 tidak dapat diakses  
    echo        - Credentials tidak valid
    echo        - Firewall blocking connection
    echo.
    echo [INFO] Mencoba dengan credentials...
    set /p username="Enter Username: "
    set /p password="Enter Password: "
    net use Z: "\\192.168.0.204\Master Design" %password% /user:%username% /persistent:yes
    
    if %errorlevel% equ 0 (
        echo [SUCCESS] Network drive berhasil di-mount dengan credentials!
    ) else (
        echo [ERROR] Masih gagal mount dengan credentials
    )
)

:end
echo.
echo ========================================
echo Press any key to continue...
pause >nul


@echo off
REM unmount_network.bat - Disconnect Network Drive
echo ========================================
echo   MASTER DESIGN NETWORK UNMOUNTER  
echo ========================================
echo.

if not exist "Z:\" (
    echo [INFO] Drive Z: tidak terpasang
    goto :end
)

echo [INFO] Disconnecting network drive Z:...
net use Z: /delete /yes

if %errorlevel% equ 0 (
    echo [SUCCESS] Network drive Z: berhasil di-disconnect
) else (
    echo [ERROR] Gagal disconnect network drive Z:
    echo [INFO] Mungkin masih ada file yang terbuka dari drive tersebut
)

:end
echo.
echo ========================================
echo Press any key to continue...
pause >nul


@echo off
REM setup_xampp.bat - Setup XAMPP dan Project
echo ========================================
echo        XAMPP SETUP FOR MASTER DESIGN
echo ========================================
echo.

REM Check if XAMPP is installed
if not exist "C:\xampp\xampp-control.exe" (
    echo [ERROR] XAMPP tidak ditemukan di C:\xampp\
    echo [INFO] Download dan install XAMPP terlebih dahulu
    echo [INFO] URL: https://www.apachefriends.org/download.html
    goto :end
)

echo [INFO] XAMPP ditemukan, memulai setup...

REM Create project directory
set PROJECT_DIR=C:\xampp\htdocs\master-design
if not exist "%PROJECT_DIR%" (
    echo [INFO] Membuat directory project: %PROJECT_DIR%
    mkdir "%PROJECT_DIR%"
)

REM Copy files (asumsi script ini berada di folder yang sama dengan file web)
echo [INFO] Menyalin file web ke XAMPP htdocs...

copy "index.html" "%PROJECT_DIR%\" >nul 2>&1
copy "search_images.php" "%PROJECT_DIR%\" >nul 2>&1  
copy "get_all_images.php" "%PROJECT_DIR%\" >nul 2>&1
copy "config.php" "%PROJECT_DIR%\" >nul 2>&1
copy "status.php" "%PROJECT_DIR%\" >nul 2>&1

REM Create images symlink/junction
echo [INFO] Membuat symbolic link untuk images...
if exist "%PROJECT_DIR%\images" rmdir "%PROJECT_DIR%\images" >nul 2>&1
if exist "Z:\" (
    mklink /D "%PROJECT_DIR%\images" "Z:\" >nul 2>&1
    if %errorlevel% equ 0 (
        echo [SUCCESS] Symbolic link berhasil dibuat
    ) else (
        echo [WARNING] Gagal membuat symbolic link, mungkin perlu run as administrator
    )
) else (
    echo [WARNING] Network drive Z: belum terpasang
    echo [INFO] Jalankan mount_network.bat terlebih dahulu
)

REM Start XAMPP
echo [INFO] Memulai XAMPP Control Panel...
start "XAMPP Control" "C:\xampp\xampp-control.exe"

echo.
echo [SUCCESS] Setup selesai!
echo [INFO] Project location: %PROJECT_DIR%
echo [INFO] Local URL: http://localhost/master-design/
echo [INFO] Public URL: http://180.251.120.19:8081/master-design/
echo.
echo [INFO] Pastikan Apache sudah running di XAMPP Control Panel

:end
echo.
echo ========================================
echo Press any key to continue...
pause >nul


@echo off
REM check_status.bat - Check System Status
echo ========================================
echo         SYSTEM STATUS CHECKER
echo ========================================
echo.

echo [INFO] Checking Network Drive...
if exist "Z:\" (
    echo [OK] Drive Z: terpasang
    dir Z: >nul 2>&1
    if %errorlevel% equ 0 (
        echo [OK] Drive Z: dapat diakses
        for /f %%i in ('dir Z: /s *.jpg ^| find "File(s)"') do echo [INFO] JPG files found: %%i
    ) else (
        echo [ERROR] Drive Z: terpasang tapi tidak dapat diakses
    )
) else (
    echo [ERROR] Drive Z: tidak terpasang
    echo [ACTION] Jalankan mount_network.bat
)

echo.
echo [INFO] Checking XAMPP...
tasklist /fi "imagename eq httpd.exe" 2>nul | find /i "httpd.exe" >nul
if %errorlevel% equ 0 (
    echo [OK] Apache (httpd.exe) sedang berjalan
) else (
    echo [ERROR] Apache tidak berjalan
    echo [ACTION] Jalankan XAMPP Control Panel dan start Apache
)

tasklist /fi "imagename eq mysqld.exe" 2>nul | find /i "mysqld.exe" >nul
if %errorlevel% equ 0 (
    echo [OK] MySQL (mysqld.exe) sedang berjalan  
) else (
    echo [WARNING] MySQL tidak berjalan (opsional untuk project ini)
)

echo.
echo [INFO] Checking Project Files...
set PROJECT_DIR=C:\xampp\htdocs\master-design
if exist "%PROJECT_DIR%\index.html" (
    echo [OK] index.html ada
) else (
    echo [ERROR] index.html tidak ditemukan
)

if exist "%PROJECT_DIR%\search_images.php" (
    echo [OK] search_images.php ada  
) else (
    echo [ERROR] search_images.php tidak ditemukan
)

if exist "%PROJECT_DIR%\images" (
    echo [OK] images symlink/folder ada
) else (
    echo [ERROR] images symlink tidak ditemukan
)

echo.
echo [INFO] Testing URLs...
echo [LOCAL] http://localhost/master-design/
echo [PUBLIC] http://180.251.120.19:8081/master-design/

echo.
echo ========================================
echo Status check selesai
echo Press any key to continue...
pause >nul


@echo off
REM start_all.bat - Start Everything
echo ========================================
echo      MASTER DESIGN COMPLETE STARTUP
echo ========================================
echo.

echo [STEP 1] Mounting Network Drive...
call mount_network.bat

echo.
echo [STEP 2] Setting up XAMPP Project...  
call setup_xampp.bat

echo.
echo [STEP 3] Checking Status...
call check_status.bat

echo.
echo [SUCCESS] Startup sequence completed!
echo.
echo [INFO] Anda sekarang dapat mengakses web application di:
echo        Local:  http://localhost/master-design/
echo        Public: http://180.251.120.19:8081/master-design/
echo.
echo [INFO] Browser akan dibuka secara otomatis...
timeout /t 3 >nul
start http://localhost/master-design/

echo.
echo ========================================
echo Press any key to exit...
pause >nul


@echo off
REM install_dependencies.bat - Install required dependencies
echo ========================================
echo      DEPENDENCY INSTALLER
echo ========================================
echo.

echo [INFO] Checking and installing required components...

REM Check if running as administrator
net session >nul 2>&1
if %errorlevel% neq 0 (
    echo [WARNING] Script tidak dijalankan sebagai Administrator
    echo [INFO] Beberapa operasi mungkin gagal
    echo [INFO] Untuk hasil optimal, run as Administrator
    echo.
    timeout /t 3 >nul
)

REM Create main project directory structure
echo [INFO] Creating project structure...
if not exist "C:\xampp\htdocs\master-design" mkdir "C:\xampp\htdocs\master-design"
if not exist "C:\xampp\htdocs\master-design\assets" mkdir "C:\xampp\htdocs\master-design\assets"
if not exist "C:\xampp\htdocs\master-design\logs" mkdir "C:\xampp\htdocs\master-design\logs"

REM Create .htaccess for better security and performance
echo [INFO] Creating .htaccess file...
(
echo # Master Design Image Viewer - Apache Configuration
echo.
echo # Enable mod_rewrite
echo RewriteEngine On
echo.
echo # Security headers
echo Header always set X-Content-Type-Options nosniff
echo Header always set X-Frame-Options DENY
echo Header always set X-XSS-Protection "1; mode=block"
echo.
echo # Cache static assets
echo ^<FilesMatch "\.(css|js|png|jpg|jpeg|gif|ico|svg)$"^>
echo     ExpiresActive On
echo     ExpiresDefault "access plus 1 month"
echo ^</FilesMatch^>
echo.
echo # Prevent access to sensitive files
echo ^<FilesMatch "\.(log|bak|config)$"^>
echo     Order allow,deny
echo     Deny from all
echo ^</FilesMatch^>
) > "C:\xampp\htdocs\master-design\.htaccess"

echo [SUCCESS] Project structure created
echo.

REM Check PHP modules
echo [INFO] Checking PHP configuration...
php -m | find "gd" >nul
if %errorlevel% equ 0 (
    echo [OK] PHP GD extension available
) else (
    echo [WARNING] PHP GD extension not found
    echo [INFO] Image processing may be limited
)

php -m | find "fileinfo" >nul  
if %errorlevel% equ 0 (
    echo [OK] PHP FileInfo extension available
) else (
    echo [WARNING] PHP FileInfo extension not found
)

echo.
echo [SUCCESS] Dependencies check completed
echo.
echo ========================================
echo Press any key to continue...
pause >nul


@echo off  
REM backup_restore.bat - Backup and Restore functionality
echo ========================================
echo       BACKUP & RESTORE UTILITY
echo ========================================
echo.

set BACKUP_DIR=C:\Master-Design-Backup
set PROJECT_DIR=C:\xampp\htdocs\master-design

echo [MENU] Pilih operasi:
echo 1. Backup Project
echo 2. Restore Project  
echo 3. Exit
echo.
set /p choice="Masukkan pilihan (1-3): "

if "%choice%"=="1" goto :backup
if "%choice%"=="2" goto :restore  
if "%choice%"=="3" goto :end
goto :menu

:backup
echo.
echo [INFO] Starting backup process...
if not exist "%BACKUP_DIR%" mkdir "%BACKUP_DIR%"

set TIMESTAMP=%DATE:~-4,4%%DATE:~-10,2%%DATE:~-7,2%_%TIME:~0,2%%TIME:~3,2%%TIME:~6,2%
set TIMESTAMP=%TIMESTAMP: =0%

set BACKUP_FILE=%BACKUP_DIR%\master-design-backup-%TIMESTAMP%.zip

echo [INFO] Creating backup: %BACKUP_FILE%
if exist "%PROJECT_DIR%" (
    powershell Compress-Archive -Path "%PROJECT_DIR%\*" -DestinationPath "%BACKUP_FILE%" -Force
    if %errorlevel% equ 0 (
        echo [SUCCESS] Backup created successfully
        echo [INFO] Location: %BACKUP_FILE%
    ) else (
        echo [ERROR] Backup failed
    )
) else (
    echo [ERROR] Project directory not found: %PROJECT_DIR%
)
goto :end

:restore
echo.  
echo [INFO] Available backups:
if exist "%BACKUP_DIR%\*.zip" (
    dir /b "%BACKUP_DIR%\*.zip"
    echo.
    set /p backup_file="Enter backup filename: "
    
    if exist "%BACKUP_DIR%\%backup_file%" (
        echo [INFO] Restoring from: %backup_file%
        if exist "%PROJECT_DIR%" rmdir /s /q "%PROJECT_DIR%"
        mkdir "%PROJECT_DIR%"
        
        powershell Expand-Archive -Path "%BACKUP_DIR%\%backup_file%" -DestinationPath "%PROJECT_DIR%" -Force
        if %errorlevel% equ 0 (
            echo [SUCCESS] Restore completed successfully
        ) else (
            echo [ERROR] Restore failed
        )
    ) else (
        echo [ERROR] Backup file not found
    )
) else (
    echo [INFO] No backup files found in %BACKUP_DIR%
)
goto :end

:end
echo.
echo ========================================
echo Press any key to exit...
pause >nul


@echo off
REM troubleshoot.bat - Troubleshooting utility  
echo ========================================
echo         TROUBLESHOOTING UTILITY
echo ========================================
echo.

echo [INFO] Running comprehensive system check...
echo.

REM Check 1: Network connectivity
echo [CHECK 1] Network Connectivity
ping -n 1 192.168.0.204 >nul 2>&1
if %errorlevel% equ 0 (
    echo [OK] Server 192.168.0.204 is reachable
) else (
    echo [ERROR] Cannot reach server 192.168.0.204
    echo [FIX] Check network connection and server status
)

REM Check 2: Network drive
echo.
echo [CHECK 2] Network Drive Status  
if exist "Z:\" (
    echo [OK] Drive Z: is mounted
    dir Z: >nul 2>&1
    if %errorlevel% equ 0 (
        echo [OK] Drive Z: is accessible
        for /f "tokens=3" %%i in ('dir Z: /-c ^| find "File(s)"') do (
            if "%%i" neq "" echo [INFO] Files found on Z: drive
        )
    ) else (
        echo [ERROR] Drive Z: mounted but not accessible
        echo [FIX] Try: net use Z: /delete then remount
    )
) else (
    echo [ERROR] Drive Z: not mounted
    echo [FIX] Run mount_network.bat
)

REM Check 3: XAMPP Status
echo.
echo [CHECK 3] XAMPP Status
if exist "C:\xampp\xampp-control.exe" (
    echo [OK] XAMPP is installed
    
    tasklist /fi "imagename eq httpd.exe" 2>nul | find /i "httpd.exe" >nul
    if %errorlevel% equ 0 (
        echo [OK] Apache is running
        
        REM Test localhost connection
        powershell -command "try { Invoke-WebRequest -Uri 'http://localhost' -TimeoutSec 5 -UseBasicParsing | Out-Null; Write-Host '[OK] Apache responding to requests' } catch { Write-Host '[ERROR] Apache not responding' }"
    ) else (
        echo [ERROR] Apache is not running  
        echo [FIX] Start Apache in XAMPP Control Panel
    )
) else (
    echo [ERROR] XAMPP not found
    echo [FIX] Install XAMPP from https://www.apachefriends.org/
)

REM Check 4: Project Files
echo.
echo [CHECK 4] Project Files
set PROJECT_DIR=C:\xampp\htdocs\master-design
if exist "%PROJECT_DIR%" (
    echo [OK] Project directory exists
    
    set file_count=0
    if exist "%PROJECT_DIR%\index.html" (
        echo [OK] index.html found
        set /a file_count+=1
    ) else (
        echo [ERROR] index.html missing
    )
    
    if exist "%PROJECT_DIR%\search_images.php" (
        echo [OK] search_images.php found  
        set /a file_count+=1
    ) else (
        echo [ERROR] search_images.php missing
    )
    
    if exist "%PROJECT_DIR%\get_all_images.php" (
        echo [OK] get_all_images.php found
        set /a file_count+=1  
    ) else (
        echo [ERROR] get_all_images.php missing
    )
    
    if %file_count% lss 3 (
        echo [WARNING] Some project files are missing
        echo [FIX] Run setup_xampp.bat to restore files
    )
) else (
    echo [ERROR] Project directory not found
    echo [FIX] Run setup_xampp.bat
)

REM Check 5: PHP Configuration
echo.
echo [CHECK 5] PHP Configuration
php -v >nul 2>&1
if %errorlevel% equ 0 (
    echo [OK] PHP is available
    php -m | find "gd" >nul 2>&1
    if %errorlevel% equ 0 (
        echo [OK] PHP GD extension loaded
    ) else (
        echo [WARNING] PHP GD extension not found
    )
) else (
    echo [ERROR] PHP not found in PATH
    echo [FIX] Add C:\xampp\php to system PATH
)

REM Check 6: Firewall and Ports
echo.
echo [CHECK 6] Port and Firewall Status
netstat -an | find ":80 " | find "LISTENING" >nul
if %errorlevel% equ 0 (
    echo [OK] Port 80 is listening
) else (
    echo [WARNING] Port 80 not listening
)

netstat -an | find ":8081 " | find "LISTENING" >nul  
if %errorlevel% equ 0 (
    echo [OK] Port 8081 is listening
) else (
    echo [WARNING] Port 8081 not listening - check Apache configuration
)

echo.
echo [INFO] Troubleshooting completed
echo [INFO] If issues persist, check the logs in:
echo        - C:\xampp\apache\logs\error.log
echo        - C:\xampp\htdocs\master-design\logs\
echo.
echo ========================================
echo Press any key to exit...
pause >nul