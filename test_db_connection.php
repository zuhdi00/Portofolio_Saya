<?php
header('Content-Type: text/html; charset=utf-8');

echo "<h2>Testing Database Connection</h2>";

$serverName = "spsdmz2";
$connectionOptions = array(
    "Database" => "dbSopanusa",
    "Uid" => "sa",
    "PWD" => "supracor",
    "LoginTimeout" => 15,
    "Encrypt" => false,
    "TrustServerCertificate" => true
);

echo "<p>Server: " . $serverName . "</p>";
echo "<p>Database: dbSopanusa</p>";
echo "<p>Attempting to connect...</p>";

$conn = sqlsrv_connect($serverName, $connectionOptions);

if ($conn) {
    echo "<h3 style='color: green;'>✓ Connection SUCCESS!</h3>";
    
    // Test query
    $sql = "SELECT TOP 5 cNoOp FROM tbOP";
    $stmt = sqlsrv_query($conn, $sql);
    
    if ($stmt) {
        echo "<h3>First 5 OP numbers:</h3>";
        echo "<ul>";
        while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            echo "<li>" . $row['cNoOp'] . "</li>";
        }
        echo "</ul>";
        sqlsrv_free_stmt($stmt);
    } else {
        echo "<p style='color: red;'>Query failed: " . print_r(sqlsrv_errors(), true) . "</p>";
    }
    
    sqlsrv_close($conn);
} else {
    echo "<h3 style='color: red;'>✗ Connection FAILED!</h3>";
    $errors = sqlsrv_errors();
    echo "<pre>";
    print_r($errors);
    echo "</pre>";
}
?>
