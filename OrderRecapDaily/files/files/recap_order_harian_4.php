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

    // 1. Kuota dari tbSetting (Nilai dalam kg → ton)
    $kuota_ton = 0;
    $stmtKuota = sqlsrv_query($conn,
        "SELECT TOP 1 CAST(Nilai / 1000.0 AS DECIMAL(10,2)) AS kuota_ton
         FROM tbSetting
         WHERE cKode = 'NilaiMaxOrderPerHari'"
    );
    if ($stmtKuota !== false) {
        $rowK = sqlsrv_fetch_array($stmtKuota, SQLSRV_FETCH_ASSOC);
        if ($rowK) $kuota_ton = (float)$rowK['kuota_ton'];
        sqlsrv_free_stmt($stmtKuota);
    }

    // 2. Recap order harian — GROUP BY CAST(Dtgl AS DATE) supaya
    //    datetime/smalldatetime tetap tergrouping per hari dengan benar
    $sqlOrder = "
        SELECT
            CAST(t.Dtgl AS DATE)                                  AS tanggal_date,
            CAST(SUM(t.nTot_brutto * t.nQty) / 1000.0 AS DECIMAL(10,2)) AS total_order_ton
        FROM tbTSC t
        WHERE CAST(t.Dtgl AS DATE) >= CAST(DATEADD(DAY, -30, GETDATE()) AS DATE)
        GROUP BY CAST(t.Dtgl AS DATE)
        ORDER BY CAST(t.Dtgl AS DATE) DESC
    ";

    $stmtOrder = sqlsrv_query($conn, $sqlOrder);
    if ($stmtOrder === false) {
        throw new Exception("Query order gagal: " . getConnectionError(sqlsrv_errors()));
    }

    // Kumpulkan raw rows (DESC dari DB)
    $raw = [];
    while ($row = sqlsrv_fetch_array($stmtOrder, SQLSRV_FETCH_ASSOC)) {
        // tanggal_date bisa berupa DateTime object (sqlsrv driver)
        $tgl = $row['tanggal_date'];
        if ($tgl instanceof DateTime) {
            $tglStr = $tgl->format('d/m/Y');
        } else {
            // fallback: string YYYY-MM-DD → reformat
            $tglStr = date('d/m/Y', strtotime((string)$tgl));
        }
        $raw[] = [
            'tanggal'         => $tglStr,
            'total_order_ton' => (float)$row['total_order_ton'],
        ];
    }
    sqlsrv_free_stmt($stmtOrder);

    // Grand total
    $grand_total_order = array_sum(array_column($raw, 'total_order_ton'));

    // Running total kumulatif: hitung dari terlama → terbaru, tampilkan DESC
    $asc   = array_reverse($raw); // ubah ke ASC
    $running = 0;
    $temp  = [];
    foreach ($asc as $item) {
        $running   = round($running + $item['total_order_ton'], 2);
        $penambahan = round($kuota_ton - $item['total_order_ton'], 2);
        $temp[] = [
            'tanggal'          => $item['tanggal'],
            'kuota_ton'        => number_format($kuota_ton,              2, '.', '') . ' Ton',
            'total_order_ton'  => number_format($item['total_order_ton'],2, '.', '') . ' Ton',
            'penambahan_order' => number_format($penambahan,             2, '.', '') . ' Ton',
            'total_akhir_ton'  => number_format($running,                2, '.', '') . ' Ton',
        ];
    }
    $rows = array_reverse($temp); // kembalikan ke DESC

    echo json_encode([
        'success'           => true,
        'kuota_ton'         => number_format($kuota_ton,          2, '.', '') . ' Ton',
        'data'              => $rows,
        'grand_total_order' => number_format($grand_total_order,  2, '.', '') . ' Ton',
        'timestamp'         => date('Y-m-d H:i:s'),
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success'   => false,
        'message'   => $e->getMessage(),
        'timestamp' => date('Y-m-d H:i:s'),
    ], JSON_UNESCAPED_UNICODE);
} finally {
    if (isset($conn) && $conn) sqlsrv_close($conn);
}
?>
