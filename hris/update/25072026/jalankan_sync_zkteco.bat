@echo off
REM =====================================================================
REM  jalankan_sync_zkteco.bat
REM  Menjalankan impor + proses absensi ZKTeco secara berurutan.
REM  Dipanggil Task Scheduler tiap 15 menit.
REM
REM  Urutan: import (MDB -> staging) lalu proses (staging -> absensi).
REM  Keduanya idempoten - aman diulang, tidak menghasilkan duplikat.
REM =====================================================================

REM ---------- SESUAIKAN ----------
set PHP=C:\xampp\php\php.exe
set DIR=C:\xampp\htdocs\hris\presensi
set LOG=C:\zkteco_data\sync.log
REM -------------------------------

echo. >> "%LOG%"
echo ================ %date% %time% ================ >> "%LOG%"

echo [1/2] Impor tap... >> "%LOG%"
"%PHP%" "%DIR%\sync_zkteco_import.php" >> "%LOG%" 2>&1

echo [2/2] Proses absensi... >> "%LOG%"
"%PHP%" "%DIR%\sync_zkteco_proses.php" >> "%LOG%" 2>&1

echo Selesai. >> "%LOG%"
exit /b 0
