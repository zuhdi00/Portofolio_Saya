<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *'); 
header('Access-Control-Allow-Methods: POST, OPTIONS'); 
header('Access-Control-Allow-Headers: Content-Type'); 

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit(0);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method. Only POST is allowed.']);
    exit;
}

$cNoSTB = $_POST['cNoSTB'] ?? null;
$cNamabrg = $_POST['cNamabrg'] ?? null;
$cNoSc = $_POST['cNoSc'] ?? null;
$cNama = $_POST['cNama'] ?? null;
$nPanjang = $_POST['nPanjang'] ?? null;
$nLebar = $_POST['nLebar'] ?? null;
$nTinggi = $_POST['nTinggi'] ?? null;
$nTot_netto = $_POST['nberat'] ?? null; 

if (empty($cNoStb) || empty($cNamabrg) || empty($cNoSc) || empty($cNama)) {
    echo json_encode(['success' => false, 'message' => 'Missing required text fields.']);
    exit;
}

if (!is_numeric($nPanjang) || !is_numeric($nLebar) || !is_numeric($nTinggi) || !is_numeric($nBeratInput)) {
    echo json_encode(['success' => false, 'message' => 'Numeric fields (Panjang, Lebar, Tinggi, Berat) must be valid numbers.']);
    exit;
}

try {
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
        error_log("SQLSRV Connection Failed: " . print_r(sqlsrv_errors(), true));
        echo json_encode(['success' => false, 'message' => 'Database connection failed. Please try again later.']);
        exit;
    }

    $sql = "INSERT INTO tbStbBJ (
                cNoSTB, cNamabrg, cNoSc, cNama, 
                nPanjang, nLebar, nTinggi, nBerat, 
                dTanggalStb, nQty 
            ) VALUES (
                ?, ?, ?, ?, 
                ?, ?, ?, ?,
                GETDATE(), 0 
            )";

    $params = array(
        $cNoStb,
        $cNamabrg,
        $cNoSc,
        $cNama,
        (float)$nPanjang,
        (float)$nLebar,
        (float)$nTinggi,
        (float)$nBeratInput 
    );

    $stmt = sqlsrv_query($conn, $sql, $params);

    if ($stmt) {
        $rowsAffected = sqlsrv_rows_affected($stmt);
        if ($rowsAffected > 0) {
            $sqlGetNewStb = "SELECT 
                                cNoSTB, cNamabrg, cNoSc, cNama, 
                                nPanjang, nLebar, nTinggi, 
                                nBerat, 
                                ISNULL(nQty, 0) as nQty, 
                                ISNULL(nQtyKg, 0) as nQtyKg,
                                dTanggalStb
                             FROM tbStbBJ 
                             WHERE cNoSTB = ?";
            
            $paramsGetNewStb = array($cNoStb);
            $stmtGetNewStb = sqlsrv_query($conn, $sqlGetNewStb, $paramsGetNewStb);

            if ($newStbData = sqlsrv_fetch_array($stmtGetNewStb, SQLSRV_FETCH_ASSOC)) {
                $newStbData['nPanjang'] = floatval($newStbData['nPanjang']);
                $newStbData['nLebar'] = floatval($newStbData['nLebar']);
                $newStbData['nTinggi'] = floatval($newStbData['nTinggi']);
                $newStbData['nBerat'] = floatval($newStbData['nBerat']); 
                $newStbData['nQty'] = floatval($newStbData['nQty']);
                $newStbData['nQtyKg'] = floatval($newStbData['nQtyKg']);
                if ($newStbData['dTanggalStb'] instanceof DateTime) {
                    $newStbData['dTanggalStb'] = $newStbData['dTanggalStb']->format('Y-m-d H:i:s');
                }


                if (isset($newStbData['nBerat'])) {
                    $newStbData['nTot_netto'] = $newStbData['nBerat'];
                }


                echo json_encode(['success' => true, 'message' => 'Data STB berhasil ditambahkan.', 'data' => $newStbData]);
            } else {

                error_log("SQLSRV Query Failed (getNewStb): " . print_r(sqlsrv_errors(), true) . " for cNoSTB: " . $cNoStb);
                echo json_encode(['success' => true, 'message' => 'Data STB berhasil ditambahkan, namun gagal mengambil detailnya kembali.']);
            }
            if ($stmtGetNewStb) sqlsrv_free_stmt($stmtGetNewStb);

        } else {
            echo json_encode(['success' => false, 'message' => 'Gagal menambahkan data STB, tidak ada baris yang terpengaruh. Mungkin cNoSTB sudah ada?']);
        }
    } else {
        // Log error server
        error_log("SQLSRV Query Failed (insertStb): " . print_r(sqlsrv_errors(), true));
        echo json_encode(['success' => false, 'message' => 'Gagal menjalankan query penambahan data. Periksa log server.']);
    }

    if ($stmt) sqlsrv_free_stmt($stmt);
    sqlsrv_close($conn);

} catch (Exception $e) {
    // Log error
    error_log("Exception: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Terjadi kesalahan pada server: ' . $e->getMessage()]);
}
?>
