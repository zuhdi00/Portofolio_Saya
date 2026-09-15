<?php
/**
 * submit_pegawai_lengkap.php   [VERSI v5 - 2026-07-23]
 * Simpan form "Tambah Pegawai Lengkap" ke SQL Server, database dbHR.
 * Tabel: pegawai, keluarga_pegawai, pendidikan_pegawai, pengalaman_kerja
 * Butuh: ../config/koneksi_sqlsrv.php (extension php_sqlsrv aktif)
 *        ./_normalisasi_enum.php      (peta nilai enum -> CHECK constraint dbHR)
 */
define('VERSI_FILE', 'v5');

include '../config/koneksi_sqlsrv.php';   // $conn
include '_normalisasi_enum.php';          // $PETA_ENUM + fungsi enum_db()

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: tambah_pegawai_lengkap.php");
    exit;
}

/** ambil POST, kosong -> NULL */
function v($key) {
    $val = trim($_POST[$key] ?? '');
    return $val === '' ? null : $val;
}

/**
 * Pembungkus sqlsrv_query.
 * array_values() dipanggil paksa di sini supaya param TIDAK PERNAH punya key string
 * (penyebab error IMSSP -57 "String keys are not allowed in parameters arrays").
 * $langkah dipakai untuk menandai bagian mana yang gagal.
 */
function q($conn, $sql, $params, $langkah) {
    $stmt = sqlsrv_query($conn, $sql, array_values($params));
    if ($stmt === false) {
        throw new Exception("[$langkah] " . print_r(sqlsrv_errors(), true));
    }
    return $stmt;
}

if (sqlsrv_begin_transaction($conn) === false) {
    die("Gagal memulai transaksi: " . print_r(sqlsrv_errors(), true));
}

