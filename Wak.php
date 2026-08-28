<?php
// filepath: c:\xampp\htdocs\Wak.php
header('Content-Type: text/html; charset=utf-8');
header('Access-Control-Allow-Origin: *');

// --- SETUP KONEKSI SQLSERVER ---
$serverName = "spsdmz";
$connectionOptions = array(
    "Database" => "dbSopanusa",
    "Uid" => "sa",
    "PWD" => "supracor",
    "LoginTimeout" => 15,
    "Encrypt" => false,
    "TrustServerCertificate" => true
);
$conn = sqlsrv_connect($serverName, $connectionOptions);

if (!$conn) {
    die("<b>Connection failed.</b>");
}

// --- AMBIL PARAMETER TANGGAL ---
$tgl1 = isset($_GET['tgl1']) ? $_GET['tgl1'] : '2025-11-24';
$tgl2 = isset($_GET['tgl2']) ? $_GET['tgl2'] : '2025-12-09';

// --- QUERY ---
$sql = "
SELECT 
    A.cKodeCust,
    A.cNama AS NamaCustomer,
    SUM(A.nQty) AS Total_Qty_STB,
    SUM(A.nQtyKg) AS Total_QtyKg_STB,
    SUM(ISNULL(B.nQty, 0)) AS Total_Qty_TMP,
    SUM(ISNULL(B.nQtyKg, 0)) AS Total_QtyKg_TMP,
    SUM(A.nQty) - SUM(ISNULL(B.nQty, 0)) AS Selisih_Qty,
    SUM(A.nQtyKg) - SUM(ISNULL(B.nQtyKg, 0)) AS Selisih_QtyKg,
    CASE 
        WHEN SUM(A.nQty) = 0 THEN 0
        ELSE (SUM(ISNULL(B.nQty, 0)) * 100.0) / SUM(A.nQty)
    END AS Akurasi_Qty_Persen,
    CASE 
        WHEN SUM(A.nQtyKg) = 0 THEN 0
        ELSE (SUM(ISNULL(B.nQtyKg, 0)) * 100.0) / SUM(A.nQtyKg)
    END AS Akurasi_QtyKg_Persen
FROM tbStbBJ A
LEFT JOIN tbTmpStbBJ B 
       ON A.cNoOp = B.cNoOp
      AND A.cNoSc = B.cNoSc
WHERE 
    A.dTanggal BETWEEN ? AND ?
GROUP BY 
    A.cKodeCust,
    A.cNama
ORDER BY 
    A.cNama ASC
";
$params = array($tgl1, $tgl2);
$stmt = sqlsrv_query($conn, $sql, $params);

