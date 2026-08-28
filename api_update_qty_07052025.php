<?php
header('Content-Type: application/json');

// Koneksi ke SQL Server
$serverName = "spsdmz2";
    $connectionOptions = array(
        "Database" => "dbSopanusa",
        "Uid" => "sa",
        "PWD" => "supracor",
        "LoginTimeout" => 15,
        "Encrypt" => false,
        "TrustServerCertificate" => true
);

$conn = sqlsrv_connect($serverName, $connectionOptions);
if ($conn === false) {
    die(print_r(sqlsrv_errors(), true));
}

$cNoOp = isset($_POST['cNoOp']) ? $_POST['cNoOp'] : '';
$nQty = isset($_POST['nQty']) ? $_POST['nQty'] : 0;

if (empty($cNoOp) || $nQty <= 0) {
    echo json_encode(array("status" => "error", "message" => "Parameter tidak valid"));
    exit;
}

$sql = "UPDATE produk SET nQty = ? WHERE cNoOp = ?";
$params = array($nQty, $cNoOp);

$stmt = sqlsrv_query($conn, $sql, $params);
if ($stmt === false) {
    echo json_encode(array("status" => "error", "message" => "Update gagal"));
} else {
    echo json_encode(array("status" => "success", "message" => "Qty berhasil diperbarui"));
}

sqlsrv_close($conn);
?>
