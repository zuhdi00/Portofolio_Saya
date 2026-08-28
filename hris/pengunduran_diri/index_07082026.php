<?php
require_once __DIR__ . '/../auth/auth.php';
wajib_izin('modul_hr'); // Asumsi HR yang bisa akses
$page_title = "Data Pengunduran Diri";
include '../template/header.php';
include '../config/koneksi_sqlsrv.php';
include '../template/sidebar.php';

// Cek dan buat tabel jika belum ada
$checkTable = sqlsrv_query($conn, "SELECT OBJECT_ID('dbo.pengunduran_diri') AS id");
$row = sqlsrv_fetch_array($checkTable);
if (empty($row['id'])) {
    $create = "
    CREATE TABLE dbo.pengunduran_diri (
        id_pengunduran INT IDENTITY(1,1) PRIMARY KEY,
        pegawai_id INT NOT NULL,
        tanggal_pengajuan DATE NOT NULL,
        tanggal_efektif DATE NOT NULL,
        alasan NVARCHAR(MAX),
        keterangan NVARCHAR(MAX),
        penilaian_kerja NVARCHAR(MAX),
        dibuat_pada DATETIME DEFAULT GETDATE(),
        dibuat_oleh NVARCHAR(100),
        status VARCHAR(50) DEFAULT 'DIAJUKAN'
    )";
    sqlsrv_query($conn, $create);
} else {
    // Pastikan kolom status ada (migrasi ringan)
    $checkCol = sqlsrv_query($conn, "SELECT COL_LENGTH('dbo.pengunduran_diri', 'status') AS len");
    $rowCol = sqlsrv_fetch_array($checkCol);
    if (empty($rowCol['len'])) {
        sqlsrv_query($conn, "ALTER TABLE dbo.pengunduran_diri ADD status VARCHAR(50) DEFAULT 'DIAJUKAN'");
        sqlsrv_query($conn, "UPDATE dbo.pengunduran_diri SET status = 'DISETUJUI' WHERE status IS NULL");
    }
}

