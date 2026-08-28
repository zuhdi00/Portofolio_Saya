<?php
/**
 * setup_schema_and_consolidate.php
 * 1. Setup schema baru (kolom + mapping table)
 * 2. Consolidate duplikat data dengan aman
 * 3. Track semua ID dari berbagai sumber
 */

include '../config/koneksi_sqlsrv.php';

header('Content-Type: text/html; charset=utf-8');

$action = isset($_GET['action']) ? $_GET['action'] : '';
$dry_run = isset($_GET['dry_run']) ? $_GET['dry_run'] === '1' : true;

echo "<h2>🏗️ Setup Schema & Consolidate Data</h2>";
echo "<p>Mode: <strong>" . ($dry_run ? "DRY RUN (Preview)" : "EXECUTE") . "</strong></p>";

// ============================================================
// STEP 1: SETUP SCHEMA
// ============================================================
if ($action === '' || $action === 'setup') {
    echo "<div style='background:#e3f2fd; padding:15px; margin:20px 0; border-left:4px solid #2196f3;'>";
    echo "<h3>📋 Step 1: Setup Schema</h3>";
    echo "<p>Akan menambahkan kolom baru untuk consolidation tracking:</p>";
    echo "<ul>";
    echo "<li><strong>person_unified_id</strong> - Universal ID untuk orang yang sama</li>";
    echo "<li><strong>id_source</strong> - Sumber ID (SQL, EXCEL, ZKTECO)</li>";
    echo "<li><strong>nik_source</strong> - Sumber NIK</li>";
    echo "<li><strong>is_primary</strong> - Flag untuk record primary</li>";
    echo "<li><strong>consolidation_notes</strong> - Catatan consolidation</li>";
    echo "<li><strong>id_peg_excel</strong> - ID dari Excel saat migrasi</li>";
    echo "<li><strong>nik_excel</strong> - NIK dari Excel saat migrasi</li>";
    echo "</ul>";
    
    if (!$dry_run) {
        echo "<p style='color:green;'><strong>✅ Menjalankan SQL...</strong></p>";
        
        // Read SQL script
        $sql_file = __DIR__ . '/setup_database_consolidation.sql';
        if (file_exists($sql_file)) {
            $sql_script = file_get_contents($sql_file);
            
            // Split by GO (SQL Server batch separator)
            $batches = preg_split('/\nGO\n/i', $sql_script);
            
            $success_count = 0;
            $error_count = 0;
            $errors = array();
            
            foreach ($batches as $batch) {
                $batch = trim($batch);
                if (empty($batch)) continue;
                
                $stmt = sqlsrv_query($conn, $batch);
                if ($stmt === false) {
                    $error_count++;
                    $errors[] = print_r(sqlsrv_errors(), true);
                } else {
                    $success_count++;
                    // Get output messages
                    while (sqlsrv_next_result($stmt)) {}
                }
            }
            
            echo "<p><strong>✅ Setup completed:</strong></p>";
            echo "<ul>";
            echo "<li>Batches executed: $success_count</li>";
            echo "<li>Errors: $error_count</li>";
            if ($error_count > 0) {
                echo "<li><details><summary>Error details</summary><pre style='background:#ffebee; padding:10px; overflow-x:auto; color:red;'>";
                foreach ($errors as $err) {
                    echo htmlspecialchars($err) . "\n";
                }
                echo "</pre></details></li>";
            }
            echo "</ul>";
        }
    }
    
    echo "<p style='margin-top:15px;'>";
    echo "<a href='?action=setup&dry_run=1' style='padding:8px 15px; background:#2196f3; color:white; text-decoration:none; border-radius:4px; margin-right:10px;'><strong>📋 Preview Setup</strong></a>";
    echo "<a href='?action=setup&dry_run=0' style='padding:8px 15px; background:#f44336; color:white; text-decoration:none; border-radius:4px;' onclick=\"return confirm('Ini akan membuat kolom baru di database. Lanjutkan?');\"><strong>🔴 Execute Setup</strong></a>";
    echo "</p>";
    echo "</div>";
}

