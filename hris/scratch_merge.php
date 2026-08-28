<?php
require 'c:/xampp/htdocs/hris/config/koneksi_sqlsrv.php';

// Ambil semua data lama
$sql_old = "SELECT id_peg, nik, nama_peg, zkteco_userid FROM pegawai WHERE id_peg < 3000";
$stmt_old = sqlsrv_query($conn, $sql_old);
$old_records = [];
while ($r = sqlsrv_fetch_array($stmt_old, SQLSRV_FETCH_ASSOC)) {
    $old_records[] = $r;
}

// Ambil semua data baru
$sql_new = "SELECT * FROM pegawai WHERE id_peg >= 3000";
$stmt_new = sqlsrv_query($conn, $sql_new);
$new_records = [];
while ($r = sqlsrv_fetch_array($stmt_new, SQLSRV_FETCH_ASSOC)) {
    $new_records[] = $r;
}

function normalize_name($name) {
    $name = strtoupper(trim($name));
    $name = str_replace(['.', ',', ' ACH ', ' MOCH ', ' MUH ', ' ACH.', ' MOCH.', ' MUH.'], [' ', ' ', ' ', ' ', ' ', ' ', ' ', ' '], $name);
    $name = preg_replace('/^(ACH|MOCH|MUHAMMAD|M)\s+/', '', $name);
    return trim(preg_replace('/\s+/', ' ', $name));
}

$matched = [];
foreach ($new_records as $new) {
    $n_name = normalize_name($new['nama_peg']);
    $best_match = null;
    $highest_percent = 0;
    
    foreach ($old_records as $old) {
        $o_name = normalize_name($old['nama_peg']);
        if ($n_name === $o_name) {
            $best_match = $old;
            $highest_percent = 100;
            break;
        }
        if (strpos($n_name, $o_name) !== false || strpos($o_name, $n_name) !== false) {
            if (strlen($o_name) > 4 && strlen($n_name) > 4) {
                if ($highest_percent < 90) {
                    $best_match = $old;
                    $highest_percent = 90;
                }
            }
        }
        similar_text($n_name, $o_name, $percent);
        if ($percent > $highest_percent && $percent > 75) {
            $highest_percent = $percent;
            $best_match = $old;
        }
    }
    
    if ($best_match && $highest_percent >= 80) {
        $matched[] = [
            'new' => $new,
            'old' => $best_match
        ];
    }
}

echo "Memulai proses MERGE untuk " . count($matched) . " data duplikat...\n\n";

sqlsrv_begin_transaction($conn);
$success = 0;
$errors = 0;

$cols_to_update = [
    'nama_peg', 'email_peg', 'no_hp_peg', 'tgl_lahir', 'tempat_lahir', 'gender', 'agama',
    'status_nikah', 'alamat_ktp_peg', 'tgl_masuk', 'status_karyawan', 'no_rekening', 'nama_bank', 'is_aktif', 'no_ktp'
];

foreach ($matched as $m) {
    $old_id = $m['old']['id_peg'];
    $new_id = $m['new']['id_peg'];
    
    // DELETE new record FIRST to avoid UNIQUE constraints (like no_ktp) clashing with itself
    $sql_del = "DELETE FROM pegawai WHERE id_peg = ?";
    $stmt_del = sqlsrv_query($conn, $sql_del, [$new_id]);
    
    if ($stmt_del === false) {
        $errors++;
        echo "Gagal DELETE id_peg={$new_id}: " . print_r(sqlsrv_errors(), true) . "\n";
        continue; // skip updating old if delete fails
    }

    $set_clauses = [];
    $params = [];
    foreach ($cols_to_update as $col) {
        $set_clauses[] = "$col = ?";
        $val = $m['new'][$col];
        if ($val instanceof DateTime) {
            $val = $val->format('Y-m-d');
        }
        $params[] = $val;
    }
    
    // Add id_peg for WHERE
    $params[] = $old_id;
    
    // UPDATE old record
    $sql_upd = "UPDATE pegawai SET " . implode(', ', $set_clauses) . " WHERE id_peg = ?";
    $stmt_upd = sqlsrv_query($conn, $sql_upd, $params);
    
    if ($stmt_upd === false) {
        $errors++;
        echo "Gagal UPDATE id_peg={$old_id}: " . print_r(sqlsrv_errors(), true) . "\n";
        // If it fails here, the transaction will roll back everything eventually.
    } else {
        $success++;
    }
}

if ($errors === 0) {
    sqlsrv_commit($conn);
    echo "MERGE SELESAI. $success data berhasil digabungkan dan duplikat dihapus.\n";
} else {
    sqlsrv_rollback($conn);
    echo "TERJADI ERROR ($errors). Rollback dilakukan.\n";
}
