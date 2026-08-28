<?php
/**
 * lembur/rekap_hr.php
 * HR: daftar form lembur + REKAP TOTAL JAM per karyawan (untuk cek batas pemerintah).
 * Batas tidak dipaksakan sistem - HR yang menilai (sesuai permintaan).
 */
require_once __DIR__ . '/../auth/auth.php';
wajib_izin('lembur_rekap');
$page_title = "Rekap Lembur (HR)";
include '../template/header.php';
include '../config/koneksi_sqlsrv.php';
include '../template/sidebar.php';

// notifikasi hasil hapus
$notif = '';
if (($_GET['msg']??'')==='terhapus')  $notif = "<div class='alert alert-success'>Form lembur dihapus.</div>";
if (($_GET['msg']??'')==='gagal')     $notif = "<div class='alert alert-danger'>Gagal menghapus form.</div>";
if (($_GET['msg']??'')==='notallowed')$notif = "<div class='alert alert-warning'>Form sudah di-ACC. Hanya HR/Admin IT yang bisa menghapus.</div>";

$dari   = $_GET['dari']   ?? date('Y-m-01');
$sampai = $_GET['sampai'] ?? date('Y-m-t');

function h($v){return htmlspecialchars((string)($v??''));}
function tgl($v){return $v instanceof DateTime?$v->format('d-m-Y'):'—';}

