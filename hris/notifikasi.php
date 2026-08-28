<?php
require_once __DIR__ . '/auth/auth.php';
wajib_login();

include 'template/header.php';
include 'config/koneksi_sqlsrv.php';
include 'template/sidebar.php';

$__u = $_SESSION['hris_user'] ?? null;
if (!$__u) {
    echo "Sesi tidak valid.";
    exit;
}

// Proses mark all as read
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_all'])) {
    $sql_update = "UPDATE dbo.hris_notifications SET is_read = 1 WHERE user_id = ?";
    sqlsrv_query($conn, $sql_update, [$__u['id_user']]);
    
    // Redirect supaya mencegah resubmit
    echo "<script>window.location.href='notifikasi.php';</script>";
    exit;
}

// Ambil semua notifikasi (limit 100 terakhir)
$sql_list = "SELECT TOP 100 id, judul, pesan, link, is_read, created_at 
             FROM dbo.hris_notifications 
             WHERE user_id = ? 
             ORDER BY created_at DESC";
$st_l = sqlsrv_query($conn, $sql_list, [$__u['id_user']]);
$notif_items = [];
if ($st_l) {
    while ($r = sqlsrv_fetch_array($st_l, SQLSRV_FETCH_ASSOC)) {
        $r['virtual'] = false;
        $notif_items[] = $r;
    }
}

// Pengajuan pending ditampilkan juga di halaman View all.
$__peran = $__u['peran'] ?? '';
if (in_array($__peran, ['hr', 'admin_it'], true)) {
    $pendingResign = sqlsrv_query($conn,
        "SELECT id_pengunduran, no_surat, nama_pegawai, dibuat_pada
         FROM dbo.pengunduran_diri
         WHERE status IN ('DIAJUKAN', 'BERKAS_MASUK')
         ORDER BY dibuat_pada DESC, id_pengunduran DESC");
    if ($pendingResign) {
        while ($r = sqlsrv_fetch_array($pendingResign, SQLSRV_FETCH_ASSOC)) {
            $notif_items[] = [
                'id' => null,
                'judul' => 'Pengunduran Diri Menunggu Review',
                'pesan' => ($r['no_surat'] ?: 'Pengajuan baru') . ' - ' . ($r['nama_pegawai'] ?: 'Nama tidak tersedia'),
                'link' => BASE_URL . '/hris/pengunduran_diri/index.php',
                'is_read' => 0,
                'created_at' => $r['dibuat_pada'],
                'virtual' => true,
            ];
        }
    }

    $pendingLembur = sqlsrv_query($conn,
        "SELECT id_form, no_form, dibuat_pada
         FROM dbo.lembur_form
         WHERE status = 'DIAJUKAN'
         ORDER BY dibuat_pada DESC, id_form DESC");
    if ($pendingLembur) {
        while ($r = sqlsrv_fetch_array($pendingLembur, SQLSRV_FETCH_ASSOC)) {
            $notif_items[] = [
                'id' => null,
                'judul' => 'Pengajuan Lembur Menunggu Review',
                'pesan' => ($r['no_form'] ?: 'Form lembur baru') . ' masih dalam pengajuan.',
                'link' => BASE_URL . '/hris/lembur/approval_hr.php',
                'is_read' => 0,
                'created_at' => $r['dibuat_pada'],
                'virtual' => true,
            ];
        }
    }
}

usort($notif_items, function ($left, $right) {
    $leftTime = $left['created_at'] instanceof DateTime ? $left['created_at']->getTimestamp() : 0;
    $rightTime = $right['created_at'] instanceof DateTime ? $right['created_at']->getTimestamp() : 0;
    return $rightTime <=> $leftTime;
});
$notif_items = array_slice($notif_items, 0, 100);
?>

<main id="main" class="main">

    <div class="pagetitle d-flex justify-content-between align-items-center">
        <div>
            <h1>Semua Notifikasi</h1>
            <nav>
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="index.php">Home</a></li>
                    <li class="breadcrumb-item active">Notifikasi</li>
                </ol>
            </nav>
        </div>
        <form method="POST">
            <button type="submit" name="mark_all" class="btn btn-sm btn-outline-primary">
                <i class="bi bi-check-all"></i> Tandai Semua Dibaca
            </button>
        </form>
    </div><!-- End Page Title -->

    <section class="section">
        <div class="row">
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-body pt-3">
                        <ul class="list-group list-group-flush">
                            <?php if (empty($notif_items)): ?>
                                <li class="list-group-item text-center text-muted">
                                    Belum ada notifikasi.
                                </li>
                            <?php else: ?>
                                <?php foreach ($notif_items as $n): ?>
                                    <li class="list-group-item <?= $n['is_read'] == 0 ? 'list-group-item-light fw-bold' : '' ?>">
                                        <div class="d-flex w-100 justify-content-between">
                                            <h5 class="mb-1">
                                                <?php if ($n['is_read'] == 0): ?>
                                                    <span class="badge bg-danger rounded-circle p-1 me-1"><span class="visually-hidden">Unread</span></span>
                                                <?php endif; ?>
                                                <a href="<?= $n['virtual'] ? htmlspecialchars($n['link']) : BASE_URL . '/hris/notifikasi_read.php?id=' . (int)$n['id'] ?>" class="text-decoration-none">
                                                    <?= htmlspecialchars($n['judul']) ?>
                                                </a>
                                            </h5>
                                            <small><?= $n['created_at']->format('Y-m-d H:i') ?></small>
                                        </div>
                                        <p class="mb-1 text-muted"><?= htmlspecialchars($n['pesan']) ?></p>
                                    </li>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </section>

</main>

<?php include 'template/footer.php'; ?>
