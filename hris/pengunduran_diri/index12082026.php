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
    $tglM  = $_POST['tanggal_pengajuan'] ?? null;
    $tglB  = $_POST['tanggal_efektif'] ?? null;
    $alasan= trim($_POST['alasan'] ?? '');
    $u = user_login();

    if (!$pegId) {
        $pesan = "<div class='alert alert-danger'>Pilih karyawan dari daftar saran. "
               . "Ketik nama/NIK di kolom Karyawan, lalu <strong>klik salah satu nama</strong> yang muncul "
               . "(mengetik saja belum cukup).</div>";
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
             (no_surat,pegawai_id,nama_pegawai,department_id,tanggal_pengajuan,tanggal_efektif,alasan,dibuat_oleh,status)
             VALUES (?,?,?,?,?,?,?,?,'DIAJUKAN')",
            [$noSurat,$pegId,$namaPeg,$dept,$tglM?:null,$tglB?:null,$alasan?:null,$u['nama_lengkap']??null]);
        $pesan = $st===false
            ? "<div class='alert alert-danger'>Gagal menyimpan.</div>"
            : "<div class='alert alert-success'>Pengajuan resign <strong>$noSurat</strong> tersimpan. Silakan cetak untuk TTD.</div>";
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

$daftar = sqlsrv_query($conn,
  "SELECT r.*, d.nama_dept FROM dbo.pengunduran_diri r
   LEFT JOIN dbo.department d ON d.id_dept=r.department_id
   ORDER BY r.dibuat_pada DESC");

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
                     placeholder="ketik nama / NIK" oninput="cariPeg(this)" onblur="setTimeout(tutupSaran,200)">
              <input type="hidden" name="pegawai_id" id="pegId">
              <div class="saran-box" id="saranBox"></div></div>
            <div class="mb-2"><label class="form-label small">Tanggal Mulai (pengajuan)</label>
              <input type="date" name="tanggal_pengajuan" class="form-control" value="<?= date('Y-m-d') ?>"></div>
            <div class="mb-2"><label class="form-label small">Tanggal Berakhir (hari terakhir kerja)</label>
              <input type="date" name="tanggal_efektif" class="form-control"></div>
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
                <td><?= tgl($r['tanggal_efektif']) ?></td>
                <td><span class="badge bg-<?= $badge[$r['status']]??'secondary' ?>"><?= h($r['status']) ?></span></td>
                <td>
                  <a href="cetak.php?id=<?= $r['id_pengunduran'] ?>" target="_blank" class="btn btn-sm btn-outline-secondary" title="cetak surat"><i class="bi bi-printer"></i></a>
                  <a href="upload.php?id=<?= $r['id_pengunduran'] ?>" class="btn btn-sm btn-outline-primary" title="upload PDF (HRD)"><i class="bi bi-upload"></i></a>
                  <a href="penilaian.php?id=<?= $r['id_pengunduran'] ?>" class="btn btn-sm btn-outline-success" title="penilaian atasan"><i class="bi bi-clipboard-check"></i></a>
                  <?php if($r['file_pdf']): ?>
                  <a href="file.php?id=<?= $r['id_pengunduran'] ?>" target="_blank" class="btn btn-sm btn-outline-dark" title="lihat PDF"><i class="bi bi-file-pdf"></i></a>
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
let acTimer;
function cariPeg(input){
  const q = input.value.trim();
  const box = document.getElementById('saranBox');
  clearTimeout(acTimer);
  // kosongkan pilihan lama saat user mengetik ulang
  document.getElementById('pegId').value = '';
  if(q.length < 1){ box.style.display='none'; return; }

  const dept = document.querySelector('[name="department_id"]').value;
  acTimer = setTimeout(function(){
    const url = 'cari_pegawai.php?q=' + encodeURIComponent(q) + (dept ? '&dept=' + dept : '');
    box.innerHTML = '<div class="saran-item text-muted">mencari...</div>';
    box.style.display = 'block';

    fetch(url)
      .then(function(r){
        if(!r.ok) throw new Error('HTTP ' + r.status);
        return r.text();          // ambil teks dulu supaya bisa lihat kalau bukan JSON
      })
      .then(function(txt){
        let data;
        try { data = JSON.parse(txt); }
        catch(e){
          // endpoint mengembalikan HTML (mis. halaman login / error PHP)
          box.innerHTML = '<div class="saran-item text-danger">Gagal memuat data. '
                        + 'Cek cari_pegawai.php (mungkin belum login / error).</div>';
          box.style.display = 'block';
          console.error('Respon bukan JSON:', txt.substring(0,300));
          return;
        }
        if(!data.length){
          box.innerHTML = '<div class="saran-item text-muted">Tidak ditemukan</div>';
          box.style.display = 'block';
          return;
        }
        // bangun elemen via DOM (tanpa atribut inline) -> aman dari masalah kutip
        box.innerHTML = '';
        data.forEach(function(p){
          const div = document.createElement('div');
          div.className = 'saran-item';
          const label = (p.nik ? p.nik + ' \u2014 ' : '') + p.nama;
          div.innerHTML = '<strong></strong> <span class="text-muted"></span>';
          div.querySelector('strong').textContent = p.nama;
          div.querySelector('span').textContent = p.nik || '';
          div.addEventListener('mousedown', function(ev){
            ev.preventDefault();
            pilih(p.id, label);
          });
          box.appendChild(div);
        });
        box.style.display = 'block';
      })
      .catch(function(err){
        box.innerHTML = '<div class="saran-item text-danger">Gagal terhubung: '
                      + err.message + '</div>';
        box.style.display = 'block';
        console.error('Autocomplete error:', err);
      });
  }, 250);
}
function pilih(id, txt){
  document.getElementById('cariPeg').value = txt;
  document.getElementById('pegId').value = id;
  document.getElementById('saranBox').style.display='none';
}
function tutupSaran(){ document.getElementById('saranBox').style.display='none'; }
function filterPeg(){
  document.getElementById('cariPeg').value='';
  document.getElementById('pegId').value='';
  document.getElementById('saranBox').style.display='none';
}
</script>

<?php include '../template/footer.php'; ?>
