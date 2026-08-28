package com.example.myapplication;

import android.os.Bundle;
import android.util.Log;
import android.widget.Toast;
import android.view.Menu;
import android.content.Intent;
<<<<<<< HEAD
=======
import com.google.gson.Gson;
>>>>>>> 3e81cfb (Update 01 November 2025)

import com.example.myapplication.api.ApiService;
import com.example.myapplication.api.ApiResponse;
import com.example.myapplication.api.ApiData;
import com.example.myapplication.model.TestResponses;
<<<<<<< HEAD
import com.example.myapplication.api.ApiResponse;
=======
import com.example.myapplication.api.TestConnectionResponse;
>>>>>>> 3e81cfb (Update 01 November 2025)

import com.google.android.material.navigation.NavigationView;

import androidx.appcompat.app.AppCompatActivity;
import androidx.navigation.NavController;
import androidx.navigation.ui.AppBarConfiguration;
import androidx.navigation.ui.NavigationUI;
import androidx.drawerlayout.widget.DrawerLayout;
import androidx.navigation.fragment.NavHostFragment;

import com.example.myapplication.databinding.ActivityMainBinding;

import retrofit2.Call;
import retrofit2.Callback;
import retrofit2.Response;
import retrofit2.Retrofit;
<<<<<<< HEAD
import retrofit2.converter.gson.GsonConverterFactory;
=======

import com.example.myapplication.api.ApiClient;
>>>>>>> 3e81cfb (Update 01 November 2025)

public class MainActivity extends AppCompatActivity {

    private AppBarConfiguration mAppBarConfiguration;

    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);

        ActivityMainBinding binding = ActivityMainBinding.inflate(getLayoutInflater());
        setContentView(binding.getRoot());

<<<<<<< HEAD
        // Retrofit Setup
        Retrofit retrofit = new Retrofit.Builder()
                .baseUrl("http://36.81.162.214:8081/")
                .addConverterFactory(GsonConverterFactory.create())
                .build();

        ApiService apiService = retrofit.create(ApiService.class);

        // ✅ TEST KONEKSI API
        apiService.testConnection().enqueue(new Callback<TestResponses>() {
            @Override
            public void onResponse(Call<TestResponses> call, Response<TestResponses> response) {
                if (response.isSuccessful() && response.body() != null) {
                    Toast.makeText(MainActivity.this, "✅ API: " + response.body().message, Toast.LENGTH_SHORT).show();
                } else {
                    Toast.makeText(MainActivity.this, "⚠️ Gagal parsing response", Toast.LENGTH_SHORT).show();
=======
        // Gunakan ApiClient yang sudah menunjuk ke supracor.co.id
        Retrofit retrofit = ApiClient.getClient();
        ApiService apiService = ApiClient.getClient().create(ApiService.class);
        Call<TestConnectionResponse> call = apiService.testConnection("test_connection.php");
        call.enqueue(new Callback<TestConnectionResponse>() {
            @Override
            public void onResponse(Call<TestConnectionResponse> call, Response<TestConnectionResponse> response) {
                if (response.isSuccessful() && response.body() != null) {
                    TestConnectionResponse result = response.body();
                    // Contoh akses data
                    String status = result.summary != null ? result.summary.overallStatus : "No status";
                    Toast.makeText(MainActivity.this, "Status: " + status, Toast.LENGTH_LONG).show();
                } else {
                    Toast.makeText(MainActivity.this, "Gagal parsing response", Toast.LENGTH_LONG).show();
>>>>>>> 3e81cfb (Update 01 November 2025)
                }
            }

            @Override
<<<<<<< HEAD
            public void onFailure(Call<TestResponses> call, Throwable t) {
                Toast.makeText(MainActivity.this, "❌ Koneksi gagal: " + t.getMessage(), Toast.LENGTH_LONG).show();
                Log.e("API_ERROR", "Koneksi gagal", t);
            }
        });

        // ✅ Ambil data utama
        apiService.fetchData().enqueue(new Callback<ApiResponse>() {
            @Override
            public void onResponse(Call<ApiResponse> call, Response<ApiResponse> response) {
                if (response.isSuccessful() && response.body() != null) {
=======
            public void onFailure(Call<TestConnectionResponse> call, Throwable t) {
                Toast.makeText(MainActivity.this, "Koneksi gagal: " + t.getMessage(), Toast.LENGTH_LONG).show();
            }
        });

        // Ambil data utama
        apiService.fetchData("api_get_data.php").enqueue(new Callback<ApiResponse>() {
            @Override
            public void onResponse(Call<ApiResponse> call, Response<ApiResponse> response) {
                if (response.isSuccessful() && response.body() != null) {
                    Log.d("API_RAW", new Gson().toJson(response.body()));
>>>>>>> 3e81cfb (Update 01 November 2025)
                    for (ApiData data : response.body().getData()) {
                        Log.d("API_DATA", "No OP: " + data.getNoOp() + ", Customer: " + data.getCustomer() + ", Barang: " + data.getBarang());
                    }
                } else {
<<<<<<< HEAD
=======
                    try {
                        Log.e("API_ERROR", "Raw: " + response.errorBody().string());
                    } catch (Exception e) { }
>>>>>>> 3e81cfb (Update 01 November 2025)
                    Toast.makeText(MainActivity.this, "Data kosong / gagal parsing", Toast.LENGTH_SHORT).show();
                }
            }

            @Override
            public void onFailure(Call<ApiResponse> call, Throwable t) {
                Toast.makeText(MainActivity.this, "Gagal koneksi: " + t.getMessage(), Toast.LENGTH_LONG).show();
                Log.e("API", "onFailure: " + t.getMessage());
            }
        });

        // UI & Navigation
        setSupportActionBar(binding.appBarMain.toolbar);
        binding.appBarMain.fab.setOnClickListener(view -> {
            Intent intent = new Intent(MainActivity.this, BarcodeScannerActivity.class);
            startActivity(intent);
        });

        DrawerLayout drawer = binding.drawerLayout;
        NavigationView navigationView = binding.navView;

        mAppBarConfiguration = new AppBarConfiguration.Builder(
                R.id.nav_home, R.id.nav_gallery, R.id.nav_slideshow)
                .setOpenableLayout(drawer)
                .build();

        NavHostFragment navHostFragment = (NavHostFragment) getSupportFragmentManager()
                .findFragmentById(R.id.nav_host_fragment_content_main);
        NavController navController = navHostFragment.getNavController();
        NavigationUI.setupActionBarWithNavController(this, navController, mAppBarConfiguration);
        NavigationUI.setupWithNavController(navigationView, navController);
    }

    @Override
    public boolean onCreateOptionsMenu(Menu menu) {
        getMenuInflater().inflate(R.menu.main, menu);
        return true;
    }

    @Override
    public boolean onSupportNavigateUp() {
        NavHostFragment navHostFragment = (NavHostFragment) getSupportFragmentManager()
                .findFragmentById(R.id.nav_host_fragment_content_main);
        NavController navController = navHostFragment.getNavController();
        return NavigationUI.navigateUp(navController, mAppBarConfiguration)
                || super.onSupportNavigateUp();
    }
}