try {
    // ---------- 1. pegawai ----------
    // key kiri  = kolom di tabel dbo.pegawai (dbHR)
    // key kanan = nama field di form (tambah_pegawai_lengkap.php)
    $map = [
        'no_ktp'          => 'no_ktp',
        'nama_peg'        => 'nama',
        'email_peg'       => 'email',
        'no_hp_peg'       => 'no_hp',
        'tgl_lahir'       => 'tanggal_lahir',
        'tempat_lahir'    => 'tempat_lahir',
        'gender'          => 'gender',
        'agama'           => 'agama',
        'status_nikah'    => 'ptkp_status',   // dbHR menyimpan KODE PTKP (TK/K0/K1..), bukan status kawin
        'npwp'            => 'npwp',
        // Alamat KTP (= alamat tetap)
        'alamat_ktp_peg'  => 'almt_tetap',
        'rt'              => 'almt_tetap_rt',
        'rw'              => 'almt_tetap_rw',
        'kelurahan'       => 'almt_tetap_desa',
        'kecamatan'       => 'almt_tetap_kecamatan',
        'kota'            => 'almt_tetap_kota',
        'provinsi'        => 'almt_tetap_provinsi',
        'kode_pos'        => 'almt_tetap_kodepos',
        // Alamat domisili (= alamat sementara)
        'alamat_domi_peg' => 'almt_smtr',
        'rt_dom'          => 'almt_smtr_rt',
        'rw_dom'          => 'almt_smtr_rw',
        'kelurahan_dom'   => 'almt_smtr_desa',
        'kecamatan_dom'   => 'almt_smtr_kecamatan',
        'kota_dom'        => 'almt_smtr_kota',
        'provinsi_dom'    => 'almt_smtr_provinsi',
        'kode_pos_dom'    => 'almt_smtr_kodepos',
        // Kepegawaian
        'tgl_masuk'         => 'enterprise_begin',
        'tgl_akhir_kontrak' => 'enterprise_end',
        'tgl_berhenti'      => 'termination_effective',
        'alasan_berhenti'   => 'term_reason',
        'lokasi_kerja'      => 'work_location',
        // Bank
        'no_rekening'     => 'bank_rekening',
        'nama_bank'       => 'bank_nama',
        // Kolom tambahan (hasil ALTER TABLE 01_alter_pegawai_dbHR.sql)
        'company_name'      => 'company_name',
        'contract_month'    => 'contract_month',
        'position_code'     => 'position_code',
        'level_code'        => 'level_code',
        'grade_code'        => 'grade_code',
        'employee_subgroup' => 'employee_subgroup',
        'bank_payee'        => 'bank_payee',
        'bank_kode'         => 'bank_kode',
        'bank_detail'       => 'bank_detail',
    ];

    // dibangun manual dgn $params[] supaya key dijamin numerik urut.
    // enum_db() menerjemahkan nilai form -> nilai yang diizinkan CHECK constraint.
    $cols   = [];
    $params = [];
    foreach ($map as $kolom => $field) {
        $cols[]   = $kolom;
        $params[] = enum_db('pegawai.' . $kolom, v($field));
    }

    // nilai default pegawai baru
    // status_karyawan (CHECK: harian/kontrak/tetap) diturunkan dari Employee Subgroup
    $cols[] = 'status_karyawan';
    $params[] = enum_db('pegawai.status_karyawan', v('employee_subgroup')) ?? 'tetap';
    $cols[] = 'is_aktif';        $params[] = 1;
    // barcode presensi = pakai no_ktp
    $cols[] = 'barcode';         $params[] = v('no_ktp');
    // FK: dikirim form sebagai dropdown (lihat _snippet_dropdown_jabatan_unit.php)
    $cols[] = 'jabatan_id';      $params[] = v('jabatan_id')    ? (int) v('jabatan_id')    : null;
    $cols[] = 'unit_kerja_id';   $params[] = v('unit_kerja_id') ? (int) v('unit_kerja_id') : null;

    $sql = "INSERT INTO dbo.pegawai (" . implode(',', $cols) . ")
            VALUES (" . implode(',', array_fill(0, count($cols), '?')) . ");
            SELECT SCOPE_IDENTITY() AS id_peg;";
    $stmt = q($conn, $sql, $params, 'INSERT pegawai');
    sqlsrv_next_result($stmt);
    sqlsrv_fetch($stmt);
    $idPeg = (int) sqlsrv_get_field($stmt, 0);
    sqlsrv_free_stmt($stmt);

    // ---------- 2. keluarga_pegawai ----------
    if (!empty($_POST['kel_nama'])) {
        $sqlKel = "INSERT INTO dbo.keluarga_pegawai
            (pegawai_id, nama, hubungan, gender, status_nikah, status_hidup,
             tempat_lahir, tgl_lahir, no_ktp, no_kk, no_bpjs)
            VALUES (?,?,?,?,?,?,?,?,?,?,?)";
        foreach ($_POST['kel_nama'] as $i => $nm) {
            if (trim($nm) === '') continue;
            $p = [];
            $p[] = $idPeg;
            $p[] = trim($nm);
            $p[] = enum_db('keluarga.hubungan', $_POST['kel_hubungan'][$i] ?? null);
            $p[] = enum_db('keluarga.gender', $_POST['kel_gender'][$i] ?? null);
            $p[] = enum_db('keluarga.status_nikah', $_POST['kel_kawin'][$i] ?? null);
            $p[] = enum_db('keluarga.status_hidup', $_POST['kel_hidup'][$i] ?? null);
            $p[] = ($_POST['kel_tmp_lahir'][$i] ?? '') ?: null;
            $p[] = ($_POST['kel_tgl_lahir'][$i] ?? '') ?: null;
            $p[] = ($_POST['kel_ktp'][$i] ?? '') ?: null;
            $p[] = ($_POST['kel_kk'][$i] ?? '') ?: null;
            $p[] = ($_POST['kel_bpjs'][$i] ?? '') ?: null;
            sqlsrv_free_stmt(q($conn, $sqlKel, $p, 'INSERT keluarga baris ' . ($i + 1)));
        }
    }

    // ---------- 3. pendidikan_pegawai ----------
    if (!empty($_POST['edu_sekolah'])) {
        $sqlEdu = "INSERT INTO dbo.pendidikan_pegawai
            (pegawai_id, nama_sekolah, jenjang, jurusan, lokasi, tahun_mulai, tahun_selesai, ipk)
            VALUES (?,?,?,?,?,?,?,?)";
        foreach ($_POST['edu_sekolah'] as $i => $sek) {
            if (trim($sek) === '') continue;
            $p = [];
            $p[] = $idPeg;
            $p[] = trim($sek);
            $p[] = enum_db('pendidikan.jenjang', $_POST['edu_jenjang'][$i] ?? null);
            $p[] = ($_POST['edu_jurusan'][$i] ?? '') ?: null;
            $p[] = ($_POST['edu_lokasi'][$i] ?? '') ?: null;
            $p[] = ($_POST['edu_mulai'][$i] ?? '') ?: null;
            $p[] = ($_POST['edu_selesai'][$i] ?? '') ?: null;
            $p[] = ($_POST['edu_ipk'][$i] ?? '') ?: null;
            sqlsrv_free_stmt(q($conn, $sqlEdu, $p, 'INSERT pendidikan baris ' . ($i + 1)));
        }
    }

    // ---------- 4. pengalaman_kerja ----------
    if (!empty($_POST['exp_perusahaan'])) {
        $sqlExp = "INSERT INTO dbo.pengalaman_kerja
            (pegawai_id, nama_perusahaan, jabatan, tgl_mulai, tgl_selesai, keterangan)
            VALUES (?,?,?,?,?,?)";
        foreach ($_POST['exp_perusahaan'] as $i => $prs) {
            if (trim($prs) === '') continue;
            $p = [];
            $p[] = $idPeg;
            $p[] = trim($prs);
            $p[] = ($_POST['exp_jabatan'][$i] ?? '') ?: null;
            $p[] = ($_POST['exp_mulai'][$i] ?? '') ?: null;
            $p[] = ($_POST['exp_akhir'][$i] ?? '') ?: null;
            $p[] = ($_POST['exp_ket'][$i] ?? '') ?: null;
            sqlsrv_free_stmt(q($conn, $sqlExp, $p, 'INSERT pengalaman baris ' . ($i + 1)));
        }
    }

    sqlsrv_commit($conn);
    echo "<script>alert('Data pegawai berhasil disimpan (ID: $idPeg)');
          window.location.href='index.php';</script>";

} catch (Exception $e) {
    sqlsrv_rollback($conn);
    // tampilkan versi file + langkah yang gagal, biar mudah dilacak
    echo "<pre style='font:13px/1.5 monospace;padding:16px;background:#fff3f3;color:#900'>";
    echo "GAGAL MENYIMPAN  (file versi: " . VERSI_FILE . ")\n\n";
    echo htmlspecialchars($e->getMessage());
    echo "\n\n<a href='javascript:history.back()'>&larr; Kembali ke form</a>";
    echo "</pre>";
}
