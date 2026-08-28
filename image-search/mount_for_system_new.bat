@echo off
REM filepath: c:\xampp\htdocs\mount_for_system.bat

REM Stop Apache/XAMPP dulu
net stop Apache2.4 2>nul
taskkill /f /im httpd.exe 2>nul

REM Cek PsExec, mount sebagai SYSTEM jika ada
if exist "%~dp0PsExec.exe" (
    echo Using PsExec to mount as SYSTEM...
    "%~dp0PsExec.exe" -s net use Z: "\\192.168.0.204\Master Design" /user:EDP2 PASSWORD /persistent:yes
) else (
    echo PsExec not found. Using scheduled task...
    REM Ambil jam dan menit, pastikan dua digit
    set hour=%time:~0,2%
    set min=%time:~3,2%
    if "%hour:~0,1%"==" " set hour=0%hour:~1,1%
    if "%min:~0,1%"==" " set min=0%min:~1,1%
    set st=%hour%:%min%
    schtasks /create /tn "MountNetworkDrive" /tr "net use Z: \"\\192.168.0.204\Master Design\" /user:EDP2 PASSWORD /persistent:yes" /sc once /st %st% /ru SYSTEM /f
    timeout /t 2
    schtasks /run /tn "MountNetworkDrive"
    timeout /t 3
    schtasks /delete /tn "MountNetworkDrive" /f
)

echo Testing mount...
net use

REM Start Apache/XAMPP lagi
net start Apache2.4

echo Done! Test PHP access now.
pause