<?php
header('Content-Type: application/json');

// Koneksi ke SQL Server
$serverName = "spsdmz";
    $connectionOptions = array(
        "Database" => "dbSopanusa",
        "Uid" => "sa",
        "PWD" => "supracor",
        "LoginTimeout" => 15,
        "Encrypt" => false,
        "TrustServerCertificate" => true
);

// Koneksi ke database
$conn = sqlsrv_connect($serverName, $connectionOptions);
if ($conn === false) {
    die(print_r(sqlsrv_errors(), true));
}

// Ambil parameter dari request POST
$cNoOp = isset($_POST['cNoOp']) ? $_POST['cNoOp'] : '';
$nQty = isset($_POST['nQty']) ? $_POST['nQty'] : 0;

// Cek parameter
if (empty($cNoOp) || $nQty <= 0) {
    echo json_encode(array("status" => "error", "message" => "Parameter tidak valid"));
    exit;
}

// Query update
$sql = "UPDATE produk SET nQty = ? WHERE cNoOp = ?";
$params = array($nQty, $cNoOp);

// Eksekusi query
$stmt = sqlsrv_query($conn, $sql, $params);
if ($stmt === false) {
    echo json_encode(array("status" => "error", "message" => "Update gagal"));
} else {
    echo json_encode(array("status" => "success", "message" => "Qty berhasil diperbarui"));
}

// Tutup koneksi
sqlsrv_close($conn);
?>
