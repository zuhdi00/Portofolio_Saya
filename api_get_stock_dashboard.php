<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

// Tarik data dari tbNewTmpStock, support pencarian
$search = $_GET['search'] ?? '';
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

    $sql = "SELECT TOP (10)
            s.cKodeBahan,
            s.nQty,
            s.cId,
            s.cSat,
            s.nHrgPokok,
            s.nhrgBeli,
            s.nHrgJual,
            s.nStkAwal,
            s.nQtyReal,
            s.cSatReal,
            s.cKodeSc,
            s.cNamaSc,
            s.lOld,
            s.UserId,
            s.UserDate,
            s.ComputerName,
            s.cUserComp,
            s.AppName,
            b.cNama AS nama_barang,
            b.cNamaAlias1 AS ukuran,
            CASE WHEN s.cId = '0' THEN s.nQty ELSE 0 END AS saldo_awal,
            CASE WHEN s.cId = 'A' THEN s.nQty ELSE 0 END AS beli,
            CASE WHEN s.cId = 'F' THEN s.nQty ELSE 0 END AS rt_beli,
            CASE WHEN s.cId = 'E' THEN s.nQty ELSE 0 END AS pakai,
            CASE WHEN s.cId = 'B' THEN s.nQty ELSE 0 END AS rt_pakai,
            CASE WHEN s.cId = 'G' THEN s.nQty ELSE 0 END AS jual,
            CASE WHEN s.cId IN ('D','C') THEN s.nQtyReal ELSE 0 END AS adj,
            (
                CASE WHEN s.cId = '0' THEN s.nQty ELSE 0 END
                + CASE WHEN s.cId = 'A' THEN s.nQty ELSE 0 END
                + CASE WHEN s.cId = 'B' THEN s.nQty ELSE 0 END
                - CASE WHEN s.cId = 'E' THEN s.nQty ELSE 0 END
                - CASE WHEN s.cId = 'F' THEN s.nQty ELSE 0 END
                - CASE WHEN s.cId = 'G' THEN s.nQty ELSE 0 END
                + CASE WHEN s.cId = 'K' THEN s.nQty ELSE 0 END
                + CASE WHEN s.cId = 'J' THEN s.nQty ELSE 0 END
                + CASE WHEN s.cId = 'I' THEN s.nQty ELSE 0 END
                - CASE WHEN s.cId = 'H' THEN s.nQty ELSE 0 END
                + CASE WHEN s.cId IN ('D','C') THEN s.nQtyReal ELSE 0 END
            ) AS saldo
    FROM tbNewTmpStock s
    LEFT JOIN tbBahan b ON s.cKodeBahan = b.cKode";
    $params = [];
    if ($search) {
        $sql .= " WHERE s.cKodeBahan LIKE ? OR b.cNama LIKE ? OR s.cKodeSc LIKE ? OR s.cNamaSc LIKE ?";
        $searchLike = "%$search%";
        $params = [$searchLike, $searchLike, $searchLike, $searchLike];
    }
    $stmt = sqlsrv_query($conn, $sql, $params);
    if ($stmt === false) {
        $errors = sqlsrv_errors();
        throw new Exception("SQL error: " . print_r($errors, true));
    }

    $result = [];
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        // Format tanggal ke Y-m-d jika ada
        $row['dTglVal'] = isset($row['dTglVal']) && $row['dTglVal'] ? $row['dTglVal']->format('Y-m-d') : null;
        $row['dTglBuku'] = isset($row['dTglBuku']) && $row['dTglBuku'] ? $row['dTglBuku']->format('Y-m-d') : null;
        $row['UserDate'] = isset($row['UserDate']) && $row['UserDate'] ? $row['UserDate']->format('Y-m-d H:i:s') : null;
        $result[] = $row;
    }

    echo json_encode(['success' => true, 'data' => $result]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
