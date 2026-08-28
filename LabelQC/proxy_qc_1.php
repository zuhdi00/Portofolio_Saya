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
        "Database"           => "dbSopanusa",
        "Uid"                => "sa",
        "PWD"                => "supracor",
        "LoginTimeout"       => 15,
        "Encrypt"            => false,
        "TrustServerCertificate" => true
    ];
    $conn = sqlsrv_connect($serverName, $opts);
    if (!$conn) {
        throw new Exception("Connection failed: " . print_r(sqlsrv_errors(), true));
    }
    return $conn;
}

// ─── GET: ambil dropdown ──────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = $_GET['action'] ?? '';

    try {
        $conn = getConn();

        if ($action === 'get_penyebab') {
            // Ambil daftar penyebab dari tabel tbMU, field cMU
            $sql  = "SELECT cMU FROM tbMU ORDER BY cMU";
            $stmt = sqlsrv_query($conn, $sql);
            if ($stmt === false) {
                throw new Exception("Query tbMU failed: " . print_r(sqlsrv_errors(), true));
            }
            $rows = [];
            while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
                $rows[] = $row;
            }
            sqlsrv_free_stmt($stmt);
            sqlsrv_close($conn);
            echo json_encode(['success' => true, 'data' => $rows]);

        } elseif ($action === 'get_mesin') {
            // Ambil daftar mesin dari tabel tbMesin, field cNama
            $sql  = "SELECT cNama FROM tbMesin ORDER BY cNama";
            $stmt = sqlsrv_query($conn, $sql);
            if ($stmt === false) {
                throw new Exception("Query tbMesin failed: " . print_r(sqlsrv_errors(), true));
            }
            $rows = [];
            while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
                $rows[] = $row;
            }
            sqlsrv_free_stmt($stmt);
            sqlsrv_close($conn);
            echo json_encode(['success' => true, 'data' => $rows]);

        } else {
            sqlsrv_close($conn);
            echo json_encode(['success' => false, 'message' => 'Unknown action']);
        }

    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

// ─── POST: simpan ke tbLabelQc ────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);

    $action      = $input['action']      ?? '';
    $dTgl        = $input['dTgl']        ?? '';   // tanggal cetak  → dTgl
    $cNoOp       = $input['cNoOp']       ?? '';   // No OP          → cNoOp
    $cCust       = $input['cCust']       ?? '';   // Pemesan        → cCust
    $cItem       = $input['cItem']       ?? '';   // Nama Barang    → cItem
    $nQtyOrder   = $input['nQtyOrder']   ?? 0;    // Jumlah Order   → cCust (sesuai spec, tapi kita simpan di kolom terpisah jika ada, fallback ke cCust)
    $nQty        = $input['nQty']        ?? 0;    // Jumlah Defect  → nQty
    $nPenyebab   = $input['nPenyebab']   ?? '';   // Penyebab       → nPenyebab
    $nMesin      = $input['nMesin']      ?? '';   // Mesin          → nMesin
    $cRegu       = $input['cRegu']       ?? '';   // Regu           → cRegu
    $cPic        = $input['cPic']        ?? '';   // PIC QC         → cPic
    $cStatus     = $input['cStatus']     ?? '';   // Status         → cRegu (sesuai spec: status dikirim ke cRegu)
    $cKeterangan = $input['cKeterangan'] ?? '';   // Keterangan     → cKeterangan
    $username    = $input['username']    ?? '';   // hidden          → username
    $userdate    = $input['userdate']    ?? '';   // hidden          → userdate

    if ($action !== 'save_label_qc') {
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
        exit;
    }

    if (!$cNoOp) {
        echo json_encode(['success' => false, 'message' => 'No OP tidak boleh kosong']);
        exit;
    }

    try {
        $conn = getConn();

        // Normalize date strings to SQL-friendly format (YYYY-MM-DD)
        $normalize = function($s) {
            if (!$s) return null;
            if ($s instanceof DateTime) return $s->format('Y-m-d');
            $s = trim($s);
            // try common formats
            $ts = strtotime(str_replace('/', '-', $s));
            if ($ts !== false && $ts > 0) return date('Y-m-d', $ts);
            // fallback: attempt to match DD-MM-YYYY
            if (preg_match('/^(\d{2})-(\d{2})-(\d{4})$/', $s, $m)) {
                return "$m[3]-$m[2]-$m[1]"; // YYYY-MM-DD
            }
            return null;
        };

        $dTgl_sql = $normalize($dTgl) ?: $dTgl;
        $userdate_sql = $normalize($userdate) ?: $userdate;

        // INSERT ke tabel tbLabelQc
        // Catatan: Status dikirim ke field cRegu sesuai spesifikasi
        $sql = "INSERT INTO tbLabelQc 
                    (dTgl, cNoOp, cCust, cItem, nQty, nPenyebab, nMesin, cRegu, cPic, cKeterangan, username, userdate)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

        $params = [
            $dTgl_sql,
            $cNoOp,
            $cCust,
            $cItem,
            intval($nQty),
            $nPenyebab,
            $nMesin,
            $cStatus,       // Status → cRegu sesuai spec
            $cPic,
            $cKeterangan,
            $username,
            $userdate_sql
        ];

        $stmt = sqlsrv_query($conn, $sql, $params);

        if ($stmt === false) {
            $sqle = sqlsrv_errors();
            // Log parameter values and SQL errors for debugging (server-side only)
            error_log('LabelQC insert failed. params=' . json_encode($params, JSON_UNESCAPED_SLASHES) . ' errors=' . print_r($sqle, true));
            throw new Exception("Insert failed. See server logs.");
        }

        sqlsrv_free_stmt($stmt);
        sqlsrv_close($conn);

        echo json_encode(['success' => true, 'message' => 'Label QC berhasil disimpan']);

    } catch (Exception $e) {
        // Log exception for admins
        error_log('proxy_qc exception: ' . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Server error while saving label. Please contact administrator.']);
    }
    exit;
}

// ─── Method not allowed ───────────────────────────────────────────────────────
http_response_code(405);
echo json_encode(['success' => false, 'message' => 'Method not allowed']);
