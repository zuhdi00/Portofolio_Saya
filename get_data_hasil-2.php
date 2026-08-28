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

function handleApiRequest($conn, $sql, $defaultResponse = ['totalHasil' => 0, 'totalRusak' => 0]) {
    $noOp = $_GET['cNoOp'] ?? null;
    if (!$noOp) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Parameter cNoOp dibutuhkan.']);
        return;
    }

    $result = executeQuery($conn, $sql, [$noOp]);
    if ($result['success']) {
        echo json_encode($result['data'][0] ?? $defaultResponse);
    } else {
        http_response_code(500);
        echo json_encode($result);
    }
}

switch ($request_uri) {
    case '/':
        echo json_encode(['message' => 'Selamat datang di Sopanusa API!']);
        break;

    case '/api/hasil-corr':
        handleApiRequest($conn, "SELECT SUM(ISNULL(nHasil, 0) * ISNULL(nOut, 0)) as totalHasil, SUM(ISNULL(nRusak, 0)) as totalRusak FROM dbo.tbHslCorrDtl WHERE cNoOp = ?");
        break;
    
    case '/api/hasil-flexo':
        handleApiRequest($conn, "SELECT SUM(ISNULL(nHasil, 0)) as totalHasil, SUM(ISNULL(nRusak, 0)) as totalRusak FROM dbo.tbConvPlanDtl WHERE cNoOp = ? AND cNamaMsn LIKE '%FLEXO%'");
        break;

    case '/api/hasil-ikat':
        handleApiRequest($conn, "SELECT SUM(ISNULL(nHasil, 0)) as totalHasil, SUM(ISNULL(nRusak, 0)) as totalRusak FROM dbo.tbConvPlanDtl WHERE cNoOp = ? AND cNamaMsn LIKE '%IKAT%'");
        break;

    case '/api/hasil-glue':
        handleApiRequest($conn, "SELECT SUM(ISNULL(nHasil, 0)) as totalHasil, SUM(ISNULL(nRusak, 0)) as totalRusak FROM dbo.tbConvPlanDtl WHERE cNoOp = ? AND cNamaMsn LIKE '%GLUE%'");
        break;

    case '/api/hasil-stitch':
        handleApiRequest($conn, "SELECT SUM(ISNULL(nHasil, 0)) as totalHasil, SUM(ISNULL(nRusak, 0)) as totalRusak FROM dbo.tbConvPlanDtl WHERE cNoOp = ? AND cNamaMsn LIKE '%STITCH%'");
        break;

    case '/api/hasil-slitter':
        handleApiRequest($conn, "SELECT SUM(ISNULL(nHasil, 0)) as totalHasil, SUM(ISNULL(nRusak, 0)) as totalRusak FROM dbo.tbConvPlanDtl WHERE cNoOp = ? AND cNamaMsn LIKE '%SLITTER%'");
        break;

    case '/api/hasil-bungkus':
        handleApiRequest($conn, "SELECT SUM(ISNULL(nHasil, 0)) as totalHasil, SUM(ISNULL(nRusak, 0)) as totalRusak FROM dbo.tbConvPlanDtl WHERE cNoOp = ? AND cNamaMsn LIKE '%BUNGKUS%'");
        break;

    case '/api/hasil-rdc':
        handleApiRequest($conn, "SELECT SUM(ISNULL(nHasil, 0)) as totalHasil, SUM(ISNULL(nRusak, 0)) as totalRusak FROM dbo.tbConvPlanDtl WHERE cNoOp = ? AND cNamaMsn LIKE '%RDC%'");
        break;

    case '/api/hasil-longway':
        handleApiRequest($conn, "SELECT SUM(ISNULL(nHasil, 0)) as totalHasil, SUM(ISNULL(nRusak, 0)) as totalRusak FROM dbo.tbConvPlanDtl WHERE cNoOp = ? AND cNamaMsn LIKE '%LONGWAY%'");
        break;

    case '/api/hasil-updown':
        handleApiRequest($conn, "SELECT SUM(ISNULL(nHasil, 0)) as totalHasil, SUM(ISNULL(nRusak, 0)) as totalRusak FROM dbo.tbConvPlanDtl WHERE cNoOp = ? AND cNamaMsn LIKE '%UP DOWN%'");
        break;

    case '/api/hasil-diecutflat':
        handleApiRequest($conn, "SELECT SUM(ISNULL(nHasil, 0)) as totalHasil, SUM(ISNULL(nRusak, 0)) as totalRusak FROM dbo.tbConvPlanDtl WHERE cNoOp = ? AND cNamaMsn LIKE '%DIE CUT FLAT%'");
        break;

    case '/api/nama-flexo':
        handleApiRequest($conn, "SELECT cFlexo FROM dbo.tbOP WHERE cNoOp = ?", ['cFlexo' => null]);
        break;

    // Combined endpoint: return all hasil values in a single response to avoid multiple HTTP calls
    case '/api/hasil-all':
        $noOp = $_GET['cNoOp'] ?? null;
        if (!$noOp) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Parameter cNoOp dibutuhkan.']);
            break;
        }

        // Combine ConvPlan aggregates into a single scan and Corr aggregates into one scan to reduce repeated table scans
        $sql = "WITH conv AS (
            SELECT
                SUM(CASE WHEN cNamaMsn LIKE '%FLEXO%' THEN ISNULL(nHasil,0) ELSE 0 END) AS totalHasilFlexo,
                SUM(CASE WHEN cNamaMsn LIKE '%FLEXO%' THEN ISNULL(nRusak,0) ELSE 0 END) AS totalRusakFlexo,
                SUM(CASE WHEN cNamaMsn LIKE '%IKAT%' THEN ISNULL(nHasil,0) ELSE 0 END) AS totalHasilIkat,
                SUM(CASE WHEN cNamaMsn LIKE '%IKAT%' THEN ISNULL(nRusak,0) ELSE 0 END) AS totalRusakIkat,
                SUM(CASE WHEN cNamaMsn LIKE '%GLUE%' THEN ISNULL(nHasil,0) ELSE 0 END) AS totalHasilGlue,
                SUM(CASE WHEN cNamaMsn LIKE '%GLUE%' THEN ISNULL(nRusak,0) ELSE 0 END) AS totalRusakGlue,
                SUM(CASE WHEN cNamaMsn LIKE '%STITCH%' THEN ISNULL(nHasil,0) ELSE 0 END) AS totalHasilStitch,
                SUM(CASE WHEN cNamaMsn LIKE '%STITCH%' THEN ISNULL(nRusak,0) ELSE 0 END) AS totalRusakStitch,
                SUM(CASE WHEN cNamaMsn LIKE '%SLITTER%' THEN ISNULL(nHasil,0) ELSE 0 END) AS totalHasilSlitter,
                SUM(CASE WHEN cNamaMsn LIKE '%SLITTER%' THEN ISNULL(nRusak,0) ELSE 0 END) AS totalRusakSlitter,
                SUM(CASE WHEN cNamaMsn LIKE '%BUNGKUS%' THEN ISNULL(nHasil,0) ELSE 0 END) AS totalHasilBungkus,
                SUM(CASE WHEN cNamaMsn LIKE '%BUNGKUS%' THEN ISNULL(nRusak,0) ELSE 0 END) AS totalRusakBungkus,
                SUM(CASE WHEN cNamaMsn LIKE '%RDC%' THEN ISNULL(nHasil,0) ELSE 0 END) AS totalHasilRdc,
                SUM(CASE WHEN cNamaMsn LIKE '%RDC%' THEN ISNULL(nRusak,0) ELSE 0 END) AS totalRusakRdc,
                SUM(CASE WHEN cNamaMsn LIKE '%LONGWAY%' THEN ISNULL(nHasil,0) ELSE 0 END) AS totalHasilLongway,
                SUM(CASE WHEN cNamaMsn LIKE '%LONGWAY%' THEN ISNULL(nRusak,0) ELSE 0 END) AS totalRusakLongway,
                SUM(CASE WHEN cNamaMsn LIKE '%UP DOWN%' THEN ISNULL(nHasil,0) ELSE 0 END) AS totalHasilUpDown,
                SUM(CASE WHEN cNamaMsn LIKE '%UP DOWN%' THEN ISNULL(nRusak,0) ELSE 0 END) AS totalRusakUpDown,
                SUM(CASE WHEN cNamaMsn LIKE '%DIE CUT FLAT%' THEN ISNULL(nHasil,0) ELSE 0 END) AS totalHasilDieCutFlat,
                SUM(CASE WHEN cNamaMsn LIKE '%DIE CUT FLAT%' THEN ISNULL(nRusak,0) ELSE 0 END) AS totalRusakDieCutFlat
            FROM dbo.tbConvPlanDtl
            WHERE cNoOp = ?
        ),
        corr AS (
            SELECT
                SUM(ISNULL(nHasil,0) * ISNULL(nOut,0)) as totalHasilCorr,
                SUM(ISNULL(nRusak,0)) as totalRusakCorr
            FROM dbo.tbHslCorrDtl
            WHERE cNoOp = ?
        )
        SELECT
            corr.totalHasilCorr,
            corr.totalRusakCorr,
            conv.totalHasilFlexo,
            conv.totalRusakFlexo,
            conv.totalHasilIkat,
            conv.totalRusakIkat,
            conv.totalHasilGlue,
            conv.totalRusakGlue,
            conv.totalHasilStitch,
            conv.totalRusakStitch,
            conv.totalHasilSlitter,
            conv.totalRusakSlitter,
            conv.totalHasilBungkus,
            conv.totalRusakBungkus,
            conv.totalHasilRdc,
            conv.totalRusakRdc,
            conv.totalHasilLongway,
            conv.totalRusakLongway,
            conv.totalHasilUpDown,
            conv.totalRusakUpDown,
            conv.totalHasilDieCutFlat,
            conv.totalRusakDieCutFlat,
            (SELECT ISNULL(cFlexo,'') FROM dbo.tbOP WHERE cNoOp = ?) AS cFlexo
        FROM conv CROSS JOIN corr";

        $params = [$noOp, $noOp, $noOp];

        $result = executeQuery($conn, $sql, $params);
        if ($result['success']) {
            echo json_encode($result['data'][0] ?? ['totalHasilCorr' => 0, 'totalRusakCorr' => 0]);
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
