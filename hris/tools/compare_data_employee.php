<?php
/**
 * compare_data_employee.php
 * Membandingkan data pegawai antara CSV dan SQL Server
 * Case-insensitive comparison untuk nama dan alamat
 */

include '../config/koneksi_sqlsrv.php';   // $conn untuk dbHR
include '../config/koneksi.php';         // $mysqli untuk MySQL hris

header('Content-Type: text/html; charset=utf-8');

// ============================================================
// 1. BACA CSV
// ============================================================
$csv_file = '../database/DATA KARYAWAN (2).csv';

if (!file_exists($csv_file)) {
    die("CSV file tidak ditemukan: $csv_file");
}

$csv_data = array();
$row = 0;

if (($handle = fopen($csv_file, 'r')) !== FALSE) {
    while (($data = fgetcsv($handle, 1000, ',')) !== FALSE) {
        $row++;
        if ($row <= 4) continue;  // Skip header (baris 1-4)
        if (empty($data[0])) continue;  // Skip baris kosong
        
        $csv_data[] = array(
            'id_peg' => $data[0],
            'no_ktp' => trim($data[1]),
            'no' => $data[2],
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

echo "<h2>📊 Laporan Perbandingan Data Pegawai</h2>";
echo "<p>Total data dari CSV: <strong>" . count($csv_data) . "</strong> records</p>";

// ============================================================
// 2. QUERY DATA DARI SQL SERVER
// ============================================================
$sql_query = "SELECT 
    nik as id_peg,
    no_ktp,
    nama,
    email,
    no_hp,
    tanggal_lahir,
    tempat_lahir,
    gender,
    agama,
    status_kawin,
    almt_tetap,
    almt_tetap_rt,
    almt_tetap_rw,
    almt_tetap_desa,
    almt_tetap_kecamatan,
    almt_tetap_kota,
    almt_tetap_provinsi,
    almt_tetap_kodepos
FROM dbo.pegawai_lengkap
ORDER BY nik";

$stmt = sqlsrv_query($conn, $sql_query);

if ($stmt === false) {
    die("Query SQL Server gagal: " . print_r(sqlsrv_errors(), true));
}

/**
 * Helper: Convert DateTime objects to string
 */
function normalize_value($value) {
    if ($value instanceof DateTime) {
        return $value->format('Y-m-d');  // Convert DateTime to Y-m-d format
    }
    return (string)$value;
}

/**
 * Helper: Parse tanggal dari berbagai format ke YYYY-MM-DD
 * Supports: DD/MM/YYYY, YYYY-MM-DD, DD-MM-YYYY, MM/DD/YYYY
 */
function normalize_date($date_str) {
    if (empty($date_str) || $date_str === '0000-00-00' || $date_str === '0000-01-01') {
        return '';
    }
    
    $date_str = trim($date_str);
    
    // Jika sudah format YYYY-MM-DD, return as is
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_str)) {
        return $date_str;
    }
    
    // Parse DD/MM/YYYY atau DD-MM-YYYY
    if (preg_match('/^(\d{2})[\/-](\d{2})[\/-](\d{4})$/', $date_str, $matches)) {
        $day = $matches[1];
        $month = $matches[2];
        $year = $matches[3];
        
        // Validate
        if (checkdate((int)$month, (int)$day, (int)$year)) {
            return "$year-$month-$day";
        }
    }
    
    // Try PHP strtotime as fallback
    $timestamp = strtotime($date_str);
    if ($timestamp !== false) {
        return date('Y-m-d', $timestamp);
    }
    
    // Return original if parsing fails
    return $date_str;
}

function normalize_name($name) {
    if ($name === null) {
        return '';
    }

    $name = trim((string)$name);
    if ($name === '') {
        return '';
    }

    $name = mb_strtoupper($name, 'UTF-8');
    $name = preg_replace('/[.\/\,_-]+/u', ' ', $name);
    $name = preg_replace('/\s+/', ' ', $name);
    $name = str_replace([' ACH ', ' MOCH ', ' MUH ', ' MUHAMMAD ', ' H ', ' KH '], ' ', $name);
    $name = preg_replace('/\b(ACH|MOCH|MUH|MUHAMMAD|H|KH)\b/u', '', $name);
    $name = preg_replace('/\s+/', ' ', trim($name));

    return $name;
}

function build_person_key($nama, $tanggal_lahir) {
    $nama_norm = normalize_name($nama);
    $tgl_norm = normalize_date($tanggal_lahir);

    if ($nama_norm === '' && $tgl_norm === '') {
        return '';
    }

    return $nama_norm . '|' . $tgl_norm;
}

function names_match($left, $right) {
    $left = normalize_name($left);
    $right = normalize_name($right);

    if ($left === '' && $right === '') {
        return true;
    }
    if ($left === '' || $right === '') {
        return false;
    }
    if (strtolower($left) === strtolower($right)) {
        return true;
    }

    if (strpos($left, $right) !== false || strpos($right, $left) !== false) {
        return true;
    }

    similar_text($left, $right, $percent);
    if ($percent >= 85) {
        return true;
    }

    $left_tokens = preg_split('/\s+/', $left);
    $right_tokens = preg_split('/\s+/', $right);
    if (count($left_tokens) > 0 && count($right_tokens) > 0) {
        $shared = array_intersect($left_tokens, $right_tokens);
        $left_common = count($shared) / max(count(array_unique($left_tokens)), 1);
        $right_common = count($shared) / max(count(array_unique($right_tokens)), 1);

        if ($left_common >= 0.6 || $right_common >= 0.6) {
            return true;
        }
    }

    return false;
}

function is_missing_date_value($value) {
    if ($value === null) {
        return true;
    }

    $value = trim((string)$value);
    if ($value === '' || strtolower($value) === 'null' || $value === '0000-00-00' || $value === '0000-01-01') {
        return true;
    }

    return false;
}

$sql_data = array();
while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    // Normalize all DateTime objects to strings
    $normalized_row = array();
    foreach ($row as $key => $value) {
        $normalized_row[$key] = normalize_value($value);
    }
    $sql_data[$normalized_row['id_peg']] = $normalized_row;
}

