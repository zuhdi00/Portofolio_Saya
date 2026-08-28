<?php
include '../connection.php'; // Pastikan koneksi database Anda sudah benar

// Mengecek apakah ID laporan ada di URL
if (isset($_GET['id_laporan'])) {
    $id_laporan = $_GET['id_laporan'];

    // Query untuk mengambil data laporan berdasarkan ID
    $query = "SELECT * FROM laporan_sdm WHERE id_laporan = ?";
    if ($stmt = $conn->prepare($query)) {
        $stmt->bind_param("i", $id_laporan);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $laporan = $result->fetch_assoc();
        } else {
            echo "Laporan tidak ditemukan!";
            exit;
        }

        $stmt->close();
    } else {
        echo "Error: " . $conn->error;
        exit;
    }
} else {
    echo "ID Laporan tidak ditemukan!";
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $judul_laporan = $_POST['judul_laporan'];
    $periode_awal = $_POST['periode_awal'];
    $periode_akhir = $_POST['periode_akhir'];
    $isi_laporan = $_POST['isi_laporan'];
    $tanggal_dibuat = $_POST['tanggal_dibuat'];

    $query = "UPDATE laporan_sdm SET judul_laporan = ?, periode_awal = ?, periode_akhir = ?, isi_laporan = ?, tanggal_dibuat = ? WHERE id_laporan = ?";
    if ($stmt = $conn->prepare($query)) {
        $stmt->bind_param("sssssi", $judul_laporan, $periode_awal, $periode_akhir, $isi_laporan, $tanggal_dibuat, $id_laporan);
        if ($stmt->execute()) {
            header("Location: index.php?status=success");
        } else {
            echo "Error: " . $stmt->error;
        }
        $stmt->close();
    } else {
        echo "Error: " . $conn->error;
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Laporan SDM</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.5.2/dist/css/bootstrap.min.css">
    <style>
        body {
            font-family: 'Segoe UI', sans-serif;
            background-color: #f0f2f5;
        }
        .hero-section {
            background: linear-gradient(135deg, #6a11cb, #2575fc);
            border-radius: 25px;
            padding: 70px;
        }
        .hero-section h1 {
            font-size: 3.5rem;
            font-weight: 700;
            text-shadow: 3px 3px 5px rgba(0, 0, 0, 0.3);
        }
        .hero-section p {
            font-size: 1.2rem;
            text-shadow: 1px 1px 3px rgba(0, 0, 0, 0.2);
        }
        .card {
            border-radius: 20px;
            box-shadow: 0px 10px 20px rgba(0, 0, 0, 0.1);
        }
        .form-control {
            border-radius: 10px;
            transition: 0.3s ease;
        }
        .form-control:focus {
            box-shadow: 0 0 0 0.2rem rgba(53, 175, 255, 0.5);
            border-color: #2575fc;
        }
        .btn-primary {
            background: linear-gradient(45deg, #6a11cb, #2575fc);
            border: none;
            font-size: 1.2rem;
            border-radius: 50px;
            transition: 0.3s ease;
        }
        .btn-primary:hover {
            background: linear-gradient(45deg, #2575fc, #6a11cb);
        }
        footer p {
            font-size: 1rem;
            color: #6c757d;
        }
    </style>
</head>
<body>
    <main id="main" class="main">
        <div class="container d-flex justify-content-center align-items-center vh-100">
            <div class="hero-section text-center text-white mb-5">
                <h1 class="display-4">Edit Laporan SDM</h1>
                <p class="lead">Perbarui laporan SDM Anda dengan mudah dan cepat.</p>
            </div>

            <div class="card w-75">
                <div class="card-body p-5">
                    <h3 class="card-title text-center mb-5">Form Edit Laporan</h3>
                    <form method="post" class="needs-validation" novalidate>
                        <div class="form-group">
                            <label for="judul_laporan">Judul Laporan</label>
                            <input type="text" class="form-control" id="judul_laporan" name="judul_laporan" value="<?= $laporan['judul_laporan'] ?>" required>
                            <div class="invalid-feedback">Mohon masukkan judul laporan.</div>
                        </div>

                        <div class="form-row">
                            <div class="form-group col-md-6">
                                <label for="periode_awal">Periode Awal</label>
                                <input type="date" class="form-control" id="periode_awal" name="periode_awal" value="<?= $laporan['periode_awal'] ?>" required>
                                <div class="invalid-feedback">Mohon masukkan periode awal.</div>
                            </div>
                            <div class="form-group col-md-6">
                                <label for="periode_akhir">Periode Akhir</label>
                                <input type="date" class="form-control" id="periode_akhir" name="periode_akhir" value="<?= $laporan['periode_akhir'] ?>" required>
                                <div class="invalid-feedback">Mohon masukkan periode akhir.</div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="isi_laporan">Isi Laporan</label>
                            <textarea class="form-control" id="isi_laporan" name="isi_laporan" rows="6" required><?= $laporan['isi_laporan'] ?></textarea>
                            <div class="invalid-feedback">Mohon masukkan isi laporan.</div>
                        </div>

                        <div class="form-group">
                            <label for="tanggal_dibuat">Tanggal Dibuat</label>
                            <input type="date" class="form-control" id="tanggal_dibuat" name="tanggal_dibuat" value="<?= $laporan['tanggal_dibuat'] ?>" required>
                            <div class="invalid-feedback">Mohon masukkan tanggal dibuat.</div>
                        </div>

                        <button type="submit" class="btn btn-primary w-100">Update Laporan</button>
                    </form>
                </div>
            </div>
        </div>
    </main>

    <footer class="text-center mt-5 py-4">
        <p>&copy; <?= date('Y') ?> Sistem Pengelolaan Laporan SDM | Dibuat dengan &#10084;</p>
    </footer>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.5.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        (function () {
            'use strict'
            var forms = document.querySelectorAll('.needs-validation')
            Array.prototype.slice.call(forms)
                .forEach(function (form) {
                    form.addEventListener('submit', function (event) {
                        if (!form.checkValidity()) {
                            event.preventDefault()
                            event.stopPropagation()
                        }
                        form.classList.add('was-validated')
                    }, false)
                })
        })()
    </script>
</body>
</html>
