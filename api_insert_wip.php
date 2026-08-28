<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

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
    echo json_encode(['success' => false, 'message' => 'Connection failed']);
    exit;
}

// List of fields we accept from the client (map to tbWIPV2 columns)
$accepted = [
    'cNoSTB','cCom','dTanggal','cNoSc','cNamabrg','cNoMC','cNoOp','cKodeCust','cNama',
    'nPanjang','nLebar','nTinggi','cWarna','cJnsGel','cType','cSub1','cSub2','cSub3','cSub4','cSub5',
    'nQty','nQtyKg','dTglkirim','lPosted','cKeterangan','cRak'
];

$columns = [];
$placeholders = [];
$params = [];

// Accept common alias keys from the app and map them to DB column names
$aliases = [
    'cnm_brg' => 'cNamabrg',
    'cNoMc'   => 'cNoMC',
    'cnm_c'   => 'cNama',
    'cNoStb'  => 'cNoSTB'
];

$used = [];
// Map incoming POST keys to target columns (accept either canonical or alias names)
foreach ($_POST as $key => $val) {
    if ($val === '') continue;
    $col = null;
    if (in_array($key, $accepted, true)) {
        $col = $key;
    } elseif (isset($aliases[$key])) {
        $col = $aliases[$key];
        if (!in_array($col, $accepted, true)) {
            $col = null;
        }
    }

    if ($col !== null && !in_array($col, $used, true)) {
        $columns[] = $col;
        $placeholders[] = '?';
        $params[] = $val;
        $used[] = $col;
    }
}

// As a fallback, check accepted keys explicitly (preserve previous behavior)
if (count($columns) === 0) {
    foreach ($accepted as $f) {
        if (isset($_POST[$f]) && $_POST[$f] !== '') {
            if (!in_array($f, $used, true)) {
                $columns[] = $f;
                $placeholders[] = '?';
                $params[] = $_POST[$f];
                $used[] = $f;
            }
        }
    }
}

if (count($columns) === 0) {
    echo json_encode(['success' => false, 'message' => 'No data provided']);
    exit;
}

// If cNoSTB not provided or empty, generate next pallet number per cNoOp
$cNoOpVal = null;
if (isset($_POST['cNoOp']) && $_POST['cNoOp'] !== '') {
    $cNoOpVal = $_POST['cNoOp'];
} else {
    // try to find cNoOp in aliases or submitted columns
    foreach ($columns as $i => $coln) {
        if (strtolower($coln) === 'cnoop' || strtolower($coln) === 'cnoop') {
            $cNoOpVal = $params[$i] ?? null;
            break;
        }
    }
}

if ((empty($_POST['cNoSTB']) || (isset($_POST['cNoSTB']) && $_POST['cNoSTB']==='')) && !in_array('cNoSTB', $used, true)) {
    // prefer tbWIPV2 then tbWIP_V2 in dbo schema
    $seqTableSchema = null;
    $seqTableName = null;
    $tryList = [ ['dbo','tbWIPV2'], ['dbo','tbWIP_V2'] ];
    foreach ($tryList as $t) {
        $s = $t[0]; $n = $t[1];
        $check = sqlsrv_query($conn, "SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA='" . $s . "' AND TABLE_NAME='" . $n . "'");
        if ($check) {
            $row = sqlsrv_fetch_array($check, SQLSRV_FETCH_NUMERIC);
            if ($row) { $seqTableSchema = $s; $seqTableName = $n; break; }
        }
    }

    $nextNum = 1;
    if ($seqTableSchema !== null && $cNoOpVal !== null) {
        $maxSql = "SELECT MAX(TRY_CAST(cNoSTB AS INT)) AS maxstb FROM [" . $seqTableSchema . "].[" . $seqTableName . "] WHERE cNoOp = ?";
        $q = sqlsrv_query($conn, $maxSql, array($cNoOpVal));
        if ($q) {
            $r = sqlsrv_fetch_array($q, SQLSRV_FETCH_ASSOC);
            $maxv = $r['maxstb'] ?? null;
            if ($maxv !== null) {
                $nextNum = intval($maxv) + 1;
            }
        }
    }
    // format as 3-digit with leading zeros
    $generatedStb = str_pad((string)$nextNum, 3, '0', STR_PAD_LEFT);
    // add into columns/params/placeholders
    $columns[] = 'cNoSTB';
    $placeholders[] = '?';
    $params[] = $generatedStb;
    $used[] = 'cNoSTB';
}

