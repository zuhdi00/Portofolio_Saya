<?php include 'realisasi_data.php'; ?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Realisasi - <?= htmlspecialchars($no_sc) ?></title>
    <style>
        /* CSS Reset & Variables */
        :root {
            --primary: #0d47a1; /* Biru Tua */
            --accent: #1976d2;
            --bg: #f0f2f5;
            --card-bg: #ffffff;
            --text: #333;
            --border: #ddd;
        }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: var(--bg); color: var(--text); margin: 0; padding: 15px; font-size: 13px; }
        
        /* Search Bar */
        .search-box { background: var(--card-bg); padding: 15px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.05); margin-bottom: 20px; display: flex; gap: 10px; }
        .search-box input { flex: 1; padding: 10px; border: 1px solid var(--border); border-radius: 4px; max-width: 300px; font-weight: bold; }
        .search-box button { padding: 10px 20px; background: var(--primary); color: white; border: none; border-radius: 4px; cursor: pointer; font-weight: bold; }
        .search-box button:hover { background: var(--accent); }

        /* Header Info SC */
        .header-info { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 15px; background: #e3f2fd; padding: 15px; border-radius: 8px; border-left: 5px solid var(--primary); margin-bottom: 20px; }
        .info-card label { display: block; font-size: 11px; color: #546e7a; font-weight: bold; text-transform: uppercase; margin-bottom: 3px; }
        .info-card span { font-size: 14px; font-weight: 700; color: #102027; }

        /* Grid Layout */
        .dashboard { display: grid; grid-template-columns: repeat(2, 1fr); gap: 20px; }
        .full-width { grid-column: 1 / -1; }

        /* Card & Table */
        .card { background: var(--card-bg); border-radius: 6px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); overflow: hidden; border: 1px solid var(--border); display: flex; flex-direction: column; }
        .card-header { background: #f8f9fa; padding: 10px 15px; font-weight: bold; border-bottom: 1px solid var(--border); color: var(--primary); display: flex; justify-content: space-between; align-items: center; }
        .card-body { overflow-x: auto; padding: 0; }
        
        table { width: 100%; border-collapse: collapse; min-width: 400px; }
        th { background: #fafafa; padding: 10px; text-align: left; font-size: 11px; text-transform: uppercase; color: #666; border-bottom: 2px solid #eee; }
        td { padding: 8px 10px; border-bottom: 1px solid #eee; vertical-align: middle; }
        tr:last-child td { border-bottom: none; }
        tr:hover { background-color: #f5f5f5; }
        
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .total-row { background: #e8f5e9; font-weight: bold; color: #2e7d32; }
        .badge { padding: 3px 6px; border-radius: 4px; font-size: 10px; color: white; background: #78909c; }
        .badge-op { background: #ffa726; color: #fff; } /* Warna Orange untuk No OP */
        .badge-mesin { background: #00acc1; }

        @media (max-width: 992px) { .dashboard { grid-template-columns: 1fr; } }
    </style>
</head>
<body>

    <div class="search-box">
        <form method="GET" style="display:flex; width:100%; gap:10px;">
            <input type="text" name="sc" value="<?= htmlspecialchars($no_sc) ?>" placeholder="Masukkan Kode SC (Cth: SLC/2602/00151)" required autofocus>
            <button type="submit">CARI DATA</button>
        </form>
    </div>

    <?php if ($dataSC): ?>
        <div class="header-info">
            <div class="info-card"><label>No SC / SLC</label><span><?= $dataSC['cNoSC'] ?></span></div>
            <div class="info-card"><label>Customer</label><span><?= $dataSC['cNama'] ?></span></div>
            <div class="info-card"><label>Ukuran Box</label><span><?= floatval($dataSC['nPanjang']) ?> x <?= floatval($dataSC['nLebar']) ?> x <?= floatval($dataSC['nTinggi']) ?></span></div>
            <div class="info-card"><label>Kualitas</label><span><?= $dataSC['cWarna'] ?> / <?= $dataSC['cJnsGel'] ?></span></div>
            <div class="info-card"><label>Qty Order</label><span style="color:blue;"><?= number_format($dataSC['nQty']) ?> Pcs</span></div>
            <div class="info-card"><label>Tgl Kirim</label><span><?= $dataSC['dTglKirim'] ?></span></div>
        </div>

        <div class="dashboard">

            <div class="card">
                <div class="card-header">PLANNING CORRUGATING (tbCorr)</div>
                <div class="card-body">
                    <table>
                        <thead>
                            <tr>
                                <th>Tanggal</th>
                                <th>Kode</th>
                                <th>Ukuran Sheet (L x P)</th>
                                <th class="text-right">Qty Plan</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($dataCorr as $r): ?>
                            <tr>
                                <td><?= $r['dTanggal'] ?></td>
                                <td><?= $r['cKodeCorr'] ?></td>
                                <td><?= floatval($r['nL']) ?> x <?= floatval($r['nP']) ?></td>
                                <td class="text-right"><?= number_format($r['nQtyOrder']) ?></td>
                            </tr>
                            <?php endforeach; ?>
                            <tr class="total-row">
                                <td colspan="3">TOTAL PLAN</td>
                                <td class="text-right"><?= number_format($summary['plan']) ?></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="card">
                <div class="card-header">HASIL CORRUGATING (tbHslCorr)</div>
                <div class="card-body">
                    <table>
                        <thead>
                            <tr>
                                <th>Tanggal</th>
                                <th>Kode</th>
                                <th class="text-right">Meter Lari</th>
                                <th class="text-right">Berat (Kg)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($dataHslCorr as $r): ?>
                            <tr>
                                <td><?= $r['dTanggal'] ?></td>
                                <td><?= $r['cKodeCorr'] ?></td>
                                <td class="text-right"><?= number_format($r['nJmlMeter']) ?></td>
                                <td class="text-right"><?= number_format($r['nBrgKg']) ?></td>
                            </tr>
                            <?php endforeach; ?>
                            <tr class="total-row">
                                <td colspan="3">TOTAL HASIL (KG)</td>
                                <td class="text-right"><?= number_format($summary['hasil']) ?></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="card full-width">
                <div class="card-header">
                    <span>PROSES MESIN (Join: tbRealOP2 + cNoOp LIKE SC)</span>
                </div>
                <div class="card-body">
                    <table>
                        <thead>
                            <tr>
                                <th>Tgl Proses</th>
                                <th>Mesin</th>
                                <th>No OP (Acuan)</th>
                                <th>Jam Mulai</th>
                                <th>Jam Selesai</th>
                                <th class="text-right">Qty Hasil</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(empty($dataMesin)): ?>
                                <tr><td colspan="6" class="text-center">Belum ada data proses mesin / No OP tidak cocok</td></tr>
                            <?php else: ?>
                                <?php foreach($dataMesin as $r): ?>
                                <tr>
                                    <td><?= $r['dTanggal'] ?></td>
                                    <td><span class="badge badge-mesin"><?= $r['cMesin'] ?></span></td>
                                    <td><span class="badge badge-op"><?= $r['cNoOp'] ?></span></td>
                                    <td><?= $r['cJamMulai'] ?></td>
                                    <td><?= $r['cJamSelesai'] ?></td>
                                    <td class="text-right"><?= number_format($r['nQty']) ?></td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="card">
                <div class="card-header">SERAH TERIMA GUDANG (tbStbBJ)</div>
                <div class="card-body">
                    <table>
                        <thead>
                            <tr>
                                <th>Tanggal</th>
                                <th>No STB</th>
                                <th class="text-right">Qty Masuk</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($dataStbBJ as $r): ?>
                            <tr>
                                <td><?= $r['dTanggal'] ?></td>
                                <td><?= $r['cNoStb'] ?></td>
                                <td class="text-right"><?= number_format($r['nQty']) ?></td>
                            </tr>
                            <?php endforeach; ?>
                            <tr class="total-row">
                                <td colspan="2">TOTAL STB</td>
                                <td class="text-right"><?= number_format($summary['stb']) ?></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="card">
                <div class="card-header">PENGIRIMAN & RETUR (tbSRJ & tbRtSrj)</div>
                <div class="card-body">
                    <table>
                        <thead>
                            <tr>
                                <th>Tanggal</th>
                                <th>No Surat Jalan / Retur</th>
                                <th>Info Kendaraan / Ket</th>
                                <th class="text-right">Qty</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($dataDelivery as $r): ?>
                            <tr>
                                <td><?= $r['dTanggal'] ?></td>
                                <td><?= $r['cNoSrj'] ?></td>
                                <td><?= $r['cNoKend'] ?></td>
                                <td class="text-right"><?= number_format($r['nQty']) ?></td>
                            </tr>
                            <?php endforeach; ?>
                            
                            <?php foreach($dataRetur as $r): ?>
                            <tr style="color:red; background:#fff5f5;">
                                <td><?= $r['dTgl'] ?> (RETUR)</td>
                                <td><?= $r['cNoRetur'] ?></td>
                                <td><?= $r['cKeterangan'] ?></td>
                                <td class="text-right">-<?= number_format($r['nQty']) ?></td>
                            </tr>
                            <?php endforeach; ?>

                            <tr class="total-row">
                                <td colspan="3">NET TERKIRIM (Kirim - Retur)</td>
                                <td class="text-right"><?= number_format($summary['kirim'] - $summary['retur']) ?></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

        </div> <?php elseif($no_sc): ?>
        <div style="padding:20px; background:#fff3cd; color:#856404; border:1px solid #ffeeba; border-radius:5px; margin-top:20px;">
            Data SC <strong><?= htmlspecialchars($no_sc) ?></strong> tidak ditemukan. Pastikan format penulisan benar.
        </div>
    <?php else: ?>
        <div style="text-align:center; padding:50px; color:#999;">
            <h3>Silakan masukkan Nomor SC / SLC</h3>
        </div>
    <?php endif; ?>

</body>
</html>