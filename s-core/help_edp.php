<?php
require_once __DIR__ . '/auth/auth.php';
$page_title = 'Help EDP';
include __DIR__ . '/template/header.php';
include __DIR__ . '/template/sidebar.php';
?>

<main id="main" class="main">
    <div class="pagetitle">
        <h1>Help EDP</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?php echo BASE_URL; ?>/s-core/index.php">Home</a></li>
                <li class="breadcrumb-item active">Help EDP</li>
            </ol>
        </nav>
    </div>

    <section class="section">
        <div class="row">
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title"><i class="bi bi-question-circle me-2"></i>Bantuan Departemen EDP</h5>
                        <p class="text-muted">Tim EDP yang dapat membantu kebutuhan teknis dan penggunaan sistem:</p>
                        <ul class="list-group list-group-flush mt-3">
                            <li class="list-group-item d-flex align-items-center gap-3 px-0">
                                <i class="bi bi-person-circle text-primary fs-4"></i>
                                <span>Prio Suwahyo</span>
                            </li>
                            <li class="list-group-item d-flex align-items-center gap-3 px-0">
                                <i class="bi bi-person-circle text-primary fs-4"></i>
                                <span>Zuhdi Abdillah Hidayat</span>
                            </li>
                            <li class="list-group-item d-flex align-items-center gap-3 px-0">
                                <i class="bi bi-person-circle text-primary fs-4"></i>
                                <span>CHRISTIAN MARCELLO DWISUSANTO</span>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </section>
</main>

<?php include __DIR__ . '/template/footer.php'; ?>
