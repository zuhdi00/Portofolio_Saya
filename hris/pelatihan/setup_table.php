<?php
require_once 'koneksi.php';

// Set error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

try {
    // Hapus tabel jika sudah ada
    $sql_drop = "DROP TABLE IF EXISTS pelatihan";
    if ($conn->query($sql_drop)) {
        echo "Tabel lama berhasil dihapus.<br>";
    }

    // Buat tabel baru
    $sql_create = "CREATE TABLE `pelatihan` (
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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci";

    if ($conn->query($sql_create)) {
        echo "Tabel pelatihan berhasil dibuat.<br>";
        
        // Tambah data contoh
        $sql_insert = "INSERT INTO pelatihan (
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
        ('Pelatihan Web Development', 'Pelatihan dasar pengembangan web', '2024-03-20', '09:00:00', '3 Jam', 'Ruang Meeting Lt.2', 'Budi Santoso', 'Tidak Terlaksana', '30 Orang')";

        if ($conn->query($sql_insert)) {
            echo "Data contoh berhasil ditambahkan.<br>";
        } else {
            throw new Exception("Error menambahkan data: " . $conn->error);
        }
    } else {
        throw new Exception("Error membuat tabel: " . $conn->error);
    }

    // Tampilkan struktur tabel
    $result = $conn->query("DESCRIBE pelatihan");
    if ($result) {
        echo "<br>Struktur tabel pelatihan:<br>";
        echo "<pre>";
        while ($row = $result->fetch_assoc()) {
            print_r($row);
        }
        echo "</pre>";
    }

} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}

$conn->close();
?> 