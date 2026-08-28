<?php
/**
 * lembur/input.php
 * Atasan/admin divisi input lembur KOLEKTIF: 1 divisi, 1 tanggal, banyak karyawan.
 * Simpan ke lembur_form (header) + lembur_detail (baris karyawan).
 */
$page_title = "Input Lembur Divisi";
require_once __DIR__ . '/../auth/auth.php';
wajib_izin('lembur_input');
include '../config/koneksi_sqlsrv.php';
include '../template/header.php';
include '../template/sidebar.php';

$pesan = '';
$userSaya = user_login() ?? [];
$peranSaya = $userSaya['peran'] ?? '';
$deptSaya = (int)($userSaya['department_id'] ?? 0);
$batasiDivisi = in_array($peranSaya, ['atasan', 'admin_divisi'], true);
if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['aksi']??'')==='simpan') {
    $tgl   = $_POST['tanggal'] ?? '';
    $dept  = (int)($_POST['department_id'] ?? 0) ?: null;
    $jenis = $_POST['jenis'] ?? 'biasa';
    $ket   = trim($_POST['keterangan'] ?? '');
    $pembuat = trim($_POST['dibuat_nama'] ?? '');
    $atasan  = trim($_POST['atasan_nama'] ?? '');
    $pegs  = $_POST['peg'] ?? [];
    $mulai = $_POST['mulai'] ?? [];
    $selesai = $_POST['selesai'] ?? [];
    $istirahat = $_POST['istirahat'] ?? [];
    $uraian= $_POST['uraian'] ?? [];

    // minimal 1 karyawan valid
    $adaValid = false;
    foreach ($pegs as $i=>$pid) {
        if ((int)$pid && !empty($mulai[$i]) && !empty($selesai[$i])) {
            $adaValid=true; 
            break; 
        }
    }

    if ($batasiDivisi && (!$deptSaya || $dept !== $deptSaya)) {
      $pesan = "<div class='alert alert-danger'>Anda hanya boleh membuat form lembur untuk divisi sendiri.</div>";
    } elseif (!$tgl || !$adaValid) {
        $pesan = "<div class='alert alert-danger'>Tanggal dan minimal satu karyawan (dengan jam) wajib diisi.</div>";
    } else {
      $validPegawai = sqlsrv_query($conn, "SELECT COUNT(*) AS jumlah
        FROM dbo.pegawai p
        LEFT JOIN dbo.unit_kerja u ON u.id = p.unit_kerja_id
        WHERE p.is_aktif=1 AND p.id_peg IN (" . implode(',', array_fill(0, count($pegs), '?')) . ")
          AND u.department_id = ?", array_merge(array_map('intval', $pegs), [$dept]));
      $validRow = $validPegawai ? sqlsrv_fetch_array($validPegawai, SQLSRV_FETCH_ASSOC) : null;
      $jumlahPegawai = count(array_filter($pegs, fn($pid) => (int)$pid > 0));
      if (!$validRow || (int)$validRow['jumlah'] !== $jumlahPegawai) {
        $pesan = "<div class='alert alert-danger'>Ada pegawai yang bukan bagian dari divisi yang dipilih.</div>";
      } else {
        sqlsrv_begin_transaction($conn);
        try {
            // nomor form: LMB-YYYYMM-urut
            $bulan = date('Ym', strtotime($tgl));
            $rs = sqlsrv_query($conn, "SELECT COUNT(*)+1 AS n FROM dbo.lembur_form WHERE FORMAT(tanggal,'yyyyMM')=?", [$bulan]);
            $urut = sqlsrv_fetch_array($rs, SQLSRV_FETCH_ASSOC)['n'] ?? 1;
            $noForm = sprintf('LMB-%s-%03d', $bulan, $urut);

            $rs = sqlsrv_query($conn,
                "INSERT INTO dbo.lembur_form (no_form,tanggal,department_id,jenis,keterangan,dibuat_nama,atasan_nama,status)
                 VALUES (?,?,?,?,?,?,?,'DIAJUKAN'); SELECT SCOPE_IDENTITY() AS id;",
                [$noForm,$tgl,$dept,$jenis,$ket?:null,$pembuat?:null,$atasan?:null]);
            if ($rs===false) throw new Exception(print_r(sqlsrv_errors(),true));
            sqlsrv_next_result($rs); sqlsrv_fetch($rs);
            $idForm = (int)sqlsrv_get_field($rs,0);

            $sqlD = "INSERT INTO dbo.lembur_detail (id_form,pegawai_id,jam_mulai,jam_selesai,durasi_jam,istirahat_jam,uraian) VALUES (?,?,?,?,?,?,?)";
            $jml = 0;
            foreach ($pegs as $i=>$pid) {
                $pid=(int)$pid; 
                if(!$pid || empty($mulai[$i]) || empty($selesai[$i])) continue;

                $m = strtotime("2000-01-01 " . $mulai[$i]);
                $s = strtotime("2000-01-01 " . $selesai[$i]);
                if ($s <= $m) $s += 86400;
                $durasiKotor = ($s - $m) / 3600;
                $istirahatJam = max(0, (float)($istirahat[$i] ?? 0));
                $durasiBersih = round($durasiKotor - $istirahatJam, 2);
                if ($durasiBersih <= 0 || $istirahatJam > $durasiKotor) {
                  throw new Exception('Durasi istirahat tidak boleh melebihi durasi lembur.');
                }

                $st=sqlsrv_query($conn,$sqlD,[$idForm,$pid,$mulai[$i],$selesai[$i],$durasiBersih,$istirahatJam,($uraian[$i]??'')?:null]);
                if($st===false) throw new Exception(print_r(sqlsrv_errors(),true));
                $jml++;
            }
            sqlsrv_commit($conn);
            $pesan = "<div class='alert alert-success'>Form <strong>$noForm</strong> tersimpan: $jml karyawan. "
                   . "<a href='cetak.php?id=$idForm' target='_blank'>Lihat / Cetak</a></div>";

            // Kirim notifikasi ke HR atau Atasan
            require_once __DIR__ . '/../config/notifikasi_helper.php';
            kirim_notifikasi_role($conn, 'hr', "Pengajuan Lembur Baru", "Form Lembur $noForm telah diajukan.", "lembur/approval_hr.php");
            kirim_notifikasi_role($conn, 'atasan', "Pengajuan Lembur Baru", "Form Lembur $noForm telah diajukan.", "lembur/approval_hr.php");
            
        } catch (Exception $e) {
            sqlsrv_rollback($conn);
            $pesan = "<div class='alert alert-danger'>Gagal: ".htmlspecialchars(substr($e->getMessage(),0,300))."</div>";
        }
        }
    }
}

/* dropdown */
$deptList=[]; $rs=sqlsrv_query($conn,"SELECT id_dept,nama_dept FROM dbo.department ORDER BY nama_dept");
while($r=sqlsrv_fetch_array($rs,SQLSRV_FETCH_ASSOC)) {
  if (!$batasiDivisi || (int)$r['id_dept'] === $deptSaya) $deptList[]=$r;
}
$pegList=[]; $rs=sqlsrv_query($conn,
  "SELECT p.id_peg, p.nik, p.nama_peg, u.department_id
   FROM dbo.pegawai p
   LEFT JOIN dbo.unit_kerja u ON u.id = p.unit_kerja_id
   WHERE p.is_aktif=1 " . ($batasiDivisi ? "AND u.department_id = " . $deptSaya : '') . " ORDER BY p.nama_peg");
while($r=sqlsrv_fetch_array($rs,SQLSRV_FETCH_ASSOC)) $pegList[]=$r;
// notifikasi hasil hapus
$notif='';
if (($_GET['msg']??'')==='terhapus')   $notif="<div class='alert alert-success'>Form lembur dihapus.</div>";
if (($_GET['msg']??'')==='gagal')      $notif="<div class='alert alert-danger'>Gagal menghapus.</div>";
if (($_GET['msg']??'')==='notallowed') $notif="<div class='alert alert-warning'>Form sudah di-ACC. Hanya HR/Admin IT yang bisa menghapus.</div>";

// daftar form terbaru (untuk bisa dihapus)
$formTerbaru = sqlsrv_query($conn,
  "SELECT lf.id_form, lf.no_form, lf.tanggal, lf.status, d.nama_dept,
          (SELECT COUNT(*) FROM dbo.lembur_detail x WHERE x.id_form=lf.id_form) AS jml_org
   FROM dbo.lembur_form lf
   LEFT JOIN dbo.department d ON d.id_dept=lf.department_id
  " . ($batasiDivisi ? "WHERE lf.department_id = " . $deptSaya : '') . "
   ORDER BY lf.dibuat_pada DESC");
$formList=[]; while($r=sqlsrv_fetch_array($formTerbaru,SQLSRV_FETCH_ASSOC)) $formList[]=$r;

function h($v){return htmlspecialchars((string)($v??''));}
function tglR($v){return $v instanceof DateTime?$v->format('d-m-Y'):'—';}
$peranSaya = function_exists('peran_saya') ? peran_saya() : '';
?>

<style>
  .pos-rel{position:relative}
  .saran-box{position:absolute;top:100%;left:0;right:0;z-index:50;background:#fff;
             border:1px solid #ccc;border-radius:4px;max-height:220px;overflow-y:auto;
             box-shadow:0 4px 10px rgba(0,0,0,.15);display:none}
  .saran-item{padding:6px 10px;cursor:pointer;font-size:.85rem;border-bottom:1px solid #f0f0f0}
  .saran-item:hover{background:#e7f1ff}
  #tbl td{vertical-align:top}
</style>
<main id="main" class="main">
  <div class="pagetitle"><h1>Input Lembur Divisi</h1>
    <nav><ol class="breadcrumb"><li class="breadcrumb-item"><a href="/hris/index.php">Home</a></li>
    <li class="breadcrumb-item active">Input Lembur</li></ol></nav></div>

  <section class="section">
    <?= $pesan ?><?= $notif ?>
    <div class="card"><div class="card-body">
      <h5 class="card-title">Formulir Lembur Kolektif</h5>
      <form method="POST" id="formLembur">
        <input type="hidden" name="aksi" value="simpan">

        <div class="row g-3 mb-4">
          <div class="col-md-3"><label class="form-label">Tanggal Lembur</label>
            <input type="date" name="tanggal" class="form-control" value="<?= date('Y-m-d') ?>" required></div>
          <div class="col-md-3"><label class="form-label">Divisi / Departemen</label>
            <select name="department_id" class="form-select" onchange="gantiDivisi()" <?= $batasiDivisi ? 'disabled' : '' ?> required>
              <option value="">-- pilih --</option>
              <?php foreach($deptList as $d): ?><option value="<?= $d['id_dept'] ?>" <?= $batasiDivisi ? 'selected' : '' ?>><?= h($d['nama_dept']) ?></option><?php endforeach; ?>
            </select></div>
            <?php if ($batasiDivisi): ?><input type="hidden" name="department_id" value="<?= $deptSaya ?>"><?php endif; ?>
          <div class="col-md-3"><label class="form-label">Jenis</label>
            <select name="jenis" class="form-select">
              <option value="biasa">Hari Kerja Biasa</option>
              <option value="libur">Hari Libur / Weekend</option>
              <option value="hari_besar">Hari Besar</option>
            </select></div>
          <div class="col-md-3"><label class="form-label">Dibuat Oleh (Admin/Pengaju)</label>
            <input type="text" name="dibuat_nama" class="form-control" placeholder="Nama pengaju"></div>
          <div class="col-md-3"><label class="form-label">Atasan Divisi</label>
            <input type="text" name="atasan_nama" class="form-control" placeholder="Nama atasan divisi"></div>
          <div class="col-12"><label class="form-label">Keterangan Umum</label>
            <input type="text" name="keterangan" class="form-control" placeholder="Uraian pekerjaan lembur secara umum"></div>
        </div>

        <h6 class="fw-bold">Daftar Karyawan Lembur
          <button type="button" class="btn btn-sm btn-primary" onclick="addRow()">+ Tambah Baris</button></h6>
        <div class="table-responsive">
        <table class="table table-bordered" id="tbl">
          <thead class="table-light"><tr>
            <th style="width:30%">Karyawan</th><th>Jam Mulai</th><th>Jam Selesai</th><th>Istirahat (Jam)</th>
            <th>Uraian Pekerjaan</th><th></th>
          </tr></thead>
          <tbody></tbody>
        </table>
        </div>

        <button class="btn btn-success mt-2"><i class="bi bi-save"></i> Simpan Form</button>
      </form>
    </div></div>

    <!-- ====== DAFTAR FORM TERBARU (bisa dihapus) ====== -->
    <div class="card"><div class="card-body">
      <h5 class="card-title">Form Lembur Terbaru</h5>
      <div class="table-responsive">
      <table class="table table-sm table-hover">
        <thead class="table-light"><tr>
          <th>No Form</th><th>Tanggal</th><th>Divisi</th><th>Karyawan</th><th>Status</th><th></th>
        </tr></thead>
        <tbody>
        <?php foreach($formList as $r):
            $sudahAcc = in_array($r['status'],['DISETUJUI_HR','DITOLAK'],true);
            $bolehHapus = $sudahAcc ? in_array($peranSaya,['hr','admin_it'],true) : true;
        ?>
          <tr>
            <td><strong><?= h($r['no_form']) ?></strong></td>
            <td><?= tglR($r['tanggal']) ?></td>
            <td><small><?= h($r['nama_dept']??'—') ?></small></td>
            <td class="text-center"><?= $r['jml_org'] ?></td>
            <td><span class="badge bg-<?= $r['status']==='DISETUJUI_HR'?'success':($r['status']==='DITOLAK'?'danger':'warning') ?>">
                <?= h($r['status']) ?></span></td>
            <td>
              <a href="cetak.php?id=<?= $r['id_form'] ?>" target="_blank" class="btn btn-sm btn-outline-secondary" title="cetak"><i class="bi bi-printer"></i></a>
              <?php if($bolehHapus): ?>
              <a href="hapus.php?id=<?= $r['id_form'] ?>&dari=input.php"
                 onclick="return confirm('Hapus form <?= h($r['no_form']) ?>?')"
                 class="btn btn-sm btn-outline-danger" title="hapus"><i class="bi bi-trash"></i></a>
              <?php else: ?>
              <span class="text-muted small" title="sudah di-ACC, hanya HR/Admin IT"><i class="bi bi-lock"></i></span>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; if(!$formList): ?>
          <tr><td colspan="6" class="text-center text-muted py-3">Belum ada form.</td></tr>
        <?php endif; ?>
        </tbody>
      </table>
      </div>
    </div></div>

  </section>
</main>

<script>
const PEG = <?= json_encode(array_map(fn($p)=>[
    'id'=>$p['id_peg'],'nik'=>$p['nik'],'nama'=>$p['nama_peg'],
    'dept'=>$p['department_id']
], $pegList)) ?>;

// divisi yang sedang dipilih (untuk mengerucutkan daftar)
function deptTerpilih(){
  const v = document.querySelector('[name="department_id"]').value;
  return v ? parseInt(v) : null;
}

// daftar pegawai sesuai divisi terpilih (atau semua kalau belum pilih)
function pegawaiTersaring(){
  const d = deptTerpilih();
  return d ? PEG.filter(p => p.dept == d) : PEG;
}

function addRow(){
  const tr=document.createElement('tr');
  tr.innerHTML=`
    <td>
      <div class="pos-rel">
        <input type="text" class="form-control form-control-sm cari-peg" autocomplete="off"
               placeholder="ketik nama / NIK..." oninput="cariPeg(this)" onblur="setTimeout(()=>tutupSaran(this),200)">
        <input type="hidden" name="peg[]" value="">
        <div class="saran-box"></div>
      </div>
    </td>
    <td><input type="time" name="mulai[]" class="form-control form-control-sm"></td>
    <td><input type="time" name="selesai[]" class="form-control form-control-sm" required></td>
    <td><input type="number" name="istirahat[]" class="form-control form-control-sm" min="0" step="0.5" value="0"></td>
    <td><input type="text" name="uraian[]" class="form-control form-control-sm"></td>
    <td><button type="button" class="btn btn-sm btn-outline-danger" onclick="this.closest('tr').remove()">&times;</button></td>`;
  document.querySelector('#tbl tbody').appendChild(tr);
}

// tampilkan saran saat mengetik
function cariPeg(input){
  const q = input.value.trim().toLowerCase();
  const box = input.parentNode.querySelector('.saran-box');
  if (q.length < 1){ box.innerHTML=''; box.style.display='none'; return; }

  const hasil = pegawaiTersaring().filter(p =>
    p.nama.toLowerCase().includes(q) || (p.nik||'').toLowerCase().includes(q)
  ).slice(0, 8);

  if (!hasil.length){ box.innerHTML='<div class="saran-item text-muted">tidak ditemukan</div>'; box.style.display='block'; return; }

  box.innerHTML = hasil.map(p =>
    `<div class="saran-item" onmousedown="pilihPeg(this, ${p.id}, '${(p.nik+' — '+p.nama).replace(/'/g,"\\'")}')">
       <strong>${p.nama}</strong> <span class="text-muted">${p.nik}</span>
     </div>`).join('');
  box.style.display='block';
}

function pilihPeg(el, id, txt){
  const cell = el.closest('td');
  cell.querySelector('.cari-peg').value = txt;
  cell.querySelector('[name="peg[]"]').value = id;
  cell.querySelector('.saran-box').style.display='none';
}
function tutupSaran(input){
  const box = input.parentNode.querySelector('.saran-box');
  if (box) box.style.display='none';
}
function gantiDivisi(){
  // kosongkan baris yg sudah diisi supaya sesuai divisi baru
  const terisi = [...document.querySelectorAll('#tbl [name="peg[]"]')].some(i=>i.value);
  if (terisi && !confirm('Ganti divisi akan mengosongkan daftar karyawan yang sudah dipilih. Lanjutkan?')){
    return;
  }
  document.querySelector('#tbl tbody').innerHTML='';
  addRow(); addRow(); addRow();
}
addRow(); addRow(); addRow();  // mulai dengan 3 baris
</script>

<?php include '../template/footer.php'; ?>
