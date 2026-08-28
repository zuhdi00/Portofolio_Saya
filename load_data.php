<?php
// load_data.php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$filePath = 'R:/250830.txt';

if (file_exists($filePath)) {
    $content = file_get_contents($filePath);
    $lines = explode("\n", $content);
    $data = [];
    
    foreach ($lines as $line) {
        if (trim($line)) {
            // Parse sesuai format data Anda
            $data[] = parseLine($line);
        }
    }
    
    echo json_encode($data);
} else {
    echo json_encode(['error' => 'File not found']);
}

function parseLine($line) {
    // Implementasi parsing sesuai format data Anda
    $parts = preg_split('/\s+/', trim($line));
    return [
        'seq' => $parts[0] ?? '',
        'customer' => $parts[1] ?? '',
        'location' => $parts[2] ?? '',
        // ... dst
    ];
}
?>