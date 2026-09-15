<?php
/**
 * analyze_sql_duplicates_detailed.php
 * Analisis duplikat di SQL dengan info lengkap (termasuk data dari zkteco)
 */

include '../config/koneksi_sqlsrv.php';

header('Content-Type: text/html; charset=utf-8');

echo "<h2>🔍 Analisis Duplikat SQL Server (dengan Source Tracking)</h2>";

// ============================================================
// HELPER FUNCTIONS
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
// 1. QUERY DATA DARI SQL
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
    created_at,
    updated_at,
    company_name
FROM dbo.pegawai_lengkap
ORDER BY nama";

$stmt = sqlsrv_query($conn, $sql_query);

if ($stmt === false) {
    die("Query SQL Server gagal: " . print_r(sqlsrv_errors(), true));
}

$sql_data = array();
while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $sql_data[] = $row;
}

echo "<p>Total data di SQL Server: <strong>" . count($sql_data) . "</strong> records</p>";

// ============================================================
// 2. DETEKSI DUPLIKAT DI SQL
// ============================================================
$sql_by_name = array();
foreach ($sql_data as $row) {
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

// ============================================================
// 3. TAMPILKAN DUPLIKAT DENGAN ANALISIS
// ============================================================
echo "<div style='background:#fff3cd; padding:15px; margin:20px 0; border-left:4px solid #ff9800;'>";
echo "<h3>⚠️ Duplikat Ditemukan: " . count($sql_duplicates) . " nama yang duplikat</h3>";

if (count($sql_duplicates) > 0) {
    foreach ($sql_duplicates as $norm_name => $rows) {
        echo "<fieldset style='background:#ffe0b2; padding:12px; margin:15px 0; border:2px solid #ff9800;'>";
        echo "<legend><strong style='font-size:14px;'>📌 $norm_name (" . count($rows) . " records)</strong></legend>";
        
        echo "<table border='1' cellpadding='10' style='width:100%; border-collapse:collapse; font-size:11px;'>";
        echo "<tr style='background:#ffb74d;'>";
        echo "<th>ID PEG (NIK)</th>";
        echo "<th>Nama Asli</th>";
        echo "<th>NO KTP</th>";
        echo "<th>Tgl Lahir</th>";
        echo "<th>Email</th>";
        echo "<th>No HP</th>";
        echo "<th>Alamat</th>";
        echo "<th>Created At</th>";
        echo "<th>Updated At</th>";
        echo "<th>Company</th>";
        echo "</tr>";
        
        foreach ($rows as $idx => $row) {
            // Format DateTime fields
            $created = $row['created_at'];
            if ($created instanceof DateTime) {
                $created = $created->format('Y-m-d H:i');
            } else {
                $created = $created ?? '-';
            }
            
            $updated = $row['updated_at'];
            if ($updated instanceof DateTime) {
                $updated = $updated->format('Y-m-d H:i');
            } else {
                $updated = $updated ?? '-';
            }
            
            $tanggal_lahir = $row['tanggal_lahir'];
            if ($tanggal_lahir instanceof DateTime) {
                $tanggal_lahir = $tanggal_lahir->format('Y-m-d');
            } else {
                $tanggal_lahir = $tanggal_lahir ?? '-';
            }
            
            echo "<tr style='" . ($idx === 0 ? 'background:#ffecb3;' : 'background:#fff9c4;') . "'>";
            echo "<td><strong>" . htmlspecialchars($row['id_peg']) . "</strong><br/>(KTP: " . htmlspecialchars($row['no_ktp'] ?? '-') . ")</td>";
            echo "<td>" . htmlspecialchars($row['nama']) . "</td>";
            echo "<td>" . htmlspecialchars($row['no_ktp'] ?? '-') . "</td>";
            echo "<td>" . htmlspecialchars($tanggal_lahir) . "</td>";
            echo "<td>" . htmlspecialchars($row['email'] ?? '-') . "</td>";
            echo "<td>" . htmlspecialchars($row['no_hp'] ?? '-') . "</td>";
            echo "<td style='font-size:10px;'>" . htmlspecialchars(substr($row['almt_tetap'] ?? '-', 0, 50)) . "...</td>";
            echo "<td style='font-size:10px;'>" . htmlspecialchars($created) . "</td>";
            echo "<td style='font-size:10px;'>" . htmlspecialchars($updated) . "</td>";
            echo "<td>" . htmlspecialchars($row['company_name'] ?? '-') . "</td>";
            echo "</tr>";
        }
        echo "</table>";
        
        // ANALYSIS
        echo "<div style='background:#fff9c4; padding:10px; margin-top:10px; border-left:3px solid #fbc02d;'>";
        echo "<p><strong>🔍 Analisis:</strong></p>";
        echo "<ul style='font-size:12px;'>";
        
        // Check if same no_ktp
        $ktps = array_filter(array_map(function($r) { return $r['no_ktp'] ?? null; }, $rows));
        $unique_ktps = array_unique($ktps);
        if (count($unique_ktps) === 1) {
            echo "<li>✅ <strong>KTP sama:</strong> " . htmlspecialchars(reset($unique_ktps)) . " - Kemungkinan DATA DUPLIKAT</li>";
        } else if (count($ktps) < count($rows)) {
            echo "<li>⚠️ <strong>Ada yang tanpa KTP:</strong> Perlu diisi</li>";
        } else {
            echo "<li>⚠️ <strong>KTP berbeda:</strong> Perlu verifikasi apakah orang yang sama atau berbeda</li>";
        }
        
        // Check tanggal lahir
        $birthdates = array_filter(array_map(function($r) { return $r['tanggal_lahir'] ?? null; }, $rows));
        $unique_birthdates = array_unique($birthdates);
        if (count($unique_birthdates) <= 1) {
            echo "<li>✅ <strong>Tanggal lahir sama/konsisten</strong></li>";
        } else {
            echo "<li>⚠️ <strong>Tanggal lahir berbeda:</strong> " . implode(", ", $unique_birthdates) . "</li>";
        }
        
        // Check timestamps
        $oldest = min(array_map(function($r) { 
            return $r['created_at'] instanceof DateTime ? $r['created_at']->getTimestamp() : 0; 
        }, $rows));
        $newest = max(array_map(function($r) { 
            return $r['updated_at'] instanceof DateTime ? $r['updated_at']->getTimestamp() : 0; 
        }, $rows));
        
        echo "<li>📅 <strong>Timestamp:</strong> Oldest created: " . date('Y-m-d', $oldest) . ", Latest updated: " . date('Y-m-d', $newest) . "</li>";
        echo "</ul>";
        
        echo "<p><strong>💡 Rekomendasi:</strong></p>";
        echo "<ul style='font-size:12px;'>";
        echo "<li>1️⃣ <strong>Verify:</strong> Pastikan orang yang sama atau berbeda</li>";
        echo "<li>2️⃣ <strong>Create Field Baru:</strong> Buat `person_unified_id` untuk group orang yang sama</li>";
        echo "<li>3️⃣ <strong>Track Sources:</strong> Buat mapping untuk track ID dari berbagai source (SQL, Excel, zkteco)</li>";
        echo "<li>4️⃣ <strong>Keep All Data:</strong> Jangan delete - semua diperlukan untuk audit trail</li>";
        echo "</ul>";
        echo "</div>";
        
        echo "</fieldset>";
    }
} else {
    echo "<p>✅ Tidak ada duplikat nama di SQL Server</p>";
}

echo "</div>";

// ============================================================
// 4. RINGKASAN & REKOMENDASI SCHEMA
// ============================================================
echo "<div style='background:#e8f5e9; padding:15px; margin:20px 0; border-left:4px solid #4caf50;'>";
echo "<h3>🏗️ Rekomendasi Schema Baru</h3>";
echo "<p><strong>Tambahkan kolom di tabel pegawai_lengkap:</strong></p>";

echo "<fieldset style='background:#c8e6c9; padding:10px; margin:10px 0;'>";
echo "<legend><strong>Option 1: Unified Person ID (Recommended)</strong></legend>";
echo "<pre style='background:#fff; padding:10px; overflow-x:auto; border:1px solid #4caf50;'>";
echo "-- Kolom baru di pegawai_lengkap
ALTER TABLE dbo.pegawai_lengkap ADD
    person_unified_id NVARCHAR(50) NULL,          -- Universal ID untuk orang yang sama
    id_source NVARCHAR(20) NULL,                   -- Sumber: 'EXCEL', 'SQL', 'ZKTECO', etc
    nik_source NVARCHAR(20) NULL,                  -- Sumber NIK
    is_primary BIT DEFAULT 0,                      -- Flag untuk record primary
    notes NVARCHAR(MAX) NULL;                      -- Catatan untuk consolidation

-- Contoh data:
-- Record 1: person_unified_id='SUDIONO_001', id_source='SQL', nik_source='SQL', is_primary=1
-- Record 2: person_unified_id='SUDIONO_001', id_source='EXCEL', nik_source='EXCEL', is_primary=0
";
echo "</pre>";
echo "</fieldset>";

echo "<fieldset style='background:#c8e6c9; padding:10px; margin:10px 0;'>";
echo "<legend><strong>Option 2: Source Tracking Columns</strong></legend>";
echo "<pre style='background:#fff; padding:10px; overflow-x:auto; border:1px solid #4caf50;'>";
echo "-- Kolom yang track berbagai ID dari berbagai sumber
ALTER TABLE dbo.pegawai_lengkap ADD
    id_peg_sql NVARCHAR(50) NULL,                  -- ID original dari SQL
    id_peg_excel NVARCHAR(50) NULL,                -- ID dari Excel (saat migrasi)
    nik_sql NVARCHAR(50) NULL,                     -- NIK dari SQL
    nik_excel NVARCHAR(50) NULL,                   -- NIK dari Excel
    id_zkteco NVARCHAR(50) NULL,                   -- ID dari mesin zkteco
    consolidation_status NVARCHAR(20) NULL;        -- Status: 'MERGED', 'PENDING', 'VERIFIED'
";
echo "</pre>";
echo "</fieldset>";

echo "<fieldset style='background:#c8e6c9; padding:10px; margin:10px 0;'>";
echo "<legend><strong>Option 3: Separate Mapping Table (Most Flexible)</strong></legend>";
echo "<pre style='background:#fff; padding:10px; overflow-x:auto; border:1px solid #4caf50;'>";
echo "-- Buat table baru untuk mapping
CREATE TABLE dbo.pegawai_id_mapping (
    mapping_id INT IDENTITY(1,1) PRIMARY KEY,
    person_unified_id NVARCHAR(50) NOT NULL,       -- Universal person ID
    id_peg NVARCHAR(50) NOT NULL,                   -- ID yang ada di pegawai_lengkap
    source NVARCHAR(20) NOT NULL,                   -- 'SQL', 'EXCEL', 'ZKTECO'
    is_primary BIT DEFAULT 0,
    created_at DATETIME DEFAULT GETDATE(),
    notes NVARCHAR(MAX),
    UNIQUE(person_unified_id, id_peg, source)
);

-- Contoh:
-- person_unified_id='SUDIONO_B1974', id_peg='2000601', source='SQL', is_primary=1
-- person_unified_id='SUDIONO_B1974', id_peg='2020624', source='EXCEL', is_primary=0
";
echo "</pre>";
echo "</fieldset>";

echo "<p style='margin-top:15px;'><strong>✅ Keuntungan Approach Ini:</strong></p>";
echo "<ul>";
echo "<li>Tidak ada data yang dihapus - semua preserved</li>";
echo "<li>Bisa track semua ID dari berbagai sumber</li>";
echo "<li>Aman untuk data zkteco</li>";
echo "<li>Bisa audit trail lengkap</li>";
echo "<li>Fleksibel untuk future needs</li>";
echo "</ul>";

echo "</div>";

sqlsrv_close($conn);
?>
