<?php
/**
 * migrate_csv_to_sqlserver.php
 * Migrasi data dari CSV ke SQL Server database dbHR
 * - Update data yang sudah ada (case-insensitive)
 * - Insert data yang baru (hanya di CSV)
 * - Dry-run mode untuk preview
 */

include '../config/koneksi_sqlsrv.php';   // $conn untuk dbHR
include '../config/koneksi.php';         // $mysqli untuk MySQL hris

header('Content-Type: text/html; charset=utf-8');

$dry_run = isset($_GET['dry_run']) ? $_GET['dry_run'] === '1' : true;
$action = isset($_GET['action']) ? $_GET['action'] : '';

echo "<h2>🔄 Migrasi Data Pegawai: CSV → SQL Server</h2>";
echo "<p>Mode: <strong>" . ($dry_run ? "DRY RUN (Preview)" : "EXECUTE") . "</strong></p>";

// ============================================================
// 1. BACA CSV
// ============================================================
$csv_file = '../database/DATA KARYAWAN (2).csv';

if (!file_exists($csv_file)) {
    die("❌ CSV file tidak ditemukan: $csv_file");
}

$csv_data = array();
$row = 0;

if (($handle = fopen($csv_file, 'r')) !== FALSE) {
    while (($data = fgetcsv($handle, 1000, ',')) !== FALSE) {
        $row++;
        if ($row <= 4) continue;  // Skip header (baris 1-4)
        if (empty($data[0])) continue;  // Skip baris kosong
        
        $csv_data[] = array(
            'id_peg' => trim($data[0]),
            'no_ktp' => trim($data[1]),
            'nama' => trim($data[3]),
            'email_peg' => trim($data[4]),
            'no_hp_peg' => trim($data[5]),
            'tgl_lahir' => trim($data[6]),
            'tempat_lahir' => trim($data[7]),
            'gender' => trim($data[8]),
            'agama' => trim($data[9]),
            'status_kawin' => trim($data[10]),
            'alamat_ktp_peg' => trim($data[11]),
            'rt' => trim($data[12]),
            'rw' => trim($data[13]),
            'kelurahan' => trim($data[14]),
            'kecamatan' => trim($data[15]),
            'kota' => trim($data[16]),
            'provinsi' => trim($data[17]),
            'kode_pos' => trim($data[18]),
        );
    }
    fclose($handle);
}

echo "<p>Total data dari CSV: <strong>" . count($csv_data) . "</strong> records</p>";

// ============================================================
// 2. AMBIL EXISTING DATA DARI SQL
// ============================================================
$sql_query = "SELECT nik as id_peg, no_ktp, nama, email, no_hp FROM dbo.pegawai_lengkap";
$stmt = sqlsrv_query($conn, $sql_query);

if ($stmt === false) {
    die("❌ Query SQL Server gagal: " . print_r(sqlsrv_errors(), true));
}

$existing_ids = array();
while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $existing_ids[$row['id_peg']] = $row;
}

echo "<p>Total data existing di SQL Server: <strong>" . count($existing_ids) . "</strong> records</p>";

// ============================================================
// 3. HELPER FUNCTIONS
// ============================================================
function normalize_name($name) {
    if ($name === null) return '';
    $name = trim((string)$name);
    if ($name === '') return '';
    $name = mb_strtoupper($name, 'UTF-8');
    $name = preg_replace('/[.\/\,_-]+/u', ' ', $name);
    $name = preg_replace('/\s+/', ' ', $name);
    $name = str_replace([' ACH ', ' MOCH ', ' MUH ', ' MUHAMMAD ', ' H ', ' KH '], ' ', $name);
    $name = preg_replace('/\b(ACH|MOCH|MUH|MUHAMMAD|H|KH)\b/u', '', $name);
    $name = preg_replace('/\s+/', ' ', trim($name));
    return $name;
}

// ============================================================
// 4. DETEKSI DUPLIKAT
// ============================================================
$csv_by_name = array();
foreach ($csv_data as $row) {
    $norm_name = normalize_name($row['nama']);
    if (!isset($csv_by_name[$norm_name])) {
        $csv_by_name[$norm_name] = array();
    }
    $csv_by_name[$norm_name][] = $row;
}

$csv_duplicates = array();
foreach ($csv_by_name as $norm_name => $rows) {
    if (count($rows) > 1) {
        $csv_duplicates[$norm_name] = $rows;
    }
}

