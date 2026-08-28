@echo off
REM =====================================================================
REM  sync_langsung_zkteco.bat  [tanpa salin - baca langsung dari \\spsdmz]
REM  Hanya impor + proses. MDB dibaca langsung dari share jaringan
REM  (koneksi_mdb.php mode 'unc'). Lebih cepat karena tak ada salin 2 GB.
REM
REM  SYARAT: akun yang menjalankan (Task Scheduler / Apache) HARUS punya
REM          akses baca ke \\spsdmz\gg$\HRD\CheckClock
REM =====================================================================

set DIR=C:\xampp\htdocs\hris\presensi
set PHP=C:\xampp\php\php.exe
set LOG=C:\zkteco_data\sync.log

if not exist "C:\zkteco_data" mkdir "C:\zkteco_data"

echo. >> "%LOG%"
echo ################ %date% %time% ################ >> "%LOG%"

echo [1/2] Impor tap (baca langsung dari jaringan)... >> "%LOG%"
"%PHP%" "%DIR%\sync_zkteco_import.php" >> "%LOG%" 2>&1

echo [2/2] Proses absensi... >> "%LOG%"
"%PHP%" "%DIR%\sync_zkteco_proses.php" >> "%LOG%" 2>&1

echo Selesai %date% %time%. >> "%LOG%"
exit /b 0
