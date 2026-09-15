-- phpMyAdmin SQL Dump
-- version 5.2.0
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: Feb 03, 2025 at 01:19 PM
-- Server version: 8.0.30
-- PHP Version: 8.1.10

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `hris`
--

-- --------------------------------------------------------

--
-- Table structure for table `absensi`
--

CREATE TABLE `absensi` (
  `ID_Absensi` int NOT NULL,
  `ID_Pegawai` int NOT NULL,
  `Tanggal_Waktu` datetime NOT NULL,
  `Status_Kehadiran` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `Metode_Verifikasi` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `Lokasi_IP` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `analisis_sdm`
--

CREATE TABLE `analisis_sdm` (
  `id_analisis` int NOT NULL,
  `judul_analisis` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `deskripsi` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `tanggal_analisis` date NOT NULL,
  `jenis_analisis` enum('Kehadiran','Kinerja','Pelatihan','Penghargaan') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `departemen`
--

CREATE TABLE `departemen` (
  `id_departemen` int NOT NULL,
  `nama_departemen` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `kepala_departemen` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `lokasi_departemen` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `departemen`
--

INSERT INTO `departemen` (`id_departemen`, `nama_departemen`, `kepala_departemen`, `lokasi_departemen`) VALUES
(1, 'Sumber Daya Manusia', 'Diana Putri', 'Lantai 3, Gedung A');

-- --------------------------------------------------------

--
-- Table structure for table `dokumen_pendukung`
--

CREATE TABLE `dokumen_pendukung` (
  `id` int NOT NULL,
  `kontrak_id` int NOT NULL,
  `nama_dokumen` varchar(255) NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `tanggal_upload` datetime DEFAULT CURRENT_TIMESTAMP,
  `keterangan` text
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `jabatan`
--

CREATE TABLE `jabatan` (
  `id_jabatan` int NOT NULL,
  `nama_jabatan` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `desk_jabatan` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `level_jabatan` tinyint NOT NULL,
  `status_jabatan` enum('Tersedia','Tidak Tersedia') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `id_departemen` int NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `jadwal_kehadiran`
--

CREATE TABLE `jadwal_kehadiran` (
  `ID_Jadwal` int NOT NULL,
  `ID_Pegawai` int NOT NULL,
  `Hari` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `Jam_Masuk` time NOT NULL,
  `Jam_Keluar` time NOT NULL,
  `Keterangan` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `jenis_cuti`
--

CREATE TABLE `jenis_cuti` (
  `id_jenis_cuti` int NOT NULL,
  `nama_jenis_cuti` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `keterangan` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `kedisiplinan`
--

CREATE TABLE `kedisiplinan` (
  `id_kedisiplinan` int NOT NULL,
  `sanksi` varchar(255) NOT NULL,
  `jenis_pelanggaran` varchar(255) NOT NULL,
  `id_peg` int NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `kedisiplinan`
--

INSERT INTO `kedisiplinan` (`id_kedisiplinan`, `sanksi`, `jenis_pelanggaran`, `id_peg`) VALUES
(1, 'denda', 'cabul', 2123034),
(2, 'skors 1 hari', 'telat', 2123035),
(3, 'denda', 'makan di kelas', 212121);

-- --------------------------------------------------------

--
-- Table structure for table `keterlambatan dan ketidakhadiran`
--

CREATE TABLE `keterlambatan dan ketidakhadiran` (
  `ID_ketidakhadiran` int NOT NULL,
  `Tanggal` date NOT NULL,
  `Jam_Masuk_Terlambat` time NOT NULL,
  `Alasan_Keterlambatan` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `kontrak_pegawai`
--

CREATE TABLE `kontrak_pegawai` (
  `id` int NOT NULL,
  `pegawai_id` int NOT NULL,
  `nomor_kontrak` varchar(50) NOT NULL,
  `tanggal_mulai` date NOT NULL,
  `tanggal_berakhir` date NOT NULL,
  `jabatan` varchar(100) NOT NULL,
  `gaji` decimal(15,2) NOT NULL,
  `status_kontrak` enum('Aktif','Non-Aktif') DEFAULT 'Aktif',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `kontrak_pegawai`
--

INSERT INTO `kontrak_pegawai` (`id`, `pegawai_id`, `nomor_kontrak`, `tanggal_mulai`, `tanggal_berakhir`, `jabatan`, `gaji`, `status_kontrak`, `created_at`, `updated_at`) VALUES
(1, 111111, '21', '2222-02-12', '2222-02-22', 'direktur', '10000000.00', 'Aktif', '2025-02-03 19:16:35', '2025-02-03 19:16:35'),
(2, 2123026, '99', '2025-02-03', '0026-01-31', 'direktur utama', '10000000.00', 'Aktif', '2025-02-03 19:33:08', '2025-02-03 19:33:08');

-- --------------------------------------------------------

--
-- Table structure for table `kpi`
--

CREATE TABLE `kpi` (
  `id_kpi` int NOT NULL,
  `deskripsi` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `target` varchar(128) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `bobot` varchar(128) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `id_peg` int NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `laporan_sdm`
--

CREATE TABLE `laporan_sdm` (
  `id_laporan` int NOT NULL,
  `judul_laporan` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `periode_awal` date NOT NULL,
  `periode_akhir` date NOT NULL,
  `isi_laporan` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `tanggal_dibuat` date NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `lowongan`
--

CREATE TABLE `lowongan` (
  `id_lowongan` int NOT NULL,
  `nama_lowongan` varchar(128) NOT NULL,
  `deskripsi_lowongan` text NOT NULL,
  `id_jabatan` int NOT NULL,
  `tgl_posting` date NOT NULL,
  `tgl_tutup` date NOT NULL,
  `status` enum('Tersedia','Tidak Tersedia') NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

-- --------------------------------------------------------

--
-- Table structure for table `mutasi`
--

CREATE TABLE `mutasi` (
  `id_mutasi` int NOT NULL,
  `id_pegawai` int NOT NULL,
  `dep_lama` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `dep_baru` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `tgl_mutasi` date NOT NULL,
  `alasan_mutasi` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `pegawai`
--

CREATE TABLE `pegawai` (
  `id_peg` int NOT NULL,
  `nama_pegawai` varchar(255) NOT NULL,
  `jabatan` varchar(255) NOT NULL,
  `departemen` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `pelamar`
--

CREATE TABLE `pelamar` (
  `id_pelamar` int NOT NULL,
  `nama_pel` varchar(128) NOT NULL,
  `email_pel` varchar(128) NOT NULL,
  `id_lowongan` int NOT NULL,
  `status_pel` enum('Diterima','Ditolak') NOT NULL,
  `jabatan_dipilih` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

-- --------------------------------------------------------

--
-- Table structure for table `penggajian`
--

CREATE TABLE `penggajian` (
  `id_jabatan` int NOT NULL,
  `nama_jabatan` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `id_penggajian` int NOT NULL,
  `gaji_pokok` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `id_tunjangan` int NOT NULL,
  `tj_transportasi` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `tj_kesehatan` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `bonus` decimal(50,0) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `penghargaan`
--

CREATE TABLE `penghargaan` (
  `id_peng` int NOT NULL,
  `nama_peng` varchar(128) NOT NULL,
  `jenis_peng` varchar(128) NOT NULL,
  `desk_peng` text NOT NULL,
  `id_peg` int NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

-- --------------------------------------------------------

--
-- Table structure for table `pengunduran_diri`
--

CREATE TABLE `pengunduran_diri` (
  `id_pengunduran` int NOT NULL,
  `id_karyawan` int NOT NULL,
  `tanggal_pengajuan` date NOT NULL,
  `tanggal_efektif` date NOT NULL,
  `alasan` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `status_pengajuan` enum('Menunggu','Disetujui','Ditolak','') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `penilaian`
--

CREATE TABLE `penilaian` (
  `id_penilaian` int NOT NULL,
  `id_pegawai` int NOT NULL,
  `id_KPI` int NOT NULL,
  `nilai` int NOT NULL,
  `periode penilaian` date NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `penilaian_pelamar`
--

CREATE TABLE `penilaian_pelamar` (
  `id_penilaian_pel` int NOT NULL,
  `id_pelamar` int NOT NULL,
  `id_tahap` int NOT NULL,
  `tgl_dinilai` date NOT NULL,
  `skor` int NOT NULL,
  `catatan` text NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

-- --------------------------------------------------------

--
-- Table structure for table `phk`
--

CREATE TABLE `phk` (
  `id_phk` int NOT NULL,
  `id_karyawan` int NOT NULL,
  `tanggal_phk` date NOT NULL,
  `alasan_phk` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `status_kompensasi` enum('Diberikan','Tidak Diberikan','','') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `jumlah_kompensasi` decimal(10,0) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `potongan_gaji`
--

CREATE TABLE `potongan_gaji` (
  `id` int NOT NULL,
  `potongan` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `jml_potongan` int NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `promosi`
--

CREATE TABLE `promosi` (
  `id_promosi` int NOT NULL,
  `id_peg` int NOT NULL,
  `jab_lama` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `jab_baru` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `tgl_promosi` date NOT NULL,
  `alasan_promosi` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `riwayat_perubahan_kontrak`
--

CREATE TABLE `riwayat_perubahan_kontrak` (
  `id` int NOT NULL,
  `kontrak_id` int NOT NULL,
  `perubahan` text NOT NULL,
  `tanggal_perubahan` datetime DEFAULT CURRENT_TIMESTAMP,
  `dibuat_oleh` varchar(100) NOT NULL,
  `gaji_sebelum_perubahan` varchar(255) NOT NULL,
  `gaji_setelah_perubahan` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `slip_gaji`
--

CREATE TABLE `slip_gaji` (
  `id_slip_gaji` int NOT NULL,
  `bulan_tahun` date NOT NULL,
  `gaji_pokok` decimal(30,0) NOT NULL,
  `tunjangan` decimal(30,0) NOT NULL,
  `potongan` decimal(30,0) NOT NULL,
  `total_gaji` decimal(30,0) NOT NULL,
  `id_peg` int NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tabel cuti`
--

CREATE TABLE `tabel cuti` (
  `id_cuti` int NOT NULL,
  `id_karyawan` int NOT NULL,
  `id_jenis_cuti` int NOT NULL,
  `tanggal_mulai` date NOT NULL,
  `tanggal_selesai` date NOT NULL,
  `status_cuti` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `tanggal_pengajuan` date NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tabel_riwayat_cuti`
--

CREATE TABLE `tabel_riwayat_cuti` (
  `id_riwayat_cuti` int NOT NULL,
  `id_karyawan` int NOT NULL,
  `id_jenis_cuti` int NOT NULL,
  `tanggal_mulai` date NOT NULL,
  `tanggal_selesai` date NOT NULL,
  `jumlah_hari` int NOT NULL,
  `status_cuti` enum('Diterima','Selesai','Kadaluarsa') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tahap_lamaran`
--

CREATE TABLE `tahap_lamaran` (
  `id_tahap` int NOT NULL,
  `nama_tahap` varchar(128) NOT NULL,
  `deskripsi_tahap` text NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `absensi`
--
ALTER TABLE `absensi`
  ADD PRIMARY KEY (`ID_Absensi`);

--
-- Indexes for table `analisis_sdm`
--
ALTER TABLE `analisis_sdm`
  ADD PRIMARY KEY (`id_analisis`);

--
-- Indexes for table `departemen`
--
ALTER TABLE `departemen`
  ADD PRIMARY KEY (`id_departemen`);

--
-- Indexes for table `dokumen_pendukung`
--
ALTER TABLE `dokumen_pendukung`
  ADD PRIMARY KEY (`id`),
  ADD KEY `kontrak_id` (`kontrak_id`);

--
-- Indexes for table `jabatan`
--
ALTER TABLE `jabatan`
  ADD PRIMARY KEY (`id_jabatan`),
  ADD UNIQUE KEY `id_departemen` (`id_departemen`);

--
-- Indexes for table `jadwal_kehadiran`
--
ALTER TABLE `jadwal_kehadiran`
  ADD PRIMARY KEY (`ID_Jadwal`);

--
-- Indexes for table `jenis_cuti`
--
ALTER TABLE `jenis_cuti`
  ADD PRIMARY KEY (`id_jenis_cuti`);

--
-- Indexes for table `kedisiplinan`
--
ALTER TABLE `kedisiplinan`
  ADD PRIMARY KEY (`id_kedisiplinan`);

--
-- Indexes for table `kontrak_pegawai`
--
ALTER TABLE `kontrak_pegawai`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `kpi`
--
ALTER TABLE `kpi`
  ADD PRIMARY KEY (`id_kpi`),
  ADD UNIQUE KEY `id_peg` (`id_peg`);

--
-- Indexes for table `laporan_sdm`
--
ALTER TABLE `laporan_sdm`
  ADD PRIMARY KEY (`id_laporan`);

--
-- Indexes for table `lowongan`
--
ALTER TABLE `lowongan`
  ADD PRIMARY KEY (`id_lowongan`),
  ADD UNIQUE KEY `id_jabatan` (`id_jabatan`);

--
-- Indexes for table `pegawai`
--
ALTER TABLE `pegawai`
  ADD PRIMARY KEY (`id_peg`);

--
-- Indexes for table `riwayat_perubahan_kontrak`
--
ALTER TABLE `riwayat_perubahan_kontrak`
  ADD PRIMARY KEY (`id`),
  ADD KEY `kontrak_id` (`kontrak_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `dokumen_pendukung`
--
ALTER TABLE `dokumen_pendukung`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `kedisiplinan`
--
ALTER TABLE `kedisiplinan`
  MODIFY `id_kedisiplinan` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `kontrak_pegawai`
--
ALTER TABLE `kontrak_pegawai`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `pegawai`
--
ALTER TABLE `pegawai`
  MODIFY `id_peg` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `riwayat_perubahan_kontrak`
--
ALTER TABLE `riwayat_perubahan_kontrak`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `dokumen_pendukung`
--
ALTER TABLE `dokumen_pendukung`
  ADD CONSTRAINT `dokumen_pendukung_ibfk_1` FOREIGN KEY (`kontrak_id`) REFERENCES `kontrak_pegawai` (`id`);

--
-- Constraints for table `riwayat_perubahan_kontrak`
--
ALTER TABLE `riwayat_perubahan_kontrak`
  ADD CONSTRAINT `riwayat_perubahan_kontrak_ibfk_1` FOREIGN KEY (`kontrak_id`) REFERENCES `kontrak_pegawai` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
