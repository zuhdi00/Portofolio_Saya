<?php include 'dashboard_realisasi_data.php'; ?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Realisasi</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f5f5;
            padding: 20px;
        }

        .container {
            max-width: 1600px;
            margin: 0 auto;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .header {
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
            color: white;
            padding: 20px 30px;
            border-radius: 8px 8px 0 0;
        }

        .header h1 {
            font-size: 24px;
            margin-bottom: 15px;
        }

        .search-form {
            display: flex;
            gap: 15px;
            align-items: center;
            flex-wrap: wrap;
        }

        .form-group {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .form-group label {
            font-weight: 500;
            white-space: nowrap;
        }

        .form-group input {
            padding: 8px 12px;
            border: 1px solid rgba(255,255,255,0.3);
            border-radius: 4px;
            background: rgba(255,255,255,0.95);
            font-size: 14px;
            min-width: 200px;
        }

        .btn-search {
            padding: 8px 24px;
            background: #4CAF50;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-weight: 500;
            transition: background 0.3s;
        }

        .btn-search:hover {
            background: #45a049;
        }

        .content {
            padding: 30px;
        }

        .status-banner {
            padding: 15px 20px;
            border-radius: 6px;
            margin-bottom: 20px;
            font-size: 18px;
            font-weight: bold;
            text-align: center;
        }

        .status-banner.unfinished {
            background: #fff3cd;
            color: #856404;
            border: 2px solid #ffeaa7;
        }

        .status-banner.finished {
            background: #d4edda;
            color: #155724;
            border: 2px solid #c3e6cb;
        }

        .status-banner.not-found {
            background: #f8d7da;
            color: #721c24;
            border: 2px solid #f5c6cb;
        }

        .info-section {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 6px;
            margin-bottom: 20px;
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
            gap: 15px;
        }

        .info-item {
            display: flex;
            padding: 8px 0;
        }

        .info-label {
            font-weight: bold;
            min-width: 180px;
            color: #333;
        }

        .info-value {
            color: #555;
        }

        .dashboard-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(500px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }

        .card {
            background: white;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }

        .card-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 15px 20px;
            font-weight: bold;
            font-size: 16px;
        }

        .card-header.corrugating {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
        }

        .card-header.converting {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
        }

        .card-header.serah-terima {
            background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);
        }

        .card-header.pengiriman {
            background: linear-gradient(135deg, #fa709a 0%, #fee140 100%);
        }

        .card-header.transfer {
            background: linear-gradient(135deg, #30cfd0 0%, #330867 100%);
        }

        .card-body {
            padding: 15px;
        }

        .sub-section {
            margin-bottom: 20px;
        }

        .sub-section:last-child {
            margin-bottom: 0;
        }

        .sub-title {
            font-weight: bold;
            color: #333;
            margin-bottom: 10px;
            padding-bottom: 5px;
            border-bottom: 2px solid #e0e0e0;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 12px;
            margin-top: 10px;
        }

        table th {
            background: #f8f9fa;
            padding: 8px 6px;
            text-align: center;
            font-weight: 600;
            border: 1px solid #dee2e6;
            color: #495057;
            font-size: 11px;
        }

        table td {
            padding: 6px;
            border: 1px solid #dee2e6;
            text-align: center;
            font-size: 11px;
        }

        table tbody tr:hover {
            background: #f8f9fa;
        }

        .total-row {
            background: #e9ecef !important;
            font-weight: bold;
        }

        .no-data {
            color: #999;
            font-style: italic;
            text-align: center;
            padding: 20px;
        }

        .text-right {
            text-align: right !important;
        }

        .text-left {
            text-align: left !important;
        }

        @media (max-width: 768px) {
            .dashboard-grid {
                grid-template-columns: 1fr;
            }

            .info-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📊 Dashboard Realisasi Produksi</h1>
            <form method="get" class="search-form">
                <div class="form-group">
                    <label for="sc">Nomor SC/SLC:</label>
                    <input type="text" id="sc" name="sc" value="<?= htmlspecialchars($no_sc) ?>" placeholder="Contoh: SLC/2602/00100" required>
                </div>
                <button type="submit" class="btn-search">🔍 Cari Data</button>
            </form>
        </div>

        <div class="content">
            <?php if ($dataSC): ?>
                <!-- Status Banner -->
                <?php if (!empty($isFinished) && $isFinished): ?>
                    <div class="status-banner finished">
                        ✅ FINISHED
                    </div>
                <?php else: ?>
                    <div class="status-banner unfinished">
                        ⚠️ UNFINISHED
                    </div>
                <?php endif; ?>

                <!-- Info Section -->
                <div class="info-section">
                    <div class="info-grid">
                        <div>
                            <div class="info-item">
                                <span class="info-label">Nama Barang</span>
                                <span class="info-value">: <?= htmlspecialchars($dataSC['cNmBrg'] ?? $dataSC['cJenis'] ?? '-') ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Jml Order DT</span>
                                <span class="info-value">: <?= formatNumber($dataSC['nQty'] ?? 0) ?> <?php if(isset($dataSC['cSat'])): ?>(<?= htmlspecialchars($dataSC['cSat']) ?>)<?php endif; ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Warna</span>
                                <span class="info-value">: <?= htmlspecialchars($dataSC['cWarna'] ?? '-') ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Toleransi</span>
                                <span class="info-value">: <?= htmlspecialchars($dataSC['nToleransi'] ?? '-') ?>%</span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Flute</span>
                                <span class="info-value">: <?= htmlspecialchars($dataSC['cKodeLayer'] ?? '-') ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Join</span>
                                <span class="info-value">: <?= htmlspecialchars($dataSC['cSambungan'] ?? '-') ?></span>
                            </div>
                        </div>
                        <div>
                            <div class="info-item">
                                <span class="info-label">Customer</span>
                                <span class="info-value">: <?= htmlspecialchars($dataSC['cNama'] ?? '-') ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Jadwal Kirim Baru</span>
                                <span class="info-value">: <?= formatDate($dataSC['dTglKirim'] ?? null) ?> 
                                <?php if(isset($dataSC['dTglKirim2']) && $dataSC['dTglKirim2']): ?>
                                    / <?= formatDate($dataSC['dTglKirim2']) ?>
                                <?php endif; ?>
                                </span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Proses Mesin</span>
                                <span class="info-value">: <?= htmlspecialchars($dataSC['cJenis'] ?? '-') ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Jenis Order</span>
                                <span class="info-value">: <?= htmlspecialchars($dataSC['cJnsSc'] ?? '-') ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Berat/Box</span>
                                <span class="info-value">: <?= formatNumber($dataSC['nBrtBox'] ?? 0) ?> gr</span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Tipe</span>
                                <span class="info-value">: <?= htmlspecialchars($dataSC['cTipe'] ?? $dataSC['cKodeTipe'] ?? '-') ?></span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Dashboard Grid -->
                <div class="dashboard-grid">
                    <!-- Corrugating Card -->
                    <div class="card">
                        <div class="card-header corrugating">Corrugating</div>
                        <div class="card-body">
                            <div class="sub-section">
                                <div class="sub-title">Planning Corrugating</div>
                                <table>
                                    <thead>
                                        <tr>
                                            <th>Kode Corr</th>
                                            <th>No MC</th>
                                            <th>Tanggal</th>
                                            <th>Shift</th>
                                            <th>Flute</th>
                                            <th>Qty Order</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (count($dataCorrPlanning) > 0): ?>
                                            <?php foreach ($dataCorrPlanning as $row): ?>
                                                <tr>
                                                    <td><?= htmlspecialchars($row['cKodeCorr'] ?? '-') ?></td>
                                                    <td><?= htmlspecialchars($row['cNoMc'] ?? '-') ?></td>
                                                    <td><?= formatDate($row['dTanggal'] ?? null) ?></td>
                                                    <td><?= htmlspecialchars($row['cType'] ?? '-') ?></td>
                                                    <td><?= htmlspecialchars($row['cFlute'] ?? '-') ?></td>
                                                    <td class="text-right"><?= formatNumber($row['nQtyOrder'] ?? 0) ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                            <tr class="total-row">
                                                <td colspan="5">Total</td>
                                                <td class="text-right"><?= formatNumber($totalPlanCorr) ?></td>
                                            </tr>
                                        <?php else: ?>
                                            <tr><td colspan="7" class="no-data">No data found</td></tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>

                            <div class="sub-section">
                                <div class="sub-title">Hasil Corrugating</div>
                                <table>
                                    <thead>
                                        <tr>
                                            <th>Kode Corr</th>
                                            <th>No MC</th>
                                            <th>Tanggal</th>
                                            <th>Hasil</th>
                                            <th>Rusak</th>
                                            <th>Berat</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (count($dataCorrHasil) > 0): ?>
                                            <?php foreach ($dataCorrHasil as $row): ?>
                                                <tr>
                                                    <td><?= htmlspecialchars($row['cKodeCorr'] ?? '-') ?></td>
                                                    <td><?= htmlspecialchars($row['cNoMc'] ?? '-') ?></td>
                                                    <td><?= formatDate($row['dTanggal'] ?? null) ?></td>
                                                    <td class="text-right"><?= formatNumber($row['nHasil'] ?? 0) ?></td>
                                                    <td class="text-right"><?= formatNumber($row['nRusak'] ?? 0) ?></td>
                                                    <td class="text-right"><?= formatNumber($row['nBerat'] ?? 0) ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                            <tr class="total-row">
                                                <td colspan="3">Total</td>
                                                <td class="text-right"><?= formatNumber($totalHslCorr) ?></td>
                                                <td class="text-right"><?= formatNumber($totalRusakCorr) ?></td>
                                                <td class="text-right"><?= formatNumber($totalBeratCorr) ?></td>
                                            </tr>
                                        <?php else: ?>
                                            <tr><td colspan="6" class="no-data">No data found</td></tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- Converting Card -->
                    <div class="card">
                        <div class="card-header converting">Converting</div>
                        <div class="card-body">
                            <div class="sub-section">
                                <div class="sub-title">Plan Converting</div>
                                <table>
                                    <thead>
                                        <tr>
                                            <th>No MC</th>
                                            <th>Tanggal</th>
                                            <th>Item</th>
                                            <th>Qty</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (count($dataConvertingPlan) > 0): ?>
                                            <?php foreach ($dataConvertingPlan as $row): ?>
                                                <tr>
                                                    <td><?= htmlspecialchars($row['cNoMc'] ?? '-') ?></td>
                                                    <td><?= formatDate($row['dTgl'] ?? null) ?></td>
                                                    <td class="text-left"><?= htmlspecialchars($row['cnm_brg'] ?? '-') ?></td>
                                                    <td class="text-right"><?= formatNumber($row['nQtyStok'] ?? 0) ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                            <tr class="total-row">
                                                <td colspan="3">Total</td>
                                                <td class="text-right"><?= formatNumber($totalConvPlan) ?></td>
                                            </tr>
                                        <?php else: ?>
                                            <tr><td colspan="4" class="no-data">No data found</td></tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>

                            <div class="sub-section">
                                <div class="sub-title">Hasil Converting</div>
                                <table>
                                    <thead>
                                        <tr>
                                            <th>Mesin</th>
                                            <th>Tanggal</th>
                                            <th>Hasil</th>
                                            <th>Rusak</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (count($dataConvertingHasil) > 0): ?>
                                            <?php foreach ($dataConvertingHasil as $row): ?>
                                                <tr>
                                                    <td><?= htmlspecialchars($row['cNamaMsn'] ?? '-') ?></td>
                                                    <td><?= formatDate($row['dTanggal'] ?? null) ?></td>
                                                    <td class="text-right"><?= formatNumber($row['totalHasil'] ?? 0) ?></td>
                                                    <td class="text-right"><?= formatNumber($row['totalRusak'] ?? 0) ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                            <tr class="total-row">
                                                <td colspan="2">Total</td>
                                                <td class="text-right"><?= formatNumber($totalConvHasil) ?></td>
                                                <td class="text-right"><?= formatNumber($totalConvRusak) ?></td>
                                            </tr>
                                        <?php else: ?>
                                            <tr><td colspan="5" class="no-data">No data found</td></tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- Serah Terima Card -->
                    <div class="card">
                        <div class="card-header serah-terima">Serah Terima Barang Jadi</div>
                        <div class="card-body">
                            <table>
                                <thead>
                                    <tr>
                                        <th>No STB</th>
                                        <th>Tanggal</th>
                                        <th>Shift</th>
                                        <th>Qty Lmbr</th>
                                        <th>Rak</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (count($dataSerahTerima) > 0): ?>
                                        <?php foreach ($dataSerahTerima as $row): ?>
                                            <tr>
                                                <td><?= htmlspecialchars($row['cNoSTB'] ?? '-') ?></td>
                                                <td><?= formatDate($row['dTanggal'] ?? null) ?></td>
                                                <td><?= htmlspecialchars($row['cShift'] ?? '-') ?></td>
                                                <td class="text-right"><?= formatNumber($row['nQty'] ?? 0) ?></td>
                                                <td><?= htmlspecialchars($row['cRak'] ?? '-') ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                        <tr class="total-row">
                                            <td colspan="3">Total Qty</td>
                                            <td class="text-right"><?= formatNumber($totalSerahTerima) ?></td>
                                            <td></td>
                                        </tr>
                                    <?php else: ?>
                                        <tr><td colspan="5" class="no-data">No data found</td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Pengiriman Card -->
                    <div class="card">
                        <div class="card-header pengiriman">Pengiriman</div>
                        <div class="card-body">
                            <div class="sub-section">
                                <div class="sub-title">Pengiriman</div>
                                <table>
                                    <thead>
                                        <tr>
                                            <th>No SRJ</th>
                                            <th>Tanggal</th>
                                            <th>Tujuan</th>
                                            <th>No Pol</th>
                                            <th>Qty</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (count($dataPengiriman) > 0): ?>
                                            <?php foreach ($dataPengiriman as $row): ?>
                                                <tr>
                                                    <td><?= htmlspecialchars($row['cNoSRJ'] ?? '-') ?></td>
                                                    <td><?= formatDate($row['dTanggal'] ?? null) ?></td>
                                                    <td class="text-left"><?= htmlspecialchars($row['cTujuanKirim'] ?? '-') ?></td>
                                                    <td><?= htmlspecialchars($row['cNoPol'] ?? '-') ?></td>
                                                    <td class="text-right"><?= formatNumber($row['nQty'] ?? 0) ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                            <tr class="total-row">
                                                <td colspan="4">Total</td>
                                                <td class="text-right"><?= formatNumber($totalPengiriman) ?></td>
                                            </tr>
                                        <?php else: ?>
                                            <tr><td colspan="5" class="no-data">No data found</td></tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>

                            <div class="sub-section">
                                <div class="sub-title">Retur Pengiriman</div>
                                <table>
                                    <thead>
                                        <tr>
                                            <th>No Retur</th>
                                            <th>Tanggal</th>
                                            <th>Item</th>
                                            <th>Qty</th>
                                            <th>Ket</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (count($dataRetur) > 0): ?>
                                            <?php foreach ($dataRetur as $row): ?>
                                                <tr>
                                                    <td><?= htmlspecialchars($row['cNoRetur'] ?? '-') ?></td>
                                                    <td><?= formatDate($row['dTgl'] ?? null) ?></td>
                                                    <td class="text-left"><?= htmlspecialchars($row['cItem'] ?? '-') ?></td>
                                                    <td class="text-right"><?= formatNumber($row['nQty'] ?? 0) ?></td>
                                                    <td class="text-left"><?= htmlspecialchars($row['cKetRetur'] ?? '-') ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                            <tr class="total-row">
                                                <td colspan="3">Total</td>
                                                <td class="text-right"><?= formatNumber($totalRetur) ?></td>
                                                <td>-</td>
                                            </tr>
                                        <?php else: ?>
                                            <tr><td colspan="5" class="no-data">No data found</td></tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!--  Transfer Card 
                    <div class="card">
                        <div class="card-header transfer">Transfer</div>
                        <div class="card-body">
                            <div class="sub-section">
                                <div class="sub-title">Transfer To</div>
                                <div class="no-data">No data found</div>
                            </div>

                            <div class="sub-section">
                                <div class="sub-title">Transfer From</div>
                                <div class="no-data">No data found</div>
                            </div>

                            <div class="sub-section">
                                <div class="sub-title">Transfer WIP To</div>
                                <div class="no-data">No data found</div>
                            </div>

                            <div class="sub-section">
                                <div class="sub-title">Transfer WIP From</div>
                                <div class="no-data">No data found</div>
                            </div>
                        </div>
                    </div>
                </div>
               -->

            <?php elseif(!empty($no_sc_clean)): ?>
                <div class="status-banner not-found">
                    ❌ DATA TIDAK DITEMUKAN
                </div>
                <div class="info-section">
                    <p>Nomor SC/SLC "<strong><?= htmlspecialchars($no_sc) ?></strong>" tidak ditemukan di database.</p>
                    <p style="margin-top: 10px;">Silakan periksa kembali nomor yang Anda masukkan.</p>
                </div>
            <?php else: ?>
                <div class="status-banner" style="background: #e3f2fd; color: #0d47a1; border-color: #90caf9;">
                    ℹ️ SILAKAN MASUKKAN NOMOR SC/SLC
                </div>
                <div class="info-section">
                    <p>Masukkan Nomor SC/SLC pada form di atas untuk melihat dashboard realisasi produksi.</p>
                    <p style="margin-top: 10px;"><strong>Contoh format:</strong> SLC/2602/00100 atau SLC/2602/00151</p>
                </div>
            <?php endif; ?>

            <?php
            // Debugging: Log data Corrugating Planning yang diteruskan ke HTML
            error_log("Data Corrugating Planning yang diteruskan ke HTML: " . print_r($dataCorrPlanning, true));

            // Debugging: Log data Corrugating Hasil yang diteruskan ke HTML
            error_log("Data Corrugating Hasil yang diteruskan ke HTML: " . print_r($dataCorrHasil, true));
            ?>
        </div>
    </div>
</body>
</html>
