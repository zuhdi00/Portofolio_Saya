<?php
/**
 * handle_duplicates.php
 * Handle duplikat data (merge, update, atau keep separate)
 * Dengan preview sebelum eksekusi
 */

include '../config/koneksi_sqlsrv.php';
include '../config/koneksi.php';

header('Content-Type: text/html; charset=utf-8');

echo "<h2>🔧 Handle Duplikat Data Pegawai</h2>";

// ============================================================
// 1. DETECT DUPLICATES
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

// BACA CSV
$csv_file = '../database/DATA KARYAWAN (2).csv';
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

// DETEKSI DUPLIKAT CSV
$csv_by_name = array();
foreach ($csv_data as $r) {
    $norm_name = normalize_name($r['nama']);
    if (!isset($csv_by_name[$norm_name])) {
        $csv_by_name[$norm_name] = array();
    }
    $csv_by_name[$norm_name][] = $r;
}

$csv_duplicates = array();
foreach ($csv_by_name as $norm_name => $rows) {
    if (count($rows) > 1) {
        $csv_duplicates[$norm_name] = $rows;
    }
}

// QUERY SQL DUPLICATES
$sql_query = "SELECT nik as id_peg, no_ktp, nama FROM dbo.pegawai_lengkap ORDER BY nama";
$stmt = sqlsrv_query($conn, $sql_query);
$sql_by_name = array();
while ($r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $norm_name = normalize_name($r['nama']);
    if (!isset($sql_by_name[$norm_name])) {
        $sql_by_name[$norm_name] = array();
    }
    $sql_by_name[$norm_name][] = $r;
}

$sql_duplicates = array();
foreach ($sql_by_name as $norm_name => $rows) {
    if (count($rows) > 1) {
        $sql_duplicates[$norm_name] = $rows;
    }
}

// ============================================================
// 2. TAMPILKAN DUPLIKAT & OPSI
// ============================================================

echo "<div style='background:#fff3cd; padding:15px; margin:20px 0; border-left:4px solid #ff9800;'>";
echo "<h3>📋 Duplikat di CSV (" . count($csv_duplicates) . " nama)</h3>";

if (count($csv_duplicates) > 0) {
    foreach ($csv_duplicates as $norm_name => $rows) {
        echo "<fieldset style='background:#ffe0b2; padding:10px; margin:10px 0; border:1px solid #ff9800;'>";
        echo "<legend><strong>$norm_name</strong></legend>";
        echo "<table border='1' cellpadding='8' style='width:100%; border-collapse:collapse; font-size:12px;'>";
        echo "<tr><th>ID</th><th>NIK</th><th>Nama Asli</th><th>Rekomendasi</th></tr>";
        
        foreach ($rows as $idx => $r) {
            $note = '';
            if (empty($r['no_ktp'])) {
                $note = '<span style="color:red;">❌ NIK kosong!</span>';
            } else {
                $note = '<span style="color:green;">✓ Ada NIK</span>';
            }
            
            echo "<tr>";
            echo "<td>" . htmlspecialchars($r['id_peg']) . "</td>";
            echo "<td>" . htmlspecialchars($r['no_ktp']) . "</td>";
            echo "<td>" . htmlspecialchars($r['nama']) . "</td>";
            echo "<td>$note</td>";
            echo "</tr>";
        }
        echo "</table>";
        
        echo "<div style='margin-top:10px; padding:10px; background:#fff9c4; border-left:2px solid #fbc02d;'>";
        echo "<p><strong>Pilihan:</strong></p>";
        echo "<ul>";
        echo "<li><strong>Merge:</strong> Gabungkan jadi satu record (ID yang mana yang dipakai sebagai primary?)</li>";
        echo "<li><strong>Keep Both:</strong> Tetap pisah tapi tandai kategori 'SAME_PERSON_DIFFERENT_ID'</li>";
        echo "<li><strong>Update NIK:</strong> Jika salah satu NIK kosong, copy dari yang lain</li>";
        echo "</ul>";
        echo "<p style='color:red;'><small>⚠️ Keputusan ini penting karena akan mempengaruhi data integrity!</small></p>";
        echo "</div>";
        echo "</fieldset>";
    }
} else {
    echo "<p>✅ Tidak ada duplikat di CSV</p>";
}
echo "</div>";

// ============================================================
// 3. ANALISIS DUPLIKAT SQL
// ============================================================
echo "<div style='background:#e8f5e9; padding:15px; margin:20px 0; border-left:4px solid #4caf50;'>";
echo "<h3>📋 Duplikat di SQL Server (" . count($sql_duplicates) . " nama)</h3>";

if (count($sql_duplicates) > 0) {
    foreach ($sql_duplicates as $norm_name => $rows) {
        echo "<fieldset style='background:#c8e6c9; padding:10px; margin:10px 0; border:1px solid #4caf50;'>";
        echo "<legend><strong>$norm_name</strong></legend>";
        echo "<table border='1' cellpadding='8' style='width:100%; border-collapse:collapse; font-size:12px;'>";
        echo "<tr><th>ID SQL</th><th>NIK</th><th>Nama</th></tr>";
        
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
    echo "<p>✅ Tidak ada duplikat di SQL Server</p>";
}
echo "</div>";

// ============================================================
// 4. REKOMENDASI ACTION
// ============================================================
echo "<div style='background:#f3e5f5; padding:15px; margin:20px 0; border-left:4px solid #9c27b0;'>";
echo "<h3>💡 Rekomendasi</h3>";
echo "<ol>";
echo "<li><strong>Review setiap duplikat:</strong> Pastikan benar-benar orang yang sama atau berbeda orang</li>";
echo "<li><strong>Untuk DIAN SANTOSO:</strong> ⚠️ Kedua record punya NIK sama (3516062803870001) - ini data anomali!</li>";
echo "<li><strong>Untuk RONI:</strong> ID 40001380 tidak ada NIK - perlu dicek apakah ada di kolom lain</li>";
echo "<li><strong>Decision:</strong>";
echo "<ul>";
echo "<li>Jika duplikat = orang yang sama → <strong>MERGE</strong> dengan ID yang benar</li>";
echo "<li>Jika duplikat = orang berbeda → <strong>KEEP BOTH</strong> dan fix ID/NIK</li>";
echo "<li>Jika ada duplikat NIK → <strong>INVESTIGATE</strong> di source system</li>";
echo "</ul>";
echo "</li>";
echo "</ol>";
echo "</div>";

// ============================================================
// 5. ACTION BUTTONS
// ============================================================
echo "<div style='margin:20px 0;'>";
echo "<a href='migrate_csv_to_sqlserver.php' style='padding:10px 20px; background:#2196f3; color:white; text-decoration:none; border-radius:4px;'><strong>← Kembali ke Migrasi</strong></a>";
echo "&nbsp;&nbsp;";
echo "<a href='find_duplicates_by_name.php' style='padding:10px 20px; background:#4caf50; color:white; text-decoration:none; border-radius:4px;'><strong>📊 Lihat Detail Lengkap</strong></a>";
echo "</div>";

sqlsrv_close($conn);
?>
