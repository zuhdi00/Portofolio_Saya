<?php
require_once __DIR__ . '/auth/auth.php';
$page_title = 'Contact EDP';
include __DIR__ . '/template/header.php';
include __DIR__ . '/template/sidebar.php';

/* ---------- daftar kontak EDP ----------
   wa   : nomor format internasional tanpa + dan tanpa spasi (untuk wa.me)
   telp : tampilan nomor
   mail : opsional
*/
$KONTAK = [
    [
        'nama'  => 'Zuhdi Abdillah Hidayat',
        'peran' => 'EDP Staff',
        'telp'  => '+62 878-5327-8085',
        'wa'    => '6287853278085',
        'mail'  => 'zuhdiabdillahhidayat@gmail.com',
        'web'   => null,
        'warna' => 'primary',
    ],
    [
        'nama'  => 'Christian',
        'peran' => 'EDP Staff',
        'telp'  => '+62 878-6989-8117',
        'wa'    => '6287869898117',
        'mail'  => null,
        'web'   => null,
        'warna' => 'success',
    ],
    [
        'nama'  => 'Prio',
        'peran' => 'Head of EDP',
        'telp'  => '+62 856-3320-345',
        'wa'    => '628563320345',
        'mail'  => null,
        'web'   => null,
        'warna' => 'warning',
    ],
];
function h($v){return htmlspecialchars((string)($v??''));}
?>

<main id="main" class="main">
    <div class="pagetitle">
        <h1>Contact EDP</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?php echo BASE_URL; ?>/s-core/index.php">Home</a></li>
                <li class="breadcrumb-item active">Contact EDP</li>
            </ol>
        </nav>
    </div>

    <section class="section">
        <div class="row">
            <div class="col-lg-10">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title"><i class="bi bi-headset me-2"></i>Kontak Departemen EDP</h5>
                        <p class="text-muted">
                            Hubungi kontak berikut untuk bantuan aplikasi, jaringan, perangkat,
                            atau kebutuhan teknis lainnya. Klik tombol WhatsApp untuk chat langsung.
                        </p>

                        <div class="row g-3 mt-2">
                        <?php foreach ($KONTAK as $k): ?>
                            <div class="col-md-6 col-xl-4">
                                <div class="border rounded p-3 h-100">
                                    <div class="d-flex align-items-center gap-3 mb-3">
                                        <div class="rounded-circle bg-<?= h($k['warna']) ?> text-white d-flex align-items-center justify-content-center flex-shrink-0"
                                             style="width:52px;height:52px;">
                                            <i class="bi bi-person fs-4"></i>
                                        </div>
                                        <div>
                                            <h6 class="mb-0"><?= h($k['nama']) ?></h6>
                                            <small class="text-muted"><?= h($k['peran']) ?></small>
                                        </div>
                                    </div>

                                    <div class="mb-2">
                                        <i class="bi bi-telephone me-1 text-muted"></i>
                                        <a href="tel:<?= h(str_replace([' ','-'],'',$k['telp'])) ?>" class="text-decoration-none">
                                            <?= h($k['telp']) ?>
                                        </a>
                                    </div>

                                    <?php if ($k['mail']): ?>
                                    <div class="mb-2 text-truncate">
                                        <i class="bi bi-envelope me-1 text-muted"></i>
                                        <a href="mailto:<?= h($k['mail']) ?>" class="text-decoration-none">
                                            <?= h($k['mail']) ?>
                                        </a>
                                    </div>
                                    <?php endif; ?>

                                    <?php if ($k['web']): ?>
                                    <div class="mb-3">
                                        <i class="bi bi-globe me-1 text-muted"></i>
                                        <a href="<?= h($k['web']) ?>" target="_blank" rel="noopener" class="text-decoration-none">
                                            Portofolio
                                        </a>
                                    </div>
                                    <?php endif; ?>

                                    <a href="https://wa.me/<?= h($k['wa']) ?>?text=<?= rawurlencode('Halo Bapak '.$k['nama'].', saya butuh bantuan terkait Aplikasi Website S-CORE di PT. Supracor Sejahtera.') ?>"
                                       target="_blank" rel="noopener"
                                       class="btn btn-success btn-sm w-100 mt-1">
                                        <i class="bi bi-whatsapp me-1"></i> Chat WhatsApp
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        </div>

                    </div>
                </div>
            </div>
        </div>
    </section>
</main>

<?php include __DIR__ . '/template/footer.php'; ?>
