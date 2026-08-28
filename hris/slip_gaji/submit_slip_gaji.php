<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

include '../connection.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Mengambil data dari form
    $id_slip_gaji = $_POST['id_slip_gaji'];
    $bulan_tahun = $_POST['bulan_tahun'];
    $gaji_pokok = $_POST['gaji_pokok'];
    $tunjangan = $_POST['tunjangan'];
    $potongan = $_POST['potongan'];
    $total_gaji = $gaji_pokok + $tunjangan - $potongan;
    $id_peg = $_POST['id_peg'];

    // Query untuk insert data
    $query = "INSERT INTO slip_gaji (id_slip_gaji, bulan_tahun, gaji_pokok, tunjangan, potongan, total_gaji, id_peg) 
              VALUES ('$id_slip_gaji', '$bulan_tahun', '$gaji_pokok', '$tunjangan', '$potongan', '$total_gaji', '$id_peg')";
    
    if ($conn->query($query) === TRUE) {
        echo "<script>
                alert('Data slip gaji berhasil disimpan');
                window.location.href = 'index.php';
              </script>";
    } else {
        echo "Error: " . $query . "<br>" . $conn->error;
    }
}
?>
