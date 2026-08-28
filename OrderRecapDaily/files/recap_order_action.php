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

// Validasi format YYYY-MM-DD
function isValidDate($str) {
    return preg_match('/^\d{4}-\d{2}-\d{2}$/', $str) && strtotime($str) !== false;
}

try {
    $conn = sqlsrv_connect($serverName, $connectionOptions);
    if (!$conn) {
        throw new Exception("Database connection failed: " . getConnectionError(sqlsrv_errors()));
    }

    // --- Ambil filter tanggal dari GET parameter ---
    $today   = date('Y-m-d');
    $default_from = date('Y-m-d', strtotime('-30 days'));

    $dateFrom = isset($_GET['from']) && isValidDate($_GET['from']) ? $_GET['from'] : $default_from;
    $dateTo   = isset($_GET['to'])   && isValidDate($_GET['to'])   ? $_GET['to']   : $today;

    // Pastikan from <= to
    if ($dateFrom > $dateTo) {
        $tmp = $dateFrom; $dateFrom = $dateTo; $dateTo = $tmp;
    }

    // 1. Kuota dari tbSetting (Nilai dalam kg → ton)
    $kuota_ton = 0;
    $stmtKuota = sqlsrv_query($conn,
        "SELECT TOP 1 CAST(TRY_CAST(NULLIF(LTRIM(RTRIM(Nilai)), '') AS DECIMAL(18,2)) / 1000.0 AS DECIMAL(10,2)) AS kuota_ton
          FROM tbSettingFitur
           WHERE KodeSetting = 'NilaiMaxOrderPerHari'"
    );
    if ($stmtKuota !== false) {
        $rowK = sqlsrv_fetch_array($stmtKuota, SQLSRV_FETCH_ASSOC);
        if ($rowK) $kuota_ton = (float)$rowK['kuota_ton'];
        sqlsrv_free_stmt($stmtKuota);
    }

    // 2. Recap order harian dengan filter tanggal
    //    total_order = total_box (tbTSC: SUM(nTot_brutto * nQty) / 1000)
    //                + total_perlengkapan (tbTSCDtl: SUM(nBrt * nQty) / 1000)
    $sqlOrder = "
        SELECT
            CAST(t.dexpired2 AS DATE) AS tanggal_date,
            CAST(ISNULL(SUM(t.nTot_brutto * t.nQty), 0) / 1000.0 AS DECIMAL(18,5)) AS total_box_ton,
            CAST(ISNULL(
                (SELECT SUM(d.nBrt * d.nQty)
                 FROM tbTSCDtl d
                 INNER JOIN tbTSC t2 ON t2.nKode = d.nKodeTSC
                 WHERE CAST(t2.dexpired2 AS DATE) >= ? AND CAST(t2.dexpired2 AS DATE) <= ?
                   AND CAST(t2.dexpired2 AS DATE) = CAST(t.dexpired2 AS DATE)
                ), 0) / 1000.0 AS DECIMAL(18,5)) AS total_perlengkapan_ton
        FROM tbTSC t
        WHERE CAST(t.dexpired2 AS DATE) >= ? AND CAST(t.dexpired2 AS DATE) <= ?
        GROUP BY CAST(t.dexpired2 AS DATE)
        ORDER BY CAST(t.dexpired2 AS DATE) DESC
    ";

    $stmtOrder = sqlsrv_query($conn, $sqlOrder, array($dateFrom, $dateTo, $dateFrom, $dateTo));
    if ($stmtOrder === false) {
        throw new Exception("Query order gagal: " . getConnectionError(sqlsrv_errors()));
    }

    // Build maps keyed by ISO date (Y-m-d) so we can iterate the full date range
    $orderMap = [];  // total_box
    $perlMap  = [];  // total_perlengkapan
    while ($row = sqlsrv_fetch_array($stmtOrder, SQLSRV_FETCH_ASSOC)) {
        $tgl = $row['tanggal_date'];
        if ($tgl instanceof DateTime) {
            $key = $tgl->format('Y-m-d');
        } else {
            $key = date('Y-m-d', strtotime((string)$tgl));
        }
        $orderMap[$key] = isset($orderMap[$key]) ? $orderMap[$key] + (float)$row['total_box_ton']          : (float)$row['total_box_ton'];
        $perlMap[$key]  = isset($perlMap[$key])  ? $perlMap[$key]  + (float)$row['total_perlengkapan_ton'] : (float)$row['total_perlengkapan_ton'];
    }
    sqlsrv_free_stmt($stmtOrder);

    // --- Ambil penambahan order dari tbRvTsc untuk rentang (nAccPiutang = 1)
    $rvMap = [];
    $sqlRv = "
        SELECT CAST(dexpired2 AS DATE) AS tanggal_date,
               CAST(ISNULL(SUM(nTot_netto * nQty),0) / 1000.0 AS DECIMAL(18,5)) AS rv_order_ton
        FROM tbRvTsc
        WHERE CAST(dexpired2 AS DATE) >= ? AND CAST(dexpired2 AS DATE) <= ? AND (nAccPiutang = 1 OR TRY_CAST(nAccPiutang AS VARCHAR) = '1')
        GROUP BY CAST(dexpired2 AS DATE)
    ";
    $stmtRv = sqlsrv_query($conn, $sqlRv, array($dateFrom, $dateTo));
    if ($stmtRv !== false) {
        while ($r = sqlsrv_fetch_array($stmtRv, SQLSRV_FETCH_ASSOC)) {
            $d = $r['tanggal_date'];
            if ($d instanceof DateTime) $key = $d->format('Y-m-d');
            else $key = date('Y-m-d', strtotime((string)$d));
            $rvMap[$key] = (isset($rvMap[$key]) ? $rvMap[$key] + 0.0 : 0.0) + (float)$r['rv_order_ton'];
        }
        sqlsrv_free_stmt($stmtRv);
    }

    // Iterate full date range and compute per-day contribution and running cumulative
    $begin = new DateTime($dateFrom);
    $end   = new DateTime($dateTo);
    $end->setTime(0,0,0);
    $interval = new DateInterval('P1D');
    $period = new DatePeriod($begin, $interval, (clone $end)->add($interval));

    $rowsAsc = [];
    $running = 0.0;
    $grand_total_order = 0.0;
    foreach ($period as $dt) {
        $key     = $dt->format('Y-m-d');
        $display = $dt->format('d/m/Y');

        // total box (brutto) dari tbTSC
        $dayBox  = isset($orderMap[$key]) ? round((float)$orderMap[$key], 5) : 0.0;
        // total perlengkapan dari tbTSCDtl
        $dayPerl = isset($perlMap[$key])  ? round((float)$perlMap[$key],  5) : 0.0;
        // total order = total box + total perlengkapan
        $dayOrder = round($dayBox + $dayPerl, 5);

        // penambahan order dari tbRvTsc
        $rvVal = isset($rvMap[$key]) ? round((float)$rvMap[$key], 5) : 0.0;

        // total akhir = total order + penambahan order
        $dayContribution = round($dayOrder + $rvVal, 5);
        $running = round($running + $dayContribution, 5);

        $grand_total_order += $dayContribution;

        $rowsAsc[] = [
            'tanggal_iso'          => $key,
            'tanggal'              => $display,
            'kuota_ton'            => number_format($kuota_ton, 5, '.', '') . ' Ton',
            'total_box_ton'        => number_format($dayBox,    5, '.', '') . ' Ton',
            'total_perlengkapan_ton' => number_format($dayPerl, 5, '.', '') . ' Ton',
            'total_order_ton'      => number_format($dayOrder,  5, '.', '') . ' Ton',
            'penambahan_order'     => number_format($rvVal,     5, '.', '') . ' Ton',
            // total akhir = total order + penambahan order (non-kumulatif)
            'total_akhir_ton'      => number_format($dayContribution, 5, '.', '') . ' Ton',
        ];
    }

    // Present rows in DESC (latest first) to keep compatibility with frontend
    $rows = array_reverse($rowsAsc);

    echo json_encode([
        'success'           => true,
        'kuota_ton'         => number_format($kuota_ton,         5, '.', '') . ' Ton',
        'data'              => $rows,
        'grand_total_order' => number_format($grand_total_order, 5, '.', '') . ' Ton',
        'date_from'         => $dateFrom,
        'date_to'           => $dateTo,
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
