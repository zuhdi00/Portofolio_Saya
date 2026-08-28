<?php
/**
 * pengunduran_diri/index.php
 * Daftar pengajuan resign + form input (user/admin divisi).
 */
require_once __DIR__ . '/../auth/auth.php';
wajib_izin('resign_input');   // hr, admin_it. (atasan/admin divisi via peran juga bisa - lihat catatan)
require_once __DIR__ . '/../config/koneksi_sqlsrv.php';

$pesan = '';

/* ---------- proses input resign ---------- */
if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['aksi']??'')==='input') {
    $pegId = (int)($_POST['pegawai_id'] ?? 0);
    $dept  = (int)($_POST['department_id'] ?? 0) ?: null;
    $tglM  = $_POST['tgl_mulai'] ?? null;
    $tglB  = $_POST['tgl_berakhir'] ?? null;
    $alasan= trim($_POST['alasan'] ?? '');
    $u = user_login();

    if (!$pegId) {
        $pesan = "<div class='alert alert-danger'>Pilih karyawan yang mengundurkan diri.</div>";
    } else {
        // ambil nama pegawai
        $rs = sqlsrv_query($conn,"SELECT nama_peg FROM dbo.pegawai WHERE id_peg=?",[$pegId]);
        $namaPeg = ($r=sqlsrv_fetch_array($rs,SQLSRV_FETCH_ASSOC)) ? $r['nama_peg'] : null;

        // nomor surat RSG-YYYYMM-NNN
        $bulan = date('Ym');
        $rs = sqlsrv_query($conn,"SELECT COUNT(*)+1 n FROM dbo.pengunduran_diri WHERE FORMAT(dibuat_pada,'yyyyMM')=?",[$bulan]);
        $urut = sqlsrv_fetch_array($rs,SQLSRV_FETCH_ASSOC)['n'] ?? 1;
        $noSurat = sprintf('RSG-%s-%03d',$bulan,$urut);

        $st = sqlsrv_query($conn,
            "INSERT INTO dbo.pengunduran_diri
             (no_surat,pegawai_id,nama_pegawai,department_id,tgl_mulai,tgl_berakhir,alasan,dibuat_oleh,status)
             VALUES (?,?,?,?,?,?,?,?,'DIAJUKAN'); SELECT SCOPE_IDENTITY() AS id;",
            [$noSurat,$pegId,$namaPeg,$dept,$tglM?:null,$tglB?:null,$alasan?:null,$u['nama_lengkap']??null]);
        
        if ($st === false) {
            $pesan = "<div class='alert alert-danger'>Gagal menyimpan.</div>";
        } else {
            sqlsrv_next_result($st);
            sqlsrv_fetch($st);
            $idResign = sqlsrv_get_field($st, 0);
            
            // Trigger notification
            require_once __DIR__ . '/../config/notifikasi_helper.php';
            kirim_notifikasi_role_auto('hr', "Pengunduran Diri Baru", "Ada pengajuan pengunduran diri baru ($noSurat) yang butuh di-review.", "pengunduran_diri/index.php");
            kirim_notifikasi_role_auto('atasan', "Pengunduran Diri Baru", "Ada pengajuan pengunduran diri baru ($noSurat) yang butuh di-review.", "pengunduran_diri/index.php");
            
            $pesan = "<div class='alert alert-success'>Pengajuan resign <strong>$noSurat</strong> tersimpan. "
                   . "<a href='cetak.php?id=$idResign' target='_blank' class='btn btn-sm btn-outline-success ms-2'><i class='bi bi-printer'></i> Cetak Surat Resign</a></div>";
        }
    }
}

/* ---------- data ---------- */
$deptList=[]; $rs=sqlsrv_query($conn,"SELECT id_dept,nama_dept FROM dbo.department ORDER BY nama_dept");
while($r=sqlsrv_fetch_array($rs,SQLSRV_FETCH_ASSOC)) $deptList[]=$r;

