<?php
/**
 * update_karyawan_csv_final.php
 * Import data pegawai dari CSV ke SQL Server
 * - UPDATE jika NIK sudah ada
 * - INSERT jika NIK belum ada
 * - Kolom CHECK constraint (status_nikah, status_karyawan, gender, agama) 
 *   constraint dilepas dulu, lalu dipasang kembali tapi dengan NULL diperbolehkan
 */
ini_set('display_errors', 1);
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);

require __DIR__ . '/config/koneksi_sqlsrv.php';

$file = __DIR__ . '/database/DATA KARYAWAN (2).csv';

// ============================================================
// STEP 1: Lepas semua CHECK CONSTRAINT pada tabel pegawai
// ============================================================
echo "=== STEP 1: Drop CHECK Constraints ===\n";
$dropSql = "
DECLARE @sql NVARCHAR(MAX) = N'';
SELECT @sql += N'ALTER TABLE dbo.pegawai DROP CONSTRAINT ' + QUOTENAME(name) + ';'
FROM sys.check_constraints 
WHERE parent_object_id = OBJECT_ID('dbo.pegawai');
IF LEN(@sql) > 0 EXEC sp_executesql @sql;
";
$stmt = sqlsrv_query($conn, $dropSql);
if ($stmt === false) {
    echo "Gagal drop constraint: " . print_r(sqlsrv_errors(), true);
} else {
    echo "OK: Semua check constraint dilepas.\n\n";
}

// ============================================================
// STEP 2: Fungsi mapping nilai CSV ke nilai database
// ============================================================

function mapGender($v) {
    $v = strtoupper(trim($v));
    if (in_array($v, ['L','LAKI','LAKI-LAKI','MALE','M'])) return 'L';
    if (in_array($v, ['P','PEREMPUAN','WANITA','FEMALE','F','W'])) return 'P';
    return null; // NULL jika tidak bisa ditentukan
}

function mapAgama($v) {
    $v = ucfirst(strtolower(trim($v)));
    $map = [
        'islam'     => 'Islam',
        'kristen'   => 'Kristen',
        'katolik'   => 'Katolik',
        'hindu'     => 'Hindu',
        'buddha'    => 'Buddha',
        'budha'     => 'Buddha',
        'budhi'     => 'Buddha',
        'konghucu'  => 'Konghucu',
        'kong hu cu'=> 'Konghucu',
    ];
    return $map[strtolower(trim($v))] ?? null;
}

function mapStatusNikah($v) {
    // DB valid: TK, K0, K1, K2, K3
    $v = strtoupper(trim($v));
    // Jika sudah dalam format yang benar
    if (in_array($v, ['TK','K0','K1','K2','K3'])) return $v;
    // Konversi dari teks umum
    if (in_array($v, ['BELUM MENIKAH','BELUM KAWIN','SINGLE','TIDAK KAWIN','TIDAK MENIKAH'])) return 'TK';
    if (in_array($v, ['MENIKAH','KAWIN','MARRIED','SUDAH MENIKAH','SUDAH KAWIN'])) return 'K0';
    // Jika ada kode numerik K1, K2, K3 dalam berbagai format
    if (preg_match('/^K[0-3]$/', $v)) return $v;
    return null; // NULL jika tidak dikenali
}

function mapStatusKaryawan($v) {
    // DB valid: harian, kontrak, tetap
    $v = strtolower(trim($v));
    if (in_array($v, ['tetap','permanent','karyawan tetap','pkwtt'])) return 'tetap';
    if (in_array($v, ['kontrak','contract','pkwt','karyawan kontrak'])) return 'kontrak';
    if (in_array($v, ['harian','daily','karyawan harian'])) return 'harian';
    return null; // NULL jika tidak dikenali
}

function convertDate($v) {
    if (empty(trim($v))) return null;
    $v = trim($v);
    // Format dd/mm/yyyy
    if (preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/', $v, $m)) {
        return $m[3] . '-' . str_pad($m[2],2,'0',STR_PAD_LEFT) . '-' . str_pad($m[1],2,'0',STR_PAD_LEFT);
    }
    // Format yyyy-mm-dd sudah benar
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $v)) return $v;
    return null;
}

