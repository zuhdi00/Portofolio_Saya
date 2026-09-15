<?php
/**
 * lupa_absen/approval_hr.php
 * HRD cek form lupa absen -> setuju (tulis balik ke absensi) / tolak.
 */
require_once __DIR__ . '/../auth/auth.php';
wajib_izin('modul_hr');   // HRD
require_once __DIR__ . '/../config/koneksi_sqlsrv.php';

$pesan='';
if ($_SERVER['REQUEST_METHOD']==='POST') {
    $id=(int)($_POST['id_form']??0);
    $keputusan=$_POST['keputusan']??'';
    $catatan=trim($_POST['hr_catatan']??'');
    $u=user_login();

    if($id && in_array($keputusan,['setuju','tolak'],true)){
        sqlsrv_begin_transaction($conn);
        try {
            $status=$keputusan==='setuju'?'DISETUJUI':'DITOLAK';
            sqlsrv_query($conn,
                "UPDATE dbo.lupa_absen_form SET status=?, hr_nama=?, hr_pada=GETDATE(), hr_catatan=?
                 WHERE id_form=? AND status='DIAJUKAN'",
                [$status,$u['nama_lengkap']??null,$catatan?:null,$id]);

            // kalau setuju: tulis balik ke absensi tiap baris detail
            if($keputusan==='setuju'){
                $rs=sqlsrv_query($conn,"SELECT * FROM dbo.lupa_absen_detail WHERE id_form=?",[$id]);
                while($d=sqlsrv_fetch_array($rs,SQLSRV_FETCH_ASSOC)){
                    $pegId=$d['pegawai_id'];
                    $tgl=$d['tanggal'] instanceof DateTime?$d['tanggal']->format('Y-m-d'):$d['tanggal'];
                    $jm=$d['jam_masuk'] instanceof DateTime?$d['jam_masuk']->format('H:i:s'):$d['jam_masuk'];
                    $jk=$d['jam_keluar'] instanceof DateTime?$d['jam_keluar']->format('H:i:s'):$d['jam_keluar'];

                    // update kolom yang diisi; hapus perlu_koreksi
                    $set=[]; $par=[];
                    if($jm){$set[]="jam_masuk=?";$par[]=$jm;}
                    if($jk){$set[]="jam_keluar=?";$par[]=$jk;}
                    $set[]="perlu_koreksi=0";
                    $set[]="keterangan=N'Koreksi lupa absen (disetujui HRD)'";
                    $par[]=$pegId; $par[]=$tgl;
                    // upsert: kalau baris absensi belum ada, buat
                    $cek=sqlsrv_query($conn,"SELECT COUNT(*) n FROM dbo.absensi WHERE pegawai_id=? AND tanggal=?",[$pegId,$tgl]);
                    $ada=sqlsrv_fetch_array($cek,SQLSRV_FETCH_ASSOC)['n']??0;
                    if($ada){
                        sqlsrv_query($conn,"UPDATE dbo.absensi SET ".implode(',',$set)." WHERE pegawai_id=? AND tanggal=?",$par);
                    } else {
                        sqlsrv_query($conn,
                            "INSERT INTO dbo.absensi (pegawai_id,tanggal,jam_masuk,jam_keluar,status,perlu_koreksi,sumber,keterangan)
                             VALUES (?,?,?,?,'hadir',0,'MANUAL',N'Koreksi lupa absen (disetujui HRD)')",
                            [$pegId,$tgl,$jm,$jk]);
                    }
                }
            }
            sqlsrv_commit($conn);
            $pesan="<div class='alert alert-success'>Form ".($keputusan==='setuju'?'disetujui & absensi diperbarui':'ditolak').".</div>";
        } catch(Exception $e){
            sqlsrv_rollback($conn);
            $pesan="<div class='alert alert-danger'>Gagal: ".htmlspecialchars(substr($e->getMessage(),0,300))."</div>";
        }
    }
}

