<?php
/**
 * check_database_status.php
 * Diagnostic tool untuk check status database dan komponmen
 */

header('Content-Type: application/json');

$response = array(
    'sql_connection' => false,
    'table_exists' => false,
    'record_count' => 0,
    'csv_exists' => false,
    'errors' => array()
);

// ============================================================
// 1. CHECK SQL SERVER CONNECTION
// ============================================================
try {
    if (!file_exists('../config/koneksi_sqlsrv.php')) {
        $response['errors'][] = 'File koneksi_sqlsrv.php tidak ditemukan';
        echo json_encode($response);
        exit;
    }
    
    include '../config/koneksi_sqlsrv.php';   // $conn adalah koneksi SQL Server
    
    if (!$conn) {
        $response['errors'][] = 'Koneksi SQL Server gagal';
        echo json_encode($response);
        exit;
    }
    
    $response['sql_connection'] = true;
    
} catch (Exception $e) {
    $response['errors'][] = 'Exception: ' . $e->getMessage();
    echo json_encode($response);
    exit;
}

// ============================================================
// 2. CHECK TABLE EXISTENCE
// ============================================================
try {
    $sql = "SELECT COUNT(*) as cnt FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = 'dbo' AND TABLE_NAME = 'pegawai_lengkap'";
    $stmt = sqlsrv_query($conn, $sql);
    
    if ($stmt === false) {
        $errors = sqlsrv_errors();
        $response['errors'][] = 'Query table check failed: ' . $errors[0]['message'];
        echo json_encode($response);
        exit;
    }
    
    $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
    
    if ($row['cnt'] > 0) {
        $response['table_exists'] = true;
        
        // ============================================================
        // 3. GET RECORD COUNT
        // ============================================================
        try {
            $sql_count = "SELECT COUNT(*) as total FROM dbo.pegawai_lengkap";
            $stmt_count = sqlsrv_query($conn, $sql_count);
            
            if ($stmt_count !== false) {
                $row_count = sqlsrv_fetch_array($stmt_count, SQLSRV_FETCH_ASSOC);
                $response['record_count'] = (int)$row_count['total'];
                sqlsrv_free_stmt($stmt_count);
            }
        } catch (Exception $e) {
            $response['errors'][] = 'Error counting records: ' . $e->getMessage();
        }
    }
    
    sqlsrv_free_stmt($stmt);
    
} catch (Exception $e) {
    $response['errors'][] = 'Exception saat check table: ' . $e->getMessage();
}

// ============================================================
// 4. CHECK CSV FILE EXISTENCE
// ============================================================
$csv_path = '../database/DATA KARYAWAN (2).csv';
if (file_exists($csv_path)) {
    $response['csv_exists'] = true;
    
    // Get file info
    $filesize = filesize($csv_path);
    $response['csv_filesize'] = $filesize;
    $response['csv_formatted_size'] = formatBytes($filesize);
    
} else {
    $response['errors'][] = 'File CSV tidak ditemukan di: ' . $csv_path;
}

// Close connection
if ($conn) {
    sqlsrv_close($conn);
}

// ============================================================
// 5. SEND RESPONSE
// ============================================================
echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

/**
 * Format bytes ke readable format
 */
function formatBytes($bytes, $precision = 2) {
    $units = array('B', 'KB', 'MB', 'GB');
    
    for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
        $bytes /= 1024;
    }
    
    return round($bytes, $precision) . ' ' . $units[$i];
}
?>
