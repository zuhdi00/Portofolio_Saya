@echo off
REM Improved mount_for_system.bat
REM - Detects Sysnative/System32 for net.exe
REM - Uses PsExec if present, otherwise scheduled task fallback
REM - Logs output to mount_for_system.log in the script folder

setlocal enabledelayedexpansion
set LOGFILE=%~dp0mount_for_system.log
echo ===== Mount run at %date% %time% =====>>"%LOGFILE%"

echo Stopping Apache...>>"%LOGFILE%"
net stop Apache2.4 2>>"%LOGFILE%" 1>>"%LOGFILE%"
taskkill /f /im httpd.exe 2>>"%LOGFILE%" 1>>"%LOGFILE%"

REM Resolve correct net.exe path (prefer Sysnative when present)
set "NETSYS=%windir%\System32\net.exe"
if exist "%windir%\Sysnative\net.exe" set "NETSYS=%windir%\Sysnative\net.exe"
echo Using net executable: %NETSYS%>>"%LOGFILE%"

REM Target UNC and credentials - update if needed
set "UNC=\\\\192.168.0.204\\Master Design"
set "DRIVE=Z:"
set "USER=EDP2"
set "PASS=PASSWORD"

REM Try PsExec if available
if exist "%~dp0PsExec.exe" (
    echo Using PsExec to run net use as SYSTEM...>>"%LOGFILE%"
    "%~dp0PsExec.exe" -accepteula -s -i "%NETSYS%" use %DRIVE% "%UNC%" /user:%USER% %PASS% /persistent:yes >>"%LOGFILE%" 2>&1
) else (
    echo PsExec not found; creating scheduled task as fallback...>>"%LOGFILE%"
    REM compute near-future time for schtasks
    for /f "tokens=1-2 delims=:" %%a in ("%time%") do (
        set "hh=%%a"
        set "mm=%%b"
    )
    set "hh=!hh: =0!"
    set "schtime=!hh!:!mm:~0,2!"
    echo Scheduling schtasks to run at !schtime! >>"%LOGFILE%"
    schtasks /create /tn "MountNetworkDrive" /tr "%COMSPEC% /c \"%NETSYS% use %DRIVE% %UNC% /user:%USER% %PASS% /persistent:yes\"" /sc once /st !schtime! /ru SYSTEM /f >>"%LOGFILE%" 2>&1
    timeout /t 2 >>"%LOGFILE%" 2>&1
    schtasks /run /tn "MountNetworkDrive" >>"%LOGFILE%" 2>&1
    timeout /t 4 >>"%LOGFILE%" 2>&1
    schtasks /delete /tn "MountNetworkDrive" /f >>"%LOGFILE%" 2>&1
)

REM Show mapping status
echo Mapping status: >>"%LOGFILE%"
"%NETSYS%" use >>"%LOGFILE%" 2>&1 || echo net use command failed >>"%LOGFILE%"

REM Try to start Apache
echo Starting Apache...>>"%LOGFILE%"
net start Apache2.4 >>"%LOGFILE%" 2>&1 || echo "net start Apache2.4" may have failed >>"%LOGFILE%"

echo Completed at %date% %time% >>"%LOGFILE%"
echo Log written to %LOGFILE%
pause
endlocal