<?php
// Koneksi database
include '../koneksi.php';

// Query untuk mengambil data slip gaji
$query = "SELECT * FROM slip_gaji ORDER BY id_slip_gaji DESC";
$result = mysqli_query($koneksi, $query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Data Slip Gaji</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <h2>Data Slip Gaji</h2>
        <a href="tambah.php" class="btn btn-primary mb-3">Tambah Slip Gaji</a>
        
        <table class="table table-bordered">
            <thead>
                <tr>
                    <th>No</th>
                    <th>ID Slip Gaji</th>
                    <th>Bulan/Tahun</th>
                    <th>Gaji Pokok</th>
                    <th>Tunjangan</th>
                    <th>Potongan</th>
                    <th>Total Gaji</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $no = 1;
                while ($row = mysqli_fetch_assoc($result)) {
                    // Hitung total gaji
                    $total_gaji = $row['gaji_pokok'] + $row['tunjangan'] - $row['potongan'];
                ?>
                    <tr>
                        <td><?php echo $no++; ?></td>
                        <td><?php echo $row['id_slip_gaji']; ?></td>
                        <td><?php echo $row['bulan_tahun']; ?></td>
                        <td>Rp <?php echo number_format($row['gaji_pokok'], 0, ',', '.'); ?></td>
                        <td>Rp <?php echo number_format($row['tunjangan'], 0, ',', '.'); ?></td>
                        <td>Rp <?php echo number_format($row['potongan'], 0, ',', '.'); ?></td>
                        <td>Rp <?php echo number_format($total_gaji, 0, ',', '.'); ?></td>
                        <td>
                            <a href="edit.php?id=<?php echo $row['id_slip_gaji']; ?>" class="btn btn-warning btn-sm">Edit</a>
                            <a href="hapus.php?id=<?php echo $row['id_slip_gaji']; ?>" class="btn btn-danger btn-sm" onclick="return confirm('Yakin ingin menghapus data ini?')">Hapus</a>
                            <a href="cetak.php?id=<?php echo $row['id_slip_gaji']; ?>" class="btn btn-success btn-sm" target="_blank">Cetak</a>
                        </td>
                    </tr>
                <?php } ?>
            </tbody>
        </table>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>