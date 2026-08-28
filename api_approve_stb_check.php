 <?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$cNoSTB = $_POST['cNoSTB'] ?? '';
$cKeterangan = $_POST['cKeterangan'] ?? '';
$cType = $_POST['cTipe'] ?? '';

if (!$cNoSTB) {
    echo json_encode(['success' => false, 'message' => 'No cNoSTB provided']);
    exit;
}

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
    echo json_encode(['success' => false, 'message' => 'Connection failed']);
    exit;
}

// Ambil cNoOp dari tbTmpStbBJ
$sqlGetOp = "SELECT cNoOp FROM tbTmpStbBJ WHERE cNoSTB = ?";
$stmtGetOp = sqlsrv_query($conn, $sqlGetOp, array($cNoSTB));
if (!$stmtGetOp || !($rowOp = sqlsrv_fetch_array($stmtGetOp, SQLSRV_FETCH_ASSOC))) {
    echo json_encode(['success' => false, 'message' => 'cNoOp not found for this STB']);
    exit;
}
$cNoOp = $rowOp['cNoOp'];

// Hitung total nQty untuk OP yang sama di tbStbBJ
$sqlTotalQty = "SELECT SUM(nQty) as totalQty FROM tbStbBJ WHERE cNoOp = ?";
$stmtTotalQty = sqlsrv_query($conn, $sqlTotalQty, array($cNoOp));
$totalQty = 0;
if ($stmtTotalQty && ($rowQty = sqlsrv_fetch_array($stmtTotalQty, SQLSRV_FETCH_ASSOC))) {
    $totalQty = floatval($rowQty['totalQty']);
}

// Ambil nToleransi dan nQty dari tbOP
$sqlOp = "SELECT nToleransi, nQty FROM tbOP WHERE cNoOp = ?";
$stmtOp = sqlsrv_query($conn, $sqlOp, array($cNoOp));
if (!$stmtOp || !($rowOpData = sqlsrv_fetch_array($stmtOp, SQLSRV_FETCH_ASSOC))) {
    echo json_encode(['success' => false, 'message' => 'OP data not found']);
    exit;
}
$nToleransi = floatval($rowOpData['nToleransi']);
$nQtyOrder = floatval($rowOpData['nQty']);

// Hitung batas maksimal
$maxQty = $nQtyOrder + (($nToleransi/100) * $nQtyOrder);

if ($totalQty >= $maxQty) {
    echo json_encode(['success' => false, 'message' => 'STB Melebihi OP + toleransi']);
    exit;
}

// Kolom yang sama di kedua tabel (urutan harus sama!)
$fields = [
    'cNoSTB', 'cNoOp', 'cNoSc', 'dTanggal', 'cNoMC', 'cNama', 'cKodeCust', 'cNamabrg',
    'nPanjang', 'nLebar', 'nTinggi', 'cWarna', 'dTglkirim', 'nQty', 'nQtyKg', 'lPosted',
    'lVoid', 'cCom', 'cType', 'nBerat', 'cSub1', 'cSub2', 'cSub3', 'cSub4', 'cSub5',
    'cJnsGel', 'cNoid', 'cKeterangan', 'nOrder'
];

$fieldsList = implode(',', $fields);
$placeholders = implode(',', array_fill(0, count($fields), '?'));

$sqlSelect = "SELECT * FROM tbTmpStbBJ WHERE cNoSTB = ?";
$params = array($cNoSTB);
$stmt = sqlsrv_query($conn, $sqlSelect, $params);

if ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $insertParams = [];
    foreach ($fields as $f) {
        if ($f === 'cType') {
            $insertParams[] = $row['cTipe'] ?? null;
        } else {
            $insertParams[] = $row[$f] ?? null;
        }
    }

    $sqlInsert = "INSERT INTO tbStbBJ ($fieldsList) VALUES ($placeholders)";
    $stmtInsert = sqlsrv_query($conn, $sqlInsert, $insertParams);

    if ($stmtInsert) {
        // Update lPosted dan cKeterangan di tbTmpStbBJ
        $sqlUpdate = "UPDATE tbTmpStbBJ SET lPosted = 1, cKeterangan = ? WHERE cNoSTB = ?";
        $paramsUpdate = array($cKeterangan, $cNoSTB);
        sqlsrv_query($conn, $sqlUpdate, $paramsUpdate);

        echo json_encode(['success' => true]);
    } else {
        // Tambahkan debug error
        if (($errors = sqlsrv_errors()) != null) {
            $errMsg = [];
            foreach ($errors as $error) {
                $errMsg[] = "SQLSTATE: ".$error['SQLSTATE']."; code: ".$error['code']."; message: ".$error['message'];
            }
            echo json_encode(['success' => false, 'message' => 'Insert to tbStbBJ failed', 'error' => $errMsg]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Insert to tbStbBJ failed']);
        }
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Data not found in tbTmpStbBJ']);
}
