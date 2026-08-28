<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

// Debug log
error_log('api_upload_to_wipv2.php called - POST: ' . json_encode($_POST));

// Get POST data dengan trim untuk safety
$cNoOp = trim($_POST['cNoOp'] ?? '');
$cNoSc = trim($_POST['cNoSc'] ?? '');
$dTgl = trim($_POST['dTgl'] ?? '');
$dTglkirim = trim($_POST['dTglkirim'] ?? '');
$nQty = floatval($_POST['nQty'] ?? 0);
$nQty_corr = floatval($_POST['nQty_corr'] ?? 0);
$nRm = floatval($_POST['nRm'] ?? 0);
$cMengetahui = trim($_POST['cMengetahui'] ?? '');
$cNoMc = trim($_POST['cNoMc'] ?? '');
$cnm_c = trim($_POST['cnm_c'] ?? '');
$cnm_brg = trim($_POST['cnm_brg'] ?? '');
$cLayer = trim($_POST['cLayer'] ?? '');
$cTipe = trim($_POST['cTipe'] ?? '');
$nTot_netto = floatval($_POST['nTot_netto'] ?? 0);
$nPanjang = floatval($_POST['nPanjang'] ?? 0);
$nLebar = floatval($_POST['nLebar'] ?? 0);
$nTinggi = floatval($_POST['nTinggi'] ?? 0);
$cWarna = trim($_POST['cWarna'] ?? '');
$cRak = trim($_POST['cRak'] ?? '');
$cKeterangan = trim($_POST['cKeterangan'] ?? '');
$UserId = trim($_POST['UserId'] ?? 'MOBILE_USER');
$ComputerName = trim($_POST['ComputerName'] ?? 'MOBILE_APP');

// Validate required fields
if (!$cNoOp) {
    echo json_encode(['success' => false, 'message' => 'cNoOp is required']);
    error_log('api_upload_to_wipv2.php: cNoOp missing');
    exit;
}

