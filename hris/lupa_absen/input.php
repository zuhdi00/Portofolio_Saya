<?php
/**
 * lupa_absen/input.php
 * Form lupa absen kolektif/individu + upload bukti gambar.
 * Simpan ke lupa_absen_form + lupa_absen_detail. Status DIAJUKAN.
 * Bukti gambar disimpan ke \\spsdmz\gg$\HRD\BuktiLupaAbsensi
 */
require_once __DIR__ . '/../auth/auth.php';
wajib_izin('lupa_absen_input');
require_once __DIR__ . '/../config/koneksi_sqlsrv.php';
require_once __DIR__ . '/../config/_notif.php';

// ===== folder simpan bukti =====
$FOLDER_BUKTI = '\\\\spsdmz\\gg$\\HRD\\BuktiLupaAbsensi';
// alternatif lokal: 'C:\\xampp\\htdocs\\hris\\uploads\\lupa_absen';

$pesan='';
if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['aksi']??'')==='simpan') {
    $dept  = (int)($_POST['department_id'] ?? 0) ?: null;
    $ket   = trim($_POST['keterangan'] ?? '');
    $pembuat = trim($_POST['dibuat_nama'] ?? '');
    $atasan  = trim($_POST['atasan_nama'] ?? '');
    $pegs  = $_POST['peg'] ?? [];
    $tgls  = $_POST['tgl'] ?? [];
    $jenis = $_POST['jenis'] ?? [];
    $jmasuk= $_POST['jam_masuk'] ?? [];
    $jkeluar=$_POST['jam_keluar'] ?? [];
    $alasan= $_POST['alasan'] ?? [];

    // minimal 1 baris valid
    $adaValid=false;
    foreach($pegs as $i=>$pid) if((int)$pid && ($tgls[$i]??'')) {$adaValid=true;break;}

    if(!$adaValid){
        $pesan="<div class='alert alert-danger'>Minimal satu karyawan dengan tanggal wajib diisi.</div>";
    } else {
        sqlsrv_begin_transaction($conn);
        try {
            // upload bukti (opsional)
            $namaFile=null;
            if (isset($_FILES['bukti']) && $_FILES['bukti']['error']===UPLOAD_ERR_OK) {
                $ext=strtolower(pathinfo($_FILES['bukti']['name'],PATHINFO_EXTENSION));
                if(!in_array($ext,['jpg','jpeg','png','gif','pdf'],true)) throw new Exception("Bukti harus gambar (jpg/png/gif) atau PDF.");
                if($_FILES['bukti']['size']>10*1024*1024) throw new Exception("Ukuran bukti maksimal 10 MB.");
                if(!is_dir($FOLDER_BUKTI)) @mkdir($FOLDER_BUKTI,0777,true);
            }

            // nomor form
            $bulan=date('Ym');
            $rs=sqlsrv_query($conn,"SELECT COUNT(*)+1 n FROM dbo.lupa_absen_form WHERE FORMAT(dibuat_pada,'yyyyMM')=?",[$bulan]);
            $urut=sqlsrv_fetch_array($rs,SQLSRV_FETCH_ASSOC)['n']??1;
            $noForm=sprintf('LAB-%s-%03d',$bulan,$urut);

            // simpan file dengan nama noform
            if (isset($_FILES['bukti']) && $_FILES['bukti']['error']===UPLOAD_ERR_OK) {
                $ext=strtolower(pathinfo($_FILES['bukti']['name'],PATHINFO_EXTENSION));
                $namaFile=$noForm.'.'.$ext;
                $tujuan=rtrim($FOLDER_BUKTI,'\\/').DIRECTORY_SEPARATOR.$namaFile;
                if(!@move_uploaded_file($_FILES['bukti']['tmp_name'],$tujuan))
                    throw new Exception("Gagal simpan bukti ke folder. Cek akses Apache ke $FOLDER_BUKTI.");
            }

            $rs=sqlsrv_query($conn,
                "INSERT INTO dbo.lupa_absen_form (no_form,department_id,keterangan,file_bukti,dibuat_oleh,atasan_nama,status)
                 VALUES (?,?,?,?,?,?,'DIAJUKAN'); SELECT SCOPE_IDENTITY() AS id;",
                [$noForm,$dept,$ket?:null,$namaFile,$pembuat?:null,$atasan?:null]);
            if($rs===false) throw new Exception(print_r(sqlsrv_errors(),true));
            sqlsrv_next_result($rs); sqlsrv_fetch($rs);
            $idForm=(int)sqlsrv_get_field($rs,0);

            $sqlD="INSERT INTO dbo.lupa_absen_detail (id_form,pegawai_id,tanggal,jenis,jam_masuk,jam_keluar,alasan) VALUES (?,?,?,?,?,?,?)";
            $jml=0;
            foreach($pegs as $i=>$pid){
                $pid=(int)$pid; if(!$pid || !($tgls[$i]??'')) continue;
                $jn=$jenis[$i]??'MASUK';
                $jm=($jn==='MASUK'||$jn==='KEDUANYA')?($jmasuk[$i]?:null):null;
                $jk=($jn==='PULANG'||$jn==='KEDUANYA')?($jkeluar[$i]?:null):null;
                $st=sqlsrv_query($conn,$sqlD,[$idForm,$pid,$tgls[$i],$jn,$jm,$jk,($alasan[$i]??'')?:null]);
                if($st===false) throw new Exception(print_r(sqlsrv_errors(),true));
                $jml++;
            }
            sqlsrv_commit($conn);

            // NOTIFIKASI ke HRD & Admin IT
            notifKePeran($conn, ['hr','admin_it'],
                'Pengajuan Lupa Absen Baru',
                "Form $noForm ($jml karyawan) menunggu approval.",
                'lupa_absen/approval_hr.php');

            $pesan="<div class='alert alert-success'>Form <strong>$noForm</strong> tersimpan: $jml karyawan. "
                 . "<a href='cetak.php?id=$idForm' target='_blank'>Cetak</a>. Menunggu approval HRD.</div>";
        } catch(Exception $e){
            sqlsrv_rollback($conn);
            $pesan="<div class='alert alert-danger'>Gagal: ".htmlspecialchars(substr($e->getMessage(),0,300))."</div>";
        }
    }
}

