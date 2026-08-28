<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

// Get search and date filter parameters
$search1 = $_GET['search1'] ?? '';
$search2 = $_GET['search2'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';
try {
    $serverName = "spsdmz2";
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

    $sql = "SELECT 
        d.cKodeBahan,
        h.cNoBPB,
        h.cNama AS supplier,
        h.dTanggal,
        d.cNama AS nama_barang,
        d.cNoPP AS no_opb,
        h.cKeterangan,
        d.cUkuran AS ukuran,
        d.nQtyK AS jumlah,
        d.cSatK AS satuan
    FROM tbBPBdtl d
    LEFT JOIN tbBPB h ON d.cNoBPB = h.cNoBPB";

    $where = [];
    $params = [];

    // Default behavior: Fetch all records if no filters are applied
    if (empty($search1) && empty($search2) && empty($date_from) && empty($date_to)) {
        $sql .= " ORDER BY h.dTanggal DESC";
    } else {
        // Search 1
        if (!empty($search1)) {
            $where[] = "(d.cKodeBahan LIKE ? OR h.cNoBPB LIKE ? OR h.cNama LIKE ? OR d.cNama LIKE ? OR d.cNoPP LIKE ?)";
            $searchLike1 = "%$search1%";
            array_push($params, $searchLike1, $searchLike1, $searchLike1, $searchLike1, $searchLike1);
        }

        // Search 2
        if (!empty($search2)) {
            $where[] = "(d.cKodeBahan LIKE ? OR h.cNoBPB LIKE ? OR h.cNama LIKE ? OR d.cNama LIKE ? OR d.cNoPP LIKE ?)";
            $searchLike2 = "%$search2%";
            array_push($params, $searchLike2, $searchLike2, $searchLike2, $searchLike2, $searchLike2);
        }

        // Date filter (convert to SQL Server string format 'Y-m-d H:i:s.000')
        function sqlsrv_date_param($date, $endOfDay = false) {
            $dt = date_create($date);
            if (!$dt) return $date;
            if ($endOfDay) {
                $dt->setTime(23, 59, 59, 999000);
            }
            return $dt->format('Y-m-d H:i:s.000');
        }

        if ($date_from && $date_to) {
            $where[] = "(h.dTanggal >= ? AND h.dTanggal <= ?)";
            $params[] = sqlsrv_date_param($date_from);
            $params[] = sqlsrv_date_param($date_to, true);
        } elseif ($date_from) {
            $where[] = "(h.dTanggal >= ?)";
            $params[] = sqlsrv_date_param($date_from);
        } elseif ($date_to) {
            $where[] = "(h.dTanggal <= ?)";
            $params[] = sqlsrv_date_param($date_to, true);
        }

        if ($where) {
            $sql .= " WHERE " . implode(' AND ', $where);
        }

        $sql .= " ORDER BY h.dTanggal DESC";
    }

    // Debug log for troubleshooting
    $debug_log = __DIR__ . '/debug_bpb_unlimited_api.txt';
    file_put_contents($debug_log, "\n====\n" . date('Y-m-d H:i:s') . "\nSQL: $sql\nParams: " . print_r($params, true), FILE_APPEND);

    $stmt = sqlsrv_query($conn, $sql, $params);
    if ($stmt === false) {
        $errors = sqlsrv_errors();
        file_put_contents($debug_log, "\nSQL ERROR: " . print_r($errors, true), FILE_APPEND);
        error_log("SQL error: " . print_r($errors, true));
        throw new Exception("SQL error: " . print_r($errors, true));
    }

    $result = [];
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $row['dTanggal'] = isset($row['dTanggal']) && $row['dTanggal'] ? $row['dTanggal']->format('Y-m-d') : null;
        $result[] = $row;
    }

    echo json_encode(['success' => true, 'data' => $result]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}