$pegList=[]; $rs=sqlsrv_query($conn,
  "SELECT p.id_peg,p.nik,p.nama_peg,u.department_id FROM dbo.pegawai p
   LEFT JOIN dbo.unit_kerja u ON u.id=p.unit_kerja_id
   WHERE p.is_aktif=1 ORDER BY p.nama_peg");
while($r=sqlsrv_fetch_array($rs,SQLSRV_FETCH_ASSOC)) $pegList[]=$r;

$sql_daftar = "SELECT r.*, d.nama_dept FROM dbo.pengunduran_diri r
               LEFT JOIN dbo.department d ON d.id_dept=r.department_id";
$params_daftar = [];

// Jika user biasa, hanya tampilkan miliknya sendiri
$u = user_login();
if ($u['peran'] === 'user' && !empty($u['pegawai_id'])) {
    $sql_daftar .= " WHERE r.pegawai_id = ?";
    $params_daftar[] = $u['pegawai_id'];
} elseif ($u['peran'] === 'admin_divisi' && !empty($u['department_id'])) {
    $sql_daftar .= " WHERE r.department_id = ?";
    $params_daftar[] = $u['department_id'];
}

$sql_daftar .= " ORDER BY r.dibuat_pada DESC";
$daftar = sqlsrv_query($conn, $sql_daftar, $params_daftar);

$page_title="Pengunduran Diri";
include '../template/header.php';
include '../template/sidebar.php';
function h($v){return htmlspecialchars((string)($v??''));}
function tgl($v){return $v instanceof DateTime?$v->format('d-m-Y'):'—';}
$badge = ['DIAJUKAN'=>'warning','BERKAS_MASUK'=>'info','DISETUJUI'=>'success','DITOLAK'=>'danger'];
?>

<main id="main" class="main">
  <div class="pagetitle"><h1>Pengunduran Diri</h1>
    <nav><ol class="breadcrumb"><li class="breadcrumb-item"><a href="../index.php">Home</a></li>
    <li class="breadcrumb-item active">Pengunduran Diri</li></ol></nav></div>

  <section class="section">
    <?= $pesan ?>
    <div class="row">
      <!-- FORM INPUT -->
      <div class="col-lg-4">
        <div class="card"><div class="card-body">
          <h5 class="card-title">Ajukan Pengunduran Diri</h5>
          <form method="POST">
            <input type="hidden" name="aksi" value="input">
            <div class="mb-2"><label class="form-label small">Divisi</label>
              <select name="department_id" class="form-select" onchange="filterPeg()">
                <option value="">-- semua --</option>
                <?php foreach($deptList as $d): ?><option value="<?= $d['id_dept'] ?>"><?= h($d['nama_dept']) ?></option><?php endforeach; ?>
              </select></div>
            <div class="mb-2 pos-rel"><label class="form-label small">Karyawan</label>
              <input type="text" class="form-control cari-peg" id="cariPeg" autocomplete="off"
                     placeholder="ketik nama / NIK" oninput="cariPeg()" onblur="setTimeout(tutupSaran,200)">
              <input type="hidden" name="pegawai_id" id="pegId">
              <div class="saran-box" id="saranBox"></div></div>
            <div class="mb-2"><label class="form-label small">Tanggal Mulai (pengajuan)</label>
              <input type="date" name="tgl_mulai" class="form-control" value="<?= date('Y-m-d') ?>"></div>
            <div class="mb-2"><label class="form-label small">Tanggal Berakhir (hari terakhir kerja)</label>
              <input type="date" name="tgl_berakhir" class="form-control"></div>
            <div class="mb-3"><label class="form-label small">Alasan</label>
              <textarea name="alasan" class="form-control" rows="3" placeholder="alasan pengunduran diri"></textarea></div>
            <button class="btn btn-primary w-100"><i class="bi bi-box-arrow-right"></i> Ajukan Resign</button>
          </form>
        </div></div>
      </div>

      <!-- DAFTAR -->
      <div class="col-lg-8">
        <div class="card"><div class="card-body">
          <h5 class="card-title">Daftar Pengunduran Diri</h5>
          <div class="table-responsive">
          <table class="table table-hover align-middle">
            <thead class="table-light"><tr>
              <th>No Surat</th><th>Nama</th><th>Divisi</th><th>Berakhir</th><th>Status</th><th>Aksi</th>
            </tr></thead>
            <tbody>
            <?php $ada=false; while($r=sqlsrv_fetch_array($daftar,SQLSRV_FETCH_ASSOC)): $ada=true; ?>
              <tr>
                <td><strong><?= h($r['no_surat']) ?></strong></td>
                <td><?= h($r['nama_pegawai']) ?></td>
                <td><small><?= h($r['nama_dept']??'—') ?></small></td>
                <td><?= tgl($r['tgl_berakhir']) ?></td>
                <td><span class="badge bg-<?= $badge[$r['status']]??'secondary' ?>"><?= h($r['status']) ?></span></td>
                <td>
                  <a href="cetak.php?id=<?= $r['id_resign'] ?>" target="_blank" class="btn btn-sm btn-outline-secondary" title="cetak surat"><i class="bi bi-printer"></i></a>
                  <a href="upload.php?id=<?= $r['id_resign'] ?>" class="btn btn-sm btn-outline-primary" title="upload PDF (HRD)"><i class="bi bi-upload"></i></a>
                  <a href="penilaian.php?id=<?= $r['id_resign'] ?>" class="btn btn-sm btn-outline-success" title="penilaian atasan"><i class="bi bi-clipboard-check"></i></a>
                  <?php if($r['file_pdf']): ?>
                  <a href="file.php?id=<?= $r['id_resign'] ?>" target="_blank" class="btn btn-sm btn-outline-dark" title="lihat PDF"><i class="bi bi-file-pdf"></i></a>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endwhile; if(!$ada): ?>
              <tr><td colspan="6" class="text-center text-muted py-4">Belum ada pengajuan.</td></tr>
            <?php endif; ?>
            </tbody>
          </table>
          </div>
        </div></div>
      </div>
    </div>
  </section>
</main>

<style>
  .pos-rel{position:relative}
  .saran-box{position:absolute;top:100%;left:0;right:0;z-index:50;background:#fff;
    border:1px solid #ccc;border-radius:4px;max-height:220px;overflow-y:auto;
    box-shadow:0 4px 10px rgba(0,0,0,.15);display:none}
  .saran-item{padding:6px 10px;cursor:pointer;font-size:.85rem;border-bottom:1px solid #f0f0f0}
  .saran-item:hover{background:#e7f1ff}
</style>
<script>
const PEG = <?= json_encode(array_map(fn($p)=>['id'=>$p['id_peg'],'nik'=>$p['nik'],'nama'=>$p['nama_peg'],'dept'=>$p['department_id']], $pegList)) ?>;
function deptDipilih(){ const v=document.querySelector('[name="department_id"]').value; return v?parseInt(v):null; }
function pegSaring(){ const d=deptDipilih(); return d?PEG.filter(p=>p.dept==d):PEG; }
function cariPeg(){
  const q=document.getElementById('cariPeg').value.trim().toLowerCase();
  const box=document.getElementById('saranBox');
  if(q.length<1){box.style.display='none';return;}
  const hasil=pegSaring().filter(p=>p.nama.toLowerCase().includes(q)||(p.nik||'').toLowerCase().includes(q)).slice(0,8);
  if(!hasil.length){box.innerHTML='<div class="saran-item text-muted">tidak ditemukan</div>';box.style.display='block';return;}
  box.innerHTML=hasil.map(p=>`<div class="saran-item" onmousedown="pilih(${p.id},'${(p.nik+' — '+p.nama).replace(/'/g,"\\'")}')"><strong>${p.nama}</strong> <span class="text-muted">${p.nik}</span></div>`).join('');
  box.style.display='block';
}
function pilih(id,txt){ document.getElementById('cariPeg').value=txt; document.getElementById('pegId').value=id; document.getElementById('saranBox').style.display='none'; }
function tutupSaran(){ document.getElementById('saranBox').style.display='none'; }
function filterPeg(){ document.getElementById('cariPeg').value=''; document.getElementById('pegId').value=''; }
</script>

<?php include '../template/footer.php'; ?>
