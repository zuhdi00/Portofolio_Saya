<?php
/**
 * Get Sample Data from tbOP
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

    // Get first 10 records
    $sql = "SELECT TOP 10 cNoOp, dTgl, nQty, cWarna, cRak FROM tbOP ORDER BY dTgl DESC";
    $stmt = sqlsrv_query($conn, $sql);

    if ($stmt) {
        $count = 0;
        echo '<p><strong>Sample Data from tbOP (Latest 10):</strong></p>';
        
        if (sqlsrv_has_rows($stmt)) {
            echo '<table border="1" cellpadding="8" style="border-collapse: collapse; width: 100%; font-size: 12px;">';
            echo '<tr style="background: #f0f0f0;"><th>cNoOp</th><th>Tanggal</th><th>Qty</th><th>Warna</th><th>Rak</th></tr>';
            
            while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
                $tgl = ($row['dTgl'] instanceof DateTime) ? $row['dTgl']->format('Y-m-d') : $row['dTgl'];
                echo '<tr>';
                echo '<td><code>' . htmlspecialchars($row['cNoOp']) . '</code></td>';
                echo '<td>' . $tgl . '</td>';
                echo '<td>' . number_format($row['nQty'], 2) . '</td>';
                echo '<td>' . htmlspecialchars($row['cWarna']) . '</td>';
                echo '<td><strong>' . htmlspecialchars($row['cRak']) . '</strong></td>';
                echo '</tr>';
                $count++;
            }
            echo '</table>';
            echo '<p><span class="success">✓ Found ' . $count . ' records</span></p>';
        } else {
            echo '<p><span class="warning">⚠ No data found in tbOP table</span></p>';
        }
    } else {
        echo '<span class="error">Query failed: ' . print_r(sqlsrv_errors(), true) . '</span>';
    }

    sqlsrv_close($conn);
} catch (Exception $e) {
    echo '<span class="error">ERROR: ' . $e->getMessage() . '</span>';
}
?>
