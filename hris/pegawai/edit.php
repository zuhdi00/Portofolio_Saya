<?php
require_once __DIR__ . '/../auth/auth.php';
wajib_izin('pegawai_edit');
$page_title = "Edit Pegawai";
include '../template/header.php';
include '../config/koneksi_sqlsrv.php';   // $conn
include '../pegawai/_normalisasi_enum.php';
include '../template/sidebar.php';

$id = (int)($_GET['id'] ?? 0);
if (!$id) { die("ID pegawai tidak valid."); }

/* ---------- ambil data pegawai ---------- */
$st = sqlsrv_query($conn, "SELECT * FROM dbo.pegawai WHERE id_peg = ?", [$id]);
if ($st === false) die("<pre>".print_r(sqlsrv_errors(),true)."</pre>");
$p = sqlsrv_fetch_array($st, SQLSRV_FETCH_ASSOC);
if (!$p) die("Data pegawai tidak ditemukan.");

/** ambil kolom dgn aman - kalau tidak ada, kembalikan null (bukan error) */
function g($p,$k){ return array_key_exists($k,$p) ? $p[$k] : null; }

/* ---------- tabel anak ---------- */
function ambil($conn,$sql,$id){ $st=sqlsrv_query($conn,$sql,[$id]); $o=[]; if($st) while($r=sqlsrv_fetch_array($st,SQLSRV_FETCH_ASSOC)) $o[]=$r; return $o; }
$keluarga   = ambil($conn,"SELECT * FROM dbo.keluarga_pegawai   WHERE pegawai_id=?",$id);
$pendidikan = ambil($conn,"SELECT * FROM dbo.pendidikan_pegawai WHERE pegawai_id=? ORDER BY tahun_mulai",$id);
$pengalaman = ambil($conn,"SELECT * FROM dbo.pengalaman_kerja   WHERE pegawai_id=? ORDER BY tgl_mulai",$id);

/* ---------- helper tampilan ---------- */
function val($v){ return htmlspecialchars((string)($v ?? '')); }
function dtv($v){ return $v instanceof DateTime ? $v->format('Y-m-d') : ''; }
/** balik nilai DB -> label form (kebalikan enum_db) */
function rev($konteks,$nilai){
    global $PETA_ENUM;
    if($nilai===null) return '';
    $peta = $PETA_ENUM[$konteks] ?? [];
    $flip = array_flip($peta);
    return $flip[$nilai] ?? $nilai;
}
/** cetak <option> dengan salah satu terpilih */
function opts($arr,$dipilih){
    $out='<option value="">-- pilih --</option>';
    foreach($arr as $o){
        $sel = ((string)$o === (string)$dipilih) ? ' selected' : '';
        $o2 = htmlspecialchars($o);
        $out .= "<option$sel>$o2</option>";
    }
    return $out;
}
$JABATAN = ["Direksi","General Manager","Commercial Manager","Factory Manager","Manager Produksi","Manager PPIC","Manager Marketing","Manager Accounting & Finance","Manager HRGA","Senior Manager","Manager","Junior Manager","SPV Convert","SPV PPIC","SPV QC","SPV Gudang FG & Ekspedisi","SPV Mekanik","SPV HR","SPV Accounting","SPV Corr","SPV Design & Persiapan","Koordinator Design","Koordinator QC","Koordinator Corr","Koordinator Print","Koordinator Non Print","Koordinator Gudang Barang Jadi","Koordinator Gudang Barang Baku","Koordinator Gudang Barang Penolong","Koordinator Ekspedisi","Koordinator Mekanik","Koordinator Listrik","Koordinator Purchasing","Koordinator Internal Marketing","Koordinator EDP","Kepala Seksi (Kasie) Printing","Kepala Seksi Corr","Kepala Seksi Non Printing","Wakil Kepala Seksi (Wk. Kasie) Corr","Wakil Kepala Seksi (Wk. Kasie) Printing","Wakil Kepala Seksi (Wk. Kasie) Non Printing","Staff Design","Staff Produksi","Staff Purchasing","Staff Internal Marketing","Sales Staff","Staff Finance","Staff Accounting","Staff HR","Staff GA","Staff Gudang Barang Jadi","Staff EDP","Admin Produksi","Admin QC","Admin Gudang Barang Jadi","Admin Gudang Barang Baku","Admin Gudang Barang Penolong","Admin Ekspedisi","Checker Gudang Barang Jadi","Checker Gudang Barang Baku","Checker Gudang Barang Penolong","Checker Ekspedisi","QC","Inspeksi QC","Inventory Control","Production Planning","R & D","MC OP Data Analis","Operator Corr","Operator Print","Operator Non Print","Teknisi Listrik","Teknisi Mekanik","Sample Maker","APR Setting Dies & Pisau","WIP","Helper Corr","Helper Printing","Helper Non Print","Helper Ekspedisi","Helper Persiapan","Helper Umum","Sopir","Receptionist","CG"];
$LEVEL = ['1a','1b','2a','2b','3a','3b','4a','4b','5a','5b','6a','6b','6c','7'];
$SUBGROUP = ['Kary. Tetap','Kary. Harian Kontrak (kls.1)','Kary. Harian Kontrak (kls.2)','Kary. Borongan','Staff'];
$AGAMA = ['Islam','Kristen','Katolik','Hindu','Buddha','Konghucu'];
$KAWIN = ['SINGLE','MARRIED','DIVORCED',''];
$PTKP  = ['Single, 0 Anak (TK/0) - Rp 54,000,000','Kawin, 0 Anak (K/0) - Rp 58,500,000','Kawin, 1 Anak (K/1) - Rp 63,000,000','Kawin, 2 Anak (K/2) - Rp 67,500,000','Kawin, 3 Anak (K/3) - Rp 72,000,000'];
?>