// --- Inisialisasi total ---
$total_qty_stb = 0;
$total_qtykg_stb = 0;
$total_qty_tmp = 0;
$total_qtykg_tmp = 0;
$total_selisih_qty = 0;
$total_selisih_qtykg = 0;
$total_akurasi_qty = 0;
$total_akurasi_qtykg = 0;
$total_rows = 0;
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Laporan Akurasi STB</title>
    <style>
        @page { size: A4; margin: 16mm 8mm 16mm 8mm; }
        body { font-family: 'Segoe UI', Arial, sans-serif; background: #f7f7f7; margin:0; }
        .container {
            width: 190mm; min-height: 265mm;
            margin: 0 auto; background: #fff;
            padding: 16mm 8mm; border-radius: 8px;
            box-shadow: 0 2px 12px #bbb;
        }
        h2 {
            text-align: center; color: #1976d2;
            margin-bottom: 14px; letter-spacing: 1px;
            font-size: 1.15rem; font-weight: 600;
        }
        .controls {
            margin-bottom: 10px; text-align: right;
        }
        .controls input {
            padding: 5px 8px; border-radius: 4px;
            border: 1px solid #bbb; font-size: 13px; min-width: 100px;
        }
        .controls button {
            padding: 5px 14px; border-radius: 4px;
            border: none; background: #1976d2; color: #fff;
            font-size: 13px; cursor: pointer; margin-left: 2px;
        }
        .controls button:hover { background: #145ea8; }
        table {
            width: 100%; border-collapse: collapse;
            margin-top: 6px; font-size: 0.92rem;
            table-layout: fixed;
        }
        th, td {
            border: 1px solid #bbb; padding: 4px 5px;
            text-align: right; word-break: break-word;
        }
        th {
            background: #1976d2; color: #fff;
            text-align: center; font-size: 0.95rem;
            font-weight: 600; letter-spacing: 0.5px;
        }
        td.nama { text-align: left; }
        tr:nth-child(even) { background: #f0f4f8; }
        tr.total-row {
            font-weight: bold; background: #e3f2fd;
            border-top: 2px solid #1976d2;
        }
        tr.total-row td { font-size: 1.01em; }
        @media print {
            .controls { display: none; }
            .container {
                box-shadow: none; width: auto; min-height: auto;
                padding: 0; border-radius: 0;
            }
            body { background: #fff; }
        }
    </style>
</head>
<body>
<div class="container">
    <h2>Laporan Akurasi STB per Customer</h2>
    <form class="controls" method="get">
        <label>Dari: <input type="date" name="tgl1" value="<?php echo htmlspecialchars($tgl1); ?>"></label>
        <label>Sampai: <input type="date" name="tgl2" value="<?php echo htmlspecialchars($tgl2); ?>"></label>
        <button type="submit">Tampilkan</button>
        <button type="button" onclick="window.print()">Print PDF</button>
    </form>
    <table>
        <thead>
            <tr>
                <th style="width: 10%;">Kode Customer</th>
                <th style="width: 18%;">Nama Customer</th>
                <th style="width: 10%;">Total Qty STB</th>
                <th style="width: 12%;">Total QtyKg STB</th>
                <th style="width: 10%;">Total Qty TMP</th>
                <th style="width: 12%;">Total QtyKg TMP</th>
                <th style="width: 10%;">Selisih Qty</th>
                <th style="width: 10%;">Selisih QtyKg</th>
                <th style="width: 8%;">% Akurasi Qty</th>
                <th style="width: 10%;">% Akurasi QtyKg</th>
            </tr>
        </thead>
        <tbody>
        <?php
        if ($stmt && sqlsrv_has_rows($stmt)) {
            while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
                $total_qty_stb += $row['Total_Qty_STB'];
                $total_qtykg_stb += $row['Total_QtyKg_STB'];
                $total_qty_tmp += $row['Total_Qty_TMP'];
                $total_qtykg_tmp += $row['Total_QtyKg_TMP'];
                $total_selisih_qty += $row['Selisih_Qty'];
                $total_selisih_qtykg += $row['Selisih_QtyKg'];
                $total_akurasi_qty += $row['Akurasi_Qty_Persen'];
                $total_akurasi_qtykg += $row['Akurasi_QtyKg_Persen'];
                $total_rows++;

                echo '<tr>';
                echo '<td>' . htmlspecialchars($row['cKodeCust']) . '</td>';
                echo '<td class="nama">' . htmlspecialchars($row['NamaCustomer']) . '</td>';
                echo '<td>' . number_format($row['Total_Qty_STB']) . '</td>';
                echo '<td>' . number_format($row['Total_QtyKg_STB'], 2) . '</td>';
                echo '<td>' . number_format($row['Total_Qty_TMP']) . '</td>';
                echo '<td>' . number_format($row['Total_QtyKg_TMP'], 2) . '</td>';
                echo '<td>' . number_format($row['Selisih_Qty']) . '</td>';
                echo '<td>' . number_format($row['Selisih_QtyKg'], 2) . '</td>';
                echo '<td>' . number_format($row['Akurasi_Qty_Persen'], 2) . '%</td>';
                echo '<td>' . number_format($row['Akurasi_QtyKg_Persen'], 2) . '%</td>';
                echo '</tr>';
            }
            // Baris total
            echo '<tr class="total-row">';
            echo '<td colspan="2" style="text-align:center;">TOTAL</td>';
            echo '<td>' . number_format($total_qty_stb) . '</td>';
            echo '<td>' . number_format($total_qtykg_stb, 2) . '</td>';
            echo '<td>' . number_format($total_qty_tmp) . '</td>';
            echo '<td>' . number_format($total_qtykg_tmp, 2) . '</td>';
            echo '<td>' . number_format($total_selisih_qty) . '</td>';
            echo '<td>' . number_format($total_selisih_qtykg, 2) . '</td>';
            // Rata-rata akurasi
            $avg_akurasi_qty = $total_rows ? $total_akurasi_qty / $total_rows : 0;
            $avg_akurasi_qtykg = $total_rows ? $total_akurasi_qtykg / $total_rows : 0;
            echo '<td>' . number_format($avg_akurasi_qty, 2) . '%</td>';
            echo '<td>' . number_format($avg_akurasi_qtykg, 2) . '%</td>';
            echo '</tr>';
        } else {
            echo '<tr><td colspan="10" style="text-align:center;color:#e74c3c;">Data tidak ditemukan</td></tr>';
        }
        ?>
        </tbody>
    </table>
</div>
</body>
</html>