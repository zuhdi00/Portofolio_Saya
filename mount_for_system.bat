@echo off
REM Mount network drive untuk SYSTEM user (untuk PHP)
echo Mounting network drive for SYSTEM user...

REM Stop Apache/XAMPP dulu
net stop Apache2.4 2>nul
taskkill /f /im httpd.exe 2>nul

REM Mount dengan SYSTEM context menggunakan PsExec
REM Download PsExec dari Microsoft Sysinternals jika belum ada
if exist "%~dp0PsExec.exe" (
    echo Using PsExec to mount as SYSTEM...
    "%~dp0PsExec.exe" -s net use Z: "\\192.168.0.204\Master Design" /user:EDP2 PASSWORD /persistent:yes
) else (
    echo PsExec not found. Using alternative method...
    
    REM Alternative: Use AT command (deprecated but might work)
    REM at %TIME:~0,2%:%TIME:~3,2% /interactive net use Z: "\\192.168.0.204\Master Design" /user:EDP2 PASSWORD
    
    REM Better alternative: Use scheduled task
    set hour=0%TIME:~0,2%
    set hour=%hour:~-2%
    set min=0%TIME:~3,2%
    set min=%min:~-2%
    set st=%hour%:%min%
    schtasks /create /tn "MountNetworkDrive" /tr "\"net use Z: \\192.168.0.204\Master Design /user:EDP2 PASSWORD /persistent:yes\"" /sc once /st %st% /ru SYSTEM /f
    timeout /t 2
    schtasks /run /tn "MountNetworkDrive"
    timeout /t 3
    schtasks /delete /tn "MountNetworkDrive" /f
)

echo Testing mount...
net use

echo Starting Apache...
net start Apache2.4

echo Done! Test PHP access now.
pause