<?php
/**
 * proxy_penyebab.php
 * Backend CRUD untuk tabel tbPenyebab (field: cSebab)
 * Mengikuti pola proxy_qc.php — koneksi SQL Server via sqlsrv
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit;
}

// ─── DB Connection (sama dengan proxy_qc.php) ────────────────────────────────
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

// ─── Safe trim & max-length guard ────────────────────────────────────────────
function safeSebab($val, $maxLen = 100) {
    $v = trim((string)($val ?? ''));
    if (mb_strlen($v) > $maxLen) {
        $v = mb_substr($v, 0, $maxLen);
    }
    return $v;
}

// ─── GET ──────────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = $_GET['action'] ?? '';

    if ($action !== 'get_all') {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Unknown action. Gunakan ?action=get_all']);
        exit;
    }

    try {
        $conn = getConn();
        $sql  = "SELECT cSebab FROM tbPenyebab ORDER BY cSebab";
        $stmt = sqlsrv_query($conn, $sql);
        if ($stmt === false) {
            throw new Exception("Query tbPenyebab failed: " . print_r(sqlsrv_errors(), true));
        }
        $rows = [];
        while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $rows[] = ['cSebab' => $row['cSebab']];
        }
        sqlsrv_free_stmt($stmt);
        sqlsrv_close($conn);

        echo json_encode(['success' => true, 'data' => $rows]);

    } catch (Exception $e) {
        error_log('proxy_penyebab GET exception: ' . $e->getMessage());
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

// ─── POST ─────────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);

    if (!is_array($input)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Payload JSON tidak valid']);
        exit;
    }

    $action = trim($input['action'] ?? '');

    // ── INSERT ──────────────────────────────────────────────────────────────
    if ($action === 'insert') {
        $cSebab = safeSebab($input['cSebab'] ?? '');

        if ($cSebab === '') {
            echo json_encode(['success' => false, 'message' => 'cSebab tidak boleh kosong']);
            exit;
        }

        try {
            $conn = getConn();

            // Cek duplikat (case-insensitive agar konsisten)
            $stmtCek = sqlsrv_query($conn,
                "SELECT COUNT(*) AS cnt FROM tbPenyebab WHERE cSebab = ?",
                [[ $cSebab, SQLSRV_PARAM_IN, SQLSRV_PHPTYPE_STRING(SQLSRV_ENC_CHAR) ]]
            );
            if ($stmtCek === false) throw new Exception("Cek duplikat gagal: " . print_r(sqlsrv_errors(), true));
            $rowCek = sqlsrv_fetch_array($stmtCek, SQLSRV_FETCH_ASSOC);
            sqlsrv_free_stmt($stmtCek);

            if ((int)$rowCek['cnt'] > 0) {
                sqlsrv_close($conn);
                echo json_encode(['success' => false, 'message' => "Penyebab \"$cSebab\" sudah ada."]);
                exit;
            }

            $stmt = sqlsrv_query($conn,
                "INSERT INTO tbPenyebab (cSebab) VALUES (?)",
                [[ $cSebab, SQLSRV_PARAM_IN, SQLSRV_PHPTYPE_STRING(SQLSRV_ENC_CHAR) ]]
            );
            if ($stmt === false) {
                $err = sqlsrv_errors();
                throw new Exception("Insert gagal: " . ($err[0]['message'] ?? print_r($err, true)));
            }
            sqlsrv_free_stmt($stmt);
            sqlsrv_close($conn);

            error_log("proxy_penyebab INSERT cSebab='$cSebab'");
            echo json_encode(['success' => true, 'message' => 'Penyebab berhasil ditambahkan']);

        } catch (Exception $e) {
            error_log('proxy_penyebab INSERT exception: ' . $e->getMessage());
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }

    // ── UPDATE ──────────────────────────────────────────────────────────────
    if ($action === 'update') {
        $cSebabLama = safeSebab($input['cSebabLama'] ?? '');
        $cSebabBaru = safeSebab($input['cSebabBaru'] ?? '');

        if ($cSebabLama === '') {
            echo json_encode(['success' => false, 'message' => 'cSebabLama (nilai lama) tidak boleh kosong']);
            exit;
        }
        if ($cSebabBaru === '') {
            echo json_encode(['success' => false, 'message' => 'cSebabBaru (nilai baru) tidak boleh kosong']);
            exit;
        }
        if ($cSebabLama === $cSebabBaru) {
            echo json_encode(['success' => true, 'message' => 'Tidak ada perubahan']);
            exit;
        }

        try {
            $conn = getConn();

            // Cek nilai lama memang ada
            $stmtCekLama = sqlsrv_query($conn,
                "SELECT COUNT(*) AS cnt FROM tbPenyebab WHERE cSebab = ?",
                [[ $cSebabLama, SQLSRV_PARAM_IN, SQLSRV_PHPTYPE_STRING(SQLSRV_ENC_CHAR) ]]
            );
            if ($stmtCekLama === false) throw new Exception("Cek data lama gagal: " . print_r(sqlsrv_errors(), true));
            $rowLama = sqlsrv_fetch_array($stmtCekLama, SQLSRV_FETCH_ASSOC);
            sqlsrv_free_stmt($stmtCekLama);
            if ((int)$rowLama['cnt'] === 0) {
                sqlsrv_close($conn);
                echo json_encode(['success' => false, 'message' => "Data \"$cSebabLama\" tidak ditemukan."]);
                exit;
            }

            // Cek duplikat pada nilai baru
            $stmtCekBaru = sqlsrv_query($conn,
                "SELECT COUNT(*) AS cnt FROM tbPenyebab WHERE cSebab = ?",
                [[ $cSebabBaru, SQLSRV_PARAM_IN, SQLSRV_PHPTYPE_STRING(SQLSRV_ENC_CHAR) ]]
            );
            if ($stmtCekBaru === false) throw new Exception("Cek duplikat baru gagal: " . print_r(sqlsrv_errors(), true));
            $rowBaru = sqlsrv_fetch_array($stmtCekBaru, SQLSRV_FETCH_ASSOC);
            sqlsrv_free_stmt($stmtCekBaru);
            if ((int)$rowBaru['cnt'] > 0) {
                sqlsrv_close($conn);
                echo json_encode(['success' => false, 'message' => "Penyebab \"$cSebabBaru\" sudah ada."]);
                exit;
            }

            $stmt = sqlsrv_query($conn,
                "UPDATE tbPenyebab SET cSebab = ? WHERE cSebab = ?",
                [
                    [ $cSebabBaru, SQLSRV_PARAM_IN, SQLSRV_PHPTYPE_STRING(SQLSRV_ENC_CHAR) ],
                    [ $cSebabLama, SQLSRV_PARAM_IN, SQLSRV_PHPTYPE_STRING(SQLSRV_ENC_CHAR) ]
                ]
            );
            if ($stmt === false) {
                $err = sqlsrv_errors();
                throw new Exception("Update gagal: " . ($err[0]['message'] ?? print_r($err, true)));
            }
            $affected = sqlsrv_rows_affected($stmt);
            sqlsrv_free_stmt($stmt);
            sqlsrv_close($conn);

            if ($affected === 0) {
                echo json_encode(['success' => false, 'message' => "Tidak ada baris yang diubah."]);
            } else {
                error_log("proxy_penyebab UPDATE '$cSebabLama' -> '$cSebabBaru'");
                echo json_encode(['success' => true, 'message' => 'Penyebab berhasil diubah']);
            }

        } catch (Exception $e) {
            error_log('proxy_penyebab UPDATE exception: ' . $e->getMessage());
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }

    // ── DELETE ──────────────────────────────────────────────────────────────
    if ($action === 'delete') {
        $cSebab = safeSebab($input['cSebab'] ?? '');

        if ($cSebab === '') {
            echo json_encode(['success' => false, 'message' => 'cSebab tidak boleh kosong']);
            exit;
        }

        try {
            $conn = getConn();

            $stmt = sqlsrv_query($conn,
                "DELETE FROM tbPenyebab WHERE cSebab = ?",
                [[ $cSebab, SQLSRV_PARAM_IN, SQLSRV_PHPTYPE_STRING(SQLSRV_ENC_CHAR) ]]
            );
            if ($stmt === false) {
                $err = sqlsrv_errors();
                throw new Exception("Delete gagal: " . ($err[0]['message'] ?? print_r($err, true)));
            }
            $affected = sqlsrv_rows_affected($stmt);
            sqlsrv_free_stmt($stmt);
            sqlsrv_close($conn);

            if ($affected === 0) {
                echo json_encode(['success' => false, 'message' => "Data \"$cSebab\" tidak ditemukan."]);
            } else {
                error_log("proxy_penyebab DELETE cSebab='$cSebab'");
                echo json_encode(['success' => true, 'message' => 'Penyebab berhasil dihapus']);
            }

        } catch (Exception $e) {
            error_log('proxy_penyebab DELETE exception: ' . $e->getMessage());
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }

    // ── Unknown action ───────────────────────────────────────────────────────
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => "Action '$action' tidak dikenal. Gunakan: insert | update | delete"]);
    exit;
}

// ─── Method not allowed ───────────────────────────────────────────────────────
http_response_code(405);
echo json_encode(['success' => false, 'message' => 'Method not allowed']);
