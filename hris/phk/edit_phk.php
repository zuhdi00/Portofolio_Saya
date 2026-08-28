<?php
require_once __DIR__ . '/../auth/auth.php';
wajib_izin('modul_hr');
include '../connection.php';

// Pastikan id_phk ada dalam URL
if (isset($_GET['id_phk'])) {
    $id_phk = $_GET['id_phk'];

    // Query untuk mengambil data PHK berdasarkan id_phk
    $query = "SELECT * FROM phk WHERE id_phk = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $id_phk);
    $stmt->execute();
    $result = $stmt->get_result();
    
    // Jika data ditemukan, masukkan ke dalam variabel
    if ($result->num_rows > 0) {
        $phk = $result->fetch_assoc();
    } else {
        echo "Pengajuan PHK tidak ditemukan!";
        exit();
    }
} else {
    echo "ID PHK tidak valid!";
    exit();
}

// Proses update jika form disubmit
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $id_karyawan = $_POST['id_karyawan'];
    $tanggal_phk = $_POST['tanggal_phk'];
    $alasan_phk = $_POST['alasan_phk'];
    $status_kompensasi = $_POST['status_kompensasi'];
    $jumlah_kompensasi = $_POST['jumlah_kompensasi'];

    // Query untuk update data PHK
    $query = "UPDATE phk SET id_karyawan = ?, tanggal_phk = ?, alasan_phk = ?, status_kompensasi = ?, jumlah_kompensasi = ? WHERE id_phk = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("issssi", $id_karyawan, $tanggal_phk, $alasan_phk, $status_kompensasi, $jumlah_kompensasi, $id_phk);

    if ($stmt->execute()) {
        header("Location: index.php?status=updated");
        exit();
    } else {
        echo "Error: " . $stmt->error;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Pengajuan PHK</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body {
            background-color: #f4f6f9;
            font-family: 'Roboto', sans-serif;
        }
        .container {
            margin-top: 30px;
        }
        .card {
            border-radius: 15px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }
        .card-header {
            background-color: #007bff;
            color: white;
            border-top-left-radius: 15px;
            border-top-right-radius: 15px;
            font-weight: bold;
        }
        .form-label {
            font-weight: bold;
        }
        .btn-primary {
            background-color: #007bff;
            border: none;
        }
        .btn-primary:hover {
            background-color: #0056b3;
        }
        .form-control {
            border-radius: 8px;
        }
        .modal-content {
            border-radius: 15px;
        }
        .alert {
            font-weight: bold;
        }
        /* Hover effect */
        .btn {
            transition: background-color 0.3s ease, transform 0.3s ease;
        }
        .btn:hover {
            transform: translateY(-2px);
        }
        .btn:active {
            transform: translateY(2px);
        }
    </style>
</head>
<body>

<div class="container">
    <div class="card">
        <div class="card-header text-center">
            <h4>Edit Pengajuan PHK</h4>
        </div>
        <div class="card-body">
            <!-- Form untuk mengedit data PHK -->
            <form method="POST">
                <div class="mb-3">
                    <label for="id_karyawan" class="form-label">ID Karyawan</label>
                    <input type="number" class="form-control" id="id_karyawan" name="id_karyawan" value="<?= $phk['id_karyawan'] ?>" required>
                </div>
                <div class="mb-3">
                    <label for="tanggal_phk" class="form-label">Tanggal PHK</label>
                    <input type="date" class="form-control" id="tanggal_phk" name="tanggal_phk" value="<?= $phk['tanggal_phk'] ?>" required>
                </div>
                <div class="mb-3">
                    <label for="alasan_phk" class="form-label">Alasan PHK</label>
                    <textarea class="form-control" id="alasan_phk" name="alasan_phk" rows="4" required><?= $phk['alasan_phk'] ?></textarea>
                </div>
                <div class="mb-3">
                    <label for="status_kompensasi" class="form-label">Status Kompensasi</label>
                    <select class="form-select" id="status_kompensasi" name="status_kompensasi" required>
                        <option value="Diberikan" <?= $phk['status_kompensasi'] == 'Diberikan' ? 'selected' : '' ?>>Diberikan</option>
                        <option value="Tidak Diberikan" <?= $phk['status_kompensasi'] == 'Tidak Diberikan' ? 'selected' : '' ?>>Tidak Diberikan</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label for="jumlah_kompensasi" class="form-label">Jumlah Kompensasi</label>
                    <input type="number" class="form-control" id="jumlah_kompensasi" name="jumlah_kompensasi" value="<?= $phk['jumlah_kompensasi'] ?>" required>
                </div>
                <button type="submit" class="btn btn-primary w-100">Update Pengajuan PHK</button>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.js"></script>
</body>
</html>
