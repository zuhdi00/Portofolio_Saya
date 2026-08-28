package com.example.myapplication;

import android.content.Intent;
import android.os.Bundle;
import android.view.View;
import android.widget.ArrayAdapter;
import android.widget.Button;
import android.widget.EditText;
import android.widget.Spinner;
import android.widget.TextView;
import android.widget.Toast;

import androidx.appcompat.app.AlertDialog;
import androidx.appcompat.app.AppCompatActivity;

import com.example.myapplication.api.ApiClient;
import com.example.myapplication.api.ApiService;
import com.example.myapplication.model.ItemDetail;
import com.example.myapplication.model.ServerResponse;
import com.google.gson.Gson;

import retrofit2.Call;
import retrofit2.Callback;
import retrofit2.Response;

public class ScanResultActivity extends AppCompatActivity {
    private TextView resultTextView;
    private Button btnEdit;
    private Button btnApproval;
    private Button btnDelete;
    private ItemDetail detail;
    private EditText editKeterangan;

    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        setContentView(R.layout.activity_scan_result);

        resultTextView = findViewById(R.id.resultTextView);
        btnEdit = findViewById(R.id.btnEdit);
        btnApproval = findViewById(R.id.btnApproval);
        btnDelete = findViewById(R.id.btnDelete);
        editKeterangan = findViewById(R.id.editKeterangan); // Inisialisasi EditText

        // Jika detail ada, tampilkan keterangan
        if (detail != null && detail.cKeterangan != null) {
            editKeterangan.setText(detail.cKeterangan);
        }

        String scannedData = getIntent().getStringExtra("scanned_data");
        String detailJson = getIntent().getStringExtra("detail");

        if (detailJson != null) {
            Gson gson = new Gson();
            detail = gson.fromJson(detailJson, ItemDetail.class);
        }

        // Jika detail ada, tampilkan keterangan
        if (detail != null && detail.cKeterangan != null) {
            editKeterangan.setText(detail.cKeterangan);
        }

        showDetail(scannedData);

