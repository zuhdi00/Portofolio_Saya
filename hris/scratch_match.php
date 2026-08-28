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
$unmatched = [];

foreach ($new_records as $new) {
    $n_name = normalize_name($new['nama_peg']);
    $best_match = null;
    $highest_percent = 0;
    
    foreach ($old_records as $old) {
        $o_name = normalize_name($old['nama_peg']);
        
        // Exact match
        if ($n_name === $o_name) {
            $best_match = $old;
            $highest_percent = 100;
            break;
        }
        
        // Substring match
        if (strpos($n_name, $o_name) !== false || strpos($o_name, $n_name) !== false) {
            if (strlen($o_name) > 4 && strlen($n_name) > 4) {
                if ($highest_percent < 90) {
                    $best_match = $old;
                    $highest_percent = 90;
                }
            }
        }
        
        // Similarity
        similar_text($n_name, $o_name, $percent);
        if ($percent > $highest_percent && $percent > 75) {
            $highest_percent = $percent;
            $best_match = $old;
        }
    }
    
    if ($best_match && $highest_percent >= 80) {
        $matched[] = [
            'new' => $new,
            'old' => $best_match,
            'confidence' => $highest_percent
        ];
    } else {
        $unmatched[] = $new;
    }
}

echo "Total New Records: " . count($new_records) . "\n";
echo "Matched to Old Records: " . count($matched) . "\n";
echo "Unmatched (Genuinely new?): " . count($unmatched) . "\n\n";

echo "=== SAMPLE MATCHED ===\n";
for($i = 0; $i < min(20, count($matched)); $i++) {
    $m = $matched[$i];
    echo "NEW: {$m['new']['id_peg']} | {$m['new']['nik']} | {$m['new']['nama_peg']} \n";
    echo "OLD: {$m['old']['id_peg']} | {$m['old']['nik']} | {$m['old']['nama_peg']} \n";
    echo "Confidence: " . round($m['confidence'],2) . "%\n---\n";
}

echo "=== UNMATCHED ===\n";
for($i = 0; $i < min(10, count($unmatched)); $i++) {
    $u = $unmatched[$i];
    echo "NEW: {$u['nik']} - {$u['nama_peg']} \n";
}
