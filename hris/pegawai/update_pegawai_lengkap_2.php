<?php
/**
 * update_pegawai_lengkap.php
 * Simpan perubahan dari edit.php ke dbHR.
 * Strategi tabel anak: hapus semua lalu insert ulang (paling sederhana & konsisten).
 */
include '../config/koneksi_sqlsrv.php';
include '_normalisasi_enum.php';
require_once __DIR__ . '/_catat_histori.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header("Location: index.php"); exit; }

$id = (int)($_POST['id_peg'] ?? 0);
if (!$id) { die("ID tidak valid."); }

function v($k){ $x=trim($_POST[$k]??''); return $x===''?null:$x; }
function q($conn,$sql,$p,$langkah){
    $st=sqlsrv_query($conn,$sql,array_values($p));
    if($st===false) throw new Exception("[$langkah] ".print_r(sqlsrv_errors(),true));
    return $st;
}

if (sqlsrv_begin_transaction($conn)===false) die("Gagal mulai transaksi.");

try {
    /* ---------- UPDATE pegawai ---------- */
    $set = [
        'nama_peg'        => v('nama'),
        'email_peg'       => v('email'),
        'no_hp_peg'       => v('no_hp'),
        'tgl_lahir'       => v('tanggal_lahir'),
        'tempat_lahir'    => v('tempat_lahir'),
        'gender'          => enum_db('pegawai.gender', v('gender')),
        'agama'           => v('agama'),
        'status_nikah'    => enum_db('pegawai.status_nikah', v('ptkp_status')),
        'status_kawin'    => v('status_kawin'),
        'npwp'            => v('npwp'),
        'no_ktp'          => v('no_ktp'),
        'alamat_ktp_peg'  => v('almt_tetap'),
        'rt'              => v('almt_tetap_rt'),
        'rw'              => v('almt_tetap_rw'),
        'kelurahan'       => v('almt_tetap_desa'),
        'kecamatan'       => v('almt_tetap_kecamatan'),
        'kota'            => v('almt_tetap_kota'),
        'provinsi'        => v('almt_tetap_provinsi'),
        'kode_pos'        => v('almt_tetap_kodepos'),
        'alamat_domi_peg' => v('almt_smtr'),
        'rt_dom'          => v('almt_smtr_rt'),
        'rw_dom'          => v('almt_smtr_rw'),
        'kelurahan_dom'   => v('almt_smtr_desa'),
        'kecamatan_dom'   => v('almt_smtr_kecamatan'),
        'kota_dom'        => v('almt_smtr_kota'),
        'provinsi_dom'    => v('almt_smtr_provinsi'),
        'kode_pos_dom'    => v('almt_smtr_kodepos'),
        'tgl_masuk'       => v('enterprise_begin'),
        'tgl_akhir_kontrak' => v('enterprise_end'),
        'tgl_berhenti'    => v('termination_effective'),
        'alasan_berhenti' => v('term_reason'),
        'lokasi_kerja'    => v('unit_kerja'),
        'work_location'   => v('work_location'),
        'no_rekening'     => v('bank_rekening'),
        'nama_bank'       => v('bank_nama'),
        'company_name'    => v('company_name'),
        'contract_month'  => v('contract_month'),
        'position_code'   => v('job_title') ?: v('position_code'),
        'level_code'      => v('level_code'),
        'grade_code'      => v('grade_code'),
        'employee_subgroup' => v('employee_subgroup'),
        'status_karyawan' => enum_db('pegawai.status_karyawan', v('employee_subgroup')),
        'bank_payee'      => v('bank_payee'),
        'bank_kode'       => v('bank_kode'),
        'bank_detail'     => v('bank_detail'),
    ];
    // ambil data lama untuk histori
    $rsLama = sqlsrv_query($conn, "SELECT * FROM dbo.pegawai WHERE id_peg=?", [$id]);
    $dataLama = $rsLama ? sqlsrv_fetch_array($rsLama, SQLSRV_FETCH_ASSOC) : [];

    $kolom = array_keys($set);
    $params = array_values($set);
    $params[] = $id;
    $sql = "UPDATE dbo.pegawai SET " . implode(',', array_map(fn($k)=>"$k=?", $kolom)) . " WHERE id_peg=?";
    sqlsrv_free_stmt(q($conn,$sql,$params,'UPDATE pegawai'));

    // catat histori perubahan (hanya kolom yang berubah)
    [$namaUser, $idUser] = _userSekarang();
    catatHistoriEdit($conn, $id, $dataLama, $set, $namaUser, $idUser);

    /* ---------- ganti total tabel anak ---------- */
    foreach (['keluarga_pegawai','pendidikan_pegawai','pengalaman_kerja'] as $t)
        sqlsrv_free_stmt(q($conn,"DELETE FROM dbo.$t WHERE pegawai_id=?",[$id],"hapus $t"));

    // keluarga
    if (!empty($_POST['kel_nama'])) {
        $sqlK="INSERT INTO dbo.keluarga_pegawai (pegawai_id,nama,hubungan,gender,status_nikah,status_hidup,tempat_lahir,tgl_lahir,no_ktp,no_kk,no_bpjs) VALUES (?,?,?,?,?,?,?,?,?,?,?)";
        foreach ($_POST['kel_nama'] as $i=>$nm){ if(trim($nm)==='')continue;
            sqlsrv_free_stmt(q($conn,$sqlK,[$id,trim($nm),
                enum_db('keluarga.hubungan',$_POST['kel_hubungan'][$i]??null),
                enum_db('keluarga.gender',$_POST['kel_gender'][$i]??null),
                enum_db('keluarga.status_nikah',$_POST['kel_kawin'][$i]??null),
                enum_db('keluarga.status_hidup',$_POST['kel_hidup'][$i]??null),
                ($_POST['kel_tmp_lahir'][$i]??'')?:null,($_POST['kel_tgl_lahir'][$i]??'')?:null,
                ($_POST['kel_ktp'][$i]??'')?:null,($_POST['kel_kk'][$i]??'')?:null,($_POST['kel_bpjs'][$i]??'')?:null
            ],'insert keluarga '.($i+1)));
        }
    }
    // pendidikan
    if (!empty($_POST['edu_sekolah'])) {
        $sqlE="INSERT INTO dbo.pendidikan_pegawai (pegawai_id,nama_sekolah,jenjang,jurusan,lokasi,tahun_mulai,tahun_selesai,ipk) VALUES (?,?,?,?,?,?,?,?)";
        foreach ($_POST['edu_sekolah'] as $i=>$sk){ if(trim($sk)==='')continue;
            sqlsrv_free_stmt(q($conn,$sqlE,[$id,trim($sk),
                enum_db('pendidikan.jenjang',$_POST['edu_jenjang'][$i]??null),
                ($_POST['edu_jurusan'][$i]??'')?:null,($_POST['edu_lokasi'][$i]??'')?:null,
                ($_POST['edu_mulai'][$i]??'')?:null,($_POST['edu_selesai'][$i]??'')?:null,($_POST['edu_ipk'][$i]??'')?:null
            ],'insert pendidikan '.($i+1)));
        }
    }
    // pengalaman
    if (!empty($_POST['exp_perusahaan'])) {
        $sqlX="INSERT INTO dbo.pengalaman_kerja (pegawai_id,nama_perusahaan,jabatan,tgl_mulai,tgl_selesai,keterangan) VALUES (?,?,?,?,?,?)";
        foreach ($_POST['exp_perusahaan'] as $i=>$pr){ if(trim($pr)==='')continue;
            sqlsrv_free_stmt(q($conn,$sqlX,[$id,trim($pr),
                ($_POST['exp_jabatan'][$i]??'')?:null,($_POST['exp_mulai'][$i]??'')?:null,
                ($_POST['exp_akhir'][$i]??'')?:null,($_POST['exp_ket'][$i]??'')?:null
            ],'insert pengalaman '.($i+1)));
        }
    }

    sqlsrv_commit($conn);
    echo "<script>alert('Data pegawai berhasil diperbarui');window.location.href='detail.php?id=$id';</script>";

} catch (Exception $e) {
    sqlsrv_rollback($conn);
    echo "<pre style='padding:16px;background:#fff3f3;color:#900'>GAGAL UPDATE\n\n".htmlspecialchars($e->getMessage())."\n\n<a href='javascript:history.back()'>Kembali</a></pre>";
}
