<?php
// API: cek dan buka nopol (set cSrjBlk = 2)
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
function logMsg($m){ error_log('['.date('c').'] '. $m ."\n",3,__DIR__.'/buka_nopol.log'); }

$nopol = trim($_POST['nopol'] ?? '');
$do    = trim($_POST['do'] ?? '');
if ($nopol === '') sendJson(['success'=>false,'message'=>'Parameter nopol wajib diisi'], 400);

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
if (!$conn) { $err = sqlsrv_errors(); $msg = $err ? $err[0]['message'] : 'Koneksi database gagal'; sendJson(['success'=>false,'message'=>'Koneksi DB gagal','detail'=>$msg], 503); }

// Check existence: take most recent by date
$sel = "SELECT TOP 1 cNoSC, ISNULL(cSrjBlk,'') AS cSrjBlk, dTanggal FROM tbSRJ WHERE cNoPol = ? ORDER BY dTanggal DESC";
$stmt = sqlsrv_query($conn, $sel, [$nopol], ["QueryTimeout"=>30]);
if ($stmt === false) { $err = sqlsrv_errors(); logMsg('SQLERR sel: '.json_encode($err)); sqlsrv_close($conn); sendJson(['success'=>false,'message'=>'Gagal membaca tbSRJ','errors'=>$err],500); }
$row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
sqlsrv_free_stmt($stmt);
if (!$row) { sqlsrv_close($conn); sendJson(['success'=>true,'found'=>false,'message'=>'NoPol tidak ditemukan']); }

$cur = (string)($row['cSrjBlk'] ?? '');
if ($do === 'open'){
    $upd = "UPDATE tbSRJ SET cSrjBlk = '2' WHERE cNoPol = ?";
    $uStmt = sqlsrv_query($conn, $upd, [$nopol]);
    if ($uStmt === false) { $err = sqlsrv_errors(); logMsg('SQLERR upd: '.json_encode($err)); sqlsrv_close($conn); sendJson(['success'=>false,'message'=>'Gagal menulis ke DB','errors'=>$err],500); }
    $affected = sqlsrv_rows_affected($uStmt);
    sqlsrv_free_stmt($uStmt);
    sqlsrv_close($conn);
    sendJson(['success'=>true,'message'=>'Buka Nopol berhasil','nopol'=>$nopol,'previous'=>$cur,'affected'=>$affected]);
} else {
    sqlsrv_close($conn);
    sendJson(['success'=>true,'found'=>true,'nopol'=>$nopol,'status'=>$cur]);
}

?>
