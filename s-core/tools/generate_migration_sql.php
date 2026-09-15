<?php
/**
 * generate_migration_sql.php
 * Generate SQL migration script dari CSV untuk dijalankan langsung di SQL Server
 * Output: SQL file yang bisa di-paste ke SSMS
 */

include '../config/koneksi.php';  // MySQL

header('Content-Type: text/plain; charset=utf-8');
header('Content-Disposition: attachment; filename="migration_pegawai_' . date('Y-m-d_His') . '.sql"');

// ============================================================
// 1. BACA CSV
// ============================================================
$csv_file = '../database/DATA KARYAWAN (2).csv';

if (!file_exists($csv_file)) {
    die("CSV file tidak ditemukan: $csv_file");
}

$csv_data = array();
$row = 0;

if (($handle = fopen($csv_file, 'r')) !== FALSE) {
    while (($data = fgetcsv($handle, 1000, ',')) !== FALSE) {
        $row++;
        if ($row <= 4) continue;  // Skip header
        if (empty($data[0])) continue;  // Skip kosong
        
        $csv_data[] = array(
            'id_peg' => trim($data[0]),
            'no_ktp' => trim($data[1]),
            'nama' => trim($data[3]),
            'email_peg' => trim($data[4]),
            'no_hp_peg' => trim($data[5]),
            'tgl_lahir' => trim($data[6]),
            'tempat_lahir' => trim($data[7]),
            'gender' => trim($data[8]),
            'agama' => trim($data[9]),
            'status_kawin' => trim($data[10]),
            'alamat_ktp_peg' => trim($data[11]),
            'rt' => trim($data[12]),
            'rw' => trim($data[13]),
            'kelurahan' => trim($data[14]),
            'kecamatan' => trim($data[15]),
            'kota' => trim($data[16]),
            'provinsi' => trim($data[17]),
            'kode_pos' => trim($data[18]),
        );
    }
    fclose($handle);
}

// ============================================================
// 2. GENERATE SQL
// ============================================================

echo "-- =====================================================================\n";
echo "-- MIGRATION SCRIPT: Data Pegawai (CSV → SQL Server dbHR)\n";
echo "-- Generated: " . date('Y-m-d H:i:s') . "\n";
echo "-- Total Records: " . count($csv_data) . "\n";
echo "-- =====================================================================\n";
echo "-- INSTRUCTIONS:\n";
echo "-- 1. Backup database dbHR sebelum menjalankan script ini\n";
echo "-- 2. Jalankan script ini di SSMS terhadap database: dbHR\n";
echo "-- 3. Script ini akan INSERT data baru dan UPDATE data yang sudah ada\n";
echo "-- 4. Gunakan CTRL+H untuk replace 'spsdmz2' dengan nama server Anda\n";
echo "-- =====================================================================\n\n";

echo "USE dbHR;\n";
echo "GO\n\n";

echo "-- Disable triggers sementara untuk performa lebih cepat\n";
echo "DISABLE TRIGGER ALL ON dbo.pegawai_lengkap;\n";
echo "GO\n\n";

echo "DECLARE @counter INT = 0;\n";
echo "DECLARE @total INT = " . count($csv_data) . ";\n\n";

