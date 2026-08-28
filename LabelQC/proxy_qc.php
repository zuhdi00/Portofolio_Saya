<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit;
}

// ─── DB Connection ────────────────────────────────────────────────────────────
function getConn() {
    $serverName = "spsdmz2";
    $opts = [
        "Database"               => "dbSopanusa",
        "Uid"                    => "sa",
        "PWD"                    => "supracor",
        "LoginTimeout"           => 15,
        "Encrypt"                => false,
        "TrustServerCertificate" => true
    ];
    $conn = sqlsrv_connect($serverName, $opts);
    if (!$conn) {
        throw new Exception("Connection failed: " . print_r(sqlsrv_errors(), true));
    }
    return $conn;
}

// ─── Normalize date → YYYY-MM-DD ─────────────────────────────────────────────
function normalizeDate($s) {
    if (!$s) return null;
    if ($s instanceof DateTime) return $s->format('Y-m-d');
    $s = trim((string)$s);
    if ($s === '' || $s === '0000-00-00') return null;
    if (preg_match('/^(\d{2})[\/\-](\d{2})[\/\-](\d{4})$/', $s, $m)) return "{$m[3]}-{$m[2]}-{$m[1]}";
    if (preg_match('/^(\d{4})[\/\-](\d{2})[\/\-](\d{2})/', $s, $m))  return "{$m[1]}-{$m[2]}-{$m[3]}";
    $ts = strtotime($s);
    if ($ts !== false && $ts > 0) return date('Y-m-d', $ts);
    return null;
}

// ─── Safe truncate ────────────────────────────────────────────────────────────
function trunc($val, $maxLen) {
    $val = trim((string)$val);
    if (mb_strlen($val) > $maxLen) {
        error_log("proxy_qc TRUNCATED to $maxLen: '" . mb_substr($val, 0, 80) . "'");
        return mb_substr($val, 0, $maxLen);
    }
    return $val;
}

// ─── Fetch column max lengths for a table (returns assoc: COLUMN_NAME => maxlen|null)
function getTableColumnMaxLengths($conn, $tableName) {
    $sql = "SELECT COLUMN_NAME, DATA_TYPE, CHARACTER_MAXIMUM_LENGTH
            FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_NAME = ?";
    $stmt = sqlsrv_query($conn, $sql, [$tableName]);
    if ($stmt === false) {
        error_log('getTableColumnMaxLengths failed: ' . print_r(sqlsrv_errors(), true));
        return [];
    }
    $res = [];
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $col = $row['COLUMN_NAME'];
        $len = $row['CHARACTER_MAXIMUM_LENGTH'];
        $val = ($len === null) ? null : intval($len);
        $res[$col] = $val;
        $res[strtolower($col)] = $val; // allow case-insensitive lookup
    }
    sqlsrv_free_stmt($stmt);
    return $res;
}

