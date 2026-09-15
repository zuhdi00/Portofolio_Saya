<?php
/**
 * verify_migration.php
 * Verifikasi status migrasi dan kondisi database
 */

include '../config/koneksi_sqlsrv.php';   // $conn untuk dbHR
include '../config/koneksi.php';         // $mysqli untuk MySQL

header('Content-Type: text/html; charset=utf-8');

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verifikasi Migrasi Data</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
            background: #f5f5f5;
        }
        .container {
            max-width: 1000px;
            margin: 0 auto;
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1 {
            color: #333;
            border-bottom: 3px solid #667eea;
            padding-bottom: 10px;
        }
        h2 {
            color: #667eea;
            margin-top: 30px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 15px 0;
        }
        th, td {
            border: 1px solid #ddd;
            padding: 12px;
            text-align: left;
        }
        th {
            background: #667eea;
            color: white;
        }
        tr:nth-child(even) {
            background: #f9f9f9;
        }
        .status-good {
            color: green;
            font-weight: bold;
        }
        .status-warning {
            color: orange;
            font-weight: bold;
        }
        .status-error {
            color: red;
            font-weight: bold;
        }
        .stat-box {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin: 20px 0;
        }
        .stat-item {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 8px;
            text-align: center;
        }
        .stat-item .number {
            font-size: 2.5em;
            font-weight: bold;
            margin-bottom: 10px;
        }
        .stat-item .label {
            font-size: 0.9em;
            opacity: 0.9;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>✓ Verifikasi Status Migrasi Data Pegawai</h1>
        <p>Laporan real-time kondisi database SQL Server (dbHR)</p>

        <?php
        // ============================================================
        // 1. QUERY DATABASE STATS
        // ============================================================

        // Total records
        $sql_total = "SELECT COUNT(*) as total FROM dbo.pegawai_lengkap";
        $stmt = sqlsrv_query($conn, $sql_total);
        $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
        $total_records = $row['total'];

        // Records dengan NIK unique
        $sql_unique = "SELECT COUNT(DISTINCT nik) as unique_nik FROM dbo.pegawai_lengkap";
        $stmt = sqlsrv_query($conn, $sql_unique);
        $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
        $unique_nik = $row['unique_nik'];

        // Records yang sudah di-update hari ini
        $sql_today = "SELECT COUNT(*) as today_updates FROM dbo.pegawai_lengkap WHERE CAST(updated_at AS DATE) = CAST(GETDATE() AS DATE)";
        $stmt = sqlsrv_query($conn, $sql_today);
        $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
        $today_updates = $row['today_updates'];

        // Records dengan data incomplete (NULL di field penting)
        $sql_incomplete = "SELECT COUNT(*) as incomplete FROM dbo.pegawai_lengkap WHERE nama IS NULL OR nik IS NULL";
        $stmt = sqlsrv_query($conn, $sql_incomplete);
        $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
        $incomplete = $row['incomplete'];

        // Recent updates
        $sql_recent = "SELECT TOP 10 nik, nama, updated_at FROM dbo.pegawai_lengkap ORDER BY updated_at DESC";
        $stmt = sqlsrv_query($conn, $sql_recent);
        $recent_updates = array();
        while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $recent_updates[] = $row;
        }

        // Data by status
        $sql_status = "SELECT COUNT(*) as count FROM dbo.pegawai_lengkap WHERE status_kawin IS NULL";
        $stmt = sqlsrv_query($conn, $sql_status);
        $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
        $null_status = $row['count'];

        // Duplicates check
        $sql_dup = "SELECT nik, COUNT(*) as cnt FROM dbo.pegawai_lengkap GROUP BY nik HAVING COUNT(*) > 1";
        $stmt = sqlsrv_query($conn, $sql_dup);
        $duplicates = array();
        while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $duplicates[] = $row;
        }

        // ============================================================
        // 2. DISPLAY STATS
        // ============================================================
        ?>

        <div class="stat-box">
            <div class="stat-item">
                <div class="number"><?php echo $total_records; ?></div>
                <div class="label">Total Records</div>
            </div>
            <div class="stat-item">
                <div class="number"><?php echo $unique_nik; ?></div>
                <div class="label">Unique NIK</div>
            </div>
            <div class="stat-item">
                <div class="number"><?php echo $today_updates; ?></div>
                <div class="label">Updated Today</div>
            </div>
            <div class="stat-item">
                <div class="number"><?php echo $incomplete; ?></div>
                <div class="label">Incomplete Data</div>
            </div>
        </div>

        <h2>📊 Status Database</h2>
        <table>
            <tr>
                <th>Item</th>
                <th>Value</th>
                <th>Status</th>
            </tr>
            <tr>
                <td>Total Records</td>
                <td><?php echo $total_records; ?></td>
                <td class="<?php echo ($total_records > 0) ? 'status-good' : 'status-error'; ?>">
                    <?php echo ($total_records > 0) ? '✓ OK' : '✗ EMPTY'; ?>
                </td>
            </tr>
            <tr>
                <td>Unique NIK (No Duplicates)</td>
                <td><?php echo $unique_nik; ?></td>
                <td class="<?php echo ($unique_nik == $total_records) ? 'status-good' : 'status-error'; ?>">
                    <?php echo ($unique_nik == $total_records) ? '✓ OK' : '✗ ADA DUPLIKAT'; ?>
                </td>
            </tr>
            <tr>
                <td>Incomplete Records</td>
                <td><?php echo $incomplete; ?></td>
                <td class="<?php echo ($incomplete == 0) ? 'status-good' : 'status-warning'; ?>">
                    <?php echo ($incomplete == 0) ? '✓ OK' : '⚠ ADA ' . $incomplete; ?>
                </td>
            </tr>
            <tr>
                <td>NULL Status Kawin</td>
                <td><?php echo $null_status; ?></td>
                <td class="<?php echo ($null_status == 0) ? 'status-good' : 'status-warning'; ?>">
                    <?php echo ($null_status == 0) ? '✓ OK' : '⚠ ADA ' . $null_status; ?>
                </td>
            </tr>
        </table>

        <h2>🔄 Update Terakhir (10 records)</h2>
        <table>
            <tr>
                <th>No</th>
                <th>NIK</th>
                <th>Nama</th>
                <th>Updated At</th>
            </tr>
            <?php
            foreach ($recent_updates as $idx => $row) {
                $date = $row['updated_at']->format('d/m/Y H:i:s');
                echo "<tr>";
                echo "<td>" . ($idx + 1) . "</td>";
                echo "<td>" . htmlspecialchars($row['nik']) . "</td>";
                echo "<td>" . htmlspecialchars($row['nama']) . "</td>";
                echo "<td>" . $date . "</td>";
                echo "</tr>";
            }
            ?>
        </table>

        <?php if (!empty($duplicates)) { ?>
        <h2>⚠️ Data Duplikat Ditemukan</h2>
        <table>
            <tr>
                <th>NIK</th>
                <th>Jumlah Duplikat</th>
            </tr>
            <?php foreach ($duplicates as $dup) { ?>
            <tr>
                <td><?php echo htmlspecialchars($dup['nik']); ?></td>
                <td><span class="status-error"><?php echo $dup['cnt']; ?> records</span></td>
            </tr>
            <?php } ?>
        </table>
        <?php } ?>

        <h2>📋 Data Completeness</h2>
        <?php
        // Check completeness per field
        $fields_to_check = array(
            'nik' => 'NIK',
            'nama' => 'Nama',
            'no_ktp' => 'No. KTP',
            'email' => 'Email',
            'no_hp' => 'No. HP',
            'tanggal_lahir' => 'Tanggal Lahir',
            'agama' => 'Agama',
            'almt_tetap' => 'Alamat Tetap',
        );

        $completeness_data = array();

        foreach ($fields_to_check as $field => $label) {
            $sql_check = "SELECT COUNT(*) as not_null FROM dbo.pegawai_lengkap WHERE $field IS NOT NULL AND $field != ''";
            $stmt = sqlsrv_query($conn, $sql_check);
            $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
            $filled = $row['not_null'];
            $percentage = round(($filled / max($total_records, 1)) * 100, 1);
            $completeness_data[] = array(
                'field' => $label,
                'filled' => $filled,
                'percentage' => $percentage,
            );
        }
        ?>

        <table>
            <tr>
                <th>Field</th>
                <th>Filled</th>
                <th>Percentage</th>
                <th>Bar</th>
            </tr>
            <?php foreach ($completeness_data as $item) { ?>
            <tr>
                <td><?php echo $item['field']; ?></td>
                <td><?php echo $item['filled']; ?> / <?php echo $total_records; ?></td>
                <td><?php echo $item['percentage']; ?>%</td>
                <td>
                    <div style="background: #e0e0e0; height: 20px; border-radius: 3px; overflow: hidden;">
                        <div style="background: linear-gradient(90deg, #4caf50 0%, #667eea 100%); height: 100%; width: <?php echo $item['percentage']; ?>%; transition: width 0.3s;">
                        </div>
                    </div>
                </td>
            </tr>
            <?php } ?>
        </table>

        <h2>✓ Kesimpulan</h2>
        <div style="background: #e3f2fd; padding: 15px; border-radius: 5px; border-left: 4px solid #2196f3;">
            <?php
            $status_items = array();
            
            if ($total_records > 0) {
                $status_items[] = "✓ Database memiliki " . $total_records . " records";
            } else {
                $status_items[] = "✗ Database kosong";
            }

            if ($unique_nik == $total_records) {
                $status_items[] = "✓ Tidak ada duplikat data";
            } else {
                $status_items[] = "✗ Ada " . ($total_records - $unique_nik) . " duplikat";
            }

            if ($incomplete == 0) {
                $status_items[] = "✓ Semua data lengkap (no NULL di field penting)";
            } else {
                $status_items[] = "⚠ Ada " . $incomplete . " data incomplete";
            }

            foreach ($status_items as $item) {
                echo "<p style='margin: 8px 0;'>$item</p>";
            }
            ?>
        </div>

        <div style="margin-top: 30px; text-align: center; opacity: 0.7;">
            <p>Last Check: <?php echo date('Y-m-d H:i:s'); ?></p>
            <p>
                <a href="javascript:location.reload();" style="padding: 8px 16px; background: #667eea; color: white; text-decoration: none; border-radius: 4px; cursor: pointer;">🔄 Refresh</a>
                <a href="index.html" style="padding: 8px 16px; background: #764ba2; color: white; text-decoration: none; border-radius: 4px; cursor: pointer; margin-left: 10px;">← Kembali</a>
            </p>
        </div>
    </div>

    <?php
    sqlsrv_close($conn);
    ?>
</body>
</html>
