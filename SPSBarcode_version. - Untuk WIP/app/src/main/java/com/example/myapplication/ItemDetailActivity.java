package com.example.myapplication;

import android.os.Bundle;
import android.view.View;
import android.widget.TextView;

import androidx.appcompat.app.AppCompatActivity;

public class ItemDetailActivity extends AppCompatActivity {

    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        setContentView(R.layout.activity_item_detail); // layout XML yang sudah kita buat

        // Mengatur row "Nama Barang"
        View rowNamaBarang = findViewById(R.id.rowNamaBarang);
        TextView lblNama = rowNamaBarang.findViewById(R.id.label);
        TextView valNama = rowNamaBarang.findViewById(R.id.value);

        lblNama.setText("Nama Barang");
        valNama.setText("Contoh Nama");
    }
}

