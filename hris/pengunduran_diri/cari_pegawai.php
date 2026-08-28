<?php
/**
 * cari_pegawai.php — endpoint AJAX untuk autocomplete pegawai
 * GET ?q=nama_atau_nik[&dept=id_dept]
 */
require_once __DIR__ . '/../auth/auth.php';
wajib_login();
require_once __DIR__ . '/../config/koneksi_sqlsrv.php';

header('Content-Type: application/json; charset=utf-8');

$q    = trim($_GET['q'] ?? '');
$dept = (int)($_GET['dept'] ?? 0);

if (strlen($q) < 1) {
    echo json_encode([]);
    exit;
}

$like = '%' . $q . '%';
$params = [$like, $like];

$whereDept = '';
if ($dept > 0) {
    $whereDept = 'AND u.department_id = ?';
    $params[]  = $dept;
}

$sql = "SELECT TOP 10 p.id_peg, p.nik, p.nama_peg, u.department_id
        FROM dbo.pegawai p
        LEFT JOIN dbo.unit_kerja u ON u.id = p.unit_kerja_id
        WHERE p.is_aktif = 1
          AND (p.nama_peg LIKE ? OR p.nik LIKE ?)
          $whereDept
        ORDER BY p.nama_peg";

$rs = sqlsrv_query($conn, $sql, $params);
$hasil = [];
if ($rs) {
    while ($r = sqlsrv_fetch_array($rs, SQLSRV_FETCH_ASSOC)) {
        $hasil[] = [
            'id'   => $r['id_peg'],
            'nik'  => $r['nik']  ?? '',
            'nama' => $r['nama_peg'] ?? '',
            'dept' => $r['department_id'],
        ];
    }
    sqlsrv_free_stmt($rs);
}

echo json_encode($hasil, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
