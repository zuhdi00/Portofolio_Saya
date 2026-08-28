<?php
// Lightweight standalone auth for image-search (dev/testing only)
header('Content-Type: application/json; charset=utf-8');

// Configure secure cookie params (skip setting domain for localhost)
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$secure = $scheme === 'https';
$host = $_SERVER['HTTP_HOST'] ?? '';
$domain = $host;
if (strpos($domain, ':') !== false) {
    $domain = explode(':', $domain)[0];
}
if ($domain === 'localhost' || $domain === '127.0.0.1') {
    $domain = '';
}

$cookieParams = [
    'lifetime' => 0,
    'path' => '/',
    'domain' => $domain ?: null,
    'secure' => $secure,
    'httponly' => true,
    'samesite' => $secure ? 'None' : 'Lax'
];
if (PHP_VERSION_ID >= 70300) {
    session_set_cookie_params($cookieParams);
} else {
    // fallback for older PHP: domain may be empty string
    session_set_cookie_params(0, '/', $domain ?: '', $secure, true);
}

session_start();

// Simple in-memory user list for local testing. Replace with DB + password_hash in production.
$users = [
    'admin' => 'admin123',
    'user'  => 'user123'
];

// Read action from GET/POST or JSON body
$action = $_GET['action'] ?? $_POST['action'] ?? null;
if (!$action && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $body = file_get_contents('php://input');
    $data = json_decode($body, true);
    if (isset($data['action'])) $action = $data['action'];
}

function jsonResponse($data, $code = 200) {
    http_response_code($code);
    echo json_encode($data);
    exit;
}

if ($action === 'login') {
    // accept JSON or form-encoded
    $username = $_POST['username'] ?? null;
    $password = $_POST['password'] ?? null;
    if (!$username || !$password) {
        $body = file_get_contents('php://input');
        $data = json_decode($body, true);
        if (is_array($data)) {
            $username = $data['username'] ?? $username;
            $password = $data['password'] ?? $password;
        }
    }
    $username = trim((string)$username);
    $password = (string)$password;

    if ($username === '' || $password === '') {
        jsonResponse(['success' => false, 'error' => 'Missing credentials'], 400);
    }

    global $users;
    if (isset($users[$username]) && $users[$username] === $password) {
        // valid
        $_SESSION['logged_in'] = true;
        $_SESSION['username'] = $username;
        session_regenerate_id(true);
        jsonResponse(['success' => true, 'username' => $username]);
    }

    jsonResponse(['success' => false, 'error' => 'Invalid username or password'], 401);
}

if ($action === 'logout') {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'] ?? '/', $params['domain'] ?? '', $params['secure'] ?? false, $params['httponly'] ?? true);
    }
    session_destroy();
    jsonResponse(['success' => true]);
}

if ($action === 'check') {
    $logged = !empty($_SESSION['logged_in']);
    jsonResponse(['logged_in' => $logged, 'username' => $logged ? ($_SESSION['username'] ?? null) : null]);
}

// default: show small info
jsonResponse(['info' => 'auth_new.php running', 'method' => $_SERVER['REQUEST_METHOD']]);

?>
