<?php
require_once '../config/koneksi_sqlsrv.php';

sqlsrv_query($conn, "IF OBJECT_ID(N'dbo.kontrak_pegawai', N'U') IS NULL
    CREATE TABLE dbo.kontrak_pegawai (
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

$pegawai = [];
$pegawaiResult = sqlsrv_query($conn, "SELECT id_peg, nik, nama_peg
    FROM dbo.pegawai WHERE is_aktif = 1 ORDER BY nama_peg, id_peg");
if ($pegawaiResult === false) {
    die('Gagal mengambil data pegawai: ' . print_r(sqlsrv_errors(), true));
}
while ($row = sqlsrv_fetch_array($pegawaiResult, SQLSRV_FETCH_ASSOC)) {
    $pegawai[] = $row;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $pegawai_id = (int)($_POST['pegawai_id'] ?? 0);
    $nomor_kontrak = trim($_POST['nomor_kontrak'] ?? '');
    $tanggal_mulai = $_POST['tanggal_mulai'] ?? '';
    $tanggal_berakhir = $_POST['tanggal_berakhir'] ?? '';
    $jabatan = trim($_POST['jabatan'] ?? '');
    $gaji = (float)($_POST['gaji'] ?? 0);
    $status_kontrak = $_POST['status_kontrak'] ?? 'Aktif';

    $sql = "INSERT INTO dbo.kontrak_pegawai
        (pegawai_id, nomor_kontrak, tanggal_mulai, tanggal_berakhir, jabatan, gaji, status_kontrak)
        VALUES (?, ?, ?, ?, ?, ?, ?)";
    $stmt = sqlsrv_query($conn, $sql, [
        $pegawai_id, $nomor_kontrak, $tanggal_mulai, $tanggal_berakhir,
        $jabatan, $gaji, $status_kontrak
    ]);

    if ($stmt !== false) {
        header('Location: index.php?message=success');
        exit;
    }

    $error = print_r(sqlsrv_errors(), true);
}

include '../template/header.php';
include '../template/sidebar.php';
?>

<main id="main" class="main">
    <div class="container">
        <h1>Tambah Kontrak Pegawai</h1>
        <form method="post" action="">
            <div class="mb-3">
                <label for="pegawai_id" class="form-label">Pegawai</label>
                <select class="form-select" id="pegawai_id" name="pegawai_id" required>
                    <option value="">-- Pilih pegawai --</option>
                    <?php foreach ($pegawai as $person): ?>
                        <option value="<?= (int)$person['id_peg'] ?>" <?= (int)($_POST['pegawai_id'] ?? 0) === (int)$person['id_peg'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars((string)$person['nama_peg']) ?> | ID <?= (int)$person['id_peg'] ?> | NIK <?= htmlspecialchars((string)($person['nik'] ?? '-')) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="mb-3">
                <label for="nomor_kontrak" class="form-label">Nomor Kontrak</label>
                <input type="text" class="form-control" id="nomor_kontrak" name="nomor_kontrak" required>
            </div>
            <div class="mb-3">
                <label for="tanggal_mulai" class="form-label">Tanggal Mulai</label>
                <input type="date" class="form-control" id="tanggal_mulai" name="tanggal_mulai" required>
            </div>
            <div class="mb-3">
                <label for="tanggal_berakhir" class="form-label">Tanggal Berakhir</label>
                <input type="date" class="form-control" id="tanggal_berakhir" name="tanggal_berakhir" required>
            </div>
            <div class="mb-3">
                <label for="jabatan" class="form-label">Jabatan</label>
                <input type="text" class="form-control" id="jabatan" name="jabatan" required>
            </div>
            <div class="mb-3">
                <label for="gaji" class="form-label">Gaji</label>
                <input type="number" class="form-control" id="gaji" name="gaji" required>
            </div>
            <div class="mb-3">
                <label for="status_kontrak" class="form-label">Status Kontrak</label>
                <select class="form-select" id="status_kontrak" name="status_kontrak" required>
                    <option value="Aktif">Aktif</option>
                    <option value="Nonaktif">Nonaktif</option>
                </select>
            </div>
            <?php if (!empty($error)): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>
            <button type="submit" class="btn btn-primary">Simpan Kontrak</button>
        </form>
    </div>
</main>

<?php include '../template/footer.php'; ?>