-- phpMyAdmin SQL Dump
-- version 5.2.0
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: Jan 28, 2025 at 07:07 PM
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
-- Table structure for table `dokumen pendukung`
--

CREATE TABLE `dokumen pendukung` (
  `dokumen_peg` int NOT NULL,
  `kontrak_peg` int NOT NULL,
  `jenis_dokumen` int NOT NULL,
  `tanggal_unggah` date NOT NULL,
  `nama_file` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `lokasi_file` int NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `dokumen pendukung`
--

INSERT INTO `dokumen pendukung` (`dokumen_peg`, `kontrak_peg`, `jenis_dokumen`, `tanggal_unggah`, `nama_file`, `lokasi_file`) VALUES
(1, 1, 1, '1111-11-11', 'RAKAA', 2);

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
  `sanksi` varchar(250) NOT NULL,
  `jenis_pelanggaran` varchar(250) NOT NULL,
  `id_peg` int NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

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
-- Table structure for table `kontrak pegawai`
--

CREATE TABLE `kontrak pegawai` (
  `id_pegawai` int NOT NULL,
  `tanggal_mulai_kontrak` date NOT NULL,
  `tanggal_berakhir_kontrak` date NOT NULL,
  `status_kontrak` varchar(11) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `gaji_bulanan` varchar(11) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `tipe_kontrak` int NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `kontrak pegawai`
--

INSERT INTO `kontrak pegawai` (`id_pegawai`, `tanggal_mulai_kontrak`, `tanggal_berakhir_kontrak`, `status_kontrak`, `gaji_bulanan`, `tipe_kontrak`) VALUES
(1234567, '2005-02-13', '2006-11-03', 'Nonaktif', '1111222', 2),
(1234567, '2005-02-13', '2006-11-03', 'Nonaktif', '1111222', 2),
(1234567, '2005-02-13', '2006-11-03', 'Nonaktif', '1111222', 2),
(1234567, '2005-02-13', '2006-11-03', 'Nonaktif', '1111222', 2),
(2123026, '3333-02-21', '3333-02-22', 'Aktif', '999999999', 1);

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
  `nama_peg` varchar(128) NOT NULL,
  `gender_peg` enum('Laki-laki','Perempuan') NOT NULL,
  `status_peg` varchar(128) NOT NULL,
  `almt_peg` text NOT NULL,
  `no_telp_peg` varchar(128) NOT NULL,
  `email_peg` varchar(128) NOT NULL,
  `jabatan_peg` varchar(11) NOT NULL,
  `ttl_peg` date NOT NULL,
  `foto_peg` varchar(64) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

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
-- Table structure for table `pelatihan`
--

CREATE TABLE IF NOT EXISTS pelatihan (
    'id_pelatihan' INT AUTO_INCREMENT PRIMARY KEY,
    'nama_pelatihan' VARCHAR(255) NOT NULL,
    'deskripsi_pelatihan' VARCHAR(225) NOT NULL,
    'tgl_pelaksanaan' DATE NOT NULL,
    'jam_pelaksanaan' TIME NOT NULL,
    'durasi_pelatihan' VARCHAR(50) NOT NULL,
    'lokasi_pelatihan' VARCHAR(255) NOT NULL,
    'pemateri_pelatihan' VARCHAR(255) NOT NULL,
    'status_pelatihan' ENUM('terlaksana', 'belum terlaksana', 'tidak terlaksana') DEFAULT 'belum terlaksana'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

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
  `id_kontrak` int NOT NULL,
  `tanggal_perubahan` date NOT NULL,
  `gaji_sebelum_perubahan` decimal(10,2) NOT NULL,
  `gaji_setelah_perubahan` decimal(10,2) NOT NULL,
  `keterangan_perubahan` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `riwayat_perubahan_kontrak`
--

INSERT INTO `riwayat_perubahan_kontrak` (`id`, `id_kontrak`, `tanggal_perubahan`, `gaji_sebelum_perubahan`, `gaji_setelah_perubahan`, `keterangan_perubahan`) VALUES
(30, 2129292, '2222-11-11', '19191919.00', '18181818.00', 'pemimpin'),
(31, 2129292, '2222-02-22', '19191919.00', '18181818.00', 'pemimpin utama'),
(32, 2129292, '1111-11-11', '19191919.00', '18181818.00', 'pemimpin utama');

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
  ADD PRIMARY KEY (`id_kedisiplinan`),
  ADD UNIQUE KEY `id_peg` (`id_peg`);

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
-- Indexes for table `mutasi`
--
ALTER TABLE `mutasi`
  ADD PRIMARY KEY (`id_mutasi`);

--
-- Indexes for table `pegawai`
--
ALTER TABLE `pegawai`
  ADD PRIMARY KEY (`id_peg`);

--
-- Indexes for table `pelamar`
--
ALTER TABLE `pelamar`
  ADD PRIMARY KEY (`id_pelamar`),
  ADD UNIQUE KEY `id_lowongan` (`id_lowongan`);

--
-- Indexes for table `pelatihan`
--
ALTER TABLE `pelatihan`
  ADD PRIMARY KEY (`id_pelatihan`),
  ADD UNIQUE KEY `id_pegawai` (`id_pegawai`);

--
-- Indexes for table `penggajian`
--
ALTER TABLE `penggajian`
  ADD PRIMARY KEY (`id_jabatan`);

--
-- Indexes for table `penghargaan`
--
ALTER TABLE `penghargaan`
  ADD PRIMARY KEY (`id_peng`),
  ADD UNIQUE KEY `id_peg` (`id_peg`);

--
-- Indexes for table `pengunduran_diri`
--
ALTER TABLE `pengunduran_diri`
  ADD PRIMARY KEY (`id_pengunduran`);

--
-- Indexes for table `penilaian`
--
ALTER TABLE `penilaian`
  ADD PRIMARY KEY (`id_penilaian`),
  ADD UNIQUE KEY `id_pegawai` (`id_pegawai`),
  ADD UNIQUE KEY `id_KPI` (`id_KPI`);

--
-- Indexes for table `penilaian_pelamar`
--
ALTER TABLE `penilaian_pelamar`
  ADD PRIMARY KEY (`id_penilaian_pel`),
  ADD UNIQUE KEY `id_pelamar` (`id_pelamar`),
  ADD UNIQUE KEY `id_tahap` (`id_tahap`);

--
-- Indexes for table `phk`
--
ALTER TABLE `phk`
  ADD PRIMARY KEY (`id_phk`);

--
-- Indexes for table `potongan_gaji`
--
ALTER TABLE `potongan_gaji`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `promosi`
--
ALTER TABLE `promosi`
  ADD PRIMARY KEY (`id_promosi`);

--
-- Indexes for table `riwayat_perubahan_kontrak`
--
ALTER TABLE `riwayat_perubahan_kontrak`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `tabel cuti`
--
ALTER TABLE `tabel cuti`
  ADD PRIMARY KEY (`id_cuti`);

--
-- Indexes for table `tabel_riwayat_cuti`
--
ALTER TABLE `tabel_riwayat_cuti`
  ADD PRIMARY KEY (`id_riwayat_cuti`);

--
-- Indexes for table `tahap_lamaran`
--
ALTER TABLE `tahap_lamaran`
  ADD PRIMARY KEY (`id_tahap`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `absensi`
--
ALTER TABLE `absensi`
  MODIFY `ID_Absensi` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `departemen`
--
ALTER TABLE `departemen`
  MODIFY `id_departemen` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `jabatan`
--
ALTER TABLE `jabatan`
  MODIFY `id_jabatan` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `jadwal_kehadiran`
--
ALTER TABLE `jadwal_kehadiran`
  MODIFY `ID_Jadwal` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `kedisiplinan`
--
ALTER TABLE `kedisiplinan`
  MODIFY `id_kedisiplinan` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `lowongan`
--
ALTER TABLE `lowongan`
  MODIFY `id_lowongan` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `pegawai`
--
ALTER TABLE `pegawai`
  MODIFY `id_peg` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `pelamar`
--
ALTER TABLE `pelamar`
  MODIFY `id_pelamar` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `pelatihan`
--
ALTER TABLE `pelatihan`
  MODIFY `id_pelatihan` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `penghargaan`
--
ALTER TABLE `penghargaan`
  MODIFY `id_peng` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `penilaian`
--
ALTER TABLE `penilaian`
  MODIFY `id_penilaian` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `penilaian_pelamar`
--
ALTER TABLE `penilaian_pelamar`
  MODIFY `id_penilaian_pel` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `riwayat_perubahan_kontrak`
--
ALTER TABLE `riwayat_perubahan_kontrak`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=33;

--
-- AUTO_INCREMENT for table `tahap_lamaran`
--
ALTER TABLE `tahap_lamaran`
  MODIFY `id_tahap` int NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `jabatan`
--
ALTER TABLE `jabatan`
  ADD CONSTRAINT `jabatan_ibfk_1` FOREIGN KEY (`id_jabatan`) REFERENCES `departemen` (`id_departemen`) ON DELETE RESTRICT ON UPDATE RESTRICT;

--
-- Constraints for table `kedisiplinan`
--
ALTER TABLE `kedisiplinan`
  ADD CONSTRAINT `kedisiplinan_ibfk_1` FOREIGN KEY (`id_peg`) REFERENCES `pegawai` (`id_peg`) ON DELETE RESTRICT ON UPDATE RESTRICT;

--
-- Constraints for table `lowongan`
--
ALTER TABLE `lowongan`
  ADD CONSTRAINT `lowongan_ibfk_1` FOREIGN KEY (`id_jabatan`) REFERENCES `jabatan` (`id_jabatan`) ON DELETE RESTRICT ON UPDATE RESTRICT;

--
-- Constraints for table `pelamar`
--
ALTER TABLE `pelamar`
  ADD CONSTRAINT `pelamar_ibfk_1` FOREIGN KEY (`id_lowongan`) REFERENCES `lowongan` (`id_lowongan`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `pelatihan`
--
ALTER TABLE `pelatihan`
  ADD CONSTRAINT `pelatihan_ibfk_1` FOREIGN KEY (`id_pegawai`) REFERENCES `pegawai` (`id_peg`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `penghargaan`
--
ALTER TABLE `penghargaan`
  ADD CONSTRAINT `penghargaan_ibfk_1` FOREIGN KEY (`id_peg`) REFERENCES `pegawai` (`id_peg`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `penilaian_pelamar`
--
ALTER TABLE `penilaian_pelamar`
  ADD CONSTRAINT `penilaian_pelamar_ibfk_1` FOREIGN KEY (`id_pelamar`) REFERENCES `pelamar` (`id_pelamar`) ON DELETE RESTRICT ON UPDATE RESTRICT,
  ADD CONSTRAINT `penilaian_pelamar_ibfk_2` FOREIGN KEY (`id_tahap`) REFERENCES `tahap_lamaran` (`id_tahap`) ON DELETE RESTRICT ON UPDATE RESTRICT;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
