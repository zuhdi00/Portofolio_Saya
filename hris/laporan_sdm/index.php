<?php
include '../template/header.php';
include '../template/sidebar.php';
include '../connection.php';

// Function to retrieve data from laporan_sdm table with optional search
function getLaporanSDM($search = '') {
    global $conn;
    $sql = "SELECT * FROM laporan_sdm";
    if (!empty($search)) {
        $sql .= " WHERE judul_laporan LIKE ? OR isi_laporan LIKE ?";
        $stmt = $conn->prepare($sql);
        $searchParam = "%$search%";
        $stmt->bind_param("ss", $searchParam, $searchParam);
        $stmt->execute();
        $result = $stmt->get_result();
    } else {
        $result = $conn->query($sql);
    }
    return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
}

// Handle search query
$searchQuery = isset($_GET['search']) ? $_GET['search'] : '';
$laporan_sdm = getLaporanSDM($searchQuery);
?>

<main id="main" class="main">
    <div class="container mt-4">
        <!-- Hero Section -->
        <div class="hero-section p-5 text-center text-white mb-4" style="background: linear-gradient(45deg, #4CAF50, #2E7D32); border-radius: 10px;">
            <h1 class="display-4">Selamat Datang di Dashboard Laporan SDM</h1>
            <p class="lead">Kelola laporan SDM dengan mudah dan cepat.</p>
        </div>

        <h1 class="display-5 text-center mb-4">Manajemen Laporan SDM</h1>

        <!-- Navigation Tabs -->
        <ul class="nav nav-tabs" id="laporanSDMTab" role="tablist">
            <li class="nav-item" role="presentation">
                <a class="nav-link active" id="laporan-tab" data-bs-toggle="tab" href="#laporan" role="tab" aria-controls="laporan" aria-selected="true">
                    <i class="bi bi-file-earmark-plus"></i> Tambah Laporan SDM
                </a>
            </li>
            <li class="nav-item" role="presentation">
                <a class="nav-link" id="status-tab" data-bs-toggle="tab" href="#status" role="tab" aria-controls="status" aria-selected="false">
                    <i class="bi bi-check-circle"></i> Status Laporan
                </a>
            </li>
        </ul>

        <div class="tab-content mt-3" id="laporanSDMTabContent">
            <!-- Tab 1: Tambah Laporan SDM -->
            <div class="tab-pane fade show active" id="laporan" role="tabpanel" aria-labelledby="laporan-tab">
                <div class="card shadow-sm">
                    <div class="card-body">
                        <h3 class="card-title mb-4">Tambah Laporan SDM</h3>
                        <form method="post" action="submit_laporan.php">
                            <div class="mb-3">
                                <label for="judul_laporan" class="form-label">Judul Laporan</label>
                                <input type="text" class="form-control" id="judul_laporan" name="judul_laporan" required>
                            </div>
                            <div class="mb-3">
                                <label for="periode_awal" class="form-label">Periode Awal</label>
                                <input type="date" class="form-control" id="periode_awal" name="periode_awal" required>
                            </div>
                            <div class="mb-3">
                                <label for="periode_akhir" class="form-label">Periode Akhir</label>
                                <input type="date" class="form-control" id="periode_akhir" name="periode_akhir" required>
                            </div>
                            <div class="mb-3">
                                <label for="isi_laporan" class="form-label">Isi Laporan</label>
                                <textarea class="form-control" id="isi_laporan" name="isi_laporan" rows="3" required></textarea>
                            </div>
                            <div class="mb-3">
                                <label for="tanggal_dibuat" class="form-label">Tanggal Dibuat</label>
                                <input type="date" class="form-control" id="tanggal_dibuat" name="tanggal_dibuat" required>
                            </div>
                            <button type="submit" class="btn btn-primary w-100">Submit Laporan</button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Tab 2: Status Laporan -->
            <div class="tab-pane fade" id="status" role="tabpanel" aria-labelledby="status-tab">
                <div class="card shadow-sm">
                    <div class="card-body">
                        <h3 class="card-title mb-4">Status Laporan SDM</h3>
                        
                        <!-- Search Form -->
                        <form method="get" class="mb-4">
                            <div class="input-group">
                                <input type="text" name="search" class="form-control" placeholder="Cari laporan SDM..." value="<?= htmlspecialchars($searchQuery) ?>">
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-search me-2"></i>Cari
                                </button>
                            </div>
                        </form>
                        
                        <table class="table table-striped table-bordered">
                            <thead class="table-success">
                                <tr>
                                    <th>Judul Laporan</th>
                                    <th>Periode Awal</th>
                                    <th>Periode Akhir</th>
                                    <th>Isi Laporan</th>
                                    <th>Tanggal Dibuat</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($laporan_sdm)): ?>
                                    <?php foreach ($laporan_sdm as $row): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($row['judul_laporan']) ?></td>
                                            <td><?= htmlspecialchars($row['periode_awal']) ?></td>
                                            <td><?= htmlspecialchars($row['periode_akhir']) ?></td>
                                            <td><?= htmlspecialchars($row['isi_laporan']) ?></td>
                                            <td><?= htmlspecialchars($row['tanggal_dibuat']) ?></td>
                                            <td>
                                                <a href="edit_laporan.php?id_laporan=<?= $row['id_laporan'] ?>" class="btn btn-warning btn-sm">Edit</a>
                                                <a href="delete_laporan.php?id_laporan=<?= $row['id_laporan'] ?>" class="btn btn-danger btn-sm" onclick="return confirm('Apakah Anda yakin ingin menghapus laporan ini?')">Hapus</a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="6" class="text-center text-muted">Data tidak ditemukan</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Footer -->
        <div class="footer mt-5 text-center">
            <p class="text-muted">© <?= date('Y') ?> Sistem Laporan SDM | Dibuat dengan ❤️</p>
        </div>
    </div>
</main>

<!-- Add CSS for better styling -->
<style>
    .table-success thead th {
        background-color: #4CAF50;
        color: white;
    }
    .table td, .table th {
        text-align: center;
        vertical-align: middle;
    }
    .input-group {
        box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
    }
</style>
<style>
    .tab-pane {
        transition: all 0.5s ease;
    }
</style>

<!-- Add JavaScript for tab switching and search functionality -->
<script>
    document.querySelectorAll('.nav-link').forEach(link => {
        link.addEventListener('click', function() {
            const target = document.querySelector(this.getAttribute('href'));
            document.querySelectorAll('.tab-pane').forEach(pane => {
                pane.classList.remove('show', 'active');
            });
            target.classList.add('show', 'active');
        });
    });

</script>

<?php
include '../template/footer.php';
?>
