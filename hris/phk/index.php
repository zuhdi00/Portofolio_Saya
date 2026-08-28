<?php
require_once __DIR__ . '/../auth/auth.php';
wajib_izin('modul_hr');
include '../template/header.php';
include '../template/sidebar.php';
include '../connection.php';

$phkTableExists = false;
$phk = [];
$tableCheck = $conn->query("SHOW TABLES LIKE 'phk'");
if ($tableCheck && $tableCheck->num_rows > 0) {
    $phkTableExists = true;
    $result = $conn->query("SELECT * FROM phk ORDER BY tanggal_phk DESC, id_phk DESC");
    if ($result) {
        $phk = $result->fetch_all(MYSQLI_ASSOC);
    }
}
?>

<main id="main" class="main">
    <div class="container mt-5">
        <!-- Dashboard Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="display-4 text-dark">Dashboard Pengajuan PHK</h1>
                <p class="lead text-muted">Kelola pengajuan PHK dengan mudah dan cepat.</p>
            </div>
            <button class="btn btn-primary btn-lg" data-bs-toggle="modal" data-bs-target="#addPHKModal">
                <i class="bi bi-plus-circle"></i> Tambah Pengajuan PHK
            </button>
        </div>

        <?php if (!$phkTableExists): ?>
            <div class="alert alert-warning" role="alert">
                <strong>Tabel PHK belum tersedia.</strong>
                Jalankan skrip <code>database/05_phk.sql</code> pada database <code>hris</code>, lalu muat ulang halaman ini.
            </div>
        <?php endif; ?>

        <!-- Row for Total Count and Stats -->
        <div class="row mb-5">
            <!-- Cards for Stats (Total, Completed, Pending) -->
            <!-- You can keep the previous stats cards here if needed -->
        </div>

        <!-- Search Box -->
        <div class="mb-3 d-flex">
            <input type="text" id="searchInput" class="form-control me-2" placeholder="Cari berdasarkan ID Karyawan, Alasan, atau Status Kompensasi" onkeyup="filterTable()">
            <button class="btn btn-outline-secondary" type="button" id="searchBtn">
                <i class="bi bi-search"></i> Cari
            </button>
        </div>

        <!-- Tab Navigation for Managing PHK -->
        <ul class="nav nav-pills mb-4" id="phkTab" role="tablist">
            <li class="nav-item" role="presentation">
                <a class="nav-link active" id="phk-tab" data-bs-toggle="pill" href="#phk" role="tab" aria-controls="phk" aria-selected="true">
                    <i class="bi bi-file-earmark-plus"></i> Pengajuan PHK
                </a>
            </li>
            <li class="nav-item" role="presentation">
                <a class="nav-link" id="status-tab" data-bs-toggle="pill" href="#status" role="tab" aria-controls="status" aria-selected="false">
                    <i class="bi bi-check-circle"></i> Status Pengajuan
                </a>
            </li>
        </ul>

        <div class="tab-content" id="phkTabContent">
            <!-- Tab 1: Pengajuan PHK -->
            <div class="tab-pane fade show active" id="phk" role="tabpanel" aria-labelledby="phk-tab">
                <div class="table-responsive">
                    <table class="table table-hover table-striped" id="phkTable">
                        <thead class="table-dark">
                            <tr>
                                <th>ID Karyawan</th>
                                <th>Tanggal PHK</th>
                                <th>Alasan PHK</th>
                                <th>Status Kompensasi</th>
                                <th>Jumlah Kompensasi</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($phk as $row): ?>
                                <tr>
                                    <td><?= htmlspecialchars((string)$row['id_karyawan']) ?></td>
                                    <td><?= htmlspecialchars((string)$row['tanggal_phk']) ?></td>
                                    <td><?= htmlspecialchars((string)$row['alasan_phk']) ?></td>
                                    <td>
                                        <span class="badge <?= ($row['status_kompensasi'] == 'Diberikan') ? 'bg-success' : 'bg-danger' ?>">
                                            <?= htmlspecialchars((string)$row['status_kompensasi']) ?>
                                        </span>
                                    </td>
                                    <td><?= number_format($row['jumlah_kompensasi'], 0, ',', '.') ?></td>
                                    <td>
                                        <a href="edit_phk.php?id_phk=<?= $row['id_phk'] ?>" class="btn btn-warning btn-sm">Edit</a>
                                        <a href="delete_phk.php?id_phk=<?= $row['id_phk'] ?>" class="btn btn-danger btn-sm" onclick="return confirm('Apakah Anda yakin ingin menghapus pengajuan PHK ini?')">Delete</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Tab 2: Status Pengajuan -->
            <div class="tab-pane fade" id="status" role="tabpanel" aria-labelledby="status-tab">
                <div class="table-responsive">
                    <table class="table table-hover table-striped">
                        <thead class="table-dark">
                            <tr>
                                <th>ID Karyawan</th>
                                <th>Tanggal PHK</th>
                                <th>Alasan PHK</th>
                                <th>Status Pengajuan</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($phk as $row): ?>
                                <tr>
                                    <td><?= htmlspecialchars((string)$row['id_karyawan']) ?></td>
                                    <td><?= htmlspecialchars((string)$row['tanggal_phk']) ?></td>
                                    <td><?= htmlspecialchars((string)$row['alasan_phk']) ?></td>
                                    <td>
                                        <span class="badge <?= ($row['status_kompensasi'] == 'Diberikan') ? 'bg-success' : 'bg-danger' ?>">
                                            <?= htmlspecialchars((string)$row['status_kompensasi']) ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Add PHK -->
    <div class="modal fade" id="addPHKModal" tabindex="-1" aria-labelledby="addPHKModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="addPHKModalLabel">Tambah Pengajuan PHK</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form method="post" action="submit_phk.php">
                        <div class="mb-3">
                            <label for="id_karyawan" class="form-label">ID Karyawan</label>
                            <input type="number" class="form-control" id="id_karyawan" name="id_karyawan" required>
                        </div>
                        <div class="mb-3">
                            <label for="tanggal_phk" class="form-label">Tanggal PHK</label>
                            <input type="date" class="form-control" id="tanggal_phk" name="tanggal_phk" required>
                        </div>
                        <div class="mb-3">
                            <label for="alasan_phk" class="form-label">Alasan PHK</label>
                            <textarea class="form-control" id="alasan_phk" name="alasan_phk" rows="3" required></textarea>
                        </div>
                        <div class="mb-3">
                            <label for="status_kompensasi" class="form-label">Status Kompensasi</label>
                            <select class="form-select" id="status_kompensasi" name="status_kompensasi" required>
                                <option value="Diberikan">Diberikan</option>
                                <option value="Tidak Diberikan">Tidak Diberikan</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="jumlah_kompensasi" class="form-label">Jumlah Kompensasi</label>
                            <input type="number" class="form-control" id="jumlah_kompensasi" name="jumlah_kompensasi" required>
                        </div>
                        <button type="submit" class="btn btn-primary w-100">Submit</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

