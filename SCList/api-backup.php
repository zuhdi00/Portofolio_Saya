<?php
// Set header untuk JSON dan CORS
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *'); 
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

        $limit = $startDate || $endDate || $scNumber ? "" : "TOP 200";

        // Menggunakan CTE (Common Table Expression) untuk menggabungkan hasil
        $sql = "
            WITH OUMc AS (
                -- C TE untuk mengambil No. MC berdasarkan SC Lama(untuk OU/OUP)
                SELECT cNoSc, MIN(cNoMc) AS cNoMc
                FROM dbo.tbOP
                GROUP BY cNoSc
            ),
            SheetMc AS (
                -- CTE untuk mengambil No. MC berdasarkan Nama Barang (untuk SHEET)
                SELECT cnm_brg, MIN(cNoMc) AS cNoMc
                FROM dbo.tbOP
                GROUP BY cnm_brg
            ),
            OPOp AS (
                -- CTE untuk mengambil No. OP berdasarkan SC saat ini
                SELECT cNoSc, MIN(cNoOp) AS cNoOp
                FROM dbo.tbOP
                GROUP BY cNoSc
            ),
            CombinedResults AS (
                -- Query untuk data induk dari tbSC
                SELECT
                    RTRIM(T1.cJnsSc) AS cJnsSc, RTRIM(T1.cNoSC) AS cNoSC, T1.dTanggal, T1.dTglKirim, T1.UserDate, 
                    RTRIM(T1.cKodeTipe) AS cKodeTipe, RTRIM(T1.cJnsGel) AS cJnsGel, 
                    RTRIM(T1.cKeterangan) AS cKeterangan, RTRIM(T1.cKet_Mkt) AS cKet_Mkt, 
                    RTRIM(T1.cNama) AS cNama, RTRIM(T1.cSales) AS cSales, 
                    RTRIM(T1.cJenis) AS cJenis, T1.nQty, RTRIM(T1.cWarna) AS cWarna,
                    -- Logika untuk mengambil OP Lama dari keterangan jika JnsSc adalah OU atau OUP
                    CASE
                        WHEN T1.cJnsSc IN ('OU', 'OUP') AND CHARINDEX(' ', T1.cKeterangan) > 0
                        THEN SUBSTRING(T1.cKeterangan,
                                     CHARINDEX(' ', T1.cKeterangan) + 1,
                                     CHARINDEX(' ', T1.cKeterangan + ' ', CHARINDEX(' ', T1.cKeterangan) + 1) - (CHARINDEX(' ', T1.cKeterangan) + 1))
                        ELSE NULL
                    END AS OPLama,
                    CAST(CAST(T1.nPanjang AS INT) AS VARCHAR) + ' x ' + CAST(CAST(T1.nLebar AS INT) AS VARCHAR) + ' x ' + CAST(CAST(T1.nTinggi AS INT) AS VARCHAR) AS Ukuran,
                    -- Mengambil Kualitas dari tbTSC
                    STUFF(
                        COALESCE(' / ' + NULLIF(RTRIM(T_TSC.ckd_b1), ''), '') + COALESCE(' / ' + NULLIF(RTRIM(T_TSC.ckd_b2), ''), '') + COALESCE(' / ' + NULLIF(RTRIM(T_TSC.ckd_b3), ''), '') + COALESCE(' / ' + NULLIF(RTRIM(T_TSC.ckd_b4), ''), '') + COALESCE(' / ' + NULLIF(RTRIM(T_TSC.ckd_b5), ''), ''),
                        1, 3, ''
                    ) AS Kualitas,
                    'parent' AS dataSource
                FROM dbo.tbSC AS T1
                LEFT JOIN dbo.tbTSC AS T_TSC ON T1.cNoSC = T_TSC.cNoSc -- Join ke tbTSC
                WHERE 1=1 $filterClause

                UNION ALL

                -- Query untuk data detail dari tbSCDtl
                SELECT
                    RTRIM(T1.cJnsSc) AS cJnsSc, RTRIM(T1.cNoSC) AS cNoSC, T1.dTanggal, T1.dTglKirim, T1.UserDate, 
                    RTRIM(T_DTL.cTipe) AS cKodeTipe, -- Menggunakan cTipe dari tbSCDtl
                    RTRIM(T_DTL.cJnsGelDtl) AS cJnsGel, 
                    RTRIM(T1.cKeterangan) AS cKeterangan, RTRIM(T1.cKet_Mkt) AS cKet_Mkt, RTRIM(T1.cNama) AS cNama, RTRIM(T1.cSales) AS cSales, 
                    RTRIM(T_DTL.cNama) AS cJenis, -- Menggunakan cNama dari tbSCDtl sebagai cJenis
                    T_DTL.nQty, -- Menggunakan nQty dari tbSCDtl
                    RTRIM(T1.cWarna) AS cWarna,
                    -- Logika yang sama untuk mengambil OP Lama
                    CASE
                        WHEN T1.cJnsSc IN ('OU', 'OUP') AND CHARINDEX(' ', T1.cKeterangan) > 0
                        THEN SUBSTRING(T1.cKeterangan,
                                     CHARINDEX(' ', T1.cKeterangan) + 1,
                                     CHARINDEX(' ', T1.cKeterangan + ' ', CHARINDEX(' ', T1.cKeterangan) + 1) - (CHARINDEX(' ', T1.cKeterangan) + 1))
                        ELSE NULL
                    END AS OPLama,
                    CAST(CAST(T1.nPanjang AS INT) AS VARCHAR) + ' x ' + CAST(CAST(T1.nLebar AS INT) AS VARCHAR) + ' x ' + CAST(CAST(T1.nTinggi AS INT) AS VARCHAR) AS Ukuran,
                    -- Mengambil Kualitas dari tbTSC
                    STUFF(
                        COALESCE(' / ' + NULLIF(RTRIM(T_TSC.ckd_b1), ''), '') + COALESCE(' / ' + NULLIF(RTRIM(T_TSC.ckd_b2), ''), '') + COALESCE(' / ' + NULLIF(RTRIM(T_TSC.ckd_b3), ''), '') + COALESCE(' / ' + NULLIF(RTRIM(T_TSC.ckd_b4), ''), '') + COALESCE(' / ' + NULLIF(RTRIM(T_TSC.ckd_b5), ''), ''),
                        1, 3, ''
                    ) AS Kualitas,
                    'detail' AS dataSource
                FROM dbo.tbSC AS T1
                INNER JOIN dbo.tbSCDtl AS T_DTL ON T1.cNoSC = T_DTL.cNoSC
                LEFT JOIN dbo.tbTSC AS T_TSC ON T1.cNoSC = T_TSC.cNoSc -- Join ke tbTSC
                WHERE 1=1 $filterClause
            )
            SELECT $limit T1.*, 
                   COALESCE(T2.cNoMc, T4.cNoMc) AS cNoMc, -- Menggabungkan hasil No. MC
                   ISNULL(T3.cNoOp, 'BELUM') AS cNoOp
            FROM CombinedResults T1
            LEFT JOIN OUMc T2 ON T1.OPLama = T2.cNoSc -- Join untuk No. MC berdasarkan SC Lama (OU/OUP)
            LEFT JOIN OPOp T3 ON T1.cNoSC = T3.cNoSc -- Join untuk No. OP berdasarkan SC saat ini
            LEFT JOIN SheetMc T4 ON T1.cJenis = T4.cnm_brg AND T1.cJnsSc = 'SHEET' -- Join untuk No. MC berdasarkan Nama Barang (SHEET)
            ORDER BY T1.dTanggal DESC, T1.cNoSC DESC, T1.dataSource ASC
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