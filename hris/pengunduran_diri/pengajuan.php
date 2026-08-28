<?php
/**
 * pengunduran_diri/pengajuan.php
 * Form pengajuan pengunduran diri oleh User / Admin Divisi.
 */
require_once __DIR__ . '/../auth/auth.php';
wajib_login(); // semua user boleh mengajukan
$page_title = "Pengajuan Pengunduran Diri";
include '../template/header.php';
include '../config/koneksi_sqlsrv.php';
include '../template/sidebar.php';

$pesan = '';

// Proses Submit
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['aksi'] ?? '') === 'ajukan') {
    $pegawai_id        = (int)($_POST['pegawai_id'] ?? 0);
    $tanggal_pengajuan = $_POST['tanggal_pengajuan'] ?? '';
    $tanggal_efektif   = $_POST['tanggal_efektif'] ?? '';
    $alasan            = trim($_POST['alasan'] ?? '');
    // Keterangan & penilaian kerja dikosongkan karena ini pengajuan awal
    $keterangan        = ''; 
    $penilaian_kerja   = '';
    $dibuat_oleh       = $_SESSION['hris_user']['nama_lengkap'] ?? 'User';

    if (!$pegawai_id || !$tanggal_pengajuan || !$tanggal_efektif || empty($alasan)) {
        $pesan = "<div class='alert alert-danger'>Semua field wajib diisi.</div>";
    } else {
        $sql = "INSERT INTO dbo.pengunduran_diri 
                (pegawai_id, tanggal_pengajuan, tanggal_efektif, alasan, keterangan, penilaian_kerja, dibuat_oleh, status)
                VALUES (?, ?, ?, ?, ?, ?, ?, 'DIAJUKAN')";
        $st = sqlsrv_query($conn, $sql, [
            $pegawai_id, $tanggal_pengajuan, $tanggal_efektif, $alasan, $keterangan, $penilaian_kerja, $dibuat_oleh
        ]);
        
        if ($st) {
            $pesan = "<div class='alert alert-success'>Pengajuan pengunduran diri berhasil dikirim. Menunggu proses HR.</div>";
        } else {
            $pesan = "<div class='alert alert-danger'>Gagal menyimpan pengajuan: " . print_r(sqlsrv_errors(), true) . "</div>";
        }
    }
}

