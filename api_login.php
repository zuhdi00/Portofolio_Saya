<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    echo json_encode(['success' => false, 'message' => 'Invalid JSON input']);
    exit();
}

$username = isset($input['username']) ? trim($input['username']) : '';
$password = isset($input['password']) ? $input['password'] : '';
$remember = isset($input['remember']) ? $input['remember'] : false;

if (empty($username) || empty($password)) {
    echo json_encode(['success' => false, 'message' => 'Username dan password harus diisi']);
    exit();
}

try {
    $serverName = "spsdmz";
    $connectionOptions = [
        "Database" => "dbSopanusa",
        "Uid" => "sa",
        "PWD" => "supracor",
        "LoginTimeout" => 15,
        "Encrypt" => false,
        "TrustServerCertificate" => true
    ];

    $conn = sqlsrv_connect($serverName, $connectionOptions);

    if (!$conn) {
        throw new Exception("Connection failed: " . print_r(sqlsrv_errors(), true));
    }

    $sql = "SELECT 
                cNamaus, 
                cPassword, 
                cGroup, 
                nLevel, 
                cNoUser, 
                Aktif,
                FiturMP,
                UserId,
                UserDate,
                ComputerName,
                cUserComp,
                AppName
            FROM tbUserV2 
            WHERE LTRIM(RTRIM(UPPER(cNamaus))) = UPPER(?) AND Aktif = 1";

    $params = array(trim($username));
    $stmt = sqlsrv_query($conn, $sql, $params);

    if ($stmt === false) {
        throw new Exception("Query execution failed: " . print_r(sqlsrv_errors(), true));
    }

    $user = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);

    if (!$user) {
        $debugSql = "SELECT cNamaus, Aktif FROM tbUserV2 WHERE LTRIM(RTRIM(UPPER(cNamaus))) = UPPER(?)";
        $debugStmt = sqlsrv_query($conn, $debugSql, array(trim($username)));
        $debugUser = sqlsrv_fetch_array($debugStmt, SQLSRV_FETCH_ASSOC);
        
        if ($debugUser && $debugUser['Aktif'] != 1) {
            echo json_encode(['success' => false, 'message' => 'User tidak aktif']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Username tidak ditemukan']);
        }
        exit();
    }

    $storedPassword = trim($user['cPassword']);
    $inputPassword = trim($password);
    
    if ($inputPassword !== $storedPassword) {
        error_log("Password mismatch for user: " . $username . 
                 " | Stored: '" . $storedPassword . "' (" . strlen($storedPassword) . " chars)" .
                 " | Input: '" . $inputPassword . "' (" . strlen($inputPassword) . " chars)");
        echo json_encode(['success' => false, 'message' => 'Password salah']);
        exit();
    }

    $token = bin2hex(random_bytes(32));
    $expires_at = date('Y-m-d H:i:s', strtotime('+24 hours'));

    $checkTableSql = "IF NOT EXISTS (SELECT * FROM sysobjects WHERE name='user_sessions' AND xtype='U')
                      CREATE TABLE user_sessions (
                          session_id INT IDENTITY(1,1) PRIMARY KEY,
                          username NVARCHAR(50) NOT NULL,
                          token NVARCHAR(64) NOT NULL UNIQUE,
                          expires_at DATETIME2 NOT NULL,
                          created_at DATETIME2 DEFAULT GETDATE()
                      )";
    sqlsrv_query($conn, $checkTableSql);

    $cleanupSql = "DELETE FROM user_sessions WHERE expires_at < GETDATE()";
    sqlsrv_query($conn, $cleanupSql);

    $insertTokenSql = "INSERT INTO user_sessions (username, token, expires_at, created_at) VALUES (?, ?, ?, GETDATE())";
    $tokenParams = array($user['cNamaus'], $token, $expires_at);
    $tokenStmt = sqlsrv_query($conn, $insertTokenSql, $tokenParams);

    if ($tokenStmt === false) {
        throw new Exception("Token storage failed: " . print_r(sqlsrv_errors(), true));
    }
    $updateLoginSql = "UPDATE tbUserV2 SET UserDate = GETDATE(), ComputerName = ? WHERE cNamaus = ?";
    $updateParams = array($_SERVER['REMOTE_ADDR'], $user['cNamaus']);
    sqlsrv_query($conn, $updateLoginSql, $updateParams);

    $userData = array(
        'username' => $user['cNamaus'],
        'group' => $user['cGroup'],
        'level' => $user['nLevel'],
        'user_code' => $user['cNoUser'],
        'computer_name' => $user['ComputerName'],
        'app_name' => $user['AppName'],
        'login_time' => date('Y-m-d H:i:s')
    );

    echo json_encode([
        'success' => true,
        'message' => 'Login berhasil',
        'token' => $token,
        'user' => $userData,
        'expires_at' => $expires_at
    ]);

    sqlsrv_close($conn);

} catch (Exception $e) {
    error_log("Login error: " . $e->getMessage());
    
    echo json_encode([
        'success' => false, 
        'message' => 'Terjadi kesalahan sistem. Silakan coba lagi.'
    ]);
}
?>