<?php
/**
 * Check Table Column Structure
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
        echo '<span class="error">Database connection failed</span>';
        exit;
    }

    $sql = "SELECT COLUMN_NAME, DATA_TYPE, IS_NULLABLE FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME='tbLabelQc' ORDER BY ORDINAL_POSITION";
    $stmt = sqlsrv_query($conn, $sql);

    if ($stmt) {
        echo '<p><strong>Columns in tbLabelQc table:</strong></p>';
        echo '<table border="1" cellpadding="10" style="border-collapse: collapse; width: 100%;">';
        echo '<tr style="background: #f0f0f0;"><th>Column Name</th><th>Data Type</th><th>Nullable</th></tr>';
        
        while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            echo '<tr>';
            echo '<td><strong>' . $row['COLUMN_NAME'] . '</strong></td>';
            echo '<td>' . $row['DATA_TYPE'] . '</td>';
            echo '<td>' . $row['IS_NULLABLE'] . '</td>';
            echo '</tr>';
        }
        echo '</table>';
    } else {
        echo '<span class="error">Query failed: ' . print_r(sqlsrv_errors(), true) . '</span>';
    }

    echo '<p><strong>Sample Rows in tbLabelQc:</strong></p>';
    $sql2 = "SELECT TOP 5 * FROM tbLabelQc";
    $stmt2 = sqlsrv_query($conn, $sql2);
    if ($stmt2) {
        while ($row = sqlsrv_fetch_array($stmt2, SQLSRV_FETCH_ASSOC)) {
            echo '<pre>' . print_r($row, true) . '</pre>';
        }
    } else {
        echo 'Query 2 failed.';
    }

    sqlsrv_close($conn);
} catch (Exception $e) {
    echo '<span class="error">ERROR: ' . $e->getMessage() . '</span>';
}
?>
