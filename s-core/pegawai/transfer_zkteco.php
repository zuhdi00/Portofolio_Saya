<?php
require_once __DIR__ . '/../auth/auth.php';
wajib_izin('pegawai_edit');
require_once __DIR__ . '/../config/koneksi_sqlsrv.php';

function transferQuery($conn, $sql, $params = []) {
    $stmt = sqlsrv_query($conn, $sql, $params);
    if ($stmt === false) {
        throw new Exception(print_r(sqlsrv_errors(), true));
    }
    return $stmt;
}

$message = null;
$messageType = 'success';
$transactionStarted = false;

try {
    transferQuery($conn, "IF OBJECT_ID(N'dbo.zkteco_user_transfer', N'U') IS NULL
    BEGIN
        CREATE TABLE dbo.zkteco_user_transfer (
            id_transfer BIGINT IDENTITY(1,1) NOT NULL PRIMARY KEY,
            source_userid INT NULL,
            source_badgenumber NVARCHAR(50) NULL,
            target_userid INT NOT NULL,
            target_badgenumber NVARCHAR(50) NULL,
            alasan NVARCHAR(500) NULL,
            dibuat_oleh NVARCHAR(150) NULL,
            dibuat_pada DATETIME2(0) NOT NULL DEFAULT (SYSDATETIME()),
            is_aktif BIT NOT NULL DEFAULT (1),
            CONSTRAINT CK_zkteco_transfer_userid CHECK (source_userid <> target_userid)
        )
    END");
    transferQuery($conn, "IF COL_LENGTH(N'dbo.zkteco_user_transfer', N'target_pegawai_id') IS NULL
        ALTER TABLE dbo.zkteco_user_transfer ADD target_pegawai_id BIGINT NULL");
    transferQuery($conn, "IF COL_LENGTH(N'dbo.zkteco_user_transfer', N'source_pegawai_id') IS NULL
        ALTER TABLE dbo.zkteco_user_transfer ADD source_pegawai_id BIGINT NULL");

    // Perbaiki unique index yang tidak mengizinkan lebih dari satu NULL (SQL Server constraint)
    transferQuery($conn, "IF EXISTS (SELECT * FROM sys.indexes WHERE name = 'UQ_zkteco_transfer_source_aktif' AND object_id = OBJECT_ID('dbo.zkteco_user_transfer'))
    BEGIN
        -- Cek apakah index berupa unique constraint, jika ya drop constraint
        IF EXISTS (SELECT * FROM sys.objects WHERE name = 'UQ_zkteco_transfer_source_aktif' AND type = 'UQ')
            ALTER TABLE dbo.zkteco_user_transfer DROP CONSTRAINT UQ_zkteco_transfer_source_aktif;
        ELSE
            DROP INDEX UQ_zkteco_transfer_source_aktif ON dbo.zkteco_user_transfer;
    END");

    transferQuery($conn, "ALTER TABLE dbo.zkteco_user_transfer ALTER COLUMN source_userid INT NULL");
    transferQuery($conn, "ALTER TABLE dbo.zkteco_user_transfer ALTER COLUMN target_userid INT NULL");

    
    // Buat ulang sebagai filtered index yang mengabaikan NULL
    transferQuery($conn, "IF NOT EXISTS (SELECT * FROM sys.indexes WHERE name = 'UQ_zkteco_transfer_source_aktif' AND object_id = OBJECT_ID('dbo.zkteco_user_transfer'))
    BEGIN
        CREATE UNIQUE NONCLUSTERED INDEX UQ_zkteco_transfer_source_aktif ON dbo.zkteco_user_transfer(source_userid) WHERE is_aktif = 1 AND source_userid IS NOT NULL;
    END");

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $sourcePegawaiId = (int)($_POST['source_pegawai_id'] ?? 0);
        $targetPegawaiId = (int)($_POST['target_pegawai_id'] ?? 0);
        $alasan = trim($_POST['alasan'] ?? 'Data duplicate pegawai');
        $user = user_login();
        $dibuatOleh = $user['nama_lengkap'] ?? $user['username'] ?? 'admin';

        if (!$sourcePegawaiId || !$targetPegawaiId) {
            throw new Exception('User 1 dan User 2 harus valid.');
        }

        $source = transferQuery($conn, "SELECT TOP 1 id_peg, nama_peg, no_ktp, zkteco_userid, zkteco_acno
            FROM dbo.pegawai WHERE id_peg = ? AND is_aktif = 1", [$sourcePegawaiId]);
        $source = sqlsrv_fetch_array($source, SQLSRV_FETCH_ASSOC);
        $target = transferQuery($conn, "SELECT TOP 1 id_peg, nama_peg, no_ktp, zkteco_userid, zkteco_acno
            FROM dbo.pegawai WHERE id_peg = ? AND is_aktif = 1", [$targetPegawaiId]);
        $target = sqlsrv_fetch_array($target, SQLSRV_FETCH_ASSOC);

        if (!$source || !$target || !$target['zkteco_userid'] || (int)$source['id_peg'] === (int)$target['id_peg']) {
            throw new Exception('User 1 atau User 2 tidak ditemukan, atau keduanya adalah data yang sama.');
        }

        sqlsrv_begin_transaction($conn);
        $transactionStarted = true;
        $sourceKtp = trim((string)($source['no_ktp'] ?? ''));
        if ($sourceKtp !== '') {
            $ktpOwner = transferQuery($conn, "SELECT TOP 1 id_peg, nama_peg
                FROM dbo.pegawai
                WHERE no_ktp = ? AND id_peg NOT IN (?, ?)", [
                    $sourceKtp, (int)$source['id_peg'], (int)$target['id_peg']
                ]);
            $ktpOwner = sqlsrv_fetch_array($ktpOwner, SQLSRV_FETCH_ASSOC);
            if ($ktpOwner) {
                throw new Exception('No. KTP ' . $sourceKtp . ' sudah dimiliki pegawai lain: ' . $ktpOwner['nama_peg'] . ' (ID ' . $ktpOwner['id_peg'] . ').');
            }

            // Kolom no_ktp unik: pindahkan nilainya, jangan menggandakan.
            transferQuery($conn, "UPDATE dbo.pegawai SET no_ktp = NULL WHERE id_peg IN (?, ?)", [
                (int)$source['id_peg'], (int)$target['id_peg']
            ]);
        }

        // Merge data yang tersedia dari sumber. Identitas dan kunci presensi tidak pernah diubah.
        $textColumns = [
            'nama_peg', 'email_peg', 'no_hp_peg', 'tempat_lahir', 'gender', 'agama', 'status_nikah',
            'alamat_ktp_peg', 'rt', 'rw', 'kelurahan', 'kecamatan', 'kota', 'provinsi', 'kode_pos',
            'alamat_domi_peg', 'rt_dom', 'rw_dom', 'kelurahan_dom', 'kecamatan_dom', 'kota_dom',
            'provinsi_dom', 'kode_pos_dom', 'status_karyawan', 'npwp', 'no_bpjs_tk', 'no_bpjs_kes',
            'durasi_kontrak', 'alasan_berhenti', 'lokasi_kerja', 'no_rekening', 'nama_bank',
            'company_name', 'position_code', 'level_code', 'grade_code', 'employee_subgroup',
            'ptkp_status', 'bank_payee', 'bank_kode', 'bank_detail', 'work_location', 'nama_nasabah'
        ];
        $dateColumns = ['tgl_lahir', 'tgl_masuk', 'tgl_akhir_kontrak', 'tgl_berhenti'];
        $numberColumns = ['contract_month', 'unit_kerja_id', 'jabatan_id', 'atasan_id'];
        $setParts = [];
        foreach ($textColumns as $column) {
            $setParts[] = "$column = COALESCE(NULLIF(s.$column, N''), t.$column)";
        }
        foreach ($dateColumns as $column) {
            $setParts[] = "$column = COALESCE(s.$column, t.$column)";
        }
        foreach ($numberColumns as $column) {
            $setParts[] = "$column = COALESCE(s.$column, t.$column)";
        }
        transferQuery($conn, "UPDATE t SET " . implode(', ', $setParts) . "
            FROM dbo.pegawai t
            INNER JOIN dbo.pegawai s ON s.id_peg = ?
            WHERE t.id_peg = ?", [(int)$source['id_peg'], (int)$target['id_peg']]);
        if ($sourceKtp !== '') {
            transferQuery($conn, "UPDATE dbo.pegawai SET no_ktp = ? WHERE id_peg = ?", [
                $sourceKtp, (int)$target['id_peg']
            ]);
        }

        if ($source['zkteco_userid']) {
            transferQuery($conn, "UPDATE dbo.zkteco_user_transfer SET is_aktif = 0
                WHERE source_userid = ? AND is_aktif = 1", [(int)$source['zkteco_userid']]);
        }
        transferQuery($conn, "INSERT INTO dbo.zkteco_user_transfer
            (source_userid, source_badgenumber, target_userid, target_badgenumber, source_pegawai_id, target_pegawai_id, alasan, dibuat_oleh)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)", [
                $source['zkteco_userid'] ? (int)$source['zkteco_userid'] : null,
                $source['zkteco_userid'] ? (string)($source['zkteco_acno'] ?? '') : null,
                $target['zkteco_userid'] ? (int)$target['zkteco_userid'] : null,
                (string)($target['zkteco_acno'] ?? ''),
                (int)$source['id_peg'],
                (int)$target['id_peg'],
                $alasan ?: 'Data duplicate pegawai',
                $dibuatOleh
            ]);
        sqlsrv_commit($conn);
            $transactionStarted = false;

        $message = 'Data berhasil ditransfer: ' . $source['nama_peg'] . ' → ' . $target['nama_peg'] . '. ID pegawai, NIK, USERID, dan Badgenumber tujuan tetap dipertahankan; no_ktp ikut ditransfer jika tersedia.';
    }

    $employees = [];
    $employeeResult = transferQuery($conn, "SELECT id_peg, nama_peg, zkteco_userid, zkteco_acno
        FROM dbo.pegawai WHERE is_aktif = 1 ORDER BY nama_peg, id_peg");
    while ($row = sqlsrv_fetch_array($employeeResult, SQLSRV_FETCH_ASSOC)) {
        $employees[] = $row;
    }

    $duplicateEmployees = [];
        $duplicateResult = transferQuery($conn, "WITH nama_ganda AS (
            SELECT UPPER(LTRIM(RTRIM(nama_peg))) AS nama_normal
            FROM dbo.pegawai
            WHERE is_aktif = 1
            GROUP BY UPPER(LTRIM(RTRIM(nama_peg)))
            HAVING COUNT(*) > 1
                ), ktp_ganda AS (
                        SELECT LTRIM(RTRIM(no_ktp)) AS ktp_normal
                        FROM dbo.pegawai
                        WHERE is_aktif = 1
                            AND NULLIF(LTRIM(RTRIM(no_ktp)), '') IS NOT NULL
                        GROUP BY LTRIM(RTRIM(no_ktp))
                        HAVING COUNT(*) > 1
        )
        SELECT p.id_peg, p.nama_peg, p.zkteco_userid, p.zkteco_acno, p.no_ktp
        FROM dbo.pegawai p
                LEFT JOIN nama_ganda g ON g.nama_normal = UPPER(LTRIM(RTRIM(p.nama_peg)))
                LEFT JOIN ktp_ganda k ON k.ktp_normal = LTRIM(RTRIM(p.no_ktp))
                WHERE p.is_aktif = 1
                    AND (g.nama_normal IS NOT NULL OR k.ktp_normal IS NOT NULL)
        ORDER BY p.nama_peg, p.zkteco_userid");
    while ($row = sqlsrv_fetch_array($duplicateResult, SQLSRV_FETCH_ASSOC)) {
        $duplicateEmployees[] = $row;
    }

    $transfers = [];
    $transferResult = transferQuery($conn, "SELECT TOP 50 source_userid, source_badgenumber,
        target_userid, target_badgenumber, alasan, dibuat_oleh, dibuat_pada
        FROM dbo.zkteco_user_transfer WHERE is_aktif = 1 ORDER BY dibuat_pada DESC");
    while ($row = sqlsrv_fetch_array($transferResult, SQLSRV_FETCH_ASSOC)) {
        $transfers[] = $row;
    }
} catch (Throwable $e) {
    if ($transactionStarted && isset($conn) && $_SERVER['REQUEST_METHOD'] === 'POST') {
        sqlsrv_rollback($conn);
    }
    $message = $e->getMessage();
    $messageType = 'danger';
    $employees = $employees ?? [];
    $duplicateEmployees = $duplicateEmployees ?? [];
    $transfers = $transfers ?? [];
}

$page_title = 'Transfer Data ZKTeco';
include '../template/header.php';
include '../template/sidebar.php';
?>
<main id="main" class="main">
    <div class="pagetitle">
        <h1>Transfer Data ZKTeco</h1>
        <p class="text-muted">Transfer data pegawai User 1 ke record ZKTeco User 2. ID pegawai, NIK, USERID, dan Badgenumber User 2 tetap dipertahankan.</p>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-<?= htmlspecialchars($messageType) ?>"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>

    <section class="section">
        <div class="alert alert-info">
            Kandidat duplicate terdeteksi berdasarkan nama: <strong><?= count($duplicateEmployees) ?></strong> data.
            Pilih User 1 sebagai data sumber dan User 2 sebagai data utama.
        </div>
        <?php if ($duplicateEmployees): ?>
            <div class="card mb-4">
                <div class="card-body">
                    <h5 class="card-title">Kandidat Duplicate</h5>
                    <div class="table-responsive">
                        <table class="table table-sm table-striped">
                            <thead><tr><th>Nama</th><th>ID Pegawai</th><th>USERID</th><th>Badgenumber</th><th>NIK</th><th></th></tr></thead>
                            <tbody>
                            <?php foreach ($duplicateEmployees as $duplicate): ?>
                                <tr>
                                    <td><?= htmlspecialchars((string)$duplicate['nama_peg']) ?></td>
                                    <td><?= (int)$duplicate['id_peg'] ?></td>
                                    <td><?= (int)$duplicate['zkteco_userid'] ?></td>
                                    <td><?= htmlspecialchars((string)($duplicate['zkteco_acno'] ?? '-')) ?></td>
                                    <td><?= htmlspecialchars((string)($duplicate['no_ktp'] ?? '-')) ?></td>
                                    <td>
                                        <button type="button" class="btn btn-sm btn-outline-primary pilih-transfer" data-pegawai-id="<?= (int)$duplicate['id_peg'] ?>" data-select-id="<?= (int)$duplicate['zkteco_userid'] > 0 ? 'target_pegawai_id' : 'source_pegawai_id' ?>" onclick="pilihPegawai('<?= (int)$duplicate['zkteco_userid'] > 0 ? 'target_pegawai_id' : 'source_pegawai_id' ?>', '<?= (int)$duplicate['zkteco_userid'] > 0 ? 'target_search' : 'source_search' ?>', '<?= (int)$duplicate['zkteco_userid'] > 0 ? 'target_results' : 'source_results' ?>', '<?= (int)$duplicate['id_peg'] ?>')">
                                            <?= (int)$duplicate['zkteco_userid'] > 0 ? 'Jadikan Tujuan' : 'Jadikan Sumber' ?>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        <?php endif; ?>
        <div class="card">
            <div class="card-body">
                <h5 class="card-title">Buat Transfer</h5>
                <form method="post" class="row g-3">
                    <div class="col-md-5">
                        <label for="source_pegawai_id" class="form-label">User 1 / sumber data pegawai</label>
                        <input type="search" id="source_search" class="form-control mb-2" placeholder="Ketik nama, USERID, atau Badgenumber" autocomplete="off" oninput="filterPegawai('source_search', 'source_pegawai_id', 'source_results')">
                        <div id="source_results" class="list-group mb-2" style="display:block; position:relative; z-index:10"></div>
                        <select name="source_pegawai_id" id="source_pegawai_id" class="form-select" required>
                            <option value="">-- pilih sumber data pegawai --</option>
                            <?php foreach ($employees as $employee): ?>
                                <option value="<?= (int)$employee['id_peg'] ?>">
                                    <?= htmlspecialchars((string)$employee['nama_peg']) ?> | ID Pegawai <?= (int)$employee['id_peg'] ?> | USERID <?= $employee['zkteco_userid'] ? (int)$employee['zkteco_userid'] : '-' ?> | Badge <?= htmlspecialchars((string)($employee['zkteco_acno'] ?? '-')) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2 d-flex align-items-end justify-content-center fs-3">→</div>
                    <div class="col-md-5">
                        <label for="target_pegawai_id" class="form-label">User 2 / tujuan yang sudah ada di ZKTeco</label>
                        <input type="search" id="target_search" class="form-control mb-2" placeholder="Ketik nama, USERID, atau Badgenumber" autocomplete="off" oninput="filterPegawai('target_search', 'target_pegawai_id', 'target_results')">
                        <div id="target_results" class="list-group mb-2" style="display:block; position:relative; z-index:10"></div>
                        <select name="target_pegawai_id" id="target_pegawai_id" class="form-select" required>
                                <option value="">-- pilih record pegawai tujuan --</option>
                            <?php foreach ($employees as $employee): ?>
                                <option value="<?= (int)$employee['id_peg'] ?>" <?= (int)$employee['zkteco_userid'] > 0 ? '' : 'disabled' ?>>
                                    <?= htmlspecialchars((string)$employee['nama_peg']) ?> | ID Pegawai <?= (int)$employee['id_peg'] ?> | USERID <?= $employee['zkteco_userid'] ? (int)$employee['zkteco_userid'] : '-' ?> | Badge <?= htmlspecialchars((string)($employee['zkteco_acno'] ?? '-')) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-12">
                        <label for="alasan" class="form-label">Alasan</label>
                        <input type="text" name="alasan" id="alasan" class="form-control" value="Data duplicate pegawai" maxlength="500" required>
                    </div>
                    <div class="col-12">
                        <button type="submit" class="btn btn-primary" onclick="return confirm('Simpan transfer User 1 ke User 2? Data asli tidak akan diubah.')">
                            <i class="bi bi-arrow-left-right"></i> Simpan Transfer
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div class="card mt-4">
            <div class="card-body">
                <h5 class="card-title">Transfer Aktif</h5>
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead><tr><th>User 1</th><th>Badge 1</th><th>User 2</th><th>Badge 2</th><th>Alasan</th><th>Dibuat</th></tr></thead>
                        <tbody>
                        <?php foreach ($transfers as $transfer): ?>
                            <tr>
                                <td><?= $transfer['source_userid'] === null ? '-' : (int)$transfer['source_userid'] ?></td>
                                <td><?= htmlspecialchars((string)($transfer['source_badgenumber'] ?? '-')) ?></td>
                                <td><?= (int)$transfer['target_userid'] ?></td>
                                <td><?= htmlspecialchars((string)($transfer['target_badgenumber'] ?? '-')) ?></td>
                                <td><?= htmlspecialchars((string)($transfer['alasan'] ?? '-')) ?></td>
                                <td><?= htmlspecialchars($transfer['dibuat_pada'] instanceof DateTime ? $transfer['dibuat_pada']->format('Y-m-d H:i:s') : (string)($transfer['dibuat_pada'] ?? '-')) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$transfers): ?><tr><td colspan="6" class="text-center">Belum ada transfer aktif.</td></tr><?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </section>
</main>
<script>
function pilihPegawai(selectId, searchId, resultsId, value) {
    const select = document.getElementById(selectId);
    const search = document.getElementById(searchId);
    const results = document.getElementById(resultsId);
    select.value = String(value);
    search.value = select.options[select.selectedIndex] ? select.options[select.selectedIndex].textContent.trim() : '';
    results.innerHTML = '';
    select.size = 1;
    select.dispatchEvent(new Event('change', { bubbles: true }));
}

function filterPegawai(inputId, selectId, resultsId) {
    const input = document.getElementById(inputId);
    const select = document.getElementById(selectId);
    const results = document.getElementById(resultsId);
    const keyword = input.value.trim().toLowerCase();
    results.innerHTML = '';
    if (!keyword) return;

    const matches = Array.from(select.options).filter(function (option, index) {
        return index > 0 && !option.disabled && option.textContent.toLowerCase().includes(keyword);
    }).slice(0, 12);
    matches.forEach(function (option) {
        const result = document.createElement('button');
        result.type = 'button';
        result.className = 'list-group-item list-group-item-action text-start';
        result.textContent = option.textContent.trim();
        result.onclick = function () {
            pilihPegawai(selectId, inputId, resultsId, option.value);
        };
        results.appendChild(result);
    });
    if (!matches.length) {
        const empty = document.createElement('div');
        empty.className = 'list-group-item text-muted';
        empty.textContent = 'Data pegawai tidak ditemukan';
        results.appendChild(empty);
    }
}

function aktifkanPencarian(inputId, selectId, resultsId) {
    const input = document.getElementById(inputId);
    input.addEventListener('input', function () {
        filterPegawai(inputId, selectId, resultsId);
    });
    const select = document.getElementById(selectId);
    select.addEventListener('change', function () {
        select.size = 1;
        document.getElementById(resultsId).innerHTML = '';
    });
}
aktifkanPencarian('source_search', 'source_pegawai_id', 'source_results');
aktifkanPencarian('target_search', 'target_pegawai_id', 'target_results');
document.querySelectorAll('.pilih-transfer').forEach(function (button) {
    button.addEventListener('click', function () {
        const targetMode = button.dataset.selectId === 'target_pegawai_id';
        pilihPegawai(
            button.dataset.selectId,
            targetMode ? 'target_search' : 'source_search',
            targetMode ? 'target_results' : 'source_results',
            button.dataset.pegawaiId
        );
    });
});
</script>
<?php include '../template/footer.php'; ?>
