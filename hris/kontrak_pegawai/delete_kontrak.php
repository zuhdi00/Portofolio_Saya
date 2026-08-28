<?php
include '../connection.php';

// Pastikan ID ada dan valid
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("<script>alert('ID tidak valid!'); window.location.href='index.php';</script>");
}

$id = (int)$_GET['id']; // Konversi ID ke integer

// Mulai transaksi
$conn->begin_transaction();

try {
    // Hapus file terkait di dokumen_pendukung sebelum menghapus kontrak
    $stmt = $conn->prepare("SELECT file_path FROM dokumen_pendukung WHERE kontrak_id = ?");
    if ($stmt) {
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();

        while ($row = $result->fetch_assoc()) {
            if (!empty($row['file_path']) && file_exists($row['file_path'])) {
                unlink($row['file_path']); // Hapus file dari server
            }
        }
        $stmt->close();
    }

    // Hapus dokumen pendukung terkait kontrak pegawai
    $stmt = $conn->prepare("DELETE FROM dokumen_pendukung WHERE kontrak_id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();

    // Hapus riwayat perubahan kontrak yang masih terkait dengan kontrak_pegawai
    $stmt = $conn->prepare("DELETE FROM riwayat_perubahan_kontrak WHERE kontrak_id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();

    // Hapus kontrak pegawai
    $stmt = $conn->prepare("DELETE FROM kontrak_pegawai WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();

    // Commit transaksi jika semua query berhasil
    $conn->commit();
    
    echo "<script>alert('Data berhasil dihapus!'); window.location.href='index.php';</script>";
} catch (Exception $e) {
    $conn->rollback(); // Batalkan transaksi jika terjadi kesalahan
    echo "<script>alert('Gagal menghapus data: " . addslashes($e->getMessage()) . "'); window.location.href='index.php';</script>";
}

$conn->close();
?>