echo "<p>Total data dari SQL Server: <strong>" . count($sql_data) . "</strong> records</p>";

// ============================================================
// 3. PERBANDINGAN
// ============================================================
$report = array(
    'sama' => array(),
    'same_person_different_id' => array(),
    'duplikat_nama' => array(),
    'berbeda' => array(),
    'hanya_csv' => array(),
    'hanya_sql' => array(),
);

$matched_sql_ids = array();
$matched_csv_ids = array();
$sql_person_index = array();
foreach ($sql_data as $sql_id => $sql_row) {
    $person_key = build_person_key($sql_row['nama'], $sql_row['tanggal_lahir']);
    if ($person_key !== '') {
        $sql_person_index[$person_key] = $sql_id;
    }
}

// Cek data di CSV
foreach ($csv_data as $csv_row) {
    $id = $csv_row['id_peg'];
    $person_key = build_person_key($csv_row['nama'], $csv_row['tgl_lahir']);

    if (isset($sql_data[$id])) {
        $sql_row = $sql_data[$id];
        $matched_csv_ids[$id] = true;
        $matched_sql_ids[$id] = true;

        // Bandingkan (case-insensitive)
        $matches = true;
        $name_matches = names_match($csv_row['nama'], $sql_row['nama']);
        $diff_fields = array();

        $compare_fields = array(
            'no_ktp' => 'no_ktp',
            'nama' => 'nama',
            'email_peg' => 'email',
            'no_hp_peg' => 'no_hp',
            'tgl_lahir' => 'tanggal_lahir',
            'tempat_lahir' => 'tempat_lahir',
            'gender' => 'gender',
            'agama' => 'agama',
            'status_kawin' => 'status_kawin',
            'alamat_ktp_peg' => 'almt_tetap',
            'rt' => 'almt_tetap_rt',
            'rw' => 'almt_tetap_rw',
            'kelurahan' => 'almt_tetap_desa',
            'kecamatan' => 'almt_tetap_kecamatan',
            'kota' => 'almt_tetap_kota',
            'provinsi' => 'almt_tetap_provinsi',
            'kode_pos' => 'almt_tetap_kodepos',
        );

        foreach ($compare_fields as $csv_field => $sql_field) {
            $csv_val = isset($csv_row[$csv_field]) ? trim((string)$csv_row[$csv_field]) : '';
            $sql_val = isset($sql_row[$sql_field]) ? trim((string)$sql_row[$sql_field]) : '';

            if (empty($csv_val) && empty($sql_val)) {
                continue;
            }

            if ($csv_field === 'tgl_lahir') {
                $csv_val = normalize_date($csv_val);
                $sql_val = normalize_date($sql_val);

                if (is_missing_date_value($csv_val) || is_missing_date_value($sql_val)) {
                    if (is_missing_date_value($csv_val) && is_missing_date_value($sql_val)) {
                        continue;
                    }
                    continue;
                }
            }

            if ($csv_field === 'nama') {
                if (!$name_matches) {
                    $matches = false;
                    $diff_fields[] = array(
                        'field' => $csv_field,
                        'csv_value' => $csv_val,
                        'sql_value' => $sql_val,
                    );
                }
                continue;
            }

            if (strtolower($csv_val) !== strtolower($sql_val)) {
                $matches = false;
                $diff_fields[] = array(
                    'field' => $csv_field,
                    'csv_value' => $csv_val,
                    'sql_value' => $sql_val,
                );
            }
        }

        if ($matches) {
            $report['sama'][] = $id;
        } elseif ($name_matches) {
            $report['duplikat_nama'][] = array(
                'id' => $id,
                'nama' => $csv_row['nama'],
                'differences' => $diff_fields,
            );
        } else {
            $report['berbeda'][] = array(
                'id' => $id,
                'nama' => $csv_row['nama'],
                'differences' => $diff_fields,
            );
        }

        continue;
    }

    if ($person_key !== '' && isset($sql_person_index[$person_key])) {
        $sql_id_match = $sql_person_index[$person_key];
        if (!isset($matched_sql_ids[$sql_id_match])) {
            $report['same_person_different_id'][] = array(
                'csv_id' => $id,
                'sql_id' => $sql_id_match,
                'csv_nik' => $csv_row['no_ktp'],
                'sql_nik' => $sql_data[$sql_id_match]['no_ktp'] ?? '',
                'nama' => $csv_row['nama'],
                'field_baru' => 'id_peg / no_ktp',
                'status' => 'SAME_PERSON_DIFFERENT_ID',
            );
            $matched_csv_ids[$id] = true;
            $matched_sql_ids[$sql_id_match] = true;
        }
        continue;
    }

    $report['hanya_csv'][] = $csv_row;
    $matched_csv_ids[$id] = true;
}

