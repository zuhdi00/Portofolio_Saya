<?php
/**
 * pengunduran_diri/penilaian.php?id=ID
 * Atasan isi catatan penilaian -> otomatis ACC (status DISETUJUI).
 */
require_once __DIR__ . '/../auth/auth.php';
wajib_izin('resign_nilai');   // atasan, hr, admin_it
require_once __DIR__ . '/../config/koneksi_sqlsrv.php';

$id = (int)($_GET['id'] ?? 0);
if (!$id) die("ID tidak valid.");

$st = sqlsrv_query($conn,"SELECT * FROM dbo.pengunduran_diri WHERE id_pengunduran=?",[$id]);
$r = $st ? sqlsrv_fetch_array($st,SQLSRV_FETCH_ASSOC) : null;
if (!$r) die("Data tidak ditemukan.");

/* ---------- batasi atasan ke divisinya sendiri ---------- */
$u        = user_login();
$peran    = peran_saya();
$deptSaya = $u['department_id'] ?? null;
if (!in_array($peran, ['hr','admin_it'], true)) {
    // atasan / peran lain: hanya boleh menilai karyawan divisinya
    if (!$deptSaya) {
        die("Akun Anda belum diberi divisi. Hubungi Admin IT. "
          . "<a href='penilaian_saya.php'>Kembali</a>");
    }
    if ((int)($r['department_id'] ?? 0) !== (int)$deptSaya) {
        die("Anda hanya dapat menilai karyawan dari divisi Anda sendiri. "
          . "<a href='penilaian_saya.php'>Kembali</a>");
    }
}

$pesan='';
if ($_SERVER['REQUEST_METHOD']==='POST') {
    $nama    = trim($_POST['atasan_nama'] ?? '');
    $catatan = trim($_POST['atasan_catatan'] ?? '');
    if ($catatan==='') {
        $pesan="<div class='alert alert-danger'>Catatan penilaian wajib diisi.</div>";
    } else {
        // isi penilaian + otomatis ACC
        $up = sqlsrv_query($conn,
            "UPDATE dbo.pengunduran_diri
             SET atasan_nama=?, atasan_catatan=?, penilaian_kerja=?, atasan_pada=GETDATE(), status='DISETUJUI'
             WHERE id_pengunduran=?",
            [$nama?:null, $catatan, $catatan, $id]);

        if ($up === false) {
            $err = sqlsrv_errors();
            $pesan = "<div class='alert alert-danger'>Gagal menyimpan penilaian: "
                   . htmlspecialchars($err[0]['message'] ?? 'error tidak diketahui') . "</div>";
        } else {
            sqlsrv_free_stmt($up);   // WAJIB: bebaskan koneksi sebelum query berikutnya
            $pesan="<div class='alert alert-success'>Penilaian tersimpan & surat resign otomatis <strong>DISETUJUI</strong>.</div>";

            // segarkan data - dgn pengaman, jangan timpa $r kalau gagal
            $st2 = sqlsrv_query($conn,"SELECT * FROM dbo.pengunduran_diri WHERE id_pengunduran=?",[$id]);
            if ($st2 !== false) {
                $baru = sqlsrv_fetch_array($st2, SQLSRV_FETCH_ASSOC);
                if ($baru) $r = $baru;          // hanya timpa kalau benar dapat data
                sqlsrv_free_stmt($st2);
            }
            // kalau refresh gagal, perbarui $r di memori supaya tampilan tetap benar
            $r['status']         = 'DISETUJUI';
            $r['atasan_nama']    = $nama;
            $r['atasan_catatan'] = $catatan;
            $r['penilaian_kerja']= $catatan;
        }
    }
}

// PENGAMAN: pastikan $r selalu array supaya tidak ada warning di tampilan
if (!is_array($r)) {
    die("Data pengunduran diri tidak dapat dimuat. Silakan kembali ke <a href='index.php'>daftar</a>.");
}

$page_title="Penilaian Resign";
include '../template/header.php';
include '../template/sidebar.php';
function h($v){return htmlspecialchars((string)($v??''));}
?>
<main id="main" class="main">
  <div class="pagetitle"><h1>Penilaian Atasan</h1></div>
  <section class="section">
    <?= $pesan ?>
    <div class="card"><div class="card-body">
      <h5 class="card-title"><?= h($r['no_surat'] ?? '—') ?> — <?= h($r['nama_pegawai'] ?? '—') ?></h5>
      <p><span class="badge bg-<?= ($r['status'] ?? '')==='DISETUJUI'?'success':'warning' ?>"><?= h($r['status'] ?? '—') ?></span></p>

      <?php if(($r['status'] ?? '')==='DISETUJUI'): ?>
        <div class="alert alert-success">
          <strong>Sudah dinilai & disetujui.</strong><br>
          Oleh: <?= h($r['atasan_nama']??'—') ?><br>
          Catatan: <?= nl2br(h($r['atasan_catatan'] ?: ($r['penilaian_kerja']??''))) ?>
        </div>
        <a href="penilaian_saya.php" class="btn btn-secondary">Kembali</a>
      <?php else: ?>
        <p class="text-muted small">Isi catatan penilaian karyawan yang resign. Menyimpan akan
           otomatis menyetujui (ACC) surat resign.</p>
        <form method="POST">
          <div class="mb-2"><label class="form-label small">Nama Atasan</label>
            <input type="text" name="atasan_nama" class="form-control" value="<?= h($u['nama_lengkap'] ?? '') ?>" placeholder="Nama atasan penilai"></div>
          <div class="mb-3"><label class="form-label small">Catatan Penilaian</label>
            <textarea name="atasan_catatan" class="form-control" rows="4" required
                      placeholder="catatan kinerja/sikap karyawan selama bekerja"></textarea></div>
          <button class="btn btn-success"><i class="bi bi-check-circle"></i> Simpan & Setujui</button>
          <a href="penilaian_saya.php" class="btn btn-secondary">Batal</a>
        </form>
      <?php endif; ?>
    </div></div>
  </section>
</main>
<?php include '../template/footer.php'; ?>
