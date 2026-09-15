<?php
require_once __DIR__ . '/../auth/auth.php';
wajib_izin('modul_hr');
include '../connection.php';

if (isset($_GET['id_phk'])) {
    $id_phk = $_GET['id_phk'];

    // Query untuk menghapus data PHK
    $query = "DELETE FROM phk WHERE id_phk = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $id_phk);

    if ($stmt->execute()) {
        header("Location: index.php?status=deleted");
        exit();
    } else {
        echo "Error: " . $stmt->error;
    }
}
?>
