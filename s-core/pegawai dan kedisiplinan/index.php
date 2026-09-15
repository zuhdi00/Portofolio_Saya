<?php
include '../template/header.php';
include '../template/sidebar.php';
include '../connection.php';

function getPegawai() {
    global $conn;
    $result = $conn->query("SELECT * FROM `pegawai`");
    return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
}

function getKedisiplinan() {
    global $conn;
    $query = "SELECT k.*, p.nama_pegawai FROM `kedisiplinan` k 
              JOIN `pegawai` p ON k.id_peg = p.id_peg 
              ORDER BY k.id_kedisiplinan DESC";
    $result = $conn->query($query);
    if (!$result) {
        die("Query Error: " . $conn->error);
    }
    return $result->fetch_all(MYSQLI_ASSOC);
}

$pegawai = getPegawai();
$kedisiplinan = getKedisiplinan();
?>

<main id="main" class="main">
    <div class="container">
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        <h1 class="card-title text-center mb-4">Manajemen Pegawai & Kedisiplinan</h1>
                        <ul class="nav nav-pills nav-fill mb-4" id="dataTab" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active" id="pegawai-tab" data-bs-toggle="tab" data-bs-target="#pegawai" type="button" role="tab">
                                    <i class="bi bi-people-fill me-2"></i>Data Pegawai
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="kedisiplinan-tab" data-bs-toggle="tab" data-bs-target="#kedisiplinan" type="button" role="tab">
                                    <i class="bi bi-clipboard2-check-fill me-2"></i>Riwayat Kedisiplinan
                                </button>
                            </li>
                        </ul>
        
                        <div class="tab-content" id="dataTabContent">
                            <!-- Data Pegawai -->
                            <div class="tab-pane fade show active" id="pegawai" role="tabpanel">
                                <div class="d-flex justify-content-between align-items-center mb-4">
                                    <h2 class="card-subtitle">Daftar Pegawai</h2>
                                    <button class="btn btn-primary" onclick="window.location.href='submit_pegawai.php'">
                                        <i class="bi bi-plus-circle me-2"></i>Tambah Pegawai
                                    </button>
                                </div>
                                <div class="table-responsive">
                                    <table class="table table-hover table-striped">
                                        <thead class="table-dark">
                                            <tr>
                                                <th>ID Pegawai</th>
                                                <th>Nama Pegawai</th>
                                                <th>Jabatan</th>
                                                <th>Departemen</th>
                                                <th>Aksi</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($pegawai as $p): ?>
                                            <tr> 
                                                <td><?= $p['id_peg'] ?></td>
                                                <td><?= $p['nama_pegawai'] ?></td>
                                                <td><span class="badge bg-info"><?= $p['jabatan'] ?></span></td>
                                                <td><span class="badge bg-secondary"><?= $p['departemen'] ?></span></td>
                                                <td>
                                                    <a href="edit_pegawai.php?id=<?= $p['id_peg'] ?>" class="btn btn-warning btn-sm">
                                                        <i class="bi bi-pencil-square"></i>
                                                    </a>
                                                    <a href="delete_pegawai.php?id=<?= $p['id_peg'] ?>" class="btn btn-danger btn-sm" onclick="return confirm('Apakah Anda yakin ingin menghapus data ini?')">
                                                        <i class="bi bi-trash"></i>
                                                    </a>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
            
                            <!-- Riwayat Kedisiplinan -->
                            <div class="tab-pane fade" id="kedisiplinan" role="tabpanel">
                                <div class="d-flex justify-content-between align-items-center mb-4">
                                    <h2 class="card-subtitle">Riwayat Kedisiplinan</h2>
                                    <button class="btn btn-primary" onclick="window.location.href='submit_kedisiplinan.php'">
                                        <i class="bi bi-plus-circle me-2"></i>Tambah Riwayat
                                    </button>
                                </div>
                                <div class="table-responsive">
                                    <table class="table table-hover table-striped">
                                        <thead class="table-dark">
                                            <tr>
                                                <th>ID Kedisiplinan</th>
                                                <th>Nama Pegawai</th>
                                                <th>Jenis Pelanggaran</th>
                                                <th>Sanksi</th>
                                                <th>Aksi</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (empty($kedisiplinan)): ?>
                                            <tr><td colspan="5" class="text-center">Tidak ada data kedisiplinan</td></tr>
                                            <?php else: ?>
                                                <?php foreach ($kedisiplinan as $k): ?>
                                                <tr>
                                                    <td><?= $k['id_kedisiplinan'] ?></td>
                                                    <td><?= $k['nama_pegawai'] ?></td>
                                                    <td><span class="badge bg-danger"><?= $k['jenis_pelanggaran'] ?></span></td>
                                                    <td><span class="badge bg-warning text-dark"><?= $k['sanksi'] ?></span></td>
                                                    <td>
                                                        <a href="edit_kedisiplinan.php?id=<?= $k['id_kedisiplinan'] ?>" class="btn btn-warning btn-sm">
                                                            <i class="bi bi-pencil-square"></i>
                                                        </a>
                                                        <a href="delete_kedisiplinan.php?id=<?= $k['id_kedisiplinan'] ?>" class="btn btn-danger btn-sm" onclick="return confirm('Apakah Anda yakin ingin menghapus data ini?')">
                                                            <i class="bi bi-trash"></i>
                                                        </a>
                                                    </td>
                                                </tr>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<!-- Tambahkan CSS Bootstrap Icons -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css">

<!-- Tambahkan JavaScript Bootstrap -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<!-- Simpan Status Tab yang Aktif -->
<script>
document.addEventListener("DOMContentLoaded", function () {
    let activeTab = localStorage.getItem('activeTab');
    if (activeTab) {
        let tab = document.querySelector(`button[data-bs-target="${activeTab}"]`);
        if (tab) {
            new bootstrap.Tab(tab).show();
        }
    }

    document.querySelectorAll('button[data-bs-toggle="tab"]').forEach(tab => {
        tab.addEventListener("shown.bs.tab", function (event) {
            localStorage.setItem('activeTab', event.target.getAttribute('data-bs-target'));
        });
    });
});
</script>

<?php
include '../template/footer.php';
?>
