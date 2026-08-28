<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header('Content-Type: application/json; charset=utf-8');

$serverName = "spsdmz2";
$connectionOptions = array(
    "Database"             => "dbSopanusa",
    "Uid"                  => "sa",
    "PWD"                  => "supracor",
    "LoginTimeout"         => 15,
    "Encrypt"              => false,
    "TrustServerCertificate" => true
);

function getConnectionError($errors) {
    if (empty($errors)) return "Unknown connection error";
    $msgs = [];
    foreach ($errors as $e) {
        $msgs[] = "SQLSTATE: {$e['SQLSTATE']}, Code: {$e['code']}, Message: {$e['message']}";
    }
    return implode("; ", $msgs);
}

try {
    $conn = sqlsrv_connect($serverName, $connectionOptions);
    if (!$conn) {
        throw new Exception("Database connection failed: " . getConnectionError(sqlsrv_errors()));
    }

    // ---------------------------------------------------------------
    // 1. Ambil Kuota dari tbSetting (field Nilai, satuan kg → ton)
    //    Asumsi: tbSetting memiliki kolom cKode / cNama untuk filter
    //    dan kolom Nilai (kg). Sesuaikan WHERE jika nama kode berbeda.
    // ---------------------------------------------------------------
    $sqlKuota = "SELECT TOP 1 CAST(Nilai / 1000.0 AS DECIMAL(10,2)) AS kuota_ton
                 FROM tbSetting
                 WHERE cKode = 'NilaiMaxOrderPerHari'";

    $stmtKuota = sqlsrv_query($conn, $sqlKuota);
    $kuota_ton = 0;
    if ($stmtKuota !== false) {
        $rowKuota = sqlsrv_fetch_array($stmtKuota, SQLSRV_FETCH_ASSOC);
        if ($rowKuota) {
            $kuota_ton = (float)$rowKuota['kuota_ton'];
        }
        sqlsrv_free_stmt($stmtKuota);
    }

    // ---------------------------------------------------------------
    // 2. Recap order harian dari tbTSC
    //    - Tanggal  : Dtgl
    //    - Total Order (ton) : SUM(nTot_brutto * nQty) / 1000
    //    - Penambahan Order  : kuota_ton - total_order_ton  (per hari)
    //    - Total Akhir Ton   : kumulatif running total
    // ---------------------------------------------------------------
    $sqlOrder = "SELECT
                    FORMAT(t.Dtgl, 'dd/MM/yyyy') AS tanggal,
                    t.Dtgl AS tanggal_raw,
                    CAST(SUM(t.nTot_brutto * t.nQty) / 1000.0 AS DECIMAL(10,2)) AS total_order_ton
                 FROM tbTSC t
                 WHERE t.Dtgl >= DATEADD(DAY, -30, GETDATE())
                 GROUP BY FORMAT(t.Dtgl, 'dd/MM/yyyy'), t.Dtgl
                 ORDER BY t.Dtgl ASC";

    $stmtOrder = sqlsrv_query($conn, $sqlOrder);
    if ($stmtOrder === false) {
        throw new Exception("Failed to execute order query: " . getConnectionError(sqlsrv_errors()));
    }

    $rows = [];
    $running_total = 0;
    while ($row = sqlsrv_fetch_array($stmtOrder, SQLSRV_FETCH_ASSOC)) {
        $total_order   = (float)$row['total_order_ton'];
        $penambahan    = round($kuota_ton - $total_order, 2);
        $running_total = round($running_total + $total_order, 2);

        $rows[] = [
            'tanggal'           => $row['tanggal'],
            'kuota_ton'         => number_format($kuota_ton, 2, '.', '') . ' Ton',
            'total_order_ton'   => number_format($total_order, 2, '.', '') . ' Ton',
            'penambahan_order'  => number_format($penambahan, 2, '.', '') . ' Ton',
            'total_akhir_ton'   => number_format($running_total, 2, '.', '') . ' Ton',
        ];
    }
    sqlsrv_free_stmt($stmtOrder);

    // Grand summary
    $grand_total_order = array_sum(array_map(fn($r) => (float)$r['total_order_ton'], $rows));

    echo json_encode([
        'success'          => true,
        'kuota_ton'        => number_format($kuota_ton, 2, '.', '') . ' Ton',
        'data'             => $rows,
        'grand_total_order'=> number_format($grand_total_order, 2, '.', '') . ' Ton',
        'timestamp'        => date('Y-m-d H:i:s')
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success'   => false,
        'message'   => $e->getMessage(),
        'timestamp' => date('Y-m-d H:i:s')
    ], JSON_UNESCAPED_UNICODE);
} finally {
    if (isset($conn) && $conn) sqlsrv_close($conn);
}
?>
