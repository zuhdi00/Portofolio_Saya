<?php
/* =====================================================================
   CARA PAKAI:
   1. Taruh blok PHP ini di PALING ATAS tambah_pegawai_lengkap.php
      (setelah include header/sidebar), untuk ambil data dropdown.
   2. Ganti 2 <input> di bagian "Organizational Assignment":
        - <input name="job_title">   -> <select name="jabatan_id">
        - <input name="unit_kerja">  -> <select name="unit_kerja_id">
      dengan potongan HTML di bawah.
   ===================================================================== */

include '../config/koneksi_sqlsrv.php';

$jabatanList = [];
$rs = sqlsrv_query($conn, "SELECT id_jabatan, nama_jabatan FROM dbo.jabatan ORDER BY nama_jabatan");
while ($row = sqlsrv_fetch_array($rs, SQLSRV_FETCH_ASSOC)) { $jabatanList[] = $row; }

$unitList = [];
$rs = sqlsrv_query($conn, "SELECT id, nama_unit FROM dbo.unit_kerja ORDER BY nama_unit");
while ($row = sqlsrv_fetch_array($rs, SQLSRV_FETCH_ASSOC)) { $unitList[] = $row; }
?>

<!-- ================= GANTI input Job Title jadi ini ================= -->
<div class="col-md-4">
    <label class="form-label">Jabatan :</label>
    <select name="jabatan_id" class="form-select" required>
        <option value="">-- pilih jabatan --</option>
        <?php foreach ($jabatanList as $j): ?>
            <option value="<?= $j['id_jabatan'] ?>"><?= htmlspecialchars($j['nama_jabatan']) ?></option>
        <?php endforeach; ?>
    </select>
</div>

<!-- ================= GANTI input Unit jadi ini ================= -->
<div class="col-md-8">
    <label class="form-label">Unit Kerja :</label>
    <select name="unit_kerja_id" class="form-select" required>
        <option value="">-- pilih unit kerja --</option>
        <?php foreach ($unitList as $u): ?>
            <option value="<?= $u['id'] ?>"><?= htmlspecialchars($u['nama_unit']) ?></option>
        <?php endforeach; ?>
    </select>
</div>

<?php
/* Kalau tabel jabatan/unit_kerja di dbHR masih KOSONG, isi dulu manual, contoh:
   INSERT INTO dbo.jabatan (nama_jabatan, level_jabatan) VALUES (N'Staff', N'Staff');
   INSERT INTO dbo.unit_kerja (kode_unit, nama_unit, department_id, level)
     VALUES (N'HRD-01', N'HR & GA', 1, 1);
*/
