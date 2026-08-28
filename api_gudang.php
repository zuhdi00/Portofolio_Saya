<?php
// Setel header untuk mengizinkan CORS dan mengembalikan konten dalam format JSON
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
        $encoded_row = [];
        foreach ($row as $key => $value) {
            if (is_string($value)) {
                $encoded_row[$key] = iconv('ISO-8859-1', 'UTF-8//IGNORE//TRANSLIT', $value);
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

    case '/api/bpb':
        $result = executeQuery($conn, 'SELECT * FROM dbo.tbBPB');
        if ($result['success']) {
            echo json_encode($result['data']);
        } else {
            http_response_code(500);
            echo json_encode($result);
        }
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

    case '/api/PemakaianPBB':
    case '/api/PemakaianPBT':
    case '/api/PemakaianPBS':
    case '/api/PemakaianPBG':
    case '/api/PemakaianPBR':
    case '/api/PemakaianPBE':
    case '/api/PemakaianPBC':
        $prefix = strtoupper(str_replace(['/api/Pemakaian', ''], '', $request_uri));
        $sql = "SELECT cNoPakai FROM dbo.tbPakaiAcc WHERE cNoPakai LIKE ? ORDER BY cNoPakai DESC";
        $params = ["{$prefix}-%"];
        $result = executeQuery($conn, $sql, $params);
        if ($result['success']) {
            echo json_encode($result['data']);
        } else {
            http_response_code(500);
            echo json_encode($result);
        }
        break;

    case '/api/master-bahan':
        $result = executeQuery($conn, 'SELECT cKode, cSatBsr, cNama, cNamaAlias1 FROM dbo.tbBahan');
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
        $sql = 'SELECT cKode, cNama, cSatBsr, cNamaAlias1 FROM dbo.tbBahan WHERE cKode = ?';
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

    case '/api/departemen':
        $result = executeQuery($conn, 'SELECT * FROM dbo.tbDept');
        if ($result['success']) {
            if (empty($result['data'])) {
                 http_response_code(404);
                echo json_encode(['success' => false, 'message' => 'Tabel dbo.tbDept tidak memiliki data.']);
            } else {
                echo json_encode($result['data']);
            }
        } else {
            http_response_code(500);
            echo json_encode($result);
        }
        break;

    case '/api/departemen-new':
        $result = executeQuery($conn, 'SELECT * FROM dbo.tbDeptNew');
        if ($result['success']) {
            if (empty($result['data'])) {
                 http_response_code(404);
                echo json_encode(['success' => false, 'message' => 'Tabel dbo.tbDeptNew tidak memiliki data.']);
            } else {
                echo json_encode($result['data']);
            }
        } else {
            http_response_code(500);
            echo json_encode($result);
        }
        break;

    case '/api/tempStock':
        $result = executeQuery($conn, 'SELECT * FROM dbo.tbNewTmpStock');
        if ($result['success']) {
            echo json_encode($result['data']);
        } else {
            http_response_code(500);
            echo json_encode($result);
        }
        break;

    default:
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Endpoint tidak ditemukan.']);
        break;
}

sqlsrv_close($conn);

?>