/* ---------- rekap total jam per karyawan ---------- */
$rekap = sqlsrv_query($conn,
    "SELECT p.id_peg, p.nik, p.nama_peg, p.status_karyawan, d.nama_dept,
            COUNT(DISTINCT ld.id_form) AS jml_form,
            COUNT(*)          AS jml_hari,
            SUM(ld.durasi_jam) AS total_jam
     FROM dbo.lembur_detail ld
     JOIN dbo.lembur_form lf ON lf.id_form = ld.id_form
     JOIN dbo.pegawai p ON p.id_peg = ld.pegawai_id
     LEFT JOIN dbo.unit_kerja u ON u.id = p.unit_kerja_id
     LEFT JOIN dbo.department d ON d.id_dept = u.department_id
     WHERE lf.tanggal BETWEEN ? AND ?
     GROUP BY p.id_peg, p.nik, p.nama_peg, p.status_karyawan, d.nama_dept
     ORDER BY total_jam DESC", [$dari,$sampai]);

/* ---------- daftar form ---------- */
$forms = sqlsrv_query($conn,
    "SELECT lf.*, d.nama_dept,
            (SELECT COUNT(*) FROM dbo.lembur_detail x WHERE x.id_form=lf.id_form) AS jml_org,
            (SELECT SUM(durasi_jam) FROM dbo.lembur_detail x WHERE x.id_form=lf.id_form) AS total_jam
     FROM dbo.lembur_form lf
     LEFT JOIN dbo.department d ON d.id_dept = lf.department_id
     WHERE lf.tanggal BETWEEN ? AND ?
     ORDER BY lf.tanggal DESC, lf.id_form DESC", [$dari,$sampai]);
?>

<main id="main" class="main">
  <div class="pagetitle"><h1>Rekap Lembur (HR)</h1>
    <nav><ol class="breadcrumb"><li class="breadcrumb-item"><a href="/hris/index.php">Home</a></li>
    <li class="breadcrumb-item active">Rekap Lembur</li></ol></nav></div>

  <section class="section">
    <?= $notif ?>
    <form method="GET" class="row g-2 mb-3">
      <div class="col-md-3"><label class="form-label small">Dari</label>
        <input type="date" name="dari" class="form-control" value="<?= h($dari) ?>"></div>
      <div class="col-md-3"><label class="form-label small">Sampai</label>
        <input type="date" name="sampai" class="form-control" value="<?= h($sampai) ?>"></div>
      <div class="col-md-3 d-flex align-items-end">
        <button class="btn btn-primary btn-sm">Terapkan</button></div>
    </form>

    <div class="row">
      <!-- REKAP PER KARYAWAN -->
      <div class="col-lg-6">
        <div class="card"><div class="card-body">
          <h5 class="card-title d-flex justify-content-between align-items-center">
            <span>Total Jam Lembur per Karyawan</span>
            <button type="button" onclick="exportExcel()" class="btn btn-success btn-sm">
              <i class="bi bi-file-earmark-excel"></i> Export Excel</button>
          </h5>
          <p class="text-muted small">Acuan batas pemerintah (PP 35/2021): maks 4 jam/hari, 18 jam/minggu.
             Sistem tidak memblokir — mohon dinilai HR.</p>
          <div class="table-responsive">
          <table class="table table-sm table-hover" id="tblRekap">
            <thead class="table-light"><tr><th>NIK</th><th>Nama</th><th>Dept</th><th>Status</th>
              <th>Hari</th><th>Total Jam</th></tr></thead>
            <tbody>
            <?php $ada=false; while($r=sqlsrv_fetch_array($rekap,SQLSRV_FETCH_ASSOC)): $ada=true;
                $tj=(float)$r['total_jam']; ?>
              <tr>
                <td><?= h($r['nik']) ?></td>
                <td><?= h($r['nama_peg']) ?></td>
                <td><small><?= h($r['nama_dept']??'—') ?></small></td>
                <td><small><?= h($r['status_karyawan']??'—') ?></small></td>
                <td><?= $r['jml_hari'] ?></td>
                <td><strong class="<?= $tj>=40?'text-danger':($tj>=20?'text-warning':'') ?>">
                    <?= number_format($tj,1) ?></strong></td>
              </tr>
            <?php endwhile; if(!$ada): ?>
              <tr><td colspan="6" class="text-center text-muted py-3">Belum ada data.</td></tr>
            <?php endif; ?>
            </tbody>
          </table>
          </div>
        </div></div>
      </div>

      <!-- DAFTAR FORM -->
      <div class="col-lg-6">
        <div class="card"><div class="card-body">
          <h5 class="card-title">Daftar Form Lembur</h5>
          <div class="table-responsive">
          <table class="table table-sm table-hover">
            <thead class="table-light"><tr><th>No Form</th><th>Tanggal</th><th>Divisi</th>
              <th>Org</th><th>Jam</th><th>Status</th><th></th></tr></thead>
            <tbody>
            <?php $ada=false; while($r=sqlsrv_fetch_array($forms,SQLSRV_FETCH_ASSOC)): $ada=true; ?>
              <tr>
                <td><small><?= h($r['no_form']) ?></small></td>
                <td><?= tgl($r['tanggal']) ?></td>
                <td><small><?= h($r['nama_dept']??'—') ?></small></td>
                <td><?= $r['jml_org'] ?></td>
                <td><?= number_format((float)$r['total_jam'],1) ?></td>
                <td><span class="badge bg-<?= $r['status']==='DISETUJUI_HR'?'success':($r['status']==='DITOLAK'?'danger':'warning') ?>">
                    <?= h($r['status']) ?></span></td>
                <td>
                  <a href="cetak.php?id=<?= $r['id_form'] ?>" target="_blank" class="btn btn-sm btn-outline-secondary" title="cetak">
                    <i class="bi bi-printer"></i></a>
                  <?php
                    $sudahAcc = in_array($r['status'], ['DISETUJUI_HR','DITOLAK'], true);
                    // di rekap_hr, user pasti hr/admin_it (wajib_izin lembur_rekap) -> selalu boleh
                  ?>
                  <a href="hapus.php?id=<?= $r['id_form'] ?>&dari=rekap_hr.php"
                     onclick="return confirm('Hapus form <?= h($r['no_form']) ?>? Tindakan ini tidak bisa dibatalkan.')"
                     class="btn btn-sm btn-outline-danger" title="hapus">
                    <i class="bi bi-trash"></i></a>
                </td>
              </tr>
            <?php endwhile; if(!$ada): ?>
              <tr><td colspan="7" class="text-center text-muted py-3">Belum ada form.</td></tr>
            <?php endif; ?>
            </tbody>
          </table>
          </div>
        </div></div>
      </div>
    </div>
  </section>
</main>

<script>
function exportExcel(){
  const tabel = document.getElementById('tblRekap');
  let html = '<table border="1">' + tabel.innerHTML + '</table>';
  // bersihkan progress-bar/badge, ambil teks saja sudah cukup untuk Excel
  const periode = '<?= $dari ?>_sd_<?= $sampai ?>';
  const tmpl = `<html xmlns:o="urn:schemas-microsoft-com:office:office"
    xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">
    <head><meta charset="utf-8"><!--[if gte mso 9]><xml>
    <x:ExcelWorkbook><x:ExcelWorksheets><x:ExcelWorksheet>
    <x:Name>Rekap Lembur</x:Name><x:WorksheetOptions><x:DisplayGridlines/>
    </x:WorksheetOptions></x:ExcelWorksheet></x:ExcelWorksheets></x:ExcelWorkbook>
    </xml><![endif]--></head><body>
    <h3>Rekap Total Jam Lembur per Karyawan</h3>
    <p>Periode: <?= $dari ?> s/d <?= $sampai ?></p>
    ${html}</body></html>`;
  const blob = new Blob(['\ufeff'+tmpl], {type:'application/vnd.ms-excel'});
  const a = document.createElement('a');
  a.href = URL.createObjectURL(blob);
  a.download = 'rekap_lembur_'+periode+'.xls';
  a.click();
}
</script>

<?php include '../template/footer.php'; ?>
