@echo off
REM mount_network_drive.bat
REM Maps a network share to a drive letter and restarts Apache service.
REM Run this script as Administrator.

SETLOCAL ENABLEDELAYEDEXPANSION

:: Configuration - edit if needed
set "SHARE=\\192.168.0.204\Master Design"
set "DRIVE=Z:"
set "SERVICE=Apache2.4"

echo ==============================================
echo Mount network share helper
echo ==============================================
echo Share: %SHARE%
echo Drive : %DRIVE%
echo Service: %SERVICE%
echo.

:: If drive already mapped, remove it first
net use %DRIVE% >nul 2>&1
if %errorlevel%==0 (
    echo %DRIVE% already mapped. Removing existing mapping...
    net use %DRIVE% /delete /y >nul 2>&1
)

:: Prompt for credentials
set /p USER=Enter username (DOMAIN\user or user): 
set /p PASS=Enter password (visible): 

echo Mapping %DRIVE% to %SHARE% ...
net use %DRIVE% "%SHARE%" /user:%USER% %PASS% /persistent:yes
if %errorlevel% neq 0 (
    echo Failed to map drive. Please verify credentials and network access.
    echo You can try mounting manually with: net use %DRIVE% "%SHARE%" /user:DOMAIN\\user YourPassword
    pause
    exit /b 1
)

echo Drive mapped successfully.

:: Restart Apache service if present
sc query "%SERVICE%" >nul 2>&1
if %errorlevel%==0 (
    echo Restarting service %SERVICE% ...
    net stop "%SERVICE%" >nul 2>&1
    timeout /t 2 >nul 2>&1
    net start "%SERVICE%" >nul 2>&1
    if %errorlevel%==0 (
        echo Service %SERVICE% restarted successfully.
    ) else (
        echo Failed to restart service %SERVICE%. You may need to restart Apache from XAMPP control panel.
    )
) else (
    echo Service %SERVICE% not found. If you use XAMPP, restart Apache via the XAMPP Control Panel.
)

echo.
echo Done. Test your PHP script now.
pause

ENDLOCAL