</main>

<!-- Add Bootstrap JS and icons -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.js"></script>

<script>
// JavaScript for search filter
function filterTable() {
    const input = document.getElementById('searchInput');
    const filter = input.value.toLowerCase();
    const table = document.getElementById('phkTable');
    const tr = table.getElementsByTagName('tr');

    for (let i = 0; i < tr.length; i++) {
        const td = tr[i].getElementsByTagName('td');
        if (td) {
            let match = false;
            for (let j = 0; j < td.length; j++) {
                if (td[j].textContent.toLowerCase().includes(filter)) {
                    match = true;
                    break;
                }
            }
            tr[i].style.display = match ? '' : 'none';
        }
    }
}
</script>

<style>
/* Global Styles */
body {
    font-family: 'Roboto', sans-serif;
    background-color: #f9f9f9;
}

/* Card Styling */
.card {
    border-radius: 12px;
    box-shadow: 0 6px 12px rgba(0, 0, 0, 0.1);
    transition: transform 0.3s ease, box-shadow 0.3s ease;
}

.card:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 20px rgba(0, 0, 0, 0.15);
}

.card-body {
    padding: 30px;
}

/* Table Styles */
.table th, .table td {
    text-align: center;
    padding: 16px;
    font-size: 14px;
}

.table-dark {
    background-color: #343a40;
    color: white;
}

.table-hover tbody tr:hover {
    background-color: #e9ecef;
    cursor: pointer;
}

/* Button Styles */
.btn-primary {
    background-color: #007bff;
    border: none;
    border-radius: 8px;
}

.btn-primary:hover {
    background-color: #0056b3;
}

/* Modal Styling */
.modal-content {
    border-radius: 12px;
}

.modal-header {
    background-color: #007bff;
}

/* Search Box Styles */
#searchInput {
    padding-left: 30px; /* Adding padding for the search icon */
    font-size: 16px;
    border-radius: 10px;
    height: 45px;
    border: 1px solid #ddd;
}

#searchInput:focus {
    outline: none;
    border-color: #007bff; /* Highlight border on focus */
}

#searchBtn {
    background-color: #007bff;
    color: white;
    border-radius: 10px;
    height: 45px;
    padding: 0 20px;
    margin-left: 10px;
}

#searchBtn:hover {
    background-color: #0056b3;
}

/* Styling for Search Icon */
#searchBtn i {
    font-size: 18px;
}

/* Responsive Adjustments */
@media (max-width: 768px) {
    .container {
        padding: 20px;
    }

    .card {
        margin-bottom: 20px;
    }

    .table th, .table td {
        font-size: 12px;
    }

    .btn {
        font-size: 14px;
    }
}
</style>

<?php
include '../template/footer.php';
?>
