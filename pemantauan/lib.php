<?php
// Helper umum: koneksi SQL Server, JSON response, validasi.

$CFG = require __DIR__ . '/config.php';
date_default_timezone_set($CFG['timezone']);

function cfg($k) { global $CFG; return $CFG[$k] ?? null; }

function db(): PDO {
    static $pdo = null;
    if ($pdo) return $pdo;
    if (!in_array('sqlsrv', PDO::getAvailableDrivers(), true)) {
        fail(500, 'Driver pdo_sqlsrv belum aktif di PHP. Lihat README bagian instalasi driver.');
    }
    $dsn = 'sqlsrv:Server=' . cfg('db_server') . ';Database=' . cfg('db_name');
    if (cfg('db_encrypt') !== null) $dsn .= ';Encrypt=' . (cfg('db_encrypt') ? '1' : '0');
    if (cfg('db_trust_cert'))       $dsn .= ';TrustServerCertificate=1';
    $user = cfg('db_user') !== '' ? cfg('db_user') : null;   // null = Windows Authentication
    $pass = cfg('db_user') !== '' ? cfg('db_pass') : null;
    try {
        $pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    } catch (PDOException $e) {
        fail(500, 'Tidak dapat terhubung ke SQL Server: ' . $e->getMessage());
    }
    return $pdo;
}

function q(string $sql, array $params = []): PDOStatement {
    $st = db()->prepare($sql);
    foreach ($params as $k => $v) {
        $st->bindValue(is_int($k) ? $k + 1 : $k, $v,
            is_int($v) ? PDO::PARAM_INT : ($v === null ? PDO::PARAM_NULL : PDO::PARAM_STR));
    }
    $st->execute();
    return $st;
}

function out($data, int $code = 200): void {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}
function ok($data = []): void { out(['success' => true] + (is_array($data) ? $data : ['data' => $data])); }
function fail(int $code, string $msg): void { out(['success' => false, 'message' => $msg], $code); }

function body(): array {
    static $b = null;
    if ($b !== null) return $b;
    $ct = $_SERVER['CONTENT_TYPE'] ?? '';
    if (stripos($ct, 'application/json') !== false) {
        $b = json_decode(file_get_contents('php://input'), true) ?: [];
    } elseif (isset($_POST['data'])) {
        $b = json_decode($_POST['data'], true) ?: [];
    } else {
        $b = $_POST;
    }
    return $b;
}

function str($v, int $max): ?string {
    if ($v === null) return null;
    $v = trim((string)$v);
    if ($v === '') return null;
    return mb_substr($v, 0, $max);
}

function require_method(string $m): void {
    if ($_SERVER['REQUEST_METHOD'] !== $m) fail(405, "Gunakan method $m");
}

function require_admin(): void {
    $pin = $_SERVER['HTTP_X_ADMIN_PIN'] ?? '';
    if (!hash_equals((string)cfg('admin_pin'), (string)$pin)) {
        usleep(800000);                                  // perlambat tebak-tebakan password
        fail(403, 'Password salah.');
    }
}

function client_ip(): string {
    $f = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '';
    if ($f) return trim(explode(',', $f)[0]);
    return $_SERVER['REMOTE_ADDR'] ?? '';
}

function checklist(): array {
    return q("SELECT ItemKey AS [key], Label AS label FROM dbo.MonChecklistItem WHERE IsActive = 1 ORDER BY SortOrder, ItemKey")->fetchAll();
}
