<?php
/**
 * verify_consolidation.php
 * Verify hasil consolidation & data integrity
 */

include '../config/koneksi_sqlsrv.php';

header('Content-Type: text/html; charset=utf-8');

echo "<h2>✅ Verify Data Consolidation</h2>";

// ============================================================
// 1. BASIC STATS
// ============================================================
echo "<div style='background:#e3f2fd; padding:15px; margin:20px 0; border-left:4px solid #2196f3;'>";
echo "<h3>📊 Basic Statistics</h3>";

$stats_query = "SELECT 
    COUNT(*) as total_records,
    COUNT(DISTINCT person_unified_id) as unified_persons,
    SUM(CASE WHEN person_unified_id IS NOT NULL THEN 1 ELSE 0 END) as consolidated_records,
    SUM(CASE WHEN is_primary = 1 THEN 1 ELSE 0 END) as primary_records,
    SUM(CASE WHEN is_primary = 0 THEN 1 ELSE 0 END) as duplicate_records
FROM dbo.pegawai_lengkap";

$stmt = sqlsrv_query($conn, $stats_query);
$stats = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);

echo "<table border='1' cellpadding='10' style='width:100%; border-collapse:collapse;'>";
echo "<tr><th>Metric</th><th>Value</th></tr>";
echo "<tr><td>Total Records</td><td><strong>" . $stats['total_records'] . "</strong></td></tr>";
echo "<tr><td>Unified Persons</td><td><strong>" . ($stats['unified_persons'] ?? 0) . "</strong></td></tr>";
echo "<tr><td>Consolidated Records</td><td><strong>" . ($stats['consolidated_records'] ?? 0) . "</strong></td></tr>";
echo "<tr><td>Primary Records</td><td><strong>" . ($stats['primary_records'] ?? 0) . "</strong></td></tr>";
echo "<tr><td>Duplicate Records (flagged)</td><td><strong>" . ($stats['duplicate_records'] ?? 0) . "</strong></td></tr>";
echo "</table>";

echo "</div>";

// ============================================================
// 2. CONSOLIDATED GROUPS
// ============================================================
echo "<div style='background:#fff3cd; padding:15px; margin:20px 0; border-left:4px solid #ff9800;'>";
echo "<h3>🔗 Consolidated Groups</h3>";

$groups_query = "SELECT 
    person_unified_id,
    COUNT(*) as group_size,
    STRING_AGG(nik + '(' + ISNULL(id_source, 'N/A') + ')', ', ') as ids_and_sources
FROM dbo.pegawai_lengkap
WHERE person_unified_id IS NOT NULL
GROUP BY person_unified_id
ORDER BY group_size DESC, person_unified_id";

$stmt = sqlsrv_query($conn, $groups_query);

if ($stmt === false) {
    echo "<p style='color:red;'>Query gagal: " . print_r(sqlsrv_errors(), true) . "</p>";
} else {
    $groups = array();
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $groups[] = $row;
    }
    
    if (count($groups) > 0) {
        echo "<p><strong>" . count($groups) . " groups ditemukan:</strong></p>";
        echo "<table border='1' cellpadding='10' style='width:100%; border-collapse:collapse;'>";
        echo "<tr style='background:#ffb74d;'><th>Unified ID</th><th>Group Size</th><th>Records (ID + Source)</th></tr>";
        
        foreach ($groups as $group) {
            echo "<tr>";
            echo "<td><code>" . htmlspecialchars($group['person_unified_id']) . "</code></td>";
            echo "<td style='text-align:center;'><strong>" . $group['group_size'] . "</strong></td>";
            echo "<td style='font-size:11px;'>" . htmlspecialchars($group['ids_and_sources']) . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p>✅ No consolidated groups found (consolidation not yet executed or no duplicates)</p>";
    }
}

echo "</div>";

