<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$cNoOp = $_POST['cNoOp'] ??'';
$cNoSTB = $_POST['cNoSTB'] ?? '';
$cKeterangan = $_POST['cKeterangan'] ?? '';
$cType = $_POST['cTipe'] ?? '';

if (!$cNoOp && !$cNoSTB) {
    echo json_encode(['success' => false, 'message' => 'No cNoOp or cNoSTB provided']);
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

// Columns according to tbWIPV2 select list
$fields = [
    'cNoSTB','cCom','dTanggal','cNoSc','cNamabrg','cNoMC','cNoOp','cKodeCust','cNama',
    'nPanjang','nLebar','nTinggi','cWarna','cJnsGel','cType','cSub1','cSub2','cSub3','cSub4','cSub5',
    'nQtyPalet','nQtyCol','nIsi','nQtyLbr','nQtyKgFisik','nQtyKgFormula','dTglSerah','lPosted','lVoid','lClose','lBeli',
    'dTglBtlPost','dTglVoid','cAlasanBatal','cAlasanVoid','UserId','UserDate','ComputerName','cUserComp','AppName',
    'cMengetahui','cSetujui','cDepUser','cDepSetujui','cDepMengetahui','nPrinted','nRevisi','cJns','cNoid','nberat',
    'nOrder','cKdSales','cNamaSales','nQty','nQtyKg','nPrint','lProd','cKeterangan','cNamaComp','cNoOpLast','cDep',
    'cRak','cShift','dTglkirim','nQtyOut','cOutSTB','dTanggalOut'
];

$fieldsList = implode(',', $fields);
$placeholders = implode(',', array_fill(0, count($fields), '?'));

$sqlSelect = "SELECT $fieldsList FROM tbWIPV2 WHERE ".($cNoSTB ? 'cNoSTB = ?' : 'cNoOp = ?');
$params = array($cNoSTB ? $cNoSTB : $cNoOp);
$stmt = sqlsrv_query($conn, $sqlSelect, $params);

if ($stmt === false) {
    echo json_encode(['success' => false, 'message' => 'Select query failed', 'error' => sqlsrv_errors()]);
    exit;
}

$row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
if ($row) {
    $insertParams = [];
    foreach ($fields as $f) {
        $insertParams[] = array_key_exists($f, $row) ? $row[$f] : null;
    }

    $sqlInsert = "INSERT INTO tbStbBJ ($fieldsList) VALUES ($placeholders)";
    $stmtInsert = sqlsrv_query($conn, $sqlInsert, $insertParams);

    if ($stmtInsert) {
        // Update lPosted and cKeterangan in tbWIPV2
        $sqlUpdate = "UPDATE tbWIPV2 SET lPosted = 1, cKeterangan = ? WHERE ".($cNoSTB ? 'cNoSTB = ?' : 'cNoOp = ?');
        $paramsUpdate = array($cKeterangan, $cNoSTB ? $cNoSTB : $cNoOp);
        sqlsrv_query($conn, $sqlUpdate, $paramsUpdate);

        echo json_encode(['success' => true]);
    } else {
        $errors = sqlsrv_errors();
        $errMsg = [];
        if ($errors !== null) {
            foreach ($errors as $error) {
                $errMsg[] = "SQLSTATE: " . ($error['SQLSTATE'] ?? '') . "; code: " . ($error['code'] ?? '') . "; message: " . ($error['message'] ?? '');
            }
        }
        echo json_encode(['success' => false, 'message' => 'Insert to tbStbBJ failed', 'error' => $errMsg]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Data not found in tbWIPV2']);
}