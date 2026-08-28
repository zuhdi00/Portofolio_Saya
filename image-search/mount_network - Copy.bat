@echo off
REM Script untuk mount network drive
REM Jalankan sebagai Administrator jika diperlukan

echo Mounting network drive...

REM Ganti USERNAME dan PASSWORD dengan kredensial yang sesuai
set NETWORK_PATH=\\192.168.0.204\Master Design
set USERNAME=your_username
set PASSWORD=your_password

REM Mount sebagai drive Z: (atau drive lain yang tersedia)
net use Z: "%NETWORK_PATH%" /user:%USERNAME% %PASSWORD% /persistent:yes

if %errorlevel% equ 0 (
    echo Network drive mounted successfully as Z:
) else (
    echo Failed to mount network drive
    echo Error code: %errorlevel%
    pause
    exit /b %errorlevel%
)

pause