$colList = implode(',', $columns);
$phList = implode(',', $placeholders);

// Normalize date parameters to SQL-friendly format (YYYY-MM-DD or YYYY-MM-DD HH:MM:SS)
$dateCols = ['dTanggal', 'dTglkirim'];
for ($i = 0; $i < count($columns); $i++) {
    if (in_array($columns[$i], $dateCols, true)) {
        $raw = trim((string)$params[$i]);
        if ($raw === '' || strtoupper($raw) === 'NULL') {
            $params[$i] = null;
            continue;
        }
        $parsed = false;
        $formats = [
            'Y-m-d H:i:s', 'Y-m-d',
            'd/m/Y H:i:s', 'd/m/Y',
            'd-m-Y H:i:s', 'd-m-Y',
            'd/m/y', 'd-m-y', 'Y/m/d'
        ];
        foreach ($formats as $fmt) {
            $dt = DateTime::createFromFormat($fmt, $raw);
            if ($dt !== false) {
                $parsed = $dt;
                break;
            }
        }
        if ($parsed !== false) {
            // prefer datetime when time is present
            $timePart = $parsed->format('H:i:s');
            if ($timePart === '00:00:00') $params[$i] = $parsed->format('Y-m-d');
            else $params[$i] = $parsed->format('Y-m-d H:i:s');
        } else {
            // fallback to strtotime
            $ts = strtotime($raw);
            if ($ts !== false) {
                $params[$i] = date('Y-m-d H:i:s', $ts);
            } else {
                // unable to parse, set NULL to avoid conversion error
                $params[$i] = null;
            }
        }
    }
}

// Check current database and try to resolve schema-qualified table name for tbWIPV2
$currentDb = null;
$dbRes = sqlsrv_query($conn, "SELECT DB_NAME() AS dbname");
if ($dbRes) {
    $dbRow = sqlsrv_fetch_array($dbRes, SQLSRV_FETCH_ASSOC);
    $currentDb = $dbRow['dbname'] ?? null;
}

$fullTable = '[dbo].[tbWIPV2]';
$foundSimilar = [];

$checkSql = "SELECT s.name AS schema_name, t.name AS table_name FROM sys.tables t JOIN sys.schemas s ON t.schema_id = s.schema_id WHERE t.name = 'tbWIPV2'";
$checkStmt = sqlsrv_query($conn, $checkSql);
if ($checkStmt) {
    $r = sqlsrv_fetch_array($checkStmt, SQLSRV_FETCH_ASSOC);
    if ($r && !empty($r['table_name'])) {
        $fullTable = '[' . $r['schema_name'] . '].[' . $r['table_name'] . ']';
    } else {
        // try to list similar tables for diagnostics
        $likeStmt = sqlsrv_query($conn, "SELECT s.name AS schema_name, t.name AS table_name FROM sys.tables t JOIN sys.schemas s ON t.schema_id = s.schema_id WHERE t.name LIKE '%WIP%'");
        if ($likeStmt) {
            while ($row = sqlsrv_fetch_array($likeStmt, SQLSRV_FETCH_ASSOC)) {
                $foundSimilar[] = ($row['schema_name'] ?? '') . '.' . ($row['table_name'] ?? '');
            }
        }
    }
} else {
    $chkErr = sqlsrv_errors();
    if ($chkErr !== null) {
        foreach ($chkErr as $e) {
            $foundSimilar[] = ($e['message'] ?? '');
        }
    }
}
$attempts = [];

// If initial insert failed, try fallback candidates (dbo-qualified, DB-qualified, similar tables)
$candidates = [];
// include resolved $fullTable (if it was detected from sys.tables), else include canonical names
$candidates[] = $fullTable;
// explicit variants (prefer these)
$candidates[] = '[dbo].[tbWIPV2]';
$candidates[] = '[dbo].[tbWIP_V2]';
if ($currentDb) {
    $candidates[] = '[' . $currentDb . '].[dbo].[tbWIPV2]';
    $candidates[] = '[' . $currentDb . '].[dbo].[tbWIP_V2]';
}
// add discovered similar tables
foreach ($foundSimilar as $fs) {
    // already like 'schema.table'
    if (strpos($fs, '.') !== false) {
        $parts = explode('.', $fs);
        $candidates[] = '[' . trim($parts[0]) . '].[' . trim($parts[1]) . ']';
    } else {
        $candidates[] = '[' . $fs . ']';
    }
}

