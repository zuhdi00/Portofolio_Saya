<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

// Debug: Log incoming parameters
error_log('api_get_op_detail.php called - GET: ' . json_encode($_GET) . ', POST: ' . json_encode($_POST));

// Get barcode from both GET dan POST untuk fleksibilitas
$barcode = trim($_GET['barcode'] ?? $_POST['barcode'] ?? '');

if (!$barcode) {
    echo json_encode(['success' => false, 'message' => 'No barcode provided', 'debug' => $_GET]);
    error_log('api_get_op_detail.php: No barcode provided');
    exit;
}

try {
    // Koneksi SQL Server
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

    if (!$conn) {
        $errors = sqlsrv_errors();
        throw new Exception("Connection failed: " . json_encode($errors));
    }

    $sql = "SELECT
                cNoOp, dTgl, cNoSc, dTglkirim, nQty, nQty_corr, nRm,
                cMengetahui, cNoMc, cnm_c, cnm_brg, cLayer, cTipe,
                nTot_netto, nPanjang, nLebar, nTinggi, cWarna
            FROM tbOP
            WHERE cNoOp = ?";

    $params = array($barcode);
    $stmt = sqlsrv_query($conn, $sql, $params);

    if ($stmt === false) {
        $errors = sqlsrv_errors();
        throw new Exception("Query failed: " . json_encode($errors));
    }

    if ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        // Convert numeric fields to proper type (float)
        $numericFields = ['nQty', 'nQty_corr', 'nRm', 'nTot_netto', 'nPanjang', 'nLebar', 'nTinggi'];
        foreach ($numericFields as $field) {
            if (isset($row[$field])) {
                $row[$field] = floatval($row[$field]);
            } else {
                $row[$field] = 0;
            }
        }

        // Format date fields to string
        if (isset($row['dTgl'])) {
            $row['dTgl'] = $row['dTgl'] instanceof DateTime ? $row['dTgl']->format('Y-m-d') : (string)$row['dTgl'];
        }
        if (isset($row['dTglkirim'])) {
            $row['dTglkirim'] = $row['dTglkirim'] instanceof DateTime ? $row['dTglkirim']->format('Y-m-d') : (string)$row['dTglkirim'];
        }

        error_log('api_get_op_detail.php: Data found for barcode ' . $barcode);
        echo json_encode(['success' => true, 'data' => $row]);
    } else {
        error_log('api_get_op_detail.php: Data NOT found for barcode ' . $barcode);
        echo json_encode(['success' => false, 'message' => 'Data OP tidak ditemukan untuk barcode: ' . $barcode]);
    }

    sqlsrv_free_stmt($stmt);
    sqlsrv_close($conn);

} catch (Exception $e) {
    error_log('api_get_op_detail.php Exception: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>