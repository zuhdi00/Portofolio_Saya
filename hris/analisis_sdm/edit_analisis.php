<?php
include '../connection.php'; // Connect to the database

if (isset($_GET['id'])) {
    $id_analisis = $_GET['id'];
    $sql = "SELECT * FROM analisis_sdm WHERE id_analisis = '$id_analisis'";
    $result = $conn->query($sql);

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
    } else {
        echo "Data not found!";
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $judul_analisis = $_POST['judul_analisis'];
    $deskripsi = $_POST['deskripsi'];
    $tanggal_analisis = $_POST['tanggal_analisis'];
    $jenis_analisis = $_POST['jenis_analisis'];

    // Query to update the analysis data
    $update_sql = "UPDATE analisis_sdm SET 
                    judul_analisis = '$judul_analisis', 
                    deskripsi = '$deskripsi', 
                    tanggal_analisis = '$tanggal_analisis', 
                    jenis_analisis = '$jenis_analisis'
                    WHERE id_analisis = '$id_analisis'";

    if ($conn->query($update_sql) === TRUE) {
        header('Location: index.php'); // Redirect after success
        exit;
    } else {
        echo "Error: " . $conn->error;
    }
}
?>

<main id="main" class="main">
    <div class="container-fluid">
        <div class="row justify-content-center">
            <div class="col-lg-8 col-md-10">
                <div class="card custom-card shadow-lg">
                    <div class="card-header custom-card-header text-center">
                        <h4 class="card-title"><i class="bi bi-pencil-square"></i> Edit Analisis SDM</h4>
                    </div>
                    <div class="card-body custom-card-body">
                        <form method="post">
                            <div class="form-group row">
                                <label for="judul_analisis" class="col-md-4 col-form-label"><i class="bi bi-pencil-square"></i> Judul Analisis</label>
                                <div class="col-md-8">
                                    <input type="text" class="form-control" name="judul_analisis" value="<?= $row['judul_analisis'] ?>" required>
                                </div>
                            </div>

                            <div class="form-group row">
                                <label for="deskripsi" class="col-md-4 col-form-label"><i class="bi bi-file-earmark-text"></i> Deskripsi</label>
                                <div class="col-md-8">
                                    <textarea class="form-control" name="deskripsi" required><?= $row['deskripsi'] ?></textarea>
                                </div>
                            </div>

                            <div class="form-group row">
                                <label for="tanggal_analisis" class="col-md-4 col-form-label"><i class="bi bi-calendar-date"></i> Tanggal Analisis</label>
                                <div class="col-md-8">
                                    <input type="date" class="form-control" name="tanggal_analisis" value="<?= $row['tanggal_analisis'] ?>" required>
                                </div>
                            </div>

                            <div class="form-group row">
                                <label for="jenis_analisis" class="col-md-4 col-form-label"><i class="bi bi-caret-down-fill"></i> Jenis Analisis</label>
                                <div class="col-md-8">
                                    <select class="form-select" name="jenis_analisis" required>
                                        <option value="Kehadiran" <?= ($row['jenis_analisis'] == 'Kehadiran') ? 'selected' : '' ?>>Kehadiran</option>
                                        <option value="Kinerja" <?= ($row['jenis_analisis'] == 'Kinerja') ? 'selected' : '' ?>>Kinerja</option>
                                        <option value="Pelatihan" <?= ($row['jenis_analisis'] == 'Pelatihan') ? 'selected' : '' ?>>Pelatihan</option>
                                        <option value="Penghargaan" <?= ($row['jenis_analisis'] == 'Penghargaan') ? 'selected' : '' ?>>Penghargaan</option>
                                    </select>
                                </div>
                            </div>

                            <div class="form-group text-center">
                                <button type="submit" class="btn btn-gradient btn-lg w-50">
                                    <i class="bi bi-save"></i> Update Analisis
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<!-- Styling CSS for a modern and attractive appearance -->
<style>
    body {
        font-family: 'Poppins', sans-serif;
        background: #f7f7f7; /* Light background color */
        margin: 0;
        padding: 0;
    }

    .container-fluid {
        padding: 50px 15px;
    }

    .custom-card {
        border-radius: 12px;
        overflow: hidden;
        border: 2px solid #2575fc;
        background: #ffffff;
    }

    .custom-card-header {
        background-color: #181f33;
        color: white;
        padding: 30px;
        font-size: 2rem;
        border-radius: 12px 12px 0 0;
        box-shadow: 0 8px 20px rgba(0, 0, 0, 0.1);
    }

    .custom-card-body {
        padding: 35px;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.05);
    }

    .form-group {
        margin-bottom: 25px;
    }

    .form-group label {
        font-size: 1.1rem;
        font-weight: 600;
        color: #333;
        display: block;
        margin-bottom: 10px;
    }

    .form-control, .form-select {
        padding: 18px;
        border-radius: 12px;
        font-size: 1.1rem;
        background-color: #f4f7fc;
        border: 2px solid #ddd;
        width: 100%;
        transition: all 0.3s ease;
    }

    .form-control:focus, .form-select:focus {
        border-color: #2575fc;
        box-shadow: 0 0 10px rgba(37, 117, 252, 0.5);
        outline: none;
    }

    .btn-gradient {
        background: linear-gradient(to right, #6a11cb, #2575fc);
        color: white;
        font-weight: 600;
        font-size: 1.2rem;
        padding: 18px;
        border-radius: 12px;
        border: 2px solid #2575fc;
        width: 50%;
        transition: all 0.3s ease;
    }

    .btn-gradient:hover {
        background: linear-gradient(to right, #3f6f98, #1e4b75);
        transform: scale(1.05);
    }

    .bi {
        font-size: 1.4rem;
        margin-right: 10px; /* Space between icon and text */
    }

    .text-center {
        text-align: center;
    }

    .form-select {
        background-color: #f4f7fc;
    }

    /* Additional styling for improved spacing and layout */
    .form-group.row {
        margin-bottom: 20px;
    }

    /* Custom hover effect for form controls */
    .form-control:hover, .form-select:hover {
        border-color: #2575fc;
        background-color: #eef3fc;
    }
</style>
