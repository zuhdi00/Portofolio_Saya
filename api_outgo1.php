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

    case '/api/get-stb':
        $sql = 'SELECT * FROM dbo.tbStbBJ';
        $params = [];

        // Cek apakah parameter cNoSTB ada di URL
        if (!empty($_GET['cNoSTB'])) { //<nav></nav>
            // Jika ada, tambahkan kondisi WHERE untuk memfilter
            $sql .= ' WHERE cNoSTB = ?';
            $params[] = $_GET['cNoSTB'];
        }

        $result = executeQuery($conn, $sql, $params);

        if ($result['success']) {
            echo json_encode($result); // Kembalikan seluruh objek hasil, termasuk 'data'
        } else {
            http_response_code(500);
            echo json_encode($result);
        }
        break;

    case '/api/put-stb':
        // Pastikan cNoSTB disediakan
        if (empty($_GET['cNoSTB'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Parameter cNoSTB tidak boleh kosong.']);
            break;
        }

        $cNoSTB = $_GET['cNoSTB'];

        $sql = "UPDATE dbo.tbStbBJ SET cOutSTB = '1', dTanggalOut = GETDATE() WHERE cNoSTB = ?";
        $params = [$cNoSTB];

        $stmt = sqlsrv_query($conn, $sql, $params);

        if ($stmt) {
            $rows_affected = sqlsrv_rows_affected($stmt);
            if ($rows_affected > 0) {
                echo json_encode(['success' => true, 'message' => 'Data STB berhasil diupdate.']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Data STB tidak ditemukan atau tidak ada perubahan.']);
            }
        } else {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Gagal mengeksekusi query update.', 'errors' => sqlsrv_errors()]);
        }
        break;

    default:
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Endpoint tidak ditemukan.']);
        break;
}

sqlsrv_close($conn);

?>
