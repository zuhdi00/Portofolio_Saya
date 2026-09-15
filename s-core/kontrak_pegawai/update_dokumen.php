<?php
include '../connection.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Validasi ID
    if (!isset($_POST['id']) || !is_numeric($_POST['id'])) {
        echo "<script>alert('ID tidak valid!'); window.history.back();</script>";
        exit;
    }

    $id = (int)$_POST['id']; // Konversi ke integer

    // Ambil data form
    $kontrak_id = $_POST['kontrak_id']; // Sesuai dengan database
    $nama_dokumen = $_POST['nama_dokumen'];
    $tanggal_upload = $_POST['tanggal_upload'];

    // Ambil lokasi file lama
    $stmt = $conn->prepare("SELECT file_path FROM dokumen_pendukung WHERE id = ?");
    if (!$stmt) {
        die("<script>alert('Gagal mempersiapkan query: " . addslashes($conn->error) . "'); window.history.back();</script>");
    }

    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $oldFile = $result->fetch_assoc()['file_path'];
    $stmt->close();

    $newFilePath = $oldFile; // Default: gunakan file lama

    // Proses upload file baru jika ada
    if (!empty($_FILES['file_upload']['name'])) {
        $uploadDir = 'uploads/';
        $fileName = basename($_FILES['file_upload']['name']);
        $newFilePath = $uploadDir . time() . "_" . $fileName; // Tambahkan timestamp untuk unik
        $fileType = strtolower(pathinfo($newFilePath, PATHINFO_EXTENSION));

        // Validasi ekstensi file
        $allowedTypes = ['pdf', 'doc', 'docx', 'xls', 'xlsx'];
        if (!in_array($fileType, $allowedTypes)) {
            die("<script>alert('Hanya file PDF, DOC, DOCX, XLS, XLSX yang diizinkan!'); history.back();</script>");
        }

        // Buat direktori jika belum ada
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        // Upload file baru
        if (move_uploaded_file($_FILES['file_upload']['tmp_name'], $newFilePath)) {
            // Hapus file lama jika ada dan berbeda
            if ($oldFile && file_exists($oldFile) && $oldFile !== $newFilePath) {
                unlink($oldFile);
            }
        } else {
            echo "<script>alert('Gagal upload file baru!'); history.back();</script>";
            exit;
        }
    }

    // Update data di database
    $stmt = $conn->prepare("UPDATE dokumen_pendukung SET
        kontrak_id = ?,
        nama_dokumen = ?,
        tanggal_upload = ?,
        file_path = ?
        WHERE id = ?");
    if (!$stmt) {
        die("<script>alert('Gagal mempersiapkan query: " . addslashes($conn->error) . "'); window.history.back();</script>");
    }

    $stmt->bind_param("isssi", $kontrak_id, $nama_dokumen, $tanggal_upload, $newFilePath, $id);

    if ($stmt->execute()) {
        echo "<script>alert('Dokumen berhasil diperbarui!'); window.location.href='index.php';</script>";
    } else {
        echo "<script>alert('Gagal memperbarui data: " . addslashes($stmt->error) . "'); history.back();</script>";
    }

    $stmt->close();
    $conn->close();
}
?>