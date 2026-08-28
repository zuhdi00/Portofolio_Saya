<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

$serverName = "spsdmz";
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
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Koneksi database gagal.']);
    exit;
}

$request_uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$request_uri = str_replace('/' . basename(__FILE__), '', $request_uri);

function executeQuery($conn, $sql, $params = []) {
    $stmt = sqlsrv_query($conn, $sql, $params);
    if ($stmt === false) {
        return ['success' => false, 'message' => 'Gagal mengeksekusi query.', 'errors' => sqlsrv_errors()];
    }

    $data = [];
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        if ($row === false) {
             return ['success' => false, 'message' => 'Gagal mengambil data dari baris.', 'errors' => sqlsrv_errors()];
        }
        // Konversi setiap nilai string ke UTF-8 untuk mencegah error pada json_encode
        $encoded_row = [];
        foreach ($row as $key => $value) {
            if (is_string($value)) {
                $encoded_row[$key] = mb_convert_encoding($value, 'UTF-8', 'auto');
            } else {
                $encoded_row[$key] = $value;
            }
        }
        $data[] = $encoded_row;
    }
    
    sqlsrv_free_stmt($stmt);
    return ['success' => true, 'data' => $data];
}

switch ($request_uri) {
    case '/':
        echo json_encode(['message' => 'Selamat datang di Sopanusa API!']);
        break;

    case '/api/bpb-detail':
        $result = executeQuery($conn, 'SELECT * FROM dbo.tbBPBDtl');
        if ($result['success']) {
            echo json_encode($result['data']);
        } else {
            http_response_code(500);
            echo json_encode($result);
        }
        break;

    case '/api/masterBahan':
        $result = executeQuery($conn, 'SELECT cKode, cSatBsr, cNama FROM dbo.tbBahan');
        if ($result['success']) {
            if (empty($result['data'])) {
                http_response_code(404);
                echo json_encode(['success' => false, 'message' => 'Tabel dbo.tbBahan tidak memiliki data.']);
            } else {
                echo json_encode($result['data']);
            }
        } else {
            http_response_code(500);
            echo json_encode($result);
        }
        break;

    case '/api/master-bahan/search':
        $kode = $_GET['kode'] ?? null;
        if (!$kode) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Parameter "kode" tidak ditemukan.']);
            break;
        }
        $sql = 'SELECT cKode, cNama, cSatBsr FROM dbo.tbBahan WHERE cKode = ?';
        $params = [$kode];
        $result = executeQuery($conn, $sql, $params);
        if ($result['success']) {
            if (empty($result['data'])) {
                http_response_code(404);
                echo json_encode(['success' => false, 'message' => 'Bahan tidak ditemukan.']);
            } else {
                echo json_encode($result['data'][0]);
            }
        } else {
            http_response_code(500);
            echo json_encode($result);
        }
        break;
}

sqlsrv_close($conn);

?>
