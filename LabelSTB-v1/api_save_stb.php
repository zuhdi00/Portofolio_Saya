<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

$input = json_decode(file_get_contents('php://input'), true);

$cNoSTB = $input['cNoSTB'] ?? '';
$cNoOp = $input['cNoOp'] ?? '';
$cNama = $input['cNama'] ?? '';
$cNamabrg = $input['cNamabrg'] ?? '';
$cNoMC = $input['cNoMC'] ?? '';
$nPanjang = $input['nPanjang'] ?? 0;
$nLebar = $input['nLebar'] ?? 0;
$nTinggi = $input['nTinggi'] ?? 0;
$nQty = $input['nQty'] ?? 0;
$dTglkirim = $input['dTglkirim'] ?? '';
$nQtyKg = $input['nQtyKg'] ?? 0;
$cNoSc = $input['cNoSc'] ?? '';
$dTanggal = $input['dTanggal'] ?? '';
$cType = $input['cType'] ?? '';
$nBerat = $input['nBerat'] ?? 0;
$cWarna = $input['cWarna'] ?? '';
$cJnsGel = $input['cJnsGel'] ?? '';
$cSub1 = $input['cSub1'] ?? '';
$cSub2 = $input['cSub2'] ?? '';
$cSub3 = $input['cSub3'] ?? '';
$cSub4 = $input['cSub4'] ?? '';
$cSub5 = $input['cSub5'] ?? '';
$cKodeBatch = $input['cKodeBatch'] ?? '';

if (!$cNoSTB || !$cNoOp) {
    echo json_encode(['success' => false, 'message' => 'No STB dan No OP wajib diisi']);
    exit;
}
function toSqlDate($str) {
    if (preg_match('/^\d{4}-\d{2}-\d{2}/', $str)) return $str;
    if (preg_match('/^(\d{2})-(\d{2})-(\d{4})$/', $str, $m)) {
        return $m[3] . '-' . $m[2] . '-' . $m[1];
    }
    if (preg_match('/^(\d{1,2}) (\w+) (\d{4})$/', $str, $m)) {
        $bulan = [
            'Januari'=> '01', 'Februari'=> '02', 'Maret'=> '03', 'April'=> '04', 'Mei'=> '05', 'Juni'=> '06',
            'Juli'=> '07', 'Agustus'=> '08', 'September'=> '09', 'Oktober'=> '10', 'November'=> '11', 'Desember'=> '12'
        ];
        $mm = $bulan[$m[2]] ?? '01';
        return $m[3] . '-' . $mm . '-' . str_pad($m[1], 2, '0', STR_PAD_LEFT);
    }
    return date('Y-m-d'); 
}

$dTglkirim = toSqlDate($dTglkirim, $dTanggal);

try {
    $serverName = "spsdmz2";
    $connectionOptions = [
        "Database" => "dbSopanusa",
        "Uid" => "sa",
        "PWD" => "supracor",
        "LoginTimeout" => 15,
        "Encrypt" => false,
        "TrustServerCertificate" => true
    ];
    $conn = sqlsrv_connect($serverName, $connectionOptions);

    if (!$conn) throw new Exception("Connection failed");

    // Cek apakah STB sudah ada
    $sqlCheck = "SELECT COUNT(*) AS cnt FROM tbStbBJ WHERE cNoSTB = ?";
    $stmtCheck = sqlsrv_query($conn, $sqlCheck, [$cNoSTB]);
    if ($stmtCheck === false) {
        throw new Exception("Check STB failed: " . print_r(sqlsrv_errors(), true));
    }
    $rowCheck = sqlsrv_fetch_array($stmtCheck, SQLSRV_FETCH_ASSOC);
    if ($rowCheck && $rowCheck['cnt'] > 0) {
        // Sudah ada, generate nomor STB baru
        // Ambil prefix STB (misal: STB-250901)
        $prefix = substr($cNoSTB, 0, 9); // Sesuaikan dengan format STB Anda
        $sqlLast = "SELECT MAX(CAST(RIGHT(cNoSTB, 4) AS INT)) AS last_number FROM tbStbBJ WHERE cNoSTB LIKE ?";
        $stmtLast = sqlsrv_query($conn, $sqlLast, [$prefix . '%']);
        $lastNum = 0;
        if ($stmtLast && ($rowLast = sqlsrv_fetch_array($stmtLast, SQLSRV_FETCH_ASSOC))) {
            $lastNum = intval($rowLast['last_number']);
        }
        $newNum = $lastNum + 1;
        $cNoSTB = $prefix . str_pad($newNum, 4, '0', STR_PAD_LEFT);

        // Kembalikan ke frontend untuk retry
        echo json_encode([
            'success' => false,
            'message' => 'STB sudah ada, dibuat nomor baru: ' . $cNoSTB,
            'new_stb' => $cNoSTB
        ]);
        exit;
    }

    $sql = "INSERT INTO tbStbBJ (
        cNoSTB, dTanggal, cNoSc, cNoOp, cNama, cNamabrg, cNoMC, nPanjang, nLebar, nTinggi, nQty, dTglkirim, lPosted, cNoid, lvoid, nQtyKg, cCom,
        cType, nBerat, cWarna, cJnsGel, cSub1, cSub2, cSub3, cSub4, cSub5, cKodeBatch
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, '001', 0, ?, 'T', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

    $params = [
        $cNoSTB, $dTanggal, $cNoSc, $cNoOp, $cNama, $cNamabrg, $cNoMC, $nPanjang, $nLebar,
        $nTinggi, $nQty, $dTglkirim, $nQtyKg, $cType, $nBerat, $cWarna, $cJnsGel, $cSub1,
        $cSub2, $cSub3, $cSub4, $cSub5, $cKodeBatch
    ];

    $stmt = sqlsrv_query($conn, $sql, $params);

    if ($stmt === false) {
        $errors = sqlsrv_errors();
        throw new Exception("Insert failed: " . print_r($errors, true));
    }

    echo json_encode(['success' => true, 'message' => 'STB berhasil disimpan']);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}