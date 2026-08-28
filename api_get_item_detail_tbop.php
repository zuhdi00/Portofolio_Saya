<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$barcode = $_GET['barcode'] ?? '';

if (!$barcode) {
    echo json_encode(['success' => false, 'message' => 'No barcode provided']);
    exit;
}

try {
    // Kueri ke tbOP seperti versi 251101: tarikan hanya dari tbOP
    $serverName = "spsdmz";
    $connectionOptions = array(
        "Database" => "dbSopanusa",
        "Uid" => "sa",
        "PWD" => "supracor",
        "LoginTimeout" => 15,
        "Encrypt" => false,
        "TrustServerCertificate" => true
    );
    $conn = sqlsrv_connect($serverName, $connectionOptions);

    if (!$conn) {
        throw new Exception("Connection failed.");
    }

    // Coba exact match, lalu fallback ke LIKE (handles encoded/slash variants)
    $sql = "SELECT
                cNoOp, dTgl, cNoSc, dTglkirim, nQty, nQty_corr, nRm,
                cMengetahui, cNoMc, cnm_c, cnm_brg, cLayer, cTipe,
                nTot_netto, nPanjang, nLebar, nTinggi, cWarna
            FROM tbOP
            WHERE cNoOp = ? OR cNoOp LIKE ?";

    $likeParam = '%' . $barcode . '%';
    $params = array($barcode, $likeParam);
    $stmt = sqlsrv_query($conn, $sql, $params);

    if ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        echo json_encode(['success' => true, 'data' => $row]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Data not found']);
    }

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

?>