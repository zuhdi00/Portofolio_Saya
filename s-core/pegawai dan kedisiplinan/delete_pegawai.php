<?php
include '../connection.php';

if (isset($_GET['id'])) {
    $id_peg = intval($_GET['id']); // Pastikan ID adalah integer

    // Periksa apakah ID pegawai valid
    $check_query = "SELECT * FROM `pegawai` WHERE `id_peg` = ?";
    $check_stmt = $conn->prepare($check_query);
    $check_stmt->bind_param("i", $id_peg);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();

    if ($check_result->num_rows > 0) {
        // Jika ID pegawai valid, lakukan penghapusan
        $delete_query = "DELETE FROM `pegawai` WHERE `id_peg` = ?";
        $delete_stmt = $conn->prepare($delete_query);
        $delete_stmt->bind_param("i", $id_peg);

        if ($delete_stmt->execute()) {
            echo "<script>alert('Data pegawai berhasil dihapus!'); window.location.href='index.php';</script>";
        } else {
            echo "<script>alert('Gagal menghapus data pegawai!'); window.location.href='index.php';</script>";
        }

        $delete_stmt->close();
    } else {
        echo "<script>alert('ID pegawai tidak ditemukan!'); window.location.href='index.php';</script>";
    }

    $check_stmt->close();
    $conn->close();
} else {
    header("Location: index.php");
    exit();
}
?>