// Cek data hanya di SQL
foreach ($sql_data as $id => $sql_row) {
    if (!isset($matched_sql_ids[$id])) {
        $report['hanya_sql'][] = array(
            'id_peg' => $id,
            'nama' => $sql_row['nama'],
        );
    }
}

// ============================================================
// 4. TAMPILKAN REPORT
// ============================================================
echo "<div style='background:#e8f5e9; padding:10px; margin:10px 0; border-left:4px solid #4caf50;'>";
echo "<h3>✅ Data Sama (ID/NIK sama): " . count($report['sama']) . "</h3>";
if (count($report['sama']) > 0) {
    echo "<ul>";
    foreach (array_slice($report['sama'], 0, 10) as $id) {
        echo "<li>ID: $id</li>";
    }
    if (count($report['sama']) > 10) {
        echo "<li>... dan " . (count($report['sama']) - 10) . " lainnya</li>";
    }
    echo "</ul>";
}
echo "</div>";

echo "<div style='background:#fff8e1; padding:10px; margin:10px 0; border-left:4px solid #ffb300;'>";
echo "<h3>⚠️ Data Sama Orang Tapi ID/NIK Berbeda: " . count($report['same_person_different_id']) . "</h3>";
if (count($report['same_person_different_id']) > 0) {
    echo "<table border='1' cellpadding='10' style='width:100%; border-collapse:collapse;'>";
    echo "<tr><th>Status</th><th>Field Baru</th><th>ID CSV</th><th>ID SQL</th><th>NIK CSV</th><th>NIK SQL</th><th>Nama</th></tr>";
    foreach (array_slice($report['same_person_different_id'], 0, 20) as $item) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($item['status']) . "</td>";
        echo "<td>" . htmlspecialchars($item['field_baru']) . "</td>";
        echo "<td>" . htmlspecialchars($item['csv_id']) . "</td>";
        echo "<td>" . htmlspecialchars($item['sql_id']) . "</td>";
        echo "<td>" . htmlspecialchars($item['csv_nik']) . "</td>";
        echo "<td>" . htmlspecialchars($item['sql_nik']) . "</td>";
        echo "<td>" . htmlspecialchars($item['nama']) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    if (count($report['same_person_different_id']) > 20) {
        echo "<p>... dan " . (count($report['same_person_different_id']) - 20) . " lainnya</p>";
    }
}
echo "</div>";

echo "<div style='background:#fff3cd; padding:10px; margin:10px 0; border-left:4px solid #ff9800;'>";
echo "<h3>⚠️ Data Nama Sama Tapi Ditulis Berbeda: " . count($report['duplikat_nama']) . "</h3>";
if (count($report['duplikat_nama']) > 0) {
    echo "<table border='1' cellpadding='10' style='width:100%; border-collapse:collapse;'>";
    echo "<tr><th>ID</th><th>Nama CSV</th><th>Field</th><th>CSV Value</th><th>SQL Value</th></tr>";
    foreach (array_slice($report['duplikat_nama'], 0, 20) as $item) {
        foreach ($item['differences'] as $diff) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($item['id']) . "</td>";
            echo "<td>" . htmlspecialchars($item['nama']) . "</td>";
            echo "<td>" . htmlspecialchars($diff['field']) . "</td>";
            echo "<td style='color:blue;'>" . htmlspecialchars($diff['csv_value']) . "</td>";
            echo "<td style='color:red;'>" . htmlspecialchars($diff['sql_value']) . "</td>";
            echo "</tr>";
        }
    }
    echo "</table>";
    if (count($report['duplikat_nama']) > 20) {
        echo "<p>... dan " . (count($report['duplikat_nama']) - 20) . " lainnya</p>";
    }
}
echo "</div>";

