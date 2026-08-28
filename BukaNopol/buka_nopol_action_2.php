<?php
// API: cek dan buka nopol (set cSrjBlk = '2' untuk semua baris dengan cNoPol tersebut)
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

@ini_set('display_errors', 0);
ob_start();

function safeJsonEncode($data) {
    $opts = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;
    $json = json_encode($data, $opts);
    if ($json === false) {
        $msg = function_exists('json_last_error_msg') ? json_last_error_msg() : json_last_error();
        $json = json_encode(['success' => false, 'message' => 'JSON encode error', 'err' => $msg]);
    }
    return $json;
}

function sendJson($data, $code = 200) {
    while (ob_get_level()) ob_end_clean();
    http_response_code($code);
    header('Content-Type: application/json');
    echo safeJsonEncode($data);
    exit;
}

function logMsg($m) {
    error_log('[' . date('c') . '] ' . $m . "\n", 3, __DIR__ . '/buka_nopol.log');
}

$nopol = strtoupper(trim($_POST['nopol'] ?? ''));
$do    = trim($_POST['do'] ?? '');

if ($nopol === '') {
    sendJson(['success' => false, 'message' => 'Parameter nopol wajib diisi'], 400);
}

$serverName = "spsdmz2";
$connectionOptions = [
    "Database"            => "dbSopanusa",
    "Uid"                 => "sa",
    "PWD"                 => "supracor",
    "LoginTimeout"        => 15,
    "Encrypt"             => false,
    "TrustServerCertificate" => true
];

$conn = @sqlsrv_connect($serverName, $connectionOptions);
if (!$conn) {
    $err = sqlsrv_errors();
    $msg = $err ? $err[0]['message'] : 'Koneksi database gagal';
    sendJson(['success' => false, 'message' => 'Koneksi DB gagal', 'detail' => $msg], 503);
}

// Ambil semua entri untuk nopol ini, urutkan terbaru
$sel  = "SELECT cNoSRJ, cNoPol, ISNULL(cSrjBlk,'') AS cSrjBlk, dTanggal, cNama
          FROM tbSRJ
          WHERE cNoPol = ?
          ORDER BY dTanggal DESC";
$stmt = sqlsrv_query($conn, $sel, [$nopol], ["QueryTimeout" => 30]);

if ($stmt === false) {
    $err = sqlsrv_errors();
    logMsg('SQLERR sel: ' . json_encode($err));
    sqlsrv_close($conn);
    sendJson(['success' => false, 'message' => 'Gagal membaca tbSRJ', 'errors' => $err], 500);
}

$rows = [];
while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    // Format tanggal agar JSON-serializable
    if ($row['dTanggal'] instanceof DateTime) {
        $row['dTanggal'] = $row['dTanggal']->format('Y-m-d');
    }
    $rows[] = $row;
}
sqlsrv_free_stmt($stmt);

if (empty($rows)) {
    sqlsrv_close($conn);
    sendJson(['success' => true, 'found' => false, 'message' => 'NoPol tidak ditemukan di tbSRJ']);
}

// Tentukan status keseluruhan:
// "open"   = semua cSrjBlk == '2'
// "closed" = ada yang bukan '2'
// "mixed"  = sebagian '2' sebagian bukan
$allOpen   = true;
$allClosed = true;
foreach ($rows as $r) {
    if ($r['cSrjBlk'] === '2') { $allClosed = false; }
    else                         { $allOpen   = false; }
}

if ($allOpen)        $overallStatus = 'open';
elseif ($allClosed)  $overallStatus = 'closed';
else                 $overallStatus = 'mixed';

if ($do === 'open') {
    // Update SEMUA entri nopol ini menjadi cSrjBlk = '2'
    $upd   = "UPDATE tbSRJ SET cSrjBlk = '2' WHERE cNoPol = ?";
    $uStmt = sqlsrv_query($conn, $upd, [$nopol]);
    if ($uStmt === false) {
        $err = sqlsrv_errors();
        logMsg('SQLERR upd: ' . json_encode($err));
        sqlsrv_close($conn);
        sendJson(['success' => false, 'message' => 'Gagal menulis ke DB', 'errors' => $err], 500);
    }
    $affected = sqlsrv_rows_affected($uStmt);
    sqlsrv_free_stmt($uStmt);
    sqlsrv_close($conn);

    sendJson([
        'success'  => true,
        'message'  => 'Buka Nopol berhasil',
        'nopol'    => $nopol,
        'affected' => $affected,
        'rows'     => $rows   // data sebelum update untuk referensi
    ]);
} else {
    // Mode cek saja
    sqlsrv_close($conn);
    sendJson([
        'success'        => true,
        'found'          => true,
        'nopol'          => $nopol,
        'overallStatus'  => $overallStatus,
        'totalRows'      => count($rows),
        'rows'           => $rows
    ]);
}
?>