// Try to discover WIP-like tables and their columns via INFORMATION_SCHEMA
$tablesInfo = [];
$tblStmt = sqlsrv_query($conn, "SELECT TABLE_SCHEMA, TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_NAME LIKE '%WIP%'");
if ($tblStmt) {
    while ($t = sqlsrv_fetch_array($tblStmt, SQLSRV_FETCH_ASSOC)) {
        $schema = $t['TABLE_SCHEMA'];
        $name = $t['TABLE_NAME'];
        $key = $schema . '.' . $name;
        $cols = [];
        $colStmt = sqlsrv_query($conn, "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA='" . $schema . "' AND TABLE_NAME='" . $name . "'");
        if ($colStmt) {
            while ($c = sqlsrv_fetch_array($colStmt, SQLSRV_FETCH_ASSOC)) {
                $cols[] = $c['COLUMN_NAME'];
            }
        }
        $tablesInfo[$key] = $cols;
    }
}

// Evaluate best matching table by column intersection
$bestTable = null;
$bestMatchCount = -1;
$bestTableCols = [];
foreach ($tablesInfo as $tname => $tcols) {
    $matchCount = 0;
    foreach ($columns as $col) {
        if (in_array($col, $tcols, true)) $matchCount++;
    }
    if ($matchCount > $bestMatchCount) {
        $bestMatchCount = $matchCount;
        $bestTable = $tname; // schema.table
        $bestTableCols = $tcols;
    }
}

if ($bestTable !== null && $bestMatchCount > 0) {
    // add to candidates list schema-qualified bracketed name
    $parts = explode('.', $bestTable);
    $candidates[] = '[' . $parts[0] . '].[' . $parts[1] . ']';
}

foreach ($candidates as $cand) {
    // If we have column info for this candidate, filter columns/params to only existing columns
    $filteredCols = $columns;
    $filteredParams = $params;
    $candKey = null;
    // normalize cand like [schema].[table] -> schema.table
    if (preg_match("/\[(.*?)\]\.\[(.*?)\]/", $cand, $m)) {
        $candKey = $m[1] . '.' . $m[2];
    }
    if ($candKey && isset($tablesInfo[$candKey]) && is_array($tablesInfo[$candKey])) {
        // do case-insensitive column intersection
        $existColsRaw = $tablesInfo[$candKey];
        $existCols = array_map('strtolower', $existColsRaw);
        $filteredCols = [];
        $filteredParams = [];
        for ($i = 0; $i < count($columns); $i++) {
            if (in_array(strtolower($columns[$i]), $existCols, true)) {
                $filteredCols[] = $columns[$i];
                $filteredParams[] = $params[$i];
            }
        }
        if (count($filteredCols) === 0) {
            // nothing to insert for this table
            $attempts[] = ['sql' => null, 'success' => false, 'errors' => ['no matching columns for ' . $candKey]];
            continue;
        }
    }

    $tryColList = implode(',', $filteredCols);
    $tryPhList = implode(',', array_fill(0, count($filteredCols), '?'));
    $trySql = "INSERT INTO $cand ($tryColList) VALUES ($tryPhList)";
    $tryStmt = @sqlsrv_query($conn, $trySql, $filteredParams);
    $attempts[] = ['sql' => $trySql, 'success' => $tryStmt ? true : false, 'errors' => sqlsrv_errors()];
    if ($tryStmt) {
        echo json_encode(['success' => true, 'attempts' => $attempts, 'used_table' => $cand, 'inserted_cols' => $filteredCols]);
        exit;
    }
}

// final failure: collect errors readable
$finalErr = [];
foreach ($attempts as $a) {
    if (!empty($a['errors'])) {
        foreach ($a['errors'] as $e) {
            $finalErr[] = "SQLSTATE: " . ($e['SQLSTATE'] ?? '') . "; code: " . ($e['code'] ?? '') . "; message: " . ($e['message'] ?? '');
        }
    }
}

echo json_encode([
    'success' => false,
    'message' => 'Insert failed after attempts',
    'error' => $finalErr,
    'attempts' => $attempts,
    'params' => $params,
    'current_db' => $currentDb,
    'found_tables_like' => $foundSimilar
]);

?>