// ============================================================
// 3. MAPPING INTEGRITY
// ============================================================
echo "<div style='background:#e8f5e9; padding:15px; margin:20px 0; border-left:4px solid #4caf50;'>";
echo "<h3>🔐 Mapping Integrity Check</h3>";

$mapping_query = "SELECT COUNT(*) as total_mappings FROM dbo.pegawai_id_mapping";
$stmt = sqlsrv_query($conn, $mapping_query);
$mapping_count = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);

echo "<p><strong>Total mappings:</strong> " . ($mapping_count['total_mappings'] ?? 0) . "</p>";

if (($mapping_count['total_mappings'] ?? 0) > 0) {
    $mapping_detail_query = "SELECT 
        person_unified_id,
        COUNT(*) as mapping_count,
        STRING_AGG(source, ', ') as sources,
        SUM(CASE WHEN is_primary = 1 THEN 1 ELSE 0 END) as primary_count
    FROM dbo.pegawai_id_mapping
    GROUP BY person_unified_id
    ORDER BY person_unified_id";
    
    $stmt = sqlsrv_query($conn, $mapping_detail_query);
    
    echo "<table border='1' cellpadding='10' style='width:100%; border-collapse:collapse; font-size:12px;'>";
    echo "<tr style='background:#c8e6c9;'><th>Unified ID</th><th>Mappings</th><th>Sources</th><th>Primary</th><th>Status</th></tr>";
    
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $status = ($row['primary_count'] > 0) ? '✅ OK' : '⚠️ No Primary';
        echo "<tr>";
        echo "<td><code>" . htmlspecialchars($row['person_unified_id']) . "</code></td>";
        echo "<td style='text-align:center;'>" . $row['mapping_count'] . "</td>";
        echo "<td>" . htmlspecialchars($row['sources']) . "</td>";
        echo "<td style='text-align:center;'>" . $row['primary_count'] . "</td>";
        echo "<td>" . $status . "</td>";
        echo "</tr>";
    }
    echo "</table>";
}

echo "</div>";

// ============================================================
// 4. AUDIT TRAIL
// ============================================================
echo "<div style='background:#f3e5f5; padding:15px; margin:20px 0; border-left:4px solid #9c27b0;'>";
echo "<h3>📋 Audit Trail</h3>";

$log_query = "SELECT TOP 20 * FROM dbo.pegawai_consolidation_log ORDER BY created_at DESC";
$stmt = sqlsrv_query($conn, $log_query);

if ($stmt === false) {
    echo "<p style='color:orange;'>Consolidation log table tidak ditemukan (belum di-execute)</p>";
} else {
    $logs = array();
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $logs[] = $row;
    }
    
    if (count($logs) > 0) {
        echo "<p><strong>Recent activities (last 20):</strong></p>";
        echo "<table border='1' cellpadding='10' style='width:100%; border-collapse:collapse; font-size:11px;'>";
        echo "<tr style='background:#e1bee7;'><th>Time</th><th>Unified ID</th><th>Action</th><th>Status</th><th>Details</th></tr>";
        
        foreach ($logs as $log) {
            $time = $log['created_at'];
            if ($time instanceof DateTime) {
                $time = $time->format('Y-m-d H:i:s');
            }
            
            echo "<tr>";
            echo "<td>" . htmlspecialchars($time) . "</td>";
            echo "<td><code>" . htmlspecialchars($log['person_unified_id']) . "</code></td>";
            echo "<td>" . htmlspecialchars($log['action']) . "</td>";
            echo "<td>" . htmlspecialchars($log['status']) . "</td>";
            echo "<td style='font-size:10px;'>" . htmlspecialchars(substr($log['details'] ?? '', 0, 100)) . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p>No audit logs yet</p>";
    }
}

echo "</div>";

// ============================================================
// 5. DATA INTEGRITY CHECKS
// ============================================================
echo "<div style='background:#ffebee; padding:15px; margin:20px 0; border-left:4px solid #f44336;'>";
echo "<h3>🔍 Data Integrity Checks</h3>";

$checks = array();

