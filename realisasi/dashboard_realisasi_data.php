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

$conn = sqlsrv_connect($serverName, $connectionOptions);
if (!$conn) {
    $errors = sqlsrv_errors();
    die("Koneksi gagal: " . print_r($errors, true));
}


if (!$conn) {
    error_log("Koneksi database gagal: " . print_r(sqlsrv_errors(), true));
} else {
    error_log("Koneksi database berhasil.");
}

// === Get Parameters ===
$no_sc = $_GET['sc'] ?? '';
$no_sc_clean = trim($no_sc);
// default wildcard param (used by LIKE queries)
$param = '%' . $no_sc_clean . '%';

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

// === Query Data if SC Provided ===
if (!empty($no_sc_clean)) {
    // 1. Get SC Data
    $sqlSC = "SELECT * FROM tbSC WHERE cNoSc = ?";
    $stmtSC = queryOrDie($conn, $sqlSC, array($no_sc_clean), 'SC');
    $dataSC = sqlsrv_fetch_array($stmtSC, SQLSRV_FETCH_ASSOC);
    sqlsrv_free_stmt($stmtSC);

    // Normalize common field names so the view can reliably read them

    if ($dataSC) {
        // 2. Get all OP related to this SC (cNoOP starts with cNoSC)
        $sqlOP = "SELECT * FROM tbOP WHERE cNoSc = ? ORDER BY cNoOp";
        $stmtOP = queryOrDie($conn, $sqlOP, array($no_sc_clean), 'OP');
        while ($row = sqlsrv_fetch_array($stmtOP, SQLSRV_FETCH_ASSOC)) {
            $dataOP[] = $row;
        }
        sqlsrv_free_stmt($stmtOP);

        // Normalize common field names so the view can reliably read them
        // Nama Barang: use `cJenis` from tbSC primarily; fall back to other common name columns
        $dataSC['cJenis'] = $dataSC['cJenis'] ?? $dataSC['cNmBrg'] ?? $dataSC['cnm_brg'] ?? $dataSC['cNama'] ?? $dataSC['nm_brg'] ?? null;

        // Tipe: prefer to take from first OP row (tbOP.cTipe / cKodeTipe) when available.
        // Keep any existing tbSC.cTipe if OP doesn't provide it.
        $dataSC['cTipe'] = $dataSC['cTipe'] ?? $dataSC['cKodeTipe'] ?? null;
        if (!empty($dataOP)) {
            $firstOp = $dataOP[0];
            $dataSC['cTipe'] = $firstOp['cTipe'] ?? $firstOp['cKodeTipe'] ?? $dataSC['cTipe'];
        }

        // Debug normalized fields
        error_log("dataSC normalized cNmBrg: " . ($dataSC['cNmBrg'] ?? '-'));
        error_log("dataSC normalized cTipe: " . ($dataSC['cTipe'] ?? '-'));

        // If we didn't find any OP rows with the first query, try a fallback using LIKE
        if (empty($dataOP)) {
            $sqlOP2 = "SELECT * FROM tbOP WHERE cNoOp LIKE ? ORDER BY cNoOp";
            $stmtOP2 = queryOrDie($conn, $sqlOP2, array($no_sc_clean.'%'), 'OP_fallback');
            while ($row = sqlsrv_fetch_array($stmtOP2, SQLSRV_FETCH_ASSOC)) {
                $dataOP[] = $row;
            }
            sqlsrv_free_stmt($stmtOP2);
            if (!empty($dataOP)) {
                error_log("Found OP via fallback LIKE for SC: $no_sc_clean");
            } else {
                error_log("No OP rows found for SC (both direct and fallback): $no_sc_clean");
            }
        }

        // 3. CORRUGATING - Planning (tbCorr + tbCorrDtl)
        $baseCorrPlan = "SELECT c.cNoCorr, c.cKodeCorr, c.dTanggal, c.cKeterangan, c.nBerat, 
                        d.cNoOp, d.cPemesan, d.cType, d.cNoMc, d.nHasil, d.nRusak,
                        d.dStart, d.dFinish, d.cFlute, d.nQtyOrder
                        FROM tbCorr c
                        LEFT JOIN tbCorrDtl d ON c.cNoCorr = d.cNoCorr";

        // If we have OPs for this SC, filter by those OP numbers (more accurate).
        if (!empty($dataOP)) {
            $opFilters = [];
            $opParams = [];
            foreach ($dataOP as $opRow) {
                // match exact cNoOp stored in tbOP
                $opFilters[] = 'd.cNoOp = ?';
                $opParams[] = $opRow['cNoOp'];
            }
            $whereClause = ' WHERE (' . implode(' OR ', $opFilters) . ')';
            $sqlCorrPlan = $baseCorrPlan . $whereClause . ' ORDER BY c.dTanggal, c.cNoCorr';
            $stmtCorrPlan = queryOrDie($conn, $sqlCorrPlan, $opParams, 'CorrPlan');
        } else {
            // fallback: search using SC pattern
            $sqlCorrPlan = $baseCorrPlan . ' WHERE d.cNoOp LIKE ? ORDER BY c.dTanggal, c.cNoCorr';
            $stmtCorrPlan = queryOrDie($conn, $sqlCorrPlan, array('%'.$no_sc_clean.'%'), 'CorrPlan');
        }

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
        if (!empty($dataOP)) {
            $opFilters = [];
            $opParams = [];
            foreach ($dataOP as $opRow) {
                $opFilters[] = 'd.cNoOp = ?';
                $opParams[] = $opRow['cNoOp'];
            }
            $whereClause = ' WHERE (' . implode(' OR ', $opFilters) . ')';
            $sqlCorrHasil = $baseCorrHasil . $whereClause . ' ORDER BY h.dTanggal, h.cNoCorr';
            $stmtCorrHasil = queryOrDie($conn, $sqlCorrHasil, $opParams, 'CorrHasil');
        } else {
            $sqlCorrHasil = $baseCorrHasil . ' WHERE d.cNoOp LIKE ? ORDER BY h.dTanggal, h.cNoCorr';
            $param = '%' . $no_sc_clean . '%';
            $stmtCorrHasil = queryOrDie($conn, $sqlCorrHasil, array($param), 'CorrHasil');
        }

        while ($row = sqlsrv_fetch_array($stmtCorrHasil, SQLSRV_FETCH_ASSOC)) {
            $nOut = isset($row['nOut']) ? (int)$row['nOut'] : 1;
            if ($nOut == 0) $nOut = 1; // Safeguard
            $row['nHasil'] = ($row['nHasil'] ?? 0) * $nOut;
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
        $sqlConvPlan = "SELECT * FROM tbOP
                WHERE cNoSc = ?
                ORDER BY cNoOp";
        $stmtConvPlan = queryOrDie($conn, $sqlConvPlan, array($no_sc_clean), 'ConvPlan');
        while ($row = sqlsrv_fetch_array($stmtConvPlan, SQLSRV_FETCH_ASSOC)) {
            $dataConvertingPlan[] = $row;
        }
        sqlsrv_free_stmt($stmtConvPlan);

        // Debugging: Check if dataConvertingPlan is populated
        if (empty($dataConvertingPlan)) {
            error_log("Converting Planning (from tbOP) is empty for SC: $no_sc_clean");
        } else {
            error_log("Converting Planning (from tbOP): " . print_r($dataConvertingPlan, true));
        }

        // 6. CONVERTING - Hasil (per OP/row detail, not aggregated)
        // Show each row from tbConvPlanDtl individually
        $baseConvHasil = "SELECT d.cNoOp, p.dTanggal, ISNULL(m.cNama, p.cKodeFlx) AS cNamaMsn,
                         ISNULL(d.nHasil,0) AS nHasil, ISNULL(d.nRusak,0) AS nRusak
                         FROM tbConvPlan p
                         INNER JOIN tbConvPlanDtl d ON d.cNoConv = p.cNoConv
                         LEFT JOIN tbMesin m ON p.cKodeFlx = m.cKode";

        if (!empty($dataOP)) {
            $opFilters = [];
            $opParams = [];
            foreach ($dataOP as $opRow) {
                $opFilters[] = 'd.cNoOp = ?';
                $opParams[] = $opRow['cNoOp'];
            }
            $whereClause = ' WHERE (' . implode(' OR ', $opFilters) . ')';
            $sqlConvHasil = $baseConvHasil . $whereClause . ' ORDER BY d.cNoOp, p.dTanggal';
            $stmtConvHasil = queryOrDie($conn, $sqlConvHasil, $opParams, 'ConvHasil');
        } else {
            // fallback: match by SC pattern in d.cNoOp
            $sqlConvHasil = $baseConvHasil . ' WHERE d.cNoOp LIKE ? ORDER BY d.cNoOp, p.dTanggal';
            $stmtConvHasil = queryOrDie($conn, $sqlConvHasil, array('%'.$no_sc_clean.'%'), 'ConvHasil');
        }

        while ($row = sqlsrv_fetch_array($stmtConvHasil, SQLSRV_FETCH_ASSOC)) {
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
        $sqlSTB = "SELECT * FROM tbStbBJ 
                   WHERE cNoSc = ? OR cNoOp LIKE ?
                   ORDER BY cNoOp, dTanggal";
        $stmtSTB = queryOrDie($conn, $sqlSTB, array($no_sc_clean, $no_sc_clean.'%'), 'STB');
        while ($row = sqlsrv_fetch_array($stmtSTB, SQLSRV_FETCH_ASSOC)) {
            $dataSerahTerima[] = $row;
        }
        sqlsrv_free_stmt($stmtSTB);

        // 8. PENGIRIMAN (tbSRJ + tbSRJDtl)
        $sqlPengiriman = "SELECT d.cNoSRJ, d.cNama, d.nQty, d.cNoOp, d.cNoScDtl,
                          s.dTanggal, s.cKeterangan, s.cNoPol, s.cTujuanKirim
                          FROM tbSRJDtl d
                          INNER JOIN tbSRJ s ON d.cNoSRJ = s.cNoSRJ
                          WHERE d.cNoScDtl = ? OR d.cNoOp LIKE ? OR s.cNoSC = ?
                          ORDER BY s.dTanggal";
        $stmtPengiriman = queryOrDie($conn, $sqlPengiriman, array($no_sc_clean, $no_sc_clean.'%', $no_sc_clean), 'Pengiriman');
        while ($row = sqlsrv_fetch_array($stmtPengiriman, SQLSRV_FETCH_ASSOC)) {
            $dataPengiriman[] = $row;
        }
        sqlsrv_free_stmt($stmtPengiriman);

        // 9. RETUR (tbRtSrj + tbRtSrjDtl)
        $sqlRetur = "SELECT d.cNomer as cNoRetur, d.cItem, d.nQty, d.cKeterangan as cKetRetur,
                     r.dTgl, r.cNoSc, r.cNoSrj, r.cNama
                     FROM tbRtSrjDtl d
                     INNER JOIN tbRtSrj r ON d.cNomer = r.cNomer
                     WHERE r.cNoSc = ?
                     ORDER BY r.dTgl";
        $stmtRetur = queryOrDie($conn, $sqlRetur, array($no_sc_clean), 'Retur');
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
        
        if (!empty($dataOP)) {
            $opFiltersQC = [];
            $opParamsQC = [];
            foreach ($dataOP as $opRow) {
                $opFiltersQC[] = 'cNoOp = ?';
                $opParamsQC[] = $opRow['cNoOp'];
            }
            $whereClauseQC = ' WHERE (' . implode(' OR ', $opFiltersQC) . ')';
            $sqlQC = "SELECT TOP 1 cStatus, cKeterangan, dTgl FROM tbLabelQc " . $whereClauseQC . " ORDER BY dTgl DESC, cNo DESC";
            $stmtQC = sqlsrv_query($conn, $sqlQC, $opParamsQC);
        } else {
            $sqlQC = "SELECT TOP 1 cStatus, cKeterangan, dTgl FROM tbLabelQc WHERE cNoOp LIKE ? ORDER BY dTgl DESC, cNo DESC";
            $stmtQC = sqlsrv_query($conn, $sqlQC, array($no_sc_clean.'%'));
        }
        
        if ($stmtQC !== false) {
            if ($row = sqlsrv_fetch_array($stmtQC, SQLSRV_FETCH_ASSOC)) {
                $qcStatus = $row['cStatus'] ?? '';
                $qcKeterangan = $row['cKeterangan'] ?? '';
                if (!empty($row['dTgl'])) {
                    $qcTanggal = is_object($row['dTgl']) ? $row['dTgl']->format('d/m/Y') : $row['dTgl'];
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
?>
