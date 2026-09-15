<?php
include '../connection.php';
include '../template/header.php';
include '../template/sidebar.php';

// Query untuk data pegawai
$query_pegawai = "SELECT id_peg, nama_peg FROM pegawai";
$result_pegawai = mysqli_query($conn, $query_pegawai);
?>

<div class="content-wrapper">
    <section class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1>Input Slip Gaji</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="#">Home</a></li>
                        <li class="breadcrumb-item active">Input Slip Gaji</li>
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
                            <h3 class="card-title">Form Input Slip Gaji</h3>
                        </div>
                        <form action="submit_slip_gaji.php" method="POST">
                            <div class="card-body">
                                <div class="form-group">
                                    <label for="id_slip_gaji">ID Slip Gaji</label>
                                    <input type="text" class="form-control" name="id_slip_gaji" required>
                                </div>

                        <div class="mb-3">
                            <label for="pegawai" class="form-label">Nama Pegawai</label>
                            <input type="text" class="form-control" id="nama_pegawai" name="nama_pegawai" required>
                        </div>

                                <div class="form-group">
                                    <label for="bulan_tahun">Periode Gaji</label>
                                    <input type="date" class="form-control" name="bulan_tahun" required>
                                </div>

                                <div class="form-group">
                                    <label for="gaji_pokok">Gaji Pokok</label>
                                    <div class="input-group">
                                        <div class="input-group-prepend">
                                            <span class="input-group-text">Rp</span>
                                        </div>
                                        <input type="number" class="form-control" name="gaji_pokok" id="gaji_pokok" required>
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label for="tunjangan">Tunjangan</label>
                                    <div class="input-group">
                                        <div class="input-group-prepend">
                                            <span class="input-group-text">Rp</span>
                                        </div>
                                        <input type="number" class="form-control" name="tunjangan" id="tunjangan" required>
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label for="potongan">Potongan</label>
                                    <div class="input-group">
                                        <div class="input-group-prepend">
                                            <span class="input-group-text">Rp</span>
                                        </div>
                                        <input type="number" class="form-control" name="potongan" id="potongan" required>
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label for="total_gaji">Total Gaji</label>
                                    <div class="input-group">
                                        <div class="input-group-prepend">
                                            <span class="input-group-text">Rp</span>
                                        </div>
                                        <input type="number" class="form-control" id="total_gaji" readonly>
                                    </div>
                                </div>
                            </div>

                            <div class="card-footer">
                                <button type="submit" class="btn btn-primary">Simpan</button>
                                <a href="index.php" class="btn btn-secondary">Batal</a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>

<?php include '../template/footer.php'; ?>

<script>
$(document).ready(function() {
    // Fungsi untuk menghitung total gaji
    function hitungTotal() {
        var gaji_pokok = parseInt($('#gaji_pokok').val()) || 0;
        var tunjangan = parseInt($('#tunjangan').val()) || 0;
        var potongan = parseInt($('#potongan').val()) || 0;
        var total = gaji_pokok + tunjangan - potongan;
        $('#total_gaji').val(total);
    }

    // Hitung total setiap kali input berubah
    $('#gaji_pokok, #tunjangan, #potongan').on('input', function() {
        hitungTotal();
    });
});
</script>
