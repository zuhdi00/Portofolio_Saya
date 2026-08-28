<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$barcode = $_GET['barcode'] ?? '';

if (!$barcode) {
    echo json_encode(['success' => false, 'message' => 'No barcode provided']);
    exit;
}

try {
    // Ganti dengan koneksi SQL Server kamu
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

    $sql = "SELECT
                cNoOp, dTgl, cNoSc, dTglkirim, nQty, nQty_corr, nRm,
                cMengetahui, cNoMc, cnm_c, cnm_brg, cLayer, cTipe,
                nTot_netto, nPanjang, nLebar, nTinggi, cWarna
            FROM tbOP
            WHERE cNoOp = ?";

    $params = array($barcode);
    $stmt = sqlsrv_query($conn, $sql, $params);

    if ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        // Convert numeric fields to proper type (float)
        $row['nQty'] = floatval($row['nQty']);
        $row['nQty_corr'] = floatval($row['nQty_corr']);
        $row['nRm'] = floatval($row['nRm']);
        $row['nTot_netto'] = floatval($row['nTot_netto']);
        $row['nPanjang'] = floatval($row['nPanjang']);
        $row['nLebar'] = floatval($row['nLebar']);
        $row['nTinggi'] = floatval($row['nTinggi']);

        // Format date fields to string
        $row['dTgl'] = $row['dTgl'] instanceof DateTime ? $row['dTgl']->format('Y-m-d') : null;
		$row['dTglkirim'] = $row['dTglkirim'] instanceof DateTime ? $row['dTglkirim']->format('Y-m-d') : null;


        echo json_encode(['success' => true, 'data' => $row]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Data not found']);
    }

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
