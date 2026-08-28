<?php
// --- PHP logic section (pisah dari HTML) ---
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
    die("Koneksi gagal: " . print_r($errors, true));
}

// Cari berdasarkan Nomor SC
$no_sc = $_GET['sc'] ?? '';
$no_sc_search = trim(str_replace(' ', '', strtoupper($no_sc)));
$tgl_kirim = $_GET['tgl_kirim'] ?? '';
$artikel = $_GET['artikel'] ?? '';
$search = $_GET['search'] ?? '';

function queryOrDie($conn, $sql, $params, $label) {
    $stmt = sqlsrv_query($conn, $sql, $params);
    if ($stmt === false) {
        $errors = sqlsrv_errors();
        die("Query $label gagal: " . print_r($errors, true));
    }
    return $stmt;
}


// Pencarian fleksibel: hilangkan spasi, case-insensitive, LIKE
$sqlSC = "SELECT TOP 1 * FROM tbSC WHERE REPLACE(UPPER(cNoSc), ' ', '') LIKE ?";
$stmtSC = queryOrDie($conn, $sqlSC, array('%'.$no_sc_search.'%'), 'SC');
$dataSC = sqlsrv_fetch_array($stmtSC, SQLSRV_FETCH_ASSOC);
sqlsrv_free_stmt($stmtSC);

$sqlCorr = "SELECT TOP 10 * FROM tbCorr WHERE cNoSc = ? ORDER BY dTanggal";
$stmtCorr = queryOrDie($conn, $sqlCorr, array($no_sc), 'Corr');
$dataCorr = [];
while ($row = sqlsrv_fetch_array($stmtCorr, SQLSRV_FETCH_ASSOC)) $dataCorr[] = $row;
sqlsrv_free_stmt($stmtCorr);

$sqlHslCorr = "SELECT TOP 10 * FROM tbHslCorr WHERE cNoSc = ? ORDER BY dTanggal";
$stmtHslCorr = queryOrDie($conn, $sqlHslCorr, array($no_sc), 'HslCorr');
$dataHslCorr = [];
while ($row = sqlsrv_fetch_array($stmtHslCorr, SQLSRV_FETCH_ASSOC)) $dataHslCorr[] = $row;
sqlsrv_free_stmt($stmtHslCorr);

$sqlStbBJ = "SELECT TOP 10 * FROM tbStbBJ WHERE cNoSc = ? ORDER BY dTanggal";
$stmtStbBJ = queryOrDie($conn, $sqlStbBJ, array($no_sc), 'StbBJ');
$dataStbBJ = [];
while ($row = sqlsrv_fetch_array($stmtStbBJ, SQLSRV_FETCH_ASSOC)) $dataStbBJ[] = $row;
sqlsrv_free_stmt($stmtStbBJ);

$sqlSRJDtl = "SELECT TOP 10 * FROM tbSRJDtl WHERE cNoSc = ? ORDER BY dTanggal";
$stmtSRJDtl = queryOrDie($conn, $sqlSRJDtl, array($no_sc), 'SRJDtl');
$dataSRJDtl = [];
while ($row = sqlsrv_fetch_array($stmtSRJDtl, SQLSRV_FETCH_ASSOC)) $dataSRJDtl[] = $row;
sqlsrv_free_stmt($stmtSRJDtl);

$sqlRtSrjDtl = "SELECT TOP 10 * FROM tbRtSrjDtl WHERE cNoSc = ? ORDER BY dTanggal";
$stmtRtSrjDtl = queryOrDie($conn, $sqlRtSrjDtl, array($no_sc), 'RtSrjDtl');
$dataRtSrjDtl = [];
while ($row = sqlsrv_fetch_array($stmtRtSrjDtl, SQLSRV_FETCH_ASSOC)) $dataRtSrjDtl[] = $row;
sqlsrv_free_stmt($stmtRtSrjDtl);

sqlsrv_close($conn);
// --- END PHP logic section ---
