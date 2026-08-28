// SQLScanRepository.java
package com.example.myapplication;

import android.util.Log;

import java.sql.Connection;
import java.sql.PreparedStatement;

public class SQLScanRepository {

    public static void insertScanResult(String kodeBarang) {
        Connection conn = SQLConnectionHelper.connect();
        if (conn != null) {
            try {
                String query = "INSERT INTO dbSopanusa.dbo.tbOP (cNoOP) VALUES (?)";
                PreparedStatement stmt = conn.prepareStatement(query);
                stmt.setString(1, kodeBarang);
                stmt.executeUpdate();
                stmt.close();
                Log.d("SQLScan", "Scan berhasil disimpan ke database.");
            } catch (Exception e) {
                Log.e("SQLScan", "Gagal insert: " + e.getMessage());
            }
        } else {
            Log.e("SQLScan", "Koneksi null, tidak bisa insert.");
        }
    }
}
