<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$barcode = $_GET['barcode'] ?? '';

if (!$barcode) {
    echo json_encode(['success' => false, 'message' => 'No barcode provided']);
    exit;
}

function mapRakName($cRak)
{
    $mapping = [
        '1' => 'A-1','2' => 'A-2','3' => 'B-1','4' => 'B-2','5' => 'C-1','6' => 'C-2','7' => 'CORRUGATING 1',
        '8' => 'CORRUGATING 2','9' => 'FOLDER GLUE','10' => 'FLADBAD','11' => 'FLEXO-1','12' => 'FLEXO-2',
        '13' => 'FLEXO-4','14' => 'FLEXO-5','15' => 'FLEXO-6','16' => 'FLEXO-7','17' => 'FLEXO-8',
        '18' => 'FLEXO-9','19' => 'IKAT', '20' => 'LANTHEC','21' => 'LANGSUNG KIRIM','22' => 'RDC',
        '23' => 'RAK-A','24' => 'RAK-B','25' => 'SLITTER','26' => 'STITCHING'
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
        throw new Exception("Connection failed.");
    }

    $sql = "SELECT cNoSTB, cNoOp, cNoSc, dTanggal, cNoMC, cNama, cKodeCust, cNamabrg, nPanjang, nLebar, nTinggi, cWarna, dTglkirim, nQty, nQtyKg, lPosted, lVoid, cCom, cTipe, nBerat, cSub1, cSub2, cSub3, cSub4, cSub5, cJnsGel, cNoid, cKeterangan, nIsi, cShift, cKashift
    FROM tbTmpWIP
    WHERE cNoSTB = ?";

    $params = array($barcode);
    $stmt = sqlsrv_query($conn, $sql, $params);

    if ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $row['nQty'] = isset($row['nQty']) ? floatval($row['nQty']) : null;
        $row['nQtyKg'] = isset($row['nQtyKg']) ? floatval($row['nQtyKg']) : null;
        $row['nPanjang'] = isset($row['nPanjang']) ? floatval($row['nPanjang']) : null;
        $row['nLebar'] = isset($row['nLebar']) ? floatval($row['nLebar']) : null;
        $row['nTinggi'] = isset($row['nTinggi']) ? floatval($row['nTinggi']) : null;
        $row['nIsi'] = isset($row['nIsi']) ? intval($row['nIsi']) : null;
        $row['cShift'] = isset($row['cShift']) ? $row['cShift'] : null;
        $row['cKashift'] = isset($row['cKashift']) ? $row['cKashift'] : null;
        $row['nTot_netto'] = isset($row['nTot_netto']) ? floatval($row['nTot_netto']) : null;
        $row['op_nPanjang'] = isset($row['op_nPanjang']) ? floatval($row['op_nPanjang']) : null;
        $row['op_nLebar'] = isset($row['op_nLebar']) ? floatval($row['op_nLebar']) : null;
        $row['op_nTinggi'] = isset($row['op_nTinggi']) ? floatval($row['op_nTinggi']) : null;
        $row['op_nQty'] = isset($row['op_nQty']) ? floatval($row['op_nQty']) : null;
        $row['op_nQtyStok'] = isset($row['nQtyStok']) ? floatval($row['nQtyStok']) : null;
        $row['dTanggal'] = $row['dTanggal'] ? $row['dTanggal']->format('Y-m-d') : null;
        $row['dTglkirim'] = $row['dTglkirim'] ? $row['dTglkirim']->format('Y-m-d') : null;
        $row['dTgl'] = isset($row['dTgl']) && $row['dTgl'] ? $row['dTgl']->format('Y-m-d') : null;

        echo json_encode(['success' => true, 'data' => $row]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Data not found']);
    }

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
