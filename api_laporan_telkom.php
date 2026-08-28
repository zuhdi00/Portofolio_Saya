<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

function indoDate($dateObj) {
    if (!$dateObj || !is_object($dateObj)) return '';
    $bulan = [
        1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
        5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
        9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
    ];
    $day = $dateObj->format('d');
    $month = intval($dateObj->format('m'));
    $year = $dateObj->format('Y');
    return $day . ' ' . $bulan[$month] . ' ' . $year;
}

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
        throw new Exception("Connection failed.");
    }

    $sql = "SELECT cNobkk, dTanggal, cNama, nTotNominal
            FROM tbBKK
            WHERE cKeterangan LIKE 'telkom%'
            ORDER BY dTanggal DESC";

    $stmt = sqlsrv_query($conn, $sql);

    if ($stmt === false) {
        throw new Exception("Query failed.");
    }

    $result = array();
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        // Format tanggal menjadi "01 Desember 2025"
        if ($row['dTanggal'] instanceof DateTime) {
            $row['dTanggal'] = indoDate($row['dTanggal']);
        } elseif (is_object($row['dTanggal']) && method_exists($row['dTanggal'], 'format')) {
            $row['dTanggal'] = indoDate($row['dTanggal']);
        } else {
            $row['dTanggal'] = '';
        }
        $row['nTotNominal'] = number_format($row['nTotNominal'], 2, ',', '.');
        $result[] = $row;
    }

    echo json_encode(['success' => true, 'data' => $result]);
    sqlsrv_free_stmt($stmt);
    sqlsrv_close($conn);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}