echo "<div style='background:#fff3cd; padding:10px; margin:10px 0; border-left:4px solid #ff9800;'>";
echo "<h3>⚠️ Data Berbeda: " . count($report['berbeda']) . "</h3>";
if (count($report['berbeda']) > 0) {
    echo "<table border='1' cellpadding='10' style='width:100%; border-collapse:collapse;'>";
    echo "<tr><th>ID</th><th>Nama</th><th>Field</th><th>CSV Value</th><th>SQL Value</th></tr>";
    foreach (array_slice($report['berbeda'], 0, 20) as $item) {
        foreach ($item['differences'] as $diff) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($item['id']) . "</td>";
            echo "<td>" . htmlspecialchars($item['nama']) . "</td>";
            echo "<td>" . htmlspecialchars($diff['field']) . "</td>";
            echo "<td style='color:blue;'>" . htmlspecialchars($diff['csv_value']) . "</td>";
            echo "<td style='color:red;'>" . htmlspecialchars($diff['sql_value']) . "</td>";
            echo "</tr>";
        }
    }
    echo "</table>";
    if (count($report['berbeda']) > 20) {
        echo "<p>... dan " . (count($report['berbeda']) - 20) . " lainnya</p>";
    }
}
echo "</div>";

echo "<div style='background:#ffebee; padding:10px; margin:10px 0; border-left:4px solid #f44336;'>";
echo "<h3>❌ Hanya di CSV (Belum di SQL): " . count($report['hanya_csv']) . "</h3>";
if (count($report['hanya_csv']) > 0) {
    echo "<table border='1' cellpadding='10' style='width:100%; border-collapse:collapse;'>";
    echo "<tr><th>ID</th><th>Nama</th><th>Email</th><th>No HP</th></tr>";
    foreach (array_slice($report['hanya_csv'], 0, 10) as $row) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($row['id_peg']) . "</td>";
        echo "<td>" . htmlspecialchars($row['nama']) . "</td>";
        echo "<td>" . htmlspecialchars($row['email_peg']) . "</td>";
        echo "<td>" . htmlspecialchars($row['no_hp_peg']) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    if (count($report['hanya_csv']) > 10) {
        echo "<p>... dan " . (count($report['hanya_csv']) - 10) . " lainnya</p>";
    }
}
echo "</div>";

echo "<div style='background:#e3f2fd; padding:10px; margin:10px 0; border-left:4px solid #2196f3;'>";
echo "<h3>ℹ️ Hanya di SQL (Tidak ada di CSV): " . count($report['hanya_sql']) . "</h3>";
if (count($report['hanya_sql']) > 0) {
    echo "<table border='1' cellpadding='10' style='width:100%; border-collapse:collapse;'>";
    echo "<tr><th>ID</th><th>Nama</th></tr>";
    foreach (array_slice($report['hanya_sql'], 0, 10) as $row) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($row['id_peg']) . "</td>";
        echo "<td>" . htmlspecialchars($row['nama']) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    if (count($report['hanya_sql']) > 10) {
        echo "<p>... dan " . (count($report['hanya_sql']) - 10) . " lainnya</p>";
    }
}
echo "</div>";

// ============================================================
// 5. SUMMARY
// ============================================================
echo "<div style='background:#f5f5f5; padding:15px; margin:20px 0; border-radius:5px;'>";
echo "<h3>📈 RINGKASAN</h3>";
echo "<ul>";
echo "<li><strong>Total CSV:</strong> " . count($csv_data) . " records</li>";
echo "<li><strong>Total SQL Server:</strong> " . count($sql_data) . " records</li>";
echo "<li><strong>Data Matching (ID/NIK sama):</strong> " . count($report['sama']) . " (" . round((count($report['sama'])/max(count($csv_data),1))*100, 2) . "%)</li>";
echo "<li><strong>Data Sama Orang Tapi ID/NIK Berbeda:</strong> " . count($report['same_person_different_id']) . "</li>";
echo "<li><strong>Nama Sama Tapi Ditulis Berbeda:</strong> " . count($report['duplikat_nama']) . "</li>";
echo "<li><strong>Data Berbeda:</strong> " . count($report['berbeda']) . "</li>";
echo "<li><strong>Hanya di CSV:</strong> " . count($report['hanya_csv']) . "</li>";
echo "<li><strong>Hanya di SQL:</strong> " . count($report['hanya_sql']) . "</li>";
echo "</ul>";
echo "</div>";

sqlsrv_close($conn);
?>