/* data */
$deptList=[]; $rs=sqlsrv_query($conn,"SELECT id_dept,nama_dept FROM dbo.department ORDER BY nama_dept");
while($r=sqlsrv_fetch_array($rs,SQLSRV_FETCH_ASSOC)) $deptList[]=$r;
$pegList=[]; $rs=sqlsrv_query($conn,
  "SELECT p.id_peg,p.nik,p.nama_peg,u.department_id FROM dbo.pegawai p
   LEFT JOIN dbo.unit_kerja u ON u.id=p.unit_kerja_id WHERE p.is_aktif=1 ORDER BY p.nama_peg");
while($r=sqlsrv_fetch_array($rs,SQLSRV_FETCH_ASSOC)) $pegList[]=$r;

$formList=[]; $rs=sqlsrv_query($conn,
  "SELECT lf.id_form,lf.no_form,lf.status,lf.dibuat_pada,d.nama_dept,
          (SELECT COUNT(*) FROM dbo.lupa_absen_detail x WHERE x.id_form=lf.id_form) AS jml
   FROM dbo.lupa_absen_form lf LEFT JOIN dbo.department d ON d.id_dept=lf.department_id
   ORDER BY lf.dibuat_pada DESC");
while($r=sqlsrv_fetch_array($rs,SQLSRV_FETCH_ASSOC)) $formList[]=$r;

$page_title="Input Lupa Absen";
include '../template/header.php';
include '../template/sidebar.php';
function h($v){return htmlspecialchars((string)($v??''));}
function tgl($v){return $v instanceof DateTime?$v->format('d-m-Y'):'—';}
$badge=['DIAJUKAN'=>'warning','DISETUJUI'=>'success','DITOLAK'=>'danger'];
?>
<style>
  .pos-rel{position:relative}
  .saran-box{position:absolute;top:100%;left:0;right:0;z-index:50;background:#fff;border:1px solid #ccc;
    border-radius:4px;max-height:200px;overflow-y:auto;box-shadow:0 4px 10px rgba(0,0,0,.15);display:none}
  .saran-item{padding:6px 10px;cursor:pointer;font-size:.85rem;border-bottom:1px solid #f0f0f0}
  .saran-item:hover{background:#e7f1ff}
  #tbl td{vertical-align:top}
</style>

<main id="main" class="main">
  <div class="pagetitle"><h1>Input Lupa Absen</h1>
    <nav><ol class="breadcrumb"><li class="breadcrumb-item"><a href="../index.php">Home</a></li>
    <li class="breadcrumb-item active">Lupa Absen</li></ol></nav></div>

  <section class="section">
    <?= $pesan ?>
    <div class="card"><div class="card-body">
      <h5 class="card-title">Formulir Koreksi Lupa Absen</h5>
      <form method="POST" enctype="multipart/form-data">
        <input type="hidden" name="aksi" value="simpan">
        <div class="row g-3 mb-3">
          <div class="col-md-3"><label class="form-label">Divisi</label>
            <select name="department_id" class="form-select" onchange="filterDiv()">
              <option value="">-- pilih --</option>
              <?php foreach($deptList as $d): ?><option value="<?= $d['id_dept'] ?>"><?= h($d['nama_dept']) ?></option><?php endforeach; ?>
            </select></div>
          <div class="col-md-3"><label class="form-label">Dibuat Oleh</label>
            <input type="text" name="dibuat_nama" class="form-control" placeholder="Nama pengaju"></div>
          <div class="col-md-3"><label class="form-label">Atasan Divisi</label>
            <input type="text" name="atasan_nama" class="form-control" placeholder="Nama atasan"></div>
          <div class="col-md-3"><label class="form-label">Bukti (gambar/PDF)</label>
            <input type="file" name="bukti" accept="image/*,application/pdf" class="form-control"></div>
          <div class="col-12"><label class="form-label">Keterangan Umum</label>
            <input type="text" name="keterangan" class="form-control" placeholder="alasan umum lupa absen"></div>
        </div>

        <h6 class="fw-bold">Daftar Karyawan
          <button type="button" class="btn btn-sm btn-primary" onclick="addRow()">+ Tambah Baris</button></h6>
        <div class="table-responsive">
        <table class="table table-bordered" id="tbl">
          <thead class="table-light"><tr>
            <th style="width:24%">Karyawan</th><th>Tanggal</th><th>Jenis Lupa</th>
            <th>Jam Masuk</th><th>Jam Pulang</th><th>Alasan</th><th></th>
          </tr></thead>
          <tbody></tbody>
        </table>
        </div>
        <button class="btn btn-success mt-2"><i class="bi bi-save"></i> Simpan Form</button>
      </form>
    </div></div>

    <!-- daftar form -->
    <div class="card"><div class="card-body">
      <h5 class="card-title">Form Lupa Absen Terbaru</h5>
      <div class="table-responsive">
      <table class="table table-sm table-hover">
        <thead class="table-light"><tr><th>No Form</th><th>Divisi</th><th>Karyawan</th><th>Status</th><th></th></tr></thead>
        <tbody>
        <?php foreach($formList as $r): ?>
          <tr>
            <td><strong><?= h($r['no_form']) ?></strong></td>
            <td><small><?= h($r['nama_dept']??'—') ?></small></td>
            <td class="text-center"><?= $r['jml'] ?></td>
            <td><span class="badge bg-<?= $badge[$r['status']]??'secondary' ?>"><?= h($r['status']) ?></span></td>
            <td><a href="cetak.php?id=<?= $r['id_form'] ?>" target="_blank" class="btn btn-sm btn-outline-secondary"><i class="bi bi-printer"></i></a></td>
          </tr>
        <?php endforeach; if(!$formList): ?>
          <tr><td colspan="5" class="text-center text-muted py-3">Belum ada form.</td></tr>
        <?php endif; ?>
        </tbody>
      </table>
      </div>
    </div></div>
  </section>
</main>

<script>
const PEG = <?= json_encode(array_map(fn($p)=>['id'=>$p['id_peg'],'nik'=>$p['nik'],'nama'=>$p['nama_peg'],'dept'=>$p['department_id']], $pegList)) ?>;
function deptDipilih(){const v=document.querySelector('[name="department_id"]').value;return v?parseInt(v):null;}
function pegSaring(){const d=deptDipilih();return d?PEG.filter(p=>p.dept==d):PEG;}
function addRow(){
  const tr=document.createElement('tr');
  tr.innerHTML=`
    <td><div class="pos-rel">
      <input type="text" class="form-control form-control-sm cari" autocomplete="off" placeholder="ketik nama/NIK" oninput="cari(this)" onblur="setTimeout(()=>tutup(this),200)">
      <input type="hidden" name="peg[]" value="">
      <div class="saran-box"></div></div></td>
    <td><input type="date" name="tgl[]" class="form-control form-control-sm"></td>
    <td><select name="jenis[]" class="form-select form-select-sm" onchange="ubahJenis(this)">
        <option value="MASUK">Lupa Masuk</option>
        <option value="PULANG">Lupa Pulang</option>
        <option value="KEDUANYA">Lupa Keduanya</option></select></td>
    <td><input type="time" name="jam_masuk[]" class="form-control form-control-sm"></td>
    <td><input type="time" name="jam_keluar[]" class="form-control form-control-sm" disabled></td>
    <td><input type="text" name="alasan[]" class="form-control form-control-sm"></td>
    <td><button type="button" class="btn btn-sm btn-outline-danger" onclick="this.closest('tr').remove()">&times;</button></td>`;
  document.querySelector('#tbl tbody').appendChild(tr);
}
function ubahJenis(sel){
  const tr=sel.closest('tr');
  const m=tr.querySelector('[name="jam_masuk[]"]'), k=tr.querySelector('[name="jam_keluar[]"]');
  const v=sel.value;
  m.disabled=(v==='PULANG'); if(m.disabled)m.value='';
  k.disabled=(v==='MASUK');  if(k.disabled)k.value='';
}
function cari(input){
  const q=input.value.trim().toLowerCase(), box=input.parentNode.querySelector('.saran-box');
  if(q.length<1){box.style.display='none';return;}
  const hasil=pegSaring().filter(p=>p.nama.toLowerCase().includes(q)||(p.nik||'').toLowerCase().includes(q)).slice(0,8);
  if(!hasil.length){box.innerHTML='<div class="saran-item text-muted">tidak ada</div>';box.style.display='block';return;}
  box.innerHTML=hasil.map(p=>`<div class="saran-item" onmousedown="pilih(this,${p.id},'${(p.nik+' — '+p.nama).replace(/'/g,"\\'")}')"><strong>${p.nama}</strong> <span class="text-muted">${p.nik}</span></div>`).join('');
  box.style.display='block';
}
function pilih(el,id,txt){const c=el.closest('td');c.querySelector('.cari').value=txt;c.querySelector('[name="peg[]"]').value=id;c.querySelector('.saran-box').style.display='none';}
function tutup(input){const b=input.parentNode.querySelector('.saran-box');if(b)b.style.display='none';}
function filterDiv(){document.querySelectorAll('#tbl .cari').forEach(i=>i.value='');document.querySelectorAll('#tbl [name="peg[]"]').forEach(i=>i.value='');}
addRow(); addRow();
</script>

<?php include '../template/footer.php'; ?>
