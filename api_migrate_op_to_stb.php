<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') exit(0);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

// Ambil input dari frontend
$cNoSTB = $_POST['cNoSTB'] ?? '';
$cNoOp = $_POST['cNoOp'] ?? '';

if (!$cNoSTB || !$cNoOp) {
    echo json_encode(['success' => false, 'message' => 'cNoSTB dan cNoOp wajib diisi.']);
    exit;
}

try {
    $serverName = "spsdmz";
    $connectionOptions = [
        "Database" => "dbSopanusa",
        "Uid" => "sa",
        "PWD" => "supracor",
        "LoginTimeout" => 15,
        "Encrypt" => false,
        "TrustServerCertificate" => true
    ];
    $conn = sqlsrv_connect($serverName, $connectionOptions);

    if (!$conn) throw new Exception("DB connection failed.");

    // 1. Ambil data dari tbOP
    $sqlOp = "SELECT 
        cnm_c, cNoMc, cNoOp, cnm_brg, nPanjang, nLebar, nTinggi, nQtyStok, nQty, dTgl, clengkap1, clengkap2, dTglkirim, cpr_ikat, clengkap3, clengkap4,
        ckd_b1, ckd_b2, ckd_b3, ckd_b4, ckd_b5, cJnsGel, cFlexo, cNoSc, cNama, nTot_netto
        FROM tbOP WHERE cNoOp = ?";
    $stmtOp = sqlsrv_query($conn, $sqlOp, [$cNoOp]);
    if (!$stmtOp || !($op = sqlsrv_fetch_array($stmtOp, SQLSRV_FETCH_ASSOC))) {
        echo json_encode(['success' => false, 'message' => 'Data OP tidak ditemukan.']);
        exit;
    }

    // 2. Hitung nBerat = nQty * nTot_netto
    $nQty = floatval($op['nQty'] ?? 0);
    $nTot_netto = floatval($op['nTot_netto'] ?? 0);
    $nBerat = $nQty * $nTot_netto;

    // 3. Insert ke tbStbBJ
    $sqlInsert = "INSERT INTO tbStbBJ (
        cNoSTB, cNoOp, cKd_C, cNamabrg, cNoSc, cNama, nPanjang, nLebar, nTinggi, nBerat, dTanggalStb, nQty
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, GETDATE(), ?)";

    $paramsInsert = [
        $cNoSTB,
        $op['cNoOp'],
        $op['cnm_c'],      // cKd_C
        $op['cnm_brg'],    // cNamabrg
        $op['cNoSc'],
        $op['cNama'],
        floatval($op['nPanjang']),
        floatval($op['nLebar']),
        floatval($op['nTinggi']),
        $nBerat,
        $nQty
    ];

    $stmtInsert = sqlsrv_query($conn, $sqlInsert, $paramsInsert);

    if ($stmtInsert) {
        echo json_encode(['success' => true, 'message' => 'Migrasi data berhasil.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Gagal insert ke tbStbBJ.']);
    }

    if ($stmtOp) sqlsrv_free_stmt($stmtOp);
    if ($stmtInsert) sqlsrv_free_stmt($stmtInsert);
    sqlsrv_close($conn);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}