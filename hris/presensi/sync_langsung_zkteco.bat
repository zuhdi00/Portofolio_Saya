@echo off
setlocal
REM =====================================================================
REM  sync_langsung_zkteco.bat   [v2 - 13-08-2026]
REM  Impor + proses ZKTeco. MDB dibaca langsung dari \\spsdmz (mode 'unc').
REM
REM  Perbaikan v2:
REM   - Berhenti kalau impor GAGAL (dulu tetap lanjut proses -> data kacau)
REM   - Kunci anti-tumpang-tindih: kalau siklus sebelumnya belum selesai,
REM     siklus baru dilewati (dulu bisa jalan 2x bersamaan -> tap terbelah)
REM   - Log dipotong otomatis kalau sudah > 20 MB
REM   - Exit code dikembalikan supaya Task Scheduler menandai Failed
REM
REM  SYARAT: akun yang menjalankan HARUS punya akses baca ke
REM          \\spsdmz\gg$\HRD\CheckClock
REM =====================================================================

set DIR=C:\xampp\htdocs\hris\presensi
set PHP=C:\xampp\php\php.exe
set LOG=C:\zkteco_data\sync.log
set LOCK=C:\zkteco_data\sync.lock

if not exist "C:\zkteco_data" mkdir "C:\zkteco_data"

REM ---------- kunci: jangan jalan dua kali bersamaan ----------
if exist "%LOCK%" (
  echo %date% %time% - DILEWATI: siklus sebelumnya masih berjalan. >> "%LOG%"
  exit /b 0
)
echo %date% %time% > "%LOCK%"

REM ---------- potong log kalau kebesaran (>20 MB) ----------
for %%A in ("%LOG%") do if %%~zA GTR 20971520 (
  move /Y "%LOG%" "%LOG%.old" >nul
)

echo. >> "%LOG%"
echo ################ %date% %time% ################ >> "%LOG%"

echo [1/2] Impor tap (baca langsung dari jaringan)... >> "%LOG%"
"%PHP%" "%DIR%\sync_zkteco_import.php" >> "%LOG%" 2>&1
if errorlevel 1 (
  echo *** IMPOR GAGAL - proses absensi DIBATALKAN *** >> "%LOG%"
  del "%LOCK%" >nul 2>&1
  exit /b 1
)

echo [2/2] Proses absensi... >> "%LOG%"
"%PHP%" "%DIR%\sync_zkteco_proses.php" >> "%LOG%" 2>&1
if errorlevel 1 (
  echo *** PROSES ABSENSI GAGAL *** >> "%LOG%"
  del "%LOCK%" >nul 2>&1
  exit /b 1
)

echo Selesai %date% %time%. >> "%LOG%"
del "%LOCK%" >nul 2>&1
exit /b 0
