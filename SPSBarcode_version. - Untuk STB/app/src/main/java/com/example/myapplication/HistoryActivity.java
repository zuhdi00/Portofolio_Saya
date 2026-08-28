package com.example.myapplication;


import android.content.Intent;
import android.os.Bundle;
import android.util.Log;
import android.widget.ArrayAdapter;
import android.widget.ListView;
import java.sql.Connection;
import android.widget.Toast;
import java.sql.Statement;
import java.sql.ResultSet;
import java.sql.SQLException;
import android.widget.Button;
import org.json.JSONException;
import org.json.JSONObject;

import java.util.Iterator;


import androidx.appcompat.app.AppCompatActivity;

import java.util.List;
import com.example.myapplication.model.ItemDetail;
import com.google.gson.Gson;

public class HistoryActivity extends AppCompatActivity {

    private ListView listHistory;



    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        setContentView(R.layout.activity_history);

        listHistory = findViewById(R.id.listHistory);

        Button btnInsert = findViewById(R.id.btnInsert);

        btnInsert.setOnClickListener(view -> {

            Toast.makeText(this, "Fitur insert dinonaktifkan", Toast.LENGTH_SHORT).show();

            /*
            Connection conn = SQLConnectionHelper.connect();
            if (conn != null) {
                try {
                    String sql = "INSERT INTO produk (nama_produk) VALUES ('Produk Uji Coba')";
                    Statement stmt = conn.createStatement();
                    int rows = stmt.executeUpdate(sql);

                    if (rows > 0) {
                        Log.d("SQL_INSERT", "Berhasil insert data");
                        Toast.makeText(this, "Insert berhasil!", Toast.LENGTH_SHORT).show();
                    }

                    conn.close();
                } catch (SQLException e) {
                    Log.e("SQL_ERROR", "Insert error: " + e.getMessage());
                    Toast.makeText(this, "Insert gagal: " + e.getMessage(), Toast.LENGTH_SHORT).show();
                }
            } else {
                Toast.makeText(this, "Koneksi gagal!", Toast.LENGTH_SHORT).show();
            }
            */
            refreshScanData();
        });

        refreshScanData();

        // Ambil data dari SharedPreferences
        ScanHistoryManager historyManager = new ScanHistoryManager(this);
        List<String> scanList = historyManager.getHistory();

        // Tampilkan di ListView
        ArrayAdapter<String> adapter = new ArrayAdapter<>(
                this,
                android.R.layout.simple_list_item_1,
                scanList
        );


        listHistory.setAdapter(adapter);
        Log.d("HistoryData", "Data: " + scanList.toString());



        /*
        // 🔌 Koneksi SQL Server
        Connection conn = SQLConnectionHelper.connect();
        if (conn != null) {
            Toast.makeText(this, "Koneksi sukses!", Toast.LENGTH_SHORT).show();

            // ✅ Tambahkan INSERT di sini
            try {
                String sql = "INSERT INTO produk (nama_produk) VALUES ('Produk Uji Coba')";
                Statement stmt = conn.createStatement();
                int rows = stmt.executeUpdate(sql);

                if (rows > 0) {
                    Log.d("SQL_INSERT", "Berhasil insert data");
                }

                conn.close();
            } catch (SQLException e) {
                Log.e("SQL_ERROR", "Insert error: " + e.getMessage());
            }

        } else {
            Toast.makeText(this, "Koneksi gagal!", Toast.LENGTH_SHORT).show();
        }
        */
    }

    private void refreshScanData() {
        // Ambil data dari SharedPreferences
        ScanHistoryManager historyManager = new ScanHistoryManager(this);
        List<String> scanList = historyManager.getHistory();

        // Buat list baru hanya berisi No STB untuk tampilan ListView
        List<String> stbList = new java.util.ArrayList<>();
        for (String item : scanList) {
            try {
                if (item.trim().startsWith("{")) {
                    // JSON, ambil cNoSTB
                    ItemDetail detail = new Gson().fromJson(item, ItemDetail.class);
                    stbList.add(detail.cNoSTB != null ? detail.cNoSTB : "(No STB kosong)");
                } else {
                    stbList.add(item); // fallback jika bukan JSON
                }
            } catch (Exception e) {
                stbList.add(item); // fallback jika gagal parse
            }
        }

        // Tampilkan hanya No STB di ListView
        ArrayAdapter<String> adapter = new ArrayAdapter<>(
                this,
                android.R.layout.simple_list_item_1,
                stbList
        );
        listHistory.setAdapter(adapter);

        // Listener klik item tetap gunakan scanList (bukan stbList)
        listHistory.setOnItemClickListener((parent, view, position, id) -> {
            String selectedItem = scanList.get(position);
            StringBuilder formatted = new StringBuilder();

            try {
                if (selectedItem.trim().startsWith("{")) {
                    // Jika formatnya JSON, parse ke ItemDetail
                    Gson gson = new Gson();
                    ItemDetail detail = gson.fromJson(selectedItem, ItemDetail.class);
                    if (detail != null) {
                        formatted.append("No STB       : ").append(nullToDash(detail.cNoSTB)).append("\n");
                        formatted.append("Nama Barang  : ").append(nullToDash(detail.cNamabrg)).append("\n");
                        formatted.append("No MC        : ").append(nullToDash(detail.cNoMC)).append("\n");
                        formatted.append("No OP        : ").append(nullToDash(detail.cNoOp)).append("\n");
                        formatted.append("Nama Customer: ").append(nullToDash(detail.cNama)).append("\n");
                        formatted.append("Ukuran       : ")
                                .append(detail.nPanjang).append(" x ")
                                .append(detail.nLebar).append(" x ")
                                .append(detail.nTinggi).append("\n");
                        formatted.append("Warna        : ").append(nullToDash(detail.cWarna)).append("\n");
                        formatted.append("Kualitas     : ")
                                .append(nullToDash(detail.cSub1)).append(" / ")
                                .append(nullToDash(detail.cSub2)).append(" / ")
                                .append(nullToDash(detail.cSub3)).append(" / ")
                                .append(nullToDash(detail.cSub4)).append(" / ")
                                .append(nullToDash(detail.cSub5)).append("\n");
                        formatted.append("Jmlh Barang  : ").append(detail.nQty).append("\n");
                        formatted.append("Total KG     : ").append(detail.nQtyKg).append("\n");
                        formatted.append("Tgl Serah    : ").append(nullToDash(detail.dTanggal)).append("\n");
                        formatted.append("Tgl Kirim    : ").append(nullToDash(detail.dTglkirim)).append("\n");
                        formatted.append("Lokasi       : ").append(nullToDash(detail.cRak)).append("\n");
                        formatted.append("Keterangan   : ").append(nullToDash(detail.cKeterangan)).append("\n");
                        formatted.append("Posted       : ").append(nullToDash(detail.lPosted)).append("\n");
                    } else {
                        formatted.append(selectedItem); // fallback
                    }
                } else {
                    formatted.append(selectedItem); // fallback jika bukan JSON
                }
            } catch (Exception e) {
                formatted.append(selectedItem); // fallback jika gagal parse
            }

            new androidx.appcompat.app.AlertDialog.Builder(this)
                    .setTitle("Detail Hasil Scan")
                    .setMessage(formatted.toString())
                    .setPositiveButton("OK", null)
                    .show();
        });
    }

    private String nullToDash(String s) {
        return (s == null || s.trim().isEmpty()) ? "-" : s;
    }


}