        btnEdit.setOnClickListener(v -> showEditOptionsDialog());
        btnApproval.setOnClickListener(v -> approveData()); // Listener Approval
        btnDelete.setOnClickListener(v -> confirmDeleteBarcode());
    }

    private void showDetail(String scannedData) {
        StringBuilder resultBuilder = new StringBuilder();

        if (scannedData != null) {
            resultBuilder.append("Hasil Scan: ").append(scannedData).append("\n\n");
        }

        if (detail != null) {
            resultBuilder.append("Detail Barang:\n");
            resultBuilder.append("No STB       : ").append(detail.cNoSTB).append("\n");
            resultBuilder.append("Nama Barang  : ").append(detail.cNamabrg).append("\n");
            resultBuilder.append("No MC        : ").append(detail.cNoMC).append("\n");
            resultBuilder.append("No OP        : ").append(detail.cNoOp).append("\n");
            resultBuilder.append("Nama Customer: ").append(detail.cNama).append("\n");
            resultBuilder.append("Ukuran       : ")
                    .append(detail.nPanjang).append(" x ")
                    .append(detail.nLebar).append(" x ")
                    .append(detail.nTinggi).append("\n");
            resultBuilder.append("Warna        : ").append(detail.cWarna).append("\n");
            resultBuilder.append("Kualitas     : ")
                    .append(detail.cSub1).append(" / ")
                    .append(detail.cSub2).append(" / ")
                    .append(detail.cSub3).append(" / ")
                    .append(detail.cSub4).append(" / ")
                    .append(detail.cSub5).append("\n");
            resultBuilder.append("Jmlh Barang  : ").append(detail.nQty).append("\n");
            resultBuilder.append("Total KG     : ").append(detail.nQtyKg).append("\n");
            resultBuilder.append("Tgl Serah    : ").append(detail.dTanggal).append("\n");
            resultBuilder.append("Tgl Kirim    : ").append(detail.dTglkirim).append("\n");
            resultBuilder.append("Lokasi       : ").append(detail.cRak).append("\n");

        } else {
            resultBuilder.append("Tidak ada detail barang ditemukan.");
        }

        resultTextView.setText(resultBuilder.toString());
    }

    private void showEditOptionsDialog() {
        String[] options = {"--", "Edit Rak"};
        new AlertDialog.Builder(this)
                .setTitle("Pilih Atribut yang Diedit")
                .setItems(options, (dialog, which) -> {
                    if (which == 0) {
                        //showEditQtyDialog();
                    } else if (which == 1) {
                        showEditRakDialog();
                    }
                })
                .show();
    }

    private void showEditQtyDialog() {
        final View dialogView = getLayoutInflater().inflate(R.layout.dialog_edit_qty, null);
        final TextView qtyInput = dialogView.findViewById(R.id.editQtyInput);
        qtyInput.setText(String.valueOf(detail.nQty));

        new AlertDialog.Builder(this)
                .setTitle("Edit Qty")
                .setView(dialogView)
                .setPositiveButton("Simpan", (dialog, which) -> {
                    try {
                        double newQty = Double.parseDouble(qtyInput.getText().toString());
                        updateQtyToServer(detail.cNoSTB, newQty);
                    } catch (NumberFormatException e) {
                        Toast.makeText(this, "Qty tidak valid", Toast.LENGTH_SHORT).show();
                    }
                })
                .setNegativeButton("Batal", null)
                .show();
    }

    private void showEditRakDialog() {
        final View dialogView = getLayoutInflater().inflate(R.layout.dialog_edit_rak, null);
        final Spinner rakSpinner = dialogView.findViewById(R.id.spinnerRak);

        String[] rakOptions = {
                "A-1", "A-2", "B-1", "B-2", "C-1", "C-2", "CORRUGATING 1", "CORRUGATING 2",
                "FOLDER GLUE", "FLADBAD", "FLEXO-1", "FLEXO-2", "FLEXO-4", "FLEXO-5",
                "FLEXO-6", "FLEXO-7", "FLEXO-8", "FLEXO-9", "IKAT", "LANTHEC", "LANGSUNG KIRIM",
                "RDC", "RAK-A", "RAK-B", "SLITTER", "STITCHING", "Area A-1", "Area A-2",
                "GBJ 2 R-A", "GBJ 2 R-B", "GBJ 2 R-C", "GBJ 2 R-D",
                // RA 1 - RA 24
                "RA 1", "RA 2", "RA 3", "RA 4", "RA 5", "RA 6", "RA 7", "RA 8", "RA 9", "RA 10",
                "RA 11", "RA 12", "RA 13", "RA 14", "RA 15", "RA 16", "RA 17", "RA 18", "RA 19", "RA 20",
                "RA 21", "RA 22", "RA 23", "RA 24","RA 25", "RA 26", "RA 27", "RA 28", "RA 29", "RA 30",
                "RA 31", "RA 32", "RA 33", "RA 34","RA 35", "RA 36", "RA 37", "RA 38", "RA 39", "RA 40",
                "RA 41", "RA 42", "RA 43", "RA 44","RA 45", "RA 46", "RA 47", "RA 48", "RA 49", "RA 50",

                // RB 1 - RB 24
                "RB 1", "RB 2", "RB 3", "RB 4", "RB 5", "RB 6", "RB 7", "RB 8", "RB 9", "RB 10",
                "RB 11", "RB 12", "RB 13", "RB 14", "RB 15", "RB 16", "RB 17", "RB 18", "RB 19", "RB 20",
                "RB 21", "RB 22", "RB 23", "RB 24", "RB 25", "RB 26", "RB 27", "RB 28", "RB 29", "RB 30",
                "RB 31", "RB 32", "RB 33", "RB 34", "RB 35", "RB 36", "RB 37", "RB 38", "RB 39", "RB 40",
                "RB 41", "RB 42", "RB 43", "RB 44", "RB 45", "RB 46", "RB 47", "RB 48", "RB 49", "RB 50",

                // RC 1 - RC 24
                "RC 1", "RC 2", "RC 3", "RC 4", "RC 5", "RC 6", "RC 7", "RC 8", "RC 9", "RC 10",
                "RC 11", "RC 12", "RC 13", "RC 14", "RC 15", "RC 16", "RC 17", "RC 18", "RC 19", "RC 20",
                "RC 21", "RC 22", "RC 23", "RC 24", "RC 25", "RC 26", "RC 27", "RC 28", "RC 29", "RC 30",
                "RC 31", "RC 32", "RC 33", "RC 34", "RC 35", "RC 36", "RC 37", "RC 38", "RC 39", "RC 40",
                "RC 41", "RC 42", "RC 43", "RC 44", "RC 45", "RC 46", "RC 47", "RC 48", "RC 49", "RC 50",

                // RD 1 - RD 24
                "RD 1", "RD 2", "RD 3", "RD 4", "RD 5", "RD 6", "RD 7", "RD 8", "RD 9", "RD 10",
                "RD 11", "RD 12", "RD 13", "RD 14", "RD 15", "RD 16", "RD 17", "RD 18", "RD 19", "RD 20",
                "RD 21", "RD 22", "RD 23", "RD 24","RD 25", "RD 26", "RD 27", "RD 28", "RD 29", "RD 30",
                "RD 31", "RD 32", "RD 33", "RD 34","RD 35", "RD 36", "RD 37", "RD 38", "RD 39", "RD 40",
                "RD 41", "RD 42", "RD 43", "RD 44","RD 45", "RD 46", "RD 47", "RD 48", "RD 49", "RD 50",

        };

        ArrayAdapter<String> adapter = new ArrayAdapter<>(this, android.R.layout.simple_spinner_dropdown_item, rakOptions);
        rakSpinner.setAdapter(adapter);

        // Set spinner selection to current rack if it exists
        if (detail.cRak != null) {
            for (int i = 0; i < rakOptions.length; i++) {
                if (rakOptions[i].equals(detail.cRak)) {
                    rakSpinner.setSelection(i);
                    break;
                }
            }
        }

        new AlertDialog.Builder(this)
                .setTitle("Edit Rak")
                .setView(dialogView)
                .setPositiveButton("Simpan", (dialog, which) -> {
                    String selectedRakName = rakSpinner.getSelectedItem().toString();
                    // Langsung kirim nama rak ke server, bukan nomor
                    updateRakToServer(detail.cNoSTB, selectedRakName);
                })
                .setNegativeButton("Batal", null)
                .show();
    }

    private void updateQtyToServer(String cNoSTB, double newQty) {
        ApiService service = ApiClient.getClient().create(ApiService.class);
<<<<<<< HEAD
        service.updateQty(cNoSTB, newQty).enqueue(new Callback<ServerResponse>() {
            @Override
            public void onResponse(Call<ServerResponse> call, Response<ServerResponse> response) {
                if (response.isSuccessful() && response.body() != null && response.body().isSuccess()) {
                    detail.nQty = newQty;
                    showDetail(null);
                    Toast.makeText(ScanResultActivity.this, "Qty berhasil diperbarui", Toast.LENGTH_SHORT).show();
                } else {
                    Toast.makeText(ScanResultActivity.this, "Gagal update Qty", Toast.LENGTH_SHORT).show();
                }
            }

            @Override
            public void onFailure(Call<ServerResponse> call, Throwable t) {
                Toast.makeText(ScanResultActivity.this, "Gagal koneksi ke server", Toast.LENGTH_SHORT).show();
            }
        });
=======
        service.updateQty(cNoSTB, newQty)
                .enqueue(new Callback<ServerResponse>() {
                    @Override
                    public void onResponse(Call<ServerResponse> call, Response<ServerResponse> response) {
                        if (response.isSuccessful() && response.body() != null && response.body().isSuccess()) {
                            detail.nQty = newQty;
                            showDetail(null);
                            Toast.makeText(ScanResultActivity.this, "Qty berhasil diperbarui", Toast.LENGTH_SHORT).show();
                        } else {
                            Toast.makeText(ScanResultActivity.this, "Gagal update Qty", Toast.LENGTH_SHORT).show();
                        }
                    }

                    @Override
                    public void onFailure(Call<ServerResponse> call, Throwable t) {
                        Toast.makeText(ScanResultActivity.this, "Gagal koneksi ke server", Toast.LENGTH_SHORT).show();
                    }
                });
>>>>>>> 3e81cfb (Update 01 November 2025)
    }

    private void updateRakToServer(String cNoSTB, String rakName) {
        ApiService service = ApiClient.getClient().create(ApiService.class);
<<<<<<< HEAD
        service.updateRak(cNoSTB, rakName).enqueue(new Callback<ServerResponse>() {
            @Override
            public void onResponse(Call<ServerResponse> call, Response<ServerResponse> response) {
                if (response.isSuccessful() && response.body() != null && response.body().isSuccess()) {
                    detail.cRak = rakName;  
                    showDetail(null);
                    Toast.makeText(ScanResultActivity.this, "Rak berhasil diperbarui", Toast.LENGTH_SHORT).show();
                } else {
                    Toast.makeText(ScanResultActivity.this, "Gagal update Rak", Toast.LENGTH_SHORT).show();
                }
            }

            @Override
            public void onFailure(Call<ServerResponse> call, Throwable t) {
                Toast.makeText(ScanResultActivity.this, "Gagal koneksi ke server", Toast.LENGTH_SHORT).show();
            }
        });
=======
        service.updateRak(cNoSTB, rakName)
                .enqueue(new Callback<ServerResponse>() {
                    @Override
                    public void onResponse(Call<ServerResponse> call, Response<ServerResponse> response) {
                        if (response.isSuccessful() && response.body() != null && response.body().isSuccess()) {
                            detail.cRak = rakName;  
                            showDetail(null);
                            Toast.makeText(ScanResultActivity.this, "Rak berhasil diperbarui", Toast.LENGTH_SHORT).show();
                        } else {
                            Toast.makeText(ScanResultActivity.this, "Gagal update Rak", Toast.LENGTH_SHORT).show();
                        }
                    }

                    @Override
                    public void onFailure(Call<ServerResponse> call, Throwable t) {
                        Toast.makeText(ScanResultActivity.this, "Gagal koneksi ke server", Toast.LENGTH_SHORT).show();
                    }
                });
>>>>>>> 3e81cfb (Update 01 November 2025)
    }

    private void approveData() {
        if (detail == null) return;
        String keterangan = editKeterangan.getText().toString();

        new AlertDialog.Builder(this)
            .setTitle("Konfirmasi Approval")
            .setMessage("Yakin ingin melakukan approval untuk barcode ini?")
            .setPositiveButton("Approve", (dialog, which) -> {
                ApiService service = ApiClient.getClient().create(ApiService.class);
                service.approveStb(detail.cNoSTB, keterangan).enqueue(new Callback<ServerResponse>() {
                    @Override
                    public void onResponse(Call<ServerResponse> call, Response<ServerResponse> response) {
                        if (response.isSuccessful() && response.body() != null && response.body().isSuccess()) {
                            detail.lPosted = "1";
                            detail.cKeterangan = keterangan;
                            Toast.makeText(ScanResultActivity.this, "Approval berhasil", Toast.LENGTH_SHORT).show();
                            Intent intent = new Intent(ScanResultActivity.this, BarcodeScannerActivity.class);
                            intent.addFlags(Intent.FLAG_ACTIVITY_CLEAR_TOP | Intent.FLAG_ACTIVITY_NEW_TASK);
                            startActivity(intent);
                            finish();
                        } else {
                            Toast.makeText(ScanResultActivity.this, "Gagal approval", Toast.LENGTH_SHORT).show();
                        }
                    }

                    @Override
                    public void onFailure(Call<ServerResponse> call, Throwable t) {
                        Toast.makeText(ScanResultActivity.this, "Gagal koneksi ke server", Toast.LENGTH_SHORT).show();
                    }
                });
            })
            .setNegativeButton("Batal", null)
            .show();
    }

    private void confirmDeleteBarcode() {
        if (detail == null) return;
        new AlertDialog.Builder(this)
            .setTitle("Konfirmasi Hapus")
            .setMessage("Yakin ingin menolak STB ini?")
            .setPositiveButton("Hapus", (dialog, which) -> deleteBarcodeFromServer(detail.cNoSTB))
            .setNegativeButton("Batal", null)
            .show();
    }

    private void deleteBarcodeFromServer(String barcode) {
        ApiService service = ApiClient.getClient().create(ApiService.class);
<<<<<<< HEAD
        service.deleteBarcode(barcode).enqueue(new Callback<ServerResponse>() {
            @Override
            public void onResponse(Call<ServerResponse> call, Response<ServerResponse> response) {
                if (response.isSuccessful() && response.body() != null && response.body().isSuccess()) {
                    Toast.makeText(ScanResultActivity.this, "Barcode berhasil dihapus", Toast.LENGTH_SHORT).show();
                    finish(); // Kembali ke activity sebelumnya
                } else {
                    Toast.makeText(ScanResultActivity.this, "Gagal menghapus barcode", Toast.LENGTH_SHORT).show();
                }
            }

            @Override
            public void onFailure(Call<ServerResponse> call, Throwable t) {
                Toast.makeText(ScanResultActivity.this, "Gagal koneksi ke server", Toast.LENGTH_SHORT).show();
            }
        });
=======
        service.deleteBarcode(barcode)
                .enqueue(new Callback<ServerResponse>() {
                    @Override
                    public void onResponse(Call<ServerResponse> call, Response<ServerResponse> response) {
                        if (response.isSuccessful() && response.body() != null && response.body().isSuccess()) {
                            Toast.makeText(ScanResultActivity.this, "Barcode berhasil dihapus", Toast.LENGTH_SHORT).show();
                            finish(); // Kembali ke activity sebelumnya
                        } else {
                            Toast.makeText(ScanResultActivity.this, "Gagal menghapus barcode", Toast.LENGTH_SHORT).show();
                        }
                    }

                    @Override
                    public void onFailure(Call<ServerResponse> call, Throwable t) {
                        Toast.makeText(ScanResultActivity.this, "Gagal koneksi ke server", Toast.LENGTH_SHORT).show();
                    }
                });
>>>>>>> 3e81cfb (Update 01 November 2025)
    }
}

