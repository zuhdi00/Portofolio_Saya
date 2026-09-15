<?php
require __DIR__ . '/config/koneksi_sqlsrv.php';

$file = __DIR__ . '/database/DATA KARYAWAN (2).csv';

if (!file_exists($file)) {
    die("File CSV tidak ditemukan.");
}

$handle = fopen($file, "r");
if ($handle === FALSE) {
    die("Gagal membuka file CSV.");
}

// Fungsi bantu untuk format tanggal DD/MM/YYYY -> YYYY-MM-DD
function convertDate($dateStr) {
    if (empty(trim($dateStr))) return null;
    $parts = explode('/', trim($dateStr));
    if (count($parts) == 3) {
        return $parts[2] . '-' . $parts[1] . '-' . $parts[0];
    }
    return $dateStr;
}

$header_found = false;
$headers = [];
$updated = 0;
$inserted = 0;
$errors = 0;

$log = [];

while (($data = fgetcsv($handle, 4000, ",")) !== FALSE) {
    // Cari baris header
    if (!$header_found) {
        if (!empty($data) && trim($data[0]) === 'id_peg') {
            $header_found = true;
            foreach ($data as $k => $v) {
                $headers[trim($v)] = $k;
            }
        }
        continue;
    }

    if (empty(trim($data[0]))) {
        continue; // Lewati baris kosong
    }

    $id_peg = trim($data[$headers['id_peg']]);
    
    // Ambil field lain yang valid untuk diupdate
    $no_ktp = isset($headers['no_ktp']) ? trim($data[$headers['no_ktp']]) : null;
    $nama_peg = isset($headers['nama']) ? trim($data[$headers['nama']]) : null;
    $email_peg = isset($headers['email_peg']) ? trim($data[$headers['email_peg']]) : null;
    $no_hp_peg = isset($headers['no_hp_peg']) ? trim($data[$headers['no_hp_peg']]) : null;
    $tgl_lahir = isset($headers['tgl_lahir']) ? convertDate($data[$headers['tgl_lahir']]) : null;
    $tempat_lahir = isset($headers['tempat_lahir']) ? trim($data[$headers['tempat_lahir']]) : null;
    $gender = isset($headers['gender']) ? trim($data[$headers['gender']]) : null;
    $agama = isset($headers['agama']) ? trim($data[$headers['agama']]) : null;
    $status_kawin = isset($headers['status_kawin']) ? trim($data[$headers['status_kawin']]) : null;
    $alamat_ktp = isset($headers['alamat_ktp_peg']) ? trim($data[$headers['alamat_ktp_peg']]) : null;
    $rt = isset($headers['rt']) ? trim($data[$headers['rt']]) : null;
    $rw = isset($headers['rw']) ? trim($data[$headers['rw']]) : null;
    $kelurahan = isset($headers['kelurahan']) ? trim($data[$headers['kelurahan']]) : null;
    $kecamatan = isset($headers['kecamatan']) ? trim($data[$headers['kecamatan']]) : null;
    $kota = isset($headers['kota']) ? trim($data[$headers['kota']]) : null;
    $provinsi = isset($headers['provinsi']) ? trim($data[$headers['provinsi']]) : null;
    
    $tgl_masuk = isset($headers['tgl_masuk']) ? convertDate($data[$headers['tgl_masuk']]) : null;
    $status_karyawan = isset($headers['status_peg']) ? trim($data[$headers['status_peg']]) : null;
    
    $no_rekening = isset($headers['no_rekening']) ? trim($data[$headers['no_rekening']]) : null;
    $nama_bank = isset($headers['nama_bank']) ? trim($data[$headers['nama_bank']]) : null;

    // Cek apakah id_peg sudah ada
    $check_sql = "SELECT id_peg FROM pegawai WHERE id_peg = ?";
    $check_stmt = sqlsrv_query($conn, $check_sql, array($id_peg));
    $exists = sqlsrv_has_rows($check_stmt);
    sqlsrv_free_stmt($check_stmt);

    if ($exists) {
        // UPDATE (jangan update nik, id_peg, kolom-kolom absen/zkteco)
        $update_sql = "UPDATE pegawai SET 
            no_ktp = ?, nama_peg = ?, email_peg = ?, no_hp_peg = ?,
            tgl_lahir = ?, tempat_lahir = ?, gender = ?, agama = ?, status_kawin = ?,
            alamat_ktp_peg = ?, rt = ?, rw = ?, kelurahan = ?, kecamatan = ?,
            kota = ?, provinsi = ?, tgl_masuk = ?, status_karyawan = ?,
            no_rekening = ?, nama_bank = ?
            WHERE id_peg = ?";
        
        $params = [
            $no_ktp, $nama_peg, $email_peg, $no_hp_peg,
            $tgl_lahir, $tempat_lahir, $gender, $agama, $status_kawin,
            $alamat_ktp, $rt, $rw, $kelurahan, $kecamatan,
            $kota, $provinsi, $tgl_masuk, $status_karyawan,
            $no_rekening, $nama_bank,
            $id_peg
        ];
        
        $stmt = sqlsrv_query($conn, $update_sql, $params);
        if ($stmt === false) {
            $errors++;
            $log[] = "Gagal UPDATE id_peg: $id_peg | Error: " . print_r(sqlsrv_errors(), true);
        } else {
            $updated++;
        }
    } else {
        // INSERT (id_peg spesifik, nik kosong)
        // Set IDENTITY_INSERT ON tidak diperlukan karena id_peg di skema sepertinya bukan identity atau jika iya perlu di set
        // Kita coba insert normal dengan ID
        $insert_sql = "INSERT INTO pegawai (
            id_peg, no_ktp, nama_peg, email_peg, no_hp_peg,
            tgl_lahir, tempat_lahir, gender, agama, status_kawin,
            alamat_ktp_peg, rt, rw, kelurahan, kecamatan,
            kota, provinsi, tgl_masuk, status_karyawan,
            no_rekening, nama_bank, is_aktif
        ) VALUES (
            ?, ?, ?, ?, ?, 
            ?, ?, ?, ?, ?, 
            ?, ?, ?, ?, ?, 
            ?, ?, ?, ?, 
            ?, ?, 1
        )";
        $params = [
            $id_peg, $no_ktp, $nama_peg, $email_peg, $no_hp_peg,
            $tgl_lahir, $tempat_lahir, $gender, $agama, $status_kawin,
            $alamat_ktp, $rt, $rw, $kelurahan, $kecamatan,
            $kota, $provinsi, $tgl_masuk, $status_karyawan,
            $no_rekening, $nama_bank
        ];
        $stmt = sqlsrv_query($conn, $insert_sql, $params);
        if ($stmt === false) {
            $errors++;
            $log[] = "Gagal INSERT id_peg: $id_peg | Error: " . print_r(sqlsrv_errors(), true);
        } else {
            $inserted++;
        }
    }
}

fclose($handle);

echo "Proses Selesai!\n";
echo "Berhasil Update: $updated\n";
echo "Berhasil Insert: $inserted\n";
echo "Gagal: $errors\n\n";
if (!empty($log)) {
    echo "Log Error:\n";
    foreach ($log as $l) {
        echo $l . "\n";
    }
}
?>