// Check 1: Missing is_primary in consolidated records
$check1_query = "SELECT COUNT(*) as cnt FROM dbo.pegawai_lengkap WHERE person_unified_id IS NOT NULL AND is_primary IS NULL";
$stmt = sqlsrv_query($conn, $check1_query);
$result = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
$checks[] = array(
    'name' => 'Missing is_primary flags',
    'value' => $result['cnt'],
    'status' => ($result['cnt'] == 0) ? '✅ OK' : '⚠️ ISSUE',
);

// Check 2: Multiple primary records in same group
$check2_query = "SELECT person_unified_id, COUNT(*) as primary_count FROM dbo.pegawai_lengkap WHERE is_primary = 1 GROUP BY person_unified_id HAVING COUNT(*) > 1";
$stmt = sqlsrv_query($conn, $check2_query);
$result = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
$multiple_primary = (sqlsrv_has_rows($stmt)) ? 1 : 0;
$checks[] = array(
    'name' => 'Multiple primary records in same group',
    'value' => $multiple_primary,
    'status' => ($multiple_primary == 0) ? '✅ OK' : '❌ ERROR',
);

// Check 3: Orphaned person_unified_id (ID not in mapping)
$check3_query = "SELECT COUNT(DISTINCT p.person_unified_id) as orphaned FROM dbo.pegawai_lengkap p 
                 WHERE p.person_unified_id IS NOT NULL 
                 AND p.person_unified_id NOT IN (SELECT DISTINCT person_unified_id FROM dbo.pegawai_id_mapping)";
$stmt = sqlsrv_query($conn, $check3_query);
$result = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
$checks[] = array(
    'name' => 'Orphaned unified IDs (not in mapping)',
    'value' => $result['orphaned'],
    'status' => ($result['orphaned'] == 0) ? '✅ OK' : '⚠️ ISSUE',
);

echo "<table border='1' cellpadding='10' style='width:100%; border-collapse:collapse;'>";
echo "<tr style='background:#ef9a9a;'><th>Check</th><th>Result</th><th>Status</th></tr>";

foreach ($checks as $check) {
    echo "<tr>";
    echo "<td>" . htmlspecialchars($check['name']) . "</td>";
    echo "<td><strong>" . $check['value'] . "</strong></td>";
    echo "<td>" . $check['status'] . "</td>";
    echo "</tr>";
}

echo "</table>";

echo "</div>";

// ============================================================
// 6. RECOMMENDATIONS
// ============================================================
echo "<div style='background:#e0f2f1; padding:15px; margin:20px 0; border-left:4px solid #009688;'>";
echo "<h3>💡 Next Steps</h3>";

$total_records = $stats['total_records'] ?? 0;
$consolidated = $stats['consolidated_records'] ?? 0;

if ($consolidated == 0) {
    echo "<p><strong>Status:</strong> Consolidation not yet executed</p>";
    echo "<ol>";
    echo "<li>Run <strong>setup_schema_and_consolidate.php Step 3</strong> to consolidate duplicates</li>";
    echo "<li>Verify results here again</li>";
    echo "<li>Proceed to migration</li>";
    echo "</ol>";
} else if ($consolidated > 0) {
    echo "<p><strong>Status:</strong> Consolidation completed ✅</p>";
    echo "<ol>";
    echo "<li>✅ " . $total_records . " total records</li>";
    echo "<li>✅ " . $consolidated . " records consolidated into " . ($stats['unified_persons'] ?? 0) . " persons</li>";
    echo "<li>✅ Ready for next phase</li>";
    echo "</ol>";
    
    echo "<p style='margin-top:15px;'>";
    echo "<a href='migrate_csv_to_sqlserver.php' style='padding:10px 20px; background:#4caf50; color:white; text-decoration:none; border-radius:4px;'><strong>✓ Proceed to CSV Migration</strong></a>";
    echo "</p>";
}

echo "</div>";

sqlsrv_close($conn);
?>
