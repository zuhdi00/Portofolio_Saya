<?php
/**
 * submit_pegawai_lengkap.php
 * Simpan form "Tambah Pegawai Lengkap" ke SQL Server, database dbHR.
 * Tabel: pegawai, keluarga_pegawai, pendidikan_pegawai, pengalaman_kerja
 * Butuh: ../config/koneksi_sqlsrv.php (extension php_sqlsrv aktif)
 */
include '../config/koneksi_sqlsrv.php';   // $conn

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: tambah_pegawai_lengkap.php");
    exit;
}

function v($key) {                       // ambil POST, kosong -> NULL
    $val = trim($_POST[$key] ?? '');
    return $val === '' ? null : $val;
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
        'status_nikah'    => 'status_kawin',
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
        'tgl_masuk'       => 'enterprise_begin',
        'tgl_akhir_kontrak' => 'enterprise_end',
        'tgl_berhenti'    => 'termination_effective',
        'alasan_berhenti' => 'term_reason',
        'lokasi_kerja'    => 'work_location',
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
        'ptkp_status'       => 'ptkp_status',
        'bank_payee'        => 'bank_payee',
        'bank_kode'         => 'bank_kode',
        'bank_detail'       => 'bank_detail',
    ];

    $cols   = array_keys($map);
    $params = array_map(fn($f) => v($f), $map);

    // status_karyawan & is_aktif diisi default saat pegawai baru
    $cols[]   = 'status_karyawan'; $params[] = 'Aktif';
    $cols[]   = 'is_aktif';        $params[] = 1;
    // barcode presensi = pakai no_ktp (lihat catatan di 01_alter_pegawai_dbHR.sql)
    $cols[]   = 'barcode';         $params[] = v('no_ktp');

    // jabatan_id / unit_kerja_id: WAJIB dikirim dari form sebagai dropdown
    // (lihat catatan di bawah / update tambah_pegawai_lengkap.php)
    $cols[]   = 'jabatan_id';    $params[] = v('jabatan_id')    ? (int)v('jabatan_id')    : null;
    $cols[]   = 'unit_kerja_id'; $params[] = v('unit_kerja_id') ? (int)v('unit_kerja_id') : null;

    $sql = "INSERT INTO dbo.pegawai (" . implode(',', $cols) . ")
            VALUES (" . implode(',', array_fill(0, count($cols), '?')) . ");
            SELECT SCOPE_IDENTITY() AS id_peg;";
    $stmt = sqlsrv_query($conn, $sql, $params);
    if ($stmt === false) throw new Exception(print_r(sqlsrv_errors(), true));
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
            $p = [
                $idPeg, trim($nm),
                $_POST['kel_hubungan'][$i] ?? null,
                $_POST['kel_gender'][$i] ?? null,
                $_POST['kel_kawin'][$i] ?? null,
                $_POST['kel_hidup'][$i] ?? null,
                ($_POST['kel_tmp_lahir'][$i] ?? '') ?: null,
                ($_POST['kel_tgl_lahir'][$i] ?? '') ?: null,
                ($_POST['kel_ktp'][$i] ?? '') ?: null,
                ($_POST['kel_kk'][$i] ?? '') ?: null,
                ($_POST['kel_bpjs'][$i] ?? '') ?: null,
            ];
            $st = sqlsrv_query($conn, $sqlKel, $p);
            if ($st === false) throw new Exception(print_r(sqlsrv_errors(), true));
            sqlsrv_free_stmt($st);
        }
    }

    // ---------- 3. pendidikan_pegawai ----------
    if (!empty($_POST['edu_sekolah'])) {
        $sqlEdu = "INSERT INTO dbo.pendidikan_pegawai
            (pegawai_id, nama_sekolah, jenjang, jurusan, lokasi, tahun_mulai, tahun_selesai, ipk)
            VALUES (?,?,?,?,?,?,?,?)";
        foreach ($_POST['edu_sekolah'] as $i => $sek) {
            if (trim($sek) === '') continue;
            $p = [
                $idPeg, trim($sek),
                $_POST['edu_jenjang'][$i] ?? null,
                ($_POST['edu_jurusan'][$i] ?? '') ?: null,
                ($_POST['edu_lokasi'][$i] ?? '') ?: null,
                ($_POST['edu_mulai'][$i] ?? '') ?: null,
                ($_POST['edu_selesai'][$i] ?? '') ?: null,
                ($_POST['edu_ipk'][$i] ?? '') ?: null,
            ];
            $st = sqlsrv_query($conn, $sqlEdu, $p);
            if ($st === false) throw new Exception(print_r(sqlsrv_errors(), true));
            sqlsrv_free_stmt($st);
        }
    }

    // ---------- 4. pengalaman_kerja ----------
    if (!empty($_POST['exp_perusahaan'])) {
        $sqlExp = "INSERT INTO dbo.pengalaman_kerja
            (pegawai_id, nama_perusahaan, jabatan, tgl_mulai, tgl_selesai, keterangan)
            VALUES (?,?,?,?,?,?)";
        foreach ($_POST['exp_perusahaan'] as $i => $prs) {
            if (trim($prs) === '') continue;
            $p = [
                $idPeg, trim($prs),
                ($_POST['exp_jabatan'][$i] ?? '') ?: null,
                ($_POST['exp_mulai'][$i] ?? '') ?: null,
                ($_POST['exp_akhir'][$i] ?? '') ?: null,
                ($_POST['exp_ket'][$i] ?? '') ?: null,
            ];
            $st = sqlsrv_query($conn, $sqlExp, $p);
            if ($st === false) throw new Exception(print_r(sqlsrv_errors(), true));
            sqlsrv_free_stmt($st);
        }
    }

    sqlsrv_commit($conn);
    echo "<script>alert('Data pegawai berhasil disimpan (ID: $idPeg)');
          window.location.href='index.php';</script>";

} catch (Exception $e) {
    sqlsrv_rollback($conn);
    $msg = str_replace(["'", "\n", "\r"], ["\\'", ' ', ''], substr($e->getMessage(), 0, 400));
    echo "<script>alert('Gagal menyimpan: $msg');
          window.history.back();</script>";
}
