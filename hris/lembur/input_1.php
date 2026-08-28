<?php
require_once __DIR__ . '/../auth/auth.php';
wajib_izin('lembur_input');
$page_title = "Input Lembur Divisi";
include '../template/header.php';
include '../config/koneksi_sqlsrv.php';
include '../template/sidebar.php';

$pesan = '';
if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['aksi']??'')==='simpan') {
    $tgl   = $_POST['tanggal'] ?? '';
    $dept  = (int)($_POST['department_id'] ?? 0) ?: null;
    $jenis = $_POST['jenis'] ?? 'biasa';
    $ket   = trim($_POST['keterangan'] ?? '');
    $pembuat = trim($_POST['dibuat_nama'] ?? '');
    $pegs  = $_POST['peg'] ?? [];
    $mulai = $_POST['mulai'] ?? [];
    $selesai = $_POST['selesai'] ?? [];
    $uraian= $_POST['uraian'] ?? [];

    // minimal 1 karyawan valid
    $adaValid = false;
    foreach ($pegs as $i=>$pid) if ((int)$pid && ($mulai[$i]??'') && ($selesai[$i]??'')) { $adaValid=true; break; }

    if (!$tgl || !$adaValid) {
        $pesan = "<div class='alert alert-danger'>Tanggal dan minimal satu karyawan (dengan jam) wajib diisi.</div>";
    } else {
        sqlsrv_begin_transaction($conn);
        try {
            // nomor form: LMB-YYYYMM-urut
            $bulan = date('Ym', strtotime($tgl));
            $rs = sqlsrv_query($conn, "SELECT COUNT(*)+1 AS n FROM dbo.lembur_form WHERE FORMAT(tanggal,'yyyyMM')=?", [$bulan]);
            $urut = sqlsrv_fetch_array($rs, SQLSRV_FETCH_ASSOC)['n'] ?? 1;
            $noForm = sprintf('LMB-%s-%03d', $bulan, $urut);

            $rs = sqlsrv_query($conn,
                "INSERT INTO dbo.lembur_form (no_form,tanggal,department_id,jenis,keterangan,dibuat_nama,status)
                 VALUES (?,?,?,?,?,?,'DIAJUKAN'); SELECT SCOPE_IDENTITY() AS id;",
                [$noForm,$tgl,$dept,$jenis,$ket?:null,$pembuat?:null]);
            if ($rs===false) throw new Exception(print_r(sqlsrv_errors(),true));
            sqlsrv_next_result($rs); sqlsrv_fetch($rs);
            $idForm = (int)sqlsrv_get_field($rs,0);

            $sqlD = "INSERT INTO dbo.lembur_detail (id_form,pegawai_id,jam_mulai,jam_selesai,durasi_jam,uraian) VALUES (?,?,?,?,?,?)";
            $jml = 0;
            foreach ($pegs as $i=>$pid) {
                $pid=(int)$pid; if(!$pid || !($mulai[$i]??'') || !($selesai[$i]??'')) continue;
                $m=strtotime("2000-01-01 ".$mulai[$i]); $s=strtotime("2000-01-01 ".$selesai[$i]);
                if($s<=$m) $s+=86400;
                $dur=round(($s-$m)/3600,2);
                $st=sqlsrv_query($conn,$sqlD,[$idForm,$pid,$mulai[$i],$selesai[$i],$dur,($uraian[$i]??'')?:null]);
                if($st===false) throw new Exception(print_r(sqlsrv_errors(),true));
                $jml++;
            }
            sqlsrv_commit($conn);
            $pesan = "<div class='alert alert-success'>Form <strong>$noForm</strong> tersimpan: $jml karyawan. "
                   . "<a href='cetak.php?id=$idForm' target='_blank'>Lihat / Cetak</a></div>";
        } catch (Exception $e) {
            sqlsrv_rollback($conn);
            $pesan = "<div class='alert alert-danger'>Gagal: ".htmlspecialchars(substr($e->getMessage(),0,300))."</div>";
        }
    }
}

/* dropdown */
$deptList=[]; $rs=sqlsrv_query($conn,"SELECT id_dept,nama_dept FROM dbo.department ORDER BY nama_dept");
while($r=sqlsrv_fetch_array($rs,SQLSRV_FETCH_ASSOC)) $deptList[]=$r;

