<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$search = isset($_GET['search']) ? trim($_GET['search']) : '';

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

    if (!$conn) throw new Exception("Connection failed.");

    $sql = "SELECT cNoSTB, dTanggal, cNoSc, cNamabrg, cNoMC, cNoOp, cNama, nPanjang, nLebar, nTinggi, cWarna, UserDate, nQty, nQtyKg, nberat, cKeterangan, cRak, cShift
            FROM tbStbBJ";
    $params = array();

    if ($search) {
        $sql .= " WHERE cNoSTB LIKE ? OR cNoMC LIKE ? OR cNama LIKE ? OR cRak LIKE ? OR cShift LIKE ?";
        $searchTerm = '%' . $search . '%';
        $params = array($searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm);
    }

    $sql .= " ORDER BY UserDate DESC";

    $stmt = sqlsrv_query($conn, $sql, $params);

    if ($stmt === false) throw new Exception("Query failed.");

    $data = array();
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        foreach ($row as $k => $v) {
            if ($v instanceof DateTime) $row[$k] = $v->format('Y-m-d');
        }
        $data[] = $row;
    }

    echo json_encode(['success' => true, 'data' => $data]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}