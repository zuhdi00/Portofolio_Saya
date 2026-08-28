<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit;
}

// ─── DB Connection ───────────────────────────────────────────────────────────
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

// ─── Normalize date → YYYY-MM-DD (string) ────────────────────────────────────
// Handles: DD-MM-YYYY, DD/MM/YYYY, YYYY-MM-DD, YYYY/MM/DD, SQL datetime strings
function normalizeDate($s) {
    if (!$s) return null;
    if ($s instanceof DateTime) return $s->format('Y-m-d');
    $s = trim((string)$s);
    if ($s === '' || $s === '0000-00-00') return null;

    // DD-MM-YYYY or DD/MM/YYYY  (most common from frontend getTodayFormatted())
    if (preg_match('/^(\d{2})[\/\-](\d{2})[\/\-](\d{4})$/', $s, $m)) {
        return "{$m[3]}-{$m[2]}-{$m[1]}";
    }
    // YYYY-MM-DD or YYYY/MM/DD (already correct or from getTodayISO())
    if (preg_match('/^(\d{4})[\/\-](\d{2})[\/\-](\d{2})/', $s, $m)) {
        return "{$m[1]}-{$m[2]}-{$m[3]}";
    }
    // Fallback: let PHP try (works for many English-format strings)
    $ts = strtotime($s);
    if ($ts !== false && $ts > 0) return date('Y-m-d', $ts);

    return null; // unable to parse
}

// ─── GET: dropdown data ───────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = $_GET['action'] ?? '';
    try {
        $conn = getConn();

        if ($action === 'get_penyebab') {
            $sql  = "SELECT cMU FROM tbMU ORDER BY cMU";
            $stmt = sqlsrv_query($conn, $sql);
            if ($stmt === false) throw new Exception("Query tbMU failed: " . print_r(sqlsrv_errors(), true));
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
    $nQty        = $input['nQty']        ?? 0;
    $nPenyebab   = $input['nPenyebab']   ?? '';   // VARCHAR – nama penyebab
    $nMesin      = $input['nMesin']      ?? '';   // VARCHAR – nama mesin
    $cStatus     = $input['cStatus']     ?? '';   // → disimpan ke cRegu sesuai spec
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

    // ── Normalize dates ──────────────────────────────────────────────────────
    $dTgl_sql     = normalizeDate($dTgl);
    $userdate_sql = normalizeDate($userdate);

    // Log raw vs normalized for debugging
    error_log("proxy_qc INSERT attempt | cNoOp=$cNoOp | dTgl_raw=$dTgl | dTgl_sql=$dTgl_sql | userdate_raw=$userdate | userdate_sql=$userdate_sql");

    if (!$dTgl_sql) {
        error_log("proxy_qc: could not parse dTgl='$dTgl'");
        // Use today as safe fallback
        $dTgl_sql = date('Y-m-d');
    }
    if (!$userdate_sql) {
        $userdate_sql = date('Y-m-d');
    }

    try {
        $conn = getConn();

        // Pass dates as plain strings in YYYY-MM-DD format.
        // sqlsrv will convert VARCHAR → datetime automatically for properly formatted strings.
        $sql = "INSERT INTO tbLabelQc 
                    (dTgl, cNoOp, cCust, cItem, nQty, nPenyebab, nMesin, cRegu, cPic, cKeterangan, username, userdate)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

        $params = [
            [ $dTgl_sql,          SQLSRV_PARAM_IN, SQLSRV_PHPTYPE_STRING(SQLSRV_ENC_CHAR) ],
            [ $cNoOp,             SQLSRV_PARAM_IN, SQLSRV_PHPTYPE_STRING(SQLSRV_ENC_CHAR) ],
            [ $cCust,             SQLSRV_PARAM_IN, SQLSRV_PHPTYPE_STRING(SQLSRV_ENC_CHAR) ],
            [ $cItem,             SQLSRV_PARAM_IN, SQLSRV_PHPTYPE_STRING(SQLSRV_ENC_CHAR) ],
            [ intval($nQty),      SQLSRV_PARAM_IN, SQLSRV_PHPTYPE_INT                     ],
            [ (string)$nPenyebab, SQLSRV_PARAM_IN, SQLSRV_PHPTYPE_STRING(SQLSRV_ENC_CHAR) ],
            [ (string)$nMesin,    SQLSRV_PARAM_IN, SQLSRV_PHPTYPE_STRING(SQLSRV_ENC_CHAR) ],
            [ $cStatus,           SQLSRV_PARAM_IN, SQLSRV_PHPTYPE_STRING(SQLSRV_ENC_CHAR) ],  // Status → cRegu
            [ $cPic,              SQLSRV_PARAM_IN, SQLSRV_PHPTYPE_STRING(SQLSRV_ENC_CHAR) ],
            [ $cKeterangan,       SQLSRV_PARAM_IN, SQLSRV_PHPTYPE_STRING(SQLSRV_ENC_CHAR) ],
            [ $username,          SQLSRV_PARAM_IN, SQLSRV_PHPTYPE_STRING(SQLSRV_ENC_CHAR) ],
            [ $userdate_sql,      SQLSRV_PARAM_IN, SQLSRV_PHPTYPE_STRING(SQLSRV_ENC_CHAR) ],
        ];

        $stmt = sqlsrv_query($conn, $sql, $params);

        if ($stmt === false) {
            $sqle = sqlsrv_errors();
            error_log('proxy_qc INSERT failed. params=' . json_encode([
                'dTgl'        => $dTgl_sql,
                'cNoOp'       => $cNoOp,
                'cCust'       => $cCust,
                'cItem'       => $cItem,
                'nQty'        => intval($nQty),
                'nPenyebab'   => $nPenyebab,
                'nMesin'      => $nMesin,
                'cRegu'       => $cStatus,
                'cPic'        => $cPic,
                'cKeterangan' => $cKeterangan,
                'username'    => $username,
                'userdate'    => $userdate_sql,
            ]) . ' | SQL errors=' . print_r($sqle, true));

            // Return the actual SQL error in the message to help diagnose
            $sqlMsg = '';
            if (is_array($sqle) && !empty($sqle)) {
                $sqlMsg = $sqle[0]['message'] ?? '';
            }
            echo json_encode(['success' => false, 'message' => 'Insert gagal: ' . $sqlMsg]);
            exit;
        }

        sqlsrv_free_stmt($stmt);
        sqlsrv_close($conn);

        echo json_encode(['success' => true, 'message' => 'Label QC berhasil disimpan']);

    } catch (Exception $e) {
        error_log('proxy_qc exception: ' . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
    }
    exit;
}

// ─── Method not allowed ───────────────────────────────────────────────────────
http_response_code(405);
echo json_encode(['success' => false, 'message' => 'Method not allowed']);
