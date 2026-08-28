# ====== Cek Akses & Apache untuk Symbolic Link ======

# 1. Konfigurasi
$targetFolder = "C:\xampp\htdocs\masterdesign"
$apacheConf   = "C:\xampp\apache\conf\httpd.conf"

Write-Host "=== 1. CEK FOLDER & HAK AKSES ===" -ForegroundColor Cyan

# Cek apakah folder ada
if (Test-Path $targetFolder) {
    Write-Host "Folder ditemukan: $targetFolder" -ForegroundColor Green
} else {
    Write-Host "Folder TIDAK ditemukan: $targetFolder" -ForegroundColor Red
}

# Cek apakah ini symbolic link/junction
$attrib = Get-Item $targetFolder -Force | Select-Object Attributes
if ($attrib.Attributes -match "ReparsePoint") {
    Write-Host "Folder ini adalah Symbolic Link / Junction" -ForegroundColor Yellow
} else {
    Write-Host "Folder ini BUKAN symbolic link" -ForegroundColor Gray
}

# Cek hak akses
Write-Host "`nHak akses folder:"
icacls $targetFolder

Write-Host "`n=== 2. CEK KONFIGURASI APACHE ===" -ForegroundColor Cyan

# Baca isi httpd.conf
if (Test-Path $apacheConf) {
    $confLines = Get-Content $apacheConf

    # Cek FollowSymLinks
    if ($confLines -match "Options.*FollowSymLinks") {
        Write-Host "FollowSymLinks sudah aktif di httpd.conf" -ForegroundColor Green
    } else {
        Write-Host "FollowSymLinks BELUM aktif di httpd.conf" -ForegroundColor Red
    }

    # Cek AllowOverride
    if ($confLines -match "AllowOverride\s+All") {
        Write-Host "AllowOverride All sudah aktif di httpd.conf" -ForegroundColor Green
    } else {
        Write-Host "AllowOverride All BELUM aktif di httpd.conf" -ForegroundColor Red
    }
} else {
    Write-Host "httpd.conf tidak ditemukan di $apacheConf" -ForegroundColor Red
}

Write-Host "`n=== 3. SARAN TINDAKAN ===" -ForegroundColor Cyan
Write-Host "1. Pastikan Apache dijalankan sebagai Administrator."
Write-Host "2. Jika FollowSymLinks atau AllowOverride belum aktif, edit httpd.conf:"
Write-Host "   - Cari bagian <Directory \"C:/xampp/htdocs\">"
Write-Host "   - Tambahkan: Options Indexes FollowSymLinks"
Write-Host "                AllowOverride All"
Write-Host "3. Restart Apache setelah perubahan."
