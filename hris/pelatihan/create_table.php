<?php
require_once 'koneksi.php';

try {
    // Set mode SQL yang lebih longgar
    $conn->query("SET sql_mode = ''");
    
    // Hapus tabel jika sudah ada
    $conn->query("DROP TABLE IF EXISTS pelatihan");
    
    // Buat tabel baru
    $sql = "CREATE TABLE pelatihan (
        id_pelatihan INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
        nama_pelatihan VARCHAR(150) NOT NULL,
        deskripsi_pelatihan VARCHAR(150) NOT NULL,
        tgl_pelatihan DATE NOT NULL,
        jam_pelatihan TIME NOT NULL,
        durasi_pelatihan VARCHAR(150) NOT NULL,
        lokasi_pelatihan VARCHAR(150) NOT NULL,
        pemateri_pelatihan VARCHAR(150) NOT NULL,
        id_peg INT NULL DEFAULT NULL,
        kapasitas VARCHAR(150) NOT NULL,
        status_pelatihan ENUM('Terlaksana','Belum Terlaksana','Gagal Terlaksana') NOT NULL
    ) ENGINE=InnoDB";

    if ($conn->query($sql)) {
        // Reset AUTO_INCREMENT
        $conn->query("ALTER TABLE pelatihan AUTO_INCREMENT = 1");
        
        // Coba insert data test
        $test = $conn->query("INSERT INTO pelatihan 
            (nama_pelatihan, deskripsi_pelatihan, tgl_pelatihan, jam_pelatihan, 
             durasi_pelatihan, lokasi_pelatihan, pemateri_pelatihan, 
             kapasitas, status_pelatihan) 
            VALUES 
            ('Test', 'Test', CURRENT_DATE(), CURRENT_TIME(), 
             '2 Jam', 'Test', 'Test', 
             '20 Orang', 'Belum Terlaksana')");
             
        if ($test) {
            echo "Data test berhasil ditambahkan dengan ID: " . $conn->insert_id;
        }
        
        echo "<br>Tabel berhasil dibuat";
    } else {
        throw new Exception($conn->error);
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}

$conn->close();
?> 