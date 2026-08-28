-- MYSQL/MARIADB ONLY: jalankan di phpMyAdmin atau mysql client, bukan SSMS.
CREATE TABLE IF NOT EXISTS `phk` (
  `id_phk` int NOT NULL AUTO_INCREMENT,
  `id_karyawan` int NOT NULL,
  `tanggal_phk` date NOT NULL,
  `alasan_phk` text NOT NULL,
  `status_kompensasi` enum('Diberikan','Tidak Diberikan') NOT NULL,
  `jumlah_kompensasi` decimal(10,0) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id_phk`),
  KEY `idx_phk_id_karyawan` (`id_karyawan`),
  KEY `idx_phk_tanggal` (`tanggal_phk`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;