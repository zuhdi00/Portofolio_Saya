<?php
/**
 * pengunduran_diri/kelola_klasifikasi.php
 * Kelola master klasifikasi resign (tambah / edit / hapus / aktif-nonaktif).
 * Khusus HRD & Admin IT.
 */
require_once __DIR__ . '/../auth/auth.php';
wajib_izin('modul_hr');
require_once __DIR__ . '/../config/koneksi_sqlsrv.php';

$pesan = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $aksi = $_POST['aksi'] ?? '';

    if ($aksi === 'tambah') {
        $kode  = strtoupper(trim($_POST['kode'] ?? ''));
        $label = trim($_POST['label'] ?? '');
        $ket   = trim($_POST['keterangan'] ?? '');
        $urut  = (int)($_POST['urutan'] ?? 99);
        // kode: huruf, angka, underscore saja
        $kode = preg_replace('/[^A-Z0-9_]/', '_', $kode);

        if ($kode === '' || $label === '') {
            $pesan = "<div class='alert alert-danger'>Kode dan label wajib diisi.</div>";
        } else {
            $st = sqlsrv_query($conn,
                "INSERT INTO dbo.klasifikasi_resign (kode,label,keterangan,urutan) VALUES (?,?,?,?)",
                [$kode,$label,$ket?:null,$urut]);
            if ($st === false) {
                $e = sqlsrv_errors();
                $pesan = "<div class='alert alert-danger'>Gagal menambah (kode mungkin sudah dipakai). "
                       . htmlspecialchars($e[0]['message'] ?? '') . "</div>";
            } else { sqlsrv_free_stmt($st);
                $pesan = "<div class='alert alert-success'>Klasifikasi <strong>".htmlspecialchars($kode)."</strong> ditambahkan.</div>"; }
        }
    }
    elseif ($aksi === 'ubah') {
        $id    = (int)($_POST['id_klasifikasi'] ?? 0);
        $label = trim($_POST['label'] ?? '');
        $ket   = trim($_POST['keterangan'] ?? '');
        $urut  = (int)($_POST['urutan'] ?? 99);
        if ($id && $label !== '') {
            $st = sqlsrv_query($conn,
                "UPDATE dbo.klasifikasi_resign SET label=?, keterangan=?, urutan=? WHERE id_klasifikasi=?",
                [$label,$ket?:null,$urut,$id]);
            if ($st !== false) { sqlsrv_free_stmt($st);
                $pesan = "<div class='alert alert-success'>Klasifikasi diperbarui.</div>"; }
        }
    }
    elseif ($aksi === 'toggle') {
        $id = (int)($_POST['id_klasifikasi'] ?? 0);
        if ($id) {
            $st = sqlsrv_query($conn,"UPDATE dbo.klasifikasi_resign SET is_aktif = 1 - is_aktif WHERE id_klasifikasi=?",[$id]);
            if ($st !== false) { sqlsrv_free_stmt($st);
                $pesan = "<div class='alert alert-info'>Status klasifikasi diubah.</div>"; }
        }
    }
    elseif ($aksi === 'hapus') {
        $id = (int)($_POST['id_klasifikasi'] ?? 0);
        // cek dipakai atau tidak
        $rsK = sqlsrv_query($conn,"SELECT kode FROM dbo.klasifikasi_resign WHERE id_klasifikasi=?",[$id]);
        $kode = ($rsK && ($x=sqlsrv_fetch_array($rsK,SQLSRV_FETCH_ASSOC))) ? $x['kode'] : null;
        if ($rsK) sqlsrv_free_stmt($rsK);

        $dipakai = 0;
        if ($kode) {
            $rsD = sqlsrv_query($conn,"SELECT COUNT(*) n FROM dbo.pengunduran_diri WHERE klasifikasi=?",[$kode]);
            if ($rsD && ($y=sqlsrv_fetch_array($rsD,SQLSRV_FETCH_ASSOC))) $dipakai = (int)$y['n'];
            if ($rsD) sqlsrv_free_stmt($rsD);
        }

        if ($dipakai > 0) {
            $pesan = "<div class='alert alert-warning'>Tidak bisa dihapus: klasifikasi <strong>"
                   . htmlspecialchars($kode) . "</strong> masih dipakai $dipakai data pengunduran diri. "
                   . "Nonaktifkan saja agar tidak muncul di pilihan baru.</div>";
        } elseif ($id) {
            $st = sqlsrv_query($conn,"DELETE FROM dbo.klasifikasi_resign WHERE id_klasifikasi=?",[$id]);
            if ($st !== false) { sqlsrv_free_stmt($st);
                $pesan = "<div class='alert alert-success'>Klasifikasi dihapus.</div>"; }
        }
    }
}

