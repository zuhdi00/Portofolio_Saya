<?php
include '../connection.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Retrieve and sanitize form data
    $id = intval($_POST['id']);
    $kontrak_id = intval($_POST['kontrak_id']);
    $tanggal_perubahan = $conn->real_escape_string($_POST['tanggal_perubahan']);
    $gaji_sebelum_perubahan = $conn->real_escape_string($_POST['gaji_sebelum_perubahan']); // VARCHAR, bukan FLOAT
    $gaji_setelah_perubahan = $conn->real_escape_string($_POST['gaji_setelah_perubahan']); // VARCHAR, bukan FLOAT
    $perubahan = $conn->real_escape_string($_POST['perubahan']); // Sesuai dengan database
    $dibuat_oleh = $conn->real_escape_string($_POST['dibuat_oleh']);

    // Prepare and execute the update query
    $stmt = $conn->prepare("UPDATE `riwayat_perubahan_kontrak` SET 
                            `kontrak_id` = ?, 
                            `tanggal_perubahan` = ?, 
                            `gaji_sebelum_perubahan` = ?, 
                            `gaji_setelah_perubahan` = ?, 
                            `perubahan` = ?,
                            `dibuat_oleh` = ?
                            WHERE `id` = ?");
    
    if ($stmt === false) {
        die('Prepare failed: ' . htmlspecialchars($conn->error));
    }

    // Bind parameters
    $stmt->bind_param("isssssi", $kontrak_id, $tanggal_perubahan, $gaji_sebelum_perubahan, $gaji_setelah_perubahan, $perubahan, $dibuat_oleh, $id);

    if ($stmt->execute()) {
        echo "<script>alert('Data berhasil diperbarui!'); window.location.href='index.php';</script>";
    } else {
        echo "<script>alert('Gagal memperbarui data: " . $stmt->error . "'); window.history.back();</script>";
    }

    $stmt->close();
    $conn->close();
} else {
    echo "<script>alert('Permintaan tidak valid.'); window.history.back();</script>";
}
?>
