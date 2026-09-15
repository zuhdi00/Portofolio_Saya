<?php
/**
 * s-core/surat_peringatan/edit.php
 * Revisi Surat Peringatan. Hanya peran hr dan admin_it.
 *
 * Setiap penyimpanan menaikkan kolom `revisi`, mencatat siapa & kapan,
 * lalu menyimpan snapshot data lama ke dbo.surat_peringatan_revisi.
 * Nomor surat (no_surat) TIDAK berubah agar arsip tetap tertelusur.
 */
require_once __DIR__ . '/../auth/auth.php';
wajib_peran(['hr']);                     // admin_it otomatis diizinkan
require_once __DIR__ . '/../config/koneksi_sqlsrv.php';

function h($v){ return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8'); }
function tglinput($v){
    if ($v instanceof DateTime) return $v->format('Y-m-d');
    if (!$v) return '';
    $t = strtotime((string)$v); return $t ? date('Y-m-d', $t) : '';
}
function tgljam($v){
    if ($v instanceof DateTime) return $v->format('d-m-Y H:i');
    if (!$v) return '-';
    $t = strtotime((string)$v); return $t ? date('d-m-Y H:i', $t) : '-';
}

$id = (int)($_GET['id'] ?? $_POST['id_sp'] ?? 0);
if (!$id) die('ID surat peringatan tidak valid.');

/* ---------- ambil data saat ini ---------- */
function ambilSp($conn, $id){
    $rs = sqlsrv_query($conn,
        "SELECT s.*, p.nama_peg, p.nik, u.nama_unit
           FROM dbo.surat_peringatan s
           JOIN dbo.pegawai p ON p.id_peg = s.pegawai_id
      LEFT JOIN dbo.unit_kerja u ON u.id = p.unit_kerja_id
          WHERE s.id_sp = ?", [$id]);
    return $rs ? sqlsrv_fetch_array($rs, SQLSRV_FETCH_ASSOC) : null;
}

$f = ambilSp($conn, $id);
if (!$f) die('Data surat peringatan tidak ditemukan.');

$pesan = '';

/* ---------- proses simpan revisi ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $u      = user_login();
    $alasan = trim($_POST['alasan_revisi'] ?? '');

    if ($alasan === '') {
        $pesan = '<div class="alert alert-danger">Alasan revisi wajib diisi.</div>';
    } else {
        /* snapshot data lama */
        $lama = $f;
        foreach ($lama as $k => $v) if ($v instanceof DateTime) $lama[$k] = $v->format('Y-m-d H:i:s');
        $snapshot = json_encode($lama, JSON_UNESCAPED_UNICODE);

        $revisiBaru = (int)($f['revisi'] ?? 0) + 1;

        $data = [
            'tanggal_sp'         => $_POST['tanggal_sp'] ?: date('Y-m-d'),
            'kategori_sp'        => trim($_POST['kategori_sp'] ?? 'SP I'),
            'jenis_pelanggaran'  => trim($_POST['jenis_pelanggaran'] ?? ''),
            'kronologi'          => trim($_POST['kronologi'] ?? ''),
            'dasar_pelanggaran'  => trim($_POST['dasar_pelanggaran'] ?? ''),
            'tindakan'           => trim($_POST['tindakan'] ?? ''),
            'tempat_pelanggaran' => trim($_POST['tempat_pelanggaran'] ?? ''),
            'pukul_pelanggaran'  => trim($_POST['pukul_pelanggaran'] ?? ''),
            'saksi_bukti'        => trim($_POST['saksi_bukti'] ?? ''),
            'kerugian_material'  => trim($_POST['kerugian_material'] ?? ''),
            'lama_skorsing'      => trim($_POST['lama_skorsing'] ?? ''),
            'status_pemutihan'   => isset($_POST['status_pemutihan']) ? 1 : 0,
        ];

        if ($data['jenis_pelanggaran'] === '') {
            $pesan = '<div class="alert alert-danger">Jenis pelanggaran wajib diisi.</div>';
        } else {
            $ok1 = sqlsrv_query($conn,
                "UPDATE dbo.surat_peringatan SET
                    tanggal_sp=?, kategori_sp=?, status_pemutihan=?, jenis_pelanggaran=?,
                    kronologi=?, dasar_pelanggaran=?, tindakan=?,
                    tempat_pelanggaran=?, pukul_pelanggaran=?, saksi_bukti=?,
                    kerugian_material=?, lama_skorsing=?,
                    revisi=?, diubah_oleh=?, diubah_pada=GETDATE()
                 WHERE id_sp=?",
                [$data['tanggal_sp'], $data['kategori_sp'], $data['status_pemutihan'],
                 $data['jenis_pelanggaran'], $data['kronologi'] ?: null,
                 $data['dasar_pelanggaran'] ?: null, $data['tindakan'] ?: null,
                 $data['tempat_pelanggaran'] ?: null, $data['pukul_pelanggaran'] ?: null,
                 $data['saksi_bukti'] ?: null, $data['kerugian_material'] ?: null,
                 $data['lama_skorsing'] ?: null,
                 $revisiBaru, $u['nama_lengkap'] ?? null, $id]);

            $ok2 = sqlsrv_query($conn,
                "INSERT INTO dbo.surat_peringatan_revisi
                    (id_sp, revisi_ke, alasan_revisi, data_sebelum, diubah_oleh, peran_pengubah)
                 VALUES (?,?,?,?,?,?)",
                [$id, $revisiBaru, $alasan, $snapshot,
                 $u['nama_lengkap'] ?? null, peran_saya()]);

            if ($ok1 && $ok2) {
                $pesan = '<div class="alert alert-success">Surat berhasil direvisi menjadi '
                       . '<strong>Revisi ke-' . $revisiBaru . '</strong>. '
                       . 'Kop formulir akan menampilkan No. Revisi <strong>'
                       . sprintf('%02d', $revisiBaru) . '</strong> dan tanggal hari ini.</div>';
                $f = ambilSp($conn, $id);   // muat ulang data terbaru
            } else {
                $pesan = '<div class="alert alert-danger">Gagal menyimpan revisi. '
                       . 'Pastikan patch <code>32_surat_peringatan_revisi.sql</code> sudah dijalankan.</div>';
            }
        }
    }
}

