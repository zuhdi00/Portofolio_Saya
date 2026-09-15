-- Menggunakan database hris
USE hris;

-- Hapus tabel jika sudah ada
DROP TABLE IF EXISTS pelatihan;

-- Buat tabel baru dengan struktur yang benar
CREATE TABLE `pelatihan` (
  `id_pelatihan` int(11) NOT NULL AUTO_INCREMENT,
  `nama_pelatihan` varchar(150) NOT NULL,
  `deskripsi_pelatihan` varchar(150) NOT NULL,
  `tgl_pelaksanaan` date NOT NULL,
  `jam_pelaksanaan` time NOT NULL,
  `durasi_pelatihan` varchar(150) NOT NULL,
  `lokasi_pelatihan` varchar(150) NOT NULL,
  `pemateri_pelatihan` varchar(150) NOT NULL,
  `status_pelatihan` enum('Terlaksana','Tidak Terlaksana') NOT NULL,
  `id_pegawai` int(11),
  `kapasitas` varchar(150) NOT NULL,
  PRIMARY KEY (`id_pelatihan`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- Menambahkan data contoh
INSERT INTO pelatihan (
    nama_pelatihan,
    deskripsi_pelatihan,
    tgl_pelaksanaan,
    jam_pelaksanaan,
    durasi_pelatihan,
    lokasi_pelatihan,
    pemateri_pelatihan,
    status_pelatihan,
    kapasitas
) VALUES 
('Pelatihan Web Development', 'Pelatihan dasar pengembangan web', '2024-03-20', '09:00:00', '3 Jam', 'Ruang Meeting Lt.2', 'Budi Santoso', 'Tidak Terlaksana', '30 Orang'),
('Workshop Digital Marketing', 'Workshop strategi pemasaran digital', '2024-03-15', '13:00:00', '4 Jam', 'Aula Utama', 'Siti Aminah', 'Terlaksana', '50 Orang');