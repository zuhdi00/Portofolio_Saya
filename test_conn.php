<?php
echo "--- TESTING SQLSRV CONNECTION TO spsdmz2 ---\n";
$serverName = "spsdmz2";
$connectionOptions = [
    "Database" => "master",
    "Uid" => "sa",
    "PWD" => "supracor",
    "LoginTimeout" => 5,
    "Encrypt" => false,
    "TrustServerCertificate" => true
];
$conn = @sqlsrv_connect($serverName, $connectionOptions);
if ($conn) {
    echo "SQLSRV Connected successfully to spsdmz2!\n";
    $query = "SELECT name FROM sys.databases";
    $stmt = sqlsrv_query($conn, $query);
    if ($stmt) {
        echo "Databases in SQLSRV:\n";
        while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            echo " - " . $row['name'] . "\n";
        }
    } else {
        echo "Failed to query sys.databases\n";
    }
    sqlsrv_close($conn);
} else {
    echo "SQLSRV Connection to spsdmz2 failed:\n";
    print_r(sqlsrv_errors());
}

echo "\n--- TESTING MYSQL/MARIADB CONNECTION TO localhost (root, empty pass) ---\n";
try {
    $conn_my = @new mysqli("localhost", "root", "", "hris");
    if ($conn_my->connect_error) {
        echo "MySQL localhost connection failed: " . $conn_my->connect_error . "\n";
    } else {
        echo "MySQL Connected successfully to localhost (db: hris)!\n";
        $conn_my->close();
    }
} catch (Exception $e) {
    echo "MySQL localhost connection threw: " . $e->getMessage() . "\n";
}

echo "\n--- TESTING MYSQL/MARIADB CONNECTION TO localhost (sa, supracor) ---\n";
try {
    $conn_my = @new mysqli("localhost", "sa", "supracor", "hris");
    if ($conn_my->connect_error) {
        echo "MySQL localhost (sa) connection failed: " . $conn_my->connect_error . "\n";
    } else {
        echo "MySQL Connected successfully to localhost (sa, db: hris)!\n";
        $conn_my->close();
    }
} catch (Exception $e) {
    echo "MySQL localhost (sa) connection threw: " . $e->getMessage() . "\n";
}
