<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

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

// Map of target DB columns => possible client field names (aliases)
$columnAliases = [
    'cNoSTB'    => ['cNoSTB','cnm_stb','no_stb'],
    'cCom'      => ['cCom','ccom'],
    'dTanggal'  => ['dTanggal','dTgl','tanggal'],
    'cNoSc'     => ['cNoSc','cNoSC'],
    'cNamabrg'  => ['cNamabrg','cnm_brg','cnmbrg','nama_brg'],
    'cNoMC'     => ['cNoMC','cNoMc','no_mc'],
    'cNoOp'     => ['cNoOp','cNoOP','cNoop','cNoOpValue'],
    'cKodeCust' => ['cKodeCust','cKodecust','kode_cust'],
    'cNama'     => ['cNama','cnm_c','cnm_c','nama_c'],
    'nPanjang'  => ['nPanjang','nPjg','panjang'],
    'nLebar'    => ['nLebar','nLbr','lebar'],
    'nTinggi'   => ['nTinggi','nTgi','tinggi'],
    'cWarna'    => ['cWarna','warna'],
    'cJnsGel'   => ['cJnsGel','cJns','jenis_gel'],
    'cType'     => ['cType','ctype'],
    'cSub1'     => ['cSub1','csub1'],
    'cSub2'     => ['cSub2','csub2'],
    'cSub3'     => ['cSub3','csub3'],
    'cSub4'     => ['cSub4','csub4'],
    'cSub5'     => ['cSub5','csub5'],
    'nQty'      => ['nQty','qty','quantity'],
    'nQtyKg'    => ['nQtyKg','nQtykg','qtykg'],
    'dTglkirim' => ['dTglkirim','tgl_kirim'],
    'lPosted'   => ['lPosted','posted','lposted'],
    'cKeterangan'=> ['cKeterangan','keterangan','cKet'],
    'cRak'      => ['cRak','rak']
];

$columns = [];
$placeholders = [];
$params = [];

// For each expected DB column, look for any of its aliases in POST and use the first non-empty value
foreach ($columnAliases as $col => $aliases) {
    foreach ($aliases as $key) {
        if (isset($_POST[$key]) && $_POST[$key] !== '') {
            $columns[] = $col;
            $placeholders[] = '?';
            $params[] = $_POST[$key];
            break;
        }
    }
    // if not found, don't include the column (DB default / nullable will apply)
}

if (count($columns) === 0) {
    echo json_encode(['success' => false, 'message' => 'No data provided']);
    exit;
}

$colList = implode(',', $columns);
$phList = implode(',', $placeholders);

$sql = "INSERT INTO tbWIPV2 ($colList) VALUES ($phList)";
$stmt = sqlsrv_query($conn, $sql, $params);

if ($stmt) {
    echo json_encode(['success' => true]);
} else {
    $errors = sqlsrv_errors();
    $errMsg = [];
    if ($errors !== null) {
        foreach ($errors as $error) {
            $errMsg[] = "SQLSTATE: " . ($error['SQLSTATE'] ?? '') . "; code: " . ($error['code'] ?? '') . "; message: " . ($error['message'] ?? '');
        }
    }
    echo json_encode(['success' => false, 'message' => 'Insert failed', 'error' => $errMsg, 'sql' => $sql, 'params' => $params]);
}

?>