// ─── GET ──────────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = $_GET['action'] ?? '';
    try {
        $conn = getConn();

        if ($action === 'get_columns') {
            $sql = "SELECT COLUMN_NAME, DATA_TYPE, CHARACTER_MAXIMUM_LENGTH, IS_NULLABLE
                    FROM INFORMATION_SCHEMA.COLUMNS
                    WHERE TABLE_NAME = 'tbLabelQc'
                    ORDER BY ORDINAL_POSITION";
            $stmt = sqlsrv_query($conn, $sql);
            if ($stmt === false) throw new Exception("Query columns failed: " . print_r(sqlsrv_errors(), true));
            $rows = [];
            while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) $rows[] = $row;
            sqlsrv_free_stmt($stmt);
            sqlsrv_close($conn);
            echo json_encode(['success' => true, 'data' => $rows], JSON_PRETTY_PRINT);
            exit;
        }

        if ($action === 'get_penyebab') {
            $sql  = "SELECT cSebab FROM tbPenyebab ORDER BY cSebab";
            $stmt = sqlsrv_query($conn, $sql);
            if ($stmt === false) throw new Exception("Query tbPenyebab failed: " . print_r(sqlsrv_errors(), true));
            $rows = [];
            while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) $rows[] = $row;
            sqlsrv_free_stmt($stmt);
            sqlsrv_close($conn);
            echo json_encode(['success' => true, 'data' => $rows]);

        } elseif ($action === 'get_mesin') {
            $sql  = "SELECT cNama FROM tbMesin ORDER BY cNama";
            $stmt = sqlsrv_query($conn, $sql);
            if ($stmt === false) throw new Exception("Query tbMesin failed: " . print_r(sqlsrv_errors(), true));
            $rows = [];
            while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) $rows[] = $row;
            sqlsrv_free_stmt($stmt);
            sqlsrv_close($conn);
            echo json_encode(['success' => true, 'data' => $rows]);

        } else {
            sqlsrv_close($conn);
            echo json_encode(['success' => false, 'message' => 'Unknown action']);
        }

    } catch (Exception $e) {
        error_log('proxy_qc GET exception: ' . $e->getMessage());
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

// ─── POST: simpan ke tbLabelQc ────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);

    if (!is_array($input)) {
        echo json_encode(['success' => false, 'message' => 'Invalid JSON payload']);
        exit;
    }

    $action      = $input['action']      ?? '';
    $dTgl        = $input['dTgl']        ?? '';
    $cNoOp       = trim($input['cNoOp']       ?? '');
    $cCust       = $input['cCust']       ?? '';
    $cItem       = $input['cItem']       ?? '';
    // nQty: defect count (from form). nQtyOrder: order quantity from API/data.
    $nQty        = $input['nQty']        ?? 0; // defect
    $nQtyOrder   = $input['nQtyOrder']    ?? 0; // order qty
    $nPenyebab   = $input['nPenyebab']   ?? '';
    $nMesin      = $input['nMesin']      ?? '';
    $cStatus     = $input['cStatus']     ?? '';
    $cRegu       = $input['cRegu']       ?? '';
    $cPic        = $input['cPic']        ?? '';
    $cKeterangan = $input['cKeterangan'] ?? '';
    $username    = $input['username']    ?? '';
    $userdate    = $input['userdate']    ?? '';

    if ($action !== 'save_label_qc') {
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
        exit;
    }
    if (!$cNoOp) {
        echo json_encode(['success' => false, 'message' => 'No OP tidak boleh kosong']);
        exit;
    }

    $dTgl_sql     = normalizeDate($dTgl)     ?: date('Y-m-d');
    $userdate_sql = normalizeDate($userdate) ?: date('Y-m-d');

    // placeholder for generated cNo; actual value will be generated inside transaction below
    $cNo_raw = '';
    $inTx = false;

    try {
        $conn = getConn();

        // fetch actual column limits to avoid truncation errors
        $colSizes = getTableColumnMaxLengths($conn, 'tbLabelQc');

        // helper to compute safe truncated value based on actual column limit or reasonable default
        $compute = function($val, $colName, $default) use ($colSizes) {
            $v = (string)($val ?? '');
            if (array_key_exists($colName, $colSizes)) {
                if ($colSizes[$colName] === null) {
                    // unlimited (varchar(max) / nvarchar(max)) — apply a large cap
                    return trunc($v, max($default, 2000));
                }
                return trunc($v, max(1, intval($colSizes[$colName])));
            }
            return trunc($v, $default);
        };

        // generate sequential cNo QC/YYMM/NNNNN if column exists (use UPDLOCK/HOLDLOCK)
        if (array_key_exists('cNo', $colSizes)) {
            if (!sqlsrv_begin_transaction($conn)) {
                throw new Exception('Failed to start transaction for cNo generation: ' . print_r(sqlsrv_errors(), true));
            }
            $inTx = true;
            $yy = date('y');
            $mm = date('m');
            $monthPrefix = "QC/{$yy}{$mm}/";
            $like = $monthPrefix . '%';
            $sqlSeq = "SELECT MAX(CAST(RIGHT(cNo,5) AS INT)) AS maxseq FROM tbLabelQc WITH (UPDLOCK, HOLDLOCK) WHERE cNo LIKE ?";
            $stmtSeq = sqlsrv_query($conn, $sqlSeq, [$like]);
            if ($stmtSeq === false) {
                sqlsrv_rollback($conn);
                throw new Exception('Failed to query max cNo: ' . print_r(sqlsrv_errors(), true));
            }
            $rowSeq = sqlsrv_fetch_array($stmtSeq, SQLSRV_FETCH_ASSOC);
            sqlsrv_free_stmt($stmtSeq);
            $maxseq = $rowSeq['maxseq'] ?? 0;
            $next = intval($maxseq) + 1;
            $seqStr = str_pad($next, 5, '0', STR_PAD_LEFT);
            $cNo_raw = $monthPrefix . $seqStr;
            error_log("proxy_qc GENERATED cNo = $cNo_raw (maxseq={$maxseq})");
        }

        // compute safe values
        $cNoOp_s       = $compute($cNoOp, 'cNoOp', 50);
        $cNo_s         = $compute($cNo_raw, 'cNo', 50);
        $cCust_s       = $compute($cCust, 'cCust', 100);
        $cItem_s       = $compute($cItem, 'cItem', 200);
        $nPenyebab_s   = $compute($nPenyebab, 'nPenyebab', 50);
        $nMesin_s      = $compute($nMesin, 'nMesin', 50);
        // cRegu and cStatus: compute safe values. For cRegu column, prefer explicit cRegu input, fallback to cStatus
        $cRegu_s     = $compute($cRegu !== '' ? $cRegu : $cStatus, 'cRegu', 50);
        $cStatus_s   = $compute($cStatus, 'cStatus', 50);
        $cPic_s      = $compute($cPic, 'cPic', 50);
        $cKeterangan_s = $compute($cKeterangan, 'cKeterangan', 100);
        $username_s    = $compute($username, 'username', 50);
        $userdate_s    = $compute($userdate_sql, 'userdate', 50);

        // numeric values
        $nQtyOrder_i   = intval($nQtyOrder);   // order quantity -> store to column `nQty`
        $nQtyDefect_i  = intval($nQty);        // defect count -> store to column `nQtyDefect`

        error_log("proxy_qc INSERT | dTgl=$dTgl_sql | cNoOp=$cNoOp_s | cNo=$cNo_s | cCust(len)=" . mb_strlen($cCust_s) . " | cItem(len)=" . mb_strlen($cItem_s) . " | nQtyOrder=" . $nQtyOrder_i . " | nQtyDefect=" . $nQtyDefect_i . " | nPenyebab=$nPenyebab_s | nMesin=$nMesin_s | cRegu=$cRegu_s | cStatus=$cStatus_s | cPic=$cPic_s | cKet(len)=" . mb_strlen($cKeterangan_s) . " | username=$username_s | userdate=$userdate_s");

        // Build insert columns dynamically — skip cNo if column doesn't exist
        $cols = ['dTgl', 'cNoOp'];
        if (array_key_exists('cNo', $colSizes)) $cols[] = 'cNo';
        // Ensure order quantity stored in `nQty`. If DB has `nQtyDefect`, include it too.
        $cols = array_merge($cols, ['cCust','cItem','nQty']);
        if (array_key_exists('nQtyDefect', $colSizes)) $cols[] = 'nQtyDefect';
        $cols = array_merge($cols, ['nPenyebab','nMesin']);
        if (array_key_exists('cRegu', $colSizes)) $cols[] = 'cRegu';
        if (array_key_exists('cStatus', $colSizes)) $cols[] = 'cStatus';
        $cols = array_merge($cols, ['cPic','cKeterangan','username','userdate']);

        $placeholders = implode(', ', array_fill(0, count($cols), '?'));
        $sql = "INSERT INTO tbLabelQc (" . implode(', ', $cols) . ") VALUES ($placeholders)";

        // prepare params in same order as $cols
        $params = [];
        foreach ($cols as $col) {
            switch ($col) {
                case 'dTgl': $params[] = [ $dTgl_sql, SQLSRV_PARAM_IN, SQLSRV_PHPTYPE_STRING(SQLSRV_ENC_CHAR) ]; break;
                case 'cNoOp': $params[] = [ $cNoOp_s, SQLSRV_PARAM_IN, SQLSRV_PHPTYPE_STRING(SQLSRV_ENC_CHAR) ]; break;
                case 'cNo': $params[] = [ $cNo_s, SQLSRV_PARAM_IN, SQLSRV_PHPTYPE_STRING(SQLSRV_ENC_CHAR) ]; break;
                case 'cCust': $params[] = [ $cCust_s, SQLSRV_PARAM_IN, SQLSRV_PHPTYPE_STRING(SQLSRV_ENC_CHAR) ]; break;
                case 'cItem': $params[] = [ $cItem_s, SQLSRV_PARAM_IN, SQLSRV_PHPTYPE_STRING(SQLSRV_ENC_CHAR) ]; break;
                case 'nQty': $params[] = [ $nQtyOrder_i, SQLSRV_PARAM_IN, SQLSRV_PHPTYPE_INT ]; break;
                case 'nQtyDefect': $params[] = [ $nQtyDefect_i, SQLSRV_PARAM_IN, SQLSRV_PHPTYPE_INT ]; break;
                case 'nPenyebab': $params[] = [ $nPenyebab_s, SQLSRV_PARAM_IN, SQLSRV_PHPTYPE_STRING(SQLSRV_ENC_CHAR) ]; break;
                case 'nMesin': $params[] = [ $nMesin_s, SQLSRV_PARAM_IN, SQLSRV_PHPTYPE_STRING(SQLSRV_ENC_CHAR) ]; break;
                case 'cRegu': $params[] = [ $cRegu_s, SQLSRV_PARAM_IN, SQLSRV_PHPTYPE_STRING(SQLSRV_ENC_CHAR) ]; break;
                case 'cStatus': $params[] = [ $cStatus_s, SQLSRV_PARAM_IN, SQLSRV_PHPTYPE_STRING(SQLSRV_ENC_CHAR) ]; break;
                case 'cPic': $params[] = [ $cPic_s, SQLSRV_PARAM_IN, SQLSRV_PHPTYPE_STRING(SQLSRV_ENC_CHAR) ]; break;
                case 'cKeterangan': $params[] = [ $cKeterangan_s, SQLSRV_PARAM_IN, SQLSRV_PHPTYPE_STRING(SQLSRV_ENC_CHAR) ]; break;
                case 'username': $params[] = [ $username_s, SQLSRV_PARAM_IN, SQLSRV_PHPTYPE_STRING(SQLSRV_ENC_CHAR) ]; break;
                case 'userdate': $params[] = [ $userdate_s, SQLSRV_PARAM_IN, SQLSRV_PHPTYPE_STRING(SQLSRV_ENC_CHAR) ]; break;
                default: $params[] = [ null, SQLSRV_PARAM_IN, SQLSRV_PHPTYPE_STRING(SQLSRV_ENC_CHAR) ]; break;
            }
        }

        $stmt = sqlsrv_query($conn, $sql, $params);

        if ($stmt === false) {
            if ($inTx) sqlsrv_rollback($conn);
            $sqle   = sqlsrv_errors();
            $sqlMsg = is_array($sqle) && !empty($sqle) ? ($sqle[0]['message'] ?? '') : '';
            if (stripos($sqlMsg, 'truncat') !== false || stripos($sqlMsg, 'would be truncated') !== false) {
                $sqlMsg .= " | Buka proxy_qc.php?action=get_columns untuk lihat batas kolom, lalu sesuaikan nilai trunc() di proxy_qc.php";
            }
            error_log('proxy_qc INSERT failed: ' . print_r($sqle, true));
            echo json_encode(['success' => false, 'message' => 'Insert gagal: ' . $sqlMsg]);
            exit;
        }

        sqlsrv_free_stmt($stmt);

        // Commit transaction if we started one for sequence generation
        if ($inTx) {
            if (!sqlsrv_commit($conn)) {
                sqlsrv_rollback($conn);
                error_log('proxy_qc COMMIT failed: ' . print_r(sqlsrv_errors(), true));
                echo json_encode(['success' => false, 'message' => 'Insert gagal: commit error']);
                exit;
            }
        }

        sqlsrv_close($conn);
        echo json_encode(['success' => true, 'message' => 'Label QC berhasil disimpan']);

    } catch (Exception $e) {
        if (isset($conn) && $inTx) {
            @sqlsrv_rollback($conn);
        }
        error_log('proxy_qc exception: ' . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
    }
    exit;
}

http_response_code(405);
echo json_encode(['success' => false, 'message' => 'Method not allowed']);