/* ---------- riwayat revisi ---------- */
$riwayat = [];
$rsR = sqlsrv_query($conn,
    "SELECT revisi_ke, alasan_revisi, diubah_oleh, peran_pengubah, diubah_pada
       FROM dbo.surat_peringatan_revisi WHERE id_sp=? ORDER BY revisi_ke DESC", [$id]);
while ($rsR && ($r = sqlsrv_fetch_array($rsR, SQLSRV_FETCH_ASSOC))) $riwayat[] = $r;

$page_title = 'Revisi Surat Peringatan';
include '../template/header.php';
include '../template/sidebar.php';
?>
<main id="main" class="main">
  <div class="pagetitle">
    <h1>Revisi Surat Peringatan</h1>
    <nav><ol class="breadcrumb">
      <li class="breadcrumb-item"><a href="../index.php">Home</a></li>
      <li class="breadcrumb-item"><a href="index.php">Surat Peringatan</a></li>
      <li class="breadcrumb-item active"><?= h($f['no_surat']) ?></li>
    </ol></nav>
  </div>

  <section class="section">
    <div class="row">
      <div class="col-lg-8">
        <div class="card">
          <div class="card-body">
            <h5 class="card-title">
              <?= h($f['no_surat']) ?>
              <span class="badge bg-dark">Revisi <?= sprintf('%02d', (int)($f['revisi'] ?? 0)) ?></span>
            </h5>
            <?= $pesan ?>

            <div class="alert alert-light border">
              <strong><?= h($f['nama_peg']) ?></strong> &middot; <?= h($f['nik']) ?>
              &middot; <?= h($f['nama_unit']) ?><br>
              <small class="text-muted">
                Dibuat oleh <?= h($f['dibuat_oleh']) ?: '-' ?> pada <?= tgljam($f['dibuat_pada'] ?? null) ?>
                <?php if (!empty($f['diubah_pada'])): ?>
                  &middot; terakhir direvisi oleh <?= h($f['diubah_oleh']) ?: '-' ?>
                  pada <?= tgljam($f['diubah_pada']) ?>
                <?php endif; ?>
              </small>
            </div>

            <form method="POST">
              <input type="hidden" name="id_sp" value="<?= (int)$id ?>">

              <div class="row">
                <div class="col-md-4 mb-2">
                  <label class="form-label">Tanggal Pelanggaran</label>
                  <input type="date" name="tanggal_sp" class="form-control"
                         value="<?= h(tglinput($f['tanggal_sp'])) ?>" required>
                </div>
                <div class="col-md-4 mb-2">
                  <label class="form-label">Kategori SP</label>
                  <select name="kategori_sp" class="form-select">
                    <?php foreach (['SP I','SP II','SP III'] as $k): ?>
                      <option value="<?= $k ?>" <?= trim((string)$f['kategori_sp'])===$k?'selected':'' ?>><?= $k ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="col-md-4 mb-2">
                  <label class="form-label">Status</label>
                  <div class="form-check mt-2">
                    <input class="form-check-input" type="checkbox" name="status_pemutihan"
                           id="pemutihan" <?= !empty($f['status_pemutihan'])?'checked':'' ?>>
                    <label class="form-check-label" for="pemutihan">Pemutihan / SP bersih</label>
                  </div>
                </div>
              </div>

              <div class="mb-2">
                <label class="form-label">Jenis Pelanggaran</label>
                <input name="jenis_pelanggaran" class="form-control" maxlength="255"
                       value="<?= h($f['jenis_pelanggaran']) ?>" required>
              </div>
              <div class="mb-2">
                <label class="form-label">Kronologi Kejadian</label>
                <textarea name="kronologi" class="form-control" rows="3"><?= h($f['kronologi']) ?></textarea>
              </div>
              <div class="mb-2">
                <label class="form-label">Dasar Pelanggaran</label>
                <textarea name="dasar_pelanggaran" class="form-control" rows="2"><?= h($f['dasar_pelanggaran']) ?></textarea>
              </div>

              <div class="row">
                <div class="col-md-6 mb-2">
                  <label class="form-label">Tempat Kejadian</label>
                  <input name="tempat_pelanggaran" class="form-control" maxlength="150"
                         value="<?= h($f['tempat_pelanggaran'] ?? '') ?>">
                </div>
                <div class="col-md-6 mb-2">
                  <label class="form-label">Pukul (WIB)</label>
                  <input name="pukul_pelanggaran" class="form-control" maxlength="10"
                         value="<?= h($f['pukul_pelanggaran'] ?? '') ?>">
                </div>
                <div class="col-md-6 mb-2">
                  <label class="form-label">Saksi / Bukti</label>
                  <input name="saksi_bukti" class="form-control" maxlength="200"
                         value="<?= h($f['saksi_bukti'] ?? '') ?>">
                </div>
                <div class="col-md-6 mb-2">
                  <label class="form-label">Kerugian Material</label>
                  <input name="kerugian_material" class="form-control" maxlength="100"
                         value="<?= h($f['kerugian_material'] ?? '') ?>">
                </div>
                <div class="col-md-6 mb-2">
                  <label class="form-label">Lama Skorsing</label>
                  <input name="lama_skorsing" class="form-control" maxlength="50"
                         value="<?= h($f['lama_skorsing'] ?? '') ?>">
                </div>
                <div class="col-md-6 mb-2">
                  <label class="form-label">Tindakan / Catatan</label>
                  <input name="tindakan" class="form-control" maxlength="500"
                         value="<?= h($f['tindakan']) ?>">
                </div>
              </div>

              <div class="mb-3">
                <label class="form-label text-danger">Alasan Revisi <span>*</span></label>
                <textarea name="alasan_revisi" class="form-control" rows="2" required
                          placeholder="Contoh: koreksi tanggal kejadian sesuai log mesin absensi"></textarea>
                <small class="text-muted">Wajib diisi. Tersimpan sebagai jejak audit dan tidak bisa dihapus dari halaman ini.</small>
              </div>

              <button class="btn btn-primary"><i class="bi bi-save"></i> Simpan Revisi</button>
              <a class="btn btn-outline-secondary" href="cetak.php?id=<?= (int)$id ?>" target="_blank">
                <i class="bi bi-printer"></i> Cetak
              </a>
              <a class="btn btn-link" href="index.php">Kembali</a>
            </form>
          </div>
        </div>
      </div>

      <div class="col-lg-4">
        <div class="card">
          <div class="card-body">
            <h5 class="card-title">Riwayat Revisi</h5>
            <?php if (!$riwayat): ?>
              <p class="text-muted mb-0">Belum pernah direvisi.</p>
            <?php else: ?>
              <ul class="list-group list-group-flush">
                <?php foreach ($riwayat as $r): ?>
                  <li class="list-group-item px-0">
                    <span class="badge bg-dark">Rev <?= sprintf('%02d', (int)$r['revisi_ke']) ?></span>
                    <small class="text-muted"><?= tgljam($r['diubah_pada']) ?></small><br>
                    <?= h($r['alasan_revisi']) ?><br>
                    <small class="text-muted">
                      oleh <?= h($r['diubah_oleh']) ?: '-' ?> (<?= h($r['peran_pengubah']) ?>)
                    </small>
                  </li>
                <?php endforeach; ?>
              </ul>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>
  </section>
</main>
<?php include '../template/footer.php'; ?>
