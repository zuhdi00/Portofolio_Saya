<?php
// ticket_submit.php
// Accepts POST from Ticketing.html and inserts into tbEDPTiket

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json');

@ini_set('display_errors','0');
@ini_set('log_errors','1');
error_reporting(E_ALL);

ob_start();
@set_time_limit(120);
@ini_set('memory_limit','256M');

function safeJsonEncode($data) {
    $opts = 0;
    if (defined('JSON_PARTIAL_OUTPUT_ON_ERROR')) $opts |= JSON_PARTIAL_OUTPUT_ON_ERROR;
    if (defined('JSON_UNESCAPED_UNICODE')) $opts |= JSON_UNESCAPED_UNICODE;
    $json = json_encode($data, $opts);
    if ($json === false) {
        $errmsg = function_exists('json_last_error_msg') ? json_last_error_msg() : json_last_error();
        error_log("[ticket_submit] json_encode failed: $errmsg\n", 3, __DIR__.'/ticket_submit.log');
        $json = json_encode(['success'=>false,'message'=>'Internal JSON encoding error','json_error'=>$errmsg], JSON_UNESCAPED_UNICODE);
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
    $fn = __DIR__ . '/ticket_submit.log';
    $t = date('c');
    error_log("[$t] $m\n", 3, $fn);
}

// OPTIONS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Read input (support form-encoded and JSON)
$input = [];
$contentType = $_SERVER['CONTENT_TYPE'] ?? '';
if (stripos($contentType, 'application/json') !== false) {
    $body = file_get_contents('php://input');
    $json = json_decode($body, true);
    if (is_array($json)) $input = $json;
} else {
    $input = $_POST;
}

$name = trim($input['name'] ?? '');
$dept = trim($input['department'] ?? '');
$subject = trim($input['subject'] ?? '');
$desc = trim($input['description'] ?? '');

if ($name === '' || $dept === '' || $subject === '' || $desc === '') {
    sendJson(['success'=>false, 'message'=>'Semua field wajib diisi (name, department, subject, description)'], 400);
}

// DB config (reuse same as report_backend)
$serverName = "spsdmz2";
$connectionOptions = [
    "Database"               => "dbSopanusa",
    "Uid"                    => "sa",
    "PWD"                    => "supracor",
    "LoginTimeout"           => 15,
    "Encrypt"                => false,
    "TrustServerCertificate" => true
];

$conn = @sqlsrv_connect($serverName, $connectionOptions);
if (!$conn) {
    $err = sqlsrv_errors();
    $errMsg = $err ? $err[0]['message'] : 'Koneksi database gagal';
    logMsg('DB connect failed: ' . $errMsg);
    sendJson(['success'=>false, 'message'=>'Koneksi database gagal', 'detail'=>$errMsg], 503);
}

// Generate NoTiket: EDP/YYMM/NNNNN
$yy = date('y');
$mm = date('m');
$base = "EDP/{$yy}{$mm}/"; // e.g. EDP/2603/
$like = $base . '%';

// Find current max sequence for this YYMM
$sqlMax = "SELECT ISNULL(MAX(CAST(RIGHT(NoTiket,5) AS INT)), 0) AS maxSeq FROM tbEDPTiket WHERE NoTiket LIKE ?";
$stmt = sqlsrv_query($conn, $sqlMax, [$like]);
if ($stmt === false) {
    $err = sqlsrv_errors();
    $msg = $err ? $err[0]['message'] : 'Gagal membaca nomor tiket terakhir';
    sqlsrv_close($conn);
    logMsg('Failed getting maxSeq: '.$msg);
    sendJson(['success'=>false, 'message'=>$msg], 500);
}
$row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
$maxSeq = isset($row['maxSeq']) ? (int)$row['maxSeq'] : 0;
sqlsrv_free_stmt($stmt);

$next = $maxSeq + 1;
$noSeq = str_pad($next, 5, '0', STR_PAD_LEFT);
$noTiket = $base . $noSeq;

// Insert record
$dTanggal = date('Y-m-d H:i:s');
$insertSql = "INSERT INTO tbEDPTiket (NoTiket, cUser, cDep, cKeterangan, cKeteranganDtl, dTanggal) VALUES (?,?,?,?,?,?)";
$params = [$noTiket, $name, $dept, $subject, $desc, $dTanggal];
$ins = sqlsrv_query($conn, $insertSql, $params);
if ($ins === false) {
    $err = sqlsrv_errors();
    $msg = $err ? $err[0]['message'] : 'Gagal menyisipkan tiket';
    sqlsrv_close($conn);
    logMsg('Insert failed: '.$msg);
    sendJson(['success'=>false, 'message'=>$msg], 500);
}

sqlsrv_free_stmt($ins);
sqlsrv_close($conn);
logMsg("Created ticket {$noTiket} by {$name}");
sendJson(['success'=>true, 'NoTiket'=>$noTiket, 'dTanggal'=>$dTanggal], 201);