$pegList=[]; 
$rs=sqlsrv_query($conn,
    "SELECT p.id_peg, p.nik, p.nama_peg, u.department_id 
     FROM dbo.pegawai p 
     LEFT JOIN dbo.unit_kerja u ON u.id = p.unit_kerja_id 
     WHERE p.is_aktif=1 
     ORDER BY p.nama_peg");
while($r=sqlsrv_fetch_array($rs,SQLSRV_FETCH_ASSOC)) $pegList[]=$r;
function h($v){return htmlspecialchars((string)($v??''));}
?>

<main id="main" class="main">
  <div class="pagetitle"><h1>Input Lembur Divisi</h1>
    <nav><ol class="breadcrumb"><li class="breadcrumb-item"><a href="/hris/index.php">Home</a></li>
    <li class="breadcrumb-item active">Input Lembur</li></ol></nav></div>

  <section class="section">
    <?= $pesan ?>
    <div class="card"><div class="card-body">
      <h5 class="card-title">Formulir Lembur Kolektif</h5>
      <form method="POST" id="formLembur">
        <input type="hidden" name="aksi" value="simpan">

        <div class="row g-3 mb-4">
          <div class="col-md-3"><label class="form-label">Tanggal Lembur</label>
            <input type="date" name="tanggal" class="form-control" value="<?= date('Y-m-d') ?>" required></div>
          <div class="col-md-3"><label class="form-label">Divisi / Departemen</label>
            <select name="department_id" id="department_id" class="form-select">
              <option value="">-- pilih --</option>
              <?php foreach($deptList as $d): ?><option value="<?= $d['id_dept'] ?>"><?= h($d['nama_dept']) ?></option><?php endforeach; ?>
            </select></div>
          <div class="col-md-3"><label class="form-label">Jenis</label>
            <select name="jenis" class="form-select">
              <option value="biasa">Hari Kerja Biasa</option>
              <option value="libur">Hari Libur / Weekend</option>
              <option value="hari_besar">Hari Besar</option>
            </select></div>
          <div class="col-md-3"><label class="form-label">Dibuat Oleh (Atasan)</label>
            <input type="text" name="dibuat_nama" class="form-control" placeholder="Nama atasan"></div>
          <div class="col-12"><label class="form-label">Keterangan Umum</label>
            <input type="text" name="keterangan" class="form-control" placeholder="Uraian pekerjaan lembur secara umum"></div>
        </div>

        <h6 class="fw-bold">Daftar Karyawan Lembur
          <button type="button" class="btn btn-sm btn-primary" onclick="addRow()">+ Tambah Baris</button></h6>
        <div class="table-responsive">
        <table class="table table-bordered" id="tbl">
          <thead class="table-light"><tr>
            <th style="width:35%">Karyawan</th><th>Jam Mulai</th><th>Jam Selesai</th>
            <th>Durasi</th><th>Uraian Pekerjaan</th><th></th>
          </tr></thead>
          <tbody></tbody>
        </table>
        </div>

        <button class="btn btn-success mt-2"><i class="bi bi-save"></i> Simpan Form</button>
      </form>
    </div></div>
  </section>
</main>

<!-- Dependencies for Searchable Dropdown -->
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" />
<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<script>
const PEG = <?= json_encode(array_map(fn($p)=>['id'=>$p['id_peg'],'txt'=>$p['nik'].' — '.$p['nama_peg'], 'dept'=>$p['department_id']], $pegList)) ?>;
let selectedDept = '';

function optionPeg(){
  let filtered = PEG;
  if(selectedDept) {
    filtered = PEG.filter(p => String(p.dept) === String(selectedDept));
  }
  return '<option value="">-- pilih --</option>' + filtered.map(p=>`<option value="${p.id}">${p.txt}</option>`).join('');
}

function initSelect2() {
  $('.select2-peg').select2({
    theme: 'bootstrap-5',
    width: '100%',
    placeholder: '-- pilih --'
  });
}

function addRow(){
  const tr=document.createElement('tr');
  tr.innerHTML=`
    <td><select name="peg[]" class="form-select form-select-sm select2-peg">${optionPeg()}</select></td>
    <td><input type="time" name="mulai[]" class="form-control form-control-sm" onchange="hitung(this)"></td>
    <td><input type="time" name="selesai[]" class="form-control form-control-sm" onchange="hitung(this)"></td>
    <td><input type="text" class="form-control form-control-sm dur" readonly placeholder="0 jam"></td>
    <td><input type="text" name="uraian[]" class="form-control form-control-sm"></td>
    <td><button type="button" class="btn btn-sm btn-outline-danger" onclick="this.closest('tr').remove()">&times;</button></td>`;
  document.querySelector('#tbl tbody').appendChild(tr);
  
  // Initialize Select2 on the newly added select element
  $(tr).find('.select2-peg').select2({
    theme: 'bootstrap-5',
    width: '100%',
    placeholder: '-- pilih --'
  });
}

function hitung(el){
  const tr=el.closest('tr');
  const m=tr.querySelector('[name="mulai[]"]').value, s=tr.querySelector('[name="selesai[]"]').value;
  if(m&&s){
    let a=new Date('2000-01-01T'+m), b=new Date('2000-01-01T'+s);
    if(b<=a) b.setDate(b.getDate()+1);
    tr.querySelector('.dur').value=((b-a)/3600000).toFixed(1)+' jam';
  }
}

// When department changes, update options in existing dropdowns
$(document).ready(function() {
    $('#department_id').on('change', function() {
        selectedDept = $(this).val();
        let newOptions = optionPeg();
        $('.select2-peg').each(function() {
            let currentVal = $(this).val(); // Keep selected value if it still exists in new options
            $(this).empty().append(newOptions);
            if($(this).find(`option[value="${currentVal}"]`).length > 0) {
                $(this).val(currentVal);
            }
            $(this).trigger('change');
        });
    });

    addRow(); addRow(); addRow();  // start with 3 rows
});
</script>

<?php include '../template/footer.php'; ?>
