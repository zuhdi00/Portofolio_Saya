<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

$barcode = $_GET['barcode'] ?? '';

if (!$barcode) {
    echo json_encode(['success' => false, 'message' => 'No barcode provided']);
    exit;
}

function mapRakName($cRak)
{
    $mapping = [
        '1' => 'A-1',
        '2' => 'A-2',
        '3' => 'B-1',
        '4' => 'B-2',
        '5' => 'C-1',
        '6' => 'C-2',
        '7' => 'CORRUGATING 1',
        '8' => 'CORRUGATING 2',
        '9' => 'FOLDER GLUE',
        '10' => 'FLADBAD',
        '11' => 'FLEXO-1',
        '12' => 'FLEXO-2',
        '13' => 'FLEXO-4',
        '14' => 'FLEXO-5',
        '15' => 'FLEXO-6',
        '16' => 'FLEXO-7',
        '17' => 'FLEXO-8',
        '18' => 'FLEXO-9',
        '19' => 'IKAT',
        '20' => 'LANTHEC',
        '21' => 'LANGSUNG KIRIM',
        '22' => 'RDC',
        '23' => 'RAK-A',
        '24' => 'RAK-B',
        '25' => 'SLITTER',
        '26' => 'STITCHING'
    ];

    return $mapping[trim($cRak)] ?? '-';
}

try {
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
        throw new Exception("Connection failed: " . print_r($errors, true));
    }

    // Fixed SQL query - added missing comma after nQtyStok
    $sql = "SELECT 
                op.cnm_c, op.cNoMc, op.cNoOp, op.cnm_brg, op.cNoSc,
                op.nPanjang, op.nLebar, op.nTinggi, op.nQtyStok, op.nTot_netto,
                op.nQty, op.dTgl, op.clengkap1, op.clengkap2, op.dTglkirim, op.cpr_ikat, op.clengkap3, op.clengkap4,
                op.cTipe, op.nTot_netto, op.cWarna, op.cJnsGel, op.nbrutto_usp, op.nbrutto_usl,
                op.ckd_b1, op.ckd_b2, op.ckd_b3, op.ckd_b4, op.ckd_b5, op.cJnsGel, op.cFlexo, op.cKd_C
            FROM tbOP op
            WHERE op.cNoOp = ?";

    $params = array($barcode);
    $stmt = sqlsrv_query($conn, $sql, $params);

    // Check if query execution was successful
    if ($stmt === false) {
        $errors = sqlsrv_errors();
        throw new Exception("Insert failed: " . print_r($errors, true));
    }

    if ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $row['nQty'] = floatval($row['nQty']);
        $row['nQtyStok'] = floatval($row['nQtyStok']);
        $row['nPanjang'] = floatval($row['nPanjang']);
        $row['nLebar'] = floatval($row['nLebar']);
        $row['nTinggi'] = floatval($row['nTinggi']);
        $row['clengkap4'] = floatval($row['clengkap4']);

        $row['dTgl'] = $row['dTgl'] ? $row['dTgl']->format('d-m-Y') : null;
        $row['dTglkirim'] = $row['dTglkirim'] ? $row['dTglkirim']->format('d-m-Y') : null;

        // Mapping rak (uncomment if needed)
        // $row['cRak'] = mapRakName($row['cRak']);

        echo json_encode(['success' => true, 'data' => $row]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Data not found']);
    }

    // Close statement and connection
    sqlsrv_free_stmt($stmt);
    sqlsrv_close($conn);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}