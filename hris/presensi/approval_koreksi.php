<?php
require_once __DIR__ . '/../auth/auth.php';
wajib_izin('approval_koreksi');
$page_title = "Approval Koreksi Absensi";
include '../template/header.php';
include '../config/koneksi_sqlsrv.php';
include '../template/sidebar.php';

function h($v){return htmlspecialchars((string)($v??''));}
function tgl($v){return $v instanceof DateTime?$v->format('d-m-Y'):'—';}
function jamF($v){return $v instanceof DateTime?$v->format('H:i'):'—';}

/* ---------- proses keputusan ---------- */
$pesan='';
if ($_SERVER['REQUEST_METHOD']==='POST') {
    $id       = (int)($_POST['id_koreksi'] ?? 0);
    $keputusan= $_POST['keputusan'] ?? '';
    $jamMasuk = $_POST['jam_masuk_usulan'] ?? '';
    $jamKeluar= $_POST['jam_keluar_usulan'] ?? '';
    $catatan  = trim($_POST['catatan'] ?? '');

    sqlsrv_begin_transaction($conn);
    try {
        // ambil data koreksi
        $st=sqlsrv_query($conn,"SELECT * FROM dbo.absensi_koreksi WHERE id_koreksi=? AND status_approval='PENDING'",[$id]);
        $k=sqlsrv_fetch_array($st,SQLSRV_FETCH_ASSOC);
        if(!$k) throw new Exception("Data tidak ditemukan atau sudah diproses.");

        if ($keputusan==='setuju') {
            // update tabel koreksi
            sqlsrv_query($conn,
                "UPDATE dbo.absensi_koreksi
                 SET status_approval='DISETUJUI', jam_masuk_usulan=?, jam_keluar_usulan=?,
                     approved_pada=GETDATE(), catatan=?
                 WHERE id_koreksi=?",
                [$jamMasuk?:null,$jamKeluar?:null,$catatan?:null,$id]);

            // tulis balik ke absensi: isi jam yang kosong, hapus flag perlu_koreksi
            $pegId=$k['pegawai_id'];
            $tglK =$k['tanggal'] instanceof DateTime ? $k['tanggal']->format('Y-m-d') : $k['tanggal'];
            // hanya isi kolom yg diusulkan (yang tadinya kosong)
            $setParts=[]; $params=[];
            if($jamMasuk){ $setParts[]="jam_masuk=?"; $params[]=$jamMasuk; }
            if($jamKeluar){ $setParts[]="jam_keluar=?"; $params[]=$jamKeluar; }
            $setParts[]="perlu_koreksi=0";
            $setParts[]="keterangan=N'Dikoreksi via approval'";
            $params[]=$pegId; $params[]=$tglK;
            $sqlA="UPDATE dbo.absensi SET ".implode(',',$setParts)." WHERE pegawai_id=? AND tanggal=?";
            $r=sqlsrv_query($conn,$sqlA,$params);
            if($r===false) throw new Exception("Gagal update absensi: ".print_r(sqlsrv_errors(),true));

            $pesan="<div class='alert alert-success'>Koreksi disetujui & absensi diperbarui.</div>";
        } else {
            sqlsrv_query($conn,
                "UPDATE dbo.absensi_koreksi SET status_approval='DITOLAK', approved_pada=GETDATE(), catatan=? WHERE id_koreksi=?",
                [$catatan?:null,$id]);
            $pesan="<div class='alert alert-warning'>Koreksi ditolak.</div>";
        }
        sqlsrv_commit($conn);
    } catch(Exception $e){
        sqlsrv_rollback($conn);
        $pesan="<div class='alert alert-danger'>Gagal: ".h(substr($e->getMessage(),0,300))."</div>";
    }
}

/* ---------- filter ---------- */
$dari  = $_GET['dari']  ?? date('Y-m-01');
$sampai= $_GET['sampai']?? date('Y-m-t');
$cari  = trim($_GET['cari'] ?? '');

$where=["k.status_approval='PENDING'","k.tanggal BETWEEN ? AND ?"]; $params=[$dari,$sampai];
if($cari!==''){ $where[]="(p.nama_peg LIKE ? OR p.nik LIKE ?)"; $params[]="%$cari%"; $params[]="%$cari%"; }

$total = sqlsrv_query($conn,"SELECT COUNT(*) n FROM dbo.absensi_koreksi WHERE status_approval='PENDING'");
$nPending = sqlsrv_fetch_array($total,SQLSRV_FETCH_ASSOC)['n'] ?? 0;

