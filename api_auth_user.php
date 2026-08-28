<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!$data || !isset($data['username']) || !isset($data['password'])) {
    echo json_encode(['success' => false, 'message' => 'Username dan password harus diisi']);
    exit;
}

$username = trim($data['username']);
$password = trim($data['password']);

if (empty($username) || empty($password)) {
    echo json_encode(['success' => false, 'message' => 'Username dan password tidak boleh kosong']);
    exit;
}

try {
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
        $errors = sqlsrv_errors();
        $errorMessage = "Database connection failed";
        if ($errors) {
            $errorMessage .= ": " . $errors[0]['message'];
        }
        throw new Exception($errorMessage);
    }

    $sql = "SELECT 
                [cNamaus], 
                [cPassword], 
                [cGroup], 
                [nLevel], 
                [cNoUser], 
                [Aktif], 
                [FiturMP],
                [UserId],
                [UserDate],
                [ComputerName],
                [cUserComp],
                [AppName]
            FROM [dbSopanusa].[dbo].[tbUserV2] 
            WHERE [cNamaus] = ? AND [Aktif] = 1";

    $params = array($username);
    $stmt = sqlsrv_query($conn, $sql, $params);

    if ($stmt === false) {
        $errors = sqlsrv_errors();
        $errorMessage = "Query execution failed";
        if ($errors) {
            $errorMessage .= ": " . $errors[0]['message'];
        }
        throw new Exception($errorMessage);
    }

    if ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $storedPassword = trim($row['cPassword']);
        
        if ($password === $storedPassword) {
            $userData = array(
                'username' => $row['cNamaus'],
                'group' => $row['cGroup'],
                'level' => intval($row['nLevel']),
                'userNo' => $row['cNoUser'],
                'fiturMP' => $row['FiturMP'],
                'userId' => $row['UserId'],
                'userDate' => $row['UserDate'] ? $row['UserDate']->format('Y-m-d H:i:s') : null,
                'computerName' => $row['ComputerName'],
                'userComp' => $row['cUserComp'],
                'appName' => $row['AppName']
            );

            $updateSql = "UPDATE [dbSopanusa].[dbo].[tbUserV2] 
                         SET [UserDate] = GETDATE(), 
                             [ComputerName] = ? 
                         WHERE [cNamaus] = ?";
            
            $computerName = $_SERVER['REMOTE_ADDR'] ?? 'Web Browser';
            $updateParams = array($computerName, $username);
            $updateStmt = sqlsrv_query($conn, $updateSql, $updateParams);
            
            if ($updateStmt === false) {
                error_log("Failed to update user login info for: " . $username);
            }

            echo json_encode([
                'success' => true, 
                'message' => 'Login berhasil',
                'user' => $userData
            ]);
        } else {
            echo json_encode([
                'success' => false, 
                'message' => 'Username atau password salah'
            ]);
        }
    } else {
        echo json_encode([
            'success' => false, 
            'message' => 'Username tidak ditemukan atau tidak aktif'
        ]);
    }

    sqlsrv_free_stmt($stmt);
    sqlsrv_close($conn);

} catch (Exception $e) {
    error_log("Authentication error: " . $e->getMessage());
    
    echo json_encode([
        'success' => false, 
        'message' => 'Terjadi kesalahan pada server. Silakan coba lagi.'
    ]);
}
?>