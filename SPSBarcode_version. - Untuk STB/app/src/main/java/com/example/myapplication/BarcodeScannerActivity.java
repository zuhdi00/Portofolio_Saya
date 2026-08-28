package com.example.myapplication;

import android.Manifest;
import android.content.Intent;
import android.content.pm.PackageManager;
import android.os.Bundle;
import android.util.Size;
import android.view.View;
import android.view.animation.Animation;
import android.view.animation.AnimationUtils;
import android.widget.Button;
import android.widget.Toast;

import androidx.annotation.OptIn;
import androidx.camera.core.ExperimentalGetImage;

import androidx.annotation.NonNull;
import androidx.appcompat.app.AppCompatActivity;
import androidx.camera.core.CameraSelector;
import androidx.camera.core.ExperimentalGetImage;
import androidx.camera.core.ImageAnalysis;
import androidx.camera.core.ImageProxy;
import androidx.camera.core.Preview;
import androidx.camera.lifecycle.ProcessCameraProvider;
import androidx.camera.view.PreviewView;
import androidx.core.app.ActivityCompat;
import androidx.core.content.ContextCompat;

import com.example.myapplication.api.ApiClient;
import com.example.myapplication.api.ApiService;
import com.google.common.util.concurrent.ListenableFuture;
import com.google.gson.Gson;
import com.google.mlkit.vision.barcode.BarcodeScanner;
import com.google.mlkit.vision.barcode.BarcodeScanning;
import com.google.mlkit.vision.barcode.common.Barcode;
import com.google.mlkit.vision.common.InputImage;

import java.util.concurrent.ExecutionException;
import java.util.concurrent.ExecutorService;
import java.util.concurrent.Executors;

import retrofit2.Call;
import retrofit2.Callback;
import retrofit2.Response;

public class BarcodeScannerActivity extends AppCompatActivity {

    private ExecutorService cameraExecutor;
    private BarcodeScanner scanner;
    private PreviewView previewView;
    private boolean hasScanned = false;

    private static final int CAMERA_PERMISSION_REQUEST = 1001;

    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        setContentView(R.layout.activity_barcode_scanner);

        previewView = findViewById(R.id.previewView);

        cameraExecutor = Executors.newSingleThreadExecutor();
        scanner = BarcodeScanning.getClient();

        View scanLine = findViewById(R.id.scanLine);
        Animation animation = AnimationUtils.loadAnimation(this, R.anim.scan_line_anim);
        scanLine.startAnimation(animation);

        Button btnHistory = findViewById(R.id.btnHistory);
        Button btnDetail = findViewById(R.id.btnDetail);

        btnHistory.setOnClickListener(v -> {
            Intent intent = new Intent(BarcodeScannerActivity.this, ScanHistoryActivity.class);
            startActivity(intent);
        });

        btnDetail.setOnClickListener(v -> {
            Intent intent = new Intent(BarcodeScannerActivity.this, ItemDetailActivity.class);
            startActivity(intent);
        });

