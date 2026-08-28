@echo off
REM =====================================================================
REM  sync_lengkap_zkteco.bat  [GABUNGAN - satu task saja]
REM  Menjalankan 3 langkah berurutan:
REM    1. Salin MDB dari server (arsip lalu ganti)
REM    2. Impor tap -> staging
REM    3. Proses -> absensi
REM  Cukup dijadwalkan SATU kali di Task Scheduler, tiap 15 menit.
REM =====================================================================

set DIR=C:\xampp\htdocs\hris\presensi
set PHP=C:\xampp\php\php.exe
set LOG=C:\zkteco_data\sync.log

echo. >> "%LOG%"
echo ################ %date% %time% ################ >> "%LOG%"

echo [1/3] Salin MDB... >> "%LOG%"
call "%DIR%\salin_mdb_zkteco.bat"
if errorlevel 1 (
    echo Salin gagal - impor tetap dicoba pakai MDB lama. >> "%LOG%"
)

echo [2/3] Impor tap... >> "%LOG%"
"%PHP%" "%DIR%\sync_zkteco_import.php" >> "%LOG%" 2>&1

echo [3/3] Proses absensi... >> "%LOG%"
"%PHP%" "%DIR%\sync_zkteco_proses.php" >> "%LOG%" 2>&1

echo Selesai %date% %time%. >> "%LOG%"
exit /b 0
