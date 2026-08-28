<?php
require __DIR__ . '/config/koneksi_sqlsrv.php';

$file = __DIR__ . '/database/DATA KARYAWAN (2).csv';

if (!file_exists($file)) {
    die("File CSV tidak ditemukan.");
}

$handle = fopen($file, "r");
if ($handle === FALSE) {
    die("Gagal membuka file CSV.");
}

$header_found = false;
$headers = [];
$terupdate = [];
$gagal_insert = [];

while (($data = fgetcsv($handle, 4000, ",")) !== FALSE) {
    if (!$header_found) {
        if (!empty($data) && trim($data[0]) === 'id_peg') {
            $header_found = true;
            foreach ($data as $k => $v) {
                $headers[trim($v)] = $k;
            }
        }
        continue;
    }

    if (empty(trim($data[0]))) {
        continue;
    }

    $csv_nik = trim($data[$headers['id_peg']]);
    $nama_peg = isset($headers['nama']) ? trim($data[$headers['nama']]) : '-';
    
    // Cek apakah NIK ini sudah ada di database
    $check_sql = "SELECT id_peg, nama_peg FROM pegawai WHERE nik = ?";
    $check_stmt = sqlsrv_query($conn, $check_sql, array($csv_nik));
    $row = sqlsrv_fetch_array($check_stmt, SQLSRV_FETCH_ASSOC);
    $exists = ($row !== null);
    sqlsrv_free_stmt($check_stmt);

    if ($exists) {
        $terupdate[] = [
            'nik' => $csv_nik,
            'nama_excel' => $nama_peg,
            'nama_db' => $row['nama_peg']
        ];
    } else {
        $gagal_insert[] = [
            'nik' => $csv_nik,
            'nama_excel' => $nama_peg
        ];
    }
}
fclose($handle);
?>
<!DOCTYPE html>
<html>
<head>
    <title>Laporan Update Karyawan</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background-color: #f9f9f9;}
        table { border-collapse: collapse; width: 100%; margin-bottom: 30px; background-color: #fff; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
        th, td { border: 1px solid #ddd; padding: 10px; text-align: left; }
        th { background-color: #007BFF; color: white; }
        tr:nth-child(even) { background-color: #f2f2f2; }
        .success { color: #28a745; font-weight: bold; }
        .danger { color: #dc3545; font-weight: bold; }
        .card { padding: 15px; background: white; border-radius: 5px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); margin-bottom: 20px;}
    </style>
</head>
<body>

    <h2>Laporan Hasil Pencocokan Data Excel ke Database</h2>
    
    <div class="card">
        <h3><span class="success">Berhasil Di-update (<?= count($terupdate) ?> Data)</span></h3>
        <p>Data berikut adalah pegawai yang NIK-nya ditemukan di database dan biodatanya telah disinkronkan dengan Excel (Data Absen tetap aman).</p>
        <table>
            <tr>
                <th>No</th>
                <th>NIK (id_peg di Excel)</th>
                <th>Nama di Excel</th>
                <th>Nama di Database Saat Ini</th>
                <th>Status</th>
            </tr>
            <?php $i=1; foreach($terupdate as $dt): ?>
            <tr>
                <td><?= $i++ ?></td>
                <td><?= htmlspecialchars($dt['nik']) ?></td>
                <td><?= htmlspecialchars($dt['nama_excel']) ?></td>
                <td><?= htmlspecialchars($dt['nama_db']) ?></td>
                <td class="success">✔ Updated</td>
            </tr>
            <?php endforeach; ?>
        </table>
    </div>

    <div class="card">
        <h3><span class="danger">Gagal Ditambahkan / Belum Ada di DB (<?= count($gagal_insert) ?> Data)</span></h3>
        <p>Data berikut ada di Excel namun NIK-nya belum terdaftar di database, sehingga gagal masuk otomatis (biasanya karena bentrok KTP kosong atau Format Status Karyawan salah).</p>
        <table>
            <tr>
                <th>No</th>
                <th>NIK (id_peg di Excel)</th>
                <th>Nama di Excel</th>
                <th>Status</th>
            </tr>
            <?php $i=1; foreach($gagal_insert as $dt): ?>
            <tr>
                <td><?= $i++ ?></td>
                <td><?= htmlspecialchars($dt['nik']) ?></td>
                <td><?= htmlspecialchars($dt['nama_excel']) ?></td>
                <td class="danger">✖ Belum Terdaftar</td>
            </tr>
            <?php endforeach; ?>
        </table>
    </div>

</body>
</html>