try {
    // Connect to SQL Server
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
        throw new Exception("Connection failed: " . json_encode($errors));
    }

    // Get current date and time
    $now = new DateTime();
    $dTanggal = $now->format('Y-m-d');
    $currentTime = $now->format('Y-m-d H:i:s');
    
    // Generate unique cNoSTB
    $cNoSTB = $cNoOp . '_' . date('Ymd_His');

    // Insert into tbWIPV2 - fixed column list
    $sql = "INSERT INTO tbWIPV2 (
                cNoSTB, cNoOp, cCom, dTanggal, cNoSc, cNamabrg, cNoMC, cKodeCust, cNama,
                nPanjang, nLebar, nTinggi, cWarna, cJnsGel, cType, cSub1, cSub2, cSub3, cSub4, cSub5,
                nQtyPalet, nQtyCol, nIsi, nQtyLbr, nQtyKgFisik, nQtyKgFormula, dTglSerah, lPosted, lVoid, lClose,
                lBeli, dTglBtlPost, dTglVoid, cAlasanBatal, cAlasanVoid, UserId, UserDate, ComputerName, cUserComp,
                AppName, cMengetahui, cSetujui, cDepUser, cDepSetujui, cDepMengetahui, nPrinted, nRevisi, cJns,
                cNoid, nberat, nOrder, cKdSales, cNamaSales, nQty, nQtyKg, nPrint, lProd, cKeterangan, cNamaComp,
                cNoOpLast, cDep, cRak, cShift, dTglkirim, nQtyOut, cOutSTB, dTanggalOut
            )
            VALUES (
                ?, ?, ?, ?, ?, ?, ?, ?, ?,
                ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?,
                ?, ?, ?, ?, ?, ?, ?, ?, ?, ?,
                ?, ?, ?, ?, ?, ?, ?, ?, ?,
                ?, ?, ?, ?, ?, ?, ?, ?, ?,
                ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?,
                ?, ?, ?, ?, ?, ?, ?, ?
            )";

    $params = array(
        $cNoSTB,           // cNoSTB
        $cNoOp,            // cNoOp
        '',                // cCom
        $dTanggal,         // dTanggal
        $cNoSc,            // cNoSc
        $cnm_brg,          // cNamabrg
        $cNoMc,            // cNoMC
        '',                // cKodeCust
        $cnm_c,            // cNama
        $nPanjang,         // nPanjang
        $nLebar,           // nLebar
        $nTinggi,          // nTinggi
        $cWarna,           // cWarna
        '',                // cJnsGel
        $cTipe,            // cType
        '',                // cSub1
        '',                // cSub2
        '',                // cSub3
        '',                // cSub4
        '',                // cSub5
        0,                 // nQtyPalet
        0,                 // nQtyCol
        0,                 // nIsi
        0,                 // nQtyLbr
        $nRm,              // nQtyKgFisik
        0,                 // nQtyKgFormula
        $dTglkirim,        // dTglSerah
        0,                 // lPosted
        0,                 // lVoid
        0,                 // lClose
        0,                 // lBeli
        null,              // dTglBtlPost
        null,              // dTglVoid
        '',                // cAlasanBatal
        '',                // cAlasanVoid
        $UserId,           // UserId
        $currentTime,      // UserDate
        $ComputerName,     // ComputerName
        '',                // cUserComp
        'MOBILE_BARCODE',  // AppName
        $cMengetahui,      // cMengetahui
        '',                // cSetujui
        '',                // cDepUser
        '',                // cDepSetujui
        '',                // cDepMengetahui
        0,                 // nPrinted
        0,                 // nRevisi
        '',                // cJns
        '',                // cNoid
        0,                 // nberat
        0,                 // nOrder
        '',                // cKdSales
        '',                // cNamaSales
        $nQty,             // nQty
        $nQty_corr,        // nQtyKg
        0,                 // nPrint
        0,                 // lProd
        $cKeterangan,      // cKeterangan
        '',                // cNamaComp
        '',                // cNoOpLast
        '',                // cDep
        $cRak,             // cRak
        '',                // cShift
        $dTglkirim,        // dTglkirim
        0,                 // nQtyOut
        '',                // cOutSTB
        null               // dTanggalOut
    );

    $stmt = sqlsrv_query($conn, $sql, $params);

    if ($stmt === false) {
        $errors = sqlsrv_errors();
        throw new Exception("Insert failed: " . json_encode($errors));
    }

    // Get rows affected
    $rowsAffected = sqlsrv_rows_affected($stmt);

    if ($rowsAffected > 0) {
        error_log('api_upload_to_wipv2.php: Data inserted successfully - cNoSTB: ' . $cNoSTB);
        echo json_encode([
            'success' => true,
            'message' => 'Data berhasil tersimpan ke tbWIPV2',
            'cNoSTB' => $cNoSTB,
            'cNoOp' => $cNoOp,
            'rowsAffected' => $rowsAffected
        ]);
    } else {
        throw new Exception("Insert tidak berhasil - tidak ada rows yang ter-affect");
    }

    sqlsrv_free_stmt($stmt);
    sqlsrv_close($conn);

} catch (Exception $e) {
    $errorMsg = $e->getMessage();
    error_log('api_upload_to_wipv2.php Exception: ' . $errorMsg);
    echo json_encode(['success' => false, 'message' => $errorMsg]);
}
?>

    // Insert into tbWIPV2
    $sql = "INSERT INTO tbWIPV2 (
                cNoSTB, cNoOp, cCom, dTanggal, cNoSc, cNamabrg, cNoMC, cKodeCust, cNama,
                nPanjang, nLebar, nTinggi, cWarna, cJnsGel, cType, cSub1, cSub2, cSub3, cSub4, cSub5,
                nQtyPalet, nQtyCol, nIsi, nQtyLbr, nQtyKgFisik, nQtyKgFormula, dTglSerah, lPosted, lVoid, lClose,
                lBeli, dTglBtlPost, dTglVoid, cAlasanBatal, cAlasanVoid, UserId, UserDate, ComputerName, cUserComp,
                AppName, cMengetahui, cSetujui, cDepUser, cDepSetujui, cDepMengetahui, nPrinted, nRevisi, cJns,
                cNoid, nberat, nOrder, cKdSales, cNamaSales, nQty, nQtyKg, nPrint, lProd, cKeterangan, cNamaComp,
                cNoOpLast, cDep, cRak, cShift, dTglkirim, nQtyOut, cOutSTB, dTanggalOut
            )
            VALUES (
                ?, ?, ?, ?, ?, ?, ?, ?, ?,
                ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?,
                ?, ?, ?, ?, ?, ?, ?, ?, ?, ?,
                ?, ?, ?, ?, ?, ?, ?, ?, ?,
                ?, ?, ?, ?, ?, ?, ?, ?, ?,
                ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?,
                ?, ?, ?, ?, ?, ?, ?, ?
            )";

    $params = array(
        $cNoSTB,           // cNoSTB
        $cNoOp,            // cNoOp
        '',                // cCom
        $dTanggal,         // dTanggal
        $cNoSc,            // cNoSc
        $cnm_brg,          // cNamabrg
        $cNoMc,            // cNoMC
        '',                // cKodeCust
        $cnm_c,            // cNama
        $nPanjang,         // nPanjang
        $nLebar,           // nLebar
        $nTinggi,          // nTinggi
        $cWarna,           // cWarna
        '',                // cJnsGel
        $cTipe,            // cType
        '',                // cSub1
        '',                // cSub2
        '',                // cSub3
        '',                // cSub4
        '',                // cSub5
        0,                 // nQtyPalet
        0,                 // nQtyCol
        0,                 // nIsi
        0,                 // nQtyLbr
        $nRm,              // nQtyKgFisik
        0,                 // nQtyKgFormula
        $dTglkirim,        // dTglSerah
        0,                 // lPosted
        0,                 // lVoid
        0,                 // lClose
        0,                 // lBeli
        null,              // dTglBtlPost
        null,              // dTglVoid
        '',                // cAlasanBatal
        '',                // cAlasanVoid
        $currentUser,      // UserId
        $currentTime,      // UserDate
        $computerName,     // ComputerName
        '',                // cUserComp
        'MOBILE_BARCODE',  // AppName
        $cMengetahui,      // cMengetahui
        '',                // cSetujui
        '',                // cDepUser
        '',                // cDepSetujui
        '',                // cDepMengetahui
        0,                 // nPrinted
        0,                 // nRevisi
        '',                // cJns
        '',                // cNoid
        0,                 // nberat
        0,                 // nOrder
        '',                // cKdSales
        '',                // cNamaSales
        $nQty,             // nQty
        $nQty_corr,        // nQtyKg
        0,                 // nPrint
        0,                 // lProd
        $cKeterangan,      // cKeterangan
        '',                // cNamaComp
        '',                // cNoOpLast
        '',                // cDep
        $cRak,             // cRak
        '',                // cShift
        $dTglkirim,        // dTglkirim
        0,                 // nQtyOut
        '',                // cOutSTB
        null               // dTanggalOut
    );

    $stmt = sqlsrv_query($conn, $sql, $params);

    if ($stmt === false) {
        $errors = sqlsrv_errors();
        throw new Exception("Insert failed: " . print_r($errors, true));
    }

    // Get rows affected
    $rowsAffected = sqlsrv_rows_affected($stmt);

    if ($rowsAffected > 0) {
        echo json_encode([
            'success' => true,
            'message' => 'Data berhasil tersimpan ke tbWIPV2',
            'cNoSTB' => $cNoSTB,
            'cNoOp' => $cNoOp
        ]);
    } else {
        throw new Exception("Insert tidak berhasil");
    }

    sqlsrv_free_stmt($stmt);
    sqlsrv_close($conn);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
