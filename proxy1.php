<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *'); // Bisa disesuaikan jika ingin restrict
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    // Untuk preflight CORS
    exit;
}

/**
 * Cari dan baca ip_log.txt di lokasi public_html (prioritas) dan fallback ke lokasi lokal.
 * Mengembalikan string IP (valid IPv4) atau default jika tidak ditemukan.
 */
function getPublicIP() {
    $default = '180.251.123.55';

    // Prioritas: public_html/ip_log.txt dan public_html/corrlabel/ip_log.txt (DOCUMENT_ROOT)
    $host = $_SERVER['HTTP_HOST'] ?? 'supracor.co.id';
    $docRoot = rtrim($_SERVER['DOCUMENT_ROOT'] ?? '', DIRECTORY_SEPARATOR);

    $candidates = [
        $docRoot . '/ip_log.txt',
        $docRoot . '/corrlabel/ip_log.txt',
        __DIR__ . '/ip_log.txt',
        __DIR__ . '/corrlabel/ip_log.txt',
    ];

    foreach ($candidates as $path) {
        if (!$path) continue;
        if (is_readable($path)) {
            $lines = @file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            if ($lines && count($lines) > 0) {
                $lastLine = trim(end($lines));
                if (preg_match('/([\d]{1,3}(?:\.[\d]{1,3}){3})/', $lastLine, $m)) {
                    $ip = $m[1];
                    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                        return $ip;
                    }
                }
            }
        }
    }

    // Jika tidak ditemukan di filesystem, coba ambil via HTTP(S) dari domain (public_html)
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $remoteCandidates = [
        $scheme . '://' . $host . '/ip_log.txt',
        $scheme . '://' . $host . '/corrlabel/ip_log.txt'
    ];

    $opts = ['http' => ['timeout' => 3, 'ignore_errors' => true]];
    $ctx = stream_context_create($opts);

    foreach ($remoteCandidates as $url) {
        $contents = @file_get_contents($url, false, $ctx);
        if ($contents !== false) {
            $lines = preg_split('/\r\n|\r|\n/', trim($contents));
            if ($lines && count($lines) > 0) {
                $lastLine = trim(end($lines));
                if (preg_match('/([\d]{1,3}(?:\.[\d]{1,3}){3})/', $lastLine, $m)) {
                    $ip = $m[1];
                    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                        return $ip;
                    }
                }
            }
        }
    }

    // fallback ke default
    return $default;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Ambil data item berdasarkan barcode
    $barcode = $_GET['barcode'] ?? '';
    if (!$barcode) {
        echo json_encode(['success' => false, 'message' => 'No barcode provided']);
        exit;
    }

    $publicIP = getPublicIP();
    $url = "http://$publicIP:8081/api_get_item_detail_v2.php?barcode=" . urlencode($barcode);

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    if (curl_errno($ch)) {
        echo json_encode(['success' => false, 'message' => 'Curl Error: ' . curl_error($ch)]);
    } else {
        http_response_code($httpCode);
        echo $response;
    }

    curl_close($ch);

} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Simpan jumlah koli dan pallet
    $input = file_get_contents('php://input');
    $publicIP = getPublicIP();
    $ch = curl_init("http://$publicIP:8081/api_save_pallet.php");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $input);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    if (curl_errno($ch)) {
        echo json_encode(['success' => false, 'message' => 'Curl Error: ' . curl_error($ch)]);
    } else {
        http_response_code($httpCode);
        echo $response;
    }

    curl_close($ch);

} else {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
}