foreach ($csv_data as $csv_row) {
    $id_peg = $csv_row['id_peg'];
    $no_ktp = isset($csv_row['no_ktp']) && $csv_row['no_ktp'] != '' ? "N'" . str_replace("'", "''", $csv_row['no_ktp']) . "'" : "NULL";
    $nama = "N'" . str_replace("'", "''", $csv_row['nama']) . "'";
    $email = isset($csv_row['email_peg']) && $csv_row['email_peg'] != '' ? "N'" . str_replace("'", "''", $csv_row['email_peg']) . "'" : "NULL";
    $no_hp = isset($csv_row['no_hp_peg']) && $csv_row['no_hp_peg'] != '' ? "N'" . str_replace("'", "''", $csv_row['no_hp_peg']) . "'" : "NULL";
    
    // Parse tanggal
    $tgl_lahir = "NULL";
    if (isset($csv_row['tgl_lahir']) && $csv_row['tgl_lahir'] != '') {
        $date = \DateTime::createFromFormat('d/m/Y', $csv_row['tgl_lahir']);
        if ($date) {
            $tgl_lahir = "'" . $date->format('Y-m-d') . "'";
        }
    }
    
    $tempat_lahir = isset($csv_row['tempat_lahir']) && $csv_row['tempat_lahir'] != '' ? "N'" . str_replace("'", "''", $csv_row['tempat_lahir']) . "'" : "NULL";
    $gender = isset($csv_row['gender']) && $csv_row['gender'] != '' ? "N'" . str_replace("'", "''", $csv_row['gender']) . "'" : "NULL";
    $agama = isset($csv_row['agama']) && $csv_row['agama'] != '' ? "N'" . str_replace("'", "''", $csv_row['agama']) . "'" : "NULL";
    $status_kawin = isset($csv_row['status_kawin']) && $csv_row['status_kawin'] != '' ? "N'" . str_replace("'", "''", $csv_row['status_kawin']) . "'" : "NULL";
    
    $alamat = isset($csv_row['alamat_ktp_peg']) && $csv_row['alamat_ktp_peg'] != '' ? "N'" . str_replace("'", "''", $csv_row['alamat_ktp_peg']) . "'" : "NULL";
    $rt = isset($csv_row['rt']) && $csv_row['rt'] != '' ? "N'" . str_replace("'", "''", $csv_row['rt']) . "'" : "NULL";
    $rw = isset($csv_row['rw']) && $csv_row['rw'] != '' ? "N'" . str_replace("'", "''", $csv_row['rw']) . "'" : "NULL";
    $kelurahan = isset($csv_row['kelurahan']) && $csv_row['kelurahan'] != '' ? "N'" . str_replace("'", "''", $csv_row['kelurahan']) . "'" : "NULL";
    $kecamatan = isset($csv_row['kecamatan']) && $csv_row['kecamatan'] != '' ? "N'" . str_replace("'", "''", $csv_row['kecamatan']) . "'" : "NULL";
    $kota = isset($csv_row['kota']) && $csv_row['kota'] != '' ? "N'" . str_replace("'", "''", $csv_row['kota']) . "'" : "NULL";
    $provinsi = isset($csv_row['provinsi']) && $csv_row['provinsi'] != '' ? "N'" . str_replace("'", "''", $csv_row['provinsi']) . "'" : "NULL";
    $kodepos = isset($csv_row['kode_pos']) && $csv_row['kode_pos'] != '' ? "N'" . str_replace("'", "''", $csv_row['kode_pos']) . "'" : "NULL";
    
    echo "-- Record " . htmlspecialchars($id_peg) . " - " . htmlspecialchars($csv_row['nama']) . "\n";
    echo "IF EXISTS (SELECT 1 FROM dbo.pegawai_lengkap WHERE nik = N'" . str_replace("'", "''", $id_peg) . "')\n";
    echo "BEGIN\n";
    echo "    UPDATE dbo.pegawai_lengkap SET\n";
    echo "        no_ktp = $no_ktp,\n";
    echo "        nama = $nama,\n";
    echo "        email = $email,\n";
    echo "        no_hp = $no_hp,\n";
    echo "        tanggal_lahir = $tgl_lahir,\n";
    echo "        tempat_lahir = $tempat_lahir,\n";
    echo "        gender = $gender,\n";
    echo "        agama = $agama,\n";
    echo "        status_kawin = $status_kawin,\n";
    echo "        almt_tetap = $alamat,\n";
    echo "        almt_tetap_rt = $rt,\n";
    echo "        almt_tetap_rw = $rw,\n";
    echo "        almt_tetap_desa = $kelurahan,\n";
    echo "        almt_tetap_kecamatan = $kecamatan,\n";
    echo "        almt_tetap_kota = $kota,\n";
    echo "        almt_tetap_provinsi = $provinsi,\n";
    echo "        almt_tetap_kodepos = $kodepos,\n";
    echo "        updated_at = GETDATE()\n";
    echo "    WHERE nik = N'" . str_replace("'", "''", $id_peg) . "';\n";
    echo "END\n";
    echo "ELSE\n";
    echo "BEGIN\n";
    echo "    INSERT INTO dbo.pegawai_lengkap (\n";
    echo "        nik, no_ktp, nama, email, no_hp, tanggal_lahir, tempat_lahir, gender,\n";
    echo "        agama, status_kawin, almt_tetap, almt_tetap_rt, almt_tetap_rw,\n";
    echo "        almt_tetap_desa, almt_tetap_kecamatan, almt_tetap_kota,\n";
    echo "        almt_tetap_provinsi, almt_tetap_kodepos, company_name, created_at, updated_at\n";
    echo "    ) VALUES (\n";
    echo "        N'" . str_replace("'", "''", $id_peg) . "',\n";
    echo "        $no_ktp, $nama, $email, $no_hp, $tgl_lahir, $tempat_lahir, $gender,\n";
    echo "        $agama, $status_kawin, $alamat, $rt, $rw,\n";
    echo "        $kelurahan, $kecamatan, $kota,\n";
    echo "        $provinsi, $kodepos, N'GRP1', GETDATE(), GETDATE()\n";
    echo "    );\n";
    echo "END\n";
    echo "GO\n\n";
}

echo "-- Re-enable triggers\n";
echo "ENABLE TRIGGER ALL ON dbo.pegawai_lengkap;\n";
echo "GO\n\n";

echo "-- Verify hasil migrasi\n";
echo "SELECT COUNT(*) as [Total Records], COUNT(DISTINCT nik) as [Unique NIK] FROM dbo.pegawai_lengkap;\n";
echo "GO\n\n";

echo "-- =====================================================================\n";
echo "-- MIGRATION SELESAI\n";
echo "-- =====================================================================\n";

?>