<main id="main" class="main">
    <div class="pagetitle">
        <h1 style="color:#c00;">Edit Data Pegawai</h1>
        <nav><ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="index.php">Pegawai</a></li>
            <li class="breadcrumb-item active">Edit</li>
        </ol></nav>
    </div>

    <style>
        fieldset { border:1px solid #ccc; border-radius:6px; padding:18px; margin-bottom:22px; background:#fff; }
        legend { font-size:1.25rem; font-weight:700; color:#c00; width:auto; padding:0 8px; }
        .form-label { font-weight:600; color:#b00; font-size:.85rem; }
        .sub-box { border:1px solid #ddd; border-radius:6px; padding:14px; margin-bottom:14px; }
        .sub-box .box-title { color:#b00; font-weight:700; font-size:.95rem; margin-top:-24px; background:#fff; display:inline-block; padding:0 6px; }
        table.dyn-table input, table.dyn-table select { font-size:.82rem; }
    </style>

    <section class="section">
        <form action="update_pegawai_lengkap.php" method="POST" id="formPegawai">
            <input type="hidden" name="id_peg" value="<?= $id ?>">

            <!-- HEADER -->
            <fieldset>
                <div class="row g-3">
                    <div class="col-md-3"><label class="form-label">Company Name :</label>
                        <input type="text" name="company_name" class="form-control" value="<?= val(g($p,'company_name') ?: 'PT. MEKABOX SEJAHTERA') ?>"></div>
                    <div class="col-md-3"><label class="form-label">NIK :</label>
                        <input type="text" name="nik" class="form-control" value="<?= val(g($p,'nik')) ?>"></div>
                    <div class="col-md-6"><label class="form-label">Nama Pegawai :</label>
                        <input type="text" name="nama" class="form-control text-uppercase" value="<?= val(g($p,'nama_peg')) ?>" required></div>
                </div>
            </fieldset>

            <!-- ORGANIZATIONAL -->
            <fieldset>
                <legend>Organizational Assignment</legend>
                <div class="row g-3">
                    <div class="col-md-3"><label class="form-label">Enterprise Begin :</label>
                        <input type="date" name="enterprise_begin" class="form-control" value="<?= dtv(g($p,'tgl_masuk')) ?>"></div>
                    <div class="col-md-3"><label class="form-label">Enterprise End :</label>
                        <input type="date" name="enterprise_end" class="form-control" value="<?= dtv(g($p,'tgl_akhir_kontrak') ?? null) ?>"></div>
                    <div class="col-md-3"><label class="form-label">Termination Effective :</label>
                        <input type="date" name="termination_effective" class="form-control" value="<?= dtv(g($p,'tgl_berhenti') ?? null) ?>"></div>
                    <div class="col-md-3"><label class="form-label">Contract (Month) :</label>
                        <input type="number" name="contract_month" class="form-control" value="<?= val(g($p,'contract_month')) ?>"></div>
                    <div class="col-md-6"><label class="form-label">Term Reason :</label>
                        <input type="text" name="term_reason" class="form-control" value="<?= val(g($p,'alasan_berhenti') ?? '') ?>"></div>
                    
                    
                    <div class="col-md-4"><label class="form-label">Job Title :</label>
                        <select name="job_title" class="form-select"><?= opts($JABATAN, g($p,'position_code') ?? '') ?></select></div>
                    <div class="col-md-8"><label class="form-label">Unit :</label>
                        <input type="text" name="unit_kerja" class="form-control" value="<?= val(g($p,'lokasi_kerja') ?? '') ?>"></div>
                    <div class="col-md-3"><label class="form-label">Bagian :</label>
                        <input type="text" name="position_code" class="form-control" value="<?= val(g($p,'position_code')) ?>"></div>
                    <div class="col-md-3"><label class="form-label">Mesin :</label>
                        <input type="text" name="work_location" class="form-control" value="<?= val(g($p,'work_location') ?? '') ?>"></div>
                    <div class="col-md-2"><label class="form-label">Level :</label>
                        <select name="level_code" class="form-select"><?= opts($LEVEL, g($p,'level_code')) ?></select></div>
                    <div class="col-md-2" style="display:none"><label class="form-label">Grade :</label>
                        <input type="text" name="grade_code" class="form-control" value="<?= val(g($p,'grade_code')) ?>"></div>
                    <div class="col-md-4"><label class="form-label">Status Kerja :</label>
                        <select name="employee_subgroup" class="form-select"><?= opts($SUBGROUP, g($p,'employee_subgroup')) ?></select></div>
                </div>
            </fieldset>

            <!-- PERSONAL -->
            <fieldset>
                <legend>Personal Data</legend>
                <div class="row g-3">
                    <div class="col-md-4"><label class="form-label">Birth Place :</label>
                        <input type="text" name="tempat_lahir" class="form-control" value="<?= val(g($p,'tempat_lahir')) ?>"></div>
                    <div class="col-md-3"><label class="form-label">Birth Date :</label>
                        <input type="date" name="tanggal_lahir" class="form-control" value="<?= dtv(g($p,'tgl_lahir')) ?>"></div>
                    <div class="col-md-3"><label class="form-label">Religion :</label>
                        <select name="agama" class="form-select"><?= opts($AGAMA, g($p,'agama')) ?></select></div>
                    <div class="col-md-2"><label class="form-label">Gender :</label>
                        <select name="gender" class="form-select">
                            <option value="">--</option>
                            <option value="M"<?= g($p,'gender')==='L'?' selected':'' ?>>M (Laki-laki)</option>
                            <option value="F"<?= g($p,'gender')==='P'?' selected':'' ?>>F (Perempuan)</option>
                        </select></div>
                    <div class="col-md-4"><label class="form-label">NPWP Number :</label>
                        <input type="text" name="npwp" class="form-control" value="<?= val(g($p,'npwp')) ?>"></div>
                    <div class="col-md-4"><label class="form-label">ID Number (KTP) :</label>
                        <input type="text" name="no_ktp" class="form-control" maxlength="16" value="<?= val(g($p,'no_ktp')) ?>"></div>
                    <div class="col-md-4"><label class="form-label">Marital Status :</label>
                        <select name="status_kawin" class="form-select"><?= opts($KAWIN, g($p,'status_kawin') ?? '') ?></select></div>
                    <div class="col-md-6"><label class="form-label">Email Address :</label>
                        <input type="email" name="email" class="form-control" value="<?= val(g($p,'email_peg')) ?>"></div>
                    <div class="col-md-6"><label class="form-label">PTKP Status :</label>
                        <select name="ptkp_status" class="form-select"><?= opts($PTKP, rev('pegawai.status_nikah', g($p,'status_nikah'))) ?></select></div>
                </div>

                <!-- Permanent Address -->
                <div class="sub-box mt-4"><span class="box-title">Permanent Address</span>
                    <div class="row g-3">
                        <div class="col-md-8"><label class="form-label">Address :</label>
                            <textarea name="almt_tetap" class="form-control" rows="2"><?= val(g($p,'alamat_ktp_peg')) ?></textarea></div>
                        <div class="col-md-2"><label class="form-label">RT :</label>
                            <input type="text" name="almt_tetap_rt" class="form-control" value="<?= val(g($p,'rt')) ?>"></div>
                        <div class="col-md-2"><label class="form-label">RW :</label>
                            <input type="text" name="almt_tetap_rw" class="form-control" value="<?= val(g($p,'rw')) ?>"></div>
                        <div class="col-md-3"><label class="form-label">Village :</label>
                            <input type="text" name="almt_tetap_desa" class="form-control" value="<?= val(g($p,'kelurahan')) ?>"></div>
                        <div class="col-md-3"><label class="form-label">Sub-District :</label>
                            <input type="text" name="almt_tetap_kecamatan" class="form-control" value="<?= val(g($p,'kecamatan')) ?>"></div>
                        <div class="col-md-3"><label class="form-label">City :</label>
                            <input type="text" name="almt_tetap_kota" class="form-control" value="<?= val(g($p,'kota')) ?>"></div>
                        <div class="col-md-3"><label class="form-label">Province :</label>
                            <input type="text" name="almt_tetap_provinsi" class="form-control" value="<?= val(g($p,'provinsi') ?: 'Jawa Timur') ?>"></div>
                        <div class="col-md-3"><label class="form-label">Zip Code :</label>
                            <input type="text" name="almt_tetap_kodepos" class="form-control" maxlength="5" value="<?= val(g($p,'kode_pos')) ?>"></div>
                        <div class="col-md-3"><label class="form-label">Mobile Phone :</label>
                            <input type="text" name="no_hp" class="form-control" value="<?= val(g($p,'no_hp_peg')) ?>"></div>
                    </div>
                </div>

                <!-- Temporary Address -->
                <div class="sub-box"><span class="box-title">Temporary Address</span>
                    <div class="row g-3">
                        <div class="col-md-8"><label class="form-label">Address :</label>
                            <textarea name="almt_smtr" class="form-control" rows="2"><?= val(g($p,'alamat_domi_peg') ?? '') ?></textarea></div>
                        <div class="col-md-2"><label class="form-label">RT :</label>
                            <input type="text" name="almt_smtr_rt" class="form-control" value="<?= val(g($p,'rt_dom') ?? '') ?>"></div>
                        <div class="col-md-2"><label class="form-label">RW :</label>
                            <input type="text" name="almt_smtr_rw" class="form-control" value="<?= val(g($p,'rw_dom') ?? '') ?>"></div>
                        <div class="col-md-3"><label class="form-label">Village :</label>
                            <input type="text" name="almt_smtr_desa" class="form-control" value="<?= val(g($p,'kelurahan_dom') ?? '') ?>"></div>
                        <div class="col-md-3"><label class="form-label">Sub-District :</label>
                            <input type="text" name="almt_smtr_kecamatan" class="form-control" value="<?= val(g($p,'kecamatan_dom') ?? '') ?>"></div>
                        <div class="col-md-3"><label class="form-label">City :</label>
                            <input type="text" name="almt_smtr_kota" class="form-control" value="<?= val(g($p,'kota_dom') ?? '') ?>"></div>
                        <div class="col-md-3"><label class="form-label">Province :</label>
                            <input type="text" name="almt_smtr_provinsi" class="form-control" value="<?= val(g($p,'provinsi_dom') ?? '') ?>"></div>
                        <div class="col-md-3"><label class="form-label">Zip Code :</label>
                            <input type="text" name="almt_smtr_kodepos" class="form-control" maxlength="5" value="<?= val(g($p,'kode_pos_dom') ?? '') ?>"></div>
                    </div>
                </div>

                <!-- Bank -->
                <div class="sub-box"><span class="box-title">Bank</span>
                    <div class="row g-3">
                        
                        
                        <div class="col-md-3"><label class="form-label">Nama Bank :</label>
                            <input type="text" class="form-control" value="PT. BANK CENTRAL ASIA" readonly>
                            <input type="hidden" name="bank_nama" value="PT. BANK CENTRAL ASIA"></div>
                        <div class="col-md-3"><label class="form-label">Bank Account :</label>
                            <input type="text" name="bank_rekening" class="form-control" required value="<?= val(g($p,'no_rekening')) ?>"></div>
                        <div class="col-md-6"><label class="form-label">Nama Nasabah :</label>
                            <input type="text" name="nama_nasabah" class="form-control text-uppercase" value="<?= val(g($p,'nama_nasabah')) ?>"></div>
                        <div class="col-12"><hr><strong>Kontak Darurat</strong></div>
                        <div class="col-md-3"><label class="form-label">Nama :</label>
                            <input type="text" name="darurat_nama" class="form-control text-uppercase" value="<?= val(g($p,'darurat_nama')) ?>"></div>
                        <div class="col-md-3"><label class="form-label">Hubungan :</label>
                            <select name="darurat_hubungan" class="form-select">
                                <?php $dh = g($p,'darurat_hubungan'); ?>
                                <option value="">-- pilih --</option>
                                <?php foreach(['SPOUSE','FATHER','MOTHER','CHILD','SIBLING','OTHER'] as $o): ?>
                                    <option <?= $dh===$o?'selected':'' ?>><?= $o ?></option>
                                <?php endforeach; ?>
                            </select></div>
                        <div class="col-md-3"><label class="form-label">No HP :</label>
                            <input type="text" name="darurat_hp" class="form-control" value="<?= val(g($p,'darurat_hp')) ?>"></div>
                        <div class="col-md-12"><label class="form-label">Alamat :</label>
                            <textarea name="darurat_alamat" class="form-control" rows="2"><?= val(g($p,'darurat_alamat')) ?></textarea></div>
                        
                    </div>
                </div>
            </fieldset>

            <!-- FAMILY -->
            <fieldset>
                <legend>Family Relation
                    <button type="button" class="btn btn-primary btn-sm" onclick="addRow('kelTable', kelRow)">Add</button></legend>
                <div class="table-responsive"><table class="table table-bordered dyn-table" id="kelTable">
                    <thead class="table-light"><tr><th>Name</th><th>Relation</th><th>Gender</th><th>Marital</th><th>Live Status</th><th>Birth Place</th><th>Birth Date</th><th>No KTP</th><th>No KK</th><th>BPJS</th><th></th></tr></thead>
                    <tbody></tbody></table></div>
            </fieldset>

            <!-- EDUCATION -->
            <fieldset>
                <legend>Education
                    <button type="button" class="btn btn-primary btn-sm" onclick="addRow('eduTable', eduRow)">Add</button></legend>
                <div class="table-responsive"><table class="table table-bordered dyn-table" id="eduTable">
                    <thead class="table-light"><tr><th>School</th><th>Degree</th><th>Major</th><th>Location</th><th>Start</th><th>End</th><th>GPA</th><th></th></tr></thead>
                    <tbody></tbody></table></div>
            </fieldset>

            <!-- EXPERIENCE -->
            <fieldset>
                <legend>Working Experience
                    <button type="button" class="btn btn-primary btn-sm" onclick="addRow('expTable', expRow)">Add</button></legend>
                <div class="table-responsive"><table class="table table-bordered dyn-table" id="expTable">
                    <thead class="table-light"><tr><th>Company</th><th>Position</th><th>Start</th><th>End</th><th>Keterangan</th><th></th></tr></thead>
                    <tbody></tbody></table></div>
            </fieldset>

            <div class="mb-5">
                <button type="submit" class="btn btn-danger px-4">Update Pegawai</button>
                <a href="detail.php?id=<?= $id ?>" class="btn btn-secondary px-4">Batal</a>
            </div>
        </form>
    </section>
</main>

<script>
function esc(s){ return (s||'').replace(/"/g,'&quot;'); }
function kelRow(d){ d=d||{};
    const sel=(opts,v)=>opts.map(o=>`<option${o===v?' selected':''}>${o}</option>`).join('');
    return `<td><input name="kel_nama[]" class="form-control form-control-sm text-uppercase" value="${esc(d.nama)}"></td>
        <td><select name="kel_hubungan[]" class="form-select form-select-sm">${sel(['SPOUSE','CHILD','FATHER','MOTHER','SIBLING'], d.hub)}</select></td>
        <td><select name="kel_gender[]" class="form-select form-select-sm">${sel(['MALE','FEMALE'], d.gender)}</select></td>
        <td><select name="kel_kawin[]" class="form-select form-select-sm">${sel(['SINGLE','MARRIED','DIVORCED',''], d.kawin)}</select></td>
        <td><select name="kel_hidup[]" class="form-select form-select-sm">${sel(['ALIVE','DECEASED'], d.hidup)}</select></td>
        <td><input name="kel_tmp_lahir[]" class="form-control form-control-sm" value="${esc(d.tmp)}"></td>
        <td><input type="date" name="kel_tgl_lahir[]" class="form-control form-control-sm" value="${esc(d.tgl)}"></td>
        <td><button type="button" class="btn btn-sm btn-outline-danger" onclick="this.closest('tr').remove()">&times;</button></td>`;
}
function eduRow(d){ d=d||{};
    const sel=(opts,v)=>opts.map(o=>`<option${o===v?' selected':''}>${o}</option>`).join('');
    return `<td><input name="edu_sekolah[]" class="form-control form-control-sm" value="${esc(d.sekolah)}"></td>
        <td><select name="edu_jenjang[]" class="form-select form-select-sm">${sel(['SD','SMP','SMA/SMK','D3','Strata 1','Strata 2'], d.jenjang)}</select></td>
        <td><input name="edu_jurusan[]" class="form-control form-control-sm" value="${esc(d.jurusan)}"></td>
        <td><input name="edu_lokasi[]" class="form-control form-control-sm" value="${esc(d.lokasi)}"></td>
        <td><input type="number" name="edu_mulai[]" class="form-control form-control-sm" value="${esc(d.mulai)}"></td>
        <td><input type="number" name="edu_selesai[]" class="form-control form-control-sm" value="${esc(d.selesai)}"></td>
        <td><input type="number" step="0.01" name="edu_ipk[]" class="form-control form-control-sm" value="${esc(d.ipk)}"></td>
        <td><button type="button" class="btn btn-sm btn-outline-danger" onclick="this.closest('tr').remove()">&times;</button></td>`;
}
function expRow(d){ d=d||{};
    return `<td><input name="exp_perusahaan[]" class="form-control form-control-sm" value="${esc(d.perusahaan)}"></td>
        <td><input name="exp_jabatan[]" class="form-control form-control-sm" value="${esc(d.jabatan)}"></td>
        <td><input type="date" name="exp_mulai[]" class="form-control form-control-sm" value="${esc(d.mulai)}"></td>
        <td><input type="date" name="exp_akhir[]" class="form-control form-control-sm" value="${esc(d.akhir)}"></td>
        <td><input name="exp_ket[]" class="form-control form-control-sm" value="${esc(d.ket)}"></td>
        <td><button type="button" class="btn btn-sm btn-outline-danger" onclick="this.closest('tr').remove()">&times;</button></td>`;
}
function addRow(tableId, rowFn, data){
    const tr=document.createElement('tr'); tr.innerHTML=rowFn(data);
    document.querySelector('#'+tableId+' tbody').appendChild(tr);
}

// isi baris dari data DB
const dataKel = <?= json_encode(array_map(fn($k)=>[
    'nama'=>$k['nama'],'hub'=>rev('keluarga.hubungan',$k['hubungan']),
    'gender'=>rev('keluarga.gender',$k['gender']),'kawin'=>rev('keluarga.status_nikah',$k['status_nikah']),
    'hidup'=>rev('keluarga.status_hidup',$k['status_hidup']),'tmp'=>$k['tempat_lahir'],
    'tgl'=>dtv($k['tgl_lahir']),'ktp'=>$k['no_ktp'],'kk'=>$k['no_kk'],'bpjs'=>$k['no_bpjs']
], $keluarga)) ?>;
const dataEdu = <?= json_encode(array_map(fn($e)=>[
    'sekolah'=>$e['nama_sekolah'],'jenjang'=>rev('pendidikan.jenjang',$e['jenjang']),
    'jurusan'=>$e['jurusan'],'lokasi'=>$e['lokasi'],'mulai'=>$e['tahun_mulai'],
    'selesai'=>$e['tahun_selesai'],'ipk'=>$e['ipk']
], $pendidikan)) ?>;
const dataExp = <?= json_encode(array_map(fn($x)=>[
    'perusahaan'=>$x['nama_perusahaan'],'jabatan'=>$x['jabatan'],
    'mulai'=>dtv($x['tgl_mulai']),'akhir'=>dtv($x['tgl_selesai']),'ket'=>$x['keterangan']
], $pengalaman)) ?>;

dataKel.length ? dataKel.forEach(d=>addRow('kelTable',kelRow,d)) : addRow('kelTable',kelRow);
dataEdu.length ? dataEdu.forEach(d=>addRow('eduTable',eduRow,d)) : addRow('eduTable',eduRow);
dataExp.forEach(d=>addRow('expTable',expRow,d));
</script>

<?php include '../template/footer.php'; ?>
