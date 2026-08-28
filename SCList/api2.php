<?php
// Set header untuk JSON dan CORS
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *'); // Izinkan akses dari domain manapun
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle OPTIONS request (pre-flight)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// --- KONEKSI DATABASE ---
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
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Koneksi database gagal.']);
    exit;
}

// --- FUNGSI EKSEKUSI QUERY ---
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
        // Gabungkan pesan error spesifik untuk debugging yang lebih mudah di frontend.
        $detailed_error = implode("; ", $error_messages);
        $message = 'Gagal mengeksekusi query.';
        if (!empty($detailed_error)) $message .= ' Detail: ' . $detailed_error;
        return ['success' => false, 'message' => $message, 'errors' => $detailed_error];
    }

    $data = [];
    while (($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) !== null) {
        $encoded_row = [];
        foreach ($row as $key => $value) {
            // Handle encoding
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

// --- ROUTING ---
// Ambil 'action' dari query string untuk routing. Lebih kompatibel daripada PATH_INFO.
$action = $_GET['action'] ?? 'home';

switch ($action) {
    case 'home':
        echo json_encode(['message' => 'Selamat datang di Sopanusa API!']);
        break;
        
    // --- ENDPOINT BARU UNTUK SALES CONTRACT ---
    case 'searchSC':
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Hanya method GET yang diizinkan.']);
            break;
        }

        // Ambil parameter filter dari query string
        $startDate = $_GET['startDate'] ?? null;
        $endDate = $_GET['endDate'] ?? null;
        $scNumber = $_GET['searchSC'] ?? null;

        $params = [];
        $filterClause = "";

        // Cek jika ada parameter filter yang diberikan
        if ($startDate || $endDate || $scNumber) {
            if ($startDate) {
                $filterClause .= " AND T1.dTanggal >= ?";
                $params[] = $startDate;
            }
            if ($endDate) {
                $filterClause .= " AND T1.dTanggal <= ?";
                $params[] = $endDate;
            }
            if ($scNumber) {
                $filterClause .= " AND T1.cNoSC LIKE ?";
                $params[] = "%" . $scNumber . "%";
            }
            // Gandakan parameter untuk klausa UNION
            $params = array_merge($params, $params);
        }

        $limit = $startDate || $endDate || $scNumber ? "" : "TOP 50";

        // Menggunakan CTE (Common Table Expression) untuk menggabungkan hasil sebelum mengurutkan dan membatasi
        $sql = "
            WITH OPAggregated AS (
                -- CTE ini menghilangkan duplikasi dari tbOP dengan mengambil hanya satu MC dan OP per SC/Nama Barang
                SELECT 
                    cNoSc, 
                    cnm_brg, 
                    MIN(cNoMc) AS cNoMc, 
                    MIN(cNoOp) AS cNoOp
                FROM dbo.tbOP
                GROUP BY cNoSc, cnm_brg
            ),
            CombinedResults AS (
                -- Query untuk data induk dari tbSC
                SELECT
                    T1.cNoSC, T1.dTanggal, T1.dTglKirim, T1.UserDate, T1.cNama AS cCustomer, T1.cJenis, T1.cKodeTipe, T1.cJnsGel,
                    T1.nPanjang, T1.nLebar, T1.nTinggi, T1.nQty, T1.cWarna, T1.cKeterangan,
                    T1.cSub1, T1.cSub2, T1.cSub3, T1.cSub4, T1.cSub5,
                    T2.cNoMc,
                    ISNULL(T2.cNoOp, 'BELUM') AS cNoOp,
                    'parent' AS dataSource
                FROM dbo.tbSC AS T1
                LEFT JOIN OPAggregated AS T2 ON T2.cNoSc = (
                    CASE
                        WHEN (T1.cKeterangan LIKE 'OU %' OR T1.cKeterangan LIKE 'OUP %' OR T1.cKeterangan LIKE 'OB %')
                             AND CHARINDEX(' ', T1.cKeterangan, CHARINDEX(' ', T1.cKeterangan) + 1) > CHARINDEX(' ', T1.cKeterangan) + 1
                        THEN SUBSTRING(T1.cKeterangan, CHARINDEX(' ', T1.cKeterangan) + 1, CHARINDEX(' ', T1.cKeterangan, CHARINDEX(' ', T1.cKeterangan) + 1) - CHARINDEX(' ', T1.cKeterangan) - 1)
                        ELSE T1.cNoSC
                    END
                ) AND T2.cnm_brg = T1.cJenis
                WHERE 1=1 $filterClause

                UNION ALL

                -- Query untuk data detail dari tbSCDtl
                SELECT
                    T1.cNoSC, T1.dTanggal, T1.dTglKirim, T1.UserDate, T1.cNama AS cCustomer, T_DTL.cNama AS cJenis, T1.cKodeTipe, T_DTL.cJnsGelDtl AS cJnsGel,
                    T1.nPanjang, T1.nLebar, T1.nTinggi, T_DTL.nQty, T1.cWarna, T1.cKeterangan,
                    T1.cSub1, T1.cSub2, T1.cSub3, T1.cSub4, T1.cSub5,
                    T2.cNoMc,
                    ISNULL(T2.cNoOp, 'BELUM') AS cNoOp,
                    'detail' AS dataSource
                FROM dbo.tbSC AS T1
                INNER JOIN dbo.tbSCDtl AS T_DTL ON T1.cNoSC = T_DTL.cNoSC
                LEFT JOIN OPAggregated AS T2 ON T2.cNoSc = (
                    CASE
                        WHEN (T1.cKeterangan LIKE 'OU %' OR T1.cKeterangan LIKE 'OUP %' OR T1.cKeterangan LIKE 'OB %')
                             AND CHARINDEX(' ', T1.cKeterangan, CHARINDEX(' ', T1.cKeterangan) + 1) > CHARINDEX(' ', T1.cKeterangan) + 1
                        THEN SUBSTRING(T1.cKeterangan, CHARINDEX(' ', T1.cKeterangan) + 1, CHARINDEX(' ', T1.cKeterangan, CHARINDEX(' ', T1.cKeterangan) + 1) - CHARINDEX(' ', T1.cKeterangan) - 1)
                        ELSE T1.cNoSC
                    END
                ) AND T2.cnm_brg = T_DTL.cNama -- Join ke OP menggunakan nama item dari detail
                WHERE 1=1 $filterClause
            )
            SELECT $limit * FROM CombinedResults
            ORDER BY dTanggal DESC, cNoSC DESC, dataSource ASC
        ";

        // Eksekusi query
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