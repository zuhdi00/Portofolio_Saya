<?php
/**
 * Test Database Connection
 */

header('Content-Type: text/html; charset=utf-8');

try {
    $serverName = "spsdmz";
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
        echo '<span class="error">❌ DATABASE CONNECTION FAILED</span>';
        echo '<div class="code">Error Details:' . print_r(sqlsrv_errors(), true) . '</div>';
    } else {
        echo '<span class="success">✅ DATABASE CONNECTION SUCCESS</span>';
        echo '<p>Server: ' . $serverName . '</p>';
        echo '<p>Database: dbSopanusa</p>';
        
        // Test tbOP table exists
        $sql = "SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_NAME='tbOP'";
        $stmt = sqlsrv_query($conn, $sql);
        if ($stmt && sqlsrv_fetch_array($stmt)) {
            echo '<p><span class="success">✓ Table tbOP EXISTS</span></p>';
        } else {
            echo '<p><span class="error">✗ Table tbOP NOT FOUND</span></p>';
        }
        
        sqlsrv_close($conn);
    }
} catch (Exception $e) {
    echo '<span class="error">❌ ERROR: ' . $e->getMessage() . '</span>';
}
?>
