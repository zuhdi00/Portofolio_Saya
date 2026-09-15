<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

include '../config/koneksi_sqlsrv.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Fetching data from the form
    $id_pelamar = $_POST['id_pelamar'];
    $nama_pel = $_POST['nama_pel'];
    $email_pel = $_POST['email_pel'];
    $id_lowongan = $_POST['id_lowongan'];
   
    $jabatan_dipilih = $_POST['jabatan_dipilih'];

    // Insert the applicant data into the database
    $query = "INSERT INTO dbo.pelamar (id_pelamar, nama_pel, email_pel, id_lowongan, jabatan_dipilih) VALUES (?, ?, ?, ?, ?)";
    $stmt = sqlsrv_query($conn, $query, [$id_pelamar, $nama_pel, $email_pel, $id_lowongan, $jabatan_dipilih]);
    if ($stmt !== false) {
        echo "Data pelamar berhasil disimpan.";
    } else {
        echo "Data pelamar gagal disimpan.";
    }

    // Redirect or display a success message
    header("Location: index.php");
    exit();
}
?>