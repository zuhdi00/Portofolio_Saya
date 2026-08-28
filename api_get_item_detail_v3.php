<?php
/**
 * API untuk get item detail dari tabel tbOP
 * Method: GET
 * Parameter: barcode (cNoOp value)
 * Returns: JSON dengan struktur ItemDetail
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$barcode = $_GET['barcode'] ?? '';

if (!$barcode) {
    echo json_encode(['success' => false, 'message' => 'No barcode provided']);
    exit;
}

try {
    // Koneksi SQL Server
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
        throw new Exception("Connection failed: " . print_r(sqlsrv_errors(), true));
    }

    // Query dari tbOP sesuai dengan struktur ItemDetail model
    $sql = "SELECT 
                cNoOp, 
                dTgl, 
                cNoSc, 
                dTglkirim, 
                nQty, 
                nQty_corr, 
                nRm,
                cMengetahui, 
                cNoMc, 
                cnm_c, 
                cnm_brg, 
                cLayer, 
                cTipe,
                nTot_netto, 
                nPanjang, 
                nLebar, 
                nTinggi, 
                cWarna,
                cRak
            FROM tbOP
            WHERE cNoOp = ?";

    $params = array($barcode);
    $stmt = sqlsrv_query($conn, $sql, $params);

    if (!$stmt) {
        throw new Exception("Query failed: " . print_r(sqlsrv_errors(), true));
    }

    if ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        // Convert data types
        $row['nQty'] = floatval($row['nQty'] ?? 0);
        $row['nQty_corr'] = floatval($row['nQty_corr'] ?? 0);
        $row['nRm'] = floatval($row['nRm'] ?? 0);
        $row['nTot_netto'] = floatval($row['nTot_netto'] ?? 0);
        $row['nPanjang'] = floatval($row['nPanjang'] ?? 0);
        $row['nLebar'] = floatval($row['nLebar'] ?? 0);
        $row['nTinggi'] = floatval($row['nTinggi'] ?? 0);
        
        // Format dates
        if ($row['dTgl'] instanceof DateTime) {
            $row['dTgl'] = $row['dTgl']->format('Y-m-d');
        }
        if ($row['dTglkirim'] instanceof DateTime) {
            $row['dTglkirim'] = $row['dTglkirim']->format('Y-m-d');
        }

        echo json_encode(['success' => true, 'data' => $row]);
    } else {
        // Log the error for debugging
        error_log("Data not found for barcode: " . $barcode);
        echo json_encode(['success' => false, 'message' => 'Data tidak ditemukan untuk barcode: ' . $barcode]);
    }

    sqlsrv_free_stmt($stmt);
    sqlsrv_close($conn);

} catch (Exception $e) {
    error_log("API Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'API Error: ' . $e->getMessage()]);
}
?>
