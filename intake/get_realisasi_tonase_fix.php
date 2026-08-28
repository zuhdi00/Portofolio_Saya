<?php
/**
 * get_realisasi_tonase_fix.php
 * Endpoint: ambil tonase per OP (default) menggunakan rumus VB:
 *   CASE WHEN cTipe LIKE 'SF%' AND nPanjang <= 0 THEN nQty ELSE nQty * nBrtOp END
 * dan kurangi retur berdasarkan vwReturnSrj (SUM(nBerat * nQty)).
 *
 * Params (GET): date_from, date_to, srj_no, op_no, limit (0=all)
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit(); }

// DB config — reuse same as other endpoints
$serverName = "spsdmz2";
$connectionOptions = [
    "Database"             => "dbSopanusa",
    "Uid"                  => "sa",
    "PWD"                  => "supracor",
    "LoginTimeout"         => 30,
    "Encrypt"              => false,
    "TrustServerCertificate" => true,
    "ReturnDatesAsStrings" => true,
    "CharacterSet"         => "UTF-8"
];

function dbConnect($serverName, $opts) {
    $conn = sqlsrv_connect($serverName, $opts);
    if (!$conn) {
        http_response_code(500);
        echo json_encode(['success'=>false,'message'=>'Koneksi DB gagal','errors'=>sqlsrv_errors()]);
        exit;
    }
    return $conn;
}

function safeStr($val) {
    if ($val === null) return '';
    if (is_string($val)) return trim(iconv('ISO-8859-1','UTF-8//IGNORE//TRANSLIT',$val));
    return $val;
}

function fetchAll($conn, $sql, $params = []) {
    $opts = ["QueryTimeout" => 6000];
    $stmt = empty($params) ? sqlsrv_query($conn, $sql, [], $opts) : sqlsrv_query($conn, $sql, $params, $opts);
    if ($stmt === false) return ['__error' => sqlsrv_errors()];
    $rows = [];
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $clean = [];
        foreach ($row as $k => $v) $clean[$k] = is_string($v) ? safeStr($v) : $v;
        $rows[] = $clean;
    }
    sqlsrv_free_stmt($stmt);
    return $rows;
}

// --- params ---
$dateFrom = trim($_GET['date_from'] ?? '');
$dateTo   = trim($_GET['date_to']   ?? '');
$srjNo    = trim($_GET['srj_no']    ?? '');
$opNo     = trim($_GET['op_no']     ?? '');
$limitRaw = $_GET['limit'] ?? null;
if ($limitRaw === 'all' || $limitRaw === '0' || $limitRaw === 0) { $limit = 0; } else { $limit = intval($limitRaw ?: 1000); }

$conn = dbConnect($serverName, $connectionOptions);

$where = [];
$params = [];
if ($srjNo !== '') { $where[] = "s.cNoSRJ LIKE ?"; $params[] = '%'.$srjNo.'%'; }
if ($opNo  !== '') { $where[] = "d.cNoOp LIKE ?";  $params[] = '%'.$opNo.'%'; }
if ($dateFrom !== '') { $where[] = "CONVERT(date,s.dTanggal) >= ?"; $params[] = $dateFrom; }
if ($dateTo   !== '') { $where[] = "CONVERT(date,s.dTanggal) <= ?"; $params[] = $dateTo; }

// Build SQL: aggregate per cNoOp with VB tonnage formula, then left join retur aggregate
$sql = "SELECT\n"
     . "  agg.cNoOp AS op_no,\n"
     . "  agg.tgl_kirim_min,\n"
     . "  agg.tgl_kirim_max,\n"
     . "  agg.jml_kirim,\n"
     . "  agg.tonase_kg_kirim,\n"
     . "  ISNULL(ret.tonase_kg_retur,0) AS tonase_kg_retur,\n"
     . "  (agg.tonase_kg_kirim - ISNULL(ret.tonase_kg_retur,0)) AS tonase_kg_net\n"
     . "FROM (\n"
     . "  SELECT d.cNoOp,\n"
     . "         MIN(s.dTanggal) AS tgl_kirim_min,\n"
     . "         MAX(s.dTanggal) AS tgl_kirim_max,\n"
    . "         SUM(ISNULL(d.nQty,0)) AS jml_kirim,\n"
    . "         SUM( CASE WHEN COALESCE(vw.lVoid, s.lVoid, '0') = '1' THEN 0 WHEN d.cTipe LIKE 'SF%' AND ISNULL(d.nPanjang,0)<=0 THEN ISNULL(d.nQty,0) ELSE ISNULL(d.nQty,0)*ISNULL(d.nBrtOp,0) END ) AS tonase_kg_kirim\n"
    . "  FROM tbSRJDtl d WITH (NOLOCK)\n"
    . "  INNER JOIN tbSRJ s WITH (NOLOCK) ON s.cNoSRJ = d.cNoSRJ\n"
    . "  LEFT JOIN vwSuratJalan vw WITH (NOLOCK) ON vw.cNoSRJ = s.cNoSRJ\n"
     . (empty($where) ? '' : '  WHERE ' . implode(' AND ', $where) . "\n")
     . "  GROUP BY d.cNoOp\n"
     . ") agg\n"
     . "LEFT JOIN (\n"
     . "  SELECT cNoOp, SUM(ISNULL(nBerat,0)*ISNULL(nQty,0)) AS tonase_kg_retur FROM vwReturnSrj WITH (NOLOCK) GROUP BY cNoOp\n"
     . ") ret ON ret.cNoOp = agg.cNoOp\n"
     . "ORDER BY agg.tgl_kirim_min DESC\n";

// NOTE: per request, ignore client-side 'limit' and always return full result set.
$params_with_limit = $params;

$rows = fetchAll($conn, $sql, $params_with_limit);
if (isset($rows['__error'])) {
    echo json_encode(['success'=>false,'message'=>'Query failed','errors'=>$rows['__error']]);
    sqlsrv_close($conn);
    exit;
}

// cast numeric values
foreach ($rows as &$r) {
    $r['tonase_kg_kirim'] = isset($r['tonase_kg_kirim']) ? floatval($r['tonase_kg_kirim']) : 0.0;
    $r['tonase_kg_retur'] = isset($r['tonase_kg_retur']) ? floatval($r['tonase_kg_retur']) : 0.0;
    $r['tonase_kg_net']  = isset($r['tonase_kg_net']) ? floatval($r['tonase_kg_net']) : ($r['tonase_kg_kirim'] - $r['tonase_kg_retur']);
}

sqlsrv_close($conn);

echo json_encode(['success'=>true,'count'=>count($rows),'data'=>$rows,'params'=>['date_from'=>$dateFrom,'date_to'=>$dateTo,'srj_no'=>$srjNo,'op_no'=>$opNo,'limit'=>$limit],'timestamp'=>date('Y-m-d H:i:s')], JSON_UNESCAPED_UNICODE);
