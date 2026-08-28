<?php
require_once '../config/koneksi_sqlsrv.php';

function sqlsrvRows($result) {
    $rows = [];
    if ($result !== false) {
        while ($row = sqlsrv_fetch_array($result, SQLSRV_FETCH_ASSOC)) {
            $rows[] = $row;
        }
    }
    return $rows;
}

function tampilTanggal($value) {
    return $value instanceof DateTimeInterface ? $value->format('Y-m-d') : (string)$value;
}

$contractTable = sqlsrv_query($conn, "SELECT TABLE_SCHEMA FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = 'dbo' AND TABLE_NAME = 'kontrak_pegawai'");
if ($contractTable === false || !sqlsrv_fetch_array($contractTable, SQLSRV_FETCH_ASSOC)) {
    sqlsrv_query($conn, "CREATE TABLE dbo.kontrak_pegawai (
        id INT IDENTITY(1,1) NOT NULL PRIMARY KEY,
        pegawai_id INT NOT NULL,
        nomor_kontrak NVARCHAR(50) NOT NULL,
        tanggal_mulai DATE NOT NULL,
        tanggal_berakhir DATE NOT NULL,
        jabatan NVARCHAR(100) NOT NULL,
        gaji DECIMAL(15,2) NOT NULL,
        status_kontrak NVARCHAR(20) NOT NULL CONSTRAINT DF_kontrak_status DEFAULT 'Aktif',
        created_at DATETIME2(0) NOT NULL CONSTRAINT DF_kontrak_created DEFAULT SYSDATETIME(),
        updated_at DATETIME2(0) NOT NULL CONSTRAINT DF_kontrak_updated DEFAULT SYSDATETIME()
    )");
}

