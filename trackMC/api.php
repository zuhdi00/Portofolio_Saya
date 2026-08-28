<?php
// Set header untuk JSON dan CORS
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *'); // Izinkan akses dari domain manapun
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// ... (Handle OPTIONS request, Koneksi Database - tidak ada perubahan) ...
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

$request_uri = $_SERVER['PATH_INFO'] ?? '/';

// ... (Fungsi executeQuery - tidak ada perubahan) ...
function executeQuery($conn, $sql, $params = []) {
    if (empty($params)) {
        $stmt = sqlsrv_query($conn, $sql);
    } else {
        $stmt = sqlsrv_query($conn, $sql, $params);
    }

    if ($stmt === false) {
        $errors = sqlsrv_errors();
        $error_messages = [];
        foreach ($errors as $error) {
            $error_messages[] = "SQLSTATE: ".$error['SQLSTATE'].", Code: ".$error['code'].", Message: ".$error['message'];
        }
        return ['success' => false, 'message' => 'Gagal mengeksekusi query.', 'errors' => implode("; ", $error_messages)];
    }

    $data = [];
    while (($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) !== null) {
        $encoded_row = [];
        foreach ($row as $key => $value) {
            if (is_string($value)) {
                $encoded_row[$key] = mb_convert_encoding($value, 'UTF-8', 'ISO-8859-1');
            } else {
                $encoded_row[$key] = $value;
            }
        }
        $data[] = $encoded_row;
    }
    sqlsrv_free_stmt($stmt);
    return ['success' => true, 'data' => $data];
}

// Routing sederhana
switch ($request_uri) {
    case '/':
        echo json_encode(['message' => 'Selamat datang di Sopanusa API!']);
        break;

    case '/api/searchMC':
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Hanya method GET yang diizinkan.']);
            break;
        }

        $noMc = $_GET['cNoMC'] ?? null;
        if (!$noMc) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Parameter cNoMC dibutuhkan.']);
            break;
        }

        $sql = "
            WITH GroupedData AS (
                SELECT 
                    T1.cNoMC, 
                    T1.cNoOp, 
                    T1.cNoSTB, 
                    T1.cNoSc, 
                    T2.cNoSRJ,
                    T1.cNama, 
                    T1.nQty AS Qty_STB, 
                    SUM(ISNULL(T3.nQty, 0)) AS Qty_SRJDtl 
                FROM 
                    dbo.tbStbBJ AS T1
                LEFT JOIN 
                    dbo.tbSRJ AS T2 ON T1.cNoSc = T2.cNoSc
                LEFT JOIN
                    dbo.tbSRJDtl AS T3 ON T2.cNoSRJ = T3.cNoSRJ 
                WHERE 
                    T1.cNoMC LIKE ?
                GROUP BY 
                    T1.cNoMC, 
                    T1.cNoOp, 
                    T1.cNoSTB, 
                    T1.cNoSc, 
                    T2.cNoSRJ,
                    T1.cNama, 
                    T1.nQty
            )
            SELECT 
                *,
                ROW_NUMBER() OVER (ORDER BY cNoMC) AS RowNum
            FROM 
                GroupedData;
        ";
        // --- ▲▲▲ BATAS AKHIR PERUBAHAN SQL ---
        
        $params = ["%" . $noMc . "%"];

        $result = executeQuery($conn, $sql, $params);

        if ($result['success']) {
            http_response_code(200);
            echo json_encode($result);
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

