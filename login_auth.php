<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

// Get JSON input
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
    // Database connection using your existing settings
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

    // Query your existing tbUserV2 table
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
            WHERE cNamaus = ? AND Aktif = 1";

    $params = array($username);
    $stmt = sqlsrv_query($conn, $sql, $params);

    if ($stmt === false) {
        throw new Exception("Query execution failed: " . print_r(sqlsrv_errors(), true));
    }

    $user = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);

    if (!$user) {
        echo json_encode(['success' => false, 'message' => 'Username atau password salah']);
        exit();
    }

    
    if ($password !== $user['cPassword']) {
        echo json_encode(['success' => false, 'message' => 'Username atau password salah']);
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

    // Store token in database
    $insertTokenSql = "INSERT INTO user_sessions (username, token, expires_at, created_at) VALUES (?, ?, ?, GETDATE())";
    $tokenParams = array($user['cNamaus'], $token, $expires_at);
    $tokenStmt = sqlsrv_query($conn, $insertTokenSql, $tokenParams);

    if ($tokenStmt === false) {
        throw new Exception("Token storage failed: " . print_r(sqlsrv_errors(), true));
    }

    // Update last login in tbUserV2
    $updateLoginSql = "UPDATE tbUserV2 SET UserDate = GETDATE() WHERE cNamaus = ?";
    $updateParams = array($user['cNamaus']);
    sqlsrv_query($conn, $updateLoginSql, $updateParams);

    // Prepare user data (exclude sensitive information)
    $userData = array(
        'username' => $user['cNamaus'],
        'group' => $user['cGroup'],
        'level' => $user['nLevel'],
        'user_code' => $user['cNoUser'],
        'computer_name' => $user['ComputerName'],
        'app_name' => $user['AppName']
    );

    // Success response
    echo json_encode([
        'success' => true,
        'message' => 'Login berhasil',
        'token' => $token,
        'user' => $userData,
        'expires_at' => $expires_at
    ]);

    sqlsrv_close($conn);

} catch (Exception $e) {
    // Log error (in production, log to file instead of exposing to client)
    error_log("Login error: " . $e->getMessage());
    
    echo json_encode([
        'success' => false, 
        'message' => 'Terjadi kesalahan sistem. Silakan coba lagi.'
    ]);
}
?>