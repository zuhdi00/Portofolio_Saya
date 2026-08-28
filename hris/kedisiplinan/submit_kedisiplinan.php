<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

include '../connection.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Fetching data from the form
    $sanksi = $_POST['sanksi'];
    $jenis_pelanggaran = $_POST['jenis_pelanggaran'];
    $id_peg = $_POST['id_peg'];

    // Insert the discipline data into the database
    $query = "INSERT INTO `kedisiplinan` (sanksi, jenis_pelanggaran, id_peg) VALUES ('$sanksi', '$jenis_pelanggaran', '$id_peg')";
    if ($conn->query($query) === TRUE) {
        // Redirect or display a success message
        header("Location: index.php");
        exit();
    } else {
        echo "Error: " . $query . "<br>" . $conn->error;
    }
}
?>

<!-- Form for submitting discipline data -->
<form method="POST" action="">
    <label for="sanksi">Sanksi:</label>
    <input type="text" id="sanksi" name="sanksi" required>
    
    <label for="jenis_pelanggaran">Jenis Pelanggaran:</label>
    <input type="text" id="jenis_pelanggaran" name="jenis_pelanggaran" required>
    
    <label for="id_peg">ID Pegawai:</label>
    <input type="text" id="id_peg" name="id_peg" required>
    
    <input type="submit" value="Submit">
</form>

<!-- Displaying the discipline data -->
<table>
    <thead>
        <tr>
            <th>ID Kedisiplinan</th>
            <th>Sanksi</th>
            <th>Jenis Pelanggaran</th>
            <th>ID Pegawai</th>
        </tr>
    </thead>
    <tbody>
        <?php
        $kedisiplinan_data = $conn->query("SELECT * FROM `kedisiplinan`");
        foreach ($kedisiplinan_data as $row): ?>
            <tr>
                <td><?= $row['id_kedisiplinan'] ?></td>
                <td><?= $row['sanksi'] ?></td>
                <td><?= $row['jenis_pelanggaran'] ?></td>
                <td><?= $row['id_peg'] ?></td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>