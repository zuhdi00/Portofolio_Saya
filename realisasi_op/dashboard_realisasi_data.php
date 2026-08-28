<?php
// === Configuration ===
$serverName = "spsdmz2";
$connectionOptions = array(
    "Database" => "dbSopanusa",
    "Uid" => "sa",
    "PWD" => "supracor",
    "LoginTimeout" => 15,
    "Encrypt" => false,
    "TrustServerCertificate" => true
);

// === Connect to Database ===
$conn = sqlsrv_connect($serverName, $connectionOptions);
if (!$conn) {
    $errors = sqlsrv_errors();
    die("Koneksi gagal: " . print_r($errors, true));
}

// Debugging: Log koneksi database
if (!$conn) {
    error_log("Koneksi database gagal: " . print_r(sqlsrv_errors(), true));
} else {
    error_log("Koneksi database berhasil.");
}

// === Get Parameters ===
$no_op = $_GET['op'] ?? '';
$no_op_clean = trim($no_op);

$tgl_awal = $_GET['tgl_awal'] ?? '';
$tgl_awal_clean = trim($tgl_awal);

$tgl_akhir = $_GET['tgl_akhir'] ?? '';
$tgl_akhir_clean = trim($tgl_akhir);

// default wildcard param (used by LIKE queries)
$param = '%' . $no_op_clean . '%';

// === Helper Functions ===
function queryOrDie($conn, $sql, $params, $label) {
    $stmt = sqlsrv_query($conn, $sql, $params);
    if ($stmt === false) {
        $errors = sqlsrv_errors();
        die("Query $label gagal: " . print_r($errors, true));
    }
    return $stmt;
}

function formatDate($date) {
    if (!$date) return '-';
    if (is_object($date)) {
        return $date->format('d/m/Y');
    }
    return date('d/m/Y', strtotime($date));
}

function formatDateTime($date) {
    if (!$date) return '-';
    if (is_object($date)) {
        return $date->format('d/m/Y H:i');
    }
    return date('d/m/Y H:i', strtotime($date));
}

function formatNumber($num) {
    return number_format($num, 0, ',', '.');
}

// === Initialize Data Arrays ===
$dataSC = null;
$dataOP = [];
$dataCorrPlanning = [];
$dataCorrHasil = [];
$dataConvertingPlan = [];
$dataConvertingHasil = [];
$dataSerahTerima = [];
$dataPengiriman = [];
$dataRetur = [];

