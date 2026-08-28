@echo off
REM Ganti "Z:" dengan huruf drive yang belum terpakai
REM Ganti user dan password sesuai server file
net use Z: "\\192.168.0.204\Master Design" /user:USERNAME PASSWORD /persistent:yes

if %ERRORLEVEL% == 0 (
    echo Drive berhasil di-mount ke Z:
) else (
    echo Gagal mount drive. Periksa koneksi atau kredensial.
)
pause