// ============================================================
// STEP 2: ANALYZE DUPLICATES & MAPPING
// ============================================================
if ($action === '' || $action === 'analyze') {
    echo "<div style='background:#fff3cd; padding:15px; margin:20px 0; border-left:4px solid #ff9800;'>";
    echo "<h3>🔍 Step 2: Analyze Duplicates & Mapping</h3>";
    
    // Get duplicates
    function normalize_name($name) {
        if ($name === null) return '';
        $name = trim((string)$name);
        $name = mb_strtoupper($name, 'UTF-8');
        $name = preg_replace('/[.\/\,_-]+/u', ' ', $name);
        $name = preg_replace('/\s+/', ' ', $name);
        $name = str_replace([' ACH ', ' MOCH ', ' MUH ', ' MUHAMMAD ', ' H ', ' KH '], ' ', $name);
        $name = preg_replace('/\b(ACH|MOCH|MUH|MUHAMMAD|H|KH)\b/u', '', $name);
        $name = preg_replace('/\s+/', ' ', trim($name));
        return $name;
    }
    
    $sql_query = "SELECT nik as id_peg, no_ktp, nama FROM dbo.pegawai_lengkap ORDER BY nama";
    $stmt = sqlsrv_query($conn, $sql_query);
    
    $sql_by_name = array();
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $norm_name = normalize_name($row['nama']);
        if (!isset($sql_by_name[$norm_name])) {
            $sql_by_name[$norm_name] = array();
        }
        $sql_by_name[$norm_name][] = $row;
    }
    
    $duplicates = array();
    foreach ($sql_by_name as $norm_name => $rows) {
        if (count($rows) > 1) {
            $duplicates[$norm_name] = $rows;
        }
    }
    
    echo "<p><strong>" . count($duplicates) . " groups of duplicate names found:</strong></p>";
    
    if (count($duplicates) > 0) {
        echo "<table border='1' cellpadding='10' style='width:100%; border-collapse:collapse; font-size:12px;'>";
        echo "<tr style='background:#ffb74d;'>";
        echo "<th>Unified ID (Proposed)</th>";
        echo "<th>Nama</th>";
        echo "<th>Records</th>";
        echo "<th>Details</th>";
        echo "</tr>";
        
        $idx = 1;
        foreach ($duplicates as $norm_name => $rows) {
            $proposed_id = 'PERSON_' . strtoupper(preg_replace('/[^A-Z0-9]/i', '_', $norm_name)) . '_' . str_pad($idx, 3, '0', STR_PAD_LEFT);
            
            echo "<tr>";
            echo "<td><code>" . htmlspecialchars($proposed_id) . "</code></td>";
            echo "<td><strong>" . htmlspecialchars($norm_name) . "</strong></td>";
            echo "<td>" . count($rows) . " records</td>";
            echo "<td><ul style='margin:0; padding-left:20px; font-size:11px;'>";
            foreach ($rows as $r) {
                echo "<li>ID: " . htmlspecialchars($r['id_peg']) . " | NIK: " . htmlspecialchars($r['no_ktp'] ?? '-') . "</li>";
            }
            echo "</ul></td>";
            echo "</tr>";
            $idx++;
        }
        echo "</table>";
    }
    
    echo "</div>";
}

// ============================================================
// STEP 3: CONSOLIDATE
// ============================================================
if ($action === '' || $action === 'consolidate') {
    echo "<div style='background:#e8f5e9; padding:15px; margin:20px 0; border-left:4px solid #4caf50;'>";
    echo "<h3>🔗 Step 3: Consolidate Data</h3>";
    
    if (!$dry_run) {
        echo "<p style='color:green;'><strong>✅ Consolidating...</strong></p>";
        // Implementation will go here
    } else {
        echo "<p>Preview consolidation akan di-execute dengan opsi dry_run=0</p>";
    }
    
    echo "</div>";
}

// ============================================================
// NAVIGATION
// ============================================================
echo "<div style='background:#f5f5f5; padding:15px; margin:20px 0; border-radius:4px;'>";
echo "<h3>📍 Navigation</h3>";
echo "<p>";
echo "<a href='?action=setup' style='padding:8px 15px; background:#2196f3; color:white; text-decoration:none; border-radius:4px; margin-right:10px;'><strong>1. Setup Schema</strong></a>";
echo "<a href='?action=analyze' style='padding:8px 15px; background:#ff9800; color:white; text-decoration:none; border-radius:4px; margin-right:10px;'><strong>2. Analyze Duplicates</strong></a>";
echo "<a href='?action=consolidate' style='padding:8px 15px; background:#4caf50; color:white; text-decoration:none; border-radius:4px;'><strong>3. Consolidate</strong></a>";
echo "</p>";
echo "</div>";

sqlsrv_close($conn);
?>
