<?php
// FILE: realisasi_data.php

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
if (!$conn) die(print_r(sqlsrv_errors(), true));

$no_sc = isset($_GET['sc']) ? trim($_GET['sc']) : '';

// Inisialisasi Data
$dataSC = null;
$dataCorr = [];
$dataHslCorr = [];
$dataStbBJ = [];
$dataDelivery = [];
$dataRetur = [];
$dataMesin = [];
$summary = ['plan'=>0, 'hasil'=>0, 'stb'=>0, 'kirim'=>0, 'retur'=>0];

if (!empty($no_sc)) {
    // ---------------------------------------------------------
    // 0. PERSIAPAN LOGIKA PENCARIAN (SC vs OP)
    // ---------------------------------------------------------
    // Mengatasi kasus SC = "SLC/..." tapi OP = "SPS/..."
    // Kita ambil "Body" nomornya saja (misal: 2602/00100)
    $sc_parts = explode('/', $no_sc);
    $sc_body = (count($sc_parts) >= 2) ? $sc_parts[1] . '/' . $sc_parts[2] : $no_sc;
    
    // Parameter untuk pencarian LIKE di cNoOp
    $op_search_pattern = '%' . $sc_body . '%'; 

    // ---------------------------------------------------------
    // 1. DATA HEADER (tbSC)
    // ---------------------------------------------------------
    $sqlSC = "SELECT TOP 1 * FROM tbSC WHERE cNoSC = ?";
    $stmtSC = sqlsrv_query($conn, $sqlSC, array($no_sc));
    if ($stmtSC && sqlsrv_has_rows($stmtSC)) {
        $dataSC = sqlsrv_fetch_array($stmtSC, SQLSRV_FETCH_ASSOC);
    }

    // ---------------------------------------------------------
    // 2. PLANNING CORRUGATING (tbCorr & tbCorrDtl)
    // ---------------------------------------------------------
    // Join Header & Detail untuk mendapatkan info lengkap
    $sqlCorr = "SELECT H.dTanggal, H.cKodeCorr, D.nQtyOrder, D.nBerat, D.cFlute, D.nL, D.nP
                FROM tbCorr H
                INNER JOIN tbCorrDtl D ON H.cNoCorr = D.cNoCorr
                WHERE H.cNoSc = ? AND H.lVoid = 0
                ORDER BY H.dTanggal ASC";
    $stmtCorr = sqlsrv_query($conn, $sqlCorr, array($no_sc));
    while ($row = sqlsrv_fetch_array($stmtCorr, SQLSRV_FETCH_ASSOC)) {
        $dataCorr[] = $row;
        $summary['plan'] += $row['nQtyOrder'];
    }

    // ---------------------------------------------------------
    // 3. HASIL CORRUGATING (tbHslCorr & tbHslCorrDtl)
    // ---------------------------------------------------------
    $sqlHsl = "SELECT H.dTanggal, H.cKodeCorr, H.nJmlMeter, H.nBrgKg, H.nRealKertas
               FROM tbHslCorr H
               WHERE H.cNoSc = ? AND H.lVoid = 0
               ORDER BY H.dTanggal ASC";
    // Catatan: Jika butuh detail breakdown per pallet, join ke tbHslCorrDtl
    $stmtHsl = sqlsrv_query($conn, $sqlHsl, array($no_sc));
    while ($row = sqlsrv_fetch_array($stmtHsl, SQLSRV_FETCH_ASSOC)) {
        $dataHslCorr[] = $row;
        $summary['hasil'] += $row['nBrgKg']; // Summary Berat
    }

    // ---------------------------------------------------------
    // 4. SERAH TERIMA BARANG JADI (tbStbBJ)
    // ---------------------------------------------------------
    $sqlStb = "SELECT dTanggal, cNoStb, nQty, cKeterangan 
               FROM tbStbBJ 
               WHERE cNoSc = ? AND lVoid = 0 
               ORDER BY dTanggal ASC";
    $stmtStb = sqlsrv_query($conn, $sqlStb, array($no_sc));
    while ($row = sqlsrv_fetch_array($stmtStb, SQLSRV_FETCH_ASSOC)) {
        $dataStbBJ[] = $row;
        $summary['stb'] += $row['nQty'];
    }

    // ---------------------------------------------------------
    // 5. DATA PENGIRIMAN (tbSRJ & tbSRJDtl)
    // ---------------------------------------------------------
    $sqlKirim = "SELECT H.cNoSrj, H.dTanggal, H.cNoKend, H.cSupir, D.nQty, D.nBerat 
                 FROM tbSRJDtl D
                 INNER JOIN tbSRJ H ON D.cNoSrj = H.cNoSrj 
                 WHERE D.cNoSc = ? AND H.lVoid = 0 
                 ORDER BY H.dTanggal ASC";
    $stmtKirim = sqlsrv_query($conn, $sqlKirim, array($no_sc));
    while ($row = sqlsrv_fetch_array($stmtKirim, SQLSRV_FETCH_ASSOC)) {
        $dataDelivery[] = $row;
        $summary['kirim'] += $row['nQty'];
    }

    // ---------------------------------------------------------
    // 6. RETUR (tbRtSrj & tbRtSrjDtl)
    // ---------------------------------------------------------
    $sqlRetur = "SELECT H.cNomer AS cNoRetur, H.dTgl, H.cKeterangan, D.nQty 
                 FROM tbRtSrjDtl D
                 INNER JOIN tbRtSrj H ON D.cNomer = H.cNomer
                 WHERE D.cNoSc = ? AND H.lVoid = 0
                 ORDER BY H.dTgl ASC";
    $stmtRetur = sqlsrv_query($conn, $sqlRetur, array($no_sc));
    while ($row = sqlsrv_fetch_array($stmtRetur, SQLSRV_FETCH_ASSOC)) {
        $dataRetur[] = $row;
        $summary['retur'] += $row['nQty'];
    }

    // ---------------------------------------------------------
    // 7. PROSES MESIN (tbRealOP2 & tbRealOP2Dtl)
    // ---------------------------------------------------------
    // LOGIKA KUNCI: Join menggunakan cNoSC jika ada, atau LIKE match cNoOp
    // Kita mencari detail (D) dimana cNoSC cocok, ATAU cNoOp mengandung string body SC
    $sqlMesin = "SELECT H.dTanggal, D.cMesin, D.cNoOp, D.nQty, D.cJamMulai, D.cJamSelesai, D.cKeterangan
                 FROM tbRealOP2Dtl D
                 INNER JOIN tbRealOP2 H ON D.cNoOp = H.cNoOp
                 WHERE (D.cNoSC = ? OR D.cNoOp LIKE ?)
                 ORDER BY H.dTanggal, D.cJamMulai ASC";
                 
    $stmtMesin = sqlsrv_query($conn, $sqlMesin, array($no_sc, $op_search_pattern));
    while ($row = sqlsrv_fetch_array($stmtMesin, SQLSRV_FETCH_ASSOC)) {
        $dataMesin[] = $row;
    }
}
?>