<?php
$page_title = "Tambah Pegawai Lengkap";
include '../template/header.php';
include '../template/sidebar.php';
?>

<main id="main" class="main">
    <div class="pagetitle">
        <h1 style="color:#c00;">Input Data Pegawai</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?php echo BASE_URL; ?>/hris/index.php">Home</a></li>
                <li class="breadcrumb-item"><a href="index.php">Pegawai</a></li>
                <li class="breadcrumb-item active">Tambah Lengkap</li>
            </ol>
        </nav>
    </div>

    <style>
        fieldset { border: 1px solid #ccc; border-radius: 6px; padding: 18px; margin-bottom: 22px; background: #fff; }
        legend { font-size: 1.25rem; font-weight: 700; color: #c00; width: auto; padding: 0 8px; }
        .form-label { font-weight: 600; color: #b00; font-size: .85rem; }
        .sub-box { border: 1px solid #ddd; border-radius: 6px; padding: 14px; margin-bottom: 14px; }
        .sub-box .box-title { color: #b00; font-weight: 700; font-size: .95rem; margin-top: -24px; background:#fff; display:inline-block; padding:0 6px; }
        .btn-add-row { font-size: .8rem; }
        table.dyn-table input, table.dyn-table select { font-size: .82rem; }
    </style>

    <section class="section">
        <form action="submit_pegawai_lengkap.php" method="POST" id="formPegawai">

            <!-- ============ HEADER ============ -->
            <fieldset>
                <div class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label">Company Name :</label>
                        <input type="text" name="company_name" class="form-control" value="GRP1" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">NIK :</label>
                        <input type="text" name="nik" class="form-control" placeholder="00101589" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Nama Pegawai :</label>
                        <input type="text" name="nama" class="form-control text-uppercase" required>
                    </div>
                </div>
            </fieldset>

            <!-- ============ ORGANIZATIONAL ASSIGNMENT ============ -->
            <fieldset>
                <legend>Organizational Assignment</legend>
                <div class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label">Enterprise Begin :</label>
                        <input type="date" name="enterprise_begin" class="form-control">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Enterprise End :</label>
                        <input type="date" name="enterprise_end" class="form-control">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Termination Effective :</label>
                        <input type="date" name="termination_effective" class="form-control">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Contract (Month) :</label>
                        <input type="number" name="contract_month" class="form-control" min="0">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Term Reason :</label>
                        <input type="text" name="term_reason" class="form-control" placeholder="cth: MENGUNDURKAN DIRI (KELUARGA)">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Personnel Area :</label>
                        <input type="text" name="personnel_area" class="form-control" value="PT Supracor Sejahtera">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Personnel Sub Area :</label>
                        <input type="text" name="personnel_subarea" class="form-control" placeholder="Factory / Office">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Job Title :</label>
                        <input type="text" name="job_title" class="form-control" placeholder="ADMINISTRATION">
                    </div>
                    <div class="col-md-8">
                        <label class="form-label">Unit :</label>
                        <input type="text" name="unit_kerja" class="form-control" placeholder="cth: HR & GA-MANUFACTURING-FACTORY">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Position :</label>
                        <input type="text" name="position_code" class="form-control" placeholder="POS12">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Work Location :</label>
                        <input type="text" name="work_location" class="form-control">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Level :</label>
                        <select name="level_code" class="form-select">
                            <option value="">-- pilih --</option>
                            <option>1a</option>
                            <option>1b</option>
                            <option>2a</option>
                            <option>2b</option>
                            <option>3a</option>
                            <option>3b</option>
                            <option>4a</option>
                            <option>4b</option>
                            <option>5a</option>
                            <option>5b</option>
                            <option>6a</option>
                            <option>6b</option>
                            <option>6c</option>
                            <option>7</option>
                        </select>
                    </div>
                    <div class="col-md-2" style="display:none"><!-- Grade disembunyikan -->
                        <label class="form-label">Grade :</label>
                        <input type="text" name="grade_code" class="form-control" placeholder="2G">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Employee Subgroup :</label>
                        <select name="employee_subgroup" class="form-select">
                            <option value="">-- pilih --</option>
                            <option>Kary. Tetap</option>
                            <option>Kary. Harian Kontrak (kls.1)</option>
                            <option>Kary. Harian Kontrak (kls.2)</option>
                            <option>Kary. Borongan</option>
                            <option>Staff</option>
                        </select>
                    </div>
                </div>
            </fieldset>

            <!-- ============ PERSONAL DATA ============ -->
            <fieldset>
                <legend>Personal Data</legend>
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Birth Place :</label>
                        <input type="text" name="tempat_lahir" class="form-control">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Birth Date :</label>
                        <input type="date" name="tanggal_lahir" class="form-control">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Religion :</label>
                        <select name="agama" class="form-select">
                            <option value="">-- pilih --</option>
                            <option>Islam</option><option>Kristen</option><option>Katolik</option>
                            <option>Hindu</option><option>Buddha</option><option>Konghucu</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Gender :</label>
                        <select name="gender" class="form-select">
                            <option value="">--</option>
                            <option value="M">M (Laki-laki)</option>
                            <option value="F">F (Perempuan)</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">NPWP Number :</label>
                        <input type="text" name="npwp" class="form-control" placeholder="60.301.222.0-625.000">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">ID Number (KTP) :</label>
                        <input type="text" name="no_ktp" class="form-control" maxlength="16">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Marital Status :</label>
                        <select name="status_kawin" class="form-select">
                            <option value="">-- pilih --</option>
                            <option>SINGLE</option><option>MARRIED</option>
                            <option>DIVORCED</option><option>WIDOWED</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Email Address :</label>
                        <input type="email" name="email" class="form-control">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">PTKP Status :</label>
                        <select name="ptkp_status" class="form-select">
                            <option value="">-- pilih --</option>
                            <option>Single, 0 Anak (TK/0) - Rp 54,000,000</option>
                            <option>Kawin, 0 Anak (K/0) - Rp 58,500,000</option>
                            <option>Kawin, 1 Anak (K/1) - Rp 63,000,000</option>
                            <option>Kawin, 2 Anak (K/2) - Rp 67,500,000</option>
                            <option>Kawin, 3 Anak (K/3) - Rp 72,000,000</option>
                        </select>
                    </div>
                </div>

                <!-- Permanent Address -->
                <div class="sub-box mt-4">
                    <span class="box-title">Permanent Address</span>
                    <div class="row g-3">
                        <div class="col-md-8">
                            <label class="form-label">Address :</label>
                            <textarea name="almt_tetap" class="form-control" rows="2"></textarea>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">RT :</label>
                            <input type="text" name="almt_tetap_rt" class="form-control" maxlength="3">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">RW :</label>
                            <input type="text" name="almt_tetap_rw" class="form-control" maxlength="3">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Village (Desa/Kel.) :</label>
                            <input type="text" name="almt_tetap_desa" class="form-control">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Sub-District (Kec.) :</label>
                            <input type="text" name="almt_tetap_kecamatan" class="form-control">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">City :</label>
                            <input type="text" name="almt_tetap_kota" class="form-control">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Province :</label>
                            <input type="text" name="almt_tetap_provinsi" class="form-control" value="Jawa Timur">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Country :</label>
                            <input type="text" name="almt_tetap_negara" class="form-control" value="Indonesia">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Zip Code :</label>
                            <input type="text" name="almt_tetap_kodepos" class="form-control" maxlength="5">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Mobile Phone :</label>
                            <input type="text" name="no_hp" class="form-control">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Telephone :</label>
                            <input type="text" name="no_telp" class="form-control">
                        </div>
                    </div>
                </div>

                <!-- Temporary Address -->
                <div class="sub-box">
                    <span class="box-title">Temporary Address</span>
                    <div class="form-check mb-2">
                        <input class="form-check-input" type="checkbox" id="samaAlamat">
                        <label class="form-check-label" for="samaAlamat" style="font-size:.85rem">Sama dengan alamat tetap</label>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-8">
                            <label class="form-label">Address :</label>
                            <textarea name="almt_smtr" class="form-control" rows="2"></textarea>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">RT :</label>
                            <input type="text" name="almt_smtr_rt" class="form-control" maxlength="3">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">RW :</label>
                            <input type="text" name="almt_smtr_rw" class="form-control" maxlength="3">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Village :</label>
                            <input type="text" name="almt_smtr_desa" class="form-control">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Sub-District :</label>
                            <input type="text" name="almt_smtr_kecamatan" class="form-control">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">City :</label>
                            <input type="text" name="almt_smtr_kota" class="form-control">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Province :</label>
                            <input type="text" name="almt_smtr_provinsi" class="form-control">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Country :</label>
                            <input type="text" name="almt_smtr_negara" class="form-control">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Zip Code :</label>
                            <input type="text" name="almt_smtr_kodepos" class="form-control" maxlength="5">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Telephone :</label>
                            <input type="text" name="almt_smtr_telp" class="form-control">
                        </div>
                    </div>
                </div>

                <!-- Bank -->
                <div class="sub-box">
                    <span class="box-title">Bank</span>
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Payee :</label>
                            <input type="text" name="bank_payee" class="form-control text-uppercase">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Kode Bank :</label>
                            <input type="text" name="bank_kode" class="form-control" placeholder="014">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Nama Bank :</label>
                            <input type="text" name="bank_nama" class="form-control" placeholder="PT. BANK CENTRAL ASIA">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Bank Account :</label>
                            <input type="text" name="bank_rekening" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Bank Detail :</label>
                            <input type="text" name="bank_detail" class="form-control" placeholder="Cabang / keterangan">
                        </div>
                    </div>
                </div>
            </fieldset>

            <!-- ============ FAMILY RELATION ============ -->
            <fieldset>
                <legend>Family Relation
                    <button type="button" class="btn btn-primary btn-sm btn-add-row" onclick="addRow('kelTable', kelRow)">Add</button>
                </legend>
                <div class="table-responsive">
                    <table class="table table-bordered dyn-table" id="kelTable">
                        <thead class="table-light">
                            <tr>
                                <th>Name</th><th>Relation</th><th>Gender</th><th>Marital</th><th>Live Status</th>
                                <th>Birth Place</th><th>Birth Date</th><th>No KTP</th><th>No KK</th><th>BPJS No</th><th></th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </fieldset>

            <!-- ============ EDUCATION ============ -->
            <fieldset>
                <legend>Education
                    <button type="button" class="btn btn-primary btn-sm btn-add-row" onclick="addRow('eduTable', eduRow)">Add</button>
                </legend>
                <div class="table-responsive">
                    <table class="table table-bordered dyn-table" id="eduTable">
                        <thead class="table-light">
                            <tr><th>School</th><th>Degree</th><th>Major</th><th>Location</th><th>Start</th><th>End</th><th>GPA</th><th></th></tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </fieldset>

            <!-- ============ WORKING EXPERIENCE ============ -->
            <fieldset>
                <legend>Working Experience
                    <button type="button" class="btn btn-primary btn-sm btn-add-row" onclick="addRow('expTable', expRow)">Add</button>
                </legend>
                <div class="table-responsive">
                    <table class="table table-bordered dyn-table" id="expTable">
                        <thead class="table-light">
                            <tr><th>Company</th><th>Position</th><th>Start Date</th><th>End Date</th><th>Keterangan</th><th></th></tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </fieldset>

            <div class="mb-5">
                <button type="submit" class="btn btn-danger px-4">Simpan Pegawai</button>
                <a href="index.php" class="btn btn-secondary px-4">Batal</a>
            </div>
        </form>
    </section>
</main>

<script>
function kelRow() {
    return `<td><input name="kel_nama[]" class="form-control form-control-sm text-uppercase"></td>
        <td><select name="kel_hubungan[]" class="form-select form-select-sm">
            <option>SPOUSE</option><option>CHILD</option><option>FATHER</option><option>MOTHER</option><option>SIBLING</option></select></td>
        <td><select name="kel_gender[]" class="form-select form-select-sm"><option>MALE</option><option>FEMALE</option></select></td>
        <td><select name="kel_kawin[]" class="form-select form-select-sm"><option>SINGLE</option><option>MARRIED</option><option>DIVORCED</option><option>WIDOWED</option></select></td>
        <td><select name="kel_hidup[]" class="form-select form-select-sm"><option>ALIVE</option><option>DECEASED</option></select></td>
        <td><input name="kel_tmp_lahir[]" class="form-control form-control-sm"></td>
        <td><input type="date" name="kel_tgl_lahir[]" class="form-control form-control-sm"></td>
        <td><input name="kel_ktp[]" class="form-control form-control-sm" maxlength="16"></td>
        <td><input name="kel_kk[]" class="form-control form-control-sm" maxlength="16"></td>
        <td><input name="kel_bpjs[]" class="form-control form-control-sm"></td>
        <td><button type="button" class="btn btn-sm btn-outline-danger" onclick="this.closest('tr').remove()">&times;</button></td>`;
}
function eduRow() {
    return `<td><input name="edu_sekolah[]" class="form-control form-control-sm"></td>
        <td><select name="edu_jenjang[]" class="form-select form-select-sm">
            <option>SD</option><option>SMP</option><option>SMA/SMK</option><option>D3</option>
            <option>Strata 1</option><option>Strata 2</option></select></td>
        <td><input name="edu_jurusan[]" class="form-control form-control-sm"></td>
        <td><input name="edu_lokasi[]" class="form-control form-control-sm"></td>
        <td><input type="number" name="edu_mulai[]" class="form-control form-control-sm" placeholder="2017" min="1950" max="2100"></td>
        <td><input type="number" name="edu_selesai[]" class="form-control form-control-sm" placeholder="2022" min="1950" max="2100"></td>
        <td><input type="number" step="0.01" name="edu_ipk[]" class="form-control form-control-sm" placeholder="3.50" min="0" max="4"></td>
        <td><button type="button" class="btn btn-sm btn-outline-danger" onclick="this.closest('tr').remove()">&times;</button></td>`;
}
function expRow() {
    return `<td><input name="exp_perusahaan[]" class="form-control form-control-sm"></td>
        <td><input name="exp_jabatan[]" class="form-control form-control-sm"></td>
        <td><input type="date" name="exp_mulai[]" class="form-control form-control-sm"></td>
        <td><input type="date" name="exp_akhir[]" class="form-control form-control-sm"></td>
        <td><input name="exp_ket[]" class="form-control form-control-sm"></td>
        <td><button type="button" class="btn btn-sm btn-outline-danger" onclick="this.closest('tr').remove()">&times;</button></td>`;
}
function addRow(tableId, rowFn) {
    const tr = document.createElement('tr');
    tr.innerHTML = rowFn();
    document.querySelector('#' + tableId + ' tbody').appendChild(tr);
}
// baris awal
addRow('kelTable', kelRow);
addRow('eduTable', eduRow);

// checkbox "sama dengan alamat tetap"
document.getElementById('samaAlamat').addEventListener('change', function () {
    const map = {
        almt_smtr: 'almt_tetap', almt_smtr_rt: 'almt_tetap_rt', almt_smtr_rw: 'almt_tetap_rw',
        almt_smtr_desa: 'almt_tetap_desa', almt_smtr_kecamatan: 'almt_tetap_kecamatan',
        almt_smtr_kota: 'almt_tetap_kota', almt_smtr_provinsi: 'almt_tetap_provinsi',
        almt_smtr_negara: 'almt_tetap_negara', almt_smtr_kodepos: 'almt_tetap_kodepos',
        almt_smtr_telp: 'no_telp'
    };
    for (const [dst, src] of Object.entries(map)) {
        const d = document.getElementsByName(dst)[0], s = document.getElementsByName(src)[0];
        d.value = this.checked ? s.value : '';
    }
});
</script>

<?php include '../template/footer.php'; ?>
