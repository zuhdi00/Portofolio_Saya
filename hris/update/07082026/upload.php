<?php
/**
 * pengunduran_diri/upload.php?id=ID
 * HRD upload PDF surat resign -> simpan ke \\spsdmz\gg$\HRD\SuratResign
 * Setelah upload, status -> BERKAS_MASUK.
 */
require_once __DIR__ . '/../auth/auth.php';
wajib_izin('modul_hr');   // HRD
require_once __DIR__ . '/../config/koneksi_sqlsrv.php';

// ===== KONFIGURASI FOLDER SIMPAN =====
// Ganti sesuai kebutuhan. UNC share HRD:
$FOLDER_SIMPAN = '\\\\spsdmz\\gg$\\HRD\\SuratResign';
// Alternatif lokal (kalau UNC tak bisa ditulis Apache):
// $FOLDER_SIMPAN = 'C:\\xampp\\htdocs\\hris\\uploads\\resign';
// =====================================

$id = (int)($_GET['id'] ?? 0);
if (!$id) die("ID tidak valid.");

$st = sqlsrv_query($conn,"SELECT no_surat, nama_pegawai, file_pdf FROM dbo.pengunduran_diri WHERE id_resign=?",[$id]);
$r = $st ? sqlsrv_fetch_array($st,SQLSRV_FETCH_ASSOC) : null;
if (!$r) die("Data tidak ditemukan.");

$pesan='';
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_FILES['pdf'])) {
    $f = $_FILES['pdf'];
    if ($f['error'] !== UPLOAD_ERR_OK) {
        $pesan = "<div class='alert alert-danger'>Gagal upload (kode {$f['error']}).</div>";
    } elseif (strtolower(pathinfo($f['name'], PATHINFO_EXTENSION)) !== 'pdf') {
        $pesan = "<div class='alert alert-danger'>File harus PDF.</div>";
    } elseif ($f['size'] > 10*1024*1024) {
        $pesan = "<div class='alert alert-danger'>Ukuran maksimal 10 MB.</div>";
    } else {
        // nama file: nosurat_nama.pdf (aman)
        $namaAman = preg_replace('/[^A-Za-z0-9_\-]/','_', $r['no_surat'].'_'.$r['nama_pegawai']);
        $namaFile = $namaAman . '.pdf';
        $u = user_login();

        if (!is_dir($FOLDER_SIMPAN)) @mkdir($FOLDER_SIMPAN, 0777, true);
        $tujuan = rtrim($FOLDER_SIMPAN,'\\/') . DIRECTORY_SEPARATOR . $namaFile;

        if (@move_uploaded_file($f['tmp_name'], $tujuan)) {
            sqlsrv_query($conn,
                "UPDATE dbo.pengunduran_diri
                 SET file_pdf=?, pdf_oleh=?, pdf_pada=GETDATE(),
                     status=CASE WHEN status='DIAJUKAN' THEN 'BERKAS_MASUK' ELSE status END
                 WHERE id_resign=?",
                [$namaFile, $u['nama_lengkap']??null, $id]);
            $pesan = "<div class='alert alert-success'>PDF tersimpan: <strong>".h($namaFile)."</strong> di folder HRD.</div>";
        } else {
            $err = error_get_last()['message'] ?? '';
            $pesan = "<div class='alert alert-danger'>Gagal menyimpan file ke folder.<br>
                <small>Kemungkinan Apache tidak punya akses tulis ke $FOLDER_SIMPAN.<br>
                Solusi: jalankan Apache sebagai akun yang bisa menulis ke \\\\spsdmz,
                atau ubah \$FOLDER_SIMPAN ke folder lokal.</small><br>
                <small class='text-muted'>".h(substr($err,0,200))."</small></div>";
        }
    }
    // refresh data
    $st = sqlsrv_query($conn,"SELECT no_surat, nama_pegawai, file_pdf FROM dbo.pengunduran_diri WHERE id_resign=?",[$id]);
    $r = sqlsrv_fetch_array($st,SQLSRV_FETCH_ASSOC);
}

$page_title="Upload PDF Resign";
include '../template/header.php';
include '../template/sidebar.php';
function h($v){return htmlspecialchars((string)($v??''));}
?>
<main id="main" class="main">
  <div class="pagetitle"><h1>Upload Surat Resign (PDF)</h1></div>
  <section class="section">
    <?= $pesan ?>
    <div class="card"><div class="card-body">
      <h5 class="card-title"><?= h($r['no_surat']) ?> — <?= h($r['nama_pegawai']) ?></h5>
      <p class="text-muted small">Upload PDF surat resign yang sudah ditandatangani atasan & HRD.
         File disimpan ke <code><?= h($FOLDER_SIMPAN) ?></code>.</p>

      <?php if($r['file_pdf']): ?>
        <div class="alert alert-info">Berkas saat ini: <strong><?= h($r['file_pdf']) ?></strong>
          — <a href="file.php?id=<?= $id ?>" target="_blank">lihat</a>. Upload lagi untuk mengganti.</div>
      <?php endif; ?>

      <form method="POST" enctype="multipart/form-data">
        <div class="mb-3">
          <label class="form-label">Pilih file PDF</label>
          <input type="file" name="pdf" accept="application/pdf" class="form-control" required>
          <small class="text-muted">Maksimal 10 MB, format PDF.</small>
        </div>
        <button class="btn btn-primary"><i class="bi bi-upload"></i> Upload</button>
        <a href="index.php" class="btn btn-secondary">Kembali</a>
      </form>
    </div></div>
  </section>
</main>
<?php include '../template/footer.php'; ?>
