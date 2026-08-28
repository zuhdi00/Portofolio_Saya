<?php
include '../template/header.php';
include '../template/sidebar.php';
include '../connection.php';

// Function to retrieve data from the `kedisiplinan` table
function getKedisiplinanData() {
    global $conn; // Use the global connection variable
    $result = $conn->query("SELECT * FROM `kedisiplinan`");
    if ($result) {
        return $result->fetch_all(MYSQLI_ASSOC);
    } else {
        return []; // Return an empty array if there's an error
    }
}

// Fetch data from `kedisiplinan` table
$kedisiplinan_data = getKedisiplinanData();
?>
<main id="main" class="main">
    <div class="container">
        <h1>Manajemen Kedisiplinan Pegawai</h1>
        <h2>Tambah Kedisiplinan</h2>
        <form method="post" action="submit_kedisiplinan.php">
            <div class="mb-3">
                <label for="sanksi" class="form-label">Sanksi</label>
                <input type="text" class="form-control" id="sanksi" name="sanksi" required>
            </div>
            <div class="mb-3">
                <label for="jenis_pelanggaran" class="form-label">Jenis Pelanggaran</label>
                <input type="text" class="form-control" id="jenis_pelanggaran" name="jenis_pelanggaran" required>
            </div>
            <div class="mb-3">
                <label for="id_peg" class="form-label">ID Pegawai</label>
                <input type="number" class="form-control" id="id_peg" name="id_peg" required>
            </div>
            <button type="submit" class="btn btn-primary">Submit</button>
        </form>

        <h2 class="mt-4">Daftar Kedisiplinan Pegawai</h2>
        <table class="table">
            <thead>
                <tr>
                    <th>ID Kedisiplinan</th>
                    <th>Sanksi</th>
                    <th>Jenis Pelanggaran</th>
                    <th>ID Pegawai</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($kedisiplinan_data as $row): ?>
                    <tr>
                        <td><?= $row['id_kedisiplinan'] ?></td>
                        <td><?= $row['sanksi'] ?></td>
                        <td><?= $row['jenis_pelanggaran'] ?></td>
                        <td><?= $row['id_peg'] ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</main><!-- End #main -->

<?php
include '../template/footer.php';
?>