<?php
$conn = sqlsrv_connect("spsdmz2", ["Database" => "dbSopanusa", "Uid" => "sa", "PWD" => "supracor"]);
if (!$conn) {
    die(print_r(sqlsrv_errors(), true));
}
$stmt = sqlsrv_query($conn, "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'tbOP'");
while ($row = sqlsrv_fetch_array($stmt)) {
    echo $row[0] . "\n";
}
sqlsrv_free_stmt($stmt);
sqlsrv_close($conn);
?>