// === Query Data if OP or Date Range Provided ===
if (!empty($no_op_clean) || !empty($tgl_awal_clean) || !empty($tgl_akhir_clean)) {
    // 2. Get OP Data (Filtered by dTglKirim2 for tbOP)
    $sqlOP = "SELECT * FROM tbOP WHERE 1=1";
    $paramsOP = [];
    if (!empty($no_op_clean)) {
        $sqlOP .= " AND cNoOp LIKE ?";
        $paramsOP[] = $no_op_clean . '%';
    }
    if (!empty($tgl_awal_clean)) {
        $sqlOP .= " AND dTglKirim2 >= ?";
        $paramsOP[] = $tgl_awal_clean . ' 00:00:00';
    }
    if (!empty($tgl_akhir_clean)) {
        $sqlOP .= " AND dTglKirim2 <= ?";
        $paramsOP[] = $tgl_akhir_clean . ' 23:59:59';
    }
    $sqlOP .= " ORDER BY cNoOp";

    $stmtOP = queryOrDie($conn, $sqlOP, $paramsOP, 'OP');
    while ($row = sqlsrv_fetch_array($stmtOP, SQLSRV_FETCH_ASSOC)) {
        $dataOP[] = $row;
    }
    sqlsrv_free_stmt($stmtOP);

    // 1. Get SC Data if we found any OP
    if (!empty($dataOP)) {
        $firstOp = $dataOP[0];
        $no_sc_clean = $firstOp['cNoSc'];
        
        $sqlSC = "SELECT * FROM tbSC WHERE cNoSc = ?";
        $stmtSC = queryOrDie($conn, $sqlSC, array($no_sc_clean), 'SC');
        $dataSC = sqlsrv_fetch_array($stmtSC, SQLSRV_FETCH_ASSOC);
        sqlsrv_free_stmt($stmtSC);

        if ($dataSC) {
            // Normalize common field names so the view can reliably read them
            $dataSC['cJenis'] = $dataSC['cJenis'] ?? $dataSC['cNmBrg'] ?? $dataSC['cnm_brg'] ?? $dataSC['cNama'] ?? $dataSC['nm_brg'] ?? null;
            $dataSC['cTipe'] = $dataSC['cTipe'] ?? $dataSC['cKodeTipe'] ?? null;
            $dataSC['cTipe'] = $firstOp['cTipe'] ?? $firstOp['cKodeTipe'] ?? $dataSC['cTipe'];
        } else {
            // Fallback if SC is not in tbSC but exists in tbOP
            $dataSC = [
                'cJenis' => $firstOp['cnm_brg'] ?? '-',
                'nQty' => $firstOp['nQty'] ?? 0,
                'cWarna' => $firstOp['cWarna'] ?? '-',
                'nToleransi' => '-',
                'cKodeLayer' => $firstOp['cLayer'] ?? '-',
                'cSambungan' => '-',
                'cNama' => $firstOp['cnm_c'] ?? '-',
                'dTglKirim' => $firstOp['dTglkirim2'] ?? null,
                'cProsesMesin' => $firstOp['cnm_brg'] ?? '-',
                'cJnsSc' => '-',
                'nBrtBox' => 0,
                'cTipe' => $firstOp['cTipe'] ?? $firstOp['cKodeTipe'] ?? '-'
            ];
        }
    }

    if (!empty($dataOP)) {
        // 3. CORRUGATING - Planning (tbCorr + tbCorrDtl)
        $baseCorrPlan = "SELECT c.cNoCorr, c.cKodeCorr, c.dTanggal, c.cKeterangan, c.nBerat, 
                        d.cNoOp, d.cPemesan, d.cType, d.cNoMc, d.nHasil, d.nRusak,
                        d.dStart, d.dFinish, d.cFlute, d.nQtyOrder
                        FROM tbCorr c
                        LEFT JOIN tbCorrDtl d ON c.cNoCorr = d.cNoCorr";

        // If we have OPs for this SC, filter by those OP numbers (more accurate).
        $opFilters = [];
        $opParams = [];
        foreach ($dataOP as $opRow) {
            $opFilters[] = 'd.cNoOp = ?';
            $opParams[] = $opRow['cNoOp'];
        }
        $whereClause = ' WHERE (' . implode(' OR ', $opFilters) . ')';
        $sqlCorrPlan = $baseCorrPlan . $whereClause . ' ORDER BY c.dTanggal, c.cNoCorr';
        $stmtCorrPlan = queryOrDie($conn, $sqlCorrPlan, $opParams, 'CorrPlan');

        while ($row = sqlsrv_fetch_array($stmtCorrPlan, SQLSRV_FETCH_ASSOC)) {
            $dataCorrPlanning[] = $row;
        }
        sqlsrv_free_stmt($stmtCorrPlan);

        // Debugging: Check if dataCorrPlanning is populated
        if (empty($dataCorrPlanning)) {
            error_log("Corrugating Planning data is empty for SC: $no_sc_clean");
        }

        // Debugging: Log parameter untuk Corrugating Planning
        error_log("Parameter untuk Corrugating Planning: " . $no_sc_clean);

        // Debugging: Log data Corrugating Planning
        if (empty($dataCorrPlanning)) {
            error_log("Corrugating Planning data is empty for SC: $no_sc_clean");
        } else {
            error_log("Corrugating Planning data: " . print_r($dataCorrPlanning, true));
        }

        // 4. CORRUGATING - Hasil (tbHslCorr + tbHslCorrDtl)
        $baseCorrHasil = "SELECT h.cNoCorr, h.cKodeCorr, h.dTanggal, h.cKeterangan, h.nBrgKg,
                         d.cNoOp, d.cPemesan, d.cType, d.cNoMc, d.nHasil, d.nRusak,
                         d.dStart, d.dFinish, d.cFlute, d.nBerat, d.nOut
                         FROM tbHslCorr h
                         LEFT JOIN tbHslCorrDtl d ON h.cNoCorr = d.cNoCorr";

        // Use the OP list when available to filter hasil
        $opFilters = [];
        $opParams = [];
        foreach ($dataOP as $opRow) {
            $opFilters[] = 'd.cNoOp = ?';
            $opParams[] = $opRow['cNoOp'];
        }
        $whereClause = ' WHERE (' . implode(' OR ', $opFilters) . ')';
        $sqlCorrHasil = $baseCorrHasil . $whereClause . ' ORDER BY h.dTanggal, h.cNoCorr';
        $stmtCorrHasil = queryOrDie($conn, $sqlCorrHasil, $opParams, 'CorrHasil');

        while ($row = sqlsrv_fetch_array($stmtCorrHasil, SQLSRV_FETCH_ASSOC)) {
            $nOut = isset($row['nOut']) ? (int)$row['nOut'] : 1;
            if ($nOut == 0) $nOut = 1; // Safeguard
            $row['nHasil'] = ($row['nHasil'] ?? 0) * $nOut;

            $ket = strtoupper($row['cKeterangan'] ?? '');
            $shift = '-';
            if (strpos($ket, 'SHIFT-III') !== false) {
                $shift = '3';
            } elseif (strpos($ket, 'SHIFT-II') !== false) {
                $shift = '2';
            } elseif (strpos($ket, 'SHIFT-I') !== false) {
                $shift = '1';
            }
            $row['cShift'] = $shift;

            $dataCorrHasil[] = $row;
        }
        sqlsrv_free_stmt($stmtCorrHasil);

        // (Previously built process labels from corrugating here) -- removed.

        // Debugging: Check if dataCorrHasil is populated
        if (empty($dataCorrHasil)) {
            error_log("Corrugating Hasil data is empty for SC: $no_sc_clean");
        }

        // Debugging: Log parameter untuk Corrugating Hasil
        error_log("Parameter untuk Corrugating Hasil: " . $param);

        // Debugging: Log data Corrugating Hasil
        if (empty($dataCorrHasil)) {
            error_log("Corrugating Hasil data is empty for SC: $no_sc_clean");
        } else {
            error_log("Corrugating Hasil data: " . print_r($dataCorrHasil, true));
        }

        // 5. CONVERTING - Plan (from tbOP: select all columns and use available fields)
        $dataConvertingPlan = $dataOP;

        // Debugging: Check if dataConvertingPlan is populated
        if (empty($dataConvertingPlan)) {
            error_log("Converting Planning (from tbOP) is empty.");
        }

        // 6. CONVERTING - Hasil (per OP/row detail, not aggregated)
        // Show each row from tbConvPlanDtl individually
        $baseConvHasil = "SELECT d.cNoOp, p.dTanggal, ISNULL(m.cNama, p.cKodeFlx) AS cNamaMsn,
                         ISNULL(d.nHasil,0) AS nHasil, ISNULL(d.nRusak,0) AS nRusak, p.cKeterangan
                         FROM tbConvPlan p
                         INNER JOIN tbConvPlanDtl d ON d.cNoConv = p.cNoConv
                         LEFT JOIN tbMesin m ON p.cKodeFlx = m.cKode";

        $opFilters = [];
        $opParams = [];
        foreach ($dataOP as $opRow) {
            $opFilters[] = 'd.cNoOp = ?';
            $opParams[] = $opRow['cNoOp'];
        }
        $whereClause = ' WHERE (' . implode(' OR ', $opFilters) . ')';
        $sqlConvHasil = $baseConvHasil . $whereClause . ' ORDER BY d.cNoOp, p.dTanggal';
        $stmtConvHasil = queryOrDie($conn, $sqlConvHasil, $opParams, 'ConvHasil');

        while ($row = sqlsrv_fetch_array($stmtConvHasil, SQLSRV_FETCH_ASSOC)) {
            $ket = strtoupper($row['cKeterangan'] ?? '');
            $shift = '-';
            if (strpos($ket, 'SHIFT-III') !== false) {
                $shift = '3';
            } elseif (strpos($ket, 'SHIFT-II') !== false) {
                $shift = '2';
            } elseif (strpos($ket, 'SHIFT-I') !== false) {
                $shift = '1';
            }
            $row['cShift'] = $shift;
            $dataConvertingHasil[] = $row;
        }
        sqlsrv_free_stmt($stmtConvHasil);

        // Collect corrugating codes (prefer cKodeCorr, fall back to cNoCorr)
        $corrCodes = [];
        foreach ($dataCorrPlanning as $r) {
            $code = trim($r['cKodeCorr'] ?? $r['cNoCorr'] ?? '');
            if ($code !== '' && !in_array($code, $corrCodes)) {
                $corrCodes[] = $code;
            }
        }
        foreach ($dataCorrHasil as $r) {
            $code = trim($r['cKodeCorr'] ?? $r['cNoCorr'] ?? '');
            if ($code !== '' && !in_array($code, $corrCodes)) {
                $corrCodes[] = $code;
            }
        }
        $corrLabel = !empty($corrCodes) ? implode(' | ', $corrCodes) : '';

        // Build machine names list from converting hasil machine names (`cNamaMsn`) only.
        $machineNames = [];
        foreach ($dataConvertingHasil as $r) {
            $name = trim($r['cNamaMsn'] ?? '');
            if ($name !== '' && !in_array($name, $machineNames)) {
                $machineNames[] = $name;
            }
        }
        $machineLabel = !empty($machineNames) ? implode(' -> ', $machineNames) : '';

        // Compose final Proses Mesin: include corr codes before machine names when available
        if ($corrLabel !== '' && $machineLabel !== '') {
            $dataSC['cProsesMesin'] = $corrLabel . ' -> ' . $machineLabel;
        } elseif ($corrLabel !== '') {
            $dataSC['cProsesMesin'] = $corrLabel;
        } elseif ($machineLabel !== '') {
            $dataSC['cProsesMesin'] = $machineLabel;
        }

        // Debugging: Check if dataConvertingHasil is populated
        if (empty($dataConvertingHasil)) {
            error_log("Converting Hasil (aggregated) is empty for SC: $no_sc_clean");
        } else {
            error_log("Converting Hasil (aggregated): " . print_r($dataConvertingHasil, true));
        }
                // 7. SERAH TERIMA (tbStbBJ) - per OP, ordered by NoOp and date
        $stbFilters = [];
        foreach ($dataOP as $opRow) {
            $stbFilters[] = 'cNoOp = ?';
        }
        $sqlSTB = "SELECT * FROM tbStbBJ WHERE (" . implode(' OR ', $stbFilters) . ") ORDER BY cNoOp, dTanggal";
        $stmtSTB = queryOrDie($conn, $sqlSTB, $opParams, 'STB');
        while ($row = sqlsrv_fetch_array($stmtSTB, SQLSRV_FETCH_ASSOC)) {
            $dataSerahTerima[] = $row;
        }
        sqlsrv_free_stmt($stmtSTB);

        // 8. PENGIRIMAN (tbSRJ + tbSRJDtl)
        $sqlPengiriman = "SELECT d.cNoSRJ, d.cNama, d.nQty, d.cNoOp, d.cNoScDtl,
                          s.dTanggal, s.cKeterangan, s.cNoPol, s.cTujuanKirim
                          FROM tbSRJDtl d
                          INNER JOIN tbSRJ s ON d.cNoSRJ = s.cNoSRJ
                          WHERE (";
        
        $pengirimanFilters = [];
        $pengirimanParams = [];
        foreach ($dataOP as $opRow) {
            $pengirimanFilters[] = 'd.cNoOp = ?';
            $pengirimanParams[] = $opRow['cNoOp'];
        }
        
        $sqlPengiriman .= implode(' OR ', $pengirimanFilters) . ") ";
        
        if (!empty($tgl_awal_clean)) {
            $sqlPengiriman .= " AND s.dTanggal >= ?";
            $pengirimanParams[] = $tgl_awal_clean . ' 00:00:00';
        }
        if (!empty($tgl_akhir_clean)) {
            $sqlPengiriman .= " AND s.dTanggal <= ?";
            $pengirimanParams[] = $tgl_akhir_clean . ' 23:59:59';
        }
        
        $sqlPengiriman .= " ORDER BY s.dTanggal";
        
        $stmtPengiriman = queryOrDie($conn, $sqlPengiriman, $pengirimanParams, 'Pengiriman');
        while ($row = sqlsrv_fetch_array($stmtPengiriman, SQLSRV_FETCH_ASSOC)) {
            $dataPengiriman[] = $row;
        }
        sqlsrv_free_stmt($stmtPengiriman);

        // 9. RETUR (tbRtSrj + tbRtSrjDtl)
        $sqlRetur = "SELECT d.cNomer as cNoRetur, d.cItem, d.nQty, d.cKeterangan as cKetRetur,
                     r.dTgl, r.cNoSc, r.cNoSrj, r.cNama
                     FROM tbRtSrjDtl d
                     INNER JOIN tbRtSrj r ON d.cNomer = r.cNomer
                     WHERE r.cNoSc = ? ";
                     
        $returParams = [$no_sc_clean];
        if (!empty($tgl_awal_clean)) {
            $sqlRetur .= " AND r.dTgl >= ?";
            $returParams[] = $tgl_awal_clean . ' 00:00:00';
        }
        if (!empty($tgl_akhir_clean)) {
            $sqlRetur .= " AND r.dTgl <= ?";
            $returParams[] = $tgl_akhir_clean . ' 23:59:59';
        }
        $sqlRetur .= " ORDER BY r.dTgl";
        
        $stmtRetur = queryOrDie($conn, $sqlRetur, $returParams, 'Retur');
        while ($row = sqlsrv_fetch_array($stmtRetur, SQLSRV_FETCH_ASSOC)) {
            $dataRetur[] = $row;
        }
        sqlsrv_free_stmt($stmtRetur);

        // Determine finished status: finished if there's at least one pengiriman (surat jalan)
        // and no retur; otherwise treat as unfinished.
        $isFinished = false;
        if (!empty($dataPengiriman) && empty($dataRetur)) {
            $isFinished = true;
        }
        // 10. QC LABEL (tbLabelQc)
        $qcStatus = '';
        $qcKeterangan = '';
        $qcTanggal = '';
        
        $opFiltersQC = [];
        $opParamsQC = [];
        foreach ($dataOP as $opRow) {
            $opFiltersQC[] = 'cNoOp = ?';
            $opParamsQC[] = $opRow['cNoOp'];
        }
        $whereClauseQC = ' WHERE (' . implode(' OR ', $opFiltersQC) . ')';
        $sqlQC = "SELECT TOP 1 cStatus, cKeterangan, dTgl, nAcc FROM tbLabelQc " . $whereClauseQC . " ORDER BY dTgl DESC, cNo DESC";
        $stmtQC = sqlsrv_query($conn, $sqlQC, $opParamsQC);
        
        if ($stmtQC !== false) {
            if ($row = sqlsrv_fetch_array($stmtQC, SQLSRV_FETCH_ASSOC)) {
                $nAcc = isset($row['nAcc']) ? (int)$row['nAcc'] : null;
                
                // Jika nAcc = 1 maka PENDING muncul. Jika 0, maka tidak muncul.
                if ($nAcc === 1) {
                    $qcStatus = 'PENDING';
                    $qcKeterangan = $row['cKeterangan'] ?? '';
                    if (!empty($row['dTgl'])) {
                        $qcTanggal = is_object($row['dTgl']) ? $row['dTgl']->format('d/m/Y') : $row['dTgl'];
                    }
                } else {
                    $qcStatus = '';
                    $qcKeterangan = '';
                    $qcTanggal = '';
                }
            }
            sqlsrv_free_stmt($stmtQC);
        }

    }
}

sqlsrv_close($conn);

// === Calculate Totals ===
$totalPlanCorr = 0;
foreach ($dataCorrPlanning as $row) {
    $totalPlanCorr += ($row['nQtyOrder'] ?? 0);
}

$totalHslCorr = 0;
foreach ($dataCorrHasil as $row) {
    $totalHslCorr += ($row['nHasil'] ?? 0);
}
$totalRusakCorr = 0;
foreach ($dataCorrHasil as $row) {
    $totalRusakCorr += ($row['nRusak'] ?? 0);
}
$totalBeratCorr = 0;
foreach ($dataCorrHasil as $row) {
    $totalBeratCorr += ($row['nBerat'] ?? 0);
}

$totalConvPlan = 0;
foreach ($dataConvertingPlan as $row) {
    $totalConvPlan += ($row['nQtyStok'] ?? 0);
}

$totalConvHasil = 0;
foreach ($dataConvertingHasil as $row) {
    $totalConvHasil += ($row['nHasil'] ?? 0);
}

$totalConvRusak = 0;
foreach ($dataConvertingHasil as $row) {
    $totalConvRusak += ($row['nRusak'] ?? 0);
}   

$totalSerahTerima = 0;
foreach ($dataSerahTerima as $row) {
    $totalSerahTerima += ($row['nQty'] ?? 0);
}

$totalPengiriman = 0;
foreach ($dataPengiriman as $row) {
    $totalPengiriman += ($row['nQty'] ?? 0);
}

$totalRetur = 0;
foreach ($dataRetur as $row) {
    $totalRetur += ($row['nQty'] ?? 0);
}

$sisaStok = max(0, $totalSerahTerima - $totalPengiriman);
?>
