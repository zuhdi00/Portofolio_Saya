<?php
// report_realisasi_data.php

$serverName = "spsdmz2";
$connectionOptions = array(
    "Database" => "dbSopanusa",
    "Uid" => "sa",
    "PWD" => "supracor",
    "LoginTimeout" => 30,
    "Encrypt" => false,
    "TrustServerCertificate" => true,
    "ReturnDatesAsStrings" => true
);

$conn = sqlsrv_connect($serverName, $connectionOptions);

if (!$conn) {
    die(print_r(sqlsrv_errors(), true));
}

$no_sc = isset($_GET['sc']) ? trim($_GET['sc']) : '';

// Inisialisasi Variabel Data agar tidak error jika kosong
$dataSC = null;
$dataCorr = [];
$dataHslCorr = [];
$dataStbBJ = [];
$dataDelivery = [];
$dataRetur = [];
$dataMesin = [];
$summary = [
    'plan_corr' => 0, 'hasil_corr' => 0, 'stb_qty' => 0, 'kirim_qty' => 0, 'retur_qty' => 0
];

if (!empty($no_sc)) {
    // 1. DATA HEADER (Info SC/SLC)
    $sqlSC = "SELECT TOP 1 
                cNoSC, cNama AS Customer, nQty AS OrderQty, cWarna, nPanjang, nLebar, nTinggi,
                cJnsGel, nBrtBox, dTglKirim, lTK
              FROM tbSC 
              WHERE cNoSC = ?";
    $stmtSC = sqlsrv_query($conn, $sqlSC, array($no_sc));
    if ($stmtSC === false) {
        die(print_r(sqlsrv_errors(), true));
    }
    if (sqlsrv_has_rows($stmtSC)) {
        $dataSC = sqlsrv_fetch_array($stmtSC, SQLSRV_FETCH_ASSOC);
    }

    // 2. PLANNING CORRUGATING (tbCorr)
    $sqlCorr = "SELECT dTanggal, nBerat, cKodeCorr, nBrgKg, cKeterangan 
                FROM tbCorr 
                WHERE cNoSc = ? AND lVoid = 0 
                ORDER BY dTanggal ASC";
    $stmtCorr = sqlsrv_query($conn, $sqlCorr, array($no_sc));
    if ($stmtCorr === false) {
        die(print_r(sqlsrv_errors(), true));
    }
    while ($row = sqlsrv_fetch_array($stmtCorr, SQLSRV_FETCH_ASSOC)) {
        $dataCorr[] = $row;
        $summary['plan_corr'] += $row['nBerat'];
    }

    // 3. HASIL CORRUGATING (tbHslCorr)
    $sqlHsl = "SELECT dTanggal, nBrgKg, cKodeCorr, nJmlMeter 
               FROM tbHslCorr 
               WHERE cNoSc = ? AND lVoid = 0 
               ORDER BY dTanggal ASC";
    $stmtHsl = sqlsrv_query($conn, $sqlHsl, array($no_sc));
    if ($stmtHsl === false) {
        die(print_r(sqlsrv_errors(), true));
    }
    while ($row = sqlsrv_fetch_array($stmtHsl, SQLSRV_FETCH_ASSOC)) {
        $dataHslCorr[] = $row;
        $summary['hasil_corr'] += $row['nBrgKg'];
    }

    // 4. SERAH TERIMA BARANG JADI (tbStbBJ)
    $sqlStb = "SELECT dTanggal, nQty, cNoStb, cKeterangan 
               FROM tbStbBJ 
               WHERE cNoSc = ? AND lVoid = 0 
               ORDER BY dTanggal ASC";
    $stmtStb = sqlsrv_query($conn, $sqlStb, array($no_sc));
    if ($stmtStb === false) {
        die(print_r(sqlsrv_errors(), true));
    }
    while ($row = sqlsrv_fetch_array($stmtStb, SQLSRV_FETCH_ASSOC)) {
        $dataStbBJ[] = $row;
        $summary['stb_qty'] += $row['nQty'];
    }

    // 5. DATA PENGIRIMAN (Join tbSRJ & tbSRJDtl)
    $sqlKirim = "SELECT H.cNoSrj, H.dTanggal, H.cNoKend, D.nQty, D.nBerat 
                 FROM tbSRJDtl D
                 INNER JOIN tbSRJ H ON D.cNoSrj = H.cNoSrj 
                 WHERE D.cNoSc = ? AND H.lVoid = 0 
                 ORDER BY H.dTanggal ASC";
    $stmtKirim = sqlsrv_query($conn, $sqlKirim, array($no_sc));
    if ($stmtKirim === false) {
        die(print_r(sqlsrv_errors(), true));
    }
    while ($row = sqlsrv_fetch_array($stmtKirim, SQLSRV_FETCH_ASSOC)) {
        $dataDelivery[] = $row;
        $summary['kirim_qty'] += $row['nQty'];
    }

    // 6. RETUR (Join tbRtSrj & tbRtSrjDtl)
    $sqlRetur = "SELECT H.cNomer AS cNoRetur, H.dTgl, H.cKeterangan, D.nQty 
                 FROM tbRtSrjDtl D
                 INNER JOIN tbRtSrj H ON D.cNomer = H.cNomer
                 WHERE D.cNoSc = ? AND H.lVoid = 0
                 ORDER BY H.dTgl ASC";
    $stmtRetur = sqlsrv_query($conn, $sqlRetur, array($no_sc));
    if ($stmtRetur === false) {
        die(print_r(sqlsrv_errors(), true));
    }
    while ($row = sqlsrv_fetch_array($stmtRetur, SQLSRV_FETCH_ASSOC)) {
        $dataRetur[] = $row;
        $summary['retur_qty'] += $row['nQty'];
    }

    // 7. PROSES MESIN (tbRealOP2 & Dtl)
    $sqlMesin = "SELECT DISTINCT H.dTanggal, D.cMesin, D.nQty, D.cJamMulai, D.cJamSelesai 
                 FROM tbRealOP2Dtl D
                 INNER JOIN tbRealOP2 H ON D.cNoOp = H.cNoOp
                 WHERE D.cNoSC = ? 
                 ORDER BY H.dTanggal ASC, D.cJamMulai ASC";
    $stmtMesin = sqlsrv_query($conn, $sqlMesin, array($no_sc));
    if ($stmtMesin === false) {
        die(print_r(sqlsrv_errors(), true));
    }
    while ($row = sqlsrv_fetch_array($stmtMesin, SQLSRV_FETCH_ASSOC)) {
        $dataMesin[] = $row;
    }
}
?>