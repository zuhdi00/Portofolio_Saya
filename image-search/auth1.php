<?php
// Configure session cookie parameters to ensure cookies work correctly
$secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443);
$cookieParams = session_get_cookie_params();
$samesite = $secure ? 'None' : 'Lax';
// Ensure cookie is available site-wide (path '/') so redirected pages receive it
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'domain' => $_SERVER['HTTP_HOST'] ?? ($cookieParams['domain'] ?? ''),
    'secure' => $secure,
    'httponly' => true,
    'samesite' => $samesite
]);
session_start();

// Konfigurasi credential (dapat diubah sesuai kebutuhan)
$VALID_USERS = array(
    'admin' => 'ziahaha00',
    'user' => 'production2026',
    'manager' => 'supracoredp'
);

// Fungsi untuk login
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    
    if ($action === 'login') {
        $username = isset($_POST['username']) ? trim($_POST['username']) : '';
        $password = isset($_POST['password']) ? trim($_POST['password']) : '';
        
        // Validasi username dan password
        header('Content-Type: application/json; charset=utf-8');
        if (isset($VALID_USERS[$username]) && $VALID_USERS[$username] === $password) {
            $_SESSION['logged_in'] = true;
            $_SESSION['username'] = $username;
            $_SESSION['login_time'] = time();

            // Regenerate session id to prevent fixation and ensure Set-Cookie header
            session_regenerate_id(true);

            echo json_encode([
                'success' => true,
                'message' => 'Login berhasil! Mengalihkan...',
                'username' => $username
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Username atau password salah!'
            ]);
        }
    } elseif ($action === 'logout') {
        // Destroy session and clear cookie
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        // Clear session array
        $_SESSION = [];
        // Clear session cookie if used
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, [
                'path' => $params['path'] ?? '/',
                'domain' => $params['domain'] ?? '',
                'secure' => $params['secure'] ?? false,
                'httponly' => $params['httponly'] ?? false,
                'samesite' => $samesite
            ]);
        }
        session_destroy();
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'message' => 'Logout berhasil'
        ]);
    }
    exit;
}

// Fungsi untuk cek status login
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action'])) {
    if ($_GET['action'] === 'check') {
        header('Content-Type: application/json');
        echo json_encode([
            'logged_in' => isset($_SESSION['logged_in']) && $_SESSION['logged_in'],
            'username' => isset($_SESSION['username']) ? $_SESSION['username'] : null
        ]);
    }
    exit;
}

// Redirect ke login jika tidak authenticated
if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
    header('Location: login.html');
    exit;
}
?>
