<?php
/**
 * find_duplicates_by_name.php
 * Cari data dengan nama yang sama (duplikat) di CSV dan SQL Server
 */

include '../config/koneksi_sqlsrv.php';
include '../config/koneksi.php';

header('Content-Type: text/html; charset=utf-8');

echo "<h2>🔍 Analisis Duplikat Data Pegawai</h2>";

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
        if ($row <= 4) continue;
        if (empty($data[0])) continue;
        
        $csv_data[] = array(
            'id_peg' => trim($data[0]),
            'no_ktp' => trim($data[1]),
            'nama' => trim($data[3]),
        );
    }
    fclose($handle);
}

echo "<p>Total data dari CSV: <strong>" . count($csv_data) . "</strong> records</p>";

// ============================================================
// 2. HELPER FUNCTIONS
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
// 3. CARI DUPLIKAT DI CSV
// ============================================================
echo "<div style='background:#fff3cd; padding:10px; margin:20px 0; border-left:4px solid #ff9800;'>";
echo "<h3>📋 Duplikat di CSV (Nama Sama, ID/NIK Berbeda)</h3>";

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
    echo "<p><strong style='color:red;'>" . count($csv_duplicates) . " nama yang duplikat ditemukan di CSV:</strong></p>";
    foreach ($csv_duplicates as $norm_name => $rows) {
        echo "<fieldset style='background:#ffe0b2; padding:10px; margin:10px 0; border:1px solid #ff9800;'>";
        echo "<legend><strong>Nama: $norm_name (" . count($rows) . " records)</strong></legend>";
        echo "<table border='1' cellpadding='8' style='width:100%; border-collapse:collapse; font-size:12px;'>";
        echo "<tr><th>ID</th><th>NIK</th><th>Nama Original</th></tr>";
        foreach ($rows as $r) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($r['id_peg']) . "</td>";
            echo "<td>" . htmlspecialchars($r['no_ktp']) . "</td>";
            echo "<td>" . htmlspecialchars($r['nama']) . "</td>";
            echo "</tr>";
        }
        echo "</table>";
        echo "</fieldset>";
    }
} else {
    echo "<p><strong>✅ Tidak ada duplikat nama di CSV</strong></p>";
}
echo "</div>";

// ============================================================
// 4. CARI DUPLIKAT DI SQL SERVER
// ============================================================
echo "<div style='background:#e8f5e9; padding:10px; margin:20px 0; border-left:4px solid #4caf50;'>";
echo "<h3>📋 Duplikat di SQL Server (Nama Sama, ID Berbeda)</h3>";

$sql_query = "SELECT nik as id_peg, no_ktp, nama FROM dbo.pegawai_lengkap ORDER BY nama";
$stmt = sqlsrv_query($conn, $sql_query);

if ($stmt === false) {
    die("Query SQL Server gagal: " . print_r(sqlsrv_errors(), true));
}

$sql_by_name = array();
while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $norm_name = normalize_name($row['nama']);
    if (!isset($sql_by_name[$norm_name])) {
        $sql_by_name[$norm_name] = array();
    }
    $sql_by_name[$norm_name][] = $row;
}

$sql_duplicates = array();
foreach ($sql_by_name as $norm_name => $rows) {
    if (count($rows) > 1) {
        $sql_duplicates[$norm_name] = $rows;
    }
}

if (count($sql_duplicates) > 0) {
    echo "<p><strong style='color:red;'>" . count($sql_duplicates) . " nama yang duplikat ditemukan di SQL Server:</strong></p>";
    foreach ($sql_duplicates as $norm_name => $rows) {
        echo "<fieldset style='background:#c8e6c9; padding:10px; margin:10px 0; border:1px solid #4caf50;'>";
        echo "<legend><strong>Nama: $norm_name (" . count($rows) . " records)</strong></legend>";
        echo "<table border='1' cellpadding='8' style='width:100%; border-collapse:collapse; font-size:12px;'>";
        echo "<tr><th>ID</th><th>NIK</th><th>Nama Original</th></tr>";
        foreach ($rows as $r) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($r['id_peg']) . "</td>";
            echo "<td>" . htmlspecialchars($r['no_ktp'] ?? '') . "</td>";
            echo "<td>" . htmlspecialchars($r['nama']) . "</td>";
            echo "</tr>";
        }
        echo "</table>";
        echo "</fieldset>";
    }
} else {
    echo "<p><strong>✅ Tidak ada duplikat nama di SQL Server</strong></p>";
}
echo "</div>";

// ============================================================
// 5. CARI DATA TERTENTU (ZUHDI, PRIO, CHRIS)
// ============================================================
echo "<div style='background:#f3e5f5; padding:10px; margin:20px 0; border-left:4px solid #9c27b0;'>";
echo "<h3>🔎 Pencarian Spesifik: ZUHDI, PRIO, CHRIS</h3>";

$search_names = ['ZUHDI', 'PRIO', 'CHRIS'];
echo "<table border='1' cellpadding='8' style='width:100%; border-collapse:collapse;'>";
echo "<tr><th>Nama Cari</th><th>Sumber</th><th>ID</th><th>NIK</th><th>Nama Lengkap</th></tr>";

foreach ($search_names as $search) {
    $found_csv = false;
    $found_sql = false;
    
    // Cari di CSV
    foreach ($csv_data as $r) {
        if (stripos($r['nama'], $search) !== false) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($search) . "</td>";
            echo "<td><strong style='color:blue;'>CSV</strong></td>";
            echo "<td>" . htmlspecialchars($r['id_peg']) . "</td>";
            echo "<td>" . htmlspecialchars($r['no_ktp']) . "</td>";
            echo "<td>" . htmlspecialchars($r['nama']) . "</td>";
            echo "</tr>";
            $found_csv = true;
        }
    }
    
    // Cari di SQL
    foreach ($sql_by_name as $norm_name => $rows) {
        foreach ($rows as $r) {
            if (stripos($r['nama'], $search) !== false) {
                echo "<tr>";
                echo "<td>" . htmlspecialchars($search) . "</td>";
                echo "<td><strong style='color:green;'>SQL</strong></td>";
                echo "<td>" . htmlspecialchars($r['id_peg']) . "</td>";
                echo "<td>" . htmlspecialchars($r['no_ktp'] ?? '') . "</td>";
                echo "<td>" . htmlspecialchars($r['nama']) . "</td>";
                echo "</tr>";
                $found_sql = true;
            }
        }
    }
    
    if (!$found_csv && !$found_sql) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($search) . "</td>";
        echo "<td colspan='4' style='color:red; text-align:center;'><strong>❌ Tidak ditemukan</strong></td>";
        echo "</tr>";
    }
}

echo "</table>";
echo "</div>";

sqlsrv_close($conn);
?>