$kontrak_pegawai = sqlsrvRows(sqlsrv_query($conn, "SELECT k.*, p.nama_peg, p.nik
    FROM dbo.kontrak_pegawai k
    LEFT JOIN dbo.pegawai p ON p.id_peg = k.pegawai_id
    WHERE k.status_kontrak = 'Aktif' ORDER BY k.tanggal_berakhir DESC, k.id DESC"));

$change_history = [];
$historyTable = sqlsrv_query($conn, "SELECT TABLE_SCHEMA FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = 'dbo' AND TABLE_NAME = 'riwayat_perubahan_kontrak'");
if ($historyTable !== false && sqlsrv_fetch_array($historyTable, SQLSRV_FETCH_ASSOC)) {
    $change_history = sqlsrvRows(sqlsrv_query($conn, "SELECT * FROM dbo.riwayat_perubahan_kontrak ORDER BY tanggal_perubahan DESC"));
}

$documents = [];
$documentTable = sqlsrv_query($conn, "SELECT TABLE_SCHEMA FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = 'dbo' AND TABLE_NAME = 'dokumen_pendukung'");
if ($documentTable !== false && sqlsrv_fetch_array($documentTable, SQLSRV_FETCH_ASSOC)) {
    $documents = sqlsrvRows(sqlsrv_query($conn, "SELECT * FROM dbo.dokumen_pendukung ORDER BY tanggal_upload DESC"));
}

include '../template/header.php';
include '../template/sidebar.php';
?>
<main id="main" class="main">
    <?php if (isset($_GET['message']) && $_GET['message'] == 'success'): ?>
        <div class="alert alert-success animate__animated animate__fadeIn" role="alert">
            Kontrak pegawai berhasil diperbarui!
        </div>
    <?php endif; ?>

    <div class="container">
        <h1 class="animate__animated animate__fadeInDown">Manajemen Kontrak Pegawai</h1>
        <ul class="nav nav-tabs animate__animated animate__fadeIn" id="kontrakPegawaiTab" role="tablist">
            <li class="nav-item" role="presentation">
                <a class="nav-link active" id="kontrak-tab" data-bs-toggle="tab" href="#kontrak" role="tab" aria-controls="kontrak" aria-selected="true">Data Kontrak Pegawai</a>
            </li>
            <li class="nav-item" role="presentation">
                <a class="nav-link" id="riwayat-tab" data-bs-toggle="tab" href="#riwayat" role="tab" aria-controls="riwayat" aria-selected="false">Riwayat Perubahan</a>
            </li>
            <li class="nav-item" role="presentation">
                <a class="nav-link" id="dokumen-tab" data-bs-toggle="tab" href="#dokumen" role="tab" aria-controls="dokumen" aria-selected="false">Dokumen Pendukung</a>
            </li>
        </ul>
        <div class="tab-content animate__animated animate__fadeIn" id="kontrakPegawaiTabContent">
            <div class="tab-pane fade show active" id="kontrak" role="tabpanel" aria-labelledby="kontrak-tab">
                <h2 class="mt-4"></h2>
                <button class="btn btn-primary mb-3 animate__animated animate__fadeIn" onclick="window.location.href='submit_kontrak.php'">Tambah Kontrak Pegawai</button>
                <input type="text" id="searchKontrak" onkeyup="searchTable('searchKontrak', 'tableKontrak')" placeholder="Cari data kontrak..." class="form-control mb-3">
                <h2 class="mt-4">Daftar Kontrak Pegawai</h2>
                <table class="table table-striped animate__animated animate__fadeIn" id="tableKontrak">
                    <thead>
                        <tr>
                            <th>ID Pegawai</th>
                            <th>Nomor Kontrak</th>
                            <th>Tanggal Mulai</th>
                            <th>Tanggal Berakhir</th>
                            <th>Jabatan</th>
                            <th>Gaji</th>
                            <th>Status</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($kontrak_pegawai as $row): ?>
                            <tr>
                                <td><?= $row['pegawai_id'] ?></td>
                                <td><?= $row['nomor_kontrak'] ?></td>
                                <td><?= htmlspecialchars(tampilTanggal($row['tanggal_mulai'])) ?></td>
                                <td><?= htmlspecialchars(tampilTanggal($row['tanggal_berakhir'])) ?></td>
                                <td><?= $row['jabatan'] ?></td>
                                <td><?= number_format((float)$row['gaji'], 2, ',', '.') ?></td>
                                <td><?= htmlspecialchars((string)$row['status_kontrak']) ?></td>
                                <td>
                                    <a href="edit_kontrak.php?id=<?= $row['id'] ?>" class="btn btn-warning btn-sm">Edit</a>
                                    <a href="delete_kontrak.php?id=<?= $row['id'] ?>" class="btn btn-danger btn-sm" onclick="return confirm('Yakin ingin menghapus?')">Hapus</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="tab-pane fade" id="riwayat" role="tabpanel" aria-labelledby="riwayat-tab">
                <h2 class="mt-4"></h2>
                <button class="btn btn-primary mb-3 animate__animated animate__fadeIn" onclick="window.location.href='submit_perubahan.php'">Tambah Perubahan Kontrak</button>
                <input type="text" id="searchRiwayat" onkeyup="searchTable('searchRiwayat', 'tableRiwayat')" placeholder="Cari riwayat perubahan..." class="form-control mb-3">
                <h2 class="mt-4">Daftar Perubahan Kontrak</h2>
                <table class="table table-striped animate__animated animate__fadeIn" id="tableRiwayat">
                    <thead>
                        <tr>
                            <th>ID Kontrak</th>
                            <th>Perubahan</th>
                            <th>Tanggal Perubahan</th>
                            <th>Dibuat Oleh</th>
                            <th>Gaji Sebelum Perubahan</th>
                            <th>Gaji Setelah Perubahan</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($change_history as $row): ?>
                            <tr>
                                <td><?= $row['kontrak_id'] ?></td>
                                <td><?= $row['perubahan'] ?></td>
                                <td><?= $row['tanggal_perubahan'] ?></td>
                                <td><?= $row['dibuat_oleh'] ?></td>
                                <td><?= $row['gaji_sebelum_perubahan'] ?></td>
                                <td><?= $row['gaji_setelah_perubahan'] ?></td>
                                <td>
                                    <a href="edit_perubahan.php?id=<?= $row['id'] ?>" class="btn btn-warning btn-sm">Edit</a>
                                    <a href="delete_perubahan.php?id=<?= $row['id'] ?>" class="btn btn-danger btn-sm" onclick="return confirm('Yakin ingin menghapus?')">Hapus</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="tab-pane fade" id="dokumen" role="tabpanel" aria-labelledby="dokumen-tab">
                <h2 class="mt-4"></h2>
                <button class="btn btn-primary mb-3 animate__animated animate__fadeIn" onclick="window.location.href='submit_dokumen.php'">Tambah Dokumen</button>
                <input type="text" id="searchDokumen" onkeyup="searchTable('searchDokumen', 'tableDokumen')" placeholder="Cari dokumen..." class="form-control mb-3">
                <h2 class="mt-4">Daftar Dokumen Pendukung</h2>
                <table class="table table-striped animate__animated animate__fadeIn" id="tableDokumen">
                    <thead>
                        <tr>
                            <th>ID Kontrak</th>
                            <th>Nama Dokumen</th>
                            <th>Tanggal Upload</th>
                            <th>Keterangan</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($documents as $doc): ?>
                            <tr>
                                <td><?= $doc['kontrak_id'] ?></td>
                                <td><?= $doc['nama_dokumen'] ?></td>
                                <td><?= $doc['tanggal_upload'] ?></td>
                                <td><?= $doc['keterangan'] ?></td>
                                <td>
                                    <a href="edit_dokumen.php?id=<?= $doc['id'] ?>" class="btn btn-warning btn-sm">Edit</a>
                                    <a href="delete_dokumen.php?id=<?= $doc['id'] ?>" class="btn btn-danger btn-sm" onclick="return confirm('Yakin ingin menghapus?')">Hapus</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</main>

<!-- Add CSS for transition -->
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

    function searchTable(inputId, tableId) {
        const input = document.getElementById(inputId);
        const filter = input.value.toUpperCase();
        const table = document.getElementById(tableId);
        const tr = table.getElementsByTagName("tr");

        for (let i = 1; i < tr.length; i++) {
            const td = tr[i].getElementsByTagName("td");
            let found = false;
            for (let j = 0; j < td.length; j++) {
                if (td[j].textContent.toUpperCase().indexOf(filter) > -1) {
                    found = true;
                    break;
                }
            }
            if (found) {
                tr[i].style.display = "";
            } else {
                tr[i].style.display = "none";
            }
        }
    }
</script>

<?php
include '../template/footer.php';
?>

