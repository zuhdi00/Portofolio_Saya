<?php
include '../template/header.php';
include '../template/sidebar.php';
include '../connection.php';

// Function to retrieve data from analisis_sdm table with optional search
function getAnalisisData($search = '') {
    global $conn;
    $sql = "SELECT * FROM `analisis_sdm`";
    if (!empty($search)) {
        $sql .= " WHERE `judul_analisis` LIKE ? OR `deskripsi` LIKE ? OR `jenis_analisis` LIKE ?";
        $stmt = $conn->prepare($sql);
        $searchParam = "%$search%";
        $stmt->bind_param("sss", $searchParam, $searchParam, $searchParam);
        $stmt->execute();
        $result = $stmt->get_result();
    } else {
        $result = $conn->query($sql);
    }
    return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
}

// Handle search query
$searchQuery = isset($_GET['search']) ? $_GET['search'] : '';
$analisis_data = getAnalisisData($searchQuery);
?>
<main id="main" class="main">
    <div class="container py-5">
        <h1 class="text-center mb-5 text-primary">
            <i class="bi bi-graph-up-arrow me-2"></i>Manajemen Analisis SDM
        </h1>

        <!-- Navigation Tabs -->
        <ul class="nav nav-pills justify-content-center mb-5" id="analisisTab" role="tablist">
            <li class="nav-item" role="presentation">
                <a class="nav-link active" id="analisis-tab" data-bs-toggle="pill" href="#analisis" role="tab" aria-controls="analisis" aria-selected="true">
                    <i class="bi bi-plus-circle me-2"></i>Tambah Analisis
                </a>
            </li>
            <li class="nav-item" role="presentation">
                <a class="nav-link" id="list-tab" data-bs-toggle="pill" href="#list" role="tab" aria-controls="list" aria-selected="false">
                    <i class="bi bi-list-task me-2"></i>Daftar Analisis
                </a>
            </li>
        </ul>

        <!-- Tab Content -->
        <div class="tab-content" id="analisisTabContent">

            <!-- Form Tab -->
            <div class="tab-pane fade show active" id="analisis" role="tabpanel" aria-labelledby="analisis-tab">
                <div class="row justify-content-center">
                    <!-- Card Form -->
                    <div class="col-lg-6 col-md-8">
                        <div class="card shadow-lg rounded-4 border-primary">
                            <div class="card-header text-white bg-gradient-custom text-center">
                                <h4><i class="bi bi-pencil-square me-2"></i>Tambah Analisis SDM</h4>
                            </div>
                            <div class="card-body">
                                <form method="post" action="submit_analisis.php">
                                    <div class="mb-3">
                                        <label for="judul_analisis" class="form-label">Judul Analisis</label>
                                        <input type="text" class="form-control border border-primary" id="judul_analisis" name="judul_analisis" required>
                                    </div>
                                    <div class="mb-3">
                                        <label for="deskripsi" class="form-label">Deskripsi</label>
                                        <textarea class="form-control border border-primary" id="deskripsi" name="deskripsi" rows="4" required></textarea>
                                    </div>
                                    <div class="mb-3">
                                        <label for="tanggal_analisis" class="form-label">Tanggal Analisis</label>
                                        <input type="date" class="form-control border border-primary" id="tanggal_analisis" name="tanggal_analisis" required>
                                    </div>
                                    <div class="mb-3">
                                        <label for="jenis_analisis" class="form-label">Jenis Analisis</label>
                                        <select class="form-select border border-primary" id="jenis_analisis" name="jenis_analisis" required>
                                            <option value="Kehadiran">Kehadiran</option>
                                            <option value="Kinerja">Kinerja</option>
                                            <option value="Pelatihan">Pelatihan</option>
                                            <option value="Penghargaan">Penghargaan</option>
                                        </select>
                                    </div>
                                    <button type="submit" class="btn btn-primary w-100 py-3 border border-primary">
                                        <i class="bi bi-save me-2"></i>Simpan Analisis
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Daftar Analisis Tab -->
            <div class="tab-pane fade" id="list" role="tabpanel" aria-labelledby="list-tab">
                <div class="row justify-content-center">
                    <!-- Search Form -->
                    <div class="col-lg-8 mb-4">
                        <form method="get" class="d-flex align-items-center justify-content-center">
                            <div class="input-group" style="max-width: 600px; width: 100%;">
                                <span class="input-group-text bg-primary text-white">
                                    <i class="bi bi-search"></i>
                                </span>
                                <input type="text" class="form-control" name="search" placeholder="Cari Analisis SDM..." value="<?= htmlspecialchars($searchQuery) ?>">
                                <button class="btn btn-primary" type="submit">
                                    <i class="bi bi-search me-1"></i>Cari
                                </button>
                            </div>
                        </form>
                    </div>

                    <!-- Card Tabel -->
                    <div class="col-lg-12">
                        <div class="card shadow-lg rounded-4 border-primary">
                            <div class="card-header bg-gradient-custom text-white text-center">
                                <h4><i class="bi bi-journal-text me-2"></i>Daftar Analisis SDM</h4>
                            </div>
                            <div class="card-body">
                                <table class="table table-striped table-bordered table-hover border-primary">
                                    <thead class="table-dark">
                                        <tr>
                                            <th>Judul Analisis</th>
                                            <th>Deskripsi</th>
                                            <th>Tanggal Analisis</th>
                                            <th>Jenis Analisis</th>
                                            <th>Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (!empty($analisis_data)): ?>
                                            <?php foreach ($analisis_data as $row): ?>
                                                <tr class="hover-shadow">
                                                    <td><?= htmlspecialchars($row['judul_analisis']) ?></td>
                                                    <td><?= htmlspecialchars($row['deskripsi']) ?></td>
                                                    <td><?= htmlspecialchars($row['tanggal_analisis']) ?></td>
                                                    <td>
                                                        <span class="badge bg-primary text-capitalize">
                                                            <?= htmlspecialchars($row['jenis_analisis']) ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <a href="edit_analisis.php?id=<?= $row['id_analisis'] ?>" class="btn btn-warning btn-sm">
                                                            <i class="bi bi-pencil-square me-2"></i>Edit
                                                        </a>
                                                        <a href="delete_analisis.php?id=<?= $row['id_analisis'] ?>" class="btn btn-danger btn-sm" onclick="return confirm('Apakah Anda yakin ingin menghapus data ini?');">
                                                            <i class="bi bi-trash me-2"></i>Hapus
                                                        </a>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="5" class="text-center text-muted">Data tidak ditemukan</td>
                                            </tr>
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
    
</main>

<!-- Add CSS for Styling -->
<style>
    .hover-shadow:hover {
        box-shadow: 0px 4px 15px rgba(0, 123, 255, 0.2);
    }

    .btn-primary {
        background-color: #0069d9;
        border-color: #0062cc;
    }

    .input-group-text {
        border-radius: 0;
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
