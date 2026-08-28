isi verify_token.php
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
    echo json_encode(['valid' => false, 'message' => 'Method not allowed']);
    exit();
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['token'])) {
    echo json_encode(['valid' => false, 'message' => 'Token is required']);
    exit();
}

$token = trim($input['token']);

if (empty($token)) {
    echo json_encode(['valid' => false, 'message' => 'Token cannot be empty']);
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

    
    $cleanupSql = "DELETE FROM user_sessions WHERE expires_at < GETDATE()";
    sqlsrv_query($conn, $cleanupSql);

    
    $sql = "SELECT 
                us.username, 
                us.expires_at,
                us.created_at,
                u.cNamaus,
                u.cGroup,
                u.nLevel,
                u.cNoUser,
                u.ComputerName,
                u.AppName
            FROM user_sessions us
            INNER JOIN tbUserV2 u ON us.username = u.cNamaus
            WHERE us.token = ? AND us.expires_at > GETDATE() AND u.Aktif = 1";

    $params = array($token);
    $stmt = sqlsrv_query($conn, $sql, $params);

    if ($stmt === false) {
        throw new Exception("Query execution failed: " . print_r(sqlsrv_errors(), true));
    }

    $result = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);

    if ($result) {
        // Token is valid
        $userData = array(
            'username' => $result['cNamaus'],
            'group' => $result['cGroup'],
            'level' => $result['nLevel'],
            'user_code' => $result['cNoUser'],
            'computer_name' => $result['ComputerName'],
            'app_name' => $result['AppName']