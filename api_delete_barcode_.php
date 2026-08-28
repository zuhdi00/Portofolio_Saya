<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

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

$response = array('success' => false, 'message' => '');

if (!$conn) {
    $response['message'] = 'Connection failed';
    echo json_encode($response);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $barcode = isset($_POST['barcode']) ? trim($_POST['barcode']) : '';

    if ($barcode === '') {
        $response['message'] = 'Barcode tidak boleh kosong';
        echo json_encode($response);
        exit;
    }

    // Hapus data dari tbTmpStbBJ berdasarkan barcode
    $sql = "DELETE FROM tbTmpStbBJ WHERE cNoSTB = ?";
    $params = array($barcode);
    $stmt = sqlsrv_query($conn, $sql, $params);

    if ($stmt) {
        // Cek berapa baris yang terhapus
        $rowsAffected = sqlsrv_rows_affected($stmt);
        if ($rowsAffected > 0) {
            $response['success'] = true;
            $response['message'] = 'Barcode berhasil dihapus';
        } else {
            $response['message'] = 'Data tidak ditemukan atau sudah dihapus';
        }
    } else {
        // Tambahkan debug error
        if (($errors = sqlsrv_errors()) != null) {
            $errMsg = [];
            foreach ($errors as $error) {
                $errMsg[] = "SQLSTATE: ".$error['SQLSTATE']."; code: ".$error['code']."; message: ".$error['message'];
            }
            $response['message'] = 'Gagal menghapus barcode';
            $response['error'] = $errMsg;
        } else {
            $response['message'] = 'Gagal menghapus barcode';
        }
    }

    sqlsrv_free_stmt($stmt);
    sqlsrv_close($conn);
} else {
    $response['message'] = 'Metode tidak diizinkan';
}

echo json_encode($response);
?>