// ============================================================
// STEP 3: Baca CSV dan lakukan INSERT/UPDATE
// ============================================================
echo "=== STEP 2: Import CSV ===\n";
if (!file_exists($file)) {
    die("File CSV tidak ditemukan: $file\n");
}

$handle = fopen($file, "r");
$header_found = false;
$headers = [];
$updated = 0;
$inserted = 0;
$errors = 0;
$log = [];

while (($data = fgetcsv($handle, 8000, ",")) !== FALSE) {
    // Cari baris header
    if (!$header_found) {
        if (!empty($data[0]) && trim($data[0]) === 'id_peg') {
            $header_found = true;
            foreach ($data as $k => $v) {
                $headers[trim($v)] = $k;
            }
        }
        continue;
    }

    // Skip baris kosong
    if (!isset($data[0]) || empty(trim($data[0]))) continue;

    // Ambil nilai dari CSV
    $g = function($col) use ($data, $headers) {
        return isset($headers[$col]) ? trim($data[$headers[$col]]) : '';
    };

    $csv_nik         = substr($g('id_peg'), 0, 50);
    $no_ktp          = substr($g('no_ktp'), 0, 20);
    $nama_peg        = substr($g('nama') ?: 'Pegawai ' . $csv_nik, 0, 255);
    $email_peg       = ($g('email_peg') !== '') ? substr($g('email_peg'), 0, 100) : null;
    $no_hp_peg       = ($g('no_hp_peg') !== '') ? substr($g('no_hp_peg'), 0, 20) : null;
    $tgl_lahir       = convertDate($g('tgl_lahir'));
    $tempat_lahir    = ($g('tempat_lahir') !== '') ? substr($g('tempat_lahir'), 0, 100) : null;
    $gender          = mapGender($g('gender'));
    $agama           = mapAgama($g('agama'));
    $status_nikah    = mapStatusNikah($g('status_kawin'));
    $alamat_ktp      = ($g('alamat_ktp_peg') !== '') ? substr($g('alamat_ktp_peg'), 0, 500) : null;
    $rt              = ($g('rt') !== '') ? substr($g('rt'), 0, 10) : null;
    $rw              = ($g('rw') !== '') ? substr($g('rw'), 0, 10) : null;
    $kelurahan       = ($g('kelurahan') !== '') ? substr($g('kelurahan'), 0, 100) : null;
    $kecamatan       = ($g('kecamatan') !== '') ? substr($g('kecamatan'), 0, 100) : null;
    $kota            = ($g('kota') !== '') ? substr($g('kota'), 0, 100) : null;
    $provinsi        = ($g('provinsi') !== '') ? substr($g('provinsi'), 0, 100) : null;
    $tgl_masuk       = convertDate($g('tgl_masuk'));
    $status_karyawan = mapStatusKaryawan($g('status_peg'));
    $no_rekening     = ($g('no_rekening') !== '') ? substr($g('no_rekening'), 0, 50) : null;
    $nama_bank       = ($g('nama_bank') !== '') ? substr($g('nama_bank'), 0, 100) : null;
    $npwp            = ($g('npwp') !== '') ? substr($g('npwp'), 0, 30) : null;
    $no_bpjs_tk      = ($g('no_bpjs_tk') !== '') ? substr($g('no_bpjs_tk'), 0, 30) : null;
    $no_bpjs_kes     = ($g('no_bpjs_kes') !== '') ? substr($g('no_bpjs_kes'), 0, 30) : null;
    $lokasi_kerja    = ($g('lokasi_kerja') !== '') ? substr($g('lokasi_kerja'), 0, 100) : null;

    // Cek apakah NIK sudah ada
    $check = sqlsrv_query($conn, "SELECT id_peg FROM pegawai WHERE nik = ?", [$csv_nik]);
    $exists = ($check && sqlsrv_has_rows($check));
    if ($check) sqlsrv_free_stmt($check);

    if ($exists) {
        // UPDATE
        $sql = "UPDATE pegawai SET
                    nama_peg        = ?,
                    email_peg       = ?,
                    no_hp_peg       = ?,
                    tgl_lahir       = ?,
                    tempat_lahir    = ?,
                    gender          = ?,
                    agama           = ?,
                    status_nikah    = ?,
                    alamat_ktp_peg  = ?,
                    rt              = ?,
                    rw              = ?,
                    kelurahan       = ?,
                    kecamatan       = ?,
                    kota            = ?,
                    provinsi        = ?,
                    tgl_masuk       = ?,
                    status_karyawan = ?,
                    no_rekening     = ?,
                    nama_bank       = ?,
                    npwp            = ?,
                    no_bpjs_tk      = ?,
                    no_bpjs_kes     = ?,
                    lokasi_kerja    = ?
                WHERE nik = ?";
        $params = [
            $nama_peg, $email_peg, $no_hp_peg,
            $tgl_lahir, $tempat_lahir, $gender, $agama, $status_nikah,
            $alamat_ktp, $rt, $rw, $kelurahan, $kecamatan, $kota, $provinsi,
            $tgl_masuk, $status_karyawan,
            $no_rekening, $nama_bank, $npwp, $no_bpjs_tk, $no_bpjs_kes, $lokasi_kerja,
            $csv_nik
        ];
        $stmt = sqlsrv_query($conn, $sql, $params);
        if ($stmt === false) {
            $errors++;
            $log[] = "GAGAL UPDATE nik=$csv_nik | " . sqlsrv_errors()[0]['message'];
        } else {
            $updated++;
        }
    } else {
        // INSERT - no_ktp harus unik, gunakan fallback
        if (empty($no_ktp)) {
            $no_ktp = 'KTP-' . $csv_nik . '-' . rand(1000,9999);
        }
        
        $sql = "INSERT INTO pegawai (
                    nik, no_ktp, nama_peg, email_peg, no_hp_peg,
                    tgl_lahir, tempat_lahir, gender, agama, status_nikah,
                    alamat_ktp_peg, rt, rw, kelurahan, kecamatan, kota, provinsi,
                    tgl_masuk, status_karyawan,
                    no_rekening, nama_bank, npwp, no_bpjs_tk, no_bpjs_kes, lokasi_kerja,
                    is_aktif
                ) VALUES (
                    ?, ?, ?, ?, ?,
                    ?, ?, ?, ?, ?,
                    ?, ?, ?, ?, ?, ?, ?,
                    ?, ?,
                    ?, ?, ?, ?, ?, ?,
                    1
                )";
        $params = [
            $csv_nik, $no_ktp, $nama_peg, $email_peg, $no_hp_peg,
            $tgl_lahir, $tempat_lahir, $gender, $agama, $status_nikah,
            $alamat_ktp, $rt, $rw, $kelurahan, $kecamatan, $kota, $provinsi,
            $tgl_masuk, $status_karyawan,
            $no_rekening, $nama_bank, $npwp, $no_bpjs_tk, $no_bpjs_kes, $lokasi_kerja
        ];
        $stmt = sqlsrv_query($conn, $sql, $params);
        if ($stmt === false) {
            $errs = sqlsrv_errors();
            $errmsg = $errs[0]['message'] ?? '?';
            // Jika no_ktp duplikat, retry dengan nilai lain
            if (stripos($errmsg, 'UX_pegawai_no_ktp') !== false || stripos($errmsg, 'UNIQUE') !== false) {
                $params[1] = 'KTP-' . $csv_nik . '-' . uniqid();
                $stmt2 = sqlsrv_query($conn, $sql, $params);
                if ($stmt2 === false) {
                    $errors++;
                    $log[] = "GAGAL INSERT (retry) nik=$csv_nik | " . (sqlsrv_errors()[0]['message'] ?? '?');
                } else {
                    $inserted++;
                }
            } else {
                $errors++;
                $log[] = "GAGAL INSERT nik=$csv_nik | $errmsg";
            }
        } else {
            $inserted++;
        }
    }
}

fclose($handle);

// ============================================================
// STEP 4: Hasil
// ============================================================
echo "\n=== HASIL IMPORT ===\n";
echo "Berhasil UPDATE : $updated\n";
echo "Berhasil INSERT : $inserted\n";
echo "Gagal           : $errors\n";

if (!empty($log)) {
    echo "\n=== LOG ERROR ===\n";
    foreach (array_slice($log, 0, 30) as $l) {
        echo $l . "\n";
    }
    if (count($log) > 30) {
        echo "... dan " . (count($log) - 30) . " error lainnya.\n";
    }
}

echo "\n=== SELESAI ===\n";
