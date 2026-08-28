@echo off
REM Lepas dulu drive Z: jika sudah ter-mount
net use Z: /delete /y
timeout /t 2

REM Mount ulang drive Z:
net use Z: "\\192.168.0.204\Master Design" /user:EDP2 PASSWORD /persistent:yes

echo Selesai. Cek hasil dengan 'net use' atau 'dir Z:\'.
pause