$pesan = '';
// Proses Input Form
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['aksi'] ?? '') === 'simpan') {
    $pegawai_id        = (int)($_POST['pegawai_id'] ?? 0);
    $tanggal_pengajuan = $_POST['tanggal_pengajuan'] ?? '';
    $tanggal_efektif   = $_POST['tanggal_efektif'] ?? '';
    $alasan            = trim($_POST['alasan'] ?? '');
    $keterangan        = trim($_POST['keterangan'] ?? '');
    $penilaian_kerja   = trim($_POST['penilaian_kerja'] ?? '');
    $dibuat_oleh       = $_SESSION['hris_user']['nama_lengkap'] ?? 'System';

    if (!$pegawai_id || !$tanggal_pengajuan || !$tanggal_efektif) {
        $pesan = "<div class='alert alert-danger'>Pilih pegawai, tanggal pengajuan, dan tanggal efektif!</div>";
    } else {
        $sql = "INSERT INTO dbo.pengunduran_diri 
                (pegawai_id, tanggal_pengajuan, tanggal_efektif, alasan, keterangan, penilaian_kerja, dibuat_oleh, status)
                VALUES (?, ?, ?, ?, ?, ?, ?, 'DISETUJUI')";
        $st = sqlsrv_query($conn, $sql, [
            $pegawai_id, $tanggal_pengajuan, $tanggal_efektif, $alasan, $keterangan, $penilaian_kerja, $dibuat_oleh
        ]);
        
        if ($st) {
            $pesan = "<div class='alert alert-success'>Data pengunduran diri berhasil dicatat (Otomatis Disetujui).</div>";
        } else {
            $pesan = "<div class='alert alert-danger'>Gagal menyimpan: " . print_r(sqlsrv_errors(), true) . "</div>";
        }
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id_pengunduran'], $_POST['keputusan'])) {
    $id_pengunduran = (int)$_POST['id_pengunduran'];
    $keputusan = $_POST['keputusan']; // 'setuju' atau 'tolak'
    
    $status_baru = $keputusan === 'setuju' ? 'DISETUJUI' : 'DITOLAK';
    $ket_hr = trim($_POST['keterangan_hr'] ?? '');
    
    $sql = "UPDATE dbo.pengunduran_diri SET status = ?, keterangan = ? WHERE id_pengunduran = ? AND (status = 'DIAJUKAN' OR status IS NULL)";
    $st = sqlsrv_query($conn, $sql, [$status_baru, $ket_hr, $id_pengunduran]);
    
    if ($st) {
        $pesan = "<div class='alert alert-success'>Form pengunduran diri berhasil di-" . ($keputusan === 'setuju' ? 'setujui' : 'tolak') . ".</div>";
    } else {
        $pesan = "<div class='alert alert-danger'>Gagal memproses approval.</div>";
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

// Ambil Riwayat Pengunduran Diri
$riwayat = [];
$rsRiwayat = sqlsrv_query($conn, "
    SELECT pd.*, p.nik, p.nama_peg, d.nama_dept
    FROM dbo.pengunduran_diri pd
    JOIN dbo.pegawai p ON p.id_peg = pd.pegawai_id
    LEFT JOIN dbo.unit_kerja u ON u.id = p.unit_kerja_id
    LEFT JOIN dbo.department d ON d.id_dept = u.department_id
    ORDER BY pd.tanggal_pengajuan DESC
");
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
        <h1>Manajemen Pengunduran Diri</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="/hris/index.php">Home</a></li>
                <li class="breadcrumb-item active">Pengunduran Diri</li>
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
                        <h5 class="card-title">Catat Pengunduran Diri</h5>
                        <form method="POST">
                            <input type="hidden" name="aksi" value="simpan">
                            
                            <div class="mb-3">
                                <label class="form-label fw-bold">Pegawai</label>
                                <select name="pegawai_id" class="form-select select2" required>
                                    <option value="">-- Pilih Pegawai --</option>
                                    <?php foreach ($pegList as $p): ?>
                                        <option value="<?= $p['id_peg'] ?>"><?= h($p['nik'] . ' — ' . $p['nama_peg']) ?> (<?= h($p['nama_dept'] ?? '-') ?>)</option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label fw-bold">Tgl Diajukan</label>
                                    <input type="date" name="tanggal_pengajuan" class="form-control" value="<?= date('Y-m-d') ?>" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label fw-bold">Tgl Efektif</label>
                                    <input type="date" name="tanggal_efektif" class="form-control" required>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-bold">Alasan Resign</label>
                                <textarea name="alasan" class="form-control" rows="2" placeholder="Contoh: Mendapat tawaran di perusahaan lain"></textarea>
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-bold">Keterangan / Notes HR</label>
                                <textarea name="keterangan" class="form-control" rows="2" placeholder="Keterangan tambahan..."></textarea>
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-bold">Penilaian Kerja Terakhir</label>
                                <textarea name="penilaian_kerja" class="form-control" rows="2" placeholder="Sikap, performa, atau evaluasi singkat..."></textarea>
                            </div>

                            <button type="submit" class="btn btn-primary w-100"><i class="bi bi-save"></i> Simpan Data</button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- TABEL RIWAYAT -->
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Riwayat Pengunduran Diri</h5>
                        <div class="table-responsive">
                            <table class="table table-hover table-striped table-sm" id="tblResign">
                                <thead class="table-light">
                                    <tr>
                                        <th>NIK</th>
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
                                    <tr><td colspan="7" class="text-center text-muted py-3">Belum ada data pengunduran diri</td></tr>
                                    <?php else: foreach($riwayat as $r): ?>
                                    <tr>
                                        <td><?= h($r['nik']) ?></td>
                                        <td><?= h($r['nama_peg']) ?></td>
                                        <td><?= h($r['nama_dept'] ?? '—') ?></td>
                                        <td><?= tgl($r['tanggal_pengajuan']) ?></td>
                                        <td><strong><?= tgl($r['tanggal_efektif']) ?></strong></td>
                                        <td><?= badgeStatus($r['status'] ?? 'DIAJUKAN') ?></td>
                                        <td>
                                            <a href="cetak.php?id=<?= $r['id_pengunduran'] ?>" target="_blank" class="btn btn-sm btn-outline-secondary" title="Cetak Form">
                                                <i class="bi bi-printer"></i>
                                            </a>
                                            <?php if (($r['status'] ?? 'DIAJUKAN') === 'DIAJUKAN'): ?>
                                            <div class="mt-1 d-inline-block">
                                                <form method="POST" style="display:inline-block;" onsubmit="return confirm('Yakin ingin menyetujui pengunduran diri ini?');">
                                                    <input type="hidden" name="id_pengunduran" value="<?= $r['id_pengunduran'] ?>">
                                                    <button type="submit" name="keputusan" value="setuju" class="btn btn-sm btn-success" title="Setuju"><i class="bi bi-check-lg"></i></button>
                                                </form>
                                                <form method="POST" style="display:inline-block;" onsubmit="return confirm('Yakin ingin menolak pengunduran diri ini?');">
                                                    <input type="hidden" name="id_pengunduran" value="<?= $r['id_pengunduran'] ?>">
                                                    <button type="submit" name="keputusan" value="tolak" class="btn btn-sm btn-danger" title="Tolak"><i class="bi bi-x-lg"></i></button>
                                                </form>
                                            </div>
                                            <?php endif; ?>
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
