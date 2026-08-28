<?php
// Enable error reporting for debugging (disable in production)
error_reporting(E_ALL);
ini_set('display_errors', 0); // Set to 0 in production

// CORS and Content-Type headers
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: https://supracor.co.id'); // More specific than *
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
header('Access-Control-Allow-Credentials: true');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'valid' => false, 
        'message' => 'Method not allowed',
        'code' => 'METHOD_NOT_ALLOWED'
    ]);
    exit();
}

// Get and validate JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['token'])) {
    echo json_encode([
        'valid' => false, 
        'message' => 'Token not provided',
        'code' => 'TOKEN_MISSING'
    ]);
    exit();
}

$token = trim($input['token']);

// Basic token validation
if (empty($token) || strlen($token) !== 64 || !ctype_xdigit($token)) {
    echo json_encode([
        'valid' => false, 
        'message' => 'Invalid token format',
        'code' => 'INVALID_TOKEN_FORMAT'
    ]);
    exit();
}

try {
    // Database connection configuration
    $serverName = "spsdmz";
    $connectionOptions = [
        "Database" => "dbSopanusa",
        "Uid" => "sa",
        "PWD" => "supracor",
        "LoginTimeout" => 15,
        "Encrypt" => false,
        "TrustServerCertificate" => true,
        "CharacterSet" => "UTF-8"
    ];

    $conn = sqlsrv_connect($serverName, $connectionOptions);

    if (!$conn) {
        $errors = sqlsrv_errors();
        error_log("Database connection failed: " . print_r($errors, true));
        throw new Exception("Database connection failed");
    }

    // Clean up expired sessions first
    $cleanupSql = "DELETE FROM user_sessions WHERE expires_at < GETDATE() OR is_active = 0";
    sqlsrv_query($conn, $cleanupSql);

    // Check if token exists, is not expired, and user is still active
    $sql = "SELECT  
                s.session_id,
                s.user_name,  
                s.expires_at,
                s.created_at,
                s.ip_address,
                u.cNamaus, 
                u.cGroup, 
                u.nLevel, 
                u.Aktif, 
                u.FiturMP, 
                u.cNoUser,
                u.AppName,
                u.ComputerName
            FROM user_sessions s 
            INNER JOIN tbUserV2 u ON s.user_name = u.cNamaus 
            WHERE s.token = ? 
                AND s.expires_at > GETDATE() 
                AND s.is_active = 1 
                AND u.Aktif = 1";

    $params = array($token);
    $stmt = sqlsrv_query($conn, $sql, $params);

    if ($stmt === false) {
        $errors = sqlsrv_errors();
        error_log("Token query failed: " . print_r($errors, true));
        throw new Exception("Token query failed");
    }

    $session = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);

    if ($session) {
        // Get client information for security check
        $current_ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        
        // Optional: Check if IP address matches (uncomment if needed)
        // if ($session['ip_address'] !== $current_ip) {
        //     error_log("IP mismatch for token: stored={$session['ip_address']}, current={$current_ip}");
        // }

        // Token is valid, extend expiration based on original session type
        $session_age = time() - strtotime($session['created_at']->format('Y-m-d H:i:s'));
        $is_remember_session = $session_age > 86400; // More than 24 hours suggests remember me
        
        $extension_time = $is_remember_session ? '+30 days' : '+24 hours';
        $newExpiration = date('Y-m-d H:i:s', strtotime($extension_time));
        
        $updateSql = "UPDATE user_sessions SET expires_at = ? WHERE token = ?";
        $updateParams = array($newExpiration, $token);
        $updateResult = sqlsrv_query($conn, $updateSql, $updateParams);

        if ($updateResult === false) {
            error_log("Failed to update token expiration: " . print_r(sqlsrv_errors(), true));
        }

        // Update user's last activity
        $updateUserSql = "UPDATE tbUserV2 SET UserDate = GETDATE() WHERE cNamaus = ?";
        $updateUserParams = array($session['cNamaus']);
        sqlsrv_query($conn, $updateUserSql, $updateUserParams);

        // Return success response
        echo json_encode([
            'valid' => true,
            'message' => 'Token valid',
            'user' => array(
                'cNoUser' => $session['cNoUser'],
                'username' => $session['cNamaus'],
                'cGroup' => $session['cGroup'],
                'nLevel' => (int)$session['nLevel'],
                'FiturMP' => $session['FiturMP'],
                'AppName' => $session['AppName'],
                'ComputerName' => $session['ComputerName']
            ),
            'expires_at' => $newExpiration,
            'session_info' => array(
                'created_at' => $session['created_at']->format('Y-m-d H:i:s'),
                'is_remember_session' => $is_remember_session
            )
        ]);

    } else {
        // Token is invalid, expired, or user is inactive
        // Clean up the specific token if it exists
        $cleanupTokenSql = "DELETE FROM user_sessions WHERE token = ?";
        $cleanupParams = array($token);
        sqlsrv_query($conn, $cleanupTokenSql, $cleanupParams);

        echo json_encode([
            'valid' => false, 
            'message' => 'Token expired or invalid',
            'code' => 'TOKEN_INVALID'
        ]);
    }

    sqlsrv_close($conn);

} catch (Exception $e) {
    // Log detailed error for debugging
    error_log("Token verification error: " . $e->getMessage() . " | File: " . $e->getFile() . " | Line: " . $e->getLine());
    
    // Return generic error to client for security
    echo json_encode([
        'valid' => false, 
        'message' => 'System error occurred',
        'code' => 'SYSTEM_ERROR'
    ]);
}

// Optional: Add logout endpoint
if (isset($input['action']) && $input['action'] === 'logout' && isset($input['token'])) {
    try {
        $conn = sqlsrv_connect($serverName, $connectionOptions);
        if ($conn) {
            // Invalidate the specific token
            $logoutSql = "UPDATE user_sessions SET is_active = 0 WHERE token = ?";
            $logoutParams = array($input['token']);
            sqlsrv_query($conn, $logoutSql, $logoutParams);
            
            echo json_encode([
                'success' => true,
                'message' => 'Logout successful'
            ]);
            
            sqlsrv_close($conn);
        }
    } catch (Exception $e) {
        error_log("Logout error: " . $e->getMessage());
        echo json_encode([
            'success' => false,
            'message' => 'Logout failed'
        ]);
    }
}
?>