if (count($csv_duplicates) > 0) {
    echo "<div style='background:#fff3cd; padding:15px; margin:20px 0; border-left:4px solid #ff9800;'>";
    echo "<h3>⚠️ PERINGATAN: Duplikat Data Ditemukan</h3>";
    echo "<p><strong style='color:red;'>" . count($csv_duplicates) . " nama yang duplikat ditemukan di CSV (sama nama, ID/NIK berbeda):</strong></p>";
    foreach ($csv_duplicates as $norm_name => $rows) {
        echo "<fieldset style='background:#ffe0b2; padding:10px; margin:10px 0; border:1px solid #ff9800;'>";
        echo "<legend><strong>Nama: $norm_name (" . count($rows) . " records)</strong></legend>";
        echo "<table border='1' cellpadding='8' style='width:100%; border-collapse:collapse; font-size:12px;'>";
        echo "<tr><th>ID CSV</th><th>NIK</th><th>Nama Original</th></tr>";
        foreach ($rows as $r) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($r['id_peg']) . "</td>";
            echo "<td>" . htmlspecialchars($r['no_ktp']) . "</td>";
            echo "<td>" . htmlspecialchars($r['nama']) . "</td>";
            echo "</tr>";
        }
        echo "</table>";
        echo "<p style='color:red; margin:10px 0 0 0;'><small>⚠️ Pastikan ini adalah orang yang sama sebelum melanjutkan migrasi. Jika berbeda orang, perlu pengaturan ID yang benar.</small></p>";
        echo "</fieldset>";
    }
    echo "<p style='margin:15px 0;'><a href='handle_duplicates.php' style='padding:10px 15px; background:#ff9800; color:white; text-decoration:none; border-radius:4px;'><strong>🔧 Handle Duplikat</strong></a></p>";
    echo "</div>";
}

// ============================================================
// 5. PROSES MIGRASI
// ============================================================
$stats = array(
    'insert' => 0,
    'update' => 0,
    'skip' => 0,
    'errors' => array(),
);

echo "<div style='background:#f9f9f9; padding:10px; margin:10px 0; border:1px solid #ddd;'>";
echo "<h3>Proses Migrasi:</h3>";
echo "<table border='1' cellpadding='8' style='width:100%; border-collapse:collapse; font-size:12px;'>";
echo "<tr><th>No</th><th>ID</th><th>Nama</th><th>Action</th><th>Status</th></tr>";

$display_count = 0;  // Counter untuk tampilan (hanya 100)

foreach ($csv_data as $idx => $csv_row) {
    $id_peg = $csv_row['id_peg'];
    
    // Convert tanggal format
    $tgl_lahir = null;
    if (!empty($csv_row['tgl_lahir'])) {
        $date = \DateTime::createFromFormat('d/m/Y', $csv_row['tgl_lahir']);
        if ($date) {
            $tgl_lahir = $date->format('Y-m-d');
        }
    }
    
    if (isset($existing_ids[$id_peg])) {
        // UPDATE
        $stats['update']++;
        $action_type = "UPDATE";
        
        if (!$dry_run) {
            $sql_update = "UPDATE dbo.pegawai_lengkap SET
                no_ktp = ?,
                nama = ?,
                email = ?,
                no_hp = ?,
                tanggal_lahir = ?,
                tempat_lahir = ?,
                gender = ?,
                agama = ?,
                status_kawin = ?,
                almt_tetap = ?,
                almt_tetap_rt = ?,
                almt_tetap_rw = ?,
                almt_tetap_desa = ?,
                almt_tetap_kecamatan = ?,
                almt_tetap_kota = ?,
                almt_tetap_provinsi = ?,
                almt_tetap_kodepos = ?,
                updated_at = GETDATE()
            WHERE nik = ?";
            
            $params = array(
                $csv_row['no_ktp'] ?: NULL,
                $csv_row['nama'],
                $csv_row['email_peg'] ?: NULL,
                $csv_row['no_hp_peg'] ?: NULL,
                $tgl_lahir,
                $csv_row['tempat_lahir'] ?: NULL,
                $csv_row['gender'] ?: NULL,
                $csv_row['agama'] ?: NULL,
                $csv_row['status_kawin'] ?: NULL,
                $csv_row['alamat_ktp_peg'] ?: NULL,
                $csv_row['rt'] ?: NULL,
                $csv_row['rw'] ?: NULL,
                $csv_row['kelurahan'] ?: NULL,
                $csv_row['kecamatan'] ?: NULL,
                $csv_row['kota'] ?: NULL,
                $csv_row['provinsi'] ?: NULL,
                $csv_row['kode_pos'] ?: NULL,
                $id_peg,
            );
            
            $stmt = sqlsrv_query($conn, $sql_update, $params);
            if ($stmt === false) {
                $stats['errors'][] = "ID $id_peg: " . print_r(sqlsrv_errors(), true);
                $status = "<span style='color:red;'>❌ ERROR</span>";
            } else {
                $status = "<span style='color:green;'>✅ SUCCESS</span>";
            }
        } else {
            $status = "<span style='color:orange;'>⏳ (akan UPDATE)</span>";
        }
        
    } else {
        // INSERT
        $stats['insert']++;
        $action_type = "INSERT";
        
        if (!$dry_run) {
            $sql_insert = "INSERT INTO dbo.pegawai_lengkap (
                nik, no_ktp, nama, email, no_hp,
                tanggal_lahir, tempat_lahir, gender, agama, status_kawin,
                almt_tetap, almt_tetap_rt, almt_tetap_rw,
                almt_tetap_desa, almt_tetap_kecamatan, almt_tetap_kota,
                almt_tetap_provinsi, almt_tetap_kodepos, company_name
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            $params = array(
                $id_peg,
                $csv_row['no_ktp'] ?: NULL,
                $csv_row['nama'],
                $csv_row['email_peg'] ?: NULL,
                $csv_row['no_hp_peg'] ?: NULL,
                $tgl_lahir,
                $csv_row['tempat_lahir'] ?: NULL,
                $csv_row['gender'] ?: NULL,
                $csv_row['agama'] ?: NULL,
                $csv_row['status_kawin'] ?: NULL,
                $csv_row['alamat_ktp_peg'] ?: NULL,
                $csv_row['rt'] ?: NULL,
                $csv_row['rw'] ?: NULL,
                $csv_row['kelurahan'] ?: NULL,
                $csv_row['kecamatan'] ?: NULL,
                $csv_row['kota'] ?: NULL,
                $csv_row['provinsi'] ?: NULL,
                $csv_row['kode_pos'] ?: NULL,
                'GRP1',
            );
            
            $stmt = sqlsrv_query($conn, $sql_insert, $params);
            if ($stmt === false) {
                $stats['errors'][] = "ID $id_peg: " . print_r(sqlsrv_errors(), true);
                $status = "<span style='color:red;'>❌ ERROR</span>";
            } else {
                $status = "<span style='color:green;'>✅ SUCCESS</span>";
            }
        } else {
            $status = "<span style='color:blue;'>⏳ (akan INSERT)</span>";
        }
    }
    
    // TAMPILKAN HANYA 100 BARIS PERTAMA DI TABEL
    if ($display_count < 100) {
        echo "<tr>";
        echo "<td>" . ($display_count + 1) . "</td>";
        echo "<td>" . htmlspecialchars($id_peg) . "</td>";
        echo "<td>" . htmlspecialchars(substr($csv_row['nama'], 0, 30)) . "</td>";
        echo "<td><strong>$action_type</strong></td>";
        echo "<td>$status</td>";
        echo "</tr>";
        $display_count++;
    }
}

