<?php
// Setel header untuk mengizinkan CORS dan mengembalikan konten dalam format JSON
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *'); // Izinkan akses dari domain manapun
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle pre-flight OPTIONS request (penting untuk CORS)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// --- KONFIGURASI DATABASE ---
$serverName = "spsdmz2";
$connectionOptions = array(
    "Database" => "dbSopanusa",
    "Uid" => "sa",
    "PWD" => "supracor",
    "LoginTimeout" => 15,
    "Encrypt" => false,
    "TrustServerCertificate" => true
);

// --- KONEKSI KE DATABASE ---
$conn = sqlsrv_connect($serverName, $connectionOptions);

if (!$conn) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Koneksi database gagal.', 'errors' => sqlsrv_errors()]);
    exit;
}

// --- ROUTING SEDERHANA ---
$request_uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
// Hapus nama file script dari URI agar routing bekerja dengan benar
$script_name = str_replace('index.php', '', $_SERVER['SCRIPT_NAME']);
$request_uri = str_replace($script_name, '', $request_uri);

/**
 * Fungsi helper untuk mengeksekusi query dengan aman dan menangani encoding
 */
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
        
        // Konversi encoding dari Latin1 (ISO-8859-1) ke UTF-8
        $encoded_row = [];
        foreach ($row as $key => $value) {
            if (is_string($value)) {
                // Menggunakan iconv untuk konversi yang lebih bersih
                $encoded_val = @iconv('ISO-8859-1', 'UTF-8//IGNORE', $value);
                $encoded_row[$key] = $encoded_val !== false ? $encoded_val : $value;
            } elseif ($value instanceof DateTime) {
                // Jika nilainya adalah objek DateTime, format menjadi string 'YYYY-MM-DD'
                // Ini adalah perbaikan untuk masalah [object Object]
                $encoded_row[$key] = $value->format('Y-m-d');
            } else {
                $encoded_row[$key] = $value;
            }
        }
        $data[] = $encoded_row;
    }
    
    sqlsrv_free_stmt($stmt);
    return ['success' => true, 'data' => $data];
}

// --- PENANGANAN ENDPOINT ---
switch ($request_uri) {
    case '/':
    case '': // Tangani juga root path jika script_name = request_uri
        echo json_encode(['message' => 'Selamat datang di API MC! Gunakan endpoint /get-mc']);
        break;

    // --- ENDPOINT BARU UNTUK MENGAMBIL DATA MC ---
    case '/get-mc':
        if ($_SERVER['REQUEST_METHOD'] === 'GET') {
            
            // 1. Tentukan field yang akan diambil.
            // Gunakan CAST(nama_kolom AS INT) untuk menghilangkan desimal .00
            $selectFields = "cNoMC, 
                             CAST(nPanjang AS INT) AS nPanjang, 
                             CAST(nLebar AS INT) AS nLebar, 
                             CAST(nTinggi AS INT) AS nTinggi, 
                             cpr_ikat, ckd_b1, ckd_b2, ckd_b3, ckd_b4, ckd_b5, Dtgl";
                             
            $baseSql = "SELECT TOP 100 $selectFields FROM tbMC"; // Batasi hasil (opsional, baik untuk performa)
            
            $whereConditions = [];
            $params = [];

            // 2. Ambil parameter filter dari query string (GET)
            // Filter Nomer MC (cNoMC) - String, pakai LIKE
            if (!empty($_GET['nomor'])) {
                $whereConditions[] = "cNoMC LIKE ?";
                $params[] = '%' . $_GET['nomor'] . '%';
            }

            // Filter Panjang (nPanjang) - Numerik, cari kecocokan persis (=)
            if (!empty($_GET['panjang'])) {
                $whereConditions[] = "nPanjang = ?";
                $params[] = $_GET['panjang'];
            }

            // Filter Lebar (nLebar) - Numerik, cari kecocokan persis (=)
            if (!empty($_GET['lebar'])) {
                $whereConditions[] = "nLebar = ?";
                $params[] = $_GET['lebar'];
            }

            // Filter Tinggi (nTinggi) - Numerik, cari kecocokan persis (=)
            if (!empty($_GET['tinggi'])) {
                $whereConditions[] = "nTinggi = ?";
                $params[] = $_GET['tinggi'];
            }

            // 3. Gabungkan query SQL
            if (count($whereConditions) > 0) {
                $baseSql .= " WHERE " . implode(" AND ", $whereConditions);
            }
            
            $baseSql .= " ORDER BY Dtgl DESC, cNoMC DESC"; // Urutkan berdasarkan tanggal terbaru di atas

            // 4. Eksekusi query
            $result = executeQuery($conn, $baseSql, $params);

            // 5. Kembalikan hasil
            if ($result['success']) {
                http_response_code(200);
                echo json_encode($result);
            } else {
                http_response_code(500);
                echo json_encode($result);
            }

        } else {
            // Handle method lain selain GET
            http_response_code(405); // Method Not Allowed
            echo json_encode(['success' => false, 'message' => 'Metode tidak diizinkan. Gunakan GET.']);
        }
        break;
    // --- AKHIR ENDPOINT BARU ---

    default:
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Endpoint tidak ditemukan: ' . $request_uri]);
        break;
}

// Tutup koneksi database
sqlsrv_close($conn);

?>