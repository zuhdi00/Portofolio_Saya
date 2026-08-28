# Pastikan script dijalankan sebagai Admin
if (-not ([Security.Principal.WindowsPrincipal] [Security.Principal.WindowsIdentity]::GetCurrent()).IsInRole([Security.Principal.WindowsBuiltInRole] "Administrator")) {
    Write-Host "Jalankan PowerShell sebagai Administrator!" -ForegroundColor Red
    exit
}

$FolderPath = "C:\xampp\htdocs\masterdesign"
$HttpdConfPath = "C:\xampp\apache\conf\httpd.conf"
$ApacheBinPath = "C:\xampp\apache\bin"

Write-Host "=== [1] Mengecek folder masterdesign ===" -ForegroundColor Cyan
if (-not (Test-Path $FolderPath)) {
    Write-Host "[ERROR] Folder tidak ditemukan: $FolderPath" -ForegroundColor Red
    exit
}

# Cek apakah folder adalah symlink
$Item = Get-Item $FolderPath -Force
if ($Item.Attributes -match "ReparsePoint") {
    Write-Host "[INFO] masterdesign adalah symlink" -ForegroundColor Yellow
} else {
    Write-Host "[INFO] masterdesign adalah folder biasa" -ForegroundColor Green
}

Write-Host "`n=== [2] Mengatur hak akses Everyone Full Control ===" -ForegroundColor Cyan
icacls $FolderPath /grant Everyone:(OI)(CI)F /T

Write-Host "`n=== [3] Backup httpd.conf ===" -ForegroundColor Cyan
$BackupPath = "$HttpdConfPath.backup"
Copy-Item $HttpdConfPath $BackupPath -Force
Write-Host "[INFO] Backup tersimpan di: $BackupPath" -ForegroundColor Green

Write-Host "`n=== [4] Update konfigurasi Apache ===" -ForegroundColor Cyan
$ConfigBlock = @"
<Directory "C:/xampp/htdocs/masterdesign">
    Options Indexes FollowSymLinks
    AllowOverride All
    Require all granted
</Directory>

EnableSendfile Off
EnableMMAP Off
"@

# Tambahkan hanya jika belum ada
if (-not (Select-String -Path $HttpdConfPath -Pattern '<Directory "C:/xampp/htdocs/masterdesign">')) {
    Add-Content -Path $HttpdConfPath -Value "`n$ConfigBlock"
    Write-Host "[INFO] Konfigurasi baru ditambahkan ke httpd.conf" -ForegroundColor Green
} else {
    Write-Host "[INFO] Konfigurasi sudah ada, dilewati" -ForegroundColor Yellow
}

Write-Host "`n=== [5] Restart Apache ===" -ForegroundColor Cyan
Set-Location $ApacheBinPath
Start-Process "httpd.exe" -ArgumentList "-k restart" -Wait
Write-Host "[INFO] Apache berhasil direstart" -ForegroundColor Green

Write-Host "`n=== [6] Tes di browser ===" -ForegroundColor Cyan
Start-Process "http://localhost/masterdesign/"
