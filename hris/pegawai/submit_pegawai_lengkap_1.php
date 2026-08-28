<?php
/**
 * submit_pegawai_lengkap.php
 * Menyimpan data form pegawai lengkap ke SQL Server (spsdmz2, db hris).
 * Butuh: ../config/koneksi_sqlsrv.php  (ekstensi php_sqlsrv aktif)
 */
include '../config/koneksi_sqlsrv.php';   // menyediakan $conn (sqlsrv)

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: tambah_pegawai_lengkap.php");
    exit;
}

function v($key) {                       // ambil POST, kosong -> NULL
    $val = trim($_POST[$key] ?? '');
    return $val === '' ? null : $val;
}

// mulai transaksi: semua tabel tersimpan atau tidak sama sekali
if (sqlsrv_begin_transaction($conn) === false) {
    die("Gagal memulai transaksi: " . print_r(sqlsrv_errors(), true));
}

try {
    // ---------- 1. pegawai_lengkap ----------
    $cols = [
        'company_name','nik','enterprise_begin','enterprise_end','termination_effective',
        'contract_month','term_reason','personnel_area','personnel_subarea','job_title',
        'unit_kerja','position_code','work_location','level_code','grade_code','employee_subgroup',
        'nama','tempat_lahir','tanggal_lahir','agama','npwp','no_ktp','gender','status_kawin',
        'email','ptkp_status',
        'almt_tetap','almt_tetap_rt','almt_tetap_rw','almt_tetap_desa','almt_tetap_kecamatan',
        'almt_tetap_kota','almt_tetap_provinsi','almt_tetap_negara','almt_tetap_kodepos',
        'no_hp','no_telp',
        'almt_smtr','almt_smtr_rt','almt_smtr_rw','almt_smtr_desa','almt_smtr_kecamatan',
        'almt_smtr_kota','almt_smtr_provinsi','almt_smtr_negara','almt_smtr_kodepos','almt_smtr_telp',
        'bank_payee','bank_kode','bank_nama','bank_detail','bank_rekening'
    ];
    $params = array_map('v', $cols);
    $sql = "INSERT INTO dbo.pegawai_lengkap (" . implode(',', $cols) . ")
            VALUES (" . implode(',', array_fill(0, count($cols), '?')) . ");
            SELECT SCOPE_IDENTITY() AS id;";
    $stmt = sqlsrv_query($conn, $sql, $params);
    if ($stmt === false) throw new Exception(print_r(sqlsrv_errors(), true));
    sqlsrv_next_result($stmt);
    sqlsrv_fetch($stmt);
    $pegawaiId = (int) sqlsrv_get_field($stmt, 0);
    sqlsrv_free_stmt($stmt);

    // ---------- 2. keluarga ----------
    if (!empty($_POST['kel_nama'])) {
        $sqlKel = "INSERT INTO dbo.pegawai_keluarga
            (pegawai_id, nama, hubungan, gender, status_kawin, status_hidup,
             tempat_lahir, tanggal_lahir, no_ktp, no_kk, no_bpjs)
            VALUES (?,?,?,?,?,?,?,?,?,?,?)";
        foreach ($_POST['kel_nama'] as $i => $nm) {
            if (trim($nm) === '') continue;
            $p = [
                $pegawaiId, trim($nm),
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

    // ---------- 3. pendidikan ----------
    if (!empty($_POST['edu_sekolah'])) {
        $sqlEdu = "INSERT INTO dbo.pegawai_pendidikan
            (pegawai_id, sekolah, jenjang, jurusan, lokasi, tahun_mulai, tahun_selesai, ipk)
            VALUES (?,?,?,?,?,?,?,?)";
        foreach ($_POST['edu_sekolah'] as $i => $sek) {
            if (trim($sek) === '') continue;
            $p = [
                $pegawaiId, trim($sek),
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

    // ---------- 4. pengalaman kerja ----------
    if (!empty($_POST['exp_perusahaan'])) {
        $sqlExp = "INSERT INTO dbo.pegawai_pengalaman
            (pegawai_id, perusahaan, jabatan, tanggal_mulai, tanggal_akhir, keterangan)
            VALUES (?,?,?,?,?,?)";
        foreach ($_POST['exp_perusahaan'] as $i => $prs) {
            if (trim($prs) === '') continue;
            $p = [
                $pegawaiId, trim($prs),
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
    echo "<script>alert('Data pegawai berhasil disimpan (ID: $pegawaiId)');
          window.location.href='index.php';</script>";

} catch (Exception $e) {
    sqlsrv_rollback($conn);
    $msg = str_replace(["'", "\n", "\r"], ["\\'", ' ', ''], substr($e->getMessage(), 0, 400));
    echo "<script>alert('Gagal menyimpan: $msg');
          window.history.back();</script>";
}
