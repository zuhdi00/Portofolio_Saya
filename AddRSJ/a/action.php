<?php
// Endpoint to unlock Feature "FiturRSJMaxAddData" ONCE per call
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
function logMsg($m){ error_log('['.date('c').'] '. $m ."\n",3,__DIR__.'/unlock_rsj.log'); }

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

$action = isset($_GET['action']) ? $_GET['action'] : '';

if ($action === 'cancel') {
    $upd = "UPDATE tbSettingFitur SET Status = '1' WHERE KodeSetting = 'FiturRSJMaxAddData'";
    $uStmt = sqlsrv_query($conn, $upd);
    if ($uStmt === false) { 
        $err = sqlsrv_errors(); 
        logMsg('SQLERR cancel: '.json_encode($err)); 
        sqlsrv_close($conn); 
        sendJson(['success'=>false,'message'=>'Gagal mereset','errors'=>$err], 500); 
    }
    sqlsrv_free_stmt($uStmt);
    sqlsrv_close($conn);
    sendJson(['success'=>true, 'message'=>'Berhasil mereset status']);
}

$sel = "SELECT ISNULL(Status,'') AS Status FROM tbSettingFitur WHERE KodeSetting = 'FiturRSJMaxAddData'";
$selStmt = sqlsrv_query($conn, $sel);

if ($selStmt === false) { 
    $err = sqlsrv_errors(); 
    logMsg('SQLERR sel: '.json_encode($err)); 
    sqlsrv_close($conn); 
    sendJson(['success'=>false,'message'=>'Gagal membaca Database','errors'=>$err], 500); 
}

$row = sqlsrv_fetch_array($selStmt, SQLSRV_FETCH_ASSOC);
sqlsrv_free_stmt($selStmt);

if (!$row) {
    sqlsrv_close($conn);
    sendJson(['success'=>false,'message'=>'Setting FiturRSJMaxAddData tidak ditemukan'], 404);
}

// Check if Status is 1, then change to 0
if ($row['Status'] === '1') {
    $upd = "UPDATE tbSettingFitur SET Status = '0' WHERE KodeSetting = 'FiturRSJMaxAddData'";
    $uStmt = sqlsrv_query($conn, $upd);
    if ($uStmt === false) { 
        $err = sqlsrv_errors(); 
        logMsg('SQLERR upd: '.json_encode($err)); 
        sqlsrv_close($conn); 
        sendJson(['success'=>false,'message'=>'Gagal mengupdate tbSettingFitur','errors'=>$err], 500); 
    }
    sqlsrv_free_stmt($uStmt);
    sqlsrv_close($conn);
    sendJson(['success'=>true, 'unlocked'=>true, 'message'=>'Berhasil membuka akses RSJ']);
} else {
    sqlsrv_close($conn);
    sendJson(['success'=>true, 'unlocked'=>false, 'message'=>'Status belum 1 (Menunggu aplikasi)']);
}
?>
