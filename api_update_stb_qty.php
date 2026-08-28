<?php
        header('Content-Type: application/json');
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: POST, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type');

        if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
            exit(0);
        }

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
            exit;
        }

        $cNoStb = $_POST['cNoStb'] ?? null;
        $nQty = $_POST['nQty'] ?? null;
        $nQtyKg = $_POST['nQtyKg'] ?? null;

        if (empty($cNoStb) || $nQty === null || $nQtyKg === null) { // nQty bisa 0, jadi cek null
            echo json_encode(['success' => false, 'message' => 'Missing required fields (cNoStb, nQty, nQtyKg).']);
            exit;
        }

        if (!is_numeric($nQty) || !is_numeric($nQtyKg)) {
            echo json_encode(['success' => false, 'message' => 'nQty and nQtyKg must be numeric.']);
            exit;
        }

        try {
            $serverName = "spsdmz2";
            $connectionOptions = array( /* ... */ );
            $conn = sqlsrv_connect($serverName, $connectionOptions);

            if (!$conn) {
                error_log("SQLSRV Connection Failed: " . print_r(sqlsrv_errors(), true));
                echo json_encode(['success' => false, 'message' => 'Database connection failed.']);
                exit;
            }

            $sql = "UPDATE tbStbBJ SET nQty = ?, nQtyKg = ? WHERE cNoSTB = ?";
            $params = array((float)$nQty, (float)$nQtyKg, $cNoStb);
            $stmt = sqlsrv_query($conn, $sql, $params);

            if ($stmt) {
                $rowsAffected = sqlsrv_rows_affected($stmt);
                if ($rowsAffected > 0) {
                    echo json_encode(['success' => true, 'message' => 'Qty STB berhasil diperbarui.']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Gagal memperbarui Qty STB atau data tidak berubah/tidak ditemukan.']);
                }
            } else {
                error_log("SQLSRV Query Failed (updateStbQty): " . print_r(sqlsrv_errors(), true));
                echo json_encode(['success' => false, 'message' => 'Gagal menjalankan query update Qty STB.']);
            }

            sqlsrv_free_stmt($stmt);
            sqlsrv_close($conn);

        } catch (Exception $e) {
            error_log("Exception (updateStbQty): " . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
        }
        ?>
        