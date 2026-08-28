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

import android.util.Log;
import java.util.ArrayList;
import java.util.List;
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
    private EditText editNBerat2;

    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        setContentView(R.layout.activity_scan_result);

        resultTextView = findViewById(R.id.resultTextView);
        btnEdit = findViewById(R.id.btnEdit);
        btnApproval = findViewById(R.id.btnApproval);
        btnDelete = findViewById(R.id.btnDelete);
        editKeterangan = findViewById(R.id.editKeterangan); // Inisialisasi EditText
        editNBerat2 = findViewById(R.id.editNBerat2);

        // Jika detail ada, tampilkan keterangan
        if (detail != null && detail.cKeterangan != null) {
            editKeterangan.setText(detail.cKeterangan);
        }

        // Jika detail ada dan memiliki nilai nBerat2 (jika model menyertakan), tampilkan
        try {
            java.lang.reflect.Field berat2Field = detail.getClass().getDeclaredField("nBerat2");
            berat2Field.setAccessible(true);
            Object berat2Val = berat2Field.get(detail);
            if (berat2Val != null) editNBerat2.setText(String.valueOf(berat2Val));
        } catch (Exception ignored) {
            // field tidak tersedia, biarkan kosong
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
            resultBuilder.append("No WIP       : ").append(detail.cNoSTB).append("\n");
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

            // Tampilkan No Palet (nIsi), Shift dan Kepala Shift jika tersedia di model
            try {
                java.lang.reflect.Field nIsiField = detail.getClass().getDeclaredField("nIsi");
                nIsiField.setAccessible(true);
                Object nIsiVal = nIsiField.get(detail);
                if (nIsiVal != null) resultBuilder.append("No Palet     : ").append(String.valueOf(nIsiVal)).append("\n");
            } catch (Exception ignored) {}

            try {
                java.lang.reflect.Field shiftField = detail.getClass().getDeclaredField("cShift");
                shiftField.setAccessible(true);
                Object shiftVal = shiftField.get(detail);
                if (shiftVal != null) resultBuilder.append("Shift        : ").append(String.valueOf(shiftVal)).append("\n");
            } catch (Exception ignored) {}

            try {
                java.lang.reflect.Field kashiftField = detail.getClass().getDeclaredField("cKashift");
                kashiftField.setAccessible(true);
                Object kashiftVal = kashiftField.get(detail);
                if (kashiftVal != null) resultBuilder.append("Kepala Shift : ").append(String.valueOf(kashiftVal)).append("\n");
            } catch (Exception ignored) {}

        } else {
            resultBuilder.append("Tidak ada detail barang ditemukan.");
        }

        resultTextView.setText(resultBuilder.toString());
    }

    private void showEditOptionsDialog() {
        String[] options = {"Edit Qty", "Edit Lokasi"};
        new AlertDialog.Builder(this)
                .setTitle("Pilih Atribut yang Diedit")
                .setItems(options, (dialog, which) -> {
                    if (which == 0) {
                        showEditQtyDialog();
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

        // Generate rakOptions programmatically: F1.A .. F9.D
        List<String> rakList = new ArrayList<>();
        for (int f = 1; f <= 9; f++) {
            for (char c = 'A'; c <= 'D'; c++) {
                rakList.add("F" + f + "." + c);
            }
        }
        String[] rakOptions = rakList.toArray(new String[0]);

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
                .setTitle("Edit Lokasi")
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
                        Log.e("UpdateQty", "onFailure", t);
                        Toast.makeText(ScanResultActivity.this, "Gagal koneksi ke server: " + t.getMessage(), Toast.LENGTH_SHORT).show();
                    }
                });
    }

    private void updateRakToServer(String cNoSTB, String rakName) {
        ApiService service = ApiClient.getClient().create(ApiService.class);
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
                        Log.e("UpdateRak", "onFailure", t);
                        Toast.makeText(ScanResultActivity.this, "Gagal koneksi ke server: " + t.getMessage(), Toast.LENGTH_SHORT).show();
                    }
                });
    }

    private void approveData() {
        if (detail == null) return;
        String keterangan = editKeterangan.getText().toString();

        // Gunakan field yang ada, fallback ke "" jika cType tidak ada di ItemDetail
        final String cTipe;
        String tempTipe = "";
        try {
            java.lang.reflect.Field tipeField = detail.getClass().getDeclaredField("cType");
            tipeField.setAccessible(true);
            Object tipeValue = tipeField.get(detail);
            if (tipeValue != null) tempTipe = tipeValue.toString();
        } catch (Exception e) {
            // cType tidak ada, biarkan kosong
        }
        cTipe = tempTipe;

        new AlertDialog.Builder(this)
            .setTitle("Konfirmasi Approval")
            .setMessage("Yakin ingin melakukan approval untuk barcode ini?")
            .setPositiveButton("Approve", (dialog, which) -> {
                ApiService service = ApiClient.getClient().create(ApiService.class);
                String nBerat2Value = editNBerat2.getText().toString();

                // Ambil nilai No Palet (nIsi), Shift dan Kepala Shift dari model jika tersedia
                String nQtyPaletValue = "";
                String cShiftValue = "";
                String cKashiftValue = "";
                try {
                    java.lang.reflect.Field f = detail.getClass().getDeclaredField("nIsi");
                    f.setAccessible(true);
                    Object v = f.get(detail);
                    if (v != null) nQtyPaletValue = String.valueOf(v);
                } catch (Exception ignored) {}
                try {
                    java.lang.reflect.Field f2 = detail.getClass().getDeclaredField("cShift");
                    f2.setAccessible(true);
                    Object v2 = f2.get(detail);
                    if (v2 != null) cShiftValue = String.valueOf(v2);
                } catch (Exception ignored) {}
                try {
                    java.lang.reflect.Field f3 = detail.getClass().getDeclaredField("cKashift");
                    f3.setAccessible(true);
                    Object v3 = f3.get(detail);
                    if (v3 != null) cKashiftValue = String.valueOf(v3);
                } catch (Exception ignored) {}
                try {
                    java.lang.reflect.Field f4 = detail.getClass().getDeclaredField("cKeterangan");
                }

                service.approveStbCheck(detail.cNoSTB, keterangan, cTipe, nBerat2Value, nQtyPaletValue, cShiftValue, cKashiftValue).enqueue(new Callback<ServerResponse>() {
                    @Override
                    public void onResponse(Call<ServerResponse> call, Response<ServerResponse> response) {
                        if (response.isSuccessful() && response.body() != null) {
                            ServerResponse resp = response.body();
                            if (resp.success) {
                                detail.lPosted = "1";
                                detail.cKeterangan = keterangan;
                                Toast.makeText(ScanResultActivity.this, "Approval berhasil", Toast.LENGTH_SHORT).show();
                                Intent intent = new Intent(ScanResultActivity.this, BarcodeScannerActivity.class);
                                intent.addFlags(Intent.FLAG_ACTIVITY_CLEAR_TOP | Intent.FLAG_ACTIVITY_NEW_TASK);
                                startActivity(intent);
                                finish();
                            } else {
                                if (resp.message != null && resp.message.contains("STB Melebihi OP + toleransi")) {
                                    Toast.makeText(ScanResultActivity.this, "STB Melebihi OP + toleransi", Toast.LENGTH_LONG).show();
                                } else {
                                    Toast.makeText(ScanResultActivity.this, resp.message != null ? resp.message : "Approval gagal", Toast.LENGTH_LONG).show();
                                }
                            }
                        } else {
                            Log.e("Approve", "Response not successful. code=" + response.code());
                            Toast.makeText(ScanResultActivity.this, "Gagal menghubungi server (code: " + response.code() + ")", Toast.LENGTH_SHORT).show();
                        }
                    }

                    @Override
                    public void onFailure(Call<ServerResponse> call, Throwable t) {
                        Toast.makeText(ScanResultActivity.this, "Error: " + t.getMessage(), Toast.LENGTH_SHORT).show();
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
                        Log.e("DeleteBarcode", "onFailure", t);
                        Toast.makeText(ScanResultActivity.this, "Gagal koneksi ke server: " + t.getMessage(), Toast.LENGTH_SHORT).show();
                    }
                });
    }
}

