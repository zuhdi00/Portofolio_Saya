package com.example.myapplication;

import android.content.Context;
import android.util.Log;
import com.android.volley.Request;
import com.android.volley.RequestQueue;
import com.android.volley.Response;
import com.android.volley.VolleyError;
import com.android.volley.toolbox.JsonObjectRequest;
import com.android.volley.toolbox.Volley;
import org.json.JSONObject;

public class ApiHelper {
<<<<<<< HEAD
    private static final String API_URL = "http://36.81.162.214:8081/api_get_data.php";
=======
    // Pindahkan ke domain dan gunakan endpoint proxy di server supracor.co.id
    private static final String API_URL = "https://supracor.co.id/api/proxy_api_get_data.php";
>>>>>>> 3e81cfb (Update 01 November 2025)
    private final Context context;

    public ApiHelper(Context context) {
        this.context = context;
    }

    public void fetchDataFromApi(final ApiResponseCallback callback) {
        RequestQueue queue = Volley.newRequestQueue(context);

        JsonObjectRequest request = new JsonObjectRequest(
                Request.Method.GET,
                API_URL,
                null,
                new Response.Listener<JSONObject>() {
                    @Override
                    public void onResponse(JSONObject response) {
                        Log.d("API_SUCCESS", "Data diterima: " + response.toString());
                        callback.onSuccess(response);
                    }
                },
                new Response.ErrorListener() {
                    @Override
                    public void onErrorResponse(VolleyError error) {
<<<<<<< HEAD
                        Log.e("API_ERROR", "Gagal mengambil data: " + error.getMessage());
                        callback.onError(error.getMessage());
=======
                        String msg = (error == null || error.getMessage() == null) ? "Unknown error" : error.getMessage();
                        Log.e("API_ERROR", "Gagal mengambil data: " + msg);
                        callback.onError(msg);
>>>>>>> 3e81cfb (Update 01 November 2025)
                    }
                }
        );

        queue.add(request);
    }

    public interface ApiResponseCallback {
        void onSuccess(JSONObject response);
        void onError(String errorMessage);
    }
}