$rows=sqlsrv_query($conn,
    "SELECT TOP 200 k.*, p.nama_peg, p.nik, d.nama_dept
     FROM dbo.absensi_koreksi k
     JOIN dbo.pegawai p ON p.id_peg=k.pegawai_id
     LEFT JOIN dbo.unit_kerja u ON u.id=p.unit_kerja_id
     LEFT JOIN dbo.department d ON d.id_dept=u.department_id
     WHERE ".implode(' AND ',$where)."
     ORDER BY k.tanggal DESC, p.nama_peg", $params);
?>

<main id="main" class="main">
  <div class="pagetitle"><h1>Approval Koreksi Absensi</h1>
    <nav><ol class="breadcrumb"><li class="breadcrumb-item"><a href="/hris/index.php">Home</a></li>
    <li class="breadcrumb-item active">Koreksi Absensi</li></ol></nav></div>

  <section class="section">
    <?= $pesan ?>

    <div class="card"><div class="card-body">
      <h5 class="card-title">
        Antrian Koreksi <span class="badge bg-danger"><?= number_format($nPending) ?> pending</span>
      </h5>
      <p class="text-muted small">Tap tidak lengkap (lupa tap masuk/pulang). Isi jam yang kosong lalu setujui —
         absensi otomatis diperbarui.</p>

      <form method="GET" class="row g-2 mb-3">
        <div class="col-md-2"><input type="date" name="dari" class="form-control form-control-sm" value="<?= h($dari) ?>"></div>
        <div class="col-md-2"><input type="date" name="sampai" class="form-control form-control-sm" value="<?= h($sampai) ?>"></div>
        <div class="col-md-3"><input type="text" name="cari" class="form-control form-control-sm" placeholder="Cari nama/NIK" value="<?= h($cari) ?>"></div>
        <div class="col-md-2"><button class="btn btn-sm btn-primary">Filter</button></div>
      </form>

      <div class="table-responsive">
      <table class="table table-hover align-middle">
        <thead class="table-light"><tr>
          <th>Tanggal</th><th>Nama</th><th>Dept</th><th>Jenis</th>
          <th>Jam Asli</th><th>Usulan Masuk</th><th>Usulan Pulang</th><th style="width:170px">Aksi</th>
        </tr></thead>
        <tbody>
        <?php $ada=false; while($r=sqlsrv_fetch_array($rows,SQLSRV_FETCH_ASSOC)): $ada=true;
            $jmA=jamF($r['jam_masuk_asli']); $jkA=jamF($r['jam_keluar_asli']); ?>
          <tr>
            <form method="POST">
            <input type="hidden" name="id_koreksi" value="<?= $r['id_koreksi'] ?>">
            <td><?= tgl($r['tanggal']) ?></td>
            <td><strong><?= h($r['nama_peg']) ?></strong><br><small class="text-muted"><?= h($r['nik']) ?></small></td>
            <td><small><?= h($r['nama_dept']??'—') ?></small></td>
            <td><span class="badge bg-<?= $r['jenis']==='LUPA_TAP_PULANG'?'warning':'info' ?>">
                <?= h(str_replace('_',' ',$r['jenis'])) ?></span></td>
            <td><small>M: <?= $jmA ?><br>P: <?= $jkA ?></small></td>
            <td><input type="time" name="jam_masuk_usulan" class="form-control form-control-sm"
                       value="<?= $r['jam_masuk_asli'] instanceof DateTime?$r['jam_masuk_asli']->format('H:i'):'' ?>"></td>
            <td><input type="time" name="jam_keluar_usulan" class="form-control form-control-sm"
                       value="<?= $r['jam_keluar_asli'] instanceof DateTime?$r['jam_keluar_asli']->format('H:i'):'' ?>"></td>
            <td>
              <input type="text" name="catatan" class="form-control form-control-sm mb-1" placeholder="catatan">
              <div class="btn-group btn-group-sm w-100">
                <button name="keputusan" value="setuju" class="btn btn-success">Setuju</button>
                <button name="keputusan" value="tolak" class="btn btn-outline-danger">Tolak</button>
              </div>
            </td>
            </form>
          </tr>
        <?php endwhile; if(!$ada): ?>
          <tr><td colspan="8" class="text-center text-muted py-4">Tidak ada antrian koreksi.</td></tr>
        <?php endif; ?>
        </tbody>
      </table>
      </div>
      <?php if($nPending>200): ?>
        <div class="alert alert-info py-2">Menampilkan 200 teratas dari <?= number_format($nPending) ?>. Persempit filter untuk sisanya.</div>
      <?php endif; ?>
    </div></div>
  </section>
</main>

<?php include '../template/footer.php'; ?>
