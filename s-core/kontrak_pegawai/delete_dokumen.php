<?php
include '../connection.php';

// Validasi ID
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    echo "<script>alert('ID tidak valid!'); window.history.back();</script>";
    exit;
}

$id = (int)$_GET['id']; // Konversi ke integer

// Mulai transaksi
$conn->begin_transaction();

try {
    // Ambil data file sebelum menghapus
    $stmt = $conn->prepare("SELECT file_path FROM dokumen_pendukung WHERE id = ?");
    if (!$stmt) {
        throw new Exception("Gagal mempersiapkan query: " . $conn->error);
    }

    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        throw new Exception("Data tidak ditemukan.");
    }

    $data = $result->fetch_assoc();
    $filePath = $data['file_path']; // Path file yang akan dihapus
    $stmt->close();

    // Hapus data dari database
    $stmt = $conn->prepare("DELETE FROM dokumen_pendukung WHERE id = ?");
    if (!$stmt) {
        throw new Exception("Gagal mempersiapkan query: " . $conn->error);
    }

    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();

    // Hapus file fisik jika ada
    if (!empty($filePath) && file_exists($filePath)) {
        if (!unlink($filePath)) {
            throw new Exception("Gagal menghapus file fisik.");
        }
    }

    // Commit transaksi jika semua berhasil
    $conn->commit();

    echo "<script>alert('Dokumen dan file berhasil dihapus!'); window.location.href='index.php';</script>";
} catch (Exception $e) {
    $conn->rollback(); // Batalkan transaksi jika terjadi kesalahan
    echo "<script>alert('Gagal menghapus data: " . addslashes($e->getMessage()) . "'); window.history.back();</script>";
}

$conn->close();
?>
