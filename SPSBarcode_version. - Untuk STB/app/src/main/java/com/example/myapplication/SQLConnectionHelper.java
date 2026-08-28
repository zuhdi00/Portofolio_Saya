<<<<<<< HEAD



=======
>>>>>>> 3e81cfb (Update 01 November 2025)
package com.example.myapplication;

import android.os.StrictMode;
import android.util.Log;

import java.sql.Connection;
import java.sql.DriverManager;
import java.sql.SQLException;

public class SQLConnectionHelper {

    public static Connection connect() {
        Connection connection = null;

        try {
            StrictMode.ThreadPolicy policy = new StrictMode.ThreadPolicy.Builder().permitAll().build();
            StrictMode.setThreadPolicy(policy);

<<<<<<< HEAD
            String ip = "36.81.162.214"; // HANYA IP, tanpa port 8081
=======
            String ip = "supracor.co.id"; // Ganti IP ke domain
>>>>>>> 3e81cfb (Update 01 November 2025)
            String port = "1433";
            String db = "dbSopanusa";
            String username = "sa";
            String password = "supracor";

            Class.forName("net.sourceforge.jtds.jdbc.Driver");

            String connUrl = "jdbc:jtds:sqlserver://" + ip + ":" + port + "/" + db +
                    ";user=" + username + ";password=" + password + ";loginTimeout=5;useNTLMv2=true;";

            connection = DriverManager.getConnection(connUrl);
            Log.d("SQLConnection", "Koneksi berhasil ke SQL Server!");
        } catch (SQLException se) {
            Log.e("SQLConnection", "SQL Exception: " + se.getMessage());
        } catch (ClassNotFoundException e) {
            Log.e("SQLConnection", "Driver tidak ditemukan: " + e.getMessage());
        } catch (Exception e) {
            Log.e("SQLConnection", "Unexpected Error: " + e.getMessage());
        }

        return connection;
    }
}
