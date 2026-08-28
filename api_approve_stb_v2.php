<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$cNoOp = $_POST['cNoOp'] ?? '';
$cKeterangan = $_POST['cKeterangan'] ?? '';
$cType = $_POST['cTipe'] ?? '';

if (!$cNoOp) {
    echo json_encode(['success' => false, 'message' => 'No cNoOp provided']);
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

// Kolom yang sama di kedua tabel (urutan harus sama!)
$fields = [
    'cNoSTB', 'cNoOp', 'cNoSc', 'dTanggal', 'cNoMC', 'cNama', 'cKodeCust', 'cNamabrg',
    'nPanjang', 'nLebar', 'nTinggi', 'cWarna', 'dTglkirim', 'nQty', 'nQtyKg', 'lPosted',
    'lVoid', 'cCom', 'cType', 'nBerat', 'cSub1', 'cSub2', 'cSub3', 'cSub4', 'cSub5',
    'cJnsGel', 'cNoid', 'cKeterangan', 'nOrder'
];

$fieldsList = implode(',', $fields);
$placeholders = implode(',', array_fill(0, count($fields), '?'));

$sqlSelect = "SELECT * FROM tbOp WHERE cNoOP = ?";
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