<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

// Validasi token keamanan
$token = $_GET['token'] ?? '';
if ($token !== 'my_secure_token') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

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
        throw new Exception("Connection failed.");
    }

    $sql = "SELECT 
                bj.cNoSTB, bj.cNamabrg, bj.cNoMC, bj.cNoOp, bj.cNama,
                bj.nPanjang, bj.nLebar, bj.nTinggi, bj.cWarna,
                bj.cSub1, bj.cSub2, bj.cSub3, bj.cSub4, bj.cSub5,
                bj.nQty, bj.nQtyKg, bj.dTanggal, bj.cRak,
                op.cpr_ikat, op.dTglkirim
            FROM tbStbBJ bj
            LEFT JOIN tbOP op ON bj.cNoOp = op.cNoOp
            WHERE bj.cNoOp = ?";

    $params = array($barcode);
    $stmt = sqlsrv_query($conn, $sql, $params);

    if ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $row['nQty'] = floatval($row['nQty']);
        $row['nQtyKg'] = floatval($row['nQtyKg']);
        $row['nPanjang'] = floatval($row['nPanjang']);
        $row['nLebar'] = floatval($row['nLebar']);
        $row['nTinggi'] = floatval($row['nTinggi']);

        $row['dTanggal'] = $row['dTanggal'] ? $row['dTanggal']->format('Y-m-d') : null;
        $row['dTglkirim'] = $row['dTglkirim'] ? $row['dTglkirim']->format('Y-m-d') : null;

        // Mapping rak
        $row['cRak'] = mapRakName($row['cRak']);

        echo json_encode(['success' => true, 'data' => $row]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Data not found']);
    }

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