/* daftar */
$rows = sqlsrv_query($conn,
  "SELECT k.*, (SELECT COUNT(*) FROM dbo.pengunduran_diri r WHERE r.klasifikasi = k.kode) AS dipakai
   FROM dbo.klasifikasi_resign k ORDER BY k.urutan, k.kode");

$page_title = "Kelola Klasifikasi Resign";
include '../template/header.php';
include '../template/sidebar.php';
function h($v){return htmlspecialchars((string)($v??''));}
?>
<main id="main" class="main">
  <div class="pagetitle"><h1>Kelola Klasifikasi Resign</h1>
    <nav><ol class="breadcrumb"><li class="breadcrumb-item"><a href="index.php">Pengunduran Diri</a></li>
    <li class="breadcrumb-item active">Klasifikasi</li></ol></nav></div>

  <section class="section">
    <?= $pesan ?>
    <div class="row">

      <!-- TAMBAH -->
      <div class="col-lg-4">
        <div class="card"><div class="card-body">
          <h5 class="card-title">Tambah Klasifikasi</h5>
          <form method="POST">
            <input type="hidden" name="aksi" value="tambah">
            <div class="mb-2"><label class="form-label small">Kode</label>
              <input type="text" name="kode" class="form-control" required
                     placeholder="mis. TRANSFER" style="text-transform:uppercase">
              <small class="text-muted">Huruf besar, tanpa spasi (otomatis dirapikan).</small></div>
            <div class="mb-2"><label class="form-label small">Label (yang tampil)</label>
              <input type="text" name="label" class="form-control" required placeholder="mis. Transfer Antar Unit"></div>
            <div class="mb-2"><label class="form-label small">Keterangan</label>
              <input type="text" name="keterangan" class="form-control" placeholder="penjelasan singkat"></div>
            <div class="mb-3"><label class="form-label small">Urutan Tampil</label>
              <input type="number" name="urutan" class="form-control" value="10"></div>
            <button class="btn btn-primary w-100"><i class="bi bi-plus-circle"></i> Tambah</button>
          </form>
        </div></div>
      </div>

      <!-- DAFTAR -->
      <div class="col-lg-8">
        <div class="card"><div class="card-body">
          <h5 class="card-title">Daftar Klasifikasi</h5>
          <div class="table-responsive">
          <table class="table table-hover align-middle">
            <thead class="table-light"><tr>
              <th>Kode</th><th>Label & Keterangan</th><th style="width:70px">Urut</th>
              <th>Dipakai</th><th>Status</th><th style="width:130px">Aksi</th>
            </tr></thead>
            <tbody>
            <?php $ada=false; while($r=sqlsrv_fetch_array($rows,SQLSRV_FETCH_ASSOC)): $ada=true; ?>
              <tr>
                <form method="POST">
                <input type="hidden" name="aksi" value="ubah">
                <input type="hidden" name="id_klasifikasi" value="<?= $r['id_klasifikasi'] ?>">
                <td><code><?= h($r['kode']) ?></code></td>
                <td>
                  <input type="text" name="label" class="form-control form-control-sm mb-1" value="<?= h($r['label']) ?>">
                  <input type="text" name="keterangan" class="form-control form-control-sm" value="<?= h($r['keterangan']) ?>" placeholder="keterangan">
                </td>
                <td><input type="number" name="urutan" class="form-control form-control-sm" value="<?= (int)$r['urutan'] ?>"></td>
                <td class="text-center"><?= (int)$r['dipakai'] ?></td>
                <td><?= $r['is_aktif'] ? '<span class="badge bg-success">aktif</span>' : '<span class="badge bg-secondary">nonaktif</span>' ?></td>
                <td>
                  <button class="btn btn-sm btn-outline-primary" title="simpan"><i class="bi bi-save"></i></button>
                </form>
                  <form method="POST" class="d-inline">
                    <input type="hidden" name="aksi" value="toggle">
                    <input type="hidden" name="id_klasifikasi" value="<?= $r['id_klasifikasi'] ?>">
                    <button class="btn btn-sm btn-outline-secondary" title="aktif/nonaktif"><i class="bi bi-power"></i></button>
                  </form>
                  <form method="POST" class="d-inline"
                        onsubmit="return confirm('Hapus klasifikasi <?= h($r['kode']) ?>?')">
                    <input type="hidden" name="aksi" value="hapus">
                    <input type="hidden" name="id_klasifikasi" value="<?= $r['id_klasifikasi'] ?>">
                    <button class="btn btn-sm btn-outline-danger" title="hapus"><i class="bi bi-trash"></i></button>
                  </form>
                </td>
              </tr>
            <?php endwhile; if(!$ada): ?>
              <tr><td colspan="6" class="text-center text-muted py-4">Belum ada klasifikasi.</td></tr>
            <?php endif; ?>
            </tbody>
          </table>
          </div>
          <small class="text-muted">Klasifikasi yang sudah dipakai data resign tidak bisa dihapus —
            nonaktifkan saja agar tidak muncul di pilihan baru.</small>
        </div></div>
      </div>
    </div>
  </section>
</main>
<?php include '../template/footer.php'; ?>