$tab=$_GET['tab']??'diajukan';
$sf=['diajukan'=>'DIAJUKAN','disetujui'=>'DISETUJUI','ditolak'=>'DITOLAK'][$tab]??'DIAJUKAN';
$rsN=sqlsrv_query($conn,"SELECT COUNT(*) n FROM dbo.lupa_absen_form WHERE status='DIAJUKAN'");
$nP=sqlsrv_fetch_array($rsN,SQLSRV_FETCH_ASSOC)['n']??0;

$forms=sqlsrv_query($conn,
  "SELECT lf.*,d.nama_dept,(SELECT COUNT(*) FROM dbo.lupa_absen_detail x WHERE x.id_form=lf.id_form) jml
   FROM dbo.lupa_absen_form lf LEFT JOIN dbo.department d ON d.id_dept=lf.department_id
   WHERE lf.status=? ORDER BY lf.dibuat_pada DESC",[$sf]);

$page_title="Approval Lupa Absen";
include '../template/header.php';
include '../template/sidebar.php';
function h($v){return htmlspecialchars((string)($v??''));}
?>
<main id="main" class="main">
  <div class="pagetitle"><h1>Approval Lupa Absen (HRD)</h1></div>
  <section class="section">
    <?= $pesan ?>
    <ul class="nav nav-tabs mb-3">
      <li class="nav-item"><a class="nav-link <?= $tab==='diajukan'?'active':'' ?>" href="?tab=diajukan">Menunggu <span class="badge bg-warning"><?= $nP ?></span></a></li>
      <li class="nav-item"><a class="nav-link <?= $tab==='disetujui'?'active':'' ?>" href="?tab=disetujui">Disetujui</a></li>
      <li class="nav-item"><a class="nav-link <?= $tab==='ditolak'?'active':'' ?>" href="?tab=ditolak">Ditolak</a></li>
    </ul>
    <div class="card"><div class="card-body">
      <div class="table-responsive">
      <table class="table table-hover align-middle">
        <thead class="table-light"><tr><th>No Form</th><th>Divisi</th><th>Karyawan</th><th>Bukti</th><th>Dibuat Oleh</th>
          <?php if($tab==='diajukan'): ?><th style="width:230px">Keputusan</th><?php else: ?><th>Catatan HRD</th><?php endif; ?></tr></thead>
        <tbody>
        <?php $ada=false; while($r=sqlsrv_fetch_array($forms,SQLSRV_FETCH_ASSOC)): $ada=true; ?>
          <tr>
            <td><strong><?= h($r['no_form']) ?></strong></td>
            <td><small><?= h($r['nama_dept']??'—') ?></small></td>
            <td class="text-center"><?= $r['jml'] ?></td>
            <td><?php if($r['file_bukti']): ?><a href="file.php?id=<?= $r['id_form'] ?>" target="_blank" class="btn btn-sm btn-outline-dark"><i class="bi bi-image"></i></a><?php else: ?><span class="text-muted">—</span><?php endif; ?></td>
            <td><small><?= h($r['dibuat_oleh']??'—') ?></small></td>
            <?php if($tab==='diajukan'): ?>
              <td>
                <a href="cetak.php?id=<?= $r['id_form'] ?>" target="_blank" class="btn btn-sm btn-outline-secondary mb-1"><i class="bi bi-eye"></i> detail</a>
                <form method="POST" class="d-flex flex-column gap-1">
                  <input type="hidden" name="id_form" value="<?= $r['id_form'] ?>">
                  <input type="text" name="hr_catatan" class="form-control form-control-sm" placeholder="catatan">
                  <div class="btn-group btn-group-sm">
                    <button name="keputusan" value="setuju" class="btn btn-success">Setuju</button>
                    <button name="keputusan" value="tolak" class="btn btn-danger">Tolak</button>
                  </div>
                </form>
              </td>
            <?php else: ?>
              <td><small><?= h($r['hr_catatan']??'—') ?></small></td>
            <?php endif; ?>
          </tr>
        <?php endwhile; if(!$ada): ?><tr><td colspan="6" class="text-center text-muted py-4">Tidak ada form.</td></tr><?php endif; ?>
        </tbody>
      </table>
      </div>
    </div></div>
  </section>
</main>
<?php include '../template/footer.php'; ?>
