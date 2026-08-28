<?php
include '../connection.php';

// Check if the ID is provided in the URL
if (isset($_GET['id_pengunduran'])) {
    $id_pengunduran = $_GET['id_pengunduran'];

    // Fetch the existing data
    $sql = "SELECT * FROM pengunduran_diri WHERE id_pengunduran = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id_pengunduran);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
    } else {
        echo "Data not found!";
        exit();
    }

    // Handle the form submission to update the record
    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        $id_karyawan = $_POST['id_karyawan'];
        $tanggal_pengajuan = $_POST['tanggal_pengajuan'];
        $tanggal_efektif = $_POST['tanggal_efektif'];
        $alasan = $_POST['alasan'];
        $status_pengajuan = $_POST['status_pengajuan'];

        // Update the record
        $update_sql = "UPDATE pengunduran_diri SET 
                       id_karyawan = ?, 
                       tanggal_pengajuan = ?, 
                       tanggal_efektif = ?, 
                       alasan = ?, 
                       status_pengajuan = ? 
                       WHERE id_pengunduran = ?";
        $update_stmt = $conn->prepare($update_sql);
        $update_stmt->bind_param("issssi", $id_karyawan, $tanggal_pengajuan, $tanggal_efektif, $alasan, $status_pengajuan, $id_pengunduran);

        if ($update_stmt->execute()) {
            header("Location: index.php?status=success");
            exit();
        } else {
            echo "Error updating record: " . $conn->error;
        }
    }
} else {
    echo "ID not specified!";
    exit();
}
?>

<!-- HTML Form to edit the resignation -->
<form method="POST" class="edit-form">
    <div class="form-container">
        <h3 class="form-title">Edit Pengajuan Pengunduran Diri</h3>
        
        <div class="form-group">
            <label for="id_karyawan" class="form-label">ID Karyawan</label>
            <input type="number" class="form-control" id="id_karyawan" name="id_karyawan" value="<?= $row['id_karyawan'] ?>" required>
        </div>

        <div class="form-group">
            <label for="tanggal_pengajuan" class="form-label">Tanggal Pengajuan</label>
            <input type="date" class="form-control" id="tanggal_pengajuan" name="tanggal_pengajuan" value="<?= $row['tanggal_pengajuan'] ?>" required>
        </div>

        <div class="form-group">
            <label for="tanggal_efektif" class="form-label">Tanggal Efektif Pengunduran Diri</label>
            <input type="date" class="form-control" id="tanggal_efektif" name="tanggal_efektif" value="<?= $row['tanggal_efektif'] ?>" required>
        </div>

        <div class="form-group">
            <label for="alasan" class="form-label">Alasan Pengunduran Diri</label>
            <textarea class="form-control" id="alasan" name="alasan" rows="4" required><?= $row['alasan'] ?></textarea>
        </div>

        <div class="form-group">
            <label for="status_pengajuan" class="form-label">Status Pengajuan</label>
            <select class="form-select" id="status_pengajuan" name="status_pengajuan" required>
                <option value="Menunggu" <?= ($row['status_pengajuan'] == 'Menunggu') ? 'selected' : '' ?>>Menunggu</option>
                <option value="Disetujui" <?= ($row['status_pengajuan'] == 'Disetujui') ? 'selected' : '' ?>>Disetujui</option>
                <option value="Ditolak" <?= ($row['status_pengajuan'] == 'Ditolak') ? 'selected' : '' ?>>Ditolak</option>
            </select>
        </div>

        <button type="submit" class="btn btn-submit">Update Pengajuan</button>
    </div>
</form>

<!-- Add CSS for styling -->
<style>
    body {
        font-family: 'Roboto', sans-serif;
        background-color: #f4f7fc;
        margin: 0;
        padding: 0;
    }

    .edit-form {
        max-width: 600px;
        margin: 60px auto;
        padding: 40px;
        background: #ffffff;
        border-radius: 12px;
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
    }

    .form-container {
        padding: 20px;
    }

    .form-title {
        font-size: 2.2rem;
        text-align: center;
        margin-bottom: 30px;
        color: #2E7D32;
    }

    .form-group {
        margin-bottom: 20px;
    }

    .form-label {
        font-weight: 600;
        color: #333;
        margin-bottom: 8px;
    }

    .form-control, .form-select, .btn {
        font-size: 1rem;
        padding: 12px;
        border-radius: 8px;
        width: 100%;
        box-sizing: border-box;
    }

    .form-control {
        border: 1px solid #ddd;
        background-color: #f9f9f9;
        transition: all 0.3s ease;
    }

    .form-control:focus {
        border-color: #4CAF50;
        box-shadow: 0 0 8px rgba(76, 175, 80, 0.3);
    }

    .form-select {
        border: 1px solid #ddd;
        background-color: #f9f9f9;
        transition: all 0.3s ease;
    }

    .form-select:focus {
        border-color: #4CAF50;
        box-shadow: 0 0 8px rgba(76, 175, 80, 0.3);
    }

    .btn-submit {
        background-color: #4CAF50;
        border: none;
        color: white;
        font-size: 1.1rem;
        font-weight: 600;
        padding: 15px;
        cursor: pointer;
        transition: background-color 0.3s ease;
        margin-top: 20px;
    }

    .btn-submit:hover {
        background-color: #45a049;
    }

    textarea.form-control {
        resize: vertical;
    }
</style>
