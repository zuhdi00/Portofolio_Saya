// Konfigurasi frontend. API = alamat api.php (proxy di hosting, atau api.php backend jika di PC internal).
window.APP_CONFIG = {
  API: "api.php",
  COMPANY: "PT Supracor Sejahtera",
  LABEL_HEADER: "PT SUPRACOR SEJAHTERA · SCAN UNTUK LAPOR",
  DIVISI: ["Produksi", "PPIC", "Gudang", "QC", "Maintenance", "IT / EDP", "HRD / GA", "K3 / HSE", "Finance", "Security", "Lainnya"],
  MAX_PHOTOS: 12,
  ALLOW_MANUAL: false,     // true = tampilkan pilih lokasi / ketik kode / foto barcode (untuk admin)
  AUTO_PHOTO_DELAY: 3      // detik hitung mundur foto otomatis setelah scan (0 = langsung)
};
