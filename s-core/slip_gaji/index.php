<?php
// Koneksi database
include '../connection.php';
include '../template/header.php';
include '../template/sidebar.php';

// Query untuk mengambil data slip gaji
$query = "SELECT sg.*, p.nama_peg 
          FROM slip_gaji sg 
          LEFT JOIN pegawai p ON sg.id_peg = p.id_peg 
          ORDER BY sg.id_slip_gaji DESC";
$result = mysqli_query($conn, $query);
?>

<!-- Main content -->
<div class="content-wrapper">
    <section class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1>Data Slip Gaji</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="#">Home</a></li>
                        <li class="breadcrumb-item active">Slip Gaji</li>
                    </ol>
                </div>
            </div>
        </div>
    </section>

    <section class="content">
        <div class="container-fluid">
            <div class="row">
                <div class="col-md-8 mx-auto">
                    <div class="card">
                        <div class="card-header">
                            <a href="tambah.php" class="btn btn-primary btn-sm">Tambah Slip Gaji</a>
                        </div>
                        <div class="card-body">
                            <table id="example1" class="table table-bordered table-striped table-sm">
                                <thead>
                                    <tr>
                                        <th>No</th>
                                        <th>ID Slip Gaji</th>
                                        <th>Nama Pegawai</th>
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
                                            <td><?php echo $row['nama_peg']; ?></td>
                                            <td><?php echo $row['bulan_tahun']; ?></td>
                                            <td>Rp <?php echo number_format($row['gaji_pokok'], 0, ',', '.'); ?></td>
                                            <td>Rp <?php echo number_format($row['tunjangan'], 0, ',', '.'); ?></td>
                                            <td>Rp <?php echo number_format($row['potongan'], 0, ',', '.'); ?></td>
                                            <td>Rp <?php echo number_format($total_gaji, 0, ',', '.'); ?></td>
                                            <td>
                                                <a href="edit.php?id=<?php echo $row['id_slip_gaji']; ?>" class="btn btn-warning btn-sm">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <a href="hapus.php?id=<?php echo $row['id_slip_gaji']; ?>" class="btn btn-danger btn-sm" onclick="return confirm('Yakin ingin menghapus data ini?')">
                                                    <i class="fas fa-trash"></i>
                                                </a>
                                                <a href="cetak.php?id=<?php echo $row['id_slip_gaji']; ?>" class="btn btn-success btn-sm" target="_blank">
                                                    <i class="fas fa-print"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    <?php } ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>

<?php
include '../template/footer.php';
?>

<script>
    $(function() {
        $("#example1").DataTable({
            "responsive": true,
            "lengthChange": false,
            "autoWidth": false,
            "buttons": ["copy", "csv", "excel", "pdf", "print", "colvis"]
        }).buttons().container().appendTo('#example1_wrapper .col-md-6:eq(0)');
    });
</script>