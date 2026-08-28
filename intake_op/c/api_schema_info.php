<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

$serverName = "spsdmz2";
$connectionOptions = [
    "Database" => "dbSopanusa",
    "Uid" => "sa",
    "PWD" => "supracor",
    "ReturnDatesAsStrings" => true,
    "CharacterSet" => "UTF-8"
];

$conn = sqlsrv_connect($serverName, $connectionOptions);
if (!$conn) { 
    echo json_encode(['success'=>false,'message'=>'DB connect failed']); 
    exit; 
}

$table = trim($_GET['table'] ?? '');
$mode = trim($_GET['mode'] ?? 'schema'); // schema atau sample

if (!in_array($table, ['tbStbBJ', 'tbHslCorrDtl', 'tbCorrDtl'])) {
    echo json_encode(['success'=>false,'message'=>'invalid table']); 
    sqlsrv_close($conn);
    exit;
}

// Get schema info
$schemaSql = "SELECT COLUMN_NAME, DATA_TYPE, CHARACTER_MAXIMUM_LENGTH FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = ? ORDER BY ORDINAL_POSITION";
$schemaStmt = sqlsrv_query($conn, $schemaSql, [$table]);

$columns = [];
if ($schemaStmt) {
    while ($r = sqlsrv_fetch_array($schemaStmt, SQLSRV_FETCH_ASSOC)) {
        $columns[] = $r;
    }
    sqlsrv_free_stmt($schemaStmt);
}

// Get sample data
$sampleData = [];
if ($mode === 'sample' && count($columns) > 0) {
    $sampleSql = "SELECT TOP 10 * FROM $table WITH (NOLOCK)";
    $sampleStmt = sqlsrv_query($conn, $sampleSql);
    
    if ($sampleStmt) {
        while ($r = sqlsrv_fetch_array($sampleStmt, SQLSRV_FETCH_ASSOC)) {
            $sampleData[] = $r;
        }
        sqlsrv_free_stmt($sampleStmt);
    }
}

sqlsrv_close($conn);

echo json_encode([
    'success' => true,
    'table' => $table,
    'mode' => $mode,
    'columns' => $columns,
    'sampleData' => $sampleData
], JSON_UNESCAPED_UNICODE);