// Tampilkan info jika ada data yang tidak ditampilkan di tabel
if (count($csv_data) > 100) {
    echo "<tr><td colspan='5' style='text-align:center; font-weight:bold; background:#f0f0f0;'>... dan " . (count($csv_data) - 100) . " records lainnya (proses dilanjutkan)</td></tr>";
}
echo "</table>";
echo "</div>";

// ============================================================
// 4. SUMMARY
// ============================================================
echo "<div style='background:#e8f5e9; padding:15px; margin:20px 0; border-radius:5px; border-left:4px solid #4caf50;'>";
echo "<h3>📊 RINGKASAN MIGRASI</h3>";
echo "<ul>";
echo "<li><strong>Total Records CSV:</strong> " . count($csv_data) . "</li>";
echo "<li><strong>Yang akan di-INSERT (baru):</strong> " . $stats['insert'] . "</li>";
echo "<li><strong>Yang akan di-UPDATE (sudah ada):</strong> " . $stats['update'] . "</li>";
if (!empty($stats['errors'])) {
    echo "<li><strong style='color:red;'>Errors:</strong> " . count($stats['errors']) . "</li>";
    echo "<details><summary>Lihat error details</summary>";
    echo "<pre style='background:#ffebee; padding:10px; overflow-x:auto;'>";
    foreach ($stats['errors'] as $err) {
        echo htmlspecialchars($err) . "\n";
    }
    echo "</pre></details>";
}
echo "</ul>";
echo "</div>";

// ============================================================
// 5. ACTION BUTTONS
// ============================================================
if ($dry_run) {
    echo "<div style='margin:20px 0;'>";
    echo "<a href='?dry_run=0' style='padding:10px 20px; background:#f44336; color:white; text-decoration:none; border-radius:4px; cursor:pointer;' onclick=\"return confirm('Ini akan benar-benar mengubah database. Lanjutkan?');\"><strong>🔴 EXECUTE MIGRASI</strong></a>";
    echo "&nbsp;&nbsp;";
    echo "<a href='compare_data_employee.php' style='padding:10px 20px; background:#2196f3; color:white; text-decoration:none; border-radius:4px;'><strong>📊 Lihat Perbandingan Detail</strong></a>";
    echo "</div>";
} else {
    echo "<div style='background:#c8e6c9; padding:15px; margin:20px 0; border-radius:5px;'>";
    echo "<h3>✅ MIGRASI SELESAI!</h3>";
    echo "<p>Database telah diupdate dengan data dari CSV.</p>";
    echo "<a href='compare_data_employee.php' style='padding:10px 20px; background:#4caf50; color:white; text-decoration:none; border-radius:4px;'><strong>✓ Verifikasi Hasil</strong></a>";
    echo "</div>";
}

sqlsrv_close($conn);
?>