        if (ContextCompat.checkSelfPermission(this, Manifest.permission.CAMERA) == PackageManager.PERMISSION_GRANTED) {
            startCamera();
            Toast.makeText(this, "Kamera aktif", Toast.LENGTH_SHORT).show();
        } else {
            ActivityCompat.requestPermissions(this, new String[]{Manifest.permission.CAMERA}, CAMERA_PERMISSION_REQUEST);
        }
    }

    @OptIn(markerClass = ExperimentalGetImage.class)
    private void startCamera() {
        ListenableFuture<ProcessCameraProvider> cameraProviderFuture = ProcessCameraProvider.getInstance(this);

        cameraProviderFuture.addListener(() -> {
            try {
                ProcessCameraProvider cameraProvider = cameraProviderFuture.get();

                Preview preview = new Preview.Builder().build();
                preview.setSurfaceProvider(previewView.getSurfaceProvider());

                ImageAnalysis imageAnalysis = new ImageAnalysis.Builder()
                        .setTargetResolution(new Size(1280, 720))
                        .setBackpressureStrategy(ImageAnalysis.STRATEGY_KEEP_ONLY_LATEST)
                        .build();

                imageAnalysis.setAnalyzer(cameraExecutor, this::processImageProxy);

                CameraSelector cameraSelector = CameraSelector.DEFAULT_BACK_CAMERA;

                cameraProvider.unbindAll();
                cameraProvider.bindToLifecycle(
                        this,
                        cameraSelector,
                        preview,
                        imageAnalysis
                );

            } catch (ExecutionException | InterruptedException e) {
                e.printStackTrace();
            }

        }, ContextCompat.getMainExecutor(this));
    }

    @ExperimentalGetImage
    private void processImageProxy(ImageProxy image) {
        if (image.getImage() == null) {
            image.close();
            return;
        }

        InputImage inputImage = InputImage.fromMediaImage(image.getImage(), image.getImageInfo().getRotationDegrees());

        scanner.process(inputImage)
                .addOnSuccessListener(barcodes -> {
                    for (Barcode barcode : barcodes) {
                        String cNoOpValue = barcode.getRawValue(); 
                        if (!hasScanned && cNoOpValue != null) {
                            hasScanned = true;

                            // Simpan ke local history
                            ScanHistoryManager historyManager = new ScanHistoryManager(this);
                            historyManager.saveScan(cNoOpValue);

                            // Panggil API berdasarkan cNoSTB
                            runOnUiThread(() -> {
                                ApiService apiService = ApiClient.getClient().create(ApiService.class);
<<<<<<< HEAD
                                apiService.getItemDetail(cNoOpValue).enqueue(new Callback<>() {
                                    @Override
                                    public void onResponse(Call<ItemDetailResponse> call, Response<ItemDetailResponse> response) {
                                        if (response.isSuccessful() && response.body() != null && response.body().isSuccess()) {
                                            Intent intent = new Intent(BarcodeScannerActivity.this, ScanResultActivity.class);
                                            intent.putExtra("scanned_data", cNoOpValue);

                                            String detailJson = new Gson().toJson(response.body().getData());
                                            intent.putExtra("detail", detailJson);

                                            // Simpan hasil scan ke history lokal dalam format JSON
                                            ScanHistoryManager historyManager = new ScanHistoryManager(BarcodeScannerActivity.this);
                                            historyManager.saveScan(detailJson);

                                            startActivity(intent);
                                            //finish();
                                        } else {
                                            Toast.makeText(BarcodeScannerActivity.this, "Data tidak ditemukan untuk Nomor STB: " + cNoOpValue, Toast.LENGTH_SHORT).show();
                                            hasScanned = false;
                                        }
                                    }

                                    @Override
                                    public void onFailure(Call<ItemDetailResponse> call, Throwable t) {
                                        Toast.makeText(BarcodeScannerActivity.this, "Gagal menghubungi server", Toast.LENGTH_SHORT).show();
                                        hasScanned = false;
                                    }
                                });
=======
                                apiService.getItemDetail("api_get_item_detail_29092025.php", cNoOpValue)
                                        .enqueue(new Callback<>() {
                                            @Override
                                            public void onResponse(Call<ItemDetailResponse> call, Response<ItemDetailResponse> response) {
                                                if (response.isSuccessful() && response.body() != null && response.body().isSuccess()) {
                                                    Intent intent = new Intent(BarcodeScannerActivity.this, ScanResultActivity.class);
                                                    intent.putExtra("scanned_data", cNoOpValue);

                                                    String detailJson = new Gson().toJson(response.body().getData());
                                                    intent.putExtra("detail", detailJson);

                                                    // Simpan hasil scan ke history lokal dalam format JSON
                                                    ScanHistoryManager historyManager = new ScanHistoryManager(BarcodeScannerActivity.this);
                                                    historyManager.saveScan(detailJson);

                                                    startActivity(intent);
                                                    //finish();
                                                } else {
                                                    Toast.makeText(BarcodeScannerActivity.this, "Data tidak ditemukan untuk Nomor STB: " + cNoOpValue, Toast.LENGTH_SHORT).show();
                                                    hasScanned = false;
                                                }
                                            }

                                            @Override
                                            public void onFailure(Call<ItemDetailResponse> call, Throwable t) {
                                                Toast.makeText(BarcodeScannerActivity.this, "Gagal menghubungi server", Toast.LENGTH_SHORT).show();
                                                hasScanned = false;
                                            }
                                        });
>>>>>>> 3e81cfb (Update 01 November 2025)
                            });

                            break; 
                        }
                    }
                })
                .addOnFailureListener(Throwable::printStackTrace)
                .addOnCompleteListener(task -> image.close());
    }

    @Override
    protected void onDestroy() {
        super.onDestroy();
        cameraExecutor.shutdown();
    }

    @Override
    public void onRequestPermissionsResult(int requestCode, @NonNull String[] permissions,
                                           @NonNull int[] grantResults) {
        super.onRequestPermissionsResult(requestCode, permissions, grantResults);

        if (requestCode == CAMERA_PERMISSION_REQUEST) {
            if (grantResults.length > 0 && grantResults[0] == PackageManager.PERMISSION_GRANTED) {
                startCamera();
                Toast.makeText(this, "Izin kamera diberikan", Toast.LENGTH_SHORT).show();
            } else {
                Toast.makeText(this, "Izin kamera ditolak", Toast.LENGTH_SHORT).show();
                finish();
            }
        }
    }
}