// Ambil Daftar Pegawai (Aktif) untuk Dropdown
$pegList = [];
$rs = sqlsrv_query($conn, "
    SELECT p.id_peg, p.nik, p.nama_peg, d.nama_dept 
    FROM dbo.pegawai p 
    LEFT JOIN dbo.unit_kerja u ON u.id = p.unit_kerja_id
    LEFT JOIN dbo.department d ON d.id_dept = u.department_id
    WHERE p.is_aktif = 1 
    ORDER BY p.nama_peg
");
while ($r = sqlsrv_fetch_array($rs, SQLSRV_FETCH_ASSOC)) {
    $pegList[] = $r;
}

// Ambil pengajuan terakhir yang dibuat oleh user ini
$nama_user = $_SESSION['hris_user']['nama_lengkap'] ?? '';
$riwayat = [];
$rsRiwayat = sqlsrv_query($conn, "
    SELECT TOP 20 pd.*, p.nik, p.nama_peg, d.nama_dept
    FROM dbo.pengunduran_diri pd
    JOIN dbo.pegawai p ON p.id_peg = pd.pegawai_id
    LEFT JOIN dbo.unit_kerja u ON u.id = p.unit_kerja_id
    LEFT JOIN dbo.department d ON d.id_dept = u.department_id
    WHERE pd.dibuat_oleh = ?
    ORDER BY pd.tanggal_pengajuan DESC
", [$nama_user]);
if ($rsRiwayat) {
    while ($r = sqlsrv_fetch_array($rsRiwayat, SQLSRV_FETCH_ASSOC)) {
        $riwayat[] = $r;
    }
}

function h($v){ return htmlspecialchars((string)($v??'')); }
function tgl($v){ return $v instanceof DateTime ? $v->format('d-m-Y') : '—'; }
function badgeStatus($s){
    if (strpos($s, 'DISETUJUI') !== false) return "<span class='badge bg-success'>$s</span>";
    if (strpos($s, 'DITOLAK') !== false) return "<span class='badge bg-danger'>$s</span>";
    return "<span class='badge bg-warning'>$s</span>";
}
?>

<main id="main" class="main">
    <div class="pagetitle">
        <h1>Pengajuan Pengunduran Diri</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="/hris/index.php">Home</a></li>
                <li class="breadcrumb-item active">Pengajuan Resign</li>
            </ol>
        </nav>
    </div>

    <section class="section">
        <?= $pesan ?>
        <div class="row">
            <!-- FORM PENCATATAN -->
            <div class="col-lg-4">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Ajukan Pengunduran Diri</h5>
                        <form method="POST">
                            <input type="hidden" name="aksi" value="ajukan">
                            
                            <div class="mb-3">
                                <label class="form-label fw-bold">Pegawai</label>
                                <select name="pegawai_id" class="form-select select2" required>
                                    <option value="">-- Cari Pegawai --</option>
                                    <?php foreach ($pegList as $p): ?>
                                        <option value="<?= $p['id_peg'] ?>"><?= h($p['nik'] . ' — ' . $p['nama_peg']) ?> (<?= h($p['nama_dept'] ?? '-') ?>)</option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-bold">Tanggal Diajukan</label>
                                <input type="date" name="tanggal_pengajuan" class="form-control" value="<?= date('Y-m-d') ?>" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-bold">Tanggal Efektif Resign</label>
                                <input type="date" name="tanggal_efektif" class="form-control" required>
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-bold">Alasan Pengunduran Diri</label>
                                <textarea name="alasan" class="form-control" rows="3" required placeholder="Jelaskan alasan secara singkat..."></textarea>
                            </div>

                            <button type="submit" class="btn btn-primary w-100"><i class="bi bi-send"></i> Kirim Pengajuan</button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- TABEL RIWAYAT -->
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Pengajuan Terakhir</h5>
                        <div class="table-responsive">
                            <table class="table table-hover table-striped table-sm" id="tblResign">
                                <thead class="table-light">
                                    <tr>
                                        <th>Nama Pegawai</th>
                                        <th>Divisi</th>
                                        <th>Tgl Pengajuan</th>
                                        <th>Tgl Efektif</th>
                                        <th>Status</th>
                                        <th>Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if(empty($riwayat)): ?>
                                    <tr><td colspan="6" class="text-center text-muted py-3">Belum ada pengajuan.</td></tr>
                                    <?php else: foreach($riwayat as $r): ?>
                                    <tr>
                                        <td><?= h($r['nama_peg']) ?></td>
                                        <td><?= h($r['nama_dept'] ?? '—') ?></td>
                                        <td><?= tgl($r['tanggal_pengajuan']) ?></td>
                                        <td><strong><?= tgl($r['tanggal_efektif']) ?></strong></td>
                                        <td><?= badgeStatus($r['status'] ?? 'DIAJUKAN') ?></td>
                                        <td>
                                            <a href="cetak.php?id=<?= $r['id_pengunduran'] ?>" target="_blank" class="btn btn-sm btn-outline-secondary" title="Cetak Form">
                                                <i class="bi bi-printer"></i>
                                            </a>
                                        </td>
                                    </tr>
                                    <?php endforeach; endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </section>
</main>

<!-- Select2 -->
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" />
<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<script>
$(document).ready(function() {
    $('.select2').select2({
        theme: 'bootstrap-5',
        placeholder: '-- Cari / Pilih Pegawai --'
    });
});
</script>

<?php include '../template/footer.php'; ?>
