<?php
// Simple endpoint to batal close (set cBatalStatus=0 and clear cStatus)
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

@ini_set('display_errors', 0);
ob_start();

function safeJsonEncode($data){
    $opts = 0;
    if (defined('JSON_UNESCAPED_UNICODE')) $opts |= JSON_UNESCAPED_UNICODE;
    $json = json_encode($data, $opts);
    if ($json === false) { $msg = function_exists('json_last_error_msg') ? json_last_error_msg() : json_last_error(); $json = json_encode(['success'=>false,'message'=>'JSON encode error','err'=>$msg]); }
    return $json;
}
function sendJson($data, $code=200){ while(ob_get_level()) ob_end_clean(); http_response_code($code); header('Content-Type: application/json'); echo safeJsonEncode($data); exit; }
function logMsg($m){ error_log('['.date('c').'] '. $m ."\n",3,__DIR__.'/batal_close.log'); }

$sc = trim($_POST['sc'] ?? '');
if ($sc === '') sendJson(['success'=>false,'message'=>'Parameter sc wajib diisi'], 400);

$serverName = "spsdmz2";
$connectionOptions = [
    "Database" => "dbSopanusa",
    "Uid" => "sa",
    "PWD" => "supracor",
    "LoginTimeout" => 15,
    "Encrypt" => false,
    "TrustServerCertificate" => true
];

$conn = @sqlsrv_connect($serverName, $connectionOptions);
if (!$conn) {
    $err = sqlsrv_errors();
    $msg = $err ? $err[0]['message'] : 'Koneksi database gagal';
    sendJson(['success'=>false,'message'=>'Koneksi DB gagal','detail'=>$msg], 503);
}

// Check existence
$sel = "SELECT ISNULL(cStatus,'') AS cStatus, ISNULL(cBatalStatus,0) AS cBatalStatus FROM tbSC WHERE cNoSc = ?";
$selStmt = sqlsrv_query($conn, $sel, [$sc], ["QueryTimeout"=>30]);
if ($selStmt === false) { $err = sqlsrv_errors(); logMsg('SQLERR sel: '.json_encode($err)); sqlsrv_close($conn); sendJson(['success'=>false,'message'=>'Gagal membaca SLC','errors'=>$err],500); }
$row = sqlsrv_fetch_array($selStmt, SQLSRV_FETCH_ASSOC);
sqlsrv_free_stmt($selStmt);
if (!$row) { sqlsrv_close($conn); sendJson(['success'=>false,'message'=>'No SLC tidak ditemukan: '.$sc], 404); }

$curBatal = (int)($row['cBatalStatus'] ?? 0);

// Perform update: set cBatalStatus=0 and clear cStatus
$upd = "UPDATE tbSC SET cBatalStatus = 0, cStatus = NULL WHERE cNoSc = ?";
$uStmt = sqlsrv_query($conn, $upd, [$sc]);
if ($uStmt === false) { $err = sqlsrv_errors(); logMsg('SQLERR upd: '.json_encode($err)); sqlsrv_close($conn); sendJson(['success'=>false,'message'=>'Gagal menulis ke DB','errors'=>$err],500); }

$rows = sqlsrv_rows_affected($uStmt);
sqlsrv_free_stmt($uStmt);
sqlsrv_close($conn);

if ($rows === 0) {
    sendJson(['success'=>false,'message'=>'No SLC tidak ditemukan atau tidak ada perubahan: '.$sc], 404);
} else {
    sendJson(['success'=>true,'message'=>'Batal close berhasil','sc'=>$sc,'previous_cBatal'=>$curBatal]);
}

?>
