<?php
require 'c:/xampp/htdocs/hris/config/koneksi_sqlsrv.php';

sqlsrv_begin_transaction($conn);

$del = sqlsrv_query($conn, "DELETE FROM pegawai WHERE id_peg = 3426");
echo "Delete 3426: " . ($del ? "OK" : "FAILED") . "\n";

$upd = sqlsrv_query($conn, "UPDATE pegawai SET no_ktp = '3516052105690001' WHERE id_peg = 1351");
if ($upd === false) {
    echo "Update 1351: FAILED\n";
    print_r(sqlsrv_errors());
} else {
    echo "Update 1351: OK\n";
}

sqlsrv_rollback($conn);
