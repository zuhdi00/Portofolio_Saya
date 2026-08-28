<?php
include '../template/header.php';
include '../template/sidebar.php';
include '../config/koneksi_sqlsrv.php';

function getData($table) {
    global $conn;
    $result = sqlsrv_query($conn, "SELECT * FROM dbo.$table");
    if ($result === false) {
        return [];
    }
    $rows = [];
    while ($row = sqlsrv_fetch_array($result, SQLSRV_FETCH_ASSOC)) {
        $rows[] = $row;
    }
    return $rows;
}

$pelamar = getData('pelamar');
?>
<main id="main" class="main">
    <div class="container">
        <h1>Pelamar</h1>
        <form method="post" action="submit_pelamar.php">
            <div class="mb-3">
                <label for="id_pelamar" class="form-label">ID Pelamar</label>
                <input type="text" class="form-control" id="id_pelamar" name="id_pelamar" required>
            </div>
            <div class="mb-3">
                <label for="nama_pel" class="form-label">Nama Pelamar</label>
                <input type="text" class="form-control" id="nama_pel" name="nama_pel" required>
            </div>
            <div class="mb-3">
                <label for="email_pel" class="form-label">Email Pelamar</label>
                <input type="email" class="form-control" id="email_pel" name="email_pel" required>
            </div>
            <div class="mb-3">
                <label for="id_lowongan" class="form-label">ID Lowongan</label>
                <input type="text" class="form-control" id="id_lowongan" name="id_lowongan" required>
            </div>
            
            <div class="mb-3">
                <label for="jabatan_dipilih" class="form-label">Jabatan Dipilih</label>
                <input type="text" class="form-control" id="jabatan_dipilih" name="jabatan_dipilih" required>
            </div>
            <button type="submit" class="btn btn-primary">Submit</button>
        </form>
        <table class="table mt-3">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Nama</th>
                    <th>Email</th>
                    <th>ID Lowongan</th>
                    <th>Status</th>
                    <th>Jabatan Dipilih</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($pelamar as $row): ?>
                    <tr>
                        <td><?= $row['id_pelamar'] ?></td>
                        <td><?= $row['nama_pel'] ?></td>
                        <td><?= $row['email_pel'] ?></td>
                        <td><?= $row['id_lowongan'] ?></td>
                        <td><?= $row['status_pel'] ?></td>
                        <td><?= $row['jabatan_dipilih'] ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</main>
<?php
include '../template/footer.php';
?>