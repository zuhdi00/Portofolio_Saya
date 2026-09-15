<?php
include '../connection.php';

// Periksa apakah metode permintaan adalah POST
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Ambil dan sanitasi input
    $id = intval($_POST['id']);
    $pegawai_id = intval($_POST['pegawai_id']);
    $nomor_kontrak = $conn->real_escape_string($_POST['nomor_kontrak']);
    $tanggal_mulai = $conn->real_escape_string($_POST['tanggal_mulai']);
    $tanggal_berakhir = $conn->real_escape_string($_POST['tanggal_berakhir']);
    $jabatan = $conn->real_escape_string($_POST['jabatan']);
    $gaji = floatval($_POST['gaji']);
    $status_kontrak = $conn->real_escape_string($_POST['status_kontrak']);

    // Query update data
    $sql = "UPDATE `kontrak_pegawai` SET 
            `pegawai_id` = '$pegawai_id', 
            `nomor_kontrak` = '$nomor_kontrak', 
            `tanggal_mulai` = '$tanggal_mulai', 
            `tanggal_berakhir` = '$tanggal_berakhir', 
            `jabatan` = '$jabatan', 
            `gaji` = '$gaji', 
            `status_kontrak` = '$status_kontrak',
            `updated_at` = CURRENT_TIMESTAMP 
            WHERE `id` = '$id'";

    if ($conn->query($sql) === TRUE) {
        echo "<script>alert('Kontrak berhasil diperbarui!'); window.location.href = '../index.php';</script>";
    } else {
        echo "<script>alert('Gagal memperbarui kontrak: " . $conn->error . "'); window.history.back();</script>";
    }

    $conn->close();
} else {
    echo "<script>alert('Permintaan tidak valid.'); window.history.back();</script>";
}
?>
